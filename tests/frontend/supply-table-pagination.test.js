import { describe, expect, it } from "vitest";
import { readFileSync } from "node:fs";

const view = readFileSync("resources/js/views/supplies/index.vue", "utf8");

describe("Abastecimiento · paginación del catálogo", () => {
  it("presenta el rango total y navegación accesible en la tabla", () => {
    expect(view).toContain('class="table-pagination"');
    expect(view).toContain("Mostrando <strong>{{ paginationFrom }}–{{ paginationTo }}</strong>");
    expect(view).toContain('aria-label="Ir a la página anterior"');
    expect(view).toContain('aria-label="Ir a la página siguiente"');
    expect(view).toContain(":aria-current=\"page === pagination.current_page ? 'page' : undefined\"");
  });

  it("limita los botones visibles y solicita la página seleccionada a la API", () => {
    expect(view).toContain("const visibleCount = Math.min(5, lastPage)");
    expect(view).toContain("this.pagination.current_page = targetPage");
    expect(view).toContain("await this.loadItems()");
    expect(view).toContain("page: this.pagination.current_page");
  });

  it("reinicia la página al aplicar filtros o cambiar de submódulo", () => {
    expect(view).toContain('if (resetPage) this.pagination.current_page = 1');
    expect(view).toMatch(/"\$route\.meta\.supplySection"\(\)[\s\S]*?this\.pagination\.current_page = 1;[\s\S]*?this\.loadAll\(\)/);
  });
});
