// @vitest-environment jsdom

import { flushPromises, mount } from "@vue/test-utils";
import axios from "axios";
import { beforeEach, describe, expect, it, vi } from "vitest";
import GradeStatistics from "../../resources/js/views/students/grade-statistics.vue";

vi.mock("axios", () => ({ default: { get: vi.fn() } }));
vi.mock("../../resources/js/layouts/main.vue", () => ({ default: { template: "<div><slot /></div>" } }));

const payload = {
  summary: {
    assessments: 7, students_evaluated: 18, expected_results: 84, completed_results: 65,
    pending_results: 19, comparable_results: 62, average_grade: 5.36, approval_rate: 82.26,
    coverage_rate: 77.38, passed_results: 51, failed_results: 11, absent_results: 2,
    exempt_results: 1, imported_results: 54, manual_results: 11,
  },
  distribution: [
    { key: "insufficient", label: "1,0 – 3,9", description: "Insuficiente", count: 11 },
    { key: "sufficient", label: "4,0 – 4,9", description: "Suficiente", count: 14 },
    { key: "good", label: "5,0 – 5,9", description: "Bueno", count: 22 },
    { key: "outstanding", label: "6,0 – 7,0", description: "Destacado", count: 15 },
  ],
  evaluation_progress: [
    { evaluation_number: 1, label: "Evaluación 1", assessments: 3, average_grade: 5.2, approval_rate: 78.4, results: 31 },
    { evaluation_number: 2, label: "Evaluación 2", assessments: 3, average_grade: 5.5, approval_rate: 82.1, results: 29 },
  ],
  by_course: [{ id: 8, name: "1° Medio A", assessments: 7, expected_results: 84, completed_results: 65, pending_results: 19, comparable_results: 62, average_grade: 5.36, approval_rate: 82.26, coverage_rate: 77.38 }],
  by_subject: [{ id: 3, name: "Lenguaje", assessments: 7, expected_results: 84, completed_results: 65, pending_results: 19, comparable_results: 62, average_grade: 5.36, approval_rate: 82.26, coverage_rate: 77.38 }],
  catalogs: {
    academic_years: [{ id: 2, name: "2026", year: 2026, is_active: true }],
    courses: [{ id: 8, name: "1° Medio A" }], subjects: [{ id: 3, name: "Lenguaje" }],
    periods: [{ id: "semester-1", name: "Primer semestre" }],
  },
  meta: {
    school: { id: 1, name: "Colegio de prueba" }, academic_year: { id: 2, name: "2026", year: 2026 },
    methodology: ["Las notas numéricas se normalizan a una escala equivalente de 1,0 a 7,0."],
  },
};

const studentsPayload = {
  data: [{
    student_profile_id: 44, name: "Alumna Consolidada", courses: [{ id: 8, name: "1° Medio A" }],
    assessments: 7, expected_results: 7, completed_results: 6, pending_results: 1,
    comparable_results: 6, average_grade: 5.7, approval_rate: 83.33, coverage_rate: 85.71,
  }],
  pagination: { page: 1, per_page: 25, total: 1, last_page: 1 },
};

const studentDetailPayload = {
  student: { student_profile_id: 44, name: "Alumna Consolidada", courses: [{ id: 8, name: "1° Medio A" }] },
  summary: { assessments: 2, expected_results: 2, completed_results: 1, pending_results: 1, comparable_results: 1, average_grade: 6.2, approval_rate: 100, coverage_rate: 50 },
  by_subject: [{ id: 3, name: "Lenguaje", assessments: 2, expected_results: 2, completed_results: 1, pending_results: 1, average_grade: 6.2, approval_rate: 100, coverage_rate: 50 }],
  evaluations: [
    { assessment_id: 101, evaluation_number: 1, label: "Evaluación 1", name: "Control de lectura", subject: { id: 3, name: "Lenguaje" }, course: { id: 8, name: "1° Medio A" }, state: "recorded", state_label: "Registrada", grade: 6.2, qualitative_value: null, source: "annual_import" },
    { assessment_id: 102, evaluation_number: 2, label: "Evaluación 2", name: "Ensayo", subject: { id: 3, name: "Lenguaje" }, course: { id: 8, name: "1° Medio A" }, state: "missing", state_label: "Sin registrar", grade: null, qualitative_value: null, source: null },
  ],
};

