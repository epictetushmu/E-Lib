<?php

namespace App\Router;

use App\Includes\Environment;
use App\Services\CasService;

class PageRouter
{
    private CasService $casService;

    public function __construct()
    {
        $this->casService = new CasService();
    }

    public function handleRequest(string $path): void
    {
        // Set security headers at the beginning of request handling
        // but only if no output has been sent yet
        if (!headers_sent()) {
            $this->setSecurityHeaders();
        }

        $pathOnly = parse_url($path, PHP_URL_PATH);

        // CAS SSO callback: validate the ticket, mint a JWT, hand it to the SPA via a URL
        // fragment (never sent to/logged by the server) for AuthCallback.vue to pick up.
        if (strpos((string) $pathOnly, '/cas-login') === 0) {
            $ticket = $_GET['ticket'] ?? null;
            // LoginForm.vue embeds a single-use nonce in the service URL's own query string
            // before redirecting to CAS, and AuthCallback.vue refuses the token unless this
            // same nonce comes back — binding the round trip to the tab that started it, so an
            // externally-supplied #token= link (from a valid JWT obtained some other way) can't
            // be adopted as a session. The service URL used here must match, character for
            // character, the one CAS was given at login time, or ticket validation fails.
            $state = $_GET['state'] ?? null;
            $appUrl = rtrim((string) Environment::get('APP_URL', 'http://localhost:8080'), '/');
            $serviceUrl = $appUrl . '/cas-login'
                . (is_string($state) && $state !== '' ? '?state=' . rawurlencode($state) : '');

            $token = (is_string($ticket) && $ticket !== '')
                ? $this->casService->authenticateAndIssueToken($ticket, $serviceUrl)
                : null;

            if (!headers_sent()) {
                $fragment = '';
                if ($token !== null) {
                    $fragment = '#token=' . $token;
                    if (is_string($state) && $state !== '') {
                        $fragment .= '&state=' . rawurlencode($state);
                    }
                }
                header('Location: ' . $appUrl . '/auth/callback' . $fragment);
                exit;
            }
            return;
        }

        // Everything else is a client-side route handled by the Vue SPA; static files under
        // public/dist/ are served directly by Apache/PHP's built-in server before this runs,
        // so anything reaching here just needs the SPA shell.
        $this->serveSpaShell();
    }

    private function serveSpaShell(): void
    {
        $indexPath = dirname(__DIR__, 2) . '/public/dist/index.html';

        if (!is_file($indexPath)) {
            http_response_code(503);
            echo 'Frontend build not found. Run: cd frontend && npm ci && npm run build';
            return;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        readfile($indexPath);
    }

    private function setSecurityHeaders(): void
    {
        // Security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');

        $connectSrc = $this->buildConnectSrcDirective();

        // connect-src: fetch/XHR (axios, PDF.js) — default-src alone blocks cross-origin & blob workers
        // worker-src: PDF.js may use blob workers; worker script from cdnjs
        header(
            "Content-Security-Policy: default-src 'self'; "
            . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
            . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
            . "font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
            . "img-src 'self' data: blob: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
            . "connect-src {$connectSrc}; "
            . "worker-src 'self' blob: https://cdnjs.cloudflare.com;"
        );

        // Remove content-type JSON header since this is for HTML pages
        // Only set CORS headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        // Don't set Content-Type header here as it should be different for HTML vs JSON responses
    }

    /**
     * Origins allowed for fetch/XHR (axios, PDF.js). 'self' is the page origin; add CDNs, blob, and
     * env URLs so localhost vs 127.0.0.1 and API_BASE_URL do not violate connect-src.
     */
    private function buildConnectSrcDirective(): string
    {
        $parts = [
            "'self'",
            'blob:',
            'data:',
            'https://cdn.jsdelivr.net',
            'https://cdnjs.cloudflare.com',
        ];

        foreach (['APP_URL', 'API_BASE_URL'] as $key) {
            $raw = Environment::get($key, '');
            if ($raw === '' || $raw === false) {
                continue;
            }
            $u = parse_url(trim((string) $raw));
            if (!empty($u['scheme']) && !empty($u['host'])) {
                $origin = $u['scheme'] . '://' . $u['host'];
                if (!empty($u['port'])) {
                    $origin .= ':' . $u['port'];
                }
                $parts[] = $origin;
            }
        }

        // Dev: page on http://localhost:8000 calling http://127.0.0.1:8000 is cross-origin — allow both
        $parts[] = 'http://127.0.0.1:*';
        $parts[] = 'http://localhost:*';
        $parts[] = 'https://127.0.0.1:*';
        $parts[] = 'https://localhost:*';

        return implode(' ', array_unique($parts));
    }
}
