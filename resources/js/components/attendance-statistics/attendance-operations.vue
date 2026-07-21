<script setup>
import { onMounted, reactive, ref, watch } from "vue";
import axios from "axios";
import Swal from "sweetalert2";

const props = defineProps({ filters: { type: Object, required: true }, capabilities: { type: Object, default: () => ({}) } });
const mode = ref("alerts"); const loading = ref(false); const error = ref(null); const groups = ref([]); const alerts = ref([]); const interventions = ref([]); const selectedGroup = ref(null); const groupAlerts = ref([]); const openingGroupId = ref(null); const interventionModal = ref(false); const saving = ref(false);
const form = reactive({ academic_year_id: null, course_section_id: null, student_profile_id: null, attendance_alert_id: null, status: "new", probable_cause: "", description: "", due_on: "", reason: "" });
const load = async () => {
  loading.value = true; error.value = null;
  try {
    const [alertResponse, interventionResponse] = await Promise.all([
      axios.get("/api/attendance-statistics/alerts", { params: { ...props.filters, per_page: 25 } }),
      axios.get("/api/attendance-statistics/interventions", { params: { ...props.filters, per_page: 25 } }),
    ]);
    groups.value = alertResponse.data.groups || []; alerts.value = alertResponse.data.data || [];
    interventions.value = interventionResponse.data.data || [];
  } catch (requestError) { error.value = requestError.response?.data?.message || "No fue posible cargar alertas e intervenciones."; }
  finally { loading.value = false; }
};
const openGroup = async (group) => {
  if (openingGroupId.value !== null) return;
  openingGroupId.value = group.course_id || "unassigned";
  try {
    const response = await axios.get("/api/attendance-statistics/alerts", { params: { ...props.filters, course_section_id: group.course_id || undefined, per_page: 100 } });
    groupAlerts.value = response.data.data || [];
    selectedGroup.value = group;
  } catch (requestError) {
    await Swal.fire({ icon: "error", title: "No se pudo abrir el grupo", text: requestError.response?.data?.message || "Intenta nuevamente." });
  } finally { openingGroupId.value = null; }
};
const assignToMe = async (alert) => {
  const confirmation = await Swal.fire({ icon: "question", title: "Asignarme esta alerta", text: alert.title, showCancelButton: true, confirmButtonText: "Asignarme", cancelButtonText: "Cancelar" });
  if (!confirmation.isConfirmed) return;
  try { const response = await axios.post(`/api/attendance-statistics/alerts/${alert.id}/assign`, { status: "acknowledged", reason: "Asignación voluntaria desde el centro de alertas." }); Object.assign(alert, response.data); await load(); }
  catch (requestError) { await Swal.fire({ icon: "error", title: "No se pudo asignar", text: requestError.response?.data?.message || "Intenta nuevamente." }); }
};
const startIntervention = (alert) => {
  Object.assign(form, { academic_year_id: Number(props.filters.academic_year_id), course_section_id: alert.course_section_id, student_profile_id: alert.student_profile_id, attendance_alert_id: alert.id, status: "new", probable_cause: "", description: `Seguimiento originado por: ${alert.title}.`, due_on: "", reason: "Apertura desde alerta activa." });
  selectedGroup.value = null;
  interventionModal.value = true;
};
const saveIntervention = async () => {
  saving.value = true;
  try { await axios.post("/api/attendance-statistics/interventions", form); interventionModal.value = false; selectedGroup.value = null; await load(); await Swal.fire({ icon: "success", title: "Intervención creada", timer: 1500, showConfirmButton: false }); }
  catch (requestError) { await Swal.fire({ icon: "error", title: "No se pudo crear", text: requestError.response?.data?.message || Object.values(requestError.response?.data?.errors || {}).flat().join(" ") }); }
  finally { saving.value = false; }
};
const closeIntervention = async (item) => {
  const result = await Swal.fire({ title: "Cerrar intervención", input: "textarea", inputLabel: "Motivo de cierre", showCancelButton: true, confirmButtonText: "Cerrar caso", confirmButtonColor: "#c13c4a", inputValidator: (value) => !value?.trim() ? "Ingresa el motivo." : undefined });
  if (!result.isConfirmed) return;
  try { await axios.patch(`/api/attendance-statistics/interventions/${item.id}`, { academic_year_id: item.academic_year_id, course_section_id: item.course_section_id, student_profile_id: item.student_profile_id, attendance_alert_id: item.attendance_alert_id, risk_level_id: item.risk_level_id, responsible_user_id: item.responsible_user_id, status: "closed", probable_cause: item.probable_cause, description: item.description, due_on: item.due_on, result: "closed", closure_reason: result.value.trim(), reason: result.value.trim() }); await load(); }
  catch (requestError) { await Swal.fire({ icon: "error", title: "No se pudo cerrar", text: requestError.response?.data?.message || Object.values(requestError.response?.data?.errors || {}).flat().join(" ") }); }
};
watch(() => JSON.stringify(props.filters), load); onMounted(load);
</script>

