<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import Multiselect from "@vueform/multiselect";
import Swal from "sweetalert2";
import { getPdfMake } from "../../utils/pdfmake";

const emptyForm = () => ({
  staff_id: null,
  contract_template_id: null,
  contract_type: null,
  start_date: "",
  end_date: "",
  position_name: "",
  contract_hours: "",
  workday: null,
  base_salary: "",
  allowances: "",
  place_of_signature: "Valdivia",
  signature_date: "",
  status: "borrador",
  rendered_content: "",
  department_ids: [],
  signer_ids: [],
  custom_variables: {
    banco: "",
    cuenta: "",
  },
  observations: "",
});

export default {
  components: { Layout, Multiselect },
  data() {
    return {
      loading: false,
      saving: false,
      previewing: false,
      syncingPreview: false,
      exportingPdf: false,
      error: null,
      success: null,
      contract: null,
      catalogs: {
        staff: [],
        templates: [],
        departments: [],
        signers: [],
        statuses: [],
        contract_types: [],
        workdays: [],
        available_variables: {},
      },
      form: emptyForm(),
      livePreviewContent: "",
      previewMissing: [],
      signatureBlocks: [],
      autoPreviewTimer: null,
    };
  },
  computed: {
    isNew() {
      return this.$route.path === "/contracts/new";
    },
    itemId() {
      return this.$route.params.id;
    },
    permissions() {
      try {
        return JSON.parse(localStorage.getItem("permissions") || "[]");
      } catch (error) {
        return [];
      }
    },
    canManage() {
      return this.permissions.includes("gestionar_contratos");
    },
    canExport() {
      return this.permissions.includes("exportar_contratos") || this.canManage;
    },
    canDelete() {
      return this.permissions.includes("eliminar_contratos");
    },
    canEditSigned() {
      return this.permissions.includes("editar_contratos_firmados");
    },
    isLocked() {
      return this.contract?.status === "firmado" && !this.canEditSigned;
    },
    staffOptions() {
      return (this.catalogs.staff || []).map((item) => ({
        value: item.id,
        label: `${item.full_name} (${item.rut || "sin RUT"})`,
      }));
    },
    templateOptions() {
      return (this.catalogs.templates || []).map((item) => ({
        value: item.id,
        label: item.name,
      }));
    },
    departmentOptions() {
      return (this.catalogs.departments || []).map((item) => ({
        value: item.id,
        label: item.name,
      }));
    },
    signerOptions() {
      return (this.catalogs.signers || []).map((item) => ({
        value: item.id,
        label: `${item.name} · ${item.position || "Sin cargo"}`,
      }));
    },
    statusOptions() {
      return (this.catalogs.statuses || []).map((item) => ({
        value: item.value,
        label: item.label,
      }));
    },
    contractTypeOptions() {
      return (this.catalogs.contract_types || []).map((item) => ({
        value: item.value,
        label: item.label,
      }));
    },
    workdayOptions() {
      return (this.catalogs.workdays || []).map((item) => ({
        value: item.value,
        label: item.label,
      }));
    },
    selectedStaff() {
      return (this.catalogs.staff || []).find((item) => item.id === this.form.staff_id) || null;
    },
    selectedTemplate() {
      return (this.catalogs.templates || []).find((item) => item.id === this.form.contract_template_id) || null;
    },
    selectedSigners() {
      const map = new Map((this.catalogs.signers || []).map((item) => [item.id, item]));
      return (this.form.signer_ids || []).map((id) => map.get(id)).filter(Boolean);
    },
    customVariableRows() {
      return Object.keys(this.form.custom_variables || {}).map((key) => ({
        key,
        value: this.form.custom_variables[key],
      }));
    },
    previewSource() {
      return JSON.stringify({
        staff_id: this.form.staff_id,
        contract_template_id: this.form.contract_template_id,
        contract_type: this.form.contract_type,
        start_date: this.form.start_date,
        end_date: this.form.end_date,
        position_name: this.form.position_name,
        contract_hours: this.form.contract_hours,
        workday: this.form.workday,
        base_salary: this.form.base_salary,
        allowances: this.form.allowances,
        place_of_signature: this.form.place_of_signature,
        signature_date: this.form.signature_date,
        status: this.form.status,
        department_ids: this.form.department_ids,
        signer_ids: this.form.signer_ids,
        custom_variables: this.form.custom_variables,
      });
    },
  },
  watch: {
    "form.staff_id"(value) {
      if (this.isNew && value) {
        this.prefillFromStaff(value);
      }
    },
    "form.contract_type"(value) {
      if (value === "indefinido") {
        this.form.end_date = "";
      }
    },
    previewSource() {
      this.schedulePreview();
    },
  },
  mounted() {
    this.load();
  },
  beforeUnmount() {
    if (this.autoPreviewTimer) {
      clearTimeout(this.autoPreviewTimer);
    }
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = null;

      try {
        const requests = [axios.get("/api/contracts/catalogs")];
        if (!this.isNew) {
          requests.push(axios.get(`/api/contracts/${this.itemId}`));
        }

        const responses = await Promise.all(requests);
        this.catalogs = responses[0].data;

        if (this.isNew) {
          const queryStaffId = Number(this.$route.query.staff_id || 0) || null;
          if (queryStaffId) {
            this.form.staff_id = queryStaffId;
            this.prefillFromStaff(queryStaffId);
          }
          this.schedulePreview();
          return;
        }

        this.contract = responses[1].data.data;
        this.form = {
          staff_id: this.contract.staff_id,
          contract_template_id: this.contract.contract_template_id,
          contract_type: this.contract.contract_type,
          start_date: this.contract.start_date || "",
          end_date: this.contract.end_date || "",
          position_name: this.contract.position_name || "",
          contract_hours: this.contract.contract_hours || "",
          workday: this.contract.workday || null,
          base_salary: this.contract.base_salary || "",
          allowances: this.contract.allowances || "",
          place_of_signature: this.contract.place_of_signature || "Valdivia",
          signature_date: this.contract.signature_date || "",
          status: this.contract.status || "borrador",
          rendered_content: this.contract.rendered_content || "",
          department_ids: (this.contract.departments || []).map((item) => item.id),
          signer_ids: (this.contract.signatures || []).map((item) => item.contract_signer_id).filter(Boolean),
          custom_variables: this.contract.custom_variables || { banco: "", cuenta: "" },
          observations: this.contract.observations || "",
        };
        this.signatureBlocks = this.contract.signatures || [];
        this.livePreviewContent = this.contract.rendered_content || "";
        this.schedulePreview();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    prefillFromStaff(staffId) {
      const staff = (this.catalogs.staff || []).find((item) => item.id === staffId);
      if (!staff) return;

      this.form.contract_type = this.form.contract_type || staff.contract_type || null;
      this.form.position_name = this.form.position_name || staff.cargo?.name || "";
      this.form.contract_hours = this.form.contract_hours || staff.contract_hours || "";
      this.form.workday = this.form.workday || staff.workday || null;
      this.form.department_ids = this.form.department_ids.length ? this.form.department_ids : (staff.departments || []).map((item) => item.id);
      if (!this.form.start_date && staff.start_date) {
        this.form.start_date = staff.start_date;
      }
    },
    schedulePreview() {
      if (this.loading) {
        return;
      }

      if (this.autoPreviewTimer) {
        clearTimeout(this.autoPreviewTimer);
      }

      if (!this.canGeneratePreview()) {
        this.livePreviewContent = "";
        this.previewMissing = [];
        this.signatureBlocks = [];
        return;
      }

      this.autoPreviewTimer = setTimeout(() => {
        this.preview({ silent: true, applyToEditor: false });
      }, 500);
    },
    canGeneratePreview() {
      return Boolean(this.form.staff_id && this.form.contract_template_id && this.form.start_date);
    },
    async preview({ silent = false, applyToEditor = false } = {}) {
      if (!this.canGeneratePreview()) {
        return;
      }

      this.previewing = !silent;
      this.syncingPreview = silent;
      this.error = null;

      try {
        const response = await axios.post("/api/contracts/preview", this.form);
        this.livePreviewContent = response.data.data.content || "";
        this.previewMissing = response.data.data.missing_variables || [];
        this.signatureBlocks = response.data.data.signature_blocks || [];

        if (applyToEditor || !this.form.rendered_content) {
          this.form.rendered_content = this.livePreviewContent;
        }
      } catch (error) {
        this.error = this.formatError(error);
        if (!silent) {
          this.showError(this.error);
        }
      } finally {
        this.previewing = false;
        this.syncingPreview = false;
      }
    },
    applyPreviewToEditor() {
      if (!this.livePreviewContent) {
        return;
      }

      this.form.rendered_content = this.livePreviewContent;
    },
    async save() {
      this.saving = true;
      this.error = null;

      try {
        const payload = {
          ...this.form,
          rendered_content: this.form.rendered_content || this.livePreviewContent,
        };

        let response;
        if (this.isNew) {
          response = await axios.post("/api/contracts", payload);
          this.$router.replace({ path: `/contracts/${response.data.data.id}`, query: { created: 1 } });
          return;
        }

        response = await axios.put(`/api/contracts/${this.contract.id}`, payload);
        this.contract = response.data.data;
        this.form.rendered_content = this.contract.rendered_content || this.form.rendered_content;
        this.livePreviewContent = this.contract.rendered_content || this.livePreviewContent;
        this.signatureBlocks = this.contract.signatures || this.signatureBlocks;
        await Swal.fire({
          title: "Contrato actualizado",
          text: "Los cambios se guardaron correctamente.",
          icon: "success",
          timer: 1500,
          showConfirmButton: false,
        });
      } catch (error) {
        this.error = this.formatError(error);
        this.showError(this.error);
      } finally {
        this.saving = false;
      }
    },
    async updateStatus(status) {
      if (!this.contract) return;

      try {
        await axios.put(`/api/contracts/${this.contract.id}/status`, { status });
        await this.load();
      } catch (error) {
        this.error = this.formatError(error);
        this.showError(this.error);
      }
    },
    async downloadWord() {
      if (!this.contract) return;

      try {
        const response = await axios.get(`/api/contracts/${this.contract.id}/export-word`, {
          responseType: "blob",
        });
        const url = URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement("a");
        link.href = url;
        link.download = `contrato_${this.contract.id}.doc`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
      } catch (error) {
        this.error = this.formatError(error);
        this.showError(this.error);
      }
    },
    async exportPdf() {
      if (!this.form.rendered_content && !this.livePreviewContent) {
        await this.preview({ silent: false, applyToEditor: false });
      }

      const sourceContent = this.form.rendered_content || this.livePreviewContent;

      if (!sourceContent) {
        return;
      }

      this.exportingPdf = true;

      try {
        const pdfMake = getPdfMake();
        const paragraphs = String(sourceContent)
          .split(/\n{2,}/)
          .map((item) => item.trim())
          .filter(Boolean)
          .map((item) => ({ text: item, margin: [0, 0, 0, 10] }));

        const signatureContent = (this.signatureBlocks || []).flatMap((signature) => ([
          { text: "______________________________", margin: [0, 10, 0, 0] },
          { text: signature.name || "", bold: true },
          { text: signature.rut || "", fontSize: 9 },
          { text: signature.position || "", fontSize: 9, margin: [0, 0, 0, 10] },
        ]));

        pdfMake.createPdf({
          pageSize: "A4",
          pageMargins: [40, 48, 40, 48],
          content: [
            { text: this.selectedTemplate?.name || "Contrato", style: "title" },
            { text: this.selectedStaff?.full_name || "", style: "subtitle", margin: [0, 0, 0, 14] },
            ...paragraphs,
            ...(signatureContent.length
              ? [{ text: "Firmas", style: "sectionTitle", margin: [0, 12, 0, 10] }, ...signatureContent]
              : []),
          ],
          styles: {
            title: { fontSize: 16, bold: true, color: "#2a3042" },
            subtitle: { fontSize: 10, color: "#495057" },
            sectionTitle: { fontSize: 11, bold: true, color: "#495057" },
          },
          defaultStyle: { fontSize: 10 },
        }).download(`contrato_${this.contract?.id || "borrador"}.pdf`);
      } catch (error) {
        this.error = this.formatError(error);
        this.showError(this.error);
      } finally {
        this.exportingPdf = false;
      }
    },
    addCustomVariable() {
      let base = "nuevo_campo";
      let counter = 1;

      while (Object.prototype.hasOwnProperty.call(this.form.custom_variables, base)) {
        base = `nuevo_campo_${counter}`;
        counter += 1;
      }

      this.form.custom_variables = {
        ...this.form.custom_variables,
        [base]: "",
      };
    },
    renameCustomVariable(oldKey, newKey) {
      const normalized = String(newKey || "").trim().replace(/\s+/g, "_").toLowerCase();
      if (!normalized || normalized === oldKey) return;

      const next = { ...this.form.custom_variables };
      next[normalized] = next[oldKey];
      delete next[oldKey];
      this.form.custom_variables = next;
    },
    removeCustomVariable(key) {
      const next = { ...this.form.custom_variables };
      delete next[key];
      this.form.custom_variables = next;
    },
    labelForOption(value, options) {
      return (options || []).find((item) => item.value === value)?.label || value || "-";
    },
    formatDate(value) {
      if (!value) return "-";
      const normalized = String(value).split(" ")[0];
      const [year, month, day] = normalized.split("-");
      return year && month && day ? `${day}/${month}/${year}` : value;
    },
    formatDateTime(value) {
      if (!value) return "-";
      const normalized = String(value).replace("T", " ").replace(/\.\d+Z?$/, "");
      const [datePart, timePart = ""] = normalized.split(" ");
      const [year, month, day] = datePart.split("-");
      const [hours = "00", minutes = "00"] = timePart.split(":");
      return year && month && day ? `${day}/${month}/${year} ${hours}:${minutes}` : value;
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
    showError(text) {
      return Swal.fire({
        title: "Error",
        text,
        icon: "error",
      });
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
      <div class="mb-3 mb-sm-0">
        <h4 class="mb-0">{{ isNew ? "Nuevo contrato" : "Ficha de contrato" }}</h4>
        <div class="text-muted" v-if="contract">
          {{ contract.staff?.full_name }} · {{ contract.template?.name }}
        </div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <router-link to="/contracts" class="btn btn-outline-secondary">Volver</router-link>
        <BButton variant="outline-primary" :disabled="previewing || isLocked" @click="preview">
          {{ previewing ? "Actualizando vista previa..." : "Actualizar vista previa" }}
        </BButton>
        <BButton variant="outline-info" :disabled="!livePreviewContent || isLocked" @click="applyPreviewToEditor">
          Usar vista previa
        </BButton>
        <BButton v-if="canExport && !isNew" variant="outline-secondary" @click="downloadWord">Exportar Word</BButton>
        <BButton v-if="canExport" variant="outline-danger" :disabled="exportingPdf" @click="exportPdf">
          {{ exportingPdf ? "Generando PDF..." : "Exportar PDF" }}
        </BButton>
        <BButton v-if="canManage" variant="primary" :disabled="saving || isLocked" @click="save">
          {{ saving ? "Guardando..." : "Guardar contrato" }}
        </BButton>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
    <BAlert v-if="previewMissing.length" variant="warning" show class="mb-3">
      Variables pendientes: {{ previewMissing.join(", ") }}
    </BAlert>

    <div v-if="loading" class="text-center py-5">
      <BSpinner class="me-2" />
      Cargando contrato...
    </div>

    <div v-else class="row g-4">
      <div class="col-12">
        <BCard>
          <div class="row g-3">
            <div class="col-xl-4">
              <label class="form-label">Funcionario</label>
              <Multiselect v-model="form.staff_id" :options="staffOptions" :searchable="true" :disabled="isLocked" />
            </div>
            <div class="col-xl-4">
              <label class="form-label">Plantilla</label>
              <Multiselect v-model="form.contract_template_id" :options="templateOptions" :searchable="true" :disabled="isLocked" />
            </div>
            <div class="col-xl-4">
              <label class="form-label">Estado del contrato</label>
              <Multiselect v-model="form.status" :options="statusOptions" :searchable="true" :disabled="isLocked" />
            </div>
            <div class="col-xl-3 col-md-6">
              <label class="form-label">Tipo de contrato</label>
              <Multiselect v-model="form.contract_type" :options="contractTypeOptions" :searchable="true" :disabled="isLocked" />
            </div>
            <div class="col-xl-3 col-md-6">
              <label class="form-label">Fecha de inicio</label>
              <BFormInput v-model="form.start_date" type="date" :disabled="isLocked" />
            </div>
            <div class="col-xl-3 col-md-6">
              <label class="form-label">Fecha de término</label>
              <BFormInput v-model="form.end_date" type="date" :disabled="isLocked || form.contract_type === 'indefinido'" />
            </div>
            <div class="col-xl-3 col-md-6">
              <label class="form-label">Fecha de firma</label>
              <BFormInput v-model="form.signature_date" type="date" :disabled="isLocked" />
            </div>
            <div class="col-xl-4 col-md-6">
              <label class="form-label">Cargo contratado</label>
              <BFormInput v-model="form.position_name" :disabled="isLocked" />
            </div>
            <div class="col-xl-2 col-md-6">
              <label class="form-label">Horas contratadas</label>
              <BFormInput v-model="form.contract_hours" type="number" step="0.01" :disabled="isLocked" />
            </div>
            <div class="col-xl-3 col-md-6">
              <label class="form-label">Jornada</label>
              <Multiselect v-model="form.workday" :options="workdayOptions" :searchable="true" :disabled="isLocked" />
            </div>
            <div class="col-xl-3 col-md-6">
              <label class="form-label">Lugar de firma</label>
              <BFormInput v-model="form.place_of_signature" :disabled="isLocked" />
            </div>
          </div>
        </BCard>
      </div>

      <div class="col-xl-8">
        <BCard>
          <h5 class="mb-3">Condiciones económicas y contenido</h5>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Sueldo base</label>
              <BFormInput v-model="form.base_salary" type="number" step="1" :disabled="isLocked" />
            </div>
            <div class="col-md-8">
              <label class="form-label">Departamentos vinculados</label>
              <Multiselect
                v-model="form.department_ids"
                :options="departmentOptions"
                mode="multiple"
                :close-on-select="false"
                :searchable="true"
                :disabled="isLocked"
              />
            </div>
            <div class="col-12">
              <label class="form-label">Asignaciones</label>
              <BFormTextarea v-model="form.allowances" rows="4" :disabled="isLocked" />
            </div>
            <div class="col-12">
              <label class="form-label">Observaciones internas</label>
              <BFormTextarea v-model="form.observations" rows="3" :disabled="isLocked" />
            </div>
            <div class="col-12">
              <label class="form-label">Contenido final editable</label>
              <BFormTextarea v-model="form.rendered_content" rows="18" :disabled="isLocked" />
              <small class="text-muted">
                La vista previa se actualiza automáticamente. Usa “Usar vista previa” para copiarla al contenido final editable.
              </small>
            </div>
          </div>
        </BCard>
      </div>

      <div class="col-xl-4">
        <BCard class="mb-4">
          <h5 class="mb-3">Firmas seleccionadas</h5>
          <Multiselect
            v-model="form.signer_ids"
            :options="signerOptions"
            mode="multiple"
            :close-on-select="false"
            :searchable="true"
            :disabled="isLocked"
          />
          <div class="mt-3" v-if="selectedSigners.length">
            <div v-for="signer in selectedSigners" :key="signer.id" class="border rounded p-2 mb-2">
              <div class="fw-semibold">{{ signer.name }}</div>
              <div class="text-muted small">{{ signer.position || "-" }} · {{ signer.rut || "Sin RUT" }}</div>
            </div>
          </div>
        </BCard>

        <BCard class="mb-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Variables personalizadas</h5>
            <BButton size="sm" variant="light" @click="addCustomVariable" :disabled="isLocked">Agregar</BButton>
          </div>
          <div v-for="row in customVariableRows" :key="row.key" class="border rounded p-2 mb-2">
            <div class="row g-2">
              <div class="col-5">
                <label class="form-label small">Clave</label>
                <BFormInput :model-value="row.key" @blur="renameCustomVariable(row.key, $event.target.value)" :disabled="isLocked" />
              </div>
              <div class="col-5">
                <label class="form-label small">Valor</label>
                <BFormInput v-model="form.custom_variables[row.key]" :disabled="isLocked" />
              </div>
              <div class="col-2 d-flex align-items-end">
                <BButton size="sm" variant="outline-danger" class="w-100" @click="removeCustomVariable(row.key)" :disabled="isLocked">×</BButton>
              </div>
            </div>
            <small class="text-muted">Usa la variable <code v-text="`{{extra.${row.key}}}`"></code></small>
          </div>
        </BCard>

        <BCard>
          <h5 class="mb-3">Datos y referencias</h5>
          <div class="small text-muted mb-2" v-if="selectedStaff">
            <div><strong>Funcionario:</strong> {{ selectedStaff.full_name }}</div>
            <div><strong>RUT:</strong> {{ selectedStaff.rut || "-" }}</div>
            <div><strong>Correo:</strong> {{ selectedStaff.institutional_email || selectedStaff.personal_email || "-" }}</div>
          </div>
          <div class="small text-muted">
            <div><strong>Tipo:</strong> {{ labelForOption(form.contract_type, contractTypeOptions) }}</div>
            <div><strong>Jornada:</strong> {{ labelForOption(form.workday, workdayOptions) }}</div>
            <div v-if="contract"><strong>Creado:</strong> {{ formatDateTime(contract.created_at) }}</div>
            <div v-if="contract"><strong>Actualizado:</strong> {{ formatDateTime(contract.updated_at) }}</div>
          </div>
        </BCard>
      </div>

      <div class="col-12">
        <BCard>
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Vista previa del contrato</h5>
            <span class="badge rounded-pill badge-soft-primary">{{ selectedTemplate?.name || "Sin plantilla" }}</span>
          </div>
          <div v-if="syncingPreview" class="text-muted mb-2">Actualizando vista previa en tiempo real...</div>
          <div class="contract-preview" v-if="livePreviewContent">{{ livePreviewContent }}</div>
          <div v-else class="text-muted">Completa funcionario, plantilla y fecha de inicio para generar la vista previa en tiempo real.</div>
        </BCard>
      </div>

      <div class="col-12">
        <BCard>
          <h5 class="mb-3">Variables disponibles</h5>
          <div class="row g-3">
            <div v-for="(items, group) in catalogs.available_variables" :key="group" class="col-xl-3 col-md-6">
              <div class="border rounded p-3 h-100">
                <div class="fw-semibold text-capitalize mb-2">{{ group.replaceAll('_', ' ') }}</div>
                <div v-for="item in items" :key="item" class="small text-muted mb-1">
                  <code v-text="`{{${item}}}`"></code>
                </div>
              </div>
            </div>
          </div>
        </BCard>
      </div>
    </div>
  </Layout>
</template>

<style scoped>
.contract-preview {
  white-space: pre-wrap;
  background: rgba(85, 110, 230, 0.06);
  border: 1px solid rgba(85, 110, 230, 0.16);
  border-radius: 0.75rem;
  padding: 1rem;
  line-height: 1.7;
}
</style>
