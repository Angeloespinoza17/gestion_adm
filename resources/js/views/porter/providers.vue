<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import PorterStatusBadge from "../../components/porter/status-badge.vue";

const emptyForm = () => ({
  service_type: "",
  company_name: "",
  contact_name: "",
  contact_rut: "",
  phone: "",
  vehicle_plate: "",
  responsible_staff_id: null,
  maintenance_dependency_id: null,
  observations: "",
});

export default {
  components: { Layout, LoadingState, PorterStatusBadge },
  data() {
    return {
      saving: false,
      loadingList: false,
      error: null,
      catalogs: { staff: [], dependencies: [], external_service_statuses: [] },
      form: emptyForm(),
      items: [],
      filters: { search: "", status: null },
      pagination: { current_page: 1, total: 0, per_page: 15 },
    };
  },
  computed: {
    statusOptions() {
      return [{ value: null, text: "Todos" }].concat((this.catalogs.external_service_statuses || []).map((item) => ({ value: item.value, text: item.label })));
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadItems();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/porter/catalogs");
      this.catalogs = response.data;
    },
    async submit() {
      this.saving = true;
      this.error = null;
      try {
        await axios.post("/api/porter/external-services", this.form);
        this.form = emptyForm();
        await this.loadItems(1);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async loadItems(page = 1) {
      this.loadingList = true;
      this.error = null;
      try {
        const response = await axios.get("/api/porter/external-services", {
          params: { page, search: this.filters.search || null, status: this.filters.status },
        });
        this.items = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page || 1,
          total: response.data.total || 0,
          per_page: response.data.per_page || 15,
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loadingList = false;
      }
    },
    async markExit(item) {
      const { value } = await Swal.fire({
        title: "Registrar salida de proveedor",
        html: `
          <select id="provider-status" class="swal2-select">
            <option value="finalizado">Finalizado</option>
            <option value="rechazado">Rechazado</option>
          </select>
          <textarea id="provider-observations" class="swal2-textarea" placeholder="Observaciones"></textarea>
        `,
        preConfirm: () => ({
          status: document.getElementById("provider-status").value,
          observations: document.getElementById("provider-observations").value,
        }),
      });
      if (!value) return;
      await axios.put(`/api/porter/external-services/${item.id}/exit`, value);
      await this.loadItems(this.pagination.current_page || 1);
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
        <h4 class="mb-0">Control de proveedores y servicios externos</h4>
        <div class="text-muted">Ingreso y salida de empresas, técnicos o servicios con responsable y dependencia.</div>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <BCard class="mb-3">
      <h5 class="mb-3">Nuevo ingreso</h5>
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Servicio externo</label><BFormInput v-model="form.service_type" placeholder="Gasfitería, internet, fumigación..." /></div>
        <div class="col-md-4"><label class="form-label">Empresa</label><BFormInput v-model="form.company_name" /></div>
        <div class="col-md-4"><label class="form-label">Responsable interno</label><BFormSelect v-model="form.responsible_staff_id" :options="[{ value: null, text: 'Seleccionar...' }].concat((catalogs.staff || []).map((item) => ({ value: item.id, text: item.full_name })))" /></div>
        <div class="col-md-4"><label class="form-label">Nombre contacto</label><BFormInput v-model="form.contact_name" /></div>
        <div class="col-md-4"><label class="form-label">RUT</label><BFormInput v-model="form.contact_rut" /></div>
        <div class="col-md-4"><label class="form-label">Teléfono</label><BFormInput v-model="form.phone" /></div>
        <div class="col-md-4"><label class="form-label">Patente</label><BFormInput v-model="form.vehicle_plate" /></div>
        <div class="col-md-8"><label class="form-label">Dependencia</label><BFormSelect v-model="form.maintenance_dependency_id" :options="[{ value: null, text: 'Seleccionar...' }].concat((catalogs.dependencies || []).map((item) => ({ value: item.id, text: `${item.code || '-'} · ${item.name}` })))" /></div>
        <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="form.observations" rows="2" /></div>
        <div class="col-12 d-flex justify-content-end"><BButton variant="primary" :disabled="saving" @click="submit">{{ saving ? "Guardando..." : "Registrar ingreso" }}</BButton></div>
      </div>
    </BCard>

    <BCard>
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="mb-0">Historial de proveedores</h5>
        <div class="d-flex gap-2 flex-wrap">
          <BFormInput v-model="filters.search" placeholder="Empresa, servicio, patente..." @keyup.enter="loadItems(1)" />
          <BFormSelect v-model="filters.status" :options="statusOptions" />
          <BButton variant="outline-primary" @click="loadItems(1)">Filtrar</BButton>
        </div>
      </div>

      <BTable
        :items="items"
        :busy="loadingList"
        responsive
        :fields="[
          { key: 'company_name', label: 'Proveedor' },
          { key: 'service_type', label: 'Servicio' },
          { key: 'status', label: 'Estado' },
          { key: 'entered_at', label: 'Ingreso' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #table-busy><LoadingState message="Cargando ingresos..." compact /></template>
        <template #cell(company_name)="{ item }">
          <div class="fw-semibold">{{ item.company_name || item.contact_name }}</div>
          <div class="small text-muted">{{ item.vehicle_plate || "-" }}</div>
        </template>
        <template #cell(service_type)="{ item }">
          <div>{{ item.service_type }}</div>
          <div class="small text-muted">{{ item.dependency?.name || item.responsible_staff?.full_name || "-" }}</div>
        </template>
        <template #cell(status)="{ item }"><PorterStatusBadge :value="item.status" :label="item.status" /></template>
        <template #cell(entered_at)="{ item }">{{ formatDateTime(item.entered_at) }}</template>
        <template #cell(actions)="{ item }">
          <BButton v-if="item.status === 'en_curso'" size="sm" variant="outline-primary" @click="markExit(item)">Registrar salida</BButton>
          <span v-else class="text-muted small">{{ formatDateTime(item.exited_at) }}</span>
        </template>
      </BTable>

      <div class="d-flex justify-content-end mt-3">
        <BPagination v-model="pagination.current_page" :total-rows="pagination.total" :per-page="pagination.per_page" @update:model-value="loadItems" />
      </div>
    </BCard>
  </Layout>
</template>
