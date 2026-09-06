import { getPdfMake } from "../../../utils/pdfmake";
import {
  buildConvivenciaPdfDefinition,
  CONVIVENCIA_PDF_COLORS,
  pdfArray,
  pdfDate,
  pdfEmpty,
  pdfLabel,
  pdfSection,
  pdfText,
  safePdfFilePart,
} from "./convivencia-pdf-theme";

const COLORS = ["#5064D9", "#2E9F83", "#D58A22", "#C54B52", "#7295C7", "#8B6CB0"];
const valueOf = (item) => Number(item?.total ?? item?.count ?? item?.value ?? 0);
const labelOf = (item) => pdfLabel(item?.label || item?.name || item?.course || "Sin categoría");

const resolveOption = (items, value) => pdfArray(items).find((item) => String(item?.id ?? item?.value) === String(value));

const filterDescription = (payload) => {
  const filters = payload.filters || {};
  const year = resolveOption(payload.catalogs?.academic_years, filters.academic_year_id);
  const course = resolveOption(payload.catalogs?.courses, filters.course_section_id);
  return [
    year ? `Año: ${year.year || year.name || year.label}` : "Todos los años",
    course ? `Curso: ${course.display_name || course.name || course.label}` : "Todos los cursos",
    filters.semester ? `Semestre ${filters.semester}` : "Año completo",
    filters.from ? `Desde ${pdfDate(filters.from)}` : null,
    filters.to ? `Hasta ${pdfDate(filters.to)}` : null,
  ].filter(Boolean).join(" · ");
};

const rate = (value) => `${Number(value || 0).toLocaleString("es-CL", { maximumFractionDigits: 1 })}%`;

const metricGrid = (summary, dashboard) => {
  const metrics = dashboard?.metrics || {};
  const entries = [
    ["Casos abiertos", summary.open_cases ?? metrics.open_cases ?? 0, "requieren seguimiento"],
    ["Tasa de cierre", rate(summary.case_resolution_rate), `${summary.closed_cases ?? metrics.closed_cases ?? 0} casos cerrados`],
    ["Denuncias", summary.complaints ?? metrics.complaints_received ?? 0, `${rate(summary.complaint_conversion_rate)} convertidas`],
    ["Cumplimiento de medidas", rate(summary.measure_completion_rate), `${summary.completed_measures ?? 0} completadas`],
    ["Cumplimiento RICE", dashboard?.metrics ? rate(metrics.protocol_compliance_percentage) : "No disponible", dashboard?.metrics ? `${metrics.active_protocols ?? 0} protocolos activos` : "Requiere acceso al panel institucional"],
    ["Clima de convivencia", summary.climate?.percentage === null || summary.climate?.percentage === undefined ? "Sin dato" : rate(summary.climate.percentage), "último IDPS visible"],
  ];

  return {
    margin: [0, 0, 0, 8],
    table: {
      widths: ["*", "*", "*"],
      body: [0, 3].map((start) => entries.slice(start, start + 3).map(([label, value, detail], index) => ({
        margin: [9, 8, 9, 8],
        fillColor: ["#F1F4FF", "#EFF9F6", "#FFF8ED"][(start + index) % 3],
        stack: [
          { text: label.toUpperCase(), fontSize: 6, bold: true, color: CONVIVENCIA_PDF_COLORS.muted, characterSpacing: .4 },
          { text: pdfText(value), fontSize: 16, bold: true, color: CONVIVENCIA_PDF_COLORS.navy, margin: [0, 3, 0, 2] },
          { text: detail, fontSize: 6.3, color: CONVIVENCIA_PDF_COLORS.text },
        ],
      }))),
    },
    layout: { hLineWidth: () => 2, vLineWidth: () => 2, hLineColor: () => "#FFFFFF", vLineColor: () => "#FFFFFF" },
  };
};

