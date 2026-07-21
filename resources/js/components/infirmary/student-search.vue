<script>
import axios from "axios";
import InfirmaryHelpButton from "./help-button.vue";
import { formatInfirmaryError } from "./module-utils";

export default {
  components: { InfirmaryHelpButton },
  props: {
    placeholder: {
      type: String,
      default: "Buscar por nombre, apellido o RUT",
    },
    courseSectionId: {
      type: [Number, String, null],
      default: null,
    },
    autoNavigate: {
      type: Boolean,
      default: false,
    },
    helpTitle: {
      type: String,
      default: "Ayuda: buscador global",
    },
    helpText: {
      type: String,
      default: "Desde aquí puedes buscar estudiantes por nombre, apellido o RUT para abrir rápidamente su ficha clínica.",
    },
    buttonLabel: {
      type: String,
      default: "Buscar",
    },
  },
  emits: ["selected", "cleared"],
  data() {
    return {
      loading: false,
      search: "",
      results: [],
      error: null,
      debounceId: null,
      opened: false,
      selectedId: null,
      requestSequence: 0,
    };
  },
  methods: {
    onInput() {
      if (this.selectedId) {
        this.selectedId = null;
        this.$emit("cleared");
      }
      window.clearTimeout(this.debounceId);
      if ((this.search || "").trim().length < 2) {
        this.results = [];
        this.opened = false;
        return;
      }

      this.debounceId = window.setTimeout(() => this.fetchStudents(), 300);
    },
    async fetchStudents() {
      const search = this.search.trim();
      if (search.length < 2) return;
      const requestSequence = ++this.requestSequence;
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/infirmary/students", {
          params: {
            search,
            course_section_id: this.courseSectionId || null,
          },
        });
        if (requestSequence !== this.requestSequence || search !== this.search.trim()) return;
        this.results = response.data.data || [];
        this.opened = true;
      } catch (error) {
        if (requestSequence !== this.requestSequence) return;
        this.error = formatInfirmaryError(error, "No se pudo realizar la búsqueda.");
      } finally {
        if (requestSequence === this.requestSequence) this.loading = false;
      }
    },
    selectStudent(student) {
      this.search = student.full_name;
      this.selectedId = student.id;
      this.opened = false;
      this.$emit("selected", student);

      if (this.autoNavigate) {
        this.$router.push({ path: "/infirmary/history", query: { student_id: student.id } });
      }
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
  <div class="position-relative infirmary-student-search">
    <div class="input-group">
      <span class="input-group-text"><i class="bx bx-search-alt"></i></span>
      <BFormInput
        v-model="search"
        :placeholder="placeholder"
        autocomplete="off"
        @input="onInput"
        @focus="opened = results.length > 0"
        @blur="closeLater"
        @keyup.enter="fetchStudents"
      />
      <BButton variant="primary" :disabled="loading" @click="fetchStudents">
        {{ loading ? "Buscando..." : buttonLabel }}
      </BButton>
      <InfirmaryHelpButton :title="helpTitle" :text="helpText" />
    </div>

    <div v-if="opened" class="card shadow-sm position-absolute start-0 end-0 mt-1 z-3">
      <div v-if="loading" class="p-3 text-muted small">Buscando estudiantes...</div>
      <div v-else-if="error" class="p-3 text-danger small">{{ error }}</div>
      <div v-else-if="!results.length" class="p-3 text-muted small">Sin resultados.</div>
      <button
        v-for="student in results"
        :key="student.id"
        type="button"
        class="btn btn-link text-start text-decoration-none border-bottom rounded-0 px-3 py-2"
        @mousedown.prevent="selectStudent(student)"
      >
        <div class="fw-semibold text-dark">{{ student.full_name }}</div>
        <div class="small text-muted">
          {{ student.rut || "Sin RUT" }} · {{ student.course || "Sin curso" }} · {{ student.age ?? "-" }} años
        </div>
        <div v-if="student.alerts?.length" class="small text-warning">
          {{ student.alerts.join(" · ") }}
        </div>
      </button>
    </div>
  </div>
</template>

<style scoped>
.infirmary-student-search .card {
  max-height: 22rem;
  overflow-y: auto;
}
</style>
