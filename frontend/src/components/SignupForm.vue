<script setup lang="ts">
import { ref } from 'vue'
import { useToast } from '@/composables/useToast'
import * as usersApi from '@/api/users'
import { isApiSuccess, apiErrorMessage, requestErrorMessage, type ApiError } from '@/types/api'

const emit = defineEmits<{ success: []; 'switch-to-login': [] }>()

const toast = useToast()

const username = ref('')
const email = ref('')
const password = ref('')
const confirmPassword = ref('')
const terms = ref(false)
const error = ref('')
const success = ref('')
const submitting = ref(false)

function validate(): string | null {
  if (!username.value || !email.value || !password.value || !confirmPassword.value) {
    return 'All fields are required!'
  }
  if (password.value !== confirmPassword.value) {
    return 'Passwords do not match!'
  }
  if (password.value.length < 8) {
    return 'Password must be at least 8 characters long!'
  }
  if (!/\d/.test(password.value)) {
    return 'Password must contain at least one number!'
  }
  if (!/[!@#$%^&*(),.?":{}|<>]/.test(password.value)) {
    return 'Password must contain at least one special character!'
  }
  if (!terms.value) {
    return 'You must agree to the Terms of Service!'
  }
  return null
}

async function handleSubmit() {
  error.value = ''
  success.value = ''
  const validationError = validate()
  if (validationError) {
    error.value = validationError
    return
  }

  submitting.value = true
  try {
    const response = await usersApi.signup(username.value.trim(), email.value.trim(), password.value)
    const body = response.data
    if (isApiSuccess(body)) {
      success.value = 'Account created successfully! You can now log in.'
      toast.success('Account created — please log in')
      emit('success')
      setTimeout(() => emit('switch-to-login'), 1200)
    } else {
      error.value = apiErrorMessage(body as ApiError, 'Signup failed. Please check your information.')
    }
  } catch (err) {
    error.value = requestErrorMessage(err, 'An error occurred while trying to create your account. Please try again later.')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <form class="signup-form" data-testid="signup-form" @submit.prevent="handleSubmit">
    <h2>Create an Account</h2>
    <p v-if="error" class="alert alert-danger" role="alert" data-testid="signup-error">{{ error }}</p>
    <p v-if="success" class="alert alert-success" role="status" data-testid="signup-success">{{ success }}</p>

    <div class="form-field">
      <label for="signup-username">Username</label>
      <input
        id="signup-username"
        v-model="username"
        type="text"
        required
        autocomplete="name"
        data-testid="signup-username"
      />
    </div>
    <div class="form-field">
      <label for="signup-email">Email</label>
      <input id="signup-email" v-model="email" type="email" required autocomplete="email" data-testid="signup-email" />
    </div>
    <div class="form-field">
      <label for="signup-password">Password</label>
      <input
        id="signup-password"
        v-model="password"
        type="password"
        minlength="8"
        required
        autocomplete="new-password"
        data-testid="signup-password"
      />
      <small class="text-muted">Min 8 chars with a number and a special character</small>
    </div>
    <div class="form-field">
      <label for="signup-confirm">Confirm Password</label>
      <input
        id="signup-confirm"
        v-model="confirmPassword"
        type="password"
        required
        autocomplete="new-password"
        data-testid="signup-confirm"
      />
    </div>
    <label class="terms-check">
      <input v-model="terms" type="checkbox" required data-testid="signup-terms" />
      <span>I agree to the <a href="/terms" target="_blank">Terms of Service</a></span>
    </label>

    <button type="submit" class="btn btn-primary full-width" :disabled="submitting" data-testid="signup-submit">
      {{ submitting ? 'Creating account…' : 'Sign Up' }}
    </button>

    <p class="switch-link">
      Already have an account?
      <a href="#" @click.prevent="emit('switch-to-login')">Login</a>
    </p>
  </form>
</template>

<style scoped src="@/css/components/SignupForm.css"></style>