const barChart = (title, items, color = COLORS[0], maxItems = 10) => {
  const rows = pdfArray(items).slice(0, maxItems).map((item) => ({ label: labelOf(item), total: valueOf(item) }));
  if (!rows.length) return { stack: [{ text: title, bold: true, color: CONVIVENCIA_PDF_COLORS.navy, fontSize: 8 }, pdfEmpty("Sin datos para este gráfico.")] };
  const max = Math.max(1, ...rows.map((item) => item.total));
  return {
    unbreakable: true,
    stack: [
      { text: title.toUpperCase(), bold: true, color: CONVIVENCIA_PDF_COLORS.navy, fontSize: 7.2, margin: [0, 0, 0, 5] },
      {
        table: {
          widths: [92, "*", 25],
          body: rows.map((item) => [
            { text: item.label, fontSize: 6, color: CONVIVENCIA_PDF_COLORS.text, margin: [2, 3, 2, 3] },
            { margin: [3, 6, 3, 3], canvas: [
              { type: "rect", x: 0, y: 0, w: 178, h: 5, r: 2.5, color: "#E8ECF3" },
              { type: "rect", x: 0, y: 0, w: Math.max(2, 178 * item.total / max), h: 5, r: 2.5, color },
            ] },
            { text: String(item.total), alignment: "right", bold: true, fontSize: 6, color, margin: [2, 3, 2, 3] },
          ]),
        },
        layout: { hLineWidth: () => .35, vLineWidth: () => 0, hLineColor: () => CONVIVENCIA_PDF_COLORS.line },
      },
    ],
  };
};

const courseItems = (courses, field) => pdfArray(courses).slice(0, 14).map((item) => ({
  label: item.course,
  total: Number(item?.[field] || 0),
}));

const monthlyTotals = (monthlyActivity) => pdfArray(monthlyActivity?.labels).map((label, index) => ({
  label,
  total: pdfArray(monthlyActivity?.series).reduce((total, series) => total + Number(series?.data?.[index] || 0), 0),
}));

export const CONVIVENCIA_ANALYTICS_CHART_GROUPS = [
  { key: "overview", title: "PANORAMA INSTITUCIONAL", description: "Volumen, evolución y prioridades de respuesta." },
  { key: "cases", title: "ANÁLISIS DE CASOS", description: "Tipologías, origen, estado y capacidad de cierre." },
  { key: "operations", title: "GESTIÓN OPERATIVA", description: "Denuncias, derivaciones, entrevistas, medidas y bitácora." },
  { key: "courses", title: "COMPARACIÓN POR CURSO", description: "Carga de acompañamiento y tasas de cumplimiento." },
  { key: "protocols", title: "EJECUCIÓN DE PROTOCOLOS RICE", description: "Activaciones, etapas, partes y cuellos de botella." },
];

