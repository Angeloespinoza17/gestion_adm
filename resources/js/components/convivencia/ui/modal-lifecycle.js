const modalOwners = new Set();

export function acquireConvivenciaModalLock(owner) {
  if (!owner || modalOwners.has(owner) || typeof document === "undefined") return;
  modalOwners.add(owner);
  document.body.classList.add("convivencia-modal-open");
}

export function releaseConvivenciaModalLock(owner) {
  if (!owner || typeof document === "undefined") return;
  modalOwners.delete(owner);
  if (modalOwners.size === 0) document.body.classList.remove("convivencia-modal-open");
}

export function focusableElements(container) {
  if (!container) return [];
  return Array.from(container.querySelectorAll("a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex='-1'])"))
    .filter((element) => element.offsetParent !== null || element === document.activeElement);
}

export function trapConvivenciaModalFocus(event, container) {
  if (event.key !== "Tab") return;
  const focusable = focusableElements(container);
  if (!focusable.length) {
    event.preventDefault();
    container?.focus();
    return;
  }
  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
}
