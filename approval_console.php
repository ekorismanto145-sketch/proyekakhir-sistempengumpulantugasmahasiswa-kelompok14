<?php
include 'includes/db.php';
include 'includes/lang.php';
include 'includes/security_workflow.php';
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }
$user = $_SESSION['user'];
$role = $user['role'];

if ($role !== 'dosen') {
    header("Location: index.php");
    exit();
}

ensureSecurityWorkflowTables($conn);

function executePendingRequest($conn, $request) {
    $payload = json_decode($request['payload_json'] ?? '{}', true) ?: [];
    $requestId = (int)$request['id'];
    $classId = (int)($request['class_id'] ?? 0);

    if ($request['action_type'] === 'update_deadline') {
        $deadline = $payload['deadline'] ?? null;
        if (!$deadline) return false;
        $taskId = (int)$request['target_id'];
        $update = $conn->prepare("UPDATE tasks SET deadline = ? WHERE id = ?");
        $update->bind_param("si", $deadline, $taskId);
        $update->execute();
        $update->close();
        $stmt = $conn->prepare("SELECT t.judul, c.nama_kelas, c.id AS class_id FROM tasks t JOIN classes c ON t.class_id = c.id WHERE t.id = ?");
        $stmt->bind_param("i", $taskId);
        $stmt->execute();
        $task = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($task) {
            $desc = t('activity_deadline_updated_by_teacher_prefix') . "'" . $task['judul'] . "'" . t('activity_deadline_updated_by_teacher_suffix') . $task['nama_kelas'] . ' telah diubah menjadi ' . date('d M Y H:i', strtotime($deadline));
            $m = $conn->prepare("SELECT mahasiswa_id FROM class_members WHERE class_id = ?");
            $m->bind_param("i", $task['class_id']);
            $m->execute();
            $res = $m->get_result();
            while ($row = $res->fetch_assoc()) {
                $ins = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'tugas')");
                $ins->bind_param("is", $row['mahasiswa_id'], $desc);
                $ins->execute();
                $ins->close();
            }
            $m->close();
            $desc_dosen = t('activity_deadline_updated_by_you_prefix') . "'" . $task['judul'] . "' menjadi " . date('d M Y H:i', strtotime($deadline));
            $ins_dosen = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'tugas')");
            $ins_dosen->bind_param("is", $request['actor_user_id'], $desc_dosen);
            $ins_dosen->execute();
            $ins_dosen->close();
        }
    } elseif ($request['action_type'] === 'delete_submission') {
        $submissionId = (int)$request['target_id'];
        $stmt = $conn->prepare("SELECT id, mahasiswa_id, file_path, task_id FROM task_submissions WHERE id = ?");
        $stmt->bind_param("i", $submissionId);
        $stmt->execute();
        $submission = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$submission) return false;
        if (!empty($submission['file_path']) && file_exists($submission['file_path'])) @unlink($submission['file_path']);
        $del = $conn->prepare("DELETE FROM task_submissions WHERE id = ?");
        $del->bind_param("i", $submissionId);
        $del->execute();
        $del->close();
        $taskStmt = $conn->prepare("SELECT judul FROM tasks WHERE id = ?");
        $taskStmt->bind_param("i", $submission['task_id']);
        $taskStmt->execute();
        $taskRow = $taskStmt->get_result()->fetch_assoc();
        $taskStmt->close();
        $desc = "Pengumpulan tugas '" . ($taskRow['judul'] ?? 'tugas') . "' telah dihapus melalui approval dosen.";
        $ins = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'tugas')");
        $ins->bind_param("is", $submission['mahasiswa_id'], $desc);
        $ins->execute();
        $ins->close();
    } elseif ($request['action_type'] === 'grade_submission') {
        $submissionId = (int)$request['target_id'];
        $nilai = array_key_exists('nilai', $payload) ? $payload['nilai'] : null;
        $feedback = $payload['feedback'] ?? '';
        $update = $conn->prepare("UPDATE task_submissions SET nilai = ?, feedback = ? WHERE id = ?");
        $update->bind_param("isi", $nilai, $feedback, $submissionId);
        $update->execute();
        $update->close();
    } else {
        return false;
    }

    approveSensitiveRequest($conn, $requestId, (int)$user['id']);
    executeSensitiveRequest($conn, $requestId);
    logSecurityAction($conn, (int)$user['id'], $user['role'], 'approve_sensitive_request', $request['target_type'], (string)$request['target_id'], 'Approval dosen', ['request_id' => $requestId]);
    return true;
}

