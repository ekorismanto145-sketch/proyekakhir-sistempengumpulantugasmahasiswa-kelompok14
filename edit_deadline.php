<?php
include 'includes/db.php';
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }
$user = $_SESSION['user'];
$role = $user['role'];

if ($role !== 'dosen' && $role !== 'admin') {
    die("Akses ditolak.");
}

$task_id = (int)$_POST['task_id'];
$new_deadline = $_POST['deadline'] ?? '';

if (!$task_id || empty($new_deadline)) {
    header("Location: index.php");
    exit();
}

$new_deadline_ts = strtotime($new_deadline);

// Ambil data tugas dan kelas
$stmt = $conn->prepare("
    SELECT t.*, c.nama_kelas, c.dosen_id, c.id as class_id
    FROM tasks t
    JOIN classes c ON t.class_id = c.id
    WHERE t.id = ?
");
$stmt->bind_param("i", $task_id);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$task) {
    die("Tugas tidak ditemukan.");
}

// Otorisasi: hanya dosen pemilik kelas atau admin
if ($role === 'dosen' && $task['dosen_id'] != $user['id']) {
    die("Anda tidak memiliki akses.");
}

if ($new_deadline_ts < time()) {
    header("Location: detail_kelas.php?id=" . $task['class_id'] . "&pesan=deadline_invalid");
    exit();
}

// Update deadline
$update = $conn->prepare("UPDATE tasks SET deadline = ? WHERE id = ?");
$update->bind_param("si", $new_deadline, $task_id);
$update->execute();
$update->close();

// Kirim notifikasi ke semua mahasiswa di kelas
$desc = "Deadline tugas '" . $task['judul'] . "' di kelas " . $task['nama_kelas'] . " telah diubah menjadi " . date('d M Y H:i', strtotime($new_deadline));
$stmt_member = $conn->prepare("SELECT mahasiswa_id FROM class_members WHERE class_id = ?");
$stmt_member->bind_param("i", $task['class_id']);
$stmt_member->execute();
$res = $stmt_member->get_result();
while ($row = $res->fetch_assoc()) {
    $ins = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'tugas')");
    $ins->bind_param("is", $row['mahasiswa_id'], $desc);
    $ins->execute();
    $ins->close();
}
$stmt_member->close();

// Notifikasi untuk dosen
$desc_dosen = "Anda telah mengubah deadline tugas '" . $task['judul'] . "' menjadi " . date('d M Y H:i', strtotime($new_deadline));
$ins_dosen = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'tugas')");
$ins_dosen->bind_param("is", $user['id'], $desc_dosen);
$ins_dosen->execute();
$ins_dosen->close();

header("Location: detail_kelas.php?id=" . $task['class_id'] . "&pesan=deadline_updated");
exit;