import { describe, expect, it } from "vitest";
import {
  buildMaintenanceCalendarDays,
  buildMaintenanceVisitsCalendarPdf,
  maintenanceCalendarPdfFilename,
} from "../../resources/js/utils/maintenance-visits-calendar-pdf";

const visits = [
  {
    id: 1,
    visit_date: "2026-09-07",
    visit_time: "09:00:00",
    responsible: "Ana Mantención",
    visit_type: "Inspección",
    status: "Programada",
    dependency: { code: "DEP-01", name: "Edificio principal" },
  },
  {
    id: 2,
    visit_date: "2026-09-07",
    visit_time: "11:30:00",
    responsible: "Ana Mantención",
    visit_type: "Mantención",
    status: "Finalizada",
    dependency: { code: "DEP-02", name: "Patio cubierto" },
  },
];

describe("Maintenance visits calendar PDF", () => {
  it("builds a Monday-first 42-day calendar and keeps visits ordered by time", () => {
    const days = buildMaintenanceCalendarDays("2026-09", [...visits].reverse());

    expect(days).toHaveLength(42);
    expect(days[0].iso).toBe("2026-08-31");
    expect(days.at(-1).iso).toBe("2026-10-11");
    expect(days.find((day) => day.iso === "2026-09-07").visits.map((visit) => visit.id)).toEqual([1, 2]);
  });

  it("creates a print-ready landscape document that identifies the selected person", () => {
    const definition = buildMaintenanceVisitsCalendarPdf({
      visits,
      calendarMonth: "2026-09",
      calendarTitle: "Septiembre de 2026",
      responsibleLabel: "Ana Mantención · Encargada",
      filterLabels: ["Responsable: Ana Mantención", "Estado: Programada"],
      generatedAt: new Date("2026-09-01T12:00:00-04:00"),
    });

    expect(definition.pageOrientation).toBe("landscape");
    expect(definition.info.subject).toContain("Ana Mantención");
    expect(JSON.stringify(definition.content)).toContain("Ana Mantención · Encargada");
    expect(JSON.stringify(definition.content)).toContain("DEP-01");
    expect(JSON.stringify(definition.content)).not.toContain("Sin responsable");

    const calendar = definition.content.find((item) => item.table?.headerRows === 1);
    expect(calendar.table.body).toHaveLength(7);
    expect(calendar.table.dontBreakRows).toBe(true);
    expect(calendar.table.keepWithHeaderRows).toBe(1);
  });

  it("uses a stable filename with the month and normalized person", () => {
    expect(maintenanceCalendarPdfFilename("2026-09", "Ángela Pérez")).toBe(
      "calendario-visitas-2026-09-angela-perez.pdf"
    );
  });
});