<template>
  <section class="operations-panel">
    <header><div><span>GESTIÓN DE RIESGO</span><h2>Alertas e intervenciones</h2><p>Alertas agrupadas por curso y seguimiento nominal bajo demanda.</p></div><div class="segmented"><button type="button" :class="{ active: mode === 'alerts' }" @click="mode = 'alerts'"><i class="bx bx-bell"></i>Alertas</button><button type="button" :class="{ active: mode === 'interventions' }" @click="mode = 'interventions'"><i class="bx bx-task"></i>Intervenciones</button></div></header>
    <div v-if="loading" class="operation-state"><span class="spinner-border"></span>Cargando gestión de riesgo</div>
    <div v-else-if="error" class="operation-state error"><i class="bx bx-error-circle"></i>{{ error }}<button type="button" class="btn btn-sm btn-outline-danger" @click="load">Reintentar</button></div>
    <template v-else-if="mode === 'alerts'">
      <div v-if="!groups.length" class="operation-state"><i class="bx bx-check-shield"></i><strong>No hay alertas en este alcance</strong></div>
      <div v-else class="group-list"><button v-for="group in groups" :key="group.course_id || 'unassigned'" type="button" class="group-row" :class="{ 'is-opening': openingGroupId === (group.course_id || 'unassigned') }" :disabled="openingGroupId !== null" @click="openGroup(group)"><span class="alert-icon"><span v-if="openingGroupId === (group.course_id || 'unassigned')" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-error"></i></span><span class="group-name"><strong>{{ group.course }}</strong><small>{{ group.students }} estudiantes con alertas</small></span><span><strong>{{ group.critical }}</strong><small>críticas</small></span><span><strong>{{ group.total }}</strong><small>activas</small></span><i class="bx bx-chevron-right"></i></button></div>
    </template>
    <template v-else>
      <div v-if="!interventions.length" class="operation-state"><i class="bx bx-clipboard"></i><strong>No hay intervenciones para los filtros aplicados</strong></div>
      <div v-else class="table-responsive"><table class="table intervention-table mb-0"><thead><tr><th>Folio</th><th>Estudiante</th><th>Curso</th><th>Estado</th><th>Responsable</th><th>Vencimiento</th><th>Acciones</th><th></th></tr></thead><tbody><tr v-for="item in interventions" :key="item.id"><td><strong>{{ item.folio }}</strong></td><td><strong>{{ item.student?.name }}</strong><small>{{ item.student?.rut }}</small></td><td>{{ item.course?.display_name || '-' }}</td><td><span class="status-pill">{{ item.status }}</span></td><td>{{ item.responsible?.name || 'Sin asignar' }}</td><td :class="{ overdue: item.due_on && new Date(item.due_on) < new Date() && item.status !== 'closed' }">{{ item.due_on || '-' }}</td><td>{{ item.actions?.length || 0 }}</td><td><button v-if="capabilities.can_manage_interventions && item.status !== 'closed'" type="button" class="icon-btn" title="Cerrar intervención" @click="closeIntervention(item)"><i class="bx bx-check"></i></button></td></tr></tbody></table></div>
    </template>

    <Teleport to="body">
      <div v-if="selectedGroup" class="attendance-modal-layer" role="dialog" aria-modal="true" :aria-label="`Alertas de ${selectedGroup.course}`" @click.self="selectedGroup = null" @keydown.esc="selectedGroup = null"><div class="modal-surface"><header><div><span>DETALLE DEL CURSO</span><h3>{{ selectedGroup.course }}</h3><p>{{ selectedGroup.total }} alertas · {{ selectedGroup.critical }} críticas</p></div><button type="button" class="icon-btn" title="Cerrar" @click="selectedGroup = null"><i class="bx bx-x"></i></button></header><div class="alert-list"><article v-for="alert in groupAlerts" :key="alert.id" class="alert-row" :class="`severity-${alert.severity}`"><i class="bx bx-error"></i><div><strong>{{ alert.title }}</strong><span>{{ alert.student_profile?.registered_name || `${alert.student_profile?.first_name || ''} ${alert.student_profile?.last_name || ''}` }}</span><small>{{ alert.description }} · {{ alert.detected_on }}</small></div><div class="row-actions"><button v-if="capabilities.can_manage_alerts && !alert.assigned_to" type="button" class="btn btn-sm btn-outline-primary" @click="assignToMe(alert)">Asignarme</button><button v-if="capabilities.can_manage_interventions && alert.student_profile_id" type="button" class="btn btn-sm btn-primary" @click="startIntervention(alert)">Intervenir</button></div></article></div></div></div>
      <div v-if="interventionModal" class="attendance-modal-layer" role="dialog" aria-modal="true" aria-label="Nueva intervención" @click.self="interventionModal = false" @keydown.esc="interventionModal = false"><form class="modal-surface form-surface" @submit.prevent="saveIntervention"><header><div><span>NUEVO CASO</span><h3>Crear intervención de asistencia</h3></div><button type="button" class="icon-btn" title="Cerrar" @click="interventionModal = false"><i class="bx bx-x"></i></button></header><div class="form-grid"><label>Causa probable<input v-model.trim="form.probable_cause" class="form-control" maxlength="120" placeholder="Sin información" /></label><label>Fecha límite<input v-model="form.due_on" type="date" class="form-control" /></label><label class="full">Descripción<textarea v-model.trim="form.description" rows="4" class="form-control" required></textarea></label><label class="full">Motivo de apertura<textarea v-model.trim="form.reason" rows="2" class="form-control" required></textarea></label></div><footer><button type="button" class="btn btn-light" @click="interventionModal = false">Cancelar</button><button type="submit" class="btn btn-primary" :disabled="saving"><span v-if="saving" class="spinner-border spinner-border-sm"></span>Crear intervención</button></footer></form></div>
    </Teleport>
  </section>
