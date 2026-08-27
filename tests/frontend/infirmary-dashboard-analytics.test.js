import { describe, expect, it, vi } from "vitest";

const createPdf = vi.fn(() => ({ download: vi.fn() }));

vi.mock("../../resources/js/utils/pdfmake", () => ({
  getPdfMake: vi.fn(async () => ({ createPdf })),
}));
import {
  buildCareOutcomeItems,
  buildClinicalDetailItems,
} from "../../resources/js/components/infirmary/dashboard-statistics";
import { downloadPdfReport } from "../../resources/js/components/infirmary/module-utils";

describe("Infirmary dashboard analytics", () => {
  it("builds the new resolution and clinical-detail indicators from the API payload", () => {
    const metrics = {
          average_response_minutes: 7.5,
          completion_rate: 75,
          finalized_total: 3,
          active_total: 1,
          high_priority_rate: 25,
          high_priority_total: 1,
          recurrence_rate: 20,
          repeat_attentions_total: 1,
          treatment_coverage: 80,
          attentions_with_treatment: 4,
          vital_signs_coverage: 60,
          vital_signs_attentions: 3,
          call_effectiveness: 50,
          answered_calls_total: 1,
          calls_total: 2,
          follow_up_resolution_rate: 100,
          closed_follow_ups_total: 2,
          follow_ups_total: 2,
          average_attentions_per_student: 1.3,
          treatments_total: 5,
          emotional_support_attentions: 2,
          emotional_support_minutes: 25,
          fever_records_total: 1,
          low_oxygen_records_total: 0,
          average_call_minutes: 3,
          medications_not_administered_total: 1,
    };
    const formatNumber = (value, digits = 0) => Number(value || 0).toLocaleString("es-CL", { maximumFractionDigits: digits });

    const outcomes = buildCareOutcomeItems(metrics, formatNumber);
    const details = buildClinicalDetailItems(metrics, formatNumber);

    expect(outcomes).toHaveLength(8);
    expect(outcomes.find((item) => item.key === "average_response_minutes").value).toBe("7,5 min");
    expect(outcomes.find((item) => item.key === "completion_rate").detail).toContain("3 cerradas");
    expect(details).toHaveLength(8);
    expect(details.find((item) => item.label.includes("fiebre")).value).toBe("1");
  });

  it("creates an executive PDF definition with KPI cards, findings and repeatable table headers", async () => {
    await downloadPdfReport(
      "dashboard_enfermeria_prueba",
      "Dashboard de Enfermería",
      "Período de prueba",
      [{ title: "Resolución", headers: ["Indicador", "Resultado"], rows: [["Cierre", "75%"]] }],
      {
        summary: "Resumen ejecutivo de prueba.",
        kpis: [{ label: "Atenciones", value: "12" }, { label: "Cierre", value: "75%" }],
        findings: ["Horario de mayor carga: 11:00."],
      }
    );

    expect(createPdf).toHaveBeenCalledOnce();
    const definition = createPdf.mock.calls[0][0];
    expect(definition.styles.kpiValue).toBeTruthy();
    expect(definition.styles.findingItem).toBeTruthy();
    const sectionTable = definition.content
      .flatMap((item) => item.stack || [item])
      .find((item) => item.table?.keepWithHeaderRows === 1);
    expect(sectionTable).toBeTruthy();
    expect(JSON.stringify(definition.content)).toContain("Hallazgos del período");
    expect(JSON.stringify(definition.content)).toContain("Atenciones");
  });
});