if (isset($_POST['generate_code'])) {
    $newCode = issueBreakGlassCode($conn, (int)$user['id'], 15);
    $_SESSION['new_break_glass_code'] = $newCode;
    header("Location: approval_console.php?pesan=code_generated");
    exit();
}

if (isset($_POST['approve_request'])) {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $request = loadSensitiveRequest($conn, $requestId);
    if ($request && (int)$request['lecturer_id'] === (int)$user['id'] && $request['status'] === 'pending') {
        executePendingRequest($conn, $request);
        header("Location: approval_console.php?pesan=approved");
        exit();
    }
    header("Location: approval_console.php?pesan=reject_failed");
    exit();
}

if (isset($_POST['reject_request'])) {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $request = loadSensitiveRequest($conn, $requestId);
    if ($request && (int)$request['lecturer_id'] === (int)$user['id'] && $request['status'] === 'pending') {
        rejectSensitiveRequest($conn, $requestId, (int)$user['id']);
        logSecurityAction($conn, (int)$user['id'], $user['role'], 'reject_sensitive_request', $request['target_type'], (string)$request['target_id'], 'Reject dosen', ['request_id' => $requestId]);
        header("Location: approval_console.php?pesan=rejected");
        exit();
    }
    header("Location: approval_console.php?pesan=reject_failed");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM sensitive_change_requests WHERE lecturer_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$requests = $stmt->get_result();
$stmt->close();
$activeCode = getActiveBreakGlassCodeRow($conn, (int)$user['id']);

include 'includes/header.php';
include 'includes/navbar.php';
?>
<main class="max-w-6xl mx-auto p-6 md:p-10">
    <div class="flex items-center justify-between gap-4 mb-6 border-b border-gray-800 pb-5">
        <div>
            <h2 class="text-3xl font-extrabold text-white tracking-wide">Console Persetujuan Dosen</h2>
            <p class="text-gray-400 mt-1">Kelola request admin dan break-glass code one-time.</p>
        </div>
        <form method="POST">
            <button type="submit" name="generate_code" class="px-4 py-3 bg-yellow-600 hover:bg-yellow-500 rounded-xl text-white font-bold">Generate Code Baru</button>
        </form>
    </div>

    <?php if (!empty($_SESSION['new_break_glass_code'])): ?>
        <div class="mb-6 px-4 py-3 rounded-xl border bg-yellow-500/10 border-yellow-500 text-yellow-300">
            Code baru: <span class="font-mono font-bold"><?= htmlspecialchars($_SESSION['new_break_glass_code']) ?></span>
        </div>
        <?php unset($_SESSION['new_break_glass_code']); ?>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-surface border border-gray-800 rounded-2xl p-5">
            <h3 class="text-lg font-bold text-white mb-3">Break-glass code aktif</h3>
            <p class="text-sm text-gray-400"><?= $activeCode ? 'Ada code aktif' : 'Belum ada code aktif' ?></p>
            <?php if ($activeCode): ?>
                <p class="text-xs text-gray-500 mt-2">Expired: <?= htmlspecialchars($activeCode['expires_at']) ?></p>
            <?php endif; ?>
        </div>
        <div class="bg-surface border border-gray-800 rounded-2xl p-5">
            <h3 class="text-lg font-bold text-white mb-3">Aturan</h3>
            <p class="text-sm text-gray-400">Approve/reject request admin di sini, atau generate code baru jika perlu direct override.</p>
        </div>
    </div>

    <div class="space-y-4">
        <?php while ($r = $requests->fetch_assoc()): ?>
            <div class="bg-surface border border-gray-800 rounded-2xl p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <p class="text-white font-bold"><?= htmlspecialchars($r['action_type']) ?></p>
                    <p class="text-sm text-gray-400"><?= htmlspecialchars($r['reason']) ?></p>
                    <p class="text-xs text-gray-500 mt-1">Status: <?= htmlspecialchars($r['status']) ?> | Target: <?= htmlspecialchars($r['target_type']) ?> #<?= htmlspecialchars($r['target_id']) ?></p>
                </div>
                <?php if ($r['status'] === 'pending'): ?>
                    <form method="POST" class="flex gap-2">
                        <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                        <button type="submit" name="approve_request" class="px-4 py-2 bg-green-600 hover:bg-green-500 text-white rounded-lg">Approve</button>
                        <button type="submit" name="reject_request" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg">Reject</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    </div>
</main>
<?php include 'includes/footer.php'; ?>
</body></html>
