import { describe, expect, it } from "vitest";
import { readFileSync } from "node:fs";
import { buildSupplyDeliveryPdfDefinition } from "../../resources/js/utils/supply-delivery-pdf";

const view = readFileSync("resources/js/views/supplies/index.vue", "utf8");
const requestsView = readFileSync("resources/js/views/supplies/requests.vue", "utf8");
const router = readFileSync("resources/js/router/index.js", "utf8");

describe("Abastecimiento · Pañol de mantenimiento", () => {
  it("expone el submódulo y adapta el catálogo para herramientas y artículos", () => {
    expect(router).toContain('path: "/supplies/maintenance-storeroom"');
    expect(router).toContain('supplySection: "maintenance_storeroom"');
    expect(view).toContain("Pañol de mantenimiento");
    expect(view).toContain("Nueva herramienta o artículo");
    expect(view).toContain("Registrar ingreso");
    expect(view).toContain("Registrar salida");
    expect(view).toContain("Incorporar desde Inventario");
    expect(view).toContain("Nueva bodega");
    expect(view).toContain("/api/supplies/storerooms/inventory-candidates");
    expect(view).toContain("inventory_item_ids: this.selectedInventoryIds");
    expect(requestsView).toContain('/supplies/maintenance-storeroom');
  });

  it("identifica el pañol y sus artículos en el acta PDF de salida", () => {
    const definition = buildSupplyDeliveryPdfDefinition({
      folio: "ABA-ENT-2026-000010",
      section: "maintenance_storeroom",
      delivered_at: "2026-09-01",
      recipient_name: "Funcionario de mantención",
      storeroom: { name: "Pañol taller norte" },
      items: [{
        item_name_snapshot: "Taladro percutor",
        quantity: 1,
        unit_snapshot: "unidad",
      }],
    });
    const content = JSON.stringify(definition.content);

    expect(definition.info.subject).toBe("Pañol de mantenimiento");
    expect(content).toContain("DETALLE DE HERRAMIENTAS Y ARTÍCULOS");
    expect(content).toContain("Taladro percutor");
    expect(content).toContain("Pañol taller norte");
  });
});
