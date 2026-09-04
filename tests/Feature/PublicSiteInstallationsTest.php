<?php

namespace Tests\Feature;

use App\Models\SiteInstallation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicSiteInstallationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_only_displays_active_installations_due_for_publication(): void
    {
        $published = SiteInstallation::query()->create($this->payload([
            'title' => 'Laboratorio de ciencias',
            'slug' => 'laboratorio-de-ciencias',
            'category' => 'Aprendizaje',
            'summary' => 'Un espacio institucional para experiencias científicas.',
            'body' => '<h2>Aprender experimentando</h2><p>Información pública revisada.</p><script>alert(1)</script>',
            'location_label' => 'Sector académico',
            'capacity' => 32,
            'accessibility_notes' => 'Acceso por ruta sin desniveles.',
            'features' => ['Mesones de trabajo', 'Equipamiento pedagógico'],
            'icon' => 'science',
            'status' => SiteInstallation::STATUS_PUBLISHED,
            'featured' => true,
            'published_at' => now()->subMinute(),
        ]));

        SiteInstallation::query()->create($this->payload([
            'title' => 'Espacio en borrador reservado',
            'slug' => 'espacio-en-borrador-reservado',
        ]));

        SiteInstallation::query()->create($this->payload([
            'title' => 'Espacio programado',
            'slug' => 'espacio-programado',
            'status' => SiteInstallation::STATUS_PUBLISHED,
            'published_at' => now()->addDay(),
        ]));

        SiteInstallation::query()->create($this->payload([
            'title' => 'Espacio inactivo',
            'slug' => 'espacio-inactivo',
            'status' => SiteInstallation::STATUS_PUBLISHED,
            'active' => false,
            'published_at' => now()->subMinute(),
        ]));

        $this->get(route('public.campus'))
            ->assertOk()
            ->assertViewIs('public.pages.campus')
            ->assertSee('Laboratorio de ciencias')
            ->assertSee('Un espacio institucional para experiencias científicas.')
            ->assertSee('Aprender experimentando')
            ->assertSee('Información pública revisada.')
            ->assertSee('Capacidad para 32 personas')
            ->assertSee('Sector académico')
            ->assertSee('Acceso por ruta sin desniveles.')
            ->assertSee('Mesones de trabajo')
            ->assertSee('bi-beaker', false)
            ->assertSee('id="'.$published->slug.'"', false)
            ->assertDontSee('<script>', false)
            ->assertDontSee('Espacio en borrador reservado')
            ->assertDontSee('Espacio programado')
            ->assertDontSee('Espacio inactivo');
    }

    public function test_public_gallery_uses_controlled_media_routes_and_rejects_private_content(): void
    {
        Storage::fake('public');

        $published = SiteInstallation::query()->create($this->payload([
            'title' => 'Biblioteca escolar',
            'slug' => 'biblioteca-escolar',
            'cover_image_path' => 'site/installations/biblioteca/cover.jpg',
            'cover_image_alt' => 'Interior de la biblioteca escolar',
            'status' => SiteInstallation::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
        ]));
        Storage::disk('public')->put($published->cover_image_path, 'cover');

        $galleryImage = $published->galleryImages()->create([
            'image_path' => 'site/installations/biblioteca/gallery/reading-room.jpg',
            'alt_text' => 'Zona de lectura de la biblioteca',
            'sort_order' => 1,
        ]);
        Storage::disk('public')->put($galleryImage->image_path, 'gallery');

        $draft = SiteInstallation::query()->create($this->payload([
            'title' => 'Sala aún privada',
            'slug' => 'sala-aun-privada',
            'cover_image_path' => 'site/installations/private/cover.jpg',
        ]));
        Storage::disk('public')->put($draft->cover_image_path, 'private');

        $this->get(route('public.campus'))
            ->assertOk()
            ->assertSee(route('public.installations.cover', $published, false), false)
            ->assertSee(route('public.installations.gallery', [$published, $galleryImage], false), false)
            ->assertSee('Interior de la biblioteca escolar')
            ->assertSee('Zona de lectura de la biblioteca')
            ->assertDontSee($draft->cover_image_path);

        $coverResponse = $this->get(route('public.installations.cover', $published))->assertOk();
        $this->assertStringContainsString('public', (string) $coverResponse->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $coverResponse->headers->get('X-Content-Type-Options'));

        $this->get(route('public.installations.gallery', [$published, $galleryImage]))->assertOk();
        $this->get(route('public.installations.cover', $draft))->assertNotFound();
    }

    public function test_empty_index_has_an_institutional_state_without_placeholder_installations(): void
    {
        $this->get(route('public.campus'))
            ->assertOk()
            ->assertSee('Próxima actualización')
            ->assertSee('Estamos preparando el recorrido por nuestras instalaciones')
            ->assertSee('El colegio está organizando la información e imágenes institucionales')
            ->assertDontSee('Sala de ejemplo')
            ->assertDontSee('chapel-hero.jpg');
    }

    public function test_installations_are_paginated_without_losing_the_public_menu_link(): void
    {
        foreach (range(1, 10) as $position) {
            SiteInstallation::query()->create($this->payload([
                'title' => 'Espacio institucional '.$position,
                'slug' => 'espacio-institucional-'.$position,
                'status' => SiteInstallation::STATUS_PUBLISHED,
                'sort_order' => $position,
                'published_at' => now()->subMinutes($position),
            ]));
        }

        $this->get(route('public.campus'))
            ->assertOk()
            ->assertSee('Espacio institucional 1')
            ->assertSee('Espacio institucional 9')
            ->assertDontSee('Espacio institucional 10')
            ->assertSee(route('public.campus', ['page' => 2]), false)
            ->assertSee('Instalaciones');

        $this->get(route('public.campus', ['page' => 2]))
            ->assertOk()
            ->assertSee('Espacio institucional 10')
            ->assertDontSee('id="espacio-institucional-1"', false);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Espacio institucional',
            'slug' => 'espacio-institucional',
            'status' => SiteInstallation::STATUS_DRAFT,
            'active' => true,
            'featured' => false,
            'sort_order' => 0,
        ], $overrides);
    }
}
