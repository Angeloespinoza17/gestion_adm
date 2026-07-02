<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";
import Swal from "sweetalert2";

const emptyForm = () => ({
  id: null,
  title: "",
  clause_type: null,
  content: "",
  active: true,
  sort_order: 0,
  is_required: false,
  observations: "",
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
      clauses: [],
      catalogs: { clause_types: [], available_variables: {}, preview_variables: {} },
      filters: {
        search: "",
        clause_type: null,
        active: null,
      },
      showFormModal: false,
      form: emptyForm(),
      selectedVariable: null,
      clausePreviewContent: "",
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
      return this.permissions.includes("administrar_clausulas_contrato");
    },
    clauseTypeOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.clause_types || []).map((item) => ({ value: item, label: item }))
      );
    },
    activeOptions() {
      return [
        { value: null, label: "Todos" },
        { value: true, label: "Activas" },
        { value: false, label: "Inactivas" },
      ];
    },
    variableOptions() {
      return Object.entries(this.catalogs.available_variables || {}).flatMap(([group, items]) =>
        (items || []).map((item) => ({
          value: item,
          label: item,
          group,
        }))
      );
    },
    previewSource() {
      return JSON.stringify({
        title: this.form.title,
        content: this.form.content,
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
    this.loadClauses();
  },
  beforeUnmount() {
    if (this.autoPreviewTimer) {
      clearTimeout(this.autoPreviewTimer);
    }
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/contract-clauses/catalogs");
      this.catalogs = response.data;
      this.schedulePreview();
    },
    async loadClauses() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/contract-clauses", {
          params: this.filters,
        });
        this.clauses = response.data.data;
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    edit(item) {
      this.form = {
        id: item.id,
        title: item.title || "",
        clause_type: item.clause_type || null,
        content: item.content || "",
        active: Boolean(item.active),
        sort_order: item.sort_order ?? 0,
        is_required: Boolean(item.is_required),
        observations: item.observations || "",
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
      this.selectedVariable = null;
      this.clausePreviewContent = "";
      this.schedulePreview();
    },
    resolveTextareaElement() {
      const ref = this.$refs.clauseContentField;

      if (!ref) {
        return null;
      }

      if (ref.$el?.querySelector) {
        return ref.$el.querySelector("textarea");
      }

      if (ref.input) {
        return ref.input;
      }

      if (ref.$refs?.input) {
        return ref.$refs.input;
      }

      return ref instanceof HTMLTextAreaElement ? ref : null;
    },
    async insertVariable(token) {
      if (!token) {
        return;
      }

      const placeholder = `{{${token}}}`;
      const textarea = this.resolveTextareaElement();

      if (!textarea) {
        this.form.content = `${this.form.content || ""}${this.form.content ? " " : ""}${placeholder}`;
        return;
      }

      const start = textarea.selectionStart ?? textarea.value.length;
      const end = textarea.selectionEnd ?? textarea.value.length;
      const value = this.form.content || "";

      this.form.content = `${value.slice(0, start)}${placeholder}${value.slice(end)}`;

      await this.$nextTick();

      const nextPosition = start + placeholder.length;
      textarea.focus();
      textarea.setSelectionRange(nextPosition, nextPosition);
    },
    async insertSelectedVariable() {
      if (!this.selectedVariable) {
        await Swal.fire({
          title: "Selecciona una variable",
          text: "Debes elegir una variable antes de insertarla.",
          icon: "info",
          confirmButtonText: "Entendido",
        });
        return;
      }

      await this.insertVariable(this.selectedVariable);
    },
    canGeneratePreview() {
      return Boolean((this.form.title || "").trim() || (this.form.content || "").trim());
    },
    schedulePreview() {
      if (this.autoPreviewTimer) {
        clearTimeout(this.autoPreviewTimer);
      }

      if (!this.canGeneratePreview()) {
        this.clausePreviewContent = "";
        return;
      }

      this.autoPreviewTimer = setTimeout(() => {
        this.preview({ silent: true });
      }, 350);
    },
    async preview({ silent = false } = {}) {
      if (!this.canGeneratePreview()) {
        this.clausePreviewContent = "";
        return false;
      }

      this.previewing = !silent;
      this.syncingPreview = silent;

      try {
        const response = await axios.post("/api/contract-clauses/preview", {
          title: this.form.title,
          content: this.form.content,
        });
        this.clausePreviewContent = response.data.data.content || "";
        return true;
      } catch (error) {
        this.error = this.formatError(error);
        return false;
      } finally {
        this.previewing = false;
        this.syncingPreview = false;
      }
    },
    async triggerPreview() {
      if (!this.canGeneratePreview()) {
        await Swal.fire({
          title: "Completa la cláusula",
          text: "Ingresa al menos el título o el contenido para generar la vista previa.",
          icon: "info",
          confirmButtonText: "Entendido",
        });
        return;
      }

      const ok = await this.preview({ silent: false });

      if (!ok) {
        return;
      }

      await this.$nextTick();
      this.$refs.previewPanel?.scrollIntoView({ behavior: "smooth", block: "start" });
    },
    async save() {
      this.saving = true;
      this.error = null;
      try {
        if (this.form.id) {
          await axios.put(`/api/contract-clauses/${this.form.id}`, this.form);
        } else {
          await axios.post("/api/contract-clauses", this.form);
        }
        this.closeFormModal();
        this.resetForm();
        await this.loadClauses();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async toggle(item) {
      await axios.put(`/api/contract-clauses/${item.id}/active`, { active: !item.active });
      await this.loadClauses();
    },
    async remove(item) {
      const result = await Swal.fire({
        title: "Eliminar cláusula",
        text: "La cláusula será eliminada.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
      });
      if (!result.isConfirmed) return;
      await axios.delete(`/api/contract-clauses/${item.id}`);
      await this.loadClauses();
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
        <h4 class="mb-0">Cláusulas contractuales</h4>
        <div class="text-muted">Banco editable de cláusulas reutilizables para plantillas y contratos.</div>
      </div>
      <div class="mt-3 mt-sm-0">
        <BButton variant="primary" :disabled="!canManage" @click="openCreateForm">
          Nueva cláusula
        </BButton>
      </div>
    </div>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

    <div class="row g-4">
      <div class="col-12">
        <BCard class="mb-4">
          <div class="row g-3 align-items-end">
            <div class="col-md-5">
              <label class="form-label">Buscar</label>
              <BFormInput v-model="filters.search" @keyup.enter="loadClauses" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Tipo</label>
              <Multiselect v-model="filters.clause_type" :options="clauseTypeOptions" :searchable="true" />
            </div>
            <div class="col-md-2">
              <label class="form-label">Estado</label>
              <Multiselect v-model="filters.active" :options="activeOptions" />
            </div>
            <div class="col-md-2">
              <BButton variant="primary" class="w-100" @click="loadClauses">Filtrar</BButton>
            </div>
          </div>
        </BCard>

        <BCard no-body>
          <div class="table-responsive">
            <BTableSimple class="table align-middle table-nowrap mb-0">
              <BThead class="table-light">
                <BTr>
                  <BTh>Cláusula</BTh>
                  <BTh>Tipo</BTh>
                  <BTh>Estado</BTh>
                  <BTh>Orden</BTh>
                  <BTh class="text-end">Acciones</BTh>
                </BTr>
              </BThead>
              <BTbody>
                <BTr v-if="loading">
                  <BTd colspan="5" class="text-center py-4">
                    <LoadingState message="Cargando cláusulas..." compact />
                  </BTd>
                </BTr>
                <BTr v-else-if="clauses.length === 0">
                  <BTd colspan="5" class="text-center py-4 text-muted">Sin cláusulas registradas.</BTd>
                </BTr>
                <BTr v-for="item in clauses" :key="item.id">
                  <BTd>
                    <div class="fw-semibold">{{ item.title }}</div>
                    <small class="text-muted text-truncate d-inline-block" style="max-width: 420px;">{{ item.content }}</small>
                  </BTd>
                  <BTd>{{ item.clause_type || "-" }}</BTd>
                  <BTd>
                    <span :class="`badge rounded-pill badge-soft-${item.active ? 'success' : 'secondary'}`">
                      {{ item.active ? "Activa" : "Inactiva" }}
                    </span>
                  </BTd>
                  <BTd>{{ item.sort_order }}</BTd>
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

        <BCard class="mt-4">
          <h5 class="mb-3">Variables disponibles</h5>
          <div class="row g-3">
            <div v-for="(items, group) in catalogs.available_variables" :key="group" class="col-xl-4 col-md-6">
              <div class="border rounded p-3 h-100">
                <div class="fw-semibold text-capitalize mb-2">{{ group.replaceAll('_', ' ') }}</div>
                <div v-for="item in items" :key="item" class="small mb-2">
                  <code v-text="`{{${item}}}`"></code>
                  <div class="text-muted mt-1">{{ catalogs.preview_variables[item] || "Valor de ejemplo" }}</div>
                </div>
              </div>
            </div>
          </div>
        </BCard>
      </div>
    </div>

    <BModal
      v-model="showFormModal"
      size="xl"
      title-class="fw-semibold"
      :title="form.id ? 'Editar cláusula' : 'Nueva cláusula'"
      hide-footer
      scrollable
      @hidden="resetForm"
    >
      <div class="row g-4">
        <div class="col-xl-6">
          <div class="mb-3">
            <label class="form-label">Título</label>
            <BFormInput v-model="form.title" />
          </div>
          <div class="mb-3">
            <label class="form-label">Tipo</label>
            <BFormInput v-model="form.clause_type" />
          </div>
          <div class="mb-3">
            <label class="form-label">Contenido</label>
            <BFormTextarea ref="clauseContentField" v-model="form.content" rows="12" />
            <small class="text-muted">
              No escribas “Primero”, “Segundo”, etc. El sistema numera y rotula las cláusulas automáticamente según el orden.
            </small>
            <div class="small text-muted mt-2">
              Las variables se reemplazan en la vista previa con valores de ejemplo para que puedas revisar el resultado final.
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Agregador de variables</label>
            <div class="d-flex gap-2 mb-2">
              <div class="flex-grow-1">
                <Multiselect
                  v-model="selectedVariable"
                  :options="variableOptions"
                  value-prop="value"
                  label="label"
                  track-by="value"
                  :searchable="true"
                  placeholder="Selecciona una variable para insertar"
                />
              </div>
              <BButton variant="outline-primary" @click="insertSelectedVariable">
                Insertar
              </BButton>
            </div>
            <div class="border rounded p-2 bg-light-subtle">
              <div class="small text-muted mb-2">Inserción rápida</div>
              <div v-for="(items, group) in catalogs.available_variables" :key="group" class="mb-2">
                <div class="small fw-semibold text-capitalize mb-1">{{ group.replaceAll("_", " ") }}</div>
                <div class="d-flex flex-wrap gap-2">
                  <BButton
                    v-for="item in items"
                    :key="item"
                    size="sm"
                    variant="light"
                    class="variable-chip"
                    @click="insertVariable(item)"
                  >
                    <code v-text="`{{${item}}}`"></code>
                  </BButton>
                </div>
              </div>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Orden</label>
              <BFormInput v-model="form.sort_order" type="number" min="0" />
            </div>
            <div class="col-md-6 d-flex align-items-center">
              <div class="mt-4">
                <BFormCheckbox v-model="form.is_required">Uso obligatorio</BFormCheckbox>
                <BFormCheckbox v-model="form.active">Activa</BFormCheckbox>
              </div>
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label">Observaciones</label>
            <BFormTextarea v-model="form.observations" rows="3" />
          </div>
        </div>
        <div class="col-xl-6">
          <BCard ref="previewPanel" class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <h5 class="mb-0">Vista previa de la cláusula</h5>
                <div class="text-muted small">Se actualiza automáticamente y reemplaza variables por datos de ejemplo.</div>
              </div>
              <BButton size="sm" variant="outline-primary" :disabled="previewing" @click="triggerPreview">
                {{ previewing ? "Actualizando..." : "Vista previa" }}
              </BButton>
            </div>
            <div v-if="syncingPreview" class="text-muted small mb-2">Actualizando vista previa en tiempo real...</div>
            <div v-if="clausePreviewContent" class="clause-preview">{{ clausePreviewContent }}</div>
            <div v-else class="text-muted">
              Escribe el título o contenido de la cláusula para ver aquí el resultado con variables resueltas.
            </div>
          </BCard>
        </div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="light" @click="closeFormModal">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving || !canManage" @click="save">
          {{ saving ? "Guardando..." : "Guardar" }}
        </BButton>
      </div>
    </BModal>
  </Layout>
</template>

<style scoped>
.clause-preview {
  white-space: pre-wrap;
  background: rgba(85, 110, 230, 0.06);
  border: 1px solid rgba(85, 110, 230, 0.16);
  border-radius: 0.75rem;
  padding: 1rem;
  line-height: 1.7;
  min-height: 220px;
}

.variable-chip code {
  color: inherit;
}
</style>
