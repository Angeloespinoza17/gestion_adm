export function normalizeRoleAccessIds(values) {
  return Array.from(new Set(
    (values || [])
      .map((value) => Number(value))
      .filter((value) => Number.isInteger(value) && value > 0)
  ));
}

export function formatRoleApiError(error) {
  const validationMessages = Object.values(error?.response?.data?.errors || {})
    .flatMap((messages) => Array.isArray(messages) ? messages : [messages])
    .filter(Boolean);

  if (validationMessages.length) {
    return Array.from(new Set(validationMessages)).join(" ");
  }

  return error?.response?.data?.message || error?.message || "Error desconocido";
}
