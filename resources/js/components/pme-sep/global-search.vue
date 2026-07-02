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
  },
};
</script>

<template>
  <div class="position-relative">
    <div class="input-group">
      <span class="input-group-text bg-white"><i class="bx bx-search"></i></span>
      <input
        v-model="search"
        type="text"
        class="form-control"
        placeholder="Buscar PME, objetivo, estrategia, indicador, acción, estudiante, curso o evidencia..."
        @input="queueSearch"
      />
      <button class="btn btn-outline-secondary" type="button" :disabled="loading" @click="performSearch">
        {{ loading ? "Buscando..." : "Buscar" }}
      </button>
    </div>

    <BCard
      v-if="results.length"
      class="position-absolute w-100 border-0 shadow-sm mt-2 z-3"
      style="max-height: 320px; overflow-y: auto;"
    >
      <div
        v-for="item in results"
        :key="`${item.type}-${item.id}`"
        class="d-flex justify-content-between align-items-start gap-2 py-2 border-bottom cursor-pointer"
        role="button"
        @click="openResult(item)"
      >
        <div>
          <div class="fw-semibold">{{ item.label }}</div>
          <div class="small text-muted">{{ item.subtitle }}</div>
        </div>
        <BBadge variant="light">{{ item.type }}</BBadge>
      </div>
    </BCard>
  </div>
</template>
