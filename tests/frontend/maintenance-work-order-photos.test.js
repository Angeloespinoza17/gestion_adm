import { describe, expect, it } from "vitest";
import { readFileSync } from "node:fs";

const view = readFileSync("resources/js/views/maintenance/work-orders.vue", "utf8");

describe("maintenance work order photos", () => {
  it("supports camera and multiple gallery selection with a three-photo counter", () => {
    expect(view).toContain('capture="environment"');
    expect(view).toMatch(/type="file" class="d-none" accept="image\/\*" multiple/);
    expect(view).toContain('payload.append("photos[]", photo)');
    expect(view).toContain("{{ totalPhotoCount }} / 3");
    expect(view).toContain("photoLimitReached");
  });

  it("shows selected files and supports removing persisted evidence", () => {
    expect(view).toContain("work-order-selected-photos");
    expect(view).toContain("removeSelectedPhoto(photoIndex)");
    expect(view).toContain("deleteWorkOrderPhoto(activeWorkOrder, photo)");
    expect(view).toContain("/photos/${photo.id}");
  });
});
