<?php
include 'includes/db.php';
include 'includes/lang.php';
include 'includes/security_workflow.php';
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }
$user = $_SESSION['user'];
$role = $user['role'];

if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

if ($role !== 'dosen' && $role !== 'admin') {
    die(t('access_denied'));
}

$task_id = (int)$_GET['task_id'];
if ($task_id <= 0) die(t('invalid_task_id'));

// Ambil info tugas dan kelas
$stmt = $conn->prepare("
    SELECT t.*, c.nama_kelas, c.dosen_id 
    FROM tasks t 
    JOIN classes c ON t.class_id = c.id 
    WHERE t.id = ?
");
$stmt->bind_param("i", $task_id);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$task) die(t('task_not_found'));

if ($role === 'dosen' && $task['dosen_id'] != $user['id']) {
    die(t('access_denied_lecturer'));
}

// Proses hapus pengumpulan tugas mahasiswa (dosen/admin)
if (isset($_POST['hapus_pengumpulan'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die(t('csrf_invalid'));
    }

    $submission_id = (int)($_POST['submission_id'] ?? 0);
    if ($submission_id <= 0) {
        header("Location: lihat_pengumpulan.php?task_id=$task_id&pesan=hapus_gagal");
        exit();
    }

    $override_code = trim($_POST['break_glass_code'] ?? '');
    $reason = trim($_POST['override_reason'] ?? '');

    if ($role === 'admin') {
        if ($override_code !== '') {
            $codeRow = verifyBreakGlassCode($conn, $task['dosen_id'], $override_code);
            if (!$codeRow) {
                header("Location: lihat_pengumpulan.php?task_id=$task_id&pesan=hapus_gagal");
                exit();
            }
        } else {
            $requestId = createSensitiveRequest(
                $conn,
                $user,
                (int)$task['dosen_id'],
                (int)$task['class_id'],
                'task_submissions',
                (string)$submission_id,
                'delete_submission',
                ['task_id' => $task_id, 'submission_id' => $submission_id],
                $reason !== '' ? $reason : 'Permintaan hapus submission oleh admin'
            );
            logSecurityAction($conn, (int)$user['id'], $role, 'request_delete_submission', 'task_submissions', (string)$submission_id, $reason !== '' ? $reason : 'Permintaan hapus submission oleh admin', ['request_id' => $requestId]);
            header("Location: lihat_pengumpulan.php?task_id=$task_id&pesan=approval_pending");
            exit();
        }
    }

    $cek = $conn->prepare("SELECT id, mahasiswa_id, file_path FROM task_submissions WHERE id = ? AND task_id = ?");
    $cek->bind_param("ii", $submission_id, $task_id);
    $cek->execute();
    $submission = $cek->get_result()->fetch_assoc();
    $cek->close();

    if (!$submission) {
        header("Location: lihat_pengumpulan.php?task_id=$task_id&pesan=hapus_gagal");
        exit();
    }

    if (!empty($submission['file_path']) && file_exists($submission['file_path'])) {
        @unlink($submission['file_path']);
    }

    $del = $conn->prepare("DELETE FROM task_submissions WHERE id = ? AND task_id = ?");
    $del->bind_param("ii", $submission_id, $task_id);
    $del->execute();
    $deleted = $del->affected_rows > 0;
    $del->close();

    if ($deleted) {
        $desc_hapus = "Pengumpulan tugas '" . $task['judul'] . "' telah dihapus oleh dosen/admin. Silakan unggah ulang jika diminta.";
        $ins_hapus = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'tugas')");
        $ins_hapus->bind_param("is", $submission['mahasiswa_id'], $desc_hapus);
        $ins_hapus->execute();
        $ins_hapus->close();

        if ($role === 'admin' && !empty($codeRow)) {
            consumeBreakGlassCode($conn, $codeRow);
            logSecurityAction($conn, (int)$user['id'], $role, 'break_glass_delete_submission', 'task_submissions', (string)$submission_id, $reason !== '' ? $reason : 'Break-glass delete submission', ['task_id' => $task_id]);
        }

        header("Location: lihat_pengumpulan.php?task_id=$task_id&pesan=hapus_sukses");
        exit();
    }

    header("Location: lihat_pengumpulan.php?task_id=$task_id&pesan=hapus_gagal");
    exit();
}

