import { describe, expect, it } from "vitest";
import { buildSupplyDeliveryPdfDefinition } from "../../resources/js/utils/supply-delivery-pdf";

describe("acta PDF de Abastecimiento", () => {
  it("incluye folio, receptor, destino, líneas, unidades legibles y firmas", () => {
    const definition = buildSupplyDeliveryPdfDefinition({
      id: 8,
      folio: "ABA-ENT-2026-000008",
      section: "heating",
      delivered_at: "2026-08-31",
      recipient_name: "María Auxiliar",
      recipient_rut: "12.345.678-5",
      recipient_role: "Auxiliar de servicios",
      destination: "Edificio principal",
      delivered_by: { name: "Responsable de Abastecimiento" },
      items: [{
        item_name_snapshot: "Leña seca",
        quantity: 1.5,
        unit_snapshot: "metro_cubico",
        notes: "Medición recepcionada",
        supply_item: { inventory_item: { code: "INV-CALEF-2026-0003" } },
      }],
    }, "2026-09-01T10:15:00-04:00");

    const content = JSON.stringify(definition.content);
    const detailTable = definition.content.find((block) => block.table?.headerRows === 1).table;
    expect(definition.pageSize).toBe("A4");
    expect(content).toContain("ABA-ENT-2026-000008");
    expect(content).toContain("María Auxiliar");
    expect(content).toContain("Edificio principal");
    expect(content).toContain("Leña seca");
    expect(content).toContain("metro cúbico");
    expect(content).toContain("Firma persona receptora");
    expect(content).toContain("Responsable de entrega");
    expect(detailTable.body[0].map((cell) => cell.text)).toEqual(["N°", "Insumo", "Cantidad", "Unidad", "Observación"]);
    expect(detailTable.body.every((row) => row.length === detailTable.widths.length)).toBe(true);
  });
});
