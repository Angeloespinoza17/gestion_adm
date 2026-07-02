<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Swal from "sweetalert2";

const emptyActionForm = () => ({
  id: null,
  course_section_id: null,
  effective_date: new Date().toISOString().slice(0, 10),
  enrollment_status: "regular",
  notes: "",
});

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      actionLoading: false,
      error: null,
      catalogs: {
        academic_years: [],
        enrollment_statuses: [],
        active_academic_year_id: null,
      },
      courseSections: [],
      enrollments: [],
      summary: { total: 0, active: 0, retired: 0 },
      pagination: { current_page: 1, last_page: 1, total: 0 },
      filters: {
        academic_year_id: null,
        course_section_id: null,
        enrollment_status: null,
        search: "",
      },
      showModal: false,
      actionType: null,
      selectedEnrollment: null,
      actionForm: emptyActionForm(),
    };
  },
  computed: {
    academicYearOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.academic_years || []).map((year) => ({
          value: year.id,
          text: `${year.name}${year.is_active ? " · activo" : ""}`,
        }))
      );
    },
    courseOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.courseSections || []).map((course) => ({
          value: course.id,
          text: course.display_name,
        }))
      );
    },
    actionCourseOptions() {
      return [{ value: null, text: "Seleccionar..." }].concat(
        (this.courseSections || []).map((course) => ({
          value: course.id,
          text: course.display_name,
        }))
      );
    },
    statusOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.enrollment_statuses || []).map((status) => ({
          value: status.value,
          text: status.label,
        }))
      );
    },
    reentryStatusOptions() {
      return [
        { value: "regular", text: "Regular" },
        { value: "matriculada", text: "Matriculada" },
        { value: "suspendida", text: "Suspendida" },
      ];
    },
    modalTitle() {
      return this.actionType === "transfer"
        ? "Cambiar de curso"
        : this.actionType === "withdraw"
          ? "Retirar estudiante"
          : "Reingresar estudiante";
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadCourses();
    await this.loadEnrollments();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/students/catalogs");
      this.catalogs = response.data;
      this.filters.academic_year_id = this.catalogs.active_academic_year_id || null;
    },
    async loadCourses() {
      const response = await axios.get("/api/students/courses", {
        params: {
          academic_year_id: this.filters.academic_year_id,
        },
      });
      this.courseSections = response.data.data || [];
    },
    async loadEnrollments(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/students/enrollment-management", {
          params: {
            page,
            academic_year_id: this.filters.academic_year_id,
            course_section_id: this.filters.course_section_id,
            enrollment_status: this.filters.enrollment_status,
            search: this.filters.search || null,
          },
        });

        this.enrollments = response.data.data || [];
        this.summary = response.data.summary || { total: 0, active: 0, retired: 0 };
        this.pagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          total: response.data.total,
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    async onAcademicYearChange() {
      this.filters.course_section_id = null;
      await this.loadCourses();
      await this.loadEnrollments(1);
    },
    openTransfer(item) {
      this.actionType = "transfer";
      this.selectedEnrollment = item;
      this.actionForm = {
        ...emptyActionForm(),
        id: item.id,
        course_section_id: item.course_section_id,
      };
      this.showModal = true;
    },
    openWithdraw(item) {
      this.actionType = "withdraw";
      this.selectedEnrollment = item;
      this.actionForm = {
        ...emptyActionForm(),
        id: item.id,
      };
      this.showModal = true;
    },
    openReenter(item) {
      this.actionType = "reenter";
      this.selectedEnrollment = item;
      this.actionForm = {
        ...emptyActionForm(),
        id: item.id,
        course_section_id: item.course_section_id,
        enrollment_status: "regular",
      };
      this.showModal = true;
    },
    quickFilter(status) {
      this.filters.enrollment_status = status;
      this.loadEnrollments(1);
    },
    async submitAction() {
      if (!this.selectedEnrollment) {
        return;
      }

      this.actionLoading = true;
      this.error = null;
      try {
        if (this.actionType === "transfer") {
          await axios.post(`/api/students/enrollment-management/${this.selectedEnrollment.id}/transfer`, {
            course_section_id: this.actionForm.course_section_id,
            effective_date: this.actionForm.effective_date,
            notes: this.actionForm.notes,
          });
        } else if (this.actionType === "withdraw") {
          await axios.post(`/api/students/enrollment-management/${this.selectedEnrollment.id}/withdraw`, {
            effective_date: this.actionForm.effective_date,
            notes: this.actionForm.notes,
          });
        } else {
          await axios.post(`/api/students/enrollment-management/${this.selectedEnrollment.id}/reenter`, {
            course_section_id: this.actionForm.course_section_id,
            effective_date: this.actionForm.effective_date,
            enrollment_status: this.actionForm.enrollment_status,
            notes: this.actionForm.notes,
          });
        }

        this.showModal = false;
        await this.loadEnrollments(this.pagination.current_page || 1);
        this.showSuccessAlert("Movimiento guardado", "La matrícula anual fue actualizada correctamente.");
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
      } finally {
        this.actionLoading = false;
      }
    },
    resetFilters() {
      this.filters = {
        academic_year_id: this.catalogs.active_academic_year_id || null,
        course_section_id: null,
        enrollment_status: null,
        search: "",
      };

      this.loadCourses().then(() => this.loadEnrollments(1));
    },
    lastMovement(item) {
      return item.movements?.[0] || null;
    },
    showSuccessAlert(title, text) {
      return Swal.fire({
        title,
        text,
        icon: "success",
        timer: 1800,
        showConfirmButton: false,
      });
    },
    showErrorAlert(text) {
      return Swal.fire({
        title: "Error",
        text,
        icon: "error",
      });
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (
        (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
        error?.response?.data?.message ||
        error?.message ||
        "Error desconocido"
      );
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h4 class="mb-0">Cambios y retiros</h4>
        <div class="text-muted">Gestiona cambios de curso, retiros y reingresos por año académico.</div>
      </div>
      <div class="d-flex gap-2">
        <router-link to="/students" class="btn btn-outline-secondary">Volver</router-link>
        <router-link to="/students/courses" class="btn btn-outline-secondary">Cursos</router-link>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <BCard>
          <div class="text-muted small mb-1">Total año</div>
          <div class="h4 mb-0">{{ summary.total }}</div>
        </BCard>
      </div>
      <div class="col-md-4">
        <BCard class="h-100">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="text-muted small mb-1">Activas</div>
              <div class="h4 mb-0">{{ summary.active }}</div>
            </div>
            <BButton size="sm" variant="outline-success" @click="quickFilter(null)">Ver todas</BButton>
          </div>
        </BCard>
      </div>
      <div class="col-md-4">
        <BCard class="h-100">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="text-muted small mb-1">Retiradas</div>
              <div class="h4 mb-0">{{ summary.retired }}</div>
            </div>
            <BButton size="sm" variant="outline-warning" @click="quickFilter('retirada')">Ver retiradas</BButton>
          </div>
        </BCard>
      </div>
    </div>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Año académico</label>
          <BFormSelect v-model="filters.academic_year_id" :options="academicYearOptions" @change="onAcademicYearChange" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Curso</label>
          <BFormSelect v-model="filters.course_section_id" :options="courseOptions" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="filters.enrollment_status" :options="statusOptions" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Nombre o RUT</label>
          <BFormInput v-model="filters.search" placeholder="Buscar..." @keyup.enter="loadEnrollments(1)" />
        </div>
        <div class="col-12 d-flex gap-2">
          <BButton variant="primary" @click="loadEnrollments(1)">Buscar</BButton>
          <BButton variant="outline-secondary" @click="resetFilters">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BCard>
      <BTable
        :items="enrollments"
        :busy="loading"
        responsive
        :fields="[
          { key: 'student', label: 'Estudiante' },
          { key: 'snapshot_course_display_name', label: 'Curso actual' },
          { key: 'enrollment_status', label: 'Estado' },
          { key: 'enrolled_at', label: 'Matrícula' },
          { key: 'withdrawn_at', label: 'Retiro' },
          { key: 'last_movement', label: 'Último movimiento' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #table-busy>
          <LoadingState message="Cargando matrículas..." compact />
        </template>

        <template #cell(student)="{ item }">
          <div class="fw-semibold">{{ item.student_profile?.first_name }} {{ item.student_profile?.last_name }}</div>
          <div class="text-muted small">{{ item.student_profile?.rut || "-" }}</div>
        </template>

        <template #cell(enrollment_status)="{ item }">
          <BBadge :variant="item.enrollment_status === 'retirada' ? 'warning' : 'success'">
            {{ item.enrollment_status }}
          </BBadge>
        </template>

        <template #cell(last_movement)="{ item }">
          <div v-if="lastMovement(item)">
            <div class="small fw-semibold">{{ lastMovement(item).movement_type }}</div>
            <div class="text-muted small">
              {{ lastMovement(item).snapshot_from_course_display_name || "-" }}
              →
              {{ lastMovement(item).snapshot_to_course_display_name || "-" }}
            </div>
          </div>
          <span v-else>-</span>
        </template>

        <template #cell(actions)="{ item }">
          <div class="d-flex flex-wrap gap-2">
            <router-link :to="`/students/${item.student_profile_id}`" class="btn btn-sm btn-outline-secondary">Ficha</router-link>
            <BButton v-if="item.enrollment_status !== 'retirada'" size="sm" variant="outline-primary" @click="openTransfer(item)">
              Cambiar curso
            </BButton>
            <BButton v-if="item.enrollment_status !== 'retirada'" size="sm" variant="outline-warning" @click="openWithdraw(item)">
              Retirar
            </BButton>
            <BButton v-if="item.enrollment_status === 'retirada'" size="sm" variant="outline-success" @click="openReenter(item)">
              Reingresar
            </BButton>
          </div>
        </template>
      </BTable>

      <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="text-muted small">Total: {{ pagination.total }}</div>
        <BPagination
          v-model="pagination.current_page"
          :total-rows="pagination.total"
          :per-page="20"
          @update:model-value="loadEnrollments"
        />
      </div>
    </BCard>

    <BModal v-model="showModal" :title="modalTitle" hide-footer>
      <div class="mb-3">
        <div class="fw-semibold">{{ selectedEnrollment?.student_profile?.first_name }} {{ selectedEnrollment?.student_profile?.last_name }}</div>
        <div class="text-muted small">
          {{ selectedEnrollment?.snapshot_year_name }} · {{ selectedEnrollment?.snapshot_course_display_name }}
        </div>
      </div>

      <div class="row g-3">
        <div v-if="actionType !== 'withdraw'" class="col-md-12">
          <label class="form-label">Curso destino</label>
          <BFormSelect v-model="actionForm.course_section_id" :options="actionCourseOptions" />
        </div>
        <div v-if="actionType === 'reenter'" class="col-md-12">
          <label class="form-label">Estado al reingresar</label>
          <BFormSelect v-model="actionForm.enrollment_status" :options="reentryStatusOptions" />
        </div>
        <div class="col-md-12">
          <label class="form-label">Fecha efectiva</label>
          <BFormInput v-model="actionForm.effective_date" type="date" />
        </div>
        <div class="col-md-12">
          <label class="form-label">Notas</label>
          <BFormTextarea v-model="actionForm.notes" rows="3" />
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="secondary" @click="showModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="actionLoading" @click="submitAction">
          {{ actionLoading ? "Guardando..." : "Guardar" }}
        </BButton>
      </div>
    </BModal>
  </Layout>
</template>
