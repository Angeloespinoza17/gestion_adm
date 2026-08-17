<?php

namespace Tests\Feature\LibroDigital;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\SystemModule;
use App\Services\Rbac\RbacReconciliationService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibroDigitalRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_rbac_seed_and_audit_include_the_complete_lcd_catalog(): void
    {
        $this->seed(RbacSeeder::class);

        $permissionSlugs = Permission::query()
            ->where('slug', 'like', 'libro_digital.%')
            ->pluck('slug');
        $group = PermissionGroup::query()->where('slug', 'libro_digital')->firstOrFail();
        $audit = app(RbacReconciliationService::class)->audit();

        $this->assertCount(35, $permissionSlugs);
        $this->assertSame(35, $group->permissions()->where('permissions.slug', 'like', 'libro_digital.%')->count());
        $this->assertSame(11, SystemModule::query()->where('slug', 'like', 'libro_digital%')->count());
        $this->assertSame([], array_values(array_filter(
            [...$audit['missing_backend_permissions'], ...$audit['missing_frontend_permissions']],
            fn (string $slug): bool => str_starts_with($slug, 'libro_digital.'),
        )));
        $this->assertSame([], array_values(array_filter(
            $audit['ungrouped_permissions'],
            fn (string $slug): bool => str_starts_with($slug, 'libro_digital.'),
        )));
    }
}
