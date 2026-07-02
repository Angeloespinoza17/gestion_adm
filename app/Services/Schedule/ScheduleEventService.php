<?php

namespace App\Services\Schedule;

use App\Models\Schedule\ScheduleEvent;
use App\Models\Schedule\ScheduleValidationIssue;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleEventService
{
    public function __construct(
        private readonly ScheduleConfigService $configService,
        private readonly ScheduleTimeCalculator $calculator,
        private readonly ScheduleValidationService $validationService,
    ) {
    }

    public function create(array $payload, bool $force = false): ScheduleEvent
    {
        return DB::transaction(function () use ($payload, $force) {
            $payload = $this->preparePayload($payload);
            $this->ensureCanPersist($payload, $force);

            $event = ScheduleEvent::query()->create($payload);
            $this->syncValidationIssues($event);

            return $event->fresh($this->defaultRelations());
        });
    }

    public function update(ScheduleEvent $event, array $payload, bool $force = false): ScheduleEvent
    {
        return DB::transaction(function () use ($event, $payload, $force) {
            $payload = $this->preparePayload(array_merge($event->toArray(), $payload));
            $payload['id'] = $event->id;
            $this->ensureCanPersist($payload, $force);

            $event->update($payload);
            $this->syncValidationIssues($event->fresh());

            return $event->fresh($this->defaultRelations());
        });
    }

    public function move(ScheduleEvent $event, array $payload, bool $force = false): ScheduleEvent
    {
        return $this->update($event, [
            'day_of_week' => $payload['day_of_week'] ?? $event->day_of_week,
            'start_time' => $payload['start_time'] ?? $event->start_time,
            'end_time' => $payload['end_time'] ?? $event->end_time,
            'school_day_block_id' => $payload['school_day_block_id'] ?? null,
        ], $force);
    }

    public function delete(ScheduleEvent $event): void
    {
        $event->delete();
    }

    public function syncValidationIssues(ScheduleEvent $event): array
    {
        $issues = $this->validationService->validateScheduleEvent($event);

        ScheduleValidationIssue::query()
            ->where('schedule_event_id', $event->id)
            ->where('resolved', false)
            ->update(['resolved' => true]);

        foreach ($issues as $issue) {
            ScheduleValidationIssue::query()->create(array_merge($issue, [
                'schedule_event_id' => $event->id,
            ]));
        }

        $event->update([
            'status' => collect($issues)->contains(fn (array $issue) => $issue['severity'] === 'error')
                ? ScheduleEvent::STATUS_CONFLICT
                : ($event->status === ScheduleEvent::STATUS_CONFLICT ? ScheduleEvent::STATUS_DRAFT : $event->status),
        ]);

        return $issues;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function preparePayload(array $payload): array
    {
        $config = $this->configService->getForAcademicYear($payload['academic_year_id'] ?? null);
        $minutes = $this->calculator->minutesBetween((string) $payload['start_time'], (string) $payload['end_time']);

        $payload['minutes'] = $minutes;
        $payload['pedagogical_hours'] = $payload['pedagogical_hours'] ?? $this->calculator->minutesToPedagogicalHours(
            $minutes,
            (int) $config->pedagogical_hour_minutes,
            $config->rounding_mode,
        );

        $payload['status'] = $payload['status'] ?? ScheduleEvent::STATUS_DRAFT;
        $payload['source'] = $payload['source'] ?? ScheduleEvent::SOURCE_MANUAL;

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function ensureCanPersist(array $payload, bool $force): void
    {
        $config = $this->configService->getForAcademicYear($payload['academic_year_id'] ?? null);
        $issues = $this->validationService->validateScheduleEvent($payload);
        $hasErrors = collect($issues)->contains(fn (array $issue) => $issue['severity'] === 'error');

        if ($hasErrors && $config->strict_validation_enabled && !$force) {
            throw ValidationException::withMessages([
                'schedule_event' => collect($issues)
                    ->where('severity', 'error')
                    ->pluck('message')
                    ->values()
                    ->all(),
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function defaultRelations(): array
    {
        return [
            'teacher:id,full_name,institutional_email',
            'layer:id,name,type,color,visible_by_default,priority,active',
            'courseSection:id,display_name,education_level_id,school_day_template_id,academic_year_id',
            'courseSection.educationLevel:id,name,default_school_day_template_id',
            'subject:id,name,code,color,area',
            'schoolDayTemplate:id,name,start_time,end_time',
            'schoolDayBlock:id,label,type,assignable,start_time,end_time',
            'validationIssues' => fn ($query) => $query->where('resolved', false),
        ];
    }
}
