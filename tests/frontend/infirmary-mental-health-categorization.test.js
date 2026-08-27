import { describe, expect, it } from "vitest";
import {
  mentalHealthCategorizationError,
  normalizeMentalHealthCategorization,
} from "../../resources/js/components/infirmary/mental-health-categorization";

describe("infirmary mental health categorization", () => {
  it("requires a mental health event and a self-harm injury when applicable", () => {
    expect(mentalHealthCategorizationError({ attentionCategory: "salud_mental" }))
      .toBe("Selecciona el tipo de atención de salud mental.");
    expect(mentalHealthCategorizationError({
      attentionCategory: "salud_mental",
      mentalHealthEventType: "autolesion",
    })).toBe("Selecciona el tipo de lesión por autolesión.");
  });

  it("keeps the self-harm injury only for self-harm events", () => {
    expect(normalizeMentalHealthCategorization({
      attentionCategory: "salud_mental",
      mentalHealthEventType: "autolesion",
      selfHarmInjuryType: "herida_abrasiva",
    })).toEqual({
      mentalHealthEventType: "autolesion",
      selfHarmInjuryType: "herida_abrasiva",
    });

    expect(normalizeMentalHealthCategorization({
      attentionCategory: "salud_mental",
      mentalHealthEventType: "contencion",
      selfHarmInjuryType: "corte",
    })).toEqual({ mentalHealthEventType: "contencion", selfHarmInjuryType: null });
  });

  it("clears the complete categorization outside mental health", () => {
    expect(normalizeMentalHealthCategorization({
      attentionCategory: "dolor_cabeza",
      mentalHealthEventType: "autolesion",
      selfHarmInjuryType: "corte",
    })).toEqual({ mentalHealthEventType: null, selfHarmInjuryType: null });
  });
});
