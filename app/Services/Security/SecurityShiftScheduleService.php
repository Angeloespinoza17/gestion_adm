<?php

namespace App\Services\Security;

use App\Models\Security\SecurityShift;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SecurityShiftScheduleService
{
    public const REGISTRATION_START_TIME = '20:00';

    public const REGISTRATION_END_TIME = '07:30';

    public function materializeOccurrence(SecurityShift $shift, ?Carbon $referenceDate = null, ?int $actorUserId = null): SecurityShift
    {
        if (! $shift->is_weekly_template) {
            $this->assertRegistrationOpen($shift, $referenceDate);

            return $shift;
        }

        $window = $this->assertRegistrationOpen($shift, $referenceDate);
        $date = $window['occurrence_date'];

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

    /**
     * Materialize a scheduled night selected by a superadmin for a traceable
     * administrative entry. Unlike the live flow, this does not require the
     * current clock to be inside the shift window.
     */
    public function materializeScheduledOccurrence(SecurityShift $shift, Carbon $occurrenceDate, int $actorUserId): SecurityShift
    {
        $window = $this->scheduledOccurrenceWindow($shift, $occurrenceDate);
        $date = $window['occurrence_date'];

        $existing = $shift->generatedShifts()
            ->whereDate('generated_for_date', $date->toDateString())
            ->first();

        if ($existing) {
            if ($existing->status === SecurityShift::STATUS_CANCELADO) {
                throw ValidationException::withMessages([
                    'occurrence_date' => 'La instancia seleccionada fue cancelada y no admite registros.',
                ]);
            }

            return $existing;
        }

        return SecurityShift::create([
            'staff_id' => $shift->staff_id,
            'schedule_type' => SecurityShift::SCHEDULE_SINGLE,
            'parent_shift_id' => $shift->id,
            'generated_for_date' => $date->toDateString(),
            'maintenance_dependency_id' => null,
            'created_by' => $actorUserId,
            'updated_by' => $actorUserId,
            'scheduled_start_at' => $window['starts_at'],
            'scheduled_end_at' => $window['ends_at'],
            'status' => $window['ends_at']->isPast()
                ? SecurityShift::STATUS_FINALIZADO
                : SecurityShift::STATUS_PROGRAMADO,
            'coverage_label' => $shift->coverage_label ?: 'Todo el colegio',
            'general_observations' => $shift->general_observations,
            'closing_observations' => null,
        ]);
    }

    /**
     * @return array{occurrence_date: Carbon, starts_at: Carbon, ends_at: Carbon}
     */
    public function scheduledOccurrenceWindow(SecurityShift $shift, Carbon $occurrenceDate): array
    {
        $date = $occurrenceDate->copy()->timezone(config('app.timezone'))->startOfDay();

        if (! $shift->is_weekly_template || ! $this->matchesTemplateDate($shift, $date)) {
            throw ValidationException::withMessages([
                'occurrence_date' => 'La fecha seleccionada no corresponde a una noche asignada a este nochero.',
            ]);
        }

        [$startsAt, $endsAt] = $this->resolveWindow($shift, $date);

        return [
            'occurrence_date' => $date,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ];
    }

    public function matchesTemplateDate(SecurityShift $shift, Carbon $date): bool
    {
        if (! $shift->is_weekly_template || $shift->status === SecurityShift::STATUS_CANCELADO) {
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

    /**
     * Resolve the active window using the shift start day as the assigned day.
     * This keeps a Monday night shift open after midnight on Tuesday.
     *
     * @return array{occurrence_date: Carbon, starts_at: Carbon, ends_at: Carbon}|null
     */
    public function registrationWindow(SecurityShift $shift, ?Carbon $referenceDate = null): ?array
    {
        $reference = ($referenceDate ?: now(config('app.timezone')))
            ->copy()
            ->timezone(config('app.timezone'));

        if (! $shift->is_weekly_template) {
            if (! $shift->scheduled_start_at || ! $shift->scheduled_end_at) {
                return null;
            }

            $startsAt = Carbon::parse($shift->scheduled_start_at, config('app.timezone'));
            $endsAt = Carbon::parse($shift->scheduled_end_at, config('app.timezone'));

            if (! $reference->betweenIncluded($startsAt, $endsAt)) {
                return null;
            }

            return [
                'occurrence_date' => $startsAt->copy()->startOfDay(),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ];
        }

        foreach ([$reference->copy()->startOfDay(), $reference->copy()->subDay()->startOfDay()] as $date) {
            if (! $this->matchesTemplateDate($shift, $date)) {
                continue;
            }

            [$startsAt, $endsAt] = $this->resolveWindow($shift, $date);
            if ($reference->betweenIncluded($startsAt, $endsAt)) {
                return [
                    'occurrence_date' => $date,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ];
            }
        }

        return null;
    }

    /**
     * @return array{occurrence_date: Carbon, starts_at: Carbon, ends_at: Carbon}
     */
    public function assertRegistrationOpen(SecurityShift $shift, ?Carbon $referenceDate = null): array
    {
        if (in_array($shift->status, [SecurityShift::STATUS_FINALIZADO, SecurityShift::STATUS_CANCELADO], true)) {
            throw ValidationException::withMessages([
                'schedule' => 'Este turno está cerrado y no permite registrar nuevas rondas.',
            ]);
        }

        $window = $this->registrationWindow($shift, $referenceDate);
        if (! $window) {
            throw ValidationException::withMessages([
                'schedule' => $shift->is_weekly_template
                    ? 'El registro se habilita automáticamente durante los días y el horario asignados a este nochero.'
                    : 'El registro sólo está disponible dentro del horario de este turno.',
            ]);
        }

        return $window;
    }

    /**
     * @return array<string, mixed>
     */
    public function registrationState(SecurityShift $shift, ?Carbon $referenceDate = null): array
    {
        $reference = ($referenceDate ?: now(config('app.timezone')))
            ->copy()
            ->timezone(config('app.timezone'));
        $window = $this->registrationWindow($shift, $reference);
        $occurrenceClosed = false;
        if ($window && $shift->is_weekly_template) {
            $occurrenceDate = $window['occurrence_date']->toDateString();
            $occurrence = $shift->relationLoaded('generatedShifts')
                ? $shift->generatedShifts->first(fn (SecurityShift $item) => $item->generated_for_date?->toDateString() === $occurrenceDate)
                : $shift->generatedShifts()->whereDate('generated_for_date', $occurrenceDate)->first();
            $occurrenceClosed = $occurrence
                && in_array($occurrence->status, [SecurityShift::STATUS_FINALIZADO, SecurityShift::STATUS_CANCELADO], true);
        }
        $next = $window ? $window['starts_at'] : $this->nextOccurrence($shift, $reference);
        $nextEnd = $window ? $window['ends_at'] : $this->nextOccurrenceEnd($shift, $reference);

        return [
            'open' => $window !== null
                && ! $occurrenceClosed
                && ! in_array($shift->status, [SecurityShift::STATUS_FINALIZADO, SecurityShift::STATUS_CANCELADO], true),
            'closed' => (bool) $occurrenceClosed,
            'starts_at' => $window ? $window['starts_at']->format('Y-m-d H:i') : null,
            'ends_at' => $window ? $window['ends_at']->format('Y-m-d H:i') : null,
            'next_starts_at' => $next?->format('Y-m-d H:i'),
            'next_ends_at' => $nextEnd?->format('Y-m-d H:i'),
        ];
    }

    public function nextOccurrence(SecurityShift $shift, ?Carbon $from = null): ?Carbon
    {
        if (! $shift->is_weekly_template) {
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
        if (! $shift->is_weekly_template) {
            return $shift->scheduled_end_at ? Carbon::parse($shift->scheduled_end_at) : null;
        }

        $start = $this->nextOccurrence($shift, $from);
        if (! $start) {
            return null;
        }

        return $this->resolveWindow($shift, $start->copy()->startOfDay())[1];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolveWindow(SecurityShift $shift, Carbon $date): array
    {
        $startTime = self::REGISTRATION_START_TIME;
        $endTime = self::REGISTRATION_END_TIME;

        $scheduledStartAt = Carbon::parse($date->toDateString().' '.$startTime, config('app.timezone'));
        $scheduledEndAt = Carbon::parse($date->toDateString().' '.$endTime, config('app.timezone'));

        if ($scheduledEndAt->lte($scheduledStartAt)) {
            $scheduledEndAt->addDay();
        }

        return [$scheduledStartAt, $scheduledEndAt];
    }
}
