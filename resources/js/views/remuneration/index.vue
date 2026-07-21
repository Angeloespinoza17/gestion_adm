<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import PageHeader from "../../components/page-header.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import BookAnalyticsPanel from "../../components/remuneration/book-analytics-panel.vue";
import { formatRemunerationError, money, shortDate } from "../../components/remuneration/module-utils";
import { getPdfMake } from "../../utils/pdfmake";

const routeMap = {
  "/remuneraciones": "dashboard",
  "/remuneraciones/trabajadores": "profiles",
  "/remuneraciones/contratos": "contract-settings",
  "/remuneraciones/periodos": "periods",
  "/remuneraciones/parametros": "parameters",
  "/remuneraciones/conceptos": "concepts",
  "/remuneraciones/movimientos": "movements",
  "/remuneraciones/liquidaciones": "payrolls",
  "/remuneraciones/importaciones": "imports",
  "/remuneraciones/libro-importado": "import-rows",
  "/remuneraciones/estadisticas-libro": "book-analytics",
  "/remuneraciones/pagos": "payments",
  "/remuneraciones/centralizacion": "accounting-exports",
  "/remuneraciones/reportes": "reports",
  "/remuneraciones/licencias-medicas": "medical-leaves",
  "/remuneraciones/cumpleanos": "birthdays",
  "/remuneraciones/permisos": "permissions",
  "/remuneraciones/departamentos": "departments",
  "/remuneraciones/funciones": "functions",
  "/remuneraciones/gestion-funcionarios": "staff-management",
  "/remuneraciones/control-documental": "document-controls",
  "/remuneraciones/induccion": "onboarding",
  "/remuneraciones/clima-laboral": "climate-surveys",
  "/remuneraciones/planes-clima": "climate-action-plans",
  "/remuneraciones/dotacion-carga": "workload",
  "/remuneraciones/banco-cv": "cv-bank",
  "/remuneraciones/reemplazos": "replacement-pool",
  "/remuneraciones/perfiles-cargo": "job-profiles",
  "/remuneraciones/certificados": "labor-certificates",
  "/remuneraciones/auditoria": "audit-logs",
};

const statusBadge = {
  abierto: "success",
  reabierto: "warning",
  cerrado: "dark",
  calculada: "info",
  imported: "success",
  importing: "info",
  replaced: "secondary",
  preview: "warning",
  observada: "warning",
  aprobada: "primary",
  pagada: "success",
  anulada: "danger",
  aprobado: "primary",
  ejecutado: "success",
  borrador: "secondary",
  generado: "primary",
  reversado: "danger",
  ingresada: "info",
  enviada: "primary",
  liquidada: "success",
  vigente: "success",
  por_vencer: "warning",
  vencido: "danger",
  pendiente: "warning",
  observado: "warning",
  archivado: "secondary",
  en_proceso: "primary",
  completo: "success",
  abierta: "success",
  cerrada: "dark",
  reportada: "primary",
  plan_accion: "info",
  atrasado: "danger",
  cancelado: "secondary",
  planificada: "info",
  reemplazo: "warning",
  postulante: "secondary",
  preseleccionado: "primary",
  entrevistado: "info",
  descartado: "danger",
  contratado: "success",
  disponible: "success",
  ocupado: "warning",
  no_disponible: "secondary",
  en_revision: "warning",
  obsoleto: "secondary",
  solicitado: "warning",
  emitido: "primary",
  entregado: "success",
  bajo: "success",
  medio: "warning",
  alto: "danger",
  critico: "dark",
};

const statusLabels = {
  abierto: "Abierto",
  reabierto: "Reabierto",
  cerrado: "Cerrado",
  calculada: "Calculada",
  imported: "Importado",
  importing: "Importando",
  replaced: "Reemplazado",
  preview: "Vista previa",
  observada: "Observada",
  aprobada: "Aprobada",
  pagada: "Pagada",
  anulada: "Anulada",
  aprobado: "Aprobado",
  ejecutado: "Ejecutado",
  borrador: "Borrador",
  generado: "Generado",
  reversado: "Reversado",
  ingresada: "Ingresada",
  enviada: "Enviada",
  liquidada: "Liquidada",
  vigente: "Vigente",
  por_vencer: "Por vencer",
  vencido: "Vencido",
  pendiente: "Pendiente",
  observado: "Observado",
  archivado: "Archivado",
  en_proceso: "En proceso",
  completo: "Completo",
  abierta: "Abierta",
  cerrada: "Cerrada",
  reportada: "Reportada",
  plan_accion: "Plan de acción",
  atrasado: "Atrasado",
  cancelado: "Cancelado",
  planificada: "Planificada",
  reemplazo: "Reemplazo",
  postulante: "Postulante",
  preseleccionado: "Preseleccionado",
  entrevistado: "Entrevistado",
  descartado: "Descartado",
  contratado: "Contratado",
  disponible: "Disponible",
  ocupado: "Ocupado",
  no_disponible: "No disponible",
  en_revision: "En revisión",
  obsoleto: "Obsoleto",
  solicitado: "Solicitado",
  emitido: "Emitido",
  entregado: "Entregado",
  bajo: "Bajo",
  medio: "Medio",
  alto: "Alto",
  critico: "Crítico",
};

