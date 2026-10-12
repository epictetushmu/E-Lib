import { defineStore } from 'pinia'
import type { JwtClaims } from '@/types/user'

const TOKEN_KEY = 'e-lib.token'
const PROFILE_KEY = 'e-lib.profile'

export interface CachedProfile {
  id: string
  email: string
  username: string
  isAdmin: boolean
}

function decodeClaims(token: string): JwtClaims | null {
  try {
    const payload = token.split('.')[1]
    if (!payload) return null
    const json = atob(payload.replace(/-/g, '+').replace(/_/g, '/'))
    return JSON.parse(json) as JwtClaims
  } catch {
    return null
  }
}

function loadCachedProfile(): CachedProfile | null {
  try {
    const raw = localStorage.getItem(PROFILE_KEY)
    return raw ? (JSON.parse(raw) as CachedProfile) : null
  } catch {
    return null
  }
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem(TOKEN_KEY) as string | null,
    profile: loadCachedProfile(),
  }),
  getters: {
    claims(state): JwtClaims | null {
      return state.token ? decodeClaims(state.token) : null
    },
    isAuthenticated(state): boolean {
      if (!state.token) return false
      const claims = decodeClaims(state.token)
      return !!claims && claims.exp * 1000 > Date.now()
    },
    isAdmin(): boolean {
      return this.isAuthenticated && !!this.claims?.isAdmin
    },
    /** Display name: cached profile's username when available, else the JWT's email claim. */
    username(state): string | null {
      return state.profile?.username ?? this.claims?.email ?? null
    },
  },
  actions: {
    /** Set the session after login/signup, where the API response already includes the profile. */
    setSession(token: string, profile: CachedProfile) {
      this.token = token
      this.profile = profile
      localStorage.setItem(TOKEN_KEY, token)
      localStorage.setItem(PROFILE_KEY, JSON.stringify(profile))
    },
    setToken(token: string) {
      this.token = token
      this.profile = null
      localStorage.setItem(TOKEN_KEY, token)
      localStorage.removeItem(PROFILE_KEY)
    },
    setProfile(profile: CachedProfile) {
      this.profile = profile
      localStorage.setItem(PROFILE_KEY, JSON.stringify(profile))
    },
    logout() {
      this.token = null
      this.profile = null
      localStorage.removeItem(TOKEN_KEY)
      localStorage.removeItem(PROFILE_KEY)
    },
  },
})
