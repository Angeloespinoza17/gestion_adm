<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Models\RiskPrevention\RiskPreventionJointCommittee;
use App\Models\RiskPrevention\RiskPreventionStaffCompliance;
use App\Models\RiskPrevention\RiskPreventionStaffRequirementType;
use App\Models\RiskPrevention\RiskPreventionTraining;
use App\Models\RiskPrevention\RiskPreventionTrainingParticipant;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class RiskPreventionPersonnelController extends Controller
{
    public function matrix(Request $request): JsonResponse
    {
        $requirements = RiskPreventionStaffRequirementType::query()
            ->where('active', true)
            ->where('kind', 'document')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $trainingRequirementTypes = RiskPreventionStaffRequirementType::query()
            ->where('active', true)
            ->where('kind', 'training')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $complianceRequirementIds = $requirements
            ->pluck('id')
            ->merge($trainingRequirementTypes->pluck('id'));

        $staff = Staff::query()
            ->with([
                'cargo:id,name',
                'preventiveCompliances' => fn ($query) => $query
                    ->whereIn('requirement_type_id', $complianceRequirementIds)
                    ->with(['requirement', 'training:id,evidence_path,evidence_name']),
            ])
            ->when(
                $request->boolean('include_inactive'),
                fn ($query) => $query,
                fn ($query) => $query->where('active', true),
            )
            ->when(trim((string) $request->query('search')) !== '', function ($query) use ($request) {
                $search = trim((string) $request->query('search'));
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('rut', 'like', "%{$search}%")
                        ->orWhereHas('cargo', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('full_name')
            ->paginate(min(max((int) $request->query('per_page', 25), 5), 100));

        $requiredTrainings = RiskPreventionTraining::query()
            ->where('is_requirement', true)
            ->orderByDesc('training_date')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'training_type',
                'training_date',
                'modality',
                'requirement_type_id',
            ]);
        $standaloneRequiredTrainings = $requiredTrainings
            ->reject(fn (RiskPreventionTraining $training) => $training->requirement_type_id
                && $trainingRequirementTypes->contains('id', $training->requirement_type_id))
            ->values();

        $trainingParticipants = RiskPreventionTrainingParticipant::query()
            ->whereIn('training_id', $standaloneRequiredTrainings->pluck('id'))
            ->whereIn('staff_id', $staff->getCollection()->pluck('id'))
            ->get([
                'id',
                'training_id',
                'staff_id',
                'compliance_status',
                'issued_on',
                'expires_on',
                'notes',
            ])
            ->groupBy('staff_id')
            ->map(fn (Collection $participants) => $participants->keyBy('training_id'));

        $staff->getCollection()->transform(function (Staff $person) use (
            $requirements,
            $trainingRequirementTypes,
            $standaloneRequiredTrainings,
            $trainingParticipants,
        ) {
            $compliances = $person->preventiveCompliances->keyBy('requirement_type_id');
            $personTrainingParticipants = $trainingParticipants->get($person->id, collect());
            $pendingCatalogRequirements = $trainingRequirementTypes
                ->map(fn (RiskPreventionStaffRequirementType $requirement) => $this
                    ->serializeCatalogTrainingRequirement(
                        $requirement,
                        $compliances->get($requirement->id),
                    ))
                ->where('is_pending', true)
                ->values();
            $pendingScheduledTrainings = $standaloneRequiredTrainings
                ->map(fn (RiskPreventionTraining $training) => $this->serializeTrainingRequirement(
                    $training,
                    $personTrainingParticipants->get($training->id),
                ))
                ->where('is_pending', true)
                ->values();
            $pendingTrainings = $pendingCatalogRequirements
                ->concat($pendingScheduledTrainings)
                ->values();
            $requiredTrainingCount = $trainingRequirementTypes->count()
                + $standaloneRequiredTrainings->count();

            return [
                'id' => $person->id,
                'full_name' => $person->full_name,
                'rut' => $person->rut,
                'position' => $person->cargo?->name,
                'active' => $person->active,
                'training_requirements' => [
                    'required_count' => $requiredTrainingCount,
                    'pending_count' => $pendingTrainings->count(),
                    'completed_count' => $requiredTrainingCount - $pendingTrainings->count(),
                    'pending' => $pendingTrainings,
                ],
                'compliances' => $requirements
                    ->concat($trainingRequirementTypes)
                    ->mapWithKeys(function ($requirement) use ($compliances) {
                        $compliance = $compliances->get($requirement->id);

                        return [(string) $requirement->id => $compliance
                            ? $this->serializeCompliance($compliance)
                            : [
                                'id' => null,
                                'requirement_type_id' => $requirement->id,
                                'current_status' => 'pendiente',
                                'issued_on' => null,
                                'expires_on' => null,
                                'is_not_applicable' => false,
                                'has_evidence' => false,
                                'evidence_name' => null,
                                'notes' => null,
                                'training_id' => null,
                            ]];
                    })->all(),
            ];
        });

        return response()->json(array_merge($staff->toArray(), [
            'requirements' => $requirements,
            'training_requirements_count' => $trainingRequirementTypes->count()
                + $standaloneRequiredTrainings->count(),
            'summary' => $this->summary(
                $requirements->concat($trainingRequirementTypes),
                $standaloneRequiredTrainings,
            ),
        ]));
    }

    public function requirementTypes(Request $request): JsonResponse
    {
        $requirements = RiskPreventionStaffRequirementType::query()
            ->withCount(['compliances', 'trainings'])
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $requirements]);
    }

    public function storeRequirement(Request $request): JsonResponse
    {
        $payload = $this->validateRequirement($request);
        $requirement = RiskPreventionStaffRequirementType::query()->create(array_merge($payload, [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Requisito creado correctamente.',
            'data' => $requirement,
        ], 201);
    }

    public function updateRequirement(
        Request $request,
        RiskPreventionStaffRequirementType $requirement,
    ): JsonResponse {
        $requirement->update(array_merge(
            $this->validateRequirement($request, $requirement),
            ['updated_by' => $request->user()->id],
        ));

        return response()->json([
            'message' => 'Requisito actualizado correctamente.',
            'data' => $requirement->fresh()->loadCount(['compliances', 'trainings']),
        ]);
    }

    public function destroyRequirement(RiskPreventionStaffRequirementType $requirement): JsonResponse
    {
        if ($requirement->compliances()->exists() || $requirement->trainings()->exists()) {
            $requirement->update(['active' => false]);

            return response()->json([
                'message' => 'El requisito tenía historial y fue desactivado para conservarlo.',
                'archived' => true,
            ]);
        }

        $requirement->delete();

        return response()->json(['message' => 'Requisito eliminado correctamente.']);
    }

    public function storeCompliance(
        Request $request,
        Staff $staff,
        RiskPreventionStaffRequirementType $requirement,
    ): JsonResponse {
        $payload = $request->validate([
            'issued_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'is_not_applicable' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'evidence' => ['nullable', 'file', 'max:20480'],
        ]);

        $compliance = RiskPreventionStaffCompliance::query()->firstOrNew([
            'staff_id' => $staff->id,
            'requirement_type_id' => $requirement->id,
        ]);

        if (
            empty($payload['expires_on'])
            && ! empty($payload['issued_on'])
            && $requirement->validity_months
        ) {
            $payload['expires_on'] = Carbon::parse($payload['issued_on'])
                ->addMonthsNoOverflow($requirement->validity_months)
                ->toDateString();
        }

        $file = $request->file('evidence');
        if ($file instanceof UploadedFile) {
            if ($compliance->exists && $compliance->evidence_path) {
                Storage::disk('local')->delete($compliance->evidence_path);
            }

            $payload = array_merge($payload, $this->storeEvidence($file, $staff, $requirement));
        }

        if ($file instanceof UploadedFile) {
            $payload['training_id'] = null;
        }

        $compliance->fill(array_merge($payload, ['updated_by' => $request->user()->id]));

        if (! $compliance->exists) {
            $compliance->created_by = $request->user()->id;
        }

        $compliance->save();

        return response()->json([
            'message' => 'Cumplimiento actualizado correctamente.',
            'data' => $this->serializeCompliance($compliance->fresh()->load(['requirement', 'training'])),
        ], $compliance->wasRecentlyCreated ? 201 : 200);
    }

    public function destroyCompliance(RiskPreventionStaffCompliance $compliance): JsonResponse
    {
        $path = $compliance->evidence_path;
        $compliance->delete();

        if ($path) {
            Storage::disk('local')->delete($path);
        }

        return response()->json(['message' => 'Cumplimiento eliminado correctamente.']);
    }

    public function downloadCompliance(RiskPreventionStaffCompliance $compliance): StreamedResponse|JsonResponse
    {
        [$path, $name] = $this->complianceEvidence($compliance->loadMissing('training'));

        if (! $path || ! Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'La evidencia no está disponible.'], 404);
        }

        return Storage::disk('local')->download($path, $name ?: basename($path));
    }

    public function downloadStaffArchive(Staff $staff): BinaryFileResponse|JsonResponse
    {
        $compliances = RiskPreventionStaffCompliance::query()
            ->where('staff_id', $staff->id)
            ->with(['requirement', 'training'])
            ->orderBy('requirement_type_id')
            ->get();

        $files = $compliances
            ->map(function (RiskPreventionStaffCompliance $compliance) {
                [$path, $name] = $this->complianceEvidence($compliance);

                if (! $path || ! Storage::disk('local')->exists($path)) {
                    return null;
                }

                return [
                    'path' => Storage::disk('local')->path($path),
                    'name' => $this->safeArchiveName(
                        ($compliance->requirement?->name ?: 'Documento').' - '.($name ?: basename($path)),
                    ),
                ];
            })
            ->filter()
            ->values();

        if ($files->isEmpty()) {
            return response()->json([
                'message' => 'No hay documentos asociados a este funcionario.',
            ], 404);
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'prevent_personnel_');
        if ($temporaryPath === false) {
            return response()->json(['message' => 'No se pudo preparar el archivo.'], 500);
        }

        $zip = new ZipArchive;
        if ($zip->open($temporaryPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($temporaryPath);

            return response()->json(['message' => 'No se pudo preparar el archivo ZIP.'], 500);
        }

        $usedNames = [];
        foreach ($files as $file) {
            $name = $this->uniqueArchiveName($file['name'], $usedNames);
            $zip->addFile($file['path'], $name);
        }
        $zip->close();

        $downloadName = 'expediente-preventivo-'.Str::slug($staff->full_name ?: "funcionario-{$staff->id}").'.zip';

        return response()->download($temporaryPath, $downloadName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    public function committees(): JsonResponse
    {
        return response()->json([
            'data' => RiskPreventionJointCommittee::query()
                ->with(['staffMembers:id,full_name,rut,cargo_id', 'staffMembers.cargo:id,name'])
                ->orderByDesc('active')
                ->orderByDesc('starts_on')
                ->get(),
        ]);
    }

    public function storeCommittee(Request $request): JsonResponse
    {
        $payload = $this->validateCommittee($request);

        $committee = DB::transaction(function () use ($request, $payload) {
            $committee = RiskPreventionJointCommittee::query()->create(array_merge(
                collect($payload)->except('members')->all(),
                ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id],
            ));
            $this->syncCommitteeMembers($committee, $payload['members'] ?? []);

            return $committee;
        });

        return response()->json([
            'message' => 'Comité Paritario creado correctamente.',
            'data' => $committee->load(['staffMembers', 'staffMembers.cargo']),
        ], 201);
    }

    public function updateCommittee(
        Request $request,
        RiskPreventionJointCommittee $committee,
    ): JsonResponse {
        $payload = $this->validateCommittee($request);

        DB::transaction(function () use ($request, $payload, $committee) {
            $committee->update(array_merge(
                collect($payload)->except('members')->all(),
                ['updated_by' => $request->user()->id],
            ));
            $this->syncCommitteeMembers($committee, $payload['members'] ?? []);
        });

        return response()->json([
            'message' => 'Comité Paritario actualizado correctamente.',
            'data' => $committee->fresh()->load(['staffMembers', 'staffMembers.cargo']),
        ]);
    }

    public function destroyCommittee(RiskPreventionJointCommittee $committee): JsonResponse
    {
        if ($committee->staffMembers()->exists()) {
            $committee->update(['active' => false]);
            DB::table('prevent_joint_committee_staff')
                ->where('committee_id', $committee->id)
                ->update(['active' => false, 'updated_at' => now()]);

            return response()->json([
                'message' => 'El comité fue desactivado y su historial se conservó.',
                'archived' => true,
            ]);
        }

        $committee->delete();

        return response()->json(['message' => 'Comité eliminado correctamente.']);
    }

    private function validateRequirement(
        Request $request,
        ?RiskPreventionStaffRequirementType $requirement = null,
    ): array {
        return $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'code' => [
                'required',
                'string',
                'max:60',
                'regex:/^[A-Z0-9_\\-]+$/',
                Rule::unique('prevent_staff_requirement_types', 'code')->ignore($requirement?->id),
            ],
            'kind' => ['required', Rule::in(['training', 'document'])],
            'validity_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'requires_evidence' => ['required', 'boolean'],
            'is_mandatory' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function validateCommittee(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'members' => ['nullable', 'array'],
            'members.*.staff_id' => ['required', 'integer', 'distinct', 'exists:staff,id'],
            'members.*.representation' => ['required', Rule::in(['trabajadores', 'empleador'])],
            'members.*.member_role' => ['required', Rule::in(['titular', 'suplente'])],
            'members.*.position_name' => ['nullable', 'string', 'max:100'],
            'members.*.joined_on' => ['nullable', 'date'],
            'members.*.ended_on' => ['nullable', 'date', 'after_or_equal:members.*.joined_on'],
            'members.*.active' => ['required', 'boolean'],
        ]);
    }

    private function syncCommitteeMembers(
        RiskPreventionJointCommittee $committee,
        array $members,
    ): void {
        $memberIds = collect($members)->pluck('staff_id')->map(fn ($id) => (int) $id)->all();

        DB::table('prevent_joint_committee_staff')
            ->where('committee_id', $committee->id)
            ->when(
                $memberIds !== [],
                fn ($query) => $query->whereNotIn('staff_id', $memberIds),
            )
            ->update(['active' => false, 'updated_at' => now()]);

        foreach ($members as $member) {
            DB::table('prevent_joint_committee_staff')->updateOrInsert(
                [
                    'committee_id' => $committee->id,
                    'staff_id' => $member['staff_id'],
                ],
                [
                    'representation' => $member['representation'],
                    'member_role' => $member['member_role'],
                    'position_name' => $member['position_name'] ?? null,
                    'joined_on' => $member['joined_on'] ?? null,
                    'ended_on' => $member['ended_on'] ?? null,
                    'active' => $member['active'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }

    private function summary(Collection $requirements, Collection $requiredTrainings): array
    {
        $staffCount = Staff::query()->where('active', true)->count();
        $mandatoryRequirements = $requirements->where('is_mandatory', true);
        $documentExpected = $staffCount * $mandatoryRequirements->count();
        $compliances = RiskPreventionStaffCompliance::query()
            ->whereIn('requirement_type_id', $mandatoryRequirements->pluck('id'))
            ->whereHas('staff', fn ($query) => $query->where('active', true))
            ->with(['requirement', 'training:id,evidence_path'])
            ->get();

        $statuses = $compliances->countBy(fn ($item) => $item->current_status);
        $notApplicable = (int) $statuses->get('no_aplica', 0);
        $documentApplicableExpected = max(0, $documentExpected - $notApplicable);
        $registeredApplicable = $compliances->count() - $notApplicable;
        $documentPending = max(0, $documentApplicableExpected - $registeredApplicable)
            + (int) $statuses->get('pendiente', 0);

        $trainingExpected = $staffCount * $requiredTrainings->count();
        $trainingParticipants = RiskPreventionTrainingParticipant::query()
            ->whereIn('training_id', $requiredTrainings->pluck('id'))
            ->whereHas('staff', fn ($query) => $query->where('active', true))
            ->get(['compliance_status', 'expires_on']);
        $trainingExpired = $trainingParticipants
            ->filter(fn ($participant) => $participant->compliance_status === 'cumplido'
                && $participant->expires_on?->isBefore(today()))
            ->count();
        $trainingCompleted = $trainingParticipants
            ->filter(fn ($participant) => $participant->compliance_status === 'cumplido'
                && ! $participant->expires_on?->isBefore(today()))
            ->count();
        $trainingPending = max(0, $trainingExpected - $trainingParticipants->count())
            + $trainingParticipants
                ->where('compliance_status', '!=', 'cumplido')
                ->count();

        $expected = $documentApplicableExpected + $trainingExpected;
        $ok = (int) $statuses->get('vigente', 0) + $trainingCompleted;

        return [
            'staff_count' => $staffCount,
            'requirements_count' => $requirements->count() + $requiredTrainings->count(),
            'expected_count' => $expected,
            'ok_count' => $ok,
            'warning_count' => (int) $statuses->get('por_vencer', 0),
            'expired_count' => (int) $statuses->get('vencido', 0) + $trainingExpired,
            'pending_count' => $documentPending + $trainingPending,
            'compliance_percentage' => $expected > 0
                ? round(($ok / $expected) * 100, 1)
                : 100,
        ];
    }

    private function serializeCompliance(RiskPreventionStaffCompliance $compliance): array
    {
        return [
            'id' => $compliance->id,
            'requirement_type_id' => $compliance->requirement_type_id,
            'training_id' => $compliance->training_id,
            'issued_on' => $compliance->issued_on?->toDateString(),
            'expires_on' => $compliance->expires_on?->toDateString(),
            'is_not_applicable' => $compliance->is_not_applicable,
            'current_status' => $compliance->current_status,
            'has_evidence' => $compliance->has_evidence,
            'evidence_name' => $compliance->evidence_name ?: $compliance->training?->evidence_name,
            'notes' => $compliance->notes,
        ];
    }

    private function serializeCatalogTrainingRequirement(
        RiskPreventionStaffRequirementType $requirement,
        ?RiskPreventionStaffCompliance $compliance,
    ): array {
        $currentStatus = $compliance?->current_status ?: 'pendiente';

        return [
            'key' => "catalog-requirement-{$requirement->id}",
            'id' => null,
            'source' => 'catalog',
            'requirement_type_id' => $requirement->id,
            'name' => $requirement->name,
            'training_type' => null,
            'training_date' => null,
            'modality' => null,
            'validity_months' => $requirement->validity_months,
            'participant_id' => null,
            'participation_status' => $compliance ? $currentStatus : 'sin_registro',
            'current_status' => $currentStatus,
            'issued_on' => $compliance?->issued_on?->toDateString(),
            'expires_on' => $compliance?->expires_on?->toDateString(),
            'notes' => $compliance?->notes,
            'is_pending' => ! in_array($currentStatus, ['vigente', 'no_aplica'], true),
        ];
    }

    private function serializeTrainingRequirement(
        RiskPreventionTraining $training,
        ?RiskPreventionTrainingParticipant $participant,
    ): array {
        $participationStatus = $participant?->compliance_status ?: 'sin_registro';
        $currentStatus = $participationStatus === 'sin_registro' ? 'pendiente' : $participationStatus;

        if ($participationStatus === 'cumplido') {
            $currentStatus = $participant?->expires_on?->isBefore(today())
                ? 'vencido'
                : 'cumplido';
        }

        return [
            'key' => "scheduled-training-{$training->id}",
            'id' => $training->id,
            'source' => 'scheduled',
            'requirement_type_id' => null,
            'name' => $training->name,
            'training_type' => $training->training_type,
            'training_date' => $training->training_date?->toDateString(),
            'modality' => $training->modality,
            'validity_months' => null,
            'participant_id' => $participant?->id,
            'participation_status' => $participationStatus,
            'current_status' => $currentStatus,
            'issued_on' => $participant?->issued_on?->toDateString(),
            'expires_on' => $participant?->expires_on?->toDateString(),
            'notes' => $participant?->notes,
            'is_pending' => $currentStatus !== 'cumplido',
        ];
    }

    private function storeEvidence(
        UploadedFile $file,
        Staff $staff,
        RiskPreventionStaffRequirementType $requirement,
    ): array {
        $directory = "risk-prevention/personnel/{$staff->id}/{$requirement->id}";
        $storedName = now()->format('Ymd_His').'_'.uniqid().'_'.$file->getClientOriginalName();
        $path = $file->storeAs($directory, $storedName, 'local');

        return [
            'evidence_path' => $path,
            'evidence_name' => $file->getClientOriginalName(),
            'evidence_mime' => $file->getClientMimeType(),
            'evidence_size' => $file->getSize(),
        ];
    }

    private function complianceEvidence(RiskPreventionStaffCompliance $compliance): array
    {
        if ($compliance->evidence_path) {
            return [$compliance->evidence_path, $compliance->evidence_name];
        }

        return [
            $compliance->training?->evidence_path,
            $compliance->training?->evidence_name,
        ];
    }

    private function safeArchiveName(string $name): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._ -]+/', '_', Str::ascii($name));

        return Str::limit(trim((string) $safe), 180, '');
    }

    private function uniqueArchiveName(string $name, array &$usedNames): string
    {
        $candidate = $name ?: 'documento';
        $extension = pathinfo($candidate, PATHINFO_EXTENSION);
        $base = $extension ? substr($candidate, 0, -strlen($extension) - 1) : $candidate;
        $counter = 2;

        while (isset($usedNames[mb_strtolower($candidate)])) {
            $candidate = $base." ({$counter})".($extension ? ".{$extension}" : '');
            $counter++;
        }

        $usedNames[mb_strtolower($candidate)] = true;

        return $candidate;
    }
}
