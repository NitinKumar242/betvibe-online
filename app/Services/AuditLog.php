<?php
/**
 * BetVibe - Audit Log Service
 * Records all admin actions for accountability
 */

namespace App\Services;

use App\Core\DB;

class AuditLog
{
    /**
     * Write an admin action to the audit log
     */
    public static function write(int $adminId, string $action, ?string $targetType = null, $targetId = null, ?string $details = null): void
    {
        $db = DB::getInstance();
        $db->insert('admin_audit_log', [
            'admin_id' => $adminId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId ? (int)$targetId : null,
            'old_value' => null,
            'new_value' => $details,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        ]);
    }
}
