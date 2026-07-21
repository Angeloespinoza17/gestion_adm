import axios from "axios";
import Swal from "sweetalert2";
import { getPdfMake } from "../../utils/pdfmake";

const convivenciaSwalClasses = {
  popup: "convivencia-swal",
  confirmButton: "convivencia-swal-confirm",
  cancelButton: "convivencia-swal-cancel",
};

const convivenciaTranslations = {
  open_cases: "Casos abiertos",
  closed_cases: "Casos cerrados",
  internal_derivations_pending: "Derivaciones internas pendientes",
  external_derivations_pending: "Derivaciones externas pendientes",
  pending_measures: "Medidas pendientes",
  interviews_done: "Entrevistas realizadas",
  complaints_received: "Denuncias recibidas",
  active_protocols: "Protocolos activos",
  daily_events: "Hechos registrados en bitácora",
  overdue_followups: "Seguimientos vencidos",
  cases_total: "Total de casos",
  open_cases_total: "Casos abiertos",
  complaints_total: "Total de denuncias",
  derivations_total: "Total de derivaciones",
  pending_measures_total: "Medidas pendientes",
  interviews_total: "Total de entrevistas",
  daily_logs_total: "Registros de bitácora",
  conflicts_registered: "Conflictos registrados",
  overdue_measures: "Medidas vencidas",
  tardiness: "Atrasos",
  dashboard: "Panel general",
  overview: "Resumen general",
  internal: "Interna",
  external: "Externa",
  open: "Abierto",
  closed: "Cerrado",
  pending: "Pendiente",
  active: "Activo",
  inactive: "Inactivo",
  completed: "Completado",
  cancelled: "Cancelado",
  draft: "Borrador",
  in_progress: "En proceso",
  overdue: "Vencido",
  done: "Realizado",
  received: "Recibido",
  abierto: "Abierto",
  en_analisis: "En análisis",
  en_intervencion: "En intervención",
  con_protocolo_activo: "Con protocolo activo",
  derivado: "Derivado",
  en_seguimiento: "En seguimiento",
  cerrado: "Cerrado",
  archivado: "Archivado",
  recibida: "Recibida",
  en_revision: "En revisión",
  requiere_antecedentes: "Requiere antecedentes",
  derivada_a_caso: "Derivada a caso",
  protocolo_activado: "Protocolo activado",
  descartada_fundadamente: "Descartada fundadamente",
  borrador: "Borrador",
  vigente: "Vigente",
  en_ejecucion: "En ejecución",
  finalizado: "Finalizado",
  suspendido: "Suspendido",
  ingresada: "Ingresada",
  respondida: "Respondida",
  rechazada: "Rechazada",
  asignada: "Asignada",
  en_proceso: "En proceso",
  cumplida: "Cumplida",
  incumplida: "Incumplida",
  reprogramada: "Reprogramada",
  registrado: "Registrado",
  revisado: "Revisado",
  convertido_caso: "Convertido en caso",
  convertido_derivacion: "Convertido en derivación",
  aplicado: "Aplicado",
  interpretado: "Interpretado",
  activo: "Activo",
};

function convivenciaLabelKey(value) {
  return String(value || "")
    .trim()
    .toLowerCase()
    .replace(/[\s-]+/g, "_");
}

