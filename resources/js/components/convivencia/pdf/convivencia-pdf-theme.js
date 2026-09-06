import { convivenciaProtocolRuntimeLabel } from "../protocol-runtime-labels";

export const CONVIVENCIA_PDF_COLORS = {
  navy: "#263A8F",
  indigo: "#4F63D9",
  teal: "#2E9F83",
  ink: "#273252",
  text: "#445069",
  muted: "#758198",
  line: "#DCE3EE",
  soft: "#F5F7FB",
  danger: "#C54B52",
  warning: "#D58A22",
  success: "#258A70",
};

const LABELS = {
  active: "Activo",
  activo: "Activo",
  inactive: "Inactivo",
  inactivo: "Inactivo",
  draft: "Borrador",
  borrador: "Borrador",
  open: "Abierto",
  closed: "Cerrado",
  archived: "Archivado",
  archivado: "Archivado",
  pending: "Pendiente",
  pendiente: "Pendiente",
  in_progress: "En proceso",
  en_proceso: "En proceso",
  completed: "Completado",
  completado: "Completado",
  cumplida: "Cumplida",
  omitted: "Omitido",
  omitido: "Omitido",
  not_applicable: "No aplica",
  no_aplica: "No aplica",
  overdue: "Vencido",
  vencido: "Vencido",
  due_soon: "Próximo a vencer",
  on_time: "En plazo",
  critical: "Crítica",
  high: "Alta",
  medium: "Media",
  low: "Baja",
  warning: "Advertencia",
  vigente: "Vigente",
  hours: "horas",
  hour: "hora",
  calendar_days: "días corridos",
  business_days: "días hábiles",
  school_days: "días hábiles escolares",
  activation: "activación",
  activation_started: "activación del protocolo",
  step_started: "inicio de la etapa",
  previous_step_completed: "término de la etapa anterior",
  previous_step: "etapa anterior",
  incident: "hecho informado",
  safeguard_measure: "Medida protectora",
  protective_measure: "Medida protectora",
  sanction: "Sanción",
  formative_measure: "Medida formativa",
  reparative_measure: "Medida reparadora",
  restorative_measure: "Medida restaurativa",
  required_document: "Documento obligatorio",
  interview: "Entrevista",
  communication: "Comunicación",
  external_report: "Denuncia o derivación externa",
  evidence: "Evidencia",
  condition: "Condición",
  action: "Acción",
  document: "Documento",
  notification: "Notificación",
  internal_referral: "Derivación interna",
  external_referral: "Derivación externa",
  appeal: "Apelación",
  follow_up: "Seguimiento",
  closure: "Cierre",
  special_rule: "Regla especial",
  all: "Deben cumplirse todas",
  any: "Debe cumplirse al menos una",
  role: "Rol",
  affected: "Persona afectada",
  education_scope: "Ámbito educativo",
  allow_not_applicable: "Permite marcar no aplicable",
  blocked_for_preschool_child: "Bloqueada para párvulo",
  requires_guardian: "Requiere apoderado",
  condition_confirmed: "Condición confirmada",
  evidence_recorded: "Evidencia registrada",
  agreement_act_signed: "Acta de acuerdo firmada",
  notification_recorded: "Notificación registrada",
  field: "Campo",
  operator: "Operador",
  value: "Valor",
  summary: "Resumen",
  custom_condition: "Condición especial",
  present: "Presente",
  external: "plazo externo",
  external_defined: "plazo definido externamente",
  other: "Otra parte",
};

export const pdfArray = (value) => {
  if (Array.isArray(value)) return value;
  if (value === null || value === undefined || value === "") return [];
  return [value];
};

export const pdfText = (value, fallback = "-") => {
  if (value === null || value === undefined || value === "") return fallback;
  if (typeof value === "boolean") return value ? "Sí" : "No";
  if (Array.isArray(value)) {
    const resolved = value.map((item) => pdfText(item, "")).filter(Boolean);
    return resolved.length ? resolved.join(", ") : fallback;
  }
  if (typeof value === "object") {
    const preferred = value.name || value.title || value.label || value.stage_name || value.code;
    if (preferred) return String(preferred);
    const resolved = Object.entries(value)
      .filter(([, item]) => item !== null && item !== undefined && item !== "")
      .map(([key, item]) => `${pdfLabel(key)}: ${pdfText(item, "")}`)
      .filter(Boolean);
    return resolved.length ? resolved.join(" | ") : fallback;
  }
  return String(value);
};

