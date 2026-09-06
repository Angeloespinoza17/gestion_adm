<?php

namespace Tests\Feature\Messaging;

use App\Events\Messaging\ConversationChanged;
use App\Events\Messaging\MessageAcknowledged;
use App\Events\Messaging\MessageCreated;
use App\Events\Messaging\MessageDeleted;
use App\Events\Messaging\MessageReactionUpdated;
use App\Events\Messaging\MessageRead;
use App\Events\Messaging\MessageUpdated;
use App\Jobs\Messaging\MarkMessageNotificationsRead;
use App\Jobs\Messaging\StoreNewMessageNotifications;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\Messaging\MessageRecipient;
use App\Models\Messaging\MessagingAuditEvent;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\SystemModule;
use App\Models\User;
use App\Notifications\Messaging\AcknowledgementReminderNotification;
use App\Notifications\Messaging\NewMessageNotification;
use App\Policies\MessagePolicy;
use App\Services\Messaging\ConversationService;
use App\Services\Messaging\MessageService;
use App\Services\Messaging\MessagingBroadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessagingModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_messaging_exposes_realtime_and_bounded_polling_configuration(): void
    {
        $user = User::factory()->state(['user_type' => 'staff'])->create(['active' => true]);
        Sanctum::actingAs($user);

        $this->getJson('/api/messaging/config')
            ->assertOk()
            ->assertJsonPath('realtime.enabled', false)
            ->assertJsonPath('realtime.connection_grace_ms', 10000)
            ->assertJsonPath('polling.enabled', true)
            ->assertJsonPath('polling.interval_ms', 60000)
            ->assertJsonPath('polling.active_interval_ms', 30000)
            ->assertJsonPath('polling.max_interval_ms', 120000)
            ->assertJsonPath('polling.reconciliation_interval_ms', 300000)
            ->assertJsonPath('polling.jitter_ratio', 0.2)
            ->assertJsonMissingPath('realtime.secret')
            ->assertJsonMissingPath('realtime.app_id');

        $this->enableRealtimeForTest();
        $this->getJson('/api/messaging/config')
            ->assertOk()
            ->assertJsonPath('realtime.enabled', true)
            ->assertJsonPath('realtime.key', 'test-key');

        config()->set('queue.default', 'sync');
        $this->getJson('/api/messaging/config')
            ->assertOk()
            ->assertJsonPath('realtime.enabled', false);
    }

    public function test_feature_flag_and_active_account_are_enforced_before_messaging_handlers(): void
    {
        $active = User::factory()->state(['user_type' => 'staff'])->create(['active' => true]);
        Sanctum::actingAs($active);
        config()->set('messaging.enabled', false);

        $this->getJson('/api/messaging/config')
            ->assertStatus(503)
            ->assertJsonPath('code', 'MESSAGING_DISABLED');

        config()->set('messaging.enabled', true);
        $inactive = User::factory()->state(['user_type' => 'staff'])->create(['active' => false]);
        Sanctum::actingAs($inactive);

        $this->getJson('/api/messaging/summary')
            ->assertForbidden()
            ->assertJsonPath('code', 'MESSAGING_USER_INACTIVE');
    }

    public function test_every_messaging_api_route_keeps_authentication_and_staff_gate_middleware(): void
    {
        $messagingRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/messaging'));

        $this->assertNotEmpty($messagingRoutes);
        $messagingRoutes->each(function ($route): void {
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth:sanctum', $middleware, $route->uri().' debe exigir autenticación.');
            $this->assertContains('messaging.available', $middleware, $route->uri().' debe exigir identidad de funcionario.');
        });
    }

    public function test_messaging_staff_scope_matches_the_fail_closed_identity_rule(): void
    {
        $users = collect([
            'modern_staff' => User::factory()->state(['user_type' => 'staff'])->create(['active' => true]),
            'legacy_null' => User::factory()->state(['user_type' => 'staff'])->create(['active' => true, 'user_type' => null, 'staff_id' => $this->staffProfileId('Legado nulo')]),
            'legacy_empty' => User::factory()->state(['user_type' => 'staff'])->create(['active' => true, 'user_type' => '', 'staff_id' => $this->staffProfileId('Legado vacío')]),
            'student' => User::factory()->state(['user_type' => 'staff'])->create(['active' => true, 'user_type' => 'student', 'staff_id' => $this->staffProfileId('Conflicto estudiante'), 'student_id' => $this->studentProfileId()]),
            'guardian' => User::factory()->state(['user_type' => 'staff'])->create(['active' => true, 'user_type' => 'guardian', 'staff_id' => $this->staffProfileId('Conflicto apoderado'), 'guardian_id' => 9001]),
            'apoderado' => User::factory()->state(['user_type' => 'staff'])->create(['active' => true, 'user_type' => 'apoderado', 'staff_id' => $this->staffProfileId('Conflicto apoderado legado'), 'guardian_id' => 9002]),
            'preview' => User::factory()->state(['user_type' => 'staff'])->create(['active' => true, 'user_type' => 'role_preview', 'staff_id' => $this->staffProfileId('Vista previa')]),
            'uppercase_staff' => User::factory()->state(['user_type' => 'staff'])->create(['active' => true, 'user_type' => 'STAFF', 'staff_id' => $this->staffProfileId('Tipo no canónico')]),
            'noncanonical_label' => User::factory()->state(['user_type' => 'staff'])->create(['active' => true, 'user_type' => 'funcionario']),
            'inactive_staff' => User::factory()->state(['user_type' => 'staff'])->create(['active' => false]),
        ]);
        $eligibleIds = User::query()->messagingStaff()->pluck('id');

        $users->each(function (User $user, string $case) use ($eligibleIds): void {
            $this->assertSame(
                $user->canUseMessaging(),
                $eligibleIds->contains($user->id),
                "El método y el scope deben coincidir para {$case}."
            );
        });

        $this->assertTrue($users['modern_staff']->canUseMessaging());
        $this->assertTrue($users['legacy_null']->canUseMessaging());
        $this->assertTrue($users['legacy_empty']->canUseMessaging());
        $this->assertFalse($users['student']->canUseMessaging());
        $this->assertFalse($users['guardian']->canUseMessaging());
        $this->assertFalse($users['apoderado']->canUseMessaging());
        $this->assertFalse($users['preview']->canUseMessaging());
        $this->assertFalse($users['uppercase_staff']->canUseMessaging());
        $this->assertFalse($users['noncanonical_label']->canUseMessaging());
        $this->assertFalse($users['inactive_staff']->canUseMessaging());
    }

    public function test_only_staff_accounts_receive_messaging_access_markers_and_search_results(): void
    {
        $staff = User::factory()->state(['user_type' => 'staff'])->create(['active' => true, 'name' => 'Funcionario Buscable']);
        $legacyStaff = User::factory()->state(['user_type' => 'staff'])->create(['active' => true, 'user_type' => null, 'staff_id' => $this->staffProfileId('Legado buscable'), 'name' => 'Legado Buscable']);
        $student = User::factory()->state(['user_type' => 'staff'])->create(['active' => true, 'user_type' => 'student', 'student_id' => $this->studentProfileId(), 'name' => 'Estudiante Buscable']);
        $guardian = User::factory()->state(['user_type' => 'staff'])->create(['active' => true, 'user_type' => 'guardian', 'guardian_id' => 9101, 'name' => 'Apoderado Buscable']);
        $inactiveStaff = User::factory()->state(['user_type' => 'staff'])->create(['active' => false, 'name' => 'Inactivo Buscable']);
        $actor = User::factory()->state(['user_type' => 'staff'])->create(['active' => true, 'name' => 'Actor Funcionario']);

        Sanctum::actingAs($actor);
        $this->getJson('/api/messaging/config')
            ->assertOk()
            ->assertJsonPath('user.is_staff', true);
        $permissions = collect($this->getJson('/api/me/permissions')->assertOk()->json('data'));
        $this->assertTrue($permissions->contains('__staff__'));

        $search = $this->getJson('/api/messaging/users/search?query=Buscable')
            ->assertOk()
            ->json('data');
        $this->assertEqualsCanonicalizing([$staff->id, $legacyStaff->id], collect($search)->pluck('id')->all());
        $this->assertTrue(collect($search)->every(fn (array $result) => $result['is_staff'] === true));

        foreach ([$student, $guardian] as $nonStaff) {
            Sanctum::actingAs($nonStaff);
            $this->getJson('/api/messaging/config')
                ->assertForbidden()
                ->assertJsonPath('code', 'MESSAGING_STAFF_ONLY');
            $this->assertNotContains('__staff__', $this->getJson('/api/me/permissions')->assertOk()->json('data'));
        }

        Sanctum::actingAs($inactiveStaff);
        $this->getJson('/api/messaging/config')
            ->assertForbidden()
            ->assertJsonPath('code', 'MESSAGING_USER_INACTIVE');
        $this->assertNotContains('__staff__', $this->getJson('/api/me/permissions')->assertOk()->json('data'));

        $superAdminRole = Role::query()->where('slug', 'super_admin')->firstOrFail();
        $student->roles()->attach($superAdminRole);
        Sanctum::actingAs($student->fresh());
        $superAdminPermissions = $this->getJson('/api/me/permissions')->assertOk()->json('data');
        $this->assertContains('__superadmin__', $superAdminPermissions);
        $this->assertNotContains('__staff__', $superAdminPermissions);
        $this->getJson('/api/messaging/config')
            ->assertForbidden()
            ->assertJsonPath('code', 'MESSAGING_STAFF_ONLY');

        $messagingModule = SystemModule::query()->updateOrCreate(
            ['slug' => 'messaging'],
            ['name' => 'Mensajería', 'frontend_route' => '/mensajeria', 'active' => true]
        );
        SystemModule::query()->create([
            'name' => 'Submódulo de mensajería',
            'slug' => 'messaging_child_test',
            'parent_id' => $messagingModule->id,
            'active' => true,
        ]);
        SystemModule::query()->create([
            'name' => 'Módulo no sensible',
            'slug' => 'unrelated_module_test',
            'active' => true,
        ]);
        $moduleSlugs = collect($this->getJson('/api/me/modules')->assertOk()->json('data'))->pluck('slug');
        $this->assertFalse($moduleSlugs->contains('messaging'));
        $this->assertFalse($moduleSlugs->contains('messaging_child_test'));
        $this->assertTrue($moduleSlugs->contains('unrelated_module_test'));

        Sanctum::actingAs($actor);
        $staffModuleSlugs = collect($this->getJson('/api/me/modules')->assertOk()->json('data'))->pluck('slug');
        $this->assertTrue($staffModuleSlugs->contains('messaging'));
    }

    public function test_non_staff_selection_channel_jobs_and_fanout_are_rejected_or_filtered(): void
    {
        config()->set('messaging.realtime.enabled', false);
        Queue::fake();
        $owner = User::factory()->state(['user_type' => 'staff'])->create(['active' => true]);
        $member = User::factory()->state(['user_type' => 'staff'])->create(['active' => true]);
        $reclassified = User::factory()->state(['user_type' => 'staff'])->create(['active' => true]);
        $student = User::factory()->state(['user_type' => 'staff'])->create(['active' => true, 'user_type' => 'student', 'student_id' => $this->studentProfileId()]);
        $inactiveStaff = User::factory()->state(['user_type' => 'staff'])->create(['active' => false]);

        Sanctum::actingAs($owner);
        $this->postJson('/api/messaging/conversations/direct', ['user_id' => $student->id])
            ->assertUnprocessable();
        $this->postJson('/api/messaging/conversations/group', [
            'title' => 'Selección inválida',
            'user_ids' => [$member->id, $student->id],
        ])->assertUnprocessable();

        $conversation = app(ConversationService::class)->group($owner, [
            'title' => 'Revalidación laboral',
            'user_ids' => [$member->id, $reclassified->id],
        ]);
        $this->postJson("/api/messaging/conversations/{$conversation->public_id}/participants", [
            'user_ids' => [$student->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('user_ids');
        $this->postJson("/api/messaging/conversations/{$conversation->public_id}/participants", [
            'user_ids' => [$inactiveStaff->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('user_ids');

        $message = app(MessageService::class)->send($conversation, $owner, [
            'body' => 'Se revalidará al consumir la cola.',
        ]);
        $this->assertSame(2, $message->recipient_count);
        /** @var StoreNewMessageNotifications $notificationJob */
        $notificationJob = Queue::pushed(StoreNewMessageNotifications::class)->sole();
        $this->enableRealtimeForTest();
        $queuedRealtimeEvent = new ConversationChanged([$reclassified->id], [
            'action' => 'queued_before_reclassification',
            'conversation_id' => $conversation->public_id,
        ]);
        $reclassified->update(['user_type' => 'student', 'student_id' => $this->studentProfileId(), 'staff_id' => null]);

        $this->assertSame([], $queuedRealtimeEvent->broadcastOn());
        $legacyRealtimeEvent = new MessageCreated(
            $conversation->public_id,
            ['public_id' => $message->public_id],
            [$reclassified->id]
        );
        $this->assertSame([], $legacyRealtimeEvent->broadcastOn());

        $queuedMembershipEvent = new ConversationChanged([$member->id], [
            'action' => 'message_created',
            'conversation_id' => $conversation->public_id,
        ]);
        $conversation->participants()->where('user_id', $member->id)->update(['left_at' => now()]);
        $this->assertSame([], $queuedMembershipEvent->broadcastOn());
        $removedMembershipEvent = new ConversationChanged([$member->id], [
            'action' => 'participant_removed',
            'conversation_id' => $conversation->public_id,
            'removed_user_id' => $member->id,
        ]);
        $this->assertSame(
            ['private-messaging.user.'.$member->id],
            array_map(fn ($channel) => (string) $channel, $removedMembershipEvent->broadcastOn())
        );
        $conversation->participants()->where('user_id', $member->id)->update(['left_at' => null]);
        $this->assertSame([], $removedMembershipEvent->broadcastOn(), 'Un tombstone encolado no debe purgar a un funcionario reactivado.');
        $removedReclassifiedEvent = new ConversationChanged([$reclassified->id], [
            'action' => 'participant_removed',
            'conversation_id' => $conversation->public_id,
            'removed_user_id' => $reclassified->id,
        ]);
        $conversation->participants()->where('user_id', $reclassified->id)->update(['left_at' => now()]);
        $this->assertSame([], $removedReclassifiedEvent->broadcastOn(), 'Una cuenta reclasificada nunca debe conservar el canal de mensajería.');

        $this->assertSame([], (new NewMessageNotification($message))->via($reclassified->fresh()));
        $this->assertSame([], (new AcknowledgementReminderNotification($message))->via($reclassified->fresh()));
        $this->assertSame(['database'], (new NewMessageNotification($message))->via($member));
        $this->assertSame(['database'], (new AcknowledgementReminderNotification($message))->via($member));

        $notificationJob->handle();
        $this->assertDatabaseHas('notifications', [
            'type' => NewMessageNotification::class,
            'notifiable_id' => $member->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'type' => NewMessageNotification::class,
            'notifiable_id' => $reclassified->id,
        ]);
        $this->assertSame(2, MessageRecipient::query()->where('message_id', $message->id)->whereNotNull('notification_sent_at')->count());

        Sanctum::actingAs($owner);
        $historicalReceipts = $this->getJson("/api/messaging/messages/{$message->public_id}/receipts?limit=100")
            ->assertOk()
            ->assertJsonPath('summary.total', 2)
            ->json('data');
        $this->assertTrue(collect($historicalReceipts)->pluck('user_id')->contains($reclassified->id));

        Event::fake([ConversationChanged::class]);
        app(MessagingBroadcaster::class)->conversationChanged($conversation, 'staff_revalidated');
        Event::assertDispatched(ConversationChanged::class, fn (ConversationChanged $event) => $event->recipientIds === [$owner->id, $member->id]);
        $this->assertFalse(app(MessagePolicy::class)->receipts($reclassified->fresh(), $message));

        Sanctum::actingAs($owner);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-messaging.user.'.$owner->id,
        ])->assertOk();
        Sanctum::actingAs($student);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-messaging.user.'.$student->id,
        ])->assertForbidden();

        $onlyRecipient = User::factory()->state(['user_type' => 'staff'])->create(['active' => true]);
        $direct = app(ConversationService::class)->direct($owner, $onlyRecipient);
        $onlyRecipient->update(['user_type' => 'guardian', 'guardian_id' => 9201, 'staff_id' => null]);
        Sanctum::actingAs($owner);
        $this->postJson("/api/messaging/conversations/{$direct->public_id}/messages", [
            'body' => 'No debe persistirse sin destinatario funcionario.',
        ])->assertStatus(409);
        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $direct->id,
            'body' => 'No debe persistirse sin destinatario funcionario.',
        ]);
    }

    public function test_queued_notification_job_stops_when_messaging_is_disabled(): void
    {
        config()->set('messaging.realtime.enabled', false);
        Queue::fake();
        [$sender, $recipient] = User::factory()->state(['user_type' => 'staff'])->count(2)->create(['active' => true]);
        $conversation = app(ConversationService::class)->direct($sender, $recipient);
        $message = app(MessageService::class)->send($conversation, $sender, [
            'body' => 'No debe notificar después de deshabilitar el módulo.',
        ]);
        /** @var StoreNewMessageNotifications $job */
        $job = Queue::pushed(StoreNewMessageNotifications::class)->sole();
        $queuedEvent = new ConversationChanged([$recipient->id], [
            'action' => 'message_created',
            'conversation_id' => $conversation->public_id,
        ]);

        config()->set('messaging.enabled', false);
        $job->handle();

        $this->assertDatabaseMissing('notifications', [
            'type' => NewMessageNotification::class,
            'notifiable_id' => $recipient->id,
        ]);
        $this->assertNull($message->recipients()->where('user_id', $recipient->id)->value('notification_sent_at'));
        $this->assertSame([], $queuedEvent->broadcastOn());
        $this->assertSame([], (new NewMessageNotification($message))->via($recipient));
    }

    public function test_legacy_queued_events_without_recipient_snapshot_fail_closed(): void
    {
        $this->enableRealtimeForTest();
        $eventStates = [
            MessageAcknowledged::class => [
                'conversationId' => (string) Str::ulid(),
                'acknowledgement' => [],
            ],
            MessageCreated::class => [
                'conversationId' => (string) Str::ulid(),
                'message' => [],
            ],
            MessageDeleted::class => [
                'conversationId' => (string) Str::ulid(),
                'messageId' => (string) Str::ulid(),
                'deletedAt' => now()->toIso8601String(),
            ],
            MessageReactionUpdated::class => [
                'conversationId' => (string) Str::ulid(),
                'reaction' => [],
            ],
            MessageRead::class => [
                'conversationId' => (string) Str::ulid(),
                'receipt' => [],
            ],
            MessageUpdated::class => [
                'conversationId' => (string) Str::ulid(),
                'message' => [],
            ],
        ];

        foreach ($eventStates as $eventClass => $state) {
            $legacyEvent = (new \ReflectionClass($eventClass))->newInstanceWithoutConstructor();
            foreach ($state as $property => $value) {
                $legacyEvent->{$property} = $value;
            }

            $restoredEvent = unserialize(serialize($legacyEvent), ['allowed_classes' => [$eventClass]]);

            $this->assertFalse(isset($restoredEvent->recipientIds));
            $this->assertSame([], $restoredEvent->broadcastOn(), $eventClass.' debe descartar el job legado sin audiencia.');
        }
    }

    public function test_authenticated_active_users_create_one_direct_conversation_and_third_parties_cannot_view_it(): void
    {
        [$one, $two, $third] = User::factory()->state(['user_type' => 'staff'])->count(3)->create(['active' => true]);
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
        [$sender, $recipient] = User::factory()->state(['user_type' => 'staff'])->count(2)->create(['active' => true]);
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

    public function test_summary_uses_one_query_and_excludes_departed_participants(): void
    {
        [$sender, $activeMember, $departedMember] = User::factory()->state(['user_type' => 'staff'])->count(3)->create(['active' => true]);
        Sanctum::actingAs($sender);
        $conversation = $this->postJson('/api/messaging/conversations/group', [
            'title' => 'Resumen eficiente',
            'user_ids' => [$activeMember->id, $departedMember->id],
        ])->assertCreated()->json('data.public_id');
        $this->postJson("/api/messaging/conversations/{$conversation}/messages", [
            'body' => 'Comunicación pendiente',
            'formal' => true,
            'requires_acknowledgement' => true,
            'acknowledgement_due_at' => now()->addHour()->toIso8601String(),
        ])->assertCreated();

        $timestamp = now()->subDay();
        $conversationDatabaseId = (int) DB::table('conversations')->where('public_id', $conversation)->value('id');
        $historicalPublicIds = collect(range(1, 180))->map(fn () => (string) Str::ulid());
        DB::table('messages')->insert($historicalPublicIds->map(fn (string $publicId) => [
            'public_id' => $publicId,
            'conversation_id' => $conversationDatabaseId,
            'sender_id' => $sender->id,
            'sender_display_name_snapshot' => $sender->name,
            'body' => 'Mensaje histórico ya leído',
            'recipient_count' => 1,
            'sent_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->all());
        $historicalMessageIds = DB::table('messages')->whereIn('public_id', $historicalPublicIds)->pluck('id');
        DB::table('message_recipients')->insert($historicalMessageIds->map(fn (int $messageId) => [
            'message_id' => $messageId,
            'user_id' => $activeMember->id,
            'recipient_display_name_snapshot' => $activeMember->name,
            'recipient_reference_snapshot' => $activeMember->email,
            'acknowledgement_required' => false,
            'delivered_at' => $timestamp,
            'read_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->all());
        $this->deleteJson("/api/messaging/conversations/{$conversation}/participants/{$departedMember->id}")->assertOk();

        Sanctum::actingAs($activeMember);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->getJson('/api/messaging/summary')->assertOk();
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $response
            ->assertJsonPath('unread_messages', 1)
            ->assertJsonPath('unread_conversations', 1)
            ->assertJsonPath('pending_acknowledgements', 1)
            ->assertJsonPath('overdue_acknowledgements', 0);
        $this->assertSame(
            1,
            $queries->filter(fn (array $query) => str_contains(strtolower($query['query']), 'message_recipients'))->count(),
            'El resumen debe resolverse con una sola consulta agregada sobre mensajería.'
        );
        $this->assertLessThanOrEqual(3, $queries->count(), 'El endpoint completo debe conservar un presupuesto acotado.');
        $aggregateQuery = $queries->first(fn (array $query) => str_contains(strtolower($query['query']), 'message_recipients'));
        $aggregateSql = strtolower($aggregateQuery['query']);
        $this->assertStringNotContainsString('case when', $aggregateSql);
        $this->assertStringContainsString('"mr"."read_at" is null', $aggregateSql);
        $this->assertStringContainsString('"mr"."acknowledged_at" is null', $aggregateSql);
        $this->assertStringContainsString('"mr"."waived_at" is null', $aggregateSql);
        if (DB::getDriverName() === 'sqlite') {
            $queryPlan = collect(DB::select('EXPLAIN QUERY PLAN '.$aggregateQuery['query'], $aggregateQuery['bindings']))
                ->pluck('detail')
                ->map(fn ($detail) => strtolower((string) $detail))
                ->implode("\n");
            $this->assertStringContainsString('using', $queryPlan);
            $this->assertStringContainsString('mr', $queryPlan);
        }

        Sanctum::actingAs($departedMember);
        $this->getJson('/api/messaging/summary')
            ->assertOk()
            ->assertJsonPath('unread_messages', 0)
            ->assertJsonPath('unread_conversations', 0)
            ->assertJsonPath('pending_acknowledgements', 0)
            ->assertJsonPath('overdue_acknowledgements', 0);
    }

    public function test_repeated_read_is_a_write_free_noop_after_the_watermark_advances(): void
    {
        [$sender, $recipient] = User::factory()->state(['user_type' => 'staff'])->count(2)->create(['active' => true]);
        Sanctum::actingAs($sender);
        $conversation = $this->postJson('/api/messaging/conversations/direct', ['user_id' => $recipient->id])->assertCreated()->json('data.public_id');
        $message = $this->postJson("/api/messaging/conversations/{$conversation}/messages", ['body' => 'Leer una sola vez'])->assertCreated()->json('data.public_id');

        Sanctum::actingAs($recipient);
        $this->enableRealtimeForTest();
        Event::fake([ConversationChanged::class]);
        $this->postJson("/api/messaging/conversations/{$conversation}/read", ['through_message_id' => $message])
            ->assertOk()
            ->assertJsonPath('data.updated', 1);
        $auditCount = MessagingAuditEvent::query()->where('event_type', 'message_read')->count();
        Event::assertDispatchedTimes(ConversationChanged::class, 1);

        $this->travel(2)->seconds();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->postJson("/api/messaging/conversations/{$conversation}/read", ['through_message_id' => $message])
            ->assertOk()
            ->assertJsonPath('data.updated', 0);
        $queries = strtolower(collect(DB::getQueryLog())->pluck('query')->implode("\n"));
        DB::disableQueryLog();

        $this->assertSame($auditCount, MessagingAuditEvent::query()->where('event_type', 'message_read')->count());
        Event::assertDispatchedTimes(ConversationChanged::class, 1);
        $this->assertStringNotContainsString('messaging_audit_events', $queries);
        $this->assertStringNotContainsString('notifications', $queries);
        $this->assertStringNotContainsString('update "conversation_participants"', $queries);
        $this->travelBack();
    }

    public function test_read_dispatches_notification_cleanup_after_the_watermark_transaction(): void
    {
        [$sender, $recipient] = User::factory()->state(['user_type' => 'staff'])->count(2)->create(['active' => true]);
        Sanctum::actingAs($sender);
        $conversation = $this->postJson('/api/messaging/conversations/direct', [
            'user_id' => $recipient->id,
        ])->assertCreated()->json('data.public_id');
        $message = $this->postJson("/api/messaging/conversations/{$conversation}/messages", [
            'body' => 'La limpieza de alertas no debe prolongar el lock.',
        ])->assertCreated()->json('data.public_id');
        $this->assertSame(1, $recipient->fresh()->unreadNotifications()->count());

        Queue::fake();
        Sanctum::actingAs($recipient);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->postJson("/api/messaging/conversations/{$conversation}/read", [
            'through_message_id' => $message,
        ])->assertOk()->assertJsonPath('data.updated', 1);
        $requestQueries = strtolower(collect(DB::getQueryLog())->pluck('query')->implode("\n"));
        DB::disableQueryLog();

        Queue::assertPushed(MarkMessageNotificationsRead::class, 1);
        $this->assertStringNotContainsString('notifications', $requestQueries);
        $this->assertStringNotContainsString('json_extract', $requestQueries);
        /** @var MarkMessageNotificationsRead $job */
        $job = Queue::pushed(MarkMessageNotificationsRead::class)->sole();
        $job->handle();
        $this->assertSame(0, $recipient->fresh()->unreadNotifications()->count());
    }

    public function test_large_conversation_message_page_has_a_bounded_query_budget(): void
    {
        $sender = User::factory()->state(['user_type' => 'staff'])->create(['active' => true]);
        $members = User::factory()->state(['user_type' => 'staff'])->count(20)->create(['active' => true]);
        Sanctum::actingAs($sender);
        $conversation = $this->postJson('/api/messaging/conversations/group', [
            'title' => 'Canal amplio',
            'user_ids' => $members->pluck('id')->all(),
        ])->assertCreated()->json('data.public_id');

        foreach (range(1, 6) as $number) {
            $this->postJson("/api/messaging/conversations/{$conversation}/messages", [
                'body' => "Comunicación {$number}",
                'formal' => true,
                'requires_acknowledgement' => true,
                'acknowledgement_due_at' => now()->addHour()->toIso8601String(),
            ])->assertCreated();
        }

        Sanctum::actingAs($members->first());
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->getJson("/api/messaging/conversations/{$conversation}/messages?limit=10")->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(6, $response->json('data'));
        $response->assertJsonPath('data.0.acknowledgement_summary.total', 20);
        $this->assertLessThanOrEqual(12, $queryCount, 'La página no debe sumar consultas por mensaje ni por destinatario.');
    }

    public function test_message_reactions_are_grouped_without_hydrating_every_reaction_row(): void
    {
        Queue::fake();
        [$sender, $viewer] = User::factory()->state(['user_type' => 'staff'])->count(2)->create(['active' => true]);
        $reactors = User::factory()->state(['user_type' => 'staff'])->count(120)->create(['active' => true]);
        Sanctum::actingAs($sender);
        $conversation = $this->postJson('/api/messaging/conversations/direct', ['user_id' => $viewer->id])->assertCreated()->json('data.public_id');
        $messagePublicId = $this->postJson("/api/messaging/conversations/{$conversation}/messages", [
            'body' => 'Mensaje con alta participación',
        ])->assertCreated()->json('data.public_id');
        $message = Message::query()->where('public_id', $messagePublicId)->firstOrFail();
        $timestamp = now();
        $rows = [];
        foreach ($reactors as $reactor) {
            foreach (array_slice(config('messaging.reactions'), 0, 3) as $reaction) {
                $rows[] = ['message_id' => $message->id, 'user_id' => $reactor->id, 'reaction' => $reaction, 'created_at' => $timestamp, 'updated_at' => $timestamp];
            }
        }
        $rows[] = ['message_id' => $message->id, 'user_id' => $viewer->id, 'reaction' => config('messaging.reactions')[0], 'created_at' => $timestamp, 'updated_at' => $timestamp];
        foreach (array_chunk($rows, 200) as $rowChunk) {
            DB::table('message_reactions')->insert($rowChunk);
        }

        Sanctum::actingAs($viewer);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->getJson("/api/messaging/conversations/{$conversation}/messages")->assertOk();
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $reactions = collect($response->json('data.0.reactions'))->keyBy('reaction');
        $this->assertSame(121, $reactions->get(config('messaging.reactions')[0])['count']);
        $this->assertTrue($reactions->get(config('messaging.reactions')[0])['mine']);
        $this->assertSame(120, $reactions->get(config('messaging.reactions')[1])['count']);
        $this->assertFalse($reactions->get(config('messaging.reactions')[1])['mine']);
        $reactionQueries = $queries->filter(fn (array $query) => str_contains(strtolower($query['query']), 'message_reactions'));
        $this->assertCount(1, $reactionQueries);
        $this->assertStringContainsString('group by', strtolower($reactionQueries->first()['query']));
        $this->assertLessThanOrEqual(12, $queries->count());
    }

    public function test_conversation_index_only_hydrates_four_participant_previews(): void
    {
        $owner = User::factory()->state(['user_type' => 'staff'])->create(['active' => true]);
        $members = User::factory()->state(['user_type' => 'staff'])->count(20)->create(['active' => true]);
        Sanctum::actingAs($owner);
        $this->postJson('/api/messaging/conversations/group', [
            'title' => 'Equipo numeroso',
            'user_ids' => $members->pluck('id')->all(),
        ])->assertCreated();
        $conversation = Conversation::query()->where('title', 'Equipo numeroso')->firstOrFail();
        $this->postJson("/api/messaging/conversations/{$conversation->public_id}/messages", [
            'body' => str_repeat('Vista previa institucional extensa. ', 500),
        ])->assertCreated();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->getJson('/api/messaging/conversations')->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(4, $response->json('data.0.participants'));
        $this->assertLessThanOrEqual(240, mb_strwidth($response->json('data.0.last_message.body')));
        $this->assertLessThanOrEqual(8, $queryCount, 'El listado debe tener un número constante de consultas.');
    }

    public function test_large_conversation_detail_and_participant_directory_stay_bounded(): void
    {
        $owner = User::factory()->state(['user_type' => 'staff'])->create(['active' => true]);
        $members = User::factory()->state(['user_type' => 'staff'])->count(130)->create(['active' => true]);
        Sanctum::actingAs($owner);
        $creation = $this->postJson('/api/messaging/conversations/group', [
            'title' => 'Directorio institucional amplio',
            'user_ids' => $members->pluck('id')->all(),
        ])->assertCreated();
        $conversation = $creation->json('data.public_id');
        $creation
            ->assertJsonPath('data.participant_count', 131)
            ->assertJsonPath('data.current_participant.role', 'owner');
        $this->assertLessThanOrEqual(5, count($creation->json('data.participants')));

        Queue::fake();
        Sanctum::actingAs($members->first());
        $message = $this->postJson("/api/messaging/conversations/{$conversation}/messages", [
            'body' => 'Mensaje pendiente para comprobar unread_count.',
            'formal' => true,
            'requires_acknowledgement' => true,
            'acknowledgement_due_at' => now()->addHour()->toIso8601String(),
        ])->assertCreated()->json('data.public_id');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $receiptPage = $this->getJson("/api/messaging/messages/{$message}/receipts?limit=100")
            ->assertOk()
            ->assertJsonPath('summary.total', 130)
            ->assertJsonPath('summary.acknowledged', 0);
        $receiptPageQueries = collect(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertCount(100, $receiptPage->json('data'));
        $this->assertNotNull($receiptPage->json('next_cursor'));
        $secondReceiptPage = $this->getJson("/api/messaging/messages/{$message}/receipts?".http_build_query([
            'limit' => 100,
            'cursor' => $receiptPage->json('next_cursor'),
        ]))->assertOk();
        $this->assertCount(30, $secondReceiptPage->json('data'));
        $this->assertNull($secondReceiptPage->json('next_cursor'));
        $this->assertLessThanOrEqual(5, $receiptPageQueries->count(), 'Los acuses deben paginarse sin hidratar toda la audiencia.');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $csv = $this->get("/api/messaging/messages/{$message}/receipt-export")
            ->assertOk()
            ->streamedContent();
        $exportQueries = collect(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertStringContainsString('Comunicación', $csv);
        $this->assertSame(133, substr_count(trim($csv), "\n") + 1);
        $this->assertLessThanOrEqual(5, $exportQueries->count(), 'La exportación debe recorrer los acuses por cursor.');

        Sanctum::actingAs($owner);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $detail = $this->getJson("/api/messaging/conversations/{$conversation}")->assertOk();
        $detailQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $detail
            ->assertJsonPath('data.participant_count', 131)
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.current_participant.role', 'owner');
        $this->assertLessThanOrEqual(5, count($detail->json('data.participants')));
        $this->assertLessThanOrEqual(10, $detailQueryCount);

        $departed = $members->last();
        $this->deleteJson("/api/messaging/conversations/{$conversation}/participants/{$departed->id}")->assertOk();
        $firstPage = $this->getJson("/api/messaging/conversations/{$conversation}/participants?limit=100")
            ->assertOk()
            ->assertJsonPath('participant_count', 130);
        $this->assertCount(100, $firstPage->json('data'));
        $this->assertSame(
            ['id', 'name', 'email', 'photo', 'role', 'can_write', 'left_at'],
            array_keys($firstPage->json('data.0'))
        );
        $secondPage = $this->getJson("/api/messaging/conversations/{$conversation}/participants?".http_build_query([
            'limit' => 100,
            'cursor' => $firstPage->json('next_cursor'),
        ]))->assertOk();
        $participants = collect($firstPage->json('data'))->concat($secondPage->json('data'));
        $this->assertCount(130, $participants);
        $this->assertFalse($participants->pluck('id')->contains($departed->id));
    }

    public function test_large_group_and_message_fanout_use_bounded_database_batches_and_jobs(): void
    {
        config()->set('messaging.announcements.chunk_size', 20);
        config()->set('messaging.realtime.enabled', false);
        $owner = User::factory()->state(['user_type' => 'staff'])->create(['active' => true]);
        $members = User::factory()->state(['user_type' => 'staff'])->count(80)->create(['active' => true]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $conversation = app(ConversationService::class)->group($owner, [
            'title' => 'Comunicación institucional masiva',
            'user_ids' => $members->pluck('id')->all(),
        ]);
        $groupQueries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertDatabaseCount('conversation_participants', 81);
        $this->assertSame(5, $groupQueries->filter(
            fn (array $query) => str_contains(strtolower($query['query']), 'insert into "conversation_participants"')
        )->count());
        $this->assertLessThanOrEqual(14, $groupQueries->count(), 'Crear el grupo no debe ejecutar una inserción por participante.');

        $inactiveRecipient = $members->last();
        $inactiveRecipient->update(['active' => false]);
        Queue::fake();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $message = app(MessageService::class)->send($conversation, $owner, [
            'body' => 'Información para toda la comunidad.',
        ]);
        $messageQueries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(79, $message->recipient_count);
        $this->assertDatabaseCount('message_recipients', 79);
        $this->assertDatabaseMissing('message_recipients', [
            'message_id' => $message->id,
            'user_id' => $inactiveRecipient->id,
        ]);
        $this->assertSame(4, $messageQueries->filter(
            fn (array $query) => str_contains(strtolower($query['query']), 'insert into "message_recipients"')
        )->count());
        $this->assertLessThanOrEqual(24, $messageQueries->count(), 'Enviar no debe ejecutar una inserción ni encolar un job por destinatario.');
        Queue::assertPushed(StoreNewMessageNotifications::class, 4);

        Queue::pushed(StoreNewMessageNotifications::class)->each(function (StoreNewMessageNotifications $job): void {
            $receiptCount = MessageRecipient::query()
                ->where('message_id', $job->messageId)
                ->whereBetween('user_id', [$job->firstUserId, $job->lastUserId])
                ->count();

            $this->assertLessThanOrEqual(20, $receiptCount);
            $this->assertSame('notifications', $job->queue);
        });
    }

    public function test_adding_many_participants_uses_bulk_queries_and_one_traceable_audit(): void
    {
        config()->set('messaging.announcements.chunk_size', 20);
        config()->set('messaging.realtime.enabled', false);
        $owner = User::factory()->state(['user_type' => 'staff'])->create(['active' => true]);
        $departed = User::factory()->state(['user_type' => 'staff'])->create(['active' => true]);
        $members = User::factory()->state(['user_type' => 'staff'])->count(80)->create(['active' => true]);
        $inactive = $members->last();
        $inactive->update(['active' => false]);
        Sanctum::actingAs($owner);
        $conversation = $this->postJson('/api/messaging/conversations/group', [
            'title' => 'Altas masivas acotadas',
            'user_ids' => [$departed->id],
        ])->assertCreated()->json('data.public_id');
        $this->deleteJson("/api/messaging/conversations/{$conversation}/participants/{$departed->id}")->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->postJson("/api/messaging/conversations/{$conversation}/participants", [
            'user_ids' => $members->where('id', '!=', $inactive->id)->pluck('id')->push($departed->id)->push($owner->id)->all(),
        ])->assertOk();
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertJsonPath('participant_count', 81);
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => DB::table('conversations')->where('public_id', $conversation)->value('id'),
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
        $this->assertFalse(collect($response->json('data'))->pluck('id')->contains($inactive->id));
        $audit = MessagingAuditEvent::query()
            ->where('event_type', 'participant_added')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame(80, $audit->metadata['count']);
        $this->assertCount(80, $audit->metadata['user_ids']);
        $this->assertContains($departed->id, $audit->metadata['reactivated_user_ids']);
        $this->assertNotContains($inactive->id, $audit->metadata['user_ids']);
        $this->assertSame(1, $queries->filter(
            fn (array $query) => str_contains(strtolower($query['query']), 'insert into "messaging_audit_events"')
        )->count());
        $this->assertLessThanOrEqual(4, $queries->filter(
            fn (array $query) => str_contains(strtolower($query['query']), 'insert')
                && str_contains(strtolower($query['query']), 'conversation_participants')
        )->count());
        $this->assertLessThanOrEqual(30, $queries->count());
    }

    public function test_notification_batch_job_is_deterministic_and_idempotent(): void
    {
        config()->set('messaging.announcements.chunk_size', 20);
        config()->set('messaging.realtime.enabled', false);
        Queue::fake();
        $owner = User::factory()->state(['user_type' => 'staff'])->create(['active' => true]);
        $members = User::factory()->state(['user_type' => 'staff'])->count(7)->create(['active' => true]);
        $conversation = app(ConversationService::class)->group($owner, [
            'title' => 'Lote idempotente',
            'user_ids' => $members->pluck('id')->all(),
        ]);
        $message = app(MessageService::class)->send($conversation, $owner, [
            'body' => 'Mensaje sin notificaciones duplicadas.',
        ]);
        /** @var StoreNewMessageNotifications $job */
        $job = Queue::pushed(StoreNewMessageNotifications::class)->sole();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $job->handle();
        $firstRunQueries = collect(DB::getQueryLog());
        DB::disableQueryLog();
        $notificationIds = DB::table('notifications')->orderBy('id')->pluck('id')->all();

        $this->assertCount(7, $notificationIds);
        $this->assertSame(7, MessageRecipient::query()->whereNotNull('notification_sent_at')->count());
        $this->assertLessThanOrEqual(6, $firstRunQueries->count());
        $payload = json_decode((string) DB::table('notifications')->value('data'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($message->public_id, $payload['message_id']);
        $this->assertSame($conversation->public_id, $payload['conversation_id']);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $job->handle();
        $retryQueries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($notificationIds, DB::table('notifications')->orderBy('id')->pluck('id')->all());
        $this->assertDatabaseCount('notifications', 7);
        $retrySql = strtolower($retryQueries->pluck('query')->implode("\n"));
        $this->assertStringNotContainsString('insert into "notifications"', $retrySql);
        $this->assertStringNotContainsString('update "message_recipients"', $retrySql);
    }

    public function test_notification_job_skips_departed_and_inactive_recipients_without_retrying_them(): void
    {
        config()->set('messaging.announcements.chunk_size', 20);
        config()->set('messaging.realtime.enabled', false);
        Queue::fake();
        $owner = User::factory()->state(['user_type' => 'staff'])->create(['active' => true]);
        [$activeMember, $departedMember, $inactiveMember] = User::factory()->state(['user_type' => 'staff'])->count(3)->create(['active' => true]);
        $conversation = app(ConversationService::class)->group($owner, [
            'title' => 'Revalidación al consumir cola',
            'user_ids' => [$activeMember->id, $departedMember->id, $inactiveMember->id],
        ]);
        $message = app(MessageService::class)->send($conversation, $owner, [
            'body' => 'Contenido que no debe filtrarse tras retirar acceso.',
        ]);
        /** @var StoreNewMessageNotifications $job */
        $job = Queue::pushed(StoreNewMessageNotifications::class)->sole();
        $conversation->participants()->where('user_id', $departedMember->id)->update(['left_at' => now()]);
        $inactiveMember->update(['active' => false]);
        MessageRecipient::query()
            ->where('message_id', $message->id)
            ->where('user_id', $activeMember->id)
            ->update(['read_at' => now()]);

        $job->handle();

        $this->assertDatabaseHas('notifications', [
            'type' => NewMessageNotification::class,
            'notifiable_id' => $activeMember->id,
        ]);
        $this->assertNotNull(DB::table('notifications')
            ->where('notifiable_id', $activeMember->id)
            ->value('read_at'), 'Un job tardío no debe recrear como no leída una notificación cuyo recibo ya fue leído.');
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $departedMember->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $inactiveMember->id]);
        $this->assertSame(1, DB::table('notifications')->count());
        $this->assertSame(3, MessageRecipient::query()
            ->where('message_id', $message->id)
            ->whereNotNull('notification_sent_at')
            ->count());

        $job->handle();
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_active_user_can_create_a_group_with_members_and_admin_only_writing(): void
    {
        [$owner, $firstMember, $secondMember, $newMember] = User::factory()->state(['user_type' => 'staff'])->count(4)->create(['active' => true]);
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
        [$sender, $recipient] = User::factory()->state(['user_type' => 'staff'])->count(2)->create(['active' => true]);
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

    public function test_incremental_message_sync_returns_paginated_tombstones_for_offline_deletes(): void
    {
        [$sender, $recipient] = User::factory()->state(['user_type' => 'staff'])->count(2)->create(['active' => true]);
        Sanctum::actingAs($sender);
        $conversation = $this->postJson('/api/messaging/conversations/direct', [
            'user_id' => $recipient->id,
        ])->assertCreated()->json('data.public_id');
        $messageIds = collect(range(1, 3))->map(fn (int $number) => $this
            ->postJson("/api/messaging/conversations/{$conversation}/messages", [
                'body' => "Mensaje eliminado sin conexión {$number}",
            ])
            ->assertCreated()
            ->json('data.public_id'));
        $checkpoint = now()->toIso8601String();

        $this->travel(2)->seconds();
        $messageIds->each(fn (string $messageId) => $this
            ->deleteJson("/api/messaging/messages/{$messageId}")
            ->assertOk());

        Sanctum::actingAs($recipient);
        $first = $this->getJson("/api/messaging/conversations/{$conversation}/messages?".http_build_query([
            'updated_since' => $checkpoint,
            'limit' => 2,
        ]))->assertOk();
        $second = $this->getJson("/api/messaging/conversations/{$conversation}/messages?".http_build_query([
            'updated_since' => $checkpoint,
            'limit' => 2,
            'before' => $first->json('before'),
        ]))->assertOk();

        $deletedIds = collect($first->json('deleted'))->concat($second->json('deleted'))->pluck('public_id');
        $this->assertEmpty($first->json('data'));
        $this->assertEmpty($second->json('data'));
        $this->assertTrue($first->json('has_more'));
        $this->assertFalse($second->json('has_more'));
        $this->assertCount(3, $deletedIds);
        $this->assertEqualsCanonicalizing($messageIds->all(), $deletedIds->all());
        $this->assertSame(['public_id', 'deleted_at'], array_keys($first->json('deleted.0')));
        $this->travelBack();
    }

    public function test_sender_can_recover_acknowledgement_changes_with_the_incremental_endpoint(): void
    {
        [$sender, $recipient] = User::factory()->state(['user_type' => 'staff'])->count(2)->create(['active' => true]);
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
        [$sender, $recipient] = User::factory()->state(['user_type' => 'staff'])->count(2)->create(['active' => true]);
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

    public function test_departed_participant_can_no_longer_view_conversation_or_messages(): void
    {
        [$owner, $member, $departed] = User::factory()->state(['user_type' => 'staff'])->count(3)->create(['active' => true]);
        Sanctum::actingAs($owner);
        $conversation = $this->postJson('/api/messaging/conversations/group', [
            'title' => 'Canal privado',
            'user_ids' => [$member->id, $departed->id],
        ])->assertCreated()->json('data.public_id');
        $message = $this->postJson("/api/messaging/conversations/{$conversation}/messages", [
            'body' => 'Información reservada',
        ])->assertCreated()->json('data.public_id');
        $this->deleteJson("/api/messaging/conversations/{$conversation}/participants/{$departed->id}")->assertOk();

        Sanctum::actingAs($departed);
        $this->getJson("/api/messaging/conversations/{$conversation}")->assertNotFound();
        $this->getJson("/api/messaging/conversations/{$conversation}/messages")->assertNotFound();
        $this->getJson("/api/messaging/messages/{$message}")->assertNotFound();
        $this->assertEmpty($this->getJson('/api/messaging/conversations')->assertOk()->json('data'));
    }

    public function test_departed_participant_cannot_acknowledge_edit_or_delete_historical_messages(): void
    {
        [$owner, $member] = User::factory()->state(['user_type' => 'staff'])->count(2)->create(['active' => true]);
        Sanctum::actingAs($owner);
        $conversation = $this->postJson('/api/messaging/conversations/group', [
            'title' => 'Integridad tras retiro',
            'user_ids' => [$member->id],
        ])->assertCreated()->json('data.public_id');
        $ackMessage = $this->postJson("/api/messaging/conversations/{$conversation}/messages", [
            'body' => 'Confirma antes de ser retirado.',
            'formal' => true,
            'requires_acknowledgement' => true,
            'acknowledgement_due_at' => now()->addHour()->toIso8601String(),
        ])->assertCreated()->json('data.public_id');

        Sanctum::actingAs($member);
        $ownMessage = $this->postJson("/api/messaging/conversations/{$conversation}/messages", [
            'body' => 'Mensaje propio previo al retiro.',
        ])->assertCreated()->json('data.public_id');

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/messaging/conversations/{$conversation}/participants/{$member->id}")->assertOk();

        Sanctum::actingAs($member);
        $this->postJson("/api/messaging/messages/{$ackMessage}/acknowledge", [])->assertNotFound();
        $this->patchJson("/api/messaging/messages/{$ownMessage}", ['body' => 'Edición no autorizada'])->assertNotFound();
        $this->deleteJson("/api/messaging/messages/{$ownMessage}")->assertNotFound();
        $this->assertDatabaseHas('messages', [
            'public_id' => $ownMessage,
            'body' => 'Mensaje propio previo al retiro.',
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('message_recipients', [
            'message_id' => Message::query()->where('public_id', $ackMessage)->value('id'),
            'user_id' => $member->id,
            'acknowledged_at' => null,
        ]);
    }

    public function test_message_history_uses_a_non_overlapping_cursor_and_reports_when_more_exists(): void
    {
        [$sender, $recipient] = User::factory()->state(['user_type' => 'staff'])->count(2)->create(['active' => true]);
        Sanctum::actingAs($sender);
        $conversation = $this->postJson('/api/messaging/conversations/direct', ['user_id' => $recipient->id])->json('data.public_id');
        foreach (range(1, 45) as $number) {
            $this->postJson("/api/messaging/conversations/{$conversation}/messages", ['body' => "Mensaje {$number}"])->assertCreated();
        }

        $first = $this->getJson("/api/messaging/conversations/{$conversation}/messages?limit=20")->assertOk();
        $second = $this->getJson("/api/messaging/conversations/{$conversation}/messages?".http_build_query([
            'limit' => 20,
            'before' => $first->json('before'),
        ]))->assertOk();
        $third = $this->getJson("/api/messaging/conversations/{$conversation}/messages?".http_build_query([
            'limit' => 20,
            'before' => $second->json('before'),
        ]))->assertOk();

        $ids = collect($first->json('data'))->merge($second->json('data'))->merge($third->json('data'))->pluck('public_id');
        $this->assertTrue($first->json('has_more'));
        $this->assertTrue($second->json('has_more'));
        $this->assertFalse($third->json('has_more'));
        $this->assertCount(45, $ids);
        $this->assertCount(45, $ids->unique());
    }

    public function test_creating_a_message_dispatches_one_compact_user_channel_signal(): void
    {
        [$sender, $recipient] = User::factory()->state(['user_type' => 'staff'])->count(2)->create(['active' => true]);
        Sanctum::actingAs($sender);
        config()->set('messaging.realtime.enabled', false);
        $conversation = $this->postJson('/api/messaging/conversations/direct', ['user_id' => $recipient->id])->json('data.public_id');
        $this->enableRealtimeForTest();
        Event::fake([ConversationChanged::class]);

        $message = $this->postJson("/api/messaging/conversations/{$conversation}/messages", [
            'body' => str_repeat('Mensaje en tiempo real. ', 700),
        ])->assertCreated()->json('data.public_id');

        Event::assertDispatched(ConversationChanged::class, fn (ConversationChanged $event) => $event->change['action'] === 'message_created'
            && $event->change['conversation_id'] === $conversation
            && $event->change['message_reference']['public_id'] === $message
            && array_keys($event->change['message_reference']) === ['public_id', 'conversation_id', 'sent_at', 'edited_at', 'version']
            && mb_strwidth($event->change['last_message']['body']) <= 240
            && strlen(json_encode($event->broadcastWith(), JSON_THROW_ON_ERROR)) < 2000
            && collect($event->broadcastOn())->every(fn ($channel) => str_starts_with((string) $channel, 'private-messaging.user.')));
        Event::assertDispatchedTimes(ConversationChanged::class, 1);
    }

    public function test_updating_a_message_broadcasts_only_a_compact_reference_and_realtime_fails_closed(): void
    {
        [$sender, $recipient] = User::factory()->state(['user_type' => 'staff'])->count(2)->create(['active' => true]);
        Sanctum::actingAs($sender);
        config()->set('messaging.realtime.enabled', false);
        $conversation = $this->postJson('/api/messaging/conversations/direct', ['user_id' => $recipient->id])->json('data.public_id');
        Event::fake([ConversationChanged::class]);
        $message = $this->postJson("/api/messaging/conversations/{$conversation}/messages", [
            'body' => 'No debe publicarse sin transporte listo.',
        ])->assertCreated()->json('data.public_id');
        Event::assertNotDispatched(ConversationChanged::class);

        $this->enableRealtimeForTest();
        $this->patchJson("/api/messaging/messages/{$message}", [
            'body' => str_repeat('Contenido editado extenso. ', 600),
        ])->assertOk();

        Event::assertDispatched(ConversationChanged::class, fn (ConversationChanged $event) => $event->change['action'] === 'message_updated'
            && $event->change['conversation_id'] === $conversation
            && $event->change['message_reference']['public_id'] === $message
            && $event->change['message_reference']['version'] === 2
            && array_keys($event->change['message_reference']) === ['public_id', 'conversation_id', 'sent_at', 'edited_at', 'version']
            && strlen(json_encode($event->broadcastWith(), JSON_THROW_ON_ERROR)) < 2000);
        Event::assertDispatchedTimes(ConversationChanged::class, 1);

        config()->set('broadcasting.connections.reverb.secret', '');
        $this->postJson("/api/messaging/conversations/{$conversation}/messages", [
            'body' => 'Tampoco debe publicarse con credenciales incompletas.',
        ])->assertCreated();
        Event::assertDispatchedTimes(ConversationChanged::class, 1);

        config()->set('broadcasting.connections.reverb.secret', 'test-secret');
        config()->set('queue.default', 'sync');
        $this->postJson("/api/messaging/conversations/{$conversation}/messages", [
            'body' => 'La cola sync también debe cerrar realtime.',
        ])->assertCreated();
        Event::assertDispatchedTimes(ConversationChanged::class, 1);
    }

    public function test_individual_receipt_and_reaction_signals_stay_constant_for_one_thousand_participants(): void
    {
        config()->set('messaging.realtime.enabled', false);
        Queue::fake();
        $owner = User::factory()->state(['user_type' => 'staff'])->create(['active' => true]);
        $memberIds = $this->insertActiveUsers(999, 'individual-signal-audience');
        $conversation = app(ConversationService::class)->group($owner, [
            'title' => 'Audiencia individual masiva',
            'user_ids' => $memberIds,
        ]);
        $message = app(MessageService::class)->send($conversation, $owner, [
            'body' => 'Señal individual acotada.',
            'formal' => true,
            'requires_acknowledgement' => true,
            'acknowledgement_due_at' => now()->addHour(),
        ])->load('conversation');
        $receipt = MessageRecipient::query()
            ->where('message_id', $message->id)
            ->where('user_id', $memberIds[0])
            ->firstOrFail();
        $receipt->acknowledged_at = now();
        $this->enableRealtimeForTest();
        Event::fake([ConversationChanged::class]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $broadcaster = app(MessagingBroadcaster::class);
        $broadcaster->messageRead($conversation, $receipt->user_id, $message->public_id, 1, $owner->id);
        $broadcaster->messageAcknowledged($message, $receipt);
        $broadcaster->reactionUpdated($message, $receipt->user_id, config('messaging.reactions')[0], true);
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $events = Event::dispatched(ConversationChanged::class)->map(fn (array $arguments) => $arguments[0]);
        $this->assertCount(3, $events);
        $events->each(function (ConversationChanged $event) use ($owner, $receipt): void {
            $this->assertLessThanOrEqual(2, count($event->recipientIds));
            $this->assertEqualsCanonicalizing([$owner->id, $receipt->user_id], $event->recipientIds);
        });
        $acknowledgement = $events->first(fn (ConversationChanged $event) => $event->change['action'] === 'message_acknowledged');
        $this->assertSame(
            ['message_id', 'user_id', 'acknowledged_at'],
            array_keys($acknowledgement->change['acknowledgement'])
        );
        $this->assertArrayNotHasKey('summary', $acknowledgement->change['acknowledgement']);
        $this->assertLessThanOrEqual(5, $queries->count(), 'Una acción individual no debe crecer con toda la audiencia.');
    }

    public function test_conversation_change_audience_is_split_under_reverb_request_budget(): void
    {
        config()->set('messaging.realtime.enabled', false);
        $owner = User::factory()->state(['user_type' => 'staff'])->create(['active' => true]);
        $companion = User::factory()->state(['user_type' => 'staff'])->create(['active' => true]);
        $conversation = app(ConversationService::class)->group($owner, [
            'title' => 'Canal de prueba de audiencia',
            'user_ids' => [$companion->id],
        ]);
        $audienceIds = array_merge(
            [$owner->id, $companion->id],
            $this->insertActiveUsers(4998, 'reverb-audience')
        );
        $this->enableRealtimeForTest();
        Event::fake([ConversationChanged::class]);

        app(MessagingBroadcaster::class)->conversationChanged(
            $conversation,
            'audience_budget_test',
            ['preview' => str_repeat('x', 240)],
            $audienceIds
        );

        $events = Event::dispatched(ConversationChanged::class)->map(fn (array $arguments) => $arguments[0]);
        $this->assertCount(50, $events);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $events->each(function (ConversationChanged $event): void {
            $this->assertLessThanOrEqual(100, count($event->recipientIds));
            $wirePayload = json_encode([
                'channels' => array_map(fn ($channel) => (string) $channel, $event->broadcastOn()),
                'payload' => $event->broadcastWith(),
            ], JSON_THROW_ON_ERROR);
            $this->assertLessThan(9000, strlen($wirePayload));
        });
        $broadcastQueries = collect(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertCount(
            $events->count(),
            $broadcastQueries,
            'Cada lote realtime debe revalidar identidad y membresía en una sola consulta.'
        );
    }

    public function test_messaging_channel_authorization_fails_closed_with_disabled_flags(): void
    {
        $this->enableRealtimeForTest();
        $user = User::factory()->state(['user_type' => 'staff'])->create(['active' => true]);
        Sanctum::actingAs($user);

        config()->set('messaging.enabled', false);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-messaging.user.'.$user->id,
        ])->assertForbidden();

        config()->set('messaging.enabled', true);
        config()->set('messaging.realtime.enabled', false);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-messaging.user.'.$user->id,
        ])->assertForbidden();
    }

    private function enableRealtimeForTest(): void
    {
        config()->set('messaging.enabled', true);
        config()->set('messaging.realtime.enabled', true);
        config()->set('broadcasting.default', 'reverb');
        config()->set('broadcasting.connections.reverb.driver', 'reverb');
        config()->set('broadcasting.connections.reverb.key', 'test-key');
        config()->set('broadcasting.connections.reverb.app_id', 'test-app-id');
        config()->set('broadcasting.connections.reverb.secret', 'test-secret');
        config()->set('queue.default', 'database');
        require base_path('routes/channels.php');
    }

    private function insertActiveUsers(int $count, string $prefix): array
    {
        $timestamp = now();
        foreach (array_chunk(range(1, $count), 500) as $numberChunk) {
            DB::table('users')->insert(array_map(fn (int $number) => [
                'name' => 'Usuario '.$number,
                'email' => $prefix.'-'.$number.'@example.test',
                'email_verified_at' => $timestamp,
                'password' => 'not-used-in-tests',
                'active' => true,
                'user_type' => 'staff',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ], $numberChunk));
        }

        return DB::table('users')
            ->where('email', 'like', $prefix.'-%@example.test')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function staffProfileId(string $name): int
    {
        return (int) Staff::query()->create([
            'full_name' => $name,
            'status' => 'activo',
            'active' => true,
        ])->id;
    }

    private function studentProfileId(): int
    {
        return (int) StudentProfile::factory()->create()->id;
    }
}
