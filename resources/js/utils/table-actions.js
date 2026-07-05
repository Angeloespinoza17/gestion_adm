const ACTIONS = [
  {
    patterns: ["ver", "ver ficha", "ver curso", "ver detalle", "ver reportes", "detalle"],
    label: "Ver",
    tone: "view",
    icon: "mdi-eye-outline",
  },
  {
    patterns: ["ficha"],
    label: "Ficha",
    tone: "view",
    icon: "mdi-card-account-details-outline",
  },
  {
    patterns: ["checklist"],
    label: "Checklist",
    tone: "activate",
    icon: "mdi-clipboard-check-outline",
  },
  {
    patterns: ["editar", "editar relaciones", "ver / editar"],
    label: "Editar",
    tone: "edit",
    icon: "mdi-pencil-outline",
  },
  {
    patterns: ["eliminar", "borrar"],
    label: "Eliminar",
    tone: "delete",
    icon: "mdi-trash-can-outline",
  },
  {
    patterns: ["activar"],
    label: "Activar",
    tone: "activate",
    icon: "mdi-check-circle-outline",
  },
  {
    patterns: ["desactivar", "anular"],
    label: "Desactivar",
    tone: "delete",
    icon: "mdi-close-circle-outline",
  },
  {
    patterns: ["reingresar"],
    label: "Reingresar",
    tone: "success",
    icon: "mdi-account-arrow-left-outline",
  },
  {
    patterns: ["cambiar curso"],
    label: "Cambiar curso",
    tone: "activate",
    icon: "mdi-swap-horizontal",
  },
  {
    patterns: ["retirar"],
    label: "Retirar",
    tone: "warning",
    icon: "mdi-account-remove-outline",
  },
  {
    patterns: ["pdf", "pdf resumen", "pdf trabajador"],
    label: "PDF",
    tone: "delete",
    icon: "mdi-file-pdf-box",
  },
  {
    patterns: ["excel"],
    label: "Excel",
    tone: "success",
    icon: "mdi-file-excel-outline",
  },
  {
    patterns: ["descargar", "exportar", "exportar csv"],
    label: "Descargar",
    tone: "view",
    icon: "mdi-download-outline",
  },
  {
    patterns: ["imprimir"],
    label: "Imprimir",
    tone: "view",
    icon: "mdi-printer-outline",
  },
  {
    patterns: ["aprobar"],
    label: "Aprobar",
    tone: "success",
    icon: "mdi-check-outline",
  },
  {
    patterns: ["rechazar"],
    label: "Rechazar",
    tone: "delete",
    icon: "mdi-close-outline",
  },
  {
    patterns: ["revisar", "resolver"],
    label: "Revisar",
    tone: "activate",
    icon: "mdi-clipboard-check-outline",
  },
  {
    patterns: ["cerrar"],
    label: "Cerrar",
    tone: "success",
    icon: "mdi-lock-check-outline",
  },
  {
    patterns: ["reabrir"],
    label: "Reabrir",
    tone: "activate",
    icon: "mdi-lock-open-outline",
  },
];

const EXCLUDED_SELECTOR = [
  ".dropdown-item",
  ".page-link",
  ".nav-link",
  ".multiselect-option",
  "[data-cnsc-action-ignore]",
].join(",");

const normalizeLabel = (value) =>
  String(value || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/\s+/g, " ")
    .trim()
    .toLowerCase();

const actionFromElement = (element) => {
  const rawLabel = element.dataset.cnscActionLabel || element.getAttribute("title") || element.textContent;
  const label = normalizeLabel(rawLabel);

  if (!label) return null;

  return ACTIONS.find((action) => action.patterns.includes(label)) || null;
};

const isActionCell = (element) => {
  const cell = element.closest("td, th");
  const table = element.closest("table");

  if (!cell || !table) return false;
  if (String(cell.className || "").toLowerCase().includes("action")) return true;

  const row = cell.parentElement;
  if (!row) return false;

  const cells = Array.from(row.children).filter((child) => ["TD", "TH"].includes(child.tagName));
  return cells[cells.length - 1] === cell;
};

const normalizeButton = (element) => {
  if (!(element instanceof HTMLElement)) return;
  if (element.matches(EXCLUDED_SELECTOR)) return;
  if (!isActionCell(element)) return;

  const action = actionFromElement(element);
  if (!action) return;

  element.dataset.cnscActionLabel = action.label;
  element.dataset.cnscActionNormalized = action.tone;
  element.classList.add("cnsc-action-btn", `cnsc-action-btn--${action.tone}`);
  element.setAttribute("title", action.label);
  element.setAttribute("aria-label", action.label);

  const iconClass = `mdi ${action.icon}`;
  if (element.innerHTML.trim() !== `<i class="${iconClass}"></i>`) {
    element.innerHTML = `<i class="${iconClass}"></i>`;
  }
};

const normalizeTableActions = (root = document) => {
  const candidates = root.querySelectorAll("table td button, table td a, table th button, table th a");
  candidates.forEach(normalizeButton);
};

export function installTableActionNormalizer(router) {
  const schedule = (() => {
    let pending = false;

    return () => {
      if (pending) return;
      pending = true;

      window.requestAnimationFrame(() => {
        pending = false;
        normalizeTableActions();
      });
    };
  })();

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", schedule, { once: true });
  } else {
    schedule();
  }

  router?.afterEach?.(() => {
    schedule();
    window.setTimeout(schedule, 250);
    window.setTimeout(schedule, 800);
  });

  const observer = new MutationObserver((mutations) => {
    if (mutations.some((mutation) => mutation.addedNodes.length > 0)) {
      schedule();
    }
  });

  observer.observe(document.body, {
    childList: true,
    subtree: true,
  });
}
