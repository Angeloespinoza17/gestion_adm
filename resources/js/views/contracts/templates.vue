<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";
import Swal from "sweetalert2";

const emptyForm = () => ({
  id: null,
  name: "",
  contract_type: null,
  description: "",
  active: true,
  body: "{{clausulas}}\n\n{{firmas}}",
  clause_ids: [],
  available_variables: [],
  internal_notes: "",
});

export default {
  components: { Layout, LoadingState, Multiselect },
  data() {
    return {
      loading: false,
      saving: false,
      previewing: false,
      syncingPreview: false,
      error: null,
      templates: [],
      catalogs: {
        clauses: [],
        contract_types: [],
        available_variables: {},
      },
      filters: {
        search: "",
        active: null,
      },
      showFormModal: false,
      form: emptyForm(),
      templatePreviewContent: "",
      autoPreviewTimer: null,
    };
  },
  computed: {
    permissions() {
      try {
        return JSON.parse(localStorage.getItem("permissions") || "[]");
      } catch (error) {
        return [];
      }
    },
    canManage() {
      return this.permissions.includes("administrar_plantillas_contrato");
    },
    clauseOptions() {
      return (this.catalogs.clauses || []).map((item) => ({
        value: item.id,
        label: `${item.title}${item.is_required ? " · obligatoria" : ""}`,
      }));
    },
    contractTypeOptions() {
      return (this.catalogs.contract_types || []).map((item) => ({
        value: item.value,
        label: item.label,
      }));
    },
    variableOptions() {
      return Object.values(this.catalogs.available_variables || {})
        .flat()
        .map((item) => ({ value: item, label: item }));
    },
    activeOptions() {
      return [
        { value: null, label: "Todos" },
        { value: true, label: "Activas" },
        { value: false, label: "Inactivas" },
      ];
    },
    previewSource() {
      return JSON.stringify({
        body: this.form.body,
        clause_ids: this.form.clause_ids,
      });
    },
  },
  watch: {
    previewSource() {
      this.schedulePreview();
    },
  },
  mounted() {
    this.loadCatalogs();
    this.loadTemplates();
  },
  beforeUnmount() {
    if (this.autoPreviewTimer) {
      clearTimeout(this.autoPreviewTimer);
    }
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/contract-templates/catalogs");
      this.catalogs = response.data;
      this.schedulePreview();
    },
    async loadTemplates() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/contract-templates", {
          params: this.filters,
        });
        this.templates = response.data.data;
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    edit(item) {
      this.form = {
        id: item.id,
        name: item.name || "",
        contract_type: item.contract_type || null,
        description: item.description || "",
        active: Boolean(item.active),
        body: item.body || "{{clausulas}}\n\n{{firmas}}",
        clause_ids: (item.clauses || []).map((clause) => clause.id),
        available_variables: item.available_variables || [],
        internal_notes: item.internal_notes || "",
      };
      this.showFormModal = true;
      this.schedulePreview();
    },
    openCreateForm() {
      this.resetForm();
      this.showFormModal = true;
    },
    closeFormModal() {
      this.showFormModal = false;
    },
    resetForm() {
      this.form = emptyForm();
      this.templatePreviewContent = "";
      this.schedulePreview();
    },
    moveClause(index, direction) {
      const next = [...this.form.clause_ids];
      const target = index + direction;
      if (target < 0 || target >= next.length) return;
      [next[index], next[target]] = [next[target], next[index]];
      this.form.clause_ids = next;
    },
    clauseLabel(id) {
      return this.clauseOptions.find((item) => item.value === id)?.label || `Cláusula ${id}`;
    },
    canGeneratePreview() {
      return Boolean((this.form.body || "").trim() || (this.form.clause_ids || []).length);
    },
    schedulePreview() {
      if (this.autoPreviewTimer) {
        clearTimeout(this.autoPreviewTimer);
      }

      if (!this.canGeneratePreview()) {
        this.templatePreviewContent = "";
        return;
      }

      this.autoPreviewTimer = setTimeout(() => {
        this.preview({ silent: true });
      }, 350);
    },
    async preview({ silent = false } = {}) {
      if (!this.canGeneratePreview()) {
        this.templatePreviewContent = "";
        return;
      }

      this.previewing = !silent;
      this.syncingPreview = silent;

      try {
        const response = await axios.post("/api/contract-templates/preview", {
          body: this.form.body,
          clause_ids: this.form.clause_ids,
        });
        this.templatePreviewContent = response.data.data.content || "";
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.previewing = false;
        this.syncingPreview = false;
      }
    },
    async save() {
      this.saving = true;
      this.error = null;
      try {
        if (this.form.id) {
          await axios.put(`/api/contract-templates/${this.form.id}`, this.form);
        } else {
          await axios.post("/api/contract-templates", this.form);
        }
        this.closeFormModal();
        this.resetForm();
        await this.loadTemplates();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async toggle(item) {
      await axios.put(`/api/contract-templates/${item.id}/active`, { active: !item.active });
      await this.loadTemplates();
    },
    async remove(item) {
      const result = await Swal.fire({
        title: "Eliminar plantilla",
        text: "La plantilla será eliminada.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
      });
      if (!result.isConfirmed) return;
      await axios.delete(`/api/contract-templates/${item.id}`);
      await this.loadTemplates();
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (
        (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
        error?.response?.data?.message ||
        error?.message ||
        "Error desconocido"
      );
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-sm-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-0">Plantillas de contrato</h4>
        <div class="text-muted">Define estructura base, variables y cláusulas reutilizables.</div>
      </div>
      <div class="mt-3 mt-sm-0">
        <BButton variant="primary" @click="openCreateForm">
          Nueva plantilla
        </BButton>
      </div>
    </div>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

    <div class="row g-4">
      <div class="col-12">
        <BCard class="mb-4">
          <div class="row g-3 align-items-end">
            <div class="col-md-8">
              <label class="form-label">Buscar</label>
              <BFormInput v-model="filters.search" @keyup.enter="loadTemplates" />
            </div>
            <div class="col-md-2">
              <label class="form-label">Estado</label>
              <Multiselect v-model="filters.active" :options="activeOptions" />
            </div>
            <div class="col-md-2">
              <BButton variant="primary" class="w-100" @click="loadTemplates">Filtrar</BButton>
            </div>
          </div>
        </BCard>

        <BCard no-body>
          <div class="table-responsive">
            <BTableSimple class="table align-middle table-nowrap mb-0">
              <BThead class="table-light">
                <BTr>
                  <BTh>Plantilla</BTh>
                  <BTh>Tipo</BTh>
                  <BTh>Cláusulas</BTh>
                  <BTh>Estado</BTh>
                  <BTh class="text-end">Acciones</BTh>
                </BTr>
              </BThead>
              <BTbody>
                <BTr v-if="loading">
                  <BTd colspan="5" class="text-center py-4">
                    <LoadingState message="Cargando plantillas..." compact />
                  </BTd>
                </BTr>
                <BTr v-else-if="templates.length === 0">
                  <BTd colspan="5" class="text-center py-4 text-muted">Sin plantillas registradas.</BTd>
                </BTr>
                <BTr v-for="item in templates" :key="item.id">
                  <BTd>
                    <div class="fw-semibold">{{ item.name }}</div>
                    <small class="text-muted">{{ item.description || "Sin descripción" }}</small>
                  </BTd>
                  <BTd>{{ contractTypeOptions.find((opt) => opt.value === item.contract_type)?.label || "-" }}</BTd>
                  <BTd>{{ (item.clauses || []).length }}</BTd>
                  <BTd>
                    <span :class="`badge rounded-pill badge-soft-${item.active ? 'success' : 'secondary'}`">
                      {{ item.active ? "Activa" : "Inactiva" }}
                    </span>
                  </BTd>
                  <BTd class="text-end">
                    <div class="d-flex justify-content-end gap-2">
                      <BButton size="sm" variant="outline-primary" @click="edit(item)">Editar</BButton>
                      <BButton size="sm" :variant="item.active ? 'warning' : 'success'" @click="toggle(item)">
                        {{ item.active ? "Desactivar" : "Activar" }}
                      </BButton>
                      <BButton v-if="canManage" size="sm" variant="danger" @click="remove(item)">Eliminar</BButton>
                    </div>
                  </BTd>
                </BTr>
              </BTbody>
            </BTableSimple>
          </div>
        </BCard>
      </div>
    </div>

    <BModal
      v-model="showFormModal"
      size="xl"
      title-class="fw-semibold"
      :title="form.id ? 'Editar plantilla' : 'Nueva plantilla'"
      hide-footer
      scrollable
      @hidden="resetForm"
    >
      <div class="row g-4">
        <div class="col-xl-6">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Nombre</label>
              <BFormInput v-model="form.name" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Tipo contrato</label>
              <Multiselect v-model="form.contract_type" :options="contractTypeOptions" :searchable="true" />
            </div>
            <div class="col-12">
              <label class="form-label">Descripción</label>
              <BFormInput v-model="form.description" />
            </div>
            <div class="col-12">
              <label class="form-label">Cuerpo base</label>
              <BFormTextarea v-model="form.body" rows="12" />
              <small class="text-muted">
                Usa <code v-text="'{{clausulas}}'"></code> y <code v-text="'{{firmas}}'"></code> para insertar bloques automáticos.
              </small>
            </div>
            <div class="col-12">
              <label class="form-label">Cláusulas asociadas</label>
              <Multiselect
                v-model="form.clause_ids"
                :options="clauseOptions"
                mode="multiple"
                :close-on-select="false"
                :searchable="true"
              />
            </div>
            <div class="col-12" v-if="form.clause_ids.length">
              <div class="border rounded p-2">
                <div class="small text-muted mb-2">
                  El orden mostrado aquí se convierte automáticamente en PRIMERO, SEGUNDO, TERCERO, etc. al generar el contrato.
                </div>
                <div v-for="(clauseId, index) in form.clause_ids" :key="`${clauseId}-${index}`" class="d-flex justify-content-between align-items-center py-2 border-bottom">
                  <span>{{ clauseLabel(clauseId) }}</span>
                  <div class="d-flex gap-2">
                    <BButton size="sm" variant="light" @click="moveClause(index, -1)">↑</BButton>
                    <BButton size="sm" variant="light" @click="moveClause(index, 1)">↓</BButton>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">Variables visibles en la plantilla</label>
              <Multiselect
                v-model="form.available_variables"
                :options="variableOptions"
                mode="multiple"
                :close-on-select="false"
                :searchable="true"
              />
            </div>
            <div class="col-12">
              <label class="form-label">Observaciones internas</label>
              <BFormTextarea v-model="form.internal_notes" rows="3" />
            </div>
            <div class="col-12">
              <BFormCheckbox v-model="form.active">Plantilla activa</BFormCheckbox>
            </div>
          </div>
        </div>
        <div class="col-xl-6">
          <BCard class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <h5 class="mb-0">Vista previa de la plantilla</h5>
                <div class="text-muted small">Se actualiza automáticamente al cambiar cuerpo o cláusulas.</div>
              </div>
              <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill badge-soft-primary">
                  {{ form.clause_ids.length }} cláusula<span v-if="form.clause_ids.length !== 1">s</span>
                </span>
                <BButton size="sm" variant="outline-primary" :disabled="previewing" @click="preview">
                  {{ previewing ? "Actualizando..." : "Vista previa" }}
                </BButton>
              </div>
            </div>
            <div v-if="syncingPreview" class="text-muted small mb-2">Actualizando vista previa en tiempo real...</div>
            <div v-if="templatePreviewContent" class="template-preview">{{ templatePreviewContent }}</div>
            <div v-else class="text-muted">
              Escribe el cuerpo base o selecciona cláusulas para ver aquí la composición final de la plantilla.
            </div>
          </BCard>
        </div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="light" @click="closeFormModal">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">
          {{ saving ? "Guardando..." : "Guardar" }}
        </BButton>
      </div>
    </BModal>
  </Layout>
</template>

<style scoped>
.template-preview {
  white-space: pre-wrap;
  background: rgba(85, 110, 230, 0.06);
  border: 1px solid rgba(85, 110, 230, 0.16);
  border-radius: 0.75rem;
  padding: 1rem;
  line-height: 1.7;
  min-height: 240px;
}
</style>
