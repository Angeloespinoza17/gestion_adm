<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import AccountingHelpButton from "../../components/accounting/help-button.vue";
import { formatAccountingError, money, shortDate } from "../../components/accounting/module-utils";
import { getPdfMake } from "../../utils/pdfmake";

const currentSubsidyDate = new Date();
const previousSubsidyDate = new Date(currentSubsidyDate.getFullYear(), currentSubsidyDate.getMonth() - 1, 1);
const subsidyMonths = [
  { value: "01", text: "Enero" },
  { value: "02", text: "Febrero" },
  { value: "03", text: "Marzo" },
  { value: "04", text: "Abril" },
  { value: "05", text: "Mayo" },
  { value: "06", text: "Junio" },
  { value: "07", text: "Julio" },
  { value: "08", text: "Agosto" },
  { value: "09", text: "Septiembre" },
  { value: "10", text: "Octubre" },
  { value: "11", text: "Noviembre" },
  { value: "12", text: "Diciembre" },
];
const toMonthKey = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}`;

const navItems = [
  { route: "/contabilidad", key: "dashboard", label: "Dashboard", group: "Resumen", icon: "bx-grid-alt", permission: "contabilidad.dashboard" },
  { route: "/contabilidad/rendiciones", key: "renderings", label: "Rendiciones", permission: "contabilidad.fondos_rendir.gestionar" },
  { route: "/contabilidad/presupuesto", key: "budget-lines", label: "Presupuesto", permission: "contabilidad.presupuesto.ver" },
  { route: "/contabilidad/centros-costo", key: "cost-centers", label: "Centros de costo", permission: "contabilidad.centros_costo.gestionar" },
  { route: "/contabilidad/manual-cuentas", key: "manual-accounts", label: "Manual de cuentas", permission: "contabilidad.manual_cuentas.gestionar" },
  { route: "/contabilidad/ingresos", key: "incomes", label: "Ingresos", permission: "contabilidad.ingresos.gestionar" },
  { route: "/contabilidad/egresos", key: "expenses", label: "Egresos", permission: "contabilidad.egresos.gestionar" },
  { route: "/contabilidad/caja-chica", key: "cash-funds", label: "Caja chica", permission: "contabilidad.caja_chica.gestionar" },
  { route: "/contabilidad/fondos-rendir", key: "funds-to-render", label: "Fondos por rendir", permission: "contabilidad.fondos_rendir.gestionar" },
  { route: "/contabilidad/conciliacion", key: "bank-movements", label: "Conciliación", permission: "contabilidad.conciliacion.gestionar" },
  { route: "/contabilidad/subvenciones", key: "funding-sources", label: "Subvenciones", permission: "contabilidad.subvenciones.ver" },
  { route: "/contabilidad/cheques", key: "cheques", label: "Cheques", permission: "contabilidad.cheques.gestionar" },
  { route: "/contabilidad/facturas", key: "invoices", label: "Facturas", permission: "contabilidad.facturas.gestionar" },
  { route: "/contabilidad/boletas-honorarios", key: "honoraries", label: "Boletas", permission: "contabilidad.boletas.gestionar" },
  { route: "/contabilidad/flujo-caja", key: "cashflow", label: "Flujo caja", permission: "contabilidad.balance.ver" },
  { route: "/contabilidad/cuentas-por-pagar", key: "payables", label: "Cuentas por pagar", permission: "contabilidad.pagos.gestionar" },
  { route: "/contabilidad/f29", key: "f29", label: "F29", permission: "contabilidad.f29.gestionar" },
  { route: "/contabilidad/balance", key: "balance", label: "Balance", permission: "contabilidad.balance.ver" },
  { route: "/contabilidad/dj-ingresos", key: "dj-income", label: "DJ Ingresos", permission: "contabilidad.dj.gestionar" },
  { route: "/contabilidad/dj-arriendo", key: "dj-rental", label: "DJ Arriendo", permission: "contabilidad.dj.gestionar" },
  { route: "/contabilidad/declaracion-renta", key: "income-tax", label: "Renta", permission: "contabilidad.renta.gestionar" },
  { route: "/contabilidad/reportes", key: "reports", label: "Reportes", permission: "contabilidad.balance.ver" },
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
    kind: "subsidies",
    title: "Panel de Subvenciones",
    subtitle: "Importación MINEDUC, liquidación, distribución por nivel educativo y conciliación del ingreso.",
    help: "La liquidación explica el cálculo; el ingreso representa una única transferencia bancaria. PIE se conserva como desglose informativo para evitar duplicidad.",
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
      subsidyYear: currentSubsidyDate.getFullYear(),
      subsidyMonth: String(currentSubsidyDate.getMonth() + 1).padStart(2, "0"),
      subsidyMonths,
      subsidyComparePeriod: toMonthKey(previousSubsidyDate),
      subsidyDashboard: {
        metrics: {},
        by_level: [],
        by_family: [],
        pie: {
          total: 0,
          allocated_total: 0,
          unallocated_total: 0,
          row_count: 0,
          by_level: [],
          by_grade: [],
          by_course: [],
          components: {},
        },
        per_student: {
          by_cycle: [],
          by_grade: [],
          enrollment_total: 0,
          allocated_amount: 0,
        },
        settlements: [],
        comparison: {
          metrics: {},
          by_level: [],
          by_family: [],
          pie: {
            total: 0,
            allocated_total: 0,
            unallocated_total: 0,
            row_count: 0,
            by_level: [],
            by_grade: [],
            by_course: [],
            components: {},
          },
          per_student: {
            by_cycle: [],
            by_grade: [],
            enrollment_total: 0,
            allocated_amount: 0,
          },
          deltas: {},
        },
        annual: [],
        available_years: [],
      },
      subsidyFiles: [],
      importingSubsidies: false,
      downloadingSubsidyPdf: false,
      manualSubsidyVisible: false,
      manualSubsidyForm: {
        rbd: "6830",
        period: toMonthKey(currentSubsidyDate),
        subsidy_type: "normal",
        funding_source_id: "",
        gross_amount: "",
        transferred_amount: "",
        payment_date: "",
        source_reference: "",
      },
      subsidyDetailVisible: false,
      selectedSubsidy: null,
      subsidyPostVisible: false,
      subsidyPostForm: {
        received_at: "",
        transferred_amount: "",
        manual_account_id: "",
        bank_account_id: "",
        cost_center_id: "",
        document_reference: "",
        notes: "",
      },
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
    isSubsidies() {
      return this.activePanel.kind === "subsidies";
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
        items: group.keys
          .map((key) => this.navItems.find((item) => item.key === key))
          .filter((item) => item && this.canAccessNavigation(item.permission)),
      })).filter((group) => group.items.length > 0);
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
    subsidyIncomeAccounts() {
      return (this.catalogs.data.manual_accounts || []).filter((account) => account.type === "ingreso");
    },
    subsidyPeriod() {
      return `${this.subsidyYear}-${this.subsidyMonth}`;
    },
    subsidyYearOptions() {
      const years = [
        ...(this.subsidyDashboard.available_years || []),
        this.subsidyYear,
        currentSubsidyDate.getFullYear(),
      ];
      for (let offset = -4; offset <= 2; offset += 1) years.push(currentSubsidyDate.getFullYear() + offset);

      return [...new Set(years.map(Number))].sort((a, b) => b - a);
    },
    subsidyComparisonRows() {
      const previous = this.subsidyDashboard.comparison?.metrics || {};
      const current = this.subsidyDashboard.metrics || {};
      return [
        { key: "net_liquidated", label: "Líquido liquidado", current: current.net_liquidated, previous: previous.net_liquidated },
        { key: "transferred_total", label: "Transferido informado", current: current.transferred_total, previous: previous.transferred_total },
        { key: "income_total", label: "Ingreso contabilizado", current: current.income_total, previous: previous.income_total },
        { key: "allocated_total", label: "Asignado a niveles", current: current.allocated_total, previous: previous.allocated_total },
        { key: "pie_informative_total", label: "PIE (informativo)", current: current.pie_informative_total, previous: previous.pie_informative_total },
      ];
    },
    subsidyAnnualMax() {
      return Math.max(1, ...(this.subsidyDashboard.annual || []).map((item) => Number(item.net_liquidated || 0)));
    },
    subsidyAnnualTotals() {
      return (this.subsidyDashboard.annual || []).reduce((totals, item) => ({
        settlement_count: totals.settlement_count + Number(item.settlement_count || 0),
        net_liquidated: totals.net_liquidated + Number(item.net_liquidated || 0),
        transferred_total: totals.transferred_total + Number(item.transferred_total || 0),
        income_total: totals.income_total + Number(item.income_total || 0),
        pie_total: totals.pie_total + Number(item.pie_total || 0),
      }), {
        settlement_count: 0,
        net_liquidated: 0,
        transferred_total: 0,
        income_total: 0,
        pie_total: 0,
      });
    },
    subsidyQuadrature() {
      const settlements = this.subsidyDashboard.settlements || [];
      if (!settlements.length) return { label: "SIN DATOS", className: "text-muted" };
      if (settlements.some((settlement) => settlement.status === "observado")) {
        return { label: "REVISAR", className: "text-danger" };
      }
      if (settlements.some((settlement) => settlement.transferred_amount == null)) {
        return { label: "PENDIENTE", className: "text-warning" };
      }
      return Number(this.subsidyDashboard.metrics.difference_total) === 0
        ? { label: "CUADRADO", className: "text-success" }
        : { label: "REVISAR", className: "text-danger" };
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
    canAccessNavigation(permission) {
      const permissions = this.catalogs.permissions || [];

      return permissions.includes("__superadmin__")
        || (
          permissions.includes("contabilidad.acceso_confidencial")
          && permissions.includes("contabilidad.ver")
          && (permissions.includes(permission) || permissions.includes("contabilidad.admin"))
        );
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
        } else if (this.isSubsidies) {
          await this.loadSubsidies();
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
    async loadSubsidies() {
      const response = await axios.get("/api/contabilidad/subvenciones/dashboard", {
        params: {
          period: this.subsidyPeriod,
          compare_period: this.subsidyComparePeriod,
        },
      });
      this.subsidyDashboard = response.data || this.subsidyDashboard;
    },
    async changeSubsidyPeriod() {
      const selected = new Date(Number(this.subsidyYear), Number(this.subsidyMonth) - 1, 1);
      this.subsidyComparePeriod = toMonthKey(new Date(selected.getFullYear(), selected.getMonth() - 1, 1));
      await this.loadSubsidies();
    },
    async selectSubsidyAnnualMonth(item) {
      const [year, month] = item.period.split("-");
      this.subsidyYear = Number(year);
      this.subsidyMonth = month;
      await this.changeSubsidyPeriod();
    },
    subsidyPeriodLabel(period) {
      if (!period) return "Sin período";
      const [year, month] = period.split("-");
      const monthLabel = this.subsidyMonths.find((item) => item.value === month)?.text || month;
      return `${monthLabel} ${year}`;
    },
    subsidyDelta(key) {
      return this.subsidyDashboard.comparison?.deltas?.[key] || { amount: 0, percentage: null };
    },
    subsidyDeltaClass(key) {
      const amount = Number(this.subsidyDelta(key).amount || 0);
      return amount > 0 ? "positive" : amount < 0 ? "negative" : "neutral";
    },
    subsidyDeltaLabel(key) {
      const delta = this.subsidyDelta(key);
      const percentage = delta.percentage == null ? "" : ` (${Math.abs(Number(delta.percentage)).toLocaleString("es-CL")}%)`;
      const direction = Number(delta.amount || 0) > 0 ? "+" : "";
      return `${direction}${money(delta.amount || 0)}${percentage}`;
    },
    subsidyAnnualBarWidth(item) {
      return `${Math.max(0, (Number(item.net_liquidated || 0) / this.subsidyAnnualMax) * 100)}%`;
    },
    perStudentAverage(item) {
      return item?.average_per_student == null ? "Sin matrícula" : money(item.average_per_student);
    },
    async downloadSubsidyComparisonPdf() {
      this.downloadingSubsidyPdf = true;
      try {
        const pdfMake = getPdfMake();
        const comparison = this.subsidyDashboard.comparison || {};
        const currentPeriodLabel = this.subsidyPeriodLabel(this.subsidyPeriod);
        const comparisonPeriodLabel = this.subsidyPeriodLabel(comparison.period || this.subsidyComparePeriod);
        const rbd = this.subsidyDashboard.settlements?.[0]?.rbd || this.manualSubsidyForm.rbd || "-";
        const levelKeys = [...new Set([
          ...(this.subsidyDashboard.by_level || []).map((item) => item.key),
          ...(comparison.by_level || []).map((item) => item.key),
        ])];
        const familyKeys = [...new Set([
          ...(this.subsidyDashboard.by_family || []).map((item) => item.key),
          ...(comparison.by_family || []).map((item) => item.key),
        ])];
        const perStudentCycleKeys = [...new Set([
          ...(this.subsidyDashboard.per_student?.by_cycle || []).map((item) => item.key),
          ...(comparison.per_student?.by_cycle || []).map((item) => item.key),
        ])];
        const perStudentGradeKeys = [...new Set([
          ...(this.subsidyDashboard.per_student?.by_grade || []).map((item) => item.key),
          ...(comparison.per_student?.by_grade || []).map((item) => item.key),
        ])];
        const pieLevelKeys = [...new Set([
          ...(this.subsidyDashboard.pie?.by_level || []).map((item) => item.key),
          ...(comparison.pie?.by_level || []).map((item) => item.key),
        ])];
        const pieCourseKeys = [...new Set([
          ...(this.subsidyDashboard.pie?.by_course || []).map((item) => item.key),
          ...(comparison.pie?.by_course || []).map((item) => item.key),
        ])];
        const metricRows = this.subsidyComparisonRows.map((row) => {
          const delta = this.subsidyDelta(row.key);
          return [
            row.label,
            money(row.current || 0),
            money(row.previous || 0),
            money(delta.amount || 0),
            delta.percentage == null ? "-" : `${Number(delta.percentage).toLocaleString("es-CL")}%`,
          ];
        });
        const levelRows = levelKeys.map((key) => {
          const current = (this.subsidyDashboard.by_level || []).find((item) => item.key === key);
          const previous = (comparison.by_level || []).find((item) => item.key === key);
          return [
            current?.label || previous?.label || key,
            money(current?.amount || 0),
            money(previous?.amount || 0),
            money(Number(current?.amount || 0) - Number(previous?.amount || 0)),
          ];
        });
        const familyRows = familyKeys.map((key) => {
          const current = (this.subsidyDashboard.by_family || []).find((item) => item.key === key);
          const previous = (comparison.by_family || []).find((item) => item.key === key);
          return [
            current?.label || previous?.label || this.familyLabel(key),
            money(current?.net_amount || 0),
            money(previous?.net_amount || 0),
            money(Number(current?.net_amount || 0) - Number(previous?.net_amount || 0)),
          ];
        });
        const averageLabel = (item) => (item?.average_per_student == null ? "-" : money(item.average_per_student));
        const averageDelta = (current, previous) => {
          if (current?.average_per_student == null) return "-";

          return money(Number(current.average_per_student) - Number(previous?.average_per_student || 0));
        };
        const perStudentCycleRows = perStudentCycleKeys.map((key) => {
          const current = (this.subsidyDashboard.per_student?.by_cycle || []).find((item) => item.key === key);
          const previous = (comparison.per_student?.by_cycle || []).find((item) => item.key === key);
          return [
            current?.label || previous?.label || key,
            Number(current?.enrollment || 0).toLocaleString("es-CL"),
            money(current?.amount || 0),
            averageLabel(current),
            Number(previous?.enrollment || 0).toLocaleString("es-CL"),
            averageLabel(previous),
            averageDelta(current, previous),
          ];
        });
        const perStudentGradeRows = perStudentGradeKeys.map((key) => {
          const current = (this.subsidyDashboard.per_student?.by_grade || []).find((item) => item.key === key);
          const previous = (comparison.per_student?.by_grade || []).find((item) => item.key === key);
          return [
            current?.label || previous?.label || key,
            Number(current?.enrollment || 0).toLocaleString("es-CL"),
            money(current?.amount || 0),
            averageLabel(current),
            Number(previous?.enrollment || 0).toLocaleString("es-CL"),
            averageLabel(previous),
            averageDelta(current, previous),
          ];
        });
        const pieLevelRows = pieLevelKeys.map((key) => {
          const current = (this.subsidyDashboard.pie?.by_level || []).find((item) => item.key === key);
          const previous = (comparison.pie?.by_level || []).find((item) => item.key === key);
          return [
            current?.label || previous?.label || key,
            money(current?.amount || 0),
            money(previous?.amount || 0),
            money(Number(current?.amount || 0) - Number(previous?.amount || 0)),
          ];
        });
        const pieComponentDefinitions = [
          ["base_amount", "Subvención PIE base"],
          ["rurality_amount", "Ruralidad"],
          ["zone_increment_amount", "Incremento zona"],
          ["law_19410_amount", "Adicional Ley 19.410"],
          ["law_19464_amount", "No docente Ley 19.464"],
          ["non_teacher_zone_amount", "Incremento zona no docente"],
          ["non_teacher_total", "Total no docente"],
          ["law_19933_reference", "Ley 19.933 (referencial)"],
        ];
        const pieComponentRows = pieComponentDefinitions.map(([key, label]) => [
          label,
          money(this.subsidyDashboard.pie?.components?.[key] || 0),
          money(comparison.pie?.components?.[key] || 0),
          money(
            Number(this.subsidyDashboard.pie?.components?.[key] || 0)
              - Number(comparison.pie?.components?.[key] || 0),
          ),
        ]);
        const pieCourseRows = pieCourseKeys.map((key) => {
          const current = (this.subsidyDashboard.pie?.by_course || []).find((item) => item.key === key);
          const previous = (comparison.pie?.by_course || []).find((item) => item.key === key);
          return [
            current?.label || previous?.label || key,
            Number(current?.enrollment || 0).toLocaleString("es-CL", { maximumFractionDigits: 4 }),
            money(current?.amount || 0),
            money(previous?.amount || 0),
            money(Number(current?.amount || 0) - Number(previous?.amount || 0)),
          ];
        });
        const annualRows = (this.subsidyDashboard.annual || []).map((item) => [
          this.subsidyPeriodLabel(item.period),
          String(item.settlement_count || 0),
          money(item.net_liquidated || 0),
          money(item.transferred_total || 0),
          money(item.income_total || 0),
          money(item.pie_total || 0),
        ]);
        annualRows.push([
          `Total ${this.subsidyYear}`,
          String(this.subsidyAnnualTotals.settlement_count),
          money(this.subsidyAnnualTotals.net_liquidated),
          money(this.subsidyAnnualTotals.transferred_total),
          money(this.subsidyAnnualTotals.income_total),
          money(this.subsidyAnnualTotals.pie_total),
        ]);
        const tableLayout = {
          hLineColor: () => "#dce3ec",
          vLineColor: () => "#dce3ec",
          fillColor: (rowIndex) => (rowIndex === 0 ? "#405189" : rowIndex % 2 === 0 ? "#f5f7fb" : null),
          paddingLeft: () => 7,
          paddingRight: () => 7,
          paddingTop: () => 6,
          paddingBottom: () => 6,
        };
        const table = (headers, widths, rows) => ({
          table: {
            headerRows: 1,
            widths,
            body: [
              headers.map((text) => ({ text, color: "#ffffff", bold: true, fontSize: 8 })),
              ...rows.map((row) => row.map((text, index) => ({
                text,
                alignment: index === 0 ? "left" : "right",
                fontSize: 8,
                color: "#344054",
              }))),
            ],
          },
          layout: tableLayout,
        });

        const documentDefinition = {
          pageSize: "A4",
          pageMargins: [36, 42, 36, 42],
          defaultStyle: { fontSize: 9, color: "#344054" },
          header: {
            margin: [36, 18, 36, 0],
            columns: [
              { text: "CONTABILIDAD / SUBVENCIONES", color: "#405189", bold: true, fontSize: 8 },
              { text: `RBD ${rbd}`, alignment: "right", color: "#667085", fontSize: 8 },
            ],
          },
          footer: (currentPage, pageCount) => ({
            margin: [36, 0, 36, 18],
            columns: [
              { text: "Informe interno de gestión", color: "#98a2b3", fontSize: 7 },
              { text: `Página ${currentPage} de ${pageCount}`, alignment: "right", color: "#98a2b3", fontSize: 7 },
            ],
          }),
          content: [
            { text: "Informe comparativo de subvenciones", fontSize: 19, bold: true, color: "#25324b" },
            {
              margin: [0, 5, 0, 18],
              columns: [
                { text: `${currentPeriodLabel} versus ${comparisonPeriodLabel}`, color: "#667085", fontSize: 10 },
                { text: `Emitido: ${new Date().toLocaleDateString("es-CL")}`, alignment: "right", color: "#667085", fontSize: 8 },
              ],
            },
            {
              columns: [
                {
                  width: "*",
                  stack: [
                    { text: "Líquido actual", color: "#667085", fontSize: 8 },
                    { text: money(this.subsidyDashboard.metrics?.net_liquidated || 0), color: "#25324b", bold: true, fontSize: 14, margin: [0, 3, 0, 0] },
                  ],
                  margin: [10, 10, 10, 10],
                },
                {
                  width: "*",
                  stack: [
                    { text: "Ingreso contabilizado", color: "#667085", fontSize: 8 },
                    { text: money(this.subsidyDashboard.metrics?.income_total || 0), color: "#25324b", bold: true, fontSize: 14, margin: [0, 3, 0, 0] },
                  ],
                  margin: [10, 10, 10, 10],
                },
                {
                  width: "*",
                  stack: [
                    { text: "Sin asignar", color: "#667085", fontSize: 8 },
                    { text: money(this.subsidyDashboard.metrics?.unallocated_total || 0), color: "#25324b", bold: true, fontSize: 14, margin: [0, 3, 0, 0] },
                  ],
                  margin: [10, 10, 10, 10],
                },
              ],
              columnGap: 8,
              margin: [0, 0, 0, 18],
            },
            { text: "Comparación general", style: "sectionTitle" },
            table(["Indicador", currentPeriodLabel, comparisonPeriodLabel, "Variación", "%"], ["*", 82, 82, 82, 44], metricRows),
            { text: "Aporte por nivel educativo", style: "sectionTitle" },
            table(["Nivel", currentPeriodLabel, comparisonPeriodLabel, "Variación"], ["*", 95, 95, 95], levelRows),
            { text: "Composición por subvención", style: "sectionTitle" },
            table(["Subvención", currentPeriodLabel, comparisonPeriodLabel, "Variación"], ["*", 95, 95, 95], familyRows),
            { text: "Aporte promedio por alumno", style: "sectionTitle", pageBreak: "before" },
            {
              text: "El promedio corresponde al aporte educacional asignado dividido por la matrícula informada en un único anexo de referencia. Pro-Retención aumenta el aporte del nivel, pero su nómina individual no vuelve a sumar matrícula.",
              margin: [0, 0, 0, 10],
              color: "#667085",
              fontSize: 8,
            },
            { text: "Promedio por ciclo educativo", style: "sectionTitle" },
            table(
              ["Ciclo", "Matr. act.", "Aporte actual", "Prom. actual", "Matr. comp.", "Prom. comp.", "Variación"],
              ["*", 47, 70, 70, 47, 70, 70],
              perStudentCycleRows,
            ),
            { text: "Promedio por nivel o curso", style: "sectionTitle" },
            table(
              ["Nivel", "Matr. act.", "Aporte actual", "Prom. actual", "Matr. comp.", "Prom. comp.", "Variación"],
              ["*", 47, 70, 70, 47, 70, 70],
              perStudentGradeRows,
            ),
            { text: "Detalle PIE (informativo)", style: "sectionTitle", pageBreak: "before" },
            {
              columns: [
                {
                  width: "*",
                  stack: [
                    { text: `Total ${currentPeriodLabel}`, color: "#667085", fontSize: 8 },
                    { text: money(this.subsidyDashboard.pie?.total || 0), color: "#25324b", bold: true, fontSize: 14, margin: [0, 3, 0, 0] },
                  ],
                  margin: [10, 10, 10, 10],
                },
                {
                  width: "*",
                  stack: [
                    { text: `Total ${comparisonPeriodLabel}`, color: "#667085", fontSize: 8 },
                    { text: money(comparison.pie?.total || 0), color: "#25324b", bold: true, fontSize: 14, margin: [0, 3, 0, 0] },
                  ],
                  margin: [10, 10, 10, 10],
                },
                {
                  width: "*",
                  stack: [
                    { text: "Filas de detalle actual", color: "#667085", fontSize: 8 },
                    { text: String(this.subsidyDashboard.pie?.row_count || 0), color: "#25324b", bold: true, fontSize: 14, margin: [0, 3, 0, 0] },
                  ],
                  margin: [10, 10, 10, 10],
                },
              ],
              columnGap: 8,
              margin: [0, 0, 0, 12],
            },
            table(["Nivel PIE", currentPeriodLabel, comparisonPeriodLabel, "Variación"], ["*", 95, 95, 95], pieLevelRows),
            { text: "Componentes informados en el anexo PIE", style: "sectionTitle" },
            table(["Componente", currentPeriodLabel, comparisonPeriodLabel, "Variación"], ["*", 95, 95, 95], pieComponentRows),
            { text: "Consolidado PIE por curso", style: "sectionTitle", pageBreak: "before" },
            table(
              ["Curso", "Matrícula", currentPeriodLabel, comparisonPeriodLabel, "Variación"],
              ["*", 55, 88, 88, 88],
              pieCourseRows,
            ),
            {
              text: "Cada curso consolida sus distintas glosas PIE. La matrícula y los montos corresponden a la suma de las filas de detalle del anexo.",
              margin: [0, 12, 0, 0],
              color: "#667085",
              fontSize: 8,
            },
            { text: `Resumen mensual ${this.subsidyYear}`, style: "sectionTitle", pageBreak: "before" },
            table(["Mes", "Liq.", "Líquido", "Transferido", "Contabilizado", "PIE info."], ["*", 35, 74, 74, 74, 68], annualRows),
            {
              text: "Criterio: el líquido corresponde a las liquidaciones MINEDUC del período; el ingreso contabilizado corresponde a transferencias registradas y no anuladas. PIE informativo no se suma nuevamente.",
              margin: [0, 16, 0, 0],
              color: "#667085",
              fontSize: 8,
            },
          ],
          styles: {
            sectionTitle: { fontSize: 11, bold: true, color: "#25324b", margin: [0, 18, 0, 7] },
          },
        };

        pdfMake.createPdf(documentDefinition).download(`informe-subvenciones-${this.subsidyPeriod}-vs-${comparison.period || this.subsidyComparePeriod}.pdf`);
      } catch (error) {
        await Swal.fire("No se pudo generar el PDF", formatAccountingError(error, "Intenta nuevamente."), "error");
      } finally {
        this.downloadingSubsidyPdf = false;
      }
    },
    hasAccountingPermission(permission) {
      const permissions = this.catalogs.permissions || [];
      return permissions.includes("__superadmin__")
        || permissions.includes("contabilidad.admin")
        || permissions.includes(permission);
    },
    selectSubsidyFiles() {
      this.$refs.subsidyFiles?.click();
    },
    async uploadSubsidyFiles(event) {
      const files = Array.from(event.target.files || []);
      event.target.value = "";
      if (!files.length) return;

      const formData = new FormData();
      files.forEach((file) => formData.append("files[]", file));
      formData.append("period", this.subsidyPeriod);
      this.importingSubsidies = true;
      try {
        const response = await axios.post("/api/contabilidad/subvenciones/importar", formData);
        const duplicates = response.data.duplicates?.length || 0;
        await Swal.fire(
          "Importación completada",
          duplicates
            ? `Se procesaron los archivos nuevos y se omitieron ${duplicates} duplicado(s).`
            : "Las liquidaciones y anexos fueron procesados correctamente.",
          duplicates ? "warning" : "success",
        );
        await this.loadSubsidies();
      } catch (error) {
        await Swal.fire("Error de importación", formatAccountingError(error, "No se pudieron procesar los archivos MINEDUC."), "error");
      } finally {
        this.importingSubsidies = false;
      }
    },
    openManualSubsidy() {
      this.manualSubsidyForm = {
        rbd: "6830",
        period: this.subsidyPeriod,
        subsidy_type: "normal",
        funding_source_id: "",
        gross_amount: "",
        transferred_amount: "",
        payment_date: "",
        source_reference: "",
      };
      this.manualSubsidyVisible = true;
    },
    async saveManualSubsidy() {
      this.saving = true;
      try {
        await axios.post("/api/contabilidad/subvenciones/manual", this.manualSubsidyForm);
        this.manualSubsidyVisible = false;
        await Swal.fire("Guardado", "El monto de subvención fue registrado para revisión.", "success");
        await this.loadSubsidies();
      } catch (error) {
        await Swal.fire("Error", formatAccountingError(error), "error");
      } finally {
        this.saving = false;
      }
    },
    familyLabel(type) {
      return {
        normal: "Subvención Normal",
        sep_prioritario: "SEP Prioritario",
        sep_preferente: "SEP Preferente",
        pro_retention: "Subvención Pro-Retención",
        school_bonus: "Nómina Bono Escolar",
        cd_brp: "CD-BRP",
        cd_asignacion_tramo: "CD-ASIGNACIÓN POR TRAMO",
        otro: "Otro ingreso",
      }[type] || type;
    },
    settlementHasEducationalBreakdown(settlement) {
      return (settlement?.lines || []).some((line) => !line.informative && line.education_allocable);
    },
    settlementAllocated(settlement) {
      return (settlement.lines || []).filter((line) => !line.informative && line.education_allocable).reduce(
        (total, line) => total + (line.allocations || []).reduce((lineTotal, allocation) => lineTotal + Number(allocation.amount || 0), 0),
        0,
      );
    },
    settlementLevelSummary(settlement) {
      const summary = {};
      (settlement.lines || []).filter((line) => !line.informative && line.education_allocable).forEach((line) => {
        (line.allocations || []).forEach((allocation) => {
          const type = allocation.education_level?.type || "sin_asignar";
          const label = {
            parvularia: "Educación Parvularia",
            basica: "Educación Básica",
            media: "Enseñanza Media",
            sin_asignar: "Sin asignar",
          }[type];
          summary[label] = (summary[label] || 0) + Number(allocation.amount || 0);
        });
      });
      return Object.entries(summary).map(([label, amount]) => ({ label, amount }));
    },
    proRetentionLine(settlement) {
      return (settlement?.lines || []).find((line) => line.concept_code === "pro_retention") || null;
    },
    proRetentionRows(settlement) {
      return this.proRetentionLine(settlement)?.allocations || [];
    },
    proRetentionData(allocation) {
      return allocation?.source_payload?._pro_retention || {};
    },
    proRetentionCourse(allocation) {
      const data = this.proRetentionData(allocation);
      return data.course_label || allocation.education_label || allocation.education_level?.name || "Sin curso";
    },
    schoolBonusLine(settlement) {
      return (settlement?.lines || []).find((line) => line.concept_code === "school_bonus") || null;
    },
    schoolBonusRows(settlement) {
      return this.schoolBonusLine(settlement)?.allocations || [];
    },
    schoolBonusData(allocation) {
      return allocation?.source_payload?._school_bonus || {};
    },
    schoolBonusComponents(settlement) {
      return this.schoolBonusLine(settlement)?.metadata?.bonus_components || {};
    },
    pieLine(settlement) {
      return (settlement?.lines || []).find((line) => line.concept_code === "pie_breakdown") || null;
    },
    pieRows(settlement) {
      return this.pieLine(settlement)?.allocations || [];
    },
    pieData(allocation) {
      return allocation?.source_payload?._pie || {};
    },
    pieNumber(value) {
      return Number(value || 0).toLocaleString("es-CL", { maximumFractionDigits: 4 });
    },
    pieSummaryTotal(items, field) {
      return (items || []).reduce((total, item) => total + Number(item?.[field] || 0), 0);
    },
    pieCourse(allocation) {
      const level = allocation.education_level?.name || allocation.education_label || "Sin nivel";
      const course = [allocation.grade_code ? `${allocation.grade_code}°` : "", allocation.course_letter || ""].filter(Boolean).join(" ");
      return course ? `${level} · ${course}` : level;
    },
    showPieDetail() {
      const settlement = (this.subsidyDashboard.settlements || []).find((item) => this.pieLine(item));
      if (!settlement) return;
      this.selectedSubsidy = settlement;
      this.subsidyDetailVisible = true;
    },
    showSubsidyDetail(settlement) {
      this.selectedSubsidy = settlement;
      this.subsidyDetailVisible = true;
    },
    async approveSubsidy(settlement) {
      const result = await Swal.fire({
        title: "Aprobar liquidación",
        text: "Puedes informar el monto transferido para comprobar la diferencia con el líquido.",
        input: "number",
        inputValue: settlement.transferred_amount ?? settlement.net_amount,
        inputAttributes: { min: 0, step: 1 },
        showCancelButton: true,
        confirmButtonText: "Aprobar",
        cancelButtonText: "Cancelar",
      });
      if (!result.isConfirmed) return;

      try {
        await axios.post(`/api/contabilidad/subvenciones/${settlement.id}/aprobar`, {
          transferred_amount: result.value === "" ? null : Number(result.value),
        });
        await Swal.fire("Aprobada", "La liquidación quedó disponible para contabilizar.", "success");
        await this.loadSubsidies();
      } catch (error) {
        await Swal.fire("No se pudo aprobar", formatAccountingError(error), "error");
      }
    },
    openPostSubsidy(settlement) {
      this.selectedSubsidy = settlement;
      this.subsidyPostForm = {
        received_at: settlement.payment_date || `${this.subsidyPeriod}-01`,
        transferred_amount: settlement.transferred_amount ?? settlement.net_amount,
        manual_account_id: "",
        bank_account_id: "",
        cost_center_id: "",
        document_reference: settlement.source_reference || settlement.code,
        notes: "",
      };
      this.subsidyPostVisible = true;
    },
    async postSubsidy() {
      if (!this.selectedSubsidy) return;
      this.saving = true;
      try {
        await axios.post(
          `/api/contabilidad/subvenciones/${this.selectedSubsidy.id}/contabilizar`,
          this.subsidyPostForm,
        );
        this.subsidyPostVisible = false;
        await Swal.fire("Contabilizada", "Se creó un único ingreso, su movimiento bancario y el asiento contable.", "success");
        await this.loadSubsidies();
      } catch (error) {
        await Swal.fire("No se pudo contabilizar", formatAccountingError(error), "error");
      } finally {
        this.saving = false;
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
      if (["aprobado", "validado", "contabilizado", "pagado", "conciliado", "rendido", "activo", "confirmado", "presentado"].includes(status)) return "bg-success-subtle text-success";
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

      <template v-else-if="isSubsidies">
        <section class="content-card subsidy-toolbar subsidy-command-card">
          <div class="subsidy-period-filters">
            <label>
              <span class="toolbar-kicker">AÑO</span>
              <BFormSelect v-model="subsidyYear" class="subsidy-period-select" @change="changeSubsidyPeriod">
                <option v-for="year in subsidyYearOptions" :key="year" :value="year">{{ year }}</option>
              </BFormSelect>
            </label>
            <label>
              <span class="toolbar-kicker">MES DE PAGO</span>
              <BFormSelect v-model="subsidyMonth" class="subsidy-period-select subsidy-month-select" @change="changeSubsidyPeriod">
                <option v-for="month in subsidyMonths" :key="month.value" :value="month.value">{{ month.text }}</option>
              </BFormSelect>
            </label>
            <label>
              <span class="toolbar-kicker">COMPARAR CON</span>
              <BFormInput v-model="subsidyComparePeriod" type="month" class="subsidy-period-select subsidy-compare-select" @change="loadSubsidies" />
            </label>
          </div>
          <div class="toolbar-actions">
            <input ref="subsidyFiles" class="d-none" type="file" multiple accept=".pdf,.xls,.html" @change="uploadSubsidyFiles" />
            <BButton
              variant="outline-secondary"
              :disabled="downloadingSubsidyPdf"
              @click="downloadSubsidyComparisonPdf"
            >
              <span v-if="downloadingSubsidyPdf" class="spinner-border spinner-border-sm"></span>
              <i v-else class="bx bxs-file-pdf"></i> Informe PDF
            </BButton>
            <BButton
              v-if="hasAccountingPermission('contabilidad.subvenciones.importar')"
              variant="outline-primary"
              :disabled="importingSubsidies"
              @click="openManualSubsidy"
            >
              <i class="bx bx-edit"></i> Monto simple
            </BButton>
            <BButton
              v-if="hasAccountingPermission('contabilidad.subvenciones.importar')"
              variant="primary"
              :disabled="importingSubsidies"
              @click="selectSubsidyFiles"
            >
              <span v-if="importingSubsidies" class="spinner-border spinner-border-sm"></span>
              <i v-else class="bx bx-upload"></i> Importar respaldo
            </BButton>
          </div>
          <div class="subsidy-period-note">
            <i class="bx bx-calendar-check"></i>
            <span>Importa órdenes, anexos, Pro-Retención o nóminas de Bono Escolar de <strong>{{ subsidyPeriodLabel(subsidyPeriod) }}</strong>. El sistema valida mes y año antes de guardar.</span>
          </div>
        </section>

        <div class="metric-grid subsidy-metrics">
          <article class="metric-card subsidy-metric-card">
            <div class="metric-icon metric-icon-1"><i class="bx bx-building-house"></i></div>
            <div class="subsidy-metric-copy">
              <span>Ingreso contabilizado</span>
              <strong>{{ money(subsidyDashboard.metrics.income_total) }}</strong>
              <small class="subsidy-delta" :class="subsidyDeltaClass('income_total')">{{ subsidyDeltaLabel('income_total') }} vs. {{ subsidyPeriodLabel(subsidyDashboard.comparison?.period) }}</small>
            </div>
          </article>
          <article class="metric-card subsidy-metric-card">
            <div class="metric-icon metric-icon-3"><i class="bx bx-file"></i></div>
            <div class="subsidy-metric-copy">
              <span>Líquido de liquidaciones</span>
              <strong>{{ money(subsidyDashboard.metrics.net_liquidated) }}</strong>
              <small class="subsidy-delta" :class="subsidyDeltaClass('net_liquidated')">{{ subsidyDeltaLabel('net_liquidated') }} vs. {{ subsidyPeriodLabel(subsidyDashboard.comparison?.period) }}</small>
            </div>
          </article>
          <article class="metric-card subsidy-metric-card">
            <div class="metric-icon metric-icon-4"><i class="bx bx-layer"></i></div>
            <div class="subsidy-metric-copy">
              <span>Asignado a niveles</span>
              <strong>{{ money(subsidyDashboard.metrics.allocated_total) }}</strong>
              <small class="subsidy-delta" :class="subsidyDeltaClass('allocated_total')">{{ subsidyDeltaLabel('allocated_total') }} vs. {{ subsidyPeriodLabel(subsidyDashboard.comparison?.period) }}</small>
            </div>
          </article>
          <article class="metric-card subsidy-metric-card" :class="{ 'subsidy-metric-warning': Number(subsidyDashboard.metrics.unallocated_total) > 0 }">
            <div class="metric-icon metric-icon-2"><i class="bx bx-error-circle"></i></div>
            <div class="subsidy-metric-copy">
              <span>Sin asignación educativa</span>
              <strong>{{ money(subsidyDashboard.metrics.unallocated_total) }}</strong>
              <small>{{ Number(subsidyDashboard.metrics.unallocated_total) === 0 ? 'Distribución completa' : 'Requiere revisar anexos o catálogo' }}</small>
            </div>
          </article>
        </div>

        <section class="content-card subsidy-comparison-card">
          <div class="card-heading">
            <div>
              <span>COMPARACIÓN</span>
              <h2>{{ subsidyPeriodLabel(subsidyPeriod) }} frente a {{ subsidyPeriodLabel(subsidyDashboard.comparison?.period) }}</h2>
            </div>
            <i class="bx bx-git-compare"></i>
          </div>
          <div class="table-responsive">
            <table class="table accounting-table align-middle mb-0">
              <thead><tr><th>Indicador</th><th>Período actual</th><th>Período comparado</th><th>Variación</th></tr></thead>
              <tbody>
                <tr v-for="row in subsidyComparisonRows" :key="row.key">
                  <td><strong>{{ row.label }}</strong></td>
                  <td>{{ money(row.current) }}</td>
                  <td>{{ money(row.previous) }}</td>
                  <td><span class="subsidy-delta subsidy-delta-table" :class="subsidyDeltaClass(row.key)">{{ subsidyDeltaLabel(row.key) }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <div class="dashboard-grid subsidy-summary-grid">
          <section class="content-card summary-panel">
            <div class="card-heading"><div><span>DISTRIBUCIÓN</span><h2>Aporte por nivel educativo</h2></div></div>
            <div class="summary-list subsidy-level-list">
              <div v-for="item in subsidyDashboard.by_level" :key="item.key" class="subsidy-level-item">
                <div>
                  <span>{{ item.label }} <small>{{ item.percentage }}%</small></span>
                  <div class="subsidy-level-track"><span :style="{ width: `${item.percentage}%` }"></span></div>
                </div>
                <strong>{{ money(item.amount) }}</strong>
              </div>
              <div v-if="!subsidyDashboard.by_level.length" class="mini-empty">Importa anexos para obtener el desglose.</div>
            </div>
          </section>
          <section class="content-card summary-panel">
            <div class="card-heading"><div><span>LIQUIDACIONES</span><h2>Resumen por subvención</h2></div></div>
            <div class="summary-list">
              <div v-for="item in subsidyDashboard.by_family" :key="item.key">
                <span>{{ item.label }}</span>
                <strong>{{ money(item.net_amount) }}</strong>
              </div>
              <div v-if="!subsidyDashboard.by_family.length" class="mini-empty">Sin liquidaciones en el período.</div>
            </div>
          </section>
          <section class="content-card subsidy-control-card">
            <div class="card-heading"><div><span>CONTROL</span><h2>Cuadratura del mes</h2></div></div>
            <div class="subsidy-control-values">
              <div><span>Transferido informado</span><strong>{{ money(subsidyDashboard.metrics.transferred_total) }}</strong></div>
              <div><span>Diferencia liquidación/banco</span><strong :class="{ 'text-danger': Number(subsidyDashboard.metrics.difference_total) !== 0 }">{{ money(subsidyDashboard.metrics.difference_total) }}</strong></div>
              <div><span>Estado</span><strong :class="subsidyQuadrature.className">{{ subsidyQuadrature.label }}</strong></div>
            </div>
          </section>
        </div>

        <section class="content-card subsidy-per-student-card">
          <div class="card-heading">
            <div>
              <span>APORTE PROMEDIO</span>
              <h2>Cuánto aporta cada alumno</h2>
            </div>
            <small>{{ subsidyPeriodLabel(subsidyPeriod) }} · matrícula de referencia {{ Number(subsidyDashboard.per_student?.enrollment_total || 0).toLocaleString('es-CL') }}</small>
          </div>
          <div class="subsidy-per-student-note">
            <i class="bx bx-info-circle"></i>
            <span>Se divide el aporte asignado por la matrícula de un único anexo de referencia. La nómina Pro-Retención aporta al monto del nivel, pero no duplica alumnos.</span>
          </div>
          <div class="subsidy-cycle-grid">
            <article v-for="item in subsidyDashboard.per_student?.by_cycle || []" :key="item.key" class="subsidy-cycle-card">
              <span>{{ item.label }}</span>
              <strong>{{ perStudentAverage(item) }}</strong>
              <small>promedio por alumno</small>
              <div>
                <span>{{ Number(item.enrollment || 0).toLocaleString('es-CL') }} alumnos</span>
                <span>{{ money(item.amount) }} aportados</span>
              </div>
            </article>
            <div v-if="!subsidyDashboard.per_student?.by_cycle?.length" class="mini-empty">Falta una matrícula de referencia para calcular promedios.</div>
          </div>
          <div class="table-responsive">
            <table class="table accounting-table align-middle mb-0">
              <thead>
                <tr>
                  <th>Nivel / curso</th>
                  <th>Ciclo</th>
                  <th class="text-end">Matrícula</th>
                  <th class="text-end">Aporte del nivel</th>
                  <th class="text-end">Promedio por alumno</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in subsidyDashboard.per_student?.by_grade || []" :key="item.key">
                  <td><strong>{{ item.label }}</strong></td>
                  <td>{{ item.cycle_label }}</td>
                  <td class="text-end">{{ Number(item.enrollment || 0).toLocaleString('es-CL') }}</td>
                  <td class="text-end">{{ money(item.amount) }}</td>
                  <td class="text-end"><strong>{{ perStudentAverage(item) }}</strong></td>
                </tr>
                <tr v-if="!subsidyDashboard.per_student?.by_grade?.length">
                  <td colspan="5"><div class="mini-empty">Sin datos suficientes para el período.</div></td>
                </tr>
              </tbody>
              <tfoot v-if="subsidyDashboard.per_student?.by_grade?.length">
                <tr>
                  <th colspan="2">Total con asignación educativa</th>
                  <th class="text-end">{{ Number(subsidyDashboard.per_student.enrollment_total || 0).toLocaleString('es-CL') }}</th>
                  <th class="text-end">{{ money(subsidyDashboard.per_student.allocated_amount) }}</th>
                  <th class="text-end">{{ subsidyDashboard.per_student.enrollment_total ? money(Number(subsidyDashboard.per_student.allocated_amount || 0) / Number(subsidyDashboard.per_student.enrollment_total)) : '-' }}</th>
                </tr>
              </tfoot>
            </table>
          </div>
        </section>

        <section class="content-card subsidy-pie-card">
          <div class="card-heading">
            <div>
              <span>PROGRAMA DE INTEGRACIÓN ESCOLAR</span>
              <h2>Detalle PIE del período</h2>
            </div>
            <BButton
              variant="outline-primary"
              size="sm"
              :disabled="!Number(subsidyDashboard.pie?.row_count)"
              @click="showPieDetail"
            ><i class="bx bx-list-ul"></i> Ver {{ subsidyDashboard.pie?.row_count || 0 }} filas</BButton>
          </div>
          <div v-if="Number(subsidyDashboard.pie?.total)" class="subsidy-pie-body">
            <div class="subsidy-pie-overview">
              <div>
                <span>Total PIE informativo</span>
                <strong>{{ money(subsidyDashboard.pie.total) }}</strong>
                <small>No se suma nuevamente al líquido de Subvención Normal.</small>
              </div>
              <div>
                <span>Subvención base</span>
                <strong>{{ money(subsidyDashboard.pie.components?.base_amount) }}</strong>
              </div>
              <div>
                <span>Incremento zona</span>
                <strong>{{ money(subsidyDashboard.pie.components?.zone_increment_amount) }}</strong>
              </div>
              <div>
                <span>Total no docente</span>
                <strong>{{ money(subsidyDashboard.pie.components?.non_teacher_total) }}</strong>
                <small>Dato complementario del anexo.</small>
              </div>
            </div>
            <div class="subsidy-pie-consolidations">
              <section class="subsidy-pie-consolidated">
                <div class="subsidy-pie-section-heading">
                  <div><span>CONSOLIDADO</span><h3>Por nivel educativo</h3></div>
                  <small>{{ subsidyDashboard.pie.by_level?.length || 0 }} niveles</small>
                </div>
                <div class="table-responsive">
                  <table class="table accounting-table align-middle mb-0">
                    <thead><tr><th>Nivel</th><th>Matrícula</th><th>Glosas</th><th>Base PIE</th><th>Zona</th><th>Total PIE</th></tr></thead>
                    <tbody>
                      <tr v-for="item in subsidyDashboard.pie.by_level || []" :key="item.key">
                        <td><strong>{{ item.label }}</strong><small class="d-block text-muted">{{ item.percentage }}% del PIE</small></td>
                        <td>{{ pieNumber(item.enrollment) }}</td>
                        <td>{{ item.detail_count }}</td>
                        <td>{{ money(item.base_amount) }}</td>
                        <td>{{ money(item.zone_increment_amount) }}</td>
                        <td><strong>{{ money(item.amount) }}</strong></td>
                      </tr>
                    </tbody>
                    <tfoot>
                      <tr>
                        <th>Total</th>
                        <th>{{ pieNumber(pieSummaryTotal(subsidyDashboard.pie.by_level, 'enrollment')) }}</th>
                        <th>{{ pieNumber(pieSummaryTotal(subsidyDashboard.pie.by_level, 'detail_count')) }}</th>
                        <th>{{ money(pieSummaryTotal(subsidyDashboard.pie.by_level, 'base_amount')) }}</th>
                        <th>{{ money(pieSummaryTotal(subsidyDashboard.pie.by_level, 'zone_increment_amount')) }}</th>
                        <th>{{ money(pieSummaryTotal(subsidyDashboard.pie.by_level, 'amount')) }}</th>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </section>

              <section class="subsidy-pie-consolidated">
                <div class="subsidy-pie-section-heading">
                  <div><span>CONSOLIDADO</span><h3>Por curso y sección</h3></div>
                  <small>{{ subsidyDashboard.pie.by_course?.length || 0 }} cursos</small>
                </div>
                <div class="table-responsive subsidy-pie-course-table">
                  <table class="table accounting-table align-middle mb-0">
                    <thead><tr><th>Curso</th><th>Matrícula</th><th>Glosas</th><th>Base PIE</th><th>Zona</th><th>Total PIE</th></tr></thead>
                    <tbody>
                      <tr v-for="item in subsidyDashboard.pie.by_course || []" :key="item.key">
                        <td><strong>{{ item.label }}</strong><small class="d-block text-muted">{{ item.percentage }}% del PIE</small></td>
                        <td>{{ pieNumber(item.enrollment) }}</td>
                        <td>{{ item.detail_count }}</td>
                        <td>{{ money(item.base_amount) }}</td>
                        <td>{{ money(item.zone_increment_amount) }}</td>
                        <td><strong>{{ money(item.amount) }}</strong></td>
                      </tr>
                    </tbody>
                    <tfoot>
                      <tr>
                        <th>Total</th>
                        <th>{{ pieNumber(pieSummaryTotal(subsidyDashboard.pie.by_course, 'enrollment')) }}</th>
                        <th>{{ pieNumber(pieSummaryTotal(subsidyDashboard.pie.by_course, 'detail_count')) }}</th>
                        <th>{{ money(pieSummaryTotal(subsidyDashboard.pie.by_course, 'base_amount')) }}</th>
                        <th>{{ money(pieSummaryTotal(subsidyDashboard.pie.by_course, 'zone_increment_amount')) }}</th>
                        <th>{{ money(pieSummaryTotal(subsidyDashboard.pie.by_course, 'amount')) }}</th>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </section>
              <div v-if="!subsidyDashboard.pie.by_course?.length" class="mini-empty">El anexo no incluye filas asignables por curso.</div>
            </div>
          </div>
          <div v-else class="subsidy-pie-empty">
            <i class="bx bx-puzzle"></i>
            <div><strong>Sin anexo PIE en este período</strong><span>Importa el archivo “Anexo Detalle PIE” para ver su distribución.</span></div>
          </div>
        </section>

        <section class="content-card subsidy-annual-card">
          <div class="card-heading">
            <div><span>VISIÓN ANUAL</span><h2>Liquidaciones mensuales {{ subsidyYear }}</h2></div>
            <small>Selecciona un mes para abrir su detalle</small>
          </div>
          <div class="subsidy-annual-grid">
            <button
              v-for="item in subsidyDashboard.annual || []"
              :key="item.period"
              type="button"
              class="subsidy-annual-row"
              :class="{ active: item.period === subsidyPeriod }"
              @click="selectSubsidyAnnualMonth(item)"
            >
              <span class="subsidy-annual-month">{{ item.label }}</span>
              <span class="subsidy-annual-bar"><span :style="{ width: subsidyAnnualBarWidth(item) }"></span></span>
              <strong>{{ money(item.net_liquidated) }}</strong>
              <small>{{ item.settlement_count }} liquidación(es)</small>
            </button>
          </div>
          <div class="subsidy-annual-master">
            <div class="subsidy-pie-section-heading">
              <div><span>TABLA MAESTRA</span><h3>Ingreso anual por mes</h3></div>
              <small>Liquidado, transferido y contabilizado</small>
            </div>
            <div class="table-responsive">
              <table class="table accounting-table align-middle mb-0">
                <thead>
                  <tr>
                    <th>Mes</th>
                    <th class="text-end">Liquidaciones</th>
                    <th class="text-end">Líquido</th>
                    <th class="text-end">Transferido</th>
                    <th class="text-end">Contabilizado</th>
                    <th class="text-end">PIE informativo</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="item in subsidyDashboard.annual || []"
                    :key="`annual-${item.period}`"
                    :class="{ 'table-active': item.period === subsidyPeriod }"
                    role="button"
                    @click="selectSubsidyAnnualMonth(item)"
                  >
                    <td><strong>{{ subsidyPeriodLabel(item.period) }}</strong></td>
                    <td class="text-end">{{ item.settlement_count }}</td>
                    <td class="text-end">{{ money(item.net_liquidated) }}</td>
                    <td class="text-end">{{ money(item.transferred_total) }}</td>
                    <td class="text-end">{{ money(item.income_total) }}</td>
                    <td class="text-end">{{ money(item.pie_total) }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <th>Total {{ subsidyYear }}</th>
                    <th class="text-end">{{ subsidyAnnualTotals.settlement_count }}</th>
                    <th class="text-end">{{ money(subsidyAnnualTotals.net_liquidated) }}</th>
                    <th class="text-end">{{ money(subsidyAnnualTotals.transferred_total) }}</th>
                    <th class="text-end">{{ money(subsidyAnnualTotals.income_total) }}</th>
                    <th class="text-end">{{ money(subsidyAnnualTotals.pie_total) }}</th>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </section>

        <section class="content-card records-card">
          <div class="records-toolbar">
            <div><span class="toolbar-kicker">TRAZABILIDAD</span><h2>Liquidaciones <span class="record-count">{{ subsidyDashboard.settlements.length }}</span></h2></div>
            <BButton variant="light" class="icon-action" title="Actualizar" @click="loadSubsidies"><i class="bx bx-refresh"></i></BButton>
          </div>
          <div class="table-responsive">
            <table class="table accounting-table align-middle mb-0">
              <thead>
                <tr>
                  <th>Liquidación</th>
                  <th>Líquido</th>
                  <th>Transferido</th>
                  <th>Por nivel</th>
                  <th>Diferencia</th>
                  <th>Estado</th>
                  <th class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="settlement in subsidyDashboard.settlements" :key="settlement.id">
                  <td><strong>{{ familyLabel(settlement.subsidy_type) }}</strong><small class="d-block text-muted">{{ settlement.code }}</small></td>
                  <td>{{ money(settlement.net_amount) }}</td>
                  <td>{{ settlement.transferred_amount == null ? 'Pendiente' : money(settlement.transferred_amount) }}</td>
                  <td>{{ settlementHasEducationalBreakdown(settlement) ? money(settlementAllocated(settlement)) : 'No aplica' }}</td>
                  <td :class="{ 'text-danger fw-semibold': Number(settlement.difference_amount) !== 0 }">{{ settlement.difference_amount == null ? '-' : money(settlement.difference_amount) }}</td>
                  <td><span class="badge rounded-pill" :class="badgeClass(settlement.status)">{{ settlement.status }}</span></td>
                  <td class="text-end">
                    <div class="row-actions">
                      <button type="button" title="Ver detalle" @click="showSubsidyDetail(settlement)"><i class="bx bx-show"></i></button>
                      <button
                        v-if="settlement.status === 'validado' && hasAccountingPermission('contabilidad.subvenciones.aprobar')"
                        type="button"
                        title="Aprobar"
                        @click="approveSubsidy(settlement)"
                      ><i class="bx bx-check"></i></button>
                      <button
                        v-if="settlement.status === 'aprobado'
                          && hasAccountingPermission('contabilidad.subvenciones.contabilizar')
                          && hasAccountingPermission('contabilidad.subvenciones.conciliar')"
                        type="button"
                        title="Contabilizar"
                        @click="openPostSubsidy(settlement)"
                      ><i class="bx bx-transfer"></i></button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!subsidyDashboard.settlements.length">
                  <td colspan="7"><div class="empty-state"><i class="bx bx-file"></i><strong>No hay liquidaciones en este período</strong><span>Importa la orden de pago y sus anexos MINEDUC.</span></div></td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <BModal v-model="manualSubsidyVisible" centered hide-footer title="Registrar monto simple">
          <form @submit.prevent="saveManualSubsidy">
            <div class="accounting-form-grid">
              <label><span>RBD *</span><BFormInput v-model="manualSubsidyForm.rbd" required /></label>
              <label><span>Período *</span><BFormInput v-model="manualSubsidyForm.period" type="month" required /></label>
              <label>
                <span>Tipo *</span>
                <BFormSelect v-model="manualSubsidyForm.subsidy_type" required>
                  <option value="normal">Subvención Normal</option>
                  <option value="sep_prioritario">SEP Prioritario</option>
                  <option value="sep_preferente">SEP Preferente</option>
                  <option value="pro_retention">Subvención Pro-Retención</option>
                  <option value="school_bonus">Nómina Bono Escolar</option>
                  <option value="cd_brp">CD-BRP</option>
                  <option value="cd_asignacion_tramo">CD-ASIGNACIÓN POR TRAMO</option>
                  <option value="otro">Otro ingreso</option>
                </BFormSelect>
              </label>
              <label><span>Fuente</span><BFormSelect v-model="manualSubsidyForm.funding_source_id"><option value="">Automática</option><option v-for="source in catalogs.data.funding_sources || []" :key="source.id" :value="source.id">{{ source.name }}</option></BFormSelect></label>
              <label><span>Monto bruto *</span><BFormInput v-model="manualSubsidyForm.gross_amount" type="number" min="1" required /></label>
              <label><span>Monto transferido</span><BFormInput v-model="manualSubsidyForm.transferred_amount" type="number" min="0" /></label>
              <label><span>Fecha transferencia</span><BFormInput v-model="manualSubsidyForm.payment_date" type="date" /></label>
              <label><span>Referencia</span><BFormInput v-model="manualSubsidyForm.source_reference" /></label>
            </div>
            <footer class="modal-actions"><BButton variant="light" type="button" @click="manualSubsidyVisible = false">Cancelar</BButton><BButton variant="primary" type="submit" :disabled="saving">Guardar para revisión</BButton></footer>
          </form>
        </BModal>

        <BModal v-model="subsidyDetailVisible" size="xl" centered scrollable hide-footer title="Detalle de liquidación">
          <template v-if="selectedSubsidy">
            <div class="subsidy-detail-heading">
              <div><span>Subvención</span><strong>{{ familyLabel(selectedSubsidy.subsidy_type) }}</strong></div>
              <div><span>Líquido</span><strong>{{ money(selectedSubsidy.net_amount) }}</strong></div>
              <div><span>Distribuido por nivel</span><strong>{{ settlementHasEducationalBreakdown(selectedSubsidy) ? money(settlementAllocated(selectedSubsidy)) : 'No aplica' }}</strong></div>
            </div>
            <h6 class="mt-4">Conceptos calculados</h6>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead><tr><th>Concepto</th><th>Clasificación</th><th>Monto</th><th>Tratamiento</th></tr></thead>
                <tbody><tr v-for="line in selectedSubsidy.lines || []" :key="line.id"><td>{{ line.concept_name }}</td><td>{{ line.classification }}</td><td>{{ money(line.amount) }}</td><td>{{ line.informative ? 'Informativo, no suma' : line.education_allocable ? 'Distribuido por nivel' : 'Sin desglose' }}</td></tr></tbody>
              </table>
            </div>
            <template v-if="settlementHasEducationalBreakdown(selectedSubsidy)">
              <h6 class="mt-4">Aporte por nivel educativo</h6>
              <div class="summary-list border rounded">
                <div v-for="item in settlementLevelSummary(selectedSubsidy)" :key="item.label"><span>{{ item.label }}</span><strong>{{ money(item.amount) }}</strong></div>
                <div v-if="!settlementLevelSummary(selectedSubsidy).length" class="mini-empty">Esta liquidación aún no tiene anexos distribuidos.</div>
              </div>
            </template>
            <div v-else class="modal-intro mt-4 mb-2">
              <i class="bx bx-info-circle"></i>
              <span>Este ingreso no contiene un desglose por nivel educativo; se conserva en el total general sin clasificarlo como monto educacional pendiente.</span>
            </div>
            <template v-if="proRetentionLine(selectedSubsidy)">
              <div class="subsidy-pie-detail-title">
                <div>
                  <span>DETALLE POR ALUMNO</span>
                  <h6>Subvención Pro-Retención</h6>
                </div>
                <strong>{{ proRetentionRows(selectedSubsidy).length }} alumno(s)</strong>
              </div>
              <div class="table-responsive subsidy-pie-detail-table">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Nivel / curso</th>
                      <th>Alumno</th>
                      <th>RUT</th>
                      <th>Tramo</th>
                      <th class="text-end">Aporte</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="allocation in proRetentionRows(selectedSubsidy)" :key="allocation.id">
                      <td><strong>{{ proRetentionCourse(allocation) }}</strong><small class="d-block text-muted">{{ allocation.education_level?.name || proRetentionData(allocation).teaching_label || '-' }}</small></td>
                      <td>{{ proRetentionData(allocation).student_name || '-' }}</td>
                      <td>{{ proRetentionData(allocation).student_rut || '-' }}</td>
                      <td>Tramo {{ proRetentionData(allocation).tranche || '-' }}</td>
                      <td class="text-end"><strong>{{ money(allocation.amount) }}</strong></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>
            <template v-if="schoolBonusLine(selectedSubsidy)">
              <div class="subsidy-pie-detail-title">
                <div>
                  <span>DETALLE DE NÓMINA</span>
                  <h6>Bono Escolar por trabajador y carga</h6>
                </div>
                <strong>{{ schoolBonusRows(selectedSubsidy).length }} carga(s)</strong>
              </div>
              <div class="summary-list border rounded mb-2">
                <div><span>Bono Escolar</span><strong>{{ money(schoolBonusComponents(selectedSubsidy).bonus_amount) }}</strong></div>
                <div><span>Aporte adicional</span><strong>{{ money(schoolBonusComponents(selectedSubsidy).additional_amount) }}</strong></div>
              </div>
              <div class="table-responsive subsidy-pie-detail-table">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Trabajador</th>
                      <th>Tipo / horas</th>
                      <th>Carga</th>
                      <th>Tramo</th>
                      <th class="text-end">Bono</th>
                      <th class="text-end">Adicional</th>
                      <th class="text-end">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="allocation in schoolBonusRows(selectedSubsidy)" :key="allocation.id">
                      <td><strong>{{ schoolBonusData(allocation).worker_name || '-' }}</strong><small class="d-block text-muted">{{ schoolBonusData(allocation).worker_rut || '-' }}</small></td>
                      <td>{{ schoolBonusData(allocation).worker_type || '-' }}<small class="d-block text-muted">{{ pieNumber(schoolBonusData(allocation).hours) }} hora(s)</small></td>
                      <td><strong>{{ schoolBonusData(allocation).dependent_name || '-' }}</strong><small class="d-block text-muted">{{ schoolBonusData(allocation).dependent_rut || '-' }}</small></td>
                      <td>{{ schoolBonusData(allocation).tranche ? `Tramo ${schoolBonusData(allocation).tranche}` : '-' }}</td>
                      <td class="text-end">{{ money(schoolBonusData(allocation).bonus_amount) }}</td>
                      <td class="text-end">{{ money(schoolBonusData(allocation).additional_amount) }}</td>
                      <td class="text-end"><strong>{{ money(allocation.amount) }}</strong></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>
            <template v-if="pieLine(selectedSubsidy)">
              <div class="subsidy-pie-detail-title">
                <div>
                  <span>DETALLE INFORMATIVO</span>
                  <h6>Programa de Integración Escolar (PIE)</h6>
                </div>
                <strong>{{ money(pieLine(selectedSubsidy).amount) }}</strong>
              </div>
              <div class="modal-intro mb-2">
                <i class="bx bx-info-circle"></i>
                <span>Estas filas explican el componente PIE incluido en la Subvención Normal. Se muestran por curso y nivel, pero no vuelven a aumentar el ingreso.</span>
              </div>
              <div class="table-responsive subsidy-pie-detail-table">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Nivel / curso</th>
                      <th>Glosa</th>
                      <th>Matrícula</th>
                      <th>Promedio</th>
                      <th>Base PIE</th>
                      <th>Zona</th>
                      <th>Ley 19.464</th>
                      <th>Total PIE</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="allocation in pieRows(selectedSubsidy)" :key="allocation.id">
                      <td><strong>{{ pieCourse(allocation) }}</strong><small class="d-block text-muted">Cód. {{ allocation.teaching_code }}</small></td>
                      <td>{{ allocation.education_label || '-' }}</td>
                      <td>{{ Number(allocation.enrollment || 0).toLocaleString('es-CL') }}</td>
                      <td>{{ allocation.attendance_average == null ? '-' : Number(allocation.attendance_average).toLocaleString('es-CL') }}</td>
                      <td>{{ money(pieData(allocation).base_amount) }}</td>
                      <td>{{ money(pieData(allocation).zone_increment_amount) }}</td>
                      <td>{{ money(pieData(allocation).law_19464_amount) }}</td>
                      <td><strong>{{ money(allocation.amount) }}</strong></td>
                    </tr>
                    <tr v-if="!pieRows(selectedSubsidy).length">
                      <td colspan="8"><div class="mini-empty">El archivo PIE sólo contiene el resumen, sin filas de curso.</div></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>
          </template>
        </BModal>

        <BModal v-model="subsidyPostVisible" centered scrollable hide-footer title="Contabilizar transferencia">
          <form @submit.prevent="postSubsidy">
            <div class="modal-intro"><i class="bx bx-info-circle"></i><span>Se creará un único ingreso, un movimiento bancario conciliado y un asiento contable.</span></div>
            <div class="accounting-form-grid">
              <label><span>Fecha ingreso *</span><BFormInput v-model="subsidyPostForm.received_at" type="date" required /></label>
              <label><span>Monto transferido *</span><BFormInput v-model="subsidyPostForm.transferred_amount" type="number" min="1" required /></label>
              <label><span>Cuenta contable *</span><BFormSelect v-model="subsidyPostForm.manual_account_id" required><option value="">Seleccionar...</option><option v-for="account in subsidyIncomeAccounts" :key="account.id" :value="account.id">{{ account.code }} - {{ account.name }}</option></BFormSelect></label>
              <label><span>Cuenta bancaria *</span><BFormSelect v-model="subsidyPostForm.bank_account_id" required><option value="">Seleccionar...</option><option v-for="account in catalogs.data.bank_accounts || []" :key="account.id" :value="account.id">{{ account.bank_name }} - {{ account.account_number }}</option></BFormSelect></label>
              <label><span>Centro de costo</span><BFormSelect v-model="subsidyPostForm.cost_center_id"><option value="">Sin centro</option><option v-for="center in catalogs.data.cost_centers || []" :key="center.id" :value="center.id">{{ center.name }}</option></BFormSelect></label>
              <label><span>Referencia</span><BFormInput v-model="subsidyPostForm.document_reference" /></label>
              <label class="full"><span>Observaciones</span><BFormTextarea v-model="subsidyPostForm.notes" rows="3" /></label>
            </div>
            <footer class="modal-actions"><BButton variant="light" type="button" @click="subsidyPostVisible = false">Cancelar</BButton><BButton variant="primary" type="submit" :disabled="saving">Crear ingreso y asiento</BButton></footer>
          </form>
        </BModal>
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
.accounting-shell{--acc-primary:#405189;--acc-ink:#263043;--acc-muted:#758095;--acc-border:#e2e7ee;display:flex;flex-direction:column;gap:1rem;padding-bottom:1.5rem}.accounting-hero{display:flex;align-items:center;justify-content:space-between;gap:2rem;padding:1.35rem 1.5rem;border:1px solid #dfe5ed;border-radius:12px;background:linear-gradient(125deg,#fff 0%,#f6f8fc 68%,#eef2fa 100%);box-shadow:0 5px 18px rgba(42,55,80,.05)}.hero-copy{max-width:850px}.eyebrow{display:flex;align-items:center;gap:.4rem;margin-bottom:.38rem;color:var(--acc-primary);font-size:.68rem;font-weight:750;letter-spacing:.075em;text-transform:uppercase}.eyebrow i{font-size:1rem}.accounting-hero h1{margin:0;color:var(--acc-ink);font-size:1.55rem;font-weight:700}.accounting-hero p{margin:.4rem 0 0;color:var(--acc-muted);font-size:.82rem}.hero-actions{display:flex;align-items:center;gap:.55rem;white-space:nowrap}.hero-actions .btn,.toolbar-actions .btn,.modal-actions .btn{display:inline-flex;align-items:center;justify-content:center;gap:.35rem}.accounting-nav{display:flex;gap:.5rem;overflow-x:auto;padding:.55rem;border:1px solid var(--acc-border);border-radius:10px;background:#fff;scrollbar-width:thin}.nav-group{flex:0 0 auto;min-width:130px;padding:.4rem .45rem;border-right:1px solid #edf0f4}.nav-group:last-child{border-right:0}.nav-group-title{display:flex;align-items:center;gap:.35rem;padding:0 .35rem .3rem;color:#8a94a4;font-size:.59rem;font-weight:750;letter-spacing:.065em;text-transform:uppercase}.nav-group-links{display:flex;flex-wrap:wrap;gap:.2rem}.nav-group-links a{padding:.34rem .52rem;border-radius:5px;color:#5f6b7c;font-size:.67rem;white-space:nowrap;transition:.15s ease}.nav-group-links a:hover{background:#f2f5fa;color:var(--acc-primary)}.nav-group-links a.active{background:#e9edf7;color:var(--acc-primary);font-weight:700}.scope-notice{display:flex;align-items:center;gap:.55rem;padding:.62rem .8rem;border:1px solid #f1dfb8;border-radius:8px;background:#fff9ec;color:#806326;font-size:.68rem}.scope-notice i{font-size:1.05rem}.metric-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.8rem}.metric-card{position:relative;display:flex;align-items:center;gap:.75rem;min-height:96px;padding:1rem;border:1px solid var(--acc-border);border-radius:10px;background:#fff;box-shadow:0 4px 14px rgba(35,48,70,.04);overflow:hidden}.metric-card span,.metric-card strong{display:block}.metric-card span{color:var(--acc-muted);font-size:.67rem}.metric-card strong{margin-top:.2rem;color:var(--acc-ink);font-size:1.25rem}.metric-icon{display:grid;place-items:center;flex:0 0 42px;width:42px;height:42px;border-radius:9px;background:#eaf4ee;color:#25845f;font-size:1.35rem}.metric-icon-2{background:#fbecee;color:#c34b59}.metric-icon-3{background:#eaf0fb;color:#456ea9}.metric-icon-4,.metric-icon-5{background:#f8f0dd;color:#a67a1f}.metric-card-accent{background:linear-gradient(135deg,#405189,#5266a2);border-color:transparent}.metric-card-accent span,.metric-card-accent strong,.metric-card-accent .metric-icon{color:#fff}.metric-card-accent .metric-icon{background:rgba(255,255,255,.14)}.metric-progress{position:absolute;right:1rem;bottom:.75rem;left:1rem;height:3px;border-radius:2px;background:rgba(255,255,255,.2)}.metric-progress span{height:100%;border-radius:2px;background:#fff}.dashboard-grid{display:grid;grid-template-columns:1.05fr 1fr 1fr;gap:.8rem}.content-card{border:1px solid var(--acc-border);border-radius:10px;background:#fff;box-shadow:0 4px 14px rgba(35,48,70,.035)}.card-heading{display:flex;align-items:center;justify-content:space-between;padding:.9rem 1rem;border-bottom:1px solid #edf0f4}.card-heading span,.toolbar-kicker{color:#8b95a5;font-size:.58rem;font-weight:750;letter-spacing:.07em}.card-heading h2,.records-toolbar h2{margin:.15rem 0 0;color:var(--acc-ink);font-size:.88rem}.card-heading>i{color:#9aa4b3;font-size:1.2rem}.alert-list>div,.summary-list>div{display:grid;grid-template-columns:27px 1fr auto;align-items:center;gap:.5rem;padding:.64rem 1rem;border-bottom:1px solid #eff2f5;color:#5d6879;font-size:.68rem}.alert-list>div:last-child,.summary-list>div:last-child{border-bottom:0}.alert-list i{display:grid;place-items:center;width:25px;height:25px;border-radius:6px;background:#fff5df;color:#ad791b;font-size:.9rem}.alert-list .danger i{background:#fdecee;color:#c04454}.alert-list strong{display:grid;place-items:center;min-width:25px;height:23px;border-radius:12px;background:#f0f3f7;color:#465366}.summary-list>div{grid-template-columns:1fr auto;min-height:43px}.summary-list strong{color:#334055}.mini-empty{display:block!important;color:#919baa!important;text-align:center}.records-card{overflow:hidden}.records-toolbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.9rem 1rem}.record-count{display:inline-grid;place-items:center;min-width:23px;height:20px;margin-left:.25rem;border-radius:10px;background:#eef1f6;color:#637087;font-size:.62rem}.toolbar-actions{display:flex;align-items:center;gap:.4rem}.search-box{display:flex;align-items:center;min-width:250px;height:35px;border:1px solid #dce2e9;border-radius:7px;background:#f9fafc}.search-box>i{margin-left:.65rem;color:#8691a1}.search-box input{width:100%;padding:0 .5rem;border:0;outline:0;background:transparent;color:#3d495b;font-size:.7rem}.search-box button{border:0;background:transparent;color:#7b8696}.icon-action{width:36px;padding:0}.table-summary{display:flex;align-items:center;justify-content:flex-end;gap:.6rem;padding:.45rem 1rem;border-top:1px solid #edf0f4;background:#f8fafc;color:#7b8697;font-size:.64rem}.table-summary strong{color:#334055;font-size:.75rem}.accounting-table{font-size:.68rem}.accounting-table thead th{padding:.65rem .8rem;border-color:#e7ebf0;background:#f5f7fa;color:#626f82;font-size:.6rem;font-weight:750;letter-spacing:.035em;text-transform:uppercase;white-space:nowrap}.accounting-table tbody td{padding:.66rem .8rem;border-color:#edf0f4;color:#4f5b6d}.accounting-table tbody tr:hover{background:#fafbfd}.accounting-table .badge{text-transform:capitalize}.row-actions{display:flex;justify-content:flex-end;gap:.25rem}.row-actions button{display:grid;place-items:center;width:29px;height:29px;border:1px solid #dce2e9;border-radius:6px;background:#fff;color:#59677b;font-size:.9rem}.row-actions button:hover{border-color:#aeb9c9;color:var(--acc-primary)}.row-actions .danger:hover{border-color:#e5aab2;background:#fff7f8;color:#bd3b4b}.empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:220px;color:#8994a4;text-align:center}.empty-state>i{margin-bottom:.5rem;color:#b1bac7;font-size:2.25rem}.empty-state strong{color:#566275;font-size:.8rem}.empty-state span{margin:.2rem 0 .65rem;font-size:.66rem}.modal-title-block span,.modal-title-block strong{display:block}.modal-title-block span{color:#8a94a4;font-size:.57rem;font-weight:750;letter-spacing:.07em;text-transform:uppercase}.modal-title-block strong{margin-top:.1rem;color:#293448;font-size:.95rem}.modal-intro{display:flex;gap:.5rem;margin-bottom:1rem;padding:.65rem .75rem;border-radius:7px;background:#f1f4fa;color:#657185;font-size:.67rem}.modal-intro i{color:var(--acc-primary);font-size:1rem}.accounting-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:.8rem}.accounting-form-grid label>span{display:block;margin-bottom:.28rem;color:#566275;font-size:.67rem;font-weight:650}.accounting-form-grid label>span b{color:#c04454}.accounting-form-grid .form-control,.accounting-form-grid .form-select{min-height:38px;border-color:#dce2e9;font-size:.72rem}.accounting-form-grid .full{grid-column:1/-1}.accounting-form-grid .switch{display:flex;align-items:center;min-height:38px;padding-top:.9rem}.modal-actions{display:flex;justify-content:flex-end;gap:.45rem;margin:1.1rem -1rem -1rem;padding:.8rem 1rem;border-top:1px solid #e5e9ef;background:#f9fafb}:deep(.accounting-form-modal .modal-content){border:0;border-radius:10px;box-shadow:0 24px 70px rgba(25,35,50,.25)}:deep(.accounting-form-modal .modal-header){padding:.8rem 1rem;border-bottom-color:#e5e9ef}:deep(.accounting-form-modal .modal-body){padding:1rem}:deep(.card){border:1px solid var(--acc-border)!important;border-radius:10px;box-shadow:0 4px 14px rgba(35,48,70,.035)!important}:deep(.table){font-size:.68rem}.subsidy-toolbar{display:flex;align-items:end;justify-content:space-between;gap:1rem;padding:.9rem 1rem}.subsidy-period{width:180px;margin-top:.25rem}.subsidy-metrics{grid-template-columns:repeat(4,minmax(0,1fr))}.subsidy-summary-grid{grid-template-columns:1fr 1fr .8fr}.summary-list small{margin-left:.3rem;color:#929bab}.subsidy-control-values{padding:.65rem 1rem}.subsidy-control-values>div{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.55rem 0;border-bottom:1px solid #eff2f5;color:#647083;font-size:.68rem}.subsidy-control-values>div:last-child{border-bottom:0}.subsidy-control-values strong{color:#334055}.subsidy-detail-heading{display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem}.subsidy-detail-heading>div{padding:.8rem;border:1px solid #e3e8ef;border-radius:8px;background:#f8fafc}.subsidy-detail-heading span,.subsidy-detail-heading strong{display:block}.subsidy-detail-heading span{color:#8792a2;font-size:.62rem}.subsidy-detail-heading strong{margin-top:.2rem;color:#334055;font-size:.9rem}
.subsidy-command-card{position:relative;align-items:flex-end;padding:1rem 1.1rem 2.9rem;background:linear-gradient(135deg,#fff 0%,#f8faff 100%)}
.subsidy-period-filters{display:flex;align-items:flex-end;gap:.65rem}
.subsidy-period-filters label{margin:0}
.subsidy-period-filters .toolbar-kicker{display:block;margin-bottom:.32rem}
.subsidy-period-select{width:118px;min-height:38px;border-color:#d8dfe9;font-size:.72rem}
.subsidy-month-select{width:155px}
.subsidy-compare-select{width:150px}
.subsidy-period-note{position:absolute;right:0;bottom:0;left:0;display:flex;align-items:center;gap:.45rem;padding:.52rem 1.1rem;border-top:1px solid #e8edf4;background:#f4f7fb;color:#667085;font-size:.65rem}
.subsidy-period-note i{color:#405189;font-size:1rem}
.subsidy-metric-card{min-height:112px}
.subsidy-metric-copy{min-width:0}
.subsidy-metric-copy small{display:block;margin-top:.34rem;color:#8691a1;font-size:.58rem;line-height:1.3}
.subsidy-metric-warning{border-color:#efc3c9;background:#fff9fa}
.subsidy-delta{display:inline-flex!important;align-items:center;width:max-content;padding:.18rem .4rem;border-radius:12px;font-weight:700}
.subsidy-delta.positive{background:#e9f7f0;color:#16845d}
.subsidy-delta.negative{background:#fdecee;color:#bd4252}
.subsidy-delta.neutral{background:#eef1f5;color:#667085}
.subsidy-delta-table{font-size:.61rem}
.subsidy-comparison-card{overflow:hidden}
.subsidy-level-list .subsidy-level-item{grid-template-columns:minmax(0,1fr) auto;padding-top:.72rem;padding-bottom:.72rem}
.subsidy-level-track{height:4px;margin-top:.35rem;border-radius:3px;background:#edf1f6;overflow:hidden}
.subsidy-level-track>span{display:block;height:100%;border-radius:3px;background:linear-gradient(90deg,#405189,#6d80bd)}
.subsidy-pie-card{overflow:hidden}
.subsidy-pie-card .card-heading .btn{display:inline-flex;align-items:center;gap:.35rem;font-size:.65rem}
.subsidy-pie-body{display:block}
.subsidy-pie-overview{display:grid;grid-template-columns:1.4fr repeat(3,1fr);border-bottom:1px solid #edf0f4}
.subsidy-pie-overview>div{min-height:92px;padding:1rem;border-right:1px solid #edf0f4}
.subsidy-pie-overview>div:last-child{border-right:0}
.subsidy-pie-overview span,.subsidy-pie-overview strong,.subsidy-pie-overview small{display:block}
.subsidy-pie-overview span{color:#7b8697;font-size:.62rem}
.subsidy-pie-overview strong{margin-top:.32rem;color:#2f3b50;font-size:1rem}
.subsidy-pie-overview small{margin-top:.3rem;color:#919baa;font-size:.56rem;line-height:1.35}
.subsidy-pie-consolidations{display:grid;grid-template-columns:.85fr 1.35fr}
.subsidy-pie-consolidated+ .subsidy-pie-consolidated{border-left:1px solid #e7ebf0}
.subsidy-pie-section-heading{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.78rem 1rem;border-bottom:1px solid #edf0f4;background:#fafbfd}
.subsidy-pie-section-heading span{color:#8b95a5;font-size:.55rem;font-weight:750;letter-spacing:.07em}
.subsidy-pie-section-heading h3{margin:.1rem 0 0;color:#334055;font-size:.78rem}
.subsidy-pie-section-heading>small{color:#7d8899;font-size:.61rem}
.subsidy-pie-consolidated .accounting-table th,.subsidy-pie-consolidated .accounting-table td{padding:.55rem .62rem}
.subsidy-pie-consolidated .accounting-table td:not(:first-child),.subsidy-pie-consolidated .accounting-table th:not(:first-child){text-align:right}
.subsidy-pie-consolidated .accounting-table tfoot th{border-top:2px solid #d9e0ea;background:#f3f6fa;color:#334055;font-size:.62rem}
.subsidy-pie-course-table{max-height:420px}
.subsidy-pie-course-table thead{position:sticky;top:0;z-index:2}
.subsidy-pie-course-table tfoot{position:sticky;bottom:0;z-index:2}
.subsidy-pie-empty{display:flex;align-items:center;justify-content:center;gap:.7rem;min-height:110px;color:#8a95a5}
.subsidy-pie-empty i{font-size:1.8rem}
.subsidy-pie-empty strong,.subsidy-pie-empty span{display:block}
.subsidy-pie-empty strong{color:#566275;font-size:.74rem}
.subsidy-pie-empty span{margin-top:.15rem;font-size:.63rem}
.subsidy-pie-detail-title{display:flex;align-items:end;justify-content:space-between;gap:1rem;margin:1.2rem 0 .55rem;padding-bottom:.5rem;border-bottom:1px solid #e5eaf0}
.subsidy-pie-detail-title span{color:#8b95a5;font-size:.57rem;font-weight:750;letter-spacing:.07em}
.subsidy-pie-detail-title h6{margin:.15rem 0 0;color:#2f3b50}
.subsidy-pie-detail-title>strong{color:#405189;font-size:1rem}
.subsidy-pie-detail-table{max-height:420px;border:1px solid #e4e9ef;border-radius:7px}
.subsidy-pie-detail-table thead{position:sticky;top:0;z-index:1}
.subsidy-pie-detail-table thead th{background:#f3f6fa;white-space:nowrap}
.subsidy-per-student-card{overflow:hidden}
.subsidy-per-student-card .card-heading>small{color:#7d8899;font-size:.61rem}
.subsidy-per-student-note{display:flex;align-items:center;gap:.5rem;padding:.65rem 1rem;border-bottom:1px solid #e8edf3;background:#f7f9fc;color:#667286;font-size:.65rem}
.subsidy-per-student-note i{color:#405189;font-size:1rem}
.subsidy-cycle-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem;padding:1rem;border-bottom:1px solid #e8edf3}
.subsidy-cycle-card{padding:.85rem;border:1px solid #dfe5ed;border-radius:8px;background:linear-gradient(135deg,#fff,#f7f9fd)}
.subsidy-cycle-card>span,.subsidy-cycle-card>strong,.subsidy-cycle-card>small{display:block}
.subsidy-cycle-card>span{color:#667286;font-size:.64rem;font-weight:700}
.subsidy-cycle-card>strong{margin-top:.25rem;color:#405189;font-size:1.15rem}
.subsidy-cycle-card>small{color:#8a95a5;font-size:.58rem}
.subsidy-cycle-card>div{display:flex;justify-content:space-between;gap:.7rem;margin-top:.65rem;padding-top:.55rem;border-top:1px solid #e6ebf2;color:#687589;font-size:.59rem}
.subsidy-per-student-card .accounting-table td:not(:first-child),.subsidy-per-student-card .accounting-table th:not(:first-child){white-space:nowrap}
.subsidy-per-student-card .accounting-table tfoot th,.subsidy-annual-master .accounting-table tfoot th{border-top:2px solid #d9e0ea;background:#f3f6fa;color:#334055;font-size:.62rem}
.subsidy-annual-card{overflow:hidden}
.subsidy-annual-card .card-heading>small{color:#8a94a4;font-size:.62rem}
.subsidy-annual-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0;border-top:0}
.subsidy-annual-row{display:grid;grid-template-columns:42px minmax(80px,1fr) 110px 86px;align-items:center;gap:.7rem;padding:.68rem 1rem;border:0;border-right:1px solid #edf0f4;border-bottom:1px solid #edf0f4;background:#fff;color:#536075;text-align:left;transition:.15s ease}
.subsidy-annual-row:nth-child(even){border-right:0}
.subsidy-annual-row:hover{background:#f8faff}
.subsidy-annual-row.active{background:#eef2fb;box-shadow:inset 3px 0 #405189}
.subsidy-annual-month{text-transform:capitalize;font-size:.66rem;font-weight:700}
.subsidy-annual-bar{height:6px;border-radius:4px;background:#edf1f6;overflow:hidden}
.subsidy-annual-bar>span{display:block;height:100%;min-width:0;border-radius:4px;background:linear-gradient(90deg,#405189,#7c8fc5)}
.subsidy-annual-row strong{text-align:right;font-size:.67rem}
.subsidy-annual-row small{text-align:right;color:#8c96a6;font-size:.57rem}
.subsidy-annual-master{border-top:1px solid #dfe5ed}
.subsidy-annual-master tbody tr{cursor:pointer}
@media(max-width:1100px){.metric-grid{grid-template-columns:repeat(2,1fr)}.dashboard-grid{grid-template-columns:1fr 1fr}.alert-panel{grid-column:1/-1}.accounting-nav{padding:.45rem}.nav-group{min-width:auto}.nav-group-title{display:none}.subsidy-pie-consolidations{grid-template-columns:1fr}.subsidy-pie-consolidated+ .subsidy-pie-consolidated{border-top:1px solid #e7ebf0;border-left:0}.subsidy-cycle-grid{grid-template-columns:1fr}}
@media(max-width:720px){.accounting-hero{align-items:flex-start;padding:1rem}.accounting-hero,.records-toolbar{flex-direction:column}.hero-actions,.toolbar-actions{width:100%}.hero-actions .btn{flex:1}.accounting-hero h1{font-size:1.25rem}.metric-grid,.dashboard-grid{grid-template-columns:1fr}.alert-panel{grid-column:auto}.records-toolbar{align-items:stretch}.toolbar-actions{flex-wrap:wrap}.search-box{flex:1;min-width:200px}.accounting-form-grid{grid-template-columns:1fr}.accounting-form-grid .full{grid-column:auto}.accounting-nav{display:block}.nav-group{padding:.3rem;border-right:0;border-bottom:1px solid #edf0f4}.nav-group-links{flex-wrap:nowrap;overflow-x:auto}.scope-notice{align-items:flex-start}.subsidy-command-card{align-items:stretch}.subsidy-command-card,.subsidy-period-filters{flex-direction:column}.subsidy-period-filters{align-items:stretch}.subsidy-period-select,.subsidy-month-select,.subsidy-compare-select{width:100%}.subsidy-period-note{position:static;margin:1rem -1.1rem -2.9rem}.subsidy-pie-overview{grid-template-columns:1fr 1fr}.subsidy-pie-overview>div:nth-child(2){border-right:0}.subsidy-pie-overview>div{border-bottom:1px solid #edf0f4}.subsidy-annual-grid{grid-template-columns:1fr}.subsidy-annual-row{grid-template-columns:40px minmax(70px,1fr) 100px}.subsidy-annual-row:nth-child(n){border-right:0}.subsidy-annual-row small{display:none}.subsidy-cycle-card>div{align-items:flex-start;flex-direction:column}}
</style>
