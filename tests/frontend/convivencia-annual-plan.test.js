// @vitest-environment jsdom
import { readFileSync } from "node:fs";
import path from "node:path";
import axios from "axios";
import { afterEach, describe, expect, it, vi } from "vitest";
import convivenciaPlanApi from "../../resources/js/services/convivencia-plan-api";
import { buildConvivenciaPlanPdfDefinition } from "../../resources/js/components/convivencia/pdf/convivencia-plan-pdf";

afterEach(() => vi.restoreAllMocks());

describe("plan anual de gestión de la convivencia", () => {
  it("integra una sola vista anual con acciones, ejecución, calendario, documentos y versiones", () => {
    const workspace = readFileSync(path.resolve(process.cwd(), "resources/js/components/convivencia/plans/convivencia-plan-workspace.vue"), "utf8");
    const index = readFileSync(path.resolve(process.cwd(), "resources/js/views/convivencia/index.vue"), "utf8");

    expect(index).toContain("<ConvivenciaPlanWorkspace :catalogs=\"catalogs\"");
    expect(workspace).toContain("Planificación, ejecución, evidencias y versiones en un solo espacio");
    expect(workspace).toContain("<FullCalendar");
    expect(workspace).toContain("Matriz anual de acciones");
    expect(workspace).toContain("Protocolo de acogida institucional");
    expect(workspace).toContain("Indicadores de evaluación");
    expect(workspace).toContain("Historial de versiones");
    expect(workspace).toContain("Documentos fuente y respaldos");
    expect(workspace).toContain("modal === 'plan-document'");
    expect(workspace).toContain("plan_revision: this.plan.revision");
    expect(workspace).toContain("Crear año siguiente");
    expect(workspace).toContain("Ponderación del plan");
    expect(workspace).toContain("Rango de fechas (permite varios años)");
    expect(workspace).toContain("Tipo de actividad *");
    expect(workspace).toContain("activity_type_item_id");
    expect(workspace).toContain("Multianual");
  });

  it("usa endpoints específicos y envía revisiones en operaciones concurrentes", async () => {
    const post = vi.spyOn(axios, "post").mockResolvedValue({ data: {} });
    const put = vi.spyOn(axios, "put").mockResolvedValue({ data: {} });
    const remove = vi.spyOn(axios, "delete").mockResolvedValue({ data: {} });

    await convivenciaPlanApi.createAction(14, { title: "Acción", plan_revision: 7 });
    await convivenciaPlanApi.updateActivity(31, { title: "Actividad", revision: 4 });
    await convivenciaPlanApi.deleteAction(8, 7);
    await convivenciaPlanApi.deleteActivity(31, 4);
    await convivenciaPlanApi.restoreVersion(14, 2, 7);
    await convivenciaPlanApi.cloneToYear(14, { revision: 7, target_year: 2027 });

    expect(post).toHaveBeenCalledWith("/api/convivencia/plans/14/actions", expect.objectContaining({ plan_revision: 7 }));
    expect(put).toHaveBeenCalledWith("/api/convivencia/plan-activities/31", expect.objectContaining({ revision: 4 }));
    expect(remove).toHaveBeenCalledWith("/api/convivencia/plan-actions/8", { data: { plan_revision: 7 } });
    expect(remove).toHaveBeenCalledWith("/api/convivencia/plan-activities/31", { data: { revision: 4 } });
    expect(post).toHaveBeenCalledWith("/api/convivencia/plans/14/versions/2/restore", { revision: 7 });
    expect(post).toHaveBeenCalledWith("/api/convivencia/plans/14/clone-to-year", { revision: 7, target_year: 2027 });
  });

  it("genera un PDF integral con el documento 2026, acciones, actividades, evidencias e historial", () => {
    const definition = buildConvivenciaPlanPdfDefinition({
      calendar_year: 2026,
      version_number: 3,
      name: "PLAN_INTEGRAL_2026",
      general_objective: "OBJETIVO_GENERAL_INTEGRAL",
      specific_objectives: ["OBJETIVO_ESPECIFICO_INTEGRAL"],
      status: "vigente",
      source_document_name: "DOCUMENTO_FUENTE_2026.docx",
      attachments: [{ original_name: "DOCUMENTO_PRIVADO_2026.docx" }],
      institutional_protocol: [{ title: "ACOGIDA_INTEGRAL", description: "Bienvenida", responsible: "Equipo Directivo" }],
      evaluation_indicators: [{ code: "KPI-01", title: "INDICADOR_INTEGRAL", target_value: 70, target_unit: "%" }],
      regulatory_linkage_text: "CLAUSULA_RICE_INTEGRAL",
      regulatory_review_required: true,
      versions: [{ version_number: 3, change_summary: "CAMBIO_VERSIONADO_INTEGRAL", created_by: { name: "Responsable" } }],
      actions: [{
        title: "ACCION_ANUAL_INTEGRAL",
        weight_percent: 25,
        is_multi_year: true,
        planned_month: 3,
        status: "en_ejecucion",
        advance_percentage: 60,
        activities: [{
          title: "ACTIVIDAD_EJECUTADA_INTEGRAL",
          activity_type_label: "TALLER_INTEGRAL",
          status: "realizada",
          contribution_percent: 60,
          attachments: [{ original_name: "EVIDENCIA_INTEGRAL.pdf" }],
        }],
      }],
    });
    const serialized = JSON.stringify(definition);

    [
      "PLAN_INTEGRAL_2026",
      "OBJETIVO_GENERAL_INTEGRAL",
      "OBJETIVO_ESPECIFICO_INTEGRAL",
      "DOCUMENTO_FUENTE_2026.docx",
      "DOCUMENTO_PRIVADO_2026.docx",
      "ACOGIDA_INTEGRAL",
      "INDICADOR_INTEGRAL",
      "CLAUSULA_RICE_INTEGRAL",
      "ACCION_ANUAL_INTEGRAL",
      "ACTIVIDAD_EJECUTADA_INTEGRAL",
      "TALLER_INTEGRAL",
      "EVIDENCIA_INTEGRAL.pdf",
      "CAMBIO_VERSIONADO_INTEGRAL",
    ].forEach((marker) => expect(serialized).toContain(marker));
    expect(definition).toMatchObject({ pageSize: "A4" });
    expect(definition.info.subject).toContain("Plan de Gestión de la Convivencia Escolar");
  });
});