const mountView = () => mount(GradeStatistics, {
  global: {
    stubs: {
      Layout: { template: "<div><slot /></div>" },
      apexchart: { props: ["options", "series"], template: "<div class='apexchart-stub' />" },
      teleport: true,
    },
  },
});

describe("Estadísticas de calificaciones", () => {
  beforeEach(() => {
    localStorage.setItem("permissions", JSON.stringify(["grade_statistics.view", "grade_statistics.view_students"]));
    axios.get.mockReset();
    axios.get.mockImplementation((url) => {
      if (url.endsWith("/students/44")) return Promise.resolve({ data: studentDetailPayload });
      if (url.endsWith("/students")) return Promise.resolve({ data: studentsPayload });
      return Promise.resolve({ data: payload });
    });
  });

  it("renders evaluation-based charts, provenance and variable subject coverage", async () => {
    const wrapper = mountView();
    await flushPromises();

    expect(wrapper.text()).toContain("Estadísticas de calificaciones");
    expect(wrapper.text()).toContain("5,36");
    expect(wrapper.text()).toContain("82,3%");
    expect(wrapper.text()).toContain("Importación anual");
    expect(wrapper.text()).toContain("Desempeño según avance evaluativo");
    expect(wrapper.text()).toContain("Cantidad de evaluaciones");
    expect(wrapper.text()).toContain("La cantidad de evaluaciones puede variar entre asignaturas");
    expect(wrapper.text()).toContain("Detalle nominal con permiso explícito");
    expect(wrapper.findAll(".apexchart-stub")).toHaveLength(3);
  });

  it("sends year, course, subject and period filters without estimated dates", async () => {
    const wrapper = mountView();
    await flushPromises();
    await wrapper.findAll("select")[1].setValue("8");
    await wrapper.findAll("select")[2].setValue("3");
    await wrapper.findAll("select")[3].setValue("semester-1");
    await wrapper.find(".apply-button").trigger("click");
    await flushPromises();

    expect(axios.get).toHaveBeenLastCalledWith("/api/students/grades/statistics", {
      params: expect.objectContaining({
        academic_year_id: 2,
        course_section_id: 8,
        schedule_subject_id: 3,
        assessment_period_code: "semester-1",
      }),
    });
    expect(wrapper.find("input[type='date']").exists()).toBe(false);
  });

  it("opens course detail and an all-course student consolidation through the protected endpoint", async () => {
    const wrapper = mountView();
    await flushPromises();

    await wrapper.find(".detail-button").trigger("click");
    await flushPromises();
    expect(wrapper.text()).toContain("Detalle de 1° Medio A");
    expect(wrapper.text()).toContain("Alumna Consolidada");
    expect(axios.get).toHaveBeenLastCalledWith("/api/students/grades/statistics/students", {
      params: expect.objectContaining({ course_section_id: 8 }),
    });

    await wrapper.find(".nominal-button").trigger("click");
    await flushPromises();
    const lastParams = axios.get.mock.calls.at(-1)[1].params;
    expect(wrapper.text()).toContain("Consolidado por alumna");
    expect(lastParams).not.toHaveProperty("course_section_id");

    await wrapper.find(".student-view-button").trigger("click");
    await flushPromises();
    expect(axios.get).toHaveBeenLastCalledWith("/api/students/grades/statistics/students/44", {
      params: expect.objectContaining({ academic_year_id: 2 }),
    });
    expect(wrapper.text()).toContain("EXPEDIENTE ACADÉMICO");
    expect(wrapper.text()).toContain("Alumna Consolidada");
    expect(wrapper.text()).toContain("Control de lectura");
    expect(wrapper.text()).toContain("Sin registrar");
  });
});
