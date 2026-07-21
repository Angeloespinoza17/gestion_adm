import axios from "axios";

const sleep = (milliseconds) => new Promise((resolve) => window.setTimeout(resolve, milliseconds));

const fallbackFilename = (exportJob) => {
  const extension = exportJob.format === "xls" ? "xls" : exportJob.format;
  const timestamp = new Date().toISOString().replaceAll(/[-:]/g, "").slice(0, 15);
  return `asistencia_${exportJob.report_type}_${timestamp}.${extension}`;
};

const responseFilename = (response, exportJob) => {
  const disposition = response.headers?.["content-disposition"] || "";
  const encoded = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
  if (encoded) {
    try { return decodeURIComponent(encoded.replaceAll('"', "")); }
    catch { return fallbackFilename(exportJob); }
  }

  return disposition.match(/filename="?([^";]+)"?/i)?.[1] || fallbackFilename(exportJob);
};

export const waitForAttendanceExport = async (exportJob, options = {}) => {
  const timeoutMs = options.timeoutMs ?? 180000;
  const pollMs = options.pollMs ?? 1500;
  const startedAt = Date.now();
  let current = exportJob;

  while (["pending", "processing"].includes(current.status)) {
    if (Date.now() - startedAt >= timeoutMs) {
      const error = new Error("La generación continúa en segundo plano.");
      error.code = "ATTENDANCE_EXPORT_TIMEOUT";
      throw error;
    }

    await sleep(pollMs);
    const response = await axios.get(`/api/attendance-statistics/exports/${exportJob.id}`);
    current = response.data;
    options.onProgress?.(current);
  }

  if (current.status === "failed") throw new Error(current.failure_message || "No se pudo generar el archivo.");
  if (current.status !== "completed" || !current.download_url) throw new Error("La exportación no entregó un archivo descargable.");

  return current;
};

export const downloadAttendanceExport = async (exportJob) => {
  if (!exportJob.download_url) throw new Error("El archivo todavía no está disponible.");

  const response = await axios.get(exportJob.download_url, { responseType: "blob" });
  const objectUrl = window.URL.createObjectURL(response.data);
  const anchor = document.createElement("a");
  anchor.href = objectUrl;
  anchor.download = responseFilename(response, exportJob);
  anchor.style.display = "none";
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  window.setTimeout(() => window.URL.revokeObjectURL(objectUrl), 1000);
};

export const waitForAndDownloadAttendanceExport = async (exportJob, options = {}) => {
  const completed = await waitForAttendanceExport(exportJob, options);
  await downloadAttendanceExport(completed);
  return completed;
};
