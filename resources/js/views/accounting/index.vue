<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import AccountingHelpButton from "../../components/accounting/help-button.vue";
import { formatAccountingError, money, shortDate } from "../../components/accounting/module-utils";

const navItems = [
  { route: "/contabilidad", key: "dashboard", label: "Dashboard", group: "Resumen", icon: "bx-grid-alt" },
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

const navGroups = [
  { label: "Resumen", icon: "bx-grid-alt", keys: ["dashboard", "cashflow", "reports"] },
  { label: "Operaciones", icon: "bx-transfer-alt", keys: ["incomes", "expenses", "invoices", "honoraries", "payables", "cheques"] },
  { label: "Presupuesto y fondos", icon: "bx-wallet", keys: ["budget-lines", "cost-centers", "funding-sources", "cash-funds", "funds-to-render", "renderings"] },
  { label: "Tesorería", icon: "bx-building-house", keys: ["bank-movements"] },
  { label: "Contabilidad", icon: "bx-book-open", keys: ["manual-accounts", "balance"] },
  { label: "Tributario", icon: "bx-receipt", keys: ["f29", "dj-income", "dj-rental", "income-tax"] },
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
      navGroups,
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
      searchDraft: "",
      formModalVisible: false,
      searchTimer: null,
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
    groupedNavigation() {
      return this.navGroups.map((group) => ({
        ...group,
        items: group.keys.map((key) => this.navItems.find((item) => item.key === key)).filter(Boolean),
      }));
    },
    activeGroupLabel() {
      return this.groupedNavigation.find((group) => group.items.some((item) => item.key === this.activePanelKey))?.label || "Contabilidad";
    },
    activePanelKey() {
      return Object.entries(this.panels).find(([, panel]) => panel === this.activePanel)?.[0] || "dashboard";
    },
    activeAmountTotal() {
      const currencyColumn = (this.activePanel.columns || []).find((column) => column.format === "currency");
      if (!currencyColumn) return null;
      return this.activeItems.reduce((total, item) => total + Number(this.valueAtPath(item, currencyColumn.key) || 0), 0);
    },
  },
  watch: {
    "$route.path"() {
      this.search = "";
      this.searchDraft = "";
      this.formModalVisible = false;
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
    openCreateModal() {
      this.resetForm();
      this.formModalVisible = true;
    },
    closeFormModal() {
      this.formModalVisible = false;
      this.resetForm();
    },
    applySearch() {
      window.clearTimeout(this.searchTimer);
      this.searchTimer = window.setTimeout(() => {
        this.search = this.searchDraft.trim();
        this.refreshCurrent();
      }, 350);
    },
    clearSearch() {
      this.searchDraft = "";
      this.search = "";
      this.refreshCurrent();
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
        this.formModalVisible = false;
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
      this.formModalVisible = true;
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
    <div class="accounting-shell">
      <header class="accounting-hero">
        <div class="hero-copy">
          <div class="eyebrow"><i class="bx bx-calculator"></i> Gestión financiera · {{ activeGroupLabel }}</div>
          <h1>{{ activePanel.title }}</h1>
          <p>{{ activePanel.subtitle }}</p>
        </div>
        <div class="hero-actions">
          <AccountingHelpButton :title="`Ayuda: ${activePanel.title}`" :text="activePanel.help" />
          <BButton v-if="activePanel.fields" variant="primary" @click="openCreateModal">
            <i class="bx bx-plus"></i> Nuevo registro
          </BButton>
        </div>
      </header>

      <nav class="accounting-nav" aria-label="Secciones de contabilidad">
        <div v-for="group in groupedNavigation" :key="group.label" class="nav-group">
          <div class="nav-group-title"><i class="bx" :class="group.icon"></i>{{ group.label }}</div>
          <div class="nav-group-links">
            <router-link v-for="item in group.items" :key="item.route" :to="item.route" :class="{ active: isNavActive(item.route) }">
              {{ item.label }}
            </router-link>
          </div>
        </div>
      </nav>

      <div class="scope-notice"><i class="bx bx-info-circle"></i><span><strong>Control interno.</strong> La presentación oficial se realiza en las plataformas correspondientes cuando aplica.</span></div>

      <BCard v-if="loadingCatalogs || loadingPanel" class="border-0 shadow-sm">
        <LoadingState message="Cargando módulo de Contabilidad..." compact />
      </BCard>

      <template v-else-if="isDashboard">
        <div class="metric-grid">
          <article v-for="(metric, index) in metricCards" :key="metric.key" class="metric-card">
            <div class="metric-icon" :class="`metric-icon-${index + 1}`"><i class="bx" :class="index === 0 ? 'bx-trending-up' : index === 1 ? 'bx-trending-down' : index === 2 ? 'bx-wallet' : 'bx-bar-chart-square'"></i></div>
            <div><span>{{ metric.label }}</span><strong>{{ money(dashboard.metrics[metric.key]) }}</strong></div>
          </article>
          <article class="metric-card metric-card-accent">
            <div class="metric-icon"><i class="bx bx-pie-chart-alt-2"></i></div>
            <div><span>Ejecución presupuestaria</span><strong>{{ dashboard.metrics.budget_execution_percentage || 0 }}%</strong></div>
            <div class="metric-progress"><span :style="{ width: `${Math.min(Number(dashboard.metrics.budget_execution_percentage || 0), 100)}%` }"></span></div>
          </article>
        </div>

        <div class="dashboard-grid">
          <section class="content-card alert-panel">
            <div class="card-heading"><div><span>ATENCIÓN REQUERIDA</span><h2>Alertas operativas</h2></div><i class="bx bx-bell"></i></div>
            <div class="alert-list">
              <div><i class="bx bx-calendar-exclamation"></i><span>Vencimientos próximos</span><strong>{{ dashboard.alerts.payables_due_soon || 0 }}</strong></div>
              <div class="danger"><i class="bx bx-error-circle"></i><span>Cuentas vencidas</span><strong>{{ dashboard.alerts.overdue_payables || 0 }}</strong></div>
              <div><i class="bx bx-time-five"></i><span>Fondos por rendir</span><strong>{{ dashboard.alerts.funds_expiring || 0 }}</strong></div>
              <div><i class="bx bx-transfer"></i><span>Sin conciliar</span><strong>{{ dashboard.alerts.reconciliation_pending || 0 }}</strong></div>
              <div><i class="bx bx-file"></i><span>Facturas pendientes</span><strong>{{ dashboard.alerts.invoices_pending_payment || 0 }}</strong></div>
            </div>
          </section>
          <section class="content-card summary-panel">
            <div class="card-heading"><div><span>DISTRIBUCIÓN</span><h2>Saldo por subvención</h2></div></div>
            <div class="summary-list">
              <div v-for="item in dashboard.summaries.funding_sources" :key="item.label"><span>{{ item.label }}</span><strong>{{ money(item.balance) }}</strong></div>
              <div v-if="!dashboard.summaries.funding_sources.length" class="mini-empty">Sin datos para el período.</div>
            </div>
          </section>
          <section class="content-card summary-panel">
            <div class="card-heading"><div><span>GESTIÓN</span><h2>Variación por centro</h2></div></div>
            <div class="summary-list">
              <div v-for="item in dashboard.summaries.cost_centers" :key="item.label"><span>{{ item.label }}</span><strong>{{ money(item.variance) }}</strong></div>
              <div v-if="!dashboard.summaries.cost_centers.length" class="mini-empty">Sin datos para el período.</div>
            </div>
          </section>
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

        <section class="content-card records-card">
          <div class="records-toolbar">
            <div>
              <span class="toolbar-kicker">BASE DE DATOS</span>
              <h2>Registros <span class="record-count">{{ activeItems.length }}</span></h2>
            </div>
            <div class="toolbar-actions">
              <div class="search-box">
                <i class="bx bx-search"></i>
                <input v-model="searchDraft" type="search" placeholder="Buscar en registros..." aria-label="Buscar registros" @input="applySearch" />
                <button v-if="searchDraft" type="button" aria-label="Limpiar búsqueda" @click="clearSearch"><i class="bx bx-x"></i></button>
              </div>
              <BButton variant="light" class="icon-action" title="Actualizar" @click="refreshCurrent"><i class="bx bx-refresh"></i></BButton>
              <BButton v-if="activePanel.fields" variant="primary" @click="openCreateModal"><i class="bx bx-plus"></i> Agregar</BButton>
            </div>
          </div>
          <div v-if="activeAmountTotal !== null" class="table-summary"><span>Total visible</span><strong>{{ money(activeAmountTotal) }}</strong></div>
          <div class="table-responsive">
            <table class="table accounting-table align-middle mb-0">
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
                    <div class="row-actions">
                      <button type="button" title="Editar" @click="editItem(item)"><i class="bx bx-edit-alt"></i></button>
                      <button type="button" class="danger" title="Eliminar" @click="removeItem(item)"><i class="bx bx-trash"></i></button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!activeItems.length">
                  <td :colspan="(activePanel.columns?.length || 0) + 1">
                    <div class="empty-state"><i class="bx bx-folder-open"></i><strong>{{ search ? 'No encontramos coincidencias' : 'Aún no hay registros' }}</strong><span>{{ search ? 'Prueba con otro término de búsqueda.' : 'Crea el primer registro para comenzar.' }}</span><BButton v-if="activePanel.fields && !search" variant="primary" size="sm" @click="openCreateModal">Crear registro</BButton></div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </template>

      <BModal v-model="formModalVisible" size="lg" centered scrollable hide-footer modal-class="accounting-form-modal" @hidden="resetForm">
        <template #title>
          <div class="modal-title-block"><span>{{ activeGroupLabel }}</span><strong>{{ editingId ? 'Editar registro' : 'Nuevo registro' }}</strong></div>
        </template>
        <form @submit.prevent="submitForm">
          <div class="modal-intro"><i class="bx bx-info-circle"></i><span>Completa la información de <strong>{{ activePanel.title.toLowerCase() }}</strong>. Los campos marcados con * son obligatorios.</span></div>
          <div class="accounting-form-grid">
            <label v-for="field in activePanel.fields || []" :key="field.key" :class="{ full: field.type === 'textarea', switch: field.type === 'checkbox' }">
              <span v-if="field.type !== 'checkbox'">{{ field.label }}<b v-if="field.required"> *</b></span>
              <BFormTextarea v-if="field.type === 'textarea'" v-model="form[field.key]" rows="3" :required="field.required" :placeholder="`Ingresa ${field.label.toLowerCase()}`" />
              <BFormCheckbox v-else-if="field.type === 'checkbox'" v-model="form[field.key]" switch>{{ field.label }}</BFormCheckbox>
              <BFormSelect v-else-if="field.type === 'select'" v-model="form[field.key]" :required="field.required">
                <option value="">Seleccionar...</option>
                <option v-for="option in resolveOptions(field)" :key="`${field.key}-${optionValue(option)}`" :value="optionValue(option)">{{ optionLabel(field, option) }}</option>
              </BFormSelect>
              <BFormInput v-else v-model="form[field.key]" :type="field.type || 'text'" :required="field.required" :min="field.type === 'number' ? 0 : undefined" :placeholder="field.type === 'date' || field.type === 'number' ? '' : `Ingresa ${field.label.toLowerCase()}`" />
            </label>
          </div>
          <footer class="modal-actions"><BButton variant="light" type="button" @click="closeFormModal">Cancelar</BButton><BButton variant="primary" type="submit" :disabled="saving"><span v-if="saving" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-check"></i>{{ saving ? 'Guardando...' : editingId ? 'Guardar cambios' : 'Crear registro' }}</BButton></footer>
        </form>
      </BModal>
    </div>
  </Layout>
</template>

<style scoped>
.accounting-shell{--acc-primary:#405189;--acc-ink:#263043;--acc-muted:#758095;--acc-border:#e2e7ee;display:flex;flex-direction:column;gap:1rem;padding-bottom:1.5rem}.accounting-hero{display:flex;align-items:center;justify-content:space-between;gap:2rem;padding:1.35rem 1.5rem;border:1px solid #dfe5ed;border-radius:12px;background:linear-gradient(125deg,#fff 0%,#f6f8fc 68%,#eef2fa 100%);box-shadow:0 5px 18px rgba(42,55,80,.05)}.hero-copy{max-width:850px}.eyebrow{display:flex;align-items:center;gap:.4rem;margin-bottom:.38rem;color:var(--acc-primary);font-size:.68rem;font-weight:750;letter-spacing:.075em;text-transform:uppercase}.eyebrow i{font-size:1rem}.accounting-hero h1{margin:0;color:var(--acc-ink);font-size:1.55rem;font-weight:700}.accounting-hero p{margin:.4rem 0 0;color:var(--acc-muted);font-size:.82rem}.hero-actions{display:flex;align-items:center;gap:.55rem;white-space:nowrap}.hero-actions .btn,.toolbar-actions .btn,.modal-actions .btn{display:inline-flex;align-items:center;justify-content:center;gap:.35rem}.accounting-nav{display:flex;gap:.5rem;overflow-x:auto;padding:.55rem;border:1px solid var(--acc-border);border-radius:10px;background:#fff;scrollbar-width:thin}.nav-group{flex:0 0 auto;min-width:130px;padding:.4rem .45rem;border-right:1px solid #edf0f4}.nav-group:last-child{border-right:0}.nav-group-title{display:flex;align-items:center;gap:.35rem;padding:0 .35rem .3rem;color:#8a94a4;font-size:.59rem;font-weight:750;letter-spacing:.065em;text-transform:uppercase}.nav-group-links{display:flex;flex-wrap:wrap;gap:.2rem}.nav-group-links a{padding:.34rem .52rem;border-radius:5px;color:#5f6b7c;font-size:.67rem;white-space:nowrap;transition:.15s ease}.nav-group-links a:hover{background:#f2f5fa;color:var(--acc-primary)}.nav-group-links a.active{background:#e9edf7;color:var(--acc-primary);font-weight:700}.scope-notice{display:flex;align-items:center;gap:.55rem;padding:.62rem .8rem;border:1px solid #f1dfb8;border-radius:8px;background:#fff9ec;color:#806326;font-size:.68rem}.scope-notice i{font-size:1.05rem}.metric-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.8rem}.metric-card{position:relative;display:flex;align-items:center;gap:.75rem;min-height:96px;padding:1rem;border:1px solid var(--acc-border);border-radius:10px;background:#fff;box-shadow:0 4px 14px rgba(35,48,70,.04);overflow:hidden}.metric-card span,.metric-card strong{display:block}.metric-card span{color:var(--acc-muted);font-size:.67rem}.metric-card strong{margin-top:.2rem;color:var(--acc-ink);font-size:1.25rem}.metric-icon{display:grid;place-items:center;flex:0 0 42px;width:42px;height:42px;border-radius:9px;background:#eaf4ee;color:#25845f;font-size:1.35rem}.metric-icon-2{background:#fbecee;color:#c34b59}.metric-icon-3{background:#eaf0fb;color:#456ea9}.metric-icon-4,.metric-icon-5{background:#f8f0dd;color:#a67a1f}.metric-card-accent{background:linear-gradient(135deg,#405189,#5266a2);border-color:transparent}.metric-card-accent span,.metric-card-accent strong,.metric-card-accent .metric-icon{color:#fff}.metric-card-accent .metric-icon{background:rgba(255,255,255,.14)}.metric-progress{position:absolute;right:1rem;bottom:.75rem;left:1rem;height:3px;border-radius:2px;background:rgba(255,255,255,.2)}.metric-progress span{height:100%;border-radius:2px;background:#fff}.dashboard-grid{display:grid;grid-template-columns:1.05fr 1fr 1fr;gap:.8rem}.content-card{border:1px solid var(--acc-border);border-radius:10px;background:#fff;box-shadow:0 4px 14px rgba(35,48,70,.035)}.card-heading{display:flex;align-items:center;justify-content:space-between;padding:.9rem 1rem;border-bottom:1px solid #edf0f4}.card-heading span,.toolbar-kicker{color:#8b95a5;font-size:.58rem;font-weight:750;letter-spacing:.07em}.card-heading h2,.records-toolbar h2{margin:.15rem 0 0;color:var(--acc-ink);font-size:.88rem}.card-heading>i{color:#9aa4b3;font-size:1.2rem}.alert-list>div,.summary-list>div{display:grid;grid-template-columns:27px 1fr auto;align-items:center;gap:.5rem;padding:.64rem 1rem;border-bottom:1px solid #eff2f5;color:#5d6879;font-size:.68rem}.alert-list>div:last-child,.summary-list>div:last-child{border-bottom:0}.alert-list i{display:grid;place-items:center;width:25px;height:25px;border-radius:6px;background:#fff5df;color:#ad791b;font-size:.9rem}.alert-list .danger i{background:#fdecee;color:#c04454}.alert-list strong{display:grid;place-items:center;min-width:25px;height:23px;border-radius:12px;background:#f0f3f7;color:#465366}.summary-list>div{grid-template-columns:1fr auto;min-height:43px}.summary-list strong{color:#334055}.mini-empty{display:block!important;color:#919baa!important;text-align:center}.records-card{overflow:hidden}.records-toolbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.9rem 1rem}.record-count{display:inline-grid;place-items:center;min-width:23px;height:20px;margin-left:.25rem;border-radius:10px;background:#eef1f6;color:#637087;font-size:.62rem}.toolbar-actions{display:flex;align-items:center;gap:.4rem}.search-box{display:flex;align-items:center;min-width:250px;height:35px;border:1px solid #dce2e9;border-radius:7px;background:#f9fafc}.search-box>i{margin-left:.65rem;color:#8691a1}.search-box input{width:100%;padding:0 .5rem;border:0;outline:0;background:transparent;color:#3d495b;font-size:.7rem}.search-box button{border:0;background:transparent;color:#7b8696}.icon-action{width:36px;padding:0}.table-summary{display:flex;align-items:center;justify-content:flex-end;gap:.6rem;padding:.45rem 1rem;border-top:1px solid #edf0f4;background:#f8fafc;color:#7b8697;font-size:.64rem}.table-summary strong{color:#334055;font-size:.75rem}.accounting-table{font-size:.68rem}.accounting-table thead th{padding:.65rem .8rem;border-color:#e7ebf0;background:#f5f7fa;color:#626f82;font-size:.6rem;font-weight:750;letter-spacing:.035em;text-transform:uppercase;white-space:nowrap}.accounting-table tbody td{padding:.66rem .8rem;border-color:#edf0f4;color:#4f5b6d}.accounting-table tbody tr:hover{background:#fafbfd}.accounting-table .badge{text-transform:capitalize}.row-actions{display:flex;justify-content:flex-end;gap:.25rem}.row-actions button{display:grid;place-items:center;width:29px;height:29px;border:1px solid #dce2e9;border-radius:6px;background:#fff;color:#59677b;font-size:.9rem}.row-actions button:hover{border-color:#aeb9c9;color:var(--acc-primary)}.row-actions .danger:hover{border-color:#e5aab2;background:#fff7f8;color:#bd3b4b}.empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:220px;color:#8994a4;text-align:center}.empty-state>i{margin-bottom:.5rem;color:#b1bac7;font-size:2.25rem}.empty-state strong{color:#566275;font-size:.8rem}.empty-state span{margin:.2rem 0 .65rem;font-size:.66rem}.modal-title-block span,.modal-title-block strong{display:block}.modal-title-block span{color:#8a94a4;font-size:.57rem;font-weight:750;letter-spacing:.07em;text-transform:uppercase}.modal-title-block strong{margin-top:.1rem;color:#293448;font-size:.95rem}.modal-intro{display:flex;gap:.5rem;margin-bottom:1rem;padding:.65rem .75rem;border-radius:7px;background:#f1f4fa;color:#657185;font-size:.67rem}.modal-intro i{color:var(--acc-primary);font-size:1rem}.accounting-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:.8rem}.accounting-form-grid label>span{display:block;margin-bottom:.28rem;color:#566275;font-size:.67rem;font-weight:650}.accounting-form-grid label>span b{color:#c04454}.accounting-form-grid .form-control,.accounting-form-grid .form-select{min-height:38px;border-color:#dce2e9;font-size:.72rem}.accounting-form-grid .full{grid-column:1/-1}.accounting-form-grid .switch{display:flex;align-items:center;min-height:38px;padding-top:.9rem}.modal-actions{display:flex;justify-content:flex-end;gap:.45rem;margin:1.1rem -1rem -1rem;padding:.8rem 1rem;border-top:1px solid #e5e9ef;background:#f9fafb}:deep(.accounting-form-modal .modal-content){border:0;border-radius:10px;box-shadow:0 24px 70px rgba(25,35,50,.25)}:deep(.accounting-form-modal .modal-header){padding:.8rem 1rem;border-bottom-color:#e5e9ef}:deep(.accounting-form-modal .modal-body){padding:1rem}:deep(.card){border:1px solid var(--acc-border)!important;border-radius:10px;box-shadow:0 4px 14px rgba(35,48,70,.035)!important}:deep(.table){font-size:.68rem}
@media(max-width:1100px){.metric-grid{grid-template-columns:repeat(2,1fr)}.dashboard-grid{grid-template-columns:1fr 1fr}.alert-panel{grid-column:1/-1}.accounting-nav{padding:.45rem}.nav-group{min-width:auto}.nav-group-title{display:none}}
@media(max-width:720px){.accounting-hero{align-items:flex-start;padding:1rem}.accounting-hero,.records-toolbar{flex-direction:column}.hero-actions,.toolbar-actions{width:100%}.hero-actions .btn{flex:1}.accounting-hero h1{font-size:1.25rem}.metric-grid,.dashboard-grid{grid-template-columns:1fr}.alert-panel{grid-column:auto}.records-toolbar{align-items:stretch}.toolbar-actions{flex-wrap:wrap}.search-box{flex:1;min-width:200px}.accounting-form-grid{grid-template-columns:1fr}.accounting-form-grid .full{grid-column:auto}.accounting-nav{display:block}.nav-group{padding:.3rem;border-right:0;border-bottom:1px solid #edf0f4}.nav-group-links{flex-wrap:nowrap;overflow-x:auto}.scope-notice{align-items:flex-start}}
</style>
