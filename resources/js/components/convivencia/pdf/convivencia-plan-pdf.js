import { getPdfMake } from "../../../utils/pdfmake";
import {
  buildConvivenciaPdfDefinition,
  CONVIVENCIA_PDF_COLORS,
  pdfArray,
  pdfDate,
  pdfFacts,
  pdfLabel,
  pdfRecord,
  pdfSection,
  pdfText,
  safePdfFilePart,
} from "./convivencia-pdf-theme";

const months = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

const tableLayout = {
  fillColor: (row) => row === 0 ? CONVIVENCIA_PDF_COLORS.navy : row % 2 === 0 ? "#F7F9FC" : null,
  hLineColor: () => CONVIVENCIA_PDF_COLORS.line,
  vLineColor: () => CONVIVENCIA_PDF_COLORS.line,
  paddingLeft: () => 6,
  paddingRight: () => 6,
  paddingTop: () => 5,
  paddingBottom: () => 5,
};

const header = (text) => ({ text, bold: true, color: "#FFFFFF", fontSize: 7 });
const join = (value) => pdfArray(value).map((item) => typeof item === "string" ? item : item?.title || item?.name).filter(Boolean).join("\n");

export function buildConvivenciaPlanPdfDefinition(plan) {
  const actions = pdfArray(plan?.actions);
  const indicators = pdfArray(plan?.evaluation_indicators);
  const protocol = pdfArray(plan?.institutional_protocol);
  const versions = pdfArray(plan?.versions);
  const stats = plan?.stats || {};
  const actionRows = actions.map((action) => [
    months[Number(action.planned_month || 0) - 1]
      ? `${months[Number(action.planned_month || 0) - 1]} ${plan?.calendar_year || ""}`
      : `${pdfDate(action.starts_on)}\n${pdfDate(action.ends_on)}${action.is_multi_year ? "\nMultianual" : ""}`,
    { text: pdfText(action.title), bold: true },
    pdfText(action.target_audience),
    pdfText(action.responsible_user?.name || action.responsible_staff?.full_name || action.responsible_label),
    pdfLabel(action.status),
    { text: `${Number(action.weight_percent || 0)}%`, alignment: "center" },
    { text: `${Number(action.advance_percentage || 0)}%`, alignment: "center", bold: true },
  ]);

  const content = [
    {
      columns: [
        { width: "*", stack: [
          { text: pdfText(plan?.name), fontSize: 13, bold: true, color: CONVIVENCIA_PDF_COLORS.ink },
          { text: `Versión ${Number(plan?.version_number || 1)} · ${Number(plan?.advance_percentage || 0)}% de avance`, color: CONVIVENCIA_PDF_COLORS.indigo, bold: true, margin: [0, 4, 0, 0] },
        ] },
        { width: 210, text: `Acciones ${Number(stats.actions || actions.length)} · Ponderación ${Number(stats.action_weight_allocated || 0)}%\nActividades ${Number(stats.activities || 0)} · Evidencias ${Number(stats.evidences || 0)}`, alignment: "right", color: CONVIVENCIA_PDF_COLORS.muted },
      ],
      margin: [0, 0, 0, 10],
    },
    pdfSection(1, "Identificación y objetivos", "Definición vigente del instrumento anual"),
    pdfFacts([
      ["Año", plan?.calendar_year],
      ["Plan anterior", plan?.previous_plan ? `${plan.previous_plan.calendar_year} · versión ${plan.previous_plan.version_number}` : null],
      ["Responsable", plan?.responsible_user?.name || plan?.responsible_staff?.full_name],
      ["Vigencia", `${pdfDate(plan?.starts_on)} a ${pdfDate(plan?.ends_on)}`],
      ["Objetivo general", plan?.general_objective],
      ["Objetivos específicos", join(plan?.specific_objectives)],
      ["Recursos", plan?.resources_required],
      ["Medios de verificación", plan?.verification_means_summary],
      ["Documento fuente", plan?.source_document_name],
      ["Documentos resguardados", pdfArray(plan?.attachments).map((file) => file.original_name).join(", ")],
    ]),
    pdfSection(2, "Protocolo de acogida institucional", "Componentes editables incorporados desde el documento 2026"),
    ...(protocol.length ? protocol.map((item, index) => pdfRecord(
      `${index + 1}. ${pdfText(item.title)}`,
      pdfText(item.timing, ""),
      [
        ["Descripción", item.description],
        ["Responsable", item.responsible],
        ["Requisitos", item.requirements],
      ],
      "protected",
    )) : [{ text: "Sin componentes registrados.", color: CONVIVENCIA_PDF_COLORS.muted }]),
    pdfSection(3, "Indicadores de evaluación", "Metas y fuentes para seguimiento"),
    {
      table: {
        headerRows: 1,
        widths: [42, 92, "*", 48, 64, 90],
        body: [
          ["Código", "Indicador", "Definición", "Meta", "Frecuencia", "Verificación"].map(header),
          ...(indicators.length ? indicators.map((item) => [
            pdfText(item.code), pdfText(item.title), pdfText(item.description), `${pdfText(item.target_value)} ${pdfText(item.target_unit, "")}`.trim(), pdfText(item.frequency), pdfText(item.verification_source),
          ]) : [[{ text: "Sin indicadores", colSpan: 6, alignment: "center" }, {}, {}, {}, {}, {}]]),
        ],
      },
      layout: tableLayout,
    },
    pdfSection(4, "Cronograma anual de acciones", "Planificación, responsables y avance"),
    {
      table: {
        headerRows: 1,
        widths: [56, "*", 66, 66, 48, 30, 34],
        body: [
          ["Vigencia", "Acción", "Público", "Responsable", "Estado", "Peso", "Avance"].map(header),
          ...(actionRows.length ? actionRows : [[{ text: "Sin acciones", colSpan: 7, alignment: "center" }, {}, {}, {}, {}, {}, {}]]),
        ],
      },
      layout: tableLayout,
    },
    pdfSection(5, "Ejecución y evidencias", "Actividades realizadas por acción"),
    ...actions.flatMap((action) => {
      const activities = pdfArray(action.activities);
      return [pdfRecord(
        action.title,
        `${Number(action.advance_percentage || 0)}% de avance · peso ${Number(action.weight_percent || 0)}% · ${activities.length} actividades${action.is_multi_year ? " · multianual" : ""}`,
        [
          ["Objetivo", action.objective],
          ["Indicador", action.indicator_summary],
          ["Verificación planificada", action.verification_means],
        ],
        "active",
      ), ...activities.map((activity) => pdfRecord(
        activity.title,
        `${pdfDate(activity.starts_at, true)} · ${pdfLabel(activity.status)}`,
        [
          ["Lugar", activity.location],
          ["Tipo", activity.activity_type?.name || activity.activity_type_label],
          ["Público", activity.target_audience],
          ["Asistentes", activity.attendee_count],
          ["Resultados", activity.results],
          ["Aporte", `${Number(activity.contribution_percent || 0)}%`],
          ["Ejecución", `${Number(activity.completion_percent || 0)}% · ${Number(activity.earned_progress || 0)} puntos obtenidos`],
          ["Evidencias", pdfArray(activity.attachments).map((file) => file.original_name).join(", ")],
        ],
        activity.status === "realizada" ? "protected" : "default",
      ))];
    }),
    pdfSection(6, "Vinculación reglamentaria", "Texto sujeto a revisión institucional antes de su publicación"),
    pdfRecord(
      plan?.regulatory_review_required ? "Revisión requerida" : "Texto revisado",
      "RICE",
      [["Contenido", plan?.regulatory_linkage_text]],
      plan?.regulatory_review_required ? "warning" : "protected",
      false,
    ),
    pdfSection(7, "Historial de versiones", "Trazabilidad de cambios de la definición del plan"),
    {
      table: {
        headerRows: 1,
        widths: [42, 92, "*", 110],
        body: [
          ["Versión", "Fecha", "Motivo", "Responsable"].map(header),
          ...(versions.length ? versions.map((version) => [
            `v${version.version_number}`, pdfDate(version.created_at, true), pdfText(version.change_summary), pdfText(version.created_by?.name),
          ]) : [[{ text: "Sin versiones", colSpan: 4, alignment: "center" }, {}, {}, {}]]),
        ],
      },
      layout: tableLayout,
    },
  ];

  return buildConvivenciaPdfDefinition({
    title: "Plan de gestión de la convivencia escolar",
    kicker: "Plan anual institucional",
    code: `PLAN-${plan?.calendar_year || "ANUAL"}-V${plan?.version_number || 1}`,
    status: plan?.status,
    subtitle: "Objetivos, protocolo de acogida, indicadores, cronograma, ejecución, evidencias y control de versiones",
    sensitive: plan?.is_sensitive === true,
    content,
    info: { subject: "Plan de Gestión de la Convivencia Escolar" },
  });
}

export async function downloadConvivenciaPlanPdf(plan) {
  const pdfMake = await getPdfMake();
  const filename = `plan-convivencia-${safePdfFilePart(plan?.calendar_year || "anual")}-v${Number(plan?.version_number || 1)}.pdf`;
  pdfMake.createPdf(buildConvivenciaPlanPdfDefinition(plan)).download(filename);
  return filename;
}
