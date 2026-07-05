<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";

const emptyAuditForm = () => ({
  audited_at: "",
  found_items_count: null,
  notes: "",
});

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loadingDependencies: false,
      loadingDetail: false,
      savingAudit: false,
      dependencySearch: "",
      dependencies: [],
      dependenciesPagination: { current_page: 1, last_page: 1, total: 0 },
      selectedDependency: null,
      items: [],
      audits: [],
      itemCatalogs: { item_types: [], statuses: [], conditions: [] },
      itemFilters: {
        search: "",
        item_type: "",
        status: "",
        condition: "",
      },
      itemsPagination: { current_page: 1, last_page: 1, total: 0 },
      showDetailModal: false,
      showAuditModal: false,
      auditForm: emptyAuditForm(),
      error: null,
      success: null,
    };
  },
  computed: {
    summaryCards() {
      const totalItems = this.dependencies.reduce(
        (total, dependency) => total + Number(dependency.active_items_count || 0),
        0
      );
      const withoutInventory = this.dependencies.filter(
        (dependency) => dependency.inventory_status === "sin_inventario"
      ).length;
      const outdated = this.dependencies.filter(
        (dependency) => dependency.inventory_status === "desactualizado"
      ).length;
      const lowStock = this.dependencies.reduce(
        (total, dependency) => total + Number(dependency.low_stock_items_count || 0),
        0
      );

      return [
        {
          label: "Dependencias",
          value: this.formatNumber(this.dependenciesPagination.total),
          detail: "Espacios con bienes asignados",
          tone: "blue",
          icon: "mdi-domain",
        },
        {
          label: "Bienes activos",
          value: this.formatNumber(totalItems),
          detail: "En la página actual",
          tone: "green",
          icon: "mdi-package-variant-closed",
        },
        {
          label: "Sin inventario",
          value: this.formatNumber(withoutInventory),
          detail: "Sin revisión registrada",
          tone: "amber",
          icon: "mdi-calendar-alert",
        },
        {
          label: "Desactualizadas",
          value: this.formatNumber(outdated),
          detail: "Más de 6 meses",
          tone: "red",
          icon: "mdi-clock-alert-outline",
        },
        {
          label: "Stock bajo",
          value: this.formatNumber(lowStock),
          detail: "Alertas en dependencias",
          tone: "slate",
          icon: "mdi-alert-outline",
        },
      ];
    },
    typeOptions() {
      return [
        { value: "", text: "Todos los tipos" },
        { value: "asset", text: "Activo fijo" },
        { value: "consumable", text: "Consumible" },
      ];
    },
    statusOptions() {
      return [
        { value: "", text: "Todos los estados" },
        ...(this.itemCatalogs.statuses || []).map((status) => ({
          value: status,
          text: status,
        })),
      ];
    },
    conditionOptions() {
      return [
        { value: "", text: "Todas las condiciones" },
        ...(this.itemCatalogs.conditions || []).map((condition) => ({
          value: condition,
          text: condition,
        })),
      ];
    },
  },
  mounted() {
    this.loadDependencies();
  },
  methods: {
    async loadDependencies(page = 1) {
      this.loadingDependencies = true;
      this.error = null;

      try {
        const response = await axios.get("/api/inventory/management/dependencies", {
          params: {
            page,
            search: this.dependencySearch,
          },
        });

        this.dependencies = response.data.data || [];
        this.dependenciesPagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          total: response.data.total,
        };

        if (this.selectedDependency) {
          const refreshed = this.dependencies.find(
            (dependency) => dependency.id === this.selectedDependency.id
          );
          if (refreshed) this.selectedDependency = refreshed;
        }
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loadingDependencies = false;
      }
    },
    async openDetailModal(dependency) {
      this.showDetailModal = true;
      this.selectedDependency = dependency;
      this.itemFilters = {
        search: "",
        item_type: "",
        status: "",
        condition: "",
      };
      await this.loadDependencyDetail(1);
    },
    async loadDependencyDetail(page = 1) {
      if (!this.selectedDependency) return;

      this.loadingDetail = true;
      this.error = null;

      try {
        const response = await axios.get(
          `/api/inventory/management/dependencies/${this.selectedDependency.id}`,
          {
            params: {
              page,
              search: this.itemFilters.search,
              item_type: this.itemFilters.item_type,
              status: this.itemFilters.status,
              condition: this.itemFilters.condition,
            },
          }
        );

        this.selectedDependency = response.data.dependency;
        this.items = response.data.items?.data || [];
        this.itemsPagination = {
          current_page: response.data.items?.current_page || 1,
          last_page: response.data.items?.last_page || 1,
          total: response.data.items?.total || 0,
        };
        this.audits = response.data.audits || [];
        this.itemCatalogs = response.data.catalogs || {
          item_types: [],
          statuses: [],
          conditions: [],
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loadingDetail = false;
      }
    },
    openAuditModal(dependency = null) {
      if (dependency && (!this.selectedDependency || dependency.id !== this.selectedDependency.id)) {
        this.selectedDependency = dependency;
      }

      this.auditForm = {
        ...emptyAuditForm(),
        audited_at: this.toDateTimeLocal(new Date()),
        found_items_count: this.selectedDependency?.active_items_count ?? 0,
      };
      this.showAuditModal = true;
    },
    async saveAudit() {
      if (!this.selectedDependency) return;

      this.savingAudit = true;
      this.error = null;
      this.success = null;

      try {
        await axios.post(
          `/api/inventory/management/dependencies/${this.selectedDependency.id}/audits`,
          {
            audited_at: this.auditForm.audited_at || null,
            found_items_count:
              this.auditForm.found_items_count === "" ||
              this.auditForm.found_items_count === null
                ? null
                : Number(this.auditForm.found_items_count),
            notes: this.auditForm.notes || null,
          }
        );

        this.success = "Inventario registrado correctamente.";
        this.showAuditModal = false;
        await this.loadDependencies(this.dependenciesPagination.current_page);
        if (this.showDetailModal) {
          await this.loadDependencyDetail(this.itemsPagination.current_page);
        }
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.savingAudit = false;
      }
    },
    resetItemFilters() {
      this.itemFilters = {
        search: "",
        item_type: "",
        status: "",
        condition: "",
      };
      this.loadDependencyDetail(1);
    },
    formatNumber(value) {
      return new Intl.NumberFormat("es-CL").format(Number(value || 0));
    },
    formatDateTime(value) {
      if (!value) return "Sin inventario";

      const date = new Date(String(value).replace(" ", "T"));
      if (Number.isNaN(date.getTime())) return value;

      const day = String(date.getDate()).padStart(2, "0");
      const month = String(date.getMonth() + 1).padStart(2, "0");
      const year = date.getFullYear();
      const hours = String(date.getHours()).padStart(2, "0");
      const minutes = String(date.getMinutes()).padStart(2, "0");

      return `${day}-${month}-${year} ${hours}:${minutes}`;
    },
    toDateTimeLocal(value) {
      const date = value instanceof Date ? value : new Date(value);
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, "0");
      const day = String(date.getDate()).padStart(2, "0");
      const hours = String(date.getHours()).padStart(2, "0");
      const minutes = String(date.getMinutes()).padStart(2, "0");

      return `${year}-${month}-${day}T${hours}:${minutes}`;
    },
    inventoryStatusLabel(status) {
      const labels = {
        vigente: "Vigente",
        desactualizado: "Desactualizado",
        sin_inventario: "Sin inventario",
      };

      return labels[status] || "Sin inventario";
    },
    typeLabel(value) {
      const labels = {
        asset: "Activo fijo",
        consumable: "Consumible",
      };

      return labels[value] || value || "-";
    },
    statusTone(value) {
      const normalized = String(value || "").toLowerCase();

      if (normalized.includes("sin") || normalized.includes("desactualizado")) return "warning";
      if (normalized.includes("critico") || normalized.includes("crítico") || normalized.includes("malo")) return "danger";
      if (normalized.includes("vigente") || normalized.includes("bueno") || normalized.includes("activo") || normalized.includes("uso")) return "success";
      if (normalized.includes("revision") || normalized.includes("revisión") || normalized.includes("regular")) return "warning";

      return "secondary";
    },
    stockText(item) {
      if (item.item_type !== "consumable") return "-";

      return `${item.stock_quantity ?? 0} / ${item.minimum_stock ?? 0} ${
        item.unit_of_measure || ""
      }`.trim();
    },
    formatError(error) {
      return (
        error?.response?.data?.message ||
        error?.response?.data?.errors?.[
          Object.keys(error.response.data.errors || {})[0]
        ]?.[0] ||
        error?.message ||
        "Error desconocido"
      );
    },
  },
};
</script>

