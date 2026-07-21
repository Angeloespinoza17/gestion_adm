<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";
import axios from "axios";
import Swal from "sweetalert2";
import { downloadAttendanceExport, waitForAndDownloadAttendanceExport } from "../../utils/attendance-export";

const props = defineProps({ filters: { type: Object, required: true }, capabilities: { type: Object, default: () => ({}) } });
const quality = ref({ summary: {}, groups: [], data: [] });
const exports = ref([]);
const audit = ref([]);
const schedules = ref([]);
const loading = ref(false);
const exporting = ref(false);
const selectedGroup = ref(null);
const exportForm = reactive({ report_type: "executive", format: "pdf" });
const scheduleOpen = ref(false);
const scheduleForm = reactive({ name: "", report_type: "executive", format: "pdf", frequency: "weekly", run_at: "07:00", next_run_at: "", recipients: "" });
let pollTimer = null;

const reportTypes = [
  ["executive", "Resumen ejecutivo"], ["students", "Estudiantes"], ["courses", "Cursos"],
  ["risk", "Riesgo"], ["alerts", "Alertas"], ["interventions", "Intervenciones"],
  ["goals", "Metas"], ["financial", "Impacto financiero"], ["data_quality", "Calidad de datos"],
];
const groupIssues = computed(() => selectedGroup.value ? quality.value.data.filter((item) => item.type === selectedGroup.value.type) : []);
const hasPending = computed(() => exports.value.some((item) => ["pending", "processing"].includes(item.status)));
const statusLabel = (status) => ({ pending: "En cola", processing: "Procesando", completed: "Listo", failed: "Falló" }[status] || status);
const issueLabel = (type) => ({ duplicate_record: "Registros duplicados", missing_record: "Registros faltantes", pending_day: "Jornadas pendientes", invalid_enrollment: "Matrícula inconsistente", orphan_record: "Registro sin relación" }[type] || String(type || "").replaceAll("_", " "));
const fileSize = (bytes) => bytes ? `${(Number(bytes) / 1024).toLocaleString("es-CL", { maximumFractionDigits: 1 })} KB` : "-";

