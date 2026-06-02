<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";

export default {
  components: { Layout },
  data() {
    return {
      loading: false,
      error: null,
      dashboard: null,
    };
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/inventory/reports/dashboard");
        this.dashboard = response.data;
      } catch (error) {
        this.error =
          error?.response?.data?.message || error?.message || "Error desconocido";
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0">Inventario · Reportes</h4>
      <BButton variant="secondary" @click="load" :disabled="loading">
        {{ loading ? "Cargando..." : "Actualizar" }}
      </BButton>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <div v-if="dashboard" class="row g-3">
      <div class="col-md-3" v-for="(value, key) in dashboard.totals" :key="key">
        <BCard>
          <div class="text-muted text-uppercase small">{{ key }}</div>
          <div class="fs-4 fw-semibold">{{ value }}</div>
        </BCard>
      </div>

      <div class="col-12">
        <BCard title="Bienes por categoría">
          <div class="table-responsive">
            <BTable
              :items="dashboard.by_category"
              :fields="[
                { key: 'category', label: 'Categoría' },
                { key: 'total', label: 'Total' },
              ]"
              small
            />
          </div>
          <div class="mt-2 text-muted">
            Insumos con stock bajo: <strong>{{ dashboard.low_stock }}</strong>
          </div>
        </BCard>
      </div>
    </div>
  </Layout>
</template>

