<?php

namespace Database\Seeders;

use Database\Seeders\Support\PreventsProductionSeeding;

use App\Models\Cargo;
use App\Models\Department;
use App\Models\Role;
use App\Models\SystemModule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RemunerationDepartmentsAndFunctionsSeeder extends Seeder
{
    use PreventsProductionSeeding;

    /**
     * @var array<int, array{function:string, department:string}>
     */
    private array $rows = [
        ['function' => 'DIRECTORA', 'department' => 'EQUIPO DIRECTIVO'],
        ['function' => 'DOCENTE', 'department' => 'APOYO UTP'],
        ['function' => 'DOCENTE', 'department' => 'ARTES'],
        ['function' => 'ASISTENTE DE AULA', 'department' => 'ASISTENTES DE AULA'],
        ['function' => 'INSPECTORA', 'department' => 'CONVIVENCIA ESCOLAR'],
        ['function' => 'TALLERISTA', 'department' => 'ACLE'],
        ['function' => 'DOCENTE', 'department' => 'ED. FISICA'],
        ['function' => 'DOCENTE', 'department' => 'CIENCIAS'],
        ['function' => 'DOCENTE', 'department' => 'HISTORIA'],
        ['function' => 'DOCENTE', 'department' => 'INGLÉS'],
        ['function' => 'DOCENTE', 'department' => 'PRE ESCOLAR'],
        ['function' => 'DOCENTE', 'department' => 'PIE'],
        ['function' => 'DOCENTE', 'department' => 'RELIGIÓN'],
        ['function' => 'DOCENTE', 'department' => 'LENGUAJE'],
        ['function' => 'DOCENTE', 'department' => 'ED. FISICA'],
        ['function' => 'NOCHERO', 'department' => 'AUXILIARES'],
        ['function' => 'COORDINADORA CICLO', 'department' => 'UTP'],
        ['function' => 'DOCENTE', 'department' => 'RELIGIÓN'],
        ['function' => 'AUXILIAR DE ASEO', 'department' => 'AUXILIARES'],
        ['function' => 'DOCENTE', 'department' => 'PIE'],
        ['function' => 'COORDINADORA PIE', 'department' => 'UTP'],
        ['function' => 'CALDERERO', 'department' => 'AUXILIARES'],
        ['function' => 'ORIENTADORA', 'department' => 'CONVIVENCIA ESCOLAR'],
        ['function' => 'COORDINADOR PASTORAL', 'department' => 'PASTORAL'],
        ['function' => 'AUXILIAR DE MANTENCIÓN', 'department' => 'AUXILIARES'],
        ['function' => 'AUXILIAR DE ASEO', 'department' => 'AUXILIARES'],
        ['function' => 'EDUCADOR/A DIFERENCIAL', 'department' => 'PIE'],
        ['function' => 'DOCENTE DIFERENCIAL', 'department' => 'PIE'],
        ['function' => 'COORDINADORA CICLO', 'department' => 'UTP'],
        ['function' => 'AUXILIAR DE ASEO', 'department' => 'AUXILIARES'],
        ['function' => 'DOCENTE', 'department' => 'BÁSICA'],
        ['function' => 'CAPELLÁN', 'department' => 'PASTORAL'],
        ['function' => 'TENS', 'department' => 'ADMINISTRATIVO'],
        ['function' => 'TRABAJADORA SOCIAL', 'department' => 'CONVIVENCIA ESCOLAR'],
        ['function' => 'INSPECTORA', 'department' => 'CONVIVENCIA ESCOLAR'],
        ['function' => 'DOCENTE', 'department' => 'BÁSICA'],
        ['function' => 'ADMINISTRADOR', 'department' => 'EQUIPO DIRECTIVO'],
        ['function' => 'DOCENTE', 'department' => 'BÁSICA'],
        ['function' => 'EDUCADOR/A DIFERENCIAL', 'department' => 'PIE'],
        ['function' => 'DOCENTE', 'department' => 'ED. FISICA'],
        ['function' => 'ASISTENTE DE PARVULO', 'department' => 'PRE ESCOLAR'],
        ['function' => 'TALLERISTA', 'department' => 'ACLE'],
        ['function' => 'SUB DIRECTOR CURRICULAR', 'department' => ' EQUIPO DIRECTIVO'],
        ['function' => 'DOCENTE DIFERENCIAL', 'department' => 'PIE'],
        ['function' => 'COORDINADORA CICLO', 'department' => 'UTP'],
        ['function' => 'DOCENTE', 'department' => 'ARTES'],
        ['function' => 'DOCENTE', 'department' => 'BÁSICA'],
        ['function' => 'ASISTENTE DE PARVULO', 'department' => 'PRE ESCOLAR'],
        ['function' => 'DOCENTE', 'department' => 'BÁSICA'],
        ['function' => 'PSICOLOGO/A', 'department' => 'CONVIVENCIA ESCOLAR'],
        ['function' => 'DOCENTE', 'department' => 'MATEMATICAS'],
        ['function' => 'DOCENTE', 'department' => 'LENGUAJE'],
        ['function' => 'DOCENTE', 'department' => 'ARTES'],
        ['function' => 'INFORMATICO', 'department' => 'ADMINISTRATIVO'],
        ['function' => 'INSPECTORA', 'department' => 'CONVIVENCIA ESCOLAR'],
        ['function' => 'DOCENTE', 'department' => 'CIENCIAS'],
        ['function' => 'SUB DIRECTORA PASTORAL', 'department' => 'EQUIPO DIRECTIVO'],
        ['function' => 'DOCENTE', 'department' => 'HISTORIA'],
        ['function' => 'DOCENTE', 'department' => 'MATEMATICAS'],
        ['function' => 'DOCENTE', 'department' => 'LENGUAJE'],
        ['function' => 'AUXILIAR DE ASEO', 'department' => 'AUXILIARES'],
        ['function' => 'DOCENTE', 'department' => 'MATEMATICAS'],
        ['function' => 'DOCENTE', 'department' => 'ARTES'],
        ['function' => 'ASISTENTE DE AULA', 'department' => 'APOYO UTP'],
        ['function' => 'DOCENTE', 'department' => 'CIENCIAS'],
        ['function' => 'DOCENTE', 'department' => 'LENGUAJE'],
        ['function' => 'ASISTENTE DE AULA', 'department' => 'APOYO UTP'],
        ['function' => 'DOCENTE', 'department' => 'ED. FISICA'],
        ['function' => 'DOCENTE', 'department' => 'CIENCIAS'],
        ['function' => 'DOCENTE', 'department' => 'MATEMATICAS'],
        ['function' => 'DOCENTE', 'department' => 'BÁSICA'],
        ['function' => 'ENCARGADO RRHH', 'department' => 'CONTABILIDAD'],
        ['function' => 'AUXILIAR DE ASEO', 'department' => 'AUXILIARES'],
        ['function' => 'DOCENTE', 'department' => 'INGLÉS'],
        ['function' => 'TALLERISTA', 'department' => 'ACLE'],
        ['function' => 'NOCHERO', 'department' => 'AUXILIARES'],
        ['function' => 'DOCENTE', 'department' => 'INGLÉS'],
        ['function' => 'DOCENTE', 'department' => 'RELIGIÓN'],
        ['function' => 'INSPECTORA', 'department' => 'CONVIVENCIA ESCOLAR'],
        ['function' => 'EDUCADOR/A DIFERENCIAL', 'department' => 'PIE'],
        ['function' => 'AUXILIAR DE ASEO', 'department' => 'AUXILIARES'],
        ['function' => 'CONTADORA', 'department' => 'CONTABILIDAD'],
        ['function' => 'DOCENTE', 'department' => 'INGLÉS'],
        ['function' => 'INSPECTORA', 'department' => 'CONVIVENCIA ESCOLAR'],
        ['function' => 'ASISTENTE DE CONVIVENCIA ESCOLAR', 'department' => 'CONVIVENCIA ESCOLAR'],
        ['function' => 'DOCENTE', 'department' => 'LENGUAJE'],
        ['function' => 'ENCARGADA CENTRO APUNTES', 'department' => 'ADMINISTRATIVO'],
        ['function' => 'BIBLIOTECARIA', 'department' => 'ADMINISTRATIVO'],
        ['function' => 'EDUCADOR/A DIFERENCIAL', 'department' => 'APOYO UTP'],
        ['function' => 'DOCENTE', 'department' => 'CIENCIAS'],
        ['function' => 'DOCENTE', 'department' => 'PRE ESCOLAR'],
        ['function' => 'INSPECTORA', 'department' => 'CONVIVENCIA ESCOLAR'],
        ['function' => 'SECRETARIA', 'department' => 'ADMINISTRATIVO'],
        ['function' => 'COORDINADOR PASTORAL', 'department' => 'PASTORAL'],
        ['function' => 'ASISTENTE DE AULA', 'department' => 'APOYO UTP'],
        ['function' => 'TALLERISTA', 'department' => 'ACLE'],
        ['function' => 'DOCENTE', 'department' => 'ARTES'],
        ['function' => 'DOCENTE', 'department' => 'MATEMATICAS'],
        ['function' => 'EDUCADOR/A DIFERENCIAL', 'department' => 'APOYO UTP'],
        ['function' => 'AUXILIAR DE MANTENCIÓN', 'department' => 'MANTENCIÓN'],
        ['function' => 'PSICOLOGO/A', 'department' => 'CONVIVENCIA ESCOLAR'],
        ['function' => 'DOCENTE', 'department' => 'HISTORIA'],
        ['function' => 'ASISTENTE PIE', 'department' => 'PIE'],
        ['function' => 'DOCENTE', 'department' => 'RELIGIÓN'],
        ['function' => 'PREVENCIONISTA DE RIESGOS', 'department' => 'ADMINISTRATIVO'],
        ['function' => 'DOCENTE', 'department' => 'LENGUAJE'],
        ['function' => 'DOCENTE', 'department' => 'ED. FISICA'],
        ['function' => 'FONOAUDIOLOGO/A', 'department' => 'PIE'],
        ['function' => 'PORTERO/A', 'department' => 'ADMINISTRATIVO'],
        ['function' => 'SUB DIRECTOR DE FORMACIÓN Y CONVIVENCIA ESCOLAR', 'department' => 'EQUIPO DIRECTIVO'],
        ['function' => 'DOCENTE', 'department' => 'CIENCIAS'],
        ['function' => 'DOCENTE', 'department' => 'LENGUAJE'],
        ['function' => 'DOCENTE', 'department' => 'ARTES'],
        ['function' => 'DOCENTE', 'department' => 'BÁSICA'],
        ['function' => 'INSPECTORA', 'department' => 'CONVIVENCIA ESCOLAR'],
        ['function' => 'DOCENTE', 'department' => 'MATEMATICAS'],
        ['function' => 'AUXILIAR DE ASEO', 'department' => 'AUXILIARES'],
        ['function' => 'PORTERO/A', 'department' => 'ADMINISTRATIVO'],
        ['function' => 'DOCENTE', 'department' => 'BÁSICA'],
        ['function' => 'DOCENTE', 'department' => 'MATEMATICAS'],
        ['function' => 'AUXILIAR DE ASEO', 'department' => 'AUXILIARES'],
        ['function' => 'PSICOLOGO/A', 'department' => 'PIE'],
        ['function' => 'ASISTENTE DE AULA', 'department' => 'APOYO UTP'],
        ['function' => 'INSPECTORA', 'department' => 'CONVIVENCIA ESCOLAR'],
    ];

    public function run(): void
    {
        $this->preventProductionSeeding();
        $this->seedDepartments();
        $this->seedFunctions();
        $this->ensureNavigation();
    }

    private function seedDepartments(): void
    {
        foreach ($this->uniqueValues('department') as $index => $name) {
            $department = Department::query()->firstOrNew(['slug' => Str::slug($name)]);
            $department->name = $name;
            $department->active = true;
            $department->description = $department->description ?: 'Departamento importado desde catálogo inicial de remuneraciones.';
            $department->color = $department->color ?: $this->colorFor($index);
            $department->sort_order = $department->sort_order ?: $index + 1;
            $department->save();
        }
    }

    private function seedFunctions(): void
    {
        foreach ($this->uniqueValues('function') as $name) {
            $matches = $this->matchingCargos($name);
            $cargo = $matches
                ->sort(fn (Cargo $left, Cargo $right) => $this->cargoUsageCount($right) <=> $this->cargoUsageCount($left) ?: $left->id <=> $right->id)
                ->first();

            if (!$cargo) {
                $cargo = Cargo::query()->firstOrNew(['slug' => Str::slug($name)]);
            }

            $matches
                ->reject(fn (Cargo $match) => $match->is($cargo))
                ->filter(fn (Cargo $match) => $this->cargoUsageCount($match) === 0)
                ->each(fn (Cargo $match) => $match->delete());

            $cargo->name = $name;
            $cargo->active = true;
            $cargo->description = $cargo->description ?: 'Función importada desde catálogo inicial de remuneraciones.';
            $cargo->save();
        }
    }

    private function ensureNavigation(): void
    {
        $parent = SystemModule::query()->updateOrCreate(
            ['slug' => 'remuneration'],
            [
                'name' => 'Remuneraciones',
                'frontend_route' => null,
                'icon' => 'bx-money',
                'sort_order' => 84,
                'active' => true,
                'parent_id' => null,
            ]
        );

        $modules = [
            [
                'slug' => 'remuneration_departments',
                'name' => 'Departamentos',
                'frontend_route' => '/remuneraciones/departamentos',
                'sort_order' => 19,
            ],
            [
                'slug' => 'remuneration_functions',
                'name' => 'Funciones',
                'frontend_route' => '/remuneraciones/funciones',
                'sort_order' => 20,
            ],
        ];

        $moduleIds = [$parent->id];
        foreach ($modules as $module) {
            $child = SystemModule::query()->updateOrCreate(
                ['slug' => $module['slug']],
                [
                    'name' => $module['name'],
                    'frontend_route' => $module['frontend_route'],
                    'icon' => null,
                    'sort_order' => $module['sort_order'],
                    'active' => true,
                    'parent_id' => $parent->id,
                ]
            );
            $moduleIds[] = $child->id;
        }

        foreach ($this->navigationSortUpdates() as $slug => $sortOrder) {
            SystemModule::query()
                ->where('slug', $slug)
                ->update(['sort_order' => $sortOrder, 'parent_id' => $parent->id]);
        }

        Role::query()
            ->whereIn('slug', ['super_admin', 'administrador', 'rrhh', 'remuneraciones_admin', 'remuneraciones_analista'])
            ->get()
            ->each(fn (Role $role) => $role->modules()->syncWithoutDetaching($moduleIds));
    }

    /**
     * @return array<int, string>
     */
    private function uniqueValues(string $key): array
    {
        return collect($this->rows)
            ->pluck($key)
            ->map(fn (string $value) => $this->normalize($value))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function normalize(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?: '';

        return mb_strtoupper($value, 'UTF-8');
    }

    private function comparisonKey(string $value): string
    {
        $value = Str::ascii($this->normalize($value));

        return preg_replace('/[^A-Z0-9]+/u', '', $value) ?: '';
    }

    /**
     * @return \Illuminate\Support\Collection<int, Cargo>
     */
    private function matchingCargos(string $name): \Illuminate\Support\Collection
    {
        $key = $this->comparisonKey($name);

        return Cargo::query()
            ->get()
            ->filter(fn (Cargo $cargo) => $this->comparisonKey($cargo->name) === $key)
            ->values();
    }

    private function cargoUsageCount(Cargo $cargo): int
    {
        if (!$cargo->exists) {
            return 0;
        }

        return $cargo->staff()->count() + $cargo->users()->count();
    }

    private function colorFor(int $index): string
    {
        $palette = [
            '#0d6efd',
            '#198754',
            '#6f42c1',
            '#dc3545',
            '#fd7e14',
            '#20c997',
            '#0dcaf0',
            '#495057',
        ];

        return $palette[$index % count($palette)];
    }

    /**
     * @return array<string, int>
     */
    private function navigationSortUpdates(): array
    {
        return [
            'remuneration_staff_management' => 18,
            'remuneration_departments' => 19,
            'remuneration_functions' => 20,
            'remuneration_documents' => 21,
            'remuneration_onboarding' => 22,
            'remuneration_climate' => 23,
            'remuneration_climate_plans' => 24,
            'remuneration_workload' => 25,
            'remuneration_cv_bank' => 26,
            'remuneration_replacements' => 27,
            'remuneration_job_profiles' => 28,
            'remuneration_certificates' => 29,
            'remuneration_audit' => 30,
        ];
    }
}
