<script>
import axios from "axios";
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
  requester_type: "student",
  requested_by_user_id: null,
  student_profile_id: null,
  staff_id: null,
  course_section_id: null,
  biblioteca_obra_id: null,
  biblioteca_ejemplar_id: null,
  requested_at: new Date().toISOString().slice(0, 16),
  pickup_at: "",
  expected_return_at: "",
  purpose: "",
  status: "solicitada",
  notes: "",
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
        status: null,
        resource_type: null,
        student_profile_id: null,
        staff_id: null,
        course_section_id: null,
        date_from: "",
        date_to: "",
      },
      showModal: false,
      form: emptyForm(),
    };
  },
  mounted() {
    this.load();
  },
  methods: {
    formatLibraryDate,
    requesterOptions() {
      return this.form.requester_type === "student"
        ? (this.catalogs.students || []).map((item) => ({ value: item.id, text: item.name }))
        : this.form.requester_type === "staff" || this.form.requester_type === "teacher"
        ? (this.catalogs.staff || []).map((item) => ({ value: item.id, text: item.full_name }))
        : this.form.requester_type === "course"
        ? (this.catalogs.courses || []).map((item) => ({ value: item.id, text: item.display_name }))
        : (this.catalogs.users || []).map((item) => ({ value: item.id, text: item.name }));
    },
    requesterModel() {
      return this.form.requester_type === "student"
        ? "student_profile_id"
        : this.form.requester_type === "staff" || this.form.requester_type === "teacher"
        ? "staff_id"
        : this.form.requester_type === "course"
        ? "course_section_id"
        : "requested_by_user_id";
    },
    async load(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/biblioteca/reservas", {
          params: { page, ...this.filters },
        });
        this.items = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page,
          total: response.data.total,
          per_page: response.data.per_page,
        };
      } catch (error) {
        this.error = formatLibraryError(error, "No se pudieron cargar las reservas.");
      } finally {
        this.loading = false;
      }
    },
    openCreate() {
      this.form = emptyForm();
      this.showModal = true;
    },
    async save() {
      const confirmed = await confirmLibraryAction({
        title: "Confirmar reserva",
        text: "Se registrará la reserva del recurso seleccionado.",
        confirmButtonText: "Sí, reservar",
      });
      if (!confirmed.isConfirmed) return;

      this.saving = true;
      try {
        const payload = {
          requester_type: this.form.requester_type,
          requested_by_user_id: this.form.requested_by_user_id || null,
          student_profile_id: this.form.student_profile_id || null,
          staff_id: this.form.staff_id || null,
          course_section_id: this.form.course_section_id || null,
          biblioteca_obra_id: this.form.biblioteca_obra_id || null,
          biblioteca_ejemplar_id: this.form.biblioteca_ejemplar_id || null,
          requested_at: this.form.requested_at || null,
          pickup_at: this.form.pickup_at || null,
          expected_return_at: this.form.expected_return_at || null,
          purpose: this.form.purpose || null,
          status: this.form.status,
          notes: this.form.notes || null,
        };
        await axios.post("/api/biblioteca/reservas", payload);
        this.showModal = false;
        this.$emit("refresh-catalogs");
        await this.load(this.pagination.current_page);
        await showLibrarySuccess("Reserva registrada correctamente.");
      } catch (error) {
        this.error = formatLibraryError(error);
      } finally {
        this.saving = false;
      }
    },
    async transition(item, action, message) {
      const confirmed = await confirmLibraryAction({
        title: message,
        text: `Se ejecutará la acción sobre la reserva ${item.reservation_code}.`,
        confirmButtonText: "Confirmar",
      });
      if (!confirmed.isConfirmed) return;

      await axios.post(`/api/biblioteca/reservas/${item.id}/${action}`);
      this.$emit("refresh-catalogs");
      await this.load(this.pagination.current_page);
      await showLibrarySuccess("Reserva actualizada correctamente.");
    },
    async closeModal() {
      const confirmed = await confirmLibraryCancel("el registro de la reserva");
      if (confirmed.isConfirmed) this.showModal = false;
    },
  },
};
</script>

