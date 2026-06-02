<?php
include 'includes/db.php';
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }
$user = $_SESSION['user'];
$role = $user['role'];

$submission_id = (int)$_GET['submission_id'];
$token = $_GET['token'] ?? '';
if ($submission_id <= 0 || empty($token)) {
    die("Parameter tidak valid.");
}

$expected_token = md5($submission_id . $user['id'] . 'secret_key');
if (!hash_equals($expected_token, $token)) {
    die("Token tidak valid.");
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
    die("Data submission tidak ditemukan.");
}

if ($role === 'dosen' && $sub['dosen_id'] != $user['id']) {
    die("Akses ditolak. Anda bukan dosen pengampu kelas ini.");
}
if ($role !== 'admin' && $role !== 'dosen') {
    die("Akses ditolak.");
}

$full_path = __DIR__ . '/' . $sub['file_path'];
if (!file_exists($full_path)) {
    die("File tidak ditemukan di server.");
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $sub['original_name'] . '"');
header('Content-Length: ' . filesize($full_path));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($full_path);
exit;