export const pdfLabel = (value) => {
  if (value === null || value === undefined || value === "") return "-";
  const normalized = String(value).trim().toLowerCase().replace(/[\s-]+/g, "_");
  const runtimeLabel = convivenciaProtocolRuntimeLabel(value);
  if (runtimeLabel) return runtimeLabel;
  if (LABELS[normalized]) return LABELS[normalized];
  return String(value)
    .replaceAll("_", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
};

export const pdfStructuredText = (value, fallback = "-") => {
  if (value === null || value === undefined || value === "") return fallback;
  if (typeof value === "boolean") return value ? "Sí" : "No";
  if (typeof value === "number") return String(value);
  if (typeof value === "string") return pdfLabel(value);
  if (Array.isArray(value)) {
    const items = value.map((item) => pdfStructuredText(item, "")).filter(Boolean);
    return items.length ? items.join(" · ") : fallback;
  }
  if (typeof value === "object") {
    const items = Object.entries(value)
      .filter(([, item]) => item !== null && item !== undefined && item !== "")
      .map(([key, item]) => `${pdfLabel(key)}: ${pdfStructuredText(item, "")}`)
      .filter(Boolean);
    return items.length ? items.join(" | ") : fallback;
  }
  return String(value);
};

export const deadlineResolutionFor = (subject = {}) => subject?.snapshot?.deadline_resolution
  || subject?.deadline_resolution
  || subject?.snapshot?.metadata?.deadline_resolution
  || subject?.metadata?.deadline_resolution
  || null;

export const deadlineFallbackText = (subject = {}, fallback = "") => {
  const resolution = deadlineResolutionFor(subject);
  if (resolution?.fallback_used !== true) return fallback;
  const reason = pdfText(resolution.reason || resolution.fallback_reason, "");
  return [
    "Plazo estimado por falta de calendario escolar; la fecha alternativa no constituye un vencimiento confirmado.",
    reason ? `Motivo: ${reason}` : null,
  ].filter(Boolean).join(" ");
};

export const pdfDate = (value, withTime = false) => {
  if (!value) return "-";
  const source = String(value);
  const parsed = new Date(source.length === 10 ? `${source}T12:00:00` : source);
  if (Number.isNaN(parsed.getTime())) return source;
  return new Intl.DateTimeFormat("es-CL", {
    dateStyle: "medium",
    ...(withTime ? { timeStyle: "short" } : {}),
  }).format(parsed);
};

export const safePdfFilePart = (value, fallback = "documento") => {
  const normalized = String(value || fallback)
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-zA-Z0-9_-]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 80);
  return normalized || fallback;
};

export const pdfSection = (number, title, detail = "") => ({
  margin: [0, 13, 0, 7],
  columns: [
    {
      width: 28,
      text: String(number).padStart(2, "0"),
      style: "sectionNumber",
    },
    {
      width: "*",
      stack: [
        { text: title, style: "sectionTitle" },
        ...(detail ? [{ text: detail, style: "sectionDetail" }] : []),
      ],
    },
  ],
});

export const pdfFacts = (rows, labelWidth = 128) => ({
  table: {
    widths: [labelWidth, "*"],
    body: rows
      .filter((row) => Array.isArray(row) && row.length >= 2)
      .map(([name, value]) => [
        { text: pdfText(name), style: "fieldLabel" },
        { text: pdfText(value), style: "fieldValue" },
      ]),
  },
  layout: {
    hLineWidth: (index) => (index === 0 ? 0 : 0.45),
    vLineWidth: () => 0,
    hLineColor: () => CONVIVENCIA_PDF_COLORS.line,
    fillColor: (row) => (row % 2 ? "#FAFBFD" : null),
    paddingLeft: () => 7,
    paddingRight: () => 7,
    paddingTop: () => 5,
    paddingBottom: () => 5,
  },
  margin: [0, 0, 0, 8],
});

export const pdfRecord = (title, meta, rows, tone = "default", unbreakable = true) => {
  const tones = {
    default: { fill: "#F8FAFC", line: "#DCE3EE", title: CONVIVENCIA_PDF_COLORS.ink },
    active: { fill: "#F1F4FF", line: "#BFC9F5", title: CONVIVENCIA_PDF_COLORS.navy },
    protected: { fill: "#F0FAF7", line: "#B9E0D4", title: "#1D6F5B" },
    warning: { fill: "#FFF8ED", line: "#EFD7AE", title: "#98601D" },
  };
  const palette = tones[tone] || tones.default;

  return {
    unbreakable,
    table: {
      widths: ["*"],
      body: [[{
        margin: [9, 8, 9, 8],
        fillColor: palette.fill,
        stack: [
          {
            columns: [
              { text: pdfText(title), style: "recordTitle", color: palette.title },
              { text: pdfText(meta, ""), style: "recordMeta", alignment: "right" },
            ],
          },
          ...rows
            .filter(([, value]) => value !== null && value !== undefined && value !== "" && pdfText(value, "") !== "")
            .map(([name, value]) => ({
              margin: [0, 4, 0, 0],
              text: [
                { text: `${pdfText(name)}: `, bold: true, color: "#526175" },
                { text: pdfText(value), color: CONVIVENCIA_PDF_COLORS.text },
              ],
            })),
        ],
      }]],
    },
    layout: {
      hLineWidth: () => 0.65,
      vLineWidth: () => 0.65,
      hLineColor: () => palette.line,
      vLineColor: () => palette.line,
    },
    margin: [0, 0, 0, 7],
  };
};

