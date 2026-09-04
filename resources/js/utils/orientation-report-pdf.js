import { getPdfMake } from "./pdfmake";

const COLORS = {
  ink: "#24324A",
  muted: "#68758A",
  line: "#DDE3ED",
  soft: "#F5F3FA",
  violet: "#6948A8",
  violetDark: "#493176",
  violetLight: "#EEE8F8",
  blue: "#2F6FDC",
  green: "#26936B",
  amber: "#C67A16",
  red: "#C7485D",
  white: "#FFFFFF",
};

const ACTION_STATUS = {
  planned: "Planificada",
  in_progress: "En ejecución",
  completed: "Completada",
  postponed: "Postergada",
  cancelled: "Cancelada",
};

const ACTIVITY_STATUS = {
  scheduled: "Programada",
  in_progress: "En ejecución",
  completed: "Realizada",
  cancelled: "Cancelada",
};

const PLAN_STATUS = {
  draft: "Borrador",
  active: "Vigente",
  completed: "Finalizado",
  archived: "Archivado",
};

const EVIDENCE_TYPES = {
  photograph: "Fotografía",
  attendance: "Asistencia",
  minute: "Acta",
  report: "Informe",
  survey: "Encuesta",
  material: "Material",
  other: "Otra",
};

const safeText = (value, fallback = "No informado") => {
  const text = String(value ?? "").trim();
  return text || fallback;
};

const formatDate = (value, withTime = false) => {
  if (!value) return "Sin fecha";
  const source = String(value);
  const date = new Date(source.length === 10 ? `${source}T12:00:00` : source);
  if (Number.isNaN(date.getTime())) return source;
  return new Intl.DateTimeFormat("es-CL", withTime
    ? { dateStyle: "medium", timeStyle: "short" }
    : { dateStyle: "medium" }).format(date);
};

const generatedAt = () => new Intl.DateTimeFormat("es-CL", {
  dateStyle: "long",
  timeStyle: "short",
}).format(new Date());

const statusColor = (status) => ({
  completed: COLORS.green,
  active: COLORS.green,
  in_progress: COLORS.blue,
  planned: COLORS.violet,
  scheduled: COLORS.violet,
  postponed: COLORS.amber,
  cancelled: COLORS.muted,
  draft: COLORS.amber,
  archived: COLORS.muted,
}[status] || COLORS.muted);

const fileName = (value) => String(value || "orientacion")
  .normalize("NFD")
  .replace(/[\u0300-\u036f]/g, "")
  .replace(/[^a-zA-Z0-9]+/g, "-")
  .replace(/^-+|-+$/g, "")
  .toLowerCase();

const escapeSvg = (value) => String(value ?? "")
  .replaceAll("&", "&amp;")
  .replaceAll("<", "&lt;")
  .replaceAll(">", "&gt;")
  .replaceAll('"', "&quot;")
  .replaceAll("'", "&apos;");

const truncate = (value, length = 28) => {
  const text = safeText(value, "Sin información");
  return text.length > length ? `${text.slice(0, length - 1)}…` : text;
};

const pageHeader = (title, year, subtitle) => ({
  margin: [0, 0, 0, 14],
  table: {
    widths: ["*", 112],
    body: [[
      {
        stack: [
          { text: "COLEGIO NUESTRA SEÑORA DEL CAMINO", style: "brand" },
          { text: title, style: "reportTitle", margin: [0, 6, 0, 2] },
          { text: subtitle, style: "reportSubtitle" },
        ],
        fillColor: COLORS.violetDark,
        margin: [18, 15, 14, 15],
      },
      {
        stack: [
          { text: "ORIENTACIÓN", style: "badgeLabel" },
          { text: String(year), style: "year" },
        ],
        alignment: "center",
        fillColor: COLORS.violet,
        margin: [8, 15, 8, 15],
      },
    ]],
  },
  layout: "noBorders",
});

const sectionTitle = (title, kicker) => ({
  stack: [
    { text: kicker.toUpperCase(), style: "sectionKicker" },
    { text: title, style: "sectionTitle" },
  ],
  margin: [0, 12, 0, 8],
});

const kpi = (label, value, note, color = COLORS.violet) => ({
  table: {
    widths: ["*"],
    body: [[{
      stack: [
        { text: label.toUpperCase(), style: "kpiLabel" },
        { text: String(value), style: "kpiValue", color },
        { text: note, style: "kpiNote" },
      ],
      fillColor: "#F8F9FC",
      margin: [10, 8, 10, 8],
    }]],
  },
  layout: {
    hLineColor: () => COLORS.line,
    vLineColor: () => COLORS.line,
  },
});

