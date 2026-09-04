<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import { getPdfMake } from "../../utils/pdfmake";

const REVIEW_OPTIONS = [
  { value: "OK", label: "Cumple", icon: "mdi-check-circle-outline", tone: "ok" },
  { value: "No OK", label: "Requiere atención", icon: "mdi-alert-circle-outline", tone: "issue" },
  { value: "N/A", label: "No aplica", icon: "mdi-minus-circle-outline", tone: "na" },
];

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      success: null,
      visit: null,
      items: [],
      responseByItem: {},
      photoUploading: {},
      photoDeleting: {},
      creatingWorkOrder: {},
      expandedItems: {},
      search: "",
      statusFilter: "all",
      activeSystem: "all",
      filtersInitialized: false,
      dirty: false,
      reviewOptions: REVIEW_OPTIONS,
    };
  },
  computed: {
    visitId() {
      return this.$route.params.id;
    },
    systems() {
      return [...new Set(this.items.map((item) => item.system || "General"))];
    },
    statusCounts() {
      return this.items.reduce(
        (counts, item) => {
          const status = this.responseFor(item.id).review_status || "pending";
          counts[status] = (counts[status] || 0) + 1;
          return counts;
        },
        { OK: 0, "No OK": 0, "N/A": 0, pending: 0 }
      );
    },
    reviewedCount() {
      return this.items.length - this.statusCounts.pending;
    },
    completionPercent() {
      return this.items.length ? Math.round((this.reviewedCount / this.items.length) * 100) : 0;
    },
    photoTotal() {
      return this.items.reduce((total, item) => total + this.photoEntries(item.id).length, 0);
    },
    workOrderTotal() {
      return this.items.filter((item) => this.responseFor(item.id).work_order_id).length;
    },
    findingTotal() {
      return this.items.filter((item) => String(this.responseFor(item.id).finding_description || "").trim()).length;
    },
    filteredItems() {
      const query = this.search.trim().toLocaleLowerCase("es");

      return this.items.filter((item) => {
        const response = this.responseFor(item.id);
        const system = item.system || "General";
        const matchesSystem = this.activeSystem === "all" || system === this.activeSystem;
        const matchesStatus =
          this.statusFilter === "all" ||
          (this.statusFilter === "pending" ? !response.review_status : response.review_status === this.statusFilter);
        const haystack = [
          item.system,
          item.subdimension,
          item.review,
          response.observations,
          response.finding_description,
          response.work_order_id ? `OT ${response.work_order_id}` : "",
        ]
          .filter(Boolean)
          .join(" ")
          .toLocaleLowerCase("es");

        return matchesSystem && matchesStatus && (!query || haystack.includes(query));
      });
    },
    groupedItems() {
      const groups = {};
      for (const item of this.filteredItems) {
        const system = item.system || "General";
        const subdimension = item.subdimension || "Revisión general";
        groups[system] ||= {};
        groups[system][subdimension] ||= [];
        groups[system][subdimension].push(item);
      }
      return groups;
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    async load({ preserveMessage = false } = {}) {
      this.loading = !this.visit;
      this.error = null;
      try {
        const { data } = await axios.get(`/api/maintenance/visits/${this.visitId}/checklist`);
        this.visit = data.visit;
        this.items = data.items || [];
        if (!this.filtersInitialized) {
          this.activeSystem = this.items[0]?.system || "all";
          this.filtersInitialized = true;
        }
        const responseByItem = {};
        for (const response of data.responses || []) {
          responseByItem[response.maintenance_checklist_item_id] = response;
        }
        this.responseByItem = responseByItem;
        this.dirty = false;
        if (!preserveMessage) this.success = null;
      } catch (error) {
        this.error = this.formatError(error, "No fue posible cargar la revisión de la dependencia.");
      } finally {
        this.loading = false;
      }
    },
    responseFor(itemId) {
      return this.responseByItem[itemId] || {
        maintenance_checklist_item_id: itemId,
        review_status: "",
        observations: "",
        finding_description: "",
        photo_url: null,
        photo_urls: [],
        photos: [],
        work_order_id: null,
      };
    },
    setResponse(itemId, patch, markDirty = true) {
      this.responseByItem = {
        ...this.responseByItem,
        [itemId]: { ...this.responseFor(itemId), ...patch },
      };
      if (markDirty) this.dirty = true;
    },
    setReviewStatus(item, status) {
      this.setResponse(item.id, { review_status: status });
      if (status === "No OK") this.expandedItems = { ...this.expandedItems, [item.id]: true };
    },
    toggleDetails(itemId) {
      this.expandedItems = { ...this.expandedItems, [itemId]: !this.isExpanded(itemId) };
    },
    isExpanded(itemId) {
      const response = this.responseFor(itemId);
      return Boolean(
        this.expandedItems[itemId] ||
          response.review_status === "No OK" ||
          response.observations ||
          response.finding_description ||
          this.photoEntries(itemId).length ||
          response.work_order_id
      );
    },
    async saveChecklist({ showMessage = true } = {}) {
      this.saving = true;
      this.error = null;
      if (showMessage) this.success = null;
      try {
        const responses = Object.values(this.responseByItem).map((row) => ({
          maintenance_checklist_item_id: row.maintenance_checklist_item_id,
          review_status: row.review_status || null,
          observations: String(row.observations || "").trim() || null,
          finding_description: String(row.finding_description || "").trim() || null,
        }));
        const { data } = await axios.post(`/api/maintenance/visits/${this.visitId}/checklist`, { responses });
        if (showMessage) this.success = data.message;
        await this.load({ preserveMessage: showMessage });
        return true;
      } catch (error) {
        this.error = this.formatError(error, "No fue posible guardar el checklist.");
        return false;
      } finally {
        this.saving = false;
      }
    },
    async handlePhotoSelection(item, event) {
      const files = [...(event.target?.files || [])];
      if (event.target) event.target.value = "";
      if (files.length) await this.uploadPhotos(item, files);
    },
    async uploadPhotos(item, files) {
      const currentCount = this.photoEntries(item.id).length;
      if (currentCount + files.length > 3) {
        this.error = `Puedes guardar hasta 3 fotos en este hallazgo u OT. Ya hay ${currentCount}.`;
        return;
      }

      this.photoUploading = { ...this.photoUploading, [item.id]: true };
      this.error = null;
      this.success = null;
      try {
        let latestResponse = null;
        const payload = new FormData();
        payload.append("maintenance_checklist_item_id", String(item.id));
        files.forEach((file) => payload.append("photos[]", file));
        const { data } = await axios.post(`/api/maintenance/visits/${this.visitId}/checklist-photo`, payload);
        latestResponse = data.data;
        this.success = data.message;
        if (latestResponse) this.setResponse(item.id, latestResponse, false);
      } catch (error) {
        this.error = this.formatError(error, "No fue posible agregar las fotografías.");
      } finally {
        this.photoUploading = { ...this.photoUploading, [item.id]: false };
      }
    },
    async deletePhoto(item, photo) {
      if (!photo.id || !confirm("¿Quitar esta foto de la evidencia?")) return;
      this.photoDeleting = { ...this.photoDeleting, [photo.id]: true };
      this.error = null;
      this.success = null;
      try {
        const { data } = await axios.delete(`/api/maintenance/visits/${this.visitId}/checklist-photos/${photo.id}`);
        this.success = data.message;
        this.setResponse(item.id, data.data, false);
      } catch (error) {
        this.error = this.formatError(error, "No fue posible eliminar la fotografía.");
      } finally {
        this.photoDeleting = { ...this.photoDeleting, [photo.id]: false };
      }
    },
    photoEntries(itemId) {
      const response = this.responseFor(itemId);
      const entries = (response.photos || [])
        .filter((photo) => photo?.url)
        .map((photo) => ({ id: photo.id, url: photo.url, name: photo.original_name || `Fotografía ${photo.id}` }));
      if (response.photo_url && !entries.some((photo) => photo.url === response.photo_url)) {
        entries.unshift({ id: null, url: response.photo_url, name: "Fotografía histórica" });
      }
      return entries;
    },
    async createWorkOrder(item) {
      const current = this.responseFor(item.id);
      if (!String(current.finding_description || "").trim()) {
        this.expandedItems = { ...this.expandedItems, [item.id]: true };
        this.error = "Describe el hallazgo antes de generar la orden de trabajo.";
        return;
      }
      if (current.work_order_id) return;

      this.creatingWorkOrder = { ...this.creatingWorkOrder, [item.id]: true };
      this.error = null;
      this.success = null;
      try {
        const saved = await this.saveChecklist({ showMessage: false });
        if (!saved) return;
        const response = this.responseFor(item.id);
        const { data } = await axios.post(`/api/maintenance/visit-checklist-responses/${response.id}/create-work-order`, {});
        this.success = `${data.message} La evidencia fotográfica quedó vinculada.`;
        await this.load({ preserveMessage: true });
      } catch (error) {
        this.error = this.formatError(error, "No fue posible generar la orden de trabajo.");
      } finally {
        this.creatingWorkOrder = { ...this.creatingWorkOrder, [item.id]: false };
      }
    },
    statusMeta(status) {
      return REVIEW_OPTIONS.find((option) => option.value === status) || {
        value: "",
        label: "Pendiente",
        icon: "mdi-circle-outline",
        tone: "pending",
      };
    },
    itemNumber(item) {
      return this.items.findIndex((candidate) => candidate.id === item.id) + 1;
    },
    resetFilters() {
      this.search = "";
      this.statusFilter = "all";
      this.activeSystem = "all";
    },
    formatError(error, fallback) {
      const errors = error.response?.data?.errors;
      return errors ? Object.values(errors).flat().join(" ") : error.response?.data?.message || error.message || fallback;
    },
    dependencyLabel(dependency) {
      if (!dependency) return "Dependencia sin identificar";
      return [dependency.code, dependency.name].filter(Boolean).join(" · ");
    },
    dependencyContext(dependency) {
      if (!dependency) return "Sin ubicación complementaria";
      return [dependency.distribution, dependency.sector, dependency.zone, dependency.usage].filter(Boolean).join(" · ") || "Sin ubicación complementaria";
    },
    formatDMY(value) {
      if (!value) return "Sin fecha";
      const [year, month, day] = String(value).slice(0, 10).split("-");
      return year && month && day ? `${day}-${month}-${year}` : String(value);
    },
    formatTime(value) {
      if (!value) return "Sin hora";
      const text = String(value);
      return text.match(/(?:T|\s)(\d{2}:\d{2})/)?.[1] || text.match(/^(\d{2}:\d{2})/)?.[1] || text;
    },
    exportExcel() {
      const escapeHtml = (value) => String(value ?? "").replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;").replaceAll("'", "&#039;");
      const header = ["Sistema", "Subdimensión", "Revisión", "Estado", "Observaciones", "Hallazgo", "Fotos", "OT"];
      const rows = this.items.map((item) => {
        const response = this.responseFor(item.id);
        return [item.system, item.subdimension || "", item.review, this.statusMeta(response.review_status).label, response.observations || "", response.finding_description || "", this.photoEntries(item.id).length, response.work_order_id ? `OT #${response.work_order_id}` : ""];
      });
      const html = `<!doctype html><html><head><meta charset="utf-8"/></head><body><h3>Revisión de dependencia</h3><p>Dependencia: ${escapeHtml(this.dependencyLabel(this.visit?.dependency))}</p><p>Responsable: ${escapeHtml(this.visit?.responsible || "")} · Fecha: ${escapeHtml(this.formatDMY(this.visit?.visit_date))}</p><table border="1"><thead><tr>${header.map((label) => `<th>${escapeHtml(label)}</th>`).join("")}</tr></thead><tbody>${rows.map((row) => `<tr>${row.map((cell) => `<td>${escapeHtml(cell)}</td>`).join("")}</tr>`).join("")}</tbody></table></body></html>`;
      const blob = new Blob([html], { type: "application/vnd.ms-excel;charset=utf-8" });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = `revision-dependencia-${this.visitId}.xls`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
    },
    async exportPdf() {
      const pdfMake = await getPdfMake();
      const fetchImage = async (url) => {
        try {
          const response = await fetch(url.startsWith("http") || url.startsWith("/") ? url : `/${url}`);
          if (!response.ok) return null;
          const blob = await response.blob();
          return await new Promise((resolve) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = () => resolve(null);
            reader.readAsDataURL(blob);
          });
        } catch { return null; }
      };
      const body = [["Sistema", "Revisión", "Estado", "Observaciones", "Hallazgo", "Fotos"].map((text) => ({ text, bold: true, color: "#fff", fillColor: "#17324d" }))];
      for (const item of this.items) {
        const response = this.responseFor(item.id);
        body.push([`${item.system || "General"}\n${item.subdimension || ""}`, item.review, this.statusMeta(response.review_status).label, response.observations || "-", response.finding_description || "-", String(this.photoEntries(item.id).length)]);
      }
      const photoSections = [];
      for (const item of this.items.filter((candidate) => this.photoEntries(candidate.id).length)) {
        const response = this.responseFor(item.id);
        const images = (await Promise.all(this.photoEntries(item.id).map((photo) => fetchImage(photo.url)))).filter(Boolean);
        const rows = [];
        for (let index = 0; index < images.length; index += 2) {
          rows.push({ columns: [{ image: images[index], width: 240, margin: [0, 0, 8, 8] }, images[index + 1] ? { image: images[index + 1], width: 240, margin: [8, 0, 0, 8] } : { text: "" }], columnGap: 10 });
        }
        photoSections.push(
          { text: `${item.system || "General"} · ${item.subdimension || "Revisión general"}`, style: "photoTitle", pageBreak: photoSections.length ? "before" : undefined },
          { text: item.review, margin: [0, 0, 0, 5] },
          { text: `Estado: ${this.statusMeta(response.review_status).label} · Hallazgo: ${response.finding_description || "-"}`, margin: [0, 0, 0, 10] },
          ...(rows.length ? rows : [{ text: "Las fotos no estuvieron disponibles para incrustar.", italics: true }])
        );
      }
      const documentDefinition = {
        pageOrientation: "landscape",
        pageMargins: [32, 32, 32, 36],
        content: [
          { text: "Revisión de dependencia", style: "header" },
          { text: this.dependencyLabel(this.visit?.dependency), style: "dependency" },
          { text: this.dependencyContext(this.visit?.dependency), color: "#64748b", margin: [0, 0, 0, 5] },
          { text: `${this.visit?.responsible || "Sin responsable"} · ${this.formatDMY(this.visit?.visit_date)} · ${this.completionPercent}% revisado`, margin: [0, 0, 0, 14] },
          { table: { headerRows: 1, widths: [82, "*", 72, "*", "*", 35], body }, layout: "lightHorizontalLines" },
          photoSections.length ? { text: "Evidencia fotográfica", style: "subheader", pageBreak: "before" } : null,
          ...photoSections,
        ].filter(Boolean),
        styles: {
          header: { fontSize: 20, bold: true, color: "#17324d", margin: [0, 0, 0, 4] },
          dependency: { fontSize: 13, bold: true, color: "#1f8a70", margin: [0, 0, 0, 2] },
          subheader: { fontSize: 16, bold: true, color: "#17324d", margin: [0, 0, 0, 12] },
          photoTitle: { fontSize: 13, bold: true, color: "#17324d", margin: [0, 0, 0, 6] },
        },
        defaultStyle: { fontSize: 8, color: "#334155" },
      };
      pdfMake.createPdf(documentDefinition).download(`revision-dependencia-${this.visitId}.pdf`);
    },
  },
};
</script>

