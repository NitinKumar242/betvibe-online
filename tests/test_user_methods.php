<?php
/**
 * Test script for User model methods
 */

require_once __DIR__ . '/../app/Core/DB.php';
require_once __DIR__ . '/../app/Models/BaseModel.php';
require_once __DIR__ . '/../app/Models/User.php';

use App\Core\DB;
use App\Models\User;

echo "=== Testing User Model Methods ===\n\n";

try {
    $db = DB::getInstance();
    $userModel = new User();

    // Test 1: findByEmailOrPhone
    echo "Test 1: findByEmailOrPhone()\n";
    echo "--------------------------------\n";

    // Create a test user
    $testEmail = 'test@example.com';
    $testPhone = '+1234567890';
    $testRefCode = 'TEST123';

    $userId = $userModel->create([
        'username' => 'testuser_' . time(),
        'email' => $testEmail,
        'phone' => $testPhone,
        'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
        'ref_code' => $testRefCode
    ]);

    echo "Created test user with ID: {$userId}\n";

    // Find by email
    $userByEmail = $userModel->findByEmailOrPhone($testEmail);
    echo "Found by email: " . ($userByEmail ? "YES (ID: {$userByEmail['id']})" : "NO") . "\n";

    // Find by phone
    $userByPhone = $userModel->findByEmailOrPhone($testPhone);
    echo "Found by phone: " . ($userByPhone ? "YES (ID: {$userByPhone['id']})" : "NO") . "\n";

    // Find by non-existent identifier
    $userByInvalid = $userModel->findByEmailOrPhone('invalid@example.com');
    echo "Found by invalid email: " . ($userByInvalid ? "YES" : "NO (expected)") . "\n";

    echo "\n";

    // Test 2: findByRefCode
    echo "Test 2: findByRefCode()\n";
    echo "--------------------------------\n";

    $userByRefCode = $userModel->findByRefCode($testRefCode);
    echo "Found by ref code '{$testRefCode}': " . ($userByRefCode ? "YES (ID: {$userByRefCode['id']})" : "NO") . "\n";

    $userByInvalidRef = $userModel->findByRefCode('INVALID');
    echo "Found by invalid ref code: " . ($userByInvalidRef ? "YES" : "NO (expected)") . "\n";

    echo "\n";

    // Test 3: updateLoginInfo
    echo "Test 3: updateLoginInfo()\n";
    echo "--------------------------------\n";

    $testIp = '192.168.1.100';
    $testDeviceFp = 'device_fingerprint_12345';

    $userModel->updateLoginInfo($userId, $testIp, $testDeviceFp);
    echo "Updated login info for user ID: {$userId}\n";

    $updatedUser = $userModel->find($userId);
    echo "Last IP: " . ($updatedUser['last_ip'] ?? 'NULL') . "\n";
    echo "Device FP: " . ($updatedUser['device_fp'] ?? 'NULL') . "\n";
    echo "Last Login: " . ($updatedUser['last_login'] ?? 'NULL') . "\n";

    echo "\n";

    // Test 4: incrementFailedAttempts
    echo "Test 4: incrementFailedAttempts()\n";
    echo "--------------------------------\n";

    $userModel->incrementFailedAttempts($userId);
    echo "Incremented failed attempts for user ID: {$userId}\n";

    $userAfterIncrement = $userModel->find($userId);
    echo "Failed attempts: " . ($userAfterIncrement['failed_attempts'] ?? 0) . "\n";
    echo "Last failed attempt: " . ($userAfterIncrement['last_failed_attempt'] ?? 'NULL') . "\n";

    // Increment more times to reach 5
    for ($i = 0; $i < 4; $i++) {
        $userModel->incrementFailedAttempts($userId);
    }

    $userAfter5Attempts = $userModel->find($userId);
    echo "After 5 increments, failed attempts: " . ($userAfter5Attempts['failed_attempts'] ?? 0) . "\n";

    echo "\n";

    // Test 5: isLockedOut
    echo "Test 5: isLockedOut()\n";
    echo "--------------------------------\n";

    $isLocked = $userModel->isLockedOut($userId);
    echo "User locked out (5+ attempts in 15 min): " . ($isLocked ? "YES" : "NO") . "\n";

    // Reset and test with fewer attempts
    $userModel->resetFailedAttempts($userId);
    $userModel->incrementFailedAttempts($userId);
    $userModel->incrementFailedAttempts($userId);

    $isLockedAfter2 = $userModel->isLockedOut($userId);
    echo "User locked out (2 attempts): " . ($isLockedAfter2 ? "YES" : "NO (expected)") . "\n";

    echo "\n";

    // Test 6: resetFailedAttempts
    echo "Test 6: resetFailedAttempts()\n";
    echo "--------------------------------\n";

    $userModel->resetFailedAttempts($userId);
    echo "Reset failed attempts for user ID: {$userId}\n";

    $userAfterReset = $userModel->find($userId);
    echo "Failed attempts after reset: " . ($userAfterReset['failed_attempts'] ?? 0) . "\n";
    echo "Last failed attempt after reset: " . ($userAfterReset['last_failed_attempt'] ?? 'NULL') . "\n";

    echo "\n";

    // Cleanup
    echo "Cleanup: Deleting test user...\n";
    $userModel->delete($userId);
    echo "Test user deleted.\n";

    echo "\n=== All User Model Tests Completed ===\n";

} catch (Exception $e) {
    echo "\nError: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
