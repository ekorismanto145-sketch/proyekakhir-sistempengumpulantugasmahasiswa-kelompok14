<?php 
include 'includes/db.php'; 
include 'includes/lang.php';
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }
$user = $_SESSION['user']; 
$role = $user['role'];

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
function columnExists($conn, $table, $column) {
    $stmt = $conn->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("ss", $table, $column);
    $exists = $stmt->execute() && $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}
function uploadedFiles($field) {
    if (!isset($_FILES[$field])) {
        return [];
    }
    $input = $_FILES[$field];
    if (!is_array($input['name'])) {
        return [$input];
    }
    $files = [];
    foreach ($input['name'] as $index => $name) {
        $files[] = [
            'name' => $name,
            'type' => $input['type'][$index] ?? '',
            'tmp_name' => $input['tmp_name'][$index] ?? '',
            'error' => $input['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $input['size'][$index] ?? 0
        ];
    }
    return $files;
}
function removeStoredFiles($paths) {
    foreach (array_unique(array_filter($paths)) as $path) {
        $full_path = storageAbsolutePath($path);
        if (is_file($full_path)) {
            @unlink($full_path);
        }
    }
}

$class_id = (int)$_GET['id'];
if ($class_id <= 0) { header("Location: index.php"); exit(); }

// Ambil data kelas
$stmt = $conn->prepare("SELECT c.*, u.nama as nama_dosen FROM classes c JOIN users u ON c.dosen_id = u.id WHERE c.id = ?");
$stmt->bind_param("i", $class_id);
$stmt->execute();
$query_kelas = $stmt->get_result();
if ($query_kelas->num_rows == 0) { header("Location: index.php"); exit(); }
$kelas = $query_kelas->fetch_assoc();

// Otorisasi akses halaman
$allowed = false;
$is_owner = false;
$can_manage_materials = false;
if ($role === 'admin') {
    $allowed = true;
    $is_owner = true;
    $can_manage_materials = true;
} elseif ($role === 'dosen') {
    if ($kelas['dosen_id'] == $user['id']) {
        $allowed = true;
        $is_owner = true;
        $can_manage_materials = true;
    }
} elseif ($role === 'mahasiswa') {
    $stmt_cek = $conn->prepare("SELECT 1 FROM class_members WHERE class_id = ? AND mahasiswa_id = ?");
    $stmt_cek->bind_param("ii", $class_id, $user['id']);
    $stmt_cek->execute();
    $allowed = $stmt_cek->get_result()->num_rows > 0;
    $stmt_cek->close();
}
if (!$allowed) { die(t('access_denied_class')); }

// =========================================================
// PROSES UPLOAD MATERI (DOSEN/ADMIN)
// =========================================================
if (isset($_POST['upload_materi']) && ($role === 'dosen' || $role === 'admin')) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die(t('csrf_invalid'));
    }
    if (!$can_manage_materials) {
        die(t('unauthorized_material_upload'));
    }

    $judul_materi = trim($_POST['judul_materi'] ?? '');
    $deskripsi_materi = trim($_POST['deskripsi_materi'] ?? '');
    $max_size = 10 * 1024 * 1024;
    $allowed_mime = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    if ($judul_materi === '') {
        die(t('material_title_required'));
    }

    $material_files = array_values(array_filter(uploadedFiles('file_materi'), function ($file) {
        return ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }));
    if (!$material_files) {
        die(t('material_file_required'));
    }

    try {
        $material_directory = storageDirectory('materi');
    } catch (Throwable $e) {
        error_log('[material-storage] ' . $e->getMessage());
        header("Location: detail_kelas.php?id=$class_id&pesan=materi_gagal");
        exit();
    }

    $prepared_materials = [];
    $saved_paths = [];
    foreach ($material_files as $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            header("Location: detail_kelas.php?id=$class_id&pesan=materi_gagal");
            exit();
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime_type, $allowed_mime, true)) {
            header("Location: detail_kelas.php?id=$class_id&pesan=materi_format_salah");
            exit();
        }
        if ((int)$file['size'] > $max_size) {
            header("Location: detail_kelas.php?id=$class_id&pesan=materi_terlalu_besar");
            exit();
        }

        $original_name = basename($file['name']);
        $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION) ?: 'bin');
        $stored_name = bin2hex(random_bytes(16)) . '.' . $file_ext;
        $file_path = storageRelativePath('materi', $stored_name);
        $absolute_file_path = $material_directory . DIRECTORY_SEPARATOR . $stored_name;
        if (!move_uploaded_file($file['tmp_name'], $absolute_file_path)) {
            removeStoredFiles($saved_paths);
            header("Location: detail_kelas.php?id=$class_id&pesan=materi_gagal");
            exit();
        }
        $saved_paths[] = $file_path;
        $prepared_materials[] = [
            'path' => $file_path,
            'name' => $original_name,
            'mime' => $mime_type,
            'size' => (int)$file['size']
        ];
    }

    $conn->begin_transaction();
    try {
        $stmt_mat = $conn->prepare("INSERT INTO materials (class_id, judul, deskripsi, file_path, original_name, mime_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt_mat) {
            throw new Exception('prepare_material_failed: ' . $conn->error);
        }
        foreach ($prepared_materials as $prepared) {
            $prepared_path = $prepared['path'];
            $prepared_name = $prepared['name'];
            $prepared_mime = $prepared['mime'];
            $prepared_size = $prepared['size'];
            $uploader_id = (int)$user['id'];
            $stmt_mat->bind_param("isssssii", $class_id, $judul_materi, $deskripsi_materi, $prepared_path, $prepared_name, $prepared_mime, $prepared_size, $uploader_id);
            if (!$stmt_mat->execute()) {
                throw new Exception('insert_material_failed: ' . $stmt_mat->error);
            }
        }
        $stmt_mat->close();

        // Material storage is the primary operation; notifications must not roll it back.
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        removeStoredFiles($saved_paths);
        error_log('[material-upload] class_id=' . $class_id . ' user_id=' . $user['id'] . ' error=' . $e->getMessage());
        header("Location: detail_kelas.php?id=$class_id&pesan=materi_gagal");
        exit();
    }

    try {
        $desc_materi_mhs = t('activity_material_shared_by_teacher_prefix') . $judul_materi . ' di kelas ' . $kelas['nama_kelas'];
        $stmt_member = $conn->prepare("SELECT mahasiswa_id FROM class_members WHERE class_id = ?");
        if (!$stmt_member) {
            throw new Exception('prepare_member_failed: ' . $conn->error);
        }
        $stmt_member->bind_param("i", $class_id);
        if (!$stmt_member->execute()) {
            throw new Exception('select_members_failed: ' . $stmt_member->error);
        }
        $res_member = $stmt_member->get_result();
        while ($row = $res_member->fetch_assoc()) {
            $ins = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'materi')");
            if (!$ins) {
                throw new Exception('prepare_activity_failed: ' . $conn->error);
            }
            $ins->bind_param("is", $row['mahasiswa_id'], $desc_materi_mhs);
            if (!$ins->execute()) {
                throw new Exception('insert_activity_failed: ' . $ins->error);
            }
            $ins->close();
        }
        $stmt_member->close();

        $desc_materi_dosen = t('activity_material_shared_by_you_prefix') . $judul_materi;
        $ins_dosen = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'materi')");
        if (!$ins_dosen) {
            throw new Exception('prepare_owner_activity_failed: ' . $conn->error);
        }
        $ins_dosen->bind_param("is", $user['id'], $desc_materi_dosen);
        if (!$ins_dosen->execute()) {
            throw new Exception('insert_owner_activity_failed: ' . $ins_dosen->error);
        }
        $ins_dosen->close();
    } catch (Throwable $e) {
        error_log('[material-notification] class_id=' . $class_id . ' user_id=' . $user['id'] . ' error=' . $e->getMessage());
    }

    header("Location: detail_kelas.php?id=$class_id&pesan=materi_sukses");
    exit();
}