<template>
  <Layout>
    <main class="dependency-review-page">
      <section class="review-hero">
        <div class="review-hero__glow"></div>
        <div class="review-hero__content">
          <button class="review-back" type="button" @click="$router.push('/maintenance/visits')">
            <i class="mdi mdi-arrow-left"></i> Volver a visitas
          </button>

          <div class="review-hero__heading">
            <div>
              <span class="review-eyebrow">Mantención preventiva</span>
              <h1>Revisión de dependencia</h1>
              <p>Registra el estado, los hallazgos y toda la evidencia visual en un recorrido simple.</p>
            </div>
            <div v-if="visit" class="review-hero__status">
              <span>{{ visit.visit_type }}</span><strong>{{ visit.status }}</strong>
            </div>
          </div>

          <div v-if="visit" class="review-hero__location">
            <div class="review-location-icon"><i class="mdi mdi-office-building-marker-outline"></i></div>
            <div>
              <strong>{{ dependencyLabel(visit.dependency) }}</strong>
              <span>{{ dependencyContext(visit.dependency) }}</span>
            </div>
          </div>
        </div>
      </section>

      <BAlert v-if="error" show variant="danger" class="review-alert">
        <i class="mdi mdi-alert-circle-outline"></i><span>{{ error }}</span>
        <button type="button" aria-label="Cerrar mensaje" @click="error = null"><i class="mdi mdi-close"></i></button>
      </BAlert>
      <BAlert v-if="success" show variant="success" class="review-alert">
        <i class="mdi mdi-check-circle-outline"></i><span>{{ success }}</span>
        <button type="button" aria-label="Cerrar mensaje" @click="success = null"><i class="mdi mdi-close"></i></button>
      </BAlert>

      <LoadingState v-if="loading" message="Preparando la revisión..." compact />

      <template v-else-if="visit">
        <section class="review-overview">
          <article class="visit-card">
            <div class="visit-card__head"><span>Datos de la visita</span><i class="mdi mdi-calendar-check-outline"></i></div>
            <div class="visit-card__grid">
              <div><i class="mdi mdi-account-hard-hat-outline"></i><span>Responsable</span><strong>{{ visit.responsible || "Sin asignar" }}</strong></div>
              <div><i class="mdi mdi-calendar-blank-outline"></i><span>Fecha</span><strong>{{ formatDMY(visit.visit_date) }}</strong></div>
              <div><i class="mdi mdi-clock-outline"></i><span>Hora</span><strong>{{ formatTime(visit.visit_time) }}</strong></div>
            </div>
          </article>

          <article class="progress-card">
            <div class="progress-card__copy"><span>Avance del recorrido</span><strong>{{ reviewedCount }} de {{ items.length }} puntos revisados</strong></div>
            <div class="progress-card__value">{{ completionPercent }}%</div>
            <div class="progress-card__track" aria-hidden="true"><span :style="{ width: `${completionPercent}%` }"></span></div>
            <div class="progress-card__legend">
              <span><i class="legend-dot legend-dot--ok"></i>{{ statusCounts.OK }} cumplen</span>
              <span><i class="legend-dot legend-dot--issue"></i>{{ statusCounts['No OK'] }} alertas</span>
              <span><i class="legend-dot legend-dot--pending"></i>{{ statusCounts.pending }} pendientes</span>
            </div>
          </article>
        </section>

        <section class="review-metrics" aria-label="Resumen de revisión">
          <div class="review-metric review-metric--blue"><i class="mdi mdi-clipboard-check-outline"></i><div><strong>{{ reviewedCount }}</strong><span>Revisados</span></div></div>
          <div class="review-metric review-metric--amber"><i class="mdi mdi-alert-decagram-outline"></i><div><strong>{{ findingTotal }}</strong><span>Hallazgos</span></div></div>
          <div class="review-metric review-metric--violet"><i class="mdi mdi-image-multiple-outline"></i><div><strong>{{ photoTotal }}</strong><span>Fotografías</span></div></div>
          <div class="review-metric review-metric--green"><i class="mdi mdi-hammer-wrench"></i><div><strong>{{ workOrderTotal }}</strong><span>OT generadas</span></div></div>
        </section>

        <section class="review-workspace">
          <div class="review-toolbar">
            <div class="review-toolbar__top">
              <label class="review-search">
                <i class="mdi mdi-magnify"></i>
                <input v-model="search" type="search" placeholder="Buscar punto, observación, hallazgo u OT..." />
                <button v-if="search" type="button" aria-label="Limpiar búsqueda" @click="search = ''"><i class="mdi mdi-close-circle"></i></button>
              </label>
              <div class="review-toolbar__actions">
                <button class="review-button review-button--ghost" type="button" @click="exportExcel"><i class="mdi mdi-file-excel-outline"></i><span>Excel</span></button>
                <button class="review-button review-button--ghost" type="button" @click="exportPdf"><i class="mdi mdi-file-pdf-box"></i><span>PDF</span></button>
                <button class="review-button review-button--primary" type="button" :disabled="saving" @click="saveChecklist()">
                  <span v-if="saving" class="spinner-border spinner-border-sm"></span><i v-else class="mdi mdi-content-save-check-outline"></i>
                  {{ saving ? "Guardando" : "Guardar cambios" }}<span v-if="dirty && !saving" class="unsaved-dot"></span>
                </button>
              </div>
            </div>

            <div class="review-filter-row">
              <span class="review-filter-label">Mostrar</span>
              <button :class="['filter-chip', { active: statusFilter === 'all' }]" type="button" @click="statusFilter = 'all'">Todos <span>{{ items.length }}</span></button>
              <button :class="['filter-chip', { active: statusFilter === 'pending' }]" type="button" @click="statusFilter = 'pending'">Pendientes <span>{{ statusCounts.pending }}</span></button>
              <button :class="['filter-chip filter-chip--issue', { active: statusFilter === 'No OK' }]" type="button" @click="statusFilter = 'No OK'">Alertas <span>{{ statusCounts['No OK'] }}</span></button>
              <button :class="['filter-chip', { active: statusFilter === 'OK' }]" type="button" @click="statusFilter = 'OK'">Cumplen <span>{{ statusCounts.OK }}</span></button>
              <button :class="['filter-chip', { active: statusFilter === 'N/A' }]" type="button" @click="statusFilter = 'N/A'">No aplica <span>{{ statusCounts['N/A'] }}</span></button>
            </div>

            <div class="system-tabs" aria-label="Filtrar por sistema">
              <button :class="{ active: activeSystem === 'all' }" type="button" @click="activeSystem = 'all'">Todos los sistemas</button>
              <button v-for="system in systems" :key="system" :class="{ active: activeSystem === system }" type="button" @click="activeSystem = system">{{ system }}</button>
            </div>
          </div>

          <div v-if="items.length === 0" class="review-empty">
            <i class="mdi mdi-clipboard-text-off-outline"></i><h2>No hay puntos configurados</h2><p>La pauta de mantención no tiene ítems activos para revisar.</p>
          </div>
          <div v-else-if="filteredItems.length === 0" class="review-empty">
            <i class="mdi mdi-filter-off-outline"></i><h2>No encontramos coincidencias</h2><p>Prueba con otro texto o limpia los filtros de la revisión.</p>
            <button class="review-button review-button--primary" type="button" @click="resetFilters">Limpiar filtros</button>
          </div>

          <div v-else class="review-groups">
            <section v-for="(subGroups, system) in groupedItems" :key="system" class="system-group">
              <header class="system-group__header">
                <div class="system-group__icon"><i class="mdi mdi-tune-variant"></i></div>
                <div><span>Sistema</span><h2>{{ system }}</h2></div>
              </header>

              <div v-for="(list, subdimension) in subGroups" :key="subdimension" class="subdimension-group">
                <div class="subdimension-group__title"><h3>{{ subdimension }}</h3><span>{{ list.length }} {{ list.length === 1 ? "punto" : "puntos" }}</span></div>

                <article v-for="item in list" :key="item.id" :class="['review-item', `review-item--${statusMeta(responseFor(item.id).review_status).tone}`]">
                  <div class="review-item__head">
                    <div class="review-item__number">{{ itemNumber(item) }}</div>
                    <div class="review-item__question"><span>Punto de revisión</span><h4>{{ item.review }}</h4></div>
                    <div :class="['current-status', `current-status--${statusMeta(responseFor(item.id).review_status).tone}`]">
                      <i :class="['mdi', statusMeta(responseFor(item.id).review_status).icon]"></i>{{ statusMeta(responseFor(item.id).review_status).label }}
                    </div>
                  </div>

                  <div class="status-selector" role="group" :aria-label="`Estado de ${item.review}`">
                    <button v-for="option in reviewOptions" :key="option.value" :class="[`status-choice--${option.tone}`, { active: responseFor(item.id).review_status === option.value }]" type="button" @click="setReviewStatus(item, option.value)">
                      <i :class="['mdi', option.icon]"></i><span>{{ option.label }}</span>
                    </button>
                  </div>

                  <button class="detail-toggle" type="button" :aria-expanded="isExpanded(item.id)" @click="toggleDetails(item.id)">
                    <i class="mdi mdi-text-box-edit-outline"></i>{{ isExpanded(item.id) ? "Ocultar detalle" : "Agregar observación, hallazgo o fotos" }}
                    <span v-if="photoEntries(item.id).length"><i class="mdi mdi-image-multiple-outline"></i>{{ photoEntries(item.id).length }}</span>
                    <span v-if="responseFor(item.id).work_order_id" class="detail-toggle__ot">OT #{{ responseFor(item.id).work_order_id }}</span>
                    <i :class="['mdi', isExpanded(item.id) ? 'mdi-chevron-up' : 'mdi-chevron-down']"></i>
                  </button>

                  <div v-show="isExpanded(item.id)" class="review-item__details">
                    <div class="detail-fields">
                      <label><span>Observación de la revisión</span><textarea :value="responseFor(item.id).observations" rows="3" placeholder="Anota contexto, condición o recomendación preventiva..." @input="setResponse(item.id, { observations: $event.target.value })"></textarea></label>
                      <label :class="{ 'field-required': responseFor(item.id).review_status === 'No OK' }">
                        <span>Hallazgo para mantención <em v-if="responseFor(item.id).review_status === 'No OK'">Recomendado</em></span>
                        <textarea :value="responseFor(item.id).finding_description" rows="3" placeholder="Describe con precisión qué debe corregirse..." @input="setResponse(item.id, { finding_description: $event.target.value })"></textarea>
                      </label>
                    </div>

                    <section class="evidence-panel">
                      <div class="evidence-panel__head">
                        <div><span class="evidence-panel__icon"><i class="mdi mdi-camera-outline"></i></span><div><h5>Evidencia fotográfica</h5><p>Agrega distintas vistas del hallazgo. Máximo 3 fotos, 5 MB cada una.</p></div></div>
                        <span class="evidence-count">{{ photoEntries(item.id).length }} / 3</span>
                      </div>

                      <div v-if="photoEntries(item.id).length" class="evidence-gallery">
                        <figure v-for="(photo, photoIndex) in photoEntries(item.id)" :key="photo.id || photo.url">
                          <a :href="photo.url" target="_blank" rel="noopener"><img :src="photo.url" :alt="`Evidencia ${photoIndex + 1} de ${item.review}`" loading="lazy" /><span><i class="mdi mdi-arrow-expand"></i></span></a>
                          <figcaption>{{ photo.name }}</figcaption>
                          <button v-if="photo.id" type="button" :disabled="photoDeleting[photo.id]" aria-label="Eliminar fotografía" @click="deletePhoto(item, photo)">
                            <span v-if="photoDeleting[photo.id]" class="spinner-border spinner-border-sm"></span><i v-else class="mdi mdi-delete-outline"></i>
                          </button>
                        </figure>
                      </div>

                      <div class="evidence-actions">
                        <label :class="['evidence-action evidence-action--camera', { disabled: photoUploading[item.id] || photoEntries(item.id).length >= 3 }]">
                          <i class="mdi mdi-camera-plus-outline"></i><span><strong>Tomar foto</strong><small>Abrir cámara del teléfono</small></span>
                          <input class="d-none" type="file" accept="image/*" capture="environment" :disabled="photoUploading[item.id] || photoEntries(item.id).length >= 3" @change="handlePhotoSelection(item, $event)" />
                        </label>
                        <label :class="['evidence-action', { disabled: photoUploading[item.id] || photoEntries(item.id).length >= 3 }]">
                          <i class="mdi mdi-image-multiple-outline"></i><span><strong>Elegir fotos</strong><small>Seleccionar varias de la galería</small></span>
                          <input class="d-none" type="file" accept="image/*" multiple :disabled="photoUploading[item.id] || photoEntries(item.id).length >= 3" @change="handlePhotoSelection(item, $event)" />
                        </label>
                        <div v-if="photoUploading[item.id]" class="evidence-uploading"><span class="spinner-border spinner-border-sm"></span>Subiendo evidencia...</div>
                      </div>
                    </section>

                    <div :class="['work-order-action', { 'work-order-action--created': responseFor(item.id).work_order_id }]">
                      <template v-if="responseFor(item.id).work_order_id">
                        <span class="work-order-action__icon"><i class="mdi mdi-check-bold"></i></span>
                        <div><strong>OT #{{ responseFor(item.id).work_order_id }} generada</strong><small>Las fotos de esta revisión están vinculadas a la orden de trabajo.</small></div>
                        <router-link class="review-button review-button--soft" to="/maintenance/work-orders">Ver órdenes</router-link>
                      </template>
                      <template v-else>
                        <span class="work-order-action__icon"><i class="mdi mdi-hammer-wrench"></i></span>
                        <div><strong>¿Este hallazgo necesita una OT?</strong><small>Se guardará el detalle y se asociarán todas las fotografías automáticamente.</small></div>
                        <button class="review-button review-button--primary" type="button" :disabled="creatingWorkOrder[item.id] || !String(responseFor(item.id).finding_description || '').trim()" @click="createWorkOrder(item)">
                          <span v-if="creatingWorkOrder[item.id]" class="spinner-border spinner-border-sm"></span><i v-else class="mdi mdi-plus"></i>{{ creatingWorkOrder[item.id] ? "Generando" : "Generar OT" }}
                        </button>
                      </template>
                    </div>
                  </div>
                </article>
              </div>
            </section>
          </div>
        </section>

        <transition name="save-bar">
          <div v-if="dirty" class="mobile-save-bar">
            <div><span></span><strong>Cambios sin guardar</strong></div>
            <button type="button" :disabled="saving" @click="saveChecklist()"><span v-if="saving" class="spinner-border spinner-border-sm"></span><i v-else class="mdi mdi-content-save-check-outline"></i>Guardar</button>
          </div>
        </transition>
      </template>
    </main>
  </Layout>
