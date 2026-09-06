// @vitest-environment jsdom
import { mount } from "@vue/test-utils";
import axios from "axios";
import { afterEach, describe, expect, it, vi } from "vitest";
import ConvivenciaIndex from "../../resources/js/views/convivencia/index.vue";
import ConvivenciaRowActions from "../../resources/js/components/convivencia/ui/convivencia-row-actions.vue";
import * as casePdf from "../../resources/js/components/convivencia/pdf/convivencia-case-pdf";

vi.mock("../../resources/js/layouts/main.vue", () => ({
  default: { name: "LayoutStub", template: "<main><slot /></main>" },
}));

afterEach(() => vi.restoreAllMocks());

describe("ficha PDF y acciones de casos de convivencia", () => {
  it("presenta una acción principal y acciones compactas accesibles en una sola barra", async () => {
    const wrapper = mount(ConvivenciaRowActions, {
      props: {
        itemLabel: "caso CONV-001",
        actions: [
          { key: "view", label: "Ver detalle", icon: "bx-show", prominent: true },
          { key: "pdf", label: "Descargar PDF", icon: "bxs-file-pdf", tone: "pdf", iconOnly: true },
          { key: "archive", label: "Archivar", icon: "bx-archive-in", tone: "danger", iconOnly: true },
        ],
      },
    });

    const buttons = wrapper.findAll("button");
    expect(buttons).toHaveLength(3);
    expect(buttons[0].classes()).toContain("is-prominent");
    expect(buttons[1].classes()).toContain("is-icon-only");
    expect(buttons[1].classes()).toContain("is-pdf");
    expect(buttons[1].attributes("aria-label")).toBe("Descargar PDF caso CONV-001");
    expect(buttons[2].classes()).toContain("is-danger");

    await buttons[1].trigger("click");
    expect(wrapper.emitted("select")?.at(-1)).toEqual(["pdf"]);
  });

  it("ofrece PDF solo con permiso y descarga desde el endpoint integral", async () => {
    const methods = ConvivenciaIndex.methods;
    const baseContext = {
      catalogs: { capabilities: { can_export_reports: true, can_edit_cases: false, can_close_cases: false } },
      canExportReports: true,
      casePdfLoadingId: null,
      canEditItem: () => false,
    };
    expect(methods.operationalActions.call(baseContext, "casos", { id: 7, status: "abierto" }).map((action) => action.key))
      .toEqual(["view", "pdf"]);
    expect(methods.operationalActions.call({ ...baseContext, canExportReports: false }, "casos", { id: 7, status: "abierto" }).map((action) => action.key))
      .toEqual(["view"]);

    const payload = { data: { id: 7, folio: "CONV-007" }, generated_at: "2026-09-05T12:00:00-04:00" };
    const get = vi.spyOn(axios, "get").mockResolvedValue({ data: payload });
    const download = vi.spyOn(casePdf, "downloadConvivenciaCasePdf").mockResolvedValue();

    await methods.exportCasePdf.call(baseContext, { id: 7 });

    expect(get).toHaveBeenCalledWith("/api/convivencia/cases/7/export-data");
    expect(download).toHaveBeenCalledWith(payload);
    expect(baseContext.casePdfLoadingId).toBeNull();
  });

  it("incluye la ficha, personas, seguimientos, entrevistas, derivaciones, medidas, protocolos y documentos", () => {
    const definition = casePdf.buildConvivenciaCasePdfDefinition({
      generated_at: "2026-09-05T12:00:00-04:00",
      data: {
        id: 4,
        folio: "CONV-CAS-2026-0004",
        status: "en_seguimiento",
        is_sensitive: true,
        initial_report: "RELATO_INTEGRAL",
        background: "ANTECEDENTE_INTEGRAL",
        immediate_measures: "ACCION_INMEDIATA_INTEGRAL",
        safeguarding_measures: "RESGUARDO_INTEGRAL",
        internal_notes: "NOTA_INTERNA_INTEGRAL",
        resolution: "RESOLUCION_INTEGRAL",
        conclusion: "CONCLUSION_INTEGRAL",
        student: { registered_name: "Estudiante de prueba", rut: "11.111.111-1" },
        course_section: { display_name: "8° A" },
        people: [{ full_name: "PROFESIONAL_APOYO", person_type: "funcionario", role_type: "profesional_apoyo" }],
        follow_ups: [{ title: "SEGUIMIENTO_INTEGRAL", notes: "Acuerdo de seguimiento" }],
        complaints: [{ folio: "DENUNCIA_INTEGRAL", report_text: "Relato de denuncia" }],
        daily_logs: [{ daily_log_type_label: "BITACORA_INTEGRAL", description: "Hecho de bitácora" }],
        interviews: [{ interview_type_label: "ENTREVISTA_INTEGRAL", motive: "Motivo", participants: [{ full_name: "Participante" }] }],
        derivations: [{ destination_label: "DERIVACION_INTEGRAL", motive: "Motivo derivación" }],
        measures: [{ measure_type_label: "MEDIDA_INTEGRAL", description: "Descripción medida", training_objective: "Objetivo" }],
        protocol_activations: [{
          status: "activo",
          protocol: { name: "PROTOCOLO_INTEGRAL", code: "RICE-P01" },
          runtime_steps: [{ step_order: 1, stage_name: "ETAPA_INTEGRAL", parts: [{ title: "PARTE_INTEGRAL", status: "completed" }] }],
        }],
        attachments: [{ original_name: "DOCUMENTO_INTEGRAL.pdf", category: "informe", file_size: 2048 }],
        status_logs: [{ previous_status: "abierto", new_status: "en_seguimiento", comment: "TRAZABILIDAD_INTEGRAL" }],
      },
    });
    const serialized = JSON.stringify(definition);

    [
      "RELATO_INTEGRAL",
      "PROFESIONAL_APOYO",
      "SEGUIMIENTO_INTEGRAL",
      "DENUNCIA_INTEGRAL",
      "BITACORA_INTEGRAL",
      "ENTREVISTA_INTEGRAL",
      "DERIVACION_INTEGRAL",
      "MEDIDA_INTEGRAL",
      "PROTOCOLO_INTEGRAL",
      "ETAPA_INTEGRAL",
      "PARTE_INTEGRAL",
      "DOCUMENTO_INTEGRAL.pdf",
      "TRAZABILIDAD_INTEGRAL",
      "RESOLUCION_INTEGRAL",
    ].forEach((marker) => expect(serialized).toContain(marker));
    expect(definition.watermark?.text).toBe("CONFIDENCIAL");
    expect(definition.info.subject).toContain("CONV-CAS-2026-0004");
  });
});

