<script>
import { formatConvivenciaDate, humanizeConvivenciaStatus } from "../module-utils";

const relationshipMeta = Object.freeze({
  todas: { label: "Todas", color: "#64748b" },
  positiva: { label: "Positivas", color: "#239274" },
  negativa: { label: "Requieren revisión", color: "#d05d72" },
  neutra: { label: "Neutras", color: "#8290a5" },
});

export default {
  name: "ConvivenciaSociogramAnalysis",
  props: {
    record: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    canExport: { type: Boolean, default: false },
    exporting: { type: Boolean, default: false },
  },
  emits: ["export"],
  data() {
    return {
      activeView: "network",
      relationshipFilter: "todas",
      selectedNodeId: null,
    };
  },
  computed: {
    relationshipMeta() { return relationshipMeta; },
    analysis() { return this.record?.analysis || {}; },
    metrics() { return this.analysis.metrics || {}; },
    nodes() { return this.analysis.graph?.nodes || []; },
    edges() { return this.analysis.graph?.edges || []; },
    visibleEdges() {
      return this.relationshipFilter === "todas"
        ? this.edges
        : this.edges.filter((edge) => edge.type === this.relationshipFilter);
    },
    positionedNodes() {
      const nodes = [...this.nodes].sort((a, b) => {
        const roleWeight = (node) => node.role === "sin_elecciones_positivas" ? 2 : (node.role === "alta_recepcion_positiva" ? 0 : 1);
        return roleWeight(a) - roleWeight(b) || Number(b.positive_received) - Number(a.positive_received) || a.name.localeCompare(b.name, "es");
      });
      const central = nodes.filter((node) => node.role === "alta_recepcion_positiva").slice(0, 3);
      const outer = nodes.filter((node) => node.role === "sin_elecciones_positivas");
      const middle = nodes.filter((node) => !central.includes(node) && !outer.includes(node));
      const locate = (items, radius, offset = 0) => items.map((node, index) => {
        const angle = ((Math.PI * 2 * index) / Math.max(items.length, 1)) - (Math.PI / 2) + offset;
        return { ...node, x: 450 + Math.cos(angle) * radius, y: 260 + Math.sin(angle) * radius };
      });
      const centerNodes = central.length === 1
        ? [{ ...central[0], x: 450, y: 260 }]
        : locate(central, central.length === 2 ? 58 : 72, 0.2);

      return [...centerNodes, ...locate(middle, 154, 0.08), ...locate(outer, 225, 0.16)];
    },
    nodeMap() { return new Map(this.positionedNodes.map((node) => [Number(node.id), node])); },
    selectedNode() { return this.nodeMap.get(Number(this.selectedNodeId)) || null; },
    metricCards() {
      return [
        { key: "students_total", label: "Estudiantes", value: this.metrics.students_total || 0, icon: "bx-group", tone: "indigo" },
        { key: "response_rate", label: "Participación", value: `${this.metrics.response_rate || 0}%`, icon: "bx-check-circle", tone: "teal" },
        { key: "positive_links", label: "Vínculos positivos", value: this.metrics.positive_links || 0, icon: "bx-link", tone: "blue" },
        { key: "reciprocal_pairs", label: "Pares recíprocos", value: this.metrics.reciprocal_pairs || 0, icon: "bx-transfer", tone: "purple" },
        { key: "without_positive_nominations", label: "Sin elección positiva", value: this.metrics.without_positive_nominations || 0, icon: "bx-user-minus", tone: "amber" },
        { key: "positive_groups", label: "Grupos conectados", value: this.metrics.positive_groups || 0, icon: "bx-network-chart", tone: "slate" },
      ];
    },
    radialSeries() {
      return [this.metrics.response_rate || 0, this.metrics.reciprocity_rate || 0, this.metrics.positive_density || 0];
    },
    radialOptions() {
      return {
        chart: { type: "radialBar", toolbar: { show: false }, fontFamily: "inherit" },
        colors: ["#4f63d9", "#269177", "#d59b35"],
        labels: ["Participación", "Reciprocidad", "Densidad positiva"],
        plotOptions: { radialBar: { hollow: { size: "28%" }, track: { background: "#edf0f6" }, dataLabels: { name: { fontSize: "11px" }, value: { fontSize: "15px", fontWeight: 800, formatter: (value) => `${Math.round(value)}%` }, total: { show: true, label: "Indicadores", formatter: () => "Red" } } } },
        legend: { show: true, position: "bottom", fontSize: "11px", markers: { size: 7 } },
        stroke: { lineCap: "round" },
      };
    },
    rankingNodes() {
      return [...this.nodes]
        .filter((node) => Number(node.positive_received) > 0 || Number(node.negative_received) > 0)
        .sort((a, b) => Number(b.positive_received) + Number(b.negative_received) - Number(a.positive_received) - Number(a.negative_received))
        .slice(0, 14);
    },
    rankingSeries() {
      return [
        { name: "Positivas recibidas", data: this.rankingNodes.map((node) => Number(node.positive_received || 0)) },
        { name: "Requieren revisión", data: this.rankingNodes.map((node) => Number(node.negative_received || 0)) },
      ];
    },
    rankingOptions() {
      return {
        chart: { type: "bar", stacked: false, toolbar: { show: false }, fontFamily: "inherit" },
        colors: ["#269177", "#d05d72"],
        plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: "64%" } },
        dataLabels: { enabled: false },
        xaxis: { categories: this.rankingNodes.map((node) => this.shortName(node.name)), tickAmount: 5, labels: { style: { fontSize: "10px" } }, title: { text: "Nominaciones recibidas" } },
        yaxis: { labels: { maxWidth: 145, style: { fontSize: "10px" } } },
        legend: { position: "top", horizontalAlign: "left", fontSize: "11px" },
        grid: { borderColor: "#edf0f5" },
        tooltip: { shared: true },
      };
    },
    matrixNodes() { return this.nodes.slice(0, 24); },
    matrixSeries() {
      const edgeScore = new Map();
      this.edges.forEach((edge) => {
        const key = `${edge.source}:${edge.target}`;
        const value = edge.type === "positiva" ? Number(edge.weight || 1) : (edge.type === "negativa" ? -Number(edge.weight || 1) : 0.25);
        edgeScore.set(key, (edgeScore.get(key) || 0) + value);
      });
      return this.matrixNodes.map((source) => ({
        name: this.shortName(source.name, 16),
        data: this.matrixNodes.map((target) => ({ x: this.shortName(target.name, 12), y: source.id === target.id ? null : (edgeScore.get(`${source.id}:${target.id}`) || 0) })),
      }));
    },
    matrixOptions() {
      return {
        chart: { type: "heatmap", toolbar: { show: false }, fontFamily: "inherit" },
        dataLabels: { enabled: false },
        plotOptions: { heatmap: { radius: 2, enableShades: false, colorScale: { ranges: [
          { from: -20, to: -0.1, color: "#e88b9b", name: "Requiere revisión" },
          { from: 0, to: 0, color: "#eef1f6", name: "Sin vínculo" },
          { from: 0.1, to: 0.5, color: "#b8c1cf", name: "Neutro" },
          { from: 0.51, to: 20, color: "#59b99f", name: "Positivo" },
        ] } } },
        xaxis: { labels: { rotate: -45, style: { fontSize: "9px" } } },
        yaxis: { labels: { maxWidth: 120, style: { fontSize: "9px" } } },
        legend: { position: "bottom", fontSize: "10px" },
        tooltip: { y: { formatter: (value) => value === null ? "Misma persona" : (value > 0.5 ? `Positivo (${value})` : (value < 0 ? `Requiere revisión (${Math.abs(value)})` : (value > 0 ? "Neutro" : "Sin vínculo"))) } },
      };
    },
  },
  watch: {
    record() { this.selectedNodeId = null; this.relationshipFilter = "todas"; this.activeView = "network"; },
  },
  methods: {
    date: formatConvivenciaDate,
    status: humanizeConvivenciaStatus,
    shortName(name, max = 19) {
      const parts = String(name || "Estudiante").trim().split(/\s+/);
      const compact = parts.length > 2 ? `${parts[0]} ${parts.at(-1)}` : parts.join(" ");
      return compact.length > max ? `${compact.slice(0, max - 1)}…` : compact;
    },
    nodeColor(node) {
      return ({ alta_recepcion_positiva: "#4f63d9", vinculo_reciproco: "#269177", sin_elecciones_positivas: "#d59b35", participacion_media: "#68809d" })[node.role] || "#68809d";
    },
    nodeRole(node) {
      return ({ alta_recepcion_positiva: "Alta recepción positiva", vinculo_reciproco: "Vínculo recíproco", sin_elecciones_positivas: "Sin nominaciones positivas recibidas", participacion_media: "Participación media" })[node.role] || "Participación";
    },
    edgeColor(edge) { return edge.reciprocal ? "#6658c7" : (relationshipMeta[edge.type]?.color || "#8290a5"); },
    edgePath(edge) {
      const source = this.nodeMap.get(Number(edge.source));
      const target = this.nodeMap.get(Number(edge.target));
      if (!source || !target) return "";
      const dx = target.x - source.x;
      const dy = target.y - source.y;
      const length = Math.max(Math.sqrt((dx * dx) + (dy * dy)), 1);
      const curve = edge.reciprocal ? (Number(edge.source) < Number(edge.target) ? 18 : -18) : 7;
      const middleX = (source.x + target.x) / 2 - (dy / length) * curve;
      const middleY = (source.y + target.y) / 2 + (dx / length) * curve;
      return `M ${source.x} ${source.y} Q ${middleX} ${middleY} ${target.x} ${target.y}`;
    },
    selectNode(node) { this.selectedNodeId = Number(node.id); },
  },
};
</script>

