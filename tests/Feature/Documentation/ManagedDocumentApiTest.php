<?php

namespace Tests\Feature\Documentation;

use App\Models\ManagedDocument;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\SystemModule;
use App\Models\User;
use Database\Seeders\DocumentationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManagedDocumentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_documentation_routes_require_authentication_and_explicit_permissions(): void
    {
        $this->getJson('/api/documentation')->assertUnauthorized();

        Sanctum::actingAs($this->userWithPermissions(['documentation.view']));
        $document = $this->makeDocument();

        $this->getJson('/api/documentation')->assertOk();
        $this->postJson('/api/documentation', [])->assertForbidden();
        $this->deleteJson("/api/documentation/{$document->id}")->assertForbidden();
    }

    public function test_authorized_user_can_create_filter_and_download_a_private_document(): void
    {
        Storage::fake('local');
        $user = $this->userWithPermissions(['documentation.view', 'documentation.create']);
        Sanctum::actingAs($user);

        $documentId = $this->post('/api/documentation', [
            'title' => 'Proyecto Educativo Institucional',
            'category' => ManagedDocument::CATEGORY_EDUCATIONAL_PROJECT,
            'year' => 2023,
            'version' => 'Final',
            'description' => 'Documento institucional vigente.',
            'is_public' => '1',
            'is_active' => '1',
            'file' => UploadedFile::fake()->create('PEI CNSC 2023.pdf', 512, 'application/pdf'),
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Proyecto Educativo Institucional')
            ->assertJsonPath('data.category', 'proyecto-educativo')
            ->assertJsonPath('data.category_label', 'Proyecto educativo')
            ->assertJsonPath('data.year', 2023)
            ->assertJsonPath('data.is_public', true)
            ->assertJsonPath('data.created_by.id', $user->id)
            ->json('data.id');

        $document = ManagedDocument::query()->findOrFail($documentId);

        $this->assertStringStartsWith('documentation/proyecto-educativo/2023/', $document->file_path);
        $this->assertStringNotContainsString('PEI CNSC 2023', $document->file_path);
        Storage::disk('local')->assertExists($document->file_path);

        $this->getJson('/api/documentation?category=proyecto-educativo&year=2023&is_public=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $documentId);

        $this->get("/api/documentation/{$documentId}/download")
            ->assertOk()
            ->assertDownload('PEI CNSC 2023.pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_update_can_replace_the_file_and_delete_preserves_audit_metadata(): void
    {
        Storage::fake('local');
        $user = $this->userWithPermissions([
            'documentation.view',
            'documentation.create',
            'documentation.update',
            'documentation.delete',
        ]);
        Sanctum::actingAs($user);

        $documentId = $this->post('/api/documentation', [
            'title' => 'Reglamento original',
            'category' => ManagedDocument::CATEGORY_REGULATION,
            'year' => 2025,
            'version' => '1.0',
            'description' => null,
            'is_public' => false,
            'is_active' => true,
            'file' => UploadedFile::fake()->create('reglamento.pdf', 120, 'application/pdf'),
        ])->assertCreated()->json('data.id');

        $oldPath = ManagedDocument::query()->findOrFail($documentId)->file_path;

        $this->post("/api/documentation/{$documentId}", [
            'title' => 'Reglamento actualizado',
            'category' => ManagedDocument::CATEGORY_REGULATION,
            'year' => 2026,
            'version' => '2.0',
            'description' => 'Segunda versión.',
            'is_public' => '1',
            'is_active' => '1',
            'file' => UploadedFile::fake()->create('reglamento-2026.docx', 180, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Reglamento actualizado')
            ->assertJsonPath('data.original_name', 'reglamento-2026.docx')
            ->assertJsonPath('data.updated_by.id', $user->id);

        $document = ManagedDocument::query()->findOrFail($documentId);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($document->file_path);
        $currentPath = $document->file_path;

        $this->deleteJson("/api/documentation/{$documentId}")
            ->assertOk()
            ->assertJsonPath('message', 'Documento eliminado correctamente.');

        Storage::disk('local')->assertMissing($currentPath);
        $this->assertSoftDeleted('managed_documents', [
            'id' => $documentId,
            'deleted_by' => $user->id,
        ]);
    }

    public function test_file_validation_rejects_unsupported_and_oversized_uploads(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->userWithPermissions(['documentation.create']));

        $base = [
            'title' => 'Documento inválido',
            'category' => ManagedDocument::CATEGORY_OTHER,
            'year' => 2026,
            'is_public' => false,
            'is_active' => true,
        ];

        $this->post('/api/documentation', [
            ...$base,
            'file' => UploadedFile::fake()->create('programa.exe', 10, 'application/x-msdownload'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->post('/api/documentation', [
            ...$base,
            'file' => UploadedFile::fake()->create('documento.pdf', 25 * 1024 + 1, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_all_supported_institutional_document_formats_are_accepted(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->userWithPermissions(['documentation.create']));

        $formats = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        foreach ($formats as $extension => $mimeType) {
            $this->post('/api/documentation', [
                'title' => "Documento {$extension}",
                'category' => ManagedDocument::CATEGORY_OTHER,
                'year' => 2026,
                'is_public' => false,
                'is_active' => true,
                'file' => UploadedFile::fake()->create("documento.{$extension}", 10, $mimeType),
            ])
                ->assertCreated()
                ->assertJsonPath('data.original_name', "documento.{$extension}");
        }

        $this->assertSame(count($formats), ManagedDocument::query()->count());
    }

    public function test_public_scope_only_returns_active_public_documents(): void
    {
        $visible = $this->makeDocument(['is_public' => true, 'is_active' => true]);
        $this->makeDocument(['is_public' => true, 'is_active' => false]);
        $this->makeDocument(['is_public' => false, 'is_active' => true]);

        $this->assertSame(
            [$visible->id],
            ManagedDocument::query()->publiclyAvailable()->pluck('id')->all(),
        );
    }

    public function test_documentation_seeder_is_idempotent_and_registers_rbac_group(): void
    {
        Role::query()->firstOrCreate(
            ['slug' => 'administrador'],
            ['name' => 'Administrador', 'active' => true],
        );

        $this->seed(DocumentationSeeder::class);
        $this->seed(DocumentationSeeder::class);

        $this->assertSame(4, Permission::query()->where('slug', 'like', 'documentation.%')->count());
        $this->assertSame(1, SystemModule::query()->where('slug', 'documentation')->count());
        $this->assertSame(1, PermissionGroup::query()->where('slug', 'documentation')->count());
        $this->assertSame(
            4,
            PermissionGroup::query()->where('slug', 'documentation')->firstOrFail()->permissions()->count(),
        );
    }

    public function test_rbac_migration_rollback_preserves_catalog_and_assignments(): void
    {
        $permission = Permission::query()->where('slug', 'documentation.view')->firstOrFail();
        $module = SystemModule::query()->where('slug', 'documentation')->firstOrFail();
        $group = PermissionGroup::query()->where('slug', 'documentation')->firstOrFail();
        $role = Role::query()->create([
            'name' => 'Rol documental preexistente',
            'slug' => 'rol_documental_preexistente',
            'active' => true,
        ]);
        $role->permissions()->attach($permission);
        $role->modules()->attach($module);
        $group->permissions()->syncWithoutDetaching([$permission->id]);

        $migration = require database_path('migrations/2026_08_30_171000_register_documentation_module.php');
        $migration->down();

        $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'slug' => 'documentation.view']);
        $this->assertDatabaseHas('system_modules', ['id' => $module->id, 'slug' => 'documentation']);
        $this->assertDatabaseHas('permission_groups', ['id' => $group->id, 'slug' => 'documentation']);
        $this->assertDatabaseHas('permission_role', ['role_id' => $role->id, 'permission_id' => $permission->id]);
        $this->assertDatabaseHas('role_system_module', ['role_id' => $role->id, 'system_module_id' => $module->id]);
        $this->assertDatabaseHas('permission_group_permission', [
            'permission_group_id' => $group->id,
            'permission_id' => $permission->id,
        ]);
    }

    private function makeDocument(array $overrides = []): ManagedDocument
    {
        return ManagedDocument::query()->create([
            'title' => 'Documento '.Str::random(8),
            'category' => ManagedDocument::CATEGORY_OTHER,
            'year' => 2026,
            'file_path' => 'documentation/otro/2026/'.Str::uuid().'.pdf',
            'original_name' => 'documento.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'is_public' => false,
            'is_active' => true,
            ...$overrides,
        ]);
    }

    /** @param array<int, string> $permissionSlugs */
    private function userWithPermissions(array $permissionSlugs): User
    {
        $role = Role::query()->create([
            'name' => 'Rol '.Str::random(8),
            'slug' => 'rol_'.Str::random(12),
            'active' => true,
        ]);

        $permissions = collect($permissionSlugs)->map(fn (string $slug) => Permission::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => Str::headline(str_replace(['.', '_'], ' ', $slug)), 'active' => true],
        ));
        $role->permissions()->sync($permissions->pluck('id')->all());

        $user = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $user->roles()->attach($role);

        return $user;
    }
}
