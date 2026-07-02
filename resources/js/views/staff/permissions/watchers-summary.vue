<script>
import axios from "axios";
import Layout from "../../../layouts/main.vue";
import LoadingState from "../../../components/ui/loading-state.vue";

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      error: null,
      rows: [],
      summary: {},
      filters: {
        search: "",
        active_only: true,
        only_with_watchers: false,
      },
      pagination: {
        current_page: 1,
        last_page: 1,
        total: 0,
      },
    };
  },
  mounted() {
    this.loadRows();
  },
  methods: {
    async loadRows(page = 1) {
      this.loading = true;
      this.error = null;

      try {
        const response = await axios.get("/api/staff/permission-watchers/summary", {
          params: {
            page,
            ...this.filters,
          },
        });

        this.rows = response.data.data?.data || [];
        this.summary = response.data.summary || {};
        this.pagination = {
          current_page: response.data.data?.current_page || 1,
          last_page: response.data.data?.last_page || 1,
          total: response.data.data?.total || 0,
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
        active_only: true,
        only_with_watchers: false,
      };
      this.loadRows(1);
    },
    watcherLabel(item) {
      if (item.target_type === "manager") return "Jefatura directa";
      if (item.target_type === "direction") return "Dirección";
      if (item.target_type === "hr") return "RRHH / Administración";
      if (item.target_type === "role") return `Rol: ${item.role?.name || "Sin rol"}`;
      if (item.target_type === "user") return `Usuario: ${item.user?.name || "Sin usuario"}`;
      return item.target_type;
    },
    openConfig(item) {
      this.$router.push({
        path: "/staff/permissions/watchers",
        query: {
          scope: "staff",
          staff_id: String(item.id),
        },
      });
    },
    formatError(error) {
      return error?.response?.data?.message || error?.message || "No se pudo cargar el resumen.";
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h4 class="mb-0">Destinatarios por funcionario</h4>
        <div class="text-muted">Verifica qué personas deben enterarse cuando un solicitante específico pide un permiso.</div>
      </div>
      <BButton variant="outline-primary" @click="$router.push('/staff/permissions/watchers')">
        Ir a configuración
      </BButton>
    </div>

    <BAlert show variant="info" class="mb-3">
      Esta vista muestra los destinatarios configurados <strong>por solicitante específico</strong>. Los destinatarios definidos
      <strong>por tipo de permiso</strong> se siguen administrando en la pantalla principal de configuración.
    </BAlert>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <BCard class="mb-3">
      <div class="row g-3 align-items-end">
        <div class="col-lg-6">
          <label class="form-label">Búsqueda</label>
          <BFormInput
            v-model="filters.search"
            placeholder="Buscar por nombre, RUT o correo institucional"
            @keyup.enter="loadRows(1)"
          />
        </div>
        <div class="col-lg-3">
          <BFormCheckbox v-model="filters.active_only">
            Solo funcionarios activos
          </BFormCheckbox>
        </div>
        <div class="col-lg-3">
          <BFormCheckbox v-model="filters.only_with_watchers">
            Solo con destinatarios configurados
          </BFormCheckbox>
        </div>
      </div>
      <div class="d-flex gap-2 mt-3">
        <BButton variant="primary" @click="loadRows(1)">Filtrar</BButton>
        <BButton variant="outline-secondary" @click="resetFilters">Limpiar</BButton>
      </div>
    </BCard>

    <div class="row g-3 mb-3">
      <div
        v-for="(label, key) in {
          total_staff: 'Funcionarios',
          with_specific_watchers: 'Con destinatarios',
          without_specific_watchers: 'Sin destinatarios',
          active_staff: 'Activos'
        }"
        :key="key"
        class="col-md-3"
      >
        <BCard>
          <div class="text-muted small">{{ label }}</div>
          <div class="h2 mb-0">{{ summary[key] ?? 0 }}</div>
        </BCard>
      </div>
    </div>

    <BCard title="Resumen">
      <LoadingState v-if="loading" message="Cargando funcionarios..." compact />
      <div v-else class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>Funcionario</th>
              <th>Cargo / departamentos</th>
              <th>Quién debe enterarse</th>
              <th>Resumen</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in rows" :key="item.id">
              <td class="align-top">
                <div class="fw-semibold">{{ item.full_name }}</div>
                <div class="text-muted small">{{ item.institutional_email || "Sin correo institucional" }}</div>
                <div class="mt-1">
                  <span class="badge" :class="item.active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'">
                    {{ item.active ? "Activo" : "Inactivo" }}
                  </span>
                </div>
              </td>
              <td class="align-top">
                <div>{{ item.cargo?.name || "Sin cargo" }}</div>
                <div class="text-muted small">
                  {{ (item.departments || []).length ? item.departments.map((department) => department.name).join(", ") : "Sin departamentos" }}
                </div>
              </td>
              <td class="align-top">
                <template v-if="(item.permission_watchers || []).length">
                  <div
                    v-for="watcher in item.permission_watchers"
                    :key="watcher.id"
                    class="border rounded px-2 py-1 mb-2"
                  >
                    <div class="fw-semibold small">{{ watcherLabel(watcher) }}</div>
                    <div class="d-flex flex-wrap gap-1 mt-1">
                      <span class="badge" :class="watcher.notify ? 'bg-primary-subtle text-primary' : 'bg-light text-muted'">
                        {{ watcher.notify ? "Envía aviso" : "Sin aviso" }}
                      </span>
                      <span class="badge" :class="watcher.can_view ? 'bg-info-subtle text-info' : 'bg-light text-muted'">
                        {{ watcher.can_view ? "Puede ver" : "No puede ver" }}
                      </span>
                      <span class="badge" :class="watcher.active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'">
                        {{ watcher.active ? "Activo" : "Inactivo" }}
                      </span>
                    </div>
                  </div>
                </template>
                <div v-else class="text-muted small">
                  Sin destinatarios específicos. Solo aplican los definidos por tipo de permiso.
                </div>
              </td>
              <td class="align-top">
                <div class="fw-semibold">{{ item.permission_watchers_count || 0 }} configurados</div>
                <div class="text-muted small">{{ item.active_permission_watchers_count || 0 }} activos</div>
              </td>
              <td class="align-top text-end">
                <div class="btn-group btn-group-sm">
                  <BButton variant="outline-primary" @click="openConfig(item)">Configurar</BButton>
                  <BButton variant="outline-secondary" @click="$router.push(`/staff/${item.id}`)">Ver ficha</BButton>
                </div>
              </td>
            </tr>
            <tr v-if="!rows.length">
              <td colspan="5" class="text-center text-muted py-4">No hay funcionarios para mostrar con los filtros actuales.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="text-muted small">Total: {{ pagination.total }}</div>
        <BPagination
          v-model="pagination.current_page"
          :total-rows="pagination.total"
          :per-page="15"
          pills
          @update:model-value="loadRows"
        />
      </div>
    </BCard>
  </Layout>
</template>
