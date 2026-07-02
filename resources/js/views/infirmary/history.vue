<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import InfirmaryHelpButton from "../../components/infirmary/help-button.vue";
import InfirmaryStatusBadge from "../../components/infirmary/status-badge.vue";
import InfirmaryStudentSearch from "../../components/infirmary/student-search.vue";
import InfirmaryStudentContextCard from "../../components/infirmary/student-context-card.vue";
import {
  downloadInfirmaryFile,
  formatInfirmaryDate,
  formatInfirmaryDateTime,
  formatInfirmaryError,
  humanizeInfirmaryStatus,
  normalizeOptions,
  showInfirmaryError,
} from "../../components/infirmary/module-utils";

export default {
  components: {
    Layout,
    LoadingState,
    InfirmaryHelpButton,
    InfirmaryStatusBadge,
    InfirmaryStudentSearch,
    InfirmaryStudentContextCard,
  },
  data() {
    return {
      loading: false,
      loadingCatalogs: false,
      error: null,
      selectedStudent: null,
      history: {
        student: null,
        summary: {},
        attentions: [],
        accidents: [],
        administrations: [],
        calls: [],
        documents: [],
        authorizations: [],
      },
      catalogs: {
        academic_years: [],
        courses: [],
        attention_categories: [],
        medications: [],
        referral_options: [],
      },
      filters: {
        academic_year_id: null,
        course_section_id: null,
        attention_category: null,
        medication_id: null,
        referral_type: null,
        accident_only: false,
      },
    };
  },
  computed: {
    yearOptions() {
      return normalizeOptions(this.catalogs.academic_years, true);
    },
    courseOptions() {
      return normalizeOptions(this.catalogs.courses, true);
    },
    attentionCategoryOptions() {
      return normalizeOptions(this.catalogs.attention_categories, true);
    },
    medicationOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.medications || []).map((item) => ({
          value: item.id,
          text: item.commercial_name || item.name,
        }))
      );
    },
    referralOptions() {
      return normalizeOptions(this.catalogs.referral_options, true);
    },
  },
  async mounted() {
    await this.loadCatalogs();
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
    formatInfirmaryDate,
    formatInfirmaryDateTime,
    humanizeInfirmaryStatus,
    async loadCatalogs() {
      this.loadingCatalogs = true;
      try {
        const response = await axios.get("/api/infirmary/catalogs");
        this.catalogs = response.data;
      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudieron cargar los catálogos.");
      } finally {
        this.loadingCatalogs = false;
      }
    },
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
        const response = await axios.get(`/api/infirmary/student-history/${this.selectedStudent.id}`, {
          params: this.filters,
        });
        this.history = response.data;
      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudo cargar la ficha médica.");
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.filters = {
        academic_year_id: null,
        course_section_id: null,
        attention_category: null,
        medication_id: null,
        referral_type: null,
        accident_only: false,
      };
      this.loadHistory();
    },
    async download(document) {
      try {
        await downloadInfirmaryFile(`/api/infirmary/documents/${document.id}/download`, document.original_name);
      } catch (error) {
        showInfirmaryError(formatInfirmaryError(error, "No se pudo descargar el archivo."));
      }
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Ficha médica del estudiante</h4>
        <div class="text-muted">
          Historial clínico consolidado con atenciones, accidentes, administraciones, llamados, seguimientos y adjuntos.
        </div>
      </div>
      <InfirmaryHelpButton
        title="Ayuda: ficha médica"
        text="En esta pantalla se concentra todo el historial médico del estudiante con filtros por año, curso, categoría, medicamentos y derivaciones."
      />
    </div>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-xl-8">
          <InfirmaryStudentSearch auto-navigate @selected="openStudent" />
        </div>
        <div class="col-xl-4 d-flex align-items-center">
          <div class="text-muted small">
            Al seleccionar una estudiante se abre inmediatamente su ficha clínica completa.
          </div>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

    <div class="row g-3">
      <div class="col-xl-4">
        <InfirmaryStudentContextCard :student="history.student" />
      </div>

      <div class="col-xl-8">
        <BCard class="mb-3">
          <template #header>
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
              <div class="fw-semibold">Filtros clínicos</div>
              <InfirmaryHelpButton
                title="Ayuda: filtros clínicos"
                text="Los filtros permiten revisar la ficha médica por año, curso, categoría de atención, medicamento, derivación o sólo accidentes."
              />
            </div>
          </template>

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Año</label>
              <BFormSelect v-model="filters.academic_year_id" :options="yearOptions" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Curso</label>
              <BFormSelect v-model="filters.course_section_id" :options="courseOptions" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Categoría</label>
              <BFormSelect v-model="filters.attention_category" :options="attentionCategoryOptions" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Medicamento</label>
              <BFormSelect v-model="filters.medication_id" :options="medicationOptions" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Derivación</label>
              <BFormSelect v-model="filters.referral_type" :options="referralOptions" />
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <div class="form-check">
                <input id="accident_only" v-model="filters.accident_only" class="form-check-input" type="checkbox" />
                <label class="form-check-label" for="accident_only">Sólo atenciones con accidente</label>
              </div>
            </div>
            <div class="col-12 d-flex gap-2">
              <BButton variant="primary" :disabled="!selectedStudent" @click="loadHistory">Aplicar filtros</BButton>
              <BButton variant="outline-secondary" :disabled="!selectedStudent" @click="resetFilters">Limpiar</BButton>
            </div>
          </div>
        </BCard>

        <BCard v-if="loading">
          <LoadingState message="Cargando ficha médica..." compact />
        </BCard>

        <template v-else-if="history.student">
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <BCard class="border-0 shadow-sm">
                <div class="text-muted small">Atenciones</div>
                <div class="display-6 fw-semibold">{{ history.summary.attentions_total || 0 }}</div>
              </BCard>
            </div>
            <div class="col-md-4">
              <BCard class="border-0 shadow-sm">
                <div class="text-muted small">Accidentes</div>
                <div class="display-6 fw-semibold">{{ history.summary.accidents_total || 0 }}</div>
              </BCard>
            </div>
            <div class="col-md-4">
              <BCard class="border-0 shadow-sm">
                <div class="text-muted small">Medicamentos administrados</div>
                <div class="display-6 fw-semibold">{{ history.summary.administrations_total || 0 }}</div>
              </BCard>
            </div>
          </div>

          <BCard class="mb-3">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-semibold">Atenciones registradas</div>
                <InfirmaryHelpButton
                  title="Ayuda: atenciones del historial"
                  text="Aquí se muestran todas las atenciones del estudiante con su trazabilidad clínica, tratamientos, derivaciones, llamados y seguimientos."
                />
              </div>
            </template>
            <div v-if="!history.attentions.length" class="text-muted">No hay atenciones para los filtros seleccionados.</div>
            <div v-else class="d-grid gap-3">
              <div v-for="attention in history.attentions" :key="attention.id" class="border rounded p-3">
                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">
                  <div>
                    <div class="fw-semibold">
                      {{ attention.consultation_reason || humanizeInfirmaryStatus(attention.attention_category) }}
                    </div>
                    <div class="text-muted small">{{ formatInfirmaryDateTime(attention.attended_at) }}</div>
                  </div>
                  <div class="d-flex gap-2">
                    <InfirmaryStatusBadge :status="attention.priority" />
                    <InfirmaryStatusBadge :status="attention.status" />
                  </div>
                </div>
                <div class="row g-3">
                  <div class="col-md-6">
                    <div class="small text-muted">Categoría</div>
                    <div>{{ humanizeInfirmaryStatus(attention.attention_category) }}</div>
                  </div>
                  <div class="col-md-6">
                    <div class="small text-muted">Descripción</div>
                    <div>{{ attention.initial_description || attention.observations || "Sin descripción adicional." }}</div>
                  </div>
                  <div class="col-md-6">
                    <div class="fw-semibold mb-2">Tratamientos</div>
                    <div v-if="!(attention.treatments || []).length" class="text-muted small">Sin tratamientos registrados.</div>
                    <ul v-else class="list-group list-group-flush">
                      <li v-for="treatment in attention.treatments" :key="treatment.id" class="list-group-item px-0">
                        <div>{{ (treatment.treatment_types || []).map(humanizeInfirmaryStatus).join(", ") || "Sin detalle" }}</div>
                        <div class="small text-muted">
                          {{ treatment.medication?.commercial_name || treatment.medication?.name || "Sin medicamento" }}
                          <span v-if="treatment.bmi"> · IMC {{ treatment.bmi }}</span>
                        </div>
                      </li>
                    </ul>
                  </div>
                  <div class="col-md-6">
                    <div class="fw-semibold mb-2">Derivaciones y seguimiento</div>
                    <div v-if="!(attention.referrals || []).length && !(attention.follow_ups || []).length" class="text-muted small">Sin derivaciones ni seguimientos.</div>
                    <ul v-else class="list-group list-group-flush">
                      <li v-for="referral in attention.referrals" :key="`ref-${referral.id}`" class="list-group-item px-0">
                        <div>{{ humanizeInfirmaryStatus(referral.referral_type) }}</div>
                        <div class="small text-muted">{{ referral.reason || "Sin motivo" }}</div>
                      </li>
                      <li v-for="followUp in attention.follow_ups" :key="`fu-${followUp.id}`" class="list-group-item px-0">
                        <div>{{ followUp.comment }}</div>
                        <div class="small text-muted">
                          <InfirmaryStatusBadge :status="followUp.status" /> · {{ formatInfirmaryDateTime(followUp.followed_at) }}
                        </div>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </BCard>

          <div class="row g-3 mb-3">
            <div class="col-xl-6">
              <BCard class="h-100">
                <template #header>
                  <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                    <div class="fw-semibold">Accidentes escolares</div>
                    <InfirmaryHelpButton
                      title="Ayuda: accidentes del historial"
                      text="Aquí se listan todos los accidentes escolares del estudiante con severidad, lugar, DIAT y estado del caso."
                    />
                  </div>
                </template>
                <div v-if="!history.accidents.length" class="text-muted">Sin accidentes registrados.</div>
                <div v-else class="table-responsive">
                  <table class="table table-sm align-middle mb-0">
                    <thead>
                      <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Lugar</th>
                        <th>Estado</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="item in history.accidents" :key="item.id">
                        <td>{{ formatInfirmaryDateTime(item.occurred_at) }}</td>
                        <td>{{ item.accident_type }}</td>
                        <td>{{ item.place || item.dependency?.name || "-" }}</td>
                        <td><InfirmaryStatusBadge :status="item.case_status" /></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </BCard>
            </div>
            <div class="col-xl-6">
              <BCard class="h-100">
                <template #header>
                  <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                    <div class="fw-semibold">Medicamentos administrados</div>
                    <InfirmaryHelpButton
                      title="Ayuda: medicamentos administrados"
                      text="Este bloque muestra las administraciones efectivas registradas para el estudiante, incluyendo cantidad, horario y responsable."
                    />
                  </div>
                </template>
                <div v-if="!history.administrations.length" class="text-muted">Sin administraciones registradas.</div>
                <div v-else class="table-responsive">
                  <table class="table table-sm align-middle mb-0">
                    <thead>
                      <tr>
                        <th>Fecha</th>
                        <th>Medicamento</th>
                        <th>Cantidad</th>
                        <th>Funcionario</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="item in history.administrations" :key="item.id">
                        <td>{{ formatInfirmaryDateTime(item.administered_at) }}</td>
                        <td>{{ item.medication?.commercial_name || item.medication?.name }}</td>
                        <td>{{ item.quantity_administered }} {{ item.medication?.unit || "" }}</td>
                        <td>{{ item.administered_by?.name || "-" }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </BCard>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-xl-6">
              <BCard class="h-100">
                <template #header>
                  <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                    <div class="fw-semibold">Llamados realizados y autorizaciones</div>
                    <InfirmaryHelpButton
                      title="Ayuda: llamados y autorizaciones"
                      text="En esta sección se visualizan las comunicaciones con apoderados y las autorizaciones médicas vigentes o históricas."
                    />
                  </div>
                </template>
                <div class="mb-3">
                  <div class="fw-semibold mb-2">Llamados</div>
                  <div v-if="!history.calls.length" class="text-muted">Sin llamados registrados.</div>
                  <ul v-else class="list-group list-group-flush">
                    <li v-for="item in history.calls" :key="item.id" class="list-group-item px-0">
                      <div class="d-flex justify-content-between gap-2">
                        <div>{{ item.person_contacted }} · {{ item.phone_number || "Sin número" }}</div>
                        <InfirmaryStatusBadge :status="item.call_status" />
                      </div>
                      <div class="small text-muted">{{ item.reason || "Sin motivo" }} · {{ formatInfirmaryDateTime(item.called_at) }}</div>
                    </li>
                  </ul>
                </div>
                <div>
                  <div class="fw-semibold mb-2">Autorizaciones</div>
                  <div v-if="!history.authorizations.length" class="text-muted">Sin autorizaciones registradas.</div>
                  <ul v-else class="list-group list-group-flush">
                    <li v-for="item in history.authorizations" :key="item.id" class="list-group-item px-0">
                      <div class="d-flex justify-content-between gap-2">
                        <div>{{ item.medication?.commercial_name || item.medication?.name }}</div>
                        <InfirmaryStatusBadge :status="item.status" />
                      </div>
                      <div class="small text-muted">{{ item.dose }} · {{ item.schedule_text || "Sin horario" }}</div>
                    </li>
                  </ul>
                </div>
              </BCard>
            </div>
            <div class="col-xl-6">
              <BCard class="h-100">
                <template #header>
                  <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                    <div class="fw-semibold">Documentos adjuntos</div>
                    <InfirmaryHelpButton
                      title="Ayuda: documentos del historial"
                      text="Desde aquí puedes revisar todos los documentos clínicos asociados al estudiante, como recetas, certificados, informes y fotografías."
                    />
                  </div>
                </template>
                <div v-if="!history.documents.length" class="text-muted">Sin documentos adjuntos.</div>
                <div v-else class="table-responsive">
                  <table class="table table-sm align-middle mb-0">
                    <thead>
                      <tr>
                        <th>Archivo</th>
                        <th>Categoría</th>
                        <th>Fecha</th>
                        <th class="text-end">Acción</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="item in history.documents" :key="item.id">
                        <td>
                          <div class="fw-semibold">{{ item.original_name }}</div>
                          <div class="small text-muted">{{ item.notes || "Sin observaciones" }}</div>
                        </td>
                        <td>{{ humanizeInfirmaryStatus(item.category) }}</td>
                        <td>{{ formatInfirmaryDateTime(item.created_at) }}</td>
                        <td class="text-end">
                          <BButton size="sm" variant="outline-primary" @click="download(item)">Descargar</BButton>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </BCard>
            </div>
          </div>
        </template>

        <BCard v-else>
          <div class="text-muted">
            Usa el buscador global para seleccionar una estudiante y revisar su historial médico.
          </div>
        </BCard>
      </div>
    </div>
  </Layout>
</template>
