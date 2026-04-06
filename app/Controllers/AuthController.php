<?php

namespace App\Controllers;

use App\Core\DB;
use App\Services\SessionService;
use App\Services\RateLimiter;

/**
 * Authentication Controller
 * Handles user registration, login, logout, and user info
 */
class AuthController
{
    private SessionService $sessionService;
    private RateLimiter $rateLimiter;

    public function __construct()
    {
        $this->sessionService = new SessionService();
        $this->rateLimiter = new RateLimiter();
    }

    /**
     * POST /api/auth/register
     * Register a new user
     */
    public function register()
    {
        header('Content-Type: application/json');

        // Get request body
        $input = json_decode(file_get_contents('php://input'), true);

        // Rate limiting: 3 attempts per hour per IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!$this->rateLimiter->check("register:{$ip}", 3, 3600)) {
            http_response_code(429);
            echo json_encode([
                'error' => 'Too many registration attempts. Please try again later.',
                'retry_after' => 3600
            ]);
            return;
        }

        // Validate required fields
        $errors = $this->validateRegistration($input);
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'errors' => $errors]);
            return;
        }

        // Check IP blacklist
        $isBlacklisted = DB::getInstance()->first(
            "SELECT ip FROM ip_blacklist WHERE ip = ?",
            [$ip]
        );

        if ($isBlacklisted) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }

        $db = DB::getInstance();

        try {
            $db->getConnection()->beginTransaction();

            // Check username uniqueness
            $existingUser = $db->first(
                "SELECT id FROM users WHERE username = ?",
                [$input['username']]
            );

            if ($existingUser) {
                $db->getConnection()->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Username already taken']);
                return;
            }

            // Check email/phone uniqueness
            if (!empty($input['email'])) {
                $existingEmail = $db->first(
                    "SELECT id FROM users WHERE email = ?",
                    [$input['email']]
                );

                if ($existingEmail) {
                    $db->getConnection()->rollBack();
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Email already registered']);
                    return;
                }
            }

            if (!empty($input['phone'])) {
                $existingPhone = $db->first(
                    "SELECT id FROM users WHERE phone = ?",
                    [$input['phone']]
                );

                if ($existingPhone) {
                    $db->getConnection()->rollBack();
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Phone already registered']);
                    return;
                }
            }

            // Hash password
            $passwordHash = password_hash($input['password'], PASSWORD_BCRYPT, ['cost' => 12]);

            // Generate unique referral code
            $refCode = $this->generateUniqueRefCode($db);

            // Get referral code from URL if present
            $referrerId = null;
            if (!empty($input['ref_code'])) {
                $referrer = $db->first(
                    "SELECT id FROM users WHERE ref_code = ?",
                    [$input['ref_code']]
                );

                if ($referrer) {
                    // Block self-referral
                    if ($referrer['id'] === $existingUser['id'] ?? null) {
                        $db->getConnection()->rollBack();
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Cannot refer yourself']);
                        return;
                    }
                    $referrerId = $referrer['id'];
                }
            }

            // Insert user
            $db->query(
                "INSERT INTO users (username, email, phone, password_hash, ref_code, referred_by, last_ip) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $input['username'],
                    $input['email'] ?? null,
                    $input['phone'] ?? null,
                    $passwordHash,
                    $refCode,
                    $referrerId,
                    $ip
                ]
            );

            $userId = $db->getConnection()->lastInsertId();

            // Insert wallet with zero balance
            $db->query(
                "INSERT INTO wallets (user_id, real_balance, bonus_coins) VALUES (?, 0.00, 0.00)",
                [$userId]
            );

            // Insert referral record if referred
            if ($referrerId) {
                $db->query(
                    "INSERT INTO referrals (referrer_id, referred_id, status) VALUES (?, ?, 'pending')",
                    [$referrerId, $userId]
                );
            }

            $db->getConnection()->commit();

            // Create session
            $remember = !empty($input['remember_me']);
            $this->sessionService->create($userId, $remember);

            // Get user data for response
            $user = $db->first(
                "SELECT id, username, level, xp, ref_code FROM users WHERE id = ?",
                [$userId]
            );

            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'level' => $user['level'],
                    'xp' => $user['xp'],
                    'ref_code' => $user['ref_code']
                ]
            ]);

        } catch (\Exception $e) {
            $db->getConnection()->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Registration failed']);
        }
    }

    /**
     * POST /api/auth/login
     * Login user
     */
    public function login()
    {
        header('Content-Type: application/json');

        // Get request body
        $input = json_decode(file_get_contents('php://input'), true);

        // Rate limiting: 5 attempts per 15 min per IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!$this->rateLimiter->check("login:{$ip}", 5, 900)) {
            http_response_code(429);
            echo json_encode([
                'error' => 'Too many login attempts. Please try again later.',
                'retry_after' => 900
            ]);
            return;
        }

        // Validate required fields
        if (empty($input['identifier']) || empty($input['password'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Identifier and password required']);
            return;
        }

        $db = DB::getInstance();

        // Find user by email or phone
        $user = $db->first(
            "SELECT * FROM users WHERE email = ? OR phone = ?",
            [$input['identifier'], $input['identifier']]
        );

        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
            return;
        }

        // Check if banned
        if ($user['is_banned']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Account is banned']);
            return;
        }

        // Check account lockout (failed_attempts >= 5 in last 15 min)
        $lockoutTime = date('Y-m-d H:i:s', strtotime('-15 minutes'));
        $recentFailures = $db->first(
            "SELECT failed_attempts FROM users
             WHERE id = ? AND last_failed_at > ?",
            [$user['id'], $lockoutTime]
        );

        if ($recentFailures && $recentFailures['failed_attempts'] >= 5) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Account temporarily locked. Try again in 15 minutes.']);
            return;
        }

        // Verify password
        if (!password_verify($input['password'], $user['password_hash'])) {
            // Increment failed attempts
            $db->query(
                "UPDATE users 
                 SET failed_attempts = COALESCE(failed_attempts, 0) + 1, 
                     last_failed_at = NOW() 
                 WHERE id = ?",
                [$user['id']]
            );

            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
            return;
        }

        // Login successful - reset failed attempts
        $db->query(
            "UPDATE users 
             SET failed_attempts = 0, 
                 last_login = NOW(), 
                 last_ip = ?,
                 device_fp = ? 
             WHERE id = ?",
            [$ip, md5(($_SERVER['HTTP_USER_AGENT'] ?? '') . $ip), $user['id']]
        );

        // Create session
        $remember = !empty($input['remember_me']);
        $this->sessionService->create($user['id'], $remember);

        // Get wallet balance
        $wallet = $db->first(
            "SELECT real_balance, bonus_coins FROM wallets WHERE user_id = ?",
            [$user['id']]
        );

        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'level' => $user['level'],
                'xp' => $user['xp'],
                'real_balance' => $wallet['real_balance'],
                'bonus_coins' => $wallet['bonus_coins']
            ]
        ]);
    }

    /**
     * POST /api/auth/logout
     * Logout user
     */
    public function logout()
    {
        header('Content-Type: application/json');

        $token = $_COOKIE['session_token'] ?? null;

        if ($token) {
            $this->sessionService->destroy($token);
        }

        echo json_encode(['success' => true]);
    }

    /**
     * GET /api/auth/me
     * Get current user info (requires auth)
     */
    public function me()
    {
        header('Content-Type: application/json');

        $user = $GLOBALS['currentUser'] ?? null;

        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $db = DB::getInstance();

        // Get wallet balance
        $wallet = $db->first(
            "SELECT real_balance, bonus_coins FROM wallets WHERE user_id = ?",
            [$user['id']]
        );

        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'level' => $user['level'],
                'xp' => $user['xp'],
                'avatar_id' => $user['avatar_id'],
                'streak_count' => $user['streak_count'],
                'login_streak' => $user['login_streak'],
                'real_balance' => $wallet['real_balance'],
                'bonus_coins' => $wallet['bonus_coins'],
                'ref_code' => $user['ref_code'],
                'kyc_status' => $user['kyc_status']
            ]
        ]);
    }

    /**
     * GET /api/auth/check-username
     * Check if username is available
     */
    public function checkUsername()
    {
        header('Content-Type: application/json');

        $username = $_GET['username'] ?? '';

        if (empty($username)) {
            http_response_code(400);
            echo json_encode(['available' => false, 'error' => 'Username required']);
            return;
        }

        // Validate username format
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            echo json_encode(['available' => false, 'error' => 'Invalid username format']);
            return;
        }

        $db = DB::getInstance();
        $existing = $db->first(
            "SELECT id FROM users WHERE username = ?",
            [$username]
        );

        echo json_encode(['available' => !$existing]);
    }

    /**
     * Validate registration input
     */
    private function validateRegistration(array $input): array
    {
        $errors = [];

        // Username validation
        if (empty($input['username'])) {
            $errors['username'] = 'Username is required';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $input['username'])) {
            $errors['username'] = 'Username must be 3-20 characters, alphanumeric and underscore only';
        }

        // Email or phone validation (at least one required)
        if (empty($input['email']) && empty($input['phone'])) {
            $errors['email'] = 'Email or phone is required';
            $errors['phone'] = 'Email or phone is required';
        } else {
            if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email format';
            }
            if (!empty($input['phone']) && !preg_match('/^[0-9]{10,15}$/', $input['phone'])) {
                $errors['phone'] = 'Invalid phone format';
            }
        }

        // Password validation
        if (empty($input['password'])) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($input['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }

        if (empty($input['password_confirm'])) {
            $errors['password_confirm'] = 'Password confirmation is required';
        } elseif ($input['password'] !== $input['password_confirm']) {
            $errors['password_confirm'] = 'Passwords do not match';
        }

        // Age confirmation
        if (empty($input['age_confirm']) || $input['age_confirm'] != '1') {
            $errors['age_confirm'] = 'You must confirm you are 18+ years old';
        }

        return $errors;
    }

    /**
     * Generate unique referral code
     */
    private function generateUniqueRefCode(DB $db): string
    {
        do {
            $refCode = strtolower(substr(md5(uniqid()), 0, 6));
            $existing = $db->first(
                "SELECT id FROM users WHERE ref_code = ?",
                [$refCode]
            );
        } while ($existing);

        return $refCode;
    }
}
