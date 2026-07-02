<?php

namespace App\Http\Controllers\Pme;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pme\ImportPmeStudentSepRequest;
use App\Http\Requests\Pme\SavePmeStudentSepRequest;
use App\Models\Pme\PmeStudentSepClassification;
use App\Services\Pme\PmeAccessService;
use App\Services\Pme\PmeStudentSepImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PmeStudentSepController extends Controller
{
    public function __construct(
        private readonly PmeAccessService $accessService,
        private readonly PmeStudentSepImportService $importService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewStudentClassifications($request->user()), 403);

        $query = PmeStudentSepClassification::query()
            ->with(['student:id,first_name,last_name,registered_name,rut', 'courseSection:id,display_name', 'academicYear:id,year'])
            ->orderByDesc('academic_year_id')
            ->orderBy('classification');

        $query->when($request->query('academic_year_id'), fn ($builder, $year) => $builder->where('academic_year_id', $year));
        $query->when($request->query('course_section_id'), fn ($builder, $course) => $builder->where('course_section_id', $course));
        $query->when($request->query('classification'), fn ($builder, $classification) => $builder->where('classification', $classification));
        $query->when($request->query('state'), fn ($builder, $state) => $builder->where('state', $state));
        $query->when($request->query('search'), function ($builder, $search) {
            $builder->whereHas('student', function ($nested) use ($search) {
                $nested->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('registered_name', 'like', "%{$search}%")
                    ->orWhere('rut', 'like', "%{$search}%");
            });
        });

        return response()->json($query->paginate(20));
    }

    public function store(SavePmeStudentSepRequest $request): JsonResponse
    {
        abort_unless($this->accessService->canLoadStudents($request->user()), 403);

        $payload = $request->validated();
        if ($request->hasFile('document')) {
            $payload['supporting_document_path'] = $request->file('document')->store('pme-sep/student-sep', 'public');
            $payload['supporting_document_name'] = $request->file('document')->getClientOriginalName();
        }

        $record = PmeStudentSepClassification::query()->create(array_merge($payload, [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Clasificación SEP registrada correctamente.',
            'data' => $record->fresh(['student', 'courseSection', 'academicYear']),
        ], 201);
    }

    public function update(SavePmeStudentSepRequest $request, PmeStudentSepClassification $studentSep): JsonResponse
    {
        abort_unless($this->accessService->canLoadStudents($request->user()), 403);

        $payload = $request->validated();
        if ($request->hasFile('document')) {
            if ($studentSep->supporting_document_path) {
                Storage::disk('public')->delete($studentSep->supporting_document_path);
            }
            $payload['supporting_document_path'] = $request->file('document')->store('pme-sep/student-sep', 'public');
            $payload['supporting_document_name'] = $request->file('document')->getClientOriginalName();
        }

        $studentSep->update(array_merge($payload, [
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Clasificación SEP actualizada correctamente.',
            'data' => $studentSep->fresh(['student', 'courseSection', 'academicYear']),
        ]);
    }

    public function import(ImportPmeStudentSepRequest $request): JsonResponse
    {
        abort_unless($this->accessService->canLoadStudents($request->user()), 403);

        $result = $this->importService->import(
            $request->file('file'),
            (int) $request->validated('academic_year_id'),
            $request->user(),
            $request->validated('source'),
        );

        return response()->json([
            'message' => 'Carga masiva SEP procesada correctamente.',
            'data' => $result,
        ]);
    }
}
