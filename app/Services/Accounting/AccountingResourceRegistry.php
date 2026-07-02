<?php

namespace App\Services\Accounting;

use App\Models\Accounting\AccountingBankAccount;
use App\Models\Accounting\AccountingBankMovement;
use App\Models\Accounting\AccountingBudget;
use App\Models\Accounting\AccountingBudgetLine;
use App\Models\Accounting\AccountingCashFund;
use App\Models\Accounting\AccountingCheque;
use App\Models\Accounting\AccountingCostCenter;
use App\Models\Accounting\AccountingDeclaration;
use App\Models\Accounting\AccountingDeclarationType;
use App\Models\Accounting\AccountingExpense;
use App\Models\Accounting\AccountingF29Declaration;
use App\Models\Accounting\AccountingFundingSource;
use App\Models\Accounting\AccountingIncome;
use App\Models\Accounting\AccountingJournalEntry;
use App\Models\Accounting\AccountingJournalEntryLine;
use App\Models\Accounting\AccountingManualAccount;
use App\Models\Accounting\AccountingManualVersion;
use App\Models\Accounting\AccountingParty;
use App\Models\Accounting\AccountingPayable;
use App\Models\Accounting\AccountingRendering;
use App\Models\Accounting\AccountingRenderingItem;
use App\Models\Accounting\AccountingTaxCode;
use App\Models\Accounting\AccountingTaxPeriod;
use Illuminate\Validation\Rule;

