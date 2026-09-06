<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Convivencia\ConvertConvivenciaRecordToCaseRequest;
use App\Http\Requests\Convivencia\SaveConvivenciaDerivationRequest;
use App\Models\Convivencia\ConvivenciaDerivation;
use App\Services\Convivencia\ConvivenciaAccessService;
use App\Services\Convivencia\ConvivenciaDerivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConvivenciaDerivationController extends Controller
{
    public function __construct(
        private readonly ConvivenciaDerivationService $derivationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ConvivenciaDerivation::class);

        $query = app(ConvivenciaAccessService::class)
            ->applyDerivationVisibility(
                ConvivenciaDerivation::query()->with([
                    'case:id,folio,status',
                    'student:id,first_name,last_name,registered_name,rut',
                    'courseSection:id,display_name',
                    'destinationDepartment:id,name',
                    'destinationStaff:id,full_name',
                    'destinationUser:id,name',
                    'externalInstitution:id,name',
                    'responsibleUser:id,name',
                ]),
                $request->user(),
            );

        $search = trim((string) $request->query('search'));
        $query
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner
                        ->where('destination_label', 'like', "%{$search}%")
                        ->orWhere('motive', 'like', "%{$search}%")
                        ->orWhereHas('case', fn ($caseQuery) => $caseQuery->where('folio', 'like', "%{$search}%"))
                        ->orWhereHas('student', function ($studentQuery) use ($search) {
                            $studentQuery
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('registered_name', 'like', "%{$search}%")
                                ->orWhere('rut', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->query('scope'), fn ($builder, $value) => $builder->where('scope', $value))
            ->when($request->query('status'), fn ($builder, $value) => $builder->where('status', $value))
            ->when($request->query('student_profile_id'), fn ($builder, $value) => $builder->where('student_profile_id', $value))
            ->when($request->query('course_section_id'), fn ($builder, $value) => $builder->where('course_section_id', $value))
            ->when($request->query('from'), fn ($builder, $value) => $builder->whereDate('derived_at', '>=', $value))
            ->when($request->query('to'), fn ($builder, $value) => $builder->whereDate('derived_at', '<=', $value));

        $perPage = min(max((int) $request->query('per_page', 12), 1), 50);

        return response()->json($query->latest('derived_at')->paginate($perPage));
    }

    public function store(SaveConvivenciaDerivationRequest $request): JsonResponse
    {
        $this->authorize('create', ConvivenciaDerivation::class);
        $this->authorizeScope($request);

        $derivation = $this->derivationService->store($request->validated(), $request->user());

        return response()->json([
            'message' => 'Derivación registrada correctamente.',
            'data' => $derivation,
        ], 201);
    }

    public function show(ConvivenciaDerivation $derivation): JsonResponse
    {
        $this->authorize('view', $derivation);
        $accessService = app(ConvivenciaAccessService::class);
        $user = request()->user();

        return response()->json([
            'data' => $derivation->load([
                'case:id,folio,status,classification_label,criticality_label',
                'academicYear:id,name,year',
                'courseSection:id,display_name',
                'student:id,first_name,last_name,registered_name,rut',
                'destinationDepartment:id,name',
                'destinationStaff:id,full_name',
                'destinationUser:id,name',
                'externalInstitution:id,name,category,contact_name,contact_email,contact_phone',
                'responsibleUser:id,name',
                'attachments' => fn ($query) => $accessService
                    ->applyAttachmentVisibility($query, $user)
                    ->with('uploadedBy:id,name'),
                'statusLogs.changedBy:id,name',
            ]),
        ]);
    }

    public function update(SaveConvivenciaDerivationRequest $request, ConvivenciaDerivation $derivation): JsonResponse
    {
        $this->authorize('update', $derivation);
        $this->authorizeScope($request);

        $updated = $this->derivationService->update($derivation, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Derivación actualizada correctamente.',
            'data' => $updated,
        ]);
    }

    public function destroy(ConvivenciaDerivation $derivation): JsonResponse
    {
        $this->authorize('delete', $derivation);

        $derivation->delete();

        return response()->json([
            'message' => 'Derivación archivada correctamente.',
        ]);
    }

    public function convertToCase(
        ConvertConvivenciaRecordToCaseRequest $request,
        ConvivenciaDerivation $derivation,
    ): JsonResponse {
        $this->authorize('update', $derivation);
        abort_unless(app(ConvivenciaAccessService::class)->canCreateCase($request->user()), 403);

        $case = $this->derivationService->convertToCase($derivation, $request->validated(), $request->user());

        return response()->json([
            'message' => 'La derivación fue convertida correctamente en un caso.',
            'data' => $case,
        ]);
    }

    private function authorizeScope(SaveConvivenciaDerivationRequest $request): void
    {
        $accessService = app(ConvivenciaAccessService::class);
        $allowed = $request->validated('scope') === 'external'
            ? $accessService->canManageExternalDerivations($request->user())
            : $accessService->canManageInternalDerivations($request->user());

        abort_unless($allowed, 403);
    }
}
