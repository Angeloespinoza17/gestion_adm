import { describe, expect, it } from "vitest";
import { readFileSync } from "node:fs";

const view = readFileSync("resources/js/views/supplies/index.vue", "utf8");

describe("Abastecimiento · personal habilitado para recibir OT", () => {
  it("reemplaza nombre, RUT y cargo manuales por un selector de funcionarios", () => {
    expect(view).toContain("Funcionario receptor");
    expect(view).toContain('v-model="deliveryForm.recipient_staff_id"');
    expect(view).toContain(':options="catalogs.delivery_recipients"');
    expect(view).toContain("Recibe OT");
    expect(view).toContain("Buscar por nombre, cargo o RUT");
    expect(view).not.toContain("Puede crear OT");
    expect(view).not.toContain('v-model.trim="deliveryForm.recipient_name"');
    expect(view).not.toContain('v-model.trim="deliveryForm.recipient_rut"');
    expect(view).not.toContain('v-model.trim="deliveryForm.recipient_role"');
  });

  it("mantiene destino editable y bloquea el registro sin receptor", () => {
    expect(view).toContain('v-model.trim="deliveryForm.destination"');
    expect(view).toContain('saving || !deliveryIsReady');
    expect(view).toContain("onDeliveryRecipientChange");
  });

  it("convierte el producto en un buscador remoto con contexto de stock", () => {
    expect(view).toContain('class="delivery-product-select"');
    expect(view).toContain(':options="searchDeliveryItems"');
    expect(view).toContain(':filter-results="false"');
    expect(view).toContain(':delay="260"');
    expect(view).toContain('placeholder="Buscar producto por nombre o código..."');
    expect(view).toContain('stock_status: "available"');
    expect(view).toContain("active_only: 1");
    expect(view).toContain("option.code");
    expect(view).toContain("option.type_label");
    expect(view).toContain("option.stock");
  });

  it("presenta el flujo de entrega en secciones compactas y trazables", () => {
    expect(view).toContain("delivery-modal-hero");
    expect(view).toContain("Datos de la entrega");
    expect(view).toContain("Productos a entregar");
    expect(view).toContain("Movimiento protegido y trazable");
    expect(view).toContain("Acta trazable");
  });

  it("permite eliminar del registro productos con stock conservando su trazabilidad", () => {
    expect(view).toContain("¿Eliminar ${name} del registro?");
    expect(view).toContain("Si tiene stock o movimientos");
    expect(view).toContain("Sí, eliminar del registro");
  });
});