export function buildConvivenciaAnalyticsCharts(payload) {
  const analytics = payload.report?.analytics || {};
  const dashboard = payload.dashboard || {};
  const dashboardCharts = dashboard.charts || {};
  const courses = pdfArray(analytics.courses);
  const compliance = Number(dashboard.metrics?.protocol_compliance_percentage || 0);
  const chart = (key, group, title, items, color = COLORS[0], maxItems = 10) => ({ key, group, title, items: pdfArray(items), color, maxItems });

  return [
    chart("monthly_activity", "overview", "Actividad mensual consolidada", monthlyTotals(analytics.monthly_activity), COLORS[0], 18),
    chart("activity_by_type", "overview", "Registros por tipo", analytics.activity_by_type, COLORS[0], 8),
    chart("cases_by_criticality", "overview", "Casos por criticidad", analytics.cases_by_criticality, COLORS[2], 8),
    chart("cases_by_status", "overview", "Casos por estado", analytics.cases_by_status, COLORS[1], 8),
    chart("activity_by_course", "overview", "Actividad total por curso", courseItems(courses, "activity_total"), COLORS[1], 12),
    chart("rice_compliance", "overview", "Cumplimiento RICE", dashboard.metrics ? [{ label: "Cumplimiento", total: compliance }] : [], compliance >= 80 ? COLORS[1] : compliance >= 60 ? COLORS[2] : COLORS[3], 1),

    chart("cases_by_classification", "cases", "Casos por clasificación", analytics.cases_by_classification, COLORS[0], 10),
    chart("cases_by_subclassification", "cases", "Casos por subclasificación", analytics.cases_by_subclassification, COLORS[4], 10),
    chart("cases_by_origin", "cases", "Origen de los casos", analytics.cases_by_origin, COLORS[4], 8),
    chart("cases_by_course", "cases", "Casos por curso", courseItems(courses, "total_cases"), COLORS[0], 12),
    chart("open_cases_by_course", "cases", "Casos abiertos por curso", courseItems(courses, "open_cases"), COLORS[2], 12),
    chart("case_closure_by_course", "cases", "Tasa de cierre por curso (%)", courseItems(courses, "resolution_rate"), COLORS[1], 12),

    chart("complaints_by_status", "operations", "Denuncias por estado", analytics.complaints_by_status, COLORS[3], 8),
    chart("complaints_by_type", "operations", "Denuncias por tipo", analytics.complaints_by_type, COLORS[3], 10),
    chart("complaints_by_complainant", "operations", "Tipo de denunciante", analytics.complaints_by_complainant, COLORS[5], 8),
    chart("derivations_by_status", "operations", "Derivaciones por estado", analytics.derivations_by_status, COLORS[1], 8),
    chart("derivations_by_scope", "operations", "Derivaciones internas y externas", analytics.derivations_by_scope, COLORS[4], 8),
    chart("derivations_by_priority", "operations", "Derivaciones por prioridad", analytics.derivations_by_priority, COLORS[2], 8),
    chart("interviews_by_type", "operations", "Entrevistas por tipo", analytics.interviews_by_type, COLORS[5], 10),
    chart("interviews_by_follow_up", "operations", "Seguimiento de entrevistas", analytics.interviews_by_follow_up, COLORS[5], 8),
    chart("measures_by_status", "operations", "Medidas por estado", analytics.measures_by_status, COLORS[1], 8),
    chart("measures_by_type", "operations", "Medidas por tipo", analytics.measures_by_type, COLORS[1], 10),
    chart("daily_logs_by_type", "operations", "Bitácora por tipo de evento", analytics.daily_logs_by_type, COLORS[4], 10),
    chart("daily_logs_by_status", "operations", "Bitácora por estado", analytics.daily_logs_by_status, COLORS[4], 8),

    chart("complaints_by_course", "courses", "Denuncias por curso", courseItems(courses, "complaints"), COLORS[3], 12),
    chart("daily_logs_by_course", "courses", "Registros de bitácora por curso", courseItems(courses, "daily_events"), COLORS[4], 12),
    chart("derivations_by_course", "courses", "Derivaciones por curso", courseItems(courses, "derivations"), COLORS[2], 12),
    chart("interviews_by_course", "courses", "Entrevistas por curso", courseItems(courses, "interviews"), COLORS[5], 12),
    chart("measures_by_course", "courses", "Medidas por curso", courseItems(courses, "measures"), COLORS[1], 12),
    chart("measure_completion_by_course", "courses", "Cumplimiento de medidas por curso (%)", courseItems(courses, "measure_completion_rate"), COLORS[1], 12),
    chart("overdue_measures_by_course", "courses", "Medidas vencidas por curso", courseItems(courses, "overdue_measures"), COLORS[3], 12),

    chart("activations_by_protocol", "protocols", "Activaciones por protocolo", dashboardCharts.activations_by_protocol, COLORS[0], 12),
    chart("current_stage_distribution", "protocols", "Activaciones por etapa actual", dashboardCharts.current_stage_distribution, COLORS[4], 12),
    chart("protocol_steps_by_status", "protocols", "Etapas por estado", dashboardCharts.protocol_steps_by_status, COLORS[0], 8),
    chart("protocol_parts_by_status", "protocols", "Partes por estado", dashboardCharts.protocol_parts_by_status, COLORS[1], 8),
    chart("parts_by_category", "protocols", "Partes por categoría", dashboardCharts.parts_by_category, COLORS[4], 10),
    chart("protocol_bottlenecks", "protocols", "Cuellos de botella", dashboardCharts.bottlenecks, COLORS[2], 10),
  ];
}

const compactTable = (headers, rows, widths = null, fontSize = 6) => ({
  table: {
    headerRows: 1,
    widths: widths || headers.map((_, index) => index === 0 ? "*" : "auto"),
    body: [
      headers.map((header) => ({ text: header, bold: true, color: "#FFFFFF", fillColor: CONVIVENCIA_PDF_COLORS.navy, fontSize, margin: [4, 4, 4, 4] })),
      ...rows.map((row) => row.map((value) => ({ text: pdfText(value), color: CONVIVENCIA_PDF_COLORS.text, fontSize, margin: [4, 3, 4, 3] }))),
    ],
  },
  layout: {
    hLineWidth: () => .35,
    vLineWidth: () => .35,
    hLineColor: () => CONVIVENCIA_PDF_COLORS.line,
    vLineColor: () => CONVIVENCIA_PDF_COLORS.line,
    fillColor: (row) => row > 0 && row % 2 === 0 ? "#F7F9FC" : null,
  },
  margin: [0, 0, 0, 8],
});

