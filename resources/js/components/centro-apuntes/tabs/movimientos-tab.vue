<script>
import axios from "axios";
import CentroApuntesHelpButton from "../help-button.vue";
import CentroApuntesStatusBadge from "../status-badge.vue";
import LoadingState from "../../ui/loading-state.vue";
import {
  confirmCentroApuntesAction,
  confirmCentroApuntesCancel,
  formatCentroApuntesDateTime,
  formatCentroApuntesError,
  normalizeOptions,
  showCentroApuntesSuccess,
} from "../module-utils";

const emptyForm = () => ({
  insumo_id: null,
  movement_type: "ingreso",
  quantity: 1,
  moved_at: new Date().toISOString().slice(0, 16),
  responsible_user_id: null,
  requested_by_user_id: null,
  department_id: null,
  reason: "",
  document_reference: "",
  observations: "",
  adjustment_mode: "sumar",
});

export default {
  components: {
    CentroApuntesHelpButton,
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
      pagination: { current_page: 1, total: 0, per_page: 20 },
      filters: {
        search: "",
        insumo_id: null,
        movement_type: null,
        department_id: null,
      },
      showModal: false,
      form: emptyForm(),
    };
  },
  computed: {
    canCreate() {
      return Boolean(this.catalogs.capabilities?.can_register_stock_movements);
    },
    supplyOptions() {
      return normalizeOptions((this.catalogs.supplies || []).map((item) => ({
        value: item.id,
        label: `${item.name} · ${item.current_stock} ${item.unit_of_measure}`,
      })));
    },
    movementTypeOptions() {
      return normalizeOptions(this.catalogs.movement_types || []);
    },
    departmentOptions() {
      return normalizeOptions(this.catalogs.departments || []);
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
        const response = await axios.get("/api/centro-apuntes/movimientos", {
          params: { page, ...this.filters },
        });
        this.items = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page,
          total: response.data.total,
          per_page: response.data.per_page,
        };
      } catch (error) {
        this.error = formatCentroApuntesError(error, "No se pudieron cargar los movimientos.");
      } finally {
        this.loading = false;
      }
    },
    openCreate() {
      this.form = emptyForm();
      this.showModal = true;
    },
    async save() {
      const confirmed = await confirmCentroApuntesAction({
        title: "Registrar movimiento",
        text: "Se actualizará automáticamente el stock del insumo seleccionado.",
        confirmButtonText: "Registrar",
      });

      if (!confirmed.isConfirmed) return;

      this.saving = true;
      try {
        await axios.post("/api/centro-apuntes/movimientos", this.form);
        this.showModal = false;
        this.$emit("refresh-catalogs");
        await this.load(this.pagination.current_page);
        await showCentroApuntesSuccess("Movimiento registrado correctamente.");
      } catch (error) {
        this.error = formatCentroApuntesError(error);
      } finally {
        this.saving = false;
      }
    },
    clearFilters() {
      this.filters = {
        search: "",
        insumo_id: null,
        movement_type: null,
        department_id: null,
      };
      this.load();
    },
    async closeModal() {
      const confirmed = await confirmCentroApuntesCancel("el registro del movimiento");
      if (confirmed.isConfirmed) {
        this.showModal = false;
      }
    },
  },
};
</script>

