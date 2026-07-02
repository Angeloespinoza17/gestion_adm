<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('type', 80)->default('operativo')->index();
            $table->string('responsible_name')->nullable();
            $table->unsignedSmallInteger('valid_year')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('accounting_funding_sources', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('category', 80)->default('subvencion')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('accounting_manual_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->index();
            $table->string('name');
            $table->string('version', 50);
            $table->date('publication_date')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_current')->default(false)->index();
            $table->string('attachment_path')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['year', 'version'], 'acc_manual_versions_year_version_unique');
        });

        Schema::create('accounting_manual_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_version_id')->constrained('accounting_manual_versions')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('accounting_manual_accounts')->nullOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('type', 50)->index();
            $table->string('category', 100)->nullable()->index();
            $table->string('subcategory', 100)->nullable();
            $table->unsignedTinyInteger('level')->default(1);
            $table->boolean('allows_movements')->default(true)->index();
            $table->boolean('requires_evidence')->default(false);
            $table->boolean('requires_cost_center')->default(false);
            $table->boolean('requires_funding_source')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['manual_version_id', 'code'], 'acc_manual_accounts_version_code_unique');
        });

        Schema::create('accounting_budgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->index();
            $table->string('name');
            $table->string('status', 40)->default('borrador')->index();
            $table->date('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['year', 'name'], 'acc_budgets_year_name_unique');
        });

        Schema::create('accounting_budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('accounting_budgets')->cascadeOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('accounting_cost_centers')->nullOnDelete();
            $table->foreignId('funding_source_id')->nullable()->constrained('accounting_funding_sources')->nullOnDelete();
            $table->foreignId('manual_account_id')->nullable()->constrained('accounting_manual_accounts')->nullOnDelete();
            $table->unsignedTinyInteger('month')->nullable()->index();
            $table->decimal('planned_amount', 14, 2)->default(0);
            $table->decimal('executed_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(
                ['budget_id', 'cost_center_id', 'funding_source_id', 'manual_account_id', 'month'],
                'acc_budget_lines_unique'
            );
        });

        Schema::create('accounting_parties', function (Blueprint $table) {
            $table->id();
            $table->string('party_type', 50)->default('proveedor')->index();
            $table->string('name');
            $table->string('business_name')->nullable();
            $table->string('rut', 20)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('phone', 80)->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('accounting_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name');
            $table->string('account_name');
            $table->string('account_number', 80)->unique();
            $table->string('account_type', 50)->default('corriente')->index();
            $table->decimal('current_balance', 14, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('accounting_bank_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('accounting_bank_accounts')->cascadeOnDelete();
            $table->string('movement_type', 50)->index();
            $table->string('description');
            $table->date('movement_date')->index();
            $table->decimal('amount', 14, 2);
            $table->string('status', 40)->default('pendiente')->index();
            $table->boolean('is_reconciled')->default(false)->index();
            $table->nullableMorphs('referenceable', 'acc_bank_mov_ref_idx');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('accounting_incomes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->date('received_at')->index();
            $table->string('income_type', 80)->index();
            $table->foreignId('party_id')->nullable()->constrained('accounting_parties')->nullOnDelete();
            $table->foreignId('funding_source_id')->nullable()->constrained('accounting_funding_sources')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('accounting_cost_centers')->nullOnDelete();
            $table->foreignId('manual_account_id')->nullable()->constrained('accounting_manual_accounts')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('accounting_bank_accounts')->nullOnDelete();
            $table->string('document_reference')->nullable();
            $table->string('evidence_path')->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('status', 40)->default('confirmado')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('accounting_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->date('expense_date')->index();
            $table->foreignId('party_id')->nullable()->constrained('accounting_parties')->nullOnDelete();
            $table->string('document_type', 50)->default('factura')->index();
            $table->string('document_number')->nullable()->index();
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('exempt_amount', 14, 2)->default(0);
            $table->decimal('withholding_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->foreignId('manual_account_id')->nullable()->constrained('accounting_manual_accounts')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('accounting_cost_centers')->nullOnDelete();
            $table->foreignId('funding_source_id')->nullable()->constrained('accounting_funding_sources')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('accounting_bank_accounts')->nullOnDelete();
            $table->string('payment_method', 50)->nullable()->index();
            $table->string('payment_reference')->nullable();
            $table->string('evidence_path')->nullable();
            $table->string('status', 40)->default('borrador')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['party_id', 'document_type', 'document_number'], 'acc_expense_document_lookup');
        });

        Schema::create('accounting_cash_funds', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('fund_type', 50)->default('caja_chica')->index();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('accounting_cost_centers')->nullOnDelete();
            $table->foreignId('funding_source_id')->nullable()->constrained('accounting_funding_sources')->nullOnDelete();
            $table->decimal('initial_amount', 14, 2)->default(0);
            $table->decimal('current_balance', 14, 2)->default(0);
            $table->date('delivered_at')->nullable();
            $table->date('due_at')->nullable();
            $table->string('status', 40)->default('abierto')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('accounting_renderings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('period_label', 80)->index();
            $table->string('status', 40)->default('borrador')->index();
            $table->date('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('accounting_rendering_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rendering_id')->constrained('accounting_renderings')->cascadeOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained('accounting_expenses')->nullOnDelete();
            $table->foreignId('income_id')->nullable()->constrained('accounting_incomes')->nullOnDelete();
            $table->foreignId('manual_account_id')->nullable()->constrained('accounting_manual_accounts')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('accounting_cost_centers')->nullOnDelete();
            $table->foreignId('funding_source_id')->nullable()->constrained('accounting_funding_sources')->nullOnDelete();
            $table->decimal('amount', 14, 2)->default(0);
            $table->date('rendered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('accounting_payables', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->foreignId('party_id')->nullable()->constrained('accounting_parties')->nullOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained('accounting_expenses')->nullOnDelete();
            $table->date('due_date')->index();
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('status', 40)->default('pendiente')->index();
            $table->string('priority', 40)->default('media')->index();
            $table->foreignId('cost_center_id')->nullable()->constrained('accounting_cost_centers')->nullOnDelete();
            $table->foreignId('funding_source_id')->nullable()->constrained('accounting_funding_sources')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('accounting_cheques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('accounting_bank_accounts')->cascadeOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained('accounting_expenses')->nullOnDelete();
            $table->foreignId('payable_id')->nullable()->constrained('accounting_payables')->nullOnDelete();
            $table->string('check_number', 80);
            $table->string('beneficiary_name');
            $table->decimal('amount', 14, 2)->default(0);
            $table->date('issued_at')->nullable()->index();
            $table->date('cashed_at')->nullable()->index();
            $table->string('status', 40)->default('emitido')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['bank_account_id', 'check_number'], 'acc_cheques_bank_number_unique');
        });

        Schema::create('accounting_tax_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->index();
            $table->unsignedTinyInteger('month')->index();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->date('filed_at')->nullable();
            $table->string('status', 40)->default('pendiente')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['year', 'month'], 'acc_tax_periods_year_month_unique');
        });

        Schema::create('accounting_tax_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->index();
            $table->string('code', 20)->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['year', 'code'], 'acc_tax_codes_year_code_unique');
        });

        Schema::create('accounting_f29_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_period_id')->constrained('accounting_tax_periods')->cascadeOnDelete();
            $table->string('status', 40)->default('en_preparacion')->index();
            $table->decimal('vat_debit', 14, 2)->default(0);
            $table->decimal('vat_credit', 14, 2)->default(0);
            $table->decimal('ppm_amount', 14, 2)->default(0);
            $table->decimal('withholding_amount', 14, 2)->default(0);
            $table->json('other_taxes')->nullable();
            $table->string('receipt_number')->nullable();
            $table->date('filed_at')->nullable();
            $table->date('paid_at')->nullable();
            $table->string('attachment_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('accounting_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_number', 50)->unique();
            $table->date('entry_date')->index();
            $table->string('status', 40)->default('borrador')->index();
            $table->string('description');
            $table->nullableMorphs('sourceable', 'acc_journal_source_idx');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('accounting_journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('accounting_journal_entries')->cascadeOnDelete();
            $table->foreignId('manual_account_id')->nullable()->constrained('accounting_manual_accounts')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('accounting_cost_centers')->nullOnDelete();
            $table->foreignId('funding_source_id')->nullable()->constrained('accounting_funding_sources')->nullOnDelete();
            $table->string('line_description')->nullable();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('accounting_declaration_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('category', 50)->default('ingresos')->index();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('accounting_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('declaration_type_id')->constrained('accounting_declaration_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('year')->index();
            $table->string('period_label', 80)->nullable()->index();
            $table->string('status', 40)->default('pendiente')->index();
            $table->foreignId('party_id')->nullable()->constrained('accounting_parties')->nullOnDelete();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->json('payload')->nullable();
            $table->date('filed_at')->nullable();
            $table->string('attachment_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('accounting_documents', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable', 'acc_documents_doc_idx');
            $table->string('label');
            $table->string('path');
            $table->json('metadata')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('accounting_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('auditable', 'acc_audit_logs_auditable_idx');
            $table->string('action', 40)->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 64)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_audit_logs');
        Schema::dropIfExists('accounting_documents');
        Schema::dropIfExists('accounting_declarations');
        Schema::dropIfExists('accounting_declaration_types');
        Schema::dropIfExists('accounting_journal_entry_lines');
        Schema::dropIfExists('accounting_journal_entries');
        Schema::dropIfExists('accounting_f29_declarations');
        Schema::dropIfExists('accounting_tax_codes');
        Schema::dropIfExists('accounting_tax_periods');
        Schema::dropIfExists('accounting_cheques');
        Schema::dropIfExists('accounting_payables');
        Schema::dropIfExists('accounting_rendering_items');
        Schema::dropIfExists('accounting_renderings');
        Schema::dropIfExists('accounting_cash_funds');
        Schema::dropIfExists('accounting_expenses');
        Schema::dropIfExists('accounting_incomes');
        Schema::dropIfExists('accounting_bank_movements');
        Schema::dropIfExists('accounting_bank_accounts');
        Schema::dropIfExists('accounting_parties');
        Schema::dropIfExists('accounting_budget_lines');
        Schema::dropIfExists('accounting_budgets');
        Schema::dropIfExists('accounting_manual_accounts');
        Schema::dropIfExists('accounting_manual_versions');
        Schema::dropIfExists('accounting_funding_sources');
        Schema::dropIfExists('accounting_cost_centers');
    }
};
