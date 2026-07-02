<?php

namespace App\Services\Schedule;

use App\Models\Schedule\TeacherContract;

class TeacherContractService
{
    public function __construct(
        private readonly ScheduleTimeCalculator $calculator,
        private readonly ScheduleConfigService $configService,
    ) {
    }

    public function calculateDistribution(array $payload): array
    {
        $config = $this->configService->getForAcademicYear($payload['academic_year_id'] ?? null);

        return $this->calculator->calculateDistribution(
            (float) $payload['weekly_contract_hours'],
            (float) ($payload['lective_percentage'] ?? $config->default_lective_percentage),
            (float) ($payload['non_lective_percentage'] ?? $config->default_non_lective_percentage),
            $config->rounding_mode,
            $config->rounding_mode === 'none' ? 0.01 : 0.1,
        );
    }

    public function createOrUpdate(array $payload, ?TeacherContract $contract = null): TeacherContract
    {
        $distribution = $this->calculateDistribution($payload);
        $payload['calculated_lective_hours'] = $distribution['lective'];
        $payload['calculated_non_lective_hours'] = $distribution['non_lective'];

        $contract = $contract
            ? tap($contract)->update($payload)
            : TeacherContract::query()->create($payload);

        return $contract->fresh(['teacher:id,full_name,institutional_email,contract_hours', 'academicYear:id,name,year,is_active']);
    }
}
