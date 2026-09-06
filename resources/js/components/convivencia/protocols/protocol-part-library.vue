<script>
import axios from "axios";
import {
  confirmConvivenciaAction,
  formatConvivenciaError,
  showConvivenciaError,
  showConvivenciaSuccess,
} from "../module-utils";
import { downloadConvivenciaPartPdf } from "../pdf/convivencia-part-pdf";
import ConvivenciaDataTable from "../ui/convivencia-data-table.vue";
import ConvivenciaFormModal from "../ui/convivencia-form-modal.vue";
import ConvivenciaRowActions from "../ui/convivencia-row-actions.vue";

const categoryDefaults = [
  { value: "action", text: "Acción", icon: "bx-play-circle", tone: "blue" },
  { value: "protective_measure", text: "Medida protectora", icon: "bx-shield-quarter", tone: "teal" },
  { value: "sanction", text: "Sanción", icon: "bx-error-circle", tone: "red" },
  { value: "formative_measure", text: "Medida formativa", icon: "bx-book-heart", tone: "indigo" },
  { value: "restorative_measure", text: "Medida restaurativa", icon: "bx-wrench", tone: "blue" },
  { value: "interview", text: "Entrevista", icon: "bx-conversation", tone: "violet" },
  { value: "communication", text: "Comunicación", icon: "bx-message-detail", tone: "cyan" },
  { value: "notification", text: "Notificación", icon: "bx-bell", tone: "cyan" },
  { value: "document", text: "Documento", icon: "bx-file", tone: "slate" },
  { value: "external_report", text: "Denuncia o derivación externa", icon: "bx-transfer-alt", tone: "amber" },
  { value: "internal_referral", text: "Derivación interna", icon: "bx-right-arrow-alt", tone: "blue" },
  { value: "external_referral", text: "Derivación externa", icon: "bx-export", tone: "amber" },
  { value: "evidence", text: "Evidencia", icon: "bx-check-square", tone: "green" },
  { value: "appeal", text: "Apelación", icon: "bx-revision", tone: "violet" },
  { value: "follow_up", text: "Seguimiento", icon: "bx-calendar-check", tone: "green" },
  { value: "closure", text: "Cierre", icon: "bx-check-circle", tone: "green" },
  { value: "special_rule", text: "Regla especial", icon: "bx-git-branch", tone: "slate" },
  { value: "other", text: "Otra parte", icon: "bx-grid-alt", tone: "slate" },
];

const blankPart = () => ({
  id: null,
  category: "protective_measure",
  code: "",
  title: "",
  description: "",
  instructions: "",
  responsible_label: "",
  population_scope: "",
  legal_reference: "",
  deadline_value: null,
  deadline_unit: "business_days",
  deadline_anchor: "step_started",
  requires_evidence: false,
  is_sensitive: false,
  active: true,
  metadata_json: "{}",
});

