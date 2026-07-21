<script>
import axios from "axios";
import Multiselect from "@vueform/multiselect";
import Layout from "../../layouts/main.vue";
import InfirmaryHelpButton from "../../components/infirmary/help-button.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import {
  confirmInfirmaryAction,
  confirmInfirmaryCancel,
  formatInfirmaryDateTime,
  formatInfirmaryError,
  humanizeInfirmaryStatus,
  normalizeOptions,
  showInfirmaryError,
  showInfirmarySuccess,
  showInfirmaryWarning,
  toInputDateTime,
} from "../../components/infirmary/module-utils";
import { getPdfMake } from "../../utils/pdfmake";
import "@vueform/multiselect/themes/default.css";

export default {
  components: {
    InfirmaryHelpButton,
    Layout,
    LoadingState,
    Multiselect,
  },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      catalogs: {
        staff: [],
        users: [],
        medications: [],
        dependencies: [],
        attention_categories: [],
        accident_location_options: [],
        treatment_category_options: [],
        physical_treatment_options: [],
        treatment_derivation_options: [],
        treatment_derivation_support_options: [],
        referral_options: [],
        follow_up_status_options: [],
        priority_options: [],
        capabilities: {},
      },
      filters: this.emptyFilters(),
      attentions: [],
      pagination: { current_page: 1, total: 0, per_page: 12 },
      showModal: false,
      showViewModal: false,
      selectedAttention: null,
      form: this.emptyForm(),
    };
  },
  computed: {
    canCreate() {
      return Boolean(this.catalogs.capabilities?.can_create_attention);
    },
    canEdit() {
      return Boolean(this.catalogs.capabilities?.can_edit_attention);
    },
    canDelete() {
      return Boolean(this.catalogs.capabilities?.can_delete_attention);
    },
    canExport() {
      return Boolean(this.catalogs.capabilities?.can_export);
    },
    isEditing() {
      return Boolean(this.form.id);
    },
    isFormAccident() {
      return this.isAccidentCategory(this.form.attention_category);
    },
    staffOptions() {
      return (this.catalogs.staff || []).map((item) => ({
        value: item.id,
        label: [item.full_name, item.rut, item.cargo?.name].filter(Boolean).join(" · "),
      }));
    },
    categoryOptions() {
      return normalizeOptions(this.catalogs.attention_categories, true, "Todas");
    },
    formCategoryOptions() {
      return normalizeOptions(this.catalogs.attention_categories);
    },
    priorityOptions() {
      return normalizeOptions(this.catalogs.priority_options);
    },
    accidentLocationOptions() {
      return normalizeOptions(this.catalogs.accident_location_options);
    },
    dependencyOptions() {
      return [{ value: null, text: "Seleccione dependencia" }].concat(
        (this.catalogs.dependencies || []).map((item) => ({
          value: item.id,
          text: item.label || item.name,
        }))
      );
    },
    treatmentCategoryOptions() {
      return normalizeOptions(this.catalogs.treatment_category_options);
    },
    physicalTreatmentOptions() {
      return normalizeOptions(this.catalogs.physical_treatment_options);
    },
    derivationOptions() {
      return normalizeOptions(this.catalogs.treatment_derivation_options, true, "Seleccione");
    },
    derivationSupportOptions() {
      return normalizeOptions(this.catalogs.treatment_derivation_support_options);
    },
    medicationOptions() {
      return [{ value: null, text: "Seleccione medicamento" }].concat(
        (this.catalogs.medications || []).map((item) => ({
          value: item.id,
          text: `${item.commercial_name || item.name} · Stock ${item.current_stock ?? 0} ${item.unit || ""}`,
        }))
      );
    },
    referralOptions() {
      return normalizeOptions(this.catalogs.referral_options);
    },
    userOptions() {
      return [{ value: null, text: "Responsable actual" }].concat(
        (this.catalogs.users || []).map((item) => ({ value: item.id, text: item.name }))
      );
    },
    followUpStatusOptions() {
      return normalizeOptions(this.catalogs.follow_up_status_options);
    },
    pageSummary() {
      return {
        staff: new Set(this.attentions.map((item) => item.staff_id).filter(Boolean)).size,
        urgent: this.attentions.filter((item) => ["alta", "emergencia"].includes(item.priority)).length,
      };
    },
  },
  watch: {
    "form.attention_category"(value) {
      if (!this.isAccidentCategory(value)) {
        this.form.accident_location_type = null;
        this.form.dependency_id = null;
        this.form.accident_circumstance = "";
      } else if (!this.form.accident_location_type) {
        this.form.accident_location_type = "colegio";
      }
    },
    "form.accident_location_type"(value) {
      if (value !== "colegio") this.form.dependency_id = null;
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadAttentions();
  },
  methods: {
    formatInfirmaryDateTime,
    humanizeInfirmaryStatus,
    normalizeOptions,
    emptyFilters() {
      return {
        search: "",
        staff_id: null,
        attention_category: null,
        priority: null,
        from: "",
        to: "",
      };
    },
    emptyTreatment() {
      return {
        treatment_categories: ["fisico"],
        treatment_types: [],
        derivation_type: null,
        derivation_support_teams: [],
        treatment_other: null,
        medication_id: null,
        medication_quantity: 1,
        blood_pressure: "",
        pulse: null,
        respiratory_rate: null,
        temperature: null,
        oxygen_saturation: null,
        weight: null,
        height: null,
        vital_signs_notes: "",
        emotional_support_required: false,
        emotional_comment: "",
        emotional_support_type: "",
        emotional_duration_minutes: null,
        emotional_professional_id: null,
        other_treatments: "",
        notes: "",
      };
    },
    emptyReferral() {
      return {
        referral_type: "regresa_a_sala",
        referred_at: toInputDateTime(new Date().toISOString()),
        responsible_user_id: null,
        responsible_name: "",
        reason: "",
        observations: "",
        result: "",
      };
    },
    emptyFollowUp() {
      return {
        followed_at: toInputDateTime(new Date().toISOString()),
        responsible_user_id: null,
        comment: "",
        status: "pendiente",
        next_review_at: "",
      };
    },
    emptyForm() {
      const now = toInputDateTime(new Date().toISOString());
      return {
        id: null,
        staff_id: null,
        attention_category: "control_signos_vitales",
        accident_location_type: null,
        occurred_at: now,
        attended_at: now,
        dependency_id: null,
        accompanied_by_type: "sin_acompanante",
        accompanied_by_staff_id: null,
        accompanied_by_name: null,
        consultation_reason: "",
        accident_circumstance: "",
        logbook: "",
        initial_description: "",
        observations: "",
        priority: "media",
        treatments: [this.emptyTreatment()],
        referrals: [],
        calls: [],
        follow_ups: [],
      };
    },
    async loadCatalogs() {
      try {
        const response = await axios.get("/api/infirmary/catalogs");
        this.catalogs = response.data;
      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudieron cargar los datos del módulo.");
      }
    },
    async loadAttentions(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/infirmary/staff-attentions", {
          params: { page, ...this.filters },
        });
        this.attentions = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page || 1,
          total: response.data.total || 0,
          per_page: response.data.per_page || 12,
        };
      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudieron cargar las atenciones a funcionarios.");
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.filters = this.emptyFilters();
      this.loadAttentions(1);
    },
    isAccidentCategory(value) {
      return ["accidente_menor", "accidente_mayor"].includes(value);
    },
    optionLabel(options, value) {
      return (options || []).find((item) => String(item.value) === String(value))?.text
        || humanizeInfirmaryStatus(value);
    },
    attentionCategoryLabel(value) {
      return this.optionLabel(this.formCategoryOptions, value);
    },
    staffName(attention) {
      return attention?.staff?.full_name || attention?.staff_full_name_snapshot || "Funcionario sin nombre";
    },
    staffRut(attention) {
      return attention?.staff?.rut || attention?.staff_rut_snapshot || "Sin RUT";
    },
    staffCargo(attention) {
      return attention?.staff?.cargo?.name || attention?.staff_cargo_snapshot || "Sin cargo registrado";
    },
    attentionCorrelative(attention) {
      return String(attention?.correlative_number || attention?.id || "").padStart(5, "0");
    },
    priorityVariant(priority) {
      return {
        baja: "secondary",
        media: "primary",
        alta: "warning",
        emergencia: "danger",
      }[priority] || "secondary";
    },
    treatmentHasCategory(treatment, category) {
      return (treatment.treatment_categories || []).includes(category);
    },
    treatmentHasMedication(treatment) {
      return (treatment.treatment_types || []).some((type) =>
        ["administracion_medicamento", "medicamento_sos"].includes(type)
      );
    },
    treatmentSummary(treatment) {
      const parts = [];
      if ((treatment.treatment_types || []).length) {
        parts.push((treatment.treatment_types || [])
          .map((value) => this.optionLabel(this.physicalTreatmentOptions, value))
          .join(", "));
      }
      if (treatment.medication) {
        parts.push(`${treatment.medication.commercial_name || treatment.medication.name} (${treatment.medication_quantity || 0})`);
      }
      if (treatment.blood_pressure) parts.push(`PA ${treatment.blood_pressure}`);
      if (treatment.pulse) parts.push(`Pulso ${treatment.pulse}`);
      if (treatment.temperature) parts.push(`${treatment.temperature} °C`);
      if (treatment.oxygen_saturation) parts.push(`Sat. ${treatment.oxygen_saturation}%`);
      if (treatment.derivation_type) {
        parts.push(`Derivación: ${this.optionLabel(this.derivationOptions, treatment.derivation_type)}`);
      }
      if (treatment.emotional_comment) parts.push(`Contención: ${treatment.emotional_comment}`);
      if (treatment.other_treatments) parts.push(treatment.other_treatments);
      return parts.filter(Boolean).join(" · ") || treatment.notes || "Sin detalle";
    },
    openCreate() {
      this.form = this.emptyForm();
      this.showModal = true;
    },
    async fetchDetail(attention) {
      const id = typeof attention === "object" ? attention.id : attention;
      const response = await axios.get(`/api/infirmary/staff-attentions/${id}`);
      return response.data.data;
    },
    async openView(attention) {
      try {
        this.selectedAttention = await this.fetchDetail(attention);
        this.showViewModal = true;
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo cargar la ficha del funcionario."));
      }
    },
    inferTreatmentCategories(treatment) {
      const categories = new Set(treatment.treatment_categories || []);
      if ((treatment.treatment_types || []).length || treatment.medication_id) categories.add("fisico");
      if (treatment.emotional_support_required || treatment.emotional_comment) categories.add("emocional");
      if (treatment.derivation_type || (treatment.derivation_support_teams || []).length) categories.add("derivacion");
      if (treatment.blood_pressure || treatment.pulse || treatment.respiratory_rate || treatment.temperature || treatment.oxygen_saturation || treatment.weight || treatment.height) categories.add("csv");
      if (treatment.other_treatments || treatment.treatment_other) categories.add("otro");
      return Array.from(categories);
    },
    async openEdit(attention) {
      try {
        const detail = await this.fetchDetail(attention);
        this.form = {
          ...this.emptyForm(),
          id: detail.id,
          staff_id: detail.staff_id,
          attention_category: detail.attention_category,
          accident_location_type: detail.accident_location_type,
          occurred_at: toInputDateTime(detail.occurred_at || detail.attended_at),
          attended_at: toInputDateTime(detail.attended_at),
          dependency_id: detail.dependency_id || null,
          consultation_reason: detail.consultation_reason || "",
          accident_circumstance: detail.accident_circumstance || "",
          logbook: detail.logbook || "",
          initial_description: detail.initial_description || "",
          observations: detail.observations || "",
          priority: detail.priority || "media",
          treatments: (detail.treatments?.length ? detail.treatments : [this.emptyTreatment()]).map((item) => ({
            ...this.emptyTreatment(),
            ...item,
            treatment_categories: this.inferTreatmentCategories(item),
            treatment_types: item.treatment_types || [],
            derivation_support_teams: item.derivation_support_teams || [],
          })),
          referrals: (detail.referrals || []).map((item) => ({
            ...item,
            referred_at: toInputDateTime(item.referred_at),
          })),
          calls: [],
          follow_ups: (detail.follow_ups || []).map((item) => ({
            ...item,
            followed_at: toInputDateTime(item.followed_at),
            next_review_at: toInputDateTime(item.next_review_at),
          })),
        };
        this.showViewModal = false;
        this.showModal = true;
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo cargar la atención."));
      }
    },
    addTreatment() {
      this.form.treatments.push(this.emptyTreatment());
    },
    removeTreatment(index) {
      this.form.treatments.splice(index, 1);
      if (!this.form.treatments.length) this.addTreatment();
    },
    addReferral() {
      this.form.referrals.push(this.emptyReferral());
    },
    removeReferral(index) {
      this.form.referrals.splice(index, 1);
    },
    addFollowUp() {
      this.form.follow_ups.push(this.emptyFollowUp());
    },
    removeFollowUp(index) {
      this.form.follow_ups.splice(index, 1);
    },
    async cancelModal() {
      const result = await confirmInfirmaryCancel("la atención del funcionario");
      if (result.isConfirmed) this.showModal = false;
    },
    normalizedPayload() {
      const accident = this.isFormAccident;
      return {
        ...this.form,
        accident_location_type: accident ? (this.form.accident_location_type || "colegio") : null,
        dependency_id: accident && this.form.accident_location_type === "colegio" ? this.form.dependency_id : null,
        accident_circumstance: accident ? (this.form.accident_circumstance || null) : null,
        accompanied_by_type: "sin_acompanante",
        accompanied_by_staff_id: null,
        accompanied_by_name: null,
        treatments: this.form.treatments.map((item) => ({
          ...item,
          treatment_categories: item.treatment_categories || [],
          treatment_types: this.treatmentHasCategory(item, "fisico") ? item.treatment_types || [] : [],
          medication_id: this.treatmentHasMedication(item) ? item.medication_id || null : null,
          medication_quantity: this.treatmentHasMedication(item) ? item.medication_quantity || null : null,
          derivation_type: this.treatmentHasCategory(item, "derivacion") ? item.derivation_type || null : null,
          derivation_support_teams: this.treatmentHasCategory(item, "derivacion") ? item.derivation_support_teams || [] : [],
          emotional_support_required: this.treatmentHasCategory(item, "emocional"),
          emotional_comment: this.treatmentHasCategory(item, "emocional") ? item.emotional_comment || null : null,
          other_treatments: this.treatmentHasCategory(item, "otro") ? item.other_treatments || null : null,
        })),
        referrals: this.form.referrals.map((item) => ({
          ...item,
          responsible_user_id: item.responsible_user_id || null,
          responsible_name: item.responsible_name || null,
        })),
        calls: [],
        follow_ups: this.form.follow_ups.map((item) => ({
          ...item,
          responsible_user_id: item.responsible_user_id || null,
          next_review_at: item.next_review_at || null,
        })),
      };
    },
    async save() {
      if (!this.form.staff_id) {
        await showInfirmaryWarning("Selecciona el funcionario que recibió la atención.");
        return;
      }
      if (!String(this.form.consultation_reason || "").trim()) {
        await showInfirmaryWarning("Ingresa el motivo de consulta.");
        return;
      }
      if (!this.form.occurred_at || !this.form.attended_at) {
        await showInfirmaryWarning("Completa las fechas de ocurrencia y registro.");
        return;
      }
      if (new Date(this.form.occurred_at).getTime() > new Date(this.form.attended_at).getTime()) {
        await showInfirmaryWarning("La fecha de ocurrencia no puede ser posterior a la fecha de registro.");
        return;
      }
      if (this.isFormAccident && this.form.accident_location_type === "colegio" && !this.form.dependency_id) {
        await showInfirmaryWarning("Selecciona la dependencia donde ocurrió el accidente.");
        return;
      }
      if (this.form.treatments.some((item) => this.treatmentHasMedication(item) && (!item.medication_id || !item.medication_quantity))) {
        await showInfirmaryWarning("Selecciona el medicamento y la cantidad administrada.");
        return;
      }

      this.saving = true;
      try {
        const payload = this.normalizedPayload();
        if (this.isEditing) {
          await axios.put(`/api/infirmary/staff-attentions/${this.form.id}`, payload);
        } else {
          await axios.post("/api/infirmary/staff-attentions", payload);
        }
        this.showModal = false;
        await this.loadAttentions(this.pagination.current_page || 1);
        await showInfirmarySuccess(this.isEditing
          ? "Atención a funcionario actualizada correctamente."
          : "Atención a funcionario registrada correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo guardar la atención."));
      } finally {
        this.saving = false;
      }
    },
    async remove(attention) {
      const confirmation = await confirmInfirmaryAction({
        title: "Eliminar atención",
        text: `Se eliminará la atención de ${this.staffName(attention)} y sus registros relacionados.`,
        confirmButtonText: "Eliminar",
      });
      if (!confirmation.isConfirmed) return;

      try {
        await axios.delete(`/api/infirmary/staff-attentions/${attention.id}`);
        await this.loadAttentions(this.pagination.current_page || 1);
        await showInfirmarySuccess("Atención eliminada correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo eliminar la atención."));
      }
    },
    async exportPdf(attention) {
      try {
        const detail = await this.fetchDetail(attention);
        const pdfMake = getPdfMake();
        const treatmentLines = (detail.treatments || []).map((item, index) =>
          `${index + 1}. ${this.treatmentSummary(item)}`
        );
        const referralLines = (detail.referrals || []).map((item) =>
          `${formatInfirmaryDateTime(item.referred_at)} · ${humanizeInfirmaryStatus(item.referral_type)} · ${item.result || item.reason || "Sin detalle"}`
        );

        pdfMake.createPdf({
          pageSize: "LETTER",
          pageMargins: [40, 40, 40, 44],
          content: [
            { text: "Ficha de Atención a Funcionarios", style: "title" },
            { text: `Registro N° ${this.attentionCorrelative(detail)}`, style: "subtitle" },
            {
              table: {
                widths: ["30%", "*"],
                body: [
                  ["Funcionario", this.staffName(detail)],
                  ["RUT", this.staffRut(detail)],
                  ["Cargo", this.staffCargo(detail)],
                  ["Fecha de atención", formatInfirmaryDateTime(detail.attended_at)],
                  ["Categoría", this.attentionCategoryLabel(detail.attention_category)],
                  ["Prioridad", humanizeInfirmaryStatus(detail.priority)],
                ],
              },
              layout: "lightHorizontalLines",
              margin: [0, 0, 0, 14],
            },
            { text: "Motivo de consulta", style: "section" },
            { text: detail.consultation_reason || "-", margin: [0, 0, 0, 10] },
            { text: "Detalle clínico", style: "section" },
            { text: [detail.initial_description, detail.accident_circumstance, detail.logbook].filter(Boolean).join("\n") || "-", margin: [0, 0, 0, 10] },
            { text: "Tratamientos y signos vitales", style: "section" },
            { text: treatmentLines.join("\n") || "Sin tratamientos registrados.", margin: [0, 0, 0, 10] },
            { text: "Derivaciones", style: "section" },
            { text: referralLines.join("\n") || "Sin derivaciones registradas.", margin: [0, 0, 0, 10] },
            { text: "Observaciones", style: "section" },
            { text: detail.observations || "-" },
          ],
          styles: {
            title: { fontSize: 18, bold: true, color: "#2a3042" },
            subtitle: { fontSize: 9, color: "#74788d", margin: [0, 3, 0, 16] },
            section: { fontSize: 11, bold: true, color: "#4056c8", margin: [0, 7, 0, 4] },
          },
          defaultStyle: { fontSize: 9, color: "#4b5563" },
        }).download(`atencion_funcionario_${this.attentionCorrelative(detail)}.pdf`);
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo generar el PDF."));
      }
    },
  },
};
</script>

