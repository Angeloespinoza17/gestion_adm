<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import InfirmaryHelpButton from "../../components/infirmary/help-button.vue";
import {
  formatInfirmaryError,
  normalizeOptions,
  showInfirmaryError,
  showInfirmaryWarning,
} from "../../components/infirmary/module-utils";
import { getPdfMake } from "../../utils/pdfmake";
import {
  buildSchoolInsuranceCertificateDefinition,
  createSchoolInsuranceCertificateForm,
  schoolInsuranceCertificateFileName,
} from "../../utils/school-insurance-certificate";

export default {
  components: {
    Layout,
    LoadingState,
    InfirmaryHelpButton,
  },
  data() {
    return {
      loading: false,
      error: null,
      catalogs: {
        attention_categories: [],
        accident_location_options: [],
        school_insurance_certificate: {},
        capabilities: {},
      },
      filters: {
        search: "",
        accident_location_type: "",
        from: "",
        to: "",
      },
      records: [],
      pagination: {
        current_page: 1,
        total: 0,
        per_page: 12,
      },
      certificateLogoDataUrl: null,
      exportingAttentionId: null,
      sequenceForm: {
        next_number: null,
        reason: "",
      },
      sequenceSaving: false,
      sequenceError: null,
      sequenceMessage: null,
    };
  },
  computed: {
    canExport() {
      return Boolean(this.catalogs.capabilities?.can_export);
    },
    canConfigureSequence() {
      return Boolean(this.catalogs.capabilities?.can_manage_catalogs);
    },
    categoryLabels() {
      return normalizeOptions(this.catalogs.attention_categories).reduce((labels, option) => {
        labels[option.value] = option.text;
        return labels;
      }, {});
    },
    locationOptions() {
      const options = this.catalogs.accident_location_options?.length
        ? normalizeOptions(this.catalogs.accident_location_options)
        : [
          { value: "colegio", text: "En el colegio" },
          { value: "trayecto", text: "En trayecto" },
        ];

      return [{ value: "", text: "Todos los tipos" }, ...options];
    },
  },
  async mounted() {
    await Promise.all([
      this.loadCatalogs(),
      this.loadRecords(),
    ]);
  },
  methods: {
    async loadCatalogs() {
      try {
        const response = await axios.get("/api/infirmary/catalogs");
        this.catalogs = response.data;
        this.sequenceForm.next_number = response.data.school_insurance_sequence?.next_number || 1;
      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudieron cargar los datos de seguro escolar.");
      }
    },
    async loadRecords(page = 1) {
      this.loading = true;
      this.error = null;

      try {
        const response = await axios.get("/api/infirmary/attentions", {
          params: {
            page,
            per_page: this.pagination.per_page,
            school_insurance: 1,
            ...this.filters,
          },
        });

        this.records = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page || 1,
          total: response.data.total || 0,
          per_page: response.data.per_page || 12,
        };
      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudieron cargar los registros de seguro escolar.");
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.filters = {
        search: "",
        accident_location_type: "",
        from: "",
        to: "",
      };
      this.loadRecords(1);
    },
    parseDate(value) {
      if (!value) return null;
      const date = new Date(String(value).replace(" ", "T"));
      return Number.isNaN(date.getTime()) ? null : date;
    },
    formatDate(value) {
      const date = this.parseDate(value);
      return date
        ? new Intl.DateTimeFormat("es-CL", {
          day: "2-digit",
          month: "2-digit",
          year: "numeric",
        }).format(date)
        : "-";
    },
    formatTime(value) {
      const date = this.parseDate(value);
      return date
        ? new Intl.DateTimeFormat("es-CL", {
          hour: "2-digit",
          minute: "2-digit",
          hour12: false,
        }).format(date)
        : "-";
    },
    certificateReference(record) {
      const number = record?.school_insurance_number || record?.correlative_number || record?.id || "";
      return `Nº ${String(number).padStart(5, "0")}`;
    },
    async saveSequence() {
      this.sequenceError = null;
      this.sequenceMessage = null;

      const nextNumber = Number(this.sequenceForm.next_number);
      if (!Number.isInteger(nextNumber) || nextNumber < 1) {
        this.sequenceError = "Ingresa un correlativo válido.";
        return;
      }

      if (String(this.sequenceForm.reason || "").trim().length < 5) {
        this.sequenceError = "Indica el motivo del ajuste con al menos 5 caracteres.";
        return;
      }

      this.sequenceSaving = true;

      try {
        const response = await axios.put("/api/infirmary/school-insurance-sequence", {
          next_number: nextNumber,
          reason: String(this.sequenceForm.reason).trim(),
        });

        this.catalogs.school_insurance_sequence = response.data.data;
        this.sequenceForm.next_number = response.data.data.next_number;
        this.sequenceForm.reason = "";
        this.sequenceMessage = response.data.message;
      } catch (error) {
        this.sequenceError = formatInfirmaryError(error, "No se pudo actualizar el correlativo.");
      } finally {
        this.sequenceSaving = false;
      }
    },
    studentName(record) {
      const relatedName = record?.student?.registered_name
        || [record?.student?.first_name, record?.student?.last_name].filter(Boolean).join(" ");

      return record?.student_full_name_snapshot || relatedName || "Sin estudiante";
    },
    categoryLabel(value) {
      return this.categoryLabels[value] || value || "-";
    },
    locationLabel(record) {
      if (record?.accident_location_type === "trayecto") {
        return "En trayecto";
      }

      return record?.dependency?.name || "En el colegio";
    },
    blobToDataUrl(blob) {
      return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(blob);
      });
    },
    async loadCertificateLogo() {
      if (this.certificateLogoDataUrl) return;

      const logoUrl = this.catalogs.school_insurance_certificate?.logo_url || "/brand/logo-cnsc.png";
      const response = await fetch(logoUrl);
      if (!response.ok) return;

      this.certificateLogoDataUrl = await this.blobToDataUrl(await response.blob());
    },
    async exportCertificate(record) {
      if (!record?.id || this.exportingAttentionId) return;

      this.exportingAttentionId = record.id;

      try {
        const response = await axios.get(`/api/infirmary/attentions/${record.id}`);
        const form = createSchoolInsuranceCertificateForm(
          response.data.data,
          this.catalogs.school_insurance_certificate || {}
        );
        const requiredFields = [
          [form.registration_date, "fecha de registro"],
          [form.rbd, "RBD"],
          [form.establishment_name, "nombre del establecimiento"],
          [form.given_names, "nombres de la estudiante"],
          [form.paternal_surname, "apellido paterno"],
          [form.rut, "RUT de la estudiante"],
          [form.occurred_at, "fecha y hora del accidente"],
          [form.circumstance, "circunstancia del accidente"],
        ];
        const missing = requiredFields
          .filter(([value]) => !String(value || "").trim())
          .map(([, label]) => label);

        if (missing.length) {
          await showInfirmaryWarning(`No se puede exportar. Completa en la ficha: ${missing.join(", ")}.`);
          return;
        }

        await this.loadCertificateLogo();

        const pdfMake = getPdfMake();
        const definition = buildSchoolInsuranceCertificateDefinition(form, this.certificateLogoDataUrl);
        pdfMake.createPdf(definition).download(schoolInsuranceCertificateFileName(form));
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo exportar el certificado de seguro escolar."));
      } finally {
        this.exportingAttentionId = null;
      }
    },
  },
};
</script>

