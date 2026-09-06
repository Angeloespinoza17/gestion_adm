import { getPdfMake } from "../../../utils/pdfmake";
import {
  buildConvivenciaPdfDefinition,
  pdfDate,
  pdfFacts,
  pdfLabel,
  pdfRecord,
  pdfSection,
  pdfText,
  safePdfFilePart,
} from "./convivencia-pdf-theme";

const xml = (value) => String(value ?? "")
  .replaceAll("&", "&amp;")
  .replaceAll("<", "&lt;")
  .replaceAll(">", "&gt;")
  .replaceAll('"', "&quot;")
  .replaceAll("'", "&apos;");

const shortName = (name, max = 18) => {
  const parts = String(name || "Estudiante").trim().split(/\s+/);
  const compact = parts.length > 2 ? `${parts[0]} ${parts.at(-1)}` : parts.join(" ");
  return compact.length > max ? `${compact.slice(0, max - 1)}…` : compact;
};

const nodeColor = (node) => ({
  alta_recepcion_positiva: "#4F63D9",
  vinculo_reciproco: "#269177",
  sin_elecciones_positivas: "#D59B35",
  participacion_media: "#68809D",
})[node.role] || "#68809D";

const positionedNodes = (sourceNodes) => {
  const nodes = [...sourceNodes].sort((a, b) => Number(b.positive_received) - Number(a.positive_received) || a.name.localeCompare(b.name, "es"));
  const central = nodes.filter((node) => node.role === "alta_recepcion_positiva").slice(0, 3);
  const outer = nodes.filter((node) => node.role === "sin_elecciones_positivas");
  const middle = nodes.filter((node) => !central.includes(node) && !outer.includes(node));
  const locate = (items, radius, offset = 0) => items.map((node, index) => {
    const angle = ((Math.PI * 2 * index) / Math.max(items.length, 1)) - (Math.PI / 2) + offset;
    return { ...node, x: 450 + Math.cos(angle) * radius, y: 260 + Math.sin(angle) * radius };
  });
  const centers = central.length === 1 ? [{ ...central[0], x: 450, y: 260 }] : locate(central, central.length === 2 ? 58 : 72, .2);
  return [...centers, ...locate(middle, 154, .08), ...locate(outer, 225, .16)];
};

export function buildSociogramSvg(analysis = {}) {
  const nodes = positionedNodes(analysis.graph?.nodes || []);
  const map = new Map(nodes.map((node) => [Number(node.id), node]));
  const edges = (analysis.graph?.edges || []).map((edge) => {
    const source = map.get(Number(edge.source));
    const target = map.get(Number(edge.target));
    if (!source || !target) return "";
    const color = edge.reciprocal ? "#6658C7" : (edge.type === "positiva" ? "#269177" : (edge.type === "negativa" ? "#D05D72" : "#8290A5"));
    const dash = edge.type === "negativa" ? ' stroke-dasharray="6 5"' : (edge.type === "neutra" ? ' stroke-dasharray="2 5"' : "");
    return `<line x1="${source.x.toFixed(1)}" y1="${source.y.toFixed(1)}" x2="${target.x.toFixed(1)}" y2="${target.y.toFixed(1)}" stroke="${color}" stroke-width="${edge.reciprocal ? 3 : 1.8}" opacity="0.62"${dash}/>`;
  }).join("");
  const nodeShapes = nodes.map((node) => `<g><circle cx="${node.x.toFixed(1)}" cy="${node.y.toFixed(1)}" r="21" fill="${nodeColor(node)}" stroke="#FFFFFF" stroke-width="3"/><text x="${node.x.toFixed(1)}" y="${(node.y + 4).toFixed(1)}" text-anchor="middle" font-family="sans-serif" font-size="10" font-weight="700" fill="#FFFFFF">${xml(node.initials)}</text><text x="${node.x.toFixed(1)}" y="${(node.y + 34).toFixed(1)}" text-anchor="middle" font-family="sans-serif" font-size="9" font-weight="600" fill="#34435D">${xml(shortName(node.name))}</text></g>`).join("");

  return `<svg xmlns="http://www.w3.org/2000/svg" width="900" height="520" viewBox="0 0 900 520"><rect width="900" height="520" rx="14" fill="#FAFBFF"/>${edges}${nodeShapes}</svg>`;
}

