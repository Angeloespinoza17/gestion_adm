<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import Layout from "../../../layouts/main.vue";
import LoadingState from "../../../components/ui/loading-state.vue";
import VepRiskMatrix from "../../../components/risk-prevention/iper/VepRiskMatrix.vue";
import RiskLevelBadge from "../../../components/risk-prevention/iper/RiskLevelBadge.vue";
import { riskMatrixApi } from "../../../services/risk-matrix-api";
import { formatRiskDate, formatRiskError, showRiskError } from "../../../components/risk-prevention/module-utils";

const router = useRouter();
const loading = ref(true);
const dashboard = ref({ metrics: {}, levels: [], heatmap: [], risks_by_process: [], risks_by_family: [] });
const matrices = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const catalogs = ref({ work_centers: [] });
const filters = reactive({ search: "", status: "", work_center_id: "", risk_level: "", overdue_controls: false, page: 1 });
let searchTimer;
const cards = computed(() => [
  ["Matrices vigentes", dashboard.value.metrics.current_matrices, "bx-check-shield", "#0e7490"],
  ["Borradores", dashboard.value.metrics.draft_matrices, "bx-edit", "#475467"],
  ["Pendientes de aprobación", dashboard.value.metrics.pending_approval, "bx-time-five", "#d97706"],
  ["Próximas a revisión", dashboard.value.metrics.upcoming_review, "bx-calendar-exclamation", "#7c3aed"],
  ["Riesgos importantes", dashboard.value.metrics.important_risks, "bx-error", "#dc6803"],
  ["Riesgos intolerables", dashboard.value.metrics.intolerable_risks, "bx-block", "#b42318"],
  ["Medidas vencidas", dashboard.value.metrics.overdue_actions, "bx-alarm-exclamation", "#be123c"],
  ["Avance del programa", `${dashboard.value.metrics.program_progress || 0}%`, "bx-trending-up", "#15803d"],
]);
async function load() {
  loading.value = true;
  try {
    const listFilters = { ...filters, overdue_controls: filters.overdue_controls ? 1 : undefined };
    const [stats, list, options] = await Promise.all([riskMatrixApi.dashboard(), riskMatrixApi.list(listFilters), riskMatrixApi.catalogs()]);
    dashboard.value = stats;
    matrices.value = list.data || [];
    meta.value = list.meta || meta.value;
    catalogs.value = options;
  } catch (error) { showRiskError(formatRiskError(error, "No se pudo cargar Matrices IPER/MIPER.")); }
  finally { loading.value = false; }
}
function filter() { clearTimeout(searchTimer); searchTimer = setTimeout(() => { filters.page = 1; load(); }, 320); }
function selectCell(cell) { filters.risk_level = cell.level_code; filter(); }
function page(number) { if (number < 1 || number > meta.value.last_page) return; filters.page = number; load(); }
onMounted(load);
</script>