export default {
  components: { ConvivenciaDataTable, ConvivenciaFormModal, ConvivenciaRowActions },
  props: {
    parts: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, total: 0 }) },
    capabilities: { type: Object, default: () => ({}) },
    categories: { type: Array, default: () => [] },
  },
  emits: ["changed", "query"],
  data() {
    return {
      search: "",
      categoryFilter: "",
      formOpen: false,
      form: blankPart(),
      saving: false,
      deletingId: null,
      exportingId: null,
    };
  },
  computed: {
    canManage() {
      return this.capabilities.can_manage_protocols === true;
    },
    canExport() {
      return this.capabilities.can_export_reports === true;
    },
    categoryOptions() {
      if (!this.categories?.length) return categoryDefaults;
      return this.categories.map((item) => {
        const value = item.value || item.code || item.slug || item.id;
        const fallback = categoryDefaults.find((option) => option.value === value);
        return {
          value,
          text: item.text || item.label || item.name || fallback?.text || this.humanize(value),
          icon: item.icon || fallback?.icon || "bx-grid-alt",
          tone: item.tone || fallback?.tone || "slate",
        };
      });
    },
    filteredParts() {
      return this.parts || [];
    },
  },
  methods: {
    humanize(value) {
      return String(value || "")
        .replaceAll("_", " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
    },
    deadlineUnitLabel(value) {
      return {
        hours: "horas",
        calendar_days: "días corridos",
        business_days: "días hábiles",
        school_days: "días hábiles escolares",
        external: "plazo externo",
        external_defined: "definido por organismo",
      }[value] || this.humanize(value);
    },
    partCategory(part) {
      return part?.category || part?.part_type || part?.type || "other";
    },
    partName(part) {
      return part?.title || part?.code || "Parte sin nombre";
    },
    categoryMeta(value) {
      return this.categoryOptions.find((option) => option.value === value)
        || categoryDefaults.find((option) => option.value === value)
        || { value, text: this.humanize(value), icon: "bx-grid-alt", tone: "slate" };
    },
    categoryLabel(value) {
      return this.categoryMeta(value).text;
    },
    applyQuery(page = 1) {
      this.$emit("query", { page, search: this.search.trim(), category: this.categoryFilter || "" });
    },
    usageCount(part) {
      return Number(part.links_count ?? part.usage_count ?? part.protocols_count ?? part.protocol_count ?? 0);
    },
    openCreate() {
      this.form = blankPart();
      this.formOpen = true;
      this.$nextTick(() => this.$refs.partName?.focus());
    },
    openEdit(part) {
      this.form = {
        id: part.id,
        category: this.partCategory(part),
        code: part.code || "",
        title: this.partName(part),
        description: part.description || "",
        instructions: part.instructions || "",
        responsible_label: part.responsible_label || "",
        population_scope: part.population_scope || "",
        legal_reference: part.legal_reference || "",
        deadline_value: part.deadline_value || null,
        deadline_unit: part.deadline_unit || "business_days",
        deadline_anchor: part.deadline_anchor || "step_started",
        requires_evidence: Boolean(part.requires_evidence),
        is_sensitive: Boolean(part.is_sensitive),
        active: Boolean(part.active ?? part.is_active ?? true),
        metadata_json: JSON.stringify(part.metadata || {}, null, 2),
      };
      this.formOpen = true;
      this.$nextTick(() => this.$refs.partName?.focus());
    },
    cancel() {
      this.form = blankPart();
      this.formOpen = false;
    },
    onModalValue(open) {
      if (!open) this.cancel();
    },
    partActions(part) {
      const count = this.usageCount(part);
      return [
        { key: "pdf", label: this.exportingId === part.id ? "Preparando…" : "Exportar PDF", icon: "bxs-file-pdf", tone: "danger", visible: this.canExport, disabled: Boolean(this.exportingId) },
        { key: "edit", label: "Editar", icon: "bx-edit-alt", visible: this.canManage },
        { key: "remove", label: count ? "Archivar" : "Eliminar", icon: count ? "bx-archive" : "bx-trash", tone: "danger", visible: this.canManage, disabled: this.deletingId === part.id },
      ];
    },
    handlePartAction(action, part) {
      if (action === "pdf") this.exportPart(part);
      if (action === "edit") this.openEdit(part);
      if (action === "remove") this.remove(part);
    },
    payload() {
      let metadata = {};
      try {
        metadata = this.form.metadata_json?.trim() ? JSON.parse(this.form.metadata_json) : {};
      } catch {
        throw new Error("La configuración adicional debe ser un objeto JSON válido.");
      }
      return {
        category: this.form.category,
        code: this.form.code,
        title: this.form.title,
        description: this.form.description || null,
        instructions: this.form.instructions || null,
        responsible_label: this.form.responsible_label || null,
        population_scope: this.form.population_scope || null,
        legal_reference: this.form.legal_reference || null,
        deadline_value: this.form.deadline_value ? Number(this.form.deadline_value) : null,
        deadline_unit: this.form.deadline_value ? this.form.deadline_unit : null,
        deadline_anchor: this.form.deadline_value ? this.form.deadline_anchor : null,
        requires_evidence: Boolean(this.form.requires_evidence),
        is_sensitive: Boolean(this.form.is_sensitive),
        active: Boolean(this.form.active),
        metadata,
      };
    },
    async save() {
      if (!this.form.title.trim() || !this.form.code.trim()) {
        showConvivenciaError("Ingresa el código y el título de la parte del protocolo.", "Falta información");
        return;
      }
      this.saving = true;
      try {
        const payload = this.payload();
        const request = this.form.id
          ? axios.put(`/api/convivencia/protocol-parts/${this.form.id}`, payload)
          : axios.post("/api/convivencia/protocol-parts", payload);
        await request;
        await showConvivenciaSuccess(this.form.id ? "Parte actualizada correctamente." : "Parte creada correctamente.");
        this.cancel();
        this.$emit("changed");
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error, "No se pudo guardar la parte del protocolo."));
      } finally {
        this.saving = false;
      }
    },
    async remove(part) {
      const count = this.usageCount(part);
      const confirmation = await confirmConvivenciaAction({
        title: count ? "Archivar parte compartida" : "Eliminar parte",
        text: count
          ? `Esta parte tiene ${count} vinculación(es) con protocolos. Se conservará en las versiones históricas y dejará de estar disponible para nuevas vinculaciones.`
          : "La parte dejará de estar disponible en la biblioteca de protocolos.",
        confirmButtonText: count ? "Archivar" : "Eliminar",
      });
      if (!confirmation.isConfirmed) return;
      this.deletingId = part.id;
      try {
        await axios.delete(`/api/convivencia/protocol-parts/${part.id}`);
        await showConvivenciaSuccess(count ? "Parte archivada correctamente." : "Parte eliminada correctamente.");
        this.$emit("changed");
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error, "No se pudo eliminar la parte del protocolo."));
      } finally {
        this.deletingId = null;
      }
    },
    async exportPart(part) {
      if (this.exportingId) return;
      this.exportingId = part.id;
      try {
        const response = await axios.get(`/api/convivencia/protocol-parts/${part.id}`);
        await downloadConvivenciaPartPdf(response.data?.data || response.data);
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error, "No se pudo exportar la parte del protocolo."));
      } finally {
        this.exportingId = null;
      }
    },
  },
};
</script>