// Proses simpan nilai & feedback
if (isset($_POST['simpan_nilai'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die(t('csrf_invalid'));
    }

    $submission_id = (int)$_POST['submission_id'];
    $nilai = !empty($_POST['nilai']) ? (int)$_POST['nilai'] : null;
    $feedback = trim($_POST['feedback'] ?? '');
    $override_code = trim($_POST['break_glass_code'] ?? '');
    $reason = trim($_POST['override_reason'] ?? '');

    if ($role === 'admin') {
        if ($override_code !== '') {
            $codeRow = verifyBreakGlassCode($conn, $task['dosen_id'], $override_code);
            if (!$codeRow) {
                header("Location: lihat_pengumpulan.php?task_id=$task_id&pesan=hapus_gagal");
                exit();
            }
        } else {
            $requestId = createSensitiveRequest(
                $conn,
                $user,
                (int)$task['dosen_id'],
                (int)$task['class_id'],
                'task_submissions',
                (string)$submission_id,
                'grade_submission',
                ['nilai' => $nilai, 'feedback' => $feedback],
                $reason !== '' ? $reason : 'Permintaan penilaian submission oleh admin'
            );
            logSecurityAction($conn, (int)$user['id'], $role, 'request_grade_submission', 'task_submissions', (string)$submission_id, $reason !== '' ? $reason : 'Permintaan penilaian submission oleh admin', ['request_id' => $requestId]);
            header("Location: lihat_pengumpulan.php?task_id=$task_id&pesan=approval_pending");
            exit();
        }
    }
    
    if ($nilai !== null && ($nilai < 0 || $nilai > 100)) {
        die(t('grade_must_be_between'));
    }
    
    // Ambil mahasiswa_id untuk notifikasi
    $stmt = $conn->prepare("SELECT mahasiswa_id FROM task_submissions WHERE id = ?");
    $stmt->bind_param("i", $submission_id);
    $stmt->execute();
    $mhs_id = $stmt->get_result()->fetch_assoc()['mahasiswa_id'];
    $stmt->close();
    
    // Jika kolom `nilai` belum ada di DB, tambahkan kolom secara aman
    $colCheck = $conn->query("SHOW COLUMNS FROM task_submissions LIKE 'nilai'");
    if ($colCheck === false) {
        // jika terjadi error pada SHOW COLUMNS, coba lanjutkan tanpa ALTER
    } elseif ($colCheck->num_rows == 0) {
        // tambahkan kolom INT NULL setelah file_path jika memungkinkan
        $conn->query("ALTER TABLE task_submissions ADD COLUMN nilai INT NULL AFTER file_path");
    }

    // Pastikan kolom `feedback` ada juga
    $colCheck2 = $conn->query("SHOW COLUMNS FROM task_submissions LIKE 'feedback'");
    if ($colCheck2 === false) {
        // lanjutkan
    } elseif ($colCheck2->num_rows == 0) {
        $conn->query("ALTER TABLE task_submissions ADD COLUMN feedback TEXT NULL AFTER nilai");
    }

    $update = $conn->prepare("UPDATE task_submissions SET nilai = ?, feedback = ? WHERE id = ?");
    $update->bind_param("isi", $nilai, $feedback, $submission_id);
    $update->execute();
    $update->close();

    if ($role === 'admin' && !empty($codeRow)) {
        consumeBreakGlassCode($conn, $codeRow);
        logSecurityAction($conn, (int)$user['id'], $role, 'break_glass_grade_submission', 'task_submissions', (string)$submission_id, $reason !== '' ? $reason : 'Break-glass grade submission', ['task_id' => $task_id, 'nilai' => $nilai]);
    }

    // Notifikasi ke mahasiswa
    $desc = "Dosen telah memberikan nilai untuk tugas '" . $task['judul'] . "'. Nilai: " . ($nilai ?? 'Belum ada nilai');
    $ins = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'tugas')");
    $ins->bind_param("is", $mhs_id, $desc);
    $ins->execute();
    $ins->close();
    
    header("Location: lihat_pengumpulan.php?task_id=$task_id&pesan=sukses");
    exit();
}

