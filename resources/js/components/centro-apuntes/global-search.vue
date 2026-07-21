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
      this.showDropdown = String(this.query || "").trim().length >= 2;
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
        this.showDropdown = true;
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
    clearSearch() {
      window.clearTimeout(this.debounceTimer);
      this.query = "";
      this.results = [];
      this.error = null;
      this.showDropdown = false;
    },
  },
};
</script>

<template>
  <div class="global-search position-relative">
    <div class="input-group search-control">
      <span class="input-group-text border-end-0">
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
      <button v-if="query" class="btn clear-button" type="button" aria-label="Limpiar búsqueda" @mousedown.prevent @click="clearSearch">
        <i class="bx bx-x"></i>
      </button>
      <span v-if="loading" class="input-group-text loading-indicator"><i class="bx bx-loader-alt bx-spin"></i></span>
    </div>

    <div v-if="showDropdown" class="card border shadow-sm position-absolute w-100 mt-1 search-dropdown">
      <div v-if="loading" class="search-message"><i class="bx bx-loader-alt bx-spin"></i> Buscando coincidencias...</div>
      <div v-else-if="error" class="p-3 text-danger small">{{ error }}</div>
      <div v-else-if="!results.length" class="p-3 text-muted small">Sin resultados.</div>
      <button
        v-for="item in results"
        :key="`${item.type}-${item.id}`"
        type="button"
        class="search-result"
        @click="openResult(item)"
      >
        <span class="result-icon"><i class="bx bx-file-find"></i></span>
        <span><strong>{{ item.label }}</strong><small>{{ item.subtitle }}</small></span>
        <i class="bx bx-chevron-right result-arrow"></i>
      </button>
    </div>
  </div>
</template>

<style scoped>
.search-dropdown {
  z-index: 25;
  max-height: 360px;
  overflow-y: auto;
  border-radius: .65rem;
  box-shadow: 0 1rem 2.5rem rgba(15, 23, 42, .14) !important;
}
.search-control :deep(.form-control), .search-control .input-group-text, .clear-button { background: var(--bs-body-bg); min-height: 2.8rem; }
.search-control .input-group-text { color: var(--bs-primary); }
.clear-button { border-color: var(--bs-border-color); border-left: 0; color: var(--bs-secondary-color); }
.loading-indicator { border-left: 0; }
.search-result { align-items: center; background: transparent; border: 0; border-bottom: 1px solid var(--bs-border-color); color: inherit; display: grid; gap: .7rem; grid-template-columns: auto minmax(0, 1fr) auto; padding: .75rem .85rem; text-align: left; width: 100%; }
.search-result:hover, .search-result:focus { background: rgba(var(--bs-primary-rgb), .055); outline: none; }
.search-result span:not(.result-icon) { display: flex; flex-direction: column; min-width: 0; }
.search-result strong, .search-result small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.search-result small { color: var(--bs-secondary-color); }
.result-icon { align-items: center; background: rgba(var(--bs-primary-rgb), .1); border-radius: .45rem; color: var(--bs-primary); display: inline-flex; height: 2rem; justify-content: center; width: 2rem; }
.result-arrow { color: var(--bs-secondary-color); }
.search-message { align-items: center; color: var(--bs-secondary-color); display: flex; gap: .45rem; padding: .9rem; }
.search-result:last-child { border-bottom: 0; }
@media (max-width: 575.98px) {
  .global-search :deep(.form-control) { font-size: .82rem; }
}
</style>
