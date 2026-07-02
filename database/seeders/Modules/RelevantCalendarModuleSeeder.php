<?php

namespace Database\Seeders\Modules;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemModule;
use Database\Seeders\CalendarInstitutionSeeder;
use Database\Seeders\CalendarProcessTypeSeeder;
use Illuminate\Database\Seeder;

class RelevantCalendarModuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPermissions();
        $this->seedModules();
        $this->assignPermissionsAndModules();
        $this->call([
            CalendarProcessTypeSeeder::class,
            CalendarInstitutionSeeder::class,
        ]);
    }

    private function seedPermissions(): void
    {
        $permissions = [
            ['slug' => 'ver_calendario_fechas_relevantes', 'name' => 'Ver Calendario de Fechas Relevantes'],
            ['slug' => 'ver_todo_calendario_fechas_relevantes', 'name' => 'Ver Todo el Calendario de Fechas Relevantes'],
            ['slug' => 'gestionar_calendario_fechas_relevantes_departamento', 'name' => 'Gestionar Calendario de Fechas Relevantes por Departamento'],
            ['slug' => 'administrar_calendario_fechas_relevantes', 'name' => 'Administrar Calendario de Fechas Relevantes'],
            ['slug' => 'administrar_tipos_calendario_fechas_relevantes', 'name' => 'Administrar Tipos de Proceso del Calendario'],
            ['slug' => 'administrar_instituciones_calendario_fechas_relevantes', 'name' => 'Administrar Instituciones del Calendario'],
            ['slug' => 'exportar_calendario_fechas_relevantes', 'name' => 'Exportar Calendario de Fechas Relevantes'],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'description' => null,
                    'active' => true,
                ],
            );
        }
    }

    private function seedModules(): void
    {
        $parent = SystemModule::query()->updateOrCreate(
            ['slug' => 'relevant_calendar'],
            [
                'name' => 'Calendario y Fechas Relevantes',
                'frontend_route' => null,
                'icon' => 'bx-calendar-event',
                'sort_order' => 118,
                'active' => true,
                'parent_id' => null,
            ],
        );

        $children = [
            ['slug' => 'relevant_calendar_main', 'name' => 'Calendario', 'route' => '/relevant-calendar', 'sort' => 1],
            ['slug' => 'relevant_calendar_process_types', 'name' => 'Tipos de procesos', 'route' => '/relevant-calendar/process-types', 'sort' => 2],
            ['slug' => 'relevant_calendar_institutions', 'name' => 'Instituciones', 'route' => '/relevant-calendar/institutions', 'sort' => 3],
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

    private function assignPermissionsAndModules(): void
    {
        $roles = Role::query()->whereIn('slug', ['super_admin', 'administrador', 'rrhh', 'direccion', 'coordinador_academico'])->get()->keyBy('slug');
        $permissions = Permission::query()->whereIn('slug', [
            'ver_calendario_fechas_relevantes',
            'ver_todo_calendario_fechas_relevantes',
            'gestionar_calendario_fechas_relevantes_departamento',
            'administrar_calendario_fechas_relevantes',
            'administrar_tipos_calendario_fechas_relevantes',
            'administrar_instituciones_calendario_fechas_relevantes',
            'exportar_calendario_fechas_relevantes',
        ])->get()->keyBy('slug');
        $modules = SystemModule::query()->whereIn('slug', [
            'relevant_calendar',
            'relevant_calendar_main',
            'relevant_calendar_process_types',
            'relevant_calendar_institutions',
        ])->get()->keyBy('slug');

        if ($roles->has('administrador')) {
            $roles['administrador']->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
            $roles['administrador']->modules()->syncWithoutDetaching($modules->pluck('id')->all());
        }

        if ($roles->has('rrhh')) {
            $roles['rrhh']->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
            $roles['rrhh']->modules()->syncWithoutDetaching($modules->pluck('id')->all());
        }

        if ($roles->has('direccion')) {
            $roles['direccion']->permissions()->syncWithoutDetaching([
                $permissions['ver_calendario_fechas_relevantes']->id,
                $permissions['ver_todo_calendario_fechas_relevantes']->id,
                $permissions['exportar_calendario_fechas_relevantes']->id,
            ]);
            $roles['direccion']->modules()->syncWithoutDetaching([
                $modules['relevant_calendar']->id,
                $modules['relevant_calendar_main']->id,
            ]);
        }

        if ($roles->has('coordinador_academico')) {
            $roles['coordinador_academico']->permissions()->syncWithoutDetaching([
                $permissions['ver_calendario_fechas_relevantes']->id,
                $permissions['gestionar_calendario_fechas_relevantes_departamento']->id,
            ]);
            $roles['coordinador_academico']->modules()->syncWithoutDetaching([
                $modules['relevant_calendar']->id,
                $modules['relevant_calendar_main']->id,
            ]);
        }
    }
}
