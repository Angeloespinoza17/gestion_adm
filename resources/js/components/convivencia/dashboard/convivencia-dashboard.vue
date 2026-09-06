<script>
import { downloadConvivenciaDashboardPdf } from "../pdf/convivencia-dashboard-pdf";
import { formatConvivenciaDateTime, formatConvivenciaError, humanizeConvivenciaStatus, showConvivenciaError } from "../module-utils";
import { convivenciaProtocolPartCategoryLabel } from "../protocol-runtime-labels";
import { CONVIVENCIA_DASHBOARD_METRIC_LABELS as metricLabels } from "./dashboard-metrics";

export default {
  props: {
    data: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    catalogs: { type: Object, default: () => ({}) },
    modelValue: { type: Object, default: () => ({}) },
  },
  emits: ["update:modelValue", "refresh"],
  data() {
    return { filters: { ...this.modelValue }, exporting: false };
  },
  computed: {
    metrics() {
      if (Array.isArray(this.data?.metrics)) return this.data.metrics.map((item) => ({ key: item.key || item.code || item.label, label: item.label || metricLabels[item.key] || this.label(item.key), value: item.value ?? item.total ?? item.count, suffix: item.suffix || "" }));
      return Object.entries(this.data?.metrics || {}).map(([key, raw]) => {
        const object = raw && typeof raw === "object" ? raw : null;
        return { key, label: object?.label || metricLabels[key] || this.label(key), value: object?.value ?? object?.total ?? object?.count ?? raw, suffix: object?.suffix || (key.includes("percentage") || key.includes("rate") ? "%" : "") };
      });
    },
    priorityMetrics() {
      const priorities = ["protocol_compliance_percentage", "compliance_percentage", "compliance_rate", "overdue_protocol_steps", "overdue_steps", "due_soon_protocol_steps", "due_soon_steps", "active_protocols", "open_cases", "overdue_followups"];
      const sorted = [...this.metrics].sort((a, b) => {
        const ai = priorities.indexOf(a.key); const bi = priorities.indexOf(b.key);
        return (ai < 0 ? 999 : ai) - (bi < 0 ? 999 : bi);
      });
      return sorted.slice(0, 6);
    },
    remainingMetrics() {
      const used = new Set(this.priorityMetrics.map((metric) => metric.key));
      return this.metrics.filter((metric) => !used.has(metric.key));
    },
    complianceMetric() {
      return this.metrics.find((metric) => ["protocol_compliance_percentage", "compliance_percentage", "compliance_rate"].includes(metric.key));
    },
    overdueMetric() {
      return this.metrics.find((metric) => ["overdue_protocol_steps", "overdue_steps"].includes(metric.key));
    },
    dueSoonMetric() {
      return this.metrics.find((metric) => ["due_soon_protocol_steps", "due_soon_steps"].includes(metric.key));
    },
    alerts() {
      const source = this.data?.alerts || this.data?.insights?.alerts || [];
      if (Array.isArray(source)) return source;
      return Object.entries(source).flatMap(([type, entries]) => this.asArray(entries).map((entry) => typeof entry === "object" ? { ...entry, type } : { title: entry, type }));
    },
    bottlenecks() {
      return this.firstArray(this.data?.charts?.bottlenecks, this.data?.insights?.bottlenecks, this.data?.insights?.protocol_bottlenecks);
    },
    activationsByProtocol() {
      return this.firstArray(this.data?.charts?.activations_by_protocol, this.data?.charts?.protocol_activations, this.data?.insights?.activations_by_protocol);
    },
    partsByCategory() {
      return this.firstArray(this.data?.charts?.parts_by_category, this.data?.charts?.protocol_parts_by_category, this.data?.insights?.parts_by_category);
    },
    casesByStatus() {
      return this.firstArray(this.data?.charts?.cases_by_status);
    },
    recentGroups() {
      return [
        { key: "protocols", title: "Protocolos recientes", icon: "bx-git-branch", items: this.asArray(this.data?.recent?.protocols || this.data?.recent?.activations) },
        { key: "cases", title: "Casos recientes", icon: "bx-folder-open", items: this.asArray(this.data?.recent?.cases) },
        { key: "complaints", title: "Denuncias recientes", icon: "bx-message-square-error", items: this.asArray(this.data?.recent?.complaints) },
      ].filter((group) => group.items.length);
    },
    narrativeInsights() {
      const source = this.data?.insights;
      if (Array.isArray(source)) return source;
      return this.asArray(source?.items || source?.recommendations || source?.summary).map((item) => typeof item === "string" ? { title: item } : item);
    },
    years() {
      return this.catalogs?.academic_years || [];
    },
    courses() {
      return this.catalogs?.courses || [];
    },
  },
  watch: {
    modelValue: { deep: true, handler(value) { this.filters = { ...value }; } },
  },
  methods: {
    asArray(value) { return Array.isArray(value) ? value : value === null || value === undefined || value === "" ? [] : [value]; },
    firstArray(...values) { return values.find((value) => Array.isArray(value) && value.length) || []; },
    label(value) { return humanizeConvivenciaStatus(value); },
    optionValue(item) { return item?.value ?? item?.id; },
    optionText(item) { return item?.text || item?.label || item?.name || item?.display_name; },
    metricIcon(key) {
      if (key.includes("compliance") || key.includes("completed")) return "bx-check-shield";
      if (key.includes("overdue")) return "bx-error-circle";
      if (key.includes("due_soon")) return "bx-time-five";
      if (key.includes("protocol")) return "bx-git-branch";
      if (key.includes("case")) return "bx-folder-open";
      if (key.includes("complaint")) return "bx-message-square-error";
      if (key.includes("measure")) return "bx-shield-quarter";
      return "bx-bar-chart-alt-2";
    },
    metricTone(metric) {
      if (metric.key.includes("overdue") && Number(metric.value) > 0) return "danger";
      if (metric.key.includes("due_soon") && Number(metric.value) > 0) return "warning";
      if (metric.key.includes("compliance")) return "success";
      return "primary";
    },
    chartLabel(item) { return item?.label || item?.name || item?.protocol_name || item?.category || item?.stage_name || "Sin categoría"; },
    chartTotal(item) { return Number(item?.total ?? item?.count ?? item?.value ?? item?.activations ?? 0); },
    barOptions(items, horizontal = true, colors = ["#5064d9"]) {
      return { chart: { toolbar: { show: false }, fontFamily: "inherit" }, colors, plotOptions: { bar: { horizontal, borderRadius: 6, barHeight: "55%" } }, dataLabels: { enabled: false }, xaxis: { categories: items.map(this.chartLabel), labels: { style: { fontSize: "10px", colors: "#758198" } } }, yaxis: { labels: { style: { fontSize: "10px", colors: "#58657c" }, maxWidth: 145 } }, grid: { borderColor: "#edf0f5" }, tooltip: { y: { formatter: (value) => Number(value).toLocaleString("es-CL") } } };
    },
    chartSeries(items, name = "Total") { return [{ name, data: items.map(this.chartTotal) }]; },
    donutOptions(items, translatePartCategories = false) {
      const labels = items.map((item) => translatePartCategories
        ? convivenciaProtocolPartCategoryLabel(item?.category || item?.label || item?.name)
        : this.chartLabel(item));
      return { labels, colors: ["#5064d9", "#2a8b77", "#d59635", "#c45259", "#7295c7", "#8b6cb0"], legend: { position: "bottom", fontSize: "11px" }, dataLabels: { enabled: false }, stroke: { width: 3, colors: ["#fff"] }, plotOptions: { pie: { donut: { size: "70%" } } } };
    },
    applyFilters() { this.$emit("update:modelValue", { ...this.filters }); this.$emit("refresh", { ...this.filters }); },
    clearFilters() { this.filters = { academic_year_id: this.catalogs?.active_academic_year_id || null, course_section_id: null, from: "", to: "" }; this.applyFilters(); },
    formatDate(value) { return formatConvivenciaDateTime(value); },
    recentTitle(item) { return item.folio || item.protocol?.name || item.protocol_name || `Registro #${item.id || "-"}`; },
    recentSubtitle(item) { return item.current_stage_name || item.classification_label || item.situation_type_label || this.label(item.status); },
    alertTone(alert) { const value = String(alert.severity || alert.level || alert.type || "").toLowerCase(); return ["critical", "high", "danger", "overdue", "vencido"].includes(value) ? "danger" : ["warning", "medium", "due_soon"].includes(value) ? "warning" : "info"; },
    async exportPdf() {
      if (this.exporting || !this.data) return;
      this.exporting = true;
      try { await downloadConvivenciaDashboardPdf({ data: this.data, filters: this.filters, catalogs: this.catalogs }); }
      catch (error) { showConvivenciaError(formatConvivenciaError(error, "No se pudo generar el PDF del panel.")); }
      finally { this.exporting = false; }
    },
  },
};
</script>