const headerCells = (labels) => labels.map((label) => ({
  text: label,
  bold: true,
  color: COLORS.white,
  fontSize: 7.4,
  margin: [0, 2, 0, 2],
}));

const tableLayout = {
  fillColor: (row) => row === 0 ? COLORS.violetDark : row % 2 === 0 ? "#F8F9FC" : null,
  hLineColor: () => COLORS.line,
  vLineColor: () => COLORS.line,
  paddingLeft: () => 6,
  paddingRight: () => 6,
  paddingTop: () => 5,
  paddingBottom: () => 5,
};

const footer = (currentPage, pageCount) => ({
  margin: [32, 0, 32, 0],
  columns: [
    { text: `Generado ${generatedAt()} · CNSC Gestión`, style: "footer" },
    { text: `Página ${currentPage} de ${pageCount}`, style: "footer", alignment: "right" },
  ],
});

const styles = {
  brand: { fontSize: 8, bold: true, color: "#DDD2F3", characterSpacing: 0.7 },
  reportTitle: { fontSize: 20, bold: true, color: COLORS.white },
  reportSubtitle: { fontSize: 8.5, color: "#E9E2F6" },
  badgeLabel: { fontSize: 8, bold: true, color: "#E7DCF7", characterSpacing: 0.8 },
  year: { fontSize: 24, bold: true, color: COLORS.white, margin: [0, 4, 0, 0] },
  sectionKicker: { fontSize: 7, bold: true, color: COLORS.violet, characterSpacing: 0.8 },
  sectionTitle: { fontSize: 13, bold: true, color: COLORS.ink, margin: [0, 2, 0, 0] },
  body: { fontSize: 8.5, color: COLORS.ink, lineHeight: 1.25 },
  muted: { fontSize: 8, color: COLORS.muted },
  fieldLabel: { fontSize: 7, bold: true, color: COLORS.violet, characterSpacing: 0.4 },
  fieldValue: { fontSize: 9, color: COLORS.ink, lineHeight: 1.25 },
  kpiLabel: { fontSize: 6.6, bold: true, color: COLORS.muted, characterSpacing: 0.3 },
  kpiValue: { fontSize: 19, bold: true, margin: [0, 3, 0, 1] },
  kpiNote: { fontSize: 6.8, color: COLORS.muted },
  table: { fontSize: 7.2, color: COLORS.ink, lineHeight: 1.15 },
  footer: { fontSize: 6.5, color: COLORS.muted },
};

const baseDefinition = (content, landscape = false) => ({
  pageSize: "A4",
  pageOrientation: landscape ? "landscape" : "portrait",
  pageMargins: [32, 34, 32, 34],
  defaultStyle: { font: "Roboto" },
  footer,
  styles,
  content,
  info: {
    title: "Informe de Orientación",
    author: "Colegio Nuestra Señora del Camino",
    subject: "Plan Anual de Orientación",
  },
});