</template>

<style scoped>
.dependency-review-page {
  --navy: #17324d;
  --green: #1f8a70;
  --border: #dfe7ef;
  --text: #233548;
  --muted: #6b7c8f;
  padding: 6px 0 48px;
  color: var(--text);
}

.review-hero {
  position: relative;
  overflow: hidden;
  min-height: 258px;
  margin-bottom: 20px;
  border-radius: 24px;
  color: #fff;
  background: radial-gradient(circle at 84% 18%, rgba(60, 196, 162, .28), transparent 25%), linear-gradient(132deg, #10253a, #17415b 55%, #176a62);
  box-shadow: 0 18px 42px rgba(16, 37, 58, .17);
}
.review-hero__glow {
  position: absolute;
  right: -70px;
  bottom: -110px;
  width: 330px;
  height: 330px;
  border: 1px solid rgba(255, 255, 255, .12);
  border-radius: 50%;
  box-shadow: 0 0 0 52px rgba(255, 255, 255, .035), 0 0 0 104px rgba(255, 255, 255, .025);
}
.review-hero__content { position: relative; z-index: 1; padding: 28px 32px 30px; }
.review-back { display: inline-flex; align-items: center; gap: 8px; padding: 0; border: 0; color: rgba(255, 255, 255, .78); background: transparent; font-size: 13px; font-weight: 600; }
.review-back:hover { color: #fff; }
.review-hero__heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; margin-top: 24px; }
.review-eyebrow { display: block; margin-bottom: 7px; color: #7de0c8; font-size: 11px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
.review-hero h1 { margin: 0; color: #fff; font-size: clamp(27px, 3vw, 38px); font-weight: 750; letter-spacing: -.035em; }
.review-hero__heading p { max-width: 660px; margin: 9px 0 0; color: rgba(255, 255, 255, .7); font-size: 15px; }
.review-hero__status { display: flex; flex-direction: column; align-items: flex-end; gap: 5px; min-width: 145px; }
.review-hero__status span, .review-hero__status strong { padding: 6px 11px; border: 1px solid rgba(255, 255, 255, .16); border-radius: 999px; background: rgba(255, 255, 255, .09); backdrop-filter: blur(8px); font-size: 12px; }
.review-hero__status strong { color: #bdf9ea; background: rgba(70, 213, 176, .2); }
.review-hero__location { display: flex; align-items: center; gap: 13px; max-width: 760px; margin-top: 25px; padding: 13px 16px; border: 1px solid rgba(255, 255, 255, .14); border-radius: 15px; background: rgba(7, 26, 41, .3); backdrop-filter: blur(9px); }
.review-location-icon { display: grid; flex: 0 0 40px; width: 40px; height: 40px; place-items: center; border-radius: 12px; color: #bdf9ea; background: rgba(80, 221, 185, .16); font-size: 20px; }
.review-hero__location strong, .review-hero__location span { display: block; }
.review-hero__location strong { font-size: 15px; }
.review-hero__location span { margin-top: 2px; color: rgba(255, 255, 255, .65); font-size: 12px; }

.review-alert { display: flex; align-items: center; gap: 10px; border: 0; border-radius: 14px; box-shadow: 0 10px 24px rgba(26, 48, 71, .08); }
.review-alert > i { font-size: 20px; }
.review-alert > span { flex: 1; }
.review-alert button { border: 0; color: inherit; background: transparent; font-size: 18px; }
.review-overview { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(320px, .8fr); gap: 16px; margin-bottom: 16px; }
.visit-card, .progress-card, .review-workspace { border: 1px solid var(--border); border-radius: 18px; background: #fff; box-shadow: 0 8px 28px rgba(33, 52, 74, .055); }
.visit-card { padding: 21px 23px; }
.visit-card__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; color: var(--muted); font-size: 12px; font-weight: 750; letter-spacing: .06em; text-transform: uppercase; }
.visit-card__head i { color: var(--green); font-size: 22px; }
.visit-card__grid { display: grid; grid-template-columns: 1.5fr 1fr .8fr; gap: 14px; }
.visit-card__grid > div { position: relative; padding-left: 35px; }
.visit-card__grid i { position: absolute; top: 0; left: 0; color: #8aa0b5; font-size: 22px; }
.visit-card__grid span, .visit-card__grid strong { display: block; }
.visit-card__grid span { color: var(--muted); font-size: 11px; }
.visit-card__grid strong { margin-top: 4px; font-size: 13px; }
.progress-card { position: relative; padding: 21px 23px; background: linear-gradient(135deg, #f8fbff, #f1faf7); }
.progress-card__copy span, .progress-card__copy strong { display: block; }
.progress-card__copy span { color: var(--muted); font-size: 12px; font-weight: 700; text-transform: uppercase; }
.progress-card__copy strong { margin-top: 4px; font-size: 14px; }
.progress-card__value { position: absolute; top: 18px; right: 23px; color: var(--green); font-size: 28px; font-weight: 800; }
.progress-card__track { height: 9px; margin: 19px 0 13px; overflow: hidden; border-radius: 999px; background: #dce8e7; }
.progress-card__track span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #22a184, #47c4a5); transition: width .3s ease; }
.progress-card__legend { display: flex; flex-wrap: wrap; gap: 13px; color: var(--muted); font-size: 11px; }
.progress-card__legend span { display: inline-flex; align-items: center; gap: 5px; }
.legend-dot { width: 7px; height: 7px; border-radius: 50%; }
.legend-dot--ok { background: #22a184; }.legend-dot--issue { background: #e16a54; }.legend-dot--pending { background: #a1afbd; }

.review-metrics { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
.review-metric { display: flex; align-items: center; gap: 13px; padding: 15px 17px; border: 1px solid var(--border); border-radius: 15px; background: #fff; }
.review-metric > i { display: grid; width: 39px; height: 39px; place-items: center; border-radius: 11px; font-size: 20px; }
.review-metric strong, .review-metric span { display: block; }.review-metric strong { font-size: 20px; line-height: 1; }.review-metric span { margin-top: 4px; color: var(--muted); font-size: 11px; }
.review-metric--blue > i { color: #377ac8; background: #edf5ff; }.review-metric--amber > i { color: #c9782c; background: #fff5e7; }.review-metric--violet > i { color: #7663c5; background: #f2efff; }.review-metric--green > i { color: #19866d; background: #e8f7f2; }

.review-workspace { overflow: hidden; }
.review-toolbar { position: sticky; top: 70px; z-index: 4; padding: 18px 20px 0; border-bottom: 1px solid var(--border); background: rgba(255, 255, 255, .97); backdrop-filter: blur(12px); }
.review-toolbar__top { display: flex; align-items: center; justify-content: space-between; gap: 18px; }
.review-search { display: flex; align-items: center; flex: 1; max-width: 620px; height: 44px; padding: 0 13px; border: 1px solid #d9e3ec; border-radius: 12px; background: #f8fafc; transition: .2s; }
.review-search:focus-within { border-color: #58aa96; background: #fff; box-shadow: 0 0 0 3px rgba(31, 138, 112, .11); }
.review-search > i { color: #8193a5; font-size: 20px; }.review-search input { flex: 1; min-width: 0; padding: 0 10px; border: 0; outline: 0; color: var(--text); background: transparent; font-size: 13px; }.review-search button { border: 0; color: #97a6b4; background: transparent; }
.review-toolbar__actions { display: flex; gap: 8px; }
.review-button { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 39px; padding: 0 14px; border: 1px solid transparent; border-radius: 10px; font-size: 12px; font-weight: 750; transition: .18s; }
.review-button:disabled { cursor: not-allowed; opacity: .56; }.review-button--ghost { border-color: #dce5ed; color: #56697b; background: #fff; }.review-button--ghost:hover { border-color: #aebfce; color: var(--navy); }.review-button--primary { color: #fff; background: linear-gradient(135deg, #1c846c, #239b7e); box-shadow: 0 7px 16px rgba(31, 138, 112, .18); }.review-button--primary:hover:not(:disabled) { color: #fff; transform: translateY(-1px); }.review-button--soft { color: #17755f; background: #e5f5f0; }
.unsaved-dot { width: 7px; height: 7px; border: 2px solid #fff; border-radius: 50%; background: #ffd167; }
.review-filter-row { display: flex; align-items: center; gap: 7px; margin-top: 15px; overflow-x: auto; scrollbar-width: none; }.review-filter-row::-webkit-scrollbar, .system-tabs::-webkit-scrollbar { display: none; }
.review-filter-label { margin-right: 3px; color: var(--muted); font-size: 11px; font-weight: 750; text-transform: uppercase; }
.filter-chip { display: inline-flex; align-items: center; gap: 6px; flex: 0 0 auto; padding: 7px 10px; border: 1px solid #dfe7ef; border-radius: 999px; color: #607184; background: #fff; font-size: 11px; font-weight: 650; }.filter-chip span { display: grid; min-width: 19px; height: 19px; place-items: center; padding: 0 5px; border-radius: 999px; background: #eef2f6; font-size: 10px; }.filter-chip.active { border-color: #8cc6b8; color: #146d59; background: #eaf7f3; }.filter-chip--issue.active { border-color: #efad9e; color: #a9402d; background: #fff0ec; }
.system-tabs { display: flex; gap: 4px; margin-top: 14px; overflow-x: auto; scrollbar-width: none; }.system-tabs button { flex: 0 0 auto; padding: 11px 13px 12px; border: 0; border-bottom: 3px solid transparent; color: #728296; background: transparent; font-size: 12px; font-weight: 650; }.system-tabs button.active { border-bottom-color: var(--green); color: #166d59; }

.review-groups { padding: 23px 20px 28px; background: #f7f9fb; }.system-group + .system-group { margin-top: 32px; }
.system-group__header { display: flex; align-items: center; gap: 12px; margin-bottom: 17px; }.system-group__icon { display: grid; width: 42px; height: 42px; place-items: center; border-radius: 12px; color: #fff; background: linear-gradient(135deg, #294b67, #1b796b); box-shadow: 0 7px 15px rgba(28, 75, 94, .17); font-size: 20px; }.system-group__header span { color: #8695a4; font-size: 10px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }.system-group__header h2 { margin: 2px 0 0; color: var(--navy); font-size: 19px; font-weight: 750; }
.subdimension-group + .subdimension-group { margin-top: 22px; }.subdimension-group__title { display: flex; align-items: center; justify-content: space-between; margin: 0 3px 10px; }.subdimension-group__title h3 { margin: 0; color: #596c7e; font-size: 12px; font-weight: 750; letter-spacing: .035em; text-transform: uppercase; }.subdimension-group__title span { color: #95a2af; font-size: 11px; }
.review-item { overflow: hidden; border: 1px solid #e0e7ee; border-left: 4px solid #b9c5cf; border-radius: 15px; background: #fff; box-shadow: 0 5px 16px rgba(34, 54, 75, .04); }.review-item + .review-item { margin-top: 11px; }.review-item--ok { border-left-color: #2da387; }.review-item--issue { border-left-color: #df6b54; }.review-item--na { border-left-color: #8291a1; }
.review-item__head { display: flex; align-items: center; gap: 13px; padding: 16px 17px 12px; }.review-item__number { display: grid; flex: 0 0 31px; width: 31px; height: 31px; place-items: center; border-radius: 9px; color: #64788b; background: #edf2f6; font-size: 11px; font-weight: 800; }.review-item__question { flex: 1; min-width: 0; }.review-item__question span { display: block; margin-bottom: 2px; color: #98a4b0; font-size: 9px; font-weight: 750; letter-spacing: .08em; text-transform: uppercase; }.review-item__question h4 { margin: 0; color: #26394b; font-size: 14px; font-weight: 650; line-height: 1.4; }
.current-status { display: inline-flex; align-items: center; gap: 5px; flex: 0 0 auto; padding: 6px 9px; border-radius: 999px; font-size: 10px; font-weight: 750; }.current-status--pending { color: #6f7f8f; background: #eef2f5; }.current-status--ok { color: #17735d; background: #e5f6f0; }.current-status--issue { color: #aa4432; background: #fff0ec; }.current-status--na { color: #647181; background: #edf0f3; }
.status-selector { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; padding: 0 17px 15px 61px; }.status-selector button { display: flex; align-items: center; justify-content: center; gap: 7px; min-height: 39px; border: 1px solid #dce4eb; border-radius: 10px; color: #6f7f8e; background: #fbfcfd; font-size: 11px; font-weight: 700; transition: .16s; }.status-selector button i { font-size: 17px; }.status-choice--ok.active { border-color: #7bc6b3; color: #16745d; background: #e9f8f3; }.status-choice--issue.active { border-color: #ee9e8d; color: #a83d2a; background: #fff0ec; }.status-choice--na.active { border-color: #aeb9c3; color: #536170; background: #edf1f4; }
.detail-toggle { display: flex; align-items: center; gap: 7px; width: 100%; padding: 10px 17px 10px 61px; border: 0; border-top: 1px solid #edf1f4; color: #697b8c; background: #fbfcfd; text-align: left; font-size: 11px; font-weight: 650; }.detail-toggle > span { display: inline-flex; align-items: center; gap: 4px; margin-left: auto; padding: 4px 7px; border-radius: 999px; color: #576b7c; background: #eaf0f4; }.detail-toggle > span + span { margin-left: 0; }.detail-toggle .detail-toggle__ot { color: #17745e; background: #e6f6f0; }.detail-toggle > i:last-child { font-size: 16px; }
.review-item__details { padding: 18px; border-top: 1px solid #e7edf2; background: #fcfdfd; }.detail-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 13px; }.detail-fields label > span { display: flex; justify-content: space-between; min-height: 18px; margin-bottom: 6px; color: #536779; font-size: 11px; font-weight: 750; }.detail-fields em { padding: 2px 6px; border-radius: 999px; color: #a84532; background: #fff0ec; font-size: 9px; font-style: normal; }.detail-fields textarea { width: 100%; min-height: 82px; resize: vertical; padding: 10px 12px; border: 1px solid #dbe4ec; border-radius: 10px; outline: none; color: var(--text); background: #fff; font-size: 12px; line-height: 1.45; }.detail-fields textarea:focus { border-color: #65ad9c; box-shadow: 0 0 0 3px rgba(31, 138, 112, .1); }.field-required textarea { border-color: #f0b4a8; background: #fffdfc; }

.evidence-panel { margin-top: 15px; padding: 15px; border: 1px solid #dfe8ee; border-radius: 13px; background: #f7fafb; }.evidence-panel__head { display: flex; align-items: center; justify-content: space-between; gap: 14px; }.evidence-panel__head > div { display: flex; align-items: center; gap: 10px; }.evidence-panel__icon { display: grid; width: 35px; height: 35px; place-items: center; border-radius: 10px; color: #276e8b; background: #e4f3f9; font-size: 18px; }.evidence-panel h5 { margin: 0; color: #30485a; font-size: 12px; font-weight: 750; }.evidence-panel p { margin: 2px 0 0; color: #82909d; font-size: 10px; }.evidence-count { flex: 0 0 auto; padding: 5px 8px; border-radius: 999px; color: #557184; background: #e7eef2; font-size: 10px; font-weight: 750; }
.evidence-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(115px, 1fr)); gap: 9px; margin-top: 13px; }.evidence-gallery figure { position: relative; min-width: 0; margin: 0; overflow: hidden; border: 1px solid #d8e2e9; border-radius: 10px; background: #fff; }.evidence-gallery a { position: relative; display: block; height: 104px; overflow: hidden; background: #e9eef2; }.evidence-gallery img { width: 100%; height: 100%; object-fit: cover; transition: transform .22s; }.evidence-gallery a:hover img { transform: scale(1.04); }.evidence-gallery a > span { position: absolute; right: 6px; bottom: 6px; display: grid; width: 25px; height: 25px; place-items: center; border-radius: 7px; color: #fff; background: rgba(18, 39, 57, .72); }.evidence-gallery figcaption { overflow: hidden; padding: 7px 30px 7px 8px; color: #677887; font-size: 9px; text-overflow: ellipsis; white-space: nowrap; }.evidence-gallery figure > button { position: absolute; right: 5px; bottom: 4px; display: grid; width: 24px; height: 24px; place-items: center; border: 0; border-radius: 7px; color: #b34a3b; background: #fff0ec; font-size: 14px; }
.evidence-actions { display: flex; flex-wrap: wrap; gap: 9px; margin-top: 13px; }.evidence-action { display: flex; align-items: center; gap: 9px; flex: 0 1 220px; min-height: 52px; padding: 9px 11px; border: 1px dashed #9bb4c5; border-radius: 10px; color: #456276; background: #fff; cursor: pointer; }.evidence-action:hover { border-color: #4c9c89; color: #176d59; background: #f2fbf8; }.evidence-action--camera { border-style: solid; border-color: #91c8ba; color: #176d59; background: #eef9f6; }.evidence-action.disabled { cursor: wait; opacity: .55; }.evidence-action > i { font-size: 21px; }.evidence-action strong, .evidence-action small { display: block; }.evidence-action strong { font-size: 11px; }.evidence-action small { margin-top: 1px; color: #8795a1; font-size: 9px; font-weight: 500; }.evidence-uploading { display: inline-flex; align-items: center; gap: 7px; padding: 0 8px; color: #547080; font-size: 11px; }
.work-order-action { display: flex; align-items: center; gap: 11px; margin-top: 14px; padding: 13px 14px; border: 1px solid #dae5ed; border-radius: 12px; background: #fff; }.work-order-action__icon { display: grid; flex: 0 0 35px; width: 35px; height: 35px; place-items: center; border-radius: 10px; color: #347492; background: #eaf4f8; font-size: 18px; }.work-order-action > div { flex: 1; min-width: 0; }.work-order-action strong, .work-order-action small { display: block; }.work-order-action strong { color: #385064; font-size: 11px; }.work-order-action small { margin-top: 2px; color: #8695a2; font-size: 9px; }.work-order-action--created { border-color: #b8dfd3; background: #f0faf7; }.work-order-action--created .work-order-action__icon { color: #fff; background: #269177; }
.review-empty { padding: 64px 20px; text-align: center; background: #f8fafb; }.review-empty > i { color: #a5b3bf; font-size: 42px; }.review-empty h2 { margin: 10px 0 5px; color: #3f5365; font-size: 17px; }.review-empty p { margin: 0 0 15px; color: #8493a0; font-size: 12px; }
.mobile-save-bar { position: fixed; z-index: 20; right: 24px; bottom: 20px; display: flex; align-items: center; gap: 20px; padding: 10px 11px 10px 15px; border: 1px solid rgba(255, 255, 255, .12); border-radius: 14px; color: #fff; background: rgba(16, 37, 58, .95); box-shadow: 0 15px 35px rgba(16, 37, 58, .26); backdrop-filter: blur(12px); }.mobile-save-bar > div { display: flex; align-items: center; gap: 7px; }.mobile-save-bar > div span { width: 8px; height: 8px; border-radius: 50%; background: #ffd166; }.mobile-save-bar strong { font-size: 11px; }.mobile-save-bar button { display: inline-flex; align-items: center; gap: 6px; min-height: 36px; padding: 0 13px; border: 0; border-radius: 9px; color: #133c32; background: #7ce0c7; font-size: 11px; font-weight: 800; }.save-bar-enter-active, .save-bar-leave-active { transition: .2s; }.save-bar-enter-from, .save-bar-leave-to { opacity: 0; transform: translateY(12px); }

@media (max-width: 991.98px) {
  .review-overview { grid-template-columns: 1fr; }
  .review-metrics { grid-template-columns: repeat(2, 1fr); }
  .review-toolbar { top: 60px; }
  .review-toolbar__top { align-items: stretch; flex-direction: column; }
  .review-search { max-width: none; }
  .review-toolbar__actions { justify-content: flex-end; }
}

@media (max-width: 767.98px) {
  .dependency-review-page { padding-top: 0; padding-bottom: 82px; }
  .review-hero { min-height: 0; margin-inline: -12px; border-radius: 0 0 22px 22px; }
  .review-hero__content { padding: 22px 18px 24px; }
  .review-hero__heading { margin-top: 20px; }
  .review-hero__heading p { font-size: 13px; }
  .review-hero__status { display: none; }
  .review-hero__location { margin-top: 18px; padding: 11px 12px; }
  .visit-card, .progress-card { padding: 17px; border-radius: 15px; }
  .visit-card__grid { grid-template-columns: 1fr; gap: 14px; }
  .review-metrics { gap: 8px; }
  .review-metric { gap: 9px; padding: 12px; }
  .review-metric > i { width: 34px; height: 34px; }
  .review-workspace { margin-inline: -4px; border-radius: 16px; }
  .review-toolbar { position: relative; top: auto; padding: 14px 13px 0; }
  .review-toolbar__actions { display: grid; grid-template-columns: 42px 42px 1fr; }
  .review-toolbar__actions .review-button--ghost { padding: 0; }
  .review-toolbar__actions .review-button--ghost span { display: none; }
  .review-filter-label { display: none; }
  .review-groups { padding: 18px 11px 24px; }
  .review-item__head { align-items: flex-start; padding: 14px 12px 11px; }
  .review-item__number { flex-basis: 28px; width: 28px; height: 28px; }
  .current-status { display: none; }
  .status-selector { padding: 0 12px 13px; }
  .status-selector button { min-height: 43px; padding: 4px; }
  .detail-toggle { padding: 11px 12px; }
  .detail-toggle > span { margin-left: auto; }
  .detail-toggle > span + span { display: none; }
  .review-item__details { padding: 13px 11px; }
  .detail-fields { grid-template-columns: 1fr; }
  .evidence-panel { padding: 12px; }
  .evidence-panel__head p { display: none; }
  .evidence-gallery { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .evidence-gallery a { height: 120px; }
  .evidence-actions { display: grid; grid-template-columns: 1fr 1fr; }
  .evidence-action { min-width: 0; padding: 9px; }
  .evidence-action small { display: none; }
  .work-order-action { align-items: flex-start; flex-wrap: wrap; }
  .work-order-action .review-button { width: 100%; margin-top: 2px; }
  .mobile-save-bar { right: 12px; bottom: 10px; left: 12px; justify-content: space-between; }
}

@media (max-width: 420px) {
  .review-metric strong { font-size: 17px; }
  .review-metric span, .status-selector button span { font-size: 10px; }
  .evidence-actions { grid-template-columns: 1fr; }
  .evidence-action { flex-basis: auto; }
}
</style>
