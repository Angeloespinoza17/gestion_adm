<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import InfirmaryHelpButton from "../../components/infirmary/help-button.vue";
import InfirmaryStatusBadge from "../../components/infirmary/status-badge.vue";
import {
  confirmInfirmaryAction,
  confirmInfirmaryCancel,
  formatInfirmaryDate,
  formatInfirmaryDateTime,
  formatInfirmaryError,
  normalizeOptions,
  showInfirmaryError,
  showInfirmarySuccess,
  toInputDate,
  toInputDateTime,
} from "../../components/infirmary/module-utils";

export default {
  components: {
    Layout,
    LoadingState,
    InfirmaryHelpButton,
    InfirmaryStatusBadge,
  },
  data() {
    return {
      loading: false,
      saving: false,
      moving: false,
      error: null,
      catalogs: {
        suppliers: [],
        capabilities: {},
      },
      filters: {
        search: "",
        status: null,
        critical: false,
        expiring: false,
      },
      medications: [],
      pagination: { current_page: 1, total: 0, per_page: 15 },
      selectedMedication: null,
      showModal: false,
      showMovementModal: false,
      form: this.emptyForm(),
      movementForm: this.emptyMovementForm(),
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
    canManage() {
      return Boolean(this.catalogs.capabilities?.can_manage_inventory);
    },
    statusOptions() {
      return [
        { value: null, text: "Todos" },
        { value: "disponible", text: "Disponible" },
        { value: "stock_bajo", text: "Stock bajo" },
        { value: "agotado", text: "Agotado" },
        { value: "proximo_a_vencer", text: "Próximo a vencer" },
        { value: "vencido", text: "Vencido" },
      ];
    },
    supplierOptions() {
      return [{ value: null, text: "Sin proveedor" }].concat(
        (this.catalogs.suppliers || []).map((item) => ({
          value: item.id,
          text: item.business_name || item.name,
        }))
      );
    },
    movementTypeOptions() {
      return normalizeOptions(["ingreso", "salida", "administracion", "ajuste", "perdida", "vencimiento", "donacion"]);
    },
    adjustmentDirectionOptions() {
      return [
        { value: "increase", text: "Aumentar stock" },
        { value: "decrease", text: "Disminuir stock" },
      ];
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadMedications();
  },
  methods: {
    formatInfirmaryDate,
    formatInfirmaryDateTime,
    emptyForm() {
      return {
        id: null,
        name: "",
        commercial_name: "",
        active_ingredient: "",
        presentation: "",
        concentration: "",
        unit: "",
        laboratory: "",
        minimum_stock: 1,
        physical_location: "",
        batch: "",
        manufactured_at: "",
        expires_at: "",
        supplier_id: null,
        observations: "",
        active: true,
      };
    },
    emptyMovementForm() {
      return {
        movement_type: "ingreso",
        quantity: 1,
        adjustment_direction: "increase",
        reason: "",
        notes: "",
        moved_at: toInputDateTime(new Date().toISOString()),
      };
    },
    async loadCatalogs() {
      const response = await axios.get("/api/infirmary/catalogs");
      this.catalogs = response.data;
    },
    async loadMedications(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/infirmary/medications", {
          params: {
            page,
            ...this.filters,
          },
        });
        this.medications = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page || 1,
          total: response.data.total || 0,
          per_page: response.data.per_page || 15,
        };

        if (this.medications[0]) {
          await this.openMedication(this.medications[0]);
        } else {
          this.selectedMedication = null;
        }
      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudo cargar el inventario.");
      } finally {
        this.loading = false;
      }
    },
    async openMedication(medication) {
      try {
        const response = await axios.get(`/api/infirmary/medications/${medication.id}`);
        this.selectedMedication = response.data.data;
      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudo cargar el detalle del medicamento.");
      }
    },
    resetFilters() {
      this.filters = {
        search: "",
        status: null,
        critical: false,
        expiring: false,
      };
      this.loadMedications(1);
    },
    openCreate() {
      this.form = this.emptyForm();
      this.showModal = true;
    },
    openEdit(medication) {
      this.form = {
        id: medication.id,
        name: medication.name || "",
        commercial_name: medication.commercial_name || "",
        active_ingredient: medication.active_ingredient || "",
        presentation: medication.presentation || "",
        concentration: medication.concentration || "",
        unit: medication.unit || "",
        laboratory: medication.laboratory || "",
        minimum_stock: medication.minimum_stock || 0,
        physical_location: medication.physical_location || "",
        batch: medication.batch || "",
        manufactured_at: toInputDate(medication.manufactured_at),
        expires_at: toInputDate(medication.expires_at),
        supplier_id: medication.supplier_id || null,
        observations: medication.observations || "",
        active: medication.active !== false,
      };
      this.showModal = true;
    },
    async cancelModal() {
      const result = await confirmInfirmaryCancel("los cambios del medicamento");
      if (result.isConfirmed) this.showModal = false;
    },
    async save() {
      this.saving = true;
      try {
        const payload = {
          ...this.form,
          manufactured_at: this.form.manufactured_at || null,
          expires_at: this.form.expires_at || null,
          supplier_id: this.form.supplier_id || null,
        };

        if (this.isEditing) {
          await axios.put(`/api/infirmary/medications/${this.form.id}`, payload);
        } else {
          await axios.post("/api/infirmary/medications", payload);
        }

        this.showModal = false;
        await this.loadMedications(this.pagination.current_page || 1);
        await showInfirmarySuccess(this.isEditing ? "Medicamento actualizado correctamente." : "Medicamento creado correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo guardar el medicamento."));
      } finally {
        this.saving = false;
      }
    },
    async remove(medication) {
      const confirmation = await confirmInfirmaryAction({
        title: "Eliminar medicamento",
        text: `Se eliminará ${medication.commercial_name || medication.name}.`,
        confirmButtonText: "Eliminar",
      });

      if (!confirmation.isConfirmed) return;

      try {
        await axios.delete(`/api/infirmary/medications/${medication.id}`);
        await this.loadMedications(this.pagination.current_page || 1);
        await showInfirmarySuccess("Medicamento eliminado correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo eliminar el medicamento."));
      }
    },
    openMovement(medication) {
      this.selectedMedication = medication;
      this.movementForm = this.emptyMovementForm();
      this.showMovementModal = true;
    },
    async cancelMovementModal() {
      const result = await confirmInfirmaryCancel("el movimiento de inventario");
      if (result.isConfirmed) this.showMovementModal = false;
    },
    async saveMovement() {
      if (!this.selectedMedication?.id) return;

      this.moving = true;
      try {
        await axios.post(`/api/infirmary/medications/${this.selectedMedication.id}/movements`, this.movementForm);
        this.showMovementModal = false;
        await this.openMedication(this.selectedMedication);
        await this.loadMedications(this.pagination.current_page || 1);
        await showInfirmarySuccess("Movimiento registrado correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo registrar el movimiento."));
      } finally {
        this.moving = false;
      }
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Inventario de medicamentos</h4>
        <div class="text-muted">
          Control de stock, vencimientos, movimientos auditados y trazabilidad del botiquín escolar.
        </div>
      </div>
      <div class="d-flex gap-2">
        <InfirmaryHelpButton
          title="Ayuda: inventario de medicamentos"
          text="En esta pantalla se administra el inventario de medicamentos, incluyendo stock mínimo, vencimientos, lotes, ubicaciones físicas y movimientos auditados."
        />
        <BButton v-if="canManage" variant="primary" @click="openCreate">Nuevo medicamento</BButton>
      </div>
    </div>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

    <div class="row g-3">
      <div class="col-xl-8">
        <BCard class="mb-3">
          <template #header>
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
              <div class="fw-semibold">Filtros de inventario</div>
              <InfirmaryHelpButton
                title="Ayuda: filtros de inventario"
                text="Filtra por nombre, estado, stock crítico o proximidad de vencimiento para priorizar la gestión del botiquín."
              />
            </div>
          </template>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Buscar</label>
              <BFormInput v-model="filters.search" placeholder="Nombre, lote, ingrediente activo" @keyup.enter="loadMedications(1)" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Estado</label>
              <BFormSelect v-model="filters.status" :options="statusOptions" />
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <div class="form-check">
                <input id="critical" v-model="filters.critical" class="form-check-input" type="checkbox" />
                <label class="form-check-label" for="critical">Stock crítico</label>
              </div>
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <div class="form-check">
                <input id="expiring" v-model="filters.expiring" class="form-check-input" type="checkbox" />
                <label class="form-check-label" for="expiring">Por vencer</label>
              </div>
            </div>
            <div class="col-md-1 d-flex align-items-end">
              <BButton variant="primary" class="w-100" @click="loadMedications(1)">Ir</BButton>
            </div>
            <div class="col-12">
              <BButton variant="outline-secondary" @click="resetFilters">Limpiar</BButton>
            </div>
          </div>
        </BCard>

        <BCard>
          <template #header>
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
              <div class="fw-semibold">Bodega clínica</div>
              <InfirmaryHelpButton
                title="Ayuda: bodega clínica"
                text="La tabla lista medicamentos, insumos y alertas de stock o vencimiento para toma de decisiones rápidas."
              />
            </div>
          </template>

          <LoadingState v-if="loading" message="Cargando inventario..." compact />

          <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Medicamento</th>
                  <th>Stock</th>
                  <th>Vencimiento</th>
                  <th>Ubicación</th>
                  <th>Estado</th>
                  <th class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="medication in medications"
                  :key="medication.id"
                  :class="{ 'table-active': selectedMedication?.id === medication.id }"
                  role="button"
                  @click="openMedication(medication)"
                >
                  <td>
                    <div class="fw-semibold">{{ medication.commercial_name || medication.name }}</div>
                    <div class="small text-muted">{{ medication.active_ingredient || medication.name }}</div>
                  </td>
                  <td>{{ medication.current_stock }} / {{ medication.minimum_stock }}</td>
                  <td>{{ formatInfirmaryDate(medication.expires_at) }}</td>
                  <td>{{ medication.physical_location || "-" }}</td>
                  <td><InfirmaryStatusBadge :status="medication.status" /></td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <BButton v-if="canManage" variant="outline-primary" @click.stop="openEdit(medication)">Editar</BButton>
                      <BButton v-if="canManage" variant="outline-secondary" @click.stop="openMovement(medication)">Movimiento</BButton>
                      <BButton v-if="canManage" variant="outline-danger" @click.stop="remove(medication)">Eliminar</BButton>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="d-flex justify-content-end mt-3">
            <BPagination
              v-model="pagination.current_page"
              :total-rows="pagination.total"
              :per-page="pagination.per_page"
              @update:model-value="loadMedications"
            />
          </div>
        </BCard>
      </div>

      <div class="col-xl-4">
        <BCard class="mb-3">
          <template #header>
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
              <div class="fw-semibold">Ficha del medicamento</div>
              <InfirmaryHelpButton
                title="Ayuda: ficha del medicamento"
                text="Aquí se revisa la trazabilidad completa del medicamento: lote, proveedor, stock, autorizaciones y movimientos."
              />
            </div>
          </template>

          <div v-if="!selectedMedication" class="text-muted">Selecciona un medicamento para ver su ficha.</div>
          <div v-else>
            <div class="fw-semibold fs-5">{{ selectedMedication.commercial_name || selectedMedication.name }}</div>
            <div class="text-muted small mb-3">{{ selectedMedication.active_ingredient || "Sin principio activo" }}</div>
            <div class="mb-2"><span class="text-muted">Presentación:</span> {{ selectedMedication.presentation || "-" }}</div>
            <div class="mb-2"><span class="text-muted">Concentración:</span> {{ selectedMedication.concentration || "-" }}</div>
            <div class="mb-2"><span class="text-muted">Laboratorio:</span> {{ selectedMedication.laboratory || "-" }}</div>
            <div class="mb-2"><span class="text-muted">Lote:</span> {{ selectedMedication.batch || "-" }}</div>
            <div class="mb-2"><span class="text-muted">Ubicación:</span> {{ selectedMedication.physical_location || "-" }}</div>
            <div class="mb-2"><span class="text-muted">Proveedor:</span> {{ selectedMedication.supplier?.business_name || selectedMedication.supplier?.name || "-" }}</div>
            <div class="mb-2"><span class="text-muted">Stock:</span> {{ selectedMedication.current_stock }} {{ selectedMedication.unit || "" }}</div>
            <div class="mb-2"><span class="text-muted">Stock mínimo:</span> {{ selectedMedication.minimum_stock }} {{ selectedMedication.unit || "" }}</div>
            <div class="mb-2"><span class="text-muted">Estado:</span> <InfirmaryStatusBadge :status="selectedMedication.status" /></div>
            <div class="mb-2"><span class="text-muted">Elaboración:</span> {{ formatInfirmaryDate(selectedMedication.manufactured_at) }}</div>
            <div class="mb-2"><span class="text-muted">Vencimiento:</span> {{ formatInfirmaryDate(selectedMedication.expires_at) }}</div>
            <div><span class="text-muted">Observaciones:</span> {{ selectedMedication.observations || "Sin observaciones." }}</div>
          </div>
        </BCard>

        <BCard class="mb-3" v-if="selectedMedication">
          <h5 class="mb-3">Movimientos recientes</h5>
          <div v-if="!(selectedMedication.movements || []).length" class="text-muted">Sin movimientos registrados.</div>
          <ul v-else class="list-group list-group-flush">
            <li v-for="movement in selectedMedication.movements.slice(0, 8)" :key="movement.id" class="list-group-item px-0">
              <div class="d-flex justify-content-between gap-2">
                <div>{{ movement.movement_type }}</div>
                <div class="fw-semibold">{{ movement.stock_before }} → {{ movement.stock_after }}</div>
              </div>
              <div class="small text-muted">{{ formatInfirmaryDateTime(movement.moved_at) }} · {{ movement.performed_by?.name || "-" }}</div>
            </li>
          </ul>
        </BCard>

        <BCard v-if="selectedMedication">
          <h5 class="mb-3">Uso clínico</h5>
          <div class="mb-2"><span class="text-muted">Autorizaciones activas:</span> {{ selectedMedication.authorizations?.length || 0 }}</div>
          <div class="mb-2"><span class="text-muted">Administraciones registradas:</span> {{ selectedMedication.administrations?.length || 0 }}</div>
          <div class="small text-muted">
            Este resumen ayuda a anticipar consumo y reposición del inventario clínico.
          </div>
        </BCard>
      </div>
    </div>

    <BModal v-model="showModal" :title="isEditing ? 'Editar medicamento' : 'Nuevo medicamento'" size="xl" hide-footer>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Nombre</label>
          <BFormInput v-model="form.name" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Nombre comercial</label>
          <BFormInput v-model="form.commercial_name" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Principio activo</label>
          <BFormInput v-model="form.active_ingredient" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Presentación</label>
          <BFormInput v-model="form.presentation" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Concentración</label>
          <BFormInput v-model="form.concentration" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Unidad</label>
          <BFormInput v-model="form.unit" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Laboratorio</label>
          <BFormInput v-model="form.laboratory" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Stock mínimo</label>
          <BFormInput v-model="form.minimum_stock" type="number" min="0" step="0.01" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Ubicación física</label>
          <BFormInput v-model="form.physical_location" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Lote</label>
          <BFormInput v-model="form.batch" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Proveedor</label>
          <BFormSelect v-model="form.supplier_id" :options="supplierOptions" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Fecha elaboración</label>
          <BFormInput v-model="form.manufactured_at" type="date" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Fecha vencimiento</label>
          <BFormInput v-model="form.expires_at" type="date" />
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <div class="form-check">
            <input id="active" v-model="form.active" class="form-check-input" type="checkbox" />
            <label class="form-check-label" for="active">Activo</label>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label">Observaciones</label>
          <BFormTextarea v-model="form.observations" rows="3" />
        </div>
        <div class="col-12 d-flex justify-content-end gap-2">
          <BButton variant="outline-secondary" @click="cancelModal">Cancelar</BButton>
          <BButton variant="primary" :disabled="saving" @click="save">
            {{ saving ? "Guardando..." : isEditing ? "Guardar cambios" : "Registrar medicamento" }}
          </BButton>
        </div>
      </div>
    </BModal>

    <BModal v-model="showMovementModal" title="Registrar movimiento de inventario" size="lg" hide-footer>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Tipo de movimiento</label>
          <BFormSelect v-model="movementForm.movement_type" :options="movementTypeOptions" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Cantidad</label>
          <BFormInput v-model="movementForm.quantity" type="number" min="0.01" step="0.01" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Fecha y hora</label>
          <BFormInput v-model="movementForm.moved_at" type="datetime-local" />
        </div>
        <div class="col-md-4" v-if="movementForm.movement_type === 'ajuste'">
          <label class="form-label">Dirección del ajuste</label>
          <BFormSelect v-model="movementForm.adjustment_direction" :options="adjustmentDirectionOptions" />
        </div>
        <div class="col-md-8">
          <label class="form-label">Motivo</label>
          <BFormInput v-model="movementForm.reason" />
        </div>
        <div class="col-12">
          <label class="form-label">Notas</label>
          <BFormTextarea v-model="movementForm.notes" rows="3" />
        </div>
        <div class="col-12 d-flex justify-content-end gap-2">
          <BButton variant="outline-secondary" @click="cancelMovementModal">Cancelar</BButton>
          <BButton variant="primary" :disabled="moving" @click="saveMovement">
            {{ moving ? "Registrando..." : "Registrar movimiento" }}
          </BButton>
        </div>
      </div>
    </BModal>
  </Layout>
</template>

