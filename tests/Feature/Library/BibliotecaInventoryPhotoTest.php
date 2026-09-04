<?php

namespace Tests\Feature\Library;

use App\Models\Library\BibliotecaEjemplar;
use App\Models\Library\BibliotecaObra;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BibliotecaInventoryPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_accepts_a_mobile_photo_and_serves_it_only_through_the_authenticated_route(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['active' => true]);
        $user->roles()->sync([
            Role::query()->where('slug', 'super_admin')->firstOrFail()->id,
        ]);
        Sanctum::actingAs($user);

        $obra = BibliotecaObra::query()->create([
            'material_type' => 'libro',
            'title' => 'Inventario fotográfico',
            'main_author' => 'Biblioteca escolar',
            'internal_code' => 'BIB-OBR-PHOTO-001',
            'general_status' => 'disponible',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $ejemplar = BibliotecaEjemplar::query()->create([
            'biblioteca_obra_id' => $obra->id,
            'code' => 'BIB-EJ-PHOTO-001',
            'origin' => 'inventario_inicial',
            'physical_state' => 'bueno',
            'availability_status' => 'disponible',
            'photo_urls' => [],
            'is_active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this->post('/api/biblioteca/ejemplares/'.$ejemplar->id.'/photos', [
            'photos' => [UploadedFile::fake()->image('camara-telefono.jpg', 900, 1200)],
        ])->assertOk()
            ->assertJsonPath('message', 'Fotografía incorporada al inventario.');

        $photoUrl = $response->json('data.photo_urls.0');
        $this->assertMatchesRegularExpression(
            '#^/api/biblioteca/ejemplares/'.$ejemplar->id.'/photos/[a-f0-9-]+\.jpg$#',
            $photoUrl
        );
        $filename = basename($photoUrl);
        Storage::disk('local')->assertExists('library/inventory-evidence/'.$ejemplar->id.'/'.$filename);
        $this->assertDatabaseHas('biblioteca_inventario_movimientos', [
            'biblioteca_ejemplar_id' => $ejemplar->id,
            'movement_type' => 'ajuste',
            'notes' => 'Evidencia fotográfica incorporada desde Inventario.',
        ]);

        $this->get($photoUrl)
            ->assertOk()
            ->assertHeader('x-content-type-options', 'nosniff');

        $ejemplar->refresh();
        $this->putJson('/api/biblioteca/ejemplares/'.$ejemplar->id, [
            'biblioteca_obra_id' => $obra->id,
            'code' => $ejemplar->code,
            'origin' => $ejemplar->origin,
            'physical_state' => $ejemplar->physical_state,
            'availability_status' => $ejemplar->availability_status,
            'photo_urls' => [],
            'is_active' => true,
        ])->assertOk();

        Storage::disk('local')->assertMissing('library/inventory-evidence/'.$ejemplar->id.'/'.$filename);
        $this->get($photoUrl)->assertNotFound();
    }
}
