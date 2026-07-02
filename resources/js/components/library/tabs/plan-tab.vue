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
  id: null,
  academic_year_id: null,
  course_section_id: null,
  subject: "",
  responsible_staff_id: null,
  biblioteca_obra_id: null,
  period: "",
  start_date: "",
  end_date: "",
  objective: "",
  associated_activity: "",
  evaluation_description: "",
  required_copies: 1,
  status: "planificado",
  notes: "",
  attachments_text: "",
});

export default {
  components: {
    LibraryHelpButton,
    LibraryStatusBadge,
    LoadingState,
  },
  props: {
    catalogs: { type: Object, required: true },
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
        academic_year_id: null,
        course_section_id: null,
        status: null,
      },
      showModal: false,
      form: emptyForm(),
    };
  },
  mounted() {
    this.load();
    if (this.$route.query.course) {
      this.filters.course_section_id = Number(this.$route.query.course);
      this.load();
    }
  },
  methods: {
    formatLibraryDate,
    async load(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/biblioteca/plan-lector", {
          params: { page, ...this.filters },
        });
        this.items = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page,
          total: response.data.total,
          per_page: response.data.per_page,
        };
      } catch (error) {
        this.error = formatLibraryError(error, "No se pudo cargar el plan lector.");
      } finally {
        this.loading = false;
      }
    },
    openCreate() {
      this.form = emptyForm();
      this.showModal = true;
    },
    openEdit(item) {
      this.form = {
        ...emptyForm(),
        id: item.id,
        academic_year_id: item.academic_year_id,
        course_section_id: item.course_section_id,
        subject: item.subject,
        responsible_staff_id: item.responsible_staff_id || null,
        biblioteca_obra_id: item.biblioteca_obra_id,
        period: item.period || "",
        start_date: item.start_date || "",
        end_date: item.end_date || "",
        objective: item.objective || "",
        associated_activity: item.associated_activity || "",
        evaluation_description: item.evaluation_description || "",
        required_copies: item.required_copies || 1,
        status: item.status,
        notes: item.notes || "",
        attachments_text: (item.attachments || []).join(", "),
      };
      this.showModal = true;
    },
    async save() {
      const confirmed = await confirmLibraryAction({
        title: this.form.id ? "Actualizar plan lector" : "Crear plan lector",
        text: "Se guardará la planificación lectora del curso seleccionado.",
        confirmButtonText: this.form.id ? "Actualizar" : "Guardar",
      });
      if (!confirmed.isConfirmed) return;

      this.saving = true;
      try {
        const payload = {
          academic_year_id: this.form.academic_year_id,
          course_section_id: this.form.course_section_id,
          subject: this.form.subject,
          responsible_staff_id: this.form.responsible_staff_id || null,
          biblioteca_obra_id: this.form.biblioteca_obra_id,
          period: this.form.period || null,
          start_date: this.form.start_date,
          end_date: this.form.end_date,
          objective: this.form.objective || null,
          associated_activity: this.form.associated_activity || null,
          evaluation_description: this.form.evaluation_description || null,
          required_copies: this.form.required_copies,
          status: this.form.status,
          notes: this.form.notes || null,
          attachments: this.form.attachments_text.split(",").map((item) => item.trim()).filter(Boolean),
        };
        if (this.form.id) {
          await axios.put(`/api/biblioteca/plan-lector/${this.form.id}`, payload);
        } else {
          await axios.post("/api/biblioteca/plan-lector", payload);
        }
        this.showModal = false;
        await this.load(this.pagination.current_page);
        await showLibrarySuccess("Plan lector guardado correctamente.");
      } catch (error) {
        this.error = formatLibraryError(error);
      } finally {
        this.saving = false;
      }
    },
    async massLoan(item) {
      const result = await Swal.fire({
        title: "Préstamo masivo por curso",
        input: "number",
        inputLabel: "Cantidad de ejemplares",
        inputValue: item.required_copies,
        showCancelButton: true,
        confirmButtonText: "Ejecutar",
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed || !result.value) return;

      await axios.post(`/api/biblioteca/plan-lector/${item.id}/mass-loan`, {
        quantity: Number(result.value),
        due_at: item.end_date,
      });
      this.$emit("refresh-catalogs");
      await this.load(this.pagination.current_page);
      await showLibrarySuccess("Préstamo masivo ejecutado correctamente.");
    },
    async closeModal() {
      const confirmed = await confirmLibraryCancel("la edición del plan lector");
      if (confirmed.isConfirmed) this.showModal = false;
    },
  },
};
</script>

