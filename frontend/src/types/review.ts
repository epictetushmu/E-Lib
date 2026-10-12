export interface ReviewReply {
  id: string
  /** The reply this one answers; null = it answers the review. */
  parent_id: string | null
  /** Null once deleted. */
  user_id: string | null
  username: string
  text: string
  /** Deleted by its author but kept as a placeholder because others answered it. */
  deleted: boolean
  /** ISO 8601. */
  created_at: string | null
  /** Set once the author has edited it. */
  updated_at: string | null
}

export interface Review {
  user_id: string
  username: string
  rating: number
  comment: string
  /** "Y-m-d H:i:s" in UTC, as the server stores it. */
  created_at: string
  updated_at?: string
  /** The review's conversation, oldest first. */
  replies?: ReviewReply[]
}
