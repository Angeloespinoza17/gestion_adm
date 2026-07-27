<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import HelpButton from "../../components/risk-prevention/help-button.vue";
import StatusBadge from "../../components/risk-prevention/status-badge.vue";
import {
  confirmRiskAction,
  downloadRiskFile,
  formatRiskDate,
  formatRiskError,
  showRiskError,
  showRiskSuccess,
  showRiskWarning,
} from "../../components/risk-prevention/module-utils";

const emptyRequirement = () => ({
  id: null,
  name: "",
  code: "",
  kind: "training",
  validity_months: 12,
  requires_evidence: true,
  is_mandatory: true,
  active: true,
  sort_order: 0,
  description: "",
});

const emptyCompliance = () => ({
  id: null,
  staff_id: null,
  staff_name: "",
  requirement_type_id: null,
  requirement_name: "",
  issued_on: "",
  expires_on: "",
  is_not_applicable: false,
  has_evidence: false,
  evidence_name: "",
  evidence: null,
  notes: "",
});

const emptyCommitteeMember = () => ({
  staff_id: "",
  representation: "trabajadores",
  member_role: "titular",
  position_name: "",
  joined_on: "",
  ended_on: "",
  active: true,
});

const emptyCommittee = () => ({
  id: null,
  name: "Comité Paritario de Higiene y Seguridad",
  starts_on: "",
  ends_on: "",
  active: true,
  notes: "",
  members: [emptyCommitteeMember()],
});

