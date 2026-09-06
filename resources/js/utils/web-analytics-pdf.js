import { getPdfMake } from "./pdfmake";

const COLORS = Object.freeze({
  navy: "#08384B",
  teal: "#0B7480",
  tealSoft: "#E7F3F2",
  gold: "#C8924F",
  goldSoft: "#FFF4E4",
  blue: "#477F9D",
  blueSoft: "#EBF3F8",
  ink: "#183B47",
  muted: "#70858D",
  line: "#DDE8EA",
  surface: "#F6F9F9",
  white: "#FFFFFF",
  green: "#167A59",
  red: "#B45045",
});

const number = (input, digits = 0) => new Intl.NumberFormat("es-CL", {
  minimumFractionDigits: digits,
  maximumFractionDigits: digits,
}).format(Number(input || 0));

const percent = (input) => `${number(input, 1)}%`;

const duration = (input) => {
  const seconds = Math.max(0, Math.round(Number(input || 0)));
  if (seconds < 60) return `${seconds} s`;
  const minutes = Math.floor(seconds / 60);
  const remainder = seconds % 60;
  return remainder ? `${minutes} min ${remainder} s` : `${minutes} min`;
};

const date = (input) => {
  if (!input) return "Sin fecha";
  const parsed = new Date(`${String(input).slice(0, 10)}T12:00:00`);
  if (Number.isNaN(parsed.getTime())) return String(input);
  return new Intl.DateTimeFormat("es-CL", { day: "2-digit", month: "short", year: "numeric" })
    .format(parsed)
    .replaceAll(".", "");
};

const dateTime = (input) => {
  const parsed = input ? new Date(input) : new Date();
  if (Number.isNaN(parsed.getTime())) return "";
  return new Intl.DateTimeFormat("es-CL", {
    day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", hour12: false,
  }).format(parsed).replaceAll(".", "");
};

const text = (input, fallback = "Sin información") => String(input ?? "").trim() || fallback;

const metric = (analytics, key) => analytics?.summary?.[key] || { value: 0, change: null, improved: null };

const change = (item) => {
  if (item?.change === null || item?.change === undefined) return "Sin base previa";
  const value = Number(item.change || 0);
  return `${value > 0 ? "+" : ""}${number(value, 1)}% vs. período anterior`;
};

const contentTypeLabel = (value) => ({
  news: "Noticias",
  event: "Eventos",
  student_life: "Vida estudiantil",
  installation: "Instalaciones",
  page: "Página",
}[value] || text(value, "Todo el sitio").replaceAll("_", " "));

const channelLabel = (value) => ({
  direct: "Directo",
  search: "Buscadores",
  social: "Redes sociales",
  referral: "Referencia",
  campaign: "Campaña",
  email: "Correo",
  paid: "Publicidad",
}[value] || text(value));

const deviceLabel = (value) => ({
  mobile: "Móvil",
  tablet: "Tablet",
  desktop: "Escritorio",
}[value] || text(value));

const tableLayout = {
  fillColor: (rowIndex) => rowIndex > 0 && rowIndex % 2 === 0 ? COLORS.surface : null,
  hLineColor: () => COLORS.line,
  vLineColor: () => COLORS.line,
  hLineWidth: () => 0.45,
  vLineWidth: () => 0.45,
  paddingLeft: () => 5,
  paddingRight: () => 5,
  paddingTop: () => 4,
  paddingBottom: () => 4,
};

const headerCell = (label, alignment = "left") => ({ text: label, style: "tableHeader", alignment });
const cell = (value, style = "tableCell") => ({ text: text(value, "-"), style });

const sectionTitle = (eyebrow, title, subtitle = "", pageBreak) => ({
  pageBreak,
  margin: [0, 4, 0, 8],
  stack: [
    { text: eyebrow.toUpperCase(), style: "sectionEyebrow" },
    { text: title, style: "sectionTitle" },
    ...(subtitle ? [{ text: subtitle, style: "sectionSubtitle" }] : []),
  ],
});

const emptyState = (message) => ({
  margin: [0, 2, 0, 12],
  table: {
    widths: ["*"],
    body: [[{ text: message, style: "emptyState", fillColor: COLORS.surface, margin: [12, 14, 12, 14] }]],
  },
  layout: "noBorders",
});

