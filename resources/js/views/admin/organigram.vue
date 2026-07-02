<script>
import axios from "axios";
import Swal from "sweetalert2";
import Multiselect from "@vueform/multiselect";
import Layout from "../../layouts/main.vue";

const emptyRelation = () => ({
  relationship_type: "direct_manager",
  related_staff_id: null,
  custom_label: "",
  priority: 1,
  is_primary: true,
  active: true,
  notes: "",
});

export default {
  components: { Layout, Multiselect },
  data() {
    return {
      loading: false,
      loadingCatalogs: false,
      loadingStaffDetail: false,
      saving: false,
      error: null,
      success: null,
      rows: [],
      summary: {},
      pagination: { current_page: 1, last_page: 1, total: 0 },
      filters: {
        search: "",
        active_only: true,
        only_with_relations: false,
      },
      catalogs: {
        staff: [],
        relationship_types: [],
      },
      showModal: false,
      selectedStaff: null,
      relations: [],
      relationForm: emptyRelation(),
    };
  },
  computed: {
    relationshipTypeOptions() {
      return (this.catalogs.relationship_types || []).map((item) => ({
        value: item.value,
        label: item.label,
      }));
    },
    relatedStaffOptions() {
      return (this.catalogs.staff || [])
        .filter((item) => Number(item.id) !== Number(this.selectedStaff?.id || 0))
        .map((item) => ({
          value: item.id,
          label: `${item.full_name}${item.rut ? ` (${item.rut})` : ""}`,
        }));
    },
    selectedRelationType() {
      return (this.catalogs.relationship_types || []).find(
        (item) => item.value === this.relationForm.relationship_type
      );
    },
  },
  mounted() {
    this.loadCatalogs();
    this.loadRows();
  },
  methods: {
    async loadCatalogs() {
      this.loadingCatalogs = true;
      try {
        const response = await axios.get("/api/admin/organigram/catalogs");
        this.catalogs = response.data;
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loadingCatalogs = false;
      }
    },
    async loadRows(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/admin/organigram", {
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
        only_with_relations: false,
      };
      this.loadRows(1);
    },
    relationLabel(item) {
      if (item.relationship_type === "other" && item.custom_label) {
        return item.custom_label;
      }

      return (
        item.relationship_label ||
        (this.catalogs.relationship_types || []).find((option) => option.value === item.relationship_type)?.label ||
        item.relationship_type
      );
    },
    directManager(item) {
      return (item.organigram_relations || [])
        .filter((relation) => relation.active && relation.relationship_type === "direct_manager")
        .sort((left, right) => {
          if (Number(right.is_primary) !== Number(left.is_primary)) {
            return Number(right.is_primary) - Number(left.is_primary);
          }
          if (Number(left.priority || 0) !== Number(right.priority || 0)) {
            return Number(left.priority || 0) - Number(right.priority || 0);
          }
          return Number(left.id || 0) - Number(right.id || 0);
        })[0] || null;
    },
    otherRelations(item) {
      return (item.organigram_relations || []).filter(
        (relation) => relation.active && relation.relationship_type !== "direct_manager"
      );
    },
    async openEdit(item) {
      this.loadingStaffDetail = true;
      this.error = null;
      this.success = null;
      this.showModal = true;
      this.selectedStaff = null;
      this.relations = [];
      this.relationForm = emptyRelation();

      try {
        const response = await axios.get(`/api/admin/organigram/${item.id}`);
        const staff = response.data.data;
        this.selectedStaff = staff;
        this.relations = (staff.organigram_relations || []).map((relation) => ({
          id: relation.id,
          relationship_type: relation.relationship_type,
          related_staff_id: relation.related_staff_id,
          custom_label: relation.custom_label || "",
          priority: relation.priority || 1,
          is_primary: !!relation.is_primary,
          active: !!relation.active,
          notes: relation.notes || "",
          related_staff: relation.related_staff || null,
          relationship_label: relation.relationship_label || null,
        }));
      } catch (error) {
        this.error = this.formatError(error);
        this.showModal = false;
      } finally {
        this.loadingStaffDetail = false;
      }
    },
    closeModal() {
      this.showModal = false;
      this.selectedStaff = null;
      this.relations = [];
      this.relationForm = emptyRelation();
    },
    resetRelationForm() {
      this.relationForm = emptyRelation();
    },
    addRelation() {
      this.error = null;

      if (!this.relationForm.related_staff_id) {
        this.error = "Debes seleccionar el funcionario relacionado.";
        return;
      }

      if (this.relationForm.relationship_type === "other" && !this.relationForm.custom_label.trim()) {
        this.error = "Debes indicar el nombre de la relación.";
        return;
      }

      const duplicate = this.relations.some(
        (item) =>
          item.relationship_type === this.relationForm.relationship_type &&
          Number(item.related_staff_id) === Number(this.relationForm.related_staff_id)
      );

      if (duplicate) {
        this.error = "Esa relación ya está agregada para este funcionario.";
        return;
      }

      if (this.relationForm.is_primary) {
        this.relations = this.relations.map((item) =>
          item.relationship_type === this.relationForm.relationship_type
            ? { ...item, is_primary: false }
            : item
        );
      }

      const relatedStaff =
        (this.catalogs.staff || []).find((item) => Number(item.id) === Number(this.relationForm.related_staff_id)) || null;

      this.relations.push({
        ...emptyRelation(),
        ...this.relationForm,
        custom_label: this.relationForm.relationship_type === "other" ? this.relationForm.custom_label.trim() : "",
        related_staff: relatedStaff,
      });

      this.resetRelationForm();
    },
    removeRelation(index) {
      this.relations.splice(index, 1);
    },
    async save() {
      if (!this.selectedStaff) {
        return;
      }

      this.saving = true;
      this.error = null;
      this.success = null;

      try {
        await axios.put(`/api/admin/organigram/${this.selectedStaff.id}/relations`, {
          relations: this.relations.map((relation) => ({
            relationship_type: relation.relationship_type,
            related_staff_id: relation.related_staff_id,
            custom_label: relation.relationship_type === "other" ? relation.custom_label : null,
            priority: relation.priority || 1,
            is_primary: !!relation.is_primary,
            active: !!relation.active,
            notes: relation.notes || null,
          })),
        });

        this.success = "Organigrama actualizado correctamente.";
        await Swal.fire({
          icon: "success",
          title: "Listo",
          text: this.success,
          timer: 1600,
          showConfirmButton: false,
        });

        this.closeModal();
        await this.loadRows(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
        await Swal.fire({
          icon: "error",
          title: "No se pudo guardar",
          text: this.error,
        });
      } finally {
        this.saving = false;
      }
    },
    formatError(error) {
      const validationErrors = error?.response?.data?.errors;
      if (validationErrors && typeof validationErrors === "object") {
        const firstKey = Object.keys(validationErrors)[0];
        if (firstKey && Array.isArray(validationErrors[firstKey]) && validationErrors[firstKey][0]) {
          return validationErrors[firstKey][0];
        }
      }

      return error?.response?.data?.message || error?.message || "Error desconocido";
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h4 class="mb-0">Organigrama</h4>
        <div class="text-muted">Define jefaturas directas y otras relaciones organizacionales por funcionario.</div>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
    <BAlert v-if="success" variant="success" show class="mb-3">{{ success }}</BAlert>

    <BCard class="mb-3">
      <div class="row g-3 align-items-end">
        <div class="col-md-5">
          <label class="form-label">Buscar funcionario</label>
          <BFormInput
            v-model="filters.search"
            placeholder="Nombre, RUT o correo institucional"
            @keyup.enter="loadRows(1)"
          />
        </div>
        <div class="col-md-3">
          <BFormCheckbox v-model="filters.active_only">Solo funcionarios activos</BFormCheckbox>
        </div>
        <div class="col-md-4">
          <BFormCheckbox v-model="filters.only_with_relations">Solo funcionarios con relaciones definidas</BFormCheckbox>
        </div>
      </div>
      <div class="d-flex gap-2 mt-3">
        <BButton variant="primary" @click="loadRows(1)">Filtrar</BButton>
        <BButton variant="outline-secondary" @click="resetFilters">Limpiar</BButton>
      </div>
    </BCard>

    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <BCard>
          <div class="text-muted small">Funcionarios en listado</div>
          <div class="h2 mb-0">{{ summary.total_staff ?? 0 }}</div>
        </BCard>
      </div>
      <div class="col-md-6">
        <BCard>
          <div class="text-muted small">Funcionarios con relaciones definidas</div>
          <div class="h2 mb-0">{{ summary.with_relations ?? 0 }}</div>
        </BCard>
      </div>
    </div>

    <BCard title="Relaciones por funcionario">
      <div v-if="loading" class="text-muted">Cargando organigrama...</div>
      <div v-else class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>Funcionario</th>
              <th>Jefatura directa</th>
              <th>Otras relaciones</th>
              <th>Resumen</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in rows" :key="item.id">
              <td class="align-top">
                <div class="fw-semibold">{{ item.full_name }}</div>
                <div class="text-muted small">{{ item.cargo?.name || "Sin cargo" }}</div>
                <div class="text-muted small">
                  {{ (item.departments || []).length ? item.departments.map((department) => department.name).join(", ") : "Sin departamentos" }}
                </div>
              </td>
              <td class="align-top">
                <template v-if="directManager(item)">
                  <div class="fw-semibold">{{ directManager(item).related_staff?.full_name || "-" }}</div>
                  <div class="text-muted small">
                    Prioridad {{ directManager(item).priority || 1 }}
                    <span v-if="directManager(item).is_primary">· Principal</span>
                  </div>
                </template>
                <span v-else class="text-muted">Sin definir</span>
              </td>
              <td class="align-top">
                <div v-if="otherRelations(item).length" class="d-flex flex-wrap gap-2">
                  <span
                    v-for="relation in otherRelations(item)"
                    :key="relation.id"
                    class="badge bg-light text-dark border"
                  >
                    {{ relationLabel(relation) }}: {{ relation.related_staff?.full_name || "-" }}
                  </span>
                </div>
                <span v-else class="text-muted">Sin otras relaciones</span>
              </td>
              <td class="align-top">
                <div class="fw-semibold">{{ item.organigram_relations_count || 0 }} relaciones</div>
                <div class="text-muted small">{{ item.active_organigram_relations_count || 0 }} activas</div>
              </td>
              <td class="align-top text-end">
                <BButton size="sm" variant="outline-primary" @click="openEdit(item)">
                  Editar relaciones
                </BButton>
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

    <BModal
      v-model="showModal"
      title="Editar organigrama"
      size="xl"
      hide-footer
      @hidden="closeModal"
    >
      <div v-if="loadingStaffDetail" class="text-muted">Cargando funcionario...</div>
      <template v-else-if="selectedStaff">
        <div class="mb-3">
          <div class="fw-semibold">{{ selectedStaff.full_name }}</div>
          <div class="text-muted small">
            {{ selectedStaff.cargo?.name || "Sin cargo" }}
            <span v-if="(selectedStaff.departments || []).length">
              · {{ selectedStaff.departments.map((department) => department.name).join(", ") }}
            </span>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-xl-4">
            <BCard title="Agregar relación">
              <div class="mb-3">
                <label class="form-label">Tipo de relación</label>
                <Multiselect v-model="relationForm.relationship_type" :options="relationshipTypeOptions" :searchable="true" />
              </div>
              <div class="mb-3" v-if="relationForm.relationship_type === 'other'">
                <label class="form-label">Nombre de la relación</label>
                <BFormInput v-model="relationForm.custom_label" maxlength="255" />
              </div>
              <div class="mb-3">
                <label class="form-label">Funcionario relacionado</label>
                <Multiselect v-model="relationForm.related_staff_id" :options="relatedStaffOptions" :searchable="true" />
              </div>
              <div class="mb-3">
                <label class="form-label">Prioridad</label>
                <BFormInput v-model="relationForm.priority" type="number" min="1" max="999" />
              </div>
              <div class="mb-3">
                <label class="form-label">Observaciones</label>
                <BFormTextarea v-model="relationForm.notes" rows="3" />
              </div>
              <div class="d-flex flex-column gap-2 mb-3">
                <BFormCheckbox v-model="relationForm.is_primary">Marcar como principal</BFormCheckbox>
                <BFormCheckbox v-model="relationForm.active">Activo</BFormCheckbox>
              </div>
              <div class="d-flex gap-2">
                <BButton variant="primary" @click="addRelation">Agregar</BButton>
                <BButton variant="outline-secondary" @click="resetRelationForm">Limpiar</BButton>
              </div>
            </BCard>
          </div>

          <div class="col-xl-8">
            <BCard title="Relaciones actuales">
              <div v-if="!relations.length" class="text-muted">No hay relaciones definidas para este funcionario.</div>
              <div v-else class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Tipo</th>
                      <th>Relacionado con</th>
                      <th>Estado</th>
                      <th>Notas</th>
                      <th class="text-end"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(relation, index) in relations" :key="`${relation.relationship_type}-${relation.related_staff_id}-${index}`">
                      <td>
                        <div class="fw-semibold">{{ relationLabel(relation) }}</div>
                        <div class="text-muted small">Prioridad {{ relation.priority || 1 }}</div>
                      </td>
                      <td>{{ relation.related_staff?.full_name || "-" }}</td>
                      <td>
                        <div class="d-flex flex-wrap gap-1">
                          <span class="badge" :class="relation.is_primary ? 'bg-primary-subtle text-primary' : 'bg-light text-muted border'">
                            {{ relation.is_primary ? "Principal" : "Secundaria" }}
                          </span>
                          <span class="badge" :class="relation.active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'">
                            {{ relation.active ? "Activa" : "Inactiva" }}
                          </span>
                        </div>
                      </td>
                      <td class="small text-muted">{{ relation.notes || "-" }}</td>
                      <td class="text-end">
                        <BButton size="sm" variant="outline-danger" @click="removeRelation(index)">Quitar</BButton>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="d-flex justify-content-end gap-2 mt-3">
                <BButton variant="outline-secondary" @click="closeModal">Cerrar</BButton>
                <BButton variant="primary" :disabled="saving" @click="save">
                  {{ saving ? "Guardando..." : "Guardar organigrama" }}
                </BButton>
              </div>
            </BCard>
          </div>
        </div>
      </template>
    </BModal>
  </Layout>
</template>
