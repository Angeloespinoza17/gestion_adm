<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import AccountingHelpButton from "../../components/accounting/help-button.vue";
import { formatAccountingError, money, shortDate } from "../../components/accounting/module-utils";

const navItems = [
  { route: "/contabilidad", key: "dashboard", label: "Dashboard" },
  { route: "/contabilidad/rendiciones", key: "renderings", label: "Rendiciones" },
  { route: "/contabilidad/presupuesto", key: "budget-lines", label: "Presupuesto" },
  { route: "/contabilidad/centros-costo", key: "cost-centers", label: "Centros de costo" },
  { route: "/contabilidad/manual-cuentas", key: "manual-accounts", label: "Manual de cuentas" },
  { route: "/contabilidad/ingresos", key: "incomes", label: "Ingresos" },
  { route: "/contabilidad/egresos", key: "expenses", label: "Egresos" },
  { route: "/contabilidad/caja-chica", key: "cash-funds", label: "Caja chica" },
  { route: "/contabilidad/fondos-rendir", key: "funds-to-render", label: "Fondos por rendir" },
  { route: "/contabilidad/conciliacion", key: "bank-movements", label: "Conciliación" },
  { route: "/contabilidad/subvenciones", key: "funding-sources", label: "Subvenciones" },
  { route: "/contabilidad/cheques", key: "cheques", label: "Cheques" },
  { route: "/contabilidad/facturas", key: "invoices", label: "Facturas" },
  { route: "/contabilidad/boletas-honorarios", key: "honoraries", label: "Boletas" },
  { route: "/contabilidad/flujo-caja", key: "cashflow", label: "Flujo caja" },
  { route: "/contabilidad/cuentas-por-pagar", key: "payables", label: "Cuentas por pagar" },
  { route: "/contabilidad/f29", key: "f29", label: "F29" },
  { route: "/contabilidad/balance", key: "balance", label: "Balance" },
  { route: "/contabilidad/dj-ingresos", key: "dj-income", label: "DJ Ingresos" },
  { route: "/contabilidad/dj-arriendo", key: "dj-rental", label: "DJ Arriendo" },
  { route: "/contabilidad/declaracion-renta", key: "income-tax", label: "Renta" },
  { route: "/contabilidad/reportes", key: "reports", label: "Reportes" },
];

const statusSelect = (statusKey) => ({ type: "select", statusKey });

