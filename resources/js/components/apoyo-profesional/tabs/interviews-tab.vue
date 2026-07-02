<script>
import axios from "axios";
import LoadingState from "../../ui/loading-state.vue";
import SupportHelpButton from "../help-button.vue";
import SupportStatusBadge from "../status-badge.vue";
import SupportStudentSearch from "../student-search.vue";
import SupportDocumentPanel from "../document-panel.vue";
import {
  confirmSupportAction,
  confirmSupportCancel,
  formatSupportDate,
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
      items: [],
      selectedInterview: null,
      showModal: false,
      filters: {
        status: null,
        student_profile_id: null,
        interview_type: null,
      },
      form: this.emptyForm(),
    };
  },
  computed: {
    typeOptions() {
      return normalizeOptions(this.catalogs.interview_type_options, true);
    },
    editTypeOptions() {
      return normalizeOptions(this.catalogs.interview_type_options);
    },
    statusOptions() {
      return [{ value: null, text: "Todos" }].concat(this.interviewStatusOptions);
    },
    interviewStatusOptions() {
      return [
        { value: "abierta", text: "Abierta" },
        { value: "en_seguimiento", text: "En seguimiento" },
        { value: "cerrada", text: "Cerrada" },
        { value: "cancelada", text: "Cancelada" },
      ];
    },
    professionalOptions() {
      return [{ value: null, text: "Profesional actual" }].concat(
        (this.catalogs.professionals || []).map((item) => ({
          value: item.id,
          text: `${item.staff?.full_name || item.user?.name || item.professional_role_name} · ${item.professional_role_name}`,
        }))
      );
    },
    confidentialityOptions() {
      return normalizeOptions(this.catalogs.confidentiality_options);
    },
    canUploadDocuments() {
      return Boolean(this.selectedInterview && this.catalogs.capabilities?.can_create_attention);
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    formatSupportDate,
    formatSupportDateTime,
    humanizeSupportStatus,
    emptyForm() {
      return {
        id: null,
        student_profile_id: null,
        student_label: "",
        professional_id: null,
        professional_user_id: null,
        interview_type: "entrevista_estudiante",
        interview_at: new Date().toISOString().slice(0, 16),
        participants_text: "",
        motive: "",
        topics: "",
        agreements: "",
        commitments: "",
        follow_up_date: "",
        status: "abierta",
        confidentiality_level: "reservada",
        observations: "",
      };
    },
    async load() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/apoyo-profesional/interviews", {
          params: this.filters,
        });
        this.items = response.data.data || [];

        if (this.selectedInterview?.id) {
          const exists = this.items.find((item) => item.id === this.selectedInterview.id);
          if (exists) {
            await this.openInterview(this.selectedInterview.id);
            return;
          }
        }

        if (this.items[0]) {
          await this.openInterview(this.items[0].id);
        } else {
          this.selectedInterview = null;
        }
      } catch (error) {
        this.error = formatSupportError(error, "No se pudieron cargar las entrevistas.");
      } finally {
        this.loading = false;
      }
    },
    async openInterview(id) {
      try {
        const response = await axios.get(`/api/apoyo-profesional/interviews/${id}`);
        this.selectedInterview = response.data.data;
      } catch (error) {
        this.error = formatSupportError(error, "No se pudo cargar la entrevista seleccionada.");
      }
    },
    selectFilterStudent(student) {
      this.filters.student_profile_id = student.id;
      this.load();
    },
    selectFormStudent(student) {
      this.form.student_profile_id = student.id;
      this.form.student_label = student.full_name;
    },
    openCreate() {
      this.form = this.emptyForm();
      this.showModal = true;
    },
    openEdit(interview) {
      const source = interview?.documents ? interview : this.selectedInterview || interview;
      this.form = {
        id: source.id,
        student_profile_id: source.student_profile_id,
        student_label: source.student?.full_name || "",
        professional_id: source.professional_id,
        professional_user_id: source.professional_user_id,
        interview_type: source.interview_type,
        interview_at: String(source.interview_at).replace(" ", "T").slice(0, 16),
        participants_text: (source.participants || []).join("\n"),
        motive: source.motive,
        topics: source.topics || "",
        agreements: source.agreements || "",
        commitments: source.commitments || "",
        follow_up_date: source.follow_up_date || "",
        status: source.status || "abierta",
        confidentiality_level: source.confidentiality_level || "reservada",
        observations: source.observations || "",
      };
      this.showModal = true;
    },
    syncProfessional() {
      const professional = (this.catalogs.professionals || []).find(
        (item) => item.id === this.form.professional_id
      );
      this.form.professional_user_id = professional?.user_id || null;
    },
    buildPayload() {
      return {
        student_profile_id: this.form.student_profile_id || null,
        professional_id: this.form.professional_id || null,
        professional_user_id: this.form.professional_user_id || null,
        interview_type: this.form.interview_type,
        interview_at: this.form.interview_at,
        participants: (this.form.participants_text || "")
          .split("\n")
          .map((item) => item.trim())
          .filter(Boolean),
        motive: this.form.motive,
        topics: this.form.topics,
        agreements: this.form.agreements,
        commitments: this.form.commitments,
        follow_up_date: this.form.follow_up_date || null,
        status: this.form.status,
        confidentiality_level: this.form.confidentiality_level,
        observations: this.form.observations,
      };
    },
    async save() {
      const confirmation = await confirmSupportAction({
        title: this.form.id ? "Editar entrevista" : "Registrar entrevista",
        text: this.form.id
          ? "Se actualizará la entrevista seleccionada."
          : "Se registrará una nueva entrevista profesional.",
        confirmButtonText: this.form.id ? "Guardar cambios" : "Registrar entrevista",
      });

      if (!confirmation.isConfirmed) return;

      this.saving = true;
      try {
        this.syncProfessional();
        const payload = this.buildPayload();
        const response = this.form.id
          ? await axios.put(`/api/apoyo-profesional/interviews/${this.form.id}`, payload)
          : await axios.post("/api/apoyo-profesional/interviews", payload);

        this.showModal = false;
        await this.load();
        await this.openInterview(response.data.data.id);
        await showSupportSuccess(
          this.form.id ? "Entrevista actualizada correctamente." : "Entrevista registrada correctamente."
        );
      } catch (error) {
        await showSupportError(formatSupportError(error, "No se pudo guardar la entrevista."));
      } finally {
        this.saving = false;
      }
    },
    async remove(interview) {
      const confirmation = await confirmSupportAction({
        title: "Eliminar entrevista",
        text: "La entrevista se eliminará definitivamente.",
        confirmButtonText: "Eliminar",
      });

      if (!confirmation.isConfirmed) return;

      try {
        await axios.delete(`/api/apoyo-profesional/interviews/${interview.id}`);
        this.selectedInterview = null;
        await this.load();
        await showSupportSuccess("Entrevista eliminada correctamente.");
      } catch (error) {
        await showSupportError(formatSupportError(error, "No se pudo eliminar la entrevista."));
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
      <BButton variant="primary" @click="openCreate()">Nueva entrevista</BButton>
      <SupportHelpButton
        title="Ayuda: entrevistas profesionales"
        text="En esta sección se registran entrevistas con estudiantes, apoderados, docentes, familias o redes externas, dejando trazabilidad de acuerdos, compromisos y seguimiento."
      />
    </div>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-xl-5">
          <SupportStudentSearch button-label="Filtrar" @selected="selectFilterStudent" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Tipo</label>
          <BFormSelect v-model="filters.interview_type" :options="typeOptions" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="filters.status" :options="statusOptions" />
        </div>
        <div class="col-12 d-flex gap-2">
          <BButton variant="primary" @click="load">Aplicar filtros</BButton>
          <BButton
            variant="outline-secondary"
            @click="filters = { status: null, student_profile_id: null, interview_type: null }; load();"
          >
            Limpiar
          </BButton>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
    <BCard v-if="loading" class="border-0 shadow-sm">
      <LoadingState message="Cargando entrevistas..." compact />
    </BCard>

    <div v-else class="row g-3">
      <div class="col-xl-4">
        <BCard class="h-100">
          <template #header><div class="fw-semibold">Entrevistas registradas</div></template>
          <div v-if="!items.length" class="text-muted">No hay entrevistas para los filtros seleccionados.</div>
          <div v-else class="d-grid gap-2">
            <button
              v-for="item in items"
              :key="item.id"
              type="button"
              class="btn btn-light text-start border"
              @click="openInterview(item.id)"
            >
              <div class="d-flex justify-content-between align-items-center gap-2">
                <div class="fw-semibold">{{ item.student?.full_name || humanizeSupportStatus(item.interview_type) }}</div>
                <SupportStatusBadge :status="item.status" />
              </div>
              <div class="small">{{ humanizeSupportStatus(item.interview_type) }}</div>
              <div class="small text-muted">{{ formatSupportDateTime(item.interview_at) }}</div>
            </button>
          </div>
        </BCard>
      </div>

      <div class="col-xl-8">
        <div v-if="!selectedInterview" class="text-muted small">
          Selecciona una entrevista para revisar su detalle y los adjuntos asociados.
        </div>

        <div v-else class="d-grid gap-3">
          <BCard>
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-semibold">Detalle de la entrevista</div>
                <div class="d-flex gap-2 flex-wrap">
                  <BButton size="sm" variant="outline-secondary" @click="openEdit(selectedInterview)">Editar</BButton>
                  <BButton size="sm" variant="outline-danger" @click="remove(selectedInterview)">Eliminar</BButton>
                </div>
              </div>
            </template>

            <div class="row g-3">
              <div class="col-md-6">
                <div class="small text-muted">Tipo</div>
                <div>{{ humanizeSupportStatus(selectedInterview.interview_type) }}</div>
              </div>
              <div class="col-md-6">
                <div class="small text-muted">Fecha y hora</div>
                <div>{{ formatSupportDateTime(selectedInterview.interview_at) }}</div>
              </div>
              <div class="col-md-6">
                <div class="small text-muted">Profesional</div>
                <div>
                  {{
                    selectedInterview.professional?.staff?.full_name
                      || selectedInterview.professionalUser?.name
                      || "-"
                  }}
                </div>
              </div>
              <div class="col-md-3">
                <div class="small text-muted">Estado</div>
                <div><SupportStatusBadge :status="selectedInterview.status" /></div>
              </div>
              <div class="col-md-3">
                <div class="small text-muted">Confidencialidad</div>
                <div><SupportStatusBadge :status="selectedInterview.confidentiality_level" /></div>
              </div>
              <div class="col-12">
                <div class="small text-muted">Participantes</div>
                <ul class="mb-0 small ps-3">
                  <li v-for="(participant, index) in selectedInterview.participants || []" :key="index">
                    {{ participant }}
                  </li>
                </ul>
              </div>
              <div class="col-12">
                <div class="small text-muted">Motivo</div>
                <div>{{ selectedInterview.motive }}</div>
              </div>
              <div class="col-md-6">
                <div class="small text-muted">Temas tratados</div>
                <div>{{ selectedInterview.topics || "Sin detalle." }}</div>
              </div>
              <div class="col-md-6">
                <div class="small text-muted">Acuerdos</div>
                <div>{{ selectedInterview.agreements || "Sin acuerdos." }}</div>
              </div>
              <div class="col-md-6">
                <div class="small text-muted">Compromisos</div>
                <div>{{ selectedInterview.commitments || "Sin compromisos." }}</div>
              </div>
              <div class="col-md-6">
                <div class="small text-muted">Fecha de seguimiento</div>
                <div>{{ formatSupportDate(selectedInterview.follow_up_date) }}</div>
              </div>
              <div class="col-12">
                <div class="small text-muted">Observaciones</div>
                <div>{{ selectedInterview.observations || "Sin observaciones." }}</div>
              </div>
            </div>
          </BCard>

          <SupportDocumentPanel
            :documents="selectedInterview.documents || []"
            :categories="catalogs.document_categories"
            :student-id="selectedInterview.student_profile_id"
            :upload-url="`/api/apoyo-profesional/interviews/${selectedInterview.id}/documents`"
            :can-upload="canUploadDocuments"
            @refresh="openInterview(selectedInterview.id)"
          />
        </div>
      </div>
    </div>

    <BModal
      v-model="showModal"
      :title="form.id ? 'Editar entrevista' : 'Nueva entrevista'"
      hide-footer
      size="xl"
    >
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Estudiante asociado</label>
          <SupportStudentSearch button-label="Seleccionar" @selected="selectFormStudent" />
          <div v-if="form.student_label" class="small text-muted mt-1">Seleccionado: {{ form.student_label }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Tipo de entrevista</label>
          <BFormSelect v-model="form.interview_type" :options="editTypeOptions" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Fecha y hora</label>
          <BFormInput v-model="form.interview_at" type="datetime-local" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Profesional</label>
          <BFormSelect v-model="form.professional_id" :options="professionalOptions" @change="syncProfessional" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="form.status" :options="interviewStatusOptions" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Confidencialidad</label>
          <BFormSelect v-model="form.confidentiality_level" :options="confidentialityOptions" />
        </div>
        <div class="col-12">
          <label class="form-label">Participantes</label>
          <BFormTextarea
            v-model="form.participants_text"
            rows="3"
            placeholder="Un participante por línea"
          />
        </div>
        <div class="col-12">
          <label class="form-label">Motivo</label>
          <BFormTextarea v-model="form.motive" rows="2" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Temas tratados</label>
          <BFormTextarea v-model="form.topics" rows="3" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Acuerdos</label>
          <BFormTextarea v-model="form.agreements" rows="3" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Compromisos</label>
          <BFormTextarea v-model="form.commitments" rows="3" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Fecha de seguimiento</label>
          <BFormInput v-model="form.follow_up_date" type="date" />
        </div>
        <div class="col-12">
          <label class="form-label">Observaciones</label>
          <BFormTextarea v-model="form.observations" rows="3" />
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-4">
        <BButton variant="outline-secondary" @click="cancelForm">Cancelar registro</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">
          {{ saving ? "Guardando..." : "Guardar entrevista" }}
        </BButton>
      </div>
    </BModal>
  </div>
</template>
