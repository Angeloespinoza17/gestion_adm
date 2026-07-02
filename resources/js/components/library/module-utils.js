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
    activo: "primary",
    renovado: "info",
    reservada: "info",
    reservado: "info",
    en_ejecucion: "info",
    solicitada: "warning",
    vencido: "danger",
    rechazada: "danger",
    cancelada: "secondary",
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
