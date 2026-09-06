<script>
import axios from "axios";
import {
  confirmConvivenciaAction,
  formatConvivenciaDateTime,
  formatConvivenciaError,
  humanizeConvivenciaStatus,
  showConvivenciaError,
  showConvivenciaSuccess,
} from "../module-utils";
import { downloadConvivenciaProtocolPdf } from "../pdf/convivencia-protocol-pdf";
import ProtocolActivationWorkspace from "./protocol-activation-workspace.vue";
import ProtocolEditor from "./protocol-editor.vue";
import ProtocolPartLibrary from "./protocol-part-library.vue";
import ConvivenciaDataTable from "../ui/convivencia-data-table.vue";
import ConvivenciaFormModal from "../ui/convivencia-form-modal.vue";
import ConvivenciaRowActions from "../ui/convivencia-row-actions.vue";
import ConvivenciaRemoteSelect from "../ui/convivencia-remote-select.vue";

const listFrom = (payload) => payload?.data?.data || payload?.data || payload?.items || [];
const recordFrom = (payload) => payload?.data?.data || payload?.data || payload || {};
const paginatorFrom = (payload, fallbackPage = 1) => {
  const source = payload?.data?.data ? payload.data : payload || {};
  return {
    current_page: Number(source.current_page || fallbackPage),
    last_page: Number(source.last_page || 1),
    total: Number(source.total ?? listFrom(payload).length),
  };
};
const blankActivation = () => ({ protocol_id: null, case_id: null, complaint_id: null, status: "activo", actions_taken: "", measures_adopted: "", log_notes: "" });

