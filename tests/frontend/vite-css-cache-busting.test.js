import { readFileSync } from "node:fs";
import { describe, expect, it } from "vitest";

const viteConfig = readFileSync("vite.config.mjs", "utf8");
const appLayout = readFileSync("resources/views/layouts/app.blade.php", "utf8");

describe("Versionado de estilos en producción", () => {
  it("genera nombres CSS con hash para evitar mezclar JS nuevo con estilos antiguos", () => {
    expect(viteConfig).toContain("[name]-[hash]");
    expect(viteConfig).not.toContain("`[name]` + '.min.'");
  });

  it("resuelve el CSS crítico del login desde el manifest vigente", () => {
    expect(appLayout).toContain("build/manifest.json");
    expect(appLayout).toContain("resources/js/views/account/login.vue");
    expect(appLayout).toContain("$loginEntry['css'][0]");
  });
});
