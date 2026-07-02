<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Convivencia\SaveConvivenciaComplaintRequest;
use App\Models\Convivencia\ConvivenciaComplaint;
use App\Services\Convivencia\ConvivenciaComplaintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConvivenciaComplaintController extends Controller
{
    public function __construct(
        private readonly ConvivenciaComplaintService $complaintService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ConvivenciaComplaint::class);

        $query = app(\App\Services\Convivencia\ConvivenciaAccessService::class)
            ->applyComplaintVisibility(
                ConvivenciaComplaint::query()->with([
                    'affectedStudent:id,first_name,last_name,registered_name,rut',
                    'courseSection:id,display_name',
                    'responsibleUser:id,name',
                    'case:id,folio,status',
                ]),
                $request->user(),
            );

        $search = trim((string) $request->query('search'));
        $query
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner
                        ->where('folio', 'like', "%{$search}%")
                        ->orWhere('complainant_name', 'like', "%{$search}%")
                        ->orWhere('report_text', 'like', "%{$search}%")
                        ->orWhereHas('affectedStudent', function ($studentQuery) use ($search) {
                            $studentQuery
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('registered_name', 'like', "%{$search}%")
                                ->orWhere('rut', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->query('status'), fn ($builder, $value) => $builder->where('status', $value))
            ->when($request->query('complainant_type'), fn ($builder, $value) => $builder->where('complainant_type', $value))
            ->when($request->query('course_section_id'), fn ($builder, $value) => $builder->where('course_section_id', $value))
            ->when($request->query('affected_student_id'), fn ($builder, $value) => $builder->where('affected_student_id', $value))
            ->when($request->query('from'), fn ($builder, $value) => $builder->whereDate('received_at', '>=', $value))
            ->when($request->query('to'), fn ($builder, $value) => $builder->whereDate('received_at', '<=', $value));

        return response()->json($query->latest('received_at')->paginate((int) $request->query('per_page', 12)));
    }

    public function store(SaveConvivenciaComplaintRequest $request): JsonResponse
    {
        $this->authorize('create', ConvivenciaComplaint::class);

        $complaint = $this->complaintService->store($request->validated(), $request->user());

        return response()->json([
            'message' => 'Denuncia registrada correctamente.',
            'data' => $complaint,
        ], 201);
    }

    public function show(ConvivenciaComplaint $complaint): JsonResponse
    {
        $this->authorize('view', $complaint);

        return response()->json([
            'data' => $complaint->load([
                'academicYear:id,name,year',
                'courseSection:id,display_name',
                'affectedStudent:id,first_name,last_name,registered_name,rut,guardian_name,guardian_phone,guardian_email',
                'situationType:id,name',
                'responsibleUser:id,name,email',
                'case:id,folio,status',
                'protocolActivations.protocol:id,name',
                'attachments.uploadedBy:id,name',
                'statusLogs.changedBy:id,name',
            ]),
        ]);
    }

    public function update(SaveConvivenciaComplaintRequest $request, ConvivenciaComplaint $complaint): JsonResponse
    {
        $this->authorize('update', $complaint);

        $updated = $this->complaintService->update($complaint, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Denuncia actualizada correctamente.',
            'data' => $updated,
        ]);
    }

    public function destroy(ConvivenciaComplaint $complaint): JsonResponse
    {
        $this->authorize('delete', $complaint);

        $complaint->delete();

        return response()->json([
            'message' => 'Denuncia archivada correctamente.',
        ]);
    }

    public function convertToCase(Request $request, ConvivenciaComplaint $complaint): JsonResponse
    {
        $this->authorize('update', $complaint);

        $payload = $request->validate([
            'case_type_item_id' => ['nullable', 'integer', 'exists:convivencia_catalog_items,id'],
            'classification_item_id' => ['required', 'integer', 'exists:convivencia_catalog_items,id'],
            'subclassification_item_id' => ['nullable', 'integer', 'exists:convivencia_catalog_items,id'],
            'criticality_item_id' => ['required', 'integer', 'exists:convivencia_catalog_items,id'],
            'responsible_user_id' => ['required', 'integer', 'exists:users,id'],
            'responsible_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'is_sensitive' => ['sometimes', 'boolean'],
            'follow_up_due_at' => ['nullable', 'date'],
        ]);

        $case = $this->complaintService->convertToCase($complaint, $payload, $request->user());

        return response()->json([
            'message' => 'La denuncia fue convertida correctamente en un caso.',
            'data' => $case,
        ]);
    }
}
