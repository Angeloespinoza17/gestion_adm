import axios from "axios";
import Swal from "sweetalert2";

export function formatRiskError(error, fallback = "No se pudo completar la operación.") {
  const errors = error?.response?.data?.errors || null;
  return (
    (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
    error?.response?.data?.message ||
    error?.message ||
    fallback
  );
}

export function showRiskSuccess(text, title = "Operación realizada") {
  return Swal.fire({
    title,
    text,
    icon: "success",
    timer: 1800,
    showConfirmButton: false,
  });
}

export function showRiskError(text, title = "Error") {
  return Swal.fire({
    title,
    text,
    icon: "error",
    confirmButtonText: "OK",
  });
}

export function showRiskWarning(text, title = "Advertencia") {
  return Swal.fire({
    title,
    text,
    icon: "warning",
    confirmButtonText: "Entendido",
  });
}

export function confirmRiskAction({ title, text, confirmButtonText = "Confirmar", icon = "warning" }) {
  return Swal.fire({
    title,
    text,
    icon,
    showCancelButton: true,
    confirmButtonText,
    cancelButtonText: "Cancelar",
    reverseButtons: true,
  });
}

export async function downloadRiskFile(url, fileName = null) {
  const response = await axios.get(url, { responseType: "blob" });
  const blobUrl = window.URL.createObjectURL(new Blob([response.data]));
  const link = document.createElement("a");
  link.href = blobUrl;
  link.setAttribute("download", fileName || "archivo");
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  window.URL.revokeObjectURL(blobUrl);
}

export function formatRiskDate(value) {
  if (!value) return "-";
  return new Date(value).toLocaleDateString("es-CL", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  });
}

export function formatRiskDateTime(value) {
  if (!value) return "-";
  return new Date(value).toLocaleString("es-CL", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  });
}

export function statusVariant(status) {
  const map = {
    vigente: "success",
    cumplido: "success",
    activo: "success",
    disponible: "success",
    por_vencer: "warning",
    por_reponer: "warning",
    pendiente: "warning",
    en_seguimiento: "warning",
    abierto: "danger",
    vencido: "danger",
    agotado: "danger",
    critico: "danger",
    dado_baja: "secondary",
    archivado: "secondary",
    repuesto: "info",
    no_asiste: "secondary",
    cerrado: "primary",
  };

  return map[status] || "light";
}

export function humanizeRiskStatus(status) {
  if (!status) return "-";
  return String(status)
    .replaceAll("_", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
