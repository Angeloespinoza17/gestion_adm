<?php

namespace Database\Seeders;

use App\Models\Convivencia\ConvivenciaCatalogItem;
use App\Models\Convivencia\ConvivenciaPlan;
use App\Models\User;
use App\Services\Convivencia\ConvivenciaPlanService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConvivenciaPlanProgressionSeeder extends Seeder
{
    private const TYPES = [
        ['charla', 'Charla', '#4f63d9', 'bx-conversation'],
        ['intervencion', 'Intervención', '#d06c4f', 'bx-support'],
        ['taller', 'Taller', '#258a70', 'bx-shape-circle'],
        ['reunion', 'Reunión', '#3576d3', 'bx-group'],
        ['capacitacion', 'Capacitación', '#8a55c5', 'bx-chalkboard'],
        ['jornada', 'Jornada', '#b7791f', 'bx-calendar-star'],
        ['campana', 'Campaña', '#d6537a', 'bx-megaphone'],
        ['mediacion', 'Mediación', '#298b9a', 'bx-link-alt'],
        ['acompanamiento', 'Acompañamiento', '#527a44', 'bx-user-voice'],
        ['seguimiento', 'Seguimiento', '#64748b', 'bx-line-chart'],
        ['encuesta', 'Encuesta', '#7164c4', 'bx-list-check'],
        ['evaluacion', 'Evaluación', '#a45d32', 'bx-bar-chart-alt-2'],
        ['difusion', 'Difusión', '#3b7ea1', 'bx-broadcast'],
        ['otro', 'Otra actividad', '#6b7280', 'bx-dots-horizontal-rounded'],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('La precarga de progresión solo está permitida en local o testing.');
        }
        if (app()->environment('local') && config('database.default') !== 'sqlite'
            && (string) config('database.connections.'.config('database.default').'.database') !== 'gestion_adm') {
            throw new \RuntimeException('La base local autorizada para esta precarga es gestion_adm.');
        }

        $actor = User::query()->where('active', true)
            ->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin'))
            ->orderBy('id')->first()
            ?: User::query()->where('active', true)->orderBy('id')->first();
        if (! $actor) {
            throw new \RuntimeException('No existe un usuario activo para registrar la autoría.');
        }

        DB::transaction(function () use ($actor): void {
            foreach (self::TYPES as $index => [$code, $name, $color, $icon]) {
                ConvivenciaCatalogItem::query()->firstOrCreate(
                    ['group' => 'plan_activity_type', 'code' => $code],
                    [
                        'name' => $name,
                        'color' => $color,
                        'metadata' => ['icon' => $icon],
                        'sort_order' => $index + 1,
                        'active' => true,
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                    ],
                );
            }

            $definition = config('convivencia_plan_2026');
            $plan = ConvivenciaPlan::query()
                ->where('calendar_year', 2026)
                ->where('source_document_sha256', $definition['source_document_sha256'])
                ->first();
            if (! $plan || $plan->actions()->count() !== count($definition['actions'])
                || (float) $plan->actions()->sum('weight_percent') !== 0.0) {
                return;
            }

            $expectedTitles = collect($definition['actions'])->pluck('title')->sort()->values()->all();
            $actualTitles = $plan->actions()->pluck('title')->sort()->values()->all();
            if ($expectedTitles !== $actualTitles) {
                return;
            }

            foreach ($definition['actions'] as $action) {
                $plan->actions()->where('title', $action['title'])->update([
                    'weight_percent' => $action['weight'] ?? 0,
                    'updated_at' => now(),
                ]);
            }
            $service = app(ConvivenciaPlanService::class);
            $service->recalculatePlanProgress($plan);
            $service->captureVersion($plan, $actor, 'Se incorporó la ponderación de acciones para calcular el avance total del plan.');
        });
    }
}
