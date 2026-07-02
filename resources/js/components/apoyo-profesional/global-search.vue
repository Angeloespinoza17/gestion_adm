<script>
import axios from "axios";
import SupportHelpButton from "./help-button.vue";
import { formatSupportError } from "./module-utils";

export default {
  components: { SupportHelpButton },
  props: {
    placeholder: {
      type: String,
      default: "Buscar estudiante, curso, profesional o motivo",
    },
  },
  data() {
    return {
      loading: false,
      search: "",
      results: [],
      error: null,
      debounceId: null,
      opened: false,
    };
  },
  methods: {
    onInput() {
      window.clearTimeout(this.debounceId);
      if ((this.search || "").trim().length < 2) {
        this.results = [];
        this.opened = false;
        return;
      }

      this.debounceId = window.setTimeout(() => this.fetchResults(), 300);
    },
    async fetchResults() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/apoyo-profesional/search", {
          params: { search: this.search },
        });
        this.results = response.data.data || [];
        this.opened = true;
      } catch (error) {
        this.error = formatSupportError(error, "No se pudo realizar la búsqueda.");
      } finally {
        this.loading = false;
      }
    },
    selectResult(result) {
      this.opened = false;
      this.search = result.label;
      this.$router.push({ path: result.route, query: result.query || {} });
    },
    closeLater() {
      window.setTimeout(() => {
        this.opened = false;
      }, 150);
    },
  },
};
</script>

<template>
  <div class="position-relative support-global-search">
    <div class="input-group">
      <span class="input-group-text"><i class="bx bx-search-alt"></i></span>
      <BFormInput
        v-model="search"
        :placeholder="placeholder"
        autocomplete="off"
        @input="onInput"
        @focus="opened = results.length > 0"
        @blur="closeLater"
        @keyup.enter="fetchResults"
      />
      <BButton variant="primary" :disabled="loading" @click="fetchResults">
        {{ loading ? "Buscando..." : "Buscar" }}
      </BButton>
      <SupportHelpButton
        title="Ayuda: buscador global"
        text="Desde aquí puedes buscar estudiantes, atenciones o coincidencias relevantes del módulo para abrir rápidamente la sección asociada."
      />
    </div>

    <div v-if="opened" class="card shadow-sm position-absolute start-0 end-0 mt-1 z-3">
      <div v-if="loading" class="p-3 text-muted small">Buscando coincidencias...</div>
      <div v-else-if="error" class="p-3 text-danger small">{{ error }}</div>
      <div v-else-if="!results.length" class="p-3 text-muted small">Sin resultados.</div>
      <button
        v-for="(result, index) in results"
        :key="`${result.type}-${index}`"
        type="button"
        class="btn btn-link text-start text-decoration-none border-bottom rounded-0 px-3 py-2"
        @mousedown.prevent="selectResult(result)"
      >
        <div class="fw-semibold text-dark">{{ result.label }}</div>
        <div class="small text-muted">{{ result.subtitle }}</div>
      </button>
    </div>
  </div>
</template>

<style scoped>
.support-global-search .card {
  max-height: 22rem;
  overflow-y: auto;
}
</style>
