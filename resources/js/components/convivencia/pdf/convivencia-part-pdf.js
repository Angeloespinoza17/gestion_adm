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
  safePdfFilePart,
} from "./convivencia-pdf-theme";

const resolvePart = (payload) => payload?.data || payload?.part || payload || {};

const deadline = (part) => part.deadline_value
  ? `${part.deadline_value} ${pdfLabel(part.deadline_unit).toLowerCase()} desde ${pdfLabel(part.deadline_anchor).toLowerCase()}`
  : "Sin plazo propio";

export function buildConvivenciaPartPdfDefinition(payload, generatedAt = null) {
  const part = resolvePart(payload);
  const links = pdfArray(part.links || part.protocol_links);
  const code = part.code || `PARTE-${part.id || "RICE"}`;
  const content = [
    pdfFacts([
      ["Categoría", pdfLabel(part.category)],
      ["Código", code],
      ["Estado", part.active === false ? "Archivada" : "Disponible"],
      ["Contenido sensible", part.is_sensitive],
      ["Exige evidencia", part.requires_evidence],
      ["Última actualización", pdfDate(part.updated_at, true)],
    ]),
    pdfSection(1, "DEFINICIÓN OPERATIVA", "Componente reutilizable de los protocolos institucionales."),
    pdfFacts([
      ["Descripción", part.description],
      ["Instrucciones", part.instructions],
      ["Responsable", part.responsible_label],
      ["Población aplicable", part.population_scope],
      ["Plazo", deadline(part)],
      ["Advertencia de cálculo", deadlineFallbackText(part, "")],
      ["Referencia reglamentaria o legal", part.legal_reference],
    ]),
    pdfSection(2, "PROTOCOLOS RELACIONADOS", `${links.length} vinculación(es) visibles.`),
    ...(links.length
      ? links.map((link) => pdfRecord(
          link.protocol?.name || link.protocol_name || "Protocolo",
          link.protocol?.code || "",
          [
            ["Etapa", link.step?.stage_name || link.step_name],
            ["Orden", link.sort_order],
            ["Obligatoria en esta relación", link.is_required ?? link.pivot?.is_required],
            ["Condición de aplicación", pdfStructuredText(link.condition ?? link.pivot?.condition, "")],
            ["Configuración operativa", pdfStructuredText(link.configuration ?? link.pivot?.configuration, "")],
          ],
          "default",
          false,
        ))
      : [pdfEmpty("Esta parte no está vinculada a protocolos visibles.")]),
    pdfSection(3, "CONTROL"),
    pdfFacts([
      ["Creada por", part.created_by?.name || part.createdBy?.name],
      ["Actualizada por", part.updated_by?.name || part.updatedBy?.name],
      ["Usos históricos", part.activation_parts_count ?? part.activationParts_count],
      ["Metadatos", part.metadata],
    ]),
  ];

  return buildConvivenciaPdfDefinition({
    title: "Ficha de parte de protocolo",
    kicker: "Convivencia Escolar | Biblioteca",
    code,
    status: part.active === false ? "archived" : "active",
    subtitle: part.title || "Componente reutilizable",
    sensitive: Boolean(part.is_sensitive),
    generatedAt: generatedAt || payload?.generated_at || new Date().toISOString(),
    content,
    info: { subject: `Parte de protocolo ${code}` },
  });
}

export async function downloadConvivenciaPartPdf(payload) {
  const part = resolvePart(payload);
  const definition = buildConvivenciaPartPdfDefinition(payload, payload?.generated_at);
  const code = safePdfFilePart(part.code || part.id || "parte");
  (await getPdfMake()).createPdf(definition).download(`parte-protocolo-${code}.pdf`);
}

export { resolvePart };
