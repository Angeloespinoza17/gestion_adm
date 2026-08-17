import { computed, reactive, ref } from "vue";
import { libroDigitalApi } from "../services/libro-digital/api";
import { cleanParams, payloadData, payloadItems } from "../components/libro-digital/module-utils";

const storageKey = "libro-digital:context:v1";
const contextFields = [
  "school_id",
  "academic_year_id",
  "education_level_id",
  "course_section_id",
  "book_id",
  "schedule_subject_id",
];

const numericOrNull = (value) => {
  if (value === null || value === undefined || value === "") return null;
  const number = Number(value);
  return Number.isFinite(number) ? number : null;
};

const readStoredContext = () => {
  try {
    return JSON.parse(sessionStorage.getItem(storageKey) || "{}");
  } catch {
    return {};
  }
};

export function useLibroDigitalContext(route, router) {
  const stored = readStoredContext();
  const initial = Object.fromEntries(contextFields.map((field) => [
    field,
    numericOrNull(route.query?.[field] ?? stored[field]),
  ]));

  const context = reactive(initial);
  const catalogs = ref({});
  const books = ref([]);
  const capabilities = ref({});
  const loading = ref(false);
  const loadingBooks = ref(false);
  const error = ref(null);
  let catalogsController = null;
  let booksController = null;

  const selectedBook = computed(() => books.value.find((item) => Number(item.id) === Number(context.book_id)) || null);
  const selectedYear = computed(() => (catalogs.value.academic_years || []).find((item) => Number(item.id) === Number(context.academic_year_id)) || null);
  const selectedCourse = computed(() => (catalogs.value.course_sections || catalogs.value.courses || []).find((item) => Number(item.id) === Number(context.course_section_id)) || null);
  const selectedSchool = computed(() => (catalogs.value.schools || catalogs.value.establishments || []).find((item) => Number(item.id) === Number(context.school_id)) || null);

  const persist = async () => {
    const values = cleanParams(Object.fromEntries(contextFields.map((field) => [field, context[field]])));
    sessionStorage.setItem(storageKey, JSON.stringify(values));

    const query = { ...route.query };
    contextFields.forEach((field) => delete query[field]);
    Object.assign(query, values);

    try {
      await router.replace({ query });
    } catch {
      // Navigation can be superseded when a section change happens at the same time.
    }
  };

  const applyDefaults = () => {
    const data = catalogs.value;
    if (!context.school_id) {
      context.school_id = numericOrNull(data.active_school_id || data.school_id || data.schools?.[0]?.id || data.establishments?.[0]?.id);
    }
    if (!context.academic_year_id) {
      context.academic_year_id = numericOrNull(
        data.active_academic_year_id
        || data.academic_years?.find((item) => item.is_active)?.id
        || data.academic_years?.[0]?.id,
      );
    }
  };

  const loadCatalogs = async () => {
    catalogsController?.abort();
    catalogsController = new AbortController();
    loading.value = true;
    error.value = null;
    try {
      const payload = await libroDigitalApi.catalogs(cleanParams({ school_id: context.school_id }), catalogsController.signal);
      const data = payloadData(payload);
      catalogs.value = data.catalogs || data;
      capabilities.value = data.capabilities || payload?.meta?.capabilities || data.meta?.capabilities || {};
      applyDefaults();
      await loadBooks();
      await persist();
    } catch (requestError) {
      if (requestError?.code !== "ERR_CANCELED") error.value = requestError;
    } finally {
      loading.value = false;
    }
  };

  const loadBooks = async () => {
    booksController?.abort();
    booksController = new AbortController();
    loadingBooks.value = true;
    try {
      const payload = await libroDigitalApi.books(cleanParams({
        school_id: context.school_id,
        academic_year_id: context.academic_year_id,
        education_level_id: context.education_level_id,
        course_section_id: context.course_section_id,
        schedule_subject_id: context.schedule_subject_id,
        per_page: 200,
      }), booksController.signal);
      books.value = payloadItems(payload);
      if (context.book_id && !books.value.some((item) => Number(item.id) === Number(context.book_id))) {
        context.book_id = null;
      }
    } finally {
      loadingBooks.value = false;
    }
  };

  const applyContext = async (next = {}) => {
    const previousScope = [context.school_id, context.academic_year_id, context.course_section_id, context.schedule_subject_id].join(":");
    contextFields.forEach((field) => {
      if (Object.prototype.hasOwnProperty.call(next, field)) context[field] = numericOrNull(next[field]);
    });
    const nextScope = [context.school_id, context.academic_year_id, context.course_section_id, context.schedule_subject_id].join(":");
    if (previousScope !== nextScope) await loadBooks();
    await persist();
  };

  const selectBook = async (book) => applyContext({
    book_id: book?.id || null,
    academic_year_id: book?.academic_year_id || context.academic_year_id,
    course_section_id: book?.course_section_id || context.course_section_id,
    schedule_subject_id: book?.schedule_subject_id || book?.subject_id || context.schedule_subject_id,
  });

  return {
    context,
    catalogs,
    books,
    capabilities,
    loading,
    loadingBooks,
    error,
    selectedBook,
    selectedYear,
    selectedCourse,
    selectedSchool,
    loadCatalogs,
    loadBooks,
    applyContext,
    selectBook,
  };
}