<template>
  <div class="d-flex flex-column gap-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="fw-semibold">Plan lector por curso y año</div>
      <div class="d-flex gap-2">
        <LibraryHelpButton
          title="Ayuda: plan lector"
          text="Aquí se programa la lectura anual por curso, asignatura y docente responsable, incluyendo disponibilidad de ejemplares y préstamo masivo."
        />
        <BButton variant="primary" @click="openCreate">Nuevo plan</BButton>
      </div>
    </div>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <BCard class="border-0 shadow-sm">
      <div class="row g-3 align-items-end">
        <div class="col-md-4"><label class="form-label">Buscar</label><BFormInput v-model="filters.search" placeholder="Obra, asignatura, periodo..." @keyup.enter="load" /></div>
        <div class="col-md-2"><label class="form-label">Año</label><BFormSelect v-model="filters.academic_year_id" :options="[{ value: null, text: 'Todos' }].concat((catalogs.academic_years || []).map((item) => ({ value: item.id, text: item.name })))" /></div>
        <div class="col-md-3"><label class="form-label">Curso</label><BFormSelect v-model="filters.course_section_id" :options="[{ value: null, text: 'Todos' }].concat((catalogs.courses || []).map((item) => ({ value: item.id, text: item.display_name })))" /></div>
        <div class="col-md-2"><label class="form-label">Estado</label><BFormSelect v-model="filters.status" :options="[{ value: null, text: 'Todos' }].concat((catalogs.plan_statuses || []).map((item) => ({ value: item.value, text: item.label })))" /></div>
        <div class="col-md-3">
          <BButton variant="secondary" class="me-2" @click="load">Filtrar</BButton>
          <BButton variant="light" @click="filters = { search: '', academic_year_id: null, course_section_id: null, status: null }; load();">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BCard class="border-0 shadow-sm">
      <LoadingState v-if="loading" message="Cargando planes lectores..." compact />
      <BTable
        v-else
        responsive
        :items="items"
        :fields="[
          { key: 'course', label: 'Curso' },
          { key: 'subject', label: 'Asignatura' },
          { key: 'book', label: 'Libro' },
          { key: 'copies', label: 'Ejemplares' },
          { key: 'status', label: 'Estado' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #cell(course)="{ item }">{{ item.course_section?.display_name || "-" }}</template>
        <template #cell(book)="{ item }">
          <div class="fw-semibold">{{ item.obra?.title || "-" }}</div>
          <div class="small text-muted">{{ item.period || "-" }} · {{ formatLibraryDate(item.start_date) }} a {{ formatLibraryDate(item.end_date) }}</div>
        </template>
        <template #cell(copies)="{ item }">{{ item.available_copies }} / {{ item.required_copies }}</template>
        <template #cell(status)="{ item }"><LibraryStatusBadge :status="item.status" /></template>
        <template #cell(actions)="{ item }">
          <div class="d-flex gap-2 flex-wrap">
            <BButton size="sm" variant="outline-primary" @click="openEdit(item)">Editar</BButton>
            <BButton size="sm" variant="outline-success" @click="massLoan(item)">Préstamo masivo</BButton>
          </div>
        </template>
      </BTable>
      <div class="d-flex justify-content-end mt-3">
        <BPagination v-model="pagination.current_page" :total-rows="pagination.total" :per-page="pagination.per_page" @update:model-value="load" />
      </div>
    </BCard>

    <BModal v-model="showModal" size="xl" :title="form.id ? 'Editar plan lector' : 'Nuevo plan lector'" hide-footer>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-muted small">Planificación anual vinculada a disponibilidad real de ejemplares.</div>
        <LibraryHelpButton
          title="Ayuda: formulario de plan lector"
          text="Define curso, asignatura, docente responsable, lectura, fechas, cantidad requerida, actividades asociadas y estado del plan."
        />
      </div>
      <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Año escolar</label><BFormSelect v-model="form.academic_year_id" :options="(catalogs.academic_years || []).map((item) => ({ value: item.id, text: item.name }))" /></div>
        <div class="col-md-3"><label class="form-label">Curso</label><BFormSelect v-model="form.course_section_id" :options="(catalogs.courses || []).map((item) => ({ value: item.id, text: item.display_name }))" /></div>
        <div class="col-md-3"><label class="form-label">Asignatura</label><BFormInput v-model="form.subject" /></div>
        <div class="col-md-3"><label class="form-label">Docente responsable</label><BFormSelect v-model="form.responsible_staff_id" :options="[{ value: null, text: 'Sin docente' }].concat((catalogs.staff || []).map((item) => ({ value: item.id, text: item.full_name })))" /></div>
        <div class="col-md-6"><label class="form-label">Libro / recurso</label><BFormSelect v-model="form.biblioteca_obra_id" :options="(catalogs.works || []).map((item) => ({ value: item.id, text: item.title }))" /></div>
        <div class="col-md-2"><label class="form-label">Periodo</label><BFormInput v-model="form.period" /></div>
        <div class="col-md-2"><label class="form-label">Inicio</label><BFormInput v-model="form.start_date" type="date" /></div>
        <div class="col-md-2"><label class="form-label">Término</label><BFormInput v-model="form.end_date" type="date" /></div>
        <div class="col-md-2"><label class="form-label">Ejemplares requeridos</label><BFormInput v-model="form.required_copies" type="number" min="1" /></div>
        <div class="col-md-2"><label class="form-label">Estado</label><BFormSelect v-model="form.status" :options="(catalogs.plan_statuses || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-12"><label class="form-label">Objetivo</label><BFormTextarea v-model="form.objective" rows="2" /></div>
        <div class="col-12"><label class="form-label">Actividad asociada</label><BFormTextarea v-model="form.associated_activity" rows="2" /></div>
        <div class="col-12"><label class="form-label">Evaluaciones / guías</label><BFormTextarea v-model="form.evaluation_description" rows="2" /></div>
        <div class="col-12"><label class="form-label">Adjuntos (URLs)</label><BFormInput v-model="form.attachments_text" placeholder="Separar por coma" /></div>
        <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="form.notes" rows="2" /></div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-4">
        <BButton variant="light" @click="closeModal">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">{{ saving ? "Guardando..." : form.id ? "Actualizar" : "Guardar" }}</BButton>
      </div>
    </BModal>
  </div>
</template>
