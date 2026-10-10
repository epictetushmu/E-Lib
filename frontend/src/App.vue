<script setup lang="ts">
import { useRoute } from 'vue-router'
import NavBar from '@/components/NavBar.vue'
import Footer from '@/components/Footer.vue'
import ToastContainer from '@/components/ToastContainer.vue'

const route = useRoute()
</script>

<template>
  <div class="app-shell" :class="{ 'app-shell--immersive': route.meta.immersive }">
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <NavBar />
    <main id="main-content" class="app-main">
      <RouterView />
    </main>
    <Footer v-if="!route.meta.immersive" />
    <ToastContainer />
  </div>
</template>

<style scoped src="@/css/App.css"></style>