<template>
  <section class="part-library" aria-label="Biblioteca de partes de protocolos">
    <ConvivenciaDataTable
      title="Biblioteca de partes"
      subtitle="Medidas, sanciones, documentos y condiciones reutilizables en los protocolos."
      icon="bx-library"
      :count="Number(pagination.total ?? filteredParts.length)"
      :loading="loading"
      :empty="!filteredParts.length"
      empty-title="No hay partes para estos filtros"
      empty-text="Crea una parte o ajusta la búsqueda para encontrar otros componentes."
      min-width="1040px"
    >
      <template #actions>
        <button v-if="canManage" type="button" class="btn btn-primary btn-sm part-library__new" @click="openCreate">
          <i class="bx bx-plus" aria-hidden="true"></i>Nueva parte
        </button>
      </template>
      <template #toolbar>
        <div class="part-library__filters">
          <div class="input-group">
            <span class="input-group-text"><i class="bx bx-search" aria-hidden="true"></i></span>
            <input v-model="search" type="search" class="form-control" placeholder="Buscar por nombre, código o descripción" aria-label="Buscar partes" @keyup.enter="applyQuery(1)" />
          </div>
          <select v-model="categoryFilter" class="form-select" aria-label="Filtrar por categoría" @change="applyQuery(1)">
            <option value="">Todas las categorías</option>
            <option v-for="option in categoryOptions" :key="option.value" :value="option.value">{{ option.text }}</option>
          </select>
          <button type="button" class="btn btn-outline-secondary part-library__apply" :disabled="loading" @click="applyQuery(1)"><i class="bx bx-search" aria-hidden="true"></i>Aplicar</button>
        </div>
      </template>

      <table class="table align-middle">
        <thead><tr><th>Parte</th><th>Categoría</th><th>Responsable / población</th><th>Plazo</th><th>Evidencia</th><th>Vinculaciones</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
        <tbody>
          <tr v-for="part in filteredParts" :key="part.id">
            <td data-label="Parte">
              <div class="part-library__identity">
                <span class="part-library__part-icon" :class="`is-${categoryMeta(partCategory(part)).tone}`"><i class="bx" :class="categoryMeta(partCategory(part)).icon" aria-hidden="true"></i></span>
                <span><b>{{ partName(part) }}</b><small>{{ part.code || "Sin código" }}</small></span>
              </div>
            </td>
            <td data-label="Categoría"><span class="part-library__category">{{ categoryLabel(partCategory(part)) }}</span></td>
            <td data-label="Responsable / población"><b class="part-library__cell-title">{{ part.responsible_label || "Sin responsable" }}</b><small class="part-library__cell-subtitle">{{ part.population_scope || "Población no especificada" }}</small></td>
            <td data-label="Plazo"><span v-if="part.deadline_value">{{ part.deadline_value }} {{ deadlineUnitLabel(part.deadline_unit) }}</span><span v-else class="text-muted">Sin plazo propio</span></td>
            <td data-label="Evidencia"><span class="part-library__boolean" :class="{ 'is-yes': part.requires_evidence }"><i class="bx" :class="part.requires_evidence ? 'bx-check' : 'bx-minus'" aria-hidden="true"></i>{{ part.requires_evidence ? "Requerida" : "No exigida" }}</span></td>
            <td data-label="Vinculaciones"><span class="part-library__usage"><i class="bx bx-link-alt" aria-hidden="true"></i>{{ usageCount(part) }} vinculación(es)</span></td>
            <td data-label="Estado"><span class="part-library__status" :class="{ 'is-off': (part.active ?? part.is_active) === false }">{{ (part.active ?? part.is_active) === false ? "Archivada" : "Disponible" }}</span></td>
            <td data-label="Acciones">
              <ConvivenciaRowActions :actions="partActions(part)" :item-label="partName(part)" @select="handlePartAction($event, part)" />
            </td>
          </tr>
        </tbody>
      </table>

      <template #empty-action>
        <button v-if="canManage" type="button" class="btn btn-primary btn-sm" @click="openCreate"><i class="bx bx-plus" aria-hidden="true"></i> Crear primera parte</button>
      </template>
      <template v-if="pagination.last_page > 1" #footer>
        <div class="part-library__pager"><button type="button" class="btn btn-sm btn-outline-secondary" :disabled="loading || pagination.current_page <= 1" @click="applyQuery(pagination.current_page - 1)"><i class="bx bx-chevron-left" aria-hidden="true"></i>Anterior</button><span>Página {{ pagination.current_page }} de {{ pagination.last_page }}</span><button type="button" class="btn btn-sm btn-outline-secondary" :disabled="loading || pagination.current_page >= pagination.last_page" @click="applyQuery(pagination.current_page + 1)">Siguiente<i class="bx bx-chevron-right" aria-hidden="true"></i></button></div>
      </template>
    </ConvivenciaDataTable>

    <ConvivenciaFormModal
      :model-value="formOpen"
      :title="form.id ? 'Editar parte del protocolo' : 'Crear parte reutilizable'"
      eyebrow="Biblioteca de protocolos"
      description="Configura el componente una sola vez para vincularlo a etapas o al protocolo completo."
      icon="bx-library"
      size="xl"
      :busy="saving"
      hide-footer
      formless
      @update:model-value="onModalValue"
    >
      <form class="part-library__form" @submit.prevent="save">
        <div class="row g-3">
        <div class="col-lg-4">
          <label class="form-label" for="protocol-part-category">Categoría *</label>
          <select id="protocol-part-category" v-model="form.category" class="form-select" required>
            <option v-for="option in categoryOptions" :key="option.value" :value="option.value">{{ option.text }}</option>
          </select>
        </div>
        <div class="col-lg-2">
          <label class="form-label" for="protocol-part-code">Código *</label>
          <input id="protocol-part-code" v-model="form.code" class="form-control" maxlength="100" required placeholder="medida_protectora_01" />
        </div>
        <div class="col-lg-6">
          <label class="form-label" for="protocol-part-name">Título *</label>
          <input id="protocol-part-name" ref="partName" v-model="form.title" class="form-control" maxlength="191" required placeholder="Nombre claro y operativo" />
        </div>
        <div class="col-12">
          <label class="form-label" for="protocol-part-description">Descripción</label>
          <textarea id="protocol-part-description" v-model="form.description" class="form-control" rows="3" placeholder="Explica qué debe ejecutarse y cuál es su propósito."></textarea>
        </div>
        <div class="col-lg-6">
          <label class="form-label" for="protocol-part-criteria">Instrucciones de ejecución</label>
          <textarea id="protocol-part-criteria" v-model="form.instructions" class="form-control" rows="2" placeholder="Indica cómo debe ejecutarse y verificarse."></textarea>
        </div>
        <div class="col-lg-6">
          <label class="form-label" for="protocol-part-legal">Referencia reglamentaria o legal</label>
          <textarea id="protocol-part-legal" v-model="form.legal_reference" class="form-control" rows="2" placeholder="Artículo, protocolo, ley u otra referencia."></textarea>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="protocol-part-responsible">Responsable</label>
          <input id="protocol-part-responsible" v-model="form.responsible_label" class="form-control" maxlength="160" placeholder="Encargado/a de Convivencia" />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="protocol-part-scope">Población aplicable</label>
          <input id="protocol-part-scope" v-model="form.population_scope" class="form-control" maxlength="120" placeholder="Estudiantes, apoderados, funcionarios…" />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="protocol-part-deadline">Plazo propio</label>
          <div class="input-group">
            <input id="protocol-part-deadline" v-model.number="form.deadline_value" type="number" min="1" max="365" class="form-control" placeholder="Sin plazo" />
            <select v-model="form.deadline_unit" class="form-select" aria-label="Unidad de plazo">
              <option value="hours">Horas</option><option value="calendar_days">Días corridos</option><option value="business_days">Días hábiles</option><option value="school_days">Días hábiles escolares</option><option value="external">Plazo externo</option><option value="external_defined">Definido por organismo</option>
            </select>
          </div>
        </div>
        <div v-if="form.deadline_value" class="col-md-4">
          <label class="form-label" for="protocol-part-anchor">El plazo se cuenta desde</label>
          <select id="protocol-part-anchor" v-model="form.deadline_anchor" class="form-select">
            <option value="activation_started">Activación del protocolo</option><option value="step_started">Inicio de la etapa</option><option value="previous_step_completed">Término de etapa anterior</option>
          </select>
        </div>
        <div class="col-md-8 part-library__switches">
          <label><input v-model="form.requires_evidence" type="checkbox" />Exige evidencia</label>
          <label><input v-model="form.is_sensitive" type="checkbox" />Contenido sensible</label>
          <label><input v-model="form.active" type="checkbox" />Disponible</label>
        </div>
        <div class="col-12">
          <details class="part-library__advanced">
            <summary>Configuración avanzada</summary>
            <label class="form-label" for="protocol-part-metadata">Metadatos JSON</label>
            <textarea id="protocol-part-metadata" v-model="form.metadata_json" class="form-control font-monospace" rows="3" spellcheck="false"></textarea>
          </details>
        </div>
        </div>
        <footer class="part-library__form-footer">
          <span><i class="bx bx-lock-alt" aria-hidden="true"></i>Los cambios quedan registrados y respetan el acceso sensible.</span>
          <div>
            <button type="button" class="btn btn-outline-secondary" :disabled="saving" @click="cancel">Cancelar</button>
            <button type="submit" class="btn btn-primary" :disabled="saving"><span v-if="saving" class="spinner-border spinner-border-sm" aria-hidden="true"></span><i v-else class="bx bx-save" aria-hidden="true"></i>{{ saving ? "Guardando…" : "Guardar parte" }}</button>
          </div>
        </footer>
      </form>
    </ConvivenciaFormModal>
  </section>
