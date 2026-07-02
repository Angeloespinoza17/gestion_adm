import axios from "axios";
import Swal from "sweetalert2";
import { getPdfMake } from "../../utils/pdfmake";

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
  return Swal.fire({ title, text, icon: "success", timer: 1800, showConfirmButton: false });
}

export function showConvivenciaError(text, title = "Error") {
  return Swal.fire({ title, text, icon: "error", confirmButtonText: "Entendido" });
}

export function showConvivenciaWarning(text, title = "Advertencia") {
  return Swal.fire({ title, text, icon: "warning", confirmButtonText: "Entendido" });
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
  });
}

export function humanizeConvivenciaStatus(status) {
  if (!status) return "-";
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
  return (items || []).map((item) => item?.[key] ?? "-");
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
