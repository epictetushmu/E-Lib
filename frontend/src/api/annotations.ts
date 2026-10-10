import client from './client'
import type { ApiResponse } from '@/types/api'
import type { Annotation, HighlightColor, NewAnnotation } from '@/types/annotation'

/** The signed-in user's own highlights and notes for a book; the server never returns anyone else's. */
export function listAnnotations(bookId: string) {
  return client.get<ApiResponse<Annotation[]>>(`/v1/books/${bookId}/annotations`)
}

export function createAnnotation(bookId: string, annotation: NewAnnotation) {
  return client.post<ApiResponse<Annotation>>(`/v1/books/${bookId}/annotations`, annotation)
}

export function updateAnnotation(id: string, changes: { note?: string; color?: HighlightColor }) {
  return client.put<ApiResponse<Annotation>>(`/v1/annotations/${id}`, changes)
}

export function deleteAnnotation(id: string) {
  return client.delete<ApiResponse<string>>(`/v1/annotations/${id}`)
}
