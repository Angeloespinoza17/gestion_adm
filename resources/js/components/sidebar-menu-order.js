const spanishCollator = new Intl.Collator("es", {
  sensitivity: "base",
  numeric: true,
});

const normalizeMenuLabel = (value) =>
  String(value || "")
    .trim()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase();

const placementPriority = (item, translatedLabel) => {
  const label = normalizeMenuLabel(translatedLabel).replace(/\s+/g, "");
  const slug = normalizeMenuLabel(item.slug).replace(/\s+/g, "_");
  const link = String(item.link || item.frontend_route || "");

  if (
    label === "inicio" ||
    slug === "dashboard" ||
    slug === "home" ||
    link === "/" ||
    link === "/inicio"
  ) return 0;
  if (label === "superadmin" || slug === "superadmin") return 2;
  if (
    label === "configuracion" ||
    slug === "settings" ||
    slug === "configuration" ||
    slug === "configuracion"
  ) return 3;

  return 1;
};

export function sortSidebarMenuItems(items = [], translate = (value) => value) {
  const sortedNavigableItems = items
    .filter((item) => !item.isTitle && !item.isLayout)
    .map((item, index) => {
      const translatedLabel = translate(item.label);

      return {
        item,
        index,
        label: String(translatedLabel || item.label || ""),
        priority: placementPriority(item, translatedLabel),
      };
    })
    .sort((left, right) => {
      if (left.priority !== right.priority) {
        return left.priority - right.priority || left.index - right.index;
      }

      if (left.priority !== 1) return left.index - right.index;

      return spanishCollator.compare(left.label, right.label) || left.index - right.index;
    })
    .map(({ item }) => item);

  let navigableIndex = 0;

  return items.map((item) => {
    if (item.isTitle || item.isLayout) return item;

    const sortedItem = sortedNavigableItems[navigableIndex];
    navigableIndex += 1;
    return sortedItem;
  });
}
