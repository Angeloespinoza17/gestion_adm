import { describe, expect, it } from "vitest";
import {
    buildPedagogicalAiReportPdfDefinition,
    pedagogicalAiReportFilename,
} from "../../resources/js/utils/pedagogical-ai-report-pdf";
import { aiReportStatistics } from "../../resources/js/utils/pedagogical-ai-report";

const report = {
    executive_summary: "Retroalimentación completa sin resumir.",
    criteria_assessment: [
        {
            code: "2.1",
            dimension: "Alineación curricular",
            applicability: "Todos los instrumentos",
            criterion: "Declara OA pertinente.",
            status: "meets",
            finding: "El OA está declarado.",
            evidence: "OA 4",
            recommendation: "Mantener la referencia.",
            improvement_example: "Añadir junto al OA el indicador evaluado.",
            page: 1,
        },
        {
            code: "3.1",
            dimension: "Calidad del instrumento",
            applicability: "Todos los instrumentos",
            criterion: "Las instrucciones son claras.",
            status: "partially_meets",
            finding: "Una instrucción es ambigua.",
            evidence: "Responde correctamente.",
            recommendation: "Precisar la acción.",
            improvement_example: "Reemplazar por: Explica dos causas usando evidencia del texto.",
            page: 2,
        },
        {
            code: "4.6",
            dimension: "Distribución y desempeño",
            applicability: "Instrumentos con puntaje",
            criterion: "El puntaje está equilibrado.",
            status: "not_evidenced",
            finding: "No se ve el detalle de puntajes.",
            evidence: null,
            recommendation: "Agregar puntaje por ítem.",
            improvement_example: "Incorporar 5 puntos junto al ítem de desarrollo.",
            page: null,
        },
    ],
    strengths: ["OA visible"],
    observations: [{
        category: "instrucciones",
        severity: "important",
        title: "Consigna ambigua",
        description: "La acción no es observable.",
        recommendation: "Usar un verbo observable.",
        evidence: "Responde correctamente.",
        page: 2,
    }],
    miscellaneous_findings: [{
        category: "arithmetic",
        severity: "critical",
        title: "Suma de puntajes inconsistente",
        finding: "Los ítems suman 28 y el encabezado declara 30.",
        evidence: "5 + 8 + 15 = 28.",
        recommendation: "Corregir el total declarado.",
        improvement_example: "Cambiar Puntaje total: 30 por Puntaje total: 28.",
        page: 1,
    }],
    recommendations: ["Precisar las instrucciones"],
    suggested_teacher_message: "Corrige la consigna y el total antes de reenviar.",
};

const instrument = {
    title: "Prueba de lenguaje",
    school: { name: "Colegio de prueba" },
    owner: { name: "Docente de prueba" },
    subject: { name: "Lenguaje" },
    courses: [{ name: "4° Básico A" }],
    latest_file: { version: 1, original_filename: "prueba.docx" },
};

describe("informe descargable de retroalimentación documental", () => {
    it("calcula estadísticas desde los criterios sin depender del modelo", () => {
        expect(aiReportStatistics(report)).toEqual({
            criteria_total: 3,
            meets: 1,
            partially_meets: 1,
            does_not_meet: 0,
            not_evidenced: 1,
            not_applicable: 0,
            needs_attention: 2,
            compliance_percentage: 50,
            evidence_coverage_percentage: 67,
            miscellaneous_findings: 1,
        });
    });

    it("conserva todo el contenido, ejemplos y hallazgos en el PDF", () => {
        const definition = buildPedagogicalAiReportPdfDefinition(instrument, {
            report,
            file_version: 1,
            finished_at: "2026-08-26T20:00:00-04:00",
        });
        const serialized = JSON.stringify(definition.content);

        expect(serialized).toContain("Retroalimentación completa sin resumir");
        expect(serialized).toContain("ESTADÍSTICA DE RESUMEN");
        expect(serialized).toContain("Ejemplo específico: Reemplazar por: Explica dos causas");
        expect(serialized).toContain("HALLAZGOS MISCELÁNEOS");
        expect(serialized).toContain("5 + 8 + 15 = 28");
        expect(serialized).toContain("Corrige la consigna y el total antes de reenviar");
        expect(serialized).toContain('"text":"Retroalimentación completa sin resumir.","alignment":"justify"');
        expect(serialized).toContain('"text":"El OA está declarado.","alignment":"justify"');
        expect(serialized).toContain('"text":"Ejemplo específico: Cambiar Puntaje total: 30 por Puntaje total: 28.","alignment":"justify"');
        expect(definition.pageMargins).toEqual([44, 42, 44, 54]);
        expect(definition.content[0].table.widths).toEqual(["*", 112]);
        expect(JSON.stringify(definition.content[0])).toContain("#143B58");
        expect(definition.defaultStyle.lineHeight).toBe(1.18);
        expect(definition.info.creator).toBe("Gestión pedagógica");
        expect(JSON.stringify(definition)).not.toContain("hecho por IA");
    });

    it("usa la fecha local en el nombre del archivo", () => {
        expect(
            pedagogicalAiReportFilename(instrument, new Date(2026, 7, 26, 23, 30))
        ).toBe("informe-retroalimentacion-prueba-de-lenguaje-2026-08-26.pdf");
    });
});
