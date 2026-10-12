import client from './client'
import type { ApiResponse } from '@/types/api'
import type { Book } from '@/types/book'

export interface Friend {
  user_id: string
  username: string
  /** incoming = they asked you; outgoing = you asked them. */
  status: 'friends' | 'incoming' | 'outgoing'
  /** ISO 8601: when you became friends, or when the request was sent. */
  since: string | null
}

export interface FriendReview {
  book_id: string
  title: string
  author: string
  thumbnail: string
  rating: number
  comment: string
  /** "Y-m-d H:i:s" in UTC, as the server stores reviews. */
  created_at: string | null
}

export interface FriendLibrary {
  username: string
  saved: Book[]
  reviews: FriendReview[]
}

export function getFriends() {
  return client.get<ApiResponse<Friend[]>>('/v1/friends')
}

export function sendRequest(email: string) {
  return client.post<ApiResponse<string>>('/v1/friends', { email })
}

export function acceptRequest(userId: string) {
  return client.post<ApiResponse<string>>(`/v1/friends/${userId}/accept`)
}

/** Decline, cancel or unfriend. */
export function removeFriend(userId: string) {
  return client.delete<ApiResponse<string>>(`/v1/friends/${userId}`)
}

export function getFriendLibrary(userId: string) {
  return client.get<ApiResponse<FriendLibrary>>(`/v1/friends/${userId}/library`)
}
