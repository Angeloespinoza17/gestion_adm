<?php

namespace App\Http\Controllers\Infirmary;

use App\Http\Controllers\Controller;
use App\Http\Requests\Infirmary\SaveInfirmaryCallRequest;
use App\Models\Infirmary\InfirmaryAttentionCall;
use App\Services\Infirmary\InfirmaryAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InfirmaryCallLogController extends Controller
{
    public function __construct(
        private readonly InfirmaryAccessService $accessService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('call_status'));
        $studentId = $request->query('student_profile_id');
        $from = trim((string) $request->query('from'));
        $to = trim((string) $request->query('to'));

        return response()->json(
            InfirmaryAttentionCall::query()
                ->with(['student:id,first_name,last_name,rut', 'attention:id,attention_category,attended_at', 'calledBy:id,name'])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($inner) use ($search) {
                        $inner
                            ->where('person_contacted', 'like', "%{$search}%")
                            ->orWhere('phone_number', 'like', "%{$search}%")
                            ->orWhere('reason', 'like', "%{$search}%")
                            ->orWhereHas('student', fn ($student) => $student
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('rut', 'like', "%{$search}%"));
                    });
                })
                ->when($status !== '', fn ($query) => $query->where('call_status', $status))
                ->when($studentId, fn ($query) => $query->where('student_profile_id', $studentId))
                ->when($from !== '', fn ($query) => $query->whereDate('called_at', '>=', $from))
                ->when($to !== '', fn ($query) => $query->whereDate('called_at', '<=', $to))
                ->latest('called_at')
                ->paginate((int) $request->query('per_page', 15))
        );
    }

    public function store(SaveInfirmaryCallRequest $request): JsonResponse
    {
        abort_unless($this->accessService->canCreateAttention($request->user()) || $this->accessService->canEditAttention($request->user()), 403);

        $call = InfirmaryAttentionCall::query()->create(array_merge(
            $request->validated(),
            ['called_by_user_id' => $request->validated()['called_by_user_id'] ?? $request->user()?->id]
        ));

        return response()->json([
            'message' => 'Llamado registrado correctamente.',
            'data' => $call->fresh(['student:id,first_name,last_name,rut', 'calledBy:id,name']),
        ], 201);
    }

    public function update(SaveInfirmaryCallRequest $request, InfirmaryAttentionCall $call): JsonResponse
    {
        abort_unless($this->accessService->canEditAttention($request->user()), 403);

        $call->update(array_merge(
            $request->validated(),
            ['called_by_user_id' => $request->validated()['called_by_user_id'] ?? $call->called_by_user_id ?? $request->user()?->id]
        ));

        return response()->json([
            'message' => 'Llamado actualizado correctamente.',
            'data' => $call->fresh(['student:id,first_name,last_name,rut', 'calledBy:id,name']),
        ]);
    }

    public function destroy(InfirmaryAttentionCall $call): JsonResponse
    {
        abort_unless($this->accessService->canDeleteAttention(request()->user()), 403);

        $call->delete();

        return response()->json([
            'message' => 'Llamado eliminado correctamente.',
        ]);
    }
}
