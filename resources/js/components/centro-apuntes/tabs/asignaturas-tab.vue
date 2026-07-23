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
  formatCentroApuntesError,
  normalizeCentroApuntesNullableFields,
  normalizeOptions,
  showCentroApuntesSuccess,
} from "../module-utils";

const emptyForm = () => ({
  id: null,
  name: "",
  code: "",
  area: null,
  education_level: null,
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
      error: null,
      items: [],
      pagination: { current_page: 1, total: 0, per_page: 15 },
      filters: {
        search: "",
        area: null,
        education_level: null,
        status: null,
      },
      showModal: false,
      form: emptyForm(),
    };
  },
  computed: {
    canManage() {
      return Boolean(this.catalogs.capabilities?.can_manage_subjects);
    },
    statusOptions() {
      return normalizeOptions(this.catalogs.subject_statuses || []);
    },
    areaOptions() {
      return normalizeOptions(this.catalogs.subject_areas || []);
    },
    levelOptions() {
      return normalizeOptions(this.catalogs.subject_levels || []);
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    async load(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/centro-apuntes/asignaturas", {
          params: { page, ...this.filters },
        });
        this.items = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page,
          total: response.data.total,
          per_page: response.data.per_page,
        };
      } catch (error) {
        this.error = formatCentroApuntesError(error, "No se pudieron cargar las asignaturas.");
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
        code: item.code,
        area: item.area ?? null,
        education_level: item.education_level ?? null,
        status: item.status,
        observations: item.observations ?? null,
      };
      this.showModal = true;
    },
    async save() {
      const confirmed = await confirmCentroApuntesAction({
        title: this.form.id ? "Guardar cambios" : "Crear asignatura",
        text: this.form.id
          ? "Se actualizará la asignatura seleccionada."
          : "Se registrará una nueva asignatura para clasificar solicitudes.",
        confirmButtonText: "Guardar",
      });

      if (!confirmed.isConfirmed) return;

      this.saving = true;
      try {
        const payload = normalizeCentroApuntesNullableFields(this.form, [
          "area",
          "education_level",
          "observations",
        ]);
        if (this.form.id) {
          await axios.put(`/api/centro-apuntes/asignaturas/${this.form.id}`, payload);
        } else {
          await axios.post("/api/centro-apuntes/asignaturas", payload);
        }
        this.showModal = false;
        this.$emit("refresh-catalogs");
        await this.load(this.pagination.current_page);
        await showCentroApuntesSuccess(this.form.id ? "Asignatura actualizada correctamente." : "Asignatura registrada correctamente.");
      } catch (error) {
        this.error = formatCentroApuntesError(error);
      } finally {
        this.saving = false;
      }
    },
    async destroy(item) {
      const confirmed = await confirmCentroApuntesAction({
        title: "Eliminar asignatura",
        text: `Se eliminará la asignatura ${item.name} si no tiene solicitudes asociadas.`,
        confirmButtonText: "Sí, eliminar",
        icon: "warning",
      });

      if (!confirmed.isConfirmed) return;

      try {
        await axios.delete(`/api/centro-apuntes/asignaturas/${item.id}`);
        await this.load(this.pagination.current_page);
        this.$emit("refresh-catalogs");
        await showCentroApuntesSuccess("Asignatura eliminada correctamente.");
      } catch (error) {
        this.error = formatCentroApuntesError(error);
      }
    },
    clearFilters() {
      this.filters = {
        search: "",
        area: null,
        education_level: null,
        status: null,
      };
      this.load();
    },
    async closeModal() {
      const confirmed = await confirmCentroApuntesCancel("la edición de la asignatura");
      if (confirmed.isConfirmed) {
        this.showModal = false;
      }
    },
  },
};
</script>

<template>
  <div class="centro-apuntes-tab d-flex flex-column gap-3">
    <CentroApuntesSectionToolbar title="Administración de asignaturas" description="Mantén ordenado el catálogo pedagógico usado en solicitudes y reportes." icon="bx-book-open">
      <div class="d-flex gap-2">
        <CentroApuntesHelpButton
          title="Ayuda: asignaturas"
          text="Aquí se crean y actualizan las asignaturas que usarán los funcionarios al ingresar solicitudes de impresión, permitiendo filtros por área y nivel."
        />
        <BButton v-if="canManage" variant="primary" @click="openCreate"><i class="bx bx-plus me-1"></i>Nueva asignatura</BButton>
      </div>
    </CentroApuntesSectionToolbar>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <BCard class="filter-card border-0 shadow-sm">
      <div class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Buscar</label>
          <BFormInput v-model="filters.search" placeholder="Nombre, código, área..." @keyup.enter="load" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Área</label>
          <BFormSelect v-model="filters.area" :options="[{ value: null, text: 'Todas' }].concat(areaOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Nivel educativo</label>
          <BFormSelect v-model="filters.education_level" :options="[{ value: null, text: 'Todos' }].concat(levelOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-2">
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
      <LoadingState v-if="loading" message="Cargando asignaturas..." compact />
      <BTable
        v-else
        responsive
        show-empty
        empty-text="No hay asignaturas que coincidan con los filtros."
        :items="items"
        :fields="[
          { key: 'name', label: 'Asignatura' },
          { key: 'code', label: 'Código' },
          { key: 'area', label: 'Área' },
          { key: 'education_level', label: 'Nivel' },
          { key: 'solicitudes_count', label: 'Solicitudes' },
          { key: 'status', label: 'Estado' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #cell(name)="{ item }">
          <div class="fw-semibold">{{ item.name }}</div>
          <div class="small text-muted">{{ item.observations || "Sin observaciones" }}</div>
        </template>
        <template #cell(status)="{ item }">
          <CentroApuntesStatusBadge :status="item.status" />
        </template>
        <template #cell(actions)="{ item }">
          <div class="d-flex gap-2">
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

    <BModal v-model="showModal" :title="form.id ? 'Editar asignatura' : 'Nueva asignatura'" hide-footer centered scrollable modal-class="centro-apuntes-modal">
      <CentroApuntesModalIntro title="Datos de la asignatura" text="Los campos marcados como opcionales pueden dejarse sin información." icon="bx-book-open">
        <CentroApuntesHelpButton
          title="Ayuda: formulario de asignatura"
          text="Use este formulario para crear o editar asignaturas, manteniendo su código, área, nivel educativo y estado operativo."
        />
      </CentroApuntesModalIntro>
      <div class="modal-form-grid row g-3">
        <div class="col-md-8">
          <label class="form-label">Nombre <span class="field-required">*</span></label>
          <BFormInput v-model="form.name" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Código <span class="field-required">*</span></label>
          <BFormInput v-model="form.code" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Área <span class="field-optional">Opcional</span></label>
          <BFormSelect v-model="form.area" :options="[{ value: null, text: 'Sin especificar' }].concat(areaOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Nivel educativo <span class="field-optional">Opcional</span></label>
          <BFormSelect v-model="form.education_level" :options="[{ value: null, text: 'Sin especificar' }].concat(levelOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Estado <span class="field-required">*</span></label>
          <BFormSelect v-model="form.status" :options="statusOptions.map((item) => ({ value: item.value, text: item.label }))" />
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
  </div>
</template>
