<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import InfirmaryDocumentPanel from "../../components/infirmary/document-panel.vue";
import InfirmaryHelpButton from "../../components/infirmary/help-button.vue";
import InfirmaryStatusBadge from "../../components/infirmary/status-badge.vue";
import InfirmaryStudentSearch from "../../components/infirmary/student-search.vue";
import InfirmaryStudentContextCard from "../../components/infirmary/student-context-card.vue";
import {
  confirmInfirmaryAction,
  confirmInfirmaryCancel,
  formatInfirmaryDate,
  formatInfirmaryDateTime,
  formatInfirmaryError,
  normalizeOptions,
  showInfirmaryError,
  showInfirmarySuccess,
  showInfirmaryWarning,
  toInputDate,
  toInputDateTime,
} from "../../components/infirmary/module-utils";

export default {
  components: {
    Layout,
    LoadingState,
    InfirmaryDocumentPanel,
    InfirmaryHelpButton,
    InfirmaryStatusBadge,
    InfirmaryStudentSearch,
    InfirmaryStudentContextCard,
  },
  data() {
    return {
      loading: false,
      saving: false,
      administering: false,
      error: null,
      selectedStudentContext: null,
      catalogs: {
        medications: [],
        users: [],
        capabilities: {},
      },
      filters: {
        search: "",
        student_profile_id: null,
        medication_id: null,
        status: null,
      },
      authorizations: [],
      pagination: { current_page: 1, total: 0, per_page: 15 },
      selectedAuthorization: null,
      showModal: false,
      showAdministrationModal: false,
      form: this.emptyForm(),
      administrationForm: this.emptyAdministrationForm(),
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
    canManage() {
      return Boolean(this.catalogs.capabilities?.can_manage_medications);
    },
    medicationOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.medications || []).map((item) => ({
          value: item.id,
          text: item.commercial_name || item.name,
        }))
      );
    },
    statusOptions() {
      return [
        { value: null, text: "Todos" },
        { value: "vigente", text: "Vigente" },
        { value: "proxima_a_vencer", text: "Próxima a vencer" },
        { value: "vencida", text: "Vencida" },
        { value: "terminada", text: "Terminada" },
      ];
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
    await this.loadAuthorizations();
  },
  methods: {
    formatInfirmaryDate,
    formatInfirmaryDateTime,
    normalizeOptions,
    emptyForm() {
      return {
        id: null,
        student_profile_id: null,
        student_label: "",
        medication_id: null,
        diagnosis: "",
        dose: "",
        frequency: "",
        schedule_text: "",
        start_date: toInputDate(new Date().toISOString()),
        end_date: "",
        physician_name: "",
        medical_authorization_expires_at: "",
        guardian_authorization_expires_at: "",
        observations: "",
        status: "vigente",
      };
    },
    emptyAdministrationForm() {
      return {
        administered_at: toInputDateTime(new Date().toISOString()),
        medication_id: null,
        student_profile_id: null,
        quantity_administered: 1,
        administered_by_user_id: null,
        schedule_reference: "",
        observations: "",
      };
    },
    async loadCatalogs() {
      const response = await axios.get("/api/infirmary/catalogs");
      this.catalogs = response.data;
    },
    async loadAuthorizations(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/infirmary/medication-authorizations", {
          params: {
            page,
            ...this.filters,
          },
        });
        this.authorizations = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page || 1,
          total: response.data.total || 0,
          per_page: response.data.per_page || 15,
        };

        if (this.authorizations[0]) {
          await this.openAuthorization(this.authorizations[0]);
        } else {
          this.selectedAuthorization = null;
          this.selectedStudentContext = null;
        }
      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudo cargar la administración de medicamentos.");
      } finally {
        this.loading = false;
      }
    },
    async openAuthorization(authorization) {
      try {
        const response = await axios.get(`/api/infirmary/medication-authorizations/${authorization.id}`);
        this.selectedAuthorization = response.data.data;
        if (this.selectedAuthorization?.student_profile_id) {
          const history = await axios.get(`/api/infirmary/student-history/${this.selectedAuthorization.student_profile_id}`);
          this.selectedStudentContext = history.data.student;
        }
      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudo cargar el detalle de la autorización.");
      }
    },
    selectStudent(student) {
      this.filters.student_profile_id = student.id;
      this.loadAuthorizations(1);
    },
    selectFormStudent(student) {
      this.form.student_profile_id = student.id;
      this.form.student_label = student.full_name;
    },
    resetFilters() {
      this.filters = {
        search: "",
        student_profile_id: null,
        medication_id: null,
        status: null,
      };
      this.loadAuthorizations(1);
    },
    openCreate() {
      this.form = this.emptyForm();
      this.showModal = true;
    },
    openEdit(authorization) {
      this.form = {
        id: authorization.id,
        student_profile_id: authorization.student_profile_id,
        student_label: authorization.student ? `${authorization.student.first_name} ${authorization.student.last_name}` : "",
        medication_id: authorization.medication_id,
        diagnosis: authorization.diagnosis || "",
        dose: authorization.dose || "",
        frequency: authorization.frequency || "",
        schedule_text: authorization.schedule_text || "",
        start_date: toInputDate(authorization.start_date),
        end_date: toInputDate(authorization.end_date),
        physician_name: authorization.physician_name || "",
        medical_authorization_expires_at: toInputDate(authorization.medical_authorization_expires_at),
        guardian_authorization_expires_at: toInputDate(authorization.guardian_authorization_expires_at),
        observations: authorization.observations || "",
        status: authorization.status || "vigente",
      };
      this.showModal = true;
    },
    async cancelModal() {
      const result = await confirmInfirmaryCancel("la autorización médica");
      if (result.isConfirmed) this.showModal = false;
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
          end_date: this.form.end_date || null,
          medical_authorization_expires_at: this.form.medical_authorization_expires_at || null,
          guardian_authorization_expires_at: this.form.guardian_authorization_expires_at || null,
        };

        if (this.isEditing) {
          await axios.put(`/api/infirmary/medication-authorizations/${this.form.id}`, payload);
        } else {
          await axios.post("/api/infirmary/medication-authorizations", payload);
        }

        this.showModal = false;
        await this.loadAuthorizations(this.pagination.current_page || 1);
        await showInfirmarySuccess(this.isEditing ? "Autorización actualizada correctamente." : "Autorización registrada correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo guardar la autorización."));
      } finally {
        this.saving = false;
      }
    },
    async remove(authorization) {
      const confirmation = await confirmInfirmaryAction({
        title: "Eliminar autorización",
        text: "Se eliminará la autorización médica seleccionada.",
        confirmButtonText: "Eliminar",
      });

      if (!confirmation.isConfirmed) return;

      try {
        await axios.delete(`/api/infirmary/medication-authorizations/${authorization.id}`);
        await this.loadAuthorizations(this.pagination.current_page || 1);
        await showInfirmarySuccess("Autorización eliminada correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo eliminar la autorización."));
      }
    },
    openAdministration(authorization) {
      this.selectedAuthorization = authorization;
      this.administrationForm = {
        ...this.emptyAdministrationForm(),
        medication_id: authorization.medication_id,
        student_profile_id: authorization.student_profile_id,
        schedule_reference: authorization.schedule_text || "",
      };
      this.showAdministrationModal = true;
    },
    async cancelAdministrationModal() {
      const result = await confirmInfirmaryCancel("la administración del medicamento");
      if (result.isConfirmed) this.showAdministrationModal = false;
    },
    async saveAdministration() {
      if (!this.selectedAuthorization?.id) return;

      this.administering = true;
      try {
        await axios.post(
          `/api/infirmary/medication-authorizations/${this.selectedAuthorization.id}/administrations`,
          this.administrationForm
        );
        this.showAdministrationModal = false;
        await this.openAuthorization(this.selectedAuthorization);
        await this.loadAuthorizations(this.pagination.current_page || 1);
        await showInfirmarySuccess("Administración registrada correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo registrar la administración."));
      } finally {
        this.administering = false;
      }
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Administración de medicamentos</h4>
        <div class="text-muted">
          Control de autorizaciones permanentes, administración diaria, vigencias y descuento automático de stock.
        </div>
      </div>
      <div class="d-flex gap-2">
        <InfirmaryHelpButton
          title="Ayuda: administración de medicamentos"
          text="Aquí se registran autorizaciones médicas permanentes, administraciones efectivas, vigencias documentales y alertas por término o vencimiento."
        />
        <BButton v-if="canManage" variant="primary" @click="openCreate">Nueva autorización</BButton>
      </div>
    </div>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

    <div class="row g-3">
      <div class="col-xl-8">
        <BCard class="mb-3">
          <template #header>
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
              <div class="fw-semibold">Filtros de autorizaciones</div>
              <InfirmaryHelpButton
                title="Ayuda: filtros de autorizaciones"
                text="Filtra por estudiante, medicamento, vigencia o texto libre para revisar tratamientos permanentes."
              />
            </div>
          </template>
          <div class="row g-3">
            <div class="col-md-5">
              <label class="form-label">Buscar</label>
              <BFormInput v-model="filters.search" placeholder="Diagnóstico, médico o estudiante" @keyup.enter="loadAuthorizations(1)" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Medicamento</label>
              <BFormSelect v-model="filters.medication_id" :options="medicationOptions" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Estado</label>
              <BFormSelect v-model="filters.status" :options="statusOptions" />
            </div>
            <div class="col-12">
              <InfirmaryStudentSearch button-label="Filtrar estudiante" @selected="selectStudent" />
            </div>
            <div class="col-12 d-flex gap-2">
              <BButton variant="primary" @click="loadAuthorizations(1)">Aplicar</BButton>
              <BButton variant="outline-secondary" @click="resetFilters">Limpiar</BButton>
            </div>
          </div>
        </BCard>

        <BCard>
          <template #header>
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
              <div class="fw-semibold">Autorizaciones vigentes e históricas</div>
              <InfirmaryHelpButton
                title="Ayuda: lista de autorizaciones"
                text="La tabla centraliza diagnósticos, medicamento, dosis, estado, vigencias y acceso directo a la administración."
              />
            </div>
          </template>

          <LoadingState v-if="loading" message="Cargando autorizaciones..." compact />

          <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Estudiante</th>
                  <th>Medicamento</th>
                  <th>Dosis</th>
                  <th>Vigencia</th>
                  <th>Estado</th>
                  <th class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="authorization in authorizations"
                  :key="authorization.id"
                  :class="{ 'table-active': selectedAuthorization?.id === authorization.id }"
                  role="button"
                  @click="openAuthorization(authorization)"
                >
                  <td>{{ authorization.student?.first_name }} {{ authorization.student?.last_name }}</td>
                  <td>{{ authorization.medication?.commercial_name || authorization.medication?.name }}</td>
                  <td>{{ authorization.dose }}</td>
                  <td>{{ formatInfirmaryDate(authorization.start_date) }} - {{ formatInfirmaryDate(authorization.end_date) }}</td>
                  <td><InfirmaryStatusBadge :status="authorization.status" /></td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <BButton v-if="canManage" variant="outline-primary" @click.stop="openEdit(authorization)">Editar</BButton>
                      <BButton v-if="canManage" variant="outline-success" @click.stop="openAdministration(authorization)">Administrar</BButton>
                      <BButton v-if="canManage" variant="outline-danger" @click.stop="remove(authorization)">Eliminar</BButton>
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
              @update:model-value="loadAuthorizations"
            />
          </div>
        </BCard>
      </div>

      <div class="col-xl-4">
        <InfirmaryStudentContextCard :student="selectedStudentContext" class="mb-3" />

        <BCard class="mb-3">
          <template #header>
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
              <div class="fw-semibold">Detalle de la autorización</div>
              <InfirmaryHelpButton
                title="Ayuda: detalle de la autorización"
                text="Aquí se revisan diagnóstico, dosis, horarios, vigencias, stock disponible y documentación de respaldo."
              />
            </div>
          </template>

          <div v-if="!selectedAuthorization" class="text-muted">Selecciona una autorización para revisar su detalle.</div>
          <div v-else>
            <div class="fw-semibold fs-5">{{ selectedAuthorization.medication?.commercial_name || selectedAuthorization.medication?.name }}</div>
            <div class="text-muted small mb-3">{{ selectedAuthorization.diagnosis || "Sin diagnóstico" }}</div>
            <div class="mb-2"><span class="text-muted">Dosis:</span> {{ selectedAuthorization.dose }}</div>
            <div class="mb-2"><span class="text-muted">Frecuencia:</span> {{ selectedAuthorization.frequency || "-" }}</div>
            <div class="mb-2"><span class="text-muted">Horario:</span> {{ selectedAuthorization.schedule_text || "-" }}</div>
            <div class="mb-2"><span class="text-muted">Médico tratante:</span> {{ selectedAuthorization.physician_name || "-" }}</div>
            <div class="mb-2"><span class="text-muted">Inicio:</span> {{ formatInfirmaryDate(selectedAuthorization.start_date) }}</div>
            <div class="mb-2"><span class="text-muted">Término:</span> {{ formatInfirmaryDate(selectedAuthorization.end_date) }}</div>
            <div class="mb-2"><span class="text-muted">Autorización médica:</span> {{ formatInfirmaryDate(selectedAuthorization.medical_authorization_expires_at) }}</div>
            <div class="mb-2"><span class="text-muted">Autorización apoderado:</span> {{ formatInfirmaryDate(selectedAuthorization.guardian_authorization_expires_at) }}</div>
            <div class="mb-2"><span class="text-muted">Stock disponible:</span> {{ selectedAuthorization.medication?.current_stock || 0 }} {{ selectedAuthorization.medication?.unit || "" }}</div>
            <div class="mb-2"><span class="text-muted">Estado:</span> <InfirmaryStatusBadge :status="selectedAuthorization.status" /></div>
            <div><span class="text-muted">Observaciones:</span> {{ selectedAuthorization.observations || "Sin observaciones." }}</div>
          </div>
        </BCard>

        <BCard class="mb-3" v-if="selectedAuthorization">
          <h5 class="mb-3">Administraciones registradas</h5>
          <div v-if="!(selectedAuthorization.administrations || []).length" class="text-muted">
            No hay administraciones registradas.
          </div>
          <ul v-else class="list-group list-group-flush">
            <li v-for="item in selectedAuthorization.administrations" :key="item.id" class="list-group-item px-0">
              <div class="d-flex justify-content-between gap-2">
                <div>{{ formatInfirmaryDateTime(item.administered_at) }}</div>
                <div class="fw-semibold">{{ item.quantity_administered }}</div>
              </div>
              <div class="small text-muted">{{ item.administered_by?.name || "-" }} · {{ item.schedule_reference || "Sin horario" }}</div>
            </li>
          </ul>
        </BCard>

        <InfirmaryDocumentPanel
          v-if="selectedAuthorization"
          :documents="selectedAuthorization.documents || []"
          :upload-url="`/api/infirmary/medication-authorizations/${selectedAuthorization.id}/documents`"
          :student-id="selectedAuthorization.student_profile_id"
          :categories="catalogs.document_categories || []"
          title="Adjuntos clínicos"
          help-text="Adjunta receta, autorización médica, autorización del apoderado u otros respaldos del tratamiento."
          @refresh="openAuthorization(selectedAuthorization)"
        />
      </div>
    </div>

    <BModal v-model="showModal" :title="isEditing ? 'Editar autorización' : 'Nueva autorización médica'" size="xl" hide-footer>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Buscar estudiante</label>
          <InfirmaryStudentSearch button-label="Seleccionar" @selected="selectFormStudent" />
          <div v-if="form.student_label" class="small text-muted mt-2">Seleccionada: {{ form.student_label }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Medicamento</label>
          <BFormSelect v-model="form.medication_id" :options="medicationOptions.filter((item) => item.value !== null)" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Diagnóstico</label>
          <BFormInput v-model="form.diagnosis" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Dosis</label>
          <BFormInput v-model="form.dose" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Frecuencia</label>
          <BFormInput v-model="form.frequency" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Horario</label>
          <BFormInput v-model="form.schedule_text" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Fecha inicio</label>
          <BFormInput v-model="form.start_date" type="date" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Fecha término</label>
          <BFormInput v-model="form.end_date" type="date" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Vence autorización médica</label>
          <BFormInput v-model="form.medical_authorization_expires_at" type="date" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Vence autorización apoderado</label>
          <BFormInput v-model="form.guardian_authorization_expires_at" type="date" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Médico tratante</label>
          <BFormInput v-model="form.physician_name" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="form.status" :options="normalizeOptions(['vigente', 'proxima_a_vencer', 'vencida', 'terminada'])" />
        </div>
        <div class="col-12">
          <label class="form-label">Observaciones</label>
          <BFormTextarea v-model="form.observations" rows="3" />
        </div>
        <div class="col-12 d-flex justify-content-end gap-2">
          <BButton variant="outline-secondary" @click="cancelModal">Cancelar</BButton>
          <BButton variant="primary" :disabled="saving" @click="save">
            {{ saving ? "Guardando..." : isEditing ? "Guardar cambios" : "Registrar autorización" }}
          </BButton>
        </div>
      </div>
    </BModal>

    <BModal v-model="showAdministrationModal" title="Registrar administración" size="lg" hide-footer>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Fecha y hora</label>
          <BFormInput v-model="administrationForm.administered_at" type="datetime-local" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Funcionario</label>
          <BFormSelect v-model="administrationForm.administered_by_user_id" :options="userOptions" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Medicamento</label>
          <BFormSelect v-model="administrationForm.medication_id" :options="medicationOptions.filter((item) => item.value !== null)" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Cantidad administrada</label>
          <BFormInput v-model="administrationForm.quantity_administered" type="number" min="0.01" step="0.01" />
        </div>
        <div class="col-12">
          <label class="form-label">Referencia de horario</label>
          <BFormInput v-model="administrationForm.schedule_reference" />
        </div>
        <div class="col-12">
          <label class="form-label">Observaciones</label>
          <BFormTextarea v-model="administrationForm.observations" rows="3" />
        </div>
        <div class="col-12 d-flex justify-content-end gap-2">
          <BButton variant="outline-secondary" @click="cancelAdministrationModal">Cancelar</BButton>
          <BButton variant="primary" :disabled="administering" @click="saveAdministration">
            {{ administering ? "Registrando..." : "Registrar administración" }}
          </BButton>
        </div>
      </div>
    </BModal>
  </Layout>
</template>
