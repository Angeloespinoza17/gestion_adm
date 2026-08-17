<?php

namespace Tests\Feature;

use App\Models\User;
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

        $this->getJson('/api/internal-notifications?limit=10&page=2')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.total', 23)
            ->assertJsonPath('total_count', 23)
            ->assertJsonPath('unread_count', 12)
            ->assertJsonPath('read_count', 11);

        $this->getJson('/api/internal-notifications?status=unread&limit=50')
            ->assertOk()
            ->assertJsonPath('meta.total', 12)
            ->assertJsonCount(12, 'data');

        $this->getJson('/api/internal-notifications?search=Agenda%20especial&limit=50')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Agenda especial del equipo');
    }

    private function createNotification(User $user, string $title, bool $read): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'Tests\\Notification',
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
    }
}