<template>
  <Layout>
    <div class="inventory-management-page">
      <div class="inventory-management-header">
        <div>
          <div class="inventory-management-eyebrow">Inventario</div>
          <h4>Gestión de inventario</h4>
          <p>Controla dependencias, última revisión y bienes asignados a cada espacio.</p>
        </div>
        <BButton
          variant="secondary"
          class="inventory-management-refresh"
          :disabled="loadingDependencies"
          @click="loadDependencies(dependenciesPagination.current_page)"
        >
          <i class="mdi mdi-refresh"></i>
          <span>{{ loadingDependencies ? "Actualizando..." : "Actualizar" }}</span>
        </BButton>
      </div>

      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
      <BAlert v-if="success" variant="success" show class="mb-3">{{ success }}</BAlert>

      <div class="inventory-summary-grid">
        <div
          v-for="card in summaryCards"
          :key="card.label"
          class="inventory-summary-card"
          :class="`inventory-summary-card--${card.tone}`"
        >
          <div class="inventory-summary-icon">
            <i :class="`mdi ${card.icon}`"></i>
          </div>
          <div class="inventory-summary-content">
            <div class="inventory-summary-label">{{ card.label }}</div>
            <strong>{{ card.value }}</strong>
            <span>{{ card.detail }}</span>
          </div>
        </div>
      </div>

      <section class="inventory-panel">
        <div class="inventory-panel-header">
          <div>
            <div class="inventory-management-eyebrow">Dependencias</div>
            <h5>Último inventario por espacio</h5>
          </div>
          <div class="inventory-search-bar">
            <BFormInput
              v-model="dependencySearch"
              placeholder="Buscar dependencia, código, sector..."
              @keyup.enter="loadDependencies(1)"
            />
            <BButton variant="primary" @click="loadDependencies(1)">Buscar</BButton>
          </div>
        </div>

        <LoadingState
          v-if="loadingDependencies && dependencies.length === 0"
          message="Cargando dependencias..."
          compact
        />
        <div v-else class="table-responsive inventory-dependency-table-wrap">
          <table class="table inventory-dependency-table">
            <thead>
              <tr>
                <th>Dependencia</th>
                <th>Ubicación</th>
                <th>Bienes</th>
                <th>Último inventario</th>
                <th>Alertas</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="dependency in dependencies" :key="dependency.id">
                <td>
                  <div class="inventory-dependency-name">{{ dependency.name }}</div>
                  <span class="inventory-code-pill">{{ dependency.code }}</span>
                </td>
                <td>
                  <div class="inventory-location-text">
                    {{ dependency.distribution || dependency.zone || "-" }}
                  </div>
                  <small>{{ dependency.sector || dependency.usage || "-" }}</small>
                </td>
                <td>
                  <div class="inventory-count-stack">
                    <span class="inventory-count-pill inventory-count-pill--blue">
                      {{ formatNumber(dependency.active_items_count) }} activos
                    </span>
                    <span>{{ formatNumber(dependency.assets_count) }} fijos · {{ formatNumber(dependency.consumables_count) }} consumibles</span>
                  </div>
                </td>
                <td>
                  <span
                    class="inventory-status-pill"
                    :class="`inventory-status-pill--${dependency.inventory_status}`"
                  >
                    {{ inventoryStatusLabel(dependency.inventory_status) }}
                  </span>
                  <div class="inventory-date-text">
                    {{ formatDateTime(dependency.latest_inventory?.audited_at) }}
                  </div>
                </td>
                <td>
                  <div class="inventory-alert-stack">
                    <span
                      class="inventory-count-pill"
                      :class="dependency.critical_items_count ? 'inventory-count-pill--red' : 'inventory-count-pill--slate'"
                    >
                      {{ formatNumber(dependency.critical_items_count) }} críticos
                    </span>
                    <span
                      class="inventory-count-pill"
                      :class="dependency.low_stock_items_count ? 'inventory-count-pill--amber' : 'inventory-count-pill--slate'"
                    >
                      {{ formatNumber(dependency.low_stock_items_count) }} stock bajo
                    </span>
                  </div>
                </td>
                <td>
                  <div class="inventory-actions">
                    <BButton size="sm" variant="outline-primary" @click="openDetailModal(dependency)">
                      Ver bienes
                    </BButton>
                    <BButton size="sm" variant="warning" @click="openAuditModal(dependency)">
                      Inventariar
                    </BButton>
                  </div>
                </td>
              </tr>
              <tr v-if="dependencies.length === 0">
                <td colspan="6">
                  <div class="inventory-empty-state">No hay dependencias para mostrar.</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="inventory-pagination-row">
          <BPagination
            v-model="dependenciesPagination.current_page"
            :per-page="18"
            :total-rows="dependenciesPagination.total"
            @update:model-value="loadDependencies"
          />
        </div>
      </section>

      <BModal
        v-model="showDetailModal"
        :title="selectedDependency ? `Detalle de dependencia · ${selectedDependency.code}` : 'Detalle de dependencia'"
        hide-footer
        scrollable
        size="xl"
        body-class="inventory-detail-modal-body"
      >
        <div v-if="selectedDependency" class="inventory-detail-modal">
          <div class="inventory-detail-hero">
            <div class="inventory-detail-identity">
              <div class="inventory-detail-icon">
                <i class="mdi mdi-domain"></i>
              </div>
              <div>
                <div class="inventory-management-eyebrow">Dependencia seleccionada</div>
                <h5>{{ selectedDependency.name }}</h5>
                <div class="inventory-detail-meta">
                  <span>{{ selectedDependency.code }}</span>
                  <span>{{ selectedDependency.distribution || selectedDependency.zone || "Sin ubicación" }}</span>
                  <span>{{ selectedDependency.sector || selectedDependency.usage || "Sin sector" }}</span>
                </div>
              </div>
            </div>
            <div class="inventory-detail-actions">
              <span
                class="inventory-status-pill"
                :class="`inventory-status-pill--${selectedDependency.inventory_status}`"
              >
                {{ inventoryStatusLabel(selectedDependency.inventory_status) }}
              </span>
              <BButton variant="warning" @click="openAuditModal()">Registrar inventario</BButton>
            </div>
          </div>

          <div class="inventory-detail-grid">
            <div class="inventory-detail-stat inventory-detail-stat--blue">
              <i class="mdi mdi-calendar-clock"></i>
              <span>Última revisión</span>
              <strong>{{ formatDateTime(selectedDependency.latest_inventory?.audited_at) }}</strong>
            </div>
            <div class="inventory-detail-stat inventory-detail-stat--green">
              <i class="mdi mdi-package-variant-closed"></i>
              <span>Bienes activos</span>
              <strong>{{ formatNumber(selectedDependency.active_items_count) }}</strong>
            </div>
            <div class="inventory-detail-stat inventory-detail-stat--red">
              <i class="mdi mdi-alert-circle-outline"></i>
              <span>Críticos</span>
              <strong>{{ formatNumber(selectedDependency.critical_items_count) }}</strong>
            </div>
            <div class="inventory-detail-stat inventory-detail-stat--amber">
              <i class="mdi mdi-package-down"></i>
              <span>Stock bajo</span>
              <strong>{{ formatNumber(selectedDependency.low_stock_items_count) }}</strong>
            </div>
          </div>

          <div class="inventory-modal-section">
            <div class="inventory-section-header">
              <div>
                <div class="inventory-management-eyebrow">Bienes asignados</div>
                <h6>Listado de la dependencia</h6>
              </div>
              <span class="inventory-count-pill inventory-count-pill--blue">
                {{ formatNumber(itemsPagination.total) }} registros
              </span>
            </div>

            <div class="inventory-item-filters">
              <label class="inventory-filter-field inventory-filter-field--search">
                <span>Buscar</span>
                <BFormInput
                  v-model="itemFilters.search"
                  placeholder="Código, nombre o serie"
                  @keyup.enter="loadDependencyDetail(1)"
                />
              </label>
              <label class="inventory-filter-field">
                <span>Tipo</span>
                <BFormSelect v-model="itemFilters.item_type" :options="typeOptions" />
              </label>
              <label class="inventory-filter-field">
                <span>Estado</span>
                <BFormSelect v-model="itemFilters.status" :options="statusOptions" />
              </label>
              <label class="inventory-filter-field">
                <span>Condición</span>
                <BFormSelect v-model="itemFilters.condition" :options="conditionOptions" />
              </label>
              <div class="inventory-filter-actions">
                <BButton variant="primary" @click="loadDependencyDetail(1)">Filtrar</BButton>
                <BButton variant="outline-secondary" @click="resetItemFilters">Limpiar</BButton>
              </div>
            </div>

            <LoadingState v-if="loadingDetail" message="Cargando bienes de la dependencia..." compact />
            <div v-else class="table-responsive inventory-items-table-wrap">
              <table class="table inventory-items-table">
                <thead>
                  <tr>
                    <th>Código</th>
                    <th>Bien</th>
                    <th>Categoría</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th>Condición</th>
                    <th>Stock</th>
                    <th>Responsable</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in items" :key="item.id">
                    <td><span class="inventory-code-pill">{{ item.code }}</span></td>
                    <td>
                      <div class="inventory-item-name">{{ item.name }}</div>
                      <small>{{ [item.brand, item.model].filter(Boolean).join(" · ") || "-" }}</small>
                    </td>
                    <td>{{ item.category?.name || "-" }}</td>
                    <td>
                      <span class="inventory-type-pill" :class="`inventory-type-pill--${item.item_type}`">
                        {{ typeLabel(item.item_type) }}
                      </span>
                    </td>
                    <td>
                      <BBadge :variant="statusTone(item.status)">{{ item.status || "-" }}</BBadge>
                    </td>
                    <td>
                      <BBadge :variant="statusTone(item.condition)">{{ item.condition || "-" }}</BBadge>
                    </td>
                    <td>{{ stockText(item) }}</td>
                    <td>{{ item.responsible_user?.name || "-" }}</td>
                    <td>
                      <router-link class="btn btn-sm btn-outline-secondary" :to="`/inventory/items/${item.id}`">
                        Ficha
                      </router-link>
                    </td>
                  </tr>
                  <tr v-if="items.length === 0">
                    <td colspan="9">
                      <div class="inventory-empty-state">No hay bienes para esta dependencia.</div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="inventory-pagination-row">
              <BPagination
                v-model="itemsPagination.current_page"
                :per-page="25"
                :total-rows="itemsPagination.total"
                @update:model-value="loadDependencyDetail"
              />
            </div>
          </div>

          <div class="inventory-modal-section inventory-audit-history">
            <div class="inventory-section-header">
              <div>
                <div class="inventory-management-eyebrow">Inventarios registrados</div>
                <h6>Historial reciente</h6>
              </div>
              <span class="inventory-count-pill inventory-count-pill--slate">
                {{ formatNumber(audits.length) }} movimientos
              </span>
            </div>
            <div v-if="audits.length === 0" class="inventory-empty-state">
              Esta dependencia aún no tiene inventarios registrados.
            </div>
            <div v-else class="inventory-audit-list">
              <div v-for="audit in audits" :key="audit.id" class="inventory-audit-row">
                <div>
                  <strong>{{ formatDateTime(audit.audited_at) }}</strong>
                  <span>{{ audit.audited_by?.name || "Sin responsable" }}</span>
                </div>
                <div>
                  {{ formatNumber(audit.found_items_count) }} / {{ formatNumber(audit.expected_items_count) }}
                  encontrados
                </div>
                <p>{{ audit.notes || "Sin observaciones." }}</p>
              </div>
            </div>
          </div>
        </div>
      </BModal>

      <BModal
        v-model="showAuditModal"
        title="Registrar inventario de dependencia"
        hide-footer
        size="lg"
      >
        <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
        <div v-if="selectedDependency" class="inventory-audit-modal-head">
          <strong>{{ selectedDependency.name }}</strong>
          <span>{{ selectedDependency.code }} · {{ selectedDependency.active_items_count }} bienes activos</span>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Fecha y hora de inventario</label>
            <BFormInput v-model="auditForm.audited_at" type="datetime-local" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Bienes encontrados</label>
            <BFormInput v-model="auditForm.found_items_count" type="number" min="0" />
          </div>
          <div class="col-12">
            <label class="form-label">Observaciones</label>
            <BFormTextarea v-model="auditForm.notes" rows="4" />
          </div>
        </div>

        <div class="inventory-modal-actions">
          <BButton variant="outline-secondary" @click="showAuditModal = false">Cancelar</BButton>
          <BButton variant="primary" :disabled="savingAudit" @click="saveAudit">
            {{ savingAudit ? "Guardando..." : "Guardar inventario" }}
          </BButton>
        </div>
      </BModal>
    </div>
  </Layout>