export function translateConvivenciaLabel(value) {
  if (value === null || value === undefined || value === "") return "-";
  const original = String(value).trim();
  const translated = convivenciaTranslations[convivenciaLabelKey(original)];

  if (translated) return translated;
  if (!original.includes("_")) return original;

  return original
    .replaceAll("_", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export function formatConvivenciaError(error, fallback = "No se pudo completar la operación.") {
  const errors = error?.response?.data?.errors || null;
  return (
    (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
    error?.response?.data?.message ||
    error?.message ||
    fallback
  );
}

export function showConvivenciaSuccess(text, title = "Operación realizada") {
  return Swal.fire({ title, text, icon: "success", timer: 1800, showConfirmButton: false, customClass: convivenciaSwalClasses });
}

export function showConvivenciaError(text, title = "Error") {
  return Swal.fire({ title, text, icon: "error", confirmButtonText: "Entendido", customClass: convivenciaSwalClasses });
}

export function showConvivenciaWarning(text, title = "Advertencia") {
  return Swal.fire({ title, text, icon: "warning", confirmButtonText: "Entendido", customClass: convivenciaSwalClasses });
}

export function confirmConvivenciaAction({ title, text, confirmButtonText = "Confirmar", icon = "warning" }) {
  return Swal.fire({
    title,
    text,
    icon,
    showCancelButton: true,
    confirmButtonText,
    cancelButtonText: "Cancelar",
    reverseButtons: true,
    focusCancel: true,
    customClass: convivenciaSwalClasses,
  });
}

export function humanizeConvivenciaStatus(status) {
  if (!status) return "-";
  const translated = translateConvivenciaLabel(status);
  if (translated !== String(status).trim()) return translated;

  return String(status)
    .replaceAll("_", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export function statusVariant(status) {
  const map = {
    abierto: "warning",
    en_analisis: "info",
    en_intervencion: "primary",
    con_protocolo_activo: "danger",
    derivado: "secondary",
    en_seguimiento: "info",
    cerrado: "success",
    archivado: "dark",
    recibida: "warning",
    en_revision: "info",
    requiere_antecedentes: "secondary",
    derivada_a_caso: "primary",
    protocolo_activado: "danger",
    descartada_fundadamente: "dark",
    borrador: "secondary",
    vigente: "primary",
    en_ejecucion: "info",
    finalizado: "success",
    suspendido: "dark",
    ingresada: "warning",
    respondida: "success",
    rechazada: "danger",
    asignada: "warning",
    en_proceso: "info",
    cumplida: "success",
    incumplida: "danger",
    reprogramada: "secondary",
    registrado: "warning",
    revisado: "info",
    convertido_caso: "primary",
    convertido_derivacion: "primary",
    aplicado: "primary",
    interpretado: "success",
    activo: "danger",
  };

  return map[status] || "light";
}

export function formatConvivenciaDate(value) {
  if (!value) return "-";
  return new Date(value).toLocaleDateString("es-CL", { year: "numeric", month: "2-digit", day: "2-digit" });
}

export function formatConvivenciaDateTime(value) {
  if (!value) return "-";
  return new Date(value).toLocaleString("es-CL", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  });
}

export function toInputDateTime(value) {
  if (!value) return "";
  return String(value).replace(" ", "T").slice(0, 16);
}

export function normalizeOptions(options, includeEmpty = false, emptyLabel = "Todos") {
  const items = (options || []).map((item) => ({
    value: item.value ?? item.id,
    text: item.label ?? item.name ?? item.display_name ?? humanizeConvivenciaStatus(item.value ?? item.id),
  }));

  return includeEmpty ? [{ value: null, text: emptyLabel }].concat(items) : items;
}

export function extractChartLabels(items, key = "label") {
  return (items || []).map((item) => translateConvivenciaLabel(item?.[key]));
}

export function extractChartTotals(items, key = "total") {
  return (items || []).map((item) => Number(item?.[key] || 0));
}

export function basicApexOptions({ categories = [], horizontal = false, colors = ["#34c38f"] } = {}) {
  return {
    chart: { toolbar: { show: false }, fontFamily: "inherit" },
    colors,
    plotOptions: { bar: { horizontal, borderRadius: 6 } },
    dataLabels: { enabled: false },
    xaxis: { categories },
    legend: { show: false },
    grid: { borderColor: "#eff2f7" },
    stroke: { curve: "smooth", width: 3 },
  };
}

export async function downloadConvivenciaFile(url, fileName = null) {
  const response = await axios.get(url, { responseType: "blob" });
  const blobUrl = window.URL.createObjectURL(new Blob([response.data]));
  const link = document.createElement("a");
  link.href = blobUrl;
  link.setAttribute("download", fileName || "archivo");
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  window.URL.revokeObjectURL(blobUrl);
}

export function downloadExcelWorkbook(fileName, sections) {
  const rows = [];
  (sections || []).forEach((section) => {
    rows.push([section.title || "Sección"]);
    if (section.headers?.length) rows.push(section.headers);
    (section.rows || []).forEach((row) => rows.push(row));
    rows.push([]);
  });

  const html = `<table>${rows.map((row) => `<tr>${row.map((cell) => `<td>${cell ?? ""}</td>`).join("")}</tr>`).join("")}</table>`;
  const blob = new Blob([`\uFEFF<html><body>${html}</body></html>`], { type: "application/vnd.ms-excel;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = fileName.endsWith(".xls") ? fileName : `${fileName}.xls`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

export function downloadPdfReport(fileName, title, subtitle, sections) {
  const pdfMake = getPdfMake();
  const content = [{ text: title, style: "title" }];
  if (subtitle) content.push({ text: subtitle, style: "subtitle" });

  (sections || []).forEach((section) => {
    content.push({ text: section.title, style: "section" });
    content.push({
      table: {
        headerRows: section.headers?.length ? 1 : 0,
        body: [].concat(section.headers?.length ? [section.headers] : []).concat(section.rows || []),
      },
      layout: "lightHorizontalLines",
      margin: [0, 0, 0, 10],
    });
  });

  pdfMake.createPdf({
    content,
    styles: {
      title: { fontSize: 18, bold: true, color: "#2a3042" },
      subtitle: { fontSize: 10, color: "#74788d", margin: [0, 0, 0, 10] },
      section: { fontSize: 12, bold: true, margin: [0, 10, 0, 6] },
    },
    defaultStyle: { fontSize: 9 },
  }).download(fileName.endsWith(".pdf") ? fileName : `${fileName}.pdf`);
}
