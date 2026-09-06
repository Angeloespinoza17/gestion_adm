<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import SiteAdminNavigation from "../../components/public-site/site-admin-navigation.vue";
import { downloadWebAnalyticsPdf } from "../../utils/web-analytics-pdf";

export default {
  components: { Layout, SiteAdminNavigation },
  data() {
    return {
      loading: false,
      exporting: false,
      error: null,
      activePreset: 30,
      filters: {
        from: "",
        to: "",
        content_type: "",
      },
      analytics: this.emptyAnalytics(),
    };
  },
  computed: {
    primaryMetrics() {
      const summary = this.analytics.summary;
      return [
        { key: "views", label: "Vistas", icon: "bx-show", value: this.formatNumber(summary.views.value), note: "Páginas cargadas" },
        { key: "visitors", label: "Visitantes", icon: "bx-user", value: this.formatNumber(summary.visitors.value), note: "Personas anónimas" },
        { key: "sessions", label: "Sesiones", icon: "bx-window-open", value: this.formatNumber(summary.sessions.value), note: "Visitas iniciadas" },
        { key: "avg_active_seconds", label: "Permanencia activa", icon: "bx-time-five", value: this.formatDuration(summary.avg_active_seconds.value), note: "Promedio por página" },
      ];
    },
    qualityMetrics() {
      const summary = this.analytics.summary;
      return [
        { key: "engagement_rate", label: "Interacción", value: this.formatPercent(summary.engagement_rate.value), description: "Vistas con lectura, scroll o clic", tone: "teal" },
        { key: "bounce_rate", label: "Rebote", value: this.formatPercent(summary.bounce_rate.value), description: "Sesiones breves de una sola página", tone: "gold" },
        { key: "pages_per_session", label: "Páginas por sesión", value: this.formatDecimal(summary.pages_per_session.value), description: "Profundidad de navegación", tone: "blue" },
      ];
    },
    chartPoints() {
      const rows = this.analytics.daily || [];
      if (!rows.length) return "";
      const max = Math.max(...rows.map((row) => Number(row.views || 0)), 1);
      return rows.map((row, index) => {
        const x = rows.length === 1 ? 500 : (index / (rows.length - 1)) * 1000;
        const y = 220 - (Number(row.views || 0) / max) * 180;
        return `${x.toFixed(1)},${y.toFixed(1)}`;
      }).join(" ");
    },
    chartAreaPoints() {
      return this.chartPoints ? `0,240 ${this.chartPoints} 1000,240` : "";
    },
    chartTicks() {
      const rows = this.analytics.daily || [];
      if (!rows.length) return [];
      const indexes = [0, Math.floor((rows.length - 1) / 2), rows.length - 1];
      return indexes.filter((value, index) => indexes.indexOf(value) === index).map((index) => ({
        x: rows.length === 1 ? 50 : (index / (rows.length - 1)) * 100,
        label: this.formatShortDate(rows[index].date),
      }));
    },
    maxSourceViews() {
      return Math.max(...(this.analytics.sources || []).map((source) => Number(source.views || 0)), 1);
    },
    hasData() {
      return Number(this.analytics.summary.views.value || 0) > 0;
    },
    contentTypeOptions() {
      const defaults = ["news", "event", "student_life"];
      const values = [...new Set(defaults.concat(this.analytics.content_types || []))];
      return values.map((value) => ({ value, label: this.contentTypeLabel(value) }));
    },
  },
  created() {
    this.setPreset(30, false);
  },
  mounted() {
    this.load();
  },
  methods: {
    emptyAnalytics() {
      const metric = () => ({ value: 0, change: null, improved: null });
      return {
        period: { label: "", from: "", to: "", days: 0 },
        summary: {
          views: metric(), visitors: metric(), sessions: metric(), avg_active_seconds: metric(),
          engagement_rate: metric(), bounce_rate: metric(), pages_per_session: metric(),
        },
        daily: [], top_pages: [], content_performance: [], sources: [], devices: [], browsers: [], content_types: [],
        tracking: { started_on: null, privacy: "" },
      };
    },
    localDate(date) {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, "0");
      const day = String(date.getDate()).padStart(2, "0");
      return `${year}-${month}-${day}`;
    },
    setPreset(days, reload = true) {
      const to = new Date();
      const from = new Date();
      from.setDate(to.getDate() - (days - 1));
      this.activePreset = days;
      this.filters.from = this.localDate(from);
      this.filters.to = this.localDate(to);
      if (reload) this.load();
    },
    useCustomDates() {
      this.activePreset = null;
    },
    async load() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/admin/web-analytics", {
          params: {
            from: this.filters.from,
            to: this.filters.to,
            content_type: this.filters.content_type || null,
          },
        });
        this.analytics = response.data;
      } catch (error) {
        this.error = error.response?.data?.message || "No fue posible cargar las métricas del sitio web.";
      } finally {
        this.loading = false;
      }
    },
    async exportReport() {
      this.exporting = true;
      this.error = null;
      try {
        await downloadWebAnalyticsPdf(this.analytics, {
          contentType: this.filters.content_type || null,
        });
      } catch (error) {
        this.error = "No fue posible generar el informe de estadísticas. Inténtalo nuevamente.";
      } finally {
        this.exporting = false;
      }
    },
    formatNumber(value) {
      return new Intl.NumberFormat("es-CL").format(Number(value || 0));
    },
    formatDecimal(value) {
      return new Intl.NumberFormat("es-CL", { minimumFractionDigits: 1, maximumFractionDigits: 2 }).format(Number(value || 0));
    },
    formatPercent(value) {
      return `${this.formatDecimal(value)}%`;
    },
    formatDuration(value) {
      const seconds = Math.round(Number(value || 0));
      if (seconds < 60) return `${seconds} s`;
      const minutes = Math.floor(seconds / 60);
      const remainder = seconds % 60;
      return remainder ? `${minutes} min ${remainder} s` : `${minutes} min`;
    },
    formatChange(metric) {
      if (metric.change === null || metric.change === undefined) return "Sin base previa";
      const sign = Number(metric.change) > 0 ? "+" : "";
      return `${sign}${this.formatDecimal(metric.change)}% vs. período anterior`;
    },
    formatShortDate(value) {
      if (!value) return "";
      return new Intl.DateTimeFormat("es-CL", { day: "numeric", month: "short" })
        .format(new Date(`${value}T12:00:00`))
        .replace(".", "");
    },
    formatLongDate(value) {
      if (!value) return "su activación";
      return new Intl.DateTimeFormat("es-CL", { day: "numeric", month: "long", year: "numeric" })
        .format(new Date(`${value}T12:00:00`));
    },
    contentTypeLabel(value) {
      return {
        news: "Noticias",
        event: "Eventos",
        student_life: "Vida estudiantil",
        installation: "Instalaciones",
        page: "Página",
      }[value] || String(value || "Página").replaceAll("_", " ");
    },
    channelLabel(value) {
      return {
        direct: "Directo", search: "Buscadores", social: "Redes sociales", referral: "Referencia",
        campaign: "Campaña", email: "Correo", paid: "Publicidad",
      }[value] || value;
    },
    channelIcon(value) {
      return {
        direct: "bx-link-alt", search: "bx-search", social: "bxl-instagram", referral: "bx-share-alt",
        campaign: "bx-target-lock", email: "bx-envelope", paid: "bx-purchase-tag",
      }[value] || "bx-globe";
    },
    deviceLabel(value) {
      return { mobile: "Móvil", tablet: "Tablet", desktop: "Escritorio" }[value] || value;
    },
    deviceIcon(value) {
      return { mobile: "bx-mobile-alt", tablet: "bx-tab", desktop: "bx-desktop" }[value] || "bx-devices";
    },
    sourceWidth(value) {
      return `${Math.max(4, (Number(value || 0) / this.maxSourceViews) * 100)}%`;
    },
  },
};
</script>