</template>

<style scoped>
.inventory-management-page {
  color: #3f4754;
}

.inventory-management-header,
.inventory-panel-header,
.inventory-detail-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.inventory-management-header h4,
.inventory-panel-header h5,
.inventory-detail-header h5 {
  margin: 0;
  color: #334155;
  font-weight: 650;
  line-height: 1.2;
}

.inventory-management-header h4 {
  font-size: 1.45rem;
}

.inventory-panel-header h5,
.inventory-detail-header h5 {
  font-size: 1.05rem;
}

.inventory-management-header p,
.inventory-detail-header p {
  margin: 0.35rem 0 0;
  color: #74788d;
  font-size: 0.9rem;
  font-weight: 500;
}

.inventory-management-eyebrow {
  color: #74788d;
  font-size: 0.74rem;
  font-weight: 650;
  line-height: 1.2;
}

.inventory-management-refresh,
.inventory-search-bar .btn,
.inventory-item-filters .btn,
.inventory-detail-actions .btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  min-height: 2.35rem;
  border-radius: 999px;
  font-size: 0.84rem;
  font-weight: 650;
}

.inventory-summary-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.85rem;
  margin-bottom: 1rem;
}

.inventory-summary-card,
.inventory-panel,
.inventory-detail-stat {
  border: 1px solid #e1ebfb;
  border-radius: 0.85rem;
  background: rgba(255, 255, 255, 0.84);
  box-shadow: 0 0.75rem 2rem rgba(31, 41, 55, 0.05);
}

