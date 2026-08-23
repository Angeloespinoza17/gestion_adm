// @vitest-environment jsdom

import { describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";
import RiskLevelBadge from "../../resources/js/components/risk-prevention/iper/RiskLevelBadge.vue";
import VepRiskMatrix from "../../resources/js/components/risk-prevention/iper/VepRiskMatrix.vue";
import RiskMatrixGrid from "../../resources/js/components/risk-prevention/iper/RiskMatrixGrid.vue";
import RiskApprovalPanel from "../../resources/js/components/risk-prevention/iper/RiskApprovalPanel.vue";
import RiskValidationSummary from "../../resources/js/components/risk-prevention/iper/RiskValidationSummary.vue";
import { formatRiskError } from "../../resources/js/components/risk-prevention/module-utils";
import { toPersistableStructure } from "../../resources/js/services/risk-matrix-api";

describe("Matriz IPER frontend", () => {
  it("never exposes SQL or database connection details in user errors", () => {
    const error = { response: { data: { message: "SQLSTATE[22001] Connection: mysql, Database: gestion_adm, insert into prevent_risk_matrix_tasks" } } };

    expect(formatRiskError(error, "No se pudo confirmar la importación.")).toBe("No se pudo confirmar la importación.");
  });

  it("communicates every VEP level with text, score and a semantic class", () => {
    const important = mount(RiskLevelBadge, { props: { score: 8 } });
    const intolerable = mount(RiskLevelBadge, { props: { score: 16 } });

    expect(important.text()).toContain("Importante");
    expect(important.text()).toContain("8");
    expect(important.classes()).toContain("iper-level--important");
    expect(intolerable.text()).toContain("Intolerable");
    expect(intolerable.classes()).toContain("iper-level--intolerable");
  });

  it("renders the nine VEP combinations and emits the selected backend-compatible factors", async () => {
    const wrapper = mount(VepRiskMatrix);
    const cells = wrapper.findAll("button.vep-cell");

    expect(cells).toHaveLength(9);
    const severeImportant = wrapper.find('button[aria-label^="Probabilidad 2, consecuencia 4"]');
    expect(severeImportant.attributes("aria-label")).toContain("Importante");
    await severeImportant.trigger("click");
    expect(wrapper.emitted("select")[0][0]).toMatchObject({ probability: 2, consequence: 4, level_code: "important" });
  });

  it("filters a large risk table without losing its contextual process and task", async () => {
    const wrapper = mount(RiskMatrixGrid, {
      props: {
        readonly: true,
        modelValue: [{
          name: "Operación Norte",
          tasks: [{
            activity_name: "Preparación",
            task_name: "Traslado",
            risks: [
              { specific_risk_name: "Caída", possible_harm: "Esguince", evaluation_method: "vep", hazard_factors: [{ hazard_description: "Piso", risk_factor_description: "Humedad" }], assessments: [{ phase: "current", probability: 2, consequence: 4 }], controls: [] },
              { specific_risk_name: "Golpe", possible_harm: "Contusión", evaluation_method: "vep", hazard_factors: [{ hazard_description: "Objeto", risk_factor_description: "Carga" }], assessments: [{ phase: "current", probability: 1, consequence: 2 }], controls: [] },
            ],
          }],
        }],
      },
    });

    expect(wrapper.findAll("tbody tr")).toHaveLength(2);
    await wrapper.find('input[type="search"]').setValue("caída");
    expect(wrapper.findAll("tbody tr")).toHaveLength(1);
    expect(wrapper.text()).toContain("Operación Norte");
    expect(wrapper.text()).toContain("Caída");
    expect(wrapper.text()).not.toContain("Golpe");
  });

  it("only exposes review and approval actions supported by status and permission", async () => {
    const wrapper = mount(RiskApprovalPanel, {
      props: {
        version: { status: "in_review", reviewed_at: "2026-08-20T10:00:00Z" },
        permissions: ["risk-matrix.review", "risk-matrix.observe", "risk-matrix.approve"],
      },
    });

    expect(wrapper.text()).toContain("Validar técnicamente");
    expect(wrapper.text()).toContain("Formular observaciones");
    expect(wrapper.text()).toContain("Aprobar y bloquear");
    const textarea = wrapper.find("textarea");
    await textarea.setValue("Revisión técnica anonimizada");
    await wrapper.findAll("button").find((button) => button.text().includes("Validar técnicamente")).trigger("click");
    expect(wrapper.emitted("action")[0]).toEqual(["review", expect.objectContaining({ notes: "Revisión técnica anonimizada" })]);
  });

  it("shows import or workflow errors with severity, path and navigation event", async () => {
    const issue = { severity: "error", code: "important_without_action", message: "Un riesgo importante requiere medida.", path: "processes.0.tasks.0.risks.0.controls" };
    const wrapper = mount(RiskValidationSummary, { props: { issues: [issue] } });

    expect(wrapper.text()).toContain("Un riesgo importante requiere medida.");
    expect(wrapper.text()).toContain(issue.path);
    await wrapper.find("button.issue").trigger("click");
    expect(wrapper.emitted("navigate")[0][0]).toEqual(issue);
  });

  it("does not persist the empty risk placeholder used by the multi-step editor", () => {
    const structure = [{
      name: "Mantención",
      tasks: [{
        task_name: "Inspección",
        risks: [{
          specific_risk_name: "",
          possible_harm: "",
          hazard_factors: [{ hazard_description: "", risk_factor_description: "" }],
          assessments: [{ phase: "current", probability: 1, consequence: 1 }],
          controls: [],
        }],
      }],
    }];

    expect(toPersistableStructure(structure)[0].tasks[0].risks).toEqual([]);
    structure[0].tasks[0].risks[0].specific_risk_name = "Caída";
    expect(toPersistableStructure(structure)[0].tasks[0].risks).toHaveLength(1);
  });
});
