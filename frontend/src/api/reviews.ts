import client from './client'
import type { ApiResponse } from '@/types/api'
import type { Review } from '@/types/review'

/** A book's reviews, each with its reply thread. */
export function getReviews(bookId: string) {
  return client.get<ApiResponse<Review[]>>(`/v1/reviews/${bookId}`)
}

export function addReview(bookId: string, rating: number, comment: string) {
  return client.post<ApiResponse<string>>('/v1/reviews', { book_id: bookId, rating, comment })
}

/** Deletes the caller's own review of the book, with its replies. */
export function deleteReview(bookId: string) {
  return client.delete<ApiResponse<string>>(`/v1/reviews/${bookId}`)
}

/** Replies in the thread of the review that reviewUserId wrote: to the review (parentId null) or to a reply in it. */
export function addReply(bookId: string, reviewUserId: string, parentId: string | null, text: string) {
  return client.post<ApiResponse<string>>(`/v1/reviews/${bookId}/replies`, {
    review_user_id: reviewUserId,
    parent_id: parentId,
    text,
  })
}

export function updateReply(replyId: string, text: string) {
  return client.put<ApiResponse<string>>(`/v1/replies/${replyId}`, { text })
}

export function deleteReply(replyId: string) {
  return client.delete<ApiResponse<string>>(`/v1/replies/${replyId}`)
}
