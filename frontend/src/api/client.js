import axios from 'axios'
import { tokenStorage } from './tokenStorage'

const baseURL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1'

export const api = axios.create({ baseURL })

api.interceptors.request.use((config) => {
  const token = tokenStorage.getAccessToken()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// When an access token expires mid-session, the FIRST 401 triggers a
// refresh; any other requests that fail with 401 while that refresh is
// in flight queue up and retry once it resolves, rather than each firing
// its own independent (and racing) refresh call.
let isRefreshing = false
let pendingQueue = []

function resolveQueue(error, token) {
  pendingQueue.forEach(({ resolve, reject }) => (error ? reject(error) : resolve(token)))
  pendingQueue = []
}

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const { config, response } = error
    const isAuthEndpoint = config?.url?.includes('/auth/login') || config?.url?.includes('/auth/refresh')

    if (response?.status !== 401 || isAuthEndpoint || config._retried) {
      return Promise.reject(error)
    }

    if (isRefreshing) {
      return new Promise((resolve, reject) => {
        pendingQueue.push({ resolve, reject })
      }).then((token) => {
        config._retried = true
        config.headers.Authorization = `Bearer ${token}`
        return api(config)
      })
    }

    config._retried = true
    isRefreshing = true

    try {
      const refreshToken = tokenStorage.getRefreshToken()
      if (!refreshToken) throw error

      const { data } = await axios.post(`${baseURL}/auth/refresh`, { refresh_token: refreshToken })
      tokenStorage.setTokens(data.access_token, data.refresh_token)
      resolveQueue(null, data.access_token)

      config.headers.Authorization = `Bearer ${data.access_token}`
      return api(config)
    } catch (refreshError) {
      resolveQueue(refreshError, null)
      tokenStorage.clear()
      window.location.href = '/login'
      return Promise.reject(refreshError)
    } finally {
      isRefreshing = false
    }
  }
)
