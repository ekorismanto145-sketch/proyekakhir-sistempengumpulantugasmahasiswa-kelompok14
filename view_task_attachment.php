<?php
include 'includes/db.php';
include 'includes/lang.php';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit(); }

$user = $_SESSION['user'];
$role = $user['role'];
$attachment_id = (int)($_GET['attachment_id'] ?? 0);
if ($attachment_id <= 0) {
    die(t('invalid_parameter'));
}

$stmt = $conn->prepare(
    "SELECT ta.*, t.class_id, c.dosen_id,
            COALESCE(ta.file_path, m.file_path) AS resolved_path,
            COALESCE(ta.mime_type, m.mime_type, 'application/octet-stream') AS resolved_mime,
            COALESCE(NULLIF(ta.original_name, ''), m.original_name) AS resolved_name
     FROM task_attachments ta
     JOIN tasks t ON ta.task_id = t.id
     JOIN classes c ON t.class_id = c.id
     LEFT JOIN materials m ON ta.material_id = m.id
     WHERE ta.id = ?"
);
$stmt->bind_param('i', $attachment_id);
$stmt->execute();
$attachment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$attachment) {
    die(t('file_not_found_server'));
}

$allowed = $role === 'admin'
    || ($role === 'dosen' && (int)$attachment['dosen_id'] === (int)$user['id']);
if (!$allowed && $role === 'mahasiswa') {
    $member = $conn->prepare('SELECT 1 FROM class_members WHERE class_id = ? AND mahasiswa_id = ?');
    $member->bind_param('ii', $attachment['class_id'], $user['id']);
    $member->execute();
    $allowed = $member->get_result()->num_rows > 0;
    $member->close();
}
if (!$allowed) {
    die(t('access_denied_class'));
}

$full_path = storageAbsolutePath($attachment['resolved_path']);
if (!$attachment['resolved_path'] || !$full_path || !is_file($full_path)) {
    error_log('[task-attachment-preview-missing] attachment_id=' . $attachment_id . ' stored_path=' . ($attachment['resolved_path'] ?? 'null') . ' resolved_path=' . ($full_path ?? 'null'));
    die(t('file_not_found_server'));
}

$disposition = isset($_GET['download']) ? 'attachment' : 'inline';
$safe_name = str_replace(["\r", "\n", '"'], '', basename($attachment['resolved_name']));
header('Content-Type: ' . $attachment['resolved_mime']);
header('Content-Disposition: ' . $disposition . '; filename="' . $safe_name . '"');
header('Content-Length: ' . filesize($full_path));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
readfile($full_path);
exit;
