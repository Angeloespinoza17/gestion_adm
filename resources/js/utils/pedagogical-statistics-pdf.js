import { getPdfMake } from "./pdfmake";

const colors = Object.freeze({
    navy: "#173D5E",
    navyDark: "#102D47",
    teal: "#238D84",
    tealDark: "#176B70",
    tealSoft: "#E8F5F3",
    blueSoft: "#EAF1F6",
    ink: "#203149",
    muted: "#708095",
    line: "#DCE5EA",
    surface: "#F5F8FA",
    page: "#FBFCFD",
    green: "#258267",
    greenSoft: "#E5F4EE",
    amber: "#A66A1D",
    amberSoft: "#FFF2DC",
    rose: "#B84D58",
    roseSoft: "#FBEAEC",
    violet: "#6868A5",
    violetSoft: "#EFEFFA",
    white: "#FFFFFF",
});

const numeric = (value) => {
    if (value === null || value === undefined || value === "") return null;
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
};

const number = (value) =>
    new Intl.NumberFormat("es-CL").format(numeric(value) ?? 0);

const countLabel = (value, singular, plural = `${singular}s`) => {
    const count = numeric(value) ?? 0;
    return `${number(count)} ${count === 1 ? singular : plural}`;
};

const percentage = (value) => {
    const parsed = numeric(value);
    return parsed === null
        ? "Sin dato"
        : `${parsed.toLocaleString("es-CL", { maximumFractionDigits: 1 })}%`;
};

const delta = (value) => {
    const parsed = numeric(value);
    if (parsed === null) return "Sin comparación";
    return `${parsed > 0 ? "+" : ""}${parsed.toLocaleString("es-CL", {
        maximumFractionDigits: 1,
    })} pp`;
};

const days = (value) => {
    const parsed = numeric(value);
    return parsed === null
        ? "Sin dato"
        : `${parsed.toLocaleString("es-CL", { maximumFractionDigits: 1 })} días`;
};

const localDateStamp = (input = new Date()) => {
    const date = input instanceof Date ? input : new Date(input);
    const valid = Number.isNaN(date.getTime()) ? new Date() : date;
    return [valid.getFullYear(), valid.getMonth() + 1, valid.getDate()]
        .map((part, index) => String(part).padStart(index ? 2 : 4, "0"))
        .join("-");
};

const dateTime = (input) => {
    const date = input ? new Date(input) : new Date();
    const valid = Number.isNaN(date.getTime()) ? new Date() : date;
    return new Intl.DateTimeFormat("es-CL", {
        dateStyle: "long",
        timeStyle: "short",
    }).format(valid);
};

const safeFilenamePart = (input) =>
    String(input || "establecimiento")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/[^a-zA-Z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "")
        .toLowerCase()
        .slice(0, 55) || "establecimiento";