export function buildConvivenciaSociogramPdfDefinition(record, generatedAt = null) {
  const analysis = record?.analysis || record?.result_summary || {};
  const metrics = analysis.metrics || {};
  const course = record?.course_section?.display_name || record?.courseSection?.display_name || "Sin curso";
  const code = `SOC-${record?.id || "BORRADOR"}`;
  const content = [
    pdfFacts([
      ["Curso", course],
      ["Fecha de aplicación", pdfDate(record?.applied_on)],
      ["Estado", pdfLabel(record?.status)],
      ["Confidencialidad", pdfLabel(record?.confidentiality_level)],
      ["Estudiantes considerados", metrics.students_total],
      ["Participación", `${metrics.response_rate || 0}%`],
    ]),
    pdfSection(1, "RED SOCIOMÉTRICA", "Las flechas representan nominaciones dirigidas; los colores de nodo resumen patrones descriptivos."),
    { svg: buildSociogramSvg(analysis), width: 515, margin: [0, 2, 0, 5] },
    {
      columns: [
        { text: "● Alta recepción positiva", color: "#4F63D9", fontSize: 6.8 },
        { text: "● Vínculo recíproco", color: "#269177", fontSize: 6.8 },
        { text: "● Participación media", color: "#68809D", fontSize: 6.8 },
        { text: "● Sin nominaciones positivas", color: "#D59B35", fontSize: 6.8 },
      ],
      margin: [0, 2, 0, 4],
    },
    pdfSection(2, "INDICADORES DE RED", "Métricas descriptivas calculadas sobre los vínculos registrados."),
    pdfFacts([
      ["Respuestas registradas", metrics.answers_total],
      ["Vínculos positivos", metrics.positive_links],
      ["Vínculos que requieren revisión", metrics.negative_links],
      ["Pares positivos recíprocos", metrics.reciprocal_pairs],
      ["Tasa de reciprocidad", `${metrics.reciprocity_rate || 0}%`],
      ["Densidad positiva", `${metrics.positive_density || 0}%`],
      ["Cobertura positiva", `${metrics.positive_coverage || 0}%`],
      ["Sin nominaciones positivas recibidas", metrics.without_positive_nominations],
      ["Grupos positivos conectados", metrics.positive_groups],
    ]),
    pdfSection(3, "RECEPCIÓN INDIVIDUAL", "Ordenamiento descriptivo para orientar la observación profesional."),
    {
      table: {
        headerRows: 1,
        widths: ["*", 70, 82, 70],
        body: [
          ["Estudiante", "Positivas", "Revisión", "Recíprocos"],
          ...([...(analysis.graph?.nodes || [])].sort((a, b) => Number(b.positive_received) - Number(a.positive_received)).map((node) => [
            pdfText(node.name),
            String(node.positive_received || 0),
            String(node.negative_received || 0),
            String(node.mutual_positive_links || 0),
          ])),
        ],
      },
      layout: "lightHorizontalLines",
    },
    pdfSection(4, "PREGUNTAS Y COBERTURA", `${(analysis.question_breakdown || []).length} criterio(s) sociométrico(s) aplicado(s).`),
    ...((analysis.question_breakdown || []).map((question) => pdfRecord(
      `${question.question_order}. ${question.prompt}`,
      pdfLabel(question.selection_type),
      [
        ["Máximo de elecciones", question.max_choices],
        ["Estudiantes que respondieron", question.respondents_total],
        ["Elecciones registradas", question.answers_total],
        ["Estudiantes seleccionados", question.selected_students_total],
      ],
      question.selection_type === "negativa" ? "warning" : "active",
      false,
    ))),
    pdfSection(5, "INTERPRETACIÓN PROFESIONAL", "La lectura debe complementarse con observación, entrevistas y antecedentes del curso."),
    pdfRecord("Interpretación registrada", pdfDate(record?.updated_at, true), [["Contenido", record?.interpretation || "Sin interpretación profesional registrada."]], "protected", false),
    {
      margin: [0, 10, 0, 0],
      fillColor: "#FFF8E8",
      color: "#6C571C",
      fontSize: 7,
      text: analysis.methodology?.scope || "Este informe presenta indicadores descriptivos y no constituye un diagnóstico individual ni grupal.",
    },
  ];

  return buildConvivenciaPdfDefinition({
    title: "Informe de sociograma",
    kicker: "Convivencia Escolar | Análisis relacional",
    code,
    status: record?.status,
    subtitle: `${course} · ${record?.title || "Sociograma"}`,
    sensitive: record?.is_sensitive !== false,
    generatedAt: generatedAt || new Date().toISOString(),
    content,
    info: { subject: `Sociograma ${course}`, keywords: "convivencia escolar, sociograma, red, nominaciones, reciprocidad" },
  });
}

export async function downloadConvivenciaSociogramPdf(record) {
  const definition = buildConvivenciaSociogramPdfDefinition(record);
  const course = record?.course_section?.display_name || record?.courseSection?.display_name || record?.id || "curso";
  const appliedOn = safePdfFilePart(pdfDate(record?.applied_on), "fecha");
  (await getPdfMake()).createPdf(definition).download(`sociograma-${safePdfFilePart(course)}-${appliedOn}.pdf`);
}
