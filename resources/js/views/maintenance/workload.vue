<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import pdfMake from "pdfmake/build/pdfmake";
import pdfFonts from "pdfmake/build/vfs_fonts";

// `vfs_fonts` es CommonJS (module.exports = vfs). Según el bundler puede llegar
// como objeto `vfs` directo o como `{ pdfMake: { vfs } }`.
pdfMake.vfs = pdfFonts?.pdfMake?.vfs || pdfFonts;

export default {
  components: { Layout },
  data() {
    const today = new Date().toISOString().slice(0, 10);

    return {
      loading: false,
      error: null,
      defaultToDate: today,
      catalogsLoading: false,
      catalogs: {
        priorities: ["Crítico", "Alta", "Media", "Baja"],
        statuses: ["Sin comenzar", "En proceso", "En espera", "Pausado", "Terminado", "Anulado"],
        dependencies: [],
        assignees: [],
        maintenance_assignees: [],
      },
      filters: {
        from: "",
        to: today,
        assignee: "",
        dependency_id: "",
        priority: "",
        status: "",
      },
      rows: [],
      totals: {
        assigned: 0,
        pending: 0,
        overdue: 0,
        critical: 0,
        closed: 0,
      },
    };
  },
  computed: {
    summaryCards() {
      return [
        {
          label: "OT asignadas",
          value: this.totals.assigned,
          detail: "Total filtrado",
          icon: "mdi-clipboard-check-outline",
          tone: "blue",
        },
        {
          label: "Pendientes",
          value: this.totals.pending,
          detail: `${this.formatPercent(this.totals.pending, this.totals.assigned)} del total`,
          icon: "mdi-progress-clock",
          tone: "green",
        },
        {
          label: "Vencidas",
          value: this.totals.overdue,
          detail: "Requieren atención",
          icon: "mdi-calendar-alert",
          tone: "red",
        },
        {
          label: "Críticas",
          value: this.totals.critical,
          detail: "Prioridad crítica abierta",
          icon: "mdi-alert-outline",
          tone: "amber",
        },
        {
          label: "Cerradas",
          value: this.totals.closed,
          detail: `${this.formatPercent(this.totals.closed, this.totals.assigned)} resuelto`,
          icon: "mdi-check-circle-outline",
          tone: "slate",
        },
      ];
    },
    activeFiltersCount() {
      return [
        this.filters.from,
        this.filters.to && this.filters.to !== this.defaultToDate ? this.filters.to : "",
        this.filters.assignee,
        this.filters.dependency_id,
        this.filters.priority,
        this.filters.status,
      ].filter(Boolean).length;
    },
    activeFilterLabels() {
      const labels = [];

      if (this.filters.from) labels.push(`Desde ${this.formatDate(this.filters.from)}`);
      if (this.filters.to && this.filters.to !== this.defaultToDate) labels.push(`Hasta ${this.formatDate(this.filters.to)}`);
      if (this.filters.assignee) labels.push(`Responsable: ${this.assigneeLabel(this.filters.assignee)}`);
      if (this.filters.dependency_id) labels.push(`Dependencia: ${this.selectedDependencyLabel()}`);
      if (this.filters.priority) labels.push(`Criticidad: ${this.filters.priority}`);
      if (this.filters.status) labels.push(`Estado: ${this.filters.status}`);

      return labels;
    },
    hasRows() {
      return this.rows.length > 0;
    },
    assigneeOptions() {
      const catalog = this.catalogs.maintenance_assignees || [];

      if (catalog.length) {
        return catalog.map((assignee) => ({
          value: assignee.value || assignee.full_name,
          label: assignee.label || assignee.full_name,
        }));
      }

      return (this.catalogs.assignees || []).map((assignee) => ({
        value: assignee,
        label: assignee,
      }));
    },
  },
  mounted() {
    this.loadCatalogs();
    this.loadWorkload();
  },
  methods: {
    formatNumber(value) {
      return new Intl.NumberFormat("es-CL").format(Number(value || 0));
    },
    formatPercent(value, total) {
      const denominator = Number(total || 0);
      if (!denominator) return "0%";

      return `${Math.round((Number(value || 0) / denominator) * 100)}%`;
    },
    formatDate(value) {
      if (!value) return "-";

      const [year, month, day] = String(value).slice(0, 10).split("-");
      if (!year || !month || !day) return String(value);

      return `${day}-${month}-${year}`;
    },
    formatDateTime(value = new Date()) {
      const date = value instanceof Date ? value : new Date(value);
      if (Number.isNaN(date.getTime())) return "";

      return date.toLocaleString("es-CL", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      });
    },
    selectedDependencyLabel() {
      const dependency = (this.catalogs.dependencies || []).find(
        (item) => Number(item.id) === Number(this.filters.dependency_id)
      );

      return dependency ? `${dependency.code} - ${dependency.name}` : "Seleccionada";
    },
    assigneeLabel(value) {
      if (!value) return "";

      const option = this.assigneeOptions.find((assignee) => assignee.value === value);
      return option?.label || value;
    },
    rowAttentionLevel(row) {
      if (Number(row.overdue || 0) > 0 || Number(row.critical || 0) > 0) return "Atención";
      if (Number(row.pending || 0) > 0) return "En curso";
      return "Al día";
    },
    rowAttentionClass(row) {
      if (Number(row.overdue || 0) > 0 || Number(row.critical || 0) > 0) return "workload-pill--danger";
      if (Number(row.pending || 0) > 0) return "workload-pill--active";
      return "workload-pill--done";
    },
    barStyle(value, total) {
      const denominator = Number(total || 0);
      const width = denominator ? Math.max(4, Math.round((Number(value || 0) / denominator) * 100)) : 0;

      return { width: `${width}%` };
    },
    resetFilters() {
      const today = new Date().toISOString().slice(0, 10);
      this.defaultToDate = today;
      this.filters = {
        from: "",
        to: today,
        assignee: "",
        dependency_id: "",
        priority: "",
        status: "",
      };
      this.loadWorkload();
    },
    exportAssigneeFromRow(row) {
      this.exportAssigneePdf(row.assignee);
    },
    pdfFooter() {
      return (currentPage, pageCount) => ({
        columns: [
          { text: "CNSC Gestion - Mantencion", color: "#6b7280", fontSize: 8 },
          { text: `Pagina ${currentPage} de ${pageCount}`, alignment: "right", color: "#6b7280", fontSize: 8 },
        ],
        margin: [32, 0, 32, 0],
      });
    },
    pdfTableLayout() {
      return {
        hLineColor: () => "#dbe5f4",
        vLineColor: () => "#dbe5f4",
        hLineWidth: (i, node) => (i === 0 || i === node.table.body.length ? 0 : 0.7),
        vLineWidth: () => 0,
        paddingLeft: () => 8,
        paddingRight: () => 8,
        paddingTop: () => 7,
        paddingBottom: () => 7,
      };
    },
    pdfCompactTableLayout() {
      return {
        hLineColor: () => "#dbe5f4",
        vLineColor: () => "#dbe5f4",
        hLineWidth: (i, node) => (i === 0 || i === node.table.body.length ? 0 : 0.6),
        vLineWidth: () => 0,
        paddingLeft: () => 4,
        paddingRight: () => 4,
        paddingTop: () => 5,
        paddingBottom: () => 5,
      };
    },
    pdfCardTable(cards) {
      return {
        table: {
          widths: cards.map(() => "*"),
          body: [
            cards.map((card) => ({
              stack: [
                { text: card.label, color: "#667085", fontSize: 9, margin: [0, 0, 0, 4] },
                { text: String(card.value), color: card.color || "#243047", fontSize: 18, bold: true },
                { text: card.detail || "", color: "#7a849a", fontSize: 8, margin: [0, 4, 0, 0] },
              ],
              margin: [10, 8, 10, 8],
              fillColor: card.fill || "#f8fafc",
            })),
          ],
        },
        layout: "noBorders",
        margin: [0, 0, 0, 14],
      };
    },
    async loadCatalogs() {
      this.catalogsLoading = true;
      try {
        const response = await axios.get("/api/maintenance/work-orders/catalogs");
        this.catalogs = response.data;
      } finally {
        this.catalogsLoading = false;
      }
    },
    async loadWorkload() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/maintenance/work-orders/workload", {
          params: this.filters,
        });
        this.rows = response.data.rows || [];
        this.totals = response.data.totals || this.totals;
      } catch (error) {
        this.error = error.response?.data?.message || error.message || "Error cargando carga de trabajo";
      } finally {
        this.loading = false;
      }
    },
    exportExcel() {
      const escapeHtml = (value) =>
        String(value ?? "")
          .replaceAll("&", "&amp;")
          .replaceAll("<", "&lt;")
          .replaceAll(">", "&gt;")
          .replaceAll('"', "&quot;")
          .replaceAll("'", "&#039;");

      const headers = ["Responsable", "OT asignadas", "Pendientes", "Vencidas", "Críticas", "Cerradas"];
      const rows = this.rows.map((row) => [
        row.assignee_label || row.assignee,
        row.assigned,
        row.pending,
        row.overdue,
        row.critical,
        row.closed,
      ]);
      rows.push(["TOTAL", this.totals.assigned, this.totals.pending, this.totals.overdue, this.totals.critical, this.totals.closed]);

      const html = `<!doctype html>
<html>
  <head><meta charset="utf-8" /></head>
  <body>
    <table border="1">
      <thead>
        <tr>${headers.map((h) => `<th>${escapeHtml(h)}</th>`).join("")}</tr>
      </thead>
      <tbody>
        ${rows
          .map((r) => `<tr>${r.map((c) => `<td>${escapeHtml(c)}</td>`).join("")}</tr>`)
          .join("")}
      </tbody>
    </table>
  </body>
</html>`;

      const blob = new Blob([html], { type: "application/vnd.ms-excel;charset=utf-8" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `carga-trabajo-${new Date().toISOString().slice(0, 10)}.xls`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    },
    exportPdf() {
      const tableHeader = ["Responsable", "Asignadas", "Pendientes", "Vencidas", "Criticas", "Cerradas", "Estado"].map((text) => ({
        text,
        bold: true,
        color: "#ffffff",
        fillColor: "#334155",
        alignment: text === "Responsable" ? "left" : "center",
      }));

      const body = this.rows.map((row, index) => [
        { text: row.assignee_label || row.assignee, color: "#243047", bold: true },
        { text: this.formatNumber(row.assigned), alignment: "center" },
        { text: this.formatNumber(row.pending), alignment: "center", color: Number(row.pending) ? "#047857" : "#64748b" },
        { text: this.formatNumber(row.overdue), alignment: "center", color: Number(row.overdue) ? "#b91c1c" : "#64748b" },
        { text: this.formatNumber(row.critical), alignment: "center", color: Number(row.critical) ? "#b45309" : "#64748b" },
        { text: this.formatNumber(row.closed), alignment: "center" },
        {
          text: this.rowAttentionLevel(row),
          alignment: "center",
          color: Number(row.overdue || 0) || Number(row.critical || 0) ? "#b91c1c" : Number(row.pending || 0) ? "#3152c9" : "#047857",
          bold: true,
        },
      ].map((cell) => ({
        fillColor: index % 2 === 0 ? "#ffffff" : "#f8fafc",
        ...cell,
      })));

      body.push([
        { text: "TOTAL", bold: true, fillColor: "#eef4ff" },
        { text: this.formatNumber(this.totals.assigned), bold: true, alignment: "center", fillColor: "#eef4ff" },
        { text: this.formatNumber(this.totals.pending), bold: true, alignment: "center", fillColor: "#eef4ff" },
        { text: this.formatNumber(this.totals.overdue), bold: true, alignment: "center", fillColor: "#eef4ff" },
        { text: this.formatNumber(this.totals.critical), bold: true, alignment: "center", fillColor: "#eef4ff" },
        { text: this.formatNumber(this.totals.closed), bold: true, alignment: "center", fillColor: "#eef4ff" },
        { text: "", fillColor: "#eef4ff" },
      ]);

      const kpiCards = [
        { label: "OT asignadas", value: this.formatNumber(this.totals.assigned), detail: "Total filtrado", fill: "#eef4ff", color: "#3152c9" },
        { label: "Pendientes", value: this.formatNumber(this.totals.pending), detail: this.formatPercent(this.totals.pending, this.totals.assigned), fill: "#ecfdf5", color: "#047857" },
        { label: "Vencidas", value: this.formatNumber(this.totals.overdue), detail: "Fuera de plazo", fill: "#fef2f2", color: "#b91c1c" },
        { label: "Criticas", value: this.formatNumber(this.totals.critical), detail: "Prioridad critica", fill: "#fffbeb", color: "#b45309" },
        { label: "Cerradas", value: this.formatNumber(this.totals.closed), detail: this.formatPercent(this.totals.closed, this.totals.assigned), fill: "#f8fafc", color: "#475569" },
      ];

      const docDefinition = {
        pageOrientation: "landscape",
        pageMargins: [32, 34, 32, 42],
        footer: this.pdfFooter(),
        content: [
          {
            columns: [
              {
                stack: [
                  { text: "MANTENCION", style: "eyebrow" },
                  { text: "Carga de trabajo", style: "header" },
                  { text: `Generado: ${this.formatDateTime()}`, style: "muted" },
                ],
              },
              { text: "Reporte operativo", style: "reportTag", alignment: "right" },
            ],
            margin: [0, 0, 0, 12],
          },
          { canvas: [{ type: "line", x1: 0, y1: 0, x2: 780, y2: 0, lineWidth: 1, lineColor: "#dbe5f4" }], margin: [0, 0, 0, 12] },
          { text: "Filtros aplicados", style: "sectionTitle" },
          { text: this.activeFilterLabels.join(" | ") || "Sin filtros aplicados", style: "filterLine", margin: [0, 0, 0, 12] },
          this.pdfCardTable(kpiCards),
          { text: "Resumen por responsable", style: "sectionTitle" },
          {
            table: {
              headerRows: 1,
              widths: ["*", 70, 70, 70, 70, 70, 86],
              body: [tableHeader, ...body],
            },
            layout: this.pdfTableLayout(),
          },
        ].filter(Boolean),
        styles: {
          eyebrow: { fontSize: 8, bold: true, color: "#5b74df", characterSpacing: 0.8 },
          header: { fontSize: 20, bold: true, color: "#243047", margin: [0, 2, 0, 4] },
          muted: { fontSize: 9, color: "#667085" },
          reportTag: { fontSize: 10, color: "#475569", margin: [0, 10, 0, 0] },
          sectionTitle: { fontSize: 11, bold: true, color: "#243047", margin: [0, 0, 0, 6] },
          filterLine: { fontSize: 9, color: "#53607a" },
        },
        defaultStyle: { fontSize: 9, color: "#364154" },
      };

      pdfMake.createPdf(docDefinition).download(`carga-trabajo-${new Date().toISOString().slice(0, 10)}.pdf`);
    },
    async exportAssigneePdf(assigneeOverride = null) {
      const assignee = typeof assigneeOverride === "string" ? assigneeOverride : this.filters.assignee;
      const assigneeLabel = this.assigneeLabel(assignee);

      if (!assignee) {
        alert("Selecciona un responsable para exportar su pauta.");
        return;
      }

      this.loading = true;
      this.error = null;

      try {
        const response = await axios.get("/api/maintenance/work-orders/assignee-report", {
          params: {
            ...this.filters,
            assignee,
          },
        });

        const tasks = response.data?.data || [];
        const today = new Date().toISOString().slice(0, 10);
        const actaDate = this.filters.to || today;

        const extraFilters = [
          this.filters.from ? `Desde: ${this.formatDate(this.filters.from)}` : null,
          this.filters.dependency_id ? `Dependencia: ${this.selectedDependencyLabel()}` : null,
          this.filters.priority ? `Criticidad: ${this.filters.priority}` : null,
          this.filters.status ? `Estado: ${this.filters.status}` : null,
        ]
          .filter(Boolean)
          .join(" | ");

        const filtersText = `Dia del acta: ${this.formatDate(actaDate)}${extraFilters ? ` | ${extraFilters}` : ""}`;

        const priorityOrder = ["Crítico", "Alta", "Media", "Baja"];
        const priorityCounts = tasks.reduce((acc, task) => {
          const key = task.priority || "Sin prioridad";
          acc[key] = (acc[key] || 0) + 1;
          return acc;
        }, {});

        const statusCounts = tasks.reduce((acc, task) => {
          const key = task.status || "Sin estado";
          acc[key] = (acc[key] || 0) + 1;
          return acc;
        }, {});

        const priorityColor = (priority) => ({
          "Crítico": "#b91c1c",
          "Alta": "#be123c",
          "Media": "#b45309",
          "Baja": "#0369a1",
        }[priority] || "#475569");

        const priorityStatsRows = [
          ["Prioridad", "Cantidad"].map((text) => ({ text, bold: true, color: "#ffffff", fillColor: "#334155" })),
          ...priorityOrder
            .filter((key) => priorityCounts[key])
            .map((key) => [{ text: key, color: priorityColor(key), bold: true }, { text: String(priorityCounts[key]), alignment: "center" }]),
        ];

        Object.keys(priorityCounts)
          .filter((key) => !priorityOrder.includes(key))
          .sort()
          .forEach((key) => {
            priorityStatsRows.push([{ text: key, bold: true }, { text: String(priorityCounts[key]), alignment: "center" }]);
          });

        if (priorityStatsRows.length === 1) {
          priorityStatsRows.push([{ text: "Sin tareas", colSpan: 2, alignment: "center", color: "#667085" }, ""]);
        }

        const statusStatsRows = [
          ["Estado", "Cantidad"].map((text) => ({ text, bold: true, color: "#ffffff", fillColor: "#334155" })),
          ...Object.keys(statusCounts)
            .sort()
            .map((key) => [{ text: key, bold: true }, { text: String(statusCounts[key]), alignment: "center" }]),
        ];

        if (statusStatsRows.length === 1) {
          statusStatsRows.push([{ text: "Sin tareas", colSpan: 2, alignment: "center", color: "#667085" }, ""]);
        }

        const taskDependency = (task) => {
          if (!task.dependency) return "-";
          return `${task.dependency.code} - ${task.dependency.name}`;
        };

        const taskFocus = (task) => {
          const values = [];
          if (task.dependency_component) values.push(`Elemento: ${task.dependency_component}`);
          if (task.technical_area) values.push(`Area: ${task.technical_area.code} - ${task.technical_area.name}`);
          if (task.inventory_item) values.push(`Bien: ${task.inventory_item.code} - ${task.inventory_item.name}`);
          return values.join(" | ") || "Dependencia general";
        };

        const summarize = (text, maxLength = 100) => {
          const value = String(text || "").trim().replace(/\s+/g, " ");
          if (!value) return "-";
          return value.length > maxLength ? `${value.slice(0, maxLength - 3)}...` : value;
        };

        const overdueTasks = tasks.filter((task) => task.due_date && String(task.due_date).slice(0, 10) < today).length;
        const criticalTasks = Number(priorityCounts["Crítico"] || 0);

        const summaryHeader = [
          ["OT", "Dependencia", "Foco", "Trabajo", "Prioridad", "Limite"].map((text) => ({
            text,
            bold: true,
            color: "#ffffff",
            fillColor: "#334155",
            fontSize: 7.6,
            alignment: text === "Trabajo" ? "left" : "center",
          })),
        ];

        const summaryRows = tasks.length
          ? tasks.map((task, index) => [
              { text: `#${task.id}`, alignment: "center", bold: true },
              taskDependency(task),
              summarize(taskFocus(task), 58),
              summarize(task.description, 78),
              { text: task.priority || "-", alignment: "center", color: priorityColor(task.priority), bold: true, fontSize: 7.4 },
              { text: task.due_date ? this.formatDate(task.due_date) : "-", alignment: "center" },
            ].map((cell) => ({
              fillColor: index % 2 === 0 ? "#ffffff" : "#f8fafc",
              fontSize: 7.4,
              ...(typeof cell === "string" ? { text: cell } : cell),
            })))
          : [[{ text: "No hay OT pendientes para los filtros seleccionados.", colSpan: 6, alignment: "center", color: "#667085" }, "", "", "", "", ""]];

        const summaryTable = {
          table: {
            headerRows: 1,
            dontBreakRows: true,
            widths: [30, 88, 86, 155, 45, 48],
            body: [...summaryHeader, ...summaryRows],
          },
          layout: this.pdfCompactTableLayout(),
          margin: [0, 0, 0, 12],
        };

        const fetchImageAsDataUrl = async (url) => {
          if (!url) return null;
          try {
            const absolute = url.startsWith("http") ? url : url.startsWith("/") ? url : `/${url}`;
            const res = await fetch(absolute);
            if (!res.ok) return null;
            const blob = await res.blob();
            return await new Promise((resolve) => {
              const reader = new FileReader();
              reader.onload = () => resolve(reader.result);
              reader.onerror = () => resolve(null);
              reader.readAsDataURL(blob);
            });
          } catch {
            return null;
          }
        };

        const detailSections = [];

        for (let i = 0; i < tasks.length; i++) {
          const task = tasks[i];
          const photoDataUrl = await fetchImageAsDataUrl(task.photo_url);
          const isOverdue = task.due_date && String(task.due_date).slice(0, 10) < today;

          detailSections.push(
            { text: `OT #${task.id}`, style: "taskTitle", pageBreak: "before" },
            {
              table: {
                widths: ["*", 90, 90],
                body: [
                  [
                    {
                      stack: [
                        { text: "Dependencia", style: "label" },
                        { text: taskDependency(task), style: "value" },
                      ],
                      fillColor: "#f8fafc",
                    },
                    {
                      stack: [
                        { text: "Prioridad", style: "label" },
                        { text: task.priority || "-", style: "value", color: priorityColor(task.priority), bold: true },
                      ],
                      fillColor: "#f8fafc",
                    },
                    {
                      stack: [
                        { text: "Limite", style: "label" },
                        { text: task.due_date ? this.formatDate(task.due_date) : "-", style: "value", color: isOverdue ? "#b91c1c" : "#243047", bold: isOverdue },
                      ],
                      fillColor: "#f8fafc",
                    },
                  ],
                ],
              },
              layout: this.pdfTableLayout(),
              margin: [0, 0, 0, 10],
            },
            {
              table: {
                widths: ["*", "*"],
                body: [
                  [
                    {
                      stack: [
                        { text: "Foco de trabajo", style: "label" },
                        { text: taskFocus(task), style: "value" },
                      ],
                    },
                    {
                      stack: [
                        { text: "Asignacion", style: "label" },
                        { text: `Solicita: ${task.requested_by || "-"}\nAsignado: ${task.assigned_to || "-"}`, style: "value" },
                      ],
                    },
                  ],
                ],
              },
              layout: this.pdfTableLayout(),
              margin: [0, 0, 0, 10],
            },
            { text: "Trabajo solicitado", style: "label" },
            { text: task.description || "-", margin: [0, 3, 0, 10] },
            { text: "Notas de cierre / resolucion", style: "label" },
            { text: task.resolution_notes || "-", margin: [0, 3, 0, 10], color: task.resolution_notes ? "#364154" : "#7a849a", italics: !task.resolution_notes },
            { text: "Foto", style: "label" },
            photoDataUrl
              ? { image: photoDataUrl, fit: [500, 250], margin: [0, 5, 0, 0] }
              : { text: "Sin foto disponible.", italics: true, color: "#667085", margin: [0, 5, 0, 0] }
          );
        }

        const docDefinition = {
          pageOrientation: "portrait",
          pageMargins: [34, 36, 34, 42],
          footer: this.pdfFooter(),
          content: [
            {
              columns: [
                {
                  stack: [
                    { text: "MANTENCION", style: "eyebrow" },
                    { text: "Pauta de trabajo", style: "header" },
                    { text: assigneeLabel, style: "assigneeTitle" },
                  ],
                },
                { text: `Generado: ${this.formatDateTime()}`, style: "muted", alignment: "right", margin: [0, 10, 0, 0] },
              ],
              margin: [0, 0, 0, 12],
            },
            { canvas: [{ type: "line", x1: 0, y1: 0, x2: 528, y2: 0, lineWidth: 1, lineColor: "#dbe5f4" }], margin: [0, 0, 0, 12] },
            { text: filtersText, style: "filterLine", margin: [0, 0, 0, 12] },
            this.pdfCardTable([
              { label: "OT activas", value: this.formatNumber(tasks.length), detail: "Asignadas a la pauta", fill: "#eef4ff", color: "#3152c9" },
              { label: "Vencidas", value: this.formatNumber(overdueTasks), detail: "Fuera de plazo", fill: "#fef2f2", color: "#b91c1c" },
              { label: "Criticas", value: this.formatNumber(criticalTasks), detail: "Prioridad critica", fill: "#fffbeb", color: "#b45309" },
              { label: "Con foto", value: this.formatNumber(tasks.filter((task) => task.photo_url).length), detail: "Respaldo visual", fill: "#ecfdf5", color: "#047857" },
            ]),
            {
              columns: [
                {
                  width: "*",
                  stack: [
                    { text: "Distribucion por prioridad", style: "sectionTitle" },
                    {
                      table: { headerRows: 1, widths: ["*", 70], body: priorityStatsRows },
                      layout: this.pdfTableLayout(),
                    },
                  ],
                },
                {
                  width: "*",
                  stack: [
                    { text: "Distribucion por estado", style: "sectionTitle" },
                    {
                      table: { headerRows: 1, widths: ["*", 70], body: statusStatsRows },
                      layout: this.pdfTableLayout(),
                    },
                  ],
                },
              ],
              columnGap: 16,
              margin: [0, 0, 0, 12],
            },
            { text: "Resumen de OT activas", style: "sectionTitle" },
            summaryTable,
            ...detailSections,
          ],
          styles: {
            eyebrow: { fontSize: 8, bold: true, color: "#5b74df", characterSpacing: 0.8 },
            header: { fontSize: 20, bold: true, color: "#243047", margin: [0, 2, 0, 4] },
            assigneeTitle: { fontSize: 13, color: "#53607a", bold: true },
            muted: { fontSize: 9, color: "#667085" },
            filterLine: { fontSize: 9, color: "#53607a" },
            sectionTitle: { fontSize: 11, bold: true, color: "#243047", margin: [0, 0, 0, 6] },
            taskTitle: { fontSize: 15, bold: true, color: "#243047", margin: [0, 0, 0, 10] },
            label: { fontSize: 9, bold: true, color: "#667085" },
            value: { fontSize: 10, color: "#243047" },
          },
          defaultStyle: { fontSize: 10, color: "#364154" },
        };

        pdfMake.createPdf(docDefinition).download(`pauta-${assignee}-${new Date().toISOString().slice(0, 10)}.pdf`);
      } catch (error) {
        this.error = error.response?.data?.message || error.message || "Error generando PDF";
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<template>
  <Layout>
    <div class="workload-page">
      <div class="workload-header">
        <div>
          <span class="workload-eyebrow">Mantención</span>
          <h4>Carga de trabajo</h4>
          <p>Resumen operativo de OT por responsable, estado de avance, vencimientos y criticidad.</p>
        </div>
        <div class="workload-header-actions">
          <button class="workload-secondary-button" type="button" :disabled="loading" @click="loadWorkload">
            <i class="mdi mdi-refresh"></i>
            Actualizar
          </button>
          <button class="workload-secondary-button workload-secondary-button--green" type="button" :disabled="!hasRows" @click="exportExcel">
            <i class="mdi mdi-file-excel-outline"></i>
            Excel
          </button>
          <button class="workload-secondary-button workload-secondary-button--red" type="button" :disabled="!hasRows" @click="exportPdf">
            <i class="mdi mdi-file-pdf-box"></i>
            PDF resumen
          </button>
          <button class="workload-primary-button" type="button" :disabled="loading || !filters.assignee" @click="exportAssigneePdf()">
            <i class="mdi mdi-account-arrow-down-outline"></i>
            PDF trabajador
          </button>
        </div>
      </div>

      <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

      <div class="workload-summary-grid">
        <div
          v-for="card in summaryCards"
          :key="card.label"
          class="workload-summary-card"
          :class="`workload-summary-card--${card.tone}`"
        >
          <div class="workload-summary-icon">
            <i :class="`mdi ${card.icon}`"></i>
          </div>
          <div class="workload-summary-content">
            <span>{{ card.label }}</span>
            <strong>{{ formatNumber(card.value) }}</strong>
            <small>{{ card.detail }}</small>
          </div>
        </div>
      </div>

      <section class="workload-panel">
        <div class="workload-panel-head">
          <div>
            <span class="workload-eyebrow">Filtros</span>
            <h5>Consulta de carga</h5>
          </div>
          <div class="workload-filter-count" :class="{ 'is-active': activeFiltersCount > 0 }">
            {{ activeFiltersCount }} filtros
          </div>
        </div>

        <div class="workload-filters">
          <label class="workload-filter-field">
            <span>Desde</span>
            <input v-model="filters.from" type="date" />
          </label>
          <label class="workload-filter-field">
            <span>Hasta</span>
            <input v-model="filters.to" type="date" />
          </label>
          <label class="workload-filter-field">
            <span>Responsable</span>
            <select v-model="filters.assignee">
              <option value="">Todos</option>
              <option v-for="assignee in assigneeOptions" :key="assignee.value" :value="assignee.value">{{ assignee.label }}</option>
              <option value="Sin asignar">Sin asignar</option>
            </select>
          </label>
          <label class="workload-filter-field workload-filter-field--wide">
            <span>Dependencia</span>
            <select v-model="filters.dependency_id">
              <option value="">Todas</option>
              <option v-for="dep in catalogs.dependencies" :key="dep.id" :value="dep.id">{{ dep.code }} - {{ dep.name }}</option>
            </select>
          </label>
          <label class="workload-filter-field">
            <span>Criticidad</span>
            <select v-model="filters.priority">
              <option value="">Todas</option>
              <option v-for="priority in catalogs.priorities" :key="priority" :value="priority">{{ priority }}</option>
            </select>
          </label>
          <label class="workload-filter-field">
            <span>Estado</span>
            <select v-model="filters.status">
              <option value="">Todos</option>
              <option v-for="status in catalogs.statuses" :key="status" :value="status">{{ status }}</option>
            </select>
          </label>
          <div class="workload-filter-actions">
            <button class="workload-primary-button" type="button" :disabled="loading" @click="loadWorkload">
              <i class="mdi mdi-filter-outline"></i>
              Aplicar
            </button>
            <button class="workload-secondary-button" type="button" @click="resetFilters">
              Limpiar
            </button>
          </div>
        </div>

        <div v-if="activeFilterLabels.length" class="workload-filter-chips">
          <span v-for="label in activeFilterLabels" :key="label">{{ label }}</span>
        </div>
      </section>

      <section class="workload-table-panel">
        <div class="workload-panel-head">
          <div>
            <span class="workload-eyebrow">Resumen</span>
            <h5>Responsables</h5>
          </div>
          <span class="workload-total-label">{{ rows.length }} responsables OT</span>
        </div>

        <div class="workload-table-wrap">
          <table class="workload-table">
            <thead>
              <tr>
                <th class="workload-col-person">Responsable</th>
                <th>Asignadas</th>
                <th>Pendientes</th>
                <th>Vencidas</th>
                <th>Críticas</th>
                <th>Cerradas</th>
                <th class="workload-col-progress">Avance</th>
                <th>Estado</th>
                <th class="workload-col-actions">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="9">
                  <div class="workload-empty-state">Cargando carga de trabajo...</div>
                </td>
              </tr>
              <tr v-else-if="rows.length === 0">
                <td colspan="9">
                  <div class="workload-empty-state">No hay datos para los filtros seleccionados.</div>
                </td>
              </tr>
              <tr v-for="row in rows" :key="row.assignee">
                <td>
                  <div class="workload-person">
                    <span>{{ row.assignee }}</span>
                    <small v-if="row.maintenance_role_label">{{ row.maintenance_role_label }}</small>
                    <small>{{ Number(row.assigned || 0) ? `${formatPercent(row.closed, row.assigned)} cerrado` : "Sin OT filtradas" }}</small>
                  </div>
                </td>
                <td class="workload-number">{{ formatNumber(row.assigned) }}</td>
                <td class="workload-number workload-number--green">{{ formatNumber(row.pending) }}</td>
                <td class="workload-number" :class="{ 'workload-number--red': Number(row.overdue) > 0 }">{{ formatNumber(row.overdue) }}</td>
                <td class="workload-number" :class="{ 'workload-number--amber': Number(row.critical) > 0 }">{{ formatNumber(row.critical) }}</td>
                <td class="workload-number">{{ formatNumber(row.closed) }}</td>
                <td>
                  <div class="workload-progress">
                    <div class="workload-progress-bar workload-progress-bar--closed" :style="barStyle(row.closed, row.assigned)"></div>
                    <div class="workload-progress-bar workload-progress-bar--pending" :style="barStyle(row.pending, row.assigned)"></div>
                  </div>
                </td>
                <td>
                  <span class="workload-pill" :class="rowAttentionClass(row)">{{ rowAttentionLevel(row) }}</span>
                </td>
                <td>
                  <div class="workload-row-actions">
                    <button class="workload-icon-button" type="button" title="PDF trabajador" @click="exportAssigneeFromRow(row)">
                      <i class="mdi mdi-file-pdf-box"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
            <tfoot v-if="rows.length">
              <tr>
                <td>Total</td>
                <td>{{ formatNumber(totals.assigned) }}</td>
                <td>{{ formatNumber(totals.pending) }}</td>
                <td>{{ formatNumber(totals.overdue) }}</td>
                <td>{{ formatNumber(totals.critical) }}</td>
                <td>{{ formatNumber(totals.closed) }}</td>
                <td colspan="3">{{ formatPercent(totals.closed, totals.assigned) }} cerrado</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>
    </div>
  </Layout>
</template>

<style scoped>
.workload-page {
  padding: 4px 0 24px;
}

.workload-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;
  padding: 18px 0 16px;
  border-bottom: 1px solid #e3ebfb;
  margin-bottom: 20px;
}

.workload-eyebrow {
  display: block;
  color: #6d7690;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0;
  line-height: 1.2;
  text-transform: uppercase;
}

.workload-header h4,
.workload-panel-head h5 {
  margin: 4px 0 0;
  color: #303848;
  font-weight: 700;
  letter-spacing: 0;
}

.workload-header p {
  margin: 8px 0 0;
  color: #717b94;
  font-size: 15px;
  font-weight: 400;
}

.workload-header-actions,
.workload-filter-actions,
.workload-row-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.workload-primary-button,
.workload-secondary-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-height: 40px;
  padding: 0 16px;
  border: 1px solid transparent;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  line-height: 1;
  transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.workload-primary-button {
  color: #fff;
  background: #5b74df;
  border-color: #5b74df;
}

.workload-primary-button:hover {
  color: #fff;
  background: #4f66ca;
  border-color: #4f66ca;
}

.workload-secondary-button {
  color: #566079;
  background: #fff;
  border-color: #b9c3d8;
}

.workload-secondary-button:hover {
  color: #384154;
  background: #f5f7fb;
  border-color: #8d99b2;
}

.workload-secondary-button--green {
  color: #047857;
  border-color: #a7f3d0;
  background: #ecfdf5;
}

.workload-secondary-button--red {
  color: #b91c1c;
  border-color: #fecaca;
  background: #fef2f2;
}

.workload-primary-button:disabled,
.workload-secondary-button:disabled {
  opacity: 0.58;
  cursor: not-allowed;
}

.workload-summary-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 20px;
}

.workload-summary-card {
  display: grid;
  grid-template-columns: 44px minmax(0, 1fr);
  align-items: center;
  gap: 13px;
  min-height: 112px;
  padding: 18px;
  border: 1px solid #dfebfb;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.8);
  box-shadow: 0 18px 42px rgba(63, 84, 120, 0.06);
}

.workload-summary-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 8px;
  font-size: 22px;
}

.workload-summary-content {
  min-width: 0;
}

.workload-summary-content span,
.workload-summary-content small {
  display: block;
}

.workload-summary-content span {
  color: #6d7690;
  font-size: 13px;
  font-weight: 600;
}

.workload-summary-content strong {
  display: block;
  margin-top: 4px;
  color: #303848;
  font-size: 27px;
  font-weight: 700;
  line-height: 1;
}

.workload-summary-content small {
  margin-top: 6px;
  color: #7b849c;
  font-size: 12px;
}

.workload-summary-card--blue .workload-summary-icon {
  color: #3152c9;
  background: #eef4ff;
}

.workload-summary-card--green .workload-summary-icon {
  color: #047857;
  background: #ecfdf5;
}

.workload-summary-card--red .workload-summary-icon {
  color: #b91c1c;
  background: #fef2f2;
}

.workload-summary-card--amber .workload-summary-icon {
  color: #b45309;
  background: #fffbeb;
}

.workload-summary-card--slate .workload-summary-icon {
  color: #475569;
  background: #f8fafc;
}

.workload-panel,
.workload-table-panel {
  padding: 22px;
  border: 1px solid #dfebfb;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.84);
  box-shadow: 0 18px 44px rgba(63, 84, 120, 0.06);
}

.workload-panel {
  margin-bottom: 20px;
}

.workload-panel-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.workload-filter-count,
.workload-total-label {
  display: inline-flex;
  align-items: center;
  min-height: 30px;
  padding: 0 12px;
  border: 1px solid #dce5f4;
  border-radius: 999px;
  color: #647089;
  background: #f4f7fb;
  font-size: 13px;
  font-weight: 600;
}

.workload-filter-count.is-active {
  color: #3152c9;
  background: #eef4ff;
  border-color: #c7d7fe;
}

.workload-filters {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
  align-items: end;
}

.workload-filter-field {
  display: flex;
  flex-direction: column;
  gap: 7px;
  min-width: 0;
  margin: 0;
}

.workload-filter-field span {
  color: #4c5568;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.2;
}

.workload-filter-field input,
.workload-filter-field select {
  width: 100%;
  min-height: 44px;
  padding: 0 14px;
  border: 1px solid #dce5f4;
  border-radius: 8px;
  color: #303848;
  background: #fff;
  font-size: 14px;
  font-weight: 400;
  outline: none;
}

.workload-filter-field input:focus,
.workload-filter-field select:focus {
  border-color: #9db1f8;
  box-shadow: 0 0 0 3px rgba(91, 116, 223, 0.12);
}

.workload-filter-field--wide {
  grid-column: span 2;
}

.workload-filter-actions {
  grid-column: 1 / -1;
  flex-wrap: wrap;
  justify-content: flex-end;
  min-width: 0;
  padding-top: 2px;
}

.workload-filter-actions .workload-primary-button,
.workload-filter-actions .workload-secondary-button {
  min-width: 130px;
}

.workload-filter-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 14px;
}

