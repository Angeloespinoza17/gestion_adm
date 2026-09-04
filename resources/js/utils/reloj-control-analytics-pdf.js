import { getPdfMake } from "./pdfmake";

const COLORS = {
  navy: "#12395C",
  teal: "#0F766E",
  ink: "#263247",
  muted: "#718096",
  line: "#DFE6ED",
  surface: "#F7F9FC",
  green: "#08785E",
  greenSoft: "#E5F8F1",
  red: "#B4233E",
  redSoft: "#FFEDF1",
  amber: "#9B5D12",
  amberSoft: "#FFF1DF",
};

const value = (input, fallback = "—") => {
  const normalized = String(input ?? "").trim();
  return normalized || fallback;
};

const dateLabel = (input) => {
  const raw = String(input || "");
  if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return `${raw.slice(8, 10)}-${raw.slice(5, 7)}-${raw.slice(0, 4)}`;
  if (/^\d{14}$/.test(raw)) return `${raw.slice(6, 8)}-${raw.slice(4, 6)}-${raw.slice(0, 4)}`;
  return value(input);
};

const metric = (label, metricValue, color, background) => ({
  margin: [0, 0, 6, 0],
  table: {
    widths: ["*"],
    body: [[{
      margin: [8, 7, 8, 7],
      fillColor: background,
      stack: [
        { text: label.toUpperCase(), color, bold: true, fontSize: 5.8, characterSpacing: .45 },
        { text: value(metricValue, "0"), color: COLORS.ink, bold: true, fontSize: 11, margin: [0, 3, 0, 0] },
      ],
    }]],
  },
  layout: "noBorders",
});

const tableLayout = {
  fillColor: (rowIndex) => rowIndex > 0 && rowIndex % 2 === 0 ? COLORS.surface : null,
  hLineColor: () => COLORS.line,
  vLineColor: () => COLORS.line,
  hLineWidth: () => .45,
  vLineWidth: () => .45,
  paddingLeft: () => 4,
  paddingRight: () => 4,
  paddingTop: () => 4,
  paddingBottom: () => 4,
};

const section = (title, subtitle, body, pageBreak) => [
  { text: title, style: "sectionTitle", pageBreak },
  { text: subtitle, style: "sectionSubtitle" },
  body,
];

const groupTable = (rows) => ({
  table: {
    headerRows: 1,
    widths: ["*", 55, 75, 62, 60, 50],
    body: [
      ["Departamento o cargo", "Personas", "Tiempo trabajado", "Eventos atraso", "Minutos atraso", "Ausencias"].map((label) => ({ text: label, style: "tableHeader" })),
      ...rows.map((row) => [
        { text: value(row.name), style: "personCell" },
        { text: row.people || 0, style: "centerCell" },
        { text: value(row.worked_time_label, "0 h"), style: "centerCell" },
        { text: row.tardiness_occurrences || 0, style: "centerCell" },
        { text: `${row.tardiness_minutes || 0} min`, style: "badCell" },
        { text: row.absences || 0, style: "absenceCell" },
      ]),
    ],
  },
  layout: tableLayout,
});

const tardinessTable = (rows) => ({
  table: {
    headerRows: 1,
    widths: ["*", 55, 72, 58, 58, 165],
    body: [
      ["Funcionario", "Eventos", "Acumulado", "Promedio", "Máximo", "Fechas principales"].map((label) => ({ text: label, style: "tableHeader" })),
      ...rows.map((row) => [
        { text: value(row.user?.name), style: "personCell" },
        { text: row.occurrences || 0, style: "centerCell" },
        { text: `${row.minutes || 0} min`, style: "badCell" },
        { text: `${row.average_minutes || 0} min`, style: "centerCell" },
        { text: `${row.maximum_minutes || 0} min`, style: "centerCell" },
        { text: (row.details || []).slice(0, 5).map((detail) => `${dateLabel(detail.date)}: ${detail.minutes} min`).join(" · ") || "—", style: "smallCell" },
      ]),
    ],
  },
  layout: tableLayout,
});

