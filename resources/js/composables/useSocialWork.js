import { computed, ref } from 'vue'
import axios from 'axios'

export function useSocialWork() {
    const pendingRequests = ref(0)
    const loading = computed(() => pendingRequests.value > 0)
    const error = ref('')
    const permissions = computed(() => {
        try { return JSON.parse(localStorage.getItem('permissions') || '[]') } catch { return [] }
    })
    const can = (permission) => permissions.value.includes('__superadmin__') || permissions.value.includes(permission)

    const errorMessage = (exception) => {
        const validationMessage = Object.values(exception.response?.data?.errors || {})
            .flat()
            .find((message) => typeof message === 'string' && message.trim())
        const responseMessage = exception.response?.data?.message

        if (exception.code === 'ECONNABORTED') {
            return 'La solicitud tardó demasiado. Comprueba la conexión y vuelve a intentar.'
        }

        if (exception.response?.status === 429) {
            return 'Se alcanzó temporalmente el límite de consultas. Espera unos segundos y vuelve a intentar.'
        }

        if (validationMessage) return validationMessage.trim()
        if (typeof responseMessage === 'string' && responseMessage.trim()) return responseMessage.trim()
        if (exception.response?.status === 403) return 'No tienes autorización para consultar esta información.'
        if (exception.response?.status >= 500) return 'El servidor no pudo cargar la información de Trabajo Social.'

        return 'No fue posible completar la operación. Vuelve a intentar.'
    }

    const call = async (method, url, data = undefined, config = {}) => {
        if (pendingRequests.value === 0) error.value = ''
        pendingRequests.value += 1

        try {
            return (await axios({ timeout: 15000, ...config, method, url: `/api/social-work${url}`, data })).data
        } catch (exception) {
            error.value = errorMessage(exception)
            throw exception
        } finally {
            pendingRequests.value = Math.max(0, pendingRequests.value - 1)
        }
    }

    return {
        loading,
        error,
        can,
        get: (url) => call('get', url),
        post: (url, data, config) => call('post', url, data, config),
        patch: (url, data) => call('patch', url, data),
        put: (url, data) => call('put', url, data),
        remove: (url) => call('delete', url),
    }
}
