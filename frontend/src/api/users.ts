import client from './client'
import type { ApiResponse } from '@/types/api'
import type { MongoId, Book, LoanRecord } from '@/types/book'

export interface LoginResult {
  token: string
  user: {
    id: MongoId
    email: string
    username: string
    isAdmin: boolean
  }
}

export interface UserProfile {
  _id: string
  username: string
  email: string
  isAdmin: boolean
  createdAt?: string
}

export function login(email: string, password: string) {
  return client.post<ApiResponse<LoginResult>>('/v1/login', { email, password })
}

export function signup(username: string, email: string, password: string) {
  return client.post<ApiResponse<string>>('/v1/signup', { username, email, password })
}

export function logout() {
  return client.get<ApiResponse<string>>('/v1/logout')
}

export function getProfile() {
  return client.get<ApiResponse<UserProfile>>('/v1/user/profile')
}

export function updateProfile(username: string) {
  return client.post<ApiResponse<string>>('/v1/update-profile', { username })
}

export function changePassword(currentPassword: string, newPassword: string) {
  return client.post<ApiResponse<string>>('/v1/change-password', {
    current_password: currentPassword,
    new_password: newPassword,
  })
}

export function saveBook(bookId: string) {
  return client.post<ApiResponse<string>>('/v1/save-book', { book_id: bookId })
}

export function removeBook(bookId: string) {
  return client.post<ApiResponse<string>>('/v1/remove-book', { book_id: bookId })
}

export function getSavedBooks() {
  return client.get<ApiResponse<Book[]>>('/v1/saved-books')
}

export function getDownloadedBooks() {
  return client.get<ApiResponse<Book[]>>('/v1/downloaded-books')
}

/** The caller's loans and reservations, newest first. */
export function getMyLoans() {
  return client.get<ApiResponse<LoanRecord[]>>('/v1/user/loans')
}

export interface UserNotification {
  id: string
  book_id: string | null
  message: string
  created_at: string | null
  read: boolean
}

export function getNotifications() {
  return client.get<ApiResponse<UserNotification[]>>('/v1/user/notifications')
}

export function markNotificationsRead() {
  return client.post<ApiResponse<string>>('/v1/user/notifications/read')
}
