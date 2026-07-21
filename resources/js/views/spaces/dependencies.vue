<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";
import Swal from "sweetalert2";

const emptyForm = () => ({
  id: null,
  dependency_type_id: null,
  code: "",
  name: "",
  description: "",
  location: "",
  floor_sector: "",
  capacity_max: "",
  available_equipment: "",
  availability_status: "disponible",
  responsible_staff_id: null,
  observations: "",
  notes: "",
  calendar_color: "#34c38f",
  is_reservable: false,
  is_inventory_auditable: true,
  is_maintenance_location: true,
  requires_approval: false,
  approver_user_ids: [],
  active: true,
  distribution: "",
  sector: "",
  zone: "",
  usage: "",
  distribution_code: "",
  floor_code: "",
  dependency_code: "",
  numbering: "",
});

export default {
  components: { Layout, LoadingState, Multiselect },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      dependencies: [],
      pagination: { current_page: 1, last_page: 1, total: 0 },
      catalogs: {
        dependency_types: [],
        responsible_staff: [],
        approver_users: [],
        statuses: [],
        total: 0,
        active: 0,
      },
      filters: {
        search: "",
        dependency_type_id: null,
        responsible_staff_id: null,
        availability_status: null,
        active: null,
      },
      form: emptyForm(),
      imageFile: null,
      showModal: false,
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
    canSaveForm() {
      return Boolean(String(this.form.name || "").trim() && String(this.form.code || "").trim());
    },
    imageFileName() {
      return this.imageFile?.name || "Ningún archivo seleccionado";
    },
    permissions() {
      return JSON.parse(localStorage.getItem("permissions") || "[]");
    },
    canCreate() {
      return this.permissions.includes("crear_dependencias");
    },
    canEdit() {
      return this.permissions.includes("editar_dependencias");
    },
    canDelete() {
      return this.permissions.includes("eliminar_dependencias");
    },
    canManageTypes() {
      return this.canCreate || this.canEdit || this.canDelete;
    },
    typeOptions() {
      return [{ value: null, label: "Sin tipo" }].concat(
        (this.catalogs.dependency_types || []).map((item) => ({
          value: item.id,
          label: item.name,
        }))
      );
    },
    staffOptions() {
      return [{ value: null, label: "Sin responsable" }].concat(
        (this.catalogs.responsible_staff || []).map((item) => ({
          value: item.id,
          label: `${item.full_name}${item.rut ? ` (${item.rut})` : ""}`,
        }))
      );
    },
    approverOptions() {
      return (this.catalogs.approver_users || []).map((item) => ({
        value: item.id,
        label: `${item.staff?.full_name || item.name}${item.email ? ` · ${item.email}` : ""}`,
      }));
    },
    statusOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.statuses || []).map((item) => ({
          value: item.value,
          label: item.label,
        }))
      );
    },
    activeOptions() {
      return [
        { value: null, label: "Todas" },
        { value: "1", label: "Activas" },
        { value: "0", label: "Inactivas" },
      ];
    },
    summaryCards() {
      const active = this.catalogs.active ?? this.dependencies.filter((item) => item.active).length;
      const total = this.catalogs.total || this.pagination.total || 0;
      const visibleReservable = this.dependencies.filter((item) => item.is_reservable).length;
      const visibleReservations = this.dependencies.reduce(
        (total, item) => total + Number(item.upcoming_reservations_count || 0),
        0
      );

      return [
        {
          label: "Catálogo",
          value: this.formatInteger(total),
          detail: "dependencias registradas",
          icon: "bx-buildings",
          tone: "blue",
        },
        {
          label: "Activas",
          value: this.formatInteger(active),
          detail: "habilitadas para operar",
          icon: "bx-check-shield",
          tone: "green",
        },
        {
          label: "Reservables",
          value: this.formatInteger(visibleReservable),
          detail: "en la página actual",
          icon: "bx-calendar-event",
          tone: "amber",
        },
        {
          label: "Próximas reservas",
          value: this.formatInteger(visibleReservations),
          detail: "en resultados visibles",
          icon: "bx-time-five",
          tone: "slate",
        },
      ];
    },
    hasActiveFilters() {
      return Boolean(
        this.filters.search ||
        this.filters.dependency_type_id ||
        this.filters.responsible_staff_id ||
        this.filters.availability_status ||
        this.filters.active !== null
      );
    },
    paginationRange() {
      if (!this.pagination.total) {
        return "Sin resultados";
      }

      const perPage = Number(this.pagination.per_page || 15);
      const from = (Number(this.pagination.current_page || 1) - 1) * perPage + 1;
      const to = Math.min(from + perPage - 1, Number(this.pagination.total || 0));

      return `${this.formatInteger(from)}-${this.formatInteger(to)} de ${this.formatInteger(this.pagination.total)}`;
    },
  },
  watch: {
    "form.is_reservable"(value) {
      if (!value) {
        this.form.requires_approval = false;
        this.form.approver_user_ids = [];
      }
    },
  },
  mounted() {
    this.loadCatalogs();
    this.load();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/spaces/dependencies/catalogs");
      this.catalogs = response.data;
    },
    async load(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/spaces/dependencies", {
          params: {
            page,
            search: this.filters.search || null,
            dependency_type_id: this.filters.dependency_type_id,
            responsible_staff_id: this.filters.responsible_staff_id,
            availability_status: this.filters.availability_status,
            active: this.filters.active,
          },
        });
        this.dependencies = response.data.data;
        this.pagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          total: response.data.total,
          per_page: response.data.per_page,
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    applyFilters() {
      this.load(1);
    },
    resetFilters() {
      this.filters = {
        search: "",
        dependency_type_id: null,
        responsible_staff_id: null,
        availability_status: null,
        active: null,
      };
      this.load(1);
    },
    openCreate() {
      if (!this.canCreate) {
        return;
      }
      this.form = emptyForm();
      this.imageFile = null;
      this.showModal = true;
    },
    async openEdit(item) {
      if (!this.canEdit) {
        return;
      }
      const response = await axios.get(`/api/spaces/dependencies/${item.id}`);
      const record = response.data.data || item;
      this.form = {
        ...emptyForm(),
        id: record.id,
        dependency_type_id: record.dependency_type_id ?? null,
        code: record.code || "",
        name: record.name || "",
        description: record.description || "",
        location: record.location || "",
        floor_sector: record.floor_sector || "",
        capacity_max: record.capacity_max || "",
        available_equipment: record.available_equipment || "",
        availability_status: record.availability_status || "disponible",
        responsible_staff_id: record.responsible_staff_id ?? null,
        observations: record.observations || "",
        notes: record.notes || "",
        calendar_color: record.calendar_color || "#34c38f",
        is_reservable: Boolean(record.is_reservable),
        is_inventory_auditable: record.is_inventory_auditable !== false,
        is_maintenance_location: record.is_maintenance_location !== false,
        requires_approval: Boolean(record.requires_approval),
        approver_user_ids: (record.approvers || []).map((approver) => approver.id),
        active: Boolean(record.active),
        distribution: record.distribution || "",
        sector: record.sector || "",
        zone: record.zone || "",
        usage: record.usage || "",
        distribution_code: record.distribution_code || "",
        floor_code: record.floor_code || "",
        dependency_code: record.dependency_code || "",
        numbering: record.numbering || "",
      };
      this.imageFile = null;
      this.showModal = true;
    },
    onImage(event) {
      this.imageFile = event?.target?.files?.[0] || null;
    },
    suggestCode() {
      const base = this.slugCode(
        this.form.dependency_code ||
        this.form.name ||
        this.form.usage ||
        "DEP"
      ).slice(0, 6) || "DEP";
      const floor = this.slugCode(this.form.floor_code || this.form.floor_sector || this.form.sector).slice(0, 3);
      const distribution = this.slugCode(this.form.distribution_code || this.form.location || this.form.distribution).slice(0, 3);
      const number = this.form.numbering
        ? String(this.form.numbering).padStart(2, "0")
        : String((this.pagination.total || 0) + 1).padStart(3, "0");

      this.form.code = [distribution, floor, `${base}${number}`].filter(Boolean).join("-");
    },
    slugCode(value) {
      return String(value || "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/[^a-zA-Z0-9]+/g, "")
        .toUpperCase();
    },
    buildFormData() {
      const fd = new FormData();
      const payload = {
        ...this.form,
        requires_approval: this.form.is_reservable ? this.form.requires_approval : false,
        approver_user_ids: this.form.is_reservable ? this.form.approver_user_ids : [],
      };

      Object.entries(payload).forEach(([key, value]) => {
        if (Array.isArray(value)) {
          value.forEach((entry) => fd.append(`${key}[]`, entry));
          return;
        }
        if (typeof value === "boolean") {
          fd.append(key, value ? "1" : "0");
          return;
        }
        fd.append(key, value ?? "");
      });
      if (this.imageFile) {
        fd.append("image", this.imageFile);
      }
      return fd;
    },
    async save() {
      if (this.isEditing ? !this.canEdit : !this.canCreate) {
        return;
      }
      if (!this.canSaveForm) {
        this.error = "Completa el nombre y el código antes de guardar.";
        return;
      }
      this.saving = true;
      this.error = null;
      try {
        const fd = this.buildFormData();
        if (this.isEditing) {
          fd.append("_method", "PUT");
          await axios.post(`/api/spaces/dependencies/${this.form.id}`, fd);
        } else {
          await axios.post("/api/spaces/dependencies", fd);
        }
        this.showModal = false;
        this.load(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async remove(item) {
      if (!this.canDelete) {
        return;
      }
      const result = await Swal.fire({
        title: "Eliminar dependencia",
        text: `Se eliminará ${item.name}.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
      });
      if (!result.isConfirmed) {
        return;
      }
      try {
        await axios.delete(`/api/spaces/dependencies/${item.id}`);
        this.load(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    statusLabel(status) {
      return (this.catalogs.statuses || []).find((item) => item.value === status)?.label || status;
    },
    statusClass(status) {
      return `spaces-status-pill--${status || "secondary"}`;
    },
    operationalBadges(item) {
      return [
        {
          label: item.is_reservable ? "Reservable" : "No reservable",
          active: item.is_reservable,
          icon: "bx-calendar-check",
        },
        {
          label: "Inventario",
          active: item.is_inventory_auditable,
          icon: "bx-package",
        },
        {
          label: "Mantención",
          active: item.is_maintenance_location,
          icon: "bx-wrench",
        },
      ];
    },
    locationMain(item) {
      return item.location || item.distribution || item.zone || "Sin ubicación";
    },
    locationDetail(item) {
      return [item.floor_sector, item.sector, item.usage].filter(Boolean).join(" · ") || "Sin sector informado";
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
    <div class="spaces-dependencies-page">
      <section class="spaces-dependencies-header">
        <div>
          <div class="spaces-dependencies-eyebrow">Dependencias y reservas</div>
          <h4>Catálogo de dependencias</h4>
          <p>Salas, oficinas, recintos y espacios físicos disponibles para operación, mantención, inventario y reservas.</p>
        </div>
        <div class="spaces-dependencies-actions">
          <router-link v-if="canManageTypes" to="/spaces/dependency-types" class="btn btn-outline-secondary">
            <i class="bx bx-category"></i>
            <span>Tipos</span>
          </router-link>
          <router-link v-if="canEdit" to="/spaces/approvers" class="btn btn-outline-secondary">
            <i class="bx bx-user-check"></i>
            <span>Gestores</span>
          </router-link>
          <BButton v-if="canCreate" variant="primary" @click="openCreate">
            <i class="bx bx-plus"></i>
            <span>Nuevo espacio</span>
          </BButton>
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

      <section class="spaces-dependencies-panel spaces-filter-panel">
        <div class="spaces-panel-header">
          <div>
            <div class="spaces-dependencies-eyebrow">Filtros</div>
            <h5>Segmentar catálogo</h5>
          </div>
          <span v-if="hasActiveFilters" class="spaces-filter-state">Filtros activos</span>
        </div>

        <div class="spaces-filter-grid">
          <label class="spaces-filter-field spaces-filter-field--search">
            <span>Buscar</span>
            <div class="input-group">
              <span class="input-group-text"><i class="bx bx-search"></i></span>
              <BFormInput
                v-model="filters.search"
                placeholder="Nombre, código, ubicación..."
                @keyup.enter="applyFilters"
              />
            </div>
          </label>
          <label class="spaces-filter-field">
            <span>Tipo</span>
            <Multiselect v-model="filters.dependency_type_id" :options="typeOptions" :searchable="true" />
          </label>
          <label class="spaces-filter-field">
            <span>Responsable</span>
            <Multiselect v-model="filters.responsible_staff_id" :options="staffOptions" :searchable="true" />
          </label>
          <label class="spaces-filter-field">
            <span>Estado</span>
            <Multiselect v-model="filters.availability_status" :options="statusOptions" :searchable="false" />
          </label>
          <label class="spaces-filter-field">
            <span>Vigencia</span>
            <Multiselect v-model="filters.active" :options="activeOptions" :searchable="false" />
          </label>
          <div class="spaces-filter-actions">
            <BButton variant="primary" :disabled="loading" @click="applyFilters">
              <i class="bx bx-filter-alt"></i>
              <span>Aplicar</span>
            </BButton>
            <BButton variant="outline-secondary" :disabled="loading || !hasActiveFilters" @click="resetFilters">
              <i class="bx bx-x"></i>
              <span>Limpiar</span>
            </BButton>
          </div>
        </div>
      </section>

      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

      <section class="spaces-dependencies-panel">
        <div class="spaces-panel-header">
          <div>
            <div class="spaces-dependencies-eyebrow">Listado</div>
            <h5>Dependencias registradas</h5>
          </div>
          <div class="spaces-panel-meta">
            <span>{{ paginationRange }}</span>
            <BButton
              variant="outline-secondary"
              class="spaces-refresh-button"
              :disabled="loading"
              @click="load(pagination.current_page)"
            >
              <i class="bx bx-refresh"></i>
              <span>{{ loading ? "Actualizando..." : "Actualizar" }}</span>
            </BButton>
          </div>
        </div>

        <div v-if="loading && dependencies.length === 0" class="spaces-loading-wrap">
          <LoadingState message="Cargando dependencias..." compact />
        </div>
        <div v-else class="table-responsive spaces-table-wrap">
          <table class="table spaces-dependencies-table">
            <thead>
              <tr>
                <th>Dependencia</th>
                <th>Ubicación y uso</th>
                <th>Uso operativo</th>
                <th>Estado</th>
                <th>Reservas</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in dependencies" :key="item.id">
                <td>
                  <div class="spaces-dependency-cell">
                    <img
                      v-if="item.image_url"
                      :src="item.image_url"
                      alt="Dependencia"
                      class="spaces-dependency-image"
                    />
                    <div class="spaces-dependency-copy">
                      <span class="spaces-dependency-name">{{ item.name }}</span>
                      <span class="spaces-code-pill">{{ item.code || "Sin código" }}</span>
                      <small v-if="!item.active" class="spaces-inactive-text">Inactiva</small>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="spaces-location-main">{{ locationMain(item) }}</div>
                  <small>{{ locationDetail(item) }}</small>
                  <small class="spaces-capacity-text">
                    {{ item.capacity_max ? `${formatInteger(item.capacity_max)} personas` : "Sin aforo" }}
                  </small>
                </td>
                <td>
                  <div class="spaces-operational-stack">
                    <span
                      v-for="badge in operationalBadges(item)"
                      :key="`${item.id}-${badge.label}`"
                      class="spaces-operational-pill"
                      :class="{ 'spaces-operational-pill--off': !badge.active }"
                    >
                      <i :class="`bx ${badge.icon}`"></i>
                      {{ badge.label }}
                    </span>
                  </div>
                </td>
                <td>
                  <span class="spaces-status-pill" :class="statusClass(item.availability_status)">
                    {{ statusLabel(item.availability_status) }}
                  </span>
                  <small class="spaces-responsible-text">{{ item.responsible_staff?.full_name || "Sin responsable" }}</small>
                </td>
                <td>
                  <div class="spaces-reservation-count">
                    <span class="spaces-reservation-value">
                      {{ item.is_reservable ? formatInteger(item.upcoming_reservations_count) : "-" }}
                    </span>
                    <span>{{ item.is_reservable ? "próximas" : "no reservable" }}</span>
                  </div>
                </td>
                <td>
                  <div class="spaces-row-actions">
                    <router-link
                      :to="`/spaces/dependencies/${item.id}`"
                      class="btn btn-sm btn-outline-primary"
                      title="Ver ficha"
                      aria-label="Ver ficha"
                    >
                      <i class="bx bx-show"></i>
                      <span>Ver</span>
                    </router-link>
                    <BButton
                      v-if="canEdit"
                      size="sm"
                      variant="outline-info"
                      title="Editar"
                      aria-label="Editar dependencia"
                      @click="openEdit(item)"
                    >
                      <i class="bx bx-edit"></i>
                      <span>Editar</span>
                    </BButton>
                    <BButton
                      v-if="canDelete"
                      size="sm"
                      variant="outline-danger"
                      title="Eliminar"
                      aria-label="Eliminar dependencia"
                      @click="remove(item)"
                    >
                      <i class="bx bx-trash"></i>
                      <span>Eliminar</span>
                    </BButton>
                  </div>
                </td>
              </tr>
              <tr v-if="dependencies.length === 0">
                <td colspan="6">
                  <div class="spaces-empty-state">
                    <i class="bx bx-buildings"></i>
                    <strong>No hay dependencias para mostrar</strong>
                    <span>Revisa los filtros aplicados o registra una nueva dependencia.</span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="pagination.total > (pagination.per_page || 15)" class="spaces-pagination-row">
          <BPagination
            v-model="pagination.current_page"
            :per-page="pagination.per_page || 15"
            :total-rows="pagination.total"
            @update:model-value="load"
          />
        </div>
      </section>
    </div>

    <BModal
      v-model="showModal"
      size="xl"
      hide-header
      hide-footer
      body-class="p-0"
      content-class="dependency-form-modal"
    >
      <form class="dependency-form" @submit.prevent="save">
        <header class="dependency-form__header">
          <div class="dependency-form__heading">
            <span class="dependency-form__icon"><i class="bx bx-buildings"></i></span>
            <div>
              <span class="spaces-dependencies-eyebrow">Catálogo de espacios</span>
              <h4>{{ isEditing ? "Editar espacio" : "Nuevo espacio" }}</h4>
              <p>Completa los datos y define dónde estará disponible.</p>
            </div>
          </div>
          <button type="button" class="dependency-form__close" aria-label="Cerrar" @click="showModal = false"><i class="bx bx-x"></i></button>
        </header>

        <div class="dependency-form__body">
          <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
          <section class="dependency-form__section">
            <div class="dependency-form__section-title"><span>1</span><div><h5>Información principal</h5><p>Datos con los que se identificará este espacio.</p></div></div>
            <div class="row g-3">
              <div class="col-lg-7"><label class="form-label">Nombre <em>*</em></label><BFormInput v-model.trim="form.name" placeholder="Ej: Sala de reuniones principal" autofocus /></div>
              <div class="col-lg-5"><label class="form-label">Tipo de espacio</label><Multiselect v-model="form.dependency_type_id" :options="typeOptions" :searchable="true" placeholder="Selecciona un tipo" /></div>
              <div class="col-lg-5">
                <label class="form-label">Código <em>*</em></label>
                <div class="input-group"><BFormInput v-model.trim="form.code" placeholder="Ej: AD1-P1-SALA01" /><BButton variant="outline-primary" type="button" @click="suggestCode"><i class="bx bx-magic-wand"></i> Sugerir</BButton></div>
                <small class="form-hint">Identificador único para búsquedas y reportes.</small>
              </div>
              <div class="col-lg-4"><label class="form-label">Estado</label><Multiselect v-model="form.availability_status" :options="catalogs.statuses || []" value-prop="value" label="label" :searchable="false" /></div>
              <div class="col-lg-3"><label class="form-label">Capacidad</label><div class="input-group"><BFormInput v-model="form.capacity_max" type="number" min="0" placeholder="0" /><span class="input-group-text">personas</span></div></div>
            </div>
          </section>

          <section class="dependency-form__section">
            <div class="dependency-form__section-title"><span>2</span><div><h5>Ubicación y responsable</h5><p>Indica dónde está y quién lo administra.</p></div></div>
            <div class="row g-3">
              <div class="col-lg-6"><label class="form-label">Ubicación</label><BFormInput v-model="form.location" placeholder="Ej: Edificio administrativo" /></div>
              <div class="col-lg-3"><label class="form-label">Piso o sector</label><BFormInput v-model="form.floor_sector" placeholder="Ej: Segundo piso" /></div>
              <div class="col-lg-3"><label class="form-label">Responsable</label><Multiselect v-model="form.responsible_staff_id" :options="staffOptions" :searchable="true" placeholder="Sin responsable" /></div>
            </div>
            <details class="dependency-form__advanced">
              <summary><i class="bx bx-code-alt"></i> Codificación interna <span>Opcional</span></summary>
              <div class="row g-3 pt-3">
                <div class="col-sm-6 col-lg-3"><label class="form-label">Distribución</label><BFormInput v-model="form.distribution_code" placeholder="AD1" /></div>
                <div class="col-sm-6 col-lg-3"><label class="form-label">Piso</label><BFormInput v-model="form.floor_code" placeholder="P1" /></div>
                <div class="col-sm-6 col-lg-3"><label class="form-label">Dependencia</label><BFormInput v-model="form.dependency_code" placeholder="SALA" /></div>
                <div class="col-sm-6 col-lg-3"><label class="form-label">Número</label><BFormInput v-model="form.numbering" type="number" min="0" placeholder="01" /></div>
              </div>
            </details>
          </section>

          <section class="dependency-form__section">
            <div class="dependency-form__section-title"><span>3</span><div><h5>Disponibilidad en módulos</h5><p>Elige en qué procesos se podrá utilizar.</p></div></div>
            <div class="dependency-form__switch-grid">
              <label class="dependency-form__switch-card" :class="{ 'is-enabled': form.is_reservable }"><i class="bx bx-calendar-check"></i><span><strong>Reservas</strong><small>Calendario y solicitudes.</small></span><BFormCheckbox v-model="form.is_reservable" switch /></label>
              <label class="dependency-form__switch-card" :class="{ 'is-enabled': form.is_inventory_auditable }"><i class="bx bx-package"></i><span><strong>Inventario</strong><small>Revisión y auditoría.</small></span><BFormCheckbox v-model="form.is_inventory_auditable" switch /></label>
              <label class="dependency-form__switch-card" :class="{ 'is-enabled': form.is_maintenance_location }"><i class="bx bx-wrench"></i><span><strong>Mantención</strong><small>Ubicación física.</small></span><BFormCheckbox v-model="form.is_maintenance_location" switch /></label>
              <label class="dependency-form__switch-card" :class="{ 'is-enabled': form.active }"><i class="bx bx-power-off"></i><span><strong>Espacio activo</strong><small>Habilitado para operar.</small></span><BFormCheckbox v-model="form.active" switch /></label>
            </div>
            <div v-if="form.is_reservable" class="dependency-form__reservation-settings">
              <div class="row g-3 align-items-end">
                <div class="col-lg-3"><label class="form-label">Color en calendario</label><div class="dependency-form__color-control"><input v-model="form.calendar_color" type="color" /><code>{{ form.calendar_color }}</code></div></div>
                <div class="col-lg-9"><label class="dependency-form__approval-toggle"><BFormCheckbox v-model="form.requires_approval" switch /><span><strong>Solicitar aprobación</strong><small>Las reservas deberán ser revisadas antes de confirmarse.</small></span></label></div>
                <div v-if="form.requires_approval" class="col-12"><label class="form-label">Gestores que pueden aprobar</label><Multiselect v-model="form.approver_user_ids" :options="approverOptions" mode="multiple" :close-on-select="false" :searchable="true" placeholder="Selecciona uno o más gestores" /></div>
              </div>
            </div>
          </section>

          <section class="dependency-form__section">
            <div class="dependency-form__section-title"><span>4</span><div><h5>Detalles adicionales</h5><p>Información útil para quienes utilizan el espacio.</p></div></div>
            <div class="row g-3">
              <div class="col-lg-6"><label class="form-label">Descripción</label><BFormTextarea v-model="form.description" rows="3" placeholder="Propósito y características del espacio" /></div>
              <div class="col-lg-6"><label class="form-label">Equipamiento disponible</label><BFormTextarea v-model="form.available_equipment" rows="3" placeholder="Ej: Proyector, pizarra, 20 sillas" /></div>
              <div class="col-lg-7"><label class="form-label">Observaciones internas</label><BFormTextarea v-model="form.observations" rows="2" placeholder="Notas para el equipo administrativo" /></div>
              <div class="col-lg-5"><label class="form-label">Imagen del espacio</label><label class="dependency-form__file"><input type="file" accept="image/*" @change="onImage" /><i class="bx bx-image-add"></i><span><strong>Seleccionar imagen</strong><small>{{ imageFileName }}</small></span></label></div>
            </div>
          </section>
        </div>

        <footer class="dependency-form__footer">
          <span><i class="bx bx-info-circle"></i> Los campos con * son obligatorios.</span>
          <div><BButton variant="light" type="button" :disabled="saving" @click="showModal = false">Cancelar</BButton><BButton variant="primary" type="submit" :disabled="saving || !canSaveForm || (isEditing ? !canEdit : !canCreate)"><i :class="saving ? 'bx bx-loader-alt bx-spin' : 'bx bx-save'"></i>{{ saving ? "Guardando..." : (isEditing ? "Guardar cambios" : "Crear espacio") }}</BButton></div>
        </footer>
      </form>
    </BModal>
  </Layout>
</template>

<style scoped>
.spaces-dependencies-page {
  display: grid;
  gap: 1rem;
  max-width: 100%;
  min-width: 0;
  overflow-x: hidden;
}

.spaces-dependencies-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
  padding: 1rem;
  border: 1px solid #e1e8f5;
  border-radius: 8px;
  background: #ffffff;
  box-shadow: 0 10px 28px rgba(31, 41, 55, 0.05);
}

.spaces-dependencies-header > div:first-child {
  flex: 1 1 32rem;
  min-width: 0;
}

.spaces-dependencies-header h4,
.spaces-panel-header h5 {
  margin: 0;
  color: #334155;
  font-weight: 700;
  line-height: 1.2;
}

.spaces-dependencies-header p {
  max-width: 48rem;
  margin: 0.35rem 0 0;
  color: #64748b;
  font-size: 0.9rem;
  line-height: 1.45;
}

.spaces-dependencies-eyebrow {
  color: #556ee6;
  font-size: 0.74rem;
  font-weight: 700;
  line-height: 1.2;
  text-transform: uppercase;
}

.spaces-dependencies-actions,
.spaces-panel-meta,
.spaces-filter-actions,
.spaces-row-actions,
.spaces-refresh-button {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.spaces-dependencies-actions {
  flex-wrap: wrap;
  justify-content: flex-end;
  flex: 0 1 auto;
  max-width: 100%;
}

.spaces-dependencies-actions .btn,
.spaces-filter-actions .btn,
.spaces-row-actions .btn,
.spaces-refresh-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  min-height: 2.3rem;
  border-radius: 6px;
  font-size: 0.82rem;
  font-weight: 650;
  line-height: 1;
  max-width: 100%;
  white-space: nowrap;
}

.spaces-dependencies-actions .btn span,
.spaces-refresh-button span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
}

.spaces-summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
  gap: 0.85rem;
}

.spaces-summary-card,
.spaces-dependencies-panel {
  border: 1px solid #e1e8f5;
  border-radius: 8px;
  background: #ffffff;
  box-shadow: 0 10px 28px rgba(31, 41, 55, 0.05);
}

.spaces-summary-card {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  min-width: 0;
  min-height: 6.25rem;
  padding: 0.95rem;
}

.spaces-summary-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 2.5rem;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 8px;
  font-size: 1.35rem;
}

.spaces-summary-card span,
.spaces-filter-field > span {
  display: block;
  color: #64748b;
  font-size: 0.76rem;
  font-weight: 700;
  line-height: 1.2;
}

.spaces-summary-card strong {
  display: block;
  margin-top: 0.2rem;
  color: #334155;
  font-size: 1.45rem;
  line-height: 1;
}

.spaces-summary-card small {
  display: block;
  margin-top: 0.28rem;
  color: #74788d;
  font-size: 0.76rem;
  font-weight: 500;
  line-height: 1.25;
}

.spaces-summary-card--blue .spaces-summary-icon {
  color: #1d4ed8;
  background: #eff6ff;
}

.spaces-summary-card--green .spaces-summary-icon {
  color: #047857;
  background: #ecfdf5;
}

.spaces-summary-card--amber .spaces-summary-icon {
  color: #b45309;
  background: #fffbeb;
}

.spaces-summary-card--slate .spaces-summary-icon {
  color: #475569;
  background: #f8fafc;
}

.spaces-dependencies-panel {
  min-width: 0;
  padding: 1rem;
}

.spaces-panel-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1rem;
}

.spaces-panel-meta {
  flex-wrap: wrap;
  justify-content: flex-end;
  color: #64748b;
  font-size: 0.82rem;
  font-weight: 600;
}

.spaces-filter-state {
  display: inline-flex;
  align-items: center;
  min-height: 1.7rem;
  padding: 0.28rem 0.62rem;
  border: 1px solid #bfdbfe;
  border-radius: 999px;
  color: #1d4ed8;
  background: #eff6ff;
  font-size: 0.76rem;
  font-weight: 700;
}

.spaces-filter-grid {
  display: grid;
  grid-template-columns: minmax(13rem, 1.35fr) repeat(4, minmax(9rem, 1fr));
  gap: 0.75rem;
  align-items: end;
}

.spaces-filter-field {
  min-width: 0;
}

.spaces-filter-field > span {
  margin-bottom: 0.38rem;
}

.spaces-filter-field .input-group-text {
  border-color: #dbe3ef;
  color: #64748b;
  background: #f8fafc;
}

.spaces-filter-field :deep(.multiselect) {
  width: 100%;
  min-width: 0;
}

.spaces-filter-actions {
  grid-column: 1 / -1;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.spaces-loading-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 11rem;
}

.spaces-table-wrap {
  border: 1px solid #e6edf7;
  border-radius: 8px;
  overflow-x: auto;
  overflow-y: visible;
}

.spaces-dependencies-table {
  width: 100%;
  min-width: 0;
  margin: 0;
  table-layout: fixed;
}

.spaces-dependencies-table th:nth-child(1),
.spaces-dependencies-table td:nth-child(1) {
  width: 22%;
}

.spaces-dependencies-table th:nth-child(2),
.spaces-dependencies-table td:nth-child(2) {
  width: 25%;
}

.spaces-dependencies-table th:nth-child(3),
.spaces-dependencies-table td:nth-child(3) {
  width: 14%;
}

.spaces-dependencies-table th:nth-child(4),
.spaces-dependencies-table td:nth-child(4) {
  width: 13%;
}

.spaces-dependencies-table th:nth-child(5),
.spaces-dependencies-table td:nth-child(5) {
  width: 9%;
}

.spaces-dependencies-table th:nth-child(6),
.spaces-dependencies-table td:nth-child(6) {
  width: 17%;
}

.spaces-dependencies-table thead th {
  padding: 0.68rem 0.8rem;
  border-bottom: 1px solid #dbe7f6;
  color: #64748b;
  background: #f8fafc;
  font-size: 0.7rem;
  font-weight: 500;
  line-height: 1.2;
  text-align: left;
  text-transform: uppercase;
  vertical-align: middle;
}

.spaces-dependencies-table tbody td {
  padding: 0.62rem 0.8rem;
  border-bottom: 1px solid #edf2f7;
  color: #3f4754;
  font-size: 0.82rem;
  font-weight: 400;
  line-height: 1.32;
  text-align: left;
  vertical-align: middle;
}

.spaces-dependencies-table th:nth-child(3),
.spaces-dependencies-table th:nth-child(4),
.spaces-dependencies-table th:nth-child(6),
.spaces-dependencies-table td:nth-child(3),
.spaces-dependencies-table td:nth-child(4),
.spaces-dependencies-table td:nth-child(6) {
  text-align: center;
}

.spaces-dependencies-table th:nth-child(5),
.spaces-dependencies-table td:nth-child(5) {
  text-align: left;
}

.spaces-dependencies-table tbody tr:last-child td {
  border-bottom: 0;
}

.spaces-dependencies-table tbody tr:hover td {
  background: #fbfdff;
}

.spaces-dependency-cell {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
  text-align: left;
}

.spaces-dependency-image {
  flex: 0 0 2.65rem;
  width: 2.65rem;
  height: 2.65rem;
  border-radius: 8px;
  border: 1px solid #dbe7f6;
  object-fit: cover;
}

.spaces-dependency-copy {
  display: grid;
  justify-items: start;
  min-width: 0;
  gap: 0.18rem;
}

.spaces-dependency-name,
.spaces-location-main {
  color: #334155;
  font-weight: 400;
  overflow-wrap: anywhere;
}

.spaces-code-pill,
.spaces-operational-pill,
.spaces-status-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 1.32rem;
  padding: 0.14rem 0.44rem;
  border: 1px solid #cbd5e1;
  border-radius: 999px;
  font-size: 0.68rem;
  font-weight: 400;
  line-height: 1;
  white-space: nowrap;
}

.spaces-code-pill {
  color: #1d4ed8;
  background: #eff6ff;
  border-color: #bfdbfe;
}

.spaces-inactive-text,
.spaces-capacity-text,
.spaces-responsible-text,
.spaces-location-main + small {
  display: block;
  color: #74788d;
  font-size: 0.75rem;
  line-height: 1.25;
}

.spaces-inactive-text {
  color: #b91c1c;
  font-weight: 500;
}

.spaces-capacity-text,
.spaces-responsible-text {
  margin-top: 0.22rem;
}

.spaces-row-actions {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.28rem;
  width: 100%;
  min-width: 7.25rem;
  max-width: 100%;
  vertical-align: middle;
}

.spaces-row-actions .btn {
  flex: 0 0 2.18rem;
  width: 2.18rem;
  min-width: 2.18rem;
  max-width: 2.18rem;
  height: 2.18rem;
  min-height: 2.18rem;
  max-height: 2.18rem;
  padding: 0;
  border-radius: 7px;
  background: #ffffff;
  font-size: 1rem;
  line-height: 1;
}

.spaces-row-actions .btn span {
  display: none;
}

.spaces-operational-stack {
  display: flex;
  align-items: center;
  flex-direction: column;
  justify-content: center;
  flex-wrap: nowrap;
  gap: 0.18rem;
}

.spaces-operational-pill {
  gap: 0.22rem;
  color: #047857;
  background: #ecfdf5;
  border-color: #a7f3d0;
  width: fit-content;
}

.spaces-operational-pill i {
  font-size: 0.78rem;
}

.spaces-operational-pill--off {
  color: #64748b;
  background: #f8fafc;
  border-color: #cbd5e1;
}

.spaces-status-pill--disponible {
  color: #047857;
  background: #ecfdf5;
  border-color: #a7f3d0;
}

.spaces-status-pill--mantencion {
  color: #b45309;
  background: #fffbeb;
  border-color: #fcd34d;
}

.spaces-status-pill--bloqueada {
  color: #b91c1c;
  background: #fef2f2;
  border-color: #fecaca;
}

.spaces-status-pill--no_disponible,
.spaces-status-pill--secondary {
  color: #475569;
  background: #f8fafc;
  border-color: #cbd5e1;
}

.spaces-reservation-count {
  display: grid;
  gap: 0.18rem;
  justify-items: start;
}

.spaces-reservation-value {
  color: #334155;
  font-size: 1rem;
  font-weight: 400;
  line-height: 1;
}

.spaces-reservation-count span {
  color: #74788d;
  font-size: 0.75rem;
}

.spaces-empty-state {
  display: grid;
  justify-items: center;
  gap: 0.35rem;
  min-height: 9rem;
  padding: 1.25rem;
  color: #64748b;
  text-align: center;
}

.spaces-empty-state i {
  color: #94a3b8;
  font-size: 2rem;
}

.spaces-empty-state strong {
  color: #334155;
}

.spaces-pagination-row {
  display: flex;
  justify-content: flex-end;
  margin-top: 0.9rem;
}

:deep(.dependency-form-modal) { overflow: hidden; border: 0; border-radius: 14px; background: #f8fafc; box-shadow: 0 24px 70px rgba(15, 23, 42, .2); }
:deep(.dependency-form-modal .modal-body) { overflow: hidden; border-radius: inherit; }
.dependency-form { overflow: hidden; color: #334155; background: #f8fafc; border-radius: inherit; }
.dependency-form__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1.35rem 1.5rem; border-bottom: 1px solid #e2e8f0; border-radius: 14px 14px 0 0; background: #fff; }
.dependency-form__heading { display: flex; align-items: center; gap: .9rem; }
.dependency-form__heading h4 { margin: .15rem 0; color: #1e293b; font-weight: 750; }
.dependency-form__heading p { margin: 0; color: #64748b; font-size: .85rem; }
.dependency-form__icon { display: grid; place-items: center; flex: 0 0 2.85rem; width: 2.85rem; height: 2.85rem; border-radius: 10px; color: #fff; background: #556ee6; font-size: 1.45rem; box-shadow: 0 8px 18px rgba(85, 110, 230, .22); }
.dependency-form__close { display: grid; place-items: center; width: 2.25rem; height: 2.25rem; padding: 0; border: 0; border-radius: 50%; color: #64748b; background: #f1f5f9; font-size: 1.4rem; transition: .15s ease; }
.dependency-form__close:hover { color: #1e293b; background: #e2e8f0; }
.dependency-form__body { display: grid; gap: 1rem; max-height: min(68vh, 50rem); padding: 1rem 1.5rem 1.35rem; overflow-y: auto; }
.dependency-form__section { padding: 1.15rem; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; }
.dependency-form__section-title { display: flex; align-items: flex-start; gap: .7rem; margin-bottom: 1rem; }
.dependency-form__section-title > span { display: grid; place-items: center; flex: 0 0 1.7rem; width: 1.7rem; height: 1.7rem; border-radius: 50%; color: #556ee6; background: #eef2ff; font-size: .75rem; font-weight: 800; }
.dependency-form__section-title h5 { margin: 0; color: #334155; font-size: .95rem; font-weight: 750; }
.dependency-form__section-title p { margin: .16rem 0 0; color: #64748b; font-size: .78rem; }
.dependency-form .form-label { margin-bottom: .38rem; color: #475569; font-size: .78rem; font-weight: 700; }
.dependency-form .form-label em { color: #ef4444; font-style: normal; }
.dependency-form .form-control, .dependency-form :deep(.multiselect) { min-height: 2.55rem; border-color: #dbe3ef; border-radius: 7px; }
.dependency-form .form-control:focus { border-color: #93a5ee; box-shadow: 0 0 0 3px rgba(85, 110, 230, .1); }
.form-hint { display: block; margin-top: .35rem; color: #94a3b8; font-size: .72rem; }
.dependency-form__advanced { margin-top: 1rem; padding: .8rem 1rem; border: 1px dashed #cbd5e1; border-radius: 8px; background: #f8fafc; }
.dependency-form__advanced summary { display: flex; align-items: center; gap: .45rem; color: #475569; font-size: .8rem; font-weight: 700; cursor: pointer; list-style: none; }
.dependency-form__advanced summary::-webkit-details-marker { display: none; }
.dependency-form__advanced summary span { margin-left: auto; color: #94a3b8; font-size: .68rem; font-weight: 600; text-transform: uppercase; }
.dependency-form__switch-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: .7rem; }
.dependency-form__switch-card { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: .6rem; min-width: 0; padding: .85rem; border: 1px solid #e2e8f0; border-radius: 9px; background: #fff; cursor: pointer; transition: .15s ease; }
.dependency-form__switch-card.is-enabled { border-color: #a5b4fc; background: #f5f7ff; }
.dependency-form__switch-card > i { color: #94a3b8; font-size: 1.3rem; }
.dependency-form__switch-card.is-enabled > i { color: #556ee6; }
.dependency-form__switch-card span { min-width: 0; }
.dependency-form__switch-card strong, .dependency-form__switch-card small, .dependency-form__approval-toggle strong, .dependency-form__approval-toggle small { display: block; }
.dependency-form__switch-card strong, .dependency-form__approval-toggle strong { color: #334155; font-size: .78rem; }
.dependency-form__switch-card small, .dependency-form__approval-toggle small { margin-top: .12rem; color: #64748b; font-size: .68rem; line-height: 1.25; }
.dependency-form__reservation-settings { margin-top: .9rem; padding: 1rem; border-radius: 9px; background: #f8fafc; }
.dependency-form__color-control { display: flex; align-items: center; gap: .6rem; height: 2.55rem; padding: .3rem .6rem; border: 1px solid #dbe3ef; border-radius: 7px; background: #fff; }
.dependency-form__color-control input { width: 2rem; height: 1.75rem; padding: 0; border: 0; background: none; }
.dependency-form__color-control code { color: #475569; font-size: .78rem; }
.dependency-form__approval-toggle { display: flex; align-items: center; gap: .65rem; min-height: 2.55rem; margin: 0; cursor: pointer; }
.dependency-form__file { display: flex; align-items: center; gap: .7rem; min-height: 4.6rem; padding: .75rem; border: 1px dashed #aebbd0; border-radius: 8px; background: #f8fafc; cursor: pointer; }
.dependency-form__file input { position: absolute; width: 1px; height: 1px; opacity: 0; }
.dependency-form__file i { color: #556ee6; font-size: 1.55rem; }
.dependency-form__file strong, .dependency-form__file small { display: block; }
.dependency-form__file strong { color: #475569; font-size: .78rem; }
.dependency-form__file small { max-width: 16rem; margin-top: .15rem; overflow: hidden; color: #94a3b8; font-size: .7rem; text-overflow: ellipsis; white-space: nowrap; }
.dependency-form__footer { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.5rem; border-top: 1px solid #e2e8f0; border-radius: 0 0 14px 14px; background: #fff; }
.dependency-form__footer > span { color: #64748b; font-size: .75rem; }
.dependency-form__footer > div { display: flex; gap: .6rem; }
.dependency-form__footer .btn { display: inline-flex; align-items: center; gap: .4rem; min-height: 2.45rem; padding-inline: 1rem; border-radius: 7px; font-weight: 650; }

@media (max-width: 991.98px) {
  .dependency-form__switch-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 575.98px) {
  .dependency-form__header, .dependency-form__body, .dependency-form__footer { padding-left: 1rem; padding-right: 1rem; }
  .dependency-form__icon { display: none; }
  .dependency-form__body { max-height: 70vh; }
  .dependency-form__switch-grid { grid-template-columns: 1fr; }
  .dependency-form__footer { align-items: stretch; flex-direction: column; }
  .dependency-form__footer > div { display: grid; grid-template-columns: 1fr 1fr; }
  .dependency-form__footer .btn { justify-content: center; }
}

@media (max-width: 1399.98px) {
  .spaces-filter-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .spaces-filter-field--search,
  .spaces-filter-actions {
    grid-column: span 3;
  }

  .spaces-filter-actions {
    justify-content: flex-end;
  }
}

@media (max-width: 1199.98px) {
  .spaces-dependencies-table {
    min-width: 66rem;
  }

  .spaces-table-wrap {
    overflow-x: auto;
  }
}

@media (max-width: 991.98px) {
  .spaces-dependencies-header,
  .spaces-panel-header {
    flex-direction: column;
  }

  .spaces-dependencies-actions,
  .spaces-panel-meta {
    justify-content: flex-start;
    width: 100%;
  }

  .spaces-summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 767.98px) {
  .spaces-filter-grid {
    grid-template-columns: 1fr;
  }

  .spaces-filter-field--search,
  .spaces-filter-actions {
    grid-column: auto;
  }

  .spaces-filter-actions,
  .spaces-dependencies-actions {
    flex-direction: column;
    align-items: stretch;
  }

  .spaces-filter-actions .btn,
  .spaces-dependencies-actions .btn,
  .spaces-refresh-button {
    width: 100%;
  }

  .spaces-summary-grid {
    grid-template-columns: 1fr;
  }

  .spaces-pagination-row {
    justify-content: center;
  }
}
</style>