export default {
  components: { ProtocolActivationWorkspace, ProtocolEditor, ProtocolPartLibrary, ConvivenciaDataTable, ConvivenciaFormModal, ConvivenciaRowActions, ConvivenciaRemoteSelect },
  props: {
    catalogs: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      section: "protocols",
      loadingProtocols: false,
      loadingParts: false,
      loadingActivations: false,
      protocols: [],
      parts: [],
      editorParts: [],
      activations: [],
      protocolPagination: { current_page: 1, last_page: 1, total: 0 },
      partPagination: { current_page: 1, last_page: 1, total: 0 },
      activationPagination: { current_page: 1, last_page: 1, total: 0 },
      partQuery: { page: 1, search: "", category: "" },
      protocolSearch: "",
      protocolStatus: "",
      activationSearch: "",
      editorOpen: false,
      editorProtocol: null,
      editorLoading: false,
      savingProtocol: false,
      exportingId: null,
      deletingId: null,
      activationDialog: false,
      activationForm: blankActivation(),
      activating: false,
      workspaceActivationId: null,
    };
  },
  computed: {
    capabilities() {
      return this.catalogs?.capabilities || {};
    },
    canManage() {
      return this.capabilities.can_manage_protocols === true;
    },
    canActivate() {
      return this.capabilities.can_activate_protocols === true;
    },
    canExport() {
      return this.capabilities.can_export_reports === true;
    },
    filteredProtocols() {
      return this.protocols;
    },
    filteredActivations() {
      return this.activations;
    },
    liveActivations() {
      return this.activations.filter((activation) => !["cerrado", "completed", "finalizado"].includes(activation.status)).length;
    },
    currentCompliance() {
      const percentages = this.activations.map((activation) => Number(activation.progress?.percentage ?? activation.progress_percentage)).filter(Number.isFinite);
      return percentages.length ? Math.round(percentages.reduce((sum, value) => sum + value, 0) / percentages.length) : 0;
    },
    statusOptions() {
      const configured = this.catalogs?.protocol_status_options || [];
      return configured.length ? configured : ["borrador", "activo", "inactivo"];
    },
    editorAvailableParts() {
      const records = new Map();
      const add = (part) => { if (part?.id) records.set(Number(part.id), part); };
      this.editorParts.forEach(add);
      this.parts.forEach(add);
      (this.editorProtocol?.part_links || []).forEach((link) => add(link.part));
      (this.editorProtocol?.steps || []).forEach((step) => (step.part_links || []).forEach((link) => add(link.part)));
      return [...records.values()];
    },
  },
  mounted() {
    this.refreshAll();
  },
  methods: {
    async refreshAll() {
      await Promise.allSettled([this.loadProtocols(), this.loadParts(), this.loadEditorParts(), this.loadActivations()]);
    },
    async loadProtocols(page = 1) {
      this.loadingProtocols = true;
      try {
        const response = await axios.get("/api/convivencia/protocols", { params: { per_page: 15, page, search: this.protocolSearch || undefined, status: this.protocolStatus || undefined } });
        this.protocols = listFrom(response.data);
        this.protocolPagination = paginatorFrom(response.data, page);
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error, "No se pudieron cargar los protocolos."));
      } finally {
        this.loadingProtocols = false;
      }
    },
    async loadParts(query = {}) {
      const nextQuery = typeof query === "number" ? { ...this.partQuery, page: query } : { ...this.partQuery, ...query };
      nextQuery.page = Number(nextQuery.page || 1);
      this.partQuery = nextQuery;
      this.loadingParts = true;
      try {
        const response = await axios.get("/api/convivencia/protocol-parts", { params: { per_page: 20, page: nextQuery.page, search: nextQuery.search || undefined, category: nextQuery.category || undefined } });
        this.parts = listFrom(response.data);
        this.partPagination = paginatorFrom(response.data, nextQuery.page);
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error, "No se pudo cargar la biblioteca de partes."));
      } finally {
        this.loadingParts = false;
      }
    },
    async loadEditorParts() {
      try {
        const response = await axios.get("/api/convivencia/protocol-parts", { params: { per_page: 20, page: 1, active: 1 } });
        this.editorParts = listFrom(response.data);
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error, "No se pudieron cargar las partes disponibles para vincular."));
      }
    },
    async loadActivations(page = 1) {
      this.loadingActivations = true;
      try {
        const response = await axios.get("/api/convivencia/protocol-activations", { params: { per_page: 15, page, search: this.activationSearch || undefined } });
        this.activations = listFrom(response.data);
        this.activationPagination = paginatorFrom(response.data, page);
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error, "No se pudieron cargar las activaciones."));
      } finally {
        this.loadingActivations = false;
      }
    },
    warnings(protocol) {
      const raw = protocol?.metadata?.warnings;
      if (Array.isArray(raw)) return raw.filter(Boolean);
      return raw ? [raw] : [];
    },
    needsReview(protocol) {
      return Boolean(protocol?.metadata?.review_required || this.warnings(protocol).length);
    },
    statusLabel(value) {
      return humanizeConvivenciaStatus(value);
    },
    statusValue(option) {
      return option?.value ?? option?.id ?? option;
    },
    statusText(option) {
      return option?.label ?? option?.name ?? humanizeConvivenciaStatus(this.statusValue(option));
    },
    stepCount(protocol) {
      return Number(protocol.steps_count ?? protocol.steps?.length ?? 0);
    },
    activationCount(protocol) {
      return Number(protocol.activations_count ?? protocol.activations?.length ?? 0);
    },
    formatDate(value) {
      return formatConvivenciaDateTime(value);
    },
    activationReference(activation) {
      return activation.case?.folio || activation.complaint?.folio || `Activación #${activation.id}`;
    },
    async fetchProtocol(id) {
      const response = await axios.get(`/api/convivencia/protocols/${id}`);
      return recordFrom(response.data);
    },
    isRevisionConflict(error) {
      const status = error?.response?.status;
      const errors = error?.response?.data?.errors || {};
      return status === 409 || Boolean(errors.revision || errors.expected_revision);
    },
    openCreate() {
      if (!this.editorParts.length) this.loadEditorParts();
      this.editorProtocol = null;
      this.editorOpen = true;
      this.section = "protocols";
    },
    async openEdit(protocol) {
      this.editorLoading = true;
      this.section = "protocols";
      try {
        const [complete] = await Promise.all([this.fetchProtocol(protocol.id), this.editorParts.length ? Promise.resolve() : this.loadEditorParts()]);
        this.editorProtocol = complete;
        this.editorOpen = true;
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error, "No se pudo abrir la versión completa del protocolo."));
      } finally {
        this.editorLoading = false;
      }
    },
    closeEditor() {
      this.editorOpen = false;
      this.editorProtocol = null;
    },
    async saveProtocol(payload) {
      if (!this.canManage) return;
      this.savingProtocol = true;
      try {
        if (this.editorProtocol?.id) await axios.put(`/api/convivencia/protocols/${this.editorProtocol.id}`, payload);
        else await axios.post("/api/convivencia/protocols", payload);
        await showConvivenciaSuccess(this.editorProtocol?.id ? "Protocolo actualizado correctamente." : "Protocolo creado correctamente.");
        this.closeEditor();
        await this.loadProtocols(this.protocolPagination.current_page);
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error, "No se pudo guardar el protocolo."));
        if (this.editorProtocol?.id && this.isRevisionConflict(error)) {
          try { this.editorProtocol = await this.fetchProtocol(this.editorProtocol.id); } catch { /* El mensaje original conserva el contexto. */ }
        }
      } finally {
        this.savingProtocol = false;
      }
    },
    async removeProtocol(protocol) {
      const confirmation = await confirmConvivenciaAction({
        title: "Archivar protocolo",
        text: "Se conservarán sus activaciones y versiones históricas, pero dejará de estar disponible para nuevas activaciones.",
        confirmButtonText: "Archivar",
      });
      if (!confirmation.isConfirmed) return;
      this.deletingId = protocol.id;
      try {
        await axios.delete(`/api/convivencia/protocols/${protocol.id}`);
        await showConvivenciaSuccess("Protocolo archivado correctamente.");
        await this.loadProtocols(this.protocolPagination.current_page);
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error, "No se pudo archivar el protocolo."));
      } finally {
        this.deletingId = null;
      }
    },
    async exportProtocol(protocol) {
      if (this.exportingId) return;
      this.exportingId = protocol.id;
      try {
        const complete = await this.fetchProtocol(protocol.id);
        await downloadConvivenciaProtocolPdf({ data: complete });
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error, "No se pudo generar el PDF del protocolo."));
      } finally {
        this.exportingId = null;
      }
    },
    openActivation(protocol = null) {
      this.activationForm = blankActivation();
      this.activationForm.protocol_id = protocol?.id || null;
      this.activationDialog = true;
    },
    closeActivationDialog() {
      if (this.activating) return;
      this.activationDialog = false;
    },
    clearOppositeReference(type) {
      if (type === "case") this.activationForm.complaint_id = null;
      if (type === "complaint") this.activationForm.case_id = null;
    },
    async activateProtocol() {
      if (!this.activationForm.protocol_id || (!this.activationForm.case_id && !this.activationForm.complaint_id)) {
        showConvivenciaError("Selecciona un protocolo y asócialo a un caso o una denuncia.", "Falta información");
        return;
      }
      this.activating = true;
      try {
        const response = await axios.post("/api/convivencia/protocol-activations", {
          ...this.activationForm,
          case_id: this.activationForm.case_id || null,
          complaint_id: this.activationForm.complaint_id || null,
          actions_taken: this.activationForm.actions_taken || null,
          measures_adopted: this.activationForm.measures_adopted || null,
          log_notes: this.activationForm.log_notes || null,
        });
        const activation = recordFrom(response.data);
        this.activationDialog = false;
        await showConvivenciaSuccess("Protocolo activado. Ya puedes gestionar su primera etapa.");
        await this.loadActivations();
        this.workspaceActivationId = activation.id || null;
        this.section = "activations";
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error, "No se pudo activar el protocolo."));
      } finally {
        this.activating = false;
      }
    },
    openWorkspace(activation) {
      this.workspaceActivationId = activation.id;
    },
    onWorkspaceChanged() {
      this.loadActivations();
    },
    async onPartsChanged() {
      await Promise.all([this.loadParts(this.partPagination.current_page), this.loadEditorParts()]);
    },
    protocolActions(protocol) {
      return [
        { key: "activate", label: "Activar", icon: "bx-play", tone: "success", visible: this.canActivate },
        { key: "pdf", label: "Exportar PDF", icon: "bx-file", visible: this.canExport, disabled: this.exportingId === protocol.id },
        { key: "edit", label: "Editar", icon: "bx-edit-alt", visible: this.canManage },
        { key: "archive", label: "Archivar", icon: "bx-archive-in", tone: "danger", visible: this.canManage, disabled: this.deletingId === protocol.id },
      ];
    },
    handleProtocolAction(action, protocol) {
      if (action === "activate") return this.openActivation(protocol);
      if (action === "pdf") return this.exportProtocol(protocol);
      if (action === "edit") return this.openEdit(protocol);
      if (action === "archive") return this.removeProtocol(protocol);
      return undefined;
    },
  },
};
</script>

