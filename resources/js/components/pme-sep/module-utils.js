import axios from "axios";
import Swal from "sweetalert2";
import { getPdfMake } from "../../utils/pdfmake";

export function formatPmeError(error, fallback = "No se pudo completar la operación.") {
  const errors = error?.response?.data?.errors || null;
  return (
    (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
    error?.response?.data?.message ||
    error?.message ||
    fallback
  );
}

export function showPmeSuccess(text, title = "Operación realizada") {
  return Swal.fire({
    title,
    text,
    icon: "success",
    timer: 1800,
    showConfirmButton: false,
  });
}

export function showPmeError(text, title = "Error") {
  return Swal.fire({
    title,
    text,
    icon: "error",
    confirmButtonText: "Entendido",
  });
}

export function showPmeWarning(text, title = "Advertencia") {
  return Swal.fire({
    title,
    text,
    icon: "warning",
    confirmButtonText: "Entendido",
  });
}

export function showPmeInfo(text, title = "Información") {
  return Swal.fire({
    title,
    text,
    icon: "info",
    confirmButtonText: "Entendido",
  });
}

export function confirmPmeAction({ title, text, confirmButtonText = "Confirmar", icon = "question" }) {
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

export function confirmPmeCancel(subject = "los cambios no guardados") {
  return confirmPmeAction({
    title: "Cancelar acción",
    text: `Se descartarán ${subject}.`,
    confirmButtonText: "Sí, cancelar",
  });
}

export function formatPmeDate(value) {
  if (!value) return "-";
  const normalized = /^\d{4}-\d{2}-\d{2}$/.test(String(value)) ? `${value}T12:00:00` : value;
  return new Date(normalized).toLocaleDateString("es-CL", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  });
}

export function formatPmeDateTime(value) {
  if (!value) return "-";
  return new Date(value).toLocaleString("es-CL", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  });
}

export function formatCurrency(value) {
  return new Intl.NumberFormat("es-CL", {
    style: "currency",
    currency: "CLP",
    maximumFractionDigits: 0,
  }).format(Number(value || 0));
}

export function humanizePmeStatus(value) {
  if (!value) return "-";
  return String(value)
    .replaceAll("_", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export function statusVariant(status) {
  const map = {
    borrador: "secondary",
    en_planificacion: "info",
    en_ejecucion: "primary",
    en_monitoreo: "warning",
    cerrado: "dark",
    archivado: "secondary",
    vigente: "primary",
    cumplido: "success",
    cumplida: "success",
    finalizada: "success",
    aprobada: "success",
    confirmado: "success",
    planificada: "secondary",
    planificado: "secondary",
    registrado: "info",
    revisado: "info",
    en_revision: "primary",
    pendiente: "warning",
    cargada: "warning",
    observada: "warning",
    parcialmente_cumplido: "warning",
    parcialmente_cumplida: "warning",
    atrasada: "danger",
    atrasado: "danger",
    critica: "danger",
    critico: "danger",
    rechazada: "danger",
    rechazado: "danger",
    no_cumplido: "danger",
    no_cumplida: "danger",
    anulada: "dark",
    suspendida: "dark",
    suspendido: "dark",
    prioritaria: "primary",
    preferente: "success",
    sin_clasificacion_sep: "secondary",
    pendiente_validacion: "warning",
    vigente_ciclo: "info",
    en_proceso: "info",
    realizada: "success",
    activa: "primary",
    activo: "primary",
    no_vigente: "secondary",
    inactivo: "secondary",
    inactiva: "secondary",
    sin_medicion: "secondary",
    en_avance: "info",
    cerrada: "dark",
    finalizado: "success",
  };

  return map[status] || "light";
}

export function normalizeOptions(options, includeEmpty = false, emptyLabel = "Todos") {
  const items = (options || []).map((item) => {
    if (typeof item === "string") {
      return { value: item, label: humanizePmeStatus(item) };
    }

    return {
      value: item.value ?? item.id,
      label: item.label ?? item.name ?? item.display_name ?? humanizePmeStatus(item.value ?? item.id),
    };
  });

  return includeEmpty ? [{ value: null, label: emptyLabel }].concat(items) : items;
}

export function normalizePagination(payload = {}) {
  const total = Number(payload.total ?? payload.data?.length ?? 0);
  const perPage = Number(payload.per_page ?? (total || 1));
  return {
    current_page: Number(payload.current_page || 1),
    last_page: Number(payload.last_page || 1),
    per_page: perPage,
    total,
    from: payload.from ?? (total ? 1 : 0),
    to: payload.to ?? total,
  };
}

export function basicApexOptions({ categories = [], colors = ["#556ee6"], horizontal = false } = {}) {
  return {
    chart: {
      toolbar: { show: false },
      fontFamily: "inherit",
      foreColor: "#738095",
      animations: { speed: 420 },
    },
    colors,
    dataLabels: { enabled: false },
    stroke: { curve: "smooth", width: 2.5 },
    xaxis: {
      categories,
      labels: { style: { fontSize: "10px", colors: "#738095" }, trim: true, hideOverlappingLabels: true },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    yaxis: { labels: { style: { fontSize: "10px", colors: "#738095" } } },
    plotOptions: {
      bar: {
        horizontal,
        borderRadius: 5,
        borderRadiusApplication: "end",
        columnWidth: "45%",
      },
    },
    grid: {
      borderColor: "#e8edf3",
      strokeDashArray: 4,
      padding: { left: 4, right: 8 },
    },
    legend: {
      position: "top",
      horizontalAlign: "right",
      fontSize: "11px",
      markers: { size: 5 },
    },
    tooltip: { theme: "light" },
    noData: { text: "Sin datos para visualizar", align: "center", verticalAlign: "middle", style: { color: "#7b8798", fontSize: "12px" } },
    responsive: [{ breakpoint: 768, options: { legend: { position: "bottom", horizontalAlign: "center" } } }],
  };
}

export function extractChartLabels(items, key = "label") {
  return (items || []).map((item) => item?.[key] ?? "-");
}

export function extractChartTotals(items, key = "total") {
  return (items || []).map((item) => Number(item?.[key] || 0));
}

export async function downloadPmeFile(url, fileName = null) {
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

export function printPmeHtml(title, html) {
  const printWindow = window.open("", "_blank", "width=1100,height=800");
  if (!printWindow) return;

  printWindow.document.write(`
    <html>
      <head>
        <title>${title}</title>
        <style>
          body { font-family: Arial, sans-serif; padding: 24px; color: #2a3042; }
          h1 { margin-bottom: 12px; }
          table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
          th, td { border: 1px solid #ced4da; padding: 8px; font-size: 12px; text-align: left; }
          th { background: #f8f9fa; }
        </style>
      </head>
      <body>
        <h1>${title}</h1>
        ${html}
      </body>
    </html>
  `);
  printWindow.document.close();
  printWindow.focus();
  printWindow.print();
}
