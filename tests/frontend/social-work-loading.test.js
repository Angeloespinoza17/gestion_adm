import { reactive } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import axios from 'axios'
import { useSocialWork } from '../../resources/js/composables/useSocialWork'

vi.mock('axios', () => ({ default: vi.fn() }))

describe('Carga de Trabajo Social', () => {
  beforeEach(() => {
    axios.mockReset()
    globalThis.localStorage = { getItem: vi.fn(() => '[]') }
  })

  it('mantiene el cargador hasta que todas las peticiones concurrentes terminan', async () => {
    const resolvers = []
    axios.mockImplementation(() => new Promise((resolve) => resolvers.push(resolve)))
    const api = useSocialWork()

    const dashboard = api.get('/dashboard')
    const catalogs = api.get('/catalogs')

    expect(api.loading.value).toBe(true)
    resolvers[0]({ data: { data: {} } })
    await dashboard
    expect(api.loading.value).toBe(true)

    resolvers[1]({ data: { data: {} } })
    await catalogs
    expect(api.loading.value).toBe(false)
  })

  it('desempaqueta loading y error al consumir el composable desde la plantilla', () => {
    const api = reactive(useSocialWork())

    expect(api.loading).toBe(false)
    expect(api.error).toBe('')
  })

  it('sale del cargador y muestra un mensaje útil ante una respuesta sin detalle', async () => {
    axios.mockRejectedValue({ response: { status: 500, data: { message: ' ' } } })
    const api = useSocialWork()

    await expect(api.get('/dashboard')).rejects.toBeTruthy()

    expect(api.loading.value).toBe(false)
    expect(api.error.value).toBe('El servidor no pudo cargar la información de Trabajo Social.')
    expect(axios).toHaveBeenCalledWith(expect.objectContaining({ timeout: 15000 }))
  })

  it('traduce el límite temporal de consultas sin exponer el mensaje genérico del servidor', async () => {
    axios.mockRejectedValue({ response: { status: 429, data: { message: 'Too Many Attempts.' } } })
    const api = useSocialWork()

    await expect(api.get('/support-matrix')).rejects.toBeTruthy()

    expect(api.error.value).toBe('Se alcanzó temporalmente el límite de consultas. Espera unos segundos y vuelve a intentar.')
  })
})
