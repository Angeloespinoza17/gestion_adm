// @vitest-environment jsdom

import axios from "axios";
import { beforeEach, describe, expect, it, vi } from "vitest";
import router from "../../resources/js/router/index";
import { resetMessagingAccess } from "../../resources/js/modules/messaging/services/messagingAccess";

vi.mock("../../resources/js/views/home.vue", () => ({
  default: { template: "<div>Inicio</div>" },
}));

vi.mock("../../resources/js/modules/messaging/views/MessagingView.vue", () => ({
  default: { template: "<div>Mensajería</div>" },
}));

vi.mock("axios", () => ({
  default: {
    defaults: { headers: { common: {} } },
    get: vi.fn(),
  },
}));

describe("guard de rutas de mensajería para funcionarios", () => {
  beforeEach(async () => {
    localStorage.clear();
    resetMessagingAccess();
    axios.get.mockReset();
    await router.replace("/").catch(() => null);
  });

  it("redirige estudiante, apoderado y superadmin no funcionario a Inicio", async () => {
    localStorage.setItem("token", "non-staff-route-token");
    axios.get.mockResolvedValue({ data: { data: ["__superadmin__"] } });

    await router.push("/mensajeria");

    expect(router.currentRoute.value.path).toBe("/inicio");
    expect(axios.get).toHaveBeenCalledTimes(1);
  });

  it("permite la ruta sólo cuando el backend confirma __staff__", async () => {
    localStorage.setItem("token", "staff-route-token");
    axios.get.mockResolvedValue({
      data: { data: ["__superadmin__", "__staff__"] },
    });

    await router.push("/mensajeria");

    expect(router.currentRoute.value.name).toBe("messaging");
    expect(router.currentRoute.value.meta.staffOnly).toBe(true);
  });

  it("falla cerrado cuando no puede comprobar la identidad", async () => {
    localStorage.setItem("token", "unverified-route-token");
    axios.get.mockRejectedValue(new Error("permissions unavailable"));

    await router.push("/mensajeria");

    expect(router.currentRoute.value.path).toBe("/inicio");
  });
});
