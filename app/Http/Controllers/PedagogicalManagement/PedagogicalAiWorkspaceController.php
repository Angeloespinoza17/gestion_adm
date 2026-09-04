<?php

namespace App\Http\Controllers\PedagogicalManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\PedagogicalManagement\StorePedagogicalAiWorkspaceReviewRequest;
use App\Http\Resources\PedagogicalManagement\PedagogicalInstrumentResource;
use App\Models\PedagogicalManagement\PedagogicalInstrument;
use App\Services\PedagogicalManagement\PedagogicalAiReportService;
use App\Services\PedagogicalManagement\PedagogicalAiWorkspaceService;
use App\Services\PedagogicalManagement\PedagogicalInstrumentAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PedagogicalAiWorkspaceController extends Controller
{
    public function catalogs(
        Request $request,
        PedagogicalInstrumentAccessService $access,
        PedagogicalAiWorkspaceService $workspace,
    ): JsonResponse {
        $school = $access->resolveSchool($request);

        return response()->json(['data' => $workspace->catalogs($school)]);
    }

    public function index(Request $request, PedagogicalAiWorkspaceService $workspace): JsonResponse
    {
        $filters = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:5,50'],
        ]);
        $paginator = $workspace->visibleQuery($request->user())
            ->with($this->summaryRelations())
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 12));

        return response()->json([
            'data' => PedagogicalInstrumentResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(
        StorePedagogicalAiWorkspaceReviewRequest $request,
        PedagogicalInstrumentAccessService $access,
        PedagogicalAiWorkspaceService $workspace,
    ): JsonResponse {
        $data = $request->validated();
        $school = $access->resolveSchool($request);
        [$instrument] = $workspace->create(
            $data,
            $request->file('file'),
            $school,
            $request->user(),
            $request,
        );

        return response()->json([
            'data' => new PedagogicalInstrumentResource($this->detail($instrument)),
        ], 202);
    }

    public function show(
        Request $request,
        PedagogicalInstrument $instrument,
        PedagogicalAiWorkspaceService $workspace,
    ): PedagogicalInstrumentResource {
        $workspace->assertCanUse($request->user(), $instrument);

        return new PedagogicalInstrumentResource($this->detail($instrument));
    }

    public function regenerate(
        Request $request,
        PedagogicalInstrument $instrument,
        PedagogicalAiWorkspaceService $workspace,
        PedagogicalAiReportService $reports,
    ): JsonResponse {
        $workspace->assertCanUse($request->user(), $instrument);
        $file = $instrument->latestFile()->first();
        abort_unless($file, 422, 'La revisión no tiene un archivo disponible.');
        $reports->requestReport($instrument, $file, $request->user(), $request);

        return response()->json([
            'data' => new PedagogicalInstrumentResource($this->detail($instrument)),
        ], 202);
    }

    private function detail(PedagogicalInstrument $instrument): PedagogicalInstrument
    {
        return $instrument->fresh()->load([
            ...$this->summaryRelations(),
            'files.uploader:id,name',
        ]);
    }

    /** @return list<string> */
    private function summaryRelations(): array
    {
        return [
            'school:id,name,rbd',
            'academicYear:id,name,year',
            'owner:id,name',
            'subject:id,name,code,color',
            'courses:id,display_name,education_level_id',
            'latestFile',
            'latestAiReport.instrumentFile',
            'latestAiReport.requester:id,name',
        ];
    }
}
