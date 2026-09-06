import { getPdfMake } from "../../../utils/pdfmake";
import { CONVIVENCIA_DASHBOARD_METRIC_LABELS as METRIC_LABELS } from "../dashboard/dashboard-metrics";
import { convivenciaProtocolPartCategoryLabel } from "../protocol-runtime-labels";
import {
  CONVIVENCIA_PDF_COLORS,
  buildConvivenciaPdfDefinition,
  pdfArray,
  pdfDate,
  pdfEmpty,
  pdfLabel,
  pdfRecord,
  pdfSection,
  pdfText,
  safePdfFilePart,
} from "./convivencia-pdf-theme";

const dataOf = (payload) => payload?.data?.data || payload?.data || payload || {};
const labelOf = (item) => item?.label || item?.name || item?.protocol_name || item?.category || item?.stage_name || "Sin categoría";
const totalOf = (item) => Number(item?.total ?? item?.count ?? item?.value ?? item?.activations ?? 0);

const resolveDashboardPeriod = (payload, data = dataOf(payload), filters = payload?.filters || data?.filters || {}) => {
  const selectedId = filters.academic_year_id;
  if (selectedId === null || selectedId === undefined || selectedId === "") return null;
  const years = pdfArray(payload?.catalogs?.academic_years || data?.catalogs?.academic_years);
  const selected = years.find((item) => String(item?.id ?? item?.value) === String(selectedId));
  const rawLabel = filters.academic_year_label
    || filters.academic_year_name
    || selected?.year
    || selected?.label
    || selected?.text
    || selected?.name
    || selectedId;
  const year = String(rawLabel).match(/\b(?:19|20)\d{2}\b/)?.[0];
  return year || String(rawLabel).trim();
};

const metricEntries = (metrics) => {
  if (Array.isArray(metrics)) return metrics.map((item) => ({ key: item.key || item.code || item.label, label: item.label || METRIC_LABELS[item.key] || pdfLabel(item.key), value: item.value ?? item.total ?? item.count, suffix: item.suffix || "" }));
  return Object.entries(metrics || {}).map(([key, raw]) => {
    const object = raw && typeof raw === "object" ? raw : null;
    return { key, label: object?.label || METRIC_LABELS[key] || pdfLabel(key), value: object?.value ?? object?.total ?? object?.count ?? raw, suffix: object?.suffix || (key.includes("percentage") || key.includes("rate") ? "%" : "") };
  });
};

const chartItems = (data, ...keys) => {
  for (const key of keys) {
    const value = key.split(".").reduce((current, segment) => current?.[segment], data);
    if (Array.isArray(value) && value.length) return value;
  }
  return [];
};

const barList = (items, color = CONVIVENCIA_PDF_COLORS.indigo, labelResolver = labelOf) => {
  const values = pdfArray(items).map((item) => ({ label: labelResolver(item), total: totalOf(item) }));
  const max = Math.max(1, ...values.map((item) => item.total));
  return {
    margin: [0, 0, 0, 8],
    table: {
      widths: [145, "*", 38],
      body: values.map((item) => [
        { text: pdfText(item.label), fontSize: 7, color: CONVIVENCIA_PDF_COLORS.text, margin: [4, 4, 4, 4] },
        {
          margin: [4, 7, 4, 4],
          canvas: [
            { type: "rect", x: 0, y: 0, w: 285, h: 6, r: 3, color: "#E8ECF3" },
            { type: "rect", x: 0, y: 0, w: Math.max(3, 285 * item.total / max), h: 6, r: 3, color },
          ],
        },
        { text: String(item.total), bold: true, alignment: "right", fontSize: 7, color, margin: [4, 4, 4, 4] },
      ]),
    },
    layout: { hLineWidth: () => .35, vLineWidth: () => 0, hLineColor: () => CONVIVENCIA_PDF_COLORS.line },
  };
};

const alertItems = (data) => {
  const source = data.alerts || data.insights?.alerts || [];
  if (Array.isArray(source)) return source;
  return Object.entries(source).flatMap(([type, entries]) => pdfArray(entries).map((entry) => typeof entry === "object" ? { ...entry, type } : { title: entry, type }));
};

