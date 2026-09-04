// @vitest-environment jsdom

import { flushPromises, mount } from "@vue/test-utils";
import { readFileSync } from "node:fs";
import Swal from "sweetalert2";
import { beforeEach, describe, expect, it, vi } from "vitest";
import DocumentationView from "../../resources/js/views/documentation/index.vue";
import {
  createDocumentation,
  getDocumentationCatalogs,
  listDocumentation,
  removeDocumentation,
  updateDocumentation,
} from "../../resources/js/services/documentation-api";

vi.mock("sweetalert2", () => ({
  default: { fire: vi.fn(() => Promise.resolve({ isConfirmed: true })) },
}));

vi.mock("../../resources/js/services/documentation-api", () => ({
  createDocumentation: vi.fn(),
  downloadDocumentation: vi.fn(),
  filenameFromDisposition: vi.fn(() => "documento.pdf"),
  getDocumentationCatalogs: vi.fn(),
  listDocumentation: vi.fn(),
  removeDocumentation: vi.fn(),
  updateDocumentation: vi.fn(),
}));

vi.mock("../../resources/js/layouts/main.vue", () => ({
  default: { template: "<div><slot /></div>" },
}));

vi.mock("../../resources/js/components/ui/loading-state.vue", () => ({
  default: { props: ["message"], template: "<div class='loading-state'>{{ message }}</div>" },
}));

const item = {
  id: 12,
  title: "Proyecto Educativo Institucional 2023",
  category: "proyecto-educativo",
  year: 2023,
  version: "Final",
  description: "Síntesis institucional y orientaciones formativas.",
  is_public: true,
  is_active: true,
  original_name: "PEI-2023.pdf",
  file_size: 2048000,
  file_size_human: "2.0 MB",
  updated_at: "2026-08-30T14:30:00-04:00",
};

const indexResponse = {
  data: [item],
  meta: { current_page: 1, last_page: 1, per_page: 15, total: 1, from: 1, to: 1 },
  summary: { total: 1, active: 1, public: 1, current_year: 0 },
  capabilities: { can_create: true, can_update: true, can_delete: true },
};

const mountView = () => mount(DocumentationView, {
  global: {
    stubs: {
      Layout: { template: "<div><slot /></div>" },
      LoadingState: { props: ["message"], template: "<div class='loading-state'>{{ message }}</div>" },
      Teleport: true,
    },
  },
});

