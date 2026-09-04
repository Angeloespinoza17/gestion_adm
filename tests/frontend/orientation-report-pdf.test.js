import { describe, expect, it } from "vitest";
import {
  buildOrientationActionPdfDefinition,
  buildOrientationPlanPdfDefinition,
  buildOrientationStatisticsPdfDefinition,
} from "../../resources/js/utils/orientation-report-pdf";

const plan = {
  year: 2026,
  title: "Plan Anual de Orientación 2026",
  status: "active",
  general_objective: "Fortalecer la formación integral.",
  description: "Plan institucional.",
  stats: { actions: 1, completed_actions: 0, progress: 45, activities: 1, completed_activities: 1, evidences: 1 },
  related_plans: [{ name: "Plan de Afectividad, Sexualidad y Género", actions_count: 1 }],
  actions: [{
    id: 10,
    title: "Taller de autocuidado",
    objective: "Promover hábitos protectores.",
    target_levels: "7° básico",
    responsible_summary: "Orientación",
    planned_verification_means: "Lista de asistencia",
    material_resources: "Guía de trabajo",
    start_date: "2026-05-01",
    end_date: "2026-05-31",
    status: "in_progress",
    progress: 45,
    activities_count: 1,
    completed_activities_count: 1,
    evidences_count: 1,
    related_plans: [{ name: "Plan de Afectividad, Sexualidad y Género" }],
    responsible_users: [],
  }],
};

const action = {
  ...plan.actions[0],
  description: "Jornada formativa.",
  activity_contribution: { allocated: 40, earned: 40, remaining: 60 },
  activities: [{ title: "Taller curso", starts_at: "2026-05-12T10:00:00-04:00", status: "completed", contribution_percent: 40, completion_percent: 100, location: "Sala", results: "Realizado" }],
  evidences: [{ title: "Asistencia", evidence_type: "attendance", occurred_on: "2026-05-12", has_file: true, original_name: "asistencia.pdf" }],
};

const statistics = {
  summary: { actions: 1, completed_actions: 0, average_progress: 45, activities: 1, completed_activities: 1, evidences: 1, related_plans: 1, overdue_actions: 0, unscheduled_actions: 0, traceability: 100 },
  action_statuses: [{ status: "in_progress", label: "En ejecución", count: 1 }],
  traceability: [{ label: "Medios de verificación", covered: 1, total: 1, percent: 100 }],
  monthly: Array.from({ length: 12 }, (_, index) => ({ month: index + 1, label: `M${index + 1}`, actions: index === 4 ? 1 : 0, activities: index === 4 ? 1 : 0 })),
  action_performance: [{ ...action, activities_count: 1, completed_activities_count: 1, evidences_count: 1, related_plans_count: 1 }],
  evidence_types: [{ type: "attendance", label: "Asistencia", count: 1 }],
};

describe("Informes PDF de Orientación", () => {
  it("construye el informe general en A4 horizontal con la matriz completa", () => {
    const definition = buildOrientationPlanPdfDefinition(plan);
    const serialized = JSON.stringify(definition.content);
    expect(definition.pageOrientation).toBe("landscape");
    expect(serialized).toContain("Informe general del plan");
    expect(serialized).toContain("Matriz general de acciones");
    expect(serialized).toContain("Plan de Afectividad");
  });

  it("construye el informe individual con actividades y evidencias", () => {
    const definition = buildOrientationActionPdfDefinition(plan, action);
    const serialized = JSON.stringify(definition.content);
    expect(definition.pageOrientation).toBe("portrait");
    expect(serialized).toContain("Informe por acción");
    expect(serialized).toContain("Taller curso");
    expect(serialized).toContain("40% aporte");
    expect(serialized).toContain("asistencia.pdf");
  });

  it("construye el informe estadístico con gráficos SVG y desempeño por acción", () => {
    const definition = buildOrientationStatisticsPdfDefinition(plan, statistics);
    const serialized = JSON.stringify(definition.content);
    expect(definition.pageOrientation).toBe("landscape");
    expect(serialized).toContain("Informe estadístico");
    expect(serialized).toContain("Cobertura de trazabilidad");
    expect(serialized).toContain("<svg");
  });
});
