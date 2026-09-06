import { getPdfMake } from "../../../utils/pdfmake";
import {
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
  protocolDeadlineText,
  safePdfFilePart,
} from "./convivencia-pdf-theme";

const resolveProtocol = (payload) => payload?.data || payload?.protocol || payload || {};

const resolvePart = (link) => link?.part || link?.protocol_part || link?.protocolPart || link;

const partCategory = (part) => part?.category || part?.part_type || part?.type || "other";

const linkedPart = (link) => ({
  ...resolvePart(link),
  link_required: link?.is_required ?? link?.required ?? link?.pivot?.is_required,
  link_condition: link?.condition ?? link?.pivot?.condition,
  link_configuration: link?.configuration ?? link?.pivot?.configuration,
  link_step_id: link?.protocol_step_id ?? link?.step_id ?? link?.pivot?.protocol_step_id ?? link?.step?.id,
  link_step_code: link?.step_code || link?.step?.code,
  link_step_name: link?.step?.stage_name || link?.step_name,
});

const stepParts = (step) => {
  const links = step?.part_links || step?.partLinks || step?.parts || [];
  return pdfArray(links)
    .map((link) => ({
      ...linkedPart({ ...link, step: link?.step || step }),
      relation_type: link?.relation_type || link?.relation || link?.pivot?.relation_type,
    }))
    .filter((part) => part && (part.id || part.name || part.title || part.code));
};

const linkedPartSummary = (step) => {
  return stepParts(step)
    .map((part) => [
      `${pdfLabel(partCategory(part))}: ${part.name || part.title || part.code}`,
      part.link_required === true ? "Obligatoria" : part.link_required === false ? "Opcional" : null,
      part.link_condition ? `Condición: ${pdfStructuredText(part.link_condition)}` : null,
      part.link_configuration ? `Configuración: ${pdfStructuredText(part.link_configuration)}` : null,
    ].filter(Boolean).join(" | "))
    .join("\n");
};

const protocolParts = (protocol, steps) => {
  const resolveLinkedStep = (link) => {
    const stepId = link?.protocol_step_id ?? link?.step_id ?? link?.pivot?.protocol_step_id ?? link?.step?.id;
    const stepCode = link?.step_code || link?.step?.code;
    return link?.step
      || steps.find((step) => stepId !== null && stepId !== undefined && Number(step.id) === Number(stepId))
      || steps.find((step) => stepCode && String(step.code) === String(stepCode))
      || null;
  };
  const definitions = [
    ...pdfArray(protocol.parts || protocol.protocol_parts).map(linkedPart),
    ...pdfArray(protocol.part_links || protocol.partLinks).map((link) => linkedPart({
      ...link,
      step: resolveLinkedStep(link) || link?.step,
    })),
  ];
  const linked = steps.flatMap(stepParts);
  const unique = new Map();

  [...definitions, ...linked].forEach((part) => {
    if (!part) return;
    const key = part.id || `${partCategory(part)}:${part.code || part.name || part.title}`;
    if (!unique.has(key)) unique.set(key, { ...part, relation_audit: [] });
    const unresolvedStepLink = part.link_step_id !== null
      && part.link_step_id !== undefined
      && !part.link_step_name
      && !part.link_step_code;
    if (unresolvedStepLink) return;
    const relation = {
      step: part.link_step_name || part.link_step_code || "General",
      required: part.link_required,
      condition: part.link_condition,
      configuration: part.link_configuration,
    };
    if (relation.required !== undefined || relation.condition || relation.configuration || part.link_step_code) {
      const target = unique.get(key).relation_audit;
      const signature = JSON.stringify(relation);
      if (!target.some((item) => JSON.stringify(item) === signature)) target.push(relation);
    }
  });

  return [...unique.values()];
};

const extensionText = (step) => {
  const allowed = step.can_extend ?? step.allows_extension ?? step.extension_allowed ?? step.deadline?.allows_extension;
  if (!allowed) return "No contempla prórroga";
  const value = step.extension_value ?? step.max_extension_value ?? step.deadline?.extension_value;
  const unit = step.extension_unit || step.max_extension_unit || step.deadline?.extension_unit;
  return value ? `Hasta ${value} ${pdfLabel(unit).toLowerCase()}` : "Prórroga autorizable con fundamento";
};