<template>
  <div class="sociogram-analysis">
    <div v-if="loading" class="sociogram-analysis__loading"><i class="bx bx-loader-alt bx-spin"></i><b>Construyendo la red sociométrica…</b></div>
    <template v-else-if="record">
      <header class="sociogram-analysis__hero">
        <div class="sociogram-analysis__hero-icon"><i class="bx bx-network-chart"></i></div>
        <div><span>ANÁLISIS SOCIOMÉTRICO · USO PROFESIONAL</span><h2>{{ record.title }}</h2><p>{{ record.course_section?.display_name || "Sin curso" }} · {{ date(record.applied_on) }} · {{ status(record.status) }}</p></div>
        <button v-if="canExport" type="button" class="sociogram-analysis__export" :disabled="exporting" @click="$emit('export')"><i class="bx" :class="exporting ? 'bx-loader-alt bx-spin' : 'bxs-file-pdf'"></i>{{ exporting ? "Preparando…" : "Descargar informe" }}</button>
      </header>

      <div class="sociogram-analysis__notice"><i class="bx bx-info-circle"></i><div><b>Lectura descriptiva, no diagnóstica</b><p>{{ analysis.methodology?.scope || "Los resultados deben complementarse con observación e interpretación profesional." }}</p></div></div>

      <section class="sociogram-analysis__metrics" aria-label="Indicadores principales">
        <article v-for="metric in metricCards" :key="metric.key" :class="`is-${metric.tone}`"><i class="bx" :class="metric.icon"></i><div><strong>{{ metric.value }}</strong><span>{{ metric.label }}</span></div></article>
      </section>

      <nav class="sociogram-analysis__views" aria-label="Vistas del sociograma">
        <button type="button" :class="{ active: activeView === 'network' }" @click="activeView = 'network'"><i class="bx bx-network-chart"></i>Red relacional</button>
        <button type="button" :class="{ active: activeView === 'balance' }" @click="activeView = 'balance'"><i class="bx bx-bar-chart-alt-2"></i>Balance y métricas</button>
        <button type="button" :class="{ active: activeView === 'matrix' }" @click="activeView = 'matrix'"><i class="bx bx-grid-alt"></i>Matriz sociométrica</button>
      </nav>

      <section v-if="activeView === 'network'" class="sociogram-analysis__network-layout">
        <article class="sociogram-analysis__network-card">
          <header><div><span>MAPA DE VÍNCULOS</span><h3>Red dirigida del curso</h3></div><div class="sociogram-analysis__filters"><button v-for="(meta, key) in relationshipMeta" :key="key" type="button" :class="{ active: relationshipFilter === key }" @click="relationshipFilter = key"><i :style="{ background: meta.color }"></i>{{ meta.label }}</button></div></header>
          <div v-if="nodes.length" class="sociogram-analysis__canvas">
            <svg viewBox="0 0 900 520" role="img" :aria-label="`Sociograma con ${nodes.length} estudiantes y ${visibleEdges.length} vínculos visibles`">
              <defs>
                <marker v-for="(meta, key) in relationshipMeta" :id="`arrow-${key}`" :key="key" markerWidth="8" markerHeight="8" refX="18" refY="3" orient="auto" markerUnits="strokeWidth"><path d="M0,0 L0,6 L7,3 z" :fill="meta.color" /></marker>
                <marker id="arrow-reciprocal" markerWidth="8" markerHeight="8" refX="18" refY="3" orient="auto" markerUnits="strokeWidth"><path d="M0,0 L0,6 L7,3 z" fill="#6658c7" /></marker>
              </defs>
              <g class="network-edges">
                <path v-for="(edge, index) in visibleEdges" :key="`${edge.source}-${edge.target}-${edge.type}-${index}`" :d="edgePath(edge)" fill="none" :stroke="edgeColor(edge)" :stroke-width="edge.reciprocal ? 2.5 : Math.min(1.2 + Number(edge.weight || 1) * .35, 3)" :stroke-dasharray="edge.type === 'negativa' ? '6 5' : (edge.type === 'neutra' ? '2 5' : '')" :marker-end="`url(#${edge.reciprocal ? 'arrow-reciprocal' : `arrow-${edge.type}`})`" opacity=".72"><title>{{ nodeMap.get(Number(edge.source))?.name }} → {{ nodeMap.get(Number(edge.target))?.name }} · {{ relationshipMeta[edge.type]?.label }}{{ edge.reciprocal ? " · Recíproco" : "" }}</title></path>
              </g>
              <g v-for="node in positionedNodes" :key="node.id" class="network-node" role="button" tabindex="0" :aria-label="`${node.name}: ${nodeRole(node)}`" @click="selectNode(node)" @keydown.enter.prevent="selectNode(node)" @keydown.space.prevent="selectNode(node)">
                <circle :cx="node.x" :cy="node.y" :r="selectedNodeId === node.id ? 24 : 21" :fill="nodeColor(node)" :stroke="selectedNodeId === node.id ? '#172554' : '#ffffff'" :stroke-width="selectedNodeId === node.id ? 4 : 3" />
                <text :x="node.x" :y="node.y + 4" text-anchor="middle" fill="#fff" font-size="10" font-weight="800">{{ node.initials }}</text>
                <text :x="node.x" :y="node.y + 34" text-anchor="middle" fill="#34435d" font-size="10" font-weight="700">{{ shortName(node.name) }}</text>
              </g>
            </svg>
          </div>
          <div v-else class="sociogram-analysis__empty"><i class="bx bx-network-chart"></i><b>Aún no hay datos para construir la red</b><span>Registra respuestas válidas del curso para visualizar los vínculos.</span></div>
          <footer class="sociogram-analysis__legend"><span><i class="is-high"></i>Alta recepción positiva</span><span><i class="is-reciprocal"></i>Vínculo recíproco</span><span><i class="is-medium"></i>Participación media</span><span><i class="is-review"></i>Sin nominaciones positivas recibidas</span></footer>
        </article>
        <aside class="sociogram-analysis__node-detail">
          <template v-if="selectedNode"><span>ESTUDIANTE SELECCIONADA</span><h3>{{ selectedNode.name }}</h3><em :style="{ color: nodeColor(selectedNode), backgroundColor: `${nodeColor(selectedNode)}18` }">{{ nodeRole(selectedNode) }}</em><div class="node-detail-grid"><p><b>{{ selectedNode.positive_received }}</b>Positivas recibidas</p><p><b>{{ selectedNode.negative_received }}</b>Requieren revisión</p><p><b>{{ selectedNode.positive_emitted }}</b>Positivas emitidas</p><p><b>{{ selectedNode.mutual_positive_links }}</b>Vínculos recíprocos</p></div><small>Estos indicadores orientan la revisión profesional; no etiquetan ni diagnostican a la estudiante.</small></template>
          <template v-else><i class="bx bx-pointer"></i><h3>Selecciona un nodo</h3><p>Haz clic sobre una estudiante para revisar sus indicadores individuales.</p></template>
        </aside>
      </section>

      <section v-else-if="activeView === 'balance'" class="sociogram-analysis__chart-grid">
        <article><header><span>INDICADORES DE RED</span><h3>Participación, reciprocidad y densidad</h3></header><apexchart type="radialBar" height="350" :options="radialOptions" :series="radialSeries" /></article>
        <article><header><span>DISTRIBUCIÓN INDIVIDUAL</span><h3>Nominaciones recibidas por estudiante</h3></header><apexchart v-if="rankingNodes.length" type="bar" :height="Math.max(350, rankingNodes.length * 34)" :options="rankingOptions" :series="rankingSeries" /><div v-else class="sociogram-analysis__empty"><b>Sin nominaciones registradas</b></div></article>
      </section>

      <section v-else class="sociogram-analysis__matrix-card">
        <header><div><span>MATRIZ SOCIOMÉTRICA</span><h3>Quién selecciona a quién</h3></div><small v-if="nodes.length > 24">Se muestran las primeras 24 estudiantes para mantener la lectura.</small></header>
        <apexchart v-if="matrixNodes.length" type="heatmap" :height="Math.max(420, matrixNodes.length * 25)" :options="matrixOptions" :series="matrixSeries" />
        <div v-else class="sociogram-analysis__empty"><b>Sin datos para la matriz</b></div>
      </section>

      <section class="sociogram-analysis__bottom-grid">
        <article><header><span>PREGUNTAS APLICADAS</span><h3>Cobertura por criterio</h3></header><div class="question-list"><div v-for="question in analysis.question_breakdown || []" :key="question.question_id"><b>{{ question.question_order }}. {{ question.prompt }}</b><span :class="`is-${question.selection_type}`">{{ relationshipMeta[question.selection_type]?.label }}</span><small>{{ question.respondents_total }} respondieron · {{ question.answers_total }} elecciones · máximo {{ question.max_choices }}</small></div></div></article>
        <article><header><span>INTERPRETACIÓN PROFESIONAL</span><h3>Lectura registrada</h3></header><p class="professional-interpretation">{{ record.interpretation || "Aún no se registra una interpretación profesional." }}</p><div class="method-note"><i class="bx bx-shield-quarter"></i><span>Información confidencial. Evita compartir capturas o conclusiones fuera del equipo autorizado.</span></div></article>
      </section>
    </template>
  </div>
