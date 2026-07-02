<?php

namespace App\Http\Controllers\Infirmary;

use App\Http\Controllers\Controller;
use App\Http\Requests\Infirmary\SaveInfirmaryAccidentRequest;
use App\Models\Infirmary\InfirmaryAccident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InfirmaryAccidentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InfirmaryAccident::class);

        $search = trim((string) $request->query('search'));
        $studentId = $request->query('student_profile_id');
        $type = trim((string) $request->query('accident_type'));
        $status = trim((string) $request->query('case_status'));
        $from = trim((string) $request->query('from'));
        $to = trim((string) $request->query('to'));

        return response()->json(
            InfirmaryAccident::query()
                ->with(['student:id,first_name,last_name,rut', 'courseSection:id,display_name', 'dependency:id,name', 'presentStaff:id,full_name'])
                ->withCount('documents')
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($inner) use ($search) {
                        $inner
                            ->where('accident_type', 'like', "%{$search}%")
                            ->orWhere('place', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhereHas('student', fn ($student) => $student
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('rut', 'like', "%{$search}%"));
                    });
                })
                ->when($studentId, fn ($query) => $query->where('student_profile_id', $studentId))
                ->when($type !== '', fn ($query) => $query->where('accident_type', $type))
                ->when($status !== '', fn ($query) => $query->where('case_status', $status))
                ->when($from !== '', fn ($query) => $query->whereDate('occurred_at', '>=', $from))
                ->when($to !== '', fn ($query) => $query->whereDate('occurred_at', '<=', $to))
                ->latest('occurred_at')
                ->paginate((int) $request->query('per_page', 12))
        );
    }

    public function store(SaveInfirmaryAccidentRequest $request): JsonResponse
    {
        $this->authorize('create', InfirmaryAccident::class);

        $accident = InfirmaryAccident::query()->create(array_merge(
            $request->validated(),
            [
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]
        ));

        return response()->json([
            'message' => 'Accidente escolar registrado correctamente.',
            'data' => $accident->fresh(['student:id,first_name,last_name,rut', 'courseSection:id,display_name', 'dependency:id,name']),
        ], 201);
    }

    public function show(InfirmaryAccident $accident): JsonResponse
    {
        $this->authorize('view', $accident);

        return response()->json([
            'data' => $accident->load([
                'student:id,first_name,last_name,rut,guardian_name,guardian_phone,guardian_email',
                'courseSection:id,display_name',
                'dependency:id,code,name,location,floor_sector',
                'presentStaff:id,full_name',
                'documents.uploadedBy:id,name',
            ]),
        ]);
    }

    public function update(SaveInfirmaryAccidentRequest $request, InfirmaryAccident $accident): JsonResponse
    {
        $this->authorize('update', $accident);

        $accident->update(array_merge(
            $request->validated(),
            ['updated_by' => $request->user()?->id]
        ));

        return response()->json([
            'message' => 'Accidente actualizado correctamente.',
            'data' => $accident->fresh(['student:id,first_name,last_name,rut', 'courseSection:id,display_name', 'dependency:id,name']),
        ]);
    }

    public function destroy(InfirmaryAccident $accident): JsonResponse
    {
        $this->authorize('delete', $accident);

        foreach ($accident->documents as $document) {
            if ($document->file_path) {
                Storage::disk('public')->delete($document->file_path);
            }
            $document->delete();
        }

        $accident->delete();

        return response()->json([
            'message' => 'Accidente eliminado correctamente.',
        ]);
    }
}
