<script>
import axios from "axios";
import LoadingState from "../../ui/loading-state.vue";
import SupportHelpButton from "../help-button.vue";
import SupportStatusBadge from "../status-badge.vue";
import SupportStudentSearch from "../student-search.vue";
import SupportStudentContextCard from "../student-context-card.vue";
import SupportDocumentPanel from "../document-panel.vue";
import {
  downloadSupportFile,
  formatSupportDateTime,
  formatSupportError,
  humanizeSupportStatus,
  normalizeOptions,
  showSupportError,
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
      error: null,
      selectedStudent: null,
      history: {
        student: null,
        summary: {},
        attentions: [],
        derivations: [],
        follow_ups: [],
        plans: [],
        interviews: [],
        documents: [],
      },
      filters: {
        academic_year_id: null,
        attended_by_user_id: null,
        attention_type_label: null,
        status: null,
        confidentiality_level: null,
        professional_area_name: null,
      },
    };
  },
  computed: {
    yearOptions() {
      return normalizeOptions(this.catalogs.academic_years, true);
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
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.attention_types || []).map((item) => ({
          value: item.name,
          text: item.name,
        }))
      );
    },
    statusOptions() {
      return normalizeOptions(this.catalogs.attention_status_options, true);
    },
    confidentialityOptions() {
      return normalizeOptions(this.catalogs.confidentiality_options, true);
    },
    areaOptions() {
      return [{ value: null, text: "Todas" }].concat(
        (this.catalogs.area_options || []).map((item) => ({
          value: item.label,
          text: item.label,
        }))
      );
    },
  },
  mounted() {
    const studentId = this.$route.query.student_id;
    if (studentId) {
      this.openStudent({ id: studentId });
    }
  },
  watch: {
    "$route.query.student_id"(value) {
      if (value) {
        this.openStudent({ id: value });
      }
    },
  },
  methods: {
    formatSupportDateTime,
    humanizeSupportStatus,
    async openStudent(student) {
      if (!student?.id) return;
      this.selectedStudent = student;
      await this.loadHistory();
    },
    async loadHistory() {
      if (!this.selectedStudent?.id) return;

      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get(`/api/apoyo-profesional/student-history/${this.selectedStudent.id}`, {
          params: this.filters,
        });
        this.history = response.data;
      } catch (error) {
        this.error = formatSupportError(error, "No se pudo cargar la ficha del estudiante.");
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.filters = {
        academic_year_id: null,
        attended_by_user_id: null,
        attention_type_label: null,
        status: null,
        confidentiality_level: null,
        professional_area_name: null,
      };
      this.loadHistory();
    },
    async download(document) {
      try {
        await downloadSupportFile(`/api/apoyo-profesional/documents/${document.id}/download`, document.original_name);
      } catch (error) {
        showSupportError(formatSupportError(error, "No se pudo descargar el archivo."));
      }
    },
  },
};
</script>

