<script>
import axios from "axios";
import Swal from "sweetalert2";
import InformaticaHelpButton from "../help-button.vue";
import InformaticaStatusBadge from "../status-badge.vue";
import LoadingState from "../../ui/loading-state.vue";
import {
  confirmInformaticaAction,
  confirmInformaticaCancel,
  formatCurrency,
  formatInformaticaDate,
  formatInformaticaDateTime,
  formatInformaticaError,
  humanizeInformaticaStatus,
  normalizeOptions,
  showInformaticaError,
  showInformaticaSuccess,
} from "../module-utils";

const emptyForm = () => ({
  id: null,
  source_mode: "inventory",
  inventory_item_id: null,
  create_inventory_item: false,
  internal_code: "",
  equipment_type: "notebook",
  brand: "",
  model: "",
  serial_number: "",
  status: "disponible",
  location_name: "",
  responsible_user_id: null,
  responsible_name: "",
  acquisition_date: "",
  reference_value: "",
  observations: "",
  active: true,
  photo: null,
  photo_url: null,
});

const emptyUploadForm = () => ({
  file: null,
  category: "documento",
  notes: "",
});

export default {
  components: {
    InformaticaHelpButton,
    InformaticaStatusBadge,
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
      uploading: false,
      error: null,
      items: [],
      summary: {
        total: 0,
        available: 0,
        loaned: 0,
        maintenance: 0,
        damaged: 0,
        decommissioned: 0,
      },
      pagination: { current_page: 1, total: 0, per_page: 15 },
      filters: {
        search: "",
        equipment_type: null,
        status: null,
        brand: null,
        location_name: null,
        responsible: "",
        internal_code: "",
        serial_number: "",
        with_inactive: false,
      },
      showModal: false,
      showDetailModal: false,
      form: emptyForm(),
      selectedItem: null,
      uploadForm: emptyUploadForm(),
    };
  },
  computed: {
    capabilities() {
      return this.catalogs.capabilities || {};
    },
    equipmentTypeOptions() {
      return normalizeOptions(this.catalogs.equipment_types || [], true).map((item) => ({ value: item.value, text: item.label }));
    },
    equipmentStatusOptions() {
      return normalizeOptions(this.catalogs.equipment_statuses || [], true).map((item) => ({ value: item.value, text: item.label }));
    },
    attachmentCategoryOptions() {
      return normalizeOptions(this.catalogs.attachment_categories || []).map((item) => ({ value: item.value, text: item.label }));
    },
    userOptions() {
      return [{ value: null, text: "Sin usuario vinculado" }].concat(
        (this.catalogs.users || []).map((item) => ({
          value: item.id,
          text: `${item.name}${item.email ? ` · ${item.email}` : ""}`,
        }))
      );
    },
    inventoryAssetOptions() {
      return [{ value: null, text: "Selecciona un activo tecnológico" }].concat(
        (this.catalogs.inventory_assets || []).map((item) => ({
          value: item.id,
          text: `${item.code} · ${item.name}${item.serial_number ? ` · S/N ${item.serial_number}` : ""}`,
        }))
      );
    },
    summaryCards() {
      return [
        { label: "Total", value: this.summary.total, help: "Cantidad total de equipos encontrados con el filtro actual." },
        { label: "Disponibles", value: this.summary.available, help: "Equipos listos para préstamo o uso inmediato." },
        { label: "Prestados", value: this.summary.loaned, help: "Equipos con préstamo activo o atrasado." },
        { label: "En mantención", value: this.summary.maintenance, help: "Equipos actualmente comprometidos en mantención." },
        { label: "Dañados", value: this.summary.damaged, help: "Equipos marcados como dañados." },
        { label: "Baja", value: this.summary.decommissioned, help: "Equipos dados de baja." },
      ];
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    formatCurrency,
    formatInformaticaDate,
    formatInformaticaDateTime,
    humanizeInformaticaStatus,
    normalizeOptions,
    async load(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/informatica/equipos", {
          params: {
            page,
            ...this.filters,
            with_inactive: this.filters.with_inactive ? 1 : 0,
          },
        });
        this.items = response.data.items.data || [];
        this.summary = response.data.summary || this.summary;
        this.pagination = {
          current_page: response.data.items.current_page,
          total: response.data.items.total,
          per_page: response.data.items.per_page,
        };
      } catch (error) {
        this.handleError(error, "No se pudo cargar el listado de equipos.");
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.filters = {
        search: "",
        equipment_type: null,
        status: null,
        brand: null,
        location_name: null,
        responsible: "",
        internal_code: "",
        serial_number: "",
        with_inactive: false,
      };
      this.load();
    },
    openCreate(mode = "inventory") {
      this.form = emptyForm();
      this.form.source_mode = mode;
      this.form.create_inventory_item = mode === "new";
      this.showModal = true;
    },
    selectSourceMode(mode) {
      this.form.source_mode = mode;
      this.form.create_inventory_item = mode === "new";
      this.form.inventory_item_id = null;
      if (mode === "new") {
        this.form.internal_code = "";
      }
    },
    applyInventoryAsset() {
      const item = (this.catalogs.inventory_assets || []).find((asset) => asset.id === this.form.inventory_item_id);
      if (!item) return;

      const typeMap = {
        computadores: "desktop", notebooks: "notebook", tablets: "tablet", impresoras: "printer",
        proyectores: "projector", routers: "router", camaras: "camera", monitores: "monitor",
        perifericos: "other", amplificacion: "audio_equipment", microfonos: "audio_equipment",
        parlantes: "speaker", pantallas: "monitor",
      };
      this.form.internal_code = item.code || "";
      this.form.equipment_type = typeMap[item.subcategory?.slug] || "other";
      this.form.brand = item.brand || "";
      this.form.model = item.model || "";
      this.form.serial_number = item.serial_number || "";
      this.form.acquisition_date = String(item.purchase_date || "").slice(0, 10);
      this.form.reference_value = item.purchase_value || "";
      this.form.responsible_user_id = item.responsible_user_id || null;
      this.form.responsible_name = item.responsible_user?.name || "";
      this.form.location_name = item.dependency?.name || "";
      this.form.photo_url = item.image_url || null;
    },
    async openEdit(item) {
      try {
        const response = await axios.get(`/api/informatica/equipos/${item.id}`);
        this.fillForm(response.data.data);
        this.showModal = true;
      } catch (error) {
        this.handleError(error, "No se pudo cargar la ficha del equipo.");
      }
    },
    async openDetail(item) {
      try {
        const response = await axios.get(`/api/informatica/equipos/${item.id}`);
        this.selectedItem = response.data.data;
        this.uploadForm = emptyUploadForm();
        this.showDetailModal = true;
      } catch (error) {
        this.handleError(error, "No se pudo cargar el detalle del equipo.");
      }
    },
    fillForm(item) {
      this.form = {
        ...emptyForm(),
        id: item.id,
        source_mode: item.inventory_item_id ? "linked" : "legacy",
        inventory_item_id: item.inventory_item_id || null,
        internal_code: item.internal_code || "",
        equipment_type: item.equipment_type || "notebook",
        brand: item.brand || "",
        model: item.model || "",
        serial_number: item.serial_number || "",
        status: item.status || "disponible",
        location_name: item.location_name || "",
        responsible_user_id: item.responsible_user_id || null,
        responsible_name: item.responsible_name || "",
        acquisition_date: String(item.acquisition_date || "").slice(0, 10),
        reference_value: item.reference_value || "",
        observations: item.observations || "",
        active: Boolean(item.active),
        photo: null,
        photo_url: item.photo_url || null,
      };
    },
    onPhotoSelected(event) {
      this.form.photo = event.target.files?.[0] || null;
    },
    onAttachmentSelected(event) {
      this.uploadForm.file = event.target.files?.[0] || null;
    },
    buildFormData() {
      const formData = new FormData();
      Object.entries({
        inventory_item_id: this.form.inventory_item_id || null,
        create_inventory_item: this.form.create_inventory_item ? 1 : 0,
        internal_code: this.form.internal_code,
        equipment_type: this.form.equipment_type,
        brand: this.form.brand || null,
        model: this.form.model || null,
        serial_number: this.form.serial_number || null,
        status: this.form.status,
        location_name: this.form.location_name || null,
        responsible_user_id: this.form.responsible_user_id || null,
        responsible_name: this.form.responsible_name || null,
        acquisition_date: this.form.acquisition_date || null,
        reference_value: this.form.reference_value || null,
        observations: this.form.observations || null,
        active: this.form.active ? 1 : 0,
      }).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== "") {
          formData.append(key, value);
        }
      });

      if (this.form.photo) {
        formData.append("photo", this.form.photo);
      }

      return formData;
    },
    async save() {
      const confirmation = await confirmInformaticaAction({
        title: this.form.id ? "Confirmar edición del equipo" : "Confirmar alta del equipo",
        text: this.form.id
          ? "Se actualizará la ficha del equipo y su trazabilidad."
          : this.form.create_inventory_item
            ? "Se creará el activo en Inventario y su ficha operativa en Informática."
            : "El activo del Inventario quedará vinculado a Informática.",
        confirmButtonText: this.form.id ? "Sí, actualizar" : "Sí, crear",
      });

      if (!confirmation.isConfirmed) return;

      this.saving = true;
      try {
        const payload = this.buildFormData();
        const url = this.form.id ? `/api/informatica/equipos/${this.form.id}` : "/api/informatica/equipos";
        const response = await axios.post(url, payload, {
          headers: { "Content-Type": "multipart/form-data" },
        });
        this.showModal = false;
        this.$emit("refresh-catalogs");
        await this.load(this.pagination.current_page);
        await showInformaticaSuccess(response.data.message || "Equipo guardado correctamente.");
      } catch (error) {
        this.handleError(error);
      } finally {
        this.saving = false;
      }
    },
    async changeStatus(item) {
      const options = Object.fromEntries((this.catalogs.equipment_statuses || []).map((status) => [status.value, status.label]));
      const result = await axios.get(`/api/informatica/equipos/${item.id}`);
      const equipment = result.data.data;

      const prompt = await Swal.fire({
        title: `Cambiar estado de ${equipment.internal_code}`,
        html: `
          <select id="informatica-status" class="swal2-select">
            ${Object.entries(options)
              .map(([value, label]) => `<option value="${value}" ${value === equipment.status ? "selected" : ""}>${label}</option>`)
              .join("")}
          </select>
          <textarea id="informatica-notes" class="swal2-textarea" placeholder="Observaciones del cambio"></textarea>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: "Guardar cambio",
        cancelButtonText: "Cancelar",
        preConfirm: () => ({
          status: document.getElementById("informatica-status")?.value,
          notes: document.getElementById("informatica-notes")?.value || null,
        }),
      });

      if (!prompt.isConfirmed || !prompt.value?.status) return;

      const confirmation = await confirmInformaticaAction({
        title: "Confirmar cambio de estado",
        text: `El equipo quedará como ${humanizeInformaticaStatus(prompt.value.status)}.`,
        confirmButtonText: "Sí, cambiar",
        icon: "warning",
      });

      if (!confirmation.isConfirmed) return;

      try {
        await axios.post(`/api/informatica/equipos/${item.id}/status`, {
          status: prompt.value.status,
          notes: prompt.value.notes,
          active: prompt.value.status !== "dado_de_baja",
        });
        this.$emit("refresh-catalogs");
        await this.load(this.pagination.current_page);
        await showInformaticaSuccess("Estado del equipo actualizado correctamente.");
      } catch (error) {
        this.handleError(error);
      }
    },
    async destroy(item) {
      const confirmation = await confirmInformaticaAction({
        title: "Confirmar eliminación lógica",
        text: `Se intentará eliminar lógicamente el equipo ${item.internal_code}. Si tiene historial, el sistema lo impedirá y deberás usar cambio de estado.`,
        confirmButtonText: "Sí, eliminar",
        icon: "warning",
      });

      if (!confirmation.isConfirmed) return;

      try {
        await axios.delete(`/api/informatica/equipos/${item.id}`);
        this.$emit("refresh-catalogs");
        await this.load(this.pagination.current_page);
        await showInformaticaSuccess("Equipo eliminado lógicamente correctamente.");
      } catch (error) {
        this.handleError(error);
      }
    },
    async uploadAttachment() {
      if (!this.selectedItem || !this.uploadForm.file) {
        await showInformaticaError("Selecciona un archivo antes de cargar el adjunto.");
        return;
      }

      this.uploading = true;
      try {
        const payload = new FormData();
        payload.append("document", this.uploadForm.file);
        payload.append("category", this.uploadForm.category);
        if (this.uploadForm.notes) {
          payload.append("notes", this.uploadForm.notes);
        }

        await axios.post(`/api/informatica/equipos/${this.selectedItem.id}/attachments`, payload, {
          headers: { "Content-Type": "multipart/form-data" },
        });

        this.uploadForm = emptyUploadForm();
        await this.openDetail(this.selectedItem);
        await showInformaticaSuccess("Adjunto cargado correctamente.");
      } catch (error) {
        this.handleError(error, "No se pudo cargar el adjunto.");
      } finally {
        this.uploading = false;
      }
    },
    attachmentUrl(attachment) {
      return `/api/informatica/adjuntos/${attachment.id}/download`;
    },
    attachmentUploader(attachment) {
      if (attachment?.uploaded_by?.name) {
        return attachment.uploaded_by.name;
      }

      if (attachment?.uploadedBy?.name) {
        return attachment.uploadedBy.name;
      }

      return typeof attachment?.uploaded_by === "number" ? `Usuario #${attachment.uploaded_by}` : "-";
    },
    async closeModal() {
      const confirmation = await confirmInformaticaCancel("los cambios del equipo");
      if (confirmation.isConfirmed) {
        this.showModal = false;
      }
    },
    handleError(error, fallback = "No se pudo completar la operación solicitada.") {
      this.error = formatInformaticaError(error, fallback);
      showInformaticaError(this.error);
    },
  },
};
</script>

