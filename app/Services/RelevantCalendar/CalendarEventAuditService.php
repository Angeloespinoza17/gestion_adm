<?php

namespace App\Services\RelevantCalendar;

use App\Models\CalendarEvent;
use App\Models\User;

class CalendarEventAuditService
{
    public function log(
        ?CalendarEvent $event,
        ?User $user,
        string $action,
        ?array $oldValue = null,
        ?array $newValue = null,
        ?string $description = null,
    ): void {
        \App\Models\CalendarEventLog::query()->create([
            'calendar_event_id' => $event?->id,
            'user_id' => $user?->id,
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'description' => $description,
        ]);
    }

    public function snapshot(CalendarEvent $event): array
    {
        $event->loadMissing([
            'eventUsers:id,calendar_event_id,user_id,role_in_event',
            'reminders:id,calendar_event_id,reminder_type,days_before,reminder_date,is_active',
            'attachments:id,calendar_event_id,file_name,file_type,uploaded_by',
        ]);

        return [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'process_type_id' => $event->process_type_id,
            'institution_id' => $event->institution_id,
            'department_id' => $event->department_id,
            'responsible_user_id' => $event->responsible_user_id,
            'start_date' => $event->start_date?->format('Y-m-d'),
            'end_date' => $event->end_date?->format('Y-m-d'),
            'start_time' => $event->start_time,
            'end_time' => $event->end_time,
            'priority' => $event->priority,
            'status' => $event->status,
            'is_recurring' => $event->is_recurring,
            'event_kind' => $event->event_kind,
            'parent_event_id' => $event->parent_event_id,
            'recurrence_group_id' => $event->recurrence_group_id,
            'recurrence_rule' => $event->recurrence_rule,
            'requires_submission' => $event->requires_submission,
            'requires_payment' => $event->requires_payment,
            'requires_signature' => $event->requires_signature,
            'requires_review' => $event->requires_review,
            'requires_approval' => $event->requires_approval,
            'external_url' => $event->external_url,
            'internal_observations' => $event->internal_observations,
            'participants' => $event->eventUsers
                ->map(fn ($entry) => [
                    'user_id' => $entry->user_id,
                    'role_in_event' => $entry->role_in_event,
                ])
                ->values()
                ->all(),
            'reminders' => $event->reminders
                ->map(fn ($entry) => [
                    'reminder_type' => $entry->reminder_type,
                    'days_before' => $entry->days_before,
                    'reminder_date' => $entry->reminder_date?->format('Y-m-d'),
                    'is_active' => $entry->is_active,
                ])
                ->values()
                ->all(),
            'attachments' => $event->attachments
                ->map(fn ($entry) => [
                    'id' => $entry->id,
                    'file_name' => $entry->file_name,
                    'file_type' => $entry->file_type,
                ])
                ->values()
                ->all(),
            'completed_by' => $event->completed_by,
            'completed_at' => $event->completed_at?->format('Y-m-d H:i:s'),
            'archived_at' => $event->archived_at?->format('Y-m-d H:i:s'),
        ];
    }
}
