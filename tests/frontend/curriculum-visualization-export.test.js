// @vitest-environment jsdom

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const pdfHarness = vi.hoisted(() => ({
    definition: null,
    download: vi.fn(),
}));

vi.mock("../../resources/js/utils/pdfmake", () => ({
    getPdfMake: () => ({
        createPdf: (definition) => {
            pdfHarness.definition = definition;
            return {
                download: (filename, complete) => {
                    pdfHarness.download(filename);
                    complete?.();
                },
            };
        },
    }),
}));

import {
    buildCurriculumVisualizationPdfDefinition,
    calculateContainRect,
    exportCurriculumVisualization,
    exportCurriculumVisualizationPdf,
    safeFilenamePart,
    serializeSvg,
    svgStringToDataUrl,
} from "../../resources/js/utils/curriculum-visualization-export";

describe("exportación curricular", () => {
    let context;
    let click;

    beforeEach(() => {
        pdfHarness.definition = null;
        pdfHarness.download.mockReset();
        context = {
            fillStyle: "",
            font: "",
            textAlign: "left",
            fillRect: vi.fn(),
            fillText: vi.fn(),
            drawImage: vi.fn(),
            beginPath: vi.fn(),
            arc: vi.fn(),
            fill: vi.fn(),
            measureText: vi.fn((value) => ({
                width: String(value).length * 8,
            })),
        };
        vi.spyOn(HTMLCanvasElement.prototype, "getContext").mockReturnValue(
            context
        );
        vi.spyOn(HTMLCanvasElement.prototype, "toDataURL").mockReturnValue(
            "data:image/png;base64,exported"
        );
        click = vi
            .spyOn(HTMLAnchorElement.prototype, "click")
            .mockImplementation(() => {});

        class LoadedImage {
            naturalWidth = 1200;
            naturalHeight = 680;
            onload = null;
            onerror = null;

            set src(value) {
                this.currentSrc = value;
                queueMicrotask(() => this.onload?.());
            }
        }
        vi.stubGlobal("Image", LoadedImage);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it("compone PNG con título, total, filtros, jerarquía y advertencia semántica", async () => {
        const result = await exportCurriculumVisualization({
            capture: () => "data:image/png;base64,chart",
            title: "Treemap · mapa curricular",
            total: 64,
            filters: ["Asignatura: FIL", "Grado: 4M"],
            hierarchy: "Asignatura → Eje/Núcleo → Grado → Objetivo",
            legend: [{ label: "Filosofía", count: "64", color: "#245486" }],
            filename: "filosofia.png",
        });

        expect(result).toBe("data:image/png;base64,exported");
        expect(context.drawImage).toHaveBeenCalledOnce();
        const written = context.fillText.mock.calls.map(([value]) => value);
        expect(written).toContain("Treemap · mapa curricular");
        expect(written).toContain("64 objetivos");
        expect(written).toContain("Filtros: Asignatura: FIL · Grado: 4M");
        expect(written).toContain(
            "Jerarquía: Asignatura → Eje/Núcleo → Grado → Objetivo"
        );
        expect(written).toContain("Filosofía · 64");
        expect(written).toContain(
            "El tamaño representa cantidad de objetivos, no importancia curricular."
        );
        expect(click).toHaveBeenCalledOnce();
    });

    it("ajusta la captura completa sin deformar su proporción", () => {
        expect(calculateContainRect(1600, 800, 0, 0, 1000, 1000)).toEqual({
            x: 0,
            y: 250,
            width: 1000,
            height: 500,
        });
        expect(calculateContainRect(400, 800, 10, 20, 1000, 600)).toEqual({
            x: 360,
            y: 20,
            width: 300,
            height: 600,
        });
    });

    it("genera un PDF A3 horizontal con la visualización completa", async () => {
        const result = await exportCurriculumVisualizationPdf({
            capture: () => "data:image/png;base64,chart",
            title: "Sankey · mapa curricular",
            total: 3982,
            filters: ["Nivel: MEDIA"],
            hierarchy: "Nivel → Grado → Asignatura",
            legend: [{ label: "3M", count: "1.727", color: "#79589f" }],
            filename: "mapa-media.pdf",
        });

        expect(result).toBe("data:image/png;base64,exported");
        expect(pdfHarness.download).toHaveBeenCalledWith("mapa-media.pdf");
        expect(pdfHarness.definition).toMatchObject({
            pageSize: "A3",
            pageOrientation: "landscape",
            info: { title: "Sankey · mapa curricular" },
        });
        expect(pdfHarness.definition.content[0]).toMatchObject({
            image: "data:image/png;base64,exported",
            fit: [1140, 790],
        });
    });

    it("construye una definición PDF con metadatos institucionales", () => {
        expect(
            buildCurriculumVisualizationPdfDefinition({
                image: "data:image/png;base64,chart",
                title: "Mapa curricular",
                total: 64,
            })
        ).toMatchObject({
            pageSize: "A3",
            pageOrientation: "landscape",
            info: {
                title: "Mapa curricular",
                subject: "64 objetivos curriculares",
                creator: "Libro Digital",
            },
        });
    });

    it("serializa SVG y produce nombres de archivo seguros", () => {
        const svg = document.createElementNS(
            "http://www.w3.org/2000/svg",
            "svg"
        );
        svg.setAttribute("viewBox", "0 0 100 100");
        const serialized = serializeSvg(svg);

        expect(serialized).toContain('xmlns="http://www.w3.org/2000/svg"');
        expect(svgStringToDataUrl(serialized)).toMatch(
            /^data:image\/svg\+xml;charset=utf-8,/
        );
        expect(safeFilenamePart("Filosofía / 4° Medio 2026")).toBe(
            "filosofia-4-medio-2026"
        );
        expect(safeFilenamePart("")).toBe("catalogo");
    });

    it("rechaza capturas vacías con un mensaje accionable", async () => {
        await expect(
            exportCurriculumVisualization({ capture: () => null })
        ).rejects.toThrow("no entregó una imagen exportable");
        expect(click).not.toHaveBeenCalled();
    });

    it("rechaza una imagen de gráfico con dimensiones cero antes de dibujar", async () => {
        class EmptyImage {
            naturalWidth = 0;
            naturalHeight = 0;
            onload = null;

            set src(value) {
                this.currentSrc = value;
                queueMicrotask(() => this.onload?.());
            }
        }
        vi.stubGlobal("Image", EmptyImage);

        await expect(
            exportCurriculumVisualizationPdf({
                capture: () => "data:image/png;base64,empty",
                title: "Mapa curricular",
                total: 64,
            })
        ).rejects.toThrow("no tiene dimensiones válidas");
        expect(context.drawImage).not.toHaveBeenCalled();
        expect(pdfHarness.download).not.toHaveBeenCalled();
    });
});
