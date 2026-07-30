import Swal from "sweetalert2";
import { getPdfMake } from "../../utils/pdfmake";

export function formatCentroApuntesError(error, fallback = "No se pudo completar la operación.") {
  const errors = error?.response?.data?.errors || null;
  return (
    (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
    error?.response?.data?.message ||
    error?.message ||
    fallback
  );
}

export function showCentroApuntesSuccess(text, title = "Operación realizada") {
  return Swal.fire({
    customClass: { popup: "centro-apuntes-alert" },
    title,
    text,
    icon: "success",
    timer: 1800,
    showConfirmButton: false,
  });
}

export function showCentroApuntesError(text, title = "Error") {
  return Swal.fire({
    customClass: { popup: "centro-apuntes-alert" },
    title,
    text,
    icon: "error",
    confirmButtonText: "Entendido",
  });
}

export function showCentroApuntesWarning(text, title = "Advertencia") {
  return Swal.fire({
    customClass: { popup: "centro-apuntes-alert" },
    title,
    text,
    icon: "warning",
    confirmButtonText: "Entendido",
  });
}

export function showCentroApuntesInfo(text, title = "Información") {
  return Swal.fire({
    customClass: { popup: "centro-apuntes-alert" },
    title,
    text,
    icon: "info",
    confirmButtonText: "Entendido",
  });
}

export function confirmCentroApuntesAction({
  title,
  text,
  confirmButtonText = "Confirmar",
  icon = "question",
}) {
  return Swal.fire({
    customClass: { popup: "centro-apuntes-alert" },
    title,
    text,
    icon,
    showCancelButton: true,
    confirmButtonText,
    cancelButtonText: "Cancelar",
    reverseButtons: true,
  });
}

export function confirmCentroApuntesCancel(subject = "los cambios no guardados") {
  return confirmCentroApuntesAction({
    title: "Cancelar acción",
    text: `Se descartarán ${subject}.`,
    confirmButtonText: "Sí, cancelar",
  });
}

export function formatCentroApuntesDate(value) {
  if (!value) return "-";
  return new Date(value).toLocaleDateString("es-CL", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  });
}

export function formatCentroApuntesDateTime(value) {
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
  return new Date(value.getTime ? value.getTime() : value).toISOString().slice(0, 16);
}

export function toInputDate(value) {
  if (!value) return "";
  return String(value).slice(0, 10);
}

export function humanizeCentroApuntesStatus(value) {
  if (!value) return "-";
  return String(value)
    .replaceAll("_", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export function statusVariant(status) {
  const map = {
    pendiente: "warning",
    recibida: "info",
    en_proceso: "primary",
    pausada: "secondary",
    lista_para_retiro: "success",
    entregada: "success",
    rechazada: "danger",
    anulada: "secondary",
    urgente: "danger",
    entrega_inmediata: "danger",
    activa: "success",
    inactiva: "secondary",
    en_mantencion: "warning",
    danada: "danger",
    disponible: "success",
    stock_bajo: "warning",
    agotado: "danger",
    proximo_a_vencer: "warning",
    vencido: "danger",
    dado_de_baja: "secondary",
    ingreso: "success",
    salida: "danger",
    ajuste: "warning",
    perdida: "danger",
    devolucion: "info",
    baja: "secondary",
    solicitada: "warning",
    aprobada: "info",
  };

  return map[status] || "light";
}

export function normalizeOptions(options, includeEmpty = false, emptyLabel = "Todos") {
  const items = (options || []).map((item) => {
    if (typeof item === "string") {
      return { value: item, label: humanizeCentroApuntesStatus(item) };
    }

    return {
      value: item.value ?? item.id,
      label: item.label ?? item.name ?? item.display_name ?? humanizeCentroApuntesStatus(item.value ?? item.id),
    };
  });

  return includeEmpty ? [{ value: null, label: emptyLabel }].concat(items) : items;
}

export function normalizeCentroApuntesNullableFields(payload, fields = []) {
  const normalized = { ...payload };

  fields.forEach((field) => {
    const value = normalized[field];
    if (value === undefined || value === null || (typeof value === "string" && value.trim() === "")) {
      normalized[field] = null;
    }
  });

  return normalized;
}

export function basicApexOptions({ categories = [], colors = ["#2f7cf6"], horizontal = false } = {}) {
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
      borderColor: "rgba(148, 163, 184, .2)",
      strokeDashArray: 4,
    },
    legend: {
      position: "top",
    },
    noData: {
      text: "Sin datos para mostrar",
      align: "center",
      verticalAlign: "middle",
    },
    tooltip: {
      shared: true,
      intersect: false,
    },
  };
}

export function extractChartLabels(items, key = "label") {
  return (items || []).map((item) => item?.[key] ?? "-");
}

export function extractChartTotals(items, key = "total") {
  return (items || []).map((item) => Number(item?.[key] || 0));
}

function escapeExportXml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&apos;");
}

