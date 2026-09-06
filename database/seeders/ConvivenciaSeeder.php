<?php

namespace Database\Seeders;

use App\Models\Convivencia\ConvivenciaCatalogItem;
use App\Models\Convivencia\ConvivenciaExternalInstitution;
use App\Models\Convivencia\ConvivenciaIdpsDimension;
use App\Models\Convivencia\ConvivenciaIdpsInstrument;
use App\Models\Convivencia\ConvivenciaIdpsPeriod;
use App\Models\Convivencia\ConvivenciaIdpsResult;
use App\Models\Convivencia\ConvivenciaProtocolPart;
use App\Models\Convivencia\ConvivenciaSetting;
use App\Models\CourseSection;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\SystemModule;
use App\Models\User;
use App\Services\Convivencia\ConvivenciaAccessService;
use App\Services\Convivencia\ConvivenciaCaseService;
use App\Services\Convivencia\ConvivenciaComplaintService;
use App\Services\Convivencia\ConvivenciaDailyLogService;
use App\Services\Convivencia\ConvivenciaDerivationService;
use App\Services\Convivencia\ConvivenciaInterviewService;
use App\Services\Convivencia\ConvivenciaMeasureService;
use App\Services\Convivencia\ConvivenciaPlanService;
use App\Services\Convivencia\ConvivenciaProtocolService;
use App\Services\Convivencia\ConvivenciaSociogramService;
use Carbon\Carbon;
use Database\Seeders\Modules\StaffModuleSeeder;
use Database\Seeders\Modules\StudentModuleSeeder;
use Database\Seeders\Support\ModuleSeeder;
use Database\Seeders\Support\PreventsProductionSeeding;
use Faker\Factory as Faker;
use Faker\Generator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConvivenciaSeeder extends ModuleSeeder
{
    use PreventsProductionSeeding;

    private Generator $faker;

    private User $actor;

    private User $convivenciaUser;

    private User $orientationUser;

    private User $inspectorUser;

    private User $directionUser;

    private User $psychologyUser;

    private Carbon $now;

    public function run(): void
    {
        $this->preventProductionSeeding();
        $this->faker = Faker::create('es_CL');
        $this->faker->seed(20260629);
        $this->now = Carbon::parse('2026-06-29 09:30:00');
        Carbon::setTestNow($this->now);

        $this->call([
            RbacSeeder::class,
            ChileLocationSeeder::class,
            SchoolDepartmentSeeder::class,
            StudentModuleSeeder::class,
            StaffModuleSeeder::class,
        ]);

        $this->seedPermissionsAndModules();
        $this->ensureConvivenciaTeam();
        $this->actor = $this->convivenciaUser;
        $this->ensureMinimumStudents(48);

        DB::transaction(function () {
            $this->purgeModuleData();

            $catalogs = $this->seedCatalogs();
            $institutions = $this->seedInstitutions();
            $this->seedSettings();

            $plans = $this->seedPlans($catalogs);
            $protocols = $this->seedProtocols($catalogs);

            $enrollments = $this->activeEnrollments();
            $directCases = $this->seedCases($catalogs, $enrollments);
            $complaints = $this->seedComplaints($catalogs, $enrollments);
            $dailyLogs = $this->seedDailyLogs($catalogs, $enrollments);

            $complaintCase = app(ConvivenciaComplaintService::class)->convertToCase($complaints->first(), [
                'case_type_item_id' => $this->catalogId($catalogs, 'case_type', 'caso_convivencia'),
                'classification_item_id' => $this->catalogId($catalogs, 'classification', 'maltrato_escolar'),
                'subclassification_item_id' => $this->catalogId($catalogs, 'subclassification', 'agresion_verbal'),
                'criticality_item_id' => $this->catalogId($catalogs, 'criticality', 'alta'),
                'responsible_user_id' => $this->convivenciaUser->id,
                'responsible_staff_id' => $this->convivenciaUser->staff_id,
                'follow_up_due_at' => $this->now->copy()->addDays(4)->toDateTimeString(),
                'is_sensitive' => true,
            ], $this->convivenciaUser);

            $dailyCase = app(ConvivenciaDailyLogService::class)->convertToCase($dailyLogs->first(), [
                'case_type_item_id' => $this->catalogId($catalogs, 'case_type', 'caso_convivencia'),
                'classification_item_id' => $this->catalogId($catalogs, 'classification', 'conflicto_interpersonal'),
                'subclassification_item_id' => $this->catalogId($catalogs, 'subclassification', 'conflicto_recreo'),
                'criticality_item_id' => $this->catalogId($catalogs, 'criticality', 'media'),
                'responsible_user_id' => $this->inspectorUser->id,
                'responsible_staff_id' => $this->inspectorUser->staff_id,
                'follow_up_due_at' => $this->now->copy()->addDays(2)->toDateTimeString(),
                'is_sensitive' => false,
            ], $this->inspectorUser);

            $derivations = $this->seedDerivations($institutions, $directCases, $complaintCase, $dailyCase);
            app(ConvivenciaDailyLogService::class)->convertToDerivation($dailyLogs->get(1), [
                'scope' => 'internal',
                'status' => 'recibida',
                'priority_level' => 'media',
                'confidentiality_level' => 'reservada',
                'destination_department_id' => $this->department('orientacion')->id,
                'responsible_user_id' => $this->inspectorUser->id,
                'derived_at' => $this->now->copy()->subDays(2)->toDateTimeString(),
                'response_due_at' => $this->now->copy()->addDays(3)->toDateTimeString(),
                'motive' => 'Seguimiento por conducta reiterada en sala.',
                'narrative' => 'Se solicita intervención inicial con apoderado y profesor jefe.',
                'is_sensitive' => false,
            ], $this->inspectorUser);

            $measures = $this->seedMeasures($catalogs, $directCases, $complaintCase, $dailyCase);
            $interviews = $this->seedInterviews($catalogs, $directCases, $complaintCase, $dailyCase);

            $activation = app(ConvivenciaProtocolService::class)->activate([
                'protocol_id' => $protocols->firstWhere('code', 'RICE-P05')->id,
                'case_id' => $complaintCase->id,
                'status' => 'activo',
                'actions_taken' => 'Entrevista inicial, contención y comunicación a familia.',
                'measures_adopted' => 'Separación de involucrados y registro de evidencias.',
            ], $this->convivenciaUser);

            $closedActivation = app(ConvivenciaProtocolService::class)->activate([
                'protocol_id' => $protocols->firstWhere('code', 'RICE-P06')->id,
                'complaint_id' => $complaints->get(1)->id,
                'status' => 'activo',
                'actions_taken' => 'Recepción de capturas y análisis inicial.',
                'measures_adopted' => 'Resguardo preventivo y contacto con apoderado.',
            ], $this->directionUser);

            $closedActivation = app(ConvivenciaProtocolService::class)->updateActivation($closedActivation, [
                'status' => 'activo',
                'closing_summary' => 'Se completó revisión, entrevista y compromiso de no repetición.',
                'action_type' => 'preparacion_cierre',
                'log_notes' => 'Se registra el resumen que acompañará el cierre de demostración.',
            ], $this->directionUser);

            $this->completeDemoProtocolActivation($closedActivation, $this->directionUser);

            app(ConvivenciaCaseService::class)->close($directCases->last(), [
                'resolution' => 'Se ejecutó mediación, entrevista con familia y medida formativa cumplida.',
                'conclusion' => 'Caso sin nuevos incidentes en cuatro semanas de seguimiento.',
            ], $this->directionUser);

            $this->seedSociograms($enrollments);
            $this->seedIdps($plans, $enrollments);

            $complaints->get(2)?->update([
                'status' => 'requiere_antecedentes',
                'admissibility_result' => 'Se solicita ampliar relato y adjuntar antecedentes complementarios.',
                'updated_by' => $this->convivenciaUser->id,
            ]);

            $complaints->get(3)?->update([
                'status' => 'descartada_fundadamente',
                'admissibility_result' => 'No se verifican antecedentes suficientes para abrir caso formal.',
                'updated_by' => $this->convivenciaUser->id,
            ]);
        });

        Carbon::setTestNow();
    }

    private function seedPermissionsAndModules(): void
    {
        $roles = [
            ['slug' => 'convivencia_escolar', 'name' => 'Convivencia Escolar', 'description' => 'Gestión integral del módulo de convivencia.'],
            ['slug' => 'orientacion', 'name' => 'Orientación', 'description' => 'Acceso transversal a seguimiento y entrevistas de convivencia.'],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(['slug' => $role['slug']], array_merge($role, ['active' => true]));
        }

        $permissions = [
            ['slug' => 'ver_convivencia', 'name' => 'Ver módulo Convivencia Escolar'],
            ['slug' => ConvivenciaAccessService::VIEW_DASHBOARD_PERMISSION, 'name' => 'Ver dashboard de convivencia'],
            ['slug' => ConvivenciaAccessService::MANAGE_PLAN_PERMISSION, 'name' => 'Gestionar plan de convivencia'],
            ['slug' => ConvivenciaAccessService::CREATE_CASE_PERMISSION, 'name' => 'Crear casos de convivencia'],
            ['slug' => ConvivenciaAccessService::VIEW_CASES_PERMISSION, 'name' => 'Ver casos de convivencia'],
            ['slug' => ConvivenciaAccessService::EDIT_CASES_PERMISSION, 'name' => 'Editar casos de convivencia'],
            ['slug' => ConvivenciaAccessService::CLOSE_CASES_PERMISSION, 'name' => 'Cerrar casos de convivencia'],
            ['slug' => ConvivenciaAccessService::VIEW_SENSITIVE_CASES_PERMISSION, 'name' => 'Ver casos sensibles de convivencia'],
            ['slug' => ConvivenciaAccessService::MANAGE_COMPLAINTS_PERMISSION, 'name' => 'Gestionar denuncias de convivencia'],
            ['slug' => ConvivenciaAccessService::MANAGE_PROTOCOLS_PERMISSION, 'name' => 'Gestionar protocolos de convivencia'],
            ['slug' => ConvivenciaAccessService::ACTIVATE_PROTOCOLS_PERMISSION, 'name' => 'Activar protocolos de convivencia'],
            ['slug' => ConvivenciaAccessService::MANAGE_INTERVIEWS_PERMISSION, 'name' => 'Gestionar entrevistas de convivencia'],
            ['slug' => ConvivenciaAccessService::MANAGE_MEASURES_PERMISSION, 'name' => 'Gestionar medidas formativas'],
            ['slug' => ConvivenciaAccessService::MANAGE_INTERNAL_DERIVATIONS_PERMISSION, 'name' => 'Gestionar derivaciones internas de convivencia'],
            ['slug' => ConvivenciaAccessService::MANAGE_EXTERNAL_DERIVATIONS_PERMISSION, 'name' => 'Gestionar derivaciones externas de convivencia'],
            ['slug' => ConvivenciaAccessService::VIEW_SOCIOGRAMS_PERMISSION, 'name' => 'Ver sociogramas de convivencia'],
            ['slug' => ConvivenciaAccessService::MANAGE_SOCIOGRAMS_PERMISSION, 'name' => 'Gestionar sociogramas de convivencia'],
            ['slug' => ConvivenciaAccessService::VIEW_COURSE_REPORTS_PERMISSION, 'name' => 'Ver reportes por curso de convivencia'],
            ['slug' => ConvivenciaAccessService::MANAGE_DAILY_LOG_PERMISSION, 'name' => 'Gestionar bitácora de inspectoría en convivencia'],
            ['slug' => ConvivenciaAccessService::EXPORT_REPORTS_PERMISSION, 'name' => 'Exportar reportes de convivencia'],
            ['slug' => ConvivenciaAccessService::MANAGE_SETTINGS_PERMISSION, 'name' => 'Administrar configuraciones de convivencia'],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                ['name' => $permission['name'], 'description' => 'Permiso del módulo Convivencia Escolar.', 'active' => true],
            );
        }

        $parent = SystemModule::query()->updateOrCreate(
            ['slug' => 'convivencia'],
            ['name' => 'Convivencia Escolar', 'frontend_route' => null, 'icon' => 'bx-happy', 'sort_order' => 70, 'active' => true, 'parent_id' => null],
        );

        $children = [
            ['slug' => 'convivencia_dashboard', 'name' => 'Análisis e informes', 'route' => '/convivencia', 'sort' => 1],
            ['slug' => 'convivencia_planes', 'name' => 'Plan de Gestión', 'route' => '/convivencia/planes', 'sort' => 2],
            ['slug' => 'convivencia_casos', 'name' => 'Casos', 'route' => '/convivencia/casos', 'sort' => 3],
            ['slug' => 'convivencia_denuncias', 'name' => 'Denuncias', 'route' => '/convivencia/denuncias', 'sort' => 4],
            ['slug' => 'convivencia_derivaciones', 'name' => 'Derivaciones', 'route' => '/convivencia/derivaciones', 'sort' => 5],
            ['slug' => 'convivencia_protocolos', 'name' => 'Protocolos', 'route' => '/convivencia/protocolos', 'sort' => 6],
            ['slug' => 'convivencia_entrevistas', 'name' => 'Entrevistas', 'route' => '/convivencia/entrevistas', 'sort' => 7],
            ['slug' => 'convivencia_medidas', 'name' => 'Medidas formativas', 'route' => '/convivencia/medidas', 'sort' => 8],
            ['slug' => 'convivencia_bitacora', 'name' => 'Bitácora inspectoría', 'route' => '/convivencia/bitacora', 'sort' => 9],
            ['slug' => 'convivencia_sociogramas', 'name' => 'Sociogramas', 'route' => '/convivencia/sociogramas', 'sort' => 10],
            ['slug' => 'convivencia_idps', 'name' => 'IDPS', 'route' => '/convivencia/idps', 'sort' => 11],
            ['slug' => 'convivencia_reportes', 'name' => 'Reportes', 'route' => '/convivencia/reportes', 'sort' => 12],
        ];

        foreach ($children as $child) {
            SystemModule::query()->updateOrCreate(
                ['slug' => $child['slug']],
                ['name' => $child['name'], 'frontend_route' => $child['route'], 'icon' => null, 'sort_order' => $child['sort'], 'active' => true, 'parent_id' => $parent->id],
            );
        }

        $rolesBySlug = Role::query()->whereIn('slug', ['super_admin', 'administrador', 'direccion', 'coordinador_academico', 'psicologo', 'enfermeria', 'inspectoria', 'convivencia_escolar', 'orientacion'])->get()->keyBy('slug');
        $permissionsBySlug = Permission::query()->whereIn('slug', array_column($permissions, 'slug'))->get()->keyBy('slug');
        $modulesBySlug = SystemModule::query()->whereIn('slug', array_merge(['convivencia'], array_column($children, 'slug')))->get()->keyBy('slug');

        $allPermissionSlugs = $permissionsBySlug->keys()->all();
        $allModuleSlugs = $modulesBySlug->keys()->all();

        $rolePermissionMap = [
            'super_admin' => $allPermissionSlugs,
            'administrador' => $allPermissionSlugs,
            'convivencia_escolar' => $allPermissionSlugs,
            'orientacion' => [
                'ver_convivencia',
                ConvivenciaAccessService::VIEW_DASHBOARD_PERMISSION,
                ConvivenciaAccessService::MANAGE_PLAN_PERMISSION,
                ConvivenciaAccessService::CREATE_CASE_PERMISSION,
                ConvivenciaAccessService::VIEW_CASES_PERMISSION,
                ConvivenciaAccessService::EDIT_CASES_PERMISSION,
                ConvivenciaAccessService::MANAGE_COMPLAINTS_PERMISSION,
                ConvivenciaAccessService::MANAGE_INTERVIEWS_PERMISSION,
                ConvivenciaAccessService::MANAGE_MEASURES_PERMISSION,
                ConvivenciaAccessService::MANAGE_INTERNAL_DERIVATIONS_PERMISSION,
                ConvivenciaAccessService::MANAGE_EXTERNAL_DERIVATIONS_PERMISSION,
                ConvivenciaAccessService::VIEW_SOCIOGRAMS_PERMISSION,
                ConvivenciaAccessService::VIEW_COURSE_REPORTS_PERMISSION,
            ],
            'direccion' => [
                'ver_convivencia',
                ConvivenciaAccessService::VIEW_DASHBOARD_PERMISSION,
                ConvivenciaAccessService::VIEW_CASES_PERMISSION,
                ConvivenciaAccessService::CLOSE_CASES_PERMISSION,
                ConvivenciaAccessService::VIEW_SENSITIVE_CASES_PERMISSION,
                ConvivenciaAccessService::MANAGE_PROTOCOLS_PERMISSION,
                ConvivenciaAccessService::ACTIVATE_PROTOCOLS_PERMISSION,
                ConvivenciaAccessService::VIEW_COURSE_REPORTS_PERMISSION,
                ConvivenciaAccessService::EXPORT_REPORTS_PERMISSION,
            ],
            'inspectoria' => [
                'ver_convivencia',
                ConvivenciaAccessService::VIEW_DASHBOARD_PERMISSION,
                ConvivenciaAccessService::CREATE_CASE_PERMISSION,
                ConvivenciaAccessService::VIEW_CASES_PERMISSION,
                ConvivenciaAccessService::EDIT_CASES_PERMISSION,
                ConvivenciaAccessService::MANAGE_INTERNAL_DERIVATIONS_PERMISSION,
                ConvivenciaAccessService::MANAGE_DAILY_LOG_PERMISSION,
                ConvivenciaAccessService::MANAGE_INTERVIEWS_PERMISSION,
            ],
            'coordinador_academico' => [
                'ver_convivencia',
                ConvivenciaAccessService::VIEW_DASHBOARD_PERMISSION,
                ConvivenciaAccessService::MANAGE_PLAN_PERMISSION,
                ConvivenciaAccessService::VIEW_CASES_PERMISSION,
                ConvivenciaAccessService::VIEW_COURSE_REPORTS_PERMISSION,
                ConvivenciaAccessService::EXPORT_REPORTS_PERMISSION,
            ],
            'psicologo' => [
                'ver_convivencia',
                ConvivenciaAccessService::VIEW_DASHBOARD_PERMISSION,
                ConvivenciaAccessService::VIEW_CASES_PERMISSION,
                ConvivenciaAccessService::MANAGE_INTERVIEWS_PERMISSION,
                ConvivenciaAccessService::MANAGE_MEASURES_PERMISSION,
                ConvivenciaAccessService::MANAGE_INTERNAL_DERIVATIONS_PERMISSION,
                ConvivenciaAccessService::MANAGE_EXTERNAL_DERIVATIONS_PERMISSION,
                ConvivenciaAccessService::VIEW_SOCIOGRAMS_PERMISSION,
            ],
            'enfermeria' => [
                'ver_convivencia',
                ConvivenciaAccessService::VIEW_CASES_PERMISSION,
                ConvivenciaAccessService::MANAGE_INTERNAL_DERIVATIONS_PERMISSION,
            ],
        ];

        $roleModuleMap = [
            'super_admin' => $allModuleSlugs,
            'administrador' => $allModuleSlugs,
            'convivencia_escolar' => $allModuleSlugs,
            'orientacion' => ['convivencia', 'convivencia_dashboard', 'convivencia_planes', 'convivencia_casos', 'convivencia_derivaciones', 'convivencia_entrevistas', 'convivencia_medidas', 'convivencia_reportes'],
            'direccion' => ['convivencia', 'convivencia_dashboard', 'convivencia_casos', 'convivencia_denuncias', 'convivencia_protocolos', 'convivencia_reportes'],
            'inspectoria' => ['convivencia', 'convivencia_dashboard', 'convivencia_casos', 'convivencia_derivaciones', 'convivencia_bitacora', 'convivencia_entrevistas'],
            'coordinador_academico' => ['convivencia', 'convivencia_dashboard', 'convivencia_planes', 'convivencia_idps', 'convivencia_reportes'],
            'psicologo' => ['convivencia', 'convivencia_dashboard', 'convivencia_casos', 'convivencia_derivaciones', 'convivencia_entrevistas', 'convivencia_medidas', 'convivencia_sociogramas'],
            'enfermeria' => ['convivencia', 'convivencia_casos', 'convivencia_derivaciones'],
        ];

        foreach ($rolePermissionMap as $roleSlug => $permissionSlugs) {
            $role = $rolesBySlug->get($roleSlug);
            if (! $role) {
                continue;
            }

            $role->permissions()->syncWithoutDetaching(
                collect($permissionSlugs)->map(fn (string $slug) => $permissionsBySlug->get($slug)?->id)->filter()->all()
            );
            $role->modules()->syncWithoutDetaching(
                collect($roleModuleMap[$roleSlug] ?? [])->map(fn (string $slug) => $modulesBySlug->get($slug)?->id)->filter()->all()
            );
        }
    }

    private function ensureConvivenciaTeam(): void
    {
        $base = [
            'region' => 'Los Ríos',
            'commune' => 'Valdivia',
            'address' => 'Av. Ramón Picarte 1450, Valdivia',
            'contract_type' => 'indefinido',
            'start_date' => '2024-03-01',
            'status' => 'activo',
            'workday' => 'completa',
            'contract_hours' => 44,
        ];

        $this->convivenciaUser = $this->upsertStaffUser(
            ['full_name' => 'Viviana Contreras Jara', 'rut' => '24444444-4', 'institutional_email' => 'viviana.contreras@cnscgestion.local', 'personal_email' => 'viviana.contreras@example.com', 'phone' => '+56961100021', 'cargo_slug' => 'administrativo', 'professional_title' => 'Trabajadora Social', 'specialty' => 'Encargada de convivencia escolar'] + $base,
            ['email' => 'viviana.contreras@cnscgestion.local', 'password' => 'Convivencia123!', 'name' => 'Viviana Contreras'],
            ['convivencia_escolar'],
            ['convivencia-escolar']
        )['user'];

        $this->orientationUser = $this->upsertStaffUser(
            ['full_name' => 'Marcela Sanhueza Rivera', 'rut' => '25555555-5', 'institutional_email' => 'marcela.sanhueza@cnscgestion.local', 'personal_email' => 'marcela.sanhueza@example.com', 'phone' => '+56961100022', 'cargo_slug' => 'docente', 'professional_title' => 'Orientadora educacional', 'specialty' => 'Orientación y mediación'] + $base,
            ['email' => 'marcela.sanhueza@cnscgestion.local', 'password' => 'Orientacion123!', 'name' => 'Marcela Sanhueza'],
            ['orientacion'],
            ['orientacion']
        )['user'];

        $this->inspectorUser = $this->upsertStaffUser(
            ['full_name' => 'Andrea Riffo Cárcamo', 'rut' => '26666666-6', 'institutional_email' => 'andrea.riffo@cnscgestion.local', 'personal_email' => 'andrea.riffo@example.com', 'phone' => '+56961100023', 'cargo_slug' => 'inspectoria', 'professional_title' => 'Inspectora general', 'specialty' => 'Inspectoría y seguimiento diario'] + $base,
            ['email' => 'andrea.riffo@cnscgestion.local', 'password' => 'Inspectoria123!', 'name' => 'Andrea Riffo'],
            ['inspectoria'],
            ['inspectoria-general']
        )['user'];

        $this->directionUser = User::query()->where('email', 'carolina.munoz@cnscgestion.local')->firstOrFail();
        $this->psychologyUser = User::query()->where('email', 'camila.soto@cnscgestion.local')->firstOrFail();
    }

    private function purgeModuleData(): void
    {
        DB::table('convivencia_status_logs')->delete();
        DB::table('convivencia_attachments')->delete();
        DB::table('convivencia_idps_results')->delete();
        DB::table('convivencia_idps_instruments')->delete();
        DB::table('convivencia_idps_periods')->delete();
        DB::table('convivencia_idps_dimensions')->delete();
        DB::table('convivencia_sociogram_answers')->delete();
        DB::table('convivencia_sociogram_questions')->delete();
        DB::table('convivencia_sociograms')->delete();
        DB::table('convivencia_daily_logs')->delete();
        DB::table('convivencia_interview_participants')->delete();
        DB::table('convivencia_interviews')->delete();
        DB::table('convivencia_measures')->delete();
        DB::table('convivencia_derivations')->delete();
        if (Schema::hasTable('convivencia_protocol_activation_parts')) {
            DB::table('convivencia_protocol_activation_parts')->delete();
        }
        DB::table('convivencia_protocol_activation_logs')->delete();
        DB::table('convivencia_protocol_activations')->delete();
        if (Schema::hasTable('convivencia_protocol_activation_steps')) {
            DB::table('convivencia_protocol_activation_steps')->delete();
        }
        DB::table('convivencia_complaints')->delete();
        if (Schema::hasTable('convivencia_protocol_part_links')) {
            DB::table('convivencia_protocol_part_links')->delete();
        }
        DB::table('convivencia_protocol_steps')->delete();
        DB::table('convivencia_protocols')->delete();
        if (Schema::hasTable('convivencia_protocol_parts')) {
            DB::table('convivencia_protocol_parts')->delete();
        }
        DB::table('convivencia_case_followups')->delete();
        DB::table('convivencia_case_people')->delete();
        DB::table('convivencia_cases')->delete();
        DB::table('convivencia_plan_actions')->delete();
        DB::table('convivencia_plans')->delete();
        DB::table('convivencia_external_institutions')->delete();
        DB::table('convivencia_settings')->delete();
        DB::table('convivencia_catalog_items')->delete();
    }

    private function seedCatalogs(): array
    {
        $groups = [
            'case_type' => [
                ['code' => 'caso_convivencia', 'name' => 'Caso de convivencia'],
                ['code' => 'situacion_reglamento', 'name' => 'Situación reglamentaria'],
                ['code' => 'vulneracion', 'name' => 'Vulneración de derechos'],
                ['code' => 'observacion_positiva', 'name' => 'Observación positiva'],
            ],
            'classification' => [
                ['code' => 'maltrato_escolar', 'name' => 'Maltrato escolar', 'color' => '#dc3545'],
                ['code' => 'conflicto_interpersonal', 'name' => 'Conflicto interpersonal', 'color' => '#fd7e14'],
                ['code' => 'discriminacion', 'name' => 'Discriminación', 'color' => '#6f42c1'],
                ['code' => 'vulneracion_derechos', 'name' => 'Vulneración de derechos', 'color' => '#d63384'],
                ['code' => 'observacion_positiva', 'name' => 'Observación positiva', 'color' => '#198754'],
            ],
            'subclassification' => [
                ['code' => 'agresion_verbal', 'name' => 'Agresión verbal'],
                ['code' => 'agresion_fisica', 'name' => 'Agresión física'],
                ['code' => 'ciberacoso', 'name' => 'Ciberacoso'],
                ['code' => 'conflicto_recreo', 'name' => 'Conflicto en recreo'],
                ['code' => 'falta_reglamento', 'name' => 'Falta al reglamento interno'],
                ['code' => 'contencion_emocional', 'name' => 'Contención emocional'],
            ],
            'criticality' => [
                ['code' => 'baja', 'name' => 'Baja', 'color' => '#6c757d'],
                ['code' => 'media', 'name' => 'Media', 'color' => '#ffc107'],
                ['code' => 'alta', 'name' => 'Alta', 'color' => '#fd7e14'],
                ['code' => 'critica', 'name' => 'Crítica', 'color' => '#dc3545'],
            ],
            'plan_dimension' => [
                ['code' => 'promocion_buen_trato', 'name' => 'Promoción del buen trato'],
                ['code' => 'prevencion_conflictos', 'name' => 'Prevención de conflictos'],
                ['code' => 'participacion', 'name' => 'Participación y ciudadanía'],
                ['code' => 'seguimiento_casos', 'name' => 'Seguimiento y reparación'],
            ],
            'plan_activity_type' => [
                ['code' => 'charla', 'name' => 'Charla', 'color' => '#4f63d9', 'metadata' => ['icon' => 'bx-conversation']],
                ['code' => 'intervencion', 'name' => 'Intervención', 'color' => '#d06c4f', 'metadata' => ['icon' => 'bx-support']],
                ['code' => 'taller', 'name' => 'Taller', 'color' => '#258a70', 'metadata' => ['icon' => 'bx-shape-circle']],
                ['code' => 'reunion', 'name' => 'Reunión', 'color' => '#3576d3', 'metadata' => ['icon' => 'bx-group']],
                ['code' => 'capacitacion', 'name' => 'Capacitación', 'color' => '#8a55c5', 'metadata' => ['icon' => 'bx-chalkboard']],
                ['code' => 'jornada', 'name' => 'Jornada', 'color' => '#b7791f', 'metadata' => ['icon' => 'bx-calendar-star']],
                ['code' => 'campana', 'name' => 'Campaña', 'color' => '#d6537a', 'metadata' => ['icon' => 'bx-megaphone']],
                ['code' => 'mediacion', 'name' => 'Mediación', 'color' => '#298b9a', 'metadata' => ['icon' => 'bx-link-alt']],
                ['code' => 'acompanamiento', 'name' => 'Acompañamiento', 'color' => '#527a44', 'metadata' => ['icon' => 'bx-user-voice']],
                ['code' => 'seguimiento', 'name' => 'Seguimiento', 'color' => '#64748b', 'metadata' => ['icon' => 'bx-line-chart']],
                ['code' => 'encuesta', 'name' => 'Encuesta', 'color' => '#7164c4', 'metadata' => ['icon' => 'bx-list-check']],
                ['code' => 'evaluacion', 'name' => 'Evaluación', 'color' => '#a45d32', 'metadata' => ['icon' => 'bx-bar-chart-alt-2']],
                ['code' => 'difusion', 'name' => 'Difusión', 'color' => '#3b7ea1', 'metadata' => ['icon' => 'bx-broadcast']],
                ['code' => 'otro', 'name' => 'Otra actividad', 'color' => '#6b7280', 'metadata' => ['icon' => 'bx-dots-horizontal-rounded']],
            ],
            'protocol_type' => [
                ['code' => 'maltrato_escolar', 'name' => 'Maltrato escolar'],
                ['code' => 'ciberacoso', 'name' => 'Ciberacoso'],
                ['code' => 'discriminacion', 'name' => 'Discriminación'],
                ['code' => 'vulneracion_derechos', 'name' => 'Vulneración de derechos'],
                ['code' => 'autolesion', 'name' => 'Riesgo de autolesión'],
            ],
            'measure_type' => [
                ['code' => 'reflexion_guiada', 'name' => 'Reflexión guiada'],
                ['code' => 'accion_reparatoria', 'name' => 'Acción reparatoria'],
                ['code' => 'mediacion', 'name' => 'Mediación'],
                ['code' => 'actividad_pedagogica', 'name' => 'Actividad pedagógica'],
            ],
            'interview_type' => [
                ['code' => 'estudiante', 'name' => 'Entrevista con estudiante'],
                ['code' => 'apoderado', 'name' => 'Entrevista con apoderado'],
                ['code' => 'funcionario', 'name' => 'Entrevista con funcionario'],
                ['code' => 'grupo_estudiantes', 'name' => 'Entrevista grupal'],
            ],
            'daily_log_type' => [
                ['code' => 'atraso', 'name' => 'Atraso'],
                ['code' => 'inasistencia_relevante', 'name' => 'Inasistencia relevante'],
                ['code' => 'conflicto_estudiantes', 'name' => 'Conflicto entre estudiantes'],
                ['code' => 'incidente_recreo', 'name' => 'Incidente en recreo'],
                ['code' => 'uso_celular', 'name' => 'Uso indebido de celular'],
                ['code' => 'observacion_positiva', 'name' => 'Observación positiva'],
            ],
            'situation_type' => [
                ['code' => 'bullying', 'name' => 'Bullying o acoso escolar'],
                ['code' => 'ciberacoso', 'name' => 'Ciberacoso'],
                ['code' => 'agresion_verbal', 'name' => 'Agresión verbal'],
                ['code' => 'vulneracion_derechos', 'name' => 'Vulneración de derechos'],
            ],
        ];

        $catalogs = [];

        foreach ($groups as $group => $items) {
            $catalogs[$group] = collect($items)->map(function (array $item, int $index) use ($group) {
                return ConvivenciaCatalogItem::query()->create([
                    'group' => $group,
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'description' => $item['description'] ?? null,
                    'color' => $item['color'] ?? null,
                    'metadata' => $item['metadata'] ?? null,
                    'sort_order' => $index + 1,
                    'active' => true,
                    'created_by' => $this->actor->id,
                    'updated_by' => $this->actor->id,
                ]);
            })->keyBy('code');
        }

        return $catalogs;
    }

    private function seedInstitutions(): Collection
    {
        return collect([
            ['category' => 'Protección', 'name' => 'Oficina Local de la Niñez Valdivia'],
            ['category' => 'Justicia', 'name' => 'Tribunal de Familia de Valdivia'],
            ['category' => 'Salud', 'name' => 'CESFAM Las Ánimas'],
            ['category' => 'Salud mental', 'name' => 'COSAM Valdivia'],
            ['category' => 'Seguridad', 'name' => 'Carabineros 1ª Comisaría Valdivia'],
            ['category' => 'Investigación', 'name' => 'PDI Valdivia'],
        ])->map(function (array $institution) {
            return ConvivenciaExternalInstitution::query()->create([
                'category' => $institution['category'],
                'name' => $institution['name'],
                'contact_name' => 'Mesa de atención',
                'contact_email' => $this->faker->safeEmail(),
                'contact_phone' => '+569'.$this->faker->numerify('7#######'),
                'address' => 'Valdivia, Chile',
                'notes' => 'Institución disponible para derivaciones del módulo.',
                'active' => true,
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]);
        });
    }

    private function seedSettings(): void
    {
        foreach ([
            ['key' => 'public_complaints_enabled', 'label' => 'Ingreso público de denuncias', 'value' => ['enabled' => true]],
            ['key' => 'public_tracking_enabled', 'label' => 'Seguimiento por folio', 'value' => ['enabled' => true]],
        ] as $setting) {
            ConvivenciaSetting::query()->create($setting + ['description' => 'Configuración base del módulo.', 'active' => true]);
        }
    }

    private function seedPlans(array $catalogs): Collection
    {
        $service = app(ConvivenciaPlanService::class);
        $activeYear = $this->activeAcademicYear();

        return collect([
            $service->store([
                'academic_year_id' => $activeYear->id,
                'responsible_user_id' => $this->convivenciaUser->id,
                'responsible_staff_id' => $this->convivenciaUser->staff_id,
                'name' => 'Plan de Gestión de Convivencia 2026',
                'general_objective' => 'Fortalecer el buen trato, la participación y la resolución formativa de conflictos durante el año escolar.',
                'specific_objectives' => ['Promover acciones preventivas por ciclo.', 'Mejorar seguimiento de casos con foco restaurativo.', 'Vincular evidencia IDPS al plan anual.'],
                'resources_required' => 'Horas de coordinación, material formativo y espacios para talleres.',
                'indicators_summary' => 'Cumplimiento de acciones, derivaciones respondidas y reducción de casos reiterados.',
                'verification_means_summary' => 'Actas, bitácoras, reportes mensuales y registros de entrevistas.',
                'status' => 'en_ejecucion',
                'advance_percentage' => 54,
                'starts_on' => $activeYear->starts_at?->format('Y-m-d') ?? '2026-03-01',
                'ends_on' => $activeYear->ends_at?->format('Y-m-d') ?? '2026-12-31',
                'observations' => 'Plan anual con foco preventivo y reparación formativa.',
                'final_evaluation' => null,
                'actions' => [
                    ['dimension_item_id' => $this->catalogId($catalogs, 'plan_dimension', 'promocion_buen_trato'), 'responsible_user_id' => $this->convivenciaUser->id, 'action_type' => 'promocional', 'title' => 'Campaña de buen trato por ciclos', 'description' => 'Talleres y cápsulas por curso.', 'starts_on' => '2026-04-01', 'ends_on' => '2026-06-30', 'status' => 'en_ejecucion', 'advance_percentage' => 65],
                    ['dimension_item_id' => $this->catalogId($catalogs, 'plan_dimension', 'prevencion_conflictos'), 'responsible_user_id' => $this->orientationUser->id, 'action_type' => 'preventiva', 'title' => 'Mediación preventiva en cursos focalizados', 'description' => 'Trabajo con cursos de mayor conflictividad.', 'starts_on' => '2026-05-01', 'ends_on' => '2026-10-31', 'status' => 'vigente', 'advance_percentage' => 45],
                    ['dimension_item_id' => $this->catalogId($catalogs, 'plan_dimension', 'seguimiento_casos'), 'responsible_user_id' => $this->convivenciaUser->id, 'action_type' => 'reactiva', 'title' => 'Mesa mensual de seguimiento de casos críticos', 'description' => 'Revisión colegiada con dirección e inspectoría.', 'starts_on' => '2026-03-15', 'ends_on' => '2026-12-15', 'status' => 'en_ejecucion', 'advance_percentage' => 58],
                ],
            ], $this->convivenciaUser),
        ]);
    }

    private function seedProtocols(array $catalogs): Collection
    {
        $rice = config('convivencia_rice_2026');

        if (! is_array($rice) || empty($rice['parts']) || empty($rice['protocols'])) {
            throw new \RuntimeException('La configuracion convivencia_rice_2026 no contiene partes y protocolos.');
        }

        $service = app(ConvivenciaProtocolService::class);
        $parts = $this->seedProtocolParts($rice['parts']);

        return collect($rice['protocols'])
            ->values()
            ->map(fn (array $definition) => $service->store(
                $this->riceProtocolPayload($definition, $parts, $rice, $catalogs),
                $this->convivenciaUser,
            ));
    }

    /**
     * @param  array<string, array<string, mixed>>  $definitions
     * @return Collection<string, ConvivenciaProtocolPart>
     */
    private function seedProtocolParts(array $definitions): Collection
    {
        return collect($definitions)->mapWithKeys(function (array $definition, string $key) {
            $code = $definition['code'] ?? $key;
            $part = ConvivenciaProtocolPart::query()->create([
                'category' => $definition['category'],
                'code' => $code,
                'title' => $definition['title'],
                'description' => $definition['description'] ?? null,
                'instructions' => $definition['instructions'] ?? null,
                'responsible_label' => $definition['responsible_label'] ?? null,
                'population_scope' => $definition['population_scope'] ?? null,
                'legal_reference' => $definition['legal_reference'] ?? null,
                'deadline_value' => $definition['deadline_value'] ?? null,
                'deadline_unit' => $definition['deadline_unit'] ?? null,
                'deadline_anchor' => $definition['deadline_anchor'] ?? null,
                'requires_evidence' => (bool) ($definition['requires_evidence'] ?? false),
                'active' => (bool) ($definition['active'] ?? true),
                'is_sensitive' => (bool) ($definition['is_sensitive'] ?? true),
                'metadata' => array_merge([
                    'source_document' => 'RICE 2026 (con ajuste)',
                    'development_fixture' => true,
                ], $definition['metadata'] ?? []),
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]);

            return [$code => $part];
        });
    }

    /**
     * @param  Collection<string, ConvivenciaProtocolPart>  $parts
     * @param  array<string, mixed>  $rice
     */
    private function riceProtocolPayload(array $definition, Collection $parts, array $rice, array $catalogs): array
    {
        $stepPartCodes = collect($definition['steps'] ?? [])
            ->flatMap(fn (array $step) => $step['parts'] ?? [])
            ->map(fn ($link) => $this->ricePartCode($link))
            ->filter()
            ->unique()
            ->values();

        // protocol.parts se conserva completo en metadata como resumen. Solo las
        // partes que no estan asignadas a un paso generan un enlace global.
        $globalPartDefinitions = collect($definition['parts'] ?? [])
            ->reject(fn ($link) => $stepPartCodes->contains($this->ricePartCode($link)))
            ->values()
            ->all();

        $protocolTypeCode = $definition['protocol_type_code'] ?? null;
        $criticalityCode = $definition['criticality_code'] ?? null;
        $protocolTypeId = $protocolTypeCode
            ? $catalogs['protocol_type']->get($protocolTypeCode)?->id
            : null;
        $criticalityId = $criticalityCode
            ? $catalogs['criticality']->get($criticalityCode)?->id
            : null;

        $steps = collect($definition['steps'] ?? [])->values()->map(function (array $step, int $index) use ($parts) {
            $deadline = $step['deadline'] ?? [];
            $extension = $step['extension'] ?? [];
            $responsibleRoles = array_values($step['responsible_roles'] ?? []);
            $deadlineUnit = $deadline['unit'] ?? null;
            $deadlineValue = $deadline['value'] ?? null;
            $legacyDueDays = in_array($deadlineUnit, ['calendar_days', 'business_days', 'school_days'], true)
                ? $deadlineValue
                : null;

            return [
                'step_order' => $index + 1,
                'code' => $step['code'],
                'stage_name' => $step['stage_name'],
                'description' => $step['description'] ?? null,
                'step_type' => $step['step_type'] ?? 'gestion',
                'responsible_label' => $responsibleRoles
                    ? implode(', ', array_map(fn (string $role) => str_replace('_', ' ', $role), $responsibleRoles))
                    : null,
                'due_days' => $legacyDueDays,
                'deadline_value' => $deadlineValue,
                'deadline_unit' => $deadlineUnit,
                'deadline_anchor' => $deadline['anchor'] ?? 'step_started',
                'can_extend' => (bool) ($extension['allowed'] ?? false),
                'extension_value' => $extension['value'] ?? null,
                'extension_unit' => $extension['unit'] ?? null,
                'completion_rule' => $step['completion_rule'] ?? null,
                'active' => (bool) ($step['active'] ?? true),
                'metadata' => array_merge($step['metadata'] ?? [], [
                    'responsible_roles' => $responsibleRoles,
                    'extension_requires_approval' => $extension['requires_approval'] ?? false,
                    'extension_requires_reason' => $extension['requires_reason'] ?? false,
                    'structured_documents' => array_values($step['documents'] ?? []),
                    'structured_actions' => array_values($step['actions'] ?? []),
                    'structured_safeguards' => array_values($step['safeguards'] ?? []),
                ]),
                'required_documents' => $this->formatRiceList($step['documents'] ?? []),
                'minimal_actions' => $this->formatRiceList($step['actions'] ?? []),
                'safeguard_measures' => $this->formatRiceList($step['safeguards'] ?? []),
                'part_links' => $this->resolveRicePartLinks($step['parts'] ?? [], $parts),
            ];
        })->all();

        $source = $rice['source'] ?? [];

        return [
            'code' => $definition['code'],
            'version_label' => $definition['version_label'] ?? 'RICE 2026 con ajuste',
            'regulatory_source' => $definition['regulatory_source'] ?? ($source['name'] ?? null),
            'education_scope' => $definition['education_scope'] ?? null,
            'legal_reference' => $definition['legal_reference'] ?? null,
            'source_reference' => $definition['source_reference'] ?? null,
            'effective_from' => $definition['effective_from'] ?? null,
            'effective_to' => $definition['effective_to'] ?? null,
            'published_at' => $definition['published_at'] ?? null,
            'metadata' => [
                'configuration_schema_version' => $rice['schema_version'] ?? 1,
                'source' => $source,
                'source_revision' => $definition['revision'] ?? 1,
                'review_required' => (bool) ($definition['review_required'] ?? false),
                'warnings' => array_values($definition['warnings'] ?? []),
                'global_warnings' => array_values($rice['global_warnings'] ?? []),
                'scope' => $definition['scope'] ?? [],
                'references' => array_values($definition['references'] ?? []),
                'transitions' => array_values($definition['transitions'] ?? []),
                'structured_documents' => array_values($definition['documents'] ?? []),
                'structured_actions' => array_values($definition['actions'] ?? []),
                'structured_safeguards' => array_values($definition['safeguards'] ?? []),
                'protocol_parts_summary' => array_values($definition['parts'] ?? []),
                'development_fixture' => true,
            ],
            'protocol_type_item_id' => $protocolTypeId,
            'criticality_item_id' => $criticalityId,
            'name' => $definition['name'],
            'type_label' => $definition['type_label'] ?? null,
            'criticality_label' => $definition['criticality_label'] ?? null,
            'description' => $definition['description'] ?? null,
            'required_documents' => $this->formatRiceList($definition['documents'] ?? []),
            'safeguard_measures' => $this->formatRiceList($definition['safeguards'] ?? []),
            'minimal_actions' => $this->formatRiceList($definition['actions'] ?? []),
            'default_due_days' => $definition['default_due_days'] ?? null,
            'status' => $definition['status'] ?? 'borrador',
            'is_sensitive' => (bool) ($definition['is_sensitive'] ?? true),
            'steps' => $steps,
            'part_links' => $this->resolveRicePartLinks($globalPartDefinitions, $parts),
        ];
    }

    /**
     * @param  array<int, string|array<string, mixed>>  $definitions
     * @param  Collection<string, ConvivenciaProtocolPart>  $parts
     * @return array<int, array<string, mixed>>
     */
    private function resolveRicePartLinks(array $definitions, Collection $parts): array
    {
        return collect($definitions)->values()->map(function ($definition, int $index) use ($parts) {
            $link = is_string($definition) ? ['code' => $definition] : $definition;
            $code = $this->ricePartCode($link);
            $part = $code ? $parts->get($code) : null;

            if (! $part) {
                throw new \RuntimeException("La parte RICE {$code} no existe en la biblioteca configurada.");
            }

            return [
                'protocol_part_id' => $part->id,
                'is_required' => (bool) ($link['required'] ?? $link['is_required'] ?? true),
                'sort_order' => $index + 1,
                'condition' => $link['condition'] ?? null,
                'configuration' => $link['configuration'] ?? null,
            ];
        })->all();
    }

    private function ricePartCode($definition): ?string
    {
        return is_string($definition) ? $definition : ($definition['code'] ?? null);
    }

    /** @param array<int, string> $items */
    private function formatRiceList(array $items): ?string
    {
        $items = array_values(array_filter($items, fn ($item) => is_string($item) && trim($item) !== ''));

        return $items ? '- '.implode("\n- ", $items) : null;
    }

    private function completeDemoProtocolActivation($activation, User $user)
    {
        $service = app(ConvivenciaProtocolService::class);
        $steps = $activation->runtimeSteps()->orderBy('step_order')->get();

        foreach ($steps as $step) {
            foreach ($step->parts()->get() as $part) {
                $isRequired = (bool) $part->is_required;
                $condition = (array) data_get($part->snapshot, 'condition', []);
                $configuration = (array) data_get($part->snapshot, 'configuration', []);
                $isBlocked = (bool) ($configuration['blocked_for_preschool_child'] ?? false);
                $status = $isBlocked ? 'not_applicable' : ($isRequired ? 'completed' : 'not_applicable');
                $activation = $service->updateRuntimePart($part, [
                    'status' => $status,
                    'notes' => $status === 'completed'
                        ? 'Parte obligatoria completada para el escenario de demostración.'
                        : 'La condición de esta parte opcional no se presenta en el escenario de demostración.',
                    'evidence_summary' => $status === 'completed' ? 'Evidencia de prueba registrada por el seeder.' : null,
                    'outcome' => $status === 'completed' ? 'Cumplida' : 'No aplica',
                    'data' => $condition ? ['condition_confirmed' => $status === 'completed'] : null,
                ], $user);
            }

            $completionRule = (array) data_get($step->snapshot, 'completion_rule', []);
            $completionCriteria = $this->demoCompletionCriteria($completionRule);
            $activation = $service->completeRuntimeStep($step, [
                'notes' => "Etapa {$step->step_order} completada para demostrar el recorrido íntegro del protocolo.",
                'outcome' => 'Etapa completada en datos de desarrollo.',
                'evidence_summary' => 'Actas y registros ficticios del escenario de demostración.',
                'completion_criteria' => $completionCriteria,
            ], $user);
        }

        return $activation;
    }

    /** @param array<string, mixed> $rule */
    private function demoCompletionCriteria(array $rule): array
    {
        $criteria = [];
        foreach (['all', 'any'] as $group) {
            $definitions = $rule[$group] ?? [];
            if ($definitions === null || $definitions === '') {
                continue;
            }
            if (! is_array($definitions)) {
                $definitions = [$definitions];
            } elseif ($definitions !== [] && ! array_is_list($definitions)) {
                $definitions = [$definitions];
            }

            foreach (array_values($definitions) as $index => $definition) {
                $key = null;
                if (is_string($definition) || is_int($definition) || is_float($definition)) {
                    $key = trim((string) $definition);
                } elseif (is_array($definition)) {
                    foreach (['key', 'code', 'field', 'name'] as $candidate) {
                        if (isset($definition[$candidate]) && trim((string) $definition[$candidate]) !== '') {
                            $key = trim((string) $definition[$candidate]);
                            break;
                        }
                    }
                }
                $criteria[$key ?: "{$group}_".($index + 1)] = true;
            }
        }

        return $criteria;
    }

    private function seedCases(array $catalogs, EloquentCollection $enrollments): Collection
    {
        $service = app(ConvivenciaCaseService::class);
        $definitions = [
            ['classification' => 'maltrato_escolar', 'subclassification' => 'agresion_fisica', 'criticality' => 'alta', 'status' => 'en_intervencion', 'origin' => 'observacion', 'responsible' => $this->convivenciaUser],
            ['classification' => 'conflicto_interpersonal', 'subclassification' => 'conflicto_recreo', 'criticality' => 'media', 'status' => 'en_analisis', 'origin' => 'entrevista', 'responsible' => $this->orientationUser],
            ['classification' => 'vulneracion_derechos', 'subclassification' => 'contencion_emocional', 'criticality' => 'critica', 'status' => 'en_seguimiento', 'origin' => 'derivacion', 'responsible' => $this->psychologyUser],
            ['classification' => 'observacion_positiva', 'subclassification' => 'falta_reglamento', 'criticality' => 'baja', 'status' => 'abierto', 'origin' => 'observacion', 'responsible' => $this->inspectorUser],
        ];

        return collect($definitions)->map(function (array $definition, int $index) use ($service, $catalogs, $enrollments) {
            $enrollment = $enrollments->get($index + 2);

            return $service->store([
                'academic_year_id' => $enrollment->academic_year_id,
                'course_section_id' => $enrollment->course_section_id,
                'student_profile_id' => $enrollment->student_profile_id,
                'case_type_item_id' => $this->catalogId($catalogs, 'case_type', 'caso_convivencia'),
                'classification_item_id' => $this->catalogId($catalogs, 'classification', $definition['classification']),
                'subclassification_item_id' => $this->catalogId($catalogs, 'subclassification', $definition['subclassification']),
                'criticality_item_id' => $this->catalogId($catalogs, 'criticality', $definition['criticality']),
                'responsible_user_id' => $definition['responsible']->id,
                'responsible_staff_id' => $definition['responsible']->staff_id,
                'opened_at' => $this->now->copy()->subDays(18 - ($index * 3))->toDateTimeString(),
                'happened_at' => $this->now->copy()->subDays(19 - ($index * 3))->toDateTimeString(),
                'origin' => $definition['origin'],
                'status' => $definition['status'],
                'place' => ['Patio central', 'Sala de clases', 'Biblioteca', 'Acceso principal'][$index],
                'initial_report' => 'Registro inicial del caso con relato suficiente para pruebas del módulo y trazabilidad completa.',
                'background' => 'Antecedentes previos considerados para intervención formativa y seguimiento.',
                'immediate_measures' => 'Contención inicial, entrevista y aviso preventivo a apoderado.',
                'safeguarding_measures' => 'Separación temporal de involucrados y monitoreo por inspectoría.',
                'follow_up_due_at' => $this->now->copy()->addDays($index + 1)->toDateTimeString(),
                'is_sensitive' => $definition['criticality'] === 'critica',
                'people' => [
                    ['student_profile_id' => $enrollment->student_profile_id, 'course_section_id' => $enrollment->course_section_id, 'person_type' => 'estudiante', 'role_type' => 'afectado', 'full_name' => $enrollment->studentProfile->registered_name_resolved, 'identifier' => $enrollment->studentProfile->rut, 'is_sensitive' => $definition['criticality'] === 'critica'],
                ],
            ], $definition['responsible']);
        });
    }

    private function seedComplaints(array $catalogs, EloquentCollection $enrollments): Collection
    {
        $service = app(ConvivenciaComplaintService::class);

        return collect(range(0, 3))->map(function (int $index) use ($service, $catalogs, $enrollments) {
            $enrollment = $enrollments->get($index + 8);
            $types = ['bullying', 'ciberacoso', 'agresion_verbal', 'vulneracion_derechos'];
            $complainantTypes = ['apoderado', 'funcionario', 'anonimo', 'externo'];

            return $service->store([
                'academic_year_id' => $enrollment->academic_year_id,
                'course_section_id' => $enrollment->course_section_id,
                'affected_student_id' => $enrollment->student_profile_id,
                'situation_type_item_id' => $this->catalogId($catalogs, 'situation_type', $types[$index]),
                'responsible_user_id' => $this->convivenciaUser->id,
                'complainant_name' => $complainantTypes[$index] === 'anonimo' ? null : $this->faker->name(),
                'complainant_type' => $complainantTypes[$index],
                'contact_email' => $complainantTypes[$index] === 'anonimo' ? null : $this->faker->safeEmail(),
                'contact_phone' => $complainantTypes[$index] === 'anonimo' ? null : '+569'.$this->faker->numerify('7#######'),
                'place' => ['Patio', 'WhatsApp', 'Sala 2B', 'Entorno externo'][$index],
                'received_at' => $this->now->copy()->subDays(12 - $index)->toDateTimeString(),
                'happened_at' => $this->now->copy()->subDays(13 - $index)->toDateTimeString(),
                'report_text' => 'Denuncia de prueba ingresada al módulo para navegación, filtros, trazabilidad y conversión en caso cuando corresponde.',
                'involved_snapshot' => [
                    ['person_type' => 'estudiante', 'role_type' => 'denunciado', 'full_name' => $this->faker->name(), 'contact_reference' => 'Mismo curso'],
                ],
                'truth_declaration_accepted' => $complainantTypes[$index] !== 'anonimo',
                'is_sensitive' => true,
                'status' => $index === 1 ? 'en_revision' : 'recibida',
            ], $this->convivenciaUser);
        });
    }

    private function seedDailyLogs(array $catalogs, EloquentCollection $enrollments): Collection
    {
        $service = app(ConvivenciaDailyLogService::class);
        $types = ['conflicto_estudiantes', 'uso_celular', 'atraso', 'observacion_positiva', 'incidente_recreo'];

        return collect(range(0, 4))->map(function (int $index) use ($service, $catalogs, $enrollments, $types) {
            $enrollment = $enrollments->get($index + 12);

            return $service->store([
                'academic_year_id' => $enrollment->academic_year_id,
                'course_section_id' => $enrollment->course_section_id,
                'student_profile_id' => $enrollment->student_profile_id,
                'daily_log_type_item_id' => $this->catalogId($catalogs, 'daily_log_type', $types[$index]),
                'inspector_user_id' => $this->inspectorUser->id,
                'inspector_staff_id' => $this->inspectorUser->staff_id,
                'happened_at' => $this->now->copy()->subDays(8 - $index)->subHours($index + 1)->toDateTimeString(),
                'place' => ['Patio', 'Sala', 'Acceso', 'Biblioteca', 'Comedor'][$index],
                'description' => 'Registro de hecho diario generado para poblamiento de bitácora e indicadores del dashboard.',
                'immediate_action' => 'Se conversa con estudiante, se informa a profesor jefe y se deja constancia.',
                'guardian_informed' => $index % 2 === 0,
                'guardian_contact_note' => $index % 2 === 0 ? 'Apoderado toma conocimiento por llamada.' : null,
                'status' => in_array($index, [2, 4], true) ? 'revisado' : 'registrado',
                'is_sensitive' => $index === 0,
                'involved_snapshot' => [
                    ['full_name' => $this->faker->name(), 'person_type' => 'estudiante', 'role_type' => 'testigo'],
                ],
            ], $this->inspectorUser);
        });
    }

    private function seedDerivations(Collection $institutions, Collection $directCases, $complaintCase, $dailyCase): Collection
    {
        $service = app(ConvivenciaDerivationService::class);

        return collect([
            [
                'case' => $directCases->first(),
                'scope' => 'internal',
                'destination_department_id' => $this->department('orientacion')->id,
                'destination_label' => 'Orientación',
                'status' => 'en_revision',
                'priority_level' => 'alta',
                'responsible_user_id' => $this->convivenciaUser->id,
                'actor' => $this->convivenciaUser,
            ],
            [
                'case' => $complaintCase,
                'scope' => 'external',
                'external_institution_id' => $institutions->firstWhere('name', 'Oficina Local de la Niñez Valdivia')->id,
                'destination_label' => 'Oficina Local de la Niñez Valdivia',
                'status' => 'ingresada',
                'priority_level' => 'urgente',
                'responsible_user_id' => $this->directionUser->id,
                'actor' => $this->directionUser,
            ],
            [
                'case' => $dailyCase,
                'scope' => 'internal',
                'destination_department_id' => $this->department('psicologia')->id,
                'destination_label' => 'Psicología',
                'status' => 'respondida',
                'priority_level' => 'media',
                'responsible_user_id' => $this->orientationUser->id,
                'actor' => $this->orientationUser,
            ],
        ])->map(function (array $definition, int $index) use ($service) {
            $case = $definition['case'];

            return $service->store([
                'case_id' => $case->id,
                'academic_year_id' => $case->academic_year_id,
                'course_section_id' => $case->course_section_id,
                'student_profile_id' => $case->student_profile_id,
                'scope' => $definition['scope'],
                'status' => $definition['status'],
                'priority_level' => $definition['priority_level'],
                'confidentiality_level' => $definition['scope'] === 'external' ? 'confidencial' : 'reservada',
                'destination_department_id' => $definition['destination_department_id'] ?? null,
                'external_institution_id' => $definition['external_institution_id'] ?? null,
                'responsible_user_id' => $definition['responsible_user_id'],
                'destination_label' => $definition['destination_label'],
                'derived_at' => $this->now->copy()->subDays(6 - $index)->toDateTimeString(),
                'response_due_at' => $this->now->copy()->addDays($index + 1)->toDateTimeString(),
                'motive' => 'Derivación asociada a seguimiento del caso.',
                'narrative' => 'Se derivan antecedentes relevantes y acciones sugeridas para continuidad de intervención.',
                'response_text' => $definition['status'] === 'respondida' ? 'Se realizó intervención inicial y se agenda seguimiento.' : null,
                'follow_up_notes' => 'Revisar respuesta comprometida según criticidad.',
                'is_sensitive' => $definition['scope'] === 'external',
            ], $definition['actor']);
        });
    }

    private function seedMeasures(array $catalogs, Collection $directCases, $complaintCase, $dailyCase): Collection
    {
        $service = app(ConvivenciaMeasureService::class);
        $cases = collect([$directCases->first(), $directCases->get(1), $complaintCase, $dailyCase]);
        $statuses = ['en_proceso', 'cumplida', 'reprogramada', 'cerrada'];
        $types = ['reflexion_guiada', 'accion_reparatoria', 'mediacion', 'actividad_pedagogica'];
        $actors = [$this->convivenciaUser, $this->orientationUser, $this->psychologyUser, $this->directionUser];

        return collect(range(0, 3))->map(function (int $index) use ($service, $catalogs, $cases, $statuses, $types, $actors) {
            $case = $cases->get($index);
            $actor = $actors[$index];

            return $service->store([
                'case_id' => $case->id,
                'student_profile_id' => $case->student_profile_id,
                'course_section_id' => $case->course_section_id,
                'measure_type_item_id' => $this->catalogId($catalogs, 'measure_type', $types[$index]),
                'responsible_user_id' => $actor->id,
                'responsible_staff_id' => $actor->staff_id,
                'description' => 'Medida formativa registrada para trabajo reflexivo y reparación.',
                'training_objective' => 'Promover reflexión, reparación y compromiso conductual.',
                'assigned_at' => $this->now->copy()->subDays(5 - $index)->toDateTimeString(),
                'due_at' => $this->now->copy()->addDays($index - 1)->toDateTimeString(),
                'status' => $statuses[$index],
                'evidence_summary' => $index > 0 ? 'Acta breve y evidencia de cumplimiento.' : null,
                'student_reflection' => $index > 1 ? 'El estudiante reconoce impacto de la situación.' : null,
                'repair_action' => 'Compromiso de reparación y restitución del vínculo.',
                'responsible_notes' => 'Seguimiento coordinado con profesor jefe.',
                'closure_notes' => in_array($statuses[$index], ['cumplida', 'cerrada'], true) ? 'Medida validada por el equipo.' : null,
                'is_sensitive' => $case->is_sensitive,
            ], $actor);
        });
    }

    private function seedInterviews(array $catalogs, Collection $directCases, $complaintCase, $dailyCase): Collection
    {
        $service = app(ConvivenciaInterviewService::class);
        $cases = collect([$directCases->first(), $complaintCase, $dailyCase, $directCases->get(1)]);
        $types = ['estudiante', 'apoderado', 'funcionario', 'grupo_estudiantes'];
        $statuses = ['pendiente', 'realizado', 'reprogramado', 'cerrado'];
        $actors = [$this->convivenciaUser, $this->orientationUser, $this->inspectorUser, $this->psychologyUser];

        return collect(range(0, 3))->map(function (int $index) use ($service, $catalogs, $cases, $types, $statuses, $actors) {
            $case = $cases->get($index);
            $actor = $actors[$index];
            $student = StudentProfile::query()->find($case->student_profile_id);

            return $service->store([
                'case_id' => $case->id,
                'student_profile_id' => $case->student_profile_id,
                'course_section_id' => $case->course_section_id,
                'interview_type_item_id' => $this->catalogId($catalogs, 'interview_type', $types[$index]),
                'responsible_user_id' => $actor->id,
                'responsible_staff_id' => $actor->staff_id,
                'interview_at' => $this->now->copy()->subDays(4 - $index)->toDateTimeString(),
                'motive' => 'Entrevista registrada para seguimiento del caso y acuerdos formativos.',
                'topics' => 'Relato del hecho, impacto, responsabilidades y apoyos necesarios.',
                'agreements' => 'Mantener seguimiento semanal y comunicación con familia.',
                'commitments' => 'Cumplir medida, informar nuevos incidentes y participar en mediación.',
                'follow_up_date' => $this->now->copy()->addDays($index + 2)->format('Y-m-d'),
                'follow_up_status' => $statuses[$index],
                'internal_notes' => 'Registro interno de convivencia.',
                'participants' => [
                    ['student_profile_id' => $student?->id, 'participant_type' => 'estudiante', 'participant_role' => 'participante', 'full_name' => $student?->registered_name_resolved ?? 'Sin estudiante'],
                    ['user_id' => $actor->id, 'staff_id' => $actor->staff_id, 'participant_type' => 'funcionario', 'participant_role' => 'responsable', 'full_name' => $actor->name],
                ],
                'is_sensitive' => $case->is_sensitive,
            ], $actor);
        });
    }

    private function seedSociograms(EloquentCollection $enrollments): void
    {
        $service = app(ConvivenciaSociogramService::class);
        $students = $enrollments->take(6)->values();

        if ($students->count() < 2) {
            return;
        }

        $service->store([
            'academic_year_id' => $students->first()->academic_year_id,
            'course_section_id' => $students->first()->course_section_id,
            'title' => 'Sociograma diagnóstico primer semestre',
            'applied_on' => $this->now->copy()->subDays(7)->format('Y-m-d'),
            'status' => 'interpretado',
            'confidentiality_level' => 'alta_confidencialidad',
            'interpretation' => 'Se observan liderazgos positivos, un estudiante con baja reciprocidad y subgrupos marcados.',
            'questions' => [
                ['prompt' => '¿Con quién prefieres trabajar en equipo?', 'selection_type' => 'positiva', 'max_choices' => 3],
                ['prompt' => '¿Con quién te cuesta más convivir?', 'selection_type' => 'negativa', 'max_choices' => 2],
            ],
            'answers' => array_values(array_filter([
                ['question_order' => 1, 'respondent_student_id' => $students[0]->student_profile_id ?? null, 'selected_student_id' => $students[1]->student_profile_id ?? null, 'selection_type' => 'positiva'],
                ['question_order' => 1, 'respondent_student_id' => $students[1]->student_profile_id ?? null, 'selected_student_id' => $students[0]->student_profile_id ?? null, 'selection_type' => 'positiva'],
                $students->get(2) ? ['question_order' => 1, 'respondent_student_id' => $students[2]->student_profile_id, 'selected_student_id' => $students[0]->student_profile_id, 'selection_type' => 'positiva'] : null,
                $students->get(3) ? ['question_order' => 1, 'respondent_student_id' => $students[3]->student_profile_id, 'selected_student_id' => $students[0]->student_profile_id, 'selection_type' => 'positiva'] : null,
                ($students->get(4) && $students->get(5)) ? ['question_order' => 2, 'respondent_student_id' => $students[4]->student_profile_id, 'selected_student_id' => $students[5]->student_profile_id, 'selection_type' => 'negativa'] : null,
                ($students->get(3) && $students->get(5)) ? ['question_order' => 2, 'respondent_student_id' => $students[3]->student_profile_id, 'selected_student_id' => $students[5]->student_profile_id, 'selection_type' => 'negativa'] : null,
            ])),
            'is_sensitive' => true,
        ], $this->psychologyUser);
    }

    private function seedIdps(Collection $plans, EloquentCollection $enrollments): void
    {
        $activeYear = $this->activeAcademicYear();
        $plan = $plans->first();
        $dimensions = collect([
            ['code' => 'clima_convivencia', 'name' => 'Clima de convivencia escolar'],
            ['code' => 'participacion', 'name' => 'Participación y formación ciudadana'],
            ['code' => 'sentido_pertenencia', 'name' => 'Sentido de pertenencia'],
            ['code' => 'seguridad', 'name' => 'Percepción de seguridad'],
        ])->map(fn (array $dimension) => ConvivenciaIdpsDimension::query()->create($dimension + ['description' => 'Dimensión configurable del módulo.', 'active' => true, 'created_by' => $this->actor->id, 'updated_by' => $this->actor->id]))->keyBy('code');

        $period = ConvivenciaIdpsPeriod::query()->create([
            'academic_year_id' => $activeYear->id,
            'name' => 'Primer semestre 2026',
            'starts_on' => '2026-03-01',
            'ends_on' => '2026-07-15',
            'status' => 'cerrado',
            'notes' => 'Aplicación semestral de indicadores.',
            'created_by' => $this->actor->id,
            'updated_by' => $this->actor->id,
        ]);

        $instrument = ConvivenciaIdpsInstrument::query()->create([
            'dimension_id' => $dimensions['clima_convivencia']->id,
            'name' => 'Encuesta de clima de convivencia',
            'description' => 'Instrumento de percepción estudiantil.',
            'response_type' => 'escala',
            'scale_label' => '1 a 5',
            'active' => true,
            'created_by' => $this->actor->id,
            'updated_by' => $this->actor->id,
        ]);

        $grouped = $enrollments->groupBy('course_section_id')->take(3);

        foreach ($grouped as $courseSectionId => $items) {
            $course = $items->first()->courseSection;
            ConvivenciaIdpsResult::query()->create([
                'period_id' => $period->id,
                'dimension_id' => $dimensions['clima_convivencia']->id,
                'instrument_id' => $instrument->id,
                'academic_year_id' => $activeYear->id,
                'course_section_id' => $courseSectionId,
                'education_level_id' => $course?->education_level_id,
                'related_plan_id' => $plan?->id,
                'result_scope' => 'curso',
                'reference_label' => $course?->display_name,
                'score' => $this->faker->randomFloat(2, 3.1, 4.7),
                'percentage' => $this->faker->numberBetween(62, 93),
                'sample_size' => $items->count(),
                'qualitative_observations' => 'Se observan percepciones favorables con focos de mejora en recreos y resolución dialogada.',
                'improvement_actions' => 'Talleres de curso, mediación preventiva y revisión de recreos focalizados.',
                'is_sensitive' => false,
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]);
        }
    }

    private function ensureMinimumStudents(int $target): void
    {
        if (StudentProfile::query()->count() >= $target) {
            return;
        }

        $activeYear = $this->activeAcademicYear();
        $courses = CourseSection::query()->where('academic_year_id', $activeYear->id)->orderBy('id')->get();

        foreach (range(StudentProfile::query()->count() + 1, $target) as $index) {
            $course = $courses->get(($index - 1) % max($courses->count(), 1));
            $rut = sprintf('%d-%d', 33000000 + $index, (($index % 9) + 1));

            $student = StudentProfile::query()->updateOrCreate(
                ['rut' => $rut],
                [
                    'first_name' => $this->faker->firstName(),
                    'last_name' => $this->faker->lastName().' '.$this->faker->lastName(),
                    'birthdate' => $this->now->copy()->subYears(random_int(10, 17))->subDays(random_int(0, 300))->format('Y-m-d'),
                    'email' => 'estudiante.convivencia'.$index.'@cnscgestion.local',
                    'phone' => '+569'.$this->faker->numerify('8#######'),
                    'address' => $this->faker->streetAddress(),
                    'general_status' => 'activo',
                    'guardian_name' => $this->faker->name(),
                    'guardian_phone' => '+569'.$this->faker->numerify('8#######'),
                    'guardian_email' => 'apoderado.convivencia'.$index.'@cnscgestion.local',
                    'created_by' => $this->creator()->id,
                    'updated_by' => $this->creator()->id,
                ],
            );

            StudentEnrollment::query()->firstOrCreate(
                ['student_profile_id' => $student->id, 'academic_year_id' => $activeYear->id],
                [
                    'course_section_id' => $course?->id,
                    'enrollment_status' => 'regular',
                    'enrolled_at' => $activeYear->starts_at?->format('Y-m-d') ?? '2026-03-01',
                    'snapshot_year_name' => $activeYear->name,
                    'snapshot_level_name' => $course?->educationLevel?->name,
                    'snapshot_section_name' => $course?->section_name,
                    'snapshot_course_display_name' => $course?->display_name,
                ],
            );
        }
    }

    private function activeEnrollments(): EloquentCollection
    {
        return StudentEnrollment::query()
            ->with(['studentProfile', 'courseSection.educationLevel'])
            ->where('academic_year_id', $this->activeAcademicYear()->id)
            ->where('enrollment_status', 'regular')
            ->orderBy('course_section_id')
            ->orderBy('student_profile_id')
            ->get();
    }

    private function catalogId(array $catalogs, string $group, string $code): int
    {
        return $catalogs[$group][$code]->id;
    }
}
