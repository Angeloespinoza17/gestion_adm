<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\StudentLifePost;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicSiteContentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_approved_active_and_due_content_is_publicly_scoped(): void
    {
        $visible = Testimonial::query()->create($this->makeTestimonialPayload([
            'author_name' => 'Comunidad educativa',
            'status' => Testimonial::STATUS_PUBLISHED,
            'active' => true,
            'consent_confirmed_at' => now()->subMinute(),
            'published_at' => now()->subMinute(),
        ]));
        Testimonial::query()->create($this->makeTestimonialPayload([
            'author_name' => 'Borrador',
            'status' => Testimonial::STATUS_DRAFT,
        ]));
        Testimonial::query()->create($this->makeTestimonialPayload([
            'author_name' => 'Inactivo',
            'status' => Testimonial::STATUS_PUBLISHED,
            'active' => false,
            'consent_confirmed_at' => now()->subMinute(),
            'published_at' => now()->subMinute(),
        ]));
        Testimonial::query()->create($this->makeTestimonialPayload([
            'author_name' => 'Programado',
            'status' => Testimonial::STATUS_PUBLISHED,
            'consent_confirmed_at' => now()->subMinute(),
            'published_at' => now()->addDay(),
        ]));
        Testimonial::query()->create($this->makeTestimonialPayload([
            'author_name' => 'Sin autorización',
            'status' => Testimonial::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
        ]));

        $this->assertSame([$visible->id], Testimonial::query()->published()->pluck('id')->all());

        $visiblePost = StudentLifePost::query()->create($this->studentLifePayload([
            'title' => 'Taller artístico publicado',
            'slug' => 'taller-artistico-publicado',
            'status' => StudentLifePost::STATUS_PUBLISHED,
            'active' => true,
            'published_at' => now()->subMinute(),
        ]));
        StudentLifePost::query()->create($this->studentLifePayload([
            'title' => 'Taller aún programado',
            'slug' => 'taller-aun-programado',
            'status' => StudentLifePost::STATUS_PUBLISHED,
            'published_at' => now()->addDay(),
        ]));

        $this->assertSame(
            [$visiblePost->id],
            StudentLifePost::query()->published()->pluck('id')->all(),
        );
    }

    public function test_authorized_user_can_manage_testimonials_without_exposing_storage_paths(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->userWithPermissions([
            'ver_testimonios',
            'gestionar_testimonios',
        ]));

        $this->postJson('/api/admin/testimonials', [
            'quote' => 'No se puede publicar sin autorización.',
            'author_name' => 'Persona sin autorización',
            'status' => Testimonial::STATUS_PUBLISHED,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('authorization_confirmed');

        $response = $this->post('/api/admin/testimonials', [
            'quote' => 'El colegio acompaña de manera cercana a cada estudiante.',
            'author_name' => 'Representante de la comunidad',
            'author_role' => 'Apoderada',
            'image_alt' => 'Retrato de representante',
            'status' => Testimonial::STATUS_PUBLISHED,
            'active' => '1',
            'featured' => '1',
            'sort_order' => 2,
            'authorization_confirmed' => '1',
            'image' => UploadedFile::fake()->image('retrato.png', 480, 480),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.author_role', 'Apoderada')
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.featured', true)
            ->assertJsonPath('data.authorization_confirmed', true)
            ->assertJsonMissingPath('data.image_path');

        $testimonial = Testimonial::query()->findOrFail($response->json('data.id'));
        Storage::disk('public')->assertExists($testimonial->image_path);
        $this->assertNotNull($testimonial->consent_confirmed_at);
        $this->assertNotNull($testimonial->consent_confirmed_by);
        $originalConsentAt = $testimonial->consent_confirmed_at->toISOString();
        $originalConsentBy = $testimonial->consent_confirmed_by;
        $publicImageUrl = $testimonial->image_url;

        $this->getJson('/api/admin/testimonials?search=apoderada&status=published')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.author_name', 'Representante de la comunidad');

        $publicImageResponse = $this->get($publicImageUrl)->assertOk();
        $cacheControl = (string) $publicImageResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=300', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);

        $oldPath = $testimonial->image_path;
        $this->post("/api/admin/testimonials/{$testimonial->id}", [
            '_method' => 'PUT',
            'quote' => 'Testimonio actualizado.',
            'author_name' => 'Representante de la comunidad',
            'author_role' => 'Apoderada',
            'status' => Testimonial::STATUS_PUBLISHED,
            'active' => '1',
            'featured' => '0',
            'sort_order' => 4,
            'authorization_confirmed' => '1',
            'remove_image' => '1',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Testimonial::STATUS_PUBLISHED)
            ->assertJsonPath('data.consent_confirmed_at', $originalConsentAt)
            ->assertJsonPath('data.preview_image_url', null);

        Storage::disk('public')->assertMissing($oldPath);
        $this->get($publicImageUrl)->assertNotFound();
        $this->assertSame($originalConsentBy, $testimonial->fresh()->consent_confirmed_by);

        $this->putJson("/api/admin/testimonials/{$testimonial->id}", [
            'quote' => 'Borrador que conserva la autorización previa.',
            'author_name' => 'Representante de la comunidad',
            'author_role' => 'Apoderada',
            'status' => Testimonial::STATUS_DRAFT,
            'active' => true,
            'featured' => false,
            'sort_order' => 4,
        ])
            ->assertOk()
            ->assertJsonPath('data.authorization_confirmed', true)
            ->assertJsonPath('data.consent_confirmed_at', $originalConsentAt);

        $this->putJson("/api/admin/testimonials/{$testimonial->id}", [
            'quote' => 'Testimonio archivado.',
            'author_name' => 'Representante de la comunidad',
            'author_role' => 'Apoderada',
            'status' => Testimonial::STATUS_ARCHIVED,
            'active' => true,
            'featured' => false,
            'sort_order' => 4,
            'authorization_confirmed' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.authorization_confirmed', false)
            ->assertJsonPath('data.consent_confirmed_at', null);

        $this->deleteJson("/api/admin/testimonials/{$testimonial->id}")
            ->assertOk();
        $this->assertDatabaseMissing('testimonials', ['id' => $testimonial->id]);
    }

    public function test_authorized_user_can_manage_student_life_cover_gallery_and_safe_body(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->userWithPermissions([
            'ver_vida_estudiantil',
            'gestionar_vida_estudiantil',
        ]));

        $response = $this->post('/api/admin/student-life', [
            'title' => 'Encuentro artístico escolar',
            'slug' => '',
            'category' => 'Arte y cultura',
            'summary' => 'Una jornada para crear y compartir en comunidad.',
            'body' => '<p>Contenido <strong>seguro</strong>.</p><script>alert(1)</script>',
            'cover_image_alt' => 'Estudiantes participando del encuentro',
            'event_date' => now()->toDateString(),
            'meta_title' => 'Encuentro artístico | CNSC',
            'meta_description' => 'Conoce el encuentro artístico de nuestra comunidad educativa.',
            'status' => StudentLifePost::STATUS_PUBLISHED,
            'active' => '1',
            'featured' => '1',
            'sort_order' => 1,
            'cover_image' => UploadedFile::fake()->image('portada.jpg', 1200, 720),
            'gallery' => [
                UploadedFile::fake()->image('galeria-1.jpg', 900, 600),
                UploadedFile::fake()->image('galeria-2.png', 900, 600),
            ],
            'gallery_alts' => ['Presentación artística', 'Trabajo colaborativo'],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.slug', 'encuentro-artistico-escolar')
            ->assertJsonPath('data.gallery_images.0.alt', 'Presentación artística')
            ->assertJsonCount(2, 'data.gallery_images')
            ->assertJsonMissingPath('data.cover_image_path')
            ->assertJsonMissingPath('data.gallery_images.0.image_path');

        $post = StudentLifePost::query()->with('galleryImages')->findOrFail($response->json('data.id'));
        $this->assertStringNotContainsString('<script', (string) $post->body);
        $this->assertStringContainsString('<strong>seguro</strong>', (string) $post->body);
        Storage::disk('public')->assertExists($post->cover_image_path);
        $post->galleryImages->each(
            fn ($image) => Storage::disk('public')->assertExists($image->image_path),
        );

        $this->get($post->cover_image_url)->assertOk();
        $this->get($post->galleryImages->first()->url)->assertOk();

        $removedImage = $post->galleryImages->first();
        $oldCover = $post->cover_image_path;
        $this->post("/api/admin/student-life/{$post->id}", [
            '_method' => 'PUT',
            'title' => 'Encuentro artístico renovado',
            'slug' => 'encuentro-artistico-escolar',
            'category' => 'Arte y cultura',
            'summary' => 'Resumen actualizado.',
            'body' => '<p>Contenido actualizado.</p>',
            'event_date' => now()->toDateString(),
            'status' => StudentLifePost::STATUS_PUBLISHED,
            'active' => '1',
            'featured' => '0',
            'sort_order' => 3,
            'remove_gallery_image_ids' => [$removedImage->id],
            'cover_image' => UploadedFile::fake()->image('portada-nueva.jpg', 1200, 720),
            'gallery' => [UploadedFile::fake()->image('galeria-3.jpg', 900, 600)],
            'gallery_alts' => ['Nueva actividad'],
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Encuentro artístico renovado')
            ->assertJsonCount(2, 'data.gallery_images');

        Storage::disk('public')->assertMissing($oldCover);
        Storage::disk('public')->assertMissing($removedImage->image_path);

        $post = $post->fresh()->load('galleryImages');
        Storage::disk('public')->assertExists($post->cover_image_path);

        $this->deleteJson("/api/admin/student-life/{$post->id}")->assertOk();
        Storage::disk('public')->assertMissing($post->cover_image_path);
        $this->assertDatabaseMissing('student_life_posts', ['id' => $post->id]);
        $this->assertDatabaseMissing('student_life_post_images', ['student_life_post_id' => $post->id]);
    }

    public function test_permissions_protect_each_internal_content_area(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'active' => true,
            'user_type' => 'staff',
        ]));

        $this->getJson('/api/admin/testimonials')->assertForbidden();
        $this->postJson('/api/admin/testimonials', [])->assertForbidden();
        $this->getJson('/api/admin/student-life')->assertForbidden();
        $this->postJson('/api/admin/student-life', [])->assertForbidden();

        $this->assertDatabaseHas('permissions', ['slug' => 'ver_testimonios']);
        $this->assertDatabaseHas('permissions', ['slug' => 'gestionar_vida_estudiantil']);
        $this->assertDatabaseHas('system_modules', [
            'slug' => 'public_site_testimonials',
            'frontend_route' => '/admin/testimonios',
        ]);
        $this->assertDatabaseHas('system_modules', [
            'slug' => 'public_site_student_life',
            'frontend_route' => '/admin/vida-estudiantil',
        ]);
    }

    private function makeTestimonialPayload(array $overrides = []): array
    {
        return array_merge([
            'quote' => 'Testimonio de prueba.',
            'author_name' => 'Integrante de la comunidad',
            'status' => Testimonial::STATUS_DRAFT,
            'active' => true,
            'featured' => false,
            'sort_order' => 0,
        ], $overrides);
    }

    private function studentLifePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Actividad de prueba',
            'slug' => 'actividad-'.Str::lower(Str::random(10)),
            'status' => StudentLifePost::STATUS_DRAFT,
            'active' => true,
            'featured' => false,
            'sort_order' => 0,
        ], $overrides);
    }

    private function userWithPermissions(array $permissionSlugs): User
    {
        $role = Role::query()->create([
            'name' => 'Rol web '.Str::random(8),
            'slug' => 'rol_web_'.Str::lower(Str::random(12)),
            'active' => true,
        ]);
        $permissions = collect($permissionSlugs)->map(fn (string $slug) => Permission::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => Str::headline(str_replace('_', ' ', $slug)), 'active' => true],
        ));

        $role->permissions()->sync($permissions->pluck('id')->all());

        $user = User::factory()->create([
            'active' => true,
            'user_type' => 'staff',
        ]);
        $user->roles()->attach($role);

        return $user;
    }
}
