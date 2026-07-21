import { getPdfMake } from "../../utils/pdfmake";

export function humanizeAdminStatus(status) {
  if (!status) return "-";

  return String(status)
    .replaceAll("_", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export function dashboardStatusVariant(status) {
  const map = {
    operativo: "success",
    en_revision: "warning",
    requiere_atencion: "danger",
    sin_datos: "secondary",
  };

  return map[status] || "secondary";
}

export function formatAdminDateTime(value) {
  if (!value) return "-";

  return new Date(value).toLocaleString("es-CL", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  });
}

export function basicAdminChartOptions({ categories = [], horizontal = false, colors = ["#556ee6"] } = {}) {
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

export function formatAdminReportCell(cell) {
  if (cell === null || cell === undefined) return "";

  if (typeof cell === "object") {
    if (Object.prototype.hasOwnProperty.call(cell, "text")) {
      return formatAdminReportCell(cell.text);
    }

    try {
      return JSON.stringify(cell) || "";
    } catch (_error) {
      return "";
    }
  }

  return String(cell);
}

function normalizeExportRow(row) {
  return (Array.isArray(row) ? row : [row]).map(formatAdminReportCell);
}

function escapeExportHtml(cell) {
  return formatAdminReportCell(cell)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

export function downloadExcelWorkbook(fileName, sections) {
  const rows = [];

  (sections || []).forEach((section) => {
    rows.push([section.title || "Sección"]);
    if (section.headers?.length) {
      rows.push(normalizeExportRow(section.headers));
    }
    (section.rows || []).forEach((row) => rows.push(normalizeExportRow(row)));
    rows.push([]);
  });

  const html = `<table>${rows
    .map((row) => `<tr>${row.map((cell) => `<td>${escapeExportHtml(cell)}</td>`).join("")}</tr>`)
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
  const content = [{ text: title, style: "title" }];
  const generatedAt = context.generatedAt || new Date().toISOString();

  content.push({
    text: `Generado: ${formatAdminDateTime(generatedAt)}`,
    style: "meta",
  });

  if (subtitle) {
    content.push({ text: subtitle, style: "subtitle" });
  }

  if (context.filters?.length) {
    content.push({
      table: {
        widths: ["auto", "*"],
        body: context.filters.map((filter) => normalizeExportRow(filter)),
      },
      layout: "lightHorizontalLines",
      margin: [0, 0, 0, 10],
    });
  }

  (context.images || []).forEach((image) => {
    if (!image?.dataUri) return;

    content.push({ text: image.title || "Gráfico", style: "section" });
    content.push({
      image: image.dataUri,
      width: 500,
      margin: [0, 0, 0, 10],
    });
  });

  (sections || []).forEach((section) => {
    const body = []
      .concat(section.headers?.length ? [normalizeExportRow(section.headers)] : [])
      .concat((section.rows || []).map((row) => normalizeExportRow(row)));

    if (!body.length) return;

    content.push({ text: section.title, style: "section" });
    content.push({
      table: {
        headerRows: section.headers?.length ? 1 : 0,
        body,
      },
      layout: "lightHorizontalLines",
      margin: [0, 0, 0, 10],
    });
  });

  pdfMake.createPdf({
    content,
    styles: {
      title: { fontSize: 18, bold: true, color: "#2a3042" },
      meta: { fontSize: 8, color: "#74788d", margin: [0, 2, 0, 6] },
      subtitle: { fontSize: 10, color: "#74788d", margin: [0, 0, 0, 10] },
      section: { fontSize: 12, bold: true, margin: [0, 10, 0, 6] },
    },
    defaultStyle: { fontSize: 9 },
  }).download(fileName.endsWith(".pdf") ? fileName : `${fileName}.pdf`);
}

export function printAdminHtml(title, html) {
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
