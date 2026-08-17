import { describe, expect, it } from "vitest";
import {
    CURRICULUM_VIEW_KEYS,
    GRAPH_VIEW_KEYS,
} from "../../resources/js/utils/curriculum-visualization";
import {
    visualizationOptions,
    visualizationRegistry,
} from "../../resources/js/components/libro-digital/curriculum-visualizations/visualizationRegistry";

describe("registro modular de visualizaciones", () => {
    it("registra tabla y las ocho vistas gráficas en el mismo orden público", () => {
        expect(Object.keys(visualizationRegistry)).toEqual(
            CURRICULUM_VIEW_KEYS
        );
        expect(visualizationOptions.map(({ value }) => value)).toEqual(
            CURRICULUM_VIEW_KEYS
        );
        expect(GRAPH_VIEW_KEYS).toHaveLength(8);
        expect(GRAPH_VIEW_KEYS).not.toContain("table");
    });

    it("mantiene la tabla sin componente gráfico y carga cada gráfico asincrónicamente", () => {
        expect(visualizationRegistry.table.component).toBeNull();
        GRAPH_VIEW_KEYS.forEach((key) => {
            const definition = visualizationRegistry[key];
            expect(definition.label).toBeTruthy();
            expect(definition.description).toBeTruthy();
            expect(definition.component).toBeTruthy();
            expect(definition.component).toHaveProperty("__asyncLoader");
        });
    });
});
