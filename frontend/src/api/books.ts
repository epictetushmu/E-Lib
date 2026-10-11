import client from './client'
import type { ApiResponse } from '@/types/api'
import type { Book, CatalogQuery, CatalogResult, ContentSearchResult } from '@/types/book'

export function getFeaturedBooks() {
  return client.get<ApiResponse<Book[]>>('/v1/books/featured')
}

export function listBooks() {
  return client.get<ApiResponse<Book[]>>('/v1/books/list')
}

/** Distinct category values already in use across the catalog, for the category picker. */
export function getCategories() {
  return client.get<ApiResponse<string[]>>('/v1/books/categories')
}

export function searchBooks(term: string) {
  return client.get<ApiResponse<Book[]>>(`/v1/search/${encodeURIComponent(term)}`)
}

/** Optional features this server has switched on. */
export function getFeatures() {
  return client.get<ApiResponse<{ contentSearch: boolean }>>('/v1/features')
}

/** Title/author search with filters and per-filter counts; an empty query lists the whole catalog. */
export function catalog(query: CatalogQuery) {
  return client.get<ApiResponse<CatalogResult>>('/v1/catalog', { params: query })
}

/** Search inside books: pages matching the query by words or by meaning. */
export function contentSearch(query: string) {
  return client.get<ApiResponse<ContentSearchResult[]>>('/v1/content-search', { params: { q: query } })
}

export function getBook(id: string) {
  return client.get<ApiResponse<Book>>(`/v1/books/${id}`)
}

export function thumbnailUrl(bookId: string): string {
  const base = (import.meta.env.VITE_API_BASE_URL ?? '/api').replace(/\/$/, '')
  return `${base}/v1/books/${bookId}/thumbnail`
}

/** Authenticated PDF download as a blob; caller is responsible for saving it. */
export function downloadBook(bookId: string) {
  return client.get<Blob>(`/v1/books/${bookId}/download`, { responseType: 'blob' })
}

export function borrowBook(bookId: string) {
  return client.post<ApiResponse<{ due_at: string | null; pickup_by: string | null }>>(`/v1/books/${bookId}/borrow`)
}

export function returnBook(bookId: string) {
  return client.post<ApiResponse<string>>(`/v1/books/${bookId}/return`)
}

export function renewBook(bookId: string) {
  return client.post<ApiResponse<{ due_at: string }>>(`/v1/books/${bookId}/renew`)
}

export function joinWaitlist(bookId: string) {
  return client.post<ApiResponse<string>>(`/v1/books/${bookId}/waitlist`)
}

export function leaveWaitlist(bookId: string) {
  return client.post<ApiResponse<string>>(`/v1/books/${bookId}/waitlist/leave`)
}

/** Raw PDF bytes for in-browser preview (Reader.vue passes this straight to pdfjs-dist). */
export function getBookFile(bookId: string) {
  return client.get<ArrayBuffer>(`/v1/books/${bookId}/file`, { responseType: 'arraybuffer' })
}
