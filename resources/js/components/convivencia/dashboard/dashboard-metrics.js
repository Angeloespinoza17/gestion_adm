export const CONVIVENCIA_DASHBOARD_METRIC_LABELS = Object.freeze({
  protocol_compliance_percentage: "Cumplimiento de protocolos",
  compliance_percentage: "Cumplimiento de protocolos",
  compliance_rate: "Cumplimiento de protocolos",
  open_cases: "Casos abiertos",
  closed_cases: "Casos cerrados",
  internal_derivations_pending: "Derivaciones internas pendientes",
  external_derivations_pending: "Derivaciones externas pendientes",
  pending_measures: "Medidas pendientes",
  interviews_done: "Entrevistas realizadas",
  complaints_received: "Denuncias recibidas",
  active_protocols: "Protocolos activos",
  daily_events: "Hechos de bitácora registrados",
  overdue_followups: "Seguimientos vencidos",
  overdue_protocol_steps: "Etapas de protocolo vencidas",
  overdue_steps: "Etapas vencidas",
  due_soon_protocol_steps: "Etapas de protocolo próximas a vencer",
  due_soon_steps: "Etapas próximas a vencer",
  overdue_protocol_parts: "Requisitos de protocolo vencidos",
  due_soon_protocol_parts: "Requisitos de protocolo próximos a vencer",
  on_time_protocol_steps: "Etapas de protocolo dentro de plazo",
  comparable_protocol_steps: "Etapas comparables para cumplimiento",
  average_protocol_closure_hours: "Promedio de cierre de protocolos (horas)",
  median_protocol_closure_hours: "Mediana de cierre de protocolos (horas)",
  protocol_closure_sample_size: "Cierres considerados en el cálculo",
});

export const convivenciaDashboardMetricLabel = (key, fallback = null) => (
  CONVIVENCIA_DASHBOARD_METRIC_LABELS[key] || fallback
);
