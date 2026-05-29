<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";

export default {
  components: { Layout },
  data() {
    return {
      loading: false,
      status: null,
      result: null,
      error: null,
    };
  },
  mounted() {
    this.loadStatus();
  },
  methods: {
    async loadStatus() {
      try {
        const response = await axios.get("/api/deploy/status");
        this.status = response.data;
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    async runDeploy() {
      if (!confirm("¿Ejecutar deploy ahora?")) return;

      this.loading = true;
      this.result = null;
      this.error = null;

      try {
        const response = await axios.post("/api/deploy");
        this.result = response.data;
      } catch (error) {
        this.error = this.formatError(error);
        this.result = error.response?.data || null;
      } finally {
        this.loading = false;
      }
    },
    formatError(error) {
      return error.response?.data?.message || error.message || "Error desconocido";
    },
  },
};
</script>

<template>
  <Layout>
    <BRow>
      <BCol cols="12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
          <h4 class="mb-0 font-size-18">Deploy</h4>
          <div class="page-title-right">
            <ol class="breadcrumb mb-0">
              <li class="breadcrumb-item active">Deploy directo al VPS</li>
            </ol>
          </div>
        </div>
      </BCol>
    </BRow>

    <BRow>
      <BCol lg="8">
        <BCard no-body>
          <BCardBody>
            <h5 class="card-title mb-3">Publicar cambios</h5>

            <p class="text-muted mb-4">
              Este botón ejecuta el build local, sincroniza el proyecto con el VPS y optimiza Laravel en producción.
            </p>

            <div v-if="status" class="mb-3">
              <div><strong>Estado:</strong> {{ status.enabled ? "habilitado" : "deshabilitado" }}</div>
              <div><strong>Servidor:</strong> {{ status.target || "sin configurar" }}</div>
              <div><strong>Ruta:</strong> {{ status.path || "sin configurar" }}</div>
            </div>

            <BAlert v-if="error" show variant="danger">
              {{ error }}
            </BAlert>

            <BButton variant="primary" :disabled="loading || !status?.enabled" @click="runDeploy">
              <span v-if="loading">Ejecutando deploy...</span>
              <span v-else>Deploy ahora</span>
            </BButton>

            <BAlert v-if="result?.message" class="mt-4" show :variant="result.success ? 'success' : 'danger'">
              {{ result.message }}
            </BAlert>

            <pre v-if="result?.output" class="bg-dark text-light p-3 mt-3 rounded deploy-output">{{ result.output }}</pre>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>

<style scoped>
.deploy-output {
  max-height: 520px;
  overflow: auto;
  white-space: pre-wrap;
}
</style>
