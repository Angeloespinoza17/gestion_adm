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
      catalogsLoading: false,
      catalogs: {
        priorities: ["Crítico", "Alta", "Media", "Baja"],
        statuses: ["Sin comenzar", "En proceso", "En espera", "Pausado", "Terminado", "Anulado"],
        dependencies: [],
        assignees: [],
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
  mounted() {
    this.loadCatalogs();
    this.loadWorkload();
  },
  methods: {
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
        row.assignee,
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
      const header = [
        ["Responsable", "OT asignadas", "Pendientes", "Vencidas", "Críticas", "Cerradas"].map((text) => ({
          text,
          bold: true,
        })),
      ];

      const body = this.rows.map((row) => [
        row.assignee,
        row.assigned,
        row.pending,
        row.overdue,
        row.critical,
        row.closed,
      ]);

      body.push(["TOTAL", this.totals.assigned, this.totals.pending, this.totals.overdue, this.totals.critical, this.totals.closed].map((value) => ({ text: String(value), bold: true })));

      const filtersText = [
        this.filters.from ? `Desde: ${this.filters.from}` : null,
        this.filters.to ? `Hasta: ${this.filters.to}` : null,
        this.filters.assignee ? `Responsable: ${this.filters.assignee}` : null,
        this.filters.dependency_id ? `Dependencia ID: ${this.filters.dependency_id}` : null,
        this.filters.priority ? `Criticidad: ${this.filters.priority}` : null,
        this.filters.status ? `Estado: ${this.filters.status}` : null,
      ]
        .filter(Boolean)
        .join(" · ");

      const docDefinition = {
        pageOrientation: "landscape",
        content: [
          { text: "Carga de trabajo", style: "header" },
          filtersText ? { text: filtersText, margin: [0, 0, 0, 10] } : null,
          {
            table: {
              headerRows: 1,
              widths: ["*", 70, 70, 70, 70, 70],
              body: [...header, ...body],
            },
            layout: "lightHorizontalLines",
          },
        ].filter(Boolean),
        styles: {
          header: { fontSize: 16, bold: true, margin: [0, 0, 0, 10] },
        },
      };

      pdfMake.createPdf(docDefinition).download(`carga-trabajo-${new Date().toISOString().slice(0, 10)}.pdf`);
    },
    async exportAssigneePdf() {
      if (!this.filters.assignee) {
        alert("Selecciona un responsable para exportar su pauta.");
        return;
      }

      this.loading = true;
      this.error = null;

      try {
        const response = await axios.get("/api/maintenance/work-orders/assignee-report", {
          params: {
            ...this.filters,
          },
        });

        const tasks = response.data?.data || [];

        const formatDMY = (value) => {
          if (!value) return "";
          const [y, m, d] = String(value).slice(0, 10).split("-");
          if (!y || !m || !d) return String(value);
          return `${d}-${m}-${y}`;
        };

        const actaDate = this.filters.to || new Date().toISOString().slice(0, 10);

        const extraFilters = [
          this.filters.from ? `Desde: ${formatDMY(this.filters.from)}` : null,
          this.filters.dependency_id ? `Dependencia ID: ${this.filters.dependency_id}` : null,
          this.filters.priority ? `Criticidad: ${this.filters.priority}` : null,
          this.filters.status ? `Estado: ${this.filters.status}` : null,
        ]
          .filter(Boolean)
          .join(" · ");

        const filtersText = `Día del acta: ${formatDMY(actaDate)}${extraFilters ? ` · ${extraFilters}` : ""}`;

        const summaryHeader = [
          ["OT", "Dependencia", "Trabajo", "Prioridad", "Límite"].map((text) => ({ text, bold: true })),
        ];

        const priorityOrder = ["Crítico", "Alta", "Media", "Baja"];
        const priorityCounts = tasks.reduce((acc, task) => {
          const key = task.priority || "Sin prioridad";
          acc[key] = (acc[key] || 0) + 1;
          return acc;
        }, {});

        const priorityStatsRows = [
          ["Prioridad", "Cantidad"].map((text) => ({ text, bold: true })),
          ...priorityOrder
            .filter((key) => priorityCounts[key])
            .map((key) => [key, String(priorityCounts[key])]),
        ];

        const restKeys = Object.keys(priorityCounts).filter((key) => !priorityOrder.includes(key));
        restKeys.sort().forEach((key) => {
          priorityStatsRows.push([key, String(priorityCounts[key])]);
        });

        const statsBlock = {
          columns: [
            {
              width: "*",
              stack: [
                { text: "Estadística", style: "subheader" },
                { text: `Total tareas: ${tasks.length}`, margin: [0, 0, 0, 8] },
                {
                  table: {
                    headerRows: 1,
                    widths: ["*", 70],
                    body: priorityStatsRows,
                  },
                  layout: "lightHorizontalLines",
                },
              ],
            },
          ],
          columnGap: 20,
          margin: [0, 0, 0, 10],
        };

        const summarize = (text) => {
          const value = String(text || "").trim().replace(/\s+/g, " ");
          if (!value) return "-";
          return value.length > 80 ? `${value.slice(0, 77)}...` : value;
        };

        const summaryRows = tasks.map((task) => [
          `#${task.id}`,
          task.dependency ? `${task.dependency.code} · ${task.dependency.name}` : "-",
          summarize(task.description),
          task.priority || "-",
          task.due_date ? formatDMY(task.due_date) : "-",
        ]);

        const summaryTable = {
          table: {
            headerRows: 1,
            widths: [60, "*", "*", 70, 70],
            body: [...summaryHeader, ...summaryRows],
          },
          layout: "lightHorizontalLines",
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

          detailSections.push(
            { text: `OT #${task.id}`, style: "taskTitle", pageBreak: i === 0 ? undefined : "before" },
            {
              columns: [
                {
                  width: "*",
                  stack: [
                    { text: "Dependencia", style: "label" },
                    { text: task.dependency ? `${task.dependency.code} · ${task.dependency.name}` : "-", style: "value" },
                  ],
                },
                {
                  width: "auto",
                  stack: [
                    { text: "Fecha límite", style: "label" },
                    { text: task.due_date ? formatDMY(task.due_date) : "-", style: "value" },
                  ],
                },
              ],
              columnGap: 20,
              margin: [0, 0, 0, 10],
            },
            {
              columns: [
                {
                  width: "*",
                  stack: [
                    { text: "Quién asigna", style: "label" },
                    { text: task.requested_by || "-", style: "value" },
                  ],
                },
                {
                  width: "*",
                  stack: [
                    { text: "Asignados", style: "label" },
                    { text: task.assigned_to || "-", style: "value" },
                  ],
                },
              ],
              columnGap: 20,
              margin: [0, 0, 0, 10],
            },
            {
              columns: [
                {
                  width: "*",
                  stack: [
                    { text: "Prioridad", style: "label" },
                    { text: task.priority || "-", style: "value" },
                  ],
                },
                {
                  width: "*",
                  stack: [
                    { text: "Estado", style: "label" },
                    { text: task.status || "-", style: "value" },
                  ],
                },
              ],
              columnGap: 20,
              margin: [0, 0, 0, 10],
            },
            { text: "Trabajo solicitado", style: "label" },
            { text: task.description || "-", margin: [0, 2, 0, 10] },
            { text: "Notas de cierre / resolución", style: "label" },
            { text: task.resolution_notes || "-", margin: [0, 2, 0, 10] },
            { text: "Foto", style: "label" },
            photoDataUrl
              ? { image: photoDataUrl, width: 500, margin: [0, 5, 0, 0] }
              : { text: "Sin foto disponible.", italics: true, color: "#666", margin: [0, 5, 0, 0] }
          );
        }

        const docDefinition = {
          pageOrientation: "portrait",
          content: [
            { text: `Pauta de trabajo: ${this.filters.assignee}`, style: "header" },
            filtersText ? { text: filtersText, margin: [0, 0, 0, 10] } : null,
            statsBlock,
            { text: "Resumen", style: "subheader" },
            summaryTable,
            tasks.length ? { text: "Detalle", style: "subheader", pageBreak: "before" } : null,
            ...detailSections,
          ].filter(Boolean),
          styles: {
            header: { fontSize: 16, bold: true, margin: [0, 0, 0, 6] },
            subheader: { fontSize: 13, bold: true, margin: [0, 12, 0, 6] },
            taskTitle: { fontSize: 14, bold: true, margin: [0, 0, 0, 8] },
            label: { fontSize: 10, bold: true, color: "#444" },
            value: { fontSize: 11 },
          },
          defaultStyle: { fontSize: 11 },
        };

        pdfMake
          .createPdf(docDefinition)
          .download(`pauta-${this.filters.assignee}-${new Date().toISOString().slice(0, 10)}.pdf`);
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
    <BRow>
      <BCol cols="12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
          <h4 class="mb-0 font-size-18">Carga de trabajo</h4>
          <div class="page-title-right">
            <ol class="breadcrumb mb-0">
              <li class="breadcrumb-item active">Gestión de mantención</li>
            </ol>
          </div>
        </div>
      </BCol>
    </BRow>

    <BCard no-body class="mb-3">
      <BCardBody>
        <div class="d-flex flex-wrap align-items-end gap-2">
          <div>
            <label class="form-label">Desde</label>
            <input v-model="filters.from" type="date" class="form-control" />
          </div>
          <div>
            <label class="form-label">Hasta</label>
            <input v-model="filters.to" type="date" class="form-control" />
          </div>
          <div>
            <label class="form-label">Responsable</label>
            <select v-model="filters.assignee" class="form-select">
              <option value="">Todos</option>
              <option v-for="assignee in catalogs.assignees" :key="assignee" :value="assignee">{{ assignee }}</option>
              <option value="Sin asignar">Sin asignar</option>
            </select>
          </div>
          <div>
            <label class="form-label">Dependencia</label>
            <select v-model="filters.dependency_id" class="form-select">
              <option value="">Todas</option>
              <option v-for="dep in catalogs.dependencies" :key="dep.id" :value="dep.id">{{ dep.code }} · {{ dep.name }}</option>
            </select>
          </div>
          <div>
            <label class="form-label">Criticidad</label>
            <select v-model="filters.priority" class="form-select">
              <option value="">Todas</option>
              <option v-for="priority in catalogs.priorities" :key="priority" :value="priority">{{ priority }}</option>
            </select>
          </div>
          <div>
            <label class="form-label">Estado</label>
            <select v-model="filters.status" class="form-select">
              <option value="">Todos</option>
              <option v-for="status in catalogs.statuses" :key="status" :value="status">{{ status }}</option>
            </select>
          </div>
          <button class="btn btn-primary" type="button" :disabled="loading" @click="loadWorkload">Aplicar</button>
          <button class="btn btn-outline-success" type="button" :disabled="rows.length === 0" @click="exportExcel">Exportar Excel</button>
          <button class="btn btn-outline-danger" type="button" :disabled="rows.length === 0" @click="exportPdf">Exportar PDF</button>
          <button class="btn btn-outline-primary" type="button" :disabled="loading || !filters.assignee" @click="exportAssigneePdf">
            PDF trabajador
          </button>
        </div>
      </BCardBody>
    </BCard>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

    <BCard no-body>
      <BCardBody>
        <div class="table-responsive">
          <table class="table table-centered table-nowrap align-middle">
            <thead class="table-light">
              <tr>
                <th>Responsable</th>
                <th>OT asignadas</th>
                <th>OT pendientes</th>
                <th>OT vencidas</th>
                <th>OT críticas</th>
                <th>OT cerradas</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="6" class="text-center text-muted py-4">Cargando...</td>
              </tr>
              <tr v-else-if="rows.length === 0">
                <td colspan="6" class="text-center text-muted py-4">No hay datos para los filtros seleccionados.</td>
              </tr>
              <tr v-for="row in rows" :key="row.assignee">
                <td>{{ row.assignee }}</td>
                <td>{{ row.assigned }}</td>
                <td>{{ row.pending }}</td>
                <td>{{ row.overdue }}</td>
                <td>{{ row.critical }}</td>
                <td>{{ row.closed }}</td>
              </tr>
            </tbody>
            <tfoot v-if="rows.length">
              <tr class="table-light fw-bold">
                <td>TOTAL</td>
                <td>{{ totals.assigned }}</td>
                <td>{{ totals.pending }}</td>
                <td>{{ totals.overdue }}</td>
                <td>{{ totals.critical }}</td>
                <td>{{ totals.closed }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </BCardBody>
    </BCard>
  </Layout>
</template>
