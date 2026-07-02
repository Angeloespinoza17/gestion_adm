<?php

namespace App\Http\Controllers\ApoyoProfesional;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApoyoProfesional\RespondApoyoDerivacionRequest;
use App\Http\Requests\ApoyoProfesional\SaveApoyoDerivacionRequest;
use App\Models\ApoyoProfesional\ApoyoDerivacion;
use App\Services\ApoyoProfesional\ApoyoProfesionalAccessService;
use App\Services\ApoyoProfesional\ApoyoProfesionalDerivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApoyoProfesionalDerivationController extends Controller
{
    public function __construct(
        private readonly ApoyoProfesionalAccessService $accessService,
        private readonly ApoyoProfesionalDerivationService $derivationService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ApoyoDerivacion::class);

        $query = $this->accessService->applyDerivationVisibility(
            ApoyoDerivacion::query()
                ->with([
                    'attention:id,student_profile_id,professional_role_name,professional_area_name,reason_summary,status,confidentiality_level',
                    'student:id,first_name,last_name,registered_name,rut',
                    'destinationProfessional.staff:id,full_name',
                    'destinationUser:id,name',
                ]),
            $request->user(),
        );

        $status = trim((string) $request->query('status'));
        $studentId = $request->query('student_profile_id');
        $area = trim((string) $request->query('destination_area_name'));
        $from = trim((string) $request->query('from'));
        $to = trim((string) $request->query('to'));

        $query
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->when($studentId, fn ($builder) => $builder->where('student_profile_id', $studentId))
            ->when($area !== '', fn ($builder) => $builder->where('destination_area_name', $area))
            ->when($from !== '', fn ($builder) => $builder->whereDate('derived_at', '>=', $from))
            ->when($to !== '', fn ($builder) => $builder->whereDate('derived_at', '<=', $to));

        return response()->json(
            $query->latest('derived_at')->paginate((int) $request->query('per_page', 12))
        );
    }

    public function store(SaveApoyoDerivacionRequest $request): JsonResponse
    {
        $this->authorize('create', ApoyoDerivacion::class);

        $derivation = $this->derivationService->store($request->validated(), $request->user());

        return response()->json([
            'message' => 'Derivación registrada correctamente.',
            'data' => $derivation,
        ], 201);
    }

    public function show(ApoyoDerivacion $derivation): JsonResponse
    {
        $this->authorize('view', $derivation);

        return response()->json([
            'data' => $derivation->load([
                'attention:id,student_profile_id,professional_role_name,professional_area_name,reason_summary,status,confidentiality_level',
                'student:id,first_name,last_name,registered_name,rut',
                'destinationProfessional.staff:id,full_name',
                'destinationUser:id,name',
                'documents.uploadedBy:id,name',
            ]),
        ]);
    }

    public function update(SaveApoyoDerivacionRequest $request, ApoyoDerivacion $derivation): JsonResponse
    {
        $this->authorize('update', $derivation);

        $updated = $this->derivationService->update($derivation, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Derivación actualizada correctamente.',
            'data' => $updated,
        ]);
    }

    public function respond(RespondApoyoDerivacionRequest $request, ApoyoDerivacion $derivation): JsonResponse
    {
        abort_unless($this->accessService->canRespondDerivation($request->user()), 403);
        $this->authorize('view', $derivation);

        return response()->json([
            'message' => 'Respuesta de derivación registrada correctamente.',
            'data' => $this->derivationService->respond($derivation, $request->validated(), $request->user()),
        ]);
    }
}