const load = async () => {
  loading.value = true;
  try {
    const requests = [];
    if (props.capabilities.can_configure) requests.push(axios.get("/api/attendance-statistics/data-quality", { params: props.filters }).then((response) => { quality.value = response.data; }));
    if (props.capabilities.can_export) requests.push(axios.get("/api/attendance-statistics/exports").then((response) => { exports.value = response.data.data || []; }));
    if (props.capabilities.can_view_audit) requests.push(axios.get("/api/attendance-statistics/audit", { params: { per_page: 25 } }).then((response) => { audit.value = response.data.data || []; }));
    if (props.capabilities.can_manage_reports) requests.push(axios.get("/api/attendance-statistics/scheduled-reports").then((response) => { schedules.value = response.data.data || []; }));
    await Promise.all(requests);
    schedulePoll();
  } catch (error) {
    await Swal.fire({ icon: "error", title: "No se pudo cargar", text: error.response?.data?.message || "Intenta nuevamente." });
  } finally { loading.value = false; }
};
const schedulePoll = () => {
  clearTimeout(pollTimer);
  if (hasPending.value) pollTimer = setTimeout(loadExports, 4000);
};
const loadExports = async () => {
  if (!props.capabilities.can_export) return;
  try { const response = await axios.get("/api/attendance-statistics/exports"); exports.value = response.data.data || []; }
  finally { schedulePoll(); }
};
const requestExport = async () => {
  exporting.value = true;
  let progressOpen = false;
  try {
    const response = await axios.post("/api/attendance-statistics/exports", {
      academic_year_id: Number(props.filters.academic_year_id), report_type: exportForm.report_type,
      format: exportForm.format, filters: { ...props.filters },
    });
    exports.value.unshift(response.data);
    schedulePoll();
    Swal.fire({ title: "Preparando archivo", text: "La descarga comenzará automáticamente.", allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });
    progressOpen = true;
    await waitForAndDownloadAttendanceExport(response.data, { onProgress: (item) => { exports.value = exports.value.map((current) => current.id === item.id ? item : current); } });
    Swal.close(); progressOpen = false;
    await loadExports();
    await Swal.fire({ icon: "success", title: "Descarga iniciada", timer: 1500, showConfirmButton: false });
  } catch (error) {
    if (progressOpen) Swal.close();
    const timedOut = error.code === "ATTENDANCE_EXPORT_TIMEOUT";
    await Swal.fire({ icon: timedOut ? "info" : "error", title: timedOut ? "La exportación continúa" : "No se pudo exportar", text: timedOut ? "Podrás descargarla desde este listado cuando termine." : error.response?.data?.message || error.message || "Revisa los filtros e intenta nuevamente." });
  } finally { exporting.value = false; }
};
const download = async (item) => {
  try { await downloadAttendanceExport(item); }
  catch (error) { await Swal.fire({ icon: "error", title: "No se pudo descargar", text: error.response?.data?.message || error.message }); }
};
const openSchedule = () => {
  const next = new Date(Date.now() + 86400000); next.setHours(7, 0, 0, 0);
  const date = `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, "0")}-${String(next.getDate()).padStart(2, "0")}T07:00`;
  Object.assign(scheduleForm, { name: "Resumen semanal", report_type: "executive", format: "pdf", frequency: "weekly", run_at: "07:00", next_run_at: date, recipients: "" });
  scheduleOpen.value = true;
};
const saveSchedule = async () => {
  try {
    await axios.post("/api/attendance-statistics/scheduled-reports", {
      academic_year_id: Number(props.filters.academic_year_id), name: scheduleForm.name,
      report_type: scheduleForm.report_type, format: scheduleForm.format, frequency: scheduleForm.frequency,
      run_at: scheduleForm.run_at, next_run_at: scheduleForm.next_run_at, filters: { ...props.filters },
      recipients: scheduleForm.recipients.split(/[;,\n]/).map((value) => value.trim()).filter(Boolean),
    });
    scheduleOpen.value = false; await load();
    await Swal.fire({ icon: "success", title: "Reporte programado", timer: 1400, showConfirmButton: false });
  } catch (error) { await Swal.fire({ icon: "error", title: "No se pudo programar", text: error.response?.data?.message || Object.values(error.response?.data?.errors || {}).flat().join(" ") }); }
};
const removeSchedule = async (item) => {
  const result = await Swal.fire({ icon: "warning", title: "Eliminar programación", text: item.name, showCancelButton: true, confirmButtonText: "Eliminar", cancelButtonText: "Cancelar", confirmButtonColor: "#c13c4a" });
  if (!result.isConfirmed) return;
  await axios.delete(`/api/attendance-statistics/scheduled-reports/${item.id}`); await load();
};

watch(() => JSON.stringify(props.filters), load);
onMounted(load);
onBeforeUnmount(() => clearTimeout(pollTimer));
</script>

