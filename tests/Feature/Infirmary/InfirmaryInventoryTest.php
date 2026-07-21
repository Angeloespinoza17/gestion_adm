<?php

namespace Tests\Feature\Infirmary;

use App\Models\Infirmary\InfirmaryMedication;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InfirmaryInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->create([
            'name' => 'Super administrador',
            'slug' => 'super_admin',
            'active' => true,
        ]);
        $user->roles()->attach($role);

        Sanctum::actingAs($user);
    }

    public function test_it_registers_a_supply_with_audited_initial_stock(): void
    {
        $response = $this->postJson('/api/infirmary/medications', [
            'inventory_type' => 'supply',
            'source_type' => 'school',
            'name' => 'Apósito estéril',
            'presentation' => 'Caja de 20 unidades',
            'unit' => 'unidad',
            'initial_stock' => 20,
            'minimum_stock' => 5,
            'physical_location' => 'Gabinete A',
            'active' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.inventory_type', 'supply')
            ->assertJsonPath('data.source_type', 'school')
            ->assertJsonPath('data.name', 'Apósito estéril');

        $itemId = $response->json('data.id');

        $this->assertDatabaseHas('infirmary_medications', [
            'id' => $itemId,
            'inventory_type' => 'supply',
            'current_stock' => 20,
        ]);
        $this->assertDatabaseHas('infirmary_medication_movements', [
            'medication_id' => $itemId,
            'movement_type' => 'ingreso',
            'quantity' => 20,
            'stock_before' => 0,
            'stock_after' => 20,
            'reason' => 'Stock inicial',
        ]);
    }

    public function test_it_registers_a_guardian_medication_for_a_student(): void
    {
        $student = StudentProfile::query()->create([
            'first_name' => 'Camila',
            'last_name' => 'Rojas',
            'rut' => '21.111.111-1',
            'guardian_name' => 'María Rojas',
            'guardian_relationship' => 'Madre',
            'guardian_phone' => '+56911111111',
        ]);

        $response = $this->postJson('/api/infirmary/medications', [
            'inventory_type' => 'medication',
            'source_type' => 'guardian',
            'name' => 'Paracetamol',
            'commercial_name' => 'Kitadol',
            'concentration' => '500 mg',
            'unit' => 'comprimido',
            'initial_stock' => 12,
            'minimum_stock' => 2,
            'student_profile_id' => $student->id,
            'received_from_guardian' => 'María Rojas',
            'received_at' => '2026-07-17 09:15:00',
            'active' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.inventory_type', 'medication')
            ->assertJsonPath('data.source_type', 'guardian')
            ->assertJsonPath('data.student.id', $student->id)
            ->assertJsonPath('data.received_from_guardian', 'María Rojas');

        $itemId = $response->json('data.id');

        $this->assertDatabaseHas('infirmary_medications', [
            'id' => $itemId,
            'student_profile_id' => $student->id,
            'source_type' => 'guardian',
            'current_stock' => 12,
        ]);
        $this->assertDatabaseHas('infirmary_medication_movements', [
            'medication_id' => $itemId,
            'movement_type' => 'ingreso',
            'reason' => 'Entrega inicial de apoderado',
            'notes' => 'María Rojas',
        ]);
    }

    public function test_guardian_medication_requires_student_and_receipt_information(): void
    {
        $this->postJson('/api/infirmary/medications', [
            'inventory_type' => 'medication',
            'source_type' => 'guardian',
            'name' => 'Salbutamol',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'student_profile_id',
                'received_from_guardian',
                'received_at',
            ]);
    }

    public function test_inventory_can_be_filtered_by_type(): void
    {
        InfirmaryMedication::query()->create([
            'inventory_type' => 'supply',
            'source_type' => 'school',
            'name' => 'Gasa estéril',
            'active' => true,
        ]);
        InfirmaryMedication::query()->create([
            'inventory_type' => 'medication',
            'source_type' => 'school',
            'name' => 'Ibuprofeno',
            'active' => true,
        ]);

        $this->getJson('/api/infirmary/medications?inventory_type=supply')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Gasa estéril')
            ->assertJsonPath('data.0.inventory_type', 'supply');

        $this->getJson('/api/infirmary/medications?inventory_type=medication')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ibuprofeno')
            ->assertJsonPath('data.0.inventory_type', 'medication');
    }

    public function test_a_supply_cannot_register_an_administration_movement(): void
    {
        $supply = InfirmaryMedication::query()->create([
            'inventory_type' => 'supply',
            'source_type' => 'school',
            'name' => 'Apósito estéril',
            'current_stock' => 10,
            'active' => true,
        ]);

        $this->postJson("/api/infirmary/medications/{$supply->id}/movements", [
            'movement_type' => 'administracion',
            'quantity' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('movement_type');

        $this->assertDatabaseHas('infirmary_medications', [
            'id' => $supply->id,
            'current_stock' => 10,
        ]);
        $this->assertDatabaseCount('infirmary_medication_movements', 0);
    }
}
