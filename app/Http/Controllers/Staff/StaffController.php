<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreStaffRequest;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Models\Cargo;
use App\Models\Commune;
use App\Models\Department;
use App\Models\Region;
use App\Models\Staff;
use App\Models\Role;
use App\Models\User;
use App\Support\Rut;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    public function catalogs(): JsonResponse
    {
        return response()->json([
            'cargos' => Cargo::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'departments' => Department::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'color', 'active']),
            'regions' => Region::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'short_name', 'abbreviation', 'iso_code']),
            'communes' => Commune::query()
                ->orderBy('name')
                ->get(['id', 'region_id', 'code', 'name']),
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'staff_id', 'cargo_id', 'active']),
            'roles' => Role::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'statuses' => Staff::STATUS_OPTIONS,
            'contract_types' => Staff::CONTRACT_TYPE_OPTIONS,
            'workdays' => Staff::WORKDAY_OPTIONS,
            'maintenance_roles' => Staff::MAINTENANCE_ROLE_OPTIONS,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $name = trim((string) $request->query('name'));
        $rut = Rut::normalize((string) $request->query('rut'));
        $status = trim((string) $request->query('status'));
        $contractType = trim((string) $request->query('contract_type'));
        $cargoId = $request->query('cargo_id');
        $departmentId = $request->query('department_id');
        $active = $request->query('active');

        $staff = Staff::query()
            ->with([
                'cargo:id,name,slug',
                'user:id,name,email,active,staff_id',
                'departments:id,name,color,active',
                'regionRecord:id,name,short_name',
                'communeRecord:id,name,region_id',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('rut', 'like', "%{$search}%")
                        ->orWhere('institutional_email', 'like', "%{$search}%")
                        ->orWhere('personal_email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('cargo', fn ($cargoQuery) => $cargoQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($name !== '', fn ($query) => $query->where('full_name', 'like', "%{$name}%"))
            ->when($rut, fn ($query) => $query->where('rut', 'like', "%{$rut}%"))
            ->when($cargoId, fn ($query) => $query->where('cargo_id', $cargoId))
            ->when($departmentId, fn ($query) => $query->whereHas('departments', fn ($deptQuery) => $deptQuery->where('departments.id', $departmentId)))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($contractType !== '', fn ($query) => $query->where('contract_type', $contractType));

        if ($active !== null && $active !== '') {
            $staff->where('active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        $items = $staff
            ->orderBy('full_name')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($items);
    }

    public function store(StoreStaffRequest $request): JsonResponse
    {
        $payload = $this->applyLocationSnapshot($request->validated());
        $departmentIds = $payload['department_ids'] ?? [];
        $associatedUserId = $payload['associated_user_id'] ?? null;
        $profilePhoto = $request->file('profile_photo');

        unset($payload['department_ids'], $payload['associated_user_id'], $payload['profile_photo']);

        $payload['created_by'] = $request->user()?->id;
        $payload['updated_by'] = $request->user()?->id;

        $staff = DB::transaction(function () use ($payload, $departmentIds, $associatedUserId, $profilePhoto) {
            $staff = Staff::query()->create($payload);
            $staff->departments()->sync($departmentIds);

            if ($profilePhoto instanceof UploadedFile) {
                $this->storeProfilePhoto($staff, $profilePhoto);
            }

            $this->syncAssociatedUser($staff, $associatedUserId, $payload['cargo_id'] ?? null);

            return $staff;
        });

        return response()->json([
            'message' => 'Funcionario creado correctamente.',
            'data' => $this->loadStaff($staff),
        ], 201);
    }

    public function show(Staff $staff): JsonResponse
    {
        return response()->json([
            'data' => $this->loadStaff($staff),
        ]);
    }

    public function update(UpdateStaffRequest $request, Staff $staff): JsonResponse
    {
        $payload = $this->applyLocationSnapshot($request->validated());
        $departmentIds = $payload['department_ids'] ?? null;
        $hasAssociatedUser = array_key_exists('associated_user_id', $payload);
        $associatedUserId = $payload['associated_user_id'] ?? null;
        $profilePhoto = $request->file('profile_photo');

        unset($payload['department_ids'], $payload['associated_user_id'], $payload['profile_photo']);

        $payload['updated_by'] = $request->user()?->id;

        DB::transaction(function () use ($staff, $payload, $departmentIds, $hasAssociatedUser, $associatedUserId, $profilePhoto) {
            $staff->update($payload);

            if (is_array($departmentIds)) {
                $staff->departments()->sync($departmentIds);
            }

            if ($profilePhoto instanceof UploadedFile) {
                $this->storeProfilePhoto($staff, $profilePhoto);
            }

            if ($hasAssociatedUser) {
                $this->syncAssociatedUser($staff, $associatedUserId, $payload['cargo_id'] ?? $staff->cargo_id);
            }
        });

        return response()->json([
            'message' => 'Funcionario actualizado correctamente.',
            'data' => $this->loadStaff($staff->fresh()),
        ]);
    }

    public function destroy(Staff $staff): JsonResponse
    {
        DB::transaction(function () use ($staff) {
            User::query()->where('staff_id', $staff->id)->update(['staff_id' => null]);
            $staff->delete();
        });

        Storage::disk('public')->deleteDirectory(sprintf('staff/%d', $staff->id));

        return response()->json([
            'message' => 'Funcionario eliminado correctamente.',
        ]);
    }

    public function setActive(Request $request, Staff $staff): JsonResponse
    {
        $payload = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        $data = ['active' => $payload['active']];

        if ($payload['active'] && $staff->status === 'inactivo') {
            $data['status'] = 'activo';
        }

        if (!$payload['active'] && $staff->status === 'activo') {
            $data['status'] = 'inactivo';
        }

        $staff->update($data);

        return response()->json([
            'message' => 'Estado del funcionario actualizado correctamente.',
            'data' => $this->loadStaff($staff->fresh()),
        ]);
    }

    private function syncAssociatedUser(Staff $staff, ?int $userId, ?int $cargoId): void
    {
        $currentUser = User::query()->where('staff_id', $staff->id)->first();

        if ($currentUser && $currentUser->id !== $userId) {
            $currentUser->update(['staff_id' => null]);
        }

        if (!$userId) {
            return;
        }

        $user = User::query()->findOrFail($userId);

        if ($user->staff_id && $user->staff_id !== $staff->id) {
            throw ValidationException::withMessages([
                'associated_user_id' => 'El usuario seleccionado ya está asociado a otro funcionario.',
            ]);
        }

        $user->staff_id = $staff->id;
        $user->user_type = $user->user_type ?: 'staff';

        if ($cargoId) {
            $user->cargo_id = $cargoId;
        }

        $user->save();
    }

    private function storeProfilePhoto(Staff $staff, UploadedFile $photo): void
    {
        if ($staff->profile_photo_path) {
            Storage::disk('public')->delete($staff->profile_photo_path);
        }

        $path = $photo->storePubliclyAs(
            sprintf('staff/%d/profile', $staff->id),
            now()->format('Ymd_His') . '_' . uniqid() . '.' . $photo->getClientOriginalExtension(),
            ['disk' => 'public']
        );

        $staff->update(['profile_photo_path' => $path]);
    }

    private function applyLocationSnapshot(array $payload): array
    {
        if (array_key_exists('region_id', $payload)) {
            $region = $payload['region_id']
                ? Region::query()->find($payload['region_id'])
                : null;

            $payload['region'] = $region?->short_name ?: $region?->name;
        }

        if (array_key_exists('commune_id', $payload)) {
            $commune = $payload['commune_id']
                ? Commune::query()->find($payload['commune_id'])
                : null;

            $payload['commune'] = $commune?->name;
        }

        return $payload;
    }

    private function loadStaff(Staff $staff): Staff
    {
        return $staff->load([
            'cargo:id,name,slug',
            'user:id,name,email,active,staff_id,cargo_id',
            'user.roles:id,name,slug',
            'departments:id,name,color,active',
            'dependencyReservations:id,maintenance_dependency_id,staff_id,department_id,title,starts_at,ends_at,status,estimated_attendees,created_by',
            'dependencyReservations.dependency:id,name,calendar_color',
            'dependencyReservations.department:id,name,color',
            'dependencyReservations.createdBy:id,name',
            'documents:id,staff_id,document_type,file_path,original_name,mime_type,observations,uploaded_by,created_at',
            'documents.uploadedBy:id,name',
            'contracts:id,staff_id,contract_template_id,contract_type,start_date,end_date,position_name,contract_hours,workday,base_salary,status,generated_at,signed_at,exported_word_path,created_at,updated_at',
            'contracts.template:id,name',
            'contracts.departments:id,name,color',
            'permissionWatchers.role:id,name,slug',
            'permissionWatchers.user:id,name,email',
            'createdBy:id,name',
            'updatedBy:id,name',
            'regionRecord:id,name,short_name',
            'communeRecord:id,name,region_id',
        ]);
    }
}
