<?php

namespace Tests\Feature;

use App\Models\NewsPost;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NewsPostManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_news_page_only_shows_published_posts(): void
    {
        $publishedPost = NewsPost::query()->create([
            'title' => 'Noticia publicada de prueba',
            'slug' => 'noticia-publicada-de-prueba',
            'excerpt' => 'Resumen visible',
            'body' => '<p>Contenido con <strong>formato</strong>.</p>',
            'category' => 'Comunidad',
            'author_name' => 'Equipo de Comunicaciones',
            'author_role' => 'Comunicaciones',
            'external_image_url' => 'niceschool/assets/img/blog/blog-hero-1.webp',
            'header_image_url' => 'niceschool/assets/img/education/showcase-1.webp',
            'detail_categories' => ['Vida escolar'],
            'toc_items' => [
                ['label' => 'Introducción', 'anchor' => 'introduccion'],
                ['label' => 'Claves', 'anchor' => 'claves'],
            ],
            'quote_text' => 'Una cita destacada de prueba.',
            'quote_author' => 'Dirección',
            'secondary_section_title' => 'Detalle institucional',
            'feature_points' => [
                ['icon' => 'bi bi-layers', 'title' => 'Participación', 'description' => 'Trabajo colaborativo.'],
            ],
            'key_principles' => [
                ['number' => '01', 'title' => 'Comunidad', 'description' => 'Participación de estudiantes y familias.'],
            ],
            'info_box_icon' => 'bi bi-info-circle',
            'info_box_title' => 'Dato importante',
            'info_box_text' => 'Información adicional visible.',
            'tags' => ['Comunidad educativa', 'Noticias'],
            'status' => NewsPost::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $olderPost = NewsPost::query()->create([
            'title' => 'Noticia antigua de prueba',
            'slug' => 'noticia-antigua-de-prueba',
            'excerpt' => 'Resumen antiguo',
            'status' => NewsPost::STATUS_PUBLISHED,
            'published_at' => now()->subDays(3),
        ]);
        $olderPost->forceFill([
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ])->save();

        $draftPost = NewsPost::query()->create([
            'title' => 'Noticia borrador de prueba',
            'slug' => 'noticia-borrador-de-prueba',
            'excerpt' => 'Resumen oculto',
            'status' => NewsPost::STATUS_DRAFT,
        ]);

        $this->get('/noticias')
            ->assertOk()
            ->assertSee('Noticia publicada de prueba')
            ->assertSee("/noticias/{$publishedPost->id}")
            ->assertSeeInOrder(['Noticia publicada de prueba', 'Noticia antigua de prueba'])
            ->assertDontSee('Noticia borrador de prueba');

        $this->get("/noticias/{$publishedPost->id}")
            ->assertOk()
            ->assertSee('Contenido con <strong>formato</strong>.', false)
            ->assertSee('Índice')
            ->assertSee('Una cita destacada de prueba.')
            ->assertSee('Detalle institucional')
            ->assertSee('Participación')
            ->assertSee('Dato importante')
            ->assertSee('Temas relacionados');

        $this->get("/noticias/{$draftPost->id}")
            ->assertNotFound();
    }

    public function test_home_recent_news_carousel_shows_five_newest_posts(): void
    {
        collect(range(1, 6))->each(function (int $number) {
            $post = NewsPost::query()->create([
                'title' => "Carrusel noticia {$number}",
                'slug' => "carrusel-noticia-{$number}",
                'excerpt' => "Resumen carrusel {$number}",
                'status' => NewsPost::STATUS_PUBLISHED,
                'published_at' => now()->subDay(),
            ]);

            $post->forceFill([
                'created_at' => now()->addDay()->subMinutes($number),
                'updated_at' => now()->addDay()->subMinutes($number),
            ])->save();
        });

        $this->get('/')
            ->assertOk()
            ->assertSee('recentNewsCarousel')
            ->assertSeeInOrder([
                'Carrusel noticia 1',
                'Carrusel noticia 2',
                'Carrusel noticia 3',
                'Carrusel noticia 4',
                'Carrusel noticia 5',
            ])
            ->assertDontSee('Carrusel noticia 6');
    }

    public function test_public_news_detail_counts_one_view_per_session(): void
    {
        $post = NewsPost::query()->create([
            'title' => 'Noticia con contador',
            'slug' => 'noticia-con-contador',
            'excerpt' => 'Resumen visible',
            'status' => NewsPost::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'views_count' => 0,
        ]);

        $this->withSession([])
            ->get("/noticias/{$post->id}")
            ->assertOk();

        $this->assertSame(1, $post->fresh()->views_count);

        $this->get("/noticias/{$post->id}")
            ->assertOk();

        $this->assertSame(1, $post->fresh()->views_count);
    }

    public function test_authorized_user_can_manage_news_posts(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->userWithPermissions(['ver_noticias', 'gestionar_noticias']));

        $postId = $this->post('/api/admin/news', [
            'title' => 'Nueva actividad pastoral',
            'slug' => '',
            'excerpt' => 'Resumen de la actividad.',
            'body' => '<p>Contenido extendido de la <strong>noticia</strong>.</p>',
            'category' => 'Pastoral',
            'author_name' => 'Equipo Pastoral',
            'author_role' => 'Área Pastoral',
            'header_image_url' => 'niceschool/assets/img/education/showcase-1.webp',
            'author_image_url' => 'niceschool/assets/img/person/person-m-6.webp',
            'author_image_alt' => 'Equipo Pastoral',
            'reading_minutes' => 4,
            'comments_label' => '0 comentarios',
            'detail_categories' => ['Comunidad', 'Fe'],
            'toc_items' => [
                ['label' => 'Introducción', 'anchor' => 'introduccion'],
                ['label' => 'Comparativa', 'anchor' => 'comparativa'],
            ],
            'quote_text' => 'Texto destacado de la actividad.',
            'quote_author' => 'Equipo Pastoral',
            'secondary_section_title' => 'Participación de la comunidad',
            'secondary_image_url' => 'niceschool/assets/img/blog/blog-hero-2.webp',
            'secondary_image_alt' => 'Comunidad participando',
            'secondary_image_caption' => 'Registro de una actividad anterior.',
            'secondary_image_position' => 'right',
            'feature_points' => [
                ['icon' => 'bi bi-layers', 'title' => 'Acompañamiento', 'description' => 'Trabajo con estudiantes.'],
            ],
            'comparison_cards' => [
                ['icon' => 'bi bi-check-circle', 'title' => 'Logros', 'items' => ['Alta convocatoria', 'Participación familiar']],
            ],
            'key_principles' => [
                ['number' => '01', 'title' => 'Encuentro', 'description' => 'Espacio de comunidad.'],
            ],
            'info_box_icon' => 'bi bi-info-circle',
            'info_box_title' => 'Información pastoral',
            'info_box_text' => 'Detalle informativo de la noticia.',
            'future_trends' => [
                ['icon' => 'bi bi-arrow-right-circle', 'title' => 'Continuidad', 'description' => 'Se realizarán nuevas actividades.'],
            ],
            'tags' => ['Pastoral', 'Comunidad educativa'],
            'share_enabled' => true,
            'status' => NewsPost::STATUS_PUBLISHED,
            'featured' => true,
            'sort_order' => 1,
            'image' => UploadedFile::fake()->image('portada.png', 900, 500),
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Nueva actividad pastoral')
            ->assertJsonPath('data.slug', 'nueva-actividad-pastoral')
            ->assertJsonPath('data.author_role', 'Área Pastoral')
            ->assertJsonPath('data.detail_categories.0', 'Comunidad')
            ->assertJsonPath('data.comparison_cards.0.items.1', 'Participación familiar')
            ->assertJsonPath('data.info_box_title', 'Información pastoral')
            ->json('data.id');

        $post = NewsPost::query()->findOrFail($postId);
        Storage::disk('public')->assertExists($post->image_path);
        $this->assertSame("/noticias/imagen/{$postId}", $post->image_url);

        $this->get($post->image_url)
            ->assertOk();

        $this->getJson('/api/admin/news?search=pastoral')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Nueva actividad pastoral']);

        $this->putJson("/api/admin/news/{$postId}", [
            'title' => 'Nueva actividad pastoral actualizada',
            'slug' => 'nueva-actividad-pastoral',
            'excerpt' => 'Resumen actualizado.',
            'body' => 'Contenido extendido actualizado.',
            'category' => 'Pastoral',
            'author_name' => 'Equipo Pastoral',
            'author_role' => 'Comunicaciones',
            'header_image_url' => '',
            'author_image_url' => '',
            'author_image_alt' => '',
            'reading_minutes' => '',
            'comments_label' => '',
            'detail_categories' => ['Actualidad'],
            'toc_items' => [
                ['label' => 'Introducción', 'anchor' => 'introduccion'],
            ],
            'quote_text' => '',
            'quote_author' => '',
            'secondary_section_title' => '',
            'secondary_image_url' => '',
            'secondary_image_alt' => '',
            'secondary_image_caption' => '',
            'secondary_image_position' => 'right',
            'feature_points' => [],
            'comparison_cards' => [],
            'key_principles' => [
                ['number' => '01', 'title' => 'Actualización', 'description' => 'Detalle actualizado.'],
            ],
            'info_box_icon' => '',
            'info_box_title' => '',
            'info_box_text' => '',
            'future_trends' => [],
            'tags' => ['Actualizada'],
            'share_enabled' => false,
            'status' => NewsPost::STATUS_ARCHIVED,
            'featured' => false,
            'sort_order' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', NewsPost::STATUS_ARCHIVED)
            ->assertJsonPath('data.author_role', 'Comunicaciones')
            ->assertJsonPath('data.detail_categories.0', 'Actualidad')
            ->assertJsonPath('data.share_enabled', false);

        $this->deleteJson("/api/admin/news/{$postId}")
            ->assertOk();

        $this->assertDatabaseMissing('news_posts', ['id' => $postId]);
    }

    private function userWithPermissions(array $permissionSlugs): User
    {
        $role = Role::query()->create([
            'name' => 'Rol ' . Str::random(8),
            'slug' => 'rol_' . Str::random(12),
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
