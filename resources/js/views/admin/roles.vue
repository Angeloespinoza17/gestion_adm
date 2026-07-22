<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";

const emptyForm = () => ({
  id: null,
  name: "",
  slug: "",
  description: "",
  active: true,
  permissions: [],
  modules: [],
  users: [],
});

const permissionModeLabels = {
  read: "Lectura",
  operation: "Operación",
  reports: "Reportes",
  all: "Todo",
  none: "Quitar",
};

const readPrefixes = ["ver_"];
const reportPrefixes = ["exportar_"];
const operationPrefixes = [
  "crear_",
  "editar_",
  "gestionar_",
  "registrar_",
  "cambiar_",
  "solicitar_",
  "revisar_",
  "validar_",
  "subir_",
  "mover_",
  "renovar_",
  "medir_",
  "entregar_",
  "autorizar_",
  "cerrar_",
  "cancelar_",
  "aprobar_",
  "rechazar_",
  "promover_",
  "imprimir_",
  "forzar_",
  "dar_baja_",
];
const adminPrefixes = ["administrar_", "configurar_", "eliminar_"];

export default {
  components: { Layout, LoadingState, Multiselect },
  data() {
    return {
      loading: false,
      saving: false,
      removingUserId: null,
      roles: [],
      catalogs: { permissions: [], modules: [], permission_groups: [] },
      permissionModeLabels,
      showModal: false,
      form: emptyForm(),
      expandedModuleGroups: [],
      filters: {
        roleSearch: "",
        moduleSearch: "",
        groupSearch: "",
        userSearch: "",
      },
      error: null,
      success: null,
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
    moduleLookup() {
      return (this.catalogs.modules || []).reduce((lookup, module) => {
        lookup[module.id] = module;
        return lookup;
      }, {});
    },
    permissionLookup() {
      return (this.catalogs.permissions || []).reduce((lookup, permission) => {
        lookup[permission.id] = permission;
        return lookup;
      }, {});
    },
    selectedPermissionSet() {
      return new Set(this.normalizeIdList(this.form.permissions));
    },
    selectedModuleSet() {
      return new Set(this.normalizeIdList(this.form.modules));
    },
    filteredRoles() {
      const search = this.filters.roleSearch.trim().toLowerCase();
      if (!search) {
        return this.roles;
      }

      return this.roles.filter((role) =>
        [role.name, role.slug, role.description]
          .filter(Boolean)
          .some((value) => String(value).toLowerCase().includes(search))
      );
    },
    filteredRoleUsers() {
      const search = this.filters.userSearch.trim().toLowerCase();
      const users = this.form.users || [];

      if (!search) return users;

      return users.filter((user) =>
        [user.name, user.email, user.cargo?.name]
          .filter(Boolean)
          .some((value) => String(value).toLowerCase().includes(search))
      );
    },
    permissionOptions() {
      return (this.catalogs.permissions || []).map((permission) => ({
        value: permission.id,
        label: `${permission.name} (${permission.slug})`,
      }));
    },
    moduleGroups() {
      const modules = this.catalogs.modules || [];
      const moduleIds = new Set(modules.map((module) => Number(module.id)));

      return modules.filter(
        (module) => !module.parent_id || !moduleIds.has(Number(module.parent_id))
      );
    },
    filteredModuleGroups() {
      const search = this.filters.moduleSearch.trim().toLowerCase();
      if (!search) return this.moduleGroups;

      return this.moduleGroups.filter((group) =>
        this.moduleGroupItems(group).some((module) =>
          [module.name, module.slug, module.frontend_route]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(search))
        )
      );
    },
    incompleteModuleGroups() {
      return this.moduleGroups.filter((group) => {
        const stats = this.moduleGroupStats(group);
        return stats.selected > 0 && stats.selected < stats.total;
      });
    },
    permissionGroups() {
      return (this.catalogs.permission_groups || []).map((group) => ({
        ...group,
        permissions: group.permissions || [],
      }));
    },
    filteredPermissionGroups() {
      const search = this.filters.groupSearch.trim().toLowerCase();
      if (!search) {
        return this.permissionGroups;
      }

      return this.permissionGroups.filter((group) =>
        [
          group.name,
          group.slug,
          group.description,
          group.system_module?.name,
          ...(group.permissions || []).flatMap((permission) => [permission.name, permission.slug]),
        ]
          .filter(Boolean)
          .some((value) => String(value).toLowerCase().includes(search))
      );
    },
    groupedPermissionIds() {
      return new Set(
        this.permissionGroups.flatMap((group) => (group.permissions || []).map((permission) => Number(permission.id)))
      );
    },
    ungroupedPermissions() {
      return (this.catalogs.permissions || []).filter((permission) => !this.groupedPermissionIds.has(Number(permission.id)));
    },
    selectedPermissionsCount() {
      return this.selectedPermissionSet.size;
    },
    selectedModulesCount() {
      return this.selectedModuleSet.size;
    },
    modalSummary() {
      return [
        {
          label: "Permisos",
          value: this.selectedPermissionsCount,
          total: (this.catalogs.permissions || []).length,
          icon: "bx-lock-open",
        },
        {
          label: "Módulos visibles",
          value: this.selectedModulesCount,
          total: (this.catalogs.modules || []).length,
          icon: "bx-grid-alt",
        },
        {
          label: "Grupos completos",
          value: this.permissionGroups.filter((group) => this.groupStats(group).selected === this.groupStats(group).total).length,
          total: this.permissionGroups.length,
          icon: "bx-check-double",
        },
      ];
    },
  },
  mounted() {
    this.loadCatalogs();
    this.loadRoles();
  },
  methods: {
    normalizeIdList(values) {
      return (values || [])
        .map((value) => Number(value))
        .filter((value) => Number.isInteger(value) && value > 0);
    },
    async loadCatalogs() {
      const response = await axios.get("/api/admin/roles/catalogs");
      this.catalogs = {
        permissions: response.data.permissions || [],
        modules: response.data.modules || [],
        permission_groups: response.data.permission_groups || [],
      };
    },
    async loadRoles() {
      this.loading = true;
      try {
        const response = await axios.get("/api/admin/roles");
        this.roles = response.data.data;
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    async openEdit(role) {
      this.error = null;
      this.filters.moduleSearch = "";
      this.filters.groupSearch = "";
      this.filters.userSearch = "";
      const response = await axios.get(`/api/admin/roles/${role.id}`);
      const data = response.data.data;

      this.form = {
        id: data.id,
        name: data.name,
        slug: data.slug,
        description: data.description || "",
        active: Boolean(data.active),
        permissions: (data.permissions || []).map((permission) => permission.id),
        modules: (data.modules || []).map((module) => module.id),
        users: data.users || [],
      };
      this.expandedModuleGroups = this.moduleGroups
        .filter((group) => {
          const stats = this.moduleGroupStats(group);
          return stats.selected > 0 && stats.selected < stats.total;
        })
        .map((group) => Number(group.id));
      this.showModal = true;
    },
    openCreate() {
      this.form = emptyForm();
      this.expandedModuleGroups = [];
      this.filters.moduleSearch = "";
      this.filters.groupSearch = "";
      this.showModal = true;
    },
    async save() {
      this.saving = true;
      this.error = null;
      this.success = null;
      try {
        if (this.isEditing) {
          await axios.put(`/api/admin/roles/${this.form.id}`, {
            name: this.form.name,
            slug: this.form.slug,
            description: this.form.description || null,
            active: this.form.active,
            permissions: this.normalizeIdList(this.form.permissions),
            modules: this.normalizeIdList(this.form.modules),
          });
          this.success = "Rol actualizado.";
        } else {
          await axios.post(`/api/admin/roles`, {
            name: this.form.name,
            slug: this.form.slug,
            description: this.form.description || null,
            active: this.form.active,
            permissions: this.normalizeIdList(this.form.permissions),
            modules: this.normalizeIdList(this.form.modules),
          });
          this.success = "Rol creado.";
        }
        this.showModal = false;
        this.loadRoles();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async remove(role) {
      if (!confirm(`Eliminar rol ${role.name}?`)) return;
      await axios.delete(`/api/admin/roles/${role.id}`);
      this.loadRoles();
    },
    async removeUser(user) {
      if (!confirm(`¿Quitar a ${user.name} del rol ${this.form.name}?`)) return;

      this.removingUserId = user.id;
      this.error = null;
      try {
        await axios.delete(`/api/admin/roles/${this.form.id}/users/${user.id}`);
        this.form.users = (this.form.users || []).filter((item) => item.id !== user.id);
        const role = this.roles.find((item) => item.id === this.form.id);
        if (role) role.users_count = Math.max(0, Number(role.users_count || 0) - 1);
        this.success = `${user.name} fue retirado del rol.`;
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.removingUserId = null;
      }
    },
    formatError(error) {
      return error?.response?.data?.message || error?.message || "Error desconocido";
    },
    isPermissionSelected(permissionId) {
      return this.selectedPermissionSet.has(Number(permissionId));
    },
    isModuleSelected(moduleId) {
      return this.selectedModuleSet.has(Number(moduleId));
    },
    directChildModules(moduleId) {
      const parentId = Number(moduleId);

      return (this.catalogs.modules || [])
        .filter((module) => Number(module.parent_id) === parentId)
        .sort((left, right) =>
          Number(left.sort_order || 0) - Number(right.sort_order || 0) ||
          String(left.name || "").localeCompare(String(right.name || ""))
        );
    },
    moduleDescendants(moduleId, depth = 1) {
      return this.directChildModules(moduleId).flatMap((module) => [
        { ...module, depth },
        ...this.moduleDescendants(module.id, depth + 1),
      ]);
    },
    moduleGroupItems(group) {
      return [{ ...group, depth: 0 }, ...this.moduleDescendants(group.id)];
    },
    moduleGroupStats(group) {
      const ids = this.moduleGroupItems(group).map((module) => Number(module.id));
      const selected = ids.filter((id) => this.selectedModuleSet.has(id)).length;

      return {
        selected,
        total: ids.length,
        complete: ids.length > 0 && selected === ids.length,
        partial: selected > 0 && selected < ids.length,
      };
    },
    isModuleGroupExpanded(moduleId) {
      return this.filters.moduleSearch.trim() !== "" || this.expandedModuleGroups.includes(Number(moduleId));
    },
    toggleModuleGroupExpanded(moduleId) {
      const id = Number(moduleId);
      const expanded = new Set(this.expandedModuleGroups.map(Number));

      if (expanded.has(id)) {
        expanded.delete(id);
      } else {
        expanded.add(id);
      }

      this.expandedModuleGroups = Array.from(expanded);
    },
    removeModules(moduleIds) {
      const ids = new Set(moduleIds.map(Number));
      this.form.modules = this.normalizeIdList(this.form.modules).filter((id) => !ids.has(id));
    },
    toggleModule(module) {
      const id = Number(module.id);
      const moduleIds = [id, ...this.childModuleIds(id)];
      const selected = this.selectedModuleSet;

      if (moduleIds.every((moduleId) => selected.has(moduleId))) {
        this.removeModules(moduleIds);
        return;
      }

      this.addModule(id, true);
    },
    toggleModuleGroup(group) {
      const ids = this.moduleGroupItems(group).map((module) => Number(module.id));

      if (this.moduleGroupStats(group).complete) {
        this.removeModules(ids);
        return;
      }

      const selected = new Set(this.normalizeIdList(this.form.modules));
      ids.forEach((id) => selected.add(id));
      this.form.modules = Array.from(selected);
      this.expandedModuleGroups = Array.from(new Set([...this.expandedModuleGroups, Number(group.id)]));
    },
    selectAllModules() {
      this.form.modules = (this.catalogs.modules || []).map((module) => Number(module.id));
    },
    clearAllModules() {
      this.form.modules = [];
    },
    togglePermission(permissionId) {
      const id = Number(permissionId);
      const selected = new Set(this.normalizeIdList(this.form.permissions));

      if (selected.has(id)) {
        selected.delete(id);
      } else {
        selected.add(id);
      }

      this.form.permissions = Array.from(selected);
    },
    childModuleIds(moduleId) {
      const parentId = Number(moduleId);

      return (this.catalogs.modules || [])
        .filter((module) => Number(module.parent_id) === parentId)
        .flatMap((module) => [Number(module.id), ...this.childModuleIds(module.id)])
        .filter((id) => Number.isInteger(id) && id > 0);
    },
    addModule(moduleId, includeChildren = false) {
      const id = Number(moduleId);
      if (!id) return;

      const selected = new Set(this.normalizeIdList(this.form.modules));
      selected.add(id);

      if (includeChildren) {
        this.childModuleIds(id).forEach((childId) => selected.add(childId));
      }

      let parentId = Number(this.moduleLookup[id]?.parent_id || 0);
      while (parentId > 0) {
        selected.add(parentId);
        parentId = Number(this.moduleLookup[parentId]?.parent_id || 0);
      }

      this.form.modules = Array.from(selected);
    },
    addGroupModule(group) {
      if (group.system_module?.id) {
        this.addModule(group.system_module.id, true);
      }
    },
    permissionActionType(permission) {
      const slug = String(permission?.slug || "");

      if (slug.endsWith(".ver") || slug.endsWith(".dashboard") || readPrefixes.some((prefix) => slug.startsWith(prefix))) {
        return "read";
      }

      if (
        slug.includes("reportes") ||
        slug.includes(".reportes.") ||
        slug.endsWith(".exportar") ||
        reportPrefixes.some((prefix) => slug.startsWith(prefix))
      ) {
        return "reports";
      }

      if (slug.endsWith(".admin") || adminPrefixes.some((prefix) => slug.startsWith(prefix))) {
        return "admin";
      }

      if (operationPrefixes.some((prefix) => slug.startsWith(prefix)) || slug.includes(".gestionar")) {
        return "operation";
      }

      if (slug.includes(".crear") || slug.includes(".editar") || slug.includes(".calcular") || slug.includes(".aprobar")) {
        return "operation";
      }

      return "operation";
    },
    permissionIdsForMode(group, mode) {
      const permissions = group.permissions || [];

      if (mode === "all") {
        return permissions.map((permission) => Number(permission.id));
      }

      if (mode === "none") {
        return [];
      }

      return permissions
        .filter((permission) => {
          const type = this.permissionActionType(permission);

          if (mode === "read") {
            return type === "read";
          }

          if (mode === "reports") {
            return type === "read" || type === "reports";
          }

          if (mode === "operation") {
            return type === "read" || type === "operation" || type === "reports";
          }

          return false;
        })
        .map((permission) => Number(permission.id));
    },
    setGroupMode(group, mode) {
      const groupIds = new Set((group.permissions || []).map((permission) => Number(permission.id)));
      const selected = new Set(this.normalizeIdList(this.form.permissions));

      groupIds.forEach((id) => selected.delete(id));
      this.permissionIdsForMode(group, mode).forEach((id) => selected.add(id));

      this.form.permissions = Array.from(selected);

      if (mode !== "none") {
        this.addGroupModule(group);
      }
    },
    groupStats(group) {
      const ids = (group.permissions || []).map((permission) => Number(permission.id));
      const selected = ids.filter((id) => this.selectedPermissionSet.has(id)).length;

      return {
        selected,
        total: ids.length,
        percent: ids.length ? Math.round((selected / ids.length) * 100) : 0,
      };
    },
    groupState(group) {
      const stats = this.groupStats(group);
      if (stats.total === 0 || stats.selected === 0) return "empty";
      if (stats.selected === stats.total) return "complete";
      return "partial";
    },
    groupStateLabel(group) {
      const state = this.groupState(group);
      if (state === "complete") return "Completo";
      if (state === "partial") return "Parcial";
      return "Sin permisos";
    },
    roleStatusVariant(role) {
      return role.active ? "success" : "secondary";
    },
  },
};
</script>

<template>
  <Layout>
    <div class="roles-page">
      <div class="roles-header">
        <div>
          <div class="roles-eyebrow">Administración</div>
          <h4 class="roles-title">Roles</h4>
          <p class="roles-subtitle">Asigna permisos y módulos visibles por rol usando grupos operativos.</p>
        </div>
        <BButton variant="primary" @click="openCreate">
          <i class="bx bx-plus me-1"></i>Nuevo rol
        </BButton>
      </div>

      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
      <BAlert v-if="success" variant="success" show class="mb-3">{{ success }}</BAlert>

      <BCard class="roles-card">
        <template #header>
          <div class="roles-card__header">
            <div class="roles-section-title">
              <i class="bx bx-user-check"></i>
              <span>Listado</span>
            </div>
            <div class="roles-search">
              <i class="bx bx-search"></i>
              <BFormInput v-model="filters.roleSearch" size="sm" placeholder="Buscar rol" />
            </div>
          </div>
        </template>

        <BTable
          :items="filteredRoles"
          :busy="loading"
          small
          responsive
          hover
          :fields="[
            { key: 'name', label: 'Nombre' },
            { key: 'slug', label: 'Slug' },
            { key: 'users_count', label: 'Usuarios' },
            { key: 'permissions_count', label: 'Permisos' },
            { key: 'modules_count', label: 'Módulos' },
            { key: 'active', label: 'Estado' },
            { key: 'actions', label: 'Acciones' },
          ]"
        >
          <template #table-busy>
            <LoadingState message="Cargando roles..." compact />
          </template>
          <template #cell(name)="{ item }">
            <div>
              <div class="fw-semibold">{{ item.name }}</div>
              <div v-if="item.description" class="text-muted small">{{ item.description }}</div>
            </div>
          </template>
          <template #cell(active)="{ item }">
            <BBadge :variant="roleStatusVariant(item)">{{ item.active ? "Activo" : "Inactivo" }}</BBadge>
          </template>
          <template #cell(users_count)="{ item }">
            <BButton size="sm" variant="link" class="p-0" @click="openEdit(item)">
              {{ item.users_count }} {{ Number(item.users_count) === 1 ? "usuario" : "usuarios" }}
            </BButton>
          </template>
          <template #cell(actions)="{ item }">
            <div class="roles-actions">
              <BButton size="sm" variant="outline-primary" @click="openEdit(item)">
                <i class="bx bx-edit-alt me-1"></i>Editar
              </BButton>
              <BButton size="sm" variant="outline-danger" @click="remove(item)">
                <i class="bx bx-trash me-1"></i>Eliminar
              </BButton>
            </div>
          </template>
        </BTable>
      </BCard>

      <BModal v-model="showModal" :title="isEditing ? 'Editar rol' : 'Nuevo rol'" size="xl" hide-footer>
        <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

        <div class="role-form-grid">
          <section class="role-panel">
            <div class="role-panel__title">
              <i class="bx bx-id-card"></i>
              <span>Datos del rol</span>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Nombre</label>
                <BFormInput v-model="form.name" />
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Slug</label>
                <BFormInput v-model="form.slug" />
              </div>
              <div class="col-12 mb-3">
                <label class="form-label">Descripción</label>
                <BFormTextarea v-model="form.description" rows="2" />
              </div>
              <div class="col-12">
                <BFormCheckbox v-model="form.active">Activo</BFormCheckbox>
              </div>
            </div>
          </section>

          <section class="role-panel role-summary-panel">
            <div v-for="item in modalSummary" :key="item.label" class="role-summary-item">
              <div class="role-summary-item__icon">
                <i :class="['bx', item.icon]"></i>
              </div>
              <div>
                <div class="role-summary-item__label">{{ item.label }}</div>
                <div class="role-summary-item__value">{{ item.value }} / {{ item.total }}</div>
              </div>
            </div>
          </section>
        </div>

        <section v-if="isEditing" class="role-panel mt-3">
          <div class="role-panel__header">
            <div class="role-panel__title">
              <i class="bx bx-user-check"></i>
              <span>Usuarios asociados ({{ (form.users || []).length }})</span>
            </div>
            <div class="permission-group-search">
              <i class="bx bx-search"></i>
              <BFormInput v-model="filters.userSearch" size="sm" placeholder="Buscar usuario" />
            </div>
          </div>

          <div v-if="filteredRoleUsers.length" class="role-users-list">
            <div v-for="user in filteredRoleUsers" :key="user.id" class="role-user-item">
              <div class="role-user-item__identity">
                <div class="fw-semibold">{{ user.name }}</div>
                <div class="text-muted small">
                  {{ user.email }}<span v-if="user.cargo?.name"> · {{ user.cargo.name }}</span>
                </div>
              </div>
              <div class="d-flex align-items-center gap-2">
                <BBadge :variant="user.active ? 'success' : 'secondary'">
                  {{ user.active ? "Activo" : "Inactivo" }}
                </BBadge>
                <BButton
                  size="sm"
                  variant="outline-danger"
                  :disabled="removingUserId === user.id"
                  @click="removeUser(user)"
                >
                  <i :class="removingUserId === user.id ? 'bx bx-loader-alt bx-spin me-1' : 'bx bx-user-minus me-1'"></i>
                  {{ removingUserId === user.id ? "Quitando..." : "Quitar del rol" }}
                </BButton>
              </div>
            </div>
          </div>
          <BAlert v-else variant="light" show class="mb-0">
            {{ (form.users || []).length ? "No hay usuarios para la búsqueda actual." : "Este rol no tiene usuarios asociados." }}
          </BAlert>
        </section>

        <section class="role-panel mt-3">
          <div class="role-panel__header">
            <div class="role-panel__title">
              <i class="bx bx-grid-alt"></i>
              <span>Módulos visibles</span>
            </div>
            <span class="module-selection-count">{{ selectedModulesCount }} de {{ (catalogs.modules || []).length }}</span>
          </div>

          <p class="module-picker-help">
            Selecciona un área completa o abre el grupo para elegir sus submódulos. Los módulos marcados son los que aparecerán en el menú lateral.
          </p>

          <div class="module-picker-toolbar">
            <div class="module-search">
              <i class="bx bx-search"></i>
              <BFormInput v-model="filters.moduleSearch" size="sm" placeholder="Buscar módulo o ruta" />
            </div>
            <div class="module-picker-actions">
              <BButton size="sm" variant="outline-primary" @click="selectAllModules">Seleccionar todos</BButton>
              <BButton size="sm" variant="outline-secondary" @click="clearAllModules">Quitar todos</BButton>
            </div>
          </div>

          <BAlert v-if="incompleteModuleGroups.length" variant="warning" show class="module-picker-alert">
            <i class="bx bx-info-circle"></i>
            <span>
              Hay {{ incompleteModuleGroups.length }}
              {{ incompleteModuleGroups.length === 1 ? "área incompleta" : "áreas incompletas" }}.
              Revisa sus submódulos antes de guardar.
            </span>
          </BAlert>

          <div v-if="filteredModuleGroups.length" class="module-picker-grid">
            <article
              v-for="group in filteredModuleGroups"
              :key="group.id"
              :class="[
                'module-group-card',
                { 'module-group-card--complete': moduleGroupStats(group).complete },
                { 'module-group-card--partial': moduleGroupStats(group).partial },
              ]"
            >
              <div class="module-group-card__header">
                <button
                  type="button"
                  class="module-group-card__toggle"
                  :aria-expanded="isModuleGroupExpanded(group.id)"
                  @click="toggleModuleGroupExpanded(group.id)"
                >
                  <span class="module-group-card__icon"><i class="bx bx-grid-alt"></i></span>
                  <span class="module-group-card__identity">
                    <strong>{{ group.name }}</strong>
                    <small>{{ moduleGroupStats(group).selected }} de {{ moduleGroupStats(group).total }} visibles</small>
                  </span>
                  <i
                    v-if="moduleGroupStats(group).total > 1"
                    :class="['bx', isModuleGroupExpanded(group.id) ? 'bx-chevron-up' : 'bx-chevron-down']"
                  ></i>
                </button>
                <BButton
                  size="sm"
                  :variant="moduleGroupStats(group).complete ? 'outline-danger' : 'outline-success'"
                  @click="toggleModuleGroup(group)"
                >
                  <i :class="moduleGroupStats(group).complete ? 'bx bx-x me-1' : 'bx bx-check me-1'"></i>
                  {{
                    moduleGroupStats(group).complete
                      ? "Quitar"
                      : moduleGroupStats(group).total === 1
                        ? "Seleccionar"
                        : "Área completa"
                  }}
                </BButton>
              </div>

              <div v-if="isModuleGroupExpanded(group.id)" class="module-group-card__items">
                <label
                  v-for="module in moduleGroupItems(group)"
                  :key="module.id"
                  class="module-option"
                  :class="{ 'module-option--parent': module.depth === 0 }"
                  :style="{ '--module-depth': module.depth }"
                >
                  <input
                    type="checkbox"
                    :checked="isModuleSelected(module.id)"
                    @change="toggleModule(module)"
                  />
                  <span class="module-option__check"><i class="bx bx-check"></i></span>
                  <span class="module-option__body">
                    <span class="module-option__name">{{ module.name }}</span>
                    <span class="module-option__meta">
                      {{ module.depth === 0 && moduleGroupStats(group).total > 1 ? "Módulo principal" : module.frontend_route || module.slug }}
                    </span>
                  </span>
                </label>
              </div>
            </article>
          </div>

          <BAlert v-else variant="light" show class="mb-0 mt-3">No hay módulos para la búsqueda actual.</BAlert>
        </section>

        <section class="role-panel mt-3">
          <div class="role-panel__header">
            <div class="role-panel__title">
              <i class="bx bx-lock-open"></i>
              <span>Permisos por grupo</span>
            </div>
            <div class="permission-group-search">
              <i class="bx bx-search"></i>
              <BFormInput v-model="filters.groupSearch" size="sm" placeholder="Buscar grupo o permiso" />
            </div>
          </div>

          <div class="permission-group-grid">
            <div
              v-for="group in filteredPermissionGroups"
              :key="group.id"
              :class="['permission-group-card', `permission-group-card--${groupState(group)}`]"
            >
              <div class="permission-group-card__header">
                <div>
                  <div class="permission-group-card__title">{{ group.name }}</div>
                  <div class="permission-group-card__meta">
                    <span>{{ group.system_module?.name || "Sin módulo vinculado" }}</span>
                    <span>·</span>
                    <span>{{ groupStats(group).selected }}/{{ groupStats(group).total }}</span>
                  </div>
                </div>
                <BBadge :variant="groupState(group) === 'complete' ? 'success' : groupState(group) === 'partial' ? 'warning' : 'secondary'">
                  {{ groupStateLabel(group) }}
                </BBadge>
              </div>

              <p class="permission-group-card__description">{{ group.description }}</p>

              <div class="permission-progress" aria-hidden="true">
                <span :style="{ width: `${groupStats(group).percent}%` }"></span>
              </div>

              <div class="permission-group-actions">
                <BButton size="sm" variant="outline-secondary" @click="setGroupMode(group, 'read')">
                  {{ permissionModeLabels.read }}
                </BButton>
                <BButton size="sm" variant="outline-primary" @click="setGroupMode(group, 'operation')">
                  {{ permissionModeLabels.operation }}
                </BButton>
                <BButton size="sm" variant="outline-info" @click="setGroupMode(group, 'reports')">
                  {{ permissionModeLabels.reports }}
                </BButton>
                <BButton size="sm" variant="outline-success" @click="setGroupMode(group, 'all')">
                  {{ permissionModeLabels.all }}
                </BButton>
                <BButton size="sm" variant="outline-danger" @click="setGroupMode(group, 'none')">
                  {{ permissionModeLabels.none }}
                </BButton>
              </div>

              <details class="permission-list">
                <summary>Ver permisos del grupo</summary>
                <div class="permission-list__items">
                  <label v-for="permission in group.permissions" :key="permission.id" class="permission-check">
                    <input
                      type="checkbox"
                      :checked="isPermissionSelected(permission.id)"
                      @change="togglePermission(permission.id)"
                    />
                    <span>
                      <span class="permission-check__name">{{ permission.name }}</span>
                      <span class="permission-check__slug">{{ permission.slug }}</span>
                    </span>
                  </label>
                </div>
              </details>
            </div>
          </div>

          <BAlert v-if="!filteredPermissionGroups.length" variant="info" show class="mt-3 mb-0">
            No hay grupos para la búsqueda actual.
          </BAlert>
        </section>

        <section class="role-panel mt-3">
          <details class="advanced-permissions">
            <summary>Permisos individuales avanzados</summary>
            <div class="mt-3">
              <Multiselect
                v-model="form.permissions"
                :options="permissionOptions"
                mode="multiple"
                :close-on-select="false"
                :searchable="true"
              />
            </div>
            <div v-if="ungroupedPermissions.length" class="ungrouped-permissions mt-3">
              <div class="text-muted small mb-2">Permisos sin grupo</div>
              <label v-for="permission in ungroupedPermissions" :key="permission.id" class="permission-check">
                <input
                  type="checkbox"
                  :checked="isPermissionSelected(permission.id)"
                  @change="togglePermission(permission.id)"
                />
                <span>
                  <span class="permission-check__name">{{ permission.name }}</span>
                  <span class="permission-check__slug">{{ permission.slug }}</span>
                </span>
              </label>
            </div>
          </details>
        </section>

        <div class="d-flex justify-content-end gap-2 mt-3">
          <BButton variant="secondary" @click="showModal = false">Cancelar</BButton>
          <BButton variant="primary" :disabled="saving" @click="save">
            <i :class="saving ? 'bx bx-loader-alt bx-spin me-1' : 'bx bx-save me-1'"></i>
            {{ saving ? "Guardando..." : "Guardar" }}
          </BButton>
        </div>
      </BModal>
    </div>
  </Layout>
</template>

<style scoped>
.roles-page {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.roles-header {
  align-items: flex-start;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
}

.roles-eyebrow {
  color: #74788d;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0;
  text-transform: uppercase;
}

.roles-title {
  color: #2a3042;
  font-weight: 700;
  margin: 0.15rem 0;
}

.roles-subtitle {
  color: #74788d;
  margin: 0;
}

.roles-card,
.role-panel {
  border: 1px solid #eff2f7;
  border-radius: 8px;
}

.roles-card__header,
.role-panel__header {
  align-items: center;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
}

.roles-section-title,
.role-panel__title {
  align-items: center;
  color: #2a3042;
  display: flex;
  font-weight: 700;
  gap: 0.4rem;
}

.roles-search,
.permission-group-search {
  position: relative;
  width: min(320px, 100%);
}

.roles-search i,
.permission-group-search i {
  color: #74788d;
  left: 0.75rem;
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  z-index: 2;
}

.roles-search :deep(input),
.permission-group-search :deep(input) {
  padding-left: 2.1rem;
}

.roles-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}

.role-form-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: minmax(0, 1fr) 320px;
}

.role-panel {
  background: #fff;
  padding: 1rem;
}

.role-summary-panel {
  display: grid;
  gap: 0.75rem;
}

.role-summary-item {
  align-items: center;
  background: #f8f9fa;
  border: 1px solid #eff2f7;
  border-radius: 8px;
  display: flex;
  gap: 0.75rem;
  padding: 0.85rem;
}

.role-summary-item__icon {
  align-items: center;
  background: #eef1ff;
  border-radius: 8px;
  color: #556ee6;
  display: inline-flex;
  flex: 0 0 38px;
  height: 38px;
  justify-content: center;
  width: 38px;
}

.role-summary-item__label {
  color: #74788d;
  font-size: 0.78rem;
}

.role-summary-item__value {
  color: #2a3042;
  font-weight: 700;
}

.role-users-list {
  border: 1px solid #eff2f7;
  border-radius: 8px;
  margin-top: 1rem;
  overflow: hidden;
}

.role-user-item {
  align-items: center;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
  padding: 0.8rem 1rem;
}

.role-user-item + .role-user-item {
  border-top: 1px solid #eff2f7;
}

.role-user-item__identity {
  min-width: 0;
}

.module-selection-count {
  background: #eef1ff;
  border-radius: 999px;
  color: #556ee6;
  font-size: 0.78rem;
  font-weight: 700;
  padding: 0.3rem 0.65rem;
}

.module-picker-help {
  color: #74788d;
  font-size: 0.86rem;
  margin: 0.55rem 0 0;
}

.module-picker-toolbar {
  align-items: center;
  display: flex;
  gap: 0.75rem;
  justify-content: space-between;
  margin-top: 1rem;
}

.module-search {
  position: relative;
  width: min(420px, 100%);
}

.module-search i {
  color: #74788d;
  left: 0.75rem;
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  z-index: 2;
}

.module-search :deep(input) {
  padding-left: 2.1rem;
}

.module-picker-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.module-picker-alert {
  align-items: center;
  display: flex;
  gap: 0.45rem;
  margin: 0.85rem 0 0;
  padding: 0.65rem 0.8rem;
}

.module-picker-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  margin-top: 0.85rem;
}

