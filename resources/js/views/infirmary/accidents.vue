<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import InfirmaryDocumentPanel from "../../components/infirmary/document-panel.vue";
import InfirmaryHelpButton from "../../components/infirmary/help-button.vue";
import InfirmaryStatusBadge from "../../components/infirmary/status-badge.vue";
import InfirmaryStudentSearch from "../../components/infirmary/student-search.vue";
import {
  confirmInfirmaryAction,
  confirmInfirmaryCancel,
  formatInfirmaryDateTime,
  formatInfirmaryError,
  normalizeOptions,
  showInfirmaryError,
  showInfirmarySuccess,
  showInfirmaryWarning,
  toInputDateTime,
} from "../../components/infirmary/module-utils";
import { getPdfMake } from "../../utils/pdfmake";

export default {
  components: {
    Layout,
    LoadingState,
    InfirmaryDocumentPanel,
    InfirmaryHelpButton,
    InfirmaryStatusBadge,
    InfirmaryStudentSearch,
  },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      catalogs: {
        dependencies: [],
        staff: [],
        accident_severity_options: [],
        accident_status_options: [],
        call_status_options: [],
      },
      filters: {
        search: "",
        student_profile_id: null,
        accident_type: "",
        case_status: null,
        from: "",
        to: "",
      },
      accidents: [],
      pagination: { current_page: 1, total: 0, per_page: 12 },
      selectedAccident: null,
      showModal: false,
      form: this.emptyForm(),
    };
  },
  computed: {
    severityOptions() {
      return normalizeOptions(this.catalogs.accident_severity_options);
    },
    statusOptions() {
      return normalizeOptions(this.catalogs.accident_status_options, true);
    },
    callStatusOptions() {
      return normalizeOptions(this.catalogs.call_status_options, true);
    },
    dependencyOptions() {
      return [{ value: null, text: "Sin dependencia" }].concat(
        (this.catalogs.dependencies || []).map((item) => ({
          value: item.id,
          text: item.name,
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
    isEditing() {
      return Boolean(this.form.id);
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadAccidents();
    this.applyRoutePrefill();
  },
  methods: {
    formatInfirmaryDateTime,
    normalizeOptions,
    emptyForm() {
      return {
        id: null,
        attention_id: null,
        student_profile_id: null,
        student_label: "",
        occurred_at: toInputDateTime(new Date().toISOString()),
        dependency_id: null,
        accident_type: "",
        place: "",
        activity: "",
        description: "",
        witnesses: "",
        present_staff_id: null,
        severity: "leve",
        observed_injuries: "",
        first_aid: "",
        guardian_call_status: "pendiente",
        referral_destination: "",
        school_insurance: true,
        diat_number: "",
        diat_generated_at: "",
        observations: "",
        case_status: "abierto",
      };
    },
    async loadCatalogs() {
      const response = await axios.get("/api/infirmary/catalogs");
      this.catalogs = response.data;
    },
    async loadAccidents(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/infirmary/accidents", {
          params: {
            page,
            ...this.filters,
          },
        });
        this.accidents = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page || 1,
          total: response.data.total || 0,
          per_page: response.data.per_page || 12,
        };

        if (this.accidents[0]) {
          await this.openAccident(this.accidents[0]);
        } else {
          this.selectedAccident = null;
        }
      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudo cargar el registro de accidentes.");
      } finally {
        this.loading = false;
      }
    },
    async openAccident(accident) {
      try {
        const response = await axios.get(`/api/infirmary/accidents/${accident.id}`);
        this.selectedAccident = response.data.data;
      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudo cargar el detalle del accidente.");
      }
    },
    selectStudent(student) {
      this.filters.student_profile_id = student.id;
      this.loadAccidents(1);
    },
    selectFormStudent(student) {
      this.form.student_profile_id = student.id;
      this.form.student_label = student.full_name;
    },
    applyRoutePrefill() {
      const query = this.$route.query || {};
      if (query.student_id || query.attention_id) {
        this.form = {
          ...this.emptyForm(),
          student_profile_id: query.student_id ? Number(query.student_id) : null,
          attention_id: query.attention_id ? Number(query.attention_id) : null,
        };
        this.showModal = true;
      }
    },
    resetFilters() {
      this.filters = {
        search: "",
        student_profile_id: null,
        accident_type: "",
        case_status: null,
        from: "",
        to: "",
      };
      this.loadAccidents(1);
    },
    openCreate() {
      this.form = this.emptyForm();
      this.showModal = true;
    },
    openEdit(accident) {
      this.form = {
        id: accident.id,
        attention_id: accident.attention_id,
        student_profile_id: accident.student_profile_id,
        student_label: accident.student ? `${accident.student.first_name} ${accident.student.last_name}` : "",
        occurred_at: toInputDateTime(accident.occurred_at),
        dependency_id: accident.dependency_id,
        accident_type: accident.accident_type || "",
        place: accident.place || "",
        activity: accident.activity || "",
        description: accident.description || "",
        witnesses: accident.witnesses || "",
        present_staff_id: accident.present_staff_id,
        severity: accident.severity || "leve",
        observed_injuries: accident.observed_injuries || "",
        first_aid: accident.first_aid || "",
        guardian_call_status: accident.guardian_call_status || "pendiente",
        referral_destination: accident.referral_destination || "",
        school_insurance: Boolean(accident.school_insurance),
        diat_number: accident.diat_number || "",
        diat_generated_at: toInputDateTime(accident.diat_generated_at),
        observations: accident.observations || "",
        case_status: accident.case_status || "abierto",
      };
      this.showModal = true;
    },
    async cancelModal() {
      const result = await confirmInfirmaryCancel("los cambios del accidente");
      if (result.isConfirmed) {
        this.showModal = false;
      }
    },
    async save() {
      if (!this.form.student_profile_id) {
        await showInfirmaryWarning("Debes seleccionar una estudiante.");
        return;
      }

      this.saving = true;
      try {
        const payload = {
          attention_id: this.form.attention_id || null,
          student_profile_id: this.form.student_profile_id,
          dependency_id: this.form.dependency_id || null,
          occurred_at: this.form.occurred_at,
          accident_type: this.form.accident_type,
          place: this.form.place || null,
          activity: this.form.activity || null,
          description: this.form.description,
          witnesses: this.form.witnesses || null,
          present_staff_id: this.form.present_staff_id || null,
          severity: this.form.severity,
          observed_injuries: this.form.observed_injuries || null,
          first_aid: this.form.first_aid || null,
          guardian_call_status: this.form.guardian_call_status || null,
          referral_destination: this.form.referral_destination || null,
          school_insurance: this.form.school_insurance,
          diat_number: this.form.diat_number || null,
          diat_generated_at: this.form.diat_generated_at || null,
          observations: this.form.observations || null,
          case_status: this.form.case_status,
        };

        if (this.isEditing) {
          await axios.put(`/api/infirmary/accidents/${this.form.id}`, payload);
        } else {
          await axios.post("/api/infirmary/accidents", payload);
        }

        this.showModal = false;
        await this.loadAccidents(this.pagination.current_page || 1);
        await showInfirmarySuccess(this.isEditing ? "Accidente actualizado correctamente." : "Accidente registrado correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo guardar el accidente."));
      } finally {
        this.saving = false;
      }
    },
    async remove(accident) {
      const confirmation = await confirmInfirmaryAction({
        title: "Eliminar accidente",
        text: `Se eliminará el accidente ${accident.accident_type}.`,
        confirmButtonText: "Eliminar",
      });

      if (!confirmation.isConfirmed) return;

      try {
        await axios.delete(`/api/infirmary/accidents/${accident.id}`);
        await this.loadAccidents(this.pagination.current_page || 1);
        await showInfirmarySuccess("Accidente eliminado correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo eliminar el accidente."));
      }
    },
    generateDiatPdf(accident = this.selectedAccident) {
      if (!accident) return;

      const pdfMake = getPdfMake();
      pdfMake.createPdf({
        content: [
          { text: "Formulario DIAT Escolar", style: "title" },
          { text: `Generado el ${formatInfirmaryDateTime(new Date())}`, style: "muted" },
          {
            table: {
              widths: ["35%", "*"],
              body: [
                ["Estudiante", `${accident.student?.first_name || ""} ${accident.student?.last_name || ""}`.trim()],
                ["RUT", accident.student?.rut || "-"],
                ["Fecha del accidente", formatInfirmaryDateTime(accident.occurred_at)],
                ["Tipo", accident.accident_type || "-"],
                ["Lugar", accident.place || accident.dependency?.name || "-"],
                ["Actividad", accident.activity || "-"],
                ["Severidad", accident.severity || "-"],
                ["Lesiones observadas", accident.observed_injuries || "-"],
                ["Primeros auxilios", accident.first_aid || "-"],
                ["Derivación", accident.referral_destination || "-"],
                ["Seguro escolar", accident.school_insurance ? "Sí" : "No"],
                ["DIAT", accident.diat_number || "Pendiente"],
                ["Observaciones", accident.observations || "-"],
              ],
            },
            layout: "lightHorizontalLines",
            margin: [0, 12, 0, 0],
          },
        ],
        styles: {
          title: { fontSize: 18, bold: true },
          muted: { fontSize: 10, color: "#74788d" },
        },
        defaultStyle: { fontSize: 10 },
      }).download(`diat_${accident.id}.pdf`);
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Accidentes escolares</h4>
        <div class="text-muted">
          Registro de accidentes, primeros auxilios, seguro escolar, DIAT y respaldos asociados.
        </div>
      </div>
      <div class="d-flex gap-2">
        <InfirmaryHelpButton
          title="Ayuda: accidentes escolares"
          text="En esta pantalla se registran accidentes escolares, su gravedad, atención inicial, derivación, seguro escolar y la generación posterior del DIAT en PDF."
        />
        <BButton variant="primary" @click="openCreate">Registrar accidente</BButton>
      </div>
    </div>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-xl-7">
          <InfirmaryStudentSearch @selected="selectStudent" />
        </div>
        <div class="col-xl-5 d-flex align-items-center">
          <div class="text-muted small">Puedes iniciar el registro de accidente desde una atención o buscar a la estudiante directamente.</div>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

    <div class="row g-3">
      <div class="col-xl-8">
        <BCard class="mb-3">
          <template #header>
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
              <div class="fw-semibold">Filtros</div>
              <InfirmaryHelpButton
                title="Ayuda: filtros de accidentes"
                text="Filtra accidentes por estudiante, tipo, estado o rango de fechas para facilitar el seguimiento."
              />
            </div>
          </template>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Buscar</label>
              <BFormInput v-model="filters.search" placeholder="Tipo, lugar o estudiante" @keyup.enter="loadAccidents(1)" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Tipo de accidente</label>
              <BFormInput v-model="filters.accident_type" placeholder="Caída, golpe..." />
            </div>
            <div class="col-md-2">
              <label class="form-label">Estado</label>
              <BFormSelect v-model="filters.case_status" :options="statusOptions" />
            </div>
            <div class="col-md-2">
              <label class="form-label">Desde</label>
              <BFormInput v-model="filters.from" type="date" />
            </div>
            <div class="col-md-1 d-flex align-items-end">
              <BButton variant="primary" class="w-100" @click="loadAccidents(1)">Ir</BButton>
            </div>
            <div class="col-md-2">
              <label class="form-label">Hasta</label>
              <BFormInput v-model="filters.to" type="date" />
            </div>
            <div class="col-12 d-flex gap-2">
              <BButton variant="outline-secondary" @click="resetFilters">Limpiar</BButton>
            </div>
          </div>
        </BCard>

        <BCard>
          <template #header>
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
              <div class="fw-semibold">Registro de accidentes</div>
              <InfirmaryHelpButton
                title="Ayuda: registro de accidentes"
                text="La tabla centraliza el detalle de accidentes escolares con su severidad, lugar, estado y acceso rápido al DIAT."
              />
            </div>
          </template>

          <LoadingState v-if="loading" message="Cargando accidentes..." compact />

          <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Estudiante</th>
                  <th>Tipo</th>
                  <th>Severidad</th>
                  <th>Estado</th>
                  <th class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="accident in accidents"
                  :key="accident.id"
                  :class="{ 'table-active': selectedAccident?.id === accident.id }"
                  role="button"
                  @click="openAccident(accident)"
                >
                  <td>{{ formatInfirmaryDateTime(accident.occurred_at) }}</td>
                  <td>{{ accident.student?.first_name }} {{ accident.student?.last_name }}</td>
                  <td>{{ accident.accident_type }}</td>
                  <td><InfirmaryStatusBadge :status="accident.severity" /></td>
                  <td><InfirmaryStatusBadge :status="accident.case_status" /></td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <BButton variant="outline-primary" @click.stop="openEdit(accident)">Editar</BButton>
                      <BButton variant="outline-secondary" @click.stop="generateDiatPdf(accident)">DIAT PDF</BButton>
                      <BButton variant="outline-danger" @click.stop="remove(accident)">Eliminar</BButton>
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
              @update:model-value="loadAccidents"
            />
          </div>
        </BCard>
      </div>

      <div class="col-xl-4">
        <BCard class="mb-3">
          <template #header>
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
              <div class="fw-semibold">Detalle del accidente</div>
              <InfirmaryHelpButton
                title="Ayuda: detalle del accidente"
                text="Aquí se visualiza el accidente seleccionado con severidad, primeros auxilios, derivación, datos del apoderado y trazabilidad del caso."
              />
            </div>
          </template>

          <div v-if="!selectedAccident" class="text-muted">Selecciona un accidente para ver su detalle.</div>
          <div v-else>
            <div class="fw-semibold fs-5">{{ selectedAccident.accident_type }}</div>
            <div class="text-muted small mb-3">
              {{ selectedAccident.student?.first_name }} {{ selectedAccident.student?.last_name }} · {{ selectedAccident.student?.rut || "Sin RUT" }}
            </div>
            <div class="mb-2"><span class="text-muted">Fecha:</span> {{ formatInfirmaryDateTime(selectedAccident.occurred_at) }}</div>
            <div class="mb-2"><span class="text-muted">Lugar:</span> {{ selectedAccident.place || selectedAccident.dependency?.name || "-" }}</div>
            <div class="mb-2"><span class="text-muted">Actividad:</span> {{ selectedAccident.activity || "-" }}</div>
            <div class="mb-2"><span class="text-muted">Severidad:</span> <InfirmaryStatusBadge :status="selectedAccident.severity" /></div>
            <div class="mb-2"><span class="text-muted">Estado:</span> <InfirmaryStatusBadge :status="selectedAccident.case_status" /></div>
            <div class="mb-2"><span class="text-muted">Lesiones:</span> {{ selectedAccident.observed_injuries || "-" }}</div>
            <div class="mb-2"><span class="text-muted">Primeros auxilios:</span> {{ selectedAccident.first_aid || "-" }}</div>
            <div class="mb-2"><span class="text-muted">Derivación:</span> {{ selectedAccident.referral_destination || "-" }}</div>
            <div class="mb-2"><span class="text-muted">Seguro escolar:</span> {{ selectedAccident.school_insurance ? "Sí" : "No" }}</div>
            <div class="mb-2"><span class="text-muted">DIAT:</span> {{ selectedAccident.diat_number || "Pendiente" }}</div>
            <div class="mb-2"><span class="text-muted">Llamado apoderado:</span> <InfirmaryStatusBadge :status="selectedAccident.guardian_call_status" /></div>
            <div class="mb-2"><span class="text-muted">Apoderado:</span> {{ selectedAccident.student?.guardian_name || "-" }}</div>
            <div class="mb-2"><span class="text-muted">Teléfono:</span> {{ selectedAccident.student?.guardian_phone || "-" }}</div>
            <div><span class="text-muted">Observaciones:</span> {{ selectedAccident.observations || "Sin observaciones." }}</div>
          </div>
        </BCard>

        <InfirmaryDocumentPanel
          v-if="selectedAccident"
          :documents="selectedAccident.documents || []"
          :upload-url="`/api/infirmary/accidents/${selectedAccident.id}/documents`"
          :student-id="selectedAccident.student_profile_id"
          :categories="catalogs.document_categories || []"
          title="Archivos del accidente"
          help-text="Adjunta fotografías, órdenes de atención, certificados o respaldos del accidente escolar."
          @refresh="openAccident(selectedAccident)"
        />
      </div>
    </div>

    <BModal v-model="showModal" :title="isEditing ? 'Editar accidente' : 'Registrar accidente'" size="xl" hide-footer>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Buscar estudiante</label>
          <InfirmaryStudentSearch button-label="Seleccionar" @selected="selectFormStudent" />
          <div v-if="form.student_label" class="small text-muted mt-2">Seleccionada: {{ form.student_label }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Fecha y hora</label>
          <BFormInput v-model="form.occurred_at" type="datetime-local" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Tipo de accidente</label>
          <BFormInput v-model="form.accident_type" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Gravedad</label>
          <BFormSelect v-model="form.severity" :options="severityOptions" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Dependencia</label>
          <BFormSelect v-model="form.dependency_id" :options="dependencyOptions" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Lugar</label>
          <BFormInput v-model="form.place" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Actividad</label>
          <BFormInput v-model="form.activity" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Funcionario presente</label>
          <BFormSelect v-model="form.present_staff_id" :options="staffOptions" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Estado del caso</label>
          <BFormSelect v-model="form.case_status" :options="normalizeOptions(catalogs.accident_status_options)" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Llamado al apoderado</label>
          <BFormSelect v-model="form.guardian_call_status" :options="callStatusOptions" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Atención asociada</label>
          <BFormInput v-model="form.attention_id" type="number" min="1" placeholder="Opcional" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Derivación</label>
          <BFormInput v-model="form.referral_destination" placeholder="SAPU, CESFAM, hospital..." />
        </div>
        <div class="col-12">
          <label class="form-label">Descripción</label>
          <BFormTextarea v-model="form.description" rows="3" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Testigos</label>
          <BFormTextarea v-model="form.witnesses" rows="2" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Lesiones observadas</label>
          <BFormTextarea v-model="form.observed_injuries" rows="2" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Primeros auxilios aplicados</label>
          <BFormTextarea v-model="form.first_aid" rows="2" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Observaciones</label>
          <BFormTextarea v-model="form.observations" rows="2" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Número DIAT</label>
          <BFormInput v-model="form.diat_number" placeholder="Opcional" />
        </div>
        <div class="col-md-4">
          <label class="form-label">DIAT generado</label>
          <BFormInput v-model="form.diat_generated_at" type="datetime-local" />
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <div class="form-check">
            <input id="school_insurance" v-model="form.school_insurance" class="form-check-input" type="checkbox" />
            <label class="form-check-label" for="school_insurance">Aplica seguro escolar</label>
          </div>
        </div>
        <div class="col-12 d-flex justify-content-end gap-2">
          <BButton variant="outline-secondary" @click="cancelModal">Cancelar</BButton>
          <BButton variant="primary" :disabled="saving" @click="save">
            {{ saving ? "Guardando..." : isEditing ? "Guardar cambios" : "Registrar accidente" }}
          </BButton>
        </div>
      </div>
    </BModal>
  </Layout>
</template>
