<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Models\Convivencia\ConvivenciaComplaint;
use App\Services\Convivencia\ConvivenciaComplaintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConvivenciaPublicComplaintController extends Controller
{
    public function __construct(
        private readonly ConvivenciaComplaintService $complaintService,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'affected_student_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'situation_type_item_id' => ['nullable', 'integer', 'exists:convivencia_catalog_items,id'],
            'complainant_name' => ['nullable', 'string', 'max:191'],
            'complainant_type' => ['required', Rule::in(array_column(ConvivenciaComplaint::COMPLAINANT_TYPE_OPTIONS, 'value'))],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'contact_phone' => ['nullable', 'string', 'max:80'],
            'situation_type_label' => ['nullable', 'string', 'max:160'],
            'place' => ['nullable', 'string', 'max:160'],
            'received_at' => ['nullable', 'date'],
            'happened_at' => ['nullable', 'date'],
            'report_text' => ['required', 'string', 'min:10'],
            'involved_snapshot' => ['nullable', 'array'],
            'involved_snapshot.*.person_type' => ['nullable', 'string', 'max:60'],
            'involved_snapshot.*.role_type' => ['nullable', 'string', 'max:60'],
            'involved_snapshot.*.full_name' => ['required_with:involved_snapshot', 'string', 'max:191'],
            'involved_snapshot.*.identifier' => ['nullable', 'string', 'max:80'],
            'involved_snapshot.*.contact_reference' => ['nullable', 'string', 'max:191'],
            'truth_declaration_accepted' => ['sometimes', 'boolean'],
        ]);

        $complaint = $this->complaintService->store(array_merge($payload, [
            'status' => 'recibida',
            'is_anonymous' => ($payload['complainant_type'] ?? null) === 'anonimo',
            'is_sensitive' => true,
        ]));

        return response()->json([
            'message' => 'Denuncia recibida correctamente.',
            'folio' => $complaint->folio,
            'data' => [
                'folio' => $complaint->folio,
                'status' => $complaint->status,
                'received_at' => $complaint->received_at,
            ],
        ], 201);
    }

    public function show(string $folio): JsonResponse
    {
        $complaint = ConvivenciaComplaint::query()
            ->with(['case:id,folio,status', 'protocolActivations:id,complaint_id,status,current_stage_name'])
            ->where('folio', $folio)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'folio' => $complaint->folio,
                'status' => $complaint->status,
                'received_at' => $complaint->received_at,
                'happened_at' => $complaint->happened_at,
                'situation_type_label' => $complaint->situation_type_label,
                'case' => $complaint->case ? [
                    'folio' => $complaint->case->folio,
                    'status' => $complaint->case->status,
                ] : null,
                'protocols' => $complaint->protocolActivations
                    ->map(fn ($activation) => [
                        'status' => $activation->status,
                        'current_stage_name' => $activation->current_stage_name,
                    ])
                    ->values(),
            ],
        ]);
    }
}
