<?php

namespace Tests\Feature\Messaging;

use App\Jobs\Messaging\SendAcknowledgementReminderBatch;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\Messaging\MessageRecipient;
use App\Models\Messaging\MessagingAuditEvent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use App\Notifications\Messaging\AcknowledgementReminderNotification;
use App\Services\Messaging\AcknowledgementReminderService;
use App\Services\Messaging\ConversationService;
use App\Services\Messaging\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessagingReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_messaging_does_not_queue_or_write_automatic_reminders(): void
    {
        Queue::fake();
        [$sender, $recipient] = User::factory()->state(['user_type' => 'staff'])->count(2)->create(['active' => true]);
        [, $message] = $this->pendingAcknowledgement($sender, [$recipient]);

        config()->set('messaging.enabled', false);

        $this->artisan('messaging:send-acknowledgement-reminders')
            ->expectsOutput('Mensajería deshabilitada; no se encolaron recordatorios.')
            ->assertSuccessful();

        Queue::assertNotPushed(SendAcknowledgementReminderBatch::class);
        $this->assertNull($message->recipients()->sole()->last_reminded_at);
        $this->assertSame(0, MessagingAuditEvent::query()->where('event_type', 'reminder_sent')->count());
        $this->assertSame(0, DB::table('notifications')->where('type', AcknowledgementReminderNotification::class)->count());
    }

    public function test_automatic_reminders_only_target_active_users_with_current_membership_and_are_idempotent(): void
    {
        Queue::fake();
        [$sender, $eligible, $departed, $inactive] = User::factory()->state(['user_type' => 'staff'])->count(4)->create(['active' => true]);
        [$conversation, $message] = $this->pendingAcknowledgement($sender, [$eligible, $departed, $inactive]);
        $conversation->participants()->where('user_id', $departed->id)->update(['left_at' => now()]);
        $inactive->update(['active' => false]);

        $this->artisan('messaging:send-acknowledgement-reminders')
            ->expectsOutput('Recordatorios encolados: 1')
            ->assertSuccessful();

        /** @var SendAcknowledgementReminderBatch $job */
        $job = Queue::pushed(SendAcknowledgementReminderBatch::class)->sole();
        $eligibleReceipt = $message->recipients()->where('user_id', $eligible->id)->sole();
        $this->assertSame([$eligibleReceipt->id], $job->receiptIds);
        $this->assertSame('notifications', $job->queue);

        $job->handle(app(AcknowledgementReminderService::class));
        $job->handle(app(AcknowledgementReminderService::class));

        $this->assertDatabaseHas('notifications', [
            'type' => AcknowledgementReminderNotification::class,
            'notifiable_id' => $eligible->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'type' => AcknowledgementReminderNotification::class,
            'notifiable_id' => $departed->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'type' => AcknowledgementReminderNotification::class,
            'notifiable_id' => $inactive->id,
        ]);
        $this->assertSame(1, DB::table('notifications')->where('type', AcknowledgementReminderNotification::class)->count());
        $this->assertSame(1, MessagingAuditEvent::query()->where('event_type', 'reminder_sent')->count());
        $this->assertSame(1, $eligibleReceipt->fresh()->reminder_count);
        $this->assertNull($message->recipients()->where('user_id', $departed->id)->sole()->last_reminded_at);
        $this->assertNull($message->recipients()->where('user_id', $inactive->id)->sole()->last_reminded_at);
    }

    public function test_manual_reminder_has_specific_rbac_is_queued_and_duplicate_jobs_send_once(): void
    {
        Queue::fake();
        [$sender, $recipient, $manager, $viewer] = User::factory()->state(['user_type' => 'staff'])->count(4)->create(['active' => true]);
        [$conversation, $message] = $this->pendingAcknowledgement($sender, [$recipient, $manager]);
        $conversation->participants()->where('user_id', $manager->id)->update(['role' => 'admin']);

        $role = Role::query()->create([
            'name' => 'Consulta de acuses',
            'slug' => 'messaging_receipt_viewer_test',
            'active' => true,
        ]);
        $role->permissions()->attach(Permission::query()->where('slug', 'messaging.view_receipts')->sole());
        $viewer->roles()->attach($role);

        Sanctum::actingAs($viewer);
        $this->postJson('/api/messaging/messages/'.$message->public_id.'/reminders', [
            'user_ids' => [$recipient->id],
        ])->assertForbidden();
        Queue::assertNotPushed(SendAcknowledgementReminderBatch::class);

        $role->permissions()->attach(Permission::query()->where('slug', 'messaging.send_reminder')->sole());
        $this->postJson('/api/messaging/messages/'.$message->public_id.'/reminders', [
            'user_ids' => [$recipient->id],
        ])->assertAccepted()->assertJsonPath('data.queued', 1);

        Sanctum::actingAs($manager);
        $this->postJson('/api/messaging/messages/'.$message->public_id.'/reminders', [
            'user_ids' => [$recipient->id],
        ])->assertAccepted()->assertJsonPath('data.queued', 1);

        Sanctum::actingAs($sender);
        $this->postJson('/api/messaging/messages/'.$message->public_id.'/reminders', [
            'user_ids' => [$recipient->id],
        ])->assertAccepted()->assertJsonPath('data.queued', 1);

        $this->assertSame(0, DB::table('notifications')->where('type', AcknowledgementReminderNotification::class)->count());
        $this->assertSame(0, MessagingAuditEvent::query()->where('event_type', 'reminder_sent')->count());

        $jobs = Queue::pushed(SendAcknowledgementReminderBatch::class);
        $this->assertCount(3, $jobs);
        $jobs->each(fn (SendAcknowledgementReminderBatch $job) => $job->handle(app(AcknowledgementReminderService::class)));

        $this->assertSame(1, DB::table('notifications')->where('type', AcknowledgementReminderNotification::class)->count());
        $this->assertSame(1, MessagingAuditEvent::query()->where('event_type', 'reminder_sent')->count());
        $this->assertSame(1, MessageRecipient::query()
            ->where('message_id', $message->id)
            ->where('user_id', $recipient->id)
            ->value('reminder_count'));
    }

    public function test_queued_reminder_revalidates_staff_identity_before_writing(): void
    {
        Queue::fake();
        [$sender, $recipient] = User::factory()->state(['user_type' => 'staff'])->count(2)->create(['active' => true]);
        [, $message] = $this->pendingAcknowledgement($sender, [$recipient]);

        $this->artisan('messaging:send-acknowledgement-reminders')->assertSuccessful();
        /** @var SendAcknowledgementReminderBatch $job */
        $job = Queue::pushed(SendAcknowledgementReminderBatch::class)->sole();
        $recipient->update([
            'user_type' => 'student',
            'student_id' => StudentProfile::factory()->create()->id,
            'staff_id' => null,
        ]);

        $job->handle(app(AcknowledgementReminderService::class));

        $receipt = $message->recipients()->where('user_id', $recipient->id)->sole();
        $this->assertSame(0, $receipt->fresh()->reminder_count);
        $this->assertNull($receipt->fresh()->last_reminded_at);
        $this->assertDatabaseMissing('notifications', [
            'type' => AcknowledgementReminderNotification::class,
            'notifiable_id' => $recipient->id,
        ]);
        $this->assertSame(0, MessagingAuditEvent::query()->where('event_type', 'reminder_sent')->count());
    }

    /**
     * @param  array<int, User>  $recipients
     * @return array{0: Conversation, 1: Message}
     */
    private function pendingAcknowledgement(User $sender, array $recipients): array
    {
        config()->set('messaging.enabled', true);
        config()->set('messaging.realtime.enabled', false);
        $conversation = app(ConversationService::class)->group($sender, [
            'title' => 'Recordatorios de prueba',
            'user_ids' => collect($recipients)->pluck('id')->all(),
        ]);
        $message = app(MessageService::class)->send($conversation, $sender, [
            'body' => 'Confirma la recepción de este mensaje.',
            'formal' => true,
            'requires_acknowledgement' => true,
            'acknowledgement_due_at' => now()->addHour(),
        ]);

        return [$conversation, $message];
    }
}
