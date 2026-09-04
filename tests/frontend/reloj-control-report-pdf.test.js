import { describe, expect, it } from "vitest";
import { buildRelojControlPdfDefinition } from "../../resources/js/utils/reloj-control-report-pdf";

const report = {
  period: { date_from: "2026-08-24", date_to: "2026-08-30" },
  queried_at: "2026-08-30T22:30:00-04:00",
  summary: {
    returned_users: 1,
    days: 2,
    punches: 4,
    absences: 0,
    worked_time_label: "16 h",
    entry_early_minutes: 7,
    entry_late_minutes: 4,
    exit_early_minutes: 10,
    exit_after_minutes: 5,
  },
  weekly: [{
    key: "101-2026-08-24",
    week_start: "2026-08-24",
    week_end: "2026-08-30",
    user: { id: 101, name: "Ana Institucional" },
    days: 2,
    worked_days: 2,
    absences: 0,
    days_with_entry: 2,
    days_with_exit: 2,
    worked_time_label: "16 h",
    entry_early_minutes: 7,
    entry_late_minutes: 4,
    exit_early_minutes: 10,
    exit_after_minutes: 5,
  }],
  data: [{
    key: "101-20260828",
    date: "20260828000000",
    user: { id: 101, name: "Ana Institucional" },
    worked_hours: "08:15",
    worked: true,
    absent: false,
    holiday: false,
    time_offs: [],
    schedule: { start_at: "2026-08-28T08:00:00-04:00", end_at: "2026-08-28T16:15:00-04:00" },
    attendance_variance: {
      entry_at: "2026-08-28T07:53:00-04:00",
      exit_at: "2026-08-28T16:20:00-04:00",
      entry_delta_minutes: -7,
      exit_delta_minutes: 5,
      entry_label: "7 min antes",
      exit_label: "5 min después",
    },
  }],
};

describe("Reloj Control PDF", () => {
  it("builds a landscape report with weekly and daily calculated values", () => {
    const definition = buildRelojControlPdfDefinition(report);
    const serialized = JSON.stringify(definition);

    expect(definition.pageOrientation).toBe("landscape");
    expect(definition.info.title).toContain("Reloj Control");
    expect(serialized).toContain("CONSOLIDADO SEMANAL");
    expect(serialized).toContain("DETALLE DIARIO");
    expect(serialized).toContain("Ana Institucional");
    expect(serialized).toContain("7 min antes");
    expect(serialized).toContain("5 min después");
    expect(JSON.stringify(definition.footer(1, 1))).toContain("Cálculos derivados después de consultar la API");
  });
});
