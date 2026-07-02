<script>
import axios from "axios";
import LoadingState from "../../ui/loading-state.vue";
import SupportStudentSearch from "../student-search.vue";
import SupportDocumentPanel from "../document-panel.vue";
import SupportStudentContextCard from "../student-context-card.vue";
import { formatSupportError } from "../module-utils";

export default {
  components: {
    LoadingState,
    SupportStudentSearch,
    SupportDocumentPanel,
    SupportStudentContextCard,
  },
  props: {
    catalogs: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      loading: false,
      error: null,
      selectedStudent: null,
      payload: null,
    };
  },
  methods: {
    async selectStudent(student) {
      this.selectedStudent = student;
      await this.loadDocuments();
    },
    async loadDocuments() {
      if (!this.selectedStudent?.id) return;

      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get(`/api/apoyo-profesional/student-history/${this.selectedStudent.id}`);
        this.payload = response.data;
      } catch (error) {
        this.error = formatSupportError(error, "No se pudieron cargar los documentos del estudiante.");
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<template>
  <div>
    <BCard class="mb-3">
      <div class="row g-3 align-items-center">
        <div class="col-xl-8">
          <SupportStudentSearch @selected="selectStudent" />
        </div>
        <div class="col-xl-4">
          <div class="small text-muted">
            Selecciona un estudiante para revisar todos los adjuntos visibles asociados a su historial de apoyo.
          </div>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
    <BCard v-if="loading">
      <LoadingState message="Cargando documentos del estudiante..." compact />
    </BCard>

    <div v-else-if="payload" class="row g-3">
      <div class="col-xl-4">
        <SupportStudentContextCard :student="payload.student" />
      </div>
      <div class="col-xl-8">
        <SupportDocumentPanel
          :documents="payload.documents || []"
          :categories="catalogs.document_categories"
          :student-id="payload.student?.id"
          :can-upload="false"
          @refresh="loadDocuments"
        />
      </div>
    </div>
  </div>
</template>
