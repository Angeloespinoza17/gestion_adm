<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import PorterStatusBadge from "../../components/porter/status-badge.vue";

const emptyKeyForm = () => ({
  code: "",
  name: "",
  maintenance_dependency_id: null,
  department_id: null,
  observations: "",
  active: true,
});

const emptyLoanForm = () => ({
  porter_key_id: null,
  staff_id: null,
  maintenance_dependency_id: null,
  requester_name: "",
  requester_rut: "",
  purpose: "",
  expected_return_at: "",
  observations: "",
});

export default {
  components: { Layout, LoadingState, PorterStatusBadge },
  data() {
    return {
      savingKey: false,
      savingLoan: false,
      loading: false,
      error: null,
      catalogs: { departments: [], dependencies: [], staff: [], key_loan_statuses: [] },
      keyForm: emptyKeyForm(),
      loanForm: emptyLoanForm(),
      keys: [],
      loans: [],
      summary: {},
      filters: { search: "", status: null },
      pagination: { current_page: 1, total: 0, per_page: 15 },
    };
  },
  computed: {
    loanStatusOptions() {
      return [{ value: null, text: "Todos" }].concat((this.catalogs.key_loan_statuses || []).map((item) => ({ value: item.value, text: item.label })));
    },
    availableKeyOptions() {
      return [{ value: null, text: "Seleccionar..." }].concat(
        (this.keys || [])
          .filter((item) => item.active && !item.active_loans_count)
          .map((item) => ({ value: item.id, text: `${item.code} · ${item.name}` }))
      );
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadData();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/porter/catalogs");
      this.catalogs = response.data;
    },
    async loadData(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/porter/keys", {
          params: { page, search: this.filters.search || null, status: this.filters.status },
        });
        this.keys = response.data.keys || [];
        this.loans = response.data.loans?.data || [];
        this.summary = response.data.summary || {};
        this.pagination = {
          current_page: response.data.loans?.current_page || 1,
          total: response.data.loans?.total || 0,
          per_page: response.data.loans?.per_page || 15,
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    async submitKey() {
      this.savingKey = true;
      this.error = null;
      try {
        await axios.post("/api/porter/keys", this.keyForm);
        this.keyForm = emptyKeyForm();
        await this.loadData();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.savingKey = false;
      }
    },
    async submitLoan() {
      if (!this.loanForm.porter_key_id) {
        this.error = "Debes seleccionar una llave disponible.";
        return;
      }
      this.savingLoan = true;
      this.error = null;
      try {
        await axios.post(`/api/porter/keys/${this.loanForm.porter_key_id}/loans`, this.loanForm);
        this.loanForm = emptyLoanForm();
        await this.loadData();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.savingLoan = false;
      }
    },
    async returnLoan(item) {
      const { value } = await Swal.fire({
        title: "Registrar devolución",
        html: `
          <select id="loan-status" class="swal2-select">
            <option value="devuelta">Devuelta</option>
            <option value="observada">Observada</option>
          </select>
          <textarea id="loan-return-observations" class="swal2-textarea" placeholder="Observaciones de devolución"></textarea>
        `,
        preConfirm: () => ({
          status: document.getElementById("loan-status").value,
          return_observations: document.getElementById("loan-return-observations").value,
        }),
      });
      if (!value) return;
      await axios.post(`/api/porter/key-loans/${item.id}/return`, value);
      await this.loadData(this.pagination.current_page || 1);
    },
    formatDateTime(value) {
      if (!value) return "-";
      const normalized = String(value).replace("T", " ");
      const [datePart, timePart] = normalized.split(" ");
      const [year, month, day] = (datePart || "").split("-");
      return year && month && day ? `${day}/${month}/${year} ${String(timePart || "").slice(0, 5)}` : value;
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || {};
      const firstKey = Object.keys(errors)[0];
      return errors[firstKey]?.[0] || error?.response?.data?.message || error?.message || "Error desconocido";
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div>
        <h4 class="mb-0">Control de llaves</h4>
        <div class="text-muted">Catálogo de llaves, préstamos y devoluciones con trazabilidad de portería.</div>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <div class="row g-3 mb-3">
      <div class="col-sm-4"><BCard><div class="text-muted small">Llaves registradas</div><div class="display-6 fw-semibold">{{ summary.total_keys || 0 }}</div></BCard></div>
      <div class="col-sm-4"><BCard><div class="text-muted small">Prestadas</div><div class="display-6 fw-semibold">{{ summary.active_loans || 0 }}</div></BCard></div>
      <div class="col-sm-4"><BCard><div class="text-muted small">Observadas</div><div class="display-6 fw-semibold">{{ summary.observed_loans || 0 }}</div></BCard></div>
    </div>

    <div class="row g-3">
      <div class="col-lg-6">
        <BCard>
          <h5 class="mb-3">Registrar llave</h5>
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Código</label><BFormInput v-model="keyForm.code" /></div>
            <div class="col-md-8"><label class="form-label">Nombre</label><BFormInput v-model="keyForm.name" /></div>
            <div class="col-md-6"><label class="form-label">Dependencia</label><BFormSelect v-model="keyForm.maintenance_dependency_id" :options="[{ value: null, text: 'Seleccionar...' }].concat((catalogs.dependencies || []).map((item) => ({ value: item.id, text: `${item.code || '-'} · ${item.name}` })))" /></div>
            <div class="col-md-6"><label class="form-label">Departamento</label><BFormSelect v-model="keyForm.department_id" :options="[{ value: null, text: 'Seleccionar...' }].concat((catalogs.departments || []).map((item) => ({ value: item.id, text: item.name })))" /></div>
            <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="keyForm.observations" rows="2" /></div>
            <div class="col-12 d-flex justify-content-end"><BButton variant="primary" :disabled="savingKey" @click="submitKey">{{ savingKey ? "Guardando..." : "Registrar llave" }}</BButton></div>
          </div>
        </BCard>
      </div>

      <div class="col-lg-6">
        <BCard>
          <h5 class="mb-3">Registrar préstamo</h5>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Llave</label><BFormSelect v-model="loanForm.porter_key_id" :options="availableKeyOptions" /></div>
            <div class="col-md-6"><label class="form-label">Funcionario responsable</label><BFormSelect v-model="loanForm.staff_id" :options="[{ value: null, text: 'Seleccionar...' }].concat((catalogs.staff || []).map((item) => ({ value: item.id, text: item.full_name })))" /></div>
            <div class="col-md-6"><label class="form-label">Solicitante</label><BFormInput v-model="loanForm.requester_name" /></div>
            <div class="col-md-6"><label class="form-label">RUT</label><BFormInput v-model="loanForm.requester_rut" /></div>
            <div class="col-md-6"><label class="form-label">Destino / dependencia</label><BFormSelect v-model="loanForm.maintenance_dependency_id" :options="[{ value: null, text: 'Seleccionar...' }].concat((catalogs.dependencies || []).map((item) => ({ value: item.id, text: `${item.code || '-'} · ${item.name}` })))" /></div>
            <div class="col-md-6"><label class="form-label">Devolución esperada</label><BFormInput v-model="loanForm.expected_return_at" type="datetime-local" /></div>
            <div class="col-12"><label class="form-label">Motivo</label><BFormInput v-model="loanForm.purpose" /></div>
            <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="loanForm.observations" rows="2" /></div>
            <div class="col-12 d-flex justify-content-end"><BButton variant="outline-primary" :disabled="savingLoan" @click="submitLoan">{{ savingLoan ? "Guardando..." : "Registrar préstamo" }}</BButton></div>
          </div>
        </BCard>
      </div>

      <div class="col-lg-5">
        <BCard>
          <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0">Catálogo de llaves</h5>
            <BFormInput v-model="filters.search" placeholder="Buscar llave" @keyup.enter="loadData(1)" />
          </div>
          <BTable :items="keys" :busy="loading" responsive :fields="[{ key: 'code', label: 'Código' }, { key: 'name', label: 'Llave' }, { key: 'active_loans_count', label: 'Prestada' }]">
            <template #table-busy><LoadingState message="Cargando llaves..." compact /></template>
            <template #cell(name)="{ item }">
              <div class="fw-semibold">{{ item.name }}</div>
              <div class="small text-muted">{{ item.dependency?.name || item.department?.name || "-" }}</div>
            </template>
            <template #cell(active_loans_count)="{ item }"><PorterStatusBadge :value="item.active_loans_count ? 'prestada' : 'disponible'" :label="item.active_loans_count ? 'Prestada' : 'Disponible'" /></template>
          </BTable>
        </BCard>
      </div>

      <div class="col-lg-7">
        <BCard>
          <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0">Historial de préstamos</h5>
            <div class="d-flex gap-2 flex-wrap">
              <BFormInput v-model="filters.search" placeholder="Llave o solicitante" @keyup.enter="loadData(1)" />
              <BFormSelect v-model="filters.status" :options="loanStatusOptions" />
              <BButton variant="outline-primary" @click="loadData(1)">Filtrar</BButton>
            </div>
          </div>
          <BTable :items="loans" :busy="loading" responsive :fields="[{ key: 'porter_key', label: 'Llave' }, { key: 'requester_name', label: 'Solicitante' }, { key: 'status', label: 'Estado' }, { key: 'checked_out_at', label: 'Salida' }, { key: 'actions', label: 'Acciones' }]">
            <template #table-busy><LoadingState message="Cargando préstamos..." compact /></template>
            <template #cell(porter_key)="{ item }">
              <div class="fw-semibold">{{ item.porter_key?.name || "-" }}</div>
              <div class="small text-muted">{{ item.porter_key?.code || "-" }}</div>
            </template>
            <template #cell(status)="{ item }"><PorterStatusBadge :value="item.status" :label="item.status" /></template>
            <template #cell(checked_out_at)="{ item }">{{ formatDateTime(item.checked_out_at) }}</template>
            <template #cell(actions)="{ item }">
              <BButton v-if="item.status === 'prestada'" size="sm" variant="outline-primary" @click="returnLoan(item)">Registrar devolución</BButton>
              <span v-else class="text-muted small">{{ formatDateTime(item.returned_at) }}</span>
            </template>
          </BTable>
          <div class="d-flex justify-content-end mt-3">
            <BPagination v-model="pagination.current_page" :total-rows="pagination.total" :per-page="pagination.per_page" @update:model-value="loadData" />
          </div>
        </BCard>
      </div>
    </div>
  </Layout>
</template>
