<?php
/**
 * Migration script to add failed_attempts columns to users table
 */

require_once __DIR__ . '/../../app/Core/DB.php';

use App\Core\DB;

try {
    $db = DB::getInstance();
    $pdo = $db->getConnection();

    echo "Adding failed_attempts and last_failed_attempt columns to users table...\n";

    $sql = "ALTER TABLE users
            ADD COLUMN failed_attempts TINYINT DEFAULT 0 AFTER device_fp,
            ADD COLUMN last_failed_attempt DATETIME AFTER failed_attempts";

    $pdo->exec($sql);

    echo "Migration completed successfully!\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
