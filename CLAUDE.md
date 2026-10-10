# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

E-Lib is a PHP 8.2 digital library management app (Hellenic Mediterranean University) built on a **custom MVC framework** (no Laravel/Symfony) with MongoDB as the sole datastore. It handles book uploads (PDF/EPUB/Word/PowerPoint/MOBI/AZW/DJVU), thumbnail generation, an in-browser PDF reader, JWT + session + CAS authentication, reviews, and an admin panel.

## Commands

```bash
composer install                       # install PHP deps
php -S localhost:8000 -t public        # run dev server (serves from public/)
docker compose up -d --build           # local Docker: app + throwaway MongoDB (http://localhost:8081) — needs COMPOSE_FILE from .env.example
DEPLOY_HOST=... DEPLOY_DIR=... ./deploy.sh   # production: rsync + `docker compose up -d --build` on the server

composer run lint                      # PHPCS, PSR-12 (App/, public/index.php, scripts/, etc.)
composer run lint:fix                  # PHPCBF autofix
composer run analyse                   # PHPStan (level 6, App/ only)
composer run check                     # lint + analyse + phpunit (what CI runs)

php scripts/mongo-ping.php             # verify MongoDB connectivity from the CLI (use when DB init fails)

cd frontend && npm install             # install frontend deps (Vue 3 + Vite + TS)
cd frontend && npm run dev             # Vite dev server on :5173, proxies /api to localhost:8000
cd frontend && npm run build           # vue-tsc typecheck + build to public/dist/ (what PHP serves)
cd frontend && npm run typecheck       # vue-tsc --noEmit only
cd frontend && npm run lint            # ESLint (eslint-plugin-vue "essential" + typescript-eslint) on frontend/src
cd frontend && npm run lint:fix        # ESLint autofix

npm install                            # install e2e tooling deps (repo root — separate package.json from frontend/)
npm run lint                           # ESLint (typescript-eslint + eslint-plugin-playwright) on qa/ + playwright.config.ts
npm run lint:fix                       # ESLint autofix
```

`composer run check` runs the PHPUnit suite (`tests/Unit/`, mocked collaborators via `tests/Support/*TestCase.php` — no real MongoDB needed). `tests/verify_refactoring.php` and `tests/verify_pdf_restriction.php` are separate standalone scripts run directly with `php tests/<file>.php`; the refactoring one talks to a real MongoDB and inserts/deletes throwaway documents, so only run it against a dev database.

Nix users: `shell.nix` provides a PHP 8.2 environment with the exact extensions the app needs (mongodb, imagick, gd, etc.) plus poppler-utils/imagemagick/libreoffice for the thumbnail pipeline — no local MongoDB is included, the shell expects `MONGO_URI` to point at Atlas.

## Architecture

### Request flow
`public/index.php` is the sole entry point: it loads the Composer autoloader, loads `.env` via `App\Includes\Environment`, initializes the MongoDB connection through `DatabaseRepository::getInstance()` (dying with a 503 if it fails — **MongoDB is required, there is no fallback**), then builds a `BaseRouter` and registers middleware before calling `handleRequest()`.

`BaseRouter` splits every request by path prefix: `/api/*` goes to `ApiRouter`, everything else to `PageRouter`. `ApiRouter` holds a flat array of `['method', 'path' (regex), 'handler' => [controller, method]]` routes matched with `preg_match`; MongoDB ObjectIds in paths are matched with `([0-9a-f]{24})` and passed as captured args to the handler. `PageRouter` has no route table at all: it special-cases `/cas-login` (see Auth below) and otherwise serves `public/dist/index.html` (the built Vue SPA) for every other path — client-side routing (Vue Router) takes it from there. Static files under `public/dist/assets/*` are served directly by Apache/PHP's built-in server before `index.php` ever runs.

