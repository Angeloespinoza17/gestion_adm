import { computed, onBeforeUnmount, reactive, ref } from "vue";
import axios from "axios";

const clean = (values) => Object.fromEntries(Object.entries(values).filter(([, value]) => value !== "" && value !== null && value !== undefined));

export function useAttendanceManagement() {
  const filters = reactive({ academic_year_id: null, course_section_id: null });
  const dashboard = ref(null);
  const loading = ref(false);
  const refreshing = ref(false);
  const error = ref("");
  let controller;

  const params = computed(() => clean(filters));
  const loadDashboard = async () => {
    controller?.abort();
    controller = new AbortController();
    dashboard.value ? (refreshing.value = true) : (loading.value = true);
    error.value = "";
    try {
      const { data } = await axios.get("/api/attendance-management/dashboard", { params: params.value, signal: controller.signal });
      dashboard.value = data;
      if (!filters.academic_year_id) filters.academic_year_id = data?.meta?.academic_year?.id || null;
    } catch (requestError) {
      if (requestError.code !== "ERR_CANCELED") error.value = requestError.response?.data?.message || "No fue posible cargar la gestión de asistencia.";
    } finally {
      loading.value = false;
      refreshing.value = false;
    }
  };

  onBeforeUnmount(() => controller?.abort());
  return { filters, params, dashboard, loading, refreshing, error, loadDashboard };
}
