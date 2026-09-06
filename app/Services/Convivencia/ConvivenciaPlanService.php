<?php

namespace App\Services\Convivencia;

use App\Models\AcademicYear;
use App\Models\Convivencia\ConvivenciaPlan;
use App\Models\Convivencia\ConvivenciaPlanAction;
use App\Models\Convivencia\ConvivenciaPlanVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConvivenciaPlanService
{
    public function __construct(
        private readonly ConvivenciaSupportService $supportService,
    ) {}

    public function store(array $payload, User $user): ConvivenciaPlan
    {
        return DB::transaction(function () use ($payload, $user) {
            if (array_key_exists('actions', $payload)) {
                $this->assertPayloadWeightAllocation((array) ($payload['actions'] ?? []));
            }
            $plan = new ConvivenciaPlan;
            $this->fillPlan($plan, $payload, $user, true);
            $plan->save();

            if (array_key_exists('actions', $payload)) {
                $this->supportService->syncPlanActions($plan, (array) ($payload['actions'] ?? []));
            }
            $this->recalculatePlanProgress($plan);
            $this->captureVersion($plan, $user, $payload['change_summary'] ?? 'Versión inicial del plan.', false);
            $this->supportService->logStatus($plan, null, $plan->status, $user, 'Plan creado.', 'created');

            return $this->loadPlan($plan, $user);
        });
    }

    public function update(ConvivenciaPlan $plan, array $payload, User $user): ConvivenciaPlan
    {
        return DB::transaction(function () use ($plan, $payload, $user) {
            $plan = ConvivenciaPlan::query()->lockForUpdate()->findOrFail($plan->id);
            if ((int) ($payload['revision'] ?? 0) !== (int) $plan->revision) {
                throw ValidationException::withMessages([
                    'revision' => ['El plan fue actualizado por otra persona. Recarga la versión vigente antes de guardar.'],
                ]);
            }
            $previousStatus = $plan->status;

            if (array_key_exists('actions', $payload)) {
                $this->assertPayloadWeightAllocation((array) ($payload['actions'] ?? []));
            }

            $this->fillPlan($plan, $payload, $user, false);
            $plan->save();

            if (array_key_exists('actions', $payload)) {
                $this->supportService->syncPlanActions($plan, (array) ($payload['actions'] ?? []));
            }
            $this->recalculatePlanProgress($plan);
            $this->captureVersion($plan, $user, $payload['change_summary'] ?? 'Actualización del plan.');

            if ($previousStatus !== $plan->status) {
                $this->supportService->logStatus($plan, $previousStatus, $plan->status, $user);
            }

            return $this->loadPlan($plan, $user);
        });
    }

    private function fillPlan(ConvivenciaPlan $plan, array $payload, User $user, bool $creating): void
    {
        $attributes = array_intersect_key($payload, array_flip([
            'academic_year_id',
            'calendar_year',
            'previous_plan_id',
            'responsible_user_id',
            'responsible_staff_id',
            'name',
            'general_objective',
            'specific_objectives',
            'resources_required',
            'indicators_summary',
            'verification_means_summary',
            'status',
            'advance_percentage',
            'starts_on',
            'ends_on',
            'observations',
            'final_evaluation',
            'source_document_name',
            'source_document_sha256',
            'institutional_protocol',
            'evaluation_indicators',
            'regulatory_linkage_text',
            'regulatory_review_required',
            'is_sensitive',
        ]));

        if (array_key_exists('specific_objectives', $attributes) && $attributes['specific_objectives'] !== null) {
            $attributes['specific_objectives'] = array_values((array) $attributes['specific_objectives']);
        }

        if (array_key_exists('is_sensitive', $attributes)) {
            $attributes['is_sensitive'] = (bool) $attributes['is_sensitive'];
        }

        if ($creating) {
            $attributes += [
                'responsible_user_id' => $user->id,
                'responsible_staff_id' => $user->staff_id,
                'specific_objectives' => [],
                'advance_percentage' => 0,
                'version_number' => 1,
                'revision' => 1,
                'institutional_protocol' => [],
                'evaluation_indicators' => [],
                'regulatory_review_required' => false,
                'is_sensitive' => false,
            ];
        }

        $attributes['updated_by'] = $user->id;
        if (isset($attributes['status'])
            && in_array($attributes['status'], ['vigente', 'en_ejecucion'], true)
            && ! in_array($plan->status, ['vigente', 'en_ejecucion', 'finalizado'], true)) {
            $attributes['approved_at'] = now();
            $attributes['approved_by'] = $user->id;
        }
        $plan->fill($attributes);

        if ($creating) {
            $plan->created_by = $user->id;
        }
    }

    public function captureVersion(ConvivenciaPlan $plan, User $user, string $summary, bool $increment = true): ConvivenciaPlanVersion
    {
        if ($increment) {
            $plan->forceFill([
                'version_number' => ((int) $plan->version_number) + 1,
                'revision' => ((int) $plan->revision) + 1,
                'updated_by' => $user->id,
            ])->save();
        }

        $plan->load(['actions' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')]);

        return $plan->versions()->updateOrCreate(
            ['version_number' => $plan->version_number],
            [
                'change_summary' => trim($summary) ?: 'Actualización del plan.',
                'snapshot' => $this->definitionSnapshot($plan),
                'created_by' => $user->id,
            ],
        );
    }

    public function restoreVersion(ConvivenciaPlan $plan, ConvivenciaPlanVersion $version, User $user, int $revision): ConvivenciaPlan
    {
        return DB::transaction(function () use ($plan, $version, $user, $revision): ConvivenciaPlan {
            $plan = ConvivenciaPlan::query()->lockForUpdate()->findOrFail($plan->id);
            abort_unless((int) $version->plan_id === (int) $plan->id, 404);
            if ($revision !== (int) $plan->revision) {
                throw ValidationException::withMessages([
                    'revision' => ['El plan cambió antes de restaurar. Recarga el historial e inténtalo nuevamente.'],
                ]);
            }

            $snapshot = (array) $version->snapshot;
            $plan->fill(array_intersect_key($snapshot, array_flip([
                'academic_year_id', 'calendar_year', 'previous_plan_id', 'responsible_user_id', 'responsible_staff_id', 'name',
                'general_objective', 'specific_objectives', 'resources_required', 'indicators_summary',
                'verification_means_summary', 'status', 'starts_on', 'ends_on', 'observations', 'final_evaluation',
                'source_document_name', 'source_document_sha256', 'institutional_protocol', 'evaluation_indicators',
                'regulatory_linkage_text', 'regulatory_review_required', 'is_sensitive',
            ])))->save();
            $this->supportService->syncPlanActions($plan, (array) ($snapshot['actions'] ?? []));
            $this->recalculatePlanProgress($plan);
            $this->captureVersion($plan, $user, "Restauración de la versión {$version->version_number}.");
            $this->supportService->logStatus($plan, $plan->status, $plan->status, $user, "Se restauró la definición de la versión {$version->version_number}.", 'version_restored');

            return $this->loadPlan($plan, $user);
        });
    }

    public function recalculatePlanProgress(ConvivenciaPlan $plan): void
    {
        $actions = $plan->actions()->get(['weight_percent', 'advance_percentage']);
        $allocated = round((float) $actions->sum('weight_percent'), 2);
        $progress = $allocated > 0
            ? $actions->sum(fn (ConvivenciaPlanAction $action) => ((float) $action->weight_percent * (float) $action->advance_percentage) / 100)
            : (float) ($actions->avg('advance_percentage') ?? 0);
        $plan->forceFill(['advance_percentage' => round($progress, 2)])->save();
    }

    public function assertActionWeightAvailable(ConvivenciaPlan $plan, float $weight, ?int $exceptId = null): void
    {
        $allocated = (float) $plan->actions()
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->sum('weight_percent');

        if (round($allocated + $weight, 2) > 100) {
            $available = max(0, round(100 - $allocated, 2));
            throw ValidationException::withMessages([
                'weight_percent' => ["La ponderación total de las acciones no puede superar 100%. Queda {$available}% disponible."],
            ]);
        }
    }

    public function cloneToYear(
        ConvivenciaPlan $source,
        int $targetYear,
        ?int $academicYearId,
        User $user,
        int $revision,
    ): ConvivenciaPlan {
        return DB::transaction(function () use ($source, $targetYear, $academicYearId, $user, $revision): ConvivenciaPlan {
            $source = ConvivenciaPlan::query()->lockForUpdate()->findOrFail($source->id);
            if ((int) $source->revision !== $revision) {
                throw ValidationException::withMessages([
                    'revision' => ['El plan de origen cambió. Recárgalo antes de crear el plan del nuevo año.'],
                ]);
            }
            if (ConvivenciaPlan::withTrashed()->where('calendar_year', $targetYear)->exists()) {
                throw ValidationException::withMessages([
                    'target_year' => ['Ya existe un plan activo o archivado para ese año.'],
                ]);
            }
            if ($academicYearId && ! AcademicYear::query()->whereKey($academicYearId)->where('year', $targetYear)->exists()) {
                throw ValidationException::withMessages([
                    'academic_year_id' => ['El año académico seleccionado no corresponde al año del nuevo plan.'],
                ]);
            }

            $yearDelta = $targetYear - (int) $source->calendar_year;
            $source->load(['actions' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')]);
            $name = preg_replace(
                '/\b'.preg_quote((string) $source->calendar_year, '/').'\b/',
                (string) $targetYear,
                $source->name,
            ) ?: "Plan de Gestión de la Convivencia Escolar {$targetYear}";

            return $this->store([
                ...$source->only([
                    'responsible_user_id', 'responsible_staff_id', 'general_objective', 'specific_objectives',
                    'resources_required', 'indicators_summary', 'verification_means_summary', 'institutional_protocol',
                    'evaluation_indicators', 'regulatory_linkage_text', 'is_sensitive',
                ]),
                'calendar_year' => $targetYear,
                'academic_year_id' => $academicYearId,
                'previous_plan_id' => $source->id,
                'name' => $name,
                'status' => 'borrador',
                'starts_on' => $this->shiftDate($source->starts_on, $yearDelta),
                'ends_on' => $this->shiftDate($source->ends_on, $yearDelta),
                'observations' => "Plan {$targetYear} creado a partir de la definición {$source->calendar_year}, versión {$source->version_number}. Requiere revisión y confirmación de fechas.",
                'regulatory_review_required' => filled($source->regulatory_linkage_text),
                'actions' => $source->actions->map(fn (ConvivenciaPlanAction $action) => [
                    ...$action->only([
                        'dimension_item_id', 'responsible_user_id', 'responsible_staff_id', 'responsible_department_id',
                        'action_type', 'title', 'objective', 'description', 'target_audience', 'planned_month',
                        'date_precision', 'sort_order', 'weight_percent', 'dimension_label', 'responsible_label',
                        'required_resources', 'indicator_summary', 'verification_means', 'observations', 'evidence_summary',
                    ]),
                    'starts_on' => $this->shiftDate($action->starts_on, $yearDelta),
                    'ends_on' => $this->shiftDate($action->ends_on, $yearDelta),
                    'status' => 'planificada',
                    'advance_percentage' => 0,
                ])->all(),
                'change_summary' => "Versión inicial {$targetYear}, basada en el plan {$source->calendar_year}.",
            ], $user);
        });
    }

    private function definitionSnapshot(ConvivenciaPlan $plan): array
    {
        return [
            ...$plan->only([
                'academic_year_id', 'calendar_year', 'previous_plan_id', 'responsible_user_id', 'responsible_staff_id', 'name',
                'general_objective', 'specific_objectives', 'resources_required', 'indicators_summary',
                'verification_means_summary', 'status', 'advance_percentage', 'starts_on', 'ends_on',
                'observations', 'final_evaluation', 'source_document_name', 'source_document_sha256',
                'institutional_protocol', 'evaluation_indicators', 'regulatory_linkage_text',
                'regulatory_review_required', 'is_sensitive',
            ]),
            'actions' => $plan->actions->map(fn ($action) => $action->only([
                'id', 'dimension_item_id', 'responsible_user_id', 'responsible_staff_id',
                'responsible_department_id', 'action_type', 'title', 'objective', 'description',
                'target_audience', 'planned_month', 'date_precision', 'sort_order', 'weight_percent', 'dimension_label',
                'responsible_label', 'starts_on', 'ends_on', 'required_resources', 'indicator_summary',
                'verification_means', 'status', 'advance_percentage', 'observations', 'evidence_summary',
            ]))->values()->all(),
        ];
    }

    private function loadPlan(ConvivenciaPlan $plan, User $user): ConvivenciaPlan
    {
        return $plan->fresh([
            'academicYear:id,name,year',
            'previousPlan:id,calendar_year,name,version_number',
            'responsibleUser:id,name',
            'responsibleStaff:id,full_name',
            'actions.dimension:id,name',
            'actions.responsibleUser:id,name',
            'actions.responsibleStaff:id,full_name',
            'actions.responsibleDepartment:id,name',
            'actions.activities:id,plan_action_id,activity_type_item_id,activity_type_label,title,status,starts_at,ends_at,contribution_percent,completion_percent',
            'versions:id,plan_id,version_number,change_summary,created_by,created_at',
            'versions.createdBy:id,name',
            'attachments' => fn ($query) => app(ConvivenciaAccessService::class)
                ->applyAttachmentVisibility($query, $user)
                ->with('uploadedBy:id,name'),
        ]);
    }

    private function assertPayloadWeightAllocation(array $actions): void
    {
        $allocated = round(collect($actions)->sum(fn (array $action) => (float) ($action['weight_percent'] ?? 0)), 2);
        if ($allocated > 100) {
            throw ValidationException::withMessages([
                'actions' => ["La ponderación total de las acciones no puede superar 100% (recibido: {$allocated}%)."],
            ]);
        }
    }

    private function shiftDate(mixed $date, int $years): ?string
    {
        if (! $date) {
            return null;
        }

        return CarbonImmutable::parse($date)->addYearsNoOverflow($years)->toDateString();
    }
}
