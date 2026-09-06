// @vitest-environment jsdom

import { mount } from "@vue/test-utils";
import { readFileSync } from "node:fs";
import { beforeEach, describe, expect, it } from "vitest";
import SiteAdminNavigation from "../../resources/js/components/public-site/site-admin-navigation.vue";

const mountNavigation = (path = "/admin/noticias") => mount(SiteAdminNavigation, {
  global: {
    mocks: { $route: { path } },
    stubs: {
      RouterLink: { props: ["to"], template: "<a :href='to'><slot /></a>" },
    },
  },
});

describe("Interfaz interna del sitio web", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("muestra una navegación unificada y sólo las secciones autorizadas", () => {
    localStorage.setItem("permissions", JSON.stringify(["ver_noticias", "ver_contactos_sitio"]));
    const wrapper = mountNavigation();

    expect(wrapper.text()).toContain("Gestión del sitio web");
    expect(wrapper.find('a[href="/admin/noticias"]').exists()).toBe(true);
    expect(wrapper.find('a[href="/admin/contactos"]').exists()).toBe(true);
    expect(wrapper.find('a[href="/admin/eventos"]').exists()).toBe(false);
    expect(wrapper.find('a[href="/admin/noticias"]').attributes("aria-current")).toBe("page");
  });

  it("prioriza el grupo de la sección actual para mantenerla siempre visible", () => {
    localStorage.setItem("permissions", JSON.stringify(["__superadmin__"]));
    const wrapper = mountNavigation("/admin/contactos");

    expect(wrapper.find(".site-admin-navigation__group-label").text()).toBe("Bandeja");
    expect(wrapper.find('a[href="/admin/contactos"]').attributes("aria-current")).toBe("page");
  });

  it("incorpora el navegador común en todas las familias del módulo", () => {
    const files = [
      "resources/js/views/admin/news.vue",
      "resources/js/views/admin/events.vue",
      "resources/js/views/admin/contact-messages.vue",
      "resources/js/components/public-site/web-content-manager.vue",
      "resources/js/components/public-site/site-organization-manager.vue",
    ];

    files.forEach((file) => {
      expect(readFileSync(file, "utf8")).toContain("SiteAdminNavigation");
    });
  });

  it("oculta las acciones de escritura a perfiles de sólo lectura", () => {
    const news = readFileSync("resources/js/views/admin/news.vue", "utf8");
    const events = readFileSync("resources/js/views/admin/events.vue", "utf8");
    const contacts = readFileSync("resources/js/views/admin/contact-messages.vue", "utf8");

    expect(news).toContain('v-if="canManage"');
    expect(news).toContain('this.catalogs.capabilities?.can_manage');
    expect(events).toContain('this.catalogs.capabilities?.can_manage');
    expect(contacts).toContain('v-if="canManage && item.status !== \'responded\'"');
    expect(contacts).toContain(':disabled="!canManage"');
  });

  it("usa acciones de tamaño legible y presenta estados accesibles", () => {
    const contentManager = readFileSync("resources/js/components/public-site/web-content-manager.vue", "utf8");
    const contacts = readFileSync("resources/js/views/admin/contact-messages.vue", "utf8");

    expect(contentManager).toContain("width: 38px;");
    expect(contacts).toContain("contact-action-btn is-labeled");
    expect(contacts).toContain('title="Responder por correo"');
    expect(contacts).toContain('aria-label="`Eliminar mensaje de ${item.full_name}`"');
  });
});