const fields = {
  periods: [
    { key: "year", label: "Año", type: "number", required: true },
    { key: "month", label: "Mes", type: "number", required: true },
    { key: "name", label: "Nombre", type: "text", required: true },
    { key: "status", label: "Estado", type: "select", statusKey: "periods" },
    { key: "period_start", label: "Inicio", type: "date", required: true },
    { key: "period_end", label: "Término", type: "date", required: true },
    { key: "notes", label: "Notas", type: "textarea" },
  ],
  parameters: [
    { key: "code", label: "Código", type: "text", required: true },
    { key: "name", label: "Nombre", type: "text", required: true },
    { key: "category", label: "Categoría", type: "text", required: true },
    { key: "value", label: "Valor", type: "number", required: true },
    { key: "unit", label: "Unidad", type: "select", typeKey: "units", required: true },
    { key: "effective_from", label: "Vigente desde", type: "date", required: true },
    { key: "effective_until", label: "Vigente hasta", type: "date" },
    { key: "source_reference", label: "Fuente", type: "text" },
    { key: "is_active", label: "Activo", type: "checkbox" },
    { key: "notes", label: "Notas", type: "textarea" },
  ],
  profiles: [
    { key: "staff_id", label: "Funcionario", type: "select", optionsKey: "staff", required: true },
    { key: "payment_method", label: "Forma pago", type: "select", typeKey: "payment_methods" },
    { key: "bank_name", label: "Banco", type: "text" },
    { key: "bank_account_type", label: "Tipo cuenta", type: "text" },
    { key: "bank_account_number", label: "Nº cuenta", type: "text" },
    { key: "afp_name", label: "AFP", type: "text" },
    { key: "afp_rate", label: "Tasa AFP", type: "number" },
    { key: "health_institution_type", label: "Tipo salud", type: "text" },
    { key: "health_institution_name", label: "Institución salud", type: "text" },
    { key: "health_plan_amount", label: "Plan salud", type: "number" },
    { key: "health_plan_unit", label: "Unidad plan", type: "select", typeKey: "units" },
    { key: "has_afc", label: "AFC", type: "checkbox" },
    { key: "is_active", label: "Activo", type: "checkbox" },
    { key: "notes", label: "Notas", type: "textarea" },
  ],
  "contract-settings": [
    { key: "staff_id", label: "Funcionario", type: "select", optionsKey: "staff", required: true },
    { key: "contract_id", label: "Contrato", type: "select", optionsKey: "contracts" },
    { key: "employee_type", label: "Tipo", type: "select", typeKey: "employee_types", required: true },
    { key: "base_salary", label: "Sueldo base", type: "number", required: true },
    { key: "weekly_hours", label: "Horas", type: "number", required: true },
    { key: "teacher_career", label: "Carrera Docente", type: "checkbox" },
    { key: "teacher_level", label: "Tramo docente", type: "text" },
    { key: "pie_hours", label: "Horas PIE", type: "number" },
    { key: "sep_hours", label: "Horas SEP", type: "number" },
    { key: "pro_retention_hours", label: "Horas Pro-Retención", type: "number" },
    { key: "funding_distribution", label: "Distribución JSON", type: "json" },
    { key: "accounting_debit_account_id", label: "Cuenta debe", type: "select", optionsKey: "manual_accounts" },
    { key: "accounting_credit_account_id", label: "Cuenta haber", type: "select", optionsKey: "manual_accounts" },
    { key: "is_active", label: "Activo", type: "checkbox" },
  ],
  concepts: [
    { key: "code", label: "Código", type: "text", required: true },
    { key: "name", label: "Nombre", type: "text", required: true },
    { key: "type", label: "Tipo", type: "select", typeKey: "concept_types", required: true },
    { key: "calculation_type", label: "Cálculo", type: "select", typeKey: "calculation_types", required: true },
    { key: "amount", label: "Monto", type: "number" },
    { key: "formula", label: "Fórmula", type: "textarea" },
    { key: "is_imponible", label: "Imponible", type: "checkbox" },
    { key: "is_taxable", label: "Tributable", type: "checkbox" },
    { key: "affects_tax_base", label: "Base impuesto", type: "checkbox" },
    { key: "affects_net", label: "Afecta líquido", type: "checkbox" },
    { key: "is_system", label: "Sistema", type: "checkbox" },
    { key: "is_active", label: "Activo", type: "checkbox" },
  ],
  movements: [
    { key: "period_id", label: "Período", type: "select", optionsKey: "periods", required: true },
    { key: "staff_id", label: "Funcionario", type: "select", optionsKey: "staff", required: true },
    { key: "contract_id", label: "Contrato", type: "select", optionsKey: "contracts" },
    { key: "concept_id", label: "Concepto", type: "select", optionsKey: "concepts" },
    { key: "movement_type", label: "Tipo", type: "select", typeKey: "movement_types", required: true },
    { key: "description", label: "Descripción", type: "text", required: true },
    { key: "amount", label: "Monto", type: "number" },
    { key: "affects_days", label: "Días", type: "number" },
    { key: "status", label: "Estado", type: "select", statusKey: "movements" },
    { key: "funding_source_id", label: "Fuente", type: "select", optionsKey: "funding_sources" },
    { key: "cost_center_id", label: "Centro", type: "select", optionsKey: "cost_centers" },
  ],
  payments: [
    { key: "payroll_id", label: "Liquidación", type: "select", optionsKey: "payrolls", required: true },
    { key: "payment_date", label: "Fecha", type: "date", required: true },
    { key: "amount", label: "Monto", type: "number", required: true },
    { key: "payment_method", label: "Forma pago", type: "select", typeKey: "payment_methods", required: true },
    { key: "bank_account_id", label: "Cuenta bancaria", type: "select", optionsKey: "bank_accounts" },
    { key: "reference", label: "Referencia", type: "text" },
    { key: "notes", label: "Notas", type: "textarea" },
  ],
  "medical-leaves": [
    { key: "staff_id", label: "Funcionario", type: "select", optionsKey: "staff", required: true },
    { key: "period_id", label: "Período", type: "select", optionsKey: "periods" },
    { key: "document_control_id", label: "Documento", type: "select", optionsKey: "document_controls" },
    { key: "license_number", label: "Folio licencia", type: "text" },
    { key: "issuer", label: "Emisor", type: "text" },
    { key: "diagnosis_group", label: "Grupo diagnóstico", type: "text" },
    { key: "starts_at", label: "Inicio", type: "date", required: true },
    { key: "ends_at", label: "Término", type: "date", required: true },
    { key: "days", label: "Días", type: "number", required: true },
    { key: "affects_payroll", label: "Afecta remuneración", type: "checkbox" },
    { key: "subsidy_status", label: "Estado subsidio", type: "text" },
    { key: "status", label: "Estado", type: "select", statusKey: "medical_leaves" },
    { key: "notes", label: "Notas", type: "textarea" },
  ],
  permissions: [
    { key: "staff_id", label: "Funcionario", type: "select", optionsKey: "staff", required: true },
    { key: "permission_type_id", label: "Tipo permiso", type: "select", optionsKey: "permission_types", required: true },
    { key: "start_date", label: "Inicio", type: "date", required: true },
    { key: "end_date", label: "Término", type: "date", required: true },
    { key: "start_time", label: "Hora inicio", type: "time" },
    { key: "end_time", label: "Hora término", type: "time" },
    { key: "duration_hours", label: "Horas", type: "number" },
    { key: "duration_days", label: "Días", type: "number" },
    { key: "reason", label: "Motivo", type: "text", required: true },
    { key: "with_pay", label: "Con goce", type: "checkbox" },
    { key: "affects_salary", label: "Afecta sueldo", type: "checkbox" },
    { key: "requires_replacement", label: "Requiere reemplazo", type: "checkbox" },
    { key: "status", label: "Estado", type: "select", statusKey: "permission_requests" },
    { key: "payroll_status", label: "Estado remuneraciones", type: "select", typeKey: "payroll_statuses" },
    { key: "internal_observations", label: "Observaciones internas", type: "textarea" },
  ],
  departments: [
    { key: "name", label: "Departamento", type: "text", required: true },
    { key: "description", label: "Descripción", type: "textarea" },
    { key: "color", label: "Color", type: "text" },
    { key: "sort_order", label: "Orden", type: "number" },
    { key: "active", label: "Activo", type: "checkbox", default: true },
  ],
  functions: [
    { key: "name", label: "Función", type: "text", required: true },
    { key: "description", label: "Descripción", type: "textarea" },
    { key: "active", label: "Activo", type: "checkbox", default: true },
  ],
  "staff-management": [
    { key: "full_name", label: "Nombre completo", type: "text", required: true },
    { key: "rut", label: "RUT", type: "text", required: true },
    { key: "birth_date", label: "Nacimiento", type: "date" },
    { key: "institutional_email", label: "Correo institucional", type: "email" },
    { key: "personal_email", label: "Correo personal", type: "email" },
    { key: "phone", label: "Teléfono", type: "text" },
    { key: "address", label: "Dirección", type: "text" },
    { key: "cargo_id", label: "Cargo", type: "select", optionsKey: "cargos" },
    { key: "contract_type", label: "Tipo contrato", type: "text" },
    { key: "start_date", label: "Ingreso", type: "date" },
    { key: "end_date", label: "Término", type: "date" },
    { key: "status", label: "Estado", type: "text" },
    { key: "workday", label: "Jornada", type: "text" },
    { key: "contract_hours", label: "Horas contrato", type: "number" },
    { key: "professional_title", label: "Título", type: "text" },
    { key: "specialty", label: "Especialidad", type: "text" },
    { key: "active", label: "Activo", type: "checkbox" },
    { key: "internal_notes", label: "Notas internas", type: "textarea" },
  ],
  "document-controls": [
    { key: "staff_id", label: "Funcionario", type: "select", optionsKey: "staff" },
    { key: "related_area", label: "Área", type: "text" },
    { key: "document_type", label: "Tipo documento", type: "select", typeKey: "document_types", required: true },
    { key: "title", label: "Título", type: "text", required: true },
    { key: "folio", label: "Folio", type: "text" },
    { key: "issued_at", label: "Emisión", type: "date" },
    { key: "expires_at", label: "Vencimiento", type: "date" },
    { key: "alert_days", label: "Alerta días", type: "number" },
    { key: "status", label: "Estado", type: "select", statusKey: "documents" },
    { key: "owner_area", label: "Responsable", type: "text" },
    { key: "file_path", label: "Ruta archivo", type: "text" },
    { key: "notes", label: "Notas", type: "textarea" },
  ],
  onboarding: [
    { key: "staff_id", label: "Funcionario", type: "select", optionsKey: "staff", required: true },
    { key: "job_profile_id", label: "Perfil cargo", type: "select", optionsKey: "job_profiles" },
    { key: "responsible_user_id", label: "Responsable", type: "select", optionsKey: "users" },
    { key: "starts_at", label: "Inicio", type: "date", required: true },
    { key: "target_completion_at", label: "Meta término", type: "date" },
    { key: "completed_at", label: "Completado", type: "date" },
    { key: "status", label: "Estado", type: "select", statusKey: "onboarding" },
    { key: "completion_percent", label: "Avance %", type: "number" },
    { key: "documents_checklist", label: "Checklist documentos JSON", type: "json" },
    { key: "trainings_checklist", label: "Checklist capacitaciones JSON", type: "json" },
    { key: "accesses_checklist", label: "Checklist accesos JSON", type: "json" },
    { key: "materials_checklist", label: "Checklist materiales JSON", type: "json" },
    { key: "notes", label: "Notas", type: "textarea" },
  ],
  "climate-surveys": [
    { key: "title", label: "Título", type: "text", required: true },
    { key: "scope", label: "Alcance", type: "text" },
    { key: "starts_at", label: "Inicio", type: "date" },
    { key: "ends_at", label: "Término", type: "date" },
    { key: "status", label: "Estado", type: "select", statusKey: "climate" },
    { key: "response_count", label: "Respuestas", type: "number" },
    { key: "satisfaction_score", label: "Satisfacción", type: "number" },
    { key: "risk_level", label: "Riesgo", type: "select", typeKey: "risk_levels" },
    { key: "questions", label: "Preguntas JSON", type: "json" },
    { key: "alerts", label: "Alertas JSON", type: "json" },
    { key: "report_payload", label: "Reporte JSON", type: "json" },
    { key: "summary", label: "Resumen", type: "textarea" },
  ],
  "climate-action-plans": [
    { key: "survey_id", label: "Encuesta", type: "select", optionsKey: "climate_surveys" },
    { key: "owner_user_id", label: "Responsable", type: "select", optionsKey: "users" },
    { key: "title", label: "Título", type: "text", required: true },
    { key: "risk_level", label: "Riesgo", type: "select", typeKey: "risk_levels" },
    { key: "action", label: "Acción", type: "textarea" },
    { key: "due_date", label: "Vence", type: "date" },
    { key: "completed_at", label: "Completado", type: "date" },
    { key: "status", label: "Estado", type: "select", statusKey: "action_plans" },
    { key: "evidence", label: "Evidencia JSON", type: "json" },
    { key: "notes", label: "Notas", type: "textarea" },
  ],
  workload: [
    { key: "staff_id", label: "Funcionario", type: "select", optionsKey: "staff", required: true },
    { key: "contract_id", label: "Contrato", type: "select", optionsKey: "contracts" },
    { key: "period_id", label: "Período", type: "select", optionsKey: "periods" },
    { key: "department_id", label: "Departamento", type: "select", optionsKey: "departments" },
    { key: "replacement_staff_id", label: "Reemplazo", type: "select", optionsKey: "staff" },
    { key: "function_name", label: "Función", type: "text", required: true },
    { key: "role_type", label: "Tipo rol", type: "select", typeKey: "workload_roles" },
    { key: "contracted_hours", label: "Horas contratadas", type: "number" },
    { key: "classroom_hours", label: "Horas aula", type: "number" },
    { key: "non_classroom_hours", label: "Horas no aula", type: "number" },
    { key: "coordination_hours", label: "Coordinación", type: "number" },
    { key: "pie_hours", label: "PIE", type: "number" },
    { key: "sep_hours", label: "SEP", type: "number" },
    { key: "replacement_hours", label: "Horas reemplazo", type: "number" },
    { key: "starts_at", label: "Inicio", type: "date" },
    { key: "ends_at", label: "Término", type: "date" },
    { key: "status", label: "Estado", type: "select", statusKey: "workload" },
    { key: "notes", label: "Notas", type: "textarea" },
  ],
  "cv-bank": [
    { key: "full_name", label: "Nombre", type: "text", required: true },
    { key: "rut", label: "RUT", type: "text" },
    { key: "email", label: "Correo", type: "email" },
    { key: "phone", label: "Teléfono", type: "text" },
    { key: "source", label: "Origen", type: "text" },
    { key: "desired_position", label: "Cargo deseado", type: "text" },
    { key: "specialty", label: "Especialidad", type: "text" },
    { key: "experience_years", label: "Años experiencia", type: "number" },
    { key: "availability", label: "Disponibilidad", type: "text" },
    { key: "rating", label: "Evaluación", type: "number" },
    { key: "status", label: "Estado", type: "select", statusKey: "cv_bank" },
    { key: "cv_path", label: "Ruta CV", type: "text" },
    { key: "notes", label: "Notas", type: "textarea" },
  ],
  "replacement-pool": [
    { key: "cv_bank_entry_id", label: "Banco CV", type: "select", optionsKey: "cv_bank_entries" },
    { key: "staff_id", label: "Funcionario asociado", type: "select", optionsKey: "staff" },
    { key: "full_name", label: "Nombre", type: "text", required: true },
    { key: "specialty", label: "Especialidad", type: "text" },
    { key: "subject_area", label: "Área", type: "text" },
    { key: "available_from", label: "Disponible desde", type: "date" },
    { key: "available_until", label: "Disponible hasta", type: "date" },
    { key: "preferred_hours", label: "Horas preferidas", type: "number" },
    { key: "rating", label: "Evaluación", type: "number" },
    { key: "last_replacement_at", label: "Último reemplazo", type: "date" },
    { key: "status", label: "Estado", type: "select", statusKey: "replacement_pool" },
    { key: "notes", label: "Notas", type: "textarea" },
  ],
  "job-profiles": [
    { key: "cargo_id", label: "Cargo", type: "select", optionsKey: "cargos" },
    { key: "code", label: "Código", type: "text", required: true },
    { key: "title", label: "Título", type: "text", required: true },
    { key: "area", label: "Área", type: "text" },
    { key: "purpose", label: "Propósito", type: "textarea" },
    { key: "responsibilities", label: "Responsabilidades JSON", type: "json" },
    { key: "requirements", label: "Requisitos JSON", type: "json" },
    { key: "competencies", label: "Competencias JSON", type: "json" },
    { key: "workload_profile", label: "Carga JSON", type: "json" },
    { key: "version", label: "Versión", type: "text" },
    { key: "status", label: "Estado", type: "select", statusKey: "job_profiles" },
    { key: "notes", label: "Notas", type: "textarea" },
  ],
  "labor-certificates": [
    { key: "staff_id", label: "Funcionario", type: "select", optionsKey: "staff", required: true },
    { key: "certificate_type", label: "Tipo", type: "select", typeKey: "certificate_types", required: true },
    { key: "purpose", label: "Finalidad", type: "text" },
    { key: "requested_at", label: "Solicitud", type: "date" },
    { key: "issued_at", label: "Emisión", type: "date" },
    { key: "folio", label: "Folio", type: "text" },
    { key: "signed_by_user_id", label: "Firma", type: "select", optionsKey: "users" },
    { key: "status", label: "Estado", type: "select", statusKey: "certificates" },
    { key: "payload", label: "Datos JSON", type: "json" },
    { key: "notes", label: "Notas", type: "textarea" },
  ],
};

