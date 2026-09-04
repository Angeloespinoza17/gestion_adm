import { describe, expect, it } from "vitest";
import { formatRoleApiError, normalizeRoleAccessIds } from "../../resources/js/utils/role-permissions";

describe("Administración de roles", () => {
  it("muestra el detalle de validación entregado por Laravel", () => {
    const message = formatRoleApiError({
      response: {
        data: {
          message: "The given data was invalid.",
          errors: {
            "permissions.4": ["El permiso seleccionado ya no está activo."],
            "modules.2": ["El módulo seleccionado ya no está activo."],
          },
        },
      },
    });

    expect(message).toBe("El permiso seleccionado ya no está activo. El módulo seleccionado ya no está activo.");
  });

  it("normaliza IDs y elimina duplicados antes de guardar", () => {
    expect(normalizeRoleAccessIds(["7", 7, 0, null, "abc", 12])).toEqual([7, 12]);
  });
});