export function buildOrientationPlanPdfDefinition(plan) {
  const actions = plan?.actions || [];
  const stats = plan?.stats || {};
  const relatedPlans = plan?.related_plans || [];
  const actionRows = actions.length ? actions.map((action, index) => [
    { text: String(index + 1), alignment: "center" },
    { stack: [
      { text: safeText(action.title), bold: true },
      { text: truncate(action.objective, 110), color: COLORS.muted, fontSize: 6.6, margin: [0, 2, 0, 0] },
    ] },
    safeText(action.target_levels, "Por definir"),
    safeText((action.responsible_users || []).map((user) => user.name).join(", ") || action.responsible_summary, "Por definir"),
    `${formatDate(action.start_date)}\n${formatDate(action.end_date)}`,
    { text: ACTION_STATUS[action.status] || action.status, color: statusColor(action.status), bold: true },
    { text: `${Number(action.progress || 0)}%`, alignment: "center", bold: true },
    { text: `${Number(action.completed_activities_count || 0)}/${Number(action.activities_count || 0)} act.\n${Number(action.evidences_count || 0)} evid.`, alignment: "center" },
  ]) : [[{ text: "El plan aún no registra acciones.", colSpan: 8, alignment: "center", color: COLORS.muted }, {}, {}, {}, {}, {}, {}, {}]];

  const verificationRows = actions.length ? actions.map((action, index) => [
    String(index + 1),
    safeText(action.title),
    safeText(action.planned_verification_means),
    safeText(action.material_resources),
    safeText((action.related_plans || []).map((item) => item.name).join(", "), "Sin planes vinculados"),
  ]) : [["—", "Sin acciones", "—", "—", "—"]];

  const content = [
    pageHeader("Informe general del plan", plan?.year || "—", "Visión anual, matriz de acciones y trazabilidad planificada"),
    {
      columns: [
        { width: "*", stack: [
          { text: safeText(plan?.title, "Plan Anual de Orientación"), fontSize: 15, bold: true, color: COLORS.ink },
          { text: PLAN_STATUS[plan?.status] || safeText(plan?.status), color: statusColor(plan?.status), bold: true, fontSize: 8, margin: [0, 4, 0, 0] },
        ] },
        { width: 270, stack: [
          { text: "OBJETIVO GENERAL", style: "fieldLabel" },
          { text: safeText(plan?.general_objective), style: "body", margin: [0, 3, 0, 0] },
        ] },
      ],
      columnGap: 20,
      margin: [0, 2, 0, 12],
    },
    {
      columns: [
        kpi("Acciones", stats.actions || actions.length, "Matriz anual", COLORS.violet),
        kpi("Avance global", `${Number(stats.progress || 0)}%`, `${Number(stats.completed_actions || 0)} completadas`, COLORS.blue),
        kpi("Actividades", stats.activities || 0, `${Number(stats.completed_activities || 0)} realizadas`, COLORS.green),
        kpi("Evidencias", stats.evidences || 0, "Respaldos registrados", COLORS.amber),
      ],
      columnGap: 8,
    },
    sectionTitle("Descripción y énfasis", "Marco anual"),
    { text: safeText(plan?.description), style: "body", fillColor: COLORS.soft, margin: [10, 8, 10, 8] },
    sectionTitle("Planes institucionales indexados", "Articulación"),
    {
      text: relatedPlans.length
        ? relatedPlans.map((item) => `${item.name} (${Number(item.actions_count || 0)} acciones)`).join("  ·  ")
        : "No existen planes relacionados registrados.",
      style: "body",
      color: relatedPlans.length ? COLORS.violetDark : COLORS.muted,
    },
    sectionTitle("Matriz general de acciones", "Planificación y ejecución"),
    {
      table: {
        headerRows: 1,
        widths: [20, "*", 67, 76, 64, 54, 34, 50],
        body: [headerCells(["N°", "Acción y objetivo", "Niveles", "Responsables", "Periodo", "Estado", "Avance", "Ejecución"]), ...actionRows],
      },
      layout: tableLayout,
      style: "table",
    },
    { text: "Detalle de trazabilidad por acción", style: "sectionTitle", margin: [0, 12, 0, 8] },
    {
      table: {
        headerRows: 1,
        widths: [22, 130, "*", 130, 128],
        body: [headerCells(["N°", "Acción", "Medios de verificación", "Recursos", "Planes vinculados"]), ...verificationRows],
      },
      layout: tableLayout,
      style: "table",
    },
  ];

  return baseDefinition(content, true);
}