export default {
  components: { Layout, LoadingState, HelpButton, StatusBadge },
  data() {
    return {
      activeTab: "matrix",
      loadingMatrix: false,
      loadingRequirements: false,
      loadingCommittees: false,
      saving: false,
      downloadingStaffId: null,
      error: null,
      filters: { search: "", include_inactive: false },
      staff: [],
      requirements: [],
      allRequirements: [],
      committees: [],
      catalogs: { staff_members: [] },
      summary: {
        staff_count: 0,
        requirements_count: 0,
        expected_count: 0,
        ok_count: 0,
        warning_count: 0,
        expired_count: 0,
        pending_count: 0,
        compliance_percentage: 0,
      },
      pagination: { current_page: 1, last_page: 1, total: 0, per_page: 25 },
      showComplianceModal: false,
      showTrainingPendingModal: false,
      showRequirementModal: false,
      showCommitteeModal: false,
      trainingPendingPerson: null,
      queuedCatalogCompliance: null,
      complianceForm: emptyCompliance(),
      requirementForm: emptyRequirement(),
      committeeForm: emptyCommittee(),
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
      return this.permissions.includes("gestionar_prevencion_riesgos")
        || this.permissions.includes("__superadmin__");
    },
    canExport() {
      return this.canManage
        || this.permissions.includes("exportar_prevencion_riesgos")
        || this.permissions.includes("__superadmin__");
    },
    isEditingRequirement() {
      return Boolean(this.requirementForm.id);
    },
    isEditingCommittee() {
      return Boolean(this.committeeForm.id);
    },
    staffOptions() {
      return [
        { value: "", text: "Seleccionar funcionario" },
        ...(this.catalogs.staff_members || []).map((item) => ({
          value: item.id,
          text: `${item.name}${item.rut ? ` · ${item.rut}` : ""}`,
        })),
      ];
    },
  },
  mounted() {
    this.loadCatalogs();
    this.loadMatrix();
  },
  methods: {
    formatRiskDate,
    async loadCatalogs() {
      try {
        const response = await axios.get("/api/risk-prevention/catalogs");
        this.catalogs = response.data || { staff_members: [] };
      } catch (error) {
        this.error = formatRiskError(error, "No se pudo cargar el listado de funcionarios.");
      }
    },
    async loadMatrix(page = 1) {
      this.loadingMatrix = true;
      this.error = null;
      try {
        const response = await axios.get("/api/risk-prevention/personnel/matrix", {
          params: {
            ...this.filters,
            include_inactive: this.filters.include_inactive ? 1 : 0,
            page,
            per_page: this.pagination.per_page,
          },
        });
        this.staff = response.data.data || [];
        this.requirements = response.data.requirements || [];
        this.summary = response.data.summary || this.summary;
        this.pagination = {
          current_page: response.data.current_page || 1,
          last_page: response.data.last_page || 1,
          total: response.data.total || 0,
          per_page: response.data.per_page || 25,
        };
      } catch (error) {
        this.error = formatRiskError(error, "No se pudo cargar la matriz preventiva.");
      } finally {
        this.loadingMatrix = false;
      }
    },
    async loadRequirements() {
      this.loadingRequirements = true;
      try {
        const response = await axios.get("/api/risk-prevention/personnel/requirement-types", {
          params: { include_inactive: 1 },
        });
        this.allRequirements = response.data.data || [];
      } catch (error) {
        this.error = formatRiskError(error, "No se pudieron cargar los requisitos.");
      } finally {
        this.loadingRequirements = false;
      }
    },
    async loadCommittees() {
      this.loadingCommittees = true;
      try {
        const response = await axios.get("/api/risk-prevention/personnel/committees");
        this.committees = response.data.data || [];
      } catch (error) {
        this.error = formatRiskError(error, "No se pudo cargar el Comité Paritario.");
      } finally {
        this.loadingCommittees = false;
      }
    },
    changeTab(tab) {
      this.activeTab = tab;
      this.error = null;
      if (tab === "matrix") this.loadMatrix(this.pagination.current_page);
      if (tab === "requirements") this.loadRequirements();
      if (tab === "committee") this.loadCommittees();
    },
    clearFilters() {
      this.filters = { search: "", include_inactive: false };
      this.loadMatrix(1);
    },
    compliance(person, requirement) {
      return person.compliances?.[String(requirement.id)] || { current_status: "pendiente" };
    },
    openCompliance(person, requirement) {
      if (!this.canManage) return;
      const compliance = this.compliance(person, requirement);
      this.complianceForm = {
        id: compliance.id || null,
        staff_id: person.id,
        staff_name: person.full_name,
        requirement_type_id: requirement.id,
        requirement_name: requirement.name,
        issued_on: compliance.issued_on || "",
        expires_on: compliance.expires_on || "",
        is_not_applicable: Boolean(compliance.is_not_applicable),
        has_evidence: Boolean(compliance.has_evidence),
        evidence_name: compliance.evidence_name || "",
        evidence: null,
        notes: compliance.notes || "",
      };
      this.showComplianceModal = true;
    },
    openTrainingPending(person) {
      this.trainingPendingPerson = person;
      this.showTrainingPendingModal = true;
    },
    openCatalogTrainingCompliance(item) {
      const requirement = this.allRequirements.find(
        (requirementItem) => Number(requirementItem.id) === Number(item.requirement_type_id),
      ) || {
        id: item.requirement_type_id,
        name: item.name,
        kind: "training",
        validity_months: item.validity_months,
      };
      if (!this.trainingPendingPerson) return;

      this.queuedCatalogCompliance = {
        person: this.trainingPendingPerson,
        requirement,
      };
      this.showTrainingPendingModal = false;
    },
    openQueuedCatalogCompliance() {
      if (!this.queuedCatalogCompliance) return;

      const { person, requirement } = this.queuedCatalogCompliance;
      this.queuedCatalogCompliance = null;
      this.openCompliance(person, requirement);
    },
    trainingParticipationLabel(item) {
      if (item.source === "catalog") {
        const catalogLabels = {
          sin_registro: "Sin cumplimiento registrado",
          pendiente: "Cumplimiento pendiente",
          por_vencer: "Requisito próximo a vencer",
          vencido: "Requisito vencido",
        };

        return catalogLabels[item.participation_status]
          || catalogLabels[item.current_status]
          || "Requisito pendiente";
      }

      const labels = {
        sin_registro: "Sin participación registrada",
        pendiente: "Participación pendiente",
        no_asiste: "No asistió",
        vencido: "Capacitación vencida",
      };

      return labels[item.participation_status] || labels[item.current_status] || "Pendiente";
    },
    trainingTypeLabel(value) {
      const labels = {
        induccion: "Inducción",
        actualizacion: "Actualización",
        obligatoria: "Obligatoria",
      };

      return labels[value] || value || "Capacitación";
    },
    onComplianceFile(event) {
      this.complianceForm.evidence = event?.target?.files?.[0] || null;
    },
    async saveCompliance() {
      this.saving = true;
      try {
        const payload = new FormData();
        payload.append("issued_on", this.complianceForm.issued_on || "");
        payload.append("expires_on", this.complianceForm.expires_on || "");
        payload.append("is_not_applicable", this.complianceForm.is_not_applicable ? "1" : "0");
        payload.append("notes", this.complianceForm.notes || "");
        if (this.complianceForm.evidence) payload.append("evidence", this.complianceForm.evidence);

        await axios.post(
          `/api/risk-prevention/personnel/staff/${this.complianceForm.staff_id}/requirements/${this.complianceForm.requirement_type_id}/compliance`,
          payload,
        );
        this.showComplianceModal = false;
        await this.loadMatrix(this.pagination.current_page);
        await showRiskSuccess("El cumplimiento individual fue actualizado.");
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo actualizar el cumplimiento."));
      } finally {
        this.saving = false;
      }
    },
    async removeCompliance() {
      if (!this.complianceForm.id) return;
      const result = await confirmRiskAction({
        title: "Eliminar cumplimiento",
        text: "Se eliminará el registro y su evidencia individual. Esta acción no afecta al funcionario.",
        confirmButtonText: "Sí, eliminar",
      });
      if (!result.isConfirmed) return;

      try {
        await axios.delete(`/api/risk-prevention/personnel/compliances/${this.complianceForm.id}`);
        this.showComplianceModal = false;
        await this.loadMatrix(this.pagination.current_page);
        await showRiskSuccess("El cumplimiento fue eliminado.");
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo eliminar el cumplimiento."));
      }
    },
    async downloadCompliance() {
      try {
        await downloadRiskFile(
          `/api/risk-prevention/personnel/compliances/${this.complianceForm.id}/download`,
          this.complianceForm.evidence_name || `${this.complianceForm.requirement_name}.pdf`,
        );
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo descargar la evidencia."));
      }
    },
    async downloadStaffArchive(person) {
      this.downloadingStaffId = person.id;
      try {
        await downloadRiskFile(
          `/api/risk-prevention/personnel/staff/${person.id}/documents/download`,
          `expediente-preventivo-${String(person.full_name).toLowerCase().replaceAll(" ", "-")}.zip`,
        );
      } catch (error) {
        if (error?.response?.status === 404) {
          await showRiskWarning(
            "No hay documentos asociados a este funcionario.",
            "Expediente sin documentos",
          );
        } else {
          showRiskError(formatRiskError(error, "No se pudo descargar el expediente preventivo."));
        }
      } finally {
        this.downloadingStaffId = null;
      }
    },
    openCreateRequirement() {
      this.requirementForm = emptyRequirement();
      this.requirementForm.sort_order = (this.allRequirements.length + 1) * 10;
      this.showRequirementModal = true;
    },
    openEditRequirement(item) {
      this.requirementForm = {
        id: item.id,
        name: item.name || "",
        code: item.code || "",
        kind: item.kind || "training",
        validity_months: item.validity_months || "",
        requires_evidence: Boolean(item.requires_evidence),
        is_mandatory: Boolean(item.is_mandatory),
        active: Boolean(item.active),
        sort_order: item.sort_order || 0,
        description: item.description || "",
      };
      this.showRequirementModal = true;
    },
    normalizeRequirementCode() {
      this.requirementForm.code = String(this.requirementForm.code || "")
        .trim()
        .toUpperCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/[^A-Z0-9]+/g, "_")
        .replace(/^_|_$/g, "");
    },
    async saveRequirement() {
      this.normalizeRequirementCode();
      this.saving = true;
      try {
        const payload = { ...this.requirementForm };
        delete payload.id;
        payload.validity_months = payload.validity_months || null;
        if (this.isEditingRequirement) {
          await axios.put(
            `/api/risk-prevention/personnel/requirement-types/${this.requirementForm.id}`,
            payload,
          );
        } else {
          await axios.post("/api/risk-prevention/personnel/requirement-types", payload);
        }
        this.showRequirementModal = false;
        await Promise.all([this.loadRequirements(), this.loadMatrix(1), this.loadCatalogs()]);
        await showRiskSuccess(this.isEditingRequirement ? "Requisito actualizado." : "Requisito creado.");
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo guardar el requisito."));
      } finally {
        this.saving = false;
      }
    },
    async removeRequirement(item) {
      const result = await confirmRiskAction({
        title: item.compliances_count || item.trainings_count ? "Desactivar requisito" : "Eliminar requisito",
        text: item.compliances_count || item.trainings_count
          ? "Tiene historial asociado, por lo que se ocultará de la matriz sin borrar sus registros."
          : `Se eliminará “${item.name}”.`,
        confirmButtonText: item.compliances_count || item.trainings_count ? "Sí, desactivar" : "Sí, eliminar",
      });
      if (!result.isConfirmed) return;
      try {
        const response = await axios.delete(`/api/risk-prevention/personnel/requirement-types/${item.id}`);
        await Promise.all([this.loadRequirements(), this.loadMatrix(1), this.loadCatalogs()]);
        await showRiskSuccess(response.data.message);
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo retirar el requisito."));
      }
    },
    openCreateCommittee() {
      this.committeeForm = emptyCommittee();
      this.committeeForm.starts_on = new Date().toISOString().slice(0, 10);
      this.showCommitteeModal = true;
    },
    openEditCommittee(item) {
      this.committeeForm = {
        id: item.id,
        name: item.name || "",
        starts_on: item.starts_on || "",
        ends_on: item.ends_on || "",
        active: Boolean(item.active),
        notes: item.notes || "",
        members: (item.staff_members || []).length
          ? item.staff_members.map((member) => ({
              staff_id: member.id,
              representation: member.pivot?.representation || "trabajadores",
              member_role: member.pivot?.member_role || "titular",
              position_name: member.pivot?.position_name || "",
              joined_on: member.pivot?.joined_on || "",
              ended_on: member.pivot?.ended_on || "",
              active: Boolean(member.pivot?.active),
            }))
          : [emptyCommitteeMember()],
      };
      this.showCommitteeModal = true;
    },
    addCommitteeMember() {
      this.committeeForm.members.push(emptyCommitteeMember());
    },
    removeCommitteeMember(index) {
      if (this.committeeForm.members.length === 1) {
        this.committeeForm.members = [emptyCommitteeMember()];
        return;
      }
      this.committeeForm.members.splice(index, 1);
    },
    async saveCommittee() {
      this.saving = true;
      try {
        const payload = {
          ...this.committeeForm,
          members: this.committeeForm.members.filter((member) => member.staff_id),
        };
        delete payload.id;
        if (this.isEditingCommittee) {
          await axios.put(`/api/risk-prevention/personnel/committees/${this.committeeForm.id}`, payload);
        } else {
          await axios.post("/api/risk-prevention/personnel/committees", payload);
        }
        this.showCommitteeModal = false;
        await this.loadCommittees();
        await showRiskSuccess(this.isEditingCommittee ? "Comité actualizado." : "Comité creado.");
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo guardar el Comité Paritario."));
      } finally {
        this.saving = false;
      }
    },
    async removeCommittee(item) {
      const result = await confirmRiskAction({
        title: "Desactivar Comité Paritario",
        text: "Si tiene integrantes, se conservará todo el historial y sólo quedará inactivo.",
        confirmButtonText: "Sí, desactivar",
      });
      if (!result.isConfirmed) return;
      try {
        const response = await axios.delete(`/api/risk-prevention/personnel/committees/${item.id}`);
        await this.loadCommittees();
        await showRiskSuccess(response.data.message);
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo desactivar el comité."));
      }
    },
    activeCommitteeMembers(item) {
      return (item.staff_members || []).filter((member) => member.pivot?.active).length;
    },
    memberLabel(member) {
      const parts = [
        member.pivot?.representation === "empleador" ? "Empleador" : "Trabajadores",
        member.pivot?.member_role === "suplente" ? "Suplente" : "Titular",
        member.pivot?.position_name,
      ].filter(Boolean);
      return parts.join(" · ");
    },
  },
};
</script>