const kpiCard = (label, value, comparison, color, fillColor) => ({
  margin: [0, 0, 6, 0],
  table: {
    widths: ["*"],
    body: [[{
      fillColor,
      margin: [9, 8, 9, 8],
      stack: [
        { text: label.toUpperCase(), color, bold: true, fontSize: 6.2, characterSpacing: 0.55 },
        { text: value, color: COLORS.ink, bold: true, fontSize: 15, margin: [0, 4, 0, 2] },
        { text: comparison, color: COLORS.muted, fontSize: 6.1 },
      ],
    }]],
  },
  layout: "noBorders",
});

const qualityCard = (label, value, detail, comparison, color) => ({
  margin: [0, 0, 6, 0],
  table: {
    widths: ["*"],
    body: [[{
      margin: [9, 7, 9, 7],
      stack: [
        { columns: [{ text: label, color, bold: true, fontSize: 7 }, { text: value, color: COLORS.ink, bold: true, fontSize: 11, alignment: "right" }] },
        { text: detail, color: COLORS.muted, fontSize: 6.1, margin: [0, 4, 0, 2] },
        { text: comparison, color: COLORS.muted, fontSize: 5.8 },
      ],
    }]],
  },
  layout: {
    hLineColor: () => COLORS.line,
    vLineColor: () => COLORS.line,
    hLineWidth: () => 0.6,
    vLineWidth: () => 0.6,
  },
});

const trendSvg = (rows = []) => {
  const series = rows.filter((row) => row?.date);
  if (!series.length || !series.some((row) => Number(row.views || 0) > 0)) return null;

  const width = 760;
  const height = 150;
  const left = 34;
  const right = 12;
  const top = 14;
  const bottom = 25;
  const plotWidth = width - left - right;
  const plotHeight = height - top - bottom;
  const max = Math.max(...series.flatMap((row) => [Number(row.views || 0), Number(row.visitors || 0)]), 1);
  const point = (row, index, key) => {
    const x = left + (series.length === 1 ? plotWidth / 2 : (index / (series.length - 1)) * plotWidth);
    const y = top + plotHeight - (Number(row[key] || 0) / max) * plotHeight;
    return `${x.toFixed(1)},${y.toFixed(1)}`;
  };
  const views = series.map((row, index) => point(row, index, "views")).join(" ");
  const visitors = series.map((row, index) => point(row, index, "visitors")).join(" ");
  const indexes = [...new Set([0, Math.floor((series.length - 1) / 2), series.length - 1])];
  const grid = [0, 0.5, 1].map((ratio) => {
    const y = top + plotHeight - ratio * plotHeight;
    return `<line x1="${left}" y1="${y}" x2="${width - right}" y2="${y}" stroke="${COLORS.line}" stroke-width="1"/><text x="0" y="${y + 3}" fill="${COLORS.muted}" font-size="7">${number(max * ratio)}</text>`;
  }).join("");
  const labels = indexes.map((index) => {
    const x = left + (series.length === 1 ? plotWidth / 2 : (index / (series.length - 1)) * plotWidth);
    const anchor = index === 0 ? "start" : (index === series.length - 1 ? "end" : "middle");
    return `<text x="${x}" y="${height - 3}" text-anchor="${anchor}" fill="${COLORS.muted}" font-size="7">${date(series[index].date).replaceAll("&", "&amp;")}</text>`;
  }).join("");

  return `<svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">${grid}<polygon points="${left},${top + plotHeight} ${views} ${width - right},${top + plotHeight}" fill="#DCEFF0" opacity="0.72"/><polyline points="${views}" fill="none" stroke="${COLORS.teal}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><polyline points="${visitors}" fill="none" stroke="${COLORS.gold}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>${labels}</svg>`;
};

const insightItems = (analytics) => {
  const items = [];
  const topPage = analytics?.top_pages?.[0];
  const topContent = analytics?.content_performance?.[0];
  const source = analytics?.sources?.[0];
  const device = analytics?.devices?.[0];

  if (topPage) items.push(`Página con mayor alcance: ${text(topPage.title)} (${number(topPage.views)} vistas).`);
  if (topContent) items.push(`Contenido destacado: ${text(topContent.title)} con ${percent(topContent.engagement_rate)} de interacción.`);
  if (source) items.push(`Principal origen de tráfico: ${text(source.source)} - ${channelLabel(source.channel)} (${number(source.views)} vistas).`);
  if (device) items.push(`Dispositivo predominante: ${deviceLabel(device.label)} (${percent(device.percentage)}).`);
  if (!items.length) items.push("Aún no hay actividad suficiente para identificar tendencias del período.");

  return items.slice(0, 4);
};

