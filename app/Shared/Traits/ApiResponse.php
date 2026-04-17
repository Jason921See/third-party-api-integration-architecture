<?php

namespace App\Shared\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * 200 OK — Successful retrieval or generic success
     */
    protected function ok(mixed $data = null, string $message = 'Success'): JsonResponse
    {
        return $this->success($data, $message, 200);
    }

    /**
     * 201 Created — Resource created successfully
     */
    protected function created(mixed $data = null, string $message = 'Created successfully'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    /**
     * 204 No Content — Success with no body (e.g. delete)
     */
    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * 400 Bad Request — Validation or logic error
     */
    protected function badRequest(string $message = 'Bad request', mixed $errors = null): JsonResponse
    {
        return $this->error($message, 400, $errors);
    }

    /**
     * 401 Unauthorized — Not authenticated
     */
    protected function unauthorized(string $message = 'Unauthenticated'): JsonResponse
    {
        return $this->error($message, 401);
    }

    /**
     * 403 Forbidden — Authenticated but not allowed
     */
    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, 403);
    }

    /**
     * 404 Not Found
     */
    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, 404);
    }

    /**
     * 409 Conflict — Duplicate or state conflict
     */
    protected function conflict(string $message = 'Conflict'): JsonResponse
    {
        return $this->error($message, 409);
    }

    /**
     * 422 Unprocessable Entity — Failed validation
     */
    protected function unprocessable(mixed $errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->error($message, 422, $errors);
    }

    /**
     * 500 Internal Server Error
     */
    protected function serverError(string $message = 'Internal server error'): JsonResponse
    {
        return $this->error($message, 500);
    }

    // ─── Internals ────────────────────────────────────────────────────────────

    private function success(mixed $data, string $message, int $status): JsonResponse
    {
        $body = [
            'success' => true,
            'message' => $message,
        ];

        if (! is_null($data)) {
            $body['data'] = $data;
        }

        return response()->json($body, $status);
    }

    private function error(string $message, int $status, mixed $errors = null): JsonResponse
    {
        $body = [
            'success' => false,
            'message' => $message,
        ];

        if (! is_null($errors)) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status);
    }
}