export function buildConvivenciaProtocolPdfDefinition(payload, generatedAt = null) {
  const protocol = resolveProtocol(payload);
  const steps = pdfArray(protocol.steps).slice().sort((a, b) =>
    Number(a.step_order ?? a.order ?? 0) - Number(b.step_order ?? b.order ?? 0));
  const parts = protocolParts(protocol, steps);
  const byCategory = parts.reduce((result, part) => {
    const category = partCategory(part);
    result[category] ||= [];
    result[category].push(part);
    return result;
  }, {});
  const code = protocol.code || protocol.protocol_code || `PROTOCOLO-${protocol.id || "RICE"}`;
  const version = protocol.version_label || protocol.version || "Vigente";
  const effectivePeriod = [
    protocol.effective_from ? `Desde ${pdfDate(protocol.effective_from)}` : null,
    protocol.effective_to ? `hasta ${pdfDate(protocol.effective_to)}` : null,
  ].filter(Boolean).join(" ") || "Sin período informado";
  const warnings = pdfArray(protocol.metadata?.warnings).filter(Boolean);
  const reviewRequired = Boolean(protocol.metadata?.review_required || warnings.length);

  const content = [
    pdfFacts([
      ["Código / versión", `${code} | ${version}`],
      ["Tipo", protocol.type_label || protocol.type?.name || protocol.protocol_type?.name],
      ["Criticidad", protocol.criticality_label || protocol.criticality?.name],
      ["Ámbito educativo", protocol.education_scope],
      ["Vigencia", effectivePeriod],
      ["Documento sensible", protocol.is_sensitive],
    ]),
    ...(reviewRequired ? [
      pdfRecord(
        "Revisión normativa requerida",
        "Advertencia RICE",
        [[
          "Observaciones registradas",
          warnings.length
            ? warnings.map((warning, index) => `${index + 1}. ${pdfText(warning)}`).join("\n")
            : "Esta definición fue marcada para revisión humana en su configuración reglamentaria.",
        ]],
        "warning",
        false,
      ),
    ] : []),
    pdfSection(1, "PROPÓSITO Y ENCUADRE NORMATIVO", "Identificación de la fuente reglamentaria y alcance de la versión."),
    pdfFacts([
      ["Descripción", protocol.description],
      ["Fuente reglamentaria", protocol.regulatory_source],
      ["Referencia legal", protocol.legal_reference],
      ["Referencia de origen", protocol.source_reference],
      ["Acciones mínimas generales", protocol.minimal_actions],
      ["Medidas protectoras generales", protocol.safeguard_measures],
      ["Documentos generales", protocol.required_documents],
    ]),
    pdfSection(2, "RUTA DE ACTUACIÓN", `${steps.length} etapa(s) ordenadas para esta versión del protocolo.`),
    ...(steps.length
      ? steps.map((step, index) => pdfRecord(
          `${index + 1}. ${step.stage_name || step.name || "Etapa"}`,
          protocolDeadlineText(step),
          [
            ["Código / tipo", [step.code, pdfLabel(step.step_type || step.type)].filter(Boolean).join(" | ")],
            ["Responsable", step.responsible_label || step.responsible_role || step.responsible?.name],
            ["Objetivo", step.description || step.objective],
            ["Regla de término", pdfStructuredText(step.completion_rule || step.completion_rules || step.rules, "")],
            ["Prórroga", extensionText(step)],
            ["Advertencia de cálculo", deadlineFallbackText(step, "")],
            ["Partes vinculadas", linkedPartSummary(step)],
          ],
          "active",
          false,
        ))
      : [pdfEmpty("El protocolo no contiene etapas configuradas.")]),
    pdfSection(3, "DETALLE OPERATIVO POR ETAPA", "Acciones, resguardos, documentos y condiciones que guían la ejecución."),
    ...(steps.length
      ? steps.map((step, index) => pdfRecord(
          `Etapa ${index + 1} | ${step.stage_name || step.name || "Sin nombre"}`,
          step.code || "",
          [
            ["Descripción", step.description],
            ["Plazo", protocolDeadlineText(step)],
            ["Responsable", step.responsible_label || step.responsible_role],
            ["Acciones mínimas", step.minimal_actions || step.actions],
            ["Medidas protectoras", step.safeguard_measures || step.measures],
            ["Documentos requeridos", step.required_documents || step.documents],
            ["Reglas / condiciones", pdfStructuredText(step.completion_rule || step.completion_rules || step.rules, "")],
            ["Extensión", extensionText(step)],
            ["Advertencia de cálculo", deadlineFallbackText(step, "")],
            ["Componentes relacionados", linkedPartSummary(step)],
          ],
          "default",
          false,
        ))
      : [pdfEmpty("Sin detalle operativo disponible.")]),
    pdfSection(4, "BIBLIOTECA DE PARTES RELACIONADAS", `${parts.length} componente(s) reutilizable(s) vinculados a esta definición.`),
    ...Object.entries(byCategory).flatMap(([category, categoryParts]) => [
      { text: pdfLabel(category).toUpperCase(), bold: true, color: "#263A8F", fontSize: 7.5, margin: [0, 5, 0, 5] },
      ...categoryParts.map((part) => pdfRecord(
        part.name || part.title || part.code,
        part.code || "",
        [
          ["Descripción", part.description],
          ["Aplicación", part.population_scope],
          ["Instrucciones", part.instructions],
          ["Responsable", part.responsible_label],
          ["Referencia", part.legal_reference || part.source_reference],
          ["Advertencia de cálculo", deadlineFallbackText(part, "")],
          ["Obligatoriedad en vínculos", pdfArray(part.relation_audit).map((relation) => `${relation.step}: ${relation.required === true ? "Obligatoria" : relation.required === false ? "Opcional" : "No informada"}`).join("\n")],
          ["Condiciones por vínculo", pdfArray(part.relation_audit).filter((relation) => relation.condition).map((relation) => `${relation.step}: ${pdfStructuredText(relation.condition)}`).join("\n")],
          ["Configuración por vínculo", pdfArray(part.relation_audit).filter((relation) => relation.configuration).map((relation) => `${relation.step}: ${pdfStructuredText(relation.configuration)}`).join("\n")],
        ],
        ["safeguard_measure", "protective_measure"].includes(partCategory(part)) ? "protected" : "default",
        false,
      )),
    ]),
    ...(!parts.length ? [pdfEmpty("No existen partes reutilizables vinculadas a esta versión.")] : []),
    pdfSection(5, "CONTROL DE VERSIÓN"),
    pdfFacts([
      ["Versión", version],
      ["Estado", pdfLabel(protocol.status)],
      ["Vigencia", effectivePeriod],
      ["Última actualización", pdfDate(protocol.updated_at, true)],
      ["Responsable de actualización", protocol.updated_by?.name || protocol.updatedBy?.name],
    ]),
  ];

  return buildConvivenciaPdfDefinition({
    title: "Protocolo de actuación",
    kicker: "Convivencia Escolar | RICE",
    code,
    status: protocol.status,
    subtitle: protocol.name || "Definición institucional",
    sensitive: Boolean(protocol.is_sensitive),
    generatedAt: generatedAt || payload?.generated_at || new Date().toISOString(),
    content,
    info: { subject: `Protocolo de Convivencia ${code}` },
  });
}

export async function downloadConvivenciaProtocolPdf(payload) {
  const protocol = resolveProtocol(payload);
  const definition = buildConvivenciaProtocolPdfDefinition(payload, payload?.generated_at);
  const code = safePdfFilePart(protocol.code || protocol.protocol_code || protocol.id || "protocolo");
  const version = safePdfFilePart(protocol.version_label || protocol.version || "vigente");
  (await getPdfMake()).createPdf(definition).download(`protocolo-convivencia-${code}-${version}.pdf`);
}

export { protocolParts, resolveProtocol };