class AccountingResourceRegistry
{
    public function __construct(
        private readonly AccountingAccessService $accessService,
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return [
            'cost-centers' => [
                'model' => AccountingCostCenter::class,
                'view_permission' => AccountingAccessService::VIEW_PERMISSION,
                'manage_permission' => AccountingAccessService::COST_CENTER_PERMISSION,
                'search' => ['code', 'name', 'description', 'responsible_name'],
                'filters' => ['type', 'is_active', 'valid_year'],
                'order_by' => 'name',
                'rules' => fn (?int $id) => [
                    'code' => ['required', 'string', 'max:40', Rule::unique('accounting_cost_centers', 'code')->ignore($id)],
                    'name' => ['required', 'string', 'max:191'],
                    'type' => ['required', 'string', 'max:80'],
                    'responsible_name' => ['nullable', 'string', 'max:191'],
                    'valid_year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
                    'is_active' => ['nullable', 'boolean'],
                    'description' => ['nullable', 'string'],
                ],
            ],
            'funding-sources' => [
                'model' => AccountingFundingSource::class,
                'view_permission' => AccountingAccessService::VIEW_PERMISSION,
                'manage_permission' => AccountingAccessService::FUNDING_PANEL_PERMISSION,
                'search' => ['code', 'name', 'description', 'category'],
                'filters' => ['category', 'is_active'],
                'order_by' => 'name',
                'rules' => fn (?int $id) => [
                    'code' => ['required', 'string', 'max:40', Rule::unique('accounting_funding_sources', 'code')->ignore($id)],
                    'name' => ['required', 'string', 'max:191'],
                    'category' => ['required', 'string', 'max:80'],
                    'is_active' => ['nullable', 'boolean'],
                    'description' => ['nullable', 'string'],
                ],
            ],
            'manual-versions' => [
                'model' => AccountingManualVersion::class,
                'view_permission' => AccountingAccessService::VIEW_PERMISSION,
                'manage_permission' => AccountingAccessService::MANUAL_PERMISSION,
                'search' => ['name', 'version'],
                'filters' => ['year', 'is_active', 'is_current'],
                'order_by' => 'year',
                'with' => ['accounts'],
                'rules' => fn (?int $id) => [
                    'year' => ['required', 'integer', 'min:2020', 'max:2100'],
                    'name' => ['required', 'string', 'max:191'],
                    'version' => ['required', 'string', 'max:50'],
                    'publication_date' => ['nullable', 'date'],
                    'valid_from' => ['nullable', 'date'],
                    'valid_until' => ['nullable', 'date'],
                    'is_active' => ['nullable', 'boolean'],
                    'is_current' => ['nullable', 'boolean'],
                    'attachment_path' => ['nullable', 'string', 'max:191'],
                    'observations' => ['nullable', 'string'],
                ],
            ],
            'manual-accounts' => [
                'model' => AccountingManualAccount::class,
                'view_permission' => AccountingAccessService::VIEW_PERMISSION,
                'manage_permission' => AccountingAccessService::MANUAL_PERMISSION,
                'search' => ['code', 'name', 'description', 'category', 'subcategory'],
                'filters' => ['manual_version_id', 'type', 'allows_movements', 'is_active'],
                'order_by' => 'code',
                'with' => ['version:id,name,version,year', 'parent:id,code,name'],
                'rules' => fn (?int $id) => [
                    'manual_version_id' => ['required', 'integer', 'exists:accounting_manual_versions,id'],
                    'parent_id' => ['nullable', 'integer', 'exists:accounting_manual_accounts,id'],
                    'code' => ['required', 'string', 'max:50'],
                    'name' => ['required', 'string', 'max:191'],
                    'type' => ['required', 'string', 'max:50'],
                    'category' => ['nullable', 'string', 'max:100'],
                    'subcategory' => ['nullable', 'string', 'max:100'],
                    'level' => ['nullable', 'integer', 'min:1', 'max:9'],
                    'allows_movements' => ['nullable', 'boolean'],
                    'requires_evidence' => ['nullable', 'boolean'],
                    'requires_cost_center' => ['nullable', 'boolean'],
                    'requires_funding_source' => ['nullable', 'boolean'],
                    'is_active' => ['nullable', 'boolean'],
                    'description' => ['nullable', 'string'],
                ],
            ],
            'budgets' => [
                'model' => AccountingBudget::class,
                'view_permission' => AccountingAccessService::BUDGET_VIEW_PERMISSION,
                'manage_permission' => AccountingAccessService::BUDGET_CREATE_PERMISSION,
                'search' => ['name', 'status'],
                'filters' => ['year', 'status'],
                'order_by' => 'year',
                'with' => ['lines.costCenter:id,name', 'lines.fundingSource:id,name', 'lines.manualAccount:id,code,name'],
                'rules' => fn (?int $id) => [
                    'year' => ['required', 'integer', 'min:2020', 'max:2100'],
                    'name' => ['required', 'string', 'max:191'],
                    'status' => ['required', 'string', 'max:40'],
                    'approved_at' => ['nullable', 'date'],
                    'approved_by' => ['nullable', 'integer', 'exists:users,id'],
                    'notes' => ['nullable', 'string'],
                ],
            ],
            'budget-lines' => [
                'model' => AccountingBudgetLine::class,
                'view_permission' => AccountingAccessService::BUDGET_VIEW_PERMISSION,
                'manage_permission' => AccountingAccessService::BUDGET_CREATE_PERMISSION,
                'filters' => ['budget_id', 'month', 'cost_center_id', 'funding_source_id'],
                'order_by' => 'id',
                'with' => ['budget:id,name,year', 'costCenter:id,name', 'fundingSource:id,name', 'manualAccount:id,code,name'],
                'rules' => fn (?int $id) => [
                    'budget_id' => ['required', 'integer', 'exists:accounting_budgets,id'],
                    'cost_center_id' => ['nullable', 'integer', 'exists:accounting_cost_centers,id'],
                    'funding_source_id' => ['nullable', 'integer', 'exists:accounting_funding_sources,id'],
                    'manual_account_id' => ['nullable', 'integer', 'exists:accounting_manual_accounts,id'],
                    'month' => ['nullable', 'integer', 'min:1', 'max:12'],
                    'planned_amount' => ['required', 'numeric', 'min:0'],
                    'executed_amount' => ['nullable', 'numeric', 'min:0'],
                    'notes' => ['nullable', 'string'],
                ],
            ],
            'parties' => [
                'model' => AccountingParty::class,
                'view_permission' => AccountingAccessService::VIEW_PERMISSION,
                'manage_permission' => AccountingAccessService::PAYMENTS_PERMISSION,
                'search' => ['name', 'business_name', 'rut', 'email'],
                'filters' => ['party_type', 'is_active'],
                'order_by' => 'name',
                'rules' => fn (?int $id) => [
                    'party_type' => ['required', 'string', 'max:50'],
                    'name' => ['required', 'string', 'max:191'],
                    'business_name' => ['nullable', 'string', 'max:191'],
                    'rut' => ['nullable', 'string', 'max:20'],
                    'email' => ['nullable', 'email', 'max:191'],
                    'phone' => ['nullable', 'string', 'max:80'],
                    'address' => ['nullable', 'string', 'max:191'],
                    'is_active' => ['nullable', 'boolean'],
                ],
            ],
            'bank-accounts' => [
                'model' => AccountingBankAccount::class,
                'view_permission' => AccountingAccessService::RECONCILIATION_PERMISSION,
                'manage_permission' => AccountingAccessService::RECONCILIATION_PERMISSION,
                'search' => ['bank_name', 'account_name', 'account_number'],
                'filters' => ['account_type', 'is_active'],
                'order_by' => 'bank_name',
                'rules' => fn (?int $id) => [
                    'bank_name' => ['required', 'string', 'max:191'],
                    'account_name' => ['required', 'string', 'max:191'],
                    'account_number' => ['required', 'string', 'max:80', Rule::unique('accounting_bank_accounts', 'account_number')->ignore($id)],
                    'account_type' => ['required', 'string', 'max:50'],
                    'current_balance' => ['nullable', 'numeric'],
                    'is_active' => ['nullable', 'boolean'],
                    'notes' => ['nullable', 'string'],
                ],
            ],
            'bank-movements' => [
                'model' => AccountingBankMovement::class,
                'view_permission' => AccountingAccessService::RECONCILIATION_PERMISSION,
                'manage_permission' => AccountingAccessService::RECONCILIATION_PERMISSION,
                'search' => ['description', 'status', 'movement_type'],
                'filters' => ['bank_account_id', 'movement_type', 'status', 'is_reconciled'],
                'order_by' => 'movement_date',
                'with' => ['bankAccount:id,bank_name,account_number'],
                'rules' => fn (?int $id) => [
                    'bank_account_id' => ['required', 'integer', 'exists:accounting_bank_accounts,id'],
                    'movement_type' => ['required', 'string', 'max:50'],
                    'description' => ['required', 'string', 'max:191'],
                    'movement_date' => ['required', 'date'],
                    'amount' => ['required', 'numeric'],
                    'status' => ['required', 'string', 'max:40'],
                    'is_reconciled' => ['nullable', 'boolean'],
                    'notes' => ['nullable', 'string'],
                ],
            ],
            'incomes' => [
                'model' => AccountingIncome::class,
                'view_permission' => AccountingAccessService::VIEW_PERMISSION,
                'manage_permission' => AccountingAccessService::INCOMES_PERMISSION,
                'search' => ['code', 'income_type', 'document_reference', 'status'],
                'filters' => ['funding_source_id', 'cost_center_id', 'manual_account_id', 'status'],
                'order_by' => 'received_at',
                'with' => ['party:id,name', 'fundingSource:id,name', 'costCenter:id,name', 'manualAccount:id,code,name'],
                'rules' => fn (?int $id) => [
                    'code' => ['required', 'string', 'max:50', Rule::unique('accounting_incomes', 'code')->ignore($id)],
                    'received_at' => ['required', 'date'],
                    'income_type' => ['required', 'string', 'max:80'],
                    'party_id' => ['nullable', 'integer', 'exists:accounting_parties,id'],
                    'funding_source_id' => ['nullable', 'integer', 'exists:accounting_funding_sources,id'],
                    'cost_center_id' => ['nullable', 'integer', 'exists:accounting_cost_centers,id'],
                    'manual_account_id' => ['nullable', 'integer', 'exists:accounting_manual_accounts,id'],
                    'bank_account_id' => ['nullable', 'integer', 'exists:accounting_bank_accounts,id'],
                    'document_reference' => ['nullable', 'string', 'max:191'],
                    'evidence_path' => ['nullable', 'string', 'max:191'],
                    'amount' => ['required', 'numeric', 'min:0'],
                    'status' => ['required', 'string', 'max:40'],
                    'notes' => ['nullable', 'string'],
                ],
            ],
            'expenses' => [
                'model' => AccountingExpense::class,
                'view_permission' => AccountingAccessService::VIEW_PERMISSION,
                'manage_permission' => AccountingAccessService::EXPENSES_PERMISSION,
                'search' => ['code', 'document_type', 'document_number', 'status', 'payment_reference'],
                'filters' => ['document_type', 'cost_center_id', 'funding_source_id', 'manual_account_id', 'status'],
                'order_by' => 'expense_date',
                'with' => ['party:id,name', 'fundingSource:id,name', 'costCenter:id,name', 'manualAccount:id,code,name'],
                'rules' => fn (?int $id) => [
                    'code' => ['required', 'string', 'max:50', Rule::unique('accounting_expenses', 'code')->ignore($id)],
                    'expense_date' => ['required', 'date'],
                    'party_id' => ['nullable', 'integer', 'exists:accounting_parties,id'],
                    'document_type' => ['required', 'string', 'max:50'],
                    'document_number' => ['nullable', 'string', 'max:191'],
                    'net_amount' => ['nullable', 'numeric', 'min:0'],
                    'tax_amount' => ['nullable', 'numeric', 'min:0'],
                    'exempt_amount' => ['nullable', 'numeric', 'min:0'],
                    'withholding_amount' => ['nullable', 'numeric', 'min:0'],
                    'total_amount' => ['required', 'numeric', 'min:0'],
                    'manual_account_id' => ['nullable', 'integer', 'exists:accounting_manual_accounts,id'],
                    'cost_center_id' => ['nullable', 'integer', 'exists:accounting_cost_centers,id'],
                    'funding_source_id' => ['nullable', 'integer', 'exists:accounting_funding_sources,id'],
                    'bank_account_id' => ['nullable', 'integer', 'exists:accounting_bank_accounts,id'],
                    'payment_method' => ['nullable', 'string', 'max:50'],
                    'payment_reference' => ['nullable', 'string', 'max:191'],
                    'evidence_path' => ['nullable', 'string', 'max:191'],
                    'status' => ['required', 'string', 'max:40'],
                    'notes' => ['nullable', 'string'],
                ],
            ],
            'cash-funds' => [
                'model' => AccountingCashFund::class,
                'view_permission' => AccountingAccessService::VIEW_PERMISSION,
                'manage_permission' => AccountingAccessService::CASH_FUND_PERMISSION,
                'search' => ['code', 'fund_type', 'status'],
                'filters' => ['fund_type', 'responsible_user_id', 'status'],
                'order_by' => 'delivered_at',
                'with' => ['responsible:id,name', 'costCenter:id,name', 'fundingSource:id,name'],
                'rules' => fn (?int $id) => [
                    'code' => ['required', 'string', 'max:50', Rule::unique('accounting_cash_funds', 'code')->ignore($id)],
                    'fund_type' => ['required', 'string', 'max:50'],
                    'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
                    'cost_center_id' => ['nullable', 'integer', 'exists:accounting_cost_centers,id'],
                    'funding_source_id' => ['nullable', 'integer', 'exists:accounting_funding_sources,id'],
                    'initial_amount' => ['required', 'numeric', 'min:0'],
                    'current_balance' => ['nullable', 'numeric'],
                    'delivered_at' => ['nullable', 'date'],
                    'due_at' => ['nullable', 'date'],
                    'status' => ['required', 'string', 'max:40'],
                    'notes' => ['nullable', 'string'],
                ],
            ],
            'renderings' => [
                'model' => AccountingRendering::class,
                'view_permission' => AccountingAccessService::FUNDS_RENDER_PERMISSION,
                'manage_permission' => AccountingAccessService::FUNDS_RENDER_PERMISSION,
                'search' => ['code', 'period_label', 'status'],
                'filters' => ['status'],
                'order_by' => 'period_label',
                'with' => ['items.expense:id,code,total_amount', 'items.income:id,code,amount'],
                'rules' => fn (?int $id) => [
                    'code' => ['required', 'string', 'max:50', Rule::unique('accounting_renderings', 'code')->ignore($id)],
                    'period_label' => ['required', 'string', 'max:80'],
                    'status' => ['required', 'string', 'max:40'],
                    'reviewed_at' => ['nullable', 'date'],
                    'reviewed_by' => ['nullable', 'integer', 'exists:users,id'],
                    'notes' => ['nullable', 'string'],
                ],
            ],
            'rendering-items' => [
                'model' => AccountingRenderingItem::class,
                'view_permission' => AccountingAccessService::FUNDS_RENDER_PERMISSION,
                'manage_permission' => AccountingAccessService::FUNDS_RENDER_PERMISSION,
                'filters' => ['rendering_id', 'expense_id', 'income_id'],
                'order_by' => 'id',
                'with' => ['rendering:id,code', 'expense:id,code', 'income:id,code', 'manualAccount:id,code,name'],
                'rules' => fn (?int $id) => [
                    'rendering_id' => ['required', 'integer', 'exists:accounting_renderings,id'],
                    'expense_id' => ['nullable', 'integer', 'exists:accounting_expenses,id'],
                    'income_id' => ['nullable', 'integer', 'exists:accounting_incomes,id'],
                    'manual_account_id' => ['nullable', 'integer', 'exists:accounting_manual_accounts,id'],
                    'cost_center_id' => ['nullable', 'integer', 'exists:accounting_cost_centers,id'],
                    'funding_source_id' => ['nullable', 'integer', 'exists:accounting_funding_sources,id'],
                    'amount' => ['required', 'numeric', 'min:0'],
                    'rendered_at' => ['nullable', 'date'],
                    'notes' => ['nullable', 'string'],
                ],
            ],
            'payables' => [
                'model' => AccountingPayable::class,
                'view_permission' => AccountingAccessService::PAYMENTS_PERMISSION,
                'manage_permission' => AccountingAccessService::PAYMENTS_PERMISSION,
                'search' => ['code', 'status', 'priority'],
                'filters' => ['status', 'priority', 'party_id', 'responsible_user_id'],
                'order_by' => 'due_date',
                'with' => ['party:id,name', 'expense:id,code,total_amount'],
                'rules' => fn (?int $id) => [
                    'code' => ['required', 'string', 'max:50', Rule::unique('accounting_payables', 'code')->ignore($id)],
                    'party_id' => ['nullable', 'integer', 'exists:accounting_parties,id'],
                    'expense_id' => ['nullable', 'integer', 'exists:accounting_expenses,id'],
                    'due_date' => ['required', 'date'],
                    'amount' => ['required', 'numeric', 'min:0'],
                    'status' => ['required', 'string', 'max:40'],
                    'priority' => ['required', 'string', 'max:40'],
                    'cost_center_id' => ['nullable', 'integer', 'exists:accounting_cost_centers,id'],
                    'funding_source_id' => ['nullable', 'integer', 'exists:accounting_funding_sources,id'],
                    'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
                    'notes' => ['nullable', 'string'],
                ],
            ],
            'cheques' => [
                'model' => AccountingCheque::class,
                'view_permission' => AccountingAccessService::CHEQUES_PERMISSION,
                'manage_permission' => AccountingAccessService::CHEQUES_PERMISSION,
                'search' => ['check_number', 'beneficiary_name', 'status'],
                'filters' => ['bank_account_id', 'status'],
                'order_by' => 'issued_at',
                'with' => ['bankAccount:id,bank_name,account_number', 'expense:id,code', 'payable:id,code'],
                'rules' => fn (?int $id) => [
                    'bank_account_id' => ['required', 'integer', 'exists:accounting_bank_accounts,id'],
                    'expense_id' => ['nullable', 'integer', 'exists:accounting_expenses,id'],
                    'payable_id' => ['nullable', 'integer', 'exists:accounting_payables,id'],
                    'check_number' => ['required', 'string', 'max:80'],
                    'beneficiary_name' => ['required', 'string', 'max:191'],
                    'amount' => ['required', 'numeric', 'min:0'],
                    'issued_at' => ['nullable', 'date'],
                    'cashed_at' => ['nullable', 'date'],
                    'status' => ['required', 'string', 'max:40'],
                    'notes' => ['nullable', 'string'],
                ],
            ],
            'tax-periods' => [
                'model' => AccountingTaxPeriod::class,
                'view_permission' => AccountingAccessService::F29_PERMISSION,
                'manage_permission' => AccountingAccessService::F29_PERMISSION,
                'search' => ['status'],
                'filters' => ['year', 'month', 'status'],
                'order_by' => 'year',
                'with' => ['f29Declaration'],
                'rules' => fn (?int $id) => [
                    'year' => ['required', 'integer', 'min:2020', 'max:2100'],
                    'month' => ['required', 'integer', 'min:1', 'max:12'],
                    'starts_at' => ['nullable', 'date'],
                    'ends_at' => ['nullable', 'date'],
                    'filed_at' => ['nullable', 'date'],
                    'status' => ['required', 'string', 'max:40'],
                    'notes' => ['nullable', 'string'],
                ],
            ],
            'tax-codes' => [
                'model' => AccountingTaxCode::class,
                'view_permission' => AccountingAccessService::F29_PERMISSION,
                'manage_permission' => AccountingAccessService::F29_PERMISSION,
                'search' => ['code', 'name'],
                'filters' => ['year', 'is_active'],
                'order_by' => 'code',
                'rules' => fn (?int $id) => [
                    'year' => ['required', 'integer', 'min:2020', 'max:2100'],
                    'code' => ['required', 'string', 'max:20'],
                    'name' => ['required', 'string', 'max:191'],
                    'description' => ['nullable', 'string'],
                    'is_active' => ['nullable', 'boolean'],
                ],
            ],
            'f29-declarations' => [
                'model' => AccountingF29Declaration::class,
                'view_permission' => AccountingAccessService::F29_PERMISSION,
                'manage_permission' => AccountingAccessService::F29_PERMISSION,
                'search' => ['status', 'receipt_number'],
                'filters' => ['tax_period_id', 'status'],
                'order_by' => 'id',
                'with' => ['taxPeriod:id,year,month,status'],
                'rules' => fn (?int $id) => [
                    'tax_period_id' => ['required', 'integer', 'exists:accounting_tax_periods,id'],
                    'status' => ['required', 'string', 'max:40'],
                    'vat_debit' => ['nullable', 'numeric', 'min:0'],
                    'vat_credit' => ['nullable', 'numeric', 'min:0'],
                    'ppm_amount' => ['nullable', 'numeric', 'min:0'],
                    'withholding_amount' => ['nullable', 'numeric', 'min:0'],
                    'other_taxes' => ['nullable', 'array'],
                    'receipt_number' => ['nullable', 'string', 'max:191'],
                    'filed_at' => ['nullable', 'date'],
                    'paid_at' => ['nullable', 'date'],
                    'attachment_path' => ['nullable', 'string', 'max:191'],
                    'notes' => ['nullable', 'string'],
                ],
            ],
            'journal-entries' => [
                'model' => AccountingJournalEntry::class,
                'view_permission' => AccountingAccessService::BALANCE_PERMISSION,
                'manage_permission' => AccountingAccessService::ADMIN_PERMISSION,
                'search' => ['entry_number', 'description', 'status'],
                'filters' => ['status'],
                'order_by' => 'entry_date',
                'with' => ['lines.manualAccount:id,code,name'],
                'rules' => fn (?int $id) => [
                    'entry_number' => ['required', 'string', 'max:50', Rule::unique('accounting_journal_entries', 'entry_number')->ignore($id)],
                    'entry_date' => ['required', 'date'],
                    'status' => ['required', 'string', 'max:40'],
                    'description' => ['required', 'string', 'max:191'],
                    'notes' => ['nullable', 'string'],
                ],
            ],
            'journal-entry-lines' => [
                'model' => AccountingJournalEntryLine::class,
                'view_permission' => AccountingAccessService::BALANCE_PERMISSION,
                'manage_permission' => AccountingAccessService::ADMIN_PERMISSION,
                'filters' => ['journal_entry_id', 'manual_account_id'],
                'order_by' => 'id',
                'with' => ['entry:id,entry_number', 'manualAccount:id,code,name', 'costCenter:id,name', 'fundingSource:id,name'],
                'rules' => fn (?int $id) => [
                    'journal_entry_id' => ['required', 'integer', 'exists:accounting_journal_entries,id'],
                    'manual_account_id' => ['nullable', 'integer', 'exists:accounting_manual_accounts,id'],
                    'cost_center_id' => ['nullable', 'integer', 'exists:accounting_cost_centers,id'],
                    'funding_source_id' => ['nullable', 'integer', 'exists:accounting_funding_sources,id'],
                    'line_description' => ['nullable', 'string', 'max:191'],
                    'debit' => ['nullable', 'numeric', 'min:0'],
                    'credit' => ['nullable', 'numeric', 'min:0'],
                ],
            ],
            'declaration-types' => [
                'model' => AccountingDeclarationType::class,
                'view_permission' => AccountingAccessService::DECLARATIONS_PERMISSION,
                'manage_permission' => AccountingAccessService::DECLARATIONS_PERMISSION,
                'search' => ['code', 'name', 'category'],
                'filters' => ['category', 'is_active'],
                'order_by' => 'name',
                'rules' => fn (?int $id) => [
                    'code' => ['required', 'string', 'max:40', Rule::unique('accounting_declaration_types', 'code')->ignore($id)],
                    'name' => ['required', 'string', 'max:191'],
                    'category' => ['required', 'string', 'max:50'],
                    'description' => ['nullable', 'string'],
                    'is_active' => ['nullable', 'boolean'],
                ],
            ],
            'declarations' => [
                'model' => AccountingDeclaration::class,
                'view_permission' => AccountingAccessService::DECLARATIONS_PERMISSION,
                'manage_permission' => AccountingAccessService::DECLARATIONS_PERMISSION,
                'search' => ['period_label', 'status'],
                'filters' => ['declaration_type_id', 'year', 'status'],
                'order_by' => 'year',
                'with' => ['type:id,name,category', 'party:id,name'],
                'rules' => fn (?int $id) => [
                    'declaration_type_id' => ['required', 'integer', 'exists:accounting_declaration_types,id'],
                    'year' => ['required', 'integer', 'min:2020', 'max:2100'],
                    'period_label' => ['nullable', 'string', 'max:80'],
                    'status' => ['required', 'string', 'max:40'],
                    'party_id' => ['nullable', 'integer', 'exists:accounting_parties,id'],
                    'total_amount' => ['nullable', 'numeric', 'min:0'],
                    'payload' => ['nullable', 'array'],
                    'filed_at' => ['nullable', 'date'],
                    'attachment_path' => ['nullable', 'string', 'max:191'],
                    'notes' => ['nullable', 'string'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $resource): array
    {
        $config = $this->all()[$resource] ?? null;

        if (!$config) {
            abort(404, 'Recurso contable no encontrado.');
        }

        return $config;
    }

    public function permissionFor(string $resource, string $type = 'view_permission'): string
    {
        return $this->get($resource)[$type] ?? $this->accessService::VIEW_PERMISSION;
    }
}
