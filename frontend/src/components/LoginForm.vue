<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import * as usersApi from '@/api/users'
import { isApiSuccess, apiErrorMessage, requestErrorMessage, type ApiError } from '@/types/api'
import { idToString } from '@/types/book'

const props = defineProps<{ redirect?: string }>()
const emit = defineEmits<{ success: []; 'switch-to-signup': [] }>()

const router = useRouter()
const auth = useAuthStore()
const toast = useToast()

const email = ref('')
const password = ref('')
const error = ref('')
const submitting = ref(false)

// Binds this CAS round trip to the tab that started it: a nonce stored here must come back
// from /cas-login (embedded in the service URL CAS is required to echo back unmodified) and
// match in AuthCallback.vue before a token is accepted — see PageRouter's /cas-login handler.
function startCasLogin() {
  const state = crypto.randomUUID()
  sessionStorage.setItem('cas_login_state', state)
  const serviceUrl = `https://epictetus.hmu.gr/cas-login?state=${encodeURIComponent(state)}`
  window.location.href = `https://auth.hmu.gr/cas/login?service=${encodeURIComponent(serviceUrl)}`
}

async function handleSubmit() {
  error.value = ''
  submitting.value = true
  try {
    const response = await usersApi.login(email.value.trim(), password.value)
    const body = response.data
    if (isApiSuccess(body)) {
      const { token, user } = body.data
      auth.setSession(token, {
        id: idToString(user.id),
        email: user.email,
        username: user.username,
        isAdmin: user.isAdmin,
      })
      toast.success(`Welcome back, ${user.username}`)
      emit('success')
      router.push(auth.isAdmin ? '/' : props.redirect || '/')
    } else {
      error.value = apiErrorMessage(body as ApiError, 'Invalid credentials')
    }
  } catch (err) {
    error.value = requestErrorMessage(err, 'An error occurred while trying to log in. Please try again later.')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <form class="login-form" data-testid="login-form" @submit.prevent="handleSubmit">
    <h2>Login</h2>
    <p v-if="error" class="alert alert-danger" role="alert" data-testid="login-error">{{ error }}</p>

    <div class="form-field">
      <label for="login-email">Email</label>
      <input id="login-email" v-model="email" type="email" required autocomplete="email" data-testid="login-email" />
    </div>
    <div class="form-field">
      <label for="login-password">Password</label>
      <input
        id="login-password"
        v-model="password"
        type="password"
        required
        autocomplete="current-password"
        data-testid="login-password"
      />
    </div>

    <button type="submit" class="btn btn-primary full-width" :disabled="submitting" data-testid="login-submit">
      {{ submitting ? 'Logging in…' : 'Login' }}
    </button>

    <p class="switch-link">
      Don't have an account?
      <a href="#" @click.prevent="emit('switch-to-signup')">Sign up</a>
    </p>

    <div class="cas-block">
      <p class="text-muted">Or login with CAS authentication:</p>
      <a href="#" class="btn btn-outline full-width" @click.prevent="startCasLogin">Login with CAS</a>
    </div>
  </form>
</template>

<style scoped src="@/css/components/LoginForm.css"></style>
