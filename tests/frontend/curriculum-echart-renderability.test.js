import { describe, expect, it } from "vitest";
import { chartRenderState } from "../../resources/js/components/libro-digital/curriculum-visualizations/useRenderableEChart";

const element = ({ width, height, canvases = [] }) => ({
    clientWidth: width,
    clientHeight: height,
    getBoundingClientRect: () => ({ width, height }),
    querySelectorAll: () => canvases,
});

describe("renderizado seguro de ECharts", () => {
    it("rechaza un host o canvas sin dimensiones", () => {
        expect(
            chartRenderState(element({ width: 0, height: 0 }), null)
        ).toMatchObject({
            ready: false,
        });
        expect(
            chartRenderState(
                element({
                    width: 900,
                    height: 560,
                    canvases: [{ width: 0, height: 0 }],
                }),
                null
            )
        ).toMatchObject({ ready: false });
    });

    it("acepta el gráfico solo cuando host, instancia y canvas son visibles", () => {
        const component = {
            chart: {
                value: {
                    getWidth: () => 900,
                    getHeight: () => 560,
                },
            },
        };
        expect(
            chartRenderState(
                element({
                    width: 900,
                    height: 560,
                    canvases: [{ width: 1800, height: 1120 }],
                }),
                component
            )
        ).toMatchObject({ width: 900, height: 560, ready: true });
    });
});
