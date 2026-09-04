// @vitest-environment jsdom

import { mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";
import CanvaConnectionCard from "../../resources/js/components/pedagogical-management/CanvaConnectionCard.vue";
import CanvaTemplatePicker from "../../resources/js/components/pedagogical-management/CanvaTemplatePicker.vue";
import ClassPresentationPipeline from "../../resources/js/components/pedagogical-management/ClassPresentationPipeline.vue";
import ClassPresentationDeliverables from "../../resources/js/components/pedagogical-management/ClassPresentationDeliverables.vue";
import ClassPresentationsView from "../../resources/js/views/pedagogical-management/class-presentations.vue";

vi.mock("../../resources/js/layouts/main.vue", () => ({
    default: { template: "<main><slot /></main>" },
}));

describe("Generador de clases · componentes Canva", () => {
    it("compila la vista integrada del generador", () => {
        expect(ClassPresentationsView).toBeTruthy();
    });

    it("explica de forma bloqueante cuando Canva no está configurado", () => {
        const wrapper = mount(CanvaConnectionCard, { props: { connection: { configured: false, connected: false } } });
        expect(wrapper.text()).toContain("Canva aún no está disponible");
        expect(wrapper.text()).toContain("administrador");
        expect(wrapper.find("button.primary-action").exists()).toBe(false);
    });

    it("muestra cuenta y capacidades sin secretos cuando la conexión está operativa", () => {
        const wrapper = mount(CanvaConnectionCard, {
            props: { connection: { configured: true, connected: true, display_name: "Docente Canva", team_name: "Colegio", enterprise_autofill: true, autofill_available: true, mode: "enterprise", capabilities: { autofill: true } } },
        });
        expect(wrapper.text()).toContain("Docente Canva");
        expect(wrapper.text()).toContain("Colegio");
        expect(wrapper.text()).toContain("Operativa · Enterprise Autofill");
        expect(wrapper.text()).toContain("Brand Templates + Autofill");
        expect(wrapper.text()).not.toContain("secret");
    });

    it("distingue acceso de desarrollo y cuenta conectada sin Autofill", async () => {
        const wrapper = mount(CanvaConnectionCard, {
            props: { connection: { configured: true, connected: true, display_name: "Docente Canva", enterprise_autofill: false, trial_enabled: true, autofill_available: true, mode: "development_trial", capabilities: { autofill: false } } },
        });
        expect(wrapper.text()).toContain("Conectada · Acceso de desarrollo/trial");
        expect(wrapper.text()).toContain("Autofill está habilitado mediante acceso de desarrollo/trial");

        await wrapper.setProps({ connection: { configured: true, connected: true, display_name: "Docente Canva", enterprise_autofill: false, trial_enabled: false, autofill_available: false, mode: "unavailable", capabilities: {} } });
        expect(wrapper.text()).toContain("Cuenta conectada · Autofill no disponible");
        expect(wrapper.text()).toContain("Plan sin Autofill");
        expect(wrapper.text()).not.toContain("Conecta tu cuenta de Canva");
        expect(wrapper.text()).not.toContain("Pendiente de conexión");
        expect(wrapper.find("button.secondary-action").text()).toContain("Verificar conexión");
        expect(wrapper.find("button.quiet-action").text()).toContain("Cambiar cuenta");
    });

    it("explica con precisión cuando no existen Brand Templates Autofill", () => {
        const wrapper = mount(CanvaTemplatePicker, { props: { templates: [], loading: false } });
        expect(wrapper.text()).toContain("No hay Brand Templates con campos Autofill");
        expect(wrapper.text()).not.toContain("cambia el estilo, formato o cantidad de diapositivas");
    });

    it("permite seleccionar sólo plantillas compatibles y mantiene la razón de bloqueo", async () => {
        const templates = [
            { id: "ok", title: "Infantil 12 láminas", page_count: 12, field_count: 28, compatible: true },
            { id: "bad", title: "Corporativa 8 láminas", page_count: 8, compatible: false, compatibility_messages: ["Faltan cuatro páginas."] },
        ];
        const wrapper = mount(CanvaTemplatePicker, { props: { templates, modelValue: "", requiredSlideCount: 12, requiredAspectRatio: "16:9" } });
        const cards = wrapper.findAll("button.template-card");
        expect(cards).toHaveLength(2);
        expect(cards[1].attributes()).toHaveProperty("disabled");
        expect(wrapper.text()).toContain("Faltan cuatro páginas.");
        await cards[0].trigger("click");
        expect(wrapper.emitted("update:modelValue")?.[0]).toEqual(["ok"]);
        expect(wrapper.emitted("select")?.[0]?.[0]).toEqual(templates[0]);
    });

    it("separa la guía docente de la presentación y conserva el modo heredado", async () => {
        const presentation = {
            id: "p-1", title: "Clase", status: "ready", provider: "canva",
            canva: { design_id: "design-1", brand_template_title: "Infantil", completed_at: "2026-08-30T10:00:00-04:00" },
            can: { edit_canva: true, sync_canva: true },
            files: [
                { id: "ppt", type: "pptx", filename: "clase.pptx", size: 1200 },
                { id: "guide", type: "teacher_guide_pdf", filename: "guia.pdf", size: 900 },
            ],
        };
        const wrapper = mount(ClassPresentationDeliverables, { props: { presentation } });
        expect(wrapper.text()).toContain("Respaldo técnico · PowerPoint");
        expect(wrapper.text()).toContain("no son exportaciones del diseño Canva");
        expect(wrapper.text()).toContain("Guía docente PDF");
        await wrapper.find("button.edit-button").trigger("click");
        expect(wrapper.emitted("edit-canva")).toHaveLength(1);

        await wrapper.setProps({ presentation: { ...presentation, provider: "powerpoint", canva: null, can: {}, files: presentation.files.slice(0, 1) } });
        expect(wrapper.text()).toContain("Presentación heredada");
        expect(wrapper.text()).toContain("PowerPoint editable");
    });

    it("representa las cuatro etapas y la fase activa de Canva", () => {
        const wrapper = mount(ClassPresentationPipeline, {
            props: {
                presentation: {
                    status: "ready",
                    progress: 100,
                    canva: { status: "in_progress" },
                    files: [{ type: "teacher_guide_pdf" }, { type: "pptx" }],
                },
            },
        });
        expect(wrapper.findAll(".pipeline-track article")).toHaveLength(4);
        expect(wrapper.text()).toContain("Contenido pedagógico");
        expect(wrapper.text()).toContain("Guía docente PDF");
        expect(wrapper.text()).toContain("Respaldo y control");
        expect(wrapper.text()).toContain("Diseño en Canva");
        expect(wrapper.findAll(".is-active")).toHaveLength(1);
        expect(wrapper.find(".pipeline-heading > strong").text()).toBe("85%");
    });
});