<template>
  <section class="protocol-manager" aria-labelledby="protocol-manager-title">
    <header class="protocol-manager__hero">
      <div class="protocol-manager__hero-copy">
        <span class="protocol-manager__shield"><i class="bx bx-shield-quarter" aria-hidden="true"></i></span>
        <div><span>Reglamento interno 2026</span><h2 id="protocol-manager-title">Centro de protocolos</h2><p>Diseña rutas editables, activa casos y controla cada paso con sus medidas, documentos, responsables y plazos.</p></div>
      </div>
      <div class="protocol-manager__hero-actions">
        <button v-if="canActivate" type="button" class="btn btn-light" @click="openActivation()"><i class="bx bx-play-circle" aria-hidden="true"></i>Activar protocolo</button>
        <button v-if="canManage" type="button" class="btn btn-primary" @click="openCreate"><i class="bx bx-plus" aria-hidden="true"></i>Nuevo protocolo</button>
      </div>
    </header>

    <div class="protocol-manager__summary" aria-label="Resumen de protocolos">
      <article><span><i class="bx bx-library" aria-hidden="true"></i></span><div><b>{{ protocolPagination.total }}</b><small>protocolos configurados</small></div></article>
      <article><span><i class="bx bx-git-branch" aria-hidden="true"></i></span><div><b>{{ liveActivations }}</b><small>en curso en esta página</small></div></article>
      <article><span><i class="bx bx-layer" aria-hidden="true"></i></span><div><b>{{ partPagination.total }}</b><small>partes reutilizables</small></div></article>
      <article><span><i class="bx bx-trending-up" aria-hidden="true"></i></span><div><b>{{ currentCompliance }}%</b><small>avance promedio de esta página</small></div></article>
    </div>

    <nav class="protocol-manager__nav" aria-label="Áreas del centro de protocolos">
      <button type="button" :class="{ active: section === 'protocols' }" @click="section = 'protocols'"><i class="bx bx-map-alt" aria-hidden="true"></i><span>Definiciones</span><b>{{ protocolPagination.total }}</b></button>
      <button type="button" :class="{ active: section === 'activations' }" @click="section = 'activations'"><i class="bx bx-play-circle" aria-hidden="true"></i><span>Activaciones</span><b>{{ activationPagination.total }}</b></button>
      <button type="button" :class="{ active: section === 'parts' }" @click="section = 'parts'"><i class="bx bx-layer" aria-hidden="true"></i><span>Biblioteca de partes</span><b>{{ partPagination.total }}</b></button>
    </nav>

    <div v-if="editorLoading" class="protocol-manager__loading"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Abriendo versión completa…</div>

    <template v-if="section === 'protocols'">
      <div class="protocol-manager__toolbar">
        <div class="input-group"><span class="input-group-text"><i class="bx bx-search" aria-hidden="true"></i></span><input v-model="protocolSearch" type="search" class="form-control" aria-label="Buscar protocolos" placeholder="Buscar por nombre, código o referencia" @keyup.enter="loadProtocols(1)" /></div>
        <select v-model="protocolStatus" class="form-select" aria-label="Filtrar protocolos por estado" @change="loadProtocols(1)"><option value="">Todos los estados</option><option v-for="option in statusOptions" :key="statusValue(option)" :value="statusValue(option)">{{ statusText(option) }}</option></select>
        <button type="button" class="btn btn-outline-secondary" :disabled="loadingProtocols" @click="loadProtocols(1)"><i class="bx bx-search" :class="{ 'bx-spin': loadingProtocols }" aria-hidden="true"></i>Aplicar</button>
      </div>

      <ConvivenciaDataTable title="Definiciones de protocolo" subtitle="Versiones reglamentarias, etapas y disponibilidad para activación." icon="bx-map-alt" :count="filteredProtocols.length" :loading="loadingProtocols" :empty="filteredProtocols.length === 0" empty-title="No hay protocolos para esta búsqueda" empty-text="Modifica los filtros o crea una nueva definición." min-width="980px">
        <template #empty-action><button v-if="canManage" type="button" class="btn btn-sm btn-primary mt-2" @click="openCreate"><i class="bx bx-plus me-1"></i>Nuevo protocolo</button></template>
        <table class="table align-middle">
          <thead><tr><th>Protocolo</th><th>Versión</th><th>Estado</th><th>Etapas</th><th>Activaciones</th><th>Plazo base</th><th>Control normativo</th><th class="text-end">Acciones</th></tr></thead>
          <tbody><tr v-for="protocol in filteredProtocols" :key="protocol.id" :class="{ 'protocol-manager__review-row': needsReview(protocol) }"><td data-label="Protocolo"><b class="protocol-manager__table-title">{{ protocol.name }}</b><small>{{ protocol.code || "Sin código" }} · {{ protocol.description || "Sin descripción" }}</small></td><td data-label="Versión">{{ protocol.version_label || "-" }}</td><td data-label="Estado"><span class="protocol-card__status" :class="`is-${protocol.status}`">{{ statusLabel(protocol.status) }}</span></td><td data-label="Etapas">{{ stepCount(protocol) }}</td><td data-label="Activaciones">{{ activationCount(protocol) }}</td><td data-label="Plazo base">{{ protocol.default_due_days ? `${protocol.default_due_days} días` : "Por etapa" }}</td><td data-label="Control normativo"><span v-if="needsReview(protocol)" class="protocol-manager__review" :title="warnings(protocol).join(' · ')"><i class="bx bx-error"></i>Revisión requerida</span><span v-else class="protocol-manager__ok"><i class="bx bx-check-circle"></i>Sin alertas</span></td><td data-label="Acciones"><ConvivenciaRowActions :actions="protocolActions(protocol)" :item-label="protocol.name" @select="handleProtocolAction($event, protocol)" /></td></tr></tbody>
        </table>
        <template v-if="protocolPagination.last_page > 1" #footer><div class="protocol-manager__pager"><button type="button" class="btn btn-sm btn-outline-secondary" :disabled="loadingProtocols || protocolPagination.current_page <= 1" @click="loadProtocols(protocolPagination.current_page - 1)"><i class="bx bx-chevron-left"></i>Anterior</button><span>Página {{ protocolPagination.current_page }} de {{ protocolPagination.last_page }}</span><button type="button" class="btn btn-sm btn-outline-secondary" :disabled="loadingProtocols || protocolPagination.current_page >= protocolPagination.last_page" @click="loadProtocols(protocolPagination.current_page + 1)">Siguiente<i class="bx bx-chevron-right"></i></button></div></template>
      </ConvivenciaDataTable>
    </template>

    <template v-else-if="section === 'activations'">
      <div class="protocol-manager__toolbar is-activations">
        <div class="input-group"><span class="input-group-text"><i class="bx bx-search" aria-hidden="true"></i></span><input v-model="activationSearch" type="search" class="form-control" aria-label="Buscar activaciones" placeholder="Buscar por protocolo, folio o etapa" @keyup.enter="loadActivations(1)" /></div>
        <button type="button" class="btn btn-outline-secondary" :disabled="loadingActivations" @click="loadActivations(1)"><i class="bx bx-refresh" :class="{ 'bx-spin': loadingActivations }" aria-hidden="true"></i>Actualizar</button>
        <button v-if="canActivate" type="button" class="btn btn-primary" @click="openActivation()"><i class="bx bx-plus" aria-hidden="true"></i>Nueva activación</button>
      </div>
      <ConvivenciaDataTable title="Activaciones y avance" subtitle="Seguimiento operativo de cada ruta protocolar activa o histórica." icon="bx-git-branch" :count="activationPagination.total" :loading="loadingActivations" :empty="filteredActivations.length === 0" empty-title="No hay activaciones visibles" empty-text="Activa un protocolo desde un caso o una denuncia." min-width="920px">
        <template #empty-action><button v-if="canActivate" type="button" class="btn btn-sm btn-primary mt-2" @click="openActivation()"><i class="bx bx-plus me-1"></i>Nueva activación</button></template>
        <table class="table align-middle"><thead><tr><th>Expediente</th><th>Protocolo</th><th>Paso actual</th><th>Estado</th><th>Avance</th><th>Fecha de inicio</th><th class="text-end">Acciones</th></tr></thead><tbody><tr v-for="activation in filteredActivations" :key="activation.id"><td data-label="Expediente"><b class="protocol-manager__table-title">{{ activationReference(activation) }}</b></td><td data-label="Protocolo">{{ activation.protocol?.name || activation.protocol_name }}</td><td data-label="Paso actual">{{ activation.progress?.label || activation.current_stage_name || "Ruta pendiente de inicio" }}</td><td data-label="Estado"><span class="protocol-card__status" :class="`is-${activation.status}`">{{ statusLabel(activation.status) }}</span></td><td data-label="Avance"><span class="protocol-manager__progress"><i :style="{ width: `${Number(activation.progress?.percentage ?? activation.progress_percentage ?? 0)}%` }"></i><b>{{ Number(activation.progress?.percentage ?? activation.progress_percentage ?? 0) }}%</b></span></td><td data-label="Fecha de inicio">{{ formatDate(activation.activated_at || activation.created_at) }}</td><td data-label="Acciones"><ConvivenciaRowActions :actions="[{ key: 'manage', label: canActivate ? 'Gestionar' : 'Ver', icon: canActivate ? 'bx-edit-alt' : 'bx-show' }]" :item-label="activationReference(activation)" @select="openWorkspace(activation)" /></td></tr></tbody></table>
        <template v-if="activationPagination.last_page > 1" #footer><div class="protocol-manager__pager"><button type="button" class="btn btn-sm btn-outline-secondary" :disabled="loadingActivations || activationPagination.current_page <= 1" @click="loadActivations(activationPagination.current_page - 1)"><i class="bx bx-chevron-left"></i>Anterior</button><span>Página {{ activationPagination.current_page }} de {{ activationPagination.last_page }}</span><button type="button" class="btn btn-sm btn-outline-secondary" :disabled="loadingActivations || activationPagination.current_page >= activationPagination.last_page" @click="loadActivations(activationPagination.current_page + 1)">Siguiente<i class="bx bx-chevron-right"></i></button></div></template>
      </ConvivenciaDataTable>
    </template>

    <ProtocolPartLibrary v-else :parts="parts" :loading="loadingParts" :pagination="partPagination" :capabilities="capabilities" @query="loadParts" @changed="onPartsChanged" />

    <ConvivenciaFormModal :model-value="editorOpen" :title="editorProtocol?.id ? 'Editar protocolo' : 'Nuevo protocolo'" eyebrow="Definición reglamentaria" description="Configura identificación, alcance, etapas, plazos y partes vinculadas sin perder la trazabilidad RICE." icon="bx-map-alt" size="xxl" :busy="savingProtocol" hide-footer formless @update:model-value="(open) => { if (!open) closeEditor() }">
      <ProtocolEditor v-if="editorOpen" :protocol="editorProtocol" :parts="editorAvailableParts" :catalogs="catalogs" :saving="savingProtocol" @save="saveProtocol" @cancel="closeEditor" />
    </ConvivenciaFormModal>

    <ConvivenciaFormModal :model-value="activationDialog" title="Activar protocolo" eyebrow="Inicio guiado" description="La ruta creará sus etapas, plazos y requisitos como una fotografía histórica editable." icon="bx-play-circle" size="lg" :busy="activating" submit-label="Crear activación y abrir" @update:model-value="(open) => { if (!open) closeActivationDialog() }" @submit="activateProtocol">
      <div class="protocol-manager__activation-form">
        <div><label class="form-label">Protocolo</label><ConvivenciaRemoteSelect v-model="activationForm.protocol_id" type="protocols" placeholder="Buscar protocolo activo" aria-label="Buscar protocolo para activar" required /></div>
        <fieldset>
          <legend>Asociar a un expediente</legend>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Caso</label><ConvivenciaRemoteSelect v-model="activationForm.case_id" type="cases" placeholder="Buscar caso por folio" aria-label="Buscar caso para activar el protocolo" @change="clearOppositeReference('case')" /></div>
            <div class="col-md-6"><label class="form-label">Denuncia</label><ConvivenciaRemoteSelect v-model="activationForm.complaint_id" type="complaints" placeholder="Buscar denuncia por folio" aria-label="Buscar denuncia para activar el protocolo" @change="clearOppositeReference('complaint')" /></div>
          </div>
        </fieldset>
        <div><label class="form-label" for="activation-actions">Acciones iniciales</label><textarea id="activation-actions" v-model="activationForm.actions_taken" rows="2" class="form-control" placeholder="Acciones de resguardo o gestión ya realizadas."></textarea></div>
        <div><label class="form-label" for="activation-measures">Medidas adoptadas</label><textarea id="activation-measures" v-model="activationForm.measures_adopted" rows="2" class="form-control" placeholder="Medidas protectoras, formativas u otras ya aplicadas."></textarea></div>
        <div><label class="form-label" for="activation-log">Nota de trazabilidad</label><textarea id="activation-log" v-model="activationForm.log_notes" rows="2" class="form-control" placeholder="Motivo o antecedente de la activación."></textarea></div>
      </div>
    </ConvivenciaFormModal>

    <ProtocolActivationWorkspace v-if="workspaceActivationId" :activation-id="workspaceActivationId" :capabilities="capabilities" @close="workspaceActivationId = null" @changed="onWorkspaceChanged" />
  </section>
