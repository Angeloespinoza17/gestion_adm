// @vitest-environment jsdom

import { flushPromises, shallowMount } from "@vue/test-utils";
import axios from "axios";
import { beforeEach, describe, expect, it, vi } from "vitest";
import App from "../../resources/js/App.vue";
import SideNav from "../../resources/js/components/side-nav.vue";
import {
  filterMessagingStaffUsers,
  hasMessagingStaffAccess,
  loadMessagingAccess,
  messagingAccess,
  resetMessagingAccess,
  revokeMessagingAccess,
} from "../../resources/js/modules/messaging/services/messagingAccess";

vi.mock("axios", () => ({
  default: {
    defaults: { headers: { common: {} } },
    get: vi.fn(),
  },
}));

const mountApp = () => shallowMount(App, {
  global: {
    mocks: { $route: { path: "/inicio" } },
    stubs: {
      RouterView: true,
      MessagingMiniChat: {
        name: "MessagingMiniChat",
        template: "<div data-test='messaging-mini-chat'></div>",
      },
    },
  },
});

describe("acceso frontend de mensajería para funcionarios", () => {
  beforeEach(() => {
    localStorage.clear();
    localStorage.setItem("token", "staff-session-token");
    axios.get.mockReset();
    resetMessagingAccess();
  });

  it("comparte una sola consulta de identidad y exige el marcador __staff__", async () => {
    axios.get.mockResolvedValue({
      data: { data: ["__staff__", "messaging.send_announcement"] },
    });

    const [first, second] = await Promise.all([
      loadMessagingAccess(),
      loadMessagingAccess(),
    ]);

    expect(axios.get).toHaveBeenCalledTimes(1);
    expect(axios.get).toHaveBeenCalledWith("/api/me/permissions", {
      headers: {
        Authorization: "Bearer staff-session-token",
        "X-Authorization": "Bearer staff-session-token",
        "X-Api-Token": "staff-session-token",
      },
    });
    expect(first).toEqual(second);
    expect(messagingAccess.resolved).toBe(true);
    expect(messagingAccess.allowed).toBe(true);
    expect(hasMessagingStaffAccess(["__superadmin__"])).toBe(false);
    expect(hasMessagingStaffAccess(["__superadmin__", "__staff__"])).toBe(true);
  });

  it("no monta el minichat para estudiantes, apoderados ni superadmin sin identidad funcionaria", async () => {
    axios.get.mockResolvedValue({ data: { data: ["__superadmin__"] } });

    const wrapper = mountApp();
    await flushPromises();

    expect(messagingAccess.resolved).toBe(true);
    expect(messagingAccess.allowed).toBe(false);
    expect(wrapper.find("[data-test='messaging-mini-chat']").exists()).toBe(false);
    wrapper.unmount();
  });

  it("monta el minichat sólo después de confirmar la identidad funcionaria", async () => {
    axios.get.mockResolvedValue({ data: { data: ["__staff__"] } });

    const wrapper = mountApp();
    expect(wrapper.find("[data-test='messaging-mini-chat']").exists()).toBe(false);

    await flushPromises();

    expect(wrapper.find("[data-test='messaging-mini-chat']").exists()).toBe(true);

    revokeMessagingAccess({
      response: { status: 403, data: { code: "MESSAGING_STAFF_ONLY" } },
    });
    await flushPromises();
    expect(wrapper.find("[data-test='messaging-mini-chat']").exists()).toBe(false);
    expect(localStorage.getItem("permissions")).toBeNull();
    wrapper.unmount();
  });

  it("oculta la ruta en navegación aunque exista __superadmin__ sin __staff__", () => {
    const route = {
      matched: [{ meta: { staffOnly: true } }],
      meta: { staffOnly: true },
    };
    const context = {
      $router: { resolve: vi.fn(() => route) },
      filterMenuByPermissions: SideNav.methods.filterMenuByPermissions,
    };
    const items = [{ id: "messaging", label: "Mensajería", link: "/mensajeria" }];

    expect(
      SideNav.methods.filterMenuByPermissions.call(context, items, ["__superadmin__"])
    ).toEqual([]);
    expect(
      SideNav.methods.filterMenuByPermissions.call(context, items, ["__superadmin__", "__staff__"])
    ).toHaveLength(1);
  });

  it("no inventa un acceso de mensajería ausente en /api/me/modules", () => {
    const context = {
      shouldHideMenuItem: () => false,
      resolveModuleLandingLink: (item) => item.frontend_route || null,
      resolveMenuIcon: () => "bx-home-circle",
    };

    const items = SideNav.methods.buildMenuFromModules.call(context, [
      {
        id: 1,
        parent_id: null,
        name: "Inicio",
        slug: "dashboard",
        frontend_route: "/inicio",
      },
    ]);

    expect(items.map((item) => item.link)).toEqual(["/inicio"]);
    expect(items.some((item) => item.link === "/mensajeria")).toBe(false);
  });

  it("retira el acceso visible del menú cuando la condición funcionaria se revoca", () => {
    const context = {
      withoutMessagingItems: SideNav.methods.withoutMessagingItems,
    };
    const items = [
      { id: 1, slug: "dashboard", link: "/inicio" },
      { id: 2, slug: "messaging", link: "/mensajeria" },
      {
        id: 3,
        slug: "workspace",
        subItems: [{ id: 4, link: "/mensajeria" }],
      },
    ];

    expect(SideNav.methods.withoutMessagingItems.call(context, items)).toEqual([
      items[0],
    ]);
  });

  it("acepta exclusivamente destinatarios confirmados como funcionarios", () => {
    const candidates = [
      { id: 1, name: "Funcionaria", is_staff: true },
      { id: 2, name: "Estudiante", is_staff: false },
      { id: 3, name: "Contrato antiguo sin tipo" },
      { id: 4, name: "Marcador inválido", is_staff: 1 },
    ];

    expect(filterMessagingStaffUsers(candidates)).toEqual([candidates[0]]);
  });
});
