import { describe, expect, it } from "vitest";
import { buildSupplyRequestQuotePdfDefinition } from "../../resources/js/utils/supply-request-quote-pdf";

describe("solicitud de cotización de Abastecimiento", () => {
  it("usa la lista final, muestra referencias fotográficas y deja campos para cotizar", () => {
    const definition = buildSupplyRequestQuotePdfDefinition({
      folio: "SOL-ABA-2026-000014",
      title: "Reposición mensual de aseo",
      needed_by: "2026-09-10",
      destination: "Bodega central",
      status_label: "Lista para cotizar",
      creator: { name: "Encargada de Abastecimiento" },
      reviewer: { name: "Super Admin" },
      review_notes: "Cotizar formato institucional.",
      items: [{
        id: 9,
        item_name_snapshot: "Detergente concentrado",
        description_snapshot: "Bidón de cinco litros",
        requested_quantity: 8,
        final_quantity: 12,
        unit_snapshot: "bidon",
      }],
    }, { 9: "data:image/png;base64,cGhvdG8=" }, "2026-09-01T12:00:00-04:00");

    const content = JSON.stringify(definition.content);
    const detail = definition.content.find((block) => block.table?.headerRows === 1).table;
    expect(content).toContain("SOL-ABA-2026-000014");
    expect(content).toContain("Detergente concentrado");
    expect(content).toContain("12");
    expect(content).not.toContain('"text":"8"');
    expect(content).toContain("data:image/png;base64,cGhvdG8=");
    expect(content).toContain("P. unitario");
    expect(content).toContain("TOTAL NETO");
    expect(detail.body.every((row) => row.length === detail.widths.length)).toBe(true);
  });
});
