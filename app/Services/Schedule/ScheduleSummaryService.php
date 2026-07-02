<?php

namespace App\Services\Schedule;

use App\Models\CourseSection;
use App\Models\Schedule\ScheduleEvent;
use App\Models\Schedule\ScheduleValidationIssue;
use App\Models\Schedule\TeacherContract;
use App\Models\Staff;

class ScheduleSummaryService
{
    public function __construct(private readonly ScheduleTimeCalculator $calculator)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function teacherWeeklySummary(Staff $teacher, int $academicYearId): array
    {
        $events = ScheduleEvent::query()
            ->with('layer')
            ->where('staff_id', $teacher->id)
            ->where('academic_year_id', $academicYearId)
            ->get();

        $contract = TeacherContract::query()
            ->where('staff_id', $teacher->id)
            ->where('academic_year_id', $academicYearId)
            ->where('active', true)
            ->latest('id')
            ->first();

        $lectiveAssigned = 0.0;
        $nonLectiveAssigned = 0.0;
        $byLayer = [];

        foreach ($events as $event) {
            $hours = (float) $event->pedagogical_hours;
            $layerType = $event->layer?->type;

            if ($this->calculator->isLectiveActivity($event->activity_type, $layerType)) {
                $lectiveAssigned += $hours;
            } elseif ($this->calculator->isNonLectiveActivity($event->activity_type, $layerType)) {
                $nonLectiveAssigned += $hours;
            }

            $layerId = $event->teacher_schedule_layer_id;
            $byLayer[$layerId] ??= [
                'layer_id' => $layerId,
                'layer_name' => $event->layer?->name,
                'layer_type' => $layerType,
                'hours' => 0.0,
            ];
            $byLayer[$layerId]['hours'] = round($byLayer[$layerId]['hours'] + $hours, 2);
        }

        $totalAssigned = $lectiveAssigned + $nonLectiveAssigned;
        $expectedLective = (float) ($contract?->calculated_lective_hours ?? 0);
        $expectedNonLective = (float) ($contract?->calculated_non_lective_hours ?? 0);
        $contractHours = (float) ($contract?->weekly_contract_hours ?? 0);

        return [
            'teacher' => $teacher->only(['id', 'full_name', 'institutional_email']),
            'contract' => $contract,
            'expected_total_hours' => round($contractHours, 2),
            'expected_lective_hours' => round($expectedLective, 2),
            'expected_non_lective_hours' => round($expectedNonLective, 2),
            'assigned_lective_hours' => round($lectiveAssigned, 2),
            'assigned_non_lective_hours' => round($nonLectiveAssigned, 2),
            'assigned_total_hours' => round($totalAssigned, 2),
            'lective_balance' => round($expectedLective - $lectiveAssigned, 2),
            'non_lective_balance' => round($expectedNonLective - $nonLectiveAssigned, 2),
            'total_balance' => round($contractHours - $totalAssigned, 2),
            'by_layer' => array_values($byLayer),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function courseWeeklySummary(CourseSection $courseSection): array
    {
        $events = ScheduleEvent::query()
            ->with(['subject:id,name,color', 'teacher:id,full_name'])
            ->where('academic_year_id', $courseSection->academic_year_id)
            ->where('course_section_id', $courseSection->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        $bySubject = [];

        foreach ($events as $event) {
            $subjectId = $event->schedule_subject_id ?: 0;
            $bySubject[$subjectId] ??= [
                'subject_id' => $event->schedule_subject_id,
                'subject_name' => $event->subject?->name ?: 'Sin asignatura',
                'subject_color' => $event->subject?->color ?: '#6c757d',
                'hours' => 0.0,
                'events_count' => 0,
            ];
            $bySubject[$subjectId]['hours'] = round($bySubject[$subjectId]['hours'] + (float) $event->pedagogical_hours, 2);
            $bySubject[$subjectId]['events_count']++;
        }

        return [
            'course' => $courseSection->loadMissing('educationLevel:id,name'),
            'assigned_hours' => round($events->sum(fn (ScheduleEvent $event) => (float) $event->pedagogical_hours), 2),
            'events_count' => $events->count(),
            'by_subject' => array_values($bySubject),
        ];
    }

    public function activeConflicts(array $filters = [])
    {
        return ScheduleValidationIssue::query()
            ->with([
                'scheduleEvent.teacher:id,full_name',
                'scheduleEvent.courseSection:id,display_name',
                'scheduleEvent.subject:id,name,color',
                'scheduleEvent.layer:id,name,type,color',
            ])
            ->where('resolved', false)
            ->when($filters['severity'] ?? null, fn ($query, $severity) => $query->where('severity', $severity))
            ->when($filters['teacher_id'] ?? null, fn ($query, $teacherId) => $query->whereHas('scheduleEvent', fn ($eventQuery) => $eventQuery->where('staff_id', $teacherId)))
            ->when($filters['course_section_id'] ?? null, fn ($query, $courseId) => $query->whereHas('scheduleEvent', fn ($eventQuery) => $eventQuery->where('course_section_id', $courseId)))
            ->when($filters['schedule_subject_id'] ?? null, fn ($query, $subjectId) => $query->whereHas('scheduleEvent', fn ($eventQuery) => $eventQuery->where('schedule_subject_id', $subjectId)))
            ->latest('id')
            ->limit((int) ($filters['limit'] ?? 100))
            ->get();
    }
}
