<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import PorterStatusBadge from "../../components/porter/status-badge.vue";

const emptyForm = () => ({
  movement_type: "recepcion_mercaderia",
  department_id: null,
  responsible_staff_id: null,
  contact_name: "",
  contact_rut: "",
  company: "",
  phone: "",
  vehicle_plate: "",
  goods_detail: "",
  quantity: "",
  unit: "",
  document_type: "",
  document_number: "",
  status: "recibido_en_porteria",
  observations: "",
  attachment: null,
});

export default {
  components: { Layout, LoadingState, PorterStatusBadge },
  data() {
    return {
      saving: false,
      loading: false,
      error: null,
      catalogs: {
        goods_movement_types: [],
        goods_statuses: [],
        goods_document_types: [],
        departments: [],
        staff: [],
      },
      form: emptyForm(),
      movements: [],
      filters: {
        search: "",
        status: null,
        movement_type: null,
      },
      pagination: { current_page: 1, total: 0, per_page: 15 },
    };
  },
  computed: {
    movementTypeOptions() {
      return (this.catalogs.goods_movement_types || []).map((item) => ({ value: item.value, text: item.label }));
    },
    statusOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.goods_statuses || []).map((item) => ({ value: item.value, text: item.label }))
      );
    },
    documentTypeOptions() {
      return [{ value: "", text: "Seleccionar..." }].concat(
        (this.catalogs.goods_document_types || []).map((item) => ({ value: item.value, text: item.label }))
      );
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadMovements();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/porter/catalogs");
      this.catalogs = response.data;
    },
    onFileChange(event) {
      this.form.attachment = event?.target?.files?.[0] || null;
    },
    async submit() {
      this.saving = true;
      this.error = null;
      try {
        const formData = new FormData();
        Object.entries(this.form).forEach(([key, value]) => {
          if (value === null || value === undefined || value === "") return;
          if (key === "attachment") {
            if (value) formData.append("attachment", value);
            return;
          }
          formData.append(key, value);
        });

        await axios.post("/api/porter/goods-movements", formData, {
          headers: { "Content-Type": "multipart/form-data" },
        });

        this.form = emptyForm();
        await this.loadMovements(1);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async loadMovements(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/porter/goods-movements", {
          params: {
            page,
            search: this.filters.search || null,
            status: this.filters.status,
            movement_type: this.filters.movement_type,
          },
        });
        this.movements = response.data.data || [];
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
    async updateStatus(item) {
      const { value } = await Swal.fire({
        title: "Actualizar estado",
        html: `
          <select id="goods-status" class="swal2-select">
            ${(this.catalogs.goods_statuses || [])
              .map((status) => `<option value="${status.value}" ${status.value === item.status ? "selected" : ""}>${status.label}</option>`)
              .join("")}
          </select>
          <input id="received-by-name" class="swal2-input" placeholder="Responsable que recibe" />
          <input id="received-by-identifier" class="swal2-input" placeholder="RUT o identificación" />
          <textarea id="delivery-observations" class="swal2-textarea" placeholder="Observaciones"></textarea>
        `,
        preConfirm: () => ({
          status: document.getElementById("goods-status").value,
          received_by_name: document.getElementById("received-by-name").value,
          received_by_identifier: document.getElementById("received-by-identifier").value,
          delivery_observations: document.getElementById("delivery-observations").value,
        }),
      });

      if (!value) return;
      await axios.put(`/api/porter/goods-movements/${item.id}/status`, value);
      await this.loadMovements(this.pagination.current_page || 1);
    },
    formatDateTime(value) {
      if (!value) return "-";
      const normalized = String(value).replace("T", " ");
      const [datePart, timePart] = normalized.split(" ");
      const [year, month, day] = (datePart || "").split("-");
      return year && month && day ? `${day}/${month}/${year} ${String(timePart || "").slice(0, 5)}` : value;
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || {};
      const firstKey = Object.keys(errors)[0];
      return errors[firstKey]?.[0] || error?.response?.data?.message || error?.message || "Error desconocido";
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div>
        <h4 class="mb-0">Mercadería y proveedores</h4>
        <div class="text-muted">Recepción, entrega y retiro de mercadería institucional.</div>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <BCard class="mb-3">
      <h5 class="mb-3">Nuevo movimiento</h5>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Tipo de movimiento</label>
          <BFormSelect v-model="form.movement_type" :options="movementTypeOptions" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Departamento destinatario</label>
          <BFormSelect
            v-model="form.department_id"
            :options="[{ value: null, text: 'Seleccionar...' }].concat((catalogs.departments || []).map((item) => ({ value: item.id, text: item.name })))"
          />
        </div>
        <div class="col-md-4">
          <label class="form-label">Responsable interno</label>
          <BFormSelect
            v-model="form.responsible_staff_id"
            :options="[{ value: null, text: 'Seleccionar...' }].concat((catalogs.staff || []).map((item) => ({ value: item.id, text: item.full_name })))"
          />
        </div>
        <div class="col-md-4">
          <label class="form-label">Proveedor o persona</label>
          <BFormInput v-model="form.contact_name" />
        </div>
        <div class="col-md-4">
          <label class="form-label">RUT</label>
          <BFormInput v-model="form.contact_rut" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Empresa</label>
          <BFormInput v-model="form.company" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Teléfono</label>
          <BFormInput v-model="form.phone" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Patente</label>
          <BFormInput v-model="form.vehicle_plate" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Estado inicial</label>
          <BFormSelect v-model="form.status" :options="statusOptions.filter((item) => item.value)" />
        </div>
        <div class="col-md-8">
          <label class="form-label">Detalle de la mercadería</label>
          <BFormTextarea v-model="form.goods_detail" rows="2" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Cantidad</label>
          <BFormInput v-model="form.quantity" type="number" min="0" step="0.01" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Unidad</label>
          <BFormInput v-model="form.unit" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Documento</label>
          <BFormSelect v-model="form.document_type" :options="documentTypeOptions" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Número de documento</label>
          <BFormInput v-model="form.document_number" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Adjunto</label>
          <BFormInput type="file" @change="onFileChange" />
        </div>
        <div class="col-12">
          <label class="form-label">Observaciones</label>
          <BFormTextarea v-model="form.observations" rows="2" />
        </div>
        <div class="col-12 d-flex justify-content-end">
          <BButton variant="primary" :disabled="saving" @click="submit">{{ saving ? "Guardando..." : "Registrar movimiento" }}</BButton>
        </div>
      </div>
    </BCard>

    <BCard>
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="mb-0">Historial</h5>
        <div class="d-flex gap-2 flex-wrap">
          <BFormInput v-model="filters.search" placeholder="Buscar" @keyup.enter="loadMovements(1)" />
          <BFormSelect v-model="filters.status" :options="statusOptions" />
          <BFormSelect v-model="filters.movement_type" :options="[{ value: null, text: 'Todos los tipos' }].concat(movementTypeOptions)" />
          <BButton variant="outline-primary" @click="loadMovements(1)">Filtrar</BButton>
        </div>
      </div>

      <BTable
        :items="movements"
        :busy="loading"
        responsive
        :fields="[
          { key: 'goods_detail', label: 'Mercadería' },
          { key: 'contact_name', label: 'Proveedor' },
          { key: 'status', label: 'Estado' },
          { key: 'moved_at', label: 'Fecha' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #table-busy>
          <LoadingState message="Cargando movimientos..." compact />
        </template>
        <template #cell(goods_detail)="{ item }">
          <div class="fw-semibold">{{ item.goods_detail }}</div>
          <div class="small text-muted">{{ item.department?.name || "-" }}</div>
        </template>
        <template #cell(status)="{ item }">
          <PorterStatusBadge :value="item.status" :label="item.status" />
        </template>
        <template #cell(moved_at)="{ item }">
          {{ formatDateTime(item.moved_at) }}
        </template>
        <template #cell(actions)="{ item }">
          <BButton size="sm" variant="outline-primary" @click="updateStatus(item)">Actualizar estado</BButton>
        </template>
      </BTable>

      <div class="d-flex justify-content-end mt-3">
        <BPagination
          v-model="pagination.current_page"
          :total-rows="pagination.total"
          :per-page="pagination.per_page"
          @update:model-value="loadMovements"
        />
      </div>
    </BCard>
  </Layout>
</template>