### Middleware
`MiddlewareManager` wraps the router's final handler in middleware registered in `public/index.php`, innermost-out (`array_reverse`), each middleware calling `$next($request)` itself (`MiddlewareInterface`). Auth is enforced this way, not per-route in controllers: `JwtAuthMiddleware` (Bearer token) is given an explicit `['path' => ..., 'method' => ...]` allowlist of API routes to protect — a new protected route must be added to that list in `public/index.php`, since `ApiRouter` itself doesn't check auth. `AuthenticatedUser::id()` / `::isAdmin()` are the shared way to read the current user from session or JWT (session still applies if a request happens to carry the `PHPSESSID` cookie `UserController::handleLogin` sets, but the SPA's own auth is JWT-only).

### Data layer
Models (`App/Models`) extend `BaseModel`, which pulls the shared `DatabaseInterface` from `DatabaseFactory` (itself backed by the `DatabaseRepository` singleton — one live MongoDB connection for the whole request) and gives `findAll/findById/create/updateById/deleteById` against `$this->collection`. IDs are validated as 24-hex-char strings and converted with `MongoHelper::createObjectId`; `MongoHelper` also centralizes safe `Regex`/`UTCDateTime` creation and BSON→array conversion (`MongoHelper::toArray`) so callers don't touch the `mongodb` extension's BSON types directly. Controllers call Services (`BookService`, `UserService`, `CasService`, `EmailService`) which call Models — controllers should not talk to `DatabaseFactory`/Models directly.

### Frontend
`frontend/` is a Vue 3 + Vite + TypeScript SPA (Pinia for auth/toast state, Vue Router with `requireAuth`/`requireAdmin` guards, one axios instance per resource in `src/api/*`). `npm run build` type-checks (`vue-tsc`) and outputs to `public/dist/`, which `PageRouter` serves as-is — there is no server-side rendering or templating layer; `App/Views` was deleted in the SPA cutover. The dev workflow is `php -S localhost:8000 -t public` alongside `cd frontend && npm run dev` (Vite on `:5173`, proxying `/api` to the PHP server) — see `docs/architecture/vue-spa-migration-plan.md` for the full migration design.

### Security headers & CSP
`PageRouter::setSecurityHeaders()` sets CSP/HSTS/frame headers on every non-API page request. The `connect-src` directive is built dynamically from `APP_URL`/`API_BASE_URL` env values plus hardcoded localhost/127.0.0.1 allowances for dev — if you add a new external host the frontend calls (CDN, API), it needs to be added here or requests will be silently blocked by CSP.

### Auth
Two mechanisms coexist: JWT bearer tokens (`JwtHelper`, checked by `JwtAuthMiddleware`) for all API auth (`UserController::handleLogin`'s JWT payload carries `user_id`/`email`/`isAdmin`), and CAS SSO (`CasService`) for university login, bridged into that same JWT shape. `PageRouter` special-cases `/cas-login`: it validates the ticket via `CasService::authenticateAndIssueToken()` (which resolves the CAS username to a local user — see the `CAS_EMAIL_DOMAIN`-gated pending-decision comment in `CasService::resolveLocalUser()`, since HMU's exact CAS response shape isn't confirmed yet) and redirects to `APP_URL/auth/callback#token=...` — a URL fragment, so the token is never sent to/logged by the server; `AuthCallback.vue` reads `location.hash` client-side. `JWT_SECRET_KEY` falls back to a hardcoded dev-only string in `App/bootstrap.php` if unset — this must be set in production `.env`.

### File handling
`App/Helpers/FileHelper.php` validates and stores uploaded documents (extension allowlist, not MIME-only) and drives thumbnail generation via ImageMagick (`pdftoppm`/`convert`) and LibreOffice (for Office format conversion) — see `MongoSchematics.png` and `docs/architecture/` for the data-model/refactor background.

### Content search ("search inside books")
`GET /api/v1/content-search?q=` (public; drafts only for admins) fuses two Atlas searches over the `BookChunks` collection (one doc per PDF page: `book_id, page, text, embedding, embedding_model, status`) with Reciprocal Rank Fusion in `ContentSearchService::fuse()`: `$search` (BM25, Greek+English analyzers) and `$vectorSearch` (int8 BSON vectors from a self-hosted bge-m3 `embedder` container; semantic hits below `SEMANTIC_MIN_SCORE` are dropped). If the embedder is down/slow (`EMBEDDING_TIMEOUT`), search degrades to keyword-only. Indexing never happens in a web request: `scripts/index-worker.php` (the `worker` compose service, scalable) claims books atomically via `Books::claimNextForIndexing()` (Mongo is the job queue; `index_*` fields on the book are the state: lease, progress for resume, attempts/backoff, `no_text` for scanned PDFs) — existing books need no backfill, and changing `EMBEDDING_MODEL` re-embeds everything in the background. `Books::deleteBook`/`updateBook` cascade deletes/status changes to the chunks. The Atlas indexes are created by `scripts/db-setup.php` (needs Atlas or the `mongodb/mongodb-atlas-local` dev image; plain mongo — e.g. CI's `mongo:4.4` — just gets a warning, so content search has no e2e coverage in CI).

### Docker
`docker-compose.yml` is the **production** stack (Caddy + app; uploads in the `uploads` named volume, `.env` mounted read-only, never baked into the image — see `.dockerignore`). `docker-compose.dev.yml` overrides it for local dev (bind-mounted code/uploads/logs, `APP_ENV=development`, a throwaway `mongo` container) and is selected via `COMPOSE_FILE` in the local `.env`; the server's `.env` must not set it (`deploy.sh` refuses). CI does not use compose — the GitHub workflows run their own `mongo:4.4` service container, so tests never touch Atlas.

## Conventions

- PSR-12 is enforced by CI (`.github/workflows/lint.yml`) via `composer run lint`/`composer run analyse`; line length limit is 140 (soft) / 220 (hard) per `phpcs.xml`.
- Bootstrap/entry scripts (`App/bootstrap.php`, `public/index.php`, `docker-entrypoint.php`, `check-system.php`) are explicitly exempted from PSR1's side-effect rule since they mix declarations and top-level execution by design.
- `.env` is loaded manually by `App\Includes\Environment` (no vlucas/phpdotenv); values are read with `Environment::get($key, $default)`, never `$_ENV`/`getenv` directly, so defaults stay consistent across the app.
