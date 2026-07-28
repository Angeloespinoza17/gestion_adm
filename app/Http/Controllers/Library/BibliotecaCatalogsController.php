<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\Library\BibliotecaCategoria;
use App\Models\Library\BibliotecaEjemplar;
use App\Models\Library\BibliotecaEspacio;
use App\Models\Library\BibliotecaObra;
use App\Models\Library\BibliotecaPlanLector;
use App\Models\Library\BibliotecaPrestamo;
use App\Models\Library\BibliotecaReserva;
use App\Models\Library\BibliotecaSubcategoria;
use App\Models\Library\BibliotecaUbicacion;
use App\Models\Library\BibliotecaUsoEspacio;
use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Library\BibliotecaAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BibliotecaCatalogsController extends Controller
{
    public function __construct(
        private readonly BibliotecaAccessService $accessService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $students = StudentProfile::query()
            ->with(['enrollments.courseSection.educationLevel'])
            ->orderBy('first_name')
            ->limit(500)
            ->get()
            ->map(function (StudentProfile $student) {
                $enrollment = $student->preferredEnrollment();

                return [
                    'id' => $student->id,
                    'name' => $student->registered_name_resolved,
                    'rut' => $student->rut,
                    'course' => $enrollment?->snapshot_course_display_name,
                    'course_section_id' => $enrollment?->course_section_id,
                    'level' => $enrollment?->snapshot_level_name ?? $enrollment?->courseSection?->educationLevel?->name,
                    'guardian_name' => $student->guardian_name,
                    'guardian_rut' => $student->guardian_rut,
                    'guardian_email' => $student->guardian_email,
                    'guardian_relationship' => $student->guardian_relationship,
                ];
            });

        $guardians = $students
            ->filter(fn (array $student) => filled($student['guardian_name']))
            ->map(fn (array $student) => [
                'student_profile_id' => $student['id'],
                'name' => $student['guardian_name'],
                'rut' => $student['guardian_rut'],
                'email' => $student['guardian_email'],
                'relationship' => $student['guardian_relationship'],
                'student_name' => $student['name'],
                'course' => $student['course'],
            ])
            ->values();

        return response()->json([
            'material_types' => $this->toOptions(BibliotecaObra::MATERIAL_TYPES),
            'obra_statuses' => $this->toOptions(BibliotecaObra::STATUS_OPTIONS),
            'ejemplar_origins' => $this->toOptions(BibliotecaEjemplar::ORIGIN_OPTIONS),
            'ejemplar_states' => $this->toOptions(BibliotecaEjemplar::STATE_OPTIONS),
            'ejemplar_availability_statuses' => $this->toOptions(BibliotecaEjemplar::AVAILABILITY_OPTIONS),
            'loan_statuses' => $this->toOptions(BibliotecaPrestamo::STATUS_OPTIONS),
            'borrower_types' => $this->borrowerTypeOptions(),
            'reservation_statuses' => $this->toOptions(BibliotecaReserva::STATUS_OPTIONS),
            'reservation_requester_types' => $this->reservationRequesterTypeOptions(),
            'plan_statuses' => $this->toOptions(BibliotecaPlanLector::STATUS_OPTIONS),
            'space_activity_types' => $this->toOptions(BibliotecaUsoEspacio::ACTIVITY_TYPES),
            'space_statuses' => $this->toOptions(BibliotecaUsoEspacio::STATUS_OPTIONS),
            'academic_years' => AcademicYear::query()->ordered()->get(['id', 'name', 'year', 'is_active']),
            'education_levels' => EducationLevel::query()->orderBy('order')->get(['id', 'name', 'type', 'order']),
            'courses' => CourseSection::query()->orderBy('display_name')->get(['id', 'academic_year_id', 'education_level_id', 'display_name']),
            'works' => BibliotecaObra::query()->orderBy('title')->get(['id', 'title', 'main_author', 'publisher', 'isbn', 'internal_code', 'material_type', 'biblioteca_categoria_id', 'biblioteca_subcategoria_id', 'category', 'subcategory', 'available_copies']),
            'exemplars' => BibliotecaEjemplar::query()
                ->with(['obra:id,title,main_author,isbn,material_type,category', 'ubicacion:id,name,code'])
                ->orderBy('code')
                ->get()
                ->map(fn (BibliotecaEjemplar $ejemplar) => [
                    'id' => $ejemplar->id,
                    'biblioteca_obra_id' => $ejemplar->biblioteca_obra_id,
                    'code' => $ejemplar->code,
                    'availability_status' => $ejemplar->availability_status,
                    'title' => $ejemplar->obra?->title,
                    'main_author' => $ejemplar->obra?->main_author,
                    'isbn' => $ejemplar->obra?->isbn,
                    'material_type' => $ejemplar->obra?->material_type,
                    'category' => $ejemplar->obra?->category,
                    'location' => $ejemplar->ubicacion?->name ?? $ejemplar->physical_location,
                    'label' => sprintf('%s · %s', $ejemplar->code, $ejemplar->obra?->title ?? 'Sin título'),
                ]),
            'students' => $students,
            'guardians' => $guardians,
            'staff' => Staff::query()->with('cargo:id,name,slug')->orderBy('full_name')->get(['id', 'full_name', 'rut', 'cargo_id']),
            'users' => User::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'email', 'user_type']),
            'spaces' => BibliotecaEspacio::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'capacity']),
            'categories' => BibliotecaCategoria::query()->where('active', true)->withCount('obras')->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug', 'code', 'color']),
            'locations' => BibliotecaUbicacion::query()->where('active', true)->with('parent:id,name,code')->orderBy('sort_order')->orderBy('name')->get(['id', 'parent_id', 'type', 'name', 'code', 'audience_type']),
            'subcategories' => BibliotecaSubcategoria::query()
                ->where('active', true)
                ->whereHas('categoria', fn ($query) => $query->where('active', true))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'biblioteca_categoria_id', 'name', 'slug']),
            'genres' => BibliotecaObra::query()->whereNotNull('genre')->distinct()->orderBy('genre')->pluck('genre'),
            'languages' => BibliotecaObra::query()->whereNotNull('language')->distinct()->orderBy('language')->pluck('language'),
            'legacy_locations' => BibliotecaEjemplar::query()->whereNotNull('physical_location')->distinct()->orderBy('physical_location')->pluck('physical_location'),
            'capabilities' => [
                'manage_catalog' => $this->accessService->canManageCatalog($request->user()),
                'manage_inventory' => $this->accessService->canManageInventory($request->user()),
                'manage_loans' => $this->accessService->canRegisterLoans($request->user()),
                'manage_spaces' => $this->accessService->canManageSpaces($request->user()),
                'manage_categories' => $this->accessService->canManageCategories($request->user()),
                'manage_storage' => $this->accessService->canManageStorage($request->user()),
                'manage_textbooks' => $this->accessService->canManageTextbooks($request->user()),
                'manage_materials' => $this->accessService->canManageMaterials($request->user()),
                'manage_passes' => $this->accessService->canManagePasses($request->user()),
                'view_statistics' => $this->accessService->canViewStatistics($request->user()),
                'export' => $this->accessService->canExport($request->user()),
            ],
        ]);
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    private function borrowerTypeOptions(): array
    {
        $labels = [
            'student' => 'Estudiante',
            'staff' => 'Funcionario/a',
            'teacher' => 'Docente',
            'guardian' => 'Apoderado/a',
            'course' => 'Curso',
        ];

        return collect(BibliotecaPrestamo::BORROWER_TYPES)
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => $labels[$value] ?? str($value)->replace('_', ' ')->title()->toString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    private function reservationRequesterTypeOptions(): array
    {
        $labels = [
            'student' => 'Estudiante',
            'staff' => 'Funcionario/a',
            'teacher' => 'Docente',
            'guardian' => 'Apoderado/a',
            'course' => 'Curso',
        ];

        return collect(BibliotecaReserva::REQUESTER_TYPES)
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => $labels[$value] ?? str($value)->replace('_', ' ')->title()->toString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, array{value:string,label:string}>
     */
    private function toOptions(array $values): array
    {
        return collect($values)
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => str($value)->replace('_', ' ')->title()->toString(),
            ])
            ->values()
            ->all();
    }
}