<template>
  <Layout>
    <div class="iper-hero">
      <div><span class="eyebrow">Prevención de Riesgos · Gestión estratégica</span><h2>Matriz IPER/MIPER</h2><p>Identificación de peligros, evaluación versionada y ejecución preventiva en un solo circuito trazable.</p></div>
      <div class="hero-actions"><router-link to="/risk-prevention/matrices/importaciones" class="btn btn-outline-light"><i class="bx bx-import"></i> Importar histórico</router-link><router-link to="/risk-prevention/matrices/nueva" class="btn btn-light"><i class="bx bx-plus"></i> Nueva matriz</router-link></div>
      <div class="hero-orb"></div>
    </div>
    <LoadingState v-if="loading" message="Consolidando indicadores IPER…" />
    <template v-else>
      <div class="metric-grid"><article v-for="card in cards" :key="card[0]" :style="{ '--accent': card[3] }"><i class="bx" :class="card[2]"></i><div><span>{{ card[0] }}</span><strong>{{ card[1] ?? 0 }}</strong></div></article></div>
      <div class="analytics-grid">
        <section class="panel"><header><div><span>Mapa de criticidad</span><h5>Matriz de calor VEP</h5></div><small>Selecciona una celda para filtrar</small></header><VepRiskMatrix :cells="dashboard.heatmap" @select="selectCell" /></section>
        <section class="panel risk-distribution"><header><div><span>Distribución actual</span><h5>Riesgos por nivel</h5></div></header><div v-for="item in dashboard.levels" :key="item.code" class="distribution-row"><RiskLevelBadge :level="item" /><div class="bar-track"><span :style="{ width: `${Math.max(4, (item.total / Math.max(...dashboard.levels.map((x) => x.total), 1)) * 100)}%`, background: item.color }"></span></div><strong>{{ item.total }}</strong></div><div class="program-progress"><div><span>Avance preventivo global</span><strong>{{ dashboard.metrics.program_progress || 0 }}%</strong></div><div><span :style="{ width: `${dashboard.metrics.program_progress || 0}%` }"></span></div></div></section>
      </div>
      <section class="panel matrix-list-panel">
        <header><div><span>Portafolio institucional</span><h5>Listado de matrices</h5></div><router-link to="/risk-prevention/preventive-program" class="btn btn-sm btn-outline-primary">Abrir programa preventivo</router-link></header>
        <div class="filter-grid"><div class="search-field"><i class="bx bx-search"></i><input v-model="filters.search" type="search" placeholder="Código, folio o nombre…" @input="filter" /></div><select v-model="filters.work_center_id" class="form-select" @change="filter"><option value="">Todos los centros</option><option v-for="item in catalogs.work_centers" :key="item.id" :value="item.id">{{ item.name }}</option></select><select v-model="filters.status" class="form-select" @change="filter"><option value="">Todos los estados</option><option value="draft">Borrador</option><option value="in_review">En revisión</option><option value="observed">Observada</option><option value="approved">Aprobada</option><option value="superseded">Reemplazada</option></select><select v-model="filters.risk_level" class="form-select" @change="filter"><option value="">Todos los niveles</option><option value="tolerable">Tolerable</option><option value="moderate">Moderado</option><option value="important">Importante</option><option value="intolerable">Intolerable</option></select><label class="overdue-check"><input v-model="filters.overdue_controls" type="checkbox" @change="filter" /> Con medidas vencidas</label></div>
        <div class="table-responsive"><table class="table align-middle portfolio-table"><thead><tr><th>Matriz</th><th>Centro</th><th>Versión</th><th>Estado</th><th>Riesgos</th><th>Críticos</th><th>Próxima revisión</th><th>Programa</th><th></th></tr></thead><tbody><tr v-for="matrix in matrices" :key="matrix.id"><td><strong>{{ matrix.code }} · {{ matrix.name }}</strong><small>{{ matrix.folio || 'Sin folio' }}</small></td><td>{{ matrix.work_center?.name || 'Institucional' }}</td><td>v{{ matrix.active_version?.number || 1 }}</td><td><span class="status-dot" :class="`status-dot--${matrix.active_version?.status}`">{{ matrix.active_version?.status || 'draft' }}</span></td><td><strong>{{ matrix.risk_count }}</strong></td><td><div class="critical-counts"><span class="important">{{ matrix.important_count }} I</span><span class="intolerable">{{ matrix.intolerable_count }} IN</span></div></td><td>{{ formatRiskDate(matrix.active_version?.next_review_at) }}</td><td><div class="mini-progress"><span :style="{ width: `${matrix.program_progress}%` }"></span></div><small>{{ matrix.program_progress }}%</small></td><td><div class="table-actions"><button type="button" title="Ver detalle" @click="router.push(`/risk-prevention/matrices/${matrix.id}`)"><i class="bx bx-show"></i></button><button v-if="['draft','observed'].includes(matrix.active_version?.status)" type="button" title="Editar borrador" @click="router.push(`/risk-prevention/matrices/${matrix.id}/editar?version=${matrix.active_version.id}`)"><i class="bx bx-edit"></i></button></div></td></tr><tr v-if="!matrices.length"><td colspan="9" class="empty-state"><i class="bx bx-grid-alt"></i><strong>No hay matrices para estos filtros</strong><span>Crea una matriz o ajusta los criterios.</span></td></tr></tbody></table></div>
        <footer class="pagination-bar"><span>{{ meta.total || 0 }} matrices</span><div><button class="btn btn-sm btn-light" :disabled="meta.current_page <= 1" @click="page(meta.current_page - 1)">Anterior</button><span>Página {{ meta.current_page }} de {{ meta.last_page }}</span><button class="btn btn-sm btn-light" :disabled="meta.current_page >= meta.last_page" @click="page(meta.current_page + 1)">Siguiente</button></div></footer>
      </section>
    </template>
  </Layout>
</template>

