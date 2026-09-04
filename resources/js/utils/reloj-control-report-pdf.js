import { getPdfMake } from "./pdfmake";

const COLORS = {
  navy: "#12395C",
  teal: "#0F766E",
  tealSoft: "#EAF7F5",
  ink: "#263247",
  muted: "#718096",
  line: "#DFE6ED",
  surface: "#F7F9FC",
  green: "#08785E",
  greenSoft: "#E5F8F1",
  red: "#B4233E",
  redSoft: "#FFEDF1",
};

const text = (value, fallback = "—") => {
  const normalized = String(value ?? "").trim();
  return normalized || fallback;
};

const parseDate = (value) => {
  if (!value) return null;
  const raw = String(value);
  const dotNet = raw.match(/\/Date\((\d+)/);
  if (dotNet) return new Date(Number(dotNet[1]));
  if (/^\d{14}$/.test(raw)) {
    return new Date(`${raw.slice(0, 4)}-${raw.slice(4, 6)}-${raw.slice(6, 8)}T${raw.slice(8, 10)}:${raw.slice(10, 12)}:${raw.slice(12, 14)}`);
  }
  const parsed = new Date(raw.includes("T") ? raw : raw.replace(" ", "T"));
  return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const dateLabel = (value) => {
  const raw = String(value || "");
  if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
    return `${raw.slice(8, 10)}-${raw.slice(5, 7)}-${raw.slice(0, 4)}`;
  }
  const parsed = parseDate(value);
  if (!parsed) return text(value);
  return new Intl.DateTimeFormat("es-CL", { day: "2-digit", month: "2-digit", year: "numeric" }).format(parsed);
};

const timeLabel = (value) => {
  const parsed = parseDate(value);
  if (!parsed) return "—";
  return new Intl.DateTimeFormat("es-CL", { hour: "2-digit", minute: "2-digit", hour12: false }).format(parsed);
};

const kpi = (label, value, color, background) => ({
  margin: [0, 0, 7, 0],
  table: {
    widths: ["*"],
    body: [[{
      margin: [9, 7, 9, 7],
      fillColor: background,
      stack: [
        { text: label.toUpperCase(), color, bold: true, fontSize: 6, characterSpacing: .5 },
        { text: text(value, "0"), color: COLORS.ink, bold: true, fontSize: 12, margin: [0, 3, 0, 0] },
      ],
    }]],
  },
  layout: "noBorders",
});

const deviationCell = (minutes, goodWhenPositive) => {
  const value = Number(minutes || 0);
  const good = value === 0 || goodWhenPositive;
  return {
    text: `${value} min`,
    bold: true,
    color: good ? COLORS.green : COLORS.red,
    fillColor: good ? COLORS.greenSoft : COLORS.redSoft,
    alignment: "center",
    margin: [2, 4, 2, 4],
  };
};

const weeklyTable = (weekly) => ({
  table: {
    headerRows: 1,
    widths: [92, 67, 38, 54, 48, 48, 48, 51],
    body: [
      ["Funcionario", "Semana", "Jornadas", "Trabajado", "Entrada antes", "Atrasos", "Salida antes", "Salida después"].map((label) => ({ text: label, style: "tableHeader" })),
      ...weekly.map((week) => [
        { text: text(week.user?.name), style: "personCell" },
        { text: `${dateLabel(week.week_start)}\nal ${dateLabel(week.week_end)}`, style: "smallCell" },
        { text: `${week.worked_days || 0}/${week.days || 0}\n${week.absences || 0} aus.`, style: "centerCell" },
        { text: text(week.worked_time_label, "0 h"), style: "centerCell" },
        deviationCell(week.entry_early_minutes, true),
        deviationCell(week.entry_late_minutes, false),
        deviationCell(week.exit_early_minutes, false),
        deviationCell(week.exit_after_minutes, true),
      ]),
    ],
  },
  layout: {
    hLineColor: () => COLORS.line,
    vLineColor: () => COLORS.line,
    hLineWidth: () => .5,
    vLineWidth: () => .5,
    paddingLeft: () => 5,
    paddingRight: () => 5,
    paddingTop: () => 4,
    paddingBottom: () => 4,
  },
});

const statusLabel = (row) => {
  if (row.absent) return "Ausente";
  if (row.time_offs?.length) return "Con permiso";
  if (row.holiday) return "Vacaciones";
  if (row.worked) return "Registrada";
  return "Sin actividad";
};

const dailyTable = (rows) => ({
  table: {
    headerRows: 1,
    widths: [48, 92, 65, 54, 62, 54, 62, 42, 52],
    body: [
      ["Fecha", "Funcionario", "Planificado", "Entrada", "Diferencia", "Salida", "Diferencia", "Horas", "Estado"].map((label) => ({ text: label, style: "tableHeader" })),
      ...rows.map((row) => {
        const variance = row.attendance_variance || {};
        const schedule = row.schedule || {};
        const entryDelta = variance.entry_delta_minutes;
        const exitDelta = variance.exit_delta_minutes;

        return [
          { text: dateLabel(row.date), style: "smallCell" },
          { text: text(row.user?.name), style: "personCell" },
          { text: schedule.start_at && schedule.end_at ? `${timeLabel(schedule.start_at)}–${timeLabel(schedule.end_at)}` : "Sin horario", style: "centerCell" },
          { text: timeLabel(variance.entry_at), style: "timeCell" },
          {
            text: text(variance.entry_label, "Sin cálculo"),
            color: entryDelta == null || entryDelta === 0 ? COLORS.muted : (entryDelta < 0 ? COLORS.green : COLORS.red),
            bold: true,
            fontSize: 6.6,
          },
          { text: timeLabel(variance.exit_at), style: "timeCell" },
          {
            text: text(variance.exit_label, "Sin cálculo"),
            color: exitDelta == null || exitDelta === 0 ? COLORS.muted : (exitDelta > 0 ? COLORS.green : COLORS.red),
            bold: true,
            fontSize: 6.6,
          },
          { text: text(row.worked_hours, "0:00"), style: "timeCell" },
          { text: statusLabel(row), style: "centerCell" },
        ];
      }),
    ],
  },
  layout: {
    fillColor: (rowIndex) => rowIndex > 0 && rowIndex % 2 === 0 ? COLORS.surface : null,
    hLineColor: () => COLORS.line,
    vLineColor: () => COLORS.line,
    hLineWidth: () => .45,
    vLineWidth: () => .45,
    paddingLeft: () => 4,
    paddingRight: () => 4,
    paddingTop: () => 4,
    paddingBottom: () => 4,
  },
});

export function buildRelojControlPdfDefinition(result = {}) {
  const summary = result.summary || {};
  const period = result.period || {};
  const weekly = result.weekly || [];
  const rows = result.data || [];

  return {
    pageSize: "A4",
    pageOrientation: "landscape",
    pageMargins: [28, 58, 28, 34],
    info: {
      title: `Reloj Control ${text(period.date_from, "consulta")}`,
      subject: "Libro de asistencia y consolidado semanal",
      author: "Sistema Institucional",
    },
    header: () => ({
      margin: [28, 18, 28, 0],
      columns: [
        { width: "*", stack: [{ text: "RELOJ CONTROL", color: COLORS.teal, bold: true, fontSize: 8, characterSpacing: 1.2 }, { text: "Libro de asistencia", color: COLORS.navy, bold: true, fontSize: 16, margin: [0, 2, 0, 0] }] },
        { width: 210, alignment: "right", stack: [{ text: "FUENTE: GEOVICTORIA", color: COLORS.muted, bold: true, fontSize: 6.5 }, { text: `${dateLabel(period.date_from)} al ${dateLabel(period.date_to)}`, color: COLORS.ink, bold: true, fontSize: 9, margin: [0, 3, 0, 0] }] },
      ],
    }),
    footer: (currentPage, pageCount) => ({
      margin: [28, 7, 28, 0],
      columns: [
        { text: "Consulta de sólo lectura · Cálculos derivados después de consultar la API", color: COLORS.muted, fontSize: 6.5 },
        { text: `Página ${currentPage} de ${pageCount}`, color: COLORS.muted, fontSize: 6.5, alignment: "right" },
      ],
    }),
    content: [
      {
        table: { widths: ["*"], body: [[{ text: "RESUMEN DE LA CONSULTA", color: "#FFFFFF", bold: true, fontSize: 7, fillColor: COLORS.navy, margin: [8, 5, 8, 5] }]] },
        layout: "noBorders",
        margin: [0, 0, 0, 8],
      },
      {
        columns: [
          kpi("Personas", summary.returned_users || 0, "#5145A8", "#EFEDFF"),
          kpi("Jornadas", summary.days || 0, "#2874A8", "#EAF6FF"),
          kpi("Marcaciones", summary.punches || 0, COLORS.teal, COLORS.tealSoft),
          kpi("Ausencias", summary.absences || 0, COLORS.red, COLORS.redSoft),
          kpi("Tiempo trabajado", summary.worked_time_label || "0 h", "#A35F07", "#FFF4DD"),
        ],
        margin: [0, 0, 0, 8],
      },
      {
        columns: [
          kpi("Entradas anticipadas", `${summary.entry_early_minutes || 0} min`, COLORS.green, COLORS.greenSoft),
          kpi("Atrasos de entrada", `${summary.entry_late_minutes || 0} min`, COLORS.red, COLORS.redSoft),
          kpi("Salidas anticipadas", `${summary.exit_early_minutes || 0} min`, COLORS.red, COLORS.redSoft),
          kpi("Salidas posteriores", `${summary.exit_after_minutes || 0} min`, COLORS.green, COLORS.greenSoft),
        ],
        margin: [0, 0, 0, 13],
      },
      ...(weekly.length ? [
        { text: "CONSOLIDADO SEMANAL", style: "sectionTitle" },
        { text: "Totales por funcionario y semana. Los minutos se calculan contra el horario planificado.", style: "sectionSubtitle" },
        weeklyTable(weekly),
        { text: "DETALLE DIARIO", style: "sectionTitle", margin: [0, 14, 0, 0], pageBreak: weekly.length > 12 ? "before" : undefined },
      ] : [{ text: "DETALLE DIARIO", style: "sectionTitle" }]),
      { text: "Primera entrada y última salida comparadas con la jornada planificada.", style: "sectionSubtitle" },
      ...(rows.length ? [dailyTable(rows)] : [{ text: "No se informaron jornadas para el período consultado.", style: "emptyState" }]),
    ],
    defaultStyle: { font: "Roboto", color: COLORS.ink, fontSize: 7.2 },
    styles: {
      sectionTitle: { color: COLORS.teal, bold: true, fontSize: 8.5, characterSpacing: .8, margin: [0, 0, 0, 2] },
      sectionSubtitle: { color: COLORS.muted, fontSize: 6.5, margin: [0, 0, 0, 7] },
      tableHeader: { color: "#FFFFFF", fillColor: COLORS.navy, bold: true, fontSize: 6, alignment: "center", margin: [1, 2, 1, 2] },
      personCell: { color: COLORS.ink, bold: true, fontSize: 6.8 },
      smallCell: { color: COLORS.muted, fontSize: 6.3 },
      centerCell: { color: COLORS.ink, fontSize: 6.4, alignment: "center" },
      timeCell: { color: COLORS.ink, bold: true, fontSize: 7, alignment: "center" },
      emptyState: { color: COLORS.muted, alignment: "center", fontSize: 9, margin: [0, 30, 0, 0] },
    },
  };
}

export async function downloadRelojControlPdf(result) {
  const pdfMake = await getPdfMake();
  const from = text(result?.period?.date_from, "consulta");
  const to = text(result?.period?.date_to, from);
  pdfMake.createPdf(buildRelojControlPdfDefinition(result)).download(`reloj-control_${from}_${to}.pdf`);
}