const detailDefinitions = [
  ["cases", "CASOS", ["Folio", "Fecha", "Clasificación", "Criticidad", "Estado"], (item) => [item.folio, pdfDate(item.opened_at), item.classification_label, item.criticality_label, pdfLabel(item.status)], [68, 58, "*", 68, 58]],
  ["complaints", "DENUNCIAS", ["Folio", "Fecha", "Tipo", "Denunciante", "Estado"], (item) => [item.folio, pdfDate(item.received_at), item.situation_type_label, pdfLabel(item.complainant_type), pdfLabel(item.status)], [68, 58, "*", 66, 62]],
  ["daily_logs", "BITÁCORA", ["Fecha", "Tipo", "Descripción", "Estado"], (item) => [pdfDate(item.happened_at), item.daily_log_type_label, item.description, pdfLabel(item.status)], [62, 88, "*", 62]],
  ["derivations", "DERIVACIONES", ["Fecha", "Ámbito", "Destino", "Prioridad", "Estado"], (item) => [pdfDate(item.derived_at), pdfLabel(item.scope), item.destination_label, pdfLabel(item.priority_level), pdfLabel(item.status)], [62, 57, "*", 58, 64]],
  ["interviews", "ENTREVISTAS", ["Fecha", "Tipo", "Motivo", "Seguimiento"], (item) => [pdfDate(item.interview_at), item.interview_type_label, item.motive, pdfLabel(item.follow_up_status)], [62, 85, "*", 75]],
  ["measures", "MEDIDAS", ["Asignada", "Tipo", "Vencimiento", "Estado"], (item) => [pdfDate(item.assigned_at), item.measure_type_label, pdfDate(item.due_at), pdfLabel(item.status)], [62, "*", 62, 68]],
];

