// @vitest-environment jsdom

import { readFileSync } from "node:fs";
import { describe, expect, it } from "vitest";

describe("Identidad de la pestaña del navegador", () => {
  it("usa CNSC Valdivia en la carga inicial, navegación y alertas de mensajería", () => {
    const layout = readFileSync("resources/views/layouts/app.blade.php", "utf8");
    const router = readFileSync("resources/js/router/index.js", "utf8");
    const messaging = readFileSync(
      "resources/js/modules/messaging/components/MessagingMiniChat.vue",
      "utf8",
    );

    expect(layout).toContain("<title>CNSC Valdivia</title>");
    expect(router).toContain('document.title = "CNSC Valdivia";');
    expect(messaging).toContain('let baseDocumentTitle = "CNSC Valdivia";');
    expect(messaging).not.toContain('|| "CNSC Gestión"');
  });
});