<template>
  <Layout>
    <main class="analytics-page">
      <SiteAdminNavigation />

      <section class="analytics-hero">
        <div class="analytics-hero__glow analytics-hero__glow--one"></div>
        <div class="analytics-hero__glow analytics-hero__glow--two"></div>
        <div class="analytics-hero__content">
          <span class="analytics-eyebrow"><i class="bx bx-line-chart"></i> Inteligencia del sitio público</span>
          <h1>Métricas web</h1>
          <p>Comprende qué contenidos conectan con la comunidad, cuánto tiempo permanecen las visitas y cómo encuentran el sitio.</p>
          <div class="analytics-hero__trust">
            <span><i class="bx bx-shield-quarter"></i> Datos anónimos</span>
            <span><i class="bx bx-time-five"></i> Permanencia activa</span>
            <span><i class="bx bx-layer"></i> Noticias, eventos y contenido futuro</span>
          </div>
        </div>
        <div class="analytics-hero__actions">
          <button type="button" class="analytics-export" :disabled="loading || exporting" title="Exportar informe de estadísticas en PDF" @click="exportReport">
            <i class="bx" :class="exporting ? 'bx-loader-alt bx-spin' : 'bxs-file-pdf'"></i>
            {{ exporting ? "Generando..." : "Exportar informe" }}
          </button>
          <button type="button" class="analytics-refresh" :disabled="loading || exporting" title="Actualizar métricas" @click="load">
            <i class="bx bx-refresh" :class="{ 'bx-spin': loading }"></i>
            Actualizar
          </button>
        </div>
      </section>

      <div v-if="error" class="analytics-alert" role="alert">
        <i class="bx bx-error-circle"></i><span>{{ error }}</span>
      </div>

      <section class="analytics-filters" aria-label="Filtros de métricas">
        <div class="analytics-presets">
          <span>Período</span>
          <button v-for="days in [7, 30, 90, 365]" :key="days" type="button" :class="{ active: activePreset === days }" @click="setPreset(days)">
            {{ days === 365 ? "12 meses" : `${days} días` }}
          </button>
        </div>
        <div class="analytics-custom-filter">
          <label>Desde <input v-model="filters.from" type="date" @change="useCustomDates"></label>
          <label>Hasta <input v-model="filters.to" type="date" @change="useCustomDates"></label>
          <label>Contenido
            <select v-model="filters.content_type" @change="load">
              <option value="">Todo el sitio</option>
              <option v-for="option in contentTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
          </label>
          <button type="button" class="analytics-apply" :disabled="loading" @click="load">Aplicar</button>
        </div>
      </section>

      <div class="analytics-period-line">
        <div><span>Período analizado</span><strong>{{ analytics.period.label || "Selecciona un período" }}</strong></div>
        <span class="analytics-live"><i></i> Actualizado al solicitar</span>
      </div>

      <section class="analytics-kpis" aria-label="Indicadores principales">
        <article v-for="metric in primaryMetrics" :key="metric.key" class="analytics-kpi">
          <div class="analytics-kpi__top">
            <span class="analytics-kpi__icon"><i class="bx" :class="metric.icon"></i></span>
            <span class="analytics-kpi__change" :class="{ positive: analytics.summary[metric.key].improved === true, negative: analytics.summary[metric.key].improved === false }">
              <i class="bx" :class="analytics.summary[metric.key].change >= 0 ? 'bx-trending-up' : 'bx-trending-down'"></i>
              {{ formatChange(analytics.summary[metric.key]) }}
            </span>
          </div>
          <span class="analytics-kpi__label">{{ metric.label }}</span>
          <strong>{{ metric.value }}</strong>
          <small>{{ metric.note }}</small>
        </article>
      </section>

      <section class="analytics-quality" aria-label="Calidad de las visitas">
        <article v-for="metric in qualityMetrics" :key="metric.key" :class="`analytics-quality-card analytics-quality-card--${metric.tone}`">
          <div>
            <span>{{ metric.label }}</span>
            <strong>{{ metric.value }}</strong>
          </div>
          <p>{{ metric.description }}</p>
          <span class="analytics-quality-card__change" :class="{ positive: analytics.summary[metric.key].improved === true, negative: analytics.summary[metric.key].improved === false }">
            {{ formatChange(analytics.summary[metric.key]) }}
          </span>
        </article>
      </section>

      <section class="analytics-grid analytics-grid--trend">
        <article class="analytics-panel analytics-panel--trend">
          <header class="analytics-panel__header">
            <div><span class="analytics-panel__eyebrow">Tendencia diaria</span><h2>Tráfico y audiencia</h2></div>
            <div class="analytics-legend"><span><i></i> Vistas</span><span><i></i> Visitantes</span></div>
          </header>

          <div v-if="hasData" class="analytics-chart" aria-label="Gráfico de vistas diarias">
            <svg viewBox="0 0 1000 250" role="img" aria-labelledby="analytics-chart-title">
              <title id="analytics-chart-title">Evolución de vistas durante el período</title>
              <defs>
                <linearGradient id="analytics-area-gradient" x1="0" x2="0" y1="0" y2="1">
                  <stop offset="0%" stop-color="#1b8792" stop-opacity="0.3" />
                  <stop offset="100%" stop-color="#1b8792" stop-opacity="0.02" />
                </linearGradient>
              </defs>
              <line v-for="y in [40, 100, 160, 220]" :key="y" x1="0" x2="1000" :y1="y" :y2="y" class="analytics-chart__grid" />
              <polygon :points="chartAreaPoints" fill="url(#analytics-area-gradient)" />
              <polyline :points="chartPoints" class="analytics-chart__line" />
            </svg>
            <div class="analytics-chart__ticks">
              <span v-for="tick in chartTicks" :key="tick.x" :style="{ left: `${tick.x}%` }">{{ tick.label }}</span>
            </div>
          </div>
          <div v-else class="analytics-empty analytics-empty--chart">
            <span><i class="bx bx-line-chart"></i></span>
            <strong>Aún no hay visitas en este período</strong>
            <p>La tendencia aparecerá automáticamente cuando el sitio público reciba nuevas visitas.</p>
          </div>
        </article>

        <aside class="analytics-panel analytics-panel--devices">
          <header class="analytics-panel__header"><div><span class="analytics-panel__eyebrow">Tecnología</span><h2>Dispositivos</h2></div></header>
          <div v-if="analytics.devices.length" class="analytics-devices">
            <div v-for="device in analytics.devices" :key="device.label" class="analytics-device">
              <span class="analytics-device__icon"><i class="bx" :class="deviceIcon(device.label)"></i></span>
              <div><strong>{{ deviceLabel(device.label) }}</strong><small>{{ formatNumber(device.views) }} vistas</small></div>
              <b>{{ formatPercent(device.percentage) }}</b>
            </div>
          </div>
          <div v-else class="analytics-empty analytics-empty--small"><p>Sin datos de dispositivos.</p></div>
          <div v-if="analytics.browsers.length" class="analytics-browser-list">
            <span>Navegadores principales</span>
            <div v-for="browser in analytics.browsers.slice(0, 4)" :key="browser.label">
              <small>{{ browser.label }}</small><strong>{{ formatPercent(browser.percentage) }}</strong>
            </div>
          </div>
        </aside>
      </section>

      <section class="analytics-grid analytics-grid--insights">
        <article class="analytics-panel analytics-panel--content">
          <header class="analytics-panel__header">
            <div><span class="analytics-panel__eyebrow">Rendimiento editorial</span><h2>Noticias, eventos y contenidos</h2></div>
            <span class="analytics-panel__hint">Preparado para nuevos tipos de contenido</span>
          </header>
          <div v-if="analytics.content_performance.length" class="analytics-table-wrap">
            <table class="analytics-table">
              <thead><tr><th>Contenido</th><th>Vistas</th><th>Visitantes</th><th>Permanencia</th><th>Interacción</th></tr></thead>
              <tbody>
                <tr v-for="item in analytics.content_performance" :key="`${item.content_type}-${item.content_identifier}`">
                  <td><span class="analytics-content-type">{{ contentTypeLabel(item.content_type) }}</span><strong>{{ item.title }}</strong><small>{{ item.path }}</small></td>
                  <td><b>{{ formatNumber(item.views) }}</b></td>
                  <td>{{ formatNumber(item.visitors) }}</td>
                  <td>{{ formatDuration(item.avg_active_seconds) }}</td>
                  <td><span class="analytics-rate"><i :style="{ width: `${item.engagement_rate}%` }"></i></span><b>{{ formatPercent(item.engagement_rate) }}</b></td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="analytics-empty">
            <span><i class="bx bx-news"></i></span><strong>El contenido aún no acumula métricas</strong>
            <p>Las noticias y eventos publicados después de activar el seguimiento aparecerán aquí automáticamente.</p>
          </div>
        </article>

        <aside class="analytics-panel analytics-panel--sources">
          <header class="analytics-panel__header"><div><span class="analytics-panel__eyebrow">Adquisición</span><h2>Cómo llegan</h2></div></header>
          <div v-if="analytics.sources.length" class="analytics-sources">
            <div v-for="source in analytics.sources" :key="`${source.channel}-${source.source}`" class="analytics-source">
              <span class="analytics-source__icon"><i class="bx" :class="channelIcon(source.channel)"></i></span>
              <div class="analytics-source__body">
                <span><strong>{{ source.source }}</strong><small>{{ channelLabel(source.channel) }}</small></span>
                <span class="analytics-source__bar"><i :style="{ width: sourceWidth(source.views) }"></i></span>
              </div>
              <b>{{ formatNumber(source.views) }}</b>
            </div>
          </div>
          <div v-else class="analytics-empty analytics-empty--small"><p>Sin fuentes de tráfico para mostrar.</p></div>
        </aside>
      </section>

      <section class="analytics-panel analytics-panel--pages">
        <header class="analytics-panel__header">
          <div><span class="analytics-panel__eyebrow">Exploración</span><h2>Páginas más visitadas</h2></div>
          <span class="analytics-panel__hint">Todo el portal público</span>
        </header>
        <div v-if="analytics.top_pages.length" class="analytics-page-list">
          <article v-for="(page, index) in analytics.top_pages" :key="page.path">
            <span class="analytics-page-list__rank">{{ String(index + 1).padStart(2, "0") }}</span>
            <div><strong>{{ page.title }}</strong><small>{{ page.path }}</small></div>
            <span><b>{{ formatNumber(page.views) }}</b><small>vistas</small></span>
            <span><b>{{ formatDuration(page.avg_active_seconds) }}</b><small>permanencia</small></span>
            <span><b>{{ formatPercent(page.engagement_rate) }}</b><small>interacción</small></span>
          </article>
        </div>
        <div v-else class="analytics-empty analytics-empty--small"><p>Las páginas visitadas aparecerán aquí.</p></div>
      </section>

      <footer class="analytics-privacy-note">
        <span><i class="bx bx-shield-quarter"></i></span>
        <div><strong>Analítica respetuosa de la privacidad</strong><p>{{ analytics.tracking.privacy }} La información comienza a recopilarse desde {{ formatLongDate(analytics.tracking.started_on) }} y no reconstruye visitas anteriores.</p></div>
      </footer>
    </main>
  </Layout>
