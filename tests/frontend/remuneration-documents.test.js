// @vitest-environment jsdom

import { flushPromises, mount } from "@vue/test-utils";
import axios from "axios";
import { beforeEach, describe, expect, it, vi } from "vitest";
import Documents from "../../resources/js/views/remuneration/documents.vue";

vi.mock("axios", () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

vi.mock("sweetalert2", () => ({
  default: { fire: vi.fn(() => Promise.resolve({ isConfirmed: true })) },
}));

vi.mock("../../resources/js/layouts/main.vue", () => ({
  default: { template: "<div><slot /></div>" },
}));

vi.mock("../../resources/js/components/page-header.vue", () => ({
  default: { template: "<div class='page-header-stub' />" },
}));

vi.mock("../../resources/js/components/ui/loading-state.vue", () => ({
  default: { template: "<div class='loading-state' />" },
}));

const requirement = {
  id: 7,
  code: "reglamento_interno",
  name: "Reglamento interno",
  description: "Documento institucional.",
  requires_delivery: true,
  requires_signature: true,
  validity_mode: "none",
  validity_months: null,
  alert_days: 30,
  is_required: true,
  active: true,
  sort_order: 10,
  controls_count: 1,
};

const matrix = {
  data: [{
    id: 3,
    full_name: "Ana Pérez",
    rut: "11.111.111-1",
    position: "Docente",
    document_summary: { total: 1, pending: 0, expired: 0, expiring: 0, current: 1 },
    documents: [{
      requirement,
      status: "current",
      compliance: {
        id: 12,
        delivered_at: "2026-08-20 00:00",
        signed_at: "2026-08-21 00:00",
        expires_at: null,
        has_file: false,
        notes: null,
      },
    }],
  }],
  summary: { staff: 1, requirements: 1, expected: 1, current: 1, expiring: 0, expired: 0, pending: 0 },
  current_page: 1,
  last_page: 1,
  total: 1,
  from: 1,
  to: 1,
};

const payslipHistory = {
  data: [{
    id: "01HLIQUIDACIONMENSUAL001",
    year: 2026,
    month: 8,
    period: "Agosto 2026",
    school: "Colegio de prueba",
    status: "importada",
    version: 1,
    net_amount: 1250000,
  }],
  current_page: 1,
  last_page: 1,
  total: 1,
};

const mountDocuments = () => mount(Documents, {
  global: { stubs: { teleport: true } },
});

describe("Documentos de Remuneraciones", () => {
  beforeEach(() => {
    axios.get.mockReset();
    axios.post.mockReset();
    axios.put.mockReset();
    axios.delete.mockReset();
    axios.get.mockImplementation((url) => {
      if (url.endsWith("/requirements")) {
        return Promise.resolve({ data: { data: [requirement], capabilities: { can_manage: true } } });
      }
      if (url.includes("/liquidaciones-sueldo/history")) {
        return Promise.resolve({ data: payslipHistory });
      }
      return Promise.resolve({ data: matrix });
    });
  });

  it("muestra una tabla compacta y abre el detalle documental por funcionario", async () => {
    const wrapper = mountDocuments();
    await flushPromises();

    expect(wrapper.text()).toContain("Listado de funcionarios");
    expect(wrapper.text()).toContain("Crear documentos");
    expect(wrapper.text()).toContain("Ana Pérez");
    expect(wrapper.find("table.staff-list-table").exists()).toBe(true);
    expect(wrapper.find("thead").text()).toContain("Cumplimiento");
    expect(wrapper.find("thead").text()).toContain("Estado general");
    expect(wrapper.find("thead").text()).not.toContain("Reglamento interno");
    expect(wrapper.find("tbody td.staff-list-person-cell").text()).toContain("Ana Pérez");
    expect(wrapper.findAll(".staff-list-detail-button")).toHaveLength(1);

    await wrapper.find(".staff-list-detail-button").trigger("click");
    await flushPromises();

    expect(wrapper.find("#staff-detail-modal-title").text()).toContain("Ana Pérez");
    expect(wrapper.findAll(".staff-detail-document")).toHaveLength(1);
    expect(wrapper.find(".staff-detail-document").text()).toContain("Reglamento interno");
    expect(wrapper.find(".staff-detail-document").text()).toContain("Entrega");
    expect(wrapper.find(".staff-detail-document").text()).toContain("Firma");
    expect(wrapper.find(".staff-detail-document").text()).toContain("20 ago 2026");
    expect(wrapper.find(".staff-detail-document").text()).toContain("21 ago 2026");
    expect(wrapper.text()).toContain("Sin vencimiento");
    expect(axios.get).toHaveBeenCalledWith("/api/remuneraciones/liquidaciones-sueldo/history", {
      params: { staff_id: 3, per_page: 36 },
    });
  });

  it("muestra las liquidaciones como historial mensual dentro del modal", async () => {
    const wrapper = mountDocuments();
    await flushPromises();

    await wrapper.find(".staff-list-detail-button").trigger("click");
    await flushPromises();
    await wrapper.findAll(".staff-detail-tabs button")[1].trigger("click");

    expect(wrapper.text()).toContain("Una liquidación por período mensual");
    expect(wrapper.findAll(".staff-payslip-row")).toHaveLength(1);
    expect(wrapper.find(".staff-payslip-row").text()).toContain("Agosto 2026");
    expect(wrapper.find(".staff-payslip-row").text()).toContain("1.250.000");
  });

  it("abre un formulario guiado para entrega, firma y vigencia", async () => {
    const wrapper = mountDocuments();
    await flushPromises();

    const tabs = wrapper.findAll(".rem-docs-tabs button");
    await tabs[1].trigger("click");
    await wrapper.find(".requirements-heading .primary-action").trigger("click");

    expect(wrapper.find("#requirement-modal-title").text()).toContain("Crear documento");
    expect(wrapper.text()).toContain("¿Qué debe hacer el funcionario?");
    expect(wrapper.text()).toContain("Sin vencimiento");
    expect(wrapper.text()).toContain("Por meses");
    expect(wrapper.text()).toContain("Fecha manual");
  });

  it("mantiene abierto el formulario al seleccionar vigencia por meses", async () => {
    const wrapper = mountDocuments();
    await flushPromises();

    await wrapper.findAll(".rem-docs-tabs button")[1].trigger("click");
    await wrapper.find(".requirements-heading .primary-action").trigger("click");
    await wrapper.find('input[type="radio"][value="months"]').setValue();

    expect(wrapper.vm.requirementModal).toBe(true);
    expect(wrapper.vm.requirementForm.validity_mode).toBe("months");
    expect(wrapper.find('input[type="number"][min="1"][max="1200"]').exists()).toBe(true);

    await wrapper.find('[aria-labelledby="requirement-modal-title"]').trigger("click");
    expect(wrapper.vm.requirementModal).toBe(true);
  });

  it("envía filtros al endpoint paginado y optimizado", async () => {
    const wrapper = mountDocuments();
    await flushPromises();
    await wrapper.setData({ filters: { search: "Ana", status: "expired" } });
    await wrapper.vm.loadStaff(2);

    expect(axios.get).toHaveBeenLastCalledWith("/api/remuneraciones/documents/staff", {
      params: { search: "Ana", status: "expired", page: 2, per_page: 12 },
    });
  });

  it("mantiene la tabla visible y estable mientras cambia el filtro de estado", async () => {
    const wrapper = mountDocuments();
    await flushPromises();

    let resolveRefresh;
    axios.get.mockImplementationOnce(() => new Promise((resolve) => {
      resolveRefresh = resolve;
    }));

    await wrapper.findAll('.staff-list-status-tabs button')[1].trigger("click");

    expect(wrapper.vm.isRefreshingStaff).toBe(true);
    expect(wrapper.find("table.staff-list-table").exists()).toBe(true);
    expect(wrapper.find(".staff-list-table-wrap").classes()).toContain("is-refreshing");
    expect(wrapper.find(".loading-state").exists()).toBe(false);

    resolveRefresh({ data: matrix });
    await flushPromises();

    expect(wrapper.vm.isRefreshingStaff).toBe(false);
    expect(wrapper.find("table.staff-list-table").exists()).toBe(true);
  });

  it("conserva estables los contadores al entrar en un estado sin resultados", async () => {
    const wrapper = mountDocuments();
    await flushPromises();

    axios.get.mockResolvedValueOnce({
      data: {
        ...matrix,
        data: [],
        summary: { staff: 0, requirements: 0, expected: 0, current: 0, expiring: 0, expired: 0, pending: 0 },
        total: 0,
        from: null,
        to: null,
      },
    });

    await wrapper.findAll('.staff-list-status-tabs button')[2].trigger("click");
    await flushPromises();

    const tabs = wrapper.findAll('.staff-list-status-tabs button');
    expect(tabs[0].text()).toContain("1");
    expect(tabs[4].text()).toContain("1");
    expect(wrapper.vm.filters.status).toBe("expiring");
    expect(wrapper.find("table.staff-list-table").exists()).toBe(false);
  });
});
