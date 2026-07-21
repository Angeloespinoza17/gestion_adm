<script>
import axios from "axios";
import {
  formatPmeError,
  showPmeError,
} from "./module-utils";

export default {
  data() {
    return {
      loading: false,
      search: "",
      results: [],
      timer: null,
    };
  },
  methods: {
    queueSearch() {
      clearTimeout(this.timer);
      if (!this.search.trim()) {
        this.results = [];
        return;
      }

      this.timer = setTimeout(() => this.performSearch(), 300);
    },
    async performSearch() {
      this.loading = true;
      try {
        const response = await axios.get("/api/pme-sep/search", {
          params: { search: this.search.trim() },
        });
        this.results = response.data.data || [];
      } catch (error) {
        this.results = [];
        showPmeError(formatPmeError(error, "No se pudo ejecutar la búsqueda global PME / SEP."));
      } finally {
        this.loading = false;
      }
    },
    openResult(item) {
      this.$router.push(item.route);
      this.search = "";
      this.results = [];
    },
    clearSearch() {
      this.search = "";
      this.results = [];
    },
  },
};
</script>

<template>
  <div class="pme-search position-relative" role="search">
    <div class="input-group">
      <span class="input-group-text bg-white"><i class="bx bx-search"></i></span>
      <input
        v-model="search"
        type="text"
        class="form-control"
        aria-label="Buscar en todo el módulo PME y SEP"
        autocomplete="off"
        placeholder="Buscar en PME / SEP..."
        @input="queueSearch"
        @keyup.enter="performSearch"
        @keyup.esc="clearSearch"
      />
      <button v-if="search" class="btn btn-light" type="button" aria-label="Limpiar búsqueda" @click="clearSearch">
        <i class="bx bx-x"></i>
      </button>
      <button class="btn btn-outline-secondary" type="button" :disabled="loading || !search.trim()" @click="performSearch">
        <span v-if="loading" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
        <i v-else class="bx bx-right-arrow-alt"></i><span class="visually-hidden">Buscar</span>
      </button>
    </div>

    <div v-if="results.length" class="pme-search-results" aria-live="polite">
      <button
        v-for="item in results"
        :key="`${item.type}-${item.id}`"
        type="button"
        class="pme-search-result"
        @click="openResult(item)"
      >
        <div>
          <strong>{{ item.label }}</strong>
          <small>{{ item.subtitle }}</small>
        </div>
        <BBadge variant="light">{{ item.type }}</BBadge>
      </button>
    </div>
  </div>
</template>
