<?php

namespace Database\Seeders\Modules;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemModule;
use App\Models\User;
use App\Services\RiskPrevention\RiskMatrixConfigurationInstaller;
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
        app(RiskMatrixConfigurationInstaller::class)->install();
    }

    private function seedPermissions(): void
    {
        $permissions = [
            ['slug' => 'ver_prevencion_riesgos', 'name' => 'Ver Prevención de Riesgos'],
            ['slug' => 'gestionar_prevencion_riesgos', 'name' => 'Gestionar Prevención de Riesgos'],
            ['slug' => 'exportar_prevencion_riesgos', 'name' => 'Exportar Prevención de Riesgos'],
            ['slug' => 'ver_documentos_prevencion_difundibles', 'name' => 'Ver Documentos Difundibles de Prevención'],
            ['slug' => 'ver_comite_paritario', 'name' => 'Ver Comité Paritario'],
            ['slug' => 'cargar_actas_comite_paritario', 'name' => 'Cargar actas del Comité Paritario'],
            ['slug' => 'ver_entregas_epp', 'name' => 'Ver entregas de EPP en Bodega'],
            ['slug' => 'registrar_entregas_epp', 'name' => 'Registrar entregas de EPP en Bodega'],
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
            ['slug' => 'risk_prevention_risk_matrices', 'name' => 'Matrices IPER/MIPER', 'route' => '/risk-prevention/matrices', 'sort' => 2],
            ['slug' => 'risk_prevention_risk_imports', 'name' => 'Importaciones IPER', 'route' => '/risk-prevention/matrices/importaciones', 'sort' => 3],
            ['slug' => 'risk_prevention_risk_catalogs', 'name' => 'Catálogos y metodología', 'route' => '/risk-prevention/matrices/catalogos', 'sort' => 4],
            ['slug' => 'risk_prevention_preventive_program', 'name' => 'Programa de Trabajo Preventivo', 'route' => '/risk-prevention/preventive-program', 'sort' => 5],
            ['slug' => 'risk_prevention_extinguishers', 'name' => 'Extintores', 'route' => '/risk-prevention/extinguishers', 'sort' => 10],
            ['slug' => 'risk_prevention_accidents', 'name' => 'Accidentes', 'route' => '/risk-prevention/accidents', 'sort' => 20],
            ['slug' => 'risk_prevention_emergencies', 'name' => 'Emergencias y planes', 'route' => '/risk-prevention/emergencies', 'sort' => 30],
            ['slug' => 'risk_prevention_epp', 'name' => 'EPP y seguridad', 'route' => '/risk-prevention/epp', 'sort' => 40],
            ['slug' => 'risk_prevention_trainings', 'name' => 'Capacitaciones', 'route' => '/risk-prevention/trainings', 'sort' => 50],
            ['slug' => 'risk_prevention_joint_committee', 'name' => 'Comité Paritario', 'route' => '/risk-prevention/joint-committee', 'sort' => 55],
            ['slug' => 'risk_prevention_personnel', 'name' => 'Gestión del personal', 'route' => '/risk-prevention/personnel', 'sort' => 60],
            ['slug' => 'risk_prevention_documents', 'name' => 'Gestión documental empresa', 'route' => '/risk-prevention/documents', 'sort' => 70],
            ['slug' => 'risk_prevention_staff_documents', 'name' => 'Gestión documental', 'route' => '/risk-prevention/document-management', 'sort' => 80],
            ['slug' => 'risk_prevention_reports', 'name' => 'Reportes', 'route' => '/risk-prevention/reports', 'sort' => 90],
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

        if (! $user) {
            $user = new User;
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
            'ver_documentos_prevencion_difundibles',
            'ver_comite_paritario',
            'cargar_actas_comite_paritario',
            'ver_entregas_epp',
            'registrar_entregas_epp',
        ])->get()->keyBy('slug');

        $modules = SystemModule::query()->whereIn('slug', [
            'risk_prevention',
            'risk_prevention_dashboard',
            'risk_prevention_risk_matrices',
            'risk_prevention_risk_imports',
            'risk_prevention_risk_catalogs',
            'risk_prevention_preventive_program',
            'risk_prevention_extinguishers',
            'risk_prevention_accidents',
            'risk_prevention_emergencies',
            'risk_prevention_epp',
            'risk_prevention_trainings',
            'risk_prevention_joint_committee',
            'risk_prevention_personnel',
            'risk_prevention_documents',
            'risk_prevention_staff_documents',
            'risk_prevention_reports',
            'inventory',
            'inventory_epp_deliveries',
        ])->get();

        foreach (['super_admin', 'administrador', 'prevencion_riesgos'] as $roleSlug) {
            if (! $roles->has($roleSlug)) {
                continue;
            }

            $roles[$roleSlug]->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
            $roles[$roleSlug]->modules()->syncWithoutDetaching($modules->pluck('id')->all());
        }

        foreach (['direccion', 'rrhh', 'inspectoria'] as $roleSlug) {
            if (! $roles->has($roleSlug)) {
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