export function buildConvivenciaAnalyticsPdfDefinition(payload, generatedAt = null) {
  const report = payload.report || {};
  const dashboard = payload.dashboard || {};
  const summary = report.summary || {};
  const analytics = report.analytics || {};
  const lists = payload.lists || report.lists || {};
  const courses = pdfArray(analytics.courses);
  const filtersText = filterDescription(payload);
  const chartDefinitions = buildConvivenciaAnalyticsCharts(payload);
  const populatedCharts = chartDefinitions.filter((chart) => chart.items.some((item) => valueOf(item) > 0));
  const chartContent = [];
  CONVIVENCIA_ANALYTICS_CHART_GROUPS.forEach((group) => {
    const groupCharts = populatedCharts.filter((chart) => chart.group === group.key);
    if (!groupCharts.length) return;
    const chartsPerPage = group.key === "courses" ? 8 : 6;
    for (let pageStart = 0; pageStart < groupCharts.length; pageStart += chartsPerPage) {
      const pageCharts = groupCharts.slice(pageStart, pageStart + chartsPerPage);
      const pageStack = [];
      if (chartContent.length === 0) {
        pageStack.push(pdfSection(2, "MAPA VISUAL DE GESTIÓN", `${chartDefinitions.length} gráficos definidos; se incorporan únicamente los que tienen datos para el ámbito autorizado.`));
      }
      pageStack.push({
        margin: [0, 2, 0, 8],
        table: {
          widths: ["*", "auto"],
          body: [[
            { stack: [{ text: `${group.title}${pageStart ? " (CONTINUACIÓN)" : ""}`, bold: true, fontSize: 8, color: CONVIVENCIA_PDF_COLORS.navy }, { text: group.description, fontSize: 6.2, color: CONVIVENCIA_PDF_COLORS.muted, margin: [0, 2, 0, 0] }], border: [false, false, false, false] },
            { text: `${pageCharts.length} de ${groupCharts.length}`, bold: true, fontSize: 6.3, color: CONVIVENCIA_PDF_COLORS.indigo, fillColor: "#EEF1FF", margin: [6, 4, 6, 4], border: [false, false, false, false] },
          ]],
        },
        layout: "noBorders",
      });
      for (let index = 0; index < pageCharts.length; index += 2) {
        const pair = pageCharts.slice(index, index + 2);
        pageStack.push({
          unbreakable: true,
          columns: pair.map((chart) => ({ width: pair.length === 1 ? "100%" : "50%", ...barChart(chart.title, chart.items, chart.color, chart.maxItems) })),
          columnGap: 12,
          margin: [0, 0, 0, 10],
        });
      }
      chartContent.push({ pageBreak: "before", margin: [0, 28, 0, 0], stack: pageStack });
    }
  });
  if (!chartContent.length) chartContent.push(pdfEmpty("No existen datos suficientes para construir gráficos con los filtros aplicados."));
  const content = [
    { text: filtersText, fontSize: 7, color: CONVIVENCIA_PDF_COLORS.muted, margin: [2, 0, 0, 5] },
    { text: "Este informe consolida únicamente la información autorizada para el usuario emisor. Los volúmenes son descriptivos y no constituyen una calificación de estudiantes o cursos.", fontSize: 6.5, color: CONVIVENCIA_PDF_COLORS.text, margin: [2, 0, 0, 5] },
    pdfSection(1, "RESUMEN EJECUTIVO", "Indicadores institucionales calculados con el mismo ámbito y período."),
    metricGrid(summary, dashboard),
    ...chartContent,
    { ...pdfSection(3, "ESTADÍSTICAS POR CURSO", "Comparación de registros y cumplimiento. La actividad total no es una escala de desempeño."), pageBreak: "before", margin: [0, 28, 0, 7] },
    ...(courses.length ? [compactTable(
      ["Curso", "Casos", "Abiertos", "Denuncias", "Bitácora", "Deriv.", "Entrev.", "Medidas", "% cierre", "% medidas", "Vencidas"],
      courses.map((item) => [item.course, item.total_cases, item.open_cases, item.complaints, item.daily_events, item.derivations, item.interviews, item.measures, rate(item.resolution_rate), rate(item.measure_completion_rate), item.overdue_measures]),
      ["*", 35, 40, 43, 42, 38, 38, 40, 43, 46, 40],
      5.4,
    )] : [pdfEmpty("No existen registros vinculados a cursos para los filtros aplicados.")]),
    {
      unbreakable: true,
      stack: [
        pdfSection(4, "CONTROL Y ALERTAS", "Seguimientos pendientes, vencimientos y cumplimiento de protocolos RICE."),
        compactTable(
          ["Indicador", "Resultado", "Lectura"],
          [
            ["Medidas vencidas", summary.alerts?.overdue_measures ?? 0, "Requieren regularización"],
            ["Derivaciones pendientes", summary.pending_derivations ?? 0, "Sin respuesta o cierre"],
            ["Seguimientos de entrevista", summary.pending_interview_follow_ups ?? 0, "Pendientes o reprogramados"],
            ["Etapas de protocolo vencidas", dashboard.metrics ? dashboard.metrics.overdue_protocol_steps ?? 0 : "No disponible", "Según acceso y vencimiento calculado"],
            ["Partes de protocolo vencidas", dashboard.metrics ? dashboard.metrics.overdue_protocol_parts ?? 0 : "No disponible", "Según acceso y vencimiento calculado"],
            ["Promedio de cierre de casos", summary.average_case_closure_days === null || summary.average_case_closure_days === undefined ? "Sin muestra" : `${summary.average_case_closure_days} días`, "Casos cerrados visibles"],
          ],
          ["*", 90, "*"],
          6.2,
        ),
      ],
    },
    { text: "ANEXO DOCUMENTAL", fontSize: 14, bold: true, color: CONVIVENCIA_PDF_COLORS.navy, margin: [0, 12, 0, 3] },
    { text: "Registros completos incluidos en la exportación, agrupados por tipo.", fontSize: 7, color: CONVIVENCIA_PDF_COLORS.muted, margin: [0, 0, 0, 8] },
  ];

  const populatedDetails = detailDefinitions.filter(([key]) => pdfArray(lists[key]).length > 0);
  if (!populatedDetails.length) {
    content.push(pdfEmpty("No existen registros documentales para los filtros aplicados."));
  }
  populatedDetails.forEach(([key, title, headers, mapper, widths], index) => {
    const rows = pdfArray(lists[key]);
    content.push(pdfSection(index + 5, title, `${rows.length} registro(s) autorizado(s).`));
    const table = compactTable(headers, rows.map(mapper), widths, 5.8);
    if (index === populatedDetails.length - 1) table.margin = [0, 0, 0, 0];
    content.push(table);
  });

  const definition = buildConvivenciaPdfDefinition({
    title: "Informe analítico de convivencia escolar",
    kicker: "Convivencia Escolar | Análisis e informes",
    code: `INFORME-${new Date(generatedAt || Date.now()).getFullYear()}`,
    status: "vigente",
    subtitle: filtersText,
    sensitive: true,
    generatedAt: generatedAt || new Date().toISOString(),
    content,
    info: { subject: "Informe estadístico y documental de Convivencia Escolar" },
  });
  definition.pageOrientation = "landscape";
  definition.pageMargins = [34, 58, 34, 48];
  return definition;
}

export async function downloadConvivenciaAnalyticsPdf(payload) {
  const generatedAt = new Date().toISOString();
  const definition = buildConvivenciaAnalyticsPdfDefinition(payload, generatedAt);
  const course = resolveOption(payload.catalogs?.courses, payload.filters?.course_section_id);
  const scope = safePdfFilePart(course?.display_name || course?.name || "institucional");
  (await getPdfMake()).createPdf(definition).download(`informe-convivencia-${scope}.pdf`);
}

export { barChart, compactTable, filterDescription, metricGrid };