.inventory-summary-card {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  min-height: 6.8rem;
  padding: 0.95rem;
  text-align: center;
}

.inventory-summary-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 2.45rem;
  width: 2.45rem;
  height: 2.45rem;
  border-radius: 0.75rem;
  font-size: 1.35rem;
}

.inventory-summary-content {
  min-width: 0;
}

.inventory-summary-label {
  color: #74788d;
  font-size: 0.74rem;
  font-weight: 650;
  line-height: 1.2;
}

.inventory-summary-card strong {
  display: block;
  margin-top: 0.25rem;
  color: #334155;
  font-size: 1.35rem;
  line-height: 1;
}

.inventory-summary-card span {
  display: block;
  margin-top: 0.28rem;
  color: #667085;
  font-size: 0.75rem;
  font-weight: 500;
  line-height: 1.25;
}

.inventory-summary-card--blue .inventory-summary-icon {
  color: #1d4ed8;
  background: #eff6ff;
}

.inventory-summary-card--green .inventory-summary-icon {
  color: #047857;
  background: #ecfdf5;
}

.inventory-summary-card--amber .inventory-summary-icon {
  color: #b45309;
  background: #fffbeb;
}

.inventory-summary-card--red .inventory-summary-icon {
  color: #b91c1c;
  background: #fef2f2;
}

