import { beforeEach, describe, expect, it, vi } from "vitest";
import { readFileSync } from "node:fs";

const pdfState = vi.hoisted(() => ({ definition: null, filename: "" }));

vi.mock("../../resources/js/utils/pdfmake", () => ({
    getPdfMake: () => ({
        createPdf: (definition) => {
            pdfState.definition = definition;
            return { download: (filename) => { pdfState.filename = filename; } };
        },
    }),
}));

import { downloadPsychologyCasePdf } from "../../resources/js/components/psychology/psychology-case-pdf";

describe("Expediente PDF de Psicología", () => {
    beforeEach(() => {
        pdfState.definition = null;
        pdfState.filename = "";
    });

    it("incluye el expediente integral y conserva el alcance autorizado", async () => {
        await downloadPsychologyCasePdf({
            generated_at: "2026-08-31T15:00:00-04:00",
            data: {
                code: "PSI-2026-000777",
                status: "active_intervention",
                priority: "high",
                confidentiality: "private_psychology",
                general_reason: "Acompañamiento psicoeducativo",
                opened_at: "2026-08-31T12:00:00-04:00",
                student: { name: "Elena Soto", rut: "11.111.111-1", course: "5° básico A", guardian_name: "María Soto" },
                responsible_user: { name: "Psicóloga responsable" },
                activities: [{
                    type: "student_interview",
                    activity_on: "2024-05-20",
                    institutional_summary: "Antecedente histórico visible.",
                    private_note: "Nota privada incluida por autorización explícita.",
                    status: "finalized",
                    addenda: [{ reason: "Precisión", content: "Corrección trazable", created_at: "2026-08-31T13:00:00-04:00" }],
                }],
                plans: [{ status: "active", versions: [{ version: 1, general_objective: "Fortalecer recursos personales." }] }],
                coordination_requests: [{ subject: "Coordinación docente", status: "pending", request_message: "Solicitar antecedentes." }],
                risk_assessments: [{ risk_type: "emotional_crisis", level: "high", professional_rationale: "Fundamento protegido." }],
                tasks: [{ title: "Seguimiento", status: "pending" }],
                consents: [{ action_type: "intervention", status: "granted" }],
                external_referrals: [{ institution: "Red externa", general_reason: "Continuidad de apoyo" }],
                documents: [{ original_name: "antecedente.pdf", category: "background" }],
                closures: [],
                reopenings: [],
            },
        });

        const serialized = JSON.stringify(pdfState.definition.content);
        expect(pdfState.definition.pageSize).toBe("A4");
        expect(pdfState.definition.watermark.text).toBe("CONFIDENCIAL");
        expect(serialized).toContain("EXPEDIENTE INTEGRAL DEL CASO");
        expect(serialized).toContain("ATENCIONES, SESIONES Y ENTREVISTAS");
        expect(serialized).toContain("Nota privada incluida por autorización explícita.");
        expect(serialized).toContain("RIESGOS Y MEDIDAS DE RESGUARDO");
        expect(serialized).toContain("DOCUMENTOS ADJUNTOS");
        expect(pdfState.filename).toBe("expediente-psicologia-PSI-2026-000777.pdf");
    });

    it("mantiene editable la fecha de atención y explica el registro histórico", () => {
        const source = readFileSync(new URL("../../resources/js/components/psychology/PsychologyCases.vue", import.meta.url), "utf8");
        const activityDate = source.indexOf("activity.activity_on");
        const start = source.lastIndexOf("<input", activityDate);
        const dateControl = source.slice(start, source.indexOf("/>", activityDate) + 2);

        expect(start).toBeGreaterThan(-1);
        expect(dateControl).toContain('type="date"');
        expect(dateControl).not.toContain("readonly");
        expect(source).toContain("anterior a la apertura del");
        expect(source).toContain("Exportar caso");
    });

    it("ofrece edición trazable de los datos funcionales del caso", () => {
        const source = readFileSync(new URL("../../resources/js/components/psychology/PsychologyCases.vue", import.meta.url), "utf8");

        expect(source).toContain('catalogs.capabilities');
        expect(source).toContain('.edit_case');
        expect(source).toContain('openForm("edit-case")');
        expect(source).toContain('`/api/psychology/cases/${selected.value.id}`');
        expect(source).toContain("Motivo de la actualización");
        expect(source).toContain("Guardar cambios");
        expect(source).toContain("el contenido sensible no se");
    });
});
