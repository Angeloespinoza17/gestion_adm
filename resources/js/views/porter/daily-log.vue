<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import PorterStatusBadge from "../../components/porter/status-badge.vue";

const emptyForm = () => ({
  shift_label: "",
  category: "novedad",
  priority: "media",
  status: "registrado",
  title: "",
  detail: "",
});

export default {
  components: { Layout, LoadingState, PorterStatusBadge },
  data() {
    return {
      saving: false,
      loadingList: false,
      error: null,
      catalogs: { daily_log_categories: [], daily_log_priorities: [], daily_log_statuses: [] },
      form: emptyForm(),
      items: [],
      filters: { search: "", category: null, priority: null },
      pagination: { current_page: 1, total: 0, per_page: 15 },
    };
  },
  computed: {
    categoryOptions() {
      return [{ value: null, text: "Todas" }].concat((this.catalogs.daily_log_categories || []).map((item) => ({ value: item.value, text: item.label })));
    },
    priorityOptions() {
      return [{ value: null, text: "Todas" }].concat((this.catalogs.daily_log_priorities || []).map((item) => ({ value: item.value, text: item.label })));
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
        await axios.post("/api/porter/daily-log", this.form);
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
        const response = await axios.get("/api/porter/daily-log", {
          params: {
            page,
            search: this.filters.search || null,
            category: this.filters.category,
            priority: this.filters.priority,
          },
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
        <h4 class="mb-0">Bitácora diaria de portería</h4>
        <div class="text-muted">Registro continuo de novedades, incidencias y observaciones del turno.</div>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <BCard class="mb-3">
      <h5 class="mb-3">Nueva entrada</h5>
      <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Turno</label><BFormInput v-model="form.shift_label" placeholder="Mañana / Tarde" /></div>
        <div class="col-md-3"><label class="form-label">Categoría</label><BFormSelect v-model="form.category" :options="(catalogs.daily_log_categories || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-3"><label class="form-label">Prioridad</label><BFormSelect v-model="form.priority" :options="(catalogs.daily_log_priorities || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-3"><label class="form-label">Estado</label><BFormSelect v-model="form.status" :options="(catalogs.daily_log_statuses || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-12"><label class="form-label">Título</label><BFormInput v-model="form.title" /></div>
        <div class="col-12"><label class="form-label">Detalle</label><BFormTextarea v-model="form.detail" rows="3" /></div>
        <div class="col-12 d-flex justify-content-end"><BButton variant="primary" :disabled="saving" @click="submit">{{ saving ? "Guardando..." : "Registrar entrada" }}</BButton></div>
      </div>
    </BCard>

    <BCard>
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="mb-0">Bitácora registrada</h5>
        <div class="d-flex gap-2 flex-wrap">
          <BFormInput v-model="filters.search" placeholder="Buscar por título o detalle" @keyup.enter="loadItems(1)" />
          <BFormSelect v-model="filters.category" :options="categoryOptions" />
          <BFormSelect v-model="filters.priority" :options="priorityOptions" />
          <BButton variant="outline-primary" @click="loadItems(1)">Filtrar</BButton>
        </div>
      </div>

      <BTable
        :items="items"
        :busy="loadingList"
        responsive
        :fields="[
          { key: 'title', label: 'Registro' },
          { key: 'category', label: 'Categoría' },
          { key: 'priority', label: 'Prioridad' },
          { key: 'logged_at', label: 'Fecha' },
        ]"
      >
        <template #table-busy><LoadingState message="Cargando bitácora..." compact /></template>
        <template #cell(title)="{ item }">
          <div class="fw-semibold">{{ item.title }}</div>
          <div class="small text-muted">{{ item.detail }}</div>
        </template>
        <template #cell(category)="{ item }"><PorterStatusBadge :value="item.category" :label="item.category" /></template>
        <template #cell(priority)="{ item }"><PorterStatusBadge :value="item.priority" :label="item.priority" /></template>
        <template #cell(logged_at)="{ item }">{{ formatDateTime(item.logged_at) }}</template>
      </BTable>

      <div class="d-flex justify-content-end mt-3">
        <BPagination v-model="pagination.current_page" :total-rows="pagination.total" :per-page="pagination.per_page" @update:model-value="loadItems" />
      </div>
    </BCard>
  </Layout>
</template>
