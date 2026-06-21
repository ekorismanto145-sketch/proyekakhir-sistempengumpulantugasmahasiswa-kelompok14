<?php

if (!function_exists('ensureSecurityWorkflowTables')) {
    function ensureSecurityWorkflowTables($conn) {
        static $done = false;
        if ($done) {
            return;
        }

        $conn->query("
            CREATE TABLE IF NOT EXISTS lecturer_break_glass_codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                lecturer_id INT NOT NULL,
                code_hash VARCHAR(255) NOT NULL,
                issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                revoked_at DATETIME DEFAULT NULL,
                last_used_at DATETIME DEFAULT NULL,
                is_active TINYINT(1) DEFAULT 1,
                INDEX(lecturer_id),
                FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS sensitive_change_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                actor_user_id INT NOT NULL,
                actor_role VARCHAR(20) NOT NULL,
                lecturer_id INT NOT NULL,
                class_id INT DEFAULT NULL,
                target_type VARCHAR(50) NOT NULL,
                target_id VARCHAR(100) NOT NULL,
                action_type VARCHAR(100) NOT NULL,
                payload_json TEXT DEFAULT NULL,
                reason TEXT NOT NULL,
                status ENUM('pending', 'approved', 'rejected', 'executed', 'expired') DEFAULT 'pending',
                approver_user_id INT DEFAULT NULL,
                break_glass_used TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                approved_at DATETIME DEFAULT NULL,
                executed_at DATETIME DEFAULT NULL,
                rejected_at DATETIME DEFAULT NULL,
                INDEX(actor_user_id),
                INDEX(lecturer_id),
                INDEX(class_id),
                INDEX(status)
            )
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS security_audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                actor_user_id INT NOT NULL,
                actor_role VARCHAR(20) NOT NULL,
                action_type VARCHAR(100) NOT NULL,
                target_type VARCHAR(100) NOT NULL,
                target_id VARCHAR(100) NOT NULL,
                reason TEXT NOT NULL,
                context_json TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX(actor_user_id),
                INDEX(action_type),
                INDEX(target_type)
            )
        ");

        $done = true;
    }
}

if (!function_exists('logSecurityAction')) {
    function logSecurityAction($conn, $actorUserId, $actorRole, $actionType, $targetType, $targetId, $reason, $context = null) {
        ensureSecurityWorkflowTables($conn);
        $ctx = $context === null ? null : json_encode($context, JSON_UNESCAPED_UNICODE);
        $stmt = $conn->prepare("INSERT INTO security_audit_logs (actor_user_id, actor_role, action_type, target_type, target_id, reason, context_json) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $actorUserId, $actorRole, $actionType, $targetType, $targetId, $reason, $ctx);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('getActiveBreakGlassCodeRow')) {
    function getActiveBreakGlassCodeRow($conn, $lecturerId) {
        ensureSecurityWorkflowTables($conn);
        $stmt = $conn->prepare("SELECT * FROM lecturer_break_glass_codes WHERE lecturer_id = ? AND is_active = 1 AND revoked_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("i", $lecturerId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('verifyBreakGlassCode')) {
    function verifyBreakGlassCode($conn, $lecturerId, $code) {
        $row = getActiveBreakGlassCodeRow($conn, $lecturerId);
        if (!$row || empty($code)) {
            return false;
        }
        if (!password_verify($code, $row['code_hash'])) {
            return false;
        }
        return $row;
    }
}

if (!function_exists('issueBreakGlassCode')) {
    function issueBreakGlassCode($conn, $lecturerId, $days = 15) {
        ensureSecurityWorkflowTables($conn);
        $plain = strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4)));
        $hash = password_hash($plain, PASSWORD_DEFAULT);

        $conn->query("UPDATE lecturer_break_glass_codes SET is_active = 0, revoked_at = NOW() WHERE lecturer_id = " . (int)$lecturerId . " AND is_active = 1 AND revoked_at IS NULL");

        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . (int)$days . ' days'));
        $stmt = $conn->prepare("INSERT INTO lecturer_break_glass_codes (lecturer_id, code_hash, expires_at, is_active) VALUES (?, ?, ?, 1)");
        $stmt->bind_param("iss", $lecturerId, $hash, $expiresAt);
        $stmt->execute();
        $stmt->close();

        return $plain;
    }
}

if (!function_exists('createSensitiveRequest')) {
    function createSensitiveRequest($conn, $actor, $lecturerId, $classId, $targetType, $targetId, $actionType, array $payload, $reason) {
        ensureSecurityWorkflowTables($conn);
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $actorUserId = (int)$actor['id'];
        $actorRole = $actor['role'];
        $stmt = $conn->prepare("INSERT INTO sensitive_change_requests (actor_user_id, actor_role, lecturer_id, class_id, target_type, target_id, action_type, payload_json, reason, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("isiisssss", $actorUserId, $actorRole, $lecturerId, $classId, $targetType, $targetId, $actionType, $payloadJson, $reason);
        $stmt->execute();
        $requestId = $stmt->insert_id;
        $stmt->close();
        return $requestId;
    }
}

if (!function_exists('loadSensitiveRequest')) {
    function loadSensitiveRequest($conn, $requestId) {
        ensureSecurityWorkflowTables($conn);
        $stmt = $conn->prepare("SELECT * FROM sensitive_change_requests WHERE id = ?");
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('approveSensitiveRequest')) {
    function approveSensitiveRequest($conn, $requestId, $approverId, $reason = null) {
        $stmt = $conn->prepare("UPDATE sensitive_change_requests SET status = 'approved', approver_user_id = ?, approved_at = NOW(), updated_at = NOW() WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("ii", $approverId, $requestId);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('rejectSensitiveRequest')) {
    function rejectSensitiveRequest($conn, $requestId, $approverId) {
        $stmt = $conn->prepare("UPDATE sensitive_change_requests SET status = 'rejected', approver_user_id = ?, rejected_at = NOW(), updated_at = NOW() WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("ii", $approverId, $requestId);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('executeSensitiveRequest')) {
    function executeSensitiveRequest($conn, $requestId) {
        $stmt = $conn->prepare("UPDATE sensitive_change_requests SET status = 'executed', executed_at = NOW(), updated_at = NOW() WHERE id = ? AND status IN ('pending', 'approved')");
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('consumeBreakGlassCode')) {
    function consumeBreakGlassCode($conn, $codeRow) {
        $stmt = $conn->prepare("UPDATE lecturer_break_glass_codes SET is_active = 0, last_used_at = NOW(), revoked_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $codeRow['id']);
        $stmt->execute();
        $stmt->close();
    }
}
