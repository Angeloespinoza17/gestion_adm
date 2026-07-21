<script>
import axios from "axios";
import Swal from "sweetalert2";
import LoadingState from "../ui/loading-state.vue";
import { formatRemunerationError, money } from "./module-utils";
import { getPdfMake } from "../../utils/pdfmake";

const emptyAnalytics = () => ({
  available_filters: { imports: [], periods: [], employee_types: [] },
  current_import: null,
  previous_import: null,
  metrics: {},
  trend: [],
  composition: { earnings: [], deductions: [], employer_contributions: [], waterfall: [] },
  concept_catalog: [],
  concept_drilldown: null,
  union_earnings: { metrics: {}, trend: [], by_concept: [], by_type: [], detail_rows: [] },
  staffing: { by_type: [], net_distribution: [], percentiles: {} },
  variations: { new_workers: 0, missing_workers: 0, top_increases: [], top_decreases: [] },
  alerts: { total: 0, by_severity: {}, by_type: {}, items: [], definitions: [], rules: [], active_rule_count: 0, reconciled_rows: 0, reconciliation_rate: 0 },
  detail_rows: [],
  coverage: {},
});

const defaultAlertRuleForm = () => ({
  name: "",
  description: "",
  severity: "requiere_revision",
  metric: "deduction_rate",
  operator: "gt",
  threshold_value: "",
  concept_key: "",
  concept_label: "",
  employee_type: "",
  enabled: true,
});

const alertMetricOptions = [
  { value: "gross_total", label: "Total haberes", kind: "money" },
  { value: "net_amount", label: "Total líquido", kind: "money" },
  { value: "total_deductions", label: "Total descuentos", kind: "money" },
  { value: "legal_deductions", label: "Descuentos legales", kind: "money" },
  { value: "other_deductions", label: "Otros descuentos", kind: "money" },
  { value: "deduction_rate", label: "Descuentos sobre haberes", kind: "percent" },
  { value: "worked_days", label: "DT", kind: "number" },
  { value: "weekly_hours", label: "Carga horaria", kind: "number" },
  { value: "concept_amount", label: "Monto de concepto", kind: "money" },
];

const alertOperatorOptions = [
  { value: "gt", label: "Mayor que" },
  { value: "gte", label: "Mayor o igual que" },
  { value: "lt", label: "Menor que" },
  { value: "lte", label: "Menor o igual que" },
  { value: "eq", label: "Igual a" },
  { value: "neq", label: "Distinto de" },
];

const alertSeverityOptions = [
  { value: "critica", label: "Crítica" },
  { value: "requiere_revision", label: "Requiere revisión" },
  { value: "informativa", label: "Informativa" },
];

