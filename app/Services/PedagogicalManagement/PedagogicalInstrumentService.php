<?php

namespace App\Services\PedagogicalManagement;

use App\Enums\PedagogicalManagement\InstrumentStatus;
use App\Enums\PedagogicalManagement\InstrumentWorkflowStatus;
use App\Models\LibroDigital\School;
use App\Models\PedagogicalManagement\PedagogicalInstrument;
use App\Models\PedagogicalManagement\PedagogicalInstrumentFile;
use App\Models\PedagogicalManagement\PedagogicalInstrumentObjective;
use App\Models\User;
use App\Services\LibroDigital\AuditEventWriter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PedagogicalInstrumentService
{
    public function __construct(
        private readonly PedagogicalInstrumentFileService $files,
        private readonly AuditEventWriter $audit,
    ) {}

    /** @param array<string,mixed> $data */
    public function create(array $data, UploadedFile $uploadedFile, School $school, User $actor, Request $request): array
    {
        $metadata = $this->files->inspect($uploadedFile, $school->id);
        [$instrument, $file] = DB::transaction(function () use ($data, $uploadedFile, $school, $actor, $metadata): array {
            $instrument = PedagogicalInstrument::query()->create([
                ...Arr::only($data, $this->editableFields()),
                'school_id' => $school->id,
                'owner_user_id' => $data['owner_user_id'] ?? $actor->id,
                'status' => InstrumentStatus::Uploaded,
                'workflow_status' => InstrumentWorkflowStatus::Submitted,
                'submitted_at' => now(),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $instrument->courses()->sync(array_values(array_unique(array_map('intval', $data['course_ids']))));
            $this->syncManualObjectives($instrument, (array) ($data['learning_objective_ids'] ?? []), $actor);
            $file = $this->files->store($instrument, $uploadedFile, $metadata, $actor);
            $instrument->forceFill(['status' => InstrumentStatus::Uploaded])->save();

            return [$instrument, $file];
        }, 3);

        $this->audit->write(
            'pedagogical.instrument.imported', 'import', $instrument, actor: $actor,
            schoolId: $school->id, academicYearId: $instrument->academic_year_id,
            after: ['instrument_uuid' => $instrument->uuid, 'file_uuid' => $file->uuid, 'sha256' => $file->sha256],
            request: $request,
        );

        return [$instrument, $file];
    }

    /** @param array<string,mixed> $data */
    public function update(PedagogicalInstrument $instrument, array $data, User $actor, Request $request): PedagogicalInstrument
    {
        $before = $instrument->only($this->editableFields());
        DB::transaction(function () use ($instrument, $data, $actor): void {
            $instrument->fill([...Arr::only($data, $this->editableFields()), 'updated_by' => $actor->id])->save();
            if (array_key_exists('course_ids', $data)) {
                $instrument->courses()->sync(array_values(array_unique(array_map('intval', $data['course_ids']))));
            }
            if (array_key_exists('learning_objective_ids', $data)) {
                $instrument->objectives()->where('origin', 'manual')->delete();
                $this->syncManualObjectives($instrument, (array) $data['learning_objective_ids'], $actor);
            }
        }, 3);
        $this->audit->write(
            'pedagogical.instrument.updated', 'update', $instrument, actor: $actor,
            schoolId: $instrument->school_id, academicYearId: $instrument->academic_year_id,
            before: $before, after: $instrument->fresh()->only($this->editableFields()), request: $request,
        );

        return $instrument->fresh();
    }

    public function addFileVersion(PedagogicalInstrument $instrument, UploadedFile $uploadedFile, User $actor, Request $request): PedagogicalInstrumentFile
    {
        if ($instrument->workflow_status !== InstrumentWorkflowStatus::RectificationRequested) {
            throw ValidationException::withMessages([
                'file' => 'Sólo puedes cargar una nueva versión cuando Coordinación Académica haya solicitado una rectificación.',
            ]);
        }
        $metadata = $this->files->inspect($uploadedFile, (int) $instrument->school_id);
        $file = DB::transaction(function () use ($instrument, $uploadedFile, $metadata, $actor): PedagogicalInstrumentFile {
            $file = $this->files->store($instrument, $uploadedFile, $metadata, $actor);
            $instrument->forceFill([
                'status' => InstrumentStatus::Uploaded,
                'workflow_status' => InstrumentWorkflowStatus::Resubmitted,
                'submitted_at' => now(),
                'approved_at' => null,
                'updated_by' => $actor->id,
            ])->save();

            return $file;
        }, 3);
        $this->audit->write(
            'pedagogical.instrument.file_version_uploaded', 'upload_version', $instrument, actor: $actor,
            schoolId: $instrument->school_id, academicYearId: $instrument->academic_year_id,
            after: ['file_uuid' => $file->uuid, 'version' => $file->version, 'sha256' => $file->sha256], request: $request,
        );

        return $file;
    }

    public function archive(PedagogicalInstrument $instrument, User $actor, Request $request): void
    {
        $instrument->forceFill([
            'status' => InstrumentStatus::Archived,
            'workflow_status' => InstrumentWorkflowStatus::Archived,
            'archived_at' => now(),
            'deleted_at' => now(),
            'updated_by' => $actor->id,
        ])->save();
        $this->audit->write(
            'pedagogical.instrument.archived', 'archive', $instrument, actor: $actor,
            schoolId: $instrument->school_id, academicYearId: $instrument->academic_year_id,
            after: ['status' => 'archived'], request: $request,
        );
    }

    /** @return list<string> */
    private function editableFields(): array
    {
        return [
            'academic_year_id', 'owner_user_id', 'subject_id', 'unit_id', 'title', 'grade_label', 'instrument_type',
            'evaluation_purpose', 'work_modality', 'application_date', 'duration_minutes',
            'declared_total_points', 'passing_percentage', 'weighting_percentage', 'minimum_grade',
            'maximum_grade', 'accessibility_measures', 'notes',
        ];
    }

    /** @param list<int|string> $objectiveIds */
    private function syncManualObjectives(PedagogicalInstrument $instrument, array $objectiveIds, User $actor): void
    {
        foreach (array_values(array_unique(array_map('intval', $objectiveIds))) as $objectiveId) {
            PedagogicalInstrumentObjective::query()->create([
                'instrument_id' => $instrument->id,
                'learning_objective_id' => $objectiveId,
                'origin' => 'manual',
                'confirmation_status' => 'confirmed',
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
            ]);
        }
    }
}
