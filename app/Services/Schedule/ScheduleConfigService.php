<?php

namespace App\Services\Schedule;

use App\Models\AcademicYear;
use App\Models\Schedule\SchoolScheduleConfig;

class ScheduleConfigService
{
    public function getForAcademicYear(?int $academicYearId = null): SchoolScheduleConfig
    {
        $academicYearId ??= AcademicYear::query()->where('is_active', true)->value('id')
            ?: AcademicYear::query()->orderByDesc('year')->value('id');

        if (!$academicYearId) {
            $academicYear = AcademicYear::query()->create([
                'name' => (string) now()->year,
                'year' => now()->year,
                'is_active' => true,
            ]);
            $academicYearId = $academicYear->id;
        }

        return SchoolScheduleConfig::query()->firstOrCreate(
            ['academic_year_id' => $academicYearId],
            [
                'pedagogical_hour_minutes' => 45,
                'default_lective_percentage' => 65,
                'default_non_lective_percentage' => 35,
                'calculation_base' => 'pedagogical',
                'rounding_mode' => 'nearest',
                'strict_validation_enabled' => true,
            ],
        );
    }

    public function update(array $payload): SchoolScheduleConfig
    {
        $config = $this->getForAcademicYear((int) $payload['academic_year_id']);
        $config->update($payload);

        return $config->fresh('academicYear');
    }
}
