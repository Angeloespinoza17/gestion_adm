<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import HelpButton from "../../components/risk-prevention/help-button.vue";
import StatusBadge from "../../components/risk-prevention/status-badge.vue";
import {
  confirmRiskAction,
  formatRiskDate,
  formatRiskError,
  showRiskError,
  showRiskSuccess,
  showRiskWarning,
} from "../../components/risk-prevention/module-utils";

const emptyItem = () => ({
  id: null,
  name: "",
  epp_type: "",
  stock: 0,
  minimum_stock: 0,
  unit: "unidad",
  description: "",
  active: true,
});

const emptyDelivery = () => ({
  id: null,
  epp_item_id: null,
  employee_name: "",
  quantity: 1,
  delivered_at: "",
  replacement_due_at: "",
  status: "vigente",
  observations: "",
});

export default {
  components: { Layout, LoadingState, HelpButton, StatusBadge },
  data() {
    return {
      loadingItems: false,
      loadingDeliveries: false,
      savingItem: false,
      savingDelivery: false,
      warningShown: false,
      error: null,
      catalogs: { epp_types: [], epp_items: [], employees: [] },
      itemFilters: { search: "", epp_type: "", low_stock: false },
      deliveryFilters: { search: "", status: "", epp_item_id: "" },
      items: [],
      deliveries: [],
      showItemModal: false,
      showDeliveryModal: false,
      itemForm: emptyItem(),
      deliveryForm: emptyDelivery(),
    };
  },
  computed: {
    isEditingItem() {
      return Boolean(this.itemForm.id);
    },
    isEditingDelivery() {
      return Boolean(this.deliveryForm.id);
    },
  },
  mounted() {
    this.loadCatalogs();
    this.loadItems();
    this.loadDeliveries();
  },
  methods: {
    formatRiskDate,
    async loadCatalogs() {
      const response = await axios.get("/api/risk-prevention/catalogs");
      this.catalogs = response.data;
    },
    async loadItems() {
      this.loadingItems = true;
      try {
        const response = await axios.get("/api/risk-prevention/epp/items", {
          params: { ...this.itemFilters, low_stock: this.itemFilters.low_stock ? 1 : "", per_page: 100 },
        });
        this.items = response.data.data || [];
      } catch (error) {
        this.error = formatRiskError(error, "No se pudo cargar el inventario EPP.");
        showRiskError(this.error);
      } finally {
        this.loadingItems = false;
      }
    },
    async loadDeliveries() {
      this.loadingDeliveries = true;
      try {
        const response = await axios.get("/api/risk-prevention/epp/deliveries", {
          params: { ...this.deliveryFilters, per_page: 100 },
        });
        this.deliveries = response.data.data || [];
        this.maybeShowWarnings();
      } catch (error) {
        this.error = formatRiskError(error, "No se pudieron cargar las entregas EPP.");
        showRiskError(this.error);
      } finally {
        this.loadingDeliveries = false;
      }
    },
    async maybeShowWarnings() {
      if (this.warningShown) return;
      const due = this.deliveries.filter((item) => item.current_status === "por_reponer").length;
      if (!due) return;
      this.warningShown = true;
      await showRiskWarning(`Hay ${due} entregas EPP que requieren reposición.`, "Reposiciones de EPP");
    },
    openCreateItem() {
      this.itemForm = emptyItem();
      this.showItemModal = true;
    },
    openEditItem(item) {
      this.itemForm = {
        id: item.id,
        name: item.name || "",
        epp_type: item.epp_type || "",
        stock: item.stock ?? 0,
        minimum_stock: item.minimum_stock ?? 0,
        unit: item.unit || "unidad",
        description: item.description || "",
        active: Boolean(item.active),
      };
      this.showItemModal = true;
    },
    openCreateDelivery() {
      this.deliveryForm = {
        ...emptyDelivery(),
        delivered_at: new Date().toISOString().slice(0, 10),
      };
      this.showDeliveryModal = true;
    },
    openEditDelivery(item) {
      this.deliveryForm = {
        id: item.id,
        epp_item_id: item.epp_item_id ?? item.item?.id ?? null,
        employee_name: item.employee_name || "",
        quantity: item.quantity ?? 1,
        delivered_at: item.delivered_at || "",
        replacement_due_at: item.replacement_due_at || "",
        status: item.status || "vigente",
        observations: item.observations || "",
      };
      this.showDeliveryModal = true;
    },
    async saveItem() {
      this.savingItem = true;
      try {
        const payload = { ...this.itemForm };
        delete payload.id;
        if (this.isEditingItem) {
          await axios.put(`/api/risk-prevention/epp/items/${this.itemForm.id}`, payload);
          await showRiskSuccess("El EPP fue actualizado correctamente.");
        } else {
          await axios.post("/api/risk-prevention/epp/items", payload);
          await showRiskSuccess("El EPP fue creado correctamente.");
        }
        this.showItemModal = false;
        this.loadCatalogs();
        this.loadItems();
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo guardar el EPP."));
      } finally {
        this.savingItem = false;
      }
    },
    async saveDelivery() {
      this.savingDelivery = true;
      try {
        const payload = { ...this.deliveryForm };
        delete payload.id;
        if (this.isEditingDelivery) {
          await axios.put(`/api/risk-prevention/epp/deliveries/${this.deliveryForm.id}`, payload);
          await showRiskSuccess("La entrega fue actualizada correctamente.");
        } else {
          await axios.post("/api/risk-prevention/epp/deliveries", payload);
          await showRiskSuccess("La entrega fue registrada correctamente.");
        }
        this.showDeliveryModal = false;
        this.loadDeliveries();
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo guardar la entrega."));
      } finally {
        this.savingDelivery = false;
      }
    },
    async removeItem(item) {
      const result = await confirmRiskAction({
        title: "Eliminar EPP",
        text: `Se eliminará ${item.name}.`,
        confirmButtonText: "Sí, eliminar",
      });
      if (!result.isConfirmed) return;

      try {
        await axios.delete(`/api/risk-prevention/epp/items/${item.id}`);
        await showRiskSuccess("El EPP fue eliminado correctamente.");
        this.loadCatalogs();
        this.loadItems();
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo eliminar el EPP."));
      }
    },
    async removeDelivery(item) {
      const result = await confirmRiskAction({
        title: "Eliminar entrega",
        text: `Se eliminará la entrega a ${item.employee_name}.`,
        confirmButtonText: "Sí, eliminar",
      });
      if (!result.isConfirmed) return;

      try {
        await axios.delete(`/api/risk-prevention/epp/deliveries/${item.id}`);
        await showRiskSuccess("La entrega fue eliminada correctamente.");
        this.loadDeliveries();
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo eliminar la entrega."));
      }
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">EPP y Elementos de Seguridad</h4>
        <div class="text-muted">Inventario de EPP y control de entregas por funcionario.</div>
      </div>
      <HelpButton
        title="Ayuda: EPP y elementos de seguridad"
        text="Permite controlar stock, entregas, reposiciones y trazabilidad de elementos de protección personal."
      />
    </div>

    <div class="row g-3">
      <div class="col-xl-5">
        <BCard class="h-100">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h5 class="mb-0">Inventario EPP</h5>
              <div class="small text-muted">Stock y mínimos críticos.</div>
            </div>
            <div class="d-flex gap-2">
              <HelpButton
                title="Ayuda: inventario EPP"
                text="Registra tipos de EPP y controla el stock disponible para evitar quiebres o faltantes críticos."
              />
              <BButton size="sm" variant="primary" @click="openCreateItem">Nuevo EPP</BButton>
            </div>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <BFormInput v-model="itemFilters.search" placeholder="Buscar EPP" @keyup.enter="loadItems" />
            </div>
            <div class="col-md-4">
              <BFormInput v-model="itemFilters.epp_type" list="risk-epp-types" placeholder="Tipo de EPP" />
              <datalist id="risk-epp-types">
                <option v-for="item in catalogs.epp_types" :key="item" :value="item"></option>
              </datalist>
            </div>
            <div class="col-md-2 d-flex align-items-center">
              <BFormCheckbox v-model="itemFilters.low_stock" switch>Crítico</BFormCheckbox>
            </div>
            <div class="col-12">
              <BButton size="sm" variant="secondary" @click="loadItems">Filtrar</BButton>
            </div>
          </div>

          <LoadingState v-if="loadingItems" message="Cargando inventario EPP..." compact />
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>EPP</th>
                  <th>Tipo</th>
                  <th>Stock</th>
                  <th>Estado</th>
                  <th class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in items" :key="item.id">
                  <td class="fw-semibold">{{ item.name }}</td>
                  <td>{{ item.epp_type }}</td>
                  <td>{{ item.stock }} / mín. {{ item.minimum_stock }} {{ item.unit }}</td>
                  <td><StatusBadge :status="item.stock_status" /></td>
                  <td class="text-end">
                    <div class="d-flex justify-content-end gap-2">
                      <BButton size="sm" variant="outline-primary" @click="openEditItem(item)">Editar</BButton>
                      <BButton size="sm" variant="outline-danger" @click="removeItem(item)">Eliminar</BButton>
                    </div>
                  </td>
                </tr>
                <tr v-if="!items.length">
                  <td colspan="5" class="text-center text-muted py-3">Sin inventario registrado.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </div>

      <div class="col-xl-7">
        <BCard class="h-100">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h5 class="mb-0">Entregas por funcionario</h5>
              <div class="small text-muted">Historial y reposiciones pendientes.</div>
            </div>
            <div class="d-flex gap-2">
              <HelpButton
                title="Ayuda: entregas EPP"
                text="Esta sección registra qué EPP recibió cada funcionario y cuándo corresponde reponerlo."
              />
              <BButton size="sm" variant="primary" @click="openCreateDelivery">Nueva entrega</BButton>
            </div>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-md-5">
              <BFormInput v-model="deliveryFilters.search" placeholder="Funcionario u observación" @keyup.enter="loadDeliveries" />
            </div>
            <div class="col-md-3">
              <BFormSelect v-model="deliveryFilters.status" :options="[
                { value: '', text: 'Todos los estados' },
                { value: 'vigente', text: 'Vigente' },
                { value: 'por_reponer', text: 'Por reponer' },
                { value: 'repuesto', text: 'Repuesto' },
              ]" />
            </div>
            <div class="col-md-4">
              <BFormSelect
                v-model="deliveryFilters.epp_item_id"
                :options="[{ value: '', text: 'Todos los EPP' }].concat((catalogs.epp_items || []).map((item) => ({ value: item.id, text: item.name })))"
              />
            </div>
            <div class="col-12">
              <BButton size="sm" variant="secondary" @click="loadDeliveries">Filtrar</BButton>
            </div>
          </div>

          <LoadingState v-if="loadingDeliveries" message="Cargando entregas..." compact />
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Funcionario</th>
                  <th>EPP</th>
                  <th>Entrega</th>
                  <th>Reposición</th>
                  <th>Estado</th>
                  <th class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in deliveries" :key="item.id">
                  <td>{{ item.employee_name }}</td>
                  <td>{{ item.item?.name || "-" }}</td>
                  <td>{{ formatRiskDate(item.delivered_at) }}</td>
                  <td>{{ formatRiskDate(item.replacement_due_at) }}</td>
                  <td><StatusBadge :status="item.current_status" /></td>
                  <td class="text-end">
                    <div class="d-flex justify-content-end gap-2">
                      <BButton size="sm" variant="outline-primary" @click="openEditDelivery(item)">Editar</BButton>
                      <BButton size="sm" variant="outline-danger" @click="removeDelivery(item)">Eliminar</BButton>
                    </div>
                  </td>
                </tr>
                <tr v-if="!deliveries.length">
                  <td colspan="6" class="text-center text-muted py-3">Sin entregas registradas.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </div>
    </div>

    <BModal v-model="showItemModal" size="lg" :title="isEditingItem ? 'Editar EPP' : 'Nuevo EPP'" hide-footer>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-muted small">Define el elemento, su stock y mínimo operativo.</div>
        <HelpButton title="Ayuda del formulario" text="Registra el EPP y define un mínimo de stock para detectar faltantes." />
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nombre</label>
          <BFormInput v-model="itemForm.name" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Tipo de EPP</label>
          <BFormInput v-model="itemForm.epp_type" list="risk-epp-types-item" />
          <datalist id="risk-epp-types-item">
            <option v-for="item in catalogs.epp_types" :key="item" :value="item"></option>
          </datalist>
        </div>
        <div class="col-md-3">
          <label class="form-label">Stock</label>
          <BFormInput v-model="itemForm.stock" type="number" min="0" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Mínimo</label>
          <BFormInput v-model="itemForm.minimum_stock" type="number" min="0" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Unidad</label>
          <BFormInput v-model="itemForm.unit" />
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <BFormCheckbox v-model="itemForm.active">Activo</BFormCheckbox>
        </div>
        <div class="col-12">
          <label class="form-label">Descripción</label>
          <BFormTextarea v-model="itemForm.description" rows="3" />
        </div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="secondary" @click="showItemModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="savingItem" @click="saveItem">{{ savingItem ? "Guardando..." : "Guardar" }}</BButton>
      </div>
    </BModal>

    <BModal v-model="showDeliveryModal" size="lg" :title="isEditingDelivery ? 'Editar entrega' : 'Nueva entrega'" hide-footer>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-muted small">Asocia el EPP entregado al funcionario y define su reposición.</div>
        <HelpButton title="Ayuda del formulario" text="Registra fecha de entrega, reposición esperada y observaciones para el historial del funcionario." />
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">EPP</label>
          <BFormSelect
            v-model="deliveryForm.epp_item_id"
            :options="[{ value: null, text: 'Selecciona un EPP' }].concat((catalogs.epp_items || []).map((item) => ({ value: item.id, text: item.name })))"
          />
        </div>
        <div class="col-md-6">
          <label class="form-label">Funcionario</label>
          <BFormInput v-model="deliveryForm.employee_name" list="risk-epp-employees" />
          <datalist id="risk-epp-employees">
            <option v-for="item in catalogs.employees || []" :key="item" :value="item"></option>
          </datalist>
        </div>
        <div class="col-md-3">
          <label class="form-label">Cantidad</label>
          <BFormInput v-model="deliveryForm.quantity" type="number" min="1" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Entrega</label>
          <BFormInput v-model="deliveryForm.delivered_at" type="date" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Reposición</label>
          <BFormInput v-model="deliveryForm.replacement_due_at" type="date" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="deliveryForm.status" :options="[
            { value: 'vigente', text: 'Vigente' },
            { value: 'por_reponer', text: 'Por reponer' },
            { value: 'repuesto', text: 'Repuesto' },
          ]" />
        </div>
        <div class="col-12">
          <label class="form-label">Observaciones</label>
          <BFormTextarea v-model="deliveryForm.observations" rows="3" />
        </div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="secondary" @click="showDeliveryModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="savingDelivery" @click="saveDelivery">
          {{ savingDelivery ? "Guardando..." : "Guardar" }}
        </BButton>
      </div>
    </BModal>
  </Layout>
</template>
