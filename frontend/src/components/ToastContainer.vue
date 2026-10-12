<script setup lang="ts">
import { useToastStore } from '@/stores/toast'

const store = useToastStore()
</script>

<template>
  <div class="toast-stack" role="status" aria-live="polite">
    <div v-for="toast in store.toasts" :key="toast.id" class="toast" :class="toast.variant" @click="store.dismiss(toast.id)">
      {{ toast.message }}
    </div>
  </div>
</template>

<style scoped src="@/css/components/ToastContainer.css"></style>
