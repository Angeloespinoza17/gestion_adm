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
  total: 4,
  from: 1,
  to: 1,
  summary: { total: 4, today: 2, follow_up: 1, high_priority: 1 },
  sources,
  catalogs: {
    priorities: [{ value: "alta", label: "Alta" }, { value: "urgente", label: "Urgente" }],
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

  it("renders the four real operational sources in one read-only workspace", async () => {
    const wrapper = mountView();
    await flushPromises();

    expect(wrapper.text()).toContain("Revisión central de bitácoras");
    expect(wrapper.text()).toContain("Sólo lectura");
    expect(wrapper.text()).toContain("Inspectoría");
    expect(wrapper.text()).toContain("Portería");
    expect(wrapper.text()).toContain("Enfermería");
    expect(wrapper.text()).toContain("Convivencia Escolar");
    expect(wrapper.text()).toContain("Todos los registros");
    expect(wrapper.findAll(".logbook-source")).toHaveLength(4);
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
});
