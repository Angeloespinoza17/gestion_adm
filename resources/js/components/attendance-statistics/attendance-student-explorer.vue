<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";
import axios from "axios";
import Swal from "sweetalert2";
import { waitForAndDownloadAttendanceExport } from "../../utils/attendance-export";

const props = defineProps({ filters: { type: Object, required: true }, capabilities: { type: Object, default: () => ({}) } });
const rows = ref([]); const loading = ref(false); const error = ref(null); const selected = ref([]); const detail = ref(null); const detailLoading = ref(false); const columnsOpen = ref(false);
const exporting = ref(false);
const query = reactive({ search: "", sort: "attendance_rate", direction: "asc", page: 1, per_page: 25 });
const meta = reactive({ current_page: 1, last_page: 1, total: 0, per_page: 25 });
const columns = reactive({ level: true, attendance: true, present: true, absent: true, justified: true, late: true, departures: true, risk: true });
let controller = null; let timer = null;
const allSelected = computed(() => rows.value.length > 0 && rows.value.every((row) => selected.value.includes(row.id)));
const pct = (value) => value === null || value === undefined ? "Sin datos" : `${Number(value).toLocaleString("es-CL", { minimumFractionDigits: 1, maximumFractionDigits: 2 })}%`;
const params = () => ({ ...props.filters, ...query });
const load = async () => {
  controller?.abort(); controller = new AbortController(); loading.value = true; error.value = null;
  try {
    const response = await axios.get("/api/attendance-statistics/students", { params: params(), signal: controller.signal });
    rows.value = response.data.data || []; Object.assign(meta, response.data.meta || {});
  } catch (requestError) { if (requestError.code !== "ERR_CANCELED") error.value = requestError.response?.data?.message || "No fue posible cargar estudiantes."; }
  finally { loading.value = false; }
};
const debounceLoad = () => { clearTimeout(timer); query.page = 1; timer = setTimeout(load, 350); };
const sortBy = (key) => { if (query.sort === key) query.direction = query.direction === "asc" ? "desc" : "asc"; else { query.sort = key; query.direction = "asc"; } load(); };
const toggleAll = () => { selected.value = allSelected.value ? selected.value.filter((id) => !rows.value.some((row) => row.id === id)) : [...new Set([...selected.value, ...rows.value.map((row) => row.id)])]; };
const openStudent = async (student) => {
  detail.value = { student: { id: student.id, name: student.name } }; detailLoading.value = true;
  try { const response = await axios.get(`/api/attendance-statistics/students/${student.id}`, { params: props.filters }); detail.value = response.data; }
  catch (requestError) { detail.value = null; await Swal.fire({ icon: "error", title: "No se pudo abrir la ficha", text: requestError.response?.data?.message || "Intenta nuevamente." }); }
  finally { detailLoading.value = false; }
};
const requestExport = async (format) => {
  exporting.value = true;
  let progressOpen = false;
  try {
    const response = await axios.post("/api/attendance-statistics/exports", { academic_year_id: Number(props.filters.academic_year_id), report_type: "students", format, filters: { ...props.filters, ...(selected.value.length ? { student_ids: selected.value } : {}) } });
    Swal.fire({ title: "Preparando archivo", text: "La descarga comenzará automáticamente.", allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });
    progressOpen = true;
    await waitForAndDownloadAttendanceExport(response.data);
    Swal.close(); progressOpen = false;
    await Swal.fire({ icon: "success", title: "Descarga iniciada", timer: 1500, showConfirmButton: false });
  } catch (requestError) {
    if (progressOpen) Swal.close();
    const timedOut = requestError.code === "ATTENDANCE_EXPORT_TIMEOUT";
    await Swal.fire({ icon: timedOut ? "info" : "error", title: timedOut ? "La exportación continúa" : "No se pudo exportar", text: timedOut ? "Podrás descargarla desde Calidad y archivos cuando termine." : requestError.response?.data?.message || requestError.message || "Intenta nuevamente." });
  } finally { exporting.value = false; }
};
watch(() => JSON.stringify(props.filters), () => { query.page = 1; load(); });
onMounted(load); onBeforeUnmount(() => { controller?.abort(); clearTimeout(timer); });
</script>