</template>

<style scoped>
.sociogram-analysis{display:grid;gap:.85rem;color:#34435d}.sociogram-analysis__loading,.sociogram-analysis__empty{display:grid;min-height:220px;place-items:center;align-content:center;gap:.35rem;color:#7c8799;text-align:center}.sociogram-analysis__loading i,.sociogram-analysis__empty i{color:#596bd3;font-size:2rem}.sociogram-analysis__hero{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:.8rem;padding:1rem 1.1rem;color:#fff;border-radius:17px;background:radial-gradient(circle at 88% 5%,rgba(255,255,255,.19),transparent 32%),linear-gradient(135deg,#283d9d,#6555c6);box-shadow:0 10px 26px rgba(45,61,146,.2)}.sociogram-analysis__hero-icon{display:grid;width:2.8rem;height:2.8rem;place-items:center;font-size:1.45rem;border:1px solid rgba(255,255,255,.25);border-radius:13px;background:rgba(255,255,255,.12)}.sociogram-analysis__hero span,.sociogram-analysis__network-card header span,.sociogram-analysis__chart-grid header span,.sociogram-analysis__matrix-card header span,.sociogram-analysis__bottom-grid header span,.sociogram-analysis__node-detail>span{font-size:.56rem;font-weight:900;letter-spacing:.1em}.sociogram-analysis__hero h2{margin:.1rem 0;font-size:1rem}.sociogram-analysis__hero p{margin:0;color:rgba(255,255,255,.78);font-size:.63rem}.sociogram-analysis__export{display:inline-flex;align-items:center;gap:.35rem;padding:.62rem .78rem;color:#33418b;font-size:.62rem;font-weight:850;border:0;border-radius:10px;background:#fff;box-shadow:0 6px 15px rgba(17,24,39,.14)}.sociogram-analysis__notice{display:flex;align-items:flex-start;gap:.55rem;padding:.7rem .8rem;color:#64531e;border:1px solid #f1dfa7;border-radius:12px;background:#fffaf0}.sociogram-analysis__notice i{font-size:1.05rem}.sociogram-analysis__notice b{font-size:.65rem}.sociogram-analysis__notice p{margin:.08rem 0 0;font-size:.58rem;line-height:1.5}.sociogram-analysis__metrics{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:.55rem}.sociogram-analysis__metrics article{display:flex;align-items:center;gap:.55rem;padding:.72rem;border:1px solid #e0e5ee;border-radius:13px;background:#fff;box-shadow:0 5px 15px rgba(31,45,80,.045)}.sociogram-analysis__metrics article>i{display:grid;width:2rem;height:2rem;place-items:center;color:#fff;font-size:.95rem;border-radius:9px;background:#5c6dd2}.sociogram-analysis__metrics .is-teal>i{background:#269177}.sociogram-analysis__metrics .is-blue>i{background:#3e7ec5}.sociogram-analysis__metrics .is-purple>i{background:#785bbb}.sociogram-analysis__metrics .is-amber>i{background:#d59b35}.sociogram-analysis__metrics .is-slate>i{background:#65798f}.sociogram-analysis__metrics strong,.sociogram-analysis__metrics span{display:block}.sociogram-analysis__metrics strong{font-size:.9rem}.sociogram-analysis__metrics span{color:#778397;font-size:.52rem;line-height:1.25}.sociogram-analysis__views{display:flex;gap:.35rem;padding:.3rem;border:1px solid #e1e6ef;border-radius:12px;background:#f7f8fc}.sociogram-analysis__views button{display:inline-flex;align-items:center;gap:.3rem;padding:.5rem .7rem;color:#68758a;font-size:.6rem;font-weight:800;border:0;border-radius:9px;background:transparent}.sociogram-analysis__views button.active{color:#3e50b4;background:#fff;box-shadow:0 3px 10px rgba(37,52,98,.09)}.sociogram-analysis__network-layout{display:grid;grid-template-columns:minmax(0,1fr) 235px;gap:.65rem}.sociogram-analysis__network-card,.sociogram-analysis__node-detail,.sociogram-analysis__chart-grid article,.sociogram-analysis__matrix-card,.sociogram-analysis__bottom-grid article{overflow:hidden;border:1px solid #dfe5ee;border-radius:15px;background:#fff}.sociogram-analysis__network-card>header,.sociogram-analysis__chart-grid article>header,.sociogram-analysis__matrix-card>header,.sociogram-analysis__bottom-grid article>header{display:flex;align-items:center;justify-content:space-between;gap:.7rem;padding:.75rem .85rem;border-bottom:1px solid #e8ecf3}.sociogram-analysis__network-card h3,.sociogram-analysis__chart-grid h3,.sociogram-analysis__matrix-card h3,.sociogram-analysis__bottom-grid h3{margin:.08rem 0 0;font-size:.72rem}.sociogram-analysis__network-card header span,.sociogram-analysis__chart-grid header span,.sociogram-analysis__matrix-card header span,.sociogram-analysis__bottom-grid header span{color:#5c6dce}.sociogram-analysis__filters{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:.25rem}.sociogram-analysis__filters button{display:inline-flex;align-items:center;gap:.25rem;padding:.3rem .42rem;color:#6d788a;font-size:.53rem;font-weight:750;border:1px solid #e0e5ed;border-radius:99px;background:#fff}.sociogram-analysis__filters button.active{color:#2f3f8d;border-color:#bfc8ef;background:#f1f3ff}.sociogram-analysis__filters button i{width:.45rem;height:.45rem;border-radius:50%}.sociogram-analysis__canvas{min-height:470px;background:radial-gradient(circle at center,#f9faff 0,#fff 60%)}.sociogram-analysis__canvas svg{display:block;width:100%;height:auto;min-height:470px}.network-node{cursor:pointer;outline:none}.network-node circle{transition:r .15s,stroke .15s}.network-node:focus circle{stroke:#172554;stroke-width:4}.sociogram-analysis__legend{display:flex;flex-wrap:wrap;gap:.7rem;padding:.55rem .75rem;color:#768196;font-size:.52rem;border-top:1px solid #edf0f4}.sociogram-analysis__legend span{display:flex;align-items:center;gap:.24rem}.sociogram-analysis__legend i{width:.52rem;height:.52rem;border-radius:50%;background:#68809d}.sociogram-analysis__legend .is-high{background:#4f63d9}.sociogram-analysis__legend .is-reciprocal{background:#269177}.sociogram-analysis__legend .is-review{background:#d59b35}.sociogram-analysis__node-detail{align-self:start;padding:.85rem}.sociogram-analysis__node-detail>i{display:grid;width:2.4rem;height:2.4rem;place-items:center;color:#586bd1;font-size:1.25rem;border-radius:11px;background:#eef1ff}.sociogram-analysis__node-detail h3{margin:.3rem 0;font-size:.78rem}.sociogram-analysis__node-detail>p,.sociogram-analysis__node-detail>small{color:#7a8598;font-size:.56rem;line-height:1.5}.sociogram-analysis__node-detail em{display:inline-flex;padding:.26rem .42rem;font-size:.51rem;font-style:normal;font-weight:800;border-radius:99px}.node-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:.4rem;margin:.75rem 0}.node-detail-grid p{padding:.5rem;margin:0;border:1px solid #e5e9f0;border-radius:10px;background:#fafbfe;color:#788396;font-size:.5rem}.node-detail-grid b{display:block;color:#34435d;font-size:.85rem}.sociogram-analysis__chart-grid,.sociogram-analysis__bottom-grid{display:grid;grid-template-columns:1fr 1.5fr;gap:.65rem}.sociogram-analysis__matrix-card>header small{color:#ad741d;font-size:.52rem}.sociogram-analysis__bottom-grid{grid-template-columns:1.25fr 1fr}.question-list{display:grid;gap:.42rem;padding:.7rem}.question-list>div{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.16rem .5rem;padding:.55rem .65rem;border:1px solid #e5e9f1;border-radius:10px;background:#fafbfe}.question-list b{font-size:.61rem}.question-list span{align-self:start;padding:.17rem .32rem;font-size:.49rem;font-weight:800;border-radius:99px}.question-list .is-positiva{color:#23765f;background:#e5f6f0}.question-list .is-negativa{color:#a54256;background:#fdecef}.question-list .is-neutra{color:#657286;background:#eef1f5}.question-list small{grid-column:1/-1;color:#7d8797;font-size:.52rem}.professional-interpretation{padding:.8rem;margin:0;color:#536079;font-size:.63rem;line-height:1.65;white-space:pre-line}.method-note{display:flex;gap:.4rem;padding:.65rem .8rem;color:#67561e;font-size:.54rem;line-height:1.5;border-top:1px solid #f0e3bc;background:#fffaf0}.method-note i{font-size:.9rem}@media(max-width:1199px){.sociogram-analysis__metrics{grid-template-columns:repeat(3,1fr)}}@media(max-width:900px){.sociogram-analysis__network-layout,.sociogram-analysis__chart-grid,.sociogram-analysis__bottom-grid{grid-template-columns:1fr}.sociogram-analysis__node-detail{display:none}}@media(max-width:650px){.sociogram-analysis__hero{grid-template-columns:auto 1fr}.sociogram-analysis__export{grid-column:1/-1;justify-content:center}.sociogram-analysis__metrics{grid-template-columns:repeat(2,1fr)}.sociogram-analysis__views{overflow:auto}.sociogram-analysis__views button{white-space:nowrap}.sociogram-analysis__network-card>header{align-items:flex-start;flex-direction:column}.sociogram-analysis__filters{justify-content:flex-start}.sociogram-analysis__canvas{overflow:auto}.sociogram-analysis__canvas svg{width:780px}.sociogram-analysis__legend{display:grid;grid-template-columns:1fr 1fr}}
</style>
