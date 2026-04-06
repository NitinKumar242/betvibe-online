<?php
/**
 * Weekly Leaderboard Reset Cron
 * Runs Sunday midnight NPT (Saturday 18:15 UTC)
 * Awards top 3 weekly profit earners real balance bonuses
 * 1st: NPR 500, 2nd: NPR 200, 3rd: NPR 100
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$db = \App\Core\DB::getInstance();

$rewards = [500, 200, 100];

// Get top 3 weekly profit earners (real bets only)
$topPlayers = $db->all(
    "SELECT u.id, u.username, SUM(b.payout - b.bet_amount) as profit,
            COUNT(CASE WHEN b.result = 'win' THEN 1 END) as wins
     FROM bets b
     JOIN users u ON u.id = b.user_id
     WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
       AND b.result IN ('win', 'loss')
       AND b.balance_type = 'real'
     GROUP BY b.user_id
     HAVING profit > 0
     ORDER BY profit DESC
     LIMIT 3"
);

if (empty($topPlayers)) {
    echo "No weekly winners to reward.\n";
    exit(0);
}

$db->beginTransaction();

try {
    $messages = [];

    foreach ($topPlayers as $index => $player) {
        $reward = $rewards[$index] ?? 0;
        if ($reward <= 0) break;

        $position = $index + 1;
        $positionLabel = match ($position) {
            1 => '🥇 1st',
            2 => '🥈 2nd',
            3 => '🥉 3rd',
        };

        // Credit real balance
        $db->query(
            "UPDATE wallets SET real_balance = real_balance + ? WHERE user_id = ?",
            [$reward, $player['id']]
        );

        // Log transaction
        $db->insert('transactions', [
            'user_id' => $player['id'],
            'type' => 'bonus',
            'amount' => $reward,
            'balance_type' => 'real',
            'status' => 'completed',
            'note' => "Weekly leaderboard reward - {$positionLabel} place",
        ]);

        $messages[] = "{$positionLabel}: {$player['username']} — NPR " . number_format($player['profit'], 0) . " profit → NPR {$reward} reward";
    }

    $db->commit();

    // Send Telegram notification to admin
    $summary = "🏆 Weekly Leaderboard Reset\n\n" . implode("\n", $messages);

    $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
    $adminChatId = $_ENV['TELEGRAM_ADMIN_CHAT_ID'] ?? '';

    if ($botToken && $adminChatId) {
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'chat_id' => $adminChatId,
                'text' => $summary,
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    echo $summary . "\n";

} catch (\Exception $e) {
    $db->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
