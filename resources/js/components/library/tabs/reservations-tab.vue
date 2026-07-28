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

const requesterTypeLabels = {
  student: "Estudiante",
  staff: "Funcionario/a",
  teacher: "Docente",
  guardian: "Apoderado/a",
  course: "Curso",
};

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
      requesterSearch: "",
      filterSearches: {
        student: "",
        staff: "",
        course: "",
      },
    };
  },
  computed: {
    requesterTypeOptions() {
      const types = this.catalogs.reservation_requester_types || [];
      return types.map((item) => ({
        value: item.value,
        text: requesterTypeLabels[item.value] || item.label,
      }));
    },
    requesterCandidates() {
      return this.buildRequesterCandidates(this.form.requester_type);
    },
    hasSelectedRequester() {
      return Boolean(this.form[this.requesterModel()]);
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    formatLibraryDate,
    requesterTypeLabel(type) {
      return requesterTypeLabels[type] || type || "Solicitante";
    },
    isTeachingStaff(item) {
      const role = `${item?.cargo?.name || ""} ${item?.cargo?.slug || ""}`
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase();

      return ["docent", "profesor", "educador"].some((term) => role.includes(term));
    },
    buildRequesterCandidates(type) {
      if (type === "student") {
        return (this.catalogs.students || []).map((item) => ({
          value: item.id,
          text: `${item.name} · ${item.rut || "Sin RUT"} · ${item.course || "Sin curso"}`,
        }));
      }

      if (type === "guardian") {
        return (this.catalogs.guardians || []).map((item) => ({
          value: item.student_profile_id,
          text: `${item.name} · ${item.rut || "Sin RUT"} · Apoderado/a de ${item.student_name}`,
        }));
      }

      if (type === "teacher" || type === "staff") {
        const staff = type === "teacher"
          ? (this.catalogs.staff || []).filter((item) => this.isTeachingStaff(item))
          : (this.catalogs.staff || []);

        return staff.map((item) => ({
          value: item.id,
          text: `${item.full_name} · ${item.rut || "Sin RUT"} · ${item.cargo?.name || this.requesterTypeLabel(type)}`,
        }));
      }

      if (type === "course") {
        return (this.catalogs.courses || []).map((item) => ({
          value: item.id,
          text: item.display_name,
        }));
      }

      return [];
    },
    requesterModel() {
      return this.form.requester_type === "student" || this.form.requester_type === "guardian"
        ? "student_profile_id"
        : this.form.requester_type === "staff" || this.form.requester_type === "teacher"
        ? "staff_id"
        : this.form.requester_type === "course"
        ? "course_section_id"
        : "requested_by_user_id";
    },
    resetRequesterIds() {
      this.form.requested_by_user_id = null;
      this.form.student_profile_id = null;
      this.form.staff_id = null;
      this.form.course_section_id = null;
    },
    changeRequesterType(type) {
      this.form.requester_type = type;
      this.requesterSearch = "";
      this.resetRequesterIds();
    },
    resolveRequesterSelection(value) {
      this.requesterSearch = value || "";
      this.resetRequesterIds();
      const candidate = this.requesterCandidates.find((item) => item.text === this.requesterSearch);
      if (candidate) {
        this.form[this.requesterModel()] = candidate.value;
      }
    },
    filterCandidates(type) {
      return this.buildRequesterCandidates(type);
    },
    resolveFilterSelection(type, value) {
      const filterFields = {
        student: "student_profile_id",
        staff: "staff_id",
        course: "course_section_id",
      };
      const field = filterFields[type];
      this.filterSearches[type] = value || "";
      this.filters[field] = this.filterCandidates(type).find(
        (item) => item.text === this.filterSearches[type]
      )?.value || null;
    },
    clearFilters() {
      this.filters = {
        search: "",
        status: null,
        resource_type: null,
        student_profile_id: null,
        staff_id: null,
        course_section_id: null,
        date_from: "",
        date_to: "",
      };
      this.filterSearches = {
        student: "",
        staff: "",
        course: "",
      };
      this.load();
    },
    resourceTypeLabel(type) {
      return (this.catalogs.material_types || []).find((item) => item.value === type)?.label
        || type
        || "Sin tipo";
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
      this.requesterSearch = "";
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
        <div class="col-md-2">
          <label class="form-label">Estudiante</label>
          <BFormInput
            :model-value="filterSearches.student"
            list="reservation-filter-students"
            placeholder="Nombre, RUT o curso"
            autocomplete="off"
            @update:model-value="resolveFilterSelection('student', $event)"
          />
          <datalist id="reservation-filter-students">
            <option v-for="item in filterCandidates('student')" :key="item.value" :value="item.text" />
          </datalist>
        </div>
        <div class="col-md-2">
          <label class="form-label">Funcionario</label>
          <BFormInput
            :model-value="filterSearches.staff"
            list="reservation-filter-staff"
            placeholder="Nombre, RUT o cargo"
            autocomplete="off"
            @update:model-value="resolveFilterSelection('staff', $event)"
          />
          <datalist id="reservation-filter-staff">
            <option v-for="item in filterCandidates('staff')" :key="item.value" :value="item.text" />
          </datalist>
        </div>
        <div class="col-md-2">
          <label class="form-label">Curso</label>
          <BFormInput
            :model-value="filterSearches.course"
            list="reservation-filter-courses"
            placeholder="Buscar curso"
            autocomplete="off"
            @update:model-value="resolveFilterSelection('course', $event)"
          />
          <datalist id="reservation-filter-courses">
            <option v-for="item in filterCandidates('course')" :key="item.value" :value="item.text" />
          </datalist>
        </div>
        <div class="col-md-2"><label class="form-label">Desde</label><BFormInput v-model="filters.date_from" type="date" /></div>
        <div class="col-md-2"><label class="form-label">Hasta</label><BFormInput v-model="filters.date_to" type="date" /></div>
        <div class="col-md-3">
          <BButton variant="secondary" class="me-2" @click="load">Filtrar</BButton>
          <BButton variant="light" @click="clearFilters">Limpiar</BButton>
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
          <div class="small text-muted">{{ item.ejemplar?.code || resourceTypeLabel(item.resource_type) }}</div>
        </template>
        <template #cell(resource_type)="{ item }">{{ resourceTypeLabel(item.resource_type) }}</template>
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
        <div class="col-md-4">
          <label class="form-label">Tipo de solicitante</label>
          <BFormSelect
            :model-value="form.requester_type"
            :options="requesterTypeOptions"
            @update:model-value="changeRequesterType"
          />
        </div>
        <div class="col-md-8">
          <label class="form-label">Buscar solicitante o curso</label>
          <BFormInput
            :model-value="requesterSearch"
            list="reservation-requester-options"
            :placeholder="`Escribe nombre, RUT o ${form.requester_type === 'course' ? 'curso' : 'dato del solicitante'}`"
            autocomplete="off"
            @update:model-value="resolveRequesterSelection"
          />
          <datalist id="reservation-requester-options">
            <option v-for="item in requesterCandidates" :key="item.value" :value="item.text" />
          </datalist>
          <div v-if="requesterSearch && !hasSelectedRequester" class="small text-warning mt-1">
            Selecciona una coincidencia de la lista para continuar.
          </div>
          <div v-else-if="hasSelectedRequester" class="small text-success mt-1">
            {{ requesterTypeLabel(form.requester_type) }} seleccionado/a correctamente.
          </div>
        </div>
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
        <BButton variant="primary" :disabled="saving || !hasSelectedRequester" @click="save">{{ saving ? "Guardando..." : "Registrar reserva" }}</BButton>
      </div>
    </BModal>
  </div>
</template>
