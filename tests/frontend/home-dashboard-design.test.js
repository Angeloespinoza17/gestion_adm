import { readFileSync } from "node:fs";
import { describe, expect, it } from "vitest";

const home = readFileSync("resources/js/views/home.vue", "utf8");

describe("Diseño del inicio institucional", () => {
  it("presenta la jornada como un centro de operaciones personalizado", () => {
    expect(home).toContain("Centro de operaciones");
    expect(home).toContain("Tu jornada institucional, ordenada para decidir qué atender primero.");
    expect(home).toContain("Lo importante ahora");
    expect(home).toContain("Vista personalizada");
    expect(home).toContain('aria-label="Resumen de hoy"');
  });

  it("mantiene las métricas legibles y compactas en pantallas pequeñas", () => {
    expect(home).toContain('cols="6" xl="3"');
    expect(home).toContain("inicio-metrics-grid");
    expect(home).toContain("grid-template-columns: repeat(2, minmax(0, 1fr));");
  });

  it("adapta el calendario a móvil sin depender del tema Bootstrap", () => {
    expect(home).toContain('initialView: compact ? "listMonth" : "dayGridMonth"');
    expect(home).toContain('contentHeight: compact ? "auto" : 560');
    expect(home).toContain('themeSystem: "standard"');
    expect(home).not.toContain("bootstrap5Plugin");
  });

  it("normaliza iconos y da identidad visual a los accesos rápidos", () => {
    expect(home).toContain('"bx-calendar-star": "bx-calendar-event"');
    expect(home).toContain('"bx-message-square-detail": "bx-envelope"');
    expect(home).toContain(":class=\"quickLinkToneClass(index)\"");
    expect(home).toContain("quickLinkIcon(link)");
    expect(home).toContain("inicio-quick-link--tone-5");
  });

  it("distingue las comunicaciones internas de los paneles genéricos", () => {
    expect(home).toContain("inicio-communications-card");
    expect(home).toContain("Comunicaciones para ti");
    expect(home).toContain("inicio-communications-card__body");
    expect(home).toContain(".inicio-announcement::before");
  });

  it("muestra el pronóstico diario persistido para Valdivia", () => {
    expect(home).toContain("Clima en {{ weatherLocation.name || 'Valdivia' }}");
    expect(home).toContain("Región de Los Ríos");
    expect(home).toContain('v-for="(day, index) in weatherDays"');
    expect(home).toContain("day.chance_of_rain");
    expect(home).toContain("day.avg_humidity");
    expect(home).toContain("Datos de WeatherAPI.com");
    expect(home).not.toContain("WEATHERAPI_KEY");
  });
});