describe("Módulo interno de Documentación", () => {
  beforeEach(() => {
    localStorage.clear();
    localStorage.setItem("permissions", JSON.stringify([
      "documentation.view",
      "documentation.create",
      "documentation.update",
      "documentation.delete",
    ]));
    vi.clearAllMocks();
    getDocumentationCatalogs.mockResolvedValue({
      data: {
        categories: [{ value: "proyecto-educativo", label: "Proyecto educativo" }],
        years: [2023],
        stats: { total: 1, active: 1, public: 1 },
        capabilities: { can_create: true, can_update: true, can_delete: true },
      },
    });
    listDocumentation.mockResolvedValue({ data: indexResponse });
    createDocumentation.mockResolvedValue({ data: { message: "Documento creado." } });
    updateDocumentation.mockResolvedValue({ data: { message: "Documento actualizado." } });
    removeDocumentation.mockResolvedValue({ data: { message: "Documento eliminado." } });
    Swal.fire.mockResolvedValue({ isConfirmed: true });
  });

  it("integra la ruta protegida y el acceso dinámico del sidenav", () => {
    const router = readFileSync("resources/js/router/index.js", "utf8");
    const sideNav = readFileSync("resources/js/components/side-nav.vue", "utf8");

    expect(router).toContain('path: "/documentation"');
    expect(router).toContain('permission: "documentation.view"');
    expect(sideNav).toContain('documentation: "/documentation"');
    expect(sideNav).toContain('documentation: "bx-folder-open"');
  });

  it("presenta el repositorio, trazabilidad y acciones según capacidades", async () => {
    const wrapper = mountView();
    await flushPromises();

    expect(wrapper.text()).toContain("Documentación");
    expect(wrapper.text()).toContain("Proyecto Educativo Institucional 2023");
    expect(wrapper.text()).toContain("Versión Final");
    expect(wrapper.text()).toContain("2.0 MB");
    expect(wrapper.find(".documentation-primary-action").exists()).toBe(true);
    expect(wrapper.find(".documentation-item__actions .is-download").exists()).toBe(true);
    expect(wrapper.find(".documentation-item__actions .is-danger").exists()).toBe(true);
  });

  it("mantiene un encabezado visual explícito y legible en el modal", async () => {
    const wrapper = mountView();
    await flushPromises();
    await wrapper.find(".documentation-primary-action").trigger("click");

    const header = wrapper.find(".documentation-modal__header");
    expect(header.exists()).toBe(true);
    expect(header.find("#documentation-modal-title").text()).toBe("Subir documento");
    expect(header.find(".documentation-modal__icon").exists()).toBe(true);
    expect(header.find(".documentation-modal__close").attributes("aria-label")).toBe("Cerrar");
    expect(wrapper.find('input[placeholder*="revisión 2026"]').exists()).toBe(true);
  });

  it("restaura el foco al botón que abrió el modal", async () => {
    const wrapper = mountView();
    await flushPromises();
    const trigger = wrapper.find(".documentation-primary-action");
    wrapper.vm.modalTrigger = trigger.element;
    wrapper.vm.showForm = true;
    await wrapper.vm.$nextTick();

    wrapper.vm.closeForm();
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.modalTrigger).toBeNull();
    expect(wrapper.vm.showForm).toBe(false);
  });

  it("envía búsqueda y filtros al listado paginado", async () => {
    const wrapper = mountView();
    await flushPromises();
    await wrapper.setData({
      filters: { search: "PEI", category: "proyecto-educativo", year: 2023, is_active: "1", is_public: "1" },
    });

    await wrapper.find(".documentation-filters").trigger("submit");
    await flushPromises();

    expect(listDocumentation).toHaveBeenLastCalledWith(expect.objectContaining({
      page: 1,
      search: "PEI",
      category: "proyecto-educativo",
      year: 2023,
      is_active: "1",
      is_public: "1",
    }));
  });

  it("valida y crea un documento con archivo y año, admitiendo versión opcional", async () => {
    const wrapper = mountView();
    await flushPromises();
    await wrapper.find(".documentation-primary-action").trigger("click");
    await wrapper.find(".documentation-form").trigger("submit");
    expect(wrapper.text()).toContain("Ingresa el título del documento");

    const file = new File(["pdf"], "PEI-2023.pdf", { type: "application/pdf" });
    Object.assign(wrapper.vm.form, {
      title: "Proyecto Educativo Institucional 2023",
      category: "proyecto-educativo",
      year: 2023,
      version: "",
      description: "Documento oficial",
      is_public: true,
      is_active: true,
      file,
    });
    await wrapper.vm.save();

    expect(createDocumentation).toHaveBeenCalledWith(expect.objectContaining({
      title: "Proyecto Educativo Institucional 2023",
      category: "proyecto-educativo",
      year: 2023,
      version: "",
      is_public: true,
      is_active: true,
      file,
    }));
    expect(getDocumentationCatalogs).toHaveBeenCalledTimes(2);
  });

  it("edita, reemplaza y elimina únicamente con autorización explícita", async () => {
    const wrapper = mountView();
    await flushPromises();
    const replacement = new File(["new"], "PEI-2024.pdf", { type: "application/pdf" });

    wrapper.vm.openEdit(item);
    Object.assign(wrapper.vm.form, { version: "2024", file: replacement });
    await wrapper.vm.save();
    expect(updateDocumentation).toHaveBeenCalledWith(12, expect.objectContaining({ file: replacement, version: "2024" }));

    await wrapper.vm.remove(item);
    expect(Swal.fire).toHaveBeenCalledWith(expect.objectContaining({ title: "¿Eliminar este documento?" }));
    expect(removeDocumentation).toHaveBeenCalledWith(12);
    expect(getDocumentationCatalogs).toHaveBeenCalledTimes(3);
  });

  it("no expone detalles SQL ni infraestructura en errores del servidor", async () => {
    listDocumentation.mockRejectedValueOnce({
      response: {
        status: 500,
        data: { message: "SQLSTATE[42S02]: Table missing (Connection: mysql, Database: gestion_adm)" },
      },
    });
    const wrapper = mountView();
    await flushPromises();

    expect(wrapper.text()).toContain("No fue posible cargar la documentación institucional.");
    expect(wrapper.text()).not.toContain("SQLSTATE");
    expect(wrapper.text()).not.toContain("gestion_adm");
  });
});
