// @vitest-environment jsdom

import { readFileSync } from "node:fs";
import { describe, expect, it } from "vitest";
import SideNav from "../../resources/js/components/side-nav.vue";

const sideNav = readFileSync("resources/js/components/side-nav.vue", "utf8");
const notifications = readFileSync("resources/js/components/internal-notifications.vue", "utf8");
const premiumStyles = readFileSync("resources/sass/custom/components/_premium.scss", "utf8");

describe("Diseño del sidenav", () => {
  it("conserva la navegación y convierte el pie en un centro personal compacto", () => {
    expect(sideNav).toContain('to="/account/profile"');
    expect(sideNav).toContain("Mi espacio");
    expect(sideNav).toContain("Cuenta personal");
    expect(sideNav).toContain("Mi perfil");
    expect(sideNav).toContain("Acceso institucional protegido");
    expect(sideNav).toContain('aria-label="Abrir mi perfil"');
    expect(sideNav).toContain("userInitials()");
    expect(sideNav).toContain("sidebarAvatarFailed");
    expect(sideNav).toContain('@error="onSidebarAvatarError"');
    expect(sideNav).toContain('alt=""');
    expect(sideNav).not.toContain("avatarPlaceholder");
  });

  it("muestra el estado de notificaciones y evita que el panel quede recortado", () => {
    expect(notifications).toContain(':container="sidebar ? \'body\' : undefined"');
    expect(notifications).toContain(':dropend="sidebar"');
    expect(notifications).toContain('"Todo al día"');
    expect(notifications).toContain('"pendientes"');
    expect(notifications).toContain(".internal-notifications__menu.show");
    expect(notifications).toContain("animation: none !important");
    expect(notifications).toContain("transform: none !important");
    expect(notifications).toContain("width: calc(100vw - 1.5rem) !important");
    expect(notifications).toContain("max-width: none !important");
    expect(notifications).toContain("max-height: calc(100dvh - 1.5rem) !important");
    expect(notifications).toContain(":global(body.vertical-collpsed .internal-notifications__copy)");
    expect(notifications).not.toContain(":global(body.vertical-collpsed) .internal-notifications__copy");
  });

  it("define jerarquía visual para estados activos, submenús y modo compacto", () => {
    expect(premiumStyles).toContain("#sidebar-menu > ul > li > a.router-link-active");
    expect(premiumStyles).toContain("#sidebar-menu ul.sub-menu");
    expect(premiumStyles).toContain(".sidebar-account__actions");
    expect(premiumStyles).toContain(".sidebar-account__avatar img");
    expect(premiumStyles).toContain("color: transparent");
    expect(premiumStyles).toContain("body.vertical-collpsed .premium-sidebar .sidebar-footer__heading");
    expect(premiumStyles).toContain(".sidebar-footer__security");
  });

  it("reemplaza una miniatura inválida por las iniciales sin conservar texto roto", () => {
    const state = {
      sidebarAvatarFailed: false,
      sidebarAvatarUrl: "/storage/users/99/profile/inexistente.jpg",
    };

    expect(SideNav.computed.hasSidebarAvatar.call(state)).toBe(true);

    SideNav.methods.onSidebarAvatarError.call(state);

    expect(state.sidebarAvatarFailed).toBe(true);
    expect(SideNav.computed.hasSidebarAvatar.call(state)).toBe(false);
    expect(SideNav.computed.sidebarAvatarUrl.call({
      sidebarUser: { profile_photo_url: "  /storage/avatar.webp  " },
    })).toBe("/storage/avatar.webp");
  });
});