const balanceTable = (rows) => ({
  table: {
    headerRows: 1,
    widths: ["*", 62, 62, 62, 62, 62, 62],
    body: [
      ["Funcionario", "Trabajado", "Entrada antes", "Atrasos", "Salida antes", "Salida después", "Saldo informativo"].map((label) => ({ text: label, style: "tableHeader" })),
      ...rows.map((row) => [
        { text: value(row.user?.name), style: "personCell" },
        { text: value(row.worked_time_label, "0 h"), style: "centerCell" },
        { text: `${row.entry_early_minutes || 0} min`, style: "goodCell" },
        { text: `${row.entry_late_minutes || 0} min`, style: "badCell" },
        { text: `${row.exit_early_minutes || 0} min`, style: "badCell" },
        { text: `${row.exit_after_minutes || 0} min`, style: "goodCell" },
        { text: value(row.net_label, "0 min"), style: Number(row.net_minutes || 0) < 0 ? "badCell" : "goodCell" },
      ]),
    ],
  },
  layout: tableLayout,
});

const absenceTable = (rows) => ({
  table: {
    headerRows: 1,
    widths: ["*", 52, 62, 80, 75, 190],
    body: [
      ["Funcionario", "Ausencias", "Justificadas", "Sin justificación", "No trabajado", "Fechas y antecedentes"].map((label) => ({ text: label, style: "tableHeader" })),
      ...rows.map((row) => [
        { text: value(row.user?.name), style: "personCell" },
        { text: row.occurrences || 0, style: "absenceCell" },
        { text: row.justified || 0, style: "centerCell" },
        { text: row.without_justification || 0, style: "centerCell" },
        { text: `${Math.floor((row.non_worked_minutes || 0) / 60)} h ${(row.non_worked_minutes || 0) % 60} min`, style: "centerCell" },
        {
          text: (row.details || []).slice(0, 5).map((detail) => `${dateLabel(detail.date)}: ${detail.justified ? ((detail.justifications || []).join(", ") || "Justificada") : "Sin justificación informada"}`).join("\n") || "—",
          style: "smallCell",
        },
      ]),
    ],
  },
  layout: tableLayout,
});

const emptyTable = (message) => ({ text: message, style: "emptyState" });

