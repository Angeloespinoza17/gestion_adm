<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import InfirmaryDocumentPanel from "../../components/infirmary/document-panel.vue";
import InfirmaryHelpButton from "../../components/infirmary/help-button.vue";
import InfirmaryStatusBadge from "../../components/infirmary/status-badge.vue";
import InfirmaryStudentContextCard from "../../components/infirmary/student-context-card.vue";
import InfirmaryStudentSearch from "../../components/infirmary/student-search.vue";
import {
  confirmInfirmaryAction,
  confirmInfirmaryCancel,
  formatInfirmaryDateTime,
  formatInfirmaryError,
  humanizeInfirmaryStatus,
  normalizeOptions,
  showInfirmaryError,
  showInfirmarySuccess,
  showInfirmaryWarning,
  toInputDateTime,
} from "../../components/infirmary/module-utils";

export default {
  components: {
    Layout,
    LoadingState,
    InfirmaryDocumentPanel,
    InfirmaryHelpButton,
    InfirmaryStatusBadge,
    InfirmaryStudentContextCard,
    InfirmaryStudentSearch,
  },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      selectedStudentContext: null,
      catalogs: {
        courses: [],
        dependencies: [],
        staff: [],
        users: [],
        medications: [],
        attention_categories: [],
        priority_options: [],
        status_options: [],
        companion_options: [],
        treatment_type_options: [],
        referral_options: [],
        call_status_options: [],
        follow_up_status_options: [],
        capabilities: {},
      },
      filters: {
        search: "",
        student_profile_id: null,
        attention_category: null,
        status: null,
        priority: null,
        course_section_id: null,
        from: "",
        to: "",
      },
      attentions: [],
      pagination: { current_page: 1, total: 0, per_page: 12 },
      selectedAttention: null,
      showModal: false,
      form: this.emptyForm(),
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
    canCreate() {
      return Boolean(this.catalogs.capabilities?.can_create_attention);
    },
    canEdit() {
      return Boolean(this.catalogs.capabilities?.can_edit_attention);
    },
    canDelete() {
      return Boolean(this.catalogs.capabilities?.can_delete_attention);
    },
    canManageAccidents() {
      return Boolean(this.catalogs.capabilities?.can_manage_accidents);
    },
    categoryOptions() {
      return normalizeOptions(this.catalogs.attention_categories, true);
    },
    priorityOptions() {
      return normalizeOptions(this.catalogs.priority_options, true);
    },
    statusOptions() {
      return normalizeOptions(this.catalogs.status_options, true);
    },
    courseOptions() {
      return normalizeOptions(this.catalogs.courses, true);
    },
    companionOptions() {
      return normalizeOptions(this.catalogs.companion_options);
    },
    treatmentTypeOptions() {
      return normalizeOptions(this.catalogs.treatment_type_options);
    },
    referralOptions() {
      return normalizeOptions(this.catalogs.referral_options);
    },
    callStatusOptions() {
      return normalizeOptions(this.catalogs.call_status_options);
    },
    followUpStatusOptions() {
      return normalizeOptions(this.catalogs.follow_up_status_options);
    },
    medicationOptions() {
      return [{ value: null, text: "Sin medicamento" }].concat(
        (this.catalogs.medications || []).map((item) => ({
          value: item.id,
          text: item.commercial_name || item.name,
        }))
      );
    },
    staffOptions() {
      return [{ value: null, text: "Sin funcionario" }].concat(
        (this.catalogs.staff || []).map((item) => ({
          value: item.id,
          text: item.full_name,
        }))
      );
    },
    userOptions() {
      return [{ value: null, text: "Automático" }].concat(
        (this.catalogs.users || []).map((item) => ({
          value: item.id,
          text: item.name,
        }))
      );
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadAttentions();
  },
  methods: {
    formatInfirmaryDateTime,
    humanizeInfirmaryStatus,
    normalizeOptions,
    emptyTreatment() {
      return {
        treatment_types: [],
        treatment_other: "",
        medication_id: null,
        medication_quantity: 1,
        blood_pressure: "",
        pulse: null,
        respiratory_rate: null,
        temperature: null,
        oxygen_saturation: null,
        weight: null,
        height: null,
        vital_signs_notes: "",
        emotional_support_required: false,
        emotional_comment: "",
        emotional_support_type: "",
        emotional_duration_minutes: null,
        emotional_professional_id: null,
        other_treatments: "",
        notes: "",
      };
    },
    emptyReferral() {
      return {
        referral_type: "regresa_a_sala",
        referred_at: toInputDateTime(new Date().toISOString()),
        responsible_user_id: null,
        responsible_name: "",
        reason: "",
        observations: "",
        result: "",
      };
    },
    emptyCall() {
      return {
        called_at: toInputDateTime(new Date().toISOString()),
        person_contacted: "",
        relationship: "",
        phone_number: "",
        call_status: "pendiente",
        reason: "",
        conversation_summary: "",
        commitments: "",
        estimated_arrival_at: "",
        duration_minutes: 5,
        called_by_user_id: null,
      };
    },
    emptyFollowUp() {
      return {
        followed_at: toInputDateTime(new Date().toISOString()),
        responsible_user_id: null,
        comment: "",
        status: "pendiente",
        next_review_at: "",
      };
    },
    emptyForm() {
      return {
        id: null,
        student_profile_id: null,
        student_label: "",
        attention_category: "malestar_general",
        attended_at: toInputDateTime(new Date().toISOString()),
        referred_by_staff_id: null,
        dependency_id: null,
        accompanied_by_type: "sin_acompanante",
        accompanied_by_name: "",
        consultation_reason: "",
        initial_description: "",
        observations: "",
        attention_duration_minutes: 15,
        priority: "media",
        status: "abierta",
        treatments: [this.emptyTreatment()],
        referrals: [],
        calls: [],
        follow_ups: [],
      };
    },
    async loadCatalogs() {
      const response = await axios.get("/api/infirmary/catalogs");
      this.catalogs = response.data;
    },
    async loadAttentions(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/infirmary/attentions", {
          params: {
            page,
            ...this.filters,
          },
        });
        this.attentions = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page || 1,
          total: response.data.total || 0,
          per_page: response.data.per_page || 12,
        };

        if (this.attentions[0]) {
          await this.openAttention(this.attentions[0]);
        } else {
          this.selectedAttention = null;
          this.selectedStudentContext = null;
        }
      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudieron cargar las atenciones.");
      } finally {
        this.loading = false;
      }
    },
    async openAttention(attention) {
      try {
        const response = await axios.get(`/api/infirmary/attentions/${attention.id}`);
        this.selectedAttention = response.data.data;
        this.selectedStudentContext = response.data.student_context;
      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudo cargar la atención.");
      }
    },
    selectStudent(student) {
      this.filters.student_profile_id = student.id;
      this.loadAttentions(1);
    },
    selectFormStudent(student) {
      this.form.student_profile_id = student.id;
      this.form.student_label = student.full_name;
    },
    resetFilters() {
      this.filters = {
        search: "",
        student_profile_id: null,
        attention_category: null,
        status: null,
        priority: null,
        course_section_id: null,
        from: "",
        to: "",
      };
      this.loadAttentions(1);
    },
    openCreate() {
      this.form = this.emptyForm();
      this.showModal = true;
    },
    openEdit(attention) {
      const treatmentSource = attention.treatments?.length ? attention.treatments : [this.emptyTreatment()];

      this.form = {
        id: attention.id,
        student_profile_id: attention.student_profile_id,
        student_label: attention.student ? `${attention.student.first_name} ${attention.student.last_name}` : attention.student_full_name_snapshot,
        attention_category: attention.attention_category || "malestar_general",
        attended_at: toInputDateTime(attention.attended_at),
        referred_by_staff_id: attention.referred_by_staff_id || null,
        dependency_id: attention.dependency_id || null,
        accompanied_by_type: attention.accompanied_by_type || "sin_acompanante",
        accompanied_by_name: attention.accompanied_by_name || "",
        consultation_reason: attention.consultation_reason || "",
        initial_description: attention.initial_description || "",
        observations: attention.observations || "",
        attention_duration_minutes: attention.attention_duration_minutes || 15,
        priority: attention.priority || "media",
        status: attention.status || "abierta",
        treatments: treatmentSource.map((item) => ({
          treatment_types: item.treatment_types || [],
          treatment_other: item.treatment_other || "",
          medication_id: item.medication_id || null,
          medication_quantity: item.medication_quantity || 1,
          blood_pressure: item.blood_pressure || "",
          pulse: item.pulse || null,
          respiratory_rate: item.respiratory_rate || null,
          temperature: item.temperature || null,
          oxygen_saturation: item.oxygen_saturation || null,
          weight: item.weight || null,
          height: item.height || null,
          vital_signs_notes: item.vital_signs_notes || "",
          emotional_support_required: Boolean(item.emotional_support_required),
          emotional_comment: item.emotional_comment || "",
          emotional_support_type: item.emotional_support_type || "",
          emotional_duration_minutes: item.emotional_duration_minutes || null,
          emotional_professional_id: item.emotional_professional_id || null,
          other_treatments: item.other_treatments || "",
          notes: item.notes || "",
        })),
        referrals: (attention.referrals || []).map((item) => ({
          referral_type: item.referral_type,
          referred_at: toInputDateTime(item.referred_at),
          responsible_user_id: item.responsible_user_id || null,
          responsible_name: item.responsible_name || "",
          reason: item.reason || "",
          observations: item.observations || "",
          result: item.result || "",
        })),
        calls: (attention.calls || []).map((item) => ({
          called_at: toInputDateTime(item.called_at),
          person_contacted: item.person_contacted || "",
          relationship: item.relationship || "",
          phone_number: item.phone_number || "",
          call_status: item.call_status || "pendiente",
          reason: item.reason || "",
          conversation_summary: item.conversation_summary || "",
          commitments: item.commitments || "",
          estimated_arrival_at: toInputDateTime(item.estimated_arrival_at),
          duration_minutes: item.duration_minutes || 5,
          called_by_user_id: item.called_by_user_id || null,
        })),
        follow_ups: (attention.follow_ups || []).map((item) => ({
          followed_at: toInputDateTime(item.followed_at),
          responsible_user_id: item.responsible_user_id || null,
          comment: item.comment || "",
          status: item.status || "pendiente",
          next_review_at: toInputDateTime(item.next_review_at),
        })),
      };
      this.showModal = true;
    },
    async cancelModal() {
      const result = await confirmInfirmaryCancel("la ficha de atención");
      if (result.isConfirmed) this.showModal = false;
    },
    addTreatment() {
      this.form.treatments.push(this.emptyTreatment());
    },
    removeTreatment(index) {
      this.form.treatments.splice(index, 1);
      if (!this.form.treatments.length) this.addTreatment();
    },
    addReferral() {
      this.form.referrals.push(this.emptyReferral());
    },
    removeReferral(index) {
      this.form.referrals.splice(index, 1);
    },
    addCall() {
      this.form.calls.push(this.emptyCall());
    },
    removeCall(index) {
      this.form.calls.splice(index, 1);
    },
    addFollowUp() {
      this.form.follow_ups.push(this.emptyFollowUp());
    },
    removeFollowUp(index) {
      this.form.follow_ups.splice(index, 1);
    },
    async save() {
      if (!this.form.student_profile_id) {
        await showInfirmaryWarning("Debes seleccionar una estudiante.");
        return;
      }

      this.saving = true;
      try {
        const payload = {
          ...this.form,
          accompanied_by_name: this.form.accompanied_by_type === "otro" ? this.form.accompanied_by_name : (this.form.accompanied_by_name || null),
          treatments: this.form.treatments.map((item) => ({
            ...item,
            treatment_other: item.treatment_types.includes("otro") ? item.treatment_other || null : null,
            medication_id: item.treatment_types.includes("administracion_medicamento") ? item.medication_id || null : null,
            medication_quantity: item.treatment_types.includes("administracion_medicamento") ? item.medication_quantity || null : null,
            emotional_comment: item.emotional_support_required ? item.emotional_comment || null : null,
            emotional_support_type: item.emotional_support_required ? item.emotional_support_type || null : null,
            emotional_duration_minutes: item.emotional_support_required ? item.emotional_duration_minutes || null : null,
            emotional_professional_id: item.emotional_support_required ? item.emotional_professional_id || null : null,
          })),
          referrals: this.form.referrals.map((item) => ({
            ...item,
            responsible_user_id: item.responsible_user_id || null,
            responsible_name: item.responsible_name || null,
          })),
          calls: this.form.calls.map((item) => ({
            ...item,
            estimated_arrival_at: item.estimated_arrival_at || null,
            called_by_user_id: item.called_by_user_id || null,
          })),
          follow_ups: this.form.follow_ups.map((item) => ({
            ...item,
            responsible_user_id: item.responsible_user_id || null,
            next_review_at: item.next_review_at || null,
          })),
        };

        if (this.isEditing) {
          await axios.put(`/api/infirmary/attentions/${this.form.id}`, payload);
        } else {
          await axios.post("/api/infirmary/attentions", payload);
        }

        this.showModal = false;
        await this.loadAttentions(this.pagination.current_page || 1);
        await showInfirmarySuccess(this.isEditing ? "Atención actualizada correctamente." : "Atención registrada correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo guardar la atención."));
      } finally {
        this.saving = false;
      }
    },
    async remove(attention) {
      const confirmation = await confirmInfirmaryAction({
        title: "Eliminar atención",
        text: "Se eliminará la atención y sus registros relacionados.",
        confirmButtonText: "Eliminar",
      });

      if (!confirmation.isConfirmed) return;

      try {
        await axios.delete(`/api/infirmary/attentions/${attention.id}`);
        await this.loadAttentions(this.pagination.current_page || 1);
        await showInfirmarySuccess("Atención eliminada correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo eliminar la atención."));
      }
    },
    async finalize(attention) {
      const result = await Swal.fire({
        title: "Finalizar atención",
        html: `
          <div class="text-start">
            <label class="form-label">Tiempo de atención (minutos)</label>
            <input id="swal-duration" type="number" class="form-control mb-3" min="1" max="480" value="${attention.attention_duration_minutes || 15}" />
            <label class="form-label">Observaciones finales</label>
            <textarea id="swal-observations" class="form-control" rows="4">${attention.observations || ""}</textarea>
          </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: "Finalizar",
        cancelButtonText: "Cancelar",
        preConfirm: () => ({
          attention_duration_minutes: Number(document.getElementById("swal-duration")?.value || 15),
          observations: document.getElementById("swal-observations")?.value || "",
        }),
      });

      if (!result.isConfirmed) return;

      try {
        await axios.post(`/api/infirmary/attentions/${attention.id}/finalize`, result.value);
        await this.loadAttentions(this.pagination.current_page || 1);
        await showInfirmarySuccess("Atención finalizada correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo finalizar la atención."));
      }
    },
    openHistory(student) {
      this.$router.push({ path: "/infirmary/history", query: { student_id: student.id } });
    },
    registerAccident(attention) {
      this.$router.push({
        path: "/infirmary/accidents",
        query: {
          attention_id: attention.id,
          student_id: attention.student_profile_id,
        },
      });
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Ficha de Atención de Enfermería</h4>
        <div class="text-muted">
          Registro clínico de atenciones, tratamientos, signos vitales, derivaciones, llamados, seguimiento y adjuntos.
        </div>
      </div>
      <div class="d-flex gap-2">
        <InfirmaryHelpButton
          title="Ayuda: ficha de atención"
          text="En esta pantalla se registran todas las atenciones realizadas por enfermería, incluyendo tratamientos, medicamentos administrados, derivaciones y seguimiento del estudiante."
        />
        <BButton v-if="canCreate" variant="primary" @click="openCreate">Nueva atención</BButton>
      </div>
    </div>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-xl-8">
          <InfirmaryStudentSearch @selected="selectStudent" />
        </div>
        <div class="col-xl-4 d-flex align-items-center">
          <div class="text-muted small">El buscador permite abrir rápidamente la bandeja de atenciones de una estudiante específica.</div>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

    <div class="row g-3">
      <div class="col-xl-8">
        <BCard class="mb-3">
          <template #header>
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
              <div class="fw-semibold">Filtros operativos</div>
              <InfirmaryHelpButton
                title="Ayuda: filtros de atenciones"
                text="Filtra por estudiante, categoría, curso, estado, prioridad o rango de fechas para revisar la operación diaria de enfermería."
              />
            </div>
          </template>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Buscar</label>
              <BFormInput v-model="filters.search" placeholder="Estudiante, RUT o motivo" @keyup.enter="loadAttentions(1)" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Categoría</label>
              <BFormSelect v-model="filters.attention_category" :options="categoryOptions" />
            </div>
            <div class="col-md-2">
              <label class="form-label">Estado</label>
              <BFormSelect v-model="filters.status" :options="statusOptions" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Prioridad</label>
              <BFormSelect v-model="filters.priority" :options="priorityOptions" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Curso</label>
              <BFormSelect v-model="filters.course_section_id" :options="courseOptions" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Desde</label>
              <BFormInput v-model="filters.from" type="date" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Hasta</label>
              <BFormInput v-model="filters.to" type="date" />
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <BButton variant="primary" class="w-100" @click="loadAttentions(1)">Filtrar</BButton>
            </div>
            <div class="col-12">
              <BButton variant="outline-secondary" @click="resetFilters">Limpiar</BButton>
            </div>
          </div>
        </BCard>

        <BCard>
          <template #header>
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
              <div class="fw-semibold">Bandeja de atenciones</div>
              <InfirmaryHelpButton
                title="Ayuda: bandeja de atenciones"
                text="Aquí se revisa el listado completo de atenciones con acceso directo a edición, finalización, accidente asociado y trazabilidad clínica."
              />
            </div>
          </template>

          <LoadingState v-if="loading" message="Cargando atenciones..." compact />

          <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Estudiante</th>
                  <th>Motivo</th>
                  <th>Prioridad</th>
                  <th>Estado</th>
                  <th class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="attention in attentions"
                  :key="attention.id"
                  :class="{ 'table-active': selectedAttention?.id === attention.id }"
                  role="button"
                  @click="openAttention(attention)"
                >
                  <td>{{ formatInfirmaryDateTime(attention.attended_at) }}</td>
                  <td>{{ attention.student_full_name_snapshot || `${attention.student?.first_name || ''} ${attention.student?.last_name || ''}` }}</td>
                  <td>{{ attention.consultation_reason }}</td>
                  <td><InfirmaryStatusBadge :status="attention.priority" /></td>
                  <td><InfirmaryStatusBadge :status="attention.status" /></td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <BButton v-if="canEdit" variant="outline-primary" @click.stop="openEdit(attention)">Editar</BButton>
                      <BButton v-if="canEdit && attention.status !== 'finalizada'" variant="outline-success" @click.stop="finalize(attention)">Finalizar</BButton>
                      <BButton v-if="canManageAccidents" variant="outline-warning" @click.stop="registerAccident(attention)">Accidente</BButton>
                      <BButton v-if="canDelete" variant="outline-danger" @click.stop="remove(attention)">Eliminar</BButton>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="d-flex justify-content-end mt-3">
            <BPagination
              v-model="pagination.current_page"
              :total-rows="pagination.total"
              :per-page="pagination.per_page"
              @update:model-value="loadAttentions"
            />
          </div>
        </BCard>
      </div>

      <div class="col-xl-4">
        <InfirmaryStudentContextCard
          :student="selectedStudentContext"
          class="mb-3"
          show-history-action
          @open-history="openHistory"
        />

        <BCard class="mb-3">
          <template #header>
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
              <div class="fw-semibold">Detalle de la atención</div>
              <InfirmaryHelpButton
                title="Ayuda: detalle de la atención"
                text="Aquí se observa la trazabilidad clínica completa de la atención, con tratamientos, derivaciones, llamados, seguimientos y accidentes relacionados."
              />
            </div>
          </template>

          <div v-if="!selectedAttention" class="text-muted">Selecciona una atención para revisar su detalle.</div>
          <div v-else>
            <div class="fw-semibold fs-5">{{ selectedAttention.consultation_reason }}</div>
            <div class="text-muted small mb-3">{{ formatInfirmaryDateTime(selectedAttention.attended_at) }}</div>
            <div class="mb-2"><span class="text-muted">Categoría:</span> {{ humanizeInfirmaryStatus(selectedAttention.attention_category) }}</div>
            <div class="mb-2"><span class="text-muted">Prioridad:</span> <InfirmaryStatusBadge :status="selectedAttention.priority" /></div>
            <div class="mb-2"><span class="text-muted">Estado:</span> <InfirmaryStatusBadge :status="selectedAttention.status" /></div>
            <div class="mb-2"><span class="text-muted">Dependencia:</span> {{ selectedAttention.dependency?.name || "-" }}</div>
            <div class="mb-2"><span class="text-muted">Deriva:</span> {{ selectedAttention.referred_by?.full_name || "-" }}</div>
            <div class="mb-2"><span class="text-muted">Acompaña:</span> {{ humanizeInfirmaryStatus(selectedAttention.accompanied_by_type) }} {{ selectedAttention.accompanied_by_name ? `· ${selectedAttention.accompanied_by_name}` : "" }}</div>
            <div class="mb-2"><span class="text-muted">Descripción inicial:</span> {{ selectedAttention.initial_description || "-" }}</div>
            <div><span class="text-muted">Observaciones:</span> {{ selectedAttention.observations || "Sin observaciones." }}</div>
          </div>
        </BCard>

        <BCard class="mb-3" v-if="selectedAttention">
          <h5 class="mb-3">Tratamientos</h5>
          <div v-if="!(selectedAttention.treatments || []).length" class="text-muted">Sin tratamientos registrados.</div>
          <ul v-else class="list-group list-group-flush">
            <li v-for="item in selectedAttention.treatments" :key="item.id" class="list-group-item px-0">
              <div>{{ (item.treatment_types || []).map(humanizeInfirmaryStatus).join(", ") }}</div>
              <div class="small text-muted">
                {{ item.medication?.commercial_name || item.medication?.name || "Sin medicamento" }}
                <span v-if="item.temperature"> · Temp {{ item.temperature }}°</span>
                <span v-if="item.bmi"> · IMC {{ item.bmi }}</span>
              </div>
            </li>
          </ul>
        </BCard>

        <BCard class="mb-3" v-if="selectedAttention">
          <h5 class="mb-3">Derivaciones, llamados y seguimientos</h5>
          <div class="mb-3">
            <div class="fw-semibold mb-2">Derivaciones</div>
            <div v-if="!(selectedAttention.referrals || []).length" class="text-muted small">Sin derivaciones.</div>
            <ul v-else class="list-group list-group-flush">
              <li v-for="item in selectedAttention.referrals" :key="item.id" class="list-group-item px-0">
                <div>{{ humanizeInfirmaryStatus(item.referral_type) }}</div>
                <div class="small text-muted">{{ item.reason || "-" }}</div>
              </li>
            </ul>
          </div>
          <div class="mb-3">
            <div class="fw-semibold mb-2">Llamados</div>
            <div v-if="!(selectedAttention.calls || []).length" class="text-muted small">Sin llamados.</div>
            <ul v-else class="list-group list-group-flush">
              <li v-for="item in selectedAttention.calls" :key="item.id" class="list-group-item px-0">
                <div class="d-flex justify-content-between gap-2">
                  <div>{{ item.person_contacted }}</div>
                  <InfirmaryStatusBadge :status="item.call_status" />
                </div>
                <div class="small text-muted">{{ formatInfirmaryDateTime(item.called_at) }}</div>
              </li>
            </ul>
          </div>
          <div>
            <div class="fw-semibold mb-2">Seguimientos</div>
            <div v-if="!(selectedAttention.follow_ups || []).length" class="text-muted small">Sin seguimientos.</div>
            <ul v-else class="list-group list-group-flush">
              <li v-for="item in selectedAttention.follow_ups" :key="item.id" class="list-group-item px-0">
                <div>{{ item.comment }}</div>
                <div class="small text-muted"><InfirmaryStatusBadge :status="item.status" /> · {{ formatInfirmaryDateTime(item.followed_at) }}</div>
              </li>
            </ul>
          </div>
        </BCard>

        <InfirmaryDocumentPanel
          v-if="selectedAttention"
          :documents="selectedAttention.documents || []"
          :upload-url="`/api/infirmary/attentions/${selectedAttention.id}/documents`"
          :student-id="selectedAttention.student_profile_id"
          :categories="catalogs.document_categories || []"
          title="Adjuntos de la atención"
          help-text="Adjunta certificados, recetas, informes, fotografías u otros documentos de respaldo clínico."
          @refresh="openAttention(selectedAttention)"
        />
      </div>
    </div>

    <BModal v-model="showModal" :title="isEditing ? 'Editar atención de enfermería' : 'Nueva atención de enfermería'" size="xl" hide-footer scrollable>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Buscar estudiante</label>
          <InfirmaryStudentSearch button-label="Seleccionar" @selected="selectFormStudent" />
          <div v-if="form.student_label" class="small text-muted mt-2">Seleccionada: {{ form.student_label }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Fecha y hora</label>
          <BFormInput v-model="form.attended_at" type="datetime-local" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Categoría</label>
          <BFormSelect v-model="form.attention_category" :options="normalizeOptions(catalogs.attention_categories)" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Inspector que deriva</label>
          <BFormSelect v-model="form.referred_by_staff_id" :options="staffOptions" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Dependencia</label>
          <BFormSelect v-model="form.dependency_id" :options="normalizeOptions(catalogs.dependencies, true)" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Quién acompaña</label>
          <BFormSelect v-model="form.accompanied_by_type" :options="companionOptions" />
        </div>
        <div class="col-md-4" v-if="form.accompanied_by_type === 'otro'">
          <label class="form-label">Detalle acompañante</label>
          <BFormInput v-model="form.accompanied_by_name" />
        </div>
        <div class="col-md-8">
          <label class="form-label">Motivo de consulta</label>
          <BFormInput v-model="form.consultation_reason" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Prioridad</label>
          <BFormSelect v-model="form.priority" :options="normalizeOptions(catalogs.priority_options)" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="form.status" :options="normalizeOptions(catalogs.status_options)" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Descripción inicial</label>
          <BFormTextarea v-model="form.initial_description" rows="3" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Observaciones</label>
          <BFormTextarea v-model="form.observations" rows="3" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Tiempo (min)</label>
          <BFormInput v-model="form.attention_duration_minutes" type="number" min="1" max="480" />
        </div>

        <div class="col-12">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-semibold">Tratamientos y signos vitales</div>
            <BButton variant="outline-primary" size="sm" @click="addTreatment">Agregar tratamiento</BButton>
          </div>
          <div v-for="(treatment, index) in form.treatments" :key="`treatment-${index}`" class="border rounded p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="fw-semibold">Tratamiento {{ index + 1 }}</div>
              <BButton variant="outline-danger" size="sm" @click="removeTreatment(index)">Quitar</BButton>
            </div>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Procedimientos</label>
                <div class="row g-2">
                  <div v-for="option in treatmentTypeOptions" :key="`type-${index}-${option.value}`" class="col-md-4">
                    <div class="form-check">
                      <input
                        :id="`type-${index}-${option.value}`"
                        v-model="treatment.treatment_types"
                        class="form-check-input"
                        type="checkbox"
                        :value="option.value"
                      />
                      <label class="form-check-label" :for="`type-${index}-${option.value}`">{{ option.text }}</label>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-6" v-if="treatment.treatment_types.includes('otro')">
                <label class="form-label">Detalle de otro tratamiento</label>
                <BFormInput v-model="treatment.treatment_other" />
              </div>
              <div class="col-md-6" v-if="treatment.treatment_types.includes('administracion_medicamento')">
                <label class="form-label">Medicamento</label>
                <BFormSelect v-model="treatment.medication_id" :options="medicationOptions" />
              </div>
              <div class="col-md-3" v-if="treatment.treatment_types.includes('administracion_medicamento')">
                <label class="form-label">Cantidad administrada</label>
                <BFormInput v-model="treatment.medication_quantity" type="number" min="0.01" step="0.01" />
              </div>
              <div class="col-md-3">
                <label class="form-label">Presión arterial</label>
                <BFormInput v-model="treatment.blood_pressure" />
              </div>
              <div class="col-md-2">
                <label class="form-label">Pulso</label>
                <BFormInput v-model="treatment.pulse" type="number" min="1" />
              </div>
              <div class="col-md-2">
                <label class="form-label">Frecuencia resp.</label>
                <BFormInput v-model="treatment.respiratory_rate" type="number" min="1" />
              </div>
              <div class="col-md-2">
                <label class="form-label">Temperatura</label>
                <BFormInput v-model="treatment.temperature" type="number" min="20" max="45" step="0.1" />
              </div>
              <div class="col-md-3">
                <label class="form-label">Saturación</label>
                <BFormInput v-model="treatment.oxygen_saturation" type="number" min="1" max="100" />
              </div>
              <div class="col-md-3">
                <label class="form-label">Peso (kg)</label>
                <BFormInput v-model="treatment.weight" type="number" min="1" step="0.01" />
              </div>
              <div class="col-md-3">
                <label class="form-label">Estatura (m o cm)</label>
                <BFormInput v-model="treatment.height" type="number" min="0.3" step="0.01" />
              </div>
              <div class="col-md-3">
                <label class="form-label">Observaciones signos vitales</label>
                <BFormInput v-model="treatment.vital_signs_notes" />
              </div>
              <div class="col-md-3 d-flex align-items-end">
                <div class="form-check">
                  <input :id="`emotional-${index}`" v-model="treatment.emotional_support_required" class="form-check-input" type="checkbox" />
                  <label class="form-check-label" :for="`emotional-${index}`">Requirió contención emocional</label>
                </div>
              </div>
              <template v-if="treatment.emotional_support_required">
                <div class="col-md-3">
                  <label class="form-label">Tipo de contención</label>
                  <BFormInput v-model="treatment.emotional_support_type" />
                </div>
                <div class="col-md-3">
                  <label class="form-label">Tiempo aprox. (min)</label>
                  <BFormInput v-model="treatment.emotional_duration_minutes" type="number" min="1" />
                </div>
                <div class="col-md-3">
                  <label class="form-label">Profesional interviniente</label>
                  <BFormSelect v-model="treatment.emotional_professional_id" :options="staffOptions" />
                </div>
                <div class="col-md-12">
                  <label class="form-label">Comentario</label>
                  <BFormTextarea v-model="treatment.emotional_comment" rows="2" />
                </div>
              </template>
              <div class="col-md-6">
                <label class="form-label">Otros tratamientos</label>
                <BFormTextarea v-model="treatment.other_treatments" rows="2" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Notas clínicas</label>
                <BFormTextarea v-model="treatment.notes" rows="2" />
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-semibold">Derivaciones</div>
            <BButton variant="outline-primary" size="sm" @click="addReferral">Agregar</BButton>
          </div>
          <div v-for="(referral, index) in form.referrals" :key="`ref-${index}`" class="border rounded p-2 mb-2">
            <div class="row g-2">
              <div class="col-12">
                <label class="form-label">Destino</label>
                <BFormSelect v-model="referral.referral_type" :options="normalizeOptions(catalogs.referral_options)" />
              </div>
              <div class="col-12">
                <label class="form-label">Hora</label>
                <BFormInput v-model="referral.referred_at" type="datetime-local" />
              </div>
              <div class="col-12">
                <label class="form-label">Responsable</label>
                <BFormSelect v-model="referral.responsible_user_id" :options="userOptions" />
              </div>
              <div class="col-12">
                <label class="form-label">Motivo</label>
                <BFormTextarea v-model="referral.reason" rows="2" />
              </div>
              <div class="col-12">
                <label class="form-label">Resultado</label>
                <BFormInput v-model="referral.result" />
              </div>
              <div class="col-12 text-end">
                <BButton variant="outline-danger" size="sm" @click="removeReferral(index)">Quitar</BButton>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-semibold">Llamados a apoderados</div>
            <BButton variant="outline-primary" size="sm" @click="addCall">Agregar</BButton>
          </div>
          <div v-for="(call, index) in form.calls" :key="`call-${index}`" class="border rounded p-2 mb-2">
            <div class="row g-2">
              <div class="col-12">
                <label class="form-label">Fecha y hora</label>
                <BFormInput v-model="call.called_at" type="datetime-local" />
              </div>
              <div class="col-12">
                <label class="form-label">Persona contactada</label>
                <BFormInput v-model="call.person_contacted" />
              </div>
              <div class="col-12">
                <label class="form-label">Relación</label>
                <BFormInput v-model="call.relationship" />
              </div>
              <div class="col-12">
                <label class="form-label">Número</label>
                <BFormInput v-model="call.phone_number" />
              </div>
              <div class="col-12">
                <label class="form-label">Resultado</label>
                <BFormSelect v-model="call.call_status" :options="normalizeOptions(catalogs.call_status_options)" />
              </div>
              <div class="col-12">
                <label class="form-label">Resumen</label>
                <BFormTextarea v-model="call.conversation_summary" rows="2" />
              </div>
              <div class="col-12 text-end">
                <BButton variant="outline-danger" size="sm" @click="removeCall(index)">Quitar</BButton>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-semibold">Seguimiento</div>
            <BButton variant="outline-primary" size="sm" @click="addFollowUp">Agregar</BButton>
          </div>
          <div v-for="(followUp, index) in form.follow_ups" :key="`fu-${index}`" class="border rounded p-2 mb-2">
            <div class="row g-2">
              <div class="col-12">
                <label class="form-label">Fecha</label>
                <BFormInput v-model="followUp.followed_at" type="datetime-local" />
              </div>
              <div class="col-12">
                <label class="form-label">Responsable</label>
                <BFormSelect v-model="followUp.responsible_user_id" :options="userOptions" />
              </div>
              <div class="col-12">
                <label class="form-label">Comentario</label>
                <BFormTextarea v-model="followUp.comment" rows="2" />
              </div>
              <div class="col-12">
                <label class="form-label">Estado</label>
                <BFormSelect v-model="followUp.status" :options="normalizeOptions(catalogs.follow_up_status_options)" />
              </div>
              <div class="col-12">
                <label class="form-label">Próxima revisión</label>
                <BFormInput v-model="followUp.next_review_at" type="datetime-local" />
              </div>
              <div class="col-12 text-end">
                <BButton variant="outline-danger" size="sm" @click="removeFollowUp(index)">Quitar</BButton>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
          <BButton variant="outline-secondary" @click="cancelModal">Cancelar</BButton>
          <BButton variant="primary" :disabled="saving" @click="save">
            {{ saving ? "Guardando..." : isEditing ? "Guardar cambios" : "Registrar atención" }}
          </BButton>
        </div>
      </div>
    </BModal>
  </Layout>
</template>
