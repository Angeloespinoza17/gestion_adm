import { describe, expect, it } from "vitest";
import { readFileSync } from "node:fs";

const view = readFileSync("resources/js/views/supplies/index.vue", "utf8");

describe("Abastecimiento · personal habilitado para recibir OT", () => {
  it("reemplaza nombre, RUT y cargo manuales por un selector de funcionarios", () => {
    expect(view).toContain("Funcionario receptor");
    expect(view).toContain('v-model="deliveryForm.recipient_staff_id"');
    expect(view).toContain(':options="catalogs.delivery_recipients"');
    expect(view).toContain("Recibe OT");
    expect(view).toContain("Buscar personal que recibe OT");
    expect(view).not.toContain("Puede crear OT");
    expect(view).not.toContain('v-model.trim="deliveryForm.recipient_name"');
    expect(view).not.toContain('v-model.trim="deliveryForm.recipient_rut"');
    expect(view).not.toContain('v-model.trim="deliveryForm.recipient_role"');
  });

  it("mantiene destino editable y bloquea el registro sin receptor", () => {
    expect(view).toContain('v-model.trim="deliveryForm.destination"');
    expect(view).toContain('saving || !deliveryForm.recipient_staff_id');
    expect(view).toContain("onDeliveryRecipientChange");
  });
});