</template>

<style scoped>
.protocol-manager{--pm-navy:#202e6d;--pm-indigo:#5064d9;--pm-teal:#2a8b77;--pm-line:#dde3ed;color:#34415a}.protocol-manager__hero{display:flex;align-items:center;justify-content:space-between;gap:1.2rem;padding:1.3rem 1.45rem;color:#fff;border-radius:22px;background:radial-gradient(circle at 92% 20%,rgba(71,206,175,.3),transparent 28%),linear-gradient(120deg,#202d6c,#4b5fcf 68%,#287f72);box-shadow:0 17px 38px rgba(30,45,108,.2)}.protocol-manager__hero-copy{display:flex;min-width:0;align-items:center;gap:1rem}.protocol-manager__shield{display:grid;width:58px;height:58px;flex:0 0 58px;font-size:1.7rem;place-items:center;border:1px solid rgba(255,255,255,.3);border-radius:18px;background:rgba(255,255,255,.13)}.protocol-manager__hero-copy>div>span{font-size:.63rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;opacity:.75}.protocol-manager__hero h2{margin:.18rem 0;font-size:1.25rem;font-weight:800}.protocol-manager__hero p{max-width:720px;margin:0;font-size:.75rem;line-height:1.5;opacity:.82}.protocol-manager__hero-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:.55rem}.protocol-manager__hero-actions .btn{display:inline-flex;align-items:center;gap:.35rem;white-space:nowrap}.protocol-manager__hero-actions .btn-primary{border-color:#162463;background:#162463}.protocol-manager__summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem;margin:1rem 0}.protocol-manager__summary article{display:flex;align-items:center;gap:.65rem;padding:.85rem;border:1px solid var(--pm-line);border-radius:16px;background:#fff;box-shadow:0 9px 23px rgba(37,48,80,.05)}.protocol-manager__summary article>span{display:grid;width:39px;height:39px;flex:0 0 39px;color:var(--pm-indigo);font-size:1.1rem;place-items:center;border-radius:12px;background:#edf0ff}.protocol-manager__summary b{display:block;color:var(--pm-navy);font-size:1rem}.protocol-manager__summary small{display:block;color:#7c879a;font-size:.64rem}.protocol-manager__nav{display:flex;gap:.35rem;padding:.35rem;margin-bottom:1rem;border:1px solid var(--pm-line);border-radius:15px;background:#fff}.protocol-manager__nav button{display:flex;min-width:0;flex:1;align-items:center;justify-content:center;gap:.42rem;padding:.62rem .8rem;color:#68758b;font-size:.7rem;font-weight:750;border:0;border-radius:11px;background:transparent}.protocol-manager__nav button:hover,.protocol-manager__nav button.active{color:var(--pm-indigo);background:#eef1ff}.protocol-manager__nav b{padding:.13rem .38rem;color:inherit;font-size:.58rem;border-radius:999px;background:rgba(80,100,217,.1)}.protocol-manager__toolbar{display:grid;grid-template-columns:minmax(260px,1fr) 220px auto;gap:.65rem;margin-bottom:.9rem}.protocol-manager__toolbar.is-activations{grid-template-columns:minmax(260px,1fr) auto auto}.protocol-manager__toolbar .form-control,.protocol-manager__toolbar .form-select,.protocol-manager__toolbar .input-group-text{font-size:.73rem;border-color:#dbe1eb}.protocol-manager__toolbar .btn{display:inline-flex;align-items:center;justify-content:center;gap:.35rem}.protocol-manager__cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.85rem}.protocol-card{position:relative;overflow:hidden;padding:1rem;border:1px solid var(--pm-line);border-radius:18px;background:#fff;box-shadow:0 11px 27px rgba(33,45,79,.06)}.protocol-card__stripe{position:absolute;top:0;right:0;left:0;height:4px;background:linear-gradient(90deg,var(--pm-indigo),var(--pm-teal))}.protocol-card.needs-review .protocol-card__stripe{background:linear-gradient(90deg,#d38a26,#c34f52)}.protocol-card header{display:flex;align-items:flex-start;justify-content:space-between;gap:.8rem}.protocol-card header>div>span{color:#718096;font-size:.58rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase}.protocol-card h3{margin:.2rem 0;color:var(--pm-navy);font-size:.9rem;font-weight:800;line-height:1.3}.protocol-card>p{min-height:2.2rem;margin:.55rem 0;color:#748096;font-size:.69rem;line-height:1.55}.protocol-card__status{padding:.35rem .55rem;color:#59667d;font-size:.57rem;font-weight:800;border-radius:999px;background:#edf0f4;white-space:nowrap}.protocol-card__status.is-vigente,.protocol-card__status.is-activo{color:#1f715f;background:#e4f4ef}.protocol-card__status.is-borrador{color:#805c1c;background:#fff2da}.protocol-card__warning{display:flex;gap:.5rem;padding:.65rem;margin:.65rem 0;color:#8b4f18;border:1px solid #efd0a2;border-radius:12px;background:#fff9ee}.protocol-card__warning>i{flex:0 0 auto;font-size:1.05rem}.protocol-card__warning b{display:block;font-size:.65rem}.protocol-card__warning ul{padding-left:1rem;margin:.3rem 0 0;font-size:.62rem}.protocol-card__warning small{font-size:.62rem}.protocol-card dl{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.4rem;padding:.65rem 0;margin:0;border-top:1px solid #edf0f4;border-bottom:1px solid #edf0f4}.protocol-card dl>div{min-width:0;text-align:center}.protocol-card dt{display:flex;align-items:center;justify-content:center;gap:.2rem;color:#8a94a5;font-size:.55rem;font-weight:650}.protocol-card dd{margin:.18rem 0 0;color:#40506b;font-size:.7rem;font-weight:800}.protocol-card footer{display:flex;justify-content:flex-end;gap:.4rem;padding-top:.7rem}.protocol-card footer .btn{display:inline-flex;align-items:center;gap:.25rem}.protocol-manager__loading{display:flex;align-items:center;justify-content:center;gap:.5rem;padding:1rem;margin-bottom:.8rem;color:#6c7890;border:1px solid var(--pm-line);border-radius:14px;background:#fff}.protocol-manager__editor{margin-bottom:1rem}.protocol-manager__empty{display:grid;gap:.4rem;min-height:210px;padding:2rem;color:#7d899d;text-align:center;place-content:center;border:1px dashed #cbd3e0;border-radius:18px;background:#fafbfe}.protocol-manager__empty>i{color:#9aa5b9;font-size:2rem}.protocol-manager__empty b{color:#526078;font-size:.8rem}.protocol-manager__empty p{margin:0;font-size:.68rem}.activation-list{display:grid;gap:.65rem}.activation-row{display:grid;grid-template-columns:43px minmax(0,1fr) minmax(150px,230px) auto;align-items:center;gap:.75rem;padding:.8rem;border:1px solid var(--pm-line);border-radius:15px;background:#fff}.activation-row__icon{display:grid;width:43px;height:43px;color:var(--pm-indigo);font-size:1.15rem;place-items:center;border-radius:13px;background:#edf0ff}.activation-row__main{min-width:0}.activation-row__main>span{color:#808b9e;font-size:.59rem;font-weight:700;text-transform:uppercase}.activation-row__main h3{overflow:hidden;margin:.13rem 0;color:var(--pm-navy);font-size:.78rem;font-weight:800;text-overflow:ellipsis;white-space:nowrap}.activation-row__main p{display:flex;align-items:center;gap:.25rem;margin:0;color:#6d7990;font-size:.65rem}.activation-row__progress>span{display:flex;align-items:center;justify-content:space-between;color:#6d7990;font-size:.58rem}.activation-row__progress b{color:var(--pm-indigo);font-size:.68rem}.activation-row__progress>div{height:6px;margin-top:.3rem;overflow:hidden;border-radius:99px;background:#e8ebf2}.activation-row__progress>div i{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--pm-indigo),var(--pm-teal))}.activation-row>.btn{display:inline-flex;align-items:center;gap:.3rem}.protocol-manager__dialog{position:fixed;z-index:1065;display:grid;padding:1rem;inset:0;place-items:center;background:rgba(14,22,45,.66);backdrop-filter:blur(7px)}.protocol-manager__dialog-card{width:min(680px,100%);max-height:calc(100vh - 2rem);overflow:auto;border-radius:22px;background:#fff;box-shadow:0 25px 75px rgba(8,15,39,.35)}.protocol-manager__dialog-card>header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1.1rem 1.2rem;color:#fff;background:linear-gradient(120deg,#202e6d,#5064d9)}.protocol-manager__dialog-card>header span{font-size:.59rem;font-weight:800;letter-spacing:.09em;text-transform:uppercase;opacity:.75}.protocol-manager__dialog-card h3{margin:.18rem 0;font-size:1.05rem;font-weight:800}.protocol-manager__dialog-card header p{margin:0;font-size:.68rem;opacity:.82}.protocol-manager__dialog-card header button{display:grid;width:35px;height:35px;flex:0 0 35px;padding:0;color:#fff;font-size:1.35rem;place-items:center;border:1px solid rgba(255,255,255,.3);border-radius:11px;background:rgba(255,255,255,.1)}.protocol-manager__dialog-body{display:grid;gap:.85rem;padding:1rem 1.2rem}.protocol-manager__dialog-body fieldset{padding:.8rem;border:1px solid #e1e6ee;border-radius:14px;background:#fafbfe}.protocol-manager__dialog-body legend{float:none;width:auto;padding:0 .3rem;margin:0;color:#58667f;font-size:.68rem;font-weight:750}.protocol-manager__dialog-body .form-label{color:#5b6980;font-size:.67rem;font-weight:700}.protocol-manager__dialog-body .form-control,.protocol-manager__dialog-body .form-select{font-size:.73rem;border-color:#dbe1eb}.protocol-manager__dialog-card>footer{display:flex;justify-content:flex-end;gap:.5rem;padding:.9rem 1.2rem;border-top:1px solid #e7eaf0;background:#fafbfe}.protocol-manager__dialog-card>footer .btn{display:inline-flex;align-items:center;gap:.35rem}
@media(max-width:991.98px){.protocol-manager__summary{grid-template-columns:repeat(2,minmax(0,1fr))}.protocol-manager__cards{grid-template-columns:1fr}.activation-row{grid-template-columns:43px minmax(0,1fr) auto}.activation-row__progress{grid-column:2}.activation-row>.btn{grid-row:1/3;grid-column:3}}
@media(max-width:767.98px){.protocol-manager__hero{align-items:flex-start;flex-direction:column;padding:1rem;border-radius:18px}.protocol-manager__hero-actions{width:100%;justify-content:stretch}.protocol-manager__hero-actions .btn{flex:1;justify-content:center}.protocol-manager__shield{width:48px;height:48px;flex-basis:48px}.protocol-manager__nav{overflow:auto;justify-content:flex-start}.protocol-manager__nav button{min-width:150px}.protocol-manager__toolbar,.protocol-manager__toolbar.is-activations{grid-template-columns:1fr}.protocol-card footer{flex-wrap:wrap}.activation-row{grid-template-columns:38px minmax(0,1fr)}.activation-row__icon{width:38px;height:38px}.activation-row__progress{grid-column:1/-1}.activation-row>.btn{grid-row:auto;grid-column:1/-1;justify-content:center}.protocol-manager__dialog{padding:0}.protocol-manager__dialog-card{height:100%;max-height:none;border-radius:0}}
@media(max-width:479.98px){.protocol-manager__summary{grid-template-columns:1fr}.protocol-manager__hero-copy{align-items:flex-start}.protocol-manager__hero-copy p{display:none}.protocol-card header{display:block}.protocol-card__status{display:inline-flex;margin-top:.35rem}.protocol-card dl{grid-template-columns:1fr 1fr 1fr}.protocol-card footer .btn{flex:1;justify-content:center}.protocol-card footer .btn:last-child{flex:0}.protocol-manager__dialog-card>footer{flex-direction:column-reverse}.protocol-manager__dialog-card>footer .btn{justify-content:center}}
@media(prefers-reduced-motion:reduce){.protocol-manager__dialog{backdrop-filter:none}}
.protocol-manager__table-title{display:block;color:var(--pm-navy);font-size:.72rem}.protocol-manager table small{display:block;max-width:320px;overflow:hidden;margin-top:.12rem;color:#8590a3;font-size:.58rem;text-overflow:ellipsis;white-space:nowrap}.protocol-manager__review-row{background:#fffdf8}.protocol-manager__review,.protocol-manager__ok{display:inline-flex;align-items:center;gap:.25rem;font-size:.59rem;font-weight:750}.protocol-manager__review{color:#a2641b}.protocol-manager__ok{color:#267360}.protocol-manager__progress{display:grid;min-width:110px;grid-template-columns:minmax(62px,1fr) auto;align-items:center;gap:.38rem}.protocol-manager__progress::before{content:"";grid-row:1;grid-column:1;height:6px;border-radius:99px;background:#e5e9f1}.protocol-manager__progress i{z-index:1;grid-row:1;grid-column:1;height:6px;border-radius:99px;background:linear-gradient(90deg,var(--pm-indigo),var(--pm-teal))}.protocol-manager__progress b{font-size:.6rem}.protocol-manager__activation-form{display:grid;gap:.8rem}.protocol-manager__activation-form fieldset{padding:.75rem;border:1px solid #e0e5ee;border-radius:13px;background:#fafbfe}.protocol-manager__activation-form legend{float:none;width:auto;padding:0 .3rem;margin:0;color:#536078;font-size:.68rem;font-weight:800}.protocol-manager__activation-form .form-label{color:#58667d;font-size:.65rem;font-weight:750}.protocol-manager__activation-form .form-control,.protocol-manager__activation-form .form-select{font-size:.7rem;border-color:#dce2ed;border-radius:9px}.protocol-manager__pager{display:flex;align-items:center;justify-content:space-between;gap:.6rem;color:#778296;font-size:.64rem}.protocol-manager__pager .btn{display:inline-flex;align-items:center;gap:.2rem;font-size:.62rem}
</style>
