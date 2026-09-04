<?php

namespace App\Http\Controllers\Psychology;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Psychology\PsychologyCatalogItem;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Psychology\PsychologyAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PsychologyCatalogController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('roles.permissions');
        $access = app(PsychologyAccessService::class);
        $nominalAccess = $access->canAccessNominalDomain($user);
        $catalogs = PsychologyCatalogItem::query()->where('active', true)->orderBy('sort_order')->get()->groupBy('type');
        $professionals = $nominalAccess
            ? User::query()->where('active', true)->where(function (Builder $query) {
                $query->whereHas('roles.permissions', fn (Builder $q) => $q->where('active', true)->whereIn('slug', ['psychology.cases.view_assigned', 'psychology.cases.view_all', 'psychology.sessions.create']))
                    ->orWhereHas('roles', fn (Builder $q) => $q->where('slug', 'super_admin')->where('active', true));
            })
                ->with('staff:id,full_name')->orderBy('name')->get(['id', 'name', 'staff_id'])->map(fn (User $professional) => ['id' => $professional->id, 'name' => $professional->staff?->full_name ?: $professional->name])
            : collect();
        $coordinationRecipients = $nominalAccess
            ? User::query()
                ->where('active', true)
                ->where('users.id', '!=', $user->id)
                ->where(fn (Builder $query) => $query->whereNotNull('staff_id')->orWhere('user_type', 'staff'))
                ->with(['staff:id,full_name,cargo_id', 'staff.cargo:id,name', 'cargo:id,name'])
                ->orderBy('name')
                ->get(['id', 'name', 'staff_id', 'cargo_id'])
                ->map(fn (User $recipient) => [
                    'id' => $recipient->id,
                    'name' => $recipient->staff?->full_name ?: $recipient->name,
                    'position' => $recipient->staff?->cargo?->name ?: $recipient->cargo?->name,
                ])
            : collect();
        $settings = DB::table('psychology_settings')->pluck('value', 'key');

        return response()->json(['catalogs' => $catalogs, 'professionals' => $professionals, 'coordination_recipients' => $coordinationRecipients, 'settings' => $settings, 'capabilities' => [
            'create_referral' => $nominalAccess && $user->hasPermission('psychology.referrals.create'), 'view_all_referrals' => $nominalAccess && $user->hasPermission('psychology.referrals.view_all'),
            'manage_referrals' => $nominalAccess && $user->hasPermission('psychology.referrals.update'),
            'view_referrals' => $nominalAccess && ($user->hasPermission('psychology.referrals.view_own') || $user->hasPermission('psychology.referrals.view_all')),
            'view_cases' => $nominalAccess && ($user->hasPermission('psychology.cases.view_assigned') || $user->hasPermission('psychology.cases.view_all')),
            'assign' => $nominalAccess && $user->hasPermission('psychology.referrals.assign'), 'create_case' => $nominalAccess && $user->hasPermission('psychology.cases.create'),
            'edit_case' => $nominalAccess && ($user->hasPermission('psychology.sessions.create') || $user->hasPermission('psychology.cases.reassign')),
            'reassign_case' => $nominalAccess && $user->hasPermission('psychology.cases.reassign'), 'close_case' => $nominalAccess && $user->hasPermission('psychology.cases.close'),
            'reopen_case' => $nominalAccess && $user->hasPermission('psychology.cases.reopen'),
            'create_activity' => $nominalAccess && $user->hasPermission('psychology.sessions.create'), 'view_private' => $access->hasExplicitPermission($user, 'psychology.sessions.view_private'),
            'risk' => $nominalAccess && $user->hasPermission('psychology.risk.view'), 'reports_aggregate' => $user->hasPermission('psychology.reports.aggregate'),
            'reports_nominal' => $access->hasExplicitPermission($user, 'psychology.reports.nominal') || $access->hasExplicitPermission($user, 'psychology.sensitive.override'),
            'documents_upload' => $nominalAccess && $user->hasPermission('psychology.documents.upload'), 'documents_download' => $nominalAccess && $user->hasPermission('psychology.documents.download'),
            'config' => $user->hasPermission('psychology.config.manage'), 'audit' => $user->hasPermission('psychology.audit.view'),
            'full_domain' => $user->isSuperAdmin(),
            'personal_scope' => $access->isScopedPsychologist($user),
        ]]);
    }

    public function students(Request $request): JsonResponse
    {
        abort_unless(app(PsychologyAccessService::class)->canAccessNominalDomain($request->user()), 403);
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id']]);
        $year = AcademicYear::query()->where('is_active', true)->first();
        $students = StudentProfile::query()->where('general_status', 'activo')
            ->when($validated['search'] ?? null, function (Builder $q, string $search) {
                $q->where(fn (Builder $s) => $s->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")->orWhere('registered_name', 'like', "%{$search}%")->orWhere('rut', 'like', "%{$search}%"));
            })
            ->when($validated['course_section_id'] ?? null, fn (Builder $q, $course) => $q->whereHas('enrollments', fn (Builder $e) => $e->where('course_section_id', $course)))
            ->with(['enrollments' => fn ($q) => $q->when($year, fn ($e) => $e->where('academic_year_id', $year->id))->with('courseSection.educationLevel')])
            ->withExists(['psychologyCases as active_case' => fn (Builder $q) => $q->where('status', '!=', 'closed')])
            ->withCount(['psychologyReferrals as pending_referrals' => fn (Builder $q) => $q->whereIn('status', ['submitted', 'under_review', 'information_requested', 'accepted'])])
            ->orderBy('last_name')->limit(20)->get();
        $data = $students->map(function (StudentProfile $student) use ($year) {
            $enrollment = $year ? $student->preferredEnrollment($year) : $student->enrollments->first();

            return ['id' => $student->id, 'name' => $student->registered_name_resolved, 'rut' => $student->rut,
                'course_section_id' => $enrollment?->course_section_id, 'course' => $enrollment?->snapshot_course_display_name,
                'cycle' => $enrollment?->courseSection?->educationLevel?->type, 'enrollment_status' => $enrollment?->enrollment_status,
                'guardian_name' => $student->guardian_name, 'guardian_phone' => $student->guardian_phone,
                'active_case' => (bool) $student->active_case,
                'pending_referrals' => (int) $student->pending_referrals];
        });

        return response()->json(['data' => $data]);
    }
}