</template>

<style scoped>
.part-library{display:grid;gap:1rem}.part-library__new,.part-library__form .btn,.part-library__apply,.part-library__pager .btn{display:inline-flex;align-items:center;gap:.35rem}.part-library__filters{display:flex;align-items:center;gap:.75rem}.part-library__filters .input-group{flex:1}.part-library__filters .form-select{max-width:280px}.part-library__identity{display:flex;min-width:230px;align-items:center;gap:.55rem}.part-library__identity>span:last-child{display:grid;min-width:0;gap:.12rem}.part-library__identity b{overflow:hidden;max-width:280px;color:#2d3a5d;font-size:.71rem;text-overflow:ellipsis;white-space:nowrap}.part-library__identity small,.part-library__cell-subtitle{display:block;color:#8590a2;font-size:.59rem}.part-library__part-icon{display:grid;width:34px;height:34px;flex:0 0 34px;color:#596980;font-size:1rem;place-items:center;border-radius:10px;background:#eef1f6}.part-library__part-icon.is-teal{color:#25816c;background:#e9f7f3}.part-library__part-icon.is-red{color:#b43c49;background:#fff0f2}.part-library__part-icon.is-indigo,.part-library__part-icon.is-blue{color:#4b5fce;background:#eef0ff}.part-library__part-icon.is-violet{color:#73509d;background:#f3eef9}.part-library__part-icon.is-cyan{color:#237f91;background:#eaf7fa}.part-library__part-icon.is-amber{color:#a66813;background:#fff5e4}.part-library__part-icon.is-green{color:#2f7950;background:#eaf7ef}.part-library__category,.part-library__status,.part-library__boolean,.part-library__usage{display:inline-flex;align-items:center;gap:.25rem;padding:.25rem .45rem;font-size:.59rem;font-weight:750;border-radius:999px}.part-library__category{color:#4658b7;background:#eef1ff}.part-library__status{color:#24745f;background:#e9f7f2}.part-library__status.is-off{color:#9a5660;background:#fff0f2}.part-library__boolean{color:#6d788b;background:#f2f4f7}.part-library__boolean.is-yes{color:#26755d;background:#e9f7f1}.part-library__usage{color:#5f6e84;background:#f2f4f8}.part-library__cell-title{display:block;color:#44516a;font-size:.66rem}.part-library__form{padding:.1rem}.part-library__switches{display:flex;align-items:flex-end;justify-content:flex-end;gap:.75rem;flex-wrap:wrap;padding-bottom:.35rem}.part-library__switches label{display:inline-flex;align-items:center;gap:.35rem;margin:0;color:#536078;font-size:.7rem;font-weight:700}.part-library__switches input{width:17px;height:17px;accent-color:#4f63d9}.part-library__form-footer{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding-top:1rem;margin-top:1rem;border-top:1px solid #e2e7ef}.part-library__form-footer>span{display:inline-flex;align-items:center;gap:.35rem;color:#788398;font-size:.65rem}.part-library__form-footer>span i{color:#2d8b75}.part-library__form-footer>div{display:flex;gap:.5rem}.part-library__advanced{grid-column:1/-1;padding:.65rem .75rem;border:1px solid #e2e7ef;border-radius:12px;background:#fafbfe}.part-library__advanced summary{color:#53617a;font-size:.68rem;font-weight:750;cursor:pointer}.part-library__advanced textarea{margin-top:.65rem;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.68rem}.part-library__pager{display:flex;align-items:center;justify-content:space-between;gap:.6rem;color:#778296;font-size:.64rem}
@media(max-width:991.98px){.part-library__switches{justify-content:flex-start}}
@media(max-width:767.98px){.part-library__filters{align-items:stretch;flex-direction:column}.part-library__filters .form-select{max-width:none}.part-library__apply{justify-content:center}.part-library__form-footer{position:sticky;bottom:-1rem;z-index:2;align-items:stretch;flex-direction:column;padding:.8rem 0;background:#fff}.part-library__form-footer>span{display:none}.part-library__form-footer>div{display:grid;grid-template-columns:1fr 1fr}.part-library__switches{align-items:flex-start;flex-direction:column}}
</style>
