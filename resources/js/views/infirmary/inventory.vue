<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import InfirmaryHelpButton from "../../components/infirmary/help-button.vue";
import InfirmaryStatusBadge from "../../components/infirmary/status-badge.vue";
import InfirmaryStudentSearch from "../../components/infirmary/student-search.vue";
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

const INVENTORY_TYPE_SUPPLY = "supply";
const INVENTORY_TYPE_MEDICATION = "medication";
const SOURCE_SCHOOL = "school";
const SOURCE_GUARDIAN = "guardian";

export default {
  components: {
    Layout,
    LoadingState,
    InfirmaryHelpButton,
    InfirmaryStatusBadge,
    InfirmaryStudentSearch,
  },
  data() {
    return {
      loading: false,
      detailLoading: false,
      saving: false,
      moving: false,
      error: null,
      activeType: INVENTORY_TYPE_SUPPLY,
      catalogs: {
        suppliers: [],
        capabilities: {},
        inventory_source_options: [],
      },
      filters: {
        search: "",
        status: null,
        source_type: null,
        critical: false,
        expiring: false,
      },
      items: [],
      pagination: { current_page: 1, total: 0, per_page: 15 },
      selectedItem: null,
      selectedStudent: null,
      showDetailModal: false,
      showModal: false,
      showMovementModal: false,
      form: this.emptyForm(INVENTORY_TYPE_SUPPLY),
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
    isMedicationTab() {
      return this.activeType === INVENTORY_TYPE_MEDICATION;
    },
    formIsMedication() {
      return this.form.inventory_type === INVENTORY_TYPE_MEDICATION;
    },
    formIsGuardianMedication() {
      return this.formIsMedication && this.form.source_type === SOURCE_GUARDIAN;
    },
    singularLabel() {
      return this.isMedicationTab ? "medicamento" : "insumo";
    },
    pluralLabel() {
      return this.isMedicationTab ? "medicamentos" : "insumos generales";
    },
    modalTitle() {
      const noun = this.formIsMedication ? "medicamento" : "insumo";
      return this.isEditing ? `Editar ${noun}` : `Nuevo ${noun}`;
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
    sourceOptions() {
      const options = this.catalogs.inventory_source_options?.length
        ? this.catalogs.inventory_source_options
        : [
            { value: SOURCE_SCHOOL, label: "Stock del colegio" },
            { value: SOURCE_GUARDIAN, label: "Entregado por apoderado" },
          ];

      return [{ value: null, text: "Todos los orígenes" }].concat(
        options.map((item) => ({ value: item.value, text: item.label }))
      );
    },
    formSourceOptions() {
      return this.sourceOptions.filter((item) => item.value !== null);
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
      const types = this.selectedItem?.inventory_type === INVENTORY_TYPE_SUPPLY
        ? ["ingreso", "salida", "ajuste", "perdida", "vencimiento", "donacion"]
        : ["ingreso", "salida", "administracion", "ajuste", "perdida", "vencimiento", "donacion"];

      return normalizeOptions(types);
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
    await this.loadItems();
  },
  methods: {
    formatInfirmaryDate,
    formatInfirmaryDateTime,
    emptyForm(inventoryType = INVENTORY_TYPE_SUPPLY) {
      return {
        id: null,
        inventory_type: inventoryType,
        source_type: SOURCE_SCHOOL,
        name: "",
        commercial_name: "",
        active_ingredient: "",
        presentation: "",
        concentration: "",
        unit: "unidad",
        laboratory: "",
        initial_stock: 0,
        minimum_stock: 1,
        physical_location: "",
        batch: "",
        manufactured_at: "",
        expires_at: "",
        supplier_id: null,
        student_profile_id: null,
        received_from_guardian: "",
        received_at: "",
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
      try {
        const response = await axios.get("/api/infirmary/catalogs");
        this.catalogs = response.data;
      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudieron cargar los catálogos de inventario.");
      }
    },
    async loadItems(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/infirmary/medications", {
          params: {
            page,
            inventory_type: this.activeType,
            search: this.filters.search,
            status: this.filters.status,
            source_type: this.isMedicationTab ? this.filters.source_type : null,
            critical: this.filters.critical,
            expiring: this.filters.expiring,
          },
        });
        this.items = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page || 1,
          total: response.data.total || 0,
          per_page: response.data.per_page || 15,
        };

      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudo cargar el inventario.");
      } finally {
        this.loading = false;
      }
    },
    async openItem(item) {
      this.selectedItem = item;
      this.showDetailModal = true;
      this.detailLoading = true;
      try {
        const response = await axios.get(`/api/infirmary/medications/${item.id}`);
        this.selectedItem = response.data.data;
      } catch (error) {
        this.showDetailModal = false;
        await showInfirmaryError(formatInfirmaryError(error, `No se pudo cargar la ficha del ${this.itemNoun(item)}.`));
      } finally {
        this.detailLoading = false;
      }
    },
    async switchType(type) {
      if (this.activeType === type) return;
      this.activeType = type;
      this.filters = {
        search: "",
        status: null,
        source_type: null,
        critical: false,
        expiring: false,
      };
      this.selectedItem = null;
      await this.loadItems(1);
    },
    resetFilters() {
      this.filters = {
        search: "",
        status: null,
        source_type: null,
        critical: false,
        expiring: false,
      };
      this.loadItems(1);
    },
    openCreate() {
      this.form = this.emptyForm(this.activeType);
      this.selectedStudent = null;
      this.showModal = true;
    },
    openEdit(item) {
      this.form = {
        id: item.id,
        inventory_type: item.inventory_type || INVENTORY_TYPE_MEDICATION,
        source_type: item.source_type || SOURCE_SCHOOL,
        name: item.name || "",
        commercial_name: item.commercial_name || "",
        active_ingredient: item.active_ingredient || "",
        presentation: item.presentation || "",
        concentration: item.concentration || "",
        unit: item.unit || "unidad",
        laboratory: item.laboratory || "",
        initial_stock: 0,
        minimum_stock: item.minimum_stock || 0,
        physical_location: item.physical_location || "",
        batch: item.batch || "",
        manufactured_at: toInputDate(item.manufactured_at),
        expires_at: toInputDate(item.expires_at),
        supplier_id: item.supplier_id || null,
        student_profile_id: item.student_profile_id || item.student?.id || null,
        received_from_guardian: item.received_from_guardian || "",
        received_at: toInputDateTime(item.received_at),
        observations: item.observations || "",
        active: item.active !== false,
      };
      this.selectedStudent = item.student
        ? {
            ...item.student,
            full_name: this.studentName(item.student),
          }
        : null;
      this.showModal = true;
    },
    onSourceChange() {
      if (!this.formIsGuardianMedication) {
        this.form.student_profile_id = null;
        this.form.received_from_guardian = "";
        this.form.received_at = "";
        this.selectedStudent = null;
        return;
      }

      if (!this.form.received_at) {
        this.form.received_at = toInputDateTime(new Date().toISOString());
      }
    },
    selectStudent(student) {
      this.selectedStudent = student;
      this.form.student_profile_id = student.id;
      if (!this.form.received_from_guardian) {
        this.form.received_from_guardian = student.guardian_name || "";
      }
    },
    clearStudent() {
      this.selectedStudent = null;
      this.form.student_profile_id = null;
    },
    async cancelModal() {
      const result = await confirmInfirmaryCancel(`los cambios del ${this.formIsMedication ? "medicamento" : "insumo"}`);
      if (result.isConfirmed) this.showModal = false;
    },
    async save() {
      if (!(this.form.name || "").trim()) {
        await showInfirmaryError("Debes ingresar el nombre del registro.");
        return;
      }

      if (this.formIsGuardianMedication && !this.form.student_profile_id) {
        await showInfirmaryError("Selecciona la estudiante a la que pertenece el medicamento.");
        return;
      }

      if (this.formIsGuardianMedication && !(this.form.received_from_guardian || "").trim()) {
        await showInfirmaryError("Indica el nombre del apoderado que entrega el medicamento.");
        return;
      }

      if (this.formIsGuardianMedication && !this.form.received_at) {
        await showInfirmaryError("Indica la fecha y hora de recepción del medicamento.");
        return;
      }

      const editing = this.isEditing;
      const noun = this.formIsMedication ? "Medicamento" : "Insumo";
      this.saving = true;
      try {
        const payload = {
          ...this.form,
          name: this.form.name.trim(),
          initial_stock: editing ? null : Number(this.form.initial_stock || 0),
          minimum_stock: Number(this.form.minimum_stock || 0),
          manufactured_at: this.form.manufactured_at || null,
          expires_at: this.form.expires_at || null,
          supplier_id: this.form.supplier_id || null,
          student_profile_id: this.form.student_profile_id || null,
          received_from_guardian: this.form.received_from_guardian || null,
          received_at: this.form.received_at || null,
        };
        delete payload.id;

        if (editing) {
          await axios.put(`/api/infirmary/medications/${this.form.id}`, payload);
        } else {
          await axios.post("/api/infirmary/medications", payload);
        }

        this.showModal = false;
        await this.loadItems(this.pagination.current_page || 1);
        await showInfirmarySuccess(`${noun} ${editing ? "actualizado" : "registrado"} correctamente.`);
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, `No se pudo guardar el ${noun.toLowerCase()}.`));
      } finally {
        this.saving = false;
      }
    },
    async remove(item) {
      const noun = this.itemNoun(item);
      const confirmation = await confirmInfirmaryAction({
        title: `Eliminar ${noun}`,
        text: `Se eliminará ${this.itemName(item)} y todos sus movimientos asociados.`,
        confirmButtonText: "Eliminar",
      });

      if (!confirmation.isConfirmed) return;

      try {
        await axios.delete(`/api/infirmary/medications/${item.id}`);
        await this.loadItems(this.pagination.current_page || 1);
        await showInfirmarySuccess(`${this.capitalize(noun)} eliminado correctamente.`);
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, `No se pudo eliminar el ${noun}.`));
      }
    },
    openMovement(item) {
      this.selectedItem = item;
      this.movementForm = this.emptyMovementForm();
      this.showMovementModal = true;
    },
    async cancelMovementModal() {
      const result = await confirmInfirmaryCancel("el movimiento de inventario");
      if (result.isConfirmed) this.showMovementModal = false;
    },
    async saveMovement() {
      if (!this.selectedItem?.id) return;

      this.moving = true;
      try {
        await axios.post(`/api/infirmary/medications/${this.selectedItem.id}/movements`, this.movementForm);
        this.showMovementModal = false;
        await this.loadItems(this.pagination.current_page || 1);
        await showInfirmarySuccess("Movimiento registrado correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo registrar el movimiento."));
      } finally {
        this.moving = false;
      }
    },
    itemName(item) {
      return item?.commercial_name || item?.name || "Sin nombre";
    },
    itemNoun(item) {
      return item?.inventory_type === INVENTORY_TYPE_SUPPLY ? "insumo" : "medicamento";
    },
    studentName(student) {
      if (!student) return "Sin estudiante";
      return student.full_name || [student.first_name, student.last_name].filter(Boolean).join(" ") || "Sin estudiante";
    },
    sourceLabel(item) {
      return item?.source_type === SOURCE_GUARDIAN ? "Entregado por apoderado" : "Stock del colegio";
    },
    capitalize(value) {
      return value ? value.charAt(0).toUpperCase() + value.slice(1) : "";
    },
  },
};
</script>