<template>
  <div class="d-flex flex-column gap-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="fw-semibold">Reservas de recursos</div>
      <div class="d-flex gap-2">
        <LibraryHelpButton
          title="Ayuda: reservas de recursos"
          text="Aquí se gestionan solicitudes, aprobación, retiro y devolución de recursos bibliotecarios con control de disponibilidad."
        />
        <BButton variant="primary" @click="openCreate">Nueva reserva</BButton>
      </div>
    </div>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <BCard class="border-0 shadow-sm">
      <div class="row g-3 align-items-end">
        <div class="col-md-4"><label class="form-label">Buscar</label><BFormInput v-model="filters.search" placeholder="Código, recurso, tipo..." @keyup.enter="load" /></div>
        <div class="col-md-2"><label class="form-label">Estado</label><BFormSelect v-model="filters.status" :options="[{ value: null, text: 'Todos' }].concat((catalogs.reservation_statuses || []).map((item) => ({ value: item.value, text: item.label })))" /></div>
        <div class="col-md-2"><label class="form-label">Tipo recurso</label><BFormSelect v-model="filters.resource_type" :options="[{ value: null, text: 'Todos' }].concat((catalogs.material_types || []).map((item) => ({ value: item.value, text: item.label })))" /></div>
        <div class="col-md-2"><label class="form-label">Estudiante</label><BFormSelect v-model="filters.student_profile_id" :options="[{ value: null, text: 'Todos' }].concat((catalogs.students || []).map((item) => ({ value: item.id, text: item.name })))" /></div>
        <div class="col-md-2"><label class="form-label">Funcionario</label><BFormSelect v-model="filters.staff_id" :options="[{ value: null, text: 'Todos' }].concat((catalogs.staff || []).map((item) => ({ value: item.id, text: item.full_name })))" /></div>
        <div class="col-md-2"><label class="form-label">Curso</label><BFormSelect v-model="filters.course_section_id" :options="[{ value: null, text: 'Todos' }].concat((catalogs.courses || []).map((item) => ({ value: item.id, text: item.display_name })))" /></div>
        <div class="col-md-2"><label class="form-label">Desde</label><BFormInput v-model="filters.date_from" type="date" /></div>
        <div class="col-md-2"><label class="form-label">Hasta</label><BFormInput v-model="filters.date_to" type="date" /></div>
        <div class="col-md-3">
          <BButton variant="secondary" class="me-2" @click="load">Filtrar</BButton>
          <BButton variant="light" @click="filters = { search: '', status: null, resource_type: null, student_profile_id: null, staff_id: null, course_section_id: null, date_from: '', date_to: '' }; load();">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BCard class="border-0 shadow-sm">
      <LoadingState v-if="loading" message="Cargando reservas..." compact />
      <BTable
        v-else
        responsive
        :items="items"
        :fields="[
          { key: 'reservation_code', label: 'Código' },
          { key: 'obra_title', label: 'Recurso' },
          { key: 'resource_type', label: 'Tipo' },
          { key: 'pickup_at', label: 'Retiro' },
          { key: 'status', label: 'Estado' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #cell(obra_title)="{ item }">
          <div class="fw-semibold">{{ item.obra?.title || item.ejemplar?.label || "-" }}</div>
          <div class="small text-muted">{{ item.ejemplar?.code || item.resource_type }}</div>
        </template>
        <template #cell(resource_type)="{ item }">{{ item.resource_type }}</template>
        <template #cell(pickup_at)="{ item }">{{ formatLibraryDate(item.pickup_at) }}</template>
        <template #cell(status)="{ item }"><LibraryStatusBadge :status="item.status" /></template>
        <template #cell(actions)="{ item }">
          <div class="d-flex flex-wrap gap-2">
            <BButton size="sm" variant="outline-success" @click="transition(item, 'approve', 'Aprobar reserva')">Aprobar</BButton>
            <BButton size="sm" variant="outline-primary" @click="transition(item, 'checkout', 'Registrar retiro')">Retirar</BButton>
            <BButton size="sm" variant="outline-info" @click="transition(item, 'return', 'Registrar devolución')">Devolver</BButton>
            <BButton size="sm" variant="outline-danger" @click="transition(item, 'cancel', 'Cancelar reserva')">Cancelar</BButton>
          </div>
        </template>
      </BTable>
      <div class="d-flex justify-content-end mt-3">
        <BPagination v-model="pagination.current_page" :total-rows="pagination.total" :per-page="pagination.per_page" @update:model-value="load" />
      </div>
    </BCard>

    <BModal v-model="showModal" size="lg" title="Nueva reserva de recurso" hide-footer>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-muted small">Reserva operativa con disponibilidad validada.</div>
        <LibraryHelpButton
          title="Ayuda: nueva reserva"
          text="Selecciona solicitante, recurso, fecha de retiro y devolución esperada. Puedes reservar una obra o un ejemplar específico."
        />
      </div>
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Tipo solicitante</label><BFormSelect v-model="form.requester_type" :options="(catalogs.reservation_requester_types || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-8"><label class="form-label">Solicitante / curso</label><BFormSelect :model-value="form[requesterModel()]" :options="requesterOptions()" @update:model-value="form[requesterModel()] = $event" /></div>
        <div class="col-md-6"><label class="form-label">Obra / recurso</label><BFormSelect v-model="form.biblioteca_obra_id" :options="(catalogs.works || []).map((item) => ({ value: item.id, text: item.title }))" /></div>
        <div class="col-md-6"><label class="form-label">Ejemplar específico</label><BFormSelect v-model="form.biblioteca_ejemplar_id" :options="[{ value: null, text: 'Asignación automática' }].concat((catalogs.exemplars || []).map((item) => ({ value: item.id, text: item.label })))" /></div>
        <div class="col-md-4"><label class="form-label">Fecha solicitud</label><BFormInput v-model="form.requested_at" type="datetime-local" /></div>
        <div class="col-md-4"><label class="form-label">Fecha retiro</label><BFormInput v-model="form.pickup_at" type="datetime-local" /></div>
        <div class="col-md-4"><label class="form-label">Devolución esperada</label><BFormInput v-model="form.expected_return_at" type="datetime-local" /></div>
        <div class="col-md-4"><label class="form-label">Estado inicial</label><BFormSelect v-model="form.status" :options="(catalogs.reservation_statuses || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-12"><label class="form-label">Motivo</label><BFormTextarea v-model="form.purpose" rows="2" /></div>
        <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="form.notes" rows="2" /></div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-4">
        <BButton variant="light" @click="closeModal">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">{{ saving ? "Guardando..." : "Registrar reserva" }}</BButton>
      </div>
    </BModal>
  </div>
</template>
