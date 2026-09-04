import fs from "node:fs/promises";
import path from "node:path";
import { createRequire } from "node:module";

const require = createRequire(import.meta.url);
const pdfMake = require("pdfmake/build/pdfmake.js");
const fontVfs = require("pdfmake/build/vfs_fonts.js");

const [, , inputPath, outputPath] = process.argv;
if (!inputPath || !outputPath) {
    throw new Error("Uso: node build-teacher-guide.mjs <entrada.json> <salida.pdf>");
}

const payload = JSON.parse(await fs.readFile(inputPath, "utf8"));
const deck = payload.deck || {};
const guide = deck.teacher_guide || {};
const snapshot = payload.curricular_snapshot || {};
const configuration = payload.configuration || {};
const styleContract = configuration.style_contract || {};
const colors = styleContract.colors || {};

pdfMake.vfs = fontVfs;
pdfMake.fonts = {
    Roboto: {
        normal: "Roboto-Regular.ttf",
        bold: "Roboto-Medium.ttf",
        italics: "Roboto-Italic.ttf",
        bolditalics: "Roboto-MediumItalic.ttf",
    },
};

function color(value, fallback) {
    const normalized = String(value || "").replace(/^#/, "").toUpperCase();
    return `#${/^[0-9A-F]{6}$/.test(normalized) ? normalized : fallback}`;
}

const C = {
    primary: color(colors.primary, "173F67"),
    secondary: color(colors.secondary, "1A8D86"),
    accent: color(colors.accent, "F2A65A"),
    accentAlt: color(colors.accent_alt || colors.accentAlt, "E45B5B"),
    ink: color(colors.ink, "17243A"),
    muted: color(colors.muted, "5F6F82"),
    soft: color(colors.soft, "EAF4F3"),
    background: color(colors.background || colors.bg, "F7FAFC"),
    line: "#DDE6ED",
    white: "#FFFFFF",
};

function text(value, fallback = "") {
    return String(value ?? fallback)
        .replace(/[\u2010-\u2015\u2212]/g, "-")
        .replace(/\u00a0/g, " ")
        .trim();
}

function values(items) {
    return Array.isArray(items) ? items.map((item) => text(item)).filter(Boolean) : [];
}

function list(items, empty = "Sin indicaciones adicionales.") {
    const normalized = values(items);
    if (!normalized.length) return { text: empty, color: C.muted, italics: true, margin: [0, 2, 0, 8] };
    return { ul: normalized, margin: [0, 2, 0, 8], color: C.ink, lineHeight: 1.18 };
}

function heading(label, options = {}) {
    return {
        columns: [
            { canvas: [{ type: "rect", x: 0, y: 2, w: 5, h: 20, r: 2, color: options.color || C.secondary }], width: 12 },
            { text: text(label), style: options.large ? "sectionTitleLarge" : "sectionTitle" },
        ],
        columnGap: 4,
        margin: options.margin || [0, 12, 0, 8],
    };
}

function label(value, colorValue = C.secondary) {
    return { text: text(value).toUpperCase(), fontSize: 8, bold: true, color: colorValue, characterSpacing: 0.8, margin: [0, 0, 0, 4] };
}

function callout(title, body, fill = C.soft, accent = C.secondary) {
    return {
        table: {
            widths: [5, "*"],
            body: [[
                { text: "", fillColor: accent, border: [false, false, false, false] },
                {
                    stack: [label(title, accent), { text: text(body), color: C.ink, lineHeight: 1.2 }],
                    fillColor: fill,
                    margin: [10, 9, 10, 9],
                    border: [false, false, false, false],
                },
            ]],
        },
        layout: "noBorders",
        margin: [0, 2, 0, 10],
    };
}

function questionBlocks(questions) {
    const normalized = Array.isArray(questions) ? questions : [];
    if (!normalized.length) return [{ text: "Sin preguntas adicionales.", color: C.muted, italics: true }];
    return normalized.map((question, index) => ({
        stack: [
            { text: `${index + 1}. ${text(question.prompt)}`, bold: true, color: C.ink, margin: [0, 0, 0, 3] },
            { text: `Ideas esperadas: ${values(question.expected_ideas).join("; ") || "respuesta abierta fundamentada"}`, fontSize: 9, color: C.muted },
            { text: `Repregunta: ${text(question.follow_up)}`, fontSize: 9, italics: true, color: C.primary, margin: [0, 2, 0, 7] },
        ],
    }));
}

function misconceptionBlocks(items) {
    const normalized = Array.isArray(items) ? items : [];
    if (!normalized.length) return [{ text: "Sin dificultades anticipadas específicas.", color: C.muted, italics: true }];
    return normalized.map((item) => ({
        table: {
            widths: ["31%", "69%"],
            body: [[
                { text: text(item.signal), bold: true, color: C.accentAlt, fillColor: "#FFF4F3", margin: [6, 5, 6, 5] },
                { text: text(item.response), color: C.ink, fillColor: "#FFF9F8", margin: [6, 5, 6, 5] },
            ]],
        },
        layout: {
            hLineColor: () => "#F1D7D3",
            vLineColor: () => "#F1D7D3",
        },
        margin: [0, 0, 0, 6],
    }));
}

const title = text(payload.title || guide.title || deck.metadata?.title, "Clase");
const guideTitle = text(guide.title, `Guía docente: ${title}`);
const school = text(snapshot.school?.name, "Institución educativa");
const course = text(deck.metadata?.course || snapshot.course?.name, "Curso");
const subject = text(deck.metadata?.subject || snapshot.subject?.name, "Asignatura");
const unit = text(deck.metadata?.unit || snapshot.unit?.title, "Unidad");
const duration = Number(deck.metadata?.duration_minutes || configuration.duration_minutes || 0);
const objectives = Array.isArray(guide.at_a_glance?.curricular_alignment)
    ? guide.at_a_glance.curricular_alignment
    : (snapshot.objectives || []).map((objective) => ({ ...objective, evidence: "Evidencia registrada en la secuencia." }));

const content = [];

content.push({
    stack: [
        { canvas: [{ type: "rect", x: 0, y: 0, w: 511, h: 14, color: C.secondary }], margin: [0, -34, 0, 44] },
        label("Guía pedagógica para la implementación", C.secondary),
        { text: "GUÍA DOCENTE", fontSize: 32, bold: true, color: C.primary, margin: [0, 2, 0, 16] },
        { text: title, fontSize: 25, bold: true, color: C.ink, lineHeight: 1.04, margin: [0, 0, 0, 12] },
        { text: text(deck.metadata?.subtitle), fontSize: 14, color: C.muted, lineHeight: 1.18, margin: [0, 0, 0, 28] },
        {
            table: {
                widths: ["*", "*"],
                body: [
                    [
                        { stack: [label("Curso"), { text: course, bold: true, fontSize: 12 }], fillColor: C.background, margin: [10, 9, 10, 9] },
                        { stack: [label("Asignatura"), { text: subject, bold: true, fontSize: 12 }], fillColor: C.background, margin: [10, 9, 10, 9] },
                    ],
                    [
                        { stack: [label("Unidad"), { text: unit, bold: true, fontSize: 11 }], fillColor: C.background, margin: [10, 9, 10, 9] },
                        { stack: [label("Duración"), { text: `${duration} minutos`, bold: true, fontSize: 12 }], fillColor: C.background, margin: [10, 9, 10, 9] },
                    ],
                ],
            },
            layout: {
                hLineColor: () => C.line,
                vLineColor: () => C.line,
            },
            margin: [0, 0, 0, 24],
        },
        callout("Mensaje central", guide.at_a_glance?.central_message || deck.metadata?.central_message, C.soft, C.secondary),
        heading("Objetivos curriculares"),
        ...objectives.map((objective) => ({
            table: {
                widths: [78, "*"],
                body: [[
                    { text: text(objective.code, "OA"), bold: true, color: C.white, fillColor: C.primary, margin: [7, 6, 7, 6], alignment: "center", border: [false, false, false, false] },
                    { text: text(objective.description), color: C.ink, margin: [10, 5, 0, 5], border: [false, false, false, false] },
                ]],
            },
            layout: "noBorders",
            margin: [0, 0, 0, 5],
        })),
        { text: `${school}  |  Versión ${Number(payload.version || 1)}`, fontSize: 8.5, color: C.muted, margin: [0, 26, 0, 0] },
    ],
    pageBreak: "after",
});

const glance = guide.at_a_glance || {};
content.push({
    stack: [
        heading("La clase de un vistazo", { large: true, margin: [0, 0, 0, 10] }),
        callout("Propósito", glance.purpose, "#F3F7FB", C.primary),
        heading("Preparación previa"),
        {
            columns: [
                { width: "48%", stack: [label("Materiales"), list(glance.preparation?.materials), label("Antes de la clase", C.primary), list(glance.preparation?.before_class)] },
                { width: "48%", stack: [label("Organización del espacio"), list(glance.preparation?.room_setup), label("Seguridad y privacidad", C.accentAlt), list(glance.preparation?.safety_and_privacy)] },
            ],
            columnGap: 18,
        },
        heading("Conocimientos previos y vocabulario"),
        list(glance.prior_knowledge),
        {
            table: {
                headerRows: 1,
                widths: [92, "*", "*"],
                body: [
                    [
                        { text: "Término", style: "tableHeader" },
                        { text: "Definición docente", style: "tableHeader" },
                        { text: "Ejemplo", style: "tableHeader" },
                    ],
                    ...(Array.isArray(glance.key_vocabulary) ? glance.key_vocabulary : []).map((item) => [
                        { text: text(item.term), bold: true, color: C.primary, margin: [5, 4, 5, 4] },
                        { text: text(item.teacher_definition), margin: [5, 4, 5, 4] },
                        { text: text(item.example), margin: [5, 4, 5, 4] },
                    ]),
                ],
            },
            layout: {
                fillColor: (rowIndex) => rowIndex === 0 ? C.primary : (rowIndex % 2 ? "#F8FAFC" : C.white),
                hLineColor: () => C.line,
                vLineColor: () => C.line,
            },
            margin: [0, 0, 0, 12],
        },
        heading("Línea de tiempo"),
        {
            table: {
                headerRows: 1,
                widths: [70, 42, 60, "*"],
                body: [
                    [
                        { text: "Momento", style: "tableHeader" },
                        { text: "Min", style: "tableHeader", alignment: "center" },
                        { text: "Slides", style: "tableHeader", alignment: "center" },
                        { text: "Foco docente", style: "tableHeader" },
                    ],
                    ...(Array.isArray(guide.timeline) ? guide.timeline : []).map((phase) => [
                        { text: text(phase.title || phase.phase), bold: true, color: C.primary, margin: [5, 4, 5, 4] },
                        { text: String(Number(phase.minutes || 0)), alignment: "center", margin: [5, 4, 5, 4] },
                        { text: values(phase.slide_numbers).join(", "), alignment: "center", margin: [5, 4, 5, 4] },
                        { text: text(phase.focus), margin: [5, 4, 5, 4] },
                    ]),
                ],
            },
            layout: {
                hLineColor: () => C.line,
                vLineColor: () => C.line,
            },
        },
    ],
});

const slidesByNumber = new Map((deck.slides || []).map((slide) => [Number(slide.number), slide]));
(guide.slide_script || []).forEach((entry) => {
    const number = Number(entry.slide_number || 0);
    const slide = slidesByNumber.get(number) || {};
    const note = text(slide.speaker_notes);
    content.push({
        pageBreak: "before",
        stack: [
            {
                table: {
                    widths: ["*", 58],
                    body: [[
                        {
                            stack: [
                                label(`Diapositiva ${number}`, C.white),
                                { text: text(slide.title, `Diapositiva ${number}`), fontSize: 20, bold: true, color: C.white, lineHeight: 1.05 },
                            ],
                            fillColor: C.primary,
                            margin: [10, 9, 10, 9],
                            border: [false, false, false, false],
                        },
                        { text: `${Number(entry.minutes || 0)} min`, fontSize: 11, bold: true, color: C.primary, fillColor: C.white, alignment: "center", margin: [5, 19, 5, 9], border: [false, false, false, false] },
                    ]],
                },
                layout: "noBorders",
                margin: [-8, -8, -8, 14],
            },
            callout("Propósito de esta diapositiva", entry.purpose, "#F3F7FB", C.primary),
            heading("Qué decir"),
            { text: text(entry.teacher_script), fontSize: 11, color: C.ink, lineHeight: 1.25, margin: [0, 0, 0, 10] },
            ...(note ? [callout("Nota breve integrada en la presentación", note, "#FFF8EC", C.accent)] : []),
            {
                columns: [
                    {
                        width: "48%",
                        stack: [
                            heading("Qué hacer", { margin: [0, 6, 0, 6] }),
                            list(entry.teacher_actions),
                            heading("Preguntas y andamiaje", { margin: [0, 8, 0, 6] }),
                            ...questionBlocks(entry.questions),
                        ],
                    },
                    {
                        width: "48%",
                        stack: [
                            heading("Qué observar", { margin: [0, 6, 0, 6] }),
                            list(entry.evidence_to_observe),
                            heading("Dificultades previsibles", { margin: [0, 8, 0, 6] }),
                            ...misconceptionBlocks(entry.misconceptions),
                        ],
                    },
                ],
                columnGap: 18,
            },
            callout("Transición", entry.transition, C.soft, C.secondary),
        ],
    });
});

(guide.activity_support || []).forEach((activity, index) => {
    content.push({
        pageBreak: "before",
        stack: [
            heading(`Facilitación de la actividad ${index + 1}`, { large: true, margin: [0, 0, 0, 10] }),
            callout(`Diapositiva ${activity.slide_number} - preparación`, activity.setup, "#F3F7FB", C.primary),
            {
                columns: [
                    { width: "50%", stack: [label("Agrupamiento"), { text: text(activity.grouping), margin: [0, 0, 0, 8] }] },
                    { width: "50%", stack: [label("Tiempo"), { text: `${Number(activity.time_minutes || 0)} minutos`, bold: true, color: C.primary }] },
                ],
            },
            heading("Pasos de facilitación"),
            list(activity.facilitation_steps),
            heading("Preguntas para monitorear"),
            list(activity.monitoring_prompts),
            heading("Evidencia esperada"),
            list(activity.expected_evidence),
            callout("Plan de contingencia", activity.contingency, "#FFF8EC", C.accent),
        ],
    });
});

(guide.assessment_support || []).forEach((assessment, index) => {
    content.push({
        pageBreak: "before",
        stack: [
            heading(`Guía de evaluación ${index + 1}`, { large: true, margin: [0, 0, 0, 10] }),
            callout(`Diapositiva ${assessment.slide_number} - aplicación`, assessment.administration, "#F3F7FB", C.primary),
            {
                columns: [
                    { width: "48%", stack: [heading("Evidencia esperada"), list(assessment.expected_evidence)] },
                    { width: "48%", stack: [heading("Criterios de éxito"), list(assessment.success_criteria)] },
                ],
                columnGap: 18,
            },
            heading("Retroalimentación durante la tarea"),
            list(assessment.feedback_prompts),
            heading("Próximos pasos"),
            {
                table: {
                    widths: [92, "*"],
                    body: [
                        [{ text: "Requiere apoyo", style: "nextStepLabel" }, { text: text(assessment.next_steps?.needs_support), margin: [7, 6, 7, 6] }],
                        [{ text: "Logro esperado", style: "nextStepLabel" }, { text: text(assessment.next_steps?.ready), margin: [7, 6, 7, 6] }],
                        [{ text: "Extensión", style: "nextStepLabel" }, { text: text(assessment.next_steps?.extension), margin: [7, 6, 7, 6] }],
                    ],
                },
                layout: {
                    hLineColor: () => C.line,
                    vLineColor: () => C.line,
                },
            },
        ],
    });
});

content.push({
    pageBreak: "before",
    stack: [
        heading("Inclusión, apoyos y profundización", { large: true, margin: [0, 0, 0, 10] }),
        {
            columns: [
                { width: "48%", stack: [label("Acceso"), list(guide.differentiation?.access), label("Participación", C.primary), list(guide.differentiation?.participation), label("Formas de expresión", C.primary), list(guide.differentiation?.expression)] },
                { width: "48%", stack: [label("Apoyo adicional", C.accentAlt), list(guide.differentiation?.support), label("Extensión", C.secondary), list(guide.differentiation?.extension)] },
            ],
            columnGap: 18,
        },
        heading("Cierre docente"),
        callout("Guion de cierre", guide.closure?.closing_script, C.soft, C.secondary),
        callout("Síntesis formativa", guide.closure?.formative_summary, "#F3F7FB", C.primary),
        heading("Continuidad"),
        list(guide.closure?.follow_up),
    ],
});
content.push({ text: "\u200B", fontSize: 1, pageBreak: "after", margin: [0, 0, 0, 0] });

const sources = Array.isArray(guide.sources) && guide.sources.length ? guide.sources : (deck.bibliography || []);
content.push({
    stack: [
        heading("Fuentes y verificación", { large: true, margin: [0, 0, 0, 12] }),
        ...sources.map((source, index) => ({
            stack: [
                { text: `${index + 1}. ${text(source.title, "Fuente")}`, bold: true, color: C.primary },
                { text: [text(source.publisher), text(source.url)].filter(Boolean).join(" | "), fontSize: 9, color: C.muted, margin: [0, 2, 0, 9] },
            ],
        })),
        heading("Advertencias de verificación"),
        list(guide.verification_warnings, "No se registraron advertencias de verificación."),
        { text: "Esta guía acompaña la presentación y debe aplicarse con criterio profesional, considerando las necesidades reales del curso.", fontSize: 9, italics: true, color: C.muted, margin: [0, 24, 0, 0] },
    ],
});

const documentDefinition = {
    pageSize: "A4",
    pageMargins: [42, 62, 42, 48],
    info: {
        title: guideTitle,
        author: text(snapshot.author?.name, school),
        subject: `${subject} - ${course}`,
        keywords: `guía docente, ${subject}, ${course}`,
        creator: "Generador de clases",
        producer: "Generador de clases",
    },
    background: (currentPage) => currentPage === 1 ? null : ({
        canvas: [
            { type: "rect", x: 0, y: 0, w: 595.28, h: 10, color: C.primary },
            { type: "rect", x: 0, y: 10, w: 595.28, h: 3, color: C.secondary },
        ],
    }),
    header: (currentPage) => currentPage === 1 ? null : ({
        columns: [
            { text: school, fontSize: 8, bold: true, color: C.primary },
            { text: `${subject} | ${course}`, fontSize: 8, color: C.muted, alignment: "right" },
        ],
        margin: [42, 28, 42, 0],
    }),
    footer: (currentPage, pageCount) => ({
        columns: [
            { text: "Guía docente", fontSize: 8, color: C.muted },
            { text: `${currentPage} / ${pageCount}`, fontSize: 8, bold: true, color: C.primary, alignment: "right" },
        ],
        margin: [42, 16, 42, 0],
    }),
    content,
    defaultStyle: {
        font: "Roboto",
        fontSize: 10,
        color: C.ink,
        lineHeight: 1.15,
    },
    styles: {
        sectionTitleLarge: { fontSize: 19, bold: true, color: C.primary },
        sectionTitle: { fontSize: 13, bold: true, color: C.primary },
        tableHeader: { fontSize: 8.5, bold: true, color: C.white, fillColor: C.primary, margin: [5, 5, 5, 5] },
        nextStepLabel: { fontSize: 9, bold: true, color: C.primary, fillColor: C.soft, margin: [7, 6, 7, 6] },
    },
};

const buffer = await new Promise((resolve, reject) => {
    try {
        pdfMake.createPdf(documentDefinition).getBuffer(resolve);
    } catch (error) {
        reject(error);
    }
});

await fs.mkdir(path.dirname(outputPath), { recursive: true });
await fs.writeFile(outputPath, buffer);
