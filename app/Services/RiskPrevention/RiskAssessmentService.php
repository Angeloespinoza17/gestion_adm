<?php

namespace App\Services\RiskPrevention;

use App\Models\RiskPrevention\RiskAssessment;
use App\Models\RiskPrevention\RiskEntry;
use App\Models\RiskPrevention\RiskMethodology;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RiskAssessmentService
{
    public function __construct(private readonly VepRiskCalculator $calculator) {}

    public function createCurrent(RiskEntry $risk, array $data, RiskMethodology $methodology, ?int $actorId): RiskAssessment
    {
        return DB::transaction(function () use ($risk, $data, $methodology, $actorId) {
            $phase = $data['phase'] ?? 'current';
            $method = $data['method'] ?? $risk->evaluation_method;
            RiskAssessment::query()->where('risk_entry_id', $risk->id)->where('phase', $phase)->where('active', true)->update(['active' => false]);

            $calculation = null;
            if ($method === 'vep') {
                $calculation = $this->calculator->calculate((int) ($data['probability'] ?? 0), (int) ($data['consequence'] ?? 0), $methodology);
            } elseif (blank($data['result_level'] ?? null) || blank($data['instrument_date'] ?? null)) {
                throw ValidationException::withMessages([
                    'assessment' => 'Las evaluaciones no VEP requieren fecha y resultado o clasificación.',
                ]);
            }

            return RiskAssessment::query()->create([
                'risk_entry_id' => $risk->id,
                'methodology_id' => $methodology->id,
                'phase' => $phase,
                'method' => $method,
                'probability' => $calculation['probability_score'] ?? null,
                'consequence' => $calculation['consequence_score'] ?? null,
                'calculated_score' => $calculation['vep'] ?? null,
                'calculated_level_id' => $calculation['risk_level_id'] ?? null,
                'protocol_id' => $data['protocol_id'] ?? null,
                'protocol_version' => $data['protocol_version'] ?? null,
                'exposure_value' => $data['exposure_value'] ?? null,
                'exposure_unit' => $data['exposure_unit'] ?? null,
                'result_value' => $data['result_value'] ?? null,
                'result_level' => $data['result_level'] ?? ($calculation['risk_level_label'] ?? null),
                'instrument' => $data['instrument'] ?? null,
                'evaluator' => $data['evaluator'] ?? null,
                'instrument_date' => $data['instrument_date'] ?? null,
                'next_measurement_at' => $data['next_measurement_at'] ?? null,
                'assessment_notes' => $data['assessment_notes'] ?? null,
                'assessed_by' => $actorId,
                'assessed_at' => now(),
                'active' => true,
                'source_payload' => $data['source_payload'] ?? null,
            ]);
        });
    }
}