const topPagesTable = (rows = []) => rows.length ? ({
  table: {
    headerRows: 1,
    keepWithHeaderRows: 1,
    widths: [18, "*", 48, 50, 64, 58],
    body: [
      ["#", "Página", "Vistas", "Visitantes", "Permanencia", "Interacción"].map((label, index) => headerCell(label, index > 1 ? "center" : "left")),
      ...rows.map((row, index) => [
        cell(index + 1, "rankCell"),
        { stack: [cell(row.title, "strongCell"), cell(row.path, "pathCell")] },
        cell(number(row.views), "numberCell"),
        cell(number(row.visitors), "numberCell"),
        cell(duration(row.avg_active_seconds), "numberCell"),
        cell(percent(row.engagement_rate), "numberCell"),
      ]),
    ],
  },
  layout: tableLayout,
}) : emptyState("No existen páginas con visitas dentro del período seleccionado.");

const contentTable = (rows = []) => rows.length ? ({
  table: {
    headerRows: 1,
    keepWithHeaderRows: 1,
    widths: [64, "*", 45, 50, 50, 63, 58],
    body: [
      ["Tipo", "Contenido", "Vistas", "Visitantes", "Sesiones", "Permanencia", "Interacción"].map((label, index) => headerCell(label, index > 1 ? "center" : "left")),
      ...rows.map((row) => [
        cell(contentTypeLabel(row.content_type), "tagCell"),
        { stack: [cell(row.title, "strongCell"), cell(row.path, "pathCell")] },
        cell(number(row.views), "numberCell"),
        cell(number(row.visitors), "numberCell"),
        cell(number(row.sessions), "numberCell"),
        cell(duration(row.avg_active_seconds), "numberCell"),
        cell(percent(row.engagement_rate), "numberCell"),
      ]),
    ],
  },
  layout: tableLayout,
}) : emptyState("Las noticias, eventos y otros contenidos todavía no acumulan métricas para este filtro.");

const distributionTable = (title, headers, widths, rows, mapper) => ({
  width: "*",
  margin: [0, 0, 7, 0],
  stack: [
    { text: title, style: "smallSectionTitle" },
    ...(rows.length ? [{
      table: {
        headerRows: 1,
        keepWithHeaderRows: 1,
        widths,
        body: [headers.map((label, index) => headerCell(label, index > 0 ? "center" : "left")), ...rows.map(mapper)],
      },
      layout: tableLayout,
    }] : [emptyState("Sin datos disponibles.")]),
  ],
});

const dailyTable = (rows = []) => {
  const activeRows = rows.filter((row) => Number(row.views || 0) > 0);
  if (!activeRows.length) return emptyState("No hubo días con visitas registradas dentro del período seleccionado.");
  return {
    table: {
      headerRows: 1,
      keepWithHeaderRows: 1,
      widths: [82, 62, 62, 62, 80, 80],
      body: [
        ["Fecha", "Vistas", "Visitantes", "Sesiones", "Permanencia", "Interacción"].map((label, index) => headerCell(label, index > 0 ? "center" : "left")),
        ...activeRows.map((row) => [
          cell(date(row.date), "strongCell"),
          cell(number(row.views), "numberCell"),
          cell(number(row.visitors), "numberCell"),
          cell(number(row.sessions), "numberCell"),
          cell(duration(row.avg_active_seconds), "numberCell"),
          cell(percent(row.engagement_rate), "numberCell"),
        ]),
      ],
    },
    layout: tableLayout,
  };
};

