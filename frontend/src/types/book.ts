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
  /** Content-search indexing state, written by the indexing worker. Absent = not indexed yet. */
  index_status?: 'pending' | 'indexing' | 'done' | 'no_text' | 'failed'
  index_error?: string
}

/** One option of a catalog filter, with how many books picking it would give. */
export interface FacetOption {
  value: string
  count: number
}

/** GET /v1/catalog: one page of matching books plus counts for every filter. */
export interface CatalogResult {
  books: Book[]
  total: number
  page: number
  pageSize: number
  facets: {
    category: FacetOption[]
    format: FacetOption[]
    author: FacetOption[]
  }
  /** Earliest and latest publication year the other filters leave; null when no book has a year. */
  years: { min: number; max: number } | null
}

export interface CatalogQuery {
  q?: string
  category?: string[]
  format?: string[]
  author?: string[]
  year_from?: string
  year_to?: string
  sort?: string
  page?: number
}

/** One book from GET /v1/content-search, with the pages that matched best. */
export interface ContentSearchResult {
  book: Book
  matches: { page: number; snippet: string }[]
}