<style scoped>
.iper-hero{position:relative;overflow:hidden;display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:1rem;padding:1.35rem 1.5rem;border-radius:14px;background:linear-gradient(125deg,#102b46,#176b76);color:#fff;box-shadow:0 14px 28px rgba(16,43,70,.18)}.iper-hero h2{margin:.1rem 0;font-size:1.55rem}.iper-hero p{max-width:720px;margin:0;color:#d7ebee;font-size:.74rem}.eyebrow{font-size:.61rem;font-weight:800;letter-spacing:.09em;text-transform:uppercase}.hero-actions{z-index:2;display:flex;gap:.5rem}.hero-actions .btn{display:inline-flex;align-items:center;gap:.35rem;white-space:nowrap}.hero-orb{position:absolute;right:-50px;top:-90px;width:220px;height:220px;border:35px solid rgba(255,255,255,.07);border-radius:50%}.metric-grid{display:grid;grid-template-columns:repeat(8,1fr);gap:.65rem;margin-bottom:.8rem}.metric-grid article{display:flex;align-items:center;gap:.55rem;padding:.7rem;border:1px solid #e0e7ee;border-radius:10px;background:#fff;box-shadow:0 4px 12px rgba(16,24,40,.035)}.metric-grid article>i{display:grid;place-items:center;width:34px;height:34px;border-radius:9px;background:color-mix(in srgb,var(--accent) 10%,#fff);color:var(--accent);font-size:1rem}.metric-grid span,.metric-grid strong{display:block}.metric-grid span{color:#667085;font-size:.56rem}.metric-grid strong{color:#1d2939;font-size:1.05rem}.analytics-grid{display:grid;grid-template-columns:1.15fr .85fr;gap:.8rem;margin-bottom:.8rem}.panel{border:1px solid #e0e7ee;border-radius:12px;background:#fff;box-shadow:0 5px 16px rgba(16,24,40,.04)}.panel>header{display:flex;align-items:center;justify-content:space-between;padding:.8rem 1rem;border-bottom:1px solid #edf0f3}.panel header span{color:#667085;font-size:.58rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase}.panel h5{margin:.08rem 0 0;font-size:.9rem}.panel header small{color:#98a2b3;font-size:.6rem}.panel :deep(.vep-shell){margin:.8rem}.risk-distribution{padding-bottom:.7rem}.distribution-row{display:grid;grid-template-columns:145px 1fr 30px;align-items:center;gap:.6rem;padding:.55rem .9rem}.bar-track{height:7px;overflow:hidden;border-radius:99px;background:#eef2f6}.bar-track span{display:block;height:100%;border-radius:99px}.distribution-row>strong{font-size:.72rem;text-align:right}.program-progress{margin:.55rem .9rem 0;padding:.65rem;border-radius:8px;background:#f3f8f7}.program-progress>div:first-child{display:flex;justify-content:space-between;color:#28536f;font-size:.66rem}.program-progress>div:last-child{height:8px;margin-top:.4rem;overflow:hidden;border-radius:99px;background:#d8e8e5}.program-progress>div:last-child span{display:block;height:100%;background:#16806f}.filter-grid{display:grid;grid-template-columns:1.4fr repeat(3,1fr) auto;align-items:center;gap:.5rem;padding:.7rem .9rem;border-bottom:1px solid #edf0f3}.search-field{display:flex;align-items:center;gap:.35rem;height:38px;padding:0 .55rem;border:1px solid #d6dee7;border-radius:7px}.search-field input{width:100%;border:0;outline:0;font-size:.7rem}.filter-grid .form-select{font-size:.68rem}.overdue-check{display:flex;align-items:center;gap:.35rem;color:#667085;font-size:.65rem;white-space:nowrap}.portfolio-table{margin:0;font-size:.68rem}.portfolio-table th{padding:.55rem .7rem;background:#f8fafc;color:#536174;font-size:.6rem;white-space:nowrap}.portfolio-table td{padding:.55rem .7rem;border-color:#eef1f4}.portfolio-table td strong,.portfolio-table td small{display:block}.portfolio-table td small{color:#8a94a3;font-size:.58rem}.status-dot{display:inline-flex;align-items:center;gap:.3rem;padding:.22rem .4rem;border-radius:999px;background:#f2f4f7;font-size:.58rem;font-weight:700}.status-dot:before{width:6px;height:6px;border-radius:50%;background:#667085;content:""}.status-dot--approved:before{background:#16a34a}.status-dot--in_review:before{background:#f59e0b}.status-dot--observed:before{background:#ea580c}.critical-counts{display:flex;gap:.25rem}.critical-counts span{padding:.15rem .28rem;border-radius:4px;font-size:.56rem;font-weight:750}.critical-counts .important{background:#fff7ed;color:#c2410c}.critical-counts .intolerable{background:#fff1f2;color:#be123c}.mini-progress{width:85px;height:6px;overflow:hidden;border-radius:99px;background:#eaecf0}.mini-progress span{display:block;height:100%;background:#16806f}.table-actions{display:flex;gap:.25rem}.table-actions button{display:grid;place-items:center;width:30px;height:30px;border:1px solid #d6dee7;border-radius:6px;background:#fff;color:#28536f}.empty-state{height:180px;text-align:center}.empty-state i,.empty-state strong,.empty-state span{display:block}.empty-state i{color:#98a2b3;font-size:2rem}.empty-state span{color:#8a94a3}.pagination-bar{display:flex;align-items:center;justify-content:space-between;padding:.65rem .9rem;border-top:1px solid #edf0f3;color:#667085;font-size:.64rem}.pagination-bar>div{display:flex;align-items:center;gap:.5rem}@media(max-width:1400px){.metric-grid{grid-template-columns:repeat(4,1fr)}}@media(max-width:900px){.iper-hero{align-items:flex-start;flex-direction:column}.analytics-grid{grid-template-columns:1fr}.filter-grid{grid-template-columns:1fr 1fr}.metric-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:560px){.hero-actions{width:100%;overflow:auto}.metric-grid,.filter-grid{grid-template-columns:1fr}.pagination-bar{align-items:flex-start;flex-direction:column;gap:.5rem}}
</style>