.inventory-summary-card--slate .inventory-summary-icon {
  color: #475569;
  background: #f8fafc;
}

.inventory-panel {
  padding: 1rem;
  margin-bottom: 1rem;
}

.inventory-search-bar {
  display: grid;
  grid-template-columns: minmax(18rem, 1fr) auto;
  gap: 0.65rem;
  width: min(34rem, 100%);
}

.inventory-dependency-table,
.inventory-items-table {
  width: 100%;
  margin: 0;
  table-layout: fixed;
}

.inventory-dependency-table {
  min-width: 64rem;
}

.inventory-items-table {
  min-width: 82rem;
}

.inventory-dependency-table thead th,
.inventory-items-table thead th {
  color: #74788d;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  line-height: 1.2;
  text-align: center;
  text-transform: uppercase;
  vertical-align: middle;
  border-bottom: 1px solid #dbe7f6;
}

.inventory-dependency-table tbody td,
.inventory-items-table tbody td {
  color: #3f4754;
  font-size: 0.84rem;
  font-weight: 500;
  line-height: 1.28;
  text-align: center;
  vertical-align: middle;
  border-bottom: 1px solid #e6eef8;
}

.inventory-dependency-table tbody tr:last-child td,
.inventory-items-table tbody tr:last-child td {
  border-bottom: 0;
}

