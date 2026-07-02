<script>
import axios from "axios";
import LoadingState from "../../ui/loading-state.vue";
import SupportHelpButton from "../help-button.vue";
import SupportStatusBadge from "../status-badge.vue";
import SupportStudentSearch from "../student-search.vue";
import SupportStudentContextCard from "../student-context-card.vue";
import SupportDocumentPanel from "../document-panel.vue";
import {
  confirmSupportAction,
  confirmSupportCancel,
  formatSupportDateTime,
  formatSupportError,
  humanizeSupportStatus,
  normalizeOptions,
  showSupportError,
  showSupportSuccess,
} from "../module-utils";

export default {
  components: {
    LoadingState,
    SupportHelpButton,
    SupportStatusBadge,
    SupportStudentSearch,
    SupportStudentContextCard,
    SupportDocumentPanel,
  },
  props: {
    catalogs: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      attentions: [],
      pagination: { current_page: 1, total: 0, per_page: 12 },
      selectedAttention: null,
      selectedStudentContext: null,
      showModal: false,
      filters: {
        search: "",
        student_profile_id: null,
        status: null,
        course_section_id: null,
        attended_by_user_id: null,
        attention_type_label: null,
        confidentiality_level: null,
        professional_area_name: null,
        from: "",
        to: "",
      },
      form: this.emptyForm(),
    };
  },
  computed: {
    statusOptions() {
      return normalizeOptions(this.catalogs.attention_status_options, true);
    },
    courseOptions() {
      return normalizeOptions(this.catalogs.courses, true);
    },
    professionalOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.professionals || []).map((item) => ({
          value: item.user_id,
          text: item.staff?.full_name || item.user?.name || item.professional_role_name,
        }))
      );
    },
    typeOptions() {
      return [{ value: null, text: "Selecciona un tipo" }].concat(
        (this.catalogs.attention_types || []).map((item) => ({
          value: item.id,
          text: item.name,
        }))
      );
    },
    motiveOptions() {
      return [{ value: null, text: "Selecciona un motivo" }].concat(
        (this.catalogs.motives || []).map((item) => ({
          value: item.id,
          text: item.name,
        }))
      );
    },
    modalityOptions() {
      return normalizeOptions(this.catalogs.modality_options);
    },
    originOptions() {
      return normalizeOptions(this.catalogs.origin_options);
    },
    priorityOptions() {
      return normalizeOptions(this.catalogs.priority_options);
    },
    confidentialityOptions() {
      return normalizeOptions(this.catalogs.confidentiality_options);
    },
    canUploadDocuments() {
      return Boolean(this.selectedAttention && this.catalogs.capabilities?.can_create_attention);
    },
  },
  mounted() {
    this.load();
  },
  watch: {
    "$route.query.attention_id": {
      immediate: true,
      handler(value) {
        if (value) {
          this.openAttention(Number(value));
        }
      },
    },
  },
  methods: {
    formatSupportDateTime,
    humanizeSupportStatus,
    emptyForm() {
      return {
        id: null,
        student_profile_id: null,
        student_label: "",
        attended_at: new Date().toISOString().slice(0, 16),
        attention_type_id: null,
        motive_id: null,
        attention_type_other: "",
        modality: "presencial",
        modality_other: "",
        origin: "observacion_profesional",
        origin_other: "",
        priority_level: "media",
        confidentiality_level: "reservada",
        reason_summary: "",
        description: "",
        professional_observations: "",
        agreements: "",
        recommendations: "",
        next_action: "",
        status: "abierta",
      };
    },
    async load(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/apoyo-profesional/attentions", {
          params: { page, ...this.filters },
        });
        this.attentions = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page || 1,
          total: response.data.total || 0,
          per_page: response.data.per_page || 12,
        };
        if (!this.selectedAttention && this.attentions[0]) {
          await this.openAttention(this.attentions[0].id);
        }
      } catch (error) {
        this.error = formatSupportError(error, "No se pudieron cargar las atenciones.");
      } finally {
        this.loading = false;
      }
    },
    async openAttention(id) {
      try {
        const response = await axios.get(`/api/apoyo-profesional/attentions/${id}`);
        this.selectedAttention = response.data.data;
        this.selectedStudentContext = response.data.student_context;
      } catch (error) {
        this.error = formatSupportError(error, "No se pudo cargar la atención.");
      }
    },
    selectStudent(student) {
      this.filters.student_profile_id = student.id;
      this.load(1);
    },
    selectFormStudent(student) {
      this.form.student_profile_id = student.id;
      this.form.student_label = student.full_name;
    },
    openCreate() {
      this.form = this.emptyForm();
      this.showModal = true;
    },
    openEdit(attention) {
      this.form = {
        id: attention.id,
        student_profile_id: attention.student_profile_id,
        student_label: attention.student_full_name_snapshot,
        attended_at: String(attention.attended_at).replace(" ", "T").slice(0, 16),
        attention_type_id: attention.attention_type_id,
        motive_id: attention.motive_id,
        attention_type_other: attention.attention_type_other || "",
        modality: attention.modality,
        modality_other: attention.modality_other || "",
        origin: attention.origin,
        origin_other: attention.origin_other || "",
        priority_level: attention.priority_level,
        confidentiality_level: attention.confidentiality_level,
        reason_summary: attention.reason_summary,
        description: attention.description || "",
        professional_observations: attention.professional_observations || "",
        agreements: attention.agreements || "",
        recommendations: attention.recommendations || "",
        next_action: attention.next_action || "",
        status: attention.status,
      };
      this.showModal = true;
    },
    async save() {
      const confirmation = await confirmSupportAction({
        title: this.form.id ? "Editar atención" : "Guardar atención",
        text: this.form.id
          ? "Se actualizará la atención profesional seleccionada."
          : "Se registrará una nueva atención profesional.",
        confirmButtonText: this.form.id ? "Guardar cambios" : "Registrar atención",
      });

      if (!confirmation.isConfirmed) return;

      this.saving = true;
      try {
        const payload = { ...this.form };
        let response;
        if (this.form.id) {
          response = await axios.put(`/api/apoyo-profesional/attentions/${this.form.id}`, payload);
        } else {
          response = await axios.post("/api/apoyo-profesional/attentions", payload);
        }
        this.showModal = false;
        await this.load(this.pagination.current_page || 1);
        await this.openAttention(response.data.data.id);
        await showSupportSuccess(this.form.id ? "Atención actualizada correctamente." : "Atención registrada correctamente.");
      } catch (error) {
        await showSupportError(formatSupportError(error, "No se pudo guardar la atención."));
      } finally {
        this.saving = false;
      }
    },
    async closeAttention(attention) {
      const confirmation = await confirmSupportAction({
        title: "Cerrar caso",
        text: "La atención quedará cerrada y ya no figurará como caso activo.",
        confirmButtonText: "Cerrar caso",
      });

      if (!confirmation.isConfirmed) return;

      try {
        await axios.post(`/api/apoyo-profesional/attentions/${attention.id}/close`, {});
        await this.load(this.pagination.current_page || 1);
        await this.openAttention(attention.id);
        await showSupportSuccess("Caso cerrado correctamente.");
      } catch (error) {
        await showSupportError(formatSupportError(error, "No se pudo cerrar el caso."));
      }
    },
    async remove(attention) {
      const confirmation = await confirmSupportAction({
        title: "Eliminar atención",
        text: `Se eliminará la atención de ${attention.student_full_name_snapshot}.`,
        confirmButtonText: "Eliminar",
      });

      if (!confirmation.isConfirmed) return;

      try {
        await axios.delete(`/api/apoyo-profesional/attentions/${attention.id}`);
        this.selectedAttention = null;
        this.selectedStudentContext = null;
        await this.load(1);
        await showSupportSuccess("Atención eliminada correctamente.");
      } catch (error) {
        await showSupportError(formatSupportError(error, "No se pudo eliminar la atención."));
      }
    },
    async cancelForm() {
      const confirmation = await confirmSupportCancel("este formulario");
      if (confirmation.isConfirmed) {
        this.showModal = false;
      }
    },
  },
};
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
      <div class="d-flex gap-2 flex-wrap">
        <BButton variant="primary" @click="openCreate">Nueva atención</BButton>
      </div>
      <SupportHelpButton
        title="Ayuda: ficha de atención profesional"
        text="En esta sección se registran, editan, cierran o eliminan atenciones del equipo de apoyo, con resguardo de confidencialidad y trazabilidad por estudiante."
      />
    </div>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-xl-5">
          <SupportStudentSearch button-label="Filtrar" @selected="selectStudent" />
        </div>
        <div class="col-md-2"><label class="form-label">Estado</label><BFormSelect v-model="filters.status" :options="statusOptions" /></div>
        <div class="col-md-2"><label class="form-label">Curso</label><BFormSelect v-model="filters.course_section_id" :options="courseOptions" /></div>
        <div class="col-md-3"><label class="form-label">Buscar</label><BFormInput v-model="filters.search" placeholder="Motivo o profesional" /></div>
        <div class="col-12 d-flex gap-2">
          <BButton variant="primary" @click="load(1)">Aplicar filtros</BButton>
          <BButton variant="outline-secondary" @click="filters = { search: '', student_profile_id: null, status: null, course_section_id: null, attended_by_user_id: null, attention_type_label: null, confidentiality_level: null, professional_area_name: null, from: '', to: '' }; load(1);">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
    <BCard v-if="loading"><LoadingState message="Cargando atenciones profesionales..." compact /></BCard>

    <div v-else class="row g-3">
      <div class="col-xl-5">
        <BCard class="h-100">
          <template #header><div class="fw-semibold">Listado de atenciones</div></template>
          <div class="d-grid gap-2">
            <button
              v-for="item in attentions"
              :key="item.id"
              type="button"
              class="btn btn-light text-start border"
              @click="openAttention(item.id)"
            >
              <div class="d-flex justify-content-between gap-2 align-items-center">
                <div class="fw-semibold">{{ item.student_full_name_snapshot }}</div>
                <SupportStatusBadge :status="item.status" />
              </div>
              <div class="small">{{ item.reason_summary }}</div>
              <div class="small text-muted">{{ item.professional_role_name }} · {{ formatSupportDateTime(item.attended_at) }}</div>
            </button>
          </div>
        </BCard>
      </div>

      <div class="col-xl-7">
        <div class="d-grid gap-3">
          <SupportStudentContextCard :student="selectedStudentContext" />

          <BCard v-if="selectedAttention">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-semibold">Detalle de la atención</div>
                <div class="d-flex gap-2 flex-wrap">
                  <BButton size="sm" variant="outline-primary" @click="$router.push({ path: '/apoyo-profesional/derivaciones', query: { attention_id: selectedAttention.id } })">Derivar estudiante</BButton>
                  <BButton size="sm" variant="outline-info" @click="$router.push({ path: '/apoyo-profesional/seguimientos', query: { attention_id: selectedAttention.id } })">Crear seguimiento</BButton>
                  <BButton size="sm" variant="outline-secondary" @click="openEdit(selectedAttention)">Editar</BButton>
                  <BButton size="sm" variant="outline-success" @click="closeAttention(selectedAttention)">Cerrar caso</BButton>
                  <BButton size="sm" variant="outline-danger" @click="remove(selectedAttention)">Eliminar</BButton>
                </div>
              </div>
            </template>

            <div class="row g-3">
              <div class="col-md-6"><div class="small text-muted">Profesional</div><div>{{ selectedAttention.professional_role_name }}</div></div>
              <div class="col-md-6"><div class="small text-muted">Área</div><div>{{ selectedAttention.professional_area_name || "-" }}</div></div>
              <div class="col-md-6"><div class="small text-muted">Tipo</div><div>{{ selectedAttention.attention_type_label || "-" }}</div></div>
              <div class="col-md-6"><div class="small text-muted">Motivo</div><div>{{ selectedAttention.motive_label || selectedAttention.reason_summary }}</div></div>
              <div class="col-md-6"><div class="small text-muted">Modalidad</div><div>{{ humanizeSupportStatus(selectedAttention.modality) }}</div></div>
              <div class="col-md-6"><div class="small text-muted">Origen</div><div>{{ humanizeSupportStatus(selectedAttention.origin) }}</div></div>
              <div class="col-md-6"><div class="small text-muted">Prioridad</div><div><SupportStatusBadge :status="selectedAttention.priority_level" /></div></div>
              <div class="col-md-6"><div class="small text-muted">Confidencialidad</div><div><SupportStatusBadge :status="selectedAttention.confidentiality_level" /></div></div>
              <div class="col-12"><div class="small text-muted">Relato / descripción</div><div>{{ selectedAttention.description || "Sin relato" }}</div></div>
              <div class="col-12"><div class="small text-muted">Observaciones profesionales</div><div>{{ selectedAttention.professional_observations || "Sin observaciones" }}</div></div>
              <div class="col-md-6"><div class="small text-muted">Acuerdos</div><div>{{ selectedAttention.agreements || "Sin acuerdos" }}</div></div>
              <div class="col-md-6"><div class="small text-muted">Recomendaciones</div><div>{{ selectedAttention.recommendations || "Sin recomendaciones" }}</div></div>
              <div class="col-12"><div class="small text-muted">Próxima acción</div><div>{{ selectedAttention.next_action || "Sin acción próxima" }}</div></div>
            </div>
          </BCard>

          <SupportDocumentPanel
            v-if="selectedAttention"
            :documents="selectedAttention.documents || []"
            :categories="catalogs.document_categories"
            :student-id="selectedAttention.student_profile_id"
            :upload-url="`/api/apoyo-profesional/attentions/${selectedAttention.id}/documents`"
            :can-upload="canUploadDocuments"
            @refresh="openAttention(selectedAttention.id)"
          />
        </div>
      </div>
    </div>

    <BModal v-model="showModal" :title="form.id ? 'Editar atención profesional' : 'Nueva atención profesional'" size="xl" hide-footer>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Estudiante</label>
          <SupportStudentSearch button-label="Seleccionar" @selected="selectFormStudent" />
          <div v-if="form.student_label" class="small text-muted mt-1">Seleccionado: {{ form.student_label }}</div>
        </div>
        <div class="col-md-4"><label class="form-label">Fecha y hora</label><BFormInput v-model="form.attended_at" type="datetime-local" /></div>
        <div class="col-md-4"><label class="form-label">Tipo de atención</label><BFormSelect v-model="form.attention_type_id" :options="typeOptions" /></div>
        <div class="col-md-4"><label class="form-label">Motivo</label><BFormSelect v-model="form.motive_id" :options="motiveOptions" /></div>
        <div class="col-md-4"><label class="form-label">Modalidad</label><BFormSelect v-model="form.modality" :options="modalityOptions" /></div>
        <div class="col-md-4"><label class="form-label">Origen</label><BFormSelect v-model="form.origin" :options="originOptions" /></div>
        <div class="col-md-4"><label class="form-label">Prioridad</label><BFormSelect v-model="form.priority_level" :options="priorityOptions" /></div>
        <div class="col-md-4"><label class="form-label">Confidencialidad</label><BFormSelect v-model="form.confidentiality_level" :options="confidentialityOptions" /></div>
        <div class="col-md-4"><label class="form-label">Estado</label><BFormSelect v-model="form.status" :options="statusOptions.filter((item) => item.value !== null)" /></div>
        <div class="col-md-4"><label class="form-label">Otra descripción</label><BFormInput v-model="form.attention_type_other" /></div>
        <div class="col-12"><label class="form-label">Motivo resumido</label><BFormInput v-model="form.reason_summary" /></div>
        <div class="col-12"><label class="form-label">Relato / descripción</label><BFormTextarea v-model="form.description" rows="3" /></div>
        <div class="col-12"><label class="form-label">Observaciones profesionales</label><BFormTextarea v-model="form.professional_observations" rows="3" /></div>
        <div class="col-md-6"><label class="form-label">Acuerdos</label><BFormTextarea v-model="form.agreements" rows="2" /></div>
        <div class="col-md-6"><label class="form-label">Recomendaciones</label><BFormTextarea v-model="form.recommendations" rows="2" /></div>
        <div class="col-12"><label class="form-label">Próxima acción</label><BFormTextarea v-model="form.next_action" rows="2" /></div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="outline-secondary" @click="cancelForm">Cancelar registro</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">{{ saving ? "Guardando..." : "Guardar atención" }}</BButton>
      </div>
    </BModal>
  </div>
</template>
