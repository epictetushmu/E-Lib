import type { NavigationGuardWithThis } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

export const requireAuth: NavigationGuardWithThis<undefined> = (to) => {
  const auth = useAuthStore()
  if (!auth.isAuthenticated) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }
  return true
}

export const requireAdmin: NavigationGuardWithThis<undefined> = (to) => {
  const auth = useAuthStore()
  if (!auth.isAuthenticated) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }
  if (!auth.isAdmin) {
    return { path: '/' }
  }
  return true
}

export const guestOnly: NavigationGuardWithThis<undefined> = () => {
  const auth = useAuthStore()
  if (auth.isAuthenticated) {
    return { path: '/' }
  }
  return true
}
