<?php

namespace App\Middleware;

use App\Services\SessionService;

/**
 * Authentication Middleware
 * Verifies user authentication using session tokens
 */
class AuthMiddleware
{
    private SessionService $sessionService;

    public function __construct()
    {
        $this->sessionService = new SessionService();
    }

    /**
     * Handle authentication
     * Returns user object if authenticated, null otherwise
     *
     * @return array|null User data or null if not authenticated
     */
    public function handle(): ?array
    {
        // Validate session
        $user = $this->sessionService->validate();

        if (!$user) {
            // Not authenticated - return null
            return null;
        }

        // Set global current user for use in controllers
        $GLOBALS['currentUser'] = $user;

        return $user;
    }

    /**
     * Require authentication - returns 401 if not authenticated
     *
     * @return array|null User data or exits with 401
     */
    public function require(): ?array
    {
        $user = $this->handle();

        if (!$user) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        return $user;
    }

    /**
     * Get user ID from session
     *
     * @return int|null User ID or null if not authenticated
     */
    public function getUserId(): ?int
    {
        $user = $this->handle();
        return $user ? (int) $user['id'] : null;
    }
}