</template>

<style scoped>
.analytics-page{--ink:#163946;--muted:#6d8189;--teal:#0a7180;--gold:#d49a53;display:grid;gap:1rem;padding-bottom:2rem;color:var(--ink)}
.analytics-hero{position:relative;display:flex;align-items:flex-end;justify-content:space-between;min-height:220px;overflow:hidden;border-radius:24px;padding:2rem;background:linear-gradient(125deg,#062f43 0%,#07566a 57%,#157d82 100%);box-shadow:0 22px 50px rgba(5,49,67,.18);color:#fff}
.analytics-hero__content{position:relative;z-index:2;max-width:760px}.analytics-eyebrow{display:inline-flex;align-items:center;gap:.45rem;margin-bottom:.7rem;color:#f3cf8d;font-size:.7rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.analytics-hero h1{margin:0;color:#fff;font-size:clamp(2rem,4vw,3.3rem);font-weight:850;letter-spacing:-.045em}.analytics-hero p{max-width:690px;margin:.65rem 0 1rem;color:rgba(255,255,255,.78);font-size:.95rem;line-height:1.65}.analytics-hero__trust{display:flex;flex-wrap:wrap;gap:.55rem}.analytics-hero__trust span{display:inline-flex;align-items:center;gap:.36rem;border:1px solid rgba(255,255,255,.14);border-radius:999px;padding:.42rem .68rem;background:rgba(255,255,255,.08);color:rgba(255,255,255,.88);font-size:.65rem;font-weight:750}.analytics-hero__trust i{color:#f3cf8d}.analytics-hero__glow{position:absolute;border-radius:50%;filter:blur(3px);opacity:.22}.analytics-hero__glow--one{right:6%;top:-90px;width:260px;height:260px;background:#77d6ca}.analytics-hero__glow--two{right:25%;bottom:-150px;width:300px;height:300px;background:#e3af63}.analytics-hero__actions{position:relative;z-index:2;display:flex;align-items:center;gap:.55rem}.analytics-refresh,.analytics-export{display:inline-flex;align-items:center;justify-content:center;gap:.4rem;border-radius:12px;padding:.66rem .9rem;color:#fff;font-size:.7rem;font-weight:800;backdrop-filter:blur(8px);transition:transform .18s ease,background .18s ease}.analytics-refresh{border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.12)}.analytics-export{border:1px solid rgba(243,207,141,.72);background:linear-gradient(135deg,rgba(211,154,82,.94),rgba(184,126,59,.94));box-shadow:0 10px 22px rgba(16,32,38,.18)}.analytics-refresh:hover{background:rgba(255,255,255,.2)}.analytics-export:hover{transform:translateY(-1px);background:linear-gradient(135deg,#dda65d,#bd7f3e)}.analytics-refresh:disabled,.analytics-export:disabled{opacity:.55;transform:none}
.analytics-alert{display:flex;align-items:center;gap:.6rem;border:1px solid #f3c4c0;border-radius:14px;padding:.8rem 1rem;background:#fff2f1;color:#9d3029;font-size:.78rem;font-weight:700}.analytics-filters{display:flex;align-items:center;justify-content:space-between;gap:1rem;border:1px solid #dce8ea;border-radius:18px;padding:.75rem;background:#fff;box-shadow:0 10px 28px rgba(20,57,70,.05)}.analytics-presets{display:flex;align-items:center;gap:.34rem}.analytics-presets>span{margin:0 .35rem;color:#81939a;font-size:.62rem;font-weight:850;text-transform:uppercase;letter-spacing:.08em}.analytics-presets button{border:1px solid transparent;border-radius:10px;padding:.5rem .7rem;background:#f4f8f8;color:#60767e;font-size:.68rem;font-weight:800}.analytics-presets button.active{border-color:#b8d9dc;background:#e5f2f1;color:#075e6d}.analytics-custom-filter{display:flex;align-items:flex-end;gap:.45rem}.analytics-custom-filter label{display:grid;gap:.2rem;color:#71858d;font-size:.55rem;font-weight:850;letter-spacing:.05em;text-transform:uppercase}.analytics-custom-filter input,.analytics-custom-filter select{height:35px;border:1px solid #d7e4e6;border-radius:9px;padding:0 .55rem;background:#fbfdfd;color:#35505a;font-size:.67rem;letter-spacing:0;text-transform:none}.analytics-apply{height:35px;border:0;border-radius:9px;padding:0 .75rem;background:#0b6e7b;color:#fff;font-size:.66rem;font-weight:850}.analytics-period-line{display:flex;align-items:center;justify-content:space-between;padding:0 .2rem}.analytics-period-line div{display:flex;align-items:baseline;gap:.5rem}.analytics-period-line span{color:#87979d;font-size:.6rem;font-weight:750}.analytics-period-line strong{font-size:.75rem}.analytics-live{display:inline-flex;align-items:center;gap:.35rem}.analytics-live i{width:7px;height:7px;border-radius:50%;background:#4ab98b;box-shadow:0 0 0 4px rgba(74,185,139,.12)}
.analytics-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.analytics-kpi{display:grid;min-height:155px;border:1px solid #dce8e9;border-radius:18px;padding:1rem;background:linear-gradient(155deg,#fff,#f8fbfb);box-shadow:0 12px 28px rgba(16,56,69,.055)}.analytics-kpi__top{display:flex;align-items:flex-start;justify-content:space-between;gap:.5rem}.analytics-kpi__icon{display:grid;width:36px;height:36px;place-items:center;border-radius:11px;background:#e6f2f2;color:#08707d;font-size:1rem}.analytics-kpi__change{display:inline-flex;align-items:center;gap:.18rem;color:#87989e;font-size:.54rem;font-weight:800}.positive{color:#18815c!important}.negative{color:#b85b4e!important}.analytics-kpi__label{align-self:end;margin-top:.65rem;color:#71858c;font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em}.analytics-kpi>strong{font-size:1.62rem;line-height:1.15;letter-spacing:-.04em}.analytics-kpi>small{color:#93a0a5;font-size:.58rem}
.analytics-quality{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}.analytics-quality-card{display:grid;grid-template-columns:1fr auto;gap:.3rem 1rem;border:1px solid #dde8e8;border-radius:16px;padding:.9rem 1rem;background:#fff}.analytics-quality-card div{display:grid}.analytics-quality-card div span{color:#75878e;font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em}.analytics-quality-card div strong{font-size:1.2rem}.analytics-quality-card p{align-self:center;margin:0;color:#7e8e94;font-size:.64rem}.analytics-quality-card__change{grid-column:1/-1;border-top:1px solid #edf2f2;padding-top:.45rem;color:#8b999e;font-size:.56rem;font-weight:750}.analytics-quality-card--teal{border-top:3px solid #36a49a}.analytics-quality-card--gold{border-top:3px solid #d9a056}.analytics-quality-card--blue{border-top:3px solid #4f8fad}
.analytics-grid{display:grid;gap:.85rem}.analytics-grid--trend{grid-template-columns:minmax(0,2.1fr) minmax(265px,.9fr)}.analytics-grid--insights{grid-template-columns:minmax(0,2fr) minmax(280px,.85fr)}.analytics-panel{min-width:0;border:1px solid #dce7e9;border-radius:20px;padding:1rem;background:#fff;box-shadow:0 12px 30px rgba(16,56,69,.05)}.analytics-panel__header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:.9rem}.analytics-panel__header h2{margin:.12rem 0 0;color:#1c404c;font-size:.92rem;font-weight:850}.analytics-panel__eyebrow{color:#8b9ba1;font-size:.54rem;font-weight:850;letter-spacing:.09em;text-transform:uppercase}.analytics-panel__hint{align-self:center;color:#85969c;font-size:.57rem}.analytics-legend{display:flex;gap:.75rem;color:#74878e;font-size:.58rem;font-weight:700}.analytics-legend span{display:flex;align-items:center;gap:.25rem}.analytics-legend i{width:8px;height:8px;border-radius:50%;background:#12808a}.analytics-legend span+span i{background:#80aeb4}.analytics-chart{position:relative;height:255px;padding-bottom:1.2rem}.analytics-chart svg{width:100%;height:100%;overflow:visible}.analytics-chart__grid{stroke:#e8eeee;stroke-width:1}.analytics-chart__line{fill:none;stroke:#0c7b86;stroke-width:5;stroke-linecap:round;stroke-linejoin:round}.analytics-chart__ticks{position:absolute;right:0;bottom:0;left:0;height:1rem}.analytics-chart__ticks span{position:absolute;transform:translateX(-50%);color:#8a999f;font-size:.55rem}.analytics-chart__ticks span:first-child{transform:none}.analytics-chart__ticks span:last-child{transform:translateX(-100%)}
.analytics-devices{display:grid;gap:.55rem}.analytics-device{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:.55rem;border-radius:12px;padding:.58rem;background:#f6f9f9}.analytics-device__icon{display:grid;width:34px;height:34px;place-items:center;border-radius:10px;background:#e4f0f0;color:#0d7180}.analytics-device div{display:grid}.analytics-device strong{font-size:.67rem}.analytics-device small{color:#89989e;font-size:.55rem}.analytics-device>b{font-size:.67rem}.analytics-browser-list{display:grid;gap:.38rem;margin-top:.85rem;border-top:1px solid #e7eeee;padding-top:.75rem}.analytics-browser-list>span{color:#87979d;font-size:.55rem;font-weight:850;text-transform:uppercase}.analytics-browser-list div{display:flex;justify-content:space-between}.analytics-browser-list small{color:#607780;font-size:.59rem}.analytics-browser-list strong{font-size:.59rem}
.analytics-table-wrap{overflow-x:auto}.analytics-table{width:100%;border-collapse:collapse}.analytics-table th{border-bottom:1px solid #e5eded;padding:.5rem;color:#84959b;font-size:.52rem;font-weight:850;letter-spacing:.07em;text-align:left;text-transform:uppercase}.analytics-table td{border-bottom:1px solid #eef3f3;padding:.72rem .5rem;color:#627780;font-size:.64rem;vertical-align:middle}.analytics-table td:first-child{display:grid;min-width:240px}.analytics-table td:first-child strong{max-width:340px;overflow:hidden;color:#264852;font-size:.66rem;text-overflow:ellipsis;white-space:nowrap}.analytics-table td:first-child small{color:#96a3a7;font-size:.53rem}.analytics-content-type{width:max-content;margin-bottom:.2rem;border-radius:99px;padding:.16rem .36rem;background:#edf5f4;color:#13717d;font-size:.47rem;font-weight:850;text-transform:uppercase}.analytics-table td:last-child{display:grid;grid-template-columns:55px auto;align-items:center;gap:.35rem}.analytics-rate{display:block;height:5px;overflow:hidden;border-radius:99px;background:#e9efef}.analytics-rate i{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#147e87,#5aad92)}
.analytics-sources{display:grid;gap:.72rem}.analytics-source{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:.5rem}.analytics-source__icon{display:grid;width:32px;height:32px;place-items:center;border-radius:10px;background:#edf4f4;color:#18707b}.analytics-source__body{display:grid;gap:.28rem}.analytics-source__body>span:first-child{display:flex;justify-content:space-between;gap:.4rem}.analytics-source__body strong{max-width:120px;overflow:hidden;font-size:.61rem;text-overflow:ellipsis;white-space:nowrap}.analytics-source__body small{color:#8c9ba0;font-size:.49rem}.analytics-source__bar{height:4px;overflow:hidden;border-radius:99px;background:#edf1f1}.analytics-source__bar i{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#0e7280,#d19a57)}.analytics-source>b{font-size:.63rem}
.analytics-panel--pages{padding-bottom:.55rem}.analytics-page-list{display:grid}.analytics-page-list article{display:grid;grid-template-columns:36px minmax(220px,1fr) repeat(3,minmax(80px,.15fr));align-items:center;gap:.6rem;border-top:1px solid #ecf1f1;padding:.68rem .25rem}.analytics-page-list__rank{color:#bd965e;font-size:.65rem;font-weight:850}.analytics-page-list article>div{display:grid}.analytics-page-list article>div strong{overflow:hidden;font-size:.66rem;text-overflow:ellipsis;white-space:nowrap}.analytics-page-list article>div small{color:#92a0a5;font-size:.53rem}.analytics-page-list article>span:not(.analytics-page-list__rank){display:grid}.analytics-page-list article>span b{font-size:.65rem}.analytics-page-list article>span small{color:#94a1a6;font-size:.49rem}
.analytics-empty{display:grid;justify-items:center;padding:2.3rem 1rem;text-align:center}.analytics-empty--chart{min-height:250px;align-content:center}.analytics-empty--small{padding:1.2rem .5rem}.analytics-empty>span{display:grid;width:42px;height:42px;place-items:center;border-radius:13px;background:#ebf3f3;color:#15727e;font-size:1.1rem}.analytics-empty strong{margin-top:.6rem;font-size:.72rem}.analytics-empty p{max-width:420px;margin:.25rem 0 0;color:#89989d;font-size:.62rem;line-height:1.5}.analytics-privacy-note{display:flex;align-items:center;gap:.7rem;border:1px solid #dce8e8;border-radius:16px;padding:.8rem 1rem;background:linear-gradient(100deg,#f7fbfa,#fbfaf5)}.analytics-privacy-note>span{display:grid;width:38px;height:38px;flex:0 0 38px;place-items:center;border-radius:11px;background:#e5f1ef;color:#167466}.analytics-privacy-note div{display:grid}.analytics-privacy-note strong{font-size:.65rem}.analytics-privacy-note p{margin:.12rem 0 0;color:#788b91;font-size:.58rem;line-height:1.5}
@media(max-width:1199.98px){.analytics-kpis{grid-template-columns:repeat(2,1fr)}.analytics-grid--trend,.analytics-grid--insights{grid-template-columns:1fr}.analytics-custom-filter{flex-wrap:wrap;justify-content:flex-end}}
@media(max-width:767.98px){.analytics-page{gap:.75rem}.analytics-hero{display:grid;min-height:0;padding:1.25rem}.analytics-hero p{font-size:.78rem}.analytics-hero__actions{display:grid;grid-template-columns:1fr 1fr;margin-top:1rem}.analytics-refresh,.analytics-export{width:100%}.analytics-filters{display:grid}.analytics-presets{overflow-x:auto}.analytics-custom-filter{display:grid;grid-template-columns:1fr 1fr;justify-content:stretch}.analytics-custom-filter label:last-of-type,.analytics-apply{grid-column:1/-1}.analytics-custom-filter input,.analytics-custom-filter select,.analytics-apply{width:100%}.analytics-kpis,.analytics-quality{grid-template-columns:1fr}.analytics-period-line{align-items:flex-start}.analytics-period-line div{display:grid}.analytics-live{display:none}.analytics-panel{border-radius:16px}.analytics-panel__header{display:grid}.analytics-panel__hint{align-self:auto}.analytics-chart{height:210px}.analytics-table{min-width:690px}.analytics-page-list article{grid-template-columns:30px 1fr auto}.analytics-page-list article>span:nth-last-child(-n+2){display:none}.analytics-page-list article>span:nth-child(3) small{display:block}.analytics-privacy-note{align-items:flex-start}}
@media(prefers-reduced-motion:reduce){.analytics-refresh,.analytics-presets button{transition:none}}
</style>
