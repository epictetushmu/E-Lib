import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import router from '@/router'

const client = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? '/api',
})

client.interceptors.request.use((config) => {
  const auth = useAuthStore()
  if (auth.token) {
    config.headers.Authorization = `Bearer ${auth.token}`
  }
  return config
})

client.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      const auth = useAuthStore()
      const wasLoggedIn = auth.isAuthenticated
      auth.logout()
      if (wasLoggedIn) {
        router.push({
          path: '/login',
          query: { redirect: router.currentRoute.value.fullPath, reason: 'session_expired' },
        })
      }
    }
    return Promise.reject(error)
  },
)

export default client
