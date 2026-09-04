import { readFileSync } from "node:fs";
import { describe, expect, it } from "vitest";

const view = readFileSync("resources/js/views/orientation/index.vue", "utf8");
const api = readFileSync("resources/js/services/orientation-api.js", "utf8");
const router = readFileSync("resources/js/router/index.js", "utf8");
const sideNav = readFileSync("resources/js/components/side-nav.vue", "utf8");
const calendarizationView = readFileSync("resources/js/views/orientation/calendarization.vue", "utf8");
const calendarizationReference = readFileSync("app/Services/Orientation/OrientationCalendarizationReferenceService.php", "utf8");

describe("Módulo Plan Anual de Orientación", () => {
  it("expone la regla de un plan único y la matriz completa del documento", () => {
    expect(view).toContain("Un plan único por año calendario");
    expect(view).toContain("Niveles o cursos");
    expect(view).toContain("Medios de verificación planificados");
    expect(view).toContain("Recursos materiales");
    expect(view).toContain("Responsables del sistema");
  });

  it("conecta planes relacionados, actividades y evidencias privadas", () => {
    expect(view).toContain("Planes relacionados");
    expect(view).toContain("Plan de Afectividad, Sexualidad y Género");
    expect(view).toContain("Actividades de la acción");
    expect(view).toContain("Aporte al avance de la acción");
    expect(view).toContain("Cálculo automático");
    expect(view).toContain("activity_contribution");
    expect(view).toContain("Archivo privado (máx. 30 MB)");
    expect(api).toContain("createRelatedPlan");
    expect(api).toContain("createActivity");
    expect(api).toContain("createEvidence");
  });

  it("presenta cada acción como fila con operaciones directas", () => {
    expect(view).toContain('class="orientation-action-table"');
    expect(view).toContain("Planificación");
    expect(view).toContain("Seguimiento");
    expect(view).toContain('class="action-table__planning"');
    expect(view).toContain('class="action-table__tracking"');
    expect(view).toContain(".orientation-action-table { min-width: 0; }");
    expect(view).toContain(".orientation-action-table-wrap { overflow-x: hidden; }");
    expect(view).toContain('class="action-table__buttons" role="group"');
    expect(view).toContain("viewAction(action)");
    expect(view).toContain("editActionFromTable(action)");
    expect(view).toContain("deleteAction(action)");
    expect(view).toContain("addActivityToAction(action)");
    expect(view).toContain("cnsc-action-btn--view");
    expect(view).toContain("cnsc-action-btn--edit");
    expect(view).toContain("cnsc-action-btn--delete");
    expect(view).toContain("cnsc-action-btn--activate");
    expect(view).toContain("data-cnsc-action-ignore");
    expect(view).toContain("mdi-calendar-plus");
    expect(view).toContain("Agregar actividad");
    expect(api).toContain("deleteAction(id)");
  });

  it("ofrece calendario mensual, semanal, diario y agenda protegido por RBAC", () => {
    expect(view).toContain('right: "dayGridMonth,timeGridWeek,timeGridDay,listMonth"');
    expect(view).toContain('week: "Semana"');
    expect(view).toContain('list: "Agenda"');
    expect(router).toContain('path: "/orientation/calendario"');
    expect(router).toContain('permission: "orientation.view"');
  });

  it("incorpora una calendarización anual por capas para los cuatro tramos documentales", () => {
    expect(calendarizationView).toContain("Calendarización por niveles");
    expect(calendarizationView).toContain("Todos juntos");
    expect(calendarizationReference).toContain("1° a 6° básico");
    expect(calendarizationReference).toContain("7° básico a II° medio");
    expect(calendarizationReference).toContain("III° medio");
    expect(calendarizationReference).toContain("IV° medio · Plan vocacional");
    expect(calendarizationView).toContain("Guardar calendarización 2026");
    expect(calendarizationView).toContain("Calendario mensual");
    expect(calendarizationView).toContain("Cronograma semanal");
    expect(calendarizationView).toContain("FullCalendar");
    expect(calendarizationView).toContain("No se asignarán automáticamente a ninguna acción");
    expect(calendarizationView).toContain("no aumenta el avance");
    expect(calendarizationView).toContain("Vincular a una acción");
    expect(calendarizationView).toContain("orientation_action_id");
    expect(calendarizationView).toContain("Actividades cargadas correctamente");
    expect(calendarizationView).toContain("associationFilter");
    expect(calendarizationView).toContain("Revisar ${unlinkedEntriesCount} sin asociar");
    expect(api).toContain("calendarization(year)");
    expect(api).toContain("importCalendarizationReference");
    expect(api).toContain("createCalendarizationEntry");
    expect(router).toContain('path: "/orientation/calendarizacion"');
    expect(sideNav).toContain('link: "/orientation/calendarizacion"');
  });

  it("incorpora la vista estadística y los tres flujos de exportación PDF", () => {
    expect(view).toContain("Estadísticas del plan");
    expect(view).toContain("exportPlanPdf");
    expect(view).toContain("exportActionPdf");
    expect(view).toContain("exportStatisticsPdf");
    expect(api).toContain("statistics(planId)");
    expect(router).toContain('path: "/orientation/estadisticas"');
    expect(sideNav).toContain('link: "/orientation/estadisticas"');
  });

  it("expone Orientación directamente en la barra lateral", () => {
    expect(sideNav).toContain("orientationFallbackSection()");
    expect(sideNav).toContain('label: "Orientación"');
    expect(sideNav).toContain('link: "/orientation/plan-anual"');
    expect(sideNav).toContain('link: "/orientation/calendarizacion"');
    expect(sideNav).toContain('link: "/orientation/calendario"');
    expect(sideNav).toContain('link: "/orientation/estadisticas"');
    expect(sideNav).toContain("normalizeOrientationSection");
  });
});
