<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import HelpButton from "../../components/risk-prevention/help-button.vue";
import StatusBadge from "../../components/risk-prevention/status-badge.vue";
import {
  confirmRiskAction,
  downloadRiskFile,
  formatRiskDate,
  formatRiskError,
  showRiskError,
  showRiskSuccess,
  showRiskWarning,
} from "../../components/risk-prevention/module-utils";

const emptyForm = () => ({
  id: null,
  document_type: "protocolo",
  title: "",
  document_group: "",
  version_number: "",
  valid_from: "",
  valid_until: "",
  status: "vigente",
  responsible_name: "",
  notes: "",
  document: null,
});

export default {
  components: { Layout, LoadingState, HelpButton, StatusBadge },
  data() {
    return {
      loading: false,
      saving: false,
      warningShown: false,
      error: null,
      items: [],
      filters: { search: "", document_type: "", status: "" },
      showModal: false,
      form: emptyForm(),
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
  },
  mounted() {
    this.loadItems();
  },
  methods: {
    formatRiskDate,
    async loadItems() {
      this.loading = true;
      try {
        const response = await axios.get("/api/risk-prevention/documents", {
          params: { ...this.filters, per_page: 100 },
        });
        this.items = response.data.data || [];
        this.maybeShowWarnings();
      } catch (error) {
        this.error = formatRiskError(error, "No se pudieron cargar los documentos.");
        showRiskError(this.error);
      } finally {
        this.loading = false;
      }
    },
    async maybeShowWarnings() {
      if (this.warningShown) return;
      const due = this.items.filter((item) => ["por_vencer", "vencido"].includes(item.current_status)).length;
      if (!due) return;
      this.warningShown = true;
      await showRiskWarning(`Hay ${due} documentos próximos a vencer o vencidos.`, "Vigencia documental");
    },
    openCreate() {
      this.form = {
        ...emptyForm(),
        valid_from: new Date().toISOString().slice(0, 10),
      };
      this.showModal = true;
    },
    openEdit(item) {
      this.form = {
        id: item.id,
        document_type: item.document_type || "protocolo",
        title: item.title || "",
        document_group: item.document_group || "",
        version_number: item.version_number || "",
        valid_from: item.valid_from || "",
        valid_until: item.valid_until || "",
        status: item.status || "vigente",
        responsible_name: item.responsible_name || "",
        notes: item.notes || "",
        document: null,
      };
      this.showModal = true;
    },
    buildFormData() {
      const formData = new FormData();
      Object.entries(this.form).forEach(([key, value]) => {
        if (key === "id") return;
        if (key === "document") {
          if (value) formData.append("document", value);
          return;
        }
        formData.append(key, value ?? "");
      });
      return formData;
    },
    async save() {
      this.saving = true;
      try {
        const payload = this.buildFormData();
        if (this.isEditing) {
          payload.append("_method", "PUT");
          await axios.post(`/api/risk-prevention/documents/${this.form.id}`, payload);
          await showRiskSuccess("El documento fue actualizado correctamente.");
        } else {
          await axios.post("/api/risk-prevention/documents", payload);
          await showRiskSuccess("El documento fue registrado correctamente.");
        }
        this.showModal = false;
        this.loadItems();
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo guardar el documento."));
      } finally {
        this.saving = false;
      }
    },
    async remove(item) {
      const result = await confirmRiskAction({
        title: "Eliminar documento",
        text: `Se eliminará ${item.title}.`,
        confirmButtonText: "Sí, eliminar",
      });
      if (!result.isConfirmed) return;

      try {
        await axios.delete(`/api/risk-prevention/documents/${item.id}`);
        await showRiskSuccess("El documento fue eliminado correctamente.");
        this.loadItems();
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo eliminar el documento."));
      }
    },
    async download(item) {
      try {
        await downloadRiskFile(`/api/risk-prevention/documents/${item.id}/download`, item.document_name || `${item.title}.txt`);
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo descargar el documento."));
      }
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Centro de Documentos</h4>
        <div class="text-muted">Protocolos, reglamentos, instructivos e informes con versión y vigencia.</div>
      </div>
      <div class="d-flex gap-2">
        <HelpButton
          title="Ayuda: centro de documentos"
          text="Permite almacenar y controlar la vigencia de documentos preventivos con versionado simple."
        />
        <BButton variant="primary" @click="openCreate">Nuevo documento</BButton>
      </div>
    </div>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-md-5">
          <BFormInput v-model="filters.search" placeholder="Buscar por título, grupo o responsable" @keyup.enter="loadItems" />
        </div>
        <div class="col-md-3">
          <BFormSelect v-model="filters.document_type" :options="[
            { value: '', text: 'Todos los tipos' },
            { value: 'protocolo', text: 'Protocolo' },
            { value: 'reglamento', text: 'Reglamento' },
            { value: 'instructivo', text: 'Instructivo' },
            { value: 'informe', text: 'Informe' },
          ]" />
        </div>
        <div class="col-md-2">
          <BFormSelect v-model="filters.status" :options="[
            { value: '', text: 'Todos los estados' },
            { value: 'vigente', text: 'Vigente' },
            { value: 'por_vencer', text: 'Por vencer' },
            { value: 'vencido', text: 'Vencido' },
            { value: 'archivado', text: 'Archivado' },
          ]" />
        </div>
        <div class="col-md-2">
          <BButton variant="secondary" class="w-100" @click="loadItems">Filtrar</BButton>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
    <BCard>
      <LoadingState v-if="loading" message="Cargando documentos..." />
      <div v-else class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Documento</th>
              <th>Grupo</th>
              <th>Versión</th>
              <th>Vigencia</th>
              <th>Estado</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td>
                <div class="fw-semibold">{{ item.title }}</div>
                <div class="small text-muted">{{ item.responsible_name || "-" }}</div>
              </td>
              <td>{{ item.document_group || "-" }}</td>
              <td>{{ item.version_number }}</td>
              <td>{{ formatRiskDate(item.valid_from) }} a {{ formatRiskDate(item.valid_until) }}</td>
              <td><StatusBadge :status="item.current_status" /></td>
              <td class="text-end">
                <div class="d-flex justify-content-end gap-2">
                  <BButton size="sm" variant="outline-primary" @click="openEdit(item)">Editar</BButton>
                  <BButton size="sm" variant="outline-info" :disabled="!item.document_path" @click="download(item)">Descargar</BButton>
                  <BButton size="sm" variant="outline-danger" @click="remove(item)">Eliminar</BButton>
                </div>
              </td>
            </tr>
            <tr v-if="!items.length">
              <td colspan="6" class="text-center text-muted py-4">No hay documentos registrados.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </BCard>

    <BModal v-model="showModal" size="lg" :title="isEditing ? 'Editar documento' : 'Nuevo documento'" hide-footer>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-muted small">Registra tipo, versión y vigencia del documento preventivo.</div>
        <HelpButton title="Ayuda del formulario" text="Completa los datos del documento y adjunta el archivo de respaldo si corresponde." />
      </div>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Tipo</label>
          <BFormSelect v-model="form.document_type" :options="[
            { value: 'protocolo', text: 'Protocolo' },
            { value: 'reglamento', text: 'Reglamento' },
            { value: 'instructivo', text: 'Instructivo' },
            { value: 'informe', text: 'Informe' },
          ]" />
        </div>
        <div class="col-md-8">
          <label class="form-label">Título</label>
          <BFormInput v-model="form.title" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Grupo documental</label>
          <BFormInput v-model="form.document_group" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Versión</label>
          <BFormInput v-model="form.version_number" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Responsable</label>
          <BFormInput v-model="form.responsible_name" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Vigente desde</label>
          <BFormInput v-model="form.valid_from" type="date" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Vigente hasta</label>
          <BFormInput v-model="form.valid_until" type="date" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Estado base</label>
          <BFormSelect v-model="form.status" :options="[
            { value: 'vigente', text: 'Vigente' },
            { value: 'por_vencer', text: 'Por vencer' },
            { value: 'vencido', text: 'Vencido' },
            { value: 'archivado', text: 'Archivado' },
          ]" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Archivo</label>
          <BFormFile @change="form.document = $event.target.files[0] || null" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Observaciones</label>
          <BFormTextarea v-model="form.notes" rows="3" />
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="secondary" @click="showModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">{{ saving ? "Guardando..." : "Guardar" }}</BButton>
      </div>
    </BModal>
  </Layout>
</template>
