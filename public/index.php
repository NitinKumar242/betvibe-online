<?php
/**
 * BetVibe - Entry Point
 * Main application entry point with routing
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Import the App class
use App\Core\App;

// Bootstrap the application
$app = App::getInstance();

// Define routes

// Health check endpoint
$app->addRoute('GET', '/api/health', function () {
    return [
        'status' => 'ok',
        'timestamp' => time(),
        'environment' => App::env('APP_ENV', 'unknown')
    ];
});

// ──────────────────── Frontend Pages ────────────────────

// Dashboard / Home (game lobby)
$app->addRoute('GET', '/', function () {
    ob_start();
    include __DIR__ . '/dashboard.php';
    return ob_get_clean();
});

$app->addRoute('GET', '', function () {
    ob_start();
    include __DIR__ . '/dashboard.php';
    return ob_get_clean();
});

// Login page
$app->addRoute('GET', '/login', function () {
    ob_start();
    include __DIR__ . '/login.php';
    return ob_get_clean();
});

// Register page
$app->addRoute('GET', '/register', function () {
    ob_start();
    include __DIR__ . '/register.php';
    return ob_get_clean();
});

// Profile page
$app->addRoute('GET', '/profile', function () {
    ob_start();
    include __DIR__ . '/profile.php';
    return ob_get_clean();
});

// Wallet page  
$app->addRoute('GET', '/wallet', function () {
    ob_start();
    include __DIR__ . '/wallet.php';
    return ob_get_clean();
});

// Game pages — serve static HTML game files
$app->addRoute('GET', '/games/{slug}', function () {
    $slug = $_GET['slug'] ?? '';
    // Sanitize slug: only allow alphanumeric + hyphens
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));
    $file = __DIR__ . '/games/' . $slug . '.html';
    if ($slug && file_exists($file)) {
        ob_start();
        include $file;
        return ob_get_clean();
    }
    http_response_code(404);
    return ['error' => 'Game not found'];
});

// Public API: list all enabled games
$app->addRoute('GET', '/api/games', function () {
    $db = \App\Core\DB::getInstance();
    $games = $db->query(
        "SELECT game_slug, display_name, is_enabled, min_bet, max_bet, round_duration 
         FROM game_config WHERE is_enabled = 1 ORDER BY game_slug"
    )->fetchAll(\PDO::FETCH_ASSOC);
    return ['success' => true, 'data' => $games];
});

// Auth API routes
$app->addRoute('POST', '/api/auth/register', function () {
    $controller = new \App\Controllers\AuthController();
    return $controller->register();
});

$app->addRoute('POST', '/api/auth/login', function () {
    $controller = new \App\Controllers\AuthController();
    return $controller->login();
});

$app->addRoute('POST', '/api/auth/logout', function () {
    $controller = new \App\Controllers\AuthController();
    return $controller->logout();
});

$app->addRoute('GET', '/api/auth/me', function () {
    $controller = new \App\Controllers\AuthController();
    return $controller->me();
});

$app->addRoute('GET', '/api/auth/check-username', function () {
    $controller = new \App\Controllers\AuthController();
    return $controller->checkUsername();
});

// Wallet sub-routes (main /wallet route defined above in Frontend Pages)

$app->addRoute('GET', '/wallet/deposit', function () {
    $controller = new \App\Controllers\WalletController();
    return $controller->deposit();
});

$app->addRoute('POST', '/wallet/deposit', function () {
    $controller = new \App\Controllers\WalletController();
    return $controller->deposit();
});

$app->addRoute('GET', '/wallet/withdraw', function () {
    $controller = new \App\Controllers\WalletController();
    return $controller->withdraw();
});

$app->addRoute('POST', '/wallet/withdraw', function () {
    $controller = new \App\Controllers\WalletController();
    return $controller->withdraw();
});

$app->addRoute('GET', '/wallet/history', function () {
    $controller = new \App\Controllers\WalletController();
    return $controller->history();
});

// Wallet API routes
$app->addRoute('POST', '/api/wallet/deposit', function () {
    $controller = new \App\Controllers\WalletController();
    return $controller->createDeposit();
});

$app->addRoute('POST', '/api/webhooks/watchpay', function () {
    $controller = new \App\Controllers\WalletController();
    return $controller->handleWatchPayWebhook();
});

$app->addRoute('GET', '/api/wallet/balance', function () {
    $controller = new \App\Controllers\WalletController();
    return $controller->getBalance();
});

$app->addRoute('GET', '/api/wallet/transactions', function () {
    $controller = new \App\Controllers\WalletController();
    return $controller->getTransactions();
});

$app->addRoute('POST', '/api/wallet/withdraw', function () {
    $controller = new \App\Controllers\WalletController();
    return $controller->createWithdrawal();
});

// Admin routes
$app->addRoute('GET', '/admin/withdrawals', function () {
    $controller = new \App\Controllers\AdminController();
    return $controller->listWithdrawals();
});

$app->addRoute('POST', '/admin/withdrawals/{id}/approve', function () {
    $controller = new \App\Controllers\AdminController();
    $id = $_GET['id'] ?? null;
    return $controller->approveWithdrawal($id);
});

$app->addRoute('POST', '/admin/withdrawals/{id}/reject', function () {
    $controller = new \App\Controllers\AdminController();
    $id = $_GET['id'] ?? null;
    return $controller->rejectWithdrawal($id);
});

$app->addRoute('GET', '/admin/withdrawals/{id}', function () {
    $controller = new \App\Controllers\AdminController();
    $id = $_GET['id'] ?? null;
    return $controller->getWithdrawal($id);
});

$app->addRoute('GET', '/admin/dashboard', function () {
    $controller = new \App\Controllers\AdminController();
    return $controller->getDashboard();
});


// Game-specific API routes
$app->addRoute('POST', '/api/games/{slug}/play', function () {
    $controller = new \App\Controllers\GameController();
    $slug = $_GET['slug'] ?? null;
    return $controller->apiPlay($slug);
});

$app->addRoute('POST', '/api/games/{slug}/start', function () {
    $controller = new \App\Controllers\GameController();
    $slug = $_GET['slug'] ?? null;
    return $controller->apiStart($slug);
});

$app->addRoute('POST', '/api/games/{slug}/action', function () {
    $controller = new \App\Controllers\GameController();
    $slug = $_GET['slug'] ?? null;
    return $controller->apiAction($slug);
});

$app->addRoute('POST', '/api/games/{slug}/cashout', function () {
    $controller = new \App\Controllers\GameController();
    $slug = $_GET['slug'] ?? null;
    return $controller->apiCashout($slug);
});

$app->addRoute('GET', '/api/games/{slug}/history', function () {
    $controller = new \App\Controllers\GameController();
    $slug = $_GET['slug'] ?? null;
    return $controller->apiHistory($slug);
});

$app->addRoute('POST', '/api/games/{slug}/bet', function () {
    $controller = new \App\Controllers\GameController();
    $slug = $_GET['slug'] ?? null;
    return $controller->apiBet($slug);
});

$app->addRoute('GET', '/api/games/{slug}/current-round', function () {
    $controller = new \App\Controllers\GameController();
    $slug = $_GET['slug'] ?? null;
    return $controller->apiCurrentRound($slug);
});

// ──────────────────── Phase 7: Referral Routes ────────────────────

$app->addRoute('GET', '/r/{code}', function () {
    $controller = new \App\Controllers\ReferralController();
    $controller->handleRefLink();
});

$app->addRoute('GET', '/referral', function () {
    $controller = new \App\Controllers\ReferralController();
    return $controller->index();
});

$app->addRoute('GET', '/api/referral/dashboard', function () {
    $controller = new \App\Controllers\ReferralController();
    return $controller->getDashboard();
});

$app->addRoute('GET', '/api/referral/share-link', function () {
    $controller = new \App\Controllers\ReferralController();
    return $controller->getShareLink();
});

// ──────────────────── Phase 8: Gen Z Features ────────────────────

// Win Card
$app->addRoute('GET', '/api/win-card/{betId}', function () {
    $controller = new \App\Controllers\WinCardController();
    $betId = $_GET['betId'] ?? null;
    return $controller->getWinCard($betId);
});

// Daily Quests
$app->addRoute('GET', '/quests', function () {
    ob_start();
    include __DIR__ . '/quests.php';
    return ob_get_clean();
});

$app->addRoute('GET', '/api/quests/today', function () {
    $auth = new \App\Middleware\AuthMiddleware();
    $user = $auth->require();
    $questService = new \App\Services\QuestService();
    return ['success' => true, 'data' => $questService->getTodayQuests((int)$user['id'])];
});

// Daily Login Reward
$app->addRoute('GET', '/api/daily-reward/status', function () {
    $auth = new \App\Middleware\AuthMiddleware();
    $user = $auth->require();
    $service = new \App\Services\LoginRewardService();
    return ['success' => true, 'data' => $service->getStatus((int)$user['id'])];
});

$app->addRoute('POST', '/api/daily-reward/claim', function () {
    $auth = new \App\Middleware\AuthMiddleware();
    $user = $auth->require();
    $service = new \App\Services\LoginRewardService();
    return $service->claim((int)$user['id']);
});

// Leaderboard
$app->addRoute('GET', '/leaderboard', function () {
    ob_start();
    include __DIR__ . '/leaderboard.php';
    return ob_get_clean();
});

$app->addRoute('GET', '/api/leaderboard', function () {
    $period = $_GET['period'] ?? 'daily';
    $service = new \App\Services\LeaderboardService();
    return ['success' => true, 'data' => $service->getLeaderboard($period)];
});

$app->addRoute('GET', '/api/leaderboard/my-rank', function () {
    $auth = new \App\Middleware\AuthMiddleware();
    $user = $auth->require();
    $period = $_GET['period'] ?? 'daily';
    $service = new \App\Services\LeaderboardService();
    return ['success' => true, 'data' => $service->getMyRank((int)$user['id'], $period)];
});

// ──────────────────── Phase 9: Admin Panel ────────────────────

$app->addRoute('GET', '/admin/login', function () {
    ob_start();
    include __DIR__ . '/admin/login.php';
    return ob_get_clean();
});

$app->addRoute('POST', '/api/admin/login', function () {
    $controller = new \App\Controllers\AdminController();
    return $controller->login();
});

$app->addRoute('POST', '/api/admin/logout', function () {
    $controller = new \App\Controllers\AdminController();
    return $controller->adminLogout();
});

$app->addRoute('GET', '/admin/users', function () {
    ob_start();
    include __DIR__ . '/admin/users.php';
    return ob_get_clean();
});

$app->addRoute('GET', '/api/admin/users', function () {
    $controller = new \App\Controllers\AdminController();
    return $controller->listUsers();
});

$app->addRoute('GET', '/api/admin/users/{id}', function () {
    $controller = new \App\Controllers\AdminController();
    $id = $_GET['id'] ?? null;
    return $controller->getUserDetail($id);
});

$app->addRoute('POST', '/admin/users/{id}/ban', function () {
    $controller = new \App\Controllers\AdminController();
    $id = $_GET['id'] ?? null;
    return $controller->banUser($id);
});

$app->addRoute('POST', '/admin/users/{id}/unban', function () {
    $controller = new \App\Controllers\AdminController();
    $id = $_GET['id'] ?? null;
    return $controller->unbanUser($id);
});

$app->addRoute('POST', '/admin/users/{id}/adjust-balance', function () {
    $controller = new \App\Controllers\AdminController();
    $id = $_GET['id'] ?? null;
    return $controller->adjustBalance($id);
});

$app->addRoute('POST', '/admin/users/{id}/reset-password', function () {
    $controller = new \App\Controllers\AdminController();
    $id = $_GET['id'] ?? null;
    return $controller->resetUserPassword($id);
});

$app->addRoute('GET', '/admin/games', function () {
    ob_start();
    include __DIR__ . '/admin/games.php';
    return ob_get_clean();
});

$app->addRoute('GET', '/api/admin/games', function () {
    $controller = new \App\Controllers\AdminController();
    return $controller->listGames();
});

$app->addRoute('POST', '/admin/games/{slug}/config', function () {
    $controller = new \App\Controllers\AdminController();
    $slug = $_GET['slug'] ?? null;
    return $controller->updateGameConfig($slug);
});

$app->addRoute('GET', '/admin/fraud', function () {
    ob_start();
    include __DIR__ . '/admin/fraud.php';
    return ob_get_clean();
});

$app->addRoute('GET', '/api/admin/fraud', function () {
    $controller = new \App\Controllers\AdminController();
    return $controller->listFraudUsers();
});

$app->addRoute('GET', '/admin/audit', function () {
    ob_start();
    include __DIR__ . '/admin/audit.php';
    return ob_get_clean();
});

$app->addRoute('GET', '/api/admin/audit', function () {
    $controller = new \App\Controllers\AdminController();
    return $controller->getAuditLog();
});

$app->addRoute('GET', '/admin/finance', function () {
    ob_start();
    include __DIR__ . '/admin/finance.php';
    return ob_get_clean();
});

$app->addRoute('GET', '/api/admin/finance', function () {
    $controller = new \App\Controllers\AdminController();
    return $controller->getFinanceData();
});

$app->addRoute('GET', '/api/admin/dashboard-data', function () {
    $controller = new \App\Controllers\AdminController();
    return $controller->getDashboardData();
});

// ──────────────────── Phase 10: Telegram + Push ────────────────────

$app->addRoute('POST', '/telegram/webhook', function () {
    require_once __DIR__ . '/../telegram/webhook.php';
});

$app->addRoute('POST', '/api/push/subscribe', function () {
    $auth = new \App\Middleware\AuthMiddleware();
    $user = $auth->require();
    $input = json_decode(file_get_contents('php://input'), true);
    $db = \App\Core\DB::getInstance();
    $db->insert('push_subscriptions', [
        'user_id' => (int)$user['id'],
        'endpoint' => $input['endpoint'] ?? '',
        'p256dh' => $input['keys']['p256dh'] ?? '',
        'auth' => $input['keys']['auth'] ?? '',
    ]);
    return ['success' => true, 'message' => 'Subscription saved'];
});

// Run the application
$app->run();
