<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\CourseSection;
use App\Models\Staff;
use App\Services\Schedule\ScheduleSummaryService;
use App\Services\Schedule\StudyPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleSummaryController extends Controller
{
    public function __construct(
        private readonly ScheduleSummaryService $summaryService,
        private readonly StudyPlanService $studyPlanService,
    ) {
    }

    public function teacher(Request $request, Staff $teacher): JsonResponse
    {
        $academicYearId = $request->integer('academic_year_id');

        return response()->json([
            'data' => $this->summaryService->teacherWeeklySummary($teacher, $academicYearId),
        ]);
    }

    public function course(CourseSection $course): JsonResponse
    {
        return response()->json([
            'data' => $this->summaryService->courseWeeklySummary($course),
        ]);
    }

    public function studyPlanProgress(CourseSection $course): JsonResponse
    {
        return response()->json([
            'data' => $this->studyPlanService->progressForCourse($course),
        ]);
    }

    public function conflicts(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->summaryService->activeConflicts($request->only([
                'severity',
                'teacher_id',
                'course_section_id',
                'schedule_subject_id',
                'limit',
            ])),
        ]);
    }
}