<template>
  <section class="quality-stack" :class="{ 'is-loading': loading }">
    <article v-if="capabilities.can_configure" class="quality-panel">
      <header><div><span>CONTROL</span><h2>Calidad de datos</h2></div><button type="button" class="icon-btn" title="Actualizar diagnóstico" @click="load"><i class="bx bx-refresh"></i></button></header>
      <div class="quality-summary">
        <div><span>Abiertas</span><strong>{{ quality.summary?.open || 0 }}</strong></div>
        <div><span>Críticas</span><strong class="text-danger">{{ quality.summary?.critical || 0 }}</strong></div>
        <div><span>Advertencias</span><strong class="text-warning">{{ quality.summary?.warning || 0 }}</strong></div>
        <div><span>Resueltas</span><strong class="text-success">{{ quality.summary?.resolved || 0 }}</strong></div>
      </div>
      <div class="quality-groups">
        <button v-for="group in quality.groups || []" :key="group.type" type="button" @click="selectedGroup = group">
          <i class="bx bx-error-circle"></i><span><strong>{{ issueLabel(group.type) }}</strong><small>{{ group.critical }} críticas</small></span><b>{{ group.total }}</b><i class="bx bx-chevron-right"></i>
        </button>
        <div v-if="!(quality.groups || []).length" class="empty-row"><i class="bx bx-check-shield"></i>No se detectaron incidencias.</div>
      </div>
    </article>

    <article v-if="capabilities.can_export" class="quality-panel">
      <header><div><span>ARCHIVOS</span><h2>Centro de exportaciones</h2></div></header>
      <div class="export-request">
        <label><span>Contenido</span><select v-model="exportForm.report_type" class="form-select"><option v-for="type in reportTypes" :key="type[0]" :value="type[0]">{{ type[1] }}</option></select></label>
        <label><span>Formato</span><select v-model="exportForm.format" class="form-select"><option value="pdf">PDF</option><option value="xls">Excel</option><option value="csv">CSV</option></select></label>
        <button type="button" class="btn btn-primary" :disabled="exporting" @click="requestExport"><i class="bx bx-export"></i>{{ exporting ? 'Generando' : 'Generar y descargar' }}</button>
      </div>
      <div class="export-list">
        <div v-for="item in exports" :key="item.id" class="export-row">
          <i class="bx" :class="item.format === 'pdf' ? 'bxs-file-pdf text-danger' : item.format === 'xls' ? 'bx-spreadsheet text-success' : 'bx-file text-secondary'"></i>
          <div><strong>{{ reportTypes.find((type) => type[0] === item.report_type)?.[1] || item.report_type }}</strong><small>{{ item.format.toUpperCase() }} · {{ fileSize(item.file_size) }}</small></div>
          <span class="status" :class="`status-${item.status}`">{{ statusLabel(item.status) }}<b v-if="item.status === 'processing'"> {{ item.progress }}%</b></span>
          <button v-if="item.status === 'completed'" type="button" class="icon-btn" title="Descargar archivo" @click="download(item)"><i class="bx bx-download"></i></button>
          <button v-else-if="item.status === 'failed'" type="button" class="icon-btn text-danger" title="Ver error" @click="Swal.fire({ icon: 'error', title: 'Exportación fallida', text: item.failure_message })"><i class="bx bx-error"></i></button>
          <span v-else class="spinner-border spinner-border-sm" aria-label="Procesando"></span>
        </div>
        <div v-if="!exports.length" class="empty-row"><i class="bx bx-archive"></i>No hay exportaciones recientes.</div>
      </div>
    </article>

    <article v-if="capabilities.can_manage_reports" class="quality-panel">
      <header><div><span>AUTOMATIZACIÓN</span><h2>Reportes programados</h2></div><button type="button" class="btn btn-sm btn-outline-primary" @click="openSchedule"><i class="bx bx-plus"></i>Programar</button></header>
      <div class="schedule-list">
        <div v-for="item in schedules" :key="item.id"><i class="bx bx-calendar-event"></i><span><strong>{{ item.name }}</strong><small>{{ item.frequency }} · próxima: {{ item.next_run_at ? new Date(item.next_run_at).toLocaleString('es-CL') : 'sin próxima ejecución' }}</small></span><b :class="{ off: !item.active }">{{ item.active ? 'Activo' : 'Finalizado' }}</b><button type="button" class="icon-btn text-danger" title="Eliminar programación" @click="removeSchedule(item)"><i class="bx bx-trash"></i></button></div>
        <div v-if="!schedules.length" class="empty-row"><i class="bx bx-calendar-x"></i>No hay reportes programados.</div>
      </div>
    </article>

    <article v-if="capabilities.can_view_audit" class="quality-panel audit-panel">
      <header><div><span>TRAZABILIDAD</span><h2>Auditoría reciente</h2></div></header>
      <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Fecha</th><th>Acción</th><th>Usuario</th><th>Entidad</th></tr></thead><tbody><tr v-for="item in audit" :key="item.id"><td>{{ new Date(item.created_at).toLocaleString('es-CL') }}</td><td>{{ item.action }}</td><td>{{ item.user?.name || 'Sistema' }}</td><td>{{ item.auditable_type?.split('\\').pop() || '-' }} #{{ item.auditable_id || '-' }}</td></tr><tr v-if="!audit.length"><td colspan="4" class="text-center text-muted">Sin eventos auditados.</td></tr></tbody></table></div>
    </article>

    <div v-if="selectedGroup" class="modal-backdrop-custom" role="dialog" aria-modal="true" @click.self="selectedGroup = null" @keydown.esc="selectedGroup = null">
      <div class="issue-dialog">
        <header><div><span>INCIDENCIAS</span><h3>{{ issueLabel(selectedGroup.type) }}</h3></div><button type="button" class="icon-btn" title="Cerrar" @click="selectedGroup = null"><i class="bx bx-x"></i></button></header>
        <div class="issue-list"><article v-for="issue in groupIssues" :key="issue.id"><span :class="`severity severity-${issue.severity}`">{{ issue.severity }}</span><div><strong>{{ issue.title }}</strong><p>{{ issue.description }}</p><small>{{ issue.course_section?.display_name || 'Institución' }} · {{ issue.suggested_action }}</small></div></article></div>
      </div>
    </div>
    <div v-if="scheduleOpen" class="modal-backdrop-custom" role="dialog" aria-modal="true" @click.self="scheduleOpen = false" @keydown.esc="scheduleOpen = false">
      <form class="schedule-dialog" @submit.prevent="saveSchedule"><header><h3>Programar reporte</h3><button type="button" class="icon-btn" title="Cerrar" @click="scheduleOpen = false"><i class="bx bx-x"></i></button></header><label><span>Nombre</span><input v-model.trim="scheduleForm.name" class="form-control" required /></label><div class="schedule-cols"><label><span>Contenido</span><select v-model="scheduleForm.report_type" class="form-select"><option v-for="type in reportTypes" :key="type[0]" :value="type[0]">{{ type[1] }}</option></select></label><label><span>Formato</span><select v-model="scheduleForm.format" class="form-select"><option value="pdf">PDF</option><option value="xls">Excel</option><option value="csv">CSV</option></select></label></div><div class="schedule-cols"><label><span>Frecuencia</span><select v-model="scheduleForm.frequency" class="form-select"><option value="daily">Diaria</option><option value="weekly">Semanal</option><option value="monthly">Mensual</option><option value="semester">Semestral</option><option value="annual">Anual</option><option value="once">Una vez</option></select></label><label><span>Primera ejecución</span><input v-model="scheduleForm.next_run_at" type="datetime-local" class="form-control" required /></label></div><label><span>Destinatarios</span><textarea v-model.trim="scheduleForm.recipients" class="form-control" rows="3" placeholder="correo@colegio.cl; otro@colegio.cl" required></textarea></label><footer><button type="button" class="btn btn-light" @click="scheduleOpen = false">Cancelar</button><button type="submit" class="btn btn-primary"><i class="bx bx-calendar-check"></i>Programar</button></footer></form>
    </div>
  </section>
