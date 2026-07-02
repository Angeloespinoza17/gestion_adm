<?php

namespace App\Http\Controllers\ApoyoProfesional;

use App\Http\Controllers\Controller;
use App\Models\ApoyoProfesional\ApoyoAtencion;
use App\Services\ApoyoProfesional\ApoyoProfesionalAccessService;
use App\Services\ApoyoProfesional\ApoyoProfesionalStudentContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApoyoProfesionalGlobalSearchController extends Controller
{
    public function __construct(
        private readonly ApoyoProfesionalAccessService $accessService,
        private readonly ApoyoProfesionalStudentContextService $studentContextService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $search = trim((string) $request->query('search'));

        if ($search === '') {
            return response()->json(['data' => []]);
        }

        $students = collect(
            $this->studentContextService->searchPayload($search, null, 8, $request->user())
        )->map(fn (array $item) => [
            'type' => 'student',
            'label' => $item['full_name'],
            'subtitle' => trim(($item['rut'] ?? '') . ' · ' . ($item['course'] ?? '')),
            'route' => '/apoyo-profesional/historial',
            'query' => ['student_id' => $item['id']],
        ]);

        $attentions = $this->accessService->applyAttentionVisibility(
            ApoyoAtencion::query(),
            $request->user(),
        )
            ->where(function ($query) use ($search) {
                $query
                    ->where('student_full_name_snapshot', 'like', "%{$search}%")
                    ->orWhere('student_rut_snapshot', 'like', "%{$search}%")
                    ->orWhere('reason_summary', 'like', "%{$search}%")
                    ->orWhere('professional_role_name', 'like', "%{$search}%")
                    ->orWhere('motive_label', 'like', "%{$search}%");
            })
            ->latest('attended_at')
            ->limit(8)
            ->get()
            ->map(fn (ApoyoAtencion $attention) => [
                'type' => 'attention',
                'label' => $attention->student_full_name_snapshot,
                'subtitle' => trim(($attention->professional_role_name ?: 'Sin profesional') . ' · ' . ($attention->reason_summary ?: 'Sin motivo')),
                'route' => '/apoyo-profesional/atenciones',
                'query' => ['attention_id' => $attention->id],
            ]);

        return response()->json([
            'data' => $students->merge($attentions)->values(),
        ]);
    }
}
