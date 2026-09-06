import { getPdfMake } from "../../../utils/pdfmake";
import {
  CONVIVENCIA_PDF_COLORS,
  buildConvivenciaPdfDefinition,
  deadlineFallbackText,
  pdfArray,
  pdfDate,
  pdfEmpty,
  pdfFacts,
  pdfLabel,
  pdfRecord,
  pdfSection,
  pdfStructuredText,
  pdfText,
  safePdfFilePart,
} from "./convivencia-pdf-theme";

const resolveActivation = (payload) => payload?.data || payload?.activation || payload || {};

const activationSteps = (activation) => pdfArray(
  activation.runtime_steps || activation.runtimeSteps || activation.steps || activation.protocol_snapshot?.steps,
).slice().sort((a, b) => Number(a.step_order ?? a.order ?? 0) - Number(b.step_order ?? b.order ?? 0));

const runtimeParts = (step) => pdfArray(step?.parts || step?.runtime_parts || step?.part_links);

const globalRuntimeParts = (activation) => pdfArray(
  activation.runtime_parts || activation.runtimeParts,
).filter((part) => !part.activation_step_id);

const stepCompletionRule = (step) => step?.snapshot?.completion_rule || step?.completion_rule || null;

const stepCompletionCriteria = (step) => step?.data?.completion_criteria || step?.completion_criteria || null;

const runtimePartAudit = (runtimePart, definition = runtimePart) => {
  const snapshot = runtimePart?.snapshot || {};
  const data = runtimePart?.data || {};
  return {
    required: runtimePart?.is_required ?? snapshot.is_required ?? runtimePart?.pivot?.is_required ?? definition?.is_required,
    condition: snapshot.condition ?? runtimePart?.condition ?? runtimePart?.pivot?.condition ?? definition?.condition,
    configuration: snapshot.configuration ?? runtimePart?.configuration ?? runtimePart?.pivot?.configuration ?? definition?.configuration,
    conditionConfirmed: data.condition_confirmed,
  };
};

const completionValue = (part) => {
  const status = part.status || part.runtime_status || part.pivot?.status || "pending";
  const notes = part.notes || part.completion_notes || part.pivot?.notes;
  return [pdfLabel(status), notes].filter(Boolean).join(" | ");
};

const progressTone = (value) => {
  if (["overdue", "vencido"].includes(value)) return CONVIVENCIA_PDF_COLORS.danger;
  if (["due_soon", "proximo", "próximo"].includes(value)) return CONVIVENCIA_PDF_COLORS.warning;
  return CONVIVENCIA_PDF_COLORS.success;
};

const progressPanel = (progress = {}) => {
  const percentage = Math.max(0, Math.min(100, Number(progress.percentage || 0)));
  const accent = progressTone(progress.deadline_status);
  const completed = Number(progress.completed_steps || 0);
  const total = Number(progress.total_steps || 0);
  return {
    unbreakable: true,
    table: {
      widths: [86, "*", 110],
      body: [[
        {
          fillColor: "#F1F4FF",
          margin: [10, 9, 10, 9],
          stack: [
            { text: `${percentage}%`, fontSize: 20, bold: true, color: CONVIVENCIA_PDF_COLORS.indigo },
            { text: "AVANCE", fontSize: 6, bold: true, color: CONVIVENCIA_PDF_COLORS.muted },
          ],
        },
        {
          fillColor: "#F8FAFC",
          margin: [10, 9, 10, 9],
          stack: [
            { text: progress.label || `Paso ${progress.current_order || 0} de ${total}`, bold: true, fontSize: 10, color: CONVIVENCIA_PDF_COLORS.navy },
            { text: `${completed} de ${total} etapas completadas`, fontSize: 7, color: CONVIVENCIA_PDF_COLORS.text, margin: [0, 4, 0, 0] },
            {
              margin: [0, 7, 0, 0],
              canvas: [
                { type: "rect", x: 0, y: 0, w: 260, h: 7, r: 3.5, color: "#E3E8F0" },
                { type: "rect", x: 0, y: 0, w: Math.max(3, 260 * percentage / 100), h: 7, r: 3.5, color: CONVIVENCIA_PDF_COLORS.indigo },
              ],
            },
          ],
        },
        {
          fillColor: "#F8FAFC",
          alignment: "right",
          margin: [10, 9, 10, 9],
          stack: [
            { text: pdfLabel(progress.deadline_status), bold: true, fontSize: 8, color: accent },
            { text: pdfDate(progress.due_at, true), fontSize: 7, color: CONVIVENCIA_PDF_COLORS.text, margin: [0, 4, 0, 0] },
          ],
        },
      ]],
    },
    layout: {
      hLineWidth: () => 0.5,
      vLineWidth: () => 0.5,
      hLineColor: () => CONVIVENCIA_PDF_COLORS.line,
      vLineColor: () => CONVIVENCIA_PDF_COLORS.line,
    },
    margin: [0, 0, 0, 10],
  };
};

