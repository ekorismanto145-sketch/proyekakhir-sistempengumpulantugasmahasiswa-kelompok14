<?php
if (!function_exists('ensureOverrideAuditTable')) {
    function ensureOverrideAuditTable($conn) {
        static $initialized = false;
        if ($initialized) {
            return;
        }

        $conn->query("
            CREATE TABLE IF NOT EXISTS security_audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                actor_user_id INT NOT NULL,
                actor_role VARCHAR(20) NOT NULL,
                action_type VARCHAR(100) NOT NULL,
                target_type VARCHAR(100) NOT NULL,
                target_id VARCHAR(100) NOT NULL,
                reason TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX(actor_user_id),
                INDEX(action_type),
                INDEX(target_type)
            )
        ");

        $initialized = true;
    }
}

if (!function_exists('logSecurityOverride')) {
    function logSecurityOverride($conn, $actorUserId, $actorRole, $actionType, $targetType, $targetId, $reason) {
        ensureOverrideAuditTable($conn);
        $stmt = $conn->prepare("INSERT INTO security_audit_logs (actor_user_id, actor_role, action_type, target_type, target_id, reason) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $actorUserId, $actorRole, $actionType, $targetType, $targetId, $reason);
        $stmt->execute();
        $stmt->close();
    }
}
