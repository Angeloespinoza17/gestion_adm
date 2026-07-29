<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_subsidy_imports', function (Blueprint $table) {
            $table->id();
            $table->string('rbd', 20)->index();
            $table->date('period')->index();
            $table->string('source_type', 80)->nullable()->index();
            $table->string('original_filename');
            $table->string('detected_format', 40);
            $table->char('sha256', 64)->unique();
            $table->string('parser_version', 30)->default('1.0');
            $table->string('status', 40)->default('procesado')->index();
            $table->string('storage_path')->nullable();
            $table->json('summary')->nullable();
            $table->json('warnings')->nullable();
            $table->json('errors')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('accounting_subsidy_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funding_source_id')->nullable()->constrained('accounting_funding_sources')->nullOnDelete();
            $table->string('code', 80)->unique();
            $table->string('rbd', 20)->index();
            $table->date('period')->index();
            $table->string('subsidy_type', 80)->index();
            $table->date('payment_date')->nullable()->index();
            $table->decimal('gross_amount', 16, 2)->default(0);
            $table->decimal('adjustments_amount', 16, 2)->default(0);
            $table->decimal('deductions_amount', 16, 2)->default(0);
            $table->decimal('reliquidations_amount', 16, 2)->default(0);
            $table->decimal('net_amount', 16, 2)->default(0);
            $table->decimal('transferred_amount', 16, 2)->nullable();
            $table->decimal('difference_amount', 16, 2)->nullable();
            $table->string('status', 40)->default('borrador')->index();
            $table->string('source_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['rbd', 'period', 'subsidy_type'], 'acc_subsidy_settlement_business_uq');
        });

        Schema::create('accounting_subsidy_settlement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained('accounting_subsidy_settlements')->cascadeOnDelete();
            $table->foreignId('import_id')->nullable()->constrained('accounting_subsidy_imports')->nullOnDelete();
            $table->string('concept_code', 100);
            $table->string('concept_name');
            $table->string('classification', 60)->default('haber');
            $table->smallInteger('sign')->default(1);
            $table->decimal('amount', 16, 2)->default(0);
            $table->boolean('education_allocable')->default(false);
            $table->boolean('informative')->default(false);
            $table->decimal('declared_amount', 16, 2)->nullable();
            $table->string('source_filename')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['settlement_id', 'concept_code'], 'acc_subsidy_line_concept_uq');
        });

        Schema::create('accounting_subsidy_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained('accounting_subsidy_settlements')->cascadeOnDelete();
            $table->foreignId('line_id')->nullable()->constrained('accounting_subsidy_settlement_lines')->cascadeOnDelete();
            $table->foreignId('education_level_id')->nullable()->constrained('education_levels')->nullOnDelete();
            $table->string('teaching_code', 30)->nullable()->index();
            $table->string('grade_code', 30)->nullable();
            $table->string('course_letter', 20)->nullable();
            $table->string('education_label')->nullable();
            $table->decimal('enrollment', 12, 4)->nullable();
            $table->decimal('attendance_average', 14, 4)->nullable();
            $table->decimal('use_factor', 14, 4)->nullable();
            $table->decimal('amount', 16, 2)->default(0);
            $table->char('source_row_hash', 64);
            $table->json('source_payload')->nullable();
            $table->timestamps();
            $table->unique(['settlement_id', 'source_row_hash'], 'acc_subsidy_allocation_row_uq');
            $table->index(['settlement_id', 'education_level_id'], 'acc_subsidy_allocation_level_idx');
        });

        Schema::create('accounting_subsidy_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained('accounting_subsidy_settlements')->cascadeOnDelete();
            $table->foreignId('income_id')->nullable()->constrained('accounting_incomes')->nullOnDelete();
            $table->foreignId('bank_movement_id')->nullable()->constrained('accounting_bank_movements')->nullOnDelete();
            $table->decimal('matched_amount', 16, 2);
            $table->string('status', 40)->default('conciliado')->index();
            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('matched_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique('settlement_id', 'acc_subsidy_match_settlement_uq');
            $table->unique('income_id', 'acc_subsidy_match_income_uq');
        });

        $this->installPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_subsidy_matches');
        Schema::dropIfExists('accounting_subsidy_allocations');
        Schema::dropIfExists('accounting_subsidy_settlement_lines');
        Schema::dropIfExists('accounting_subsidy_settlements');
        Schema::dropIfExists('accounting_subsidy_imports');

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->whereIn('slug', [
                'contabilidad.subvenciones.importar',
                'contabilidad.subvenciones.aprobar',
                'contabilidad.subvenciones.conciliar',
                'contabilidad.subvenciones.contabilizar',
            ])->delete();
        }
    }

    private function installPermissions(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        $permissions = [
            ['slug' => 'contabilidad.subvenciones.importar', 'name' => 'Importar liquidaciones de subvenciones', 'description' => 'Permite cargar y previsualizar órdenes y anexos MINEDUC.'],
            ['slug' => 'contabilidad.subvenciones.aprobar', 'name' => 'Aprobar liquidaciones de subvenciones', 'description' => 'Permite validar y aprobar liquidaciones importadas.'],
            ['slug' => 'contabilidad.subvenciones.conciliar', 'name' => 'Conciliar subvenciones', 'description' => 'Permite vincular liquidaciones con transferencias y movimientos bancarios.'],
            ['slug' => 'contabilidad.subvenciones.contabilizar', 'name' => 'Contabilizar subvenciones', 'description' => 'Permite crear el ingreso y asiento contable desde una liquidación aprobada.'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore([
                ...$permission,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', array_column($permissions, 'slug'))
            ->pluck('id', 'slug');

        if (Schema::hasTable('permission_group_permission') && Schema::hasTable('permission_groups')) {
            $groupId = DB::table('permission_groups')->where('slug', 'contabilidad')->value('id');
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
        }

        if (! Schema::hasTable('permission_role') || ! Schema::hasTable('roles')) {
            return;
        }

        $roleMatrix = [
            'super_admin' => array_keys($permissionIds->all()),
            'contabilidad_admin' => array_keys($permissionIds->all()),
            'contabilidad_analista' => [
                'contabilidad.subvenciones.importar',
                'contabilidad.subvenciones.aprobar',
                'contabilidad.subvenciones.conciliar',
            ],
        ];

        foreach ($roleMatrix as $roleSlug => $permissionSlugs) {
            $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');
            if (! $roleId) {
                continue;
            }

            foreach ($permissionSlugs as $permissionSlug) {
                $permissionId = $permissionIds[$permissionSlug] ?? null;
                if ($permissionId) {
                    DB::table('permission_role')->insertOrIgnore([
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }
};
