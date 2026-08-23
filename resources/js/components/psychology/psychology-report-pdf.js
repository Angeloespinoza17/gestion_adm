import { getPdfMake } from "../../utils/pdfmake";

const statusLabels = {
    draft: "Borrador",
    submitted: "Enviada",
    under_review: "En revisión",
    information_requested: "Antecedentes solicitados",
    accepted: "Aceptada",
    rejected: "Rechazada",
    closed: "Cerrada",
    completed: "Completada",
};

const priorityLabels = {
    low: "Baja",
    medium: "Media",
    high: "Alta",
    critical: "Crítica",
};

const date = (input) => {
    if (!input) return "Sin fecha";
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
        : "Sin fecha";

const escapeSvg = (input) =>
    String(input ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&apos;");

const short = (input, length = 34) => {
    const text = String(input || "Sin categoría");
    return text.length > length ? `${text.slice(0, length - 3)}...` : text;
};

const monthLabel = (input) => {
    if (!input) return "S/F";
    const parsed = new Date(`${input}-01T12:00:00`);
    return new Intl.DateTimeFormat("es-CL", { month: "short" })
        .format(parsed)
        .replace(".", "")
        .toUpperCase();
};

const labelFor = (kind, label, activityTypes) => {
    if (kind === "status") return statusLabels[label] || label || "Sin estado";
    if (kind === "priority")
        return priorityLabels[label] || label || "Sin prioridad";
    if (kind === "activity")
        return (
            activityTypes.find((item) => item.slug === label)?.name ||
            label ||
            "Sin tipo"
        );
    return label || "Sin categoría";
};

const horizontalChartSvg = (
    title,
    items,
    kind,
    activityTypes,
    color = "#675584"
) => {
    const rows = (items || []).slice(0, 9);
    const height = Math.max(105, 62 + rows.length * 32);
    const maximum = Math.max(1, ...rows.map((item) => Number(item.total || 0)));
    const content = rows.length
        ? rows
              .map((item, index) => {
                  const y = 59 + index * 32;
                  const width = Math.max(
                      4,
                      (Number(item.total || 0) / maximum) * 330
                  );
                  const label = short(
                      labelFor(kind, item.label, activityTypes),
                      40
                  );
                  return `<text x="22" y="${
                      y + 4
                  }" font-size="10" font-weight="600" fill="#4b586b">${escapeSvg(
                      label
                  )}</text><rect x="245" y="${
                      y - 7
                  }" width="340" height="14" rx="7" fill="#edf1f5"/><rect x="245" y="${
                      y - 7
                  }" width="${width}" height="14" rx="7" fill="${color}"/><text x="680" y="${
                      y + 4
                  }" text-anchor="end" font-size="11" font-weight="700" fill="#29384d">${Number(
                      item.total || 0
                  )}</text>`;
              })
              .join("")
        : '<text x="360" y="72" text-anchor="middle" font-size="10" fill="#8793a3">Sin datos para el período seleccionado</text>';

    return `<svg width="720" height="${height}" viewBox="0 0 720 ${height}" xmlns="http://www.w3.org/2000/svg"><rect width="720" height="${height}" rx="14" fill="#f8fafc"/><text x="22" y="28" font-size="12" font-weight="700" fill="#26374c">${escapeSvg(
        title
    )}</text><line x1="22" y1="42" x2="698" y2="42" stroke="#e2e7ed"/>${content}</svg>`;
};

const monthlyChartSvg = (items) => {
    const rows = (items || []).slice(-12);
    const maximum = Math.max(1, ...rows.map((item) => Number(item.total || 0)));
    const baseY = 196;
    const usableWidth = 620;
    const groupWidth = usableWidth / Math.max(1, rows.length);
    const barWidth = Math.min(30, Math.max(12, groupWidth * 0.48));
    const bars = rows.length
        ? rows
              .map((item, index) => {
                  const x =
                      58 + index * groupWidth + (groupWidth - barWidth) / 2;
                  const barHeight = Math.max(
                      4,
                      (Number(item.total || 0) / maximum) * 128
                  );
                  const center = x + barWidth / 2;
                  return `<rect x="${x}" y="${
                      baseY - barHeight
                  }" width="${barWidth}" height="${barHeight}" rx="5" fill="#675584"/><text x="${center}" y="${
                      baseY - barHeight - 8
                  }" text-anchor="middle" font-size="9" font-weight="700" fill="#34445a">${Number(
                      item.total || 0
                  )}</text><text x="${center}" y="218" text-anchor="middle" font-size="8" fill="#7d8998">${escapeSvg(
                      monthLabel(item.label)
                  )}</text>`;
              })
              .join("")
        : '<text x="360" y="130" text-anchor="middle" font-size="10" fill="#8793a3">Sin evolución disponible</text>';

    return `<svg width="720" height="235" viewBox="0 0 720 235" xmlns="http://www.w3.org/2000/svg"><rect width="720" height="235" rx="14" fill="#f8fafc"/><line x1="45" y1="196" x2="690" y2="196" stroke="#ccd5df"/><line x1="45" y1="68" x2="690" y2="68" stroke="#e8edf2" stroke-dasharray="4 5"/>${bars}</svg>`;
};

const kpiCell = (label, amount, note, accent) => ({
    margin: [0, 0, 7, 7],
    table: {
        widths: ["*"],
        body: [
            [
                {
                    stack: [
                        { text: label.toUpperCase(), style: "kpiLabel" },
                        {
                            text: String(amount),
                            style: "kpiValue",
                            color: accent,
                        },
                        { text: note, style: "kpiNote" },
                    ],
                    margin: [11, 9, 11, 9],
                    fillColor: "#f8fafc",
                },
            ],
        ],
    },
    layout: {
        hLineColor: () => "#dfe6ed",
        vLineColor: () => "#dfe6ed",
    },
});

const tableHeader = (labels) =>
    labels.map((label) => ({
        text: label,
        color: "#ffffff",
        bold: true,
        fontSize: 7.5,
    }));

const normalizedRows = (items, kind, activityTypes) => {
    const rows = (items || [])
        .slice(0, 12)
        .map((item) => [
            labelFor(kind, item.label, activityTypes),
            Number(item.total || 0),
        ]);
    return rows.length ? rows : [["Sin datos", 0]];
};

export function buildPsychologyReportPdfDefinition(report, options = {}) {
    const activityTypes = options.activityTypes || [];
    const professional = ["personal", "professional"].includes(
        report.scope?.type
    );
    const scopeName = professional
        ? report.scope?.professional_name || "Profesional"
        : "Cobertura institucional";
    const title = professional
        ? `Estadísticas de ${scopeName}`
        : "Reporte institucional";
    const counts = report.counts || {};
    const service = report.service_levels || {};
    const tasks = report.tasks || {};
    const activityRate = counts.unique_students
        ? (
              Number(counts.activities || 0) / Number(counts.unique_students)
          ).toLocaleString("es-CL", { maximumFractionDigits: 1 })
        : "0";
    const overview = `Durante el período se registraron ${Number(
        counts.activities || 0
    )} atenciones asociadas a ${Number(
        counts.unique_students || 0
    )} estudiantes únicos y ${Number(
        counts.cases || 0
    )} casos. El promedio fue de ${activityRate} atenciones por estudiante.`;
    const privacyNote = professional
        ? "Este informe contiene exclusivamente registros vinculados a la profesional seleccionada."
        : "Este informe presenta información agregada. Los grupos pequeños se protegen según el umbral institucional.";
    const tableLayout = {
        fillColor: (rowIndex) =>
            rowIndex === 0 ? "#405a78" : rowIndex % 2 === 0 ? "#f8fafc" : null,
        hLineColor: () => "#e2e7ed",
        vLineColor: () => "#e2e7ed",
        paddingLeft: () => 7,
        paddingRight: () => 7,
        paddingTop: () => 6,
        paddingBottom: () => 6,
    };

    return {
        pageSize: "A4",
        pageMargins: [38, 48, 38, 44],
        info: {
            title: `${title} - Psicología Escolar`,
            subject: "Reporte de estadísticas de Psicología Escolar",
            author: "CNSC Gestión",
        },
        defaultStyle: { fontSize: 8.5, color: "#34445a" },
        watermark: {
            text: "CONFIDENCIAL",
            color: "#675584",
            opacity: 0.035,
            bold: true,
        },
        header: (currentPage) =>
            currentPage > 1
                ? {
                      margin: [38, 17, 38, 0],
                      columns: [
                          {
                              text: "PSICOLOGÍA ESCOLAR",
                              color: "#675584",
                              bold: true,
                              fontSize: 7.5,
                              characterSpacing: 1,
                          },
                          {
                              text: scopeName,
                              alignment: "right",
                              color: "#7b8798",
                              fontSize: 7.5,
                          },
                      ],
                  }
                : null,
        footer: (currentPage, pageCount) => ({
            margin: [38, 9, 38, 0],
            columns: [
                {
                    text: `CNSC Gestión - Documento confidencial - Emitido ${dateTime(
                        report.generated_at
                    )}`,
                    color: "#8a95a4",
                    fontSize: 6.5,
                },
                {
                    text: `Página ${currentPage} de ${pageCount}`,
                    alignment: "right",
                    color: "#8a95a4",
                    fontSize: 6.5,
                },
            ],
        }),
        content: [
            {
                canvas: [
                    {
                        type: "rect",
                        x: 0,
                        y: 0,
                        w: 519,
                        h: 9,
                        r: 4,
                        color: "#675584",
                    },
                    {
                        type: "rect",
                        x: 397,
                        y: 0,
                        w: 122,
                        h: 9,
                        r: 4,
                        color: "#4b8b87",
                    },
                ],
                margin: [0, 0, 0, 25],
            },
            {
                text: "INFORME EJECUTIVO CONFIDENCIAL",
                style: "eyebrow",
            },
            {
                text: title,
                fontSize: 27,
                bold: true,
                color: "#203047",
                lineHeight: 1.04,
                margin: [0, 7, 0, 7],
            },
            {
                text: "Psicología Escolar - Gestión psicoeducativa",
                fontSize: 11,
                color: "#6f7d90",
                margin: [0, 0, 0, 20],
            },
            {
                columns: [
                    {
                        width: "*",
                        stack: [
                            { text: "ALCANCE", style: "metaLabel" },
                            { text: scopeName, style: "metaValue" },
                        ],
                    },
                    {
                        width: "*",
                        stack: [
                            { text: "PERÍODO", style: "metaLabel" },
                            {
                                text: `${date(report.period?.from)} al ${date(
                                    report.period?.to
                                )}`,
                                style: "metaValue",
                            },
                        ],
                    },
                    {
                        width: 104,
                        stack: [
                            { text: "GENERADO", style: "metaLabel" },
                            {
                                text: dateTime(report.generated_at),
                                style: "metaValue",
                            },
                        ],
                    },
                ],
                columnGap: 14,
                margin: [0, 0, 0, 23],
            },
            { text: "PANORAMA GENERAL", style: "eyebrow" },
            { text: "Indicadores principales", style: "sectionTitle" },
            {
                columns: [
                    kpiCell(
                        "Derivaciones",
                        Number(counts.referrals || 0),
                        "Solicitudes asignadas",
                        "#675584"
                    ),
                    kpiCell(
                        "Casos",
                        Number(counts.cases || 0),
                        "Casos del período",
                        "#405a78"
                    ),
                ],
            },
            {
                columns: [
                    kpiCell(
                        "Estudiantes únicos",
                        Number(counts.unique_students || 0),
                        "Sin duplicar estudiantes",
                        "#4b8b87"
                    ),
                    kpiCell(
                        "Atenciones",
                        Number(counts.activities || 0),
                        `${activityRate} por estudiante`,
                        "#b07058"
                    ),
                ],
                margin: [0, 0, 0, 12],
            },
            {
                table: {
                    widths: ["*"],
                    body: [
                        [
                            {
                                stack: [
                                    {
                                        text: "LECTURA DEL PERÍODO",
                                        style: "metaLabel",
                                    },
                                    {
                                        text: overview,
                                        color: "#3f4e62",
                                        fontSize: 9,
                                        lineHeight: 1.25,
                                        margin: [0, 5, 0, 0],
                                    },
                                ],
                                fillColor: "#f2f6f8",
                                margin: [12, 10, 12, 10],
                            },
                        ],
                    ],
                },
                layout: "noBorders",
                margin: [0, 0, 0, 18],
            },
            { text: "GESTIÓN OPERATIVA", style: "eyebrow" },
            { text: "Seguimiento y oportunidad", style: "sectionTitle" },
            {
                table: {
                    widths: ["*", 66, "*", 66],
                    body: [
                        tableHeader([
                            "Indicador",
                            "Resultado",
                            "Indicador",
                            "Resultado",
                        ]),
                        [
                            "Promedio primera revisión",
                            `${Number(
                                service.average_first_review_hours || 0
                            ).toLocaleString("es-CL")} h`,
                            "Mediana primera revisión",
                            `${Number(
                                service.median_first_review_hours || 0
                            ).toLocaleString("es-CL")} h`,
                        ],
                        [
                            "Casos sin actividad reciente",
                            Number(service.cases_without_activity || 0),
                            "Seguimientos pendientes",
                            Number(tasks.pending_followups || 0),
                        ],
                        [
                            "Tareas vencidas",
                            Number(tasks.overdue || 0),
                            "Casos reabiertos",
                            Number(report.reopenings || 0),
                        ],
                    ],
                },
                layout: tableLayout,
                margin: [0, 0, 0, 18],
            },
            {
                text: "EVOLUCIÓN EN EL TIEMPO",
                style: "eyebrow",
                pageBreak: "before",
            },
            { text: "Derivaciones por mes", style: "sectionTitle" },
            {
                svg: monthlyChartSvg(report.monthly_evolution || []),
                width: 519,
                margin: [0, 2, 0, 18],
            },
            { text: "DISTRIBUCIÓN DE LA DEMANDA", style: "eyebrow" },
            { text: "Estados y prioridades", style: "sectionTitle" },
            {
                svg: horizontalChartSvg(
                    "Estado de derivaciones",
                    report.by_status,
                    "status",
                    activityTypes,
                    "#675584"
                ),
                width: 519,
                margin: [0, 2, 0, 9],
            },
            {
                svg: horizontalChartSvg(
                    "Prioridad profesional",
                    report.by_priority,
                    "priority",
                    activityTypes,
                    "#b07058"
                ),
                width: 519,
                margin: [0, 0, 0, 18],
            },
            {
                text: "ACTIVIDAD PROFESIONAL",
                style: "eyebrow",
                pageBreak: "before",
            },
            { text: "Atenciones registradas por tipo", style: "sectionTitle" },
            {
                svg: horizontalChartSvg(
                    "Tipos de atención",
                    report.activities_by_type,
                    "activity",
                    activityTypes,
                    "#4b8b87"
                ),
                width: 519,
                margin: [0, 2, 0, 18],
            },
            { text: "MOTIVOS Y COBERTURA", style: "eyebrow" },
            { text: "Distribución agregada", style: "sectionTitle" },
            {
                columns: [
                    {
                        width: "*",
                        stack: [
                            {
                                text: "Motivo o grupo protegido",
                                style: "tableTitle",
                            },
                            {
                                table: {
                                    headerRows: 1,
                                    widths: ["*", 44],
                                    body: [
                                        tableHeader(["Motivo", "Total"]),
                                        ...normalizedRows(
                                            report.by_reason,
                                            "reason",
                                            activityTypes
                                        ),
                                    ],
                                },
                                layout: tableLayout,
                            },
                        ],
                    },
                    {
                        width: "*",
                        stack: [
                            {
                                text: "Origen de derivación",
                                style: "tableTitle",
                            },
                            {
                                table: {
                                    headerRows: 1,
                                    widths: ["*", 44],
                                    body: [
                                        tableHeader(["Origen", "Total"]),
                                        ...normalizedRows(
                                            report.by_origin,
                                            "origin",
                                            activityTypes
                                        ),
                                    ],
                                },
                                layout: tableLayout,
                            },
                        ],
                    },
                ],
                columnGap: 12,
                margin: [0, 0, 0, 18],
            },
            {
                table: {
                    widths: ["*"],
                    body: [
                        [
                            {
                                stack: [
                                    {
                                        text: "PROTECCIÓN Y TRAZABILIDAD",
                                        style: "metaLabel",
                                        color: "#80622d",
                                    },
                                    {
                                        text: `${privacyNote} Todo acceso y descarga queda registrado para fines de trazabilidad.`,
                                        color: "#6e5426",
                                        fontSize: 8,
                                        lineHeight: 1.2,
                                        margin: [0, 5, 0, 0],
                                    },
                                ],
                                fillColor: "#fff7e6",
                                margin: [11, 9, 11, 9],
                            },
                        ],
                    ],
                },
                layout: {
                    hLineColor: () => "#efd9ac",
                    vLineColor: () => "#efd9ac",
                },
            },
        ],
        styles: {
            eyebrow: {
                color: "#675584",
                bold: true,
                fontSize: 7.5,
                characterSpacing: 1.2,
                margin: [0, 0, 0, 3],
            },
            sectionTitle: {
                color: "#203047",
                bold: true,
                fontSize: 16,
                margin: [0, 0, 0, 10],
            },
            metaLabel: {
                color: "#8a95a4",
                bold: true,
                fontSize: 6.8,
                characterSpacing: 0.8,
            },
            metaValue: {
                color: "#34445a",
                bold: true,
                fontSize: 8.5,
                margin: [0, 4, 0, 0],
            },
            kpiLabel: {
                color: "#7b8798",
                bold: true,
                fontSize: 6.8,
                characterSpacing: 0.6,
            },
            kpiValue: {
                bold: true,
                fontSize: 18,
                margin: [0, 4, 0, 2],
            },
            kpiNote: {
                color: "#8a95a4",
                fontSize: 6.8,
            },
            tableTitle: {
                color: "#34445a",
                bold: true,
                fontSize: 9,
                margin: [0, 0, 0, 6],
            },
        },
    };
}

export async function downloadPsychologyReportPdf(report, options = {}) {
    const pdfMake = await getPdfMake();
    const definition = buildPsychologyReportPdfDefinition(report, options);
    const professional = ["personal", "professional"].includes(
        report.scope?.type
    );
    const scope = professional
        ? report.scope?.professional_name || "profesional"
        : "institucional";
    const filename =
        `reporte-psicologia-${scope}-${report.period?.from}-${report.period?.to}.pdf`
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .replace(/[^a-z0-9._-]+/g, "-")
            .replace(/-+/g, "-");

    pdfMake.createPdf(definition).download(filename);
}