.inventory-dependency-name,
.inventory-item-name {
  color: #334155;
  font-weight: 650;
  overflow-wrap: anywhere;
}

.inventory-location-text {
  color: #334155;
  font-weight: 600;
  overflow-wrap: anywhere;
}

.inventory-location-text + small,
.inventory-items-table small {
  display: block;
  margin-top: 0.16rem;
  color: #74788d;
  font-size: 0.75rem;
}

.inventory-code-pill,
.inventory-count-pill,
.inventory-status-pill,
.inventory-type-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 1.65rem;
  padding: 0.26rem 0.62rem;
  border: 1px solid #bfdbfe;
  border-radius: 999px;
  color: #1d4ed8;
  background: #eff6ff;
  font-size: 0.76rem;
  font-weight: 650;
  line-height: 1;
  text-align: center;
  white-space: nowrap;
}

.inventory-code-pill {
  margin-top: 0.35rem;
}

.inventory-count-stack,
.inventory-alert-stack,
.inventory-actions {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  flex-wrap: wrap;
}

.inventory-count-stack span:not(.inventory-count-pill) {
  width: 100%;
  color: #74788d;
  font-size: 0.75rem;
}

.inventory-count-pill--blue {
  color: #1d4ed8;
  background: #eff6ff;
  border-color: #bfdbfe;
}