.workload-filter-chips span {
  display: inline-flex;
  align-items: center;
  min-height: 30px;
  padding: 0 12px;
  border: 1px solid #d7dee9;
  border-radius: 999px;
  color: #53607a;
  background: #f8fafc;
  font-size: 12px;
  font-weight: 500;
}

.workload-table-wrap {
  overflow-x: auto;
  border-top: 1px solid #e2eaf8;
}

.workload-table {
  width: 100%;
  min-width: 1120px;
  table-layout: fixed;
  border-collapse: separate;
  border-spacing: 0;
}

.workload-table th {
  padding: 16px 14px;
  color: #727b92;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0;
  line-height: 1.25;
  text-align: center;
  text-transform: uppercase;
  border-bottom: 1px solid #dce7f7;
}

.workload-table td {
  padding: 17px 14px;
  color: #364154;
  font-size: 14px;
  font-weight: 400;
  line-height: 1.35;
  text-align: center;
  vertical-align: middle;
  border-bottom: 1px solid #e5edf9;
}

.workload-table tfoot td {
  color: #303848;
  background: #eef4ff;
  font-weight: 600;
}

.workload-col-person {
  width: 230px;
  text-align: left !important;
}

.workload-col-progress {
  width: 180px;
}

.workload-col-actions {
  width: 96px;
}

