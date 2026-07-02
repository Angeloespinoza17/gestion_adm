<?php

namespace App\Services\Security;

use App\Models\Security\SecurityShift;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SecurityShiftScheduleService
{
    public function materializeOccurrence(SecurityShift $shift, ?Carbon $referenceDate = null, ?int $actorUserId = null): SecurityShift
    {
        if (!$shift->is_weekly_template) {
            return $shift;
        }

        $date = ($referenceDate ?: now(config('app.timezone')))->copy()->startOfDay();

        if (!$this->matchesTemplateDate($shift, $date)) {
            throw ValidationException::withMessages([
                'schedule' => 'Hoy no corresponde a este turno semanal.',
            ]);
        }

        $existing = $shift->generatedShifts()
            ->whereDate('generated_for_date', $date->toDateString())
            ->first();

        if ($existing) {
            if (in_array($existing->status, [SecurityShift::STATUS_FINALIZADO, SecurityShift::STATUS_CANCELADO], true)) {
                throw ValidationException::withMessages([
                    'schedule' => 'El turno de hoy ya fue cerrado.',
                ]);
            }

            return $existing;
        }

        [$scheduledStartAt, $scheduledEndAt] = $this->resolveWindow($shift, $date);

        return SecurityShift::create([
            'staff_id' => $shift->staff_id,
            'schedule_type' => SecurityShift::SCHEDULE_SINGLE,
            'parent_shift_id' => $shift->id,
            'generated_for_date' => $date->toDateString(),
            'maintenance_dependency_id' => null,
            'created_by' => $actorUserId ?: $shift->created_by,
            'updated_by' => $actorUserId ?: $shift->updated_by,
            'scheduled_start_at' => $scheduledStartAt,
            'scheduled_end_at' => $scheduledEndAt,
            'status' => SecurityShift::STATUS_PROGRAMADO,
            'coverage_label' => $shift->coverage_label ?: 'Todo el colegio',
            'general_observations' => $shift->general_observations,
            'closing_observations' => null,
        ]);
    }

    public function matchesTemplateDate(SecurityShift $shift, Carbon $date): bool
    {
        if (!$shift->is_weekly_template) {
            return false;
        }

        $allowedDays = collect($shift->weekdays ?: [])->values();
        if ($allowedDays->isEmpty()) {
            return false;
        }

        if ($shift->recurrence_starts_on && $date->lt(Carbon::parse($shift->recurrence_starts_on)->startOfDay())) {
            return false;
        }

        if ($shift->recurrence_ends_on && $date->gt(Carbon::parse($shift->recurrence_ends_on)->startOfDay())) {
            return false;
        }

        return $allowedDays->contains($date->englishDayOfWeek);
    }

    public function nextOccurrence(SecurityShift $shift, ?Carbon $from = null): ?Carbon
    {
        if (!$shift->is_weekly_template) {
            return $shift->scheduled_start_at ? Carbon::parse($shift->scheduled_start_at) : null;
        }

        $cursor = ($from ?: now(config('app.timezone')))->copy()->startOfDay();
        $limit = $shift->recurrence_ends_on
            ? Carbon::parse($shift->recurrence_ends_on)->endOfDay()
            : $cursor->copy()->addMonths(12)->endOfDay();

        if ($shift->recurrence_starts_on) {
            $cursor = $cursor->max(Carbon::parse($shift->recurrence_starts_on)->startOfDay());
        }

        while ($cursor->lte($limit)) {
            if ($this->matchesTemplateDate($shift, $cursor)) {
                return $this->resolveWindow($shift, $cursor)[0];
            }

            $cursor->addDay();
        }

        return null;
    }

    public function nextOccurrenceEnd(SecurityShift $shift, ?Carbon $from = null): ?Carbon
    {
        if (!$shift->is_weekly_template) {
            return $shift->scheduled_end_at ? Carbon::parse($shift->scheduled_end_at) : null;
        }

        $start = $this->nextOccurrence($shift, $from);
        if (!$start) {
            return null;
        }

        return $this->resolveWindow($shift, $start->copy()->startOfDay())[1];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolveWindow(SecurityShift $shift, Carbon $date): array
    {
        $startTime = $shift->template_start_time ?: '22:00:00';
        $endTime = $shift->template_end_time ?: '07:00:00';

        $scheduledStartAt = Carbon::parse($date->toDateString() . ' ' . $startTime, config('app.timezone'));
        $scheduledEndAt = Carbon::parse($date->toDateString() . ' ' . $endTime, config('app.timezone'));

        if ($scheduledEndAt->lte($scheduledStartAt)) {
            $scheduledEndAt->addDay();
        }

        return [$scheduledStartAt, $scheduledEndAt];
    }
};
