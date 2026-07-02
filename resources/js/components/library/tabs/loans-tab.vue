<script>
import axios from "axios";
import Swal from "sweetalert2";
import LibraryHelpButton from "../help-button.vue";
import LibraryStatusBadge from "../status-badge.vue";
import LoadingState from "../../ui/loading-state.vue";
import {
  confirmLibraryAction,
  confirmLibraryCancel,
  formatLibraryDate,
  formatLibraryError,
  showLibrarySuccess,
} from "../module-utils";

const emptyForm = () => ({
  borrower_type: "student",
  student_profile_id: null,
  staff_id: null,
  user_id: null,
  course_section_id: null,
  biblioteca_ejemplar_id: null,
  borrowed_at: new Date().toISOString().slice(0, 10),
  due_at: new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10),
  notes: "",
});

export default {
  components: {
    LibraryHelpButton,
    LibraryStatusBadge,
    LoadingState,
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
      pagination: { current_page: 1, total: 0, per_page: 15 },
      filters: {
        search: "",
        status: null,
        student_profile_id: null,
        staff_id: null,
        course_section_id: null,
        overdue_only: false,
        date_from: "",
        date_to: "",
      },
      showModal: false,
      form: emptyForm(),
    };
  },
  mounted() {
    this.consumeRouteFilters();
    this.load();
  },
  watch: {
    "$route.query": {
      deep: true,
      handler() {
        this.consumeRouteFilters();
      },
    },
  },
  methods: {
    formatLibraryDate,
    consumeRouteFilters() {
      if (this.$route.query.student) {
        this.filters.student_profile_id = Number(this.$route.query.student);
      }
      if (this.$route.query.staff) {
        this.filters.staff_id = Number(this.$route.query.staff);
      }
    },
    async load(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/biblioteca/prestamos", {
          params: { page, ...this.filters, overdue_only: this.filters.overdue_only ? 1 : "" },
        });
        this.items = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page,
          total: response.data.total,
          per_page: response.data.per_page,
        };
      } catch (error) {
        this.error = formatLibraryError(error, "No se pudieron cargar los préstamos.");
      } finally {
        this.loading = false;
      }
    },
    openCreate() {
      this.form = emptyForm();
      this.showModal = true;
    },
    borrowerOptions() {
      if (this.form.borrower_type === "student") {
        return (this.catalogs.students || []).map((item) => ({ value: item.id, text: `${item.name} · ${item.course || "Sin curso"}` }));
      }
      if (this.form.borrower_type === "staff" || this.form.borrower_type === "teacher") {
        return (this.catalogs.staff || []).map((item) => ({ value: item.id, text: item.full_name }));
      }
      if (this.form.borrower_type === "course") {
        return (this.catalogs.courses || []).map((item) => ({ value: item.id, text: item.display_name }));
      }
      return (this.catalogs.users || []).map((item) => ({ value: item.id, text: item.name }));
    },
    selectedBorrowerModel() {
      return this.form.borrower_type === "student"
        ? "student_profile_id"
        : this.form.borrower_type === "staff" || this.form.borrower_type === "teacher"
        ? "staff_id"
        : this.form.borrower_type === "course"
        ? "course_section_id"
        : "user_id";
    },
    async save() {
      const confirmed = await confirmLibraryAction({
        title: "Confirmar préstamo",
        text: "Se registrará el préstamo seleccionado y se actualizará la disponibilidad del ejemplar.",
        confirmButtonText: "Sí, registrar",
      });
      if (!confirmed.isConfirmed) return;

      this.saving = true;
      try {
        const payload = {
          borrower_type: this.form.borrower_type,
          student_profile_id: this.form.student_profile_id || null,
          staff_id: this.form.staff_id || null,
          user_id: this.form.user_id || null,
          course_section_id: this.form.course_section_id || null,
          biblioteca_ejemplar_id: this.form.biblioteca_ejemplar_id,
          borrowed_at: this.form.borrowed_at,
          due_at: this.form.due_at,
          notes: this.form.notes || null,
        };
        await axios.post("/api/biblioteca/prestamos", payload);
        this.showModal = false;
        this.$emit("refresh-catalogs");
        await this.load(this.pagination.current_page);
        await showLibrarySuccess("Préstamo registrado correctamente.");
      } catch (error) {
        this.error = formatLibraryError(error);
      } finally {
        this.saving = false;
      }
    },
    async renew(item) {
      const result = await Swal.fire({
        title: "Renovar préstamo",
        input: "date",
        inputLabel: "Nueva fecha de devolución",
        inputValue: item.due_at,
        showCancelButton: true,
        confirmButtonText: "Renovar",
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed || !result.value) return;

      await axios.post(`/api/biblioteca/prestamos/${item.id}/renew`, { due_at: result.value });
      await this.load(this.pagination.current_page);
      await showLibrarySuccess("Préstamo renovado correctamente.");
    },
    async registerReturn(item) {
      const result = await Swal.fire({
        title: "Registrar devolución",
        input: "select",
        inputOptions: {
          bueno: "Bueno",
          regular: "Regular",
          danado: "Dañado",
          perdido: "Perdido",
        },
        inputPlaceholder: "Selecciona condición",
        showCancelButton: true,
        confirmButtonText: "Registrar devolución",
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed || !result.value) return;

      await axios.post(`/api/biblioteca/prestamos/${item.id}/return`, {
        returned_condition: result.value,
      });
      this.$emit("refresh-catalogs");
      await this.load(this.pagination.current_page);
      await showLibrarySuccess("Devolución registrada correctamente.");
    },
    async cancel(item) {
      const confirmed = await confirmLibraryAction({
        title: "Cancelar préstamo",
        text: `Se cancelará el préstamo ${item.loan_code}.`,
        confirmButtonText: "Sí, cancelar",
        icon: "warning",
      });

      if (!confirmed.isConfirmed) return;

      await axios.post(`/api/biblioteca/prestamos/${item.id}/cancel`);
      this.$emit("refresh-catalogs");
      await this.load(this.pagination.current_page);
      await showLibrarySuccess("Préstamo cancelado correctamente.");
    },
    async closeModal() {
      const confirmed = await confirmLibraryCancel("el registro del préstamo");
      if (confirmed.isConfirmed) this.showModal = false;
    },
  },
};
</script>

