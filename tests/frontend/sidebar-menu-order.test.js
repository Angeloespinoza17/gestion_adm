import { describe, expect, it } from "vitest";

import { sortSidebarMenuItems } from "../../resources/js/components/sidebar-menu-order";

const visibleLabels = (items) =>
  items.filter((item) => !item.isTitle && !item.isLayout).map((item) => item.label);

describe("sidebar menu ordering", () => {
  it("keeps Inicio first, sorts authorized modules alphabetically, and keeps administration entries last", () => {
    const title = { id: "title", label: "Menú", isTitle: true };
    const layout = { id: "layout", isLayout: true };
    const items = [
      title,
      { id: 1, label: "Trabajo Social" },
      { id: 2, label: "Configuración", slug: "settings" },
      layout,
      { id: 3, label: "Ámbitos de coordinación" },
      { id: 4, label: "Inicio" },
      { id: 5, label: "Superadmin", slug: "superadmin" },
      { id: 6, label: "Psicología Escolar" },
    ];

    const sorted = sortSidebarMenuItems(items);

    expect(visibleLabels(sorted)).toEqual([
      "Inicio",
      "Ámbitos de coordinación",
      "Psicología Escolar",
      "Trabajo Social",
      "Superadmin",
      "Configuración",
    ]);
    expect(sorted[0]).toBe(title);
    expect(sorted[3]).toBe(layout);
  });

  it("uses the translated visible label, preserves submenus, and does not mutate the source", () => {
    const submenu = [{ id: 21, label: "Casos" }];
    const items = [
      { id: 1, label: "menu.zeta" },
      { id: 2, label: "menu.alfa", subItems: submenu },
      { id: 3, label: "System", slug: "configuration" },
      { id: 4, label: "Administration", slug: "superadmin" },
    ];
    const originalOrder = items.map((item) => item.id);
    const translations = {
      "menu.zeta": "Zoología",
      "menu.alfa": "Apoderados",
      System: "Configuración",
      Administration: "Superadmin",
    };

    const sorted = sortSidebarMenuItems(items, (label) => translations[label] || label);

    expect(sorted.map((item) => item.id)).toEqual([2, 1, 4, 3]);
    expect(sorted[0].subItems).toBe(submenu);
    expect(items.map((item) => item.id)).toEqual(originalOrder);
  });

  it("recognizes the dashboard route even before its visible label is normalized to Inicio", () => {
    const sorted = sortSidebarMenuItems([
      { id: 1, label: "Apoderados" },
      { id: 2, label: "Panel principal", slug: "dashboard", link: "/inicio" },
      { id: 3, label: "Configuración", slug: "settings" },
      { id: 4, label: "Superadmin", slug: "superadmin" },
    ]);

    expect(sorted.map((item) => item.id)).toEqual([2, 1, 4, 3]);
  });
});