function excelCell(value, styleId = null) {
  const style = styleId ? ` ss:StyleID="${styleId}"` : "";
  if (typeof value === "number" && Number.isFinite(value)) {
    return `<Cell${style || ' ss:StyleID="Number"'}><Data ss:Type="Number">${value}</Data></Cell>`;
  }
  if (typeof value === "boolean") {
    return `<Cell${style}><Data ss:Type="Boolean">${value ? 1 : 0}</Data></Cell>`;
  }
  return `<Cell${style}><Data ss:Type="String">${escapeExportXml(value)}</Data></Cell>`;
}

function excelColumnWidths(section, columnCount) {
  const rows = [section.headers || [], ...(section.rows || [])];
  return Array.from({ length: columnCount }, (_, columnIndex) => {
    if (section.columnWidths?.[columnIndex]) {
      return section.columnWidths[columnIndex];
    }
    const maxLength = rows.reduce((longest, row) => {
      const length = String(row?.[columnIndex] ?? "").length;
      return Math.max(longest, Math.min(length, 40));
    }, 10);
    return Math.min(250, Math.max(72, maxLength * 7.2));
  });
}

export function downloadExcelWorkbook(fileName, sections, options = {}) {
  const usedSheetNames = new Set();
  const normalizedSections = (sections || []).length ? sections : [{ title: "Reporte", rows: [["Sin datos"]] }];
  const worksheets = normalizedSections.map((section, sectionIndex) => {
    const fallbackName = `Hoja ${sectionIndex + 1}`;
    const baseName = String(section.title || fallbackName)
      .replace(/[\\/?*\[\]:]/g, " ")
      .trim()
      .slice(0, 31) || fallbackName;
    let sheetName = baseName;
    let suffix = 2;
    while (usedSheetNames.has(sheetName)) {
      const label = ` (${suffix})`;
      sheetName = `${baseName.slice(0, 31 - label.length)}${label}`;
      suffix += 1;
    }
    usedSheetNames.add(sheetName);

    const rows = section.rows || [];
    const columnCount = Math.max(
      1,
      section.headers?.length || 0,
      ...rows.map((row) => row.length)
    );
    const columns = excelColumnWidths(section, columnCount)
      .map((width) => `<Column ss:AutoFitWidth="0" ss:Width="${width}"/>`)
      .join("");
    const titleRow = `<Row ss:Height="28"><Cell ss:StyleID="Title" ss:MergeAcross="${columnCount - 1}"><Data ss:Type="String">${escapeExportXml(section.title || options.title || "Reporte")}</Data></Cell></Row>`;
    const subtitle = section.subtitle || options.subtitle || "";
    const subtitleRow = subtitle
      ? `<Row ss:Height="24"><Cell ss:StyleID="Subtitle" ss:MergeAcross="${columnCount - 1}"><Data ss:Type="String">${escapeExportXml(subtitle)}</Data></Cell></Row>`
      : "";
    const headerRow = section.headers?.length
      ? `<Row ss:Height="24">${section.headers.map((header) => excelCell(header, "Header")).join("")}</Row>`
      : "";
    const dataRows = (rows.length ? rows : [["Sin datos"]])
      .map((row) => `<Row>${row.map((value) => excelCell(value)).join("")}</Row>`)
      .join("");
    const splitRow = 1 + (subtitleRow ? 1 : 0) + (headerRow ? 1 : 0);

    return `<Worksheet ss:Name="${escapeExportXml(sheetName)}">
      <Table>${columns}${titleRow}${subtitleRow}${headerRow}${dataRows}</Table>
      <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
        <FreezePanes/><FrozenNoSplit/><SplitHorizontal>${splitRow}</SplitHorizontal>
        <TopRowBottomPane>${splitRow}</TopRowBottomPane><ActivePane>2</ActivePane>
        <ProtectObjects>False</ProtectObjects><ProtectScenarios>False</ProtectScenarios>
      </WorksheetOptions>
    </Worksheet>`;
  }).join("");

  const workbook = `<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
  <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
    <Title>${escapeExportXml(options.title || "Reporte Centro de Apuntes")}</Title>
    <Author>${escapeExportXml(options.author || "Centro de Apuntes")}</Author>
    <Created>${new Date().toISOString()}</Created>
  </DocumentProperties>
  <Styles>
    <Style ss:ID="Default" ss:Name="Normal">
      <Alignment ss:Vertical="Center"/><Font ss:FontName="Arial" ss:Size="10"/>
    </Style>
    <Style ss:ID="Title">
      <Alignment ss:Vertical="Center"/><Font ss:FontName="Arial" ss:Size="16" ss:Bold="1" ss:Color="#FFFFFF"/>
      <Interior ss:Color="#405189" ss:Pattern="Solid"/>
    </Style>
    <Style ss:ID="Subtitle">
      <Alignment ss:Vertical="Center" ss:WrapText="1"/><Font ss:FontName="Arial" ss:Size="9" ss:Color="#586174"/>
      <Interior ss:Color="#EEF2FF" ss:Pattern="Solid"/>
    </Style>
    <Style ss:ID="Header">
      <Alignment ss:Vertical="Center" ss:WrapText="1"/><Font ss:FontName="Arial" ss:Size="9" ss:Bold="1" ss:Color="#2A3042"/>
      <Interior ss:Color="#DDE5FF" ss:Pattern="Solid"/>
      <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AEBBDD"/></Borders>
    </Style>
    <Style ss:ID="Number"><Alignment ss:Horizontal="Right" ss:Vertical="Center"/><NumberFormat ss:Format="#,##0.00"/></Style>
  </Styles>
  ${worksheets}
</Workbook>`;

  const blob = new Blob(["\uFEFF", workbook], {
    type: "application/vnd.ms-excel;charset=utf-8;",
  });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = String(fileName).toLowerCase().endsWith(".xls") ? fileName : `${fileName}.xls`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

export function downloadPdfReport(fileName, title, subtitle, sections, options = {}) {
  const pdfMake = getPdfMake();
  const content = [{ text: title, style: "title" }];
  const generatedAt = options.generatedAt || new Date().toLocaleString("es-CL");

  if (subtitle) {
    content.push({ text: subtitle, style: "subtitle" });
  }

  (sections || []).forEach((section) => {
    const headers = section.headers || [];
    const sourceRows = section.rows?.length ? section.rows : [["Sin datos"]];
    const columnCount = Math.max(1, headers.length, ...sourceRows.map((row) => row.length));
    const rows = sourceRows.map((row) =>
      Array.from({ length: columnCount }, (_, columnIndex) =>
        columnIndex < row.length ? row[columnIndex] : ""
      )
    );
    const body = []
      .concat(headers.length ? [headers.map((header) => ({ text: String(header ?? ""), style: "tableHeader" }))] : [])
      .concat(rows.map((row) => row.map((cell) => ({ text: String(cell ?? "-"), style: "tableCell" }))));
    const tableNode = {
      table: {
        headerRows: headers.length ? 1 : 0,
        keepWithHeaderRows: 1,
        dontBreakRows: true,
        widths: section.widths || Array.from({ length: columnCount }, () => "*"),
        body,
      },
      layout: {
        fillColor: (rowIndex) => (headers.length && rowIndex === 0 ? "#DDE5FF" : (rowIndex % 2 === 0 ? "#F8FAFD" : null)),
        hLineColor: () => "#DCE2EC",
        vLineColor: () => "#E8ECF2",
        hLineWidth: (index, node) => (index === 0 || index === node.table.body.length ? 0.8 : 0.35),
        vLineWidth: () => 0.35,
        paddingLeft: () => 5,
        paddingRight: () => 5,
        paddingTop: () => 4,
        paddingBottom: () => 4,
      },
      margin: [0, 0, 0, 12],
    };
    const titleNode = { text: section.title || "Sección", style: "section" };
    const fitsAsBlock = rows.length <= 10 && columnCount <= 7;

    if (fitsAsBlock) {
      content.push({ stack: [titleNode, tableNode], unbreakable: true });
    } else {
      content.push({ ...titleNode, pageBreak: "before" }, tableNode);
    }
  });

  pdfMake.createPdf({
    pageSize: options.pageSize || "A4",
    pageOrientation: options.pageOrientation || "portrait",
    pageMargins: options.pageMargins || [32, 44, 32, 38],
    header: () => ({
      text: options.headerText || "CENTRO DE APUNTES - REPORTE OPERATIVO",
      color: "#7A8498",
      fontSize: 7,
      bold: true,
      margin: [32, 18, 32, 0],
    }),
    footer: (currentPage, pageCount) => ({
      columns: [
        { text: `Generado ${generatedAt}`, alignment: "left" },
        { text: `Página ${currentPage} de ${pageCount}`, alignment: "right" },
      ],
      color: "#7A8498",
      fontSize: 7,
      margin: [32, 10, 32, 0],
    }),
    content,
    styles: {
      title: { fontSize: 18, bold: true, color: "#2A3042", margin: [0, 0, 0, 4] },
      subtitle: { fontSize: 9, color: "#667085", margin: [0, 0, 0, 12] },
      section: { fontSize: 11, bold: true, color: "#405189", margin: [0, 9, 0, 6] },
      tableHeader: { bold: true, color: "#2A3042", fontSize: options.tableFontSize || 8 },
      tableCell: { color: "#3D4657", fontSize: options.tableFontSize || 8 },
    },
    defaultStyle: { fontSize: options.tableFontSize || 8, lineHeight: 1.15 },
    info: {
      title,
      subject: subtitle || "Reporte del Centro de Apuntes",
      author: options.author || "Centro de Apuntes",
    },
  }).download(String(fileName).toLowerCase().endsWith(".pdf") ? fileName : `${fileName}.pdf`);
}

export function printCentroApuntesHtml(title, html) {
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
