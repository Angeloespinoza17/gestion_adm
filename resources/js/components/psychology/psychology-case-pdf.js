import { getPdfMake } from "../../utils/pdfmake";

const asText = (value) => {
    if (value === null || value === undefined || value === "") return "—";
    if (typeof value === "boolean") return value ? "Sí" : "No";
    if (Array.isArray(value)) return value.length ? value.map((item) => typeof item === "string" ? label(item) : asText(item)).join(", ") : "—";
    if (typeof value === "object") {
        return Object.entries(value)
            .filter(([, item]) => item !== null && item !== undefined && item !== "")
            .map(([key, item]) => `${label(key)}: ${asText(item)}`)
            .join(" · ") || "—";
    }
    return String(value);
};

const LABELS = {
    open: "Abierto",
    closed: "Cerrado",
    draft: "Borrador",
    active: "Activo",
    pending: "Pendiente",
    completed: "Completado",
    finalized: "Finalizada",
    cancelled: "Cancelado",
    low: "Baja",
    medium: "Media",
    high: "Alta",
    critical: "Crítica",
    direct: "Atención directa",
    internal: "Interno",
    restricted: "Restringido",
    private_psychology: "Privado de Psicología",
    psychology_team: "Equipo de Psicología",
    interdisciplinary_team: "Equipo interdisciplinario",
    primary: "Responsable principal",
    student: "Estudiante",
    guardian: "Apoderado/a",
    teacher: "Docente",
    staff: "Funcionario/a",
    other: "Otra persona",
    student_interview: "Entrevista con estudiante",
    guardian_interview: "Entrevista con apoderado/a",
    staff_interview: "Entrevista con funcionario/a",
    individual_session: "Sesión individual",
    group_session: "Sesión grupal",
    observation: "Observación",
    coordination: "Coordinación",
    follow_up: "Seguimiento",
    present: "Presente",
    absent: "Ausente",
    scheduled: "Programada",
    not_requested: "No solicitada",
    not_applicable: "No aplica",
};
const label = (value) => {
    const normalized = asText(value);
    return LABELS[normalized] || normalized.replaceAll("_", " ");
};
const date = (value, withTime = false) => {
    if (!value) return "—";
    const source = String(value);
    const parsed = new Date(source.length === 10 ? `${source}T12:00:00` : source);
    if (Number.isNaN(parsed.getTime())) return asText(value);
    return new Intl.DateTimeFormat("es-CL", {
        dateStyle: "medium",
        ...(withTime ? { timeStyle: "short" } : {}),
    }).format(parsed);
};
const person = (value) => value?.name || value?.registered_name_resolved || asText(value);
const section = (number, title, detail) => ({
    margin: [0, 9, 0, 7],
    columns: [
        { width: 26, text: number, style: "sectionNumber" },
        { width: "*", stack: [{ text: title, style: "sectionTitle" }, ...(detail ? [{ text: detail, style: "sectionDetail" }] : [])] },
    ],
});
const empty = (message) => ({ text: message, style: "empty", margin: [0, 0, 0, 10] });
const facts = (rows) => ({
    table: {
        widths: [118, "*"],
        body: rows.map(([name, value]) => [
            { text: name, style: "fieldLabel" },
            { text: asText(value), style: "fieldValue" },
        ]),
    },
    layout: {
        hLineWidth: (index) => (index === 0 ? 0 : 0.45),
        vLineWidth: () => 0,
        hLineColor: () => "#e3e8ef",
        fillColor: (row) => (row % 2 ? "#fafbfc" : null),
        paddingLeft: () => 7,
        paddingRight: () => 7,
        paddingTop: () => 5,
        paddingBottom: () => 5,
    },
    margin: [0, 0, 0, 10],
});
const record = (title, meta, rows, tone = "default") => ({
    unbreakable: true,
    table: {
        widths: ["*"],
        body: [[{
            margin: [9, 8, 9, 8],
            fillColor: tone === "protected" ? "#fff8f2" : "#f8fafc",
            stack: [
                { columns: [{ text: title, style: "recordTitle" }, { text: meta, style: "recordMeta", alignment: "right" }] },
                ...rows.filter(([, value]) => value !== null && value !== undefined && value !== "").map(([name, value]) => ({
                    margin: [0, 4, 0, 0],
                    text: [{ text: `${name}: `, bold: true, color: "#526175" }, { text: asText(value) }],
                })),
            ],
        }]],
    },
    layout: {
        hLineWidth: () => 0.65,
        vLineWidth: () => 0.65,
        hLineColor: () => tone === "protected" ? "#efd8c8" : "#dfe6ee",
        vLineColor: () => tone === "protected" ? "#efd8c8" : "#dfe6ee",
    },
    margin: [0, 0, 0, 7],
});

