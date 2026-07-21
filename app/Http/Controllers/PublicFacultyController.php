<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Staff;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PublicFacultyController extends Controller
{
    public function index(): View
    {
        $staff = $this->publicStaff();
        $teamGroups = $this->departmentGroups($staff);

        return view('public.pages.faculty', [
            'teamFilters' => $this->filters($teamGroups),
            'teamGroups' => $teamGroups,
            'staffCount' => $staff->count(),
            'departmentCount' => $teamGroups->count(),
        ]);
    }

    private function publicStaff(): Collection
    {
        if (! Schema::hasTable('staff') || ! Schema::hasTable('departments')) {
            return collect();
        }

        return Staff::query()
            ->with([
                'cargo:id,name,slug',
                'departments:id,name,slug,sort_order,color',
            ])
            ->where('active', true)
            ->where('institutional_email', 'like', '%@cnscvaldivia.cl')
            ->orderBy('full_name')
            ->get();
    }

    private function departmentGroups(Collection $staff): Collection
    {
        $departments = $staff
            ->flatMap(fn (Staff $person) => $person->departments)
            ->unique('id')
            ->sortBy([
                ['sort_order', 'asc'],
                ['name', 'asc'],
            ])
            ->values();

        $groups = $departments->map(function (Department $department) use ($staff): array {
            $members = $staff
                ->filter(fn (Staff $person) => $person->departments->contains('id', $department->id))
                ->sortBy(fn (Staff $person) => sprintf(
                    '%03d-%s-%s',
                    $this->rolePriority((string) optional($person->cargo)->name),
                    (string) optional($person->cargo)->name,
                    $person->full_name
                ))
                ->values()
                ->map(fn (Staff $person) => $this->cardFor($person, $department))
                ->values()
                ->all();

            $label = $this->displayLabel($department->name);

            return [
                'key' => "department-{$department->id}",
                'label' => $label,
                'eyebrow' => 'Departamento',
                'title' => $label,
                'description' => "Funcionarios asociados a {$label}.",
                'cards' => $members,
            ];
        });

        $withoutDepartment = $staff
            ->filter(fn (Staff $person) => $person->departments->isEmpty())
            ->sortBy('full_name')
            ->values();

        if ($withoutDepartment->isNotEmpty()) {
            $groups->push([
                'key' => 'department-unassigned',
                'label' => 'Sin Departamento',
                'eyebrow' => 'Pendiente de clasificación',
                'title' => 'Sin Departamento',
                'description' => 'Funcionarios activos que aún no tienen un departamento asociado.',
                'cards' => $withoutDepartment
                    ->map(fn (Staff $person) => $this->cardFor($person))
                    ->values()
                    ->all(),
            ]);
        }

        return $groups
            ->filter(fn (array $group) => ! empty($group['cards']))
            ->values();
    }

    private function filters(Collection $teamGroups): array
    {
        return collect([[
            'key' => 'all',
            'label' => 'Todos',
        ]])
            ->merge($teamGroups->map(fn (array $group) => [
                'key' => $group['key'],
                'label' => $group['label'],
            ]))
            ->values()
            ->all();
    }

    private function cardFor(Staff $staff, ?Department $department = null): array
    {
        return [
            'name' => $this->personName($staff->full_name),
            'role' => $this->displayLabel(optional($staff->cargo)->name ?: 'Funcionario/a'),
            'department' => $department ? $this->displayLabel($department->name) : null,
            'email' => $staff->institutional_email,
            'image' => $this->profileImage($staff),
            'icon' => $this->iconFor((string) optional($staff->cargo)->name, (string) optional($department)->name),
        ];
    }

    private function profileImage(Staff $staff): ?string
    {
        return $staff->profile_photo_url;
    }

    private function rolePriority(string $role): int
    {
        $key = $this->comparisonKey($role);

        return match (true) {
            str_contains($key, 'DIRECTOR') || str_contains($key, 'ADMINISTRADOR') => 10,
            str_contains($key, 'COORDINADOR') || str_contains($key, 'ORIENTADOR') => 20,
            str_contains($key, 'DOCENTE') || str_contains($key, 'EDUCADOR') => 30,
            str_contains($key, 'INSPECTOR') || str_contains($key, 'ASISTENTE') => 40,
            default => 50,
        };
    }

    private function iconFor(string $role, string $department): string
    {
        $key = $this->comparisonKey("{$role} {$department}");

        return match (true) {
            str_contains($key, 'DIRECTOR') => 'bi-diagram-3',
            str_contains($key, 'PASTORAL') || str_contains($key, 'CAPELLAN') || str_contains($key, 'RELIGION') => 'bi-stars',
            str_contains($key, 'CONVIVENCIA') || str_contains($key, 'INSPECTOR') => 'bi-shield-check',
            str_contains($key, 'AUXILIAR') || str_contains($key, 'MANTENCION') || str_contains($key, 'CALDERERO') => 'bi-tools',
            str_contains($key, 'ADMINISTRATIVO') || str_contains($key, 'CONTABILIDAD') || str_contains($key, 'RRHH') => 'bi-briefcase',
            str_contains($key, 'TENS') || str_contains($key, 'FONOAUDIOLOGO') || str_contains($key, 'PSICOLOGO') => 'bi-heart-pulse',
            default => 'bi-mortarboard',
        };
    }

    private function displayLabel(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 'Sin definir';
        }

        $overrides = [
            'ACLE' => 'ACLE',
            'APOYO UTP' => 'Apoyo UTP',
            'ED FISICA' => 'Ed. Física',
            'EDUCADOR A DIFERENCIAL' => 'Educador/a Diferencial',
            'EQUIPO DIRECTIVO' => 'Equipo Directivo',
            'FONOAUDIOLOGO A' => 'Fonoaudiólogo/a',
            'INFORMATICO' => 'Informático',
            'MATEMATICAS' => 'Matemáticas',
            'PIE' => 'PIE',
            'PORTERO A' => 'Portero/a',
            'PSICOLOGO A' => 'Psicólogo/a',
            'RRHH' => 'RRHH',
            'TENS' => 'TENS',
            'UTP' => 'UTP',
        ];

        $key = $this->comparisonKey($value);

        if (isset($overrides[$key])) {
            return $overrides[$key];
        }

        return mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private function personName(string $value): string
    {
        return mb_convert_case(mb_strtolower(trim($value), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private function comparisonKey(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', ' ')
            ->squish()
            ->toString();
    }
}
