<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remuneration_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->index();
            $table->unsignedTinyInteger('month')->index();
            $table->string('name');
            $table->string('status', 40)->default('abierto')->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['year', 'month'], 'rem_periods_year_month_unique');
        });

        Schema::create('remuneration_legal_parameters', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->index();
            $table->string('name');
            $table->string('category', 80)->index();
            $table->decimal('value', 18, 6);
            $table->string('unit', 30)->default('percent');
            $table->date('effective_from')->index();
            $table->date('effective_until')->nullable()->index();
            $table->string('source_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['code', 'effective_from', 'effective_until'], 'rem_params_code_dates_idx');
        });

        Schema::create('remuneration_employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->unique()->constrained('staff')->cascadeOnDelete();
            $table->string('payment_method', 60)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_type', 80)->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('afp_name')->nullable();
            $table->decimal('afp_rate', 9, 6)->nullable();
            $table->boolean('is_pensioned')->default(false);
            $table->string('health_institution_type', 40)->nullable();
            $table->string('health_institution_name')->nullable();
            $table->decimal('health_plan_amount', 18, 6)->nullable();
            $table->string('health_plan_unit', 30)->nullable();
            $table->boolean('has_afc')->default(true);
            $table->date('afc_started_at')->nullable();
            $table->string('family_allowance_tramo', 20)->nullable();
            $table->string('apv_institution')->nullable();
            $table->decimal('apv_amount', 18, 6)->nullable();
            $table->string('apv_unit', 30)->nullable();
            $table->string('tax_regime', 60)->nullable();
            $table->json('family_dependents')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('remuneration_contract_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->unique()->constrained('contracts')->cascadeOnDelete();
            $table->foreignId('employee_profile_id')->nullable()->constrained('remuneration_employee_profiles')->nullOnDelete();
            $table->string('employee_type', 80)->default('asistente')->index();
            $table->boolean('teacher_career')->default(false)->index();
            $table->string('teacher_level', 80)->nullable();
            $table->unsignedTinyInteger('bienios')->nullable();
            $table->decimal('priority_percent', 7, 4)->nullable();
            $table->bigInteger('base_salary')->default(0);
            $table->decimal('weekly_hours', 8, 2)->default(0);
            $table->decimal('basic_hours', 8, 2)->nullable();
            $table->decimal('middle_hours', 8, 2)->nullable();
            $table->decimal('pie_hours', 8, 2)->nullable();
            $table->decimal('sep_hours', 8, 2)->nullable();
            $table->decimal('pro_retention_hours', 8, 2)->nullable();
            $table->json('funding_distribution')->nullable();
            $table->foreignId('accounting_debit_account_id')->nullable();
            $table->foreignId('accounting_credit_account_id')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->date('effective_from')->nullable()->index();
            $table->date('effective_until')->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['staff_id', 'is_active', 'effective_from'], 'rem_contract_settings_staff_active_idx');
            $table->foreign('accounting_debit_account_id', 'rem_contract_debit_fk')->references('id')->on('accounting_manual_accounts')->nullOnDelete();
            $table->foreign('accounting_credit_account_id', 'rem_contract_credit_fk')->references('id')->on('accounting_manual_accounts')->nullOnDelete();
        });

        Schema::create('remuneration_concepts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->string('type', 40)->index();
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_imponible')->default(false);
            $table->boolean('affects_tax_base')->default(false);
            $table->boolean('affects_net')->default(true);
            $table->boolean('is_legal')->default(false)->index();
            $table->boolean('is_system')->default(false)->index();
            $table->string('calculation_type', 40)->default('manual');
            $table->bigInteger('amount')->nullable();
            $table->string('default_unit', 30)->nullable();
            $table->text('formula')->nullable();
            $table->foreignId('accounting_debit_account_id')->nullable();
            $table->foreignId('accounting_credit_account_id')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->boolean('is_active')->default(true)->index();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('accounting_debit_account_id', 'rem_concepts_debit_fk')->references('id')->on('accounting_manual_accounts')->nullOnDelete();
            $table->foreign('accounting_credit_account_id', 'rem_concepts_credit_fk')->references('id')->on('accounting_manual_accounts')->nullOnDelete();
        });

        Schema::create('remuneration_employee_concepts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('concept_id')->constrained('remuneration_concepts')->cascadeOnDelete();
            $table->boolean('is_recurring')->default(true)->index();
            $table->bigInteger('amount')->nullable();
            $table->text('formula_override')->nullable();
            $table->date('starts_at')->nullable()->index();
            $table->date('ends_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['staff_id', 'concept_id', 'is_active'], 'rem_employee_concepts_staff_concept_idx');
        });

        Schema::create('remuneration_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('remuneration_periods')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('concept_id')->nullable()->constrained('remuneration_concepts')->nullOnDelete();
            $table->string('movement_type', 60)->index();
            $table->string('source_type', 60)->nullable()->index();
            $table->string('description');
            $table->bigInteger('amount')->default(0);
            $table->decimal('quantity', 12, 4)->nullable();
            $table->bigInteger('unit_value')->nullable();
            $table->decimal('affects_days', 8, 2)->nullable();
            $table->date('starts_at')->nullable()->index();
            $table->date('ends_at')->nullable()->index();
            $table->string('status', 40)->default('borrador')->index();
            $table->foreignId('funding_source_id')->nullable()->constrained('accounting_funding_sources')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('accounting_cost_centers')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['period_id', 'staff_id', 'status'], 'rem_movements_period_staff_status_idx');
        });

        Schema::create('remuneration_payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('remuneration_periods')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('employee_profile_id')->nullable()->constrained('remuneration_employee_profiles')->nullOnDelete();
            $table->string('code', 80)->unique();
            $table->string('payroll_type', 40)->default('mensual')->index();
            $table->string('status', 40)->default('calculada')->index();
            $table->string('calculation_version', 40)->default('v1');
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('annulled_at')->nullable();
            $table->foreignId('annulled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->bigInteger('gross_taxable_amount')->default(0);
            $table->bigInteger('gross_non_taxable_amount')->default(0);
            $table->bigInteger('gross_total')->default(0);
            $table->bigInteger('taxable_amount')->default(0);
            $table->bigInteger('legal_deductions')->default(0);
            $table->bigInteger('other_deductions')->default(0);
            $table->bigInteger('total_deductions')->default(0);
            $table->bigInteger('employer_contributions')->default(0);
            $table->bigInteger('net_amount')->default(0);
            $table->bigInteger('total_cost')->default(0);
            $table->json('snapshot')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['period_id', 'staff_id', 'payroll_type'], 'rem_payroll_period_staff_type_unique');
            $table->index(['period_id', 'status'], 'rem_payrolls_period_status_idx');
        });

        Schema::create('remuneration_payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('remuneration_payrolls')->cascadeOnDelete();
            $table->foreignId('concept_id')->nullable()->constrained('remuneration_concepts')->nullOnDelete();
            $table->foreignId('source_movement_id')->nullable()->constrained('remuneration_movements')->nullOnDelete();
            $table->string('line_type', 40)->index();
            $table->string('code', 80);
            $table->string('name');
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_imponible')->default(false);
            $table->boolean('affects_tax_base')->default(false);
            $table->boolean('affects_net')->default(true);
            $table->bigInteger('amount')->default(0);
            $table->decimal('quantity', 12, 4)->nullable();
            $table->bigInteger('unit_value')->nullable();
            $table->text('formula')->nullable();
            $table->string('source', 60)->nullable();
            $table->json('snapshot')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();

            $table->index(['payroll_id', 'line_type'], 'rem_payroll_lines_payroll_type_idx');
        });

        Schema::create('remuneration_payroll_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('remuneration_payrolls')->cascadeOnDelete();
            $table->foreignId('funding_source_id')->nullable()->constrained('accounting_funding_sources')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('accounting_cost_centers')->nullOnDelete();
            $table->decimal('percentage', 9, 6)->default(100);
            $table->bigInteger('gross_amount')->default(0);
            $table->bigInteger('employer_contribution_amount')->default(0);
            $table->bigInteger('deduction_amount')->default(0);
            $table->bigInteger('net_amount')->default(0);
            $table->bigInteger('total_cost_amount')->default(0);
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->index(['payroll_id', 'funding_source_id', 'cost_center_id'], 'rem_payroll_dist_lookup_idx');
        });

        Schema::create('remuneration_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('remuneration_payrolls')->cascadeOnDelete();
            $table->date('payment_date')->index();
            $table->bigInteger('amount');
            $table->string('payment_method', 60)->default('transferencia')->index();
            $table->foreignId('bank_account_id')->nullable()->constrained('accounting_bank_accounts')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->string('status', 40)->default('pagado')->index();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('remuneration_accounting_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('remuneration_periods')->cascadeOnDelete();
            $table->foreignId('payroll_id')->nullable()->constrained('remuneration_payrolls')->cascadeOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();
            $table->string('export_code', 80)->unique();
            $table->string('status', 40)->default('generado')->index();
            $table->timestamp('exported_at')->nullable();
            $table->foreignId('exported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->bigInteger('total_debit')->default(0);
            $table->bigInteger('total_credit')->default(0);
            $table->json('payload')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['period_id', 'status'], 'rem_acc_exports_period_status_idx');
        });

        Schema::create('remuneration_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 80)->nullable();
            $table->string('module', 80)->default('remuneraciones')->index();
            $table->nullableMorphs('auditable', 'rem_audit_auditable_idx');
            $table->string('action', 80)->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changes')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remuneration_audit_logs');
        Schema::dropIfExists('remuneration_accounting_exports');
        Schema::dropIfExists('remuneration_payments');
        Schema::dropIfExists('remuneration_payroll_distributions');
        Schema::dropIfExists('remuneration_payroll_lines');
        Schema::dropIfExists('remuneration_payrolls');
        Schema::dropIfExists('remuneration_movements');
        Schema::dropIfExists('remuneration_employee_concepts');
        Schema::dropIfExists('remuneration_concepts');
        Schema::dropIfExists('remuneration_contract_settings');
        Schema::dropIfExists('remuneration_employee_profiles');
        Schema::dropIfExists('remuneration_legal_parameters');
        Schema::dropIfExists('remuneration_periods');
    }
};
