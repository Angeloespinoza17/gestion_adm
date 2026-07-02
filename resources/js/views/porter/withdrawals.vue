<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import PorterStudentSummaryCard from "../../components/porter/student-summary-card.vue";
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
  components: { Layout, LoadingState, PorterStudentSummaryCard, PorterStatusBadge },
  data() {
    return {
      loadingCatalogs: false,
      saving: false,
      loadingList: false,
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
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadWithdrawals();
    if (this.$route.query.student_id) {
      await this.loadStudentById(this.$route.query.student_id);
    }
  },
  methods: {
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
        this.studentOptions = [];
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
      const response = await axios.get(`/api/porter/students/${id}`);
      this.selectedStudent = response.data.data;
      this.form.student_profile_id = response.data.data.id;
    },
    async selectStudent(id) {
      if (!id) {
        this.selectedStudent = null;
        return;
      }

      await this.loadStudentById(id);
    },
    onFileChange(event) {
      this.form.attachment = event?.target?.files?.[0] || null;
    },
    async submit() {
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

        this.form = emptyForm();
        this.selectedStudent = null;
        this.studentSearch = "";
        this.studentOptions = [];
        await this.loadWithdrawals(1);
      } catch (error) {
        this.error = this.formatError(error);
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
    goToWithdrawal(student) {
      this.form.student_profile_id = student.id;
      this.selectedStudent = student;
    },
    formatDateTime(value) {
      if (!value) return "-";
      const normalized = String(value).replace("T", " ");
      const [datePart, timePart] = normalized.split(" ");
      const [year, month, day] = (datePart || "").split("-");
      return year && month && day ? `${day}/${month}/${year} ${String(timePart || "").slice(0, 5)}` : value;
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
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div>
        <h4 class="mb-0">Registro de retiros</h4>
        <div class="text-muted">Control de retiros de estudiantes durante la jornada.</div>
      </div>
      <router-link to="/porter/students" class="btn btn-outline-primary">Buscar estudiante</router-link>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <div class="row g-3">
      <div class="col-xl-7">
        <BCard class="mb-3">
          <h5 class="mb-3">Nuevo retiro</h5>
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Buscar estudiante</label>
              <div class="d-flex gap-2">
                <BFormInput v-model="studentSearch" placeholder="Nombre o RUT" @keyup.enter="searchStudents" />
                <BButton variant="outline-primary" @click="searchStudents">Buscar</BButton>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Seleccionar resultado</label>
              <BFormSelect
                :options="[{ value: null, text: 'Seleccionar...' }].concat(studentOptions.map((item) => ({ value: item.id, text: `${item.full_name} · ${item.current_enrollment?.course_name || '-'}` })))"
                :model-value="form.student_profile_id"
                @update:model-value="selectStudent"
              />
            </div>

            <div class="col-md-6">
              <label class="form-label">Persona que retira</label>
              <BFormInput v-model="form.person_name" />
            </div>
            <div class="col-md-3">
              <label class="form-label">RUT</label>
              <BFormInput v-model="form.person_rut" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Relación</label>
              <BFormSelect v-model="form.person_relationship" :options="relationshipOptions" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Teléfono</label>
              <BFormInput v-model="form.person_phone" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Motivo</label>
              <BFormSelect v-model="form.reason" :options="reasonOptions" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Adjunto</label>
              <BFormInput type="file" @change="onFileChange" />
            </div>
            <div class="col-12">
              <label class="form-label">Observaciones</label>
              <BFormTextarea v-model="form.observations" rows="3" />
            </div>

            <div class="col-md-6">
              <BFormCheckbox v-model="form.force_duplicate_confirmation">
                Confirmar si existe un retiro duplicado reciente
              </BFormCheckbox>
            </div>

            <div v-if="catalogs.capabilities?.can_authorize_special_withdrawal" class="col-md-6">
              <BFormCheckbox v-model="form.approve_override">
                Autorizar de inmediato si aparece alerta especial
              </BFormCheckbox>
            </div>
            <div v-if="form.approve_override && catalogs.capabilities?.can_authorize_special_withdrawal" class="col-12">
              <label class="form-label">Motivo de autorización especial</label>
              <BFormTextarea v-model="form.override_reason" rows="2" />
            </div>

            <div class="col-12 d-flex justify-content-end">
              <BButton variant="primary" :disabled="saving || !form.student_profile_id" @click="submit">
                {{ saving ? "Guardando..." : "Registrar retiro" }}
              </BButton>
            </div>
          </div>
        </BCard>

        <BCard>
          <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0">Historial de retiros</h5>
            <div class="d-flex gap-2 flex-wrap">
              <BFormInput v-model="listFilters.search" placeholder="Estudiante o persona" @keyup.enter="loadWithdrawals(1)" />
              <BFormSelect v-model="listFilters.status" :options="statusOptions" />
              <BFormSelect v-model="listFilters.reason" :options="[{ value: null, text: 'Todos los motivos' }].concat(reasonOptions)" />
              <BButton variant="outline-primary" @click="loadWithdrawals(1)">Filtrar</BButton>
            </div>
          </div>

          <BTable
            :items="withdrawals"
            :busy="loadingList"
            responsive
            :fields="[
              { key: 'student', label: 'Estudiante' },
              { key: 'person_name', label: 'Retira' },
              { key: 'reason', label: 'Motivo' },
              { key: 'status', label: 'Estado' },
              { key: 'withdrawn_at', label: 'Fecha' },
              { key: 'actions', label: 'Acciones' },
            ]"
          >
            <template #table-busy>
              <LoadingState message="Cargando retiros..." compact />
            </template>
            <template #cell(student)="{ item }">
              <div class="fw-semibold">{{ item.student_full_name_snapshot }}</div>
              <div class="small text-muted">{{ item.course_name_snapshot }}</div>
            </template>
            <template #cell(status)="{ item }">
              <PorterStatusBadge :value="item.status" :label="item.status" />
            </template>
            <template #cell(withdrawn_at)="{ item }">
              {{ formatDateTime(item.withdrawn_at) }}
            </template>
            <template #cell(actions)="{ item }">
              <div class="d-flex gap-2">
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
      </div>

      <div class="col-xl-5">
        <LoadingState v-if="loadingCatalogs" message="Cargando catálogos..." compact />
        <PorterStudentSummaryCard v-else :student="selectedStudent" @register-withdrawal="goToWithdrawal" />
      </div>
    </div>
  </Layout>
</template>
