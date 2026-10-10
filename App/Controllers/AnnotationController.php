<?php

namespace App\Controllers;

use App\Includes\AuthenticatedUser;
use App\Includes\ResponseHandler;
use App\Services\AnnotationService;
use InvalidArgumentException;

/**
 * The signed-in user's own highlights and notes. JwtAuthMiddleware guards these routes;
 * the id check here is the second line, and every service call is scoped to that id.
 */
class AnnotationController
{
    private AnnotationService $annotationService;

    public function __construct()
    {
        $this->annotationService = new AnnotationService();
    }

    public function listForBook(string $bookId): void
    {
        $userId = $this->requireUser();
        ResponseHandler::respond(true, $this->annotationService->listForBook($userId, $bookId));
    }

    public function create(string $bookId): void
    {
        $userId = $this->requireUser();
        try {
            $annotation = $this->annotationService->create($userId, $bookId, $this->jsonBody());
            ResponseHandler::respond(true, $annotation, 201);
        } catch (InvalidArgumentException $e) {
            ResponseHandler::respond(false, json_decode($e->getMessage(), true) ?? $e->getMessage(), 422);
        }
    }

    public function update(string $id): void
    {
        $userId = $this->requireUser();
        try {
            $annotation = $this->annotationService->update($userId, $id, $this->jsonBody());
        } catch (InvalidArgumentException $e) {
            ResponseHandler::respond(false, json_decode($e->getMessage(), true) ?? $e->getMessage(), 422);
            return;
        }
        if ($annotation === null) {
            ResponseHandler::respond(false, 'Annotation not found', 404);
            return;
        }
        ResponseHandler::respond(true, $annotation);
    }

    public function delete(string $id): void
    {
        $userId = $this->requireUser();
        if (!$this->annotationService->delete($userId, $id)) {
            ResponseHandler::respond(false, 'Annotation not found', 404);
            return;
        }
        ResponseHandler::respond(true, 'Annotation deleted');
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

    /**
     * @return array<string, mixed>
     */
    private function jsonBody(): array
    {
        $input = json_decode((string) file_get_contents('php://input'), true);
        return is_array($input) ? $input : [];
    }
}
