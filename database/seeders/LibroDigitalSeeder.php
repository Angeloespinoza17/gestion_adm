<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\SystemModule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LibroDigitalSeeder extends Seeder
{
    /** @var array<string, string> */
    private const PERMISSIONS = [
        'libro_digital.access' => 'Acceder al Libro Digital',
        'libro_digital.books.view' => 'Consultar libros y nominas',
        'libro_digital.books.manage' => 'Configurar y abrir libros',
        'libro_digital.subject_catalog.manage' => 'Administrar el catalogo institucional global de asignaturas',
        'libro_digital.curriculum.import' => 'Cargar y validar catalogos curriculares',
        'libro_digital.curriculum.approve' => 'Aprobar importaciones curriculares validadas',
        'libro_digital.curriculum.activate' => 'Activar versiones curriculares aprobadas',
        'libro_digital.sessions.view' => 'Consultar sesiones y leccionario',
        'libro_digital.sessions.manage' => 'Gestionar sesiones asignadas',
        'libro_digital.attendance.manage' => 'Registrar asistencia por sesion',
        'libro_digital.lesson.manage' => 'Registrar leccionario y cobertura',
        'libro_digital.sign' => 'Firmar clases propias',
        'libro_digital.assessments.manage' => 'Gestionar evaluaciones y resultados',
        'libro_digital.coexistence.view' => 'Consultar convivencia autorizada',
        'libro_digital.coexistence.manage' => 'Gestionar registros de convivencia',
        'libro_digital.pie.view' => 'Consultar registros PIE autorizados',
        'libro_digital.pie.manage' => 'Gestionar registros PIE autorizados',
        'libro_digital.withdrawals.manage' => 'Gestionar salidas y retiros',
        'libro_digital.absence.manage' => 'Gestionar ausencias prolongadas',
        'libro_digital.parvularia.manage' => 'Gestionar Libro Tecnico Pedagogico',
        'libro_digital.amendments.request' => 'Solicitar correcciones',
        'libro_digital.amendments.review' => 'Revisar correcciones',
        'libro_digital.amendments.apply' => 'Aplicar correcciones aprobadas',
        'libro_digital.closures.manage' => 'Gestionar cierres',
        'libro_digital.closures.reopen' => 'Autorizar reaperturas',
        'libro_digital.statistics.view' => 'Consultar estadisticas',
        'libro_digital.reports.view' => 'Consultar reportes',
        'libro_digital.reports.export' => 'Descargar reportes',
        'libro_digital.ede.manage' => 'Gestionar estandar y mapeos EDE',
        'libro_digital.ede.export' => 'Generar exportaciones EDE',
        'libro_digital.ede.validate' => 'Ejecutar validacion EDE',
        'libro_digital.ede.download' => 'Descargar paquetes de fiscalizacion',
        'libro_digital.audit.view' => 'Consultar auditoria',
        'libro_digital.audit.verify' => 'Verificar integridad de auditoria',
        'libro_digital.configuration.manage' => 'Administrar configuracion normativa',
    ];

    /** @var array<string, array<int, string>> */
    private const ROLE_PERMISSIONS = [
        'super_admin' => ['*'],
        'sostenedor' => [
            'libro_digital.access', 'libro_digital.books.view', 'libro_digital.sessions.view',
            'libro_digital.statistics.view', 'libro_digital.reports.view', 'libro_digital.reports.export',
            'libro_digital.ede.export', 'libro_digital.ede.download', 'libro_digital.audit.view',
        ],
        'direccion' => [
            'libro_digital.access', 'libro_digital.books.view', 'libro_digital.books.manage',
            'libro_digital.subject_catalog.manage', 'libro_digital.curriculum.approve',
            'libro_digital.curriculum.activate',
            'libro_digital.sessions.view', 'libro_digital.amendments.review', 'libro_digital.amendments.apply',
            'libro_digital.closures.manage', 'libro_digital.closures.reopen', 'libro_digital.statistics.view',
            'libro_digital.reports.view', 'libro_digital.reports.export', 'libro_digital.ede.export',
            'libro_digital.ede.validate', 'libro_digital.ede.download', 'libro_digital.audit.view',
        ],
        'subdirector' => [
            'libro_digital.access', 'libro_digital.books.view', 'libro_digital.books.manage',
            'libro_digital.curriculum.approve',
            'libro_digital.sessions.view', 'libro_digital.assessments.manage', 'libro_digital.amendments.review',
            'libro_digital.closures.manage', 'libro_digital.statistics.view', 'libro_digital.reports.view',
            'libro_digital.reports.export', 'libro_digital.audit.view',
        ],
        'coordinador_academico' => [
            'libro_digital.access', 'libro_digital.books.view', 'libro_digital.books.manage',
            'libro_digital.subject_catalog.manage', 'libro_digital.curriculum.import',
            'libro_digital.sessions.view', 'libro_digital.assessments.manage', 'libro_digital.amendments.review',
            'libro_digital.closures.manage', 'libro_digital.statistics.view', 'libro_digital.reports.view',
            'libro_digital.reports.export',
        ],
        'jefe_utp' => [
            'libro_digital.access', 'libro_digital.books.view', 'libro_digital.books.manage',
            'libro_digital.subject_catalog.manage', 'libro_digital.curriculum.import',
            'libro_digital.sessions.view', 'libro_digital.assessments.manage', 'libro_digital.amendments.review',
            'libro_digital.closures.manage', 'libro_digital.statistics.view', 'libro_digital.reports.view',
            'libro_digital.reports.export',
        ],
        'docente' => [
            'libro_digital.access', 'libro_digital.books.view', 'libro_digital.sessions.view',
            'libro_digital.sessions.manage', 'libro_digital.attendance.manage', 'libro_digital.lesson.manage',
            'libro_digital.sign', 'libro_digital.assessments.manage', 'libro_digital.amendments.request',
            'libro_digital.statistics.view', 'libro_digital.reports.view',
        ],
        'profesor_jefe' => [
            'libro_digital.access', 'libro_digital.books.view', 'libro_digital.sessions.view',
            'libro_digital.attendance.manage', 'libro_digital.coexistence.view', 'libro_digital.amendments.request',
            'libro_digital.statistics.view', 'libro_digital.reports.view',
        ],
        'educador_parvulos' => [
            'libro_digital.access', 'libro_digital.books.view', 'libro_digital.sessions.view',
            'libro_digital.sessions.manage', 'libro_digital.attendance.manage', 'libro_digital.lesson.manage',
            'libro_digital.sign', 'libro_digital.parvularia.manage', 'libro_digital.amendments.request',
            'libro_digital.statistics.view', 'libro_digital.reports.view',
        ],
        'inspectoria' => [
            'libro_digital.access', 'libro_digital.books.view', 'libro_digital.sessions.view',
            'libro_digital.attendance.manage', 'libro_digital.withdrawals.manage', 'libro_digital.absence.manage',
            'libro_digital.amendments.request', 'libro_digital.statistics.view', 'libro_digital.reports.view',
        ],
        'coordinador_inspectoria' => [
            'libro_digital.access', 'libro_digital.books.view', 'libro_digital.sessions.view',
            'libro_digital.attendance.manage', 'libro_digital.withdrawals.manage', 'libro_digital.absence.manage',
            'libro_digital.amendments.review', 'libro_digital.closures.manage', 'libro_digital.statistics.view',
            'libro_digital.reports.view', 'libro_digital.reports.export',
        ],
        'encargado_convivencia' => [
            'libro_digital.access', 'libro_digital.books.view', 'libro_digital.coexistence.view',
            'libro_digital.coexistence.manage', 'libro_digital.reports.view', 'libro_digital.reports.export',
        ],
        'coordinador_pie' => [
            'libro_digital.access', 'libro_digital.books.view', 'libro_digital.pie.view',
            'libro_digital.pie.manage', 'libro_digital.reports.view', 'libro_digital.reports.export',
        ],
        'profesional_pie' => [
            'libro_digital.access', 'libro_digital.books.view', 'libro_digital.pie.view',
            'libro_digital.pie.manage', 'libro_digital.reports.view',
        ],
        'secretaria' => [
            'libro_digital.access', 'libro_digital.books.view', 'libro_digital.sessions.view',
            'libro_digital.reports.view', 'libro_digital.reports.export',
        ],
        'administrador_matricula' => [
            'libro_digital.access', 'libro_digital.books.view', 'libro_digital.books.manage',
            'libro_digital.reports.view', 'libro_digital.reports.export',
        ],
        'auditor_interno' => [
            'libro_digital.access', 'libro_digital.books.view', 'libro_digital.sessions.view',
            'libro_digital.statistics.view', 'libro_digital.reports.view', 'libro_digital.reports.export',
            'libro_digital.audit.view', 'libro_digital.audit.verify', 'libro_digital.ede.download',
        ],
        'fiscalizador_consulta' => [
            'libro_digital.access', 'libro_digital.books.view', 'libro_digital.sessions.view',
            'libro_digital.reports.view', 'libro_digital.audit.view', 'libro_digital.ede.download',
        ],
        'soporte_tecnico_restringido' => [
            'libro_digital.access', 'libro_digital.audit.view',
        ],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('lcd_regulatory_profiles')) {
            $this->command?->warn('Las migraciones LCD aun no han sido ejecutadas.');

            return;
        }

        DB::transaction(function (): void {
            $this->seedRegulatoryProfiles();
            $this->seedNormativeSources();
            $this->seedFeatureFlagsAndBlockers();
            $this->seedSchoolContext();
            $this->seedRbacAndNavigation();
        });
    }

    private function seedRegulatoryProfiles(): void
    {
        $profiles = [
            [
                'code' => 'ESCOLAR_CIRCULAR_30_2023',
                'name' => 'Libro Digital Escolar - Circular de Registros',
                'version' => '2023.1',
                'authority' => 'Superintendencia de Educacion',
                'resolution_number' => 'REX 30/2021 modificada por REX 432/2023',
                'effective_from' => '2023-10-28',
                'effective_to' => null,
                'retention_years' => 5,
                'rules_snapshot' => [
                    'education_types' => ['basica', 'media'],
                    'attendance' => [
                        'daily_resolution' => 'requires_configured_policy',
                        'subsidy_resolution' => 'compliance_blocker',
                        'requires_session_signature' => true,
                    ],
                    'signature' => [
                        'required' => true,
                        'provider' => 'mineduc_identity_verifier',
                        'requires_complete_attendance' => true,
                        'requires_pedagogical_record' => true,
                    ],
                    'export' => ['ede_required' => true, 'formats' => ['json', 'csv', 'sqlite']],
                    'retention' => ['minimum_years_source' => 'EDE FAQ'],
                ],
                'source_hash' => null,
            ],
            [
                'code' => 'PARVULARIA_CIRCULAR_700_2025',
                'name' => 'Libro Tecnico Pedagogico de Educacion Parvularia',
                'version' => '2025.1',
                'authority' => 'Superintendencia de Educacion',
                'resolution_number' => 'REX 700/2025',
                'effective_from' => '2026-03-01',
                'effective_to' => null,
                'retention_years' => 5,
                'rules_snapshot' => [
                    'education_types' => ['parvularia'],
                    'attendance' => [
                        'daily_resolution' => 'compliance_blocker',
                        'subsidy_resolution' => 'compliance_blocker',
                        'parvularia_arrival_window_minutes' => null,
                        'requires_session_signature' => true,
                    ],
                    'signature' => [
                        'required' => true,
                        'provider' => 'mineduc_identity_verifier',
                        'requires_complete_attendance' => true,
                        'requires_pedagogical_record' => true,
                    ],
                    'export' => ['ede_required' => true, 'formats' => ['json', 'csv', 'sqlite']],
                    'retention' => [
                        'minimum_years_source' => 'EDE FAQ general',
                        'profile_specific_period_status' => 'compliance_blocker',
                    ],
                ],
                'source_hash' => null,
            ],
        ];

        foreach ($profiles as $profile) {
            $existing = DB::table('lcd_regulatory_profiles')
                ->where('code', $profile['code'])
                ->where('version', $profile['version'])
                ->first();
            $payload = [...$profile, 'rules_snapshot' => json_encode($profile['rules_snapshot'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'active' => true, 'updated_at' => now()];

            if ($existing) {
                DB::table('lcd_regulatory_profiles')->where('id', $existing->id)->update($payload);
            } else {
                DB::table('lcd_regulatory_profiles')->insert([
                    'public_id' => (string) Str::ulid(),
                    ...$payload,
                    'created_at' => now(),
                ]);
            }
        }
    }

    private function seedNormativeSources(): void
    {
        $schoolProfileId = DB::table('lcd_regulatory_profiles')->where('code', 'ESCOLAR_CIRCULAR_30_2023')->value('id');
        $parvProfileId = DB::table('lcd_regulatory_profiles')->where('code', 'PARVULARIA_CIRCULAR_700_2025')->value('id');
        $sources = [
            [
                'regulatory_profile_id' => $schoolProfileId,
                'title' => 'Establece Estandar de Datos para la Educacion',
                'authority' => 'Ministerio de Educacion',
                'document_number' => 'REX 3335/2020',
                'published_on' => '2020-08-03',
                'source_url' => 'https://drive.google.com/uc?export=download&id=10d1DM4_2s2uP1L3zjOKU1j485CZDVEgF',
                'sha256' => 'c8e8b791e5a3c6119fcdaaddfee35c993847878bdda156c6937ec9cf3f158aae',
                'status' => 'verified',
                'metadata' => ['bytes' => 578886],
            ],
            [
                'regulatory_profile_id' => $schoolProfileId,
                'title' => 'Modifica REX 3335 y fija texto refundido EDE',
                'authority' => 'Ministerio de Educacion',
                'document_number' => 'REX 917/2021',
                'published_on' => '2021-01-29',
                'source_url' => 'https://drive.google.com/uc?export=download&id=1CRnZNWgJHYxObWRIkudMvxNRi_sxTX61',
                'sha256' => '0439d3439b64343c223bb91a4bad13fc1614f94d23b197d7c88e3eb5a446c6dd',
                'status' => 'verified',
                'metadata' => ['bytes' => 723296],
            ],
            [
                'regulatory_profile_id' => $schoolProfileId,
                'title' => 'Mapeo oficial EDE/CEDS para Libro de Clases Digital',
                'authority' => 'Ministerio de Educacion',
                'document_number' => 'EDE_MAPPING_XLSX',
                'published_on' => null,
                'source_url' => 'https://docs.google.com/spreadsheets/d/1W5JNVZmO2_kYjvSU8zRQF-lMBCxDdvxguVobTQ0Jd-w/edit',
                'sha256' => '638eadb82f64a559311c2676ef7004fc70281396c73800923317f7a6fa2e9fd0',
                'status' => 'verified_with_blocker',
                'metadata' => ['bytes' => 312328, 'warning' => 'Contradiccion observada para numero de matricula: referencia 43 frente a 55.'],
            ],
            [
                'regulatory_profile_id' => $schoolProfileId,
                'title' => 'Circular sobre registros de informacion que deben mantener los establecimientos educacionales',
                'authority' => 'Superintendencia de Educacion',
                'document_number' => 'REX 30/2021 modificada 2023',
                'published_on' => '2021-04-05',
                'source_url' => 'https://www.supereduc.cl/wp-content/uploads/2021/01/Circular-Registros-Modificada-2023.pdf',
                'sha256' => null,
                'status' => 'blocked_source_download',
                'metadata' => ['reason' => 'WAF impidio archivar y verificar los bytes en la consulta.'],
            ],
            [
                'regulatory_profile_id' => $schoolProfileId,
                'title' => 'Modifica procedimiento de ausencia continua y baja excepcional',
                'authority' => 'Superintendencia de Educacion',
                'document_number' => 'REX 432/2023',
                'published_on' => '2023-10-28',
                'source_url' => 'https://www.bcn.cl/leychile/navegar?i=1197327',
                'sha256' => null,
                'status' => 'verified_metadata_only',
                'metadata' => ['rule' => 'No autoriza retiros automaticos; requiere procedimiento y resolucion humana.'],
            ],
            [
                'regulatory_profile_id' => $parvProfileId,
                'title' => 'Circular sobre registros para establecimientos de educacion parvularia',
                'authority' => 'Superintendencia de Educacion',
                'document_number' => 'REX 700/2025',
                'published_on' => '2025-11-29',
                'source_url' => 'https://www.supereduc.cl/wp-content/uploads/2025/11/REX-No-0700-APRUEBA-CIRCULAR-SOBRE-REGISTROS-DE-INFORMACION-QUE-DEBEN-MANTENER-LOS-EST.-DE-ED.-PARVULARIA.pdf',
                'sha256' => null,
                'status' => 'blocked_source_download',
                'metadata' => ['effective_from' => '2026-03-01', 'reason' => 'WAF impidio archivar y verificar los bytes en la consulta.'],
            ],
        ];

        foreach ($sources as $source) {
            $existing = DB::table('lcd_normative_sources')->where('document_number', $source['document_number'])->first();
            $payload = [
                ...$source,
                'consulted_at' => '2026-08-12 12:00:00',
                'metadata' => json_encode($source['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];

            if ($existing) {
                DB::table('lcd_normative_sources')->where('id', $existing->id)->update($payload);
            } else {
                DB::table('lcd_normative_sources')->insert([
                    'public_id' => (string) Str::ulid(),
                    ...$payload,
                    'created_at' => now(),
                ]);
            }
        }
    }

    private function seedFeatureFlagsAndBlockers(): void
    {
        foreach ([
            'lcd_enabled',
            'lcd_parvularia_enabled',
            'lcd_identity_verifier_enabled',
            'lcd_ede_export_enabled',
            'lcd_sige_reconciliation_enabled',
            'lcd_fiscalization_download_enabled',
        ] as $code) {
            DB::table('lcd_feature_flags')->insertOrIgnore([
                [
                    'scope_key' => 'global',
                    'code' => $code,
                    'school_id' => null,
                    'enabled' => false,
                    'configuration' => json_encode(['rollout' => 'disabled_by_default']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        // Governance values are insert-only. Re-running a production seeder must
        // never re-open a resolved blocker or overwrite a reviewed rollout choice.
        DB::table('lcd_settings')->insertOrIgnore([
            [
                'scope_key' => 'compliance',
                'key' => 'open_blockers',
                'school_id' => null,
                'academic_year_id' => null,
                'value' => json_encode([
                    ['code' => 'EDE_VERSION_DIGEST', 'feature_flag' => 'lcd_ede_export_enabled'],
                    ['code' => 'EDE_VALIDATOR_NOT_EXECUTED', 'feature_flag' => 'lcd_ede_export_enabled'],
                    ['code' => 'IDENTITY_VERIFIER_NOT_CONFIGURED', 'feature_flag' => 'lcd_identity_verifier_enabled'],
                    ['code' => 'CURRICULUM_OA_NOT_IMPORTED', 'feature_flag' => 'lcd_enabled'],
                    ['code' => 'SIGE_API_NOT_DOCUMENTED', 'feature_flag' => 'lcd_sige_reconciliation_enabled'],
                    ['code' => 'PARVULARIA_FINE_RULES_NOT_VERIFIED', 'feature_flag' => 'lcd_parvularia_enabled'],
                    ['code' => 'EDE_ENROLLMENT_REFERENCE_CONTRADICTION', 'feature_flag' => 'lcd_ede_export_enabled'],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_encrypted' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function seedSchoolContext(): void
    {
        $rbd = trim((string) config('libro_digital.default_school.rbd'));
        if ($rbd === '') {
            return;
        }

        $school = DB::table('lcd_schools')->where('rbd', $rbd)->first();
        $payload = [
            'name' => (string) config('libro_digital.default_school.name', 'Establecimiento educacional'),
            'legal_name' => config('libro_digital.default_school.legal_name'),
            'timezone' => (string) config('libro_digital.timezone', 'America/Santiago'),
            'active' => true,
            'updated_at' => now(),
        ];

        if ($school) {
            DB::table('lcd_schools')->where('id', $school->id)->update($payload);
            $schoolId = $school->id;
        } else {
            $schoolId = DB::table('lcd_schools')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'rbd' => $rbd,
                ...$payload,
                'created_at' => now(),
            ]);
        }

        $profileId = DB::table('lcd_regulatory_profiles')->where('code', 'ESCOLAR_CIRCULAR_30_2023')->value('id');
        if (Schema::hasTable('academic_years')) {
            DB::table('academic_years')->orderBy('year')->get()->each(function ($year) use ($schoolId, $rbd, $profileId): void {
                DB::table('lcd_school_academic_years')->updateOrInsert(
                    ['school_id' => $schoolId, 'academic_year_id' => $year->id],
                    [
                        'regulatory_profile_id' => $profileId,
                        'rbd_snapshot' => $rbd,
                        'year_snapshot' => $year->year,
                        'timezone_snapshot' => (string) config('libro_digital.timezone', 'America/Santiago'),
                        'active' => ! $year->is_closed,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            });
        }

        if (Schema::hasTable('users') && Schema::hasTable('lcd_school_users')) {
            DB::table('users')->where('active', true)->orderBy('id')->get(['id'])->each(function ($user) use ($schoolId): void {
                $roleSnapshot = Schema::hasTable('role_user')
                    ? DB::table('role_user')
                        ->join('roles', 'roles.id', '=', 'role_user.role_id')
                        ->where('role_user.user_id', $user->id)
                        ->orderBy('roles.id')
                        ->value('roles.slug')
                    : null;

                DB::table('lcd_school_users')->updateOrInsert(
                    ['school_id' => $schoolId, 'user_id' => $user->id],
                    [
                        'role_snapshot' => $roleSnapshot,
                        'permission_scope' => json_encode(['source' => 'single_school_migration']),
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            });
        }

        DB::table('lcd_feature_flags')->whereNull('school_id')->where('scope_key', 'global')->get()->each(function ($flag) use ($schoolId): void {
            DB::table('lcd_feature_flags')->insertOrIgnore([
                [
                    'scope_key' => 'school:'.$schoolId,
                    'code' => $flag->code,
                    'school_id' => $schoolId,
                    'enabled' => false,
                    'configuration' => json_encode(['inherits_global' => true]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        });
    }

    public function seedRbacAndNavigation(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('system_modules')) {
            return;
        }

        foreach (self::PERMISSIONS as $slug => $name) {
            Permission::query()->updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'description' => 'Permiso del Libro Digital de Clases.',
                'active' => true,
            ]);
        }

        $parent = SystemModule::query()->updateOrCreate(['slug' => 'libro_digital'], [
            'name' => 'Libro Digital',
            'frontend_route' => null,
            'icon' => 'bx-book-content',
            'sort_order' => 48,
            'active' => true,
            'parent_id' => null,
        ]);

        $leaves = [
            ['slug' => 'libro_digital_jornada', 'name' => 'Mi jornada', 'route' => '/libro-digital', 'sort' => 1, 'permission' => 'libro_digital.access'],
            ['slug' => 'libro_digital_books', 'name' => 'Libros y cursos', 'route' => '/libro-digital/books', 'sort' => 2, 'permission' => 'libro_digital.books.view'],
            ['slug' => 'libro_digital_subjects', 'name' => 'Asignaturas', 'route' => '/libro-digital/subjects', 'sort' => 3, 'permission' => 'libro_digital.books.manage'],
            ['slug' => 'libro_digital_objectives', 'name' => 'Objetivos curriculares', 'route' => '/libro-digital/objectives', 'sort' => 4, 'permission' => 'libro_digital.books.view'],
            ['slug' => 'libro_digital_control', 'name' => 'Firmas y cierres', 'route' => '/libro-digital/control', 'sort' => 5, 'permission' => 'libro_digital.closures.manage'],
            ['slug' => 'libro_digital_statistics', 'name' => 'Estadisticas', 'route' => '/libro-digital/statistics', 'sort' => 6, 'permission' => 'libro_digital.statistics.view'],
            ['slug' => 'libro_digital_reports', 'name' => 'Reportes e informes', 'route' => '/libro-digital/reports', 'sort' => 7, 'permission' => 'libro_digital.reports.view'],
            ['slug' => 'libro_digital_ede', 'name' => 'Fiscalizacion EDE', 'route' => '/libro-digital/ede', 'sort' => 8, 'permission' => 'libro_digital.ede.export'],
            ['slug' => 'libro_digital_audit', 'name' => 'Auditoria', 'route' => '/libro-digital/audit', 'sort' => 9, 'permission' => 'libro_digital.audit.view'],
            ['slug' => 'libro_digital_configuration', 'name' => 'Configuracion', 'route' => '/libro-digital/configuration', 'sort' => 10, 'permission' => 'libro_digital.configuration.manage'],
        ];

        $modulePermissions = [$parent->id => array_keys(self::PERMISSIONS)];
        foreach ($leaves as $leaf) {
            $module = SystemModule::query()->updateOrCreate(['slug' => $leaf['slug']], [
                'name' => $leaf['name'],
                'frontend_route' => $leaf['route'],
                'icon' => null,
                'sort_order' => $leaf['sort'],
                'active' => true,
                'parent_id' => $parent->id,
            ]);
            $modulePermissions[$module->id] = [$leaf['permission']];
        }

        if (Schema::hasTable('permission_groups') && Schema::hasTable('permission_group_permission')) {
            $group = PermissionGroup::query()->updateOrCreate(['slug' => 'libro_digital'], [
                'system_module_id' => $parent->id,
                'name' => 'Libro Digital',
                'description' => 'Operaciones, reportes y cumplimiento del Libro Digital.',
                'sort_order' => 1,
                'active' => true,
            ]);
            $group->permissions()->syncWithoutDetaching(Permission::query()->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id'));
        }

        foreach (self::ROLE_PERMISSIONS as $roleSlug => $slugs) {
            $role = Role::query()->firstOrCreate(['slug' => $roleSlug], [
                'name' => Str::headline(str_replace('_', ' ', $roleSlug)),
                'description' => 'Rol institucional para el Libro Digital.',
                'active' => true,
            ]);
            $effectiveSlugs = $slugs === ['*'] ? array_keys(self::PERMISSIONS) : $slugs;
            $permissionIds = Permission::query()->whereIn('slug', $effectiveSlugs)->pluck('id');
            $role->permissions()->syncWithoutDetaching($permissionIds);

            $moduleIds = collect($modulePermissions)
                ->filter(fn (array $required): bool => array_intersect($required, $effectiveSlugs) !== [])
                ->keys()
                ->push($parent->id)
                ->unique()
                ->all();
            $role->modules()->syncWithoutDetaching($moduleIds);
        }
    }
}
