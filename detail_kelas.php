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

    if (!isset($_FILES['file_materi']) || $_FILES['file_materi']['error'] !== 0) {
        die(t('material_file_required'));
    }

    $tmp_name = $_FILES['file_materi']['tmp_name'];
    $original_name = $_FILES['file_materi']['name'];
    $file_size = (int)$_FILES['file_materi']['size'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $tmp_name);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_mime)) {
        header("Location: detail_kelas.php?id=$class_id&pesan=materi_format_salah");
        exit();
    }

    if ($file_size > $max_size) {
        header("Location: detail_kelas.php?id=$class_id&pesan=materi_terlalu_besar");
        exit();
    }

    $folder = 'uploads/materi/';
    if (!is_dir($folder)) {
        if (!mkdir($folder, 0777, true) && !is_dir($folder)) {
            header("Location: detail_kelas.php?id=$class_id&pesan=materi_gagal");
            exit();
        }
    }

    $file_ext = pathinfo($original_name, PATHINFO_EXTENSION);
    $nama_simpan = bin2hex(random_bytes(16)) . '.' . strtolower($file_ext ?: 'bin');
    $file_path = $folder . $nama_simpan;

    if (!move_uploaded_file($tmp_name, $file_path)) {
        header("Location: detail_kelas.php?id=$class_id&pesan=materi_gagal");
        exit();
    }

    $saved_file = true;
    $conn->begin_transaction();
    try {
        $stmt_mat = $conn->prepare("INSERT INTO materials (class_id, judul, deskripsi, file_path, original_name, mime_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt_mat) {
            throw new Exception('prepare_material_failed: ' . $conn->error);
        }
        $stmt_mat->bind_param("isssssii", $class_id, $judul_materi, $deskripsi_materi, $file_path, $original_name, $mime_type, $file_size, $user['id']);
        if (!$stmt_mat->execute()) {
            throw new Exception('insert_material_failed: ' . $stmt_mat->error);
        }
        $stmt_mat->close();

        // Material storage is the primary operation; notifications must not roll it back.
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        if ($saved_file && file_exists($file_path)) {
            @unlink($file_path);
        }
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
if (isset($_GET['leave']) && $role === 'mahasiswa') {
    if (!isset($_GET['csrf_token']) || !verifyCSRFToken($_GET['csrf_token'])) {
        die(t('csrf_invalid'));
    }
    $leave = $conn->prepare("DELETE FROM class_members WHERE class_id = ? AND mahasiswa_id = ?");
    $leave->bind_param("ii", $class_id, $user['id']);
    $leave->execute();
    $leave->close();
    header("Location: index.php?pesan=keluar_kelas");
    exit();
}

// =========================================================
// PROSES HAPUS KELAS (DOSEN/ADMIN) - DENGAN NOTIFIKASI
// =========================================================
if (isset($_POST['delete_class']) && $is_owner) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die(t('csrf_invalid'));
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

    // 3. Insert notifikasi untuk setiap mahasiswa anggota
    foreach ($memberIds as $mhs_id) {
        $ins = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'kelas_baru')");
        $ins->bind_param("is", $mhs_id, $desc);
        $ins->execute();
        $ins->close();
    }

    // 4. Insert notifikasi untuk dosen yang menghapus
    $insOwner = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'kelas_baru')");
    $insOwner->bind_param("is", $user['id'], $desc);
    $insOwner->execute();
    $insOwner->close();

    // 5. Hapus kelas (cascade akan hapus class_members, tasks, dll)
    $del = $conn->prepare("DELETE FROM classes WHERE id = ?");
    $del->bind_param("i", $class_id);
    $del->execute();
    $del->close();

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

    if ($task_material_mode === 'existing') {
        $requested_material_id = (int)($_POST['existing_material_id'] ?? 0);
        $stmt_material = $conn->prepare("SELECT id FROM materials WHERE id = ? AND class_id = ?");
        $stmt_material->bind_param("ii", $requested_material_id, $class_id);
        $stmt_material->execute();
        $valid_material = $stmt_material->get_result()->fetch_assoc();
        $stmt_material->close();

        if (!$valid_material) {
            header("Location: detail_kelas.php?id=$class_id&pesan=task_material_required");
            exit();
        }
        $existing_material_id = (int)$valid_material['id'];
    } elseif ($task_material_mode === 'upload_baru') {
        if (!isset($_FILES['task_material_file']) || $_FILES['task_material_file']['error'] !== UPLOAD_ERR_OK) {
            header("Location: detail_kelas.php?id=$class_id&pesan=task_material_required");
            exit();
        }

        $task_file_size = (int)$_FILES['task_material_file']['size'];
        $task_tmp_name = $_FILES['task_material_file']['tmp_name'];
        $task_file_name = basename($_FILES['task_material_file']['name']);
        $task_finfo = finfo_open(FILEINFO_MIME_TYPE);
        $task_mime_type = finfo_file($task_finfo, $task_tmp_name);
        finfo_close($task_finfo);
        $allowed_task_mime = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];

        if ($task_file_size > 10 * 1024 * 1024 || !in_array($task_mime_type, $allowed_task_mime, true)) {
            header("Location: detail_kelas.php?id=$class_id&pesan=task_material_invalid");
            exit();
        }

        $task_folder = 'uploads/task_materials/';
        if (!is_dir($task_folder) && !mkdir($task_folder, 0777, true) && !is_dir($task_folder)) {
            header("Location: detail_kelas.php?id=$class_id&pesan=task_material_failed");
            exit();
        }
        $task_extension = strtolower(pathinfo($task_file_name, PATHINFO_EXTENSION) ?: 'bin');
        $task_file_path = $task_folder . bin2hex(random_bytes(16)) . '.' . $task_extension;
        if (!move_uploaded_file($task_tmp_name, $task_file_path)) {
            header("Location: detail_kelas.php?id=$class_id&pesan=task_material_failed");
            exit();
        }
    } else {
        header("Location: detail_kelas.php?id=$class_id&pesan=task_material_required");
        exit();
    }
    
    $stmt2 = $conn->prepare("INSERT INTO tasks (class_id, judul, deskripsi, deadline) VALUES (?, ?, ?, ?)");
    $stmt2->bind_param("isss", $class_id, $judul, $deskripsi, $deadline);
    if (!$stmt2->execute()) {
        if ($task_file_path && file_exists($task_file_path)) {
            @unlink($task_file_path);
        }
        header("Location: detail_kelas.php?id=$class_id&pesan=task_create_failed");
        exit();
    }
    $task_id = $stmt2->insert_id;
    $stmt2->close();

    if (columnExists($conn, 'tasks', 'material_source_type')) {
        $stmt_meta = $conn->prepare("UPDATE tasks SET material_source_type = ?, material_file_path = ?, material_original_name = ?, material_reference_id = ? WHERE id = ?");
        $stmt_meta->bind_param("sssii", $task_material_mode, $task_file_path, $task_file_name, $existing_material_id, $task_id);
        if (!$stmt_meta->execute()) {
            $delete_task = $conn->prepare("DELETE FROM tasks WHERE id = ?");
            $delete_task->bind_param("i", $task_id);
            $delete_task->execute();
            $delete_task->close();
            if ($task_file_path && file_exists($task_file_path)) {
                @unlink($task_file_path);
            }
            header("Location: detail_kelas.php?id=$class_id&pesan=task_create_failed");
            exit();
        }
        $stmt_meta->close();
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

include 'includes/header.php'; 
include 'includes/navbar.php'; 
?>

<main class="max-w-5xl mx-auto p-4 md:p-8">
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
            <a href="?leave=1&csrf_token=<?= generateCSRFToken(); ?>" 
               onclick="return confirm('<?= t('leave_class_confirm') ?>')" 
                class="px-3 py-1.5 bg-red-600/50 hover:bg-red-600 text-white text-xs font-bold rounded-lg transition">
                <i class="fas fa-sign-out-alt mr-1"></i> <?= t('keluar_kelas') ?>
            </a>
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
                                        <a href="<?= htmlspecialchars($materi['file_path']) ?>" download class="px-3 py-2 bg-green-600 hover:bg-green-500 text-white text-xs font-bold rounded-lg transition whitespace-nowrap">
                                            <i class="fas fa-download mr-1"></i> <?= t('download') ?>
                                        </a>
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
            <?php
            $stmt_tugas = $conn->prepare("SELECT * FROM tasks WHERE class_id = ? ORDER BY created_at DESC");
            $stmt_tugas->bind_param("i", $class_id);
            $stmt_tugas->execute();
            $query_tugas = $stmt_tugas->get_result();
            if ($query_tugas->num_rows > 0):
                while($tugas = $query_tugas->fetch_assoc()):
                    $deadline_time = strtotime($tugas['deadline']);
                    $is_expired = ($deadline_time < time());
            ?>
                <div class="bg-surface border border-gray-800 rounded-2xl p-6 shadow-lg hover:border-gray-600 transition">
                    <div class="flex flex-col md:flex-row justify-between md:items-start mb-3 gap-3">
                        <h4 class="text-lg font-bold text-white"><?= htmlspecialchars($tugas['judul']) ?></h4>
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
                                <button onclick="openDeadlineModal(<?= $tugas['id'] ?>, '<?= date('Y-m-d\TH:i', $deadline_time) ?>')" 
                                        class="text-xs bg-yellow-600 hover:bg-yellow-500 px-2 py-1 rounded text-white">
                                    <i class="fas fa-edit mr-1"></i> <?= t('edit_deadline') ?>
                                </button>
                                <a href="lihat_pengumpulan.php?task_id=<?= $tugas['id'] ?>" class="text-xs bg-green-600 hover:bg-green-500 px-2 py-1 rounded text-white whitespace-nowrap">
                                    <i class="fas fa-eye mr-1"></i> <?= t('view_submissions') ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <p class="text-sm text-gray-400 mb-4"><?= nl2br(htmlspecialchars($tugas['deskripsi'])) ?></p>
                    <?php if($role === 'mahasiswa'): ?>
                        <a href="tugas.php" class="inline-block px-4 py-2 bg-darkbg border border-gray-600 hover:border-blue-500 text-gray-300 hover:text-white text-sm font-bold rounded-lg transition">
                            <i class="fas fa-upload mr-2"></i> <?= t('kumpulkan_tugas_ini') ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endwhile; else: ?>
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
                <input type="file" name="file_materi" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="w-full bg-darkbg border border-gray-700 text-white px-3 py-2.5 rounded-lg text-sm" required>
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
                    <input type="file" name="task_material_file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="w-full bg-darkbg border border-gray-700 text-white px-3 py-2.5 rounded-lg text-sm">
                </div>
                <div id="taskExistingWrap" class="hidden space-y-2">
                    <label class="block text-xs font-bold text-gray-400 mb-1.5"><?= t('task_material_existing') ?></label>
                    <select name="existing_material_id" class="w-full bg-darkbg border border-gray-700 text-white px-3 py-2.5 rounded-lg text-sm">
                        <option value=""><?= t('choose_material') ?></option>
                        <?php foreach ($materials as $materi): ?>
                            <option value="<?= $materi['id'] ?>"><?= htmlspecialchars($materi['judul']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
            <button type="submit" name="buat_tugas" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-lg transition mt-2"><?= t('publish_task') ?></button>
        </form>
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
    function openMaterialPreview(materialId, title, mimeType, filePath) {
        materialPreviewTitle.textContent = title;
        materialPreviewFrame.src = 'view_material.php?material_id=' + materialId;
        materialPreviewDownload.href = filePath || '#';
        materialPreviewDownload.setAttribute('download', '');
        openModal(materialPreviewModal, materialPreviewModalBox);
    }
    function closeMaterialPreview() {
        materialPreviewFrame.src = 'about:blank';
        closeModal(materialPreviewModal, materialPreviewModalBox);
    }
    function openTaskModal() { openModal(taskModal, taskModalBox); toggleTaskMaterialMode(); }
    function closeTaskModal() { closeModal(taskModal, taskModalBox); }
    function toggleTaskMaterialMode() {
        const mode = document.querySelector('input[name="task_material_mode"]:checked')?.value || 'upload_baru';
        document.getElementById('taskUploadWrap').classList.toggle('hidden', mode !== 'upload_baru');
        document.getElementById('taskExistingWrap').classList.toggle('hidden', mode !== 'existing');
        const fileInput = document.querySelector('input[name="task_material_file"]');
        const selectInput = document.querySelector('select[name="existing_material_id"]');
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
