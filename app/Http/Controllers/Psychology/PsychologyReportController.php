<?php

namespace App\Http\Controllers\Psychology;

use App\Http\Controllers\Controller;
use App\Jobs\Psychology\GeneratePsychologyExport;
use App\Models\Psychology\PsychologyActivity;
use App\Models\Psychology\PsychologyCase;
use App\Models\Psychology\PsychologyExport;
use App\Models\Psychology\PsychologyReferral;
use App\Models\Psychology\PsychologyTask;
use App\Models\User;
use App\Services\Psychology\PsychologyAccessService;
use App\Services\Psychology\PsychologyAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PsychologyReportController extends Controller
{
    public function __construct(private readonly PsychologyAccessService $access, private readonly PsychologyAuditService $audit) {}

    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate(
            ['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'status' => ['nullable', 'string', 'max:40'], 'priority' => ['nullable', 'string', 'max:20'], 'professional_id' => ['nullable', 'integer'], 'nominal' => ['nullable', 'boolean']],
            ['nominal.boolean' => 'El campo de detalle nominal debe ser verdadero o falso.'],
        );
        $from = $filters['from'] ?? now()->startOfYear()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $user = $request->user();
        $personalScope = $this->access->isScopedPsychologist($user);
        $selectedProfessionalId = $personalScope ? $user->id : ($filters['professional_id'] ?? null);
        $selectedProfessional = $selectedProfessionalId
            ? User::query()->whereKey($selectedProfessionalId)->where('active', true)->first(['id', 'name'])
            : null;
        abort_if($selectedProfessionalId && ! $selectedProfessional, 422, 'La profesional seleccionada no está disponible.');
        $referrals = PsychologyReferral::query()->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->filter($filters);
        $this->access->applyReferralReportScope($referrals, $user);
        $referrals->when(! $personalScope && $selectedProfessionalId, function ($query) use ($selectedProfessionalId) {
            $query->where(function ($professional) use ($selectedProfessionalId) {
                $professional->where('assigned_user_id', $selectedProfessionalId)
                    ->orWhereHas('assignments', fn ($assignment) => $assignment->where('user_id', $selectedProfessionalId)->whereNull('ended_at'));
            });
        });
        $cases = PsychologyCase::query()->whereBetween('opened_at', [$from.' 00:00:00', $to.' 23:59:59'])->filter([
            'status' => $filters['status'] ?? null,
            'priority' => $filters['priority'] ?? null,
            'responsible_user_id' => null,
        ]);
        $this->access->applyCaseVisibility($cases, $user, true);
        $cases->when(! $personalScope && $selectedProfessionalId, function ($query) use ($selectedProfessionalId) {
            $query->where(function ($professional) use ($selectedProfessionalId) {
                $professional->where('responsible_user_id', $selectedProfessionalId)
                    ->orWhereHas('assignments', fn ($assignment) => $assignment->where('user_id', $selectedProfessionalId)->whereNull('ended_at'));
            });
        });
        $caseIds = (clone $cases)->pluck('id');
        $visibleStudents = (clone $referrals)->select('student_profile_id')
            ->union((clone $cases)->select('student_profile_id'));
        $uniqueStudents = DB::query()->fromSub($visibleStudents, 'visible_psychology_students')
            ->whereNotNull('student_profile_id')->distinct()->count('student_profile_id');
        $threshold = (int) (DB::table('psychology_settings')->where('key', 'anonymization_threshold')->value('value') ?: 5);
        $groups = (clone $referrals)->select('primary_reason as label', DB::raw('COUNT(*) as total'))->groupBy('primary_reason')->orderByDesc('total')->get()->map(fn ($row) => ['label' => $row->total < $threshold ? 'Grupo protegido' : $row->label, 'total' => (int) $row->total])->groupBy('label')->map(fn ($rows, $label) => ['label' => $label, 'total' => $rows->sum('total')])->values();
        $activities = PsychologyActivity::query()->whereIn('case_id', $caseIds)->whereBetween('activity_on', [$from, $to]);
        $this->access->applyActivityVisibility($activities, $user);
        $activities->when(! $personalScope && $selectedProfessionalId, fn ($query) => $query->where('responsible_user_id', $selectedProfessionalId));
        $tasks = PsychologyTask::query()->whereIn('case_id', $caseIds);
        $this->access->applyTaskVisibility($tasks, $user);
        $tasks->when(! $personalScope && $selectedProfessionalId, fn ($query) => $query->where('responsible_user_id', $selectedProfessionalId));
        $responseHours = (clone $referrals)->whereNotNull('first_reviewed_at')->get(['referred_at', 'first_reviewed_at'])->map(fn ($row) => $row->referred_at?->diffInMinutes($row->first_reviewed_at) / 60)->filter()->sort()->values();
        $median = $responseHours->isEmpty() ? 0 : ($responseHours->count() % 2 ? $responseHours[(int) floor($responseHours->count() / 2)] : ($responseHours[$responseHours->count() / 2 - 1] + $responseHours[$responseHours->count() / 2]) / 2);
        $monthly = (clone $referrals)->get(['created_at'])->groupBy(fn ($row) => $row->created_at->format('Y-m'))->map(fn ($rows, $month) => ['label' => $month, 'total' => $rows->count()])->values();
        $professionalScope = $personalScope || (bool) $selectedProfessional;
        $scopeProfessional = $personalScope ? $user : $selectedProfessional;
        $workload = $professionalScope
            ? collect([['label' => $scopeProfessional->name, 'total' => (clone $cases)->where('status', '!=', 'closed')->count()]])
            : (clone $cases)->join('users', 'users.id', '=', 'psychology_cases.responsible_user_id')->select('users.name as label', DB::raw('COUNT(*) as total'))->where('psychology_cases.status', '!=', 'closed')->groupBy('users.id', 'users.name')->get();
        $result = [
            'period' => ['from' => $from, 'to' => $to],
            'scope' => [
                'type' => $personalScope ? 'personal' : ($selectedProfessional ? 'professional' : 'institutional'),
                'professional_id' => $scopeProfessional?->id,
                'professional_name' => $scopeProfessional?->name,
            ],
            'counts' => [
                'referrals' => (clone $referrals)->count(), 'cases' => (clone $cases)->count(),
                'unique_students' => $uniqueStudents,
                'activities' => (clone $activities)->count(),
            ],
            'service_levels' => ['average_first_review_hours' => round((float) $responseHours->average(), 1), 'median_first_review_hours' => round((float) $median, 1), 'cases_without_activity' => (clone $cases)->where(fn ($q) => $q->whereNull('last_activity_at')->orWhere('last_activity_at', '<', now()->subDays((int) config('psychology.inactive_days', 14))))->count()],
            'by_reason' => $groups,
            'by_origin' => (clone $referrals)->select('origin_area as label', DB::raw('COUNT(*) as total'))->groupBy('origin_area')->get(),
            'by_status' => (clone $referrals)->select('status as label', DB::raw('COUNT(*) as total'))->groupBy('status')->get(),
            'by_priority' => (clone $referrals)->selectRaw('COALESCE(professional_priority, suggested_urgency) as label, COUNT(*) as total')->groupByRaw('COALESCE(professional_priority, suggested_urgency)')->get(),
            'workload' => $workload,
            'activities_by_type' => (clone $activities)->select('type as label', DB::raw('COUNT(*) as total'))->groupBy('type')->get(),
            'tasks' => ['pending_followups' => (clone $tasks)->where('type', 'follow_up')->whereIn('status', ['pending', 'in_progress'])->count(), 'overdue' => (clone $tasks)->whereIn('status', ['pending', 'in_progress', 'overdue'])->where('due_at', '<', now())->count()],
            'closures' => ['total' => DB::table('psychology_case_closures')->whereIn('case_id', $caseIds)->whereBetween('closed_at', [$from.' 00:00:00', $to.' 23:59:59'])->count(), 'by_type' => DB::table('psychology_case_closures')->whereIn('case_id', $caseIds)->whereBetween('closed_at', [$from.' 00:00:00', $to.' 23:59:59'])->select('closure_type as label', DB::raw('COUNT(*) as total'))->groupBy('closure_type')->get()],
            'external_referrals' => DB::table('psychology_external_referrals')->whereIn('case_id', $caseIds)->whereBetween('referred_on', [$from, $to])->select('status as label', DB::raw('COUNT(*) as total'))->groupBy('status')->get(),
            'reopenings' => DB::table('psychology_case_reopenings')->whereIn('case_id', $caseIds)->whereBetween('reopened_at', [$from.' 00:00:00', $to.' 23:59:59'])->count(),
            'reiterated_students' => (clone $referrals)->select('student_profile_id')->groupBy('student_profile_id')->havingRaw('COUNT(*) > 1')->get()->count(),
            'monthly_evolution' => $monthly, 'generated_at' => now(), 'nominal' => null,
        ];
        if (($filters['nominal'] ?? false)) {
            abort_unless($this->access->hasExplicitPermission($request->user(), 'psychology.reports.nominal') || $this->access->hasExplicitPermission($request->user(), 'psychology.sensitive.override'), 403);
            $result['nominal'] = (clone $referrals)->with('student:id,first_name,last_name,registered_name')->get(['id', 'code', 'student_profile_id', 'status', 'professional_priority', 'suggested_urgency', 'created_at']);
            $subject = new PsychologyReferral;
            $subject->setAttribute('id', 0);
            $this->audit->record('report.nominal_generated', $subject, $request->user(), [], [], json_encode(['from' => $from, 'to' => $to]));
        }

        return response()->json($result);
    }

    public function csv(Request $request): StreamedResponse
    {
        abort_unless($this->access->hasExplicitPermission($request->user(), 'psychology.reports.nominal') || $this->access->hasExplicitPermission($request->user(), 'psychology.sensitive.override'), 403);
        $filters = $request->validate(['professional_id' => ['nullable', 'integer', 'exists:users,id']]);
        $from = $request->date('from')?->startOfDay() ?: now()->startOfYear();
        $to = $request->date('to')?->endOfDay() ?: now()->endOfDay();
        $query = PsychologyReferral::query()->with('student:id,first_name,last_name,registered_name')->whereBetween('created_at', [$from, $to]);
        $this->access->applyReferralReportScope($query, $request->user());
        $query->when(! $this->access->isScopedPsychologist($request->user()) && ($filters['professional_id'] ?? null), function ($filtered) use ($filters) {
            $filtered->where(function ($professional) use ($filters) {
                $professional->where('assigned_user_id', $filters['professional_id'])
                    ->orWhereHas('assignments', fn ($assignment) => $assignment->where('user_id', $filters['professional_id'])->whereNull('ended_at'));
            });
        });
        $subject = new PsychologyReferral;
        $subject->setAttribute('id', 0);
        $this->audit->record('report.nominal_exported', $subject, $request->user());

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Código', 'Estudiante', 'Estado', 'Prioridad', 'Fecha'], ';');
            $query->chunkById(500, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [$row->code, $row->student?->registered_name_resolved, $row->status, $row->professional_priority ?: $row->suggested_urgency, $row->created_at?->format('d-m-Y H:i')], ';');
                }
            });
            fclose($out);
        }, 'reporte-psicologia-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    public function queue(Request $request): JsonResponse
    {
        abort_unless($this->access->hasExplicitPermission($request->user(), 'psychology.reports.nominal') || $this->access->hasExplicitPermission($request->user(), 'psychology.sensitive.override'), 403);
        $filters = $request->validate(['status' => ['nullable', 'string', 'max:40'], 'priority' => ['nullable', 'string', 'max:20'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'professional_id' => ['nullable', 'integer', 'exists:users,id']]);
        $export = PsychologyExport::query()->create(['format' => 'csv', 'status' => 'pending', 'filters' => $filters, 'created_by' => $request->user()->id]);
        GeneratePsychologyExport::dispatch($export->id);

        return response()->json(['message' => 'Exportación encolada. Recibirás una notificación segura al finalizar.', 'data' => $export], 202);
    }

    public function exportStatus(PsychologyExport $export): JsonResponse
    {
        abort_unless((int) $export->created_by === (int) request()->user()->id, 404);

        return response()->json(['data' => $export]);
    }

    public function downloadExport(PsychologyExport $export): StreamedResponse
    {
        abort_unless((int) $export->created_by === (int) request()->user()->id && $export->status === 'completed' && $export->private_path, 404);
        $subject = new PsychologyReferral;
        $subject->setAttribute('id', 0);
        $this->audit->record('report.queued_export_downloaded', $subject, request()->user());

        return Storage::disk(config('psychology.disk', 'local'))->download($export->private_path, 'reporte-psicologia-'.$export->id.'.csv', ['Cache-Control' => 'private, no-store']);
    }
}