const panels = {
  dashboard: {
    title: "Dashboard Remuneraciones",
    help: "Indicadores del período activo, alertas operativas y últimas liquidaciones.",
    kind: "dashboard",
  },
  periods: {
    title: "Períodos",
    help: "Apertura, cierre y reapertura de períodos mensuales.",
    kind: "resource",
    resource: "periods",
    fields: fields.periods,
    columns: [
      { key: "name", label: "Período" },
      { key: "status", label: "Estado", format: "badge" },
      { key: "period_start", label: "Inicio", format: "date" },
      { key: "period_end", label: "Término", format: "date" },
    ],
  },
  parameters: {
    title: "Parámetros",
    help: "Parámetros legales y operativos por vigencia.",
    kind: "resource",
    resource: "parameters",
    fields: fields.parameters,
    columns: [
      { key: "code", label: "Código" },
      { key: "name", label: "Nombre" },
      { key: "category", label: "Categoría" },
      { key: "value", label: "Valor" },
      { key: "unit", label: "Unidad" },
      { key: "effective_from", label: "Desde", format: "date" },
      { key: "is_active", label: "Activo", format: "boolean" },
    ],
  },
  profiles: {
    title: "Trabajadores",
    help: "Ficha previsional y de pago asociada a funcionarios.",
    kind: "resource",
    resource: "profiles",
    fields: fields.profiles,
    columns: [
      { key: "staff.full_name", label: "Funcionario" },
      { key: "payment_method", label: "Pago" },
      { key: "afp_name", label: "AFP" },
      { key: "health_institution_name", label: "Salud" },
      { key: "has_afc", label: "AFC", format: "boolean" },
      { key: "is_active", label: "Activo", format: "boolean" },
    ],
  },
  "contract-settings": {
    title: "Contratos",
    help: "Configuración remuneracional enlazada al contrato laboral.",
    kind: "resource",
    resource: "contract-settings",
    fields: fields["contract-settings"],
    columns: [
      { key: "staff.full_name", label: "Funcionario" },
      { key: "contract.position_name", label: "Cargo" },
      { key: "employee_type", label: "Tipo" },
      { key: "base_salary", label: "Sueldo base", format: "currency" },
      { key: "weekly_hours", label: "Horas" },
      { key: "teacher_career", label: "Carrera Docente", format: "boolean" },
    ],
  },
  concepts: {
    title: "Conceptos",
    help: "Haberes, descuentos, aportes y fórmulas controladas.",
    kind: "resource",
    resource: "concepts",
    fields: fields.concepts,
    columns: [
      { key: "code", label: "Código" },
      { key: "name", label: "Nombre" },
      { key: "type", label: "Tipo", format: "badge" },
      { key: "calculation_type", label: "Cálculo" },
      { key: "is_imponible", label: "Imponible", format: "boolean" },
      { key: "is_active", label: "Activo", format: "boolean" },
    ],
  },
  movements: {
    title: "Movimientos",
    help: "Bonos, descuentos, licencias, atrasos, reemplazos y ajustes.",
    kind: "resource",
    resource: "movements",
    fields: fields.movements,
    columns: [
      { key: "period.name", label: "Período" },
      { key: "staff.full_name", label: "Funcionario" },
      { key: "movement_type", label: "Tipo" },
      { key: "description", label: "Detalle" },
      { key: "amount", label: "Monto", format: "currency" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  payrolls: {
    title: "Liquidaciones",
    help: "Cálculo, revisión, aprobación, pago y centralización.",
    kind: "resource",
    resource: "payrolls",
    readOnlyForm: true,
    columns: [
      { key: "period.name", label: "Período" },
      { key: "staff.full_name", label: "Funcionario" },
      { key: "code", label: "Código" },
      { key: "gross_total", label: "Haberes", format: "currency" },
      { key: "total_deductions", label: "Descuentos", format: "currency" },
      { key: "net_amount", label: "Líquido", format: "currency" },
      { key: "source", label: "Origen" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  imports: {
    title: "Importador de Libro",
    help: "Carga mensual de libro externo, conciliación por RUT y trazabilidad de liquidaciones importadas.",
    kind: "imports",
    resource: "imports",
    readOnlyForm: true,
    columns: [
      { key: "period.name", label: "Período" },
      { key: "original_filename", label: "Archivo" },
      { key: "row_count", label: "Filas" },
      { key: "gross_total", label: "Haberes", format: "currency" },
      { key: "net_total", label: "Líquido", format: "currency" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  "import-rows": {
    title: "Libro Importado",
    help: "Consulta directa de todas las filas cargadas desde libros externos, incluyendo RUT sin funcionario asociado.",
    kind: "resource",
    resource: "import-rows",
    readOnlyForm: true,
    columns: [
      { key: "import.period.name", label: "Período" },
      { key: "row_number", label: "Fila" },
      { key: "rut", label: "RUT libro" },
      { key: "employee_name", label: "Funcionario libro" },
      { key: "staff.full_name", label: "Funcionario sistema" },
      { key: "employee_type", label: "Tipo" },
      { key: "gross_total", label: "Haberes", format: "currency" },
      { key: "total_deductions", label: "Descuentos", format: "currency" },
      { key: "employer_contributions", label: "Aportes", format: "currency" },
      { key: "net_amount", label: "Líquido", format: "currency" },
    ],
  },
  "book-analytics": {
    title: "Datos y Estadísticas",
    help: "Indicadores, tendencias, composición, alertas y detalle derivados de libros de remuneraciones importados.",
    kind: "book-analytics",
  },
  payments: {
    title: "Pagos",
    help: "Registro de pagos y referencias bancarias.",
    kind: "resource",
    resource: "payments",
    fields: fields.payments,
    columns: [
      { key: "payroll.code", label: "Liquidación" },
      { key: "payroll.staff.full_name", label: "Funcionario" },
      { key: "payment_date", label: "Fecha", format: "date" },
      { key: "amount", label: "Monto", format: "currency" },
      { key: "payment_method", label: "Medio" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  "accounting-exports": {
    title: "Centralización",
    help: "Asientos contables generados desde liquidaciones aprobadas o pagadas.",
    kind: "resource",
    resource: "accounting-exports",
    readOnlyForm: true,
    columns: [
      { key: "export_code", label: "Código" },
      { key: "payroll.code", label: "Liquidación" },
      { key: "journalEntry.entry_number", label: "Asiento" },
      { key: "total_debit", label: "Debe", format: "currency" },
      { key: "total_credit", label: "Haber", format: "currency" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  "medical-leaves": {
    title: "Licencias Médicas",
    help: "Registro y seguimiento de licencias médicas con vínculo a período, documento y remuneraciones.",
    kind: "resource",
    resource: "medical-leaves",
    fields: fields["medical-leaves"],
    columns: [
      { key: "staff.full_name", label: "Funcionario" },
      { key: "period.name", label: "Período" },
      { key: "license_number", label: "Folio" },
      { key: "starts_at", label: "Inicio", format: "date" },
      { key: "ends_at", label: "Término", format: "date" },
      { key: "days", label: "Días" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  birthdays: {
    title: "Cumpleaños",
    help: "Listado de funcionarios con fecha de nacimiento registrada.",
    kind: "resource",
    resource: "birthdays",
    readOnlyForm: true,
    columns: [
      { key: "full_name", label: "Funcionario" },
      { key: "rut", label: "RUT" },
      { key: "cargo.name", label: "Cargo" },
      { key: "birth_date", label: "Nacimiento", format: "date" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  permissions: {
    title: "Permisos",
    help: "Permisos administrativos y su impacto en asistencia y remuneraciones.",
    kind: "resource",
    resource: "permissions",
    fields: fields.permissions,
    columns: [
      { key: "staff.full_name", label: "Funcionario" },
      { key: "permissionType.name", label: "Tipo" },
      { key: "start_date", label: "Inicio", format: "date" },
      { key: "end_date", label: "Término", format: "date" },
      { key: "reason", label: "Motivo" },
      { key: "payroll_status", label: "Remuneraciones", format: "badge" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  departments: {
    title: "Departamentos",
    help: "Catálogo de departamentos institucionales usado para dotación, carga horaria y procesos de RR.HH.",
    kind: "resource",
    resource: "departments",
    fields: fields.departments,
    columns: [
      { key: "name", label: "Departamento" },
      { key: "slug", label: "Slug" },
      { key: "description", label: "Descripción" },
      { key: "sort_order", label: "Orden" },
      { key: "active", label: "Activo", format: "boolean" },
    ],
  },
  functions: {
    title: "Funciones",
    help: "Catálogo de funciones/cargos institucionales para clasificar funcionarios y perfiles.",
    kind: "resource",
    resource: "functions",
    fields: fields.functions,
    columns: [
      { key: "name", label: "Función" },
      { key: "slug", label: "Slug" },
      { key: "description", label: "Descripción" },
      { key: "active", label: "Activo", format: "boolean" },
    ],
  },
  "staff-management": {
    title: "Gestión de Funcionarios",
    help: "Administración de datos laborales base de funcionarios.",
    kind: "resource",
    resource: "staff-management",
    fields: fields["staff-management"],
    columns: [
      { key: "full_name", label: "Funcionario" },
      { key: "rut", label: "RUT" },
      { key: "cargo.name", label: "Cargo" },
      { key: "contract_type", label: "Contrato" },
      { key: "contract_hours", label: "Horas" },
      { key: "status", label: "Estado", format: "badge" },
      { key: "active", label: "Activo", format: "boolean" },
    ],
  },
  "document-controls": {
    title: "Control Documental",
    help: "Documentos laborales, vencimientos, responsables y alertas.",
    kind: "resource",
    resource: "document-controls",
    fields: fields["document-controls"],
    columns: [
      { key: "staff.full_name", label: "Funcionario" },
      { key: "document_type", label: "Tipo" },
      { key: "title", label: "Documento" },
      { key: "folio", label: "Folio" },
      { key: "expires_at", label: "Vence", format: "date" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  onboarding: {
    title: "Inducción",
    help: "Checklist de documentos, capacitaciones, accesos y materiales para ingreso de funcionarios.",
    kind: "resource",
    resource: "onboarding",
    fields: fields.onboarding,
    columns: [
      { key: "staff.full_name", label: "Funcionario" },
      { key: "jobProfile.title", label: "Perfil" },
      { key: "responsibleUser.name", label: "Responsable" },
      { key: "starts_at", label: "Inicio", format: "date" },
      { key: "completion_percent", label: "Avance %" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  "climate-surveys": {
    title: "Clima Laboral",
    help: "Encuestas, reportes, alertas y riesgo laboral consolidado.",
    kind: "resource",
    resource: "climate-surveys",
    fields: fields["climate-surveys"],
    columns: [
      { key: "title", label: "Encuesta" },
      { key: "scope", label: "Alcance" },
      { key: "response_count", label: "Respuestas" },
      { key: "satisfaction_score", label: "Satisfacción" },
      { key: "risk_level", label: "Riesgo", format: "badge" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  "climate-action-plans": {
    title: "Planes de Acción",
    help: "Seguimiento de acciones derivadas de alertas de clima laboral.",
    kind: "resource",
    resource: "climate-action-plans",
    fields: fields["climate-action-plans"],
    columns: [
      { key: "survey.title", label: "Encuesta" },
      { key: "title", label: "Plan" },
      { key: "ownerUser.name", label: "Responsable" },
      { key: "risk_level", label: "Riesgo", format: "badge" },
      { key: "due_date", label: "Vence", format: "date" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  workload: {
    title: "Dotación y Carga Horaria",
    help: "Horas contratadas, horas aula, funciones, departamentos y reemplazos.",
    kind: "resource",
    resource: "workload",
    fields: fields.workload,
    columns: [
      { key: "staff.full_name", label: "Funcionario" },
      { key: "function_name", label: "Función" },
      { key: "department.name", label: "Departamento" },
      { key: "contracted_hours", label: "Contratadas" },
      { key: "classroom_hours", label: "Aula" },
      { key: "replacementStaff.full_name", label: "Reemplazo" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  "cv-bank": {
    title: "Banco de CV",
    help: "Postulantes, especialidades, disponibilidad y evaluación.",
    kind: "resource",
    resource: "cv-bank",
    fields: fields["cv-bank"],
    columns: [
      { key: "full_name", label: "Postulante" },
      { key: "desired_position", label: "Cargo" },
      { key: "specialty", label: "Especialidad" },
      { key: "availability", label: "Disponibilidad" },
      { key: "rating", label: "Evaluación" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  "replacement-pool": {
    title: "Banco de Buenos Reemplazos",
    help: "Reemplazos recomendados, disponibilidad y calificación.",
    kind: "resource",
    resource: "replacement-pool",
    fields: fields["replacement-pool"],
    columns: [
      { key: "full_name", label: "Nombre" },
      { key: "specialty", label: "Especialidad" },
      { key: "subject_area", label: "Área" },
      { key: "available_from", label: "Desde", format: "date" },
      { key: "preferred_hours", label: "Horas" },
      { key: "rating", label: "Evaluación" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  "job-profiles": {
    title: "Perfiles de Cargo",
    help: "Propósito, responsabilidades, requisitos, competencias y carga esperada por cargo.",
    kind: "resource",
    resource: "job-profiles",
    fields: fields["job-profiles"],
    columns: [
      { key: "code", label: "Código" },
      { key: "title", label: "Perfil" },
      { key: "cargo.name", label: "Cargo" },
      { key: "area", label: "Área" },
      { key: "version", label: "Versión" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  "labor-certificates": {
    title: "Certificados Laborales",
    help: "Solicitudes, emisión y descarga de certificados laborales.",
    kind: "resource",
    resource: "labor-certificates",
    fields: fields["labor-certificates"],
    columns: [
      { key: "staff.full_name", label: "Funcionario" },
      { key: "certificate_type", label: "Tipo" },
      { key: "purpose", label: "Finalidad" },
      { key: "folio", label: "Folio" },
      { key: "issued_at", label: "Emisión", format: "date" },
      { key: "status", label: "Estado", format: "badge" },
    ],
  },
  reports: {
    title: "Reportes",
    help: "Resumen exportable por período.",
    kind: "reports",
  },
  "audit-logs": {
    title: "Auditoría",
    help: "Trazabilidad de acciones críticas del módulo.",
    kind: "resource",
    resource: "audit-logs",
    readOnlyForm: true,
    columns: [
      { key: "created_at", label: "Fecha", format: "date" },
      { key: "user.name", label: "Usuario" },
      { key: "action", label: "Acción", format: "badge" },
      { key: "auditable_type", label: "Entidad" },
      { key: "auditable_id", label: "ID" },
      { key: "reason", label: "Motivo" },
    ],
  },
};

export default {
  components: { Layout, PageHeader, LoadingState, BookAnalyticsPanel },
  data() {
    return {
      catalogs: { statuses: {}, types: {}, data: {}, permissions: [] },
      dashboard: null,
      resources: {},
      isLoading: false,
      error: null,
      search: "",
      modalVisible: false,
      calculationModalVisible: false,
      pdfModalVisible: false,
      pdfMode: "complete",
      pdfExporting: false,
      importFile: null,
      importReplace: false,
      importPreview: null,
      importLoading: false,
      importCommitting: false,
      importBookModalVisible: false,
      importBookLoading: false,
      importBookPdfExporting: false,
      selectedImport: null,
      importBookRows: [],
      editingId: null,
      form: {},
      calculationForm: {
        period_id: "",
        staff_id: "",
        payroll_type: "mensual",
        force: false,
      },
      pdfForm: {
        staff_id: "",
        period_id: "",
        from_period_id: "",
        to_period_id: "",
        payroll_type: "mensual",
        include_annulled: false,
      },
    };
  },
  computed: {
    activeKey() {
      return routeMap[this.$route.path] || "dashboard";
    },
    activePanel() {
      return panels[this.activeKey] || panels.dashboard;
    },
    activeRows() {
      return this.resources[this.activePanel.resource]?.items || [];
    },
    pageTitle() {
      return this.activePanel.title;
    },
    canMutateActive() {
      return this.activePanel.kind === "resource" && !this.activePanel.readOnlyForm;
    },
    importBookSortedRows() {
      return [...this.importBookRows].sort((left, right) => Number(left.row_number || 0) - Number(right.row_number || 0));
    },
    importBookEarningsColumns() {
      return this.collectImportBookColumns("raw_earnings_columns");
    },
    importBookDeductionsColumns() {
      return this.collectImportBookColumns("raw_deductions_columns");
    },
    dashboardTrendSeries() {
      const rows = this.dashboard?.analytics?.trend || [];
      return [
        { name: "Haberes", data: rows.map((item) => Number(item.gross_total || 0)) },
        { name: "Líquido", data: rows.map((item) => Number(item.net_total || 0)) },
        { name: "Descuentos", data: rows.map((item) => Number(item.total_deductions || 0)) },
      ];
    },
    dashboardTypeSeries() {
      return (this.dashboard?.analytics?.by_type || []).map((item) => Number(item.count || 0));
    },
    dashboardTypeLabels() {
      return (this.dashboard?.analytics?.by_type || []).map((item) => item.type || "Sin tipo");
    },
  },
  watch: {
    "$route.path"() {
      this.search = "";
      this.loadActive();
    },
  },
  mounted() {
    this.loadInitial();
  },
  methods: {
    money,
    shortDate,
    compactMoney(value) {
      return new Intl.NumberFormat("es-CL", { notation: "compact", maximumFractionDigits: 1 }).format(Number(value || 0));
    },
    dashboardTrendOptions() {
      return {
        chart: { toolbar: { show: false }, zoom: { enabled: false }, fontFamily: "inherit" },
        colors: ["#556ee6", "#34c38f", "#f1b44c"],
        stroke: { curve: "smooth", width: 3 },
        dataLabels: { enabled: false },
        grid: { borderColor: "#edf1f7", strokeDashArray: 4 },
        xaxis: {
          categories: (this.dashboard?.analytics?.trend || []).map((item) => item.period),
          axisBorder: { show: false }, axisTicks: { show: false },
        },
        yaxis: { labels: { formatter: (value) => this.compactMoney(value) } },
        tooltip: { y: { formatter: (value) => money(value) } },
        legend: { position: "top", horizontalAlign: "right" },
      };
    },
    dashboardTypeOptions() {
      return {
        chart: { fontFamily: "inherit" },
        labels: this.dashboardTypeLabels,
        colors: ["#556ee6", "#34c38f", "#50a5f1", "#f1b44c", "#f46a6a", "#74788d"],
        legend: { position: "bottom", fontSize: "12px" },
        dataLabels: { enabled: true, formatter: (value) => `${Math.round(value)}%` },
        plotOptions: { pie: { donut: { size: "68%", labels: { show: true, total: { show: true, label: "Dotación" } } } } },
        stroke: { width: 3, colors: ["#fff"] },
      };
    },
    async loadInitial() {
      await this.loadCatalogs();
      await this.loadActive();
    },
    async loadCatalogs() {
      const response = await axios.get("/api/remuneraciones/catalogs");
      this.catalogs = response.data;
    },
    async loadActive() {
      this.error = null;
      this.isLoading = true;
      try {
        if (this.activePanel.kind === "dashboard" || this.activePanel.kind === "reports") {
          await this.loadDashboard();
        }
        if (this.activePanel.kind === "resource" || this.activePanel.kind === "imports") {
          await this.loadResource(this.activePanel.resource);
        }
        if (!this.resources.payrolls) {
          await this.loadResource("payrolls", true);
        }
      } catch (error) {
        this.error = formatRemunerationError(error);
      } finally {
        this.isLoading = false;
      }
    },
    async loadDashboard() {
      const response = await axios.get("/api/remuneraciones/dashboard");
      this.dashboard = response.data;
    },
    async loadResource(resource, silent = false) {
      if (!resource) return;
      const response = await axios.get(`/api/remuneraciones/resources/${resource}`, {
        params: {
          search: silent ? "" : this.search,
          per_page: 50,
        },
      });
      this.resources[resource] = { items: response.data.data || [] };
      if (resource === "payrolls") this.catalogs.data.payrolls = this.resources[resource].items;
    },
    optionItems(field) {
      if (field.optionsKey) return this.catalogs.data?.[field.optionsKey] || [];
      if (field.statusKey) return this.catalogs.statuses?.[field.statusKey] || [];
      if (field.typeKey) return this.catalogs.types?.[field.typeKey] || [];
      return [];
    },
    optionValue(option) {
      return typeof option === "object" ? option.id : option;
    },
    optionLabel(option, field) {
      if (typeof option !== "object") return option;
      if (field.optionsKey === "staff") return `${option.full_name} (${option.rut || "sin RUT"})`;
      if (field.optionsKey === "contracts") return `${option.staff?.full_name || "Contrato"} · ${option.position_name || option.contract_type}`;
      if (field.optionsKey === "manual_accounts") return `${option.code} - ${option.name}`;
      if (field.optionsKey === "payrolls") return `${option.code} · ${money(option.net_amount)}`;
      if (field.optionsKey === "users") return `${option.name} (${option.email || "sin correo"})`;
      if (field.optionsKey === "cargos") return option.name;
      if (field.optionsKey === "departments") return option.name;
      if (field.optionsKey === "permission_types") return option.name;
      if (field.optionsKey === "job_profiles") return `${option.code} · ${option.title}`;
      if (field.optionsKey === "document_controls") return `${option.title} · ${option.status}`;
      if (field.optionsKey === "climate_surveys") return `${option.title} · ${option.status}`;
      if (field.optionsKey === "cv_bank_entries") return `${option.full_name} · ${option.specialty || option.desired_position || "CV"}`;
      return option.name || option.code || option.id;
    },
    openCreate() {
      this.editingId = null;
      this.form = {};
      (this.activePanel.fields || []).forEach((field) => {
        if (Object.prototype.hasOwnProperty.call(field, "default")) {
          this.form[field.key] = field.default;
          return;
        }
        this.form[field.key] = field.type === "checkbox" ? false : field.type === "json" ? "[]" : "";
      });
      this.modalVisible = true;
    },
    openEdit(item) {
      this.editingId = item.id;
      this.form = {};
      (this.activePanel.fields || []).forEach((field) => {
        const value = this.getValue(item, field.key);
        this.form[field.key] = field.type === "json" ? JSON.stringify(value || [], null, 2) : value ?? (field.type === "checkbox" ? false : "");
      });
      this.modalVisible = true;
    },
    payloadFromForm() {
      const payload = {};
      (this.activePanel.fields || []).forEach((field) => {
        let value = this.form[field.key];
        if (value === "") value = null;
        if (field.type === "checkbox") value = Boolean(value);
        if (field.type === "number" && value !== null) value = Number(value);
        if (field.type === "json") value = value ? JSON.parse(value) : null;
        payload[field.key] = value;
      });
      return payload;
    },
    async saveRecord() {
      try {
        const payload = this.payloadFromForm();
        if (this.editingId) {
          await axios.put(`/api/remuneraciones/resources/${this.activePanel.resource}/${this.editingId}`, payload);
        } else {
          await axios.post(`/api/remuneraciones/resources/${this.activePanel.resource}`, payload);
        }
        this.modalVisible = false;
        await this.loadCatalogs();
        await this.loadActive();
        Swal.fire("Guardado", "Registro actualizado correctamente.", "success");
      } catch (error) {
        Swal.fire("Error", formatRemunerationError(error), "error");
      }
    },
    async deleteRecord(item) {
      const result = await Swal.fire({
        title: "Eliminar registro",
        text: "Esta acción quedará auditada.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
      });
      if (!result.isConfirmed) return;

      try {
        await axios.delete(`/api/remuneraciones/resources/${this.activePanel.resource}/${item.id}`);
        await this.loadActive();
        Swal.fire("Eliminado", "Registro eliminado correctamente.", "success");
      } catch (error) {
        Swal.fire("Error", formatRemunerationError(error), "error");
      }
    },
    async calculatePayroll() {
      try {
        await axios.post("/api/remuneraciones/payrolls/calculate", this.calculationForm);
        this.calculationModalVisible = false;
        await this.loadActive();
        Swal.fire("Calculada", "Liquidación calculada correctamente.", "success");
      } catch (error) {
        Swal.fire("Error", formatRemunerationError(error), "error");
      }
    },
    async payrollAction(action, item) {
      const settings = {
        approve: { title: "Aprobar liquidación", url: "approve", confirm: "Aprobar" },
        annul: { title: "Anular liquidación", url: "annul", confirm: "Anular" },
        centralize: { title: "Centralizar", url: "centralize", confirm: "Centralizar" },
      }[action];
      const result = await Swal.fire({
        title: settings.title,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: settings.confirm,
        cancelButtonText: "Cancelar",
      });
      if (!result.isConfirmed) return;

      try {
        await axios.post(`/api/remuneraciones/payrolls/${item.id}/${settings.url}`);
        await this.loadActive();
        Swal.fire("Listo", "Acción ejecutada correctamente.", "success");
      } catch (error) {
        Swal.fire("Error", formatRemunerationError(error), "error");
      }
    },
    async observePayroll(item) {
      const result = await Swal.fire({
        title: "Observar liquidación",
        input: "textarea",
        inputPlaceholder: "Observación",
        showCancelButton: true,
        confirmButtonText: "Observar",
        cancelButtonText: "Cancelar",
        inputValidator: (value) => (!value ? "Ingrese una observación." : undefined),
      });
      if (!result.isConfirmed) return;
      try {
        await axios.post(`/api/remuneraciones/payrolls/${item.id}/observe`, { observations: result.value });
        await this.loadActive();
        Swal.fire("Observada", "Liquidación observada.", "success");
      } catch (error) {
        Swal.fire("Error", formatRemunerationError(error), "error");
      }
    },
    async payPayroll(item) {
      const result = await Swal.fire({
        title: "Registrar pago",
        input: "number",
        inputValue: item.net_amount,
        showCancelButton: true,
        confirmButtonText: "Pagar",
        cancelButtonText: "Cancelar",
      });
      if (!result.isConfirmed) return;
      try {
        await axios.post(`/api/remuneraciones/payrolls/${item.id}/pay`, {
          amount: Number(result.value || item.net_amount),
          payment_date: new Date().toISOString().slice(0, 10),
          payment_method: "transferencia",
        });
        await this.loadActive();
        Swal.fire("Pagada", "Pago registrado correctamente.", "success");
      } catch (error) {
        Swal.fire("Error", formatRemunerationError(error), "error");
      }
    },
    async periodAction(action, item) {
      try {
        await axios.post(`/api/remuneraciones/periods/${item.id}/${action}`);
        await this.loadActive();
        Swal.fire("Listo", "Período actualizado.", "success");
      } catch (error) {
        Swal.fire("Error", formatRemunerationError(error), "error");
      }
    },
    async exportReport() {
      window.location.href = "/api/remuneraciones/export";
    },
    handleImportFile(event) {
      this.importFile = event.target.files?.[0] || null;
      this.importPreview = null;
    },
    importFormData() {
      const formData = new FormData();
      formData.append("file", this.importFile);
      formData.append("replace", this.importReplace ? "1" : "0");
      return formData;
    },
    async previewImport() {
      if (!this.importFile) {
        Swal.fire("Falta archivo", "Seleccione un libro XLSX.", "warning");
        return;
      }
      this.importLoading = true;
      try {
        const response = await axios.post("/api/remuneraciones/imports/preview", this.importFormData(), {
          headers: { "Content-Type": "multipart/form-data" },
        });
        this.importPreview = response.data;
      } catch (error) {
        Swal.fire("Error", formatRemunerationError(error), "error");
      } finally {
        this.importLoading = false;
      }
    },
    async commitImport() {
      if (!this.importFile || !this.importPreview) return;
      if (this.importPreview.summary?.error_count > 0) {
        Swal.fire("Validación pendiente", "Corrija los errores detectados antes de importar.", "warning");
        return;
      }

      const result = await Swal.fire({
        title: "Importar libro",
        text: "Se crearán liquidaciones importadas para el período detectado.",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Importar",
        cancelButtonText: "Cancelar",
      });
      if (!result.isConfirmed) return;

      this.importCommitting = true;
      try {
        await axios.post("/api/remuneraciones/imports", this.importFormData(), {
          headers: { "Content-Type": "multipart/form-data" },
        });
        this.importPreview = null;
        this.importFile = null;
        await this.loadCatalogs();
        await this.loadActive();
        Swal.fire("Importado", "Libro de remuneraciones importado correctamente.", "success");
      } catch (error) {
        Swal.fire("Error", formatRemunerationError(error), "error");
      } finally {
        this.importCommitting = false;
      }
    },
    async openImportBookModal(item) {
      this.selectedImport = item;
      this.importBookRows = [];
      this.importBookModalVisible = true;
      this.importBookLoading = true;
      try {
        const response = await axios.get("/api/remuneraciones/resources/import-rows", {
          params: {
            book_import_id: item.id,
            all: true,
          },
        });
        this.importBookRows = response.data.data || [];
      } catch (error) {
        Swal.fire("Error", formatRemunerationError(error, "No fue posible cargar el libro importado."), "error");
      } finally {
        this.importBookLoading = false;
      }
    },
    collectImportBookColumns(field) {
      const columns = new Map();
      this.importBookSortedRows.forEach((row) => {
        (row[field] || []).forEach((column) => {
          const key = Number(column.column);
          if (!columns.has(key)) {
            columns.set(key, column);
          }
        });
      });
      return Array.from(columns.values()).sort((left, right) => Number(left.column || 0) - Number(right.column || 0));
    },
    importBookColumnLabel(column) {
      return column.header_display || column.header || column.letter || column.column;
    },
    importBookRawCell(row, field, column) {
      return (row[field] || []).find((cell) => Number(cell.column) === Number(column.column))?.value ?? null;
    },
    importBookCellValue(value) {
      if (value === null || value === undefined || value === "") return "";
      if (typeof value === "number") return new Intl.NumberFormat("es-CL", { maximumFractionDigits: 2 }).format(value);
      return String(value);
    },
    importBookPdfIdentityColumns(field, sourceColumns) {
      const identityColumns = sourceColumns.filter((column) => Number(column.column) <= 6);
      const rutIndex = identityColumns.findIndex((column) => this.importBookColumnLabel(column).toLowerCase() === "rut");
      const printableIdentityColumns = rutIndex >= 0 ? identityColumns.slice(rutIndex) : identityColumns;

      return printableIdentityColumns.map((column) => ({ section: "Identificación", field, column }));
    },
    importBookPdfSectionColumns(section, field, sourceColumns) {
      const identityColumns = this.importBookPdfIdentityColumns(field, sourceColumns);
      const dataColumns = sourceColumns
        .filter((column) => Number(column.column) > 6)
        .map((column) => ({ section, field, column }));

      return [...identityColumns, ...dataColumns];
    },
    importBookPdfSections() {
      return [
        {
          title: "Haberes",
          columns: this.importBookPdfSectionColumns("Haberes", "raw_earnings_columns", this.importBookEarningsColumns),
        },
        {
          title: "Descuentos",
          columns: this.importBookPdfSectionColumns("Descuentos", "raw_deductions_columns", this.importBookDeductionsColumns),
        },
      ].filter((section) => section.columns.length);
    },
    importBookPdfColumns() {
      return this.importBookPdfSections().flatMap((section) => section.columns);
    },
    importBookPdfGroupHeader(columns) {
      const header = [];
      let index = 0;
      while (index < columns.length) {
        const section = columns[index].section;
        const span = columns.slice(index).findIndex((column) => column.section !== section);
        const colSpan = span === -1 ? columns.length - index : span;
        header.push({ text: section, style: "bookSectionHeader", colSpan });
        for (let offset = 1; offset < colSpan; offset++) header.push({});
        index += colSpan;
      }
      return header;
    },
    importBookPdfWidth(columnDefinition) {
      const header = this.importBookColumnLabel(columnDefinition.column).toLowerCase();
      if (header === "rut") return 50;
      if (header.includes("empleado")) return 78;
      if (header.includes("tipo funcionario")) return 62;
      if (["nº", "no", "dt"].includes(header)) return 24;
      if (header.includes("carga horaria")) return 34;
      return 32;
    },
    importBookPdfUnifiedTable(columns) {
      return {
        margin: [0, 8, 0, 10],
        table: {
          headerRows: 2,
          widths: columns.map((column) => this.importBookPdfWidth(column)),
          body: [
            this.importBookPdfGroupHeader(columns),
            columns.map((column) => ({
              text: this.importBookColumnLabel(column.column),
              style: "bookHeader",
            })),
            ...this.importBookSortedRows.map((row) =>
              columns.map((column) => ({
                text: this.importBookCellValue(this.importBookRawCell(row, column.field, column.column)),
                style: "bookCell",
              }))
            ),
          ],
        },
        layout: {
          hLineColor: () => "#d9dee8",
          vLineColor: () => "#d9dee8",
          paddingLeft: () => 1.5,
          paddingRight: () => 1.5,
          paddingTop: () => 1,
          paddingBottom: () => 1,
        },
      };
    },
    exportImportBookPdf() {
      if (!this.importBookSortedRows.length) {
        Swal.fire("Sin datos", "No hay filas importadas para exportar.", "warning");
        return;
      }
      if (!this.importBookPdfColumns().length) {
        Swal.fire("Sin columnas", "Esta importación no tiene columnas de haberes o descuentos guardadas.", "warning");
        return;
      }

      this.importBookPdfExporting = true;
      try {
        const pdfMake = getPdfMake();
        const fileBase = (this.selectedImport?.original_filename || `libro_${this.selectedImport?.id || ""}`)
          .replace(/\.[^.]+$/, "")
          .replace(/[^a-z0-9_-]+/gi, "_");
        const content = [
          { text: "Libro de remuneraciones importado", style: "title" },
          {
            text: `${this.selectedImport?.period?.name || this.selectedImport?.book_period || "-"} · ${this.selectedImport?.original_filename || "-"}`,
            style: "subtitle",
          },
        ];
        const sections = this.importBookPdfSections();
        sections.forEach((section, index) => {
          if (index > 0) content.push({ text: "", pageBreak: "before" });
          content.push(
            { text: `Hoja ${index + 1}: ${section.title}`, style: "blockTitle" },
            this.importBookPdfUnifiedTable(section.columns)
          );
        });

        pdfMake.createPdf({
          pageSize: "A2",
          pageOrientation: "landscape",
          pageMargins: [14, 18, 14, 18],
          content,
          defaultStyle: { fontSize: 4.6 },
          styles: {
            title: { fontSize: 11, bold: true },
            subtitle: { fontSize: 6, color: "#6c757d", margin: [0, 2, 0, 8] },
            blockTitle: { fontSize: 6, bold: true, color: "#495057", margin: [0, 4, 0, 2] },
            bookSectionHeader: { bold: true, alignment: "center", fillColor: "#dfe7f3", fontSize: 5.3 },
            bookHeader: { bold: true, fillColor: "#f8fafc", fontSize: 4.5 },
            bookCell: { fontSize: 4.3 },
          },
          footer: (currentPage, pageCount) => ({
            text: `Página ${currentPage} de ${pageCount}`,
            alignment: "center",
            fontSize: 5,
            color: "#6c757d",
          }),
        }).download(`libro_remuneraciones_${fileBase}.pdf`);
      } catch (error) {
        Swal.fire("Error", "No fue posible generar el PDF del libro importado.", "error");
      } finally {
        this.importBookPdfExporting = false;
      }
    },
    openPayrollPdfModal(mode = "complete") {
      this.pdfMode = mode;
      this.pdfForm = {
        staff_id: "",
        period_id: this.catalogs.data?.periods?.[0]?.id || "",
        from_period_id: "",
        to_period_id: "",
        payroll_type: "mensual",
        include_annulled: false,
      };
      this.pdfModalVisible = true;
    },
    cleanParams(params) {
      return Object.fromEntries(
        Object.entries(params).filter(([, value]) => value !== "" && value !== null && value !== undefined)
      );
    },
    async downloadPayrollPdf(item) {
      await this.generatePayrollPdf(
        { payroll_id: item.id },
        `liquidacion_${(item.code || item.id).toString().replace(/[^a-z0-9_-]+/gi, "_")}.pdf`
      );
    },
    async exportPayrollPdfFromModal() {
      const params = {
        payroll_type: this.pdfForm.payroll_type,
        include_annulled: this.pdfForm.include_annulled,
      };

      if (this.pdfMode === "staff") {
        if (!this.pdfForm.staff_id) {
          Swal.fire("Falta funcionario", "Seleccione un funcionario para exportar sus liquidaciones.", "warning");
          return;
        }
        params.staff_id = this.pdfForm.staff_id;
        params.from_period_id = this.pdfForm.from_period_id;
        params.to_period_id = this.pdfForm.to_period_id;
      } else {
        params.period_id = this.pdfForm.period_id;
      }

      const filename = this.pdfMode === "staff"
        ? `liquidaciones_funcionario_${new Date().toISOString().slice(0, 10)}.pdf`
        : `liquidaciones_periodo_${new Date().toISOString().slice(0, 10)}.pdf`;

      await this.generatePayrollPdf(params, filename);
      this.pdfModalVisible = false;
    },
    async generatePayrollPdf(params, filename) {
      this.pdfExporting = true;
      try {
        const response = await axios.get("/api/remuneraciones/payrolls/pdf-data", {
          params: this.cleanParams(params),
        });
        const pdfMake = getPdfMake();
        pdfMake.createPdf(this.buildPayrollPdfDefinition(response.data)).download(filename);
      } catch (error) {
        Swal.fire("Error", formatRemunerationError(error, "No fue posible generar el PDF."), "error");
      } finally {
        this.pdfExporting = false;
      }
    },
    payrollSnapshot(payroll, key, fallback = null) {
      return payroll.snapshot?.[key] ?? fallback;
    },
    payrollWorkerName(payroll) {
      return this.payrollSnapshot(payroll, "staff")?.full_name || payroll.staff?.full_name || "-";
    },
    payrollWorkerRut(payroll) {
      return this.payrollSnapshot(payroll, "staff")?.rut || payroll.staff?.rut || "-";
    },
    payrollPeriodName(payroll) {
      return this.payrollSnapshot(payroll, "period")?.name || payroll.period?.name || "-";
    },
    payrollLineTable(payroll, type, title) {
      const lines = (payroll.lines || []).filter((line) => line.line_type === type);
      if (!lines.length) {
        return { text: `${title}: sin registros`, margin: [0, 4, 0, 6], color: "#6c757d" };
      }

      return {
        margin: [0, 4, 0, 8],
        table: {
          widths: ["auto", "*", "auto"],
          body: [
            [
              { text: title, style: "tableHeader", colSpan: 3 },
              {},
              {},
            ],
            [
              { text: "Código", style: "tableSubHeader" },
              { text: "Concepto", style: "tableSubHeader" },
              { text: "Monto", style: "tableSubHeader", alignment: "right" },
            ],
            ...lines.map((line) => [
              line.code || "-",
              line.name || "-",
              { text: money(line.amount), alignment: "right" },
            ]),
          ],
        },
        layout: "lightHorizontalLines",
      };
    },
    buildPayrollPdfDefinition(payload) {
      const content = [];
      (payload.data || []).forEach((payroll, index) => {
        const contractSnapshot = payroll.snapshot?.contract_setting || {};
        const profileSnapshot = payroll.snapshot?.employee_profile || {};
        if (index > 0) content.push({ text: "", pageBreak: "before" });

        content.push(
          { text: "Liquidación de remuneraciones", style: "title" },
          { text: `${payload.institution || "Institución"} · Generado ${payload.generated_at}`, style: "subtitle" },
          {
            columns: [
              [
                { text: `Funcionario: ${this.payrollWorkerName(payroll)}`, bold: true },
                { text: `RUT: ${this.payrollWorkerRut(payroll)}` },
                { text: `Cargo: ${payroll.contract?.position_name || payroll.staff?.cargo?.name || "-"}` },
              ],
              [
                { text: `Período: ${this.payrollPeriodName(payroll)}`, alignment: "right", bold: true },
                { text: `Código: ${payroll.code}`, alignment: "right" },
                { text: `Estado: ${payroll.status}`, alignment: "right" },
              ],
            ],
            margin: [0, 8, 0, 8],
          },
          {
            table: {
              widths: ["*", "*", "*", "*"],
              body: [
                [
                  { text: "Sueldo base", style: "tableSubHeader" },
                  { text: "Horas", style: "tableSubHeader" },
                  { text: "AFP", style: "tableSubHeader" },
                  { text: "Salud", style: "tableSubHeader" },
                ],
                [
                  money(contractSnapshot.base_salary),
                  contractSnapshot.weekly_hours ?? "-",
                  profileSnapshot.afp_name || "-",
                  profileSnapshot.health_institution_name || "-",
                ],
              ],
            },
            layout: "lightHorizontalLines",
            margin: [0, 0, 0, 8],
          },
          this.payrollLineTable(payroll, "earning", "Haberes"),
          this.payrollLineTable(payroll, "deduction", "Descuentos"),
          this.payrollLineTable(payroll, "employer_contribution", "Aportes empleador"),
          {
            table: {
              widths: ["*", "auto"],
              body: [
                ["Total haberes", { text: money(payroll.gross_total), alignment: "right" }],
                ["Total descuentos", { text: money(payroll.total_deductions), alignment: "right" }],
                [{ text: "Líquido a pago", bold: true }, { text: money(payroll.net_amount), alignment: "right", bold: true }],
                ["Costo total", { text: money(payroll.total_cost), alignment: "right" }],
              ],
            },
            layout: "lightHorizontalLines",
            margin: [0, 4, 0, 8],
          },
          {
            text: "Documento generado desde snapshot histórico de la liquidación. Cambios posteriores en parámetros, AFP, salud, contrato o conceptos no recalculan este resultado.",
            style: "note",
          }
        );
      });

      return {
        pageSize: "A4",
        pageMargins: [32, 36, 32, 36],
        content,
        defaultStyle: { fontSize: 8 },
        styles: {
          title: { fontSize: 14, bold: true },
          subtitle: { fontSize: 8, color: "#6c757d", margin: [0, 2, 0, 8] },
          tableHeader: { bold: true, fillColor: "#eef2f7", margin: [0, 3, 0, 3] },
          tableSubHeader: { bold: true, color: "#495057" },
          note: { fontSize: 7, color: "#6c757d", italics: true },
        },
        footer: (currentPage, pageCount) => ({
          text: `Página ${currentPage} de ${pageCount}`,
          alignment: "center",
          fontSize: 7,
          color: "#6c757d",
        }),
      };
    },
    async downloadCertificatePdf(item) {
      const pdfMake = getPdfMake();
      const staff = item.staff || {};
      const docDefinition = {
        pageSize: "A4",
        pageMargins: [48, 54, 48, 54],
        content: [
          { text: "Certificado laboral", style: "title", alignment: "center" },
          { text: `Folio: ${item.folio || item.id}`, alignment: "right", margin: [0, 18, 0, 18] },
          {
            text: [
              "Se certifica que ",
              { text: staff.full_name || "-", bold: true },
              `, RUT ${staff.rut || "-"}, mantiene registro laboral en la institución`,
              staff.cargo?.name ? ` desempeñándose como ${staff.cargo.name}` : "",
              staff.start_date ? ` desde el ${shortDate(staff.start_date)}` : "",
              ".",
            ],
            margin: [0, 0, 0, 14],
            lineHeight: 1.35,
          },
          { text: `Tipo de certificado: ${item.certificate_type || "-"}`, margin: [0, 0, 0, 6] },
          { text: `Finalidad: ${item.purpose || "Fines que estime conveniente"}`, margin: [0, 0, 0, 6] },
          { text: `Fecha de emisión: ${shortDate(item.issued_at) || shortDate(new Date().toISOString())}`, margin: [0, 0, 0, 34] },
          { text: item.signed_by_user_id ? `Emitido por usuario ID ${item.signed_by_user_id}` : "Emitido por RR.HH.", alignment: "center", margin: [0, 34, 0, 0] },
        ],
        styles: {
          title: { fontSize: 16, bold: true },
        },
      };
      pdfMake.createPdf(docDefinition).download(`certificado_laboral_${item.folio || item.id}.pdf`);
    },
    getValue(item, path) {
      return path.split(".").reduce((value, key) => (value == null ? null : value[key]), item);
    },
    formatCell(item, column) {
      const value = this.getValue(item, column.key);
      if (column.format === "currency") return money(value);
      if (column.format === "date") return shortDate(value);
      if (column.format === "boolean") return value ? "Sí" : "No";
      return value ?? "-";
    },
    badgeVariant(value) {
      return statusBadge[value] || "secondary";
    },
    statusLabel(value) {
      if (!value) return "-";
      if (statusLabels[value]) return statusLabels[value];
      const text = String(value).replace(/_/g, " ");
      return text.charAt(0).toUpperCase() + text.slice(1);
    },
  },
};
</script>

<template>
  <Layout>
    <PageHeader :title="pageTitle" page-title="Remuneraciones" />

    <div class="d-flex flex-column gap-3 remuneration-shell">
      <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

      <BCard v-if="isLoading" class="border-0 shadow-sm">
        <LoadingState message="Cargando remuneraciones..." compact />
      </BCard>

      <template v-else>
        <section class="remuneration-heading">
          <div>
            <div class="remuneration-eyebrow"><i class="bx bx-wallet"></i> Gestión de personas</div>
            <h2>{{ pageTitle }}</h2>
            <p>{{ activePanel.help }}</p>
          </div>
          <div class="remuneration-heading-status">
            <span class="status-dot"></span>
            Información actualizada
          </div>
        </section>

        <div v-if="activePanel.kind === 'dashboard' || activePanel.kind === 'reports'" class="d-flex flex-column gap-3">
          <div class="row g-3">
            <div class="col-md-3">
              <BCard class="metric-card metric-card--primary border-0">
                <div class="metric-icon"><i class="bx bx-receipt"></i></div>
                <div><span>Liquidaciones</span><strong>{{ dashboard?.metrics?.payrolls || 0 }}</strong></div>
              </BCard>
            </div>
            <div class="col-md-3">
              <BCard class="metric-card metric-card--info border-0">
                <div class="metric-icon"><i class="bx bx-trending-up"></i></div>
                <div><span>Total haberes</span><strong>{{ money(dashboard?.metrics?.gross_total) }}</strong></div>
              </BCard>
            </div>
            <div class="col-md-3">
              <BCard class="metric-card metric-card--success border-0">
                <div class="metric-icon"><i class="bx bx-money"></i></div>
                <div><span>Líquido a pago</span><strong>{{ money(dashboard?.metrics?.net_total) }}</strong></div>
              </BCard>
            </div>
            <div class="col-md-3">
              <BCard class="metric-card metric-card--warning border-0">
                <div class="metric-icon"><i class="bx bx-building-house"></i></div>
                <div><span>Costo empleador</span><strong>{{ money(dashboard?.metrics?.total_cost) }}</strong></div>
              </BCard>
            </div>
          </div>

          <BCard class="border-0 shadow-sm remuneration-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="mb-0">Últimas liquidaciones</h5>
              <BButton v-if="activePanel.kind === 'reports'" size="sm" variant="outline-primary" @click="exportReport">
                <i class="bx bx-download me-1"></i> Exportar
              </BButton>
            </div>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead>
                  <tr>
                    <th>Código</th>
                    <th>Funcionario</th>
                    <th>Período</th>
                    <th>Líquido</th>
                    <th>Estado</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in dashboard?.recent || []" :key="item.id">
                    <td>{{ item.code }}</td>
                    <td>{{ item.staff?.full_name }}</td>
                    <td>{{ item.period?.name }}</td>
                    <td>{{ money(item.net_amount) }}</td>
                    <td><span class="badge" :class="`bg-${badgeVariant(item.status)}`">{{ statusLabel(item.status) }}</span></td>
                  </tr>
                  <tr v-if="!dashboard?.recent?.length">
                    <td colspan="5" class="text-center text-muted py-4">Sin liquidaciones registradas.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>

          <div class="row g-3">
            <div class="col-xl-7">
              <BCard class="border-0 shadow-sm h-100 remuneration-card">
                <div class="card-heading"><div><span>Evolución</span><h5>Tendencia mensual</h5></div><i class="bx bx-line-chart"></i></div>
                <apexchart v-if="dashboard?.analytics?.trend?.length" type="area" height="310" :options="dashboardTrendOptions()" :series="dashboardTrendSeries" />
                <div v-else class="empty-state"><i class="bx bx-bar-chart-alt-2"></i><strong>Sin datos históricos</strong><span>Los gráficos aparecerán al procesar liquidaciones.</span></div>
              </BCard>
            </div>
            <div class="col-xl-5">
              <BCard class="border-0 shadow-sm h-100 remuneration-card">
                <div class="card-heading"><div><span>Composición</span><h5>Dotación por tipo</h5></div><i class="bx bx-group"></i></div>
                <apexchart v-if="dashboardTypeSeries.length" type="donut" height="310" :options="dashboardTypeOptions()" :series="dashboardTypeSeries" />
                <div v-else class="empty-state"><i class="bx bx-group"></i><strong>Sin dotación disponible</strong></div>
              </BCard>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-xl-6">
              <BCard class="border-0 shadow-sm h-100">
                <h5 class="mb-3">Conceptos principales</h5>
                <div class="table-responsive">
                  <table class="table table-sm align-middle">
                    <thead>
                      <tr>
                        <th>Código</th>
                        <th>Concepto</th>
                        <th>Tipo</th>
                        <th>Monto</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="item in dashboard?.analytics?.top_concepts || []" :key="`${item.line_type}-${item.code}`">
                        <td>{{ item.code }}</td>
                        <td>{{ item.name }}</td>
                        <td><span class="badge bg-secondary">{{ item.line_type }}</span></td>
                        <td>{{ money(item.amount) }}</td>
                      </tr>
                      <tr v-if="!dashboard?.analytics?.top_concepts?.length">
                        <td colspan="4" class="text-center text-muted py-4">Sin conceptos.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </BCard>
            </div>
            <div class="col-xl-6">
              <BCard class="border-0 shadow-sm h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <h5 class="mb-0">Variación líquida</h5>
                  <span v-if="dashboard?.analytics?.period_movement?.previous_period" class="text-muted small">
                    vs {{ dashboard.analytics.period_movement.previous_period.name }}
                  </span>
                </div>
                <div class="d-flex gap-3 mb-3">
                  <div><span class="text-muted d-block small">Altas</span><strong>{{ dashboard?.analytics?.period_movement?.new_count || 0 }}</strong></div>
                  <div><span class="text-muted d-block small">Bajas</span><strong>{{ dashboard?.analytics?.period_movement?.missing_count || 0 }}</strong></div>
                </div>
                <div class="table-responsive">
                  <table class="table table-sm align-middle">
                    <thead>
                      <tr>
                        <th>Funcionario</th>
                        <th>Anterior</th>
                        <th>Actual</th>
                        <th>Delta</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="item in dashboard?.analytics?.period_movement?.largest_net_changes || []" :key="item.staff_id">
                        <td>{{ item.staff }}</td>
                        <td>{{ money(item.previous_net) }}</td>
                        <td>{{ money(item.current_net) }}</td>
                        <td>{{ money(item.delta_net) }}</td>
                      </tr>
                      <tr v-if="!dashboard?.analytics?.period_movement?.largest_net_changes?.length">
                        <td colspan="4" class="text-center text-muted py-4">Sin variaciones comparables.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </BCard>
            </div>
          </div>
        </div>

        <BookAnalyticsPanel v-if="activePanel.kind === 'book-analytics'" />

        <div v-if="activePanel.kind === 'imports'" class="d-flex flex-column gap-3 import-view">
          <BCard class="border-0 shadow-sm import-uploader-card">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
              <div>
                <h5 class="mb-1">Nuevo libro</h5>
                <div class="text-muted small">{{ importFile?.name || "Sin archivo seleccionado" }}</div>
              </div>
              <div class="d-flex flex-wrap align-items-center gap-3">
                <div class="form-check form-switch mb-0">
                  <input v-model="importReplace" class="form-check-input" type="checkbox" />
                  <label class="form-check-label">Reemplazar</label>
                </div>
                <div class="d-flex gap-2">
                  <BButton variant="outline-primary" :disabled="!importFile || importLoading" @click="previewImport">
                    <i class="bx bx-search me-1"></i> {{ importLoading ? "Revisando..." : "Vista previa" }}
                  </BButton>
                  <BButton variant="primary" :disabled="!importPreview || importPreview.summary?.error_count > 0 || importCommitting" @click="commitImport">
                    <i class="bx bx-upload me-1"></i> {{ importCommitting ? "Importando..." : "Importar" }}
                  </BButton>
                </div>
              </div>
            </div>
            <div class="row g-3 align-items-end">
              <div class="col-xl-7 col-lg-8">
                <label class="form-label">Libro XLSX</label>
                <input class="form-control" type="file" accept=".xlsx" @change="handleImportFile" />
              </div>
            </div>
          </BCard>

          <BCard v-if="importPreview" class="border-0 shadow-sm">
            <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
              <div>
                <h5 class="mb-1">{{ importPreview.period?.name }}</h5>
                <div class="text-muted small">{{ importPreview.file?.name }}</div>
              </div>
              <div class="d-flex flex-wrap gap-3">
                <div><span class="text-muted d-block small">Filas</span><strong>{{ importPreview.summary?.row_count || 0 }}</strong></div>
                <div><span class="text-muted d-block small">Calzadas</span><strong>{{ importPreview.summary?.matched_count || 0 }}</strong></div>
                <div><span class="text-muted d-block small">Sin match</span><strong>{{ importPreview.summary?.unmatched_count || 0 }}</strong></div>
                <div><span class="text-muted d-block small">Errores</span><strong>{{ importPreview.summary?.error_count || 0 }}</strong></div>
                <div><span class="text-muted d-block small">Alertas</span><strong>{{ importPreview.summary?.warning_count || 0 }}</strong></div>
                <div><span class="text-muted d-block small">Líquido</span><strong>{{ money(importPreview.summary?.net_total) }}</strong></div>
              </div>
            </div>

            <BAlert v-if="importPreview.file?.already_imported" show variant="warning">
              Este archivo ya fue importado para el período detectado.
            </BAlert>

            <div v-if="importPreview.errors?.length" class="table-responsive mb-3">
              <table class="table table-sm table-danger align-middle">
                <thead>
                  <tr>
                    <th>Fila</th>
                    <th>RUT</th>
                    <th>Error</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(error, index) in importPreview.errors" :key="index">
                    <td>{{ error.row_number }}</td>
                    <td>{{ error.rut }}</td>
                    <td>{{ error.message }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-if="importPreview.warnings?.length" class="table-responsive mb-3">
              <table class="table table-sm table-warning align-middle">
                <thead>
                  <tr>
                    <th>Fila</th>
                    <th>RUT</th>
                    <th>Alerta</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(warning, index) in importPreview.warnings" :key="index">
                    <td>{{ warning.row_number }}</td>
                    <td>{{ warning.rut }}</td>
                    <td>{{ warning.message }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="table-responsive">
              <table class="table table-sm align-middle remuneration-table">
                <thead>
                  <tr>
                    <th>Fila</th>
                    <th>RUT</th>
                    <th>Funcionario libro</th>
                    <th>Funcionario sistema</th>
                    <th>Tipo</th>
                    <th>Haberes</th>
                    <th>Descuentos</th>
                    <th>Líquido</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in importPreview.rows || []" :key="`${row.row_number}-${row.rut}`" :class="{ 'table-danger': row.errors?.length, 'table-warning': !row.errors?.length && row.warnings?.length }">
                    <td>{{ row.row_number }}</td>
                    <td>{{ row.rut }}</td>
                    <td>{{ row.employee_name }}</td>
                    <td>{{ row.matched_staff || "-" }}</td>
                    <td>{{ row.employee_type }}</td>
                    <td>{{ money(row.gross_total) }}</td>
                    <td>{{ money(row.total_deductions) }}</td>
                    <td>{{ money(row.net_amount) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>

          <BCard class="border-0 shadow-sm import-history-card">
            <div class="mb-3">
              <div>
                <h5 class="mb-1">Historial de importaciones</h5>
                <div class="text-muted small">{{ activeRows.length }} importaciones registradas</div>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-hover table-sm align-middle remuneration-table">
                <thead>
                  <tr>
                    <th v-for="column in activePanel.columns" :key="column.key">{{ column.label }}</th>
                    <th class="text-end">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in activeRows" :key="item.id">
                    <td v-for="column in activePanel.columns" :key="column.key">
                      <span v-if="column.format === 'badge'" class="badge" :class="`bg-${badgeVariant(getValue(item, column.key))}`">
                        {{ statusLabel(getValue(item, column.key)) }}
                      </span>
                      <span v-else>{{ formatCell(item, column) }}</span>
                    </td>
                    <td class="text-end">
                      <BButton size="sm" variant="outline-primary" title="Ver libro importado" @click="openImportBookModal(item)">
                        <i class="bx bx-show"></i>
                      </BButton>
                    </td>
                  </tr>
                  <tr v-if="activeRows.length === 0">
                    <td :colspan="activePanel.columns.length + 1" class="text-center text-muted py-4">Sin importaciones registradas.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>

        <BCard v-if="activePanel.kind === 'resource'" class="border-0 shadow-sm remuneration-card resource-card">
          <div class="resource-toolbar">
            <div>
              <h5 class="mb-1">Listado de {{ pageTitle.toLowerCase() }}</h5>
              <span>{{ activeRows.length }} registros encontrados</span>
            </div>
            <div class="input-group input-group-sm remuneration-search">
              <span class="input-group-text"><i class="bx bx-search"></i></span>
              <input v-model="search" type="search" class="form-control" :placeholder="`Buscar en ${pageTitle.toLowerCase()}...`" @keyup.enter="loadActive" />
              <BButton variant="outline-secondary" @click="loadActive">Filtrar</BButton>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <BButton v-if="activeKey === 'payrolls'" size="sm" variant="primary" @click="calculationModalVisible = true">
                <i class="bx bx-calculator me-1"></i> Calcular
              </BButton>
              <BButton v-if="activeKey === 'payrolls'" size="sm" variant="outline-danger" @click="openPayrollPdfModal('complete')">
                <i class="bx bxs-file-pdf me-1"></i> PDF completo
              </BButton>
              <BButton v-if="activeKey === 'payrolls'" size="sm" variant="outline-danger" @click="openPayrollPdfModal('staff')">
                <i class="bx bxs-file-pdf me-1"></i> PDF trabajador
              </BButton>
              <BButton v-if="canMutateActive" size="sm" variant="primary" @click="openCreate">
                <i class="bx bx-plus me-1"></i> Nuevo
              </BButton>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-hover table-sm align-middle remuneration-table">
              <thead>
                <tr>
                  <th v-for="column in activePanel.columns" :key="column.key">{{ column.label }}</th>
                  <th class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in activeRows" :key="item.id">
                  <td v-for="column in activePanel.columns" :key="column.key">
                    <span v-if="column.format === 'badge'" class="badge" :class="`bg-${badgeVariant(getValue(item, column.key))}`">
                      {{ statusLabel(getValue(item, column.key)) }}
                    </span>
                    <span v-else>{{ formatCell(item, column) }}</span>
                  </td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <BButton v-if="canMutateActive" variant="outline-secondary" title="Editar" @click="openEdit(item)">
                        <i class="bx bx-edit"></i>
                      </BButton>
                      <BButton v-if="canMutateActive" variant="outline-danger" title="Eliminar" @click="deleteRecord(item)">
                        <i class="bx bx-trash"></i>
                      </BButton>
                      <BButton v-if="activeKey === 'periods' && item.status !== 'cerrado'" variant="outline-dark" title="Cerrar" @click="periodAction('close', item)">
                        <i class="bx bx-lock"></i>
                      </BButton>
                      <BButton v-if="activeKey === 'periods' && item.status === 'cerrado'" variant="outline-warning" title="Reabrir" @click="periodAction('reopen', item)">
                        <i class="bx bx-lock-open"></i>
                      </BButton>
                      <BButton v-if="activeKey === 'payrolls'" variant="outline-primary" title="Aprobar" @click="payrollAction('approve', item)">
                        <i class="bx bx-check"></i>
                      </BButton>
                      <BButton v-if="activeKey === 'payrolls'" variant="outline-danger" title="PDF" @click="downloadPayrollPdf(item)">
                        <i class="bx bxs-file-pdf"></i>
                      </BButton>
                      <BButton v-if="activeKey === 'payrolls'" variant="outline-warning" title="Observar" @click="observePayroll(item)">
                        <i class="bx bx-message-square-error"></i>
                      </BButton>
                      <BButton v-if="activeKey === 'payrolls'" variant="outline-success" title="Pagar" @click="payPayroll(item)">
                        <i class="bx bx-credit-card"></i>
                      </BButton>
                      <BButton v-if="activeKey === 'payrolls'" variant="outline-dark" title="Centralizar" @click="payrollAction('centralize', item)">
                        <i class="bx bx-transfer"></i>
                      </BButton>
                      <BButton v-if="activeKey === 'payrolls'" variant="outline-danger" title="Anular" @click="payrollAction('annul', item)">
                        <i class="bx bx-x"></i>
                      </BButton>
                      <BButton v-if="activeKey === 'labor-certificates'" variant="outline-danger" title="PDF certificado" @click="downloadCertificatePdf(item)">
                        <i class="bx bxs-file-pdf"></i>
                      </BButton>
                    </div>
                  </td>
                </tr>
                <tr v-if="activeRows.length === 0">
                  <td :colspan="activePanel.columns.length + 1" class="text-center text-muted py-4">Sin registros.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </template>
    </div>

    <BModal
      v-model="importBookModalVisible"
      :title="`Libro importado${selectedImport?.period?.name ? ' · ' + selectedImport.period.name : ''}`"
      hide-footer
      size="xl"
      scrollable
      modal-class="import-book-modal"
    >
      <LoadingState v-if="importBookLoading" message="Cargando libro importado..." />

      <div v-else class="import-book-modal-shell">
        <div class="import-book-modal-summary">
          <div class="min-w-0">
            <div class="text-muted small mb-1">{{ selectedImport?.period?.name || selectedImport?.book_period || "-" }}</div>
            <h5 class="mb-1 text-truncate">{{ selectedImport?.original_filename || "-" }}</h5>
            <div class="text-muted small">{{ importBookSortedRows.length }} filas importadas</div>
          </div>
          <div class="import-book-stats">
            <div>
              <span>Haberes</span>
              <strong>{{ importBookEarningsColumns.length }}</strong>
            </div>
            <div>
              <span>Descuentos</span>
              <strong>{{ importBookDeductionsColumns.length }}</strong>
            </div>
          </div>
          <BButton variant="danger" size="sm" :disabled="importBookPdfExporting || importBookSortedRows.length === 0" @click="exportImportBookPdf">
            <i class="bx bxs-file-pdf me-1"></i> {{ importBookPdfExporting ? "Generando..." : "PDF por hojas" }}
          </BButton>
        </div>

        <BTabs pills nav-class="import-book-tabs" content-class="pt-3">
          <BTab title="Haberes" active>
            <div class="import-book-table-wrap">
              <table class="table table-bordered table-sm align-middle import-book-table">
                <thead>
                  <tr>
                    <th v-for="column in importBookEarningsColumns" :key="`earning-${column.column}`">
                      {{ importBookColumnLabel(column) }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in importBookSortedRows" :key="`earnings-row-${row.id}`">
                    <td v-for="column in importBookEarningsColumns" :key="`earnings-${row.id}-${column.column}`">
                      {{ importBookCellValue(importBookRawCell(row, "raw_earnings_columns", column)) }}
                    </td>
                  </tr>
                  <tr v-if="importBookSortedRows.length === 0 || importBookEarningsColumns.length === 0">
                    <td :colspan="Math.max(importBookEarningsColumns.length, 1)" class="text-center text-muted py-4">Sin haberes importados.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BTab>

          <BTab title="Descuentos">
            <div class="import-book-table-wrap">
              <table class="table table-bordered table-sm align-middle import-book-table">
                <thead>
                  <tr>
                    <th v-for="column in importBookDeductionsColumns" :key="`deduction-${column.column}`">
                      {{ importBookColumnLabel(column) }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in importBookSortedRows" :key="`deductions-row-${row.id}`">
                    <td v-for="column in importBookDeductionsColumns" :key="`deductions-${row.id}-${column.column}`">
                      {{ importBookCellValue(importBookRawCell(row, "raw_deductions_columns", column)) }}
                    </td>
                  </tr>
                  <tr v-if="importBookSortedRows.length === 0 || importBookDeductionsColumns.length === 0">
                    <td :colspan="Math.max(importBookDeductionsColumns.length, 1)" class="text-center text-muted py-4">Sin descuentos importados.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BTab>
        </BTabs>
      </div>
    </BModal>

    <BModal v-model="modalVisible" :title="`${editingId ? 'Editar' : 'Nuevo'} · ${pageTitle}`" hide-footer size="lg" modal-class="remuneration-form-modal">
      <form class="row g-3" @submit.prevent="saveRecord">
        <div v-for="field in activePanel.fields || []" :key="field.key" class="col-md-6" :class="{ 'col-md-12': ['textarea', 'json'].includes(field.type) }">
          <label class="form-label">{{ field.label }}</label>
          <select v-if="field.type === 'select'" v-model="form[field.key]" class="form-select">
            <option value="">Seleccione</option>
            <option v-for="option in optionItems(field)" :key="optionValue(option)" :value="optionValue(option)">
              {{ optionLabel(option, field) }}
            </option>
          </select>
          <textarea v-else-if="field.type === 'textarea' || field.type === 'json'" v-model="form[field.key]" class="form-control" rows="4"></textarea>
          <div v-else-if="field.type === 'checkbox'" class="form-check form-switch mt-2">
            <input v-model="form[field.key]" class="form-check-input" type="checkbox" />
          </div>
          <input v-else v-model="form[field.key]" :type="field.type || 'text'" class="form-control" />
        </div>
        <div class="col-12 d-flex justify-content-end gap-2">
          <BButton variant="outline-secondary" @click="modalVisible = false">Cancelar</BButton>
          <BButton variant="primary" type="submit">Guardar</BButton>
        </div>
      </form>
    </BModal>

    <BModal v-model="calculationModalVisible" title="Calcular liquidación" hide-footer modal-class="remuneration-form-modal">
      <form class="row g-3" @submit.prevent="calculatePayroll">
        <div class="col-12">
          <label class="form-label">Período</label>
          <select v-model="calculationForm.period_id" class="form-select" required>
            <option value="">Seleccione</option>
            <option v-for="period in catalogs.data?.periods || []" :key="period.id" :value="period.id">{{ period.name }}</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">Funcionario</label>
          <select v-model="calculationForm.staff_id" class="form-select" required>
            <option value="">Seleccione</option>
            <option v-for="staff in catalogs.data?.staff || []" :key="staff.id" :value="staff.id">{{ staff.full_name }}</option>
          </select>
        </div>
        <div class="col-8">
          <label class="form-label">Tipo</label>
          <input v-model="calculationForm.payroll_type" class="form-control" />
        </div>
        <div class="col-4 d-flex align-items-end">
          <div class="form-check form-switch">
            <input v-model="calculationForm.force" class="form-check-input" type="checkbox" />
            <label class="form-check-label">Forzar</label>
          </div>
        </div>
        <div class="col-12 d-flex justify-content-end gap-2">
          <BButton variant="outline-secondary" @click="calculationModalVisible = false">Cancelar</BButton>
          <BButton variant="primary" type="submit">Calcular</BButton>
        </div>
      </form>
    </BModal>

    <BModal v-model="pdfModalVisible" :title="pdfMode === 'staff' ? 'PDF liquidaciones por trabajador' : 'PDF completo de liquidaciones'" hide-footer modal-class="remuneration-form-modal">
      <form class="row g-3" @submit.prevent="exportPayrollPdfFromModal">
        <div v-if="pdfMode === 'staff'" class="col-12">
          <label class="form-label">Funcionario</label>
          <select v-model="pdfForm.staff_id" class="form-select" required>
            <option value="">Seleccione</option>
            <option v-for="staff in catalogs.data?.staff || []" :key="staff.id" :value="staff.id">{{ staff.full_name }}</option>
          </select>
        </div>
        <div v-if="pdfMode === 'complete'" class="col-12">
          <label class="form-label">Período</label>
          <select v-model="pdfForm.period_id" class="form-select">
            <option value="">Todos</option>
            <option v-for="period in catalogs.data?.periods || []" :key="period.id" :value="period.id">{{ period.name }}</option>
          </select>
        </div>
        <template v-if="pdfMode === 'staff'">
          <div class="col-md-6">
            <label class="form-label">Desde</label>
            <select v-model="pdfForm.from_period_id" class="form-select">
              <option value="">Sin inicio</option>
              <option v-for="period in catalogs.data?.periods || []" :key="period.id" :value="period.id">{{ period.name }}</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Hasta</label>
            <select v-model="pdfForm.to_period_id" class="form-select">
              <option value="">Sin término</option>
              <option v-for="period in catalogs.data?.periods || []" :key="period.id" :value="period.id">{{ period.name }}</option>
            </select>
          </div>
        </template>
        <div class="col-md-8">
          <label class="form-label">Tipo liquidación</label>
          <input v-model="pdfForm.payroll_type" class="form-control" />
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <div class="form-check form-switch">
            <input v-model="pdfForm.include_annulled" class="form-check-input" type="checkbox" />
            <label class="form-check-label">Incluir anuladas</label>
          </div>
        </div>
        <div class="col-12 d-flex justify-content-end gap-2">
          <BButton variant="outline-secondary" @click="pdfModalVisible = false">Cancelar</BButton>
          <BButton variant="danger" type="submit" :disabled="pdfExporting">
            {{ pdfExporting ? "Generando..." : "Generar PDF" }}
          </BButton>
        </div>
      </form>
    </BModal>
  </Layout>
</template>

<style scoped>
.remuneration-shell {
  min-height: 520px;
  --rem-primary: #556ee6;
  --rem-border: #e6eaf2;
  --rem-text: #293042;
  --rem-muted: #74788d;
}

.remuneration-search {
  max-width: 460px;
}

.remuneration-heading {
  align-items: center;
  background: linear-gradient(125deg, #ffffff 0%, #f4f6ff 100%);
  border: 1px solid var(--rem-border);
  border-radius: 14px;
  display: flex;
  justify-content: space-between;
  overflow: hidden;
  padding: 1.25rem 1.4rem;
  position: relative;
}

.remuneration-heading::after {
  background: rgba(85, 110, 230, 0.07);
  border-radius: 50%;
  content: "";
  height: 150px;
  position: absolute;
  right: -45px;
  top: -75px;
  width: 150px;
}

.remuneration-eyebrow {
  align-items: center;
  color: var(--rem-primary);
  display: flex;
  font-size: 0.7rem;
  font-weight: 800;
  gap: 0.35rem;
  letter-spacing: 0.08em;
  margin-bottom: 0.35rem;
  text-transform: uppercase;
}

.remuneration-heading h2 { color: var(--rem-text); font-size: 1.35rem; margin: 0 0 0.25rem; }
.remuneration-heading p { color: var(--rem-muted); margin: 0; }
.remuneration-heading-status { align-items: center; color: var(--rem-muted); display: flex; font-size: 0.78rem; gap: 0.45rem; padding-right: 1.25rem; white-space: nowrap; }
.status-dot { background: #34c38f; border: 3px solid rgba(52, 195, 143, 0.18); border-radius: 50%; height: 10px; width: 10px; }

:deep(.remuneration-card),
:deep(.metric-card) { border-radius: 14px; }

:deep(.metric-card .card-body) {
  align-items: center;
  display: flex;
  gap: 0.9rem;
  min-height: 104px;
  padding: 1rem;
}

.metric-card { box-shadow: 0 5px 18px rgba(42, 48, 66, 0.07); }
.metric-card .metric-icon { align-items: center; border-radius: 12px; display: flex; flex: 0 0 44px; font-size: 1.35rem; height: 44px; justify-content: center; }
.metric-card--primary .metric-icon { background: rgba(85,110,230,.12); color: #556ee6; }
.metric-card--info .metric-icon { background: rgba(80,165,241,.12); color: #50a5f1; }
.metric-card--success .metric-icon { background: rgba(52,195,143,.12); color: #34c38f; }
.metric-card--warning .metric-icon { background: rgba(241,180,76,.14); color: #d99520; }

.metric-card span {
  display: block;
  color: #6c757d;
  font-size: 0.82rem;
}

.metric-card strong {
  display: block;
  color: var(--rem-text);
  font-size: 1.18rem;
  line-height: 1.4;
}

.card-heading { align-items: center; display: flex; justify-content: space-between; margin-bottom: 0.75rem; }
.card-heading span { color: var(--rem-muted); font-size: .68rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; }
.card-heading h5 { margin: .15rem 0 0; }
.card-heading > i { align-items: center; background: #f2f4ff; border-radius: 10px; color: var(--rem-primary); display: flex; font-size: 1.25rem; height: 38px; justify-content: center; width: 38px; }
.empty-state { align-items: center; color: var(--rem-muted); display: flex; flex-direction: column; justify-content: center; min-height: 280px; text-align: center; }
.empty-state i { color: #c8cfdd; font-size: 3rem; margin-bottom: .65rem; }
.empty-state strong { color: var(--rem-text); }
.empty-state span { font-size: .8rem; margin-top: .25rem; }

.resource-toolbar { align-items: center; display: grid; gap: 1rem; grid-template-columns: minmax(180px, 1fr) minmax(260px, 460px) auto; margin-bottom: 1rem; }
.resource-toolbar > div:first-child > span { color: var(--rem-muted); font-size: .78rem; }
.remuneration-search .input-group-text { background: #f7f8fb; border-color: var(--rem-border); color: var(--rem-muted); }
.remuneration-search .form-control { border-color: var(--rem-border); }

.remuneration-table th,
.remuneration-table td {
  white-space: nowrap;
}

.remuneration-table { border-collapse: separate; border-spacing: 0; margin: 0; }
.remuneration-table thead th { background: #f7f8fb; border-bottom: 1px solid var(--rem-border); color: #62697a; font-size: .69rem; font-weight: 800; letter-spacing: .035em; padding: .8rem .75rem; text-transform: uppercase; }
.remuneration-table tbody td { border-color: #eef1f6; color: #3d4352; padding: .78rem .75rem; }
.remuneration-table tbody tr { transition: background-color .15s ease; }
.remuneration-table tbody tr:hover td { background: #f8f9ff; }
.remuneration-table .badge { border-radius: 999px; font-size: .68rem; font-weight: 700; padding: .38rem .62rem; }
.remuneration-table .btn-group { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(42,48,66,.06); }

:deep(.remuneration-form-modal .modal-content) { border: 0; border-radius: 16px; box-shadow: 0 18px 55px rgba(31,38,56,.2); overflow: hidden; }
:deep(.remuneration-form-modal .modal-header) { background: linear-gradient(120deg, #556ee6, #6f7fe8); border: 0; color: #fff; padding: 1.15rem 1.3rem; }
:deep(.remuneration-form-modal .modal-title) { color: #fff; font-size: 1.05rem; }
:deep(.remuneration-form-modal .btn-close) { filter: brightness(0) invert(1); opacity: .8; }
:deep(.remuneration-form-modal .modal-body) { background: #fbfcfe; padding: 1.3rem; }
:deep(.remuneration-form-modal .form-label) { color: #5b6272; font-size: .72rem; font-weight: 750; margin-bottom: .35rem; }
:deep(.remuneration-form-modal .form-control), :deep(.remuneration-form-modal .form-select) { border-color: #dfe4ed; border-radius: 8px; min-height: 40px; }
:deep(.remuneration-form-modal .form-control:focus), :deep(.remuneration-form-modal .form-select:focus) { border-color: #8091ed; box-shadow: 0 0 0 3px rgba(85,110,230,.12); }

.import-view .form-label {
  color: #5f6678;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
}

.import-uploader-card,
.import-history-card {
  border-radius: 10px;
}

.import-history-card .remuneration-table th {
  color: #6b7280;
  font-size: 0.72rem;
  letter-spacing: 0;
  text-transform: uppercase;
}

.import-history-card .remuneration-table td {
  padding-top: 0.85rem;
  padding-bottom: 0.85rem;
}

.import-history-card .btn {
  align-items: center;
  display: inline-flex;
  height: 34px;
  justify-content: center;
  width: 34px;
}

:deep(.import-book-modal .modal-xl) {
  max-width: min(1680px, calc(100vw - 56px));
}

:deep(.import-book-modal .modal-body) {
  padding: 1.25rem;
}

.import-book-modal-shell {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.import-book-modal-summary {
  align-items: center;
  background: #f8fafc;
  border: 1px solid #e9edf3;
  border-radius: 8px;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
  padding: 1rem;
}

.import-book-stats {
  display: flex;
  gap: 0.65rem;
  margin-left: auto;
}

.import-book-stats div {
  background: #ffffff;
  border: 1px solid #e9edf3;
  border-radius: 8px;
  min-width: 104px;
  padding: 0.55rem 0.75rem;
}

.import-book-stats span {
  color: #6c757d;
  display: block;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
}

.import-book-stats strong {
  color: #343a40;
  display: block;
  font-size: 1rem;
  line-height: 1.25;
}

:deep(.import-book-tabs) {
  border-bottom: 1px solid #e9edf3;
  gap: 0.35rem;
  padding-bottom: 0.75rem;
}

:deep(.import-book-tabs .nav-link) {
  border-radius: 999px;
  color: #5f6678;
  font-weight: 700;
  padding: 0.45rem 0.95rem;
}

:deep(.import-book-tabs .nav-link.active) {
  background: #556ee6;
  color: #ffffff;
}

.import-book-table-wrap {
  background: #ffffff;
  max-height: calc(100vh - 350px);
  min-height: 420px;
  overflow: auto;
  border: 1px solid #e9edf3;
  border-radius: 8px;
}

.import-book-table {
  border-color: #edf1f7;
  margin-bottom: 0;
  font-size: 0.76rem;
}

.import-book-table th {
  position: sticky;
  top: 0;
  z-index: 1;
  min-width: 92px;
  background: #f5f7fb;
  color: #5f6678;
  font-size: 0.68rem;
  font-weight: 800;
  line-height: 1.15;
  padding: 0.55rem 0.65rem;
  white-space: normal;
  vertical-align: middle;
}

.import-book-table td {
  background: #ffffff;
  border-color: #edf1f7;
  padding: 0.5rem 0.65rem;
  white-space: nowrap;
}

.import-book-table tbody tr:nth-child(even) td {
  background: #fbfcfe;
}

.import-book-table th:first-child,
.import-book-table td:first-child {
  left: 0;
  min-width: 56px;
  position: sticky;
  width: 56px;
  z-index: 2;
}

.import-book-table th:nth-child(2),
.import-book-table td:nth-child(2) {
  box-shadow: 1px 0 0 #e9edf3;
  left: 56px;
  min-width: 118px;
  position: sticky;
  z-index: 2;
}

.import-book-table thead th:first-child,
.import-book-table thead th:nth-child(2) {
  background: #f5f7fb;
  z-index: 4;
}

.import-book-table tbody td:first-child,
.import-book-table tbody td:nth-child(2) {
  background: #ffffff;
}

.import-book-table tbody tr:nth-child(even) td:first-child,
.import-book-table tbody tr:nth-child(even) td:nth-child(2) {
  background: #fbfcfe;
}

@media (max-width: 767.98px) {
  .remuneration-heading { align-items: flex-start; flex-direction: column; gap: .75rem; }
  .remuneration-heading-status { padding: 0; }
  .resource-toolbar { grid-template-columns: 1fr; }
  .remuneration-search { max-width: none; }

  .import-book-modal-summary {
    align-items: stretch;
    flex-direction: column;
  }

  .import-book-stats {
    margin-left: 0;
    width: 100%;
  }

  .import-book-stats div {
    flex: 1;
  }
}
</style>
