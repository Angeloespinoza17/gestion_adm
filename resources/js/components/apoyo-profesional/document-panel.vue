<script>
import axios from "axios";
import SupportHelpButton from "./help-button.vue";
import {
  confirmSupportAction,
  downloadSupportFile,
  fileSizeLabel,
  formatSupportDateTime,
  formatSupportError,
  humanizeSupportStatus,
  showSupportError,
  showSupportSuccess,
  showSupportWarning,
} from "./module-utils";

export default {
  components: { SupportHelpButton },
  props: {
    documents: {
      type: Array,
      default: () => [],
    },
    uploadUrl: {
      type: String,
      default: "",
    },
    categories: {
      type: Array,
      default: () => [],
    },
    studentId: {
      type: [Number, String, null],
      default: null,
    },
    title: {
      type: String,
      default: "Adjuntos",
    },
    canUpload: {
      type: Boolean,
      default: true,
    },
    canDelete: {
      type: Boolean,
      default: true,
    },
  },
  emits: ["refresh"],
  data() {
    return {
      uploading: false,
      form: {
        category: "",
        confidentiality_level: "general",
        notes: "",
        document: null,
      },
    };
  },
  computed: {
    categoryOptions() {
      return [{ value: "", text: "Categoría" }].concat(
        (this.categories || []).map((item) => ({
          value: item.value,
          text: item.label,
        }))
      );
    },
    confidentialityOptions() {
      return [
        { value: "general", text: "General" },
        { value: "reservada", text: "Reservada" },
        { value: "confidencial", text: "Confidencial" },
        { value: "alta_confidencialidad", text: "Alta confidencialidad" },
      ];
    },
  },
  methods: {
    formatSupportDateTime,
    fileSizeLabel,
    humanizeSupportStatus,
    onFileChange(event) {
      this.form.document = event.target.files?.[0] || null;
    },
    async upload() {
      if (!this.uploadUrl) {
        await showSupportWarning("Primero debes guardar el registro principal antes de adjuntar documentos.");
        return;
      }

      if (!this.form.document) {
        await showSupportWarning("Debes seleccionar un archivo para continuar.");
        return;
      }

      this.uploading = true;
      try {
        const formData = new FormData();
        formData.append("document", this.form.document);
        if (this.form.category) formData.append("category", this.form.category);
        if (this.form.confidentiality_level) formData.append("confidentiality_level", this.form.confidentiality_level);
        if (this.form.notes) formData.append("notes", this.form.notes);
        if (this.studentId) formData.append("student_profile_id", this.studentId);

        await axios.post(this.uploadUrl, formData, {
          headers: { "Content-Type": "multipart/form-data" },
        });
        this.form = { category: "", confidentiality_level: "general", notes: "", document: null };
        this.$emit("refresh");
        await showSupportSuccess("Documento cargado correctamente.");
      } catch (error) {
        await showSupportError(formatSupportError(error, "No se pudo cargar el documento."));
      } finally {
        this.uploading = false;
      }
    },
    async download(document) {
      try {
        await downloadSupportFile(`/api/apoyo-profesional/documents/${document.id}/download`, document.original_name);
      } catch (error) {
        await showSupportError(formatSupportError(error, "No se pudo descargar el documento."));
      }
    },
    async remove(document) {
      const confirmation = await confirmSupportAction({
        title: "Eliminar documento",
        text: `Se eliminará ${document.original_name}.`,
        confirmButtonText: "Eliminar",
      });

      if (!confirmation.isConfirmed) return;

      try {
        await axios.delete(`/api/apoyo-profesional/documents/${document.id}`);
        this.$emit("refresh");
        await showSupportSuccess("Documento eliminado correctamente.");
      } catch (error) {
        await showSupportError(formatSupportError(error, "No se pudo eliminar el documento."));
      }
    },
  },
};
</script>

<template>
  <BCard>
    <template #header>
      <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <div class="fw-semibold">{{ title }}</div>
        <SupportHelpButton
          title="Ayuda: adjuntos"
          text="En esta sección puedes cargar, descargar y eliminar respaldos asociados al registro seleccionado, respetando el nivel de confidencialidad definido."
        />
      </div>
    </template>

    <div v-if="canUpload" class="row g-2 mb-3">
      <div class="col-md-3">
        <BFormSelect v-model="form.category" :options="categoryOptions" />
      </div>
      <div class="col-md-3">
        <BFormSelect v-model="form.confidentiality_level" :options="confidentialityOptions" />
      </div>
      <div class="col-md-3">
        <BFormInput type="file" @change="onFileChange" />
      </div>
      <div class="col-md-2">
        <BFormInput v-model="form.notes" placeholder="Notas del adjunto" />
      </div>
      <div class="col-md-1">
        <BButton variant="primary" class="w-100" :disabled="uploading" @click="upload">
          {{ uploading ? "..." : "Subir" }}
        </BButton>
      </div>
    </div>

    <div v-if="!documents.length" class="text-muted">No hay documentos cargados.</div>

    <div v-else class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>Archivo</th>
            <th>Categoría</th>
            <th>Confidencialidad</th>
            <th>Tamaño</th>
            <th>Subido</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="document in documents" :key="document.id">
            <td>
              <div class="fw-semibold">{{ document.original_name }}</div>
              <div class="small text-muted">{{ document.notes || "Sin observaciones" }}</div>
            </td>
            <td>{{ humanizeSupportStatus(document.category) }}</td>
            <td>{{ humanizeSupportStatus(document.confidentiality_level) }}</td>
            <td>{{ fileSizeLabel(document.file_size) }}</td>
            <td>{{ formatSupportDateTime(document.created_at) }}</td>
            <td class="text-end">
              <div class="btn-group btn-group-sm">
                <BButton variant="outline-primary" @click="download(document)">Descargar</BButton>
                <BButton v-if="canDelete" variant="outline-danger" @click="remove(document)">Eliminar</BButton>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </BCard>
</template>
