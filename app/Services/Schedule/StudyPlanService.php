<?php

namespace App\Services\Schedule;

use App\Models\CourseSection;
use App\Models\Schedule\ScheduleEvent;
use App\Models\Schedule\StudyPlan;
use App\Models\Schedule\StudyPlanSubject;
use Illuminate\Support\Facades\DB;

class StudyPlanService
{
    public function createOrUpdate(array $payload, ?StudyPlan $studyPlan = null): StudyPlan
    {
        return DB::transaction(function () use ($payload, $studyPlan) {
            $subjects = $payload['subjects'] ?? null;
            unset($payload['subjects']);

            $studyPlan = $studyPlan
                ? tap($studyPlan)->update($payload)
                : StudyPlan::query()->create($payload);

            if (is_array($subjects)) {
                foreach ($subjects as $subjectPayload) {
                    $this->assignSubjectHours($studyPlan, $subjectPayload);
                }
            }

            return $studyPlan->fresh(['academicYear', 'educationLevel', 'courseSection', 'subjects.scheduleSubject']);
        });
    }

    public function assignSubjectHours(StudyPlan $studyPlan, array $payload): StudyPlanSubject
    {
        return StudyPlanSubject::query()->updateOrCreate(
            [
                'study_plan_id' => $studyPlan->id,
                'schedule_subject_id' => $payload['schedule_subject_id'],
            ],
            [
                'weekly_pedagogical_hours' => $payload['weekly_pedagogical_hours'],
                'required' => $payload['required'] ?? true,
                'notes' => $payload['notes'] ?? null,
            ],
        );
    }

    public function planForCourse(CourseSection $courseSection): ?StudyPlan
    {
        return StudyPlan::query()
            ->with('subjects.scheduleSubject')
            ->where('academic_year_id', $courseSection->academic_year_id)
            ->where('active', true)
            ->where(function ($query) use ($courseSection) {
                $query
                    ->where('course_section_id', $courseSection->id)
                    ->orWhere(function ($query) use ($courseSection) {
                        $query
                            ->whereNull('course_section_id')
                            ->where('education_level_id', $courseSection->education_level_id);
                    });
            })
            ->orderByRaw('case when course_section_id is null then 1 else 0 end')
            ->latest('id')
            ->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function progressForCourse(CourseSection $courseSection): array
    {
        $plan = $this->planForCourse($courseSection);

        if (!$plan) {
            return [];
        }

        $assigned = ScheduleEvent::query()
            ->selectRaw('schedule_subject_id, sum(pedagogical_hours) as assigned_hours')
            ->where('academic_year_id', $courseSection->academic_year_id)
            ->where('course_section_id', $courseSection->id)
            ->whereIn('activity_type', ['lective_class', 'class', 'jefatura_course'])
            ->whereNotNull('schedule_subject_id')
            ->groupBy('schedule_subject_id')
            ->pluck('assigned_hours', 'schedule_subject_id');

        return $plan->subjects->map(function (StudyPlanSubject $planSubject) use ($assigned) {
            $assignedHours = (float) ($assigned[$planSubject->schedule_subject_id] ?? 0);
            $requiredHours = (float) $planSubject->weekly_pedagogical_hours;

            return [
                'study_plan_subject_id' => $planSubject->id,
                'subject_id' => $planSubject->schedule_subject_id,
                'subject_name' => $planSubject->scheduleSubject?->name,
                'subject_color' => $planSubject->scheduleSubject?->color,
                'required_hours' => round($requiredHours, 2),
                'assigned_hours' => round($assignedHours, 2),
                'pending_hours' => round(max(0, $requiredHours - $assignedHours), 2),
                'exceeded_hours' => round(max(0, $assignedHours - $requiredHours), 2),
            ];
        })->values()->all();
    }

    public function planSubjectForCourse(CourseSection $courseSection, int $subjectId): ?StudyPlanSubject
    {
        $plan = $this->planForCourse($courseSection);

        return $plan?->subjects->firstWhere('schedule_subject_id', $subjectId);
    }
}
