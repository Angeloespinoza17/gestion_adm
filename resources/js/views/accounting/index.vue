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
const compactMoney = (value) => {
  const amount = Number(value || 0);
  const absolute = Math.abs(amount);
  const format = (divisor, suffix) => `$${(amount / divisor).toLocaleString("es-CL", { maximumFractionDigits: 1 })} ${suffix}`;

  if (absolute >= 1_000_000_000) return format(1_000_000_000, "mil MM");
  if (absolute >= 1_000_000) return format(1_000_000, "MM");
  if (absolute >= 1_000) return format(1_000, "mil");
  return money(amount);
};

const navItems = [
  { route: "/contabilidad", key: "dashboard", label: "Dashboard", group: "Resumen", icon: "bx-grid-alt", permission: "contabilidad.dashboard" },
  { route: "/contabilidad/rendiciones", key: "renderings", label: "Rendiciones", permission: "contabilidad.fondos_rendir.gestionar" },
  { route: "/contabilidad/presupuesto", key: "budget-lines", label: "Presupuesto", permission: "contabilidad.presupuesto.ver" },
  { route: "/contabilidad/ejecucion-presupuestaria", key: "budget-execution", label: "Ejecución", permission: "contabilidad.ejecucion_presupuestaria.ver" },
  { route: "/contabilidad/centros-costo", key: "cost-centers", label: "Centros de costo", permission: "contabilidad.centros_costo.gestionar" },
  { route: "/contabilidad/manual-cuentas", key: "manual-accounts", label: "Manual de cuentas", permission: "contabilidad.manual_cuentas.gestionar" },
  { route: "/contabilidad/ingresos", key: "incomes", label: "Ingresos", permission: "contabilidad.ingresos.gestionar" },
  { route: "/contabilidad/egresos", key: "expenses", label: "Egresos", permission: "contabilidad.egresos.gestionar" },
  { route: "/contabilidad/caja-chica", key: "cash-funds", label: "Caja chica", permission: "contabilidad.caja_chica.gestionar" },
  { route: "/contabilidad/fondos-rendir", key: "funds-to-render", label: "Fondos por rendir", permission: "contabilidad.fondos_rendir.gestionar" },
  { route: "/contabilidad/conciliacion", key: "bank-movements", label: "Conciliación", permission: "contabilidad.conciliacion.gestionar" },
  { route: "/contabilidad/subvenciones", key: "funding-sources", label: "Subvención y asistencia", permission: "contabilidad.subvenciones.ver" },
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
  { label: "Presupuesto y fondos", icon: "bx-wallet", keys: ["budget-lines", "budget-execution", "cost-centers", "funding-sources", "cash-funds", "funds-to-render", "renderings"] },
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
  "budget-execution": {
    route: "/contabilidad/ejecucion-presupuestaria",
    kind: "budget-execution",
    title: "Ejecución Presupuestaria",
    subtitle: "Seguimiento anual por cuenta y subvención, con lectura mensual, proyección y alertas de desviación.",
    help: "Carga un libro Excel con hojas GENERAL, MANTENCION, SEP y PIE. Cada nueva carga reemplaza de forma atómica la versión del año seleccionado y deja trazabilidad de importación.",
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
    title: "Subvención, asistencia e ingresos",
    subtitle: "Estimación reglamentaria, merma por inasistencia y conciliación con liquidaciones MINEDUC e ingresos contabilizados.",
    help: "La estimación usa la asistencia promedio de la ventana legal previa al pago. La merma compara contra 100% de asistencia; la liquidación oficial y el ingreso contable se mantienen separados para auditoría.",
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
        annual_overview: {
          year: currentSubsidyDate.getFullYear(),
          metrics: {
            net_liquidated: 0,
            transferred_total: 0,
            income_total: 0,
            income_gap: 0,
            pie_total: 0,
            settlement_count: 0,
            months_with_data: 0,
            average_active_month: 0,
            observed_count: 0,
            pending_transfer_count: 0,
          },
          first_period: null,
          last_period: null,
          peak_month: null,
          by_family: [],
        },
        available_years: [],
        attendance_reconciliation: {
          available: false,
          status: "sin_asistencia",
          metrics: {},
          window: { required_periods: [], found_periods: [], missing_periods: [], source_imports: [] },
          assumptions: {},
          by_level: [],
          by_subsidy: [],
          warnings: [],
          sources: [],
        },
      },
      subsidyCalculationOptions: {
        jec: "1",
        sep_category: "autonomo",
        include_gratuity: "1",
        concentration_band: "auto",
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
      budgetExecutionYear: currentSubsidyDate.getFullYear(),
      budgetExecution: {
        year: currentSubsidyDate.getFullYear(),
        available_years: [],
        has_data: false,
        import: null,
        metrics: {},
        alerts: {},
        monthly: [],
        subsidies: [],
        categories: [],
        accounts: [],
      },
      importingBudgetExecution: false,
      downloadingBudgetExecutionPdf: false,
      budgetExecutionSearch: "",
      budgetExecutionFlow: "expense",
      budgetExecutionSubsidy: "all",
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
    isBudgetExecution() {
      return this.activePanel.kind === "budget-execution";
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
    subsidyAnnualOverview() {
      return this.subsidyDashboard.annual_overview || {
        metrics: this.subsidyAnnualTotals,
        first_period: null,
        last_period: null,
        peak_month: null,
        by_family: [],
      };
    },
    subsidyAnnualChartMax() {
      return Math.max(1, ...(this.subsidyDashboard.annual || []).flatMap((item) => [
        Number(item.net_liquidated || 0),
        Number(item.income_total || 0),
      ]));
    },
    subsidyAnnualIncomeCoverage() {
      const liquidated = Number(this.subsidyAnnualOverview.metrics?.net_liquidated || 0);
      const income = Number(this.subsidyAnnualOverview.metrics?.income_total || 0);
      return liquidated > 0 ? Math.min(100, Math.max(0, (income / liquidated) * 100)) : 0;
    },
    subsidyAnnualLoadedRange() {
      const first = this.subsidyAnnualOverview.first_period;
      const last = this.subsidyAnnualOverview.last_period;
      if (!first || !last) return "Aún no hay liquidaciones cargadas";
      if (first === last) return this.subsidyPeriodLabel(first);
      return `${this.subsidyPeriodLabel(first)} a ${this.subsidyPeriodLabel(last)}`;
    },
    subsidyAnnualHealth() {
      const metrics = this.subsidyAnnualOverview.metrics || {};
      if (!Number(metrics.settlement_count || 0)) {
        return { label: "SIN DATOS", className: "empty", detail: "Importa una liquidación para comenzar" };
      }
      if (Number(metrics.observed_count || 0) > 0) {
        return { label: "REQUIERE REVISIÓN", className: "danger", detail: `${metrics.observed_count} liquidación(es) observada(s)` };
      }
      if (Number(metrics.pending_transfer_count || 0) > 0) {
        return { label: "PENDIENTE DE CONCILIAR", className: "warning", detail: `${metrics.pending_transfer_count} liquidación(es) sin transferencia` };
      }
      return { label: "CONCILIADO", className: "success", detail: "Sin observaciones pendientes" };
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
    attendanceReconciliation() {
      return this.subsidyDashboard.attendance_reconciliation || {
        available: false,
        metrics: {},
        window: {},
        assumptions: {},
        by_level: [],
        by_subsidy: [],
        warnings: [],
        sources: [],
      };
    },
    attendanceReconciliationStatus() {
      const status = this.attendanceReconciliation.status;
      if (status === "cuadrado") return { label: "DENTRO DE TOLERANCIA", className: "success", icon: "bx-check-shield" };
      if (status === "diferencia") return { label: "CON DIFERENCIA", className: "danger", icon: "bx-error-circle" };
      if (status === "incompleto") return { label: "CÁLCULO PROVISIONAL", className: "warning", icon: "bx-time-five" };
      if (status === "sin_ingreso") return { label: "SIN INGRESO REGISTRADO", className: "warning", icon: "bx-receipt" };
      if (status === "sin_parametros") return { label: "SIN TARIFA VIGENTE", className: "neutral", icon: "bx-calendar-x" };
      return { label: "SIN ASISTENCIA", className: "neutral", icon: "bx-cloud-upload" };
    },
    attendanceComparisonMax() {
      const metrics = this.attendanceReconciliation.metrics || {};
      return Math.max(
        1,
        Number(metrics.full_attendance_total || 0),
        Number(metrics.expected_total || 0),
        Number(metrics.liquidated_gross_total || 0),
        Number(metrics.liquidated_total || 0),
        Number(metrics.registered_comparable_income_total || 0),
      );
    },
    budgetExecutionYearOptions() {
      const currentYear = currentSubsidyDate.getFullYear();
      const years = [...(this.budgetExecution.available_years || []), this.budgetExecutionYear, currentYear];
      for (let offset = -5; offset <= 2; offset += 1) years.push(currentYear + offset);
      return [...new Set(years.map(Number))].sort((a, b) => b - a);
    },
    canImportBudgetExecution() {
      const permissions = this.catalogs.permissions || [];
      return permissions.includes("__superadmin__") || permissions.includes("contabilidad.admin") || permissions.includes("contabilidad.ejecucion_presupuestaria.importar");
    },
    canExportBudgetExecution() {
      const permissions = this.catalogs.permissions || [];
      return permissions.includes("__superadmin__") || permissions.includes("contabilidad.admin") || permissions.includes("contabilidad.ejecucion_presupuestaria.exportar");
    },
    budgetExecutionMonthlyMax() {
      return Math.max(0, ...(this.budgetExecution.monthly || []).flatMap((item) => [Number(item.income || 0), Number(item.expense || 0)]));
    },
    budgetExecutionCategoryMax() {
      return Math.max(1, ...(this.budgetExecution.categories || []).map((item) => Math.max(Number(item.budget || 0), Number(item.executed || 0))));
    },
    budgetExecutionCumulativeChart() {
      let cumulativeIncome = 0;
      let cumulativeExpense = 0;
      const rows = (this.budgetExecution.monthly || []).map((month, index) => {
        cumulativeIncome += Number(month.income || 0);
        cumulativeExpense += Number(month.expense || 0);
        return {
          ...month,
          index,
          cumulativeIncome,
          cumulativeExpense,
          cumulativeBalance: cumulativeIncome - cumulativeExpense,
        };
      });
      const values = rows.flatMap((item) => [item.cumulativeIncome, item.cumulativeExpense, item.cumulativeBalance]);
      const rawMin = Math.min(0, ...values);
      const rawMax = Math.max(0, ...values);
      const span = Math.max(1, rawMax - rawMin);
      const min = rawMin < 0 ? rawMin - (span * 0.08) : 0;
      const max = rawMax > 0 ? rawMax + (span * 0.08) : 1;
      const x = (index) => 54 + ((630 / Math.max(1, rows.length - 1)) * index);
      const y = (value) => 210 - (((value - min) / Math.max(1, max - min)) * 160);
      const pointRows = rows.map((item) => ({
        ...item,
        x: x(item.index),
        incomeY: y(item.cumulativeIncome),
        expenseY: y(item.cumulativeExpense),
        balanceY: y(item.cumulativeBalance),
      }));
      const points = (key) => pointRows.map((item) => `${item.x},${item[key]}`).join(" ");
      const grid = Array.from({ length: 5 }, (_, index) => {
        const value = max - (((max - min) / 4) * index);
        return { y: 50 + (index * 40), value };
      });

      return {
        rows: pointRows,
        incomePoints: points("incomeY"),
        expensePoints: points("expenseY"),
        balancePoints: points("balanceY"),
        incomeArea: pointRows.length ? `54,${y(0)} ${points("incomeY")} 684,${y(0)}` : "",
        zeroY: y(0),
        grid,
      };
    },
    budgetExecutionSubsidyMix() {
      const colors = ["#405189", "#2f9e78", "#d49a3a", "#8765ad", "#c65a68", "#4a91b8"];
      const total = (this.budgetExecution.subsidies || []).reduce((sum, item) => sum + Number(item.expense_budget || 0), 0);
      let offset = 0;
      const items = (this.budgetExecution.subsidies || []).map((item, index) => {
        const share = total > 0 ? (Number(item.expense_budget || 0) / total) * 100 : 0;
        const result = {
          ...item,
          share,
          color: colors[index % colors.length],
          dasharray: `${share} ${100 - share}`,
          dashoffset: -offset,
        };
        offset += share;
        return result;
      });

      return { total, items };
    },
    budgetExecutionTopAccountsChart() {
      const accounts = (this.budgetExecution.accounts || []).filter((item) => (
        item.flow_type === "expense"
        && (Number(item.annual_budget || 0) > 0 || Number(item.executed || 0) > 0)
      ));
      const hasExecution = accounts.some((item) => Number(item.executed || 0) > 0);
      const ranked = [...accounts]
        .sort((a, b) => Number(hasExecution ? b.executed : b.annual_budget) - Number(hasExecution ? a.executed : a.annual_budget))
        .slice(0, 8);
      const max = Math.max(1, ...ranked.flatMap((item) => [Number(item.annual_budget || 0), Number(item.executed || 0)]));

      return {
        hasExecution,
        items: ranked.map((item) => ({
          ...item,
          budgetWidth: `${Math.min(100, (Number(item.annual_budget || 0) / max) * 100)}%`,
          executedWidth: `${Math.min(100, (Number(item.executed || 0) / max) * 100)}%`,
          chartValue: Number(hasExecution ? item.executed : item.annual_budget),
        })),
      };
    },
    budgetExecutionAccounts() {
      const search = this.budgetExecutionSearch.trim().toLocaleLowerCase("es-CL");
      return (this.budgetExecution.accounts || []).filter((account) => (
        account.flow_type === this.budgetExecutionFlow
        && (this.budgetExecutionSubsidy === "all" || account.subsidy_code === this.budgetExecutionSubsidy)
        && (!search || `${account.account_name} ${account.category} ${account.subsidy_name}`.toLocaleLowerCase("es-CL").includes(search))
      ));
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
    compactMoney,
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
        } else if (this.isBudgetExecution) {
          await this.loadBudgetExecution();
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
          jec: this.subsidyCalculationOptions.jec,
          sep_category: this.subsidyCalculationOptions.sep_category,
          include_gratuity: this.subsidyCalculationOptions.include_gratuity,
          concentration_band: this.subsidyCalculationOptions.concentration_band,
        },
      });
      this.subsidyDashboard = response.data || this.subsidyDashboard;
    },
    async loadBudgetExecution() {
      const response = await axios.get("/api/contabilidad/ejecucion-presupuestaria", {
        params: { year: this.budgetExecutionYear },
      });
      this.budgetExecution = response.data || this.budgetExecution;
    },
    async changeBudgetExecutionYear() {
      await this.loadBudgetExecution();
    },
    openBudgetExecutionFilePicker() {
      this.$refs.budgetExecutionFile?.click();
    },
    async uploadBudgetExecution(event) {
      const file = event.target.files?.[0];
      event.target.value = "";
      if (!file) return;

      if (this.budgetExecution.has_data) {
        const confirmation = await Swal.fire({
          title: `Reemplazar ejecución ${this.budgetExecutionYear}`,
          html: `La carga vigente <strong>${this.budgetExecution.import?.original_filename || "del año"}</strong> será reemplazada por <strong>${file.name}</strong>.`,
          icon: "warning",
          showCancelButton: true,
          confirmButtonText: "Sí, reemplazar",
          cancelButtonText: "Cancelar",
          confirmButtonColor: "#405189",
        });
        if (!confirmation.isConfirmed) return;
      }

      this.importingBudgetExecution = true;
      try {
        const payload = new FormData();
        payload.append("year", String(this.budgetExecutionYear));
        payload.append("file", file);
        const response = await axios.post("/api/contabilidad/ejecucion-presupuestaria/importar", payload, {
          headers: { "Content-Type": "multipart/form-data" },
        });
        this.budgetExecution = response.data.data || this.budgetExecution;
        await Swal.fire({
          title: response.data.replaced ? "Año actualizado" : "Ejecución importada",
          text: response.data.message,
          icon: "success",
          confirmButtonColor: "#405189",
        });
      } catch (error) {
        await Swal.fire("No se pudo importar", formatAccountingError(error, "Revisa el año y la estructura del archivo Excel."), "error");
      } finally {
        this.importingBudgetExecution = false;
      }
    },
    budgetExecutionBarHeight(value) {
      const scale = Math.max(1, this.budgetExecutionMonthlyMax);
      return `${Math.max(0, Math.min(100, (Number(value || 0) / scale) * 100))}%`;
    },
    budgetExecutionHeatColor(month) {
      const intensity = Math.min(1, Number(month.expense || 0) / Math.max(1, this.budgetExecutionMonthlyMax));
      const alpha = 0.09 + (intensity * 0.72);
      if (Number(month.balance || 0) < 0) return `rgba(194,65,79,${alpha})`;
      if (Number(month.balance || 0) > 0) return `rgba(47,158,120,${alpha})`;
      return `rgba(64,81,137,${alpha})`;
    },
    budgetExecutionCategoryWidth(value) {
      return `${Math.max(0, Math.min(100, (Number(value || 0) / this.budgetExecutionCategoryMax) * 100))}%`;
    },
    budgetExecutionProgress(value) {
      return `${Math.max(0, Math.min(100, Number(value || 0)))}%`;
    },
    budgetExecutionStatus(percentage) {
      const value = Number(percentage || 0);
      if (value > 100) return { label: "Sobre ejecutada", className: "danger" };
      if (value >= 85) return { label: "Atención", className: "warning" };
      return { label: "En rango", className: "success" };
    },
    budgetExecutionChartSvg() {
      const months = this.budgetExecution.monthly || [];
      const max = Math.max(1, ...months.flatMap((item) => [Number(item.income || 0), Number(item.expense || 0)]));
      const chartHeight = 150;
      const baseY = 190;
      const barWidth = 13;
      const groupWidth = 52;
      const startX = 55;
      const bars = months.map((item, index) => {
        const x = startX + index * groupWidth;
        const incomeHeight = (Number(item.income || 0) / max) * chartHeight;
        const expenseHeight = (Number(item.expense || 0) / max) * chartHeight;
        return `<rect x="${x}" y="${baseY - incomeHeight}" width="${barWidth}" height="${incomeHeight}" rx="3" fill="#2f9e78"/><rect x="${x + 17}" y="${baseY - expenseHeight}" width="${barWidth}" height="${expenseHeight}" rx="3" fill="#405189"/><text x="${x + 15}" y="207" text-anchor="middle" font-size="9" fill="#667085">${item.short_label}</text>`;
      }).join("");
      return `<svg width="720" height="225" viewBox="0 0 720 225" xmlns="http://www.w3.org/2000/svg"><rect width="720" height="225" rx="12" fill="#f7f9fc"/><line x1="45" y1="190" x2="690" y2="190" stroke="#d7deea"/>${bars}<circle cx="510" cy="18" r="5" fill="#2f9e78"/><text x="520" y="22" font-size="10" fill="#667085">Ingresos</text><circle cx="585" cy="18" r="5" fill="#405189"/><text x="595" y="22" font-size="10" fill="#667085">Egresos</text></svg>`;
    },
    budgetExecutionSvgEscape(value) {
      return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&apos;");
    },
    budgetExecutionCumulativeSvg() {
      const chart = this.budgetExecutionCumulativeChart;
      const grid = chart.grid.map((line) => `<line x1="54" y1="${line.y}" x2="684" y2="${line.y}" stroke="#e4e9f0"/><text x="47" y="${line.y + 4}" text-anchor="end" font-size="8" fill="#8792a4">${this.budgetExecutionSvgEscape(compactMoney(line.value))}</text>`).join("");
      const months = chart.rows.map((point) => `<text x="${point.x}" y="232" text-anchor="middle" font-size="8" fill="#8792a4">${this.budgetExecutionSvgEscape(point.short_label)}</text>`).join("");
      return `<svg width="720" height="245" viewBox="0 0 720 245" xmlns="http://www.w3.org/2000/svg"><rect width="720" height="245" rx="12" fill="#f7f9fc"/>${grid}<line x1="54" y1="${chart.zeroY}" x2="684" y2="${chart.zeroY}" stroke="#aeb8c8" stroke-dasharray="4 4"/><polyline points="${chart.incomePoints}" fill="none" stroke="#2f9e78" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><polyline points="${chart.expensePoints}" fill="none" stroke="#405189" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><polyline points="${chart.balancePoints}" fill="none" stroke="#c2414f" stroke-width="2" stroke-dasharray="5 4" stroke-linecap="round" stroke-linejoin="round"/>${months}<circle cx="472" cy="18" r="4" fill="#2f9e78"/><text x="481" y="22" font-size="9" fill="#667085">Ingresos</text><circle cx="548" cy="18" r="4" fill="#405189"/><text x="557" y="22" font-size="9" fill="#667085">Egresos</text><circle cx="625" cy="18" r="4" fill="#c2414f"/><text x="634" y="22" font-size="9" fill="#667085">Balance</text></svg>`;
    },
    budgetExecutionSubsidyMixSvg() {
      const mix = this.budgetExecutionSubsidyMix;
      const circumference = 2 * Math.PI * 72;
      const segments = mix.items.map((item) => {
        const length = (item.share / 100) * circumference;
        const offset = (item.dashoffset / 100) * circumference;
        return `<circle cx="120" cy="115" r="72" fill="none" stroke="${item.color}" stroke-width="24" stroke-dasharray="${length} ${circumference - length}" stroke-dashoffset="${offset}" transform="rotate(-90 120 115)"/>`;
      }).join("");
      const legend = mix.items.map((item, index) => {
        const y = 62 + (index * 34);
        return `<circle cx="285" cy="${y - 3}" r="5" fill="${item.color}"/><text x="299" y="${y}" font-size="10" font-weight="700" fill="#344054">${this.budgetExecutionSvgEscape(item.code.toUpperCase())}</text><text x="410" y="${y}" font-size="10" fill="#667085">${this.budgetExecutionSvgEscape(money(item.expense_budget))}</text><text x="665" y="${y}" text-anchor="end" font-size="10" font-weight="700" fill="#344054">${item.share.toLocaleString("es-CL", { maximumFractionDigits: 1 })}%</text>`;
      }).join("");
      return `<svg width="720" height="230" viewBox="0 0 720 230" xmlns="http://www.w3.org/2000/svg"><rect width="720" height="230" rx="12" fill="#f7f9fc"/><circle cx="120" cy="115" r="72" fill="none" stroke="#e5eaf1" stroke-width="24"/>${segments}<text x="120" y="111" text-anchor="middle" font-size="22" font-weight="700" fill="#263043">${mix.items.length}</text><text x="120" y="129" text-anchor="middle" font-size="9" fill="#8792a4">FUENTES</text>${legend}<text x="285" y="205" font-size="9" fill="#8792a4">Presupuesto consolidado</text><text x="665" y="205" text-anchor="end" font-size="11" font-weight="700" fill="#405189">${this.budgetExecutionSvgEscape(money(mix.total))}</text></svg>`;
    },
    budgetExecutionTopAccountsSvg() {
      const chart = this.budgetExecutionTopAccountsChart;
      const max = Math.max(1, ...chart.items.flatMap((item) => [Number(item.annual_budget || 0), Number(item.executed || 0)]));
      const rows = chart.items.map((item, index) => {
        const y = 48 + (index * 36);
        const budgetWidth = Math.max(1, (Number(item.annual_budget || 0) / max) * 300);
        const executedWidth = Math.max(0, (Number(item.executed || 0) / max) * 300);
        const account = item.account_name.length > 42 ? `${item.account_name.slice(0, 39)}…` : item.account_name;
        return `<text x="24" y="${y + 3}" font-size="9" font-weight="700" fill="#465366">${index + 1}. ${this.budgetExecutionSvgEscape(account)}</text><rect x="305" y="${y - 8}" width="300" height="12" rx="6" fill="#edf0f4"/><rect x="305" y="${y - 8}" width="${budgetWidth}" height="12" rx="6" fill="#d7dde8"/><rect x="305" y="${y - 4}" width="${executedWidth}" height="4" rx="2" fill="#405189"/><text x="690" y="${y + 3}" text-anchor="end" font-size="9" font-weight="700" fill="#344054">${this.budgetExecutionSvgEscape(money(item.chartValue))}</text>`;
      }).join("");
      return `<svg width="720" height="345" viewBox="0 0 720 345" xmlns="http://www.w3.org/2000/svg"><rect width="720" height="345" rx="12" fill="#f7f9fc"/><circle cx="520" cy="20" r="4" fill="#d7dde8"/><text x="529" y="24" font-size="9" fill="#667085">Presupuesto</text><circle cx="606" cy="20" r="4" fill="#405189"/><text x="615" y="24" font-size="9" fill="#667085">Ejecutado</text>${rows}</svg>`;
    },
    async downloadBudgetExecutionPdf() {
      if (!this.budgetExecution.has_data) return;
      this.downloadingBudgetExecutionPdf = true;
      try {
        const pdfMake = await getPdfMake();
        const data = this.budgetExecution;
        const metrics = data.metrics || {};
        const importedAt = data.import?.imported_at ? new Date(data.import.imported_at).toLocaleString("es-CL") : "-";
        const kpiCell = (label, value, note, color = "#405189") => ({
          margin: [0, 0, 8, 8],
          table: {
            widths: ["*"],
            body: [[{
              stack: [
                { text: label.toUpperCase(), style: "kpiLabel" },
                { text: value, style: "kpiValue", color },
                { text: note, style: "kpiNote" },
              ],
              margin: [12, 10, 12, 10],
              fillColor: "#f7f9fc",
            }]],
          },
          layout: { hLineColor: () => "#e1e6ee", vLineColor: () => "#e1e6ee" },
        });
        const subsidyRows = (data.subsidies || []).map((item) => [
          item.name,
          money(item.expense_budget),
          money(item.expense_executed),
          money(item.available),
          `${Number(item.execution_percentage || 0).toLocaleString("es-CL")}%`,
        ]);
        const categoryRows = (data.categories || []).slice(0, 16).map((item) => [
          item.category,
          item.subsidy_name,
          money(item.budget),
          money(item.executed),
          `${Number(item.execution_percentage || 0).toLocaleString("es-CL")}%`,
        ]);
        const accountRows = (data.accounts || []).map((item) => [
          item.account_name,
          item.subsidy_name,
          item.flow_type === "income" ? "Ingreso" : "Egreso",
          money(item.annual_budget),
          money(item.executed),
          money(item.variance),
          `${Number(item.execution_percentage || 0).toLocaleString("es-CL")}%`,
        ]);
        const tableHeader = (labels) => labels.map((label) => ({ text: label, color: "#ffffff", bold: true }));
        const tableLayout = {
          fillColor: (rowIndex) => rowIndex === 0 ? "#405189" : rowIndex % 2 === 0 ? "#f7f9fc" : null,
          hLineColor: () => "#e4e8ef",
          vLineColor: () => "#e4e8ef",
          paddingLeft: () => 6,
          paddingRight: () => 6,
          paddingTop: () => 5,
          paddingBottom: () => 5,
        };
        const accountPages = [];
        const accountPageSize = 12;
        const accountPageCount = Math.max(1, Math.ceil(accountRows.length / accountPageSize));
        for (let pageIndex = 0; pageIndex < accountPageCount; pageIndex += 1) {
          const rows = accountRows.slice(pageIndex * accountPageSize, (pageIndex + 1) * accountPageSize);
          accountPages.push(
            { text: "ANEXO DE CUENTAS", style: "sectionEyebrow", pageBreak: "before" },
            { text: "Detalle completo importado", style: "sectionTitle" },
            { text: `${accountRows.length} cuentas normalizadas desde ${data.import?.source_sheets?.join(", ") || "el libro fuente"} · página ${pageIndex + 1} de ${accountPageCount}.`, color: "#667085", margin: [0, 0, 0, 10] },
            { table: { headerRows: 1, dontBreakRows: true, widths: ["*", 54, 38, 62, 62, 62, 30], body: [tableHeader(["Cuenta", "Fuente", "Tipo", "Presupuesto", "Ejecutado", "Diferencia", "%"]), ...rows] }, layout: tableLayout },
          );
        }
        const documentDefinition = {
          pageSize: "A4",
          pageMargins: [38, 52, 38, 42],
          defaultStyle: { fontSize: 8, color: "#344054" },
          header: (page) => page > 1 ? ({
            margin: [38, 18, 38, 0],
            columns: [
              { text: "EJECUCIÓN PRESUPUESTARIA", color: "#405189", bold: true, fontSize: 8 },
              { text: `${data.year} · ${data.import?.school_name || "Establecimiento"}`, alignment: "right", color: "#7b8494", fontSize: 8 },
            ],
          }) : null,
          footer: (currentPage, pageCount) => ({
            margin: [38, 10, 38, 0],
            columns: [
              { text: "Informe de control interno", color: "#98a2b3", fontSize: 7 },
              { text: `${currentPage} / ${pageCount}`, alignment: "right", color: "#98a2b3", fontSize: 7 },
            ],
          }),
          content: [
            { canvas: [{ type: "rect", x: 0, y: 0, w: 519, h: 9, r: 4, color: "#405189" }], margin: [0, 0, 0, 28] },
            { text: "INFORME EJECUTIVO", color: "#2f9e78", bold: true, fontSize: 9, characterSpacing: 1.6 },
            { text: "Ejecución\npresupuestaria", fontSize: 30, bold: true, color: "#1d2939", lineHeight: 1.02, margin: [0, 7, 0, 8] },
            { text: `${data.year} · ${data.import?.school_name || "Establecimiento"}`, fontSize: 13, color: "#667085", margin: [0, 0, 0, 24] },
            {
              columns: [
                { width: "*", stack: [{ text: "CORTE INFORMADO", style: "metaLabel" }, { text: data.import?.reported_through_label || "Sin movimientos", style: "metaValue" }] },
                { width: "*", stack: [{ text: "ARCHIVO FUENTE", style: "metaLabel" }, { text: data.import?.original_filename || "-", style: "metaValue" }] },
                { width: "*", stack: [{ text: "ACTUALIZADO", style: "metaLabel" }, { text: importedAt, style: "metaValue" }] },
              ],
              columnGap: 14,
              margin: [0, 0, 0, 28],
            },
            { text: "PANORAMA GENERAL", style: "sectionEyebrow" },
            { text: "Indicadores de control", style: "sectionTitle" },
            {
              columns: [
                kpiCell("Presupuesto de egresos", money(metrics.expense_budget), "Base anual aprobada"),
                kpiCell("Egresos ejecutados", money(metrics.expense_executed), `${metrics.expense_execution_percentage || 0}% de ejecución`, "#c2414f"),
              ],
            },
            {
              columns: [
                kpiCell("Disponible", money(metrics.available_budget), "Presupuesto aún no ejecutado", "#2f9e78"),
                kpiCell("Resultado ejecutado", money(metrics.net_result), "Ingresos menos egresos", Number(metrics.net_result || 0) < 0 ? "#c2414f" : "#2f9e78"),
              ],
              margin: [0, 0, 0, 14],
            },
            { text: "EVOLUCIÓN MENSUAL", style: "sectionEyebrow", pageBreak: "before" },
            { text: "Ingresos y egresos ejecutados", style: "sectionTitle" },
            { svg: this.budgetExecutionChartSvg(), width: 519, margin: [0, 5, 0, 18] },
            { text: "TRAYECTORIA ACUMULADA", style: "sectionEyebrow" },
            { text: "Ingresos, egresos y balance progresivo", style: "sectionTitle" },
            { svg: this.budgetExecutionCumulativeSvg(), width: 519, margin: [0, 5, 0, 18] },
            { text: "LECTURA POR FUENTE", style: "sectionEyebrow", pageBreak: "before" },
            { text: "Composición del presupuesto por subvención", style: "sectionTitle" },
            { svg: this.budgetExecutionSubsidyMixSvg(), width: 519, margin: [0, 5, 0, 18] },
            { text: "Ejecución de egresos por subvención", style: "sectionTitle" },
            { table: { headerRows: 1, widths: ["*", 82, 82, 82, 50], body: [tableHeader(["Subvención", "Presupuesto", "Ejecutado", "Disponible", "%"]), ...subsidyRows] }, layout: tableLayout, margin: [0, 5, 0, 18], pageBreak: "after" },
            { text: "CONCENTRACIÓN DE CUENTAS", style: "sectionEyebrow" },
            { text: this.budgetExecutionTopAccountsChart.hasExecution ? "Cuentas con mayor ejecución" : "Cuentas con mayor peso presupuestario", style: "sectionTitle" },
            { svg: this.budgetExecutionTopAccountsSvg(), width: 519, margin: [0, 5, 0, 18] },
            { text: "FOCOS DE GESTIÓN", style: "sectionEyebrow" },
            { text: "Principales categorías de egreso", style: "sectionTitle" },
            { table: { headerRows: 1, widths: ["*", 78, 76, 76, 42], body: [tableHeader(["Categoría", "Subvención", "Presupuesto", "Ejecutado", "%"]), ...categoryRows] }, layout: tableLayout, margin: [0, 5, 0, 18] },
            ...accountPages,
          ],
          styles: {
            sectionEyebrow: { color: "#405189", bold: true, fontSize: 8, characterSpacing: 1.1, margin: [0, 0, 0, 3] },
            sectionTitle: { color: "#1d2939", bold: true, fontSize: 17, margin: [0, 0, 0, 10] },
            metaLabel: { color: "#98a2b3", bold: true, fontSize: 7, characterSpacing: 0.8 },
            metaValue: { color: "#344054", bold: true, fontSize: 9, margin: [0, 4, 0, 0] },
            kpiLabel: { color: "#7b8494", bold: true, fontSize: 7, characterSpacing: 0.6 },
            kpiValue: { bold: true, fontSize: 16, margin: [0, 5, 0, 3] },
            kpiNote: { color: "#98a2b3", fontSize: 7 },
          },
        };
        pdfMake.createPdf(documentDefinition).download(`ejecucion-presupuestaria-${data.year}.pdf`);
      } catch (error) {
        await Swal.fire("Error", "No se pudo generar el informe PDF.", "error");
      } finally {
        this.downloadingBudgetExecutionPdf = false;
      }
    },
    async changeSubsidyPeriod() {
      const selected = new Date(Number(this.subsidyYear), Number(this.subsidyMonth) - 1, 1);
      this.subsidyComparePeriod = toMonthKey(new Date(selected.getFullYear(), selected.getMonth() - 1, 1));
      await this.loadSubsidies();
    },
    async changeSubsidyCalculationOptions() {
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
    subsidyAnnualColumnHeight(item, key) {
      const value = Number(item?.[key] || 0);
      return `${Math.max(0, (value / this.subsidyAnnualChartMax) * 100)}%`;
    },
    subsidyAnnualMonthStatus(status) {
      return {
        sin_datos: { label: "Sin datos", className: "empty" },
        revisar: { label: "Revisar", className: "danger" },
        pendiente: { label: "Pendiente", className: "warning" },
        diferencia: { label: "Diferencia", className: "danger" },
        cuadrado: { label: "Cuadrado", className: "success" },
      }[status] || { label: "Sin datos", className: "empty" };
    },
    goToSubsidyAnnual() {
      this.$refs.subsidyAnnualSection?.scrollIntoView({ behavior: "smooth", block: "start" });
    },
    perStudentAverage(item) {
      return item?.average_per_student == null ? "Sin matrícula" : money(item.average_per_student);
    },
    attendancePeriodList(periods) {
      return (periods || []).map((period) => this.subsidyPeriodLabel(period)).join(" · ") || "Sin períodos";
    },
    attendanceDifferenceClass(value) {
      const amount = Number(value || 0);
      return amount > 0 ? "positive" : amount < 0 ? "negative" : "neutral";
    },
    attendanceGapReading(actual, expected) {
      if (expected == null) {
        return {
          label: "No calculable por asistencia",
          detail: "Requiere antecedentes adicionales",
          className: "pending",
        };
      }

      const actualAmount = Number(actual || 0);
      const expectedAmount = Number(expected || 0);
      const gap = actualAmount - expectedAmount;
      const tolerance = Math.max(1, Math.abs(expectedAmount) * 0.01);

      if (Math.abs(gap) <= tolerance) {
        return {
          label: "Dentro de tolerancia",
          detail: `Brecha de ${money(Math.abs(gap))}`,
          className: "matched",
        };
      }

      return gap < 0
        ? {
            label: `Faltaron ${money(Math.abs(gap))}`,
            detail: "Frente a lo debido por asistencia",
            className: "negative",
          }
        : {
            label: `Registrado sobre estimación: ${money(gap)}`,
            detail: "Revisar clasificación, reliquidaciones o glosas",
            className: "positive",
          };
    },
    attendanceLiquidationReading(actual, fullAttendance, scope = "total", requiresReview = false) {
      if (fullAttendance == null) {
        return {
          label: "No comparable con asistencia",
          detail: "Requiere antecedentes adicionales",
          className: "pending",
        };
      }

      const metrics = this.attendanceReconciliation.metrics || {};
      if (!Number(metrics.settlement_count || 0)) {
        return {
          label: "Sin liquidación MINEDUC",
          detail: `Máximo teórico ${money(fullAttendance || 0)}`,
          className: "pending",
        };
      }

      if (scope === "level" && Number(actual || 0) === 0 && Number(metrics.liquidated_total || 0) > 0) {
        return {
          label: "Sin desglose por nivel",
          detail: "La liquidación no asignó monto al nivel",
          className: "pending",
        };
      }

      if (requiresReview) {
        return {
          label: "Base SEP incompleta",
          detail: "La pérdida mínima aún no puede proyectarse",
          className: "pending",
        };
      }

      const actualAmount = Number(actual || 0);
      const fullAmount = Number(fullAttendance || 0);
      const loss = fullAmount - actualAmount;
      const tolerance = Math.max(1, Math.abs(fullAmount) * 0.01);

      if (Math.abs(loss) <= tolerance) {
        return {
          label: "Sin pérdida relevante",
          detail: `Brecha frente al 100%: ${money(Math.abs(loss))}`,
          className: "matched",
        };
      }

      if (loss > 0) {
        return {
          label: `Pérdida ${money(loss)}`,
          detail: "Diferencia entre el máximo al 100% y lo liquidado",
          className: "negative",
        };
      }

      return {
        label: "Base de cálculo por revisar",
        detail: "La liquidación supera la nómina teórica disponible",
        className: "pending",
      };
    },
    attendanceIncomeReading(actual, reference) {
      if (!Number(this.attendanceReconciliation.metrics?.income_records_count || 0)) {
        return {
          label: "Sin ingreso contabilizado",
          detail: Number(reference || 0)
            ? `Liquidación comparable pendiente: ${money(reference || 0)}`
            : "Aún no existe liquidación de referencia",
          className: "pending",
        };
      }

      const actualAmount = Number(actual || 0);
      const referenceAmount = Number(reference || 0);
      const gap = actualAmount - referenceAmount;
      const tolerance = Math.max(1, Math.abs(referenceAmount) * 0.01);

      if (Math.abs(gap) <= tolerance) {
        return {
          label: "Ingreso conciliado",
          detail: `Brecha frente a la liquidación: ${money(Math.abs(gap))}`,
          className: "matched",
        };
      }

      return gap < 0
        ? {
            label: `Falta contabilizar ${money(Math.abs(gap))}`,
            detail: "Frente a la liquidación comparable MINEDUC",
            className: "negative",
          }
        : {
            label: `Ingreso excede liquidación en ${money(gap)}`,
            detail: "Revisar glosas no comparables o clasificación contable",
            className: "pending",
          };
    },
    attendanceComparisonWidth(value) {
      return `${Math.max(0, Math.min(100, (Number(value || 0) / this.attendanceComparisonMax) * 100))}%`;
    },
    attendanceConcentrationLabel(value) {
      return {
        none: "sin concentración",
        "15_30": "15% a menos de 30%",
        "30_45": "30% a menos de 45%",
        "45_60": "45% a menos de 60%",
        "60_plus": "60% o más",
      }[value] || value || "sin banda";
    },
    async downloadSubsidyComparisonPdf() {
      this.downloadingSubsidyPdf = true;
      try {
        const pdfMake = await getPdfMake();
        const comparison = this.subsidyDashboard.comparison || {};
        const attendance = this.attendanceReconciliation || {};
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
        const attendanceLevelRows = (attendance.by_level || []).map((item) => [
          item.label,
          item.attendance_rate == null ? "-" : `${Number(item.attendance_rate).toLocaleString("es-CL")}%`,
          money(item.full_attendance_amount || 0),
          money(item.attendance_loss_amount || 0),
          money(item.expected_amount || 0),
          money(item.liquidated_amount ?? 0),
          this.attendanceLiquidationReading(item.liquidated_amount, item.full_attendance_amount, "level", Boolean(item.baseline_review_families?.length)).label,
        ]);
        if (!attendanceLevelRows.length) attendanceLevelRows.push(["Sin asistencia disponible", "-", "-", "-", "-", "-", "-"]);
        const attendanceSubsidyRows = (attendance.by_subsidy || []).map((item) => [
          item.label,
          item.full_attendance_amount == null ? "-" : money(item.full_attendance_amount),
          item.attendance_loss_amount == null ? "-" : money(item.attendance_loss_amount),
          item.expected_amount == null ? "No calculable" : money(item.expected_amount),
          money(item.liquidated_amount ?? 0),
          this.attendanceLiquidationReading(item.liquidated_amount, item.full_attendance_amount, "subsidy", item.baseline_requires_review).label,
        ]);
        if (!attendanceSubsidyRows.length) attendanceSubsidyRows.push(["Sin liquidaciones ni cálculo", "-", "-", "-", "-", "-"]);
        const attendanceIncomeRows = (attendance.income_records || []).map((item) => [
          item.code,
          shortDate(item.received_at),
          item.family_label,
          item.status,
          item.matched_to_settlement ? "Vinculado" : "Sin vínculo",
          money(item.amount || 0),
        ]);
        if (!attendanceIncomeRows.length) attendanceIncomeRows.push(["Sin ingresos de subvención en el mes", "-", "-", "-", "-", "-"]);
        const attendanceWindow = this.attendancePeriodList(attendance.window?.found_periods);
        const attendanceRequiredWindow = this.attendancePeriodList(attendance.window?.required_periods);
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
          pageMargins: [36, 42, 36, 62],
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
            { text: "Informe de conciliación de subvenciones", fontSize: 19, bold: true, color: "#25324b" },
            {
              margin: [0, 5, 0, 18],
              columns: [
                { text: `${currentPeriodLabel} · asistencia, liquidación MINEDUC e ingreso contable`, color: "#667085", fontSize: 10 },
                { text: `Emitido: ${new Date().toLocaleDateString("es-CL")}`, alignment: "right", color: "#667085", fontSize: 8 },
              ],
            },
            {
              columns: [
                {
                  width: "*",
                  stack: [
                    { text: "Línea base 100%", color: "#667085", fontSize: 8 },
                    { text: attendance.available ? money(attendance.metrics?.full_attendance_total || 0) : "Sin cálculo", color: "#405189", bold: true, fontSize: 13, margin: [0, 3, 0, 0] },
                  ],
                  margin: [8, 10, 8, 10],
                },
                {
                  width: "*",
                  stack: [
                    { text: "Merma por inasistencia", color: "#667085", fontSize: 8 },
                    { text: attendance.available ? money(attendance.metrics?.attendance_loss_total || 0) : "Sin cálculo", color: "#b75c32", bold: true, fontSize: 13, margin: [0, 3, 0, 0] },
                  ],
                  margin: [8, 10, 8, 10],
                },
                {
                  width: "*",
                  stack: [
                    { text: "Modelo con asistencia local", color: "#667085", fontSize: 8 },
                    { text: attendance.available ? money(attendance.metrics?.expected_total || 0) : "Sin cálculo", color: "#25324b", bold: true, fontSize: 13, margin: [0, 3, 0, 0] },
                  ],
                  margin: [8, 10, 8, 10],
                },
                {
                  width: "*",
                  stack: [
                    { text: "Ingreso registrado", color: "#667085", fontSize: 8 },
                    { text: money(attendance.metrics?.registered_comparable_income_total || 0), color: "#237b5b", bold: true, fontSize: 13, margin: [0, 3, 0, 0] },
                  ],
                  margin: [8, 10, 8, 10],
                },
              ],
              columnGap: 8,
              margin: [0, 0, 0, 18],
            },
            { text: "Base de cálculo", style: "sectionTitle" },
            {
              margin: [0, 0, 0, 8],
              table: {
                widths: ["*"],
                body: [[{
                  stack: [
                    { text: `Ventana legal previa al pago: ${attendanceRequiredWindow}`, bold: true, color: "#344054", fontSize: 8 },
                    { text: `Meses disponibles: ${attendanceWindow} · Cobertura ${Number(attendance.metrics?.coverage_percentage || 0).toLocaleString("es-CL")}%`, margin: [0, 3, 0, 0], color: "#667085", fontSize: 8 },
                    { text: `Supuestos: ${attendance.assumptions?.jec ? "con JEC" : "sin JEC"}; SEP ${attendance.assumptions?.sep_category || "-"}; ${this.attendanceConcentrationLabel(attendance.assumptions?.concentration_band)}; USE ${attendance.assumptions?.use_value ? money(attendance.assumptions.use_value) : "sin parámetro"}.`, margin: [0, 3, 0, 0], color: "#667085", fontSize: 8 },
                    { text: `Liquidado comparable MINEDUC ${money(attendance.metrics?.liquidated_total || 0)}; total del documento ${money(attendance.metrics?.liquidated_gross_total || 0)} · ${this.attendanceLiquidationReading(attendance.metrics?.liquidated_total, attendance.metrics?.full_attendance_total).label} · ${this.attendanceIncomeReading(attendance.metrics?.registered_comparable_income_total, attendance.metrics?.liquidated_total).label}.`, margin: [0, 3, 0, 0], color: "#667085", fontSize: 8 },
                  ],
                  fillColor: "#f3f6fa",
                  margin: [8, 7, 8, 7],
                }]],
              },
              layout: "noBorders",
            },
            { text: "Desglose por nivel", style: "sectionTitle" },
            table(["Nivel", "% asist.", "Pudo llegar", "Pérdida", "Modelo local", "Liquidado comparable", "Lectura"], ["*", 38, 61, 61, 61, 61, 98], attendanceLevelRows),
            { text: "Desglose por subvención", style: "sectionTitle", pageBreak: "before" },
            table(["Subvención", "Pudo llegar", "Pérdida", "Modelo local", "Liquidado comparable", "Lectura"], ["*", 68, 68, 68, 68, 112], attendanceSubsidyRows),
            { text: "Ingresos contabilizados del mes", style: "sectionTitle" },
            table(["Código", "Fecha", "Clasificación", "Estado", "Vínculo", "Monto"], [80, 55, "*", 55, 55, 75], attendanceIncomeRows),
            ...(attendance.warnings?.length ? [{
              text: [{ text: "Controles: ", bold: true }, attendance.warnings.join(" · ")],
              margin: [0, 14, 0, 0],
              color: "#806326",
              fillColor: "#fff7e6",
              fontSize: 7,
            }] : []),
            ...(attendance.sources?.length ? [{
              margin: [0, 12, 0, 0],
              stack: [
                { text: "Fuentes normativas oficiales", bold: true, color: "#344054", fontSize: 8 },
                ...attendance.sources.map((source) => ({ text: source.label, link: source.url, color: "#405189", decoration: "underline", fontSize: 7, margin: [0, 3, 0, 0] })),
              ],
            }] : []),
            {
              margin: [0, 18, 0, 0],
              stack: [
                { text: "Método y alcance", bold: true, color: "#25324b", fontSize: 10 },
                { text: "La subvención general se estima multiplicando la asistencia media promedio de los tres meses inmediatamente anteriores al pago por el factor USE del nivel y el valor USE vigente. El artículo 13 contempla excepciones y reliquidaciones para los primeros meses del año escolar. SEP prioritaria y preferente aplican el mismo criterio sobre la asistencia equivalente de las alumnas clasificadas.", margin: [0, 6, 0, 0], color: "#667085", fontSize: 8, lineHeight: 1.25 },
                { text: "La merma por inasistencia es la diferencia contra un escenario teórico de 100% de asistencia; sirve como indicador de gestión y no constituye por sí sola un monto exigible al MINEDUC.", margin: [0, 6, 0, 0], color: "#667085", fontSize: 8, lineHeight: 1.25 },
                { text: "PIE, zona, ruralidad, reliquidaciones, topes, Pro-Retención y bonos se excluyen del esperado cuando requieren antecedentes adicionales; sus montos efectivos permanecen visibles en la liquidación. Este documento es un control interno y no reemplaza la liquidación oficial MINEDUC.", margin: [0, 6, 0, 0], color: "#667085", fontSize: 8, lineHeight: 1.25 },
              ],
            },
            { text: "Liquidaciones y comparación mensual", style: "sectionTitle", pageBreak: "before" },
            {
              columns: [
                { width: "*", stack: [{ text: "Líquido actual", color: "#667085", fontSize: 8 }, { text: money(this.subsidyDashboard.metrics?.net_liquidated || 0), color: "#25324b", bold: true, fontSize: 14, margin: [0, 3, 0, 0] }], margin: [10, 10, 10, 10] },
                { width: "*", stack: [{ text: "Ingreso contabilizado", color: "#667085", fontSize: 8 }, { text: money(this.subsidyDashboard.metrics?.income_total || 0), color: "#25324b", bold: true, fontSize: 14, margin: [0, 3, 0, 0] }], margin: [10, 10, 10, 10] },
                { width: "*", stack: [{ text: "Sin asignar", color: "#667085", fontSize: 8 }, { text: money(this.subsidyDashboard.metrics?.unallocated_total || 0), color: "#25324b", bold: true, fontSize: 14, margin: [0, 3, 0, 0] }], margin: [10, 10, 10, 10] },
              ],
              columnGap: 8,
              margin: [0, 0, 0, 12],
            },
            { text: `Comparación ${currentPeriodLabel} versus ${comparisonPeriodLabel}`, style: "sectionTitle" },
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

        pdfMake.createPdf(documentDefinition).download(`conciliacion-subvenciones-asistencia-${this.subsidyPeriod}.pdf`);
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
        maintenance: "Subvención de Mantenimiento",
        staff_bonuses: "Bonos al personal",
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
    maintenanceLine(settlement) {
      return (settlement?.lines || []).find((line) => line.concept_code === "maintenance") || null;
    },
    maintenanceRows(settlement) {
      return this.maintenanceLine(settlement)?.allocations || [];
    },
    maintenanceData(allocation) {
      return allocation?.source_payload?._maintenance || {};
    },
    staffBonusLines(settlement) {
      return (settlement?.lines || []).filter((line) => line.concept_code?.startsWith("staff_"));
    },
    staffBonusRows(settlement) {
      return this.staffBonusLines(settlement).flatMap((line) => (line.allocations || []).map((allocation) => ({
        allocation,
        conceptName: line.concept_name,
      })));
    },
    staffBonusData(allocation) {
      return allocation?.source_payload?._staff_bonus || {};
    },
    reliquidationLine(settlement) {
      return (settlement?.lines || []).find((line) => line.concept_code === "reliquidation") || null;
    },
    reliquidationRows(settlement) {
      return this.reliquidationLine(settlement)?.allocations || [];
    },
    reliquidationData(allocation) {
      return allocation?.source_payload?._reliquidation || {};
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

      <template v-else-if="isBudgetExecution">
        <section class="content-card be-command-bar">
          <div class="be-year-control">
            <span class="toolbar-kicker">PERÍODO DE ANÁLISIS</span>
            <div>
              <BFormSelect v-model="budgetExecutionYear" class="be-year-select" @change="changeBudgetExecutionYear">
                <option v-for="year in budgetExecutionYearOptions" :key="year" :value="year">Año {{ year }}</option>
              </BFormSelect>
              <span v-if="budgetExecution.has_data" class="be-current-badge"><i class="bx bx-check-circle"></i> Versión vigente</span>
              <span v-else class="be-current-badge empty"><i class="bx bx-cloud-upload"></i> Pendiente de carga</span>
            </div>
          </div>
          <div class="be-command-actions">
            <input ref="budgetExecutionFile" type="file" class="d-none" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" @change="uploadBudgetExecution" />
            <BButton v-if="canExportBudgetExecution" variant="outline-secondary" :disabled="!budgetExecution.has_data || downloadingBudgetExecutionPdf" @click="downloadBudgetExecutionPdf">
              <span v-if="downloadingBudgetExecutionPdf" class="spinner-border spinner-border-sm"></span>
              <i v-else class="bx bxs-file-pdf"></i> Informe PDF
            </BButton>
            <BButton v-if="canImportBudgetExecution" variant="primary" :disabled="importingBudgetExecution" @click="openBudgetExecutionFilePicker">
              <span v-if="importingBudgetExecution" class="spinner-border spinner-border-sm"></span>
              <i v-else class="bx bx-upload"></i> {{ budgetExecution.has_data ? 'Reemplazar Excel' : 'Cargar Excel' }}
            </BButton>
          </div>
          <div v-if="budgetExecution.has_data" class="be-version-strip">
            <span><i class="bx bx-spreadsheet"></i>{{ budgetExecution.import.original_filename }}</span>
            <span><i class="bx bx-calendar-check"></i>Corte: {{ budgetExecution.import.reported_through_label }}</span>
            <span><i class="bx bx-layer"></i>{{ budgetExecution.import.line_count }} cuentas · {{ budgetExecution.import.source_sheets.join(', ') }}</span>
            <span><i class="bx bx-user"></i>{{ budgetExecution.import.imported_by || 'Usuario autorizado' }} · {{ shortDate(budgetExecution.import.imported_at) }}</span>
          </div>
        </section>

        <section v-if="!budgetExecution.has_data" class="content-card be-empty-state">
          <div class="be-empty-illustration">
            <span class="be-sheet sheet-back"></span><span class="be-sheet sheet-front"><i class="bx bx-bar-chart-alt-2"></i></span>
          </div>
          <span class="toolbar-kicker">EJECUCIÓN {{ budgetExecutionYear }}</span>
          <h2>Convierte el presupuesto mensual en decisiones claras</h2>
          <p>Carga el archivo Excel del establecimiento. El sistema reconocerá cuentas, categorías y subvenciones para construir el tablero y el informe ejecutivo.</p>
          <div class="be-empty-features">
            <span><i class="bx bx-check"></i> General, Mantención, SEP y PIE</span>
            <span><i class="bx bx-check"></i> Reemplazo controlado por año</span>
            <span><i class="bx bx-check"></i> Informe PDF de alta presentación</span>
          </div>
          <BButton v-if="canImportBudgetExecution" variant="primary" size="lg" @click="openBudgetExecutionFilePicker"><i class="bx bx-upload"></i> Seleccionar archivo .xlsx</BButton>
        </section>

        <template v-else>
          <div class="be-kpi-grid">
            <article class="be-kpi-card primary">
              <div class="be-kpi-icon"><i class="bx bx-target-lock"></i></div>
              <div><span>Presupuesto de egresos</span><strong>{{ money(budgetExecution.metrics.expense_budget) }}</strong><small>Base anual consolidada</small></div>
            </article>
            <article class="be-kpi-card">
              <div class="be-kpi-icon coral"><i class="bx bx-trending-up"></i></div>
              <div><span>Egresos ejecutados</span><strong>{{ money(budgetExecution.metrics.expense_executed) }}</strong><small>{{ budgetExecution.metrics.expense_execution_percentage || 0 }}% consumido</small></div>
            </article>
            <article class="be-kpi-card">
              <div class="be-kpi-icon green"><i class="bx bx-wallet"></i></div>
              <div><span>Saldo disponible</span><strong>{{ money(budgetExecution.metrics.available_budget) }}</strong><small>Presupuesto por ejecutar</small></div>
            </article>
            <article class="be-kpi-card" :class="{ negative: Number(budgetExecution.metrics.net_result || 0) < 0 }">
              <div class="be-kpi-icon ink"><i class="bx bx-line-chart"></i></div>
              <div><span>Resultado ejecutado</span><strong>{{ money(budgetExecution.metrics.net_result) }}</strong><small>Ingresos menos egresos</small></div>
            </article>
          </div>

          <div class="be-analysis-grid">
            <section class="content-card be-monthly-card">
              <div class="card-heading be-card-heading">
                <div><span>RITMO DEL AÑO</span><h2>Movimiento mensual</h2></div>
                <div class="be-chart-legend"><span class="income"><i></i>Ingresos</span><span class="expense"><i></i>Egresos</span></div>
              </div>
              <div class="be-month-chart">
                <div class="be-chart-axis"><span>{{ money(budgetExecutionMonthlyMax) }}</span><span>{{ money(budgetExecutionMonthlyMax / 2) }}</span><span>$0</span></div>
                <div v-for="month in budgetExecution.monthly" :key="month.month" class="be-month-column">
                  <div class="be-bars">
                    <span class="income" :style="{ height: budgetExecutionBarHeight(month.income) }" :title="`${month.label}: ${money(month.income)} en ingresos`"></span>
                    <span class="expense" :style="{ height: budgetExecutionBarHeight(month.expense) }" :title="`${month.label}: ${money(month.expense)} en egresos`"></span>
                  </div>
                  <strong>{{ month.short_label }}</strong>
                </div>
              </div>
              <div class="be-chart-summary">
                <div><span>Ingresos ejecutados</span><strong>{{ money(budgetExecution.metrics.income_executed) }}</strong></div>
                <div><span>Proyección anual de egresos</span><strong>{{ money(budgetExecution.metrics.projected_expenses) }}</strong></div>
                <div><span>Holgura proyectada</span><strong :class="{ 'text-danger': Number(budgetExecution.metrics.projected_variance || 0) < 0 }">{{ money(budgetExecution.metrics.projected_variance) }}</strong></div>
              </div>
            </section>

            <section class="content-card be-health-card">
              <div class="card-heading be-card-heading"><div><span>CONTROL DE DESVIACIONES</span><h2>Salud presupuestaria</h2></div><i class="bx bx-pulse"></i></div>
              <div class="be-health-score">
                <div class="be-ring" :style="{ '--progress': budgetExecutionProgress(budgetExecution.metrics.expense_execution_percentage) }">
                  <div><strong>{{ budgetExecution.metrics.expense_execution_percentage || 0 }}%</strong><span>ejecutado</span></div>
                </div>
                <div>
                  <span class="be-status-pill" :class="budgetExecutionStatus(budgetExecution.metrics.expense_execution_percentage).className">{{ budgetExecutionStatus(budgetExecution.metrics.expense_execution_percentage).label }}</span>
                  <p>Avance consolidado respecto del presupuesto anual de egresos.</p>
                </div>
              </div>
              <div class="be-alert-list">
                <div :class="{ danger: budgetExecution.alerts.over_executed_accounts > 0 }"><i class="bx bx-error-circle"></i><span>Cuentas sobre ejecutadas</span><strong>{{ budgetExecution.alerts.over_executed_accounts || 0 }}</strong></div>
                <div :class="{ warning: budgetExecution.alerts.unbudgeted_movements > 0 }"><i class="bx bx-question-mark"></i><span>Movimientos sin presupuesto</span><strong>{{ budgetExecution.alerts.unbudgeted_movements || 0 }}</strong></div>
              </div>
            </section>
          </div>

          <div class="be-visual-grid">
            <section class="content-card be-cumulative-card">
              <div class="card-heading be-card-heading">
                <div><span>TRAYECTORIA FINANCIERA</span><h2>Curva acumulada del año</h2></div>
                <div class="be-chart-legend be-line-legend">
                  <span class="income"><i></i>Ingresos</span>
                  <span class="expense"><i></i>Egresos</span>
                  <span class="balance"><i></i>Balance</span>
                </div>
              </div>
              <div class="be-cumulative-chart">
                <svg viewBox="0 0 720 245" role="img" aria-label="Ingresos, egresos y balance acumulados por mes">
                  <defs>
                    <linearGradient id="be-income-area" x1="0" x2="0" y1="0" y2="1">
                      <stop offset="0" stop-color="#2f9e78" stop-opacity=".22" />
                      <stop offset="1" stop-color="#2f9e78" stop-opacity="0" />
                    </linearGradient>
                  </defs>
                  <g v-for="line in budgetExecutionCumulativeChart.grid" :key="line.y">
                    <line x1="54" :y1="line.y" x2="684" :y2="line.y" class="be-svg-grid" />
                    <text x="46" :y="line.y + 4" text-anchor="end" class="be-svg-axis-label">{{ compactMoney(line.value) }}</text>
                  </g>
                  <line x1="54" :y1="budgetExecutionCumulativeChart.zeroY" x2="684" :y2="budgetExecutionCumulativeChart.zeroY" class="be-svg-zero" />
                  <polygon v-if="budgetExecutionCumulativeChart.incomeArea" :points="budgetExecutionCumulativeChart.incomeArea" fill="url(#be-income-area)" />
                  <polyline :points="budgetExecutionCumulativeChart.incomePoints" class="be-svg-line income" />
                  <polyline :points="budgetExecutionCumulativeChart.expensePoints" class="be-svg-line expense" />
                  <polyline :points="budgetExecutionCumulativeChart.balancePoints" class="be-svg-line balance" />
                  <g v-for="point in budgetExecutionCumulativeChart.rows" :key="point.month">
                    <circle :cx="point.x" :cy="point.incomeY" r="3" class="be-svg-dot income"><title>{{ point.label }} · Ingresos acumulados {{ money(point.cumulativeIncome) }}</title></circle>
                    <circle :cx="point.x" :cy="point.expenseY" r="3" class="be-svg-dot expense"><title>{{ point.label }} · Egresos acumulados {{ money(point.cumulativeExpense) }}</title></circle>
                    <circle :cx="point.x" :cy="point.balanceY" r="3" class="be-svg-dot balance"><title>{{ point.label }} · Balance acumulado {{ money(point.cumulativeBalance) }}</title></circle>
                    <text :x="point.x" y="232" text-anchor="middle" class="be-svg-month">{{ point.short_label }}</text>
                  </g>
                </svg>
              </div>
            </section>

            <section class="content-card be-mix-card">
              <div class="card-heading be-card-heading"><div><span>ESTRUCTURA DEL PRESUPUESTO</span><h2>Distribución por subvención</h2></div></div>
              <div class="be-mix-body">
                <div class="be-donut-wrap">
                  <svg viewBox="0 0 180 180" role="img" aria-label="Distribución del presupuesto de egresos por subvención">
                    <circle cx="90" cy="90" r="64" pathLength="100" class="be-donut-base" />
                    <circle
                      v-for="item in budgetExecutionSubsidyMix.items"
                      :key="item.code"
                      cx="90"
                      cy="90"
                      r="64"
                      pathLength="100"
                      class="be-donut-segment"
                      :stroke="item.color"
                      :stroke-dasharray="item.dasharray"
                      :stroke-dashoffset="item.dashoffset"
                    ><title>{{ item.name }} · {{ item.share.toLocaleString('es-CL', { maximumFractionDigits: 1 }) }}%</title></circle>
                    <text x="90" y="86" text-anchor="middle" class="be-donut-value">{{ budgetExecutionSubsidyMix.items.length }}</text>
                    <text x="90" y="103" text-anchor="middle" class="be-donut-label">fuentes</text>
                  </svg>
                </div>
                <div class="be-mix-legend">
                  <div v-for="item in budgetExecutionSubsidyMix.items" :key="item.code">
                    <i :style="{ background: item.color }"></i>
                    <span><strong>{{ item.code.toUpperCase() }}</strong><small>{{ money(item.expense_budget) }}</small></span>
                    <b>{{ item.share.toLocaleString('es-CL', { maximumFractionDigits: 1 }) }}%</b>
                  </div>
                </div>
              </div>
              <footer class="be-chart-note">Presupuesto consolidado: <strong>{{ money(budgetExecutionSubsidyMix.total) }}</strong></footer>
            </section>

            <section class="content-card be-ranking-card">
              <div class="card-heading be-card-heading">
                <div><span>CONCENTRACIÓN DEL GASTO</span><h2>{{ budgetExecutionTopAccountsChart.hasExecution ? 'Cuentas con mayor ejecución' : 'Cuentas con mayor peso presupuestario' }}</h2></div>
                <div class="be-chart-legend"><span class="budget"><i></i>Presupuesto</span><span class="expense"><i></i>Ejecutado</span></div>
              </div>
              <div class="be-ranking-list">
                <div v-for="(item, index) in budgetExecutionTopAccountsChart.items" :key="item.id" class="be-ranking-row">
                  <span class="be-rank-number">{{ index + 1 }}</span>
                  <div class="be-rank-copy"><strong>{{ item.account_name }}</strong><small>{{ item.subsidy_name }} · {{ item.category }}</small></div>
                  <div class="be-rank-bars">
                    <i class="budget" :style="{ width: item.budgetWidth }"></i>
                    <i class="executed" :style="{ width: item.executedWidth }"></i>
                  </div>
                  <strong>{{ money(item.chartValue) }}</strong>
                </div>
              </div>
            </section>

            <section class="content-card be-heatmap-card">
              <div class="card-heading be-card-heading"><div><span>ESTACIONALIDAD</span><h2>Mapa mensual de intensidad</h2></div><small>Color por egreso · signo por balance</small></div>
              <div class="be-heatmap">
                <article v-for="month in budgetExecution.monthly" :key="month.month" :style="{ background: budgetExecutionHeatColor(month) }">
                  <span>{{ month.short_label }}</span>
                  <strong>{{ money(month.expense) }}</strong>
                  <small :class="{ positive: month.balance > 0, negative: month.balance < 0 }">{{ month.balance > 0 ? '+' : '' }}{{ money(month.balance) }}</small>
                </article>
              </div>
              <div class="be-heat-legend"><span><i class="low"></i>Baja intensidad</span><span><i class="high"></i>Alta intensidad</span><span><b>±</b>Balance mensual</span></div>
            </section>
          </div>

          <section class="content-card be-subsidy-section">
            <div class="card-heading be-card-heading"><div><span>FUENTES DE FINANCIAMIENTO</span><h2>Lectura por subvención</h2></div><small>Presupuesto y ejecución de egresos</small></div>
            <div class="be-subsidy-grid">
              <article v-for="item in budgetExecution.subsidies" :key="item.code" class="be-subsidy-card" :class="`source-${item.code}`">
                <header><span class="be-source-mark">{{ item.code.toUpperCase() }}</span><span class="be-status-pill" :class="budgetExecutionStatus(item.execution_percentage).className">{{ budgetExecutionStatus(item.execution_percentage).label }}</span></header>
                <h3>{{ item.name }}</h3>
                <strong>{{ money(item.expense_executed) }}</strong>
                <span>de {{ money(item.expense_budget) }}</span>
                <div class="be-progress"><i :style="{ width: budgetExecutionProgress(item.execution_percentage) }"></i></div>
                <footer><span>{{ item.execution_percentage || 0 }}% ejecutado</span><strong>{{ money(item.available) }} disponible</strong></footer>
              </article>
            </div>
          </section>

          <div class="be-detail-grid">
            <section class="content-card be-category-card">
              <div class="card-heading be-card-heading"><div><span>COMPOSICIÓN DEL GASTO</span><h2>Categorías con mayor ejecución</h2></div></div>
              <div class="be-category-list">
                <div v-for="item in budgetExecution.categories.slice(0, 10)" :key="`${item.subsidy_code}-${item.category}`" class="be-category-row">
                  <div class="be-category-meta"><div><strong>{{ item.category }}</strong><span>{{ item.subsidy_name }}</span></div><strong>{{ money(item.executed) }}</strong></div>
                  <div class="be-category-track"><i class="budget" :style="{ width: budgetExecutionCategoryWidth(item.budget) }"></i><i class="executed" :style="{ width: budgetExecutionCategoryWidth(item.executed) }"></i></div>
                  <div class="be-category-foot"><span>{{ item.execution_percentage || 0 }}% del presupuesto</span><span>{{ money(item.variance) }} disponible</span></div>
                </div>
              </div>
            </section>

            <section class="content-card be-insight-card">
              <div class="card-heading be-card-heading"><div><span>LECTURA EJECUTIVA</span><h2>Señales para la gestión</h2></div><i class="bx bx-bulb"></i></div>
              <div class="be-insights">
                <article><i class="bx bx-calendar"></i><div><strong>Corte del archivo</strong><span>{{ budgetExecution.import.reported_through_label }}</span></div></article>
                <article><i class="bx bx-line-chart-down"></i><div><strong>Proyección de egresos</strong><span>{{ money(budgetExecution.metrics.projected_expenses) }} al cierre</span></div></article>
                <article><i class="bx bx-shield-quarter"></i><div><strong>Margen presupuestario</strong><span>{{ money(budgetExecution.metrics.available_budget) }} aún disponible</span></div></article>
                <article><i class="bx bx-data"></i><div><strong>Trazabilidad</strong><span>{{ budgetExecution.import.line_count }} cuentas desde {{ budgetExecution.import.source_sheets.length }} hojas</span></div></article>
              </div>
            </section>
          </div>

          <section class="content-card be-account-card">
            <div class="records-toolbar be-account-toolbar">
              <div><span class="toolbar-kicker">DETALLE AUDITABLE</span><h2>Cuentas importadas <span class="record-count">{{ budgetExecutionAccounts.length }}</span></h2></div>
              <div class="be-account-filters">
                <div class="be-segmented"><button type="button" :class="{ active: budgetExecutionFlow === 'expense' }" @click="budgetExecutionFlow = 'expense'">Egresos</button><button type="button" :class="{ active: budgetExecutionFlow === 'income' }" @click="budgetExecutionFlow = 'income'">Ingresos</button></div>
                <BFormSelect v-model="budgetExecutionSubsidy" class="be-filter-select"><option value="all">Todas las subvenciones</option><option v-for="item in budgetExecution.subsidies" :key="item.code" :value="item.code">{{ item.name }}</option></BFormSelect>
                <div class="search-box"><i class="bx bx-search"></i><input v-model="budgetExecutionSearch" type="search" placeholder="Buscar cuenta..." /></div>
              </div>
            </div>
            <div class="table-responsive be-account-table-wrap">
              <table class="table accounting-table be-account-table align-middle mb-0">
                <thead><tr><th>Cuenta</th><th>Subvención</th><th>Categoría</th><th class="text-end">Presupuesto</th><th class="text-end">Ejecutado</th><th class="text-end">Disponible</th><th>Avance</th></tr></thead>
                <tbody>
                  <tr v-for="account in budgetExecutionAccounts" :key="account.id" :class="{ 'be-overrun-row': account.execution_percentage > 100 }">
                    <td><strong>{{ account.account_name }}</strong></td><td><span class="be-table-source" :class="`source-${account.subsidy_code}`">{{ account.subsidy_code.toUpperCase() }}</span></td><td>{{ account.category }}</td><td class="text-end">{{ money(account.annual_budget) }}</td><td class="text-end fw-semibold">{{ money(account.executed) }}</td><td class="text-end" :class="{ 'text-danger fw-semibold': account.variance < 0 }">{{ money(account.variance) }}</td>
                    <td><div class="be-table-progress"><i :style="{ width: budgetExecutionProgress(account.execution_percentage) }"></i><span>{{ account.execution_percentage || 0 }}%</span></div></td>
                  </tr>
                  <tr v-if="!budgetExecutionAccounts.length"><td colspan="7"><div class="empty-state"><i class="bx bx-search-alt"></i><strong>Sin cuentas para estos filtros</strong><span>Prueba otra subvención o término de búsqueda.</span></div></td></tr>
                </tbody>
              </table>
            </div>
          </section>
        </template>
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
            <BButton variant="light" class="subsidy-annual-jump" @click="goToSubsidyAnnual">
              <i class="bx bx-line-chart"></i> Resumen anual
            </BButton>
            <BButton
              variant="outline-secondary"
              :disabled="downloadingSubsidyPdf"
              @click="downloadSubsidyComparisonPdf"
            >
              <span v-if="downloadingSubsidyPdf" class="spinner-border spinner-border-sm"></span>
              <i v-else class="bx bxs-file-pdf"></i> Conciliación PDF
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
            <span>Importa órdenes, anexos, Mantenimiento, reliquidaciones, Pro-Retención o nóminas de bonos de <strong>{{ subsidyPeriodLabel(subsidyPeriod) }}</strong>. El sistema valida mes, año y contenido antes de guardar.</span>
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

        <section class="content-card attendance-reconciliation-card">
          <header class="attendance-reconciliation-header">
            <div class="attendance-reconciliation-title">
              <span class="attendance-reconciliation-icon"><i class="bx bx-calculator"></i></span>
              <div>
                <span class="toolbar-kicker">CONCILIACIÓN DESDE ASISTENCIA</span>
                <h2>Asistencia, liquidación MINEDUC e ingreso contable</h2>
                <p>Conciliación para {{ subsidyPeriodLabel(subsidyPeriod) }}: cuánto correspondía por asistencia, qué se liquidó y qué quedó registrado en Ingresos.</p>
              </div>
            </div>
            <span class="attendance-status" :class="attendanceReconciliationStatus.className">
              <i class="bx" :class="attendanceReconciliationStatus.icon"></i>{{ attendanceReconciliationStatus.label }}
            </span>
          </header>

          <div class="attendance-assumptions">
            <label>
              <span>Jornada</span>
              <BFormSelect v-model="subsidyCalculationOptions.jec" @change="changeSubsidyCalculationOptions">
                <option value="1">Con JEC</option>
                <option value="0">Sin JEC</option>
              </BFormSelect>
            </label>
            <label>
              <span>Categoría SEP</span>
              <BFormSelect v-model="subsidyCalculationOptions.sep_category" @change="changeSubsidyCalculationOptions">
                <option value="autonomo">Autónomo</option>
                <option value="emergente">Emergente</option>
              </BFormSelect>
            </label>
            <label>
              <span>Gratuidad</span>
              <BFormSelect v-model="subsidyCalculationOptions.include_gratuity" @change="changeSubsidyCalculationOptions">
                <option value="1">Incluir</option>
                <option value="0">No incluir</option>
              </BFormSelect>
            </label>
            <label>
              <span>Concentración SEP</span>
              <BFormSelect v-model="subsidyCalculationOptions.concentration_band" @change="changeSubsidyCalculationOptions">
                <option value="auto">Estimar automáticamente</option>
                <option value="none">Sin concentración</option>
                <option value="15_30">15% a menos de 30%</option>
                <option value="30_45">30% a menos de 45%</option>
                <option value="45_60">45% a menos de 60%</option>
                <option value="60_plus">60% o más</option>
              </BFormSelect>
            </label>
          </div>

          <div class="attendance-window-strip">
            <div>
              <i class="bx bx-calendar"></i>
              <span>Ventana usada</span>
              <strong>{{ attendancePeriodList(attendanceReconciliation.window?.found_periods) }}</strong>
            </div>
            <div>
              <span>Cobertura</span>
              <strong>{{ Number(attendanceReconciliation.metrics?.coverage_percentage || 0).toLocaleString('es-CL') }}%</strong>
              <span class="attendance-coverage-track"><i :style="{ width: `${attendanceReconciliation.metrics?.coverage_percentage || 0}%` }"></i></span>
            </div>
            <div>
              <span>USE aplicada</span>
              <strong>{{ attendanceReconciliation.assumptions?.use_value ? money(attendanceReconciliation.assumptions.use_value) : '-' }}</strong>
              <small>{{ attendanceReconciliation.assumptions?.rate_label || 'Sin tabla parametrizada' }}</small>
            </div>
          </div>
          <div class="attendance-rule-note">
            <i class="bx bx-check-shield"></i>
            <div>
              <strong>Regla oficial verificada</strong>
              <span>El pago de {{ subsidyPeriodLabel(subsidyPeriod) }} usa la asistencia media promedio de {{ attendancePeriodList(attendanceReconciliation.window?.required_periods) }}. Al inicio del año escolar se aplican las excepciones y reliquidaciones del artículo 13.</span>
            </div>
          </div>

          <template v-if="attendanceReconciliation.available">
            <div class="attendance-kpi-grid">
              <article class="attendance-kpi baseline">
                <span>Máximo comparable a 100% asistencia</span>
                <strong>{{ money(attendanceReconciliation.metrics?.full_attendance_total) }}</strong>
                <small>Modelo normativo; ajustado con la liquidación si la nómina SEP está incompleta</small>
              </article>
              <article class="attendance-kpi loss">
                <span>Pérdida frente al máximo verificable</span>
                <strong>{{ money(attendanceReconciliation.metrics?.attendance_loss_total) }}</strong>
                <small>{{ attendanceReconciliation.metrics?.attendance_loss_percentage == null ? 'Sin base' : `${Number(attendanceReconciliation.metrics.attendance_loss_percentage).toLocaleString('es-CL')}% de la línea base` }}</small>
              </article>
              <article class="attendance-kpi primary">
                <span>Modelo con asistencia local</span>
                <strong>{{ money(attendanceReconciliation.metrics?.expected_total) }}</strong>
                <small>General + SEP calculable</small>
              </article>
              <article class="attendance-kpi">
                <span>Asistencia promedio</span>
                <strong>{{ attendanceReconciliation.metrics?.attendance_rate == null ? '-' : `${Number(attendanceReconciliation.metrics.attendance_rate).toLocaleString('es-CL')}%` }}</strong>
                <small>{{ Number(attendanceReconciliation.metrics?.attendance_equivalent || 0).toLocaleString('es-CL') }} alumnas equivalentes de {{ Number(attendanceReconciliation.metrics?.enrollment_average || 0).toLocaleString('es-CL') }}</small>
              </article>
              <article class="attendance-kpi">
                <span>Liquidado comparable por MINEDUC</span>
                <strong>{{ money(attendanceReconciliation.metrics?.liquidated_total) }}</strong>
                <small>Documento total {{ money(attendanceReconciliation.metrics?.liquidated_gross_total) }} · {{ attendanceReconciliation.metrics?.excluded_liquidated_total ? `${money(attendanceReconciliation.metrics.excluded_liquidated_total)} en otras glosas` : 'sin otras glosas' }}</small>
              </article>
              <article class="attendance-kpi" :class="attendanceLiquidationReading(attendanceReconciliation.metrics?.liquidated_total, attendanceReconciliation.metrics?.full_attendance_total).className">
                <span>Impacto frente al 100%</span>
                <strong>{{ attendanceLiquidationReading(attendanceReconciliation.metrics?.liquidated_total, attendanceReconciliation.metrics?.full_attendance_total).label }}</strong>
                <small>{{ attendanceLiquidationReading(attendanceReconciliation.metrics?.liquidated_total, attendanceReconciliation.metrics?.full_attendance_total).detail }}</small>
              </article>
              <article class="attendance-kpi income">
                <span>Registrado en Ingresos</span>
                <strong>{{ money(attendanceReconciliation.metrics?.registered_comparable_income_total) }}</strong>
                <small>{{ attendanceReconciliation.metrics?.income_records_count || 0 }} registros · {{ attendanceReconciliation.metrics?.registered_income_unallocated_total ? `${money(attendanceReconciliation.metrics.registered_income_unallocated_total)} SEP sin desglose` : 'clasificación trazable' }}</small>
              </article>
              <article class="attendance-kpi" :class="attendanceIncomeReading(attendanceReconciliation.metrics?.registered_comparable_income_total, attendanceReconciliation.metrics?.liquidated_total).className">
                <span>Brecha del módulo de Ingresos</span>
                <strong>{{ attendanceIncomeReading(attendanceReconciliation.metrics?.registered_comparable_income_total, attendanceReconciliation.metrics?.liquidated_total).label }}</strong>
                <small>{{ attendanceIncomeReading(attendanceReconciliation.metrics?.registered_comparable_income_total, attendanceReconciliation.metrics?.liquidated_total).detail }}</small>
              </article>
            </div>

            <div class="attendance-contrast-band">
              <div class="attendance-contrast-copy">
                <span>LECTURA EJECUTIVA</span>
                <strong>{{ attendanceIncomeReading(attendanceReconciliation.metrics?.registered_comparable_income_total, attendanceReconciliation.metrics?.liquidated_total).label }}</strong>
                <small>El contraste usa sólo familias calculables desde asistencia y conserva aparte las demás glosas.</small>
              </div>
              <div class="attendance-contrast-bars">
                <div><span>Pudo llegar</span><i class="baseline" :style="{ width: attendanceComparisonWidth(attendanceReconciliation.metrics?.full_attendance_total) }"></i><strong>{{ money(attendanceReconciliation.metrics?.full_attendance_total) }}</strong></div>
                <div><span>Modelo local</span><i class="expected" :style="{ width: attendanceComparisonWidth(attendanceReconciliation.metrics?.expected_total) }"></i><strong>{{ money(attendanceReconciliation.metrics?.expected_total) }}</strong></div>
                <div><span>Liquidado comparable</span><i class="actual" :style="{ width: attendanceComparisonWidth(attendanceReconciliation.metrics?.liquidated_total) }"></i><strong>{{ money(attendanceReconciliation.metrics?.liquidated_total) }}</strong></div>
                <div><span>Contabilizado</span><i class="income" :style="{ width: attendanceComparisonWidth(attendanceReconciliation.metrics?.registered_comparable_income_total) }"></i><strong>{{ money(attendanceReconciliation.metrics?.registered_comparable_income_total) }}</strong></div>
              </div>
            </div>

            <div class="attendance-meaning-strip">
              <div><i class="bx bx-target-lock"></i><span><strong>Pudo llegar</strong>Escenario teórico con 100% de asistencia.</span></div>
              <div><i class="bx bx-trending-down"></i><span><strong>Pérdida verificable</strong>Diferencia entre el máximo al 100% y la parte comparable liquidada.</span></div>
              <div><i class="bx bx-calculator"></i><span><strong>Modelo local</strong>Cálculo reglamentario con la asistencia y clasificación disponibles; es un control, no la liquidación.</span></div>
              <div><i class="bx bx-receipt"></i><span><strong>Liquidado MINEDUC</strong>No equivale a ingreso bancario; las otras glosas se muestran aparte.</span></div>
            </div>

            <div class="attendance-detail-stack">
              <section class="attendance-detail-panel subsidy-breakdown-panel">
                <div class="attendance-detail-heading"><div><span>POR SUBVENCIÓN</span><h3>Máximo a 100%, pérdida y liquidación oficial comparable</h3></div><small>{{ attendanceReconciliation.by_subsidy?.length || 0 }} subvenciones</small></div>
                <div class="table-responsive">
                  <table class="table accounting-table attendance-table attendance-table-wide align-middle mb-0">
                    <thead><tr><th>Subvención</th><th class="text-end">Pudo llegar<br><small>100% asistencia</small></th><th class="text-end">Pérdida frente<br><small>al 100%</small></th><th class="text-end">Modelo local<br><small>según asistencia</small></th><th class="text-end">Liquidado<br><small>parte comparable</small></th><th>Lectura</th></tr></thead>
                    <tbody>
                      <tr v-for="item in attendanceReconciliation.by_subsidy || []" :key="item.key">
                        <td><strong>{{ item.label }}</strong><small v-if="item.note">{{ item.note }}</small><small v-else-if="!item.calculable_from_attendance">No calculable sólo con asistencia</small></td>
                        <td class="text-end">{{ item.full_attendance_amount == null ? '-' : money(item.full_attendance_amount) }}</td>
                        <td class="text-end"><span v-if="item.attendance_loss_amount != null" class="attendance-loss-chip">{{ money(item.attendance_loss_amount) }}</span><span v-else>-</span></td>
                        <td class="text-end fw-semibold">{{ item.expected_amount == null ? '-' : money(item.expected_amount) }}</td>
                        <td class="text-end attendance-liquidated-cell"><strong>{{ money(item.liquidated_amount) }}</strong><small v-if="Number(item.excluded_liquidated_amount || 0)">Documento total {{ money(item.liquidated_total_amount) }}</small></td>
                        <td><span class="attendance-payment-reading" :class="attendanceLiquidationReading(item.liquidated_amount, item.full_attendance_amount, 'subsidy', item.baseline_requires_review).className"><strong>{{ attendanceLiquidationReading(item.liquidated_amount, item.full_attendance_amount, 'subsidy', item.baseline_requires_review).label }}</strong><small>{{ attendanceLiquidationReading(item.liquidated_amount, item.full_attendance_amount, 'subsidy', item.baseline_requires_review).detail }}</small></span></td>
                      </tr>
                    </tbody>
                    <tfoot><tr><th>Total comparable</th><th class="text-end">{{ money(attendanceReconciliation.metrics?.full_attendance_total) }}</th><th class="text-end">{{ money(attendanceReconciliation.metrics?.attendance_loss_total) }}</th><th class="text-end">{{ money(attendanceReconciliation.metrics?.expected_total) }}</th><th class="text-end">{{ money(attendanceReconciliation.metrics?.liquidated_total) }}</th><th>{{ attendanceLiquidationReading(attendanceReconciliation.metrics?.liquidated_total, attendanceReconciliation.metrics?.full_attendance_total).label }}</th></tr></tfoot>
                  </table>
                </div>
              </section>

              <section class="attendance-detail-panel">
                <div class="attendance-detail-heading"><div><span>POR NIVEL</span><h3>Detalle del impacto de la asistencia en cada nivel</h3></div><small>{{ attendanceReconciliation.by_level?.length || 0 }} niveles</small></div>
                <div class="table-responsive">
                  <table class="table accounting-table attendance-table attendance-table-wide align-middle mb-0">
                    <thead><tr><th>Nivel</th><th class="text-end">Asistencia</th><th class="text-end">Pudo llegar<br><small>100% asistencia</small></th><th class="text-end">Pérdida frente<br><small>al 100%</small></th><th class="text-end">Modelo local<br><small>según asistencia</small></th><th class="text-end">Liquidado asignado<br><small>parte comparable</small></th><th>Lectura</th></tr></thead>
                    <tbody>
                      <tr v-for="item in attendanceReconciliation.by_level || []" :key="item.key">
                        <td><strong>{{ item.label }}</strong><small>{{ Number(item.enrollment_average || 0).toLocaleString('es-CL') }} matrícula prom.</small></td>
                        <td class="text-end">{{ item.attendance_rate == null ? '-' : `${Number(item.attendance_rate).toLocaleString('es-CL')}%` }}</td>
                        <td class="text-end">{{ money(item.full_attendance_amount) }}</td>
                        <td class="text-end"><span class="attendance-loss-chip">{{ money(item.attendance_loss_amount) }}</span></td>
                        <td class="text-end fw-semibold">{{ money(item.expected_amount) }}</td>
                        <td class="text-end attendance-liquidated-cell"><strong>{{ money(item.liquidated_amount) }}</strong><small v-if="Number(item.excluded_liquidated_amount || 0)">Total asignado {{ money(item.liquidated_total_amount) }}</small></td>
                        <td><span class="attendance-payment-reading" :class="attendanceLiquidationReading(item.liquidated_amount, item.full_attendance_amount, 'level', Boolean(item.baseline_review_families?.length)).className"><strong>{{ attendanceLiquidationReading(item.liquidated_amount, item.full_attendance_amount, 'level', Boolean(item.baseline_review_families?.length)).label }}</strong><small>{{ attendanceLiquidationReading(item.liquidated_amount, item.full_attendance_amount, 'level', Boolean(item.baseline_review_families?.length)).detail }}</small></span></td>
                      </tr>
                    </tbody>
                    <tfoot><tr><th>Total comparable</th><th class="text-end">{{ attendanceReconciliation.metrics?.attendance_rate == null ? '-' : `${Number(attendanceReconciliation.metrics.attendance_rate).toLocaleString('es-CL')}%` }}</th><th class="text-end">{{ money(attendanceReconciliation.metrics?.full_attendance_total) }}</th><th class="text-end">{{ money(attendanceReconciliation.metrics?.attendance_loss_total) }}</th><th class="text-end">{{ money(attendanceReconciliation.metrics?.expected_total) }}</th><th class="text-end">{{ money(attendanceReconciliation.metrics?.liquidated_total) }}</th><th>{{ attendanceLiquidationReading(attendanceReconciliation.metrics?.liquidated_total, attendanceReconciliation.metrics?.full_attendance_total).label }}</th></tr></tfoot>
                  </table>
                </div>
              </section>
            </div>

            <section class="attendance-income-panel">
              <div class="attendance-income-heading">
                <div>
                  <span>MÓDULO DE INGRESOS</span>
                  <h3>Trazabilidad de lo contabilizado en {{ subsidyPeriodLabel(subsidyPeriod) }}</h3>
                  <small>Sólo se consideran registros no anulados cuyo tipo comienza con “subvencion_”.</small>
                </div>
                <router-link v-if="canAccessNavigation('contabilidad.ingresos.gestionar')" to="/contabilidad/ingresos" class="btn btn-sm btn-outline-primary"><i class="bx bx-link-external"></i> Revisar ingresos</router-link>
              </div>
              <div v-if="attendanceReconciliation.income_records?.length" class="table-responsive">
                <table class="table accounting-table attendance-income-table align-middle mb-0">
                  <thead><tr><th>Código</th><th>Fecha</th><th>Clasificación</th><th>Fuente</th><th>Estado</th><th>Conciliación</th><th class="text-end">Monto</th></tr></thead>
                  <tbody>
                    <tr v-for="income in attendanceReconciliation.income_records" :key="income.id">
                      <td><strong>{{ income.code }}</strong><small>{{ income.document_reference || 'Sin referencia documental' }}</small></td>
                      <td>{{ shortDate(income.received_at) }}</td>
                      <td><strong>{{ income.family_label }}</strong><small>{{ income.income_type }}</small></td>
                      <td>{{ income.funding_source?.name || 'Sin fuente asignada' }}</td>
                      <td><span class="status-pill">{{ income.status }}</span></td>
                      <td><span class="income-match" :class="income.matched_to_settlement ? 'matched' : 'pending'"><i class="bx" :class="income.matched_to_settlement ? 'bx-link' : 'bx-unlink'"></i>{{ income.matched_to_settlement ? 'Vinculado' : 'Sin vínculo' }}</span></td>
                      <td class="text-end fw-semibold">{{ money(income.amount) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div v-else class="attendance-income-empty">
                <i class="bx bx-receipt"></i>
                <div><strong>No hay ingresos de subvención registrados para este mes</strong><span>La estimación y la liquidación quedan visibles; registra o corrige el ingreso cuando se reciba la transferencia.</span></div>
              </div>
            </section>
          </template>

          <div v-else class="attendance-empty-state">
            <span class="attendance-empty-icon"><i class="bx bx-spreadsheet"></i></span>
            <div>
              <strong>Faltan cargas para calcular este mes</strong>
              <p>Se requieren: {{ attendancePeriodList(attendanceReconciliation.window?.required_periods) }}.</p>
              <small>La liquidación existente se conserva visible y el cálculo aparecerá automáticamente al cargar la asistencia mensual.</small>
            </div>
          </div>

          <div v-if="attendanceReconciliation.warnings?.length" class="attendance-warning-panel">
            <div class="attendance-warning-heading"><i class="bx bx-info-circle"></i><strong>Alcance y controles antes del cierre</strong></div>
            <ul><li v-for="warning in attendanceReconciliation.warnings" :key="warning">{{ warning }}</li></ul>
          </div>
          <footer class="attendance-methodology">
            <div><i class="bx bx-shield-quarter"></i><span>{{ attendanceReconciliation.disclaimer }}</span></div>
            <div class="attendance-sources"><span>Fuentes oficiales:</span><a v-for="source in attendanceReconciliation.sources || []" :key="source.url" :href="source.url" target="_blank" rel="noopener noreferrer">{{ source.label }}</a></div>
          </footer>
        </section>

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

        <section id="resumen-anual-subvenciones" ref="subsidyAnnualSection" class="content-card subsidy-annual-card">
          <header class="subsidy-annual-hero">
            <div>
              <span class="subsidy-annual-eyebrow"><i class="bx bx-calendar"></i> VISIÓN ANUAL {{ subsidyYear }}</span>
              <h2>Panorama consolidado de subvenciones</h2>
              <p>{{ subsidyAnnualLoadedRange }} · Selecciona cualquier mes para abrir su detalle.</p>
            </div>
            <div class="subsidy-annual-health" :class="subsidyAnnualHealth.className">
              <span>{{ subsidyAnnualHealth.label }}</span>
              <small>{{ subsidyAnnualHealth.detail }}</small>
            </div>
          </header>

          <div class="subsidy-annual-kpis">
            <article>
              <span>Total liquidado</span>
              <strong>{{ money(subsidyAnnualOverview.metrics?.net_liquidated) }}</strong>
              <small>{{ subsidyAnnualOverview.metrics?.settlement_count || 0 }} liquidaciones en {{ subsidyAnnualOverview.metrics?.months_with_data || 0 }} meses</small>
            </article>
            <article>
              <span>Ingreso contabilizado</span>
              <strong>{{ money(subsidyAnnualOverview.metrics?.income_total) }}</strong>
              <small>{{ subsidyAnnualIncomeCoverage.toLocaleString('es-CL', { maximumFractionDigits: 1 }) }}% del líquido anual</small>
            </article>
            <article :class="{ warning: Number(subsidyAnnualOverview.metrics?.income_gap || 0) !== 0 }">
              <span>Brecha frente a Ingresos</span>
              <strong>{{ money(subsidyAnnualOverview.metrics?.income_gap) }}</strong>
              <small>Control contable; no confirma una deuda bancaria</small>
            </article>
            <article>
              <span>Promedio por mes cargado</span>
              <strong>{{ money(subsidyAnnualOverview.metrics?.average_active_month) }}</strong>
              <small v-if="subsidyAnnualOverview.peak_month">Máximo: {{ subsidyPeriodLabel(subsidyAnnualOverview.peak_month.period) }}</small>
              <small v-else>Sin meses disponibles</small>
            </article>
          </div>

          <div class="subsidy-annual-analysis">
            <section class="subsidy-annual-trend">
              <div class="subsidy-annual-section-heading">
                <div><span>TENDENCIA MENSUAL</span><h3>Liquidado frente a contabilizado</h3></div>
                <div class="subsidy-annual-legend"><span><i></i> Liquidado</span><span class="income"><i></i> Contabilizado</span></div>
              </div>
              <div class="subsidy-annual-chart" role="img" :aria-label="`Comparación mensual de subvenciones liquidadas y contabilizadas durante ${subsidyYear}`">
                <button
                  v-for="item in subsidyDashboard.annual || []"
                  :key="`chart-${item.period}`"
                  type="button"
                  class="subsidy-annual-column"
                  :class="{ active: item.period === subsidyPeriod, empty: !Number(item.settlement_count) }"
                  :title="`${subsidyPeriodLabel(item.period)}: ${money(item.net_liquidated)} liquidado`"
                  @click="selectSubsidyAnnualMonth(item)"
                >
                  <span class="subsidy-annual-bars">
                    <i class="liquidated" :style="{ height: subsidyAnnualColumnHeight(item, 'net_liquidated') }"></i>
                    <i class="income" :style="{ height: subsidyAnnualColumnHeight(item, 'income_total') }"></i>
                  </span>
                  <strong>{{ item.label }}</strong>
                  <small v-if="item.settlement_count">{{ item.settlement_count }}</small>
                </button>
              </div>
              <footer class="subsidy-annual-trend-footer">
                <div><span>Período cubierto</span><strong>{{ subsidyAnnualLoadedRange }}</strong></div>
                <div><span>Mes de mayor liquidación</span><strong>{{ subsidyAnnualOverview.peak_month ? `${subsidyPeriodLabel(subsidyAnnualOverview.peak_month.period)} · ${money(subsidyAnnualOverview.peak_month.amount)}` : '-' }}</strong></div>
              </footer>
            </section>

            <section class="subsidy-annual-composition">
              <div class="subsidy-annual-section-heading">
                <div><span>COMPOSICIÓN</span><h3>Participación por subvención</h3></div>
                <small>{{ subsidyAnnualOverview.by_family?.length || 0 }} tipos</small>
              </div>
              <div v-if="subsidyAnnualOverview.by_family?.length" class="subsidy-annual-family-list">
                <article v-for="family in subsidyAnnualOverview.by_family" :key="family.key">
                  <div><strong>{{ family.label }}</strong><span>{{ family.percentage.toLocaleString('es-CL') }}%</span></div>
                  <div class="subsidy-annual-family-track"><i :style="{ width: `${family.percentage}%` }"></i></div>
                  <footer><span>{{ family.months_count }} mes(es) · {{ family.settlement_count }} liquidación(es)</span><strong>{{ money(family.net_amount) }}</strong></footer>
                </article>
              </div>
              <div v-else class="mini-empty">Sin subvenciones para el año seleccionado.</div>
            </section>
          </div>

          <div class="subsidy-annual-controls">
            <div><i class="bx bx-calendar-check"></i><span>Meses con datos</span><strong>{{ subsidyAnnualOverview.metrics?.months_with_data || 0 }} de 12</strong></div>
            <div :class="{ danger: Number(subsidyAnnualOverview.metrics?.observed_count || 0) > 0 }"><i class="bx bx-error-circle"></i><span>Por revisar</span><strong>{{ subsidyAnnualOverview.metrics?.observed_count || 0 }}</strong></div>
            <div :class="{ warning: Number(subsidyAnnualOverview.metrics?.pending_transfer_count || 0) > 0 }"><i class="bx bx-transfer"></i><span>Sin transferencia informada</span><strong>{{ subsidyAnnualOverview.metrics?.pending_transfer_count || 0 }}</strong></div>
            <div><i class="bx bx-puzzle"></i><span>PIE informativo</span><strong>{{ money(subsidyAnnualOverview.metrics?.pie_total) }}</strong></div>
          </div>

          <div class="subsidy-annual-master">
            <div class="subsidy-pie-section-heading">
              <div><span>TABLA MAESTRA</span><h3>Seguimiento anual por mes</h3></div>
              <small>Haz clic en una fila para revisar sus liquidaciones</small>
            </div>
            <div class="table-responsive">
              <table class="table accounting-table align-middle mb-0">
                <thead>
                  <tr>
                    <th>Mes</th>
                    <th>Estado</th>
                    <th class="text-end">Liquidaciones</th>
                    <th class="text-end">Líquido</th>
                    <th class="text-end">Transferido</th>
                    <th class="text-end">Contabilizado</th>
                    <th class="text-end">Brecha ingresos</th>
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
                    <td><span class="subsidy-annual-status" :class="subsidyAnnualMonthStatus(item.status).className">{{ subsidyAnnualMonthStatus(item.status).label }}</span></td>
                    <td class="text-end">{{ item.settlement_count }}</td>
                    <td class="text-end">{{ money(item.net_liquidated) }}</td>
                    <td class="text-end">{{ money(item.transferred_total) }}</td>
                    <td class="text-end">{{ money(item.income_total) }}</td>
                    <td class="text-end" :class="{ 'text-danger fw-semibold': Number(item.income_gap || 0) !== 0 }">{{ money(item.income_gap) }}</td>
                    <td class="text-end">{{ money(item.pie_total) }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <th>Total {{ subsidyYear }}</th>
                    <th>-</th>
                    <th class="text-end">{{ subsidyAnnualTotals.settlement_count }}</th>
                    <th class="text-end">{{ money(subsidyAnnualTotals.net_liquidated) }}</th>
                    <th class="text-end">{{ money(subsidyAnnualTotals.transferred_total) }}</th>
                    <th class="text-end">{{ money(subsidyAnnualTotals.income_total) }}</th>
                    <th class="text-end">{{ money(subsidyAnnualTotals.net_liquidated - subsidyAnnualTotals.income_total) }}</th>
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
            <template v-if="maintenanceLine(selectedSubsidy)">
              <div class="subsidy-pie-detail-title">
                <div>
                  <span>DETALLE POR CURSO</span>
                  <h6>Subvención de Mantenimiento</h6>
                </div>
                <strong>{{ maintenanceRows(selectedSubsidy).length }} registro(s)</strong>
              </div>
              <div class="table-responsive subsidy-pie-detail-table">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Nivel / curso</th>
                      <th>Glosa</th>
                      <th>Asistencia año anterior</th>
                      <th>Matrícula promedio</th>
                      <th>Factor</th>
                      <th class="text-end">Monto</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="allocation in maintenanceRows(selectedSubsidy)" :key="allocation.id">
                      <td><strong>{{ pieCourse(allocation) }}</strong><small class="d-block text-muted">Cód. {{ allocation.teaching_code }}</small></td>
                      <td>{{ allocation.education_label || '-' }}</td>
                      <td>{{ pieNumber(maintenanceData(allocation).attendance_previous_year) }}</td>
                      <td>{{ pieNumber(maintenanceData(allocation).average_enrollment) }}</td>
                      <td>{{ pieNumber(maintenanceData(allocation).calculation_factor) }}</td>
                      <td class="text-end"><strong>{{ money(allocation.amount) }}</strong></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>
            <template v-if="staffBonusLines(selectedSubsidy).length">
              <div class="subsidy-pie-detail-title">
                <div>
                  <span>DETALLE CONFIDENCIAL DE NÓMINA</span>
                  <h6>Bonos y aguinaldos al personal</h6>
                </div>
                <strong>{{ staffBonusRows(selectedSubsidy).length }} registro(s)</strong>
              </div>
              <div class="table-responsive subsidy-pie-detail-table">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Concepto</th>
                      <th>Trabajador</th>
                      <th>Tipo / horas</th>
                      <th>Tramo</th>
                      <th>Período origen</th>
                      <th class="text-end">Monto</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="entry in staffBonusRows(selectedSubsidy)" :key="entry.allocation.id">
                      <td><strong>{{ entry.conceptName }}</strong></td>
                      <td><strong>{{ staffBonusData(entry.allocation).worker_name || '-' }}</strong><small class="d-block text-muted">{{ staffBonusData(entry.allocation).worker_rut || '-' }}</small></td>
                      <td>{{ staffBonusData(entry.allocation).worker_type || '-' }}<small class="d-block text-muted">{{ pieNumber(staffBonusData(entry.allocation).hours) }} hora(s)</small></td>
                      <td>{{ staffBonusData(entry.allocation).tranche ? `Tramo ${staffBonusData(entry.allocation).tranche}` : '-' }}</td>
                      <td>{{ staffBonusData(entry.allocation).source_period || '-' }}</td>
                      <td class="text-end"><strong>{{ money(entry.allocation.amount) }}</strong></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>
            <template v-if="reliquidationLine(selectedSubsidy)">
              <div class="subsidy-pie-detail-title">
                <div>
                  <span>AJUSTE RETROACTIVO</span>
                  <h6>Reliquidación marzo a mayo</h6>
                </div>
                <strong>{{ money(Number(reliquidationLine(selectedSubsidy).amount || 0) * Number(reliquidationLine(selectedSubsidy).sign || 1)) }}</strong>
              </div>
              <div class="table-responsive subsidy-pie-detail-table">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Mes reliquidado</th>
                      <th>Período origen</th>
                      <th class="text-end">Monto a pagar</th>
                      <th class="text-end">Pagado en el mes</th>
                      <th class="text-end">Diferencia</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="allocation in reliquidationRows(selectedSubsidy)" :key="allocation.id">
                      <td><strong>{{ reliquidationData(allocation).month || '-' }} {{ reliquidationData(allocation).year || '' }}</strong></td>
                      <td>{{ reliquidationData(allocation).source_period || '-' }}</td>
                      <td class="text-end">{{ money(reliquidationData(allocation).expected_amount) }}</td>
                      <td class="text-end">{{ money(reliquidationData(allocation).paid_amount) }}</td>
                      <td class="text-end" :class="{ 'text-danger fw-semibold': Number(reliquidationData(allocation).difference) < 0 }"><strong>{{ money(reliquidationData(allocation).difference) }}</strong></td>
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
.subsidy-annual-jump{display:inline-flex;align-items:center;gap:.35rem;border-color:#dbe3ee;color:#405189;font-weight:700}
.subsidy-annual-card{overflow:hidden;scroll-margin-top:82px;background:linear-gradient(180deg,#fff 0%,#fbfcff 100%)}
.subsidy-annual-hero{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1.35rem 1.4rem;background:radial-gradient(circle at 84% -25%,rgba(120,143,199,.23),transparent 42%),linear-gradient(135deg,#354777,#4b6098);color:#fff}
.subsidy-annual-eyebrow{display:flex;align-items:center;gap:.38rem;color:#d9e2ff;font-size:.62rem;font-weight:800;letter-spacing:.12em}
.subsidy-annual-hero h2{margin:.38rem 0 .25rem;color:#fff;font-size:1.25rem}
.subsidy-annual-hero p{margin:0;color:#d8e0f1;font-size:.68rem}
.subsidy-annual-health{display:flex;flex-direction:column;align-items:flex-end;min-width:190px;padding:.6rem .72rem;border:1px solid rgba(255,255,255,.2);border-radius:10px;background:rgba(255,255,255,.1);text-align:right;backdrop-filter:blur(5px)}
.subsidy-annual-health span{font-size:.62rem;font-weight:800;letter-spacing:.05em}
.subsidy-annual-health small{margin-top:.18rem;color:#e1e7f5;font-size:.55rem}.subsidy-annual-health.success{background:rgba(31,122,89,.28)}.subsidy-annual-health.warning{background:rgba(154,104,20,.3)}.subsidy-annual-health.danger{background:rgba(178,54,70,.3)}
.subsidy-annual-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));border-bottom:1px solid #e4e9f0}
.subsidy-annual-kpis article{min-width:0;padding:1rem 1.15rem;border-right:1px solid #e6eaf0;background:rgba(255,255,255,.8)}.subsidy-annual-kpis article:last-child{border-right:0}.subsidy-annual-kpis article.warning{background:#fffaf3}
.subsidy-annual-kpis span,.subsidy-annual-kpis strong,.subsidy-annual-kpis small{display:block}.subsidy-annual-kpis span{color:#7b8494;font-size:.6rem;font-weight:700}.subsidy-annual-kpis strong{margin:.32rem 0;color:#29364b;font-size:1.02rem}.subsidy-annual-kpis small{color:#98a2b3;font-size:.55rem;line-height:1.35}
.subsidy-annual-analysis{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(290px,.8fr);gap:.85rem;padding:.9rem}
.subsidy-annual-trend,.subsidy-annual-composition{overflow:hidden;border:1px solid #e1e6ee;border-radius:12px;background:#fff;box-shadow:0 7px 20px rgba(41,54,75,.045)}
.subsidy-annual-section-heading{display:flex;align-items:center;justify-content:space-between;gap:.8rem;padding:.85rem 1rem;border-bottom:1px solid #edf0f4}.subsidy-annual-section-heading span{color:#405189;font-size:.55rem;font-weight:800;letter-spacing:.09em}.subsidy-annual-section-heading h3{margin:.18rem 0 0;color:#344054;font-size:.82rem}.subsidy-annual-section-heading>small{color:#8b95a5;font-size:.56rem}
.subsidy-annual-legend{display:flex;align-items:center;gap:.75rem}.subsidy-annual-legend span{display:flex;align-items:center;gap:.3rem;color:#7b8494;font-size:.54rem;font-weight:600;letter-spacing:0}.subsidy-annual-legend i{width:8px;height:8px;border-radius:3px;background:#405189}.subsidy-annual-legend .income i{background:#2f9e78}
.subsidy-annual-chart{display:grid;grid-template-columns:repeat(12,minmax(30px,1fr));align-items:end;height:245px;padding:1.15rem .8rem .7rem;background:repeating-linear-gradient(to bottom,#fff 0,#fff 54px,#eef1f5 55px)}
.subsidy-annual-column{display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;min-width:0;padding:0 .16rem;border:0;border-radius:7px;background:transparent;color:#667085;transition:.18s ease}.subsidy-annual-column:hover,.subsidy-annual-column.active{background:#f0f3fa}.subsidy-annual-column.active{box-shadow:inset 0 -3px #405189}.subsidy-annual-column.empty{opacity:.48}
.subsidy-annual-bars{display:flex;align-items:flex-end;justify-content:center;gap:3px;width:100%;height:180px}.subsidy-annual-bars i{display:block;width:10px;min-height:0;border-radius:5px 5px 2px 2px;transition:height .25s ease}.subsidy-annual-bars .liquidated{background:linear-gradient(180deg,#7d90c3,#405189)}.subsidy-annual-bars .income{background:linear-gradient(180deg,#62c29f,#2f9e78)}
.subsidy-annual-column>strong{margin-top:.4rem;font-size:.55rem;text-transform:uppercase}.subsidy-annual-column>small{display:grid;place-items:center;min-width:18px;height:18px;margin-top:.18rem;border-radius:9px;background:#edf1f7;color:#536075;font-size:.48rem;font-weight:800}
.subsidy-annual-trend-footer{display:grid;grid-template-columns:1fr 1fr;border-top:1px solid #edf0f4;background:#fafbfd}.subsidy-annual-trend-footer>div{padding:.7rem 1rem;border-right:1px solid #e7ebf0}.subsidy-annual-trend-footer>div:last-child{border-right:0}.subsidy-annual-trend-footer span,.subsidy-annual-trend-footer strong{display:block}.subsidy-annual-trend-footer span{color:#8a94a4;font-size:.53rem}.subsidy-annual-trend-footer strong{margin-top:.22rem;color:#465366;font-size:.61rem}
.subsidy-annual-family-list{max-height:310px;overflow-y:auto;padding:.3rem 1rem .65rem}.subsidy-annual-family-list article{padding:.62rem 0;border-bottom:1px solid #edf0f4}.subsidy-annual-family-list article:last-child{border-bottom:0}.subsidy-annual-family-list article>div:first-child,.subsidy-annual-family-list footer{display:flex;align-items:center;justify-content:space-between;gap:.65rem}.subsidy-annual-family-list strong{color:#465366;font-size:.6rem}.subsidy-annual-family-list article>div:first-child span{color:#405189;font-size:.54rem;font-weight:800}.subsidy-annual-family-track{height:6px;margin:.42rem 0;border-radius:4px;background:#edf0f4;overflow:hidden}.subsidy-annual-family-track i{display:block;height:100%;border-radius:4px;background:linear-gradient(90deg,#405189,#8294c4)}.subsidy-annual-family-list footer span{color:#98a2b3;font-size:.5rem}.subsidy-annual-family-list footer strong{font-size:.56rem}
.subsidy-annual-controls{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));margin:0 .9rem .9rem;border:1px solid #e2e7ee;border-radius:10px;background:#fff;overflow:hidden}.subsidy-annual-controls>div{display:grid;grid-template-columns:30px 1fr;grid-template-rows:auto auto;align-items:center;padding:.72rem .8rem;border-right:1px solid #e7ebf0}.subsidy-annual-controls>div:last-child{border-right:0}.subsidy-annual-controls i{grid-row:1/3;display:grid;place-items:center;width:27px;height:27px;border-radius:8px;background:#edf1f8;color:#405189;font-size:1rem}.subsidy-annual-controls span{color:#8a94a4;font-size:.51rem}.subsidy-annual-controls strong{color:#465366;font-size:.65rem}.subsidy-annual-controls .warning i{background:#fff3dc;color:#9a6814}.subsidy-annual-controls .danger i{background:#fdecef;color:#bd4252}
.subsidy-annual-status{display:inline-flex;padding:.25rem .48rem;border-radius:12px;background:#eef1f5;color:#667085;font-size:.52rem;font-weight:800}.subsidy-annual-status.success{background:#e8f6ef;color:#1f7a59}.subsidy-annual-status.warning{background:#fff3dc;color:#9a6814}.subsidy-annual-status.danger{background:#fdecef;color:#bd4252}
.subsidy-annual-master{border-top:1px solid #dfe5ed;background:#fff}.subsidy-annual-master tbody tr{cursor:pointer}.subsidy-annual-master tbody tr:hover{background:#f8faff}
.attendance-reconciliation-card{overflow:hidden;border-color:#d9e2ee;background:linear-gradient(145deg,#fff 0%,#fbfcff 100%);box-shadow:0 10px 30px rgba(43,57,85,.07)}
.attendance-reconciliation-header{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1.15rem 1.25rem;border-bottom:1px solid #e4e9f1;background:linear-gradient(120deg,#f8faff 0%,#eef3fb 100%)}
.attendance-reconciliation-title{display:flex;align-items:center;gap:.8rem}.attendance-reconciliation-icon{display:grid;place-items:center;flex:0 0 46px;width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#405189,#667bb3);box-shadow:0 8px 18px rgba(64,81,137,.22);color:#fff;font-size:1.45rem}.attendance-reconciliation-title h2{margin:.16rem 0;color:#263043;font-size:1rem}.attendance-reconciliation-title p{margin:0;color:#738096;font-size:.63rem}.attendance-status{display:inline-flex;align-items:center;gap:.35rem;padding:.38rem .6rem;border-radius:16px;background:#eef1f5;color:#667085;font-size:.58rem;font-weight:800;letter-spacing:.035em;white-space:nowrap}.attendance-status.success{background:#e7f6ef;color:#1f7a59}.attendance-status.warning{background:#fff3dc;color:#95640f}.attendance-status.danger{background:#fdecef;color:#b83b4c}.attendance-status i{font-size:.9rem}
.attendance-assumptions{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.7rem;padding:.9rem 1.25rem;border-bottom:1px solid #e8ecf2;background:#fff}.attendance-assumptions label{margin:0}.attendance-assumptions label>span{display:block;margin-bottom:.28rem;color:#69768a;font-size:.58rem;font-weight:700;letter-spacing:.025em;text-transform:uppercase}.attendance-assumptions .form-select{min-height:36px;border-color:#d8e0ea;background-color:#fafbfd;color:#3d4a60;font-size:.67rem}
.attendance-window-strip{display:grid;grid-template-columns:1.45fr .8fr 1fr;border-bottom:1px solid #e7ebf1;background:#f8fafc}.attendance-window-strip>div{min-height:68px;padding:.78rem 1.25rem;border-right:1px solid #e5eaf1}.attendance-window-strip>div:last-child{border-right:0}.attendance-window-strip span,.attendance-window-strip strong,.attendance-window-strip small{display:block}.attendance-window-strip span{color:#8792a3;font-size:.55rem;text-transform:uppercase}.attendance-window-strip strong{margin-top:.18rem;color:#344054;font-size:.68rem}.attendance-window-strip small{margin-top:.12rem;color:#98a2b3;font-size:.51rem}.attendance-window-strip>div:first-child{display:grid;grid-template-columns:24px 1fr;align-items:center}.attendance-window-strip>div:first-child i{grid-row:1/3;color:#405189;font-size:1.15rem}.attendance-window-strip>div:first-child strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.attendance-coverage-track{height:4px;margin-top:.35rem;border-radius:3px;background:#e2e7ef;overflow:hidden}.attendance-coverage-track i{display:block;height:100%;border-radius:3px;background:#2f9e78}
.attendance-rule-note{display:flex;align-items:flex-start;gap:.65rem;padding:.72rem 1.25rem;border-bottom:1px solid #d8ebe4;background:#f3fbf8;color:#376b5b}.attendance-rule-note>i{margin-top:.05rem;color:#238262;font-size:1.1rem}.attendance-rule-note strong,.attendance-rule-note span{display:block}.attendance-rule-note strong{font-size:.63rem}.attendance-rule-note span{margin-top:.16rem;color:#628275;font-size:.55rem;line-height:1.45}
.attendance-kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem;padding:1rem 1.25rem}.attendance-kpi{min-height:93px;padding:.85rem;border:1px solid #e0e6ee;border-radius:10px;background:#fff}.attendance-kpi span,.attendance-kpi strong,.attendance-kpi small{display:block}.attendance-kpi span{color:#758095;font-size:.59rem}.attendance-kpi strong{margin:.27rem 0;color:#263043;font-size:1.05rem}.attendance-kpi small{color:#98a2b3;font-size:.52rem;line-height:1.35}.attendance-kpi.primary{border-color:transparent;background:linear-gradient(135deg,#344575,#5368a1);box-shadow:0 8px 20px rgba(64,81,137,.18)}.attendance-kpi.primary span,.attendance-kpi.primary strong,.attendance-kpi.primary small{color:#fff}.attendance-kpi.negative{border-color:#efc8ce;background:#fff9fa}.attendance-kpi.negative strong{color:#b83b4c}.attendance-kpi.positive{border-color:#bee3d3;background:#f9fdfb}.attendance-kpi.positive strong{color:#237b5b}.attendance-kpi.pending{border-color:#e6d6ad;background:#fffdf7}.attendance-kpi.pending strong{color:#8a681c;font-size:.87rem}
.attendance-kpi.baseline{border-color:#d7deeb;background:linear-gradient(145deg,#f8faff,#eef2f8)}.attendance-kpi.baseline strong{color:#405189}.attendance-kpi.loss{border-color:#f0d1c2;background:#fff9f4}.attendance-kpi.loss strong{color:#b75c32}.attendance-kpi.income{border-color:#c7e5d9;background:#f6fcf9}.attendance-kpi.income strong{color:#237b5b}
.attendance-contrast-band{display:grid;grid-template-columns:.8fr 1.5fr;align-items:center;gap:1rem;margin:0 1.25rem 1rem;padding:.8rem 1rem;border:1px solid #dfe5ed;border-radius:10px;background:#f8fafc}.attendance-contrast-copy span,.attendance-contrast-copy strong,.attendance-contrast-copy small{display:block}.attendance-contrast-copy span{color:#8a95a5;font-size:.52rem;font-weight:800;letter-spacing:.05em}.attendance-contrast-copy strong{margin:.22rem 0;color:#344054;font-size:.72rem}.attendance-contrast-copy small{color:#8a95a5;font-size:.52rem}.attendance-contrast-bars{display:flex;flex-direction:column;gap:.43rem}.attendance-contrast-bars>div{display:grid;grid-template-columns:60px minmax(90px,1fr) 110px;align-items:center;gap:.55rem}.attendance-contrast-bars span{color:#667085;font-size:.55rem}.attendance-contrast-bars>div:before{grid-column:2;grid-row:1;height:8px;border-radius:5px;background:#e5eaf1;content:""}.attendance-contrast-bars i{grid-column:2;grid-row:1;z-index:1;display:block;height:8px;border-radius:5px;background:#667bb3}.attendance-contrast-bars i.actual{background:#2f9e78}.attendance-contrast-bars strong{text-align:right;color:#344054;font-size:.62rem}
.attendance-contrast-bars i.baseline{background:#aab4c6}.attendance-contrast-bars i.income{background:#d99b36}.attendance-loss-chip{display:inline-flex;padding:.18rem .34rem;border-radius:11px;background:#fff0e6;color:#a8512b;font-size:.55rem;font-weight:750;white-space:nowrap}
.attendance-meaning-strip{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));border-top:1px solid #e3e8ef;border-bottom:1px solid #e3e8ef;background:linear-gradient(110deg,#fbfcfe,#f5f8fc)}.attendance-meaning-strip>div{display:flex;align-items:flex-start;gap:.48rem;padding:.75rem .9rem;border-right:1px solid #e4e9f0}.attendance-meaning-strip>div:last-child{border-right:0}.attendance-meaning-strip i{display:grid;place-items:center;flex:0 0 27px;width:27px;height:27px;border-radius:8px;background:#e9eef8;color:#405189;font-size:.9rem}.attendance-meaning-strip span,.attendance-meaning-strip strong{display:block}.attendance-meaning-strip span{color:#7b8495;font-size:.52rem;line-height:1.35}.attendance-meaning-strip strong{margin-bottom:.08rem;color:#344054;font-size:.58rem}.attendance-detail-stack{display:flex;flex-direction:column}.attendance-detail-panel{min-width:0;overflow:hidden}.attendance-detail-panel+ .attendance-detail-panel{border-top:1px solid #dce3ec}.attendance-detail-heading{display:flex;align-items:center;justify-content:space-between;padding:.85rem 1rem;border-bottom:1px solid #e8ecf2;background:#fafbfd}.attendance-detail-heading span{color:#8b95a5;font-size:.52rem;font-weight:800;letter-spacing:.055em}.attendance-detail-heading h3{margin:.14rem 0 0;color:#344054;font-size:.79rem}.attendance-detail-heading small{color:#8b95a5;font-size:.54rem}.attendance-table-wide{min-width:1040px}.attendance-table thead th{font-size:.51rem;vertical-align:bottom}.attendance-table thead th small{display:block;margin-top:.12rem;color:#8c96a7;font-size:.47rem;font-weight:600;letter-spacing:0;text-transform:none}.attendance-table tbody td{font-size:.61rem}.attendance-table td:first-child strong,.attendance-table td:first-child small{display:block;white-space:nowrap}.attendance-table td:first-child small{margin-top:.12rem;color:#98a2b3;font-size:.5rem}.attendance-liquidated-cell strong,.attendance-liquidated-cell small{display:block;white-space:nowrap}.attendance-liquidated-cell small{margin-top:.12rem;color:#8a94a4;font-size:.48rem}.attendance-table tfoot th{border-top:2px solid #d9e0ea;background:#f3f6fa;color:#344054;font-size:.56rem;white-space:nowrap}.attendance-payment-reading{display:flex;flex-direction:column;align-items:flex-start;min-width:155px;padding:.32rem .48rem;border-radius:8px;background:#eef1f5;color:#667085}.attendance-payment-reading strong,.attendance-payment-reading small{display:block}.attendance-payment-reading strong{font-size:.57rem;line-height:1.25}.attendance-payment-reading small{margin-top:.1rem;color:inherit;font-size:.48rem;line-height:1.25;opacity:.84}.attendance-payment-reading.positive,.attendance-payment-reading.matched{background:#e7f6ef;color:#237b5b}.attendance-payment-reading.negative{background:#fdecef;color:#b83b4c}.attendance-payment-reading.pending{background:#fff4dc;color:#8a681c}
.attendance-income-panel{border-top:1px solid #dfe6ee;background:#fff}.attendance-income-heading{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.85rem 1.25rem;border-bottom:1px solid #e7ebf1;background:linear-gradient(110deg,#fbfcfe,#f4faf7)}.attendance-income-heading span,.attendance-income-heading h3,.attendance-income-heading small{display:block}.attendance-income-heading span{color:#2a8767;font-size:.52rem;font-weight:800;letter-spacing:.06em}.attendance-income-heading h3{margin:.15rem 0;color:#344054;font-size:.76rem}.attendance-income-heading small{color:#8a95a5;font-size:.53rem}.attendance-income-heading .btn{display:inline-flex;align-items:center;gap:.3rem;white-space:nowrap}.attendance-income-table thead th{font-size:.52rem}.attendance-income-table tbody td{font-size:.6rem}.attendance-income-table td strong,.attendance-income-table td small{display:block}.attendance-income-table td small{margin-top:.12rem;color:#98a2b3;font-size:.49rem}.income-match{display:inline-flex;align-items:center;gap:.25rem;padding:.2rem .38rem;border-radius:12px;font-size:.52rem;font-weight:700;white-space:nowrap}.income-match.matched{background:#e7f6ef;color:#237b5b}.income-match.pending{background:#fff3dc;color:#95640f}.attendance-income-empty{display:flex;align-items:center;gap:.7rem;padding:1.1rem 1.25rem;color:#667085}.attendance-income-empty>i{display:grid;place-items:center;width:38px;height:38px;border-radius:10px;background:#fff3dc;color:#95640f;font-size:1.25rem}.attendance-income-empty strong,.attendance-income-empty span{display:block}.attendance-income-empty strong{color:#344054;font-size:.67rem}.attendance-income-empty span{margin-top:.18rem;font-size:.54rem}
.attendance-empty-state{display:flex;align-items:center;gap:1rem;padding:1.6rem 1.25rem;background:radial-gradient(circle at 8% 50%,#eef3fb,transparent 28%),#fff}.attendance-empty-icon{display:grid;place-items:center;flex:0 0 58px;width:58px;height:58px;border:1px solid #d7e0ec;border-radius:15px;background:#fff;color:#405189;font-size:1.8rem;box-shadow:0 8px 20px rgba(43,57,85,.08)}.attendance-empty-state strong{display:block;color:#344054;font-size:.78rem}.attendance-empty-state p{margin:.25rem 0;color:#667085;font-size:.63rem}.attendance-empty-state small{color:#98a2b3;font-size:.55rem}
.attendance-warning-panel{padding:.8rem 1.25rem;border-top:1px solid #eadbbd;background:#fffaf0}.attendance-warning-heading{display:flex;align-items:center;gap:.4rem;color:#8a641e;font-size:.62rem}.attendance-warning-heading i{font-size:1rem}.attendance-warning-panel ul{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.3rem 1.5rem;margin:.55rem 0 0;padding-left:1.1rem;color:#7a6846;font-size:.55rem;line-height:1.45}.attendance-methodology{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:.75rem 1.25rem;border-top:1px solid #e5eaf1;background:#f7f9fc;color:#758095;font-size:.53rem}.attendance-methodology>div:first-child{display:flex;align-items:flex-start;gap:.35rem;max-width:58%}.attendance-methodology i{color:#405189;font-size:.9rem}.attendance-sources{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:.28rem .55rem}.attendance-sources a{color:#405189;text-decoration:underline;text-underline-offset:2px}
.be-command-bar{position:relative;display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.15rem 2.9rem;background:linear-gradient(120deg,#fff 0%,#f7f9fd 62%,#eef3fb 100%);overflow:hidden}.be-year-control>.toolbar-kicker{display:block;margin-bottom:.35rem}.be-year-control>div,.be-command-actions{display:flex;align-items:center;gap:.55rem}.be-year-select{width:145px;min-height:38px;border-color:#d6deea;font-size:.75rem;font-weight:700;color:#344054}.be-current-badge,.be-status-pill{display:inline-flex;align-items:center;gap:.3rem;padding:.24rem .48rem;border-radius:14px;background:#e8f6ef;color:#1f7a59;font-size:.59rem;font-weight:750}.be-current-badge.empty{background:#eef1f5;color:#667085}.be-version-strip{position:absolute;right:0;bottom:0;left:0;display:flex;align-items:center;gap:1.1rem;padding:.55rem 1.15rem;border-top:1px solid #e3e8f0;background:rgba(247,249,252,.94);color:#667085;font-size:.61rem;white-space:nowrap;overflow-x:auto}.be-version-strip span{display:inline-flex;align-items:center;gap:.32rem}.be-version-strip i{color:#405189;font-size:.9rem}.be-empty-state{display:flex;flex-direction:column;align-items:center;min-height:470px;padding:3.5rem 1.5rem;text-align:center;background:radial-gradient(circle at 50% 15%,#f0f4fb,transparent 42%),#fff}.be-empty-state h2{max-width:620px;margin:.55rem 0 .65rem;color:#1d2939;font-size:1.7rem}.be-empty-state p{max-width:650px;margin:0;color:#667085;font-size:.78rem;line-height:1.65}.be-empty-illustration{position:relative;width:105px;height:105px;margin-bottom:1.5rem}.be-sheet{position:absolute;display:grid;place-items:center;width:72px;height:88px;border:1px solid #cfd8e6;border-radius:10px;background:#fff;box-shadow:0 12px 28px rgba(51,65,85,.12)}.be-sheet.sheet-back{top:0;left:7px;transform:rotate(-9deg);background:#edf2fa}.be-sheet.sheet-front{right:5px;bottom:0;transform:rotate(5deg);color:#405189;font-size:2.1rem}.be-empty-features{display:flex;gap:1.2rem;margin:1.5rem 0;color:#536075;font-size:.67rem}.be-empty-features span{display:flex;align-items:center;gap:.3rem}.be-empty-features i{display:grid;place-items:center;width:18px;height:18px;border-radius:50%;background:#e8f6ef;color:#25845f}.be-empty-state .btn{display:inline-flex;align-items:center;gap:.4rem}.be-kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem}.be-kpi-card{display:flex;align-items:center;gap:.75rem;min-height:112px;padding:1rem;border:1px solid #e0e6ee;border-radius:12px;background:#fff;box-shadow:0 5px 17px rgba(39,50,72,.045)}.be-kpi-card.primary{border:0;background:linear-gradient(135deg,#344575,#4d6199);box-shadow:0 10px 24px rgba(64,81,137,.2)}.be-kpi-card.primary span,.be-kpi-card.primary strong,.be-kpi-card.primary small{color:#fff}.be-kpi-card.negative{border-color:#efcbd0;background:#fffafb}.be-kpi-icon{display:grid;place-items:center;flex:0 0 43px;width:43px;height:43px;border-radius:11px;background:rgba(255,255,255,.13);color:#fff;font-size:1.35rem}.be-kpi-icon.coral{background:#fdecef;color:#c2414f}.be-kpi-icon.green{background:#e8f6ef;color:#25845f}.be-kpi-icon.ink{background:#edf1f8;color:#405189}.be-kpi-card span,.be-kpi-card strong,.be-kpi-card small{display:block}.be-kpi-card span{color:#758095;font-size:.64rem}.be-kpi-card strong{margin:.24rem 0;color:#263043;font-size:1.08rem}.be-kpi-card small{color:#98a2b3;font-size:.56rem}.be-analysis-grid{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(280px,.72fr);gap:.8rem}.be-card-heading small{color:#8b95a5;font-size:.62rem}.be-chart-legend{display:flex;gap:.75rem}.be-chart-legend span{display:flex;align-items:center;gap:.3rem;color:#667085;font-size:.59rem;letter-spacing:0}.be-chart-legend i{width:8px;height:8px;border-radius:2px;background:#405189}.be-chart-legend .income i{background:#2f9e78}.be-month-chart{position:relative;display:grid;grid-template-columns:repeat(12,1fr);align-items:end;height:245px;padding:28px 18px 24px 70px;background:repeating-linear-gradient(to bottom,transparent 0,transparent 72px,#edf0f4 73px);border-bottom:1px solid #edf0f4}.be-chart-axis{position:absolute;top:26px;bottom:25px;left:12px;display:flex;flex-direction:column;justify-content:space-between;color:#98a2b3;font-size:.52rem}.be-month-column{display:flex;flex-direction:column;align-items:center;height:100%;min-width:0}.be-bars{display:flex;align-items:flex-end;justify-content:center;gap:3px;width:100%;height:100%}.be-bars span{width:10px;min-height:0;border-radius:4px 4px 1px 1px;background:linear-gradient(180deg,#6176ad,#405189);transition:.25s ease}.be-bars .income{background:linear-gradient(180deg,#54b995,#2f9e78)}.be-month-column>strong{margin-top:.4rem;color:#7b8494;font-size:.54rem;text-transform:uppercase}.be-chart-summary{display:grid;grid-template-columns:repeat(3,1fr);padding:.75rem 1rem}.be-chart-summary>div{padding:0 .8rem;border-right:1px solid #e6eaf0}.be-chart-summary>div:last-child{border-right:0}.be-chart-summary span,.be-chart-summary strong{display:block}.be-chart-summary span{color:#8a94a4;font-size:.56rem}.be-chart-summary strong{margin-top:.25rem;color:#344054;font-size:.78rem}.be-health-card{overflow:hidden}.be-health-score{display:grid;grid-template-columns:116px 1fr;align-items:center;gap:.65rem;padding:1.2rem 1rem;border-bottom:1px solid #edf0f4}.be-ring{display:grid;place-items:center;width:105px;height:105px;border-radius:50%;background:conic-gradient(#405189 var(--progress),#e7ebf1 0)}.be-ring:before{position:absolute;width:78px;height:78px;border-radius:50%;background:#fff;content:""}.be-ring>div{position:relative;text-align:center}.be-ring strong,.be-ring span{display:block}.be-ring strong{color:#2c3950;font-size:1.15rem}.be-ring span{color:#8a94a4;font-size:.52rem}.be-health-score p{margin:.55rem 0 0;color:#7b8494;font-size:.62rem;line-height:1.45}.be-status-pill.warning{background:#fff3dc;color:#9a6814}.be-status-pill.danger{background:#fdecef;color:#bd4252}.be-status-pill.success{background:#e8f6ef;color:#1f7a59}.be-alert-list>div{display:grid;grid-template-columns:28px 1fr auto;align-items:center;gap:.5rem;padding:.75rem 1rem;border-bottom:1px solid #edf0f4;color:#667085;font-size:.63rem}.be-alert-list>div:last-child{border-bottom:0}.be-alert-list i{display:grid;place-items:center;width:27px;height:27px;border-radius:7px;background:#eef2f7;color:#667085;font-size:1rem}.be-alert-list strong{font-size:.78rem}.be-alert-list .danger i{background:#fdecef;color:#bd4252}.be-alert-list .warning i{background:#fff3dc;color:#9a6814}.be-subsidy-section{overflow:hidden}.be-subsidy-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem;padding:1rem}.be-subsidy-card{padding:1rem;border:1px solid #e0e6ee;border-radius:10px;background:linear-gradient(145deg,#fff,#f9fafd)}.be-subsidy-card header,.be-subsidy-card footer{display:flex;align-items:center;justify-content:space-between;gap:.5rem}.be-source-mark{display:grid;place-items:center;min-width:38px;height:24px;padding:0 .35rem;border-radius:6px;background:#e9edf7;color:#405189;font-size:.58rem;font-weight:800;letter-spacing:.05em}.be-subsidy-card h3{min-height:32px;margin:.8rem 0 .25rem;color:#465366;font-size:.68rem}.be-subsidy-card>strong{display:block;color:#263043;font-size:1.05rem}.be-subsidy-card>span{color:#8a94a4;font-size:.56rem}.be-progress{height:6px;margin:.75rem 0 .55rem;border-radius:4px;background:#e9edf2;overflow:hidden}.be-progress i{display:block;height:100%;border-radius:4px;background:linear-gradient(90deg,#405189,#788bc1)}.be-subsidy-card footer{color:#7b8494;font-size:.55rem}.be-subsidy-card footer strong{color:#465366;font-size:.58rem}.be-subsidy-card.source-mantencion .be-source-mark,.be-table-source.source-mantencion{background:#fff1db;color:#9b6812}.be-subsidy-card.source-sep .be-source-mark,.be-table-source.source-sep{background:#e9f7f1;color:#237b5b}.be-subsidy-card.source-pie .be-source-mark,.be-table-source.source-pie{background:#f3eefa;color:#7751a4}.be-detail-grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(275px,.7fr);gap:.8rem}.be-category-list{padding:.15rem 1rem .6rem}.be-category-row{padding:.72rem 0;border-bottom:1px solid #edf0f4}.be-category-row:last-child{border-bottom:0}.be-category-meta,.be-category-foot{display:flex;align-items:center;justify-content:space-between;gap:1rem}.be-category-meta>div strong,.be-category-meta>div span{display:block}.be-category-meta>div strong{color:#465366;font-size:.64rem}.be-category-meta>div span{margin-top:.15rem;color:#98a2b3;font-size:.53rem}.be-category-meta>strong{color:#344054;font-size:.67rem}.be-category-track{position:relative;height:7px;margin:.45rem 0;border-radius:5px;background:#edf0f4;overflow:hidden}.be-category-track i{position:absolute;top:0;left:0;height:100%;border-radius:5px}.be-category-track .budget{background:#dfe4ed}.be-category-track .executed{height:3px;top:2px;background:#405189}.be-category-foot{color:#8a94a4;font-size:.52rem}.be-insights{padding:.35rem 1rem}.be-insights article{display:flex;align-items:center;gap:.7rem;padding:.75rem 0;border-bottom:1px solid #edf0f4}.be-insights article:last-child{border-bottom:0}.be-insights article>i{display:grid;place-items:center;flex:0 0 34px;width:34px;height:34px;border-radius:9px;background:#edf1f8;color:#405189;font-size:1.1rem}.be-insights strong,.be-insights span{display:block}.be-insights strong{color:#465366;font-size:.63rem}.be-insights span{margin-top:.18rem;color:#8a94a4;font-size:.56rem}.be-account-card{overflow:hidden}.be-account-toolbar{align-items:flex-end}.be-account-filters{display:flex;align-items:center;gap:.45rem}.be-segmented{display:flex;padding:3px;border:1px solid #dce2e9;border-radius:7px;background:#f4f6f9}.be-segmented button{padding:.38rem .58rem;border:0;border-radius:5px;background:transparent;color:#778295;font-size:.62rem}.be-segmented button.active{background:#fff;color:#405189;font-weight:700;box-shadow:0 1px 4px rgba(44,57,80,.12)}.be-filter-select{width:170px;min-height:35px;border-color:#dce2e9;font-size:.65rem}.be-account-table-wrap{max-height:620px}.be-account-table thead{position:sticky;top:0;z-index:2}.be-account-table td:first-child{min-width:210px}.be-account-table td:nth-child(3){min-width:170px}.be-account-table td strong{color:#465366}.be-table-source{display:inline-grid;place-items:center;min-width:42px;padding:.22rem .36rem;border-radius:5px;background:#e9edf7;color:#405189;font-size:.52rem;font-weight:800}.be-table-progress{display:grid;grid-template-columns:70px 38px;align-items:center;gap:.4rem}.be-table-progress:before{grid-column:1;grid-row:1;width:70px;height:5px;border-radius:4px;background:#e8ebf0;content:""}.be-table-progress i{grid-column:1;grid-row:1;display:block;height:5px;max-width:70px;border-radius:4px;background:#405189}.be-table-progress span{grid-column:2;grid-row:1;color:#667085;font-size:.56rem}.be-overrun-row{background:#fff9fa}.be-overrun-row .be-table-progress i{background:#c2414f}
.be-visual-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:.8rem}.be-cumulative-card{grid-column:span 8;overflow:hidden}.be-mix-card{grid-column:span 4;overflow:hidden}.be-ranking-card{grid-column:span 7;overflow:hidden}.be-heatmap-card{grid-column:span 5;overflow:hidden}.be-line-legend .balance i{border-radius:50%;background:#c2414f}.be-chart-legend .budget i{background:#d7dde8}.be-cumulative-chart{padding:.4rem .8rem .1rem}.be-cumulative-chart svg{display:block;width:100%;height:auto;min-height:250px}.be-svg-grid{stroke:#e8ecf2;stroke-width:1}.be-svg-zero{stroke:#aeb8c8;stroke-width:1.2;stroke-dasharray:4 4}.be-svg-axis-label,.be-svg-month{fill:#8994a6;font-size:9px}.be-svg-line{fill:none;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}.be-svg-line.income{stroke:#2f9e78}.be-svg-line.expense{stroke:#405189}.be-svg-line.balance{stroke:#c2414f;stroke-width:2;stroke-dasharray:5 4}.be-svg-dot{stroke:#fff;stroke-width:1.5}.be-svg-dot.income{fill:#2f9e78}.be-svg-dot.expense{fill:#405189}.be-svg-dot.balance{fill:#c2414f}.be-mix-body{display:grid;grid-template-columns:145px 1fr;align-items:center;gap:.35rem;padding:.8rem 1rem .55rem}.be-donut-wrap svg{display:block;width:100%;height:auto}.be-donut-base,.be-donut-segment{fill:none;stroke-width:20;transform:rotate(-90deg);transform-origin:90px 90px}.be-donut-base{stroke:#edf0f4}.be-donut-segment{stroke-linecap:butt}.be-donut-value{fill:#28364c;font-size:25px;font-weight:800}.be-donut-label{fill:#8994a6;font-size:9px}.be-mix-legend>div{display:grid;grid-template-columns:8px 1fr auto;align-items:center;gap:.45rem;padding:.47rem 0;border-bottom:1px solid #edf0f4}.be-mix-legend>div:last-child{border-bottom:0}.be-mix-legend i{width:8px;height:8px;border-radius:50%}.be-mix-legend strong,.be-mix-legend small{display:block}.be-mix-legend strong{color:#465366;font-size:.59rem}.be-mix-legend small{margin-top:.08rem;color:#8b95a5;font-size:.51rem}.be-mix-legend b{color:#344054;font-size:.58rem}.be-chart-note{padding:.65rem 1rem;border-top:1px solid #edf0f4;background:#fafbfd;color:#7b8494;font-size:.56rem}.be-chart-note strong{color:#344054}.be-ranking-list{padding:.25rem 1rem .65rem}.be-ranking-row{display:grid;grid-template-columns:24px minmax(150px,1.2fr) minmax(120px,1fr) 95px;align-items:center;gap:.65rem;padding:.57rem 0;border-bottom:1px solid #edf0f4}.be-ranking-row:last-child{border-bottom:0}.be-rank-number{display:grid;place-items:center;width:22px;height:22px;border-radius:6px;background:#eef1f7;color:#405189;font-size:.56rem;font-weight:800}.be-rank-copy strong,.be-rank-copy small{display:block}.be-rank-copy strong{overflow:hidden;color:#465366;font-size:.59rem;text-overflow:ellipsis;white-space:nowrap}.be-rank-copy small{margin-top:.12rem;overflow:hidden;color:#98a2b3;font-size:.49rem;text-overflow:ellipsis;white-space:nowrap}.be-rank-bars{position:relative;height:10px;border-radius:6px;background:#f0f2f6;overflow:hidden}.be-rank-bars i{position:absolute;top:0;left:0;height:100%;border-radius:6px}.be-rank-bars .budget{background:#d7dde8}.be-rank-bars .executed{top:3px;height:4px;background:#405189}.be-ranking-row>strong{text-align:right;color:#344054;font-size:.59rem}.be-heatmap{display:grid;grid-template-columns:repeat(3,1fr);gap:5px;padding:.8rem 1rem}.be-heatmap article{min-height:72px;padding:.62rem;border:1px solid rgba(64,81,137,.08);border-radius:8px}.be-heatmap span,.be-heatmap strong,.be-heatmap small{display:block}.be-heatmap span{color:#3e4b60;font-size:.52rem;font-weight:800;text-transform:uppercase}.be-heatmap strong{margin:.28rem 0;color:#263043;font-size:.64rem}.be-heatmap small{color:#58667b;font-size:.5rem}.be-heatmap small.positive{color:#1b6e51}.be-heatmap small.negative{color:#a73545}.be-heat-legend{display:flex;flex-wrap:wrap;gap:.8rem;padding:.62rem 1rem;border-top:1px solid #edf0f4;color:#7b8494;font-size:.51rem}.be-heat-legend span{display:flex;align-items:center;gap:.3rem}.be-heat-legend i{width:18px;height:6px;border-radius:4px;background:rgba(64,81,137,.12)}.be-heat-legend i.high{background:rgba(64,81,137,.76)}.be-heat-legend b{color:#c2414f}
@media(max-width:1100px){.metric-grid{grid-template-columns:repeat(2,1fr)}.dashboard-grid{grid-template-columns:1fr 1fr}.alert-panel{grid-column:1/-1}.accounting-nav{padding:.45rem}.nav-group{min-width:auto}.nav-group-title{display:none}.subsidy-pie-consolidations{grid-template-columns:1fr}.subsidy-pie-consolidated+ .subsidy-pie-consolidated{border-top:1px solid #e7ebf0;border-left:0}.subsidy-cycle-grid{grid-template-columns:1fr}.attendance-assumptions,.attendance-kpi-grid{grid-template-columns:repeat(2,1fr)}.attendance-meaning-strip{grid-template-columns:repeat(2,1fr)}.attendance-meaning-strip>div:nth-child(2){border-right:0}.attendance-meaning-strip>div:nth-child(-n+2){border-bottom:1px solid #e4e9f0}.attendance-methodology{flex-direction:column}.attendance-methodology>div:first-child{max-width:none}.attendance-sources{justify-content:flex-start}}
@media(max-width:720px){.accounting-hero{align-items:flex-start;padding:1rem}.accounting-hero,.records-toolbar{flex-direction:column}.hero-actions,.toolbar-actions{width:100%}.hero-actions .btn{flex:1}.accounting-hero h1{font-size:1.25rem}.metric-grid,.dashboard-grid{grid-template-columns:1fr}.alert-panel{grid-column:auto}.records-toolbar{align-items:stretch}.toolbar-actions{flex-wrap:wrap}.search-box{flex:1;min-width:200px}.accounting-form-grid{grid-template-columns:1fr}.accounting-form-grid .full{grid-column:auto}.accounting-nav{display:block}.nav-group{padding:.3rem;border-right:0;border-bottom:1px solid #edf0f4}.nav-group-title{display:none}.nav-group-links{flex-wrap:nowrap;overflow-x:auto}.scope-notice{align-items:flex-start}.subsidy-command-card{align-items:stretch}.subsidy-command-card,.subsidy-period-filters{flex-direction:column}.subsidy-period-filters{align-items:stretch}.subsidy-period-select,.subsidy-month-select,.subsidy-compare-select{width:100%}.subsidy-period-note{position:static;margin:1rem -1.1rem -2.9rem}.subsidy-pie-overview{grid-template-columns:1fr 1fr}.subsidy-pie-overview>div:nth-child(2){border-right:0}.subsidy-pie-overview>div{border-bottom:1px solid #edf0f4}.subsidy-annual-grid{grid-template-columns:1fr}.subsidy-annual-row{grid-template-columns:40px minmax(70px,1fr) 100px}.subsidy-annual-row:nth-child(n){border-right:0}.subsidy-annual-row small{display:none}.subsidy-cycle-card>div{align-items:flex-start;flex-direction:column}.attendance-reconciliation-header{align-items:flex-start;flex-direction:column}.attendance-assumptions,.attendance-kpi-grid,.attendance-window-strip,.attendance-meaning-strip{grid-template-columns:1fr}.attendance-meaning-strip>div{border-right:0;border-bottom:1px solid #e4e9f0}.attendance-meaning-strip>div:last-child{border-bottom:0}.attendance-window-strip>div{border-right:0;border-bottom:1px solid #e5eaf1}.attendance-window-strip>div:last-child{border-bottom:0}.attendance-contrast-band{grid-template-columns:1fr}.attendance-contrast-bars>div{grid-template-columns:54px minmax(75px,1fr) 95px}.attendance-warning-panel ul{grid-template-columns:1fr}.attendance-empty-state{align-items:flex-start;flex-direction:column}.attendance-reconciliation-title{align-items:flex-start}.attendance-table,.attendance-income-table{min-width:760px}.attendance-income-heading{align-items:flex-start;flex-direction:column}.attendance-income-heading .btn{width:100%;justify-content:center}}
@media(max-width:1100px){.subsidy-annual-kpis{grid-template-columns:repeat(2,1fr)}.subsidy-annual-kpis article:nth-child(2){border-right:0}.subsidy-annual-kpis article:nth-child(-n+2){border-bottom:1px solid #e6eaf0}.subsidy-annual-analysis{grid-template-columns:1fr}.subsidy-annual-controls{grid-template-columns:repeat(2,1fr)}.subsidy-annual-controls>div:nth-child(2){border-right:0}.subsidy-annual-controls>div:nth-child(-n+2){border-bottom:1px solid #e7ebf0}}
@media(max-width:720px){.subsidy-annual-hero{align-items:stretch;flex-direction:column;padding:1rem}.subsidy-annual-health{align-items:flex-start;min-width:0;text-align:left}.subsidy-annual-kpis{grid-template-columns:1fr}.subsidy-annual-kpis article:nth-child(n){border-right:0;border-bottom:1px solid #e6eaf0}.subsidy-annual-kpis article:last-child{border-bottom:0}.subsidy-annual-analysis{padding:.65rem}.subsidy-annual-trend{overflow-x:auto}.subsidy-annual-trend>.subsidy-annual-section-heading,.subsidy-annual-chart,.subsidy-annual-trend-footer{min-width:680px}.subsidy-annual-controls{grid-template-columns:1fr;margin:0 .65rem .65rem}.subsidy-annual-controls>div:nth-child(n){border-right:0;border-bottom:1px solid #e7ebf0}.subsidy-annual-controls>div:last-child{border-bottom:0}.subsidy-annual-master .accounting-table{min-width:940px}.subsidy-annual-jump{width:100%;justify-content:center}}
@media(max-width:1100px){.be-kpi-grid,.be-subsidy-grid{grid-template-columns:repeat(2,1fr)}.be-analysis-grid,.be-detail-grid{grid-template-columns:1fr}.be-cumulative-card,.be-mix-card,.be-ranking-card,.be-heatmap-card{grid-column:span 12}.be-account-toolbar{align-items:stretch}.be-account-filters{flex-wrap:wrap}.be-account-filters .search-box{flex:1}}
@media(max-width:720px){.be-command-bar{align-items:stretch;flex-direction:column;padding-bottom:1rem}.be-year-control>div,.be-command-actions{align-items:stretch;flex-direction:column}.be-year-select{width:100%}.be-version-strip{position:static;align-items:flex-start;flex-direction:column;margin:1rem -1.15rem -1rem;white-space:normal}.be-empty-state{min-height:420px;padding:2.5rem 1rem}.be-empty-state h2{font-size:1.35rem}.be-empty-features{align-items:flex-start;flex-direction:column;gap:.65rem}.be-kpi-grid,.be-subsidy-grid{grid-template-columns:1fr}.be-monthly-card{overflow-x:auto}.be-month-chart{min-width:720px}.be-chart-summary{min-width:720px}.be-cumulative-card{overflow-x:auto}.be-cumulative-chart{min-width:700px}.be-mix-body{grid-template-columns:120px 1fr}.be-ranking-card{overflow-x:auto}.be-ranking-list{min-width:650px}.be-heatmap{grid-template-columns:repeat(2,1fr)}.be-subsidy-grid{padding:.75rem}.be-account-filters{align-items:stretch;flex-direction:column}.be-filter-select{width:100%;min-width:0}.be-account-filters .search-box{width:100%;min-width:0}.be-health-score{grid-template-columns:105px 1fr}}
</style>
