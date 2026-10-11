import client from './client'
import type { ApiResponse } from '@/types/api'
import type { Book, LoanRecord } from '@/types/book'

/** Full catalog (all statuses) — admin only. */
export function getAllBooks() {
  return client.get<ApiResponse<Book[]>>('/v1/books')
}

export interface BookUpdatePayload {
  title?: string
  author?: string
  year?: string
  description?: string
  categories?: string[]
  status?: string
  featured?: boolean
  isbn?: string
  downloadable?: boolean
  loan_copies?: number
  loan_days?: number
  shelf_location?: string
}

export function updateBook(id: string, payload: BookUpdatePayload) {
  return client.put<ApiResponse<string>>(`/v1/books/${id}`, payload)
}

export function deleteBook(id: string) {
  return client.delete<ApiResponse<string>>(`/v1/books/${id}`)
}

export interface UploadDefaults {
  categories: string[]
  status: string
  downloadable: boolean
}

export interface UploadFileMeta {
  file: File
  title: string
  author: string
  year: string
  isbn: string
  description: string
  /** This file's own categories, on top of the defaults. */
  categories: string[]
}

export interface MetadataLookup {
  title: string
  author: string
  year: string
  isbn: string
  description: string
  categories: string[]
}

/** Google Books / Open Library details for a book (null when not found) — pre-fills the upload form. */
export function lookupMetadata(params: { isbn?: string; title?: string; author?: string }) {
  return client.get<ApiResponse<MetadataLookup | null>>('/v1/admin/metadata-lookup', { params })
}

export interface UploadResult {
  message: string
  results: {
    success: { filename: string; title: string; id: string }[]
    failed: { filename: string; reason: string }[]
  }
}

export function uploadBooks(files: UploadFileMeta[], defaults: UploadDefaults, onProgress?: (percent: number) => void) {
  const formData = new FormData()
  formData.append('defaultCategories', JSON.stringify(defaults.categories))
  formData.append('defaultStatus', defaults.status)
  formData.append('defaultDownloadable', defaults.downloadable ? 'true' : 'false')

  files.forEach((item, index) => {
    formData.append('books[]', item.file)
    formData.append(
      `metadata_${index}`,
      JSON.stringify({
        title: item.title,
        author: item.author,
        year: item.year,
        isbn: item.isbn,
        description: item.description,
        // Sent per file, this replaces the default categories server-side, so merge them here.
        categories: [...new Set([...defaults.categories, ...item.categories])],
      }),
    )
  })

  return client.post<ApiResponse<UploadResult>>('/v1/books/upload', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
    onUploadProgress: (event) => {
      if (onProgress && event.total) {
        onProgress(Math.round((event.loaded * 100) / event.total))
      }
    },
  })
}

export interface AdminLogs {
  errors: string[] | string
  requests: string[] | string
}

export function getLoans() {
  return client.get<ApiResponse<LoanRecord[]>>('/v1/admin/loans')
}

/** Hand a physical copy to a reader at the desk (their reservation, or a walk-in). */
export function checkOut(bookId: string, email: string) {
  return client.post<ApiResponse<{ due_at: string; username: string | null }>>('/v1/admin/loans/checkout', {
    book_id: bookId,
    email,
  })
}

/** A copy is back on the shelf (also ends a reservation or PDF loan early). */
export function checkIn(bookId: string, userId: string) {
  return client.post<ApiResponse<string>>('/v1/admin/loans/checkin', { book_id: bookId, user_id: userId })
}

export interface PhysicalBookPayload {
  title: string
  author: string
  year: string
  isbn: string
  description: string
  categories: string[]
  status: string
  loan_copies: number
  loan_days: number
  shelf_location: string
}

export function addPhysicalBook(payload: PhysicalBookPayload) {
  return client.post<ApiResponse<unknown>>('/v1/books/physical', payload)
}

export function getLogs() {
  return client.get<ApiResponse<AdminLogs>>('/v1/admin/logs')
}
