export const QUICK_ATTENTION_REASONS = [
  { value: "dolor_cabeza", label: "Dolor de cabeza", icon: "bx-headphone" },
  { value: "dolor_estomago", label: "Dolor de estómago", icon: "bx-body" },
  { value: "herido_dolor_anterior", label: "Herida o raspón", icon: "bx-band-aid" },
  { value: "epistaxis", label: "Sangrado nasal", icon: "bx-droplet" },
  { value: "otro", label: "Mareo o malestar", icon: "bx-pulse" },
  { value: "emocional", label: "Malestar emocional", icon: "bx-heart" },
  { value: "control_signos_vitales", label: "Control simple", icon: "bx-stats" },
];

export const QUICK_ATTENTION_ACTIONS = [
  { value: "hidratacion_oral", label: "Dar agua", description: "Hidratación oral", icon: "bx-water" },
  { value: "reposo", label: "Reposo breve", description: "Pausa y recuperación", icon: "bx-bed" },
  { value: "lavado_heridas", label: "Limpiar herida", description: "Aseo superficial", icon: "bx-bath" },
  { value: "curaciones", label: "Curación simple", description: "Protección de lesión menor", icon: "bx-plus-medical" },
  { value: "aplicacion_hielo", label: "Aplicar hielo", description: "Frío local protegido", icon: "bx-cube" },
  { value: "compresa_caliente", label: "Aplicar calor", description: "Compresa de calor", icon: "bx-sun" },
  { value: "vendaje", label: "Vendaje simple", description: "Soporte o protección", icon: "bx-layer" },
  { value: "toma_temperatura", label: "Tomar temperatura", description: "Control básico", icon: "bx-test-tube" },
  { value: "observacion_breve", label: "Observar evolución", description: "Seguimiento corto", icon: "bx-show" },
  { value: "contencion_emocional", label: "Contención breve", description: "Escucha y regulación", icon: "bx-conversation" },
];

export const QUICK_ATTENTION_RESULTS = [
  { value: "vuelve_sala", label: "Vuelve a sala", description: "Atención finalizada", status: "finalizada", icon: "bx-exit" },
  { value: "observacion", label: "En observación", description: "Permanece en Enfermería", status: "en_atencion", icon: "bx-time-five" },
  { value: "retiro_enfermeria", label: "Se retira", description: "Sale de Enfermería", status: "finalizada", icon: "bx-walk" },
];

const optionByValue = (options, value) => options.find((option) => option.value === value);

export function buildQuickAttentionPayload({
  studentId,
  reason,
  actions,
  result,
  duration = 5,
  notes = "",
  attendedAt,
}) {
  const reasonOption = optionByValue(QUICK_ATTENTION_REASONS, reason);
  const resultOption = optionByValue(QUICK_ATTENTION_RESULTS, result);
  const actionOptions = QUICK_ATTENTION_ACTIONS.filter((option) => actions.includes(option.value));
  const hasEmotionalSupport = reason === "emocional" || actions.includes("contencion_emocional");
  const cleanNotes = notes.trim();
  const actionSummary = actionOptions.map((option) => option.label).join(", ");
  const clinicalTreatmentTypes = actions.filter((action) => action !== "contencion_emocional");
  const treatmentCategories = [
    clinicalTreatmentTypes.length ? "fisico" : null,
    hasEmotionalSupport ? "emocional" : null,
  ].filter(Boolean);
  const resultLabel = resultOption?.label || "Resultado no indicado";

  return {
    student_profile_id: studentId,
    attention_category: reasonOption?.value || reason,
    accident_location_type: null,
    occurred_at: attendedAt,
    attended_at: attendedAt,
    referred_by_staff_id: null,
    dependency_id: null,
    accompanied_by_type: "sin_acompanante",
    accompanied_by_staff_id: null,
    accompanied_by_name: null,
    consultation_reason: reasonOption?.label || reason,
    accident_circumstance: null,
    logbook: [
      `Atención rápida: ${actionSummary}`,
      `Resultado: ${resultLabel}`,
      cleanNotes || null,
    ].filter(Boolean).join(" · "),
    initial_description: null,
    observations: cleanNotes || null,
    attention_duration_minutes: Number(duration),
    priority: "baja",
    status: resultOption?.status || "finalizada",
    treatments: [{
      treatment_categories: treatmentCategories,
      treatment_types: clinicalTreatmentTypes,
      derivation_type: null,
      derivation_support_teams: [],
      treatment_other: null,
      medication_id: null,
      medication_quantity: null,
      emotional_support_required: hasEmotionalSupport,
      emotional_comment: hasEmotionalSupport ? (cleanNotes || "Contención emocional breve") : null,
      emotional_support_type: null,
      emotional_duration_minutes: hasEmotionalSupport ? Number(duration) : null,
      emotional_professional_id: null,
      blood_pressure: null,
      pulse: null,
      respiratory_rate: null,
      temperature: null,
      oxygen_saturation: null,
      weight: null,
      height: null,
      vital_signs_notes: null,
      other_treatments: null,
      notes: `Atención rápida · ${resultLabel}${cleanNotes ? ` · ${cleanNotes}` : ""}`,
    }],
    referrals: [],
    calls: [],
    follow_ups: [],
  };
}
