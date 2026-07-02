export function formatRemunerationError(error, fallback = "No fue posible completar la operación.") {
  if (error?.response?.data?.message) return error.response.data.message;

  const errors = error?.response?.data?.errors;
  if (errors && typeof errors === "object") {
    const first = Object.values(errors).flat().find(Boolean);
    if (first) return first;
  }

  return fallback;
}

export function money(value) {
  return new Intl.NumberFormat("es-CL", {
    style: "currency",
    currency: "CLP",
    maximumFractionDigits: 0,
  }).format(Number(value || 0));
}

export function shortDate(value) {
  if (!value) return "-";
  return new Intl.DateTimeFormat("es-CL").format(new Date(value));
}
