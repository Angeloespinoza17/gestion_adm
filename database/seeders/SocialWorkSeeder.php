<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SocialWork\FormTemplate;
use App\Models\SocialWork\JunaebBenefitType;
use App\Models\SocialWork\ProgramType;
use App\Models\SocialWork\RiskRule;
use App\Models\SystemModule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SocialWorkSeeder extends Seeder
{
    private const SOCIAL_WORK_ROLE = 'trabajador_social';

    private const PRIVILEGED_PERMISSIONS = [
        'social_work.highly_confidential.view',
        'social_work.closed_case.correct',
        'social_work.cases.assign',
        'social_work.reports.approve',
        'social_work.templates.manage',
        'social_work.audit.view',
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedCatalogs();
            $this->seedRiskRules();
            $this->seedFormTemplates();
            $this->assignAccess();
        });
    }

    private function seedCatalogs(): void
    {
        $programs = [
            ['code' => 'junaeb', 'name' => 'JUNAEB', 'category' => 'beneficio'],
            ['code' => 'pro_retencion', 'name' => 'Pro Retención', 'category' => 'programa_externo'],
            ['code' => 'otro_externo', 'name' => 'Otro programa externo', 'category' => 'programa_externo'],
        ];

        foreach ($programs as $program) {
            ProgramType::updateOrCreate(
                ['code' => $program['code']],
                array_merge($program, ['active' => true, 'configuration' => []]),
            );
        }

        $benefits = [
            ['code' => 'utiles_escolares', 'name' => 'Útiles escolares', 'category' => 'utiles'],
            ['code' => 'servicio_medico', 'name' => 'Servicios médicos', 'category' => 'salud'],
        ];

        foreach ($benefits as $benefit) {
            JunaebBenefitType::updateOrCreate(
                ['code' => $benefit['code']],
                array_merge($benefit, ['active' => true, 'eligible_level_codes' => []]),
            );
        }
    }

    private function seedRiskRules(): void
    {
        $rules = [
            ['code' => 'asistencia_baja', 'name' => 'Asistencia bajo umbral', 'source' => 'attendance.percentage', 'operator' => '<', 'threshold' => 85, 'result_level' => 'alto'],
            ['code' => 'inasistencia_consecutiva', 'name' => 'Inasistencias consecutivas', 'source' => 'attendance.consecutive_absences', 'operator' => '>=', 'threshold' => 3, 'result_level' => 'alto'],
            ['code' => 'atrasos_periodo', 'name' => 'Atrasos en período', 'source' => 'late_arrivals', 'operator' => '>=', 'threshold' => 5, 'result_level' => 'medio'],
            ['code' => 'compromisos_vencidos', 'name' => 'Compromisos vencidos', 'source' => 'overdue_commitments', 'operator' => '>=', 'threshold' => 1, 'result_level' => 'alto'],
        ];

        foreach ($rules as $rule) {
            RiskRule::updateOrCreate(
                ['code' => $rule['code']],
                array_merge($rule, ['weight' => 1, 'active' => true, 'conditions' => []]),
            );
        }
    }

    private function seedFormTemplates(): void
    {
        foreach (['entrevista', 'visita_domiciliaria', 'llamado_telefonico', 'derivacion', 'acta_entrega', 'informe_social'] as $type) {
            $template = FormTemplate::updateOrCreate(
                ['code' => "borrador_{$type}"],
                [
                    'name' => 'BORRADOR BASE – '.str_replace('_', ' ', strtoupper($type)).' – REQUIERE VALIDACIÓN INSTITUCIONAL',
                    'type' => $type,
                    'active' => true,
                ],
            );

            if (! $template->versions()->exists()) {
                $template->versions()->create([
                    'version' => 1,
                    'status' => 'borrador',
                    'schema' => [
                        ['key' => 'objetivo', 'type' => 'textarea', 'label' => 'Objetivo', 'required' => true],
                        ['key' => 'observaciones', 'type' => 'textarea', 'label' => 'Observaciones', 'required' => false],
                    ],
                    'role_visibility' => [self::SOCIAL_WORK_ROLE],
                    'confidentiality' => 'restringido',
                    'header' => 'BORRADOR BASE – REQUIERE VALIDACIÓN INSTITUCIONAL',
                    'signature_blocks' => [],
                ]);
            }
        }
    }

    private function assignAccess(): void
    {
        $socialWorkRole = Role::updateOrCreate(
            ['slug' => self::SOCIAL_WORK_ROLE],
            [
                'name' => 'Trabajador/a Social',
                'description' => 'Gestión confidencial de casos e intervenciones sociales.',
                'active' => true,
            ],
        );

        $superAdminRole = Role::firstOrCreate(
            ['slug' => 'super_admin'],
            [
                'name' => 'Super Admin',
                'description' => 'Acceso total.',
                'active' => true,
            ],
        );

        $allPermissionIds = Permission::query()
            ->where('slug', 'like', 'social_work.%')
            ->pluck('id');

        $operationalPermissionIds = Permission::query()
            ->where('slug', 'like', 'social_work.%')
            ->whereNotIn('slug', self::PRIVILEGED_PERMISSIONS)
            ->pluck('id');

        $socialWorkRole->permissions()->syncWithoutDetaching($operationalPermissionIds);
        $superAdminRole->permissions()->syncWithoutDetaching($allPermissionIds);

        $submitPermissionId = Permission::query()->where('slug', 'social_work.referrals.submit')->value('id');
        $referringRoles = Role::query()->whereIn('slug', ['inspectoria', 'coordinador_inspectoria'])->get();
        if ($submitPermissionId) {
            $referringRoles->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$submitPermissionId]));
        }

        $module = SystemModule::query()->firstWhere('slug', 'social_work');
        if ($module) {
            $module->update([
                'name' => 'Trabajo Social',
                'icon' => 'bx-heart',
                'frontend_route' => null,
                'active' => true,
            ]);
            $moduleIds = SystemModule::query()
                ->where(fn ($query) => $query->whereKey($module->id)->orWhere('parent_id', $module->id))
                ->pluck('id');
            $socialWorkRole->modules()->syncWithoutDetaching($moduleIds);
            $superAdminRole->modules()->syncWithoutDetaching($moduleIds);
            $referralModuleIds = SystemModule::query()->whereIn('slug', ['social_work', 'social_work_referrals'])->pluck('id');
            $referringRoles->each(fn (Role $role) => $role->modules()->syncWithoutDetaching($referralModuleIds));
        }
    }
}
