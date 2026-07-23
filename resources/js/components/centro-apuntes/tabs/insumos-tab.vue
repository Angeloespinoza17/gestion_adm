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
  formatCentroApuntesDate,
  formatCentroApuntesDateTime,
  formatCentroApuntesError,
  normalizeCentroApuntesNullableFields,
  normalizeOptions,
  showCentroApuntesSuccess,
} from "../module-utils";

const emptyForm = () => ({
  id: null,
  name: "",
  category: "papel",
  unit_of_measure: "unidad",
  current_stock: 0,
  minimum_stock: 0,
  maximum_stock: null,
  location: null,
  supplier_id: null,
  last_purchase_at: null,
  expires_at: null,
  status: "disponible",
  observations: null,
  active: true,
  photo: null,
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
        category: null,
        status: null,
        critical_only: false,
      },
      showModal: false,
      showDetailModal: false,
      form: emptyForm(),
      selectedSupply: null,
    };
  },
  computed: {
    canManage() {
      return Boolean(this.catalogs.capabilities?.can_manage_inventory);
    },
    categoryOptions() {
      return normalizeOptions(this.catalogs.supply_categories || []);
    },
    unitOptions() {
      return normalizeOptions(this.catalogs.supply_units || []);
    },
    statusOptions() {
      return normalizeOptions(this.catalogs.supply_statuses || []);
    },
    supplierOptions() {
      return normalizeOptions(this.catalogs.suppliers || []);
    },
  },
  mounted() {
    this.load();
    this.consumeRouteFocus();
  },
  methods: {
    formatCentroApuntesDate,
    formatCentroApuntesDateTime,
    async load(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/centro-apuntes/insumos", {
          params: {
            page,
            ...this.filters,
            critical_only: this.filters.critical_only ? 1 : "",
          },
        });
        this.items = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page,
          total: response.data.total,
          per_page: response.data.per_page,
        };
      } catch (error) {
        this.error = formatCentroApuntesError(error, "No se pudieron cargar los insumos.");
      } finally {
        this.loading = false;
      }
    },
    async consumeRouteFocus() {
      if (!this.$route.query.supply) return;
      await this.openDetail(this.$route.query.supply);
    },
    resetForm() {
      this.form = emptyForm();
      this.selectedSupply = null;
    },
    openCreate() {
      this.resetForm();
      this.showModal = true;
    },
    async openEdit(item) {
      await this.openDetail(item.id, true);
    },
    async openDetail(itemOrId, alsoEdit = false) {
      const id = typeof itemOrId === "object" ? itemOrId.id : itemOrId;
      this.detailLoading = true;
      try {
        const response = await axios.get(`/api/centro-apuntes/insumos/${id}`);
        this.selectedSupply = response.data.data;
        if (alsoEdit) {
          this.form = {
            id: this.selectedSupply.id,
            name: this.selectedSupply.name,
            category: this.selectedSupply.category,
            unit_of_measure: this.selectedSupply.unit_of_measure,
            current_stock: this.selectedSupply.current_stock,
            minimum_stock: this.selectedSupply.minimum_stock,
            maximum_stock: this.selectedSupply.maximum_stock,
            location: this.selectedSupply.location ?? null,
            supplier_id: this.selectedSupply.supplier_id || null,
            last_purchase_at: this.selectedSupply.last_purchase_at ? String(this.selectedSupply.last_purchase_at).slice(0, 10) : null,
            expires_at: this.selectedSupply.expires_at ? String(this.selectedSupply.expires_at).slice(0, 10) : null,
            status: this.selectedSupply.status,
            observations: this.selectedSupply.observations ?? null,
            active: this.selectedSupply.active,
            photo: null,
          };
          this.showModal = true;
        } else {
          this.showDetailModal = true;
        }
      } catch (error) {
        this.error = formatCentroApuntesError(error, "No se pudo cargar el detalle del insumo.");
      } finally {
        this.detailLoading = false;
      }
    },
    buildFormData() {
      const formData = new FormData();
      const photo = Array.isArray(this.form.photo) ? this.form.photo[0] : this.form.photo;
      const normalized = normalizeCentroApuntesNullableFields(this.form, [
        "maximum_stock",
        "location",
        "supplier_id",
        "last_purchase_at",
        "expires_at",
        "observations",
      ]);
      [
        "name",
        "category",
        "unit_of_measure",
        "current_stock",
        "minimum_stock",
        "maximum_stock",
        "location",
        "supplier_id",
        "last_purchase_at",
        "expires_at",
        "status",
        "observations",
      ].forEach((field) => formData.append(field, normalized[field] ?? ""));
      formData.append("active", this.form.active ? 1 : 0);

      if (photo) {
        formData.append("photo", photo);
      }

      return formData;
    },
    async save() {
      const confirmed = await confirmCentroApuntesAction({
        title: this.form.id ? "Guardar cambios" : "Crear insumo",
        text: this.form.id
          ? "Se actualizará el insumo seleccionado."
          : "Se registrará un nuevo insumo para el pañol.",
        confirmButtonText: "Guardar",
      });

      if (!confirmed.isConfirmed) return;

      this.saving = true;
      try {
        const formData = this.buildFormData();
        if (this.form.id) {
          formData.append("_method", "PUT");
          await axios.post(`/api/centro-apuntes/insumos/${this.form.id}`, formData, {
            headers: { "Content-Type": "multipart/form-data" },
          });
        } else {
          await axios.post("/api/centro-apuntes/insumos", formData, {
            headers: { "Content-Type": "multipart/form-data" },
          });
        }
        this.showModal = false;
        this.$emit("refresh-catalogs");
        await this.load(this.pagination.current_page);
        await showCentroApuntesSuccess(this.form.id ? "Insumo actualizado correctamente." : "Insumo registrado correctamente.");
      } catch (error) {
        this.error = formatCentroApuntesError(error);
      } finally {
        this.saving = false;
      }
    },
    async destroy(item) {
      const confirmed = await confirmCentroApuntesAction({
        title: "Eliminar insumo",
        text: `Se eliminará el insumo ${item.name} si no tiene movimientos asociados.`,
        confirmButtonText: "Sí, eliminar",
        icon: "warning",
      });

      if (!confirmed.isConfirmed) return;

      try {
        await axios.delete(`/api/centro-apuntes/insumos/${item.id}`);
        await this.load(this.pagination.current_page);
        this.$emit("refresh-catalogs");
        await showCentroApuntesSuccess("Insumo eliminado correctamente.");
      } catch (error) {
        this.error = formatCentroApuntesError(error);
      }
    },
    clearFilters() {
      this.filters = {
        search: "",
        category: null,
        status: null,
        critical_only: false,
      };
      this.load();
    },
    async closeModal() {
      const confirmed = await confirmCentroApuntesCancel("la edición del insumo");
      if (confirmed.isConfirmed) {
        this.showModal = false;
      }
    },
  },
};
</script>