<template>
  <div class="d-flex flex-column gap-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="fw-semibold">Flujo de préstamos</div>
      <div class="d-flex gap-2">
        <LibraryHelpButton
          title="Ayuda: flujo de préstamos"
          text="Esta sección permite prestar ejemplares, renovar fechas, registrar devoluciones, marcar pérdidas o daños y controlar la mora por usuario o curso."
        />
        <BButton variant="primary" @click="openCreate">Nuevo préstamo</BButton>
      </div>
    </div>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <BCard class="border-0 shadow-sm">
      <div class="row g-3 align-items-end">
        <div class="col-md-4"><label class="form-label">Buscar</label><BFormInput v-model="filters.search" placeholder="Código, usuario, obra..." @keyup.enter="load" /></div>
        <div class="col-md-2"><label class="form-label">Estado</label><BFormSelect v-model="filters.status" :options="[{ value: null, text: 'Todos' }].concat((catalogs.loan_statuses || []).map((item) => ({ value: item.value, text: item.label })))" /></div>
        <div class="col-md-2"><label class="form-label">Estudiante</label><BFormSelect v-model="filters.student_profile_id" :options="[{ value: null, text: 'Todos' }].concat((catalogs.students || []).map((item) => ({ value: item.id, text: item.name })))" /></div>
        <div class="col-md-2"><label class="form-label">Funcionario</label><BFormSelect v-model="filters.staff_id" :options="[{ value: null, text: 'Todos' }].concat((catalogs.staff || []).map((item) => ({ value: item.id, text: item.full_name })))" /></div>
        <div class="col-md-2"><label class="form-label">Curso</label><BFormSelect v-model="filters.course_section_id" :options="[{ value: null, text: 'Todos' }].concat((catalogs.courses || []).map((item) => ({ value: item.id, text: item.display_name })))" /></div>
        <div class="col-md-2"><label class="form-label">Desde</label><BFormInput v-model="filters.date_from" type="date" /></div>
        <div class="col-md-2"><label class="form-label">Hasta</label><BFormInput v-model="filters.date_to" type="date" /></div>
        <div class="col-md-3 d-flex align-items-center"><BFormCheckbox v-model="filters.overdue_only">Solo mora</BFormCheckbox></div>
        <div class="col-md-3">
          <BButton variant="secondary" class="me-2" @click="load">Filtrar</BButton>
          <BButton variant="light" @click="filters = { search: '', status: null, student_profile_id: null, staff_id: null, course_section_id: null, overdue_only: false, date_from: '', date_to: '' }; load();">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BCard class="border-0 shadow-sm">
      <LoadingState v-if="loading" message="Cargando préstamos..." compact />
      <BTable
        v-else
        responsive
        :items="items"
        :fields="[
          { key: 'loan_code', label: 'Código' },
          { key: 'borrower_name_snapshot', label: 'Usuario' },
          { key: 'obra_title', label: 'Obra' },
          { key: 'due_at', label: 'Vence' },
          { key: 'status', label: 'Estado' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #cell(obra_title)="{ item }">
          <div class="fw-semibold">{{ item.obra?.title || "-" }}</div>
          <div class="small text-muted">{{ item.ejemplar?.code || "-" }}</div>
        </template>
        <template #cell(due_at)="{ item }">
          <div>{{ formatLibraryDate(item.due_at) }}</div>
          <div v-if="item.overdue_days" class="small text-danger">{{ item.overdue_days }} día(s) de atraso</div>
        </template>
        <template #cell(status)="{ item }"><LibraryStatusBadge :status="item.status" /></template>
        <template #cell(actions)="{ item }">
          <div class="d-flex flex-wrap gap-2">
            <BButton size="sm" variant="outline-info" @click="renew(item)">Renovar</BButton>
            <BButton size="sm" variant="outline-success" @click="registerReturn(item)">Devolver</BButton>
            <BButton size="sm" variant="outline-danger" @click="cancel(item)">Cancelar</BButton>
          </div>
        </template>
      </BTable>
      <div class="d-flex justify-content-end mt-3">
        <BPagination v-model="pagination.current_page" :total-rows="pagination.total" :per-page="pagination.per_page" @update:model-value="load" />
      </div>
    </BCard>

    <BModal v-model="showModal" size="lg" title="Nuevo préstamo" hide-footer>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-muted small">Entrega controlada con validación de disponibilidad y mora.</div>
        <LibraryHelpButton
          title="Ayuda: nuevo préstamo"
          text="Selecciona el tipo de usuario, el ejemplar y la fecha comprometida de devolución. El sistema validará disponibilidad y mora existente."
        />
      </div>
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Tipo de usuario</label><BFormSelect v-model="form.borrower_type" :options="(catalogs.borrower_types || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-8">
          <label class="form-label">Usuario / curso</label>
          <BFormSelect
            :model-value="form[selectedBorrowerModel()]"
            :options="borrowerOptions()"
            @update:model-value="form[selectedBorrowerModel()] = $event"
          />
        </div>
        <div class="col-md-8"><label class="form-label">Ejemplar</label><BFormSelect v-model="form.biblioteca_ejemplar_id" :options="(catalogs.exemplars || []).map((item) => ({ value: item.id, text: item.label }))" /></div>
        <div class="col-md-2"><label class="form-label">Fecha préstamo</label><BFormInput v-model="form.borrowed_at" type="date" /></div>
        <div class="col-md-2"><label class="form-label">Fecha devolución</label><BFormInput v-model="form.due_at" type="date" /></div>
        <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="form.notes" rows="3" /></div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-4">
        <BButton variant="light" @click="closeModal">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">{{ saving ? "Guardando..." : "Registrar préstamo" }}</BButton>
      </div>
    </BModal>
  </div>
</template>