export function buildConvivenciaActivationPdfDefinition(payload, generatedAt = null) {
  const activation = resolveActivation(payload);
  const protocol = activation.protocol_snapshot || activation.protocol || {};
  const steps = activationSteps(activation);
  const progress = activation.progress || {};
  const caseData = activation.case || {};
  const complaint = activation.complaint || {};
  const reference = caseData.folio || complaint.folio || `ACT-${activation.id || "RICE"}`;
  const currentStepId = activation.current_activation_step_id || activation.current_runtime_step_id || activation.current_step_id || progress.current_step_id;
  const currentStep = steps.find((step) => Number(step.id) === Number(currentStepId))
    || steps.find((step) => step.status === "in_progress")
    || null;
  const currentDeadlineWarning = deadlineFallbackText(currentStep, "");
  const timeline = pdfArray(activation.timeline || activation.logs || activation.status_logs);
  const globalParts = globalRuntimeParts(activation);

  const content = [
    pdfFacts([
      ["Caso / denuncia", reference],
      ["Protocolo", protocol.name || activation.protocol_name],
      ["Código / versión", [protocol.code, protocol.version_label || protocol.version].filter(Boolean).join(" | ")],
      ["Activado por", activation.activated_by?.name || activation.activatedBy?.name],
      ["Fecha de activación", pdfDate(activation.activated_at || activation.created_at, true)],
      ["Estado", pdfLabel(activation.status)],
    ]),
    pdfSection(1, "ESTADO ACTUAL", "Posición del caso dentro de la ruta vigente y control de plazo."),
    ...(currentDeadlineWarning ? [pdfRecord(
      "Plazo no confirmado",
      "Cálculo alternativo",
      [["Advertencia", currentDeadlineWarning]],
      "warning",
      false,
    )] : []),
    progressPanel(progress),
    pdfFacts([
      ["Etapa actual", activation.current_stage_name || steps.find((step) => Number(step.id) === Number(currentStepId))?.stage_name],
      ["Vencimiento actual", pdfDate(progress.due_at || activation.due_at, true)],
      ["Acciones realizadas", activation.actions_taken],
      ["Medidas adoptadas", activation.measures_adopted],
      ["Resumen de cierre", activation.closing_summary],
    ]),
    pdfSection(2, "RECORRIDO DEL PROTOCOLO", `${steps.length} etapa(s) instanciadas para esta activación.`),
    ...(steps.length
      ? steps.map((step, index) => {
          const status = step.status || step.runtime_status || (Number(step.id) === Number(currentStepId) ? "in_progress" : "pending");
          const parts = runtimeParts(step);
          const completionRule = stepCompletionRule(step);
          const completionCriteria = stepCompletionCriteria(step);
          return pdfRecord(
            `${index + 1}. ${step.stage_name || step.name || "Etapa"}`,
            pdfLabel(status),
            [
              ["Responsable", step.responsible_label || step.responsible_role || step.responsible?.name],
              ["Inicio", pdfDate(step.started_at, true)],
              ["Vencimiento", pdfDate(step.due_at, true)],
              ["Advertencia de cálculo", deadlineFallbackText(step, "")],
              ["Término", pdfDate(step.completed_at, true)],
              ["Descripción", step.description],
              ["Resultado / notas", step.completion_notes || step.notes || step.result],
              ["Regla de término", pdfStructuredText(completionRule, "")],
              ["Criterios de término registrados", pdfStructuredText(completionCriteria, "")],
              ["Partes", parts.map((part) => `${part.part?.name || part.name || part.title || part.code}: ${completionValue(part)}`).join("\n")],
            ],
            Number(step.id) === Number(currentStepId) || ["active", "in_progress"].includes(status) ? "active" : status === "completed" ? "protected" : "default",
            false,
          );
        })
      : [pdfEmpty("La activación no contiene etapas de ejecución.")]),
    pdfSection(3, "MEDIDAS, SANCIONES Y REQUISITOS", "Estado individual de las partes vinculadas a cada etapa."),
    ...(globalParts.length ? [
      { text: "REQUISITOS GENERALES DEL PROTOCOLO", bold: true, fontSize: 7.5, color: CONVIVENCIA_PDF_COLORS.navy, margin: [0, 5, 0, 5] },
      ...globalParts.map((runtimePart) => {
        const audit = runtimePartAudit(runtimePart);
        return pdfRecord(
          runtimePart.title || runtimePart.name || runtimePart.code,
          pdfLabel(runtimePart.category || runtimePart.part_type || runtimePart.type),
          [
            ["Estado", completionValue(runtimePart)],
            ["Obligatoria", audit.required],
            ["Condición de aplicación", pdfStructuredText(audit.condition, "")],
            ["Configuración operativa", pdfStructuredText(audit.configuration, "")],
            ["Condición confirmada", audit.condition ? audit.conditionConfirmed : null],
            ["Descripción", runtimePart.description],
            ["Responsable", runtimePart.responsible_label],
            ["Fecha", pdfDate(runtimePart.completed_at || runtimePart.updated_at, true)],
            ["Advertencia de cálculo", deadlineFallbackText(runtimePart, "")],
            ["Evidencia", runtimePart.evidence_summary || runtimePart.evidence],
          ],
          ["completed", "completado", "cumplida"].includes(runtimePart.status) ? "protected" : "default",
          false,
        );
      }),
    ] : []),
    ...steps.flatMap((step, stepIndex) => {
      const parts = runtimeParts(step);
      if (!parts.length) return [];
      return [
        { text: `ETAPA ${stepIndex + 1} | ${pdfText(step.stage_name || step.name)}`, bold: true, fontSize: 7.5, color: CONVIVENCIA_PDF_COLORS.navy, margin: [0, 5, 0, 5] },
        ...parts.map((runtimePart) => {
          const part = runtimePart.part || runtimePart.protocol_part || runtimePart;
          const audit = runtimePartAudit(runtimePart, part);
          return pdfRecord(
            part.name || part.title || part.code,
            pdfLabel(part.category || part.part_type || part.type),
            [
              ["Estado", completionValue(runtimePart)],
              ["Obligatoria", audit.required],
              ["Condición de aplicación", pdfStructuredText(audit.condition, "")],
              ["Configuración operativa", pdfStructuredText(audit.configuration, "")],
              ["Condición confirmada", audit.condition ? audit.conditionConfirmed : null],
              ["Descripción", part.description],
              ["Responsable", runtimePart.responsible?.name || runtimePart.responsible_label],
              ["Fecha", pdfDate(runtimePart.completed_at || runtimePart.updated_at, true)],
              ["Advertencia de cálculo", deadlineFallbackText(runtimePart, "") || deadlineFallbackText(part, "")],
              ["Evidencia", runtimePart.evidence_summary || runtimePart.evidence],
            ],
            ["completed", "completado", "cumplida"].includes(runtimePart.status) ? "protected" : "default",
            false,
          );
        }),
      ];
    }),
    ...(!globalParts.length && !steps.some((step) => runtimeParts(step).length) ? [pdfEmpty("No existen partes de ejecución vinculadas.")] : []),
    pdfSection(4, "TRAZABILIDAD", `${timeline.length} evento(s) registrados durante la ejecución.`),
    ...(timeline.length
      ? timeline.map((event) => pdfRecord(
          event.title || event.action_type || event.event_type || "Actualización",
          pdfDate(event.event_at || event.created_at || event.changed_at || event.completed_at, true),
          [
            ["Etapa", event.stage_name || event.protocol_step?.stage_name],
            ["Responsable", event.created_by?.name || event.changed_by?.name || event.author?.name],
            ["Estado", event.status || event.new_status],
            ["Detalle", event.notes || event.comment || event.description],
          ],
          "default",
          false,
        ))
      : [pdfEmpty("No hay eventos adicionales en la trazabilidad visible.")]),
  ];

  return buildConvivenciaPdfDefinition({
    title: "Seguimiento de protocolo activado",
    kicker: "Convivencia Escolar | Ejecución",
    code: reference,
    status: activation.status,
    subtitle: protocol.name || activation.protocol_name || "Protocolo de actuación",
    sensitive: activation.is_sensitive !== false,
    generatedAt: generatedAt || payload?.generated_at || new Date().toISOString(),
    content,
    info: { subject: `Activación de protocolo ${reference}` },
  });
}

export async function downloadConvivenciaActivationPdf(payload) {
  const activation = resolveActivation(payload);
  const definition = buildConvivenciaActivationPdfDefinition(payload, payload?.generated_at);
  const reference = safePdfFilePart(activation.case?.folio || activation.complaint?.folio || activation.id || "activacion");
  (await getPdfMake()).createPdf(definition).download(`activacion-protocolo-${reference}.pdf`);
}

export { activationSteps, globalRuntimeParts, resolveActivation, runtimeParts };
