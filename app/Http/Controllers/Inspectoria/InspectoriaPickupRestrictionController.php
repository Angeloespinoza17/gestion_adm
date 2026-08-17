<?php

namespace App\Http\Controllers\Inspectoria;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inspectoria\SaveInspectoriaPickupRestrictionRequest;
use App\Models\AcademicYear;
use App\Models\Inspectoria\InspectoriaPickupRestriction;
use App\Models\StudentProfile;
use App\Services\Inspectoria\InspectoriaAccessService;
use App\Support\Rut;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspectoriaPickupRestrictionController extends Controller
{
    public function __construct(private readonly InspectoriaAccessService $access) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->access->can($request->user(), InspectoriaAccessService::RESTRICTIONS), 403);

        $search = trim((string) $request->query('search'));
        $query = InspectoriaPickupRestriction::query()->with([
            'student:id,first_name,last_name,registered_name,rut',
            'courseSection:id,display_name',
            'createdBy:id,name',
        ]);
        $this->access->scopeToAssignedCourses($query, $request->user());

        $query->when($search !== '', function (Builder $query) use ($search) {
            $query->where(function (Builder $inner) use ($search) {
                $inner->where('restricted_person_name', 'like', "%{$search}%")
                    ->orWhere('restricted_person_rut', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhereHas('student', fn (Builder $student) => $student
                        ->where('registered_name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('rut', 'like', "%{$search}%"));
            });
        })->when($request->filled('course_section_id'), fn (Builder $query) => $query->where('course_section_id', $request->query('course_section_id')))
            ->when($request->filled('restriction_type'), fn (Builder $query) => $query->where('restriction_type', $request->query('restriction_type')))
            ->when($request->filled('active'), fn (Builder $query) => $query->where('active', $request->boolean('active')));

        return response()->json($query->latest('active')->latest('starts_on')->latest('id')->paginate((int) $request->query('per_page', 15)));
    }

    public function store(SaveInspectoriaPickupRestrictionRequest $request): JsonResponse
    {
        abort_unless($this->access->can($request->user(), InspectoriaAccessService::RESTRICTIONS), 403);

        $payload = $request->validated();
        $student = StudentProfile::query()->with(['enrollments.courseSection', 'enrollments.academicYear'])->findOrFail($payload['student_profile_id']);
        abort_unless($this->access->canAccessStudent($request->user(), $student->id), 403, 'La alumna no pertenece a un curso asignado a esta inspectora.');
        $enrollment = $this->enrollmentFor($student, $request->user());
        abort_unless($enrollment, 422, 'La alumna no tiene un curso vigente disponible para esta inspectora.');

        $restriction = InspectoriaPickupRestriction::query()->create([
            ...$payload,
            'course_section_id' => $enrollment->course_section_id,
            'restricted_person_rut' => Rut::normalize($payload['restricted_person_rut'] ?? null),
            'created_by_user_id' => $request->user()->id,
            'updated_by_user_id' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Restricción de retiro creada.', 'data' => $this->load($restriction)], 201);
    }

    public function update(SaveInspectoriaPickupRestrictionRequest $request, InspectoriaPickupRestriction $restriction): JsonResponse
    {
        abort_unless($this->access->can($request->user(), InspectoriaAccessService::RESTRICTIONS), 403);
        abort_unless($this->access->canAccessCourse($request->user(), (int) $restriction->course_section_id), 403);

        $payload = $request->validated();
        abort_unless((int) $payload['student_profile_id'] === (int) $restriction->student_profile_id, 422, 'No se puede cambiar la alumna de una restricción existente.');
        $restriction->fill([
            ...$payload,
            'restricted_person_rut' => Rut::normalize($payload['restricted_person_rut'] ?? null),
            'updated_by_user_id' => $request->user()->id,
        ])->save();

        return response()->json(['message' => 'Restricción de retiro actualizada.', 'data' => $this->load($restriction)]);
    }

    public function destroy(Request $request, InspectoriaPickupRestriction $restriction): JsonResponse
    {
        abort_unless($this->access->can($request->user(), InspectoriaAccessService::RESTRICTIONS), 403);
        abort_unless($this->access->canAccessCourse($request->user(), (int) $restriction->course_section_id), 403);

        $restriction->update([
            'active' => false,
            'updated_by_user_id' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Restricción finalizada.', 'data' => $this->load($restriction)]);
    }

    private function enrollmentFor(StudentProfile $student, mixed $user): mixed
    {
        $activeYear = AcademicYear::query()->where('is_active', true)->first();
        $enrollment = $student->preferredEnrollment($activeYear);

        if (! $this->access->isCourseScoped($user)) {
            return $enrollment;
        }

        $courseIds = $this->access->assignedCourseIds($user);

        return $student->enrollments->first(fn ($item) => $courseIds->contains((int) $item->course_section_id)
            && (! $activeYear || (int) $item->academic_year_id === (int) $activeYear->id));
    }

    private function load(InspectoriaPickupRestriction $restriction): InspectoriaPickupRestriction
    {
        return $restriction->fresh([
            'student:id,first_name,last_name,registered_name,rut',
            'courseSection:id,display_name',
            'createdBy:id,name',
        ]);
    }
}
