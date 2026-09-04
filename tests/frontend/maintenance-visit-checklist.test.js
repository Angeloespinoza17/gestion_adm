import { describe, expect, it } from "vitest";
import fs from "node:fs";
import path from "node:path";

const source = fs.readFileSync(
  path.resolve(process.cwd(), "resources/js/views/maintenance/visit-checklist.vue"),
  "utf8"
);

describe("maintenance dependency review", () => {
  it("uses the focused checklist endpoint without loading the full visits catalog", () => {
    expect(source).toContain("/checklist`);");
    expect(source).not.toContain('axios.get("/api/maintenance/visits/catalogs")');
  });

  it("provides touch states, filtering and an explicit unsaved-change action", () => {
    expect(source).toContain("status-selector");
    expect(source).toContain("statusFilter");
    expect(source).toContain("activeSystem");
    expect(source).toContain("Cambios sin guardar");
  });

  it("supports direct camera capture and multiple gallery photos", () => {
    expect(source).toContain('capture="environment"');
    expect(source).toMatch(/type="file" accept="image\/\*" multiple/);
    expect(source).toContain('payload.append("photos[]", file)');
    expect(source).toContain("photoEntries(item.id).length");
    expect(source).toContain("Máximo 3 fotos");
    expect(source).toContain("photoEntries(item.id).length }} / 3");
  });
});