const panelDefinitions = {
  dashboard: {
    route: "/contabilidad",
    kind: "dashboard",
    title: "Dashboard Contabilidad",
    subtitle: "Control interno de presupuesto, ejecución, tesorería y cumplimiento tributario del establecimiento.",
    help: "Este dashboard centraliza el control contable interno. No reemplaza SII, Supereduc ni contabilidad oficial externa.",
  },
  renderings: {
    route: "/contabilidad/rendiciones",
    kind: "resource",
    resource: "renderings",
    title: "Rendición de Cuentas",
    subtitle: "Períodos internos de rendición, observaciones y estados de revisión.",
    help: "Aquí se controlan rendiciones internas y su trazabilidad. La presentación oficial debe realizarse en la plataforma correspondiente cuando aplique.",
    fields: [
      { key: "code", label: "Código", type: "text", required: true },
      { key: "period_label", label: "Período", type: "text", required: true },
      { key: "status", label: "Estado", required: true, ...statusSelect("records") },
      { key: "reviewed_at", label: "Fecha revisión", type: "date" },
      { key: "reviewed_by", label: "Revisó", type: "select", optionsKey: "users" },
      { key: "notes", label: "Observaciones", type: "textarea" },
    ],
    columns: [
      { key: "code", label: "Código" },
      { key: "period_label", label: "Período" },
      { key: "status", label: "Estado", format: "badge" },
      { key: "reviewed_at", label: "Revisión", format: "date" },
      { key: "notes", label: "Observaciones" },
    ],
  },
  "budget-lines": {
    route: "/contabilidad/presupuesto",
    kind: "resource",
    resource: "budget-lines",
    secondaryResource: "budgets",
    title: "Presupuesto Anual",
    subtitle: "Líneas presupuestarias por centro de costo, subvención y cuenta contable.",
    help: "Permite comparar presupuesto planificado y ejecución real por centro de costo, fuente y cuenta.",
    fields: [
      { key: "budget_id", label: "Presupuesto", type: "select", optionsKey: "budgets", required: true, labelKey: "name" },
      { key: "cost_center_id", label: "Centro de costo", type: "select", optionsKey: "cost_centers", required: true },
      { key: "funding_source_id", label: "Fuente", type: "select", optionsKey: "funding_sources", required: true },
      { key: "manual_account_id", label: "Cuenta", type: "select", optionsKey: "manual_accounts", required: true, labelFormatter: (item) => `${item.code} - ${item.name}` },
      { key: "month", label: "Mes", type: "number" },
      { key: "planned_amount", label: "Planificado", type: "number", required: true },
      { key: "executed_amount", label: "Ejecutado", type: "number" },
      { key: "notes", label: "Observaciones", type: "textarea" },
    ],
    columns: [
      { key: "budget.name", label: "Presupuesto" },
      { key: "cost_center_id", label: "Centro", format: "lookup", lookupKey: "cost_centers" },
      { key: "funding_source_id", label: "Fuente", format: "lookup", lookupKey: "funding_sources" },
      { key: "manual_account_id", label: "Cuenta", format: "lookup", lookupKey: "manual_accounts", lookupFormatter: (item) => `${item.code} - ${item.name}` },
      { key: "planned_amount", label: "Plan", format: "currency" },
      { key: "executed_amount", label: "Ejecutado", format: "currency" },
    ],
  },
  "cost-centers": {
    route: "/contabilidad/centros-costo",
    kind: "resource",
    resource: "cost-centers",
    title: "Centros de Costo",
    subtitle: "Catálogo de áreas responsables para distribuir presupuesto, gastos y control de ejecución.",
    help: "Los centros de costo permiten asignar presupuesto, gastos y responsables administrativos de forma trazable.",
    fields: [
      { key: "code", label: "Código", type: "text", required: true },
      { key: "name", label: "Nombre", type: "text", required: true },
      { key: "type", label: "Tipo", type: "select", staticOptions: ["operativo", "academico", "administrativo", "programa", "subvencion"], required: true },
      { key: "responsible_name", label: "Responsable", type: "text" },
      { key: "valid_year", label: "Año vigencia", type: "number" },
      { key: "is_active", label: "Activo", type: "checkbox" },
      { key: "description", label: "Descripción", type: "textarea" },
    ],
    columns: [
      { key: "code", label: "Código" },
      { key: "name", label: "Nombre" },
      { key: "type", label: "Tipo" },
      { key: "responsible_name", label: "Responsable" },
      { key: "valid_year", label: "Vigencia" },
      { key: "is_active", label: "Activo", format: "boolean" },
    ],
  },
  "manual-accounts": {
    route: "/contabilidad/manual-cuentas",
    kind: "resource",
    resource: "manual-accounts",
    secondaryResource: "manual-versions",
    title: "Manual de Cuentas",
    subtitle: "Cuentas contables asociadas a una versión vigente del manual interno de rendición.",
    help: "Cada cuenta debe pertenecer a una versión del manual. Desde aquí se define exigencia de respaldo, centro de costo y fuente.",
    fields: [
      { key: "manual_version_id", label: "Versión", type: "select", optionsKey: "manual_versions", required: true, labelFormatter: (item) => `${item.year} - ${item.version}` },
      { key: "parent_id", label: "Cuenta padre", type: "select", optionsKey: "manual_accounts", labelFormatter: (item) => `${item.code} - ${item.name}` },
      { key: "code", label: "Código", type: "text", required: true },
      { key: "name", label: "Nombre", type: "text", required: true },
      { key: "type", label: "Tipo", type: "select", staticOptions: ["ingreso", "egreso", "activo", "pasivo", "patrimonio", "orden"], required: true },
      { key: "category", label: "Categoría", type: "text" },
      { key: "level", label: "Nivel", type: "number" },
      { key: "allows_movements", label: "Permite movimientos", type: "checkbox" },
      { key: "requires_evidence", label: "Requiere respaldo", type: "checkbox" },
      { key: "requires_cost_center", label: "Requiere centro", type: "checkbox" },
      { key: "requires_funding_source", label: "Requiere fuente", type: "checkbox" },
      { key: "is_active", label: "Activa", type: "checkbox" },
      { key: "description", label: "Descripción", type: "textarea" },
    ],
    columns: [
      { key: "code", label: "Código" },
      { key: "name", label: "Nombre" },
      { key: "type", label: "Tipo", format: "badge" },
      { key: "category", label: "Categoría" },
      { key: "level", label: "Nivel" },
      { key: "requires_cost_center", label: "Centro", format: "boolean" },
      { key: "requires_funding_source", label: "Fuente", format: "boolean" },
    ],
  },
  incomes: {
    route: "/contabilidad/ingresos",
    kind: "resource",
    resource: "incomes",
    title: "Ingresos",
    subtitle: "Registro de ingresos por tipo, subvención, centro de costo y cuenta asociada.",
    help: "Los ingresos alimentan dashboard, flujo de caja, subvenciones, conciliación y balance.",
    fields: [
      { key: "code", label: "Código", type: "text", required: true },
      { key: "received_at", label: "Fecha", type: "date", required: true },
      { key: "income_type", label: "Tipo ingreso", type: "text", required: true },
      { key: "funding_source_id", label: "Fuente", type: "select", optionsKey: "funding_sources", required: true },
      { key: "cost_center_id", label: "Centro de costo", type: "select", optionsKey: "cost_centers" },
      { key: "manual_account_id", label: "Cuenta", type: "select", optionsKey: "manual_accounts", labelFormatter: (item) => `${item.code} - ${item.name}`, required: true },
      { key: "bank_account_id", label: "Cuenta bancaria", type: "select", optionsKey: "bank_accounts", labelFormatter: (item) => `${item.bank_name} - ${item.account_number}` },
      { key: "document_reference", label: "Documento", type: "text" },
      { key: "amount", label: "Monto", type: "number", required: true },
      { key: "status", label: "Estado", required: true, ...statusSelect("incomes") },
      { key: "notes", label: "Observaciones", type: "textarea" },
    ],
    columns: [
      { key: "code", label: "Código" },
      { key: "received_at", label: "Fecha", format: "date" },
      { key: "income_type", label: "Tipo" },
      { key: "funding_source_id", label: "Fuente", format: "lookup", lookupKey: "funding_sources" },
      { key: "amount", label: "Monto", format: "currency" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  expenses: {
    route: "/contabilidad/egresos",
    kind: "resource",
    resource: "expenses",
    title: "Egresos y Pagos",
    subtitle: "Registro de facturas, boletas y pagos con imputación a cuenta, centro y fuente.",
    help: "Este registro consolida egresos, pagos y base documental interna para rendición y control.",
    fields: [
      { key: "code", label: "Código", type: "text", required: true },
      { key: "expense_date", label: "Fecha", type: "date", required: true },
      { key: "party_id", label: "Proveedor / beneficiario", type: "select", optionsKey: "parties", labelFormatter: (item) => item.name },
      { key: "document_type", label: "Tipo documento", type: "select", staticOptions: ["factura", "boleta_honorarios", "boleta", "comprobante", "otro"], required: true },
      { key: "document_number", label: "Número documento", type: "text" },
      { key: "manual_account_id", label: "Cuenta", type: "select", optionsKey: "manual_accounts", labelFormatter: (item) => `${item.code} - ${item.name}`, required: true },
      { key: "cost_center_id", label: "Centro de costo", type: "select", optionsKey: "cost_centers" },
      { key: "funding_source_id", label: "Fuente", type: "select", optionsKey: "funding_sources" },
      { key: "bank_account_id", label: "Cuenta bancaria", type: "select", optionsKey: "bank_accounts", labelFormatter: (item) => `${item.bank_name} - ${item.account_number}` },
      { key: "total_amount", label: "Monto total", type: "number", required: true },
      { key: "payment_method", label: "Forma pago", type: "select", staticOptions: ["transferencia", "cheque", "efectivo", "tarjeta", "otro"] },
      { key: "payment_reference", label: "Referencia pago", type: "text" },
      { key: "status", label: "Estado", required: true, ...statusSelect("expenses") },
      { key: "notes", label: "Observaciones", type: "textarea" },
    ],
    columns: [
      { key: "code", label: "Código" },
      { key: "expense_date", label: "Fecha", format: "date" },
      { key: "document_type", label: "Documento" },
      { key: "document_number", label: "Folio" },
      { key: "total_amount", label: "Monto", format: "currency" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  "cash-funds": {
    route: "/contabilidad/caja-chica",
    kind: "resource",
    resource: "cash-funds",
    preset: { fund_type: "caja_chica" },
    filters: { fund_type: "caja_chica" },
    title: "Caja Chica",
    subtitle: "Fondos menores con saldo, responsable, fechas y estado de rendición.",
    help: "La caja chica permite registrar entregas, saldo disponible y control de rendición parcial o total.",
    fields: [
      { key: "code", label: "Código", type: "text", required: true },
      { key: "fund_type", label: "Tipo", type: "select", staticOptions: ["caja_chica", "fondo_por_rendir"], required: true },
      { key: "responsible_user_id", label: "Responsable", type: "select", optionsKey: "users" },
      { key: "cost_center_id", label: "Centro de costo", type: "select", optionsKey: "cost_centers" },
      { key: "funding_source_id", label: "Fuente", type: "select", optionsKey: "funding_sources" },
      { key: "initial_amount", label: "Monto inicial", type: "number", required: true },
      { key: "current_balance", label: "Saldo actual", type: "number" },
      { key: "delivered_at", label: "Entrega", type: "date" },
      { key: "due_at", label: "Vence", type: "date" },
      { key: "status", label: "Estado", required: true, ...statusSelect("cash_funds") },
      { key: "notes", label: "Observaciones", type: "textarea" },
    ],
    columns: [
      { key: "code", label: "Código" },
      { key: "responsible_user_id", label: "Responsable", format: "lookup", lookupKey: "users" },
      { key: "initial_amount", label: "Inicial", format: "currency" },
      { key: "current_balance", label: "Saldo", format: "currency" },
      { key: "due_at", label: "Vence", format: "date" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  "funds-to-render": {
    route: "/contabilidad/fondos-rendir",
    kind: "resource",
    resource: "cash-funds",
    preset: { fund_type: "fondo_por_rendir" },
    filters: { fund_type: "fondo_por_rendir" },
    title: "Fondos por Rendir",
    subtitle: "Vista específica de recursos entregados y pendientes de rendición final.",
    help: "Esta vista separa fondos por rendir de caja chica para facilitar seguimiento, observación y aprobación.",
    fields: [
      { key: "code", label: "Código", type: "text", required: true },
      { key: "fund_type", label: "Tipo", type: "select", staticOptions: ["caja_chica", "fondo_por_rendir"], required: true },
      { key: "responsible_user_id", label: "Responsable", type: "select", optionsKey: "users" },
      { key: "cost_center_id", label: "Centro de costo", type: "select", optionsKey: "cost_centers" },
      { key: "funding_source_id", label: "Fuente", type: "select", optionsKey: "funding_sources" },
      { key: "initial_amount", label: "Monto entregado", type: "number", required: true },
      { key: "current_balance", label: "Saldo pendiente", type: "number" },
      { key: "delivered_at", label: "Fecha entrega", type: "date" },
      { key: "due_at", label: "Límite rendición", type: "date" },
      { key: "status", label: "Estado", required: true, ...statusSelect("cash_funds") },
      { key: "notes", label: "Observaciones", type: "textarea" },
    ],
    columns: [
      { key: "code", label: "Código" },
      { key: "responsible_user_id", label: "Responsable", format: "lookup", lookupKey: "users" },
      { key: "initial_amount", label: "Entregado", format: "currency" },
      { key: "current_balance", label: "Pendiente", format: "currency" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  "bank-movements": {
    route: "/contabilidad/conciliacion",
    kind: "resource",
    resource: "bank-movements",
    secondaryResource: "bank-accounts",
    title: "Conciliación Bancaria",
    subtitle: "Cartola interna, estado de conciliación y diferencias entre banco y libro.",
    help: "La conciliación compara movimientos bancarios con ingresos, egresos y cheques registrados internamente.",
    fields: [
      { key: "bank_account_id", label: "Cuenta bancaria", type: "select", optionsKey: "bank_accounts", required: true, labelFormatter: (item) => `${item.bank_name} - ${item.account_number}` },
      { key: "movement_type", label: "Tipo", type: "select", staticOptions: ["income", "expense", "transfer", "cheque", "adjustment"], required: true },
      { key: "description", label: "Descripción", type: "text", required: true },
      { key: "movement_date", label: "Fecha", type: "date", required: true },
      { key: "amount", label: "Monto", type: "number", required: true },
      { key: "status", label: "Estado", required: true, ...statusSelect("bank_movements") },
      { key: "is_reconciled", label: "Conciliado", type: "checkbox" },
      { key: "notes", label: "Observaciones", type: "textarea" },
    ],
    columns: [
      { key: "movement_date", label: "Fecha", format: "date" },
      { key: "bank_account_id", label: "Cuenta", format: "lookup", lookupKey: "bank_accounts", lookupFormatter: (item) => `${item.bank_name} - ${item.account_number}` },
      { key: "movement_type", label: "Tipo" },
      { key: "description", label: "Descripción" },
      { key: "amount", label: "Monto", format: "currency" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  "funding-sources": {
    route: "/contabilidad/subvenciones",
    kind: "resource",
    resource: "funding-sources",
    title: "Panel de Subvenciones",
    subtitle: "Catálogo de subvenciones y fuentes con foco en ingresos, ejecución y saldo interno.",
    help: "Esta sección organiza las fuentes de financiamiento para asociar ingresos, egresos y presupuesto.",
    fields: [
      { key: "code", label: "Código", type: "text", required: true },
      { key: "name", label: "Nombre", type: "text", required: true },
      { key: "category", label: "Categoría", type: "select", staticOptions: ["subvencion", "aporte_municipal", "ingreso_propio", "convenio", "otro"], required: true },
      { key: "is_active", label: "Activa", type: "checkbox" },
      { key: "description", label: "Descripción", type: "textarea" },
    ],
    columns: [
      { key: "code", label: "Código" },
      { key: "name", label: "Nombre" },
      { key: "category", label: "Categoría" },
      { key: "is_active", label: "Activa", format: "boolean" },
    ],
  },
  cheques: {
    route: "/contabilidad/cheques",
    kind: "resource",
    resource: "cheques",
    optionResources: ["payables", "expenses"],
    title: "Gestión de Cheques",
    subtitle: "Control de cheques emitidos, cobrados, anulados o pendientes.",
    help: "Permite llevar correlativo, beneficiario, fecha de emisión y estado del cheque.",
    fields: [
      { key: "bank_account_id", label: "Cuenta bancaria", type: "select", optionsKey: "bank_accounts", required: true, labelFormatter: (item) => `${item.bank_name} - ${item.account_number}` },
      { key: "check_number", label: "Número cheque", type: "text", required: true },
      { key: "payable_id", label: "Cuenta por pagar", type: "select", optionsKey: "payables", labelFormatter: (item) => item.code },
      { key: "expense_id", label: "Egreso", type: "select", optionsKey: "expenses", labelFormatter: (item) => item.code },
      { key: "beneficiary_name", label: "Beneficiario", type: "text", required: true },
      { key: "amount", label: "Monto", type: "number", required: true },
      { key: "issued_at", label: "Emisión", type: "date" },
      { key: "cashed_at", label: "Cobro", type: "date" },
      { key: "status", label: "Estado", required: true, ...statusSelect("cheques") },
      { key: "notes", label: "Observaciones", type: "textarea" },
    ],
    columns: [
      { key: "check_number", label: "Cheque" },
      { key: "beneficiary_name", label: "Beneficiario" },
      { key: "amount", label: "Monto", format: "currency" },
      { key: "issued_at", label: "Emisión", format: "date" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  invoices: {
    route: "/contabilidad/facturas",
    kind: "resource",
    resource: "expenses",
    preset: { document_type: "factura" },
    filters: { document_type: "factura" },
    title: "Gestión de Facturas",
    subtitle: "Control de facturas recibidas con estado de pago y uso contable interno.",
    help: "Módulo de control interno y preparación documental. No reemplaza libros oficiales del SII.",
    fields: [
      { key: "code", label: "Código", type: "text", required: true },
      { key: "expense_date", label: "Fecha emisión", type: "date", required: true },
      { key: "party_id", label: "Proveedor", type: "select", optionsKey: "parties" },
      { key: "document_type", label: "Tipo", type: "select", staticOptions: ["factura", "boleta_honorarios", "boleta", "comprobante", "otro"], required: true },
      { key: "document_number", label: "Folio", type: "text" },
      { key: "manual_account_id", label: "Cuenta", type: "select", optionsKey: "manual_accounts", labelFormatter: (item) => `${item.code} - ${item.name}` },
      { key: "cost_center_id", label: "Centro", type: "select", optionsKey: "cost_centers" },
      { key: "funding_source_id", label: "Fuente", type: "select", optionsKey: "funding_sources" },
      { key: "total_amount", label: "Total", type: "number", required: true },
      { key: "status", label: "Estado", required: true, ...statusSelect("expenses") },
      { key: "notes", label: "Observaciones", type: "textarea" },
    ],
    columns: [
      { key: "document_number", label: "Folio" },
      { key: "party_id", label: "Proveedor", format: "lookup", lookupKey: "parties" },
      { key: "expense_date", label: "Emisión", format: "date" },
      { key: "total_amount", label: "Total", format: "currency" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  honoraries: {
    route: "/contabilidad/boletas-honorarios",
    kind: "resource",
    resource: "expenses",
    preset: { document_type: "boleta_honorarios" },
    filters: { document_type: "boleta_honorarios" },
    title: "Boletas de Honorarios",
    subtitle: "Control de prestadores, retención parametrizable y base para DJ/F29.",
    help: "La tasa de retención se controla internamente por período; esta pantalla concentra la trazabilidad de boletas.",
    fields: [
      { key: "code", label: "Código", type: "text", required: true },
      { key: "expense_date", label: "Fecha", type: "date", required: true },
      { key: "party_id", label: "Prestador", type: "select", optionsKey: "parties" },
      { key: "document_type", label: "Tipo", type: "select", staticOptions: ["factura", "boleta_honorarios", "boleta", "comprobante", "otro"], required: true },
      { key: "document_number", label: "Folio", type: "text" },
      { key: "manual_account_id", label: "Cuenta", type: "select", optionsKey: "manual_accounts", labelFormatter: (item) => `${item.code} - ${item.name}` },
      { key: "cost_center_id", label: "Centro", type: "select", optionsKey: "cost_centers" },
      { key: "funding_source_id", label: "Fuente", type: "select", optionsKey: "funding_sources" },
      { key: "total_amount", label: "Bruto", type: "number", required: true },
      { key: "withholding_amount", label: "Retención", type: "number" },
      { key: "status", label: "Estado", required: true, ...statusSelect("expenses") },
      { key: "notes", label: "Observaciones", type: "textarea" },
    ],
    columns: [
      { key: "document_number", label: "Folio" },
      { key: "party_id", label: "Prestador", format: "lookup", lookupKey: "parties" },
      { key: "total_amount", label: "Bruto", format: "currency" },
      { key: "withholding_amount", label: "Retención", format: "currency" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  cashflow: {
    route: "/contabilidad/flujo-caja",
    kind: "cashflow",
    title: "Flujo de Caja",
    subtitle: "Lectura rápida de ingresos, egresos, saldo y proyección operativa interna.",
    help: "El flujo de caja es una vista interna para seguimiento y proyección. No reemplaza estados oficiales.",
  },
  payables: {
    route: "/contabilidad/cuentas-por-pagar",
    kind: "resource",
    resource: "payables",
    optionResources: ["expenses"],
    title: "Cuentas por Pagar",
    subtitle: "Obligaciones de pago con prioridad, vencimiento y responsable.",
    help: "Esta bandeja concentra pagos pendientes, programados o vencidos con foco de tesorería.",
    fields: [
      { key: "code", label: "Código", type: "text", required: true },
      { key: "party_id", label: "Proveedor", type: "select", optionsKey: "parties" },
      { key: "expense_id", label: "Egreso asociado", type: "select", optionsKey: "expenses", labelFormatter: (item) => item.code },
      { key: "due_date", label: "Vencimiento", type: "date", required: true },
      { key: "amount", label: "Monto", type: "number", required: true },
      { key: "status", label: "Estado", required: true, ...statusSelect("payables") },
      { key: "priority", label: "Prioridad", type: "select", staticOptions: ["baja", "media", "alta"], required: true },
      { key: "cost_center_id", label: "Centro", type: "select", optionsKey: "cost_centers" },
      { key: "funding_source_id", label: "Fuente", type: "select", optionsKey: "funding_sources" },
      { key: "responsible_user_id", label: "Responsable", type: "select", optionsKey: "users" },
      { key: "notes", label: "Observaciones", type: "textarea" },
    ],
    columns: [
      { key: "code", label: "Código" },
      { key: "party_id", label: "Proveedor", format: "lookup", lookupKey: "parties" },
      { key: "due_date", label: "Vence", format: "date" },
      { key: "amount", label: "Monto", format: "currency" },
      { key: "priority", label: "Prioridad", format: "badge" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  f29: {
    route: "/contabilidad/f29",
    kind: "resource",
    resource: "f29-declarations",
    secondaryResource: "tax-periods",
    title: "Gestión F29",
    subtitle: "Seguimiento mensual interno de IVA, PPM, retenciones y respaldo del período.",
    help: "Este módulo permite preparar, ordenar y controlar información interna. La presentación oficial debe realizarse en el SII cuando aplique.",
    fields: [
      { key: "tax_period_id", label: "Período", type: "select", optionsKey: "tax_periods", required: true, labelFormatter: (item) => `${item.year}-${String(item.month).padStart(2, "0")}` },
      { key: "status", label: "Estado", required: true, ...statusSelect("f29") },
      { key: "vat_debit", label: "IVA débito", type: "number" },
      { key: "vat_credit", label: "IVA crédito", type: "number" },
      { key: "ppm_amount", label: "PPM", type: "number" },
      { key: "withholding_amount", label: "Retenciones", type: "number" },
      { key: "receipt_number", label: "Comprobante", type: "text" },
      { key: "filed_at", label: "Presentado", type: "date" },
      { key: "paid_at", label: "Pagado", type: "date" },
      { key: "notes", label: "Observaciones", type: "textarea" },
    ],
    columns: [
      { key: "tax_period.year", label: "Año" },
      { key: "tax_period.month", label: "Mes" },
      { key: "vat_debit", label: "Débito", format: "currency" },
      { key: "vat_credit", label: "Crédito", format: "currency" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  balance: {
    route: "/contabilidad/balance",
    kind: "balance",
    resource: "journal-entry-lines",
    secondaryResource: "journal-entries",
    title: "Balance 8 y 9 Columnas",
    subtitle: "Balance interno generado desde asientos y líneas contables registradas.",
    help: "El balance se construye desde asientos contables internos. Todo asiento debe cuadrar: suma debe igual a haber.",
    fields: [
      { key: "journal_entry_id", label: "Asiento", type: "select", optionsKey: "journal_entries", required: true, labelFormatter: (item) => item.entry_number },
      { key: "manual_account_id", label: "Cuenta", type: "select", optionsKey: "manual_accounts", required: true, labelFormatter: (item) => `${item.code} - ${item.name}` },
      { key: "cost_center_id", label: "Centro", type: "select", optionsKey: "cost_centers" },
      { key: "funding_source_id", label: "Fuente", type: "select", optionsKey: "funding_sources" },
      { key: "line_description", label: "Detalle", type: "text" },
      { key: "debit", label: "Debe", type: "number" },
      { key: "credit", label: "Haber", type: "number" },
    ],
    columns: [
      { key: "journal_entry_id", label: "Asiento", format: "lookup", lookupKey: "journal_entries", lookupFormatter: (item) => item.entry_number },
      { key: "manual_account_id", label: "Cuenta", format: "lookup", lookupKey: "manual_accounts", lookupFormatter: (item) => `${item.code} - ${item.name}` },
      { key: "debit", label: "Debe", format: "currency" },
      { key: "credit", label: "Haber", format: "currency" },
      { key: "line_description", label: "Detalle" },
    ],
  },
  "dj-income": {
    route: "/contabilidad/dj-ingresos",
    kind: "resource",
    resource: "declarations",
    title: "DJ Ingresos",
    subtitle: "Registros internos base para declaraciones juradas asociadas a ingresos.",
    help: "Esta sección ordena información interna y no reemplaza la carga oficial en SII.",
    dynamicDeclarationCode: "dj_ingresos",
    fields: [
      { key: "declaration_type_id", label: "Tipo", type: "select", optionsKey: "declaration_types", required: true },
      { key: "year", label: "Año", type: "number", required: true },
      { key: "period_label", label: "Período", type: "text" },
      { key: "total_amount", label: "Monto", type: "number" },
      { key: "status", label: "Estado", required: true, ...statusSelect("declarations") },
      { key: "notes", label: "Observaciones", type: "textarea" },
    ],
    columns: [
      { key: "year", label: "Año" },
      { key: "period_label", label: "Período" },
      { key: "total_amount", label: "Monto", format: "currency" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  "dj-rental": {
    route: "/contabilidad/dj-arriendo",
    kind: "resource",
    resource: "declarations",
    title: "DJ Arriendo",
    subtitle: "Preparación interna de antecedentes de arriendos e inmuebles vinculados.",
    help: "Mantiene datos internos de contratos y montos de arriendo. La presentación oficial sigue siendo externa.",
    dynamicDeclarationCode: "dj_arriendo",
    fields: [
      { key: "declaration_type_id", label: "Tipo", type: "select", optionsKey: "declaration_types", required: true },
      { key: "party_id", label: "Arrendador", type: "select", optionsKey: "parties" },
      { key: "year", label: "Año", type: "number", required: true },
      { key: "period_label", label: "Período", type: "text" },
      { key: "total_amount", label: "Monto anual", type: "number" },
      { key: "status", label: "Estado", required: true, ...statusSelect("declarations") },
      { key: "notes", label: "Observaciones", type: "textarea" },
    ],
    columns: [
      { key: "year", label: "Año" },
      { key: "party_id", label: "Arrendador", format: "lookup", lookupKey: "parties" },
      { key: "total_amount", label: "Monto", format: "currency" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  "income-tax": {
    route: "/contabilidad/declaracion-renta",
    kind: "resource",
    resource: "declarations",
    title: "Declaración de Renta",
    subtitle: "Control interno documental para preparación de renta anual.",
    help: "Este módulo es de control interno y documental; no promete automatizar ni reemplazar la declaración oficial del SII.",
    dynamicDeclarationCode: "renta_interna",
    fields: [
      { key: "declaration_type_id", label: "Tipo", type: "select", optionsKey: "declaration_types", required: true },
      { key: "year", label: "Año tributario", type: "number", required: true },
      { key: "period_label", label: "Período", type: "text" },
      { key: "total_amount", label: "Resultado", type: "number" },
      { key: "status", label: "Estado", required: true, ...statusSelect("declarations") },
      { key: "notes", label: "Observaciones", type: "textarea" },
    ],
    columns: [
      { key: "year", label: "Año" },
      { key: "period_label", label: "Período" },
      { key: "total_amount", label: "Resultado", format: "currency" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  reports: {
    route: "/contabilidad/reportes",
    kind: "reports",
    title: "Reportes Contables",
    subtitle: "Exportación y lectura consolidada de presupuesto, ingresos, egresos y cumplimiento.",
    help: "Los reportes son exportables en CSV y sirven como base interna de análisis y seguimiento.",
  },
};

const metricCards = [
  { key: "income_amount", label: "Ingresos del período" },
  { key: "expense_amount", label: "Egresos del período" },
  { key: "available_balance", label: "Saldo disponible" },
  { key: "approved_budget", label: "Presupuesto aprobado" },
  { key: "budget_execution", label: "Presupuesto ejecutado" },
];

export default {
  components: {
    Layout,
    LoadingState,
    AccountingHelpButton,
  },
  data() {
    return {
      navItems,
      metricCards,
      panels: panelDefinitions,
      catalogs: {
        data: {},
        statuses: {},
        types: {},
        permissions: [],
      },
      dashboard: {
        metrics: {},
        alerts: {},
        summaries: { funding_sources: [], cost_centers: [] },
        recent: { incomes: [], expenses: [], payables: [] },
      },
      reports: {},
      resources: {},
      form: {},
      editingId: null,
      loadingCatalogs: false,
      loadingPanel: false,
      saving: false,
      search: "",
    };
  },
  computed: {
    activePanel() {
      return Object.values(this.panels).find((panel) => panel.route === this.$route.path) || this.panels.dashboard;
    },
    isDashboard() {
      return this.activePanel.kind === "dashboard";
    },
    isReports() {
      return this.activePanel.kind === "reports";
    },
    isCashflow() {
      return this.activePanel.kind === "cashflow";
    },
    isBalance() {
      return this.activePanel.kind === "balance";
    },
    activeItems() {
      return this.resourceItems(this.activePanel.resource);
    },
    secondaryItems() {
      return this.resourceItems(this.activePanel.secondaryResource);
    },
  },
  watch: {
    "$route.path"() {
      this.resetForm();
      this.refreshCurrent();
    },
  },
  async mounted() {
    await this.loadCatalogs();
    this.resetForm();
    await this.refreshCurrent();
  },
  methods: {
    money,
    shortDate,
    isNavActive(route) {
      return this.$route.path === route;
    },
    async loadCatalogs() {
      this.loadingCatalogs = true;
      try {
        const response = await axios.get("/api/contabilidad/catalogs");
        this.catalogs = response.data || this.catalogs;
      } catch (error) {
        await Swal.fire("Error", formatAccountingError(error, "No se pudieron cargar los catálogos de Contabilidad."), "error");
      } finally {
        this.loadingCatalogs = false;
      }
    },
    async refreshCurrent() {
      this.loadingPanel = true;
      try {
        if (this.isDashboard) {
          await this.loadDashboard();
        } else if (this.isCashflow) {
          await this.loadDashboard();
        } else if (this.isReports || this.isBalance) {
          await this.loadReports();
          if (this.isBalance) {
            await this.loadResource(this.activePanel.secondaryResource);
            await this.loadResource(this.activePanel.resource);
          }
        } else {
          await this.loadResource(this.activePanel.resource, this.panelFilters(this.activePanel));
          if (this.activePanel.secondaryResource) {
            await this.loadResource(this.activePanel.secondaryResource);
          }
          for (const resource of this.activePanel.optionResources || []) {
            await this.loadResource(resource);
          }
        }
      } catch (error) {
        await Swal.fire("Error", formatAccountingError(error, "No se pudo cargar la sección de Contabilidad."), "error");
      } finally {
        this.loadingPanel = false;
      }
    },
    async loadDashboard() {
      const response = await axios.get("/api/contabilidad/dashboard");
      this.dashboard = response.data || this.dashboard;
    },
    async loadReports() {
      const response = await axios.get("/api/contabilidad/reportes");
      this.reports = response.data || {};
      if (!this.resources["journal-entries"]) {
        this.resources["journal-entries"] = { items: [] };
      }
    },
    async loadResource(resource, filters = {}) {
      if (!resource) return;
      const response = await axios.get(`/api/contabilidad/resources/${resource}`, {
        params: {
          all: 1,
          search: this.search || undefined,
          ...filters,
        },
      });
      this.resources[resource] = {
        items: response.data.data || response.data || [],
      };

      if (resource === "journal-entries") {
        this.catalogs.data.journal_entries = this.resources[resource].items;
      }
      if (resource === "payables") {
        this.catalogs.data.payables = this.resources[resource].items;
      }
      if (resource === "expenses") {
        this.catalogs.data.expenses = this.resources[resource].items;
      }
    },
    resourceItems(resource) {
      return this.resources[resource]?.items || [];
    },
    panelFilters(panel) {
      const filters = { ...(panel.filters || {}) };
      if (panel.dynamicDeclarationCode) {
        const type = (this.catalogs.data.declaration_types || []).find((item) => item.code === panel.dynamicDeclarationCode);
        if (type) {
          filters.declaration_type_id = type.id;
        }
      }
      return filters;
    },
    resetForm() {
      const panel = this.activePanel;
      const base = {};
      (panel.fields || []).forEach((field) => {
        if (field.type === "checkbox") {
          base[field.key] = false;
        } else {
          base[field.key] = "";
        }
      });
      Object.assign(base, panel.preset || {});
      if (panel.dynamicDeclarationCode) {
        const type = (this.catalogs.data.declaration_types || []).find((item) => item.code === panel.dynamicDeclarationCode);
        if (type) {
          base.declaration_type_id = type.id;
        }
      }
      this.form = base;
      this.editingId = null;
    },
    resolveOptions(field) {
      if (field.staticOptions) {
        return field.staticOptions.map((value) => ({ id: value, name: value }));
      }
      if (field.statusKey) {
        return (this.catalogs.statuses[field.statusKey] || []).map((value) => ({ id: value, name: value }));
      }
      return this.catalogs.data[field.optionsKey] || [];
    },
    optionValue(option) {
      return option.id;
    },
    optionLabel(field, option) {
      if (field.labelFormatter) {
        return field.labelFormatter(option);
      }
      return option[field.labelKey || "name"] ?? option.name ?? option.code ?? option.id;
    },
    async submitForm() {
      if (!this.activePanel.resource) return;

      this.saving = true;
      const payload = { ...this.form, ...(this.activePanel.preset || {}) };
      try {
        if (this.editingId) {
          await axios.put(`/api/contabilidad/resources/${this.activePanel.resource}/${this.editingId}`, payload);
        } else {
          await axios.post(`/api/contabilidad/resources/${this.activePanel.resource}`, payload);
        }

        await Swal.fire("Guardado", "La información fue registrada correctamente.", "success");
        this.resetForm();
        await this.refreshCurrent();
      } catch (error) {
        await Swal.fire("Error", formatAccountingError(error), "error");
      } finally {
        this.saving = false;
      }
    },
    editItem(item) {
      this.editingId = item.id;
      const nextForm = {};
      (this.activePanel.fields || []).forEach((field) => {
        const value = this.valueAtPath(item, field.key);
        nextForm[field.key] = field.type === "checkbox" ? Boolean(value) : value ?? "";
      });
      Object.assign(nextForm, this.activePanel.preset || {});
      this.form = nextForm;
      window.scrollTo({ top: 0, behavior: "smooth" });
    },
    async removeItem(item) {
      const result = await Swal.fire({
        title: "Eliminar registro",
        text: "Esta acción mantiene la trazabilidad y aplicará borrado lógico cuando corresponda.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed) {
        return;
      }

      try {
        await axios.delete(`/api/contabilidad/resources/${this.activePanel.resource}/${item.id}`);
        await Swal.fire("Eliminado", "El registro fue eliminado correctamente.", "success");
        await this.refreshCurrent();
      } catch (error) {
        await Swal.fire("Error", formatAccountingError(error), "error");
      }
    },
    valueAtPath(item, path) {
      if (!path) return null;
      return path.split(".").reduce((carry, key) => (carry == null ? carry : carry[key]), item);
    },
    lookupValue(lookupKey, value, formatter = null) {
      const options = this.catalogs.data[lookupKey] || [];
      const option = options.find((item) => String(item.id) === String(value));
      if (!option) return "-";
      return formatter ? formatter(option) : option.name || option.code || option.id;
    },
    formatCell(item, column) {
      const value = this.valueAtPath(item, column.key);
      switch (column.format) {
        case "currency":
          return money(value);
        case "date":
          return shortDate(value);
        case "boolean":
          return value ? "Sí" : "No";
        case "lookup":
          return this.lookupValue(column.lookupKey, value, column.lookupFormatter);
        default:
          return value ?? "-";
      }
    },
    badgeClass(value) {
      const status = String(value || "").toLowerCase();
      if (["aprobado", "pagado", "conciliado", "rendido", "activo", "confirmado", "presentado"].includes(status)) return "bg-success-subtle text-success";
      if (["pendiente", "borrador", "en_preparacion", "pendiente_revision", "programada", "emitido"].includes(status)) return "bg-warning-subtle text-warning";
      if (["observado", "rechazado", "anulado", "vencida", "vencido", "diferencia"].includes(status)) return "bg-danger-subtle text-danger";
      return "bg-info-subtle text-info";
    },
    async downloadReport(report) {
      try {
        const response = await axios.get(`/api/contabilidad/export/${report}`, { responseType: "blob" });
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement("a");
        link.href = url;
        link.setAttribute("download", `contabilidad-${report}.csv`);
        document.body.appendChild(link);
        link.click();
        link.remove();
      } catch (error) {
        await Swal.fire("Error", formatAccountingError(error, "No se pudo exportar el reporte."), "error");
      }
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-column gap-3">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
          <h4 class="mb-1">{{ activePanel.title }}</h4>
          <div class="text-muted">{{ activePanel.subtitle }}</div>
        </div>
        <AccountingHelpButton :title="`Ayuda: ${activePanel.title}`" :text="activePanel.help" />
      </div>

      <BAlert show variant="warning" class="mb-0">
        Este módulo permite preparar, ordenar y controlar información interna. La presentación oficial debe realizarse en las plataformas correspondientes cuando aplique.
      </BAlert>

      <div class="d-flex flex-wrap gap-2">
        <router-link
          v-for="item in navItems"
          :key="item.route"
          :to="item.route"
          class="btn btn-sm"
          :class="isNavActive(item.route) ? 'btn-primary' : 'btn-outline-secondary'"
        >
          {{ item.label }}
        </router-link>
      </div>

      <BCard v-if="loadingCatalogs || loadingPanel" class="border-0 shadow-sm">
        <LoadingState message="Cargando módulo de Contabilidad..." compact />
      </BCard>

      <template v-else-if="isDashboard">
        <div class="row g-3">
          <div v-for="metric in metricCards" :key="metric.key" class="col-md-6 col-xl-4">
            <BCard class="border-0 shadow-sm h-100">
              <div class="text-muted small">{{ metric.label }}</div>
              <div class="h4 mt-2 mb-0">{{ money(dashboard.metrics[metric.key]) }}</div>
            </BCard>
          </div>
          <div class="col-md-6 col-xl-4">
            <BCard class="border-0 shadow-sm h-100">
              <div class="text-muted small">Ejecución presupuestaria</div>
              <div class="h4 mt-2 mb-0">{{ dashboard.metrics.budget_execution_percentage || 0 }}%</div>
            </BCard>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-xl-4">
            <BCard class="border-0 shadow-sm h-100">
              <div class="fw-semibold mb-2">Alertas</div>
              <ul class="list-unstyled mb-0 small">
                <li class="py-1">Vencimientos próximos: {{ dashboard.alerts.payables_due_soon || 0 }}</li>
                <li class="py-1">Cuentas vencidas: {{ dashboard.alerts.overdue_payables || 0 }}</li>
                <li class="py-1">Fondos por rendir: {{ dashboard.alerts.funds_expiring || 0 }}</li>
                <li class="py-1">Movimientos sin conciliar: {{ dashboard.alerts.reconciliation_pending || 0 }}</li>
                <li class="py-1">Facturas pendientes: {{ dashboard.alerts.invoices_pending_payment || 0 }}</li>
              </ul>
            </BCard>
          </div>
          <div class="col-xl-4">
            <BCard class="border-0 shadow-sm h-100">
              <div class="fw-semibold mb-2">Resumen por subvención</div>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Fuente</th>
                      <th>Saldo</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in dashboard.summaries.funding_sources" :key="item.label">
                      <td>{{ item.label }}</td>
                      <td>{{ money(item.balance) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </BCard>
          </div>
          <div class="col-xl-4">
            <BCard class="border-0 shadow-sm h-100">
              <div class="fw-semibold mb-2">Resumen por centro de costo</div>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Centro</th>
                      <th>Diferencia</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in dashboard.summaries.cost_centers" :key="item.label">
                      <td>{{ item.label }}</td>
                      <td>{{ money(item.variance) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </BCard>
          </div>
        </div>
      </template>

      <template v-else-if="isCashflow">
        <div class="row g-3">
          <div class="col-md-4">
            <BCard class="border-0 shadow-sm">
              <div class="text-muted small">Ingresos reales</div>
              <div class="h4 mt-2 mb-0">{{ money(dashboard.metrics.income_amount) }}</div>
            </BCard>
          </div>
          <div class="col-md-4">
            <BCard class="border-0 shadow-sm">
              <div class="text-muted small">Egresos reales</div>
              <div class="h4 mt-2 mb-0">{{ money(dashboard.metrics.expense_amount) }}</div>
            </BCard>
          </div>
          <div class="col-md-4">
            <BCard class="border-0 shadow-sm">
              <div class="text-muted small">Saldo final</div>
              <div class="h4 mt-2 mb-0">{{ money(dashboard.metrics.available_balance) }}</div>
            </BCard>
          </div>
        </div>
        <BCard class="border-0 shadow-sm">
          <div class="fw-semibold mb-2">Lectura rápida</div>
          <p class="mb-0 text-muted">
            El flujo usa ingresos y egresos registrados en el módulo. La proyección detallada puede ajustarse con movimientos proyectados en una siguiente iteración.
          </p>
        </BCard>
      </template>

      <template v-else-if="isReports">
        <BCard class="border-0 shadow-sm">
          <div class="d-flex flex-wrap gap-2">
            <BButton variant="primary" size="sm" @click="downloadReport('budget_execution')">Exportar presupuesto</BButton>
            <BButton variant="outline-primary" size="sm" @click="downloadReport('incomes_by_source')">Exportar ingresos</BButton>
            <BButton variant="outline-primary" size="sm" @click="downloadReport('expenses_by_center')">Exportar egresos</BButton>
            <BButton variant="outline-primary" size="sm" @click="downloadReport('payables')">Exportar cuentas por pagar</BButton>
          </div>
        </BCard>

        <BCard class="border-0 shadow-sm">
          <div class="fw-semibold mb-2">Ejecución presupuestaria</div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Presupuesto</th>
                  <th>Centro</th>
                  <th>Cuenta</th>
                  <th>Planificado</th>
                  <th>Ejecutado</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in reports.budget_execution || []" :key="`${row.presupuesto}-${row.cuenta}-${row.centro_costo}`">
                  <td>{{ row.presupuesto }}</td>
                  <td>{{ row.centro_costo }}</td>
                  <td>{{ row.cuenta }}</td>
                  <td>{{ money(row.monto_planificado) }}</td>
                  <td>{{ money(row.monto_ejecutado) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </template>

      <template v-else>
        <BCard v-if="isBalance" class="border-0 shadow-sm">
          <div class="fw-semibold mb-2">Balance 8 Columnas</div>
          <div class="table-responsive mb-3">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Cuenta</th>
                  <th>Débitos</th>
                  <th>Créditos</th>
                  <th>Saldo deudor</th>
                  <th>Saldo acreedor</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in reports.balance_8_columns || []" :key="row.cuenta">
                  <td>{{ row.cuenta }}</td>
                  <td>{{ money(row.debitos) }}</td>
                  <td>{{ money(row.creditos) }}</td>
                  <td>{{ money(row.saldo_deudor) }}</td>
                  <td>{{ money(row.saldo_acreedor) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>

        <BCard v-if="activePanel.secondaryResource" class="border-0 shadow-sm">
          <div class="fw-semibold mb-2">Resumen relacionado</div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th v-for="column in (activePanel.secondaryResource === 'budgets'
                    ? ['Nombre', 'Estado', 'Año']
                    : activePanel.secondaryResource === 'manual-versions'
                    ? ['Versión', 'Año', 'Vigente']
                    : activePanel.secondaryResource === 'bank-accounts'
                    ? ['Banco', 'Cuenta', 'Saldo']
                    : activePanel.secondaryResource === 'tax-periods'
                    ? ['Año', 'Mes', 'Estado']
                    : ['Asiento', 'Fecha', 'Estado'])" :key="column">
                    {{ column }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in secondaryItems" :key="item.id">
                  <template v-if="activePanel.secondaryResource === 'budgets'">
                    <td>{{ item.name }}</td>
                    <td><span class="badge rounded-pill" :class="badgeClass(item.status)">{{ item.status }}</span></td>
                    <td>{{ item.year }}</td>
                  </template>
                  <template v-else-if="activePanel.secondaryResource === 'manual-versions'">
                    <td>{{ item.version }}</td>
                    <td>{{ item.year }}</td>
                    <td>{{ item.is_current ? 'Sí' : 'No' }}</td>
                  </template>
                  <template v-else-if="activePanel.secondaryResource === 'bank-accounts'">
                    <td>{{ item.bank_name }}</td>
                    <td>{{ item.account_number }}</td>
                    <td>{{ money(item.current_balance) }}</td>
                  </template>
                  <template v-else-if="activePanel.secondaryResource === 'tax-periods'">
                    <td>{{ item.year }}</td>
                    <td>{{ item.month }}</td>
                    <td><span class="badge rounded-pill" :class="badgeClass(item.status)">{{ item.status }}</span></td>
                  </template>
                  <template v-else>
                    <td>{{ item.entry_number }}</td>
                    <td>{{ shortDate(item.entry_date) }}</td>
                    <td><span class="badge rounded-pill" :class="badgeClass(item.status)">{{ item.status }}</span></td>
                  </template>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>

        <BCard v-if="activePanel.fields" class="border-0 shadow-sm">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="fw-semibold">{{ editingId ? 'Editar registro' : 'Nuevo registro' }}</div>
            <div class="d-flex gap-2">
              <BButton variant="outline-secondary" size="sm" @click="resetForm">Limpiar</BButton>
              <BButton variant="primary" size="sm" :disabled="saving" @click="submitForm">{{ saving ? 'Guardando...' : editingId ? 'Actualizar' : 'Guardar' }}</BButton>
            </div>
          </div>
          <div class="row g-3">
            <div v-for="field in activePanel.fields" :key="field.key" class="col-md-6">
              <label class="form-label">{{ field.label }}</label>
              <BFormTextarea
                v-if="field.type === 'textarea'"
                v-model="form[field.key]"
                rows="3"
              />
              <BFormCheckbox
                v-else-if="field.type === 'checkbox'"
                v-model="form[field.key]"
                switch
              >
                {{ field.label }}
              </BFormCheckbox>
              <BFormSelect
                v-else-if="field.type === 'select'"
                v-model="form[field.key]"
              >
                <option value="">Seleccionar...</option>
                <option
                  v-for="option in resolveOptions(field)"
                  :key="`${field.key}-${optionValue(option)}`"
                  :value="optionValue(option)"
                >
                  {{ optionLabel(field, option) }}
                </option>
              </BFormSelect>
              <BFormInput
                v-else
                v-model="form[field.key]"
                :type="field.type || 'text'"
              />
            </div>
          </div>
        </BCard>

        <BCard class="border-0 shadow-sm">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="fw-semibold">Registros</div>
            <BButton variant="outline-primary" size="sm" @click="refreshCurrent">Actualizar</BButton>
          </div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th v-for="column in activePanel.columns" :key="column.key">{{ column.label }}</th>
                  <th class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in activeItems" :key="item.id">
                  <td v-for="column in activePanel.columns" :key="`${item.id}-${column.key}`">
                    <span v-if="column.format === 'badge'" class="badge rounded-pill" :class="badgeClass(valueAtPath(item, column.key))">
                      {{ valueAtPath(item, column.key) }}
                    </span>
                    <span v-else>{{ formatCell(item, column) }}</span>
                  </td>
                  <td class="text-end">
                    <div class="d-flex justify-content-end gap-2">
                      <BButton variant="outline-secondary" size="sm" @click="editItem(item)">Editar</BButton>
                      <BButton variant="outline-danger" size="sm" @click="removeItem(item)">Eliminar</BButton>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </template>
    </div>
  </Layout>
</template>
