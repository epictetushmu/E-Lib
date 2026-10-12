import { defineStore } from 'pinia'

export interface Toast {
  id: number
  message: string
  variant: 'success' | 'error' | 'info'
}

let nextId = 1

export const useToastStore = defineStore('toast', {
  state: () => ({
    toasts: [] as Toast[],
  }),
  actions: {
    push(message: string, variant: Toast['variant'] = 'info', durationMs = 4000) {
      const id = nextId++
      this.toasts.push({ id, message, variant })
      setTimeout(() => this.dismiss(id), durationMs)
    },
    dismiss(id: number) {
      this.toasts = this.toasts.filter((t) => t.id !== id)
    },
  },
})
