// @vitest-environment jsdom

import { flushPromises, mount } from "@vue/test-utils";
import axios from "axios";
import { beforeEach, describe, expect, it, vi } from "vitest";
import IncidentsView from "../../resources/js/views/security/incidents.vue";

vi.mock("axios", () => ({
  default: { get: vi.fn(), post: vi.fn() },
}));

vi.mock("sweetalert2", () => ({
  default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

vi.mock("../../resources/js/utils/pdfmake", () => ({
  getPdfMake: vi.fn(),
}));

vi.mock("../../resources/js/layouts/main.vue", () => ({
  default: { template: "<div><slot /></div>" },
}));

vi.mock("../../resources/js/components/ui/loading-state.vue", () => ({
  default: { template: "<div class='loading-state' />" },
}));

const incident = {
  id: 9,
  title: "Ventana forzada en laboratorio",
  description: "Se aisló el sector y se informó a mantención.",
  priority: "critica",
  status_id: 1,
  current_responsible_user_id: 4,
  sector_name: "Laboratorio",
  response_due_at: "2026-09-05 04:00",
  created_at: "2026-09-05 01:10",
  status: { id: 1, code: "pendiente", name: "Pendiente", is_closed: false },
  current_responsible: { id: 4, name: "Jefe de mantención" },
  shift: { id: 3, coverage_label: "Todo el colegio", staff: { id: 2, full_name: "José Campos" } },
  round: { id: 7, round_number: 2, act_number: "ACT-NOCHE-007" },
};

const catalogs = {
  priorities: [
    { value: "baja", label: "Baja" },
    { value: "media", label: "Media" },
    { value: "alta", label: "Alta" },
    { value: "critica", label: "Urgente / crítica" },
  ],
  incident_statuses: [
    { id: 1, code: "pendiente", name: "Pendiente", is_closed: false },
    { id: 2, code: "resuelta", name: "Resuelta", is_closed: true },
  ],
  responsible_users: [
    { id: 4, name: "Jefe de mantención", staff: { full_name: "Ana Pérez" } },
  ],
  current_user: { id: 1, name: "Superadmin" },
  capabilities: { can_manage_incidents: true, can_export: true },
};

const indexResponse = {
  data: [incident],
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 1,
  from: 1,
  to: 1,
  summary: { total: 1, open: 1, critical: 1, overdue: 0, unassigned: 0 },
};

const showIncident = {
  ...incident,
  assignments: [{ id: 1, user_id: 4, is_current: true, user: { id: 4, name: "Jefe de mantención" } }],
  comments: [{ id: 5, comment: "Se solicitó revisión inmediata.", created_at: "2026-09-05 01:30", user: { name: "Superadmin" }, status: { name: "Pendiente" } }],
  evidences: [{ id: 2 }],
};

const multiselectStub = {
  props: ["modelValue", "options", "mode"],
  emits: ["update:modelValue"],
  template: "<select :multiple='mode === \"tags\"' :value='modelValue' @change=\"$emit('update:modelValue', mode === 'tags' ? Array.from($event.target.selectedOptions).map(option => option.value) : $event.target.value)\"><option v-for='option in options' :key='option.value' :value='option.value'>{{ option.label }}</option></select>",
};

const mountView = () => mount(IncidentsView, {
  global: {
    stubs: {
      Layout: { template: "<div><slot /></div>" },
      LoadingState: { template: "<div class='loading-state' />" },
      Multiselect: multiselectStub,
      BAlert: { props: ["show"], template: "<div v-if='show'><slot /></div>" },
      BFormInput: { props: ["modelValue", "type"], emits: ["update:modelValue", "change"], template: "<input :type='type || \"text\"' :value='modelValue' @input=\"$emit('update:modelValue', $event.target.value)\" @change=\"$emit('change', $event)\">" },
      BFormTextarea: { props: ["modelValue"], emits: ["update:modelValue"], template: "<textarea :value='modelValue' @input=\"$emit('update:modelValue', $event.target.value)\"></textarea>" },
    },
  },
});

describe("Gestión visual de incidencias nocturnas", () => {
  beforeEach(() => {
    axios.get.mockReset();
    axios.post.mockReset();
    axios.get.mockImplementation((url) => {
      if (url === "/api/security/catalogs") return Promise.resolve({ data: catalogs });
      if (url === "/api/security/incidents") return Promise.resolve({ data: indexResponse });
      if (url === "/api/security/incidents/9") return Promise.resolve({ data: { data: showIncident } });
      return Promise.reject(new Error(`Unexpected URL ${url}`));
    });
  });

  it("renders a clear operational queue with server summaries", async () => {
    const wrapper = mountView();
    await flushPromises();

    expect(wrapper.text()).toContain("Incidencias de rondas");
    expect(wrapper.text()).toContain("Compromiso vencido");
    expect(wrapper.text()).toContain("Sin responsable");
    expect(wrapper.text()).toContain("Ventana forzada en laboratorio");
    expect(wrapper.text()).toContain("José Campos");
    expect(wrapper.findAll(".incident-metric")).toHaveLength(4);
    expect(wrapper.findAll(".incident-card")).toHaveLength(1);
    expect(axios.get).toHaveBeenCalledWith("/api/security/incidents", {
      params: expect.objectContaining({ page: 1, pending_only: true }),
    });
  });

  it("opens the traceable management file and its follow-up history", async () => {
    const wrapper = mountView();
    await flushPromises();

    await wrapper.find(".incident-card").trigger("click");
    await flushPromises();

    expect(wrapper.find(".incident-detail").text()).toContain("ACT-NOCHE-007");
    expect(wrapper.find(".incident-detail").text()).toContain("José Campos");
    expect(wrapper.find(".incident-detail").text()).toContain("Jefe de mantención");
    expect(wrapper.find(".incident-detail").text()).toContain("Se solicitó revisión inmediata.");
    expect(wrapper.find(".incident-form-section").exists()).toBe(true);
    expect(wrapper.text()).toContain("Guardar gestión");
  });

  it("keeps the incident detail read-only without management authority", async () => {
    axios.get.mockImplementation((url) => {
      if (url === "/api/security/catalogs") return Promise.resolve({ data: { ...catalogs, capabilities: { can_manage_incidents: false, can_export: false }, current_user: { id: 88 } } });
      if (url === "/api/security/incidents") return Promise.resolve({ data: indexResponse });
      if (url === "/api/security/incidents/9") return Promise.resolve({ data: { data: { ...showIncident, current_responsible_user_id: 4 } } });
      return Promise.reject(new Error(`Unexpected URL ${url}`));
    });
    const wrapper = mountView();
    await flushPromises();
    await wrapper.find(".incident-card").trigger("click");
    await flushPromises();

    expect(wrapper.text()).toContain("Consulta de sólo lectura");
    expect(wrapper.find(".incident-form-section").exists()).toBe(false);
    expect(wrapper.text()).not.toContain("Exportar PDF");
  });
});
