<?php
/**
 * Test script for Wallet model methods
 */

require_once __DIR__ . '/../app/Core/DB.php';
require_once __DIR__ . '/../app/Models/BaseModel.php';
require_once __DIR__ . '/../app/Models/User.php';
require_once __DIR__ . '/../app/Models/Wallet.php';

use App\Core\DB;
use App\Models\User;
use App\Models\Wallet;

echo "=== Testing Wallet Model Methods ===\n\n";

try {
    $db = DB::getInstance();
    $userModel = new User();
    $walletModel = new Wallet();

    // Create a test user first
    echo "Setup: Creating test user...\n";
    $userId = $userModel->create([
        'username' => 'wallettest_' . time(),
        'email' => 'wallettest@example.com',
        'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
        'ref_code' => 'WALLET' . time()
    ]);
    echo "Created test user with ID: {$userId}\n\n";

    // Test 1: getBalance
    echo "Test 1: getBalance()\n";
    echo "--------------------------------\n";

    $balance = $walletModel->getBalance($userId);
    echo "Initial balance - Real: {$balance['real']}, Bonus: {$balance['bonus']}\n";

    // Add some funds
    $walletModel->addRealBalance($userId, 100.00);
    $walletModel->addBonusCoins($userId, 50.00);

    $balanceAfterAdd = $walletModel->getBalance($userId);
    echo "After adding funds - Real: {$balanceAfterAdd['real']}, Bonus: {$balanceAfterAdd['bonus']}\n";

    echo "\n";

    // Test 2: deductBet
    echo "Test 2: deductBet()\n";
    echo "--------------------------------\n";

    // Test deducting from real balance
    $balanceType1 = $walletModel->deductBet($userId, 30.00);
    echo "Deducted 30.00 from: {$balanceType1}\n";

    $balanceAfterDeduct1 = $walletModel->getBalance($userId);
    echo "Balance after deduct - Real: {$balanceAfterDeduct1['real']}, Bonus: {$balanceAfterDeduct1['bonus']}\n";

    // Test deducting from bonus balance (when real is low)
    $balanceType2 = $walletModel->deductBet($userId, 80.00);
    echo "Deducted 80.00 from: {$balanceType2}\n";

    $balanceAfterDeduct2 = $walletModel->getBalance($userId);
    echo "Balance after deduct - Real: {$balanceAfterDeduct2['real']}, Bonus: {$balanceAfterDeduct2['bonus']}\n";

    echo "\n";

    // Test 3: creditAmount
    echo "Test 3: creditAmount()\n";
    echo "--------------------------------\n";

    // Credit to real balance
    $walletModel->creditAmount($userId, 25.00, 'real');
    echo "Credited 25.00 to real balance\n";

    // Credit to bonus balance
    $walletModel->creditAmount($userId, 15.00, 'bonus');
    echo "Credited 15.00 to bonus balance\n";

    $balanceAfterCredit = $walletModel->getBalance($userId);
    echo "Balance after credit - Real: {$balanceAfterCredit['real']}, Bonus: {$balanceAfterCredit['bonus']}\n";

    echo "\n";

    // Test 4: addBonusCoins
    echo "Test 4: addBonusCoins()\n";
    echo "--------------------------------\n";

    $walletModel->addBonusCoins($userId, 100.00);
    echo "Added 100.00 bonus coins\n";

    $balanceAfterBonus = $walletModel->getBalance($userId);
    echo "Balance after bonus - Real: {$balanceAfterBonus['real']}, Bonus: {$balanceAfterBonus['bonus']}\n";

    echo "\n";

    // Test 5: checkWageringRequirement
    echo "Test 5: checkWageringRequirement()\n";
    echo "--------------------------------\n";

    // Initial state - should be false (bonus > 0 but not wagered enough)
    $wageringMet = $walletModel->checkWageringRequirement($userId);
    echo "Wagering requirement met (initial): " . ($wageringMet ? "YES" : "NO (expected - 3x requirement)") . "\n";

    // Add wagering to meet requirement (bonus * 3)
    $currentBonus = $balanceAfterBonus['bonus'];
    $requiredWager = $currentBonus * 3;
    $walletModel->addWagered($userId, $requiredWager);
    echo "Added {$requiredWager} to total wagered\n";

    $wageringMetAfter = $walletModel->checkWageringRequirement($userId);
    echo "Wagering requirement met (after wagering): " . ($wageringMetAfter ? "YES" : "NO") . "\n";

    // Test with no bonus
    $walletModel->subtractBonusCoins($userId, $currentBonus);
    $wageringMetNoBonus = $walletModel->checkWageringRequirement($userId);
    echo "Wagering requirement met (no bonus): " . ($wageringMetNoBonus ? "YES (expected)" : "NO") . "\n";

    echo "\n";

    // Cleanup
    echo "Cleanup: Deleting test user and wallet...\n";
    $wallet = $walletModel->findByUserId($userId);
    if ($wallet) {
        $walletModel->delete($wallet['id']);
    }
    $userModel->delete($userId);
    echo "Test user and wallet deleted.\n";

    echo "\n=== All Wallet Model Tests Completed ===\n";

} catch (Exception $e) {
    echo "\nError: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
