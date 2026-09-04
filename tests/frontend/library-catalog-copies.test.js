// @vitest-environment jsdom

import { flushPromises, mount } from "@vue/test-utils";
import axios from "axios";
import { beforeEach, describe, expect, it, vi } from "vitest";
import CatalogTab from "../../resources/js/components/library/tabs/catalog-tab.vue";

vi.mock("axios", () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock("sweetalert2", () => ({
  default: { fire: vi.fn(() => Promise.resolve({ isConfirmed: true })) },
}));

const workSummary = {
  id: 41,
  material_type: "libro",
  title: "El principito",
  main_author: "Antoine de Saint-Exupéry",
  publisher: "Editorial de prueba",
  isbn: "9780156013987",
  internal_code: "BIB-OBR-2026-0041",
  total_copies: 3,
  available_copies: 1,
  general_status: "disponible",
  categoria: { id: 3, name: "Narrativa" },
  ubicacion: { id: 7, code: "SALA-1", name: "Sala principal" },
};

const workDetails = {
  ...workSummary,
  ejemplares: [
    {
      id: 101,
      code: "BIB-EJ-2026-0101",
      barcode: "780000000101",
      physical_state: "bueno",
      availability_status: "disponible",
      last_inventory_checked_at: "2026-08-20",
      is_active: true,
      is_loanable: true,
      ubicacion: { id: 7, code: "SALA-1", name: "Sala principal" },
    },
    {
      id: 102,
      code: "BIB-EJ-2026-0102",
      barcode: "780000000102",
      physical_state: "regular",
      availability_status: "prestado",
      last_inventory_checked_at: null,
      is_active: true,
      is_loanable: true,
      ubicacion: { id: 8, code: "EST-2", name: "Estante 2" },
    },
    {
      id: 103,
      code: "BIB-EJ-2026-0103",
      barcode: null,
      legacy_registration_number: "REG-1998-14",
      physical_state: "danado",
      availability_status: "danado",
      last_inventory_checked_at: "2026-08-21",
      is_active: false,
      is_loanable: false,
      physical_location: "Taller de reparación",
      ubicacion: null,
    },
  ],
};

const catalogs = {
  categories: [],
  subcategories: [],
  locations: [],
  course_sections: [],
  material_types: [{ value: "libro", label: "Libro" }],
  obra_statuses: [{ value: "disponible", label: "Disponible" }],
  ejemplar_states: [
    { value: "bueno", label: "Bueno" },
    { value: "regular", label: "Regular" },
    { value: "danado", label: "Dañado" },
  ],
  ejemplar_availability_statuses: [
    { value: "disponible", label: "Disponible" },
    { value: "prestado", label: "Prestado" },
    { value: "danado", label: "Dañado" },
  ],
  capabilities: { manage_catalog: true },
};

const indexResponse = {
  data: [workSummary],
  current_page: 1,
  total: 1,
  per_page: 12,
};

const mountTab = () => mount(CatalogTab, {
  props: { catalogs },
  global: {
    mocks: { $route: { query: {} } },
    stubs: {
      LibraryHelpButton: true,
      LibraryStatusBadge: {
        props: ["status"],
        template: "<span class='status-badge'>{{ status }}</span>",
      },
      LoadingState: { template: "<div class='loading-state'><slot /></div>" },
      BAlert: { template: "<div><slot /></div>" },
      BButton: { template: "<button type='button'><slot /></button>" },
      BFormInput: { props: ["modelValue"], template: "<input :value='modelValue' />" },
      BFormSelect: { props: ["modelValue", "options"], template: "<select :value='modelValue' />" },
      BFormTextarea: { props: ["modelValue"], template: "<textarea :value='modelValue' />" },
      BFormCheckbox: { props: ["modelValue"], template: "<label><input type='checkbox' :checked='modelValue' /><slot /></label>" },
      BPagination: { template: "<div class='pagination-stub' />" },
      BModal: {
        props: ["modelValue"],
        template: "<div v-if='modelValue' class='modal-stub'><slot /></div>",
      },
    },
  },
});

describe("Biblioteca · ejemplares por título", () => {
  beforeEach(() => {
    window.localStorage.clear();
    Object.defineProperty(URL, "createObjectURL", {
      configurable: true,
      value: vi.fn(() => "blob:portada-libro"),
    });
    Object.defineProperty(URL, "revokeObjectURL", {
      configurable: true,
      value: vi.fn(),
    });
    axios.get.mockReset();
    axios.post.mockReset();
    axios.put.mockReset();
    axios.post.mockResolvedValue({ data: { data: workDetails } });
    axios.put.mockResolvedValue({ data: { data: workDetails } });
    axios.get.mockImplementation((url) => Promise.resolve({
      data: url === "/api/biblioteca/obras"
        ? indexResponse
        : { data: workDetails },
    }));
  });

  it("opens the title and shows every physical copy with its own code and state", async () => {
    const wrapper = mountTab();
    await flushPromises();

    const viewButton = wrapper.find(".action-button--view");
    expect(viewButton.text()).toContain("Ver ejemplares");
    await viewButton.trigger("click");
    await flushPromises();

    expect(axios.get).toHaveBeenCalledWith("/api/biblioteca/obras/41");
    expect(wrapper.find(".copies-panel").exists()).toBe(true);
    expect(wrapper.findAll(".copies-table tbody tr")).toHaveLength(3);
    expect(wrapper.text()).toContain("BIB-EJ-2026-0101");
    expect(wrapper.text()).toContain("BIB-EJ-2026-0102");
    expect(wrapper.text()).toContain("BIB-EJ-2026-0103");
    expect(wrapper.text()).toContain("Taller de reparación");
    expect(wrapper.text()).toContain("Ejemplar inactivo");
    expect(wrapper.text()).toContain("Préstamo restringido");
    expect(wrapper.vm.copySummary).toEqual({ total: 3, available: 1, circulating: 1, incidents: 1 });
  });

  it("filters the copies without hiding inactive inventory by default", async () => {
    const wrapper = mountTab();
    await flushPromises();
    await wrapper.find(".action-button--view").trigger("click");
    await flushPromises();

    expect(wrapper.vm.copyFilters.active_scope).toBe("all");
    await wrapper.setData({
      copyFilters: {
        search: "",
        physical_state: null,
        availability_status: "prestado",
        active_scope: "all",
      },
    });

    expect(wrapper.findAll(".copies-table tbody tr")).toHaveLength(1);
    expect(wrapper.text()).toContain("BIB-EJ-2026-0102");
    expect(wrapper.text()).not.toContain("BIB-EJ-2026-0103");
  });

  it("selects a cover from the computer and sends it with the new book", async () => {
    const wrapper = mountTab();
    await flushPromises();
    await wrapper.find(".hero-button").trigger("click");

    expect(wrapper.find(".cover-action--camera").text()).toContain("Tomar fotografía");
    expect(wrapper.find(".cover-action--file").text()).toContain("Seleccionar desde computador");
    expect(wrapper.find('input[capture="environment"]').exists()).toBe(true);

    const cover = new File(["imagen"], "portada.jpg", { type: "image/jpeg" });
    const fileInput = wrapper.find(".cover-action--file input");
    Object.defineProperty(fileInput.element, "files", {
      configurable: true,
      value: [cover],
    });
    await fileInput.trigger("change");

    expect(wrapper.vm.coverFile).toBe(cover);
    expect(wrapper.find(".cover-upload__preview img").attributes("src")).toBe("blob:portada-libro");
    expect(wrapper.text()).toContain("Lista para guardar");

    wrapper.vm.form.title = "Libro fotografiado";
    wrapper.vm.form.main_author = "Biblioteca escolar";
    await wrapper.vm.save();
    await flushPromises();

    const [url, payload] = axios.post.mock.calls.find(([requestUrl]) => requestUrl === "/api/biblioteca/obras");
    expect(url).toBe("/api/biblioteca/obras");
    expect(payload).toBeInstanceOf(FormData);
    expect(payload.get("title")).toBe("Libro fotografiado");
    expect(payload.get("cover_image")).toBe(cover);
  });
});
