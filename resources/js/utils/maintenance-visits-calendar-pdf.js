const COLORS = {
  ink: "#172033",
  muted: "#667085",
  line: "#DCE4F0",
  soft: "#F5F7FB",
  brand: "#3157C8",
  brandDark: "#203B8F",
  brandSoft: "#EEF3FF",
  green: "#087B59",
  amber: "#B36108",
  red: "#B42318",
  violet: "#6941C6",
};

const pad = (value) => String(value).padStart(2, "0");

const parseYmd = (value) => {
  const [year, month, day] = String(value || "").slice(0, 10).split("-").map(Number);
  return new Date(year, month - 1, day, 12);
};

const formatYmd = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

const formatTime = (value) => String(value || "").slice(0, 5) || "--:--";

const formatDateTime = (value) => new Intl.DateTimeFormat("es-CL", {
  day: "2-digit",
  month: "2-digit",
  year: "numeric",
  hour: "2-digit",
  minute: "2-digit",
}).format(value);

const statusColor = (status) => ({
  Programada: COLORS.amber,
  "En progreso": COLORS.brand,
  Finalizada: COLORS.green,
  Cancelada: COLORS.red,
}[status] || COLORS.muted);

const statusCounts = (visits) => {
  const count = (status) => visits.filter((visit) => visit.status === status).length;
  return [
    { label: "TOTAL", value: visits.length, detail: "visitas del período", color: COLORS.brand, fill: COLORS.brandSoft },
    { label: "PROGRAMADAS", value: count("Programada"), detail: "pendientes", color: COLORS.amber, fill: "#FFF8E8" },
    { label: "EN PROGRESO", value: count("En progreso"), detail: "en ejecución", color: COLORS.violet, fill: "#F4F0FF" },
    { label: "FINALIZADAS", value: count("Finalizada"), detail: "cerradas", color: COLORS.green, fill: "#EAF8F3" },
  ];
};

export function maintenanceDependencyCalendarDetails(dependency = {}) {
  const seen = new Set([dependency.code, dependency.name]
    .map((value) => String(value || "").trim().toLocaleLowerCase("es-CL"))
    .filter(Boolean));
  const uniqueDetails = [
    dependency.distribution,
    dependency.sector,
    dependency.zone,
    dependency.usage,
  ]
    .map((value) => String(value || "").trim())
    .filter((value) => {
      const normalized = value.toLocaleLowerCase("es-CL");
      if (!value || seen.has(normalized)) return false;
      seen.add(normalized);
      return true;
    });

  return {
    code: String(dependency.code || "").trim() || "S/C",
    name: String(dependency.name || "").trim() || "Dependencia sin nombre",
    context: uniqueDetails.join(" · "),
  };
}

export function buildMaintenanceCalendarDays(calendarMonth, visits) {
  const [year, month] = String(calendarMonth).split("-").map(Number);
  const firstDay = new Date(year, month - 1, 1, 12);
  const firstWeekday = (firstDay.getDay() + 6) % 7;
  const gridStart = new Date(firstDay);
  gridStart.setDate(firstDay.getDate() - firstWeekday);

  const byDate = new Map();
  visits.forEach((visit) => {
    const date = String(visit.visit_date || "").slice(0, 10);
    if (!byDate.has(date)) byDate.set(date, []);
    byDate.get(date).push(visit);
  });

  return Array.from({ length: 42 }, (_, index) => {
    const date = new Date(gridStart);
    date.setDate(gridStart.getDate() + index);
    const iso = formatYmd(date);

    return {
      iso,
      day: date.getDate(),
      isCurrentMonth: date.getMonth() === month - 1,
      isWeekend: date.getDay() === 0 || date.getDay() === 6,
      visits: (byDate.get(iso) || []).sort((left, right) =>
        String(left.visit_time || "").localeCompare(String(right.visit_time || ""))
      ),
    };
  });
}

function calendarCell(day, compactPerson) {
  const visibleLimit = compactPerson ? 3 : 2;
  const events = day.visits.slice(0, visibleLimit).map((visit) => {
    const dependency = maintenanceDependencyCalendarDetails(visit.dependency);
    const stack = [{
      columns: [
        { text: formatTime(visit.visit_time), width: 31, bold: true, color: statusColor(visit.status) },
        { text: dependency.code, bold: true, color: COLORS.ink, noWrap: true },
      ],
      columnGap: 3,
    }, {
      text: dependency.name,
      color: COLORS.ink,
      bold: true,
      fontSize: 5.4,
      noWrap: true,
      margin: [34, 0.5, 0, 0],
    }];

    if (dependency.context) {
      stack.push({
        text: dependency.context,
        color: COLORS.muted,
        fontSize: 4.8,
        noWrap: true,
        margin: [34, 0.5, 0, 0],
      });
    }

    if (!compactPerson) {
      stack.push({
        text: visit.responsible || "Sin responsable",
        color: COLORS.muted,
        fontSize: 4.8,
        noWrap: true,
        margin: [34, 0.5, 0, 0],
      });
    }

    return { stack, margin: [0, 1.5, 0, 0] };
  });

  if (day.visits.length > visibleLimit) {
    events.push({
      text: `+${day.visits.length - visibleLimit} visitas adicionales`,
      color: COLORS.brand,
      bold: true,
      fontSize: 5.6,
      margin: [0, 2, 0, 0],
    });
  }

  return {
    stack: [
      {
        columns: [
          { text: String(day.day), bold: true, color: day.isCurrentMonth ? COLORS.ink : "#98A2B3", fontSize: 8.5 },
          day.visits.length
            ? { text: String(day.visits.length), alignment: "right", bold: true, color: COLORS.brand, fontSize: 6.5 }
            : { text: "" },
        ],
      },
      ...events,
    ],
    fillColor: day.isCurrentMonth ? (day.isWeekend ? "#FAFBFD" : "#FFFFFF") : COLORS.soft,
    color: day.isCurrentMonth ? COLORS.ink : "#98A2B3",
    margin: [4, 3, 4, 2],
  };
}