// =========================================================
// PROSES KELUAR KELAS (MAHASISWA)
// =========================================================
if (isset($_POST['leave_class']) && $role === 'mahasiswa') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die(t('csrf_invalid'));
    }
    $conn->begin_transaction();
    try {
        $leave = $conn->prepare("DELETE FROM class_members WHERE class_id = ? AND mahasiswa_id = ?");
        $leave->bind_param("ii", $class_id, $user['id']);
        if (!$leave->execute() || $leave->affected_rows !== 1) {
            throw new Exception('leave_class_failed: ' . $leave->error);
        }
        $leave->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('[leave-class] class_id=' . $class_id . ' user_id=' . $user['id'] . ' error=' . $e->getMessage());
        header("Location: detail_kelas.php?id=$class_id&pesan=class_action_failed");
        exit();
    }
    header("Location: index.php?pesan=keluar_kelas");
    exit();
}

// =========================================================
// PROSES HAPUS MATERI / TUGAS (DOSEN/ADMIN)
// =========================================================
if (isset($_POST['delete_material']) && $can_manage_materials) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die(t('csrf_invalid'));
    }
    $material_id = (int)($_POST['material_id'] ?? 0);
    $find_material = $conn->prepare("SELECT file_path FROM materials WHERE id = ? AND class_id = ?");
    $find_material->bind_param("ii", $material_id, $class_id);
    $find_material->execute();
    $material_to_delete = $find_material->get_result()->fetch_assoc();
    $find_material->close();
    if (!$material_to_delete) {
        header("Location: detail_kelas.php?id=$class_id&pesan=delete_failed");
        exit();
    }
    $delete_material = $conn->prepare("DELETE FROM materials WHERE id = ? AND class_id = ?");
    $delete_material->bind_param("ii", $material_id, $class_id);
    if (!$delete_material->execute()) {
        error_log('[delete-material] material_id=' . $material_id . ' error=' . $delete_material->error);
        $delete_material->close();
        header("Location: detail_kelas.php?id=$class_id&pesan=delete_failed");
        exit();
    }
    $delete_material->close();
    removeStoredFiles([$material_to_delete['file_path']]);
    header("Location: detail_kelas.php?id=$class_id&pesan=material_deleted");
    exit();
}

if (isset($_POST['delete_task']) && $is_owner) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die(t('csrf_invalid'));
    }
    $task_id = (int)($_POST['task_id'] ?? 0);
    $task_files = [];
    $find_task = $conn->prepare("SELECT material_file_path FROM tasks WHERE id = ? AND class_id = ?");
    $find_task->bind_param("ii", $task_id, $class_id);
    $find_task->execute();
    $task_to_delete = $find_task->get_result()->fetch_assoc();
    $find_task->close();
    if (!$task_to_delete) {
        header("Location: detail_kelas.php?id=$class_id&pesan=delete_failed");
        exit();
    }
    $task_files[] = $task_to_delete['material_file_path'];
    $attachment_files = $conn->prepare("SELECT file_path FROM task_attachments WHERE task_id = ? AND file_path IS NOT NULL");
    $attachment_files->bind_param('i', $task_id);
    $attachment_files->execute();
    foreach ($attachment_files->get_result() as $row) { $task_files[] = $row['file_path']; }
    $attachment_files->close();
    $submission_files = $conn->prepare("SELECT file_path FROM task_submissions WHERE task_id = ?");
    $submission_files->bind_param('i', $task_id);
    $submission_files->execute();
    foreach ($submission_files->get_result() as $row) { $task_files[] = $row['file_path']; }
    $submission_files->close();

    $delete_task = $conn->prepare("DELETE FROM tasks WHERE id = ? AND class_id = ?");
    $delete_task->bind_param("ii", $task_id, $class_id);
    if (!$delete_task->execute()) {
        error_log('[delete-task] task_id=' . $task_id . ' error=' . $delete_task->error);
        $delete_task->close();
        header("Location: detail_kelas.php?id=$class_id&pesan=delete_failed");
        exit();
    }
    $delete_task->close();
    removeStoredFiles($task_files);
    header("Location: detail_kelas.php?id=$class_id&pesan=task_deleted");
    exit();
}

// =========================================================
// PROSES HAPUS KELAS (DOSEN/ADMIN) - DENGAN NOTIFIKASI
// =========================================================
if (isset($_POST['delete_class']) && $is_owner) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die(t('csrf_invalid'));
    }

    $class_files = [];
    $file_queries = [
        "SELECT file_path FROM materials WHERE class_id = ?",
        "SELECT ta.file_path FROM task_attachments ta JOIN tasks t ON ta.task_id = t.id WHERE t.class_id = ? AND ta.file_path IS NOT NULL",
        "SELECT material_file_path AS file_path FROM tasks WHERE class_id = ? AND material_file_path IS NOT NULL",
        "SELECT ts.file_path FROM task_submissions ts JOIN tasks t ON ts.task_id = t.id WHERE t.class_id = ?"
    ];
    foreach ($file_queries as $file_query) {
        $file_stmt = $conn->prepare($file_query);
        $file_stmt->bind_param('i', $class_id);
        $file_stmt->execute();
        foreach ($file_stmt->get_result() as $file_row) { $class_files[] = $file_row['file_path']; }
        $file_stmt->close();
    }

    // 1. Ambil semua mahasiswa anggota kelas ini
    $memberStmt = $conn->prepare("SELECT mahasiswa_id FROM class_members WHERE class_id = ?");
    $memberStmt->bind_param("i", $class_id);
    $memberStmt->execute();
    $members = $memberStmt->get_result();
    $memberIds = [];
    while ($row = $members->fetch_assoc()) {
        $memberIds[] = $row['mahasiswa_id'];
    }
    $memberStmt->close();

    // 2. Buat pesan notifikasi
    $desc = t('activity_class_deleted_prefix') . "'" . $kelas['nama_kelas'] . "'" . t('activity_class_deleted_suffix') . ($role === 'admin' ? t('admin_role') : $user['nama']);

    $conn->begin_transaction();
    try {
        // Hapus kelas lebih dulu; kegagalan notifikasi tidak boleh membatalkan aksi utama.
        $del = $conn->prepare("DELETE FROM classes WHERE id = ?");
        $del->bind_param("i", $class_id);
        if (!$del->execute() || $del->affected_rows !== 1) {
            throw new Exception('delete_class_failed: ' . $del->error);
        }
        $del->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('[delete-class] class_id=' . $class_id . ' user_id=' . $user['id'] . ' error=' . $e->getMessage());
        header("Location: detail_kelas.php?id=$class_id&pesan=class_action_failed");
        exit();
    }

    removeStoredFiles($class_files);

    try {
        foreach ($memberIds as $mhs_id) {
            $ins = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'kelas_baru')");
            $ins->bind_param("is", $mhs_id, $desc);
            if (!$ins->execute()) { throw new Exception('notify_member_failed: ' . $ins->error); }
            $ins->close();
        }
        $insOwner = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'kelas_baru')");
        $owner_id = (int)$user['id'];
        $insOwner->bind_param("is", $owner_id, $desc);
        if (!$insOwner->execute()) { throw new Exception('notify_owner_failed: ' . $insOwner->error); }
        $insOwner->close();
    } catch (Throwable $e) {
        error_log('[delete-class-notification] class_id=' . $class_id . ' error=' . $e->getMessage());
    }

    header("Location: index.php?pesan=kelas_dihapus");
    exit();
}

