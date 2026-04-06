<?php
/**
 * Migration script to create active_game_sessions table
 */

require_once __DIR__ . '/../../app/Core/DB.php';

use App\Core\DB;

try {
    $db = DB::getInstance();
    $pdo = $db->getConnection();

    echo "Creating active_game_sessions table...\n";

    // Read the SQL file
    $sql = file_get_contents(__DIR__ . '/create_active_game_sessions_table.sql');

    if ($sql === false) {
        throw new \Exception("Could not read SQL file");
    }

    // Execute the SQL
    $pdo->exec($sql);

    echo "Migration completed successfully!\n";
    echo "Table 'active_game_sessions' has been created.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
