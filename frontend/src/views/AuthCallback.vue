<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import * as usersApi from '@/api/users'
import { isApiSuccess } from '@/types/api'

const router = useRouter()
const auth = useAuthStore()
const toast = useToast()

onMounted(async () => {
  const hash = window.location.hash.replace(/^#/, '')
  const params = new URLSearchParams(hash)
  const token = params.get('token')
  const state = params.get('state')

  // Single-use: whether this check passes or fails, the nonce this tab set before
  // redirecting to CAS (see LoginForm.vue) must not be reusable for a second attempt.
  const expectedState = sessionStorage.getItem('cas_login_state')
  sessionStorage.removeItem('cas_login_state')

  if (!token) {
    toast.error('CAS login failed: no token received')
    router.replace('/login')
    return
  }

  // Refuse a token that didn't arrive via a CAS round trip this tab itself started —
  // otherwise anyone who obtains a valid JWT (their own, via ordinary login) could craft
  // /auth/callback#token=<that jwt> and get a victim's browser to silently adopt it as its
  // session just by opening the link.
  if (!expectedState || state !== expectedState) {
    toast.error('CAS login failed: could not verify this login attempt')
    router.replace('/login')
    return
  }

  // The CAS callback only carries a token; fetch the profile to populate the username.
  auth.setToken(token)
  try {
    const response = await usersApi.getProfile()
    const body = response.data
    if (isApiSuccess(body)) {
      auth.setProfile({
        id: body.data._id,
        email: body.data.email,
        username: body.data.username,
        isAdmin: body.data.isAdmin,
      })
    }
  } catch {
    // Non-fatal: the navbar falls back to the JWT's email claim.
  }

  toast.success('Logged in via CAS')
  router.replace('/')
})
</script>

<template>
  <div class="container callback-page">
    <div class="spinner" role="status" aria-label="Signing you in"></div>
    <p class="text-muted">Signing you in…</p>
  </div>
</template>

<style scoped src="@/css/views/AuthCallback.css"></style>
