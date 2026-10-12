import { useToastStore } from '@/stores/toast'

export function useToast() {
  const store = useToastStore()
  return {
    success: (message: string) => store.push(message, 'success'),
    error: (message: string) => store.push(message, 'error'),
    info: (message: string) => store.push(message, 'info'),
  }
}
