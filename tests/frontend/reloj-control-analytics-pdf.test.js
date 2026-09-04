import { describe, expect, it } from "vitest";
import { buildRelojControlAnalyticsPdfDefinition } from "../../resources/js/utils/reloj-control-analytics-pdf";

const person = { id: 1, name: "Funcionaria de Prueba", position: "Administración" };
const result = {
  period: { date_from: "2026-08-24", date_to: "2026-08-30" },
  filters: { scope: "all_linked", tolerance_minutes: 5 },
  coverage: { scheduled_days: 5 },
  summary: {
    requested_users: 2,
    returned_users: 2,
    tardy_people: 1,
    tardiness_minutes: 8,
    absent_people: 1,
    absences: 1,
    worked_time_label: "24 h",
    entry_early_minutes: 7,
    exit_early_minutes: 10,
    exit_after_minutes: 12,
    missing_entry: 0,
    missing_exit: 1,
  },
  reports: {
    by_group: [{ name: "Administración", people: 2, worked_time_label: "24 h", tardiness_occurrences: 1, tardiness_minutes: 8, absences: 1 }],
    tardiness: [{ user: person, occurrences: 1, minutes: 8, average_minutes: 8, maximum_minutes: 8, details: [{ date: "20260828000000", minutes: 8 }] }],
    balances: [{ user: person, worked_time_label: "16 h", entry_early_minutes: 7, entry_late_minutes: 8, exit_early_minutes: 10, exit_after_minutes: 12, net_minutes: 1, net_label: "+1 min" }],
    absences: [{ user: person, occurrences: 1, justified: 0, without_justification: 1, non_worked_minutes: 480, details: [{ date: "20260827000000", justified: false, justifications: [] }] }],
  },
};

describe("Reloj Control analytics PDF", () => {
  it("includes the general, tardiness, balance and absence reports", () => {
    const definition = buildRelojControlAnalyticsPdfDefinition(result);
    const serialized = JSON.stringify(definition);

    expect(definition.pageOrientation).toBe("landscape");
    expect(serialized).toContain("RESULTADO GENERAL DEL PERÍODO");
    expect(serialized).toContain("REPORTE DE ATRASOS");
    expect(serialized).toContain("BALANCE DE MINUTOS");
    expect(serialized).toContain("REPORTE DE AUSENCIAS");
    expect(serialized).toContain("Sin justificación informada");
    expect(serialized).toContain("Funcionaria de Prueba");
    expect(JSON.stringify(definition.footer(1, 4))).toContain("Tolerancia aplicada: 5 min");
  });
});