export function buildOrientationActionPdfDefinition(plan, action) {
  const activities = action?.activities || [];
  const evidences = action?.evidences || [];
  const responsible = (action?.responsible_users || []).map((user) => user.name).join(", ") || action?.responsible_summary;
  const activityRows = activities.length ? activities.map((activity, index) => [
    String(index + 1),
    { stack: [
      { text: safeText(activity.title), bold: true },
      { text: safeText(activity.description, "Sin descripción"), color: COLORS.muted, fontSize: 7 },
    ] },
    formatDate(activity.starts_at, true),
    safeText(activity.location, "Sin lugar"),
    { text: ACTIVITY_STATUS[activity.status] || activity.status, color: statusColor(activity.status), bold: true },
    { text: `${Number(activity.contribution_percent || 0)}% aporte\n${Number(activity.completion_percent || 0)}% cumplido`, alignment: "center" },
    safeText(activity.results, "Sin resultados informados"),
  ]) : [[{ text: "No hay actividades registradas para esta acción.", colSpan: 7, alignment: "center", color: COLORS.muted }, {}, {}, {}, {}, {}, {}]];

  const evidenceRows = evidences.length ? evidences.map((evidence, index) => [
    String(index + 1),
    safeText(evidence.title),
    EVIDENCE_TYPES[evidence.evidence_type] || evidence.evidence_type,
    formatDate(evidence.occurred_on || evidence.created_at),
    evidence.has_file ? safeText(evidence.original_name, "Archivo privado") : "Enlace externo",
    evidence.activity?.title || "Acción general",
  ]) : [[{ text: "No hay medios de verificación cargados para esta acción.", colSpan: 6, alignment: "center", color: COLORS.muted }, {}, {}, {}, {}, {}]];

  const content = [
    pageHeader("Informe por acción", plan?.year || action?.plan?.year || "—", "Planificación, ejecución y evidencias de una acción"),
    {
      columns: [
        { width: "*", stack: [
          { text: safeText(action?.title, "Acción de Orientación"), fontSize: 17, bold: true, color: COLORS.ink },
          { text: ACTION_STATUS[action?.status] || action?.status, color: statusColor(action?.status), bold: true, margin: [0, 5, 0, 0] },
        ] },
        { width: 120, stack: [
          { text: `${Number(action?.progress || 0)}%`, fontSize: 28, bold: true, color: COLORS.violet, alignment: "right" },
          { text: "AVANCE DECLARADO", style: "fieldLabel", alignment: "right" },
        ] },
      ],
      margin: [0, 2, 0, 13],
    },
    {
      table: {
        widths: ["*", "*"],
        body: [
          [
            { stack: [{ text: "OBJETIVO ESPECÍFICO", style: "fieldLabel" }, { text: safeText(action?.objective), style: "fieldValue", margin: [0, 4, 0, 0] }], fillColor: COLORS.soft, margin: 9 },
            { stack: [{ text: "DESCRIPCIÓN", style: "fieldLabel" }, { text: safeText(action?.description), style: "fieldValue", margin: [0, 4, 0, 0] }], fillColor: COLORS.soft, margin: 9 },
          ],
        ],
      },
      layout: "noBorders",
    },
    sectionTitle("Definición planificada", "Ficha de acción"),
    {
      table: {
        widths: [95, "*"],
        body: [
          [{ text: "Niveles o cursos", style: "fieldLabel" }, { text: safeText(action?.target_levels), style: "fieldValue" }],
          [{ text: "Responsables", style: "fieldLabel" }, { text: safeText(responsible), style: "fieldValue" }],
          [{ text: "Periodo", style: "fieldLabel" }, { text: `${formatDate(action?.start_date)} al ${formatDate(action?.end_date)}`, style: "fieldValue" }],
          [{ text: "Medios planificados", style: "fieldLabel" }, { text: safeText(action?.planned_verification_means), style: "fieldValue" }],
          [{ text: "Recursos", style: "fieldLabel" }, { text: safeText(action?.material_resources), style: "fieldValue" }],
          [{ text: "Planes vinculados", style: "fieldLabel" }, { text: safeText((action?.related_plans || []).map((item) => item.name).join(", "), "Sin planes vinculados"), style: "fieldValue" }],
          [{ text: "Aporte de actividades", style: "fieldLabel" }, { text: `${Number(action?.activity_contribution?.earned || action?.progress || 0)}% obtenido · ${Number(action?.activity_contribution?.allocated || 0)}% distribuido · ${Number(action?.activity_contribution?.remaining ?? 100)}% disponible`, style: "fieldValue" }],
        ],
      },
      layout: {
        fillColor: (row) => row % 2 === 0 ? "#F8F9FC" : null,
        hLineColor: () => COLORS.line,
        vLineColor: () => COLORS.line,
        paddingLeft: () => 8,
        paddingRight: () => 8,
        paddingTop: () => 7,
        paddingBottom: () => 7,
      },
    },
    sectionTitle("Actividades realizadas", "Ejecución"),
    {
      table: {
        headerRows: 1,
        widths: [18, 78, 56, 42, 45, 58, "*"],
        body: [headerCells(["N°", "Actividad", "Fecha", "Lugar", "Estado", "Aporte / cumplimiento", "Resultados"]), ...activityRows],
      },
      layout: tableLayout,
      style: "table",
    },
    sectionTitle("Medios de verificación y evidencias", "Trazabilidad"),
    {
      table: {
        headerRows: 1,
        widths: [20, "*", 57, 57, 82, 72],
        body: [headerCells(["N°", "Evidencia", "Tipo", "Fecha", "Soporte", "Actividad"]), ...evidenceRows],
      },
      layout: tableLayout,
      style: "table",
    },
  ];

  return baseDefinition(content, false);
}

