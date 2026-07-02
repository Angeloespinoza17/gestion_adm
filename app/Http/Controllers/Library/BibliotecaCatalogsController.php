<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\Library\BibliotecaEjemplar;
use App\Models\Library\BibliotecaEspacio;
use App\Models\Library\BibliotecaObra;
use App\Models\Library\BibliotecaPlanLector;
use App\Models\Library\BibliotecaPrestamo;
use App\Models\Library\BibliotecaReserva;
use App\Models\Library\BibliotecaUsoEspacio;
use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class BibliotecaCatalogsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $students = StudentProfile::query()
            ->with(['enrollments.courseSection'])
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
                ];
            });

        return response()->json([
            'material_types' => $this->toOptions(BibliotecaObra::MATERIAL_TYPES),
            'obra_statuses' => $this->toOptions(BibliotecaObra::STATUS_OPTIONS),
            'ejemplar_origins' => $this->toOptions(BibliotecaEjemplar::ORIGIN_OPTIONS),
            'ejemplar_states' => $this->toOptions(BibliotecaEjemplar::STATE_OPTIONS),
            'ejemplar_availability_statuses' => $this->toOptions(BibliotecaEjemplar::AVAILABILITY_OPTIONS),
            'loan_statuses' => $this->toOptions(BibliotecaPrestamo::STATUS_OPTIONS),
            'borrower_types' => $this->toOptions(BibliotecaPrestamo::BORROWER_TYPES),
            'reservation_statuses' => $this->toOptions(BibliotecaReserva::STATUS_OPTIONS),
            'reservation_requester_types' => $this->toOptions(BibliotecaReserva::REQUESTER_TYPES),
            'plan_statuses' => $this->toOptions(BibliotecaPlanLector::STATUS_OPTIONS),
            'space_activity_types' => $this->toOptions(BibliotecaUsoEspacio::ACTIVITY_TYPES),
            'space_statuses' => $this->toOptions(BibliotecaUsoEspacio::STATUS_OPTIONS),
            'academic_years' => AcademicYear::query()->ordered()->get(['id', 'name', 'year', 'is_active']),
            'courses' => CourseSection::query()->orderBy('display_name')->get(['id', 'academic_year_id', 'display_name']),
            'works' => BibliotecaObra::query()->orderBy('title')->get(['id', 'title', 'internal_code', 'material_type', 'category', 'available_copies']),
            'exemplars' => BibliotecaEjemplar::query()
                ->with('obra:id,title')
                ->orderBy('code')
                ->get()
                ->map(fn (BibliotecaEjemplar $ejemplar) => [
                    'id' => $ejemplar->id,
                    'biblioteca_obra_id' => $ejemplar->biblioteca_obra_id,
                    'code' => $ejemplar->code,
                    'availability_status' => $ejemplar->availability_status,
                    'label' => sprintf('%s · %s', $ejemplar->code, $ejemplar->obra?->title ?? 'Sin título'),
                ]),
            'students' => $students,
            'staff' => Staff::query()->orderBy('full_name')->get(['id', 'full_name']),
            'users' => User::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'email', 'user_type']),
            'spaces' => BibliotecaEspacio::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'capacity']),
            'categories' => BibliotecaObra::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'subcategories' => BibliotecaObra::query()->whereNotNull('subcategory')->distinct()->orderBy('subcategory')->pluck('subcategory'),
            'genres' => BibliotecaObra::query()->whereNotNull('genre')->distinct()->orderBy('genre')->pluck('genre'),
            'languages' => BibliotecaObra::query()->whereNotNull('language')->distinct()->orderBy('language')->pluck('language'),
            'locations' => BibliotecaEjemplar::query()->whereNotNull('physical_location')->distinct()->orderBy('physical_location')->pluck('physical_location'),
        ]);
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