const escapeSvg = (input) =>
    String(input ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&apos;");

const shorten = (input, length = 48) => {
    const text = String(input || "Sin información");
    return text.length > length ? `${text.slice(0, length - 3)}...` : text;
};

const metricCard = (label, value, note, accent, fillColor) => ({
    table: {
        widths: ["*"],
        body: [[{
            stack: [
                { text: label.toUpperCase(), style: "metricLabel" },
                { text: value, color: accent, bold: true, fontSize: 16, margin: [0, 4, 0, 3] },
                { text: note, color: colors.muted, fontSize: 6.4, lineHeight: 1.15 },
            ],
            fillColor,
            margin: [10, 9, 10, 9],
        }]],
    },
    layout: {
        hLineColor: () => colors.line,
        vLineColor: () => colors.line,
        hLineWidth: () => 0.6,
        vLineWidth: () => 0.6,
    },
});

const sectionHeader = (kicker, title, description = "", accent = colors.teal) => ({
    unbreakable: true,
    stack: [
        {
            columns: [
                { width: 22, canvas: [{ type: "rect", x: 0, y: 2, w: 17, h: 3, color: accent }] },
                { width: "*", text: kicker.toUpperCase(), color: accent, bold: true, fontSize: 7, characterSpacing: 0.8 },
            ],
            columnGap: 2,
        },
        { text: title, color: colors.ink, bold: true, fontSize: 13.5, margin: [24, 4, 0, 0] },
        description
            ? { text: description, color: colors.muted, fontSize: 7.2, lineHeight: 1.25, margin: [24, 3, 0, 0] }
            : null,
    ].filter(Boolean),
    margin: [0, 3, 0, 10],
});

const statusLabel = (decision) => ({
    approved: "Aprobado",
    approved_with_observations: "Aprobado con observaciones",
    rectification_requested: "Rectificación solicitada",
})[decision] || decision || "Sin resolución";

const scopeText = (context) => {
    const labels = context.filter_labels || [];
    return labels.length ? labels.join("  /  ") : "Todos los registros autorizados";
};

const executiveNarrative = (dashboard) => {
    const summary = dashboard.summary || {};
    const priorities = dashboard.priorities || [];
    const leading = priorities[0];
    const reportCount = numeric(summary.official_reports) ?? 0;
    const score = percentage(summary.current_compliance_percentage);
    const evidence = percentage(summary.evidence_coverage_percentage);
    const improvement = numeric(summary.median_improvement_pp);
    const evolutionClause = improvement === null
        ? "sin una comparación entre versiones todavía disponible"
        : `con una evolución mediana de ${delta(improvement)} entre versiones comparables`;
    const priorityCriterion = String(leading?.criterion || "").replace(/[.,;:!?]+$/, "");
    const priority = leading
        ? `El principal foco de acompañamiento corresponde al criterio ${leading.code}: ${priorityCriterion}, con un índice de oportunidad de ${Number(leading.opportunity_index || 0).toLocaleString("es-CL", { maximumFractionDigits: 1 })}.`
        : "Aún no existe una muestra suficiente para identificar un foco prioritario de la pauta.";

    return `El período analizado reúne ${countLabel(reportCount, "informe oficial", "informes oficiales")} ${reportCount === 1 ? "correspondiente" : "correspondientes"} a ${countLabel(summary.instruments, "instrumento")} de ${countLabel(summary.teachers, "docente")}. El ajuste mediano de la versión más reciente es ${score}, ${evolutionClause}, y la cobertura de evidencia alcanza ${evidence}. ${priority}`;
};

const trendSvg = (items = []) => {
    const rows = items.slice(-12);
    const width = 507;
    const height = 210;
    const left = 42;
    const right = 25;
    const top = 35;
    const bottom = 38;
    const plotWidth = width - left - right;
    const plotHeight = height - top - bottom;
    const maxReports = Math.max(1, ...rows.map((item) => Number(item.reports || 0)));
    const step = rows.length > 1 ? plotWidth / (rows.length - 1) : 0;
    const xAt = (index) => rows.length > 1 ? left + index * step : left + plotWidth / 2;
    const yAt = (value) => top + plotHeight - (Math.max(0, Math.min(100, Number(value || 0))) / 100) * plotHeight;
    const linePath = (key) => rows
        .map((item, index) => `${index ? "L" : "M"}${xAt(index).toFixed(1)},${yAt(item[key]).toFixed(1)}`)
        .join(" ");
    const grid = [0, 25, 50, 75, 100].map((tick) => {
        const y = yAt(tick);
        return `<line x1="${left}" y1="${y}" x2="${width - right}" y2="${y}" stroke="#DFE7EB" stroke-dasharray="3 4"/><text x="${left - 8}" y="${y + 3}" text-anchor="end" font-size="7" fill="#7C8998">${tick}%</text>`;
    }).join("");
    const columns = rows.map((item, index) => {
        const barWidth = Math.min(22, Math.max(8, plotWidth / Math.max(1, rows.length) * 0.42));
        const barHeight = Math.max(3, (Number(item.reports || 0) / maxReports) * plotHeight * 0.62);
        const x = xAt(index) - barWidth / 2;
        return `<rect x="${x}" y="${top + plotHeight - barHeight}" width="${barWidth}" height="${barHeight}" rx="3" fill="#D7E2E8"/>`;
    }).join("");
    const labels = rows.map((item, index) => `<text x="${xAt(index)}" y="${height - 16}" text-anchor="middle" font-size="6.5" fill="#748296">${escapeSvg(shorten(item.label, 10))}</text>`).join("");
    const points = (key, color) => rows.map((item, index) => `<circle cx="${xAt(index)}" cy="${yAt(item[key])}" r="2.6" fill="${color}" stroke="#FFFFFF" stroke-width="1"/>`).join("");
    const empty = rows.length
        ? ""
        : `<text x="${width / 2}" y="${height / 2}" text-anchor="middle" font-size="9" fill="#8190A0">Sin serie temporal para el período</text>`;

    return `<svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" xmlns="http://www.w3.org/2000/svg"><rect width="${width}" height="${height}" rx="12" fill="#F7FAFB"/>${grid}${columns}${rows.length ? `<path d="${linePath("median_compliance")}" fill="none" stroke="${colors.navy}" stroke-width="2.5"/><path d="${linePath("evidence_coverage")}" fill="none" stroke="${colors.teal}" stroke-width="2.1"/>${points("median_compliance", colors.navy)}${points("evidence_coverage", colors.teal)}` : ""}${labels}${empty}<circle cx="310" cy="17" r="3" fill="${colors.navy}"/><text x="318" y="20" font-size="7" fill="#536176">Ajuste mediano</text><circle cx="397" cy="17" r="3" fill="${colors.teal}"/><text x="405" y="20" font-size="7" fill="#536176">Evidencia</text></svg>`;
};

const dimensionsSvg = (items = []) => {
    const rows = items.slice(0, 10);
    const width = 507;
    const rowHeight = 33;
    const height = Math.max(90, 45 + rows.length * rowHeight);
    const labelWidth = 168;
    const chartWidth = 310;
    const content = rows.map((item, index) => {
        const y = 38 + index * rowHeight;
        const first = Math.max(0, Math.min(100, Number(item.first_score || 0)));
        const latest = Math.max(0, Math.min(100, Number(item.latest_score || 0)));
        return `<text x="12" y="${y + 7}" font-size="7" font-weight="600" fill="#405066">${escapeSvg(shorten(item.dimension, 31))}</text><rect x="${labelWidth}" y="${y}" width="${chartWidth}" height="6" rx="3" fill="#E5EBEF"/><rect x="${labelWidth}" y="${y}" width="${chartWidth * first / 100}" height="6" rx="3" fill="#AAB9C5"/><rect x="${labelWidth}" y="${y + 10}" width="${chartWidth}" height="8" rx="4" fill="#E5EBEF"/><rect x="${labelWidth}" y="${y + 10}" width="${chartWidth * latest / 100}" height="8" rx="4" fill="${colors.teal}"/><text x="${width - 7}" y="${y + 18}" text-anchor="end" font-size="6.5" font-weight="700" fill="${colors.tealDark}">${percentage(item.latest_score)}</text>`;
    }).join("");
    const empty = rows.length
        ? ""
        : `<text x="${width / 2}" y="54" text-anchor="middle" font-size="9" fill="#8190A0">Sin dimensiones comparables</text>`;
    return `<svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" xmlns="http://www.w3.org/2000/svg"><rect width="${width}" height="${height}" rx="12" fill="#F7FAFB"/><rect x="342" y="15" width="12" height="5" rx="2" fill="#AAB9C5"/><text x="359" y="20" font-size="7" fill="#667589">Primera versión</text><rect x="425" y="14" width="12" height="7" rx="3" fill="${colors.teal}"/><text x="442" y="20" font-size="7" fill="#667589">Última</text>${content}${empty}</svg>`;
};

const distributionTable = (dashboard) => {
    const total = Math.max(1, Number(dashboard.summary?.official_reports || 0));
    const rows = (dashboard.decisions || []).map((item) => {
        const share = (Number(item.count || 0) / total) * 100;
        return [
            { text: item.label, color: colors.ink, bold: true, fontSize: 7.2 },
            {
                stack: [
                    { canvas: [{ type: "rect", x: 0, y: 2, w: 210, h: 7, r: 3.5, color: colors.line }, { type: "rect", x: 0, y: 2, w: Math.max(2, 210 * share / 100), h: 7, r: 3.5, color: item.decision === "approved" ? colors.green : item.decision === "approved_with_observations" ? colors.violet : colors.amber }] },
                ],
                margin: [0, 2, 0, 0],
            },
            { text: number(item.count), alignment: "right", color: colors.navy, bold: true, fontSize: 8 },
            { text: percentage(share), alignment: "right", color: colors.muted, fontSize: 6.8 },
        ];
    });
    return {
        table: {
            headerRows: 1,
            widths: [115, "*", 38, 42],
            body: [
                ["Resolución", "Distribución", "Total", "%"].map((text) => ({ text, style: "tableHeader" })),
                ...(rows.length ? rows : [[{ text: "Sin resoluciones", colSpan: 4, color: colors.muted, italics: true }, {}, {}, {}]]),
            ],
        },
        layout: "lightHorizontalLines",
    };
};

const prioritiesTable = (items = []) => ({
    table: {
        headerRows: 1,
        widths: [28, 38, "*", 48, 48, 48, 42],
        body: [
            ["#", "Código", "Criterio y dimensión", "Atención", "Persist.", "Alcance", "Índice"].map((text) => ({ text, style: "tableHeader" })),
            ...(items.length ? items.slice(0, 6).map((item, index) => [
                { text: String(index + 1).padStart(2, "0"), color: colors.teal, bold: true, alignment: "center" },
                { text: item.code, color: colors.navy, bold: true },
                { stack: [{ text: item.criterion, color: colors.ink, bold: true }, { text: item.dimension, color: colors.muted, fontSize: 6.1, margin: [0, 2, 0, 0] }] },
                { text: percentage(item.attention_rate), alignment: "right" },
                { text: percentage(item.persistence_rate), alignment: "right" },
                { text: percentage(item.reach_rate), alignment: "right" },
                { text: Number(item.opportunity_index || 0).toLocaleString("es-CL", { maximumFractionDigits: 1 }), alignment: "center", bold: true, color: item.sample_sufficient ? colors.tealDark : colors.amber, fillColor: item.sample_sufficient ? colors.tealSoft : colors.amberSoft },
            ]) : [[{ text: "Sin datos suficientes para priorizar", colSpan: 7, color: colors.muted, italics: true }, {}, {}, {}, {}, {}, {}]]),
        ],
    },
    layout: "lightHorizontalLines",
});

const criteriaTable = (items = []) => ({
    table: {
        headerRows: 1,
        widths: [30, "*", 34, 34, 34, 36, 38, 38, 38],
        body: [
            ["Código", "Criterio", "Cumple", "Parcial", "No cumple", "Sin evid.", "Persist.", "Resuelve", "Índice"].map((text) => ({ text, style: "tableHeader", fontSize: 5.8 })),
            ...(items.length ? items.map((item) => [
                { text: item.code, color: colors.tealDark, bold: true, alignment: "center" },
                { stack: [{ text: item.criterion, color: colors.ink, bold: true, lineHeight: 1.1 }, { text: `${item.dimension}  /  n=${number(item.applicable)}`, color: colors.muted, fontSize: 5.5, margin: [0, 2, 0, 0] }] },
                { text: number(item.meets), alignment: "center", color: colors.green },
                { text: number(item.partially_meets), alignment: "center", color: colors.amber },
                { text: number(item.does_not_meet), alignment: "center", color: colors.rose },
                { text: number(item.not_evidenced), alignment: "center", color: colors.muted },
                { text: percentage(item.persistence_rate), alignment: "right" },
                { text: percentage(item.resolution_rate), alignment: "right", color: colors.green },
                { text: Number(item.opportunity_index || 0).toLocaleString("es-CL", { maximumFractionDigits: 1 }), alignment: "center", bold: true, color: item.sample_sufficient ? colors.tealDark : colors.amber, fillColor: item.sample_sufficient ? colors.tealSoft : colors.amberSoft },
            ]) : [[{ text: "Sin criterios disponibles", colSpan: 9, color: colors.muted, italics: true }, {}, {}, {}, {}, {}, {}, {}, {}]]),
        ],
    },
    layout: {
        fillColor: (rowIndex) => rowIndex > 0 && rowIndex % 2 === 0 ? "#F8FAFB" : null,
        hLineColor: () => colors.line,
        vLineColor: () => colors.line,
        hLineWidth: () => 0.45,
        vLineWidth: () => 0.35,
        paddingLeft: () => 4,
        paddingRight: () => 4,
        paddingTop: () => 5,
        paddingBottom: () => 5,
    },
    fontSize: 6.2,
});

const miscellaneousTable = (items = []) => ({
    table: {
        headerRows: 1,
        widths: ["*", 45, 53, 53, 45, 45, 45],
        body: [
            ["Categoría", "Hallazgos", "Informes", "Docentes", "Críticos", "Import.", "Suger."].map((text) => ({ text, style: "tableHeader", fontSize: 6.2 })),
            ...(items.length ? items.map((item) => [
                { text: item.label, color: colors.ink, bold: true },
                { text: number(item.findings), alignment: "center" },
                { text: `${number(item.affected_reports)} (${percentage(item.incidence_rate)})`, alignment: "center" },
                { text: number(item.affected_teachers), alignment: "center" },
                { text: number(item.critical), alignment: "center", color: colors.rose },
                { text: number(item.important), alignment: "center", color: colors.amber },
                { text: number(item.suggestion), alignment: "center", color: colors.teal },
            ]) : [[{ text: "Sin hallazgos misceláneos", colSpan: 7, color: colors.green, italics: true }, {}, {}, {}, {}, {}, {}]]),
        ],
    },
    layout: "lightHorizontalLines",
});

const teachersTable = (items = []) => ({
    table: {
        headerRows: 1,
        widths: ["*", 42, 46, 46, 48, 55, 76],
        body: [
            ["Docente", "Instr.", "Inicial", "Actual", "Evolución", "Aprob. inicial", "Persistencia"].map((text) => ({ text, style: "tableHeader", fontSize: 6.1 })),
            ...(items.length ? items.slice(0, 25).map((item) => [
                { stack: [{ text: item.name, color: colors.ink, bold: true }, { text: `${number(item.reports)} informes`, color: colors.muted, fontSize: 5.7 }] },
                { text: number(item.instruments), alignment: "center" },
                { text: percentage(item.first_score), alignment: "right" },
                { text: percentage(item.latest_score), alignment: "right", bold: true },
                { text: delta(item.improvement_pp), alignment: "right", color: Number(item.improvement_pp || 0) < 0 ? colors.rose : colors.green },
                { text: percentage(item.first_pass_approval_rate), alignment: "right" },
                { text: item.persistent_criteria?.join(", ") || "Sin persistencia comparable", color: item.persistent_criteria?.length ? colors.amber : colors.muted, fontSize: 6 },
            ]) : [[{ text: "Sin trayectorias docentes", colSpan: 7, color: colors.muted, italics: true }, {}, {}, {}, {}, {}, {}]]),
        ],
    },
    layout: "lightHorizontalLines",
    fontSize: 6.4,
});

const instrumentsTable = (items = []) => ({
    table: {
        headerRows: 1,
        widths: ["*", 86, 78, 35, 48, 55, 82],
        body: [
            ["Instrumento", "Docente", "Asignatura / curso", "Ver.", "Ajuste", "Evolución", "Resolución"].map((text) => ({ text, style: "tableHeader", fontSize: 5.9 })),
            ...(items.length ? items.slice(0, 30).map((item) => [
                { text: item.title, color: colors.ink, bold: true },
                { text: item.teacher?.name || "Sin docente" },
                { stack: [{ text: item.subject?.name || "Sin asignatura", bold: true }, { text: item.courses?.map((course) => course.name).join(", ") || "Sin curso", color: colors.muted, fontSize: 5.5 }] },
                { text: number(item.reviewed_versions), alignment: "center" },
                { text: percentage(item.latest_score), alignment: "right", bold: true },
                { text: item.rubric_compatible ? delta(item.improvement_pp) : "Pauta distinta", alignment: "right", color: item.rubric_compatible ? Number(item.improvement_pp || 0) < 0 ? colors.rose : colors.green : colors.amber },
                { text: statusLabel(item.latest_decision), color: item.latest_decision === "rectification_requested" ? colors.amber : colors.tealDark, fontSize: 5.9, bold: true },
            ]) : [[{ text: "Sin trayectorias de instrumentos", colSpan: 7, color: colors.muted, italics: true }, {}, {}, {}, {}, {}, {}]]),
        ],
    },
    layout: "lightHorizontalLines",
    fontSize: 6.1,
});

export function buildPedagogicalStatisticsPdfDefinition(dashboard, context = {}) {
    const summary = dashboard?.summary || {};
    const meta = dashboard?.meta || {};
    const schoolName = meta.school?.name || context.school_name || "Establecimiento actual";
    const generatedAt = context.generated_at || new Date();
    const sampleMessage = meta.sample_sufficient
        ? `La muestra cumple los mínimos metodológicos de ${countLabel(meta.minimum_reports, "informe")} y ${countLabel(meta.minimum_comparable_pairs, "par comparable", "pares comparables")}.`
        : `Lectura exploratoria: hay ${countLabel(meta.report_count, "informe")} y ${countLabel(meta.comparable_pairs, "par comparable", "pares comparables")}. Se requieren al menos ${countLabel(meta.minimum_reports, "informe")} y ${countLabel(meta.minimum_comparable_pairs, "par", "pares")} para conclusiones firmes.`;

    return {
        pageSize: "A4",
        pageMargins: [44, 42, 44, 52],
        background: () => ({ canvas: [{ type: "rect", x: 0, y: 0, w: 595.28, h: 841.89, color: colors.page }] }),
        info: {
            title: `Informe de evolución documental - ${schoolName}`,
            subject: "Estadísticas de revisión de instrumentos de evaluación",
            author: schoolName,
            creator: "Gestión pedagógica",
        },
        footer: (currentPage, pageCount) => ({
            margin: [44, 5, 44, 0],
            stack: [
                { canvas: [{ type: "line", x1: 0, y1: 0, x2: 507, y2: 0, lineWidth: 0.6, lineColor: colors.line }] },
                {
                    columns: [
                        { text: "GESTIÓN PEDAGÓGICA  /  EVOLUCIÓN DOCUMENTAL", color: colors.muted, fontSize: 6, bold: true, characterSpacing: 0.3, margin: [0, 7, 0, 0] },
                        { text: `${String(currentPage).padStart(2, "0")}  /  ${String(pageCount).padStart(2, "0")}`, color: colors.teal, fontSize: 6.4, bold: true, alignment: "right", margin: [0, 7, 0, 0] },
                    ],
                },
            ],
        }),
        content: [
            {
                table: {
                    widths: ["*", 118],
                    body: [[
                        {
                            stack: [
                                { text: "INTELIGENCIA PEDAGÓGICA", color: "#9CDED7", bold: true, fontSize: 7.1, characterSpacing: 1.15 },
                                { text: "Informe de evolución documental", color: colors.white, bold: true, fontSize: 20, lineHeight: 1.05, margin: [0, 9, 0, 7] },
                                { text: schoolName, color: "#DDEAF0", fontSize: 8.2 },
                            ],
                            fillColor: colors.navy,
                            margin: [18, 16, 16, 17],
                        },
                        {
                            stack: [
                                { text: "INFORMES", alignment: "center", color: "#C7ECE7", bold: true, fontSize: 6.4, characterSpacing: 0.8 },
                                { text: number(summary.official_reports), alignment: "center", color: colors.white, bold: true, fontSize: 27, margin: [0, 7, 0, 3] },
                                { text: "OFICIALES", alignment: "center", color: "#D6EBE8", fontSize: 6.1, bold: true },
                            ],
                            fillColor: colors.tealDark,
                            margin: [12, 17, 12, 14],
                        },
                    ]],
                },
                layout: "noBorders",
                margin: [0, 0, 0, 12],
            },
            {
                table: {
                    widths: [82, "*"],
                    body: [
                        [{ text: "ALCANCE", style: "metaLabel" }, { text: scopeText(context), style: "metaValue" }],
                        [{ text: "GENERADO", style: "metaLabel" }, { text: dateTime(generatedAt), style: "metaValue" }],
                        [{ text: "TRAZABILIDAD", style: "metaLabel" }, { text: `Pauta ${meta.rubric_versions?.join(", ") || "sin versión"}  /  Prompt ${meta.prompt_versions?.join(", ") || "sin versión"}`, style: "metaValue" }],
                    ],
                },
                layout: { hLineColor: () => colors.line, vLineColor: () => colors.line, hLineWidth: () => 0.5, vLineWidth: () => 0.5, paddingLeft: () => 8, paddingRight: () => 8, paddingTop: () => 6, paddingBottom: () => 6 },
                margin: [0, 0, 0, 17],
            },
            sectionHeader("Lectura ejecutiva", "Panorama del período", "Los resultados representan únicamente los instrumentos incluidos por los filtros y el alcance autorizado."),
            {
                table: {
                    widths: [5, "*"],
                    body: [[
                        { text: "", fillColor: colors.teal },
                        { text: executiveNarrative(dashboard), alignment: "justify", color: colors.ink, fillColor: colors.surface, fontSize: 8.8, lineHeight: 1.4, margin: [12, 10, 12, 11] },
                    ]],
                },
                layout: "noBorders",
                margin: [0, 0, 0, 14],
            },
            {
                columns: [
                    metricCard("Ajuste actual", percentage(summary.current_compliance_percentage), "Mediana de la última versión", colors.tealDark, colors.tealSoft),
                    metricCard("Aprobación inicial", percentage(summary.first_pass_approval_rate), "Aprobados en el primer envío", colors.green, colors.greenSoft),
                    metricCard("Evolución", delta(summary.median_improvement_pp), `${number(summary.comparable_instruments)} instrumentos comparables`, colors.violet, colors.violetSoft),
                ],
                columnGap: 7,
                margin: [0, 0, 0, 7],
            },
            {
                columns: [
                    metricCard("Cobertura de evidencia", percentage(summary.evidence_coverage_percentage), "Criterios con evidencia verificable", colors.navy, colors.blueSoft),
                    metricCard("Tiempo de rectificación", days(summary.median_rectification_days), "Mediana hasta el reenvío", colors.amber, colors.amberSoft),
                    metricCard("Rectificaciones cerradas", percentage(summary.rectification_closure_rate), `${percentage(summary.rectification_rate)} requirió rectificación`, colors.green, colors.greenSoft),
                ],
                columnGap: 7,
                margin: [0, 0, 0, 12],
            },
            {
                table: {
                    widths: [28, "*"],
                    body: [[
                        { text: meta.sample_sufficient ? "OK" : "i", alignment: "center", bold: true, color: meta.sample_sufficient ? colors.green : colors.amber, fillColor: meta.sample_sufficient ? colors.greenSoft : colors.amberSoft, fontSize: 10, margin: [0, 6, 0, 6] },
                        { text: sampleMessage, alignment: "justify", color: meta.sample_sufficient ? colors.green : colors.amber, fillColor: meta.sample_sufficient ? colors.greenSoft : colors.amberSoft, fontSize: 7.3, lineHeight: 1.25, margin: [8, 6, 8, 6] },
                    ]],
                },
                layout: "noBorders",
                margin: [0, 0, 0, 17],
            },
            {
                unbreakable: true,
                stack: [
                    sectionHeader("Evolución temporal", "Ajuste, evidencia y volumen", "La línea azul muestra el ajuste mediano; la línea verde, la cobertura de evidencia. Las columnas representan el volumen de informes."),
                    { svg: trendSvg(dashboard.trend), width: 507 },
                ],
                margin: [0, 0, 0, 18],
            },
            sectionHeader("Resoluciones", "Distribución de resultados oficiales", "Cada resolución corresponde a una revisión formal registrada en el período."),
            distributionTable(dashboard),
            { text: "", pageBreak: "after" },
            sectionHeader("Dimensiones", "Primera versión frente a última versión", "Sólo se comparan instrumentos con una pauta compatible entre versiones."),
            { svg: dimensionsSvg(dashboard.dimensions), width: 507, margin: [0, 0, 0, 18] },
            sectionHeader("Foco de acompañamiento", "Prioridades institucionales de la pauta", "Índice: 45% déficit, 30% persistencia, 20% alcance y 5% brecha de evidencia."),
            prioritiesTable(dashboard.priorities || []),
            { text: "", pageBreak: "after" },
            sectionHeader("Mapa completo", "Estado y evolución de los 19 criterios", "No evidenciado se presenta por separado y no se interpreta como incumplimiento comprobado."),
            criteriaTable(dashboard.criteria || []),
            sectionHeader("Control complementario", "Hallazgos misceláneos", "Incluye puntajes, cálculos, consistencia interna, redacción y presentación."),
            miscellaneousTable(dashboard.miscellaneous || []),
            { text: "", margin: [0, 0, 0, 15] },
            sectionHeader("Trayectoria docente", "Evolución consolidada por docente", "Esta lectura orienta el acompañamiento pedagógico y no constituye una calificación laboral."),
            teachersTable(dashboard.teachers || []),
            { text: "", pageBreak: "after" },
            sectionHeader("Historial documental", "Trayectoria de instrumentos", "Se muestran hasta 30 instrumentos dentro del alcance filtrado."),
            instrumentsTable(dashboard.instruments || []),
            { text: "", margin: [0, 0, 0, 18] },
            sectionHeader("Metodología", "Criterios de interpretación y trazabilidad"),
            {
                table: {
                    widths: [105, "*"],
                    body: [
                        [{ text: "FUENTE OFICIAL", style: "metaLabel" }, { text: meta.methodology?.official_source || "Informe completado vinculado a una resolución de coordinación.", alignment: "justify", style: "metaValue" }],
                        [{ text: "CÁLCULO DE AJUSTE", style: "metaLabel" }, { text: meta.methodology?.compliance_formula || "(Cumple + 0,5 x Cumple parcialmente) / criterios aplicables.", alignment: "justify", style: "metaValue" }],
                        [{ text: "COMPARABILIDAD", style: "metaLabel" }, { text: meta.methodology?.comparison_rule || "Sólo compara versiones consecutivas del mismo instrumento con la misma pauta.", alignment: "justify", style: "metaValue" }],
                        [{ text: "PRIVACIDAD", style: "metaLabel" }, { text: "El informe conserva el mismo alcance autorizado de la vista y no incorpora instrumentos de otros ámbitos.", alignment: "justify", style: "metaValue" }],
                    ],
                },
                layout: { hLineColor: () => colors.line, vLineColor: () => colors.line, hLineWidth: () => 0.5, vLineWidth: () => 0.5, paddingLeft: () => 8, paddingRight: () => 8, paddingTop: () => 7, paddingBottom: () => 7 },
            },
        ],
        styles: {
            metricLabel: { color: colors.muted, bold: true, fontSize: 5.6, characterSpacing: 0.3 },
            metaLabel: { color: colors.tealDark, fillColor: colors.tealSoft, fontSize: 6.2, bold: true, characterSpacing: 0.25 },
            metaValue: { color: colors.ink, fillColor: colors.white, fontSize: 7.3, lineHeight: 1.2 },
            tableHeader: { color: colors.white, fillColor: colors.navy, bold: true, fontSize: 6.7, margin: [0, 2, 0, 2] },
        },
        defaultStyle: {
            font: "Roboto",
            color: colors.ink,
            fontSize: 6.8,
            lineHeight: 1.15,
        },
    };
}

export function pedagogicalStatisticsPdfFilename(dashboard, generatedAt = new Date()) {
    const school = dashboard?.meta?.school?.name || "establecimiento";
    return `informe-evolucion-documental-${safeFilenamePart(school)}-${localDateStamp(generatedAt)}.pdf`;
}

export async function downloadPedagogicalStatisticsPdf(dashboard, context = {}) {
    const generatedAt = context.generated_at || new Date();
    const definition = buildPedagogicalStatisticsPdfDefinition(dashboard, {
        ...context,
        generated_at: generatedAt,
    });
    const filename = pedagogicalStatisticsPdfFilename(dashboard, generatedAt);
    const pdfMake = await getPdfMake();
    pdfMake.createPdf(definition).download(filename);
    return { definition, filename };
}
