import axios from "axios";
import Swal from "sweetalert2";
import { getPdfMake } from "../../utils/pdfmake";

export function formatInfirmaryError(error, fallback = "No se pudo completar la operación.") {
  const errors = error?.response?.data?.errors || null;
  return (
    (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
    error?.response?.data?.message ||
    error?.message ||
    fallback
  );
}

export function showInfirmarySuccess(text, title = "Operación realizada") {
  return Swal.fire({
    title,
    text,
    icon: "success",
    timer: 1800,
    showConfirmButton: false,
  });
}

export function showInfirmaryError(text, title = "Error") {
  return Swal.fire({
    title,
    text,
    icon: "error",
    confirmButtonText: "Entendido",
  });
}

export function showInfirmaryWarning(text, title = "Advertencia") {
  return Swal.fire({
    title,
    text,
    icon: "warning",
    confirmButtonText: "Entendido",
  });
}

export function showInfirmaryInfo(text, title = "Información") {
  return Swal.fire({
    title,
    text,
    icon: "info",
    confirmButtonText: "Entendido",
  });
}

export function confirmInfirmaryAction({ title, text, confirmButtonText = "Confirmar", icon = "warning" }) {
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

export function confirmInfirmaryCancel(subject = "los cambios no guardados") {
  return confirmInfirmaryAction({
    title: "Cancelar edición",
    text: `Se descartarán ${subject}.`,
    confirmButtonText: "Sí, cancelar",
    icon: "question",
  });
}

export async function downloadInfirmaryFile(url, fileName = null) {
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

export function formatInfirmaryDate(value) {
  if (!value) return "-";
  const rawValue = String(value).slice(0, 10);
  const dateOnlyMatch = rawValue.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  const date = dateOnlyMatch
    ? new Date(Number(dateOnlyMatch[1]), Number(dateOnlyMatch[2]) - 1, Number(dateOnlyMatch[3]))
    : new Date(value);

  return date.toLocaleDateString("es-CL", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  });
}

export function formatInfirmaryDateTime(value) {
  if (!value) return "-";
  return new Date(value).toLocaleString("es-CL", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  });
}

export function toInputDate(value) {
  if (!value) return "";
  return String(value).slice(0, 10);
}

export function toInputDateTime(value) {
  if (!value) return "";
  const rawValue = String(value);

  // Los valores ISO con zona horaria (por ejemplo, Date#toISOString) deben
  // convertirse a la hora local antes de asignarlos a un datetime-local.
  if (/T.*(?:Z|[+-]\d{2}:?\d{2})$/i.test(rawValue)) {
    const date = new Date(rawValue);
    if (!Number.isNaN(date.getTime())) {
      const pad = (part) => String(part).padStart(2, "0");
      return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    }
  }

  return rawValue.replace(" ", "T").slice(0, 16);
}

export function humanizeInfirmaryStatus(status) {
  if (!status) return "-";
  return String(status)
    .replaceAll("_", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export function statusVariant(status) {
  const map = {
    abierta: "warning",
    en_atencion: "info",
    finalizada: "success",
    abierta_diat: "primary",
    disponible: "success",
    vigente: "success",
    administrada: "success",
    leve: "success",
    contesto: "success",
    cerrado: "success",
    moderado: "warning",
    media: "warning",
    pendiente: "warning",
    proxima_a_vencer: "warning",
    proximo_a_vencer: "warning",
    stock_bajo: "warning",
    mensaje_dejado: "info",
    en_seguimiento: "info",
    en_proceso: "info",
    alta: "danger",
    emergencia: "danger",
    grave: "danger",
    critico: "danger",
    critico_stock: "danger",
    agotado: "danger",
    vencido: "danger",
    vencida: "danger",
    no_contesto: "secondary",
    no_administrada: "secondary",
    terminada: "secondary",
    sin_acompanante: "light",
  };

  return map[status] || "light";
}

export function boolLabel(value, yes = "Sí", no = "No") {
  return value ? yes : no;
}

export function fileSizeLabel(bytes) {
  const size = Number(bytes || 0);
  if (size < 1024) return `${size} B`;
  if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
  return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

export function normalizeOptions(options, includeEmpty = false, emptyLabel = "Todos") {
  const items = (options || []).map((item) => {
    if (typeof item === "string") {
      return { value: item, text: humanizeInfirmaryStatus(item) };
    }

    return {
      value: item.value ?? item.id,
      text: item.label ?? item.name ?? item.display_name ?? humanizeInfirmaryStatus(item.value ?? item.id),
    };
  });

  return includeEmpty ? [{ value: null, text: emptyLabel }].concat(items) : items;
}

export function extractChartLabels(items, key = "label") {
  return (items || []).map((item) => item?.[key] ?? "-");
}

export function extractChartTotals(items, key = "total") {
  return (items || []).map((item) => Number(item?.[key] || 0));
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

export function downloadPdfReport(fileName, title, subtitle, sections, context = {}) {
  const pdfMake = getPdfMake();
  const generatedAt = context.generatedAt || new Date().toLocaleString("es-CL");
  const content = [
    {
      columns: [
        {
          width: "*",
          stack: [
            { text: context.organization || "Gestión Escolar", style: "eyebrow" },
            { text: title, style: "title" },
            subtitle ? { text: subtitle, style: "subtitle" } : null,
          ].filter(Boolean),
        },
        {
          width: 125,
          stack: [
            { text: "INFORME ESTADÍSTICO", style: "reportBadge" },
            { text: `Generado: ${generatedAt}`, style: "generatedAt" },
          ],
        },
      ],
      margin: [0, 0, 0, 12],
    },
    { canvas: [{ type: "line", x1: 0, y1: 0, x2: 515, y2: 0, lineWidth: 2, lineColor: "#3568d4" }] },
  ];

  if (context.summary) {
    content.push({ text: context.summary, style: "executiveSummary" });
  }

  (sections || []).forEach((section) => {
    const columnCount = Math.max(
      1,
      section.headers?.length || 0,
      ...(section.rows || []).map((row) => (Array.isArray(row) ? row.length : 1))
    );
    const normalizeCell = (cell) => {
      if (cell === null || cell === undefined || cell === "") return "-";
      if (["string", "number", "boolean"].includes(typeof cell)) return cell;
      return String(cell?.text ?? cell?.label ?? cell?.name ?? "-");
    };
    const normalizeRow = (row) => {
      const cells = (Array.isArray(row) ? row : [row]).map(normalizeCell).slice(0, columnCount);
      return cells.concat(Array(Math.max(0, columnCount - cells.length)).fill("-"));
    };
    const dataRows = section.rows?.length
      ? section.rows.map(normalizeRow)
      : [normalizeRow(["Sin datos disponibles"])];
    const rows = []
      .concat(section.headers?.length ? [normalizeRow(section.headers)] : [])
      .concat(dataRows);

    content.push({ text: section.title, style: "section", pageBreak: section.pageBreakBefore ? "before" : undefined });
    if (section.description) {
      content.push({ text: section.description, style: "sectionDescription" });
    }
    content.push({
      table: {
        headerRows: section.headers?.length ? 1 : 0,
        widths: section.widths?.length === columnCount ? section.widths : Array(columnCount).fill("*"),
        body: rows,
      },
      layout: {
        fillColor: (rowIndex) => (rowIndex === 0 && section.headers?.length ? "#eaf0fb" : rowIndex % 2 === 0 ? "#f8fafc" : null),
        hLineColor: () => "#dfe5ee",
        vLineColor: () => "#dfe5ee",
        hLineWidth: () => 0.5,
        vLineWidth: () => 0.5,
        paddingLeft: () => 6,
        paddingRight: () => 6,
        paddingTop: () => 5,
        paddingBottom: () => 5,
      },
      margin: [0, 0, 0, 8],
    });
  });

  if (context.charts?.length) {
    content.push({ text: "Visualizaciones", style: "section", pageBreak: "before" });
    content.push({
      columns: context.charts.slice(0, 2).map((chart) => chartColumn(chart)),
      columnGap: 12,
      margin: [0, 0, 0, 12],
    });

    for (let index = 2; index < context.charts.length; index += 2) {
      content.push({
        columns: context.charts.slice(index, index + 2).map((chart) => chartColumn(chart)),
        columnGap: 12,
        margin: [0, 0, 0, 12],
      });
    }
  }

  pdfMake.createPdf({
    pageSize: "A4",
    pageMargins: [40, 45, 40, 48],
    header: (currentPage) => currentPage > 1
      ? { text: title, alignment: "right", color: "#8791a3", fontSize: 8, margin: [40, 18, 40, 0] }
      : null,
    footer: (currentPage, pageCount) => ({
      columns: [
        { text: "Documento de uso interno · Información confidencial", color: "#8791a3", fontSize: 7 },
        { text: `Página ${currentPage} de ${pageCount}`, alignment: "right", color: "#8791a3", fontSize: 7 },
      ],
      margin: [40, 14, 40, 0],
    }),
    content,
    styles: {
      eyebrow: { fontSize: 8, bold: true, color: "#3568d4", characterSpacing: 1.1, margin: [0, 0, 0, 3] },
      title: { fontSize: 21, bold: true, color: "#263247" },
      subtitle: { fontSize: 9, color: "#687386", margin: [0, 4, 0, 0] },
      reportBadge: { fontSize: 8, bold: true, color: "#ffffff", fillColor: "#3568d4", alignment: "center", margin: [0, 5, 0, 5] },
      generatedAt: { fontSize: 7, color: "#7b8493", alignment: "right" },
      executiveSummary: { fontSize: 9, color: "#3f4a5c", fillColor: "#f2f6fc", margin: [0, 12, 0, 3] },
      section: { fontSize: 12, bold: true, color: "#263247", margin: [0, 12, 0, 6] },
      sectionDescription: { fontSize: 8, color: "#7b8493", margin: [0, -3, 0, 6] },
      chartTitle: { fontSize: 9, bold: true, color: "#263247", margin: [0, 0, 0, 4] },
    },
    defaultStyle: { fontSize: 8, color: "#354052" },
  }).download(fileName.endsWith(".pdf") ? fileName : `${fileName}.pdf`);
}

function chartColumn(chart) {
  return {
    width: "*",
    stack: [
      { text: chart.title || "Gráfico", style: "chartTitle" },
      { image: chart.image, fit: [245, 165], alignment: "center" },
      chart.caption ? { text: chart.caption, fontSize: 7, color: "#7b8493", margin: [0, 4, 0, 0] } : null,
    ].filter(Boolean),
    margin: [0, 0, 0, 4],
  };
}

export function printInfirmaryHtml(title, html) {
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

export function basicApexOptions({ categories = [], horizontal = false, colors = ["#556ee6"], distributed = false } = {}) {
  return {
    chart: {
      toolbar: { show: false },
      fontFamily: "inherit",
    },
    colors,
    plotOptions: {
      bar: {
        horizontal,
        distributed,
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