// =========================================================
// PROSES BUAT TUGAS
// =========================================================
if (isset($_POST['buat_tugas']) && ($role === 'dosen' || $role === 'admin')) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die(t('csrf_invalid'));
    }
    if ($role === 'dosen' && $kelas['dosen_id'] != $user['id']) {
        die(t('unauthorized_task_create'));
    }
    $judul = trim($_POST['judul']);
    $deskripsi = trim($_POST['deskripsi']);
    $deadline = $_POST['deadline'];
    $deadline_ts = strtotime($deadline);
    if ($deadline_ts === false || $deadline_ts < time()) {
        header("Location: detail_kelas.php?id=$class_id&pesan=deadline_invalid");
        exit();
    }

    $task_material_mode = $_POST['task_material_mode'] ?? 'upload_baru';
    $task_file_path = null;
    $task_file_name = null;
    $existing_material_id = null;
    $task_attachments = [];
    $saved_task_paths = [];

    if ($task_material_mode === 'existing') {
        $requested_material_ids = array_values(array_unique(array_filter(array_map('intval', $_POST['existing_material_ids'] ?? []))));
        if (!$requested_material_ids) {
            header("Location: detail_kelas.php?id=$class_id&pesan=task_material_required");
            exit();
        }
        $stmt_material = $conn->prepare("SELECT id, original_name, mime_type, file_size FROM materials WHERE id = ? AND class_id = ?");
        foreach ($requested_material_ids as $requested_material_id) {
            $stmt_material->bind_param("ii", $requested_material_id, $class_id);
            $stmt_material->execute();
            $valid_material = $stmt_material->get_result()->fetch_assoc();
            if (!$valid_material) {
                $stmt_material->close();
                header("Location: detail_kelas.php?id=$class_id&pesan=task_material_required");
                exit();
            }
            $task_attachments[] = [
                'material_id' => (int)$valid_material['id'],
                'path' => null,
                'name' => $valid_material['original_name'],
                'mime' => $valid_material['mime_type'],
                'size' => (int)$valid_material['file_size']
            ];
        }
        $stmt_material->close();
        $existing_material_id = $task_attachments[0]['material_id'];
    } elseif ($task_material_mode === 'upload_baru') {
        $task_files = array_values(array_filter(uploadedFiles('task_material_files'), function ($file) {
            return ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        }));
        if (!$task_files) {
            header("Location: detail_kelas.php?id=$class_id&pesan=task_material_required");
            exit();
        }
        $allowed_task_mime = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        try {
            $task_directory = storageDirectory('task_materials');
        } catch (Throwable $e) {
            error_log('[task-material-storage] ' . $e->getMessage());
            header("Location: detail_kelas.php?id=$class_id&pesan=task_material_failed");
            exit();
        }
        foreach ($task_files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                removeStoredFiles($saved_task_paths);
                header("Location: detail_kelas.php?id=$class_id&pesan=task_material_failed");
                exit();
            }
            $task_finfo = finfo_open(FILEINFO_MIME_TYPE);
            $task_mime_type = finfo_file($task_finfo, $file['tmp_name']);
            finfo_close($task_finfo);
            if ((int)$file['size'] > 10 * 1024 * 1024 || !in_array($task_mime_type, $allowed_task_mime, true)) {
                removeStoredFiles($saved_task_paths);
                header("Location: detail_kelas.php?id=$class_id&pesan=task_material_invalid");
                exit();
            }
            $original_task_name = basename($file['name']);
            $task_extension = strtolower(pathinfo($original_task_name, PATHINFO_EXTENSION) ?: 'bin');
            $stored_task_name = bin2hex(random_bytes(16)) . '.' . $task_extension;
            $stored_task_path = storageRelativePath('task_materials', $stored_task_name);
            $absolute_task_path = $task_directory . DIRECTORY_SEPARATOR . $stored_task_name;
            if (!move_uploaded_file($file['tmp_name'], $absolute_task_path)) {
                removeStoredFiles($saved_task_paths);
                header("Location: detail_kelas.php?id=$class_id&pesan=task_material_failed");
                exit();
            }
            $saved_task_paths[] = $stored_task_path;
            $task_attachments[] = [
                'material_id' => null,
                'path' => $stored_task_path,
                'name' => $original_task_name,
                'mime' => $task_mime_type,
                'size' => (int)$file['size']
            ];
        }
        $task_file_path = $task_attachments[0]['path'];
        $task_file_name = $task_attachments[0]['name'];
    } else {
        header("Location: detail_kelas.php?id=$class_id&pesan=task_material_required");
        exit();
    }

    $conn->begin_transaction();
    try {
        $stmt2 = $conn->prepare("INSERT INTO tasks (class_id, judul, deskripsi, deadline) VALUES (?, ?, ?, ?)");
        $stmt2->bind_param("isss", $class_id, $judul, $deskripsi, $deadline);
        if (!$stmt2->execute()) {
            throw new Exception('insert_task_failed: ' . $stmt2->error);
        }
        $task_id = $stmt2->insert_id;
        $stmt2->close();

        $stmt_meta = $conn->prepare("UPDATE tasks SET material_source_type = ?, material_file_path = ?, material_original_name = ?, material_reference_id = ? WHERE id = ?");
        $stmt_meta->bind_param("sssii", $task_material_mode, $task_file_path, $task_file_name, $existing_material_id, $task_id);
        if (!$stmt_meta->execute()) {
            throw new Exception('update_task_material_failed: ' . $stmt_meta->error);
        }
        $stmt_meta->close();

        $stmt_attachment = $conn->prepare("INSERT INTO task_attachments (task_id, material_id, file_path, original_name, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($task_attachments as $attachment) {
            $attachment_material_id = $attachment['material_id'];
            $attachment_path = $attachment['path'];
            $attachment_name = $attachment['name'];
            $attachment_mime = $attachment['mime'];
            $attachment_size = $attachment['size'];
            $stmt_attachment->bind_param("iisssi", $task_id, $attachment_material_id, $attachment_path, $attachment_name, $attachment_mime, $attachment_size);
            if (!$stmt_attachment->execute()) {
                throw new Exception('insert_task_attachment_failed: ' . $stmt_attachment->error);
            }
        }
        $stmt_attachment->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        removeStoredFiles($saved_task_paths);
        error_log('[task-create] class_id=' . $class_id . ' user_id=' . $user['id'] . ' error=' . $e->getMessage());
        header("Location: detail_kelas.php?id=$class_id&pesan=task_create_failed");
        exit();
    }
    
    $desc_mahasiswa = t('activity_task_shared_by_teacher_prefix') . $judul . ' di kelas ' . $kelas['nama_kelas'];
    $stmt_member = $conn->prepare("SELECT mahasiswa_id FROM class_members WHERE class_id = ?");
    $stmt_member->bind_param("i", $class_id);
    $stmt_member->execute();
    $res_member = $stmt_member->get_result();
    while ($row = $res_member->fetch_assoc()) {
        $ins = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'tugas')");
        $ins->bind_param("is", $row['mahasiswa_id'], $desc_mahasiswa);
        $ins->execute();
        $ins->close();
    }
    $stmt_member->close();
    $desc_dosen = t('activity_task_shared_by_you_prefix') . $judul;
    $ins_dosen = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'tugas')");
    $ins_dosen->bind_param("is", $user['id'], $desc_dosen);
    $ins_dosen->execute();
    $ins_dosen->close();
    
    header("Location: detail_kelas.php?id=$class_id&pesan=tugas_dibuat");
    exit();
}