const statusChartSvg = (items = []) => {
  const maximum = Math.max(1, ...items.map((item) => Number(item.count || 0)));
  const rows = items.map((item, index) => {
    const y = 55 + index * 31;
    const width = Math.max(3, Number(item.count || 0) / maximum * 280);
    return `<text x="18" y="${y + 4}" font-size="10" fill="#38465C">${escapeSvg(item.label)}</text><rect x="150" y="${y - 8}" width="290" height="15" rx="7" fill="#E8EAF1"/><rect x="150" y="${y - 8}" width="${width}" height="15" rx="7" fill="${statusColor(item.status)}"/><text x="462" y="${y + 4}" font-size="11" font-weight="700" text-anchor="end" fill="#24324A">${Number(item.count || 0)}</text>`;
  }).join("");
  return `<svg width="480" height="220" viewBox="0 0 480 220" xmlns="http://www.w3.org/2000/svg"><rect width="480" height="220" rx="13" fill="#F8F9FC"/><text x="18" y="27" font-size="12" font-weight="700" fill="#24324A">Estado de las acciones</text>${rows || '<text x="240" y="115" text-anchor="middle" font-size="10" fill="#68758A">Sin datos</text>'}</svg>`;
};

const traceabilityChartSvg = (items = []) => {
  const rows = items.map((item, index) => {
    const y = 55 + index * 31;
    const width = Math.max(3, Number(item.percent || 0) / 100 * 270);
    return `<text x="18" y="${y + 4}" font-size="9.5" fill="#38465C">${escapeSvg(item.label)}</text><rect x="160" y="${y - 8}" width="280" height="15" rx="7" fill="#E8EAF1"/><rect x="160" y="${y - 8}" width="${width}" height="15" rx="7" fill="#6948A8"/><text x="462" y="${y + 4}" font-size="10" font-weight="700" text-anchor="end" fill="#24324A">${Number(item.percent || 0)}%</text>`;
  }).join("");
  return `<svg width="480" height="220" viewBox="0 0 480 220" xmlns="http://www.w3.org/2000/svg"><rect width="480" height="220" rx="13" fill="#F8F9FC"/><text x="18" y="27" font-size="12" font-weight="700" fill="#24324A">Cobertura de trazabilidad</text>${rows || '<text x="240" y="115" text-anchor="middle" font-size="10" fill="#68758A">Sin datos</text>'}</svg>`;
};

const monthlyChartSvg = (items = []) => {
  const maximum = Math.max(1, ...items.flatMap((item) => [Number(item.actions || 0), Number(item.activities || 0)]));
  const groupWidth = 680 / Math.max(1, items.length);
  const bars = items.map((item, index) => {
    const x = 42 + index * groupWidth;
    const actionHeight = Math.max(2, Number(item.actions || 0) / maximum * 105);
    const activityHeight = Math.max(2, Number(item.activities || 0) / maximum * 105);
    return `<rect x="${x + 6}" y="${157 - actionHeight}" width="16" height="${actionHeight}" rx="4" fill="#6948A8"/><rect x="${x + 25}" y="${157 - activityHeight}" width="16" height="${activityHeight}" rx="4" fill="#2F6FDC"/><text x="${x + 23}" y="178" font-size="8" text-anchor="middle" fill="#68758A">${escapeSvg(item.label)}</text>`;
  }).join("");
  return `<svg width="760" height="200" viewBox="0 0 760 200" xmlns="http://www.w3.org/2000/svg"><rect width="760" height="200" rx="13" fill="#F8F9FC"/><text x="18" y="25" font-size="12" font-weight="700" fill="#24324A">Carga mensual planificada y ejecutada</text><rect x="510" y="17" width="10" height="10" rx="3" fill="#6948A8"/><text x="525" y="26" font-size="8" fill="#68758A">Acciones</text><rect x="590" y="17" width="10" height="10" rx="3" fill="#2F6FDC"/><text x="605" y="26" font-size="8" fill="#68758A">Actividades</text><line x1="42" y1="157" x2="735" y2="157" stroke="#CDD4DF"/>${bars}</svg>`;
};

