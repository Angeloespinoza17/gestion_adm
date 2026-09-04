<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private const PERMISSIONS = [
        'orientation.view' => 'Ver módulo de Orientación',
        'orientation.manage_plan' => 'Gestionar plan anual de Orientación',
        'orientation.manage_execution' => 'Gestionar actividades de Orientación',
        'orientation.manage_evidence' => 'Gestionar evidencias de Orientación',
    ];

    public function up(): void
    {
        $this->createTables();
        $this->registerAccess();
    }

    public function down(): void
    {
        // Migración aditiva: no se eliminan planes, actividades, evidencias ni asignaciones RBAC.
    }

    private function createTables(): void
    {
        if (! Schema::hasTable('orientation_plans')) {
            Schema::create('orientation_plans', function (Blueprint $table): void {
                $table->id();
                $table->unsignedSmallInteger('year')->unique();
                $table->string('title', 191);
                $table->text('general_objective')->nullable();
                $table->longText('description')->nullable();
                $table->string('status', 30)->default('draft');
                $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['status', 'year'], 'orientation_plan_status_year_idx');
            });
        }

        if (! Schema::hasTable('orientation_actions')) {
            Schema::create('orientation_actions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('orientation_plan_id')->constrained('orientation_plans')->cascadeOnDelete();
                $table->string('title', 191);
                $table->text('objective')->nullable();
                $table->longText('description')->nullable();
                $table->text('target_levels')->nullable();
                $table->text('planned_verification_means')->nullable();
                $table->text('material_resources')->nullable();
                $table->text('responsible_summary')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->string('status', 30)->default('planned');
                $table->unsignedTinyInteger('progress')->default(0);
                $table->unsignedSmallInteger('sort_order')->default(1);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['orientation_plan_id', 'status', 'sort_order'], 'orientation_action_plan_status_idx');
                $table->index(['orientation_plan_id', 'start_date', 'end_date'], 'orientation_action_calendar_idx');
            });
        }

        if (! Schema::hasTable('orientation_action_responsibles')) {
            Schema::create('orientation_action_responsibles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('orientation_action_id')->constrained('orientation_actions')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['orientation_action_id', 'user_id'], 'orientation_action_responsible_unique');
                $table->index(['user_id', 'orientation_action_id'], 'orientation_responsible_user_idx');
            });
        }

        if (! Schema::hasTable('orientation_related_plans')) {
            Schema::create('orientation_related_plans', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('orientation_plan_id')->constrained('orientation_plans')->cascadeOnDelete();
                $table->string('name', 191);
                $table->string('category', 80)->default('institutional');
                $table->text('description')->nullable();
                $table->string('reference_url', 2048)->nullable();
                $table->string('status', 30)->default('active');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['orientation_plan_id', 'name'], 'orientation_related_plan_name_unique');
                $table->index(['orientation_plan_id', 'status'], 'orientation_related_plan_status_idx');
            });
        }

        if (! Schema::hasTable('orientation_action_related_plan')) {
            Schema::create('orientation_action_related_plan', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('orientation_action_id')->constrained('orientation_actions')->cascadeOnDelete();
                $table->foreignId('orientation_related_plan_id');
                $table->foreign('orientation_related_plan_id', 'orientation_arp_related_plan_fk')
                    ->references('id')
                    ->on('orientation_related_plans')
                    ->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['orientation_action_id', 'orientation_related_plan_id'], 'orientation_action_related_unique');
                $table->index(['orientation_related_plan_id', 'orientation_action_id'], 'orientation_related_action_idx');
            });
        }

        if (! Schema::hasTable('orientation_activities')) {
            Schema::create('orientation_activities', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('orientation_action_id')->constrained('orientation_actions')->cascadeOnDelete();
                $table->string('title', 191);
                $table->longText('description')->nullable();
                $table->dateTime('starts_at');
                $table->dateTime('ends_at')->nullable();
                $table->string('status', 30)->default('scheduled');
                $table->string('location', 191)->nullable();
                $table->text('participants')->nullable();
                $table->unsignedInteger('attendee_count')->nullable();
                $table->longText('results')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['orientation_action_id', 'status', 'starts_at'], 'orientation_activity_action_status_idx');
                $table->index(['starts_at', 'ends_at'], 'orientation_activity_calendar_idx');
            });
        }

        if (! Schema::hasTable('orientation_evidences')) {
            Schema::create('orientation_evidences', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('orientation_action_id')->constrained('orientation_actions')->cascadeOnDelete();
                $table->foreignId('orientation_activity_id')->nullable()->constrained('orientation_activities')->nullOnDelete();
                $table->string('evidence_type', 50)->default('other');
                $table->string('title', 191);
                $table->text('description')->nullable();
                $table->date('occurred_on')->nullable();
                $table->string('storage_disk', 30)->default('local');
                $table->string('file_path', 1024)->nullable();
                $table->string('external_url', 2048)->nullable();
                $table->string('original_name', 255)->nullable();
                $table->string('mime_type', 150)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['orientation_action_id', 'occurred_on'], 'orientation_evidence_action_date_idx');
                $table->index(['orientation_activity_id', 'created_at'], 'orientation_evidence_activity_idx');
            });
        }

        $this->ensureRelatedPlanPivotForeignKey();
    }

    private function ensureRelatedPlanPivotForeignKey(): void
    {
        if (! Schema::hasTable('orientation_action_related_plan')) {
            return;
        }

        $exists = collect(Schema::getForeignKeys('orientation_action_related_plan'))
            ->contains(fn (array $foreign): bool => in_array('orientation_related_plan_id', $foreign['columns'] ?? [], true));

        if ($exists) {
            return;
        }

        Schema::table('orientation_action_related_plan', function (Blueprint $table): void {
            $table->foreign('orientation_related_plan_id', 'orientation_arp_related_plan_fk')
                ->references('id')
                ->on('orientation_related_plans')
                ->cascadeOnDelete();
        });
    }

    private function registerAccess(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();

            DB::table('roles')->insertOrIgnore([
                'slug' => 'orientacion',
                'name' => 'Orientador/a',
                'description' => 'Gestión del Plan Anual de Orientación, sus actividades y evidencias.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (self::PERMISSIONS as $slug => $name) {
                DB::table('permissions')->insertOrIgnore([
                    'slug' => $slug,
                    'name' => $name,
                    'description' => 'Permiso del módulo de Orientación.',
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('system_modules')->insertOrIgnore([
                'slug' => 'orientation',
                'name' => 'Orientación',
                'frontend_route' => null,
                'icon' => 'bx-compass',
                'sort_order' => 72,
                'active' => true,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $parentId = DB::table('system_modules')->where('slug', 'orientation')->value('id');
            if (! $parentId) {
                return;
            }

            $children = [
                ['slug' => 'orientation_annual_plan', 'name' => 'Plan anual', 'route' => '/orientation/plan-anual', 'sort' => 1],
                ['slug' => 'orientation_calendar', 'name' => 'Calendario', 'route' => '/orientation/calendario', 'sort' => 2],
                ['slug' => 'orientation_statistics', 'name' => 'Estadísticas', 'route' => '/orientation/estadisticas', 'sort' => 3],
            ];

            foreach ($children as $child) {
                DB::table('system_modules')->insertOrIgnore([
                    'slug' => $child['slug'],
                    'name' => $child['name'],
                    'frontend_route' => $child['route'],
                    'icon' => null,
                    'sort_order' => $child['sort'],
                    'active' => true,
                    'parent_id' => $parentId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $roleId = DB::table('roles')->where('slug', 'orientacion')->value('id');
            $permissionIds = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id');
            $moduleIds = DB::table('system_modules')->whereIn('slug', ['orientation', 'orientation_annual_plan', 'orientation_calendar', 'orientation_statistics'])->pluck('id');

            if ($roleId && Schema::hasTable('permission_role')) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_role')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            if ($roleId && Schema::hasTable('role_system_module')) {
                foreach ($moduleIds as $moduleId) {
                    DB::table('role_system_module')->insertOrIgnore([
                        'role_id' => $roleId,
                        'system_module_id' => $moduleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            if (! Schema::hasTable('permission_groups') || ! Schema::hasTable('permission_group_permission')) {
                return;
            }

            DB::table('permission_groups')->insertOrIgnore([
                'system_module_id' => $parentId,
                'slug' => 'orientacion',
                'name' => 'Orientación',
                'description' => 'Plan anual, acciones, actividades, calendario y evidencias de Orientación.',
                'sort_order' => 125,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $groupId = DB::table('permission_groups')->where('slug', 'orientacion')->value('id');
            if ($groupId) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_group_permission')->insertOrIgnore([
                        'permission_group_id' => $groupId,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }
};
