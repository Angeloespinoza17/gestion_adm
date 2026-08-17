<?php

namespace App\Services\SocialWork;

use App\Models\SocialWork\RiskAssessment;
use App\Models\SocialWork\RiskRule;
use App\Models\SocialWork\SocialCase;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RiskAssessmentService
{
    private const RANK = ['sin_evaluar' => 0, 'bajo' => 1, 'medio' => 2, 'alto' => 3, 'critico' => 4];
    public function __construct(private readonly RiskDataAdapter $adapter, private readonly AlertService $alerts, private readonly AuditService $audit) {}

    public function evaluate(int $studentId, ?SocialCase $case, User $user, array $options = []): RiskAssessment
    {
        $to = Carbon::parse($options['period_to'] ?? today());
        $from = Carbon::parse($options['period_from'] ?? $to->copy()->subDays(30));
        $snapshot = $this->adapter->snapshot($studentId, $from, $to);
        $rules = RiskRule::query()->where('active', true)->get();
        $matches = [];
        $suggested = 'bajo';
        foreach ($rules as $rule) {
            $value = data_get($snapshot, $rule->source);
            if ($value === null || ! $this->matches((float) $value, $rule->operator, (float) $rule->threshold)) continue;
            $matches[] = ['rule_id' => $rule->id, 'code' => $rule->code, 'source' => $rule->source, 'observed' => $value, 'threshold' => $rule->threshold, 'level' => $rule->result_level];
            if ((self::RANK[$rule->result_level] ?? 0) > (self::RANK[$suggested] ?? 0)) $suggested = $rule->result_level;
        }

        $final = $options['final_level'] ?? $suggested;
        $overridden = $final !== $suggested;
        if ($overridden && empty(trim((string) ($options['override_justification'] ?? '')))) throw ValidationException::withMessages(['override_justification' => 'La modificación manual del riesgo requiere justificación.']);

        return DB::transaction(function () use ($studentId, $case, $user, $from, $to, $snapshot, $matches, $suggested, $final, $overridden, $options) {
            $assessment = RiskAssessment::create(['student_profile_id' => $studentId, 'case_id' => $case?->id, 'assessed_at' => now(), 'period_from' => $from, 'period_to' => $to, 'suggested_level' => $suggested, 'final_level' => $final, 'indicators' => $matches, 'source_snapshot' => $snapshot, 'evaluation_origin' => $options['evaluation_origin'] ?? 'manual', 'evaluated_by' => $user->id, 'manually_overridden' => $overridden, 'override_justification' => $options['override_justification'] ?? null, 'notes' => $options['notes'] ?? null]);
            if ($case) $case->update(['risk_level' => $final, 'updated_by' => $user->id]);
            if (in_array($final, ['alto', 'critico'], true)) {
                $this->alerts->raise(['deduplication_key' => 'risk:'.($case?->id ?? 'student-'.$studentId).':'.$to->format('Y-m-d').':'.$final, 'type' => 'riesgo_'.$final, 'student_profile_id' => $studentId, 'case_id' => $case?->id, 'severity' => $final, 'reason' => 'Evaluación de riesgo requiere revisión profesional.', 'evidence' => ['assessment_id' => $assessment->id], 'responsible_user_id' => $case?->responsible_user_id, 'confidentiality' => 'interno']);
            }
            $this->audit->record('risk.assessed', $assessment, $user, [], ['suggested_level' => $suggested, 'final_level' => $final], $options['override_justification'] ?? null);
            return $assessment;
        });
    }

    private function matches(float $value, string $operator, float $threshold): bool
    {
        return match ($operator) { '<' => $value < $threshold, '<=' => $value <= $threshold, '>' => $value > $threshold, '>=' => $value >= $threshold, '==' => $value === $threshold, default => false };
    }
}
