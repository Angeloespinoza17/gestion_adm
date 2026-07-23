<script>
import axios from "axios";
import CentroApuntesHelpButton from "../help-button.vue";
import CentroApuntesModalIntro from "../modal-intro.vue";
import CentroApuntesSectionToolbar from "../section-toolbar.vue";
import CentroApuntesStatusBadge from "../status-badge.vue";
import LoadingState from "../../ui/loading-state.vue";
import {
  confirmCentroApuntesAction,
  confirmCentroApuntesCancel,
  formatCentroApuntesDateTime,
  formatCentroApuntesError,
  normalizeCentroApuntesNullableFields,
  normalizeOptions,
  showCentroApuntesSuccess,
} from "../module-utils";

const emptyForm = () => ({
  id: null,
  name: "",
  internal_code: "",
  type: "impresora",
  brand: null,
  model: null,
  location: null,
  responsible_user_id: null,
  status: "activa",
  observations: null,
});

export default {
  components: {
    CentroApuntesHelpButton,
    CentroApuntesModalIntro,
    CentroApuntesSectionToolbar,
    CentroApuntesStatusBadge,
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
      detailLoading: false,
      error: null,
      items: [],
      pagination: { current_page: 1, total: 0, per_page: 15 },
      filters: {
        search: "",
        type: null,
        status: null,
      },
      showModal: false,
      showDetailModal: false,
      form: emptyForm(),
      selectedMachine: null,
    };
  },
  computed: {
    canManage() {
      return Boolean(this.catalogs.capabilities?.can_manage_machines);
    },
    typeOptions() {
      return normalizeOptions(this.catalogs.machine_types || []);
    },
    statusOptions() {
      return normalizeOptions(this.catalogs.machine_statuses || []);
    },
    userOptions() {
      return normalizeOptions(this.catalogs.users || []);
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    formatCentroApuntesDateTime,
    async load(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/centro-apuntes/maquinas", {
          params: { page, ...this.filters },
        });
        this.items = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page,
          total: response.data.total,
          per_page: response.data.per_page,
        };
      } catch (error) {
        this.error = formatCentroApuntesError(error, "No se pudieron cargar las máquinas.");
      } finally {
        this.loading = false;
      }
    },
    resetForm() {
      this.form = emptyForm();
    },
    openCreate() {
      this.resetForm();
      this.showModal = true;
    },
    openEdit(item) {
      this.form = {
        id: item.id,
        name: item.name,
        internal_code: item.internal_code,
        type: item.type,
        brand: item.brand ?? null,
        model: item.model ?? null,
        location: item.location ?? null,
        responsible_user_id: item.responsible_user_id || null,
        status: item.status,
        observations: item.observations ?? null,
      };
      this.showModal = true;
    },
    async openUsage(item) {
      this.detailLoading = true;
      try {
        const response = await axios.get(`/api/centro-apuntes/maquinas/${item.id}`);
        this.selectedMachine = response.data.data;
        this.showDetailModal = true;
      } catch (error) {
        this.error = formatCentroApuntesError(error, "No se pudo cargar el historial de uso de la máquina.");
      } finally {
        this.detailLoading = false;
      }
    },
    async save() {
      const confirmed = await confirmCentroApuntesAction({
        title: this.form.id ? "Guardar máquina" : "Registrar máquina",
        text: this.form.id
          ? "Se actualizarán los datos de la máquina seleccionada."
          : "Se registrará una nueva máquina para asignación de tareas.",
        confirmButtonText: "Guardar",
      });

      if (!confirmed.isConfirmed) return;

      this.saving = true;
      try {
        const payload = normalizeCentroApuntesNullableFields(this.form, [
          "brand",
          "model",
          "location",
          "responsible_user_id",
          "observations",
        ]);
        if (this.form.id) {
          await axios.put(`/api/centro-apuntes/maquinas/${this.form.id}`, payload);
        } else {
          await axios.post("/api/centro-apuntes/maquinas", payload);
        }
        this.showModal = false;
        this.$emit("refresh-catalogs");
        await this.load(this.pagination.current_page);
        await showCentroApuntesSuccess(this.form.id ? "Máquina actualizada correctamente." : "Máquina registrada correctamente.");
      } catch (error) {
        this.error = formatCentroApuntesError(error);
      } finally {
        this.saving = false;
      }
    },
    async destroy(item) {
      const confirmed = await confirmCentroApuntesAction({
        title: "Eliminar máquina",
        text: `Se eliminará la máquina ${item.name} si no tiene solicitudes asociadas.`,
        confirmButtonText: "Sí, eliminar",
        icon: "warning",
      });

      if (!confirmed.isConfirmed) return;

      try {
        await axios.delete(`/api/centro-apuntes/maquinas/${item.id}`);
        await this.load(this.pagination.current_page);
        this.$emit("refresh-catalogs");
        await showCentroApuntesSuccess("Máquina eliminada correctamente.");
      } catch (error) {
        this.error = formatCentroApuntesError(error);
      }
    },
    clearFilters() {
      this.filters = {
        search: "",
        type: null,
        status: null,
      };
      this.load();
    },
    async closeModal() {
      const confirmed = await confirmCentroApuntesCancel("la edición de la máquina");
      if (confirmed.isConfirmed) {
        this.showModal = false;
      }
    },
  },
};
</script>

