<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";
import Swal from "sweetalert2";

export default {
  components: { Layout, LoadingState, Multiselect },
  data() {
    return {
      loading: false,
      error: null,
      contracts: [],
      pagination: { current_page: 1, last_page: 1, total: 0 },
      catalogs: {
        staff: [],
        templates: [],
        departments: [],
        statuses: [],
        contract_types: [],
      },
      filters: {
        search: "",
        staff_id: null,
        rut: "",
        contract_type: null,
        status: null,
        start_date: "",
        end_date: "",
        department_id: null,
        contract_template_id: null,
      },
    };
  },
  computed: {
    permissions() {
      try {
        return JSON.parse(localStorage.getItem("permissions") || "[]");
      } catch (error) {
        return [];
      }
    },
    canManage() {
      return this.permissions.includes("gestionar_contratos");
    },
    canDelete() {
      return this.permissions.includes("eliminar_contratos");
    },
    canExport() {
      return this.permissions.includes("exportar_contratos") || this.canManage;
    },
    staffOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.staff || []).map((item) => ({
          value: item.id,
          label: `${item.full_name} (${item.rut || "sin RUT"})`,
        }))
      );
    },
    templateOptions() {
      return [{ value: null, label: "Todas" }].concat(
        (this.catalogs.templates || []).map((item) => ({
          value: item.id,
          label: item.name,
        }))
      );
    },
    departmentOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.departments || []).map((item) => ({
          value: item.id,
          label: item.name,
        }))
      );
    },
    statusOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.statuses || []).map((item) => ({
          value: item.value,
          label: item.label,
        }))
      );
    },
    contractTypeOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.contract_types || []).map((item) => ({
          value: item.value,
          label: item.label,
        }))
      );
    },
    activeFilterCount() {
      return Object.values(this.filters).filter((value) => value !== null && value !== "").length;
    },
  },
  mounted() {
    this.loadCatalogs();
    this.loadContracts();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/contracts/catalogs");
      this.catalogs = response.data;
    },
    async loadContracts(page = 1) {
      this.loading = true;
      this.error = null;

      try {
        const response = await axios.get("/api/contracts", {
          params: {
            page,
            ...this.filters,
          },
        });

        this.contracts = response.data.data;
        this.pagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          total: response.data.total,
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.filters = {
        search: "",
        staff_id: null,
        rut: "",
        contract_type: null,
        status: null,
        start_date: "",
        end_date: "",
        department_id: null,
        contract_template_id: null,
      };
      this.loadContracts(1);
    },
    statusLabel(value) {
      return (this.catalogs.statuses || []).find((item) => item.value === value)?.label || value || "-";
    },
    contractTypeLabel(value) {
      return (this.catalogs.contract_types || []).find((item) => item.value === value)?.label || value || "-";
    },
    statusVariant(value) {
      if (value === "firmado") return "success";
      if (value === "anulado") return "danger";
      if (value === "vencido") return "secondary";
      if (value === "enviado_firma") return "warning";
      if (value === "generado") return "info";
      return "primary";
    },
    formatDate(value) {
      if (!value) return "-";
      const normalized = String(value).split(" ")[0];
      const [year, month, day] = normalized.split("-");
      return year && month && day ? `${day}/${month}/${year}` : value;
    },
    formatCurrency(value) {
      if (value === null || value === undefined || value === "") return "-";
      return new Intl.NumberFormat("es-CL", {
        style: "currency",
        currency: "CLP",
        maximumFractionDigits: 0,
      }).format(Number(value));
    },
    async updateStatus(contract, status) {
      const result = await Swal.fire({
        title: "Actualizar estado",
        text: `El contrato pasará a estado ${this.statusLabel(status)}.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Confirmar",
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed) {
        return;
      }

      try {
        await axios.put(`/api/contracts/${contract.id}/status`, { status });
        await this.loadContracts(this.pagination.current_page);
        await Swal.fire({
          title: "Estado actualizado",
          text: "El contrato fue actualizado correctamente.",
          icon: "success",
          timer: 1600,
          showConfirmButton: false,
        });
      } catch (error) {
        this.error = this.formatError(error);
        this.showError(this.error);
      }
    },
    async remove(contract) {
      const result = await Swal.fire({
        title: "Eliminar contrato",
        text: "El contrato será eliminado definitivamente.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed) {
        return;
      }

      try {
        await axios.delete(`/api/contracts/${contract.id}`);
        await this.loadContracts(this.pagination.current_page);
        await Swal.fire({
          title: "Contrato eliminado",
          text: "El contrato fue eliminado correctamente.",
          icon: "success",
          timer: 1600,
          showConfirmButton: false,
        });
      } catch (error) {
        this.error = this.formatError(error);
        this.showError(this.error);
      }
    },
    showError(text) {
      return Swal.fire({
        title: "Error",
        text,
        icon: "error",
      });
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (
        (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
        error?.response?.data?.message ||
        error?.message ||
        "Error desconocido"
      );
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
      <div class="mb-3 mb-sm-0">
        <h4 class="mb-0">Gestión de Contratos</h4>
        <div class="text-muted">Contratos, plantillas, cláusulas y firmas institucionales.</div>
      </div>
      <router-link v-if="canManage" to="/contracts/new" class="btn btn-primary">
        <i class="mdi mdi-file-document-edit-outline me-1"></i>
        Nuevo contrato
      </router-link>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <BCard class="mb-4">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h5 class="mb-1">Buscador y filtros</h5>
          <p class="text-muted mb-0">Filtra por funcionario, plantilla, fechas, estado o departamento.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <router-link to="/contracts/templates" class="btn btn-outline-secondary btn-sm">Plantillas</router-link>
          <router-link to="/contracts/clauses" class="btn btn-outline-secondary btn-sm">Cláusulas</router-link>
          <router-link to="/contracts/signatures" class="btn btn-outline-secondary btn-sm">Firmas</router-link>
        </div>
      </div>

      <div class="row g-3 align-items-end">
        <div class="col-xl-3 col-md-6">
          <label class="form-label">Búsqueda general</label>
          <BFormInput v-model="filters.search" placeholder="Funcionario, RUT, plantilla, cargo" @keyup.enter="loadContracts(1)" />
        </div>
        <div class="col-xl-3 col-md-6">
          <label class="form-label">Funcionario</label>
          <Multiselect v-model="filters.staff_id" :options="staffOptions" :searchable="true" />
        </div>
        <div class="col-xl-2 col-md-6">
          <label class="form-label">RUT</label>
          <BFormInput v-model="filters.rut" @keyup.enter="loadContracts(1)" />
        </div>
        <div class="col-xl-2 col-md-6">
          <label class="form-label">Tipo de contrato</label>
          <Multiselect v-model="filters.contract_type" :options="contractTypeOptions" :searchable="true" />
        </div>
        <div class="col-xl-2 col-md-6">
          <label class="form-label">Estado</label>
          <Multiselect v-model="filters.status" :options="statusOptions" :searchable="true" />
        </div>
        <div class="col-xl-3 col-md-6">
          <label class="form-label">Plantilla</label>
          <Multiselect v-model="filters.contract_template_id" :options="templateOptions" :searchable="true" />
        </div>
        <div class="col-xl-3 col-md-6">
          <label class="form-label">Departamento</label>
          <Multiselect v-model="filters.department_id" :options="departmentOptions" :searchable="true" />
        </div>
        <div class="col-xl-2 col-md-6">
          <label class="form-label">Fecha inicio</label>
          <BFormInput v-model="filters.start_date" type="date" />
        </div>
        <div class="col-xl-2 col-md-6">
          <label class="form-label">Fecha término</label>
          <BFormInput v-model="filters.end_date" type="date" />
        </div>
        <div class="col-xl-2 col-md-12">
          <div class="d-flex gap-2 flex-wrap">
            <BButton variant="primary" @click="loadContracts(1)">Filtrar</BButton>
            <BButton variant="light" @click="resetFilters">Limpiar</BButton>
          </div>
        </div>
      </div>
    </BCard>

    <BCard no-body>
      <div class="card-header border-bottom">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div>
            <h5 class="mb-1">Listado de contratos</h5>
            <p class="text-muted mb-0">Total registros: {{ pagination.total }} · Filtros activos: {{ activeFilterCount }}</p>
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <BTableSimple class="table align-middle table-nowrap mb-0">
          <BThead class="table-light">
            <BTr>
              <BTh>Funcionario</BTh>
              <BTh>Plantilla</BTh>
              <BTh>Vigencia</BTh>
              <BTh>Cargo</BTh>
              <BTh>Estado</BTh>
              <BTh>Remuneración</BTh>
              <BTh class="text-end">Acciones</BTh>
            </BTr>
          </BThead>
          <BTbody>
            <BTr v-if="loading">
              <BTd colspan="7" class="text-center py-5">
                <LoadingState message="Cargando contratos..." compact />
              </BTd>
            </BTr>
            <BTr v-else-if="contracts.length === 0">
              <BTd colspan="7" class="text-center py-5 text-muted">
                No hay contratos para los filtros seleccionados.
              </BTd>
            </BTr>
            <BTr v-for="contract in contracts" :key="contract.id">
              <BTd>
                <div class="fw-semibold">{{ contract.staff?.full_name || "-" }}</div>
                <div class="text-muted">{{ contract.staff?.rut || "-" }}</div>
              </BTd>
              <BTd>
                <div class="fw-medium">{{ contract.template?.name || "-" }}</div>
                <small class="text-muted">{{ contractTypeLabel(contract.contract_type) }}</small>
              </BTd>
              <BTd>
                <div>{{ formatDate(contract.start_date) }}</div>
                <small class="text-muted">Hasta {{ formatDate(contract.end_date) }}</small>
              </BTd>
              <BTd>
                <div>{{ contract.position_name || contract.staff?.cargo?.name || "-" }}</div>
                <small class="text-muted">{{ contract.contract_hours || "-" }} hrs · {{ contract.workday || "-" }}</small>
              </BTd>
              <BTd>
                <span :class="`badge rounded-pill badge-soft-${statusVariant(contract.status)}`">
                  {{ statusLabel(contract.status) }}
                </span>
              </BTd>
              <BTd>{{ formatCurrency(contract.base_salary) }}</BTd>
              <BTd class="text-end">
                <div class="d-flex justify-content-end flex-wrap gap-2">
                  <router-link :to="`/contracts/${contract.id}`" class="btn btn-sm btn-outline-primary">
                    Ver ficha
                  </router-link>
                  <a
                    v-if="canExport && contract.exported_word_url"
                    :href="contract.exported_word_url"
                    class="btn btn-sm btn-outline-secondary"
                    download
                  >
                    Word
                  </a>
                  <BButton
                    v-if="canManage && !['firmado', 'anulado'].includes(contract.status)"
                    size="sm"
                    variant="warning"
                    @click="updateStatus(contract, 'anulado')"
                  >
                    Anular
                  </BButton>
                  <BButton
                    v-if="canDelete && contract.status !== 'firmado'"
                    size="sm"
                    variant="danger"
                    @click="remove(contract)"
                  >
                    Eliminar
                  </BButton>
                </div>
              </BTd>
            </BTr>
          </BTbody>
        </BTableSimple>
      </div>

      <div class="card-footer border-top">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
          <small class="text-muted">Mostrando {{ contracts.length }} de {{ pagination.total }} registros</small>
          <BPagination
            v-model="pagination.current_page"
            :per-page="15"
            :total-rows="pagination.total"
            align="end"
            pills
            @update:model-value="loadContracts"
          />
        </div>
      </div>
    </BCard>
  </Layout>
</template>
