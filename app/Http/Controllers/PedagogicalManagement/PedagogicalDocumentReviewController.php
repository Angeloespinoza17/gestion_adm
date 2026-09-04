<?php

namespace App\Http\Controllers\PedagogicalManagement;

use App\Enums\PedagogicalManagement\InstrumentWorkflowStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PedagogicalManagement\StorePedagogicalReviewRequest;
use App\Http\Resources\PedagogicalManagement\PedagogicalInstrumentResource;
use App\Http\Resources\PedagogicalManagement\PedagogicalReviewResource;
use App\Models\PedagogicalManagement\PedagogicalInstrument;
use App\Services\PedagogicalManagement\PedagogicalDocumentReviewService;
use App\Services\PedagogicalManagement\PedagogicalInstrumentAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PedagogicalDocumentReviewController extends Controller
{
    public function index(Request $request, PedagogicalInstrumentAccessService $access): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('pedagogical-instruments.decide'), 403);
        $filters = $request->validate([
            'school_id' => ['sometimes', 'integer', 'exists:lcd_schools,id'],
            'workflow_status' => ['sometimes', Rule::enum(InstrumentWorkflowStatus::class)],
            'search' => ['sometimes', 'string', 'max:120'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:10,100'],
        ]);
        $query = $access->visibleQuery($request->user())
            ->whereNotIn('workflow_status', [
                InstrumentWorkflowStatus::Draft->value,
                InstrumentWorkflowStatus::Archived->value,
            ])
            ->with([
                'school:id,name,rbd', 'academicYear:id,name,year', 'owner:id,name',
                'subject:id,name,code,color', 'courses:id,display_name,education_level_id',
                'latestFile',
            ])
            ->when($filters['school_id'] ?? null, fn (Builder $builder, int $id) => $builder->where('school_id', $id))
            ->when($filters['search'] ?? null, fn (Builder $builder, string $search) => $builder->where(function (Builder $nested) use ($search): void {
                $nested->where('title', 'like', '%'.$search.'%')
                    ->orWhereHas('owner', fn (Builder $owner) => $owner->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('subject', fn (Builder $subject) => $subject->where('name', 'like', '%'.$search.'%'));
            }));
        $counts = (clone $query)->reorder()->selectRaw('workflow_status, COUNT(*) as total')->groupBy('workflow_status')->pluck('total', 'workflow_status');
        $query->when($filters['workflow_status'] ?? null, fn (Builder $builder, string $status) => $builder->where('workflow_status', $status));
        $paginator = $query->orderByRaw("CASE WHEN workflow_status IN ('submitted','resubmitted') THEN 0 ELSE 1 END")
            ->orderByDesc('submitted_at')->paginate((int) ($filters['per_page'] ?? 15));

        return response()->json([
            'data' => PedagogicalInstrumentResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'status_counts' => $counts,
            ],
        ]);
    }

    public function store(
        StorePedagogicalReviewRequest $request,
        PedagogicalInstrument $instrument,
        PedagogicalDocumentReviewService $service,
    ): JsonResponse {
        $review = $service->decide($instrument, $request->validated(), $request->user(), $request);

        return response()->json([
            'message' => match ($review->decision->value) {
                'approved' => 'Instrumento aprobado y enviado a Centro de Apuntes.',
                'approved_with_observations' => 'Instrumento aprobado con observaciones y enviado a Centro de Apuntes.',
                default => 'Rectificación solicitada al docente.',
            },
            'data' => new PedagogicalReviewResource($review),
        ], 201);
    }
}