<template>
  <section class="convivencia-dashboard" aria-labelledby="convivencia-dashboard-title">
    <header class="convivencia-dashboard__hero">
      <div><span>Visión ejecutiva · Convivencia Escolar</span><h2 id="convivencia-dashboard-title">Cumplimiento y salud operativa</h2><p>Controla plazos, activaciones y puntos de congestión con indicadores trazables al período seleccionado.</p></div>
      <button v-if="data && catalogs.capabilities?.can_export_reports === true" type="button" class="btn btn-light" :disabled="exporting" @click="exportPdf"><i class="bx" :class="exporting ? 'bx-loader-alt bx-spin' : 'bxs-file-pdf'" aria-hidden="true"></i>{{ exporting ? "Preparando…" : "Exportar panel PDF" }}</button>
    </header>

    <form class="convivencia-dashboard__filters" aria-label="Filtros del panel" @submit.prevent="applyFilters">
      <div><label for="dashboard-year">Año académico</label><select id="dashboard-year" v-model="filters.academic_year_id" class="form-select"><option :value="null">Todos</option><option v-for="item in years" :key="optionValue(item)" :value="optionValue(item)">{{ optionText(item) }}</option></select></div>
      <div><label for="dashboard-course">Curso</label><select id="dashboard-course" v-model="filters.course_section_id" class="form-select"><option :value="null">Todos los cursos</option><option v-for="item in courses" :key="optionValue(item)" :value="optionValue(item)">{{ optionText(item) }}</option></select></div>
      <div><label for="dashboard-from">Desde</label><input id="dashboard-from" v-model="filters.from" type="date" class="form-control" /></div>
      <div><label for="dashboard-to">Hasta</label><input id="dashboard-to" v-model="filters.to" type="date" class="form-control" /></div>
      <div class="convivencia-dashboard__filter-actions"><button type="button" class="btn btn-outline-secondary" @click="clearFilters">Limpiar</button><button type="submit" class="btn btn-primary"><i class="bx bx-filter-alt" aria-hidden="true"></i>Aplicar</button></div>
    </form>

    <div v-if="loading" class="convivencia-dashboard__state" role="status"><span class="spinner-border text-primary" aria-hidden="true"></span><b>Construyendo indicadores…</b></div>
    <template v-else-if="data">
      <section class="convivencia-dashboard__pulse" aria-label="Pulso de cumplimiento">
        <article class="is-compliance"><span><i class="bx bx-check-shield" aria-hidden="true"></i></span><div><small>Cumplimiento de protocolos</small><b>{{ complianceMetric ? `${complianceMetric.value}${complianceMetric.suffix || '%'}` : 'Sin dato' }}</b><p>{{ complianceMetric ? 'Según etapas y requisitos informados por el backend.' : 'Requiere indicador de cumplimiento en la respuesta.' }}</p></div></article>
        <article class="is-overdue"><span><i class="bx bx-error-circle" aria-hidden="true"></i></span><div><small>Etapas vencidas</small><b>{{ overdueMetric?.value ?? 0 }}</b><p>Requieren regularización o justificación registrada.</p></div></article>
        <article class="is-due"><span><i class="bx bx-time-five" aria-hidden="true"></i></span><div><small>Próximas a vencer</small><b>{{ dueSoonMetric?.value ?? 0 }}</b><p>Prioriza acciones antes del límite reglamentario.</p></div></article>
      </section>

      <section v-if="priorityMetrics.length" class="convivencia-dashboard__metrics" aria-label="Indicadores principales">
        <article v-for="metric in priorityMetrics" :key="metric.key" :class="`is-${metricTone(metric)}`"><span><i class="bx" :class="metricIcon(metric.key)" aria-hidden="true"></i></span><div><small>{{ metric.label }}</small><b>{{ metric.value }}{{ metric.suffix }}</b></div></article>
      </section>

      <section v-if="alerts.length" class="convivencia-dashboard__alerts" aria-labelledby="dashboard-alerts-title">
        <header><div><span>Atención prioritaria</span><h3 id="dashboard-alerts-title">Alertas de ejecución</h3></div><b>{{ alerts.length }}</b></header>
        <div><article v-for="(alert, index) in alerts" :key="alert.id || index" :class="`is-${alertTone(alert)}`"><i class="bx" :class="alertTone(alert) === 'danger' ? 'bx-error' : 'bx-info-circle'" aria-hidden="true"></i><div><b>{{ alert.title || alert.label || alert.message || 'Alerta operativa' }}</b><p>{{ alert.description || alert.detail || (alert.title ? alert.message : '') }}</p><small v-if="alert.due_at"><i class="bx bx-time-five" aria-hidden="true"></i>{{ formatDate(alert.due_at) }}</small></div><strong v-if="alert.total ?? alert.count">{{ alert.total ?? alert.count }}</strong></article></div>
      </section>

      <section class="convivencia-dashboard__charts">
        <article><header><div><span>Demanda operativa</span><h3>Activaciones por protocolo</h3></div><i class="bx bx-git-branch" aria-hidden="true"></i></header><apexchart v-if="activationsByProtocol.length" type="bar" height="285" :options="barOptions(activationsByProtocol, true)" :series="chartSeries(activationsByProtocol, 'Activaciones')" /><p v-else class="convivencia-dashboard__chart-empty">Sin distribución de activaciones disponible.</p></article>
        <article><header><div><span>Diseño reglamentario</span><h3>Partes por categoría</h3></div><i class="bx bx-layer" aria-hidden="true"></i></header><apexchart v-if="partsByCategory.length" type="donut" height="285" :options="donutOptions(partsByCategory, true)" :series="partsByCategory.map(chartTotal)" /><p v-else class="convivencia-dashboard__chart-empty">Sin distribución de partes disponible.</p></article>
        <article><header><div><span>Tiempo y capacidad</span><h3>Cuellos de botella</h3></div><i class="bx bx-timer" aria-hidden="true"></i></header><apexchart v-if="bottlenecks.length" type="bar" height="285" :options="barOptions(bottlenecks, true, ['#d59635'])" :series="chartSeries(bottlenecks, 'Casos o demora')" /><p v-else class="convivencia-dashboard__chart-empty">No se informan cuellos de botella.</p></article>
        <article><header><div><span>Contexto general</span><h3>Casos por estado</h3></div><i class="bx bx-folder-open" aria-hidden="true"></i></header><apexchart v-if="casesByStatus.length" type="donut" height="285" :options="donutOptions(casesByStatus)" :series="casesByStatus.map(chartTotal)" /><p v-else class="convivencia-dashboard__chart-empty">Sin distribución de casos disponible.</p></article>
      </section>

      <section v-if="narrativeInsights.length || remainingMetrics.length" class="convivencia-dashboard__insights">
        <div v-if="narrativeInsights.length"><span>Análisis operativo</span><h3>Hallazgos relevantes</h3><ul><li v-for="(insight, index) in narrativeInsights" :key="insight.id || index"><i class="bx bx-bulb" aria-hidden="true"></i><span><b>{{ insight.title || insight.label || insight.message }}</b><small v-if="insight.description || insight.detail">{{ insight.description || insight.detail }}</small></span></li></ul></div>
        <div v-if="remainingMetrics.length"><span>Indicadores complementarios</span><h3>Otros resultados</h3><dl><div v-for="metric in remainingMetrics" :key="metric.key"><dt>{{ metric.label }}</dt><dd>{{ metric.value }}{{ metric.suffix }}</dd></div></dl></div>
      </section>

      <section v-if="recentGroups.length" class="convivencia-dashboard__recent">
        <article v-for="group in recentGroups" :key="group.key"><header><i class="bx" :class="group.icon" aria-hidden="true"></i><h3>{{ group.title }}</h3></header><div><div v-for="item in group.items.slice(0, 6)" :key="item.id" class="convivencia-dashboard__recent-row"><span><b>{{ recentTitle(item) }}</b><small>{{ recentSubtitle(item) }}</small></span><em>{{ label(item.status) }}</em></div></div></article>
      </section>
    </template>
    <div v-else class="convivencia-dashboard__state"><i class="bx bx-bar-chart-alt-2" aria-hidden="true"></i><b>No hay información para construir el panel</b><p>Ajusta los filtros e inténtalo nuevamente.</p></div>
  </section>