<template>
  <div class="d-flex flex-column gap-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="fw-semibold">Gestión de equipos tecnológicos</div>
      <div class="d-flex gap-2 flex-wrap">
        <InformaticaHelpButton
          title="Ayuda: gestión de equipos"
          text="Aquí se administran los equipos informáticos con alta, edición, estado, baja lógica, responsables, fotografía, filtros y trazabilidad completa."
        />
        <BButton v-if="capabilities.can_create_equipment" variant="outline-primary" @click="openCreate('inventory')"><i class="bx bx-link me-1"></i>Agregar desde inventario</BButton>
        <BButton v-if="capabilities.can_create_equipment" variant="primary" @click="openCreate('new')"><i class="bx bx-plus me-1"></i>Crear activo tecnológico</BButton>
      </div>
    </div>

    <div class="row g-3">
      <div v-for="card in summaryCards" :key="card.label" class="col-md-4 col-xl-2">
        <BCard class="border-0 shadow-sm h-100">
          <div class="d-flex justify-content-between gap-2">
            <div>
              <div class="small text-muted">{{ card.label }}</div>
              <div class="display-6 fw-semibold">{{ card.value }}</div>
            </div>
            <InformaticaHelpButton :title="card.label" :text="card.help" />
          </div>
        </BCard>
      </div>
    </div>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <BCard class="border-0 shadow-sm">
      <template #header>
        <div class="d-flex justify-content-between align-items-center gap-2">
          <div class="fw-semibold">Filtros del inventario informático</div>
          <InformaticaHelpButton
            title="Ayuda: filtros de equipos"
            text="Puedes buscar por código, tipo, estado, marca, ubicación, responsable o serie para localizar rápidamente una ficha de equipo."
          />
        </div>
      </template>
      <div class="row g-3 align-items-end">
        <div class="col-md-3"><label class="form-label">Búsqueda general</label><BFormInput v-model="filters.search" placeholder="Código, marca, modelo, responsable..." @keyup.enter="load" /></div>
        <div class="col-md-2"><label class="form-label">Tipo</label><BFormSelect v-model="filters.equipment_type" :options="equipmentTypeOptions" /></div>
        <div class="col-md-2"><label class="form-label">Estado</label><BFormSelect v-model="filters.status" :options="equipmentStatusOptions" /></div>
        <div class="col-md-2"><label class="form-label">Marca</label><BFormSelect v-model="filters.brand" :options="[{ value: null, text: 'Todas' }].concat((catalogs.brands || []).map((item) => ({ value: item, text: item })))" /></div>
        <div class="col-md-3"><label class="form-label">Ubicación</label><BFormSelect v-model="filters.location_name" :options="[{ value: null, text: 'Todas' }].concat((catalogs.locations || []).map((item) => ({ value: item, text: item })))" /></div>
        <div class="col-md-3"><label class="form-label">Responsable</label><BFormInput v-model="filters.responsible" placeholder="Nombre del responsable" @keyup.enter="load" /></div>
        <div class="col-md-2"><label class="form-label">Código interno</label><BFormInput v-model="filters.internal_code" @keyup.enter="load" /></div>
        <div class="col-md-2"><label class="form-label">N° serie</label><BFormInput v-model="filters.serial_number" @keyup.enter="load" /></div>
        <div class="col-md-2 d-flex align-items-center"><BFormCheckbox v-model="filters.with_inactive">Incluir inactivos</BFormCheckbox></div>
        <div class="col-md-3 d-flex gap-2">
          <BButton variant="secondary" @click="load">Filtrar</BButton>
          <BButton variant="light" @click="resetFilters">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BCard class="border-0 shadow-sm">
      <LoadingState v-if="loading" message="Cargando equipos..." compact />
      <BTable
        v-else
        responsive
        :items="items"
        :fields="[
          { key: 'internal_code', label: 'Equipo' },
          { key: 'equipment_type', label: 'Tipo' },
          { key: 'location_name', label: 'Ubicación' },
          { key: 'responsible_name', label: 'Responsable' },
          { key: 'status', label: 'Estado' },
          { key: 'active', label: 'Activo' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #cell(internal_code)="{ item }">
          <div class="fw-semibold">{{ item.internal_code }}</div>
          <div class="small text-muted">{{ [item.brand, item.model].filter(Boolean).join(" ") || "Sin marca/modelo" }}</div>
        </template>
        <template #cell(equipment_type)="{ item }">{{ humanizeInformaticaStatus(item.equipment_type) }}</template>
        <template #cell(responsible_name)="{ item }">{{ item.responsible_name || item.responsible_user?.name || "-" }}</template>
        <template #cell(status)="{ item }"><InformaticaStatusBadge :status="item.status" /></template>
        <template #cell(active)="{ item }">
          <BBadge :variant="item.active ? 'success' : 'secondary'">{{ item.active ? "Sí" : "No" }}</BBadge>
        </template>
        <template #cell(actions)="{ item }">
          <div class="d-flex flex-wrap gap-2">
            <BButton size="sm" variant="outline-info" @click="openDetail(item)">Detalle</BButton>
            <BButton v-if="capabilities.can_edit_equipment" size="sm" variant="outline-primary" @click="openEdit(item)">Editar</BButton>
            <BButton v-if="capabilities.can_edit_equipment" size="sm" variant="outline-warning" @click="changeStatus(item)">Estado</BButton>
            <BButton v-if="capabilities.can_delete_equipment" size="sm" variant="outline-danger" @click="destroy(item)">Eliminar</BButton>
          </div>
        </template>
      </BTable>
      <div class="d-flex justify-content-end mt-3">
        <BPagination v-model="pagination.current_page" :total-rows="pagination.total" :per-page="pagination.per_page" @update:model-value="load" />
      </div>
    </BCard>

    <BModal v-model="showModal" size="xl" :title="form.id ? 'Editar equipo informático' : 'Incorporar equipo informático'" hide-footer>
      <div class="d-flex justify-content-between align-items-center mb-3 gap-2">
        <div class="text-muted small">Completa la ficha técnica, responsable, estado y datos de identificación del equipo.</div>
        <InformaticaHelpButton
          title="Ayuda: formulario de equipos"
          text="El código interno debe ser único. También puedes registrar número de serie, responsable habitual, ubicación, fotografía y dejar el equipo activo o inactivo."
        />
      </div>
      <div v-if="!form.id" class="equipment-source-picker mb-4">
        <button type="button" class="equipment-source-option" :class="{ 'is-active': form.source_mode === 'inventory' }" @click="selectSourceMode('inventory')">
          <span class="equipment-source-option__icon"><i class="bx bx-package"></i></span>
          <span><strong>Usar activo del inventario</strong><small>Vincula un equipo tecnológico ya registrado, sin duplicarlo.</small></span>
          <i class="bx bx-check-circle equipment-source-option__check"></i>
        </button>
        <button type="button" class="equipment-source-option" :class="{ 'is-active': form.source_mode === 'new' }" @click="selectSourceMode('new')">
          <span class="equipment-source-option__icon"><i class="bx bx-plus-circle"></i></span>
          <span><strong>Crear activo nuevo</strong><small>Lo registra simultáneamente en Inventario e Informática.</small></span>
          <i class="bx bx-check-circle equipment-source-option__check"></i>
        </button>
      </div>

      <div v-if="!form.id && form.source_mode === 'inventory'" class="inventory-asset-selector mb-4">
        <label class="form-label">Activo tecnológico del inventario</label>
        <BFormSelect v-model="form.inventory_item_id" :options="inventoryAssetOptions" @update:model-value="applyInventoryAsset" />
        <div v-if="!catalogs.inventory_assets?.length" class="small text-warning mt-2"><i class="bx bx-info-circle me-1"></i>No hay activos tecnológicos disponibles para vincular. Puedes crear uno nuevo desde esta misma ventana.</div>
      </div>

      <div v-if="form.id && form.inventory_item_id" class="linked-inventory-notice mb-4">
        <i class="bx bx-link-alt"></i><div><strong>Vinculado con Inventario</strong><small>Los datos principales y el estado se mantienen sincronizados con el activo institucional.</small></div>
      </div>

      <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Código institucional</label><BFormInput v-model="form.internal_code" :disabled="form.create_inventory_item || Boolean(form.inventory_item_id)" :placeholder="form.create_inventory_item ? 'Se generará automáticamente' : ''" /></div>
        <div class="col-md-3"><label class="form-label">Tipo de equipo</label><BFormSelect v-model="form.equipment_type" :options="normalizeOptions(catalogs.equipment_types || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-3"><label class="form-label">Marca</label><BFormInput v-model="form.brand" /></div>
        <div class="col-md-3"><label class="form-label">Modelo</label><BFormInput v-model="form.model" /></div>
        <div class="col-md-3"><label class="form-label">Número de serie</label><BFormInput v-model="form.serial_number" /></div>
        <div class="col-md-3"><label class="form-label">Estado inicial</label><BFormSelect v-model="form.status" :options="normalizeOptions(catalogs.equipment_statuses || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-3"><label class="form-label">Ubicación habitual</label><BFormInput v-model="form.location_name" /></div>
        <div class="col-md-3"><label class="form-label">Responsable habitual (usuario)</label><BFormSelect v-model="form.responsible_user_id" :options="userOptions" /></div>
        <div class="col-md-4"><label class="form-label">Responsable habitual (texto libre)</label><BFormInput v-model="form.responsible_name" placeholder="Usar si no existe un usuario asociado" /></div>
        <div class="col-md-3"><label class="form-label">Fecha adquisición</label><BFormInput v-model="form.acquisition_date" type="date" /></div>
        <div class="col-md-3"><label class="form-label">Valor referencial</label><BFormInput v-model="form.reference_value" type="number" step="1" /></div>
        <div class="col-md-2 d-flex align-items-center"><BFormCheckbox v-model="form.active">Registro activo</BFormCheckbox></div>
        <div class="col-md-6">
          <label class="form-label">Foto referencial</label>
          <BFormFile @change="onPhotoSelected" accept="image/*" />
          <div v-if="form.photo_url" class="mt-2">
            <img :src="form.photo_url" alt="Foto equipo" class="img-thumbnail" style="max-height: 140px" />
          </div>
        </div>
        <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="form.observations" rows="3" /></div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-4">
        <BButton variant="light" @click="closeModal">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving || (!form.id && form.source_mode === 'inventory' && !form.inventory_item_id)" @click="save">{{ saving ? "Guardando..." : form.id ? "Actualizar equipo" : form.create_inventory_item ? "Crear activo y ficha" : "Vincular activo" }}</BButton>
      </div>
    </BModal>

    <BModal v-model="showDetailModal" size="xl" title="Detalle del equipo" hide-footer scrollable>
      <template v-if="selectedItem">
        <div class="d-flex justify-content-between align-items-center mb-3 gap-2">
          <div>
            <div class="fw-semibold">{{ selectedItem.internal_code }}</div>
            <div class="text-muted small">{{ [selectedItem.brand, selectedItem.model].filter(Boolean).join(" ") || "Ficha sin marca/modelo" }}</div>
          </div>
          <InformaticaHelpButton
            title="Ayuda: ficha completa del equipo"
            text="Esta ficha muestra préstamos, mantenciones, adjuntos y cambios de estado para mantener trazabilidad completa del equipo."
          />
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-3"><div class="small text-muted">Tipo</div><div class="fw-semibold">{{ humanizeInformaticaStatus(selectedItem.equipment_type) }}</div></div>
          <div class="col-md-3"><div class="small text-muted">Estado</div><div><InformaticaStatusBadge :status="selectedItem.status" /></div></div>
          <div class="col-md-3"><div class="small text-muted">Ubicación</div><div class="fw-semibold">{{ selectedItem.location_name || "-" }}</div></div>
          <div class="col-md-3"><div class="small text-muted">Responsable</div><div class="fw-semibold">{{ selectedItem.responsible_name || selectedItem.responsible_user?.name || "-" }}</div></div>
          <div class="col-md-3"><div class="small text-muted">Fecha adquisición</div><div class="fw-semibold">{{ formatInformaticaDate(selectedItem.acquisition_date) }}</div></div>
          <div class="col-md-3"><div class="small text-muted">Valor referencial</div><div class="fw-semibold">{{ formatCurrency(selectedItem.reference_value) }}</div></div>
          <div class="col-md-3"><div class="small text-muted">Serie</div><div class="fw-semibold">{{ selectedItem.serial_number || "-" }}</div></div>
          <div class="col-md-3"><div class="small text-muted">Activo</div><div class="fw-semibold">{{ selectedItem.active ? "Sí" : "No" }}</div></div>
          <div class="col-12" v-if="selectedItem.photo_url">
            <img :src="selectedItem.photo_url" alt="Foto equipo" class="img-thumbnail" style="max-height: 180px" />
          </div>
          <div class="col-12"><div class="small text-muted">Observaciones</div><div>{{ selectedItem.observations || "-" }}</div></div>
        </div>

        <BCard class="border-0 bg-light-subtle mb-3">
          <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
            <div class="fw-semibold">Adjuntos del equipo</div>
            <InformaticaHelpButton
              title="Ayuda: adjuntos"
              text="Puedes cargar evidencias, actas, fotografías o documentos del equipo para conservar respaldo técnico o administrativo."
            />
          </div>
          <div class="row g-3 align-items-end">
            <div class="col-md-4"><label class="form-label">Archivo</label><BFormFile @change="onAttachmentSelected" /></div>
            <div class="col-md-3"><label class="form-label">Categoría</label><BFormSelect v-model="uploadForm.category" :options="attachmentCategoryOptions" /></div>
            <div class="col-md-3"><label class="form-label">Notas</label><BFormInput v-model="uploadForm.notes" /></div>
            <div class="col-md-2"><BButton variant="primary" :disabled="uploading" @click="uploadAttachment">{{ uploading ? "Cargando..." : "Subir adjunto" }}</BButton></div>
          </div>
          <BTable
            small
            responsive
            class="mt-3"
            :items="selectedItem.attachments || []"
            :fields="[
              { key: 'original_name', label: 'Archivo' },
              { key: 'category', label: 'Categoría' },
              { key: 'uploaded_by', label: 'Subido por' },
              { key: 'created_at', label: 'Fecha' },
              { key: 'download', label: 'Descarga' },
            ]"
          >
            <template #cell(category)="{ item }">{{ humanizeInformaticaStatus(item.category) }}</template>
            <template #cell(uploaded_by)="{ item }">{{ attachmentUploader(item) }}</template>
            <template #cell(created_at)="{ item }">{{ formatInformaticaDateTime(item.created_at) }}</template>
            <template #cell(download)="{ item }">
              <a :href="attachmentUrl(item)" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">Descargar</a>
            </template>
          </BTable>
        </BCard>

        <div class="row g-3">
          <div class="col-xl-6">
            <BCard class="border-0 shadow-sm h-100">
              <template #header><div class="fw-semibold">Historial de préstamos</div></template>
              <BTable
                small
                responsive
                :items="selectedItem.loans || []"
                :fields="[
                  { key: 'loan_code', label: 'Código' },
                  { key: 'requester_name_snapshot', label: 'Solicitante' },
                  { key: 'borrowed_at', label: 'Préstamo' },
                  { key: 'due_at', label: 'Compromiso' },
                  { key: 'status', label: 'Estado' },
                ]"
              >
                <template #cell(borrowed_at)="{ item }">{{ formatInformaticaDateTime(item.borrowed_at) }}</template>
                <template #cell(due_at)="{ item }">{{ formatInformaticaDateTime(item.due_at) }}</template>
                <template #cell(status)="{ item }"><InformaticaStatusBadge :status="item.status" /></template>
              </BTable>
            </BCard>
          </div>
          <div class="col-xl-6">
            <BCard class="border-0 shadow-sm h-100">
              <template #header><div class="fw-semibold">Historial de mantenciones</div></template>
              <BTable
                small
                responsive
                :items="selectedItem.maintenance_reports || []"
                :fields="[
                  { key: 'maintenance_code', label: 'Código' },
                  { key: 'maintenance_type', label: 'Tipo' },
                  { key: 'maintenance_date', label: 'Fecha' },
                  { key: 'status', label: 'Estado' },
                  { key: 'final_equipment_status', label: 'Estado final' },
                ]"
              >
                <template #cell(maintenance_type)="{ item }">{{ humanizeInformaticaStatus(item.maintenance_type) }}</template>
                <template #cell(maintenance_date)="{ item }">{{ formatInformaticaDate(item.maintenance_date) }}</template>
                <template #cell(status)="{ item }"><InformaticaStatusBadge :status="item.status" /></template>
                <template #cell(final_equipment_status)="{ item }">
                  <InformaticaStatusBadge v-if="item.final_equipment_status" :status="item.final_equipment_status" />
                  <span v-else>-</span>
                </template>
              </BTable>
            </BCard>
          </div>
        </div>

        <BCard class="border-0 shadow-sm mt-3">
          <template #header><div class="fw-semibold">Trazabilidad de estados</div></template>
          <BTable
            small
            responsive
            :items="selectedItem.status_logs || []"
            :fields="[
              { key: 'changed_at', label: 'Fecha' },
              { key: 'previous_status', label: 'Estado anterior' },
              { key: 'new_status', label: 'Estado nuevo' },
              { key: 'source_type', label: 'Origen' },
              { key: 'notes', label: 'Observaciones' },
            ]"
          >
            <template #cell(changed_at)="{ item }">{{ formatInformaticaDateTime(item.changed_at) }}</template>
            <template #cell(previous_status)="{ item }">
              <InformaticaStatusBadge v-if="item.previous_status" :status="item.previous_status" />
              <span v-else>-</span>
            </template>
            <template #cell(new_status)="{ item }"><InformaticaStatusBadge :status="item.new_status" /></template>
            <template #cell(source_type)="{ item }">{{ humanizeInformaticaStatus(item.source_type) }}</template>
          </BTable>
        </BCard>
      </template>
    </BModal>
  </div>
