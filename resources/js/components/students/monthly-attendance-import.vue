<script setup>
import { computed, ref, watch } from "vue";
import axios from "axios";

const props = defineProps({
  academicYears: { type: Array, default: () => [] },
  initialYearId: { type: [Number, String], default: null },
});
const emit = defineEmits(["completed"]);

const open = ref(false);
const loading = ref(false);
const uploading = ref(false);
const resolving = ref(false);
const error = ref("");
const success = ref("");
const resolutionError = ref("");
const yearId = ref(props.initialYearId || null);
const file = ref(null);
const workspace = ref({ summary: {}, imports: [], unmatched: [], catalogs: { academic_years: [] } });
const activeTab = ref("unmatched");
const selectedRow = ref(null);
const candidateSearch = ref("");
const candidateResults = ref([]);
const candidateSearchPerformed = ref(false);
const resolutionNote = ref("");

watch(() => props.initialYearId, (value) => {
  if (value) yearId.value = value;
});

const years = computed(() => workspace.value.catalogs?.academic_years?.length
  ? workspace.value.catalogs.academic_years
  : props.academicYears);
const displayedCandidates = computed(() => candidateSearchPerformed.value
  ? candidateResults.value
  : (selectedRow.value?.candidates || []));
const activeImports = computed(() => (workspace.value.imports || []).filter((item) => item.is_active));

function messageFrom(errorObject, fallback) {
  const errors = errorObject?.response?.data?.errors;
  if (errors) return Object.values(errors).flat().join(" ");
  return errorObject?.response?.data?.message || fallback;
}

function monthLabel(period) {
  if (!period) return "Sin período";
  const [year, month] = String(period).split("-");
  return new Date(Number(year), Number(month) - 1, 1).toLocaleDateString("es-CL", { month: "long", year: "numeric" });
}

function rate(value) {
  return value === null || value === undefined ? "—" : `${Number(value).toFixed(1)}%`;
}

function statusLabel(status) {
  return ({ partial: "Con pendientes", completed: "Completa", superseded: "Reemplazada", processing: "Procesando" })[status] || status;
}

async function show() {
  open.value = true;
  error.value = "";
  success.value = "";
  resolutionError.value = "";
  if (!yearId.value) yearId.value = props.initialYearId || years.value.find((item) => item.is_active)?.id || null;
  await loadWorkspace();
}

function close() {
  if (uploading.value || resolving.value) return;
  open.value = false;
}

async function loadWorkspace() {
  loading.value = true;
  error.value = "";
  try {
    const { data } = await axios.get("/api/students/attendance/monthly-imports", {
      params: { academic_year_id: yearId.value || undefined },
    });
    workspace.value = data;
    if (selectedRow.value) {
      selectedRow.value = data.unmatched.find((row) => row.id === selectedRow.value.id) || data.unmatched[0] || null;
    } else {
      selectedRow.value = data.unmatched[0] || null;
    }
    candidateResults.value = [];
    candidateSearchPerformed.value = false;
    resolutionError.value = "";
  } catch (requestError) {
    error.value = messageFrom(requestError, "No se pudo cargar el centro de conciliación.");
  } finally {
    loading.value = false;
  }
}

function handleFile(event) {
  file.value = event.target.files?.[0] || null;
  error.value = "";
}

