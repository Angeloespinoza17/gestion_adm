import { getPdfMake } from "./pdfmake";

const palette = {
    ink: "#17324D",
    muted: "#66788A",
    line: "#DDE6ED",
    soft: "#F4F8FA",
    teal: "#177A83",
    tealSoft: "#EAF7F7",
    red: "#B42318",
    redSoft: "#FFF1F0",
    amber: "#9A6700",
    amberSoft: "#FFF7E6",
    white: "#FFFFFF",
};

function text(value, fallback = "No informado") {
    const normalized = String(value ?? "").trim();
    return normalized || fallback;
}

function formatDateTime(value) {
    if (!value) return "No informada";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return text(value);
    return new Intl.DateTimeFormat("es-CL", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(date);
}

function safeFilenamePart(value) {
    return (
        text(value, "instrumento")
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .replace(/[^a-zA-Z0-9]+/g, "-")
            .replace(/^-+|-+$/g, "")
            .toLowerCase()
            .slice(0, 70) || "instrumento"
    );
}

function findingRows(items, tone) {
    if (!items.length) {
        return [
            {
                table: {
                    widths: ["*"],
                    body: [
                        [
                            {
                                text:
                                    tone === "error"
                                        ? "No se reportaron errores concretos."
                                        : "No se agregaron sugerencias.",
                                color:
                                    tone === "error"
                                        ? palette.teal
                                        : palette.muted,
                                fillColor:
                                    tone === "error"
                                        ? palette.tealSoft
                                        : palette.soft,
                                margin: [12, 10, 12, 10],
                                bold: true,
                            },
                        ],
                    ],
                },
                layout: "noBorders",
                margin: [0, 0, 0, 10],
            },
        ];
    }

    const accent = tone === "error" ? palette.red : palette.amber;
    const fill = tone === "error" ? palette.redSoft : palette.amberSoft;

    return items.map((item, index) => ({
        table: {
            widths: [30, "*"],
            body: [
                [
                    {
                        text: String(index + 1).padStart(2, "0"),
                        alignment: "center",
                        color: palette.white,
                        fillColor: accent,
                        bold: true,
                        fontSize: 9,
                        margin: [0, 6, 0, 6],
                    },
                    {
                        stack: [
                            {
                                columns: [
                                    {
                                        text: text(
                                            item.title,
                                            "Hallazgo sin título"
                                        ),
                                        bold: true,
                                        color: palette.ink,
                                        fontSize: 10.5,
                                    },
                                    item.page_number
                                        ? {
                                              text: `PÁG. ${item.page_number}`,
                                              alignment: "right",
                                              color: accent,
                                              bold: true,
                                              fontSize: 7.5,
                                              width: 52,
                                          }
                                        : { text: "", width: 1 },
                                ],
                                columnGap: 8,
                            },
                            {
                                text: text(item.message, "Sin descripción."),
                                color: palette.muted,
                                fontSize: 8.8,
                                lineHeight: 1.28,
                                margin: [0, 4, 0, 0],
                            },
                            item.source_excerpt
                                ? {
                                      text: `Evidencia: ${text(
                                          item.source_excerpt
                                      )}`,
                                      color: palette.muted,
                                      italics: true,
                                      fontSize: 7.8,
                                      lineHeight: 1.2,
                                      margin: [0, 5, 0, 0],
                                  }
                                : null,
                        ].filter(Boolean),
                        fillColor: fill,
                        margin: [10, 7, 10, 7],
                    },
                ],
            ],
        },
        layout: {
            hLineWidth: () => 0,
            vLineWidth: () => 0,
            paddingLeft: () => 0,
            paddingRight: () => 0,
            paddingTop: () => 0,
            paddingBottom: () => 0,
        },
        margin: [0, 0, 0, 7],
    }));
}

function metric(label, value, tone) {
    const colors =
        tone === "error"
            ? [palette.red, palette.redSoft]
            : tone === "suggestion"
            ? [palette.amber, palette.amberSoft]
            : [palette.teal, palette.tealSoft];

    return {
        table: {
            widths: ["*"],
            body: [
                [
                    {
                        stack: [
                            {
                                text: String(value),
                                fontSize: 18,
                                bold: true,
                                color: colors[0],
                            },
                            {
                                text: label.toUpperCase(),
                                fontSize: 7,
                                bold: true,
                                color: palette.muted,
                                characterSpacing: 0.5,
                                margin: [0, 2, 0, 0],
                            },
                        ],
                        fillColor: colors[1],
                        margin: [12, 9, 12, 9],
                    },
                ],
            ],
        },
        layout: "noBorders",
    };
}

export function buildPedagogicalFeedbackPdfDefinition(
    instrument,
    generatedAt = new Date()
) {
    const analysis = instrument?.latest_analysis || {};
    const results = Array.isArray(analysis.results) ? analysis.results : [];
    const errors = results.filter((item) => item.category === "error");
    const suggestions = results.filter(
        (item) => item.category === "suggestion"
    );
    const school = text(
        instrument?.school?.name,
        "Establecimiento no informado"
    );
    const file = text(
        instrument?.latest_file?.original_filename || instrument?.title,
        "instrumento.pdf"
    );
    const course = text(
        instrument?.courses?.[0]?.name || instrument?.grade_label
    );
    const generatedLabel = formatDateTime(generatedAt);

    return {
        pageSize: "A4",
        pageMargins: [44, 50, 44, 64],
        info: {
            title: `Retroalimentación pedagógica - ${file}`,
            subject: "Hallazgos de revisión determinística de instrumentos",
            author: school,
            creator: "Gestión pedagógica",
        },
        footer: (currentPage, pageCount) => ({
            margin: [44, 3, 44, 0],
            stack: [
                {
                    table: {
                        widths: [20, "*"],
                        body: [
                            [
                                {
                                    text: "i",
                                    alignment: "center",
                                    color: palette.white,
                                    fillColor: palette.teal,
                                    bold: true,
                                    margin: [0, 2, 0, 2],
                                },
                                {
                                    text: text(
                                        analysis.disclaimer,
                                        "Revisión mecánica y reproducible basada en reglas institucionales. Requiere criterio profesional humano."
                                    ),
                                    color: palette.muted,
                                    fillColor: palette.tealSoft,
                                    fontSize: 7,
                                    margin: [7, 3, 7, 3],
                                },
                            ],
                        ],
                    },
                    layout: "noBorders",
                    margin: [0, 0, 0, 6],
                },
                {
                    columns: [
                        {
                            text: "GESTIÓN PEDAGÓGICA · INFORME DE REVISIÓN",
                            color: palette.muted,
                            fontSize: 7,
                            bold: true,
                            width: 180,
                        },
                        {
                            text: `Emitido: ${generatedLabel}`,
                            alignment: "center",
                            color: palette.muted,
                            fontSize: 6.2,
                            width: "*",
                        },
                        {
                            text: `Página ${currentPage} de ${pageCount}`,
                            alignment: "right",
                            color: palette.muted,
                            fontSize: 7,
                            width: 62,
                        },
                    ],
                    columnGap: 6,
                },
            ],
        }),
        content: [
            {
                columns: [
                    {
                        width: "*",
                        stack: [
                            {
                                text: "GESTIÓN PEDAGÓGICA",
                                color: palette.teal,
                                fontSize: 8,
                                bold: true,
                                characterSpacing: 1.1,
                            },
                            {
                                text: "Retroalimentación del instrumento",
                                color: palette.ink,
                                fontSize: 21,
                                bold: true,
                                margin: [0, 5, 0, 3],
                            },
                            { text: school, color: palette.muted, fontSize: 9 },
                        ],
                    },
                    {
                        width: 100,
                        table: {
                            widths: ["*"],
                            body: [
                                [
                                    {
                                        text: "INFORME DE REVISIÓN",
                                        alignment: "center",
                                        color: palette.teal,
                                        fillColor: palette.tealSoft,
                                        bold: true,
                                        fontSize: 7.5,
                                        margin: [6, 7, 6, 7],
                                    },
                                ],
                            ],
                        },
                        layout: "noBorders",
                    },
                ],
                columnGap: 16,
                margin: [0, 0, 0, 18],
            },
            {
                table: {
                    widths: [78, "*", 78, "*"],
                    body: [
                        [
                            { text: "DOCENTE", style: "metaLabel" },
                            {
                                text: text(instrument?.owner?.name),
                                style: "metaValue",
                            },
                            { text: "ASIGNATURA", style: "metaLabel" },
                            {
                                text: text(instrument?.subject?.name),
                                style: "metaValue",
                            },
                        ],
                        [
                            { text: "CURSO", style: "metaLabel" },
                            { text: course, style: "metaValue" },
                            { text: "ANÁLISIS", style: "metaLabel" },
                            {
                                text: formatDateTime(analysis.finished_at),
                                style: "metaValue",
                            },
                        ],
                        [
                            { text: "ARCHIVO", style: "metaLabel" },
                            { text: file, style: "metaValue", colSpan: 3 },
                            {},
                            {},
                        ],
                    ],
                },
                layout: {
                    hLineColor: () => palette.line,
                    vLineColor: () => palette.line,
                    hLineWidth: () => 0.6,
                    vLineWidth: () => 0.6,
                    paddingLeft: () => 8,
                    paddingRight: () => 8,
                    paddingTop: () => 6,
                    paddingBottom: () => 6,
                },
                margin: [0, 0, 0, 14],
            },
            {
                columns: [
                    metric("Errores", errors.length, "error"),
                    metric("Sugerencias", suggestions.length, "suggestion"),
                    metric(
                        "Estado",
                        errors.length
                            ? "Revisar"
                            : suggestions.length
                            ? "Mejorable"
                            : "Sin alertas",
                        "status"
                    ),
                ],
                columnGap: 8,
                margin: [0, 0, 0, 16],
            },
            {
                stack: [
                    { text: "RESUMEN DE LA REVISIÓN", style: "sectionLabel" },
                    {
                        text: text(
                            analysis.review_summary,
                            "El motor determinístico no generó un resumen para esta revisión."
                        ),
                        color: palette.ink,
                        fontSize: 10,
                        lineHeight: 1.35,
                        margin: [0, 6, 0, 0],
                    },
                ],
                fillColor: palette.soft,
                margin: [0, 0, 0, 16],
            },
            {
                text: "Errores detectados",
                style: "sectionTitle",
                color: palette.red,
            },
            ...findingRows(errors, "error"),
            {
                text: "Sugerencias de mejora",
                style: "sectionTitle",
                color: palette.amber,
                margin: [0, 11, 0, 8],
            },
            ...findingRows(suggestions, "suggestion"),
        ],
        styles: {
            metaLabel: {
                color: palette.muted,
                fillColor: palette.soft,
                fontSize: 7,
                bold: true,
            },
            metaValue: { color: palette.ink, fontSize: 8.5, bold: true },
            sectionLabel: {
                color: palette.teal,
                fontSize: 7.5,
                bold: true,
                characterSpacing: 0.7,
            },
            sectionTitle: { fontSize: 13, bold: true, margin: [0, 0, 0, 8] },
        },
        defaultStyle: {
            font: "Roboto",
            color: palette.ink,
        },
    };
}

export function pedagogicalFeedbackFilename(
    instrument,
    generatedAt = new Date()
) {
    const course =
        instrument?.courses?.[0]?.name || instrument?.grade_label || "curso";
    const date =
        generatedAt instanceof Date && !Number.isNaN(generatedAt.getTime())
            ? generatedAt.toISOString().slice(0, 10)
            : new Date().toISOString().slice(0, 10);
    return `retroalimentacion-${safeFilenamePart(course)}-${date}.pdf`;
}

export async function downloadPedagogicalFeedbackPdf(
    instrument,
    generatedAt = new Date()
) {
    const pdfMake = await getPdfMake();
    const definition = buildPedagogicalFeedbackPdfDefinition(
        instrument,
        generatedAt
    );
    const filename = pedagogicalFeedbackFilename(instrument, generatedAt);
    pdfMake.createPdf(definition).download(filename);
    return { definition, filename };
}