<template>
  <Layout>
    <div class="staff-attentions-page">
      <div class="staff-attentions-header mb-3">
        <div>
          <div class="staff-attentions-eyebrow">Enfermería</div>
          <h4 class="mb-1">Atención a funcionarios</h4>
          <p class="text-muted mb-0">Registro clínico independiente para el personal del establecimiento.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <InfirmaryHelpButton
            title="Ayuda: atención a funcionarios"
            text="Esta vista registra exclusivamente atenciones realizadas a funcionarios. Los registros no se mezclan con las fichas de estudiantes."
          />
          <BButton v-if="canCreate" variant="primary" @click="openCreate">
            <i class="mdi mdi-plus me-1"></i>Nueva atención
          </BButton>
        </div>
      </div>

      <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <div class="staff-metric-card">
            <span>Atenciones registradas</span>
            <strong>{{ pagination.total }}</strong>
            <small>Total histórico según filtros</small>
          </div>
        </div>
        <div class="col-md-4">
          <div class="staff-metric-card staff-metric-card--warning">
            <span>Funcionarios visibles</span>
            <strong>{{ pageSummary.staff }}</strong>
            <small>Personas con fichas en esta página</small>
          </div>
        </div>
        <div class="col-md-4">
          <div class="staff-metric-card staff-metric-card--danger">
            <span>Prioridad alta</span>
            <strong>{{ pageSummary.urgent }}</strong>
            <small>Altas o emergencias visibles</small>
          </div>
        </div>
      </div>

      <BCard class="mb-3 staff-filter-card">
        <div class="row g-2 align-items-end">
          <div class="col-lg-3">
            <label class="form-label">Buscar</label>
            <BFormInput v-model="filters.search" placeholder="Nombre, RUT, cargo o motivo" @keyup.enter="loadAttentions(1)" />
          </div>
          <div class="col-lg-3">
            <label class="form-label">Funcionario</label>
            <Multiselect
              v-model="filters.staff_id"
              :options="staffOptions"
              value-prop="value"
              label="label"
              searchable
              clearable
              placeholder="Todos los funcionarios"
              no-results-text="Sin coincidencias"
            />
          </div>
          <div class="col-md-4 col-lg-2">
            <label class="form-label">Categoría</label>
            <BFormSelect v-model="filters.attention_category" :options="categoryOptions" />
          </div>
          <div class="col-md-4 col-lg-2 d-flex gap-2">
            <BButton variant="primary" class="flex-grow-1" @click="loadAttentions(1)">Filtrar</BButton>
            <BButton variant="outline-secondary" @click="resetFilters" aria-label="Limpiar filtros">
              <i class="mdi mdi-filter-remove-outline"></i>
            </BButton>
          </div>
          <div class="col-md-3">
            <label class="form-label">Prioridad</label>
            <BFormSelect v-model="filters.priority" :options="normalizeOptions(catalogs.priority_options, true, 'Todas')" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Desde</label>
            <BFormInput v-model="filters.from" type="date" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Hasta</label>
            <BFormInput v-model="filters.to" type="date" />
          </div>
        </div>
      </BCard>

      <BCard class="staff-list-card">
        <LoadingState v-if="loading" message="Cargando atenciones a funcionarios..." compact />
        <div v-else-if="!attentions.length" class="staff-empty-state">
          <i class="bx bx-plus-medical"></i>
          <h5>Sin atenciones de funcionarios</h5>
          <p>No existen registros que coincidan con los filtros seleccionados.</p>
          <BButton v-if="canCreate" variant="outline-primary" @click="openCreate">Registrar primera atención</BButton>
        </div>
        <div v-else class="table-responsive">
          <table class="table align-middle staff-attentions-table mb-0">
            <thead>
              <tr>
                <th>N° correlativo</th>
                <th>Funcionario</th>
                <th>Atención</th>
                <th>Motivo</th>
                <th>Prioridad</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="attention in attentions" :key="attention.id">
                <td><span class="fw-semibold">N° {{ attentionCorrelative(attention) }}</span></td>
                <td>
                  <div class="staff-person-cell">
                    <div class="staff-person-avatar">{{ staffName(attention).charAt(0) }}</div>
                    <div>
                      <strong>{{ staffName(attention) }}</strong>
                      <span>{{ staffRut(attention) }} · {{ staffCargo(attention) }}</span>
                    </div>
                  </div>
                </td>
                <td>
                  <strong>{{ attentionCategoryLabel(attention.attention_category) }}</strong>
                  <span class="d-block text-muted small">{{ formatInfirmaryDateTime(attention.attended_at) }}</span>
                </td>
                <td class="staff-reason-cell">{{ attention.consultation_reason }}</td>
                <td><BBadge :variant="priorityVariant(attention.priority)">{{ humanizeInfirmaryStatus(attention.priority) }}</BBadge></td>
                <td class="text-end">
                  <div class="staff-action-group">
                    <button type="button" class="cnsc-action-btn cnsc-action-btn--view" title="Ver ficha" @click="openView(attention)">
                      <i class="mdi mdi-eye-outline"></i>
                    </button>
                    <button v-if="canEdit" type="button" class="cnsc-action-btn cnsc-action-btn--edit" title="Editar" @click="openEdit(attention)">
                      <i class="mdi mdi-pencil-outline"></i>
                    </button>
                    <button v-if="canExport" type="button" class="cnsc-action-btn cnsc-action-btn--delete" title="Exportar PDF" @click="exportPdf(attention)">
                      <i class="mdi mdi-file-pdf-box"></i>
                    </button>
                    <button v-if="canDelete" type="button" class="cnsc-action-btn cnsc-action-btn--delete" title="Eliminar" @click="remove(attention)">
                      <i class="mdi mdi-trash-can-outline"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="attentions.length" class="d-flex justify-content-end mt-3">
          <BPagination
            v-model="pagination.current_page"
            :total-rows="pagination.total"
            :per-page="pagination.per_page"
            @update:model-value="loadAttentions"
          />
        </div>
      </BCard>

      <BModal v-model="showViewModal" title="Ficha de atención a funcionario" size="xl" hide-footer scrollable>
        <div v-if="selectedAttention" class="staff-detail">
          <div class="staff-detail-hero">
            <div class="staff-person-avatar staff-person-avatar--large">{{ staffName(selectedAttention).charAt(0) }}</div>
            <div class="flex-grow-1">
              <span>Funcionario atendido</span>
              <h5>{{ staffName(selectedAttention) }}</h5>
              <p>{{ staffRut(selectedAttention) }} · {{ staffCargo(selectedAttention) }}</p>
            </div>
            <div class="text-end">
              <strong>N° {{ attentionCorrelative(selectedAttention) }}</strong>
              <span class="d-block">{{ formatInfirmaryDateTime(selectedAttention.attended_at) }}</span>
            </div>
          </div>

          <div class="staff-detail-grid">
            <div><span>Categoría</span><strong>{{ attentionCategoryLabel(selectedAttention.attention_category) }}</strong></div>
            <div><span>Prioridad</span><strong>{{ humanizeInfirmaryStatus(selectedAttention.priority) }}</strong></div>
            <div><span>Dependencia</span><strong>{{ selectedAttention.dependency?.name || "No aplica" }}</strong></div>
            <div><span>Registrado por</span><strong>{{ selectedAttention.attended_by?.name || "Automático" }}</strong></div>
          </div>

          <section class="staff-detail-section">
            <h6>Detalle clínico</h6>
            <div class="staff-detail-copy">
              <div><span>Motivo de consulta</span><p>{{ selectedAttention.consultation_reason || "-" }}</p></div>
              <div v-if="selectedAttention.accident_circumstance"><span>Circunstancia</span><p>{{ selectedAttention.accident_circumstance }}</p></div>
              <div v-if="selectedAttention.initial_description"><span>Descripción inicial</span><p>{{ selectedAttention.initial_description }}</p></div>
              <div v-if="selectedAttention.logbook"><span>Bitácora</span><p>{{ selectedAttention.logbook }}</p></div>
              <div v-if="selectedAttention.observations"><span>Observaciones</span><p>{{ selectedAttention.observations }}</p></div>
            </div>
          </section>

          <section class="staff-detail-section">
            <h6>Tratamientos y signos vitales</h6>
            <div v-if="selectedAttention.treatments?.length" class="staff-detail-list">
              <div v-for="(treatment, index) in selectedAttention.treatments" :key="treatment.id || index">
                <strong>Tratamiento {{ index + 1 }}</strong>
                <p>{{ treatmentSummary(treatment) }}</p>
              </div>
            </div>
            <p v-else class="text-muted mb-0">Sin tratamientos registrados.</p>
          </section>

          <div class="row g-3">
            <div class="col-lg-6">
              <section class="staff-detail-section h-100">
                <h6>Derivaciones</h6>
                <div v-if="selectedAttention.referrals?.length" class="staff-detail-list">
                  <div v-for="referral in selectedAttention.referrals" :key="referral.id">
                    <strong>{{ humanizeInfirmaryStatus(referral.referral_type) }}</strong>
                    <span>{{ formatInfirmaryDateTime(referral.referred_at) }}</span>
                    <p>{{ referral.result || referral.reason || "Sin detalle" }}</p>
                  </div>
                </div>
                <p v-else class="text-muted mb-0">Sin derivaciones.</p>
              </section>
            </div>
            <div class="col-lg-6">
              <section class="staff-detail-section h-100">
                <h6>Seguimiento</h6>
                <div v-if="selectedAttention.follow_ups?.length" class="staff-detail-list">
                  <div v-for="followUp in selectedAttention.follow_ups" :key="followUp.id">
                    <strong>{{ humanizeInfirmaryStatus(followUp.status) }}</strong>
                    <span>{{ formatInfirmaryDateTime(followUp.followed_at) }}</span>
                    <p>{{ followUp.comment }}</p>
                  </div>
                </div>
                <p v-else class="text-muted mb-0">Sin seguimiento.</p>
              </section>
            </div>
          </div>

          <div class="d-flex flex-wrap justify-content-end gap-2">
            <BButton variant="outline-secondary" @click="showViewModal = false">Cerrar</BButton>
            <BButton v-if="canExport" variant="outline-danger" @click="exportPdf(selectedAttention)">
              <i class="mdi mdi-file-pdf-box me-1"></i>PDF
            </BButton>
            <BButton v-if="canEdit" variant="primary" @click="openEdit(selectedAttention)">Editar ficha</BButton>
          </div>
        </div>
      </BModal>

      <BModal
        v-model="showModal"
        :title="isEditing ? 'Editar atención a funcionario' : 'Nueva atención a funcionario'"
        size="xl"
        hide-footer
        scrollable
      >
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Funcionario <span class="text-danger">*</span></label>
            <Multiselect
              v-model="form.staff_id"
              :options="staffOptions"
              value-prop="value"
              label="label"
              searchable
              placeholder="Buscar por nombre, RUT o cargo"
              no-results-text="Sin coincidencias"
            />
          </div>
          <div class="col-md-4">
            <label class="form-label">Fecha de ocurrencia</label>
            <BFormInput v-model="form.occurred_at" type="datetime-local" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Fecha de registro</label>
            <BFormInput v-model="form.attended_at" type="datetime-local" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Categoría</label>
            <BFormSelect v-model="form.attention_category" :options="formCategoryOptions" />
          </div>
          <div v-if="isFormAccident" class="col-md-4">
            <label class="form-label">Lugar del accidente</label>
            <BFormSelect v-model="form.accident_location_type" :options="accidentLocationOptions" />
          </div>
          <div v-if="isFormAccident && form.accident_location_type === 'colegio'" class="col-md-4">
            <label class="form-label">Dependencia</label>
            <BFormSelect v-model="form.dependency_id" :options="dependencyOptions" />
          </div>
          <div class="col-12">
            <label class="form-label">Motivo de consulta <span class="text-danger">*</span></label>
            <BFormTextarea v-model="form.consultation_reason" rows="3" placeholder="Describe el motivo principal de la atención" />
          </div>
          <div v-if="isFormAccident" class="col-12">
            <label class="form-label">Circunstancia del accidente</label>
            <BFormTextarea v-model="form.accident_circumstance" rows="3" placeholder="Describe cómo ocurrió" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Descripción inicial</label>
            <BFormTextarea v-model="form.initial_description" rows="3" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Bitácora</label>
            <BFormTextarea v-model="form.logbook" rows="3" placeholder="Antecedentes o indicaciones relevantes" />
          </div>

          <div class="col-12">
            <div class="staff-form-section-heading">
              <div><strong>Tratamientos y signos vitales</strong><span>Registra sólo los bloques que correspondan.</span></div>
              <BButton variant="outline-primary" size="sm" @click="addTreatment">Agregar tratamiento</BButton>
            </div>
            <div v-for="(treatment, index) in form.treatments" :key="`treatment-${index}`" class="staff-treatment-card">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <strong>Tratamiento {{ index + 1 }}</strong>
                <BButton variant="outline-danger" size="sm" @click="removeTreatment(index)">Quitar</BButton>
              </div>
              <div class="mb-3">
                <label class="form-label d-block">Categorías</label>
                <div class="staff-check-grid">
                  <BFormCheckbox
                    v-for="option in treatmentCategoryOptions"
                    :key="`${index}-${option.value}`"
                    v-model="treatment.treatment_categories"
                    :value="option.value"
                  >{{ option.text }}</BFormCheckbox>
                </div>
              </div>
              <div v-if="treatmentHasCategory(treatment, 'fisico')" class="staff-treatment-block">
                <strong>Atención física</strong>
                <div class="staff-check-grid mt-2">
                  <BFormCheckbox
                    v-for="option in physicalTreatmentOptions"
                    :key="`${index}-physical-${option.value}`"
                    v-model="treatment.treatment_types"
                    :value="option.value"
                  >{{ option.text }}</BFormCheckbox>
                </div>
                <div v-if="treatmentHasMedication(treatment)" class="row g-2 mt-2">
                  <div class="col-md-8"><label class="form-label">Medicamento</label><BFormSelect v-model="treatment.medication_id" :options="medicationOptions" /></div>
                  <div class="col-md-4"><label class="form-label">Cantidad</label><BFormInput v-model="treatment.medication_quantity" type="number" min="0.01" step="0.01" /></div>
                </div>
              </div>
              <div v-if="treatmentHasCategory(treatment, 'csv')" class="staff-treatment-block">
                <strong>Control de signos vitales</strong>
                <div class="row g-2 mt-2">
                  <div class="col-md-3"><label class="form-label">Presión arterial</label><BFormInput v-model="treatment.blood_pressure" placeholder="120/80" /></div>
                  <div class="col-md-3"><label class="form-label">Pulso</label><BFormInput v-model="treatment.pulse" type="number" min="1" /></div>
                  <div class="col-md-3"><label class="form-label">Temperatura °C</label><BFormInput v-model="treatment.temperature" type="number" min="20" max="45" step="0.1" /></div>
                  <div class="col-md-3"><label class="form-label">Saturación %</label><BFormInput v-model="treatment.oxygen_saturation" type="number" min="1" max="100" /></div>
                </div>
              </div>
              <div v-if="treatmentHasCategory(treatment, 'emocional')" class="staff-treatment-block">
                <strong>Contención emocional</strong>
                <BFormTextarea v-model="treatment.emotional_comment" class="mt-2" rows="2" placeholder="Comentario de la intervención" />
              </div>
              <div v-if="treatmentHasCategory(treatment, 'derivacion')" class="staff-treatment-block">
                <strong>Derivación</strong>
                <div class="row g-2 mt-2">
                  <div class="col-md-5"><label class="form-label">Destino</label><BFormSelect v-model="treatment.derivation_type" :options="derivationOptions" /></div>
                  <div class="col-md-7">
                    <label class="form-label d-block">Equipo de apoyo</label>
                    <div class="staff-check-grid">
                      <BFormCheckbox
                        v-for="option in derivationSupportOptions"
                        :key="`${index}-support-${option.value}`"
                        v-model="treatment.derivation_support_teams"
                        :value="option.value"
                      >{{ option.text }}</BFormCheckbox>
                    </div>
                  </div>
                </div>
              </div>
              <div v-if="treatmentHasCategory(treatment, 'otro')" class="staff-treatment-block">
                <strong>Otro tratamiento</strong>
                <BFormTextarea v-model="treatment.other_treatments" class="mt-2" rows="2" />
              </div>
              <div class="mt-3"><label class="form-label">Notas clínicas</label><BFormTextarea v-model="treatment.notes" rows="2" /></div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="staff-form-section-heading">
              <div><strong>Derivaciones</strong><span>Destino y resultado de la derivación.</span></div>
              <BButton variant="outline-primary" size="sm" @click="addReferral">Agregar</BButton>
            </div>
            <div v-for="(referral, index) in form.referrals" :key="`referral-${index}`" class="staff-repeat-card">
              <div class="row g-2">
                <div class="col-md-6"><label class="form-label">Destino</label><BFormSelect v-model="referral.referral_type" :options="referralOptions" /></div>
                <div class="col-md-6"><label class="form-label">Fecha</label><BFormInput v-model="referral.referred_at" type="datetime-local" /></div>
                <div class="col-12"><label class="form-label">Motivo</label><BFormTextarea v-model="referral.reason" rows="2" /></div>
                <div class="col-12"><label class="form-label">Resultado</label><BFormInput v-model="referral.result" /></div>
                <div class="col-12 text-end"><BButton variant="outline-danger" size="sm" @click="removeReferral(index)">Quitar</BButton></div>
              </div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="staff-form-section-heading">
              <div><strong>Seguimiento</strong><span>Controles posteriores de la atención.</span></div>
              <BButton variant="outline-primary" size="sm" @click="addFollowUp">Agregar</BButton>
            </div>
            <div v-for="(followUp, index) in form.follow_ups" :key="`follow-${index}`" class="staff-repeat-card">
              <div class="row g-2">
                <div class="col-md-6"><label class="form-label">Fecha</label><BFormInput v-model="followUp.followed_at" type="datetime-local" /></div>
                <div class="col-md-6"><label class="form-label">Estado</label><BFormSelect v-model="followUp.status" :options="followUpStatusOptions" /></div>
                <div class="col-12"><label class="form-label">Comentario</label><BFormTextarea v-model="followUp.comment" rows="2" /></div>
                <div class="col-md-8"><label class="form-label">Próxima revisión</label><BFormInput v-model="followUp.next_review_at" type="datetime-local" /></div>
                <div class="col-md-4 d-flex align-items-end justify-content-end"><BButton variant="outline-danger" size="sm" @click="removeFollowUp(index)">Quitar</BButton></div>
              </div>
            </div>
          </div>

          <div class="col-md-3"><label class="form-label">Prioridad</label><BFormSelect v-model="form.priority" :options="priorityOptions" /></div>
          <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="form.observations" rows="3" /></div>

          <div class="col-12 d-flex justify-content-end gap-2 pt-2">
            <BButton variant="outline-secondary" @click="cancelModal">Cancelar</BButton>
            <BButton variant="primary" :disabled="saving" @click="save">
              {{ saving ? "Guardando..." : isEditing ? "Guardar cambios" : "Registrar atención" }}
            </BButton>
          </div>
        </div>
      </BModal>
    </div>
  </Layout>