<template>
  <div class="d-flex flex-column gap-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="fw-semibold">Movimientos de stock del pañol</div>
      <div class="d-flex gap-2">
        <CentroApuntesHelpButton
          title="Ayuda: movimientos de stock"
          text="Aquí se registran ingresos, salidas, ajustes, pérdidas, devoluciones, vencimientos y bajas del pañol, con impacto inmediato en stock."
        />
        <BButton v-if="canCreate" variant="primary" @click="openCreate"><i class="bx bx-plus me-1"></i>Nuevo movimiento</BButton>
      </div>
    </div>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <BCard class="border-0 shadow-sm">
      <div class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Buscar insumo</label>
          <BFormInput v-model="filters.search" placeholder="Nombre del insumo..." @keyup.enter="load" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Insumo</label>
          <BFormSelect v-model="filters.insumo_id" :options="[{ value: null, text: 'Todos' }].concat(supplyOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Tipo</label>
          <BFormSelect v-model="filters.movement_type" :options="[{ value: null, text: 'Todos' }].concat(movementTypeOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Área</label>
          <BFormSelect v-model="filters.department_id" :options="[{ value: null, text: 'Todas' }].concat(departmentOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-3">
          <BButton variant="secondary" class="me-2" @click="load">Filtrar</BButton>
          <BButton variant="light" @click="clearFilters">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BCard class="border-0 shadow-sm">
      <LoadingState v-if="loading" message="Cargando movimientos..." compact />
      <BTable
        v-else
        responsive
        show-empty
        empty-text="No hay movimientos que coincidan con los filtros."
        :items="items"
        :fields="[
          { key: 'moved_at', label: 'Fecha' },
          { key: 'insumo', label: 'Insumo' },
          { key: 'movement_type', label: 'Tipo' },
          { key: 'quantity', label: 'Cantidad' },
          { key: 'stock_after', label: 'Stock final' },
          { key: 'department', label: 'Área' },
          { key: 'reason', label: 'Motivo' },
        ]"
      >
        <template #cell(moved_at)="{ item }">
          {{ formatCentroApuntesDateTime(item.moved_at) }}
        </template>
        <template #cell(insumo)="{ item }">
          <div class="fw-semibold">{{ item.insumo?.name }}</div>
          <div class="small text-muted">{{ item.insumo?.category }}</div>
        </template>
        <template #cell(movement_type)="{ item }">
          <CentroApuntesStatusBadge :status="item.movement_type" />
        </template>
        <template #cell(quantity)="{ item }">
          {{ item.quantity }}
        </template>
        <template #cell(stock_after)="{ item }">
          {{ item.stock_after }}
        </template>
        <template #cell(department)="{ item }">
          {{ item.department?.name || "Sin área" }}
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

    <BModal v-model="showModal" size="lg" title="Registrar movimiento" hide-footer>
      <div class="d-flex justify-content-end mb-3">
        <CentroApuntesHelpButton
          title="Ayuda: formulario de movimiento"
          text="Use este formulario para registrar cada ingreso, salida o ajuste del pañol. El sistema validará el stock disponible y actualizará el inventario automáticamente."
        />
      </div>
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Insumo</label>
          <BFormSelect v-model="form.insumo_id" :options="supplyOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Tipo</label>
          <BFormSelect v-model="form.movement_type" :options="movementTypeOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Cantidad</label>
          <BFormInput v-model="form.quantity" type="number" min="0" step="0.01" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Fecha</label>
          <BFormInput v-model="form.moved_at" type="datetime-local" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Modo ajuste</label>
          <BFormSelect v-model="form.adjustment_mode" :disabled="form.movement_type !== 'ajuste'" :options="[{ value: 'sumar', text: 'Sumar' }, { value: 'restar', text: 'Restar' }]" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Responsable</label>
          <BFormSelect v-model="form.responsible_user_id" :options="[{ value: null, text: 'Usuario actual' }].concat(userOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Solicitante</label>
          <BFormSelect v-model="form.requested_by_user_id" :options="[{ value: null, text: 'Sin solicitante' }].concat(userOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Área</label>
          <BFormSelect v-model="form.department_id" :options="[{ value: null, text: 'Sin área' }].concat(departmentOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Documento asociado</label>
          <BFormInput v-model="form.document_reference" />
        </div>
        <div class="col-md-12">
          <label class="form-label">Motivo</label>
          <BFormInput v-model="form.reason" />
        </div>
        <div class="col-md-12">
          <label class="form-label">Observaciones</label>
          <BFormTextarea v-model="form.observations" rows="3" />
        </div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-4">
        <BButton variant="light" @click="closeModal">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">{{ saving ? "Registrando..." : "Registrar" }}</BButton>
      </div>
    </BModal>
  </div>
</template>