.workload-person {
  display: flex;
  flex-direction: column;
  gap: 3px;
  text-align: left;
}

.workload-person span {
  color: #303848;
  font-weight: 600;
}

.workload-person small {
  color: #778199;
  font-size: 12px;
}

.workload-number {
  color: #303848;
  font-weight: 600 !important;
}

.workload-number--green {
  color: #047857;
}

.workload-number--red {
  color: #b91c1c;
}

.workload-number--amber {
  color: #b45309;
}

.workload-progress {
  position: relative;
  display: flex;
  width: 100%;
  height: 10px;
  overflow: hidden;
  border-radius: 999px;
  background: #edf2f8;
}

.workload-progress-bar {
  height: 100%;
}

.workload-progress-bar--closed {
  background: #34d399;
}

.workload-progress-bar--pending {
  background: #5b74df;
}

.workload-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 82px;
  min-height: 30px;
  padding: 0 12px;
  border: 1px solid transparent;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  line-height: 1;
  white-space: nowrap;
}

.workload-pill--danger {
  color: #b91c1c;
  background: #fef2f2;
  border-color: #fecaca;
}

.workload-pill--active {
  color: #3152c9;
  background: #eef4ff;
  border-color: #c7d7fe;
}

.workload-pill--done {
  color: #047857;
  background: #ecfdf5;
  border-color: #a7f3d0;
}

.workload-icon-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border: 1px solid #fecaca;
  border-radius: 8px;
  color: #b91c1c;
  background: #fef2f2;
  font-size: 17px;
}

.workload-icon-button:hover {
  color: #991b1b;
  background: #fee2e2;
}

.workload-empty-state {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 90px;
  color: #7a849a;
  font-weight: 500;
}

@media (max-width: 1400px) {
  .workload-summary-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .workload-filters {
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  }

  .workload-filter-actions {
    justify-content: flex-end;
  }
}

@media (max-width: 768px) {
  .workload-header,
  .workload-panel-head {
    flex-direction: column;
    align-items: stretch;
  }

  .workload-header-actions,
  .workload-filter-actions {
    flex-wrap: wrap;
  }

  .workload-summary-grid,
  .workload-filters {
    grid-template-columns: 1fr;
  }

  .workload-filter-field--wide,
  .workload-filter-actions {
    grid-column: auto;
  }

  .workload-filter-actions .workload-primary-button,
  .workload-filter-actions .workload-secondary-button {
    flex: 1 1 140px;
  }

  .workload-panel,
  .workload-table-panel {
    padding: 16px;
  }
}
</style>