<template>
  <Layout>
    <main class="personnel-page">
      <section class="personnel-hero">
        <div>
          <span class="personnel-hero__eyebrow">Prevención de Riesgos</span>
          <h1>Gestión del personal</h1>
          <p>Matriz maestra de capacitaciones y documentos vigentes por funcionario.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <HelpButton
            title="Matriz preventiva"
            text="Las capacitaciones obligatorias se consolidan en una sola columna. Los documentos y otros requisitos mantienen su cumplimiento individual."
          />
          <BButton variant="light" @click="$router.push('/risk-prevention/trainings')">
            <i class="bx bx-chalkboard me-1"></i> Gestionar capacitaciones
          </BButton>
        </div>
      </section>

      <nav class="personnel-tabs" aria-label="Secciones de gestión del personal">
        <button :class="{ active: activeTab === 'matrix' }" @click="changeTab('matrix')">
          <i class="bx bx-grid-alt"></i> Matriz de cumplimiento
        </button>
        <button :class="{ active: activeTab === 'requirements' }" @click="changeTab('requirements')">
          <i class="bx bx-list-check"></i> Tipos de requisitos
        </button>
        <button :class="{ active: activeTab === 'committee' }" @click="changeTab('committee')">
          <i class="bx bx-group"></i> Comité Paritario
        </button>
      </nav>

      <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

      <template v-if="activeTab === 'matrix'">
        <section class="summary-grid">
          <article>
            <span><i class="bx bx-user"></i></span>
            <div><strong>{{ summary.staff_count }}</strong><small>Funcionarios activos</small></div>
          </article>
          <article>
            <span class="success"><i class="bx bx-check-shield"></i></span>
            <div><strong>{{ summary.compliance_percentage }}%</strong><small>Cumplimiento vigente</small></div>
          </article>
          <article>
            <span class="warning"><i class="bx bx-time-five"></i></span>
            <div><strong>{{ summary.warning_count }}</strong><small>Por vencer</small></div>
          </article>
          <article>
            <span class="danger"><i class="bx bx-error-circle"></i></span>
            <div><strong>{{ summary.expired_count + summary.pending_count }}</strong><small>Vencidos o pendientes</small></div>
          </article>
        </section>

        <section class="matrix-toolbar">
          <div class="matrix-search">
            <i class="bx bx-search"></i>
            <input
              v-model="filters.search"
              type="search"
              placeholder="Buscar por funcionario, RUT o cargo"
              @keyup.enter="loadMatrix(1)"
            />
          </div>
          <BFormCheckbox v-model="filters.include_inactive">Incluir inactivos</BFormCheckbox>
          <BButton variant="primary" @click="loadMatrix(1)">Filtrar</BButton>
          <BButton variant="light" @click="clearFilters">Limpiar</BButton>
        </section>

        <LoadingState v-if="loadingMatrix" message="Cargando matriz de cumplimiento..." />
        <section v-else class="matrix-card">
          <div class="matrix-scroll">
            <table class="matrix-table">
              <thead>
                <tr>
                  <th class="sticky-person">Funcionario</th>
                  <th class="requirement-heading training-heading">
                    <span>Capacitaciones</span>
                    <small>Requisitos pendientes</small>
                  </th>
                  <th v-for="requirement in requirements" :key="requirement.id" class="requirement-heading">
                    <span>{{ requirement.name }}</span>
                    <small>
                      {{ requirement.kind === "training" ? "Capacitación" : "Documento" }}
                      <template v-if="requirement.validity_months"> · {{ requirement.validity_months }} meses</template>
                    </small>
                  </th>
                  <th class="sticky-actions">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="person in staff" :key="person.id">
                  <td class="sticky-person person-cell">
                    <strong>{{ person.full_name }}</strong>
                    <span>{{ person.rut || "Sin RUT" }} · {{ person.position || "Sin cargo" }}</span>
                    <BBadge v-if="!person.active" variant="secondary">Inactivo</BBadge>
                  </td>
                  <td class="training-requirement-cell">
                    <button
                      type="button"
                      class="training-requirement-button"
                      :class="{
                        pending: person.training_requirements?.pending_count,
                        complete: person.training_requirements?.required_count
                          && !person.training_requirements?.pending_count,
                      }"
                      @click="openTrainingPending(person)"
                    >
                      <template v-if="person.training_requirements?.pending_count">
                        <i class="bx bx-error-circle"></i>
                        <strong>
                          {{ person.training_requirements.pending_count }}
                          {{ person.training_requirements.pending_count === 1 ? "pendiente" : "pendientes" }}
                        </strong>
                        <small>Ver detalle</small>
                      </template>
                      <template v-else-if="person.training_requirements?.required_count">
                        <i class="bx bx-check-circle"></i>
                        <strong>Al día</strong>
                        <small>{{ person.training_requirements.completed_count }} completadas</small>
                      </template>
                      <template v-else>
                        <i class="bx bx-minus-circle"></i>
                        <strong>Sin requisitos</strong>
                        <small>No hay capacitaciones marcadas</small>
                      </template>
                    </button>
                  </td>
                  <td
                    v-for="requirement in requirements"
                    :key="`${person.id}-${requirement.id}`"
                    class="compliance-cell"
                  >
                    <button
                      type="button"
                      class="compliance-button"
                      :disabled="!canManage"
                      :title="canManage ? 'Editar cumplimiento individual' : 'Sólo lectura'"
                      @click="openCompliance(person, requirement)"
                    >
                      <StatusBadge :status="compliance(person, requirement).current_status" />
                      <small v-if="compliance(person, requirement).expires_on">
                        Vence {{ formatRiskDate(compliance(person, requirement).expires_on) }}
                      </small>
                      <small v-else-if="compliance(person, requirement).has_evidence">Sin vencimiento</small>
                      <small v-else>Sin respaldo</small>
                      <i v-if="compliance(person, requirement).has_evidence" class="bx bx-paperclip"></i>
                    </button>
                  </td>
                  <td class="sticky-actions">
                    <BButton
                      v-if="canExport"
                      size="sm"
                      variant="primary"
                      :disabled="downloadingStaffId === person.id"
                      title="Descargar todos los documentos preventivos del funcionario"
                      @click="downloadStaffArchive(person)"
                    >
                      <i class="bx" :class="downloadingStaffId === person.id ? 'bx-loader-alt bx-spin' : 'bx-download'"></i>
                      Expediente
                    </BButton>
                    <span v-else class="text-muted small">Sin permiso de exportación</span>
                  </td>
                </tr>
                <tr v-if="!staff.length">
                  <td :colspan="requirements.length + 3" class="text-center text-muted py-5">
                    No hay funcionarios para los filtros seleccionados.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <footer v-if="pagination.last_page > 1" class="matrix-pagination">
            <span>{{ pagination.total }} funcionarios</span>
            <BPagination
              v-model="pagination.current_page"
              :total-rows="pagination.total"
              :per-page="pagination.per_page"
              @update:model-value="loadMatrix"
            />
          </footer>
        </section>
      </template>

      <template v-else-if="activeTab === 'requirements'">
        <section class="section-heading">
          <div>
            <h2>Tipos de capacitación y documentos</h2>
            <p>Cada requisito activo aparece automáticamente como columna en la matriz.</p>
          </div>
          <BButton v-if="canManage" variant="primary" @click="openCreateRequirement">
            <i class="bx bx-plus me-1"></i> Nuevo requisito
          </BButton>
        </section>

        <LoadingState v-if="loadingRequirements" message="Cargando tipos de requisitos..." />
        <section v-else class="requirements-grid">
          <article v-for="item in allRequirements" :key="item.id" :class="{ inactive: !item.active }">
            <header>
              <span class="requirement-icon" :class="item.kind">
                <i class="bx" :class="item.kind === 'training' ? 'bx-chalkboard' : 'bx-file'"></i>
              </span>
              <div>
                <h3>{{ item.name }}</h3>
                <small>{{ item.code }}</small>
              </div>
              <BBadge :variant="item.active ? 'success' : 'secondary'">{{ item.active ? "Activo" : "Inactivo" }}</BBadge>
            </header>
            <p>{{ item.description || "Sin descripción." }}</p>
            <dl>
              <div><dt>Tipo</dt><dd>{{ item.kind === "training" ? "Capacitación" : "Documento" }}</dd></div>
              <div><dt>Vigencia</dt><dd>{{ item.validity_months ? `${item.validity_months} meses` : "Sin vencimiento" }}</dd></div>
              <div><dt>Respaldo</dt><dd>{{ item.requires_evidence ? "Obligatorio" : "Opcional" }}</dd></div>
              <div><dt>Registros</dt><dd>{{ item.compliances_count || 0 }}</dd></div>
            </dl>
            <footer v-if="canManage">
              <BButton size="sm" variant="outline-primary" @click="openEditRequirement(item)">Editar</BButton>
              <BButton size="sm" variant="outline-danger" @click="removeRequirement(item)">
                {{ item.compliances_count || item.trainings_count ? "Desactivar" : "Eliminar" }}
              </BButton>
            </footer>
          </article>
          <div v-if="!allRequirements.length" class="empty-state">
            <i class="bx bx-list-plus"></i>
            <h3>Aún no hay requisitos configurados</h3>
            <p>Crea el primer tipo de capacitación o documento.</p>
          </div>
        </section>
      </template>

      <template v-else>
        <section class="section-heading">
          <div>
            <h2>Comité Paritario de Higiene y Seguridad</h2>
            <p>Períodos, representantes titulares y suplentes vinculados a funcionarios.</p>
          </div>
          <BButton v-if="canManage" variant="primary" @click="openCreateCommittee">
            <i class="bx bx-plus me-1"></i> Nuevo comité
          </BButton>
        </section>

        <LoadingState v-if="loadingCommittees" message="Cargando Comité Paritario..." />
        <section v-else class="committee-list">
          <article v-for="item in committees" :key="item.id">
            <header>
              <div>
                <span class="committee-state" :class="{ active: item.active }">
                  {{ item.active ? "Vigente" : "Histórico" }}
                </span>
                <h3>{{ item.name }}</h3>
                <p>{{ formatRiskDate(item.starts_on) }} — {{ item.ends_on ? formatRiskDate(item.ends_on) : "Sin fecha de término" }}</p>
              </div>
              <div class="committee-actions" v-if="canManage">
                <BButton size="sm" variant="outline-primary" @click="openEditCommittee(item)">Editar</BButton>
                <BButton v-if="item.active" size="sm" variant="outline-danger" @click="removeCommittee(item)">Desactivar</BButton>
              </div>
            </header>
            <div class="committee-count">
              <strong>{{ activeCommitteeMembers(item) }}</strong>
              <span>integrantes activos</span>
            </div>
            <div class="member-grid">
              <div v-for="member in item.staff_members || []" :key="member.id" :class="{ inactive: !member.pivot?.active }">
                <span class="member-avatar">{{ String(member.full_name || "?").charAt(0) }}</span>
                <div>
                  <strong>{{ member.full_name }}</strong>
                  <small>{{ memberLabel(member) }}</small>
                </div>
              </div>
              <span v-if="!(item.staff_members || []).length" class="text-muted">Sin integrantes registrados.</span>
            </div>
          </article>
          <div v-if="!committees.length" class="empty-state">
            <i class="bx bx-group"></i>
            <h3>No hay comités registrados</h3>
            <p>Registra el período vigente y sus integrantes.</p>
          </div>
        </section>
      </template>

      <BModal
        v-model="showTrainingPendingModal"
        size="lg"
        :title="`Capacitaciones pendientes · ${trainingPendingPerson?.full_name || ''}`"
        hide-footer
        @hidden="openQueuedCatalogCompliance"
      >
        <template v-if="trainingPendingPerson">
          <BAlert
            show
            :variant="trainingPendingPerson.training_requirements?.pending_count ? 'warning' : 'success'"
          >
            <template v-if="trainingPendingPerson.training_requirements?.pending_count">
              Este funcionario tiene
              <strong>{{ trainingPendingPerson.training_requirements.pending_count }}</strong>
              {{ trainingPendingPerson.training_requirements.pending_count === 1
                ? "capacitación pendiente"
                : "capacitaciones pendientes" }}.
            </template>
            <template v-else>
              El funcionario está al día en todas las capacitaciones marcadas como requisito.
            </template>
          </BAlert>

          <div
            v-if="trainingPendingPerson.training_requirements?.pending?.length"
            class="pending-training-list"
          >
            <article
              v-for="training in trainingPendingPerson.training_requirements.pending"
              :key="training.key || training.id"
              class="pending-training-item"
            >
              <div class="pending-training-item__icon">
                <i class="bx" :class="training.source === 'catalog' ? 'bx-certification' : 'bx-chalkboard'"></i>
              </div>
              <div class="pending-training-item__content">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                  <div>
                    <strong>{{ training.name }}</strong>
                    <small v-if="training.source === 'catalog'">
                      Requisito permanente
                      <template v-if="training.validity_months">
                        · Vigencia {{ training.validity_months }} meses
                      </template>
                    </small>
                    <small v-else>
                      {{ trainingTypeLabel(training.training_type) }}
                      <template v-if="training.modality"> · {{ training.modality }}</template>
                      <template v-if="training.training_date">
                        · Planificada {{ formatRiskDate(training.training_date) }}
                      </template>
                    </small>
                  </div>
                  <StatusBadge :status="training.current_status" />
                </div>
                <div class="pending-training-item__details">
                  <span><i class="bx bx-info-circle"></i> {{ trainingParticipationLabel(training) }}</span>
                  <span v-if="training.issued_on">
                    <i class="bx bx-calendar-check"></i> Realizada {{ formatRiskDate(training.issued_on) }}
                  </span>
                  <span v-if="training.expires_on">
                    <i class="bx bx-calendar-x"></i> Vence {{ formatRiskDate(training.expires_on) }}
                  </span>
                  <BButton
                    v-if="canManage && training.source === 'catalog'"
                    size="sm"
                    variant="outline-primary"
                    @click="openCatalogTrainingCompliance(training)"
                  >
                    Actualizar cumplimiento
                  </BButton>
                </div>
                <p v-if="training.notes">{{ training.notes }}</p>
              </div>
            </article>
          </div>

          <div v-else class="training-empty-state">
            <i class="bx bx-check-shield"></i>
            <strong>Sin capacitaciones pendientes</strong>
            <span>No hay acciones pendientes para este funcionario.</span>
          </div>

          <div class="d-flex justify-content-end gap-2 mt-4">
            <BButton variant="light" @click="showTrainingPendingModal = false">Cerrar</BButton>
            <BButton
              v-if="canManage"
              variant="primary"
              @click="showTrainingPendingModal = false; $router.push('/risk-prevention/trainings')"
            >
              Gestionar capacitaciones
            </BButton>
          </div>
        </template>
      </BModal>

      <BModal
        v-model="showComplianceModal"
        size="lg"
        :title="`${complianceForm.requirement_name} · ${complianceForm.staff_name}`"
        hide-footer
      >
        <BAlert show variant="info">
          Las fechas y el respaldo pertenecen exclusivamente a este funcionario.
        </BAlert>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Fecha de realización / emisión</label>
            <BFormInput v-model="complianceForm.issued_on" type="date" :disabled="complianceForm.is_not_applicable" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Fecha de vencimiento individual</label>
            <BFormInput v-model="complianceForm.expires_on" type="date" :disabled="complianceForm.is_not_applicable" />
            <small class="text-muted">Si queda vacía y el requisito tiene vigencia, se calculará automáticamente.</small>
          </div>
          <div class="col-12">
            <label class="form-label">Documento o certificado</label>
            <BFormFile :disabled="complianceForm.is_not_applicable" @change="onComplianceFile" />
            <div v-if="complianceForm.evidence_name" class="existing-file">
              <i class="bx bx-paperclip"></i> {{ complianceForm.evidence_name }}
              <BButton
                v-if="complianceForm.id && complianceForm.has_evidence"
                size="sm"
                variant="link"
                @click="downloadCompliance"
              >
                Descargar
              </BButton>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Observaciones</label>
            <BFormTextarea v-model="complianceForm.notes" rows="3" />
          </div>
          <div class="col-12">
            <BFormCheckbox v-model="complianceForm.is_not_applicable">
              Este requisito no aplica a este funcionario
            </BFormCheckbox>
          </div>
        </div>
        <div class="d-flex justify-content-between gap-2 mt-4">
          <BButton
            v-if="complianceForm.id"
            variant="outline-danger"
            :disabled="saving"
            @click="removeCompliance"
          >
            Eliminar registro
          </BButton>
          <span v-else></span>
          <div class="d-flex gap-2">
            <BButton variant="light" @click="showComplianceModal = false">Cancelar</BButton>
            <BButton variant="primary" :disabled="saving" @click="saveCompliance">
              {{ saving ? "Guardando..." : "Guardar" }}
            </BButton>
          </div>
        </div>
      </BModal>

      <BModal
        v-model="showRequirementModal"
        size="lg"
        :title="isEditingRequirement ? 'Editar requisito' : 'Nuevo requisito'"
        hide-footer
      >
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Nombre</label>
            <BFormInput v-model="requirementForm.name" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Código único</label>
            <BFormInput v-model="requirementForm.code" placeholder="PRIMEROS_AUXILIOS" @blur="normalizeRequirementCode" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Tipo</label>
            <BFormSelect v-model="requirementForm.kind" :options="[
              { value: 'training', text: 'Capacitación' },
              { value: 'document', text: 'Documento' },
            ]" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Vigencia en meses</label>
            <BFormInput v-model="requirementForm.validity_months" type="number" min="1" placeholder="Sin vencimiento" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Orden en matriz</label>
            <BFormInput v-model="requirementForm.sort_order" type="number" min="0" />
          </div>
          <div class="col-12">
            <label class="form-label">Descripción</label>
            <BFormTextarea v-model="requirementForm.description" rows="3" />
          </div>
          <div class="col-md-4"><BFormCheckbox v-model="requirementForm.requires_evidence">Exige respaldo</BFormCheckbox></div>
          <div class="col-md-4"><BFormCheckbox v-model="requirementForm.is_mandatory">Obligatorio</BFormCheckbox></div>
          <div class="col-md-4"><BFormCheckbox v-model="requirementForm.active">Visible en matriz</BFormCheckbox></div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
          <BButton variant="light" @click="showRequirementModal = false">Cancelar</BButton>
          <BButton variant="primary" :disabled="saving" @click="saveRequirement">
            {{ saving ? "Guardando..." : "Guardar requisito" }}
          </BButton>
        </div>
      </BModal>

      <BModal
        v-model="showCommitteeModal"
        size="xl"
        :title="isEditingCommittee ? 'Editar Comité Paritario' : 'Nuevo Comité Paritario'"
        hide-footer
      >
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nombre</label>
            <BFormInput v-model="committeeForm.name" />
          </div>
          <div class="col-md-2">
            <label class="form-label">Inicio</label>
            <BFormInput v-model="committeeForm.starts_on" type="date" />
          </div>
          <div class="col-md-2">
            <label class="form-label">Término</label>
            <BFormInput v-model="committeeForm.ends_on" type="date" />
          </div>
          <div class="col-md-2 d-flex align-items-end pb-2">
            <BFormCheckbox v-model="committeeForm.active">Comité vigente</BFormCheckbox>
          </div>
          <div class="col-12">
            <label class="form-label">Observaciones</label>
            <BFormTextarea v-model="committeeForm.notes" rows="2" />
          </div>
        </div>

        <div class="committee-form-heading">
          <div>
            <h5>Integrantes</h5>
            <p>La relación se almacena en una tabla intermedia y conserva integrantes históricos.</p>
          </div>
          <BButton size="sm" variant="outline-primary" @click="addCommitteeMember">Agregar integrante</BButton>
        </div>
        <div
          v-for="(member, index) in committeeForm.members"
          :key="index"
          class="committee-member-form row g-2 align-items-end"
        >
          <div class="col-md-4">
            <label class="form-label">Funcionario</label>
            <BFormSelect v-model="member.staff_id" :options="staffOptions" />
          </div>
          <div class="col-md-2">
            <label class="form-label">Representación</label>
            <BFormSelect v-model="member.representation" :options="[
              { value: 'trabajadores', text: 'Trabajadores' },
              { value: 'empleador', text: 'Empleador' },
            ]" />
          </div>
          <div class="col-md-2">
            <label class="form-label">Calidad</label>
            <BFormSelect v-model="member.member_role" :options="[
              { value: 'titular', text: 'Titular' },
              { value: 'suplente', text: 'Suplente' },
            ]" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Cargo en el comité</label>
            <BFormInput v-model="member.position_name" placeholder="Presidente, secretario..." />
          </div>
          <div class="col-md-1">
            <BButton class="w-100" size="sm" variant="outline-danger" @click="removeCommitteeMember(index)">X</BButton>
          </div>
          <div class="col-md-3">
            <label class="form-label">Desde</label>
            <BFormInput v-model="member.joined_on" type="date" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Hasta</label>
            <BFormInput v-model="member.ended_on" type="date" />
          </div>
          <div class="col-md-3 pb-2">
            <BFormCheckbox v-model="member.active">Integrante activo</BFormCheckbox>
          </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
          <BButton variant="light" @click="showCommitteeModal = false">Cancelar</BButton>
          <BButton variant="primary" :disabled="saving" @click="saveCommittee">
            {{ saving ? "Guardando..." : "Guardar comité" }}
          </BButton>
        </div>
      </BModal>
    </main>
  </Layout>
