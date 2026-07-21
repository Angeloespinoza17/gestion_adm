<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceSimulationRequest;
use App\Http\Requests\Attendance\AttendanceStatisticsFilterRequest;
use App\Models\Attendance\AttendanceAlert;
use App\Models\Attendance\AttendanceProjectionRun;
use App\Models\Attendance\AttendanceStatisticsAuditLog;
use App\Models\StudentProfile;
use App\Services\Attendance\AttendanceAggregationService;
use App\Services\Attendance\AttendanceCalculationService;
use App\Services\Attendance\AttendanceDataQualityService;
use App\Services\Attendance\AttendanceFinancialImpactService;
use App\Services\Attendance\AttendanceStatisticsAuditService;
use App\Services\Attendance\AttendanceStatisticsCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceStatisticsController extends Controller
{
    public function __construct(
        private readonly AttendanceAggregationService $aggregation,
        private readonly AttendanceStatisticsCache $cache,
    ) {}

    public function dashboard(AttendanceStatisticsFilterRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $cacheFilters = [...$filters, 'user_id' => $request->user()?->id];
        $payload = $this->cache->remember('dashboard', $cacheFilters, fn () => $this->aggregation->dashboard($filters, $request->user()));

        return response()->json($payload);
    }

    public function timeline(AttendanceStatisticsFilterRequest $request): JsonResponse
    {
        $payload = $this->aggregation->dashboard($request->validated(), $request->user());

        return response()->json(['meta' => $payload['meta'], 'timeline' => $payload['timeline'], 'monthly' => $payload['monthly'], 'weekdays' => $payload['weekdays']]);
    }

    public function courses(AttendanceStatisticsFilterRequest $request): JsonResponse
    {
        $payload = $this->aggregation->dashboard($request->validated(), $request->user());

        return response()->json(['meta' => $payload['meta'], 'courses' => $payload['courses'], 'levels' => $payload['levels']]);
    }

    public function students(AttendanceStatisticsFilterRequest $request): JsonResponse
    {
        return response()->json($this->aggregation->students($request->validated()));
    }

    public function student(
        AttendanceStatisticsFilterRequest $request,
        StudentProfile $studentProfile,
        AttendanceStatisticsAuditService $audit,
    ): JsonResponse {
        $audit->log('student_sensitive_view', $studentProfile, $request->user(), metadata: ['filters' => $request->validated()], request: $request);

        return response()->json($this->aggregation->student($request->validated(), $studentProfile));
    }

    public function heatmap(AttendanceStatisticsFilterRequest $request): JsonResponse
    {
        return response()->json($this->aggregation->heatmap($request->validated()));
    }

    public function risk(AttendanceStatisticsFilterRequest $request): JsonResponse
    {
        $payload = $this->aggregation->dashboard($request->validated(), $request->user());

        return response()->json([
            'meta' => $payload['meta'],
            'distribution' => $payload['risk_distribution'],
            'statistics' => $payload['statistics'],
            'courses' => $payload['courses'],
        ]);
    }

    public function alerts(AttendanceStatisticsFilterRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $yearId = (int) ($filters['academic_year_id'] ?? 0);
        $baseQuery = AttendanceAlert::query()
            ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId))
            ->when($filters['course_section_id'] ?? null, fn ($query, $id) => $query->where('course_section_id', (int) $id))
            ->when($filters['student_profile_id'] ?? null, fn ($query, $id) => $query->where('student_profile_id', (int) $id));
        $groups = (clone $baseQuery)
            ->select('course_section_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical")
            ->selectRaw('COUNT(DISTINCT student_profile_id) as students')
            ->groupBy('course_section_id')
            ->with('courseSection:id,display_name')
            ->orderByDesc('critical')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($group) => [
                'course_id' => $group->course_section_id,
                'course' => $group->courseSection?->display_name ?? 'Sin curso',
                'total' => (int) $group->total,
                'critical' => (int) $group->critical,
                'students' => (int) $group->students,
            ])
            ->values();
        $query = $baseQuery
            ->with(['studentProfile:id,first_name,last_name,registered_name,rut', 'courseSection:id,display_name', 'assignedTo:id,name'])
            ->withCount('followups')
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 ELSE 1 END")
            ->latest('detected_on');
        $paginator = $query->paginate((int) ($filters['per_page'] ?? 25));

        return response()->json([
            'data' => collect($paginator->items())->values(),
            'groups' => $groups,
            'meta' => [
                'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(), 'total' => $paginator->total(),
            ],
        ]);
    }

    public function simulate(
        AttendanceSimulationRequest $request,
        AttendanceCalculationService $calculations,
        AttendanceFinancialImpactService $financial,
        AttendanceStatisticsAuditService $audit,
    ): JsonResponse {
        $data = $request->validated();
        $result = $calculations->project(
            (int) $data['observed_present'],
            (int) $data['observed_expected'],
            (int) $data['remaining_expected'],
            (float) $data['future_rate'],
            (float) $data['target_rate'],
        );
        $run = AttendanceProjectionRun::query()->create([
            'academic_year_id' => $data['academic_year_id'],
            'course_section_id' => $data['course_section_id'] ?? null,
            'method' => $data['method'] ?? 'custom_scenario',
            'model_version' => '1.0',
            'inputs' => $data,
            'results' => $result,
            'confidence' => $data['confidence'] ?? null,
            'created_by' => $request->user()?->id,
        ]);
        $impact = $financial->calculate((int) $data['academic_year_id'], [
            'attendance_rate' => $calculations->rate((int) $data['observed_present'], (int) $data['observed_expected']),
            'expected' => (int) $data['observed_expected'] + (int) $data['remaining_expected'],
        ], (float) $result['projected_rate']);
        $audit->log('projection_simulated', $run, $request->user(), newValues: $data, request: $request);

        return response()->json(['projection' => $result, 'financial_impact' => $impact, 'run_id' => $run->id], 201);
    }

    public function financial(
        AttendanceStatisticsFilterRequest $request,
        AttendanceFinancialImpactService $financial,
    ): JsonResponse {
        $payload = $this->aggregation->dashboard($request->validated(), $request->user());
        $yearId = (int) $payload['meta']['academic_year']['id'];
        $rate = (float) ($payload['summary']['attendance_rate'] ?? 0);

        return response()->json($financial->calculate($yearId, $payload['summary'], $rate));
    }

    public function dataQuality(
        AttendanceStatisticsFilterRequest $request,
        AttendanceDataQualityService $quality,
    ): JsonResponse {
        $filters = $request->validated();
        $payload = $this->aggregation->dashboard($filters, $request->user());
        $issues = $quality->scan((int) $payload['meta']['academic_year']['id'], isset($filters['course_section_id']) ? (int) $filters['course_section_id'] : null);

        return response()->json([
            'summary' => [
                'open' => $issues->where('status', 'open')->count(),
                'critical' => $issues->where('status', 'open')->where('severity', 'critical')->count(),
                'warning' => $issues->where('status', 'open')->where('severity', 'warning')->count(),
                'resolved' => $issues->where('status', 'resolved')->count(),
            ],
            'groups' => $issues->groupBy('type')->map(fn ($group, $type) => ['type' => $type, 'total' => $group->count(), 'critical' => $group->where('severity', 'critical')->count()])->values(),
            'data' => $issues,
        ]);
    }

    public function audit(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:100'], 'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);
        $paginator = AttendanceStatisticsAuditLog::query()
            ->when($filters['action'] ?? null, fn ($query, $value) => $query->where('action', $value))
            ->when($filters['user_id'] ?? null, fn ($query, $value) => $query->where('user_id', $value))
            ->when($filters['from'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($filters['to'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '<=', $value))
            ->with('user:id,name')
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 25));

        return response()->json($paginator);
    }
}
