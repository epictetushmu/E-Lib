<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import LoginForm from '@/components/LoginForm.vue'
import { useToast } from '@/composables/useToast'

const route = useRoute()
const router = useRouter()
const toast = useToast()

onMounted(() => {
  if (route.query.reason === 'session_expired') {
    toast.info('Your session expired. Please log in again.')
  }
})

const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : undefined
</script>

<template>
  <div class="container auth-page">
    <div class="card auth-card">
      <LoginForm :redirect="redirect" @switch-to-signup="router.push('/signup')" />
    </div>
  </div>
</template>

<style scoped src="@/css/views/Login.css"></style>
