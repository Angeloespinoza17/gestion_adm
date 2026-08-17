<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'ver_traslados_operativos' => 'Ver traslados operativos',
        'solicitar_traslados_operativos' => 'Solicitar traslados operativos',
        'visar_traslados_operativos' => 'Visar traslados operativos',
        'gestionar_traslados_operativos' => 'Gestionar traslados operativos',
        'administrar_proveedores_traslados' => 'Administrar proveedores de traslados',
        'exportar_traslados_operativos' => 'Exportar traslados operativos',
        'importar_traslados_operativos' => 'Importar traslados operativos históricos',
    ];

    private const MODULES = [
        ['slug' => 'operational_transfers_requests', 'name' => 'Mis solicitudes', 'route' => '/operational/transfers', 'sort' => 1],
        ['slug' => 'operational_transfers_review', 'name' => 'Bandeja de visación', 'route' => '/operational/transfers/review', 'sort' => 2],
        ['slug' => 'operational_transfers_management', 'name' => 'Gestión de traslados', 'route' => '/operational/transfers/management', 'sort' => 3],
        ['slug' => 'operational_transfers_reports', 'name' => 'Reportes de traslados', 'route' => '/operational/transfers/reports', 'sort' => 4],
    ];

    public function up(): void
    {
        Schema::create('operational_transfer_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('operational_transfer_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('rut', 30)->nullable()->unique();
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 60)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('name');
        });

        Schema::create('operational_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 40)->unique();
            $table->foreignId('requester_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('visor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('administration_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requester_name_snapshot')->nullable();
            $table->string('requester_role_snapshot')->nullable();
            $table->string('requester_unit_snapshot')->nullable();
            $table->string('visor_name_snapshot')->nullable();
            $table->string('activity_type', 80)->default('salida_pedagogica');
            $table->string('activity_name');
            $table->string('course_subject')->nullable();
            $table->text('purpose')->nullable();
            $table->date('transport_date')->index();
            $table->time('departure_time');
            $table->time('return_time')->nullable();
            $table->string('origin')->default('Colegio Nuestra Señora del Carmen');
            $table->string('destination');
            $table->string('transport_mode', 30)->default('ida_vuelta');
            $table->unsignedSmallInteger('student_count')->default(0);
            $table->unsignedSmallInteger('adult_count')->default(1);
            $table->unsignedSmallInteger('passenger_count')->default(1);
            $table->boolean('reduced_mobility')->default(false);
            $table->text('mobility_requirements')->nullable();
            $table->text('visible_observations')->nullable();
            $table->text('internal_observations')->nullable();
            $table->string('approval_status', 50)->default('borrador')->index();
            $table->string('service_status', 50)->default('sin_gestion')->index();
            $table->string('dte_status', 30)->default('no_aplica')->index();
            $table->string('payment_status', 30)->default('no_iniciado')->index();
            $table->boolean('urgent')->default(false)->index();
            $table->boolean('legacy_imported')->default(false)->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('visor_reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['requester_staff_id', 'approval_status'], 'otr_requester_status_idx');
            $table->index(['visor_user_id', 'approval_status'], 'otr_visor_status_idx');
            $table->index(['transport_date', 'service_status'], 'otr_date_service_idx');
        });

        Schema::create('operational_transfer_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_transfer_request_id')->constrained('operational_transfer_requests', indexName: 'ota_request_fk')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('step', 40);
            $table->string('decision', 30);
            $table->text('comments')->nullable();
            $table->text('internal_comments')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();
            $table->index(['operational_transfer_request_id', 'step'], 'ota_request_step_idx');
        });

        Schema::create('operational_transfer_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_transfer_request_id')->constrained('operational_transfer_requests', indexName: 'otq_request_fk')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('operational_transfer_providers')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->date('valid_until')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('selected')->default(false)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['operational_transfer_request_id', 'selected'], 'otq_request_selected_idx');
        });

        Schema::create('operational_transfer_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_transfer_request_id')->constrained('operational_transfer_requests', indexName: 'oto_request_fk')->cascadeOnDelete();
            $table->unique('operational_transfer_request_id', 'oto_request_unique');
            $table->foreignId('selected_quote_id')->nullable()->constrained('operational_transfer_quotes')->nullOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('operational_transfer_providers')->nullOnDelete();
            $table->unsignedBigInteger('final_cost')->nullable();
            $table->string('confirmation_reference')->nullable();
            $table->text('confirmation_notes')->nullable();
            $table->string('dte_number')->nullable();
            $table->date('dte_received_on')->nullable();
            $table->string('payment_reference')->nullable();
            $table->date('payment_requested_on')->nullable();
            $table->date('payment_scheduled_on')->nullable();
            $table->date('paid_on')->nullable();
            $table->text('administrative_notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('operational_transfer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_transfer_request_id')->constrained('operational_transfer_requests', indexName: 'otd_request_fk')->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('operational_transfer_quotes')->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_type', 50)->default('otro')->index();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->boolean('official_snapshot')->default(false)->index();
            $table->string('snapshot_stage', 30)->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();
            $table->index(['operational_transfer_request_id', 'document_type'], 'otd_request_type_idx');
        });

        Schema::create('operational_transfer_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_transfer_request_id')->constrained('operational_transfer_requests', indexName: 'otl_request_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('old_status', 50)->nullable();
            $table->string('new_status', 50)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['operational_transfer_request_id', 'created_at'], 'otl_request_created_idx');
        });

        $this->registerAccess();
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_transfer_logs');
        Schema::dropIfExists('operational_transfer_documents');
        Schema::dropIfExists('operational_transfer_operations');
        Schema::dropIfExists('operational_transfer_quotes');
        Schema::dropIfExists('operational_transfer_approvals');
        Schema::dropIfExists('operational_transfer_requests');
        Schema::dropIfExists('operational_transfer_providers');
        Schema::dropIfExists('operational_transfer_sequences');
    }

    private function registerAccess(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        $now = now();
        foreach (self::PERMISSIONS as $slug => $name) {
            DB::table('permissions')->insertOrIgnore([
                'slug' => $slug,
                'name' => $name,
                'description' => 'Permiso del módulo de Gestión Operativa - Traslados.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('permissions')->where('slug', $slug)->update([
                'name' => $name,
                'active' => true,
                'updated_at' => $now,
            ]);
        }

        DB::table('system_modules')->insertOrIgnore([
            'slug' => 'operational_management',
            'name' => 'Gestión Operativa',
            'frontend_route' => null,
            'icon' => 'bx-briefcase-alt-2',
            'sort_order' => 44,
            'active' => true,
            'parent_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $parentId = DB::table('system_modules')->where('slug', 'operational_management')->value('id');

        foreach (self::MODULES as $module) {
            DB::table('system_modules')->insertOrIgnore([
                'slug' => $module['slug'],
                'name' => $module['name'],
                'frontend_route' => $module['route'],
                'icon' => null,
                'sort_order' => $module['sort'],
                'active' => true,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasTable('roles')) {
            $this->upsertRole('subdirector', 'Subdirector/a', 'Visación de solicitudes institucionales.', $now);
            $this->attachAccess(['docente', 'coordinador_academico'], ['ver_traslados_operativos', 'solicitar_traslados_operativos'], ['operational_management', 'operational_transfers_requests'], $now);
            $this->attachAccess(['subdirector'], ['ver_traslados_operativos', 'solicitar_traslados_operativos', 'visar_traslados_operativos', 'exportar_traslados_operativos'], ['operational_management', 'operational_transfers_requests', 'operational_transfers_review'], $now);
            $this->attachAccess(['administrador'], array_keys(self::PERMISSIONS), array_merge(['operational_management'], array_column(self::MODULES, 'slug')), $now);
            $this->attachAccess(['super_admin'], array_keys(self::PERMISSIONS), array_merge(['operational_management'], array_column(self::MODULES, 'slug')), $now);
        }

        if (Schema::hasTable('permission_groups') && Schema::hasTable('permission_group_permission')) {
            DB::table('permission_groups')->insertOrIgnore([
                'system_module_id' => $parentId,
                'name' => 'Gestión Operativa - Traslados',
                'slug' => 'operational_transfers',
                'description' => 'Solicitudes, visación, cotización, confirmación, DTE, pago e importación histórica de traslados.',
                'sort_order' => 44,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $groupId = DB::table('permission_groups')->where('slug', 'operational_transfers')->value('id');
            foreach (DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id') as $permissionId) {
                DB::table('permission_group_permission')->insertOrIgnore([
                    'permission_group_id' => $groupId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function upsertRole(string $slug, string $name, string $description, mixed $now): void
    {
        DB::table('roles')->insertOrIgnore(compact('slug', 'name', 'description') + [
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('roles')->where('slug', $slug)->update([
            'name' => $name,
            'description' => $description,
            'active' => true,
            'updated_at' => $now,
        ]);
    }

    /** @param array<int, string> $roleSlugs @param array<int, string> $permissionSlugs @param array<int, string> $moduleSlugs */
    private function attachAccess(array $roleSlugs, array $permissionSlugs, array $moduleSlugs, mixed $now): void
    {
        $roleIds = DB::table('roles')->whereIn('slug', $roleSlugs)->pluck('id');
        $permissionIds = DB::table('permissions')->whereIn('slug', $permissionSlugs)->pluck('id');
        $moduleIds = DB::table('system_modules')->whereIn('slug', $moduleSlugs)->pluck('id');

        foreach ($roleIds as $roleId) {
            if (Schema::hasTable('permission_role')) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_role')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
            if (Schema::hasTable('role_system_module')) {
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
    }
};