</template>

<style scoped>
.personnel-page {
  --ink: #18233d;
  --muted: #758097;
  padding: 0.25rem 0 2rem;
}

.personnel-hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  padding: 1.8rem 2rem;
  margin-bottom: 1rem;
  overflow: hidden;
  border-radius: 1.25rem;
  background:
    radial-gradient(circle at 80% 20%, rgba(58, 210, 170, 0.2), transparent 30%),
    linear-gradient(120deg, #14224e 0%, #284dac 67%, #237d9e 100%);
  box-shadow: 0 18px 38px rgba(34, 64, 132, 0.18);
}

.personnel-hero__eyebrow {
  color: #acd3ff;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.personnel-hero h1 { margin: 0.45rem 0 0.3rem; color: white; font-size: clamp(1.65rem, 3vw, 2.3rem); }
.personnel-hero p { margin: 0; color: rgba(255, 255, 255, 0.76); }

.personnel-tabs {
  display: flex;
  gap: 0.45rem;
  padding: 0.45rem;
  margin-bottom: 1rem;
  border: 1px solid #e2e7f0;
  border-radius: 0.9rem;
  background: white;
}

.personnel-tabs button {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.65rem 0.9rem;
  border: 0;
  border-radius: 0.65rem;
  background: transparent;
  color: #667188;
  font-weight: 700;
}

.personnel-tabs button.active { background: #e9efff; color: #3159d9; }

.summary-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.8rem;
  margin-bottom: 1rem;
}

.summary-grid article {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  padding: 1rem;
  border: 1px solid #e3e8f0;
  border-radius: 0.95rem;
  background: white;
  box-shadow: 0 7px 18px rgba(31, 45, 84, 0.05);
}

.summary-grid article > span {
  display: grid;
  width: 2.8rem;
  height: 2.8rem;
  place-items: center;
  border-radius: 0.8rem;
  background: #e8efff;
  color: #3159d9;
  font-size: 1.35rem;
}

.summary-grid article > span.success { background: #e4f7ef; color: #148462; }
.summary-grid article > span.warning { background: #fff3d5; color: #ac7100; }
.summary-grid article > span.danger { background: #fde8e8; color: #c53a43; }
.summary-grid strong { display: block; color: var(--ink); font-size: 1.45rem; line-height: 1.1; }
.summary-grid small { color: var(--muted); }

.matrix-toolbar {
  display: grid;
  grid-template-columns: minmax(18rem, 1fr) auto auto auto;
  align-items: center;
  gap: 0.7rem;
  padding: 0.85rem;
  margin-bottom: 1rem;
  border: 1px solid #e3e8f0;
  border-radius: 0.9rem;
  background: white;
}

.matrix-search { position: relative; }
.matrix-search i { position: absolute; top: 50%; left: 0.85rem; color: #919aab; transform: translateY(-50%); }
.matrix-search input {
  width: 100%;
  min-height: 2.4rem;
  padding: 0.5rem 0.8rem 0.5rem 2.3rem;
  border: 1px solid #cfd6e2;
  border-radius: 0.5rem;
  outline: none;
}
.matrix-search input:focus { border-color: #7191e7; box-shadow: 0 0 0 0.18rem rgba(49, 89, 217, 0.11); }

.matrix-card {
  overflow: hidden;
  border: 1px solid #dee4ee;
  border-radius: 1rem;
  background: white;
  box-shadow: 0 10px 25px rgba(31, 45, 84, 0.06);
}

.matrix-scroll { max-height: 68vh; overflow: auto; }
.matrix-table { width: max-content; min-width: 100%; border-collapse: separate; border-spacing: 0; }
.matrix-table th {
  position: sticky;
  z-index: 3;
  top: 0;
  min-width: 11.5rem;
  padding: 0.8rem;
  border-bottom: 1px solid #dfe5ef;
  border-right: 1px solid #edf0f5;
  background: #f4f6fa;
  color: #47536b;
  font-size: 0.72rem;
  text-align: center;
}
.matrix-table td { padding: 0.65rem; border-bottom: 1px solid #edf0f5; border-right: 1px solid #edf0f5; }
.matrix-table .sticky-person { position: sticky; z-index: 2; left: 0; min-width: 16rem; background: white; }
.matrix-table th.sticky-person { z-index: 5; background: #edf1f8; text-align: left; }
.matrix-table .sticky-actions { position: sticky; z-index: 2; right: 0; min-width: 8.5rem; background: white; text-align: center; }
.matrix-table th.sticky-actions { z-index: 5; background: #edf1f8; }
.requirement-heading span { display: block; color: #283550; font-weight: 800; }
.requirement-heading small { display: block; margin-top: 0.2rem; color: #8a94a8; font-weight: 500; }
.person-cell strong { display: block; color: var(--ink); font-size: 0.8rem; }
.person-cell span { display: block; margin-top: 0.2rem; color: var(--muted); font-size: 0.68rem; }

.compliance-cell { min-width: 11.5rem; text-align: center; }
.compliance-button {
  position: relative;
  display: flex;
  width: 100%;
  min-height: 4rem;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 0.28rem;
  border: 1px solid transparent;
  border-radius: 0.65rem;
  background: #fafbfc;
  transition: 150ms ease;
}
.compliance-button:not(:disabled):hover { border-color: #abc0ef; background: #f0f4ff; transform: translateY(-1px); }
.compliance-button small { color: #7d879a; font-size: 0.64rem; }
.compliance-button > i { position: absolute; top: 0.35rem; right: 0.4rem; color: #3159d9; }
.training-heading { min-width: 10.5rem; }
.training-requirement-cell { min-width: 10.5rem; text-align: center; }
.training-requirement-button {
  display: flex;
  width: 100%;
  min-height: 4rem;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 0.2rem;
  border: 1px solid #dce2eb;
  border-radius: 0.7rem;
  background: #f8fafc;
  color: #667085;
  transition: 150ms ease;
}
.training-requirement-button:hover { border-color: #9db2e7; transform: translateY(-1px); }
.training-requirement-button i { font-size: 1.15rem; }
.training-requirement-button strong { font-size: 0.76rem; }
.training-requirement-button small { font-size: 0.64rem; }
.training-requirement-button.pending {
  border-color: #f4c45e;
  background: #fff8e7;
  color: #9b6500;
}
.training-requirement-button.complete {
  border-color: #8dd7b2;
  background: #effbf5;
  color: #157347;
}
.pending-training-list { display: grid; gap: 0.75rem; }
.pending-training-item {
  display: flex;
  gap: 0.8rem;
  padding: 0.9rem;
  border: 1px solid #e0e5ed;
  border-radius: 0.8rem;
  background: #fbfcfe;
}
.pending-training-item__icon {
  display: grid;
  width: 2.5rem;
  height: 2.5rem;
  flex: 0 0 2.5rem;
  place-items: center;
  border-radius: 0.65rem;
  background: #fff2d5;
  color: #a66a00;
  font-size: 1.25rem;
}
.pending-training-item__content { min-width: 0; flex: 1; }
.pending-training-item__content strong { display: block; color: var(--ink); }
.pending-training-item__content small { display: block; margin-top: 0.15rem; color: var(--muted); }
.pending-training-item__details { display: flex; flex-wrap: wrap; gap: 0.45rem 1rem; margin-top: 0.65rem; }
.pending-training-item__details span { color: #657087; font-size: 0.72rem; }
.pending-training-item__details i { margin-right: 0.2rem; color: #3159d9; }
.pending-training-item__content p { margin: 0.6rem 0 0; color: #657087; font-size: 0.75rem; }
.training-empty-state {
  display: flex;
  min-height: 10rem;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 0.35rem;
  border: 1px dashed #9fd7ba;
  border-radius: 0.8rem;
  background: #f3fcf7;
  color: #157347;
  text-align: center;
}
.training-empty-state i { font-size: 2rem; }
.training-empty-state span { color: #6c7d73; font-size: 0.78rem; }
.matrix-pagination { display: flex; align-items: center; justify-content: space-between; padding: 0.8rem 1rem; color: var(--muted); }
.matrix-pagination :deep(.pagination) { margin: 0; }

.section-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.1rem;
  margin-bottom: 1rem;
  border: 1px solid #e3e8f0;
  border-radius: 0.9rem;
  background: white;
}
.section-heading h2 { margin: 0; color: var(--ink); font-size: 1.1rem; }
.section-heading p { margin: 0.25rem 0 0; color: var(--muted); font-size: 0.78rem; }

.requirements-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.9rem; }
.requirements-grid article {
  display: flex;
  min-height: 17rem;
  flex-direction: column;
  padding: 1rem;
  border: 1px solid #e1e6ef;
  border-radius: 1rem;
  background: white;
  box-shadow: 0 7px 18px rgba(31, 45, 84, 0.05);
}
.requirements-grid article.inactive { opacity: 0.68; }
.requirements-grid header { display: flex; align-items: center; gap: 0.7rem; }
.requirements-grid header > .requirement-icon {
  display: grid;
  width: 2.7rem;
  height: 2.7rem;
  flex: 0 0 auto;
  place-items: center;
  border-radius: 0.75rem;
  background: #e9efff;
  color: #3159d9;
  font-size: 1.3rem;
}
.requirements-grid header > .requirement-icon.document { background: #e4f7ef; color: #148462; }
.requirements-grid header > div { min-width: 0; flex: 1; }
.requirements-grid h3 { margin: 0; color: var(--ink); font-size: 0.9rem; }
.requirements-grid header small { color: #8a94a8; font-size: 0.66rem; }
.requirements-grid article > p { min-height: 2.3rem; margin: 1rem 0; color: var(--muted); font-size: 0.76rem; }
.requirements-grid dl { display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem; margin: 0; }
.requirements-grid dt { color: #99a1b0; font-size: 0.62rem; text-transform: uppercase; }
.requirements-grid dd { margin: 0; color: #46536a; font-size: 0.73rem; }
.requirements-grid footer { display: flex; justify-content: flex-end; gap: 0.5rem; padding-top: 0.9rem; margin-top: auto; border-top: 1px solid #edf0f5; }

.committee-list { display: grid; gap: 0.9rem; }
.committee-list > article { padding: 1.1rem; border: 1px solid #e1e6ef; border-radius: 1rem; background: white; }
.committee-list > article > header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
.committee-list h3 { margin: 0.35rem 0 0.15rem; color: var(--ink); font-size: 1rem; }
.committee-list header p { margin: 0; color: var(--muted); font-size: 0.74rem; }
.committee-state { padding: 0.22rem 0.5rem; border-radius: 999px; background: #eceff4; color: #70798c; font-size: 0.65rem; font-weight: 800; }
.committee-state.active { background: #e4f7ef; color: #148462; }
.committee-actions { display: flex; gap: 0.4rem; }
.committee-count { display: inline-flex; align-items: baseline; gap: 0.35rem; margin: 1rem 0 0.7rem; color: var(--muted); }
.committee-count strong { color: #3159d9; font-size: 1.2rem; }
.member-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.6rem; }
.member-grid > div { display: flex; align-items: center; gap: 0.55rem; padding: 0.6rem; border: 1px solid #edf0f5; border-radius: 0.7rem; background: #fafbfc; }
.member-grid > div.inactive { opacity: 0.55; }
.member-avatar { display: grid; width: 2rem; height: 2rem; place-items: center; border-radius: 50%; background: #dfe8ff; color: #3159d9; font-weight: 800; }
.member-grid strong, .member-grid small { display: block; }
.member-grid strong { color: var(--ink); font-size: 0.75rem; }
.member-grid small { color: var(--muted); font-size: 0.65rem; }

.empty-state { grid-column: 1 / -1; padding: 4rem 1rem; color: var(--muted); text-align: center; }
.empty-state i { color: #3159d9; font-size: 3rem; }
.empty-state h3 { margin: 0.5rem 0 0; color: var(--ink); }
.empty-state p { margin: 0.3rem 0 0; }
.existing-file { display: flex; align-items: center; gap: 0.3rem; padding-top: 0.45rem; color: #5f6b81; font-size: 0.76rem; }
.committee-form-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin: 1.2rem 0 0.7rem; }
.committee-form-heading h5 { margin: 0; }
.committee-form-heading p { margin: 0.2rem 0 0; color: var(--muted); font-size: 0.74rem; }
.committee-member-form { padding: 0.75rem; margin: 0 0 0.6rem; border: 1px solid #e3e8f0; border-radius: 0.75rem; background: #fafbfc; }

@media (max-width: 991.98px) {
  .summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .requirements-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .member-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 767.98px) {
  .personnel-hero { align-items: flex-start; flex-direction: column; padding: 1.35rem; }
  .personnel-tabs { overflow-x: auto; }
  .personnel-tabs button { flex: 0 0 auto; }
  .matrix-toolbar { grid-template-columns: 1fr 1fr; }
  .matrix-search { grid-column: 1 / -1; }
  .requirements-grid, .member-grid { grid-template-columns: 1fr; }
}

@media (max-width: 479.98px) {
  .summary-grid, .matrix-toolbar { grid-template-columns: 1fr; }
  .section-heading { align-items: flex-start; flex-direction: column; }
}
</style>
