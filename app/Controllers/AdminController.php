<?php

namespace App\Controllers;

use App\Services\WalletService;
use App\Services\WatchPayService;
use App\Services\AuditLog;

/**
 * Admin Controller
 * Full admin panel: auth, dashboard, users, games, finance, fraud, audit
 */
class AdminController
{
    private $db;
    private $walletService;
    private $watchPayService;

    public function __construct()
    {
        $this->db = \App\Core\DB::getInstance();
        $this->walletService = new WalletService($this->db);
        $this->watchPayService = new WatchPayService($this->db);
    }

    // ──────────────────── Auth ────────────────────

    /**
     * POST /api/admin/login
     */
    public function login()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';

        if (!$username || !$password) {
            return $this->json(['error' => 'Username and password required'], 400);
        }

        // IP whitelist check
        if (!$this->checkIPWhitelist()) {
            return $this->json(['error' => 'Access denied from this IP'], 403);
        }

        $admin = $this->db->first(
            "SELECT * FROM admins WHERE username = ?",
            [$username]
        );

        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            return $this->json(['error' => 'Invalid credentials'], 401);
        }

        // Create admin session
        $token = bin2hex(random_bytes(32));
        $this->db->query(
            "INSERT INTO admin_sessions (admin_id, token, ip, expires_at, created_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 8 HOUR), NOW())
             ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)",
            [$admin['id'], $token, $_SERVER['REMOTE_ADDR'] ?? '']
        );

        // Update last login
        $this->db->query("UPDATE admins SET last_login = NOW() WHERE id = ?", [$admin['id']]);

        // Set session
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_token'] = $token;
        $_SESSION['admin_username'] = $admin['username'];

        AuditLog::write($admin['id'], 'admin_login', null, null, 'Admin login');

        return $this->json(['success' => true, 'message' => 'Login successful']);
    }

    /**
     * POST /api/admin/logout
     */
    public function adminLogout()
    {
        $adminId = $_SESSION['admin_id'] ?? null;
        if ($adminId) {
            $this->db->query("DELETE FROM admin_sessions WHERE admin_id = ?", [$adminId]);
            AuditLog::write($adminId, 'admin_logout', null, null, 'Admin logout');
        }
        unset($_SESSION['admin_id'], $_SESSION['admin_token'], $_SESSION['admin_username']);
        return $this->json(['success' => true]);
    }

    // ──────────────────── Dashboard ────────────────────

    /**
     * GET /admin/dashboard — render page
     */
    public function getDashboard()
    {
        if (!$this->isAdmin()) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        // Return basic data for API
        return $this->getDashboardData();
    }

    /**
     * GET /api/admin/dashboard-data
     */
    public function getDashboardData()
    {
        if (!$this->isAdmin()) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $stats = $this->db->first(
            "SELECT
                (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='deposit' AND status='completed' AND DATE(created_at)=CURDATE()) as deposits_today,
                (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='withdraw' AND status='completed' AND DATE(created_at)=CURDATE()) as withdrawals_today,
                (SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()) as new_users_today,
                (SELECT COUNT(DISTINCT user_id) FROM bets WHERE DATE(created_at)=CURDATE()) as active_users_today,
                (SELECT COUNT(*) FROM bets WHERE DATE(created_at)=CURDATE()) as bets_today,
                (SELECT COALESCE(SUM(CASE WHEN result='loss' THEN bet_amount ELSE -payout END),0)
                 FROM bets WHERE DATE(created_at)=CURDATE() AND balance_type='real' AND result IN ('win','loss')) as house_profit_today"
        );

        // 30-day revenue data
        $revenue30 = $this->db->all(
            "SELECT DATE(created_at) as date,
                    COALESCE(SUM(CASE WHEN type='deposit' THEN amount ELSE 0 END), 0) as deposits,
                    COALESCE(SUM(CASE WHEN type='withdraw' THEN amount ELSE 0 END), 0) as withdrawals
             FROM transactions
             WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at)
             ORDER BY date ASC"
        );

        // Revenue by game
        $gameRevenue = $this->db->all(
            "SELECT gc.display_name as game, gc.game_slug,
                    COUNT(b.id) as total_bets,
                    COALESCE(SUM(b.bet_amount), 0) as total_wagered,
                    COALESCE(SUM(b.payout), 0) as total_payout,
                    COALESCE(SUM(b.bet_amount) - SUM(b.payout), 0) as house_profit
             FROM game_config gc
             LEFT JOIN bets b ON b.game_slug = gc.game_slug AND b.result IN ('win','loss')
             GROUP BY gc.game_slug
             ORDER BY house_profit DESC"
        );

        // Recent 10 bets
        $recentBets = $this->db->all(
            "SELECT b.*, u.username, gc.display_name as game_name
             FROM bets b
             JOIN users u ON u.id = b.user_id
             JOIN game_config gc ON gc.game_slug = b.game_slug
             ORDER BY b.created_at DESC
             LIMIT 10"
        );

        // Pending withdrawals
        $pending = $this->db->first(
            "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total
             FROM withdrawal_requests WHERE status = 'pending'"
        );

        return $this->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'deposits_today' => (float)$stats['deposits_today'],
                    'withdrawals_today' => (float)$stats['withdrawals_today'],
                    'new_users_today' => (int)$stats['new_users_today'],
                    'active_users_today' => (int)$stats['active_users_today'],
                    'bets_today' => (int)$stats['bets_today'],
                    'house_profit_today' => (float)$stats['house_profit_today'],
                    'pending_withdrawals' => (int)$pending['count'],
                    'pending_withdrawal_amount' => (float)$pending['total'],
                ],
                'revenue_30d' => $revenue30,
                'game_revenue' => $gameRevenue,
                'recent_bets' => $recentBets,
            ]
        ]);
    }

    // ──────────────────── User Management ────────────────────

    /**
     * GET /api/admin/users
     */
    public function listUsers()
    {
        if (!$this->isAdmin()) return $this->json(['error' => 'Unauthorized'], 401);

        $search = $_GET['search'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        $where = '1=1';
        $params = [];

        if ($search) {
            $where = "(u.username LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
            $searchLike = "%{$search}%";
            $params = [$searchLike, $searchLike, $searchLike];
        }

        $users = $this->db->all(
            "SELECT u.id, u.username, u.email, u.phone, u.is_banned, u.created_at,
                    w.real_balance, w.bonus_coins, w.total_wagered,
                    (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=u.id AND type='deposit' AND status='completed') as total_deposited,
                    (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=u.id AND type='withdraw' AND status='completed') as total_withdrawn
             FROM users u
             LEFT JOIN wallets w ON w.user_id = u.id
             WHERE {$where}
             ORDER BY u.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $total = $this->db->first(
            "SELECT COUNT(*) as c FROM users u WHERE {$where}",
            $params
        );

        return $this->json([
            'success' => true,
            'data' => $users,
            'pagination' => [
                'total' => (int)$total['c'],
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil((int)$total['c'] / $perPage),
            ]
        ]);
    }

    /**
     * GET /api/admin/users/{id}
     */
    public function getUserDetail($id)
    {
        if (!$this->isAdmin()) return $this->json(['error' => 'Unauthorized'], 401);

        $userId = (int)$id;
        $user = $this->db->first(
            "SELECT u.*, w.real_balance, w.bonus_coins, w.total_wagered
             FROM users u LEFT JOIN wallets w ON w.user_id = u.id
             WHERE u.id = ?",
            [$userId]
        );

        if (!$user) return $this->json(['error' => 'User not found'], 404);

        // Recent bets
        $bets = $this->db->all(
            "SELECT b.*, gc.display_name as game_name
             FROM bets b JOIN game_config gc ON gc.game_slug = b.game_slug
             WHERE b.user_id = ? ORDER BY b.created_at DESC LIMIT 50",
            [$userId]
        );

        // Recent transactions
        $transactions = $this->db->all(
            "SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 50",
            [$userId]
        );

        // Referrals made
        $referrals = $this->db->all(
            "SELECT r.*, u.username as referred_username
             FROM referrals r JOIN users u ON u.id = r.referred_id
             WHERE r.referrer_id = ? ORDER BY r.created_at DESC",
            [$userId]
        );

        return $this->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'bets' => $bets,
                'transactions' => $transactions,
                'referrals' => $referrals,
            ]
        ]);
    }

    /**
     * POST /admin/users/{id}/ban
     */
    public function banUser($id)
    {
        if (!$this->isAdmin()) return $this->json(['error' => 'Unauthorized'], 401);

        $userId = (int)$id;
        $input = json_decode(file_get_contents('php://input'), true);
        $reason = $input['reason'] ?? 'Banned by admin';

        $this->db->query(
            "UPDATE users SET is_banned = 1, ban_reason = ? WHERE id = ?",
            [$reason, $userId]
        );

        // Invalidate sessions
        $this->db->query("DELETE FROM sessions WHERE user_id = ?", [$userId]);

        $adminId = $_SESSION['admin_id'] ?? 0;
        AuditLog::write($adminId, 'ban_user', 'user', $userId, "Reason: {$reason}");

        return $this->json(['success' => true, 'message' => 'User banned']);
    }

    /**
     * POST /admin/users/{id}/unban
     */
    public function unbanUser($id)
    {
        if (!$this->isAdmin()) return $this->json(['error' => 'Unauthorized'], 401);

        $userId = (int)$id;
        $this->db->query(
            "UPDATE users SET is_banned = 0, ban_reason = NULL WHERE id = ?",
            [$userId]
        );

        $adminId = $_SESSION['admin_id'] ?? 0;
        AuditLog::write($adminId, 'unban_user', 'user', $userId, 'User unbanned');

        return $this->json(['success' => true, 'message' => 'User unbanned']);
    }

    /**
     * POST /admin/users/{id}/adjust-balance
     */
    public function adjustBalance($id)
    {
        if (!$this->isAdmin()) return $this->json(['error' => 'Unauthorized'], 401);

        $userId = (int)$id;
        $input = json_decode(file_get_contents('php://input'), true);
        $type = $input['type'] ?? 'real'; // real or bonus
        $amount = (float)($input['amount'] ?? 0);
        $reason = $input['reason'] ?? 'Admin adjustment';

        if ($amount == 0) return $this->json(['error' => 'Amount required'], 400);

        $column = $type === 'bonus' ? 'bonus_coins' : 'real_balance';

        $this->db->transaction(function ($db) use ($userId, $column, $amount, $type, $reason) {
            $db->query(
                "UPDATE wallets SET {$column} = {$column} + ? WHERE user_id = ?",
                [$amount, $userId]
            );

            $db->insert('transactions', [
                'user_id' => $userId,
                'type' => $amount > 0 ? 'bonus' : 'loss',
                'amount' => abs($amount),
                'balance_type' => $type,
                'status' => 'completed',
                'note' => "Admin adjustment: {$reason}",
            ]);
        });

        $adminId = $_SESSION['admin_id'] ?? 0;
        AuditLog::write($adminId, 'adjust_balance', 'user', $userId,
            "Type: {$type}, Amount: {$amount}, Reason: {$reason}");

        return $this->json(['success' => true, 'message' => 'Balance adjusted']);
    }

    /**
     * POST /admin/users/{id}/reset-password
     */
    public function resetUserPassword($id)
    {
        if (!$this->isAdmin()) return $this->json(['error' => 'Unauthorized'], 401);

        $userId = (int)$id;
        $tempPassword = bin2hex(random_bytes(4)); // 8-char hex
        $hash = password_hash($tempPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        $this->db->query("UPDATE users SET password_hash = ? WHERE id = ?", [$hash, $userId]);

        $adminId = $_SESSION['admin_id'] ?? 0;
        AuditLog::write($adminId, 'reset_password', 'user', $userId, 'Password reset by admin');

        // Try to send via Telegram
        $ticket = $this->db->first(
            "SELECT telegram_id FROM telegram_support_tickets WHERE user_id = ? ORDER BY created_at DESC LIMIT 1",
            [$userId]
        );

        if ($ticket && $ticket['telegram_id']) {
            $this->sendTelegramNotification(
                $ticket['telegram_id'],
                "🔑 Your temporary password: *{$tempPassword}*\n\nLogin at betsvibe.online and change it immediately."
            );
        }

        return $this->json([
            'success' => true,
            'temp_password' => $tempPassword,
            'message' => 'Password reset. Temp password: ' . $tempPassword
        ]);
    }

    // ──────────────────── Game Control ────────────────────

    /**
     * GET /api/admin/games
     */
    public function listGames()
    {
        if (!$this->isAdmin()) return $this->json(['error' => 'Unauthorized'], 401);

        $games = $this->db->all("SELECT * FROM game_config ORDER BY display_name ASC");

        return $this->json(['success' => true, 'data' => $games]);
    }

    /**
     * POST /admin/games/{slug}/config
     */
    public function updateGameConfig($slug)
    {
        if (!$this->isAdmin()) return $this->json(['error' => 'Unauthorized'], 401);

        $input = json_decode(file_get_contents('php://input'), true);

        $winRatio = isset($input['win_ratio']) ? (float)$input['win_ratio'] : null;
        $minBet = isset($input['min_bet']) ? (float)$input['min_bet'] : null;
        $maxBet = isset($input['max_bet']) ? (float)$input['max_bet'] : null;
        $isEnabled = isset($input['is_enabled']) ? (int)$input['is_enabled'] : null;

        $updates = [];
        $params = [];

        if ($winRatio !== null) { $updates[] = 'win_ratio = ?'; $params[] = $winRatio; }
        if ($minBet !== null) { $updates[] = 'min_bet = ?'; $params[] = $minBet; }
        if ($maxBet !== null) { $updates[] = 'max_bet = ?'; $params[] = $maxBet; }
        if ($isEnabled !== null) { $updates[] = 'is_enabled = ?'; $params[] = $isEnabled; }

        if (empty($updates)) return $this->json(['error' => 'No changes'], 400);

        $params[] = $slug;
        $this->db->query(
            "UPDATE game_config SET " . implode(', ', $updates) . " WHERE game_slug = ?",
            $params
        );

        $adminId = $_SESSION['admin_id'] ?? 0;
        AuditLog::write($adminId, 'update_game_config', 'game', null,
            "Game: {$slug}, Changes: " . json_encode($input));

        return $this->json(['success' => true, 'message' => 'Game config updated']);
    }

    // ──────────────────── Fraud ────────────────────

    /**
     * GET /api/admin/fraud
     */
    public function listFraudUsers()
    {
        if (!$this->isAdmin()) return $this->json(['error' => 'Unauthorized'], 401);

        $users = $this->db->all(
            "SELECT u.*, w.real_balance, w.total_wagered
             FROM users u LEFT JOIN wallets w ON w.user_id = u.id
             WHERE u.fraud_flag = 1
             ORDER BY u.created_at DESC"
        );

        return $this->json(['success' => true, 'data' => $users]);
    }

    // ──────────────────── Audit Log ────────────────────

    /**
     * GET /api/admin/audit
     */
    public function getAuditLog()
    {
        if (!$this->isAdmin()) return $this->json(['error' => 'Unauthorized'], 401);

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;
        $action = $_GET['action'] ?? '';

        $where = '1=1';
        $params = [];
        if ($action) {
            $where .= ' AND aal.action = ?';
            $params[] = $action;
        }

        $logs = $this->db->all(
            "SELECT aal.*, a.username as admin_username
             FROM admin_audit_log aal
             LEFT JOIN admins a ON a.id = aal.admin_id
             WHERE {$where}
             ORDER BY aal.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $total = $this->db->first(
            "SELECT COUNT(*) as c FROM admin_audit_log aal WHERE {$where}",
            $params
        );

        return $this->json([
            'success' => true,
            'data' => $logs,
            'pagination' => [
                'total' => (int)$total['c'],
                'page' => $page,
                'per_page' => $perPage,
            ]
        ]);
    }

    // ──────────────────── Finance ────────────────────

    /**
     * GET /api/admin/finance
     */
    public function getFinanceData()
    {
        if (!$this->isAdmin()) return $this->json(['error' => 'Unauthorized'], 401);

        // Revenue chart 30/60/90 days
        $days = (int)($_GET['days'] ?? 30);
        $revenue = $this->db->all(
            "SELECT DATE(created_at) as date,
                    SUM(CASE WHEN type='deposit' THEN amount ELSE 0 END) as deposits,
                    SUM(CASE WHEN type='withdraw' THEN amount ELSE 0 END) as withdrawals
             FROM transactions WHERE status='completed' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(created_at) ORDER BY date ASC",
            [$days]
        );

        // Game breakdown
        $gameBreakdown = $this->db->all(
            "SELECT gc.display_name, gc.game_slug,
                    COUNT(b.id) as total_bets,
                    COALESCE(SUM(b.bet_amount),0) as total_wagered,
                    COALESCE(SUM(b.payout),0) as total_payout,
                    COALESCE(SUM(b.bet_amount) - SUM(b.payout), 0) as house_profit
             FROM game_config gc LEFT JOIN bets b ON b.game_slug = gc.game_slug AND b.result IN ('win','loss')
             GROUP BY gc.game_slug ORDER BY house_profit DESC"
        );

        // Top depositors
        $topDepositors = $this->db->all(
            "SELECT u.username, u.id,
                    SUM(t.amount) as total_deposited,
                    COUNT(t.id) as deposit_count
             FROM transactions t JOIN users u ON u.id = t.user_id
             WHERE t.type='deposit' AND t.status='completed'
             GROUP BY t.user_id ORDER BY total_deposited DESC LIMIT 20"
        );

        // Withdrawal history
        $withdrawalHistory = $this->db->all(
            "SELECT wr.*, u.username
             FROM withdrawal_requests wr JOIN users u ON u.id = wr.user_id
             ORDER BY wr.requested_at DESC LIMIT 50"
        );

        return $this->json([
            'success' => true,
            'data' => [
                'revenue_chart' => $revenue,
                'game_breakdown' => $gameBreakdown,
                'top_depositors' => $topDepositors,
                'withdrawal_history' => $withdrawalHistory,
            ]
        ]);
    }

    // ──────────────────── Withdrawals (existing + enhanced) ────────────────────

    /**
     * GET /admin/withdrawals
     */
    public function listWithdrawals()
    {
        if (!$this->isAdmin()) return $this->json(['error' => 'Unauthorized'], 401);

        $status = $_GET['status'] ?? 'pending';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $withdrawals = $this->db->all(
            "SELECT wr.*, u.username, u.email, u.phone, u.kyc_status
             FROM withdrawal_requests wr JOIN users u ON wr.user_id = u.id
             WHERE wr.status = ?
             ORDER BY wr.amount DESC
             LIMIT ? OFFSET ?",
            [$status, $perPage, $offset]
        );

        $total = $this->db->first(
            "SELECT COUNT(*) as total FROM withdrawal_requests WHERE status = ?",
            [$status]
        );

        return $this->json([
            'success' => true,
            'data' => $withdrawals,
            'pagination' => [
                'total' => (int)$total['total'],
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil((int)$total['total'] / $perPage),
            ]
        ]);
    }

    /**
     * POST /admin/withdrawals/{id}/approve
     */
    public function approveWithdrawal($id)
    {
        if (!$this->isAdmin()) return $this->json(['error' => 'Unauthorized'], 401);

        $withdrawalId = (int)$id;
        $withdrawal = $this->db->first(
            "SELECT wr.*, u.username, u.telegram_chat_id
             FROM withdrawal_requests wr JOIN users u ON wr.user_id = u.id
             WHERE wr.id = ? AND wr.status = 'pending'",
            [$withdrawalId]
        );

        if (!$withdrawal) return $this->json(['error' => 'Not found or already processed'], 404);

        try {
            $result = $this->watchPayService->processPayout($withdrawalId);

            $adminId = $_SESSION['admin_id'] ?? 0;
            AuditLog::write($adminId, 'approve_withdrawal', 'withdrawal', $withdrawalId,
                "Amount: NPR {$withdrawal['amount']}, User: {$withdrawal['username']}");

            // Telegram notification to user
            if (!empty($withdrawal['telegram_chat_id'])) {
                $this->sendTelegramNotification(
                    $withdrawal['telegram_chat_id'],
                    "✅ Withdrawal Approved!\n\nAmount: NPR {$withdrawal['amount']}\nStatus: Processing"
                );
            }

            return $this->json(['success' => true, 'message' => 'Withdrawal approved']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /admin/withdrawals/{id}/reject
     */
    public function rejectWithdrawal($id)
    {
        if (!$this->isAdmin()) return $this->json(['error' => 'Unauthorized'], 401);

        $withdrawalId = (int)$id;
        $input = json_decode(file_get_contents('php://input'), true);
        $reason = $input['reason'] ?? $input['admin_note'] ?? 'Rejected by admin';

        $withdrawal = $this->db->first(
            "SELECT wr.*, u.username, u.telegram_chat_id
             FROM withdrawal_requests wr JOIN users u ON wr.user_id = u.id
             WHERE wr.id = ? AND wr.status = 'pending'",
            [$withdrawalId]
        );

        if (!$withdrawal) return $this->json(['error' => 'Not found or already processed'], 404);

        $this->db->transaction(function ($db) use ($withdrawal, $withdrawalId, $reason) {
            // Restore balance
            $db->query(
                "UPDATE wallets SET real_balance = real_balance + ? WHERE user_id = ?",
                [$withdrawal['amount'], $withdrawal['user_id']]
            );

            // Update status
            $db->query(
                "UPDATE withdrawal_requests SET status = 'rejected', admin_note = ?, reviewed_at = NOW() WHERE id = ?",
                [$reason, $withdrawalId]
            );

            // Update transaction
            $db->query(
                "UPDATE transactions SET status = 'rejected', note = ?
                 WHERE user_id = ? AND type = 'withdraw' AND reference_id = ?",
                [$reason, $withdrawal['user_id'], "withdrawal_{$withdrawalId}"]
            );
        });

        $adminId = $_SESSION['admin_id'] ?? 0;
        AuditLog::write($adminId, 'reject_withdrawal', 'withdrawal', $withdrawalId,
            "Amount: NPR {$withdrawal['amount']}, Reason: {$reason}");

        // Telegram notification
        if (!empty($withdrawal['telegram_chat_id'])) {
            $this->sendTelegramNotification(
                $withdrawal['telegram_chat_id'],
                "❌ Withdrawal Rejected\n\nAmount: NPR {$withdrawal['amount']}\nReason: {$reason}\nBalance has been restored."
            );
        }

        return $this->json(['success' => true, 'message' => 'Withdrawal rejected and balance restored']);
    }

    /**
     * GET /admin/withdrawals/{id}
     */
    public function getWithdrawal($id)
    {
        if (!$this->isAdmin()) return $this->json(['error' => 'Unauthorized'], 401);

        $withdrawal = $this->db->first(
            "SELECT wr.*, u.username, u.email, u.phone, u.kyc_status, u.created_at as user_created_at,
                    (SELECT COUNT(*) FROM withdrawal_requests WHERE user_id = u.id) as total_withdrawals,
                    (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = u.id AND type = 'deposit' AND status = 'completed') as total_deposits
             FROM withdrawal_requests wr JOIN users u ON wr.user_id = u.id
             WHERE wr.id = ?",
            [(int)$id]
        );

        if (!$withdrawal) return $this->json(['error' => 'Not found'], 404);

        return $this->json(['success' => true, 'data' => $withdrawal]);
    }

    // ──────────────────── Helpers ────────────────────

    private function isAdmin(): bool
    {
        // Check admin session first
        if (isset($_SESSION['admin_id'])) {
            $session = $this->db->first(
                "SELECT * FROM admin_sessions WHERE admin_id = ? AND expires_at > NOW()",
                [$_SESSION['admin_id']]
            );
            if ($session) return true;
        }

        // Fallback: regular user admin check
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) return false;

        $adminUserId = $_ENV['ADMIN_USER_ID'] ?? null;
        if ($adminUserId && $userId == $adminUserId) return true;

        $user = $this->db->first("SELECT is_admin FROM users WHERE id = ?", [$userId]);
        return $user && ($user['is_admin'] ?? 0) == 1;
    }

    private function checkIPWhitelist(): bool
    {
        $whitelist = $_ENV['ADMIN_IP_WHITELIST'] ?? '';
        if (!$whitelist) return true; // No whitelist = allow all

        $allowedIPs = array_map('trim', explode(',', $whitelist));
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

        return in_array($clientIP, $allowedIPs) || in_array('0.0.0.0', $allowedIPs);
    }

    private function sendTelegramNotification(?string $chatId, string $message): void
    {
        if (!$chatId) return;

        $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
        if (!$botToken) return;

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    private function json($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