</template>

<style scoped>
.quality-stack{display:grid;grid-template-columns:1fr 1fr;gap:.8rem;transition:opacity .15s}.quality-stack.is-loading{opacity:.72}.quality-panel{min-width:0;border:1px solid #dfe5ec;border-radius:8px;background:#fff;padding:.9rem}.quality-panel>header,.issue-dialog>header{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:.75rem}.quality-panel header span,.issue-dialog header span{color:#738093;font-size:.61rem;font-weight:750}.quality-panel h2,.issue-dialog h3{margin:.1rem 0 0;color:#273244;font-size:.92rem}.quality-summary{display:grid;grid-template-columns:repeat(4,1fr);border:1px solid #e5e9ef;border-radius:6px}.quality-summary div{padding:.65rem;border-right:1px solid #e5e9ef}.quality-summary div:last-child{border:0}.quality-summary span,.quality-summary strong{display:block}.quality-summary span{color:#7a8596;font-size:.62rem}.quality-summary strong{font-size:1rem}.quality-groups,.export-list{display:grid;margin-top:.7rem;border-top:1px solid #edf0f4}.quality-groups>button,.export-row{display:grid;grid-template-columns:28px minmax(0,1fr) auto 26px;align-items:center;gap:.55rem;min-height:55px;border:0;border-bottom:1px solid #edf0f4;background:#fff;color:#526071;text-align:left}.quality-groups>button>i:first-child{color:#c84f5a;font-size:1.15rem}.quality-groups strong,.quality-groups small,.export-row strong,.export-row small{display:block}.quality-groups strong,.export-row strong{color:#303b4d;font-size:.73rem}.quality-groups small,.export-row small{color:#8993a2;font-size:.61rem}.export-request{display:grid;grid-template-columns:1fr 150px auto;gap:.55rem;align-items:end}.export-request label span{display:block;margin-bottom:.25rem;color:#657184;font-size:.65rem;font-weight:650}.export-request .form-select,.export-request .btn{min-height:36px;font-size:.72rem}.export-row>i{font-size:1.25rem}.status{font-size:.64rem;font-weight:700}.status-completed{color:#248260}.status-failed{color:#bd3443}.status-processing,.status-pending{color:#a67414}.empty-row{display:flex;align-items:center;justify-content:center;gap:.4rem;min-height:90px;color:#8490a0;font-size:.7rem}.audit-panel{grid-column:1/-1}.audit-panel table{font-size:.68rem}.audit-panel th{background:#f7f9fb;color:#5d697b}.icon-btn{display:grid;place-items:center;width:32px;height:32px;border:1px solid #dbe1e9;border-radius:6px;background:#fff;color:#405189}.modal-backdrop-custom{position:fixed;z-index:1090;inset:0;display:grid;place-items:center;padding:1rem;background:rgba(24,32,45,.5)}.issue-dialog{width:min(780px,100%);max-height:min(720px,90vh);overflow:hidden;border-radius:8px;background:#fff;padding:1rem;box-shadow:0 25px 60px rgba(20,30,45,.25)}.issue-list{display:grid;max-height:600px;overflow:auto;border-top:1px solid #e8ecf1}.issue-list article{display:grid;grid-template-columns:75px 1fr;gap:.7rem;padding:.75rem .2rem;border-bottom:1px solid #edf0f4}.issue-list p{margin:.18rem 0;color:#5d697b;font-size:.7rem}.issue-list small{color:#8590a0;font-size:.62rem}.severity{align-self:start;border-radius:4px;padding:.2rem .35rem;text-align:center;font-size:.58rem;font-weight:750;text-transform:uppercase}.severity-critical{background:#fdecee;color:#b63040}.severity-warning{background:#fff4dc;color:#966814}.severity-info{background:#eaf2fc;color:#366fa9}@media(max-width:1000px){.quality-stack{grid-template-columns:1fr}}@media(max-width:600px){.quality-summary{grid-template-columns:1fr 1fr}.quality-summary div:nth-child(2){border-right:0}.quality-summary div:nth-child(n+3){border-top:1px solid #e5e9ef}.export-request{grid-template-columns:1fr}.issue-list article{grid-template-columns:1fr}}
.schedule-list{display:grid;border-top:1px solid #edf0f4}.schedule-list>div:not(.empty-row){display:grid;grid-template-columns:26px minmax(0,1fr) auto 32px;align-items:center;gap:.55rem;min-height:54px;border-bottom:1px solid #edf0f4}.schedule-list strong,.schedule-list small{display:block}.schedule-list strong{color:#303b4d;font-size:.72rem}.schedule-list small{color:#8993a2;font-size:.6rem}.schedule-list>b{color:#248260;font-size:.6rem}.schedule-list>b.off{color:#8993a2}.schedule-dialog{display:grid;gap:.65rem;width:min(560px,100%);max-height:90vh;overflow:auto;border-radius:8px;background:#fff;padding:1rem;box-shadow:0 25px 60px rgba(20,30,45,.25)}.schedule-dialog header,.schedule-dialog footer{display:flex;align-items:center;justify-content:space-between}.schedule-dialog h3{margin:0;font-size:1rem}.schedule-dialog label>span{display:block;margin-bottom:.25rem;color:#657184;font-size:.65rem;font-weight:650}.schedule-cols{display:grid;grid-template-columns:1fr 1fr;gap:.6rem}.schedule-dialog footer{justify-content:flex-end;gap:.4rem;padding-top:.6rem;border-top:1px solid #e8ecf1}@media(max-width:600px){.schedule-cols{grid-template-columns:1fr}}
</style>
