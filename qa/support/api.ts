import type { APIRequestContext } from '@playwright/test';

/**
 * Thin wrappers around the app's real HTTP API for test setup/teardown that
 * can't be done through a UI flow (e.g. an admin creating a book fixture
 * ahead of a test). These never touch the database directly — everything
 * here is a request the app itself serves and validates.
 */

interface LoginResponse {
  status: string;
  data?: { token?: string; user?: { isAdmin?: boolean } };
  message?: unknown;
}

/**
 * Logs in via POST /api/v1/login on the given request context. The context
 * keeps the session cookie for subsequent calls, which is what
 * AuthenticatedUser::isAdmin() actually checks server-side (not the JWT).
 */
export async function loginAsAdmin(
  request: APIRequestContext,
  email: string,
  password: string,
): Promise<string> {
  const { token, isAdmin } = await loginViaApi(request, email, password);
  if (!isAdmin) {
    throw new Error(
      `"${email}" logged in but is not an admin. Provision one with: ` +
        `composer admin:create -- ${email} <password>`,
    );
  }

  return token;
}

interface CreateBookResponse {
  status: string;
  data?: { insertedId?: string };
  message?: unknown;
}

/**
 * POST /api/v1/books (multipart) — the single-book endpoint behind the app's
 * upload flow (frontend/src/views/admin/UploadPdf.vue calls the equivalent
 * mass-upload endpoint; this is its one-file sibling, used here for fixture
 * setup that doesn't need a UI round-trip).
 */
export async function createBookViaApi(
  request: APIRequestContext,
  adminToken: string,
  input: { title: string; author?: string; pdf: Buffer },
): Promise<string> {
  const response = await request.post('/api/v1/books', {
    headers: { Authorization: `Bearer ${adminToken}` },
    multipart: {
      title: input.title,
      author: input.author ?? 'QA Test Author',
      categories: '[]',
      downloadable: 'true',
      bookFile: {
        name: 'sample.pdf',
        mimeType: 'application/pdf',
        buffer: input.pdf,
      },
    },
  });
  const body = (await response.json()) as CreateBookResponse;

  if (!response.ok() || !body.data?.insertedId) {
    throw new Error(`createBookViaApi failed: ${response.status()} ${JSON.stringify(body)}`);
  }

  return body.data.insertedId;
}

/** PUT /api/v1/books/:id — same endpoint the dashboard's status/featured toggles use. */
export async function setBookVisibility(
  request: APIRequestContext,
  adminToken: string,
  id: string,
  fields: { status?: 'public' | 'draft'; featured?: boolean },
): Promise<void> {
  const response = await request.put(`/api/v1/books/${id}`, {
    headers: { Authorization: `Bearer ${adminToken}` },
    data: fields,
  });
  if (!response.ok()) {
    throw new Error(`setBookVisibility failed: ${response.status()} ${await response.text()}`);
  }
}

export interface UploadedBook {
  _id: string | { $oid: string };
  title: string;
  author?: string;
  description?: string;
  isbn?: string;
  status?: string;
  featured?: boolean;
  downloadable?: boolean;
  file_extension?: string;
  categories?: string[];
}

export function bookId(book: UploadedBook): string {
  return typeof book._id === 'string' ? book._id : book._id.$oid;
}

/**
 * GET /api/v1/books is unauthenticated and returns every book regardless of
 * status — the same admin "all books" list the Dashboard table reads from.
 * Used both to find a book's id for cleanup, and to cross-check that a UI
 * action (an upload, a status toggle, ...) actually persisted the fields it
 * claimed to, rather than trusting the page's own success message.
 */
export async function getBookByTitle(request: APIRequestContext, title: string): Promise<UploadedBook | null> {
  const response = await request.get('/api/v1/books');
  if (!response.ok()) {
    return null;
  }
  const body = (await response.json()) as { data?: UploadedBook[] };
  return body.data?.find((book) => book.title === title) ?? null;
}

export async function findBookIdByTitle(request: APIRequestContext, title: string): Promise<string | null> {
  const book = await getBookByTitle(request, title);
  return book ? bookId(book) : null;
}

