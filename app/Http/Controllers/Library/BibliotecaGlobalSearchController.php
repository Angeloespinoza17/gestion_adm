<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Models\CourseSection;
use App\Models\Library\BibliotecaEjemplar;
use App\Models\Library\BibliotecaObra;
use App\Models\Staff;
use App\Models\StudentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BibliotecaGlobalSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));

        if ($term === '') {
            return response()->json(['data' => []]);
        }

        $works = BibliotecaObra::query()
            ->where(function ($query) use ($term) {
                $query
                    ->where('title', 'like', "%{$term}%")
                    ->orWhere('main_author', 'like', "%{$term}%")
                    ->orWhere('isbn', 'like', "%{$term}%")
                    ->orWhere('internal_code', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%")
                    ->orWhere('category', 'like', "%{$term}%");
            })
            ->limit(8)
            ->get(['id', 'title', 'main_author', 'isbn', 'internal_code'])
            ->map(fn (BibliotecaObra $obra) => [
                'type' => 'obra',
                'id' => $obra->id,
                'label' => $obra->title,
                'subtitle' => trim("{$obra->main_author} · {$obra->isbn}"),
                'route' => '/biblioteca/catalogo',
                'query' => ['obra' => $obra->id],
            ]);

        $exemplars = BibliotecaEjemplar::query()
            ->with('obra:id,title')
            ->where(function ($query) use ($term) {
                $query
                    ->where('code', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%");
            })
            ->limit(6)
            ->get()
            ->map(fn (BibliotecaEjemplar $ejemplar) => [
                'type' => 'ejemplar',
                'id' => $ejemplar->id,
                'label' => $ejemplar->code,
                'subtitle' => $ejemplar->obra?->title ?? 'Sin obra',
                'route' => '/biblioteca/inventario',
                'query' => ['ejemplar' => $ejemplar->id],
            ]);

        $students = StudentProfile::query()
            ->where(function ($query) use ($term) {
                $query
                    ->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('rut', 'like', "%{$term}%");
            })
            ->limit(6)
            ->get(['id', 'first_name', 'last_name', 'rut'])
            ->map(fn (StudentProfile $student) => [
                'type' => 'student',
                'id' => $student->id,
                'label' => $student->full_name,
                'subtitle' => $student->rut,
                'route' => '/biblioteca/prestamos',
                'query' => ['student' => $student->id],
            ]);

        $staff = Staff::query()
            ->where(function ($query) use ($term) {
                $query
                    ->where('full_name', 'like', "%{$term}%")
                    ->orWhere('rut', 'like', "%{$term}%");
            })
            ->limit(6)
            ->get(['id', 'full_name', 'rut'])
            ->map(fn (Staff $staffMember) => [
                'type' => 'staff',
                'id' => $staffMember->id,
                'label' => $staffMember->full_name,
                'subtitle' => $staffMember->rut,
                'route' => '/biblioteca/prestamos',
                'query' => ['staff' => $staffMember->id],
            ]);

        $courses = CourseSection::query()
            ->where('display_name', 'like', "%{$term}%")
            ->limit(6)
            ->get(['id', 'display_name'])
            ->map(fn (CourseSection $course) => [
                'type' => 'course',
                'id' => $course->id,
                'label' => $course->display_name,
                'subtitle' => 'Curso',
                'route' => '/biblioteca/plan-lector',
                'query' => ['course' => $course->id],
            ]);

        return response()->json([
            'data' => $works
                ->concat($exemplars)
                ->concat($students)
                ->concat($staff)
                ->concat($courses)
                ->take(20)
                ->values(),
        ]);
    }
}
