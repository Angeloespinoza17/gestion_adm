<?php

namespace Tests\Feature;

use App\Models\StudentProfile;
use App\Models\User;
use App\Notifications\Messaging\AcknowledgementReminderNotification;
use App\Notifications\Messaging\NewMessageNotification;
use App\Notifications\OperationalEventNotification;
use App\Services\Notifications\OperationalNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InternalNotificationHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_paginate_filter_and_search_their_notification_history(): void
    {
        $user = User::factory()->create(['active' => true]);
        $other = User::factory()->create(['active' => true]);

        foreach (range(1, 23) as $index) {
            $this->createNotification($user, $index === 7 ? 'Agenda especial del equipo' : 'Aviso '.$index, $index % 2 === 0);
        }
        $this->createNotification($other, 'Notificación privada de otra persona', false);
        Sanctum::actingAs($user);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/internal-notifications?limit=10&page=2')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.total', 23)
            ->assertJsonPath('total_count', 23)
            ->assertJsonPath('unread_count', 12)
            ->assertJsonPath('read_count', 11);

        $aggregateQuery = collect(DB::getQueryLog())
            ->pluck('query')
            ->first(fn (string $sql) => str_contains(strtolower($sql), 'as total_count'));

        $this->assertNotNull($aggregateQuery);
        $this->assertStringNotContainsString('order by', strtolower($aggregateQuery));

        $this->getJson('/api/internal-notifications?status=unread&limit=50')
            ->assertOk()
            ->assertJsonPath('meta.total', 12)
            ->assertJsonCount(12, 'data');

        $this->getJson('/api/internal-notifications?search=Agenda%20especial&limit=50')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Agenda especial del equipo');
    }

    public function test_non_staff_user_cannot_see_or_mutate_historical_messaging_notifications(): void
    {
        $student = User::factory()->create([
            'active' => true,
            'user_type' => 'student',
            'student_id' => StudentProfile::factory()->create()->id,
        ]);
        $generalId = $this->createNotification($student, 'Aviso general visible', false);
        $messageId = $this->createNotification($student, 'Mensaje histórico oculto', false, NewMessageNotification::class);
        $reminderId = $this->createNotification($student, 'Recordatorio histórico oculto', false, AcknowledgementReminderNotification::class);
        Sanctum::actingAs($student);

        $this->getJson('/api/internal-notifications?limit=50')
            ->assertOk()
            ->assertJsonPath('total_count', 1)
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $generalId);

        $this->putJson("/api/internal-notifications/{$messageId}/read")->assertNotFound();
        $this->putJson('/api/internal-notifications/read-all')->assertOk()->assertJsonPath('unread_count', 0);

        $this->assertNotNull(DB::table('notifications')->where('id', $generalId)->value('read_at'));
        $this->assertNull(DB::table('notifications')->where('id', $messageId)->value('read_at'));
        $this->assertNull(DB::table('notifications')->where('id', $reminderId)->value('read_at'));
    }

    public function test_operational_notifications_are_encapsulated_and_idempotent_per_recipient(): void
    {
        $user = User::factory()->create(['active' => true]);
        $inactive = User::factory()->create(['active' => false]);
        $service = app(OperationalNotificationService::class);
        $eventKey = 'test.referral.created:77';
        $notification = new OperationalEventNotification(
            eventKey: $eventKey,
            eventType: 'test.referral.created',
            module: 'social_work',
            title: 'Nueva derivación',
            message: 'Existe una derivación pendiente de revisión.',
            resource: ['type' => 'test_referral', 'id' => 77, 'code' => 'DER-00077'],
            actionUrl: '/social-work/referrals',
            icon: 'bx bx-git-branch',
            priority: 'urgente',
            actor: $user,
            context: ['status' => 'enviada'],
        );

        $this->assertSame(1, $service->send([$user, $user, $inactive], $notification, $eventKey));
        $this->assertSame(0, $service->send($user, $notification, $eventKey));
        $this->assertDatabaseCount('notifications', 1);

        Sanctum::actingAs($user);
        $this->getJson('/api/internal-notifications')
            ->assertOk()
            ->assertJsonPath('data.0.module', 'social_work')
            ->assertJsonPath('data.0.priority', 'critica')
            ->assertJsonPath('data.0.event.schema', 'cnsc.operational-notification.v1')
            ->assertJsonPath('data.0.event.key', $eventKey)
            ->assertJsonPath('data.0.event.resource.code', 'DER-00077')
            ->assertJsonPath('data.0.action_url', '/social-work/referrals');
    }

    public function test_history_supports_legacy_url_and_rejects_external_action_urls(): void
    {
        $user = User::factory()->create(['active' => true]);
        $legacyId = $this->createNotification($user, 'Aviso legado', false);
        DB::table('notifications')->where('id', $legacyId)->update([
            'data' => json_encode([
                'title' => 'Aviso legado',
                'message' => 'Notificación anterior',
                'url' => '/psychology/referrals',
            ], JSON_THROW_ON_ERROR),
        ]);
        $externalId = $this->createNotification($user, 'Enlace externo', false);
        DB::table('notifications')->where('id', $externalId)->update([
            'data' => json_encode([
                'title' => 'Enlace externo',
                'message' => 'No debe navegar fuera del sistema',
                'action_url' => 'https://example.test/phishing',
            ], JSON_THROW_ON_ERROR),
        ]);

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/internal-notifications?limit=50')->assertOk();
        $items = collect($response->json('data'))->keyBy('id');

        $this->assertSame('/psychology/referrals', $items[$legacyId]['action_url']);
        $this->assertNull($items[$externalId]['action_url']);
    }

    private function createNotification(User $user, string $title, bool $read, string $type = 'Tests\\Notification'): string
    {
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id,
            'type' => $type,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'title' => $title,
                'message' => 'Detalle de la notificación '.$title,
                'icon' => 'bx bx-bell',
                'priority' => 'media',
                'action_url' => '/inicio',
            ], JSON_THROW_ON_ERROR),
            'read_at' => $read ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
