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
  /** Copies lent at once; 0/absent = open access. Lent books are read only while borrowed. */
  loan_copies?: number
  /** Loan period in days (server defaults to 14). */
  loan_days?: number
  /** Shelf / call number of a physical book. */
  shelf_location?: string
  /** "physical" for a book that's only on the library's shelves (no PDF). */
  file_type?: string
  /** Lending state, only on GET /v1/books/{id} for lent books. due_at = the caller has it;
   *  pickup_by = the caller reserved a physical copy that's waiting at the desk. */
  loan?: {
    copies: number
    available: number
    loan_days: number
    due_at: string | null
    pickup_by: string | null
    /** Days a held copy waits: for pickup (physical) or to start reading (PDF off the waitlist). */
    pickup_days: number
    /** Readers in the queue, and the caller's place in it (1-based, null if not queued). */
    waiting: number
    position: number | null
    /** Renewals the caller has left on their loan (null without a loan). */
    renewals_left: number | null
  }
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

/** One loan record, from GET /v1/admin/loans (all) or /v1/user/loans (the caller's), newest first. */
export interface LoanRecord {
  id: string
  kind: 'digital' | 'physical'
  book_id: string
  book_title: string | null
  book_author: string | null
  shelf_location: string | null
  user_id: string
  username: string | null
  email: string | null
  reserved_at: string | null
  pickup_by: string | null
  borrowed_at: string | null
  due_at: string | null
  returned_at: string | null
  cancelled_at: string | null
  renewals: number
  status: 'active' | 'expired' | 'reserved' | 'not_collected' | 'cancelled' | 'out' | 'overdue' | 'returned'
}

export const LOAN_STATUS_LABELS: Record<LoanRecord['status'], string> = {
  active: 'Reading (PDF)',
  expired: 'Ended',
  reserved: 'Held for pickup',
  not_collected: 'Not picked up',
  cancelled: 'Cancelled',
  out: 'On loan',
  overdue: 'Overdue',
  returned: 'Returned',
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
