// @vitest-environment jsdom
import { mount } from "@vue/test-utils";
import { describe, expect, it } from "vitest";
import { readFileSync } from "node:fs";
import path from "node:path";
import ConvivenciaSociogramAnalysis from "../../resources/js/components/convivencia/sociograms/convivencia-sociogram-analysis.vue";
import { buildConvivenciaSociogramPdfDefinition, buildSociogramSvg } from "../../resources/js/components/convivencia/pdf/convivencia-sociogram-pdf";

const analysis = {
  schema_version: 2,
  methodology: { scope: "LECTURA_DESCRIPTIVA_NO_DIAGNOSTICA" },
  metrics: {
    students_total: 3,
    respondents_total: 3,
    response_rate: 100,
    answers_total: 4,
    positive_links: 3,
    negative_links: 1,
    reciprocal_pairs: 1,
    reciprocity_rate: 66.7,
    positive_density: 50,
    positive_coverage: 66.7,
    without_positive_nominations: 1,
    positive_groups: 1,
  },
  graph: {
    nodes: [
      { id: 1, name: "Antonia Pérez", initials: "AP", role: "alta_recepcion_positiva", positive_received: 2, negative_received: 0, positive_emitted: 1, mutual_positive_links: 1 },
      { id: 2, name: "Beatriz Soto", initials: "BS", role: "vinculo_reciproco", positive_received: 1, negative_received: 0, positive_emitted: 1, mutual_positive_links: 1 },
      { id: 3, name: "Camila Díaz", initials: "CD", role: "sin_elecciones_positivas", positive_received: 0, negative_received: 1, positive_emitted: 1, mutual_positive_links: 0 },
    ],
    edges: [
      { source: 1, target: 2, type: "positiva", weight: 1, reciprocal: true, question_orders: [1] },
      { source: 2, target: 1, type: "positiva", weight: 1, reciprocal: true, question_orders: [1] },
      { source: 3, target: 1, type: "positiva", weight: 1, reciprocal: false, question_orders: [1] },
      { source: 1, target: 3, type: "negativa", weight: 1, reciprocal: false, question_orders: [2] },
    ],
  },
  question_breakdown: [
    { question_id: 10, question_order: 1, prompt: "PREGUNTA_POSITIVA", selection_type: "positiva", max_choices: 2, respondents_total: 3, answers_total: 3, selected_students_total: 2 },
    { question_id: 11, question_order: 2, prompt: "PREGUNTA_REVISION", selection_type: "negativa", max_choices: 1, respondents_total: 1, answers_total: 1, selected_students_total: 1 },
  ],
};

const record = {
  id: 7,
  title: "SOCIOGRAMA_VISUAL_TEST",
  status: "interpretado",
  applied_on: "2026-09-05",
  confidentiality_level: "alta_confidencialidad",
  is_sensitive: true,
  interpretation: "INTERPRETACION_PROFESIONAL_TEST",
  course_section: { display_name: "1° A" },
  analysis,
};

describe("análisis gráfico de sociogramas", () => {
  it("presenta una red dirigida filtrable y detalle individual no diagnóstico", async () => {
    const wrapper = mount(ConvivenciaSociogramAnalysis, {
      props: { record, canExport: true },
      global: { stubs: { apexchart: { template: "<div class='apexchart-stub'></div>" } } },
    });

    expect(wrapper.text()).toContain("SOCIOGRAMA_VISUAL_TEST");
    expect(wrapper.text()).toContain("Lectura descriptiva, no diagnóstica");
    expect(wrapper.findAll(".network-node")).toHaveLength(3);
    expect(wrapper.findAll(".network-edges path")).toHaveLength(4);

    const negativeFilter = wrapper.findAll(".sociogram-analysis__filters button").find((button) => button.text().includes("Requieren revisión"));
    await negativeFilter.trigger("click");
    expect(wrapper.findAll(".network-edges path")).toHaveLength(1);

    await wrapper.find(".network-node").trigger("click");
    expect(wrapper.text()).toContain("ESTUDIANTE SELECCIONADA");
    expect(wrapper.text()).toContain("no etiquetan ni diagnostican");
  });

  it("incluye balance ApexCharts, matriz de calor y captura guiada por curso", async () => {
    const wrapper = mount(ConvivenciaSociogramAnalysis, {
      props: { record },
      global: { stubs: { apexchart: { template: "<div class='apexchart-stub'></div>" } } },
    });
    const balance = wrapper.findAll(".sociogram-analysis__views button").find((button) => button.text().includes("Balance"));
    await balance.trigger("click");
    expect(wrapper.findAll(".apexchart-stub")).toHaveLength(2);
    const matrix = wrapper.findAll(".sociogram-analysis__views button").find((button) => button.text().includes("Matriz"));
    await matrix.trigger("click");
    expect(wrapper.findAll(".apexchart-stub")).toHaveLength(1);

    const form = readFileSync(path.resolve(process.cwd(), "resources/js/components/convivencia/forms/convivencia-record-form.vue"), "utf8");
    expect(form).toContain("Captura sociométrica guiada");
    expect(form).toContain(":filters=\"sociogramStudentFilters\"");
    expect(form).toContain(":options=\"sociogramQuestionChoices\"");
    expect(form).toContain("syncSociogramAnswerType(answer)");
  });

  it("genera un PDF reservado con red, indicadores, preguntas e interpretación", () => {
    const definition = buildConvivenciaSociogramPdfDefinition(record, "2026-09-05T12:00:00Z");
    const serialized = JSON.stringify(definition);
    const svg = buildSociogramSvg(analysis);

    expect(definition.pageSize).toBe("A4");
    expect(definition.watermark?.text).toBe("CONFIDENCIAL");
    expect(svg).toContain("Antonia Pérez");
    [
      "SOCIOGRAMA_VISUAL_TEST",
      "INTERPRETACION_PROFESIONAL_TEST",
      "PREGUNTA_POSITIVA",
      "PREGUNTA_REVISION",
      "LECTURA_DESCRIPTIVA_NO_DIAGNOSTICA",
      "RED SOCIOMÉTRICA",
      "Tasa de reciprocidad",
    ].forEach((marker) => expect(serialized).toContain(marker));
  });
});
