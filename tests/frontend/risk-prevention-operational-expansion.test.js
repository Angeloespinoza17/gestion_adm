// @vitest-environment jsdom

import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { flushPromises, mount } from "@vue/test-utils";
import axios from "axios";
import { beforeEach, describe, expect, it, vi } from "vitest";
import JointCommittee from "../../resources/js/views/risk-prevention/joint-committee.vue";

vi.mock("axios", () => ({
  default: { get: vi.fn(), post: vi.fn() },
}));

vi.mock("sweetalert2", () => ({
  default: { fire: vi.fn(() => Promise.resolve({ isConfirmed: true })) },
}));

vi.mock("../../resources/js/layouts/main.vue", () => ({
  default: { template: "<div><slot /></div>" },
}));

vi.mock("../../resources/js/components/ui/loading-state.vue", () => ({
  default: { template: "<div class='loading-state' />" },
}));

vi.mock("../../resources/js/components/risk-prevention/help-button.vue", () => ({
  default: { template: "<button class='help-stub'>Ayuda</button>" },
}));

const committee = {
  id: 8,
  name: "Comité Paritario 2026-2028",
  starts_on: "2026-01-01",
  ends_on: "2028-12-31",
  active: true,
  staff_members: [{
    id: 10,
    full_name: "Carolina Seguridad",
    cargo: { name: "Secretaria" },
    pivot: { active: true, position_name: "Presidenta", representation: "trabajadores" },
  }],
  documents: [{
    id: 15,
    document_type: "constitucion",
    document_date: "2026-01-08",
    title: "Acta de constitución",
    original_name: "constitucion.pdf",
  }, {
    id: 16,
    document_type: "acta_mensual",
    period_key: "2026-08",
    document_date: "2026-08-18",
    title: "Acta mensual de agosto",
    original_name: "agosto.pdf",
  }],
  trainings: [{
    id: 21,
    name: "Investigación de accidentes",
    training_date: "2026-08-20",
    modality: "Presencial",
    training_type: "obligatoria",
    participants: [{ compliance_status: "cumplido" }],
  }],
  summary: {
    active_members: 1,
    constitution_uploaded: true,
    monthly_minutes_current_year: 7,
    expected_months_current_year: 8,
    committee_trainings: 1,
  },
};

const stubs = {
  Layout: { template: "<div><slot /></div>" },
  LoadingState: { template: "<div class='loading-state' />" },
  HelpButton: { template: "<button class='help-stub'>Ayuda</button>" },
  BButton: {
    props: ["disabled"],
    emits: ["click"],
    template: "<button :disabled='disabled' @click='$emit(`click`)'><slot /></button>",
  },
  BFormSelect: { template: "<select />" },
  BFormInput: { template: "<input />" },
  BFormFile: { template: "<input type='file' />" },
  BFormTextarea: { template: "<textarea />" },
  BBadge: { template: "<span class='badge'><slot /></span>" },
  BSpinner: { template: "<span />" },
  BModal: {
    props: ["modelValue"],
    template: "<div v-if='modelValue' class='modal-stub'><slot /></div>",
  },
  RouterLink: {
    props: ["to"],
    template: "<a :data-to='JSON.stringify(to)'><slot /></a>",
  },
};

describe("expansión operativa de Prevención de Riesgos", () => {
  beforeEach(() => {
    axios.get.mockReset();
    axios.post.mockReset();
    axios.get.mockResolvedValue({
      data: {
        data: [committee],
        permissions: { can_view: true, can_upload_minutes: true, can_manage_committee: true },
      },
    });
  });

  it("muestra actas, integrantes y las capacitaciones vinculadas al Comité", async () => {
    const wrapper = mount(JointCommittee, { global: { stubs, config: { warnHandler: () => {} } } });
    await flushPromises();

    expect(axios.get).toHaveBeenCalledWith("/api/risk-prevention/joint-committees");
    expect(wrapper.text()).toContain("Comité Paritario 2026-2028");
    expect(wrapper.text()).toContain("1/8");
    expect(wrapper.text()).toContain("Acta de constitución");

    await wrapper.findAll(".committee-tabs button")[1].trigger("click");
    expect(wrapper.text()).toContain("Carolina Seguridad");
    expect(wrapper.text()).toContain("Presidenta");

    await wrapper.findAll(".committee-tabs button")[2].trigger("click");
    expect(wrapper.text()).toContain("Investigación de accidentes");
    expect(wrapper.text()).toContain("1 de 1 participantes cumplidos");
  });

  it("mantiene visibles los campos y rutas exactos de Accidentes y Bodega E.P.P.", () => {
    const base = resolve(process.cwd(), "resources/js");
    const accidents = readFileSync(`${base}/views/risk-prevention/accidents.vue`, "utf8");
    const epp = readFileSync(`${base}/views/risk-prevention/epp.vue`, "utf8");
    const router = readFileSync(`${base}/router/index.js`, "utf8");

    expect(accidents).toContain('v-model.number="form.lost_days"');
    expect(accidents).toContain('v-model="form.injured_body_part"');
    expect(accidents).toContain("enfermedad_profesional");
    expect(accidents).toContain("annual_lost_days");
    expect(epp).toContain('v-model="itemForm.inventory_item_id"');
    expect(epp).toContain("available_stock");
    expect(epp).toContain("Entrega diaria");
    expect(router).toContain('path: "/inventory/epp-deliveries"');
    expect(router).toContain('permissionsAny: ["ver_entregas_epp", "registrar_entregas_epp"]');
  });
});