<template>
  <Layout>
    <div class="school-insurance-header">
      <div>
        <h4 class="mb-1">Seguros escolares</h4>
        <p class="text-muted mb-0">Certificados asociados a los accidentes registrados en las fichas de atención.</p>
      </div>
      <InfirmaryHelpButton
        title="Ayuda: seguros escolares"
        text="La tabla muestra las atenciones clasificadas como accidente. Usa la acción PDF para generar el certificado con los datos precargados."
      />
    </div>

    <BCard v-if="canConfigureSequence" class="school-insurance-sequence mb-3">
      <div class="sequence-heading">
        <span class="sequence-icon"><i class="mdi mdi-counter"></i></span>
        <div>
          <h5>Correlativo de certificados</h5>
          <p>
            Define el próximo número a emitir. Los certificados existentes no se modifican
            y el sistema no permite retroceder ni reutilizar correlativos.
          </p>
        </div>
        <div class="sequence-current">
          <small>Base reservada hasta</small>
          <strong>N° {{ String(catalogs.school_insurance_sequence?.last_number || 0).padStart(5, "0") }}</strong>
        </div>
      </div>

      <BAlert v-if="sequenceMessage" show variant="success" class="mt-3 mb-0">
        {{ sequenceMessage }}
      </BAlert>
      <BAlert v-if="sequenceError" show variant="danger" class="mt-3 mb-0">
        {{ sequenceError }}
      </BAlert>

      <div class="sequence-form">
        <div>
          <label class="form-label">Próximo correlativo</label>
          <BFormInput
            v-model.number="sequenceForm.next_number"
            type="number"
            min="1"
            step="1"
            placeholder="Ej.: 994"
          />
        </div>
        <div class="sequence-reason">
          <label class="form-label">Motivo del ajuste</label>
          <BFormInput
            v-model="sequenceForm.reason"
            maxlength="500"
            placeholder="Ej.: Continuidad con certificados físicos históricos"
            @keyup.enter="saveSequence"
          />
        </div>
        <BButton variant="primary" :disabled="sequenceSaving" @click="saveSequence">
          <i class="mdi me-1" :class="sequenceSaving ? 'mdi-loading mdi-spin' : 'mdi-content-save-check-outline'"></i>
          {{ sequenceSaving ? "Guardando..." : "Guardar correlativo" }}
        </BButton>
      </div>
    </BCard>

    <BCard class="school-insurance-filters mb-3">
      <div class="row g-3 align-items-end">
        <div class="col-lg-5">
          <label class="form-label">Buscar estudiante</label>
          <BFormInput
            v-model="filters.search"
            placeholder="Nombre, RUT o N° correlativo"
            @keyup.enter="loadRecords(1)"
          />
        </div>
        <div class="col-sm-6 col-lg-3">
          <label class="form-label">Tipo</label>
          <BFormSelect v-model="filters.accident_location_type" :options="locationOptions" />
        </div>
        <div class="col-sm-6 col-lg-2">
          <label class="form-label">Desde</label>
          <BFormInput v-model="filters.from" type="date" />
        </div>
        <div class="col-sm-6 col-lg-2">
          <label class="form-label">Hasta</label>
          <BFormInput v-model="filters.to" type="date" />
        </div>
        <div class="col-12 d-flex flex-wrap gap-2">
          <BButton variant="primary" @click="loadRecords(1)">
            <i class="mdi mdi-filter-outline me-1"></i>
            Filtrar
          </BButton>
          <BButton variant="outline-secondary" @click="resetFilters">
            Limpiar
          </BButton>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

    <BCard class="school-insurance-records">
      <template #header>
        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
          <div>
            <div class="fw-semibold">Registros de seguro escolar</div>
            <div class="small text-muted">{{ pagination.total }} registros encontrados</div>
          </div>
        </div>
      </template>

      <LoadingState v-if="loading" message="Cargando seguros escolares..." compact />

      <div v-else class="table-responsive">
        <table class="table table-sm align-middle mb-0 school-insurance-table">
          <thead>
            <tr>
              <th>N° correlativo / certificado</th>
              <th>Estudiante</th>
              <th>Fecha del accidente</th>
              <th>Fecha de registro</th>
              <th>Categoría</th>
              <th>Lugar</th>
              <th>Circunstancia</th>
              <th class="text-center">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!records.length">
              <td colspan="8" class="text-center text-muted py-5">
                No hay registros de seguro escolar para los filtros seleccionados.
              </td>
            </tr>
            <tr v-for="record in records" :key="record.id">
              <td>
                <span class="school-insurance-reference">{{ certificateReference(record) }}</span>
              </td>
              <td>
                <div class="fw-semibold">{{ studentName(record) }}</div>
                <div class="small text-muted">{{ record.student?.rut || record.student_rut_snapshot || "Sin RUT" }}</div>
                <div class="small text-muted">{{ record.course_name_snapshot || "Sin curso" }}</div>
              </td>
              <td class="school-insurance-date">
                <div>{{ formatDate(record.occurred_at || record.attended_at) }}</div>
                <div class="small text-muted">{{ formatTime(record.occurred_at || record.attended_at) }}</div>
              </td>
              <td class="school-insurance-date">
                <div>{{ formatDate(record.attended_at) }}</div>
                <div class="small text-muted">{{ formatTime(record.attended_at) }}</div>
              </td>
              <td>
                <div>{{ categoryLabel(record.attention_category) }}</div>
                <div class="small text-muted">
                  {{ record.accident_location_type === "trayecto" ? "Trayecto" : "Colegio" }}
                </div>
              </td>
              <td>{{ locationLabel(record) }}</td>
              <td class="school-insurance-circumstance">
                {{ record.accident_circumstance || record.consultation_reason || "-" }}
              </td>
              <td class="text-center">
                <button
                  v-if="canExport"
                  type="button"
                  class="cnsc-action-btn cnsc-action-btn--delete"
                  :disabled="Boolean(exportingAttentionId)"
                  title="Exportar certificado de seguro escolar"
                  aria-label="Exportar certificado de seguro escolar"
                  @click="exportCertificate(record)"
                >
                  <i
                    class="mdi"
                    :class="exportingAttentionId === record.id ? 'mdi-loading mdi-spin' : 'mdi-file-pdf-box'"
                  ></i>
                  <span class="visually-hidden">Exportar PDF</span>
                </button>
                <span v-else class="small text-muted">Sin permiso</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="pagination.total > pagination.per_page" class="d-flex justify-content-end mt-3">
        <BPagination
          v-model="pagination.current_page"
          :total-rows="pagination.total"
          :per-page="pagination.per_page"
          @update:model-value="loadRecords"
        />
      </div>
    </BCard>
  </Layout>
