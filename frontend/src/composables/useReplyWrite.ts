import { ref } from 'vue'
import { isApiSuccess, apiErrorMessage, requestErrorMessage, type ApiError } from '@/types/api'
import { useToast } from '@/composables/useToast'

/** Runs a review/reply write, toasts the outcome, and calls onDone on success. Returns whether it worked. */
export function useReplyWrite(onDone: () => void) {
  const toast = useToast()
  const busy = ref(false)

  async function write(request: () => Promise<{ data: unknown }>, success: string, failure: string): Promise<boolean> {
    busy.value = true
    try {
      const { data: body } = await request()
      if (isApiSuccess(body as ApiError)) {
        toast.success(success)
        onDone()
        return true
      }
      toast.error(apiErrorMessage(body as ApiError, failure))
    } catch (err) {
      toast.error(requestErrorMessage(err, failure))
    } finally {
      busy.value = false
    }
    return false
  }

  return { busy, write }
}
