import { beforeEach, describe, expect, it, vi } from "vitest";

const pdfHarness = vi.hoisted(() => ({
  definition: null,
  filename: "",
  download: vi.fn(),
}));

vi.mock("../../resources/js/utils/pdfmake", () => ({
  getPdfMake: vi.fn(async () => ({
    createPdf: (definition) => {
      pdfHarness.definition = definition;
      return {
        download: (filename) => {
          pdfHarness.filename = filename;
          pdfHarness.download(filename);
        },
      };
    },
  })),
}));

import {
  buildWebAnalyticsPdfDefinition,
  downloadWebAnalyticsPdf,
} from "../../resources/js/utils/web-analytics-pdf";

const analytics = {
  period: { from: "2026-08-08", to: "2026-09-06", days: 30, label: "8 ago - 6 sep 2026" },
  summary: {
    views: { value: 1280, change: 14.5, improved: true },
    visitors: { value: 830, change: 8.2, improved: true },
    sessions: { value: 970, change: 6.1, improved: true },
    avg_active_seconds: { value: 86, change: 12, improved: true },
    engagement_rate: { value: 63.4, change: 4.2, improved: true },
    bounce_rate: { value: 21.8, change: -3.4, improved: true },
    pages_per_session: { value: 1.32, change: 5.1, improved: true },
  },
  daily: [
    { date: "2026-09-04", views: 38, visitors: 31, sessions: 33, avg_active_seconds: 72, engagement_rate: 61.2 },
    { date: "2026-09-05", views: 54, visitors: 42, sessions: 45, avg_active_seconds: 91, engagement_rate: 68.5 },
    { date: "2026-09-06", views: 47, visitors: 37, sessions: 39, avg_active_seconds: 88, engagement_rate: 66.1 },
  ],
  top_pages: [
    { title: "Inicio", path: "/", views: 510, visitors: 390, avg_active_seconds: 75, engagement_rate: 62.4 },
  ],
  content_performance: [
    { content_type: "news", title: "Aniversario del colegio", path: "/noticias/aniversario", views: 188, visitors: 150, sessions: 161, avg_active_seconds: 104, engagement_rate: 72.3 },
  ],
  sources: [{ source: "Google", channel: "search", views: 620, visitors: 470 }],
  devices: [{ label: "mobile", views: 850, percentage: 66.4 }],
  browsers: [{ label: "Chrome", views: 790, percentage: 61.7 }],
  tracking: {
    started_on: "2026-09-01",
    privacy: "Métricas anónimas: no se almacenan IP, parámetros de URL ni agentes de usuario.",
  },
  generated_at: "2026-09-06T18:30:00-03:00",
};

describe("Informe PDF de métricas web", () => {
  beforeEach(() => {
    pdfHarness.definition = null;
    pdfHarness.filename = "";
    pdfHarness.download.mockClear();
  });

  it("construye un informe institucional completo con gráfico, tablas y privacidad", () => {
    const definition = buildWebAnalyticsPdfDefinition(analytics, { contentType: "news" });
    const serialized = JSON.stringify(definition.content);

    expect(definition.pageSize).toBe("A4");
    expect(definition.pageOrientation).toBe("landscape");
    expect(definition.info.subject).toContain("Noticias");
    expect(serialized).toContain("RESUMEN EJECUTIVO DEL PERÍODO");
    expect(serialized).toContain("Tráfico y audiencia");
    expect(serialized).toContain("Páginas más visitadas");
    expect(serialized).toContain("Noticias, eventos y contenidos");
    expect(serialized).toContain("Fuentes de tráfico");
    expect(serialized).toContain("Actividad registrada por día");
    expect(serialized).toContain("ALCANCE Y PRIVACIDAD");
    expect(serialized).toContain("Aniversario del colegio");
    expect(serialized).toContain("<svg");
    expect(definition.styles.tableHeader).toBeTruthy();
    expect(definition.footer(2, 4).columns[1].text).toBe("Página 2 de 4");
  });

  it("descarga el PDF con período y filtro en el nombre", async () => {
    const filename = await downloadWebAnalyticsPdf(analytics, { contentType: "news" });

    expect(filename).toBe("informe-estadisticas-web_2026-08-08_2026-09-06_news.pdf");
    expect(pdfHarness.download).toHaveBeenCalledWith(filename);
    expect(pdfHarness.definition.info.title).toContain("2026-08-08");
  });

  it("mantiene un informe válido cuando todavía no existen visitas", () => {
    const empty = buildWebAnalyticsPdfDefinition({
      period: analytics.period,
      summary: {},
      daily: [],
      top_pages: [],
      content_performance: [],
      sources: [],
      devices: [],
      browsers: [],
      tracking: analytics.tracking,
    });
    const serialized = JSON.stringify(empty.content);

    expect(serialized).toContain("Aún no hay visitas");
    expect(serialized).toContain("No existen páginas con visitas");
    expect(serialized).toContain("Sin datos disponibles");
  });
});
