<?php

namespace Tests\Feature\Profile;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_identifies_an_institutional_photo_separately_from_a_custom_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('staff/17/profile/avatar.jpg', 'institutional-photo');

        $staff = Staff::query()->create([
            'full_name' => 'Ana Soto',
            'institutional_email' => 'ana.soto@example.test',
            'profile_photo_path' => 'staff/17/profile/avatar.jpg',
            'status' => 'activo',
            'active' => true,
        ]);
        $user = User::factory()->create([
            'name' => 'Ana Soto',
            'email' => 'ana.soto@example.test',
            'user_type' => 'staff',
            'staff_id' => $staff->id,
            'profile_photo_path' => null,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/me/profile')
            ->assertOk()
            ->assertJsonPath('data.profile_photo_source', 'staff')
            ->assertJsonPath('data.profile_photo_url', '/storage/staff/17/profile/avatar.jpg')
            ->assertJsonPath('data.staff.profile_photo_url', '/storage/staff/17/profile/avatar.jpg');

        $user->forceFill(['profile_photo_path' => 'users/9/profile/custom.webp'])->save();

        $this->getJson('/api/me/profile')
            ->assertOk()
            ->assertJsonPath('data.profile_photo_source', 'user')
            ->assertJsonPath('data.profile_photo_url', '/storage/users/9/profile/custom.webp')
            ->assertJsonPath('data.staff.profile_photo_url', '/storage/staff/17/profile/avatar.jpg');
    }

    public function test_removing_a_custom_photo_restores_the_institutional_photo_without_modifying_it(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('staff/21/profile/avatar.jpg', 'institutional-photo');
        Storage::disk('public')->put('users/12/profile/custom.jpg', 'custom-photo');

        $staff = Staff::query()->create([
            'full_name' => 'Mario Pérez',
            'profile_photo_path' => 'staff/21/profile/avatar.jpg',
            'status' => 'activo',
            'active' => true,
        ]);
        $user = User::factory()->create([
            'name' => 'Mario Pérez',
            'user_type' => 'staff',
            'staff_id' => $staff->id,
            'profile_photo_path' => 'users/12/profile/custom.jpg',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/me/profile', [
            'name' => 'Mario Pérez',
            'remove_photo' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.profile_photo_source', 'staff')
            ->assertJsonPath('data.profile_photo_url', '/storage/staff/21/profile/avatar.jpg');

        $this->assertNull($user->fresh()->profile_photo_path);
        Storage::disk('public')->assertMissing('users/12/profile/custom.jpg');
        Storage::disk('public')->assertExists('staff/21/profile/avatar.jpg');
        $this->assertSame('staff/21/profile/avatar.jpg', $staff->fresh()->profile_photo_path);
    }
}
