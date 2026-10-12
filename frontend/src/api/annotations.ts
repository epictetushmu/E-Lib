import client from './client'
import type { ApiResponse } from '@/types/api'
import type { Annotation, FriendAnnotation, HighlightColor, NewAnnotation } from '@/types/annotation'

/** The signed-in user's own highlights and notes for a book. */
export function listAnnotations(bookId: string) {
  return client.get<ApiResponse<Annotation[]>>(`/v1/books/${bookId}/annotations`)
}

/** Highlights and notes on this book by friends who share theirs (only for a book the caller can read). */
export function listFriendAnnotations(bookId: string) {
  return client.get<ApiResponse<FriendAnnotation[]>>(`/v1/books/${bookId}/annotations/friends`)
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
