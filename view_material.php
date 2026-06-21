<?php
include 'includes/db.php';
include 'includes/lang.php';
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }

$user = $_SESSION['user'];
$role = $user['role'];
$material_id = (int)($_GET['material_id'] ?? 0);

if ($material_id <= 0) {
    die(t('invalid_parameter'));
}

$stmt = $conn->prepare("
    SELECT m.*, c.dosen_id
    FROM materials m
    JOIN classes c ON m.class_id = c.id
    WHERE m.id = ?
");
$stmt->bind_param("i", $material_id);
$stmt->execute();
$material = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$material) {
    die(t('material_not_found'));
}

$allowed = false;
if ($role === 'admin') {
    $allowed = true;
} elseif ($role === 'dosen' && (int)$material['dosen_id'] === (int)$user['id']) {
    $allowed = true;
} elseif ($role === 'mahasiswa') {
    $stmt_cek = $conn->prepare("SELECT 1 FROM class_members WHERE class_id = ? AND mahasiswa_id = ?");
    $stmt_cek->bind_param("ii", $material['class_id'], $user['id']);
    $stmt_cek->execute();
    $allowed = $stmt_cek->get_result()->num_rows > 0;
    $stmt_cek->close();
}

if (!$allowed) {
    die(t('access_denied_class'));
}

$full_path = __DIR__ . '/' . $material['file_path'];
if (!file_exists($full_path)) {
    die(t('file_not_found_server'));
}

$mime_type = $material['mime_type'] ?: 'application/octet-stream';
header('Content-Type: ' . $mime_type);
header('Content-Disposition: inline; filename="' . $material['original_name'] . '"');
header('Content-Length: ' . filesize($full_path));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($full_path);
exit;
