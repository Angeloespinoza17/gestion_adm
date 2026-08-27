import { beforeEach, describe, expect, it, vi } from "vitest";

const pdfState = vi.hoisted(() => ({ definition: null, filename: "" }));

vi.mock("../../resources/js/utils/pdfmake", () => ({
    getPdfMake: async () => ({
        createPdf: (definition) => {
            pdfState.definition = definition;
            return {
                download: (filename) => {
                    pdfState.filename = filename;
                },
            };
        },
    }),
}));

import {
    buildPedagogicalFeedbackPdfDefinition,
    downloadPedagogicalFeedbackPdf,
    pedagogicalFeedbackFilename,
} from "../../resources/js/utils/pedagogical-feedback-pdf";

const instrument = {
    title: "Control de probabilidad",
    status: "review_required",
    school: { name: "Colegio de prueba" },
    owner: { name: "Samuel Vásquez" },
    subject: { name: "Matemática" },
    courses: [{ name: "III° Medio A" }],
    latest_file: { original_filename: "control-probabilidad.pdf" },
    latest_analysis: {
        finished_at: "2026-08-25T15:30:00-04:00",
        extractor_version: "gpt-5.4-mini-2026-03-17",
        review_summary:
            "El instrumento requiere corregir una pregunta con dos respuestas válidas.",
        disclaimer: "La revisión requiere criterio profesional humano.",
        results: [
            {
                id: "e1",
                category: "error",
                title: "Dos alternativas correctas",
                message: "5/14 y 20/56 son equivalentes.",
                page_number: 2,
                source_excerpt: "B) 5/14 C) 20/56",
            },
            {
                id: "s1",
                category: "suggestion",
                title: "Ajustar distractor",
                message: "Reemplazar una alternativa.",
                page_number: 2,
            },
        ],
    },
};

describe("PDF de retroalimentación pedagógica", () => {
    beforeEach(() => {
        pdfState.definition = null;
        pdfState.filename = "";
    });

    it("construye un informe A4 con contexto, resumen, errores y sugerencias", () => {
        const definition = buildPedagogicalFeedbackPdfDefinition(
            instrument,
            new Date("2026-08-25T19:30:00Z")
        );
        const serialized = JSON.stringify({
            content: definition.content,
            footer: definition.footer(1, 1),
        });

        expect(definition.pageSize).toBe("A4");
        expect(serialized).toContain("Retroalimentación del instrumento");
        expect(serialized).toContain("Samuel Vásquez");
        expect(serialized).toContain("Dos alternativas correctas");
        expect(serialized).toContain("Ajustar distractor");
        expect(serialized).toContain("criterio profesional humano");
        expect(serialized).toContain("INFORME DE REVISIÓN");
        expect(serialized).not.toContain("GENERADO CON IA");
        expect(serialized).not.toContain("RETROALIMENTACIÓN IA");
        expect(serialized).not.toContain("gpt-5.4-mini");
    });

    it("descarga un nombre estable y legible", async () => {
        const generatedAt = new Date("2026-08-25T19:30:00Z");
        expect(pedagogicalFeedbackFilename(instrument, generatedAt)).toBe(
            "retroalimentacion-iii-medio-a-2026-08-25.pdf"
        );

        await downloadPedagogicalFeedbackPdf(instrument, generatedAt);
        expect(pdfState.definition.pageSize).toBe("A4");
        expect(pdfState.filename).toBe(
            "retroalimentacion-iii-medio-a-2026-08-25.pdf"
        );
    });

    it("explica cuando no existen hallazgos", () => {
        const clean = structuredClone(instrument);
        clean.latest_analysis.results = [];
        const serialized = JSON.stringify(
            buildPedagogicalFeedbackPdfDefinition(clean).content
        );
        expect(serialized).toContain("No se reportaron errores concretos");
        expect(serialized).toContain("No se agregaron sugerencias");
    });
});
