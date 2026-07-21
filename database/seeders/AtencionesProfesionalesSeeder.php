<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ApoyoProfesional\ApoyoAdjunto;
use App\Models\ApoyoProfesional\ApoyoAtencion;
use App\Models\ApoyoProfesional\ApoyoConfigAttentionType;
use App\Models\ApoyoProfesional\ApoyoConfigMotivo;
use App\Models\ApoyoProfesional\ApoyoDerivacion;
use App\Models\ApoyoProfesional\ApoyoEntrevista;
use App\Models\ApoyoProfesional\ApoyoPlan;
use App\Models\ApoyoProfesional\ApoyoProfesionalProfile;
use App\Models\ApoyoProfesional\ApoyoSeguimiento;
use App\Models\Cargo;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemModule;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\ApoyoProfesional\ApoyoProfesionalAccessService;
use App\Services\ApoyoProfesional\ApoyoProfesionalAttentionService;
use App\Services\ApoyoProfesional\ApoyoProfesionalDerivationService;
use App\Services\ApoyoProfesional\ApoyoProfesionalPlanService;
use Carbon\Carbon;
use Database\Seeders\Modules\StaffModuleSeeder;
use Database\Seeders\Modules\StudentModuleSeeder;
use Database\Seeders\Support\ModuleSeeder;
use Database\Seeders\Support\PreventsProductionSeeding;
use Faker\Factory as Faker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AtencionesProfesionalesSeeder extends ModuleSeeder
{
    use PreventsProductionSeeding;

    private \Faker\Generator $faker;

    private User $actor;

    public function run(): void
    {
        $this->preventProductionSeeding();
        $this->faker = Faker::create('es_CL');
        $this->faker->seed(20260629);

        $this->call([
            RbacSeeder::class,
            ChileLocationSeeder::class,
            SchoolDepartmentSeeder::class,
            StudentModuleSeeder::class,
            StaffModuleSeeder::class,
        ]);

        $this->actor = $this->creator();

        $this->seedSupportRolesAndCargos();
        $this->seedPermissionsAndModules();
        $this->seedCatalogs();

        $professionals = $this->seedProfessionals();
        $students = $this->studentPool(24);
        $attentions = $this->seedAttentions($students, $professionals);

        $this->seedDerivations($attentions, $professionals);
        $this->seedFollowUps($attentions, $professionals);
        $this->seedPlans($students, $professionals);
        $this->seedInterviews($students, $professionals);
    }

    private function seedSupportRolesAndCargos(): void
    {
        $cargos = [
            ['slug' => 'terapeuta_ocupacional', 'name' => 'Terapeuta Ocupacional', 'description' => 'Equipo de apoyo terapéutico.'],
            ['slug' => 'trabajador_social', 'name' => 'Trabajador/a Social', 'description' => 'Trabajo social escolar.'],
            ['slug' => 'psicopedagogo', 'name' => 'Psicopedagogo/a', 'description' => 'Apoyo psicopedagógico.'],
            ['slug' => 'fonoaudiologo', 'name' => 'Fonoaudiólogo/a', 'description' => 'Apoyo fonoaudiológico.'],
            ['slug' => 'orientador', 'name' => 'Orientador/a', 'description' => 'Orientación escolar.'],
            ['slug' => 'profesional_pie', 'name' => 'Profesional PIE', 'description' => 'Programa de Integración Escolar.'],
            ['slug' => 'coordinador_pie', 'name' => 'Coordinador/a PIE', 'description' => 'Coordinación PIE.'],
            ['slug' => 'convivencia_escolar', 'name' => 'Encargado/a de Convivencia Escolar', 'description' => 'Convivencia escolar.'],
        ];

        foreach ($cargos as $cargo) {
            Cargo::query()->updateOrCreate(
                ['slug' => $cargo['slug']],
                [
                    'name' => $cargo['name'],
                    'description' => $cargo['description'],
                    'active' => true,
                ],
            );
        }

        $roles = [
            ['slug' => 'terapeuta_ocupacional', 'name' => 'Terapeuta Ocupacional', 'description' => 'Registro de atenciones de terapia ocupacional.'],
            ['slug' => 'trabajador_social', 'name' => 'Trabajador/a Social', 'description' => 'Registro de atenciones de trabajo social.'],
            ['slug' => 'psicopedagogo', 'name' => 'Psicopedagogo/a', 'description' => 'Registro de atenciones psicopedagógicas.'],
            ['slug' => 'fonoaudiologo', 'name' => 'Fonoaudiólogo/a', 'description' => 'Registro de atenciones de fonoaudiología.'],
            ['slug' => 'orientador', 'name' => 'Orientador/a', 'description' => 'Registro de atenciones de orientación.'],
            ['slug' => 'profesional_pie', 'name' => 'Profesional PIE', 'description' => 'Registro de atenciones PIE.'],
            ['slug' => 'coordinador_pie', 'name' => 'Coordinador/a PIE', 'description' => 'Coordinación y seguimiento PIE.'],
            ['slug' => 'convivencia_escolar', 'name' => 'Convivencia Escolar', 'description' => 'Gestión de casos y derivaciones de convivencia.'],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'active' => true,
                ],
            );
        }
    }

    private function seedPermissionsAndModules(): void
    {
        $permissions = [
            ['slug' => ApoyoProfesionalAccessService::VIEW_PERMISSION, 'name' => 'Ver módulo Equipo de Apoyo'],
            ['slug' => ApoyoProfesionalAccessService::CREATE_ATTENTION_PERMISSION, 'name' => 'Crear atención profesional'],
            ['slug' => ApoyoProfesionalAccessService::EDIT_OWN_ATTENTION_PERMISSION, 'name' => 'Editar atención propia'],
            ['slug' => ApoyoProfesionalAccessService::EDIT_ANY_ATTENTION_PERMISSION, 'name' => 'Editar cualquier atención'],
            ['slug' => ApoyoProfesionalAccessService::DELETE_ATTENTION_PERMISSION, 'name' => 'Eliminar atención'],
            ['slug' => ApoyoProfesionalAccessService::VIEW_OWN_ATTENTIONS_PERMISSION, 'name' => 'Ver atenciones propias'],
            ['slug' => ApoyoProfesionalAccessService::VIEW_TEAM_ATTENTIONS_PERMISSION, 'name' => 'Ver atenciones del equipo'],
            ['slug' => ApoyoProfesionalAccessService::VIEW_CONFIDENTIAL_ATTENTIONS_PERMISSION, 'name' => 'Ver atenciones confidenciales'],
            ['slug' => ApoyoProfesionalAccessService::CREATE_DERIVATION_PERMISSION, 'name' => 'Crear derivación interna'],
            ['slug' => ApoyoProfesionalAccessService::RESPOND_DERIVATION_PERMISSION, 'name' => 'Responder derivación interna'],
            ['slug' => ApoyoProfesionalAccessService::CREATE_FOLLOW_UP_PERMISSION, 'name' => 'Crear seguimiento'],
            ['slug' => ApoyoProfesionalAccessService::CLOSE_CASE_PERMISSION, 'name' => 'Cerrar caso de apoyo'],
            ['slug' => ApoyoProfesionalAccessService::CREATE_PLAN_PERMISSION, 'name' => 'Crear plan de apoyo'],
            ['slug' => ApoyoProfesionalAccessService::VIEW_REPORTS_PERMISSION, 'name' => 'Ver reportes del módulo'],
            ['slug' => ApoyoProfesionalAccessService::EXPORT_REPORTS_PERMISSION, 'name' => 'Exportar reportes del módulo'],
            ['slug' => ApoyoProfesionalAccessService::MANAGE_CONFIGURATION_PERMISSION, 'name' => 'Administrar configuración del módulo'],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'description' => 'Permiso del módulo Atenciones Profesionales / Equipo de Apoyo.',
                    'active' => true,
                ],
            );
        }

        $parent = SystemModule::query()->updateOrCreate(
            ['slug' => 'apoyo_profesional'],
            [
                'name' => 'Equipo de Apoyo',
                'frontend_route' => null,
                'icon' => 'bx-heart-circle',
                'sort_order' => 56,
                'active' => true,
                'parent_id' => null,
            ],
        );

        $children = [
            ['slug' => 'apoyo_profesional_dashboard', 'name' => 'Dashboard', 'route' => '/apoyo-profesional', 'sort' => 1],
            ['slug' => 'apoyo_profesional_attentions', 'name' => 'Atenciones', 'route' => '/apoyo-profesional/atenciones', 'sort' => 2],
            ['slug' => 'apoyo_profesional_history', 'name' => 'Ficha estudiante', 'route' => '/apoyo-profesional/historial', 'sort' => 3],
            ['slug' => 'apoyo_profesional_derivations', 'name' => 'Derivaciones', 'route' => '/apoyo-profesional/derivaciones', 'sort' => 4],
            ['slug' => 'apoyo_profesional_followups', 'name' => 'Seguimientos', 'route' => '/apoyo-profesional/seguimientos', 'sort' => 5],
            ['slug' => 'apoyo_profesional_plans', 'name' => 'Planes de apoyo', 'route' => '/apoyo-profesional/planes', 'sort' => 6],
            ['slug' => 'apoyo_profesional_interviews', 'name' => 'Entrevistas', 'route' => '/apoyo-profesional/entrevistas', 'sort' => 7],
            ['slug' => 'apoyo_profesional_documents', 'name' => 'Documentos', 'route' => '/apoyo-profesional/documentos', 'sort' => 8],
            ['slug' => 'apoyo_profesional_reports', 'name' => 'Reportes', 'route' => '/apoyo-profesional/reportes', 'sort' => 9],
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

        $permissionIds = Permission::query()
            ->whereIn('slug', array_column($permissions, 'slug'))
            ->pluck('id', 'slug');
        $moduleIds = SystemModule::query()
            ->whereIn('slug', array_merge(['apoyo_profesional'], array_column($children, 'slug')))
            ->pluck('id', 'slug');

        $fullAccessPermissions = array_keys($permissionIds->all());
        $professionalPermissions = [
            ApoyoProfesionalAccessService::VIEW_PERMISSION,
            ApoyoProfesionalAccessService::CREATE_ATTENTION_PERMISSION,
            ApoyoProfesionalAccessService::EDIT_OWN_ATTENTION_PERMISSION,
            ApoyoProfesionalAccessService::VIEW_OWN_ATTENTIONS_PERMISSION,
            ApoyoProfesionalAccessService::CREATE_DERIVATION_PERMISSION,
            ApoyoProfesionalAccessService::RESPOND_DERIVATION_PERMISSION,
            ApoyoProfesionalAccessService::CREATE_FOLLOW_UP_PERMISSION,
            ApoyoProfesionalAccessService::CLOSE_CASE_PERMISSION,
            ApoyoProfesionalAccessService::CREATE_PLAN_PERMISSION,
        ];
        $leadPermissions = array_merge($professionalPermissions, [
            ApoyoProfesionalAccessService::VIEW_TEAM_ATTENTIONS_PERMISSION,
            ApoyoProfesionalAccessService::VIEW_CONFIDENTIAL_ATTENTIONS_PERMISSION,
            ApoyoProfesionalAccessService::VIEW_REPORTS_PERMISSION,
            ApoyoProfesionalAccessService::EXPORT_REPORTS_PERMISSION,
        ]);

        $rolePermissions = [
            'super_admin' => $fullAccessPermissions,
            'administrador' => $fullAccessPermissions,
            'direccion' => $fullAccessPermissions,
            'coordinador_academico' => [
                ApoyoProfesionalAccessService::VIEW_PERMISSION,
                ApoyoProfesionalAccessService::VIEW_TEAM_ATTENTIONS_PERMISSION,
                ApoyoProfesionalAccessService::CREATE_DERIVATION_PERMISSION,
                ApoyoProfesionalAccessService::RESPOND_DERIVATION_PERMISSION,
                ApoyoProfesionalAccessService::VIEW_REPORTS_PERMISSION,
                ApoyoProfesionalAccessService::EXPORT_REPORTS_PERMISSION,
            ],
            'psicologo' => $professionalPermissions,
            'trabajador_social' => $professionalPermissions,
            'terapeuta_ocupacional' => $professionalPermissions,
            'psicopedagogo' => $professionalPermissions,
            'fonoaudiologo' => $professionalPermissions,
            'orientador' => $professionalPermissions,
            'profesional_pie' => $professionalPermissions,
            'coordinador_pie' => $leadPermissions,
            'convivencia_escolar' => $leadPermissions,
            'inspectoria' => [
                ApoyoProfesionalAccessService::VIEW_PERMISSION,
                ApoyoProfesionalAccessService::CREATE_DERIVATION_PERMISSION,
                ApoyoProfesionalAccessService::RESPOND_DERIVATION_PERMISSION,
            ],
        ];

        $roleModules = [
            'super_admin' => $moduleIds->keys()->all(),
            'administrador' => $moduleIds->keys()->all(),
            'direccion' => $moduleIds->keys()->all(),
            'coordinador_academico' => $moduleIds->keys()->all(),
            'psicologo' => $moduleIds->keys()->all(),
            'trabajador_social' => $moduleIds->keys()->all(),
            'terapeuta_ocupacional' => $moduleIds->keys()->all(),
            'psicopedagogo' => $moduleIds->keys()->all(),
            'fonoaudiologo' => $moduleIds->keys()->all(),
            'orientador' => $moduleIds->keys()->all(),
            'profesional_pie' => $moduleIds->keys()->all(),
            'coordinador_pie' => $moduleIds->keys()->all(),
            'convivencia_escolar' => $moduleIds->keys()->all(),
            'inspectoria' => ['apoyo_profesional', 'apoyo_profesional_dashboard', 'apoyo_profesional_derivations', 'apoyo_profesional_history'],
        ];

        foreach ($rolePermissions as $roleSlug => $permissionSlugs) {
            $role = Role::query()->firstWhere('slug', $roleSlug);

            if (!$role) {
                continue;
            }

            $role->permissions()->syncWithoutDetaching(
                collect($permissionSlugs)
                    ->map(fn (string $slug) => $permissionIds[$slug] ?? null)
                    ->filter()
                    ->all()
            );

            $role->modules()->syncWithoutDetaching(
                collect($roleModules[$roleSlug] ?? [])
                    ->map(fn (string $slug) => $moduleIds[$slug] ?? null)
                    ->filter()
                    ->all()
            );
        }
    }

    private function seedCatalogs(): void
    {
        $types = [
            ['slug' => 'individual', 'name' => 'Individual'],
            ['slug' => 'grupal', 'name' => 'Grupal'],
            ['slug' => 'familiar', 'name' => 'Familiar'],
            ['slug' => 'con_apoderado', 'name' => 'Con apoderado'],
            ['slug' => 'con_profesor_jefe', 'name' => 'Con profesor jefe'],
            ['slug' => 'con_docente', 'name' => 'Con docente'],
            ['slug' => 'en_aula', 'name' => 'En aula'],
            ['slug' => 'en_patio', 'name' => 'En patio'],
            ['slug' => 'en_oficina', 'name' => 'En oficina'],
            ['slug' => 'entrevista', 'name' => 'Entrevista'],
            ['slug' => 'observacion', 'name' => 'Observación'],
            ['slug' => 'evaluacion', 'name' => 'Evaluación'],
            ['slug' => 'seguimiento', 'name' => 'Seguimiento'],
            ['slug' => 'contencion', 'name' => 'Contención'],
            ['slug' => 'coordinacion', 'name' => 'Coordinación'],
            ['slug' => 'visita_domiciliaria', 'name' => 'Visita domiciliaria'],
            ['slug' => 'otra', 'name' => 'Otra', 'requires_other_description' => true],
        ];

        foreach ($types as $index => $type) {
            ApoyoConfigAttentionType::query()->updateOrCreate(
                ['slug' => $type['slug']],
                [
                    'name' => $type['name'],
                    'requires_other_description' => $type['requires_other_description'] ?? false,
                    'active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }

        $motives = [
            ['slug' => 'contencion_emocional', 'name' => 'Contención emocional', 'area_slug' => 'psicologia'],
            ['slug' => 'convivencia_escolar', 'name' => 'Conflicto de convivencia escolar', 'area_slug' => 'convivencia_escolar'],
            ['slug' => 'dificultad_aprendizaje', 'name' => 'Dificultades de aprendizaje', 'area_slug' => 'psicopedagogia'],
            ['slug' => 'acompanamiento_familiar', 'name' => 'Acompañamiento familiar', 'area_slug' => 'trabajo_social'],
            ['slug' => 'adaptacion_escolar', 'name' => 'Adaptación escolar', 'area_slug' => 'orientacion'],
            ['slug' => 'evaluacion_pie', 'name' => 'Evaluación PIE', 'area_slug' => 'pie'],
            ['slug' => 'seguimiento_fonoaudiologia', 'name' => 'Seguimiento fonoaudiológico', 'area_slug' => 'fonoaudiologia'],
            ['slug' => 'habilidades_funcionales', 'name' => 'Habilidades funcionales', 'area_slug' => 'terapia_ocupacional'],
            ['slug' => 'inasistencia_reiterada', 'name' => 'Inasistencia reiterada', 'area_slug' => 'trabajo_social'],
            ['slug' => 'desregulacion', 'name' => 'Desregulación socioemocional', 'area_slug' => 'psicologia'],
            ['slug' => 'derivacion_docente', 'name' => 'Derivación docente', 'area_slug' => null],
            ['slug' => 'solicitud_apoderado', 'name' => 'Solicitud de apoderado', 'area_slug' => null],
        ];

        foreach ($motives as $index => $motive) {
            ApoyoConfigMotivo::query()->updateOrCreate(
                ['slug' => $motive['slug']],
                [
                    'name' => $motive['name'],
                    'area_slug' => $motive['area_slug'],
                    'description' => 'Motivo de apoyo profesional generado para ambiente de pruebas.',
                    'active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }

    private function seedProfessionals(): Collection
    {
        $region = $this->region('Los Ríos') ?: $this->region('Los Rios');
        $commune = $this->commune('Valdivia');

        $baseLocation = [
            'region' => $region?->name ?? 'Los Ríos',
            'region_id' => $region?->id,
            'commune' => $commune?->name ?? 'Valdivia',
            'commune_id' => $commune?->id,
            'address' => 'Av. Ramón Picarte 1450, Valdivia',
        ];

        $definitions = [
            [
                'email' => 'camila.soto@cnscgestion.local',
                'password' => 'Psico123!',
                'roles' => ['psicologo'],
                'departments' => ['psicologia'],
                'staff' => array_merge($baseLocation, [
                    'full_name' => 'Camila Soto Benavides',
                    'rut' => '21111111-1',
                    'birth_date' => '1991-12-04',
                    'institutional_email' => 'camila.soto@cnscgestion.local',
                    'personal_email' => 'camila.soto@example.com',
                    'phone' => '+56961000010',
                    'cargo_slug' => 'psicologo',
                    'contract_type' => 'indefinido',
                    'start_date' => '2024-03-01',
                    'status' => 'activo',
                    'workday' => 'parcial',
                    'contract_hours' => 33,
                    'professional_title' => 'Psicóloga educacional',
                    'specialty' => 'Apoyo socioemocional',
                ]),
                'profile' => [
                    'area_slug' => 'psicologia',
                    'area_name' => 'Psicología',
                    'professional_role_slug' => 'psicologo',
                    'professional_role_name' => 'Psicóloga',
                    'can_receive_derivations' => true,
                    'can_manage_confidential_cases' => true,
                ],
            ],
            [
                'email' => 'valentina.navarro@cnscgestion.local',
                'password' => 'Apoyo123!',
                'roles' => ['trabajador_social'],
                'departments' => ['convivencia-escolar'],
                'staff' => array_merge($baseLocation, [
                    'full_name' => 'Valentina Navarro Morales',
                    'rut' => '23333333-3',
                    'birth_date' => '1989-06-18',
                    'institutional_email' => 'valentina.navarro@cnscgestion.local',
                    'personal_email' => 'valentina.navarro@example.com',
                    'phone' => '+56962000001',
                    'cargo_slug' => 'trabajador_social',
                    'contract_type' => 'indefinido',
                    'start_date' => '2023-03-01',
                    'status' => 'activo',
                    'workday' => 'completa',
                    'contract_hours' => 44,
                    'professional_title' => 'Trabajadora social',
                    'specialty' => 'Vinculación familia-escuela',
                ]),
                'profile' => [
                    'area_slug' => 'trabajo_social',
                    'area_name' => 'Trabajo social',
                    'professional_role_slug' => 'trabajador_social',
                    'professional_role_name' => 'Trabajadora social',
                    'can_receive_derivations' => true,
                    'can_manage_confidential_cases' => false,
                ],
            ],
            [
                'email' => 'daniela.arriagada@cnscgestion.local',
                'password' => 'Apoyo123!',
                'roles' => ['terapeuta_ocupacional'],
                'departments' => ['pie'],
                'staff' => array_merge($baseLocation, [
                    'full_name' => 'Daniela Arriagada Paredes',
                    'rut' => '24444444-4',
                    'birth_date' => '1990-09-11',
                    'institutional_email' => 'daniela.arriagada@cnscgestion.local',
                    'personal_email' => 'daniela.arriagada@example.com',
                    'phone' => '+56962000002',
                    'cargo_slug' => 'terapeuta_ocupacional',
                    'contract_type' => 'indefinido',
                    'start_date' => '2024-03-01',
                    'status' => 'activo',
                    'workday' => 'parcial',
                    'contract_hours' => 33,
                    'professional_title' => 'Terapeuta ocupacional',
                    'specialty' => 'Autonomía y participación escolar',
                ]),
                'profile' => [
                    'area_slug' => 'terapia_ocupacional',
                    'area_name' => 'Terapia ocupacional',
                    'professional_role_slug' => 'terapeuta_ocupacional',
                    'professional_role_name' => 'Terapeuta ocupacional',
                    'can_receive_derivations' => true,
                    'can_manage_confidential_cases' => false,
                ],
            ],
            [
                'email' => 'fernanda.mella@cnscgestion.local',
                'password' => 'Apoyo123!',
                'roles' => ['psicopedagogo'],
                'departments' => ['pie'],
                'staff' => array_merge($baseLocation, [
                    'full_name' => 'Fernanda Mella Cifuentes',
                    'rut' => '25555555-5',
                    'birth_date' => '1993-02-14',
                    'institutional_email' => 'fernanda.mella@cnscgestion.local',
                    'personal_email' => 'fernanda.mella@example.com',
                    'phone' => '+56962000003',
                    'cargo_slug' => 'psicopedagogo',
                    'contract_type' => 'indefinido',
                    'start_date' => '2024-03-01',
                    'status' => 'activo',
                    'workday' => 'parcial',
                    'contract_hours' => 30,
                    'professional_title' => 'Psicopedagoga',
                    'specialty' => 'Apoyo al aprendizaje',
                ]),
                'profile' => [
                    'area_slug' => 'psicopedagogia',
                    'area_name' => 'Psicopedagogía',
                    'professional_role_slug' => 'psicopedagogo',
                    'professional_role_name' => 'Psicopedagoga',
                    'can_receive_derivations' => true,
                    'can_manage_confidential_cases' => false,
                ],
            ],
            [
                'email' => 'rocio.sepulveda@cnscgestion.local',
                'password' => 'Apoyo123!',
                'roles' => ['fonoaudiologo'],
                'departments' => ['pie'],
                'staff' => array_merge($baseLocation, [
                    'full_name' => 'Rocío Sepúlveda Lagos',
                    'rut' => '26666666-6',
                    'birth_date' => '1992-05-09',
                    'institutional_email' => 'rocio.sepulveda@cnscgestion.local',
                    'personal_email' => 'rocio.sepulveda@example.com',
                    'phone' => '+56962000004',
                    'cargo_slug' => 'fonoaudiologo',
                    'contract_type' => 'indefinido',
                    'start_date' => '2024-03-01',
                    'status' => 'activo',
                    'workday' => 'parcial',
                    'contract_hours' => 28,
                    'professional_title' => 'Fonoaudióloga',
                    'specialty' => 'Lenguaje y comunicación',
                ]),
                'profile' => [
                    'area_slug' => 'fonoaudiologia',
                    'area_name' => 'Fonoaudiología',
                    'professional_role_slug' => 'fonoaudiologo',
                    'professional_role_name' => 'Fonoaudióloga',
                    'can_receive_derivations' => true,
                    'can_manage_confidential_cases' => false,
                ],
            ],
            [
                'email' => 'javiera.cardenas@cnscgestion.local',
                'password' => 'Apoyo123!',
                'roles' => ['orientador'],
                'departments' => ['orientacion'],
                'staff' => array_merge($baseLocation, [
                    'full_name' => 'Javiera Cárdenas Rivas',
                    'rut' => '27777777-7',
                    'birth_date' => '1988-08-22',
                    'institutional_email' => 'javiera.cardenas@cnscgestion.local',
                    'personal_email' => 'javiera.cardenas@example.com',
                    'phone' => '+56962000005',
                    'cargo_slug' => 'orientador',
                    'contract_type' => 'indefinido',
                    'start_date' => '2023-03-01',
                    'status' => 'activo',
                    'workday' => 'parcial',
                    'contract_hours' => 32,
                    'professional_title' => 'Orientadora educacional',
                    'specialty' => 'Trayectoria formativa',
                ]),
                'profile' => [
                    'area_slug' => 'orientacion',
                    'area_name' => 'Orientación',
                    'professional_role_slug' => 'orientador',
                    'professional_role_name' => 'Orientadora',
                    'can_receive_derivations' => true,
                    'can_manage_confidential_cases' => false,
                ],
            ],
            [
                'email' => 'andrea.pino@cnscgestion.local',
                'password' => 'Apoyo123!',
                'roles' => ['profesional_pie'],
                'departments' => ['pie'],
                'staff' => array_merge($baseLocation, [
                    'full_name' => 'Andrea Pino Zamora',
                    'rut' => '28888888-8',
                    'birth_date' => '1991-01-17',
                    'institutional_email' => 'andrea.pino@cnscgestion.local',
                    'personal_email' => 'andrea.pino@example.com',
                    'phone' => '+56962000006',
                    'cargo_slug' => 'profesional_pie',
                    'contract_type' => 'indefinido',
                    'start_date' => '2024-03-01',
                    'status' => 'activo',
                    'workday' => 'parcial',
                    'contract_hours' => 34,
                    'professional_title' => 'Educadora diferencial',
                    'specialty' => 'Adecuaciones curriculares',
                ]),
                'profile' => [
                    'area_slug' => 'pie',
                    'area_name' => 'PIE',
                    'professional_role_slug' => 'profesional_pie',
                    'professional_role_name' => 'Profesional PIE',
                    'can_receive_derivations' => true,
                    'can_manage_confidential_cases' => true,
                ],
            ],
            [
                'email' => 'claudia.palma@cnscgestion.local',
                'password' => 'Apoyo123!',
                'roles' => ['coordinador_pie'],
                'departments' => ['pie'],
                'staff' => array_merge($baseLocation, [
                    'full_name' => 'Claudia Palma Orellana',
                    'rut' => '29999999-9',
                    'birth_date' => '1985-04-06',
                    'institutional_email' => 'claudia.palma@cnscgestion.local',
                    'personal_email' => 'claudia.palma@example.com',
                    'phone' => '+56962000007',
                    'cargo_slug' => 'coordinador_pie',
                    'contract_type' => 'indefinido',
                    'start_date' => '2022-03-01',
                    'status' => 'activo',
                    'workday' => 'completa',
                    'contract_hours' => 44,
                    'professional_title' => 'Coordinadora PIE',
                    'specialty' => 'Gestión de apoyos especializados',
                ]),
                'profile' => [
                    'area_slug' => 'pie',
                    'area_name' => 'PIE',
                    'professional_role_slug' => 'coordinador_pie',
                    'professional_role_name' => 'Coordinadora PIE',
                    'can_receive_derivations' => true,
                    'can_manage_confidential_cases' => true,
                ],
            ],
            [
                'email' => 'veronica.salas@cnscgestion.local',
                'password' => 'Apoyo123!',
                'roles' => ['convivencia_escolar'],
                'departments' => ['convivencia-escolar'],
                'staff' => array_merge($baseLocation, [
                    'full_name' => 'Verónica Salas Riquelme',
                    'rut' => '30111111-1',
                    'birth_date' => '1987-11-03',
                    'institutional_email' => 'veronica.salas@cnscgestion.local',
                    'personal_email' => 'veronica.salas@example.com',
                    'phone' => '+56962000008',
                    'cargo_slug' => 'convivencia_escolar',
                    'contract_type' => 'indefinido',
                    'start_date' => '2023-03-01',
                    'status' => 'activo',
                    'workday' => 'completa',
                    'contract_hours' => 44,
                    'professional_title' => 'Encargada de convivencia escolar',
                    'specialty' => 'Mediación y convivencia',
                ]),
                'profile' => [
                    'area_slug' => 'convivencia_escolar',
                    'area_name' => 'Convivencia Escolar',
                    'professional_role_slug' => 'convivencia_escolar',
                    'professional_role_name' => 'Encargada de convivencia',
                    'can_receive_derivations' => true,
                    'can_manage_confidential_cases' => true,
                ],
            ],
        ];

        $records = collect($definitions)->map(function (array $definition) {
            $result = $this->upsertStaffUser(
                $definition['staff'],
                [
                    'email' => $definition['email'],
                    'password' => $definition['password'],
                ],
                $definition['roles'],
                $definition['departments'],
            );

            $profile = ApoyoProfesionalProfile::query()->updateOrCreate(
                [
                    'user_id' => $result['user']->id,
                    'area_slug' => $definition['profile']['area_slug'],
                ],
                array_merge($definition['profile'], [
                    'staff_id' => $result['staff']->id,
                    'active' => true,
                    'notes' => 'Perfil profesional de apoyo generado por seeder.',
                    'created_by' => $this->actor->id,
                    'updated_by' => $this->actor->id,
                ]),
            );

            return [
                'user' => $result['user']->fresh(['roles', 'staff.cargo']),
                'staff' => $result['staff']->fresh(),
                'profile' => $profile->fresh(['user', 'staff']),
            ];
        });

        $carolina = User::query()->where('email', 'carolina.munoz@cnscgestion.local')->first();
        if ($carolina?->staff) {
            $records->push([
                'user' => $carolina->fresh(['roles', 'staff.cargo']),
                'staff' => $carolina->staff,
                'profile' => ApoyoProfesionalProfile::query()->updateOrCreate(
                    ['user_id' => $carolina->id, 'area_slug' => 'direccion'],
                    [
                        'staff_id' => $carolina->staff_id,
                        'area_name' => 'Dirección',
                        'professional_role_slug' => 'direccion',
                        'professional_role_name' => 'Directora',
                        'can_receive_derivations' => true,
                        'can_manage_confidential_cases' => true,
                        'active' => true,
                        'notes' => 'Perfil de dirección para cierre y derivaciones de prueba.',
                        'created_by' => $this->actor->id,
                        'updated_by' => $this->actor->id,
                    ],
                )->fresh(['user', 'staff']),
            ]);
        }

        $paula = User::query()->where('email', 'paula.vargas@cnscgestion.local')->first();
        if ($paula?->staff) {
            $records->push([
                'user' => $paula->fresh(['roles', 'staff.cargo']),
                'staff' => $paula->staff,
                'profile' => ApoyoProfesionalProfile::query()->updateOrCreate(
                    ['user_id' => $paula->id, 'area_slug' => 'utp'],
                    [
                        'staff_id' => $paula->staff_id,
                        'area_name' => 'UTP',
                        'professional_role_slug' => 'coordinador_academico',
                        'professional_role_name' => 'Coordinadora UTP',
                        'can_receive_derivations' => true,
                        'can_manage_confidential_cases' => true,
                        'active' => true,
                        'notes' => 'Perfil UTP para derivaciones internas de prueba.',
                        'created_by' => $this->actor->id,
                        'updated_by' => $this->actor->id,
                    ],
                )->fresh(['user', 'staff']),
            ]);
        }

        return $records->values();
    }

    private function studentPool(int $limit): Collection
    {
        $activeYear = AcademicYear::query()->where('is_active', true)->firstOrFail();

        return StudentProfile::query()
            ->with([
                'enrollments' => fn ($query) => $query
                    ->where('academic_year_id', $activeYear->id)
                    ->with(['academicYear', 'courseSection.educationLevel']),
            ])
            ->whereHas('enrollments', fn ($query) => $query->where('academic_year_id', $activeYear->id))
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    private function seedAttentions(Collection $students, Collection $professionals): Collection
    {
        $service = app(ApoyoProfesionalAttentionService::class);
        $types = ApoyoConfigAttentionType::query()->get()->keyBy('slug');
        $motives = ApoyoConfigMotivo::query()->get()->keyBy('slug');
        $modalityCycle = ['presencial', 'telefonica', 'online', 'correo', 'reunion_interna', 'otra'];
        $originCycle = [
            'solicitud_profesor_jefe',
            'derivacion_convivencia',
            'derivacion_utp',
            'derivacion_pie',
            'derivacion_direccion',
            'solicitud_apoderado',
            'solicitud_estudiante',
            'observacion_profesional',
            'seguimiento_previo',
            'otro',
        ];
        $priorityCycle = ['baja', 'media', 'alta', 'urgente'];
        $motiveCycle = [
            'contencion_emocional',
            'convivencia_escolar',
            'dificultad_aprendizaje',
            'acompanamiento_familiar',
            'adaptacion_escolar',
            'evaluacion_pie',
            'seguimiento_fonoaudiologia',
            'habilidades_funcionales',
            'inasistencia_reiterada',
            'desregulacion',
            'derivacion_docente',
            'solicitud_apoderado',
        ];
        $typeCycle = [
            'individual',
            'grupal',
            'familiar',
            'con_apoderado',
            'con_profesor_jefe',
            'entrevista',
            'observacion',
            'evaluacion',
            'seguimiento',
            'contencion',
            'coordinacion',
            'visita_domiciliaria',
            'otra',
        ];

        $attentions = collect();

        foreach (range(1, 36) as $index) {
            $record = $professionals[($index - 1) % max($professionals->count(), 1)];
            $student = $students[($index - 1) % max($students->count(), 1)];
            $attendedAt = now()->copy()->subDays(($index - 1) * 9)->setHour(8 + ($index % 9))->setMinute(($index * 7) % 60);
            $typeSlug = $typeCycle[($index - 1) % count($typeCycle)];
            $motiveSlug = $motiveCycle[($index - 1) % count($motiveCycle)];
            $confidentiality = match (true) {
                $index % 9 === 0 => 'alta_confidencialidad',
                $index % 5 === 0 => 'confidencial',
                $index % 2 === 0 => 'reservada',
                default => 'general',
            };

            $payload = [
                'student_profile_id' => $student->id,
                'attended_at' => $attendedAt->format('Y-m-d H:i:s'),
                'attention_type_id' => $types[$typeSlug]?->id,
                'motive_id' => $motives[$motiveSlug]?->id,
                'attention_type_other' => $typeSlug === 'otra' ? 'Intervención especial coordinada con red externa.' : null,
                'modality' => $modalityCycle[($index - 1) % count($modalityCycle)],
                'modality_other' => $modalityCycle[($index - 1) % count($modalityCycle)] === 'otra' ? 'Videollamada de seguimiento institucional.' : null,
                'origin' => $originCycle[($index - 1) % count($originCycle)],
                'origin_other' => $originCycle[($index - 1) % count($originCycle)] === 'otro' ? 'Solicitud derivada desde coordinación de ciclo.' : null,
                'priority_level' => $priorityCycle[($index - 1) % count($priorityCycle)],
                'confidentiality_level' => $confidentiality,
                'reason_summary' => sprintf('Caso de apoyo %02d: %s', $index, $motives[$motiveSlug]?->name ?? 'Seguimiento'),
                'description' => $this->faker->sentence(14),
                'professional_observations' => $this->faker->paragraph(2),
                'agreements' => $this->faker->sentence(10),
                'recommendations' => $this->faker->sentence(12),
                'next_action' => $this->faker->sentence(8),
                'status' => $index % 11 === 0 ? 'borrador' : ($index % 3 === 0 ? 'en_seguimiento' : 'abierta'),
            ];

            $attention = ApoyoAtencion::query()
                ->where('student_profile_id', $student->id)
                ->where('reason_summary', $payload['reason_summary'])
                ->where('attended_at', $payload['attended_at'])
                ->first();

            $attention = $attention
                ? $service->update($attention, $payload, $record['user'])
                : $service->store($payload, $record['user']);

            if ($index % 4 === 0 && $attention->status !== 'anulada') {
                $attention = $service->close($attention, $record['user'], [
                    'case_closed_notes' => 'Caso cerrado luego de revisión y cumplimiento de acuerdos.',
                ]);
            }

            if ($index % 13 === 0) {
                $attention = $service->update($attention, array_merge($payload, ['status' => 'anulada']), $record['user']);
            }

            if ($index <= 12) {
                $this->attachDocument(
                    $attention,
                    $student->id,
                    $record['user']->id,
                    $index % 2 === 0 ? 'informe' : 'pdf',
                    sprintf('atencion_%02d.%s', $index, $index % 2 === 0 ? 'pdf' : 'txt'),
                    'Documento de respaldo asociado a la atención.',
                    $confidentiality
                );
            }

            $attentions->push($attention);
        }

        return $attentions->values();
    }

    private function seedDerivations(Collection $attentions, Collection $professionals): void
    {
        $service = app(ApoyoProfesionalDerivationService::class);
        $areas = ['convivencia_escolar', 'pie', 'direccion', 'orientacion', 'trabajo_social', 'utp'];

        foreach ($attentions->take(14)->values() as $index => $attention) {
            if (in_array($attention->status, ['cerrada', 'anulada'], true)) {
                continue;
            }

            $destinationArea = $areas[$index % count($areas)];
            $destination = $this->professionalForArea($professionals, $destinationArea);
            $creator = $this->professionalForArea($professionals, $attention->professional_area_slug) ?: $professionals->first();
            $reason = sprintf('Derivación interna %02d hacia %s', $index + 1, Str::headline($destinationArea));

            $derivation = ApoyoDerivacion::query()
                ->where('attention_id', $attention->id)
                ->where('destination_area_slug', $destinationArea)
                ->where('reason', $reason)
                ->first();

            $payload = [
                'attention_id' => $attention->id,
                'destination_professional_id' => $destination['profile']->id ?? null,
                'destination_user_id' => $destination['user']->id ?? null,
                'destination_area_slug' => $destinationArea,
                'destination_area_name' => $destination['profile']->area_name ?? Str::headline($destinationArea),
                'urgency_level' => ['media', 'alta', 'urgente', 'baja'][$index % 4],
                'confidentiality_level' => $attention->confidentiality_level,
                'reason' => $reason,
                'description' => $this->faker->paragraph(),
                'derived_at' => Carbon::parse($attention->attended_at)->copy()->addDays(($index % 3) + 1)->format('Y-m-d H:i:s'),
            ];

            $derivation = $derivation
                ? $service->update($derivation, array_merge($payload, ['status' => $derivation->status]), $creator['user'])
                : $service->store($payload, $creator['user']);

            if ($index % 3 === 0) {
                $service->respond($derivation, [
                    'status' => 'aceptada',
                    'destination_response' => 'Se acoge derivación y se agenda intervención de seguimiento.',
                ], $destination['user'] ?? $this->actor);
            } elseif ($index % 5 === 0) {
                $service->respond($derivation, [
                    'status' => 'rechazada',
                    'destination_response' => 'Se requiere información complementaria antes de aceptar la derivación.',
                ], $destination['user'] ?? $this->actor);
            } elseif ($index % 7 === 0) {
                $service->respond($derivation, [
                    'status' => 'cerrada',
                    'destination_response' => 'Derivación resuelta y caso informado al profesional de origen.',
                ], $destination['user'] ?? $this->actor);
            }

            if ($index < 8) {
                $this->attachDocument(
                    $derivation->fresh(),
                    $attention->student_profile_id,
                    $creator['user']->id,
                    $index % 2 === 0 ? 'acta' : 'registro_externo',
                    sprintf('derivacion_%02d.txt', $index + 1),
                    'Respaldo de la derivación interna.',
                    $attention->confidentiality_level
                );
            }
        }
    }

    private function seedFollowUps(Collection $attentions, Collection $professionals): void
    {
        foreach ($attentions->take(18)->values() as $index => $attention) {
            $responsible = $this->professionalForArea($professionals, $attention->professional_area_slug) ?: $professionals->first();
            $status = match (true) {
                $index % 6 === 0 => 'cerrado',
                $index % 5 === 0 => 'realizado',
                $index % 4 === 0 => 'reprogramado',
                $index % 7 === 0 => 'cancelado',
                default => 'pendiente',
            };

            $scheduledAt = Carbon::parse($attention->attended_at)->copy()->addDays(($index % 9) - 4)->setHour(10 + ($index % 5));
            $followUp = ApoyoSeguimiento::query()->updateOrCreate(
                [
                    'attention_id' => $attention->id,
                    'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
                ],
                [
                    'student_profile_id' => $attention->student_profile_id,
                    'responsible_professional_id' => $responsible['profile']->id ?? null,
                    'responsible_user_id' => $responsible['user']->id ?? null,
                    'completed_at' => in_array($status, ['realizado', 'cerrado'], true)
                        ? $scheduledAt->copy()->addHours(2)->format('Y-m-d H:i:s')
                        : null,
                    'comment' => sprintf('Seguimiento %02d posterior a la atención.', $index + 1),
                    'status' => $status,
                    'next_action' => $status === 'pendiente' ? 'Contactar al apoderado y confirmar asistencia.' : 'Registrar cierre en ficha del estudiante.',
                    'evidence_summary' => in_array($status, ['realizado', 'cerrado'], true) ? 'Evidencia cargada en carpeta de apoyo.' : null,
                    'result' => in_array($status, ['realizado', 'cerrado'], true) ? 'Seguimiento ejecutado conforme a planificación.' : null,
                    'created_by' => $responsible['user']->id ?? $this->actor->id,
                    'updated_by' => $responsible['user']->id ?? $this->actor->id,
                ],
            );

            if ($index < 6) {
                $this->attachDocument(
                    $followUp,
                    $attention->student_profile_id,
                    $responsible['user']->id ?? $this->actor->id,
                    'pauta',
                    sprintf('seguimiento_%02d.txt', $index + 1),
                    'Pauta o evidencia de seguimiento.',
                    $attention->confidentiality_level
                );
            }
        }
    }

    private function seedPlans(Collection $students, Collection $professionals): void
    {
        $service = app(ApoyoProfesionalPlanService::class);
        $statuses = ['disenado', 'en_ejecucion', 'en_seguimiento', 'finalizado', 'suspendido'];
        $areas = ['psicologia', 'trabajo_social', 'pie', 'psicopedagogia', 'orientacion'];

        foreach ($students->take(10)->values() as $index => $student) {
            $area = $areas[$index % count($areas)];
            $responsible = $this->professionalForArea($professionals, $area) ?: $professionals->first();
            $motive = sprintf('Plan de apoyo integral %02d', $index + 1);
            $payload = [
                'student_profile_id' => $student->id,
                'responsible_professional_id' => $responsible['profile']->id ?? null,
                'responsible_user_id' => $responsible['user']->id ?? null,
                'area_slug' => $responsible['profile']->area_slug ?? $area,
                'area_name' => $responsible['profile']->area_name ?? Str::headline($area),
                'motive' => $motive,
                'general_objective' => 'Fortalecer la participación del estudiante y mejorar su respuesta ante demandas escolares.',
                'specific_objectives' => [
                    'Monitorear avances académicos y socioemocionales.',
                    'Ajustar apoyos pedagógicos según evolución del caso.',
                    'Coordinar acciones con familia y equipo interno.',
                ],
                'actions_summary' => 'Reuniones de seguimiento, adecuaciones de aula y coordinación con familia.',
                'responsibles_summary' => 'Profesional responsable, profesor jefe y familia.',
                'start_date' => now()->copy()->subDays(45 - ($index * 3))->format('Y-m-d'),
                'end_date' => now()->copy()->addDays(30 + ($index * 5))->format('Y-m-d'),
                'indicators' => 'Asistencia, cumplimiento de acuerdos, avances en aula y disminución de alertas.',
                'status' => $statuses[$index % count($statuses)],
                'evidences' => 'Actas de reunión, informes de seguimiento y observaciones de aula.',
                'observations' => 'Plan generado para validación integral del módulo.',
                'confidentiality_level' => $index % 3 === 0 ? 'confidencial' : 'reservada',
                'actions' => [
                    [
                        'action_description' => 'Reunión con apoderado para revisión de acuerdos.',
                        'responsible_label' => $responsible['staff']->full_name ?? $responsible['user']->name,
                        'due_date' => now()->copy()->addDays(7 + $index)->format('Y-m-d'),
                        'completed_at' => null,
                        'status' => 'pendiente',
                        'observations' => 'Confirmar asistencia con 24 horas de anticipación.',
                    ],
                    [
                        'action_description' => 'Observación en aula y retroalimentación con docente.',
                        'responsible_label' => 'Equipo de apoyo y profesor jefe',
                        'due_date' => now()->copy()->addDays(14 + $index)->format('Y-m-d'),
                        'completed_at' => $index % 4 === 0 ? now()->copy()->subDays(3)->format('Y-m-d') : null,
                        'status' => $index % 4 === 0 ? 'cerrada' : 'en_proceso',
                        'observations' => 'Registrar acuerdos en ficha del estudiante.',
                    ],
                ],
            ];

            $plan = ApoyoPlan::query()
                ->where('student_profile_id', $student->id)
                ->where('motive', $motive)
                ->first();

            $plan = $plan
                ? $service->update($plan, $payload, $responsible['user'])
                : $service->store($payload, $responsible['user']);

            if ($index < 5) {
                $this->attachDocument(
                    $plan,
                    $student->id,
                    $responsible['user']->id,
                    'informe',
                    sprintf('plan_%02d.txt', $index + 1),
                    'Respaldo del plan de apoyo.',
                    $payload['confidentiality_level']
                );
            }
        }
    }

    private function seedInterviews(Collection $students, Collection $professionals): void
    {
        $types = [
            'entrevista_estudiante',
            'entrevista_apoderado',
            'entrevista_profesor_jefe',
            'entrevista_docente',
            'entrevista_familiar',
            'entrevista_equipo_interno',
            'entrevista_red_externa',
        ];
        $statuses = ['abierta', 'en_seguimiento', 'cerrada', 'cancelada'];
        $areas = ['psicologia', 'trabajo_social', 'orientacion', 'pie', 'convivencia_escolar'];

        foreach ($students->take(12)->values() as $index => $student) {
            $responsible = $this->professionalForArea($professionals, $areas[$index % count($areas)]) ?: $professionals->first();
            $interviewAt = $index < 3
                ? now()->copy()->addDays($index + 1)->setHour(11)
                : now()->copy()->subDays(($index - 2) * 6)->setHour(15);
            $motive = sprintf('Entrevista profesional %02d', $index + 1);

            $interview = ApoyoEntrevista::query()->updateOrCreate(
                [
                    'student_profile_id' => $student->id,
                    'interview_at' => $interviewAt->format('Y-m-d H:i:s'),
                    'motive' => $motive,
                ],
                [
                    'professional_id' => $responsible['profile']->id ?? null,
                    'professional_user_id' => $responsible['user']->id ?? null,
                    'interview_type' => $types[$index % count($types)],
                    'participants' => [
                        $student->full_name,
                        $responsible['staff']->full_name ?? $responsible['user']->name,
                        $index % 2 === 0 ? $student->guardian_name : 'Profesor jefe del curso',
                    ],
                    'topics' => 'Revisión de antecedentes, acuerdos de apoyo y definición de seguimiento.',
                    'agreements' => 'Mantener comunicación semanal y registrar avances en ficha del estudiante.',
                    'commitments' => 'Apoderado y estudiante asistirán a próxima reunión de seguimiento.',
                    'follow_up_date' => $interviewAt->copy()->addDays(14)->format('Y-m-d'),
                    'status' => $statuses[$index % count($statuses)],
                    'confidentiality_level' => $index % 4 === 0 ? 'confidencial' : 'reservada',
                    'observations' => 'Registro de entrevista para validar seguimiento interdisciplinario.',
                    'created_by' => $responsible['user']->id,
                    'updated_by' => $responsible['user']->id,
                ],
            );

            if ($index < 6) {
                $this->attachDocument(
                    $interview,
                    $student->id,
                    $responsible['user']->id,
                    $index % 2 === 0 ? 'acta' : 'certificado',
                    sprintf('entrevista_%02d.txt', $index + 1),
                    'Documento asociado a la entrevista profesional.',
                    $interview->confidentiality_level
                );
            }
        }
    }

    private function professionalForArea(Collection $professionals, ?string $areaSlug): ?array
    {
        return $professionals->first(function (array $record) use ($areaSlug) {
            return ($record['profile']->area_slug ?? null) === $areaSlug;
        });
    }

    private function attachDocument(
        Model $subject,
        int $studentId,
        ?int $userId,
        string $category,
        string $fileName,
        string $notes,
        string $confidentialityLevel = 'general'
    ): void {
        if (!method_exists($subject, 'documents')) {
            return;
        }

        $directory = sprintf('apoyo-profesional/seed/%s/%d', class_basename($subject), $subject->getKey());
        $path = $directory . '/' . $fileName;

        Storage::disk('public')->put(
            $path,
            implode(PHP_EOL, [
                'Documento de prueba del módulo Equipo de Apoyo.',
                'Registro asociado: ' . class_basename($subject) . ' #' . $subject->getKey(),
                'Notas: ' . $notes,
            ])
        );

        $subject->documents()->updateOrCreate(
            ['file_path' => $path],
            [
                'student_profile_id' => $studentId,
                'category' => $category,
                'confidentiality_level' => $confidentialityLevel,
                'original_name' => $fileName,
                'mime_type' => $this->mimeTypeForName($fileName),
                'file_size' => Storage::disk('public')->size($path),
                'notes' => $notes,
                'uploaded_by' => $userId,
            ],
        );
    }

    private function mimeTypeForName(string $fileName): string
    {
        return match (Str::lower(pathinfo($fileName, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'doc', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'text/plain',
        };
    }
}
