<script setup lang="ts">
import { useRouter } from 'vue-router'
import SignupForm from '@/components/SignupForm.vue'

const router = useRouter()
</script>

<template>
  <div class="container auth-page">
    <div class="card auth-card">
      <SignupForm @switch-to-login="router.push('/login')" />
    </div>
  </div>
</template>

<style scoped src="@/css/views/Signup.css"></style>
