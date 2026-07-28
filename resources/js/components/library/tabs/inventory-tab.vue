<script>
import axios from "axios";
import LibraryHelpButton from "../help-button.vue";
import LibraryStatusBadge from "../status-badge.vue";
import LoadingState from "../../ui/loading-state.vue";
import {
  confirmLibraryAction,
  confirmLibraryCancel,
  formatLibraryDate,
  formatLibraryError,
  showLibrarySuccess,
} from "../module-utils";

const emptyForm = () => ({
  id: null,
  biblioteca_obra_id: null,
  code: "",
  barcode: "",
  ingress_date: "",
  origin: "inventario_inicial",
  estimated_value: "",
  biblioteca_ubicacion_id: null,
  physical_location: "",
  physical_state: "bueno",
  availability_status: "disponible",
  registered_by: null,
  photo_urls_text: "",
  observations: "",
  last_inventory_checked_at: "",
  is_active: true,
});

export default {
  components: {
    LibraryHelpButton,
    LibraryStatusBadge,
    LoadingState,
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
      saving: false,
      error: null,
      items: [],
      failedCovers: {},
      summary: {
        active_total: 0,
        checked_this_year: 0,
        pending_check: 0,
        damaged_or_lost: 0,
      },
      pagination: { current_page: 1, total: 0, per_page: 15 },
      filters: {
        search: "",
        biblioteca_obra_id: null,
        physical_state: null,
        availability_status: null,
        biblioteca_ubicacion_id: null,
        physical_location: null,
        only_active: true,
      },
      showModal: false,
      showActionsModal: false,
      actionItem: null,
      selectedHistory: null,
      form: emptyForm(),
    };
  },
  computed: {
    auditPercent() {
      if (!Number(this.summary.active_total)) return 0;
      return Math.min(100, Math.round(Number(this.summary.checked_this_year || 0) / Number(this.summary.active_total) * 100));
    },
    summaryCards() {
      return [
        {
          key: "active",
          label: "Ejemplares activos",
          value: this.summary.active_total,
          detail: "unidades bajo control",
          icon: "bx-barcode",
          tone: "blue",
        },
        {
          key: "checked",
          label: "Revisados este año",
          value: this.summary.checked_this_year,
          detail: `${this.auditPercent}% de cobertura`,
          icon: "bx-check-shield",
          tone: "green",
        },
        {
          key: "pending",
          label: "Pendientes de revisión",
          value: this.summary.pending_check,
          detail: this.summary.pending_check ? "requieren verificación" : "inventario al día",
          icon: "bx-time-five",
          tone: "amber",
        },
        {
          key: "critical",
          label: "Dañados o perdidos",
          value: this.summary.damaged_or_lost,
          detail: this.summary.damaged_or_lost ? "requieren atención" : "sin incidencias",
          icon: "bx-error-circle",
          tone: "red",
        },
      ];
    },
    resultRange() {
      if (!this.pagination.total || !this.items.length) return "Sin resultados";
      const start = (this.pagination.current_page - 1) * this.pagination.per_page + 1;
      const end = Math.min(start + this.items.length - 1, this.pagination.total);
      return `${start}–${end} de ${this.pagination.total}`;
    },
    hasActiveFilters() {
      return Boolean(
        this.filters.search ||
        this.filters.biblioteca_obra_id ||
        this.filters.physical_state ||
        this.filters.availability_status ||
        this.filters.biblioteca_ubicacion_id ||
        !this.filters.only_active
      );
    },
    selectedWork() {
      return (this.catalogs.works || []).find((item) => Number(item.id) === Number(this.form.biblioteca_obra_id)) || null;
    },
  },
  mounted() {
    this.load();
    this.consumeRouteFocus();
  },
  methods: {
    formatLibraryDate,
    async load(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/biblioteca/ejemplares", {
          params: { page, ...this.filters, only_active: this.filters.only_active ? 1 : 0 },
        });
        this.items = response.data.items.data || [];
        this.summary = response.data.summary || this.summary;
        this.pagination = {
          current_page: response.data.items.current_page,
          total: response.data.items.total,
          per_page: response.data.items.per_page,
        };
      } catch (error) {
        this.error = formatLibraryError(error, "No se pudo cargar el inventario de biblioteca.");
      } finally {
        this.loading = false;
      }
    },
    async consumeRouteFocus() {
      if (!this.$route.query.ejemplar) return;
      await this.openEditById(this.$route.query.ejemplar);
    },
    resetFilters() {
      this.filters = {
        search: "",
        biblioteca_obra_id: null,
        physical_state: null,
        availability_status: null,
        biblioteca_ubicacion_id: null,
        physical_location: null,
        only_active: true,
      };
      this.load(1);
    },
    itemLocation(item) {
      return item.ubicacion?.name || item.physical_location || "Sin ubicación asignada";
    },
    coverAvailable(item) {
      return Boolean(item?.obra?.cover_image_url && !this.failedCovers[item.id]);
    },
    markCoverFailed(item) {
      this.failedCovers = { ...this.failedCovers, [item.id]: true };
    },
    buildPayload() {
      return {
        biblioteca_obra_id: this.form.biblioteca_obra_id,
        code: this.form.code,
        barcode: this.form.barcode || null,
        ingress_date: this.form.ingress_date || null,
        origin: this.form.origin,
        estimated_value: this.form.estimated_value || null,
        biblioteca_ubicacion_id: this.form.biblioteca_ubicacion_id || null,
        physical_location: this.form.physical_location || null,
        physical_state: this.form.physical_state,
        availability_status: this.form.availability_status,
        registered_by: this.form.registered_by || null,
        photo_urls: this.form.photo_urls_text.split(",").map((item) => item.trim()).filter(Boolean),
        observations: this.form.observations || null,
        last_inventory_checked_at: this.form.last_inventory_checked_at || null,
        is_active: this.form.is_active,
      };
    },
    openCreate() {
      this.form = emptyForm();
      this.selectedHistory = null;
      this.showModal = true;
    },
    async openEdit(item) {
      await this.openEditById(item.id);
    },
    async openEditById(id) {
      const response = await axios.get(`/api/biblioteca/ejemplares/${id}`);
      const ejemplar = response.data.data;
      this.selectedHistory = ejemplar.movimientos || [];
      this.form = {
        ...emptyForm(),
        id: ejemplar.id,
        biblioteca_obra_id: ejemplar.biblioteca_obra_id,
        code: ejemplar.code,
        barcode: ejemplar.barcode || "",
        ingress_date: ejemplar.ingress_date || "",
        origin: ejemplar.origin,
        estimated_value: ejemplar.estimated_value || "",
        biblioteca_ubicacion_id: ejemplar.biblioteca_ubicacion_id || null,
        physical_location: ejemplar.physical_location || "",
        physical_state: ejemplar.physical_state,
        availability_status: ejemplar.availability_status,
        registered_by: ejemplar.registered_by || null,
        photo_urls_text: (ejemplar.photo_urls || []).join(", "),
        observations: ejemplar.observations || "",
        last_inventory_checked_at: ejemplar.last_inventory_checked_at || "",
        is_active: Boolean(ejemplar.is_active),
      };
      this.showModal = true;
    },
    async save() {
      const confirmed = await confirmLibraryAction({
        title: this.form.id ? "Confirmar edición de ejemplar" : "Confirmar alta de ejemplar",
        text: this.form.id
          ? "Se actualizará el ejemplar y se registrará su trazabilidad."
          : "Se registrará un nuevo ejemplar físico en inventario.",
        confirmButtonText: this.form.id ? "Sí, actualizar" : "Sí, guardar",
      });

      if (!confirmed.isConfirmed) return;

      this.saving = true;
      try {
        const payload = this.buildPayload();
        if (this.form.id) {
          await axios.put(`/api/biblioteca/ejemplares/${this.form.id}`, payload);
        } else {
          await axios.post("/api/biblioteca/ejemplares", payload);
        }
        this.showModal = false;
        this.$emit("refresh-catalogs");
        await this.load(this.pagination.current_page);
        await showLibrarySuccess(this.form.id ? "Ejemplar actualizado correctamente." : "Ejemplar registrado correctamente.");
      } catch (error) {
        this.error = formatLibraryError(error);
      } finally {
        this.saving = false;
      }
    },
    async askNotes(title, text, action) {
      const result = await confirmLibraryAction({
        title,
        text,
        confirmButtonText: "Confirmar",
        icon: "warning",
      });

      if (!result.isConfirmed) return;

      await action({ notes: "" });
    },
    async audit(item) {
      const result = await axios.get(`/api/biblioteca/ejemplares/${item.id}`);
      const ejemplar = result.data.data;
      await axios.post(`/api/biblioteca/ejemplares/${item.id}/audit`, {
        physical_count_status: "verificado",
        physical_location: ejemplar.physical_location,
        physical_state: ejemplar.physical_state,
      });
      await this.load(this.pagination.current_page);
      await showLibrarySuccess("Inventario físico registrado correctamente.");
    },
    async markDamage(item) {
      await this.askNotes("Registrar daño", `Se marcará el ejemplar ${item.code} como dañado.`, async (payload) => {
        await axios.post(`/api/biblioteca/ejemplares/${item.id}/damage`, payload);
        await this.load(this.pagination.current_page);
        await showLibrarySuccess("Daño registrado correctamente.");
      });
    },
    async markLoss(item) {
      await this.askNotes("Registrar pérdida", `Se marcará el ejemplar ${item.code} como perdido.`, async (payload) => {
        await axios.post(`/api/biblioteca/ejemplares/${item.id}/loss`, payload);
        await this.load(this.pagination.current_page);
        await showLibrarySuccess("Pérdida registrada correctamente.");
      });
    },
    async deactivate(item) {
      await this.askNotes("Dar de baja ejemplar", `Se dará de baja el ejemplar ${item.code}.`, async (payload) => {
        await axios.post(`/api/biblioteca/ejemplares/${item.id}/deactivate`, payload);
        await this.load(this.pagination.current_page);
        await showLibrarySuccess("Ejemplar dado de baja correctamente.");
      });
    },
    openActions(item) {
      this.actionItem = item;
      this.showActionsModal = true;
    },
    async runIncident(action) {
      const item = this.actionItem;
      this.showActionsModal = false;
      this.actionItem = null;
      if (!item) return;

      if (action === "damage") await this.markDamage(item);
      if (action === "loss") await this.markLoss(item);
      if (action === "deactivate") await this.deactivate(item);
    },
    async closeModal() {
      const confirmed = await confirmLibraryCancel("los cambios del ejemplar");
      if (confirmed.isConfirmed) this.showModal = false;
    },
  },
};
</script>

