// @vitest-environment jsdom

import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";
import {
    aiWorkspacePolling,
    aiWorkspacePollingDecision,
    analysisIsStale,
    errorMessage,
    instrumentFileIcon,
    pedagogicalPolling,
    printPresentation,
    pollingDecision,
    statusPresentation,
    validateInstrumentCandidate,
    workflowPresentation,
} from "../../resources/js/services/pedagogical-management-api";
import {
    buildPedagogicalStatisticsPdfDefinition,
    pedagogicalStatisticsPdfFilename,
} from "../../resources/js/utils/pedagogical-statistics-pdf";

describe("Gestión pedagógica · análisis de instrumentos", () => {
    it("accepts non-empty PDF and DOCX documents within the configured limit", () => {
        expect(
            validateInstrumentCandidate(
                new File(["%PDF-1.7"], "instrumento.pdf", {
                    type: "application/pdf",
                }),
                20
            )
        ).toBeNull();
        expect(
            validateInstrumentCandidate(
                new File(["texto"], "instrumento.docx", {
                    type: "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
                }),
                20
            )
        ).toBeNull();
        expect(instrumentFileIcon("instrumento.docx")).toBe("bx-file");
        expect(instrumentFileIcon("instrumento.pdf")).toBe("bxs-file-pdf");
        expect(
            validateInstrumentCandidate(
                new File(["texto"], "instrumento.txt", {
                    type: "text/plain",
                }),
                20
            )
        ).toContain("Solo se permiten archivos PDF o Word");
        expect(
            validateInstrumentCandidate(
                new File([], "vacio.pdf", { type: "application/pdf" }),
                20
            )
        ).toContain("vacío");
        expect(
            validateInstrumentCandidate(
                new File(["x".repeat(22000)], "grande.pdf", {
                    type: "application/pdf",
                }),
                20
            )
        ).toContain("supera");
    });

    it("accepts exactly 30 MB and rejects the next byte with the configured limit", () => {
        const base = {
            name: "instrumento.pdf",
            type: "application/pdf",
        };

        expect(
            validateInstrumentCandidate({ ...base, size: 30 * 1024 * 1024 })
        ).toBeNull();
        expect(
            validateInstrumentCandidate({ ...base, size: 30 * 1024 * 1024 + 1 })
        ).toBe("El archivo supera el máximo de 30 MB.");
    });

    it("communicates deterministic review states in human language", () => {
        expect(statusPresentation("review_required")).toEqual({
            label: "Con errores",
            tone: "danger",
        });
        expect(statusPresentation("validated_with_warnings")).toEqual({
            label: "Con sugerencias",
            tone: "warning",
        });
        expect(statusPresentation("validated")).toEqual({
            label: "Revisado",
            tone: "success",
        });
        expect(statusPresentation("not_analyzable")).toEqual({
            label: "No analizable",
            tone: "danger",
        });
    });

    it("communicates the human document workflow and print states", () => {
        expect(workflowPresentation("submitted")).toEqual({
            label: "En revisión",
            tone: "info",
        });
        expect(workflowPresentation("rectification_requested")).toEqual({
            label: "Rectificación solicitada",
            tone: "danger",
        });
        expect(workflowPresentation("approved_with_observations")).toEqual({
            label: "Aprobado con observaciones",
            tone: "success",
        });
        expect(printPresentation("in_process")).toEqual({
            label: "En preparación",
            tone: "info",
        });
    });

    it("keeps the new role surfaces and explicit decisions in their own views", () => {
        const teacher = readFileSync(
            resolve(
                process.cwd(),
                "resources/js/views/pedagogical-management/teacher-instruments.vue"
            ),
            "utf8"
        );
        const review = readFileSync(
            resolve(
                process.cwd(),
                "resources/js/views/pedagogical-management/document-review.vue"
            ),
            "utf8"
        );
        const assignments = readFileSync(
            resolve(
                process.cwd(),
                "resources/js/views/pedagogical-management/coordinator-assignments.vue"
            ),
            "utf8"
        );
        const analysis = readFileSync(
            resolve(
                process.cwd(),
                "resources/js/views/pedagogical-management/index.vue"
            ),
            "utf8"
        );
        const printQueue = readFileSync(
            resolve(
                process.cwd(),
                "resources/js/views/pedagogical-management/print-queue.vue"
            ),
            "utf8"
        );
        const aiReportContent = readFileSync(
            resolve(
                process.cwd(),
                "resources/js/components/pedagogical-management/PedagogicalAiReportContent.vue"
            ),
            "utf8"
        );
        const statistics = readFileSync(
            resolve(
                process.cwd(),
                "resources/js/views/pedagogical-management/statistics.vue"
            ),
            "utf8"
        );
        const aiWorkspace = readFileSync(
            resolve(
                process.cwd(),
                "resources/js/views/pedagogical-management/ai-workspace.vue"
            ),
            "utf8"
        );
        const router = readFileSync(
            resolve(process.cwd(), "resources/js/router/index.js"),
            "utf8"
        );

        expect(teacher).toContain("Cargar versión rectificada");
        expect(teacher).toContain("Historial de versiones");
        expect(teacher).toContain("Mis envíos");
        expect(teacher).toContain('<table class="instrument-table">');
        expect(teacher).toContain('aria-labelledby="submission-modal-title"');
        expect(teacher).toContain('scope: "mine"');
        expect(teacher).not.toContain('id="submission-school"');
        expect(teacher).toContain("PDF o Word (.docx)");
        expect(teacher).toContain('import Swal from "sweetalert2"');
        expect(teacher).toContain('title: "Archivo demasiado grande"');
        expect(teacher).toContain(
            'confirmButtonText: "Seleccionar otro archivo"'
        );
        expect(teacher).toContain(
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
        );
        expect(review).toContain("Solicitar rectificación");
        expect(review).toContain(
            "Enviar informe de retroalimentación completo"
        );
        expect(review).toContain("downloadPedagogicalAiReportPdf");
        expect(review).toContain("Gestionar documentos");
        expect(review).toContain("PedagogicalAiReportContent");
        expect(aiReportContent).toContain("text-align:justify");
        expect(aiReportContent).toContain("--report-navy");
        expect(aiReportContent).toContain("box-shadow");
        expect(teacher).toContain(
            "Corresponde exactamente al informe revisado por coordinación"
        );
        expect(teacher).toContain("can_download_ai_report");
        expect(teacher).toContain("Descargar informe");
        expect(aiReportContent).toContain(
            "Evaluación de criterios y consideraciones"
        );
        expect(aiReportContent).toContain("criteria_assessment");
        expect(aiReportContent).toContain("criteriaGroups");
        expect(aiReportContent).toContain("item.applicability");
        expect(aiReportContent).toContain("Ejemplo específico");
        expect(aiReportContent).toContain("Hallazgos misceláneos");
        expect(aiReportContent).toContain("ESTADÍSTICA DE RESUMEN");
        expect(review).toContain('<table class="review-table">');
        expect(review).toContain('aria-labelledby="review-modal-title"');
        expect(assignments).toContain("Niveles completos");
        expect(assignments).toContain("Cursos específicos");
        expect(assignments).not.toContain(">Establecimiento</label>");
        expect(analysis).not.toContain('id="school-context"');
        expect(printQueue).toContain("Instrumentos aprobados");
        expect(printQueue).toContain("Imprimir");
        expect(printQueue).toContain("Descargar");
        expect(printQueue).toContain("Descargar Word para imprimir");
        expect(statistics).toContain("Evolución documental");
        expect(statistics).toContain("Pauta y prioridades");
        expect(statistics).toContain("Trayectorias");
        expect(statistics).toContain("Historial todavía insuficiente");
        expect(statistics).toContain("No evidenciado");
        expect(statistics).toContain("HALLAZGOS MISCELÁNEOS");
        expect(statistics).toContain(
            "pedagogicalManagementApi.instrumentStatistics"
        );
        expect(statistics).toContain("downloadPedagogicalStatisticsPdf");
        expect(statistics).toContain("Exportar informe PDF");
        expect(statistics).toContain("ALCANCE APLICADO AL TABLERO Y AL PDF");
        expect(statistics).toContain("LECTURA EJECUTIVA");
        expect(aiWorkspace).toContain("Revisión de instrumentos con IA");
        expect(aiWorkspace).toMatch(/Sin envío al\s+docente/);
        expect(aiWorkspace).toContain("no entra a Revisión documental");
        expect(aiWorkspace).toContain("PedagogicalAiReportContent");
        expect(aiWorkspace).toContain("downloadPedagogicalAiReportPdf");
        expect(aiWorkspace).toContain("aiWorkspacePollingDecision");
        expect(router).toContain('path: "/gestion-pedagogica/revision-ia"');
        expect(router).toContain(
            'permission: "pedagogical-instruments.ai-workspace"'
        );
    });

    it("builds a filtered executive PDF with the full pedagogical evidence trail", () => {
        const dashboard = {
            meta: {
                school: { name: "Escuela Estadísticas" },
                report_count: 8,
                comparable_pairs: 4,
                minimum_reports: 5,
                minimum_comparable_pairs: 3,
                sample_sufficient: true,
                rubric_versions: ["institutional-review-v1.0.0"],
                prompt_versions: ["document-review-v1.3.0"],
                methodology: {
                    official_source:
                        "Informe completado vinculado a una resolución.",
                    compliance_formula: "Fórmula institucional.",
                    comparison_rule:
                        "Versiones consecutivas con la misma pauta.",
                },
            },
            summary: {
                official_reports: 8,
                instruments: 5,
                teachers: 3,
                current_compliance_percentage: 81.5,
                first_pass_approval_rate: 62.5,
                median_improvement_pp: 9.5,
                comparable_instruments: 4,
                evidence_coverage_percentage: 91,
                median_rectification_days: 3,
                rectification_closure_rate: 75,
                rectification_rate: 50,
            },
            trend: [
                {
                    label: "ago 2026",
                    reports: 8,
                    median_compliance: 81.5,
                    evidence_coverage: 91,
                },
            ],
            decisions: [{ decision: "approved", label: "Aprobados", count: 5 }],
            dimensions: [
                {
                    dimension: "Alineación curricular",
                    first_score: 72,
                    latest_score: 84,
                },
            ],
            priorities: [
                {
                    code: "2.1",
                    criterion: "Alineación con objetivos",
                    dimension: "Alineación curricular",
                    attention_rate: 40,
                    persistence_rate: 25,
                    reach_rate: 50,
                    opportunity_index: 37.5,
                    sample_sufficient: true,
                },
            ],
            criteria: [
                {
                    code: "2.1",
                    criterion: "Alineación con objetivos",
                    dimension: "Alineación curricular",
                    applicable: 8,
                    meets: 5,
                    partially_meets: 2,
                    does_not_meet: 1,
                    not_evidenced: 0,
                    persistence_rate: 25,
                    resolution_rate: 75,
                    opportunity_index: 37.5,
                    sample_sufficient: true,
                },
            ],
            miscellaneous: [
                {
                    label: "Puntajes y cálculos",
                    findings: 1,
                    affected_reports: 1,
                    affected_teachers: 1,
                    incidence_rate: 12.5,
                    critical: 0,
                    important: 1,
                    suggestion: 0,
                },
            ],
            teachers: [
                {
                    name: "Docente Ejemplo",
                    reports: 4,
                    instruments: 2,
                    first_score: 70,
                    latest_score: 84,
                    improvement_pp: 14,
                    first_pass_approval_rate: 50,
                    persistent_criteria: ["3.1"],
                },
            ],
            instruments: [
                {
                    title: "Prueba unidad 1",
                    teacher: { name: "Docente Ejemplo" },
                    subject: { name: "Lenguaje" },
                    courses: [{ name: "7° Básico A" }],
                    reviewed_versions: 2,
                    latest_score: 84,
                    improvement_pp: 14,
                    rubric_compatible: true,
                    latest_decision: "approved_with_observations",
                },
            ],
        };
        const definition = buildPedagogicalStatisticsPdfDefinition(dashboard, {
            filter_labels: ["Año 2026", "Asignatura: Lenguaje"],
            generated_at: new Date(2026, 7, 26, 18, 30),
        });
        const serialized = JSON.stringify(definition);

        expect(definition.pageSize).toBe("A4");
        expect(serialized).toContain("Informe de evolución documental");
        expect(serialized).toContain("Año 2026  /  Asignatura: Lenguaje");
        expect(serialized).toContain("Estado y evolución de los 19 criterios");
        expect(serialized).toContain("Hallazgos misceláneos");
        expect(serialized).toContain("TRAYECTORIA DOCENTE");
        expect(serialized).toContain('"alignment":"justify"');
        expect(
            pedagogicalStatisticsPdfFilename(dashboard, new Date(2026, 7, 26))
        ).toBe(
            "informe-evolucion-documental-escuela-estadisticas-2026-08-26.pdf"
        );
    });

    it("shows field validation without leaking a generic stack trace", () => {
        const error = {
            response: {
                data: {
                    errors: {
                        file: ["Solo se permiten archivos PDF."],
                        course_id: ["Selecciona un curso."],
                    },
                },
            },
        };
        expect(errorMessage(error)).toBe(
            "Solo se permiten archivos PDF. Selecciona un curso."
        );
    });

    it("stops automatic HTTP checks instead of leaving an infinite loader", () => {
        expect(pollingDecision({ pending: true, attempts: 1 })).toBe(
            "continue"
        );
        expect(
            pollingDecision({
                pending: true,
                attempts: pedagogicalPolling.maxAttempts,
            })
        ).toBe("pause");
        expect(
            pollingDecision({ pending: true, attempts: 2, requestFailed: true })
        ).toBe("pause");
        expect(pollingDecision({ pending: false, attempts: 2 })).toBe(
            "complete"
        );
        expect(
            aiWorkspacePollingDecision({ status: "processing", attempts: 1 })
        ).toBe("continue");
        expect(
            aiWorkspacePollingDecision({
                status: "pending",
                attempts: aiWorkspacePolling.maxAttempts,
            })
        ).toBe("pause");
        expect(
            aiWorkspacePollingDecision({ status: "completed", attempts: 2 })
        ).toBe("complete");
        expect(
            analysisIsStale(
                {
                    status: "pending_analysis",
                    latest_analysis: {
                        created_at: "2026-08-25T12:00:00.000Z",
                    },
                },
                new Date("2026-08-25T12:03:00.000Z").getTime()
            )
        ).toBe(true);
        expect(
            analysisIsStale(
                {
                    status: "validated",
                    updated_at: "2026-08-25T12:00:00.000Z",
                },
                new Date("2026-08-25T12:03:00.000Z").getTime()
            )
        ).toBe(false);
    });

    it("keeps the completed feedback visible and exportable from the instrument view", () => {
        const view = readFileSync(
            resolve(
                process.cwd(),
                "resources/js/views/pedagogical-management/index.vue"
            ),
            "utf8"
        );

        expect(view).toContain("Retroalimentación disponible");
        expect(view).toContain("Exportar retroalimentación");
        expect(view).toContain("downloadPedagogicalFeedbackPdf");
        expect(view).toContain("Archivo PDF");
        expect(view).toContain("reglas Laravel");
        expect(view).not.toContain("OpenAI");
        expect(view).not.toContain("Documento Word");
        expect(view).toContain("La pantalla dejó de esperar automáticamente");
        expect(view).toMatch(/sin\s+WebSocket/);
    });

    it("renders literal menu labels without producing missing i18n warnings", () => {
        const sideNav = readFileSync(
            resolve(process.cwd(), "resources/js/components/side-nav.vue"),
            "utf8"
        );
        const horizontalNav = readFileSync(
            resolve(
                process.cwd(),
                "resources/js/components/horizontal-nav.vue"
            ),
            "utf8"
        );

        for (const navigation of [sideNav, horizontalNav]) {
            expect(navigation).toContain("translateMenuLabel(value)");
            expect(navigation).toContain("this.$te(label)");
            expect(navigation).not.toContain("$t(item.label)");
            expect(navigation).not.toContain("$t(subitem.label)");
        }
    });
});
