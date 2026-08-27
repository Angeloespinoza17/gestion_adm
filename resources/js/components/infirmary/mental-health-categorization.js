export const MENTAL_HEALTH_CATEGORY = "salud_mental";

export const MENTAL_HEALTH_EVENT_OPTIONS = Object.freeze([
  { value: "autolesion", label: "Autolesión" },
  { value: "contencion", label: "Contención" },
  { value: "ingesta_medicamentos", label: "Ingesta de medicamentos" },
]);

export const SELF_HARM_INJURY_OPTIONS = Object.freeze([
  { value: "corte", label: "Corte" },
  { value: "contusion", label: "Contusión" },
  { value: "herida_abrasiva", label: "Herida abrasiva" },
]);

export function normalizeMentalHealthCategorization({
  attentionCategory,
  mentalHealthEventType,
  selfHarmInjuryType,
}) {
  if (attentionCategory !== MENTAL_HEALTH_CATEGORY) {
    return { mentalHealthEventType: null, selfHarmInjuryType: null };
  }

  return {
    mentalHealthEventType: mentalHealthEventType || null,
    selfHarmInjuryType: mentalHealthEventType === "autolesion"
      ? selfHarmInjuryType || null
      : null,
  };
}

export function mentalHealthCategorizationError({
  attentionCategory,
  mentalHealthEventType,
  selfHarmInjuryType,
}) {
  if (attentionCategory !== MENTAL_HEALTH_CATEGORY) return null;
  if (!mentalHealthEventType) return "Selecciona el tipo de atención de salud mental.";
  if (mentalHealthEventType === "autolesion" && !selfHarmInjuryType) {
    return "Selecciona el tipo de lesión por autolesión.";
  }

  return null;
}
