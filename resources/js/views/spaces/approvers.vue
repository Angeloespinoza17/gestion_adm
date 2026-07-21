<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";
import Swal from "sweetalert2";
import "./shared.css";

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
        name: item.staff?.full_name || item.name,
        email: item.email,
        label: `${item.staff?.full_name || item.name}${item.email ? ` · ${item.email}` : ""}`,
      }));
    },
    summaryCards() {
      const assigned = this.dependencies.filter(
        (item) => (this.forms[item.id]?.approver_user_ids || item.approvers || []).length
      ).length;

      return [
        {
          label: "Dependencias",
          value: this.formatInteger(this.dependencies.length),
          detail: "reservables visibles",
          icon: "bx-buildings",
          tone: "blue",
        },
        {
          label: "Con gestores",
          value: this.formatInteger(assigned),
          detail: "tienen aprobadores",
          icon: "bx-user-check",
          tone: "green",
        },
        {
          label: "Funcionarios",
          value: this.formatInteger(this.approverUsers.length),
          detail: "disponibles para aprobar",
          icon: "bx-group",
          tone: "slate",
        },
      ];
    },
    hasActiveFilters() {
      return Boolean(this.filters.search || this.filters.dependency_type_id);
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
    selectedApproverLabel(value) {
      const selected = Array.isArray(value) ? value : [value].filter(Boolean);
      const names = selected
        .map((entry) => {
          if (entry && typeof entry === "object") {
            return entry.name || this.cleanApproverLabel(entry.label);
          }

          const option = this.approverOptions.find((item) => Number(item.value) === Number(entry));
          return option?.name || this.cleanApproverLabel(option?.label);
        })
        .filter(Boolean);

      return names.length ? names.join(", ") : "Selecciona funcionarios";
    },
    cleanApproverLabel(label) {
      return String(label || "").split(" · ")[0].trim();
    },
    resetFilters() {
      this.filters.search = "";
      this.filters.dependency_type_id = null;
      this.load();
    },
    formatInteger(value) {
      return Number(value || 0).toLocaleString("es-CL", {
        maximumFractionDigits: 0,
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
    <div class="spaces-shell">
      <section class="spaces-hero">
        <div class="spaces-hero__body">
          <div class="spaces-eyebrow">Dependencias y reservas</div>
          <h4>Gestores de aprobación</h4>
          <p>Asigna funcionarios responsables de aprobar o rechazar reservas por dependencia.</p>
        </div>
        <div class="spaces-actions">
          <router-link to="/spaces/dependencies" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i>
            <span>Dependencias</span>
          </router-link>
        </div>
      </section>

      <div class="spaces-summary-grid">
        <div
          v-for="card in summaryCards"
          :key="card.label"
          class="spaces-summary-card"
          :class="`spaces-summary-card--${card.tone}`"
        >
          <div class="spaces-summary-icon">
            <i :class="`bx ${card.icon}`"></i>
          </div>
          <div>
            <span>{{ card.label }}</span>
            <strong>{{ card.value }}</strong>
            <small>{{ card.detail }}</small>
          </div>
        </div>
      </div>

      <section class="spaces-panel">
        <div class="spaces-panel-header">
          <div>
            <div class="spaces-eyebrow">Filtros</div>
            <h5 class="spaces-panel-title">Segmentar dependencias</h5>
          </div>
        </div>

        <div class="spaces-filter-grid spaces-filter-grid--wide">
          <label class="spaces-field">
            <span>Buscar dependencia</span>
            <BFormInput v-model="filters.search" placeholder="Nombre, código o ubicación" @keyup.enter="load" />
          </label>
          <label class="spaces-field">
            <span>Tipo</span>
            <Multiselect v-model="filters.dependency_type_id" :options="typeOptions" :searchable="true" />
          </label>
          <div class="spaces-filter-actions">
            <BButton variant="primary" :disabled="loading" @click="load">
              <i class="bx bx-search"></i>
              <span>Buscar</span>
            </BButton>
            <BButton variant="outline-secondary" :disabled="loading || !hasActiveFilters" @click="resetFilters">
              <i class="bx bx-x"></i>
              <span>Limpiar</span>
            </BButton>
          </div>
        </div>
      </section>

      <BAlert v-if="error" variant="danger" show class="mb-0">{{ error }}</BAlert>

      <section class="spaces-panel">
        <div class="spaces-panel-header">
          <div>
            <div class="spaces-eyebrow">Asignación</div>
            <h5 class="spaces-panel-title">Dependencias con aprobación</h5>
          </div>
          <div class="spaces-panel-meta">{{ formatInteger(dependencies.length) }} resultados</div>
        </div>

        <div v-if="loading" class="spaces-empty-state">
          <LoadingState message="Cargando gestores..." compact />
        </div>
        <div v-else class="spaces-table-wrap spaces-approvers-table-wrap">
          <table class="table spaces-data-table spaces-approvers-table">
            <colgroup>
              <col class="spaces-approvers-table__dependency" />
              <col class="spaces-approvers-table__approval" />
              <col class="spaces-approvers-table__managers" />
              <col class="spaces-approvers-table__actions" />
            </colgroup>
            <thead>
              <tr>
                <th>Dependencia</th>
                <th class="text-center">Aprobación</th>
                <th>Gestores</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in dependencies" :key="item.id">
                <td>
                  <div class="spaces-table-title">{{ item.name }}</div>
                  <span class="spaces-table-subtitle">
                    {{ item.type?.name || "Sin tipo" }} · {{ item.code || "Sin código" }}
                  </span>
                  <span class="spaces-table-subtitle">{{ item.location || item.floor_sector || "Sin ubicación" }}</span>
                </td>
                <td class="text-center">
                  <span class="spaces-status-pill spaces-status-pill--warning" title="Aprobación obligatoria">
                    <i class="bx bx-check-shield"></i>
                    <span>Obligatoria</span>
                  </span>
                </td>
                <td>
                  <div v-if="forms[item.id]" class="spaces-approver-picker">
                    <Multiselect
                      v-model="forms[item.id].approver_user_ids"
                      class="spaces-approver-multiselect"
                      :options="approverOptions"
                      mode="multiple"
                      value-prop="value"
                      label="label"
                      track-by="label"
                      :close-on-select="false"
                      :searchable="true"
                      :append-to-body="true"
                      :classes="{ dropdown: 'multiselect-dropdown spaces-approver-dropdown' }"
                      :multiple-label="selectedApproverLabel"
                      :disabled="!canEdit"
                      no-options-text="No hay funcionarios disponibles"
                      no-results-text="Sin resultados"
                      placeholder="Selecciona funcionarios"
                    />
                    <span class="spaces-table-subtitle spaces-approver-count">
                      {{ formatInteger(forms[item.id].approver_user_ids.length) }} funcionario(s) asignado(s)
                    </span>
                  </div>
                </td>
                <td>
                  <div class="spaces-row-actions spaces-approver-actions">
                    <BButton
                      v-if="canEdit"
                      size="sm"
                      variant="primary"
                      :disabled="savingId === item.id"
                      title="Guardar cambios"
                      aria-label="Guardar cambios"
                      @click="save(item)"
                    >
                      <i :class="savingId === item.id ? 'bx bx-loader-alt bx-spin' : 'bx bx-save'"></i>
                      <span>{{ savingId === item.id ? "Guardando..." : "Guardar" }}</span>
                    </BButton>
                    <BButton
                      v-if="canEdit"
                      size="sm"
                      variant="outline-secondary"
                      :disabled="savingId === item.id"
                      title="Restablecer"
                      aria-label="Restablecer"
                      @click="resetRow(item)"
                    >
                      <i class="bx bx-undo"></i>
                      <span>Restablecer</span>
                    </BButton>
                    <router-link
                      :to="`/spaces/dependencies/${item.id}`"
                      class="btn btn-sm btn-outline-info"
                      title="Ver ficha"
                      aria-label="Ver ficha"
                    >
                      <i class="bx bx-show"></i>
                      <span>Ficha</span>
                    </router-link>
                  </div>
                </td>
              </tr>
              <tr v-if="dependencies.length === 0">
                <td colspan="4">
                  <div class="spaces-empty-state">
                    <i class="bx bx-user-check"></i>
                    <strong>No hay dependencias para mostrar</strong>
                    <span>Ajusta los filtros para revisar asignaciones de gestores.</span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </Layout>
</template>

<style scoped>
.spaces-approvers-table {
  min-width: 64rem;
}

.spaces-approvers-table-wrap {
  overflow-x: auto;
  overflow-y: visible;
}

.spaces-approvers-table__dependency {
  width: 28%;
}

.spaces-approvers-table__approval {
  width: 10%;
}

.spaces-approvers-table__managers {
  width: 48%;
}

.spaces-approvers-table__actions {
  width: 14%;
}

.spaces-approvers-table tbody td {
  padding: 0.82rem 0.85rem;
}

.spaces-approvers-table tbody td:nth-child(3) {
  padding-right: 1.15rem;
}

.spaces-approvers-table tbody td:nth-child(4) {
  padding-left: 0.55rem;
}

.spaces-approvers-table .spaces-status-pill {
  gap: 0.22rem;
  padding-inline: 0.48rem;
}

.spaces-approver-picker {
  display: grid;
  gap: 0.32rem;
  position: relative;
  z-index: 2;
  min-width: 0;
  max-width: 100%;
}

.spaces-approver-picker :deep(.spaces-approver-multiselect.multiselect) {
  --ms-radius: 8px;
  --ms-border-color: #dce5f4;
  --ms-border-width: 1px;
  --ms-bg: #ffffff;
  --ms-font-size: 0.82rem;
  --ms-line-height: 1.35;
  --ms-option-bg-selected: #556ee6;
  --ms-option-color-selected: #ffffff;
  --ms-option-bg-pointed: #f1f5ff;
  --ms-option-color-pointed: #3152c9;
  width: 100%;
  min-height: 2.25rem;
  border: 1px solid #dce5f4;
  border-radius: 8px;
  background: #ffffff;
  box-shadow: 0 0.2rem 0.7rem rgba(15, 23, 42, 0.04);
  font-size: 0.82rem;
}

.spaces-approver-picker :deep(.spaces-approver-multiselect.multiselect.is-open) {
  z-index: 120;
}

.spaces-approver-picker :deep(.multiselect-wrapper) {
  min-height: 2.25rem;
  border: 0;
  border-radius: 8px;
  background: transparent;
}

.spaces-approver-picker :deep(.multiselect-placeholder),
.spaces-approver-picker :deep(.multiselect-single-label),
.spaces-approver-picker :deep(.multiselect-multiple-label) {
  display: flex;
  align-items: center;
  min-height: 2.25rem;
  padding-right: 2.65rem;
  padding-left: 0.75rem;
  color: #64748b;
  font-weight: 500;
}

.spaces-approver-picker :deep(.multiselect-multiple-label) {
  overflow: hidden;
  max-width: calc(100% - 3.2rem);
  white-space: nowrap;
  text-overflow: ellipsis;
}

.spaces-approver-picker :deep(.multiselect-tags) {
  gap: 0.25rem;
  min-height: 2.25rem;
  padding: 0.25rem 2.45rem 0.25rem 0.5rem;
}

.spaces-approver-picker :deep(.multiselect-tag) {
  max-width: 100%;
  margin: 0;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 500;
}

.spaces-approver-picker :deep(.multiselect-caret) {
  margin-right: 0.55rem;
}

.spaces-approver-picker :deep(.multiselect-dropdown) {
  top: calc(100% + 0.28rem);
  z-index: 50;
  border: 1px solid #dbe7ff;
  border-radius: 8px;
  background: #ffffff;
  box-shadow: 0 0.85rem 2rem rgba(15, 23, 42, 0.14);
}

.spaces-approver-picker :deep(.multiselect-option) {
  min-height: 2rem;
  padding: 0.45rem 0.7rem;
  border-radius: 6px;
  font-size: 0.8rem;
}

:global(.spaces-approver-dropdown.multiselect-dropdown) {
  z-index: 2055;
  max-height: min(20rem, 46vh);
  border: 1px solid #dbe7ff;
  border-radius: 8px;
  background: #ffffff;
  box-shadow: 0 0.9rem 2rem rgba(15, 23, 42, 0.18);
  --ms-option-bg-selected: #556ee6;
  --ms-option-color-selected: #ffffff;
  --ms-option-bg-pointed: #eef3ff;
  --ms-option-color-pointed: #2f4fca;
  --ms-option-font-size: 0.82rem;
  --ms-option-line-height: 1.35;
}

:global(.spaces-approver-dropdown .multiselect-option) {
  min-height: 2.15rem;
  padding: 0.5rem 0.75rem;
}

:global(.spaces-approver-dropdown .multiselect-no-options),
:global(.spaces-approver-dropdown .multiselect-no-results) {
  padding: 0.6rem 0.75rem;
  color: #64748b;
  font-size: 0.82rem;
}

.spaces-approver-count {
  margin-top: 0;
  font-size: 0.72rem;
}

.spaces-approver-actions {
  justify-content: flex-end;
  gap: 0.38rem;
}

.spaces-approver-actions .btn {
  width: 2.25rem;
  min-width: 2.25rem;
  height: 2.25rem;
  min-height: 2.25rem;
  font-size: 1.05rem;
}

.spaces-approver-actions .btn-primary {
  color: #fff;
  background: #556ee6;
  border-color: #556ee6;
}

.spaces-approver-actions .btn-primary:disabled {
  color: #fff;
  background: #7b8df0;
  border-color: #7b8df0;
}

@media (max-width: 1199.98px) {
  .spaces-approvers-table {
    min-width: 58rem;
  }

  .spaces-approvers-table__dependency {
    width: 30%;
  }

  .spaces-approvers-table__approval {
    width: 9%;
  }

  .spaces-approvers-table__managers {
    width: 46%;
  }

  .spaces-approvers-table__actions {
    width: 15%;
  }

  .spaces-approvers-table .spaces-status-pill span {
    display: none;
  }
}
</style>
