<script>
import axios from "axios";
import SupportHelpButton from "./help-button.vue";
import { formatSupportError } from "./module-utils";

export default {
  components: { SupportHelpButton },
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
    buttonLabel: {
      type: String,
      default: "Buscar",
    },
  },
  emits: ["selected"],
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

      this.debounceId = window.setTimeout(() => this.fetchStudents(), 300);
    },
    async fetchStudents() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/apoyo-profesional/students", {
          params: {
            search: this.search,
            course_section_id: this.courseSectionId || null,
          },
        });
        this.results = response.data.data || [];
        this.opened = true;
      } catch (error) {
        this.error = formatSupportError(error, "No se pudo realizar la búsqueda.");
      } finally {
        this.loading = false;
      }
    },
    selectStudent(student) {
      this.search = student.full_name;
      this.opened = false;
      this.$emit("selected", student);

      if (this.autoNavigate) {
        this.$router.push({ path: "/apoyo-profesional/historial", query: { student_id: student.id } });
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
  <div class="position-relative support-student-search">
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
      <SupportHelpButton
        title="Ayuda: búsqueda de estudiantes"
        text="Busca estudiantes por nombre, apellido o RUT para abrir rápidamente su historial de apoyo o asociarlos a una nueva gestión."
      />
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
.support-student-search .card {
  max-height: 22rem;
  overflow-y: auto;
}
</style>
