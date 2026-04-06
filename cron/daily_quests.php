<?php
/**
 * Daily Quests Cron Job
 * Runs at midnight NPT (18:15 UTC)
 * Generates 3 quests for today (1 easy + 1 medium + 1 hard)
 * Rotates so same quest doesn't repeat within 7 days
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$db = \App\Core\DB::getInstance();

$today = date('Y-m-d');

// Check if quests already exist for today
$existing = $db->first(
    "SELECT COUNT(*) as c FROM daily_quests WHERE active_date = ?",
    [$today]
);

if ((int)$existing['c'] > 0) {
    echo "Quests already generated for {$today}\n";
    exit(0);
}

// Load quest templates
$templates = require __DIR__ . '/../config/quest_templates.php';

// Get quests used in last 7 days
$recentKeys = $db->all(
    "SELECT DISTINCT quest_key FROM daily_quests WHERE active_date >= DATE_SUB(?, INTERVAL 7 DAY)",
    [$today]
);
$recentKeySet = array_column($recentKeys, 'quest_key');

// Separate by difficulty
$easy = array_filter($templates, fn($t) => $t['difficulty'] === 'easy' && !in_array($t['key'], $recentKeySet));
$medium = array_filter($templates, fn($t) => $t['difficulty'] === 'medium' && !in_array($t['key'], $recentKeySet));
$hard = array_filter($templates, fn($t) => $t['difficulty'] === 'hard' && !in_array($t['key'], $recentKeySet));

// If all quests used recently, allow repeats
if (empty($easy)) $easy = array_filter($templates, fn($t) => $t['difficulty'] === 'easy');
if (empty($medium)) $medium = array_filter($templates, fn($t) => $t['difficulty'] === 'medium');
if (empty($hard)) $hard = array_filter($templates, fn($t) => $t['difficulty'] === 'hard');

// Re-index
$easy = array_values($easy);
$medium = array_values($medium);
$hard = array_values($hard);

// Pick 1 from each difficulty
$selected = [
    $easy[array_rand($easy)],
    $medium[array_rand($medium)],
    $hard[array_rand($hard)],
];

// Insert into daily_quests
foreach ($selected as $quest) {
    $db->insert('daily_quests', [
        'quest_key' => $quest['key'],
        'title' => $quest['title'],
        'description' => $quest['description'],
        'difficulty' => $quest['difficulty'],
        'xp_reward' => $quest['xp_reward'],
        'coin_reward' => $quest['coin_reward'],
        'condition' => json_encode($quest['condition']),
        'active_date' => $today,
    ]);
}

echo "Generated 3 daily quests for {$today}:\n";
foreach ($selected as $q) {
    echo "  [{$q['difficulty']}] {$q['title']}\n";
}
