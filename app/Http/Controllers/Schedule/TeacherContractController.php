<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Schedule\TeacherContract;
use App\Services\Schedule\TeacherContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeacherContractController extends Controller
{
    public function __construct(private readonly TeacherContractService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => TeacherContract::query()
                ->with(['teacher:id,full_name,institutional_email,contract_hours', 'academicYear:id,name,year,is_active'])
                ->when($request->integer('academic_year_id'), fn ($query, $yearId) => $query->where('academic_year_id', $yearId))
                ->when($request->integer('teacher_id'), fn ($query, $teacherId) => $query->where('staff_id', $teacherId))
                ->orderByDesc('active')
                ->latest('id')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Contrato docente creado correctamente.',
            'data' => $this->service->createOrUpdate($this->validatedPayload($request)),
        ], 201);
    }

    public function update(Request $request, TeacherContract $teacherContract): JsonResponse
    {
        return response()->json([
            'message' => 'Contrato docente actualizado correctamente.',
            'data' => $this->service->createOrUpdate($this->validatedPayload($request, $teacherContract), $teacherContract),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ?TeacherContract $contract = null): array
    {
        return $request->validate([
            'staff_id' => [$contract ? 'sometimes' : 'required', 'integer', Rule::exists('staff', 'id')],
            'academic_year_id' => [$contract ? 'sometimes' : 'required', 'integer', Rule::exists(AcademicYear::class, 'id')],
            'weekly_contract_hours' => [$contract ? 'sometimes' : 'required', 'numeric', 'min:0', 'max:80'],
            'hour_type' => ['sometimes', Rule::in(['chronological', 'pedagogical'])],
            'lective_percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'non_lective_percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }
}