<template>
  <div class="centro-apuntes-tab d-flex flex-column gap-3">
    <CentroApuntesSectionToolbar title="Inventario de insumos" description="Consulta existencias, mínimos, vencimientos y ubicación de cada material." icon="bx-box">
      <div class="d-flex gap-2">
        <CentroApuntesHelpButton
          title="Ayuda: inventario de insumos"
          text="Aquí se registran y actualizan los insumos del pañol de librería, controlando stock, vencimientos, proveedor, ubicación y estado de disponibilidad."
        />
        <BButton v-if="canManage" variant="primary" @click="openCreate"><i class="bx bx-plus me-1"></i>Nuevo insumo</BButton>
      </div>
    </CentroApuntesSectionToolbar>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <BCard class="filter-card border-0 shadow-sm">
      <div class="row g-3 align-items-end">
        <div class="col-md-5">
          <label class="form-label">Buscar</label>
          <BFormInput v-model="filters.search" placeholder="Nombre, categoría, ubicación..." @keyup.enter="load" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Categoría</label>
          <BFormSelect v-model="filters.category" :options="[{ value: null, text: 'Todas' }].concat(categoryOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="filters.status" :options="[{ value: null, text: 'Todos' }].concat(statusOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-2">
          <BFormCheckbox v-model="filters.critical_only">Solo críticos</BFormCheckbox>
        </div>
        <div class="col-md-3">
          <BButton variant="secondary" class="me-2" @click="load">Filtrar</BButton>
          <BButton variant="light" @click="clearFilters">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BCard class="data-card border-0 shadow-sm">
      <LoadingState v-if="loading || detailLoading" message="Cargando insumos..." compact />
      <BTable
        v-else
        responsive
        show-empty
        empty-text="No hay insumos que coincidan con los filtros."
        :items="items"
        :fields="[
          { key: 'name', label: 'Insumo' },
          { key: 'category', label: 'Categoría' },
          { key: 'current_stock', label: 'Stock' },
          { key: 'expires_at', label: 'Vencimiento' },
          { key: 'status', label: 'Estado' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #cell(name)="{ item }">
          <div class="fw-semibold">{{ item.name }}</div>
          <div class="small text-muted">{{ item.location || "Sin ubicación" }}</div>
        </template>
        <template #cell(current_stock)="{ item }">
          {{ item.current_stock }} {{ item.unit_of_measure }}
          <div class="small text-muted">Mín. {{ item.minimum_stock }}</div>
        </template>
        <template #cell(expires_at)="{ item }">
          {{ formatCentroApuntesDate(item.expires_at) }}
        </template>
        <template #cell(status)="{ item }">
          <CentroApuntesStatusBadge :status="item.status" />
        </template>
        <template #cell(actions)="{ item }">
          <div class="d-flex flex-wrap gap-2">
            <BButton size="sm" variant="outline-info" @click="openDetail(item)">Ver</BButton>
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

    <BModal v-model="showModal" size="lg" :title="form.id ? 'Editar insumo' : 'Nuevo insumo'" hide-footer centered scrollable modal-class="centro-apuntes-modal">
      <CentroApuntesModalIntro title="Ficha de inventario" text="Proveedor, ubicación, stock máximo, fechas, foto y observaciones pueden quedar sin información." icon="bx-box">
        <CentroApuntesHelpButton
          title="Ayuda: formulario de insumo"
          text="Use este formulario para registrar insumos, su stock, proveedor, ubicación, vencimiento, foto y estado operativo."
        />
      </CentroApuntesModalIntro>
      <div class="modal-form-grid row g-3">
        <div class="col-md-8">
          <label class="form-label">Nombre <span class="field-required">*</span></label>
          <BFormInput v-model="form.name" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="form.status" :options="statusOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Categoría <span class="field-required">*</span></label>
          <BFormSelect v-model="form.category" :options="categoryOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Unidad de medida <span class="field-required">*</span></label>
          <BFormSelect v-model="form.unit_of_measure" :options="unitOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Stock actual <span class="field-required">*</span></label>
          <BFormInput v-model="form.current_stock" type="number" min="0" step="0.01" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Stock mínimo <span class="field-required">*</span></label>
          <BFormInput v-model="form.minimum_stock" type="number" min="0" step="0.01" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Stock máximo <span class="field-optional">Opcional</span></label>
          <BFormInput v-model="form.maximum_stock" type="number" min="0" step="0.01" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Ubicación <span class="field-optional">Opcional</span></label>
          <BFormInput v-model="form.location" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Proveedor <span class="field-optional">Opcional</span></label>
          <BFormSelect v-model="form.supplier_id" :options="[{ value: null, text: 'Sin proveedor' }].concat(supplierOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Última compra <span class="field-optional">Opcional</span></label>
          <BFormInput v-model="form.last_purchase_at" type="date" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Fecha de vencimiento <span class="field-optional">Opcional</span></label>
          <BFormInput v-model="form.expires_at" type="date" />
        </div>
        <div class="col-md-12">
          <label class="form-label">Foto <span class="field-optional">Opcional</span></label>
          <BFormFile v-model="form.photo" browse-text="Seleccionar" />
        </div>
        <div class="col-md-12">
          <label class="form-label">Observaciones <span class="field-optional">Opcional</span></label>
          <BFormTextarea v-model="form.observations" rows="3" />
        </div>
        <div class="col-md-12">
          <BFormCheckbox v-model="form.active">Insumo activo</BFormCheckbox>
        </div>
      </div>

      <div v-if="selectedSupply?.photo_url" class="mt-4">
        <div class="fw-semibold mb-2">Foto actual</div>
        <img :src="selectedSupply.photo_url" alt="Foto insumo" class="img-thumbnail" style="max-width: 220px" />
      </div>

      <div class="modal-actions">
        <BButton variant="light" @click="closeModal">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">{{ saving ? "Guardando..." : "Guardar" }}</BButton>
      </div>
    </BModal>

    <BModal v-model="showDetailModal" size="xl" title="Detalle de insumo" hide-footer centered scrollable modal-class="centro-apuntes-modal">
      <template v-if="selectedSupply">
        <div class="detail-grid row g-3">
          <div class="col-md-4">
            <div class="text-muted small">Insumo</div>
            <div class="fw-semibold">{{ selectedSupply.name }}</div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Estado</div>
            <CentroApuntesStatusBadge :status="selectedSupply.status" />
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Stock actual</div>
            <div>{{ selectedSupply.current_stock }} {{ selectedSupply.unit_of_measure }}</div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Proveedor</div>
            <div>{{ selectedSupply.supplier?.name || "Sin proveedor" }}</div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Ubicación</div>
            <div>{{ selectedSupply.location || "-" }}</div>
          </div>
        </div>

        <div class="mt-4">
          <div class="modal-section-title">Movimientos recientes</div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Tipo</th>
                  <th>Cantidad</th>
                  <th>Responsable</th>
                  <th>Motivo</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="movement in selectedSupply.movements || []" :key="movement.id">
                  <td>{{ formatCentroApuntesDateTime(movement.moved_at) }}</td>
                  <td><CentroApuntesStatusBadge :status="movement.movement_type" /></td>
                  <td>{{ movement.quantity }}</td>
                  <td>{{ movement.responsible_user?.name || movement.responsibleUser?.name || "-" }}</td>
                  <td>{{ movement.reason || "-" }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </BModal>
  </div>
</template>