// Ambil daftar submission
$subs = $conn->prepare("
    SELECT ts.*, u.nama as mahasiswa_nama 
    FROM task_submissions ts
    JOIN users u ON ts.mahasiswa_id = u.id
    WHERE ts.task_id = ?
    ORDER BY ts.submitted_at DESC
");
$subs->bind_param("i", $task_id);
$subs->execute();
$submissions = $subs->get_result();
$subs->close();

include 'includes/header.php';
include 'includes/navbar.php';
?>

<main class="max-w-6xl mx-auto p-6 md:p-10">
    <div class="flex justify-between items-center border-b border-gray-800 pb-5 mb-8">
        <div>
            <h2 class="text-3xl font-extrabold text-white tracking-wide"><?= t('submission_list_title') ?></h2>
            <p class="text-gray-400 mt-1"><?= t('task_title') ?>: <?= htmlspecialchars($task['judul']) ?> | <?= t('kelas') ?>: <?= htmlspecialchars($task['nama_kelas']) ?></p>
        </div>
        <a href="detail_kelas.php?id=<?= $task['class_id'] ?>" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-white">
            <i class="fas fa-arrow-left mr-2"></i><?= t('back_to_class') ?>
        </a>
    </div>

    <?php if(isset($_GET['pesan']) && $_GET['pesan'] == 'sukses'): ?>
        <div class="mb-6 px-4 py-3 bg-green-500/20 border border-green-500 text-green-400 rounded-xl">
            <i class="fas fa-check-circle mr-2"></i> <?= t('grade_saved_success') ?>
        </div>
    <?php endif; ?>
    <?php if(isset($_GET['pesan']) && $_GET['pesan'] == 'hapus_sukses'): ?>
        <div class="mb-6 px-4 py-3 bg-red-500/20 border border-red-500 text-red-300 rounded-xl">
            <i class="fas fa-trash-alt mr-2"></i> <?= t('submission_deleted_success') ?>
        </div>
    <?php endif; ?>
    <?php if(isset($_GET['pesan']) && $_GET['pesan'] == 'hapus_gagal'): ?>
        <div class="mb-6 px-4 py-3 bg-yellow-500/20 border border-yellow-500 text-yellow-300 rounded-xl">
            <i class="fas fa-exclamation-triangle mr-2"></i> <?= t('submission_delete_failed') ?>
        </div>
    <?php endif; ?>
    <?php if(isset($_GET['pesan']) && $_GET['pesan'] == 'approval_pending'): ?>
        <div class="mb-6 px-4 py-3 bg-yellow-500/20 border border-yellow-500 text-yellow-300 rounded-xl">
            <i class="fas fa-shield-alt mr-2"></i> Permintaan admin menunggu persetujuan dosen.
        </div>
    <?php endif; ?>

    <div class="bg-surface border border-gray-800 rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-darkbg border-b border-gray-800">
                    <tr>
                        <th class="px-6 py-4 text-gray-400 text-xs uppercase">No</th>
                        <th class="px-6 py-4 text-gray-400 text-xs uppercase"><?= t('mahasiswa_role') ?></th>
                        <th class="px-6 py-4 text-gray-400 text-xs uppercase">File</th>
                        <th class="px-6 py-4 text-gray-400 text-xs uppercase"><?= t('upload_time') ?></th>
                        <th class="px-6 py-4 text-gray-400 text-xs uppercase"><?= t('score') ?></th>
                        <th class="px-6 py-4 text-gray-400 text-xs uppercase"><?= t('feedback') ?></th>
                        <th class="px-6 py-4 text-gray-400 text-xs uppercase"><?= t('action') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    <?php if ($submissions->num_rows > 0): $no=1; while($row = $submissions->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-800/30 transition">
                        <td class="px-6 py-4 text-gray-300"><?= $no++ ?></td>
                        <td class="px-6 py-4 text-gray-200 font-medium"><?= htmlspecialchars($row['mahasiswa_nama']) ?></td>
                        <td class="px-6 py-4">
                            <a href="download.php?submission_id=<?= $row['id'] ?>&token=<?= md5($row['id'] . $user['id'] . 'secret_key') ?>" 
                               class="text-blue-400 hover:underline text-sm">
                                <i class="fas fa-download mr-1"></i> <?= htmlspecialchars($row['original_name']) ?>
                            </a>
                        </td>
                        <td class="px-6 py-4 text-gray-400 text-sm"><?= date('d M Y, H:i', strtotime($row['submitted_at'])) ?></td>
                        <form method="POST" action="">
                            <input type="hidden" name="submission_id" value="<?= $row['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                            <td class="px-6 py-4">
                                <input type="number" name="nilai" min="0" max="100" value="<?= htmlspecialchars($row['nilai'] ?? '') ?>" 
                                       class="w-20 bg-darkbg border border-gray-700 text-white px-2 py-1 rounded text-sm text-center">
                            </td>
                            <td class="px-6 py-4">
                                <input type="text" name="feedback" value="<?= htmlspecialchars($row['feedback'] ?? '') ?>" 
                                       placeholder="<?= t('feedback') ?>..." 
                                       class="w-48 bg-darkbg border border-gray-700 text-white px-2 py-1 rounded text-sm">
                                <?php if ($role === 'admin'): ?>
                                    <div class="mt-2 space-y-2">
                                        <input type="text" name="break_glass_code" placeholder="Break-glass code (opsional)" class="w-48 bg-darkbg border border-gray-700 text-white px-2 py-1 rounded text-xs">
                                        <input type="text" name="override_reason" placeholder="Alasan perubahan" class="w-48 bg-darkbg border border-gray-700 text-white px-2 py-1 rounded text-xs">
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <button type="submit" name="simpan_nilai" class="px-3 py-1 bg-blue-600 hover:bg-blue-500 rounded text-white text-xs mr-2">
                                    <i class="fas fa-save mr-1"></i> <?= t('save') ?>
                                </button>
                                <button type="submit" name="hapus_pengumpulan" onclick="return confirm('<?= t('delete_submission_confirm') ?>');" class="px-3 py-1 bg-red-600 hover:bg-red-500 rounded text-white text-xs">
                                    <i class="fas fa-trash-alt mr-1"></i> <?= t('delete') ?>
                                </button>
                            </td>
                        </form>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-inbox text-3xl mb-2 block"></i>
                            <?= t('no_submissions_yet') ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?php include 'includes/footer.php'; ?>
</body></html>
