<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../../layouts/main.vue";
import EmptyState from "../../../components/staff/permissions/empty-state.vue";
import LoadingState from "../../../components/ui/loading-state.vue";
import MetricCard from "../../../components/staff/permissions/metric-card.vue";
import PageHeader from "../../../components/staff/permissions/page-header.vue";
import StatusBadge from "../../../components/staff/permissions/status-badge.vue";

const emptyWatcher = () => ({
  target_type: "manager",
  role_id: null,
  user_id: null,
  notify: true,
  can_view: true,
  active: true,
});

export default {
  components: { Layout, EmptyState, LoadingState, MetricCard, PageHeader, StatusBadge },
  data() {
    return {
      loadingCatalogs: false,
      loadingItems: false,
      saving: false,
      showWatcherModal: false,
      error: null,
      scope: "type",
      types: [],
      staff: [],
      roles: [],
      users: [],
      targetOptions: [],
      selectedTypeId: null,
      selectedStaffId: null,
      configurations: [],
      items: [],
      form: emptyWatcher(),
    };
  },
  computed: {
    selectedType() {
      return this.types.find((item) => Number(item.id) === Number(this.selectedTypeId)) || null;
    },
    selectedStaff() {
      return this.staff.find((item) => Number(item.id) === Number(this.selectedStaffId)) || null;
    },
    currentLabel() {
      return this.scope === "type"
        ? this.selectedType?.name || "Sin tipo seleccionado"
        : this.selectedStaff?.full_name || "Sin funcionario seleccionado";
    },
    currentEmptyLabel() {
      return this.scope === "type"
        ? "Selecciona un tipo de permiso."
        : "Selecciona un funcionario.";
    },
    currentScopeHint() {
      return this.scope === "type"
        ? "Regla general para todas las solicitudes de este tipo."
        : "Regla específica para el funcionario seleccionado.";
    },
    canAddWatcher() {
      return this.scope === "type" ? !!this.selectedTypeId : !!this.selectedStaffId;
    },
    scopeOptions() {
      return [
        {
          value: "type",
          label: "Por tipo",
          hint: "Aplica a un permiso",
          icon: "bx-category",
        },
        {
          value: "staff",
          label: "Por solicitante",
          hint: "Regla individual",
          icon: "bx-user",
        },
      ];
    },
    watcherRows() {
      return this.configurations
        .flatMap((configuration) => (configuration.watchers || []).map((watcher, index) => ({
          ...watcher,
          key: `${configuration.key}:${watcher.id || index}:${watcher.target_type}:${watcher.role_id || 0}:${watcher.user_id || 0}`,
          configuration,
        })))
        .sort((a, b) => {
          const watcherOrder = this.watcherLabel(a).localeCompare(this.watcherLabel(b));
          return watcherOrder || a.configuration.label.localeCompare(b.configuration.label);
        });
    },
    summaryCards() {
      const watchers = this.watcherRows;

      return [
        {
          label: "Destinatarios",
          value: watchers.length,
          hint: "Quién debe enterarse",
          icon: "bx-user-voice",
          variant: "primary",
        },
        {
          label: "Con aviso",
          value: watchers.filter((item) => item.notify).length,
          hint: "Reciben notificación",
          icon: "bx-bell",
          variant: "info",
        },
        {
          label: "Con lectura",
          value: watchers.filter((item) => item.can_view).length,
          hint: "Pueden ver solicitudes",
          icon: "bx-show",
          variant: "success",
        },
        {
          label: "Inactivos",
          value: watchers.filter((item) => !item.active).length,
          hint: "No se aplican",
          icon: "bx-pause-circle",
          variant: "neutral",
        },
      ];
    },
  },
  watch: {
    "$route.query": {
      deep: true,
      async handler() {
        if (!this.types.length && !this.staff.length) {
          return;
        }

        this.applyRouteSelection();
        await this.loadItems();
        this.showWatcherModal = true;
      },
    },
  },
  mounted() {
    this.loadCatalogs();
  },
  methods: {
    applyRouteSelection() {
      const scope = this.$route.query?.scope;
      const staffId = this.$route.query?.staff_id;
      const typeId = this.$route.query?.type_id;

      if (scope === "staff") {
        this.scope = "staff";
      } else if (scope === "type") {
        this.scope = "type";
      }

      if (staffId && this.staff.some((item) => Number(item.id) === Number(staffId))) {
        this.selectedStaffId = Number(staffId);
      }

      if (typeId && this.types.some((item) => Number(item.id) === Number(typeId))) {
        this.selectedTypeId = Number(typeId);
      }
    },
    async loadCatalogs(options = {}) {
      const preserveSelection = !!options.preserveSelection;

      this.loadingCatalogs = true;
      this.error = null;
      try {
        const response = await axios.get("/api/staff/permission-type-watchers/catalogs");
        this.types = response.data.types || [];
        this.staff = response.data.staff || [];
        this.roles = response.data.roles || [];
        this.users = response.data.users || [];
        this.targetOptions = response.data.target_options || [];
        this.configurations = this.normalizeConfigurations(response.data.configurations || {});

        if (!this.selectedTypeId && this.types.length) {
          this.selectedTypeId = this.types[0].id;
        }

        if (!this.selectedStaffId && this.staff.length) {
          this.selectedStaffId = this.staff[0].id;
        }

        const routeHasSelection = this.hasRouteSelection();

        if (routeHasSelection) {
          this.applyRouteSelection();
        } else if (!preserveSelection) {
          this.applyInitialConfiguredSelection();
        }

        await this.loadItems();

        if (routeHasSelection) {
          this.showWatcherModal = true;
        }
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loadingCatalogs = false;
      }
    },
    normalizeConfigurations(configurations) {
      const typeRows = (configurations.types || []).map((item) => ({
        key: `type:${item.id}`,
        scope: "type",
        id: item.id,
        label: item.name,
        active: !!item.active,
        watchers_count: Number(item.watchers_count || 0),
        active_watchers_count: Number(item.active_watchers_count || 0),
        watchers: (item.watchers || []).map(this.normalizeWatcher),
      }));

      const staffRows = (configurations.staff || []).map((item) => ({
        key: `staff:${item.id}`,
        scope: "staff",
        id: item.id,
        label: item.full_name,
        active: !!item.active,
        secondary: item.institutional_email || "Sin correo institucional",
        watchers_count: Number(item.permission_watchers_count || 0),
        active_watchers_count: Number(item.active_permission_watchers_count || 0),
        watchers: (item.permission_watchers || []).map(this.normalizeWatcher),
      }));

      return [...typeRows, ...staffRows].sort((a, b) => a.label.localeCompare(b.label));
    },
    normalizeWatcher(item) {
      return {
        id: item.id,
        target_type: item.target_type,
        role_id: item.role_id,
        user_id: item.user_id,
        notify: !!item.notify,
        can_view: !!item.can_view,
        active: !!item.active,
        role: item.role || null,
        user: item.user || null,
      };
    },
    hasRouteSelection() {
      return Boolean(this.$route.query?.scope || this.$route.query?.staff_id || this.$route.query?.type_id);
    },
    applyInitialConfiguredSelection() {
      const staffWithWatchers = this.staff.find((item) => Number(item.permission_watchers_count || 0) > 0);
      const typeWithWatchers = this.types.find((item) => Number(item.watchers_count || 0) > 0);

      if (staffWithWatchers) {
        this.scope = "staff";
        this.selectedStaffId = staffWithWatchers.id;
        return;
      }

      if (typeWithWatchers) {
        this.scope = "type";
        this.selectedTypeId = typeWithWatchers.id;
      }
    },
    async loadItems() {
      if (this.scope === "type" && !this.selectedTypeId) {
        this.items = [];
        return;
      }

      if (this.scope === "staff" && !this.selectedStaffId) {
        this.items = [];
        return;
      }

      this.loadingItems = true;
      this.error = null;
      try {
        const response = this.scope === "type"
          ? await axios.get(`/api/staff/permission-types/${this.selectedTypeId}/watchers`)
          : await axios.get(`/api/staff/${this.selectedStaffId}/permission-watchers`);
        this.items = (response.data.data || []).map(this.normalizeWatcher);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loadingItems = false;
      }
    },
    async addWatcher() {
      this.error = null;

      if (this.form.target_type === "role" && !this.form.role_id) {
        await Swal.fire({
          title: "Rol requerido",
          text: "Selecciona el rol que debe enterarse.",
          icon: "warning",
          confirmButtonText: "Entendido",
        });
        return;
      }

      if (this.form.target_type === "user" && !this.form.user_id) {
        await Swal.fire({
          title: "Usuario requerido",
          text: "Selecciona el usuario que debe enterarse.",
          icon: "warning",
          confirmButtonText: "Entendido",
        });
        return;
      }

      const duplicate = this.items.some((item) =>
        item.target_type === this.form.target_type &&
        Number(item.role_id || 0) === Number(this.form.role_id || 0) &&
        Number(item.user_id || 0) === Number(this.form.user_id || 0)
      );

      if (duplicate) {
        await Swal.fire({
          title: "Destinatario duplicado",
          text: "Ese destinatario ya está agregado a esta regla.",
          icon: "warning",
          confirmButtonText: "Entendido",
        });
        return;
      }

      this.items.push({
        ...emptyWatcher(),
        ...this.form,
        role: this.roles.find((item) => Number(item.id) === Number(this.form.role_id)) || null,
        user: this.users.find((item) => Number(item.id) === Number(this.form.user_id)) || null,
      });

      this.form = emptyWatcher();

      await this.persistWatchers({
        successTitle: "Destinatario guardado",
        successText: "El destinatario quedó guardado en quién debe enterarse.",
      });
    },
    async openWatcherModal() {
      this.error = null;
      this.resetForm();
      this.selectFirstAvailableConfigurationBase();
      await this.loadItems();
      this.showWatcherModal = true;
    },
    selectFirstAvailableConfigurationBase() {
      const unconfiguredType = this.types.find((item) => Number(item.watchers_count || 0) === 0);
      const unconfiguredStaff = this.staff.find((item) => Number(item.permission_watchers_count || 0) === 0);

      if (unconfiguredType) {
        this.scope = "type";
        this.selectedTypeId = unconfiguredType.id;
        return;
      }

      if (unconfiguredStaff) {
        this.scope = "staff";
        this.selectedStaffId = unconfiguredStaff.id;
      }
    },
    async editConfiguration(configuration) {
      this.scope = configuration.scope;

      if (configuration.scope === "type") {
        this.selectedTypeId = configuration.id;
      } else {
        this.selectedStaffId = configuration.id;
      }

      this.resetForm();
      await this.loadItems();
      this.showWatcherModal = true;
    },
    editWatcherRow(row) {
      return this.editConfiguration(row.configuration);
    },
    async setScope(scope) {
      if (this.scope === scope) return;
      this.scope = scope;
      this.resetForm();
      await this.loadItems();
    },
    selectTargetType(targetType) {
      this.form = {
        ...emptyWatcher(),
        notify: this.form.notify,
        can_view: this.form.can_view,
        active: this.form.active,
        target_type: targetType,
      };
    },
    resetForm() {
      this.form = emptyWatcher();
    },
    async removeWatcher(index) {
      const item = this.items[index];
      const result = await Swal.fire({
        title: "Quitar destinatario",
        text: this.watcherLabel(item),
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Quitar",
        cancelButtonText: "Cancelar",
        reverseButtons: true,
      });

      if (!result.isConfirmed) return;

      this.items.splice(index, 1);
      await this.persistWatchers({
        successTitle: "Destinatario quitado",
        successText: "La lista de quién debe enterarse fue actualizada.",
      });
    },
    async persistWatchers(options = {}) {
      this.saving = true;
      this.error = null;

      try {
        const payload = {
          watchers: this.items.map((item) => ({
            target_type: item.target_type,
            role_id: item.target_type === "role" ? item.role_id : null,
            user_id: item.target_type === "user" ? item.user_id : null,
            notify: !!item.notify,
            can_view: !!item.can_view,
            active: !!item.active,
          })),
        };

        if (this.scope === "type") {
          await axios.put(`/api/staff/permission-types/${this.selectedTypeId}/watchers`, payload);
        } else {
          await axios.put(`/api/staff/${this.selectedStaffId}/permission-watchers`, payload);
        }

        await this.loadItems();
        await this.loadCatalogs({ preserveSelection: true });

        if (options.closeModal) {
          this.showWatcherModal = false;
        }

        await Swal.fire({
          title: options.successTitle || "Destinatarios guardados",
          text: options.successText || "Los destinatarios fueron actualizados correctamente.",
          icon: "success",
          timer: 1800,
          showConfirmButton: false,
        });
        return true;
      } catch (error) {
        const message = this.formatError(error);
        this.error = message;
        await Swal.fire({
          title: "No se pudo guardar",
          text: message,
          icon: "error",
          confirmButtonText: "Entendido",
        });
        return false;
      } finally {
        this.saving = false;
      }
    },
    async confirmReset() {
      const result = await Swal.fire({
        title: "Descartar cambios",
        text: "Se volverán a cargar los destinatarios guardados.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Recargar",
        cancelButtonText: "Cancelar",
      });

      if (result.isConfirmed) {
        await this.loadItems();
      }
    },
    watcherLabel(item) {
      if (item.target_type === "manager") return "Jefatura directa";
      if (item.target_type === "direction") return "Dirección";
      if (item.target_type === "hr") return "RRHH / Administración";
      if (item.target_type === "role") return `Rol: ${item.role?.name || "Sin rol"}`;
      if (item.target_type === "user") return `Usuario: ${item.user?.name || "Sin usuario"}`;
      return item.target_type;
    },
    watcherIcon(item) {
      if (item.target_type === "manager") return "bx-user-check";
      if (item.target_type === "direction") return "bx-buildings";
      if (item.target_type === "hr") return "bx-id-card";
      if (item.target_type === "role") return "bx-group";
      if (item.target_type === "user") return "bx-user";
      return "bx-user-voice";
    },
    configurationScopeLabel(item) {
      return item.scope === "type" ? "Por tipo de permiso" : "Por solicitante";
    },
    configurationIcon(item) {
      return item.scope === "type" ? "bx-category" : "bx-user";
    },
    configurationHint(item) {
      if (item.scope === "type") {
        return "Regla general para todas las solicitudes de este tipo.";
      }

      return item.secondary || "Regla específica para un funcionario.";
    },
    configurationNotifyCount(item) {
      return (item.watchers || []).filter((watcher) => watcher.notify).length;
    },
    configurationReadCount(item) {
      return (item.watchers || []).filter((watcher) => watcher.can_view).length;
    },
    targetOptionIcon(targetType) {
      if (targetType === "manager") return "bx-user-check";
      if (targetType === "direction") return "bx-buildings";
      if (targetType === "hr") return "bx-id-card";
      if (targetType === "role") return "bx-group";
      if (targetType === "user") return "bx-user";
      return "bx-user-voice";
    },
    targetOptionDescription(targetType) {
      if (targetType === "manager") return "Jefatura directa del solicitante.";
      if (targetType === "direction") return "Equipo de Dirección.";
      if (targetType === "hr") return "RRHH o administración.";
      if (targetType === "role") return "Todos los usuarios de un rol.";
      if (targetType === "user") return "Un usuario específico.";
      return "Destinatario configurable.";
    },
    formatUserOption(user) {
      return {
        value: user.id,
        text: `${user.name}${user.email ? ` · ${user.email}` : ""}`,
      };
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (errors ? errors[Object.keys(errors)[0]]?.[0] : null) || error?.response?.data?.message || error?.message || "Error desconocido";
    },
  },
};
</script>

