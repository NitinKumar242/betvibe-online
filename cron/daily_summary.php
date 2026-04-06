<?php
/**
 * Daily Summary Cron Job
 * Generates daily reports and sends notifications
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Connect to database
$db = new PDO(
    "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']}",
    $_ENV['DB_USER'],
    $_ENV['DB_PASS']
);

$yesterday = date('Y-m-d', strtotime('-1 day'));

// Generate daily statistics
$stats = generateDailyStats($db, $yesterday);

// Log the summary
logDailySummary($yesterday, $stats);

// Send summary to admin via Telegram
sendAdminSummary($stats);

function generateDailyStats($db, $date)
{
    $stats = [];

    // Total bets
    $stmt = $db->prepare("SELECT COUNT(*) FROM bets WHERE DATE(created_at) = ?");
    $stmt->execute([$date]);
    $stats['total_bets'] = $stmt->fetchColumn();

    // Total wagered
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM bets WHERE DATE(created_at) = ?");
    $stmt->execute([$date]);
    $stats['total_wagered'] = $stmt->fetchColumn();

    // Total payouts
    $stmt = $db->prepare("SELECT COALESCE(SUM(payout), 0) FROM bets WHERE DATE(created_at) = ? AND payout > 0");
    $stmt->execute([$date]);
    $stats['total_payouts'] = $stmt->fetchColumn();

    // House profit
    $stats['house_profit'] = $stats['total_wagered'] - $stats['total_payouts'];

    // Active users
    $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) FROM bets WHERE DATE(created_at) = ?");
    $stmt->execute([$date]);
    $stats['active_users'] = $stmt->fetchColumn();

    // New users
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at) = ?");
    $stmt->execute([$date]);
    $stats['new_users'] = $stmt->fetchColumn();

    return $stats;
}

function logDailySummary($date, $stats)
{
    $logFile = __DIR__ . '/../storage/logs/daily_summary.log';
    $logEntry = sprintf(
        "[%s] Date: %s | Bets: %d | Wagered: %.2f | Payouts: %.2f | Profit: %.2f | Active Users: %d | New Users: %d\n",
        date('Y-m-d H:i:s'),
        $date,
        $stats['total_bets'],
        $stats['total_wagered'],
        $stats['total_payouts'],
        $stats['house_profit'],
        $stats['active_users'],
        $stats['new_users']
    );
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function sendAdminSummary($stats)
{
    // Send summary to admin via Telegram
    $botToken = $_ENV['TELEGRAM_BOT_TOKEN'];
    $adminChatId = $_ENV['TELEGRAM_ADMIN_CHAT_ID'];

    $message = "📊 Daily Summary\n\n";
    $message .= "🎯 Total Bets: {$stats['total_bets']}\n";
    $message .= "💰 Total Wagered: ₹{$stats['total_wagered']}\n";
    $message .= "💸 Total Payouts: ₹{$stats['total_payouts']}\n";
    $message .= "📈 House Profit: ₹{$stats['house_profit']}\n";
    $message .= "👥 Active Users: {$stats['active_users']}\n";
    $message .= "🆕 New Users: {$stats['new_users']}\n";

    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $data = [
        'chat_id' => $adminChatId,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];

    // Send the message
    file_get_contents($url . '?' . http_build_query($data));
}
