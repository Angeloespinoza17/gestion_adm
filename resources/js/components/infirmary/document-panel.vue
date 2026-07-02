<script>
import axios from "axios";
import InfirmaryHelpButton from "./help-button.vue";
import {
  confirmInfirmaryAction,
  downloadInfirmaryFile,
  fileSizeLabel,
  formatInfirmaryDateTime,
  formatInfirmaryError,
  humanizeInfirmaryStatus,
  showInfirmaryError,
  showInfirmarySuccess,
  showInfirmaryWarning,
} from "./module-utils";

export default {
  components: { InfirmaryHelpButton },
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
    helpText: {
      type: String,
      default: "En esta sección puedes cargar, descargar y eliminar respaldos asociados al registro clínico seleccionado.",
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
        notes: "",
        document: null,
      },
    };
  },
  computed: {
    categoryOptions() {
      return [{ value: "", text: "Categoría" }].concat(
        (this.categories || []).map((item) => ({
          value: typeof item === "string" ? item : item.value,
          text: typeof item === "string" ? humanizeInfirmaryStatus(item) : item.label,
        }))
      );
    },
  },
  methods: {
    formatInfirmaryDateTime,
    fileSizeLabel,
    humanizeInfirmaryStatus,
    onFileChange(event) {
      this.form.document = event.target.files?.[0] || null;
    },
    async upload() {
      if (!this.uploadUrl) {
        await showInfirmaryWarning("Primero debes guardar el registro principal antes de adjuntar documentos.");
        return;
      }

      if (!this.form.document) {
        await showInfirmaryWarning("Debes seleccionar un archivo para continuar.");
        return;
      }

      this.uploading = true;
      try {
        const formData = new FormData();
        formData.append("document", this.form.document);
        if (this.form.category) formData.append("category", this.form.category);
        if (this.form.notes) formData.append("notes", this.form.notes);
        if (this.studentId) formData.append("student_profile_id", this.studentId);

        await axios.post(this.uploadUrl, formData, {
          headers: { "Content-Type": "multipart/form-data" },
        });
        this.form = { category: "", notes: "", document: null };
        this.$emit("refresh");
        await showInfirmarySuccess("Documento cargado correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo cargar el documento."));
      } finally {
        this.uploading = false;
      }
    },
    async download(document) {
      try {
        await downloadInfirmaryFile(`/api/infirmary/documents/${document.id}/download`, document.original_name);
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo descargar el documento."));
      }
    },
    async remove(document) {
      const confirmation = await confirmInfirmaryAction({
        title: "Eliminar documento",
        text: `Se eliminará ${document.original_name}.`,
        confirmButtonText: "Eliminar",
      });

      if (!confirmation.isConfirmed) return;

      try {
        await axios.delete(`/api/infirmary/documents/${document.id}`);
        this.$emit("refresh");
        await showInfirmarySuccess("Documento eliminado correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo eliminar el documento."));
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
        <InfirmaryHelpButton title="Ayuda: adjuntos" :text="helpText" />
      </div>
    </template>

    <div v-if="canUpload" class="row g-2 mb-3">
      <div class="col-md-3">
        <BFormSelect v-model="form.category" :options="categoryOptions" />
      </div>
      <div class="col-md-4">
        <BFormInput type="file" @change="onFileChange" />
      </div>
      <div class="col-md-3">
        <BFormInput v-model="form.notes" placeholder="Notas del adjunto" />
      </div>
      <div class="col-md-2">
        <BButton variant="primary" class="w-100" :disabled="uploading" @click="upload">
          {{ uploading ? "Cargando..." : "Subir" }}
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
            <td>{{ humanizeInfirmaryStatus(document.category) }}</td>
            <td>{{ fileSizeLabel(document.file_size) }}</td>
            <td>{{ formatInfirmaryDateTime(document.created_at) }}</td>
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
