<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Cargo;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\Library\BibliotecaAlerta;
use App\Models\Library\BibliotecaEjemplar;
use App\Models\Library\BibliotecaEspacio;
use App\Models\Library\BibliotecaInventarioMovimiento;
use App\Models\Library\BibliotecaObra;
use App\Models\Library\BibliotecaPlanLector;
use App\Models\Library\BibliotecaPrestamo;
use App\Models\Library\BibliotecaReserva;
use App\Models\Library\BibliotecaUsoEspacio;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\SystemModule;
use App\Models\User;
use App\Services\Library\BibliotecaAlertService;
use App\Services\Library\BibliotecaInventoryService;
use Carbon\Carbon;
use Database\Seeders\Modules\StaffModuleSeeder;
use Database\Seeders\Modules\StudentModuleSeeder;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BibliotecaSeeder extends Seeder
{
    private \Faker\Generator $faker;

    private User $actor;

    private Carbon $now;

    public function run(): void
    {
        $this->faker = Faker::create('es_CL');
        $this->faker->seed(20260628);
        $this->now = Carbon::parse('2026-06-28 10:00:00');

        $this->call([
            RbacSeeder::class,
            ChileLocationSeeder::class,
            SchoolDepartmentSeeder::class,
            StudentModuleSeeder::class,
            StaffModuleSeeder::class,
        ]);

        $this->ensureAcademicScaffolding();
        $this->ensureMinimumStudents(80);
        $this->ensureLibraryUser();
        $this->actor = User::query()
            ->where('email', 'biblioteca.cra@cnscgestion.local')
            ->orWhere('email', 'patricia.lopez@cnscgestion.local')
            ->orWhere('email', 'superadmin@cnscgestion.cl')
            ->firstOrFail();

        $this->seedPermissionsAndModules();
        $this->resetLibraryData();

        $works = $this->seedWorks(200);
        $exemplars = $this->seedExemplars($works, 500);
        $spaces = $this->seedSpaces();
        $plans = $this->seedReadingPlans($works);

        $this->seedLoans($exemplars);
        $this->seedReservations($exemplars);
        $this->seedDamagedAndLostExemplars($exemplars);
        $this->seedSpaceUsage($spaces);
        $this->seedAdditionalInventoryMovements();

        $inventoryService = app(BibliotecaInventoryService::class);
        $works->each(fn (BibliotecaObra $obra) => $inventoryService->refreshWorkAvailability($obra));

        app(BibliotecaAlertService::class)->refreshOperationalAlerts($this->actor);
    }

    private function seedPermissionsAndModules(): void
    {
        $permissions = [
            ['slug' => 'ver_modulo_biblioteca', 'name' => 'Ver módulo Biblioteca Escolar'],
            ['slug' => 'crear_libros_biblioteca', 'name' => 'Crear libros de Biblioteca'],
            ['slug' => 'editar_libros_biblioteca', 'name' => 'Editar libros de Biblioteca'],
            ['slug' => 'eliminar_libros_biblioteca', 'name' => 'Eliminar libros de Biblioteca'],
            ['slug' => 'administrar_catalogo_biblioteca', 'name' => 'Administrar catálogo bibliográfico'],
            ['slug' => 'administrar_inventario_biblioteca', 'name' => 'Administrar inventario de Biblioteca'],
            ['slug' => 'registrar_prestamos_biblioteca', 'name' => 'Registrar préstamos de Biblioteca'],
            ['slug' => 'registrar_devoluciones_biblioteca', 'name' => 'Registrar devoluciones de Biblioteca'],
            ['slug' => 'renovar_prestamos_biblioteca', 'name' => 'Renovar préstamos de Biblioteca'],
            ['slug' => 'gestionar_mora_biblioteca', 'name' => 'Gestionar mora de Biblioteca'],
            ['slug' => 'gestionar_reservas_biblioteca', 'name' => 'Gestionar reservas de Biblioteca'],
            ['slug' => 'gestionar_plan_lector_biblioteca', 'name' => 'Gestionar plan lector de Biblioteca'],
            ['slug' => 'gestionar_uso_espacios_biblioteca', 'name' => 'Gestionar uso de espacios de Biblioteca'],
            ['slug' => 'ver_estadisticas_biblioteca', 'name' => 'Ver estadísticas de Biblioteca'],
            ['slug' => 'exportar_reportes_biblioteca', 'name' => 'Exportar reportes de Biblioteca'],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'description' => 'Permiso correspondiente al módulo Biblioteca Escolar / CRA.',
                    'active' => true,
                ]
            );
        }

        $parent = SystemModule::query()->updateOrCreate(
            ['slug' => 'biblioteca'],
            [
                'name' => 'Biblioteca Escolar',
                'frontend_route' => null,
                'icon' => 'bx-book-reader',
                'sort_order' => 85,
                'active' => true,
                'parent_id' => null,
            ]
        );

        $children = [
            ['slug' => 'biblioteca_dashboard', 'name' => 'Dashboard', 'route' => '/biblioteca', 'sort' => 1],
            ['slug' => 'biblioteca_catalogo', 'name' => 'Catálogo', 'route' => '/biblioteca/catalogo', 'sort' => 2],
            ['slug' => 'biblioteca_inventario', 'name' => 'Ejemplares e inventario', 'route' => '/biblioteca/inventario', 'sort' => 3],
            ['slug' => 'biblioteca_prestamos', 'name' => 'Préstamos y devoluciones', 'route' => '/biblioteca/prestamos', 'sort' => 4],
            ['slug' => 'biblioteca_reservas', 'name' => 'Reservas de recursos', 'route' => '/biblioteca/reservas', 'sort' => 5],
            ['slug' => 'biblioteca_plan_lector', 'name' => 'Plan lector', 'route' => '/biblioteca/plan-lector', 'sort' => 6],
            ['slug' => 'biblioteca_espacios', 'name' => 'Uso de espacios', 'route' => '/biblioteca/espacios', 'sort' => 7],
            ['slug' => 'biblioteca_reportes', 'name' => 'Reportes', 'route' => '/biblioteca/reportes', 'sort' => 8],
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
                ]
            );
        }

        $permissionsBySlug = Permission::query()->whereIn('slug', array_column($permissions, 'slug'))->get()->keyBy('slug');
        $modules = SystemModule::query()->whereIn('slug', array_merge(['biblioteca'], array_column($children, 'slug')))->get()->keyBy('slug');

        $rolePermissionMap = [
            'super_admin' => array_keys($permissionsBySlug->all()),
            'administrador' => array_keys($permissionsBySlug->all()),
            'coordinador_academico' => [
                'ver_modulo_biblioteca',
                'gestionar_plan_lector_biblioteca',
                'ver_estadisticas_biblioteca',
                'exportar_reportes_biblioteca',
            ],
            'direccion' => [
                'ver_modulo_biblioteca',
                'ver_estadisticas_biblioteca',
                'exportar_reportes_biblioteca',
            ],
            'inspectoria' => [
                'ver_modulo_biblioteca',
                'registrar_prestamos_biblioteca',
                'registrar_devoluciones_biblioteca',
                'renovar_prestamos_biblioteca',
                'gestionar_mora_biblioteca',
                'gestionar_reservas_biblioteca',
            ],
            'docente' => [
                'ver_modulo_biblioteca',
                'gestionar_reservas_biblioteca',
                'gestionar_plan_lector_biblioteca',
                'gestionar_uso_espacios_biblioteca',
            ],
        ];

        $roleModuleMap = [
            'super_admin' => $modules->keys()->all(),
            'administrador' => $modules->keys()->all(),
            'coordinador_academico' => ['biblioteca', 'biblioteca_dashboard', 'biblioteca_catalogo', 'biblioteca_plan_lector', 'biblioteca_reportes'],
            'direccion' => ['biblioteca', 'biblioteca_dashboard', 'biblioteca_reportes'],
            'inspectoria' => ['biblioteca', 'biblioteca_dashboard', 'biblioteca_prestamos', 'biblioteca_reservas', 'biblioteca_catalogo'],
            'docente' => ['biblioteca', 'biblioteca_dashboard', 'biblioteca_reservas', 'biblioteca_plan_lector', 'biblioteca_espacios'],
        ];

        foreach ($rolePermissionMap as $roleSlug => $permissionSlugs) {
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
                collect($roleModuleMap[$roleSlug] ?? [])
                    ->map(fn (string $slug) => $modules[$slug]?->id)
                    ->filter()
                    ->all()
            );
        }
    }

    private function resetLibraryData(): void
    {
        BibliotecaAlerta::query()->delete();
        BibliotecaInventarioMovimiento::query()->delete();
        BibliotecaUsoEspacio::query()->delete();
        BibliotecaEspacio::query()->delete();
        BibliotecaPlanLector::query()->delete();
        BibliotecaReserva::query()->delete();
        BibliotecaPrestamo::query()->delete();
        BibliotecaEjemplar::query()->delete();
        BibliotecaObra::query()->delete();
    }

    /**
     * @return Collection<int, BibliotecaObra>
     */
    private function seedWorks(int $count): Collection
    {
        $categories = ['Narrativa', 'Ciencias', 'Historia', 'Lenguaje', 'Matemática', 'Arte', 'Tecnología', 'CRA', 'Convivencia', 'PIE'];
        $genres = ['Novela', 'Cuento', 'Poesía', 'Ensayo', 'Manual', 'Atlas', 'Investigación'];
        $levels = ['NT1', 'NT2', '1° básico', '3° básico', '5° básico', '7° básico', '1° medio', '3° medio'];
        $languages = ['Español', 'Inglés', 'Mapudungun'];
        $materialTypes = array_merge(array_fill(0, 140, 'libro'), array_fill(0, 10, 'diccionario'), array_fill(0, 10, 'enciclopedia'), array_fill(0, 15, 'tablet'), array_fill(0, 10, 'notebook'), array_fill(0, 5, 'proyector'), array_fill(0, 5, 'parlante'), array_fill(0, 5, 'juego_educativo'));
        $courses = CourseSection::query()->orderBy('display_name')->get();

        $items = collect();

        foreach (range(1, $count) as $index) {
            $materialType = $materialTypes[$index - 1] ?? 'otro';
            $obra = BibliotecaObra::query()->create([
                'material_type' => $materialType,
                'title' => $this->titleForMaterialType($materialType, $index),
                'subtitle' => $index % 4 === 0 ? 'Edición CRA ' . $this->faker->year() : null,
                'main_author' => $this->faker->name(),
                'secondary_authors' => $index % 3 === 0 ? [$this->faker->name(), $this->faker->name()] : [],
                'publisher' => $this->faker->company(),
                'publication_year' => random_int(1998, 2026),
                'isbn' => sprintf('978-956-%04d-%03d-%d', random_int(1000, 9999), $index, random_int(0, 9)),
                'category' => $categories[array_rand($categories)],
                'subcategory' => 'Colección ' . chr(64 + (($index % 5) + 1)),
                'genre' => $genres[array_rand($genres)],
                'recommended_level' => $levels[array_rand($levels)],
                'recommended_course_section_id' => $courses->random()?->id,
                'language' => $languages[array_rand($languages)],
                'page_count' => in_array($materialType, ['tablet', 'notebook', 'proyector', 'parlante'], true) ? null : random_int(40, 420),
                'description' => $this->faker->paragraph(2),
                'keywords' => [$this->faker->word(), $this->faker->word(), $this->faker->word()],
                'cover_image_url' => 'https://picsum.photos/seed/biblioteca-obra-' . $index . '/320/480',
                'internal_code' => sprintf('BIB-OBR-%04d', $index),
                'barcode' => sprintf('QR-OBR-%05d', $index),
                'physical_location' => 'Biblioteca Central',
                'shelf' => 'Estante ' . chr(64 + (($index % 8) + 1)),
                'section' => 'Zona ' . (($index % 4) + 1),
                'general_status' => 'disponible',
                'observations' => $index % 9 === 0 ? 'Recurso con alta demanda en plan lector.' : null,
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]);

            $items->push($obra);
        }

        return $items;
    }

    /**
     * @param  Collection<int, BibliotecaObra>  $works
     * @return Collection<int, BibliotecaEjemplar>
     */
    private function seedExemplars(Collection $works, int $count): Collection
    {
        $origins = BibliotecaEjemplar::ORIGIN_OPTIONS;
        $states = ['nuevo', 'bueno', 'regular'];
        $exemplars = collect();

        foreach (range(1, $count) as $index) {
            $obra = $works[$index % $works->count()];
            $exemplars->push(BibliotecaEjemplar::query()->create([
                'biblioteca_obra_id' => $obra->id,
                'code' => sprintf('BIB-EJ-%05d', $index),
                'barcode' => sprintf('QR-EJ-%05d', $index),
                'ingress_date' => $this->now->copy()->subDays(random_int(10, 1200))->format('Y-m-d'),
                'origin' => $origins[array_rand($origins)],
                'estimated_value' => random_int(4000, 65000),
                'physical_location' => $obra->physical_location,
                'physical_state' => $states[array_rand($states)],
                'availability_status' => 'disponible',
                'registered_by' => $this->actor->id,
                'observations' => $index % 11 === 0 ? 'Ejemplar utilizado en apoyo de aula.' : null,
                'photo_urls' => ['https://picsum.photos/seed/biblioteca-ejemplar-' . $index . '/640/480'],
                'last_inventory_checked_at' => $index % 3 === 0 ? $this->now->copy()->subMonths(random_int(0, 11))->format('Y-m-d') : null,
                'is_active' => true,
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]));
        }

        return $exemplars;
    }

    /**
     * @return Collection<int, BibliotecaEspacio>
     */
    private function seedSpaces(): Collection
    {
        $spaceNames = [
            'Biblioteca',
            'Sala de lectura',
            'Sala CRA',
            'Rincón lector',
            'Sala audiovisual',
            'Sala de estudio',
            'Espacio de cuentacuentos',
            'Sala de recursos',
            'Laboratorio móvil CRA',
            'Zona de mediación lectora',
        ];

        return collect($spaceNames)->map(function (string $name, int $index) {
            return BibliotecaEspacio::query()->create([
                'name' => $name,
                'location' => 'Piso ' . (($index % 2) + 1),
                'capacity' => random_int(12, 45),
                'resources' => ['Notebook', 'Proyector', 'Colección apoyo #' . ($index + 1)],
                'active' => true,
                'notes' => 'Espacio disponible para actividades CRA.',
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]);
        });
    }

    /**
     * @param  Collection<int, BibliotecaObra>  $works
     * @return Collection<int, BibliotecaPlanLector>
     */
    private function seedReadingPlans(Collection $works): Collection
    {
        $courses = CourseSection::query()->orderBy('display_name')->get();
        $years = AcademicYear::query()->ordered()->get();
        $teachers = Staff::query()->orderBy('full_name')->get();
        $subjects = ['Lenguaje', 'Historia', 'Ciencias', 'Inglés', 'Artes'];
        $statuses = ['planificado', 'en_ejecucion', 'finalizado', 'suspendido'];

        $items = collect();

        foreach (range(1, 20) as $index) {
            $course = $courses[$index % max($courses->count(), 1)];
            $obra = $works[$index % $works->count()];
            $startDate = $this->now->copy()->subMonths(random_int(0, 5))->startOfMonth()->addDays(random_int(0, 10));
            $endDate = $startDate->copy()->addWeeks(random_int(3, 8));

            $items->push(BibliotecaPlanLector::query()->create([
                'academic_year_id' => $course?->academic_year_id ?? $years->first()?->id,
                'course_section_id' => $course?->id,
                'subject' => $subjects[array_rand($subjects)],
                'responsible_staff_id' => $teachers->random()?->id,
                'biblioteca_obra_id' => $obra->id,
                'period' => 'Periodo ' . (($index % 4) + 1),
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'objective' => 'Fortalecer comprensión lectora y conversación literaria.',
                'associated_activity' => 'Bitácora de lectura, foro y presentación grupal.',
                'evaluation_description' => 'Rúbrica de comprensión, debate y producción escrita.',
                'required_copies' => random_int(6, 20),
                'available_copies' => BibliotecaEjemplar::query()
                    ->where('biblioteca_obra_id', $obra->id)
                    ->where('availability_status', 'disponible')
                    ->where('is_active', true)
                    ->count(),
                'fulfillment_percentage' => random_int(0, 100),
                'status' => $statuses[array_rand($statuses)],
                'notes' => $index % 5 === 0 ? 'Curso con seguimiento por alta demanda.' : null,
                'attachments' => ['https://example.com/guia-' . $index, 'https://example.com/pauta-' . $index],
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]));
        }

        return $items;
    }

    /**
     * @param  Collection<int, BibliotecaEjemplar>  $exemplars
     */
    private function seedLoans(Collection $exemplars): void
    {
        $studentBorrowers = StudentProfile::query()->with(['enrollments.courseSection', 'enrollments.academicYear'])->limit(40)->get();
        $staffBorrowers = Staff::query()->limit(12)->get();
        $courseBorrowers = CourseSection::query()->limit(10)->get();

        $historical = $exemplars->take(100)->values();
        $active = $exemplars->slice(100, 30)->values();
        $overdue = $exemplars->slice(130, 20)->values();

        foreach ($historical as $index => $ejemplar) {
            [$borrowerType, $student, $staff, $course, $name, $courseName] = $this->borrowerSnapshot($studentBorrowers, $staffBorrowers, $courseBorrowers, $index);
            $borrowedAt = $this->now->copy()->subDays(random_int(50, 320))->setTime(random_int(8, 17), [0, 15, 30, 45][array_rand([0, 15, 30, 45])]);
            $dueAt = $borrowedAt->copy()->addDays(random_int(7, 18));
            $returnedAt = $dueAt->copy()->addDays(random_int(-2, 10));

            $loan = BibliotecaPrestamo::query()->create([
                'loan_code' => sprintf('PRE-HIS-%04d', $index + 1),
                'borrower_type' => $borrowerType,
                'student_profile_id' => $student?->id,
                'staff_id' => $staff?->id,
                'course_section_id' => $course?->id,
                'academic_year_id' => $course?->academic_year_id,
                'biblioteca_obra_id' => $ejemplar->biblioteca_obra_id,
                'biblioteca_ejemplar_id' => $ejemplar->id,
                'borrower_name_snapshot' => $name,
                'course_name_snapshot' => $courseName,
                'borrowed_at' => $borrowedAt,
                'due_at' => $dueAt->format('Y-m-d'),
                'returned_at' => $returnedAt,
                'status' => 'devuelto',
                'renewed_count' => random_int(0, 1),
                'overdue_days' => max($returnedAt->diffInDays($dueAt, false), 0),
                'returned_condition' => 'bueno',
                'notes' => 'Préstamo histórico de biblioteca.',
                'audit_trail' => [['event' => 'prestamo_historial', 'at' => $borrowedAt->toDateTimeString(), 'by' => $this->actor->id]],
                'delivered_by_user_id' => $this->actor->id,
                'received_by_user_id' => $this->actor->id,
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]);

            BibliotecaInventarioMovimiento::query()->create([
                'biblioteca_ejemplar_id' => $ejemplar->id,
                'movement_type' => 'prestamo',
                'previous_location' => $ejemplar->physical_location,
                'new_location' => $ejemplar->physical_location,
                'previous_state' => $ejemplar->physical_state,
                'new_state' => $ejemplar->physical_state,
                'movement_date' => $borrowedAt,
                'notes' => 'Salida a préstamo histórico.',
                'responsible_user_id' => $this->actor->id,
                'metadata' => ['loan_id' => $loan->id],
            ]);

            BibliotecaInventarioMovimiento::query()->create([
                'biblioteca_ejemplar_id' => $ejemplar->id,
                'movement_type' => 'devolucion',
                'previous_location' => $ejemplar->physical_location,
                'new_location' => $ejemplar->physical_location,
                'previous_state' => $ejemplar->physical_state,
                'new_state' => $ejemplar->physical_state,
                'movement_date' => $returnedAt,
                'notes' => 'Devolución histórica.',
                'responsible_user_id' => $this->actor->id,
                'metadata' => ['loan_id' => $loan->id],
            ]);
        }

        foreach ($active as $index => $ejemplar) {
            [$borrowerType, $student, $staff, $course, $name, $courseName] = $this->borrowerSnapshot($studentBorrowers, $staffBorrowers, $courseBorrowers, $index + 100);
            $borrowedAt = $this->now->copy()->subDays(random_int(1, 20));
            $dueAt = $this->now->copy()->addDays(random_int(2, 18));
            $status = $index % 3 === 0 ? 'renovado' : 'activo';

            BibliotecaPrestamo::query()->create([
                'loan_code' => sprintf('PRE-ACT-%04d', $index + 1),
                'borrower_type' => $borrowerType,
                'student_profile_id' => $student?->id,
                'staff_id' => $staff?->id,
                'course_section_id' => $course?->id,
                'academic_year_id' => $course?->academic_year_id,
                'biblioteca_obra_id' => $ejemplar->biblioteca_obra_id,
                'biblioteca_ejemplar_id' => $ejemplar->id,
                'borrower_name_snapshot' => $name,
                'course_name_snapshot' => $courseName,
                'borrowed_at' => $borrowedAt,
                'due_at' => $dueAt->format('Y-m-d'),
                'status' => $status,
                'renewed_count' => $status === 'renovado' ? 1 : 0,
                'overdue_days' => 0,
                'notes' => 'Préstamo activo para monitoreo CRA.',
                'audit_trail' => [['event' => 'prestamo_activo', 'at' => $borrowedAt->toDateTimeString(), 'by' => $this->actor->id]],
                'delivered_by_user_id' => $this->actor->id,
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]);

            $ejemplar->forceFill([
                'availability_status' => 'prestado',
                'updated_by' => $this->actor->id,
            ])->save();

            BibliotecaInventarioMovimiento::query()->create([
                'biblioteca_ejemplar_id' => $ejemplar->id,
                'movement_type' => 'prestamo',
                'previous_location' => $ejemplar->physical_location,
                'new_location' => $ejemplar->physical_location,
                'previous_state' => $ejemplar->physical_state,
                'new_state' => $ejemplar->physical_state,
                'movement_date' => $borrowedAt,
                'notes' => 'Préstamo activo.',
                'responsible_user_id' => $this->actor->id,
                'metadata' => ['status' => $status],
            ]);
        }

        foreach ($overdue as $index => $ejemplar) {
            [$borrowerType, $student, $staff, $course, $name, $courseName] = $this->borrowerSnapshot($studentBorrowers, $staffBorrowers, $courseBorrowers, $index + 200);
            $borrowedAt = $this->now->copy()->subDays(random_int(10, 40));
            $dueAt = $this->now->copy()->subDays(random_int(2, 18));

            BibliotecaPrestamo::query()->create([
                'loan_code' => sprintf('PRE-VEN-%04d', $index + 1),
                'borrower_type' => $borrowerType,
                'student_profile_id' => $student?->id,
                'staff_id' => $staff?->id,
                'course_section_id' => $course?->id,
                'academic_year_id' => $course?->academic_year_id,
                'biblioteca_obra_id' => $ejemplar->biblioteca_obra_id,
                'biblioteca_ejemplar_id' => $ejemplar->id,
                'borrower_name_snapshot' => $name,
                'course_name_snapshot' => $courseName,
                'borrowed_at' => $borrowedAt,
                'due_at' => $dueAt->format('Y-m-d'),
                'status' => 'vencido',
                'renewed_count' => random_int(0, 1),
                'overdue_days' => $this->now->diffInDays($dueAt),
                'notes' => 'Préstamo vencido para control de mora.',
                'audit_trail' => [['event' => 'prestamo_vencido', 'at' => $borrowedAt->toDateTimeString(), 'by' => $this->actor->id]],
                'delivered_by_user_id' => $this->actor->id,
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]);

            $ejemplar->forceFill([
                'availability_status' => 'prestado',
                'updated_by' => $this->actor->id,
            ])->save();

            BibliotecaInventarioMovimiento::query()->create([
                'biblioteca_ejemplar_id' => $ejemplar->id,
                'movement_type' => 'mora',
                'previous_location' => $ejemplar->physical_location,
                'new_location' => $ejemplar->physical_location,
                'previous_state' => $ejemplar->physical_state,
                'new_state' => $ejemplar->physical_state,
                'movement_date' => $dueAt,
                'notes' => 'Préstamo vencido.',
                'responsible_user_id' => $this->actor->id,
                'metadata' => ['overdue_days' => $this->now->diffInDays($dueAt)],
            ]);
        }
    }

    /**
     * @param  Collection<int, BibliotecaEjemplar>  $exemplars
     */
    private function seedReservations(Collection $exemplars): void
    {
        $students = StudentProfile::query()->limit(20)->get();
        $staff = Staff::query()->limit(10)->get();
        $courses = CourseSection::query()->limit(8)->get();
        $available = $exemplars
            ->filter(fn (BibliotecaEjemplar $ejemplar) => $ejemplar->availability_status === 'disponible')
            ->values()
            ->take(40);

        $statuses = array_merge(
            array_fill(0, 12, 'solicitada'),
            array_fill(0, 10, 'aprobada'),
            array_fill(0, 6, 'rechazada'),
            array_fill(0, 6, 'cancelada'),
            array_fill(0, 6, 'vencida')
        );

        foreach ($available as $index => $ejemplar) {
            $requesterType = ['student', 'staff', 'course'][array_rand(['student', 'staff', 'course'])];
            $student = $requesterType === 'student' ? $students->random() : null;
            $staffMember = $requesterType === 'staff' ? $staff->random() : null;
            $course = $requesterType === 'course' ? $courses->random() : null;
            $requestedAt = $this->now->copy()->subDays(random_int(0, 25));
            $pickupAt = $requestedAt->copy()->addDays(random_int(0, 5));
            $status = $statuses[$index] ?? 'solicitada';

            BibliotecaReserva::query()->create([
                'reservation_code' => sprintf('RES-%04d', $index + 1),
                'resource_type' => $ejemplar->obra->material_type,
                'biblioteca_obra_id' => $ejemplar->biblioteca_obra_id,
                'biblioteca_ejemplar_id' => $ejemplar->id,
                'requester_type' => $requesterType,
                'student_profile_id' => $student?->id,
                'staff_id' => $staffMember?->id,
                'course_section_id' => $course?->id,
                'requested_at' => $requestedAt,
                'pickup_at' => $pickupAt,
                'expected_return_at' => $pickupAt->copy()->addDays(random_int(3, 10)),
                'purpose' => 'Uso CRA para apoyo pedagógico y lectura.',
                'status' => $status,
                'responsible_user_id' => $this->actor->id,
                'approval_notes' => $status === 'rechazada' ? 'Recurso comprometido para otra actividad.' : null,
                'notes' => $status === 'vencida' ? 'Reserva no retirada dentro del plazo.' : null,
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]);

            if ($status === 'aprobada') {
                $ejemplar->forceFill([
                    'availability_status' => 'reservado',
                    'updated_by' => $this->actor->id,
                ])->save();

                BibliotecaInventarioMovimiento::query()->create([
                    'biblioteca_ejemplar_id' => $ejemplar->id,
                    'movement_type' => 'reserva',
                    'previous_location' => $ejemplar->physical_location,
                    'new_location' => $ejemplar->physical_location,
                    'previous_state' => $ejemplar->physical_state,
                    'new_state' => $ejemplar->physical_state,
                    'movement_date' => $requestedAt,
                    'notes' => 'Reserva aprobada.',
                    'responsible_user_id' => $this->actor->id,
                    'metadata' => ['status' => $status],
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, BibliotecaEjemplar>  $exemplars
     */
    private function seedDamagedAndLostExemplars(Collection $exemplars): void
    {
        $available = $exemplars
            ->filter(fn (BibliotecaEjemplar $ejemplar) => $ejemplar->availability_status === 'disponible')
            ->values();

        foreach ($available->take(10) as $ejemplar) {
            $ejemplar->forceFill([
                'physical_state' => 'danado',
                'availability_status' => 'danado',
                'damaged_at' => $this->now->copy()->subDays(random_int(1, 45)),
                'updated_by' => $this->actor->id,
            ])->save();

            BibliotecaInventarioMovimiento::query()->create([
                'biblioteca_ejemplar_id' => $ejemplar->id,
                'movement_type' => 'danio',
                'previous_location' => $ejemplar->physical_location,
                'new_location' => $ejemplar->physical_location,
                'previous_state' => 'bueno',
                'new_state' => 'danado',
                'movement_date' => $ejemplar->damaged_at,
                'notes' => 'Ejemplar dañado en devolución.',
                'responsible_user_id' => $this->actor->id,
                'metadata' => ['source' => 'seeder'],
            ]);
        }

        foreach ($available->slice(10, 10) as $ejemplar) {
            $ejemplar->forceFill([
                'physical_state' => 'perdido',
                'availability_status' => 'perdido',
                'lost_at' => $this->now->copy()->subDays(random_int(1, 60)),
                'is_active' => false,
                'updated_by' => $this->actor->id,
            ])->save();

            BibliotecaInventarioMovimiento::query()->create([
                'biblioteca_ejemplar_id' => $ejemplar->id,
                'movement_type' => 'perdida',
                'previous_location' => $ejemplar->physical_location,
                'new_location' => $ejemplar->physical_location,
                'previous_state' => 'bueno',
                'new_state' => 'perdido',
                'movement_date' => $ejemplar->lost_at,
                'notes' => 'Ejemplar declarado perdido.',
                'responsible_user_id' => $this->actor->id,
                'metadata' => ['source' => 'seeder'],
            ]);
        }
    }

    /**
     * @param  Collection<int, BibliotecaEspacio>  $spaces
     */
    private function seedSpaceUsage(Collection $spaces): void
    {
        $courses = CourseSection::query()->limit(12)->get();
        $staff = Staff::query()->limit(12)->get();
        $types = BibliotecaUsoEspacio::ACTIVITY_TYPES;
        $statuses = ['solicitada', 'aprobada', 'rechazada', 'realizada', 'cancelada'];

        foreach (range(1, 30) as $index) {
            $start = $this->now->copy()->subDays(random_int(0, 150))->setTime(random_int(8, 17), [0, 30][array_rand([0, 30])]);
            if ($index <= 4) {
                $start = $this->now->copy()->setTime(9 + $index, 0);
            }
            $end = $start->copy()->addMinutes(random_int(45, 120));

            BibliotecaUsoEspacio::query()->create([
                'biblioteca_espacio_id' => $spaces->random()->id,
                'activity_type' => $types[array_rand($types)],
                'title' => 'Actividad CRA #' . $index,
                'course_section_id' => $courses->random()?->id,
                'responsible_staff_id' => $staff->random()?->id,
                'requested_by_user_id' => $this->actor->id,
                'attendee_count' => random_int(8, 38),
                'requested_resources' => ['Notebook', 'Proyector'],
                'start_at' => $start,
                'end_at' => $end,
                'status' => $statuses[array_rand($statuses)],
                'observations' => 'Actividad planificada en agenda CRA.',
                'evidence' => $index % 5 === 0 ? ['https://example.com/evidencia-' . $index] : [],
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]);
        }
    }

    private function seedAdditionalInventoryMovements(): void
    {
        $candidates = BibliotecaEjemplar::query()->where('is_active', true)->limit(30)->get();

        foreach ($candidates as $index => $ejemplar) {
            BibliotecaInventarioMovimiento::query()->create([
                'biblioteca_ejemplar_id' => $ejemplar->id,
                'movement_type' => $index % 2 === 0 ? 'cambio_ubicacion' : 'ajuste',
                'previous_location' => $ejemplar->physical_location,
                'new_location' => $ejemplar->physical_location,
                'previous_state' => $ejemplar->physical_state,
                'new_state' => $ejemplar->physical_state,
                'movement_date' => $this->now->copy()->subDays(random_int(1, 120)),
                'notes' => 'Movimiento complementario para trazabilidad de inventario.',
                'responsible_user_id' => $this->actor->id,
                'metadata' => ['seed_extra' => true],
            ]);
        }
    }

    private function ensureAcademicScaffolding(): void
    {
        if (!AcademicYear::query()->exists()) {
            $year = AcademicYear::query()->create([
                'name' => 'Año académico 2026',
                'year' => 2026,
                'starts_at' => '2026-03-01',
                'ends_at' => '2026-12-20',
                'is_active' => true,
                'is_closed' => false,
                'created_by' => 1,
                'updated_by' => 1,
            ]);

            $levels = collect([
                ['name' => '5° básico', 'order' => 5, 'type' => 'basica'],
                ['name' => '6° básico', 'order' => 6, 'type' => 'basica'],
                ['name' => '7° básico', 'order' => 7, 'type' => 'basica'],
                ['name' => '8° básico', 'order' => 8, 'type' => 'basica'],
                ['name' => '1° medio', 'order' => 9, 'type' => 'media'],
                ['name' => '2° medio', 'order' => 10, 'type' => 'media'],
            ])->map(fn (array $payload) => EducationLevel::query()->firstOrCreate(['name' => $payload['name']], $payload));

            foreach ($levels as $level) {
                foreach (['A', 'B'] as $section) {
                    CourseSection::query()->create([
                        'academic_year_id' => $year->id,
                        'education_level_id' => $level->id,
                        'section_name' => $section,
                        'display_name' => trim($level->name . ' ' . $section),
                        'capacity' => 35,
                        'active' => true,
                        'created_by' => 1,
                        'updated_by' => 1,
                    ]);
                }
            }
        }
    }

    private function ensureMinimumStudents(int $target): void
    {
        $current = StudentProfile::query()->count();
        if ($current >= $target) {
            return;
        }

        $activeYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $courses = CourseSection::query()->where('academic_year_id', $activeYear->id)->orderBy('id')->get();
        $creatorId = User::query()->where('email', 'patricia.lopez@cnscgestion.local')->value('id') ?: User::query()->value('id');

        foreach (range($current + 1, $target) as $index) {
            $student = StudentProfile::query()->create([
                'first_name' => $this->faker->firstName(),
                'last_name' => $this->faker->lastName() . ' ' . $this->faker->lastName(),
                'registered_name' => null,
                'rut' => sprintf('%d-%d', 32000000 + $index, (($index % 9) + 1)),
                'birthdate' => $this->now->copy()->subYears(random_int(10, 17))->subDays(random_int(1, 360))->format('Y-m-d'),
                'email' => 'estudiante.biblioteca' . $index . '@cnscgestion.local',
                'phone' => '+5699' . str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT),
                'address' => $this->faker->streetAddress(),
                'general_status' => 'activo',
                'guardian_name' => $this->faker->name(),
                'guardian_relationship' => random_int(0, 1) ? 'Madre' : 'Padre',
                'guardian_phone' => '+5698' . str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT),
                'guardian_email' => 'apoderado.biblioteca' . $index . '@example.com',
                'created_by' => $creatorId,
                'updated_by' => $creatorId,
            ]);

            $course = $courses[($index - 1) % max($courses->count(), 1)];

            StudentEnrollment::query()->create([
                'student_profile_id' => $student->id,
                'academic_year_id' => $activeYear->id,
                'course_section_id' => $course->id,
                'enrollment_status' => 'regular',
                'enrolled_at' => $activeYear->starts_at,
                'snapshot_year_name' => $activeYear->name,
                'snapshot_level_name' => $course->educationLevel?->name ?? $course->display_name,
                'snapshot_section_name' => $course->section_name,
                'snapshot_course_display_name' => $course->display_name,
                'created_by' => $creatorId,
                'updated_by' => $creatorId,
            ]);
        }
    }

    private function ensureLibraryUser(): void
    {
        $role = Role::query()->firstWhere('slug', 'administrador');
        $cargoId = Cargo::query()->firstWhere('slug', 'administrativo')?->id;

        $user = User::query()->updateOrCreate(
            ['email' => 'biblioteca.cra@cnscgestion.local'],
            [
                'name' => 'Biblioteca CRA',
                'password' => Hash::make('Biblioteca123!'),
                'cargo_id' => $cargoId,
                'user_type' => 'staff',
                'active' => true,
            ]
        );

        if ($role) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    }

    private function titleForMaterialType(string $materialType, int $index): string
    {
        return match ($materialType) {
            'libro' => 'Libro CRA ' . $index . ': ' . Str::title($this->faker->words(3, true)),
            'diccionario' => 'Diccionario escolar ' . $index,
            'enciclopedia' => 'Enciclopedia temática ' . $index,
            'tablet' => 'Tablet educativa ' . $index,
            'notebook' => 'Notebook CRA ' . $index,
            'proyector' => 'Proyector multimedia ' . $index,
            'parlante' => 'Parlante portátil ' . $index,
            'juego_educativo' => 'Juego educativo ' . $index,
            'material_didactico' => 'Material didáctico ' . $index,
            'kit_pedagogico' => 'Kit pedagógico ' . $index,
            'audiovisual' => 'Recurso audiovisual ' . $index,
            default => 'Recurso biblioteca ' . $index,
        };
    }

    /**
     * @param  Collection<int, StudentProfile>  $students
     * @param  Collection<int, Staff>  $staff
     * @param  Collection<int, CourseSection>  $courses
     * @return array{0:string,1:?StudentProfile,2:?Staff,3:?CourseSection,4:string,5:?string}
     */
    private function borrowerSnapshot(Collection $students, Collection $staff, Collection $courses, int $index): array
    {
        $mode = $index % 3;

        if ($mode === 0) {
            $student = $students[$index % $students->count()];
            $enrollment = $student->preferredEnrollment();

            return [
                'student',
                $student,
                null,
                $enrollment?->courseSection,
                $student->registered_name_resolved,
                $enrollment?->snapshot_course_display_name,
            ];
        }

        if ($mode === 1) {
            $staffMember = $staff[$index % $staff->count()];

            return ['staff', null, $staffMember, null, $staffMember->full_name, null];
        }

        $course = $courses[$index % $courses->count()];

        return ['course', null, null, $course, $course->display_name, $course->display_name];
    }
}
