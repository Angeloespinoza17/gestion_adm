import axios from "axios";

export const COURSE_REPORT_EXPORT_DATASETS = [
  "cases",
  "complaints",
  "daily_logs",
  "derivations",
  "interviews",
  "measures",
];

const EXPORT_ENDPOINT = "/api/convivencia/reports/course/export-data";

function cleanFilters(filters = {}) {
  return Object.fromEntries(
    Object.entries(filters).filter(([, value]) => value !== null && value !== undefined && value !== ""),
  );
}

async function fetchDataset({ dataset, filters, perPage, signal, http, onProgress }) {
  const rows = [];
  let page = 1;
  let lastPage = 1;
  let total = 0;

  do {
    const response = await http.get(EXPORT_ENDPOINT, {
      params: { ...filters, dataset, page, per_page: perPage },
      signal,
    });
    const payload = response?.data || {};
    const pageRows = Array.isArray(payload.data) ? payload.data : [];
    const currentPage = Number(payload.current_page || page);
    lastPage = Math.max(1, Number(payload.last_page || 1));
    total = Math.max(0, Number(payload.total || 0));

    if (payload.dataset !== dataset || currentPage !== page) {
      throw new Error("La paginación del reporte cambió durante la exportación.");
    }
    if (pageRows.length === 0 && page <= lastPage && rows.length < total) {
      throw new Error("El servidor entregó una página incompleta del reporte.");
    }

    rows.push(...pageRows);
    onProgress?.({ dataset, page, lastPage, loaded: rows.length, total });
    page += 1;
  } while (page <= lastPage);

  if (rows.length !== total) {
    throw new Error(`No fue posible reunir todos los registros de ${dataset}.`);
  }

  return [dataset, rows];
}

/**
 * Reúne el conjunto exportable sin cargarlo al abrir el reporte. Cada conjunto
 * conserva su paginación secuencial y se procesan como máximo dos en paralelo.
 */
export async function fetchCompleteCourseReportLists({
  filters = {},
  signal,
  concurrency = 2,
  perPage = 200,
  http = axios,
  onProgress,
} = {}) {
  const sanitizedFilters = cleanFilters(filters);
  const results = {};
  let nextDataset = 0;
  const workerCount = Math.max(1, Math.min(Number(concurrency) || 1, COURSE_REPORT_EXPORT_DATASETS.length));

  const worker = async () => {
    while (nextDataset < COURSE_REPORT_EXPORT_DATASETS.length) {
      const dataset = COURSE_REPORT_EXPORT_DATASETS[nextDataset];
      nextDataset += 1;
      const [key, rows] = await fetchDataset({
        dataset,
        filters: sanitizedFilters,
        perPage: Math.max(1, Math.min(Number(perPage) || 200, 200)),
        signal,
        http,
        onProgress,
      });
      results[key] = rows;
    }
  };

  await Promise.all(Array.from({ length: workerCount }, () => worker()));

  return Object.fromEntries(COURSE_REPORT_EXPORT_DATASETS.map((dataset) => [dataset, results[dataset] || []]));
}

export { cleanFilters as cleanCourseReportFilters };
