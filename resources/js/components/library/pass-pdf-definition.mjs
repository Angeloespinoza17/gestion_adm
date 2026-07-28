const STATUS_META = {
  emitido: { label: "VIGENTE", accent: "#5268dd", soft: "#eef1ff" },
  utilizado: { label: "UTILIZADO", accent: "#218765", soft: "#e9f7f1" },
  vencido: { label: "VENCIDO", accent: "#c77924", soft: "#fff4e5" },
  anulado: { label: "ANULADO", accent: "#687284", soft: "#eef0f3" },
};

const textValue = (value, fallback = "No informado") => {
  const normalized = String(value ?? "").trim();
  return normalized || fallback;
};

const formatDateTime = (value) => {
  if (!value) return "No informado";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return textValue(value);

  return new Intl.DateTimeFormat("es-CL", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
  }).format(date);
};

const formatDate = (value) => {
  if (!value) return "No informado";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return textValue(value);

  return new Intl.DateTimeFormat("es-CL", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(date);
};

const durationLabel = (from, until) => {
  const fromDate = new Date(from);
  const untilDate = new Date(until);
  const minutes = Math.round((untilDate.getTime() - fromDate.getTime()) / 60000);
  if (!Number.isFinite(minutes) || minutes <= 0) return "Horario autorizado";
  if (minutes < 60) return `${minutes} minutos autorizados`;

  const hours = Math.floor(minutes / 60);
  const remainder = minutes % 60;
  return remainder ? `${hours} h ${remainder} min autorizados` : `${hours} h autorizadas`;
};

const detailCell = (label, value, options = {}) => ({
  colSpan: options.colSpan,
  fillColor: options.fillColor || "#f6f8fc",
  margin: options.margin || [11, 9, 11, 9],
  stack: [
    {
      text: label.toUpperCase(),
      fontSize: 6.5,
      bold: true,
      color: options.labelColor || "#718097",
      characterSpacing: 0.75,
    },
    {
      text: textValue(value),
      fontSize: options.fontSize || 9.5,
      bold: options.bold !== false,
      color: options.color || "#22324b",
      margin: [0, 3, 0, 0],
      lineHeight: 1.15,
    },
  ],
});

const signatureCell = (label, name, rut, signatureData) => ({
  margin: [8, 4, 8, 0],
  stack: [
    signatureData
      ? { image: signatureData, fit: [125, 40], alignment: "center", margin: [0, 0, 0, 3] }
      : { text: "\n\n", fontSize: 12 },
    {
      canvas: [{
        type: "line",
        x1: 0,
        y1: 0,
        x2: 138,
        y2: 0,
        lineWidth: 0.7,
        lineColor: "#8090a6",
      }],
      alignment: "center",
    },
    { text: label, fontSize: 6.5, bold: true, color: "#4b5b73", alignment: "center", margin: [0, 5, 0, 2] },
    { text: textValue(name, "Nombre y firma"), fontSize: 7, color: "#66758a", alignment: "center" },
    rut ? { text: `RUT: ${rut}`, fontSize: 6.5, color: "#7d8999", alignment: "center", margin: [0, 2, 0, 0] } : null,
  ].filter(Boolean),
});

export function buildLibraryPassPdfDefinition(pass, options = {}) {
  const meta = STATUS_META[pass.status] || STATUS_META.emitido;
  const course = pass.course_section?.display_name || pass.course_name_snapshot;
  const professorRut = pass.professor?.rut || "";
  const signatureData = String(pass.signature_data || "").startsWith("data:image")
    ? pass.signature_data
    : null;
  const generatedAt = options.generatedAt || new Date();
  const logoDataUrl = options.logoDataUrl || null;

  return {
    pageSize: "A4",
    pageMargins: [42, 36, 42, 38],
    content: [
      {
        table: {
          widths: [64, "*", 118],
          body: [[
            {
              fillColor: "#172b4d",
              margin: [8, 8, 8, 8],
              stack: logoDataUrl
                ? [{ image: logoDataUrl, fit: [46, 50], alignment: "center" }]
                : [{ text: "CNSC", fontSize: 13, bold: true, color: "#ffffff", alignment: "center", margin: [0, 16, 0, 15] }],
            },
            {
              fillColor: "#172b4d",
              margin: [8, 12, 8, 10],
              stack: [
                { text: "COLEGIO NUESTRA SEÑORA DEL CARMEN", fontSize: 7, bold: true, color: "#9edbd4", characterSpacing: 0.9 },
                { text: "Pase de Biblioteca", fontSize: 21, bold: true, color: "#ffffff", margin: [0, 5, 0, 2] },
                { text: "Autorización temporal de ingreso y permanencia", fontSize: 7.5, color: "#cbd6e5" },
              ],
            },
            {
              fillColor: "#172b4d",
              margin: [5, 11, 11, 8],
              stack: [
                {
                  table: {
                    widths: ["*"],
                    body: [[{
                      text: meta.label,
                      fillColor: meta.accent,
                      color: "#ffffff",
                      bold: true,
                      fontSize: 7,
                      characterSpacing: 0.8,
                      alignment: "center",
                      margin: [5, 5, 5, 5],
                    }]],
                  },
                  layout: "noBorders",
                },
                { text: textValue(pass.pass_code, "SIN CÓDIGO"), fontSize: 8, bold: true, color: "#ffffff", alignment: "right", margin: [0, 8, 0, 2] },
                { text: `Emitido: ${formatDate(pass.issued_at)}`, fontSize: 6.5, color: "#b9c5d5", alignment: "right" },
              ],
            },
          ]],
        },
        layout: "noBorders",
        margin: [0, 0, 0, 13],
      },
      {
        table: {
          widths: [5, "*", 126],
          body: [[
            { text: "", fillColor: meta.accent },
            {
              fillColor: meta.soft,
              margin: [10, 8, 10, 8],
              stack: [
                { text: "VIGENCIA DEL PASE", fontSize: 6.5, bold: true, color: meta.accent, characterSpacing: 0.9 },
                { text: `${formatDateTime(pass.valid_from)} - ${formatDateTime(pass.valid_until)}`, fontSize: 10, bold: true, color: "#253650", margin: [0, 3, 0, 0] },
              ],
            },
            {
              text: durationLabel(pass.valid_from, pass.valid_until),
              fillColor: meta.soft,
              fontSize: 7,
              bold: true,
              color: meta.accent,
              alignment: "right",
              margin: [5, 16, 10, 10],
            },
          ]],
        },
        layout: "noBorders",
        margin: [0, 0, 0, 14],
      },
      { text: "ESTUDIANTE AUTORIZADA", style: "sectionEyebrow" },
      {
        table: {
          widths: ["*", "*", 115],
          body: [
            [
              detailCell("Nombre completo", pass.student_name_snapshot, { colSpan: 2, fontSize: 12 }),
              {},
              detailCell("Curso", course, { color: meta.accent }),
            ],
            [
              detailCell("RUT", pass.student_rut_snapshot),
              detailCell("Profesor/a responsable", pass.professor_name_snapshot, { colSpan: 2 }),
              {},
            ],
          ],
        },
        layout: {
          hLineColor: () => "#ffffff",
          vLineColor: () => "#ffffff",
          hLineWidth: () => 4,
          vLineWidth: () => 4,
        },
        margin: [0, 2, 0, 10],
      },
      { text: "MOTIVO Y ALCANCE", style: "sectionEyebrow" },
      {
        table: {
          widths: ["*"],
          body: [[detailCell("Motivo autorizado", pass.reason, {
            fontSize: 10,
            fillColor: "#f6f8fc",
            margin: [12, 10, 12, 11],
          })]],
        },
        layout: "noBorders",
        margin: [0, 2, 0, 10],
      },
      {
        table: {
          widths: [5, "*"],
          body: [[
            { text: "", fillColor: "#7a61c9" },
            {
              fillColor: "#f4f0ff",
              margin: [10, 8, 10, 8],
              stack: [
                { text: textValue(pass.regulation_version, "Reglamento de Biblioteca vigente"), fontSize: 8, bold: true, color: "#6049aa" },
                {
                  text: "Este pase es personal e intransferible. Su uso supone el conocimiento y cumplimiento del reglamento de Biblioteca y de las indicaciones del personal responsable.",
                  fontSize: 7,
                  color: "#6f6686",
                  lineHeight: 1.2,
                  margin: [0, 4, 0, 0],
                },
              ],
            },
          ]],
        },
        layout: "noBorders",
        margin: [0, 0, 0, 15],
      },
      { text: "VALIDACIÓN Y FIRMAS", style: "sectionEyebrow" },
      {
        table: {
          widths: ["*", "*", "*"],
          body: [[
            signatureCell(
              "Estudiante / persona firmante",
              pass.signature_name || pass.student_name_snapshot,
              pass.signature_rut || pass.student_rut_snapshot,
              signatureData,
            ),
            signatureCell("Profesor/a responsable", pass.professor_name_snapshot, professorRut),
            signatureCell("Recepción Biblioteca / Inspectoría", "", ""),
          ]],
        },
        layout: "noBorders",
        margin: [0, 3, 0, 15],
      },
      pass.notes
        ? {
          table: {
            widths: ["*"],
            body: [[detailCell("Observaciones", pass.notes, { bold: false, fontSize: 8 })]],
          },
          layout: "noBorders",
          margin: [0, 0, 0, 10],
        }
        : null,
      {
        table: {
          widths: ["*", "auto"],
          body: [[
            {
              text: `Código de verificación: ${textValue(pass.pass_code, "Sin código")} · Documento de gestión interna`,
              fontSize: 6.5,
              color: "#7f8a9b",
              margin: [0, 7, 0, 0],
            },
            {
              text: `Generado: ${formatDateTime(generatedAt)}`,
              fontSize: 6.5,
              color: "#7f8a9b",
              alignment: "right",
              margin: [0, 7, 0, 0],
            },
          ]],
        },
        layout: {
          hLineWidth: (index) => (index === 0 ? 0.7 : 0),
          hLineColor: () => "#d7dde7",
          vLineWidth: () => 0,
        },
      },
    ].filter(Boolean),
    styles: {
      sectionEyebrow: {
        fontSize: 7,
        bold: true,
        color: "#63728a",
        characterSpacing: 1,
        margin: [0, 1, 0, 3],
      },
    },
    defaultStyle: {
      font: "Roboto",
      fontSize: 8,
      color: "#2f3d52",
    },
  };
}
