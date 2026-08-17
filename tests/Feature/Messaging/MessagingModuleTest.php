<?php

namespace Tests\Feature\Messaging;

use App\Models\Messaging\Message;
use App\Models\Messaging\MessageRecipient;
use App\Models\Messaging\MessagingAuditEvent;
use App\Models\User;
use App\Notifications\Messaging\NewMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessagingModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_active_users_create_one_direct_conversation_and_third_parties_cannot_view_it(): void
    {
        [$one, $two, $third] = User::factory()->count(3)->create(['active' => true]);
        Sanctum::actingAs($one);
        $first = $this->postJson('/api/messaging/conversations/direct', ['user_id' => $two->id])->assertCreated()->json('data.public_id');
        $second = $this->postJson('/api/messaging/conversations/direct', ['user_id' => $two->id])->assertCreated()->json('data.public_id');
        $this->assertSame($first, $second);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('conversation_participants', 2);
        Sanctum::actingAs($third);
        $this->getJson('/api/messaging/conversations/'.$first)->assertNotFound();
    }

    public function test_reading_does_not_acknowledge_and_explicit_acknowledgement_is_idempotent(): void
    {
        [$sender, $recipient] = User::factory()->count(2)->create(['active' => true]);
        Sanctum::actingAs($sender);
        $conversation = $this->postJson('/api/messaging/conversations/direct', ['user_id' => $recipient->id])->json('data.public_id');
        $message = $this->postJson("/api/messaging/conversations/{$conversation}/messages", [
            'body' => 'Lee el protocolo institucional adjunto.',
            'subject' => 'Protocolo actualizado',
            'formal' => true,
            'requires_acknowledgement' => true,
            'acknowledgement_comment_required' => true,
            'acknowledgement_due_at' => now()->addDay()->toIso8601String(),
        ])->assertCreated()->json('data.public_id');

        $messageModel = Message::query()->where('public_id', $message)->firstOrFail();
        $recipient->notify(new NewMessageNotification($messageModel->load('conversation')));
        $this->assertGreaterThan(0, $recipient->fresh()->unreadNotifications()->count());

        Sanctum::actingAs($recipient);
        $this->getJson("/api/messaging/conversations/{$conversation}/messages")->assertOk();
        $this->postJson("/api/messaging/conversations/{$conversation}/read", ['through_message_id' => $message])->assertOk();
        $this->assertNull(MessageRecipient::query()->firstOrFail()->acknowledged_at);
        $this->assertSame(0, $recipient->fresh()->unreadNotifications()->count());
        $this->postJson("/api/messaging/messages/{$message}/acknowledge", [])->assertUnprocessable();
        $first = $this->postJson("/api/messaging/messages/{$message}/acknowledge", ['comment' => 'Recibido y revisado.'])->assertOk()->json('data');
        $second = $this->postJson("/api/messaging/messages/{$message}/acknowledge", ['comment' => 'No debe reemplazar el original.'])->assertOk()->json('data');
        $this->assertSame($first['acknowledged_at'], $second['acknowledged_at']);
        $this->assertSame('Recibido y revisado.', MessageRecipient::query()->firstOrFail()->acknowledgement_comment);
        $this->assertDatabaseCount('messaging_audit_events', 5);
        $this->assertSame(1, MessagingAuditEvent::query()->where('event_type', 'message_acknowledged')->count());
    }

    public function test_active_user_can_create_a_group_with_members_and_admin_only_writing(): void
    {
        [$owner, $firstMember, $secondMember, $newMember] = User::factory()->count(4)->create(['active' => true]);
        Sanctum::actingAs($owner);

        $group = $this->postJson('/api/messaging/conversations/group', [
            'title' => 'Equipo de coordinación',
            'description' => 'Canal para organizar el trabajo semanal.',
            'user_ids' => [$firstMember->id, $secondMember->id],
            'only_admins_can_write' => true,
        ])->assertCreated()->json('data');

        $this->assertSame('group', $group['type']);
        $this->assertSame('Equipo de coordinación', $group['title']);
        $this->assertTrue($group['only_admins_can_write']);
        $this->assertCount(3, $group['participants']);
        $this->assertDatabaseHas('conversation_participants', [
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        $publicId = $group['public_id'];
        $this->patchJson("/api/messaging/conversations/{$publicId}", [
            'title' => 'Coordinación general',
            'description' => 'Descripción actualizada.',
            'only_admins_can_write' => false,
        ])->assertOk()->assertJsonPath('data.title', 'Coordinación general');

        $this->postJson("/api/messaging/conversations/{$publicId}/participants", [
            'user_ids' => [$newMember->id],
        ])->assertOk();
        $this->patchJson("/api/messaging/conversations/{$publicId}/participants/{$firstMember->id}", [
            'role' => 'admin',
        ])->assertOk();
        $this->deleteJson("/api/messaging/conversations/{$publicId}/participants/{$secondMember->id}")->assertOk();

        $this->assertDatabaseHas('conversation_participants', ['user_id' => $newMember->id, 'left_at' => null]);
        $this->assertDatabaseHas('conversation_participants', ['user_id' => $firstMember->id, 'role' => 'admin']);
        $this->assertDatabaseMissing('conversation_participants', ['user_id' => $secondMember->id, 'left_at' => null]);

        $this->postJson("/api/messaging/conversations/{$publicId}/transfer-ownership", [
            'user_id' => $firstMember->id,
        ])->assertOk();
        $this->assertDatabaseHas('conversation_participants', ['user_id' => $firstMember->id, 'role' => 'owner']);
    }

    public function test_reacting_to_an_old_message_exposes_it_as_an_updated_message(): void
    {
        [$sender, $recipient] = User::factory()->count(2)->create(['active' => true]);
        Sanctum::actingAs($sender);
        $conversation = $this->postJson('/api/messaging/conversations/direct', ['user_id' => $recipient->id])->json('data.public_id');
        $message = $this->postJson("/api/messaging/conversations/{$conversation}/messages", ['body' => 'Mensaje anterior'])->assertCreated()->json('data.public_id');
        $checkpoint = now()->toIso8601String();

        $this->travel(2)->seconds();
        Sanctum::actingAs($recipient);
        $this->postJson("/api/messaging/messages/{$message}/reactions", ['reaction' => config('messaging.reactions')[0]])->assertOk();

        Sanctum::actingAs($sender);
        $response = $this->getJson("/api/messaging/conversations/{$conversation}/messages?".http_build_query([
            'updated_since' => $checkpoint,
            'limit' => 100,
        ]))->assertOk();

        $this->assertNotEmpty($response->json('sync_cursor'));
        $this->assertSame($message, $response->json('data.0.public_id'));
        $this->assertSame(1, $response->json('data.0.reactions.0.count'));
        $this->travelBack();
    }

    public function test_sender_receives_acknowledgement_changes_through_incremental_polling(): void
    {
        [$sender, $recipient] = User::factory()->count(2)->create(['active' => true]);
        Sanctum::actingAs($sender);
        $conversation = $this->postJson('/api/messaging/conversations/direct', ['user_id' => $recipient->id])->json('data.public_id');
        $message = $this->postJson("/api/messaging/conversations/{$conversation}/messages", [
            'body' => 'Confirma la recepción de esta comunicación.',
            'formal' => true,
            'requires_acknowledgement' => true,
            'acknowledgement_due_at' => now()->addHour()->toIso8601String(),
        ])->assertCreated()->json('data.public_id');
        $checkpoint = now()->toIso8601String();

        $this->travel(2)->seconds();
        Sanctum::actingAs($recipient);
        $this->postJson("/api/messaging/messages/{$message}/acknowledge", [])->assertOk();

        Sanctum::actingAs($sender);
        $response = $this->getJson("/api/messaging/conversations/{$conversation}/messages?".http_build_query([
            'updated_since' => $checkpoint,
            'limit' => 100,
        ]))->assertOk();

        $response->assertJsonPath('data.0.public_id', $message)
            ->assertJsonPath('data.0.acknowledgement_summary.total', 1)
            ->assertJsonPath('data.0.acknowledgement_summary.acknowledged', 1)
            ->assertJsonPath('data.0.acknowledgement_summary.pending', 0)
            ->assertJsonPath('data.0.acknowledgement_summary.acknowledged_by.0.name', $recipient->name);
        $this->travelBack();
    }

    public function test_acknowledgement_messages_are_immutable_and_recipients_are_a_snapshot(): void
    {
        [$sender, $recipient] = User::factory()->count(2)->create(['active' => true]);
        Sanctum::actingAs($sender);
        $conversation = $this->postJson('/api/messaging/conversations/direct', ['user_id' => $recipient->id])->json('data.public_id');
        $message = $this->postJson("/api/messaging/conversations/{$conversation}/messages", ['body' => 'Contenido inmutable', 'requires_acknowledgement' => true, 'acknowledgement_due_at' => now()->addHour()])->assertCreated()->json('data.public_id');
        $this->patchJson("/api/messaging/messages/{$message}", ['body' => 'Cambio silencioso'])->assertForbidden();
        $model = Message::query()->where('public_id', $message)->firstOrFail();
        $this->assertSame(1, $model->recipient_count);
        $this->assertNotNull($model->content_hash);
        $this->assertSame(64, strlen($model->content_hash));
        $this->assertDatabaseHas('message_recipients', ['message_id' => $model->id, 'user_id' => $recipient->id, 'acknowledgement_required' => true]);
    }

    public function test_unauthenticated_user_cannot_access_messaging(): void
    {
        $this->getJson('/api/messaging/summary')->assertUnauthorized();
    }
}
