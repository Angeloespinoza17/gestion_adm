// @vitest-environment jsdom

import { readFileSync } from "node:fs";
import { describe, expect, it } from "vitest";

describe("Métricas del sitio web", () => {
  it("integra el panel interno con navegación, permiso y estados vacíos", () => {
    const dashboard = readFileSync("resources/js/views/admin/web-analytics.vue", "utf8");
    const router = readFileSync("resources/js/router/index.js", "utf8");
    const navigation = readFileSync("resources/js/components/public-site/site-admin-navigation.vue", "utf8");

    expect(dashboard).toContain("SiteAdminNavigation");
    expect(dashboard).toContain("Permanencia activa");
    expect(dashboard).toContain("Noticias, eventos y contenidos");
    expect(dashboard).toContain("Analítica respetuosa de la privacidad");
    expect(dashboard).toContain("Exportar informe");
    expect(dashboard).toContain("downloadWebAnalyticsPdf");
    expect(router).toContain('path: "/admin/metricas-web"');
    expect(router).toContain('permission: "ver_metricas_sitio"');
    expect(navigation).toContain('label: "Métricas"');
  });

  it("captura actividad real sin almacenar IP ni parámetros de navegación", () => {
    const tracker = readFileSync("public/niceschool/assets/js/cnsc-analytics.js", "utf8");
    const migration = readFileSync("database/migrations/2026_09_06_200000_create_web_analytics_page_views.php", "utf8");

    expect(tracker).toContain("navigator.doNotTrack");
    expect(tracker).toContain("document.visibilityState");
    expect(tracker).toContain("max_scroll_depth");
    expect(tracker).toContain("window.location.pathname");
    expect(tracker).not.toContain("window.location.href");
    expect(migration).not.toContain("ip_address");
    expect(migration).not.toContain("user_agent");
  });

  it("etiqueta automáticamente noticias, eventos y vida estudiantil publicados después", () => {
    const news = readFileSync("resources/views/public/pages/news-show.blade.php", "utf8");
    const events = readFileSync("resources/views/public/pages/event-show.blade.php", "utf8");
    const studentLife = readFileSync("resources/views/public/pages/student-life-show.blade.php", "utf8");
    const layout = readFileSync("resources/views/public/layouts/site.blade.php", "utf8");

    expect(news).toContain("@section('analytics_content_type', 'news')");
    expect(events).toContain("@section('analytics_content_type', 'event')");
    expect(studentLife).toContain("@section('analytics_content_type', 'student_life')");
    expect(layout).toContain("data-analytics-content-type");
    expect(layout).toContain("cnsc-analytics.js");
  });
});