export function buildRelojControlAnalyticsPdfDefinition(result = {}) {
  const summary = result.summary || {};
  const reports = result.reports || {};
  const coverage = result.coverage || {};
  const filters = result.filters || {};
  const period = result.period || {};
  const byGroup = reports.by_group || [];
  const tardiness = reports.tardiness || [];
  const balances = reports.balances || [];
  const absences = reports.absences || [];

  return {
    pageSize: "A4",
    pageOrientation: "landscape",
    pageMargins: [28, 58, 28, 34],
    info: {
      title: `Reportes Reloj Control ${value(period.date_from, "consulta")}`,
      subject: "Atrasos, balance de minutos, ausencias y consolidado general",
      author: "Sistema Institucional",
    },
    header: () => ({
      margin: [28, 17, 28, 0],
      columns: [
        { width: "*", stack: [{ text: "RELOJ CONTROL", color: COLORS.teal, bold: true, fontSize: 8, characterSpacing: 1.2 }, { text: "Reportes consolidados", color: COLORS.navy, bold: true, fontSize: 16, margin: [0, 2, 0, 0] }] },
        { width: 225, alignment: "right", stack: [{ text: "FUENTE: GEOVICTORIA", color: COLORS.muted, bold: true, fontSize: 6.5 }, { text: `${dateLabel(period.date_from)} al ${dateLabel(period.date_to)}`, color: COLORS.ink, bold: true, fontSize: 9, margin: [0, 3, 0, 0] }] },
      ],
    }),
    footer: (currentPage, pageCount) => ({
      margin: [28, 7, 28, 0],
      columns: [
        { text: `Sólo lectura · Tolerancia aplicada: ${filters.tolerance_minutes || 0} min · Los saldos son informativos`, color: COLORS.muted, fontSize: 6.3 },
        { text: `Página ${currentPage} de ${pageCount}`, color: COLORS.muted, fontSize: 6.3, alignment: "right" },
      ],
    }),
    content: [
      { table: { widths: ["*"], body: [[{ text: "RESULTADO GENERAL DEL PERÍODO", color: "#FFFFFF", bold: true, fontSize: 7, fillColor: COLORS.navy, margin: [8, 5, 8, 5] }]] }, layout: "noBorders", margin: [0, 0, 0, 8] },
      {
        columns: [
          metric("Funcionarios", `${summary.returned_users || 0}/${summary.requested_users || 0}`, "#4267A9", "#EAF1FF"),
          metric("Personas con atrasos", summary.tardy_people || 0, COLORS.amber, "#FFF4DD"),
          metric("Minutos de atraso", `${summary.tardiness_minutes || 0} min`, COLORS.red, COLORS.redSoft),
          metric("Personas ausentes", summary.absent_people || 0, COLORS.red, COLORS.redSoft),
          metric("Jornadas ausentes", summary.absences || 0, COLORS.amber, COLORS.amberSoft),
          metric("Tiempo trabajado", summary.worked_time_label || "0 h", COLORS.teal, "#EAF7F5"),
        ],
        margin: [0, 0, 0, 8],
      },
      {
        columns: [
          metric("Entradas anticipadas", `${summary.entry_early_minutes || 0} min`, COLORS.green, COLORS.greenSoft),
          metric("Salidas anticipadas", `${summary.exit_early_minutes || 0} min`, COLORS.red, COLORS.redSoft),
          metric("Salidas posteriores", `${summary.exit_after_minutes || 0} min`, COLORS.green, COLORS.greenSoft),
          metric("Horarios cubiertos", coverage.scheduled_days || 0, COLORS.teal, "#EAF7F5"),
          metric("Marcas incompletas", (summary.missing_entry || 0) + (summary.missing_exit || 0), COLORS.amber, COLORS.amberSoft),
        ],
        margin: [0, 0, 0, 12],
      },
      ...section("CONSOLIDADO POR ÁREA O CARGO", "Suma de toda la dotación respondida para el período consultado.", byGroup.length ? groupTable(byGroup) : emptyTable("No hay áreas con datos para el período.")),
      ...section("REPORTE DE ATRASOS", `Sólo se consideran entradas que superan la tolerancia de ${filters.tolerance_minutes || 0} minutos.`, tardiness.length ? tardinessTable(tardiness) : emptyTable("No se informaron atrasos para el criterio seleccionado."), "before"),
      ...section("BALANCE DE MINUTOS", "Las categorías permanecen separadas. El saldo neto es únicamente informativo.", balances.length ? balanceTable(balances) : emptyTable("No hay balances calculables para el período."), "before"),
      ...section("REPORTE DE AUSENCIAS", "Incluye únicamente jornadas marcadas como ausencia por GeoVictoria; se muestran justificaciones cuando fueron informadas.", absences.length ? absenceTable(absences) : emptyTable("No se informaron ausencias para el período."), "before"),
    ],
    defaultStyle: { font: "Roboto", color: COLORS.ink, fontSize: 7 },
    styles: {
      sectionTitle: { color: COLORS.teal, bold: true, fontSize: 8.5, characterSpacing: .8, margin: [0, 0, 0, 2] },
      sectionSubtitle: { color: COLORS.muted, fontSize: 6.4, margin: [0, 0, 0, 7] },
      tableHeader: { color: "#FFFFFF", fillColor: COLORS.navy, bold: true, fontSize: 5.8, alignment: "center", margin: [1, 2, 1, 2] },
      personCell: { color: COLORS.ink, bold: true, fontSize: 6.6 },
      centerCell: { color: COLORS.ink, fontSize: 6.3, alignment: "center" },
      smallCell: { color: COLORS.muted, fontSize: 6 },
      goodCell: { color: COLORS.green, fillColor: COLORS.greenSoft, bold: true, fontSize: 6.3, alignment: "center" },
      badCell: { color: COLORS.red, fillColor: COLORS.redSoft, bold: true, fontSize: 6.3, alignment: "center" },
      absenceCell: { color: COLORS.amber, fillColor: COLORS.amberSoft, bold: true, fontSize: 6.3, alignment: "center" },
      emptyState: { color: COLORS.muted, alignment: "center", fontSize: 8.5, margin: [0, 22, 0, 22] },
    },
  };
}

export async function downloadRelojControlAnalyticsPdf(result) {
  const pdfMake = await getPdfMake();
  const from = value(result?.period?.date_from, "consulta");
  const to = value(result?.period?.date_to, from);
  pdfMake.createPdf(buildRelojControlAnalyticsPdfDefinition(result)).download(`reportes-reloj-control_${from}_${to}.pdf`);
}
