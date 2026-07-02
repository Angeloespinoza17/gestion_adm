<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaCatalogItem;
use App\Models\Convivencia\ConvivenciaComplaint;
use App\Models\Convivencia\ConvivenciaDailyLog;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConvivenciaCaseService
{
    public function __construct(
        private readonly ConvivenciaStudentContextService $studentContextService,
        private readonly ConvivenciaSupportService $supportService,
    ) {
    }

    public function store(array $payload, User $user): ConvivenciaCase
    {
        return DB::transaction(function () use ($payload, $user) {
            $case = new ConvivenciaCase();
            $this->fillCase($case, $payload, $user, true);
            $case->save();

            $this->supportService->syncCasePeople($case, $payload['people'] ?? []);
            $this->supportService->logStatus($case, null, $case->status, $user, 'Caso creado.', 'created');

            return $this->loadCase($case);
        });
    }

    public function update(ConvivenciaCase $case, array $payload, User $user): ConvivenciaCase
    {
        return DB::transaction(function () use ($case, $payload, $user) {
            $previousStatus = $case->status;

            $this->fillCase($case, $payload, $user, false);
            $case->save();

            $this->supportService->syncCasePeople($case, $payload['people'] ?? []);

            if ($previousStatus !== $case->status) {
                $this->supportService->logStatus($case, $previousStatus, $case->status, $user);
            }

            return $this->loadCase($case);
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

        return $this->loadCase($case);
    }

    public function createFromComplaint(ConvivenciaComplaint $complaint, array $payload, User $user): ConvivenciaCase
    {
        $case = $this->store(array_merge([
            'academic_year_id' => $complaint->academic_year_id,
            'course_section_id' => $complaint->course_section_id,
            'student_profile_id' => $complaint->affected_student_id,
            'sourceable_type' => $complaint->getMorphClass(),
            'sourceable_id' => $complaint->id,
            'opened_at' => now()->format('Y-m-d H:i:s'),
            'happened_at' => $complaint->happened_at,
            'origin' => 'denuncia',
            'initial_report' => $complaint->report_text,
            'background' => $complaint->admissibility_result,
            'is_sensitive' => $complaint->is_sensitive,
            'people' => $this->peopleFromComplaint($complaint),
        ], $payload), $user);

        $complaint->forceFill([
            'case_id' => $case->id,
            'status' => 'derivada_a_caso',
            'updated_by' => $user->id,
        ])->save();

        $this->supportService->logStatus($complaint, $complaint->getOriginal('status'), $complaint->status, $user, 'Denuncia convertida en caso.');

        return $case;
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

    private function fillCase(ConvivenciaCase $case, array $payload, User $user, bool $creating): void
    {
        $student = !empty($payload['student_profile_id'])
            ? StudentProfile::query()->find($payload['student_profile_id'])
            : null;
        $summary = $student ? $this->studentContextService->studentSummary($student, $payload['opened_at'] ?? null, $user) : null;
        $enrollment = $student ? $this->studentContextService->currentEnrollment($student) : null;

        $caseType = !empty($payload['case_type_item_id'])
            ? ConvivenciaCatalogItem::query()->find($payload['case_type_item_id'])
            : null;
        $classification = !empty($payload['classification_item_id'])
            ? ConvivenciaCatalogItem::query()->find($payload['classification_item_id'])
            : null;
        $subclassification = !empty($payload['subclassification_item_id'])
            ? ConvivenciaCatalogItem::query()->find($payload['subclassification_item_id'])
            : null;
        $criticality = !empty($payload['criticality_item_id'])
            ? ConvivenciaCatalogItem::query()->find($payload['criticality_item_id'])
            : null;

        $case->fill([
            'academic_year_id' => $payload['academic_year_id'] ?? $enrollment?->academic_year_id,
            'course_section_id' => $payload['course_section_id'] ?? $enrollment?->course_section_id,
            'student_profile_id' => $student?->id,
            'case_type_item_id' => $caseType?->id,
            'classification_item_id' => $classification?->id,
            'subclassification_item_id' => $subclassification?->id,
            'criticality_item_id' => $criticality?->id,
            'responsible_user_id' => $payload['responsible_user_id'],
            'responsible_staff_id' => $payload['responsible_staff_id'] ?? $user->staff_id,
            'opened_at' => $payload['opened_at'],
            'happened_at' => $payload['happened_at'] ?? null,
            'origin' => $payload['origin'],
            'status' => $payload['status'] ?? ($case->status ?: 'abierto'),
            'case_type_label' => $payload['case_type_label'] ?? $caseType?->name,
            'classification_label' => $payload['classification_label'] ?? $classification?->name,
            'subclassification_label' => $payload['subclassification_label'] ?? $subclassification?->name,
            'criticality_label' => $payload['criticality_label'] ?? $criticality?->name,
            'place' => $payload['place'] ?? null,
            'initial_report' => $payload['initial_report'],
            'background' => $payload['background'] ?? null,
            'immediate_measures' => $payload['immediate_measures'] ?? null,
            'safeguarding_measures' => $payload['safeguarding_measures'] ?? null,
            'internal_notes' => $payload['internal_notes'] ?? null,
            'resolution' => $payload['resolution'] ?? $case->resolution,
            'conclusion' => $payload['conclusion'] ?? $case->conclusion,
            'follow_up_due_at' => $payload['follow_up_due_at'] ?? null,
            'is_sensitive' => (bool) ($payload['is_sensitive'] ?? $case->is_sensitive ?? $student?->has_judicial_process ?? false),
            'updated_by' => $user->id,
        ]);

        if ($creating) {
            $case->folio = $this->supportService->nextFolio('CAS', ConvivenciaCase::query());
            $case->created_by = $user->id;

            if ($case->sourceable_type === null && isset($payload['sourceable_type'], $payload['sourceable_id'])) {
                $case->sourceable_type = $payload['sourceable_type'];
                $case->sourceable_id = $payload['sourceable_id'];
            }

            if (($payload['people'] ?? []) === [] && $summary) {
                $case->setRelation('people', collect());
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
            if (!empty($item['full_name'])) {
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
            if (!empty($item['full_name'])) {
                $people[] = array_merge([
                    'person_type' => 'otro',
                    'role_type' => 'testigo',
                    'is_sensitive' => $dailyLog->is_sensitive,
                ], $item);
            }
        }

        return $people;
    }

    private function loadCase(ConvivenciaCase $case): ConvivenciaCase
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
            'attachments.uploadedBy:id,name',
            'statusLogs.changedBy:id,name',
        ]);
    }
}
