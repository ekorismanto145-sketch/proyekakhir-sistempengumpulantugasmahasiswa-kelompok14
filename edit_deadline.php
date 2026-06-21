<?php
include 'includes/db.php';
include 'includes/lang.php';
include 'includes/security_workflow.php';
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }
$user = $_SESSION['user'];
$role = $user['role'];

if ($role !== 'dosen' && $role !== 'admin') {
    die(t('access_denied'));
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
    die(t('task_not_found'));
}

// Otorisasi: hanya dosen pemilik kelas atau admin
if ($role === 'dosen' && $task['dosen_id'] != $user['id']) {
    die(t('access_denied_lecturer'));
}

if ($new_deadline_ts < time()) {
    header("Location: detail_kelas.php?id=" . $task['class_id'] . "&pesan=deadline_invalid");
    exit();
}

$override_code = trim($_POST['break_glass_code'] ?? '');
$reason = trim($_POST['override_reason'] ?? '');

if ($role === 'admin') {
    if ($override_code !== '') {
        $codeRow = verifyBreakGlassCode($conn, $task['dosen_id'], $override_code);
        if (!$codeRow) {
            header("Location: detail_kelas.php?id=" . $task['class_id'] . "&pesan=approval_code_invalid");
            exit();
        }

        $update = $conn->prepare("UPDATE tasks SET deadline = ? WHERE id = ?");
        $update->bind_param("si", $new_deadline, $task_id);
        $update->execute();
        $update->close();

        $desc = t('activity_deadline_updated_by_teacher_prefix') . "'" . $task['judul'] . "'" . t('activity_deadline_updated_by_teacher_suffix') . $task['nama_kelas'] . ' telah diubah menjadi ' . date('d M Y H:i', strtotime($new_deadline));
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

        $desc_dosen = t('activity_deadline_updated_by_you_prefix') . "'" . $task['judul'] . "' menjadi " . date('d M Y H:i', strtotime($new_deadline));
        $ins_dosen = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'tugas')");
        $ins_dosen->bind_param("is", $task['dosen_id'], $desc_dosen);
        $ins_dosen->execute();
        $ins_dosen->close();

        consumeBreakGlassCode($conn, $codeRow);
        logSecurityAction($conn, (int)$user['id'], $role, 'break_glass_update_deadline', 'tasks', (string)$task_id, $reason, ['lecturer_id' => $task['dosen_id'], 'class_id' => $task['class_id']]);
        header("Location: detail_kelas.php?id=" . $task['class_id'] . "&pesan=deadline_updated");
        exit();
    }

    $requestId = createSensitiveRequest(
        $conn,
        $user,
        (int)$task['dosen_id'],
        (int)$task['class_id'],
        'tasks',
        (string)$task_id,
        'update_deadline',
        ['deadline' => $new_deadline],
        $reason !== '' ? $reason : 'Permintaan perubahan deadline oleh admin'
    );
    logSecurityAction($conn, (int)$user['id'], $role, 'request_update_deadline', 'tasks', (string)$task_id, $reason !== '' ? $reason : 'Permintaan perubahan deadline oleh admin', ['request_id' => $requestId]);
    header("Location: detail_kelas.php?id=" . $task['class_id'] . "&pesan=approval_pending");
    exit();
}

// Update deadline for lecturer
$update = $conn->prepare("UPDATE tasks SET deadline = ? WHERE id = ?");
$update->bind_param("si", $new_deadline, $task_id);
$update->execute();
$update->close();

$desc = t('activity_deadline_updated_by_teacher_prefix') . "'" . $task['judul'] . "'" . t('activity_deadline_updated_by_teacher_suffix') . $task['nama_kelas'] . ' telah diubah menjadi ' . date('d M Y H:i', strtotime($new_deadline));
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
$desc_dosen = t('activity_deadline_updated_by_you_prefix') . "'" . $task['judul'] . "' menjadi " . date('d M Y H:i', strtotime($new_deadline));
$ins_dosen = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'tugas')");
$ins_dosen->bind_param("is", $user['id'], $desc_dosen);
$ins_dosen->execute();
$ins_dosen->close();

header("Location: detail_kelas.php?id=" . $task['class_id'] . "&pesan=deadline_updated");
exit;