// =========================================================
// AMBIL DATA MATERI
// =========================================================
$materials = [];
$stmt_materi = $conn->prepare("SELECT m.*, u.nama as uploader_nama FROM materials m JOIN users u ON m.uploaded_by = u.id WHERE m.class_id = ? ORDER BY m.created_at DESC");
$stmt_materi->bind_param("i", $class_id);
$stmt_materi->execute();
$res_materi = $stmt_materi->get_result();
while ($m = $res_materi->fetch_assoc()) {
    $materials[] = $m;
}
$stmt_materi->close();

$tasks = [];
$task_attachments = [];
$stmt_tugas = $conn->prepare("SELECT * FROM tasks WHERE class_id = ? ORDER BY created_at DESC");
$stmt_tugas->bind_param('i', $class_id);
$stmt_tugas->execute();
foreach ($stmt_tugas->get_result() as $task_row) {
    $task_row['attachments'] = [];
    $tasks[(int)$task_row['id']] = $task_row;
}
$stmt_tugas->close();

$stmt_attachments = $conn->prepare(
    "SELECT ta.id, ta.task_id, ta.material_id,
            COALESCE(NULLIF(ta.original_name, ''), m.original_name) AS original_name,
            COALESCE(ta.mime_type, m.mime_type, 'application/octet-stream') AS mime_type,
            COALESCE(ta.file_size, m.file_size, 0) AS file_size
     FROM task_attachments ta
     JOIN tasks t ON ta.task_id = t.id
     LEFT JOIN materials m ON ta.material_id = m.id
     WHERE t.class_id = ?
     ORDER BY ta.created_at ASC, ta.id ASC"
);
$stmt_attachments->bind_param('i', $class_id);
$stmt_attachments->execute();
foreach ($stmt_attachments->get_result() as $attachment_row) {
    $task_id_for_attachment = (int)$attachment_row['task_id'];
    if (isset($tasks[$task_id_for_attachment])) {
        $tasks[$task_id_for_attachment]['attachments'][] = $attachment_row;
    }
}
$stmt_attachments->close();

include 'includes/header.php'; 
include 'includes/navbar.php'; 
?>

