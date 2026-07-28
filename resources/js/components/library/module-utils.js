import Swal from "sweetalert2";
import { getPdfMake } from "../../utils/pdfmake";

export function formatLibraryError(error, fallback = "No se pudo completar la operación.") {
  const errors = error?.response?.data?.errors || null;
  return (
    (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
    error?.response?.data?.message ||
    error?.message ||
    fallback
  );
}

export function showLibrarySuccess(text, title = "Operación realizada") {
  return Swal.fire({
    title,
    text,
    icon: "success",
    timer: 1800,
    showConfirmButton: false,
  });
}

export function showLibraryError(text, title = "Error") {
  return Swal.fire({
    title,
    text,
    icon: "error",
    confirmButtonText: "Entendido",
  });
}

export function showLibraryWarning(text, title = "Advertencia") {
  return Swal.fire({
    title,
    text,
    icon: "warning",
    confirmButtonText: "Entendido",
  });
}

export function showLibraryInfo(text, title = "Información") {
  return Swal.fire({
    title,
    text,
    icon: "info",
    confirmButtonText: "Entendido",
  });
}

export function confirmLibraryAction({ title, text, confirmButtonText = "Confirmar", icon = "question" }) {
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

export function confirmLibraryCancel(subject = "los cambios no guardados") {
  return confirmLibraryAction({
    title: "Cancelar acción",
    text: `Se descartarán ${subject}.`,
    confirmButtonText: "Sí, cancelar",
  });
}

export function formatLibraryDate(value) {
  if (!value) return "-";
  return new Date(value).toLocaleDateString("es-CL", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  });
}

export function formatLibraryDateTime(value) {
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

export function humanizeLibraryStatus(value) {
  if (!value) return "-";
  return String(value)
    .replaceAll("_", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export function statusVariant(status) {
  const map = {
    disponible: "success",
    devuelto: "success",
    aprobada: "success",
    realizada: "success",
    finalizado: "success",
    entregado: "success",
    utilizado: "success",
    cerrada: "success",
    activo: "primary",
    emitido: "primary",
    preparada: "primary",
    renovado: "info",
    parcial: "info",
    reservada: "info",
    reservado: "info",
    en_ejecucion: "info",
    solicitada: "warning",
    pendiente: "warning",
    borrador: "secondary",
    vencido: "danger",
    rechazada: "danger",
    cancelada: "secondary",
    anulado: "secondary",
    perdido: "dark",
    dano: "warning",
    danado: "warning",
    en_reparacion: "warning",
    suspendido: "secondary",
    planificado: "secondary",
    dado_de_baja: "secondary",
  };

  return map[status] || "light";
}

export function normalizeOptions(options, includeEmpty = false, emptyLabel = "Todos") {
  const items = (options || []).map((item) => {
    if (typeof item === "string") {
      return { value: item, label: humanizeLibraryStatus(item) };
    }

    return {
      value: item.value ?? item.id,
      label: item.label ?? item.name ?? item.display_name ?? humanizeLibraryStatus(item.value ?? item.id),
    };
  });

  return includeEmpty ? [{ value: null, label: emptyLabel }].concat(items) : items;
}

export function basicApexOptions({ categories = [], colors = ["#556ee6"], horizontal = false } = {}) {
  return {
    chart: {
      toolbar: { show: false },
      fontFamily: "inherit",
    },
    colors,
    dataLabels: { enabled: false },
    stroke: { curve: "smooth", width: 3 },
    xaxis: { categories },
    plotOptions: {
      bar: {
        horizontal,
        borderRadius: 6,
        columnWidth: "45%",
      },
    },
    grid: {
      borderColor: "#eff2f7",
    },
    legend: {
      position: "top",
    },
  };
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

function normalizePdfCell(cell) {
  if (cell === null || cell === undefined || cell === "") return "-";
  if (["string", "number", "boolean"].includes(typeof cell)) return String(cell);
  return String(cell?.text ?? cell?.label ?? cell?.name ?? "-");
}

function normalizePdfRows(section) {
  const columnCount = Math.max(
    1,
    section.headers?.length || 0,
    ...(section.rows || []).map((row) => (Array.isArray(row) ? row.length : 1))
  );
  const normalizeRow = (row) => {
    const values = (Array.isArray(row) ? row : [row])
      .map(normalizePdfCell)
      .slice(0, columnCount);
    return values.concat(Array(Math.max(0, columnCount - values.length)).fill("-"));
  };
  const rows = (section.rows || []).length
    ? section.rows.map(normalizeRow)
    : [normalizeRow(["Sin datos disponibles"])];

  return {
    columnCount,
    rows: []
      .concat(section.headers?.length ? [normalizeRow(section.headers)] : [])
      .concat(rows),
  };
}

function analyticsTableLayout(hasHeaders) {
  return {
    fillColor: (rowIndex) => {
      if (hasHeaders && rowIndex === 0) return "#e9effb";
      return rowIndex % 2 === (hasHeaders ? 0 : 1) ? "#f8fafc" : null;
    },
    hLineColor: () => "#dfe5ee",
    vLineColor: () => "#dfe5ee",
    hLineWidth: () => 0.5,
    vLineWidth: () => 0.5,
    paddingLeft: () => 7,
    paddingRight: () => 7,
    paddingTop: () => 6,
    paddingBottom: () => 6,
  };
}

function metricCell(metric, index) {
  const backgrounds = ["#eef2ff", "#fff0f2", "#eaf8f3", "#fff6e6"];
  const accents = ["#4f67d7", "#ce5367", "#278f70", "#b77a24"];

  return {
    fillColor: metric.background || backgrounds[index % backgrounds.length],
    margin: [8, 8, 8, 8],
    stack: [
      {
        text: normalizePdfCell(metric.label).toUpperCase(),
        fontSize: 6.5,
        bold: true,
        color: metric.accent || accents[index % accents.length],
        characterSpacing: 0.7,
      },
      {
        text: normalizePdfCell(metric.value),
        fontSize: 18,
        bold: true,
        color: "#24334b",
        margin: [0, 4, 0, 2],
      },
      {
        text: normalizePdfCell(metric.detail),
        fontSize: 6.5,
        color: "#6f7b8e",
      },
    ],
  };
}

function compactMetricCell(metric) {
  return {
    fillColor: "#f8fafc",
    margin: [7, 6, 7, 6],
    columns: [
      {
        width: "*",
        text: normalizePdfCell(metric.label),
        fontSize: 7,
        color: "#667286",
      },
      {
        width: "auto",
        text: normalizePdfCell(metric.value),
        fontSize: 9,
        bold: true,
        color: "#2d3b52",
        alignment: "right",
      },
    ],
  };
}

function chartColumn(chart) {
  return {
    width: "*",
    stack: [
      { text: chart.title || "Visualización", style: "chartTitle" },
      { image: chart.image, fit: [242, 165], alignment: "center" },
      chart.caption
        ? { text: chart.caption, style: "chartCaption" }
        : null,
    ].filter(Boolean),
    margin: [0, 0, 0, 5],
  };
}

function buildAnalyticsPdfDefinition(title, subtitle, sections, context) {
  const generatedAt = context.generatedAt || new Date().toLocaleString("es-CL");
  const metrics = context.metrics || [];
  const supportingMetrics = context.supportingMetrics || [];
  const content = [
    {
      table: {
        widths: ["*"],
        body: [[{
          fillColor: "#173250",
          margin: [18, 16, 18, 16],
          stack: [{
            columns: [
              {
                width: "*",
                stack: [
                  { text: context.organization || "BIBLIOTECA ESCOLAR | AVIS", style: "coverEyebrow" },
                  { text: title, style: "coverTitle" },
                  subtitle ? { text: subtitle, style: "coverSubtitle" } : null,
                ].filter(Boolean),
              },
              {
                width: 128,
                stack: [
                  {
                    text: context.reportLabel || "INFORME DE GESTIÓN CRA",
                    style: "coverBadge",
                  },
                  {
                    text: `Generado: ${generatedAt}`,
                    style: "coverGenerated",
                  },
                ],
              },
            ],
          }],
        }]],
      },
      layout: "noBorders",
      margin: [0, 0, 0, 12],
    },
  ];

  if (context.summary) {
    content.push({
      table: {
        widths: [5, "*"],
        body: [[
          { text: "", fillColor: "#4f67d7" },
          {
            stack: [
              { text: "LECTURA EJECUTIVA", style: "summaryEyebrow" },
              { text: context.summary, style: "executiveSummary" },
            ],
            fillColor: "#f2f5fb",
            margin: [10, 8, 10, 8],
          },
        ]],
      },
      layout: "noBorders",
      margin: [0, 0, 0, 12],
    });
  }

  if (metrics.length) {
    content.push({ text: "INDICADORES PRINCIPALES", style: "sectionEyebrow" });
    content.push({
      table: {
        widths: Array(Math.min(4, metrics.length)).fill("*"),
        body: [metrics.slice(0, 4).map(metricCell)],
      },
      layout: {
        hLineWidth: () => 0,
        vLineWidth: () => 4,
        vLineColor: () => "#ffffff",
      },
      margin: [0, 4, 0, 10],
    });
  }

  if (supportingMetrics.length) {
    const compactRows = [];
    for (let index = 0; index < supportingMetrics.length; index += 3) {
      const row = supportingMetrics.slice(index, index + 3).map(compactMetricCell);
      while (row.length < 3) row.push({ text: "", border: [false, false, false, false] });
      compactRows.push(row);
    }
    content.push({
      table: {
        widths: ["*", "*", "*"],
        body: compactRows,
      },
      layout: {
        hLineColor: () => "#ffffff",
        vLineColor: () => "#ffffff",
        hLineWidth: () => 3,
        vLineWidth: () => 3,
      },
      margin: [0, 0, 0, 5],
    });
  }

  if (context.charts?.length) {
    content.push({ text: "VISUALIZACIONES DEL PERIODO", style: "pageSection", pageBreak: "before" });
    content.push({
      text: "Distribución de circulación, disponibilidad del inventario y participación de la comunidad escolar.",
      style: "pageSectionDescription",
    });

    for (let index = 0; index < context.charts.length; index += 2) {
      content.push({
        columns: context.charts.slice(index, index + 2).map(chartColumn),
        columnGap: 14,
        margin: [0, 0, 0, 14],
      });
    }
  }

  if (sections?.length) {
    content.push({ text: "ANEXO DE DATOS", style: "pageSection", pageBreak: "before" });
    content.push({
      text: "Detalle consolidado de los registros que componen el informe.",
      style: "pageSectionDescription",
    });

    sections.forEach((section) => {
      const normalized = normalizePdfRows(section);
      content.push({
        text: section.title,
        style: "tableTitle",
        pageBreak: section.pageBreakBefore ? "before" : undefined,
      });
      if (section.description) {
        content.push({ text: section.description, style: "tableDescription" });
      }
      content.push({
        table: {
          headerRows: section.headers?.length ? 1 : 0,
          widths: section.widths?.length === normalized.columnCount
            ? section.widths
            : Array(normalized.columnCount).fill("*"),
          body: normalized.rows,
          dontBreakRows: true,
        },
        layout: analyticsTableLayout(Boolean(section.headers?.length)),
        margin: [0, 0, 0, 9],
      });
    });
  }

  return {
    pageSize: "A4",
    pageMargins: [40, 42, 40, 46],
    header: (currentPage) => currentPage > 1
      ? {
        columns: [
          { text: "BIBLIOTECA ESCOLAR | AVIS", style: "runningHeader" },
          { text: title, style: "runningHeader", alignment: "right" },
        ],
        margin: [40, 17, 40, 0],
      }
      : null,
    footer: (currentPage, pageCount) => ({
      columns: [
        { text: "Documento de gestión interna", style: "footerText" },
        { text: `Página ${currentPage} de ${pageCount}`, style: "footerText", alignment: "right" },
      ],
      margin: [40, 14, 40, 0],
    }),
    content,
    styles: {
      coverEyebrow: { fontSize: 7.5, bold: true, color: "#9edbd4", characterSpacing: 1.2, margin: [0, 0, 0, 6] },
      coverTitle: { fontSize: 21, bold: true, color: "#ffffff" },
      coverSubtitle: { fontSize: 8.5, color: "#d3dee9", margin: [0, 5, 0, 0] },
      coverBadge: { fontSize: 7, bold: true, color: "#9edbd4", alignment: "right", characterSpacing: 0.8, margin: [0, 3, 0, 3] },
      coverGenerated: { fontSize: 6.5, color: "#c1cfdd", alignment: "right", margin: [0, 7, 0, 0] },
      summaryEyebrow: { fontSize: 6.5, bold: true, color: "#4f67d7", characterSpacing: 1 },
      executiveSummary: { fontSize: 8.5, color: "#3e4b60", lineHeight: 1.25, margin: [0, 3, 0, 0] },
      sectionEyebrow: { fontSize: 7, bold: true, color: "#61739f", characterSpacing: 1.1, margin: [0, 1, 0, 0] },
      pageSection: { fontSize: 16, bold: true, color: "#24334b", margin: [0, 6, 0, 3] },
      pageSectionDescription: { fontSize: 8, color: "#778397", margin: [0, 0, 0, 12] },
      chartTitle: { fontSize: 9, bold: true, color: "#29384f", margin: [0, 0, 0, 5] },
      chartCaption: { fontSize: 6.5, color: "#7d8899", margin: [0, 4, 0, 0] },
      tableTitle: { fontSize: 10, bold: true, color: "#29384f", margin: [0, 9, 0, 5] },
      tableDescription: { fontSize: 7, color: "#7d8899", margin: [0, -2, 0, 5] },
      runningHeader: { fontSize: 6.5, bold: true, color: "#8b96a6" },
      footerText: { fontSize: 6.5, color: "#8b96a6" },
    },
    defaultStyle: { fontSize: 8, color: "#39465a" },
  };
}

export function downloadPdfReport(fileName, title, subtitle, sections, context = {}) {
  const pdfMake = getPdfMake();
  if (context.variant === "analytics") {
    pdfMake
      .createPdf(buildAnalyticsPdfDefinition(title, subtitle, sections, context))
      .download(fileName.endsWith(".pdf") ? fileName : `${fileName}.pdf`);
    return;
  }

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

export function printLibraryHtml(title, html) {
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
