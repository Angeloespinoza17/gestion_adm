<?php

namespace App\Http\Controllers\Infirmary;

use App\Http\Controllers\Controller;
use App\Http\Requests\Infirmary\SaveInfirmaryMedicationAdministrationRequest;
use App\Http\Requests\Infirmary\SaveInfirmaryMedicationAuthorizationRequest;
use App\Models\Infirmary\InfirmaryMedication;
use App\Models\Infirmary\InfirmaryMedicationAdministration;
use App\Models\Infirmary\InfirmaryMedicationAuthorization;
use App\Models\Infirmary\InfirmaryMedicationMovement;
use App\Services\Infirmary\InfirmaryMedicationStockService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InfirmaryMedicationAuthorizationController extends Controller
{
    public function __construct(
        private readonly InfirmaryMedicationStockService $stockService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InfirmaryMedicationAuthorization::class);
        $this->stockService->refreshDynamicStatuses();

        $search = trim((string) $request->query('search'));
        $studentId = $request->query('student_profile_id');
        $medicationId = $request->query('medication_id');
        $status = trim((string) $request->query('status'));

        $items = InfirmaryMedicationAuthorization::query()
            ->with(['student:id,first_name,last_name,rut', 'medication:id,name,commercial_name,unit', 'createdBy:id,name'])
            ->withCount('administrations')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner
                        ->where('diagnosis', 'like', "%{$search}%")
                        ->orWhere('physician_name', 'like', "%{$search}%")
                        ->orWhereHas('student', fn ($student) => $student
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('rut', 'like', "%{$search}%"))
                        ->orWhereHas('medication', fn ($medication) => $medication
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('commercial_name', 'like', "%{$search}%"));
                });
            })
            ->when($studentId, fn ($query) => $query->where('student_profile_id', $studentId))
            ->when($medicationId, fn ($query) => $query->where('medication_id', $medicationId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('start_date')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($items);
    }

    public function store(SaveInfirmaryMedicationAuthorizationRequest $request): JsonResponse
    {
        $this->authorize('create', InfirmaryMedicationAuthorization::class);

        $authorization = InfirmaryMedicationAuthorization::query()->create(array_merge(
            $request->validated(),
            [
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]
        ));

        $this->stockService->refreshDynamicStatuses();

        return response()->json([
            'message' => 'Autorización médica registrada correctamente.',
            'data' => $authorization->fresh(['student:id,first_name,last_name,rut', 'medication:id,name,commercial_name,unit']),
        ], 201);
    }

    public function show(InfirmaryMedicationAuthorization $authorization): JsonResponse
    {
        $this->authorize('view', $authorization);
        $this->stockService->refreshDynamicStatuses();

        return response()->json([
            'data' => $authorization->load([
                'student:id,first_name,last_name,rut,guardian_name,guardian_phone,guardian_email',
                'medication:id,name,commercial_name,unit,current_stock,status,expires_at',
                'administrations.administeredBy:id,name',
                'documents.uploadedBy:id,name',
                'createdBy:id,name',
                'updatedBy:id,name',
            ]),
        ]);
    }

    public function update(SaveInfirmaryMedicationAuthorizationRequest $request, InfirmaryMedicationAuthorization $authorization): JsonResponse
    {
        $this->authorize('update', $authorization);

        $authorization->update(array_merge(
            $request->validated(),
            ['updated_by' => $request->user()?->id]
        ));

        $this->stockService->refreshDynamicStatuses();

        return response()->json([
            'message' => 'Autorización actualizada correctamente.',
            'data' => $authorization->fresh(['student:id,first_name,last_name,rut', 'medication:id,name,commercial_name,unit']),
        ]);
    }

    public function destroy(InfirmaryMedicationAuthorization $authorization): JsonResponse
    {
        $this->authorize('delete', $authorization);

        foreach ($authorization->documents as $document) {
            if ($document->file_path) {
                Storage::disk('public')->delete($document->file_path);
            }
            $document->delete();
        }

        $authorization->delete();

        return response()->json([
            'message' => 'Autorización eliminada correctamente.',
        ]);
    }

    public function storeAdministration(SaveInfirmaryMedicationAdministrationRequest $request, InfirmaryMedicationAuthorization $authorization): JsonResponse
    {
        $this->authorize('update', $authorization);

        $payload = $request->validated();
        $medication = InfirmaryMedication::query()->findOrFail($payload['medication_id'] ?? $authorization->medication_id);
        $administration = InfirmaryMedicationAdministration::query()->create([
            'authorization_id' => $authorization->id,
            'attention_id' => $payload['attention_id'] ?? null,
            'medication_id' => $medication->id,
            'student_profile_id' => $payload['student_profile_id'] ?? $authorization->student_profile_id,
            'administered_at' => Carbon::parse($payload['administered_at'])->format('Y-m-d H:i:s'),
            'administered_by_user_id' => $payload['administered_by_user_id'] ?? $request->user()?->id,
            'quantity_administered' => $payload['quantity_administered'],
            'schedule_reference' => $payload['schedule_reference'] ?? $authorization->schedule_text,
            'source_type' => 'autorizacion',
            'observations' => $payload['observations'] ?? null,
        ]);

        $this->stockService->decreaseStock(
            $medication,
            InfirmaryMedicationMovement::TYPE_ADMINISTRACION,
            (float) $payload['quantity_administered'],
            $request->user(),
            'Administración de medicamento autorizada',
            null,
            $administration,
            Carbon::parse($payload['administered_at']),
        );

        return response()->json([
            'message' => 'Administración registrada correctamente.',
            'data' => $administration->load(['medication:id,name,commercial_name,unit', 'administeredBy:id,name']),
            'authorization' => $authorization->fresh(['administrations.administeredBy:id,name']),
        ], 201);
    }
}
