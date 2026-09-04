<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SiteInstallation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteInstallationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_installations_module_is_registered_and_protected_by_granular_permissions(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'active' => true,
            'user_type' => 'staff',
        ]));

        $this->getJson('/api/admin/installations')->assertForbidden();
        $this->postJson('/api/admin/installations', [])->assertForbidden();
        $this->patchJson('/api/admin/installations/reorder', ['items' => []])->assertForbidden();

        $privateInstallation = SiteInstallation::query()->create($this->installationPayload([
            'cover_image_path' => 'site/installations/private/cover.jpg',
        ]));
        $this->get($privateInstallation->preview_cover_image_url)->assertForbidden();
        $this->get($privateInstallation->cover_image_url)->assertNotFound();

        $this->assertDatabaseHas('permissions', [
            'slug' => 'ver_instalaciones_sitio',
            'active' => true,
        ]);
        $this->assertDatabaseHas('permissions', [
            'slug' => 'gestionar_instalaciones_sitio',
            'active' => true,
        ]);
        $this->assertDatabaseHas('system_modules', [
            'slug' => 'public_site_installations',
            'frontend_route' => '/admin/instalaciones',
            'active' => true,
        ]);
    }

    public function test_authorized_user_can_manage_safe_installation_content_cover_and_gallery(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->userWithPermissions([
            'ver_instalaciones_sitio',
            'gestionar_instalaciones_sitio',
        ]));

        $response = $this->post('/api/admin/installations', [
            'title' => 'Laboratorio de Ciencias',
            'category' => 'Aprendizaje',
            'summary' => 'Un espacio equipado para aprender mediante la experimentación.',
            'body' => '<p>Aprendizaje <strong>activo</strong>.</p><script>alert(1)</script>',
            'location_label' => 'Segundo piso',
            'capacity' => 36,
            'accessibility_notes' => 'Acceso mediante ascensor institucional.',
            'features' => ['Mesones de trabajo', 'Equipamiento audiovisual'],
            'icon' => 'science',
            'cover_image_alt' => 'Laboratorio de ciencias del colegio',
            'meta_title' => 'Laboratorio de Ciencias | CNSC',
            'meta_description' => 'Conoce el laboratorio de ciencias del colegio.',
            'status' => SiteInstallation::STATUS_PUBLISHED,
            'active' => '1',
            'featured' => '1',
            'sort_order' => 1,
            'cover_image' => UploadedFile::fake()->image('laboratorio.jpg', 1400, 900),
            'gallery' => [
                UploadedFile::fake()->image('mesones.jpg', 1100, 720),
                UploadedFile::fake()->image('equipamiento.png', 1100, 720),
            ],
            'gallery_alts' => ['Mesones del laboratorio', 'Equipamiento del laboratorio'],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.slug', 'laboratorio-de-ciencias')
            ->assertJsonPath('data.capacity', 36)
            ->assertJsonPath('data.capacity_label', 'Capacidad para 36 personas')
            ->assertJsonPath('data.features.0', 'Mesones de trabajo')
            ->assertJsonPath('data.gallery_images.0.alt', 'Mesones del laboratorio')
            ->assertJsonCount(2, 'data.gallery_images')
            ->assertJsonMissingPath('data.cover_image_path')
            ->assertJsonMissingPath('data.gallery_images.0.image_path');

        $installation = SiteInstallation::query()
            ->with('galleryImages')
            ->findOrFail($response->json('data.id'));

        $this->assertStringNotContainsString('<script', (string) $installation->body);
        $this->assertStringContainsString('<strong>activo</strong>', (string) $installation->body);
        Storage::disk('public')->assertExists($installation->cover_image_path);
        $installation->galleryImages->each(
            fn ($image) => Storage::disk('public')->assertExists($image->image_path),
        );

        $publicCover = $this->get($installation->cover_image_url)->assertOk();
        $this->assertStringContainsString(
            'public',
            (string) $publicCover->headers->get('Cache-Control'),
        );
        $this->get($installation->galleryImages->first()->url)->assertOk();
        $adminCover = $this->get($installation->preview_cover_image_url)->assertOk();
        $this->assertStringContainsString(
            'private',
            (string) $adminCover->headers->get('Cache-Control'),
        );
        $this->assertStringContainsString(
            'no-store',
            (string) $adminCover->headers->get('Cache-Control'),
        );

        $this->getJson('/api/admin/installations?search=Laboratorio&status=published')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.location_label', 'Segundo piso');
        $this->getJson('/api/admin/installations/catalogs')
            ->assertOk()
            ->assertJsonPath('stats.published', 1)
            ->assertJsonPath('capabilities.can_manage', true)
            ->assertJsonPath('categories.0', 'Aprendizaje');

        $removedImage = $installation->galleryImages->first();
        $retainedImage = $installation->galleryImages->last();
        $oldCover = $installation->cover_image_path;

        $this->post("/api/admin/installations/{$installation->id}", [
            '_method' => 'PUT',
            'title' => 'Laboratorio de Ciencias renovado',
            'slug' => 'laboratorio-de-ciencias',
            'category' => 'Aprendizaje',
            'summary' => 'Resumen actualizado.',
            'body' => '<p>Contenido actualizado.</p>',
            'location_label' => 'Segundo piso',
            'capacity' => 40,
            'features' => ['Mesones de trabajo', 'Pantalla interactiva'],
            'icon' => 'science',
            'status' => SiteInstallation::STATUS_PUBLISHED,
            'active' => '1',
            'featured' => '0',
            'sort_order' => 3,
            'remove_gallery_image_ids' => [$removedImage->id],
            'gallery_order' => [$retainedImage->id],
            'cover_image_alt' => 'Laboratorio renovado',
            'cover_image' => UploadedFile::fake()->image('laboratorio-nuevo.jpg', 1400, 900),
            'gallery' => [UploadedFile::fake()->image('pantalla.jpg', 1100, 720)],
            'gallery_alts' => ['Pantalla interactiva'],
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Laboratorio de Ciencias renovado')
            ->assertJsonPath('data.capacity', 40)
            ->assertJsonCount(2, 'data.gallery_images');

        Storage::disk('public')->assertMissing($oldCover);
        Storage::disk('public')->assertMissing($removedImage->image_path);
        Storage::disk('public')->assertExists($installation->fresh()->cover_image_path);

        $installation = $installation->fresh()->load('galleryImages');
        $newCover = $installation->cover_image_path;
        $this->deleteJson("/api/admin/installations/{$installation->id}")->assertOk();

        Storage::disk('public')->assertMissing($newCover);
        $this->assertDatabaseMissing('site_installations', ['id' => $installation->id]);
        $this->assertDatabaseMissing('site_installation_images', [
            'site_installation_id' => $installation->id,
        ]);
    }

    public function test_publication_requires_a_cover_and_public_scope_excludes_inactive_or_future_content(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->userWithPermissions([
            'ver_instalaciones_sitio',
            'gestionar_instalaciones_sitio',
        ]));

        $this->postJson('/api/admin/installations', [
            'title' => 'Biblioteca sin portada',
            'status' => SiteInstallation::STATUS_PUBLISHED,
            'active' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cover_image');

        $this->post('/api/admin/installations', [
            'title' => 'Biblioteca sin descripción de portada',
            'status' => SiteInstallation::STATUS_PUBLISHED,
            'active' => '1',
            'cover_image' => UploadedFile::fake()->image('biblioteca.jpg', 1200, 800),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cover_image_alt');

        $this->post('/api/admin/installations', [
            'title' => 'Biblioteca con galería sin descripción',
            'status' => SiteInstallation::STATUS_PUBLISHED,
            'active' => '1',
            'cover_image_alt' => 'Biblioteca del colegio',
            'cover_image' => UploadedFile::fake()->image('biblioteca-portada.jpg', 1200, 800),
            'gallery' => [UploadedFile::fake()->image('biblioteca-interior.jpg', 1000, 700)],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('gallery_alts');

        $draft = SiteInstallation::query()->create($this->installationPayload([
            'title' => 'Sala borrador',
            'slug' => 'sala-borrador',
            'cover_image_path' => 'site/installations/draft/cover.jpg',
        ]));
        $inactive = SiteInstallation::query()->create($this->installationPayload([
            'title' => 'Sala inactiva',
            'slug' => 'sala-inactiva',
            'cover_image_path' => 'site/installations/inactive/cover.jpg',
            'status' => SiteInstallation::STATUS_PUBLISHED,
            'active' => false,
            'published_at' => now()->subMinute(),
        ]));
        $future = SiteInstallation::query()->create($this->installationPayload([
            'title' => 'Sala programada',
            'slug' => 'sala-programada',
            'cover_image_path' => 'site/installations/future/cover.jpg',
            'status' => SiteInstallation::STATUS_PUBLISHED,
            'published_at' => now()->addDay(),
        ]));
        $visible = SiteInstallation::query()->create($this->installationPayload([
            'title' => 'Sala publicada',
            'slug' => 'sala-publicada',
            'cover_image_path' => 'site/installations/visible/cover.jpg',
            'status' => SiteInstallation::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
        ]));

        Storage::disk('public')->put($draft->cover_image_path, 'draft');
        Storage::disk('public')->put($inactive->cover_image_path, 'inactive');
        Storage::disk('public')->put($future->cover_image_path, 'future');
        Storage::disk('public')->put($visible->cover_image_path, 'visible');

        $this->assertSame(
            [$visible->id],
            SiteInstallation::query()->published()->pluck('id')->all(),
        );
        $this->get($draft->cover_image_url)->assertNotFound();
        $this->get($inactive->cover_image_url)->assertNotFound();
        $this->get($future->cover_image_url)->assertNotFound();
        $this->get($visible->cover_image_url)->assertOk();

        $this->post("/api/admin/installations/{$visible->id}", [
            '_method' => 'PUT',
            'title' => $visible->title,
            'slug' => $visible->slug,
            'status' => SiteInstallation::STATUS_PUBLISHED,
            'active' => '1',
            'featured' => '0',
            'sort_order' => 1,
            'remove_cover_image' => '1',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cover_image');

        $visible->refresh();
        $this->assertSame('site/installations/visible/cover.jpg', $visible->cover_image_path);
        Storage::disk('public')->assertExists($visible->cover_image_path);
    }

    public function test_reorder_is_atomic_and_gallery_operations_cannot_cross_installations(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->userWithPermissions([
            'ver_instalaciones_sitio',
            'gestionar_instalaciones_sitio',
        ]));

        $first = SiteInstallation::query()->create($this->installationPayload([
            'title' => 'Primera instalación',
            'slug' => 'primera-instalacion',
            'sort_order' => 1,
        ]));
        $second = SiteInstallation::query()->create($this->installationPayload([
            'title' => 'Segunda instalación',
            'slug' => 'segunda-instalacion',
            'sort_order' => 2,
        ]));
        $foreignImage = $second->galleryImages()->create([
            'image_path' => 'site/installations/second/gallery/image.jpg',
            'alt_text' => 'Imagen ajena',
            'sort_order' => 1,
        ]);

        $this->patchJson('/api/admin/installations/reorder', [
            'items' => [
                ['id' => $first->id, 'sort_order' => 20],
                ['id' => $second->id, 'sort_order' => 10],
            ],
        ])->assertOk();

        $this->assertSame(20, $first->fresh()->sort_order);
        $this->assertSame(10, $second->fresh()->sort_order);

        $this->putJson("/api/admin/installations/{$first->id}", [
            'title' => $first->title,
            'slug' => $first->slug,
            'status' => SiteInstallation::STATUS_DRAFT,
            'active' => true,
            'featured' => false,
            'sort_order' => 20,
            'remove_gallery_image_ids' => [$foreignImage->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('remove_gallery_image_ids');

        $this->assertDatabaseHas('site_installation_images', ['id' => $foreignImage->id]);
        $this->get(route('api.admin.installations.gallery', [
            'siteInstallation' => $first,
            'siteInstallationImage' => $foreignImage,
        ]))->assertNotFound();
    }

    private function installationPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Instalación de prueba',
            'slug' => 'instalacion-'.Str::lower(Str::random(10)),
            'status' => SiteInstallation::STATUS_DRAFT,
            'active' => true,
            'featured' => false,
            'sort_order' => 0,
        ], $overrides);
    }

    private function userWithPermissions(array $permissionSlugs): User
    {
        $role = Role::query()->create([
            'name' => 'Rol instalaciones '.Str::random(8),
            'slug' => 'rol_instalaciones_'.Str::lower(Str::random(12)),
            'active' => true,
        ]);
        $permissions = collect($permissionSlugs)->map(
            fn (string $slug) => Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => Str::headline(str_replace('_', ' ', $slug)), 'active' => true],
            ),
        );

        $role->permissions()->sync($permissions->pluck('id')->all());

        $user = User::factory()->create([
            'active' => true,
            'user_type' => 'staff',
        ]);
        $user->roles()->attach($role);

        return $user;
    }
}
