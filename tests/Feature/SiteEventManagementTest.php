<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SiteEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteEventManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_events_page_only_shows_published_events(): void
    {
        $newerEvent = SiteEvent::query()->create([
            'title' => 'Evento publico destacado de prueba',
            'slug' => 'evento-publico-destacado-de-prueba',
            'summary' => 'Resumen visible del evento',
            'body' => '<p>Contenido con <strong>formato</strong>.</p>',
            'category' => 'Pastoral',
            'location' => 'Auditorio',
            'starts_at' => now()->addYears(10),
            'hero_image_url' => 'niceschool/assets/img/education/events-9.webp',
            'highlights' => ['Acreditación de familias', 'Presentación pastoral'],
            'schedule_items' => [
                ['time' => '09:00 - 09:30', 'title' => 'Bienvenida', 'description' => 'Recepción de la comunidad.'],
            ],
            'gallery_intro' => 'Registro de actividades anteriores.',
            'gallery_images' => [
                ['url' => 'niceschool/assets/img/education/events-1.webp', 'alt' => 'Galería pastoral'],
            ],
            'registration_enabled' => true,
            'registration_title' => 'Inscripción familiar',
            'registration_button_label' => 'Enviar inscripción',
            'organizer_name' => 'Equipo Pastoral',
            'organizer_email' => 'pastoral@example.com',
            'status' => SiteEvent::STATUS_PUBLISHED,
        ]);

        SiteEvent::query()->create([
            'title' => 'Evento publico antiguo de prueba',
            'slug' => 'evento-publico-antiguo-de-prueba',
            'summary' => 'Resumen visible antiguo',
            'category' => 'Comunidad',
            'location' => 'Patio central',
            'starts_at' => now()->addYears(9),
            'status' => SiteEvent::STATUS_PUBLISHED,
        ]);

        $draftEvent = SiteEvent::query()->create([
            'title' => 'Evento borrador oculto de prueba',
            'slug' => 'evento-borrador-oculto-de-prueba',
            'summary' => 'Resumen oculto',
            'starts_at' => now()->addYears(11),
            'status' => SiteEvent::STATUS_DRAFT,
        ]);

        $this->get('/eventos')
            ->assertOk()
            ->assertSee('Evento publico destacado de prueba')
            ->assertSee("/eventos/{$newerEvent->id}")
            ->assertSeeInOrder(['Evento publico destacado de prueba', 'Evento publico antiguo de prueba'])
            ->assertDontSee('Evento borrador oculto de prueba');

        $this->get("/eventos/{$newerEvent->id}")
            ->assertOk()
            ->assertSee('Contenido con <strong>formato</strong>.', false)
            ->assertSee('Puntos destacados')
            ->assertSee('Acreditación de familias')
            ->assertSee('Programa del evento')
            ->assertSee('Bienvenida')
            ->assertSee('Galería del evento')
            ->assertSee('Inscripción familiar')
            ->assertSee('Equipo Pastoral');

        $this->get("/eventos/{$draftEvent->id}")
            ->assertNotFound();
    }

    public function test_authorized_user_can_manage_site_events(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['ver_eventos', 'gestionar_eventos']));

        $eventId = $this->postJson('/api/admin/events', [
            'title' => 'Nueva jornada pastoral',
            'slug' => '',
            'summary' => 'Resumen de la jornada.',
            'body' => '<p>Contenido extendido del <strong>evento</strong>.</p>',
            'category' => 'Pastoral',
            'location' => 'Capilla',
            'starts_at' => now()->addMonth()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addMonth()->addHours(2)->format('Y-m-d H:i:s'),
            'external_url' => 'https://example.com/evento',
            'header_image_url' => 'niceschool/assets/img/education/showcase-1.webp',
            'hero_image_url' => 'niceschool/assets/img/education/events-9.webp',
            'hero_image_alt' => 'Evento pastoral',
            'highlights' => ['Oración inicial', 'Trabajo comunitario', 'Cierre familiar'],
            'schedule_items' => [
                ['time' => '10:00 - 10:30', 'title' => 'Apertura', 'description' => 'Saludo inicial.'],
                ['time' => '10:30 - 12:00', 'title' => 'Talleres', 'description' => 'Trabajo por grupos.'],
            ],
            'gallery_intro' => 'Imágenes de referencia.',
            'gallery_images' => [
                ['url' => 'niceschool/assets/img/education/events-1.webp', 'alt' => 'Actividad uno'],
                ['url' => 'niceschool/assets/img/education/events-2.webp', 'alt' => 'Actividad dos'],
            ],
            'registration_enabled' => true,
            'registration_title' => 'Registro de participantes',
            'registration_button_label' => 'Registrar asistencia',
            'registration_url' => '/contacto',
            'organizer_name' => 'Área Pastoral',
            'organizer_position' => 'Equipo organizador',
            'organizer_description' => 'Responsables de la jornada.',
            'organizer_email' => 'pastoral@example.com',
            'organizer_phone' => '+56 63 222 2222',
            'organizer_image_url' => 'niceschool/assets/img/person/person-m-5.webp',
            'organizer_image_alt' => 'Equipo Pastoral',
            'status' => SiteEvent::STATUS_PUBLISHED,
            'featured' => true,
            'sort_order' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Nueva jornada pastoral')
            ->assertJsonPath('data.slug', 'nueva-jornada-pastoral')
            ->assertJsonPath('data.highlights.0', 'Oración inicial')
            ->assertJsonPath('data.schedule_items.1.title', 'Talleres')
            ->assertJsonPath('data.gallery_images.0.alt', 'Actividad uno')
            ->assertJsonPath('data.registration_enabled', true)
            ->assertJsonPath('data.organizer_name', 'Área Pastoral')
            ->json('data.id');

        $this->assertDatabaseHas('site_events', [
            'id' => $eventId,
            'title' => 'Nueva jornada pastoral',
            'status' => SiteEvent::STATUS_PUBLISHED,
        ]);

        $this->getJson('/api/admin/events?search=pastoral')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Nueva jornada pastoral']);

        $this->putJson("/api/admin/events/{$eventId}", [
            'title' => 'Nueva jornada pastoral actualizada',
            'slug' => 'nueva-jornada-pastoral',
            'summary' => 'Resumen actualizado.',
            'body' => 'Contenido actualizado.',
            'category' => 'Pastoral',
            'location' => 'Auditorio',
            'starts_at' => now()->addMonth()->format('Y-m-d H:i:s'),
            'ends_at' => '',
            'external_url' => '',
            'header_image_url' => '',
            'hero_image_url' => '',
            'hero_image_alt' => '',
            'highlights' => ['Programa actualizado'],
            'schedule_items' => [
                ['time' => '11:00', 'title' => 'Bloque actualizado', 'description' => 'Detalle actualizado.'],
            ],
            'gallery_intro' => '',
            'gallery_images' => [],
            'registration_enabled' => false,
            'registration_title' => '',
            'registration_button_label' => '',
            'registration_url' => '',
            'organizer_name' => 'Equipo actualizado',
            'organizer_position' => '',
            'organizer_description' => '',
            'organizer_email' => '',
            'organizer_phone' => '',
            'organizer_image_url' => '',
            'organizer_image_alt' => '',
            'status' => SiteEvent::STATUS_ARCHIVED,
            'featured' => false,
            'sort_order' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', SiteEvent::STATUS_ARCHIVED)
            ->assertJsonPath('data.highlights.0', 'Programa actualizado')
            ->assertJsonPath('data.organizer_name', 'Equipo actualizado');

        $this->deleteJson("/api/admin/events/{$eventId}")
            ->assertOk();

        $this->assertDatabaseMissing('site_events', ['id' => $eventId]);
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
