// @vitest-environment jsdom

import { flushPromises, mount } from "@vue/test-utils";
import axios from "axios";
import { beforeEach, describe, expect, it, vi } from "vitest";
import OperationalStaffLogbook from "../../resources/js/views/operational/logbook/index.vue";

vi.mock("axios", () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
    },
}));

vi.mock("../../resources/js/layouts/main.vue", () => ({
    default: { template: "<div><slot /></div>" },
}));

vi.mock("../../resources/js/components/ui/loading-state.vue", () => ({
    default: { template: "<div class='loading-state'>Cargando</div>" },
}));

vi.mock("../../resources/js/components/operational/operational-workspace-header.vue", () => ({
    default: {
        props: ["title", "subtitle", "sectionLabel", "showTools"],
        template: "<header><span>{{ sectionLabel }}</span><h1>{{ title }}</h1><p>{{ subtitle }}</p><slot name='actions' /></header>",
    },
}));

const categories = [
    { value: "general", label: "Observación general", icon: "bx-note" },
    { value: "meeting", label: "Reunión o acuerdo", icon: "bx-group" },
    { value: "other", label: "Otra categoría", icon: "bx-dots-horizontal-rounded" },
];

const entry = {
    id: 7,
    occurred_at: "2026-09-05 09:15:00",
    category: "meeting",
    category_label: "Reunión o acuerdo",
    custom_category: null,
    title: "Acuerdo de coordinación semanal",
    details: "Se definieron responsables y la fecha de revisión.",
    was_edited: false,
    can_edit: true,
    owner: { id: 12, name: "Camila Funcionaria", position: "Docente" },
};

const listResponse = (superadmin = false) => ({
    data: [entry],
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 1,
    from: 1,
    to: 1,
    summary: { total: 1, today: 1, staff: 1 },
    categories,
    scope: {
        mode: superadmin ? "all" : "own",
        is_superadmin: superadmin,
        can_create: true,
        privacy_note: superadmin
            ? "Consulta institucional de todas las bitácoras de funcionarios."
            : "Tu bitácora se mantiene en un espacio interno y protegido.",
    },
    staff: superadmin ? [{ id: 12, name: "Camila Funcionaria" }] : [],
});

const mountView = () => mount(OperationalStaffLogbook, {
    global: {
        stubs: {
            BPagination: { template: "<nav class='pagination-stub' />" },
            Teleport: true,
        },
    },
});

describe("Bitácora operativa de funcionarios", () => {
    beforeEach(() => {
        axios.get.mockReset();
        axios.post.mockReset();
        axios.put.mockReset();
        axios.get.mockResolvedValue({ data: listResponse(false) });
    });

    it("renders the private personal timeline with categories and free text", async () => {
        const wrapper = mountView();
        await flushPromises();

        expect(wrapper.text()).toContain("Mi bitácora");
        expect(wrapper.text()).toContain("Bitácora institucional");
        expect(wrapper.text()).not.toContain("Gestión Operativa");
        expect(wrapper.text()).toContain("Espacio personal protegido");
        expect(wrapper.text()).toContain("espacio interno y protegido");
        expect(wrapper.text()).not.toContain("Superadmin");
        expect(wrapper.text()).toContain("Acuerdo de coordinación semanal");
        expect(wrapper.text()).toContain("Camila Funcionaria");
        expect(wrapper.find("#logbook-owner").exists()).toBe(false);
        expect(axios.get).toHaveBeenCalledWith("/api/operational/logbook", {
            params: expect.objectContaining({ page: 1, per_page: 20 }),
        });
    });

    it("shows the consolidated staff selector only in the superadmin view", async () => {
        axios.get.mockResolvedValue({ data: listResponse(true) });
        const wrapper = mountView();
        await flushPromises();

        expect(wrapper.text()).toContain("Bitácoras de funcionarios");
        expect(wrapper.text()).toContain("Vista institucional protegida");
        expect(wrapper.text()).toContain("Todos los funcionarios");
        expect(wrapper.find("#logbook-owner").exists()).toBe(true);
        expect(wrapper.find("#logbook-owner").text()).toContain("Camila Funcionaria");
    });

    it("creates an entry using clickable categories, suggestions and guided detail blocks", async () => {
        axios.post.mockResolvedValue({
            data: { message: "Registro agregado a tu bitácora.", data: entry },
        });
        const wrapper = mountView();
        await flushPromises();

        await wrapper.find("header button").trigger("click");
        expect(wrapper.find(".logbook-form-privacy").text()).toContain("seguimiento institucional");
        expect(wrapper.find(".logbook-form-privacy").text()).not.toContain("Superadmin");
        const categoryButtons = wrapper.findAll(".category-picker__item");
        await categoryButtons[1].trigger("click");
        await wrapper.findAll(".title-suggestions .form-option-chip")[0].trigger("click");
        const detailOptions = wrapper.findAll(".detail-prompt-picker .form-option-chip");
        await detailOptions[0].trigger("click");
        await wrapper.find("#logbook-details").setValue("Contexto: Reunión semanal");
        await detailOptions[1].trigger("click");
        await wrapper.find("#logbook-details").setValue("Contexto: Reunión semanal\n\nAcuerdo: Revisar el avance la próxima semana.");
        await wrapper.find(".logbook-modal form").trigger("submit");
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith("/api/operational/logbook", expect.objectContaining({
            category: "meeting",
            title: "Acuerdo de reunión",
            details: "Contexto: Reunión semanal\n\nAcuerdo: Revisar el avance la próxima semana.",
            custom_category: null,
        }));
        expect(wrapper.text()).toContain("Registro agregado a tu bitácora.");
        expect(wrapper.find(".logbook-modal").exists()).toBe(false);
    });

    it("updates only entries marked editable by the protected API", async () => {
        axios.put.mockResolvedValue({
            data: { message: "Registro de bitácora actualizado.", data: { ...entry, title: "Título corregido" } },
        });
        const wrapper = mountView();
        await flushPromises();

        await wrapper.find(".logbook-entry__footer button").trigger("click");
        await wrapper.find("#logbook-title").setValue("Título corregido");
        await wrapper.find(".logbook-modal form").trigger("submit");
        await flushPromises();

        expect(axios.put).toHaveBeenCalledWith("/api/operational/logbook/7", expect.objectContaining({
            category: "meeting",
            title: "Título corregido",
        }));
    });
});