<template>
  <div class="centro-apuntes-tab d-flex flex-column gap-3">
    <CentroApuntesSectionToolbar title="Administración de máquinas" description="Controla disponibilidad, responsables y uso de los equipos de impresión." icon="bx-devices">
      <div class="d-flex gap-2">
        <CentroApuntesHelpButton
          title="Ayuda: máquinas"
          text="Aquí se gestionan impresoras, fotocopiadoras y equipos del centro de apuntes, incluyendo su estado, ubicación, responsable e historial de uso."
        />
        <BButton v-if="canManage" variant="primary" @click="openCreate"><i class="bx bx-plus me-1"></i>Nueva máquina</BButton>
      </div>
    </CentroApuntesSectionToolbar>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <BCard class="filter-card border-0 shadow-sm">
      <div class="row g-3 align-items-end">
        <div class="col-md-6">
          <label class="form-label">Buscar</label>
          <BFormInput v-model="filters.search" placeholder="Nombre, código, marca, modelo..." @keyup.enter="load" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Tipo</label>
          <BFormSelect v-model="filters.type" :options="[{ value: null, text: 'Todos' }].concat(typeOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="filters.status" :options="[{ value: null, text: 'Todos' }].concat(statusOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-3">
          <BButton variant="secondary" class="me-2" @click="load">Filtrar</BButton>
          <BButton variant="light" @click="clearFilters">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BCard class="data-card border-0 shadow-sm">
      <LoadingState v-if="loading || detailLoading" message="Cargando máquinas..." compact />
      <BTable
        v-else
        responsive
        show-empty
        empty-text="No hay máquinas que coincidan con los filtros."
        :items="items"
        :fields="[
          { key: 'name', label: 'Máquina' },
          { key: 'type', label: 'Tipo' },
          { key: 'location', label: 'Ubicación' },
          { key: 'responsibleUser', label: 'Responsable' },
          { key: 'solicitudes_count', label: 'Tareas' },
          { key: 'status', label: 'Estado' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #cell(name)="{ item }">
          <div class="fw-semibold">{{ item.name }}</div>
          <div class="small text-muted">{{ item.internal_code }} · {{ item.brand || "Sin marca" }} {{ item.model || "" }}</div>
        </template>
        <template #cell(type)="{ item }">
          {{ item.type ? item.type.replaceAll("_", " ") : "-" }}
        </template>
        <template #cell(responsibleUser)="{ item }">
          {{ item.responsible_user?.name || item.responsibleUser?.name || "-" }}
        </template>
        <template #cell(status)="{ item }">
          <CentroApuntesStatusBadge :status="item.status" />
        </template>
        <template #cell(actions)="{ item }">
          <div class="d-flex flex-wrap gap-2">
            <BButton size="sm" variant="outline-info" @click="openUsage(item)">Uso</BButton>
            <BButton v-if="canManage" size="sm" variant="outline-primary" @click="openEdit(item)">Editar</BButton>
            <BButton v-if="canManage" size="sm" variant="outline-danger" @click="destroy(item)">Eliminar</BButton>
          </div>
        </template>
      </BTable>
      <div class="d-flex justify-content-end mt-3">
        <BPagination
          v-model="pagination.current_page"
          :total-rows="pagination.total"
          :per-page="pagination.per_page"
          @update:model-value="load"
        />
      </div>
    </BCard>

    <BModal v-model="showModal" :title="form.id ? 'Editar máquina' : 'Nueva máquina'" hide-footer centered scrollable modal-class="centro-apuntes-modal">
      <CentroApuntesModalIntro title="Ficha del equipo" text="Registra la identificación del equipo; marca, modelo, ubicación y responsable son opcionales." icon="bx-devices">
        <CentroApuntesHelpButton
          title="Ayuda: formulario de máquina"
          text="Use este formulario para registrar impresoras, fotocopiadoras y equipos del centro de apuntes, indicando su ubicación, responsable y estado operativo."
        />
      </CentroApuntesModalIntro>
      <div class="modal-form-grid row g-3">
        <div class="col-md-8">
          <label class="form-label">Nombre <span class="field-required">*</span></label>
          <BFormInput v-model="form.name" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Código interno <span class="field-required">*</span></label>
          <BFormInput v-model="form.internal_code" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Tipo <span class="field-required">*</span></label>
          <BFormSelect v-model="form.type" :options="typeOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Estado <span class="field-required">*</span></label>
          <BFormSelect v-model="form.status" :options="statusOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Marca <span class="field-optional">Opcional</span></label>
          <BFormInput v-model="form.brand" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Modelo <span class="field-optional">Opcional</span></label>
          <BFormInput v-model="form.model" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Ubicación <span class="field-optional">Opcional</span></label>
          <BFormInput v-model="form.location" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Responsable <span class="field-optional">Opcional</span></label>
          <BFormSelect v-model="form.responsible_user_id" :options="[{ value: null, text: 'Sin responsable' }].concat(userOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-12">
          <label class="form-label">Observaciones <span class="field-optional">Opcional</span></label>
          <BFormTextarea v-model="form.observations" rows="3" />
        </div>
      </div>
      <div class="modal-actions">
        <BButton variant="light" @click="closeModal">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">{{ saving ? "Guardando..." : "Guardar" }}</BButton>
      </div>
    </BModal>

    <BModal v-model="showDetailModal" size="xl" title="Uso e historial de máquina" hide-footer centered scrollable modal-class="centro-apuntes-modal">
      <template v-if="selectedMachine">
        <div class="detail-grid row g-3">
          <div class="col-md-4">
            <div class="text-muted small">Máquina</div>
            <div class="fw-semibold">{{ selectedMachine.name }}</div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Estado</div>
            <CentroApuntesStatusBadge :status="selectedMachine.status" />
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Responsable</div>
            <div>{{ selectedMachine.responsible_user?.name || selectedMachine.responsibleUser?.name || "-" }}</div>
          </div>
        </div>

        <div class="mt-4">
          <div class="modal-section-title">Últimas tareas asignadas</div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Código</th>
                  <th>Solicitante</th>
                  <th>Fecha</th>
                  <th>Estado</th>
                  <th>Volumen</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="request in selectedMachine.solicitudes || []" :key="request.id">
                  <td>{{ request.request_code }}</td>
                  <td>{{ request.requested_by_name_snapshot }}</td>
                  <td>{{ formatCentroApuntesDateTime(request.requested_at) }}</td>
                  <td><CentroApuntesStatusBadge :status="request.status" /></td>
                  <td>{{ request.sheet_count }} hoja(s) · {{ request.copies_count }} copia(s)</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </BModal>
  </div>
</template>
