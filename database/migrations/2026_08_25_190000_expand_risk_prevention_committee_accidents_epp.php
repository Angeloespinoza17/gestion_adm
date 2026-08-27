<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'ver_comite_paritario' => [
            'Ver Comité Paritario',
            'Permite consultar integrantes, actas y capacitaciones asociadas al Comité Paritario.',
        ],
        'cargar_actas_comite_paritario' => [
            'Cargar actas del Comité Paritario',
            'Permite cargar actas de constitución y actas mensuales del Comité Paritario.',
        ],
        'ver_entregas_epp' => [
            'Ver entregas de EPP en Bodega',
            'Permite consultar el stock vinculado a Bodega y el historial diario de entregas de EPP.',
        ],
        'registrar_entregas_epp' => [
            'Registrar entregas de EPP en Bodega',
            'Permite registrar entregas diarias de EPP y descontarlas del stock de Bodega.',
        ],
    ];

    public function up(): void
    {
        $this->expandOperationalTables();
        $this->registerAccessConfiguration();
    }

    private function expandOperationalTables(): void
    {
        if (Schema::hasTable('prevent_joint_committees') && ! Schema::hasTable('prevent_joint_committee_documents')) {
            Schema::create('prevent_joint_committee_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('committee_id')->constrained('prevent_joint_committees')->restrictOnDelete();
                $table->string('document_type', 40);
                $table->string('period_key', 20);
                $table->date('document_date');
                $table->string('title', 180);
                $table->string('file_path');
                $table->string('original_name');
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['committee_id', 'document_type', 'period_key'], 'prevent_joint_committee_doc_period_unique');
                $table->index(['committee_id', 'document_date'], 'prevent_joint_committee_doc_date_idx');
            });
        }

        if (Schema::hasTable('prevent_trainings') && ! Schema::hasColumn('prevent_trainings', 'joint_committee_id')) {
            Schema::table('prevent_trainings', function (Blueprint $table) {
                $table->foreignId('joint_committee_id')
                    ->nullable()
                    ->after('requirement_type_id')
                    ->constrained('prevent_joint_committees')
                    ->nullOnDelete();
                $table->index(['joint_committee_id', 'training_date'], 'prevent_training_committee_date_idx');
            });
        }

        if (Schema::hasTable('prevent_accidents')) {
            Schema::table('prevent_accidents', function (Blueprint $table) {
                if (! Schema::hasColumn('prevent_accidents', 'staff_id')) {
                    $table->foreignId('staff_id')->nullable()->after('accident_type')->constrained('staff')->nullOnDelete();
                }
                if (! Schema::hasColumn('prevent_accidents', 'event_type')) {
                    $table->string('event_type', 40)->default('accidente')->after('accident_type');
                }
                if (! Schema::hasColumn('prevent_accidents', 'lost_days')) {
                    $table->unsignedInteger('lost_days')->default(0)->after('injuries');
                }
                if (! Schema::hasColumn('prevent_accidents', 'injured_body_part')) {
                    $table->string('injured_body_part', 120)->nullable()->after('injuries');
                }
            });

            if (! Schema::hasIndex('prevent_accidents', 'prevent_accident_staff_year_idx')) {
                Schema::table('prevent_accidents', function (Blueprint $table) {
                    $table->index(['staff_id', 'occurred_at'], 'prevent_accident_staff_year_idx');
                });
            }
            if (! Schema::hasIndex('prevent_accidents', 'prevent_accident_event_year_idx')) {
                Schema::table('prevent_accidents', function (Blueprint $table) {
                    $table->index(['event_type', 'occurred_at'], 'prevent_accident_event_year_idx');
                });
            }
        }

        if (Schema::hasTable('prevent_epp_items') && ! Schema::hasColumn('prevent_epp_items', 'inventory_item_id')) {
            Schema::table('prevent_epp_items', function (Blueprint $table) {
                $table->foreignId('inventory_item_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('inventory_items')
                    ->nullOnDelete();
                $table->unique('inventory_item_id', 'prevent_epp_inventory_item_unique');
            });
        }
    }

    private function registerAccessConfiguration(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();

            foreach (self::PERMISSIONS as $slug => [$name, $description]) {
                DB::table('permissions')->insertOrIgnore([
                    'slug' => $slug,
                    'name' => $name,
                    'description' => $description,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $riskParentId = DB::table('system_modules')->where('slug', 'risk_prevention')->value('id');
            $inventoryParentId = DB::table('system_modules')->where('slug', 'inventory')->value('id');

            if ($riskParentId) {
                DB::table('system_modules')->insertOrIgnore([
                    'slug' => 'risk_prevention_joint_committee',
                    'name' => 'Comité Paritario',
                    'frontend_route' => '/risk-prevention/joint-committee',
                    'icon' => null,
                    'sort_order' => 55,
                    'active' => true,
                    'parent_id' => $riskParentId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($inventoryParentId) {
                DB::table('system_modules')->insertOrIgnore([
                    'slug' => 'inventory_epp_deliveries',
                    'name' => 'Entrega diaria de EPP',
                    'frontend_route' => '/inventory/epp-deliveries',
                    'icon' => null,
                    'sort_order' => 3,
                    'active' => true,
                    'parent_id' => $inventoryParentId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $permissionIds = DB::table('permissions')
                ->whereIn('slug', array_keys(self::PERMISSIONS))
                ->pluck('id', 'slug');

            $this->registerPermissionGroup(
                'comite_paritario',
                'risk_prevention_joint_committee',
                'Comité Paritario',
                'Consulta y carga controlada de actas mensuales y de constitución.',
                ['ver_comite_paritario', 'cargar_actas_comite_paritario'],
                $permissionIds,
                $now,
            );
            $this->registerPermissionGroup(
                'entregas_epp_bodega',
                'inventory_epp_deliveries',
                'Entrega diaria de EPP',
                'Consulta y registro de entregas de EPP descontadas desde Bodega.',
                ['ver_entregas_epp', 'registrar_entregas_epp'],
                $permissionIds,
                $now,
            );

            if (! Schema::hasTable('roles')) {
                return;
            }

            $roles = DB::table('roles')
                ->whereIn('slug', ['super_admin', 'administrador', 'prevencion_riesgos'])
                ->pluck('id');

            if (Schema::hasTable('permission_role')) {
                foreach ($roles as $roleId) {
                    foreach ($permissionIds as $permissionId) {
                        DB::table('permission_role')->insertOrIgnore([
                            'role_id' => $roleId,
                            'permission_id' => $permissionId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }

            if (Schema::hasTable('role_system_module')) {
                $moduleIds = DB::table('system_modules')
                    ->whereIn('slug', [
                        'risk_prevention',
                        'risk_prevention_joint_committee',
                        'inventory',
                        'inventory_epp_deliveries',
                    ])
                    ->pluck('id');

                foreach ($roles as $roleId) {
                    foreach ($moduleIds as $moduleId) {
                        DB::table('role_system_module')->insertOrIgnore([
                            'role_id' => $roleId,
                            'system_module_id' => $moduleId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        });
    }

    private function registerPermissionGroup(
        string $slug,
        string $moduleSlug,
        string $name,
        string $description,
        array $permissionSlugs,
        $permissionIds,
        $now,
    ): void {
        if (! Schema::hasTable('permission_groups') || ! Schema::hasTable('permission_group_permission')) {
            return;
        }

        $moduleId = DB::table('system_modules')->where('slug', $moduleSlug)->value('id');
        if (! $moduleId) {
            return;
        }

        DB::table('permission_groups')->insertOrIgnore([
            'slug' => $slug,
            'system_module_id' => $moduleId,
            'name' => $name,
            'description' => $description,
            'sort_order' => 132,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $groupId = DB::table('permission_groups')->where('slug', $slug)->value('id');
        foreach ($permissionSlugs as $permissionSlug) {
            $permissionId = $permissionIds[$permissionSlug] ?? null;
            if ($groupId && $permissionId) {
                DB::table('permission_group_permission')->insertOrIgnore([
                    'permission_group_id' => $groupId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Forward-only en producción: no elimina datos, archivos, permisos ni módulos.
    }
};
