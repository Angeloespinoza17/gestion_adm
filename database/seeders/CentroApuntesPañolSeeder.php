<?php

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\CentroApuntes\CentroApuntesAsignatura;
use App\Models\CentroApuntes\CentroApuntesMaquina;
use App\Models\CentroApuntes\CentroApuntesSolicitud;
use App\Models\CentroApuntes\PanolEntrega;
use App\Models\CentroApuntes\PanolInsumo;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Supplier;
use App\Models\SystemModule;
use App\Models\User;
use App\Services\CentroApuntes\CentroApuntesDashboardService;
use App\Services\CentroApuntes\CentroApuntesSolicitudService;
use App\Services\CentroApuntes\PanolDeliveryService;
use App\Services\CentroApuntes\PanolStockService;
use Carbon\Carbon;
use Database\Seeders\Modules\StaffModuleSeeder;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class CentroApuntesPañolSeeder extends Seeder
{
    private \Faker\Generator $faker;

    private User $actor;

    private Collection $staffUsers;

    public function run(): void
    {
        $this->faker = Faker::create('es_CL');
        $this->faker->seed(20260629);

        $this->call([
            RbacSeeder::class,
            SchoolDepartmentSeeder::class,
            StaffModuleSeeder::class,
        ]);

        $this->seedPermissionsAndModules();
        $this->ensureSuppliers();
        $this->ensureMinimumStaffUsers();

        $this->actor = User::query()
            ->where('email', 'superadmin@cnscgestion.cl')
            ->orWhere('user_type', 'staff')
            ->orderByDesc('id')
            ->firstOrFail();

        $this->staffUsers = User::query()
            ->where('user_type', 'staff')
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $this->resetModuleData();

        $subjects = $this->seedSubjects();
        $machines = $this->seedMachines();
        $supplies = $this->seedSupplies();

        $this->seedSupplyMovements($supplies);
        $this->seedRequests($subjects, $machines);
        $this->seedDeliveries($supplies);

        app(CentroApuntesDashboardService::class)->build();
    }

    private function seedPermissionsAndModules(): void
    {
        $permissions = [
            ['slug' => 'ver_modulo_centro_apuntes', 'name' => 'Ver módulo Centro de Apuntes y Pañol'],
            ['slug' => 'crear_solicitud_impresion', 'name' => 'Crear solicitud de impresión'],
            ['slug' => 'editar_solicitud_impresion', 'name' => 'Editar solicitud de impresión'],
            ['slug' => 'eliminar_solicitud_impresion', 'name' => 'Eliminar solicitud de impresión'],
            ['slug' => 'cambiar_estado_solicitud_impresion', 'name' => 'Cambiar estado de solicitud de impresión'],
            ['slug' => 'registrar_entrega_centro_apuntes', 'name' => 'Registrar entrega en Centro de Apuntes'],
            ['slug' => 'administrar_asignaturas_centro_apuntes', 'name' => 'Administrar asignaturas del Centro de Apuntes'],
            ['slug' => 'administrar_maquinas_centro_apuntes', 'name' => 'Administrar máquinas del Centro de Apuntes'],
            ['slug' => 'administrar_inventario_panol', 'name' => 'Administrar inventario del pañol'],
            ['slug' => 'registrar_movimientos_panol', 'name' => 'Registrar movimientos de stock del pañol'],
            ['slug' => 'aprobar_entregas_panol', 'name' => 'Aprobar entregas de materiales del pañol'],
            ['slug' => 'ver_reportes_centro_apuntes', 'name' => 'Ver reportes del Centro de Apuntes'],
            ['slug' => 'exportar_reportes_centro_apuntes', 'name' => 'Exportar reportes del Centro de Apuntes'],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'description' => 'Permiso correspondiente al módulo Centro de Apuntes y Pañol de Librería.',
                    'active' => true,
                ]
            );
        }

        $parent = SystemModule::query()->updateOrCreate(
            ['slug' => 'centro_apuntes'],
            [
                'name' => 'Centro de Apuntes',
                'frontend_route' => null,
                'icon' => 'bx-printer',
                'sort_order' => 86,
                'active' => true,
                'parent_id' => null,
            ]
        );

        $children = [
            ['slug' => 'centro_apuntes_dashboard', 'name' => 'Dashboard', 'route' => '/centro-apuntes', 'sort' => 1],
            ['slug' => 'centro_apuntes_solicitudes', 'name' => 'Solicitudes y tareas', 'route' => '/centro-apuntes/solicitudes', 'sort' => 2],
            ['slug' => 'centro_apuntes_asignaturas', 'name' => 'Asignaturas', 'route' => '/centro-apuntes/asignaturas', 'sort' => 3],
            ['slug' => 'centro_apuntes_maquinas', 'name' => 'Máquinas', 'route' => '/centro-apuntes/maquinas', 'sort' => 4],
            ['slug' => 'centro_apuntes_insumos', 'name' => 'Pañol e insumos', 'route' => '/centro-apuntes/insumos', 'sort' => 5],
            ['slug' => 'centro_apuntes_movimientos', 'name' => 'Movimientos de stock', 'route' => '/centro-apuntes/movimientos', 'sort' => 6],
            ['slug' => 'centro_apuntes_entregas', 'name' => 'Entregas de materiales', 'route' => '/centro-apuntes/entregas', 'sort' => 7],
            ['slug' => 'centro_apuntes_reportes', 'name' => 'Reportes', 'route' => '/centro-apuntes/reportes', 'sort' => 8],
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
        $modules = SystemModule::query()->whereIn('slug', array_merge(['centro_apuntes'], array_column($children, 'slug')))->get()->keyBy('slug');

        $rolePermissionMap = [
            'super_admin' => array_keys($permissionsBySlug->all()),
            'administrador' => array_keys($permissionsBySlug->all()),
            'rrhh' => [
                'ver_modulo_centro_apuntes',
                'crear_solicitud_impresion',
                'editar_solicitud_impresion',
                'cambiar_estado_solicitud_impresion',
                'registrar_entrega_centro_apuntes',
                'aprobar_entregas_panol',
                'ver_reportes_centro_apuntes',
                'exportar_reportes_centro_apuntes',
            ],
            'direccion' => [
                'ver_modulo_centro_apuntes',
                'aprobar_entregas_panol',
                'ver_reportes_centro_apuntes',
                'exportar_reportes_centro_apuntes',
            ],
            'coordinador_academico' => [
                'ver_modulo_centro_apuntes',
                'crear_solicitud_impresion',
                'editar_solicitud_impresion',
                'cambiar_estado_solicitud_impresion',
                'administrar_asignaturas_centro_apuntes',
                'ver_reportes_centro_apuntes',
                'exportar_reportes_centro_apuntes',
            ],
            'administrativo' => [],
            'inspectoria' => [
                'ver_modulo_centro_apuntes',
                'crear_solicitud_impresion',
                'editar_solicitud_impresion',
                'cambiar_estado_solicitud_impresion',
                'registrar_entrega_centro_apuntes',
                'administrar_maquinas_centro_apuntes',
                'administrar_inventario_panol',
                'registrar_movimientos_panol',
                'aprobar_entregas_panol',
                'ver_reportes_centro_apuntes',
            ],
            'docente' => [
                'ver_modulo_centro_apuntes',
                'crear_solicitud_impresion',
            ],
        ];

        $roleModuleMap = [
            'super_admin' => $modules->keys()->all(),
            'administrador' => $modules->keys()->all(),
            'rrhh' => ['centro_apuntes', 'centro_apuntes_dashboard', 'centro_apuntes_solicitudes', 'centro_apuntes_entregas', 'centro_apuntes_reportes'],
            'direccion' => ['centro_apuntes', 'centro_apuntes_dashboard', 'centro_apuntes_entregas', 'centro_apuntes_reportes'],
            'coordinador_academico' => ['centro_apuntes', 'centro_apuntes_dashboard', 'centro_apuntes_solicitudes', 'centro_apuntes_asignaturas', 'centro_apuntes_reportes'],
            'inspectoria' => ['centro_apuntes', 'centro_apuntes_dashboard', 'centro_apuntes_solicitudes', 'centro_apuntes_maquinas', 'centro_apuntes_insumos', 'centro_apuntes_movimientos', 'centro_apuntes_entregas', 'centro_apuntes_reportes'],
            'docente' => ['centro_apuntes', 'centro_apuntes_dashboard', 'centro_apuntes_solicitudes', 'centro_apuntes_entregas'],
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

    private function ensureSuppliers(): void
    {
        $suppliers = [
            ['rut' => '76990001-1', 'name' => 'Papelera Austral', 'business_name' => 'Papelera Austral SpA', 'email' => 'ventas@papeleraaustral.cl', 'phone' => '+56632200001', 'address' => 'Picarte 1400, Valdivia'],
            ['rut' => '76990002-2', 'name' => 'Color Print Sur', 'business_name' => 'Color Print Sur Ltda.', 'email' => 'contacto@colorprintsur.cl', 'phone' => '+56632200002', 'address' => 'Ramón Picarte 990, Valdivia'],
            ['rut' => '76990003-3', 'name' => 'Insumos Educativos del Sur', 'business_name' => 'Insumos Educativos del Sur SpA', 'email' => 'pedidos@insumosedu.cl', 'phone' => '+56226550003', 'address' => 'Av. Grecia 1234, Santiago'],
            ['rut' => '76990004-4', 'name' => 'Office Red Chile', 'business_name' => 'Office Red Chile SpA', 'email' => 'catalogo@officered.cl', 'phone' => '+56226550004', 'address' => 'San Diego 455, Santiago'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::query()->updateOrCreate(
                ['rut' => $supplier['rut']],
                array_merge($supplier, ['active' => true]),
            );
        }
    }

    private function ensureMinimumStaffUsers(): void
    {
        $count = User::query()->where('user_type', 'staff')->where('active', true)->count();

        if ($count >= 12) {
            return;
        }

        $fallback = [
            ['name' => 'Carolina Duarte', 'email' => 'carolina.duarte@cnscgestion.local', 'cargo' => 'administrativo', 'role' => 'administrador'],
            ['name' => 'Mario Sepúlveda', 'email' => 'mario.sepulveda@cnscgestion.local', 'cargo' => 'administrativo', 'role' => 'rrhh'],
            ['name' => 'Paula Sandoval', 'email' => 'paula.sandoval@cnscgestion.local', 'cargo' => 'coordinador_academico', 'role' => 'coordinador_academico'],
            ['name' => 'Rocío Álvarez', 'email' => 'rocio.alvarez@cnscgestion.local', 'cargo' => 'docente', 'role' => 'docente'],
            ['name' => 'Elisa Figueroa', 'email' => 'elisa.figueroa@cnscgestion.local', 'cargo' => 'docente', 'role' => 'docente'],
            ['name' => 'Víctor Melo', 'email' => 'victor.melo@cnscgestion.local', 'cargo' => 'inspectoria', 'role' => 'inspectoria'],
            ['name' => 'Nadia Cárdenas', 'email' => 'nadia.cardenas@cnscgestion.local', 'cargo' => 'administrativo', 'role' => 'administrador'],
            ['name' => 'Héctor Lagos', 'email' => 'hector.lagos@cnscgestion.local', 'cargo' => 'docente', 'role' => 'docente'],
        ];

        foreach ($fallback as $item) {
            $cargo = Cargo::query()->firstWhere('slug', $item['cargo']);
            $staff = Staff::query()->updateOrCreate(
                ['institutional_email' => $item['email']],
                [
                    'full_name' => $item['name'],
                    'rut' => $this->faker->unique()->numerify('1#######-#'),
                    'cargo_id' => $cargo?->id,
                    'status' => 'activo',
                    'active' => true,
                    'created_by' => 1,
                    'updated_by' => 1,
                ]
            );

            $user = User::query()->updateOrCreate(
                ['email' => $item['email']],
                [
                    'name' => $item['name'],
                    'password' => Hash::make('password'),
                    'active' => true,
                    'user_type' => 'staff',
                    'cargo_id' => $cargo?->id,
                    'staff_id' => $staff->id,
                ]
            );

            $role = Role::query()->firstWhere('slug', $item['role']);
            if ($role) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }
        }
    }

    private function resetModuleData(): void
    {
        Storage::disk('public')->deleteDirectory('centro-apuntes');

        \App\Models\CentroApuntes\CentroApuntesAlerta::query()->delete();
        \App\Models\CentroApuntes\PanolEntregaDetalle::query()->delete();
        PanolEntrega::query()->delete();
        \App\Models\CentroApuntes\PanolMovimiento::query()->delete();
        PanolInsumo::query()->delete();
        \App\Models\CentroApuntes\CentroApuntesHistorialEstado::query()->delete();
        \App\Models\CentroApuntes\CentroApuntesAdjunto::query()->delete();
        CentroApuntesSolicitud::query()->delete();
        CentroApuntesMaquina::query()->delete();
        CentroApuntesAsignatura::query()->delete();
    }

    /**
     * @return Collection<int, CentroApuntesAsignatura>
     */
    private function seedSubjects(): Collection
    {
        $items = [
            ['Lenguaje', 'LENG-01', 'Lenguaje', '1° a 4° Básico'],
            ['Matemática', 'MATE-01', 'Matemática', '1° a 4° Básico'],
            ['Historia', 'HIST-01', 'Historia y Ciencias Sociales', '5° a 8° Básico'],
            ['Ciencias', 'CIEN-01', 'Ciencias Naturales', '1° a 4° Básico'],
            ['Inglés', 'ING-01', 'Inglés', '5° a 8° Básico'],
            ['Religión', 'REL-01', 'Religión', 'Transversal'],
            ['Artes', 'ART-01', 'Artes', '5° a 8° Básico'],
            ['Música', 'MUS-01', 'Música', '1° a 4° Básico'],
            ['Educación Física', 'EDF-01', 'Educación Física', 'Transversal'],
            ['Tecnología', 'TEC-01', 'Tecnología', '5° a 8° Básico'],
            ['Orientación', 'ORI-01', 'Orientación', 'Transversal'],
            ['Filosofía', 'FIL-01', 'Filosofía', '1° a 4° Medio'],
            ['Biología', 'BIO-01', 'Biología', '1° a 4° Medio'],
            ['Física', 'FIS-01', 'Física', '1° a 4° Medio'],
            ['Química', 'QUI-01', 'Química', '1° a 4° Medio'],
            ['Comprensión Lectora', 'CL-01', 'Lenguaje', '5° a 8° Básico'],
            ['Taller PIE', 'PIE-01', 'PIE', 'Transversal'],
            ['Educación Ciudadana', 'ECI-01', 'Historia y Ciencias Sociales', '1° a 4° Medio'],
            ['Artes Visuales', 'ARV-01', 'Artes', '1° a 4° Medio'],
            ['Preparación PAES', 'PAES-01', 'Matemática', '1° a 4° Medio'],
        ];

        return collect($items)->map(function (array $item, int $index) {
            return CentroApuntesAsignatura::query()->create([
                'name' => $item[0],
                'code' => $item[1],
                'area' => $item[2],
                'education_level' => $item[3],
                'status' => $index === 18 ? 'inactiva' : 'activa',
                'observations' => $index % 4 === 0 ? 'Asignatura de alta demanda para material impreso.' : null,
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]);
        });
    }

    /**
     * @return Collection<int, CentroApuntesMaquina>
     */
    private function seedMachines(): Collection
    {
        $staff = $this->staffUsers->values();
        $items = [
            ['Impresora Dirección', 'MAQ-001', 'impresora', 'HP', 'LaserJet Pro', 'Oficina Dirección', 'activa', 6.5, 8.2],
            ['Fotocopiadora UTP', 'MAQ-002', 'fotocopiadora', 'Ricoh', 'MP 402', 'Unidad Técnico Pedagógica', 'activa', 5.8, 7.5],
            ['Riso Sala Docentes', 'MAQ-003', 'riso', 'Riso', 'EZ220', 'Sala de Profesores', 'activa', 3.2, 4.4],
            ['Multifuncional Biblioteca', 'MAQ-004', 'multifuncional', 'Brother', 'DCP-L5650', 'Biblioteca CRA', 'en_mantencion', 6.2, 8.1],
            ['Guillotina Taller', 'MAQ-005', 'guillotina', 'Dasa', 'G500', 'Centro de Apuntes', 'activa', 1.4, 1.8],
            ['Anilladora Técnica', 'MAQ-006', 'anilladora', 'Tamerica', 'OfficePro', 'Centro de Apuntes', 'activa', 2.1, 2.9],
            ['Plastificadora Secretaría', 'MAQ-007', 'plastificadora', 'GBC', 'Fusion 3000', 'Secretaría', 'en_mantencion', 1.8, 2.3],
            ['Escáner Inspectoría', 'MAQ-008', 'escaner', 'Epson', 'DS-530', 'Inspectoría General', 'danada', 0.8, 1.1],
        ];

        return collect($items)->map(function (array $item, int $index) use ($staff) {
            return CentroApuntesMaquina::query()->create([
                'name' => $item[0],
                'internal_code' => $item[1],
                'type' => $item[2],
                'brand' => $item[3],
                'model' => $item[4],
                'location' => $item[5],
                'responsible_user_id' => $staff[$index % $staff->count()]->id,
                'status' => $item[6],
                'estimated_cost_letter' => $item[7],
                'estimated_cost_officio' => $item[8],
                'observations' => in_array($item[6], ['en_mantencion', 'danada'], true)
                    ? 'Requiere seguimiento técnico y coordinación con proveedor.'
                    : 'Máquina operativa para solicitudes diarias.',
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]);
        });
    }

    /**
     * @return Collection<int, PanolInsumo>
     */
    private function seedSupplies(): Collection
    {
        $suppliers = Supplier::query()->where('active', true)->get()->values();
        $categories = PanolInsumo::CATEGORY_OPTIONS;
        $units = PanolInsumo::UNIT_OPTIONS;

        return collect(range(1, 80))->map(function (int $index) use ($suppliers, $categories, $units) {
            $category = $categories[$index % count($categories)];
            $unit = $units[$index % count($units)];

            return PanolInsumo::query()->create([
                'name' => sprintf('%s %02d', str($category)->replace('_', ' ')->title(), $index),
                'category' => $category,
                'unit_of_measure' => $unit,
                'current_stock' => 0,
                'minimum_stock' => $this->faker->numberBetween(2, 18),
                'maximum_stock' => $this->faker->numberBetween(20, 120),
                'location' => $this->faker->randomElement(['Bodega principal', 'Estantería A', 'Estantería B', 'Mueble insumos', 'Centro de Apuntes']),
                'supplier_id' => $suppliers->isNotEmpty() ? $suppliers[$index % $suppliers->count()]->id : null,
                'unit_price_estimated' => $this->faker->randomFloat(2, 850, 16500),
                'last_purchase_at' => Carbon::today()->subDays($this->faker->numberBetween(1, 220)),
                'expires_at' => $index % 11 === 0
                    ? Carbon::today()->subDays($this->faker->numberBetween(1, 40))
                    : ($index % 7 === 0 ? Carbon::today()->addDays($this->faker->numberBetween(5, 25)) : null),
                'status' => 'disponible',
                'observations' => $index % 9 === 0 ? 'Uso frecuente en evaluaciones y material de apoyo.' : null,
                'active' => true,
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]);
        });
    }

    private function seedSupplyMovements(Collection $supplies): void
    {
        $stockService = app(PanolStockService::class);
        $departments = Department::query()->where('active', true)->get()->values();

        $supplies->each(function (PanolInsumo $supply, int $index) use ($stockService, $departments) {
            $initialQuantity = $this->faker->numberBetween(8, 90);
            $responsible = $this->staffUsers[$index % $this->staffUsers->count()];

            $stockService->registerMovement($supply, [
                'movement_type' => 'ingreso',
                'quantity' => $initialQuantity,
                'moved_at' => Carbon::today()->subDays($this->faker->numberBetween(15, 180)),
                'responsible_user_id' => $responsible->id,
                'reason' => 'Ingreso inicial de bodega',
                'document_reference' => 'FAC-' . $this->faker->numberBetween(1000, 9999),
                'observations' => 'Reposición de stock para operación del módulo.',
            ], $this->actor);

            if ($index < 12) {
                $outQuantity = max(0, (float) $supply->current_stock - (float) $supply->minimum_stock + ($index < 6 ? 1 : 0));
                if ($outQuantity > 0) {
                    $stockService->registerMovement($supply, [
                        'movement_type' => 'salida',
                        'quantity' => $outQuantity,
                        'moved_at' => Carbon::today()->subDays($this->faker->numberBetween(1, 20)),
                        'responsible_user_id' => $responsible->id,
                        'requested_by_user_id' => $this->staffUsers->random()->id,
                        'department_id' => $departments->random()->id,
                        'reason' => 'Consumo intensivo de la semana',
                        'observations' => 'Ajuste para dejar stock crítico o agotado.',
                    ], $this->actor);
                }
            } elseif ($index % 4 === 0) {
                $stockService->registerMovement($supply, [
                    'movement_type' => $this->faker->randomElement(['salida', 'ajuste', 'devolucion']),
                    'quantity' => $this->faker->numberBetween(1, min(12, max(2, (int) floor((float) $supply->current_stock)))),
                    'adjustment_mode' => 'restar',
                    'moved_at' => Carbon::today()->subDays($this->faker->numberBetween(1, 30)),
                    'responsible_user_id' => $responsible->id,
                    'requested_by_user_id' => $this->staffUsers->random()->id,
                    'department_id' => $departments->random()->id,
                    'reason' => 'Movimiento operativo',
                    'observations' => 'Ajuste generado por consumo o devolución.',
                ], $this->actor);
            }

            if ($index % 13 === 0) {
                $supply->forceFill([
                    'active' => false,
                ]);
                $stockService->refreshSupplyStatus($supply, 'dado_de_baja');
                $supply->save();
            }
        });
    }

    private function seedRequests(Collection $subjects, Collection $machines): void
    {
        $service = app(CentroApuntesSolicitudService::class);
        $statuses = array_merge(
            array_fill(0, 18, 'pendiente'),
            array_fill(0, 12, 'recibida'),
            array_fill(0, 20, 'en_proceso'),
            array_fill(0, 8, 'pausada'),
            array_fill(0, 15, 'lista_para_retiro'),
            array_fill(0, 17, 'entregada'),
            array_fill(0, 5, 'rechazada'),
            array_fill(0, 5, 'anulada'),
        );

        foreach ($statuses as $index => $targetStatus) {
            $requester = $this->staffUsers->random();
            $requestedAt = Carbon::today()->subDays($this->faker->numberBetween(0, 170))->setTime($this->faker->numberBetween(8, 17), $this->faker->randomElement([0, 10, 20, 30, 40, 50]));
            $priority = $this->faker->randomElement(['normal', 'normal', 'normal', 'urgente', 'entrega_inmediata']);
            $taskType = $this->faker->randomElement(['guia', 'evaluacion', 'pauta_de_evaluacion', 'actividad_en_clases', 'idps', 'otro']);
            $machine = $machines->whereNotIn('status', ['inactiva', 'danada'])->random();

            $payload = [
                'requested_by_user_id' => $requester->id,
                'subject_id' => $subjects->where('status', 'activa')->random()->id,
                'machine_id' => $machine->id,
                'task_type' => $taskType,
                'task_type_other' => $taskType === 'otro' ? $this->faker->randomElement(['Set de apoyo PIE', 'Material de laboratorio', 'Guía de reforzamiento', 'Cartillas para apoderados']) : null,
                'requested_at' => $requestedAt,
                'delivery_date' => $requestedAt->copy()->addDays($this->faker->numberBetween(1, 8))->toDateString(),
                'sheet_count' => $this->faker->numberBetween(1, 28),
                'copies_count' => $this->faker->numberBetween(8, 120),
                'paper_size' => $this->faker->randomElement(['carta', 'oficio']),
                'priority' => $priority,
                'instructions' => $this->faker->sentence(10),
                'observations' => $index % 6 === 0 ? 'Solicitante requiere revisión previa antes de imprimir.' : null,
                'internal_observations' => $index % 5 === 0 ? 'Preparar con prioridad al inicio de la jornada.' : null,
            ];

            $request = $service->create($payload, $this->actor);

            if ($targetStatus === 'recibida') {
                $service->changeStatus($request, $this->actor, 'recibida', 'Solicitud recepcionada por el centro de apuntes.');
            }

            if ($targetStatus === 'en_proceso') {
                $service->changeStatus($request, $this->actor, 'recibida', 'Solicitud recepcionada.');
                $service->changeStatus($request, $this->actor, 'en_proceso', 'Trabajo asignado a operador.');
            }

            if ($targetStatus === 'pausada') {
                $service->changeStatus($request, $this->actor, 'recibida', 'Solicitud recepcionada.');
                $service->changeStatus($request, $this->actor, 'en_proceso', 'Trabajo iniciado.');
                $service->changeStatus($request, $this->actor, 'pausada', 'Pausada por falta de insumo o validación.');
            }

            if ($targetStatus === 'lista_para_retiro') {
                $service->changeStatus($request, $this->actor, 'recibida', 'Solicitud recepcionada.');
                $service->changeStatus($request, $this->actor, 'en_proceso', 'Trabajo iniciado.');
                $service->changeStatus($request, $this->actor, 'lista_para_retiro', 'Material listo para entregar.');
            }

            if ($targetStatus === 'entregada') {
                $service->changeStatus($request, $this->actor, 'recibida', 'Solicitud recepcionada.');
                $service->changeStatus($request, $this->actor, 'en_proceso', 'Trabajo iniciado.');
                $service->changeStatus($request, $this->actor, 'lista_para_retiro', 'Trabajo terminado.');
                $service->registerDelivery($request->fresh(), $this->actor, $requester, 'Entrega conforme al funcionario solicitante.');
                $deliveryTimestamp = $index < 4
                    ? Carbon::now()
                    : $requestedAt->copy()->addDays($this->faker->numberBetween(1, 12))->setTime($this->faker->numberBetween(9, 18), $this->faker->randomElement([0, 15, 30, 45]));
                $request->fresh()->forceFill([
                    'delivered_at' => $deliveryTimestamp,
                    'status_changed_at' => $deliveryTimestamp,
                ])->save();
            }

            if ($targetStatus === 'rechazada') {
                $service->changeStatus($request, $this->actor, 'rechazada', 'Solicitud rechazada por archivo ilegible o información incompleta.');
            }

            if ($targetStatus === 'anulada') {
                $service->changeStatus($request, $this->actor, 'anulada', 'Solicitud anulada por el solicitante.');
            }

            if ($targetStatus === 'pendiente' && $index < 6) {
                $request->forceFill([
                    'delivery_date' => Carbon::today()->subDays($this->faker->numberBetween(1, 4))->toDateString(),
                ])->save();
            }
        }
    }

    private function seedDeliveries(Collection $supplies): void
    {
        $service = app(PanolDeliveryService::class);
        $departments = Department::query()->where('active', true)->get()->values();
        $supplyIds = $supplies->pluck('id');

        foreach (range(1, 24) as $index) {
            $requester = $this->staffUsers->random();
            $withdrawnBy = $this->staffUsers->random();
            $department = $departments->random();
            $eligibleSupplies = PanolInsumo::query()
                ->whereIn('id', $supplyIds)
                ->where('active', true)
                ->where('current_stock', '>', 2)
                ->inRandomOrder()
                ->limit($this->faker->numberBetween(1, 4))
                ->get();

            if ($eligibleSupplies->isEmpty()) {
                continue;
            }

            $payload = [
                'requested_by_user_id' => $requester->id,
                'withdrawn_by_user_id' => $withdrawnBy->id,
                'department_id' => $department->id,
                'requested_at' => Carbon::today()->subDays($this->faker->numberBetween(0, 90))->setTime($this->faker->numberBetween(8, 17), $this->faker->randomElement([0, 20, 40])),
                'observations' => $index % 3 === 0 ? 'Entrega solicitada para actividad académica y material de apoyo.' : null,
                'details' => $eligibleSupplies->map(function (PanolInsumo $item) {
                    $maxQty = max(1, min(6, (int) floor((float) $item->current_stock)));

                    return [
                        'insumo_id' => $item->id,
                        'quantity' => $this->faker->numberBetween(1, $maxQty),
                        'notes' => $this->faker->boolean(25) ? 'Entregar en oficina principal.' : null,
                    ];
                })->values()->all(),
            ];

            $delivery = $service->create($payload, $this->actor);

            if ($index <= 6) {
                continue;
            }

            if ($index <= 11) {
                $service->approve($delivery, $this->actor, 'Solicitud aprobada y reservada para retiro.');
                continue;
            }

            if ($index <= 20) {
                $delivery = $service->approve($delivery, $this->actor, 'Solicitud aprobada.');
                $delivery = $service->deliver($delivery, $this->actor, $withdrawnBy, 'Entrega registrada en mostrador.');
                $deliveredAt = $index <= 14
                    ? Carbon::now()
                    : Carbon::today()->subDays($this->faker->numberBetween(1, 45))->setTime($this->faker->numberBetween(9, 18), $this->faker->randomElement([0, 15, 30, 45]));
                $delivery->forceFill([
                    'requested_at' => Carbon::parse($payload['requested_at']),
                    'approved_at' => $deliveredAt->copy()->subHours(1),
                    'delivered_at' => $deliveredAt,
                ])->save();
                continue;
            }

            if ($index <= 22) {
                $service->reject($delivery, $this->actor, 'Solicitud rechazada por no justificar consumo.');
                continue;
            }

            $service->annul($delivery, $this->actor, 'Solicitud anulada por cambio de actividad.');
        }
    }
}
