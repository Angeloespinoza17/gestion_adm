import { computed, reactive, ref } from "vue";
import axios from "axios";

export function usePsychology() {
    const loading = ref(false);
    const error = ref("");
    const catalogs = reactive({
        catalogs: {},
        professionals: [],
        settings: {},
        capabilities: {},
    });

    const run = async (request) => {
        loading.value = true;
        error.value = "";
        try {
            return await request();
        } catch (exception) {
            error.value =
                exception?.response?.data?.message ||
                Object.values(
                    exception?.response?.data?.errors || {}
                )?.flat()?.[0] ||
                "No fue posible completar la operación.";
            throw exception;
        } finally {
            loading.value = false;
        }
    };

    const loadCatalogs = () =>
        run(async () =>
            Object.assign(
                catalogs,
                (await axios.get("/api/psychology/catalogs")).data
            )
        );
    const get = (url, params = {}) =>
        run(async () => (await axios.get(url, { params })).data);
    const post = (url, payload = {}, config = {}) =>
        run(async () => (await axios.post(url, payload, config)).data);
    const put = (url, payload = {}) =>
        run(async () => (await axios.put(url, payload)).data);
    const patch = (url, payload = {}) =>
        run(async () => (await axios.patch(url, payload)).data);

    return {
        loading: computed(() => loading.value),
        error,
        catalogs,
        loadCatalogs,
        get,
        post,
        put,
        patch,
    };
}

export const psychologyStatusLabels = {
    draft: "Borrador",
    submitted: "Enviada",
    under_review: "En revisión",
    information_requested: "Requiere antecedentes",
    accepted: "Aceptada",
    linked_to_existing_case: "Asociada a caso",
    redirected: "Redirigida",
    rejected: "Rechazada",
    duplicated: "Duplicada",
    cancelled: "Cancelada",
    completed: "Completada",
    open: "Abierto",
    assessment: "Evaluación",
    active_intervention: "Intervención activa",
    monitoring: "Seguimiento",
    awaiting_information: "Esperando antecedentes",
    awaiting_external_response: "Esperando red externa",
    paused: "Pausado",
    externally_referred: "Derivación externa",
    closure_pending: "Cierre pendiente",
    closed: "Cerrado",
    reopened: "Reabierto",
    pending: "Pendiente",
    in_progress: "En curso",
    overdue: "Vencida",
    finalized: "Finalizada",
    active: "Activo",
    granted: "Otorgado",
    not_required: "No requerido",
    exception: "Excepción institucional",
    not_requested: "No solicitado",
    acknowledged: "Confirmado",
    queued: "En cola",
    processing: "Procesando",
    failed: "Fallido",
};

export const priorityLabels = {
    low: "Baja",
    medium: "Media",
    high: "Alta",
    critical: "Crítica",
};
