<?php

namespace Database\Seeders;

use Database\Seeders\Modules\StaffModuleSeeder;
use Database\Seeders\Modules\StudentModuleSeeder;
use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\Supplier;
use App\Models\SystemModule;
use App\Models\User;
use App\Models\Infirmary\InfirmaryAccident;
use App\Models\Infirmary\InfirmaryAttentionCall;
use App\Models\Infirmary\InfirmaryDocument;
use App\Models\Infirmary\InfirmaryMedication;
use App\Models\Infirmary\InfirmaryMedicationAdministration;
use App\Models\Infirmary\InfirmaryMedicationAuthorization;
use App\Models\Infirmary\InfirmaryMedicationMovement;
use App\Services\Infirmary\InfirmaryAttentionService;
use App\Services\Infirmary\InfirmaryMedicationStockService;
use App\Services\Infirmary\InfirmaryStudentContextService;
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EnfermeriaSeeder extends Seeder
{
    private \Faker\Generator $faker;

    private User $actor;

    public function run(): void
    {
        $this->faker = Faker::create('es_CL');
        $this->faker->seed(20260628);

        $this->call([
            RbacSeeder::class,
            ChileLocationSeeder::class,
            SchoolDepartmentSeeder::class,
            DependencyTypeSeeder::class,
            SchoolDependencySeeder::class,
            StudentModuleSeeder::class,
            StaffModuleSeeder::class,
        ]);

        $this->actor = User::query()->where('email', 'ivonne.reyes@cnscgestion.local')->first()
            ?: User::query()->where('email', 'superadmin@cnscgestion.cl')->first()
            ?: User::query()->firstOrFail();

        $this->seedPermissionsAndModules();
        $this->ensureSuppliers();
        $this->ensureMinimumStudents(100);

        $medications = $this->seedMedications();
        $students = StudentProfile::query()->orderBy('id')->limit(100)->get();

        $authorizations = $this->seedAuthorizations($students, $medications);
        $attentions = $this->seedAttentions($students, $medications);
        $this->seedAccidents($students, $attentions);
        $this->seedStandaloneCalls($students);
        $this->seedAuthorizationAdministrations($authorizations);

        app(InfirmaryMedicationStockService::class)->refreshDynamicStatuses();
    }

    private function seedPermissionsAndModules(): void
    {
        $permissions = [
            ['slug' => 'ver_enfermeria', 'name' => 'Ver módulo Enfermería'],
            ['slug' => 'crear_atenciones_enfermeria', 'name' => 'Crear atenciones de Enfermería'],
            ['slug' => 'editar_atenciones_enfermeria', 'name' => 'Editar atenciones de Enfermería'],
            ['slug' => 'eliminar_atenciones_enfermeria', 'name' => 'Eliminar atenciones de Enfermería'],
            ['slug' => 'exportar_enfermeria', 'name' => 'Exportar módulo Enfermería'],
            ['slug' => 'administrar_inventario_enfermeria', 'name' => 'Administrar inventario de Enfermería'],
            ['slug' => 'administrar_medicamentos_enfermeria', 'name' => 'Administrar medicamentos de Enfermería'],
            ['slug' => 'gestionar_accidentes_enfermeria', 'name' => 'Gestionar accidentes escolares de Enfermería'],
            ['slug' => 'ver_reportes_enfermeria', 'name' => 'Ver reportes de Enfermería'],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'description' => 'Permiso del módulo de Enfermería Escolar.',
                    'active' => true,
                ],
            );
        }

        $parent = SystemModule::query()->updateOrCreate(
            ['slug' => 'infirmary'],
            [
                'name' => 'Enfermería',
                'frontend_route' => null,
                'icon' => 'bx-plus-medical',
                'sort_order' => 50,
                'active' => true,
                'parent_id' => null,
            ],
        );

        $children = [
            ['slug' => 'infirmary_dashboard', 'name' => 'Dashboard', 'route' => '/infirmary', 'sort' => 1],
            ['slug' => 'infirmary_attentions', 'name' => 'Atenciones', 'route' => '/infirmary/attentions', 'sort' => 2],
            ['slug' => 'infirmary_history', 'name' => 'Ficha médica', 'route' => '/infirmary/history', 'sort' => 3],
            ['slug' => 'infirmary_inventory', 'name' => 'Inventario medicamentos', 'route' => '/infirmary/inventory', 'sort' => 4],
            ['slug' => 'infirmary_medications', 'name' => 'Administración medicamentos', 'route' => '/infirmary/medications', 'sort' => 5],
            ['slug' => 'infirmary_accidents', 'name' => 'Accidentes escolares', 'route' => '/infirmary/accidents', 'sort' => 6],
            ['slug' => 'infirmary_calls', 'name' => 'Registro de llamados', 'route' => '/infirmary/calls', 'sort' => 7],
            ['slug' => 'infirmary_reports', 'name' => 'Reportes', 'route' => '/infirmary/reports', 'sort' => 8],
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

        $permissionsBySlug = Permission::query()->whereIn('slug', array_column($permissions, 'slug'))->get()->keyBy('slug');
        $modules = SystemModule::query()->whereIn('slug', array_merge(['infirmary'], array_column($children, 'slug')))->get()->keyBy('slug');

        $roleMap = [
            'super_admin' => array_keys($permissionsBySlug->all()),
            'administrador' => array_keys($permissionsBySlug->all()),
            'enfermeria' => array_keys($permissionsBySlug->all()),
            'direccion' => ['ver_enfermeria', 'ver_reportes_enfermeria', 'exportar_enfermeria'],
            'rrhh' => ['ver_enfermeria', 'ver_reportes_enfermeria', 'exportar_enfermeria'],
            'inspectoria' => [
                'ver_enfermeria',
                'crear_atenciones_enfermeria',
                'editar_atenciones_enfermeria',
                'gestionar_accidentes_enfermeria',
            ],
        ];

        $moduleRoleMap = [
            'super_admin' => $modules->keys()->all(),
            'administrador' => $modules->keys()->all(),
            'enfermeria' => $modules->keys()->all(),
            'direccion' => ['infirmary', 'infirmary_dashboard', 'infirmary_history', 'infirmary_reports'],
            'rrhh' => ['infirmary', 'infirmary_dashboard', 'infirmary_history', 'infirmary_reports'],
            'inspectoria' => ['infirmary', 'infirmary_dashboard', 'infirmary_attentions', 'infirmary_history', 'infirmary_accidents', 'infirmary_calls'],
        ];

        foreach ($roleMap as $roleSlug => $permissionSlugs) {
            $role = Role::query()->firstWhere('slug', $roleSlug);

            if (!$role) {
                continue;
            }

            $role->permissions()->syncWithoutDetaching(
                collect($permissionSlugs)
                    ->map(fn (string $slug) => $permissionsBySlug[$slug]?->id)
                    ->filter()
                    ->all()
            );

            $role->modules()->syncWithoutDetaching(
                collect($moduleRoleMap[$roleSlug] ?? [])
                    ->map(fn (string $slug) => $modules[$slug]?->id)
                    ->filter()
                    ->all()
            );
        }
    }

    private function ensureSuppliers(): void
    {
        $suppliers = [
            ['name' => 'Farmacia Escolar Austral', 'business_name' => 'Austral Salud SPA', 'rut' => '76.123.456-7'],
            ['name' => 'Distribuidora Clínica Sur', 'business_name' => 'Clínica Sur Limitada', 'rut' => '77.234.567-8'],
            ['name' => 'Botiquín Pedagógico', 'business_name' => 'Servicios Pedagógicos y Salud SPA', 'rut' => '78.345.678-9'],
            ['name' => 'MediKids Chile', 'business_name' => 'MediKids Chile SPA', 'rut' => '79.456.789-0'],
            ['name' => 'Insumos Valdivia', 'business_name' => 'Insumos Valdivia LTDA', 'rut' => '75.567.890-1'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::query()->updateOrCreate(
                ['rut' => $supplier['rut']],
                [
                    'name' => $supplier['name'],
                    'business_name' => $supplier['business_name'],
                    'email' => Str::slug($supplier['name'], '.') . '@proveedores.local',
                    'phone' => '+5697' . str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT),
                    'address' => 'Valdivia, Chile',
                    'active' => true,
                ],
            );
        }
    }

    private function ensureMinimumStudents(int $target): void
    {
        $currentCount = StudentProfile::query()->count();
        if ($currentCount >= $target) {
            return;
        }

        $activeYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $courses = CourseSection::query()->where('academic_year_id', $activeYear->id)->orderBy('id')->get();

        foreach (range($currentCount + 1, $target) as $index) {
            $rutNumber = 32000000 + $index;
            $rut = sprintf('%d-%d', $rutNumber, (($index % 9) + 1));
            $course = $courses->get(($index - 1) % max($courses->count(), 1));

            $student = StudentProfile::query()->updateOrCreate(
                ['rut' => $rut],
                [
                    'first_name' => $this->faker->firstNameFemale(),
                    'last_name' => $this->faker->lastName() . ' ' . $this->faker->lastName(),
                    'registered_name' => null,
                    'birthdate' => Carbon::now()->subYears(random_int(6, 18))->subDays(random_int(0, 364))->format('Y-m-d'),
                    'email' => 'estudiante.enfermeria' . $index . '@cnscgestion.local',
                    'phone' => '+5699' . str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT),
                    'address' => $this->faker->streetAddress(),
                    'general_status' => 'activo',
                    'guardian_name' => $this->faker->name(),
                    'guardian_relationship' => random_int(0, 1) ? 'Madre' : 'Padre',
                    'guardian_phone' => '+5698' . str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT),
                    'guardian_email' => 'apoderado.enfermeria' . $index . '@cnscgestion.local',
                    'guardian_backup_name' => $this->faker->name(),
                    'guardian_backup_relationship' => 'Abuela',
                    'guardian_backup_phone' => '+5698' . str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT),
                    'health_insurance' => random_int(0, 1) ? 'Fonasa' : 'Isapre',
                    'has_chronic_illness' => random_int(1, 100) <= 20,
                    'chronic_illness_details' => random_int(1, 100) <= 50 ? 'Control crónico en seguimiento.' : null,
                    'has_medication_allergies' => random_int(1, 100) <= 15,
                    'medication_allergies_details' => random_int(1, 100) <= 50 ? 'Alergia reportada a antibióticos.' : null,
                    'has_physical_restrictions' => random_int(1, 100) <= 10,
                    'physical_restrictions_details' => random_int(1, 100) <= 50 ? 'Evitar actividad física de alto impacto.' : null,
                    'created_by' => $this->actor->id,
                    'updated_by' => $this->actor->id,
                ],
            );

            User::query()->updateOrCreate(
                ['student_id' => $student->id],
                [
                    'cargo_id' => null,
                    'user_type' => 'student',
                    'active' => true,
                    'guardian_id' => null,
                    'staff_id' => null,
                    'name' => $student->full_name,
                    'email' => $student->email,
                    'password' => Hash::make('Estudiante123!'),
                ],
            );

            if ($course) {
                StudentEnrollment::query()->updateOrCreate(
                    [
                        'student_profile_id' => $student->id,
                        'academic_year_id' => $activeYear->id,
                    ],
                    array_merge([
                        'course_section_id' => $course->id,
                        'enrollment_status' => 'regular',
                        'enrolled_at' => $activeYear->starts_at?->format('Y-m-d') ?: now()->format('Y-m-d'),
                        'created_by' => $this->actor->id,
                        'updated_by' => $this->actor->id,
                    ], StudentEnrollment::snapshotPayload($activeYear, $course)),
                );
            }
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, InfirmaryMedication>
     */
    private function seedMedications()
    {
        $stockService = app(InfirmaryMedicationStockService::class);
        $suppliers = Supplier::query()->where('active', true)->get();
        $names = [
            'Paracetamol', 'Ibuprofeno', 'Loratadina', 'Suero fisiológico', 'Sales de rehidratación',
            'Clorfenamina', 'Omeprazol', 'Salbutamol', 'Glucosa oral', 'Agua oxigenada',
            'Povidona yodada', 'Clorhexidina', 'Gasas estériles', 'Apósitos adhesivos', 'Cinta hipoalergénica',
            'Vendas elásticas', 'Compresas frías', 'Compresas calientes', 'Termómetro digital', 'Guantes de procedimiento',
            'Mascarillas', 'Baja lenguas', 'Jeringas orales', 'Crema para quemaduras', 'Antiséptico en spray',
            'Ungüento cicatrizante', 'Tijeras de trauma', 'Inmovilizador de muñeca', 'Inmovilizador de tobillo', 'Tobillera elástica',
            'Collar cervical pediátrico', 'Parches oculares', 'Solución salina oftálmica', 'Pastillas de menta', 'Bicarbonato en sobres',
            'Tabletas de glucosa', 'Suplemento férrico', 'Vitamina C masticable', 'Apósito hidrocoloide', 'Alcohol gel',
            'Crema antihistamínica', 'Inhalador espaciador', 'Termómetro infrarrojo', 'Manta térmica', 'Botella de agua esterilizada',
            'Toallas desinfectantes', 'Nebulizador portátil', 'Cubre camilla desechable', 'Suplemento de zinc', 'Spray nasal salino',
        ];

        $items = collect();

        foreach ($names as $index => $name) {
            $stock = random_int(5, 60);
            if ($index % 11 === 0) {
                $stock = 0;
            } elseif ($index % 7 === 0) {
                $stock = random_int(1, 4);
            }

            $minimum = random_int(3, 8);
            $expiresAt = match (true) {
                $index % 13 === 0 => now()->subDays(random_int(1, 40)),
                $index % 5 === 0 => now()->addDays(random_int(3, 25)),
                default => now()->addDays(random_int(45, 300)),
            };

            $medication = InfirmaryMedication::query()->updateOrCreate(
                ['name' => $name, 'batch' => 'LOT-' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'commercial_name' => $name . ' Escolar',
                    'active_ingredient' => $name,
                    'presentation' => random_int(0, 1) ? 'Tabletas' : 'Solución',
                    'concentration' => random_int(0, 1) ? '500 mg' : '100 ml',
                    'unit' => random_int(0, 1) ? 'unidad' : 'ml',
                    'laboratory' => 'Laboratorio ' . chr(65 + ($index % 10)),
                    'current_stock' => 0,
                    'minimum_stock' => $minimum,
                    'physical_location' => 'Botiquín ' . (($index % 4) + 1),
                    'manufactured_at' => now()->subMonths(random_int(2, 18))->format('Y-m-d'),
                    'expires_at' => $expiresAt->format('Y-m-d'),
                    'supplier_id' => $suppliers->random()?->id,
                    'observations' => 'Medicamento de prueba para validación del módulo.',
                    'status' => 'disponible',
                    'active' => true,
                    'created_by' => $this->actor->id,
                    'updated_by' => $this->actor->id,
                ],
            );

            $items->push($medication);

            $stockService->increaseStock(
                $medication,
                InfirmaryMedicationMovement::TYPE_INGRESO,
                $stock,
                $this->actor,
                'Carga inicial de inventario',
                'Seeder de Enfermería',
            );

            if ($index % 4 === 0) {
                $stockService->decreaseStock(
                    $medication->fresh(),
                    InfirmaryMedicationMovement::TYPE_SALIDA,
                    random_int(1, 3),
                    $this->actor,
                    'Ajuste por consumo histórico',
                    'Seeder de Enfermería',
                );
            }
        }

        return $items->map(fn (InfirmaryMedication $item) => $item->fresh());
    }

    /**
     * @param  \Illuminate\Support\Collection<int, StudentProfile>  $students
     * @param  \Illuminate\Support\Collection<int, InfirmaryMedication>  $medications
     * @return \Illuminate\Support\Collection<int, InfirmaryMedicationAuthorization>
     */
    private function seedAuthorizations($students, $medications)
    {
        $items = collect();
        $selectedStudents = $students->shuffle()->take(30)->values();

        foreach ($selectedStudents as $index => $student) {
            $medication = $medications->get($index % max($medications->count(), 1));
            $startDate = now()->subDays(random_int(1, 120));
            $endDate = match (true) {
                $index % 10 === 0 => now()->subDays(random_int(1, 10)),
                $index % 4 === 0 => now()->addDays(random_int(5, 20)),
                default => now()->addDays(random_int(30, 180)),
            };

            $authorization = InfirmaryMedicationAuthorization::query()->create([
                'student_profile_id' => $student->id,
                'medication_id' => $medication->id,
                'diagnosis' => $this->faker->randomElement(['Asma', 'Rinitis alérgica', 'Tratamiento antibiótico', 'Control glicémico', 'Dolor crónico leve']),
                'dose' => $this->faker->randomElement(['1 comprimido', '5 ml', '10 ml', '2 puff', '1 cápsula']),
                'frequency' => $this->faker->randomElement(['Cada 8 horas', 'Cada 12 horas', 'Una vez al día', 'SOS']),
                'schedule_text' => $this->faker->randomElement(['08:00', '12:30', '14:00', '08:00 / 16:00', '09:00 / 13:00 / 17:00']),
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'physician_name' => 'Dr(a). ' . $this->faker->lastName(),
                'medical_authorization_expires_at' => $endDate->copy()->subDays(random_int(-5, 15))->format('Y-m-d'),
                'guardian_authorization_expires_at' => $endDate->copy()->subDays(random_int(-3, 20))->format('Y-m-d'),
                'observations' => 'Autorización permanente de prueba para administración escolar.',
                'status' => 'vigente',
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]);

            $this->attachTextDocument($authorization, $student->id, 'receta', 'receta_' . $authorization->id, 'Receta médica de prueba');
            $this->attachTextDocument($authorization, $student->id, 'autorizacion_medica', 'autorizacion_medica_' . $authorization->id, 'Autorización médica de prueba');
            $this->attachTextDocument($authorization, $student->id, 'autorizacion_apoderado', 'autorizacion_apoderado_' . $authorization->id, 'Autorización de apoderado de prueba');

            $items->push($authorization->fresh());
        }

        return $items;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, StudentProfile>  $students
     * @param  \Illuminate\Support\Collection<int, InfirmaryMedication>  $medications
     * @return \Illuminate\Support\Collection<int, \App\Models\Infirmary\InfirmaryAttention>
     */
    private function seedAttentions($students, $medications)
    {
        $attentionService = app(InfirmaryAttentionService::class);
        $studentContextService = app(InfirmaryStudentContextService::class);
        $dependencies = \App\Models\MaintenanceDependency::query()->where('active', true)->get();
        $inspectors = Staff::query()->whereHas('cargo', fn ($query) => $query->where('slug', 'inspectoria'))->get();
        $nurse = $this->actor;
        $items = collect();

        foreach (range(1, 80) as $index) {
            $student = $students->random();
            $enrollment = $studentContextService->currentEnrollment($student);
            $medication = $medications->random();
            $date = now()->subDays(random_int(0, 120))->setTime(random_int(8, 17), random_int(0, 59));
            $status = $index % 9 === 0 ? 'abierta' : ($index % 6 === 0 ? 'en_atencion' : 'finalizada');
            $withMedication = $index % 3 === 0;

            $payload = [
                'student_profile_id' => $student->id,
                'academic_year_id' => $enrollment?->academic_year_id,
                'course_section_id' => $enrollment?->course_section_id,
                'referred_by_staff_id' => $inspectors->random()?->id,
                'dependency_id' => $dependencies->random()?->id,
                'attention_category' => $this->faker->randomElement([
                    'accidente_escolar', 'malestar_general', 'control_signos_vitales', 'administracion_medicamento',
                    'curacion', 'contencion_emocional', 'control_cronico', 'otro',
                ]),
                'attended_at' => $date->format('Y-m-d H:i:s'),
                'accompanied_by_type' => $this->faker->randomElement(['sin_acompanante', 'inspectora', 'profesor', 'apoderado']),
                'accompanied_by_name' => null,
                'consultation_reason' => $this->faker->randomElement([
                    'Dolor de cabeza', 'Golpe en recreo', 'Control por medicamento', 'Malestar abdominal', 'Herida superficial',
                    'Contención emocional', 'Control de temperatura', 'Mareo transitorio',
                ]),
                'initial_description' => $this->faker->sentence(12),
                'observations' => $this->faker->optional()->sentence(10),
                'attention_duration_minutes' => random_int(8, 45),
                'priority' => $this->faker->randomElement(['baja', 'media', 'alta', 'emergencia']),
                'status' => $status,
                'treatments' => [[
                    'treatment_types' => array_values(array_unique([
                        $this->faker->randomElement(['compresa_fria', 'curaciones', 'reposo', 'vendaje', 'toma_temperatura', 'control_glicemia']),
                        $withMedication ? 'administracion_medicamento' : null,
                    ])),
                    'medication_id' => $withMedication ? $medication->id : null,
                    'medication_quantity' => $withMedication ? random_int(1, 2) : null,
                    'blood_pressure' => random_int(90, 130) . '/' . random_int(60, 85),
                    'pulse' => random_int(65, 110),
                    'respiratory_rate' => random_int(12, 24),
                    'temperature' => $this->faker->randomFloat(1, 36, 39.5),
                    'oxygen_saturation' => random_int(94, 100),
                    'weight' => random_int(20, 80),
                    'height' => $this->faker->randomFloat(2, 1.1, 1.8),
                    'vital_signs_notes' => $this->faker->optional()->sentence(8),
                    'emotional_support_required' => $index % 5 === 0,
                    'emotional_comment' => $index % 5 === 0 ? 'Se realizó contención breve y contención verbal.' : null,
                    'emotional_support_type' => $index % 5 === 0 ? 'Contención verbal' : null,
                    'emotional_duration_minutes' => $index % 5 === 0 ? random_int(5, 20) : null,
                    'other_treatments' => $this->faker->optional()->sentence(6),
                    'notes' => $this->faker->optional()->sentence(8),
                ]],
                'referrals' => $index % 4 === 0 ? [[
                    'referral_type' => $this->faker->randomElement(['regresa_a_sala', 'observacion_en_enfermeria', 'retiro_por_apoderado', 'cesfam', 'urgencias']),
                    'referred_at' => $date->copy()->addMinutes(random_int(5, 30))->format('Y-m-d H:i:s'),
                    'responsible_user_id' => $nurse->id,
                    'reason' => $this->faker->sentence(8),
                    'observations' => $this->faker->optional()->sentence(8),
                    'result' => $this->faker->optional()->sentence(8),
                ]] : [],
                'calls' => $index % 3 === 0 ? [[
                    'called_at' => $date->copy()->addMinutes(random_int(3, 20))->format('Y-m-d H:i:s'),
                    'person_contacted' => $student->guardian_name ?: $this->faker->name(),
                    'relationship' => $student->guardian_relationship ?: 'Apoderado',
                    'phone_number' => $student->guardian_phone ?: '+5698' . random_int(1000000, 9999999),
                    'call_status' => $this->faker->randomElement(['contesto', 'no_contesto', 'mensaje_dejado']),
                    'reason' => 'Aviso por atención de enfermería',
                    'conversation_summary' => $this->faker->sentence(12),
                    'commitments' => $this->faker->optional()->sentence(10),
                    'estimated_arrival_at' => $index % 2 === 0 ? $date->copy()->addMinutes(random_int(20, 80))->format('Y-m-d H:i:s') : null,
                    'duration_minutes' => random_int(1, 8),
                    'called_by_user_id' => $nurse->id,
                ]] : [],
                'follow_ups' => $index % 4 === 0 ? [[
                    'followed_at' => $date->copy()->addDay()->format('Y-m-d H:i:s'),
                    'responsible_user_id' => $nurse->id,
                    'comment' => $this->faker->sentence(12),
                    'status' => $index % 8 === 0 ? 'pendiente' : ($status === 'finalizada' ? 'cerrado' : 'en_proceso'),
                    'next_review_at' => $status === 'finalizada' ? null : $date->copy()->addDays(2)->format('Y-m-d H:i:s'),
                ]] : [],
            ];

            $attention = $attentionService->store($payload, $nurse);
            $items->push($attention);

            if ($index % 4 === 0) {
                $this->attachTextDocument($attention, $student->id, 'certificado_medico', 'certificado_' . $attention->id, 'Certificado médico ficticio');
            }

            if ($index % 6 === 0) {
                $this->attachImageDocument($attention, $student->id, 'fotografia', 'foto_atencion_' . $attention->id);
            }
        }

        return $items;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, StudentProfile>  $students
     * @param  \Illuminate\Support\Collection<int, \App\Models\Infirmary\InfirmaryAttention>  $attentions
     */
    private function seedAccidents($students, $attentions): void
    {
        $dependencies = \App\Models\MaintenanceDependency::query()->where('active', true)->get();
        $staff = Staff::query()->where('active', true)->get();

        foreach (range(1, 20) as $index) {
            $baseAttention = $attentions->get(($index - 1) % max($attentions->count(), 1));
            $student = $baseAttention?->student ?: $students->random();
            $enrollment = app(InfirmaryStudentContextService::class)->currentEnrollment($student);
            $occurredAt = ($baseAttention?->attended_at ? Carbon::parse($baseAttention->attended_at) : now())->copy()->subMinutes(random_int(5, 35));

            $accident = InfirmaryAccident::query()->create([
                'attention_id' => $baseAttention?->id,
                'student_profile_id' => $student->id,
                'academic_year_id' => $enrollment?->academic_year_id,
                'course_section_id' => $enrollment?->course_section_id,
                'dependency_id' => $dependencies->random()?->id,
                'occurred_at' => $occurredAt->format('Y-m-d H:i:s'),
                'accident_type' => $this->faker->randomElement(['Caída', 'Golpe', 'Corte superficial', 'Esguince', 'Contusión']),
                'place' => $this->faker->randomElement(['Patio', 'Sala', 'Gimnasio', 'Escalera', 'Cancha', 'Comedor']),
                'activity' => $this->faker->randomElement(['Recreo', 'Educación física', 'Cambio de sala', 'Almuerzo', 'Trabajo en aula']),
                'description' => $this->faker->sentence(18),
                'witnesses' => $this->faker->name() . ', ' . $this->faker->name(),
                'present_staff_id' => $staff->random()?->id,
                'severity' => $this->faker->randomElement(['leve', 'moderado', 'grave']),
                'observed_injuries' => $this->faker->sentence(10),
                'first_aid' => $this->faker->randomElement(['Aplicación de hielo', 'Curación básica', 'Reposo', 'Inmovilización preventiva']),
                'guardian_call_status' => $this->faker->randomElement(['contesto', 'no_contesto', 'mensaje_dejado']),
                'referral_destination' => $this->faker->randomElement(['Regresa a sala', 'Observación en enfermería', 'Retiro por apoderado', 'CESFAM', 'Urgencias']),
                'school_insurance' => $index % 3 === 0,
                'diat_number' => $index % 3 === 0 ? 'DIAT-' . now()->format('Y') . '-' . str_pad((string) $index, 4, '0', STR_PAD_LEFT) : null,
                'diat_generated_at' => $index % 3 === 0 ? now()->subDays(random_int(0, 5)) : null,
                'observations' => $this->faker->optional()->sentence(10),
                'case_status' => $index % 4 === 0 ? 'abierto' : ($index % 5 === 0 ? 'en_seguimiento' : 'cerrado'),
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]);

            $this->attachTextDocument($accident, $student->id, 'orden_atencion', 'diat_' . $accident->id, 'Documento DIAT ficticio');

            if ($index % 2 === 0) {
                $this->attachImageDocument($accident, $student->id, 'fotografia', 'foto_accidente_' . $accident->id);
            }
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, StudentProfile>  $students
     */
    private function seedStandaloneCalls($students): void
    {
        foreach (range(1, 15) as $index) {
            $student = $students->random();

            InfirmaryAttentionCall::query()->create([
                'student_profile_id' => $student->id,
                'attention_id' => null,
                'called_at' => now()->subDays(random_int(0, 45))->setTime(random_int(8, 18), random_int(0, 59)),
                'person_contacted' => $student->guardian_name ?: $this->faker->name(),
                'relationship' => $student->guardian_relationship ?: 'Apoderado',
                'phone_number' => $student->guardian_phone ?: '+5698' . random_int(1000000, 9999999),
                'call_status' => $this->faker->randomElement(['pendiente', 'contesto', 'no_contesto', 'mensaje_dejado']),
                'reason' => $this->faker->randomElement(['Seguimiento posterior', 'Coordinación de medicamento', 'Aviso de accidente', 'Recordatorio de receta']),
                'conversation_summary' => $this->faker->sentence(12),
                'commitments' => $this->faker->optional()->sentence(8),
                'estimated_arrival_at' => $index % 3 === 0 ? now()->addHours(random_int(1, 6)) : null,
                'duration_minutes' => random_int(1, 7),
                'called_by_user_id' => $this->actor->id,
            ]);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, InfirmaryMedicationAuthorization>  $authorizations
     */
    private function seedAuthorizationAdministrations($authorizations): void
    {
        $stockService = app(InfirmaryMedicationStockService::class);

        foreach ($authorizations as $index => $authorization) {
            foreach (range(1, random_int(1, 3)) as $iteration) {
                $adminDate = now()->subDays(random_int(0, 35))->setTime(random_int(8, 17), random_int(0, 59));
                $administration = InfirmaryMedicationAdministration::query()->create([
                    'authorization_id' => $authorization->id,
                    'attention_id' => null,
                    'medication_id' => $authorization->medication_id,
                    'student_profile_id' => $authorization->student_profile_id,
                    'administered_at' => $adminDate->format('Y-m-d H:i:s'),
                    'administered_by_user_id' => $this->actor->id,
                    'quantity_administered' => 1,
                    'schedule_reference' => $authorization->schedule_text,
                    'source_type' => 'autorizacion',
                    'observations' => 'Administración programada registrada por seeder.',
                ]);

                $stockService->decreaseStock(
                    $authorization->medication()->firstOrFail(),
                    InfirmaryMedicationMovement::TYPE_ADMINISTRACION,
                    1,
                    $this->actor,
                    'Administración programada por autorización médica',
                    'Seeder de Enfermería',
                    $administration,
                    $adminDate,
                );
            }
        }
    }

    private function attachTextDocument(Model $subject, ?int $studentId, string $category, string $basename, string $title): void
    {
        $path = sprintf('infirmary/%s/%s.txt', strtolower(class_basename($subject)), $basename);
        Storage::disk('public')->put($path, $title . PHP_EOL . 'Documento de prueba generado por EnfermeriaSeeder.');

        $subject->documents()->create([
            'student_profile_id' => $studentId,
            'category' => $category,
            'file_path' => $path,
            'original_name' => $basename . '.txt',
            'mime_type' => 'text/plain',
            'file_size' => Storage::disk('public')->size($path),
            'notes' => 'Documento ficticio generado automáticamente.',
            'uploaded_by' => $this->actor->id,
        ]);
    }

    private function attachImageDocument(Model $subject, ?int $studentId, string $category, string $basename): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9sGZk3wAAAAASUVORK5CYII=');
        $path = sprintf('infirmary/%s/%s.png', strtolower(class_basename($subject)), $basename);
        Storage::disk('public')->put($path, $png);

        $subject->documents()->create([
            'student_profile_id' => $studentId,
            'category' => $category,
            'file_path' => $path,
            'original_name' => $basename . '.png',
            'mime_type' => 'image/png',
            'file_size' => Storage::disk('public')->size($path),
            'notes' => 'Imagen ficticia generada automáticamente.',
            'uploaded_by' => $this->actor->id,
        ]);
    }
}
