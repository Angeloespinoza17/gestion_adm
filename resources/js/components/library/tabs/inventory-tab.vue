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
        physical_location: null,
      },
      showModal: false,
      selectedHistory: null,
      form: emptyForm(),
    };
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
          params: { page, ...this.filters },
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
    buildPayload() {
      return {
        biblioteca_obra_id: this.form.biblioteca_obra_id,
        code: this.form.code,
        barcode: this.form.barcode || null,
        ingress_date: this.form.ingress_date || null,
        origin: this.form.origin,
        estimated_value: this.form.estimated_value || null,
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
    async closeModal() {
      const confirmed = await confirmLibraryCancel("los cambios del ejemplar");
      if (confirmed.isConfirmed) this.showModal = false;
    },
  },
};
</script>

<template>
  <div class="d-flex flex-column gap-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="fw-semibold">Control unitario de ejemplares</div>
      <div class="d-flex gap-2">
        <LibraryHelpButton
          title="Ayuda: control de ejemplares"
          text="Aquí se registra cada ejemplar físico, su origen, ubicación, estado, disponibilidad, evidencias y movimientos de inventario."
        />
        <BButton variant="primary" @click="openCreate">Alta de ejemplar</BButton>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-md-3"><BCard class="border-0 shadow-sm"><div class="small text-muted">Ejemplares activos</div><div class="display-6 fw-semibold">{{ summary.active_total }}</div></BCard></div>
      <div class="col-md-3"><BCard class="border-0 shadow-sm"><div class="small text-muted">Revisados este año</div><div class="display-6 fw-semibold">{{ summary.checked_this_year }}</div></BCard></div>
      <div class="col-md-3"><BCard class="border-0 shadow-sm"><div class="small text-muted">Pendientes de revisión</div><div class="display-6 fw-semibold">{{ summary.pending_check }}</div></BCard></div>
      <div class="col-md-3"><BCard class="border-0 shadow-sm"><div class="small text-muted">Dañados o perdidos</div><div class="display-6 fw-semibold">{{ summary.damaged_or_lost }}</div></BCard></div>
    </div>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <BCard class="border-0 shadow-sm">
      <div class="row g-3 align-items-end">
        <div class="col-md-4"><label class="form-label">Buscar</label><BFormInput v-model="filters.search" placeholder="Código, barra, obra..." @keyup.enter="load" /></div>
        <div class="col-md-3"><label class="form-label">Obra</label><BFormSelect v-model="filters.biblioteca_obra_id" :options="[{ value: null, text: 'Todas' }].concat((catalogs.works || []).map((item) => ({ value: item.id, text: item.title })))" /></div>
        <div class="col-md-2"><label class="form-label">Estado físico</label><BFormSelect v-model="filters.physical_state" :options="[{ value: null, text: 'Todos' }].concat((catalogs.ejemplar_states || []).map((item) => ({ value: item.value, text: item.label })))" /></div>
        <div class="col-md-2"><label class="form-label">Disponibilidad</label><BFormSelect v-model="filters.availability_status" :options="[{ value: null, text: 'Todos' }].concat((catalogs.ejemplar_availability_statuses || []).map((item) => ({ value: item.value, text: item.label })))" /></div>
        <div class="col-md-3"><label class="form-label">Ubicación</label><BFormSelect v-model="filters.physical_location" :options="[{ value: null, text: 'Todas' }].concat((catalogs.locations || []).map((item) => ({ value: item, text: item })))" /></div>
        <div class="col-md-3">
          <BButton variant="secondary" class="me-2" @click="load">Filtrar</BButton>
          <BButton variant="light" @click="filters = { search: '', biblioteca_obra_id: null, physical_state: null, availability_status: null, physical_location: null }; load();">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BCard class="border-0 shadow-sm">
      <LoadingState v-if="loading" message="Cargando inventario..." compact />
      <BTable
        v-else
        responsive
        :items="items"
        :fields="[
          { key: 'code', label: 'Ejemplar' },
          { key: 'obra_title', label: 'Obra' },
          { key: 'physical_location', label: 'Ubicación' },
          { key: 'physical_state', label: 'Estado físico' },
          { key: 'availability_status', label: 'Disponibilidad' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #cell(code)="{ item }">
          <div class="fw-semibold">{{ item.code }}</div>
          <div class="small text-muted">{{ item.barcode || "Sin código de barra" }}</div>
        </template>
        <template #cell(obra_title)="{ item }">{{ item.obra?.title || "-" }}</template>
        <template #cell(physical_state)="{ item }"><LibraryStatusBadge :status="item.physical_state" /></template>
        <template #cell(availability_status)="{ item }"><LibraryStatusBadge :status="item.availability_status" /></template>
        <template #cell(actions)="{ item }">
          <div class="d-flex flex-wrap gap-2">
            <BButton size="sm" variant="outline-primary" @click="openEdit(item)">Editar</BButton>
            <BButton size="sm" variant="outline-secondary" @click="audit(item)">Inventario</BButton>
            <BButton size="sm" variant="outline-warning" @click="markDamage(item)">Daño</BButton>
            <BButton size="sm" variant="outline-danger" @click="markLoss(item)">Pérdida</BButton>
            <BButton size="sm" variant="outline-dark" @click="deactivate(item)">Baja</BButton>
          </div>
        </template>
      </BTable>
      <div class="d-flex justify-content-end mt-3">
        <BPagination v-model="pagination.current_page" :total-rows="pagination.total" :per-page="pagination.per_page" @update:model-value="load" />
      </div>
    </BCard>

    <BModal v-model="showModal" size="xl" :title="form.id ? 'Editar ejemplar' : 'Nuevo ejemplar'" hide-footer>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-muted small">Ficha física, disponibilidad y trazabilidad.</div>
        <LibraryHelpButton
          title="Ayuda: formulario de ejemplar"
          text="Aquí se define la unidad física asociada a una obra, su origen, valoración, ubicación, estado material, evidencias y disponibilidad."
        />
      </div>
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Obra asociada</label><BFormSelect v-model="form.biblioteca_obra_id" :options="(catalogs.works || []).map((item) => ({ value: item.id, text: item.title }))" /></div>
        <div class="col-md-2"><label class="form-label">Código único</label><BFormInput v-model="form.code" /></div>
        <div class="col-md-3"><label class="form-label">Código barra / QR</label><BFormInput v-model="form.barcode" /></div>
        <div class="col-md-3"><label class="form-label">Fecha ingreso</label><BFormInput v-model="form.ingress_date" type="date" /></div>
        <div class="col-md-3"><label class="form-label">Origen</label><BFormSelect v-model="form.origin" :options="(catalogs.ejemplar_origins || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-3"><label class="form-label">Valor estimado</label><BFormInput v-model="form.estimated_value" type="number" step="0.01" /></div>
        <div class="col-md-3"><label class="form-label">Ubicación física</label><BFormInput v-model="form.physical_location" /></div>
        <div class="col-md-3"><label class="form-label">Responsable registro</label><BFormSelect v-model="form.registered_by" :options="[{ value: null, text: 'Sin responsable' }].concat((catalogs.users || []).map((item) => ({ value: item.id, text: item.name })))" /></div>
        <div class="col-md-3"><label class="form-label">Estado físico</label><BFormSelect v-model="form.physical_state" :options="(catalogs.ejemplar_states || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-3"><label class="form-label">Disponibilidad</label><BFormSelect v-model="form.availability_status" :options="(catalogs.ejemplar_availability_statuses || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-3"><label class="form-label">Último inventario</label><BFormInput v-model="form.last_inventory_checked_at" type="date" /></div>
        <div class="col-md-3 d-flex align-items-center"><BFormCheckbox v-model="form.is_active">Activo en inventario</BFormCheckbox></div>
        <div class="col-12"><label class="form-label">Fotografías (URLs)</label><BFormInput v-model="form.photo_urls_text" placeholder="Separar por coma" /></div>
        <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="form.observations" rows="3" /></div>
      </div>

      <div v-if="selectedHistory?.length" class="mt-4">
        <div class="fw-semibold mb-2">Historial de movimientos</div>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr><th>Fecha</th><th>Movimiento</th><th>Ubicación</th><th>Estado</th><th>Notas</th></tr>
            </thead>
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
      </div>

      <div class="d-flex justify-content-end gap-2 mt-4">
        <BButton variant="light" @click="closeModal">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">{{ saving ? "Guardando..." : form.id ? "Actualizar" : "Guardar" }}</BButton>
      </div>
    </BModal>
  </div>
</template>
