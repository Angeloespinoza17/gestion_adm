import { getPdfMake } from "./pdfmake.js";

const text = (value, fallback = "—") => {
  const normalized = String(value ?? "").trim();
  return normalized || fallback;
};

const date = (value) => {
  if (!value) return "—";
  const parsed = new Date(`${String(value).slice(0, 10)}T12:00:00`);
  return Number.isNaN(parsed.getTime()) ? text(value) : parsed.toLocaleDateString("es-CL");
};

const quantity = (value) => new Intl.NumberFormat("es-CL", {
  minimumFractionDigits: 0,
  maximumFractionDigits: 2,
}).format(Number(value || 0));

const unit = (value) => ({
  metro_cubico: "metro cúbico",
  kilogramo: "kilogramo",
  cilindro: "cilindro",
  litro: "litro",
  saco: "saco",
  unidad: "unidad",
  rollo: "rollo",
  caja: "caja",
  bidon: "bidón",
  juego: "juego",
  pieza: "pieza",
  par: "par",
}[value] || text(value));

export function buildSupplyDeliveryPdfDefinition(delivery, generatedAt = null) {
  const sectionLabel = ({
    heating: "Combustibles y calefacción",
    maintenance_storeroom: "Pañol de mantenimiento",
  }[delivery.section] || "Insumos de aseo");
  const detailLabel = delivery.section === "maintenance_storeroom"
    ? "DETALLE DE HERRAMIENTAS Y ARTÍCULOS"
    : "DETALLE DE INSUMOS";
  const lines = (delivery.items || []).map((line, index) => [
    { text: String(index + 1), alignment: "center", color: "#728096" },
    { stack: [
      { text: text(line.item_name_snapshot || line.supply_item?.inventory_item?.name), bold: true, color: "#24354f" },
      { text: text(line.supply_item?.inventory_item?.code), fontSize: 7, color: "#7d8ba0", margin: [0, 2, 0, 0] },
    ] },
    { text: quantity(line.quantity), alignment: "right", bold: true },
    { text: unit(line.unit_snapshot), alignment: "center" },
    { text: text(line.notes), color: "#5f6f83" },
  ]);

  return {
    pageSize: "A4",
    pageMargins: [44, 48, 44, 50],
    info: {
      title: `Acta de entrega ${text(delivery.folio, "Abastecimiento")}`,
      subject: sectionLabel,
      author: "Colegio Nuestra Señora del Carmen",
    },
    footer: (currentPage, pageCount) => ({
      margin: [44, 10, 44, 0],
      columns: [
        { text: `Abastecimiento · ${text(delivery.folio)}`, fontSize: 7, color: "#7b8798" },
        { text: `Página ${currentPage} de ${pageCount}`, fontSize: 7, alignment: "right", color: "#7b8798" },
      ],
    }),
    content: [
      {
        table: {
          widths: ["*", 150],
          body: [[
            {
              border: [false, false, false, false],
              fillColor: "#173f67",
              margin: [18, 16, 18, 16],
              stack: [
                { text: "COLEGIO NUESTRA SEÑORA DEL CARMEN", fontSize: 7.5, bold: true, color: "#a9deda", characterSpacing: 1.2 },
                { text: "ACTA DE ENTREGA", fontSize: 20, bold: true, color: "#ffffff", margin: [0, 4, 0, 0] },
                { text: sectionLabel, fontSize: 9, color: "#dce8f3", margin: [0, 4, 0, 0] },
              ],
            },
            {
              border: [false, false, false, false],
              fillColor: "#1a8d86",
              color: "#ffffff",
              alignment: "right",
              margin: [12, 20, 14, 16],
              stack: [
                { text: "FOLIO", fontSize: 7, bold: true, color: "#c8f0ed", characterSpacing: 1 },
                { text: text(delivery.folio), fontSize: 11, bold: true, margin: [0, 5, 0, 0] },
                { text: date(delivery.delivered_at), fontSize: 8, margin: [0, 6, 0, 0] },
              ],
            },
          ]],
        },
        layout: "noBorders",
        margin: [0, 0, 0, 18],
      },
      { text: "DATOS DE LA ENTREGA", style: "sectionTitle" },
      {
        table: {
          widths: [96, "*", 96, "*"],
          body: [
            [{ text: "Persona receptora", style: "label" }, { text: text(delivery.recipient_name), style: "value" }, { text: "RUT", style: "label" }, { text: text(delivery.recipient_rut), style: "value" }],
            [{ text: "Cargo / función", style: "label" }, { text: text(delivery.recipient_role), style: "value" }, { text: "Destino", style: "label" }, { text: text(delivery.destination), style: "value" }],
            [{ text: "Entregado por", style: "label" }, { text: text(delivery.delivered_by?.name), style: "value" }, { text: "Fecha", style: "label" }, { text: date(delivery.delivered_at), style: "value" }],
            ...(delivery.section === "maintenance_storeroom" ? [[{ text: "Bodega de origen", style: "label" }, { text: text(delivery.storeroom?.name, "Pañol de mantenimiento"), style: "value", colSpan: 3 }, {}, {}]] : []),
          ],
        },
        layout: {
          hLineWidth: () => 0.5,
          vLineWidth: () => 0.5,
          hLineColor: () => "#dce4ed",
          vLineColor: () => "#dce4ed",
        },
        margin: [0, 7, 0, 18],
      },
      { text: detailLabel, style: "sectionTitle" },
      {
        table: {
          headerRows: 1,
          widths: [24, "*", 58, 62, 104],
          body: [[
            { text: "N°", style: "tableHead", alignment: "center" },
            { text: "Insumo", style: "tableHead" },
            { text: "Cantidad", style: "tableHead", alignment: "right" },
            { text: "Unidad", style: "tableHead", alignment: "center" },
            { text: "Observación", style: "tableHead" },
          ], ...(lines.length ? lines : [["", "Sin insumos", "", "", ""]])],
        },
        layout: {
          fillColor: (row) => row === 0 ? "#eef4f8" : (row % 2 === 0 ? "#f9fbfc" : null),
          hLineWidth: () => 0.45,
          vLineWidth: () => 0,
          hLineColor: () => "#dce4ed",
          paddingTop: () => 7,
          paddingBottom: () => 7,
          paddingLeft: () => 7,
          paddingRight: () => 7,
        },
        margin: [0, 7, 0, 16],
      },
      {
        table: { widths: ["*"], body: [[{
          text: [{ text: "Observaciones: ", bold: true }, text(delivery.notes, "Sin observaciones.")],
          fillColor: "#f5f8fb",
          color: "#526279",
          margin: [12, 10, 12, 10],
        }]] },
        layout: { hLineWidth: () => 0.5, vLineWidth: () => 0.5, hLineColor: () => "#dde5ee", vLineColor: () => "#dde5ee" },
      },
      {
        text: "Declaro haber recibido conforme los insumos individualizados en esta acta, en las cantidades indicadas y para el destino señalado.",
        fontSize: 9,
        lineHeight: 1.35,
        color: "#34445a",
        margin: [0, 18, 0, 0],
      },
      {
        columns: [
          { width: "45%", margin: [0, 58, 0, 0], stack: [
            { canvas: [{ type: "line", x1: 0, y1: 0, x2: 218, y2: 0, lineWidth: 0.7, lineColor: "#68778b" }] },
            { text: text(delivery.recipient_name), alignment: "center", fontSize: 8, bold: true, margin: [0, 5, 0, 0] },
            { text: "Firma persona receptora", alignment: "center", fontSize: 7, color: "#718095" },
          ] },
          { width: "10%", text: "" },
          { width: "45%", margin: [0, 58, 0, 0], stack: [
            { canvas: [{ type: "line", x1: 0, y1: 0, x2: 218, y2: 0, lineWidth: 0.7, lineColor: "#68778b" }] },
            { text: text(delivery.delivered_by?.name), alignment: "center", fontSize: 8, bold: true, margin: [0, 5, 0, 0] },
            { text: "Responsable de entrega", alignment: "center", fontSize: 7, color: "#718095" },
          ] },
        ],
      },
      { text: `Documento generado desde el módulo de Abastecimiento${generatedAt ? ` el ${new Date(generatedAt).toLocaleString("es-CL")}` : ""}.`, style: "notice", margin: [0, 24, 0, 0] },
    ],
    styles: {
      sectionTitle: { fontSize: 9, bold: true, color: "#173f67", characterSpacing: 0.8 },
      label: { fontSize: 7.2, bold: true, color: "#66758a", fillColor: "#f5f8fb", margin: [5, 5, 5, 5] },
      value: { fontSize: 8.4, color: "#28384f", margin: [5, 5, 5, 5] },
      tableHead: { fontSize: 7.2, bold: true, color: "#3d4e64" },
      notice: { fontSize: 7, italics: true, alignment: "center", color: "#7b8798" },
    },
    defaultStyle: { fontSize: 8.3, lineHeight: 1.2, color: "#34445a" },
  };
}

export async function downloadSupplyDeliveryAct(delivery, generatedAt = null) {
  const pdfMake = await getPdfMake();
  const definition = buildSupplyDeliveryPdfDefinition(delivery, generatedAt);
  pdfMake.createPdf(definition).download(`acta-entrega-${delivery.folio || delivery.id}.pdf`);
}
