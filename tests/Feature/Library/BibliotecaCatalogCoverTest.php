<?php

namespace Tests\Feature\Library;

use App\Models\Library\BibliotecaObra;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BibliotecaCatalogCoverTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->user = User::factory()->create(['active' => true]);
        $this->user->roles()->sync([
            Role::query()->where('slug', 'super_admin')->firstOrFail()->id,
        ]);
        Sanctum::actingAs($this->user);
    }

    public function test_it_creates_a_book_with_a_private_uploaded_cover(): void
    {
        $response = $this->post('/api/biblioteca/obras', [
            'material_type' => 'libro',
            'title' => 'Libro con portada',
            'main_author' => 'Biblioteca escolar',
            'general_status' => 'disponible',
            'quantity' => 1,
            'cover_image' => UploadedFile::fake()->image('portada-computador.jpg', 900, 1200),
        ])->assertCreated();

        $obra = BibliotecaObra::query()->firstOrFail();
        $coverUrl = $response->json('data.cover_image_url');

        $this->assertMatchesRegularExpression(
            '#^/api/biblioteca/obras/'.$obra->id.'/cover/[a-f0-9-]+\.jpg$#',
            $coverUrl
        );
        $coverPath = 'library/catalog-covers/'.$obra->id.'/'.basename($coverUrl);
        Storage::disk('local')->assertExists($coverPath);

        $coverResponse = $this->get($coverUrl)
            ->assertOk()
            ->assertHeader('x-content-type-options', 'nosniff');
        $this->assertStringContainsString('private', (string) $coverResponse->headers->get('cache-control'));
        $this->assertStringContainsString('max-age=3600', (string) $coverResponse->headers->get('cache-control'));

        $unauthorizedUser = User::factory()->create(['active' => true]);
        Sanctum::actingAs($unauthorizedUser);
        $this->get($coverUrl)->assertForbidden();
    }

    public function test_it_replaces_an_uploaded_cover_without_leaving_the_previous_file(): void
    {
        $createResponse = $this->post('/api/biblioteca/obras', [
            'material_type' => 'libro',
            'title' => 'Portada inicial',
            'main_author' => 'Biblioteca escolar',
            'general_status' => 'disponible',
            'quantity' => 0,
            'cover_image' => UploadedFile::fake()->image('portada-inicial.png', 600, 800),
        ])->assertCreated();

        $obra = BibliotecaObra::query()->firstOrFail();
        $obra->forceFill([
            'secondary_authors' => ['Autor anterior'],
            'keywords' => ['palabra anterior'],
        ])->save();
        $previousUrl = $createResponse->json('data.cover_image_url');
        $previousPath = 'library/catalog-covers/'.$obra->id.'/'.basename($previousUrl);

        $updateResponse = $this->post('/api/biblioteca/obras/'.$obra->id, [
            '_method' => 'PUT',
            'material_type' => 'libro',
            'title' => 'Portada actualizada',
            'main_author' => 'Biblioteca escolar',
            'general_status' => 'disponible',
            'cover_image_url' => $previousUrl,
            'cover_image' => UploadedFile::fake()->image('portada-camara.jpg', 900, 1200),
        ])->assertOk();

        $currentUrl = $updateResponse->json('data.cover_image_url');
        $this->assertNotSame($previousUrl, $currentUrl);
        $this->assertSame([], $obra->fresh()->secondary_authors);
        $this->assertSame([], $obra->fresh()->keywords);
        Storage::disk('local')->assertMissing($previousPath);
        Storage::disk('local')->assertExists(
            'library/catalog-covers/'.$obra->id.'/'.basename($currentUrl)
        );
    }

    public function test_it_rejects_a_non_image_cover_without_creating_the_book(): void
    {
        $this->post('/api/biblioteca/obras', [
            'material_type' => 'libro',
            'title' => 'Archivo inválido',
            'main_author' => 'Biblioteca escolar',
            'general_status' => 'disponible',
            'quantity' => 0,
            'cover_image' => UploadedFile::fake()->create('portada.pdf', 100, 'application/pdf'),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('cover_image');

        $this->assertDatabaseMissing('biblioteca_obras', ['title' => 'Archivo inválido']);
    }
}
