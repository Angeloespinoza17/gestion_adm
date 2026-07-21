<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContactMessageManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_contact_form_stores_message(): void
    {
        $this->post('/contacto', [
            'nombre' => 'Apoderada de prueba',
            'correo' => 'apoderada@example.com',
            'telefono' => '+56 9 1234 5678',
            'asunto' => 'Consulta de admisión',
            'mensaje' => 'Necesito información sobre el proceso de admisión.',
            'website' => '',
        ])
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
            ->post('/contacto', [
                'nombre' => 'Apoderada de prueba',
                'correo' => 'correo-invalido',
                'asunto' => 'Consulta',
                'mensaje' => 'Mensaje de prueba',
                'website' => '',
            ])
            ->assertRedirect('/contacto')
            ->assertSessionHasErrors('correo');

        $this->assertDatabaseMissing('contact_messages', [
            'full_name' => 'Apoderada de prueba',
            'email' => 'correo-invalido',
        ]);
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
