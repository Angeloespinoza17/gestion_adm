<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSiteOrganizationRequest;
use App\Http\Requests\SaveSiteOrganizationRoleRequest;
use App\Http\Resources\SiteOrganizationResource;
use App\Models\AcademicYear;
use App\Models\RiskPrevention\RiskPreventionJointCommittee;
use App\Models\SiteOrganization;
use App\Models\SiteOrganizationRole;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Services\SiteOrganizationAccessService;
use App\Services\SiteOrganizationManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class SiteOrganizationController extends Controller
{
    public function __construct(
        private readonly SiteOrganizationAccessService $accessService,
        private readonly SiteOrganizationManagementService $managementService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $type = $this->validatedType($request);
        abort_unless($this->accessService->canView($request->user(), $type), 403);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(SiteOrganization::STATUSES)],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $year = isset($filters['year']) ? (int) $filters['year'] : null;
        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 12;

        if ($type === 'joint_committee') {
            $stats = RiskPreventionJointCommittee::query()
                ->selectRaw('COUNT(*) as total')
                ->selectRaw(
                    "SUM(CASE WHEN web_status = 'published' AND active = 1 AND web_published_at IS NOT NULL AND web_published_at <= ? THEN 1 ELSE 0 END) as published",
                    [now()],
                )
                ->selectRaw("SUM(CASE WHEN web_status = 'draft' THEN 1 ELSE 0 END) as draft")
                ->selectRaw("SUM(CASE WHEN web_status = 'archived' THEN 1 ELSE 0 END) as archived")
                ->selectRaw('SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active')
                ->first();
            $items = RiskPreventionJointCommittee::query()
                ->with([
                    'staffMembers' => fn ($query) => $query
                        ->select(['staff.id', 'staff.full_name', 'staff.cargo_id'])
                        ->with('cargo:id,name'),
                ])
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->when(in_array($status, SiteOrganization::STATUSES, true), fn ($query) => $query->where('web_status', $status))
                ->when($year, fn ($query) => $query->whereYear('starts_on', $year))
                ->orderByRaw("CASE web_status WHEN 'published' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
                ->orderByDesc('active')
                ->orderByDesc('starts_on')
                ->orderByDesc('id')
                ->paginate($perPage);
        } else {
            $stats = SiteOrganization::query()
                ->ofType($type)
                ->selectRaw('COUNT(*) as total')
                ->selectRaw(
                    "SUM(CASE WHEN status = 'published' AND active = 1 AND published_at IS NOT NULL AND published_at <= ? THEN 1 ELSE 0 END) as published",
                    [now()],
                )
                ->selectRaw("SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft")
                ->selectRaw("SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived")
                ->selectRaw('SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active')
                ->first();
            $items = SiteOrganization::query()
                ->ofType($type)
                ->with('members.role:id,organization_type,name,section,sort_order,active')
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($inner) use ($search): void {
                        $inner->where('name', 'like', "%{$search}%")
                            ->orWhere('summary', 'like', "%{$search}%");
                    });
                })
                ->when(in_array($status, SiteOrganization::STATUSES, true), fn ($query) => $query->where('status', $status))
                ->when($year, fn ($query) => $query->where('year', $year))
                ->orderByRaw("CASE status WHEN 'published' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
                ->orderByDesc('year')
                ->orderByDesc('id')
                ->paginate($perPage);
        }

        return SiteOrganizationResource::collection($items)->additional([
            'summary' => [
                'total' => (int) ($stats?->total ?? 0),
                'published' => (int) ($stats?->published ?? 0),
                'draft' => (int) ($stats?->draft ?? 0),
                'archived' => (int) ($stats?->archived ?? 0),
                'active' => (int) ($stats?->active ?? 0),
            ],
            'capabilities' => [
                'can_manage' => $this->accessService->canManage($request->user(), $type),
            ],
        ]);
    }

    public function catalogs(Request $request): JsonResponse
    {
        $type = $this->validatedType($request);
        abort_unless($this->accessService->canView($request->user(), $type), 403);

        return response()->json([
            'types' => [
                ['value' => 'cgpa', 'label' => 'Centro General de Padres y Apoderados'],
                ['value' => 'cde', 'label' => 'Centro de Estudiantes'],
                ['value' => 'joint_committee', 'label' => 'Comité Paritario'],
            ],
            'statuses' => [
                ['value' => 'draft', 'label' => 'Borrador'],
                ['value' => 'published', 'label' => 'Publicado'],
                ['value' => 'archived', 'label' => 'Archivado'],
            ],
            'sections' => [
                ['value' => 'leadership', 'label' => 'Directiva'],
                ['value' => 'member', 'label' => 'Integrantes'],
                ['value' => 'advisor', 'label' => 'Equipo asesor'],
            ],
            'roles' => in_array($type, SiteOrganization::TYPES, true)
                ? SiteOrganizationRole::query()
                    ->where('organization_type', $type)
                    ->where('active', true)
                    ->orderByDesc('active')
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'organization_type', 'name', 'section', 'sort_order', 'active'])
                : [],
            'academic_years' => AcademicYear::query()
                ->ordered()
                ->get(['id', 'year', 'name', 'is_active', 'is_closed']),
            'capabilities' => [
                'can_manage' => $this->accessService->canManage($request->user(), $type),
            ],
        ]);
    }

    public function show(Request $request, string $type, int $id): SiteOrganizationResource
    {
        $type = $this->routeType($type);
        abort_unless($this->accessService->canView($request->user(), $type), 403);

        return new SiteOrganizationResource($this->managementService->find($type, $id));
    }

    public function store(SaveSiteOrganizationRequest $request): JsonResponse
    {
        $type = $request->organizationType();
        $organization = $this->managementService->create(
            $type,
            $request->validated(),
            $request->user(),
        );

        return response()->json([
            'message' => $this->savedMessage($type, true),
            'data' => (new SiteOrganizationResource($organization))->resolve($request),
        ], 201);
    }

    public function update(
        SaveSiteOrganizationRequest $request,
        string $type,
        int $id,
    ): JsonResponse {
        $type = $this->routeType($type);
        $organization = $this->managementService->update(
            $type,
            $id,
            $request->validated(),
            $request->user(),
        );

        return response()->json([
            'message' => $this->savedMessage($type, false),
            'data' => (new SiteOrganizationResource($organization))->resolve($request),
        ]);
    }

    public function destroy(Request $request, string $type, int $id): JsonResponse
    {
        $type = $this->routeType($type);
        abort_unless($this->accessService->canManage($request->user(), $type), 403);

        $organization = $this->managementService->archive($type, $id, $request->user());

        return response()->json([
            'message' => 'El período fue archivado y su historial se conservó.',
            'data' => (new SiteOrganizationResource($organization))->resolve($request),
        ]);
    }

    public function students(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canView($request->user(), 'cde'), 403);
        $payload = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim((string) ($payload['search'] ?? ''));

        $items = StudentEnrollment::query()
            ->select([
                'id',
                'student_profile_id',
                'academic_year_id',
                'course_section_id',
                'snapshot_course_display_name',
            ])
            ->whereHas('academicYear', fn ($query) => $query->where('year', $payload['year']))
            ->whereNotIn('enrollment_status', StudentEnrollment::NON_ROSTER_STATUS_VALUES)
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('studentProfile', function ($studentQuery) use ($search): void {
                    $studentQuery->where(function ($inner) use ($search): void {
                        $inner->where('registered_name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
                });
            })
            ->with([
                'studentProfile:id,first_name,last_name,registered_name',
                'courseSection:id,display_name',
            ])
            ->orderBy('snapshot_course_display_name')
            ->orderBy('student_profile_id')
            ->paginate(20);

        return response()->json([
            'data' => $items->getCollection()->map(fn (StudentEnrollment $enrollment) => [
                'id' => $enrollment->student_profile_id,
                'name' => $enrollment->studentProfile?->registered_name_resolved,
                'course' => $enrollment->courseSection?->display_name
                    ?: $enrollment->snapshot_course_display_name,
            ])->values(),
            'meta' => $this->paginationMeta($items),
        ]);
    }

    public function staff(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'type' => ['nullable', Rule::in(['cde', 'joint_committee', 'joint-committee'])],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $requestedType = $payload['type'] ?? null;
        $type = $requestedType
            ? $this->routeType($requestedType)
            : ($this->accessService->canView($request->user(), 'cde') ? 'cde' : 'joint_committee');
        abort_unless(
            in_array($type, ['cde', 'joint_committee'], true)
            && $this->accessService->canView($request->user(), $type),
            403,
        );
        $search = trim((string) ($payload['search'] ?? ''));

        $items = Staff::query()
            ->select(['id', 'full_name', 'cargo_id'])
            ->where('active', true)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('full_name', 'like', "%{$search}%")
                        ->orWhereHas('cargo', fn ($cargoQuery) => $cargoQuery
                            ->where('name', 'like', "%{$search}%"));
                });
            })
            ->with('cargo:id,name')
            ->orderBy('full_name')
            ->paginate(20);

        return response()->json([
            'data' => $items->getCollection()->map(fn (Staff $staff) => [
                'id' => $staff->id,
                'name' => $staff->full_name,
                'position' => $staff->cargo?->name,
            ])->values(),
            'meta' => $this->paginationMeta($items),
        ]);
    }

    public function storeRole(SaveSiteOrganizationRoleRequest $request): JsonResponse
    {
        $role = SiteOrganizationRole::query()->create(array_merge($request->validated(), [
            'sort_order' => (int) ($request->validated('sort_order') ?? 0),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Cargo creado correctamente.',
            'data' => $role,
        ], 201);
    }

    public function updateRole(
        SaveSiteOrganizationRoleRequest $request,
        SiteOrganizationRole $siteOrganizationRole,
    ): JsonResponse {
        $siteOrganizationRole->update(array_merge($request->validated(), [
            'sort_order' => (int) ($request->validated('sort_order') ?? 0),
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Cargo actualizado correctamente.',
            'data' => $siteOrganizationRole->fresh(),
        ]);
    }

    private function validatedType(Request $request): string
    {
        $payload = $request->validate([
            'type' => ['required', Rule::in(['cgpa', 'cde', 'joint_committee', 'joint-committee'])],
        ]);

        return $this->routeType($payload['type']);
    }

    private function routeType(string $type): string
    {
        $type = $this->accessService->normalizeType($type);
        abort_unless($this->accessService->isSupportedType($type), 404);

        return $type;
    }

    private function savedMessage(string $type, bool $created): string
    {
        $name = match ($type) {
            'cgpa' => 'CGPA',
            'cde' => 'CDE',
            default => 'Comité Paritario',
        };

        return "{$name} ".($created ? 'creado' : 'actualizado').' correctamente.';
    }

    private function paginationMeta($items): array
    {
        return [
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
        ];
    }
}
