<?php

namespace App\Http\Controllers\Inspectoria;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inspectoria\SaveInspectoriaPassRequest;
use App\Models\Inspectoria\InspectoriaPass;
use App\Services\Inspectoria\InspectoriaAccessService;
use App\Services\Inspectoria\InspectoriaPassService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspectoriaPassController extends Controller
{
    public function __construct(
        private readonly InspectoriaAccessService $access,
        private readonly InspectoriaPassService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->access->canView($request->user()), 403);
        $this->service->refreshExpired();
        $search = trim((string) $request->query('search'));
        $query = InspectoriaPass::query()->with([
            'student:id,first_name,last_name,registered_name,rut', 'courseSection:id,display_name',
            'inspector:id,full_name,rut', 'issuedBy:id,name', 'usedBy:id,name',
        ]);
        $this->access->scopeToAssignedCourses($query, $request->user());
        $query->when($search !== '', function (Builder $query) use ($search) {
            $query->where(function (Builder $inner) use ($search) {
                $inner->where('pass_code', 'like', "%{$search}%")
                    ->orWhere('student_name_snapshot', 'like', "%{$search}%")
                    ->orWhere('student_rut_snapshot', 'like', "%{$search}%")
                    ->orWhere('destination_detail', 'like', "%{$search}%");
            });
        })->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('valid_from', $request->query('date')));

        return response()->json($query->latest('issued_at')->paginate((int) $request->query('per_page', 15)));
    }

    public function store(SaveInspectoriaPassRequest $request): JsonResponse
    {
        abort_unless($this->access->can($request->user(), InspectoriaAccessService::PASSES), 403);
        abort_unless($this->access->canAccessStudent($request->user(), (int) $request->validated('student_profile_id')), 403, 'La alumna no pertenece a un curso asignado a esta inspectora.');

        return response()->json(['message' => 'Pase prioritario emitido correctamente.', 'data' => $this->service->create($request->validated(), $request->user())], 201);
    }

    public function update(SaveInspectoriaPassRequest $request, InspectoriaPass $pass): JsonResponse
    {
        abort_unless($this->access->can($request->user(), InspectoriaAccessService::PASSES), 403);
        abort_unless($this->access->canAccessCourse($request->user(), $pass->course_section_id), 403);

        return response()->json(['message' => 'Pase actualizado correctamente.', 'data' => $this->service->update($pass, $request->validated(), $request->user())]);
    }

    public function transition(Request $request, InspectoriaPass $pass, string $status): JsonResponse
    {
        abort_unless($this->access->can($request->user(), InspectoriaAccessService::PASSES), 403);
        abort_unless($this->access->canAccessCourse($request->user(), $pass->course_section_id), 403);

        return response()->json(['message' => $status === 'utilizado' ? 'Uso del pase registrado.' : 'Pase anulado.', 'data' => $this->service->transition($pass, $status, $request->user())]);
    }
}
