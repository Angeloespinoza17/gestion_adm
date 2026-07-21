<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Convivencia\ConvivenciaCatalogItem;
use App\Models\Convivencia\ConvivenciaExternalInstitution;
use App\Models\Convivencia\ConvivenciaIdpsDimension;
use App\Models\Convivencia\ConvivenciaIdpsInstrument;
use App\Models\Convivencia\ConvivenciaIdpsPeriod;
use App\Models\Convivencia\ConvivenciaIdpsResult;
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
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConvivenciaSeeder extends ModuleSeeder
{
    use PreventsProductionSeeding;

    private \Faker\Generator $faker;

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
                'protocol_id' => $protocols->firstWhere('name', 'Protocolo de Maltrato Escolar')->id,
                'case_id' => $complaintCase->id,
                'status' => 'activo',
                'actions_taken' => 'Entrevista inicial, contención y comunicación a familia.',
                'measures_adopted' => 'Separación de involucrados y registro de evidencias.',
            ], $this->convivenciaUser);

            $closedActivation = app(ConvivenciaProtocolService::class)->activate([
                'protocol_id' => $protocols->firstWhere('name', 'Protocolo de Ciberacoso')->id,
                'complaint_id' => $complaints->get(1)->id,
                'status' => 'activo',
                'actions_taken' => 'Recepción de capturas y análisis inicial.',
                'measures_adopted' => 'Resguardo preventivo y contacto con apoderado.',
            ], $this->directionUser);

            app(ConvivenciaProtocolService::class)->updateActivation($closedActivation, [
                'status' => 'cerrado',
                'current_stage_name' => 'Cierre y restitución',
                'closing_summary' => 'Se completó revisión, entrevista y compromiso de no repetición.',
                'action_type' => 'cierre',
                'log_notes' => 'Se cierra protocolo con seguimiento derivado al caso.',
            ], $this->directionUser);

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
            ['slug' => 'convivencia_dashboard', 'name' => 'Dashboard', 'route' => '/convivencia', 'sort' => 1],
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
            if (!$role) {
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
        DB::table('convivencia_protocol_activation_logs')->delete();
        DB::table('convivencia_protocol_activations')->delete();
        DB::table('convivencia_complaints')->delete();
        DB::table('convivencia_protocol_steps')->delete();
        DB::table('convivencia_protocols')->delete();
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
                'contact_phone' => '+569' . $this->faker->numerify('7#######'),
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
        $service = app(ConvivenciaProtocolService::class);

        return collect([
            [
                'name' => 'Protocolo de Maltrato Escolar',
                'code' => 'maltrato_escolar',
                'criticality' => 'alta',
            ],
            [
                'name' => 'Protocolo de Ciberacoso',
                'code' => 'ciberacoso',
                'criticality' => 'alta',
            ],
            [
                'name' => 'Protocolo de Vulneración de Derechos',
                'code' => 'vulneracion_derechos',
                'criticality' => 'critica',
            ],
        ])->map(function (array $definition) use ($service, $catalogs) {
            return $service->store([
                'protocol_type_item_id' => $this->catalogId($catalogs, 'protocol_type', $definition['code']),
                'criticality_item_id' => $this->catalogId($catalogs, 'criticality', $definition['criticality']),
                'name' => $definition['name'],
                'description' => 'Protocolo institucional configurable del módulo Convivencia.',
                'required_documents' => 'Acta, relato inicial, evidencias y registro de medidas de resguardo.',
                'safeguard_measures' => 'Resguardo inmediato, comunicación a familia y seguimiento oportuno.',
                'minimal_actions' => 'Recepción, análisis, activación, entrevistas y cierre con trazabilidad.',
                'default_due_days' => 5,
                'status' => 'activo',
                'steps' => [
                    ['stage_name' => 'Recepción y análisis inicial', 'responsible_label' => 'Encargada de convivencia', 'due_days' => 1],
                    ['stage_name' => 'Entrevistas y resguardo', 'responsible_label' => 'Equipo interdisciplinario', 'due_days' => 3],
                    ['stage_name' => 'Seguimiento y cierre', 'responsible_label' => 'Dirección y convivencia', 'due_days' => 5],
                ],
            ], $this->convivenciaUser);
        });
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
                'contact_phone' => $complainantTypes[$index] === 'anonimo' ? null : '+569' . $this->faker->numerify('7#######'),
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
                    'last_name' => $this->faker->lastName() . ' ' . $this->faker->lastName(),
                    'birthdate' => $this->now->copy()->subYears(random_int(10, 17))->subDays(random_int(0, 300))->format('Y-m-d'),
                    'email' => 'estudiante.convivencia' . $index . '@cnscgestion.local',
                    'phone' => '+569' . $this->faker->numerify('8#######'),
                    'address' => $this->faker->streetAddress(),
                    'general_status' => 'activo',
                    'guardian_name' => $this->faker->name(),
                    'guardian_phone' => '+569' . $this->faker->numerify('8#######'),
                    'guardian_email' => 'apoderado.convivencia' . $index . '@cnscgestion.local',
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