export function buildOrientationStatisticsPdfDefinition(plan, statistics) {
  const summary = statistics?.summary || {};
  const performance = statistics?.action_performance || [];
  const rows = performance.length ? performance.map((action, index) => [
    String(index + 1),
    safeText(action.title),
    { text: ACTION_STATUS[action.status] || action.status, color: statusColor(action.status), bold: true },
    { text: `${Number(action.progress || 0)}%`, alignment: "center", bold: true },
    { text: `${Number(action.completed_activities_count || 0)}/${Number(action.activities_count || 0)}`, alignment: "center" },
    { text: String(Number(action.evidences_count || 0)), alignment: "center" },
    { text: String(Number(action.related_plans_count || 0)), alignment: "center" },
    `${formatDate(action.start_date)}\n${formatDate(action.end_date)}`,
  ]) : [[{ text: "No existen acciones para analizar.", colSpan: 8, alignment: "center", color: COLORS.muted }, {}, {}, {}, {}, {}, {}, {}]];

  const content = [
    pageHeader("Informe estadístico", plan?.year || "—", "Avance, ejecución, trazabilidad y distribución anual"),
    { text: safeText(plan?.title, "Plan Anual de Orientación"), fontSize: 15, bold: true, color: COLORS.ink, margin: [0, 2, 0, 11] },
    {
      columns: [
        kpi("Avance promedio", `${Number(summary.average_progress || 0)}%`, `${Number(summary.completed_actions || 0)} acciones completadas`, COLORS.violet),
        kpi("Trazabilidad", `${Number(summary.traceability || 0)}%`, "Cobertura documental", COLORS.blue),
        kpi("Actividades", summary.activities || 0, `${Number(summary.completed_activities || 0)} realizadas`, COLORS.green),
        kpi("Evidencias", summary.evidences || 0, `${Number(summary.related_plans || 0)} planes indexados`, COLORS.amber),
        kpi("Alertas", Number(summary.overdue_actions || 0) + Number(summary.unscheduled_actions || 0), `${Number(summary.overdue_actions || 0)} vencidas · ${Number(summary.unscheduled_actions || 0)} sin fecha`, COLORS.red),
      ],
      columnGap: 7,
    },
    sectionTitle("Lectura ejecutiva", "Estado y control"),
    {
      columns: [
        { svg: statusChartSvg(statistics?.action_statuses || []), width: 370 },
        { svg: traceabilityChartSvg(statistics?.traceability || []), width: 370 },
      ],
      columnGap: 12,
    },
    {
      stack: [
        sectionTitle("Distribución temporal", "Calendario anual"),
        { svg: monthlyChartSvg(statistics?.monthly || []), width: 760 },
      ],
      unbreakable: true,
    },
    { text: "Desempeño por acción", style: "sectionTitle", pageBreak: "before", margin: [0, 0, 0, 8] },
    {
      table: {
        headerRows: 1,
        widths: [20, "*", 58, 38, 42, 38, 38, 76],
        body: [headerCells(["N°", "Acción", "Estado", "Avance", "Activ.", "Evid.", "Planes", "Periodo"]), ...rows],
      },
      layout: tableLayout,
      style: "table",
    },
    sectionTitle("Distribución de evidencias", "Soporte documental"),
    {
      text: (statistics?.evidence_types || []).length
        ? statistics.evidence_types.map((item) => `${item.label}: ${item.count}`).join("   ·   ")
        : "Aún no se registran evidencias para el periodo.",
      style: "body",
      fillColor: COLORS.soft,
      margin: [10, 8, 10, 8],
    },
  ];

  return baseDefinition(content, true);
}

async function download(definition, name) {
  const pdfMake = await getPdfMake();
  pdfMake.createPdf(definition).download(name);
}

export function downloadOrientationPlanPdf(plan) {
  return download(
    buildOrientationPlanPdfDefinition(plan),
    `informe-plan-orientacion-${plan.year}.pdf`
  );
}

export function downloadOrientationActionPdf(plan, action) {
  return download(
    buildOrientationActionPdfDefinition(plan, action),
    `informe-accion-${fileName(action.title)}-${plan.year}.pdf`
  );
}

export function downloadOrientationStatisticsPdf(plan, statistics) {
  return download(
    buildOrientationStatisticsPdfDefinition(plan, statistics),
    `estadisticas-orientacion-${plan.year}.pdf`
  );
}
