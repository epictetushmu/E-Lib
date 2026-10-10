/** MongoDB ObjectId as it comes back from PHP's json_encode: either a plain string or {"$oid": "..."}. */
export type MongoId = string | { $oid: string }

export function idToString(id: MongoId | undefined | null): string {
  if (!id) return ''
  return typeof id === 'string' ? id : id.$oid
}

export interface Book {
  _id: MongoId
  title: string
  author?: string
  year?: string
  isbn?: string
  description?: string
  categories?: string[]
  status?: string
  featured?: boolean
  downloadable?: boolean
  thumbnail?: string
  average_rating?: number
  review_count?: number
  views?: number
  downloads?: number
  /** BSON UTCDateTime as json_encode emits it. */
  created_at?: { $date: { $numberLong: string } }
  published_date?: string
  language?: string
  pages?: number
  publisher?: string
  file_extension?: string
}
