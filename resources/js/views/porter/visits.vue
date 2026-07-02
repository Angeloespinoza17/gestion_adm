<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import PorterStatusBadge from "../../components/porter/status-badge.vue";

const emptyForm = () => ({
  visitor_name: "",
  visitor_rut: "",
  purpose: "",
  visited_staff_id: null,
  visited_department_id: null,
  visited_person_label: "",
  contact_phone: "",
  observations: "",
});

export default {
  components: { Layout, LoadingState, PorterStatusBadge },
  data() {
    return {
      saving: false,
      loadingList: false,
      error: null,
      catalogs: { staff: [], departments: [], visit_statuses: [] },
      form: emptyForm(),
      items: [],
      filters: { search: "", status: null },
      pagination: { current_page: 1, total: 0, per_page: 15 },
    };
  },
  computed: {
    statusOptions() {
      return [{ value: null, text: "Todos" }].concat((this.catalogs.visit_statuses || []).map((item) => ({ value: item.value, text: item.label })));
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
        await axios.post("/api/porter/visits", this.form);
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
        const response = await axios.get("/api/porter/visits", {
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
        title: "Registrar salida",
        html: `
          <select id="visit-status" class="swal2-select">
            <option value="finalizada">Finalizada</option>
            <option value="rechazada">Rechazada</option>
          </select>
          <textarea id="visit-observations" class="swal2-textarea" placeholder="Observaciones de salida"></textarea>
        `,
        preConfirm: () => ({
          status: document.getElementById("visit-status").value,
          observations: document.getElementById("visit-observations").value,
        }),
      });
      if (!value) return;
      await axios.put(`/api/porter/visits/${item.id}/exit`, value);
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
        <h4 class="mb-0">Control de visitas</h4>
        <div class="text-muted">Ingreso y salida de visitas con trazabilidad básica de portería.</div>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <BCard class="mb-3">
      <h5 class="mb-3">Nueva visita</h5>
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Nombre</label><BFormInput v-model="form.visitor_name" /></div>
        <div class="col-md-4"><label class="form-label">RUT</label><BFormInput v-model="form.visitor_rut" /></div>
        <div class="col-md-4"><label class="form-label">Teléfono</label><BFormInput v-model="form.contact_phone" /></div>
        <div class="col-md-6"><label class="form-label">Motivo</label><BFormInput v-model="form.purpose" /></div>
        <div class="col-md-6"><label class="form-label">Persona visitada</label><BFormSelect v-model="form.visited_staff_id" :options="[{ value: null, text: 'Seleccionar...' }].concat((catalogs.staff || []).map((item) => ({ value: item.id, text: item.full_name })))" /></div>
        <div class="col-md-6"><label class="form-label">Dependencia / departamento</label><BFormSelect v-model="form.visited_department_id" :options="[{ value: null, text: 'Seleccionar...' }].concat((catalogs.departments || []).map((item) => ({ value: item.id, text: item.name })))" /></div>
        <div class="col-md-6"><label class="form-label">Referencia adicional</label><BFormInput v-model="form.visited_person_label" placeholder="Ej. Reunión con apoderados / sala de espera" /></div>
        <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="form.observations" rows="2" /></div>
        <div class="col-12 d-flex justify-content-end"><BButton variant="primary" :disabled="saving" @click="submit">{{ saving ? "Guardando..." : "Registrar ingreso" }}</BButton></div>
      </div>
    </BCard>

    <BCard>
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="mb-0">Historial de visitas</h5>
        <div class="d-flex gap-2 flex-wrap">
          <BFormInput v-model="filters.search" placeholder="Nombre, RUT, motivo..." @keyup.enter="loadItems(1)" />
          <BFormSelect v-model="filters.status" :options="statusOptions" />
          <BButton variant="outline-primary" @click="loadItems(1)">Filtrar</BButton>
        </div>
      </div>

      <BTable
        :items="items"
        :busy="loadingList"
        responsive
        :fields="[
          { key: 'visitor_name', label: 'Visita' },
          { key: 'purpose', label: 'Motivo' },
          { key: 'status', label: 'Estado' },
          { key: 'entered_at', label: 'Ingreso' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #table-busy><LoadingState message="Cargando visitas..." compact /></template>
        <template #cell(visitor_name)="{ item }">
          <div class="fw-semibold">{{ item.visitor_name }}</div>
          <div class="small text-muted">{{ item.visitor_rut || "-" }}</div>
        </template>
        <template #cell(purpose)="{ item }">
          <div>{{ item.purpose }}</div>
          <div class="small text-muted">{{ item.visited_staff?.full_name || item.visited_department?.name || item.visited_person_label || "-" }}</div>
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
