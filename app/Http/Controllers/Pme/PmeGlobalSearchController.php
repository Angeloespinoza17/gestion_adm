<?php

namespace App\Http\Controllers\Pme;

use App\Http\Controllers\Controller;
use App\Models\CourseSection;
use App\Models\Pme\PmeAction;
use App\Models\Pme\PmeEvidence;
use App\Models\Pme\PmeIndicator;
use App\Models\Pme\PmeObjective;
use App\Models\Pme\PmePlan;
use App\Models\Pme\PmeStrategy;
use App\Models\Pme\PmeStudentSepClassification;
use App\Services\Pme\PmeAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PmeGlobalSearchController extends Controller
{
    public function __construct(
        private readonly PmeAccessService $accessService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $search = trim((string) $request->query('search', ''));
        if ($search === '') {
            return response()->json(['data' => []]);
        }

        $like = '%' . $search . '%';

        $results = collect()
            ->merge(
                PmePlan::query()->where('name', 'like', $like)->limit(5)->get()->map(fn (PmePlan $plan) => [
                    'type' => 'plan',
                    'label' => $plan->name,
                    'subtitle' => "PME {$plan->school_year}",
                    'route' => '/pme-sep/configuracion',
                    'id' => $plan->id,
                ])
            )
            ->merge(
                PmeObjective::query()->where('name', 'like', $like)->limit(5)->get()->map(fn (PmeObjective $objective) => [
                    'type' => 'objective',
                    'label' => $objective->name,
                    'subtitle' => 'Objetivo estratégico',
                    'route' => '/pme-sep/objetivos',
                    'id' => $objective->id,
                ])
            )
            ->merge(
                PmeStrategy::query()->where('name', 'like', $like)->limit(5)->get()->map(fn (PmeStrategy $strategy) => [
                    'type' => 'strategy',
                    'label' => $strategy->name,
                    'subtitle' => 'Estrategia',
                    'route' => '/pme-sep/estrategias',
                    'id' => $strategy->id,
                ])
            )
            ->merge(
                PmeIndicator::query()->where('name', 'like', $like)->limit(5)->get()->map(fn (PmeIndicator $indicator) => [
                    'type' => 'indicator',
                    'label' => $indicator->name,
                    'subtitle' => 'Indicador',
                    'route' => '/pme-sep/indicadores',
                    'id' => $indicator->id,
                ])
            )
            ->merge(
                PmeAction::query()->where('name', 'like', $like)->limit(10)->get()->map(fn (PmeAction $action) => [
                    'type' => 'action',
                    'label' => $action->name,
                    'subtitle' => 'Acción PME',
                    'route' => '/pme-sep/acciones',
                    'id' => $action->id,
                ])
            )
            ->merge(
                PmeEvidence::query()->where('name', 'like', $like)->limit(5)->get()->map(fn (PmeEvidence $evidence) => [
                    'type' => 'evidence',
                    'label' => $evidence->name,
                    'subtitle' => 'Evidencia',
                    'route' => '/pme-sep/evidencias',
                    'id' => $evidence->id,
                ])
            )
            ->merge(
                PmeStudentSepClassification::query()
                    ->with(['student:id,first_name,last_name,registered_name', 'courseSection:id,display_name'])
                    ->whereHas('student', fn ($query) => $query->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('registered_name', 'like', $like)->orWhere('rut', 'like', $like))
                    ->limit(10)
                    ->get()
                    ->map(fn (PmeStudentSepClassification $classification) => [
                        'type' => 'student',
                        'label' => $classification->student?->registered_name_resolved ?? $classification->student?->full_name ?? 'Estudiante',
                        'subtitle' => $classification->courseSection?->display_name ?? 'Estudiante SEP',
                        'route' => '/pme-sep/estudiantes',
                        'id' => $classification->id,
                    ])
            )
            ->merge(
                CourseSection::query()->where('display_name', 'like', $like)->limit(5)->get()->map(fn (CourseSection $course) => [
                    'type' => 'course',
                    'label' => $course->display_name,
                    'subtitle' => 'Curso',
                    'route' => '/pme-sep/estudiantes',
                    'id' => $course->id,
                ])
            )
            ->values()
            ->take(25);

        return response()->json(['data' => $results]);
    }
}
