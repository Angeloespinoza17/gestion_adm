<script>
import axios from "axios";
import { formatCentroApuntesError } from "./module-utils";

export default {
  data() {
    return {
      query: "",
      loading: false,
      error: null,
      results: [],
      showDropdown: false,
      debounceTimer: null,
    };
  },
  methods: {
    scheduleSearch() {
      window.clearTimeout(this.debounceTimer);
      this.debounceTimer = window.setTimeout(() => this.search(), 250);
    },
    async search() {
      this.error = null;
      if (!this.query || String(this.query).trim().length < 2) {
        this.results = [];
        this.showDropdown = false;
        return;
      }

      this.loading = true;
      try {
        const response = await axios.get("/api/centro-apuntes/search", {
          params: { q: this.query },
        });
        this.results = response.data.data || [];
        this.showDropdown = true;
      } catch (error) {
        this.error = formatCentroApuntesError(error, "No se pudo ejecutar el buscador global.");
      } finally {
        this.loading = false;
      }
    },
    openResult(item) {
      this.showDropdown = false;
      this.query = "";
      this.results = [];
      this.$router.push({ path: item.route, query: item.query || {} });
    },
    onBlur() {
      window.setTimeout(() => {
        this.showDropdown = false;
      }, 150);
    },
  },
};
</script>

<template>
  <div class="position-relative">
    <div class="input-group">
      <span class="input-group-text bg-white border-end-0">
        <i class="bx bx-search"></i>
      </span>
      <BFormInput
        v-model="query"
        class="border-start-0"
        placeholder="Buscar solicitante, asignatura, tipo de tarea, máquina, estado, insumo o área"
        @input="scheduleSearch"
        @focus="showDropdown = results.length > 0"
        @blur="onBlur"
      />
    </div>

    <div v-if="showDropdown" class="card border shadow-sm position-absolute w-100 mt-1 search-dropdown">
      <div v-if="loading" class="p-3 text-muted small">Buscando...</div>
      <div v-else-if="error" class="p-3 text-danger small">{{ error }}</div>
      <div v-else-if="!results.length" class="p-3 text-muted small">Sin resultados.</div>
      <button
        v-for="item in results"
        :key="`${item.type}-${item.id}`"
        type="button"
        class="btn btn-link text-start text-decoration-none p-3 border-bottom"
        @click="openResult(item)"
      >
        <div class="fw-semibold text-body">{{ item.label }}</div>
        <div class="text-muted small">{{ item.subtitle }}</div>
      </button>
    </div>
  </div>
</template>

<style scoped>
.search-dropdown {
  z-index: 25;
  max-height: 360px;
  overflow-y: auto;
}
</style>
