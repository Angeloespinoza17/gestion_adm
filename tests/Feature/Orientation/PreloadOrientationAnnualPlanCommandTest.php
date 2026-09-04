<?php

namespace Tests\Feature\Orientation;

use App\Models\Orientation\OrientationAction;
use App\Models\Orientation\OrientationPlan;
use App\Models\Orientation\OrientationRelatedPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PreloadOrientationAnnualPlanCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_does_not_persist_the_reference_plan(): void
    {
        $this->assertSame(0, Artisan::call('orientation:preload-annual-plan', ['--year' => 2026]));

        $this->assertDatabaseCount('orientation_plans', 0);
        $this->assertDatabaseCount('orientation_actions', 0);
    }

    public function test_command_preloads_the_complete_reference_matrix_idempotently(): void
    {
        $arguments = [
            '--year' => 2026,
            '--apply' => true,
            '--confirm' => 'PRECARGAR-PLAN-ORIENTACION',
        ];

        $this->assertSame(0, Artisan::call('orientation:preload-annual-plan', $arguments));

        $plan = OrientationPlan::query()->where('year', 2026)->firstOrFail();
        $this->assertSame(33, $plan->actions()->count());
        $this->assertSame(1, $plan->relatedPlans()->count());
        $this->assertSame(3, $plan->relatedPlans()->firstOrFail()->actions()->count());

        $this->assertDatabaseHas('orientation_actions', [
            'orientation_plan_id' => $plan->id,
            'title' => 'Plan de Afectividad, Sexualidad y Género',
            'target_levels' => '1° básico a IV medio',
            'start_date' => '2026-04-01',
            'end_date' => '2026-11-30',
        ]);
        $this->assertDatabaseHas('orientation_actions', [
            'orientation_plan_id' => $plan->id,
            'title' => 'Campus Tour UACH',
            'start_date' => null,
            'end_date' => null,
        ]);

        $this->assertSame(0, Artisan::call('orientation:preload-annual-plan', $arguments));
        $this->assertSame(33, OrientationAction::query()->count());
        $this->assertSame(1, OrientationRelatedPlan::query()->count());
        $this->assertDatabaseCount('orientation_action_related_plan', 3);
    }
}