/** DELETE /api/v1/books/:id */
export async function deleteBookViaApi(
  request: APIRequestContext,
  adminToken: string,
  id: string,
): Promise<void> {
  const response = await request.delete(`/api/v1/books/${id}`, {
    headers: { Authorization: `Bearer ${adminToken}` },
  });
  if (!response.ok()) {
    throw new Error(`deleteBookViaApi failed: ${response.status()} ${await response.text()}`);
  }
}

/**
 * Finds and deletes each book by title — cleanup for books created through a
 * UI flow (e.g. the upload page) whose ids the test never saw. A title with
 * no matching book is skipped, not an error: the lookup only ever misses
 * when creation itself failed, in which case there's nothing to delete.
 */
export async function deleteBooksByTitle(
  request: APIRequestContext,
  adminToken: string,
  titles: string[],
): Promise<void> {
  for (const title of titles) {
    const id = await findBookIdByTitle(request, title);
    if (id) {
      await deleteBookViaApi(request, adminToken, id);
    }
  }
}

/** POST /api/v1/login for any account; returns its JWT (for API-side setup/cleanup as that user). */
export async function loginViaApi(
  request: APIRequestContext,
  email: string,
  password: string,
): Promise<{ token: string; isAdmin: boolean }> {
  const response = await request.post('/api/v1/login', { data: { email, password } });
  const body = (await response.json()) as LoginResponse;
  if (!response.ok() || body.status !== 'success' || !body.data?.token) {
    throw new Error(`Login failed for ${email}: ${response.status()} ${JSON.stringify(body)}`);
  }
  return { token: body.data.token, isAdmin: !!body.data.user?.isAdmin };
}

/** A private highlight (has rects) or page note, as the annotations API returns it. */
export interface ApiAnnotation {
  id: string;
  page: number;
  rects: number[][];
  quote: string;
  note: string;
  color: string | null;
}

/** GET /api/v1/books/:id/annotations — only ever the calling user's own annotations. */
export async function listAnnotations(request: APIRequestContext, token: string, bookId: string): Promise<ApiAnnotation[]> {
  const response = await request.get(`/api/v1/books/${bookId}/annotations`, {
    headers: { Authorization: `Bearer ${token}` },
  });
  if (!response.ok()) {
    throw new Error(`listAnnotations failed: ${response.status()} ${await response.text()}`);
  }
  return ((await response.json()) as { data: ApiAnnotation[] }).data;
}

/** POST /api/v1/books/:id/annotations — seeds a highlight or note as that user. */
export async function createAnnotation(
  request: APIRequestContext,
  token: string,
  bookId: string,
  annotation: Omit<ApiAnnotation, 'id'>,
): Promise<ApiAnnotation> {
  const response = await request.post(`/api/v1/books/${bookId}/annotations`, {
    headers: { Authorization: `Bearer ${token}` },
    data: annotation,
  });
  if (!response.ok()) {
    throw new Error(`createAnnotation failed: ${response.status()} ${await response.text()}`);
  }
  return ((await response.json()) as { data: ApiAnnotation }).data;
}

/** Removes every annotation the user made on a book (test teardown). */
export async function deleteAllAnnotations(request: APIRequestContext, token: string, bookId: string): Promise<void> {
  for (const annotation of await listAnnotations(request, token, bookId)) {
    await request.delete(`/api/v1/annotations/${annotation.id}`, { headers: { Authorization: `Bearer ${token}` } });
  }
}

/** GET /api/v1/books/:id — e.g. to cross-check a book's average rating. */
export async function getBookViaApi(
  request: APIRequestContext,
  id: string,
): Promise<{ title: string; average_rating?: number; review_count?: number }> {
  const response = await request.get(`/api/v1/books/${id}`);
  if (!response.ok()) {
    throw new Error(`getBookViaApi failed: ${response.status()} ${await response.text()}`);
  }
  return ((await response.json()) as { data: { title: string; average_rating?: number; review_count?: number } }).data;
}
