import { readFileSync } from "node:fs";
import path from "node:path";
import { describe, expect, it } from "vitest";
import { normalizeConvivenciaRecord } from "../../resources/js/components/convivencia/forms/record-normalizers";

const source = (file) => readFileSync(path.resolve(process.cwd(), file), "utf8");

describe("edición trazable de actas", () => {
  it("hidrata la versión y exige motivo al editar un acta de convivencia", () => {
    const form = normalizeConvivenciaRecord("entrevistas", {
      id: 17,
      updated_at: "2026-09-06T22:30:00.000000Z",
      motive: "Seguimiento",
      participants: [{ full_name: "Participante", participant_type: "estudiante" }],
    });

    expect(form).toMatchObject({
      id: 17,
      record_updated_at: "2026-09-06T22:30:00.000000Z",
      change_reason: "",
      motive: "Seguimiento",
    });
    expect(form.participants).toEqual([expect.objectContaining({ full_name: "Participante" })]);
    expect(source("resources/js/components/convivencia/forms/convivencia-record-form.vue"))
      .toContain('v-model="form.change_reason"');
  });

  it("envía las correcciones de psicología y trabajo social por PATCH", () => {
    const psychology = source("resources/js/components/psychology/PsychologyCases.vue");
    const socialWork = source("resources/js/components/social-work/case-workspace.vue");

    expect(psychology).toContain("api.patch(`/api/psychology/activities/${activityId}`");
    expect(psychology).toContain("openActivityEdit(item)");
    expect(socialWork).toContain("api.patch(`/interventions/${interventionId}`");
    expect(socialWork).toContain("openInterventionEdit(item)");
    expect(socialWork).toContain("La versión anterior y la nueva quedarán cifradas");
  });

  it("expone las actas vinculadas desde el detalle del caso de convivencia", () => {
    const convivencia = source("resources/js/views/convivencia/index.vue");

    expect(convivencia).toContain("Actas de entrevista");
    expect(convivencia).toContain("editCaseInterview(interview)");
    expect(convivencia).toContain("/convivencia/entrevistas");
  });
});
