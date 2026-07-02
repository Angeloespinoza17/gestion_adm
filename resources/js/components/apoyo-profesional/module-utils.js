import axios from "axios";
import Swal from "sweetalert2";
import { getPdfMake } from "../../utils/pdfmake";

export function formatSupportError(error, fallback = "No se pudo completar la operación.") {
  const errors = error?.response?.data?.errors || null;
  return (
    (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
    error?.response?.data?.message ||
    error?.message ||
    fallback
  );
}

export function showSupportSuccess(text, title = "Operación realizada") {
  return Swal.fire({
    title,
    text,
    icon: "success",
    timer: 1800,
    showConfirmButton: false,
  });
}

export function showSupportError(text, title = "Error") {
  return Swal.fire({
    title,
    text,
    icon: "error",
    confirmButtonText: "Entendido",
  });
}

export function showSupportWarning(text, title = "Advertencia") {
  return Swal.fire({
    title,
    text,
    icon: "warning",
    confirmButtonText: "Entendido",
  });
}

export function confirmSupportAction({ title, text, confirmButtonText = "Confirmar", icon = "warning" }) {
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

export function confirmSupportCancel(subject = "los cambios no guardados") {
  return confirmSupportAction({
    title: "Cancelar registro",
    text: `Se descartarán ${subject}.`,
    confirmButtonText: "Sí, cancelar",
    icon: "question",
  });
}

export async function downloadSupportFile(url, fileName = null) {
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

export function formatSupportDate(value) {
  if (!value) return "-";
  return new Date(value).toLocaleDateString("es-CL", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  });
}

export function formatSupportDateTime(value) {
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

export function humanizeSupportStatus(status) {
  if (!status) return "-";
  return String(status)
    .replaceAll("_", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export function supportStatusVariant(status) {
  const map = {
    borrador: "secondary",
    abierta: "warning",
    en_seguimiento: "info",
    derivada: "primary",
    escalada: "danger",
    cerrada: "success",
    anulada: "dark",
    enviada: "warning",
    recibida: "info",
    en_revision: "primary",
    aceptada: "success",
    rechazada: "danger",
    pendiente: "warning",
    realizado: "success",
    reprogramado: "info",
    cancelado: "secondary",
    disenado: "secondary",
    en_ejecucion: "primary",
    finalizado: "success",
    suspendido: "danger",
    general: "light",
    reservada: "warning",
    confidencial: "danger",
    alta_confidencialidad: "dark",
    baja: "light",
    media: "warning",
    alta: "danger",
    urgente: "danger",
  };

  return map[status] || "light";
}

export function normalizeOptions(options, includeEmpty = false, emptyLabel = "Todos") {
  const items = (options || []).map((item) => {
    if (typeof item === "string") {
      return { value: item, text: humanizeSupportStatus(item) };
    }

    return {
      value: item.value ?? item.id,
      text: item.label ?? item.name ?? item.display_name ?? humanizeSupportStatus(item.value ?? item.id),
    };
  });

  return includeEmpty ? [{ value: null, text: emptyLabel }].concat(items) : items;
}

export function fileSizeLabel(bytes) {
  const size = Number(bytes || 0);
  if (size < 1024) return `${size} B`;
  if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
  return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

export function extractChartLabels(items, key = "label") {
  return (items || []).map((item) => item?.[key] ?? "-");
}

export function extractChartTotals(items, key = "total") {
  return (items || []).map((item) => Number(item?.[key] || 0));
}

export function basicApexOptions({ categories = [], horizontal = false, colors = ["#556ee6"] } = {}) {
  return {
    chart: {
      toolbar: { show: false },
      fontFamily: "inherit",
    },
    colors,
    plotOptions: {
      bar: {
        horizontal,
        borderRadius: 6,
      },
    },
    dataLabels: { enabled: false },
    xaxis: { categories },
    stroke: { curve: "smooth", width: 3 },
    legend: { show: false },
    grid: { borderColor: "#eff2f7" },
  };
}

export function downloadExcelWorkbook(fileName, sections) {
  const rows = [];

  (sections || []).forEach((section) => {
    rows.push([section.title || "Sección"]);
    if (section.headers?.length) {
      rows.push(section.headers);
    }
    (section.rows || []).forEach((row) => rows.push(row));
    rows.push([]);
  });

  const html = `<table>${rows
    .map((row) => `<tr>${row.map((cell) => `<td>${cell ?? ""}</td>`).join("")}</tr>`)
    .join("")}</table>`;

  const blob = new Blob([`\uFEFF<html><body>${html}</body></html>`], {
    type: "application/vnd.ms-excel;charset=utf-8;",
  });
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

  if (subtitle) {
    content.push({ text: subtitle, style: "subtitle" });
  }

  (sections || []).forEach((section) => {
    content.push({ text: section.title, style: "section" });
    content.push({
      table: {
        headerRows: section.headers?.length ? 1 : 0,
        body: []
          .concat(section.headers?.length ? [section.headers] : [])
          .concat(section.rows || []),
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

export function printSupportHtml(title, html) {
  const printWindow = window.open("", "_blank", "width=1100,height=800");
  if (!printWindow) return;

  printWindow.document.write(`
    <html>
      <head>
        <title>${title}</title>
        <style>
          body { font-family: Arial, sans-serif; padding: 24px; color: #2a3042; }
          table { border-collapse: collapse; width: 100%; margin-bottom: 16px; }
          th, td { border: 1px solid #d9dee7; padding: 8px; font-size: 12px; text-align: left; }
          th { background: #eff2f7; }
          h1, h2, h3 { margin: 0 0 10px; }
          .muted { color: #74788d; margin-bottom: 18px; }
        </style>
      </head>
      <body>${html}</body>
    </html>
  `);
  printWindow.document.close();
  printWindow.focus();
  printWindow.print();
}
