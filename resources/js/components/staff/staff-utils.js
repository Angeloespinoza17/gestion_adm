export function normalizeStaffNullableFields(payload, fields = []) {
  const normalized = { ...payload };

  fields.forEach((field) => {
    const value = normalized[field];
    if (value === undefined || value === null || (typeof value === "string" && value.trim() === "")) {
      normalized[field] = null;
    }
  });

  return normalized;
}