export default {
  components: { LoadingState },
  data() {
    return {
      loading: false,
      loadingConcept: false,
      error: null,
      analytics: emptyAnalytics(),
      filters: {
        import_id: "",
        from_period_id: "",
        to_period_id: "",
        employee_type: "",
        concept_key: "",
      },
      conceptQuery: "",
      unionConceptQuery: "",
      conceptNatureFilter: "",
      conceptModalVisible: false,
      alertRulesModalVisible: false,
      loadingUnionSetting: "",
      loadingAlertRules: false,
      savingAlertRule: false,
      alertRules: [],
      alertRuleForm: defaultAlertRuleForm(),
      alertMetricOptions,
      alertOperatorOptions,
      alertSeverityOptions,
      exportingPdf: false,
      exportingExcel: false,
      exportingConceptPdf: false,
      exportingUnionPdf: false,
    };
  },
  computed: {
    metricCards() {
      const metrics = this.analytics.metrics || {};
      return [
        { label: "Dotación", value: metrics.workers || 0, type: "number" },
        { label: "Con pago", value: metrics.paid_workers || 0, type: "number" },
        { label: "Haberes", value: metrics.gross_total || 0, type: "money", variation: metrics.gross_variation },
        { label: "Descuentos", value: metrics.total_deductions || 0, type: "money", variation: metrics.deduction_variation },
        { label: "Líquido", value: metrics.net_total || 0, type: "money", variation: metrics.net_variation },
        { label: "Promedio líquido", value: metrics.average_net_paid || 0, type: "money" },
        { label: "Mediana líquida", value: metrics.median_net_paid || 0, type: "money" },
        { label: "Tasa descuentos", value: metrics.discount_rate || 0, type: "percent" },
      ];
    },
    trendCategories() {
      return (this.analytics.trend || []).map((item) => item.period);
    },
    trendSeries() {
      const trend = this.analytics.trend || [];
      return [
        { name: "Haberes", data: trend.map((item) => item.gross_total || 0) },
        { name: "Descuentos", data: trend.map((item) => item.total_deductions || 0) },
        { name: "Líquido", data: trend.map((item) => item.net_total || 0) },
      ];
    },
    staffingShareSeries() {
      const rows = this.analytics.staffing?.by_type || [];
      return [
        { name: "Dotación", data: rows.map((item) => item.staff_share || 0) },
        { name: "Haberes", data: rows.map((item) => item.gross_share || 0) },
      ];
    },
    conceptEarningsSeries() {
      return [{ name: "Monto", data: (this.analytics.composition?.earnings || []).slice(0, 10).map((item) => item.amount || 0) }];
    },
    conceptDeductionsSeries() {
      return [{ name: "Monto", data: (this.analytics.composition?.deductions || []).slice(0, 10).map((item) => item.amount || 0) }];
    },
    distributionSeries() {
      return [{ name: "Trabajadores", data: (this.analytics.staffing?.net_distribution || []).map((item) => item.workers || 0) }];
    },
    waterfallSeries() {
      return [{ name: "Monto", data: (this.analytics.composition?.waterfall || []).map((item) => Math.abs(Number(item.amount || 0))) }];
    },
    filteredConceptCatalog() {
      const query = this.normalizeSearch(this.conceptQuery);
      return (this.analytics.concept_catalog || [])
        .filter((concept) => !this.conceptNatureFilter || concept.nature === this.conceptNatureFilter)
        .filter((concept) => !query || this.normalizeSearch(`${concept.label} ${concept.nature} ${concept.group}`).includes(query))
        .slice(0, 30);
    },
    filteredEarningConcepts() {
      const query = this.normalizeSearch(this.conceptQuery);
      return (this.analytics.concept_catalog || [])
        .filter((concept) => concept.nature === "Haber")
        .filter((concept) => !query || this.normalizeSearch(`${concept.label} ${concept.group} ${concept.code}`).includes(query));
    },
    filteredUnionConcepts() {
      const query = this.normalizeSearch(this.unionConceptQuery);
      return (this.analytics.concept_catalog || [])
        .filter((concept) => concept.nature === "Haber")
        .filter((concept) => !query || this.normalizeSearch(`${concept.label} ${concept.group} ${concept.code}`).includes(query));
    },
    unionMetricCards() {
      const metrics = this.analytics.union_earnings?.metrics || {};
      return [
        { label: "Total sindical", value: metrics.total_amount || 0, type: "money" },
        { label: "Trabajadores", value: metrics.workers || 0, type: "number" },
        { label: "Promedio trabajador", value: metrics.average_amount || 0, type: "money" },
        { label: "Promedio mensual", value: metrics.monthly_average_amount || 0, type: "money" },
        { label: "Conceptos marcados", value: metrics.concept_count || 0, type: "number" },
        { label: "Participación", value: metrics.share || 0, type: "percent" },
      ];
    },
    unionTrendCategories() {
      return (this.analytics.union_earnings?.trend || []).map((item) => item.period);
    },
    unionTrendSeries() {
      return [{ name: "Haberes sindicales", data: (this.analytics.union_earnings?.trend || []).map((item) => item.amount || 0) }];
    },
    unionConceptSeries() {
      return [{ name: "Monto", data: (this.analytics.union_earnings?.by_concept || []).slice(0, 10).map((item) => item.amount || 0) }];
    },
    unionTypeSeries() {
      return [{ name: "Monto", data: (this.analytics.union_earnings?.by_type || []).map((item) => item.amount || 0) }];
    },
    selectedConcept() {
      return this.analytics.concept_drilldown?.selected || null;
    },
    conceptMetricCards() {
      const metrics = this.analytics.concept_drilldown?.metrics || {};
      return [
        { label: "Monto acumulado", value: metrics.total_amount || 0, type: "money" },
        { label: "Monto mensual", value: metrics.monthly_amount || 0, type: "money", variation: metrics.variation },
        { label: "Trabajadores", value: metrics.workers || 0, type: "number" },
        { label: "Promedio", value: metrics.average_amount || 0, type: "money" },
        { label: "Mediana", value: metrics.median_amount || 0, type: "money" },
        { label: "Participación", value: metrics.share || 0, type: "percent" },
      ];
    },
    conceptTrendCategories() {
      return (this.analytics.concept_drilldown?.trend || []).map((item) => item.period);
    },
    conceptTrendSeries() {
      return [{ name: "Monto", data: (this.analytics.concept_drilldown?.trend || []).map((item) => item.amount || 0) }];
    },
    conceptByTypeSeries() {
      return [{ name: "Monto", data: (this.analytics.concept_drilldown?.by_type || []).map((item) => item.amount || 0) }];
    },
    alertRulesForModal() {
      return this.alertRules.length ? this.alertRules : (this.analytics.alerts?.rules || []);
    },
    systemAlertDefinitions() {
      return this.analytics.alerts?.definitions || [];
    },
    selectedAlertMetricOption() {
      return this.alertMetricOptions.find((item) => item.value === this.alertRuleForm.metric) || this.alertMetricOptions[0];
    },
    alertConceptOptions() {
      return (this.analytics.concept_catalog || [])
        .filter((concept) => concept.key)
        .map((concept) => ({ key: concept.key, label: concept.label || concept.name }))
        .sort((a, b) => a.label.localeCompare(b.label, "es-CL"));
    },
  },
  mounted() {
    this.loadAnalytics();
  },
  methods: {
    money,
    async loadAnalytics(options = {}) {
      const conceptOnly = Boolean(options.conceptOnly);
      const silent = Boolean(options.silent);
      if (conceptOnly) {
        this.loadingConcept = true;
      } else if (!silent) {
        this.loading = true;
        this.error = null;
      }
      try {
        const response = await axios.get("/api/remuneraciones/book-analytics", {
          params: this.cleanFilters(),
        });
        this.analytics = response.data || emptyAnalytics();
      } catch (error) {
        const message = formatRemunerationError(error, "No fue posible cargar las estadísticas del libro.");
        if (silent) {
          Swal.fire("Error", message, "error");
        } else {
          this.error = message;
        }
      } finally {
        if (conceptOnly) {
          this.loadingConcept = false;
        } else if (!silent) {
          this.loading = false;
        }
      }
    },
    cleanFilters() {
      return Object.fromEntries(Object.entries(this.filters).filter(([, value]) => value !== "" && value !== null && value !== undefined));
    },
    clearFilters() {
      this.filters = {
        import_id: "",
        from_period_id: "",
        to_period_id: "",
        employee_type: "",
        concept_key: "",
      };
      this.conceptQuery = "";
      this.unionConceptQuery = "";
      this.conceptNatureFilter = "";
      this.loadAnalytics();
    },
    normalizeSearch(value) {
      return String(value || "")
        .toLocaleLowerCase("es-CL")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "");
    },
    selectConcept(concept) {
      this.filters.concept_key = concept.key;
      this.conceptQuery = concept.label;
      this.loadAnalytics({ conceptOnly: true });
    },
    openConceptModal(concept) {
      this.filters.concept_key = concept.key;
      this.conceptModalVisible = true;
      this.loadAnalytics({ conceptOnly: true });
    },
    resetConceptModal() {
      this.filters.concept_key = "";
      this.analytics.concept_drilldown = null;
      this.loadingConcept = false;
      this.exportingConceptPdf = false;
    },
    clearConceptSelection() {
      this.filters.concept_key = "";
      this.conceptModalVisible = false;
      this.loadAnalytics({ conceptOnly: true });
    },
    async toggleUnionIncome(concept, checked) {
      const previous = Boolean(concept.is_union_income);
      concept.is_union_income = checked;
      this.loadingUnionSetting = concept.key;

      try {
        await axios.patch(`/api/remuneraciones/book-concept-settings/${concept.key}`, {
          code: concept.code || null,
          name: concept.name,
          label: concept.label,
          nature: concept.nature,
          group: concept.group,
          is_union_income: checked,
          notes: concept.union_notes || null,
        });
        await this.loadAnalytics({ silent: true });
      } catch (error) {
        concept.is_union_income = previous;
        Swal.fire("Error", formatRemunerationError(error, "No fue posible actualizar la clasificación sindical."), "error");
      } finally {
        this.loadingUnionSetting = "";
      }
    },
    async openAlertRulesModal() {
      this.alertRulesModalVisible = true;
      await this.loadAlertRules();
    },
    async loadAlertRules() {
      this.loadingAlertRules = true;
      try {
        const response = await axios.get("/api/remuneraciones/book-alert-rules");
        this.alertRules = response.data?.data || [];
      } catch (error) {
        Swal.fire("Error", formatRemunerationError(error, "No fue posible cargar las reglas de alerta."), "error");
      } finally {
        this.loadingAlertRules = false;
      }
    },
    resetAlertRuleForm() {
      this.alertRuleForm = defaultAlertRuleForm();
    },
    onAlertRuleMetricChange() {
      if (this.alertRuleForm.metric !== "concept_amount") {
        this.alertRuleForm.concept_key = "";
        this.alertRuleForm.concept_label = "";
      }
    },
    syncAlertRuleConceptLabel() {
      const concept = this.alertConceptOptions.find((item) => item.key === this.alertRuleForm.concept_key);
      this.alertRuleForm.concept_label = concept?.label || "";
    },
    async createAlertRule() {
      if (this.alertRuleForm.metric === "concept_amount" && !this.alertRuleForm.concept_key) {
        Swal.fire("Falta concepto", "Selecciona el haber o descuento que quieres controlar.", "warning");
        return;
      }

      this.savingAlertRule = true;
      try {
        const payload = {
          ...this.alertRuleForm,
          threshold_value: Number(this.alertRuleForm.threshold_value),
          concept_key: this.alertRuleForm.metric === "concept_amount" ? this.alertRuleForm.concept_key : null,
          concept_label: this.alertRuleForm.metric === "concept_amount" ? this.alertRuleForm.concept_label : null,
          employee_type: this.alertRuleForm.employee_type || null,
          description: this.alertRuleForm.description || null,
        };

        await axios.post("/api/remuneraciones/book-alert-rules", payload);
        Swal.fire("Regla creada", "La alerta quedó disponible para los libros filtrados.", "success");
        this.resetAlertRuleForm();
        await this.loadAlertRules();
        await this.loadAnalytics({ silent: true });
      } catch (error) {
        Swal.fire("Error", formatRemunerationError(error, "No fue posible crear la regla de alerta."), "error");
      } finally {
        this.savingAlertRule = false;
      }
    },
    alertMetricLabel(metric, fallback = "") {
      return this.alertMetricOptions.find((item) => item.value === metric)?.label || fallback || metric || "-";
    },
    alertMetricKind(metric) {
      return this.alertMetricOptions.find((item) => item.value === metric)?.kind || "number";
    },
    alertOperatorLabel(operator) {
      return this.alertOperatorOptions.find((item) => item.value === operator)?.label || operator || "-";
    },
    formatAlertValue(value, metric) {
      if (value === null || value === undefined || value === "") return "-";
      const kind = this.alertMetricKind(metric);
      if (kind === "money") return money(value);
      if (kind === "percent") return this.percent(value);
      return Number(value || 0).toLocaleString("es-CL", { maximumFractionDigits: 2 });
    },
    alertDefinitionFor(alert) {
      if (alert?.explanation) return alert.explanation;
      const definition = this.systemAlertDefinitions.find((item) => item.id === alert?.rule_id || item.name === alert?.type);
      return definition?.description || "Alerta detectada según una regla de control del libro de remuneraciones.";
    },
    alertSuggestedReview(alert) {
      if (alert?.suggested_review) return alert.suggested_review;
      const definition = this.systemAlertDefinitions.find((item) => item.id === alert?.rule_id || item.name === alert?.type);
      return definition?.suggested_review || "Revisar el caso y justificarlo si corresponde a una situación válida.";
    },
    alertRuleSummary(rule) {
      const metric = rule.metric_label || this.alertMetricLabel(rule.metric, rule.concept_label);
      return `${metric} ${rule.operator_label || this.alertOperatorLabel(rule.operator).toLocaleLowerCase("es-CL")} ${this.formatAlertValue(rule.threshold_value, rule.metric)}`;
    },
    formatMetric(card) {
      if (card.type === "money") return this.compactMoney(card.value);
      if (card.type === "percent") return `${Number(card.value || 0).toLocaleString("es-CL", { maximumFractionDigits: 1 })}%`;
      return Number(card.value || 0).toLocaleString("es-CL");
    },
    compactMoney(value) {
      const amount = Number(value || 0);
      if (Math.abs(amount) >= 1000000) {
        return `$${(amount / 1000000).toLocaleString("es-CL", { maximumFractionDigits: 1 })} MM`;
      }
      return money(amount);
    },
    percent(value) {
      return `${Number(value || 0).toLocaleString("es-CL", { maximumFractionDigits: 1 })}%`;
    },
    variationText(variation) {
      if (!variation || variation.percent === null || variation.percent === undefined) return "Sin comparación";
      const sign = variation.absolute > 0 ? "+" : "";
      return `${sign}${this.percent(variation.percent)} vs período anterior`;
    },
    variationClass(variation) {
      if (!variation || variation.absolute === 0) return "text-muted";
      return variation.absolute > 0 ? "text-success" : "text-danger";
    },
    baseChartOptions(categories, moneyAxis = true) {
      return {
        chart: { toolbar: { show: false }, fontFamily: "inherit" },
        colors: ["#556ee6", "#f46a6a", "#34c38f", "#f1b44c", "#50a5f1"],
        dataLabels: { enabled: false },
        grid: { borderColor: "#eef2f7" },
        stroke: { width: 3, curve: "smooth" },
        xaxis: { categories, labels: { style: { colors: "#6c757d" } } },
        yaxis: {
          labels: {
            formatter: (value) => (moneyAxis ? this.compactMoney(value) : Number(value || 0).toLocaleString("es-CL")),
          },
        },
        tooltip: {
          y: {
            formatter: (value) => (moneyAxis ? money(value) : Number(value || 0).toLocaleString("es-CL")),
          },
        },
        legend: { position: "top" },
      };
    },
    barOptions(items, labelKey = "name", moneyAxis = true) {
      const categories = items.map((item) => item[labelKey] || "Sin concepto");
      const options = this.baseChartOptions(categories, moneyAxis);

      return {
        ...options,
        plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
        stroke: { width: 0 },
        xaxis: {
          ...options.xaxis,
          labels: {
            ...options.xaxis.labels,
            formatter: (value) => (moneyAxis ? this.compactMoney(value) : Number(value || 0).toLocaleString("es-CL")),
          },
        },
        yaxis: {
          labels: {
            maxWidth: 260,
            style: { colors: "#6c757d" },
            formatter: (value) => this.truncateChartLabel(value),
          },
        },
      };
    },
    truncateChartLabel(value) {
      const text = String(value || "");
      return text.length > 36 ? `${text.slice(0, 33)}...` : text;
    },
    columnOptions(categories, moneyAxis = false) {
      return {
        ...this.baseChartOptions(categories, moneyAxis),
        plotOptions: { bar: { borderRadius: 4, columnWidth: "48%" } },
        stroke: { width: 0 },
      };
    },
    severityLabel(value) {
      return {
        critica: "Crítica",
        requiere_revision: "Requiere revisión",
        informativa: "Informativa",
      }[value] || value || "-";
    },
    severityVariant(value) {
      return {
        critica: "danger",
        requiere_revision: "warning",
        informativa: "info",
      }[value] || "secondary";
    },
    async exportPdf() {
      if (!this.analytics.current_import) {
        Swal.fire("Sin datos", "No hay libros importados para exportar.", "warning");
        return;
      }
      this.exportingPdf = true;
      try {
        const pdfMake = getPdfMake();
        const metrics = this.analytics.metrics || {};
        const rows = [
          ["Dotación", metrics.workers || 0],
          ["Dotación pagada", metrics.paid_workers || 0],
          ["Masa de haberes", money(metrics.gross_total || 0)],
          ["Total imponible", money(metrics.gross_taxable_amount || 0)],
          ["Total descuentos", money(metrics.total_deductions || 0)],
          ["Total líquido", money(metrics.net_total || 0)],
          ["Promedio líquido", money(metrics.average_net_paid || 0)],
          ["Mediana líquida", money(metrics.median_net_paid || 0)],
          ["Tasa de descuentos", this.percent(metrics.discount_rate || 0)],
        ];
        const filterText = [
          this.analytics.current_import?.period,
          this.filters.employee_type ? `Tipo: ${this.filters.employee_type}` : "Tipo: Todos",
        ].filter(Boolean).join(" · ");

        pdfMake.createPdf({
          pageSize: "A4",
          pageOrientation: "landscape",
          pageMargins: [28, 28, 28, 34],
          content: [
            { text: "Estadísticas de remuneraciones", style: "title" },
            { text: filterText, style: "subtitle" },
            {
              columns: [
                { width: "50%", stack: [{ text: "Resumen ejecutivo", style: "section" }, this.pdfTable(["Indicador", "Valor"], rows)] },
                {
                  width: "50%",
                  stack: [
                    { text: "Conciliación y control", style: "section" },
                    this.pdfTable(["Indicador", "Valor"], [
                      ["Filas conciliadas", `${this.analytics.alerts?.reconciled_rows || 0} de ${metrics.workers || 0}`],
                      ["Tasa conciliación", this.percent(this.analytics.alerts?.reconciliation_rate || 0)],
                      ["Alertas", this.analytics.alerts?.total || 0],
                      ["Altas vs período anterior", this.analytics.variations?.new_workers || 0],
                      ["Bajas vs período anterior", this.analytics.variations?.missing_workers || 0],
                    ]),
                  ],
                },
              ],
              columnGap: 18,
            },
            { text: "Principales haberes", style: "section" },
            this.pdfTable(["Código", "Concepto", "Monto", "Trabajadores", "%"], (this.analytics.composition?.earnings || []).slice(0, 8).map((item) => [item.code, item.name, money(item.amount), item.workers, this.percent(item.share)])),
            { text: "Principales descuentos", style: "section", pageBreak: "before" },
            this.pdfTable(["Código", "Concepto", "Grupo", "Monto", "Trabajadores"], (this.analytics.composition?.deductions || []).slice(0, 10).map((item) => [item.code, item.name, item.group, money(item.amount), item.workers])),
            { text: "Alertas principales", style: "section" },
            this.pdfTable(["Severidad", "Tipo", "RUT", "Funcionario", "Mensaje"], (this.analytics.alerts?.items || []).slice(0, 12).map((item) => [this.severityLabel(item.severity), item.type, item.rut, item.employee_name, item.message])),
          ],
          styles: {
            title: { fontSize: 16, bold: true },
            subtitle: { fontSize: 9, color: "#6c757d", margin: [0, 4, 0, 14] },
            section: { fontSize: 11, bold: true, margin: [0, 12, 0, 6] },
            tableHeader: { bold: true, fillColor: "#eef2ff", color: "#343a40" },
          },
          defaultStyle: { fontSize: 8 },
          footer: (currentPage, pageCount) => ({
            text: `Generado ${this.analytics.generated_at || ""} · Página ${currentPage} de ${pageCount}`,
            alignment: "center",
            fontSize: 7,
            color: "#6c757d",
          }),
        }).download(`estadisticas_remuneraciones_${this.analytics.current_import?.period || "libro"}.pdf`);
      } catch (error) {
        Swal.fire("Error", "No fue posible generar el PDF de estadísticas.", "error");
      } finally {
        this.exportingPdf = false;
      }
    },
    pdfTable(headers, rows) {
      return {
        table: {
          headerRows: 1,
          widths: headers.map(() => "*"),
          body: [
            headers.map((text) => ({ text, style: "tableHeader" })),
            ...(rows.length ? rows : [["Sin datos", "", "", "", ""].slice(0, headers.length)]),
          ],
        },
        layout: {
          hLineColor: () => "#d9dee8",
          vLineColor: () => "#d9dee8",
          paddingLeft: () => 4,
          paddingRight: () => 4,
          paddingTop: () => 3,
          paddingBottom: () => 3,
        },
      };
    },
    pdfPositiveValue(value) {
      const amount = Number(value || 0);
      return Number.isFinite(amount) ? Math.abs(amount) : 0;
    },
    svgText(value) {
      return this.escapeXml(String(value ?? ""));
    },
    pdfChartEmptySvg(message = "Sin datos suficientes", width = 760, height = 220) {
      const safeMessage = this.svgText(message);
      return `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
        <rect x="0" y="0" width="${width}" height="${height}" rx="10" fill="#f8fafc" stroke="#d9e2ef"/>
        <text x="${width / 2}" y="${height / 2}" text-anchor="middle" font-family="Arial, sans-serif" font-size="13" fill="#6c757d">${safeMessage}</text>
      </svg>`;
    },
    pdfLineChartSvg(rows, options = {}) {
      const width = options.width || 760;
      const height = options.height || 220;
      const left = options.left || 78;
      const right = options.right || 24;
      const top = options.top || 20;
      const bottom = options.bottom || 44;
      const color = options.color || "#556ee6";
      const points = (rows || [])
        .map((row) => ({
          label: row.label || row.period || "",
          value: this.pdfPositiveValue(row.value ?? row.amount),
        }))
        .filter((row) => row.label || row.value > 0);

      if (!points.length) return this.pdfChartEmptySvg("Sin evolución para graficar", width, height);

      const maxValue = Math.max(...points.map((row) => row.value), 1);
      const axisMax = maxValue * 1.12;
      const plotWidth = width - left - right;
      const plotHeight = height - top - bottom;
      const xFor = (index) => {
        if (points.length === 1) return left + plotWidth / 2;
        return left + (plotWidth * index) / (points.length - 1);
      };
      const yFor = (value) => top + plotHeight - (value / axisMax) * plotHeight;
      const path = points
        .map((point, index) => `${index === 0 ? "M" : "L"} ${xFor(index).toFixed(2)} ${yFor(point.value).toFixed(2)}`)
        .join(" ");
      const ticks = [0, 0.25, 0.5, 0.75, 1].map((ratio) => {
        const y = top + plotHeight - ratio * plotHeight;
        const value = axisMax * ratio;
        return `<line x1="${left}" y1="${y.toFixed(2)}" x2="${width - right}" y2="${y.toFixed(2)}" stroke="#e9edf5" stroke-width="1"/>
          <text x="${left - 10}" y="${(y + 4).toFixed(2)}" text-anchor="end" font-family="Arial, sans-serif" font-size="10" fill="#697386">${this.svgText(this.compactMoney(value))}</text>`;
      }).join("");
      const every = points.length > 8 ? Math.ceil(points.length / 8) : 1;
      const labels = points.map((point, index) => {
        const show = index === 0 || index === points.length - 1 || index % every === 0;
        if (!show) return "";
        return `<text x="${xFor(index).toFixed(2)}" y="${height - 16}" text-anchor="middle" font-family="Arial, sans-serif" font-size="10" fill="#697386">${this.svgText(this.truncateChartLabel(point.label))}</text>`;
      }).join("");
      const circles = points.map((point, index) => {
        const x = xFor(index).toFixed(2);
        const y = yFor(point.value).toFixed(2);
        const showLabel = points.length <= 6 || index === points.length - 1;
        return `<circle cx="${x}" cy="${y}" r="4" fill="#ffffff" stroke="${color}" stroke-width="2"/>
          ${showLabel ? `<text x="${x}" y="${(Number(y) - 9).toFixed(2)}" text-anchor="middle" font-family="Arial, sans-serif" font-size="9" fill="#495057">${this.svgText(this.compactMoney(point.value))}</text>` : ""}`;
      }).join("");

      return `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
        <rect x="0" y="0" width="${width}" height="${height}" rx="10" fill="#ffffff" stroke="#d9e2ef"/>
        ${ticks}
        <line x1="${left}" y1="${top}" x2="${left}" y2="${top + plotHeight}" stroke="#d9e2ef" stroke-width="1"/>
        <line x1="${left}" y1="${top + plotHeight}" x2="${width - right}" y2="${top + plotHeight}" stroke="#d9e2ef" stroke-width="1"/>
        <path d="${path}" fill="none" stroke="${color}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        ${circles}
        ${labels}
      </svg>`;
    },
    pdfHorizontalBarChartSvg(rows, options = {}) {
      const width = options.width || 760;
      const top = options.top || 18;
      const bottom = options.bottom || 24;
      const left = options.left || 190;
      const right = options.right || 96;
      const rowHeight = options.rowHeight || 28;
      const color = options.color || "#556ee6";
      const items = (rows || [])
        .map((row) => ({
          label: row.label || row.name || row.type || "",
          value: this.pdfPositiveValue(row.value ?? row.amount),
        }))
        .filter((row) => row.label || row.value > 0);
      const height = options.height || Math.max(150, top + bottom + items.length * rowHeight);

      if (!items.length) return this.pdfChartEmptySvg("Sin datos para graficar", width, height);

      const maxValue = Math.max(...items.map((row) => row.value), 1);
      const plotWidth = width - left - right;
      const bars = items.map((item, index) => {
        const y = top + index * rowHeight;
        const barWidth = Math.max(2, (item.value / maxValue) * plotWidth);
        const label = this.truncateChartLabel(item.label);
        return `<text x="12" y="${y + 16}" font-family="Arial, sans-serif" font-size="10" fill="#495057">${this.svgText(label)}</text>
          <rect x="${left}" y="${y + 4}" width="${barWidth.toFixed(2)}" height="16" rx="5" fill="${color}"/>
          <text x="${width - 12}" y="${y + 16}" text-anchor="end" font-family="Arial, sans-serif" font-size="10" fill="#495057">${this.svgText(this.compactMoney(item.value))}</text>
          <line x1="${left}" y1="${y + 25}" x2="${width - right}" y2="${y + 25}" stroke="#eef2f7" stroke-width="1"/>`;
      }).join("");

      return `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
        <rect x="0" y="0" width="${width}" height="${height}" rx="10" fill="#ffffff" stroke="#d9e2ef"/>
        <line x1="${left}" y1="${top}" x2="${left}" y2="${height - bottom}" stroke="#d9e2ef" stroke-width="1"/>
        ${bars}
      </svg>`;
    },
    async exportConceptPdf() {
      if (!this.analytics.concept_drilldown) {
        Swal.fire("Sin datos", "Selecciona un haber para exportar sus estadísticas.", "warning");
        return;
      }

      this.exportingConceptPdf = true;
      try {
        const pdfMake = getPdfMake();
        const drilldown = this.analytics.concept_drilldown;
        const selected = drilldown.selected || {};
        const metrics = drilldown.metrics || {};
        const trendRows = (drilldown.trend || []).map((item) => ({
          label: item.period,
          value: item.amount,
        }));
        const byTypeRows = (drilldown.by_type || []).map((item) => ({
          label: item.type || "Sin tipo",
          value: item.amount,
        }));
        const topWorkerRows = (drilldown.detail_rows || [])
          .map((row) => ({
            label: `${row.employee_name || "Sin nombre"} · ${row.rut || ""}`,
            value: row.amount,
          }))
          .sort((a, b) => this.pdfPositiveValue(b.value) - this.pdfPositiveValue(a.value))
          .slice(0, 10);
        const trendChart = this.pdfLineChartSvg(trendRows, { width: 500, height: 220, color: "#556ee6" });
        const typeChart = this.pdfHorizontalBarChartSvg(byTypeRows, { width: 370, left: 128, right: 82, color: "#34c38f" });
        const workerChart = this.pdfHorizontalBarChartSvg(topWorkerRows, { width: 780, left: 260, right: 96, color: "#556ee6" });

        pdfMake.createPdf({
          pageSize: "A4",
          pageOrientation: "landscape",
          pageMargins: [28, 28, 28, 34],
          content: [
            { text: "Estadísticas por haber", style: "title" },
            { text: `${selected.label || "Haber"} · ${this.analytics.current_import?.period || ""}`, style: "subtitle" },
            {
              columns: [
                {
                  width: "34%",
                  stack: [
                    { text: "Indicadores", style: "section" },
                    this.pdfTable(["Indicador", "Valor"], [
                      ["Monto acumulado", money(metrics.total_amount || 0)],
                      ["Monto mensual", money(metrics.monthly_amount || 0)],
                      ["Trabajadores", metrics.workers || 0],
                      ["Promedio", money(metrics.average_amount || 0)],
                      ["Promedio mensual", money(metrics.monthly_average_amount || 0)],
                      ["Mediana", money(metrics.median_amount || 0)],
                      ["Participación", this.percent(metrics.share || 0)],
                      ["Variación", this.variationText(metrics.variation)],
                    ]),
                  ],
                },
                {
                  width: "66%",
                  stack: [
                    { text: "Evolución del haber", style: "section" },
                    { svg: trendChart, width: 500 },
                  ],
                },
              ],
              columnGap: 16,
            },
            { text: "Evolución", style: "section" },
            this.pdfTable(["Periodo", "Monto", "Trabajadores", "Promedio"], (drilldown.trend || []).map((item) => [item.period, money(item.amount), item.workers, money(item.average_amount)])),
            {
              columns: [
                {
                  width: "50%",
                  stack: [
                    { text: "Distribución por tipo funcionario", style: "section" },
                    { svg: typeChart, width: 370 },
                  ],
                },
                {
                  width: "50%",
                  stack: [
                    { text: "Detalle por tipo funcionario", style: "section" },
                    this.pdfTable(["Tipo", "Monto", "Trabajadores", "Promedio", "%"], (drilldown.by_type || []).map((item) => [item.type, money(item.amount), item.workers, money(item.average_amount), this.percent(item.share)])),
                  ],
                },
              ],
              columnGap: 16,
            },
            { text: "Top trabajadores por monto del haber", style: "section", pageBreak: "before" },
            { svg: workerChart, width: 780, margin: [0, 0, 0, 10] },
            { text: "Detalle por trabajador", style: "section", pageBreak: "before" },
            this.pdfTable(["RUT", "Funcionario", "Tipo", "Acumulado", "Mensual", "DT", "Horas", "Líquido"], (drilldown.detail_rows || []).map((row) => [row.rut, row.employee_name, row.employee_type, money(row.amount), money(row.monthly_amount), row.worked_days, row.weekly_hours, money(row.net_amount)])),
          ],
          styles: {
            title: { fontSize: 16, bold: true },
            subtitle: { fontSize: 9, color: "#6c757d", margin: [0, 4, 0, 14] },
            section: { fontSize: 11, bold: true, margin: [0, 12, 0, 6] },
            tableHeader: { bold: true, fillColor: "#eef2ff", color: "#343a40" },
          },
          defaultStyle: { fontSize: 8 },
          footer: (currentPage, pageCount) => ({
            text: `Generado ${this.analytics.generated_at || ""} · Página ${currentPage} de ${pageCount}`,
            alignment: "center",
            fontSize: 7,
            color: "#6c757d",
          }),
        }).download(`estadisticas_haber_${selected.code || "concepto"}_${this.analytics.current_import?.period || "libro"}.pdf`);
      } catch (error) {
        Swal.fire("Error", "No fue posible generar el PDF del haber.", "error");
      } finally {
        this.exportingConceptPdf = false;
      }
    },
    async exportUnionPdf() {
      const union = this.analytics.union_earnings || {};
      const metrics = union.metrics || {};
      if (!metrics.concept_count) {
        Swal.fire("Sin haberes sindicales", "Marca al menos un haber como sindical para exportar sus estadísticas.", "warning");
        return;
      }

      this.exportingUnionPdf = true;
      try {
        const pdfMake = getPdfMake();
        const trendRows = (union.trend || []).map((item) => ({ label: item.period, value: item.amount }));
        const conceptRows = (union.by_concept || []).slice(0, 10).map((item) => ({ label: item.label, value: item.amount }));
        const typeRows = (union.by_type || []).map((item) => ({ label: item.type, value: item.amount }));
        const workerRows = (union.detail_rows || []).slice(0, 10).map((item) => ({ label: `${item.employee_name || "Sin nombre"} · ${item.rut || ""}`, value: item.amount }));
        const trendChart = this.pdfLineChartSvg(trendRows, { width: 780, height: 220, color: "#556ee6" });
        const conceptChart = this.pdfHorizontalBarChartSvg(conceptRows, { width: 370, left: 150, right: 82, color: "#556ee6" });
        const typeChart = this.pdfHorizontalBarChartSvg(typeRows, { width: 370, left: 128, right: 82, color: "#34c38f" });
        const workerChart = this.pdfHorizontalBarChartSvg(workerRows, { width: 780, left: 260, right: 96, color: "#556ee6" });

        pdfMake.createPdf({
          pageSize: "A4",
          pageOrientation: "landscape",
          pageMargins: [28, 28, 28, 34],
          content: [
            { text: "Estadísticas de haberes sindicales", style: "title" },
            { text: `${this.analytics.current_import?.period || ""} · ${this.analytics.current_import?.original_filename || ""}`, style: "subtitle" },
            {
              columns: [
                {
                  width: "34%",
                  stack: [
                    { text: "Indicadores", style: "section" },
                    this.pdfTable(["Indicador", "Valor"], [
                      ["Total sindical", money(metrics.total_amount || 0)],
                      ["Trabajadores", metrics.workers || 0],
                      ["Promedio por trabajador", money(metrics.average_amount || 0)],
                      ["Promedio mensual", money(metrics.monthly_average_amount || 0)],
                      ["Conceptos marcados", metrics.concept_count || 0],
                      ["Participación sobre haberes", this.percent(metrics.share || 0)],
                    ]),
                  ],
                },
                {
                  width: "66%",
                  stack: [
                    { text: "Evolución de haberes sindicales", style: "section" },
                    { svg: trendChart, width: 500 },
                  ],
                },
              ],
              columnGap: 16,
            },
            {
              columns: [
                {
                  width: "50%",
                  stack: [
                    { text: "Top conceptos sindicales", style: "section" },
                    { svg: conceptChart, width: 370 },
                  ],
                },
                {
                  width: "50%",
                  stack: [
                    { text: "Por tipo funcionario", style: "section" },
                    { svg: typeChart, width: 370 },
                  ],
                },
              ],
              columnGap: 16,
            },
            { text: "Conceptos sindicales", style: "section" },
            this.pdfTable(["Código", "Haber", "Monto", "Trabajadores", "Promedio", "%"], (union.by_concept || []).map((item) => [item.code, item.name, money(item.amount), item.workers, money(item.average_amount), this.percent(item.share)])),
            { text: "Top trabajadores por haberes sindicales", style: "section", pageBreak: "before" },
            { svg: workerChart, width: 780, margin: [0, 0, 0, 10] },
            { text: "Detalle por trabajador", style: "section" },
            this.pdfTable(["RUT", "Funcionario", "Tipo", "Monto sindical", "Conceptos", "Haberes", "Líquido"], (union.detail_rows || []).map((row) => [row.rut, row.employee_name, row.employee_type, money(row.amount), (row.concepts || []).join(", "), money(row.gross_total), money(row.net_amount)])),
          ],
          styles: {
            title: { fontSize: 16, bold: true },
            subtitle: { fontSize: 9, color: "#6c757d", margin: [0, 4, 0, 14] },
            section: { fontSize: 11, bold: true, margin: [0, 12, 0, 6] },
            tableHeader: { bold: true, fillColor: "#eef2ff", color: "#343a40" },
          },
          defaultStyle: { fontSize: 8 },
          footer: (currentPage, pageCount) => ({
            text: `Generado ${this.analytics.generated_at || ""} · Página ${currentPage} de ${pageCount}`,
            alignment: "center",
            fontSize: 7,
            color: "#6c757d",
          }),
        }).download(`estadisticas_haberes_sindicales_${this.analytics.current_import?.period || "libro"}.pdf`);
      } catch (error) {
        Swal.fire("Error", "No fue posible generar el PDF de haberes sindicales.", "error");
      } finally {
        this.exportingUnionPdf = false;
      }
    },
    async exportExcel() {
      if (!this.analytics.current_import) {
        Swal.fire("Sin datos", "No hay libros importados para exportar.", "warning");
        return;
      }
      this.exportingExcel = true;
      try {
        const metrics = this.analytics.metrics || {};
        const sheets = [
          {
            name: "Resumen",
            rows: [
              ["Indicador", "Valor"],
              ["Periodo", this.analytics.current_import?.period || ""],
              ["Dotación", metrics.workers || 0],
              ["Dotación pagada", metrics.paid_workers || 0],
              ["Haberes", metrics.gross_total || 0],
              ["Imponible", metrics.gross_taxable_amount || 0],
              ["No imponible", metrics.gross_non_taxable_amount || 0],
              ["Descuentos", metrics.total_deductions || 0],
              ["Líquido", metrics.net_total || 0],
              ["Promedio líquido", metrics.average_net_paid || 0],
              ["Mediana líquida", metrics.median_net_paid || 0],
              ["Tasa descuentos", metrics.discount_rate || 0],
            ],
          },
          { name: "Series mensuales", rows: this.sheetFromObjects(this.analytics.trend || []) },
          { name: "Dotacion", rows: this.sheetFromObjects(this.analytics.staffing?.by_type || []) },
          { name: "Haberes por concepto", rows: this.sheetFromObjects(this.analytics.composition?.earnings || []) },
          { name: "Descuentos por concepto", rows: this.sheetFromObjects(this.analytics.composition?.deductions || []) },
          { name: "Tramos", rows: this.sheetFromObjects(this.analytics.staffing?.net_distribution || []) },
          { name: "Variaciones", rows: this.sheetFromObjects([...(this.analytics.variations?.top_increases || []), ...(this.analytics.variations?.top_decreases || [])]) },
          { name: "Alertas", rows: this.sheetFromObjects(this.analytics.alerts?.items || []) },
          { name: "Datos por trabajador", rows: this.sheetFromObjects(this.analytics.detail_rows || []) },
        ];

        this.downloadBlob(
          this.buildExcelXml(sheets),
          "application/vnd.ms-excel;charset=utf-8",
          `estadisticas_remuneraciones_${this.analytics.current_import?.period || "libro"}.xls`
        );
      } catch (error) {
        Swal.fire("Error", "No fue posible generar el Excel de estadísticas.", "error");
      } finally {
        this.exportingExcel = false;
      }
    },
    sheetFromObjects(items) {
      if (!items.length) return [["Sin datos"]];
      const keys = Object.keys(items[0]);
      return [keys, ...items.map((item) => keys.map((key) => item[key]))];
    },
    buildExcelXml(sheets) {
      const worksheets = sheets.map((sheet) => this.excelWorksheet(sheet.name, sheet.rows)).join("");
      return `<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
 <Styles>
  <Style ss:ID="Header"><Font ss:Bold="1"/><Interior ss:Color="#EEF2FF" ss:Pattern="Solid"/></Style>
 </Styles>
 ${worksheets}
</Workbook>`;
    },
    excelWorksheet(name, rows) {
      const safeName = this.escapeXml(String(name || "Hoja").replace(/[\\/?*\[\]:]/g, " ").trim().slice(0, 31) || "Hoja");
      const body = (rows.length ? rows : [["Sin datos"]])
        .map((row, rowIndex) => `<Row>${row.map((value) => this.excelCell(value, rowIndex === 0)).join("")}</Row>`)
        .join("");

      return `<Worksheet ss:Name="${safeName}"><Table>${body}</Table><WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><FreezePanes/><FrozenNoSplit/><SplitHorizontal>1</SplitHorizontal><TopRowBottomPane>1</TopRowBottomPane><ActivePane>2</ActivePane></WorksheetOptions></Worksheet>`;
    },
    excelCell(value, isHeader = false) {
      if (value === null || value === undefined) {
        return `<Cell${isHeader ? ' ss:StyleID="Header"' : ""}><Data ss:Type="String"></Data></Cell>`;
      }
      if (typeof value === "number" && Number.isFinite(value)) {
        return `<Cell${isHeader ? ' ss:StyleID="Header"' : ""}><Data ss:Type="Number">${value}</Data></Cell>`;
      }
      const normalized = typeof value === "object" ? JSON.stringify(value) : String(value);
      return `<Cell${isHeader ? ' ss:StyleID="Header"' : ""}><Data ss:Type="String">${this.escapeXml(normalized)}</Data></Cell>`;
    },
    escapeXml(value) {
      return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&apos;");
    },
    downloadBlob(content, type, filename) {
      const blob = new Blob(["\uFEFF", content], { type });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
    },
  },
};
</script>

