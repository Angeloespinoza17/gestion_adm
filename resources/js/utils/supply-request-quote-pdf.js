import { getPdfMake } from "./pdfmake.js";
import { fetchPdfCompatibleImage } from "./pdf-image.js";

const cleanText = (value, fallback = "—") => String(value ?? "").trim() || fallback;
const quantity = (value) => new Intl.NumberFormat("es-CL", { maximumFractionDigits: 2 }).format(Number(value || 0));
const unit = (value) => ({ metro_cubico: "metro cúbico", bidon: "bidón" }[value] || cleanText(value));
const date = (value) => {
  if (!value) return "—";
  const parsed = new Date(`${String(value).slice(0, 10)}T12:00:00`);
  return Number.isNaN(parsed.getTime()) ? cleanText(value) : parsed.toLocaleDateString("es-CL");
};

const photoCell = (item, images) => {
  const source = images[item.id];
  if (!source) {
    return { text: "Sin foto", alignment: "center", color: "#93a0af", fontSize: 6.5, margin: [0, 11, 0, 0] };
  }

  return { image: source, fit: [38, 34], alignment: "center", margin: [0, 1, 0, 1] };
};

export function buildSupplyRequestQuotePdfDefinition(request, images = {}, generatedAt = null) {
  const lines = (request.items || []).map((item, index) => [
    { text: String(index + 1), alignment: "center", color: "#718095", margin: [0, 10, 0, 0] },
    photoCell(item, images),
    {
      stack: [
        { text: cleanText(item.item_name_snapshot), bold: true, color: "#24354f" },
        ...(item.description_snapshot ? [{ text: item.description_snapshot, fontSize: 6.8, color: "#718095", margin: [0, 2, 0, 0] }] : []),
      ],
      margin: [0, 5, 0, 0],
    },
    { text: quantity(item.final_quantity ?? item.requested_quantity), alignment: "right", bold: true, margin: [0, 10, 0, 0] },
    { text: unit(item.unit_snapshot), alignment: "center", fontSize: 7, margin: [0, 10, 0, 0] },
    { text: "", margin: [0, 10, 0, 0] },
    { text: "", margin: [0, 10, 0, 0] },
  ]);

  return {
    pageSize: "A4",
    pageMargins: [38, 42, 38, 48],
    info: {
      title: `Solicitud de cotización ${cleanText(request.folio, "Abastecimiento")}`,
      subject: "Solicitud de cotización de insumos de aseo",
      author: "Colegio Nuestra Señora del Carmen",
    },
    footer: (currentPage, pageCount) => ({
      margin: [38, 10, 38, 0],
      columns: [
        { text: `Abastecimiento · ${cleanText(request.folio)}`, fontSize: 7, color: "#7b8798" },
        { text: `Página ${currentPage} de ${pageCount}`, fontSize: 7, alignment: "right", color: "#7b8798" },
      ],
    }),
    content: [
      {
        table: {
          widths: ["*", 154],
          body: [[
            {
              border: [false, false, false, false],
              fillColor: "#173f67",
              margin: [18, 16, 18, 16],
              stack: [
                { text: "COLEGIO NUESTRA SEÑORA DEL CARMEN", fontSize: 7.2, bold: true, color: "#a9deda", characterSpacing: 1.1 },
                { text: "SOLICITUD DE COTIZACIÓN", fontSize: 17, bold: true, color: "#ffffff", margin: [0, 5, 0, 0] },
                { text: "Insumos de aseo · Lista final revisada", fontSize: 8.5, color: "#dce8f3", margin: [0, 4, 0, 0] },
              ],
            },
            {
              border: [false, false, false, false],
              fillColor: "#16857f",
              color: "#ffffff",
              alignment: "right",
              margin: [12, 18, 14, 15],
              stack: [
                { text: "FOLIO", fontSize: 7, bold: true, color: "#c8f0ed", characterSpacing: 1 },
                { text: cleanText(request.folio), fontSize: 10.5, bold: true, margin: [0, 5, 0, 0] },
                { text: `Emisión: ${date(generatedAt || new Date().toISOString())}`, fontSize: 7.5, margin: [0, 6, 0, 0] },
              ],
            },
          ]],
        },
        layout: "noBorders",
        margin: [0, 0, 0, 16],
      },
      { text: "DATOS DE LA SOLICITUD", style: "sectionTitle" },
      {
        table: {
          widths: [78, "*", 72, "*"],
          body: [
            [{ text: "Solicitud", style: "label" }, { text: cleanText(request.title), style: "value" }, { text: "Fecha requerida", style: "label" }, { text: date(request.needed_by), style: "value" }],
            [{ text: "Solicitado por", style: "label" }, { text: cleanText(request.creator?.name), style: "value" }, { text: "Destino", style: "label" }, { text: cleanText(request.destination), style: "value" }],
            [{ text: "Revisado por", style: "label" }, { text: cleanText(request.reviewer?.name), style: "value" }, { text: "Estado", style: "label" }, { text: cleanText(request.status_label), style: "value" }],
          ],
        },
        layout: { hLineWidth: () => 0.45, vLineWidth: () => 0.45, hLineColor: () => "#dce4ed", vLineColor: () => "#dce4ed" },
        margin: [0, 7, 0, 15],
      },
      {
        table: {
          widths: ["*", "*", "*"],
          body: [[
            { stack: [{ text: "EMPRESA PROVEEDORA", style: "quoteLabel" }, { text: " ", margin: [0, 7, 0, 7] }] },
            { stack: [{ text: "RUT", style: "quoteLabel" }, { text: " ", margin: [0, 7, 0, 7] }] },
            { stack: [{ text: "CONTACTO / VIGENCIA", style: "quoteLabel" }, { text: " ", margin: [0, 7, 0, 7] }] },
          ]],
        },
        layout: { hLineWidth: () => 0.55, vLineWidth: () => 0.55, hLineColor: () => "#cad5e0", vLineColor: () => "#cad5e0" },
        margin: [0, 0, 0, 16],
      },
      { text: "LISTA FINAL PARA COTIZAR", style: "sectionTitle" },
      { text: "Complete precio unitario y total por cada producto. Las cantidades ya fueron revisadas por Superadmin.", fontSize: 7.3, color: "#6d7c90", margin: [0, 3, 0, 7] },
      {
        table: {
          headerRows: 1,
          widths: [19, 42, "*", 45, 43, 51, 54],
          body: [[
            { text: "N°", style: "tableHead", alignment: "center" },
            { text: "Foto", style: "tableHead", alignment: "center" },
            { text: "Producto / especificación", style: "tableHead" },
            { text: "Cantidad", style: "tableHead", alignment: "right" },
            { text: "Unidad", style: "tableHead", alignment: "center" },
            { text: "P. unitario", style: "tableHead", alignment: "right" },
            { text: "Total", style: "tableHead", alignment: "right" },
          ], ...(lines.length ? lines : [["", "", "Sin productos", "", "", "", ""]])],
        },
        layout: {
          fillColor: (row) => row === 0 ? "#eaf2f7" : (row % 2 === 0 ? "#f9fbfc" : null),
          hLineWidth: () => 0.45,
          vLineWidth: () => 0,
          hLineColor: () => "#d9e2eb",
          paddingTop: () => 5,
          paddingBottom: () => 5,
          paddingLeft: () => 5,
          paddingRight: () => 5,
        },
        margin: [0, 0, 0, 12],
      },
      {
        table: {
          widths: ["*", 132],
          body: [[
            { text: [{ text: "Observaciones para cotización: ", bold: true }, cleanText(request.review_notes || request.notes, "Sin observaciones.")], color: "#526279", margin: [10, 9, 10, 9] },
            { text: "TOTAL NETO\n\nIVA\n\nTOTAL", bold: true, alignment: "right", fontSize: 7.5, lineHeight: 1.45, margin: [8, 8, 8, 8] },
          ]],
        },
        layout: { hLineWidth: () => 0.5, vLineWidth: () => 0.5, hLineColor: () => "#d9e2eb", vLineColor: () => "#d9e2eb" },
      },
      {
        columns: [
          { width: "45%", margin: [0, 48, 0, 0], stack: [
            { canvas: [{ type: "line", x1: 0, y1: 0, x2: 210, y2: 0, lineWidth: 0.7, lineColor: "#68778b" }] },
            { text: cleanText(request.creator?.name), alignment: "center", fontSize: 8, bold: true, margin: [0, 5, 0, 0] },
            { text: "Responsable de la solicitud", alignment: "center", fontSize: 7, color: "#718095" },
          ] },
          { width: "10%", text: "" },
          { width: "45%", margin: [0, 48, 0, 0], stack: [
            { canvas: [{ type: "line", x1: 0, y1: 0, x2: 210, y2: 0, lineWidth: 0.7, lineColor: "#68778b" }] },
            { text: cleanText(request.reviewer?.name, "Superadmin"), alignment: "center", fontSize: 8, bold: true, margin: [0, 5, 0, 0] },
            { text: "Revisión y autorización", alignment: "center", fontSize: 7, color: "#718095" },
          ] },
        ],
      },
      { text: "Documento generado desde la revisión de solicitudes de Abastecimiento. La lista impresa corresponde a las cantidades finales.", style: "notice", margin: [0, 20, 0, 0] },
    ],
    styles: {
      sectionTitle: { fontSize: 8.7, bold: true, color: "#173f67", characterSpacing: 0.75 },
      label: { fontSize: 6.8, bold: true, color: "#66758a", fillColor: "#f5f8fb", margin: [5, 5, 5, 5] },
      value: { fontSize: 8, color: "#28384f", margin: [5, 5, 5, 5] },
      quoteLabel: { fontSize: 6.6, bold: true, color: "#67768a", characterSpacing: 0.45 },
      tableHead: { fontSize: 6.7, bold: true, color: "#3d4e64" },
      notice: { fontSize: 6.7, italics: true, alignment: "center", color: "#7b8798" },
    },
    defaultStyle: { fontSize: 8, lineHeight: 1.18, color: "#34445a" },
  };
}

export async function prepareSupplyRequestQuotePdf(request) {
  const pairs = await Promise.all((request.items || []).map(async (item) => {
    const result = await fetchPdfCompatibleImage(item.photo_url);
    return [item.id, result.dataUrl];
  }));

  return Object.fromEntries(pairs.filter(([, dataUrl]) => dataUrl));
}

export async function downloadSupplyRequestQuote(request, generatedAt = null) {
  const [pdfMake, images] = await Promise.all([
    getPdfMake(),
    prepareSupplyRequestQuotePdf(request),
  ]);
  pdfMake.createPdf(buildSupplyRequestQuotePdfDefinition(request, images, generatedAt))
    .download(`solicitud-cotizacion-${request.folio || request.id}.pdf`);
}
