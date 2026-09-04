import { readFileSync } from "node:fs";
import { describe, expect, it } from "vitest";

const source = readFileSync("resources/js/views/maintenance/annual-plan.vue", "utf8");

describe("maintenance annual plan compact filters", () => {
  it("keeps the primary filters compact and moves secondary criteria to an explicit disclosure", () => {
    expect(source).toContain('showAdvancedFilters: false');
    expect(source).toContain('class="annual-filters annual-filters--primary"');
    expect(source).toContain('v-show="showAdvancedFilters" class="annual-filters annual-filters--advanced"');
    expect(source).toContain('Más filtros');
    expect(source).toContain('advancedFiltersCount');
  });

  it("keeps filter actions visible and provides a single-column responsive fallback", () => {
    expect(source).toContain('class="annual-filter-actions"');
    expect(source).toContain('.annual-filters--primary .annual-filter-actions');
    expect(source).toContain('.annual-filters--advanced .annual-field');
    expect(source).toContain('grid-column: 1 / -1;');
  });
});
