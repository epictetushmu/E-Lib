<?php

namespace App\Services;

use App\Includes\Environment;
use App\Models\Notifications;
use App\Models\Users;

/**
 * Tells a reader about their loans: in the app straight away (profile → Notifications), and by email
 * from the scheduler (scripts/scheduler.php), so a slow mail server never holds up a request.
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
        $this->notifications->add($userId, $bookId, $subject, $message);
    }

    /**
     * Email queued notifications. Without a real MAIL_HOST they're marked skipped, so configuring mail
     * later doesn't flood readers with old notices. Returns how many were sent.
     */
    public function sendPendingEmails(int $limit = 50): int
    {
        $pending = $this->notifications->pendingEmails($limit);
        // .env.example's placeholder host counts as "not configured".
        if (in_array(Environment::get('MAIL_HOST', ''), ['', 'smtp.example.com'], true)) {
            foreach ($pending as $n) {
                $this->notifications->setEmailStatus($n['_id'], 'skipped', false);
            }
            return 0;
        }

        $users = new Users();
        $mailer = new EmailService();
        $sent = 0;
        foreach ($pending as $n) {
            $user = $users->getUserById((string) $n['user_id']);
            if (empty($user['email'])) {
                $this->notifications->setEmailStatus($n['_id'], 'skipped', false);
                continue;
            }
            $ok = $mailer->sendToUser(
                (string) $user['email'],
                (string) ($user['username'] ?? ''),
                (string) $n['subject'],
                (string) $n['message']
            );
            $attempts = (int) ($n['email_attempts'] ?? 0) + 1;
            $status = $ok ? 'sent' : ($attempts >= Notifications::MAX_EMAIL_ATTEMPTS ? 'failed' : 'pending');
            $this->notifications->setEmailStatus($n['_id'], $status, true);
            $sent += $ok ? 1 : 0;
        }
        return $sent;
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
