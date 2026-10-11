<?php

namespace App\Services;

use App\Includes\Environment;
use App\Models\Notifications;
use App\Models\Users;

/**
 * Tells a reader about their loans: always in the app (profile → Notifications), and by email when
 * SMTP is configured. Email failures are logged and never fail the action that caused them.
 */
class NotificationService
{
    private Notifications $notifications;

    public function __construct()
    {
        $this->notifications = new Notifications();
    }

    public function notify(string $userId, ?string $bookId, string $subject, string $message): void
    {
        $this->notifications->add($userId, $bookId, $message);

        // .env.example's placeholder host counts as "not configured", or every notice would wait out the timeout.
        if (in_array(Environment::get('MAIL_HOST', ''), ['', 'smtp.example.com'], true)) {
            return;
        }
        try {
            $user = (new Users())->getUserById($userId);
            if (!empty($user['email'])) {
                // ponytail: SMTP inside the request (10s timeout); move to a queue if desk checkouts feel slow
                (new EmailService())->sendToUser((string) $user['email'], (string) ($user['username'] ?? ''), $subject, $message);
            }
        } catch (\Throwable $e) {
            error_log('Notification email failed: ' . $e->getMessage());
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(string $userId): array
    {
        return $this->notifications->forUser($userId);
    }

    public function markAllRead(string $userId): void
    {
        $this->notifications->markAllRead($userId);
    }
}