export function buildWebAnalyticsPdfDefinition(analytics = {}, options = {}) {
  const period = analytics.period || {};
  const scope = options.contentType ? contentTypeLabel(options.contentType) : "Todo el sitio";
  const chart = trendSvg(analytics.daily || []);
  const views = metric(analytics, "views");
  const visitors = metric(analytics, "visitors");
  const sessions = metric(analytics, "sessions");
  const activeTime = metric(analytics, "avg_active_seconds");
  const engagement = metric(analytics, "engagement_rate");
  const bounce = metric(analytics, "bounce_rate");
  const pagesPerSession = metric(analytics, "pages_per_session");

  return {
    pageSize: "A4",
    pageOrientation: "landscape",
    pageMargins: [28, 62, 28, 34],
    info: {
      title: `Informe de estadísticas web ${text(period.from, "consulta")} ${text(period.to, "")}`.trim(),
      subject: `Rendimiento del sitio público - ${scope}`,
      author: "Colegio Nuestra Señora del Carmen",
      keywords: "estadísticas web, visitas, contenidos, permanencia",
    },
    header: () => ({
      margin: [28, 17, 28, 0],
      columns: [
        {
          width: "*",
          stack: [
            { text: "COLEGIO NUESTRA SEÑORA DEL CARMEN", color: COLORS.gold, bold: true, fontSize: 6.5, characterSpacing: 1.2 },
            { text: "Informe de estadísticas web", color: COLORS.navy, bold: true, fontSize: 16, margin: [0, 2, 0, 0] },
          ],
        },
        {
          width: 250,
          alignment: "right",
          stack: [
            { text: scope.toUpperCase(), color: COLORS.teal, bold: true, fontSize: 6.5, characterSpacing: 0.7 },
            { text: `${date(period.from)} al ${date(period.to)}`, color: COLORS.ink, bold: true, fontSize: 8.5, margin: [0, 3, 0, 0] },
          ],
        },
      ],
    }),
    footer: (currentPage, pageCount) => ({
      margin: [28, 8, 28, 0],
      columns: [
        { text: "Analítica anónima - sin IP, parámetros de URL ni agentes de usuario", color: COLORS.muted, fontSize: 6.2 },
        { text: `Página ${currentPage} de ${pageCount}`, color: COLORS.muted, fontSize: 6.2, alignment: "right" },
      ],
    }),
    content: [
      {
        margin: [0, 0, 0, 9],
        table: {
          widths: ["*", 190],
          body: [[
            { text: "RESUMEN EJECUTIVO DEL PERÍODO", color: COLORS.white, bold: true, fontSize: 7, fillColor: COLORS.navy, margin: [8, 5, 8, 5] },
            { text: `Generado: ${dateTime(analytics.generated_at)}`, color: COLORS.white, fontSize: 6.4, alignment: "right", fillColor: COLORS.navy, margin: [8, 5, 8, 5] },
          ]],
        },
        layout: "noBorders",
      },
      {
        columns: [
          kpiCard("Vistas", number(views.value), change(views), COLORS.teal, COLORS.tealSoft),
          kpiCard("Visitantes", number(visitors.value), change(visitors), COLORS.blue, COLORS.blueSoft),
          kpiCard("Sesiones", number(sessions.value), change(sessions), COLORS.gold, COLORS.goldSoft),
          kpiCard("Permanencia activa", duration(activeTime.value), change(activeTime), COLORS.teal, COLORS.tealSoft),
        ],
        margin: [0, 0, 0, 8],
      },
      {
        columns: [
          qualityCard("Interacción", percent(engagement.value), "Lectura, scroll o clic por vista", change(engagement), COLORS.teal),
          qualityCard("Rebote", percent(bounce.value), "Sesiones breves de una página", change(bounce), COLORS.gold),
          qualityCard("Páginas por sesión", number(pagesPerSession.value, 2), "Profundidad de navegación", change(pagesPerSession), COLORS.blue),
        ],
        margin: [0, 0, 0, 9],
      },
      sectionTitle("Evolución", "Tráfico y audiencia", "Vistas en verde petróleo y visitantes en dorado."),
      ...(chart ? [{ svg: chart, width: 760, height: 150, margin: [0, 0, 0, 7] }] : [emptyState("Aún no hay visitas para dibujar la evolución del período.")]),
      {
        margin: [0, 2, 0, 0],
        columns: [
          {
            width: 88,
            table: { widths: ["*"], body: [[{ text: "LECTURAS CLAVE", color: COLORS.white, bold: true, fontSize: 6.5, alignment: "center", fillColor: COLORS.teal, margin: [5, 10, 5, 10] }]] },
            layout: "noBorders",
          },
          {
            width: "*",
            margin: [10, 2, 0, 0],
            ul: insightItems(analytics).map((item) => ({ text: item, style: "insightItem" })),
          },
        ],
      },
      sectionTitle("Exploración", "Páginas más visitadas", "Clasificación del portal público para el filtro aplicado.", "before"),
      topPagesTable(analytics.top_pages || []),
      sectionTitle("Rendimiento editorial", "Noticias, eventos y contenidos", "Resultados de los contenidos etiquetados automáticamente.", "before"),
      contentTable(analytics.content_performance || []),
      sectionTitle("Adquisición y tecnología", "Origen y contexto de navegación", "Distribuciones calculadas sobre las vistas del período.", "before"),
      {
        columns: [
          distributionTable("Fuentes de tráfico", ["Fuente", "Canal", "Vistas", "Visitantes"], ["*", 68, 45, 55], analytics.sources || [], (row) => [
            cell(row.source, "strongCell"), cell(channelLabel(row.channel)), cell(number(row.views), "numberCell"), cell(number(row.visitors), "numberCell"),
          ]),
          distributionTable("Dispositivos", ["Dispositivo", "Vistas", "Participación"], ["*", 48, 62], analytics.devices || [], (row) => [
            cell(deviceLabel(row.label), "strongCell"), cell(number(row.views), "numberCell"), cell(percent(row.percentage), "numberCell"),
          ]),
          distributionTable("Navegadores", ["Navegador", "Vistas", "Participación"], ["*", 48, 62], analytics.browsers || [], (row) => [
            cell(row.label, "strongCell"), cell(number(row.views), "numberCell"), cell(percent(row.percentage), "numberCell"),
          ]),
        ],
      },
      sectionTitle("Detalle diario", "Actividad registrada por día", "Se incluyen únicamente los días que acumularon al menos una vista.", "before"),
      dailyTable(analytics.daily || []),
      {
        margin: [0, 12, 0, 0],
        table: {
          widths: [28, "*"],
          body: [[
            { text: "i", color: COLORS.white, bold: true, fontSize: 10, alignment: "center", fillColor: COLORS.teal, margin: [0, 7, 0, 7] },
            {
              fillColor: COLORS.tealSoft,
              margin: [8, 5, 8, 5],
              stack: [
                { text: "ALCANCE Y PRIVACIDAD", color: COLORS.teal, bold: true, fontSize: 6.5 },
                { text: `${text(analytics?.tracking?.privacy)} La medición comenzó ${analytics?.tracking?.started_on ? `el ${date(analytics.tracking.started_on)}` : "al activarse el módulo"}; no reconstruye actividad anterior.`, color: COLORS.muted, fontSize: 6.2, margin: [0, 2, 0, 0] },
              ],
            },
          ]],
        },
        layout: "noBorders",
      },
    ],
    defaultStyle: { font: "Roboto", color: COLORS.ink, fontSize: 7 },
    styles: {
      sectionEyebrow: { color: COLORS.gold, bold: true, fontSize: 6.1, characterSpacing: 0.8 },
      sectionTitle: { color: COLORS.navy, bold: true, fontSize: 12, margin: [0, 2, 0, 0] },
      sectionSubtitle: { color: COLORS.muted, fontSize: 6.3, margin: [0, 2, 0, 0] },
      smallSectionTitle: { color: COLORS.navy, bold: true, fontSize: 8, margin: [0, 0, 0, 5] },
      tableHeader: { color: COLORS.white, fillColor: COLORS.navy, bold: true, fontSize: 6, margin: [1, 2, 1, 2] },
      tableCell: { color: COLORS.ink, fontSize: 6.4 },
      strongCell: { color: COLORS.ink, bold: true, fontSize: 6.5 },
      pathCell: { color: COLORS.muted, fontSize: 5.5, margin: [0, 2, 0, 0] },
      numberCell: { color: COLORS.ink, fontSize: 6.4, alignment: "center" },
      rankCell: { color: COLORS.gold, bold: true, fontSize: 6.5, alignment: "center" },
      tagCell: { color: COLORS.teal, bold: true, fontSize: 5.8 },
      insightItem: { color: COLORS.ink, fontSize: 6.6, margin: [0, 1, 0, 1] },
      emptyState: { color: COLORS.muted, alignment: "center", fontSize: 7.2 },
    },
  };
}

export async function downloadWebAnalyticsPdf(analytics = {}, options = {}) {
  const pdfMake = await getPdfMake();
  const from = text(analytics?.period?.from, "consulta").replaceAll(/[^0-9A-Za-z_-]/g, "-");
  const to = text(analytics?.period?.to, from).replaceAll(/[^0-9A-Za-z_-]/g, "-");
  const type = options.contentType ? `_${String(options.contentType).replaceAll(/[^0-9A-Za-z_-]/g, "-")}` : "";
  const filename = `informe-estadisticas-web_${from}_${to}${type}.pdf`;
  pdfMake.createPdf(buildWebAnalyticsPdfDefinition(analytics, options)).download(filename);
  return filename;
}
