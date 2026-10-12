import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import '@/css/main.css'

// A tab opened before a rebuild/deploy still asks for the old hashed chunks, which no longer exist.
// Reload once to pick up the new build; the timestamp guard stops a reload loop if a chunk is genuinely broken.
window.addEventListener('vite:preloadError', (event) => {
  try {
    const last = Number(sessionStorage.getItem('e-lib.chunk-reload') ?? 0)
    if (Date.now() - last < 10_000) return
    sessionStorage.setItem('e-lib.chunk-reload', String(Date.now()))
  } catch {
    // Storage unavailable: still reload rather than leave the page broken.
  }
  event.preventDefault()
  window.location.reload()
})

const app = createApp(App)

app.use(createPinia())
app.use(router)

app.mount('#app')