.module-group-card {
  align-self: start;
  border: 1px solid #e9ecf5;
  border-left: 4px solid #ced4da;
  border-radius: 10px;
  min-width: 0;
  overflow: hidden;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.module-group-card--partial {
  border-left-color: #f1b44c;
}

.module-group-card--complete {
  border-left-color: #34c38f;
}

.module-group-card__header {
  align-items: center;
  background: #fff;
  display: flex;
  gap: 0.6rem;
  justify-content: space-between;
  padding: 0.75rem;
}

.module-group-card__toggle {
  align-items: center;
  background: transparent;
  border: 0;
  color: inherit;
  display: flex;
  flex: 1;
  gap: 0.65rem;
  min-width: 0;
  padding: 0;
  text-align: left;
}

.module-group-card__toggle:focus-visible {
  border-radius: 6px;
  outline: 2px solid rgba(85, 110, 230, 0.35);
  outline-offset: 3px;
}

.module-group-card__icon {
  align-items: center;
  background: #eef1ff;
  border-radius: 8px;
  color: #556ee6;
  display: inline-flex;
  flex: 0 0 34px;
  height: 34px;
  justify-content: center;
  width: 34px;
}

.module-group-card__identity {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}

.module-group-card__identity strong {
  color: #2a3042;
  font-size: 0.88rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.module-group-card__identity small {
  color: #74788d;
  font-size: 0.74rem;
}

.module-group-card__items {
  background: #f8f9fc;
  border-top: 1px solid #eff2f7;
  display: grid;
  gap: 0.45rem;
  max-height: 320px;
  overflow-y: auto;
  padding: 0.7rem;
}

.module-option {
  --module-depth: 0;
  align-items: center;
  background: #fff;
  border: 1px solid #e9ecf5;
  border-radius: 8px;
  cursor: pointer;
  display: flex;
  gap: 0.55rem;
  margin: 0;
  margin-left: calc(var(--module-depth) * 0.8rem);
  min-width: 0;
  padding: 0.55rem 0.65rem;
  transition: border-color 0.15s ease, background-color 0.15s ease;
}

.module-option:hover {
  background: #f5f7ff;
  border-color: #cfd6fb;
}

.module-option--parent {
  background: #f5f7ff;
}

.module-option input {
  height: 1px;
  opacity: 0;
  position: absolute;
  width: 1px;
}

.module-option__check {
  align-items: center;
  border: 2px solid #adb5bd;
  border-radius: 5px;
  color: transparent;
  display: inline-flex;
  flex: 0 0 20px;
  height: 20px;
  justify-content: center;
  transition: all 0.15s ease;
  width: 20px;
}

.module-option input:checked + .module-option__check {
  background: #556ee6;
  border-color: #556ee6;
  color: #fff;
}

.module-option input:focus-visible + .module-option__check {
  box-shadow: 0 0 0 3px rgba(85, 110, 230, 0.2);
}

.module-option__body,
.module-option__name,
.module-option__meta {
  display: block;
  min-width: 0;
}

.module-option__body {
  flex: 1;
}

.module-option__name {
  color: #2a3042;
  font-size: 0.82rem;
  font-weight: 700;
}

.module-option__meta {
  color: #74788d;
  font-size: 0.7rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.permission-group-grid {
  display: grid;
  gap: 0.85rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.permission-group-card {
  border: 1px solid #eff2f7;
  border-left: 4px solid #ced4da;
  border-radius: 8px;
  padding: 1rem;
}

.permission-group-card--complete {
  border-left-color: #34c38f;
}

.permission-group-card--partial {
  border-left-color: #f1b44c;
}

.permission-group-card__header {
  align-items: flex-start;
  display: flex;
  gap: 0.75rem;
  justify-content: space-between;
}

.permission-group-card__title {
  color: #2a3042;
  font-weight: 700;
}

.permission-group-card__meta {
  color: #74788d;
  display: flex;
  flex-wrap: wrap;
  font-size: 0.78rem;
  gap: 0.25rem;
}

.permission-group-card__description {
  color: #495057;
  font-size: 0.86rem;
  margin: 0.65rem 0;
}

.permission-progress {
  background: #eff2f7;
  border-radius: 999px;
  height: 6px;
  overflow: hidden;
}

.permission-progress span {
  background: #34c38f;
  display: block;
  height: 100%;
  transition: width 0.15s ease;
}

.permission-group-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-top: 0.8rem;
}

.permission-list,
.advanced-permissions {
  margin-top: 0.85rem;
}

.permission-list summary,
.advanced-permissions summary {
  color: #556ee6;
  cursor: pointer;
  font-size: 0.84rem;
  font-weight: 700;
}

.permission-list__items,
.ungrouped-permissions {
  display: grid;
  gap: 0.45rem;
  margin-top: 0.65rem;
}

.permission-check {
  align-items: flex-start;
  border: 1px solid #eff2f7;
  border-radius: 8px;
  display: flex;
  gap: 0.55rem;
  margin: 0;
  padding: 0.55rem;
}

.permission-check input {
  margin-top: 0.2rem;
}

.permission-check__name,
.permission-check__slug {
  display: block;
}

.permission-check__name {
  color: #2a3042;
  font-size: 0.84rem;
  font-weight: 700;
}

.permission-check__slug {
  color: #74788d;
  font-size: 0.76rem;
  overflow-wrap: anywhere;
}

@media (max-width: 991.98px) {
  .role-form-grid,
  .module-picker-grid,
  .permission-group-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 767.98px) {
  .roles-header,
  .roles-card__header,
  .role-panel__header,
  .module-picker-toolbar {
    align-items: stretch;
    flex-direction: column;
  }

  .roles-header .btn,
  .roles-search,
  .module-search,
  .permission-group-search {
    width: 100%;
  }

  .role-user-item {
    align-items: stretch;
    flex-direction: column;
  }

  .role-user-item > .d-flex {
    justify-content: space-between;
  }

  .module-picker-actions .btn {
    flex: 1;
  }

  .module-group-card__header {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