export const pdfEmpty = (message) => ({
  text: message,
  style: "empty",
  margin: [0, 0, 0, 9],
});

export const protocolDeadlineText = (step = {}) => {
  const value = step.deadline_value ?? step.due_days ?? step.deadline?.value;
  const unit = step.deadline_unit || step.deadline?.unit || (step.due_days ? "calendar_days" : null);
  const anchor = step.deadline_anchor || step.deadline?.anchor;
  if (!value) return "Sin plazo configurado";
  return `${value} ${pdfLabel(unit)}${anchor ? ` desde ${pdfLabel(anchor).toLowerCase()}` : ""}`;
};

export function buildConvivenciaPdfDefinition({
  title,
  kicker,
  code,
  status,
  subtitle,
  sensitive = true,
  generatedAt = new Date().toISOString(),
  content = [],
  info = {},
}) {
  const colors = CONVIVENCIA_PDF_COLORS;
  const documentCode = pdfText(code, "Sin código");
  const codeColumnWidth = Math.min(178, Math.max(116, documentCode.length * 4.35));

  return {
    pageSize: "A4",
    pageMargins: [38, 62, 38, 52],
    info: {
      title: `${title} ${documentCode}`,
      subject: info.subject || "Documento de Convivencia Escolar",
      author: info.author || "CNSC Gestión",
      keywords: info.keywords || "convivencia escolar, protocolo, RICE",
    },
    ...(sensitive
      ? { watermark: { text: "CONFIDENCIAL", color: colors.navy, opacity: 0.045, bold: true } }
      : {}),
    header: (currentPage) => currentPage === 1 ? null : ({
      margin: [38, 20, 38, 0],
      columns: [
        {
          text: `${pdfText(kicker || "CONVIVENCIA ESCOLAR")} | ${documentCode}`,
          fontSize: 7,
          bold: true,
          color: colors.navy,
          characterSpacing: 0.5,
        },
        {
          text: pdfText(title).toUpperCase(),
          alignment: "right",
          fontSize: 6.5,
          color: colors.muted,
        },
      ],
    }),
    footer: (currentPage, pageCount) => ({
      margin: [38, 10, 38, 0],
      columns: [
        {
          width: "*",
          text: `CNSC Gestión | Emitido ${pdfDate(generatedAt, true)}${sensitive ? " | Documento reservado" : " | Documento institucional"}`,
          fontSize: 6.5,
          color: colors.muted,
        },
        {
          width: 74,
          text: `Página ${currentPage} de ${pageCount}`,
          alignment: "right",
          fontSize: 6.5,
          color: colors.muted,
        },
      ],
    }),
    content: [
      {
        table: {
          widths: ["*", codeColumnWidth],
          body: [[
            {
              border: [false, false, false, false],
              fillColor: colors.navy,
              color: "#FFFFFF",
              margin: [15, 13, 13, 13],
              stack: [
                { text: pdfText(kicker || "CONVIVENCIA ESCOLAR"), style: "coverKicker" },
                { text: pdfText(title).toUpperCase(), style: "coverTitle" },
                ...(subtitle ? [{ text: pdfText(subtitle), style: "coverSubtitle" }] : []),
              ],
            },
            {
              border: [false, false, false, false],
              fillColor: colors.indigo,
              color: "#FFFFFF",
              alignment: "right",
              margin: [10, 13, 13, 13],
              stack: [
                { text: documentCode, fontSize: 8, bold: true, noWrap: true },
                { text: pdfLabel(status).toUpperCase(), fontSize: 6.5, color: "#DDE3FF", margin: [0, 6, 0, 0] },
              ],
            },
          ]],
        },
        layout: "noBorders",
        margin: [0, 0, 0, 12],
      },
      ...content,
    ],
    styles: {
      coverKicker: { fontSize: 7, bold: true, characterSpacing: 1.2, color: "#C9D2FF" },
      coverTitle: { fontSize: 15.5, bold: true, margin: [0, 3, 0, 0] },
      coverSubtitle: { fontSize: 8, color: "#E8ECFF", margin: [0, 4, 0, 0], lineHeight: 1.15 },
      sectionNumber: { fontSize: 8, bold: true, color: colors.indigo, alignment: "center", margin: [0, 4, 0, 4] },
      sectionTitle: { fontSize: 9.5, bold: true, color: colors.navy, characterSpacing: 0.25 },
      sectionDetail: { fontSize: 6.8, color: colors.muted, margin: [0, 2, 0, 0] },
      fieldLabel: { bold: true, color: "#526175", fontSize: 7.1 },
      fieldValue: { color: colors.ink, fontSize: 7.4 },
      recordTitle: { fontSize: 8.1, bold: true },
      recordMeta: { fontSize: 6.5, color: colors.muted },
      empty: { italics: true, color: colors.muted, fontSize: 7.3 },
    },
    defaultStyle: { fontSize: 7.3, lineHeight: 1.2, color: colors.text },
  };
}
