import { describe, expect, it } from "vitest";
import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const requester = fs.readFileSync(path.join(root, "resources/js/views/supplies/requests.vue"), "utf8");
const reviewer = fs.readFileSync(path.join(root, "resources/js/views/superadmin/supply-requests.vue"), "utf8");
const router = fs.readFileSync(path.join(root, "resources/js/router/index.js"), "utf8");

describe("solicitudes de abastecimiento", () => {
  it("permite precargar inventario, agregar productos y enviar cantidades iniciales", () => {
    expect(requester).toContain("Productos del inventario");
    expect(requester).toContain("addCatalogItem");
    expect(requester).toContain("Agregar producto fuera de inventario");
    expect(requester).toContain("requested_quantity");
    expect(requester).toContain('capture="environment"');
  });

  it("mantiene el formulario compacto, guiado y con acciones siempre identificables", () => {
    expect(requester).toContain('class="modal-panel request-form-modal"');
    expect(requester).toContain('class="request-builder"');
    expect(requester).toContain('class="request-form-footer"');
    expect(requester).toContain("requestItemSummary");
    expect(requester).toContain("@media(max-width:620px)");
    expect(requester).toContain("Enviar a Superadmin");
  });

  it("expone una revisión exclusiva que conserva cantidades y genera el PDF final", () => {
    expect(reviewer).toContain("Herramienta exclusiva de Superadmin");
    expect(reviewer).toContain("requested_quantity");
    expect(reviewer).toContain("final_quantity");
    expect(reviewer).toContain("Guardar y generar PDF");
    expect(reviewer).toContain("downloadSupplyRequestQuote");
    expect(router).toContain('path: "/superadmin/solicitudes-abastecimiento"');
    expect(router).toContain("superAdminOnly: true");
  });
});
