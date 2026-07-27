<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BackupManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'backup.disk' => 'local',
            'backup.path' => 'backups/database',
            'backup.retention_days' => 35,
        ]);
    }

    public function test_backup_endpoints_are_exclusive_to_super_admin(): void
    {
        $this->getJson('/api/admin/backups')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['active' => true]));

        $this->getJson('/api/admin/backups')->assertForbidden();
        $this->get('/api/admin/backups/mysql-2026-07-27_164328.sql.gz/download')->assertForbidden();
    }

    public function test_super_admin_can_list_available_database_backups(): void
    {
        $this->actingAsSuperAdmin();
        Storage::disk('local')->put('backups/database/mysql-2026-07-27_164328.sql.gz', 'backup-data');
        Storage::disk('local')->put('backups/database/sqlite-2026-07-20_030000.sqlite', 'sqlite-data');
        Storage::disk('local')->put('backups/database/notes.txt', 'not-a-backup');

        $response = $this->getJson('/api/admin/backups');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.count', 2)
            ->assertJsonPath('meta.retention_days', 35)
            ->assertJsonFragment([
                'filename' => 'mysql-2026-07-27_164328.sql.gz',
                'format' => 'SQL comprimido',
                'size_bytes' => 11,
            ])
            ->assertJsonMissing(['filename' => 'notes.txt']);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_super_admin_can_download_an_existing_backup(): void
    {
        $this->actingAsSuperAdmin();
        $filename = 'mysql-2026-07-27_164328.sql.gz';
        Storage::disk('local')->put("backups/database/{$filename}", 'backup-content');

        $response = $this->get("/api/admin/backups/{$filename}/download");

        $response->assertOk()
            ->assertDownload($filename)
            ->assertHeader('Content-Type', 'application/gzip');
        $this->assertSame('backup-content', $response->streamedContent());
    }

    public function test_unavailable_or_unsupported_files_cannot_be_downloaded(): void
    {
        $this->actingAsSuperAdmin();
        Storage::disk('local')->put('backups/database/private.txt', 'private');

        $this->get('/api/admin/backups/private.txt/download')->assertNotFound();
        $this->get('/api/admin/backups/mysql-missing.sql.gz/download')->assertNotFound();
    }

    private function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'active' => true],
        );
        $user->roles()->attach($role);
        Sanctum::actingAs($user);

        return $user;
    }
}