async function upload() {
  if (!yearId.value || !file.value) {
    error.value = "Selecciona el año académico y el Excel mensual.";
    return;
  }
  uploading.value = true;
  error.value = "";
  success.value = "";
  try {
    const form = new FormData();
    form.append("academic_year_id", yearId.value);
    form.append("file", file.value);
    const { data } = await axios.post("/api/students/attendance/monthly-imports", form, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    success.value = `${data.message} ${data.data.matched_rows} alumnas asociadas y ${data.data.unmatched_rows} casos pendientes.`;
    file.value = null;
    activeTab.value = data.data.unmatched_rows ? "unmatched" : "history";
    await loadWorkspace();
    emit("completed", data.data);
  } catch (requestError) {
    error.value = messageFrom(requestError, "No se pudo procesar el Excel mensual.");
  } finally {
    uploading.value = false;
  }
}

function selectRow(row) {
  selectedRow.value = row;
  candidateSearch.value = "";
  candidateResults.value = [];
  candidateSearchPerformed.value = false;
  resolutionNote.value = "";
  resolutionError.value = "";
}

async function searchCandidates() {
  if (!candidateSearch.value || candidateSearch.value.trim().length < 2) return;
  resolutionError.value = "";
  try {
    const { data } = await axios.get("/api/students/attendance/monthly-imports/candidates", {
      params: {
        monthly_attendance_import_row_id: selectedRow.value?.id,
        search: candidateSearch.value.trim(),
      },
    });
    candidateResults.value = data.data || [];
    candidateSearchPerformed.value = true;
  } catch (requestError) {
    candidateResults.value = [];
    candidateSearchPerformed.value = true;
    resolutionError.value = messageFrom(requestError, "No se pudo buscar alumnas.");
  }
}

async function resolve(candidate) {
  if (!selectedRow.value || !candidate?.student_profile_id) return;
  resolving.value = true;
  resolutionError.value = "";
  success.value = "";
  try {
    const { data } = await axios.patch(`/api/students/attendance/monthly-import-rows/${selectedRow.value.id}/match`, {
      student_profile_id: candidate.student_profile_id,
      note: resolutionNote.value || null,
    });
    success.value = `${data.message} ${selectedRow.value.source_name} → ${candidate.name}.`;
    await loadWorkspace();
    emit("completed", data.data);
  } catch (requestError) {
    resolutionError.value = messageFrom(requestError, "No se pudo guardar la asociación manual.");
  } finally {
    resolving.value = false;
  }
}
</script>

<template>
  <button type="button" class="btn btn-monthly-excel" @click="show"><i class="bx bx-spreadsheet"></i>Cargar Excel mensual</button>

  <Teleport to="body">
    <div v-if="open" class="monthly-overlay" role="dialog" aria-modal="true" aria-label="Carga mensual de asistencia" @click.self="close" @keydown.esc="close">
      <section class="monthly-shell">
        <header class="monthly-header">
          <div class="monthly-header-icon"><i class="bx bx-spreadsheet"></i></div>
          <div><span>CENTRO DE DATOS ESTUDIANTILES</span><h2>Asistencia mensual y conciliación</h2><p>Una carga institucional para todos los cursos, con RUT, SEP, PIE y trazabilidad.</p></div>
          <button type="button" class="monthly-close" aria-label="Cerrar" @click="close"><i class="bx bx-x"></i></button>
        </header>

        <div v-if="error" class="monthly-alert danger"><i class="bx bx-error-circle"></i><span>{{ error }}</span><button @click="error = ''"><i class="bx bx-x"></i></button></div>
        <div v-if="success" class="monthly-alert success"><i class="bx bx-check-circle"></i><span>{{ success }}</span><button @click="success = ''"><i class="bx bx-x"></i></button></div>

        <section class="upload-ribbon">
          <label><span>Año académico</span><select v-model="yearId" class="form-select" :disabled="uploading" @change="loadWorkspace"><option :value="null">Seleccionar año</option><option v-for="year in years" :key="year.id" :value="year.id">{{ year.name }}{{ year.is_active ? ' · activo' : '' }}</option></select></label>
          <label class="excel-picker"><input type="file" accept=".xls,.xlsx,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" :disabled="uploading" @change="handleFile"><i class="bx bx-cloud-upload"></i><span><strong>{{ file?.name || 'Seleccionar reporte mensual' }}</strong><small>.xls o .xlsx · máximo 25 MB</small></span></label>
          <button type="button" class="btn btn-primary" :disabled="uploading || !file || !yearId" @click="upload"><i class="bx" :class="uploading ? 'bx-loader-alt bx-spin' : 'bx-data'"></i>{{ uploading ? 'Procesando hojas…' : 'Procesar Excel' }}</button>
        </section>

        <div class="monthly-kpis">
          <article><span>Versiones activas</span><strong>{{ activeImports.length }}</strong><small>meses disponibles</small></article>
          <article><span>Alumnas vinculadas</span><strong>{{ workspace.summary?.matched || 0 }}</strong><small>match validado</small></article>
          <article :class="{ warning: workspace.summary?.unmatched }"><span>Sin match</span><strong>{{ workspace.summary?.unmatched || 0 }}</strong><small>requieren corrección</small></article>
          <article><span>Último período</span><strong>{{ activeImports[0] ? monthLabel(activeImports[0].period) : 'Sin carga' }}</strong><small>fuente institucional</small></article>
        </div>

        <nav class="monthly-tabs">
          <button type="button" :class="{ active: activeTab === 'unmatched' }" @click="activeTab = 'unmatched'"><i class="bx bx-git-compare"></i>Casos sin match <b>{{ workspace.summary?.unmatched || 0 }}</b></button>
          <button type="button" :class="{ active: activeTab === 'history' }" @click="activeTab = 'history'"><i class="bx bx-history"></i>Historial de cargas</button>
          <span v-if="loading"><i class="bx bx-loader-alt bx-spin"></i>Actualizando</span>
        </nav>

        <div v-if="activeTab === 'unmatched'" class="reconcile-layout">
          <aside class="case-list">
            <button v-for="row in workspace.unmatched" :key="row.id" type="button" :class="{ active: selectedRow?.id === row.id }" @click="selectRow(row)">
              <span class="case-avatar">{{ row.source_name.split(' ').slice(0, 2).map(part => part[0]).join('') }}</span>
              <span><strong>{{ row.source_name }}</strong><small>{{ row.source_rut }} · {{ row.source_course_name }}</small></span>
              <i class="bx bx-chevron-right"></i>
            </button>
            <div v-if="!workspace.unmatched?.length && !loading" class="case-empty"><i class="bx bx-check-shield"></i><strong>Todo conciliado</strong><span>No quedan alumnas pendientes en las versiones activas.</span></div>
          </aside>

          <main v-if="selectedRow" class="case-detail">
            <header><div><span>FILA {{ selectedRow.source_row }} · {{ selectedRow.source_sheet }}</span><h3>{{ selectedRow.source_name }}</h3><p>{{ selectedRow.source_rut }} · curso informado {{ selectedRow.source_course_name }}</p></div><em :class="selectedRow.match_status"><i class="bx bx-error"></i>{{ selectedRow.match_status === 'course_conflict' ? 'Conflicto de curso' : selectedRow.match_status === 'course_not_found' ? 'Curso no encontrado' : 'Sin coincidencia' }}</em></header>
            <div class="source-facts">
              <article><span>Asistencia</span><strong>{{ rate(selectedRow.attendance_rate) }}</strong><small>{{ selectedRow.present_days }} presentes · {{ selectedRow.absent_days }} ausentes</small></article>
              <article><span>Clasificación</span><strong>{{ selectedRow.is_sep_priority ? 'SEP prioritaria' : selectedRow.is_sep_preferential ? 'SEP preferente' : 'Sin SEP' }}</strong><small>{{ selectedRow.is_pie ? 'Participante PIE' : 'Sin PIE informado' }}</small></article>
              <article><span>Período</span><strong>{{ monthLabel(selectedRow.period) }}</strong><small>{{ selectedRow.filename }}</small></article>
            </div>
            <div v-if="resolutionError" class="resolution-feedback" role="alert" aria-live="assertive"><i class="bx bx-error-circle"></i><div><strong>No se pudo asociar</strong><span>{{ resolutionError }}</span></div></div>
            <form class="candidate-search" @submit.prevent="searchCandidates"><i class="bx bx-search"></i><input v-model="candidateSearch" class="form-control" placeholder="Buscar otra alumna por nombre o RUT"><button class="btn btn-outline-primary">Buscar</button></form>
            <div class="candidate-heading"><div><span>COINCIDENCIAS PROPUESTAS</span><strong>Selecciona sólo después de verificar RUT y curso</strong></div><small>{{ displayedCandidates.length }} opciones</small></div>
            <div class="candidate-list">
              <article v-for="candidate in displayedCandidates" :key="candidate.student_profile_id">
                <div class="candidate-check"><i class="bx bx-user-check"></i></div>
                <div><strong>{{ candidate.name }}</strong><span>{{ candidate.rut || 'Sin RUT' }} · {{ candidate.course || 'Sin curso' }}</span></div>
                <em v-if="candidate.score !== null && candidate.score !== undefined">{{ candidate.score }}%</em>
                <button type="button" class="btn btn-sm btn-primary" :disabled="resolving" @click="resolve(candidate)">Asociar</button>
              </article>
              <div v-if="!displayedCandidates.length" class="candidate-empty"><i class="bx bx-user-x"></i><strong>{{ candidateSearchPerformed ? 'Sin resultados disponibles' : 'No hay coincidencias disponibles' }}</strong><span>El RUT {{ selectedRow.source_rut }} no está en la matrícula de este año o las coincidencias ya pertenecen a otra fila del Excel. Corrige o crea la matrícula antes de asociar.</span></div>
            </div>
            <label class="resolution-note"><span>Nota de conciliación <small>opcional</small></span><textarea v-model="resolutionNote" class="form-control" rows="2" maxlength="1000" placeholder="Ej. RUT corregido y curso confirmado con matrícula 2026."></textarea></label>
          </main>
          <main v-else class="case-detail case-detail-empty"><i class="bx bx-git-compare"></i><strong>Selecciona un caso pendiente</strong><span>Compara los datos del Excel con la matrícula antes de asociar.</span></main>
        </div>

        <div v-else class="history-panel">
          <div class="history-intro"><div><span>TRAZABILIDAD POR PERÍODO</span><h3>Versiones mensuales conservadas</h3></div><p>Una nueva carga del mismo mes reemplaza la versión visible, pero la anterior permanece auditada.</p></div>
          <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Período</th><th>Archivo</th><th>Versión</th><th>Match</th><th>Registros</th><th>Correcciones preservadas</th><th>Estado</th></tr></thead><tbody>
            <tr v-for="item in workspace.imports" :key="item.id"><td><strong>{{ monthLabel(item.period) }}</strong></td><td><span>{{ item.filename }}</span><small>{{ item.sheet_count }} hojas</small></td><td>v{{ item.version }}</td><td>{{ item.matched_rows }} / {{ item.parsed_rows }}<small v-if="item.unmatched_rows">{{ item.unmatched_rows }} pendientes</small></td><td>{{ item.imported_records }}</td><td>{{ item.preserved_manual_records }}</td><td><span class="history-status" :class="item.status">{{ statusLabel(item.status) }}</span></td></tr>
            <tr v-if="!workspace.imports?.length"><td colspan="7" class="history-empty">Aún no hay cargas mensuales para el año seleccionado.</td></tr>
          </tbody></table></div>
        </div>
      </section>
    </div>
  </Teleport>
</template>

<style scoped>
.btn-monthly-excel{display:inline-flex;align-items:center;gap:.35rem;border:1px solid #18835f;background:#effaf5;color:#126b4e;font-size:.72rem;font-weight:700}.btn-monthly-excel:hover{background:#18835f;color:#fff}.monthly-overlay{position:fixed;z-index:1100;inset:0;display:grid;place-items:center;padding:1rem;background:rgba(18,27,43,.62);backdrop-filter:blur(5px)}.monthly-shell{width:min(1220px,calc(100vw - 2rem));max-height:calc(100dvh - 2rem);overflow:auto;border-radius:18px;background:#f5f8fb;box-shadow:0 30px 90px rgba(12,22,39,.35);color:#253149}.monthly-header{position:relative;display:flex;align-items:center;gap:1rem;padding:1.15rem 1.35rem;background:linear-gradient(130deg,#223558,#405189 60%,#397b70);color:#fff}.monthly-header-icon{display:grid;width:48px;height:48px;place-items:center;border:1px solid rgba(255,255,255,.22);border-radius:14px;background:rgba(255,255,255,.1);font-size:1.6rem}.monthly-header>div:nth-child(2){flex:1}.monthly-header span,.history-intro span,.candidate-heading span,.case-detail header>div>span{font-size:.61rem;font-weight:800;letter-spacing:.11em}.monthly-header h2{margin:.12rem 0;font-size:1.25rem}.monthly-header p{margin:0;color:#dce5ef;font-size:.7rem}.monthly-close{display:grid;width:38px;height:38px;place-items:center;border:0;border-radius:50%;background:rgba(255,255,255,.12);color:#fff;font-size:1.4rem}.monthly-alert{display:flex;align-items:center;gap:.5rem;margin:.75rem 1rem 0;padding:.6rem .75rem;border-radius:9px;font-size:.7rem}.monthly-alert span{flex:1}.monthly-alert button{border:0;background:transparent;color:inherit}.monthly-alert.danger{background:#fff0f1;color:#a42f3e}.monthly-alert.success{background:#eaf8f1;color:#166c50}.upload-ribbon{display:grid;grid-template-columns:220px minmax(360px,1fr) auto;align-items:end;gap:.7rem;margin:1rem;padding:1rem;border:1px solid #dfe7ee;border-radius:12px;background:#fff}.upload-ribbon label>span{display:block;margin-bottom:.3rem;color:#526176;font-size:.63rem;font-weight:800}.upload-ribbon .form-select{min-height:42px;font-size:.72rem}.excel-picker{display:flex;min-height:58px;align-items:center;gap:.65rem;padding:.55rem .7rem;border:1px dashed #aebdcb;border-radius:9px;background:#f8fafc;cursor:pointer}.excel-picker input{position:absolute;width:1px;height:1px;opacity:0}.excel-picker>i{color:#18835f;font-size:1.55rem}.excel-picker span{margin:0!important}.excel-picker strong,.excel-picker small{display:block}.excel-picker strong{color:#354258;font-size:.72rem}.excel-picker small{margin-top:.08rem;color:#7d8999;font-size:.61rem}.upload-ribbon>.btn{display:inline-flex;min-height:42px;align-items:center;gap:.35rem;font-size:.72rem}.monthly-kpis{display:grid;grid-template-columns:repeat(4,1fr);margin:0 1rem 1rem;border:1px solid #dfe6ed;border-radius:11px;background:#fff;overflow:hidden}.monthly-kpis article{padding:.75rem .9rem}.monthly-kpis article+article{border-left:1px solid #e5eaf0}.monthly-kpis span,.monthly-kpis strong,.monthly-kpis small{display:block}.monthly-kpis span{color:#748196;font-size:.6rem;text-transform:uppercase}.monthly-kpis strong{margin:.12rem 0;color:#263249;font-size:1.02rem}.monthly-kpis small{color:#8792a1;font-size:.61rem}.monthly-kpis article.warning strong{color:#ba6d13}.monthly-tabs{display:flex;align-items:center;gap:.15rem;margin:0 1rem;border-bottom:1px solid #dce4eb}.monthly-tabs button{display:flex;align-items:center;gap:.35rem;padding:.65rem .8rem;border:0;border-bottom:2px solid transparent;background:transparent;color:#69778a;font-size:.68rem;font-weight:700}.monthly-tabs button.active{border-bottom-color:#405189;color:#405189}.monthly-tabs b{display:grid;min-width:20px;height:20px;place-items:center;border-radius:999px;background:#e9eef7;font-size:.59rem}.monthly-tabs>span{display:flex;align-items:center;gap:.3rem;margin-left:auto;color:#7d8998;font-size:.62rem}.reconcile-layout{display:grid;grid-template-columns:340px minmax(0,1fr);min-height:480px;margin:0 1rem 1rem;border:1px solid #dce4eb;border-top:0;border-radius:0 0 12px 12px;background:#fff;overflow:hidden}.case-list{max-height:570px;overflow:auto;border-right:1px solid #e3e9ef;background:#f8fafc}.case-list>button{display:grid;width:100%;grid-template-columns:38px minmax(0,1fr) auto;align-items:center;gap:.55rem;padding:.7rem;border:0;border-bottom:1px solid #e8edf2;background:transparent;text-align:left}.case-list>button.active{background:#edf2fb;box-shadow:inset 3px 0 #405189}.case-avatar{display:grid;width:36px;height:36px;place-items:center;border-radius:10px;background:#e2e9f4;color:#405189;font-size:.66rem;font-weight:800}.case-list strong,.case-list small{display:block}.case-list strong{overflow:hidden;color:#344158;font-size:.7rem;text-overflow:ellipsis;white-space:nowrap}.case-list small{margin-top:.1rem;color:#7b8797;font-size:.59rem}.case-list>button>i{color:#94a0af}.case-empty,.case-detail-empty{display:grid;place-items:center;align-content:center;gap:.3rem;min-height:300px;padding:1rem;color:#7d8999;text-align:center}.case-empty i,.case-detail-empty>i{color:#3d8b6d;font-size:2rem}.case-empty strong,.case-empty span,.case-detail-empty strong,.case-detail-empty span{display:block}.case-empty strong,.case-detail-empty strong{color:#4a576b;font-size:.75rem}.case-empty span,.case-detail-empty span{font-size:.64rem}.case-detail{padding:1rem}.case-detail>header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.case-detail h3{margin:.15rem 0;color:#263249;font-size:1.05rem}.case-detail header p{margin:0;color:#758195;font-size:.67rem}.case-detail header>em{display:inline-flex;align-items:center;gap:.3rem;padding:.3rem .45rem;border-radius:999px;background:#fff1df;color:#9b621c;font-size:.62rem;font-style:normal;font-weight:700}.case-detail header>em.course_conflict{background:#fff0f1;color:#a53543}.source-facts{display:grid;grid-template-columns:repeat(3,1fr);margin-top:.85rem;border:1px solid #e1e7ed;border-radius:9px}.source-facts article{padding:.65rem}.source-facts article+article{border-left:1px solid #e5eaf0}.source-facts span,.source-facts strong,.source-facts small{display:block}.source-facts span{color:#7a8697;font-size:.59rem;text-transform:uppercase}.source-facts strong{margin:.12rem 0;color:#344157;font-size:.76rem}.source-facts small{color:#8792a1;font-size:.59rem}.resolution-feedback{display:flex;align-items:flex-start;gap:.55rem;margin-top:.75rem;padding:.65rem .75rem;border:1px solid #f1cbd0;border-radius:9px;background:#fff3f4;color:#9d3442}.resolution-feedback>i{margin-top:.05rem;font-size:1rem}.resolution-feedback strong,.resolution-feedback span{display:block}.resolution-feedback strong{font-size:.69rem}.resolution-feedback span{margin-top:.08rem;font-size:.62rem;line-height:1.45}.candidate-search{position:relative;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.4rem;margin-top:.8rem}.candidate-search>i{position:absolute;top:50%;left:.65rem;transform:translateY(-50%);color:#8c98a7}.candidate-search input{padding-left:2rem;font-size:.68rem}.candidate-search .btn{font-size:.65rem}.candidate-heading{display:flex;align-items:end;justify-content:space-between;margin:.8rem 0 .4rem}.candidate-heading strong{display:block;color:#475469;font-size:.68rem}.candidate-heading small{color:#8490a0;font-size:.6rem}.candidate-list{display:grid;gap:.35rem}.candidate-list article{display:grid;grid-template-columns:34px minmax(0,1fr) auto auto;align-items:center;gap:.55rem;padding:.55rem;border:1px solid #e3e8ed;border-radius:8px}.candidate-check{display:grid;width:32px;height:32px;place-items:center;border-radius:8px;background:#e9f6f0;color:#1c7759}.candidate-list strong,.candidate-list span{display:block}.candidate-list strong{color:#344157;font-size:.69rem}.candidate-list span{margin-top:.08rem;color:#7c8898;font-size:.59rem}.candidate-list em{color:#405189;font-size:.63rem;font-style:normal;font-weight:800}.candidate-list .btn{font-size:.61rem}.candidate-empty{display:grid;justify-items:center;gap:.18rem;padding:1.25rem;border:1px dashed #cdd9e2;border-radius:9px;background:#f8fafc;color:#738195;text-align:center}.candidate-empty i{color:#a56c22;font-size:1.35rem}.candidate-empty strong,.candidate-empty span{display:block}.candidate-empty strong{color:#46546a;font-size:.7rem}.candidate-empty span{max-width:520px;font-size:.62rem;line-height:1.5}.resolution-note{display:block;margin-top:.7rem}.resolution-note>span{display:block;margin-bottom:.25rem;color:#536176;font-size:.63rem;font-weight:700}.resolution-note small{font-weight:400}.resolution-note textarea{font-size:.66rem}.history-panel{margin:0 1rem 1rem;border:1px solid #dce4eb;border-top:0;border-radius:0 0 12px 12px;background:#fff;overflow:hidden}.history-intro{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.9rem 1rem;border-bottom:1px solid #e5eaf0}.history-intro h3{margin:.12rem 0 0;font-size:.92rem}.history-intro p{max-width:480px;margin:0;color:#758195;font-size:.64rem;text-align:right}.history-panel th{background:#f8fafc;color:#6c788b;font-size:.59rem;text-transform:uppercase}.history-panel td{font-size:.66rem}.history-panel td strong,.history-panel td small{display:block}.history-panel td small{color:#84909f;font-size:.58rem}.history-status{display:inline-flex;padding:.25rem .4rem;border-radius:999px;background:#edf1f5;color:#687587;font-size:.59rem;font-weight:700}.history-status.completed{background:#e8f7ef;color:#1b7456}.history-status.partial{background:#fff2df;color:#95601b}.history-status.superseded{background:#eceff3;color:#778292}.history-empty{padding:2rem!important;color:#7e8998;text-align:center}@media(max-width:900px){.upload-ribbon{grid-template-columns:1fr 1fr}.upload-ribbon>.btn{grid-column:1/-1;justify-content:center}.monthly-kpis{grid-template-columns:1fr 1fr}.monthly-kpis article:nth-child(3){border-left:0;border-top:1px solid #e5eaf0}.monthly-kpis article:nth-child(4){border-top:1px solid #e5eaf0}.reconcile-layout{grid-template-columns:1fr}.case-list{max-height:210px;border-right:0;border-bottom:1px solid #e3e9ef}.source-facts{grid-template-columns:1fr}.source-facts article+article{border-top:1px solid #e5eaf0;border-left:0}}@media(max-width:600px){.monthly-overlay{align-items:end;padding:.4rem}.monthly-shell{width:100%;max-height:calc(100dvh - .8rem);border-radius:14px}.monthly-header{align-items:flex-start}.monthly-header-icon{display:none}.monthly-header h2{font-size:1.05rem}.upload-ribbon{grid-template-columns:1fr}.upload-ribbon>.btn{grid-column:auto}.monthly-kpis{grid-template-columns:1fr 1fr}.monthly-kpis strong{font-size:.86rem}.reconcile-layout,.history-panel{margin-inline:.5rem}.monthly-tabs{margin-inline:.5rem}.case-detail>header{flex-direction:column}.candidate-list article{grid-template-columns:34px minmax(0,1fr) auto}.candidate-list em{display:none}.candidate-list .btn{grid-column:2/-1}.history-intro{align-items:flex-start;flex-direction:column}.history-intro p{text-align:left}}
</style>