<main class="max-w-5xl mx-auto p-4 md:p-8">
    <?php if (isset($_GET['pesan']) && in_array($_GET['pesan'], ['material_deleted', 'task_deleted'], true)): ?>
        <div class="mb-4 px-4 py-3 rounded-xl border bg-green-500/20 border-green-500 text-green-300">
            <i class="fas fa-check-circle mr-2"></i> <?= t($_GET['pesan']) ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['pesan']) && in_array($_GET['pesan'], ['delete_failed', 'class_action_failed'], true)): ?>
        <div class="mb-4 px-4 py-3 rounded-xl border bg-red-500/20 border-red-500 text-red-300">
            <i class="fas fa-exclamation-circle mr-2"></i> <?= t($_GET['pesan']) ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['pesan']) && $_GET['pesan'] === 'tugas_dibuat'): ?>
        <div class="mb-4 px-4 py-3 rounded-xl border bg-green-500/20 border-green-500 text-green-300">
            <i class="fas fa-check-circle mr-2"></i> <?= t('task_created_success') ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['pesan']) && in_array($_GET['pesan'], ['task_material_required', 'task_material_invalid', 'task_material_failed', 'task_create_failed'], true)): ?>
        <div class="mb-4 px-4 py-3 rounded-xl border bg-red-500/20 border-red-500 text-red-300">
            <i class="fas fa-exclamation-circle mr-2"></i> <?= t($_GET['pesan']) ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['pesan']) && $_GET['pesan'] === 'deadline_invalid'): ?>
        <div class="mb-4 px-4 py-3 rounded-xl border bg-red-500/20 border-red-500 text-red-300">
            <i class="fas fa-exclamation-circle mr-2"></i> <?= t('task_deadline_invalid') ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['pesan']) && $_GET['pesan'] === 'approval_pending'): ?>
        <div class="mb-4 px-4 py-3 rounded-xl border bg-yellow-500/20 border-yellow-500 text-yellow-300">
            <i class="fas fa-shield-alt mr-2"></i> Permintaan sensitif sudah dikirim ke dosen untuk persetujuan.
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['pesan']) && $_GET['pesan'] === 'approval_code_invalid'): ?>
        <div class="mb-4 px-4 py-3 rounded-xl border bg-red-500/20 border-red-500 text-red-300">
            <i class="fas fa-times-circle mr-2"></i> Break-glass code tidak valid atau sudah kedaluwarsa.
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['pesan']) && $_GET['pesan'] === 'materi_sukses'): ?>
        <div class="mb-4 px-4 py-3 rounded-xl border bg-green-500/20 border-green-500 text-green-300">
            <i class="fas fa-check-circle mr-2"></i> <?= t('material_uploaded_success') ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['pesan']) && $_GET['pesan'] === 'materi_gagal'): ?>
        <div class="mb-4 px-4 py-3 rounded-xl border bg-red-500/20 border-red-500 text-red-300">
            <i class="fas fa-exclamation-circle mr-2"></i> <?= t('material_upload_failed') ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['pesan']) && $_GET['pesan'] === 'materi_format_salah'): ?>
        <div class="mb-4 px-4 py-3 rounded-xl border bg-orange-500/20 border-orange-500 text-orange-300">
            <i class="fas fa-exclamation-triangle mr-2"></i> <?= t('material_format_rejected') ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['pesan']) && $_GET['pesan'] === 'materi_terlalu_besar'): ?>
        <div class="mb-4 px-4 py-3 rounded-xl border bg-red-500/20 border-red-500 text-red-300">
            <i class="fas fa-exclamation-triangle mr-2"></i> <?= t('material_too_large') ?>
        </div>
    <?php endif; ?>

    <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-4 gap-3">
        <a href="index.php" class="inline-flex items-center text-sm text-gray-400 hover:text-white transition">
            <i class="fas fa-arrow-left mr-2"></i> <?= t('back_to_dashboard') ?>
        </a>
        <?php if ($is_owner): ?>
            <div class="flex gap-2 items-center flex-wrap">
                <a href="edit_kelas.php?id=<?= $class_id ?>" class="px-3 py-1.5 bg-yellow-600 hover:bg-yellow-500 text-white text-xs font-bold rounded-lg transition">
                    <i class="fas fa-edit mr-1"></i> <?= t('edit_kelas') ?>
                </a>
                <form method="POST" class="inline" onsubmit="return confirm('<?= t('delete_class_confirm') ?>')">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                    <button type="submit" name="delete_class" class="px-3 py-1.5 bg-red-600 hover:bg-red-500 text-white text-xs font-bold rounded-lg transition">
                        <i class="fas fa-trash-alt mr-1"></i> <?= t('hapus_kelas') ?>
                    </button>
                </form>
            </div>
        <?php elseif ($role === 'mahasiswa'): ?>
            <form method="POST" onsubmit="return confirm('<?= t('leave_class_confirm') ?>')">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                <button type="submit" name="leave_class" class="px-3 py-1.5 bg-red-600/50 hover:bg-red-600 text-white text-xs font-bold rounded-lg transition">
                    <i class="fas fa-sign-out-alt mr-1"></i> <?= t('keluar_kelas') ?>
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="bg-blue-600 rounded-3xl p-6 md:p-10 shadow-2xl mb-8 relative overflow-hidden flex flex-col md:flex-row justify-between items-center gap-4">
        <div class="relative z-10 text-center md:text-left mb-6 md:mb-0">
            <h1 class="text-2xl sm:text-3xl md:text-5xl font-extrabold text-white mb-2 break-words"><?= htmlspecialchars($kelas['nama_kelas']); ?></h1>
            <p class="text-blue-200 text-sm sm:text-base md:text-lg break-words"><?= htmlspecialchars($kelas['deskripsi']); ?> • <?= t('lecturer_label') ?>: <?= htmlspecialchars($kelas['nama_dosen']); ?></p>
        </div>
        <div class="relative z-10 bg-darkbg/50 backdrop-blur-md border border-white/20 p-4 sm:p-5 rounded-2xl text-center w-full md:w-auto min-w-0 md:min-w-[200px]">
            <p class="text-blue-200 text-xs font-bold uppercase tracking-widest mb-1"><?= t('join_class_code') ?></p>
            <p class="text-2xl sm:text-3xl font-mono font-bold text-white tracking-widest select-all cursor-pointer break-all"><?= htmlspecialchars($kelas['kode_kelas']); ?></p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-6">
            <div id="materials" class="bg-surface border border-gray-800 rounded-2xl p-5 sm:p-6 shadow-xl">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h3 class="text-xl font-bold text-white border-b border-gray-800 pb-3 w-full"><i class="fas fa-book-open mr-2 text-green-500"></i> <?= t('materials_title') ?></h3>
                </div>
                <?php if (count($materials) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach ($materials as $materi): ?>
                            <div class="bg-darkbg border border-gray-800 rounded-2xl p-4">
                                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                                    <div class="min-w-0">
                                        <h4 class="text-lg font-bold text-white break-words"><?= htmlspecialchars($materi['judul']) ?></h4>
                                        <p class="text-sm text-gray-400 mt-1 break-words"><?= nl2br(htmlspecialchars($materi['deskripsi'] ?? '')) ?></p>
                                        <div class="flex flex-wrap gap-2 mt-3 text-xs">
                                            <span class="px-2.5 py-1 rounded-lg border border-gray-700 text-gray-300 bg-surface/80">
                                                <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($materi['uploader_nama']) ?>
                                            </span>
                                            <span class="px-2.5 py-1 rounded-lg border border-gray-700 text-gray-300 bg-surface/80">
                                                <i class="fas fa-file mr-1"></i> <?= strtoupper(pathinfo($materi['original_name'], PATHINFO_EXTENSION)) ?>
                                            </span>
                                            <span class="px-2.5 py-1 rounded-lg border border-gray-700 text-gray-300 bg-surface/80">
                                                <i class="fas fa-weight-hanging mr-1"></i> <?= $materi['file_size'] ? number_format($materi['file_size'] / 1024, 1) . ' KB' : '-' ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" onclick='openMaterialPreview(<?= (int)$materi["id"] ?>, <?= json_encode($materi["judul"] ?? "") ?>, <?= json_encode($materi["mime_type"] ?? "application/octet-stream") ?>, <?= json_encode($materi["file_path"] ?? "") ?>)' class="px-3 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-lg transition whitespace-nowrap">
                                            <i class="fas fa-eye mr-1"></i> <?= t('preview') ?>
                                        </button>
                                        <a href="view_material.php?material_id=<?= (int)$materi['id'] ?>&download=1" class="px-3 py-2 bg-green-600 hover:bg-green-500 text-white text-xs font-bold rounded-lg transition whitespace-nowrap">
                                            <i class="fas fa-download mr-1"></i> <?= t('download') ?>
                                        </a>
                                        <?php if ($can_manage_materials): ?>
                                            <form method="POST" onsubmit="return confirm('<?= t('delete_material_confirm') ?>')">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                                                <input type="hidden" name="material_id" value="<?= (int)$materi['id'] ?>">
                                                <button type="submit" name="delete_material" class="px-3 py-2 bg-red-600/80 hover:bg-red-500 text-white text-xs font-bold rounded-lg transition whitespace-nowrap">
                                                    <i class="fas fa-trash-alt mr-1"></i> <?= t('delete') ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                        <div class="bg-darkbg border border-dashed border-gray-700 rounded-2xl p-8 text-center text-gray-500">
                        <?= t('no_materials_yet') ?>
                    </div>
                <?php endif; ?>
            </div>

            <h3 class="text-xl font-bold text-white border-b border-gray-800 pb-3"><i class="fas fa-tasks mr-2 text-blue-500"></i> <?= t('task_class_title') ?></h3>
            <?php if ($tasks): ?>
                <?php foreach ($tasks as $tugas):
                    $deadline_time = strtotime($tugas['deadline']);
                    $is_expired = ($deadline_time < time());
                    $attachment_count = count($tugas['attachments']);
                ?>
                <article role="button" tabindex="0" onclick="openTaskDetail(<?= (int)$tugas['id'] ?>)" onkeydown="if(event.key === 'Enter' || event.key === ' '){ event.preventDefault(); openTaskDetail(<?= (int)$tugas['id'] ?>); }" class="group bg-surface border border-gray-800 rounded-2xl p-5 sm:p-6 shadow-lg hover:border-blue-500/70 hover:-translate-y-0.5 transition cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <div class="flex flex-col md:flex-row justify-between md:items-start mb-3 gap-3">
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-[0.2em] font-bold text-blue-400 mb-1"><?= t('task_glance_label') ?></p>
                            <h4 class="text-lg font-bold text-white group-hover:text-blue-300 transition break-words"><?= htmlspecialchars($tugas['judul']) ?></h4>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <?php if ($is_expired): ?>
                                <span class="px-3 py-1 bg-red-500/10 text-red-400 border border-red-500/50 text-xs font-bold rounded-lg">
                                    <i class="fas fa-clock mr-1.5"></i> <?= date('d M Y, H:i', $deadline_time) ?>
                                </span>
                            <?php else: ?>
                                <span class="px-3 py-1 bg-blue-500/10 text-blue-400 border border-blue-500/50 text-xs font-bold rounded-lg">
                                    <i class="fas fa-clock mr-1.5"></i> <?= date('d M Y, H:i', $deadline_time) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($is_owner): ?>
                                <button type="button" onclick="event.stopPropagation(); openDeadlineModal(<?= $tugas['id'] ?>, '<?= date('Y-m-d\TH:i', $deadline_time) ?>')"
                                        class="text-xs bg-yellow-600 hover:bg-yellow-500 px-2 py-1 rounded text-white">
                                    <i class="fas fa-edit mr-1"></i> <?= t('edit_deadline') ?>
                                </button>
                                <a onclick="event.stopPropagation()" href="lihat_pengumpulan.php?task_id=<?= $tugas['id'] ?>" class="text-xs bg-green-600 hover:bg-green-500 px-2 py-1 rounded text-white whitespace-nowrap">
                                    <i class="fas fa-eye mr-1"></i> <?= t('view_submissions') ?>
                                </a>
                                <form method="POST" onclick="event.stopPropagation()" onsubmit="event.stopPropagation(); return confirm('<?= t('delete_task_confirm') ?>')">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                                    <input type="hidden" name="task_id" value="<?= (int)$tugas['id'] ?>">
                                    <button type="submit" name="delete_task" class="text-xs bg-red-600/80 hover:bg-red-500 px-2 py-1 rounded text-white">
                                        <i class="fas fa-trash-alt mr-1"></i> <?= t('delete') ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <p class="text-sm text-gray-400 mb-4 line-clamp-2"><?= htmlspecialchars($tugas['deskripsi']) ?></p>
                    <div class="flex flex-wrap items-center gap-2 mb-4">
                        <span class="px-2.5 py-1 rounded-lg border border-gray-700 bg-darkbg text-xs text-gray-300">
                            <i class="fas fa-paperclip mr-1 text-blue-400"></i> <?= $attachment_count ?> <?= t('files_attached') ?>
                        </span>
                        <?php foreach (array_slice($tugas['attachments'], 0, 2) as $attachment): ?>
                            <span class="max-w-[180px] truncate px-2.5 py-1 rounded-lg bg-blue-500/10 border border-blue-500/20 text-xs text-blue-300" title="<?= htmlspecialchars($attachment['original_name']) ?>">
                                <?= htmlspecialchars($attachment['original_name']) ?>
                            </span>
                        <?php endforeach; ?>
                        <?php if ($attachment_count > 2): ?>
                            <span class="text-xs text-gray-500">+<?= $attachment_count - 2 ?> <?= t('more_files') ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if($role === 'mahasiswa'): ?>
                        <a onclick="event.stopPropagation()" href="tugas.php" class="inline-block px-4 py-2 bg-darkbg border border-gray-600 hover:border-blue-500 text-gray-300 hover:text-white text-sm font-bold rounded-lg transition">
                            <i class="fas fa-upload mr-2"></i> <?= t('kumpulkan_tugas_ini') ?>
                        </a>
                    <?php endif; ?>
                    <span class="float-right text-xs font-bold text-blue-400 group-hover:text-blue-300"><?= t('view_task_detail') ?> <i class="fas fa-arrow-right ml-1"></i></span>
                </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-surface border border-dashed border-gray-700 rounded-2xl p-8 text-center text-gray-500"><?= t('no_tasks_in_class') ?></div>
            <?php endif; ?>
        </div>

        <?php if($can_manage_materials): ?>
        <div>
            <div class="bg-surface border border-gray-800 rounded-2xl p-5 sm:p-6 shadow-xl md:sticky md:top-24 space-y-3">
                <button type="button" onclick="openMaterialModal()" class="w-full bg-green-600 hover:bg-green-500 text-white font-bold py-3 rounded-lg transition">
                    <i class="fas fa-upload mr-2"></i> <?= t('upload_material') ?>
                </button>
                <button type="button" onclick="openTaskModal()" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-lg transition">
                    <i class="fas fa-plus-circle mr-2"></i> <?= t('create_new_task') ?>
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<div id="materialModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden items-center justify-center transition-opacity opacity-0">
    <div class="bg-surface border border-gray-700 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden transform scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center p-5 border-b border-gray-800 bg-darkbg">
            <h3 class="text-xl font-bold text-white flex items-center"><i class="fas fa-upload text-green-500 mr-3"></i> <?= t('upload_material') ?></h3>
            <button onclick="closeMaterialModal()" class="text-gray-500 hover:text-red-500 transition"><i class="fas fa-times text-xl"></i></button>
        </div>
        <form action="" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5"><?= t('material_title') ?></label>
                <input type="text" name="judul_materi" class="w-full bg-darkbg border border-gray-700 text-white px-3 py-2.5 rounded-lg focus:border-green-500 text-sm" required>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5"><?= t('deskripsi') ?></label>
                <textarea name="deskripsi_materi" rows="3" class="w-full bg-darkbg border border-gray-700 text-white px-3 py-2.5 rounded-lg focus:border-green-500 text-sm resize-none"></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5"><?= t('pdf_word') ?></label>
                <input type="file" name="file_materi[]" multiple accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="w-full bg-darkbg border border-gray-700 text-white px-3 py-2.5 rounded-lg text-sm" required>
                <p class="text-[11px] text-gray-500 mt-1.5"><?= t('multiple_files_hint') ?></p>
                <div class="mt-3">
                    <div class="flex items-center justify-between text-[11px] text-gray-400 mb-1">
                        <span><?= t('upload_progress') ?></span>
                        <span id="materialProgressText">0%</span>
                    </div>
                    <div class="h-2 rounded-full bg-gray-800 overflow-hidden">
                        <div id="materialProgressBar" class="h-full w-0 bg-gradient-to-r from-green-500 to-emerald-400 transition-[width] duration-200"></div>
                    </div>
                </div>
            </div>
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
            <button type="submit" name="upload_materi" class="w-full bg-green-600 hover:bg-green-500 text-white font-bold py-3 rounded-lg transition mt-2" id="materialUploadBtn"><?= t('upload_material') ?></button>
        </form>
    </div>
</div>

<div id="taskModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden items-center justify-center transition-opacity opacity-0">
    <div class="bg-surface border border-gray-700 rounded-2xl w-full max-w-2xl shadow-2xl overflow-hidden transform scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center p-5 border-b border-gray-800 bg-darkbg">
            <h3 class="text-xl font-bold text-white flex items-center"><i class="fas fa-plus-circle text-blue-500 mr-3"></i> <?= t('create_new_task') ?></h3>
            <button onclick="closeTaskModal()" class="text-gray-500 hover:text-red-500 transition"><i class="fas fa-times text-xl"></i></button>
        </div>
        <form action="" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5"><?= t('task_title') ?></label>
                <input type="text" name="judul" class="w-full bg-darkbg border border-gray-700 text-white px-3 py-2.5 rounded-lg focus:border-blue-500 text-sm" required>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5"><?= t('task_description') ?></label>
                <textarea name="deskripsi" rows="3" class="w-full bg-darkbg border border-gray-700 text-white px-3 py-2.5 rounded-lg focus:border-blue-500 text-sm resize-none" required></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 mb-1.5"><?= t('task_deadline') ?></label>
                <input type="datetime-local" name="deadline" min="<?= date('Y-m-d\\TH:i') ?>" class="w-full bg-darkbg border border-gray-700 text-white px-3 py-2.5 rounded-lg focus:border-blue-500 text-sm" required>
            </div>
            <div class="rounded-xl border border-gray-700 bg-darkbg p-4 space-y-4">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400"><?= t('task_material_source') ?></p>
                <label class="flex items-center gap-3 text-sm text-gray-200">
                    <input type="radio" name="task_material_mode" value="upload_baru" checked onchange="toggleTaskMaterialMode()" class="accent-blue-500">
                    <span><?= t('task_material_upload_new') ?></span>
                </label>
                <label class="flex items-center gap-3 text-sm text-gray-200">
                    <input type="radio" name="task_material_mode" value="existing" onchange="toggleTaskMaterialMode()" class="accent-blue-500">
                    <span><?= t('task_material_use_existing') ?></span>
                </label>
                <div id="taskUploadWrap" class="space-y-2">
                    <label class="block text-xs font-bold text-gray-400 mb-1.5"><?= t('task_material_file') ?></label>
                    <input type="file" name="task_material_files[]" multiple accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="w-full bg-darkbg border border-gray-700 text-white px-3 py-2.5 rounded-lg text-sm">
                    <p class="text-[11px] text-gray-500"><?= t('multiple_files_hint') ?></p>
                </div>
                <div id="taskExistingWrap" class="hidden space-y-2">
                    <label class="block text-xs font-bold text-gray-400 mb-1.5"><?= t('task_material_existing') ?></label>
                    <select name="existing_material_ids[]" multiple size="5" class="w-full bg-darkbg border border-gray-700 text-white px-3 py-2.5 rounded-lg text-sm">
                        <?php foreach ($materials as $materi): ?>
                            <option value="<?= $materi['id'] ?>"><?= htmlspecialchars($materi['judul'] . ' - ' . $materi['original_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-[11px] text-gray-500"><?= t('multiple_select_hint') ?></p>
                </div>
            </div>
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
            <button type="submit" name="buat_tugas" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-lg transition mt-2"><?= t('publish_task') ?></button>
        </form>
    </div>
</div>

<div id="taskDetailModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4 transition-opacity opacity-0">
    <div class="bg-surface border border-blue-500/30 rounded-2xl w-full max-w-3xl max-h-[90vh] shadow-2xl overflow-hidden transform scale-95 transition-transform duration-300">
        <div class="flex justify-between items-start gap-4 p-5 border-b border-gray-800 bg-darkbg">
            <div class="min-w-0">
                <p class="text-[10px] uppercase tracking-[0.22em] font-bold text-blue-400 mb-1"><?= t('task_detail') ?></p>
                <h3 id="taskDetailTitle" class="text-xl font-bold text-white break-words"></h3>
            </div>
            <button type="button" onclick="closeTaskDetail()" class="text-gray-500 hover:text-red-500 transition"><i class="fas fa-times text-xl"></i></button>
        </div>
        <div class="p-5 sm:p-6 overflow-y-auto max-h-[calc(90vh-84px)] space-y-6">
            <div class="grid sm:grid-cols-[1fr_auto] gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2"><?= t('task_description') ?></p>
                    <p id="taskDetailDescription" class="text-sm leading-6 text-gray-300 whitespace-pre-wrap"></p>
                </div>
                <div class="rounded-xl border border-gray-700 bg-darkbg px-4 py-3 sm:min-w-[190px]">
                    <p class="text-[10px] uppercase tracking-wider text-gray-500 mb-1"><?= t('task_deadline') ?></p>
                    <p id="taskDetailDeadline" class="text-sm font-bold text-white"></p>
                </div>
            </div>
            <div>
                <div class="flex items-center justify-between gap-3 mb-3">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400"><i class="fas fa-paperclip mr-2 text-blue-400"></i><?= t('attached_files') ?></p>
                    <span id="taskDetailFileCount" class="text-xs text-gray-500"></span>
                </div>
                <div id="taskDetailAttachments" class="space-y-2"></div>
            </div>
        </div>
    </div>
</div>

<div id="materialPreviewModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden items-center justify-center transition-opacity opacity-0">
    <div class="bg-surface border border-gray-700 rounded-2xl w-full max-w-5xl shadow-2xl overflow-hidden transform scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center p-5 border-b border-gray-800 bg-darkbg">
            <div class="min-w-0">
                <h3 id="materialPreviewTitle" class="text-xl font-bold text-white truncate"><?= t('preview') ?></h3>
                <p class="text-xs text-gray-400 mt-1"><?= t('preview') ?> <?= t('materials_title') ?></p>
            </div>
            <button onclick="closeMaterialPreview()" class="text-gray-500 hover:text-red-500 transition"><i class="fas fa-times text-xl"></i></button>
        </div>
        <div class="bg-black">
            <iframe id="materialPreviewFrame" class="w-full h-[75vh] bg-white" src="about:blank" title="<?= t('preview') ?>"></iframe>
        </div>
        <div class="p-4 border-t border-gray-800 bg-darkbg flex justify-end gap-3">
            <a id="materialPreviewDownload" href="#" download class="px-4 py-2 rounded-lg bg-green-600 hover:bg-green-500 text-white font-bold transition">
                <i class="fas fa-download mr-2"></i> <?= t('download') ?>
            </a>
        </div>
    </div>
</div>

<!-- Modal Edit Deadline -->
<div id="deadlineModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden items-center justify-center transition-opacity opacity-0">
    <div class="bg-surface border border-gray-700 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden transform scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center p-5 border-b border-gray-800 bg-darkbg">
            <h3 class="text-xl font-bold text-white flex items-center"><i class="fas fa-calendar-alt text-yellow-500 mr-3"></i> <?= t('edit_deadline') ?></h3>
            <button onclick="closeDeadlineModal()" class="text-gray-500 hover:text-red-500 transition"><i class="fas fa-times text-xl"></i></button>
        </div>
        <form action="edit_deadline.php" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="task_id" id="deadline_task_id">
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2"><?= t('deadline_new') ?></label>
                <input type="datetime-local" name="deadline" id="deadline_date" min="<?= date('Y-m-d\\TH:i') ?>" class="w-full bg-darkbg border border-gray-700 text-white px-4 py-3 rounded-xl focus:border-yellow-500 transition" required>
            </div>
            <?php if ($role === 'admin'): ?>
                <div class="space-y-2">
                    <p class="text-xs text-gray-400">Admin bisa ajukan approval ke dosen atau pakai break-glass code yang diberikan dosen.</p>
                    <input type="text" name="break_glass_code" placeholder="Break-glass code (opsional)" class="w-full bg-darkbg border border-gray-700 text-white px-4 py-3 rounded-xl focus:border-yellow-500 transition text-sm">
                    <input type="text" name="override_reason" placeholder="Alasan perubahan" class="w-full bg-darkbg border border-gray-700 text-white px-4 py-3 rounded-xl focus:border-yellow-500 transition text-sm">
                </div>
            <?php endif; ?>
            <div class="flex justify-end gap-3 pt-3">
                <button type="button" onclick="closeDeadlineModal()" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-white"><?= t('batal') ?></button>
                <button type="submit" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-500 rounded-lg text-white font-bold"><?= t('simpan_perubahan') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
    const taskDetails = <?= json_encode(array_values($tasks), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const materialModal = document.getElementById('materialModal');
    const materialModalBox = materialModal.querySelector('.bg-surface');
    const materialForm = materialModal.querySelector('form');
    const materialUploadBtn = document.getElementById('materialUploadBtn');
    const materialProgressBar = document.getElementById('materialProgressBar');
    const materialProgressText = document.getElementById('materialProgressText');
    const materialPreviewModal = document.getElementById('materialPreviewModal');
    const materialPreviewModalBox = materialPreviewModal.querySelector('.bg-surface');
    const materialPreviewFrame = document.getElementById('materialPreviewFrame');
    const materialPreviewTitle = document.getElementById('materialPreviewTitle');
    const materialPreviewDownload = document.getElementById('materialPreviewDownload');
    const taskModal = document.getElementById('taskModal');
    const taskModalBox = taskModal.querySelector('.bg-surface');
    const taskDetailModal = document.getElementById('taskDetailModal');
    const taskDetailModalBox = taskDetailModal.querySelector('.bg-surface');
    const modal = document.getElementById('deadlineModal');
    const modalBox = modal.querySelector('.bg-surface');
    function openModal(el, box) {
        el.classList.remove('hidden');
        el.classList.add('flex');
        setTimeout(() => { el.classList.remove('opacity-0'); box.classList.remove('scale-95'); }, 10);
    }
    function closeModal(el, box) {
        el.classList.add('opacity-0');
        box.classList.add('scale-95');
        setTimeout(() => { el.classList.add('hidden'); el.classList.remove('flex'); }, 300);
    }
    function openMaterialModal() { openModal(materialModal, materialModalBox); }
    function closeMaterialModal() { closeModal(materialModal, materialModalBox); }
    function openMaterialPreview(materialId, title) {
        materialPreviewTitle.textContent = title;
        materialPreviewFrame.src = 'view_material.php?material_id=' + materialId;
        materialPreviewDownload.href = 'view_material.php?material_id=' + materialId + '&download=1';
        openModal(materialPreviewModal, materialPreviewModalBox);
    }
    function openAttachmentPreview(attachmentId, title) {
        materialPreviewTitle.textContent = title;
        materialPreviewFrame.src = 'view_task_attachment.php?attachment_id=' + attachmentId;
        materialPreviewDownload.href = 'view_task_attachment.php?attachment_id=' + attachmentId + '&download=1';
        openModal(materialPreviewModal, materialPreviewModalBox);
    }
    function closeMaterialPreview() {
        materialPreviewFrame.src = 'about:blank';
        closeModal(materialPreviewModal, materialPreviewModalBox);
    }
    function openTaskModal() { openModal(taskModal, taskModalBox); toggleTaskMaterialMode(); }
    function closeTaskModal() { closeModal(taskModal, taskModalBox); }
    function formatFileSize(bytes) {
        const size = Number(bytes || 0);
        if (!size) return '-';
        if (size < 1024 * 1024) return (size / 1024).toFixed(1) + ' KB';
        return (size / (1024 * 1024)).toFixed(1) + ' MB';
    }
    function openTaskDetail(taskId) {
        const task = taskDetails.find(item => Number(item.id) === Number(taskId));
        if (!task) return;
        document.getElementById('taskDetailTitle').textContent = task.judul;
        document.getElementById('taskDetailDescription').textContent = task.deskripsi;
        document.getElementById('taskDetailDeadline').textContent = new Date(task.deadline.replace(' ', 'T')).toLocaleString('<?= ($_SESSION['lang'] ?? 'id') === 'en' ? 'en-US' : 'id-ID' ?>', { dateStyle: 'medium', timeStyle: 'short' });
        document.getElementById('taskDetailFileCount').textContent = task.attachments.length + ' <?= t('files_attached') ?>';
        const list = document.getElementById('taskDetailAttachments');
        list.replaceChildren();
        if (!task.attachments.length) {
            const empty = document.createElement('div');
            empty.className = 'rounded-xl border border-dashed border-gray-700 p-5 text-center text-sm text-gray-500';
            empty.textContent = '<?= t('no_task_attachments') ?>';
            list.appendChild(empty);
        }
        task.attachments.forEach(attachment => {
            const row = document.createElement('div');
            row.className = 'flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border border-gray-700 bg-darkbg p-3';
            const info = document.createElement('div');
            info.className = 'min-w-0';
            const name = document.createElement('p');
            name.className = 'text-sm font-bold text-white truncate';
            name.textContent = attachment.original_name;
            const meta = document.createElement('p');
            meta.className = 'text-[11px] text-gray-500 mt-1';
            meta.textContent = (attachment.material_id ? '<?= t('existing_material_source') ?>' : '<?= t('uploaded_file_source') ?>') + ' · ' + formatFileSize(attachment.file_size);
            info.append(name, meta);
            const actions = document.createElement('div');
            actions.className = 'flex gap-2 shrink-0';
            const view = document.createElement('button');
            view.type = 'button';
            view.className = 'px-3 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-lg transition';
            view.innerHTML = '<i class="fas fa-eye mr-1"></i><?= t('preview') ?>';
            view.addEventListener('click', () => openAttachmentPreview(attachment.id, attachment.original_name));
            const download = document.createElement('a');
            download.className = 'px-3 py-2 bg-green-600 hover:bg-green-500 text-white text-xs font-bold rounded-lg transition';
            download.href = 'view_task_attachment.php?attachment_id=' + attachment.id + '&download=1';
            download.innerHTML = '<i class="fas fa-download mr-1"></i><?= t('download') ?>';
            actions.append(view, download);
            row.append(info, actions);
            list.appendChild(row);
        });
        openModal(taskDetailModal, taskDetailModalBox);
    }
    function closeTaskDetail() { closeModal(taskDetailModal, taskDetailModalBox); }
    function toggleTaskMaterialMode() {
        const mode = document.querySelector('input[name="task_material_mode"]:checked')?.value || 'upload_baru';
        document.getElementById('taskUploadWrap').classList.toggle('hidden', mode !== 'upload_baru');
        document.getElementById('taskExistingWrap').classList.toggle('hidden', mode !== 'existing');
        const fileInput = document.querySelector('input[name="task_material_files[]"]');
        const selectInput = document.querySelector('select[name="existing_material_ids[]"]');
        if (fileInput) fileInput.required = mode === 'upload_baru';
        if (selectInput) selectInput.required = mode === 'existing';
    }
    function openDeadlineModal(taskId, currentDeadline) {
        document.getElementById('deadline_task_id').value = taskId;
        document.getElementById('deadline_date').value = currentDeadline;
        const now = new Date();
        const localNow = new Date(now.getTime() - (now.getTimezoneOffset() * 60000)).toISOString().slice(0,16);
        document.getElementById('deadline_date').min = localNow;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modalBox.classList.remove('scale-95');
        }, 10);
    }
    function closeDeadlineModal() {
        closeModal(modal, modalBox);
    }

    if (materialForm) {
        materialForm.addEventListener('submit', function (event) {
            event.preventDefault();

            const formData = new FormData(materialForm);
            // FormData(form) does not include the submit button that triggered this handler.
            formData.set('upload_materi', '1');
            const xhr = new XMLHttpRequest();

            materialUploadBtn.disabled = true;
            materialUploadBtn.textContent = '<?= t('uploading') ?>';
            materialProgressBar.style.width = '0%';
            materialProgressText.textContent = '0%';

            xhr.upload.addEventListener('progress', function (e) {
                if (!e.lengthComputable) return;
                const percent = Math.round((e.loaded / e.total) * 100);
                materialProgressBar.style.width = percent + '%';
                materialProgressText.textContent = percent + '%';
            });

            xhr.addEventListener('load', function () {
                const responseUrl = new URL(xhr.responseURL || window.location.href, window.location.href);
                const result = responseUrl.searchParams.get('pesan');
                window.location.href = 'detail_kelas.php?id=<?= $class_id ?>&pesan=' +
                    (xhr.status >= 200 && xhr.status < 300 && result === 'materi_sukses'
                        ? 'materi_sukses'
                        : (result || 'materi_gagal'));
            });

            xhr.addEventListener('error', function () {
                window.location.href = 'detail_kelas.php?id=<?= $class_id ?>&pesan=materi_gagal';
            });

            xhr.open('POST', window.location.href, true);
            xhr.send(formData);
        });
    }
</script>
</body></html>
