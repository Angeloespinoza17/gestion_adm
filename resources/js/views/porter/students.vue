<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import PorterStudentSummaryCard from "../../components/porter/student-summary-card.vue";
import PorterStatusBadge from "../../components/porter/status-badge.vue";

export default {
  components: { Layout, LoadingState, PorterStudentSummaryCard, PorterStatusBadge },
  data() {
    return {
      loading: false,
      detailLoading: false,
      showStudentModal: false,
      error: null,
      catalogs: {
        courses: [],
        education_levels: [],
        student_general_statuses: [],
        student_enrollment_statuses: [],
      },
      filters: {
        search: "",
        course_section_id: null,
        education_level_id: null,
        status: null,
      },
      students: [],
      pagination: { current_page: 1, total: 0, per_page: 15 },
      selectedStudent: null,
    };
  },
  computed: {
    courseOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.courses || []).map((course) => ({ value: course.id, text: course.display_name }))
      );
    },
    levelOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.education_levels || []).map((level) => ({ value: level.id, text: level.name }))
      );
    },
    statusOptions() {
      const statuses = this.catalogs.student_general_statuses?.length
        ? this.catalogs.student_general_statuses
        : [
            { value: "activo", label: "Activo" },
            { value: "retirado", label: "Retirado" },
            { value: "egresado", label: "Egresado" },
            { value: "suspendido", label: "Suspendido" },
          ];

      return [{ value: null, text: "Todos" }].concat(statuses.map((status) => ({ value: status.value, text: status.label })));
    },
    studentFields() {
      return [
        { key: "student", label: "Estudiante" },
        { key: "course", label: "Curso / nivel" },
        { key: "enrollment_status", label: "Matrícula" },
        { key: "general_status", label: "Estado" },
        { key: "guardian", label: "Apoderado titular" },
        { key: "authorized_people", label: "Autorizados" },
        { key: "alerts", label: "Alertas" },
        { key: "last_withdrawal", label: "Último retiro" },
        { key: "actions", label: "Acciones", thClass: "text-end", tdClass: "text-end" },
      ];
    },
    studentModalTitle() {
      return this.selectedStudent?.full_name ? `Ficha de ${this.selectedStudent.full_name}` : "Ficha operativa";
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadStudents();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/porter/catalogs");
      this.catalogs = response.data;
    },
    async loadStudents(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/porter/students", {
          params: {
            page,
            per_page: this.pagination.per_page,
            search: this.filters.search || null,
            course_section_id: this.filters.course_section_id,
            education_level_id: this.filters.education_level_id,
            status: this.filters.status,
          },
        });
        this.students = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page || 1,
          total: response.data.total || 0,
          per_page: response.data.per_page || 15,
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    async openStudent(student) {
      this.selectedStudent = student;
      this.showStudentModal = true;
      this.detailLoading = true;
      this.error = null;

      try {
        const response = await axios.get(`/api/porter/students/${student.id}`);
        this.selectedStudent = response.data.data;
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.detailLoading = false;
      }
    },
    goToWithdrawal(student) {
      this.showStudentModal = false;
      this.$router.push({ path: "/porter/withdrawals", query: { student_id: student.id } });
    },
    resetFilters() {
      this.filters = {
        search: "",
        course_section_id: null,
        education_level_id: null,
        status: null,
      };
      this.loadStudents(1);
    },
    statusLabel(value, catalogKey) {
      if (!value) return "-";
      const option = (this.catalogs[catalogKey] || []).find((item) => item.value === value);
      return option?.label || this.humanize(value);
    },
    humanize(value) {
      if (!value) return "-";
      return String(value)
        .replace(/_/g, " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
    },
    authorizedPeoplePreview(student) {
      const people = student.authorized_pickup_people || [];
      if (!people.length) return "Sin personas registradas";

      return people
        .slice(0, 2)
        .map((person) => person.name)
        .join(", ");
    },
    latestWithdrawal(student) {
      return student.recent_withdrawals?.[0] || null;
    },
    alertBadgeVariant(student) {
      const alerts = student.alerts || [];
      if (alerts.some((alert) => alert.priority === "high")) return "danger";
      if (alerts.some((alert) => alert.priority === "medium")) return "warning";
      return "info";
    },
    formatDateTime(value) {
      if (!value) return "-";
      const normalized = String(value).replace("T", " ");
      const [datePart, timePart] = normalized.split(" ");
      const [year, month, day] = (datePart || "").split("-");
      return year && month && day ? `${day}/${month}/${year} ${String(timePart || "").slice(0, 5)}` : value;
    },
    formatError(error) {
      return error?.response?.data?.message || error?.message || "No se pudieron cargar las estudiantes.";
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div>
        <h4 class="mb-0">Consulta de estudiantes</h4>
        <div class="text-muted">Búsqueda rápida para uso operativo de portería.</div>
      </div>
      <router-link to="/porter/withdrawals" class="btn btn-primary">Registrar retiro</router-link>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <BCard class="mb-3">
      <div class="row g-3 align-items-end">
        <div class="col-lg-4">
          <label class="form-label">Nombre, apellido o RUT</label>
          <BFormInput v-model="filters.search" placeholder="Buscar estudiante o apoderado" @keyup.enter="loadStudents(1)" />
        </div>
        <div class="col-sm-6 col-lg-2">
          <label class="form-label">Nivel</label>
          <BFormSelect v-model="filters.education_level_id" :options="levelOptions" />
        </div>
        <div class="col-sm-6 col-lg-2">
          <label class="form-label">Curso</label>
          <BFormSelect v-model="filters.course_section_id" :options="courseOptions" />
        </div>
        <div class="col-sm-6 col-lg-2">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="filters.status" :options="statusOptions" />
        </div>
        <div class="col-sm-6 col-lg-2">
          <div class="d-flex gap-2">
            <BButton variant="primary" class="flex-fill" @click="loadStudents(1)">Buscar</BButton>
            <BButton variant="outline-secondary" class="flex-fill" @click="resetFilters">Limpiar</BButton>
          </div>
        </div>
      </div>
    </BCard>

    <BCard>
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="mb-0">Estudiantes</h5>
        <div class="small text-muted">{{ pagination.total }} resultado(s)</div>
      </div>

      <BTable
        :items="students"
        :busy="loading"
        :fields="studentFields"
        responsive
        hover
        small
        show-empty
        table-class="align-middle porter-students-table mb-0"
      >
        <template #table-busy>
          <LoadingState message="Buscando estudiantes..." compact />
        </template>
        <template #empty>
          <div class="text-center text-muted py-4">No hay estudiantes para los filtros seleccionados.</div>
        </template>
        <template #cell(student)="{ item }">
          <div class="fw-semibold">{{ item.full_name }}</div>
          <div class="small text-muted">{{ item.rut || "Sin RUT" }}</div>
          <div v-if="item.registered_name && item.registered_name !== item.full_name" class="small text-muted">
            Reg.: {{ item.registered_name }}
          </div>
        </template>
        <template #cell(course)="{ item }">
          <div class="fw-semibold">{{ item.current_enrollment?.course_name || "-" }}</div>
          <div class="small text-muted">{{ item.current_enrollment?.education_level_name || "-" }}</div>
          <div class="small text-muted">{{ item.current_enrollment?.academic_year_name || "-" }}</div>
        </template>
        <template #cell(enrollment_status)="{ item }">
          <PorterStatusBadge
            :value="item.current_enrollment?.enrollment_status"
            :label="statusLabel(item.current_enrollment?.enrollment_status, 'student_enrollment_statuses')"
          />
        </template>
        <template #cell(general_status)="{ item }">
          <PorterStatusBadge :value="item.general_status" :label="statusLabel(item.general_status, 'student_general_statuses')" />
        </template>
        <template #cell(guardian)="{ item }">
          <div class="fw-semibold">{{ item.guardian_name || "-" }}</div>
          <div class="small text-muted">{{ item.guardian_phone || "Sin teléfono" }}</div>
          <div v-if="item.guardian_relationship" class="small text-muted">{{ item.guardian_relationship }}</div>
        </template>
        <template #cell(authorized_people)="{ item }">
          <div>{{ (item.authorized_pickup_people || []).length }} persona(s)</div>
          <div class="small text-muted porter-authorized-preview">{{ authorizedPeoplePreview(item) }}</div>
        </template>
        <template #cell(alerts)="{ item }">
          <div v-if="(item.alerts || []).length">
            <BBadge :variant="alertBadgeVariant(item)">{{ item.alerts.length }} alerta(s)</BBadge>
            <div class="small text-muted mt-1">{{ item.alerts[0]?.label }}</div>
          </div>
          <span v-else class="text-muted">Sin alertas</span>
        </template>
        <template #cell(last_withdrawal)="{ item }">
          <template v-if="latestWithdrawal(item)">
            <div>{{ formatDateTime(latestWithdrawal(item).withdrawn_at) }}</div>
            <div class="small text-muted">{{ latestWithdrawal(item).person_name || "-" }}</div>
          </template>
          <span v-else class="text-muted">Sin retiros</span>
        </template>
        <template #cell(actions)="{ item }">
          <div class="d-flex gap-2 justify-content-end">
            <BButton
              variant="outline-primary"
              size="sm"
              class="porter-action-btn"
              title="Ver ficha"
              :aria-label="`Ver ficha de ${item.full_name}`"
              @click="openStudent(item)"
            >
              <i class="bx bx-show"></i>
            </BButton>
            <BButton
              variant="outline-warning"
              size="sm"
              class="porter-action-btn"
              title="Registrar retiro"
              :aria-label="`Registrar retiro de ${item.full_name}`"
              @click="goToWithdrawal(item)"
            >
              <i class="bx bx-user-x"></i>
            </BButton>
          </div>
        </template>
      </BTable>

      <div class="d-flex justify-content-end mt-3">
        <BPagination
          v-model="pagination.current_page"
          :total-rows="pagination.total"
          :per-page="pagination.per_page"
          @update:model-value="loadStudents"
        />
      </div>
    </BCard>

    <BModal v-model="showStudentModal" :title="studentModalTitle" size="xl" hide-footer scrollable>
      <LoadingState v-if="detailLoading" message="Cargando ficha..." compact />
      <PorterStudentSummaryCard v-else :student="selectedStudent" :framed="false" @register-withdrawal="goToWithdrawal" />
    </BModal>
  </Layout>
</template>

<style scoped>
.porter-action-btn {
  align-items: center;
  display: inline-flex;
  height: 2.25rem;
  justify-content: center;
  padding: 0;
  width: 2.25rem;
}

.porter-action-btn i {
  font-size: 1.2rem;
}

.porter-authorized-preview {
  max-width: 14rem;
}

:deep(.porter-students-table th) {
  white-space: nowrap;
}

:deep(.porter-students-table td) {
  vertical-align: middle;
}
</style>
