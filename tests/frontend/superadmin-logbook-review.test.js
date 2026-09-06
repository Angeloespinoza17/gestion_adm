// @vitest-environment jsdom

import { flushPromises, mount } from "@vue/test-utils";
import axios from "axios";
import { beforeEach, describe, expect, it, vi } from "vitest";
import LogbookReview from "../../resources/js/views/superadmin/logbook-review.vue";

vi.mock("axios", () => ({
  default: { get: vi.fn() },
}));

vi.mock("../../resources/js/layouts/main.vue", () => ({
  default: { template: "<div><slot /></div>" },
}));

vi.mock("../../resources/js/components/ui/loading-state.vue", () => ({
  default: { template: "<div class='loading-state'><slot /></div>" },
}));

const sources = [
  { value: "inspectoria", label: "Inspectoría", description: "Novedades de jornada", icon: "bx-shield-quarter", accent: "#5b5bd6", route: "/inspectoria/bitacora", count: 1 },
  { value: "porter", label: "Portería", description: "Control de accesos", icon: "bx-building-house", accent: "#0d9488", route: "/porter/daily-log", count: 1 },
  { value: "infirmary", label: "Enfermería", description: "Continuidad de atención", icon: "bx-plus-medical", accent: "#dc5f73", route: "/infirmary/daily-log", count: 1 },
  { value: "convivencia", label: "Convivencia Escolar", description: "Hechos diarios", icon: "bx-happy-heart-eyes", accent: "#d97706", route: "/convivencia/bitacora", count: 1 },
  { value: "staff_logbook", label: "Funcionarios", description: "Registros personales", icon: "bx-notepad", accent: "#2563eb", route: "/bitacora", count: 1 },
  { value: "security_rounds", label: "Nocheros", description: "Rondas nocturnas", icon: "bx-moon", accent: "#4338ca", route: "/security/shifts", count: 1 },
  { value: "security_incidents", label: "Incidencias nocturnas", description: "Alertas y resolución", icon: "bx-error-alt", accent: "#be3652", route: "/security/incidents", count: 1 },
];

const entry = {
  key: "infirmary-19",
  source: "infirmary",
  source_id: 19,
  source_label: "Enfermería",
  occurred_at: "2026-08-28 10:20:00",
  category: "contacto_apoderado",
  priority: "urgente",
  status: "en_seguimiento",
  title: "Atención relevante de jornada",
  detail: "Se realizó una atención que requiere continuidad.",
  author: { id: 1, name: "Enfermera CNSC" },
  student: { id: 7, name: "Antonia Soto", rut: "21.111.222-3" },
  course: { id: 8, name: "1° medio A" },
  requires_follow_up: true,
  is_sensitive: true,
  extra: { action_taken: "Se coordinó con la apoderada.", follow_up_note: "Confirmar evolución." },
};

const response = {
  data: [entry],
  current_page: 1,
  last_page: 1,
  per_page: 20,
  total: 7,
  from: 1,
  to: 1,
  summary: { total: 7, today: 2, follow_up: 1, high_priority: 1 },
  sources,
  catalogs: {
    priorities: [{ value: "alta", label: "Alta" }, { value: "urgente", label: "Urgente" }, { value: "critica", label: "Crítica" }],
    statuses: [{ value: "registrado", label: "Registrado" }, { value: "en_seguimiento", label: "En seguimiento" }],
  },
};

const mountView = () => mount(LogbookReview, {
  global: {
    stubs: {
      Layout: { template: "<div><slot /></div>" },
      LoadingState: { template: "<div class='loading-state' />" },
      BAlert: { props: ["show"], template: "<div v-if='show'><slot /></div>" },
      BFormSelect: {
        props: ["modelValue", "options"],
        emits: ["update:modelValue", "change"],
        template: "<select :value='modelValue' @change=\"$emit('update:modelValue', $event.target.value); $emit('change', $event.target.value)\"><option v-for='option in options' :key='option.value' :value='option.value'>{{ option.text }}</option></select>",
      },
      BPagination: { template: "<nav class='pagination-stub' />" },
      BModal: {
        props: ["modelValue"],
        template: "<div v-if='modelValue' class='modal-stub'><div class='modal-header-stub'><slot name='header' /></div><slot /></div>",
      },
      RouterLink: { props: ["to"], template: "<a :href='to'><slot /></a>" },
    },
  },
});

