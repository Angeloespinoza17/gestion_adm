import { getPdfMake } from "./pdfmake";

const valueOrDash = (value) => (value === null || value === undefined || value === "" ? "-" : String(value));

const personName = (value, fallback = "-") => {
  if (value && typeof value === "object") return value.name || fallback;
  return typeof value === "string" && value.trim() ? value : fallback;
};

const formatDateTime = (value) => {
  if (!value) return "-";
  const date = new Date(String(value).replace(" ", "T"));
  if (Number.isNaN(date.getTime())) return String(value);
  return new Intl.DateTimeFormat("es-CL", { dateStyle: "long", timeStyle: "short" }).format(date);
};

const fallbackCode = (item) => {
  const year = String(item?.withdrawn_at || item?.academic_year_name_snapshot || new Date().getFullYear()).slice(0, 4);
  return `RET-${year}-${String(item?.id || 0).padStart(6, "0")}`;
};

const tableRow = (label, value) => [
  { text: label, style: "fieldLabel" },
  { text: valueOrDash(value), style: "fieldValue" },
];

export async function downloadWithdrawalReceipt(item, labels = {}) {
  if (!item) return;

  const code = item.withdrawal_code || fallbackCode(item);
  const reason = labels.reasonLabel?.(item.reason) || valueOrDash(item.reason);
  const relationship = labels.relationshipLabel?.(item.person_relationship) || valueOrDash(item.person_relationship);
  const status = labels.statusLabel?.(item.status) || valueOrDash(item.status);
  const registeredBy = personName(item.registered_by || item.registeredBy, "Portería");
  const authorizedBy = personName(item.authorized_by || item.authorizedBy, "-");
  const inspector = personName(item.inspector, item.inspector_name_snapshot || "-");

  const documentDefinition = {
    pageSize: "A4",
    pageMargins: [42, 38, 42, 38],
    info: {
      title: `Acta de retiro ${code}`,
      subject: "Constancia física de retiro de estudiante",
    },
    content: [
      {
        columns: [
          {
            width: "*",
            stack: [
              { text: "ACTA DE RETIRO DE ESTUDIANTE", style: "title" },
              { text: "REGISTRO OFICIAL DE PORTERÍA", style: "subtitle" },
            ],
          },
          {
            width: 145,
            stack: [
              { text: "FOLIO", style: "folioLabel", alignment: "right" },
              { text: code, style: "folio", alignment: "right" },
            ],
          },
        ],
      },
      { text: "Documento para respaldo físico del retiro. Debe permanecer firmado en Portería.", style: "notice", margin: [0, 14, 0, 18] },
      { text: "DATOS DEL RETIRO", style: "sectionTitle" },
      {
        table: {
          widths: [120, "*"],
          body: [
            tableRow("Fecha y hora", formatDateTime(item.withdrawn_at)),
            tableRow("Estado", status),
            tableRow("Año académico", item.academic_year_name_snapshot || item.academic_year?.name),
          ],
        },
        layout: "lightHorizontalLines",
        margin: [0, 5, 0, 18],
      },
      { text: "ESTUDIANTE", style: "sectionTitle" },
      {
        table: {
          widths: [120, "*"],
          body: [
            tableRow("Nombre", item.student_full_name_snapshot || item.student_profile?.registered_name || item.student_profile?.full_name),
            tableRow("RUT", item.student_rut_snapshot || item.student_profile?.rut),
            tableRow("Curso", item.course_name_snapshot || item.course_section?.display_name),
          ],
        },
        layout: "lightHorizontalLines",
        margin: [0, 5, 0, 18],
      },
      { text: "PERSONA QUE RETIRA", style: "sectionTitle" },
      {
        table: {
          widths: [120, "*"],
          body: [
            tableRow("Nombre", item.person_name),
            tableRow("RUT", item.person_rut),
            tableRow("Vínculo", relationship),
            tableRow("Teléfono", item.person_phone),
            tableRow("Persona autorizada", item.person_authorized ? "Sí" : "No / requiere validación"),
          ],
        },
        layout: "lightHorizontalLines",
        margin: [0, 5, 0, 18],
      },
      { text: "MOTIVO Y RESPALDO", style: "sectionTitle" },
      {
        table: {
          widths: [120, "*"],
          body: [
            tableRow("Motivo", reason),
            tableRow("Observaciones", item.observations),
            tableRow("Inspectora responsable", inspector),
            tableRow("Registro en Portería", registeredBy),
            tableRow("Autorizó", authorizedBy),
          ],
        },
        layout: "lightHorizontalLines",
        margin: [0, 5, 0, 20],
      },
      {
        text: "Declaro que retiro a la estudiante individualizada en este documento y que los datos entregados son correctos. Portería deja constancia de la fecha y hora indicadas.",
        style: "declaration",
      },
      {
        columns: [
          {
            width: "48%",
            stack: [
              { text: "\n\n\n", margin: [0, 4] },
              { canvas: [{ type: "line", x1: 0, y1: 0, x2: 220, y2: 0, lineWidth: 0.8, lineColor: "#314761" }] },
              { text: "Firma de apoderado/a o persona autorizada", style: "signatureTitle" },
              { text: valueOrDash(item.person_name), style: "signatureDetail" },
              { text: `RUT: ${valueOrDash(item.person_rut)}`, style: "signatureDetail" },
            ],
          },
          { width: "4%", text: "" },
          {
            width: "48%",
            stack: [
              { text: "\n\n\n", margin: [0, 4] },
              { canvas: [{ type: "line", x1: 0, y1: 0, x2: 220, y2: 0, lineWidth: 0.8, lineColor: "#314761" }] },
              { text: "Firma y timbre de Portería", style: "signatureTitle" },
              { text: registeredBy, style: "signatureDetail" },
              { text: `Fecha/hora: ${formatDateTime(item.withdrawn_at)}`, style: "signatureDetail" },
            ],
          },
        ],
        columnGap: 12,
        margin: [0, 14, 0, 0],
      },
    ],
    footer: (currentPage, pageCount) => ({
      text: `${code} · Página ${currentPage} de ${pageCount}`,
      alignment: "center",
      color: "#7a8998",
      fontSize: 8,
      margin: [0, 10, 0, 0],
    }),
    defaultStyle: { fontSize: 10, color: "#26384a" },
    styles: {
      title: { fontSize: 18, bold: true, color: "#173b57" },
      subtitle: { fontSize: 8.5, bold: true, color: "#59748a", characterSpacing: 1.4, margin: [0, 4, 0, 0] },
      folioLabel: { fontSize: 8, bold: true, color: "#74889a" },
      folio: { fontSize: 11, bold: true, color: "#173b57", margin: [0, 3, 0, 0] },
      notice: { fontSize: 9, bold: true, color: "#8a4f08", fillColor: "#fff5dc" },
      sectionTitle: { fontSize: 9, bold: true, color: "#1c6a59", characterSpacing: 0.8 },
      fieldLabel: { fontSize: 8.5, bold: true, color: "#62788b", fillColor: "#f4f7fa", margin: [4, 4] },
      fieldValue: { fontSize: 9.5, margin: [4, 4] },
      declaration: { fontSize: 9, italics: true, color: "#53687a", margin: [0, 2, 0, 4] },
      signatureTitle: { fontSize: 8.5, bold: true, alignment: "center", margin: [0, 7, 0, 3] },
      signatureDetail: { fontSize: 8, color: "#607488", alignment: "center", margin: [0, 1] },
    },
  };

  (await getPdfMake()).createPdf(documentDefinition).download(`acta-retiro-${code}.pdf`);
}