</template>

<style scoped>
.staff-attentions-page { color: #303846; }
.staff-attentions-header { align-items: center; display: flex; gap: 1rem; justify-content: space-between; }
.staff-attentions-eyebrow { color: #556ee6; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.staff-metric-card { background: #fff; border: 1px solid #e2e8f2; border-left: 4px solid #556ee6; border-radius: 8px; display: grid; gap: .2rem; min-height: 120px; padding: 1rem 1.1rem; }
.staff-metric-card--warning { border-left-color: #f1b44c; }
.staff-metric-card--danger { border-left-color: #f46a6a; }
.staff-metric-card span, .staff-metric-card small { color: #74788d; }
.staff-metric-card strong { font-size: 1.75rem; line-height: 1.1; }
.staff-filter-card, .staff-list-card { border-radius: 8px; }
.staff-attentions-table { min-width: 1050px; }
.staff-attentions-table thead th { background: #f7f9fc; color: #687287; font-size: .76rem; font-weight: 700; padding: .8rem; text-transform: uppercase; }
.staff-attentions-table tbody td { border-bottom: 1px solid #edf0f5; padding: .85rem .8rem; }
.staff-person-cell { align-items: center; display: flex; gap: .7rem; min-width: 260px; }
.staff-person-cell strong, .staff-person-cell span { display: block; }
.staff-person-cell span { color: #74788d; font-size: .8rem; margin-top: .1rem; }
.staff-person-avatar { align-items: center; background: #eef1ff; border-radius: 50%; color: #4056c8; display: flex; flex: 0 0 38px; font-weight: 700; height: 38px; justify-content: center; text-transform: uppercase; }
.staff-person-avatar--large { flex-basis: 54px; font-size: 1.15rem; height: 54px; }
.staff-reason-cell { max-width: 300px; min-width: 220px; white-space: normal; }
.staff-action-group { display: inline-flex; flex-wrap: wrap; gap: .4rem; justify-content: flex-end; min-width: 180px; }
.staff-action-group .cnsc-action-btn + .cnsc-action-btn { margin-left: 0 !important; }
.staff-empty-state { align-items: center; display: flex; flex-direction: column; padding: 3rem 1rem; text-align: center; }
.staff-empty-state i { color: #aab4c7; font-size: 3rem; }
.staff-empty-state p { color: #74788d; }
.staff-detail { display: grid; gap: 1rem; }
.staff-detail-hero { align-items: center; background: #f7f9ff; border: 1px solid #dfe5f5; border-radius: 8px; display: flex; gap: 1rem; padding: 1rem; }
.staff-detail-hero span, .staff-detail-hero p { color: #74788d; font-size: .85rem; margin: 0; }
.staff-detail-hero h5 { margin: .15rem 0; }
.staff-detail-grid { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); }
.staff-detail-grid > div { border: 1px solid #e5eaf4; border-radius: 8px; display: grid; gap: .25rem; padding: .75rem; }
.staff-detail-grid span, .staff-detail-copy span { color: #7a8498; font-size: .72rem; font-weight: 700; text-transform: uppercase; }
.staff-detail-section { background: #fbfcff; border: 1px solid #e5eaf4; border-radius: 8px; padding: 1rem; }
.staff-detail-section h6 { font-weight: 700; margin-bottom: .8rem; }
.staff-detail-copy { display: grid; gap: .8rem; }
.staff-detail-copy p, .staff-detail-list p { margin: .2rem 0 0; white-space: pre-line; }
.staff-detail-list { display: grid; gap: .65rem; }
.staff-detail-list > div { background: #fff; border: 1px solid #e8ecf3; border-radius: 6px; padding: .75rem; }
.staff-detail-list span { color: #74788d; display: block; font-size: .78rem; }
.staff-form-section-heading { align-items: center; border-bottom: 1px solid #e6eaf1; display: flex; gap: 1rem; justify-content: space-between; margin-bottom: .8rem; padding-bottom: .7rem; }
.staff-form-section-heading span { color: #74788d; display: block; font-size: .8rem; }
.staff-treatment-card, .staff-repeat-card { background: #fbfcff; border: 1px solid #dfe5ee; border-radius: 8px; margin-bottom: .8rem; padding: 1rem; }
.staff-treatment-block { background: #fff; border: 1px solid #e7ebf2; border-radius: 7px; margin-top: .75rem; padding: .85rem; }
.staff-check-grid { display: grid; gap: .55rem .9rem; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); }
@media (max-width: 767px) {
  .staff-attentions-header, .staff-detail-hero { align-items: flex-start; flex-direction: column; }
  .staff-detail-hero .text-end { text-align: left !important; }
}
</style>
