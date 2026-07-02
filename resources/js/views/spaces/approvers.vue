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
      savingId: null,
      error: null,
      filters: {
        search: "",
        dependency_type_id: null,
      },
      dependencies: [],
      dependencyTypes: [],
      approverUsers: [],
      forms: {},
    };
  },
  computed: {
    permissions() {
      return JSON.parse(localStorage.getItem("permissions") || "[]");
    },
    canEdit() {
      return this.permissions.includes("editar_dependencias");
    },
    typeOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.dependencyTypes || []).map((item) => ({
          value: item.id,
          label: item.name,
        }))
      );
    },
    approverOptions() {
      return (this.approverUsers || []).map((item) => ({
        value: item.id,
        label: `${item.staff?.full_name || item.name}${item.email ? ` · ${item.email}` : ""}`,
      }));
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    buildForm(dependency) {
      return {
        requires_approval: true,
        approver_user_ids: (dependency.approvers || []).map((item) => item.id),
      };
    },
    syncForms() {
      const nextForms = {};
      (this.dependencies || []).forEach((dependency) => {
        nextForms[dependency.id] = this.forms[dependency.id]
          ? {
              requires_approval: true,
              approver_user_ids: [...(this.forms[dependency.id].approver_user_ids || [])],
            }
          : this.buildForm(dependency);
      });
      this.forms = nextForms;
    },
    async load() {
      this.loading = true;
      this.error = null;

      try {
        const response = await axios.get("/api/spaces/dependencies/approvers", {
          params: {
            search: this.filters.search || null,
            dependency_type_id: this.filters.dependency_type_id,
          },
        });

        this.dependencies = response.data.dependencies || [];
        this.dependencyTypes = response.data.dependency_types || [];
        this.approverUsers = response.data.approver_users || [];
        this.syncForms();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    async save(dependency) {
      if (!this.canEdit) {
        return;
      }

      this.savingId = dependency.id;
      this.error = null;

      try {
        const response = await axios.put(`/api/spaces/dependencies/${dependency.id}/approvers`, {
          requires_approval: true,
          approver_user_ids: this.forms[dependency.id]?.approver_user_ids || [],
        });

        const updated = response.data.data;
        this.dependencies = this.dependencies.map((item) => (item.id === updated.id ? updated : item));
        this.forms[dependency.id] = this.buildForm(updated);

        await Swal.fire({
          title: "Gestores actualizados",
          text: response.data.message || "Cambios guardados correctamente.",
          icon: "success",
          timer: 1600,
          showConfirmButton: false,
        });
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.savingId = null;
      }
    },
    resetRow(dependency) {
      this.forms[dependency.id] = this.buildForm(dependency);
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
    <div class="d-sm-flex justify-content-between align-items-center mb-4">
      <div class="mb-3 mb-sm-0">
        <h4 class="mb-0">Gestores de aprobación</h4>
        <div class="text-muted">Asigna quién aprueba o rechaza reservas por dependencia.</div>
      </div>
      <router-link to="/spaces/dependencies" class="btn btn-outline-secondary">Volver a dependencias</router-link>
    </div>

    <BCard class="mb-3">
      <div class="row g-3 align-items-end">
        <div class="col-md-5">
          <label class="form-label">Buscar dependencia</label>
          <BFormInput v-model="filters.search" placeholder="Nombre, código o ubicación" @keyup.enter="load" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Tipo</label>
          <Multiselect v-model="filters.dependency_type_id" :options="typeOptions" :searchable="true" />
        </div>
        <div class="col-md-3 d-flex gap-2">
          <BButton variant="secondary" @click="load">Buscar</BButton>
          <BButton
            variant="outline-secondary"
            @click="
              filters.search = '';
              filters.dependency_type_id = null;
              load();
            "
          >
            Limpiar
          </BButton>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <BCard>
      <BTable
        :items="dependencies"
        :busy="loading"
        :fields="[
          { key: 'dependency', label: 'Dependencia' },
          { key: 'requires_approval', label: 'Aprobación' },
          { key: 'approvers', label: 'Gestores' },
          { key: 'actions', label: 'Acciones' },
        ]"
        responsive
        small
      >
        <template #table-busy>
          <LoadingState message="Cargando gestores..." compact />
        </template>
        <template #cell(dependency)="{ item }">
          <div>
            <div class="fw-semibold">{{ item.name }}</div>
            <div class="text-muted small">
              {{ item.type?.name || "Sin tipo" }} · {{ item.code || "Sin código" }}
            </div>
            <div class="text-muted small">
              {{ item.location || item.floor_sector || "-" }}
            </div>
          </div>
        </template>

        <template #cell(requires_approval)="{ item }">
          <div class="d-flex flex-column gap-2">
            <BFormCheckbox :model-value="true" disabled>
              Requiere aprobación
            </BFormCheckbox>
            <BBadge variant="warning">Obligatoria</BBadge>
          </div>
        </template>

        <template #cell(approvers)="{ item }">
          <div style="min-width: 320px">
            <Multiselect
              v-model="forms[item.id].approver_user_ids"
              :options="approverOptions"
              mode="multiple"
              :close-on-select="false"
              :searchable="true"
              :disabled="!canEdit"
              placeholder="Selecciona gestores"
            />
            <div class="text-muted small mt-2">
              {{ forms[item.id].approver_user_ids.length }} gestor(es) asignado(s)
            </div>
          </div>
        </template>

        <template #cell(actions)="{ item }">
          <div class="d-flex flex-wrap gap-2">
            <BButton
              v-if="canEdit"
              size="sm"
              variant="primary"
              :disabled="savingId === item.id"
              @click="save(item)"
            >
              {{ savingId === item.id ? "Guardando..." : "Guardar" }}
            </BButton>
            <BButton
              v-if="canEdit"
              size="sm"
              variant="outline-secondary"
              :disabled="savingId === item.id"
              @click="resetRow(item)"
            >
              Restablecer
            </BButton>
            <router-link :to="`/spaces/dependencies/${item.id}`" class="btn btn-sm btn-outline-info">Ver ficha</router-link>
          </div>
        </template>
      </BTable>
    </BCard>
  </Layout>
</template>
