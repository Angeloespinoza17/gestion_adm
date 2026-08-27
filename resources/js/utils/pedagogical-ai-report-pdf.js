import { getPdfMake } from "./pdfmake";
import {
    aiReportStatistics,
    criterionStatusPresentation,
    groupAiReportCriteria,
    severityPresentation,
} from "./pedagogical-ai-report";

const colors = {
    navy: "#143B58",
    navySoft: "#EAF1F5",
    ink: "#1E3448",
    muted: "#66798A",
    line: "#DCE6EB",
    soft: "#F5F8FA",
    page: "#FBFCFD",
    teal: "#137F7A",
    tealDark: "#0F6663",
    tealSoft: "#E7F5F3",
    violet: "#6556B8",
    violetSoft: "#F0EEFA",
    amber: "#9A6408",
    amberSoft: "#FFF5DD",
    red: "#A43C46",
    redSoft: "#FDECEF",
    green: "#187057",
    greenSoft: "#E6F4ED",
    white: "#FFFFFF",
};

function value(input, fallback = "No informado") {
    const normalized = String(input ?? "").trim();
    return normalized || fallback;
}

function formatDateTime(input) {
    if (!input) return "No informada";
    const date = new Date(input);
    if (Number.isNaN(date.getTime())) return value(input);
    return new Intl.DateTimeFormat("es-CL", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(date);
}

function localDateStamp(input = new Date()) {
    const date = input instanceof Date ? input : new Date(input);
    const valid = Number.isNaN(date.getTime()) ? new Date() : date;
    return [valid.getFullYear(), valid.getMonth() + 1, valid.getDate()]
        .map((part, index) => String(part).padStart(index ? 2 : 4, "0"))
        .join("-");
}

function safeFilenamePart(input) {
    return (
        value(input, "instrumento")
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .replace(/[^a-zA-Z0-9]+/g, "-")
            .replace(/^-+|-+$/g, "")
            .toLowerCase()
            .slice(0, 70) || "instrumento"
    );
}

function metric(label, metricValue, fillColor, color) {
    return {
        table: {
            widths: ["*"],
            body: [
                [{ text: "", fillColor: color, margin: [0, 1.2, 0, 1.2] }],
                [{
                    stack: [
                        { text: String(metricValue), bold: true, fontSize: 17, color },
                        { text: label.toUpperCase(), bold: true, fontSize: 5.6, characterSpacing: 0.25, color: colors.muted, margin: [0, 3, 0, 0] },
                    ],
                    fillColor,
                    margin: [7, 7, 7, 8],
                }],
            ],
        },
        layout: "noBorders",
    };
}

function criterionBlock(item) {
    const presentation = criterionStatusPresentation(item.status);
    const tone = {
        success: [colors.green, colors.greenSoft],
        warning: [colors.amber, colors.amberSoft],
        danger: [colors.red, colors.redSoft],
        neutral: [colors.muted, colors.soft],
    }[presentation.tone] || [colors.muted, colors.soft];

    return {
        unbreakable: true,
        table: {
            widths: [44, "*", 104],
            body: [
                [
                    { text: value(item.code), bold: true, alignment: "center", color: colors.white, fillColor: colors.teal, fontSize: 10, margin: [0, 7, 0, 7] },
                    { text: value(item.criterion), bold: true, color: colors.ink, fillColor: colors.soft, fontSize: 9.2, lineHeight: 1.1, margin: [10, 6, 10, 6] },
                    { text: presentation.label, bold: true, alignment: "center", color: tone[0], fillColor: tone[1], fontSize: 6.8, margin: [5, 8, 5, 8] },
                ],
                [
                    {
                        stack: [
                            { text: `APLICACIÓN · ${value(item.applicability).toUpperCase()}`, color: colors.violet, fontSize: 6.3, bold: true, characterSpacing: 0.25 },
                            { text: value(item.finding, "Sin hallazgo descrito."), alignment: "justify", color: colors.ink, fontSize: 8.1, lineHeight: 1.3, margin: [0, 5, 0, 0] },
                            item.evidence ? { text: `Evidencia: ${value(item.evidence)}${item.page ? ` · pág. ${item.page}` : ""}`, alignment: "justify", color: colors.muted, italics: true, fontSize: 7.2, lineHeight: 1.2, margin: [0, 5, 0, 0] } : null,
                            item.recommendation ? { text: `Orientación: ${value(item.recommendation)}`, alignment: "justify", color: colors.violet, fontSize: 7.5, lineHeight: 1.2, margin: [0, 5, 0, 0] } : null,
                            { text: `Ejemplo específico: ${value(item.improvement_example, "No disponible en esta versión del informe.")}`, alignment: "justify", color: colors.amber, fillColor: colors.amberSoft, fontSize: 7.5, bold: true, lineHeight: 1.25, margin: [0, 7, 0, 0] },
                        ].filter(Boolean),
                        colSpan: 3,
                        fillColor: colors.white,
                        margin: [10, 7, 10, 9],
                    },
                    {},
                    {},
                ],
            ],
        },
        layout: {
            hLineColor: () => colors.line,
            vLineColor: () => colors.line,
            hLineWidth: () => 0.7,
            vLineWidth: () => 0.7,
            paddingLeft: () => 0,
            paddingRight: () => 0,
            paddingTop: () => 0,
            paddingBottom: () => 0,
        },
        margin: [0, 0, 0, 10],
    };
}

function listSection(title, items, accent) {
    const rows = Array.isArray(items) && items.length
        ? items.map((item) => ({ text: value(typeof item === "string" ? item : item?.recommendation || item?.title), alignment: "justify", margin: [0, 0, 0, 4] }))
        : [{ text: "Sin antecedentes registrados.", color: colors.muted, italics: true }];
    return {
        unbreakable: true,
        table: {
            widths: ["*"],
            body: [
                [{ text: title.toUpperCase(), color: colors.white, fillColor: accent, bold: true, fontSize: 7.3, characterSpacing: 0.35, margin: [9, 6, 9, 6] }],
                [{ ul: rows, color: colors.ink, fillColor: colors.white, fontSize: 7.8, lineHeight: 1.3, margin: [9, 8, 9, 7] }],
            ],
        },
        layout: {
            hLineColor: () => colors.line,
            vLineColor: () => colors.line,
            hLineWidth: () => 0.6,
            vLineWidth: () => 0.6,
            paddingLeft: () => 0,
            paddingRight: () => 0,
            paddingTop: () => 0,
            paddingBottom: () => 0,
        },
    };
}

function detailedFinding(item, miscellaneous = false) {
    const title = value(item.title, "Hallazgo sin título");
    const description = value(miscellaneous ? item.finding : item.description, "Sin descripción.");
    const severityColor = item.severity === "critical" ? colors.red : item.severity === "important" ? colors.amber : colors.teal;
    return {
        unbreakable: true,
        table: {
            widths: [5, "*"],
            body: [[
                { text: "", fillColor: severityColor },
                {
                    stack: [
                        {
                            columns: [
                                { text: severityPresentation(item.severity).toUpperCase(), color: severityColor, bold: true, fontSize: 6.3, characterSpacing: 0.25, width: 72 },
                                { text: title, color: colors.ink, bold: true, fontSize: 9.2, width: "*" },
                                item.page ? { text: `PÁG. ${item.page}`, alignment: "right", color: colors.muted, bold: true, fontSize: 6.3, width: 42 } : { text: "", width: 1 },
                            ],
                        },
                        { text: description, alignment: "justify", color: colors.ink, fontSize: 8, lineHeight: 1.3, margin: [0, 5, 0, 0] },
                        item.evidence ? { text: `Evidencia: ${value(item.evidence)}`, alignment: "justify", color: colors.muted, italics: true, fontSize: 7.2, margin: [0, 5, 0, 0] } : null,
                        { text: `Recomendación: ${value(item.recommendation)}`, alignment: "justify", color: colors.violet, fontSize: 7.5, margin: [0, 5, 0, 0] },
                        miscellaneous ? { text: `Ejemplo específico: ${value(item.improvement_example)}`, alignment: "justify", color: colors.amber, fillColor: colors.amberSoft, bold: true, fontSize: 7.5, margin: [8, 6, 8, 6] } : null,
                    ].filter(Boolean),
                    fillColor: colors.white,
                    margin: [10, 9, 10, 9],
                },
            ]],
        },
        layout: "noBorders",
        margin: [0, 0, 0, 10],
    };
}

function sectionHeader(kicker, title = null, accent = colors.teal) {
    return {
        unbreakable: true,
        stack: [
            {
                columns: [
                    { width: 22, canvas: [{ type: "rect", x: 0, y: 2, w: 18, h: 3, color: accent }] },
                    { width: "*", text: kicker.toUpperCase(), color: accent, bold: true, fontSize: 7.2, characterSpacing: 0.8 },
                ],
                columnGap: 2,
            },
            title ? { text: title, color: colors.ink, bold: true, fontSize: 13.5, margin: [24, 4, 0, 0] } : null,
        ].filter(Boolean),
        margin: [0, 3, 0, title ? 9 : 7],
    };
}

export function buildPedagogicalAiReportPdfDefinition(instrument, aiReport) {
    const report = aiReport?.report || aiReport || {};
    const statistics = aiReportStatistics(report);
    const groups = groupAiReportCriteria(report);
    const width = 507;
    const coveredWidth = Math.max(1, Math.round(width * statistics.evidence_coverage_percentage / 100));
    const remainingWidth = Math.max(1, width - coveredWidth);
    const course = value(instrument?.courses?.map((item) => item.name).join(", ") || instrument?.grade_label);
    const file = value(instrument?.latest_file?.original_filename || instrument?.title, "instrumento");

    return {
        pageSize: "A4",
        pageMargins: [44, 42, 44, 54],
        background: () => ({ canvas: [{ type: "rect", x: 0, y: 0, w: 595.28, h: 841.89, color: colors.page }] }),
        info: {
            title: `Informe de retroalimentación documental - ${file}`,
            subject: "Revisión pedagógica de instrumento de evaluación",
            author: value(instrument?.school?.name, "Gestión pedagógica"),
            creator: "Gestión pedagógica",
        },
        footer: (currentPage, pageCount) => ({
            margin: [44, 4, 44, 0],
            stack: [
                { canvas: [{ type: "line", x1: 0, y1: 0, x2: 507, y2: 0, lineWidth: 0.6, lineColor: colors.line }] },
                {
                    columns: [
                        { text: "GESTIÓN PEDAGÓGICA  /  REVISIÓN DOCUMENTAL", color: colors.muted, fontSize: 6.1, bold: true, characterSpacing: 0.25, margin: [0, 7, 0, 0] },
                        { text: `${String(currentPage).padStart(2, "0")}  /  ${String(pageCount).padStart(2, "0")}`, color: colors.teal, fontSize: 6.5, bold: true, alignment: "right", margin: [0, 7, 0, 0] },
                    ],
                },
            ],
        }),
        content: [
            {
                table: {
                    widths: ["*", 112],
                    body: [[
                        {
                            stack: [
                                { text: "GESTIÓN PEDAGÓGICA", color: "#92D8D1", bold: true, fontSize: 7.3, characterSpacing: 1.2 },
                                { text: "Informe de retroalimentación documental", color: colors.white, bold: true, fontSize: 19, lineHeight: 1.08, margin: [0, 8, 0, 7] },
                                { text: value(instrument?.school?.name), color: "#D9E8EF", fontSize: 8.2 },
                            ],
                            fillColor: colors.navy,
                            margin: [18, 15, 16, 16],
                        },
                        {
                            stack: [
                                { text: "REVISIÓN", alignment: "center", color: "#BDE7E1", bold: true, fontSize: 6.3, characterSpacing: 0.7 },
                                { text: "DOCUMENTAL", alignment: "center", color: colors.white, bold: true, fontSize: 9.2, margin: [0, 4, 0, 9] },
                                { text: `VERSIÓN ${value(aiReport?.file_version || instrument?.latest_file?.version, "1")}`, alignment: "center", color: colors.white, fillColor: colors.teal, bold: true, fontSize: 6.2, margin: [8, 6, 8, 6] },
                            ],
                            fillColor: colors.tealDark,
                            margin: [12, 18, 12, 14],
                        },
                    ]],
                },
                layout: "noBorders",
                margin: [0, 0, 0, 14],
            },
            {
                table: {
                    widths: [70, "*", 70, "*"],
                    body: [
                        [{ text: "DOCENTE", style: "metaLabel" }, { text: value(instrument?.owner?.name), style: "metaValue" }, { text: "ASIGNATURA", style: "metaLabel" }, { text: value(instrument?.subject?.name), style: "metaValue" }],
                        [{ text: "CURSO", style: "metaLabel" }, { text: course, style: "metaValue" }, { text: "VERSIÓN", style: "metaLabel" }, { text: `v${value(aiReport?.file_version || instrument?.latest_file?.version, "1")} · ${formatDateTime(aiReport?.finished_at)}`, style: "metaValue" }],
                        [{ text: "ARCHIVO", style: "metaLabel" }, { text: file, style: "metaValue", colSpan: 3 }, {}, {}],
                    ],
                },
                layout: { hLineColor: () => colors.line, vLineColor: () => colors.line, hLineWidth: () => 0.5, vLineWidth: () => 0.5, paddingLeft: () => 8, paddingRight: () => 8, paddingTop: () => 6, paddingBottom: () => 6 },
                margin: [0, 0, 0, 18],
            },
            sectionHeader("Resumen ejecutivo"),
            {
                table: {
                    widths: [5, "*"],
                    body: [[
                        { text: "", fillColor: colors.teal },
                        { text: value(report.executive_summary, "Sin resumen ejecutivo."), alignment: "justify", color: colors.ink, fillColor: colors.soft, fontSize: 9, lineHeight: 1.4, margin: [12, 10, 12, 11] },
                    ]],
                },
                layout: "noBorders",
                margin: [0, 0, 0, 16],
            },
            sectionHeader("Estadística de resumen"),
            { columns: [
                metric("Nivel de ajuste", `${statistics.compliance_percentage}%`, colors.violetSoft, colors.violet),
                metric("Se ajustan", statistics.meets, colors.greenSoft, colors.green),
                metric("Parciales", statistics.partially_meets, colors.amberSoft, colors.amber),
                metric("No se ajustan", statistics.does_not_meet, colors.redSoft, colors.red),
                metric("No evidenciados", statistics.not_evidenced, colors.soft, colors.muted),
                metric("Misceláneos", statistics.miscellaneous_findings, colors.tealSoft, colors.teal),
            ], columnGap: 5, margin: [0, 0, 0, 9] },
            { columns: [{ text: "COBERTURA DE EVIDENCIA", color: colors.muted, bold: true, fontSize: 6.2, characterSpacing: 0.25 }, { text: `${statistics.evidence_coverage_percentage}%`, color: colors.teal, bold: true, alignment: "right", fontSize: 7 }] },
            { table: { widths: [coveredWidth, remainingWidth], body: [[{ text: "", fillColor: colors.teal, margin: [0, 2.2, 0, 2.2] }, { text: "", fillColor: colors.line, margin: [0, 2.2, 0, 2.2] }]] }, layout: "noBorders", margin: [0, 4, 0, 18] },
            sectionHeader("Pauta institucional + EPA", "Evaluación de criterios y consideraciones", colors.violet),
            ...groups.flatMap((group) => {
                const [firstCriterion, ...remainingCriteria] = group.items;
                const dimensionHeader = {
                    table: {
                        widths: ["*", 74],
                        body: [[
                            { text: group.dimension.toUpperCase(), color: colors.violet, fillColor: colors.violetSoft, bold: true, fontSize: 7.2, characterSpacing: 0.3, margin: [10, 6, 10, 6] },
                            { text: `${group.items.length} ${group.items.length === 1 ? "CRITERIO" : "CRITERIOS"}`, alignment: "center", color: colors.white, fillColor: colors.violet, bold: true, fontSize: 6.2, margin: [5, 7, 5, 7] },
                        ]],
                    },
                    layout: "noBorders",
                };

                return [
                    { unbreakable: true, stack: [dimensionHeader, criterionBlock(firstCriterion)] },
                    ...remainingCriteria.map(criterionBlock),
                ];
            }),
            { columns: [listSection("Fortalezas", report.strengths, colors.green), listSection("Recomendaciones", report.recommendations, colors.violet)], columnGap: 14, margin: [0, 10, 0, 18] },
            ...(() => {
                if (!report.observations?.length) {
                    return [
                        sectionHeader("Observaciones detectadas"),
                        { text: "No se registraron observaciones adicionales.", color: colors.muted, italics: true, margin: [0, 5, 0, 12] },
                    ];
                }

                const [firstObservation, ...remainingObservations] = report.observations;
                return [
                    { unbreakable: true, stack: [sectionHeader("Observaciones detectadas"), detailedFinding(firstObservation)] },
                    ...remainingObservations.map((item) => detailedFinding(item)),
                ];
            })(),
            ...(() => {
                const header = sectionHeader("Hallazgos misceláneos", "Control complementario de puntajes, totales, ponderaciones y coherencia interna", colors.navy);
                if (!report.miscellaneous_findings?.length) {
                    return [
                        header,
                        { text: "No se detectaron incoherencias adicionales verificables.", color: colors.green, fillColor: colors.greenSoft, bold: true, fontSize: 8, margin: [8, 7, 8, 10] },
                    ];
                }

                const [firstFinding, ...remainingFindings] = report.miscellaneous_findings;
                return [
                    { unbreakable: true, stack: [header, detailedFinding(firstFinding, true)] },
                    ...remainingFindings.map((item) => detailedFinding(item, true)),
                ];
            })(),
            { unbreakable: true, stack: [
                sectionHeader("Mensaje sugerido para el docente", null, colors.violet),
                {
                    table: {
                        widths: [5, "*"],
                        body: [[
                            { text: "", fillColor: colors.violet },
                            { text: value(report.suggested_teacher_message, "Sin mensaje adicional."), alignment: "justify", color: colors.ink, fillColor: colors.violetSoft, fontSize: 8.2, lineHeight: 1.35, margin: [12, 9, 12, 10] },
                        ]],
                    },
                    layout: "noBorders",
                },
            ], margin: [0, 2, 0, 0] },
        ],
        styles: {
            metaLabel: { color: colors.tealDark, fillColor: colors.tealSoft, fontSize: 6.3, bold: true, characterSpacing: 0.25 },
            metaValue: { color: colors.ink, fillColor: colors.white, fontSize: 7.8, bold: true },
        },
        defaultStyle: { font: "Roboto", color: colors.ink, lineHeight: 1.18 },
    };
}

export function pedagogicalAiReportFilename(instrument, generatedAt = new Date()) {
    return `informe-retroalimentacion-${safeFilenamePart(instrument?.title)}-${localDateStamp(generatedAt)}.pdf`;
}

export async function downloadPedagogicalAiReportPdf(instrument, aiReport, generatedAt = new Date()) {
    const pdfMake = await getPdfMake();
    const definition = buildPedagogicalAiReportPdfDefinition(instrument, aiReport);
    const filename = pedagogicalAiReportFilename(instrument, generatedAt);
    pdfMake.createPdf(definition).download(filename);
    return { definition, filename };
}
