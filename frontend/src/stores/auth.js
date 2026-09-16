import { defineStore } from 'pinia'
import { api } from '../api/client'
import { tokenStorage } from '../api/tokenStorage'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    loading: false,
  }),
  getters: {
    isAuthenticated: (state) => !!state.user,
    role: (state) => state.user?.role,
    isCustomer: (state) => state.user?.role === 'customer',
    isStaff: (state) => ['loan_officer', 'manager', 'admin'].includes(state.user?.role),
    canApprove: (state) => ['manager', 'admin'].includes(state.user?.role),
    isAdmin: (state) => state.user?.role === 'admin',
  },
  actions: {
    async login(email, password) {
      const { data } = await api.post('/auth/login', { email, password })
      tokenStorage.setTokens(data.access_token, data.refresh_token)
      this.user = data.user
    },
    async register(payload) {
      const { data } = await api.post('/auth/register', payload)
      tokenStorage.setTokens(data.access_token, data.refresh_token)
      this.user = data.user
    },
    async logout() {
      try {
        await api.post('/auth/logout')
      } catch {
        // Best-effort — clear local state regardless of whether the
        // server call succeeds (e.g. token already expired).
      }
      tokenStorage.clear()
      this.user = null
    },
    async fetchCurrentUser() {
      if (!tokenStorage.getAccessToken()) return
      this.loading = true
      try {
        const { data } = await api.get('/auth/me')
        this.user = data
      } catch {
        tokenStorage.clear()
        this.user = null
      } finally {
        this.loading = false
      }
    },
  },
})