<template>
  <Layout>
    <PageHeader
      title="Quién debe enterarse"
      subtitle="Define personas, roles o áreas que reciben aviso o lectura de solicitudes."
      icon="bx-user-voice"
    >
      <template #actions>
        <BButton variant="outline-primary" @click="$router.push('/staff/permissions/watchers-summary')">
          <i class="bx bx-list-check me-1"></i>Resumen por funcionario
        </BButton>
      </template>
    </PageHeader>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <div class="row g-3 mb-3">
      <div v-for="card in summaryCards" :key="card.label" class="col-md-6 col-xl-3">
        <MetricCard
          :label="card.label"
          :value="card.value"
          :hint="card.hint"
          :icon="card.icon"
          :variant="card.variant"
        />
      </div>
    </div>

    <BCard class="permission-card permission-watchers-main">
      <template #header>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div>
            <div class="permission-section-title mb-1">
              <i class="bx bx-user-voice"></i>
              <span>Quién debe enterarse</span>
            </div>
            <div class="text-muted small">Destinatarios configurados y regla donde se aplican.</div>
          </div>
          <div class="permission-watchers-actions">
            <BButton variant="primary" :disabled="loadingCatalogs" @click="openWatcherModal">
              <i class="bx bx-user-plus me-1"></i>Agregar destinatario
            </BButton>
            <BButton
              variant="outline-secondary"
              :disabled="loadingCatalogs"
              @click="loadCatalogs({ preserveSelection: true })"
            >
              <i class="bx bx-refresh me-1"></i>Recargar
            </BButton>
          </div>
        </div>
      </template>

      <LoadingState v-if="loadingCatalogs" message="Cargando destinatarios..." compact />
      <EmptyState
        v-else-if="!watcherRows.length"
        icon="bx-user-x"
        title="Sin destinatarios"
        text="Aún no hay personas, roles o áreas configuradas para enterarse."
      />
      <div v-else class="table-responsive">
        <table class="table table-sm align-middle permission-table">
          <thead>
            <tr>
              <th>Quién debe enterarse</th>
              <th>Se aplica a</th>
              <th>Avisos y lectura</th>
              <th>Estado</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in watcherRows" :key="row.key">
              <td>
                <div class="permission-watcher-recipient">
                  <span class="permission-watcher-recipient__icon">
                    <i :class="`bx ${watcherIcon(row)}`"></i>
                  </span>
                  <div>
                    <div class="fw-semibold">{{ watcherLabel(row) }}</div>
                    <div class="text-muted small">{{ targetOptionDescription(row.target_type) }}</div>
                  </div>
                </div>
              </td>
              <td>
                <div class="permission-watcher-recipient">
                  <span class="permission-watcher-recipient__icon">
                    <i :class="`bx ${configurationIcon(row.configuration)}`"></i>
                  </span>
                  <div>
                    <div class="fw-semibold">{{ row.configuration.label }}</div>
                    <div class="text-muted small">
                      {{ configurationScopeLabel(row.configuration) }} · {{ configurationHint(row.configuration) }}
                    </div>
                  </div>
                </div>
              </td>
              <td>
                <div class="permission-chip-list">
                  <span class="permission-chip" :class="{ 'permission-chip--muted': !row.notify }">
                    <i :class="row.notify ? 'bx bx-bell' : 'bx bx-bell-off'"></i>
                    {{ row.notify ? "Con aviso" : "Sin aviso" }}
                  </span>
                  <span class="permission-chip" :class="{ 'permission-chip--muted': !row.can_view }">
                    <i :class="row.can_view ? 'bx bx-show' : 'bx bx-hide'"></i>
                    {{ row.can_view ? "Con lectura" : "Sin lectura" }}
                  </span>
                </div>
              </td>
              <td>
                <StatusBadge :status="row.active ? 'activo' : 'inactivo'" />
                <div v-if="!row.configuration.active" class="text-muted small mt-1">Base inactiva</div>
              </td>
              <td class="text-end">
                <div class="permission-row-actions">
                  <BButton
                    class="permission-action-button permission-action-button--edit"
                    variant="outline-light"
                    title="Editar destinatarios"
                    aria-label="Editar destinatarios"
                    @click="editWatcherRow(row)"
                  >
                    <i class="bx bx-edit"></i>
                  </BButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BCard>

    <BModal
      v-model="showWatcherModal"
      title="Configurar quién debe enterarse"
      size="lg"
      hide-footer
      centered
      scrollable
      modal-class="permission-detail-modal permission-watchers-modal"
    >
      <LoadingState v-if="loadingCatalogs" message="Cargando catálogos..." compact />
      <div v-else class="permission-watchers-modal__content">
        <section class="permission-type-panel permission-watchers-panel">
          <div class="permission-section-title mb-3">
            <i class="bx bx-slider-alt"></i>
            <span>Dónde se aplica</span>
          </div>

          <div class="permission-scope-switch">
            <button
              v-for="option in scopeOptions"
              :key="option.value"
              type="button"
              class="permission-scope-option"
              :class="{ 'permission-scope-option--active': scope === option.value }"
              @click="setScope(option.value)"
            >
              <i :class="`bx ${option.icon}`"></i>
              <span>{{ option.label }}</span>
              <small>{{ option.hint }}</small>
            </button>
          </div>

          <div v-if="scope === 'type'" class="mt-3">
            <label class="form-label">Tipo de permiso</label>
            <BFormSelect v-model="selectedTypeId" @change="loadItems">
              <option :value="null">Seleccionar...</option>
              <option v-for="item in types" :key="item.id" :value="item.id">
                {{ item.name }}{{ item.active ? "" : " (inactivo)" }} · {{ item.watchers_count || 0 }} destinatarios
              </option>
            </BFormSelect>
          </div>

          <div v-else class="mt-3">
            <label class="form-label">Solicitante</label>
            <BFormSelect v-model="selectedStaffId" @change="loadItems">
              <option :value="null">Seleccionar...</option>
              <option v-for="item in staff" :key="item.id" :value="item.id">
                {{ item.full_name }} · {{ item.permission_watchers_count || 0 }} destinatarios
              </option>
            </BFormSelect>
          </div>

          <div v-if="canAddWatcher" class="permission-context-card mt-3">
            <div class="permission-context-card__icon">
              <i :class="scope === 'type' ? 'bx bx-category' : 'bx bx-user'"></i>
            </div>
            <div>
              <div class="permission-context-card__label">Configurando</div>
              <div class="permission-context-card__title">{{ currentLabel }}</div>
              <div class="permission-context-card__hint">{{ currentScopeHint }}</div>
            </div>
          </div>
        </section>

        <section class="permission-type-panel permission-watchers-panel">
          <div class="permission-section-title mb-3">
            <i class="bx bx-user-plus"></i>
            <span>Agregar destinatario</span>
          </div>

          <div class="permission-target-grid">
            <button
              v-for="option in targetOptions"
              :key="option.value"
              type="button"
              class="permission-target-option"
              :class="{ 'permission-target-option--active': form.target_type === option.value }"
              @click="selectTargetType(option.value)"
            >
              <i :class="`bx ${targetOptionIcon(option.value)}`"></i>
              <span>{{ option.label }}</span>
              <small>{{ targetOptionDescription(option.value) }}</small>
            </button>
          </div>

          <div class="row g-3 mt-1">
            <div v-if="form.target_type === 'role'" class="col-12">
              <label class="form-label">Rol</label>
              <BFormSelect v-model="form.role_id">
                <option :value="null">Seleccionar rol...</option>
                <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
              </BFormSelect>
            </div>

            <div v-if="form.target_type === 'user'" class="col-12">
              <label class="form-label">Usuario</label>
              <BFormSelect v-model="form.user_id">
                <option :value="null">Seleccionar usuario...</option>
                <option v-for="user in users" :key="user.id" :value="user.id">
                  {{ formatUserOption(user).text }}
                </option>
              </BFormSelect>
            </div>

            <div class="col-12">
              <div class="permission-option-grid permission-option-grid--compact">
                <div class="permission-option-card">
                  <BFormCheckbox v-model="form.notify">Enviar aviso</BFormCheckbox>
                  <div class="form-text">Recibe notificación de la solicitud.</div>
                </div>
                <div class="permission-option-card">
                  <BFormCheckbox v-model="form.can_view">Puede ver solicitudes</BFormCheckbox>
                  <div class="form-text">Tiene acceso de lectura al detalle.</div>
                </div>
              </div>
            </div>

            <div class="col-12">
              <div
                class="permission-type-status-card permission-watcher-status-card"
                :class="{ 'permission-type-status-card--inactive': !form.active }"
              >
                <div class="permission-type-status-card__content">
                  <div class="permission-type-status-card__title">
                    <i :class="form.active ? 'bx bx-check-circle' : 'bx bx-pause-circle'"></i>
                    <span>Estado del destinatario</span>
                  </div>
                  <p>{{ form.active ? "Se aplicará al guardar los cambios." : "Quedará registrado, pero no se aplicará." }}</p>
                </div>
                <BFormCheckbox v-model="form.active" switch class="permission-type-status-switch">
                  {{ form.active ? "Activo" : "Inactivo" }}
                </BFormCheckbox>
              </div>
            </div>
          </div>
        </section>

        <section class="permission-type-panel permission-watchers-panel">
          <div class="permission-section-title mb-3">
            <i class="bx bx-list-ul"></i>
            <span>Quién debe enterarse en esta regla</span>
          </div>

          <LoadingState v-if="loadingItems" message="Cargando destinatarios..." compact />
          <EmptyState
            v-else-if="!items.length"
            icon="bx-user-x"
            title="Sin destinatarios"
            text="Agrega destinatarios o guarda vacío para dejar esta regla sin destinatarios."
          />
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle permission-table">
              <thead>
                <tr>
                  <th>Destinatario</th>
                  <th>Aviso</th>
                  <th>Puede ver</th>
                  <th>Activo</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in items" :key="`${item.target_type}-${item.role_id || 0}-${item.user_id || 0}-${index}`">
                  <td>
                    <div class="permission-watcher-recipient">
                      <span class="permission-watcher-recipient__icon">
                        <i :class="`bx ${watcherIcon(item)}`"></i>
                      </span>
                      <div>
                        <div class="fw-semibold">{{ watcherLabel(item) }}</div>
                        <div class="text-muted small">{{ targetOptionDescription(item.target_type) }}</div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <BFormCheckbox
                      v-model="item.notify"
                      switch
                      :disabled="saving"
                      @change="persistWatchers({ successTitle: 'Aviso actualizado', successText: 'El destinatario fue actualizado.' })"
                    >
                      Aviso
                    </BFormCheckbox>
                  </td>
                  <td>
                    <BFormCheckbox
                      v-model="item.can_view"
                      switch
                      :disabled="saving"
                      @change="persistWatchers({ successTitle: 'Lectura actualizada', successText: 'El destinatario fue actualizado.' })"
                    >
                      Lectura
                    </BFormCheckbox>
                  </td>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <BFormCheckbox
                        v-model="item.active"
                        switch
                        :disabled="saving"
                        @change="persistWatchers({ successTitle: 'Estado actualizado', successText: 'El destinatario fue actualizado.' })"
                      />
                      <StatusBadge :status="item.active ? 'activo' : 'inactivo'" />
                    </div>
                  </td>
                  <td class="text-end">
                    <BButton
                      class="permission-action-button permission-action-button--cancel"
                      variant="outline-light"
                      title="Quitar destinatario"
                      aria-label="Quitar destinatario"
                      @click="removeWatcher(index)"
                    >
                      <i class="bx bx-trash"></i>
                    </BButton>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>

      <div class="permission-type-modal__footer">
        <BButton variant="outline-secondary" @click="showWatcherModal = false">
          Cerrar
        </BButton>
        <BButton variant="outline-secondary" @click="resetForm">
          <i class="bx bx-reset me-1"></i>Limpiar
        </BButton>
        <BButton variant="primary" :disabled="saving || !canAddWatcher" @click="addWatcher">
          <i :class="saving ? 'bx bx-loader-alt bx-spin me-1' : 'bx bx-plus me-1'"></i>
          {{ saving ? "Guardando..." : "Agregar destinatario" }}
        </BButton>
      </div>
    </BModal>
  </Layout>
</template>
