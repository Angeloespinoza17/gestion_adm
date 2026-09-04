<?php

namespace App\Http\Controllers\Infirmary;

use App\Http\Controllers\Controller;
use App\Http\Requests\Infirmary\SaveInfirmaryDailyLogRequest;
use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\Infirmary\InfirmaryDailyLog;
use App\Models\StudentProfile;
use App\Services\Infirmary\InfirmaryAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InfirmaryDailyLogController extends Controller
{
    public function __construct(private readonly InfirmaryAccessService $access) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->access->canViewDailyLog($request->user()), 403);

        $query = InfirmaryDailyLog::query()->with([
            'student:id,first_name,last_name,registered_name,rut',
            'courseSection:id,display_name',
            'registeredBy:id,name',
        ]);
        $this->applyFilters($query, $request);

        $perPage = min(max((int) $request->integer('per_page', 15), 10), 100);
        $logs = $query
            ->orderByDesc('happened_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $today = today()->startOfDay();
        $tomorrow = $today->copy()->addDay();
        $summary = InfirmaryDailyLog::query()
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw('SUM(CASE WHEN happened_at >= ? AND happened_at < ? THEN 1 ELSE 0 END) as today_records', [$today, $tomorrow])
            ->selectRaw("SUM(CASE WHEN requires_follow_up = 1 AND status <> 'cerrado' THEN 1 ELSE 0 END) as pending_follow_up")
            ->selectRaw("SUM(CASE WHEN priority IN ('alta', 'urgente') AND status <> 'cerrado' THEN 1 ELSE 0 END) as open_high_priority_records")
            ->first();

        return response()->json([
            ...$logs->toArray(),
            'summary' => [
                'total_records' => (int) ($summary?->total_records ?? 0),
                'today_records' => (int) ($summary?->today_records ?? 0),
                'pending_follow_up' => (int) ($summary?->pending_follow_up ?? 0),
                'high_priority' => (int) ($summary?->open_high_priority_records ?? 0),
            ],
            'capabilities' => [
                'can_manage' => $this->access->canManageDailyLog($request->user()),
            ],
        ]);
    }

    public function catalogs(Request $request): JsonResponse
    {
        abort_unless($this->access->canViewDailyLog($request->user()), 403);
        $academicYear = $this->currentAcademicYear();

        return response()->json([
            'categories' => $this->options(InfirmaryDailyLog::CATEGORIES),
            'priorities' => $this->options(InfirmaryDailyLog::PRIORITIES),
            'statuses' => $this->options(InfirmaryDailyLog::STATUSES),
            'current_academic_year' => $academicYear?->only(['id', 'name', 'year']),
            'courses' => CourseSection::query()
                ->when($academicYear, fn (Builder $query) => $query->where('academic_year_id', $academicYear->id))
                ->when(! $academicYear, fn (Builder $query) => $query->whereRaw('1 = 0'))
                ->where('active', true)
                ->orderBy('display_name')
                ->get(['id', 'academic_year_id', 'display_name']),
            'capabilities' => [
                'can_manage' => $this->access->canManageDailyLog($request->user()),
            ],
        ]);
    }

    public function students(Request $request): JsonResponse
    {
        abort_unless($this->access->canViewDailyLog($request->user()), 403);
        $search = trim((string) $request->query('search'));

        if (mb_strlen($search) < 2) {
            return response()->json(['data' => []]);
        }

        $academicYear = $this->currentAcademicYear();
        $students = StudentProfile::query()
            ->with(['enrollments' => fn ($enrollments) => $enrollments
                ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
                ->with('courseSection:id,display_name')])
            ->where(function (Builder $query) use ($search): void {
                $query
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('registered_name', 'like', "%{$search}%")
                    ->orWhere('rut', 'like', "%{$search}%");
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(20)
            ->get(['id', 'first_name', 'last_name', 'registered_name', 'rut']);

        return response()->json([
            'data' => $students->map(function (StudentProfile $student) use ($academicYear): array {
                $enrollment = $academicYear ? $student->preferredEnrollment($academicYear) : null;

                return [
                    'id' => $student->id,
                    'name' => $student->registered_name_resolved,
                    'rut' => $student->rut,
                    'course_id' => $enrollment?->course_section_id,
                    'course' => $enrollment?->snapshot_course_display_name
                        ?? $enrollment?->courseSection?->display_name,
                ];
            })->values(),
        ]);
    }

    public function store(SaveInfirmaryDailyLogRequest $request): JsonResponse
    {
        abort_unless($this->access->canManageDailyLog($request->user()), 403);
        $attributes = $this->normalizePayload($request->validated());

        $entry = InfirmaryDailyLog::query()->create([
            ...$attributes,
            'registered_by_user_id' => $request->user()->id,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Registro incorporado a la bitácora de Enfermería.',
            'data' => $this->load($entry),
        ], 201);
    }

    public function update(SaveInfirmaryDailyLogRequest $request, InfirmaryDailyLog $dailyLog): JsonResponse
    {
        abort_unless($this->access->canManageDailyLog($request->user()), 403);
        $attributes = $this->normalizePayload($request->validated());

        $entry = DB::transaction(function () use ($dailyLog, $attributes, $request): InfirmaryDailyLog {
            $locked = InfirmaryDailyLog::query()->lockForUpdate()->findOrFail($dailyLog->id);
            $locked->fill([
                ...$attributes,
                'updated_by' => $request->user()->id,
            ])->save();

            return $locked;
        }, 3);

        return response()->json([
            'message' => 'Registro de bitácora actualizado.',
            'data' => $this->load($entry),
        ]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $search = trim((string) $request->query('search'));
        $query
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('detail', 'like', "%{$search}%")
                        ->orWhere('action_taken', 'like', "%{$search}%")
                        ->orWhereHas('student', function (Builder $students) use ($search): void {
                            $students
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('registered_name', 'like', "%{$search}%")
                                ->orWhere('rut', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('date'), fn (Builder $builder) => $builder->whereDate('happened_at', $request->query('date')))
            ->when($request->filled('category'), fn (Builder $builder) => $builder->where('category', $request->query('category')))
            ->when($request->filled('priority'), fn (Builder $builder) => $builder->where('priority', $request->query('priority')))
            ->when($request->filled('status'), fn (Builder $builder) => $builder->where('status', $request->query('status')))
            ->when($request->boolean('follow_up'), fn (Builder $builder) => $builder->where('requires_follow_up', true)->where('status', '<>', 'cerrado'));
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $attributes = Arr::only($payload, [
            'student_profile_id', 'course_section_id', 'happened_at', 'category', 'priority',
            'status', 'title', 'detail', 'action_taken', 'requires_follow_up', 'follow_up_note',
        ]);
        $academicYear = $this->currentAcademicYear();

        if (! empty($attributes['student_profile_id'])) {
            $student = StudentProfile::query()->with([
                'enrollments' => fn ($query) => $query
                    ->when($academicYear, fn ($enrollments) => $enrollments->where('academic_year_id', $academicYear->id))
                    ->with('courseSection:id,display_name'),
            ])->findOrFail((int) $attributes['student_profile_id']);
            $attributes['course_section_id'] = $academicYear
                ? $student->preferredEnrollment($academicYear)?->course_section_id
                : null;
        } elseif (! empty($attributes['course_section_id'])) {
            $validCourse = $academicYear && CourseSection::query()
                ->whereKey((int) $attributes['course_section_id'])
                ->where('academic_year_id', $academicYear->id)
                ->where('active', true)
                ->exists();

            if (! $validCourse) {
                throw ValidationException::withMessages([
                    'course_section_id' => 'Selecciona un curso vigente del año escolar actual.',
                ]);
            }
        }

        if (! ($attributes['requires_follow_up'] ?? false) || ($attributes['status'] ?? null) === 'cerrado') {
            $attributes['requires_follow_up'] = false;
            $attributes['follow_up_note'] = null;
        }

        return $attributes;
    }

    private function load(InfirmaryDailyLog $entry): InfirmaryDailyLog
    {
        return $entry->fresh([
            'student:id,first_name,last_name,registered_name,rut',
            'courseSection:id,display_name',
            'registeredBy:id,name',
        ]);
    }

    private function currentAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('year', today()->year)->first();
    }

    /** @param array<string, string> $items
     * @return array<int, array{value: string, label: string}>
     */
    private function options(array $items): array
    {
        return collect($items)
            ->map(fn (string $label, string $value): array => compact('value', 'label'))
            ->values()
            ->all();
    }
}