<template>
  <section class="explorer-panel">
    <header>
      <div><span>EXPLORADOR</span><h2>Estudiantes y seguimiento individual</h2><p>Consulta paginada con riesgo, eventos y acceso a ficha analítica.</p></div>
      <div class="explorer-actions">
        <button type="button" class="icon-btn" title="Configurar columnas" @click="columnsOpen = !columnsOpen"><i class="bx bx-columns"></i></button>
        <button v-if="capabilities.can_export" type="button" class="btn btn-outline-success" :disabled="exporting" @click="requestExport('xls')"><i class="bx bx-spreadsheet"></i>Excel</button>
        <button v-if="capabilities.can_export" type="button" class="btn btn-outline-secondary" :disabled="exporting" @click="requestExport('csv')"><i class="bx bx-file"></i>CSV</button>
        <button v-if="capabilities.can_export" type="button" class="btn btn-danger" :disabled="exporting" @click="requestExport('pdf')"><i class="bx bxs-file-pdf"></i>PDF</button>
      </div>
    </header>
    <div class="explorer-toolbar">
      <label class="search-box"><i class="bx bx-search"></i><input v-model.trim="query.search" type="search" placeholder="Buscar estudiante o RUT" @input="debounceLoad" /></label>
      <select v-model.number="query.per_page" class="form-select" aria-label="Filas por página" @change="query.page = 1; load()"><option :value="10">10 filas</option><option :value="25">25 filas</option><option :value="50">50 filas</option><option :value="100">100 filas</option></select>
      <span>{{ meta.total }} estudiantes<span v-if="selected.length"> · {{ selected.length }} seleccionadas</span></span>
    </div>
    <div v-if="columnsOpen" class="column-picker"><label v-for="(_,key) in columns" :key="key"><input v-model="columns[key]" type="checkbox" />{{ ({ level:'Nivel',attendance:'Asistencia',present:'Presentes',absent:'Ausentes',justified:'Justificadas',late:'Atrasos',departures:'Retiros',risk:'Riesgo' })[key] }}</label></div>
    <div v-if="error" class="alert alert-danger"><i class="bx bx-error-circle"></i>{{ error }}<button type="button" class="btn btn-sm btn-outline-danger" @click="load">Reintentar</button></div>
    <div class="table-responsive explorer-table" :class="{ 'is-loading': loading }">
      <table class="table mb-0">
        <thead><tr><th class="select-cell"><input type="checkbox" :checked="allSelected" aria-label="Seleccionar página" @change="toggleAll" /></th><th><button type="button" @click="sortBy('student_name')">Estudiante <i class="bx bx-sort"></i></button></th><th><button type="button" @click="sortBy('course_name')">Curso <i class="bx bx-sort"></i></button></th><th v-if="columns.level">Nivel</th><th v-if="columns.attendance"><button type="button" @click="sortBy('attendance_rate')">Asistencia <i class="bx bx-sort"></i></button></th><th v-if="columns.present">Presentes</th><th v-if="columns.absent"><button type="button" @click="sortBy('absent')">Ausentes <i class="bx bx-sort"></i></button></th><th v-if="columns.justified">Justificadas</th><th v-if="columns.late"><button type="button" @click="sortBy('late')">Atrasos <i class="bx bx-sort"></i></button></th><th v-if="columns.departures">Retiros</th><th v-if="columns.risk">Riesgo</th><th></th></tr></thead>
        <tbody><tr v-if="!loading && !rows.length"><td :colspan="12" class="empty-cell">No hay estudiantes para los filtros aplicados.</td></tr><tr v-for="row in rows" :key="`${row.id}-${row.course_id}`"><td class="select-cell"><input v-model="selected" type="checkbox" :value="row.id" :aria-label="`Seleccionar ${row.name}`" /></td><td><strong>{{ row.name }}</strong><small>{{ row.rut || 'Sin RUT' }}</small></td><td>{{ row.course }}</td><td v-if="columns.level">{{ row.level }}</td><td v-if="columns.attendance"><span class="rate-pill" :style="{ '--risk-color': row.risk.color }">{{ pct(row.attendance_rate) }}</span></td><td v-if="columns.present">{{ row.present }}</td><td v-if="columns.absent">{{ row.absent }}</td><td v-if="columns.justified">{{ row.justified_absent }}</td><td v-if="columns.late">{{ row.late }}</td><td v-if="columns.departures">{{ row.early_departure }}</td><td v-if="columns.risk"><span class="risk-label" :style="{ '--risk-color': row.risk.color }"><i class="bx" :class="row.risk.icon"></i>{{ row.risk.name }}</span></td><td><button type="button" class="icon-btn" title="Abrir ficha analítica" @click="openStudent(row)"><i class="bx bx-right-arrow-alt"></i></button></td></tr></tbody>
      </table>
      <div v-if="loading" class="table-loader"><span class="spinner-border spinner-border-sm"></span>Actualizando resultados</div>
    </div>
    <footer class="pagination-bar"><span>Página {{ meta.current_page }} de {{ meta.last_page }}</span><div><button type="button" class="icon-btn" :disabled="meta.current_page <= 1" title="Página anterior" @click="query.page--; load()"><i class="bx bx-chevron-left"></i></button><button type="button" class="icon-btn" :disabled="meta.current_page >= meta.last_page" title="Página siguiente" @click="query.page++; load()"><i class="bx bx-chevron-right"></i></button></div></footer>

    <div v-if="detail" class="detail-backdrop" role="dialog" aria-modal="true" aria-label="Ficha analítica de asistencia" @click.self="detail = null" @keydown.esc="detail = null">
      <div class="student-detail"><header><div><span>FICHA ANALÍTICA</span><h3>{{ detail.student?.name }}</h3><p>{{ detail.student?.rut || 'Sin RUT' }} · {{ detail.student?.course || 'Sin curso' }}</p></div><button type="button" class="icon-btn" title="Cerrar" @click="detail = null"><i class="bx bx-x"></i></button></header>
        <div v-if="detailLoading" class="detail-loading"><span class="spinner-border"></span>Cargando ficha</div>
        <template v-else><div class="detail-kpis"><div><span>Asistencia</span><strong>{{ pct(detail.summary?.attendance_rate) }}</strong></div><div><span>Riesgo</span><strong>{{ detail.summary?.risk?.name }}</strong></div><div><span>Ausencias</span><strong>{{ detail.summary?.absent }}</strong></div><div><span>Justificadas</span><strong>{{ detail.summary?.justified_absent }}</strong></div><div><span>Atrasos</span><strong>{{ detail.summary?.late }}</strong></div><div><span>Racha máxima</span><strong>{{ detail.summary?.maximum_consecutive_absences }}</strong></div><div><span>Promedio curso</span><strong>{{ pct(detail.summary?.course_average) }}</strong></div><div><span>Brecha curso</span><strong>{{ detail.summary?.course_gap ?? '-' }} pp</strong></div></div>
          <section class="detail-section"><h4>Evolución mensual</h4><div class="monthly-strip"><div v-for="month in detail.monthly || []" :key="month.key"><span>{{ month.label }}</span><strong>{{ pct(month.attendance_rate) }}</strong><small>{{ month.absent }} ausencias</small></div></div></section>
          <section class="detail-section"><h4>Registros recientes</h4><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Fecha</th><th>Estado</th><th>Justificación</th><th>Atraso</th><th>Retiro</th></tr></thead><tbody><tr v-for="record in (detail.records || []).slice(0,20)" :key="record.id"><td>{{ record.attendance_date }}</td><td>{{ record.status === 'present' ? 'Presente' : 'Ausente' }}</td><td>{{ record.is_justified ? (record.reason || 'Justificada') : '-' }}</td><td>{{ record.minutes_late ? `${record.minutes_late} min` : '-' }}</td><td>{{ record.early_departure ? 'Sí' : '-' }}</td></tr></tbody></table></div></section>
        </template>
      </div>
    </div>
  </section>
