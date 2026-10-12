<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import * as adminApi from '@/api/admin'
import { isApiSuccess, requestErrorMessage } from '@/types/api'
import { useToast } from '@/composables/useToast'
import { downloadBlob } from '@/utils/dom'
import { shortDateTime } from '@/utils/date'

const toast = useToast()

type Tab = 'errors' | 'requests' | 'system'
const activeTab = ref<Tab>('errors')

const errorLines = ref<string[]>([])
const requestLines = ref<string[]>([])
const loading = ref(true)
const loadError = ref('')
const autoRefresh = ref(false)
let refreshTimer: ReturnType<typeof setInterval> | null = null

const systemInfo = computed(() => ({
  'Page loaded': shortDateTime(new Date().toISOString()),
  Browser: navigator.userAgent,
  'Screen resolution': `${window.screen.width}x${window.screen.height}`,
  'Cookies enabled': navigator.cookieEnabled ? 'Yes' : 'No',
}))

function lineVariant(line: string): string {
  if (/ERROR|Fatal|Exception/i.test(line)) return 'error'
  if (/WARNING/i.test(line)) return 'warning'
  if (/INFO|Notice/i.test(line)) return 'info'
  return ''
}

async function loadLogs() {
  loading.value = true
  loadError.value = ''
  try {
    const response = await adminApi.getLogs()
    const body = response.data
    if (isApiSuccess(body)) {
      errorLines.value = Array.isArray(body.data.errors) ? body.data.errors : [String(body.data.errors)]
      requestLines.value = Array.isArray(body.data.requests) ? body.data.requests : [String(body.data.requests)]
    } else {
      loadError.value = 'Failed to load logs.'
    }
  } catch (err) {
    loadError.value = requestErrorMessage(err, 'Failed to load logs.')
  } finally {
    loading.value = false
  }
}

function toggleAutoRefresh() {
  if (autoRefresh.value) {
    refreshTimer = setInterval(loadLogs, 10000)
  } else if (refreshTimer) {
    clearInterval(refreshTimer)
    refreshTimer = null
  }
}

function downloadActiveTab() {
  let content = ''
  let filename = 'logs.txt'
  if (activeTab.value === 'errors') {
    content = errorLines.value.join('\n')
    filename = 'error-logs.txt'
  } else if (activeTab.value === 'requests') {
    content = requestLines.value.join('\n')
    filename = 'request-logs.txt'
  } else {
    content = Object.entries(systemInfo.value)
      .map(([k, v]) => `${k}: ${v}`)
      .join('\n')
    filename = 'system-info.txt'
  }

  downloadBlob(new Blob([content], { type: 'text/plain' }), filename)
}

async function refresh() {
  await loadLogs()
  toast.success('Logs refreshed')
}

onBeforeUnmount(() => {
  if (refreshTimer) clearInterval(refreshTimer)
})

onMounted(loadLogs)
</script>

<template>
  <div class="container logs-page">
    <h1>System Logs</h1>
    <p class="text-muted">Review system logs for troubleshooting and monitoring.</p>

    <div class="logs-layout">
      <div class="logs-sidebar">
        <div class="tabs-vertical">
          <button type="button" :class="{ active: activeTab === 'errors' }" @click="activeTab = 'errors'">Error Logs</button>
          <button type="button" :class="{ active: activeTab === 'requests' }" @click="activeTab = 'requests'">Request Logs</button>
          <button type="button" :class="{ active: activeTab === 'system' }" @click="activeTab = 'system'">System Info</button>
        </div>
        <div class="sidebar-actions">
          <button type="button" class="btn btn-outline full-width" @click="refresh">Refresh Logs</button>
          <button type="button" class="btn btn-outline full-width" @click="downloadActiveTab">Download Logs</button>
        </div>
      </div>

      <div class="card logs-content-card">
        <div class="logs-content-header">
          <h3>{{ activeTab === 'errors' ? 'Error Logs' : activeTab === 'requests' ? 'Request Logs' : 'System Info' }}</h3>
          <label class="auto-refresh-toggle">
            <input v-model="autoRefresh" type="checkbox" @change="toggleAutoRefresh" />
            Auto-refresh
          </label>
        </div>

        <div v-if="loading" class="spinner" role="status" aria-label="Loading logs"></div>
        <p v-else-if="loadError" class="alert alert-danger">{{ loadError }}</p>

        <div v-else class="log-content">
          <template v-if="activeTab === 'errors'">
            <p v-if="!errorLines.length" class="text-muted">No error logs available</p>
            <div v-for="(line, i) in errorLines" :key="i" class="log-line" :class="lineVariant(line)">{{ line }}</div>
          </template>
          <template v-else-if="activeTab === 'requests'">
            <p v-if="!requestLines.length" class="text-muted">No request logs available</p>
            <div v-for="(line, i) in requestLines" :key="i" class="log-line">{{ line }}</div>
          </template>
          <template v-else>
            <div v-for="(value, key) in systemInfo" :key="key" class="log-line">
              <strong>{{ key }}:</strong> {{ value }}
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped src="@/css/views/admin/Logs.css"></style>
