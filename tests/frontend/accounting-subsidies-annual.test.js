import { readFileSync } from "node:fs";
import { describe, expect, it } from "vitest";

const view = readFileSync("resources/js/views/accounting/index.vue", "utf8");

describe("Resumen anual de subvenciones", () => {
  it("ofrece acceso directo y una lectura ejecutiva del año", () => {
    expect(view).toContain("Resumen anual");
    expect(view).toContain('ref="subsidyAnnualSection"');
    expect(view).toContain("Panorama consolidado de subvenciones");
    expect(view).toContain("Total liquidado");
    expect(view).toContain("Brecha frente a Ingresos");
    expect(view).toContain("Promedio por mes cargado");
  });

  it("compara la tendencia mensual y explica la composición", () => {
    expect(view).toContain("Liquidado frente a contabilizado");
    expect(view).toContain("subsidyAnnualColumnHeight(item, 'net_liquidated')");
    expect(view).toContain("subsidyAnnualColumnHeight(item, 'income_total')");
    expect(view).toContain("Participación por subvención");
    expect(view).toContain("subsidyAnnualOverview.by_family");
  });

  it("mantiene navegación mensual, estados y diseño responsive", () => {
    expect(view).toContain("selectSubsidyAnnualMonth(item)");
    expect(view).toContain("subsidyAnnualMonthStatus(item.status)");
    expect(view).toContain("Sin transferencia informada");
    expect(view).toContain("@media(max-width:720px){.subsidy-annual-hero");
    expect(view).toContain("min-width:940px");
  });
});
