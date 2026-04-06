<?php
/**
 * Lucky Hours Notification Cron
 * Runs daily at 14:10 UTC (7:55pm NPT)
 * Sends push notifications to all subscribers about lucky hour bonus
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$pushService = new \App\Services\PushNotificationService();

$dayOfWeek = date('l'); // Monday, Tuesday, etc.
$messages = [
    'Monday' => ['🔥 Monday Madness!', 'Play now and get 2x XP for the next 2 hours!'],
    'Tuesday' => ['💎 Treasure Tuesday!', 'Bonus coins for every 5th bet today!'],
    'Wednesday' => ['⚡ Midweek Boost!', 'Win streaks give double rewards tonight!'],
    'Thursday' => ['🎯 Thursday Thrills!', 'All game multipliers boosted for 1 hour!'],
    'Friday' => ['🎉 Friday Fiesta!', 'Happy hour: Extra leaderboard points tonight!'],
    'Saturday' => ['🏆 Weekend Warriors!', 'Leaderboard awards doubled this weekend!'],
    'Sunday' => ['🌟 Sunday Special!', 'Login now for bonus daily reward!'],
];

$title = $messages[$dayOfWeek][0] ?? '🎰 BetVibe Lucky Hour!';
$body = $messages[$dayOfWeek][1] ?? 'Login now for special bonuses!';

$sent = $pushService->broadcast($title, $body, '/');

echo "Sent push notifications to {$sent} subscribers\n";
echo "Day: {$dayOfWeek}\n";
echo "Title: {$title}\n";
echo "Body: {$body}\n";
