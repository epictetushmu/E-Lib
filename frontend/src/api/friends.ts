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

/** Each section is null when the friend doesn't share it. */
export interface FriendLibrary {
  username: string
  saved: Book[] | null
  reviews: FriendReview[] | null
  /** Books they have borrowed right now. */
  loans: Book[] | null
  downloads: Book[] | null
}

/** What the signed-in user shares with friends. */
export interface Sharing {
  saved: boolean
  reviews: boolean
  loans: boolean
  downloads: boolean
  /** Shown to friends inside the reader, on books they can read. */
  notes: boolean
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

export function getSharing() {
  return client.get<ApiResponse<Sharing>>('/v1/user/sharing')
}

export function setSharing(changes: Partial<Sharing>) {
  return client.post<ApiResponse<Sharing>>('/v1/user/sharing', changes)
}

export function getFriendLibrary(userId: string) {
  return client.get<ApiResponse<FriendLibrary>>(`/v1/friends/${userId}/library`)
}
