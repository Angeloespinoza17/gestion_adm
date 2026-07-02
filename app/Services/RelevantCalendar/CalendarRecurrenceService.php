<?php

namespace App\Services\RelevantCalendar;

use App\Models\CalendarEvent;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CalendarRecurrenceService
{
    public function normalizeRule(array $recurrence, string $startDate): array
    {
        $mode = $recurrence['mode'] ?? 'monthly';
        $frequency = $mode === 'custom'
            ? ($recurrence['frequency'] ?? 'monthly')
            : $mode;

        return [
            'mode' => $mode,
            'frequency' => $frequency,
            'interval' => max(1, (int) ($recurrence['interval'] ?? 1)),
            'weekdays' => array_values($recurrence['weekdays'] ?? []),
            'monthly_mode' => $recurrence['monthly_mode'] ?? 'day_of_month',
            'day_of_month' => (int) ($recurrence['day_of_month'] ?? Carbon::parse($startDate, config('app.timezone'))->day),
            'starts_on' => $startDate,
            'ends_on' => $recurrence['ends_on'] ?? null,
            'auto_generate' => (bool) ($recurrence['auto_generate'] ?? false),
            'series_key' => (string) ($recurrence['series_key'] ?? Str::uuid()),
        ];
    }

    public function ensureOccurrencesForEvent(CalendarEvent $master, ?Carbon $from = null, ?Carbon $to = null): void
    {
        if (!$this->isSeriesMaster($master)) {
            return;
        }

        $rule = $master->recurrence_rule ?: [];
        if ($rule === []) {
            return;
        }

        $master->loadMissing(['eventUsers', 'reminders']);

        $fromDate = ($from ?: Carbon::parse($rule['starts_on'] ?? $master->start_date?->format('Y-m-d'), config('app.timezone')))
            ->copy()
            ->startOfDay();
        $toDate = ($to ?: $this->generationEndForEvent($master, $fromDate))
            ->copy()
            ->startOfDay();

        $existingDates = $master->childEvents()
            ->pluck('start_date')
            ->filter()
            ->map(fn ($date) => Carbon::parse($date, config('app.timezone'))->format('Y-m-d'))
            ->values()
            ->all();

        $cursor = $fromDate->copy();
        while ($cursor->lte($toDate)) {
            if ($this->matchesDate($master, $cursor, $rule)) {
                $dateKey = $cursor->format('Y-m-d');
                if (!in_array($dateKey, $existingDates, true)) {
                    $occurrence = CalendarEvent::query()->create($this->buildOccurrencePayload($master, $cursor));
                    $this->cloneRelationsFromMaster($master, $occurrence);
                    $existingDates[] = $dateKey;
                }
            }

            $cursor->addDay();
        }
    }

    public function refreshFutureOccurrences(CalendarEvent $master, Carbon $from): void
    {
        if (!$this->isSeriesMaster($master)) {
            return;
        }

        $master->childEvents()
            ->whereDate('start_date', '>=', $from->toDateString())
            ->whereNotIn('status', CalendarEvent::TERMINAL_STATUSES)
            ->get()
            ->each(function (CalendarEvent $event) {
                $event->eventUsers()->delete();
                $event->reminders()->delete();
                $event->attachments()->delete();
                $event->logs()->delete();
                $event->forceDelete();
            });

        $this->ensureOccurrencesForEvent($master, $from, $this->generationEndForEvent($master, $from));
    }

    public function generationEndForEvent(CalendarEvent $master, ?Carbon $from = null): Carbon
    {
        $rule = $master->recurrence_rule ?: [];
        $base = ($from ?: now(config('app.timezone')))->copy()->startOfDay();
        $monthsAhead = !empty($rule['auto_generate']) ? 12 : 4;
        $candidate = $base->copy()->addMonths($monthsAhead)->endOfMonth();

        if (!empty($rule['ends_on'])) {
            $until = Carbon::parse($rule['ends_on'], config('app.timezone'))->startOfDay();

            return $until->lt($candidate) ? $until : $candidate;
        }

        return $candidate;
    }

    public function isSeriesMaster(CalendarEvent $event): bool
    {
        return $event->event_kind === CalendarEvent::KIND_SERIES_MASTER
            && $event->is_recurring;
    }

    public function seriesMaster(CalendarEvent $event): CalendarEvent
    {
        if ($this->isSeriesMaster($event)) {
            return $event;
        }

        return $event->parentEvent ?: $event;
    }

    public function matchesDate(CalendarEvent $master, Carbon $date, array $rule): bool
    {
        $anchor = Carbon::parse($rule['starts_on'] ?? $master->start_date?->format('Y-m-d'), config('app.timezone'))->startOfDay();

        if ($date->lt($anchor)) {
            return false;
        }

        if (!empty($rule['ends_on']) && $date->gt(Carbon::parse($rule['ends_on'], config('app.timezone'))->startOfDay())) {
            return false;
        }

        $frequency = $rule['frequency'] ?? 'monthly';
        $interval = max(1, (int) ($rule['interval'] ?? 1));

        return match ($frequency) {
            'daily' => $anchor->diffInDays($date) % $interval === 0,
            'weekly' => $this->matchesWeeklyDate($anchor, $date, $interval, $rule),
            'monthly' => $this->matchesMonthlyDate($anchor, $date, $interval, $rule),
            'yearly' => $this->matchesYearlyDate($anchor, $date, $interval, $rule),
            default => false,
        };
    }

    private function matchesWeeklyDate(Carbon $anchor, Carbon $date, int $interval, array $rule): bool
    {
        $weekdays = Arr::wrap($rule['weekdays'] ?? [$anchor->englishDayOfWeek]);
        if (!in_array($date->englishDayOfWeek, $weekdays, true)) {
            return false;
        }

        $anchorWeek = $anchor->copy()->startOfWeek(Carbon::MONDAY);
        $dateWeek = $date->copy()->startOfWeek(Carbon::MONDAY);

        return $anchorWeek->diffInWeeks($dateWeek) % $interval === 0;
    }

    private function matchesMonthlyDate(Carbon $anchor, Carbon $date, int $interval, array $rule): bool
    {
        $monthDiff = (($date->year - $anchor->year) * 12) + ($date->month - $anchor->month);
        if ($monthDiff < 0 || $monthDiff % $interval !== 0) {
            return false;
        }

        $mode = $rule['monthly_mode'] ?? 'day_of_month';
        if ($mode === 'last_business_day') {
            return $date->isSameDay($this->lastBusinessDayOfMonth($date));
        }

        $targetDay = max(1, (int) ($rule['day_of_month'] ?? $anchor->day));
        $targetDay = min($targetDay, $date->daysInMonth);

        return $date->day === $targetDay;
    }

    private function matchesYearlyDate(Carbon $anchor, Carbon $date, int $interval, array $rule): bool
    {
        $yearDiff = $date->year - $anchor->year;
        if ($yearDiff < 0 || $yearDiff % $interval !== 0) {
            return false;
        }

        $targetMonth = $anchor->month;
        $targetDay = max(1, (int) ($rule['day_of_month'] ?? $anchor->day));

        if ((int) $date->month !== (int) $targetMonth) {
            return false;
        }

        $targetDay = min($targetDay, $date->daysInMonth);

        return $date->day === $targetDay;
    }

    private function lastBusinessDayOfMonth(Carbon $date): Carbon
    {
        $cursor = $date->copy()->endOfMonth()->startOfDay();

        while ($cursor->isWeekend()) {
            $cursor->subDay();
        }

        return $cursor;
    }

    private function buildOccurrencePayload(CalendarEvent $master, Carbon $occurrenceDate): array
    {
        $baseStartDate = $master->start_date?->copy() ?: $occurrenceDate->copy();
        $baseEndDate = $master->end_date?->copy() ?: $baseStartDate->copy();
        $spanDays = $baseStartDate->diffInDays($baseEndDate);
        $endDate = $occurrenceDate->copy()->addDays($spanDays);

        return [
            'title' => $master->title,
            'description' => $master->description,
            'process_type_id' => $master->process_type_id,
            'institution_id' => $master->institution_id,
            'department_id' => $master->department_id,
            'responsible_user_id' => $master->responsible_user_id,
            'start_date' => $occurrenceDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'start_time' => $master->start_time,
            'end_time' => $master->end_time,
            'priority' => $master->priority,
            'status' => $master->status,
            'requires_submission' => $master->requires_submission,
            'requires_payment' => $master->requires_payment,
            'requires_signature' => $master->requires_signature,
            'requires_review' => $master->requires_review,
            'requires_approval' => $master->requires_approval,
            'is_recurring' => true,
            'auto_generate_occurrences' => false,
            'recurrence_rule' => null,
            'recurrence_group_id' => $master->recurrence_group_id,
            'parent_event_id' => $master->id,
            'event_kind' => CalendarEvent::KIND_OCCURRENCE,
            'stage_key' => null,
            'stage_order' => null,
            'is_exception' => false,
            'external_url' => $master->external_url,
            'internal_observations' => $master->internal_observations,
            'created_by' => $master->created_by,
            'updated_by' => $master->updated_by,
            'completed_by' => null,
            'completed_at' => null,
            'archived_at' => null,
        ];
    }

    private function cloneRelationsFromMaster(CalendarEvent $master, CalendarEvent $occurrence): void
    {
        if ($master->eventUsers->isNotEmpty()) {
            $occurrence->eventUsers()->createMany(
                $master->eventUsers
                    ->map(fn ($entry) => [
                        'user_id' => $entry->user_id,
                        'role_in_event' => $entry->role_in_event,
                    ])
                    ->all()
            );
        }

        if ($master->reminders->isNotEmpty()) {
            $occurrence->reminders()->createMany(
                $master->reminders
                    ->map(fn ($entry) => [
                        'reminder_type' => $entry->reminder_type,
                        'days_before' => $entry->days_before,
                        'reminder_date' => $entry->reminder_date?->format('Y-m-d'),
                        'sent_at' => null,
                        'is_active' => $entry->is_active,
                    ])
                    ->all()
            );
        }
    }
}
