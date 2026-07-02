<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Models\RiskPrevention\RiskPreventionDocument;
use App\Models\RiskPrevention\RiskPreventionEppDelivery;
use App\Models\RiskPrevention\RiskPreventionEppItem;
use App\Models\RiskPrevention\RiskPreventionEmergencyPlan;
use App\Models\RiskPrevention\RiskPreventionFireExtinguisher;
use App\Models\RiskPrevention\RiskPreventionTrainingParticipant;
use App\Models\Staff;
use App\Services\RiskPrevention\RiskPreventionAccessService;
use Illuminate\Http\JsonResponse;

class RiskPreventionCatalogController extends Controller
{
    public function __construct(
        private readonly RiskPreventionAccessService $accessService,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        abort_unless($this->accessService->canView(request()->user()), 403);

        return response()->json([
            'extinguisher_types' => $this->mergeDistinct(
                ['PQS', 'CO2', 'Agua presurizada', 'Espuma AFFF', 'Clase K'],
                RiskPreventionFireExtinguisher::query()->pluck('extinguisher_type')->all(),
            ),
            'accident_types' => [
                ['value' => 'student', 'label' => 'Estudiante'],
                ['value' => 'staff', 'label' => 'Funcionario'],
                ['value' => 'visit', 'label' => 'Visita'],
            ],
            'case_statuses' => [
                ['value' => 'abierto', 'label' => 'Abierto'],
                ['value' => 'en_seguimiento', 'label' => 'En seguimiento'],
                ['value' => 'cerrado', 'label' => 'Cerrado'],
            ],
            'emergency_record_types' => [
                ['value' => 'plan_evacuacion', 'label' => 'Plan de evacuación'],
                ['value' => 'protocolo', 'label' => 'Protocolo'],
            ],
            'emergency_types' => $this->mergeDistinct(
                ['Incendio', 'Sismo', 'Fuga de gas', 'Accidente químico', 'Amenaza externa'],
                RiskPreventionEmergencyPlan::query()->pluck('emergency_type')->all(),
            ),
            'epp_types' => $this->mergeDistinct(
                ['Casco', 'Guantes', 'Protección visual', 'Calzado de seguridad', 'Chaleco reflectante'],
                RiskPreventionEppItem::query()->pluck('epp_type')->all(),
            ),
            'epp_items' => RiskPreventionEppItem::query()->orderBy('name')->get(['id', 'name', 'epp_type', 'stock', 'minimum_stock', 'unit']),
            'training_types' => [
                ['value' => 'induccion', 'label' => 'Inducción'],
                ['value' => 'actualizacion', 'label' => 'Actualización'],
                ['value' => 'obligatoria', 'label' => 'Obligatoria'],
            ],
            'training_modalities' => $this->mergeDistinct(
                ['Presencial', 'Online', 'Mixta'],
                [],
            ),
            'training_compliance_statuses' => [
                ['value' => 'cumplido', 'label' => 'Cumplido'],
                ['value' => 'pendiente', 'label' => 'Pendiente'],
                ['value' => 'no_asiste', 'label' => 'No asiste'],
            ],
            'document_types' => [
                ['value' => 'protocolo', 'label' => 'Protocolo'],
                ['value' => 'reglamento', 'label' => 'Reglamento'],
                ['value' => 'instructivo', 'label' => 'Instructivo'],
                ['value' => 'informe', 'label' => 'Informe'],
            ],
            'document_statuses' => [
                ['value' => 'vigente', 'label' => 'Vigente'],
                ['value' => 'por_vencer', 'label' => 'Por vencer'],
                ['value' => 'vencido', 'label' => 'Vencido'],
                ['value' => 'archivado', 'label' => 'Archivado'],
            ],
            'employees' => $this->employeeNames(),
            'document_groups' => RiskPreventionDocument::query()
                ->whereNotNull('document_group')
                ->distinct()
                ->orderBy('document_group')
                ->pluck('document_group')
                ->values(),
        ]);
    }

    /**
     * @param  array<int, string>  $defaults
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    private function mergeDistinct(array $defaults, array $values): array
    {
        return collect($defaults)
            ->merge($values)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => trim((string) $value))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function employeeNames(): array
    {
        return collect()
            ->merge(Staff::query()->where('active', true)->orderBy('full_name')->limit(200)->pluck('full_name'))
            ->merge(RiskPreventionEppDelivery::query()->distinct()->pluck('employee_name'))
            ->merge(RiskPreventionTrainingParticipant::query()->distinct()->pluck('employee_name'))
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => trim((string) $value))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