describe("Revisión central de bitácoras para Superadmin", () => {
  beforeEach(() => {
    axios.get.mockReset();
    axios.get.mockResolvedValue({ data: response });
  });

  it("renders all real sources, including staff and night-shift logbooks, in one read-only workspace", async () => {
    const wrapper = mountView();
    await flushPromises();

    expect(wrapper.text()).toContain("Revisión central de bitácoras");
    expect(wrapper.text()).toContain("Sólo lectura");
    expect(wrapper.text()).toContain("Inspectoría");
    expect(wrapper.text()).toContain("Portería");
    expect(wrapper.text()).toContain("Enfermería");
    expect(wrapper.text()).toContain("Convivencia Escolar");
    expect(wrapper.text()).toContain("Funcionarios");
    expect(wrapper.text()).toContain("Nocheros");
    expect(wrapper.text()).toContain("Incidencias nocturnas");
    expect(wrapper.text()).toContain("Todos los registros");
    expect(wrapper.findAll(".logbook-source")).toHaveLength(7);
  });

  it("sends global production filters to the paginated endpoint", async () => {
    const wrapper = mountView();
    await flushPromises();
    await wrapper.setData({
      filters: {
        search: "apoderada",
        source: "infirmary",
        date_from: "2026-08-01",
        date_to: "2026-08-28",
        priority: "urgente",
        status: "en_seguimiento",
      },
    });

    await wrapper.vm.loadEntries(2);

    expect(axios.get).toHaveBeenLastCalledWith("/api/superadmin/logbooks", {
      params: {
        page: 2,
        per_page: 20,
        search: "apoderada",
        source: "infirmary",
        date_from: "2026-08-01",
        date_to: "2026-08-28",
        priority: "urgente",
        status: "en_seguimiento",
      },
    });
  });

  it("opens a protected detail file without edit controls", async () => {
    const wrapper = mountView();
    await flushPromises();

    await wrapper.find(".logbook-detail-button").trigger("click");

    expect(wrapper.find(".modal-stub").exists()).toBe(true);
    expect(wrapper.find(".modal-stub").text()).toContain("Consulta protegida y de sólo lectura");
    expect(wrapper.find(".modal-stub").text()).toContain("Información sensible");
    expect(wrapper.find(".modal-stub").text()).toContain("Se coordinó con la apoderada.");
    expect(wrapper.find(".modal-stub").text()).not.toContain("Editar");
  });

  it("shows the nocturnal route, sectors and incidents in the protected detail", async () => {
    const nightEntry = {
      ...entry,
      key: "security_rounds-7",
      source: "security_rounds",
      source_id: 7,
      source_label: "Nocheros",
      title: "Ronda nocturna #2 · José Campos",
      priority: "critica",
      status: "requiere_atencion",
      student: null,
      extra: {
        act_number: "ACT-NOCHE-007",
        nochero_name: "José Campos",
        shift_window: "28-08-2026 22:00 a 29-08-2026 07:00",
        coverage_label: "Todo el colegio",
        sector_count: 1,
        incident_count: 1,
        evidence_count: 2,
        geolocation_recorded: true,
        location_accuracy: "8.50",
        sectors: [{ name: "Laboratorio", state: "riesgo_detectado", observations: "Humedad visible." }],
        incidents: [{ id: 1, title: "Fuga de agua", description: "Sector aislado.", priority: "critica", sector: "Laboratorio", status: "Pendiente", responsible: "Mantención" }],
      },
    };
    axios.get.mockResolvedValueOnce({ data: { ...response, data: [nightEntry] } });
    const wrapper = mountView();
    await flushPromises();

    await wrapper.find(".logbook-detail-button").trigger("click");

    expect(wrapper.find(".modal-stub").text()).toContain("ACT-NOCHE-007");
    expect(wrapper.find(".modal-stub").text()).toContain("Sectores revisados");
    expect(wrapper.find(".modal-stub").text()).toContain("Laboratorio");
    expect(wrapper.find(".modal-stub").text()).toContain("Fuga de agua");
    expect(wrapper.find(".modal-stub a").attributes("href")).toBe("/security/shifts");
  });

  it("integrates each nocturnal incident as its own traceable read-only record", async () => {
    const incidentEntry = {
      ...entry,
      key: "security_incidents-11",
      source: "security_incidents",
      source_id: 11,
      source_label: "Incidencias nocturnas",
      title: "Fuga de agua detectada",
      detail: "Se aisló preventivamente el sector.",
      priority: "critica",
      status: "pendiente",
      student: null,
      extra: {
        act_number: "ACT-NOCHE-007",
        nochero_name: "José Campos",
        shift_window: "28-08-2026 22:00 a 29-08-2026 07:00",
        sector_name: "Laboratorio",
        status_label: "Pendiente",
        responsible: "Mantención",
        response_due_at: "2026-08-29 01:00",
        requires_immediate_attention: true,
        comments_count: 2,
        assignments_count: 1,
        response_summary: "Se cerró la llave de paso.",
      },
    };
    axios.get.mockResolvedValueOnce({ data: { ...response, data: [incidentEntry] } });
    const wrapper = mountView();
    await flushPromises();

    await wrapper.find(".logbook-detail-button").trigger("click");

    expect(wrapper.find(".modal-stub").text()).toContain("Incidencias nocturnas");
    expect(wrapper.find(".modal-stub").text()).toContain("Responsable actual");
    expect(wrapper.find(".modal-stub").text()).toContain("Mantención");
    expect(wrapper.find(".modal-stub").text()).toContain("Se cerró la llave de paso.");
    expect(wrapper.find(".modal-stub a").attributes("href")).toBe("/security/incidents");
    expect(wrapper.find(".modal-stub").text()).not.toContain("Editar");
  });
});
