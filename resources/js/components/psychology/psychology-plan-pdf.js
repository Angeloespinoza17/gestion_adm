import { getPdfMake } from "../../utils/pdfmake";

const statusLabels = {
    draft: "Borrador",
    active: "Activo",
    under_review: "En revisión",
    completed: "Completado",
    replaced: "Reemplazado",
    cancelled: "Cancelado",
};

const value = (input) =>
    input === null || input === undefined || input === "" ? "—" : String(input);

const date = (input) => {
    if (!input) return "—";
    const source = String(input);
    const parsed = new Date(
        source.length === 10 ? `${source}T12:00:00` : source
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

const narrative = (title, content, protectedContent = false) => {
    if (!content) return [];

    return [
        {
            text: title,
            style: protectedContent ? "protectedTitle" : "sectionTitle",
        },
        {
            table: {
                widths: ["*"],
                body: [
                    [
                        {
                            text: value(content),
                            fillColor: protectedContent ? "#f7f3fb" : "#fafbfd",
                            color: protectedContent ? "#514168" : "#354256",
                            margin: [10, 9, 10, 9],
                        },
                    ],
                ],
            },
            layout: {
                hLineWidth: () => 0.6,
                vLineWidth: () => 0.6,
                hLineColor: () => (protectedContent ? "#dfd3ed" : "#dfe5ec"),
                vLineColor: () => (protectedContent ? "#dfd3ed" : "#dfe5ec"),
            },
            margin: [0, 0, 0, 11],
        },
    ];
};

export async function downloadPsychologyPlanPdf(payload) {
    const pdfMake = await getPdfMake();
    const caseData = payload.case || {};
    const student = payload.student || {};
    const plan = payload.plan || {};
    const version = plan.version || {};
    const caseCode = value(caseData.code);
    const versionNumber = version.number || plan.current_version || 1;
    const filename = `psicologia-${caseCode}-plan-v${versionNumber}.pdf`
        .toLowerCase()
        .replace(/[^a-z0-9._-]+/g, "-");

    const definition = {
        pageSize: "A4",
        pageMargins: [38, 42, 38, 54],
        info: {
            title: `Plan de intervención v${versionNumber} · ${caseCode}`,
            subject: "Plan de intervención de Psicología Escolar",
            author: "CNSC Gestión",
        },
        watermark: {
            text:
                plan.status === "draft"
                    ? "BORRADOR CONFIDENCIAL"
                    : "CONFIDENCIAL",
            color: "#66527f",
            opacity: 0.05,
            bold: true,
        },
        footer: (current, total) => ({
            margin: [38, 9, 38, 0],
            columns: [
                {
                    text: `CNSC Gestión · ${caseCode} · Emitido ${dateTime(
                        payload.generated_at
                    )}`,
                    fontSize: 6.8,
                    color: "#68778a",
                },
                {
                    text: `Página ${current} de ${total}`,
                    alignment: "right",
                    fontSize: 6.8,
                    color: "#68778a",
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
                                fillColor: "#28364d",
                                color: "#ffffff",
                                margin: [15, 13, 15, 13],
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
                                                text: `Plan de intervención · Versión ${versionNumber}`,
                                                fontSize: 16,
                                                bold: true,
                                                margin: [0, 3, 0, 0],
                                            },
                                            {
                                                text: value(student.name),
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
                                                text: caseCode,
                                                fontSize: 10,
                                                bold: true,
                                            },
                                            {
                                                text:
                                                    statusLabels[plan.status] ||
                                                    value(plan.status),
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
                                        text: `${value(student.rut)} · ${value(
                                            student.course
                                        )}`,
                                        style: "metaSubvalue",
                                    },
                                ],
                                style: "metaCell",
                            },
                            {
                                stack: [
                                    { text: "RESPONSABLE", style: "metaLabel" },
                                    {
                                        text: value(plan.responsible_name),
                                        style: "metaValue",
                                    },
                                    {
                                        text: `Versión creada ${dateTime(
                                            version.created_at
                                        )}`,
                                        style: "metaSubvalue",
                                    },
                                ],
                                style: "metaCell",
                            },
                            {
                                stack: [
                                    { text: "REVISIÓN", style: "metaLabel" },
                                    {
                                        text: date(plan.review_on),
                                        style: "metaValue",
                                    },
                                    {
                                        text: `Periodo: ${date(
                                            version.estimated_start_on
                                        )} – ${date(version.estimated_end_on)}`,
                                        style: "metaSubvalue",
                                    },
                                ],
                                style: "metaCell",
                            },
                        ],
                    ],
                },
                layout: {
                    hLineWidth: () => 0.6,
                    vLineWidth: () => 0.6,
                    hLineColor: () => "#dfe5ec",
                    vLineColor: () => "#dfe5ec",
                },
                margin: [0, 0, 0, 14],
            },
            ...narrative(
                "01  Situación general",
                version.general_situation,
                true
            ),
            ...narrative("02  Objetivo general", version.general_objective),
            ...narrative(
                "03  Objetivos específicos",
                version.specific_objectives
            ),
            ...narrative("04  Acciones planificadas", version.planned_actions),
            {
                text: "05  Organización y seguimiento",
                style: "sectionTitle",
            },
            {
                table: {
                    widths: [90, "*", 90, "*"],
                    body: [
                        [
                            { text: "Responsables", style: "fieldLabel" },
                            value(version.responsibles),
                            { text: "Frecuencia", style: "fieldLabel" },
                            value(version.frequency),
                        ],
                        [
                            { text: "Participantes", style: "fieldLabel" },
                            value(version.participants),
                            { text: "Indicadores", style: "fieldLabel" },
                            value(version.monitoring_indicators),
                        ],
                    ],
                },
                layout: {
                    hLineWidth: () => 0.45,
                    vLineWidth: () => 0.45,
                    hLineColor: () => "#dfe5ec",
                    vLineColor: () => "#dfe5ec",
                },
                margin: [0, 0, 0, 13],
            },
            ...narrative(
                "06  Coordinación con familia",
                version.family_coordination
            ),
            ...narrative(
                "07  Coordinación docente",
                version.teacher_coordination
            ),
            ...narrative(
                "08  Coordinación con convivencia escolar",
                version.coexistence_coordination
            ),
            ...narrative(
                "09  Coordinación externa",
                version.external_coordination
            ),
            ...narrative("10  Resultado de revisión", version.review_result),
            {
                text: "Documento confidencial. Su generación queda registrada en la auditoría institucional. Verifique destinatarios y resguarde el archivo conforme a los protocolos vigentes.",
                fontSize: 7.4,
                color: "#745c31",
                fillColor: "#fff8e8",
                margin: [10, 9, 10, 9],
            },
        ],
        styles: {
            metaLabel: { fontSize: 6.8, bold: true, color: "#718096" },
            metaValue: {
                fontSize: 9.2,
                bold: true,
                color: "#28364d",
                margin: [0, 3, 0, 0],
            },
            metaSubvalue: {
                fontSize: 7,
                color: "#718096",
                margin: [0, 3, 0, 0],
            },
            metaCell: { margin: [9, 8, 9, 8] },
            sectionTitle: {
                fontSize: 9.5,
                bold: true,
                color: "#354256",
                margin: [0, 0, 0, 5],
            },
            protectedTitle: {
                fontSize: 9.5,
                bold: true,
                color: "#66527f",
                margin: [0, 0, 0, 5],
            },
            fieldLabel: {
                fontSize: 7,
                bold: true,
                color: "#718096",
                fillColor: "#f5f7fa",
            },
        },
        defaultStyle: {
            fontSize: 8.4,
            color: "#354256",
            lineHeight: 1.25,
        },
    };

    pdfMake.createPdf(definition).download(filename);
}
