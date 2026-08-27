<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remuneration_funding_source_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funding_source_id')->constrained('accounting_funding_sources')->restrictOnDelete();
            $table->string('provider', 80)->default('*');
            $table->string('alias', 120);
            $table->string('normalized_alias', 120);
            $table->unsignedSmallInteger('display_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['provider', 'normalized_alias'], 'rem_funding_alias_provider_uq');
            $table->index(['funding_source_id', 'is_active', 'display_order'], 'rem_funding_alias_source_idx');
        });

        Schema::create('remuneration_payslip_concept_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concept_id')->nullable()->constrained('remuneration_concepts')->nullOnDelete();
            $table->string('provider', 80)->default('*');
            $table->string('source_code', 80)->nullable();
            $table->string('normalized_label');
            $table->string('line_type', 40)->index();
            $table->string('classification', 80)->nullable()->index();
            $table->string('payment_destination', 120)->nullable()->index();
            $table->string('distribution_base', 40)->nullable();
            $table->boolean('is_imponible')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['provider', 'line_type', 'source_code', 'normalized_label'], 'rem_payslip_concept_rule_uq');
            $table->index(['provider', 'line_type', 'is_active'], 'rem_payslip_rule_lookup_idx');
        });

        Schema::create('remuneration_payslip_batches', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
            $table->string('status', 40)->default('cargado')->index();
            $table->string('stage', 50)->default('carga')->index();
            $table->unsignedInteger('file_count')->default(0);
            $table->unsignedInteger('page_count')->default(0);
            $table->unsignedInteger('processed_pages')->default(0);
            $table->unsignedInteger('payslip_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->bigInteger('gross_total')->default(0);
            $table->bigInteger('taxable_total')->default(0);
            $table->bigInteger('non_taxable_total')->default(0);
            $table->bigInteger('deduction_total')->default(0);
            $table->bigInteger('net_total')->default(0);
            $table->bigInteger('employer_contribution_total')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('options')->nullable();
            $table->json('summary')->nullable();
            $table->text('failure_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'status', 'created_at'], 'rem_payslip_batch_school_idx');
        });

        Schema::create('remuneration_payslip_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('remuneration_payslip_batches')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
            $table->foreignId('replaces_file_id')->nullable()->constrained('remuneration_payslip_files')->nullOnDelete();
            $table->char('sha256', 64);
            $table->string('original_filename');
            $table->string('private_path');
            $table->string('mime_type', 100)->default('application/pdf');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('provider', 80)->nullable()->index();
            $table->string('parser_version', 40)->nullable();
            $table->unsignedInteger('page_count')->default(0);
            $table->unsignedSmallInteger('detected_year')->nullable()->index();
            $table->unsignedTinyInteger('detected_month')->nullable()->index();
            $table->string('status', 40)->default('pendiente')->index();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'sha256'], 'rem_payslip_file_batch_hash_uq');
            $table->index(['school_id', 'sha256', 'status'], 'rem_payslip_file_hash_idx');
            $table->index(['school_id', 'detected_year', 'detected_month'], 'rem_payslip_file_period_idx');
        });

        Schema::create('remuneration_payslip_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('remuneration_payslip_files')->cascadeOnDelete();
            $table->unsignedInteger('page_number');
            $table->string('provider', 80)->nullable();
            $table->string('parser_version', 40)->nullable();
            $table->unsignedSmallInteger('period_year')->nullable()->index();
            $table->unsignedTinyInteger('period_month')->nullable()->index();
            $table->string('extraction_method', 30)->default('native');
            $table->decimal('confidence', 6, 5)->default(0);
            $table->char('text_hash', 64)->nullable();
            $table->longText('normalized_text_encrypted')->nullable();
            $table->json('normalized_structure')->nullable();
            $table->json('extracted_payload')->nullable();
            $table->json('warnings')->nullable();
            $table->string('status', 40)->default('pendiente')->index();
            $table->timestamps();

            $table->unique(['file_id', 'page_number'], 'rem_payslip_page_file_number_uq');
            $table->index(['file_id', 'status', 'page_number'], 'rem_payslip_page_progress_idx');
        });

        Schema::create('remuneration_payslips', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('batch_id')->constrained('remuneration_payslip_batches')->cascadeOnDelete();
            $table->foreignId('file_id')->constrained('remuneration_payslip_files')->cascadeOnDelete();
            $table->foreignId('page_id')->constrained('remuneration_payslip_pages')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
            $table->foreignId('period_id')->nullable()->constrained('remuneration_periods')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('previous_version_id')->nullable()->constrained('remuneration_payslips')->nullOnDelete();
            $table->char('rut_hash', 64)->index();
            $table->text('rut_encrypted');
            $table->text('employee_name_encrypted')->nullable();
            $table->char('business_key_hash', 64)->index();
            $table->unsignedSmallInteger('year')->index();
            $table->unsignedTinyInteger('month')->index();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_current')->default(false)->index();
            $table->string('status', 40)->default('borrador')->index();
            $table->string('reconciliation_status', 40)->default('pendiente')->index();
            $table->decimal('confidence', 6, 5)->default(0);
            $table->bigInteger('gross_taxable_amount')->default(0);
            $table->bigInteger('gross_non_taxable_amount')->default(0);
            $table->bigInteger('gross_total')->default(0);
            $table->bigInteger('legal_deductions')->default(0);
            $table->bigInteger('other_deductions')->default(0);
            $table->bigInteger('total_deductions')->default(0);
            $table->bigInteger('net_amount')->default(0);
            $table->bigInteger('employer_contributions')->default(0);
            $table->bigInteger('total_cost')->default(0);
            $table->json('employment_snapshot')->nullable();
            $table->json('source_totals')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('page_id', 'rem_payslip_page_uq');
            $table->index(['school_id', 'year', 'month', 'is_current'], 'rem_payslip_scope_idx');
            $table->index(['period_id', 'staff_id', 'is_current'], 'rem_payslip_staff_period_idx');
            $table->index(['business_key_hash', 'version'], 'rem_payslip_business_version_idx');
        });

        Schema::table('remuneration_payslip_pages', function (Blueprint $table) {
            $table->foreignId('payslip_id')->nullable()->after('file_id')->constrained('remuneration_payslips')->nullOnDelete();
        });

        Schema::create('remuneration_payslip_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained('remuneration_payslips')->cascadeOnDelete();
            $table->foreignId('funding_source_id')->nullable()->constrained('accounting_funding_sources')->nullOnDelete();
            $table->foreignId('concept_rule_id')->nullable()->constrained('remuneration_payslip_concept_rules')->nullOnDelete();
            $table->string('code', 80)->nullable()->index();
            $table->string('description');
            $table->unsignedSmallInteger('line_number');
            $table->boolean('is_imponible')->index();
            $table->bigInteger('amount');
            $table->string('source_funding_label', 120)->nullable();
            $table->string('original_label');
            $table->unsignedInteger('page_number');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['payslip_id', 'funding_source_id', 'is_imponible'], 'rem_payslip_earning_matrix_idx');
        });

        Schema::create('remuneration_payslip_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained('remuneration_payslips')->cascadeOnDelete();
            $table->foreignId('concept_rule_id')->nullable()->constrained('remuneration_payslip_concept_rules')->nullOnDelete();
            $table->string('code', 80)->nullable()->index();
            $table->string('description');
            $table->string('classification', 80)->index();
            $table->string('payment_destination', 120)->nullable()->index();
            $table->string('distribution_base', 40);
            $table->bigInteger('amount');
            $table->unsignedSmallInteger('line_number');
            $table->unsignedInteger('page_number');
            $table->string('original_label');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['payslip_id', 'classification'], 'rem_payslip_discount_matrix_idx');
        });

        Schema::create('remuneration_payslip_discount_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_id')->constrained('remuneration_payslip_discounts')->cascadeOnDelete();
            $table->foreignId('funding_source_id');
            $table->bigInteger('base_amount');
            $table->decimal('proportion', 16, 12);
            $table->decimal('calculated_amount', 20, 6);
            $table->integer('rounding_adjustment')->default(0);
            $table->bigInteger('assigned_amount');
            $table->timestamps();

            $table->unique(['discount_id', 'funding_source_id'], 'rem_payslip_discount_alloc_uq');
            $table->index(['funding_source_id', 'assigned_amount'], 'rem_payslip_discount_source_idx');
            $table->foreign('funding_source_id', 'rem_pay_disc_alloc_fund_fk')->references('id')->on('accounting_funding_sources')->restrictOnDelete();
        });

        Schema::create('remuneration_payslip_employer_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained('remuneration_payslips')->cascadeOnDelete();
            $table->foreignId('funding_source_id')->nullable();
            $table->foreignId('concept_rule_id')->nullable();
            $table->string('code', 80)->nullable()->index();
            $table->string('description');
            $table->string('source_funding_label', 120)->nullable();
            $table->bigInteger('amount');
            $table->unsignedSmallInteger('line_number');
            $table->unsignedInteger('page_number');
            $table->string('original_label');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['payslip_id', 'funding_source_id'], 'rem_payslip_contribution_matrix_idx');
            $table->foreign('funding_source_id', 'rem_pay_contrib_fund_fk')->references('id')->on('accounting_funding_sources')->nullOnDelete();
            $table->foreign('concept_rule_id', 'rem_pay_contrib_rule_fk')->references('id')->on('remuneration_payslip_concept_rules')->nullOnDelete();
        });

        Schema::create('remuneration_payslip_funding_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained('remuneration_payslips')->cascadeOnDelete();
            $table->foreignId('funding_source_id')->constrained('accounting_funding_sources')->restrictOnDelete();
            $table->bigInteger('taxable_earnings')->default(0);
            $table->bigInteger('non_taxable_earnings')->default(0);
            $table->bigInteger('gross_earnings')->default(0);
            $table->bigInteger('legal_deductions')->default(0);
            $table->bigInteger('other_deductions')->default(0);
            $table->bigInteger('net_amount')->default(0);
            $table->bigInteger('employer_contributions')->default(0);
            $table->bigInteger('total_cost')->default(0);
            $table->bigInteger('reconciliation_difference')->default(0);
            $table->json('calculation_detail')->nullable();
            $table->timestamps();

            $table->unique(['payslip_id', 'funding_source_id'], 'rem_payslip_funding_summary_uq');
            $table->index(['funding_source_id', 'net_amount'], 'rem_payslip_funding_net_idx');
        });

        Schema::create('remuneration_payslip_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->nullable()->constrained('remuneration_payslip_batches')->cascadeOnDelete();
            $table->foreignId('file_id')->nullable()->constrained('remuneration_payslip_files')->cascadeOnDelete();
            $table->foreignId('payslip_id')->nullable()->constrained('remuneration_payslips')->cascadeOnDelete();
            $table->foreignId('funding_source_id')->nullable()->constrained('accounting_funding_sources')->nullOnDelete();
            $table->string('scope', 30)->index();
            $table->string('code', 100)->index();
            $table->string('label');
            $table->string('status', 20)->index();
            $table->bigInteger('expected_amount')->nullable();
            $table->bigInteger('actual_amount')->nullable();
            $table->bigInteger('difference')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['payslip_id', 'status'], 'rem_payslip_control_status_idx');
        });

        Schema::create('remuneration_payslip_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('remuneration_payslip_batches')->cascadeOnDelete();
            $table->foreignId('file_id')->nullable()->constrained('remuneration_payslip_files')->cascadeOnDelete();
            $table->foreignId('page_id')->nullable()->constrained('remuneration_payslip_pages')->cascadeOnDelete();
            $table->foreignId('payslip_id')->nullable()->constrained('remuneration_payslips')->cascadeOnDelete();
            $table->string('code', 100)->index();
            $table->string('severity', 20)->index();
            $table->string('status', 30)->default('abierta')->index();
            $table->string('message');
            $table->json('context')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['batch_id', 'status', 'severity'], 'rem_payslip_issue_batch_idx');
        });

        Schema::create('remuneration_payment_proposals', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
            $table->foreignId('period_id')->constrained('remuneration_periods')->restrictOnDelete();
            $table->string('status', 30)->default('borrador')->index();
            $table->unsignedInteger('version')->default(1);
            $table->bigInteger('net_total')->default(0);
            $table->bigInteger('distributed_total')->default(0);
            $table->bigInteger('employer_contribution_total')->default(0);
            $table->bigInteger('total_cost')->default(0);
            $table->char('source_hash', 64);
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('summary')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'period_id', 'source_hash'], 'rem_payment_proposal_source_uq');
            $table->index(['school_id', 'period_id', 'status'], 'rem_payment_proposal_scope_idx');
        });

        Schema::create('remuneration_payment_proposal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('remuneration_payment_proposals')->cascadeOnDelete();
            $table->foreignId('payslip_id')->constrained('remuneration_payslips')->restrictOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
            $table->bigInteger('payment_amount');
            $table->string('status', 30)->default('incluido')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['proposal_id', 'payslip_id'], 'rem_payment_proposal_item_uq');
        });

        Schema::create('remuneration_payment_proposal_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_item_id');
            $table->foreignId('funding_source_id');
            $table->string('allocation_type', 50)->default('sueldo_liquido')->index();
            $table->bigInteger('amount');
            $table->json('source_detail')->nullable();
            $table->timestamps();

            $table->unique(['proposal_item_id', 'funding_source_id', 'allocation_type'], 'rem_payment_proposal_alloc_uq');
            $table->foreign('proposal_item_id', 'rem_pay_prop_alloc_item_fk')->references('id')->on('remuneration_payment_proposal_items')->cascadeOnDelete();
            $table->foreign('funding_source_id', 'rem_pay_prop_alloc_fund_fk')->references('id')->on('accounting_funding_sources')->restrictOnDelete();
        });

        $this->installCatalogsAndPermissions();
    }

    private function installCatalogsAndPermissions(): void
    {
        $now = now();
        $sourceDefinitions = [
            'GENERAL' => ['candidates' => ['FS-GRAL', 'GENERAL'], 'name' => 'General', 'order' => 10],
            'SEP' => ['candidates' => ['FS-SEP', 'SEP'], 'name' => 'Subvención Escolar Preferencial', 'order' => 20],
            'PIE' => ['candidates' => ['FS-PIE', 'PIE'], 'name' => 'Programa de Integración Escolar', 'order' => 30],
        ];
        foreach ($sourceDefinitions as $alias => $definition) {
            $sourceId = null;
            foreach ($definition['candidates'] as $candidate) {
                $sourceId = DB::table('accounting_funding_sources')->where('code', $candidate)->value('id');
                if ($sourceId) {
                    break;
                }
            }
            if (! $sourceId) {
                DB::table('accounting_funding_sources')->insertOrIgnore([
                    'code' => $alias,
                    'name' => $definition['name'],
                    'category' => 'subvencion',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $sourceId = DB::table('accounting_funding_sources')->where('code', $alias)->value('id');
            }
            if ($sourceId) {
                DB::table('remuneration_funding_source_aliases')->insertOrIgnore([
                    'funding_source_id' => $sourceId,
                    'provider' => '*',
                    'alias' => $alias,
                    'normalized_alias' => $alias,
                    'display_order' => $definition['order'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach ([
            ['2005', 'IMPUESTO UNICO', 'impuesto_unico', 'Formulario 29', 'imponible'],
            ['2000', 'PREVISION', 'legal_previsional', 'Previred', 'imponible'],
            ['2001', 'SALUD', 'legal_previsional', 'Previred', 'imponible'],
            ['2026', 'SEGURO CESANTIA', 'legal_previsional', 'Previred', 'imponible'],
        ] as [$code, $label, $classification, $destination, $base]) {
            DB::table('remuneration_payslip_concept_rules')->insertOrIgnore([
                'provider' => 'numerus',
                'source_code' => $code,
                'normalized_label' => $label,
                'line_type' => 'discount',
                'classification' => $classification,
                'payment_destination' => $destination,
                'distribution_base' => $base,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissions = [
            ['remuneraciones.liquidaciones_pdf.ver', 'Ver liquidaciones de sueldo importadas'],
            ['remuneraciones.liquidaciones_pdf.importar', 'Importar liquidaciones de sueldo'],
            ['remuneraciones.liquidaciones_pdf.incidencias', 'Resolver incidencias de liquidaciones'],
            ['remuneraciones.liquidaciones_pdf.reprocesar', 'Reprocesar liquidaciones'],
            ['remuneraciones.liquidaciones_pdf.exportar', 'Exportar matrices de liquidaciones'],
            ['remuneraciones.liquidaciones_pdf.propuesta_pago', 'Generar propuesta de pago de remuneraciones'],
            ['remuneraciones.liquidaciones_pdf.anular', 'Anular importaciones de liquidaciones'],
            ['remuneraciones.liquidaciones_pdf.auditoria', 'Consultar auditoría de liquidaciones'],
        ];

        foreach ($permissions as [$slug, $name]) {
            DB::table('permissions')->insertOrIgnore([
                'slug' => $slug,
                'name' => $name,
                'description' => $name.'.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $parentId = DB::table('system_modules')->where('slug', 'remuneration')->value('id');
        foreach ([
            ['remuneration_payslips_pdf', 'Liquidaciones de sueldo', '/remuneraciones/liquidaciones-sueldo', 31],
            ['remuneration_payslip_matrix', 'Matriz por subvención', '/remuneraciones/matriz-subvenciones', 32],
            ['remuneration_payslip_reconciliation', 'Conciliación Libro vs. Liquidaciones', '/remuneraciones/conciliacion-liquidaciones', 33],
            ['remuneration_payslip_imports', 'Importaciones e incidencias', '/remuneraciones/importaciones-liquidaciones', 34],
        ] as [$slug, $name, $route, $order]) {
            DB::table('system_modules')->insertOrIgnore([
                'slug' => $slug,
                'name' => $name,
                'frontend_route' => $route,
                'icon' => null,
                'sort_order' => $order,
                'active' => true,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissionIds = DB::table('permissions')->whereIn('slug', array_column($permissions, 0))->pluck('id');
        $groupId = DB::table('permission_groups')->where('slug', 'remuneraciones')->value('id');
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

        $superAdminId = DB::table('roles')->where('slug', 'super_admin')->value('id');
        if ($superAdminId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $superAdminId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $moduleIds = DB::table('system_modules')->whereIn('slug', [
                'remuneration_payslips_pdf',
                'remuneration_payslip_matrix',
                'remuneration_payslip_reconciliation',
                'remuneration_payslip_imports',
            ])->pluck('id');
            foreach ($moduleIds as $moduleId) {
                DB::table('role_system_module')->insertOrIgnore([
                    'role_id' => $superAdminId,
                    'system_module_id' => $moduleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: payroll history and its audit trail must be preserved.
    }
};
