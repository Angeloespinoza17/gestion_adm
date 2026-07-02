<?php

namespace App\Services\Infirmary;

use App\Models\Infirmary\InfirmaryAttention;
use App\Models\Infirmary\InfirmaryAttentionCall;
use App\Models\Infirmary\InfirmaryAttentionFollowUp;
use App\Models\Infirmary\InfirmaryAttentionReferral;
use App\Models\Infirmary\InfirmaryAttentionTreatment;
use App\Models\Infirmary\InfirmaryMedication;
use App\Models\Infirmary\InfirmaryMedicationAdministration;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InfirmaryAttentionService
{
    public function __construct(
        private readonly InfirmaryStudentContextService $studentContextService,
        private readonly InfirmaryMedicationStockService $stockService,
    ) {
    }

    public function store(array $payload, User $user): InfirmaryAttention
    {
        return DB::transaction(function () use ($payload, $user) {
            $attention = new InfirmaryAttention();
            $this->fillAttention($attention, $payload, $user, true);
            $attention->save();

            $this->syncNestedData($attention, $payload, $user);

            return $this->loadAttention($attention);
        });
    }

    public function update(InfirmaryAttention $attention, array $payload, User $user): InfirmaryAttention
    {
        return DB::transaction(function () use ($attention, $payload, $user) {
            $attention->load(['administrations.medication', 'treatments', 'referrals', 'calls', 'followUps']);

            $this->revertAdministrations($attention, $user);
            $attention->administrations()->delete();
            $attention->treatments()->delete();
            $attention->referrals()->delete();
            $attention->calls()->delete();
            $attention->followUps()->delete();

            $this->fillAttention($attention, $payload, $user, false);
            $attention->save();

            $this->syncNestedData($attention, $payload, $user);

            return $this->loadAttention($attention);
        });
    }

    public function finalize(InfirmaryAttention $attention, User $user, array $payload = []): InfirmaryAttention
    {
        $attention->forceFill([
            'status' => 'finalizada',
            'finalized_at' => now(),
            'attention_duration_minutes' => $payload['attention_duration_minutes'] ?? $attention->attention_duration_minutes,
            'observations' => $payload['observations'] ?? $attention->observations,
            'updated_by' => $user->id,
        ])->save();

        return $this->loadAttention($attention);
    }

    public function delete(InfirmaryAttention $attention, User $user): void
    {
        DB::transaction(function () use ($attention, $user) {
            $attention->load('administrations.medication');
            $this->revertAdministrations($attention, $user, 'Reversa por eliminación de atención');
            $attention->delete();
        });
    }

    private function fillAttention(InfirmaryAttention $attention, array $payload, User $user, bool $creating): void
    {
        $student = StudentProfile::query()->findOrFail($payload['student_profile_id']);
        $attendedAt = Carbon::parse($payload['attended_at']);
        $summary = $this->studentContextService->studentSummary($student, $attendedAt);
        $currentEnrollment = $this->studentContextService->currentEnrollment($student);
        $teacherId = $payload['teacher_staff_id'] ?? null;
        $teacher = $teacherId
            ? \App\Models\Staff::query()->find($teacherId)
            : $this->studentContextService->teacherForCourse($currentEnrollment?->courseSection);

        $attention->fill([
            'student_profile_id' => $student->id,
            'academic_year_id' => $payload['academic_year_id'] ?? $currentEnrollment?->academic_year_id,
            'course_section_id' => $payload['course_section_id'] ?? $currentEnrollment?->course_section_id,
            'teacher_staff_id' => $teacher?->id,
            'referred_by_staff_id' => $payload['referred_by_staff_id'] ?? null,
            'dependency_id' => $payload['dependency_id'] ?? null,
            'attended_by_user_id' => $payload['attended_by_user_id'] ?? $user->id,
            'attention_category' => $payload['attention_category'],
            'attended_at' => $attendedAt->format('Y-m-d H:i:s'),
            'student_full_name_snapshot' => $summary['full_name'],
            'student_rut_snapshot' => $summary['rut'],
            'course_name_snapshot' => $payload['course_name_snapshot'] ?? $summary['course'],
            'teacher_name_snapshot' => $teacher?->full_name ?: $summary['teacher_name'],
            'age_snapshot' => $summary['age'],
            'accompanied_by_type' => $payload['accompanied_by_type'],
            'accompanied_by_name' => $payload['accompanied_by_name'] ?? null,
            'consultation_reason' => $payload['consultation_reason'],
            'initial_description' => $payload['initial_description'] ?? null,
            'observations' => $payload['observations'] ?? null,
            'attention_duration_minutes' => $payload['attention_duration_minutes'] ?? null,
            'priority' => $payload['priority'],
            'status' => $payload['status'],
            'finalized_at' => ($payload['status'] ?? null) === 'finalizada'
                ? ($attention->finalized_at ?: now())
                : null,
            'updated_by' => $user->id,
        ]);

        if ($creating) {
            $attention->created_by = $user->id;
        }
    }

    private function syncNestedData(InfirmaryAttention $attention, array $payload, User $user): void
    {
        foreach ($payload['treatments'] ?? [] as $treatmentPayload) {
            $treatment = $attention->treatments()->create($this->normalizeTreatmentPayload($treatmentPayload));

            if (!empty($treatmentPayload['medication_id']) && !empty($treatmentPayload['medication_quantity'])) {
                $medication = InfirmaryMedication::query()->findOrFail($treatmentPayload['medication_id']);
                $administration = InfirmaryMedicationAdministration::query()->create([
                    'attention_id' => $attention->id,
                    'authorization_id' => null,
                    'medication_id' => $medication->id,
                    'student_profile_id' => $attention->student_profile_id,
                    'administered_at' => $attention->attended_at,
                    'administered_by_user_id' => $user->id,
                    'quantity_administered' => $treatmentPayload['medication_quantity'],
                    'schedule_reference' => null,
                    'source_type' => 'atencion',
                    'observations' => $treatmentPayload['notes'] ?? null,
                ]);

                $this->stockService->decreaseStock(
                    $medication,
                    \App\Models\Infirmary\InfirmaryMedicationMovement::TYPE_ADMINISTRACION,
                    (float) $treatmentPayload['medication_quantity'],
                    $user,
                    'Administración registrada en atención de enfermería',
                    null,
                    $administration,
                    Carbon::parse($attention->attended_at),
                );
            }
        }

        foreach ($payload['referrals'] ?? [] as $referralPayload) {
            $attention->referrals()->create([
                'referral_type' => $referralPayload['referral_type'],
                'referred_at' => Carbon::parse($referralPayload['referred_at'])->format('Y-m-d H:i:s'),
                'responsible_user_id' => $referralPayload['responsible_user_id'] ?? null,
                'responsible_name' => $referralPayload['responsible_name'] ?? null,
                'reason' => $referralPayload['reason'] ?? null,
                'observations' => $referralPayload['observations'] ?? null,
                'result' => $referralPayload['result'] ?? null,
            ]);
        }

        foreach ($payload['calls'] ?? [] as $callPayload) {
            $attention->calls()->create([
                'student_profile_id' => $attention->student_profile_id,
                'called_at' => Carbon::parse($callPayload['called_at'])->format('Y-m-d H:i:s'),
                'person_contacted' => $callPayload['person_contacted'],
                'relationship' => $callPayload['relationship'] ?? null,
                'phone_number' => $callPayload['phone_number'] ?? null,
                'call_status' => $callPayload['call_status'],
                'reason' => $callPayload['reason'] ?? null,
                'conversation_summary' => $callPayload['conversation_summary'] ?? null,
                'commitments' => $callPayload['commitments'] ?? null,
                'estimated_arrival_at' => !empty($callPayload['estimated_arrival_at'])
                    ? Carbon::parse($callPayload['estimated_arrival_at'])->format('Y-m-d H:i:s')
                    : null,
                'duration_minutes' => $callPayload['duration_minutes'] ?? null,
                'called_by_user_id' => $callPayload['called_by_user_id'] ?? $user->id,
            ]);
        }

        foreach ($payload['follow_ups'] ?? [] as $followUpPayload) {
            $attention->followUps()->create([
                'followed_at' => Carbon::parse($followUpPayload['followed_at'])->format('Y-m-d H:i:s'),
                'responsible_user_id' => $followUpPayload['responsible_user_id'] ?? $user->id,
                'comment' => $followUpPayload['comment'],
                'status' => $followUpPayload['status'],
                'next_review_at' => !empty($followUpPayload['next_review_at'])
                    ? Carbon::parse($followUpPayload['next_review_at'])->format('Y-m-d H:i:s')
                    : null,
                'completed_at' => ($followUpPayload['status'] ?? null) === 'cerrado' ? now() : null,
            ]);
        }
    }

    private function normalizeTreatmentPayload(array $payload): array
    {
        $height = $this->normalizeHeight($payload['height'] ?? null);
        $weight = $payload['weight'] ?? null;

        return [
            'treatment_types' => array_values($payload['treatment_types'] ?? []),
            'treatment_other' => $payload['treatment_other'] ?? null,
            'medication_id' => $payload['medication_id'] ?? null,
            'medication_quantity' => $payload['medication_quantity'] ?? null,
            'blood_pressure' => $payload['blood_pressure'] ?? null,
            'pulse' => $payload['pulse'] ?? null,
            'respiratory_rate' => $payload['respiratory_rate'] ?? null,
            'temperature' => $payload['temperature'] ?? null,
            'oxygen_saturation' => $payload['oxygen_saturation'] ?? null,
            'weight' => $weight,
            'height' => $height,
            'bmi' => $this->calculateBmi($weight, $height),
            'vital_signs_notes' => $payload['vital_signs_notes'] ?? null,
            'emotional_support_required' => (bool) ($payload['emotional_support_required'] ?? false),
            'emotional_comment' => $payload['emotional_comment'] ?? null,
            'emotional_support_type' => $payload['emotional_support_type'] ?? null,
            'emotional_duration_minutes' => $payload['emotional_duration_minutes'] ?? null,
            'emotional_professional_id' => $payload['emotional_professional_id'] ?? null,
            'other_treatments' => $payload['other_treatments'] ?? null,
            'notes' => $payload['notes'] ?? null,
        ];
    }

    private function calculateBmi(mixed $weight, mixed $height): ?float
    {
        if (!$weight || !$height || (float) $height <= 0) {
            return null;
        }

        return round(((float) $weight) / (((float) $height) * ((float) $height)), 2);
    }

    private function normalizeHeight(mixed $height): ?float
    {
        if ($height === null || $height === '') {
            return null;
        }

        $value = (float) $height;

        if ($value > 3) {
            return round($value / 100, 2);
        }

        return round($value, 2);
    }

    private function revertAdministrations(InfirmaryAttention $attention, User $user, string $reason = 'Reversa por actualización de atención'): void
    {
        $attention->administrations->each(function (InfirmaryMedicationAdministration $administration) use ($user, $reason) {
            $this->stockService->reverseAdministration($administration, $user, $reason);
        });
    }

    private function loadAttention(InfirmaryAttention $attention): InfirmaryAttention
    {
        return $attention->fresh([
            'student:id,first_name,last_name,registered_name,rut,birthdate,guardian_name,guardian_phone,guardian_email,guardian_backup_name,guardian_backup_phone,guardian_backup_email,health_insurance,has_chronic_illness,chronic_illness_details,has_medication_allergies,medication_allergies_details,has_physical_restrictions,physical_restrictions_details',
            'academicYear:id,name,year',
            'courseSection:id,display_name',
            'teacher:id,full_name',
            'referredBy:id,full_name',
            'dependency:id,code,name,location,floor_sector',
            'attendedBy:id,name',
            'treatments.medication:id,name,commercial_name,unit',
            'treatments.emotionalProfessional:id,full_name',
            'referrals.responsibleUser:id,name',
            'calls.calledBy:id,name',
            'followUps.responsibleUser:id,name',
            'administrations.medication:id,name,commercial_name,unit',
            'accidents',
            'documents.uploadedBy:id,name',
        ]);
    }
}
