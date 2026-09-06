<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaCatalogItem;
use App\Models\Convivencia\ConvivenciaComplaint;
use App\Models\Convivencia\ConvivenciaDailyLog;
use App\Models\Convivencia\ConvivenciaDerivation;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConvivenciaCaseService
{
    public function __construct(
        private readonly ConvivenciaStudentContextService $studentContextService,
        private readonly ConvivenciaSupportService $supportService,
    ) {}

    public function store(array $payload, User $user): ConvivenciaCase
    {
        return DB::transaction(function () use ($payload, $user) {
            $case = new ConvivenciaCase;
            $this->fillCase($case, $payload, $user, true);
            $case->save();

            if (array_key_exists('people', $payload)) {
                $this->supportService->syncCasePeople($case, (array) ($payload['people'] ?? []));
            }
            $this->supportService->logStatus($case, null, $case->status, $user, 'Caso creado.', 'created');

            return $this->loadCase($case, $user);
        });
    }

    public function update(ConvivenciaCase $case, array $payload, User $user): ConvivenciaCase
    {
        return DB::transaction(function () use ($case, $payload, $user) {
            $previousStatus = $case->status;

            $this->fillCase($case, $payload, $user, false);
            $case->save();

            if (array_key_exists('people', $payload)) {
                $this->supportService->syncCasePeople($case, (array) ($payload['people'] ?? []));
            }

            if ($previousStatus !== $case->status) {
                $this->supportService->logStatus($case, $previousStatus, $case->status, $user);
            }

            return $this->loadCase($case, $user);
        });
    }

    public function close(ConvivenciaCase $case, array $payload, User $user): ConvivenciaCase
    {
        $previousStatus = $case->status;

        $case->forceFill([
            'status' => 'cerrado',
            'resolution' => $payload['resolution'] ?? $case->resolution,
            'conclusion' => $payload['conclusion'] ?? $case->conclusion,
            'closed_at' => now(),
            'closed_by' => $user->id,
            'updated_by' => $user->id,
        ])->save();

        $this->supportService->logStatus($case, $previousStatus, 'cerrado', $user, 'Caso cerrado.');

        return $this->loadCase($case, $user);
    }

    public function createFromComplaint(ConvivenciaComplaint $complaint, array $payload, User $user): ConvivenciaCase
    {
        return DB::transaction(function () use ($complaint, $payload, $user): ConvivenciaCase {
            $lockedComplaint = ConvivenciaComplaint::query()->lockForUpdate()->findOrFail($complaint->id);
            if ($lockedComplaint->case_id) {
                throw ValidationException::withMessages([
                    'record' => 'Esta denuncia ya se encuentra vinculada a un caso.',
                ]);
            }

            $previousStatus = $lockedComplaint->status;
            $case = $this->store(array_merge([
                'academic_year_id' => $lockedComplaint->academic_year_id,
                'course_section_id' => $lockedComplaint->course_section_id,
                'student_profile_id' => $lockedComplaint->affected_student_id,
                'sourceable_type' => $lockedComplaint->getMorphClass(),
                'sourceable_id' => $lockedComplaint->id,
                'opened_at' => now()->format('Y-m-d H:i:s'),
                'happened_at' => $lockedComplaint->happened_at,
                'origin' => 'denuncia',
                'initial_report' => $lockedComplaint->report_text,
                'background' => $lockedComplaint->admissibility_result,
                'is_sensitive' => $lockedComplaint->is_sensitive,
                'people' => $this->peopleFromComplaint($lockedComplaint),
            ], $payload), $user);

            $lockedComplaint->forceFill([
                'case_id' => $case->id,
                'status' => 'derivada_a_caso',
                'updated_by' => $user->id,
            ])->save();

            $this->supportService->logStatus($lockedComplaint, $previousStatus, $lockedComplaint->status, $user, 'Denuncia convertida en caso.');

            return $this->loadCase($case, $user);
        });
    }

    public function createFromDerivation(ConvivenciaDerivation $derivation, array $payload, User $user): ConvivenciaCase
    {
        return DB::transaction(function () use ($derivation, $payload, $user): ConvivenciaCase {
            $lockedDerivation = ConvivenciaDerivation::query()->lockForUpdate()->findOrFail($derivation->id);
            if ($lockedDerivation->case_id) {
                throw ValidationException::withMessages([
                    'record' => 'Esta derivación ya se encuentra vinculada a un caso.',
                ]);
            }

            $initialReport = trim(collect([
                $lockedDerivation->motive,
                $lockedDerivation->narrative,
            ])->filter()->implode("\n\n"));
            $internalNotes = trim(collect([
                $lockedDerivation->response_text,
                $lockedDerivation->follow_up_notes,
            ])->filter()->implode("\n\n"));
            $inheritedFollowUp = $lockedDerivation->response_due_at?->greaterThanOrEqualTo(now())
                ? $lockedDerivation->response_due_at
                : null;

            $case = $this->store(array_merge([
                'academic_year_id' => $lockedDerivation->academic_year_id,
                'course_section_id' => $lockedDerivation->course_section_id,
                'student_profile_id' => $lockedDerivation->student_profile_id,
                'sourceable_type' => $lockedDerivation->getMorphClass(),
                'sourceable_id' => $lockedDerivation->id,
                'opened_at' => now()->format('Y-m-d H:i:s'),
                'happened_at' => $lockedDerivation->derived_at,
                'origin' => 'derivacion',
                'initial_report' => $initialReport,
                'background' => $lockedDerivation->narrative,
                'immediate_measures' => $lockedDerivation->suggested_actions,
                'internal_notes' => $internalNotes,
                'follow_up_due_at' => $inheritedFollowUp,
                'is_sensitive' => $lockedDerivation->is_sensitive,
                'people' => $this->peopleFromDerivation($lockedDerivation),
            ], $payload), $user);

            $lockedDerivation->forceFill([
                'case_id' => $case->id,
                'updated_by' => $user->id,
            ])->save();

            $this->supportService->logStatus(
                $lockedDerivation,
                $lockedDerivation->status,
                $lockedDerivation->status,
                $user,
                'Derivación convertida en caso.',
                'converted_to_case',
            );

            return $this->loadCase($case, $user);
        });
    }

    public function createFromDailyLog(ConvivenciaDailyLog $dailyLog, array $payload, User $user): ConvivenciaCase
    {
        $case = $this->store(array_merge([
            'academic_year_id' => $dailyLog->academic_year_id,
            'course_section_id' => $dailyLog->course_section_id,
            'student_profile_id' => $dailyLog->student_profile_id,
            'sourceable_type' => $dailyLog->getMorphClass(),
            'sourceable_id' => $dailyLog->id,
            'opened_at' => now()->format('Y-m-d H:i:s'),
            'happened_at' => $dailyLog->happened_at,
            'origin' => 'bitacora',
            'initial_report' => $dailyLog->description,
            'background' => $dailyLog->immediate_action,
            'place' => $dailyLog->place,
            'is_sensitive' => $dailyLog->is_sensitive,
            'people' => $this->peopleFromDailyLog($dailyLog),
        ], $payload), $user);

        $dailyLog->forceFill([
            'case_id' => $case->id,
            'status' => 'convertido_caso',
            'updated_by' => $user->id,
        ])->save();

        $this->supportService->logStatus($dailyLog, $dailyLog->getOriginal('status'), $dailyLog->status, $user, 'Bitácora convertida en caso.');

        return $case;
    }

    public function loadForExport(ConvivenciaCase $case, User $user): ConvivenciaCase
    {
        $accessService = app(ConvivenciaAccessService::class);
        $attachmentLoader = fn ($query) => $accessService
            ->applyAttachmentVisibility($query, $user)
            ->with('uploadedBy:id,name');
        $statusLogLoader = fn ($query) => $query->with('changedBy:id,name');

        return $case->fresh([
            'academicYear:id,name,year',
            'courseSection:id,display_name,education_level_id',
            'courseSection.educationLevel:id,name',
            'student:id,first_name,last_name,registered_name,rut,guardian_name,guardian_phone,guardian_email',
            'caseType:id,name',
            'classification:id,name',
            'subclassification:id,name',
            'criticality:id,name,color',
            'responsibleUser:id,name,email',
            'responsibleStaff:id,full_name',
            'closedBy:id,name',
            'createdBy:id,name',
            'updatedBy:id,name',
            'people' => function ($query) use ($accessService, $user) {
                if (! $user->isSuperAdmin() && ! $accessService->canViewSensitiveData($user)) {
                    $query->where('is_sensitive', false);
                }

                $query->with([
                    'student:id,first_name,last_name,registered_name,rut',
                    'user:id,name',
                    'staff:id,full_name',
                    'courseSection:id,display_name',
                ]);
            },
            'followUps.responsibleUser:id,name',
            'complaints' => function ($query) use ($accessService, $user, $attachmentLoader, $statusLogLoader) {
                $accessService->applyComplaintVisibility($query->getQuery(), $user);
                $query->with([
                    'affectedStudent:id,first_name,last_name,registered_name,rut',
                    'situationType:id,name',
                    'responsibleUser:id,name',
                    'attachments' => $attachmentLoader,
                    'statusLogs' => $statusLogLoader,
                ]);
            },
            'dailyLogs' => function ($query) use ($accessService, $user, $attachmentLoader, $statusLogLoader) {
                $accessService->applyDailyLogVisibility($query->getQuery(), $user);
                $query->with([
                    'student:id,first_name,last_name,registered_name,rut',
                    'courseSection:id,display_name',
                    'type:id,name',
                    'inspectorUser:id,name',
                    'inspectorStaff:id,full_name',
                    'attachments' => $attachmentLoader,
                    'statusLogs' => $statusLogLoader,
                ]);
            },
            'derivations' => function ($query) use ($accessService, $user, $attachmentLoader, $statusLogLoader) {
                $accessService->applyDerivationVisibility($query->getQuery(), $user);
                $query->with([
                    'student:id,first_name,last_name,registered_name,rut',
                    'courseSection:id,display_name',
                    'responsibleUser:id,name',
                    'destinationDepartment:id,name',
                    'destinationStaff:id,full_name',
                    'destinationUser:id,name',
                    'externalInstitution:id,name',
                    'attachments' => $attachmentLoader,
                    'statusLogs' => $statusLogLoader,
                ]);
            },
            'measures' => function ($query) use ($accessService, $user, $attachmentLoader, $statusLogLoader) {
                $accessService->applyMeasureVisibility($query->getQuery(), $user);
                $query->with([
                    'student:id,first_name,last_name,registered_name,rut',
                    'courseSection:id,display_name',
                    'type:id,name',
                    'responsibleUser:id,name',
                    'responsibleStaff:id,full_name',
                    'validator:id,name',
                    'attachments' => $attachmentLoader,
                    'statusLogs' => $statusLogLoader,
                ]);
            },
            'interviews' => function ($query) use ($accessService, $user, $attachmentLoader, $statusLogLoader) {
                $accessService->applyInterviewVisibility($query->getQuery(), $user);
                $query->with([
                    'student:id,first_name,last_name,registered_name,rut',
                    'courseSection:id,display_name',
                    'type:id,name',
                    'responsibleUser:id,name',
                    'responsibleStaff:id,full_name',
                    'participants.student:id,first_name,last_name,registered_name,rut',
                    'participants.user:id,name',
                    'participants.staff:id,full_name',
                    'attachments' => $attachmentLoader,
                    'statusLogs' => $statusLogLoader,
                ]);
            },
            'protocolActivations' => function ($query) use ($accessService, $user, $attachmentLoader, $statusLogLoader) {
                $accessService->applyProtocolActivationVisibility($query->getQuery(), $user);
                $query->with([
                    'protocol:id,name,code,version_label',
                    'currentStep:id,stage_name',
                    'currentActivationStep:id,stage_name',
                    'activatedBy:id,name',
                    'runtimeSteps.completedBy:id,name',
                    'runtimeSteps.parts.completedBy:id,name',
                    'runtimeParts.completedBy:id,name',
                    'logs.createdBy:id,name',
                    'attachments' => $attachmentLoader,
                    'statusLogs' => $statusLogLoader,
                ]);
            },
            'attachments' => $attachmentLoader,
            'statusLogs' => $statusLogLoader,
        ]);
    }

    private function fillCase(ConvivenciaCase $case, array $payload, User $user, bool $creating): void
    {
        $studentWasProvided = array_key_exists('student_profile_id', $payload);
        $student = ($creating || $studentWasProvided) && ! empty($payload['student_profile_id'])
            ? StudentProfile::query()->find($payload['student_profile_id'])
            : null;
        $enrollment = $creating && $student ? $this->studentContextService->currentEnrollment($student) : null;

        $attributes = array_intersect_key($payload, array_flip([
            'academic_year_id',
            'course_section_id',
            'student_profile_id',
            'responsible_user_id',
            'responsible_staff_id',
            'opened_at',
            'happened_at',
            'origin',
            'status',
            'case_type_label',
            'classification_label',
            'subclassification_label',
            'criticality_label',
            'place',
            'initial_report',
            'background',
            'immediate_measures',
            'safeguarding_measures',
            'internal_notes',
            'resolution',
            'conclusion',
            'follow_up_due_at',
            'is_sensitive',
        ]));

        if ($studentWasProvided) {
            $attributes['student_profile_id'] = $student?->id;
        }

        if (array_key_exists('responsible_user_id', $payload)) {
            $attributes['responsible_staff_id'] = User::query()
                ->whereKey($payload['responsible_user_id'])
                ->value('staff_id');
        }

        $catalogFields = [
            'case_type_item_id' => 'case_type_label',
            'classification_item_id' => 'classification_label',
            'subclassification_item_id' => 'subclassification_label',
            'criticality_item_id' => 'criticality_label',
        ];
        $catalogIds = collect($catalogFields)
            ->keys()
            ->filter(fn (string $key) => array_key_exists($key, $payload) && ! empty($payload[$key]))
            ->map(fn (string $key) => (int) $payload[$key])
            ->unique()
            ->values();
        $catalogItems = ConvivenciaCatalogItem::query()
            ->whereIn('id', $catalogIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        foreach ($catalogFields as $idField => $labelField) {
            if (! array_key_exists($idField, $payload)) {
                continue;
            }

            $catalogItem = ! empty($payload[$idField])
                ? $catalogItems->get((int) $payload[$idField])
                : null;
            $attributes[$idField] = $catalogItem?->id;

            if (! array_key_exists($labelField, $payload)) {
                $attributes[$labelField] = $catalogItem?->name;
            }
        }

        if (array_key_exists('is_sensitive', $attributes)) {
            $attributes['is_sensitive'] = (bool) $attributes['is_sensitive'];
        }

        if ($creating) {
            $attributes += [
                'academic_year_id' => $enrollment?->academic_year_id,
                'course_section_id' => $enrollment?->course_section_id,
                'student_profile_id' => $student?->id,
                'responsible_staff_id' => $user->staff_id,
                'status' => 'abierto',
                'is_sensitive' => (bool) ($student?->has_judicial_process ?? false),
            ];
        }

        $attributes['updated_by'] = $user->id;
        $case->fill($attributes);

        if ($creating) {
            $case->folio = $this->supportService->nextFolio('CAS', ConvivenciaCase::query());
            $case->created_by = $user->id;

            if ($case->sourceable_type === null && isset($payload['sourceable_type'], $payload['sourceable_id'])) {
                $case->sourceable_type = $payload['sourceable_type'];
                $case->sourceable_id = $payload['sourceable_id'];
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function peopleFromComplaint(ConvivenciaComplaint $complaint): array
    {
        $people = [];

        if ($complaint->affectedStudent) {
            $people[] = [
                'student_profile_id' => $complaint->affected_student_id,
                'person_type' => 'estudiante',
                'role_type' => 'afectado',
                'full_name' => $complaint->affectedStudent->registered_name_resolved,
                'identifier' => $complaint->affectedStudent->rut,
                'course_section_id' => $complaint->course_section_id,
                'is_sensitive' => true,
            ];
        }

        foreach (($complaint->involved_snapshot ?? []) as $item) {
            if (! empty($item['full_name'])) {
                $people[] = array_merge([
                    'person_type' => 'otro',
                    'role_type' => 'informante',
                    'is_sensitive' => $complaint->is_sensitive,
                ], $item);
            }
        }

        return $people;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function peopleFromDailyLog(ConvivenciaDailyLog $dailyLog): array
    {
        $people = [];

        if ($dailyLog->student) {
            $people[] = [
                'student_profile_id' => $dailyLog->student_profile_id,
                'person_type' => 'estudiante',
                'role_type' => 'afectado',
                'full_name' => $dailyLog->student->registered_name_resolved,
                'identifier' => $dailyLog->student->rut,
                'course_section_id' => $dailyLog->course_section_id,
                'is_sensitive' => $dailyLog->is_sensitive,
            ];
        }

        foreach (($dailyLog->involved_snapshot ?? []) as $item) {
            if (! empty($item['full_name'])) {
                $people[] = array_merge([
                    'person_type' => 'otro',
                    'role_type' => 'testigo',
                    'is_sensitive' => $dailyLog->is_sensitive,
                ], $item);
            }
        }

        return $people;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function peopleFromDerivation(ConvivenciaDerivation $derivation): array
    {
        if (! $derivation->student) {
            return [];
        }

        return [[
            'student_profile_id' => $derivation->student_profile_id,
            'person_type' => 'estudiante',
            'role_type' => 'afectado',
            'full_name' => $derivation->student->registered_name_resolved,
            'identifier' => $derivation->student->rut,
            'course_section_id' => $derivation->course_section_id,
            'is_sensitive' => $derivation->is_sensitive,
        ]];
    }

    private function loadCase(ConvivenciaCase $case, User $user): ConvivenciaCase
    {
        return $case->fresh([
            'academicYear:id,name,year',
            'courseSection:id,display_name,education_level_id',
            'courseSection.educationLevel:id,name',
            'student:id,first_name,last_name,registered_name,rut,guardian_name,guardian_phone,guardian_email',
            'caseType:id,name',
            'classification:id,name',
            'subclassification:id,name',
            'criticality:id,name,color',
            'responsibleUser:id,name,email',
            'responsibleStaff:id,full_name',
            'closedBy:id,name',
            'people',
            'followUps.responsibleUser:id,name',
            'derivations.destinationDepartment:id,name',
            'derivations.destinationStaff:id,full_name',
            'derivations.destinationUser:id,name',
            'derivations.externalInstitution:id,name',
            'measures.responsibleUser:id,name',
            'interviews.responsibleUser:id,name',
            'protocolActivations.protocol:id,name',
            'protocolActivations.currentStep:id,stage_name',
            'attachments' => fn ($query) => app(ConvivenciaAccessService::class)
                ->applyAttachmentVisibility($query, $user)
                ->with('uploadedBy:id,name'),
            'statusLogs.changedBy:id,name',
        ]);
    }
}
