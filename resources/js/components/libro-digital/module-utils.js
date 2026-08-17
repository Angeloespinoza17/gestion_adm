import Swal from "sweetalert2";

export const payloadData = (payload, fallback = {}) => {
  if (payload?.data !== undefined && !Array.isArray(payload.data)) return payload.data ?? fallback;
  return payload ?? fallback;
};

export const payloadItems = (payload) => {
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload?.data)) return payload.data;
  if (Array.isArray(payload?.data?.data)) return payload.data.data;
  if (Array.isArray(payload?.items)) return payload.items;
  return [];
};

export const payloadMeta = (payload) => payload?.meta || payload?.data?.meta || {};

export const cleanParams = (params = {}) => Object.fromEntries(
  Object.entries(params).filter(([, value]) => value !== null && value !== undefined && value !== ""),
);

export const contextParams = (context = {}) => cleanParams({
  school_id: context.school_id,
  academic_year_id: context.academic_year_id,
  education_level_id: context.education_level_id,
  course_section_id: context.course_section_id,
  book_id: context.book_id,
  schedule_subject_id: context.schedule_subject_id,
});

export const formatDate = (value, options = {}) => {
  if (!value) return "—";
  const normalized = /^\d{4}-\d{2}-\d{2}$/.test(String(value)) ? `${value}T12:00:00` : value;
  const date = new Date(normalized);
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleDateString("es-CL", { day: "2-digit", month: "2-digit", year: "numeric", ...options });
};

export const formatDateTime = (value) => {
  if (!value) return "—";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleString("es-CL", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

export const humanize = (value) => {
  if (!value) return "—";
  return String(value)
    .replaceAll("_", " ")
    .replaceAll("-", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
};

export const statusVariant = (status) => ({
  draft: "secondary",
  borrador: "secondary",
  open: "primary",
  opened: "primary",
  abierto: "primary",
  active: "success",
  activo: "success",
  ready_to_sign: "info",
  pendiente_firma: "info",
  signed: "success",
  firmado: "success",
  closed: "dark",
  cerrado: "dark",
  completed: "success",
  complete: "success",
  cancelled: "secondary",
  canceled: "secondary",
  anulado: "secondary",
  pending: "warning",
  queued: "warning",
  uploaded: "secondary",
  validated: "info",
  pending_approval: "warning",
  approved: "primary",
  activated: "success",
  processing: "info",
  validating: "info",
  failed: "danger",
  invalid: "danger",
  rejected: "danger",
  stale: "warning",
  reopened: "warning",
  conflict: "danger",
}[String(status || "").toLowerCase()] || "light");

export const errorMessage = (error, fallback = "No fue posible completar la operación.") => {
  if (!error) return fallback;
  if (Array.isArray(error.details) && error.details.length) {
    return error.details.map((detail) => detail.reason || detail.message || detail).join(" ");
  }
  if (error.details && typeof error.details === "object") {
    const values = Object.values(error.details).flat().filter(Boolean);
    if (values.length) return values.join(" ");
  }
  return error.message || error?.response?.data?.message || fallback;
};

export const showError = (error, title = "No se pudo completar") => Swal.fire({
  icon: error?.isConflict ? "warning" : "error",
  title: error?.isConflict ? "El registro cambió" : title,
  text: error?.isConflict
    ? "Otra persona actualizó este registro. Recarga la información antes de volver a guardar."
    : errorMessage(error),
  footer: error?.correlationId ? `ID de seguimiento: ${error.correlationId}` : undefined,
  confirmButtonText: "Entendido",
});

export const showSuccess = (title, text = "") => Swal.fire({
  icon: "success",
  title,
  text,
  timer: 1600,
  showConfirmButton: false,
});

export const confirmAction = ({ title, text, confirmText = "Confirmar", icon = "warning" }) => Swal.fire({
  title,
  text,
  icon,
  showCancelButton: true,
  confirmButtonText: confirmText,
  cancelButtonText: "Cancelar",
  reverseButtons: true,
});

export const hasCapability = (capabilities, capability) => {
  if (!capability) return true;
  const entries = Object.keys(capabilities || {});
  if (!entries.length) return true;
  return Boolean(
    capabilities?.[capability]
    || capabilities?.[capability.replace(/^can_/, "")]
    || capabilities?.["*"]
    || capabilities?.is_super_admin,
  );
};

export const bookLabel = (book) =>
  book?.display_name
  || book?.name
  || [book?.course?.display_name, book?.subject?.name].filter(Boolean).join(" · ")
  || (book?.id ? `Libro #${book.id}` : "Libro");

export const studentLabel = (student) =>
  student?.display_name
  || student?.registered_name_resolved
  || student?.registered_name
  || student?.full_name
  || student?.name
  || (student?.id ? `Estudiante #${student.id}` : "Estudiante");
