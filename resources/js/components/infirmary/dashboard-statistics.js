export function buildCareOutcomeItems(metrics = {}, formatNumber = defaultFormatNumber) {
  return [
    { key: "average_response_minutes", label: "Respuesta promedio", value: `${formatNumber(metrics.average_response_minutes, 1)} min`, detail: "Desde el evento hasta el ingreso", icon: "bx-timer", tone: "blue" },
    { key: "completion_rate", label: "Atenciones finalizadas", value: `${formatNumber(metrics.completion_rate, 1)}%`, detail: `${formatNumber(metrics.finalized_total)} cerradas · ${formatNumber(metrics.active_total)} activas`, icon: "bx-check-circle", tone: "green" },
    { key: "high_priority_rate", label: "Alta prioridad", value: `${formatNumber(metrics.high_priority_rate, 1)}%`, detail: `${formatNumber(metrics.high_priority_total)} altas o emergencias`, icon: "bx-pulse", tone: "coral" },
    { key: "recurrence_rate", label: "Atenciones repetidas", value: `${formatNumber(metrics.recurrence_rate, 1)}%`, detail: `${formatNumber(metrics.repeat_attentions_total)} atenciones adicionales`, icon: "bx-revision", tone: "violet" },
    { key: "treatment_coverage", label: "Con tratamiento", value: `${formatNumber(metrics.treatment_coverage, 1)}%`, detail: `${formatNumber(metrics.attentions_with_treatment)} atenciones documentadas`, icon: "bx-band-aid", tone: "teal" },
    { key: "vital_signs_coverage", label: "Con signos vitales", value: `${formatNumber(metrics.vital_signs_coverage, 1)}%`, detail: `${formatNumber(metrics.vital_signs_attentions)} controles registrados`, icon: "bx-pulse", tone: "cyan" },
    { key: "call_effectiveness", label: "Contacto efectivo", value: `${formatNumber(metrics.call_effectiveness, 1)}%`, detail: `${formatNumber(metrics.answered_calls_total)} de ${formatNumber(metrics.calls_total)} llamados`, icon: "bx-phone-call", tone: "amber" },
    { key: "follow_up_resolution_rate", label: "Seguimientos cerrados", value: `${formatNumber(metrics.follow_up_resolution_rate, 1)}%`, detail: `${formatNumber(metrics.closed_follow_ups_total)} de ${formatNumber(metrics.follow_ups_total)} seguimientos`, icon: "bx-task", tone: "slate" },
  ];
}

export function buildClinicalDetailItems(metrics = {}, formatNumber = defaultFormatNumber) {
  return [
    { label: "Atenciones por estudiante", value: formatNumber(metrics.average_attentions_per_student, 1), icon: "bx-user-check" },
    { label: "Registros de tratamiento", value: formatNumber(metrics.treatments_total), icon: "bx-first-aid" },
    { label: "Apoyos emocionales", value: formatNumber(metrics.emotional_support_attentions), icon: "bx-heart" },
    { label: "Minutos de apoyo emocional", value: formatNumber(metrics.emotional_support_minutes), icon: "bx-time-five" },
    { label: "Registros con fiebre ≥ 38 °C", value: formatNumber(metrics.fever_records_total), icon: "bx-pulse" },
    { label: "Saturación ≤ 94%", value: formatNumber(metrics.low_oxygen_records_total), icon: "bx-wind" },
    { label: "Duración media de llamados", value: `${formatNumber(metrics.average_call_minutes, 1)} min`, icon: "bx-phone" },
    { label: "Dosis no administradas", value: formatNumber(metrics.medications_not_administered_total), icon: "bx-error-circle" },
  ];
}

function defaultFormatNumber(value, maximumFractionDigits = 0) {
  return Number(value || 0).toLocaleString("es-CL", { maximumFractionDigits });
}