<template>
  <div class="inventory-shell">
    <section class="inventory-command">
      <div class="inventory-command__copy">
        <span class="inventory-eyebrow"><i class="bx bx-barcode"></i> CONTROL FÍSICO</span>
        <h3>Inventario claro, ubicación precisa</h3>
        <p>Supervisa cada ejemplar, registra la revisión anual y gestiona incidencias sin perder su trazabilidad.</p>
      </div>
      <div class="inventory-command__actions">
        <div class="audit-progress">
          <div class="audit-progress__ring" :style="{ '--audit-progress': `${auditPercent * 3.6}deg` }">
            <strong>{{ auditPercent }}%</strong>
          </div>
          <div><small>Cobertura anual</small><span>{{ summary.checked_this_year }} de {{ summary.active_total }} revisados</span></div>
        </div>
        <LibraryHelpButton
          title="Ayuda: control de ejemplares"
          text="Aquí se registra cada ejemplar físico, su origen, ubicación, estado, disponibilidad, evidencias y movimientos de inventario."
        />
        <button v-if="catalogs.capabilities?.manage_inventory !== false" type="button" class="inventory-create" @click="openCreate">
          <i class="bx bx-plus"></i><span>Nuevo ejemplar</span>
        </button>
      </div>
    </section>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <section class="inventory-summary" aria-label="Resumen del inventario">
      <article v-for="card in summaryCards" :key="card.key" class="summary-card" :class="`summary-card--${card.tone}`">
        <span class="summary-card__icon"><i class="bx" :class="card.icon"></i></span>
        <div class="summary-card__copy">
          <small>{{ card.label }}</small>
          <strong>{{ card.value }}</strong>
          <span>{{ card.detail }}</span>
        </div>
        <i class="summary-card__accent bx bx-right-up-arrow-circle"></i>
      </article>
    </section>

    <section class="inventory-filters">
      <header class="section-heading">
        <div>
          <span>BUSCAR Y SEGMENTAR</span>
          <h5>Explorar ejemplares</h5>
        </div>
        <button type="button" class="clear-filters" :disabled="!hasActiveFilters" @click="resetFilters">
          <i class="bx bx-reset"></i>Limpiar filtros
        </button>
      </header>
      <div class="row g-3 align-items-end">
        <div class="col-lg-4">
          <label class="form-label">Búsqueda rápida</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bx bx-search"></i></span>
            <BFormInput v-model="filters.search" placeholder="Código, barra, título, autor o ISBN" @keyup.enter="load(1)" />
          </div>
        </div>
        <div class="col-md-6 col-lg-2">
          <label class="form-label">Obra</label>
          <BFormSelect v-model="filters.biblioteca_obra_id" :options="[{ value: null, text: 'Todas las obras' }].concat((catalogs.works || []).map((item) => ({ value: item.id, text: item.title })))" @change="load(1)" />
        </div>
        <div class="col-md-6 col-lg-2">
          <label class="form-label">Estado físico</label>
          <BFormSelect v-model="filters.physical_state" :options="[{ value: null, text: 'Todos' }].concat((catalogs.ejemplar_states || []).map((item) => ({ value: item.value, text: item.label })))" @change="load(1)" />
        </div>
        <div class="col-md-6 col-lg-2">
          <label class="form-label">Disponibilidad</label>
          <BFormSelect v-model="filters.availability_status" :options="[{ value: null, text: 'Todas' }].concat((catalogs.ejemplar_availability_statuses || []).map((item) => ({ value: item.value, text: item.label })))" @change="load(1)" />
        </div>
        <div class="col-md-6 col-lg-2">
          <label class="form-label">Ubicación</label>
          <BFormSelect v-model="filters.biblioteca_ubicacion_id" :options="[{ value: null, text: 'Todas' }].concat((catalogs.locations || []).map((item) => ({ value: item.id, text: `${item.parent?.name ? item.parent.name + ' · ' : ''}${item.name}` })))" @change="load(1)" />
        </div>
        <div class="col-12 filter-footer">
          <BFormCheckbox v-model="filters.only_active" @change="load(1)">Mostrar solamente ejemplares activos</BFormCheckbox>
          <button type="button" class="apply-filters" @click="load(1)"><i class="bx bx-search"></i>Aplicar búsqueda</button>
        </div>
      </div>
    </section>

    <section class="inventory-list">
      <header class="list-heading">
        <div>
          <span class="list-heading__icon"><i class="bx bx-list-check"></i></span>
          <div><strong>{{ pagination.total }} {{ pagination.total === 1 ? "ejemplar" : "ejemplares" }}</strong><small>Mostrando {{ resultRange }}</small></div>
        </div>
        <div class="inventory-legend">
          <span><i class="legend-dot legend-dot--green"></i>Disponible</span>
          <span><i class="legend-dot legend-dot--amber"></i>Revisión pendiente</span>
          <span><i class="legend-dot legend-dot--red"></i>Incidencia</span>
        </div>
      </header>

      <LoadingState v-if="loading" message="Organizando el inventario..." compact />

      <div v-else-if="!items.length" class="inventory-empty">
        <span><i class="bx bx-package"></i></span>
        <h5>No hay ejemplares para estos filtros</h5>
        <p>Prueba con otros criterios o limpia la búsqueda para revisar el inventario completo.</p>
        <button v-if="hasActiveFilters" type="button" @click="resetFilters"><i class="bx bx-reset"></i>Limpiar filtros</button>
      </div>

      <div v-else class="inventory-table-wrap">
        <table class="inventory-table">
          <thead>
            <tr>
              <th>Ejemplar</th>
              <th>Obra asociada</th>
              <th>Ubicación</th>
              <th>Estado físico</th>
              <th>Disponibilidad</th>
              <th>Última revisión</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td>
                <div class="copy-identity">
                  <span class="copy-identity__icon"><i class="bx bx-barcode"></i></span>
                  <div><code>{{ item.code }}</code><small>{{ item.barcode || "Sin código de barra" }}</small></div>
                </div>
              </td>
              <td>
                <div class="inventory-work">
                  <span class="inventory-work__cover">
                    <img v-if="coverAvailable(item)" :src="item.obra.cover_image_url" :alt="`Portada de ${item.obra?.title}`" @error="markCoverFailed(item)" />
                    <i v-else class="bx bx-book-open"></i>
                  </span>
                  <div><strong>{{ item.obra?.title || "Obra no disponible" }}</strong><span>{{ item.obra?.main_author || item.obra?.material_type || "Sin autor informado" }}</span></div>
                </div>
              </td>
              <td>
                <div class="location-cell">
                  <i class="bx bx-map"></i>
                  <div><strong>{{ itemLocation(item) }}</strong><small>{{ item.ubicacion?.code || "Ubicación libre" }}</small></div>
                </div>
              </td>
              <td><LibraryStatusBadge :status="item.physical_state" /></td>
              <td><LibraryStatusBadge :status="item.availability_status" /></td>
              <td>
                <div class="audit-date" :class="{ pending: !item.last_inventory_checked_at }">
                  <i class="bx" :class="item.last_inventory_checked_at ? 'bx-check-circle' : 'bx-time-five'"></i>
                  <div><strong>{{ item.last_inventory_checked_at ? formatLibraryDate(item.last_inventory_checked_at) : "Pendiente" }}</strong><small>{{ item.last_inventory_checked_at ? "Inventario registrado" : "Sin revisión anual" }}</small></div>
                </div>
              </td>
              <td>
                <div v-if="catalogs.capabilities?.manage_inventory !== false" class="inventory-actions">
                  <button type="button" class="inventory-action inventory-action--edit" data-cnsc-action-ignore :aria-label="`Editar ${item.code}`" @click="openEdit(item)">
                    <i class="bx bx-edit-alt"></i><span>Editar</span>
                  </button>
                  <button type="button" class="inventory-action inventory-action--audit" data-cnsc-action-ignore :aria-label="`Verificar ${item.code}`" @click="audit(item)">
                    <i class="bx bx-check-shield"></i><span>Verificar</span>
                  </button>
                  <button type="button" class="inventory-more-button" :aria-label="`Más acciones para ${item.code}`" @click="openActions(item)">
                    <i class="bx bx-dots-horizontal-rounded"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer v-if="pagination.total" class="inventory-pagination">
        <span>{{ resultRange }} ejemplares</span>
        <BPagination v-if="pagination.total > pagination.per_page" v-model="pagination.current_page" :total-rows="pagination.total" :per-page="pagination.per_page" @update:model-value="load" />
      </footer>
    </section>

    <BModal v-model="showActionsModal" title="Gestionar ejemplar" hide-footer centered>
      <div v-if="actionItem" class="incident-sheet">
        <div class="incident-sheet__identity">
          <span><i class="bx bx-barcode"></i></span>
          <div>
            <small>ACCIONES DE INVENTARIO</small>
            <h5>{{ actionItem.code }}</h5>
            <p>{{ actionItem.obra?.title || "Obra no disponible" }}</p>
          </div>
        </div>

        <p class="incident-sheet__intro">Selecciona la incidencia que deseas registrar. Antes de aplicar el cambio se solicitará una confirmación.</p>

        <div class="incident-actions">
          <button type="button" class="incident-action incident-action--warning" @click="runIncident('damage')">
            <span><i class="bx bx-error-circle"></i></span>
            <div><strong>Registrar daño</strong><small>Actualiza el estado físico del ejemplar.</small></div>
            <i class="bx bx-chevron-right"></i>
          </button>
          <button type="button" class="incident-action incident-action--danger" @click="runIncident('loss')">
            <span><i class="bx bx-search-alt-2"></i></span>
            <div><strong>Registrar pérdida</strong><small>Marca la unidad como no localizada.</small></div>
            <i class="bx bx-chevron-right"></i>
          </button>
          <button type="button" class="incident-action incident-action--neutral" @click="runIncident('deactivate')">
            <span><i class="bx bx-archive-out"></i></span>
            <div><strong>Dar de baja</strong><small>Retira el ejemplar del inventario activo.</small></div>
            <i class="bx bx-chevron-right"></i>
          </button>
        </div>

        <button type="button" class="incident-cancel" @click="showActionsModal = false">Cancelar</button>
      </div>
    </BModal>

    <BModal v-model="showModal" size="xl" :title="form.id ? 'Editar ejemplar' : 'Nuevo ejemplar'" hide-footer scrollable>
      <div class="inventory-form-head">
        <span class="inventory-form-head__icon"><i class="bx bx-barcode"></i></span>
        <div>
          <small>FICHA FÍSICA Y TRAZABILIDAD</small>
          <h5>{{ form.code || "Código AVIS automático" }}</h5>
          <p>{{ selectedWork?.title || "Selecciona la obra a la que pertenece este ejemplar." }}</p>
        </div>
        <LibraryHelpButton
          title="Ayuda: formulario de ejemplar"
          text="Aquí se define la unidad física asociada a una obra, su origen, valoración, ubicación, estado material, evidencias y disponibilidad."
        />
      </div>

      <section class="inventory-form-section">
        <header><span>1</span><div><small>Identificación</small><h6>Obra y códigos institucionales</h6></div></header>
        <div class="row g-3">
          <div class="col-md-5"><label class="form-label">Obra asociada *</label><BFormSelect v-model="form.biblioteca_obra_id" :options="(catalogs.works || []).map((item) => ({ value: item.id, text: item.title }))" /></div>
          <div class="col-md-3"><label class="form-label">Código único</label><BFormInput v-model="form.code" placeholder="Automático al guardar" /><small class="form-hint">Se genera con la base AVIS.</small></div>
          <div class="col-md-2"><label class="form-label">Código barra / QR</label><BFormInput v-model="form.barcode" /></div>
          <div class="col-md-2"><label class="form-label">Fecha ingreso</label><BFormInput v-model="form.ingress_date" type="date" /></div>
        </div>
      </section>

      <section class="inventory-form-section">
        <header><span>2</span><div><small>Procedencia y almacenaje</small><h6>Origen, valor y ubicación física</h6></div></header>
        <div class="row g-3">
          <div class="col-md-3"><label class="form-label">Origen</label><BFormSelect v-model="form.origin" :options="(catalogs.ejemplar_origins || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-md-3"><label class="form-label">Valor estimado</label><BFormInput v-model="form.estimated_value" type="number" step="0.01" /></div>
          <div class="col-md-3"><label class="form-label">Sala / estante / repisa</label><BFormSelect v-model="form.biblioteca_ubicacion_id" :options="[{ value: null, text: 'Ubicación libre' }].concat((catalogs.locations || []).map((item) => ({ value: item.id, text: `${item.parent?.name ? item.parent.name + ' · ' : ''}${item.name}` })))" /></div>
          <div v-if="!form.biblioteca_ubicacion_id" class="col-md-3"><label class="form-label">Ubicación histórica</label><BFormInput v-model="form.physical_location" /></div>
          <div class="col-md-3"><label class="form-label">Responsable registro</label><BFormSelect v-model="form.registered_by" :options="[{ value: null, text: 'Sin responsable' }].concat((catalogs.users || []).map((item) => ({ value: item.id, text: item.name })))" /></div>
        </div>
      </section>

      <section class="inventory-form-section">
        <header><span>3</span><div><small>Condición y control</small><h6>Estado, disponibilidad y evidencias</h6></div></header>
        <div class="row g-3">
          <div class="col-md-3"><label class="form-label">Estado físico</label><BFormSelect v-model="form.physical_state" :options="(catalogs.ejemplar_states || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-md-3"><label class="form-label">Disponibilidad</label><BFormSelect v-model="form.availability_status" :options="(catalogs.ejemplar_availability_statuses || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-md-3"><label class="form-label">Último inventario</label><BFormInput v-model="form.last_inventory_checked_at" type="date" /></div>
          <div class="col-md-3 inventory-active-check"><BFormCheckbox v-model="form.is_active">Activo en inventario</BFormCheckbox></div>
          <div class="col-12"><label class="form-label">Fotografías de evidencia</label><BFormInput v-model="form.photo_urls_text" placeholder="Pega una o más URLs separadas por coma" /></div>
          <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="form.observations" rows="3" placeholder="Condición, marcas, reparaciones u otra información relevante" /></div>
        </div>
      </section>

      <section v-if="selectedHistory?.length" class="movement-history">
        <header><span><i class="bx bx-history"></i></span><div><small>TRAZABILIDAD</small><h6>Historial de movimientos</h6></div></header>
        <div class="table-responsive">
          <table>
            <thead><tr><th>Fecha</th><th>Movimiento</th><th>Ubicación</th><th>Estado</th><th>Notas</th></tr></thead>
            <tbody>
              <tr v-for="movement in selectedHistory" :key="movement.id">
                <td>{{ formatLibraryDate(movement.movement_date) }}</td>
                <td>{{ movement.movement_type }}</td>
                <td>{{ movement.new_location || "-" }}</td>
                <td>{{ movement.new_state || "-" }}</td>
                <td>{{ movement.notes || "-" }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <div class="inventory-form-actions">
        <button type="button" class="form-cancel" @click="closeModal">Cancelar</button>
        <button type="button" class="form-save" :disabled="saving" @click="save">
          <span v-if="saving" class="spinner-border spinner-border-sm"></span>
          <i v-else class="bx bx-save"></i>{{ saving ? "Guardando..." : form.id ? "Guardar cambios" : "Registrar ejemplar" }}
        </button>
      </div>
    </BModal>
  </div>
</template>

<style scoped>
.inventory-shell {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  color: #314057;
}

.inventory-command {
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.25rem;
  padding: 1.2rem 1.35rem;
  border: 1px solid #dfe6f2;
  border-radius: 18px;
  background:
    radial-gradient(circle at 94% -20%, rgba(76, 111, 255, .16), transparent 38%),
    linear-gradient(135deg, #fff 0%, #f7f9ff 62%, #eef8f5 100%);
  box-shadow: 0 10px 30px rgba(35, 53, 86, .07);
}

.inventory-command::after {
  content: "";
  position: absolute;
  width: 170px;
  height: 170px;
  right: -82px;
  bottom: -110px;
  border: 1px solid rgba(76, 111, 255, .15);
  border-radius: 50%;
}

.inventory-command__copy {
  position: relative;
  z-index: 1;
  min-width: 0;
}

.inventory-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  color: #4c6fff;
  font-size: .64rem;
  font-weight: 800;
  letter-spacing: .14em;
}

.inventory-command h3 {
  margin: .32rem 0 .2rem;
  color: #26354c;
  font-size: 1.18rem;
}

.inventory-command p {
  max-width: 610px;
  margin: 0;
  color: #78869a;
  font-size: .77rem;
}

.inventory-command__actions {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: .7rem;
  flex: 0 0 auto;
}

.audit-progress {
  display: flex;
  align-items: center;
  gap: .65rem;
  padding: .48rem .68rem;
  border: 1px solid rgba(70, 100, 170, .12);
  border-radius: 13px;
  background: rgba(255, 255, 255, .78);
}

.audit-progress__ring {
  width: 42px;
  height: 42px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: conic-gradient(#3cb58d var(--audit-progress), #e4eaf3 0);
  position: relative;
}

.audit-progress__ring::after {
  content: "";
  position: absolute;
  inset: 5px;
  border-radius: 50%;
  background: #fff;
}

.audit-progress__ring strong {
  position: relative;
  z-index: 1;
  color: #2f6e5b;
  font-size: .62rem;
}

.audit-progress > div:last-child {
  display: flex;
  flex-direction: column;
}

.audit-progress small {
  color: #4a5b73;
  font-size: .67rem;
  font-weight: 800;
}

.audit-progress span {
  color: #8b96a7;
  font-size: .61rem;
}

.inventory-create {
  min-height: 42px;
  padding: .65rem .9rem;
  border: 0;
  border-radius: 11px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: .4rem;
  color: #fff;
  background: linear-gradient(135deg, #405fd2, #5e7af0);
  box-shadow: 0 8px 18px rgba(64, 95, 210, .24);
  font-size: .72rem;
  font-weight: 750;
  white-space: nowrap;
  transition: transform .15s ease, box-shadow .15s ease;
}

.inventory-create:hover {
  transform: translateY(-1px);
  box-shadow: 0 11px 22px rgba(64, 95, 210, .28);
}

.inventory-create i {
  font-size: 1rem;
}

.inventory-summary {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: .8rem;
}

.summary-card {
  position: relative;
  overflow: hidden;
  min-height: 116px;
  padding: .9rem;
  border: 1px solid #e4e9f1;
  border-radius: 16px;
  display: flex;
  align-items: flex-start;
  gap: .7rem;
  background: #fff;
  box-shadow: 0 8px 24px rgba(35, 53, 86, .055);
}

.summary-card::before {
  content: "";
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 3px;
  background: var(--summary-color);
}

.summary-card--blue { --summary-color: #4c6fff; --summary-soft: #edf1ff; }
.summary-card--green { --summary-color: #2ea984; --summary-soft: #e9f8f2; }
.summary-card--amber { --summary-color: #d79a32; --summary-soft: #fff5df; }
.summary-card--red { --summary-color: #d45a67; --summary-soft: #fff0f2; }

.summary-card__icon {
  width: 38px;
  height: 38px;
  flex: 0 0 38px;
  display: grid;
  place-items: center;
  border-radius: 11px;
  color: var(--summary-color);
  background: var(--summary-soft);
  font-size: 1.12rem;
}

.summary-card__copy {
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.summary-card__copy small {
  color: #7d899c;
  font-size: .65rem;
  font-weight: 700;
}

.summary-card__copy strong {
  margin: .12rem 0;
  color: #26354d;
  font-size: 1.45rem;
  line-height: 1.05;
}

.summary-card__copy span {
  color: #98a2b2;
  font-size: .61rem;
}

.summary-card__accent {
  position: absolute;
  right: .65rem;
  bottom: .55rem;
  color: var(--summary-color);
  opacity: .18;
  font-size: 1.15rem;
}

.inventory-filters,
.inventory-list {
  border: 1px solid #e4e9f1;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 9px 28px rgba(35, 53, 86, .055);
}

.inventory-filters {
  padding: 1rem 1.1rem;
}

.section-heading,
.list-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.section-heading {
  margin-bottom: .9rem;
  padding-bottom: .8rem;
  border-bottom: 1px solid #edf0f5;
}

.section-heading > div {
  display: flex;
  flex-direction: column;
}

.section-heading span {
  color: #657ee1;
  font-size: .59rem;
  font-weight: 800;
  letter-spacing: .12em;
}

.section-heading h5 {
  margin: .12rem 0 0;
  color: #2d3b52;
  font-size: .96rem;
}

.clear-filters {
  padding: .4rem .6rem;
  border: 0;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  gap: .32rem;
  color: #66748a;
  background: #f3f5f9;
  font-size: .67rem;
  font-weight: 700;
}

.clear-filters:disabled {
  opacity: .45;
}

.inventory-filters .form-label {
  color: #59677c;
  font-size: .67rem;
  font-weight: 750;
}

.inventory-filters :deep(.form-control),
.inventory-filters :deep(.form-select),
.inventory-filters .input-group-text {
  min-height: 42px;
  border-color: #e0e6ef;
}

.inventory-filters .input-group-text {
  color: #8090a7;
  background: #f7f9fc;
}

.filter-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding-top: .2rem;
}

.filter-footer :deep(.form-check-label) {
  color: #6b788c;
  font-size: .7rem;
}

.apply-filters {
  min-height: 36px;
  padding: .45rem .72rem;
  border: 0;
  border-radius: 9px;
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  color: #fff;
  background: #405fd2;
  font-size: .68rem;
  font-weight: 750;
}

.inventory-list {
  overflow: hidden;
}

.list-heading {
  min-height: 62px;
  padding: .75rem 1rem;
  border-bottom: 1px solid #e8edf4;
}

.list-heading > div:first-child {
  display: flex;
  align-items: center;
  gap: .65rem;
}

.list-heading__icon {
  width: 36px;
  height: 36px;
  display: grid;
  place-items: center;
  border-radius: 10px;
  color: #4c6fff;
  background: #edf1ff;
  font-size: 1.05rem;
}

.list-heading > div:first-child > div {
  display: flex;
  flex-direction: column;
}

.list-heading strong {
  color: #2f3e55;
  font-size: .84rem;
}

.list-heading small {
  color: #929cad;
  font-size: .63rem;
}

.inventory-legend {
  display: flex;
  align-items: center;
  gap: .9rem;
}

.inventory-legend span {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  color: #808b9d;
  font-size: .61rem;
}

.legend-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
}

.legend-dot--green { background: #39b68d; }
.legend-dot--amber { background: #e4a23a; }
.legend-dot--red { background: #d95d6a; }

.inventory-table-wrap {
  overflow-x: auto;
}

.inventory-table {
  width: 100%;
  min-width: 1120px;
  border-collapse: separate;
  border-spacing: 0;
}

.inventory-table th {
  padding: .75rem .85rem;
  border-bottom: 1px solid #e6ebf3;
  color: #7f8b9e;
  background: #f7f9fc;
  font-size: .61rem;
  font-weight: 800;
  letter-spacing: .065em;
  text-transform: uppercase;
  white-space: nowrap;
}

.inventory-table th:last-child {
  position: sticky;
  right: 0;
  z-index: 2;
  background: #f7f9fc;
  box-shadow: -10px 0 18px rgba(34, 49, 76, .045);
}

.inventory-table td {
  padding: .72rem .85rem;
  border-bottom: 1px solid #edf0f5;
  color: #59667a;
  font-size: .7rem;
  vertical-align: middle;
}

.inventory-table td:last-child {
  position: sticky;
  right: 0;
  z-index: 1;
  background: #fff;
  box-shadow: -10px 0 18px rgba(34, 49, 76, .045);
}

.inventory-table tbody tr:last-child td {
  border-bottom: 0;
}

.inventory-table tbody tr {
  transition: background .15s ease;
}

.inventory-table tbody tr:hover,
.inventory-table tbody tr:hover td:last-child {
  background: #fafbff;
}

.copy-identity,
.inventory-work,
.location-cell,
.audit-date {
  display: flex;
  align-items: center;
  gap: .6rem;
}

.copy-identity {
  min-width: 178px;
}

.copy-identity__icon {
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  display: grid;
  place-items: center;
  border-radius: 10px;
  color: #506ed6;
  background: #edf1ff;
  font-size: 1rem;
}

.copy-identity > div,
.inventory-work > div,
.location-cell > div,
.audit-date > div {
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.copy-identity code {
  width: max-content;
  padding: .22rem .38rem;
  border-radius: 6px;
  color: #3d58bd;
  background: #eef2ff;
  font-size: .64rem;
  font-weight: 750;
}

.copy-identity small,
.inventory-work span,
.location-cell small,
.audit-date small {
  margin-top: .15rem;
  color: #96a0b0;
  font-size: .6rem;
}

.inventory-work {
  min-width: 220px;
}

.inventory-work__cover {
  width: 38px;
  height: 52px;
  flex: 0 0 38px;
  overflow: hidden;
  display: grid;
  place-items: center;
  border-radius: 7px;
  color: #8794a8;
  background: #edf1f7;
  font-size: 1.1rem;
}

.inventory-work__cover img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.inventory-work strong,
.location-cell strong,
.audit-date strong {
  max-width: 190px;
  overflow: hidden;
  color: #35445b;
  font-size: .7rem;
  font-weight: 750;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.location-cell {
  min-width: 145px;
}

.location-cell > i {
  color: #8696ac;
  font-size: 1rem;
}

.audit-date {
  min-width: 145px;
}

.audit-date > i {
  width: 30px;
  height: 30px;
  flex: 0 0 30px;
  display: grid;
  place-items: center;
  border-radius: 9px;
  color: #269a77;
  background: #eaf8f3;
  font-size: .95rem;
}

.audit-date.pending > i {
  color: #bf8125;
  background: #fff4df;
}

.inventory-actions {
  width: max-content;
  min-width: 210px;
  margin-left: auto;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: .32rem;
}

.inventory-actions .inventory-action {
  min-width: 76px !important;
  min-height: 32px !important;
  padding: 0 .55rem !important;
  border-radius: 8px !important;
  display: inline-flex !important;
  align-items: center;
  justify-content: center;
  gap: .3rem;
  font-size: .63rem;
  font-weight: 750;
  white-space: nowrap;
  transition: transform .15s ease, background .15s ease, border-color .15s ease;
}

.inventory-action:hover {
  transform: translateY(-1px);
}

.inventory-action--edit {
  border: 1px solid #dbe3f0 !important;
  color: #52627a;
  background: #fff;
}

.inventory-action--edit:hover {
  color: #3f5ec9;
  border-color: #bdcaef !important;
  background: #f5f7ff;
}

.inventory-action--audit {
  border: 1px solid #ccecdf !important;
  color: #258768;
  background: #edf9f5;
}

.inventory-action--audit:hover {
  color: #1d7257;
  background: #e1f5ed;
}

.inventory-more-button {
  width: 32px !important;
  height: 32px !important;
  min-width: 32px !important;
  min-height: 32px !important;
  padding: 0 !important;
  border: 1px solid #e0e6ef !important;
  border-radius: 8px !important;
  display: grid !important;
  place-items: center;
  color: #6f7c90 !important;
  background: #f7f9fc !important;
}

.inventory-more-button:hover {
  color: #4058b7 !important;
  border-color: #cbd5eb !important;
  background: #eef2ff !important;
}

.incident-sheet {
  display: flex;
  flex-direction: column;
  gap: .9rem;
}

.incident-sheet__identity {
  display: flex;
  align-items: center;
  gap: .8rem;
  padding: .85rem;
  border: 1px solid #e2e8f2;
  border-radius: 14px;
  background: linear-gradient(135deg, #f8faff, #f3f8f6);
}

.incident-sheet__identity > span {
  width: 42px;
  height: 42px;
  flex: 0 0 42px;
  display: grid;
  place-items: center;
  border-radius: 12px;
  color: #4966d7;
  background: #e9eeff;
  font-size: 1.2rem;
}

.incident-sheet__identity small {
  display: block;
  margin-bottom: .12rem;
  color: #8793a5;
  font-size: .59rem;
  font-weight: 800;
  letter-spacing: .1em;
}

.incident-sheet__identity h5 {
  margin: 0;
  color: #29384f;
  font-size: .92rem;
}

.incident-sheet__identity p,
.incident-sheet__intro {
  margin: 0;
  color: #78869a;
  font-size: .7rem;
}

.incident-sheet__identity p {
  margin-top: .12rem;
}

.incident-actions {
  display: grid;
  gap: .55rem;
}

.incident-action {
  display: grid;
  grid-template-columns: 40px minmax(0, 1fr) 22px;
  align-items: center;
  gap: .7rem;
  width: 100%;
  padding: .72rem .78rem;
  border: 1px solid #e2e7ef !important;
  border-radius: 12px !important;
  text-align: left;
  background: #fff;
  transition: transform .15s ease, border-color .15s ease, background .15s ease;
}

.incident-action:hover {
  transform: translateY(-1px);
}

.incident-action > span {
  width: 38px;
  height: 38px;
  display: grid;
  place-items: center;
  border-radius: 10px;
  font-size: 1.05rem;
}

.incident-action div {
  min-width: 0;
}

.incident-action strong,
.incident-action small {
  display: block;
}

.incident-action strong {
  color: #34435a;
  font-size: .75rem;
}

.incident-action small {
  margin-top: .1rem;
  color: #8793a5;
  font-size: .64rem;
}

.incident-action > .bx-chevron-right {
  color: #a0aaba;
  font-size: 1.15rem;
}

.incident-action--warning:hover {
  border-color: #f1d7a0 !important;
  background: #fffbf2;
}

.incident-action--warning > span {
  color: #c68119;
  background: #fff3d9;
}

.incident-action--danger:hover {
  border-color: #f2c7ce !important;
  background: #fff8f9;
}

.incident-action--danger > span {
  color: #cf5362;
  background: #ffeaed;
}

.incident-action--neutral:hover {
  border-color: #d3d9e2 !important;
  background: #f8f9fb;
}

.incident-action--neutral > span {
  color: #5e6b7e;
  background: #edf0f4;
}

.incident-cancel {
  align-self: center;
  min-height: 36px;
  padding: .45rem 1.15rem;
  border: 1px solid #dfe5ee !important;
  border-radius: 9px !important;
  color: #68768a;
  background: #fff;
  font-size: .7rem;
  font-weight: 750;
}

.incident-cancel:hover {
  color: #46566d;
  background: #f5f7fa;
}

.inventory-empty {
  padding: 3rem 1.5rem;
  text-align: center;
}

.inventory-empty > span {
  width: 62px;
  height: 62px;
  margin: 0 auto .9rem;
  display: grid;
  place-items: center;
  border-radius: 18px;
  color: #5871d2;
  background: #edf1ff;
  font-size: 1.8rem;
}

.inventory-empty h5 {
  margin-bottom: .3rem;
  color: #334158;
}

.inventory-empty p {
  margin-bottom: 1rem;
  color: #8792a3;
  font-size: .74rem;
}

.inventory-empty button {
  padding: .5rem .72rem;
  border: 1px solid #d6def3;
  border-radius: 9px;
  color: #405ec6;
  background: #f6f8ff;
  font-size: .68rem;
  font-weight: 750;
}

.inventory-pagination {
  min-height: 54px;
  padding: .6rem 1rem;
  border-top: 1px solid #e8edf4;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  color: #8792a4;
  font-size: .66rem;
}

.inventory-pagination :deep(.pagination) {
  margin: 0;
}

.inventory-form-head {
  display: flex;
  align-items: center;
  gap: .85rem;
  padding: .9rem 1rem;
  border: 1px solid #dfe7f3;
  border-radius: 15px;
  background: linear-gradient(135deg, #f1f5ff, #f5fbf9);
}

.inventory-form-head__icon {
  width: 44px;
  height: 44px;
  flex: 0 0 44px;
  display: grid;
  place-items: center;
  border-radius: 12px;
  color: #4c6fff;
  background: #fff;
  box-shadow: 0 5px 14px rgba(49, 72, 121, .09);
  font-size: 1.3rem;
}

.inventory-form-head > div {
  min-width: 0;
}

.inventory-form-head small {
  color: #5c75d5;
  font-size: .58rem;
  font-weight: 800;
  letter-spacing: .1em;
}

.inventory-form-head h5 {
  margin: .08rem 0;
  color: #2f3e55;
  font-size: .95rem;
}

.inventory-form-head p {
  margin: 0;
  color: #8190a4;
  font-size: .68rem;
}

.inventory-form-head > :last-child {
  margin-left: auto;
}

.inventory-form-section,
.movement-history {
  margin-top: .8rem;
  padding: 1rem;
  border: 1px solid #e4e9f1;
  border-radius: 15px;
}

.inventory-form-section > header,
.movement-history > header {
  margin-bottom: .9rem;
  display: flex;
  align-items: center;
  gap: .6rem;
}

.inventory-form-section > header > span,
.movement-history > header > span {
  width: 30px;
  height: 30px;
  flex: 0 0 30px;
  display: grid;
  place-items: center;
  border-radius: 9px;
  color: #4c6fff;
  background: #edf1ff;
  font-size: .72rem;
  font-weight: 800;
}

.inventory-form-section > header > div,
.movement-history > header > div {
  display: flex;
  flex-direction: column;
}

.inventory-form-section header small,
.movement-history header small {
  color: #909bab;
  font-size: .56rem;
  font-weight: 800;
  letter-spacing: .08em;
  text-transform: uppercase;
}

.inventory-form-section header h6,
.movement-history header h6 {
  margin: .04rem 0 0;
  color: #344258;
  font-size: .78rem;
}

.inventory-form-section .form-label {
  color: #5a687d;
  font-size: .65rem;
  font-weight: 750;
}

.form-hint {
  display: block;
  margin-top: .2rem;
  color: #929dae;
  font-size: .58rem;
}

.inventory-active-check {
  display: flex;
  align-items: center;
  padding-top: 1.45rem;
}

.movement-history {
  background: #fbfcfe;
}

.movement-history table {
  width: 100%;
  border-collapse: collapse;
}

.movement-history th {
  padding: .5rem;
  border-bottom: 1px solid #e1e6ee;
  color: #8792a3;
  font-size: .59rem;
  text-transform: uppercase;
}

.movement-history td {
  padding: .55rem .5rem;
  border-bottom: 1px solid #edf0f4;
  color: #59677b;
  font-size: .64rem;
}

.inventory-form-actions {
  display: flex;
  justify-content: flex-end;
  gap: .5rem;
  margin-top: 1rem;
}

.inventory-form-actions button {
  min-height: 40px;
  padding: .55rem .85rem;
  border-radius: 9px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: .35rem;
  font-size: .7rem;
  font-weight: 750;
}

.form-cancel {
  border: 1px solid #dfe4ec;
  color: #667489;
  background: #fff;
}

.form-save {
  border: 1px solid #405fd2;
  color: #fff;
  background: #405fd2;
  box-shadow: 0 6px 14px rgba(64, 95, 210, .2);
}

.form-save:disabled {
  opacity: .65;
}

@media (max-width: 1100px) {
  .inventory-command {
    align-items: flex-start;
    flex-direction: column;
  }

  .inventory-command__actions {
    width: 100%;
  }

  .inventory-summary {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 767px) {
  .inventory-command {
    padding: 1rem;
  }

  .inventory-command__actions {
    align-items: stretch;
    flex-wrap: wrap;
  }

  .audit-progress {
    flex: 1 1 180px;
  }

  .inventory-create {
    flex: 1 1 150px;
  }

  .section-heading,
  .list-heading {
    align-items: flex-start;
    flex-direction: column;
  }

  .inventory-legend {
    width: 100%;
    flex-wrap: wrap;
  }

  .filter-footer {
    align-items: stretch;
    flex-direction: column;
  }

  .apply-filters {
    justify-content: center;
  }

  .inventory-pagination {
    align-items: flex-start;
    flex-direction: column;
  }

  .inventory-form-head {
    align-items: flex-start;
    flex-wrap: wrap;
  }

  .inventory-form-head > :last-child {
    margin-left: 0;
  }

  .inventory-active-check {
    padding-top: 0;
  }
}

@media (max-width: 430px) {
  .inventory-summary {
    grid-template-columns: 1fr;
  }

  .summary-card {
    min-height: 92px;
  }

  .audit-progress {
    width: 100%;
    flex-basis: 100%;
  }

  .inventory-create {
    width: 100%;
    flex-basis: 100%;
  }

  .inventory-form-actions {
    display: grid;
    grid-template-columns: 1fr;
  }
}
</style>
