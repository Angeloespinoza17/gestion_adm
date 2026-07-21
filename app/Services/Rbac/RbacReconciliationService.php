<?php

namespace App\Services\Rbac;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\SystemModule;
use App\Services\Remuneration\RemunerationAccessService;
use Database\Seeders\AttendancePermissionSeeder;
use Database\Seeders\NavigationModuleBackfillSeeder;
use Database\Seeders\PermissionBackfillSeeder;
use Database\Seeders\PermissionGroupSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class RbacReconciliationService
{
    /** @var array<int, string> */
    private const CONTAMINATED_ROLE_SLUGS = [
        'enfermeria',
        'psicologo',
        'prevencion_riesgos',
        'docente',
        'estudiante',
        'apoderado',
        'porteria',
        'nochero',
        'coordinador_academico',
        'rrhh',
        'inspectoria',
    ];

    /** @var array<int, string> */
    private const CONTAMINATING_PERMISSION_SLUGS = [
        'ver_inventario',
        'ver_reportes_inventario',
        'imprimir_etiquetas_inventario',
        'administrar_categorias_inventario',
    ];

    /** @var array<int, string> */
    private const CONTAMINATING_MODULE_SLUGS = [
        'inventory',
        'inventory_items',
        'inventory_management',
        'inventory_categories',
        'inventory_suppliers',
        'inventory_reports',
        'inventory_labels',
    ];

    /** @var array<int, string> */
    private const INFIRMARY_PERMISSION_SLUGS = [
        'ver_enfermeria',
        'crear_atenciones_enfermeria',
        'editar_atenciones_enfermeria',
        'eliminar_atenciones_enfermeria',
        'exportar_enfermeria',
        'administrar_inventario_enfermeria',
        'administrar_medicamentos_enfermeria',
        'administrar_catalogos_enfermeria',
        'gestionar_accidentes_enfermeria',
        'ver_reportes_enfermeria',
    ];

    /** @var array<int, string> */
    private const INFIRMARY_MODULE_SLUGS = [
        'infirmary',
        'infirmary_dashboard',
        'infirmary_attentions',
        'infirmary_staff_attentions',
        'infirmary_categories',
        'infirmary_inventory',
        'infirmary_accidents',
        'infirmary_medications',
    ];

    public function __construct(
        private readonly RoleModuleSyncService $roleModuleSyncService,
        private readonly RemunerationAccessService $remunerationAccessService,
    ) {}

    /**
     * Ejecuta toda la reconciliación dentro de una transacción que siempre se revierte.
     *
     * @return array<string, mixed>
     */
    public function preview(): array
    {
        DB::beginTransaction();

        try {
            $before = $this->snapshot();
            $this->reconcile();
            $after = $this->snapshot();
            $audit = $this->audit();

            return $this->diff($before, $after, $audit);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function apply(): array
    {
        return DB::transaction(function (): array {
            $before = $this->snapshot();
            $this->reconcile();
            $after = $this->snapshot();
            $audit = $this->audit();

            if ($audit['critical_issue_count'] > 0) {
                throw new RuntimeException('La reconciliación fue revertida porque aún existen inconsistencias RBAC críticas.');
            }

            return $this->diff($before, $after, $audit);
        }, 3);
    }

    /**
     * @return array<string, mixed>
     */
    public function audit(): array
    {
        $activePermissionSlugs = Permission::query()
            ->where('active', true)
            ->pluck('slug')
            ->all();
        $activePermissionLookup = array_fill_keys($activePermissionSlugs, true);

        $missingBackendPermissions = array_values(array_filter(
            $this->backendRoutePermissions(),
            fn (string $slug) => ! isset($activePermissionLookup[$slug]),
        ));
        $missingFrontendPermissions = array_values(array_filter(
            $this->frontendRoutePermissions(),
            fn (string $slug) => ! isset($activePermissionLookup[$slug]),
        ));

        $ungroupedPermissions = Permission::query()
            ->where('active', true)
            ->whereDoesntHave('groups', fn ($query) => $query->where('permission_groups.active', true))
            ->orderBy('slug')
            ->pluck('slug')
            ->all();

        $groupsWithoutModule = PermissionGroup::query()
            ->where('active', true)
            ->where(function ($query): void {
                $query->whereNull('system_module_id')
                    ->orWhereDoesntHave('systemModule', fn ($moduleQuery) => $moduleQuery->where('active', true));
            })
            ->orderBy('slug')
            ->pluck('slug')
            ->all();

        $contaminatedRoles = Role::query()
            ->whereIn('slug', self::CONTAMINATED_ROLE_SLUGS)
            ->whereHas('permissions', fn ($query) => $query->whereIn('slug', self::CONTAMINATING_PERMISSION_SLUGS))
            ->with(['permissions' => fn ($query) => $query->whereIn('slug', self::CONTAMINATING_PERMISSION_SLUGS)])
            ->get()
            ->mapWithKeys(fn (Role $role) => [$role->slug => $role->permissions->pluck('slug')->sort()->values()->all()])
            ->all();

        $nurseRole = Role::query()->where('slug', 'enfermeria')->with(['permissions:id,slug', 'modules:id,slug'])->first();
        $nursePermissionSlugs = $nurseRole?->permissions->pluck('slug')->all() ?? [];
        $nurseModuleSlugs = $nurseRole?->modules->pluck('slug')->all() ?? [];
        $nurseMissingPermissions = array_values(array_diff(self::INFIRMARY_PERMISSION_SLUGS, $nursePermissionSlugs));
        $nurseMissingModules = array_values(array_diff(self::INFIRMARY_MODULE_SLUGS, $nurseModuleSlugs));

        $invalidRoleSlugs = Role::query()
            ->get(['slug'])
            ->pluck('slug')
            ->filter(fn (string $slug) => $slug !== $this->normalizeRoleSlug($slug))
            ->values()
            ->all();

        $rolesWithoutAccess = Role::query()
            ->where('active', true)
            ->whereHas('users', fn ($query) => $query->where('users.active', true))
            ->whereDoesntHave('permissions', fn ($query) => $query->where('permissions.active', true))
            ->orderBy('slug')
            ->pluck('slug')
            ->all();

        $criticalIssueCount = count($missingBackendPermissions)
            + count($missingFrontendPermissions)
            + count($ungroupedPermissions)
            + count($groupsWithoutModule)
            + count($contaminatedRoles)
            + count($nurseMissingPermissions)
            + count($nurseMissingModules)
            + count($invalidRoleSlugs)
            + count($rolesWithoutAccess);

        return [
            'counts' => [
                'roles' => Role::query()->where('active', true)->count(),
                'permissions' => count($activePermissionSlugs),
                'modules' => SystemModule::query()->where('active', true)->count(),
                'permission_groups' => PermissionGroup::query()->where('active', true)->count(),
            ],
            'missing_backend_permissions' => $missingBackendPermissions,
            'missing_frontend_permissions' => $missingFrontendPermissions,
            'ungrouped_permissions' => $ungroupedPermissions,
            'groups_without_active_module' => $groupsWithoutModule,
            'contaminated_roles' => $contaminatedRoles,
            'nurse_missing_permissions' => $nurseMissingPermissions,
            'nurse_missing_modules' => $nurseMissingModules,
            'invalid_role_slugs' => $invalidRoleSlugs,
            'roles_with_users_without_permissions' => $rolesWithoutAccess,
            'critical_issue_count' => $criticalIssueCount,
        ];
    }

    private function reconcile(): void
    {
        app(PermissionBackfillSeeder::class)->run();
        $this->upsertCatalogPermissions();
        $this->normalizeRoleSlugs();
        $this->ensureGroupModules();
        $this->ensureInfirmaryModules();

        app(PermissionGroupSeeder::class)->run();
        $this->attachAttendancePermissionsToStudentsGroup();

        app(RbacSeeder::class)->reconcileRoleAssignmentsAdditively();
        $this->grantDashboardBaseline();
        $this->grantInfirmaryProfiles();
        $this->removeInventoryContamination();

        app(NavigationModuleBackfillSeeder::class)->run();

        Role::query()
            ->where('active', true)
            ->with('permissions:id')
            ->get()
            ->each(fn (Role $role) => $this->roleModuleSyncService->syncRoleModulesFromPermissions($role));

        $this->synchronizeSuperAdmin();
    }

    private function upsertCatalogPermissions(): void
    {
        $richDefinitions = collect(AttendancePermissionSeeder::definitions())
            ->concat(collect($this->remunerationAccessService->permissionDefinitions())->map(fn (array $definition) => [
                ...$definition,
                'description' => 'Permiso del módulo Remuneraciones.',
            ]))
            ->push([
                'slug' => 'administrar_catalogos_enfermeria',
                'name' => 'Administrar catálogos de Enfermería',
                'description' => 'Permite gestionar categorías y catálogos operativos del módulo Enfermería.',
            ])
            ->keyBy('slug');

        foreach ($richDefinitions as $definition) {
            Permission::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'active' => true,
                ],
            );
        }

        $catalogSlugs = collect(PermissionGroupSeeder::definitions())
            ->flatMap(fn (array $group) => $group['permissions'])
            ->merge($this->backendRoutePermissions())
            ->merge($this->frontendRoutePermissions())
            ->unique()
            ->sort()
            ->values();

        foreach ($catalogSlugs as $slug) {
            Permission::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => Str::headline(str_replace('.', ' ', $slug)),
                    'description' => 'Permiso registrado por el catálogo RBAC canónico.',
                    'active' => true,
                ],
            );
        }
    }

    private function ensureInfirmaryModules(): void
    {
        $parent = SystemModule::query()->updateOrCreate(
            ['slug' => 'infirmary'],
            [
                'name' => 'Enfermería',
                'frontend_route' => null,
                'icon' => 'bx-plus-medical',
                'sort_order' => 50,
                'active' => true,
                'parent_id' => null,
            ],
        );

        $children = [
            ['slug' => 'infirmary_dashboard', 'name' => 'Dashboard', 'route' => '/infirmary', 'sort_order' => 1],
            ['slug' => 'infirmary_attentions', 'name' => 'Atención a estudiantes', 'route' => '/infirmary/attentions', 'sort_order' => 2],
            ['slug' => 'infirmary_staff_attentions', 'name' => 'Atención a funcionarios', 'route' => '/infirmary/staff-attentions', 'sort_order' => 3],
            ['slug' => 'infirmary_categories', 'name' => 'Categorías', 'route' => '/infirmary/categories', 'sort_order' => 4],
            ['slug' => 'infirmary_inventory', 'name' => 'Inventario', 'route' => '/infirmary/inventory', 'sort_order' => 5],
            ['slug' => 'infirmary_accidents', 'name' => 'Seguro escolar', 'route' => '/infirmary/accidents', 'sort_order' => 6],
            ['slug' => 'infirmary_medications', 'name' => 'Medicamentos', 'route' => '/infirmary/medications', 'sort_order' => 7],
        ];

        foreach ($children as $child) {
            SystemModule::query()->updateOrCreate(
                ['slug' => $child['slug']],
                [
                    'name' => $child['name'],
                    'frontend_route' => $child['route'],
                    'icon' => null,
                    'sort_order' => $child['sort_order'],
                    'active' => true,
                    'parent_id' => $parent->id,
                ],
            );
        }

        SystemModule::query()
            ->whereIn('slug', ['infirmary_history', 'infirmary_calls', 'infirmary_reports'])
            ->update(['active' => false, 'parent_id' => $parent->id]);
    }

    private function ensureGroupModules(): void
    {
        foreach (PermissionGroupSeeder::definitions() as $definition) {
            $module = SystemModule::query()->firstOrNew(['slug' => $definition['module_slug']]);

            if (! $module->exists) {
                $module->fill([
                    'name' => $definition['name'],
                    'frontend_route' => null,
                    'icon' => null,
                    'sort_order' => $definition['sort_order'],
                    'parent_id' => null,
                ]);
            }

            $module->active = true;
            $module->save();
        }
    }

    private function attachAttendancePermissionsToStudentsGroup(): void
    {
        $group = PermissionGroup::query()->where('slug', 'estudiantes')->first();

        if (! $group) {
            return;
        }

        $permissionIds = Permission::query()
            ->whereIn('slug', array_column(AttendancePermissionSeeder::definitions(), 'slug'))
            ->pluck('id');

        $group->permissions()->syncWithoutDetaching($permissionIds);
    }

    private function grantDashboardBaseline(): void
    {
        $permissionId = Permission::query()->where('slug', 'ver_dashboard')->value('id');
        $moduleId = SystemModule::query()->where('slug', 'dashboard')->value('id');

        Role::query()->where('active', true)->get()->each(function (Role $role) use ($permissionId, $moduleId): void {
            if ($permissionId) {
                $role->permissions()->syncWithoutDetaching([$permissionId]);
            }

            if ($moduleId) {
                $role->modules()->syncWithoutDetaching([$moduleId]);
            }
        });
    }

    private function grantInfirmaryProfiles(): void
    {
        $permissionMap = [
            'administrador' => self::INFIRMARY_PERMISSION_SLUGS,
            'enfermeria' => self::INFIRMARY_PERMISSION_SLUGS,
            'direccion' => ['ver_enfermeria', 'ver_reportes_enfermeria', 'exportar_enfermeria'],
            'rrhh' => ['ver_enfermeria', 'ver_reportes_enfermeria', 'exportar_enfermeria'],
            'inspectoria' => [
                'ver_enfermeria',
                'crear_atenciones_enfermeria',
                'editar_atenciones_enfermeria',
                'gestionar_accidentes_enfermeria',
            ],
        ];
        $permissionIds = Permission::query()->whereIn('slug', self::INFIRMARY_PERMISSION_SLUGS)->pluck('id', 'slug');
        $moduleIds = SystemModule::query()->whereIn('slug', self::INFIRMARY_MODULE_SLUGS)->pluck('id');

        foreach ($permissionMap as $roleSlug => $slugs) {
            $role = Role::query()->where('slug', $roleSlug)->first();

            if (! $role) {
                continue;
            }

            $role->permissions()->syncWithoutDetaching(
                collect($slugs)->map(fn (string $slug) => $permissionIds[$slug] ?? null)->filter()->all(),
            );
            $role->modules()->syncWithoutDetaching($moduleIds);
        }
    }

    private function removeInventoryContamination(): void
    {
        $permissionIds = Permission::query()->whereIn('slug', self::CONTAMINATING_PERMISSION_SLUGS)->pluck('id');
        $moduleIds = SystemModule::query()->whereIn('slug', self::CONTAMINATING_MODULE_SLUGS)->pluck('id');

        Role::query()
            ->whereIn('slug', self::CONTAMINATED_ROLE_SLUGS)
            ->get()
            ->each(function (Role $role) use ($permissionIds, $moduleIds): void {
                $role->permissions()->detach($permissionIds);
                $role->modules()->detach($moduleIds);
            });
    }

    private function synchronizeSuperAdmin(): void
    {
        $role = Role::query()->where('slug', 'super_admin')->first();

        if (! $role) {
            return;
        }

        $role->permissions()->sync(Permission::query()->where('active', true)->pluck('id'));
        $role->modules()->sync(SystemModule::query()->where('active', true)->pluck('id'));
    }

    private function normalizeRoleSlugs(): void
    {
        Role::query()->get()->each(function (Role $role): void {
            $normalized = $this->normalizeRoleSlug($role->slug);

            if ($normalized === $role->slug) {
                return;
            }

            $collision = Role::query()
                ->whereKeyNot($role->id)
                ->whereRaw('LOWER(slug) = ?', [$normalized])
                ->exists();

            if ($collision) {
                throw new RuntimeException("No se puede normalizar el rol {$role->slug}: el slug {$normalized} ya existe.");
            }

            $role->update(['slug' => $normalized]);
        });
    }

    private function normalizeRoleSlug(string $slug): string
    {
        return Str::snake(Str::lower(trim($slug)));
    }

    /** @return array<int, string> */
    private function backendRoutePermissions(): array
    {
        $permissions = [];

        foreach (app('router')->getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware) || ! str_starts_with($middleware, 'permission:')) {
                    continue;
                }

                $permissions = array_merge($permissions, explode(',', substr($middleware, strlen('permission:'))));
            }
        }

        sort($permissions);

        return array_values(array_unique(array_filter($permissions)));
    }

    /** @return array<int, string> */
    private function frontendRoutePermissions(): array
    {
        $routerPath = resource_path('js/router/index.js');

        if (! is_file($routerPath)) {
            return [];
        }

        $contents = file_get_contents($routerPath) ?: '';
        preg_match_all("/permission\\s*:\\s*['\"]([^'\"]+)['\"]/", $contents, $matches);
        $permissions = array_values(array_unique($matches[1] ?? []));
        sort($permissions);

        return $permissions;
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'permissions' => Permission::query()->where('active', true)->orderBy('slug')->pluck('slug')->all(),
            'modules' => SystemModule::query()->where('active', true)->orderBy('slug')->pluck('slug')->all(),
            'groups' => PermissionGroup::query()
                ->where('active', true)
                ->with('permissions:id,slug')
                ->orderBy('slug')
                ->get()
                ->mapWithKeys(fn (PermissionGroup $group) => [
                    $group->slug => $group->permissions->pluck('slug')->sort()->values()->all(),
                ])
                ->all(),
            'roles' => Role::query()
                ->with(['permissions:id,slug', 'modules:id,slug'])
                ->orderBy('id')
                ->get()
                ->mapWithKeys(fn (Role $role) => [
                    $role->id => [
                        'slug' => $role->slug,
                        'permissions' => $role->permissions->pluck('slug')->sort()->values()->all(),
                        'modules' => $role->modules->pluck('slug')->sort()->values()->all(),
                    ],
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $audit
     * @return array<string, mixed>
     */
    private function diff(array $before, array $after, array $audit): array
    {
        $roleChanges = [];

        foreach ($after['roles'] as $roleId => $role) {
            $previous = $before['roles'][$roleId] ?? ['slug' => null, 'permissions' => [], 'modules' => []];
            $addedPermissions = array_values(array_diff($role['permissions'], $previous['permissions']));
            $removedPermissions = array_values(array_diff($previous['permissions'], $role['permissions']));
            $addedModules = array_values(array_diff($role['modules'], $previous['modules']));
            $removedModules = array_values(array_diff($previous['modules'], $role['modules']));

            if ($role['slug'] !== $previous['slug'] || $addedPermissions || $removedPermissions || $addedModules || $removedModules) {
                $roleChanges[] = [
                    'role_id' => $roleId,
                    'slug_before' => $previous['slug'],
                    'slug_after' => $role['slug'],
                    'permissions_added' => $addedPermissions,
                    'permissions_removed' => $removedPermissions,
                    'modules_added' => $addedModules,
                    'modules_removed' => $removedModules,
                ];
            }
        }

        return [
            'permissions_created' => array_values(array_diff($after['permissions'], $before['permissions'])),
            'modules_created' => array_values(array_diff($after['modules'], $before['modules'])),
            'groups_created' => array_values(array_diff(array_keys($after['groups']), array_keys($before['groups']))),
            'group_links_added' => collect($after['groups'])
                ->map(fn (array $permissions, string $slug) => count(array_diff($permissions, $before['groups'][$slug] ?? [])))
                ->sum(),
            'group_links_removed' => collect($before['groups'])
                ->map(fn (array $permissions, string $slug) => count(array_diff($permissions, $after['groups'][$slug] ?? [])))
                ->sum(),
            'role_changes' => $roleChanges,
            'audit' => $audit,
        ];
    }
}