</template>

<style scoped>
.school-insurance-header {
  align-items: flex-start;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
  margin-bottom: 1rem;
}

.school-insurance-header h4 {
  color: #303846;
  font-size: 1.35rem;
  font-weight: 700;
}

.school-insurance-filters,
.school-insurance-records,
.school-insurance-sequence {
  border: 1px solid #e2e8f3;
  border-radius: 8px;
  box-shadow: none;
}

.sequence-heading {
  align-items: center;
  display: flex;
  gap: 0.9rem;
}

.sequence-heading h5 {
  color: #303846;
  font-size: 1rem;
  font-weight: 700;
  margin: 0 0 0.2rem;
}

.sequence-heading p {
  color: #68738a;
  font-size: 0.84rem;
  margin: 0;
  max-width: 720px;
}

.sequence-icon {
  align-items: center;
  background: #eaf2ff;
  border-radius: 10px;
  color: #3267bd;
  display: flex;
  flex: 0 0 42px;
  font-size: 1.35rem;
  height: 42px;
  justify-content: center;
}

.sequence-current {
  margin-left: auto;
  text-align: right;
}

.sequence-current small {
  color: #7b8495;
  display: block;
  font-size: 0.72rem;
}

.sequence-current strong {
  color: #2d5da8;
  display: block;
  font-size: 1.05rem;
}

