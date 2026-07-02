<?php

namespace App\Services\RelevantCalendar;

use App\Models\CalendarEvent;
use App\Models\CalendarEventReminder;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CalendarReminderService
{
    public function dueAlerts(Collection $events, ?Carbon $referenceDate = null, int $limit = 12): Collection
    {
        $today = ($referenceDate ?: now(config('app.timezone')))->copy()->startOfDay();

        return $events
            ->flatMap(function (CalendarEvent $event) use ($today) {
                return $event->reminders
                    ->filter(fn (CalendarEventReminder $reminder) => $reminder->is_active)
                    ->map(function (CalendarEventReminder $reminder) use ($event, $today) {
                        if (!$this->isReminderDue($event, $reminder, $today)) {
                            return null;
                        }

                        return [
                            'event_id' => $event->id,
                            'title' => $event->title,
                            'due_date' => $event->due_date,
                            'priority' => $event->priority,
                            'status' => $event->effective_status,
                            'reminder_type' => $reminder->reminder_type,
                            'reminder_label' => $this->labelForReminder($reminder),
                            'institution' => $event->institution?->name,
                            'responsible' => $event->responsibleUser?->name,
                        ];
                    })
                    ->filter();
            })
            ->sortBy([
                ['due_date', 'asc'],
                ['priority', 'desc'],
            ])
            ->values()
            ->take($limit);
    }

    public function isReminderDue(CalendarEvent $event, CalendarEventReminder $reminder, Carbon $referenceDate): bool
    {
        if (!$event->due_date) {
            return false;
        }

        $dueDate = Carbon::parse($event->due_date, config('app.timezone'))->startOfDay();

        return match ($reminder->reminder_type) {
            'before' => $referenceDate->equalTo($dueDate->copy()->subDays((int) ($reminder->days_before ?? 0))),
            'same_day' => $referenceDate->equalTo($dueDate),
            'after_overdue' => !$event->is_terminal
                && $referenceDate->equalTo($dueDate->copy()->addDays((int) ($reminder->days_before ?? 0))),
            'fixed_date' => $reminder->reminder_date !== null
                && $referenceDate->equalTo($reminder->reminder_date->copy()->startOfDay()),
            default => false,
        };
    }

    public function labelForReminder(CalendarEventReminder $reminder): string
    {
        return match ($reminder->reminder_type) {
            'before' => sprintf('%d día(s) antes', (int) ($reminder->days_before ?? 0)),
            'same_day' => 'El mismo día',
            'after_overdue' => sprintf('%d día(s) después del vencimiento', (int) ($reminder->days_before ?? 0)),
            'fixed_date' => $reminder->reminder_date?->format('d/m/Y') ?: 'Fecha específica',
            default => 'Recordatorio',
        };
    }
}