.inventory-count-pill--amber,
.inventory-status-pill--desactualizado,
.inventory-status-pill--sin_inventario {
  color: #b45309;
  background: #fffbeb;
  border-color: #fcd34d;
}

.inventory-count-pill--red {
  color: #b91c1c;
  background: #fef2f2;
  border-color: #fecaca;
}

.inventory-count-pill--slate {
  color: #475569;
  background: #f8fafc;
  border-color: #cbd5e1;
}

.inventory-status-pill--vigente,
.inventory-type-pill--consumable {
  color: #047857;
  background: #ecfdf5;
  border-color: #a7f3d0;
}

.inventory-type-pill--asset {
  color: #3152c9;
  background: #eef4ff;
  border-color: #c7d7fe;
}

.inventory-date-text {
  margin-top: 0.35rem;
  color: #667085;
  font-size: 0.76rem;
}

.inventory-actions .btn {
  border-radius: 999px;
  font-weight: 650;
}

.inventory-pagination-row {
  display: flex;
  justify-content: flex-end;
  margin-top: 0.85rem;
}

.inventory-empty-state {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 4.5rem;
  padding: 1rem;
  color: #74788d;
  font-size: 0.88rem;
  font-weight: 500;
  text-align: center;
}

:deep(.inventory-detail-modal-body) {
  padding: 1rem;
  background: #f8fbff;
}

.inventory-detail-modal {
  display: grid;
  gap: 1rem;
}

.inventory-detail-modal .inventory-detail-grid,
.inventory-detail-modal .inventory-item-filters,
.inventory-detail-modal .inventory-pagination-row {
  margin-bottom: 0;
}

.inventory-detail-hero,
.inventory-modal-section {
  border: 1px solid #dbe7f6;
  border-radius: 0.9rem;
  background: rgba(255, 255, 255, 0.92);
  box-shadow: 0 0.75rem 2rem rgba(31, 41, 55, 0.05);
}

.inventory-detail-hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem;
}

.inventory-detail-identity {
  display: flex;
  align-items: center;
  min-width: 0;
  gap: 0.85rem;
}

.inventory-detail-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 3rem;
  width: 3rem;
  height: 3rem;
  border-radius: 0.85rem;
  color: #3152c9;
  background: #eef4ff;
  font-size: 1.45rem;
}

.inventory-detail-identity h5 {
  margin: 0.18rem 0 0;
  color: #334155;
  font-size: 1.18rem;
  font-weight: 700;
  line-height: 1.2;
}

.inventory-detail-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-top: 0.45rem;
}

.inventory-detail-meta span {
  display: inline-flex;
  align-items: center;
  min-height: 1.55rem;
  padding: 0.22rem 0.55rem;
  border: 1px solid #e1ebfb;
  border-radius: 999px;
  color: #667085;
  background: #f8fafc;
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.15;
}

.inventory-detail-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.55rem;
  flex-wrap: wrap;
}

.inventory-detail-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.inventory-detail-stat {
  display: grid;
  justify-items: center;
  gap: 0.28rem;
  min-height: 6.8rem;
  padding: 0.9rem;
  text-align: center;
}

