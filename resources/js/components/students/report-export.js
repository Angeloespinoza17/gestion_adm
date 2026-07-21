import { getPdfMake } from "../../utils/pdfmake";

function formatCell(value) {
  if (value === null || value === undefined) return "";
  if (typeof value === "boolean") return value ? "Sí" : "No";
  return String(value);
}

function excelSafeCell(value) {
  const cell = formatCell(value);
  return /^[=+\-@]/.test(cell) ? `'${cell}` : cell;
}

function escapeHtml(value) {
  return excelSafeCell(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function normalizeRow(row) {
  return (Array.isArray(row) ? row : [row]).map(formatCell);
}

export function downloadStudentReportExcel(fileName, sections) {
  const rows = [];

  (sections || []).forEach((section) => {
    rows.push([section.title || "Sección"]);
    if (section.headers?.length) rows.push(section.headers);
    (section.rows || []).forEach((row) => rows.push(row));
    rows.push([]);
  });

  const html = `
    <html>
      <head><meta charset="UTF-8"></head>
      <body><table>${rows
        .map((row) => `<tr>${normalizeRow(row).map((cell) => `<td>${escapeHtml(cell)}</td>`).join("")}</tr>`)
        .join("")}</table></body>
    </html>
  `;
  const blob = new Blob(["\uFEFF", html], { type: "application/vnd.ms-excel;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = fileName.endsWith(".xls") ? fileName : `${fileName}.xls`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

export function downloadStudentReportPdf(fileName, title, subtitle, sections, context = {}) {
  const pdfMake = getPdfMake();
  const content = [
    {
      columns: [
        [
          { text: title, style: "title" },
          { text: subtitle || "", style: "subtitle" },
        ],
        {
          text: `Generado ${new Date(context.generatedAt || Date.now()).toLocaleString("es-CL")}`,
          style: "generatedAt",
          width: 190,
        },
      ],
      margin: [0, 0, 0, 10],
    },
  ];

  if (context.filters?.length) {
    content.push({
      table: {
        widths: [95, "*", 95, "*"],
        body: context.filters.reduce((rows, filter, index) => {
          if (index % 2 === 0) {
            const next = context.filters[index + 1] || ["", ""];
            rows.push([
              { text: formatCell(filter[0]), bold: true, color: "#495057" },
              formatCell(filter[1]),
              { text: formatCell(next[0]), bold: true, color: "#495057" },
              formatCell(next[1]),
            ]);
          }
          return rows;
        }, []),
      },
      layout: {
        hLineColor: () => "#e9ecef",
        vLineColor: () => "#e9ecef",
        paddingLeft: () => 6,
        paddingRight: () => 6,
        paddingTop: () => 4,
        paddingBottom: () => 4,
      },
      margin: [0, 0, 0, 10],
    });
  }

  (context.images || []).forEach((image, index) => {
    if (!image?.dataUri) return;
    content.push({
      text: image.title || "Gráfico",
      style: "section",
      pageBreak: index > 0 && index % 2 === 0 ? "before" : undefined,
    });
    content.push({ image: image.dataUri, width: 735, height: 245, fit: [735, 245], margin: [0, 0, 0, 8] });
  });

  (sections || []).forEach((section) => {
    const rows = (section.rows || []).map(normalizeRow);
    if (!rows.length && !section.headers?.length) return;

    const body = section.headers?.length
      ? [section.headers.map((header) => ({ text: formatCell(header), style: "tableHeader" })), ...rows]
      : rows;

    content.push({
      text: section.title,
      style: "section",
      pageBreak: section.pageBreakBefore ? "before" : undefined,
    });
    if (section.note) content.push({ text: section.note, style: "note" });
    content.push({
      table: {
        headerRows: section.headers?.length ? 1 : 0,
        widths: section.widths || Array(body[0]?.length || 1).fill("*"),
        body,
        dontBreakRows: true,
      },
      layout: {
        fillColor: (rowIndex) => (rowIndex > 0 && rowIndex % 2 === 0 ? "#f8f9fa" : null),
        hLineColor: () => "#dee2e6",
        vLineColor: () => "#dee2e6",
        paddingLeft: () => 5,
        paddingRight: () => 5,
        paddingTop: () => 4,
        paddingBottom: () => 4,
      },
      margin: [0, 0, 0, 8],
    });
  });

  pdfMake
    .createPdf({
      pageSize: "A4",
      pageOrientation: "landscape",
      pageMargins: [32, 30, 32, 36],
      content,
      footer: (currentPage, pageCount) => ({
        columns: [
          { text: "Reporte de estudiantes", margin: [32, 0, 0, 0], color: "#74788d" },
          { text: `Página ${currentPage} de ${pageCount}`, alignment: "right", margin: [0, 0, 32, 0], color: "#74788d" },
        ],
        fontSize: 8,
      }),
      styles: {
        title: { fontSize: 19, bold: true, color: "#1f2937" },
        subtitle: { fontSize: 10, color: "#64748b", margin: [0, 3, 0, 0] },
        generatedAt: { fontSize: 8, color: "#64748b", alignment: "right", margin: [0, 4, 0, 0] },
        section: { fontSize: 12, bold: true, color: "#1f2937", margin: [0, 10, 0, 5] },
        note: { fontSize: 8, color: "#64748b", margin: [0, 0, 0, 5] },
        tableHeader: { bold: true, color: "#ffffff", fillColor: "#405189" },
      },
      defaultStyle: { fontSize: 8.5, color: "#343a40" },
    })
    .download(fileName.endsWith(".pdf") ? fileName : `${fileName}.pdf`);
}
