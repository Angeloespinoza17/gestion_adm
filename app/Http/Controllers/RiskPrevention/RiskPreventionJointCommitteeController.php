<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Models\RiskPrevention\RiskPreventionJointCommittee;
use App\Models\RiskPrevention\RiskPreventionJointCommitteeDocument;
use App\Services\RiskPrevention\RiskPreventionAccessService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RiskPreventionJointCommitteeController extends Controller
{
    public function __construct(
        private readonly RiskPreventionAccessService $accessService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewCommittee($request->user()), 403);

        $committees = RiskPreventionJointCommittee::query()
            ->with([
                'staffMembers:id,full_name,rut,cargo_id',
                'staffMembers.cargo:id,name',
                'documents.uploadedBy:id,name',
                'trainings' => fn ($query) => $query
                    ->with([
                        'participants.staff:id,full_name,rut',
                        'requirement:id,name,kind',
                    ]),
            ])
            ->orderByDesc('active')
            ->orderByDesc('starts_on')
            ->get()
            ->map(fn (RiskPreventionJointCommittee $committee) => $this->serializeCommittee($committee));

        $user = $request->user();

        return response()->json([
            'data' => $committees,
            'permissions' => [
                'can_view' => $this->accessService->canViewCommittee($user),
                'can_upload_minutes' => $this->accessService->canUploadCommitteeMinutes($user),
                'can_manage_committee' => $user?->hasPermission('gestionar_prevencion_riesgos') ?? false,
            ],
        ]);
    }

    public function storeDocument(
        Request $request,
        RiskPreventionJointCommittee $committee,
    ): JsonResponse {
        abort_unless($this->accessService->canUploadCommitteeMinutes($request->user()), 403);

        $payload = $request->validate([
            'document_type' => [
                'required',
                Rule::in([
                    RiskPreventionJointCommitteeDocument::TYPE_CONSTITUTION,
                    RiskPreventionJointCommitteeDocument::TYPE_MONTHLY_MINUTES,
                ]),
            ],
            'document_date' => ['required', 'date'],
            'title' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'file' => [
                'required',
                'file',
                'max:20480',
                'mimes:pdf,doc,docx,jpg,jpeg,png',
            ],
        ]);

        $documentDate = Carbon::parse($payload['document_date']);
        $periodKey = $payload['document_type'] === RiskPreventionJointCommitteeDocument::TYPE_MONTHLY_MINUTES
            ? $documentDate->format('Y-m')
            : RiskPreventionJointCommitteeDocument::TYPE_CONSTITUTION;

        if (
            RiskPreventionJointCommitteeDocument::query()
                ->where('committee_id', $committee->id)
                ->where('document_type', $payload['document_type'])
                ->where('period_key', $periodKey)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'document_date' => $payload['document_type'] === RiskPreventionJointCommitteeDocument::TYPE_MONTHLY_MINUTES
                    ? 'Ya existe un acta mensual para el período seleccionado.'
                    : 'Este comité ya tiene registrada su acta de constitución.',
            ]);
        }

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $path = $file->storeAs(
            "risk-prevention/joint-committees/{$committee->id}",
            Str::uuid().'.'.$extension,
            'local',
        );

        try {
            $document = RiskPreventionJointCommitteeDocument::query()->create([
                'committee_id' => $committee->id,
                'document_type' => $payload['document_type'],
                'period_key' => $periodKey,
                'document_date' => $documentDate->toDateString(),
                'title' => filled($payload['title'] ?? null)
                    ? trim((string) $payload['title'])
                    : $this->defaultTitle($payload['document_type'], $documentDate),
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'notes' => $payload['notes'] ?? null,
                'uploaded_by' => $request->user()?->id,
            ]);
        } catch (QueryException $exception) {
            Storage::disk('local')->delete($path);

            throw ValidationException::withMessages([
                'document_date' => 'Ya existe un acta para el período seleccionado.',
            ]);
        }

        return response()->json([
            'message' => 'Acta cargada correctamente y resguardada en almacenamiento privado.',
            'data' => $document->load('uploadedBy:id,name'),
        ], 201);
    }

    public function downloadDocument(
        RiskPreventionJointCommittee $committee,
        RiskPreventionJointCommitteeDocument $document,
    ): StreamedResponse|JsonResponse {
        abort_unless($this->accessService->canViewCommittee(request()->user()), 403);
        abort_unless((int) $document->committee_id === (int) $committee->id, 404);

        if (! Storage::disk('local')->exists($document->file_path)) {
            return response()->json(['message' => 'El archivo del acta no está disponible.'], 404);
        }

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }

    private function serializeCommittee(RiskPreventionJointCommittee $committee): array
    {
        $documents = $committee->documents;
        $currentYear = now()->year;
        $monthlyDocuments = $documents
            ->where('document_type', RiskPreventionJointCommitteeDocument::TYPE_MONTHLY_MINUTES);
        $currentYearPeriods = $monthlyDocuments
            ->filter(fn ($document) => $document->document_date?->year === $currentYear)
            ->pluck('period_key')
            ->flip();
        $expectedPeriods = $this->expectedMonthlyPeriods($committee, $currentYear);
        $activeMembers = $committee->staffMembers->where('pivot.active', true);

        return array_merge($committee->toArray(), [
            'summary' => [
                'active_members' => $activeMembers->count(),
                'constitution_uploaded' => $documents->contains(
                    'document_type',
                    RiskPreventionJointCommitteeDocument::TYPE_CONSTITUTION,
                ),
                'monthly_minutes_current_year' => $currentYearPeriods->count(),
                'expected_months_current_year' => count($expectedPeriods),
                'missing_months' => collect($expectedPeriods)
                    ->reject(fn (string $period) => $currentYearPeriods->has($period))
                    ->values(),
                'committee_trainings' => $committee->trainings->count(),
                'trained_participants' => $committee->trainings
                    ->flatMap->participants
                    ->where('compliance_status', 'cumplido')
                    ->count(),
            ],
        ]);
    }

    /** @return array<int, string> */
    private function expectedMonthlyPeriods(RiskPreventionJointCommittee $committee, int $year): array
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfMonth();
        $yearEnd = Carbon::create($year, 12, 1)->startOfMonth();
        $from = $committee->starts_on?->copy()->startOfMonth()->max($yearStart) ?? $yearStart;
        $to = ($committee->ends_on?->copy()->startOfMonth() ?? $yearEnd)->min($yearEnd);

        if ($year === now()->year) {
            $to = $to->min(now()->startOfMonth());
        }

        if ($from->gt($to)) {
            return [];
        }

        $periods = [];
        for ($cursor = $from->copy(); $cursor->lte($to); $cursor->addMonth()) {
            $periods[] = $cursor->format('Y-m');
        }

        return $periods;
    }

    private function defaultTitle(string $documentType, Carbon $documentDate): string
    {
        if ($documentType === RiskPreventionJointCommitteeDocument::TYPE_CONSTITUTION) {
            return 'Acta de constitución del Comité Paritario';
        }

        return 'Acta mensual '.$documentDate->locale('es')->translatedFormat('F Y');
    }
}
