import { getPdfMake } from "../../utils/pdfmake";

const typeLabels = {
    student_interview: "Entrevista individual con estudiante",
    guardian_interview: "Entrevista con apoderado(a)",
    teacher_interview: "Entrevista con docente",
    crisis_intervention: "Intervención en crisis",
    case_meeting: "Reunión de caso",
    follow_up: "Seguimiento",
    external_referral: "Derivación externa",
};

const participantLabels = {
    student: "Estudiante",
    guardian: "Apoderado(a)",
    teacher: "Docente",
    education_assistant: "Asistente de la educación",
    other: "Otro",
};

const statusLabels = { draft: "Borrador", finalized: "Finalizada" };
const modalityLabels = {
    presencial: "Presencial",
    remota: "Remota",
    telefonica: "Telefónica",
};
const attendanceLabels = {
    realizada: "Realizada",
    completed: "Realizada",
    scheduled: "Programada",
    absent: "Inasistencia",
    cancelled: "Cancelada",
    rescheduled: "Reprogramada",
};
const value = (input) =>
    input === null || input === undefined || input === "" ? "—" : String(input);

const date = (input) => {
    if (!input) return "—";
    const source = String(input);
    const parsed = new Date(
        source.length === 10 ? source + "T12:00:00" : source
    );
    return new Intl.DateTimeFormat("es-CL", { dateStyle: "medium" }).format(
        parsed
    );
};

const dateTime = (input) =>
    input
        ? new Intl.DateTimeFormat("es-CL", {
              dateStyle: "medium",
              timeStyle: "short",
          }).format(new Date(input))
        : "—";

const section = (number, title, content, privateSection = false) => {
    if (!content) return [];

    return [
        {
            text: number + "  " + title,
            style: privateSection ? "privateSectionTitle" : "sectionTitle",
        },
        {
            table: {
                widths: ["*"],
                body: [
                    [
                        {
                            text: value(content),
                            margin: [10, 9, 10, 9],
                            fillColor: privateSection ? "#f7f3fb" : "#fafbfd",
                            color: privateSection ? "#514168" : "#354256",
                        },
                    ],
                ],
            },
            layout: {
                hLineWidth: () => 0.6,
                vLineWidth: () => 0.6,
                hLineColor: () => (privateSection ? "#dfd3ed" : "#dfe5ec"),
                vLineColor: () => (privateSection ? "#dfd3ed" : "#dfe5ec"),
            },
            margin: [0, 0, 0, 13],
        },
    ];
};

