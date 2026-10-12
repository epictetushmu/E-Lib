import axios from 'axios'

export interface ApiSuccess<T> {
  status: 'success'
  data: T
}

export interface ApiError {
  status: 'error'
  message: string | Record<string, string[]>
}

export type ApiResponse<T> = ApiSuccess<T> | ApiError

export function isApiSuccess<T>(response: ApiResponse<T>): response is ApiSuccess<T> {
  return response.status === 'success'
}

/** Extracts a human-readable message from an ApiError's `message` field. */
export function apiErrorMessage(error: ApiError, fallback = 'Something went wrong'): string {
  if (typeof error.message === 'string') {
    return error.message
  }
  if (error.message && typeof error.message === 'object') {
    return Object.values(error.message).flat().join(' ')
  }
  return fallback
}

/**
 * Extracts a human-readable message from a caught request error without
 * asserting `any` on the catch binding. Handles axios errors whose response
 * body carries either a plain string `message` or ApiError's field-errors
 * object, and falls back to a generic `Error`'s own message otherwise.
 */
export function requestErrorMessage(err: unknown, fallback: string): string {
  if (axios.isAxiosError(err)) {
    const message = err.response?.data?.message
    if (typeof message === 'string' || (message && typeof message === 'object')) {
      return apiErrorMessage({ status: 'error', message }, fallback)
    }
  }
  if (err instanceof Error && err.message) {
    return err.message
  }
  return fallback
}