</template>

<style scoped>
.operations-panel{border:1px solid #dfe5ec;border-radius:8px;background:#fff}.operations-panel>header,.modal-surface>header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1rem;border-bottom:1px solid #e7ebf0}.operations-panel>header span,.modal-surface>header span{color:#6d7889;font-size:.62rem;font-weight:700}.operations-panel h2,.modal-surface h3{margin:.12rem 0;color:#273244;font-size:1rem}.operations-panel p,.modal-surface p{margin:0;color:#748094;font-size:.7rem}.segmented{display:flex;padding:3px;border:1px solid #dce2ea;border-radius:7px;background:#f5f7fa}.segmented button{display:inline-flex;align-items:center;gap:.3rem;height:30px;padding:0 .65rem;border:0;border-radius:5px;background:transparent;color:#657184;font-size:.68rem}.segmented button.active{background:#fff;color:#405189;box-shadow:0 1px 3px rgba(30,40,55,.12)}.operation-state{display:flex;align-items:center;justify-content:center;gap:.55rem;min-height:260px;color:#6f7b8c;font-size:.75rem}.operation-state i{font-size:1.6rem}.operation-state strong{display:block}.operation-state.error{color:#b43341}.group-list{padding:.6rem}.group-row{display:grid;grid-template-columns:34px minmax(0,1fr) 80px 80px 24px;align-items:center;gap:.65rem;width:100%;padding:.72rem;border:0;border-bottom:1px solid #edf0f4;background:#fff;color:#354153;text-align:left}.group-row:hover{background:#f8fafb}.group-row:disabled{cursor:wait}.group-row.is-opening{background:#f4f7fb}.alert-icon{display:grid;place-items:center;width:32px;height:32px;border-radius:50%;background:#fff0f1;color:#c23c4a}.alert-icon .spinner-border{width:14px;height:14px;border-width:2px}.group-row strong,.group-row small{display:block}.group-row small{color:#7a8596;font-size:.61rem}.intervention-table{font-size:.7rem}.intervention-table th{background:#f7f9fb;color:#546174;white-space:nowrap}.intervention-table td,.intervention-table th{padding:.55rem .65rem;border-color:#edf0f4;vertical-align:middle}.intervention-table td strong,.intervention-table td small{display:block}.intervention-table td small{color:#8a94a3;font-size:.6rem}.status-pill{padding:.2rem .38rem;background:#edf2fa;color:#405189;font-size:.61rem}.overdue{color:#c13c4a;font-weight:650}.icon-btn{display:grid;place-items:center;width:32px;height:32px;border:1px solid #d9e0e8;border-radius:6px;background:#fff;color:#405189}.attendance-modal-layer{position:fixed;z-index:1085;inset:0;display:grid;place-items:center;contain:layout paint;padding:1rem;background:rgba(25,34,48,.58);opacity:1;transition:none}.modal-surface{position:relative;z-index:1;width:min(900px,100%);max-height:calc(100vh - 2rem);overflow:auto;overscroll-behavior:contain;border:1px solid #e1e6ed;border-radius:8px;background:#fff;box-shadow:0 24px 64px rgba(18,27,40,.28);opacity:1;transition:none}.alert-list{padding:.65rem;background:#fff}.alert-row{display:grid;grid-template-columns:28px minmax(0,1fr) auto;align-items:center;gap:.6rem;padding:.7rem;border-left:3px solid #d59b26;border-bottom:1px solid #edf0f4;background:#fff}.alert-row.severity-critical{border-left-color:#c13c4a}.alert-row>i{color:#c13c4a;font-size:1.2rem}.alert-row strong,.alert-row span,.alert-row small{display:block}.alert-row span{margin-top:.12rem;color:#465366;font-size:.7rem}.alert-row small{margin-top:.16rem;color:#7a8596;font-size:.61rem}.row-actions{display:flex;gap:.3rem}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:.7rem;padding:1rem;background:#fff}.form-grid label{color:#526071;font-size:.68rem;font-weight:650}.form-grid .full{grid-column:1/-1}.form-grid .form-control{margin-top:.25rem;font-size:.74rem}.form-surface>footer{display:flex;justify-content:flex-end;gap:.4rem;padding:.8rem 1rem;border-top:1px solid #e7ebf0;background:#fff}.form-surface .btn{display:inline-flex;align-items:center;gap:.35rem}@media(max-width:700px){.operations-panel>header{flex-direction:column}.segmented{width:100%}.segmented button{flex:1;justify-content:center}.group-row{grid-template-columns:34px minmax(0,1fr) 65px 22px}.group-row>span:nth-child(4){display:none}.attendance-modal-layer{align-items:end;padding:.5rem}.modal-surface{max-height:calc(100dvh - 1rem)}.alert-row{grid-template-columns:25px 1fr}.row-actions{grid-column:1/-1;justify-content:flex-end}.form-grid{grid-template-columns:1fr}.form-grid .full{grid-column:auto}}
</style>