export async function downloadPsychologyActivityPdf(payload) {
    const activity = payload.activity || {};
    const caseData = payload.case || {};
    const student = payload.student || {};
    const code = value(caseData.code);
    const title = activity.interview_number
        ? "Entrevista N° " + activity.interview_number
        : "Ficha de atención";
    const participants = (activity.participant_types || [])
        .map((item) => participantLabels[item] || item)
        .join(", ");
    const timeRange =
        activity.starts_at || activity.ends_at
            ? value(activity.starts_at?.slice(0, 5)) +
              " a " +
              value(activity.ends_at?.slice(0, 5))
            : "—";
    const privateContentIncluded = Boolean(
        activity.general_background || activity.private_note
    );

    const definition = {
        pageSize: "A4",
        pageMargins: [38, 42, 38, 54],
        info: {
            title: title + " · " + code,
            subject: "Registro profesional de Psicología Escolar",
            author: "CNSC Gestión",
        },
        watermark: {
            text:
                activity.status === "finalized"
                    ? "CONFIDENCIAL"
                    : "BORRADOR CONFIDENCIAL",
            color: "#66527f",
            opacity: 0.05,
            bold: true,
        },
        footer: (current, total) => ({
            margin: [38, 10, 38, 0],
            stack: [
                {
                    canvas: [
                        {
                            type: "line",
                            x1: 0,
                            y1: 0,
                            x2: 519,
                            y2: 0,
                            lineWidth: 0.6,
                            lineColor: "#d8dee7",
                        },
                    ],
                },
                {
                    margin: [0, 6, 0, 0],
                    columns: [
                        {
                            text:
                                "CNSC Gestión · " +
                                code +
                                " · Emitido " +
                                dateTime(payload.generated_at),
                            fontSize: 6.8,
                            color: "#68778a",
                        },
                        {
                            text: "Página " + current + " de " + total,
                            alignment: "right",
                            fontSize: 6.8,
                            color: "#68778a",
                        },
                    ],
                },
            ],
        }),
        content: [
            {
                table: {
                    widths: ["*"],
                    body: [
                        [
                            {
                                margin: [15, 13, 15, 13],
                                fillColor: "#28364d",
                                color: "#ffffff",
                                columns: [
                                    {
                                        width: "*",
                                        stack: [
                                            {
                                                text: "PSICOLOGÍA ESCOLAR",
                                                fontSize: 8,
                                                bold: true,
                                                characterSpacing: 1.4,
                                                color: "#cdd8e8",
                                            },
                                            {
                                                text: title,
                                                fontSize: 17,
                                                bold: true,
                                                margin: [0, 3, 0, 0],
                                            },
                                            {
                                                text:
                                                    typeLabels[activity.type] ||
                                                    value(activity.type),
                                                fontSize: 8.5,
                                                color: "#e5eaf1",
                                                margin: [0, 4, 0, 0],
                                            },
                                        ],
                                    },
                                    {
                                        width: 115,
                                        alignment: "right",
                                        stack: [
                                            {
                                                text: code,
                                                fontSize: 10,
                                                bold: true,
                                            },
                                            {
                                                text:
                                                    statusLabels[
                                                        activity.status
                                                    ] || value(activity.status),
                                                fontSize: 7,
                                                color: "#f1cf86",
                                                margin: [0, 5, 0, 0],
                                            },
                                        ],
                                    },
                                ],
                            },
                        ],
                    ],
                },
                layout: "noBorders",
                margin: [0, 0, 0, 12],
            },
            {
                table: {
                    widths: ["*", "*", "*"],
                    body: [
                        [
                            {
                                stack: [
                                    { text: "ESTUDIANTE", style: "metaLabel" },
                                    {
                                        text: value(student.name),
                                        style: "metaValue",
                                    },
                                    {
                                        text:
                                            value(student.rut) +
                                            " · " +
                                            value(student.course),
                                        style: "metaSubvalue",
                                    },
                                ],
                                style: "metaCell",
                            },
                            {
                                stack: [
                                    {
                                        text: "PERSONA ENTREVISTADA",
                                        style: "metaLabel",
                                    },
                                    {
                                        text: value(activity.interviewee_name),
                                        style: "metaValue",
                                    },
                                    {
                                        text:
                                            (participantLabels[
                                                activity.interviewee_type
                                            ] ||
                                                value(
                                                    activity.interviewee_type
                                                )) +
                                            " · RUT " +
                                            value(activity.interviewee_rut),
                                        style: "metaSubvalue",
                                    },
                                ],
                                style: "metaCell",
                            },
                            {
                                stack: [
                                    {
                                        text: "FECHA Y HORARIO",
                                        style: "metaLabel",
                                    },
                                    {
                                        text: date(activity.activity_on),
                                        style: "metaValue",
                                    },
                                    {
                                        text: timeRange,
                                        style: "metaSubvalue",
                                    },
                                ],
                                style: "metaCell",
                            },
                        ],
                    ],
                },
                layout: {
                    hLineWidth: () => 0.5,
                    vLineWidth: () => 0.5,
                    hLineColor: () => "#dbe2ea",
                    vLineColor: () => "#dbe2ea",
                },
                margin: [0, 0, 0, 12],
            },
            {
                table: {
                    widths: [94, "*", 82, "*"],
                    body: [
                        [
                            { text: "Profesional", style: "fieldLabel" },
                            value(activity.interviewer_name),
                            { text: "Cargo", style: "fieldLabel" },
                            value(activity.interviewer_position),
                        ],
                        [
                            { text: "Modalidad / lugar", style: "fieldLabel" },
                            value(
                                modalityLabels[activity.modality] ||
                                    activity.modality
                            ) +
                                " · " +
                                value(activity.location),
                            { text: "Asistencia", style: "fieldLabel" },
                            value(
                                attendanceLabels[activity.attendance_status] ||
                                    activity.attendance_status
                            ),
                        ],
                        [
                            { text: "Participantes", style: "fieldLabel" },
                            value(participants),
                            { text: "Otros", style: "fieldLabel" },
                            value(activity.participants),
                        ],
                    ],
                },
                layout: {
                    hLineWidth: (index) => (index === 0 ? 0 : 0.45),
                    vLineWidth: () => 0,
                    hLineColor: () => "#e1e6ed",
                    fillColor: (row) => (row % 2 ? "#fafbfc" : null),
                },
                margin: [0, 0, 0, 14],
            },
            ...section("01", "MOTIVO U OBJETIVO", activity.objective),
            ...section(
                "02",
                "RESUMEN INSTITUCIONAL",
                activity.institutional_summary
            ),
            ...section(
                "03",
                "ANTECEDENTES GENERALES · CONTENIDO PROTEGIDO",
                activity.general_background,
                true
            ),
            ...section(
                "04",
                "NOTA PROFESIONAL PRIVADA · CONTENIDO PROTEGIDO",
                activity.private_note,
                true
            ),
            ...section("05", "RESULTADO", activity.result),
            ...section("06", "ACUERDOS", activity.agreements),
            ...section("07", "PRÓXIMOS PASOS", activity.next_steps),
            ...section(
                "08",
                "RETROALIMENTACIÓN AL ÁREA DERIVANTE",
                activity.referral_feedback
            ),
            {
                table: {
                    widths: ["*", "*"],
                    body: [
                        [
                            {
                                stack: [
                                    {
                                        text: "TIPO DE SEGUIMIENTO",
                                        style: "metaLabel",
                                    },
                                    {
                                        text: value(
                                            activity.follow_up_type_label ||
                                                activity.follow_up_type
                                        ),
                                        style: "metaValue",
                                    },
                                ],
                                style: "metaCell",
                            },
                            {
                                stack: [
                                    {
                                        text: "FECHA DE SEGUIMIENTO",
                                        style: "metaLabel",
                                    },
                                    {
                                        text: date(activity.next_action_on),
                                        style: "metaValue",
                                    },
                                ],
                                style: "metaCell",
                            },
                        ],
                        [
                            {
                                stack: [
                                    { text: "VISIBILIDAD", style: "metaLabel" },
                                    {
                                        text: value(activity.visibility),
                                        style: "metaValue",
                                    },
                                ],
                                style: "metaCell",
                            },
                            {
                                stack: [
                                    { text: "ESTADO", style: "metaLabel" },
                                    {
                                        text:
                                            statusLabels[activity.status] ||
                                            value(activity.status),
                                        style: "metaValue",
                                    },
                                ],
                                style: "metaCell",
                            },
                        ],
                    ],
                },
                layout: {
                    hLineWidth: () => 0.5,
                    vLineWidth: () => 0.5,
                    hLineColor: () => "#dbe2ea",
                    vLineColor: () => "#dbe2ea",
                },
                margin: [0, 1, 0, 13],
            },
            {
                text: privateContentIncluded
                    ? "Documento confidencial con contenido profesional protegido. Su exportación quedó registrada en la auditoría institucional."
                    : "Documento confidencial. La exportación contiene únicamente la información autorizada para la persona emisora y quedó registrada en la auditoría institucional.",
                style: "notice",
            },
        ],
        styles: {
            sectionTitle: {
                fontSize: 8.6,
                bold: true,
                color: "#5d497c",
                characterSpacing: 0.45,
                margin: [0, 2, 0, 6],
            },
            privateSectionTitle: {
                fontSize: 8.6,
                bold: true,
                color: "#70518c",
                characterSpacing: 0.35,
                margin: [0, 2, 0, 6],
            },
            metaCell: { margin: [7, 7, 7, 7] },
            metaLabel: {
                fontSize: 6.2,
                bold: true,
                color: "#788596",
            },
            metaValue: {
                fontSize: 8.2,
                bold: true,
                color: "#2f3c4f",
                margin: [0, 3, 0, 0],
            },
            metaSubvalue: {
                fontSize: 6.6,
                color: "#6f7d90",
                margin: [0, 2, 0, 0],
            },
            fieldLabel: {
                bold: true,
                color: "#4d5a6d",
                fontSize: 7.2,
            },
            notice: {
                fontSize: 7,
                italics: true,
                color: "#647184",
                alignment: "center",
                margin: [0, 8, 0, 0],
            },
        },
        defaultStyle: {
            fontSize: 7.7,
            lineHeight: 1.22,
            color: "#354256",
        },
    };

    const safeCode = String(caseData.code || "caso")
        .replace(/[^a-z0-9-]+/gi, "-")
        .replace(/^-+|-+$/g, "");
    const suffix = activity.interview_number
        ? "entrevista-" + activity.interview_number
        : "atencion-" + (activity.id || "registro");

    (await getPdfMake())
        .createPdf(definition)
        .download("psicologia-" + safeCode + "-" + suffix + ".pdf");
}
