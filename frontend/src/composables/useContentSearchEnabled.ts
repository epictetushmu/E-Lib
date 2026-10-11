import { ref } from 'vue'
import { getFeatures } from '@/api/books'
import { isApiSuccess } from '@/types/api'

// Fetched once per page load and shared: the setting only changes when the server is redeployed.
const enabled = ref(false)
let loading: Promise<void> | null = null

/** Whether the server has content search ("Inside books") switched on. False until known, and on errors. */
export function useContentSearchEnabled() {
  loading ??= getFeatures()
    .then((response) => {
      enabled.value = isApiSuccess(response.data) && response.data.data.contentSearch
    })
    .catch(() => {
      // Unknown means hidden: offering a search that then fails is worse than not offering it.
    })
  return enabled
}