</template>

<style scoped>
.explorer-panel{border:1px solid #dfe5ec;border-radius:8px;background:#fff}.explorer-panel>header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1rem;border-bottom:1px solid #e8ecf1}.explorer-panel>header span,.student-detail>header span{color:#6d7889;font-size:.62rem;font-weight:700}.explorer-panel h2,.student-detail h3{margin:.12rem 0;color:#273244;font-size:1rem}.explorer-panel p,.student-detail p{margin:0;color:#768195;font-size:.7rem}.explorer-actions{display:flex;gap:.35rem}.explorer-actions .btn{display:inline-flex;align-items:center;gap:.3rem;font-size:.7rem}.icon-btn{display:grid;place-items:center;width:34px;height:34px;border:1px solid #d9e0e8;border-radius:6px;background:#fff;color:#405189}.explorer-toolbar{display:grid;grid-template-columns:minmax(260px,1fr) 120px auto;align-items:center;gap:.6rem;padding:.7rem 1rem}.search-box{display:flex;align-items:center;gap:.4rem;height:36px;padding:0 .65rem;border:1px solid #d9e0e8;border-radius:6px}.search-box input{min-width:0;width:100%;border:0;outline:0;font-size:.74rem}.explorer-toolbar .form-select{font-size:.7rem}.explorer-toolbar>span{color:#768195;font-size:.68rem}.column-picker{display:flex;flex-wrap:wrap;gap:.75rem;padding:.65rem 1rem;background:#f7f9fb;border-block:1px solid #e8ecf1}.column-picker label{display:flex;align-items:center;gap:.3rem;color:#536174;font-size:.68rem}.explorer-table{position:relative;min-height:220px}.explorer-table table{font-size:.69rem}.explorer-table th{background:#f7f9fb;color:#536174;white-space:nowrap}.explorer-table th button{border:0;background:transparent;color:inherit;font:inherit;font-weight:650}.explorer-table td,.explorer-table th{padding:.52rem .6rem;border-color:#edf0f4;vertical-align:middle}.explorer-table td strong,.explorer-table td small{display:block}.explorer-table td small{color:#8a94a3;font-size:.59rem}.select-cell{width:36px;text-align:center}.rate-pill,.risk-label{display:inline-flex;align-items:center;gap:.25rem;padding:.2rem .38rem;border-left:3px solid var(--risk-color);background:#f5f7fa;color:#354153;font-weight:650;white-space:nowrap}.risk-label{font-weight:500}.empty-cell{height:180px;color:#768195;text-align:center}.table-loader{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;gap:.5rem;background:rgba(255,255,255,.72);color:#536174;font-size:.72rem}.pagination-bar{display:flex;align-items:center;justify-content:space-between;padding:.65rem 1rem;border-top:1px solid #e8ecf1;color:#6d7889;font-size:.68rem}.pagination-bar>div{display:flex;gap:.3rem}.detail-backdrop{position:fixed;z-index:1085;inset:0;display:grid;place-items:center;padding:1rem;background:rgba(25,34,48,.5)}.student-detail{width:min(940px,100%);max-height:calc(100vh - 2rem);overflow:auto;border-radius:8px;background:#fff}.student-detail>header{display:flex;justify-content:space-between;padding:1rem;border-bottom:1px solid #e8ecf1}.detail-loading{display:flex;justify-content:center;align-items:center;gap:.6rem;min-height:300px}.detail-kpis{display:grid;grid-template-columns:repeat(4,1fr);border-bottom:1px solid #e8ecf1}.detail-kpis>div{padding:.7rem;border-right:1px solid #e8ecf1;border-top:1px solid #e8ecf1}.detail-kpis span,.detail-kpis strong{display:block}.detail-kpis span{color:#768195;font-size:.61rem}.detail-kpis strong{margin-top:.18rem;font-size:.9rem}.detail-section{padding:.9rem 1rem}.detail-section h4{margin:0 0 .6rem;font-size:.82rem}.monthly-strip{display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));border:1px solid #e5e9ef}.monthly-strip>div{padding:.6rem;border-right:1px solid #e5e9ef}.monthly-strip span,.monthly-strip strong,.monthly-strip small{display:block}.monthly-strip span,.monthly-strip small{color:#7a8596;font-size:.58rem}.monthly-strip strong{margin:.16rem 0;font-size:.85rem}.alert{display:flex;align-items:center;gap:.5rem;margin:.5rem 1rem;font-size:.72rem}.alert .btn{margin-left:auto}@media(max-width:900px){.explorer-panel>header{flex-direction:column}.explorer-actions{width:100%;overflow-x:auto}.explorer-toolbar{grid-template-columns:1fr 120px}.explorer-toolbar>span{grid-column:1/-1}.detail-kpis{grid-template-columns:repeat(2,1fr)}}@media(max-width:520px){.explorer-toolbar{grid-template-columns:1fr}.detail-backdrop{align-items:end;padding:.5rem}.student-detail{max-height:calc(100dvh - 1rem)}.explorer-actions .btn{min-width:max-content}}
</style>
