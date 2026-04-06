<?php
/**
 * BetVibe Telegram Bot Webhook Handler
 *
 * Commands:
 *   User: /start, /recover, /balance, /support
 *   Admin: /approve, /reject, /user, /stats
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

use App\Services\TelegramService;
use App\Core\DB;

$db = DB::getInstance();
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(200);
    echo 'OK';
    exit;
}

// Handle callback queries
if (isset($input['callback_query'])) {
    handleCallback($db, $input['callback_query']);
    exit;
}

$message = $input['message'] ?? null;
if (!$message) {
    http_response_code(200);
    exit;
}

$chatId = (string)$message['chat']['id'];
$text = trim($message['text'] ?? '');
$fromId = (string)$message['from']['id'];
$firstName = $message['from']['first_name'] ?? 'User';

// Get or create conversation state
$state = $db->first(
    "SELECT * FROM telegram_states WHERE chat_id = ?",
    [$chatId]
);

// Check if in a state flow
if ($state && $state['state'] !== 'idle') {
    handleStateFlow($db, $chatId, $text, $state);
    exit;
}

// Parse command
$command = strtolower(explode(' ', $text)[0]);
$args = substr($text, strlen($command) + 1);

// Admin commands
$adminChatId = $_ENV['TELEGRAM_ADMIN_CHAT_ID'] ?? '';
$isAdmin = ($chatId === $adminChatId);

switch ($command) {
    case '/start':
        $refCode = trim(str_replace('/start', '', $text));
        handleStart($db, $chatId, $firstName, $refCode);
        break;

    case '/recover':
        handleRecover($db, $chatId);
        break;

    case '/balance':
        handleBalance($db, $chatId);
        break;

    case '/support':
        handleSupport($db, $chatId, $args);
        break;

    case '/approve':
        if ($isAdmin) handleAdminApprove($db, $chatId, $args);
        break;

    case '/reject':
        if ($isAdmin) handleAdminReject($db, $chatId, $args);
        break;

    case '/user':
        if ($isAdmin) handleAdminUser($db, $chatId, $args);
        break;

    case '/stats':
        if ($isAdmin) handleAdminStats($db, $chatId);
        break;

    default:
        TelegramService::sendMessage($chatId,
            "🎰 <b>BetVibe Bot</b>\n\nCommands:\n/start — Get started\n/recover — Recover account\n/balance — Check balance\n/support — Contact support"
        );
}

http_response_code(200);

// ──────────────────── Command Handlers ────────────────────

function handleStart(DB $db, string $chatId, string $name, string $refCode): void
{
    $welcome = "🎰 <b>Welcome to BetVibe, {$name}!</b>\n\n";
    $welcome .= "Nepal's #1 GenZ gaming platform 🔥\n\n";
    $welcome .= "🎮 Play 16+ games\n";
    $welcome .= "💰 Real cash prizes\n";
    $welcome .= "📱 <a href=\"https://betsvibe.online\">Open BetVibe</a>\n\n";

    if ($refCode) {
        $welcome .= "✨ Referral code: <code>{$refCode}</code>\n";
        $welcome .= "Sign up to get 50 bonus coins!";
    }

    TelegramService::sendMessage($chatId, $welcome, [
        'inline_keyboard' => [
            [['text' => '🎮 Play Now', 'url' => 'https://betsvibe.online']],
            [['text' => '📱 Register', 'url' => 'https://betsvibe.online/register' . ($refCode ? "?ref={$refCode}" : '')]],
        ]
    ]);

    // Save chat_id for future use
    $db->query(
        "INSERT INTO telegram_states (chat_id, state, data)
         VALUES (?, 'idle', '{}')
         ON DUPLICATE KEY UPDATE state = 'idle'",
        [$chatId]
    );
}

function handleRecover(DB $db, string $chatId): void
{
    TelegramService::sendMessage($chatId,
        "🔑 <b>Account Recovery</b>\n\nPlease enter your registered phone number or email:"
    );

    saveState($db, $chatId, 'recover_input');
}

function handleBalance(DB $db, string $chatId): void
{
    // Look up user by telegram chat_id
    $user = $db->first(
        "SELECT u.id, u.username, w.real_balance, w.bonus_coins
         FROM users u JOIN wallets w ON w.user_id = u.id
         WHERE u.telegram_chat_id = ?",
        [$chatId]
    );

    if (!$user) {
        TelegramService::sendMessage($chatId,
            "❌ Account not linked.\n\nLink your account by visiting <a href=\"https://betsvibe.online/settings\">Settings</a> on the website."
        );
        return;
    }

    $real = number_format((float)$user['real_balance'], 2);
    $bonus = (int)$user['bonus_coins'];

    TelegramService::sendMessage($chatId,
        "💰 <b>{$user['username']}'s Balance</b>\n\n💵 Real: NPR {$real}\n🪙 Bonus: {$bonus} coins"
    );
}

function handleSupport(DB $db, string $chatId, string $message): void
{
    if (!$message) {
        TelegramService::sendMessage($chatId,
            "📧 <b>Support</b>\n\nUsage: /support Your message here\n\nExample: /support I can't withdraw my money"
        );
        return;
    }

    // Create support ticket
    $user = $db->first(
        "SELECT id, username FROM users WHERE telegram_chat_id = ?",
        [$chatId]
    );

    $username = $user ? $user['username'] : 'Unknown';
    $userId = $user ? $user['id'] : null;

    $db->insert('telegram_support_tickets', [
        'user_id' => $userId,
        'telegram_id' => $chatId,
        'message' => $message,
        'status' => 'open',
    ]);

    // Notify admin
    TelegramService::sendToAdminGroup(
        "📨 <b>Support Ticket</b>\n\nFrom: {$username} (Chat: {$chatId})\nMessage: {$message}"
    );

    TelegramService::sendMessage($chatId,
        "✅ Support ticket created!\n\nOur team will respond shortly. Avg response time: 2-4 hours."
    );
}

// ──────────────────── Admin Commands ────────────────────

function handleAdminApprove(DB $db, string $chatId, string $args): void
{
    $withdrawalId = (int)trim($args);
    if (!$withdrawalId) {
        TelegramService::sendMessage($chatId, "Usage: /approve {withdrawal_id}");
        return;
    }

    $wd = $db->first(
        "SELECT wr.*, u.username FROM withdrawal_requests wr JOIN users u ON wr.user_id = u.id WHERE wr.id = ? AND wr.status = 'pending'",
        [$withdrawalId]
    );

    if (!$wd) {
        TelegramService::sendMessage($chatId, "❌ Withdrawal #{$withdrawalId} not found or already processed.");
        return;
    }

    $db->query("UPDATE withdrawal_requests SET status = 'approved', reviewed_at = NOW() WHERE id = ?", [$withdrawalId]);

    TelegramService::sendMessage($chatId,
        "✅ Withdrawal #{$withdrawalId} approved!\n\n👤 {$wd['username']}\n💰 NPR {$wd['amount']}"
    );
}

function handleAdminReject(DB $db, string $chatId, string $args): void
{
    $parts = explode(' ', trim($args), 2);
    $withdrawalId = (int)($parts[0] ?? 0);
    $reason = $parts[1] ?? 'Rejected by admin';

    if (!$withdrawalId) {
        TelegramService::sendMessage($chatId, "Usage: /reject {withdrawal_id} {reason}");
        return;
    }

    $wd = $db->first(
        "SELECT wr.*, u.username FROM withdrawal_requests wr JOIN users u ON wr.user_id = u.id WHERE wr.id = ? AND wr.status = 'pending'",
        [$withdrawalId]
    );

    if (!$wd) {
        TelegramService::sendMessage($chatId, "❌ Not found or already processed.");
        return;
    }

    $db->transaction(function ($db) use ($wd, $withdrawalId, $reason) {
        $db->query("UPDATE withdrawal_requests SET status = 'rejected', admin_note = ?, reviewed_at = NOW() WHERE id = ?", [$reason, $withdrawalId]);
        $db->query("UPDATE wallets SET real_balance = real_balance + ? WHERE user_id = ?", [$wd['amount'], $wd['user_id']]);
    });

    TelegramService::sendMessage($chatId,
        "❌ Withdrawal #{$withdrawalId} rejected.\n\n👤 {$wd['username']}\n💰 NPR {$wd['amount']} returned\n📝 Reason: {$reason}"
    );
}

function handleAdminUser(DB $db, string $chatId, string $args): void
{
    $search = trim($args);
    if (!$search) {
        TelegramService::sendMessage($chatId, "Usage: /user {username or ID}");
        return;
    }

    $user = is_numeric($search)
        ? $db->first("SELECT u.*, w.real_balance, w.bonus_coins FROM users u LEFT JOIN wallets w ON w.user_id = u.id WHERE u.id = ?", [(int)$search])
        : $db->first("SELECT u.*, w.real_balance, w.bonus_coins FROM users u LEFT JOIN wallets w ON w.user_id = u.id WHERE u.username = ?", [$search]);

    if (!$user) {
        TelegramService::sendMessage($chatId, "❌ User not found.");
        return;
    }

    TelegramService::sendMessage($chatId,
        "👤 <b>{$user['username']}</b> (#{$user['id']})\n\n" .
        "💵 Balance: NPR " . number_format((float)$user['real_balance'], 2) . "\n" .
        "🪙 Bonus: {$user['bonus_coins']}\n" .
        "📅 Joined: {$user['created_at']}\n" .
        "🚫 Banned: " . ($user['is_banned'] ? 'Yes' : 'No') . "\n" .
        "🌐 IP: " . ($user['last_ip'] ?? '—')
    );
}

function handleAdminStats(DB $db, string $chatId): void
{
    $stats = $db->first(
        "SELECT
            (SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()) as new_today,
            (SELECT COUNT(DISTINCT user_id) FROM bets WHERE DATE(created_at) = CURDATE()) as active_today,
            (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='deposit' AND status='completed' AND DATE(created_at)=CURDATE()) as deposits_today,
            (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='withdraw' AND status='completed' AND DATE(created_at)=CURDATE()) as withdrawals_today,
            (SELECT COUNT(*) FROM withdrawal_requests WHERE status='pending') as pending_wd"
    );

    TelegramService::sendMessage($chatId,
        "📊 <b>Today's Stats</b>\n\n" .
        "👥 New users: {$stats['new_today']}\n" .
        "🎮 Active: {$stats['active_today']}\n" .
        "💵 Deposits: NPR " . number_format((float)$stats['deposits_today']) . "\n" .
        "💸 Withdrawals: NPR " . number_format((float)$stats['withdrawals_today']) . "\n" .
        "⏳ Pending WDs: {$stats['pending_wd']}"
    );
}

// ──────────────────── State Machine ────────────────────

function handleStateFlow(DB $db, string $chatId, string $text, array $state): void
{
    switch ($state['state']) {
        case 'recover_input':
            // User sent phone/email for recovery
            $user = $db->first(
                "SELECT id, username, phone FROM users WHERE phone = ? OR email = ?",
                [$text, $text]
            );

            if (!$user) {
                TelegramService::sendMessage($chatId, "❌ No account found with that info.");
                saveState($db, $chatId, 'idle');
                return;
            }

            // Generate temp password
            $tempPw = bin2hex(random_bytes(4));
            $hash = password_hash($tempPw, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->query("UPDATE users SET password_hash = ?, telegram_chat_id = ? WHERE id = ?", [$hash, $chatId, $user['id']]);

            TelegramService::sendMessage($chatId,
                "✅ Account found: <b>{$user['username']}</b>\n\n" .
                "🔑 Temp password: <code>{$tempPw}</code>\n\n" .
                "Login at betsvibe.online and change your password immediately!"
            );

            saveState($db, $chatId, 'idle');
            break;

        default:
            saveState($db, $chatId, 'idle');
    }
}

function saveState(DB $db, string $chatId, string $state, array $data = []): void
{
    $db->query(
        "INSERT INTO telegram_states (chat_id, state, data) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE state = VALUES(state), data = VALUES(data)",
        [$chatId, $state, json_encode($data)]
    );
}

function handleCallback(DB $db, array $callbackQuery): void
{
    $data = $callbackQuery['data'] ?? '';
    $callbackId = $callbackQuery['id'];

    TelegramService::answerCallbackQuery($callbackId, 'Processing...');
}
