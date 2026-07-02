<?php

namespace Database\Seeders\Modules;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemModule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PrevencionRiesgosModuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPermissions();
        $this->seedModules();
        $this->ensureRoles();
        $this->ensureSuperAdminUser();
        $this->assignPermissionsAndModules();
    }

    private function seedPermissions(): void
    {
        $permissions = [
            ['slug' => 'ver_prevencion_riesgos', 'name' => 'Ver Prevención de Riesgos'],
            ['slug' => 'gestionar_prevencion_riesgos', 'name' => 'Gestionar Prevención de Riesgos'],
            ['slug' => 'exportar_prevencion_riesgos', 'name' => 'Exportar Prevención de Riesgos'],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'description' => 'Permiso del módulo de prevención de riesgos.',
                    'active' => true,
                ],
            );
        }
    }

    private function seedModules(): void
    {
        $parent = SystemModule::query()->updateOrCreate(
            ['slug' => 'risk_prevention'],
            [
                'name' => 'Prevención de Riesgos',
                'frontend_route' => null,
                'icon' => 'bx-shield-alt-2',
                'sort_order' => 119,
                'active' => true,
                'parent_id' => null,
            ],
        );

        $children = [
            ['slug' => 'risk_prevention_dashboard', 'name' => 'Dashboard', 'route' => '/risk-prevention', 'sort' => 1],
            ['slug' => 'risk_prevention_extinguishers', 'name' => 'Extintores', 'route' => '/risk-prevention/extinguishers', 'sort' => 2],
            ['slug' => 'risk_prevention_accidents', 'name' => 'Accidentes', 'route' => '/risk-prevention/accidents', 'sort' => 3],
            ['slug' => 'risk_prevention_emergencies', 'name' => 'Emergencias y planes', 'route' => '/risk-prevention/emergencies', 'sort' => 4],
            ['slug' => 'risk_prevention_epp', 'name' => 'EPP y seguridad', 'route' => '/risk-prevention/epp', 'sort' => 5],
            ['slug' => 'risk_prevention_trainings', 'name' => 'Capacitaciones', 'route' => '/risk-prevention/trainings', 'sort' => 6],
            ['slug' => 'risk_prevention_documents', 'name' => 'Centro de documentos', 'route' => '/risk-prevention/documents', 'sort' => 7],
            ['slug' => 'risk_prevention_reports', 'name' => 'Reportes', 'route' => '/risk-prevention/reports', 'sort' => 8],
        ];

        foreach ($children as $child) {
            SystemModule::query()->updateOrCreate(
                ['slug' => $child['slug']],
                [
                    'name' => $child['name'],
                    'frontend_route' => $child['route'],
                    'icon' => null,
                    'sort_order' => $child['sort'],
                    'active' => true,
                    'parent_id' => $parent->id,
                ],
            );
        }
    }

    private function ensureRoles(): void
    {
        $roles = [
            ['slug' => 'super_admin', 'name' => 'Super Admin'],
            ['slug' => 'administrador', 'name' => 'Administrador'],
            ['slug' => 'direccion', 'name' => 'Dirección'],
            ['slug' => 'rrhh', 'name' => 'RRHH / Administración'],
            ['slug' => 'inspectoria', 'name' => 'Inspectoría'],
            ['slug' => 'prevencion_riesgos', 'name' => 'Prevención de Riesgos'],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'description' => 'Rol requerido por el módulo de prevención de riesgos.',
                    'active' => true,
                ],
            );
        }
    }

    private function ensureSuperAdminUser(): void
    {
        $user = User::query()->where('email', 'superadmin@cnscgestion.cl')->first();

        if (!$user) {
            $user = new User();
            $user->name = 'Super Admin';
            $user->email = 'superadmin@cnscgestion.cl';
            $user->password = Hash::make('Demo123!');
            $user->user_type = 'staff';
            $user->active = true;
            $user->save();
        }

        $role = Role::query()->where('slug', 'super_admin')->first();
        if ($role) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    }

    private function assignPermissionsAndModules(): void
    {
        $roles = Role::query()->whereIn('slug', [
            'super_admin',
            'administrador',
            'direccion',
            'rrhh',
            'inspectoria',
            'prevencion_riesgos',
        ])->get()->keyBy('slug');

        $permissions = Permission::query()->whereIn('slug', [
            'ver_prevencion_riesgos',
            'gestionar_prevencion_riesgos',
            'exportar_prevencion_riesgos',
        ])->get()->keyBy('slug');

        $modules = SystemModule::query()->whereIn('slug', [
            'risk_prevention',
            'risk_prevention_dashboard',
            'risk_prevention_extinguishers',
            'risk_prevention_accidents',
            'risk_prevention_emergencies',
            'risk_prevention_epp',
            'risk_prevention_trainings',
            'risk_prevention_documents',
            'risk_prevention_reports',
        ])->get();

        foreach (['super_admin', 'administrador', 'prevencion_riesgos'] as $roleSlug) {
            if (!$roles->has($roleSlug)) {
                continue;
            }

            $roles[$roleSlug]->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
            $roles[$roleSlug]->modules()->syncWithoutDetaching($modules->pluck('id')->all());
        }

        foreach (['direccion', 'rrhh', 'inspectoria'] as $roleSlug) {
            if (!$roles->has($roleSlug)) {
                continue;
            }

            $roles[$roleSlug]->permissions()->syncWithoutDetaching([
                $permissions['ver_prevencion_riesgos']->id,
                $permissions['exportar_prevencion_riesgos']->id,
            ]);
            $roles[$roleSlug]->modules()->syncWithoutDetaching($modules->pluck('id')->all());
        }
    }
}
