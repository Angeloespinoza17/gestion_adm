<?php

namespace App\Http\Controllers\Inspectoria;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inspectoria\SaveInspectoriaDailyLogRequest;
use App\Models\Inspectoria\InspectoriaDailyLog;
use App\Models\User;
use App\Services\Inspectoria\InspectoriaAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspectoriaDailyLogController extends Controller
{
    public function __construct(private readonly InspectoriaAccessService $access) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->access->canView($request->user()), 403);
        $search = trim((string) $request->query('search'));
        $query = InspectoriaDailyLog::query()->with([
            'student:id,first_name,last_name,registered_name,rut', 'courseSection:id,display_name',
            'inspector:id,full_name', 'registeredBy:id,name',
        ]);
        $this->access->scopeDailyLogs($query, $request->user());
        $query->when($search !== '', function (Builder $query) use ($search) {
            $query->where(function (Builder $inner) use ($search) {
                $inner->where('title', 'like', "%{$search}%")->orWhere('detail', 'like', "%{$search}%")
                    ->orWhereHas('student', fn ($student) => $student->where('registered_name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"));
            });
        })->when($request->filled('date'), fn ($q) => $q->whereDate('happened_at', $request->query('date')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->query('category')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->query('priority')))
            ->when($request->filled('course_section_id'), fn ($q) => $q->where('course_section_id', $request->query('course_section_id')));

        return response()->json($query->latest('happened_at')->paginate((int) $request->query('per_page', 15)));
    }

    public function store(SaveInspectoriaDailyLogRequest $request): JsonResponse
    {
        abort_unless($this->access->can($request->user(), InspectoriaAccessService::DAILY_LOG), 403);
        $user = $request->user();
        $payload = $request->validated();
        $this->assertPayloadAccess($user, $payload);
        $entry = InspectoriaDailyLog::query()->create([
            ...$payload, 'inspector_staff_id' => $user->staff_id,
            'registered_by_user_id' => $user->id, 'status' => $request->input('status', 'registrado'),
            'created_by' => $user->id, 'updated_by' => $user->id,
        ]);

        return response()->json(['message' => 'Entrada de bitácora registrada.', 'data' => $this->load($entry)], 201);
    }

    public function update(SaveInspectoriaDailyLogRequest $request, InspectoriaDailyLog $dailyLog): JsonResponse
    {
        abort_unless($this->access->can($request->user(), InspectoriaAccessService::DAILY_LOG), 403);
        $user = $request->user();
        abort_unless($this->canAccessEntry($user, $dailyLog), 403);
        $payload = $request->validated();
        $this->assertPayloadAccess($user, $payload);
        $dailyLog->fill($payload);
        $dailyLog->updated_by = $user->id;
        $dailyLog->save();

        return response()->json(['message' => 'Entrada actualizada.', 'data' => $this->load($dailyLog)]);
    }

    private function load(InspectoriaDailyLog $entry): InspectoriaDailyLog
    {
        return $entry->fresh(['student:id,first_name,last_name,registered_name,rut', 'courseSection:id,display_name', 'inspector:id,full_name', 'registeredBy:id,name']);
    }

    private function assertPayloadAccess(User $user, array $payload): void
    {
        if (! $this->access->isCourseScoped($user)) {
            return;
        }

        if (! empty($payload['student_profile_id'])) {
            abort_unless($this->access->canAccessStudent($user, (int) $payload['student_profile_id']), 403, 'La alumna no pertenece a un curso asignado a esta inspectora.');
        }
        if (! empty($payload['course_section_id'])) {
            abort_unless($this->access->canAccessCourse($user, (int) $payload['course_section_id']), 403, 'El curso no está asignado a esta inspectora.');
        }
    }

    private function canAccessEntry(User $user, InspectoriaDailyLog $entry): bool
    {
        if (! $this->access->isCourseScoped($user)) {
            return true;
        }

        return $entry->course_section_id
            ? $this->access->canAccessCourse($user, (int) $entry->course_section_id)
            : (int) $entry->inspector_staff_id === (int) $user->staff_id;
    }
}
