<script>
import axios from "axios";
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
  showInformaticaWarning,
  toInputDate,
  toInputDateTime,
} from "../module-utils";

const emptyForm = () => ({
  id: null,
  it_equipment_id: null,
  maintenance_date: toInputDate(new Date()),
  maintenance_type: "preventiva",
  technician_user_id: null,
  technician_name: "",
  reason: "",
  diagnosis: "",
  actions_performed: "",
  spare_parts: "",
  cost_amount: "",
  initial_equipment_status: "disponible",
  next_maintenance_at: "",
  observations: "",
  status: "borrador",
  attachment: null,
});

const emptyCloseForm = () => ({
  final_equipment_status: "disponible",
  closed_at: toInputDateTime(),
  observations: "",
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
      closing: false,
      error: null,
      items: [],
      summary: {
        pending: 0,
        closed: 0,
        month_total: 0,
        month_cost: 0,
      },
      pagination: { current_page: 1, total: 0, per_page: 15 },
      filters: {
        search: "",
        status: null,
        maintenance_type: null,
        it_equipment_id: null,
        technician_user_id: null,
        final_equipment_status: null,
        date_from: "",
        date_to: "",
        only_pending: false,
      },
      showFormModal: false,
      showDetailModal: false,
      showCloseModal: false,
      form: emptyForm(),
      closeForm: emptyCloseForm(),
      selectedReport: null,
    };
  },
  computed: {
    capabilities() {
      return this.catalogs.capabilities || {};
    },
    equipmentOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.equipment || []).map((item) => ({
          value: item.id,
          text: `${item.internal_code} · ${[item.brand, item.model].filter(Boolean).join(" ")}`,
        }))
      );
    },
    maintenanceTypeOptions() {
      return normalizeOptions(this.catalogs.maintenance_types || [], true).map((item) => ({ value: item.value, text: item.label }));
    },
    maintenanceStatusOptions() {
      return normalizeOptions(this.catalogs.maintenance_statuses || [], true).map((item) => ({ value: item.value, text: item.label }));
    },
    equipmentStatusOptions() {
      return normalizeOptions(this.catalogs.equipment_statuses || [], true).map((item) => ({ value: item.value, text: item.label }));
    },
    userOptions() {
      return [{ value: null, text: "Seleccionar usuario" }].concat(
        (this.catalogs.users || []).map((item) => ({ value: item.id, text: item.name }))
      );
    },
    summaryCards() {
      return [
        { label: "Pendientes", value: this.summary.pending, help: "Informes abiertos o en borrador que aún no se cierran." },
        { label: "Cerradas", value: this.summary.closed, help: "Mantenciones que ya definieron estado final del equipo." },
        { label: "Registradas este mes", value: this.summary.month_total, help: "Cantidad total de informes generados en el mes actual." },
        { label: "Costo del mes", value: formatCurrency(this.summary.month_cost || 0), help: "Suma estimada o real de mantenciones registradas este mes." },
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
        const response = await axios.get("/api/informatica/mantenciones", {
          params: {
            page,
            ...this.filters,
            only_pending: this.filters.only_pending ? 1 : 0,
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
        this.handleError(error, "No se pudieron cargar los informes de mantención.");
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.filters = {
        search: "",
        status: null,
        maintenance_type: null,
        it_equipment_id: null,
        technician_user_id: null,
        final_equipment_status: null,
        date_from: "",
        date_to: "",
        only_pending: false,
      };
      this.load();
    },
    openCreate() {
      this.form = emptyForm();
      this.showFormModal = true;
    },
    async openEdit(item) {
      try {
        const response = await axios.get(`/api/informatica/mantenciones/${item.id}`);
        this.fillForm(response.data.data);
        this.showFormModal = true;
      } catch (error) {
        this.handleError(error, "No se pudo cargar el informe de mantención.");
      }
    },
    async openDetail(item) {
      try {
        const response = await axios.get(`/api/informatica/mantenciones/${item.id}`);
        this.selectedReport = response.data.data;
        this.showDetailModal = true;
      } catch (error) {
        this.handleError(error, "No se pudo cargar el detalle de mantención.");
      }
    },
    openClose(item) {
      this.selectedReport = item;
      this.closeForm = emptyCloseForm();
      this.showCloseModal = true;
    },
    fillForm(item) {
      this.form = {
        ...emptyForm(),
        id: item.id,
        it_equipment_id: item.it_equipment_id,
        maintenance_date: toInputDate(item.maintenance_date),
        maintenance_type: item.maintenance_type || "preventiva",
        technician_user_id: item.technician_user_id || null,
        technician_name: item.technician_name_snapshot || "",
        reason: item.reason || "",
        diagnosis: item.diagnosis || "",
        actions_performed: item.actions_performed || "",
        spare_parts: item.spare_parts || "",
        cost_amount: item.cost_amount || "",
        initial_equipment_status: item.initial_equipment_status || "disponible",
        next_maintenance_at: toInputDate(item.next_maintenance_at),
        observations: item.observations || "",
        status: item.status || "borrador",
        attachment: null,
      };
    },
    onAttachmentSelected(event) {
      this.form.attachment = event.target.files?.[0] || null;
    },
    buildFormData() {
      const payload = new FormData();
      [
        "it_equipment_id",
        "maintenance_date",
        "maintenance_type",
        "technician_user_id",
        "technician_name",
        "reason",
        "diagnosis",
        "actions_performed",
        "spare_parts",
        "cost_amount",
        "initial_equipment_status",
        "next_maintenance_at",
        "observations",
        "status",
      ].forEach((key) => {
        const value = this.form[key];
        if (value !== null && value !== undefined && value !== "") {
          payload.append(key, value);
        }
      });

      if (this.form.attachment) {
        payload.append("attachment", this.form.attachment);
      }

      return payload;
    },
    async save() {
      const confirmation = await confirmInformaticaAction({
        title: this.form.id ? "Confirmar edición del informe" : "Confirmar creación del informe",
        text: this.form.id
          ? "Se actualizará el informe técnico y su trazabilidad."
          : "Se registrará una nueva mantención para el equipo seleccionado.",
        confirmButtonText: this.form.id ? "Sí, actualizar" : "Sí, registrar",
      });

      if (!confirmation.isConfirmed) return;

      this.saving = true;
      try {
        const url = this.form.id ? `/api/informatica/mantenciones/${this.form.id}` : "/api/informatica/mantenciones";
        const response = await axios.post(url, this.buildFormData(), {
          headers: { "Content-Type": "multipart/form-data" },
        });
        this.showFormModal = false;
        this.$emit("refresh-catalogs");
        await this.load(this.pagination.current_page);
        await showInformaticaSuccess(response.data.message || "Informe guardado correctamente.");
      } catch (error) {
        this.handleError(error);
      } finally {
        this.saving = false;
      }
    },
    async closeReport() {
      if (!this.selectedReport) return;

      if (this.closeForm.final_equipment_status === "dado_de_baja") {
        await showInformaticaWarning("El equipo será dado de baja al cerrar la mantención.");
      }

      const confirmation = await confirmInformaticaAction({
        title: "Confirmar cierre de mantención",
        text: "Se cerrará el informe y se actualizará el estado final del equipo.",
        confirmButtonText: "Sí, cerrar",
        icon: "warning",
      });

      if (!confirmation.isConfirmed) return;

      this.closing = true;
      try {
        const response = await axios.post(`/api/informatica/mantenciones/${this.selectedReport.id}/close`, {
          final_equipment_status: this.closeForm.final_equipment_status,
          closed_at: this.closeForm.closed_at,
          observations: this.closeForm.observations || null,
        });
        this.showCloseModal = false;
        this.$emit("refresh-catalogs");
        await this.load(this.pagination.current_page);
        await showInformaticaSuccess(response.data.message || "Mantención cerrada correctamente.");
      } catch (error) {
        this.handleError(error);
      } finally {
        this.closing = false;
      }
    },
    attachmentUrl(attachment) {
      return `/api/informatica/adjuntos/${attachment.id}/download`;
    },
    async closeFormModal() {
      const confirmation = await confirmInformaticaCancel("los cambios del informe");
      if (confirmation.isConfirmed) {
        this.showFormModal = false;
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
      <div class="fw-semibold">Registro e historial de mantenciones</div>
      <div class="d-flex gap-2 flex-wrap">
        <InformaticaHelpButton
          title="Ayuda: mantenciones"
          text="Aquí se registran diagnósticos, acciones realizadas, repuestos, costos, estado inicial y final del equipo, manteniendo la trazabilidad técnica."
        />
        <BButton v-if="capabilities.can_create_maintenance" variant="primary" @click="openCreate">Nuevo informe</BButton>
      </div>
    </div>

    <div class="row g-3">
      <div v-for="card in summaryCards" :key="card.label" class="col-md-3">
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
          <div class="fw-semibold">Filtros de mantención</div>
          <InformaticaHelpButton
            title="Ayuda: filtros de mantención"
            text="Filtra por equipo, tipo, estado, técnico, fecha, estado final o por informes pendientes de cierre."
          />
        </div>
      </template>
      <div class="row g-3 align-items-end">
        <div class="col-md-3"><label class="form-label">Buscar</label><BFormInput v-model="filters.search" placeholder="Código, técnico, motivo..." @keyup.enter="load" /></div>
        <div class="col-md-2"><label class="form-label">Estado informe</label><BFormSelect v-model="filters.status" :options="maintenanceStatusOptions" /></div>
        <div class="col-md-2"><label class="form-label">Tipo</label><BFormSelect v-model="filters.maintenance_type" :options="maintenanceTypeOptions" /></div>
        <div class="col-md-3"><label class="form-label">Equipo</label><BFormSelect v-model="filters.it_equipment_id" :options="equipmentOptions" /></div>
        <div class="col-md-2"><label class="form-label">Técnico</label><BFormSelect v-model="filters.technician_user_id" :options="[{ value: null, text: 'Todos' }].concat((catalogs.users || []).map((item) => ({ value: item.id, text: item.name })))" /></div>
        <div class="col-md-2"><label class="form-label">Estado final</label><BFormSelect v-model="filters.final_equipment_status" :options="equipmentStatusOptions" /></div>
        <div class="col-md-2"><label class="form-label">Desde</label><BFormInput v-model="filters.date_from" type="date" /></div>
        <div class="col-md-2"><label class="form-label">Hasta</label><BFormInput v-model="filters.date_to" type="date" /></div>
        <div class="col-md-2 d-flex align-items-center"><BFormCheckbox v-model="filters.only_pending">Solo pendientes</BFormCheckbox></div>
        <div class="col-md-2 d-flex gap-2">
          <BButton variant="secondary" @click="load">Filtrar</BButton>
          <BButton variant="light" @click="resetFilters">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BCard class="border-0 shadow-sm">
      <LoadingState v-if="loading" message="Cargando mantenciones..." compact />
      <BTable
        v-else
        responsive
        :items="items"
        :fields="[
          { key: 'maintenance_code', label: 'Código' },
          { key: 'equipment', label: 'Equipo' },
          { key: 'maintenance_type', label: 'Tipo' },
          { key: 'maintenance_date', label: 'Fecha' },
          { key: 'status', label: 'Estado' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #cell(equipment)="{ item }">
          <div class="fw-semibold">{{ item.equipment?.internal_code || "-" }}</div>
          <div class="small text-muted">{{ [item.equipment?.brand, item.equipment?.model].filter(Boolean).join(" ") || "-" }}</div>
        </template>
        <template #cell(maintenance_type)="{ item }">{{ humanizeInformaticaStatus(item.maintenance_type) }}</template>
        <template #cell(maintenance_date)="{ item }">{{ formatInformaticaDate(item.maintenance_date) }}</template>
        <template #cell(status)="{ item }"><InformaticaStatusBadge :status="item.status" /></template>
        <template #cell(actions)="{ item }">
          <div class="d-flex flex-wrap gap-2">
            <BButton size="sm" variant="outline-info" @click="openDetail(item)">Detalle</BButton>
            <BButton v-if="capabilities.can_edit_maintenance && item.status !== 'cerrado'" size="sm" variant="outline-primary" @click="openEdit(item)">Editar</BButton>
            <BButton v-if="capabilities.can_close_maintenance && item.status !== 'cerrado'" size="sm" variant="outline-success" @click="openClose(item)">Cerrar</BButton>
          </div>
        </template>
      </BTable>
      <div class="d-flex justify-content-end mt-3">
        <BPagination v-model="pagination.current_page" :total-rows="pagination.total" :per-page="pagination.per_page" @update:model-value="load" />
      </div>
    </BCard>

    <BModal v-model="showFormModal" size="xl" :title="form.id ? 'Editar informe de mantención' : 'Nuevo informe de mantención'" hide-footer>
      <div class="d-flex justify-content-between align-items-center mb-3 gap-2">
        <div class="text-muted small">Registra el motivo, diagnóstico, acciones, costo y el estado operacional de la mantención.</div>
        <InformaticaHelpButton
          title="Ayuda: formulario de mantención"
          text="Si el informe sale de borrador, el equipo puede pasar a en mantención. El cierre se hace desde una acción separada para resguardar trazabilidad."
        />
      </div>
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Equipo</label><BFormSelect v-model="form.it_equipment_id" :options="equipmentOptions" /></div>
        <div class="col-md-2"><label class="form-label">Fecha</label><BFormInput v-model="form.maintenance_date" type="date" /></div>
        <div class="col-md-3"><label class="form-label">Tipo de mantención</label><BFormSelect v-model="form.maintenance_type" :options="normalizeOptions(catalogs.maintenance_types || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-3"><label class="form-label">Estado del informe</label><BFormSelect v-model="form.status" :options="normalizeOptions((catalogs.maintenance_statuses || []).filter((item) => item.value !== 'cerrado')).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-4"><label class="form-label">Técnico del sistema</label><BFormSelect v-model="form.technician_user_id" :options="userOptions" /></div>
        <div class="col-md-4"><label class="form-label">Técnico / responsable texto libre</label><BFormInput v-model="form.technician_name" /></div>
        <div class="col-md-4"><label class="form-label">Estado inicial del equipo</label><BFormSelect v-model="form.initial_equipment_status" :options="normalizeOptions(catalogs.equipment_statuses || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-12"><label class="form-label">Motivo</label><BFormInput v-model="form.reason" /></div>
        <div class="col-md-6"><label class="form-label">Diagnóstico</label><BFormTextarea v-model="form.diagnosis" rows="3" /></div>
        <div class="col-md-6"><label class="form-label">Acciones realizadas</label><BFormTextarea v-model="form.actions_performed" rows="3" /></div>
        <div class="col-md-6"><label class="form-label">Repuestos utilizados</label><BFormTextarea v-model="form.spare_parts" rows="3" /></div>
        <div class="col-md-3"><label class="form-label">Costo estimado/real</label><BFormInput v-model="form.cost_amount" type="number" step="0.01" /></div>
        <div class="col-md-3"><label class="form-label">Próxima mantención sugerida</label><BFormInput v-model="form.next_maintenance_at" type="date" /></div>
        <div class="col-md-6"><label class="form-label">Adjunto</label><BFormFile @change="onAttachmentSelected" /></div>
        <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="form.observations" rows="3" /></div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-4">
        <BButton variant="light" @click="closeFormModal">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">{{ saving ? "Guardando..." : form.id ? "Actualizar informe" : "Registrar informe" }}</BButton>
      </div>
    </BModal>

    <BModal v-model="showDetailModal" size="xl" title="Detalle de mantención" hide-footer scrollable>
      <template v-if="selectedReport">
        <div class="row g-3">
          <div class="col-md-3"><div class="small text-muted">Código</div><div class="fw-semibold">{{ selectedReport.maintenance_code }}</div></div>
          <div class="col-md-3"><div class="small text-muted">Estado</div><div><InformaticaStatusBadge :status="selectedReport.status" /></div></div>
          <div class="col-md-3"><div class="small text-muted">Tipo</div><div class="fw-semibold">{{ humanizeInformaticaStatus(selectedReport.maintenance_type) }}</div></div>
          <div class="col-md-3"><div class="small text-muted">Fecha</div><div class="fw-semibold">{{ formatInformaticaDate(selectedReport.maintenance_date) }}</div></div>
          <div class="col-md-4"><div class="small text-muted">Equipo</div><div class="fw-semibold">{{ selectedReport.equipment?.internal_code || "-" }}</div><div class="small text-muted">{{ [selectedReport.equipment?.brand, selectedReport.equipment?.model].filter(Boolean).join(" ") || "-" }}</div></div>
          <div class="col-md-4"><div class="small text-muted">Técnico</div><div class="fw-semibold">{{ selectedReport.technician?.name || selectedReport.technician_name_snapshot || "-" }}</div></div>
          <div class="col-md-4"><div class="small text-muted">Costo</div><div class="fw-semibold">{{ formatCurrency(selectedReport.cost_amount) }}</div></div>
          <div class="col-md-4"><div class="small text-muted">Estado inicial</div><div><InformaticaStatusBadge :status="selectedReport.initial_equipment_status" /></div></div>
          <div class="col-md-4"><div class="small text-muted">Estado final</div><div><InformaticaStatusBadge v-if="selectedReport.final_equipment_status" :status="selectedReport.final_equipment_status" /><span v-else>-</span></div></div>
          <div class="col-md-4"><div class="small text-muted">Próxima mantención</div><div class="fw-semibold">{{ formatInformaticaDate(selectedReport.next_maintenance_at) }}</div></div>
          <div class="col-12"><div class="small text-muted">Motivo</div><div>{{ selectedReport.reason || "-" }}</div></div>
          <div class="col-md-6"><div class="small text-muted">Diagnóstico</div><div>{{ selectedReport.diagnosis || "-" }}</div></div>
          <div class="col-md-6"><div class="small text-muted">Acciones realizadas</div><div>{{ selectedReport.actions_performed || "-" }}</div></div>
          <div class="col-md-6"><div class="small text-muted">Repuestos</div><div>{{ selectedReport.spare_parts || "-" }}</div></div>
          <div class="col-md-6"><div class="small text-muted">Observaciones</div><div>{{ selectedReport.observations || "-" }}</div></div>
        </div>

        <BCard class="border-0 shadow-sm mt-3">
          <template #header><div class="fw-semibold">Adjuntos del informe</div></template>
          <BTable
            small
            responsive
            :items="selectedReport.attachments || []"
            :fields="[
              { key: 'original_name', label: 'Archivo' },
              { key: 'category', label: 'Categoría' },
              { key: 'created_at', label: 'Fecha' },
              { key: 'download', label: 'Descarga' },
            ]"
          >
            <template #cell(category)="{ item }">{{ humanizeInformaticaStatus(item.category) }}</template>
            <template #cell(created_at)="{ item }">{{ formatInformaticaDateTime(item.created_at) }}</template>
            <template #cell(download)="{ item }">
              <a :href="attachmentUrl(item)" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">Descargar</a>
            </template>
          </BTable>
        </BCard>
      </template>
    </BModal>

    <BModal v-model="showCloseModal" size="lg" title="Cerrar mantención" hide-footer>
      <div class="d-flex justify-content-between align-items-center mb-3 gap-2">
        <div class="text-muted small">El cierre actualizará el estado final del equipo y dejará el informe como cerrado.</div>
        <InformaticaHelpButton
          title="Ayuda: cierre de mantención"
          text="Usa esta acción cuando el equipo ya tenga un resultado técnico definido. Si corresponde baja, quedará inactivo automáticamente."
        />
      </div>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Estado final del equipo</label><BFormSelect v-model="closeForm.final_equipment_status" :options="normalizeOptions(catalogs.equipment_statuses || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-6"><label class="form-label">Fecha y hora cierre</label><BFormInput v-model="closeForm.closed_at" type="datetime-local" /></div>
        <div class="col-12"><label class="form-label">Observaciones de cierre</label><BFormTextarea v-model="closeForm.observations" rows="3" /></div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-4">
        <BButton variant="light" @click="showCloseModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="closing" @click="closeReport">{{ closing ? "Cerrando..." : "Cerrar mantención" }}</BButton>
      </div>
    </BModal>
  </div>
</template>
