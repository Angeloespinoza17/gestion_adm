import { computed, onBeforeUnmount, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import axios from "axios";

const storageKey = "attendance-statistics:filters";

const defaultFilters = () => ({
  academic_year_id: null,
  period: "academic_year",
  from: "",
  to: "",
  education_level_id: null,
  course_section_id: null,
  school_day_template_id: null,
  enrollment_status: "",
  attendance_status: "",
  absence_reason_id: null,
  is_justified: "",
  gender: "",
  commune: "",
  is_pie_participant: "",
  attendance_min: "",
  attendance_max: "",
  risk: "",
});

const clean = (value) => Object.fromEntries(
  Object.entries(value).filter(([, item]) => item !== "" && item !== null && item !== undefined)
);

export function useAttendanceStatistics() {
  const route = useRoute();
  const router = useRouter();
  let stored = {};
  try {
    stored = JSON.parse(sessionStorage.getItem(storageKey) || "{}");
  } catch {
    stored = {};
  }
  const routeFilters = { ...route.query };
  delete routeFilters.section;
  delete routeFilters.export;
  const filters = reactive({ ...defaultFilters(), ...stored, ...routeFilters });
  const dashboard = ref(null);
  const loading = ref(false);
  const refreshing = ref(false);
  const error = ref(null);
  let controller = null;

  const activeFilters = computed(() => clean(filters));
  const activeFilterCount = computed(() => Object.keys(clean({
    education_level_id: filters.education_level_id,
    course_section_id: filters.course_section_id,
    school_day_template_id: filters.school_day_template_id,
    enrollment_status: filters.enrollment_status,
    attendance_status: filters.attendance_status,
    absence_reason_id: filters.absence_reason_id,
    is_justified: filters.is_justified,
    gender: filters.gender,
    commune: filters.commune,
    is_pie_participant: filters.is_pie_participant,
    attendance_min: filters.attendance_min,
    attendance_max: filters.attendance_max,
    risk: filters.risk,
  })).length);

  const fetchDashboard = async () => {
    controller?.abort();
    controller = new AbortController();
    dashboard.value ? (refreshing.value = true) : (loading.value = true);
    error.value = null;
    try {
      const response = await axios.get("/api/attendance-statistics/dashboard", {
        params: activeFilters.value,
        signal: controller.signal,
      });
      dashboard.value = response.data;
      if (!filters.academic_year_id && response.data?.meta?.academic_year?.id) {
        filters.academic_year_id = response.data.meta.academic_year.id;
      }
      sessionStorage.setItem(storageKey, JSON.stringify(clean(filters)));
      await router.replace({ query: { ...clean(filters), ...(route.query.section ? { section: route.query.section } : {}) } });
    } catch (requestError) {
      if (requestError.code !== "ERR_CANCELED") {
        error.value = requestError.response?.data?.message || "No fue posible cargar las estadísticas de asistencia.";
      }
    } finally {
      loading.value = false;
      refreshing.value = false;
    }
  };

  const resetFilters = () => {
    Object.assign(filters, defaultFilters(), {
      academic_year_id: dashboard.value?.meta?.academic_year?.id || null,
    });
    fetchDashboard();
  };

  const drillToCourse = (courseId) => {
    filters.course_section_id = courseId;
    fetchDashboard();
  };

  onBeforeUnmount(() => controller?.abort());

  return {
    filters, dashboard, loading, refreshing, error, activeFilters, activeFilterCount,
    fetchDashboard, resetFilters, drillToCourse,
  };
}