export function buildPsychologyCasePdfDefinition(caseData, generatedAt = new Date().toISOString()) {
    const code = asText(caseData.code);
    const student = caseData.student || {};
    const activities = caseData.activities || [];
    const plans = caseData.plans || [];
    const referrals = caseData.referrals || [];
    const coordinations = caseData.coordination_requests || [];
    const risks = caseData.risk_assessments || [];
    const tasks = caseData.tasks || [];
    const consents = caseData.consents || [];
    const externalReferrals = caseData.external_referrals || [];
    const feedback = caseData.shared_feedback || [];
    const documents = caseData.documents || [];
    const closures = caseData.closures || [];
    const reopenings = caseData.reopenings || [];
    const activityRecords = activities.flatMap((item, index) => [
        record(
            `${index + 1}. ${label(item.type)}${item.interview_number ? ` · Entrevista N° ${item.interview_number}` : ""}`,
            `${date(item.activity_on)} · ${label(item.status)}`,
            [
                ["Profesional", person(item.responsible_user) || item.interviewer_name_snapshot],
                ["Horario", [item.starts_at, item.ends_at].filter(Boolean).join(" – ")],
                ["Modalidad / lugar", [label(item.modality), item.location].filter(Boolean).join(" · ")],
                ["Participantes", item.participant_types],
                ["Detalle de participantes", item.participants],
                ["Persona entrevistada", [item.interviewee_name, label(item.interviewee_type), item.interviewee_rut].filter(Boolean).join(" · ")],
                ["Objetivo", item.objective],
                ["Resumen institucional", item.institutional_summary],
                ["Antecedentes generales protegidos", item.general_background],
                ["Nota profesional privada", item.private_note],
                ["Resultado", item.result],
                ["Acuerdos", item.agreements],
                ["Seguimiento", [label(item.follow_up_type), date(item.next_action_on), item.next_steps].filter((value) => value && value !== "—").join(" · ")],
                ["Constancia", [label(item.acknowledgement_status), item.acknowledged_name, item.acknowledged_rut, date(item.acknowledged_at, true), item.acknowledgement_observations].filter(Boolean).join(" · ")],
                ["Asistencia / visibilidad", `${label(item.attendance_status)} · ${label(item.visibility)}`],
                ["Retroalimentación", item.referral_feedback],
            ],
            item.private_note || item.general_background ? "protected" : "default",
        ),
        ...(item.addenda || []).map((addendum) => record(
            `Adenda · ${asText(addendum.reason)}`,
            `${date(addendum.created_at, true)} · ${person(addendum.author)}`,
            [["Contenido", addendum.content], ["Visibilidad", label(addendum.visibility)]],
        )),
    ]);
    const planRecords = plans.flatMap((plan, planIndex) => (plan.versions || []).map((version) => record(
        `Plan ${planIndex + 1} · Versión ${asText(version.version)}`,
        `${label(plan.status)} · revisión ${date(plan.review_on)}`,
        [
            ["Responsable", person(plan.responsible_user)],
            ["Situación general", version.general_situation],
            ["Objetivo general", version.general_objective],
            ["Objetivos específicos", version.specific_objectives],
            ["Acciones planificadas", version.planned_actions],
            ["Responsables / frecuencia", [version.responsibles, version.frequency].filter(Boolean).join(" · ")],
            ["Período estimado", `${date(version.estimated_start_on)} – ${date(version.estimated_end_on)}`],
            ["Indicadores", version.monitoring_indicators],
            ["Participantes", version.participants],
            ["Coordinación familiar", version.family_coordination],
            ["Coordinación docente", version.teacher_coordination],
            ["Coordinación convivencia", version.coexistence_coordination],
            ["Coordinación externa", version.external_coordination],
            ["Resultado de revisión", version.review_result],
            ["Autoría", `${person(version.author)} · ${date(version.created_at, true)}`],
        ],
    )));

    return {
        pageSize: "A4",
        pageMargins: [38, 42, 38, 54],
        info: { title: `Expediente de Psicología ${code}`, subject: "Exportación integral de caso", author: "CNSC Gestión" },
        watermark: { text: "CONFIDENCIAL", color: "#72588f", opacity: 0.05, bold: true },
        footer: (current, total) => ({
            margin: [38, 10, 38, 0],
            columns: [
                { text: `CNSC Gestión · ${code} · Emitido ${date(generatedAt, true)}`, fontSize: 6.8, color: "#718094" },
                { text: `Página ${current} de ${total}`, alignment: "right", fontSize: 6.8, color: "#718094" },
            ],
        }),
        content: [
            { table: { widths: ["*"], body: [[{ margin: [15, 13, 15, 13], fillColor: "#3f3158", color: "#ffffff", columns: [
                { width: "*", stack: [{ text: "PSICOLOGÍA", fontSize: 8, bold: true, characterSpacing: 1.5, color: "#d9cfea" }, { text: "EXPEDIENTE INTEGRAL DEL CASO", fontSize: 16.5, bold: true, margin: [0, 3, 0, 0] }, { text: asText(caseData.general_reason), fontSize: 8.5, color: "#ede8f5", margin: [0, 4, 0, 0] }] },
                { width: 112, alignment: "right", stack: [{ text: code, fontSize: 11, bold: true }, { text: label(caseData.status).toUpperCase(), fontSize: 6.8, color: "#f0d391", margin: [0, 5, 0, 0] }] },
            ] }]] }, layout: "noBorders", margin: [0, 0, 0, 12] },
            { table: { widths: ["*", "*", "*", "*"], body: [[
                { stack: [{ text: "ESTUDIANTE", style: "metaLabel" }, { text: person(student), style: "metaValue" }], style: "metaCell" },
                { stack: [{ text: "RUT / CURSO", style: "metaLabel" }, { text: `${asText(student.rut)} · ${asText(student.course)}`, style: "metaValue" }], style: "metaCell" },
                { stack: [{ text: "PRIORIDAD", style: "metaLabel" }, { text: label(caseData.priority), style: "metaValue" }], style: "metaCell" },
                { stack: [{ text: "RESPONSABLE", style: "metaLabel" }, { text: person(caseData.responsible_user), style: "metaValue" }], style: "metaCell" },
            ]] }, layout: { hLineWidth: () => 0.5, vLineWidth: () => 0.5, hLineColor: () => "#dce3eb", vLineColor: () => "#dce3eb" }, margin: [0, 0, 0, 10] },
            section("01", "IDENTIFICACIÓN Y APERTURA", "Encuadre del caso y datos de contacto autorizados."),
            facts([
                ["Fecha de apertura", date(caseData.opened_at, true)],
                ["Origen", label(caseData.origin)],
                ["Confidencialidad", label(caseData.confidentiality)],
                ["Motivo general", caseData.general_reason],
                ["Categorías", caseData.categories],
                ["Objetivos", caseData.objectives],
                ["Próxima acción", caseData.next_action],
                ["Próxima revisión", date(caseData.next_review_on)],
                ["Información al apoderado", label(caseData.guardian_information_status)],
                ["Apoderado/a", [student.guardian_name, student.guardian_relationship, student.guardian_rut].filter(Boolean).join(" · ")],
                ["Contacto apoderado/a", [student.guardian_phone, student.guardian_email].filter(Boolean).join(" · ")],
            ]),
            section("02", "EQUIPO, ASIGNACIONES Y DERIVACIONES"),
            ...((caseData.assignments || []).map((item) => record("Asignación", `${date(item.assigned_at, true)} · ${label(item.role)}`, [["Profesional", person(item.user)], ["Motivo", item.reason], ["Término", date(item.ended_at, true)]]))),
            ...((caseData.collaborators || []).map((item) => record("Colaborador/a", label(item.pivot?.participation_role), [["Nombre", person(item)], ["Visibilidad", label(item.pivot?.visibility)]]))),
            ...(referrals.map((item) => record(`Derivación ${asText(item.code)}`, `${date(item.referred_at, true)} · ${label(item.status)}`, [["Área de origen", label(item.origin_area)], ["Motivo principal", item.primary_reason], ["Motivos secundarios", item.secondary_reasons], ["Hechos observados", item.observed_facts], ["Inicio aproximado", date(item.approximate_started_on)], ["Personas involucradas", item.people_involved], ["Medidas adoptadas", item.measures_taken], ["Intervenciones previas", item.known_previous_interventions], ["Indicadores de riesgo", item.observed_risk_indicators], ["Observaciones", item.observations], ["Solicitud de información", item.information_request], ["Respuesta recibida", item.information_response], ["Decisión compartida", item.shared_decision_note], ["Nota interna autorizada", item.internal_decision_note]]))),
            ...(!(caseData.assignments || []).length && !(caseData.collaborators || []).length && !referrals.length ? [empty("Sin asignaciones, colaboradores ni derivaciones visibles.")] : []),
            section("03", "PLANES DE INTERVENCIÓN", `${plans.length} plan(es) visible(s).`),
            ...(planRecords.length ? planRecords : [empty("Sin planes de intervención registrados.")]),
            section("04", "ATENCIONES, SESIONES Y ENTREVISTAS", `${activities.length} registro(s) profesional(es).`),
            ...(activityRecords.length ? activityRecords : [empty("Sin atenciones registradas.")]),
            section("05", "COORDINACIONES"),
            ...(coordinations.length ? coordinations.map((item) => record(asText(item.subject), `${date(item.created_at, true)} · ${label(item.status)}`, [["Tipo", label(item.coordination_type)], ["Solicita", person(item.requester)], ["Destinatario/a", person(item.recipient)], ["Fecha propuesta", date(item.requested_for)], ["Solicitud", item.request_message], ["Respuesta", item.response_message], ["Respondida por", person(item.responder)], ["Fecha de respuesta", date(item.responded_at, true)]])) : [empty("Sin coordinaciones registradas.")]),
            section("06", "RIESGOS Y MEDIDAS DE RESGUARDO", "Esta sección solo aparece cuando la persona emisora cuenta con autorización."),
            ...(risks.length ? risks.map((item) => record(`${label(item.risk_type)} · ${label(item.level)}`, `${date(item.created_at, true)} · ${label(item.status)}`, [["Indicadores", item.structured_indicators], ["Fundamento profesional", item.professional_rationale], ["Acción inmediata", item.immediate_action], ["Respuesta programada", date(item.response_at, true)], ["Protocolo", item.protocol_reference], ["Acciones protectoras", (item.actions || []).map((action) => `${date(action.action_at, true)} · ${asText(action.action)} · ${person(action.responsible_user)}`)]] , "protected")) : [empty("Sin evaluaciones de riesgo visibles.")]),
            section("07", "TAREAS Y COMPROMISOS"),
            ...(tasks.length ? tasks.map((item) => record(asText(item.title), `${label(item.status)} · ${date(item.due_at, true)}`, [["Responsable", person(item.responsible_user)], ["Tipo / prioridad", `${label(item.type)} · ${label(item.priority)}`], ["Descripción", item.description], ["Recordatorio", date(item.remind_at, true)], ["Finalización", date(item.completed_at, true)], ["Evidencia", item.completion_evidence]])) : [empty("Sin tareas registradas.")]),
            section("08", "COMUNICACIONES, CONSENTIMIENTOS Y REDES"),
            ...(consents.map((item) => record(`Consentimiento · ${label(item.action_type)}`, `${label(item.status)} · ${date(item.informed_at, true)}`, [["Apoderado informado", item.guardian_informed], ["Medio / resultado", `${label(item.contact_method)} · ${label(item.contact_result)}`], ["Consentimiento requerido", item.consent_required], ["Excepción institucional", item.institutional_exception], ["Observaciones", item.observations]]))),
            ...(externalReferrals.map((item) => record(`Derivación externa · ${asText(item.institution)}`, `${date(item.referred_on)} · ${label(item.status)}`, [["Tipo de institución", label(item.institution_type)], ["Motivo", item.general_reason], ["Apoderado informado", item.guardian_informed], ["Estado de recepción", label(item.reception_status)], ["Respuesta", item.response_summary], ["Próximo contacto", date(item.next_contact_on)], ["Seguimiento pendiente", item.follow_up_pending]]))),
            ...(feedback.map((item) => record("Retroalimentación compartida", `${date(item.created_at, true)} · ${person(item.author)}`, [["Contenido", item.content], ["Visibilidad", label(item.visibility)]]))),
            ...(!consents.length && !externalReferrals.length && !feedback.length ? [empty("Sin comunicaciones, consentimientos ni derivaciones externas registradas.")] : []),
            section("09", "DOCUMENTOS ADJUNTOS", "Inventario de archivos; el contenido binario no se incrusta en el PDF."),
            ...(documents.length ? documents.map((item) => record(asText(item.original_name), `${label(item.category)} · ${label(item.status)}`, [["Descripción", item.description], ["Tipo / tamaño", `${asText(item.mime_type)} · ${asText(item.size_bytes)} bytes`], ["Visibilidad", label(item.visibility)], ["Subido por", person(item.uploaded_by)], ["Fecha", date(item.created_at, true)]])) : [empty("Sin documentos adjuntos.")]),
            section("10", "CIERRES Y REAPERTURAS"),
            ...(closures.map((item) => record(`Cierre · ${label(item.closure_type)}`, `${date(item.closed_at, true)} · ${person(item.author)}`, [["Motivo", item.reason], ["Resultado", item.result_summary], ["Recomendaciones", item.recommendations]]))),
            ...(reopenings.map((item) => record("Reapertura", `${date(item.reopened_at, true)} · ${person(item.author)}`, [["Motivo", item.reason]]))),
            ...(!closures.length && !reopenings.length ? [empty("Caso sin cierres ni reaperturas registradas.")] : []),
            { text: "Documento confidencial generado desde el expediente autorizado. Incluye únicamente los campos que la persona emisora podía consultar al momento de la descarga; no constituye un diagnóstico automático.", style: "notice", margin: [0, 16, 0, 0] },
        ],
        styles: {
            metaCell: { margin: [6, 6, 6, 6] },
            metaLabel: { fontSize: 6.1, bold: true, color: "#7a8798" },
            metaValue: { fontSize: 8, bold: true, color: "#344256", margin: [0, 3, 0, 0] },
            sectionNumber: { fontSize: 8, bold: true, color: "#745b92", alignment: "center", margin: [0, 4, 0, 4] },
            sectionTitle: { fontSize: 9.4, bold: true, color: "#513d6b", characterSpacing: 0.35 },
            sectionDetail: { fontSize: 6.8, color: "#788596", margin: [0, 2, 0, 0] },
            fieldLabel: { bold: true, color: "#526175", fontSize: 7.2 },
            fieldValue: { color: "#344256", fontSize: 7.4 },
            recordTitle: { fontSize: 8.2, bold: true, color: "#3f4c60" },
            recordMeta: { fontSize: 6.6, color: "#7a8797" },
            empty: { italics: true, color: "#7c8999", fontSize: 7.4 },
            notice: { fontSize: 6.9, italics: true, color: "#667488", alignment: "center" },
        },
        defaultStyle: { fontSize: 7.3, lineHeight: 1.2, color: "#354256" },
    };
}

export async function downloadPsychologyCasePdf(payload) {
    const caseData = payload?.data || payload;
    const definition = buildPsychologyCasePdfDefinition(caseData, payload?.generated_at);
    const safeCode = String(caseData.code || "caso").replace(/[^a-zA-Z0-9_-]/g, "-");
    (await getPdfMake()).createPdf(definition).download(`expediente-psicologia-${safeCode}.pdf`);
}
