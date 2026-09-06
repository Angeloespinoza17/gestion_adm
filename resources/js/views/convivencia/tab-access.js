export const canViewConvivenciaTab = (tabKey, capabilities = {}) => {
  const canViewCases = capabilities.can_view_cases === true;

  switch (tabKey) {
    case "dashboard":
      return capabilities.can_view_dashboard === true
        || capabilities.can_view_course_reports === true;
    case "planes":
      return capabilities.can_manage_plans === true || canViewCases;
    case "casos":
      return canViewCases
        || capabilities.can_create_cases === true
        || capabilities.can_manage_complaints === true
        || capabilities.can_manage_internal_derivations === true
        || capabilities.can_manage_external_derivations === true;
    case "denuncias":
      return capabilities.can_manage_complaints === true || canViewCases;
    case "derivaciones":
      return capabilities.can_manage_internal_derivations === true
        || capabilities.can_manage_external_derivations === true
        || canViewCases;
    case "protocolos":
      return capabilities.can_manage_protocols === true
        || capabilities.can_activate_protocols === true
        || canViewCases;
    case "entrevistas":
      return capabilities.can_manage_interviews === true || canViewCases;
    case "medidas":
      return capabilities.can_manage_measures === true || canViewCases;
    case "bitacora":
      return capabilities.can_manage_daily_logs === true || canViewCases;
    case "sociogramas":
      return capabilities.can_view_sociograms === true;
    case "idps":
      return capabilities.can_view_course_reports === true
        || capabilities.can_manage_plans === true
        || capabilities.can_manage_settings === true
        || capabilities.can_view_dashboard === true;
    case "reportes":
      return capabilities.can_view_course_reports === true;
    default:
      return false;
  }
};

export const visibleConvivenciaTabs = (tabs = [], capabilities = {}) => (
  tabs.filter((tab) => canViewConvivenciaTab(tab.key, capabilities))
);

export const firstConvivenciaFallbackRoute = (currentPath, visibleTabs = []) => {
  if (visibleTabs.some((tab) => tab.route === currentPath)) return null;
  return visibleTabs[0]?.route || null;
};
