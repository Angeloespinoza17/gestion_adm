// @vitest-environment jsdom
import { describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import Swal from "sweetalert2";
import ConvivenciaIndex from "../../resources/js/views/convivencia/index.vue";
import ConvivenciaIdpsWorkspace from "../../resources/js/components/convivencia/idps/convivencia-idps-workspace.vue";
import ConvivenciaAnalytics from "../../resources/js/components/convivencia/dashboard/convivencia-analytics.vue";
import { downloadPdfReport } from "../../resources/js/components/convivencia/module-utils";
import {
  buildConvivenciaAnalyticsCharts,
  downloadConvivenciaAnalyticsPdf,
} from "../../resources/js/components/convivencia/pdf/convivencia-analytics-pdf";
import {
  COURSE_REPORT_EXPORT_DATASETS,
  fetchCompleteCourseReportLists,
} from "../../resources/js/components/convivencia/reports/course-report-export";
import { canViewConvivenciaTab } from "../../resources/js/views/convivencia/tab-access";

const pdfHarness = vi.hoisted(() => ({
  createPdf: vi.fn(),
  download: vi.fn(),
}));

vi.mock("../../resources/js/utils/pdfmake", () => ({
  getPdfMake: vi.fn(async () => ({ createPdf: pdfHarness.createPdf })),
}));

vi.mock("../../resources/js/layouts/main.vue", () => ({
  default: { name: "LayoutStub", template: "<main><slot /></main>" },
}));

describe("exportación completa de reportes de convivencia", () => {
  it("consolida resumen, estadísticas por curso y al menos 30 gráficos en una sola vista", async () => {
    expect(ConvivenciaIndex.data().tabs.map((tab) => tab.key)).not.toContain("reportes");
    expect(ConvivenciaIndex.data().tabs[0]).toMatchObject({ key: "dashboard", label: "Análisis e informes" });
    expect(canViewConvivenciaTab("dashboard", { can_view_course_reports: true })).toBe(true);

    const wrapper = mount(ConvivenciaAnalytics, {
      props: {
        data: { metrics: { open_cases: 3, protocol_compliance_percentage: 91 }, charts: {} },
        reportData: {
          summary: { open_cases: 3, case_resolution_rate: 70, measure_completion_rate: 80, complaints: 4 },
          analytics: { courses: [{ course_id: 1, course: "5° Básico A", total_cases: 3, open_cases: 1, complaints: 2, daily_events: 4, derivations: 1, interviews: 2, measures: 3, resolution_rate: 70, measure_completion_rate: 80, overdue_measures: 0, activity_total: 15 }] },
          lists: { cases: [] },
          list_meta: { cases: { total: 0, shown: 0 } },
        },
        catalogs: { capabilities: { can_view_course_reports: true, can_export_reports: true } },
      },
      global: { stubs: { apexchart: { template: "<div class='chart-stub'></div>" } } },
    });

    expect(wrapper.text()).toContain("Una sola visión para decidir y reportar");
    expect(wrapper.text()).toContain("Estadísticas por curso");
    expect(wrapper.text()).toContain("5° Básico A");
    expect(wrapper.text()).toContain("Detalle autorizado del período");
    expect(wrapper.text()).toContain("Informe PDF");
    expect(wrapper.vm.availableChartCount).toBeGreaterThanOrEqual(30);
    expect(wrapper.vm.chartGroups.map((group) => group.key)).toEqual(["overview", "cases", "operations", "courses", "protocols"]);
    expect(new Set(wrapper.vm.chartDefinitions.map((chart) => chart.key)).size).toBe(wrapper.vm.chartDefinitions.length);
    wrapper.vm.activeChartGroup = "operations";
    await wrapper.vm.$nextTick();
    expect(wrapper.text()).toContain("Denuncias por tipo");
    expect(wrapper.text()).toContain("Derivaciones por prioridad");
    expect(wrapper.text()).toContain("Bitácora por tipo de evento");
  });

  it("genera un PDF A4 institucional con cabecera, tablas, confidencialidad y paginación", async () => {
    pdfHarness.createPdf.mockReturnValue({ download: pdfHarness.download });

    await downloadPdfReport(
      "reporte-convivencia",
      "Reporte por curso de convivencia",
      "Resumen integral autorizado",
      [
        { title: "Resumen del curso", headers: ["Indicador", "Resultado"], rows: [["Casos abiertos", 3]] },
        { title: "Entrevistas", headers: ["Fecha", "Motivo", "Seguimiento"], rows: [] },
      ],
    );

    const definition = pdfHarness.createPdf.mock.calls.at(-1)?.[0];
    expect(definition).toMatchObject({ pageSize: "A4", watermark: expect.objectContaining({ text: "CONFIDENCIAL" }) });
    expect(definition.info).toMatchObject({ author: "CNSC Gestión" });
    const footer = definition.footer(2, 4);
    expect(footer.columns[0].width).toBe("*");
    expect(footer.columns[1]).toMatchObject({ width: 74, text: "Página 2 de 4" });
    expect(JSON.stringify(definition.content)).toContain("CONVIVENCIA ESCOLAR · REPORTE");
    expect(JSON.stringify(definition.content)).toContain("Sin registros para los filtros aplicados.");
    expect(JSON.stringify(definition.content)).toContain('"fillColor":"#263A8F"');
    expect(pdfHarness.download).toHaveBeenCalledWith("reporte-convivencia.pdf");
  });

  it("reúne todas las páginas de cada conjunto sin usar la vista previa de 50", async () => {
    const totals = { cases: 63, complaints: 7, daily_logs: 54, derivations: 3, interviews: 2, measures: 51 };
    const http = {
      get: vi.fn(async (url, { params, signal }) => {
        expect(url).toBe("/api/convivencia/reports/course/export-data");
        expect(signal).toBeInstanceOf(AbortSignal);
        expect(params).toMatchObject({ academic_year_id: 2026, per_page: 25 });
        const total = totals[params.dataset];
        const start = (params.page - 1) * params.per_page;
        const rows = Array.from(
          { length: Math.max(0, Math.min(params.per_page, total - start)) },
          (_, offset) => ({ id: start + offset + 1, dataset: params.dataset }),
        );

        return {
          data: {
            dataset: params.dataset,
            data: rows,
            current_page: params.page,
            last_page: Math.ceil(total / params.per_page),
            per_page: params.per_page,
            total,
          },
        };
      }),
    };
    const controller = new AbortController();
    const progress = vi.fn();

    const result = await fetchCompleteCourseReportLists({
      filters: { academic_year_id: 2026, course_section_id: null, from: "" },
      perPage: 25,
      concurrency: 2,
      signal: controller.signal,
      http,
      onProgress: progress,
    });

    expect(Object.keys(result)).toEqual(COURSE_REPORT_EXPORT_DATASETS);
    for (const dataset of COURSE_REPORT_EXPORT_DATASETS) {
      expect(result[dataset]).toHaveLength(totals[dataset]);
      expect(new Set(result[dataset].map((row) => row.id)).size).toBe(totals[dataset]);
    }
    expect(http.get.mock.calls.filter(([, config]) => config.params.dataset === "cases")).toHaveLength(3);
    expect(http.get.mock.calls.filter(([, config]) => config.params.dataset === "daily_logs")).toHaveLength(3);
    expect(http.get.mock.calls.filter(([, config]) => config.params.dataset === "measures")).toHaveLength(3);
    expect(progress).toHaveBeenCalled();
  });

  it("genera el informe analítico único con gráficos, estadísticas por curso y anexos", async () => {
    pdfHarness.createPdf.mockReturnValue({ download: pdfHarness.download });
    await downloadConvivenciaAnalyticsPdf({
      filters: { academic_year_id: 4, course_section_id: 9, semester: 1 },
      catalogs: {
        academic_years: [{ id: 4, year: 2026 }],
        courses: [{ id: 9, display_name: "6° Básico A" }],
      },
      dashboard: { metrics: { protocol_compliance_percentage: 88, active_protocols: 3 } },
      report: {
        summary: { open_cases: 4, closed_cases: 6, case_resolution_rate: 60, complaints: 2, measure_completion_rate: 75 },
        analytics: {
          activity_by_type: [{ label: "Casos", total: 10 }],
          cases_by_status: [{ label: "abierto", total: 4 }],
          cases_by_classification: [{ label: "Conflicto", total: 7 }],
          cases_by_subclassification: [{ label: "Desacuerdo entre pares", total: 4 }],
          cases_by_criticality: [{ label: "Media", total: 5 }],
          cases_by_origin: [{ label: "denuncia", total: 6 }],
          complaints_by_status: [{ label: "recibida", total: 2 }],
          complaints_by_type: [{ label: "Maltrato", total: 2 }],
          complaints_by_complainant: [{ label: "apoderado", total: 2 }],
          derivations_by_status: [{ label: "en_revision", total: 2 }],
          derivations_by_scope: [{ label: "internal", total: 2 }],
          derivations_by_priority: [{ label: "alta", total: 1 }],
          interviews_by_type: [{ label: "Acogida", total: 3 }],
          interviews_by_follow_up: [{ label: "pendiente", total: 1 }],
          measures_by_status: [{ label: "en_proceso", total: 3 }],
          measures_by_type: [{ label: "Protectora", total: 3 }],
          daily_logs_by_type: [{ label: "Conflicto", total: 8 }],
          daily_logs_by_status: [{ label: "registrado", total: 8 }],
          monthly_activity: { labels: ["2026-03", "2026-04"], series: [{ name: "Casos", data: [4, 6] }] },
          courses: [{ course_id: 9, course: "6° Básico A", total_cases: 10, open_cases: 4, complaints: 2, daily_events: 8, derivations: 2, interviews: 3, measures: 4, resolution_rate: 60, measure_completion_rate: 75, overdue_measures: 1, activity_total: 29 }],
        },
      },
      lists: { cases: [{ id: 1, folio: "CONV-001", opened_at: "2026-04-01", classification_label: "Conflicto", criticality_label: "Media", status: "abierto" }] },
    });

    const definition = pdfHarness.createPdf.mock.calls.at(-1)?.[0];
    const serialized = JSON.stringify(definition.content);
    expect(definition).toMatchObject({ pageSize: "A4", pageOrientation: "landscape", watermark: expect.objectContaining({ text: "CONFIDENCIAL" }) });
    expect(buildConvivenciaAnalyticsCharts({
      report: {
        analytics: {
          activity_by_type: [{ label: "Casos", total: 10 }],
          courses: [],
        },
      },
      dashboard: {},
    })).toHaveLength(37);
    expect(serialized).toContain("MAPA VISUAL DE GESTIÓN");
    expect(serialized).toContain("DENUNCIAS POR TIPO");
    expect(serialized).toContain("DERIVACIONES POR PRIORIDAD");
    expect(serialized).toContain("ESTADÍSTICAS POR CURSO");
    expect(serialized).toContain("6° Básico A");
    expect(serialized).toContain("ANEXO DOCUMENTAL");
    expect(pdfHarness.download).toHaveBeenCalledWith("informe-convivencia-6-Basico-A.pdf");
  });

  it("falla cerrado si una respuesta paginada no coincide con el total anunciado", async () => {
    const http = {
      get: vi.fn().mockResolvedValue({
        data: {
          dataset: "cases",
          data: [{ id: 1 }],
          current_page: 1,
          last_page: 1,
          per_page: 200,
          total: 2,
        },
      }),
    };

    await expect(fetchCompleteCourseReportLists({ http, concurrency: 1 }))
      .rejects.toThrow("No fue posible reunir todos los registros de cases.");
  });

  it("detecta filtros sin aplicar y bloquea una exportación que mezclaría resumen y listas", async () => {
    const context = {
      reports: {
        data: { summary: {}, lists: {} },
        filters: { course_section_id: 22 },
        appliedFilters: { course_section_id: 11 },
        exporting: null,
      },
      canExportReports: true,
    };
    context.reportFiltersDirty = ConvivenciaIndex.computed.reportFiltersDirty.call(context);
    const alert = vi.spyOn(Swal, "fire").mockResolvedValue({ isConfirmed: true });

    expect(context.reportFiltersDirty).toBe(true);
    await ConvivenciaIndex.methods.exportCompleteReport.call(context, "excel");

    expect(alert).toHaveBeenCalledWith(expect.objectContaining({
      icon: "warning",
      text: expect.stringContaining("Actualiza el reporte antes de exportar"),
    }));
    expect(context.reports.exporting).toBeNull();
  });

  it("ofrece solo catálogos IDPS activos al crear y conserva el valor inactivo histórico al editar", () => {
    const dimensions = [
      { id: 1, code: "ACT", name: "Activa", active: true, instruments: [{ id: 10, name: "Activo", active: true }] },
      { id: 2, code: "INA", name: "Inactiva", active: false, instruments: [{ id: 20, name: "Histórico", active: false }] },
    ];
    const createContext = { dimensions, state: { resultForm: { id: null, dimension_id: null, instrument_id: null } } };
    const editContext = { dimensions, state: { resultForm: { id: 99, dimension_id: 2, instrument_id: 20 } } };

    expect(ConvivenciaIdpsWorkspace.computed.resultDimensionOptions.call(createContext).map((item) => item.value)).toEqual([1]);
    expect(ConvivenciaIdpsWorkspace.computed.resultDimensionOptions.call(editContext)).toEqual([
      expect.objectContaining({ value: 1 }),
      expect.objectContaining({ value: 2, text: expect.stringContaining("valor histórico") }),
    ]);
    expect(ConvivenciaIdpsWorkspace.computed.instrumentOptions.call(editContext)).toEqual([
      expect.objectContaining({ value: 20, text: expect.stringContaining("valor histórico") }),
    ]);
  });
});