<template>
  <div>
    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-xl-8">
          <SupportStudentSearch auto-navigate @selected="openStudent" />
        </div>
        <div class="col-xl-4 d-flex align-items-center">
          <div class="text-muted small">
            Al seleccionar un estudiante se abre su ficha de apoyo con historial, derivaciones, seguimientos y adjuntos visibles.
          </div>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

    <div class="row g-3">
      <div class="col-xl-4">
        <SupportStudentContextCard :student="history.student" />
      </div>

      <div class="col-xl-8">
        <BCard class="mb-3">
          <template #header>
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
              <div class="fw-semibold">Filtros del historial</div>
              <SupportHelpButton
                title="Ayuda: filtros del historial"
                text="Los filtros permiten revisar la ficha por año, profesional, tipo de atención, estado, confidencialidad o área profesional."
              />
            </div>
          </template>

          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Año</label><BFormSelect v-model="filters.academic_year_id" :options="yearOptions" /></div>
            <div class="col-md-4"><label class="form-label">Profesional</label><BFormSelect v-model="filters.attended_by_user_id" :options="professionalOptions" /></div>
            <div class="col-md-4"><label class="form-label">Tipo de atención</label><BFormSelect v-model="filters.attention_type_label" :options="typeOptions" /></div>
            <div class="col-md-4"><label class="form-label">Estado</label><BFormSelect v-model="filters.status" :options="statusOptions" /></div>
            <div class="col-md-4"><label class="form-label">Confidencialidad</label><BFormSelect v-model="filters.confidentiality_level" :options="confidentialityOptions" /></div>
            <div class="col-md-4"><label class="form-label">Área</label><BFormSelect v-model="filters.professional_area_name" :options="areaOptions" /></div>
            <div class="col-12 d-flex gap-2">
              <BButton variant="primary" :disabled="!selectedStudent" @click="loadHistory">Aplicar filtros</BButton>
              <BButton variant="outline-secondary" :disabled="!selectedStudent" @click="resetFilters">Limpiar</BButton>
            </div>
          </div>
        </BCard>

        <BCard v-if="loading">
          <LoadingState message="Cargando ficha del estudiante..." compact />
        </BCard>

        <template v-else-if="history.student">
          <div class="row g-3 mb-3">
            <div class="col-md-3"><BCard class="border-0 shadow-sm"><div class="text-muted small">Atenciones</div><div class="display-6 fw-semibold">{{ history.summary.attentions_total || 0 }}</div></BCard></div>
            <div class="col-md-3"><BCard class="border-0 shadow-sm"><div class="text-muted small">Derivaciones</div><div class="display-6 fw-semibold">{{ history.summary.derivations_total || 0 }}</div></BCard></div>
            <div class="col-md-3"><BCard class="border-0 shadow-sm"><div class="text-muted small">Seguimientos</div><div class="display-6 fw-semibold">{{ history.summary.follow_ups_total || 0 }}</div></BCard></div>
            <div class="col-md-3"><BCard class="border-0 shadow-sm"><div class="text-muted small">Planes</div><div class="display-6 fw-semibold">{{ history.summary.plans_total || 0 }}</div></BCard></div>
          </div>

          <BCard class="mb-3">
            <template #header><div class="fw-semibold">Atenciones registradas</div></template>
            <div v-if="!history.attentions.length" class="text-muted">No hay atenciones para los filtros seleccionados.</div>
            <div v-else class="d-grid gap-3">
              <div v-for="attention in history.attentions" :key="attention.id" class="border rounded p-3">
                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">
                  <div>
                    <div class="fw-semibold">{{ attention.reason_summary }}</div>
                    <div class="small text-muted">{{ attention.professional_role_name }} · {{ formatSupportDateTime(attention.attended_at) }}</div>
                  </div>
                  <div class="d-flex gap-2">
                    <SupportStatusBadge :status="attention.confidentiality_level" />
                    <SupportStatusBadge :status="attention.status" />
                  </div>
                </div>
                <div class="small mb-2">{{ attention.description || attention.professional_observations || "Sin descripción adicional." }}</div>
                <div class="small text-muted">
                  Acuerdos: {{ attention.agreements || "Sin acuerdos" }}<br />
                  Recomendaciones: {{ attention.recommendations || "Sin recomendaciones" }}
                </div>
              </div>
            </div>
          </BCard>

          <div class="row g-3 mb-3">
            <div class="col-xl-6">
              <BCard class="h-100">
                <template #header><div class="fw-semibold">Derivaciones</div></template>
                <div v-if="!history.derivations.length" class="text-muted">Sin derivaciones registradas.</div>
                <div v-else class="d-grid gap-2">
                  <div v-for="item in history.derivations" :key="item.id" class="border rounded p-2">
                    <div class="d-flex justify-content-between gap-2 align-items-center">
                      <div class="fw-semibold">{{ item.destination_area_name }}</div>
                      <SupportStatusBadge :status="item.status" />
                    </div>
                    <div class="small">{{ item.reason }}</div>
                    <div class="small text-muted">{{ formatSupportDateTime(item.derived_at) }}</div>
                  </div>
                </div>
              </BCard>
            </div>
            <div class="col-xl-6">
              <BCard class="h-100">
                <template #header><div class="fw-semibold">Seguimientos</div></template>
                <div v-if="!history.follow_ups.length" class="text-muted">Sin seguimientos registrados.</div>
                <div v-else class="d-grid gap-2">
                  <div v-for="item in history.follow_ups" :key="item.id" class="border rounded p-2">
                    <div class="d-flex justify-content-between gap-2 align-items-center">
                      <div class="fw-semibold">Seguimiento #{{ item.id }}</div>
                      <SupportStatusBadge :status="item.status" />
                    </div>
                    <div class="small">{{ item.comment }}</div>
                    <div class="small text-muted">{{ formatSupportDateTime(item.scheduled_at) }}</div>
                  </div>
                </div>
              </BCard>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-xl-6">
              <BCard class="h-100">
                <template #header><div class="fw-semibold">Planes de apoyo</div></template>
                <div v-if="!history.plans.length" class="text-muted">Sin planes de apoyo registrados.</div>
                <div v-else class="d-grid gap-2">
                  <div v-for="item in history.plans" :key="item.id" class="border rounded p-2">
                    <div class="d-flex justify-content-between gap-2 align-items-center">
                      <div class="fw-semibold">{{ item.motive }}</div>
                      <SupportStatusBadge :status="item.status" />
                    </div>
                    <div class="small">{{ item.general_objective }}</div>
                    <div class="small text-muted">{{ item.area_name || "Sin área" }} · {{ item.start_date }}</div>
                  </div>
                </div>
              </BCard>
            </div>
            <div class="col-xl-6">
              <BCard class="h-100">
                <template #header><div class="fw-semibold">Entrevistas</div></template>
                <div v-if="!history.interviews.length" class="text-muted">Sin entrevistas registradas.</div>
                <div v-else class="d-grid gap-2">
                  <div v-for="item in history.interviews" :key="item.id" class="border rounded p-2">
                    <div class="d-flex justify-content-between gap-2 align-items-center">
                      <div class="fw-semibold">{{ humanizeSupportStatus(item.interview_type) }}</div>
                      <SupportStatusBadge :status="item.status" />
                    </div>
                    <div class="small">{{ item.motive }}</div>
                    <div class="small text-muted">{{ formatSupportDateTime(item.interview_at) }}</div>
                  </div>
                </div>
              </BCard>
            </div>
          </div>

          <SupportDocumentPanel
            :documents="history.documents || []"
            :categories="catalogs.document_categories"
            :student-id="history.student?.id"
            :can-upload="false"
            @refresh="loadHistory"
          />
        </template>
      </div>
    </div>
  </div>
</template>
