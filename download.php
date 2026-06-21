<?php
include 'includes/db.php';
include 'includes/lang.php';
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }
$user = $_SESSION['user'];
$role = $user['role'];

$submission_id = (int)$_GET['submission_id'];
$token = $_GET['token'] ?? '';
if ($submission_id <= 0 || empty($token)) {
    die(t('invalid_parameter'));
}

$expected_token = md5($submission_id . $user['id'] . 'secret_key');
if (!hash_equals($expected_token, $token)) {
    die(t('invalid_token'));
}

$stmt = $conn->prepare("
    SELECT ts.*, t.class_id, c.dosen_id 
    FROM task_submissions ts
    JOIN tasks t ON ts.task_id = t.id
    JOIN classes c ON t.class_id = c.id
    WHERE ts.id = ?
");
$stmt->bind_param("i", $submission_id);
$stmt->execute();
$sub = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sub) {
    die(t('submission_data_not_found'));
}

if ($role === 'dosen' && $sub['dosen_id'] != $user['id']) {
    die(t('access_denied_lecturer'));
}
if ($role !== 'admin' && $role !== 'dosen') {
    die(t('access_denied'));
}

$full_path = storageAbsolutePath($sub['file_path']);
if (!$full_path || !is_file($full_path)) {
    error_log('[submission-download-missing] submission_id=' . $submission_id . ' stored_path=' . $sub['file_path'] . ' resolved_path=' . ($full_path ?? 'null'));
    die(t('file_not_found_server'));
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $sub['original_name'] . '"');
header('Content-Length: ' . filesize($full_path));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($full_path);
exit;
