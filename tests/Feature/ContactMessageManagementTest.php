<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContactMessageManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_contact_form_stores_message(): void
    {
        $this->post('/contacto', $this->contactPayload([
            'nombre' => 'Apoderada de prueba',
            'correo' => 'apoderada@example.com',
            'telefono' => '+56 9 1234 5678',
            'asunto' => 'Consulta de admisión',
            'mensaje' => 'Necesito información sobre el proceso de admisión.',
        ]))
            ->assertRedirect(route('public.contact'))
            ->assertSessionHas('contact_success');

        $this->assertDatabaseHas('contact_messages', [
            'full_name' => 'Apoderada de prueba',
            'email' => 'apoderada@example.com',
            'subject' => 'Consulta de admisión',
            'status' => ContactMessage::STATUS_NEW,
        ]);
    }

    public function test_public_contact_form_validates_email(): void
    {
        $this->from('/contacto')
            ->post('/contacto', $this->contactPayload([
                'nombre' => 'Apoderada de prueba',
                'correo' => 'correo-invalido',
                'asunto' => 'Consulta',
                'mensaje' => 'Mensaje de prueba',
            ]))
            ->assertRedirect('/contacto')
            ->assertSessionHasErrors('correo');

        $this->assertDatabaseMissing('contact_messages', [
            'full_name' => 'Apoderada de prueba',
            'email' => 'correo-invalido',
        ]);
    }

    public function test_public_contact_form_rejects_honeypot_submissions(): void
    {
        $this->from('/contacto')
            ->post('/contacto', $this->contactPayload([
                'correo' => 'bot@example.com',
                'website' => 'https://spam.example',
            ]))
            ->assertRedirect('/contacto')
            ->assertSessionHasErrors('website');

        $this->assertDatabaseMissing('contact_messages', ['email' => 'bot@example.com']);
    }

    public function test_public_contact_form_rejects_too_fast_or_tampered_challenges(): void
    {
        $this->from('/contacto')
            ->post('/contacto', $this->contactPayload([
                'correo' => 'fast-bot@example.com',
                'contact_started_at' => Crypt::encryptString((string) now()->timestamp),
            ]))
            ->assertRedirect('/contacto')
            ->assertSessionHasErrors('contact_started_at');

        $this->from('/contacto')
            ->post('/contacto', $this->contactPayload([
                'correo' => 'tampered-bot@example.com',
                'contact_started_at' => 'token-manipulado',
            ]))
            ->assertRedirect('/contacto')
            ->assertSessionHasErrors('contact_started_at');

        $this->assertDatabaseMissing('contact_messages', ['email' => 'fast-bot@example.com']);
        $this->assertDatabaseMissing('contact_messages', ['email' => 'tampered-bot@example.com']);
    }

    public function test_public_contact_form_rejects_the_observed_promotional_bot_pattern(): void
    {
        $this->from('/contacto')
            ->post('/contacto', $this->contactPayload([
                'nombre' => 'HarryPiz',
                'correo' => 'whiteman.86@hotmail.com',
                'telefono' => '82144762684',
                'asunto' => '334219',
                'mensaje' => 'Go all in with a $25,000 promo code https://telegra.ph/Win-the-jackpot-today',
            ]))
            ->assertRedirect('/contacto')
            ->assertSessionHasErrors(['telefono', 'asunto', 'mensaje']);

        $this->from('/contacto')
            ->post('/contacto', $this->contactPayload([
                'nombre' => 'Sammybetly',
                'correo' => 'novikov_ivan_1978_10_4@bk.ru',
                'telefono' => '84957232528',
                'asunto' => '545646',
                'mensaje' => 'Игры — от простых игр вроде пасьянса или онлайн покера — до самых последних Android игр.',
            ]))
            ->assertRedirect('/contacto')
            ->assertSessionHasErrors(['telefono', 'asunto', 'mensaje']);

        $this->assertDatabaseMissing('contact_messages', ['email' => 'whiteman.86@hotmail.com']);
        $this->assertDatabaseMissing('contact_messages', ['email' => 'novikov_ivan_1978_10_4@bk.ru']);
    }

    public function test_public_contact_form_rejects_external_links_but_not_email_addresses_in_message(): void
    {
        $this->from('/contacto')
            ->post('/contacto', $this->contactPayload([
                'correo' => 'link@example.com',
                'mensaje' => 'Solicito revisar antecedentes publicados en www.spam.example/oferta.',
            ]))
            ->assertSessionHasErrors('mensaje');

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.33'])
            ->post('/contacto', $this->contactPayload([
                'correo' => 'family@example.com',
                'mensaje' => 'Pueden responder también al correo familiar alternativo@example.cl.',
            ]))
            ->assertSessionHas('contact_success');

        $this->assertDatabaseHas('contact_messages', ['email' => 'family@example.com']);
    }

    public function test_public_contact_form_suppresses_exact_duplicates(): void
    {
        $payload = $this->contactPayload([
            'correo' => 'duplicate@example.com',
            'asunto' => 'Consulta duplicada',
            'mensaje' => 'Este mensaje no debe almacenarse dos veces.',
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.31'])->post('/contacto', $payload)
            ->assertSessionHas('contact_success');
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.31'])->post('/contacto', $payload)
            ->assertSessionHas('contact_success');

        $this->assertSame(1, ContactMessage::query()->where('email', 'duplicate@example.com')->count());
    }

    public function test_public_contact_form_limits_repeated_attempts_by_ip(): void
    {
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.32'])
                ->post('/contacto', $this->contactPayload([
                    'correo' => "rate-limit-{$attempt}@example.com",
                    'asunto' => "Consulta número {$attempt}",
                    'mensaje' => "Mensaje legítimo número {$attempt} para probar el límite.",
                ]))
                ->assertSessionHas('contact_success');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.32'])
            ->post('/contacto', $this->contactPayload([
                'correo' => 'rate-limit-5@example.com',
                'asunto' => 'Consulta número cinco',
                'mensaje' => 'Este intento debe ser bloqueado por el límite configurado.',
            ]))
            ->assertRedirect(route('public.contact'))
            ->assertSessionHasErrors('formulario');

        $this->assertSame(4, ContactMessage::query()->where('email', 'like', 'rate-limit-%@example.com')->count());
    }

    public function test_public_contact_form_limits_repeated_email_across_different_ips(): void
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.4{$attempt}"])
                ->post('/contacto', $this->contactPayload([
                    'correo' => 'repeated-address@example.com',
                    'asunto' => "Consulta familiar {$attempt}",
                    'mensaje' => "Esta es una consulta legítima diferente número {$attempt}.",
                ]))
                ->assertSessionHas('contact_success');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.44'])
            ->post('/contacto', $this->contactPayload([
                'correo' => 'repeated-address@example.com',
                'asunto' => 'Cuarta consulta familiar',
                'mensaje' => 'Este cuarto intento debe bloquearse aunque cambie la dirección IP.',
            ]))
            ->assertRedirect(route('public.contact'))
            ->assertSessionHasErrors('formulario');

        $this->assertSame(3, ContactMessage::query()->where('email', 'repeated-address@example.com')->count());
    }

    public function test_authorized_user_can_manage_contact_messages(): void
    {
        $user = $this->userWithPermissions(['ver_contactos_sitio', 'gestionar_contactos_sitio']);
        Sanctum::actingAs($user);

        $message = ContactMessage::query()->create([
            'full_name' => 'Contacto panel',
            'email' => 'contacto.panel@example.com',
            'phone' => '632244731',
            'subject' => 'Mensaje para panel',
            'message' => 'Contenido recibido desde contacto.',
            'status' => ContactMessage::STATUS_NEW,
            'source_page' => '/contacto',
        ]);

        $this->getJson('/api/admin/contact-messages?search=panel')
            ->assertOk()
            ->assertJsonFragment(['subject' => 'Mensaje para panel']);

        $this->getJson("/api/admin/contact-messages/{$message->id}")
            ->assertOk()
            ->assertJsonPath('data.status', ContactMessage::STATUS_READ);

        $this->putJson("/api/admin/contact-messages/{$message->id}", [
            'status' => ContactMessage::STATUS_RESPONDED,
            'internal_notes' => 'Respondido por secretaría.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', ContactMessage::STATUS_RESPONDED)
            ->assertJsonPath('data.internal_notes', 'Respondido por secretaría.');

        $this->assertDatabaseHas('contact_messages', [
            'id' => $message->id,
            'status' => ContactMessage::STATUS_RESPONDED,
            'handled_by' => $user->id,
        ]);

        $this->deleteJson("/api/admin/contact-messages/{$message->id}")
            ->assertOk();

        $this->assertDatabaseMissing('contact_messages', ['id' => $message->id]);
    }

    public function test_contact_catalog_reports_read_only_capability(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['ver_contactos_sitio']));

        $this->getJson('/api/admin/contact-messages/catalogs')
            ->assertOk()
            ->assertJsonPath('capabilities.can_manage', false);
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

    private function contactPayload(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Persona de prueba',
            'correo' => 'contacto@example.com',
            'telefono' => '',
            'asunto' => 'Consulta general',
            'mensaje' => 'Necesito información adicional sobre el colegio.',
            'website' => '',
            'contact_started_at' => Crypt::encryptString((string) now()->subSeconds(4)->timestamp),
        ], $overrides);
    }
}