</template>

<style scoped>
.convivencia-dashboard{--cd-navy:#202e6d;--cd-indigo:#5064d9;--cd-teal:#2a8b77;--cd-line:#dde3ed;display:grid;gap:1rem;color:#34415a}.convivencia-dashboard__hero{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1.25rem 1.4rem;color:#fff;border-radius:22px;background:radial-gradient(circle at 85% 0,rgba(78,207,178,.31),transparent 28%),linear-gradient(120deg,#202d6c,#4a5dc8 65%,#287f72);box-shadow:0 16px 38px rgba(30,44,103,.18)}.convivencia-dashboard__hero>div>span,.convivencia-dashboard__alerts header span,.convivencia-dashboard__charts header span,.convivencia-dashboard__insights>div>span{font-size:.62rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;opacity:.78}.convivencia-dashboard__hero h2{margin:.18rem 0;font-size:1.2rem;font-weight:800}.convivencia-dashboard__hero p{max-width:760px;margin:0;font-size:.73rem;opacity:.82}.convivencia-dashboard__hero .btn{display:inline-flex;align-items:center;gap:.35rem;white-space:nowrap}.convivencia-dashboard__filters{display:grid;grid-template-columns:repeat(4,minmax(120px,1fr)) auto;align-items:end;gap:.65rem;padding:.85rem;border:1px solid var(--cd-line);border-radius:16px;background:#fff}.convivencia-dashboard__filters label{display:block;margin-bottom:.3rem;color:#67748a;font-size:.64rem;font-weight:700}.convivencia-dashboard__filters .form-control,.convivencia-dashboard__filters .form-select{font-size:.7rem;border-color:#dce2ec}.convivencia-dashboard__filter-actions{display:flex;gap:.4rem}.convivencia-dashboard__filter-actions .btn{display:inline-flex;align-items:center;gap:.25rem}.convivencia-dashboard__pulse{display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:.75rem}.convivencia-dashboard__pulse article{display:flex;align-items:center;gap:.8rem;padding:1rem;border:1px solid var(--cd-line);border-radius:18px;background:#fff;box-shadow:0 10px 25px rgba(35,48,80,.055)}.convivencia-dashboard__pulse article>span{display:grid;width:48px;height:48px;flex:0 0 48px;font-size:1.35rem;place-items:center;border-radius:15px}.convivencia-dashboard__pulse small{display:block;color:#748097;font-size:.62rem;font-weight:750;text-transform:uppercase}.convivencia-dashboard__pulse b{display:block;margin:.12rem 0;color:var(--cd-navy);font-size:1.4rem}.convivencia-dashboard__pulse p{margin:0;color:#7c879a;font-size:.62rem}.convivencia-dashboard__pulse .is-compliance>span{color:#1d735f;background:#e5f5ef}.convivencia-dashboard__pulse .is-overdue>span{color:#ad3e45;background:#fff0f1}.convivencia-dashboard__pulse .is-due>span{color:#9a661f;background:#fff3df}.convivencia-dashboard__metrics{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:.65rem}.convivencia-dashboard__metrics article{display:flex;min-width:0;align-items:center;gap:.5rem;padding:.7rem;border:1px solid var(--cd-line);border-radius:14px;background:#fff}.convivencia-dashboard__metrics article>span{display:grid;width:34px;height:34px;flex:0 0 34px;color:var(--cd-indigo);place-items:center;border-radius:10px;background:#edf0ff}.convivencia-dashboard__metrics small{display:block;overflow:hidden;color:#7b869a;font-size:.55rem;text-overflow:ellipsis;white-space:nowrap}.convivencia-dashboard__metrics b{display:block;color:#34415a;font-size:.86rem}.convivencia-dashboard__metrics .is-danger>span{color:#ad3e45;background:#fff0f1}.convivencia-dashboard__metrics .is-warning>span{color:#9a661f;background:#fff3df}.convivencia-dashboard__metrics .is-success>span{color:#1d735f;background:#e5f5ef}.convivencia-dashboard__alerts{padding:.95rem;border:1px solid #edd1ab;border-radius:18px;background:#fffaf1}.convivencia-dashboard__alerts>header{display:flex;align-items:center;justify-content:space-between}.convivencia-dashboard__alerts header span{color:#9a661f}.convivencia-dashboard__alerts h3{margin:.15rem 0;color:#754b18;font-size:.9rem;font-weight:800}.convivencia-dashboard__alerts header>b{display:grid;width:31px;height:31px;color:#fff;font-size:.72rem;place-items:center;border-radius:10px;background:#d59635}.convivencia-dashboard__alerts>div{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.55rem;margin-top:.7rem}.convivencia-dashboard__alerts article{display:grid;grid-template-columns:29px minmax(0,1fr) auto;gap:.5rem;padding:.65rem;border:1px solid #ead7b8;border-radius:12px;background:#fff}.convivencia-dashboard__alerts article>i{color:#b77924;font-size:1.15rem}.convivencia-dashboard__alerts article b{display:block;color:#664c2b;font-size:.68rem}.convivencia-dashboard__alerts article p{margin:.15rem 0;color:#806d55;font-size:.62rem}.convivencia-dashboard__alerts article small{display:flex;align-items:center;gap:.2rem;color:#9a763f;font-size:.58rem}.convivencia-dashboard__alerts article>strong{color:#a56619}.convivencia-dashboard__alerts article.is-danger{border-color:#ecc4c7;background:#fff7f7}.convivencia-dashboard__alerts article.is-danger>i,.convivencia-dashboard__alerts article.is-danger>strong{color:#b43d45}.convivencia-dashboard__charts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.8rem}.convivencia-dashboard__charts>article{min-width:0;padding:.9rem;border:1px solid var(--cd-line);border-radius:18px;background:#fff;box-shadow:0 10px 25px rgba(35,48,80,.05)}.convivencia-dashboard__charts header{display:flex;align-items:center;justify-content:space-between}.convivencia-dashboard__charts header span{color:#778399}.convivencia-dashboard__charts h3{margin:.13rem 0;color:var(--cd-navy);font-size:.85rem;font-weight:800}.convivencia-dashboard__charts header>i{color:var(--cd-indigo);font-size:1.3rem}.convivencia-dashboard__chart-empty{display:grid;height:285px;margin:0;color:#8591a4;font-size:.68rem;place-items:center;border:1px dashed #d5dce7;border-radius:13px;background:#fafbfe}.convivencia-dashboard__insights{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.8rem}.convivencia-dashboard__insights>div{padding:.9rem;border:1px solid var(--cd-line);border-radius:17px;background:#fff}.convivencia-dashboard__insights h3{margin:.15rem 0 .7rem;color:var(--cd-navy);font-size:.85rem;font-weight:800}.convivencia-dashboard__insights ul{display:grid;gap:.45rem;padding:0;margin:0;list-style:none}.convivencia-dashboard__insights li{display:flex;gap:.5rem;padding:.55rem;border-radius:10px;background:#f7f9fc}.convivencia-dashboard__insights li>i{color:#d59635}.convivencia-dashboard__insights li b,.convivencia-dashboard__insights li small{display:block;font-size:.64rem}.convivencia-dashboard__insights li small{margin-top:.15rem;color:#7c879a}.convivencia-dashboard__insights dl{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.4rem;margin:0}.convivencia-dashboard__insights dl>div{display:flex;justify-content:space-between;gap:.6rem;padding:.55rem;border-radius:10px;background:#f7f9fc}.convivencia-dashboard__insights dt{color:#6f7c91;font-size:.62rem;font-weight:500}.convivencia-dashboard__insights dd{margin:0;color:var(--cd-navy);font-size:.65rem;font-weight:800}.convivencia-dashboard__recent{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}.convivencia-dashboard__recent>article{padding:.85rem;border:1px solid var(--cd-line);border-radius:17px;background:#fff}.convivencia-dashboard__recent>article>header{display:flex;align-items:center;gap:.4rem;margin-bottom:.55rem;color:var(--cd-navy)}.convivencia-dashboard__recent>article>header i{font-size:1.05rem}.convivencia-dashboard__recent h3{margin:0;font-size:.76rem;font-weight:800}.convivencia-dashboard__recent-row{display:flex;align-items:center;justify-content:space-between;gap:.6rem;padding:.5rem 0;border-top:1px solid #edf0f4}.convivencia-dashboard__recent-row span{min-width:0}.convivencia-dashboard__recent-row b,.convivencia-dashboard__recent-row small{display:block;overflow:hidden;font-size:.62rem;text-overflow:ellipsis;white-space:nowrap}.convivencia-dashboard__recent-row b{color:#3b4964}.convivencia-dashboard__recent-row small{margin-top:.13rem;color:#8590a2}.convivencia-dashboard__recent-row em{padding:.25rem .4rem;color:#637088;font-size:.53rem;font-style:normal;font-weight:750;border-radius:999px;background:#eef1f5;white-space:nowrap}.convivencia-dashboard__state{display:grid;min-height:240px;gap:.5rem;color:#7b879a;text-align:center;place-content:center;border:1px dashed #d1d8e4;border-radius:18px;background:#fafbfe}.convivencia-dashboard__state>i{font-size:2rem}.convivencia-dashboard__state p{margin:0;font-size:.68rem}
@media(max-width:1199.98px){.convivencia-dashboard__filters{grid-template-columns:repeat(2,minmax(0,1fr))}.convivencia-dashboard__filter-actions{justify-content:flex-end}.convivencia-dashboard__metrics{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:767.98px){.convivencia-dashboard__hero{align-items:flex-start;flex-direction:column;padding:1rem;border-radius:18px}.convivencia-dashboard__hero .btn{width:100%;justify-content:center}.convivencia-dashboard__pulse,.convivencia-dashboard__charts,.convivencia-dashboard__insights,.convivencia-dashboard__recent{grid-template-columns:1fr}.convivencia-dashboard__alerts>div{grid-template-columns:1fr}}
@media(max-width:575.98px){.convivencia-dashboard__filters,.convivencia-dashboard__metrics{grid-template-columns:1fr}.convivencia-dashboard__filter-actions .btn{flex:1}.convivencia-dashboard__pulse article{padding:.8rem}.convivencia-dashboard__hero p{display:none}}
</style>
