<?php

// filepath: /Users/hub/Documents/Personal/GenCode/E-Lib/App/Services/CasService.php
namespace App\Services;

use App\Includes\Environment;
use App\Includes\JwtHelper;

class CasService
{
    private string $casServerUrl;
    private \GuzzleHttp\Client $httpClient;
    private ?UserService $userService = null;

    public function __construct()
    {
        $this->casServerUrl = Environment::get('CAS_SERVER_URL', 'https://cas-server.example.org/cas');
        $this->httpClient = new \GuzzleHttp\Client();
    }

    /**
     * Validates a CAS ticket against the CAS server and returns the CAS username
     * (the principal on line 2 of a CAS 1.0 validation response), or null on failure.
     */
    public function authenticate(string $ticket, string $serviceUrl): ?string
    {
        try {
            $response = $this->httpClient->get("{$this->casServerUrl}/validate", [
                'query' => [
                    'ticket' => $ticket,
                    'service' => $serviceUrl
                ]
            ]);
            $body = $response->getBody()->getContents();

            // Process the CAS server response: line 1 is "yes"/"no", line 2 (on success) is the username
            $lines = preg_split('/\r\n|\r|\n/', trim($body)) ?: [];
            if (strtolower(trim($lines[0] ?? '')) !== 'yes') {
                return null;
            }

            $username = trim($lines[1] ?? '');
            return $username !== '' ? $username : null;
        } catch (\Exception $e) {
            echo("CAS authentication error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Validates the ticket and, if the CAS username resolves to a local user, mints the
     * same JWT shape UserController::handleLogin issues. Returns null on any failure so the
     * caller (PageRouter's /cas-login branch) can redirect without a token.
     */
    public function authenticateAndIssueToken(string $ticket, string $serviceUrl): ?string
    {
        $casUsername = $this->authenticate($ticket, $serviceUrl);
        if ($casUsername === null) {
            return null;
        }

        $user = $this->resolveLocalUser($casUsername);
        if ($user === null) {
            return null;
        }

        return JwtHelper::generateToken([
            'user_id' => (string) $user['_id'],
            'email' => $user['email'],
            'isAdmin' => $user['isAdmin'] ?? false,
        ]);
    }

    /**
     * PENDING DECISION POINT: it isn't yet confirmed whether HMU's CAS server releases the
     * university email as the validated principal, or a bare netid that needs a domain
     * suffix. Until that's confirmed, this tries the CAS username as an email directly, then
     * falls back to appending CAS_EMAIL_DOMAIN (e.g. "@hmu.gr") if configured. Revisit here
     * once the real CAS response shape is known — see docs/architecture/vue-spa-migration-plan.md.
     *
     * @return array<string, mixed>|null
     */
    private function resolveLocalUser(string $casUsername): ?array
    {
        $userService = $this->getUserService();

        try {
            $user = $userService->getUserByEmail($casUsername);
            if ($user !== null) {
                return $this->reclaimIfNeeded($user);
            }
        } catch (\InvalidArgumentException $e) {
            // $casUsername wasn't a valid email (likely a bare netid) — fall through below.
        }

        if (str_contains($casUsername, '@')) {
            return null;
        }

        $domain = trim((string) Environment::get('CAS_EMAIL_DOMAIN', ''));
        if ($domain === '') {
            return null;
        }

        try {
            $user = $userService->getUserByEmail($casUsername . $domain);
            return $user !== null ? $this->reclaimIfNeeded($user) : null;
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }

    /**
     * Open self-signup never verifies that the registrant controls the email they supply, so
     * an attacker can pre-register an account under a target's institutional address before
     * the target ever uses the app (CWE-287, pre-account-takeover). CAS is the authoritative
     * identity check, so the first successful CAS login for an account reclaims it: whatever
     * password is on file stops working (replaced with an unguessable one an attacker can't
     * have set) and the account is flagged so this only happens once per account.
     *
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function reclaimIfNeeded(array $user): array
    {
        if (!empty($user['cas_verified'])) {
            return $user;
        }

        $this->getUserService()->updateUser((string) $user['_id'], [
            'password' => password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT),
            'cas_verified' => true,
        ]);
        $user['cas_verified'] = true;

        return $user;
    }

    private function getUserService(): UserService
    {
        if ($this->userService === null) {
            $this->userService = new UserService();
        }
        return $this->userService;
    }
}
