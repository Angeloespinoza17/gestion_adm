<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import PorterActionDeck from "../../components/porter/action-deck.vue";
import PorterModuleHeader from "../../components/porter/module-header.vue";
import PorterStatusBadge from "../../components/porter/status-badge.vue";

const emptyForm = () => ({
  student_profile_id: null,
  person_name: "",
  person_rut: "",
  person_relationship: "apoderado",
  person_phone: "",
  reason: "otro",
  observations: "",
  attachment: null,
  force_duplicate_confirmation: false,
  approve_override: false,
  override_reason: "",
});

export default {
  components: { Layout, LoadingState, PorterActionDeck, PorterModuleHeader, PorterStatusBadge },
  data() {
    return {
      loadingCatalogs: false,
      saving: false,
      loadingList: false,
      loadingStudent: false,
      showWithdrawalModal: false,
      error: null,
      catalogs: {
        withdrawal_relationships: [],
        withdrawal_reasons: [],
        withdrawal_statuses: [],
        capabilities: {},
      },
      form: emptyForm(),
      studentSearch: "",
      studentOptions: [],
      selectedStudent: null,
      withdrawals: [],
      listFilters: {
        search: "",
        status: null,
        reason: null,
      },
      pagination: { current_page: 1, total: 0, per_page: 15 },
    };
  },
  computed: {
    relationshipOptions() {
      return (this.catalogs.withdrawal_relationships || []).map((item) => ({
        value: item.value,
        text: item.label,
      }));
    },
    reasonOptions() {
      return (this.catalogs.withdrawal_reasons || []).map((item) => ({
        value: item.value,
        text: item.label,
      }));
    },
    statusOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.withdrawal_statuses || []).map((item) => ({
          value: item.value,
          text: item.label,
        }))
      );
    },
    studentSelectOptions() {
      const options = [...(this.studentOptions || [])];
      if (this.selectedStudent && !options.some((item) => Number(item.id) === Number(this.selectedStudent.id))) {
        options.unshift(this.selectedStudent);
      }

      return [{ value: null, text: "Seleccionar..." }].concat(
        options.map((item) => ({
          value: item.id,
          text: `${item.full_name} - ${item.current_enrollment?.course_name || "Sin curso"}`,
        }))
      );
    },
    authorizedPeople() {
      return this.selectedStudent?.authorized_pickup_people || [];
    },
    studentAlerts() {
      return this.selectedStudent?.alerts || [];
    },
    recentWithdrawals() {
      return this.selectedStudent?.withdrawal_history || this.selectedStudent?.recent_withdrawals || [];
    },
    historyFields() {
      return [
        { key: "student", label: "Estudiante" },
        { key: "person_name", label: "Retira" },
        { key: "reason", label: "Motivo" },
        { key: "status", label: "Estado" },
        { key: "withdrawn_at", label: "Fecha" },
        { key: "actions", label: "Acciones", thClass: "text-end", tdClass: "text-end" },
      ];
    },
  },
  watch: {
    "$route.query.student_id": {
      async handler(id) {
        if (id && Number(id) !== Number(this.form.student_profile_id)) {
          await this.loadStudentById(id);
          this.showWithdrawalModal = true;
        }
      },
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadWithdrawals();
    if (this.$route.query.student_id) {
      await this.loadStudentById(this.$route.query.student_id);
      this.showWithdrawalModal = true;
    }
  },
  methods: {
    openWithdrawalModal() {
      this.error = null;
      this.showWithdrawalModal = true;
    },
    async requestCloseWithdrawalModal() {
      if (this.saving) return;

      const hasProgress = Boolean(
        this.form.student_profile_id ||
        this.form.person_name ||
        this.form.person_rut ||
        this.form.person_phone ||
        this.form.observations ||
        this.form.attachment
      );

      if (hasProgress) {
        const { isConfirmed } = await Swal.fire({
          title: "¿Cerrar el formulario?",
          text: "Los datos ingresados para este retiro se descartarán.",
          icon: "warning",
          showCancelButton: true,
          confirmButtonText: "Sí, descartar",
          cancelButtonText: "Continuar registrando",
          reverseButtons: true,
        });

        if (!isConfirmed) return;
      }

      this.showWithdrawalModal = false;
      this.resetFormAfterSubmit();
    },
    async loadCatalogs() {
      this.loadingCatalogs = true;
      try {
        const response = await axios.get("/api/porter/catalogs");
        this.catalogs = response.data;
      } finally {
        this.loadingCatalogs = false;
      }
    },
    async searchStudents() {
      if (!this.studentSearch.trim()) {
        this.studentOptions = this.selectedStudent ? [this.selectedStudent] : [];
        return;
      }

      const response = await axios.get("/api/porter/students", {
        params: {
          search: this.studentSearch,
          per_page: 8,
        },
      });

      this.studentOptions = response.data.data || [];
    },
    async loadStudentById(id) {
      if (!id) return;
      this.loadingStudent = true;
      this.error = null;
      try {
        const response = await axios.get(`/api/porter/students/${id}`);
        this.setSelectedStudent(response.data.data);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loadingStudent = false;
      }
    },
    setSelectedStudent(student) {
      if (!student) return;
      this.selectedStudent = student;
      this.form.student_profile_id = student.id;
      this.studentSearch = student.full_name || "";
      this.studentOptions = [
        student,
        ...(this.studentOptions || []).filter((item) => Number(item.id) !== Number(student.id)),
      ];
    },
    async selectStudent(id) {
      if (!id) {
        this.clearSelectedStudent();
        return;
      }

      await this.loadStudentById(id);
    },
    clearSelectedStudent() {
      this.selectedStudent = null;
      this.form.student_profile_id = null;
      this.studentSearch = "";
      this.studentOptions = [];

      if (this.$route.query.student_id) {
        const query = { ...this.$route.query };
        delete query.student_id;
        this.$router.replace({ query });
      }
    },
    applyAuthorizedPerson(person) {
      this.form.person_name = person.name || "";
      this.form.person_rut = person.rut || "";
      this.form.person_phone = person.phone || "";
      this.form.person_relationship = this.relationshipValue(person.relationship, person.source);
    },
    relationshipValue(relationship, source = null) {
      const normalized = String(relationship || source || "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase();

      if (normalized.includes("madre")) return "madre";
      if (normalized.includes("padre")) return "padre";
      if (normalized.includes("apoderado")) return "apoderado";
      if (normalized.includes("transporte")) return "transporte";
      if (normalized.includes("abu") || normalized.includes("tio") || normalized.includes("tia") || normalized.includes("familiar")) return "familiar";
      return "otro";
    },
    onFileChange(event) {
      this.form.attachment = event?.target?.files?.[0] || null;
    },
    resetFormAfterSubmit() {
      this.form = emptyForm();
      this.clearSelectedStudent();
      const input = this.$refs.attachmentInput?.$el?.querySelector?.("input") || this.$refs.attachmentInput;
      if (input) {
        input.value = "";
      }
    },
    validateWithdrawalForm() {
      const issues = [];

      if (!this.form.student_profile_id) {
        issues.push("Selecciona una estudiante.");
      }

      if (!this.form.person_name.trim()) {
        issues.push("Ingresa el nombre de la persona que retira.");
      }

      if (!this.form.person_relationship) {
        issues.push("Selecciona la relación con la estudiante.");
      }

      if (!this.form.reason) {
        issues.push("Selecciona el motivo del retiro.");
      }

      if (
        this.form.approve_override &&
        this.catalogs.capabilities?.can_authorize_special_withdrawal &&
        !this.form.override_reason.trim()
      ) {
        issues.push("Indica el motivo de autorización especial.");
      }

      return issues;
    },
    async showValidationAlert(issues) {
      await Swal.fire({
        title: "Faltan datos para registrar",
        html: `<div class="text-start">${issues.map((issue) => `<div>&bull; ${this.escapeHtml(issue)}</div>`).join("")}</div>`,
        icon: "warning",
        confirmButtonText: "Entendido",
      });
    },
    async confirmWithdrawalSubmit() {
      const studentName = this.selectedStudent?.full_name || "Sin estudiante";
      const personName = this.form.person_name || "Sin persona";

      const { isConfirmed } = await Swal.fire({
        title: "Registrar retiro",
        html: `
          <div class="text-start">
            <div><strong>Estudiante:</strong> ${this.escapeHtml(studentName)}</div>
            <div><strong>Retira:</strong> ${this.escapeHtml(personName)}</div>
            <div><strong>Motivo:</strong> ${this.escapeHtml(this.reasonLabel(this.form.reason))}</div>
          </div>
        `,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Registrar retiro",
        cancelButtonText: "Cancelar",
        reverseButtons: true,
      });

      return isConfirmed;
    },
    async showSubmitError(error) {
      const message = this.formatError(error);
      this.error = message;

      await Swal.fire({
        title: "No se pudo registrar",
        text: message,
        icon: "error",
        confirmButtonText: "Revisar",
      });
    },
    async handleDuplicateWithdrawal(error) {
      const duplicateError = error?.response?.data?.errors?.force_duplicate_confirmation?.[0];
      if (!duplicateError) return false;

      const { isConfirmed } = await Swal.fire({
        title: "Retiro duplicado reciente",
        text: "Ya existe un retiro reciente para esta estudiante. Puedes confirmarlo como duplicado si corresponde.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Registrar duplicado",
        cancelButtonText: "Revisar",
        reverseButtons: true,
      });

      if (!isConfirmed) {
        this.error = duplicateError;
        return true;
      }

      this.form.force_duplicate_confirmation = true;
      this.saving = false;
      await this.submit({ skipConfirm: true });
      return true;
    },
    async submit(options = {}) {
      if (this.saving) return;

      const issues = this.validateWithdrawalForm();
      if (issues.length) {
        await this.showValidationAlert(issues);
        return;
      }

      if (!options.skipConfirm) {
        const confirmed = await this.confirmWithdrawalSubmit();
        if (!confirmed) return;
      }

      this.saving = true;
      this.error = null;

      try {
        const formData = new FormData();
        Object.entries(this.form).forEach(([key, value]) => {
          if (value === null || value === undefined || value === "") {
            return;
          }

          if (key === "attachment" && value) {
            formData.append("attachment", value);
            return;
          }

          formData.append(key, value);
        });

        const response = await axios.post("/api/porter/withdrawals", formData, {
          headers: { "Content-Type": "multipart/form-data" },
        });

        await Swal.fire({
          title: "Retiro registrado",
          text: response.data.message,
          icon: "success",
          timer: 1800,
          showConfirmButton: false,
        });

        this.resetFormAfterSubmit();
        this.showWithdrawalModal = false;
        await this.loadWithdrawals(1);
      } catch (error) {
        const duplicateHandled = await this.handleDuplicateWithdrawal(error);
        if (!duplicateHandled) {
          await this.showSubmitError(error);
        }
      } finally {
        this.saving = false;
      }
    },
    async loadWithdrawals(page = 1) {
      this.loadingList = true;
      this.error = null;
      try {
        const response = await axios.get("/api/porter/withdrawals", {
          params: {
            page,
            search: this.listFilters.search || null,
            status: this.listFilters.status,
            reason: this.listFilters.reason,
          },
        });

        this.withdrawals = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page || 1,
          total: response.data.total || 0,
          per_page: response.data.per_page || 15,
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loadingList = false;
      }
    },
    async resolve(item) {
      const { value } = await Swal.fire({
        title: "Resolver retiro observado",
        html: `
          <select id="withdrawal-decision" class="swal2-select">
            <option value="autorizado">Autorizar</option>
            <option value="observado">Mantener observado</option>
            <option value="rechazado">Rechazar</option>
          </select>
          <textarea id="withdrawal-reason" class="swal2-textarea" placeholder="Motivo o respaldo"></textarea>
        `,
        focusConfirm: false,
        preConfirm: () => {
          const decision = document.getElementById("withdrawal-decision").value;
          const reason = document.getElementById("withdrawal-reason").value;

          if (!reason.trim()) {
            Swal.showValidationMessage("Debes indicar un motivo.");
            return false;
          }

          return { decision, reason };
        },
      });

      if (!value) return;

      await axios.post(`/api/porter/withdrawals/${item.id}/resolve`, value);
      await this.loadWithdrawals(this.pagination.current_page || 1);
    },
    async annul(item) {
      const { value: reason } = await Swal.fire({
        title: "Anular retiro",
        input: "textarea",
        inputLabel: "Motivo de anulación",
        inputValidator: (value) => (!value ? "Debes indicar un motivo." : undefined),
      });

      if (!reason) return;

      await axios.post(`/api/porter/withdrawals/${item.id}/annul`, { reason });
      await this.loadWithdrawals(this.pagination.current_page || 1);
    },
    optionLabel(value, options) {
      return (options || []).find((item) => item.value === value)?.label || this.humanize(value);
    },
    relationshipLabel(value) {
      return this.optionLabel(value, this.catalogs.withdrawal_relationships);
    },
    reasonLabel(value) {
      return this.optionLabel(value, this.catalogs.withdrawal_reasons);
    },
    statusLabel(value) {
      return this.optionLabel(value, this.catalogs.withdrawal_statuses);
    },
    humanize(value) {
      if (!value) return "-";
      return String(value)
        .replace(/_/g, " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
    },
    sourceLabel(source) {
      const labels = {
        apoderado_titular: "Titular",
        apoderado_suplente: "Suplente",
        lista_porteria: "Autorizado",
      };

      return labels[source] || "Autorizado";
    },
    alertVariant(priority) {
      if (priority === "high") return "danger";
      if (priority === "medium") return "warning";
      return "info";
    },
    formatDateTime(value) {
      if (!value) return "-";
      const normalized = String(value).replace("T", " ");
      const [datePart, timePart] = normalized.split(" ");
      const [year, month, day] = (datePart || "").split("-");
      return year && month && day ? `${day}/${month}/${year} ${String(timePart || "").slice(0, 5)}` : value;
    },
    escapeHtml(value) {
      return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || {};
      const firstKey = Object.keys(errors)[0];
      return errors[firstKey]?.[0] || error?.response?.data?.message || error?.message || "Error desconocido";
    },
  },
};
</script>

<template>
  <Layout>
    <section class="withdrawals-page porter-view">
      <PorterModuleHeader
        title="Registro de retiros"
        subtitle="Controla la salida de estudiantes, valida autorizaciones y conserva la trazabilidad de cada retiro."
        eyebrow="Salida segura de estudiantes"
        icon="bx bx-log-out-circle"
      >
        <template #actions>
          <BButton variant="primary" @click="openWithdrawalModal">
            <i class="bx bx-plus-circle me-1"></i>Nuevo retiro
          </BButton>
          <router-link to="/porter/students" class="btn btn-outline-primary">
            <i class="bx bx-search-alt me-1"></i>Buscar estudiante
          </router-link>
        </template>
      </PorterModuleHeader>

      <PorterActionDeck compact :featured-routes="['/porter/students', '/porter/dashboard']" />

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <BModal
      v-model="showWithdrawalModal"
      title="Registrar retiro"
      size="xl"
      hide-header
      hide-footer
      scrollable
      no-close-on-backdrop
      no-close-on-esc
      body-class="p-0"
      modal-class="withdrawal-form-modal"
    >
      <div class="withdrawal-modal-shell">
        <div class="withdrawal-modal-intro">
          <span class="withdrawal-modal-intro__icon"><i class="bx bx-log-out-circle"></i></span>
          <div>
            <div class="withdrawal-modal-intro__eyebrow">Registro seguro</div>
            <h4>Nuevo retiro de estudiante</h4>
            <p>Selecciona la estudiante, valida a la persona autorizada y confirma la salida.</p>
          </div>
          <button type="button" class="withdrawal-modal-close" aria-label="Cerrar formulario" @click="requestCloseWithdrawalModal">
            <i class="bx bx-x"></i>
          </button>
        </div>

        <div class="withdrawal-modal-body">
          <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
    <div class="row g-3 align-items-start">
      <div class="col-xxl-8">
        <BCard class="withdrawal-card">
          <div class="withdrawal-card-header mb-3">
            <div>
              <h5 class="mb-1">Nuevo retiro</h5>
              <div class="text-muted small">Registro operativo de salida durante la jornada.</div>
            </div>
            <BBadge :variant="selectedStudent ? 'success' : 'secondary'" class="state-chip">
              {{ selectedStudent ? "Estudiante seleccionada" : "Sin estudiante" }}
            </BBadge>
          </div>

          <div class="student-flow-panel mb-3">
            <div v-if="selectedStudent" class="selected-student-strip">
              <div class="selected-student-main">
                <span class="selected-student-icon">
                  <i class="bx bx-user"></i>
                </span>
                <div class="min-w-0">
                  <div class="text-muted small">Estudiante</div>
                  <div class="fw-semibold text-truncate">{{ selectedStudent.full_name }}</div>
                  <div class="small text-muted text-truncate">
                    {{ selectedStudent.rut || "Sin RUT" }} · {{ selectedStudent.current_enrollment?.course_name || "Sin curso" }}
                  </div>
                </div>
              </div>
              <div class="selected-student-actions">
                <PorterStatusBadge
                  :value="selectedStudent.current_enrollment?.enrollment_status"
                  :label="selectedStudent.current_enrollment?.enrollment_status || '-'"
                />
                <BButton size="sm" variant="outline-secondary" @click="clearSelectedStudent">
                  <i class="bx bx-transfer-alt me-1"></i>
                  Cambiar
                </BButton>
              </div>
            </div>
            <div v-else class="selection-empty">
              <i class="bx bx-search-alt"></i>
              <span>Selecciona una estudiante para habilitar el registro.</span>
            </div>

            <div class="row g-2 align-items-end student-search-row">
              <div class="col-lg-7">
                <label class="form-label">Buscar estudiante</label>
                <div class="d-flex gap-2">
                  <BFormInput v-model="studentSearch" placeholder="Nombre o RUT" @keyup.enter="searchStudents" />
                  <BButton variant="outline-primary" :disabled="loadingStudent" @click="searchStudents">
                    <span v-if="loadingStudent">...</span>
                    <span v-else><i class="bx bx-search me-1"></i>Buscar</span>
                  </BButton>
                </div>
              </div>
              <div class="col-lg-5">
                <label class="form-label">Seleccionar resultado</label>
                <BFormSelect
                  :options="studentSelectOptions"
                  :model-value="form.student_profile_id"
                  :disabled="loadingStudent"
                  @update:model-value="selectStudent"
                />
              </div>
            </div>
          </div>

          <div v-if="authorizedPeople.length" class="authorized-picker mt-3">
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
              <h6 class="mb-0">Autorizados para retiro</h6>
              <BBadge variant="secondary">{{ authorizedPeople.length }}</BBadge>
            </div>
            <div class="authorized-picker-grid">
              <button
                v-for="(person, index) in authorizedPeople"
                :key="`${person.name}-${index}`"
                type="button"
                class="authorized-person-button"
                @click="applyAuthorizedPerson(person)"
              >
                <span class="authorized-source">{{ sourceLabel(person.source) }}</span>
                <span class="authorized-person-name">{{ person.name }}</span>
                <span class="authorized-person-meta">{{ person.relationship || "Sin relación" }} · {{ person.phone || "Sin teléfono" }}</span>
              </button>
            </div>
          </div>

          <div class="form-section-title mt-3">Persona que retira</div>
          <div class="row g-2 g-lg-3">
            <div class="col-lg-6">
              <label class="form-label">Nombre</label>
              <BFormInput v-model="form.person_name" />
            </div>
            <div class="col-md-6 col-lg-3">
              <label class="form-label">RUT</label>
              <BFormInput v-model="form.person_rut" />
            </div>
            <div class="col-md-6 col-lg-3">
              <label class="form-label">Relación</label>
              <BFormSelect v-model="form.person_relationship" :options="relationshipOptions" />
            </div>
            <div class="col-md-6 col-lg-4">
              <label class="form-label">Teléfono</label>
              <BFormInput v-model="form.person_phone" />
            </div>
            <div class="col-md-6 col-lg-4">
              <label class="form-label">Motivo</label>
              <BFormSelect v-model="form.reason" :options="reasonOptions" />
            </div>
            <div class="col-lg-4">
              <label class="form-label">Adjunto</label>
              <div class="file-picker">
                <BFormInput
                  id="withdrawal-attachment"
                  ref="attachmentInput"
                  type="file"
                  class="file-picker-input"
                  @change="onFileChange"
                />
                <label class="file-picker-button" for="withdrawal-attachment">
                  <i class="bx bx-paperclip"></i>
                  Adjuntar
                </label>
                <span class="file-picker-name">{{ form.attachment?.name || "Sin archivo" }}</span>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">Observaciones</label>
              <BFormTextarea v-model="form.observations" rows="2" />
            </div>
          </div>

          <div class="withdrawal-options mt-3">
            <BFormCheckbox v-model="form.force_duplicate_confirmation">
              Confirmar si existe un retiro duplicado reciente
            </BFormCheckbox>
            <BFormCheckbox v-if="catalogs.capabilities?.can_authorize_special_withdrawal" v-model="form.approve_override">
              Autorizar de inmediato si aparece alerta especial
            </BFormCheckbox>
          </div>

          <div v-if="form.approve_override && catalogs.capabilities?.can_authorize_special_withdrawal" class="mt-3">
            <label class="form-label">Motivo de autorización especial</label>
            <BFormTextarea v-model="form.override_reason" rows="2" />
          </div>

          <div class="modal-form-actions mt-4">
            <BButton variant="outline-secondary" :disabled="saving" @click="requestCloseWithdrawalModal">
              Cancelar
            </BButton>
            <BButton variant="primary" :disabled="saving" @click="submit">
              <span v-if="saving">Guardando...</span>
              <span v-else><i class="bx bx-check-circle me-1"></i>Registrar retiro</span>
            </BButton>
          </div>
        </BCard>
      </div>

      <div class="col-xxl-4">
        <BCard class="withdrawal-context-card">
          <LoadingState v-if="loadingCatalogs || loadingStudent" message="Cargando ficha..." compact />
          <template v-else-if="selectedStudent">
            <div class="context-header mb-3">
              <span class="context-avatar">
                <i class="bx bx-id-card"></i>
              </span>
              <div class="min-w-0">
                <h5 class="mb-1 text-truncate">{{ selectedStudent.full_name }}</h5>
                <div class="text-muted">{{ selectedStudent.rut || "Sin RUT" }}</div>
              </div>
              <div class="ms-auto context-header-badge">
                <PorterStatusBadge :value="selectedStudent.general_status" :label="selectedStudent.general_status || '-'" />
              </div>
            </div>

            <div class="context-meta-grid">
              <div class="context-meta-item">
                <span>Curso</span>
                <strong>{{ selectedStudent.current_enrollment?.course_name || "-" }}</strong>
              </div>
              <div class="context-meta-item">
                <span>Año</span>
                <strong>{{ selectedStudent.current_enrollment?.academic_year_name || "-" }}</strong>
              </div>
              <div class="context-meta-item context-meta-item--status">
                <span>Matrícula</span>
                <div class="context-badge-wrap">
                  <PorterStatusBadge
                    :value="selectedStudent.current_enrollment?.enrollment_status"
                    :label="selectedStudent.current_enrollment?.enrollment_status || '-'"
                  />
                </div>
              </div>
            </div>

            <div v-if="studentAlerts.length" class="mt-3">
              <BAlert
                v-for="(alert, index) in studentAlerts"
                :key="index"
                :variant="alertVariant(alert.priority)"
                show
                class="py-2 mb-2"
              >
                <div class="fw-semibold">{{ alert.label }}</div>
                <div class="small">{{ alert.detail }}</div>
              </BAlert>
            </div>

            <div class="context-section context-section--guardian">
              <h6>Apoderado titular</h6>
              <div class="fw-semibold">{{ selectedStudent.guardian_name || "-" }}</div>
              <div class="small text-muted">{{ selectedStudent.guardian_relationship || "Sin relación" }}</div>
              <div class="small">{{ selectedStudent.guardian_phone || "Sin teléfono" }}</div>
            </div>

            <div class="context-section">
              <h6>Últimos retiros</h6>
              <div v-if="!recentWithdrawals.length" class="text-muted small">Sin retiros recientes.</div>
              <div v-else class="recent-withdrawal-list">
                <div v-for="withdrawal in recentWithdrawals.slice(0, 4)" :key="withdrawal.id" class="recent-withdrawal-item">
                  <div class="fw-semibold">{{ formatDateTime(withdrawal.withdrawn_at) }}</div>
                  <div class="small text-muted">{{ withdrawal.person_name || "-" }} · {{ reasonLabel(withdrawal.reason) }}</div>
                </div>
              </div>
            </div>
          </template>
          <div v-else class="context-empty">
            <i class="bx bx-id-card"></i>
            <div class="fw-semibold">Ficha de retiro</div>
            <div class="text-muted small">La información operativa aparecerá cuando selecciones una estudiante.</div>
          </div>
        </BCard>
      </div>
    </div>
        </div>
      </div>
    </BModal>

    <BCard class="withdrawal-history-card">
      <div class="withdrawal-history-heading">
        <div class="withdrawal-history-title">
          <span class="withdrawal-history-icon"><i class="bx bx-list-ul"></i></span>
          <div>
            <div class="withdrawal-history-eyebrow">Control de salidas</div>
            <h5 class="mb-1">Retiros registrados</h5>
            <div class="text-muted small">{{ pagination.total }} registro(s) encontrados</div>
          </div>
        </div>
        <BButton variant="primary" @click="openWithdrawalModal">
          <i class="bx bx-plus-circle me-1"></i>Registrar retiro
        </BButton>
      </div>

      <div class="history-filters">
          <BFormInput v-model="listFilters.search" placeholder="Estudiante o persona" @keyup.enter="loadWithdrawals(1)" />
          <BFormSelect v-model="listFilters.status" :options="statusOptions" />
          <BFormSelect v-model="listFilters.reason" :options="[{ value: null, text: 'Todos los motivos' }].concat(reasonOptions)" />
          <BButton variant="outline-primary" @click="loadWithdrawals(1)"><i class="bx bx-filter-alt me-1"></i>Filtrar</BButton>
      </div>

      <BTable
        :items="withdrawals"
        :busy="loadingList"
        :fields="historyFields"
        responsive
        hover
        small
        show-empty
        table-class="align-middle withdrawal-history-table mb-0"
      >
        <template #table-busy>
          <LoadingState message="Cargando retiros..." compact />
        </template>
        <template #empty>
          <div class="text-center text-muted py-4">No hay retiros para los filtros seleccionados.</div>
        </template>
        <template #cell(student)="{ item }">
          <div class="fw-semibold">{{ item.student_full_name_snapshot }}</div>
          <div class="small text-muted">{{ item.course_name_snapshot }}</div>
          <div class="small text-muted">{{ item.student_rut_snapshot }}</div>
        </template>
        <template #cell(person_name)="{ item }">
          <div class="fw-semibold">{{ item.person_name }}</div>
          <div class="small text-muted">{{ relationshipLabel(item.person_relationship) }}</div>
          <div class="small text-muted">{{ item.person_phone || "Sin teléfono" }}</div>
        </template>
        <template #cell(reason)="{ item }">
          {{ reasonLabel(item.reason) }}
        </template>
        <template #cell(status)="{ item }">
          <PorterStatusBadge :value="item.status" :label="statusLabel(item.status)" />
        </template>
        <template #cell(withdrawn_at)="{ item }">
          {{ formatDateTime(item.withdrawn_at) }}
        </template>
        <template #cell(actions)="{ item }">
          <div class="d-flex gap-2 justify-content-end">
            <BButton
              v-if="catalogs.capabilities?.can_authorize_special_withdrawal && ['observado', 'rechazado'].includes(item.status)"
              size="sm"
              variant="outline-primary"
              @click="resolve(item)"
            >
              Resolver
            </BButton>
            <BButton
              v-if="catalogs.capabilities?.can_authorize_special_withdrawal && item.status !== 'anulado'"
              size="sm"
              variant="outline-danger"
              @click="annul(item)"
            >
              Anular
            </BButton>
          </div>
        </template>
      </BTable>

      <div class="d-flex justify-content-end mt-3">
        <BPagination
          v-model="pagination.current_page"
          :total-rows="pagination.total"
          :per-page="pagination.per_page"
          @update:model-value="loadWithdrawals"
        />
      </div>
    </BCard>
    </section>
  </Layout>
</template>

<style scoped>
:global(.withdrawal-form-modal .modal-dialog) {
  max-width: min(94vw, 92rem);
}

:global(.withdrawal-form-modal .modal-content) {
  border: 0;
  border-radius: 1rem;
  box-shadow: 0 1.5rem 4rem rgba(18, 36, 67, 0.26);
  overflow: hidden;
}

:global(.withdrawal-form-modal .modal-body) {
  background: #f5f8fd;
}

.withdrawal-modal-shell {
  min-height: 20rem;
}

.withdrawal-modal-intro {
  align-items: center;
  background:
    radial-gradient(circle at 88% 0, rgba(255, 255, 255, 0.17), transparent 32%),
    linear-gradient(125deg, #182f57 0%, #315f9f 100%);
  color: #fff;
  display: flex;
  gap: 1rem;
  min-height: 7.5rem;
  padding: 1.25rem 1.4rem;
  position: relative;
}

.withdrawal-modal-intro::after {
  background-image: radial-gradient(rgba(255, 255, 255, 0.18) 0.7px, transparent 0.7px);
  background-size: 13px 13px;
  content: "";
  inset: 0 0 0 55%;
  opacity: 0.34;
  pointer-events: none;
  position: absolute;
}

.withdrawal-modal-intro__icon {
  align-items: center;
  background: rgba(255, 255, 255, 0.13);
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 0.8rem;
  display: inline-flex;
  flex: 0 0 auto;
  font-size: 1.65rem;
  height: 3.5rem;
  justify-content: center;
  width: 3.5rem;
}

.withdrawal-modal-intro__eyebrow {
  color: #7ce7bb;
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 0.1em;
  margin-bottom: 0.25rem;
  text-transform: uppercase;
}

.withdrawal-modal-intro h4 {
  color: #fff;
  font-weight: 750;
  letter-spacing: -0.02em;
  margin: 0;
}

.withdrawal-modal-intro p {
  color: rgba(255, 255, 255, 0.7);
  margin: 0.35rem 0 0;
}

.withdrawal-modal-close {
  align-items: center;
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 0.65rem;
  color: #fff;
  display: inline-flex;
  font-size: 1.35rem;
  height: 2.55rem;
  justify-content: center;
  margin-left: auto;
  position: relative;
  transition: background-color 0.16s ease, transform 0.16s ease;
  width: 2.55rem;
  z-index: 1;
}

.withdrawal-modal-close:hover,
.withdrawal-modal-close:focus-visible {
  background: rgba(255, 255, 255, 0.2);
  outline: none;
  transform: scale(1.03);
}

.withdrawal-modal-body {
  padding: 1rem;
}

.modal-form-actions {
  align-items: center;
  border-top: 1px solid var(--bs-border-color);
  display: flex;
  gap: 0.65rem;
  justify-content: flex-end;
  padding-top: 1rem;
}

.withdrawal-history-heading {
  align-items: center;
  border-bottom: 1px solid var(--bs-border-color);
  display: flex;
  gap: 1rem;
  justify-content: space-between;
  margin: -0.15rem -0.15rem 1rem;
  padding: 0.15rem 0.15rem 1rem;
}

.withdrawal-history-title {
  align-items: center;
  display: flex;
  gap: 0.8rem;
  min-width: 0;
}

.withdrawal-history-icon {
  align-items: center;
  background: rgba(var(--bs-primary-rgb), 0.1);
  border: 1px solid rgba(var(--bs-primary-rgb), 0.12);
  border-radius: 0.72rem;
  color: var(--bs-primary);
  display: inline-flex;
  flex: 0 0 auto;
  font-size: 1.3rem;
  height: 3rem;
  justify-content: center;
  width: 3rem;
}

.withdrawal-history-eyebrow {
  color: var(--bs-primary);
  font-size: 0.67rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.withdrawal-card,
.withdrawal-context-card,
.withdrawal-history-card {
  overflow: hidden;
}

.withdrawal-card :deep(.card-body),
.withdrawal-context-card :deep(.card-body),
.withdrawal-history-card :deep(.card-body) {
  padding: 1.25rem;
}

.withdrawal-card-header,
.context-header {
  align-items: flex-start;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
}

.state-chip {
  flex: 0 0 auto;
  white-space: nowrap;
}

.student-flow-panel,
.selected-student-strip,
.selection-empty,
.authorized-picker,
.context-meta-item,
.context-section {
  border: 1px solid var(--bs-border-color);
  border-radius: 0.5rem;
}

.student-flow-panel {
  background: rgba(var(--bs-primary-rgb), 0.025);
  padding: 0.875rem;
}

.selected-student-strip {
  align-items: center;
  border: 0;
  border-bottom: 1px solid var(--bs-border-color);
  border-radius: 0;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
  padding: 0 0 0.75rem;
}

.selected-student-main {
  align-items: center;
  display: flex;
  gap: 0.75rem;
  min-width: 0;
}

.selected-student-icon,
.context-avatar {
  align-items: center;
  background: rgba(var(--bs-primary-rgb), 0.1);
  color: var(--bs-primary);
  display: inline-flex;
  flex: 0 0 auto;
  justify-content: center;
}

.selected-student-icon {
  border-radius: 0.5rem;
  height: 2.25rem;
  width: 2.25rem;
}

.selected-student-actions {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  justify-content: flex-end;
}

.selection-empty {
  align-items: center;
  border: 0;
  border-bottom: 1px solid var(--bs-border-color);
  border-radius: 0;
  color: var(--bs-secondary-color);
  display: flex;
  gap: 0.5rem;
  padding: 0 0 0.75rem;
}

.selection-empty i,
.context-empty i {
  font-size: 1.35rem;
}

.student-search-row {
  padding-top: 0.75rem;
}

.student-search-row .btn {
  white-space: nowrap;
}

.authorized-picker {
  background: rgba(var(--bs-info-rgb), 0.035);
  padding: 0.75rem;
}

.authorized-picker-grid {
  display: grid;
  gap: 0.5rem;
  grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
}

.authorized-person-button {
  background: var(--bs-body-bg);
  border: 1px solid var(--bs-border-color);
  border-radius: 0.5rem;
  color: inherit;
  display: flex;
  flex-direction: column;
  min-height: 4.25rem;
  padding: 0.625rem 0.75rem;
  position: relative;
  text-align: left;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.authorized-person-button:hover,
.authorized-person-button:focus {
  border-color: var(--bs-primary);
  box-shadow: 0 0.25rem 0.75rem rgba(var(--bs-primary-rgb), 0.08);
  outline: 0;
}

.authorized-source {
  color: var(--bs-secondary-color);
  font-size: 0.72rem;
  line-height: 1;
  margin-bottom: 0.35rem;
  text-transform: uppercase;
}

.authorized-person-name {
  font-weight: 600;
  line-height: 1.2;
}

.authorized-person-meta {
  color: var(--bs-secondary-color);
  font-size: 0.8rem;
  line-height: 1.35;
  margin-top: 0.15rem;
}

.file-picker {
  align-items: stretch;
  background: var(--bs-body-bg);
  border: 1px solid var(--bs-border-color);
  border-radius: 0.5rem;
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  min-height: 38px;
  overflow: hidden;
}

.file-picker-input {
  height: 1px;
  opacity: 0;
  overflow: hidden;
  position: absolute;
  width: 1px;
}

.file-picker-button {
  align-items: center;
  background: rgba(var(--bs-primary-rgb), 0.06);
  border-right: 1px solid var(--bs-border-color);
  color: var(--bs-primary);
  cursor: pointer;
  display: inline-flex;
  font-weight: 600;
  gap: 0.35rem;
  margin: 0;
  padding: 0.45rem 0.75rem;
  white-space: nowrap;
}

.file-picker-name {
  align-items: center;
  color: var(--bs-secondary-color);
  display: flex;
  min-width: 0;
  overflow: hidden;
  padding: 0 0.75rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.form-section-title {
  color: var(--bs-secondary-color);
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0;
  margin-bottom: 0.75rem;
  text-transform: uppercase;
}

.withdrawal-options {
  display: grid;
  gap: 0.75rem 1.5rem;
  grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
}

.withdrawal-context-card {
  position: sticky;
  top: 1rem;
}

.context-header {
  align-items: center;
}

.context-avatar {
  border-radius: 0.625rem;
  height: 2.5rem;
  width: 2.5rem;
}

.context-header-badge {
  flex: 0 1 auto;
  max-width: 45%;
  text-align: right;
}

.context-header-badge :deep(.badge),
.context-badge-wrap :deep(.badge),
.selected-student-actions :deep(.badge) {
  max-width: 100%;
  overflow-wrap: anywhere;
  white-space: normal;
}

.context-meta-grid {
  display: grid;
  gap: 0.625rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.context-meta-item {
  background: var(--bs-body-bg);
  min-height: 4.25rem;
  padding: 0.75rem;
}

.context-meta-item span {
  color: var(--bs-secondary-color);
  display: block;
  font-size: 0.8rem;
}

.context-meta-item strong {
  display: block;
  font-size: 1rem;
  margin-top: 0.25rem;
}

.context-meta-item--status {
  align-items: center;
  display: flex;
  gap: 0.75rem;
  grid-column: 1 / -1;
  justify-content: space-between;
  min-height: auto;
}

.context-badge-wrap {
  min-width: 0;
  text-align: right;
}

.context-section {
  background: var(--bs-body-bg);
  margin-top: 0.75rem;
  padding: 0.75rem;
}

.context-section h6 {
  margin-bottom: 0.45rem;
}

.context-empty {
  align-items: center;
  display: flex;
  flex-direction: column;
  min-height: 12rem;
  justify-content: center;
  text-align: center;
}

.recent-withdrawal-list {
  display: grid;
  gap: 0.5rem;
}

.recent-withdrawal-item {
  border-bottom: 1px solid var(--bs-border-color);
  padding-bottom: 0.5rem;
}

.recent-withdrawal-item:last-child {
  border-bottom: 0;
  padding-bottom: 0;
}

.history-filters {
  background: rgba(var(--bs-primary-rgb), 0.035);
  border: 1px solid rgba(var(--bs-primary-rgb), 0.08);
  border-radius: 0.7rem;
  display: grid;
  gap: 0.5rem;
  grid-template-columns: minmax(14rem, 1.5fr) minmax(10rem, 1fr) minmax(11rem, 1fr) auto;
  margin-bottom: 1rem;
  padding: 0.75rem;
}

.min-w-0 {
  min-width: 0;
}

:deep(.withdrawal-history-table th) {
  white-space: nowrap;
}

:deep(.withdrawal-history-table tbody tr) {
  transition: background-color 0.15s ease;
}

:global([data-bs-theme="dark"] .withdrawal-form-modal .modal-body),
:global(body[data-layout-mode="dark"] .withdrawal-form-modal .modal-body) {
  background: #1c283d;
}

@media (max-width: 1199.98px) {
  .withdrawal-context-card {
    position: static;
  }
}

@media (max-width: 991.98px) {
  .context-meta-grid,
  .history-filters {
    grid-template-columns: 1fr;
  }

  .selected-student-strip {
    align-items: flex-start;
    flex-direction: column;
  }

  .selected-student-actions {
    justify-content: flex-start;
  }

  .context-header-badge {
    max-width: 100%;
  }

  .withdrawal-history-heading {
    align-items: flex-start;
    flex-direction: column;
  }
}

@media (max-width: 575.98px) {
  .withdrawal-card :deep(.card-body),
  .withdrawal-context-card :deep(.card-body),
  .withdrawal-history-card :deep(.card-body) {
    padding: 1rem;
  }

  .student-search-row .d-flex {
    flex-direction: column;
  }

  .student-search-row .btn {
    width: 100%;
  }

  .withdrawal-modal-intro {
    align-items: flex-start;
    padding: 1rem;
  }

  .withdrawal-modal-intro__icon {
    display: none;
  }

  .withdrawal-modal-intro p {
    font-size: 0.78rem;
  }

  .withdrawal-modal-body {
    padding: 0.75rem;
  }

  .modal-form-actions,
  .modal-form-actions .btn,
  .withdrawal-history-heading > .btn {
    width: 100%;
  }

  .modal-form-actions {
    flex-direction: column-reverse;
  }
}
</style>