.inventory-detail-stat i {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.15rem;
  height: 2.15rem;
  border-radius: 0.7rem;
  font-size: 1.15rem;
}

.inventory-detail-stat span {
  display: block;
  color: #74788d;
  font-size: 0.74rem;
  font-weight: 650;
}

.inventory-detail-stat strong {
  display: block;
  margin-top: 0.35rem;
  color: #334155;
  font-size: 1rem;
  line-height: 1.2;
}

.inventory-detail-stat--blue i {
  color: #3152c9;
  background: #eef4ff;
}

.inventory-detail-stat--green i {
  color: #047857;
  background: #ecfdf5;
}

.inventory-detail-stat--red i {
  color: #b91c1c;
  background: #fef2f2;
}

.inventory-detail-stat--amber i {
  color: #b45309;
  background: #fffbeb;
}

.inventory-modal-section {
  padding: 0.95rem;
}

.inventory-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.8rem;
}

.inventory-section-header h6 {
  margin: 0.12rem 0 0;
  color: #334155;
  font-size: 0.98rem;
  font-weight: 700;
  line-height: 1.2;
}

.inventory-item-filters {
  display: grid;
  grid-template-columns: minmax(13rem, 1.3fr) repeat(3, minmax(8.5rem, 1fr)) auto;
  align-items: end;
  gap: 0.65rem;
  margin-bottom: 1rem;
  padding: 0.75rem;
  border: 1px solid #e1ebfb;
  border-radius: 0.8rem;
  background: #f8fbff;
}

.inventory-filter-field {
  display: grid;
  gap: 0.32rem;
  margin: 0;
  min-width: 0;
}

.inventory-filter-field > span {
  color: #667085;
  font-size: 0.72rem;
  font-weight: 700;
  line-height: 1;
}

.inventory-filter-field .form-control,
.inventory-filter-field .form-select {
  min-height: 2.35rem;
  border-color: #dbe7f6;
  border-radius: 999px;
  color: #334155;
  font-size: 0.82rem;
  font-weight: 550;
}

.inventory-filter-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.45rem;
  flex-wrap: wrap;
}

.inventory-audit-history {
  margin-top: 0;
}

.inventory-audit-list {
  display: grid;
  gap: 0.6rem;
}

.inventory-audit-row {
  display: grid;
  grid-template-columns: minmax(12rem, 1fr) minmax(10rem, auto) minmax(0, 2fr);
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem;
  border: 1px solid #e1ebfb;
  border-radius: 0.75rem;
  background: #fbfdff;
}

.inventory-audit-row strong {
  color: #334155;
  font-size: 0.86rem;
}

.inventory-audit-row strong,
.inventory-audit-row span {
  display: block;
}

.inventory-audit-row span,
.inventory-audit-row p {
  margin: 0;
  color: #667085;
  font-size: 0.8rem;
}

.inventory-audit-modal-head {
  display: grid;
  gap: 0.2rem;
  padding: 0.85rem;
  margin-bottom: 1rem;
  border: 1px solid #e1ebfb;
  border-radius: 0.75rem;
  background: #f8fbff;
}

.inventory-audit-modal-head span {
  color: #667085;
  font-size: 0.85rem;
}

.inventory-modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.65rem;
  margin-top: 1rem;
}

@media (max-width: 1399.98px) {
  .inventory-summary-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .inventory-item-filters {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .inventory-filter-actions {
    justify-content: flex-start;
  }
}

@media (max-width: 991.98px) {
  .inventory-management-header,
  .inventory-panel-header,
  .inventory-detail-header {
    flex-direction: column;
    align-items: stretch;
  }

  .inventory-detail-hero,
  .inventory-section-header {
    flex-direction: column;
    align-items: stretch;
  }

  .inventory-detail-actions,
  .inventory-filter-actions {
    justify-content: stretch;
  }

  .inventory-detail-actions .btn,
  .inventory-filter-actions .btn {
    flex: 1 1 auto;
  }

  .inventory-search-bar,
  .inventory-item-filters,
  .inventory-detail-grid {
    grid-template-columns: 1fr;
  }

  .inventory-summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .inventory-audit-row {
    grid-template-columns: 1fr;
    text-align: center;
  }
}

@media (max-width: 575.98px) {
  .inventory-summary-grid {
    grid-template-columns: 1fr;
  }
}
</style>