.sequence-form {
  align-items: end;
  background: #f8fafc;
  border: 1px solid #e9edf4;
  border-radius: 8px;
  display: grid;
  gap: 0.75rem;
  grid-template-columns: minmax(150px, 0.35fr) minmax(280px, 1fr) auto;
  margin-top: 1rem;
  padding: 0.9rem;
}

.sequence-form .form-label {
  color: #4b5565;
  font-size: 0.8rem;
  font-weight: 600;
}

.school-insurance-filters .form-label {
  color: #4b5565;
  font-size: 0.85rem;
  font-weight: 600;
  margin-bottom: 0.35rem;
}

.school-insurance-table {
  min-width: 1120px;
}

.school-insurance-table thead th {
  color: #68738a;
  font-size: 0.75rem;
  font-weight: 700;
  padding-bottom: 0.85rem;
  padding-top: 0.85rem;
  text-transform: uppercase;
  vertical-align: middle;
}

.school-insurance-table tbody td {
  color: #3e4756;
  padding-bottom: 0.85rem;
  padding-top: 0.85rem;
  vertical-align: middle;
}

.school-insurance-table th:last-child,
.school-insurance-table td:last-child {
  box-shadow: -10px 0 12px -14px rgba(45, 55, 72, 0.65);
  min-width: 82px;
  position: sticky;
  right: 0;
  z-index: 2;
}

.school-insurance-table tbody td:last-child {
  background: rgba(238, 246, 255, 0.96);
}

.school-insurance-table thead th:last-child {
  background: #f8fafc;
  z-index: 3;
}

.school-insurance-reference {
  color: #344054;
  font-weight: 700;
  white-space: nowrap;
}

.school-insurance-date {
  min-width: 115px;
}

.school-insurance-circumstance {
  max-width: 300px;
  min-width: 230px;
  overflow-wrap: anywhere;
}

.school-insurance-table .cnsc-action-btn {
  height: 2.7rem;
  width: 2.7rem;
}

.school-insurance-table .cnsc-action-btn:disabled {
  cursor: wait;
  opacity: 0.55;
}

@media (max-width: 767.98px) {
  .school-insurance-header {
    align-items: stretch;
  }

  .school-insurance-header p {
    font-size: 0.85rem;
  }

  .sequence-heading {
    align-items: flex-start;
    flex-wrap: wrap;
  }

  .sequence-current {
    margin-left: 0;
    text-align: left;
    width: 100%;
  }

  .sequence-form {
    grid-template-columns: 1fr;
  }
}
</style>
