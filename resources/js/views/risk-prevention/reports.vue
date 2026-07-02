<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import HelpButton from "../../components/risk-prevention/help-button.vue";
import StatusBadge from "../../components/risk-prevention/status-badge.vue";
import { getPdfMake } from "../../utils/pdfmake";
import { formatRiskDate, formatRiskDateTime, formatRiskError, showRiskError, showRiskSuccess } from "../../components/risk-prevention/module-utils";

export default {
  components: { Layout, LoadingState, HelpButton, StatusBadge },
  data() {
    const from = new Date();
    from.setDate(from.getDate() - 90);

    return {
      loading: false,
      exporting: false,
      error: null,
      filters: {
        from: from.toISOString().slice(0, 10),
        to: new Date().toISOString().slice(0, 10),
      },
      data: {
        accidents_by_type: [],
        accidents_by_status: [],
        accident_details: [],
        training_compliance: [],
        training_pending_people: [],
        epp_by_employee: [],
        general_status: {
          extinguishers: [],
          documents: [],
        },
      },
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
    canExport() {
      return this.permissions.includes("exportar_prevencion_riesgos") || this.permissions.includes("__superadmin__");
    },
  },
  mounted() {
    this.loadReports();
  },
  methods: {
    formatRiskDate,
    formatRiskDateTime,
    async loadReports() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/risk-prevention/reports", {
          params: this.filters,
        });
        this.data = response.data;
      } catch (error) {
        this.error = formatRiskError(error, "No se pudieron cargar los reportes.");
        showRiskError(this.error);
      } finally {
        this.loading = false;
      }
    },
    async exportPdf() {
      this.exporting = true;
      try {
        const pdfMake = getPdfMake();
        const docDefinition = {
          content: [
            { text: "Reporte de Prevención de Riesgos", style: "title" },
            { text: `Periodo: ${this.filters.from} al ${this.filters.to}`, margin: [0, 0, 0, 12] },
            { text: "Accidentes por tipo", style: "section" },
            {
              table: {
                headerRows: 1,
                widths: ["*", 80],
                body: [["Tipo", "Total"]].concat(
                  (this.data.accidents_by_type || []).map((item) => [item.label, String(item.total)])
                ),
              },
              layout: "lightHorizontalLines",
            },
            { text: "Cumplimiento de capacitaciones", style: "section" },
            {
              table: {
                headerRows: 1,
                widths: ["*", 80],
                body: [["Estado", "Total"]].concat(
                  (this.data.training_compliance || []).map((item) => [item.label, String(item.total)])
                ),
              },
              layout: "lightHorizontalLines",
            },
            { text: "Entrega de EPP por funcionario", style: "section" },
            {
              table: {
                headerRows: 1,
                widths: ["*", 70, 70, 70],
                body: [["Funcionario", "Entregas", "Unidades", "Reposiciones"]].concat(
                  (this.data.epp_by_employee || []).map((item) => [
                    item.employee_name,
                    String(item.deliveries_count),
                    String(item.total_units),
                    String(item.pending_replacements),
                  ])
                ),
              },
              layout: "lightHorizontalLines",
            },
          ],
          styles: {
            title: { fontSize: 18, bold: true },
            section: { fontSize: 12, bold: true, margin: [0, 12, 0, 6] },
          },
        };

        pdfMake.createPdf(docDefinition).download(`prevencion-riesgos-${this.filters.from}-${this.filters.to}.pdf`);
        await showRiskSuccess("El reporte PDF fue generado correctamente.");
      } finally {
        this.exporting = false;
      }
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Reportes de Prevención</h4>
        <div class="text-muted">Accidentes por período, cumplimiento de capacitaciones y estado general preventivo.</div>
      </div>
      <div class="d-flex gap-2">
        <HelpButton
          title="Ayuda: reportes"
          text="Esta vista resume accidentes, cumplimiento de capacitaciones, entregas de EPP y estado preventivo general por período."
        />
        <BButton variant="secondary" @click="loadReports">Actualizar</BButton>
        <BButton v-if="canExport" variant="primary" :disabled="exporting" @click="exportPdf">
          {{ exporting ? "Exportando..." : "Exportar PDF" }}
        </BButton>
      </div>
    </div>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Desde</label>
          <BFormInput v-model="filters.from" type="date" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Hasta</label>
          <BFormInput v-model="filters.to" type="date" />
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <BButton variant="primary" class="w-100" @click="loadReports">Generar reporte</BButton>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
    <LoadingState v-if="loading" message="Generando reportes..." />

    <template v-else>
      <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-3">
          <BCard class="shadow-sm border-0 h-100">
            <div class="text-muted small">Accidentes abiertos</div>
            <div class="display-6 fw-semibold">{{ data.general_status.open_accidents || 0 }}</div>
          </BCard>
        </div>
        <div class="col-md-6 col-xl-3">
          <BCard class="shadow-sm border-0 h-100">
            <div class="text-muted small">Capacitaciones pendientes</div>
            <div class="display-6 fw-semibold">{{ data.general_status.pending_trainings || 0 }}</div>
          </BCard>
        </div>
        <div class="col-md-6 col-xl-3">
          <BCard class="shadow-sm border-0 h-100">
            <div class="text-muted small">EPP por reponer</div>
            <div class="display-6 fw-semibold">{{ data.general_status.epp_due || 0 }}</div>
          </BCard>
        </div>
        <div class="col-md-6 col-xl-3">
          <BCard class="shadow-sm border-0 h-100">
            <div class="text-muted small">Simulacros registrados</div>
            <div class="display-6 fw-semibold">{{ data.general_status.drills_total || 0 }}</div>
          </BCard>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-xl-6">
          <BCard class="h-100">
            <h5 class="mb-3">Accidentes por período</h5>
            <div class="table-responsive mb-3">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Tipo</th>
                    <th class="text-end">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in data.accidents_by_type" :key="item.label">
                    <td>{{ item.label }}</td>
                    <td class="text-end">{{ item.total }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Persona</th>
                    <th>Lugar</th>
                    <th>Estado</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in data.accident_details" :key="item.id">
                    <td>{{ formatRiskDateTime(item.occurred_at) }}</td>
                    <td>{{ item.involved_person_name }}</td>
                    <td>{{ item.location }}</td>
                    <td><StatusBadge :status="item.case_status" /></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>

        <div class="col-xl-6">
          <BCard class="h-100">
            <h5 class="mb-3">Capacitaciones cumplidas / pendientes</h5>
            <div class="table-responsive mb-3">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Estado</th>
                    <th class="text-end">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in data.training_compliance" :key="item.label">
                    <td>{{ item.label }}</td>
                    <td class="text-end">{{ item.total }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Funcionario</th>
                    <th>Capacitación</th>
                    <th>Fecha</th>
                    <th>Modalidad</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in data.training_pending_people" :key="`${item.employee_name}-${item.training_name}`">
                    <td>{{ item.employee_name }}</td>
                    <td>{{ item.training_name }}</td>
                    <td>{{ formatRiskDate(item.training_date) }}</td>
                    <td>{{ item.modality }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-xl-6">
          <BCard class="h-100">
            <h5 class="mb-3">Entrega de EPP por funcionario</h5>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Funcionario</th>
                    <th class="text-end">Entregas</th>
                    <th class="text-end">Unidades</th>
                    <th class="text-end">Reposiciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in data.epp_by_employee" :key="item.employee_name">
                    <td>{{ item.employee_name }}</td>
                    <td class="text-end">{{ item.deliveries_count }}</td>
                    <td class="text-end">{{ item.total_units }}</td>
                    <td class="text-end">{{ item.pending_replacements }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>

        <div class="col-xl-6">
          <BCard class="h-100">
            <h5 class="mb-3">Estado general de prevención</h5>
            <div class="row g-3">
              <div class="col-md-6">
                <div class="fw-semibold mb-2">Extintores</div>
                <div v-for="item in data.general_status.extinguishers || []" :key="`ext-${item.label}`" class="d-flex justify-content-between border-bottom py-1">
                  <span>{{ item.label }}</span>
                  <span>{{ item.total }}</span>
                </div>
              </div>
              <div class="col-md-6">
                <div class="fw-semibold mb-2">Documentos</div>
                <div v-for="item in data.general_status.documents || []" :key="`doc-${item.label}`" class="d-flex justify-content-between border-bottom py-1">
                  <span>{{ item.label }}</span>
                  <span>{{ item.total }}</span>
                </div>
              </div>
            </div>
          </BCard>
        </div>
      </div>
    </template>
  </Layout>
</template>