export function buildConvivenciaDashboardPdfDefinition(payload, generatedAt = null) {
  const data = dataOf(payload);
  const metrics = metricEntries(data.metrics);
  const activations = chartItems(data, "charts.activations_by_protocol", "charts.protocol_activations", "insights.activations_by_protocol");
  const parts = chartItems(data, "charts.parts_by_category", "charts.protocol_parts_by_category", "insights.parts_by_category");
  const bottlenecks = chartItems(data, "charts.bottlenecks", "insights.bottlenecks", "insights.protocol_bottlenecks");
  const alerts = alertItems(data);
  const recent = [
    ...pdfArray(data.recent?.cases).map((item) => ({ ...item, _kind: "Caso" })),
    ...pdfArray(data.recent?.complaints).map((item) => ({ ...item, _kind: "Denuncia" })),
    ...pdfArray(data.recent?.protocols || data.recent?.activations).map((item) => ({ ...item, _kind: "Protocolo" })),
  ].slice(0, 12);
  const filters = payload?.filters || data.filters || {};
  const period = resolveDashboardPeriod(payload, data, filters);

  const content = [
    pdfSection(1, "INDICADORES CLAVE", "Estado consolidado del período y ámbito seleccionados."),
    ...(metrics.length ? metrics.map((metric) => pdfRecord(metric.label, "Indicador", [["Resultado", `${pdfText(metric.value)}${metric.suffix}`]], metric.key.includes("overdue") && Number(metric.value) > 0 ? "warning" : "default")) : [pdfEmpty("No hay indicadores disponibles para los filtros aplicados.")]),
    pdfSection(2, "CUMPLIMIENTO Y CONTROL DE PLAZOS", "Activaciones, etapas vencidas o próximas y puntos de congestión."),
    ...(alerts.length ? alerts.map((alert) => pdfRecord(
      alert.title || alert.label || alert.message || "Alerta operativa",
      pdfLabel(alert.severity || alert.level || alert.type),
      [
        ["Detalle", alert.description || alert.message || alert.detail],
        ["Cantidad", alert.total ?? alert.count],
        ["Vencimiento", pdfDate(alert.due_at, true)],
        ["Protocolo / etapa", alert.protocol_name || alert.stage_name],
      ],
      ["high", "critical", "overdue", "danger"].includes(alert.severity || alert.type) ? "warning" : "default",
      true,
    )) : [pdfEmpty("No se informan alertas operativas en este período.")]),
    ...(bottlenecks.length ? [
      { text: "CUELLOS DE BOTELLA", bold: true, fontSize: 7.5, color: CONVIVENCIA_PDF_COLORS.navy, margin: [0, 6, 0, 5] },
      barList(bottlenecks, CONVIVENCIA_PDF_COLORS.warning),
    ] : []),
    pdfSection(3, "ACTIVACIONES POR PROTOCOLO", "Volumen de rutas activadas según la definición institucional."),
    ...(activations.length ? [barList(activations)] : [pdfEmpty("El backend no entregó una distribución de activaciones por protocolo.")]),
    pdfSection(4, "PARTES POR CATEGORÍA", "Distribución de sanciones, medidas protectoras, documentos y demás componentes reutilizables."),
    ...(parts.length ? [barList(
      parts,
      CONVIVENCIA_PDF_COLORS.teal,
      (item) => convivenciaProtocolPartCategoryLabel(item?.category || item?.label || item?.name),
    )] : [pdfEmpty("El backend no entregó una distribución de partes por categoría.")]),
    pdfSection(5, "ACTIVIDAD RECIENTE", `${recent.length} registro(s) consolidados sin ampliar su nivel de detalle sensible.`),
    ...(recent.length ? recent.map((item) => pdfRecord(
      item.folio || item.protocol?.name || item.protocol_name || `Registro #${item.id || "-"}`,
      item._kind,
      [
        ["Estado", pdfLabel(item.status)],
        ["Clasificación / etapa", item.classification_label || item.situation_type_label || item.current_stage_name],
        ["Fecha", pdfDate(item.opened_at || item.received_at || item.activated_at || item.created_at, true)],
      ],
      "default",
      true,
    )) : [pdfEmpty("No hay actividad reciente visible.")]),
  ];

  return buildConvivenciaPdfDefinition({
    title: "Panel de gestión de convivencia",
    kicker: "Convivencia Escolar | Control ejecutivo",
    code: period ? `PERIODO-${period}` : "RESUMEN-INSTITUCIONAL",
    status: "vigente",
    subtitle: "Cumplimiento, alertas y distribución de protocolos",
    sensitive: true,
    generatedAt: generatedAt || payload?.generated_at || new Date().toISOString(),
    content,
    info: { subject: "Panel estadístico de Convivencia Escolar" },
  });
}

export async function downloadConvivenciaDashboardPdf(payload) {
  const definition = buildConvivenciaDashboardPdfDefinition(payload, payload?.generated_at);
  const period = safePdfFilePart(resolveDashboardPeriod(payload) || new Date().getFullYear());
  (await getPdfMake()).createPdf(definition).download(`panel-convivencia-${period}.pdf`);
}

export { alertItems, chartItems, dataOf, metricEntries, resolveDashboardPeriod };
