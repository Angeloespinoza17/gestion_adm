<?php

namespace App\Http\Controllers\Inspectoria;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Inspectoria\InspectoriaDailyLog;
use App\Services\Inspectoria\InspectoriaAccessService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspectoriaStatisticsController extends Controller
{
    public function __construct(private readonly InspectoriaAccessService $access) {}

    public function staffLateness(Request $request): JsonResponse
    {
        abort_unless($this->access->can($request->user(), InspectoriaAccessService::STATISTICS), 403);

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
        ]);
        $activeYear = AcademicYear::query()->where('is_active', true)->first();
        $academicYearEnd = $activeYear?->ends_at ? Carbon::parse($activeYear->ends_at) : now();
        $defaultEnd = $academicYearEnd->isBefore(now()) ? $academicYearEnd : now();
        $dateFrom = Carbon::parse($filters['date_from'] ?? $activeYear?->starts_at ?? now()->startOfYear())->startOfDay();
        $dateTo = Carbon::parse($filters['date_to'] ?? $defaultEnd)->endOfDay();
        $courseScoped = $this->access->isCourseScoped($request->user());
        $assignedCourseIds = $courseScoped ? $this->access->assignedCourseIds($request->user()) : collect();

        $query = InspectoriaDailyLog::query()
            ->where('category', 'asistencia')
            ->where('is_staff_lateness', true)
            ->whereBetween('happened_at', [$dateFrom, $dateTo])
            ->with([
                'lateStaff:id,full_name,cargo_id',
                'lateStaff.cargo:id,name',
                'associatedCourses:id,academic_year_id,display_name',
                'registeredBy:id,name',
            ])
            ->when(! empty($filters['staff_id']), fn ($query) => $query->where('late_staff_id', $filters['staff_id']))
            ->when(! empty($filters['course_section_id']), fn ($query) => $query->whereHas(
                'associatedCourses',
                fn ($courses) => $courses->where('course_sections.id', $filters['course_section_id']),
            ));

        $this->access->scopeDailyLogs($query, $request->user());
        $logs = $query->latest('happened_at')->get();
        $staffTotals = [];
        $courseTotals = [];
        $staffCourseRows = [];
        $unassignedTotal = 0;

        foreach ($logs as $log) {
            $staffKey = (string) ($log->late_staff_id ?: $log->late_staff_name_snapshot);
            $staffName = $log->late_staff?->full_name ?: $log->late_staff_name_snapshot ?: 'Funcionario no disponible';
            $cargoName = $log->lateStaff?->cargo?->name;
            $courses = $log->associatedCourses
                ->when($courseScoped, fn ($items) => $items->whereIn('id', $assignedCourseIds))
                ->when(! empty($filters['course_section_id']), fn ($items) => $items->where('id', (int) $filters['course_section_id']))
                ->values();

            if (! isset($staffTotals[$staffKey])) {
                $staffTotals[$staffKey] = [
                    'staff_id' => $log->late_staff_id,
                    'staff_name' => $staffName,
                    'cargo_name' => $cargoName,
                    'total' => 0,
                    'minutes_total' => 0,
                    'last_happened_at' => null,
                ];
            }
            $staffTotals[$staffKey]['total']++;
            $staffTotals[$staffKey]['minutes_total'] += (int) $log->lateness_minutes;
            $staffTotals[$staffKey]['last_happened_at'] ??= $log->happened_at;

            if ($courses->isEmpty()) {
                $unassignedTotal++;
                $rowKey = "{$staffKey}:none";
                $staffCourseRows[$rowKey] ??= [
                    'staff_id' => $log->late_staff_id,
                    'staff_name' => $staffName,
                    'cargo_name' => $cargoName,
                    'course_section_id' => null,
                    'course_name' => 'Sin curso asociado',
                    'total' => 0,
                    'minutes_total' => 0,
                    'last_happened_at' => null,
                ];
                $staffCourseRows[$rowKey]['total']++;
                $staffCourseRows[$rowKey]['minutes_total'] += (int) $log->lateness_minutes;
                $staffCourseRows[$rowKey]['last_happened_at'] ??= $log->happened_at;

                continue;
            }

            foreach ($courses as $course) {
                $courseKey = (string) $course->id;
                $courseTotals[$courseKey] ??= [
                    'course_section_id' => $course->id,
                    'course_name' => $course->display_name,
                    'total' => 0,
                    'minutes_total' => 0,
                ];
                $courseTotals[$courseKey]['total']++;
                $courseTotals[$courseKey]['minutes_total'] += (int) $log->lateness_minutes;

                $rowKey = "{$staffKey}:{$courseKey}";
                $staffCourseRows[$rowKey] ??= [
                    'staff_id' => $log->late_staff_id,
                    'staff_name' => $staffName,
                    'cargo_name' => $cargoName,
                    'course_section_id' => $course->id,
                    'course_name' => $course->display_name,
                    'total' => 0,
                    'minutes_total' => 0,
                    'last_happened_at' => null,
                ];
                $staffCourseRows[$rowKey]['total']++;
                $staffCourseRows[$rowKey]['minutes_total'] += (int) $log->lateness_minutes;
                $staffCourseRows[$rowKey]['last_happened_at'] ??= $log->happened_at;
            }
        }

        return response()->json([
            'range' => ['date_from' => $dateFrom->toDateString(), 'date_to' => $dateTo->toDateString()],
            'summary' => [
                'events_total' => $logs->count(),
                'minutes_total' => $logs->sum('lateness_minutes'),
                'staff_total' => count($staffTotals),
                'courses_total' => count($courseTotals),
                'unassigned_total' => $unassignedTotal,
            ],
            'staff_totals' => collect($staffTotals)->sortByDesc('total')->values(),
            'course_totals' => collect($courseTotals)->sortByDesc('total')->values(),
            'staff_course_rows' => collect($staffCourseRows)->sortBy([
                ['total', 'desc'],
                ['staff_name', 'asc'],
                ['course_name', 'asc'],
            ])->values(),
            'recent' => $logs->take(20)->map(fn (InspectoriaDailyLog $log) => [
                'id' => $log->id,
                'happened_at' => $log->happened_at,
                'staff_name' => $log->lateStaff?->full_name ?: $log->late_staff_name_snapshot,
                'lateness_minutes' => $log->lateness_minutes,
                'course_names' => $log->associatedCourses->pluck('display_name')->values(),
                'detail' => $log->detail,
                'registered_by' => $log->registeredBy?->name,
            ])->values(),
        ]);
    }
}
