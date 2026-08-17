// @vitest-environment jsdom

import { mount } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";
import CurriculumCirclePacking from "../../resources/js/components/libro-digital/curriculum-visualizations/CurriculumCirclePacking.vue";
import CurriculumIcicle from "../../resources/js/components/libro-digital/curriculum-visualizations/CurriculumIcicle.vue";
import CurriculumVisualizationHelp from "../../resources/js/components/libro-digital/curriculum-visualizations/CurriculumVisualizationHelp.vue";

const objective = {
    id: "objective:one",
    entityId: "01HOBJECTIVEPUBLICID000001",
    type: "objective",
    name: "FIL-4M-OA-01",
    code: "FIL-4M-OA-01",
    objectiveCount: 1,
    value: 1,
    availableCount: 1,
    unavailableCount: 0,
    percentageOfParent: 100,
    metadata: {},
    path: [],
    children: [],
};

const root = {
    id: "catalog:root",
    type: "catalog",
    name: "Resultados filtrados",
    objectiveCount: 1,
    value: 1,
    availableCount: 1,
    unavailableCount: 0,
    percentageOfParent: 100,
    metadata: {},
    path: [],
    children: [objective],
};

describe("vistas SVG y ciclo de vida", () => {
    let resizeObserver;

    beforeEach(() => {
        globalThis.ResizeObserver = class ResizeObserverMock {
            constructor() {
                this.observe = vi.fn();
                this.disconnect = vi.fn();
                resizeObserver = this;
            }
        };
        vi.spyOn(Element.prototype, "getBoundingClientRect").mockReturnValue({
            width: 900,
            height: 560,
            top: 0,
            left: 0,
            right: 900,
            bottom: 560,
            x: 0,
            y: 0,
            toJSON: () => ({}),
        });
    });

    it("observa el contenedor de Círculos, permite teclado y desconecta al desmontar", async () => {
        const wrapper = mount(CurriculumCirclePacking, {
            props: { root },
        });

        expect(resizeObserver.observe).toHaveBeenCalledOnce();
        const nodes = wrapper.findAll(".cv-pack__node");
        const leaf = nodes.find((node) =>
            node.attributes("aria-label").includes("FIL-4M-OA-01")
        );
        await leaf.trigger("keydown", { key: "Enter" });

        expect(wrapper.emitted("select-node")?.at(-1)?.[0]).toMatchObject({
            id: "objective:one",
        });
        expect(wrapper.emitted("open-objective")?.at(-1)?.[0]).toMatchObject({
            entityId: "01HOBJECTIVEPUBLICID000001",
        });

        wrapper.unmount();
        expect(resizeObserver.disconnect).toHaveBeenCalledOnce();
    });

    it("cambia la orientación de Icicle y activa OA con la barra espaciadora", async () => {
        const wrapper = mount(CurriculumIcicle, {
            props: { root },
        });
        const vertical = wrapper
            .findAll(".cv-icicle__toggle button")
            .find((button) => button.text() === "Vertical");

        await vertical.trigger("click");
        expect(vertical.attributes("aria-pressed")).toBe("true");

        const leaf = wrapper
            .findAll(".cv-icicle__node")
            .find((node) =>
                node.attributes("aria-label").includes("FIL-4M-OA-01")
            );
        await leaf.trigger("keydown", { key: " " });
        expect(wrapper.emitted("open-objective")?.at(-1)?.[0]).toMatchObject({
            id: "objective:one",
        });

        wrapper.unmount();
        expect(resizeObserver.disconnect).toHaveBeenCalledOnce();
    });
});

describe("ayuda accesible", () => {
    it("expone semántica modal y cierra con Escape", async () => {
        const wrapper = mount(CurriculumVisualizationHelp, {
            props: { modelValue: true },
        });
        const dialog = wrapper.get("[role='dialog']");

        expect(dialog.attributes("aria-modal")).toBe("true");
        expect(dialog.attributes("aria-labelledby")).toBe("cv-help-title");
        expect(wrapper.text()).toContain(
            "no implica mayor importancia curricular"
        );

        await dialog.trigger("keydown", { key: "Escape" });
        expect(wrapper.emitted("update:model-value")).toContainEqual([false]);
    });

    it("enfoca el modal, contiene Tab y devuelve el foco al cerrar", async () => {
        const trigger = document.createElement("button");
        trigger.textContent = "Abrir ayuda";
        document.body.appendChild(trigger);
        trigger.focus();
        const wrapper = mount(CurriculumVisualizationHelp, {
            attachTo: document.body,
            props: { modelValue: false },
        });

        await wrapper.setProps({ modelValue: true });
        await Promise.resolve();
        const first = wrapper.get("[aria-label='Cerrar ayuda']").element;
        const last = wrapper.get(".cv-help__close").element;
        expect(document.activeElement).toBe(first);

        last.focus();
        await wrapper.get("[role='dialog']").trigger("keydown", { key: "Tab" });
        expect(document.activeElement).toBe(first);

        await wrapper.setProps({ modelValue: false });
        expect(document.activeElement).toBe(trigger);
        wrapper.unmount();
        trigger.remove();
    });

    it("no deja el diálogo en el DOM cuando está cerrado", () => {
        const wrapper = mount(CurriculumVisualizationHelp, {
            props: { modelValue: false },
        });
        expect(wrapper.find("[role='dialog']").exists()).toBe(false);
    });
});