<template>
  <Layout>
    <div class="infirmary-inventory-page">
      <header class="inventory-header mb-3">
        <div>
          <h4 class="mb-1">Inventario de enfermería</h4>
          <p class="text-muted mb-0">Existencias clínicas, vencimientos y medicamentos bajo custodia del colegio.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
          <InfirmaryHelpButton
            title="Ayuda: inventario de enfermería"
            text="Separa los insumos de uso general de los medicamentos. Los medicamentos entregados por apoderados quedan vinculados a una estudiante."
            button-text=""
          />
          <BButton v-if="canManage" variant="primary" @click="openCreate">
            <i class="mdi mdi-plus me-1"></i>
            Nuevo {{ singularLabel }}
          </BButton>
        </div>
      </header>

      <div class="inventory-type-tabs mb-3" role="tablist" aria-label="Tipo de inventario">
        <button
          type="button"
          role="tab"
          :aria-selected="activeType === 'supply'"
          :class="['inventory-type-tab', { active: activeType === 'supply' }]"
          @click="switchType('supply')"
        >
          <i class="mdi mdi-bandage"></i>
          <span>Insumos generales</span>
        </button>
        <button
          type="button"
          role="tab"
          :aria-selected="activeType === 'medication'"
          :class="['inventory-type-tab', { active: activeType === 'medication' }]"
          @click="switchType('medication')"
        >
          <i class="mdi mdi-pill"></i>
          <span>Medicamentos</span>
        </button>
      </div>

      <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

      <BCard class="inventory-filter-card mb-3">
        <div class="inventory-filter-header">
          <div>
            <h5 class="mb-0">Filtros</h5>
            <small class="text-muted">{{ pagination.total }} {{ pluralLabel }} registrados</small>
          </div>
          <InfirmaryHelpButton
            title="Ayuda: filtros de inventario"
            text="Busca por nombre, lote, estudiante o apoderado y prioriza existencias críticas o próximas a vencer."
            button-text=""
          />
        </div>

        <div class="row g-3 mt-0">
          <div :class="isMedicationTab ? 'col-lg-4' : 'col-lg-6'">
            <label class="form-label">Buscar</label>
            <BFormInput
              v-model="filters.search"
              :placeholder="isMedicationTab ? 'Medicamento, estudiante, apoderado o lote' : 'Insumo, presentación o lote'"
              @keyup.enter="loadItems(1)"
            />
          </div>
          <div class="col-md-4 col-lg-2">
            <label class="form-label">Estado</label>
            <BFormSelect v-model="filters.status" :options="statusOptions" />
          </div>
          <div v-if="isMedicationTab" class="col-md-5 col-lg-3">
            <label class="form-label">Origen</label>
            <BFormSelect v-model="filters.source_type" :options="sourceOptions" />
          </div>
          <div class="col-md-4 col-lg-2 inventory-checks">
            <div class="form-check">
              <input id="inventory-critical" v-model="filters.critical" class="form-check-input" type="checkbox" />
              <label class="form-check-label" for="inventory-critical">Stock crítico</label>
            </div>
            <div class="form-check">
              <input id="inventory-expiring" v-model="filters.expiring" class="form-check-input" type="checkbox" />
              <label class="form-check-label" for="inventory-expiring">Por vencer</label>
            </div>
          </div>
          <div class="col-md-4 col-lg-3 inventory-filter-actions">
            <BButton variant="outline-secondary" @click="resetFilters">Limpiar</BButton>
            <BButton variant="primary" @click="loadItems(1)">Filtrar</BButton>
          </div>
        </div>
      </BCard>

      <BCard class="inventory-table-card">
            <div class="inventory-table-heading">
              <div>
                <h5 class="mb-0">{{ isMedicationTab ? "Medicamentos en custodia" : "Insumos disponibles" }}</h5>
                <small class="text-muted">Control de stock y vencimientos</small>
              </div>
              <InfirmaryHelpButton
                :title="`Ayuda: ${pluralLabel}`"
                :text="isMedicationTab
                  ? 'Los medicamentos del apoderado muestran la estudiante asociada y sus datos de recepción.'
                  : 'Los insumos generales consideran apósitos, gasas, guantes y otros materiales clínicos.'"
                button-text=""
              />
            </div>

            <LoadingState v-if="loading" message="Cargando inventario..." compact />

            <div v-else class="table-responsive inventory-table-wrap">
              <table class="table align-middle mb-0 inventory-table">
                <thead>
                  <tr>
                    <th>{{ isMedicationTab ? "Medicamento" : "Insumo" }}</th>
                    <th>{{ isMedicationTab ? "Origen y estudiante" : "Presentación" }}</th>
                    <th>Stock</th>
                    <th>Vencimiento</th>
                    <th>Ubicación</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!items.length">
                    <td colspan="7" class="inventory-empty-state">
                      No hay {{ pluralLabel }} para los filtros seleccionados.
                    </td>
                  </tr>
                  <template v-else>
                  <tr
                    v-for="item in items"
                    :key="item.id"
                  >
                    <td>
                      <div class="fw-semibold">{{ itemName(item) }}</div>
                      <div class="small text-muted">
                        {{ isMedicationTab ? item.active_ingredient || item.name : item.name }}
                      </div>
                      <div v-if="item.batch" class="small text-muted">Lote {{ item.batch }}</div>
                    </td>
                    <td>
                      <template v-if="isMedicationTab">
                        <span :class="['inventory-source-badge', { guardian: item.source_type === 'guardian' }]">
                          {{ sourceLabel(item) }}
                        </span>
                        <div v-if="item.source_type === 'guardian'" class="mt-1 fw-semibold">
                          {{ studentName(item.student) }}
                        </div>
                        <div v-if="item.source_type === 'guardian'" class="small text-muted">
                          {{ item.student?.rut || "Sin RUT" }}
                        </div>
                      </template>
                      <template v-else>
                        <div>{{ item.presentation || "Sin presentación" }}</div>
                        <div class="small text-muted">{{ item.unit || "Sin unidad" }}</div>
                      </template>
                    </td>
                    <td>
                      <div class="fw-semibold">{{ item.current_stock }} {{ item.unit || "" }}</div>
                      <div class="small text-muted">Mínimo {{ item.minimum_stock }}</div>
                    </td>
                    <td>{{ formatInfirmaryDate(item.expires_at) }}</td>
                    <td>{{ item.physical_location || "-" }}</td>
                    <td><InfirmaryStatusBadge :status="item.status" /></td>
                    <td class="text-end">
                      <div class="infirmary-inventory-actions">
                        <button
                          type="button"
                          class="cnsc-action-btn cnsc-action-btn--view"
                          title="Ver"
                          :aria-label="`Ver ficha del ${itemNoun(item)}`"
                          @click.stop="openItem(item)"
                        >
                          <i class="mdi mdi-eye-outline"></i>
                        </button>
                        <button
                          v-if="canManage"
                          type="button"
                          class="cnsc-action-btn cnsc-action-btn--edit"
                          :title="`Editar ${itemNoun(item)}`"
                          :aria-label="`Editar ${itemNoun(item)}`"
                          @click.stop="openEdit(item)"
                        >
                          <i class="mdi mdi-pencil-outline"></i>
                        </button>
                        <button
                          v-if="canManage"
                          type="button"
                          class="cnsc-action-btn cnsc-action-btn--success"
                          title="Registrar movimiento"
                          aria-label="Registrar movimiento"
                          @click.stop="openMovement(item)"
                        >
                          <i class="mdi mdi-swap-horizontal"></i>
                        </button>
                        <button
                          v-if="canManage"
                          type="button"
                          class="cnsc-action-btn cnsc-action-btn--delete"
                          :title="`Eliminar ${itemNoun(item)}`"
                          :aria-label="`Eliminar ${itemNoun(item)}`"
                          @click.stop="remove(item)"
                        >
                          <i class="mdi mdi-trash-can-outline"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                  </template>
                </tbody>
              </table>
            </div>

            <div class="d-flex justify-content-end mt-3">
              <BPagination
                v-model="pagination.current_page"
                :total-rows="pagination.total"
                :per-page="pagination.per_page"
                @update:model-value="loadItems"
              />
            </div>
      </BCard>

    </div>

    <BModal
      v-model="showDetailModal"
      :title="selectedItem ? `Ficha de ${itemNoun(selectedItem)}` : 'Ficha de inventario'"
      size="xl"
      scrollable
      hide-footer
    >
      <LoadingState v-if="detailLoading" message="Cargando ficha..." compact />

      <div v-else-if="selectedItem" class="inventory-detail-modal">
        <div class="inventory-detail-summary">
          <div class="inventory-detail-summary-icon">
            <i :class="selectedItem.inventory_type === 'medication' ? 'mdi mdi-pill' : 'mdi mdi-bandage'"></i>
          </div>
          <div class="inventory-detail-summary-copy">
            <div class="inventory-detail-title">{{ itemName(selectedItem) }}</div>
            <div class="text-muted small">
              {{ selectedItem.inventory_type === 'medication'
                ? selectedItem.active_ingredient || selectedItem.name || "Sin principio activo"
                : selectedItem.presentation || "Sin presentación" }}
            </div>
          </div>
          <InfirmaryStatusBadge :status="selectedItem.status" />
        </div>

        <section class="inventory-detail-section">
          <h6>Identificación</h6>
          <dl class="inventory-detail-grid">
            <div class="inventory-detail-field">
              <dt>Nombre</dt>
              <dd>{{ selectedItem.name || "-" }}</dd>
            </div>
            <div v-if="selectedItem.inventory_type === 'medication'" class="inventory-detail-field">
              <dt>Nombre comercial</dt>
              <dd>{{ selectedItem.commercial_name || "-" }}</dd>
            </div>
            <div v-if="selectedItem.inventory_type === 'medication'" class="inventory-detail-field">
              <dt>Principio activo</dt>
              <dd>{{ selectedItem.active_ingredient || "-" }}</dd>
            </div>
            <div class="inventory-detail-field">
              <dt>Presentación</dt>
              <dd>{{ selectedItem.presentation || "-" }}</dd>
            </div>
            <div v-if="selectedItem.inventory_type === 'medication'" class="inventory-detail-field">
              <dt>Concentración</dt>
              <dd>{{ selectedItem.concentration || "-" }}</dd>
            </div>
            <div v-if="selectedItem.inventory_type === 'medication'" class="inventory-detail-field">
              <dt>Laboratorio</dt>
              <dd>{{ selectedItem.laboratory || "-" }}</dd>
            </div>
            <div class="inventory-detail-field">
              <dt>Unidad de stock</dt>
              <dd>{{ selectedItem.unit || "-" }}</dd>
            </div>
            <div class="inventory-detail-field">
              <dt>Proveedor</dt>
              <dd>{{ selectedItem.supplier?.business_name || selectedItem.supplier?.name || "Sin proveedor" }}</dd>
            </div>
          </dl>
        </section>

        <section v-if="selectedItem.inventory_type === 'medication'" class="inventory-detail-section">
          <h6>Procedencia y custodia</h6>
          <dl class="inventory-detail-grid">
            <div class="inventory-detail-field">
              <dt>Origen</dt>
              <dd>{{ sourceLabel(selectedItem) }}</dd>
            </div>
            <template v-if="selectedItem.source_type === 'guardian'">
              <div class="inventory-detail-field">
                <dt>Estudiante</dt>
                <dd>{{ studentName(selectedItem.student) }}</dd>
              </div>
              <div class="inventory-detail-field">
                <dt>RUT estudiante</dt>
                <dd>{{ selectedItem.student?.rut || "-" }}</dd>
              </div>
              <div class="inventory-detail-field">
                <dt>Apoderado que entrega</dt>
                <dd>{{ selectedItem.received_from_guardian || "-" }}</dd>
              </div>
              <div class="inventory-detail-field">
                <dt>Fecha de recepción</dt>
                <dd>{{ formatInfirmaryDateTime(selectedItem.received_at) }}</dd>
              </div>
            </template>
          </dl>
        </section>

        <section class="inventory-detail-section">
          <h6>Control de existencias</h6>
          <dl class="inventory-detail-grid">
            <div class="inventory-detail-field inventory-detail-field--emphasis">
              <dt>Stock actual</dt>
              <dd>{{ selectedItem.current_stock }} {{ selectedItem.unit || "" }}</dd>
            </div>
            <div class="inventory-detail-field">
              <dt>Stock mínimo</dt>
              <dd>{{ selectedItem.minimum_stock }} {{ selectedItem.unit || "" }}</dd>
            </div>
            <div class="inventory-detail-field">
              <dt>Ubicación</dt>
              <dd>{{ selectedItem.physical_location || "-" }}</dd>
            </div>
            <div class="inventory-detail-field">
              <dt>Lote</dt>
              <dd>{{ selectedItem.batch || "-" }}</dd>
            </div>
            <div class="inventory-detail-field">
              <dt>Fecha de elaboración</dt>
              <dd>{{ formatInfirmaryDate(selectedItem.manufactured_at) }}</dd>
            </div>
            <div class="inventory-detail-field">
              <dt>Fecha de vencimiento</dt>
              <dd>{{ formatInfirmaryDate(selectedItem.expires_at) }}</dd>
            </div>
            <div class="inventory-detail-field">
              <dt>Estado</dt>
              <dd><InfirmaryStatusBadge :status="selectedItem.status" /></dd>
            </div>
            <div class="inventory-detail-field">
              <dt>Registro</dt>
              <dd>{{ selectedItem.active === false ? "Inactivo" : "Activo" }}</dd>
            </div>
          </dl>
        </section>

        <section class="inventory-detail-section">
          <h6>Observaciones</h6>
          <p class="inventory-detail-notes">{{ selectedItem.observations || "Sin observaciones." }}</p>
        </section>

        <section class="inventory-detail-section inventory-detail-section--movements">
          <div class="inventory-detail-section-heading">
            <h6>Movimientos recientes</h6>
            <span class="text-muted small">Últimos {{ Math.min((selectedItem.movements || []).length, 8) }} registros</span>
          </div>
          <div v-if="!(selectedItem.movements || []).length" class="inventory-detail-empty">
            Sin movimientos registrados.
          </div>
          <div v-else class="table-responsive">
            <table class="table align-middle mb-0 inventory-movements-table">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Movimiento</th>
                  <th>Cantidad</th>
                  <th>Stock</th>
                  <th>Responsable</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="movement in selectedItem.movements.slice(0, 8)" :key="movement.id">
                  <td>{{ formatInfirmaryDateTime(movement.moved_at) }}</td>
                  <td class="text-capitalize">{{ movement.movement_type }}</td>
                  <td>{{ movement.quantity }} {{ selectedItem.unit || "" }}</td>
                  <td>{{ movement.stock_before }} → {{ movement.stock_after }}</td>
                  <td>{{ movement.performed_by?.name || "-" }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <div class="d-flex justify-content-end pt-3">
          <BButton variant="outline-secondary" @click="showDetailModal = false">Cerrar</BButton>
        </div>
      </div>
    </BModal>

    <BModal v-model="showModal" :title="modalTitle" size="xl" hide-footer>
      <div class="inventory-form-heading">
        <span :class="['inventory-record-type', { medication: formIsMedication }]">
          <i :class="formIsMedication ? 'mdi mdi-pill' : 'mdi mdi-bandage'"></i>
          {{ formIsMedication ? "Medicamento" : "Insumo general" }}
        </span>
        <small v-if="isEditing" class="text-muted">El tipo de registro no cambia durante la edición.</small>
      </div>

      <div class="row g-3">
        <div :class="formIsMedication ? 'col-md-4' : 'col-md-6'">
          <label class="form-label">Nombre <span class="text-danger">*</span></label>
          <BFormInput v-model="form.name" :placeholder="formIsMedication ? 'Ej: Paracetamol' : 'Ej: Apósito estéril'" />
        </div>
        <div v-if="formIsMedication" class="col-md-4">
          <label class="form-label">Nombre comercial</label>
          <BFormInput v-model="form.commercial_name" placeholder="Ej: Kitadol" />
        </div>
        <div v-if="formIsMedication" class="col-md-4">
          <label class="form-label">Principio activo</label>
          <BFormInput v-model="form.active_ingredient" />
        </div>

        <div class="col-md-3">
          <label class="form-label">Presentación</label>
          <BFormInput v-model="form.presentation" :placeholder="formIsMedication ? 'Comprimidos, jarabe...' : 'Caja, paquete, rollo...'" />
        </div>
        <div v-if="formIsMedication" class="col-md-3">
          <label class="form-label">Concentración</label>
          <BFormInput v-model="form.concentration" placeholder="Ej: 500 mg" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Unidad de stock</label>
          <BFormInput v-model="form.unit" placeholder="unidad, caja, ml..." />
        </div>
        <div v-if="formIsMedication" class="col-md-3">
          <label class="form-label">Laboratorio</label>
          <BFormInput v-model="form.laboratory" />
        </div>

        <div v-if="!isEditing" class="col-md-3">
          <label class="form-label">Stock inicial</label>
          <BFormInput v-model="form.initial_stock" type="number" min="0" step="0.01" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Stock mínimo</label>
          <BFormInput v-model="form.minimum_stock" type="number" min="0" step="0.01" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Ubicación física</label>
          <BFormInput v-model="form.physical_location" placeholder="Ej: Gabinete A, bandeja 2" />
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
        <div class="col-md-3 inventory-active-field">
          <div class="form-check">
            <input id="inventory-active" v-model="form.active" class="form-check-input" type="checkbox" />
            <label class="form-check-label" for="inventory-active">Registro activo</label>
          </div>
        </div>

        <template v-if="formIsMedication">
          <div class="col-12">
            <div class="inventory-guardian-section">
              <div class="row g-3">
                <div class="col-md-5">
                  <label class="form-label">Origen del medicamento</label>
                  <BFormSelect v-model="form.source_type" :options="formSourceOptions" @change="onSourceChange" />
                </div>

                <template v-if="formIsGuardianMedication">
                  <div class="col-12">
                    <label class="form-label">Estudiante <span class="text-danger">*</span></label>
                    <InfirmaryStudentSearch
                      placeholder="Buscar estudiante por nombre, apellido o RUT"
                      button-label="Buscar"
                      help-title="Ayuda: estudiante asociada"
                      help-text="Selecciona la estudiante a la que pertenece el medicamento entregado por el apoderado."
                      @selected="selectStudent"
                    />
                  </div>
                  <div v-if="selectedStudent" class="col-12">
                    <div class="inventory-selected-student">
                      <div>
                        <strong>{{ studentName(selectedStudent) }}</strong>
                        <div class="small text-muted">
                          {{ selectedStudent.rut || "Sin RUT" }} · {{ selectedStudent.course || "Sin curso" }}
                        </div>
                        <div v-if="selectedStudent.guardian_name" class="small text-muted">
                          Apoderado registrado: {{ selectedStudent.guardian_name }}
                        </div>
                      </div>
                      <button type="button" class="btn btn-sm btn-outline-secondary" @click="clearStudent">Cambiar</button>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Apoderado que entrega <span class="text-danger">*</span></label>
                    <BFormInput v-model="form.received_from_guardian" placeholder="Nombre completo" />
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Fecha y hora de recepción <span class="text-danger">*</span></label>
                    <BFormInput v-model="form.received_at" type="datetime-local" />
                  </div>
                </template>
              </div>
            </div>
          </div>
        </template>

        <div class="col-12">
          <label class="form-label">Observaciones</label>
          <BFormTextarea
            v-model="form.observations"
            rows="3"
            :placeholder="formIsGuardianMedication ? 'Indicaciones de custodia, envase recibido o información entregada por el apoderado.' : 'Información relevante para el control del inventario.'"
          />
        </div>
        <div class="col-12 d-flex justify-content-end gap-2">
          <BButton variant="outline-secondary" @click="cancelModal">Cancelar</BButton>
          <BButton variant="primary" :disabled="saving" @click="save">
            {{ saving ? "Guardando..." : isEditing ? "Guardar cambios" : `Registrar ${formIsMedication ? "medicamento" : "insumo"}` }}
          </BButton>
        </div>
      </div>
    </BModal>

    <BModal v-model="showMovementModal" title="Registrar movimiento de inventario" size="lg" hide-footer>
      <div v-if="selectedItem" class="inventory-movement-summary">
        <strong>{{ itemName(selectedItem) }}</strong>
        <span>Stock actual: {{ selectedItem.current_stock }} {{ selectedItem.unit || "" }}</span>
      </div>

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
        <div v-if="movementForm.movement_type === 'ajuste'" class="col-md-4">
          <label class="form-label">Dirección del ajuste</label>
          <BFormSelect v-model="movementForm.adjustment_direction" :options="adjustmentDirectionOptions" />
        </div>
        <div :class="movementForm.movement_type === 'ajuste' ? 'col-md-8' : 'col-12'">
          <label class="form-label">Motivo</label>
          <BFormInput v-model="movementForm.reason" placeholder="Ej: reposición, uso en atención o corrección de stock" />
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

<style scoped>
.infirmary-inventory-page {
  color: #2f3747;
}

.inventory-header,
.inventory-filter-header,
.inventory-table-heading {
  align-items: center;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
}

.inventory-header h4,
.inventory-filter-header h5,
.inventory-table-heading h5 {
  font-weight: 650;
}

.inventory-type-tabs {
  align-items: center;
  border-bottom: 1px solid #dfe5ee;
  display: flex;
  gap: 0.25rem;
}

.inventory-type-tab {
  align-items: center;
  background: transparent;
  border: 0;
  border-bottom: 3px solid transparent;
  color: #697386;
  display: inline-flex;
  font-size: 0.95rem;
  font-weight: 600;
  gap: 0.5rem;
  min-height: 44px;
  padding: 0.6rem 1rem;
}

.inventory-type-tab i {
  font-size: 1.15rem;
}

.inventory-type-tab:hover,
.inventory-type-tab:focus-visible {
  color: #4056c8;
}

.inventory-type-tab.active {
  border-bottom-color: #556ee6;
  color: #4056c8;
}

.inventory-filter-card,
.inventory-table-card {
  border-radius: 8px;
}

.inventory-checks,
.inventory-active-field {
  align-items: flex-start;
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
  justify-content: flex-end;
  padding-bottom: 0.6rem;
}

.inventory-filter-actions {
  align-items: flex-end;
  display: flex;
  gap: 0.5rem;
  justify-content: flex-end;
}

.inventory-table-wrap {
  margin-top: 1rem;
}

.inventory-table {
  min-width: 960px;
}

.inventory-table thead th {
  background: #f7f9fc;
  border-bottom: 1px solid #dfe5ee;
  color: #687287;
  font-size: 0.78rem;
  font-weight: 700;
  padding: 0.85rem 0.75rem;
  text-transform: uppercase;
}

.inventory-table tbody td {
  border-bottom: 1px solid #e9edf3;
  padding: 0.9rem 0.75rem;
  vertical-align: middle;
}

.inventory-table th:last-child,
.inventory-table td:last-child {
  box-shadow: -10px 0 12px -14px rgba(45, 55, 72, 0.65);
  min-width: 112px;
  position: sticky;
  right: 0;
  z-index: 2;
}

.inventory-table tbody td:last-child {
  background: rgba(238, 246, 255, 0.96);
}

.inventory-table thead th:last-child {
  background: #f7f9fc;
  z-index: 3;
}

.inventory-source-badge,
.inventory-record-type {
  align-items: center;
  background: #edf5f1;
  border: 1px solid #cce4d8;
  border-radius: 5px;
  color: #24704d;
  display: inline-flex;
  font-size: 0.78rem;
  font-weight: 600;
  gap: 0.4rem;
  padding: 0.25rem 0.5rem;
}

.inventory-source-badge.guardian,
.inventory-record-type.medication {
  background: #eef2ff;
  border-color: #d4dcfa;
  color: #4056c8;
}

.infirmary-inventory-actions {
  display: grid;
  gap: 8px;
  grid-template-columns: repeat(2, 42px);
  justify-content: end;
}

.inventory-empty-state {
  color: #758095;
  padding: 2.5rem 1rem !important;
  text-align: center;
}

.inventory-detail-empty {
  color: #758095;
  padding: 1.5rem 0;
  text-align: center;
}

.inventory-detail-summary {
  align-items: center;
  border-bottom: 1px solid #e4e8ef;
  display: flex;
  gap: 0.85rem;
  padding: 0 0 1rem;
}

.inventory-detail-summary-icon {
  align-items: center;
  background: #eef2ff;
  border: 1px solid #d4dcfa;
  border-radius: 6px;
  color: #4056c8;
  display: flex;
  flex: 0 0 42px;
  font-size: 1.25rem;
  height: 42px;
  justify-content: center;
}

.inventory-detail-summary-copy {
  min-width: 0;
  flex: 1 1 auto;
}

.inventory-detail-title {
  font-size: 1.05rem;
  font-weight: 650;
}

.inventory-detail-section {
  padding: 1.15rem 0;
}

.inventory-detail-section + .inventory-detail-section {
  border-top: 1px solid #e8ecf2;
}

.inventory-detail-section h6 {
  font-size: 0.9rem;
  font-weight: 650;
  margin: 0 0 0.85rem;
}

.inventory-detail-section-heading {
  align-items: center;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
}

.inventory-detail-grid {
  display: grid;
  gap: 0.85rem 1.5rem;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  margin: 0;
}

.inventory-detail-field {
  min-width: 0;
}

.inventory-detail-field dt {
  color: #758095;
  font-size: 0.76rem;
  font-weight: 600;
  margin-bottom: 0.2rem;
}

.inventory-detail-field dd {
  color: #2f3747;
  font-size: 0.9rem;
  font-weight: 550;
  margin: 0;
  overflow-wrap: anywhere;
}

.inventory-detail-field--emphasis dd {
  color: #4056c8;
  font-weight: 700;
}

.inventory-detail-notes {
  background: #f7f9fc;
  border-left: 3px solid #cfd8ea;
  margin: 0;
  padding: 0.75rem 0.9rem;
  white-space: pre-wrap;
}

.inventory-movements-table {
  min-width: 720px;
}

.inventory-movements-table thead th {
  background: #f7f9fc;
  color: #687287;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.65rem;
  text-transform: uppercase;
}

.inventory-movements-table tbody td {
  border-color: #e9edf3;
  font-size: 0.85rem;
  padding: 0.65rem;
}

.inventory-form-heading {
  align-items: center;
  border-bottom: 1px solid #e4e8ef;
  display: flex;
  gap: 0.75rem;
  justify-content: space-between;
  margin-bottom: 1rem;
  padding-bottom: 0.85rem;
}

.inventory-guardian-section {
  background: #f8f9fc;
  border: 1px solid #dfe5ee;
  border-radius: 7px;
  padding: 1rem;
}

.inventory-selected-student {
  align-items: center;
  background: #fff;
  border: 1px solid #dfe5ee;
  border-radius: 6px;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
  padding: 0.75rem 0.9rem;
}

.inventory-movement-summary {
  align-items: center;
  background: #f7f9fc;
  border: 1px solid #dfe5ee;
  border-radius: 6px;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
  margin-bottom: 1rem;
  padding: 0.75rem 0.9rem;
}

@media (max-width: 767.98px) {
  .inventory-header,
  .inventory-form-heading,
  .inventory-movement-summary {
    align-items: stretch;
    flex-direction: column;
  }

  .inventory-header > .d-flex,
  .inventory-filter-actions {
    justify-content: flex-start;
  }

  .inventory-type-tabs {
    align-items: stretch;
  }

  .inventory-type-tab {
    flex: 1 1 0;
    justify-content: center;
    padding-left: 0.5rem;
    padding-right: 0.5rem;
  }

  .inventory-filter-actions .btn {
    flex: 1 1 0;
  }

  .inventory-detail-summary {
    align-items: flex-start;
  }

  .inventory-detail-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .inventory-detail-section-heading {
    align-items: flex-start;
    flex-direction: column;
    gap: 0.15rem;
  }
}

@media (max-width: 479.98px) {
  .inventory-detail-grid {
    grid-template-columns: 1fr;
  }
}
</style>