<template>
  <div class="book-analytics d-flex flex-column gap-3">
    <BCard class="border-0 shadow-sm analytics-filter-card">
      <div class="row g-3 align-items-end">
        <div class="col-xl-3 col-lg-4">
          <label class="form-label">Libro</label>
          <select v-model="filters.import_id" class="form-select">
            <option value="">Último disponible</option>
            <option v-for="item in analytics.available_filters?.imports || []" :key="item.id" :value="item.id">{{ item.label }}</option>
          </select>
        </div>
        <div class="col-xl-2 col-lg-4">
          <label class="form-label">Desde</label>
          <select v-model="filters.from_period_id" class="form-select">
            <option value="">Sin inicio</option>
            <option v-for="period in analytics.available_filters?.periods || []" :key="period.id" :value="period.id">{{ period.name }}</option>
          </select>
        </div>
        <div class="col-xl-2 col-lg-4">
          <label class="form-label">Hasta</label>
          <select v-model="filters.to_period_id" class="form-select">
            <option value="">Sin término</option>
            <option v-for="period in analytics.available_filters?.periods || []" :key="period.id" :value="period.id">{{ period.name }}</option>
          </select>
        </div>
        <div class="col-xl-2 col-lg-4">
          <label class="form-label">Tipo funcionario</label>
          <select v-model="filters.employee_type" class="form-select">
            <option value="">Todos</option>
            <option v-for="type in analytics.available_filters?.employee_types || []" :key="type" :value="type">{{ type }}</option>
          </select>
        </div>
        <div class="col-xl-3 col-lg-8 d-flex flex-wrap gap-2">
          <BButton variant="primary" :disabled="loading" @click="loadAnalytics">
            <i class="bx bx-filter-alt me-1"></i> Aplicar
          </BButton>
          <BButton variant="outline-secondary" :disabled="loading" @click="clearFilters">Limpiar</BButton>
          <BButton variant="outline-danger" :disabled="loading || exportingPdf" @click="exportPdf">
            <i class="bx bxs-file-pdf me-1"></i> PDF
          </BButton>
          <BButton variant="outline-success" :disabled="loading || exportingExcel" @click="exportExcel">
            <i class="bx bx-spreadsheet me-1"></i> Excel
          </BButton>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>
    <BCard v-if="loading" class="border-0 shadow-sm"><LoadingState message="Cargando estadísticas del libro..." compact /></BCard>

    <template v-else>
      <BAlert v-if="!analytics.current_import" show variant="info">No hay libros de remuneraciones importados para construir estadísticas.</BAlert>

      <template v-else>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 analytics-period-bar">
          <div>
            <h5 class="mb-1">{{ analytics.current_import.period }}</h5>
            <div class="text-muted small">{{ analytics.current_import.original_filename }}</div>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <span class="badge bg-light text-dark">Conciliación {{ percent(analytics.alerts?.reconciliation_rate) }}</span>
            <span class="badge bg-light text-dark">{{ analytics.coverage?.imports || 0 }} libros en rango</span>
            <span class="badge bg-light text-dark">FTE 44h: {{ analytics.metrics?.estimated_fte_44h || 0 }}</span>
          </div>
        </div>

        <div class="analytics-kpi-grid">
          <BCard v-for="card in metricCards" :key="card.label" class="border-0 shadow-sm analytics-kpi-card">
            <span>{{ card.label }}</span>
            <strong>{{ formatMetric(card) }}</strong>
            <small v-if="card.variation" :class="variationClass(card.variation)">{{ variationText(card.variation) }}</small>
          </BCard>
        </div>

        <BTabs pills nav-class="analytics-tabs" content-class="pt-3">
          <BTab title="Resumen" active>
            <div class="row g-3">
              <div class="col-xl-8">
                <BCard class="border-0 shadow-sm h-100">
                  <h5 class="mb-3">Evolución últimos periodos</h5>
                  <apexchart type="line" height="320" :options="baseChartOptions(trendCategories)" :series="trendSeries" />
                </BCard>
              </div>
              <div class="col-xl-4">
                <BCard class="border-0 shadow-sm h-100">
                  <h5 class="mb-3">Haberes a líquido</h5>
                  <apexchart type="bar" height="320" :options="columnOptions((analytics.composition?.waterfall || []).map((item) => item.label))" :series="waterfallSeries" />
                </BCard>
              </div>
            </div>
          </BTab>

          <BTab title="Evolución">
            <BCard class="border-0 shadow-sm">
              <h5 class="mb-3">Serie mensual</h5>
              <div class="table-responsive">
                <table class="table table-sm align-middle">
                  <thead>
                    <tr>
                      <th>Periodo</th>
                      <th>Dotación</th>
                      <th>Con pago</th>
                      <th>Haberes</th>
                      <th>Descuentos</th>
                      <th>Líquido</th>
                      <th>Prom. líquido</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in analytics.trend" :key="item.key">
                      <td>{{ item.period }}</td>
                      <td>{{ item.workers }}</td>
                      <td>{{ item.paid_workers }}</td>
                      <td>{{ money(item.gross_total) }}</td>
                      <td>{{ money(item.total_deductions) }}</td>
                      <td>{{ money(item.net_total) }}</td>
                      <td>{{ money(item.average_net_paid) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </BCard>
          </BTab>

          <BTab title="Composición">
            <div class="row g-3">
              <div class="col-xl-6">
                <BCard class="border-0 shadow-sm h-100">
                  <h5 class="mb-3">Principales haberes</h5>
                  <apexchart type="bar" height="360" :options="barOptions((analytics.composition?.earnings || []).slice(0, 10))" :series="conceptEarningsSeries" />
                </BCard>
              </div>
              <div class="col-xl-6">
                <BCard class="border-0 shadow-sm h-100">
                  <h5 class="mb-3">Principales descuentos</h5>
                  <apexchart type="bar" height="360" :options="barOptions((analytics.composition?.deductions || []).slice(0, 10))" :series="conceptDeductionsSeries" />
                </BCard>
              </div>
            </div>
          </BTab>

          <BTab title="Haberes">
            <BCard class="border-0 shadow-sm">
              <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
                <div>
                  <h5 class="mb-1">Haberes del libro</h5>
                  <div class="text-muted small">{{ filteredEarningConcepts.length }} haberes encontrados</div>
                </div>
                <div class="analytics-concept-search">
                  <label class="form-label">Buscar haber</label>
                  <input
                    v-model="conceptQuery"
                    type="search"
                    class="form-control"
                    placeholder="Código o nombre"
                  />
                </div>
              </div>

              <div class="table-responsive analytics-concept-table">
                <table class="table table-hover align-middle">
                  <thead>
                    <tr>
                      <th>Código</th>
                      <th>Haber</th>
                      <th>Grupo</th>
                      <th class="text-end">Monto acumulado</th>
                      <th class="text-end">Monto mensual</th>
                      <th class="text-end">Trabajadores</th>
                      <th class="text-end">Promedio</th>
                      <th class="text-end">Participación</th>
                      <th class="text-center">Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="concept in filteredEarningConcepts" :key="concept.key">
                      <td>{{ concept.code || "-" }}</td>
                      <td>
                        <strong>{{ concept.label || concept.name }}</strong>
                        <div class="text-muted small">{{ concept.group }}</div>
                      </td>
                      <td>{{ concept.group }}</td>
                      <td class="text-end">{{ money(concept.amount) }}</td>
                      <td class="text-end">{{ money(concept.monthly_amount) }}</td>
                      <td class="text-end">{{ concept.workers }}</td>
                      <td class="text-end">{{ money(concept.average_amount) }}</td>
                      <td class="text-end">{{ percent(concept.share) }}</td>
                      <td class="text-center">
                        <BButton size="sm" variant="outline-primary" @click="openConceptModal(concept)">
                          <i class="bx bx-show me-1"></i> Ver
                        </BButton>
                      </td>
                    </tr>
                    <tr v-if="!filteredEarningConcepts.length">
                      <td colspan="9" class="text-center text-muted py-4">Sin haberes para los filtros seleccionados.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </BCard>
          </BTab>

          <BTab title="Sindicales">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 analytics-period-bar">
              <div>
                <h5 class="mb-1">Haberes sindicales</h5>
                <div class="text-muted small">Marca los haberes sindicales y revisa estadísticas agregadas con los filtros actuales.</div>
              </div>
              <BButton variant="outline-danger" :disabled="exportingUnionPdf || !(analytics.union_earnings?.metrics?.concept_count)" @click="exportUnionPdf">
                <i class="bx bxs-file-pdf me-1"></i> PDF sindical
              </BButton>
            </div>

            <div class="analytics-kpi-grid mb-3">
              <BCard v-for="card in unionMetricCards" :key="card.label" class="border-0 shadow-sm analytics-kpi-card">
                <span>{{ card.label }}</span>
                <strong>{{ formatMetric(card) }}</strong>
              </BCard>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-xl-7">
                <BCard class="border-0 shadow-sm h-100">
                  <h5 class="mb-3">Evolución sindical</h5>
                  <apexchart type="line" height="300" :options="baseChartOptions(unionTrendCategories)" :series="unionTrendSeries" />
                </BCard>
              </div>
              <div class="col-xl-5">
                <BCard class="border-0 shadow-sm h-100">
                  <h5 class="mb-3">Por tipo funcionario</h5>
                  <apexchart type="bar" height="300" :options="columnOptions((analytics.union_earnings?.by_type || []).map((item) => item.type), true)" :series="unionTypeSeries" />
                </BCard>
              </div>
              <div class="col-12">
                <BCard class="border-0 shadow-sm">
                  <h5 class="mb-3">Top haberes sindicales</h5>
                  <apexchart type="bar" height="340" :options="barOptions((analytics.union_earnings?.by_concept || []).slice(0, 10), 'name')" :series="unionConceptSeries" />
                </BCard>
              </div>
            </div>

            <BCard class="border-0 shadow-sm mb-3">
              <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
                <div>
                  <h5 class="mb-1">Clasificación de haberes</h5>
                  <div class="text-muted small">{{ filteredUnionConcepts.length }} haberes disponibles</div>
                </div>
                <div class="analytics-concept-search">
                  <label class="form-label">Buscar haber</label>
                  <input
                    v-model="unionConceptQuery"
                    type="search"
                    class="form-control"
                    placeholder="Código o nombre"
                  />
                </div>
              </div>

              <div class="table-responsive analytics-concept-table">
                <table class="table table-hover align-middle">
                  <thead>
                    <tr>
                      <th> Sindical </th>
                      <th>Código</th>
                      <th>Haber</th>
                      <th>Grupo</th>
                      <th class="text-end">Monto acumulado</th>
                      <th class="text-end">Monto mensual</th>
                      <th class="text-end">Trabajadores</th>
                      <th class="text-end">Participación</th>
                      <th class="text-center">Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="concept in filteredUnionConcepts" :key="`union-${concept.key}`">
                      <td>
                        <div class="form-check form-switch mb-0">
                          <input
                            class="form-check-input"
                            type="checkbox"
                            role="switch"
                            :checked="concept.is_union_income"
                            :disabled="loadingUnionSetting === concept.key"
                            @change="toggleUnionIncome(concept, $event.target.checked)"
                          />
                        </div>
                      </td>
                      <td>{{ concept.code || "-" }}</td>
                      <td>
                        <strong>{{ concept.label || concept.name }}</strong>
                        <div class="text-muted small">{{ concept.is_union_income ? "Clasificado como sindical" : "No sindical" }}</div>
                      </td>
                      <td>{{ concept.group }}</td>
                      <td class="text-end">{{ money(concept.amount) }}</td>
                      <td class="text-end">{{ money(concept.monthly_amount) }}</td>
                      <td class="text-end">{{ concept.workers }}</td>
                      <td class="text-end">{{ percent(concept.share) }}</td>
                      <td class="text-center">
                        <BButton size="sm" variant="outline-primary" @click="openConceptModal(concept)">
                          <i class="bx bx-show me-1"></i> Ver
                        </BButton>
                      </td>
                    </tr>
                    <tr v-if="!filteredUnionConcepts.length">
                      <td colspan="9" class="text-center text-muted py-4">Sin haberes para los filtros seleccionados.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </BCard>

            <BCard class="border-0 shadow-sm">
              <h5 class="mb-3">Detalle de trabajadores con haberes sindicales</h5>
              <div class="table-responsive analytics-detail-table">
                <table class="table table-hover table-sm align-middle">
                  <thead>
                    <tr>
                      <th>RUT</th>
                      <th>Funcionario</th>
                      <th>Tipo</th>
                      <th class="text-end">Monto sindical</th>
                      <th class="text-end">Conceptos</th>
                      <th class="text-end">Haberes</th>
                      <th class="text-end">Líquido</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in analytics.union_earnings?.detail_rows || []" :key="`union-detail-${row.rut}`">
                      <td>{{ row.rut }}</td>
                      <td>{{ row.employee_name }}</td>
                      <td>{{ row.employee_type }}</td>
                      <td class="text-end">{{ money(row.amount) }}</td>
                      <td class="text-end">{{ row.concept_count }}</td>
                      <td class="text-end">{{ money(row.gross_total) }}</td>
                      <td class="text-end">{{ money(row.net_amount) }}</td>
                    </tr>
                    <tr v-if="!(analytics.union_earnings?.detail_rows || []).length">
                      <td colspan="7" class="text-center text-muted py-4">Marca haberes sindicales para ver el detalle.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </BCard>
          </BTab>

          <BTab title="Dotación">
            <div class="row g-3">
              <div class="col-xl-7">
                <BCard class="border-0 shadow-sm h-100">
                  <h5 class="mb-3">Dotación versus masa de haberes</h5>
                  <apexchart type="bar" height="340" :options="columnOptions((analytics.staffing?.by_type || []).map((item) => item.type), false)" :series="staffingShareSeries" />
                </BCard>
              </div>
              <div class="col-xl-5">
                <BCard class="border-0 shadow-sm h-100">
                  <h5 class="mb-3">Tramos de líquido</h5>
                  <apexchart type="bar" height="340" :options="columnOptions((analytics.staffing?.net_distribution || []).map((item) => item.label), false)" :series="distributionSeries" />
                </BCard>
              </div>
              <div class="col-12">
                <BCard class="border-0 shadow-sm">
                  <h5 class="mb-3">Percentiles</h5>
                  <div class="analytics-percentiles">
                    <div><span>P10</span><strong>{{ money(analytics.staffing?.percentiles?.p10) }}</strong></div>
                    <div><span>P25</span><strong>{{ money(analytics.staffing?.percentiles?.p25) }}</strong></div>
                    <div><span>P50</span><strong>{{ money(analytics.staffing?.percentiles?.p50) }}</strong></div>
                    <div><span>P75</span><strong>{{ money(analytics.staffing?.percentiles?.p75) }}</strong></div>
                    <div><span>P90</span><strong>{{ money(analytics.staffing?.percentiles?.p90) }}</strong></div>
                    <div><span>P90/P10</span><strong>{{ analytics.staffing?.percentiles?.p90_p10_ratio || 0 }}</strong></div>
                  </div>
                </BCard>
              </div>
            </div>
          </BTab>

          <BTab title="Alertas">
            <div class="row g-3">
              <div class="col-xl-3">
                <BCard class="border-0 shadow-sm h-100 analytics-alert-summary">
                  <span>Total alertas</span>
                  <strong>{{ analytics.alerts?.total || 0 }}</strong>
                  <small>{{ analytics.alerts?.reconciled_rows || 0 }} filas conciliadas</small>
                  <BButton class="mt-3 w-100" size="sm" variant="outline-primary" @click="openAlertRulesModal">
                    <i class="bx bx-bell me-1"></i> Ver reglas
                  </BButton>
                </BCard>
              </div>
              <div class="col-xl-9">
                <BCard class="border-0 shadow-sm">
                  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                      <h5 class="mb-1">Alertas detectadas</h5>
                      <div class="text-muted small">Revisa la explicación de cada alerta y crea reglas propias desde el modal.</div>
                    </div>
                    <BButton variant="primary" size="sm" @click="openAlertRulesModal">
                      <i class="bx bx-slider-alt me-1"></i> Explicar / crear alerta
                    </BButton>
                  </div>
                  <div class="table-responsive">
                    <table class="table table-sm align-middle">
                      <thead>
                        <tr>
                          <th>Severidad</th>
                          <th>Tipo</th>
                          <th>RUT</th>
                          <th>Funcionario</th>
                          <th>Mensaje</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(alert, index) in analytics.alerts?.items || []" :key="index">
                          <td><span class="badge" :class="`bg-${severityVariant(alert.severity)}`">{{ severityLabel(alert.severity) }}</span></td>
                          <td>{{ alert.type }}</td>
                          <td>{{ alert.rut }}</td>
                          <td>{{ alert.employee_name }}</td>
                          <td>{{ alert.message }}</td>
                        </tr>
                        <tr v-if="!(analytics.alerts?.items || []).length">
                          <td colspan="5" class="text-center text-muted py-4">Sin alertas para los filtros seleccionados.</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </BCard>
              </div>
            </div>
          </BTab>

          <BTab title="Detalle">
            <BCard class="border-0 shadow-sm">
              <div class="table-responsive analytics-detail-table">
                <table class="table table-hover table-sm align-middle">
                  <thead>
                    <tr>
                      <th>RUT</th>
                      <th>Funcionario</th>
                      <th>Tipo</th>
                      <th>DT</th>
                      <th>Horas</th>
                      <th>Imponible</th>
                      <th>No imponible</th>
                      <th>Haberes</th>
                      <th>Desc. legal</th>
                      <th>Otros desc.</th>
                      <th>Líquido</th>
                      <th>Conciliación</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in analytics.detail_rows || []" :key="row.rut">
                      <td>{{ row.rut }}</td>
                      <td>{{ row.employee_name }}</td>
                      <td>{{ row.employee_type }}</td>
                      <td>{{ row.worked_days }}</td>
                      <td>{{ row.weekly_hours }}</td>
                      <td>{{ money(row.gross_taxable_amount) }}</td>
                      <td>{{ money(row.gross_non_taxable_amount) }}</td>
                      <td>{{ money(row.gross_total) }}</td>
                      <td>{{ money(row.legal_deductions) }}</td>
                      <td>{{ money(row.other_deductions) }}</td>
                      <td>{{ money(row.net_amount) }}</td>
                      <td><span class="badge" :class="row.reconciled ? 'bg-success' : 'bg-danger'">{{ row.reconciled ? "OK" : "Revisar" }}</span></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </BCard>
          </BTab>
        </BTabs>
      </template>
    </template>

    <BModal
      v-model="alertRulesModalVisible"
      title="Alertas del libro de remuneraciones"
      size="xl"
      hide-footer
      scrollable
      modal-class="analytics-alert-modal"
    >
      <div class="analytics-alert-modal-body">
        <div class="analytics-alert-kpis mb-3">
          <div>
            <span>Total alertas</span>
            <strong>{{ analytics.alerts?.total || 0 }}</strong>
          </div>
          <div>
            <span>Críticas</span>
            <strong>{{ analytics.alerts?.by_severity?.critica || 0 }}</strong>
          </div>
          <div>
            <span>Requieren revisión</span>
            <strong>{{ analytics.alerts?.by_severity?.requiere_revision || 0 }}</strong>
          </div>
          <div>
            <span>Informativas</span>
            <strong>{{ analytics.alerts?.by_severity?.informativa || 0 }}</strong>
          </div>
          <div>
            <span>Reglas activas</span>
            <strong>{{ analytics.alerts?.active_rule_count || 0 }}</strong>
          </div>
        </div>

        <BTabs nav-class="analytics-tabs" content-class="pt-3">
          <BTab title="Alertas detectadas">
            <div class="d-flex flex-column gap-3">
              <div v-for="(alert, index) in analytics.alerts?.items || []" :key="`alert-modal-${index}`" class="analytics-alert-item">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                  <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                      <span class="badge" :class="`bg-${severityVariant(alert.severity)}`">{{ severityLabel(alert.severity) }}</span>
                      <strong>{{ alert.type }}</strong>
                      <span v-if="!alert.system" class="badge bg-primary-subtle text-primary">Personalizada</span>
                    </div>
                    <div class="text-muted small">{{ alert.rut || "-" }} · {{ alert.employee_name || "Sin funcionario" }} · {{ alert.employee_type || "Sin tipo" }}</div>
                  </div>
                  <div class="text-end small text-muted">
                    <div>{{ alert.metric_label || "Valor" }}</div>
                    <strong class="text-body">{{ formatAlertValue(alert.metric_value, alert.metric || alert.rule_metric) }}</strong>
                  </div>
                </div>
                <div class="mt-3">
                  <div class="fw-semibold mb-1">{{ alert.message }}</div>
                  <div class="small text-muted mb-2"><strong>Qué significa:</strong> {{ alertDefinitionFor(alert) }}</div>
                  <div class="small text-muted"><strong>Qué revisar:</strong> {{ alertSuggestedReview(alert) }}</div>
                </div>
              </div>
              <BAlert v-if="!(analytics.alerts?.items || []).length" show variant="success" class="mb-0">
                No hay alertas detectadas para los filtros actuales.
              </BAlert>
            </div>
          </BTab>

          <BTab title="Reglas">
            <div class="row g-3">
              <div class="col-xl-6">
                <BCard class="border-0 shadow-sm h-100">
                  <h5 class="mb-3">Reglas del sistema</h5>
                  <div class="analytics-rule-list">
                    <div v-for="rule in systemAlertDefinitions" :key="rule.id" class="analytics-rule-item">
                      <div class="d-flex justify-content-between gap-2">
                        <strong>{{ rule.name }}</strong>
                        <span class="badge" :class="`bg-${severityVariant(rule.severity)}`">{{ severityLabel(rule.severity) }}</span>
                      </div>
                      <div class="small text-muted mt-1">{{ rule.description }}</div>
                      <div class="small mt-2"><strong>Criterio:</strong> {{ rule.metric_label }} {{ alertOperatorLabel(rule.operator).toLocaleLowerCase("es-CL") }} {{ formatAlertValue(rule.threshold_value, rule.metric) }}</div>
                    </div>
                  </div>
                </BCard>
              </div>
              <div class="col-xl-6">
                <BCard class="border-0 shadow-sm h-100">
                  <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                    <h5 class="mb-0">Reglas creadas</h5>
                    <BButton size="sm" variant="outline-secondary" :disabled="loadingAlertRules" @click="loadAlertRules">Actualizar</BButton>
                  </div>
                  <LoadingState v-if="loadingAlertRules" message="Cargando reglas..." compact />
                  <div v-else class="analytics-rule-list">
                    <div v-for="rule in alertRulesForModal" :key="`custom-rule-${rule.id}`" class="analytics-rule-item">
                      <div class="d-flex justify-content-between gap-2">
                        <strong>{{ rule.name }}</strong>
                        <span class="badge" :class="rule.enabled ? 'bg-success' : 'bg-secondary'">{{ rule.enabled ? "Activa" : "Inactiva" }}</span>
                      </div>
                      <div class="small text-muted mt-1">{{ rule.description || "Sin descripción." }}</div>
                      <div class="small mt-2"><strong>Criterio:</strong> {{ alertRuleSummary(rule) }}</div>
                      <div class="small text-muted mt-1">
                        Severidad: {{ severityLabel(rule.severity) }}
                        <span v-if="rule.employee_type"> · Tipo: {{ rule.employee_type }}</span>
                      </div>
                    </div>
                    <BAlert v-if="!alertRulesForModal.length" show variant="info" class="mb-0">
                      Aún no hay reglas personalizadas.
                    </BAlert>
                  </div>
                </BCard>
              </div>
            </div>
          </BTab>

          <BTab title="Crear alerta">
            <BCard class="border-0 shadow-sm">
              <form class="row g-3" @submit.prevent="createAlertRule">
                <div class="col-lg-6">
                  <label class="form-label">Nombre</label>
                  <input v-model="alertRuleForm.name" type="text" class="form-control" maxlength="160" required placeholder="Ej: Descuento superior al 45%" />
                </div>
                <div class="col-lg-3">
                  <label class="form-label">Severidad</label>
                  <select v-model="alertRuleForm.severity" class="form-select" required>
                    <option v-for="option in alertSeverityOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                  </select>
                </div>
                <div class="col-lg-3">
                  <label class="form-label">Estado</label>
                  <div class="form-check form-switch mt-2">
                    <input v-model="alertRuleForm.enabled" class="form-check-input" type="checkbox" role="switch" id="alert-rule-enabled" />
                    <label class="form-check-label" for="alert-rule-enabled">Activa</label>
                  </div>
                </div>
                <div class="col-12">
                  <label class="form-label">Descripción</label>
                  <textarea v-model="alertRuleForm.description" class="form-control" rows="2" maxlength="1200" placeholder="Explica para qué sirve esta alerta y qué se debe revisar."></textarea>
                </div>
                <div class="col-lg-4">
                  <label class="form-label">Métrica</label>
                  <select v-model="alertRuleForm.metric" class="form-select" required @change="onAlertRuleMetricChange">
                    <option v-for="option in alertMetricOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                  </select>
                </div>
                <div class="col-lg-4">
                  <label class="form-label">Condición</label>
                  <select v-model="alertRuleForm.operator" class="form-select" required>
                    <option v-for="option in alertOperatorOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                  </select>
                </div>
                <div class="col-lg-4">
                  <label class="form-label">Umbral</label>
                  <div class="input-group">
                    <input v-model="alertRuleForm.threshold_value" type="number" step="0.01" class="form-control" required />
                    <span v-if="selectedAlertMetricOption?.kind === 'percent'" class="input-group-text">%</span>
                  </div>
                </div>
                <div v-if="alertRuleForm.metric === 'concept_amount'" class="col-lg-6">
                  <label class="form-label">Haber o descuento</label>
                  <select v-model="alertRuleForm.concept_key" class="form-select" required @change="syncAlertRuleConceptLabel">
                    <option value="">Seleccionar concepto</option>
                    <option v-for="concept in alertConceptOptions" :key="`alert-concept-${concept.key}`" :value="concept.key">{{ concept.label }}</option>
                  </select>
                </div>
                <div class="col-lg-6">
                  <label class="form-label">Tipo funcionario</label>
                  <select v-model="alertRuleForm.employee_type" class="form-select">
                    <option value="">Todos</option>
                    <option v-for="type in analytics.available_filters?.employee_types || []" :key="`alert-type-${type}`" :value="type">{{ type }}</option>
                  </select>
                </div>
                <div class="col-12">
                  <div class="analytics-rule-preview">
                    <span>Vista previa</span>
                    <strong>{{ alertRuleForm.name || "Nueva alerta" }}</strong>
                    <small>
                      {{ alertMetricLabel(alertRuleForm.metric, alertRuleForm.concept_label) }}
                      {{ alertOperatorLabel(alertRuleForm.operator).toLocaleLowerCase("es-CL") }}
                      {{ alertRuleForm.threshold_value || "0" }}{{ selectedAlertMetricOption?.kind === "percent" ? "%" : "" }}
                    </small>
                  </div>
                </div>
                <div class="col-12 d-flex flex-wrap justify-content-end gap-2">
                  <BButton type="button" variant="outline-secondary" :disabled="savingAlertRule" @click="resetAlertRuleForm">Limpiar</BButton>
                  <BButton type="submit" variant="primary" :disabled="savingAlertRule">
                    <i class="bx bx-plus me-1"></i> Crear alerta
                  </BButton>
                </div>
              </form>
            </BCard>
          </BTab>
        </BTabs>
      </div>
    </BModal>

    <BModal
      v-model="conceptModalVisible"
      :title="selectedConcept ? selectedConcept.label : 'Estadísticas del haber'"
      size="xl"
      hide-footer
      scrollable
      modal-class="analytics-concept-modal"
      @hidden="resetConceptModal"
    >
      <LoadingState v-if="loadingConcept" message="Cargando estadísticas del haber..." compact />

      <div v-else-if="analytics.concept_drilldown" class="analytics-concept-modal-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
          <div>
            <span class="text-muted small">{{ selectedConcept?.nature }} · {{ selectedConcept?.group }}</span>
            <h5 class="mb-1">{{ selectedConcept?.label }}</h5>
            <div class="text-muted small">{{ analytics.current_import?.period }} · {{ analytics.current_import?.original_filename }}</div>
          </div>
          <BButton variant="outline-danger" :disabled="exportingConceptPdf" @click="exportConceptPdf">
            <i class="bx bxs-file-pdf me-1"></i> Exportar PDF
          </BButton>
        </div>

        <div class="analytics-concept-kpis mb-3">
          <div v-for="card in conceptMetricCards" :key="card.label">
            <span>{{ card.label }}</span>
            <strong>{{ formatMetric(card) }}</strong>
            <small v-if="card.variation" :class="variationClass(card.variation)">{{ variationText(card.variation) }}</small>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-xl-7">
            <BCard class="border-0 shadow-sm h-100">
              <h5 class="mb-3">Evolución del haber</h5>
              <apexchart type="line" height="280" :options="baseChartOptions(conceptTrendCategories)" :series="conceptTrendSeries" />
            </BCard>
          </div>
          <div class="col-xl-5">
            <BCard class="border-0 shadow-sm h-100">
              <h5 class="mb-3">Por tipo funcionario</h5>
              <apexchart type="bar" height="280" :options="columnOptions((analytics.concept_drilldown?.by_type || []).map((item) => item.type), true)" :series="conceptByTypeSeries" />
            </BCard>
          </div>
          <div class="col-12">
            <BCard class="border-0 shadow-sm">
              <h5 class="mb-3">Detalle por trabajador</h5>
              <div class="table-responsive analytics-detail-table">
                <table class="table table-hover table-sm align-middle">
                  <thead>
                    <tr>
                      <th>RUT</th>
                      <th>Funcionario</th>
                      <th>Tipo</th>
                      <th class="text-end">Monto acumulado</th>
                      <th class="text-end">Monto mensual</th>
                      <th class="text-end">DT</th>
                      <th class="text-end">Horas</th>
                      <th class="text-end">Haberes</th>
                      <th class="text-end">Descuentos</th>
                      <th class="text-end">Líquido</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(row, index) in analytics.concept_drilldown?.detail_rows || []" :key="`${row.rut}-${row.amount}-${index}`">
                      <td>{{ row.rut }}</td>
                      <td>{{ row.employee_name }}</td>
                      <td>{{ row.employee_type }}</td>
                      <td class="text-end">{{ money(row.amount) }}</td>
                      <td class="text-end">{{ money(row.monthly_amount) }}</td>
                      <td class="text-end">{{ row.worked_days }}</td>
                      <td class="text-end">{{ row.weekly_hours }}</td>
                      <td class="text-end">{{ money(row.gross_total) }}</td>
                      <td class="text-end">{{ money(row.total_deductions) }}</td>
                      <td class="text-end">{{ money(row.net_amount) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </BCard>
          </div>
        </div>
      </div>

      <BAlert v-else show variant="warning" class="mb-0">
        El haber seleccionado no tiene movimientos para los filtros actuales.
      </BAlert>
    </BModal>
  </div>
</template>

<style scoped>
.book-analytics { --analytics-primary: #556ee6; --analytics-border: #e6eaf2; --analytics-text: #293042; --analytics-muted: #74788d; }

:deep(.book-analytics .card) { border-radius: 14px; box-shadow: 0 5px 18px rgba(42, 48, 66, 0.065) !important; }
:deep(.book-analytics h5) { color: var(--analytics-text); font-size: 1rem; font-weight: 700; }

.analytics-filter-card,
.analytics-kpi-card {
  border-radius: 14px;
}

:deep(.analytics-filter-card .card-body) { padding: 1.15rem 1.25rem; }

.analytics-filter-card .form-label {
  color: #5f6678;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
}

.analytics-period-bar {
  background: linear-gradient(120deg, #ffffff, #f5f7ff);
  border: 1px solid var(--analytics-border);
  border-radius: 14px;
  padding: 1rem 1.2rem;
}

.analytics-kpi-grid {
  display: grid;
  gap: 0.85rem;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
}

.analytics-kpi-card span,
.analytics-alert-summary span,
.analytics-percentiles span {
  color: #6c757d;
  display: block;
  font-size: 0.74rem;
  font-weight: 700;
  text-transform: uppercase;
}

.analytics-kpi-card strong {
  color: var(--analytics-text);
  display: block;
  font-size: 1.25rem;
  line-height: 1.35;
}

:deep(.analytics-kpi-card .card-body) { border-left: 3px solid var(--analytics-primary); padding: .9rem 1rem; }
:deep(.book-analytics table) { border-collapse: separate; border-spacing: 0; margin-bottom: 0; }
:deep(.book-analytics table thead th) { background: #f7f8fb; border-bottom: 1px solid var(--analytics-border); color: #62697a; font-size: .68rem; font-weight: 800; letter-spacing: .035em; padding: .78rem .7rem; text-transform: uppercase; }
:deep(.book-analytics table tbody td) { border-color: #eef1f6; color: #3d4352; padding: .72rem .7rem; }
:deep(.book-analytics table tbody tr:hover td) { background: #f8f9ff; }
:deep(.book-analytics .badge) { border-radius: 999px; padding: .38rem .62rem; }
:deep(.book-analytics .form-control), :deep(.book-analytics .form-select) { border-color: #dfe4ed; border-radius: 8px; min-height: 40px; }
:deep(.book-analytics .form-control:focus), :deep(.book-analytics .form-select:focus) { border-color: #8091ed; box-shadow: 0 0 0 3px rgba(85,110,230,.12); }

.analytics-kpi-card small {
  display: block;
  font-size: 0.72rem;
}

:deep(.analytics-tabs) {
  gap: 0.35rem;
}

:deep(.analytics-tabs .nav-link) {
  border-radius: 999px;
  color: #5f6678;
  font-weight: 700;
  padding: 0.45rem 0.95rem;
}

:deep(.analytics-tabs .nav-link.active) {
  background: #556ee6;
  color: #ffffff;
}

.analytics-percentiles {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
}

.analytics-percentiles div {
  background: #f8fafc;
  border: 1px solid #e9edf3;
  border-radius: 8px;
  padding: 0.75rem;
}

.analytics-percentiles strong,
.analytics-alert-summary strong {
  color: #343a40;
  display: block;
  font-size: 1.2rem;
  line-height: 1.4;
}

.analytics-concept-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  max-height: 640px;
  overflow: auto;
  padding-right: 0.25rem;
}

.analytics-concept-item {
  background: #ffffff;
  border: 1px solid #e3e8f2;
  border-radius: 8px;
  color: #343a40;
  padding: 0.65rem 0.75rem;
  text-align: left;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.analytics-concept-item:hover,
.analytics-concept-item.active {
  border-color: #556ee6;
  box-shadow: 0 0 0 3px rgba(85, 110, 230, 0.12);
}

.analytics-concept-item span,
.analytics-concept-header h5 {
  display: block;
  font-weight: 700;
}

.analytics-concept-item small,
.analytics-concept-header span {
  color: #6c757d;
  display: block;
  font-size: 0.75rem;
}

.analytics-concept-header {
  background: #ffffff;
  border: 1px solid #e9edf3;
  border-radius: 10px;
  padding: 1rem;
}

.analytics-concept-kpis {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
}

.analytics-concept-kpis div {
  background: #ffffff;
  border: 1px solid #e9edf3;
  border-radius: 8px;
  padding: 0.75rem;
}

.analytics-concept-kpis span {
  color: #6c757d;
  display: block;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
}

.analytics-concept-kpis strong {
  color: #343a40;
  display: block;
  font-size: 1.1rem;
  line-height: 1.35;
}

.analytics-concept-kpis small {
  display: block;
  font-size: 0.72rem;
}

.analytics-concept-search {
  min-width: min(100%, 320px);
}

.analytics-concept-search .form-label {
  color: #6c757d;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
}

.analytics-concept-table {
  max-height: 640px;
}

.analytics-concept-table th {
  background: #f8fafc;
  color: #6b7280;
  font-size: 0.72rem;
  position: sticky;
  text-transform: uppercase;
  top: 0;
  z-index: 1;
}

.analytics-concept-table td,
.analytics-concept-table th {
  white-space: nowrap;
}

.analytics-concept-table td:nth-child(2) {
  min-width: 260px;
  white-space: normal;
}

:deep(.analytics-concept-modal .modal-dialog) {
  max-width: min(1180px, calc(100vw - 2rem));
}

:deep(.analytics-concept-modal .modal-content),
:deep(.analytics-alert-modal .modal-content) { border: 0; border-radius: 16px; box-shadow: 0 18px 55px rgba(31,38,56,.2); overflow: hidden; }
:deep(.analytics-concept-modal .modal-header),
:deep(.analytics-alert-modal .modal-header) { background: linear-gradient(120deg, #556ee6, #6f7fe8); border: 0; color: #fff; padding: 1.15rem 1.3rem; }
:deep(.analytics-concept-modal .modal-title),
:deep(.analytics-alert-modal .modal-title) { color: #fff; }
:deep(.analytics-concept-modal .btn-close),
:deep(.analytics-alert-modal .btn-close) { filter: brightness(0) invert(1); opacity: .85; }

:deep(.analytics-alert-modal .modal-dialog) {
  max-width: min(1180px, calc(100vw - 2rem));
}

:deep(.analytics-concept-modal .modal-body) {
  background: #f6f8fc;
}

:deep(.analytics-alert-modal .modal-body) {
  background: #f6f8fc;
}

.analytics-concept-modal-body {
  min-height: 420px;
}

.analytics-alert-modal-body {
  min-height: 420px;
}

.analytics-alert-kpis {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
}

.analytics-alert-kpis div,
.analytics-rule-preview {
  background: #ffffff;
  border: 1px solid #e9edf3;
  border-radius: 8px;
  padding: 0.75rem;
}

.analytics-alert-kpis span,
.analytics-rule-preview span {
  color: #6c757d;
  display: block;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
}

.analytics-alert-kpis strong,
.analytics-rule-preview strong {
  color: #343a40;
  display: block;
  font-size: 1.1rem;
  line-height: 1.35;
}

.analytics-rule-preview small {
  color: #6c757d;
  display: block;
  margin-top: 0.2rem;
}

.analytics-alert-item,
.analytics-rule-item {
  background: #ffffff;
  border: 1px solid #e9edf3;
  border-radius: 8px;
  padding: 0.85rem;
}

.analytics-rule-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  max-height: 560px;
  overflow: auto;
  padding-right: 0.25rem;
}

.analytics-detail-table {
  max-height: 560px;
}

.analytics-detail-table th {
  background: #f8fafc;
  color: #6b7280;
  font-size: 0.72rem;
  position: sticky;
  top: 0;
  text-transform: uppercase;
  z-index: 1;
}

.analytics-detail-table td,
.analytics-detail-table th {
  white-space: nowrap;
}
</style>
