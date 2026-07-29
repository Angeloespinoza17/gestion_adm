<?php

namespace Tests\Feature\CentroApuntes;

use App\Models\CentroApuntes\PanolInsumo;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PanolInsumoIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'user_type' => 'staff',
            'active' => true,
        ]);
        $role = Role::query()->create([
            'name' => 'Super administrador',
            'slug' => 'super_admin',
            'active' => true,
        ]);
        $this->user->roles()->attach($role);

        Sanctum::actingAs($this->user);
    }

    public function test_inventory_index_returns_filtered_operational_summary(): void
    {
        $this->createSupply('Papel carta', 'papel', 12, 4);
        $this->createSupply('Tinta negra', 'tinta', 2, 5, ['status' => 'stock_bajo']);
        $this->createSupply('Tóner color', 'toner', 0, 3, ['status' => 'agotado']);
        $this->createSupply('Papel fotográfico', 'papel', 8, 2, [
            'expires_at' => now()->addDays(10)->toDateString(),
        ]);
        $this->createSupply('Papel vencido', 'papel', 7, 2, [
            'expires_at' => now()->subDay()->toDateString(),
            'status' => 'vencido',
        ]);

        $this->getJson('/api/centro-apuntes/insumos?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('total', 5)
            ->assertJsonPath('summary.total', 5)
            ->assertJsonPath('summary.available', 2)
            ->assertJsonPath('summary.low_stock', 1)
            ->assertJsonPath('summary.out_of_stock', 1)
            ->assertJsonPath('summary.expiring_soon', 1);

        $this->getJson('/api/centro-apuntes/insumos?critical_only=1')
            ->assertOk()
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.low_stock', 1)
            ->assertJsonPath('summary.out_of_stock', 1);
    }

    public function test_inventory_export_payload_contains_all_filtered_rows(): void
    {
        $this->createSupply('Papel carta', 'papel', 12, 4);
        $this->createSupply('Papel oficio', 'papel', 9, 3);
        $this->createSupply('Tinta negra', 'tinta', 5, 2);

        $this->getJson('/api/centro-apuntes/insumos?export=1&category=papel')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('total', 2)
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('truncated', false)
            ->assertJsonPath('data.0.category', 'papel')
            ->assertJsonPath('data.1.category', 'papel');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createSupply(
        string $name,
        string $category,
        float $currentStock,
        float $minimumStock,
        array $overrides = [],
    ): PanolInsumo {
        return PanolInsumo::query()->create(array_merge([
            'name' => $name,
            'category' => $category,
            'unit_of_measure' => 'unidad',
            'current_stock' => $currentStock,
            'minimum_stock' => $minimumStock,
            'maximum_stock' => 20,
            'status' => 'disponible',
            'active' => true,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ], $overrides));
    }
}