function footer(currentPage, pageCount) {
  return {
    columns: [
      { text: "CNSC Gestión - Planificador de visitas", color: "#7A8495", fontSize: 7 },
      { text: `Uso operativo | Página ${currentPage} de ${pageCount}`, alignment: "right", color: "#7A8495", fontSize: 7 },
    ],
    margin: [30, 0, 30, 0],
  };
}

export function buildMaintenanceVisitsCalendarPdf({
  visits = [],
  calendarMonth,
  calendarTitle,
  responsibleLabel = "Todas las personas",
  filterLabels = [],
  generatedAt = new Date(),
}) {
  const days = buildMaintenanceCalendarDays(calendarMonth, visits);
  const cards = statusCounts(visits);
  const compactPerson = responsibleLabel !== "Todas las personas";
  const weekdays = ["LUN", "MAR", "MIÉ", "JUE", "VIE", "SÁB", "DOM"].map((text) => ({
    text,
    alignment: "center",
    bold: true,
    color: "#FFFFFF",
    fillColor: COLORS.brandDark,
    margin: [0, 4, 0, 4],
  }));
  const weeks = Array.from({ length: 6 }, (_, week) =>
    days.slice(week * 7, week * 7 + 7).map((day) => calendarCell(day, compactPerson))
  );

  return {
    pageSize: "A4",
    pageOrientation: "landscape",
    pageMargins: [30, 26, 30, 34],
    footer,
    defaultStyle: { fontSize: 7, color: COLORS.ink },
    content: [
      {
        columns: [
          {
            width: "*",
            stack: [
              { text: "MANTENCIÓN · AGENDA OPERATIVA", style: "eyebrow" },
              { text: "Calendario de visitas", style: "title" },
              { text: calendarTitle, style: "subtitle" },
            ],
          },
          {
            width: 205,
            table: {
              widths: ["*"],
              body: [[{
                stack: [
                  { text: "RESPONSABLE", color: "#C7D2FE", bold: true, fontSize: 6.5 },
                  { text: responsibleLabel, color: "#FFFFFF", bold: true, fontSize: 11, margin: [0, 3, 0, 2] },
                  { text: `${visits.length} visitas · generado ${formatDateTime(generatedAt)}`, color: "#E0E7FF", fontSize: 6.5 },
                ],
                fillColor: COLORS.brandDark,
                margin: [12, 9, 12, 9],
              }]],
            },
            layout: "noBorders",
          },
        ],
        columnGap: 18,
        margin: [0, 0, 0, 9],
      },
      {
        text: filterLabels.length ? filterLabels.join("  ·  ") : "Mes completo · todas las dependencias · todos los estados",
        color: COLORS.muted,
        fontSize: 7,
        margin: [0, 0, 0, 8],
      },
      {
        table: {
          widths: cards.map(() => "*"),
          body: [cards.map((card) => ({
            columns: [
              { text: String(card.value), width: 28, bold: true, color: card.color, fontSize: 15 },
              { stack: [{ text: card.label, bold: true, color: COLORS.ink, fontSize: 6.5 }, { text: card.detail, color: COLORS.muted, fontSize: 6 }] },
            ],
            columnGap: 4,
            fillColor: card.fill,
            margin: [8, 5, 8, 5],
          }))],
        },
        layout: {
          hLineWidth: () => 0,
          vLineWidth: () => 4,
          vLineColor: () => "#FFFFFF",
          paddingLeft: () => 0,
          paddingRight: () => 0,
          paddingTop: () => 0,
          paddingBottom: () => 0,
        },
        margin: [0, 0, 0, 8],
      },
      {
        table: {
          headerRows: 1,
          keepWithHeaderRows: 1,
          dontBreakRows: true,
          heights: (row) => (row === 0 ? 18 : 58),
          widths: ["*", "*", "*", "*", "*", "*", "*"],
          body: [weekdays, ...weeks],
        },
        layout: {
          hLineColor: () => COLORS.line,
          vLineColor: () => COLORS.line,
          hLineWidth: () => 0.65,
          vLineWidth: () => 0.65,
          paddingLeft: () => 0,
          paddingRight: () => 0,
          paddingTop: () => 0,
          paddingBottom: () => 0,
        },
      },
    ],
    styles: {
      eyebrow: { fontSize: 7, bold: true, color: COLORS.brand, characterSpacing: 0.7 },
      title: { fontSize: 20, bold: true, color: COLORS.ink, margin: [0, 2, 0, 2] },
      subtitle: { fontSize: 9, color: COLORS.muted },
    },
    info: {
      title: `Calendario de visitas - ${calendarTitle}`,
      subject: `Agenda de mantención para ${responsibleLabel}`,
      author: "CNSC Gestión",
    },
  };
}

export function maintenanceCalendarPdfFilename(calendarMonth, responsibleLabel) {
  const person = String(responsibleLabel || "todas-las-personas")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "")
    .slice(0, 48) || "todas-las-personas";

  return `calendario-visitas-${calendarMonth}-${person}.pdf`;
}
