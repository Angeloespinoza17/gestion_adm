<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Models\UserUsageDaily;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuperAdminUsageLevelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-01 12:00:00');
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_successful_logins_and_authenticated_activity_are_recorded_without_request_content(): void
    {
        $user = User::factory()->create([
            'email' => 'funcionaria@cnsc.cl',
            'user_type' => 'staff',
            'active' => true,
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('user_usage_daily', [
            'user_id' => $user->id,
            'usage_date' => '2026-09-01',
            'login_count' => 1,
            'usage_count' => 1,
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'incorrecta',
        ])->assertOk();

        $this->assertSame(1, UserUsageDaily::query()->where('user_id', $user->id)->sum('login_count'));

        Cache::flush();
        Sanctum::actingAs($user);
        $this->getJson('/api/me/modules')->assertOk();
        $this->getJson('/api/me/modules')->assertOk();

        $row = UserUsageDaily::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(2, $row->usage_count);
        $this->assertSame(1, $row->login_count);
    }

    public function test_superadmin_can_filter_staff_and_students_and_select_an_individual_detail(): void
    {
        $superAdmin = $this->superAdmin();
        $staff = User::factory()->create([
            'name' => 'Camila Funcionaria',
            'email' => 'camila@cnsc.cl',
            'user_type' => 'staff',
            'active' => true,
        ]);
        $student = User::factory()->create([
            'name' => 'Antonia Estudiante',
            'email' => 'antonia@estudiantes.cnsc.cl',
            'user_type' => 'student',
            'active' => true,
        ]);

        UserUsageDaily::query()->create([
            'user_id' => $staff->id,
            'usage_date' => '2026-08-20',
            'login_count' => 3,
            'usage_count' => 8,
            'first_activity_at' => '2026-08-20 08:00:00',
            'last_activity_at' => '2026-08-20 16:00:00',
            'last_login_at' => '2026-08-20 08:00:00',
        ]);
        UserUsageDaily::query()->create([
            'user_id' => $staff->id,
            'usage_date' => '2026-08-28',
            'login_count' => 2,
            'usage_count' => 5,
            'first_activity_at' => '2026-08-28 09:00:00',
            'last_activity_at' => '2026-08-28 13:00:00',
            'last_login_at' => '2026-08-28 09:00:00',
        ]);
        UserUsageDaily::query()->create([
            'user_id' => $student->id,
            'usage_date' => '2026-08-30',
            'login_count' => 1,
            'usage_count' => 2,
            'first_activity_at' => '2026-08-30 18:00:00',
            'last_activity_at' => '2026-08-30 18:20:00',
            'last_login_at' => '2026-08-30 18:00:00',
        ]);

        Sanctum::actingAs($superAdmin);

        $this->getJson('/api/superadmin/usage-level/users?group=staff&period=30&search=Camila')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Camila Funcionaria')
            ->assertJsonPath('data.0.group', 'staff')
            ->assertJsonPath('data.0.login_count', 5)
            ->assertJsonPath('data.0.usage_count', 13)
            ->assertJsonPath('data.0.active_days', 2)
            ->assertJsonPath('summary.users_with_usage', 1)
            ->assertJsonPath('summary.adoption_rate', 100)
            ->assertJsonPath('groups.student.total_users', 1)
            ->assertJsonPath('groups.staff.total_users', 2)
            ->assertJsonPath('tracking.activity_window_minutes', 10);

        $this->getJson('/api/superadmin/usage-level/users?group=student&period=30')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Antonia Estudiante')
            ->assertJsonPath('data.0.group', 'student')
            ->assertJsonPath('data.0.login_count', 1);

        $this->getJson("/api/superadmin/usage-level/users/{$staff->id}?period=30")
            ->assertOk()
            ->assertJsonPath('user.name', 'Camila Funcionaria')
            ->assertJsonPath('user.group', 'staff')
            ->assertJsonPath('totals.login_count', 5)
            ->assertJsonPath('totals.usage_count', 13)
            ->assertJsonPath('totals.active_days', 2)
            ->assertJsonCount(2, 'timeline');
    }

    public function test_usage_level_is_exclusive_to_active_superadmins_and_validates_filters(): void
    {
        $this->getJson('/api/superadmin/usage-level/users')->assertUnauthorized();

        $regular = User::factory()->create(['active' => true]);
        Sanctum::actingAs($regular);
        $this->getJson('/api/superadmin/usage-level/users')->assertForbidden();

        $inactive = $this->superAdmin(active: false);
        Sanctum::actingAs($inactive);
        $this->getJson('/api/superadmin/usage-level/users')->assertForbidden();

        Sanctum::actingAs($this->superAdmin());
        $this->getJson('/api/superadmin/usage-level/users?group=apoderados&period=15')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['group', 'period']);
    }

    public function test_superadmin_navigation_exposes_usage_level_under_its_parent(): void
    {
        $user = $this->superAdmin();
        Sanctum::actingAs($user);

        $modules = collect($this->getJson('/api/me/modules')->assertOk()->json('data'));
        $parent = $modules->firstWhere('slug', 'superadmin');
        $tool = $modules->firstWhere('slug', 'superadmin_usage_level');

        $this->assertNotNull($parent);
        $this->assertNotNull($tool);
        $this->assertSame('/superadmin/nivel-uso', $tool['frontend_route']);
        $this->assertSame($parent['id'], $tool['parent_id']);
    }

    private function superAdmin(bool $active = true): User
    {
        $user = User::factory()->create(['active' => $active, 'user_type' => 'staff']);
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'active' => true],
        );
        $user->roles()->attach($role);

        return $user;
    }
}
