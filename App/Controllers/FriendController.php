<?php

namespace App\Controllers;

use App\Includes\AuthenticatedUser;
use App\Includes\ResponseHandler;
use App\Services\FriendService;
use InvalidArgumentException;

/**
 * The signed-in user's friend requests and friends. JwtAuthMiddleware guards every route here.
 */
class FriendController
{
    private FriendService $friendService;

    public function __construct()
    {
        $this->friendService = new FriendService();
    }

    /**
     * GET /api/v1/friends — friends plus pending requests both ways.
     */
    public function list(): void
    {
        ResponseHandler::respond(true, $this->friendService->list($this->requireUser()));
    }

    /**
     * POST /api/v1/friends {email} — send a request (or accept theirs, if they already asked).
     */
    public function request(): void
    {
        $userId = $this->requireUser();
        $input = json_decode((string) file_get_contents('php://input'), true);
        $email = is_array($input) ? (string) ($input['email'] ?? '') : '';
        try {
            $outcome = $this->friendService->request($userId, $email);
        } catch (InvalidArgumentException) {
            ResponseHandler::respond(false, 'Enter a valid email address', 422);
            return;
        }
        match ($outcome) {
            'not_found' => ResponseHandler::respond(false, 'No reader with that email', 404),
            'self' => ResponseHandler::respond(false, 'You can\'t add yourself', 422),
            'exists' => ResponseHandler::respond(false, 'You already sent a request or are friends', 409),
            'requested' => ResponseHandler::respond(true, 'Friend request sent', 201),
            'accepted' => ResponseHandler::respond(true, 'You are now friends'),
        };
    }

    /**
     * POST /api/v1/friends/{userId}/accept
     */
    public function accept(string $otherId): void
    {
        if (!$this->friendService->accept($this->requireUser(), $otherId)) {
            ResponseHandler::respond(false, 'No pending request from that reader', 404);
            return;
        }
        ResponseHandler::respond(true, 'Friend request accepted');
    }

    /**
     * DELETE /api/v1/friends/{userId} — decline, cancel or unfriend.
     */
    public function remove(string $otherId): void
    {
        if (!$this->friendService->remove($this->requireUser(), $otherId)) {
            ResponseHandler::respond(false, 'Not found', 404);
            return;
        }
        ResponseHandler::respond(true, 'Removed');
    }

    /**
     * GET /api/v1/friends/{userId}/library — their saved books and reviews.
     */
    public function library(string $friendId): void
    {
        $library = $this->friendService->library($this->requireUser(), $friendId);
        if ($library === null) {
            ResponseHandler::respond(false, 'You can only see the library of a friend', 403);
            return;
        }
        ResponseHandler::respond(true, $library);
    }

    private function requireUser(): string
    {
        $userId = AuthenticatedUser::id();
        if ($userId === null) {
            ResponseHandler::respond(false, 'Authentication required', 401);
            exit();
        }
        return $userId;
    }
}
