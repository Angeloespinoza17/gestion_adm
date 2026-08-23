import { describe, expect, it } from "vitest";
import {
  buildQuickAttentionPayload,
  QUICK_ATTENTION_ACTIONS,
} from "../../resources/js/components/infirmary/quick-attention";

describe("Atención rápida de Enfermería", () => {
  it("offers clickable minor-care measures without medication shortcuts", () => {
    const values = QUICK_ATTENTION_ACTIONS.map((action) => action.value);

    expect(values).toContain("hidratacion_oral");
    expect(values).toContain("curaciones");
    expect(values).toContain("lavado_heridas");
    expect(values).toContain("observacion_breve");
    expect(values).not.toContain("administracion_medicamento");
    expect(values).not.toContain("medicamento_sos");
  });

  it("builds a traceable finalized clinical record for a minor care", () => {
    const payload = buildQuickAttentionPayload({
      studentId: 42,
      reason: "dolor_cabeza",
      actions: ["hidratacion_oral", "reposo"],
      result: "vuelve_sala",
      duration: "5",
      notes: "Disminuye el malestar.",
      attendedAt: "2026-08-21T10:30",
    });

    expect(payload).toMatchObject({
      student_profile_id: 42,
      attention_category: "dolor_cabeza",
      consultation_reason: "Dolor de cabeza",
      attention_duration_minutes: 5,
      priority: "baja",
      status: "finalizada",
      referrals: [],
      calls: [],
    });
    expect(payload.logbook).toContain("Dar agua, Reposo breve");
    expect(payload.logbook).toContain("Vuelve a sala");
    expect(payload.treatments[0].treatment_types).toEqual(["hidratacion_oral", "reposo"]);
    expect(payload.treatments[0].medication_id).toBeNull();
  });

  it("keeps the attention open when the student remains under observation", () => {
    const payload = buildQuickAttentionPayload({
      studentId: 9,
      reason: "emocional",
      actions: ["contencion_emocional", "observacion_breve"],
      result: "observacion",
      duration: 10,
      attendedAt: "2026-08-21T11:00",
    });

    expect(payload.status).toBe("en_atencion");
    expect(payload.treatments[0].treatment_categories).toEqual(["fisico", "emocional"]);
    expect(payload.treatments[0].treatment_types).toEqual(["observacion_breve"]);
    expect(payload.treatments[0].emotional_support_required).toBe(true);
    expect(payload.treatments[0].emotional_duration_minutes).toBe(10);
  });
});
