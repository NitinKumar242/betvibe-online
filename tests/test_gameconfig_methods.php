<?php
/**
 * Test script for GameConfig model methods
 */

require_once __DIR__ . '/../app/Core/DB.php';
require_once __DIR__ . '/../app/Models/BaseModel.php';
require_once __DIR__ . '/../app/Models/GameConfig.php';

use App\Core\DB;
use App\Models\GameConfig;

echo "=== Testing GameConfig Model Methods ===\n\n";

try {
    $db = DB::getInstance();
    $gameConfigModel = new GameConfig();

    // Test 1: getAllEnabled
    echo "Test 1: getAllEnabled()\n";
    echo "--------------------------------\n";

    $enabledGames = $gameConfigModel->getAllEnabled();
    echo "Number of enabled games: " . count($enabledGames) . "\n";

    if (!empty($enabledGames)) {
        echo "First enabled game: {$enabledGames[0]['display_name']} ({$enabledGames[0]['game_slug']})\n";
        echo "Last enabled game: " . end($enabledGames)['display_name'] . " (" . end($enabledGames)['game_slug'] . ")\n";
    }

    echo "\n";

    // Test 2: updateConfig
    echo "Test 2: updateConfig()\n";
    echo "--------------------------------\n";

    // Get a test game slug
    $testSlug = 'crash';
    $gameBefore = $gameConfigModel->getBySlug($testSlug);

    if ($gameBefore) {
        echo "Original config for '{$testSlug}':\n";
        echo "  - Display Name: {$gameBefore['display_name']}\n";
        echo "  - Win Ratio: {$gameBefore['win_ratio']}\n";
        echo "  - Min Bet: {$gameBefore['min_bet']}\n";
        echo "  - Max Bet: {$gameBefore['max_bet']}\n";
        echo "  - Enabled: " . ($gameBefore['is_enabled'] ? 'YES' : 'NO') . "\n";

        // Update multiple fields
        $updateData = [
            'display_name' => 'Crash Game Updated',
            'win_ratio' => 25.00,
            'min_bet' => 20.00,
            'max_bet' => 15000.00,
            'is_enabled' => 1
        ];

        $gameConfigModel->updateConfig($testSlug, $updateData);
        echo "\nUpdated config with new values...\n";

        $gameAfter = $gameConfigModel->getBySlug($testSlug);
        echo "Updated config for '{$testSlug}':\n";
        echo "  - Display Name: {$gameAfter['display_name']}\n";
        echo "  - Win Ratio: {$gameAfter['win_ratio']}\n";
        echo "  - Min Bet: {$gameAfter['min_bet']}\n";
        echo "  - Max Bet: {$gameAfter['max_bet']}\n";
        echo "  - Enabled: " . ($gameAfter['is_enabled'] ? 'YES' : 'NO') . "\n";

        // Restore original values
        $restoreData = [
            'display_name' => 'Crash',
            'win_ratio' => 20.00,
            'min_bet' => 10.00,
            'max_bet' => 10000.00
        ];

        $gameConfigModel->updateConfig($testSlug, $restoreData);
        echo "\nRestored original values...\n";

        $gameRestored = $gameConfigModel->getBySlug($testSlug);
        echo "Restored config for '{$testSlug}':\n";
        echo "  - Display Name: {$gameRestored['display_name']}\n";
        echo "  - Win Ratio: {$gameRestored['win_ratio']}\n";
        echo "  - Min Bet: {$gameRestored['min_bet']}\n";
        echo "  - Max Bet: {$gameRestored['max_bet']}\n";
    } else {
        echo "Game '{$testSlug}' not found. Skipping update test.\n";
    }

    echo "\n";

    // Test 3: updateConfig with extra_config
    echo "Test 3: updateConfig() with extra_config\n";
    echo "--------------------------------\n";

    $testSlug2 = 'mines';
    $gameBefore2 = $gameConfigModel->getBySlug($testSlug2);

    if ($gameBefore2) {
        echo "Original extra_config for '{$testSlug2}': " . ($gameBefore2['extra_config'] ?? 'NULL') . "\n";

        $extraConfigData = [
            'grid_size' => 5,
            'mines_count' => 3,
            'multipliers' => [1.2, 1.5, 2.0, 3.0, 5.0]
        ];

        $gameConfigModel->updateConfig($testSlug2, ['extra_config' => $extraConfigData]);
        echo "Updated extra_config with test data...\n";

        $gameAfter2 = $gameConfigModel->getBySlug($testSlug2);
        echo "Updated extra_config for '{$testSlug2}': " . ($gameAfter2['extra_config'] ?? 'NULL') . "\n";

        // Restore
        $gameConfigModel->updateConfig($testSlug2, ['extra_config' => null]);
        echo "Restored extra_config to NULL...\n";
    } else {
        echo "Game '{$testSlug2}' not found. Skipping extra_config test.\n";
    }

    echo "\n";

    // Test 4: updateConfig with partial data
    echo "Test 4: updateConfig() with partial data\n";
    echo "--------------------------------\n";

    $testSlug3 = 'limbo';
    $gameBefore3 = $gameConfigModel->getBySlug($testSlug3);

    if ($gameBefore3) {
        echo "Original win_ratio for '{$testSlug3}': {$gameBefore3['win_ratio']}\n";

        // Update only win_ratio
        $gameConfigModel->updateConfig($testSlug3, ['win_ratio' => 22.50]);
        echo "Updated only win_ratio to 22.50...\n";

        $gameAfter3 = $gameConfigModel->getBySlug($testSlug3);
        echo "Updated win_ratio for '{$testSlug3}': {$gameAfter3['win_ratio']}\n";
        echo "Display name unchanged: {$gameAfter3['display_name']}\n";

        // Restore
        $gameConfigModel->updateConfig($testSlug3, ['win_ratio' => 20.00]);
        echo "Restored win_ratio to 20.00...\n";
    } else {
        echo "Game '{$testSlug3}' not found. Skipping partial update test.\n";
    }

    echo "\n";

    echo "=== All GameConfig Model Tests Completed ===\n";

} catch (Exception $e) {
    echo "\nError: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