</template>

<style scoped>
.equipment-source-picker { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .8rem; }
.equipment-source-option { position: relative; display: flex; align-items: center; gap: .8rem; padding: 1rem; color: #616b7d; text-align: left; border: 1px solid #e1e5ee; border-radius: 13px; background: #fff; transition: .18s ease; }
.equipment-source-option:hover { border-color: #b8c2f4; background: #f8f9ff; }
.equipment-source-option.is-active { color: #4057d6; border-color: #8fa0f1; background: rgba(85,110,230,.07); box-shadow: inset 0 0 0 1px rgba(85,110,230,.12); }
.equipment-source-option__icon { display: grid; flex: 0 0 42px; height: 42px; place-items: center; border-radius: 11px; background: #f0f2f8; font-size: 1.35rem; }.equipment-source-option.is-active .equipment-source-option__icon { color: #fff; background: #556ee6; }
.equipment-source-option strong, .equipment-source-option small { display: block; }.equipment-source-option strong { color: #273047; font-size: .86rem; }.equipment-source-option small { margin-top: .2rem; font-size: .72rem; }.equipment-source-option__check { margin-left: auto; opacity: 0; font-size: 1.25rem; }.equipment-source-option.is-active .equipment-source-option__check { opacity: 1; }
.inventory-asset-selector { padding: 1rem; border: 1px solid #dce2fa; border-radius: 12px; background: #f8f9ff; }
.linked-inventory-notice { display: flex; align-items: center; gap: .8rem; padding: .85rem 1rem; color: #286a55; border-radius: 11px; background: rgba(52,195,143,.1); }.linked-inventory-notice > i { font-size: 1.45rem; }.linked-inventory-notice strong, .linked-inventory-notice small { display: block; }.linked-inventory-notice small { margin-top: .15rem; color: #648076; }
@media (max-width: 767.98px) { .equipment-source-picker { grid-template-columns: 1fr; } }
</style>
