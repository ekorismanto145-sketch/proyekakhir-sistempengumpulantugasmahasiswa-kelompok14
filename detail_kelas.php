<?php 
include 'includes/db.php'; 
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
if ($role === 'admin') {
    $allowed = true;
    $is_owner = true;
} elseif ($role === 'dosen') {
    if ($kelas['dosen_id'] == $user['id']) {
        $allowed = true;
        $is_owner = true;
    }
} elseif ($role === 'mahasiswa') {
    $stmt_cek = $conn->prepare("SELECT 1 FROM class_members WHERE class_id = ? AND mahasiswa_id = ?");
    $stmt_cek->bind_param("ii", $class_id, $user['id']);
    $stmt_cek->execute();
    $allowed = $stmt_cek->get_result()->num_rows > 0;
    $stmt_cek->close();
}
if (!$allowed) { die("Anda tidak memiliki akses ke kelas ini."); }

// =========================================================
// PROSES KELUAR KELAS (MAHASISWA)
// =========================================================
if (isset($_GET['leave']) && $role === 'mahasiswa') {
    if (!isset($_GET['csrf_token']) || !verifyCSRFToken($_GET['csrf_token'])) {
        die("CSRF token tidak valid.");
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
        die("CSRF token tidak valid.");
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
    $desc = "Kelas '" . $kelas['nama_kelas'] . "' telah dihapus oleh " . ($role === 'admin' ? 'Admin' : $user['nama']);

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
        die("CSRF token tidak valid.");
    }
    if ($role === 'dosen' && $kelas['dosen_id'] != $user['id']) {
        die("Anda tidak berwenang membuat tugas di kelas ini.");
    }
    $judul = trim($_POST['judul']);
    $deskripsi = trim($_POST['deskripsi']);
    $deadline = $_POST['deadline'];
    $deadline_ts = strtotime($deadline);
    if ($deadline_ts === false || $deadline_ts < time()) {
        header("Location: detail_kelas.php?id=$class_id&pesan=deadline_invalid");
        exit();
    }
    
    $stmt2 = $conn->prepare("INSERT INTO tasks (class_id, judul, deskripsi, deadline) VALUES (?, ?, ?, ?)");
    $stmt2->bind_param("isss", $class_id, $judul, $deskripsi, $deadline);
    $stmt2->execute();
    $stmt2->close();
    
    $desc_mahasiswa = "Dosen memberikan tugas baru: " . $judul . " di kelas " . $kelas['nama_kelas'];
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
    $desc_dosen = "Anda telah membuat tugas baru: " . $judul;
    $ins_dosen = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'tugas')");
    $ins_dosen->bind_param("is", $user['id'], $desc_dosen);
    $ins_dosen->execute();
    $ins_dosen->close();
    
    header("Location: detail_kelas.php?id=$class_id&pesan=tugas_dibuat");
    exit();
}

include 'includes/header.php'; 
include 'includes/navbar.php'; 
?>

<main class="max-w-5xl mx-auto p-4 md:p-8">
    <?php if (isset($_GET['pesan']) && $_GET['pesan'] === 'deadline_invalid'): ?>
        <div class="mb-4 px-4 py-3 rounded-xl border bg-red-500/20 border-red-500 text-red-300">
            <i class="fas fa-exclamation-circle mr-2"></i> Deadline tidak boleh kurang dari waktu saat ini.
        </div>
    <?php endif; ?>

    <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-4 gap-3">
        <a href="index.php" class="inline-flex items-center text-sm text-gray-400 hover:text-white transition">
            <i class="fas fa-arrow-left mr-2"></i> Kembali ke Dashboard
        </a>
        <?php if ($is_owner): ?>
            <div class="flex gap-2 items-center flex-wrap">
                <a href="edit_kelas.php?id=<?= $class_id ?>" class="px-3 py-1.5 bg-yellow-600 hover:bg-yellow-500 text-white text-xs font-bold rounded-lg transition">
                    <i class="fas fa-edit mr-1"></i> Edit Kelas
                </a>
                <form method="POST" class="inline" onsubmit="return confirm('Yakin ingin menghapus kelas ini? Semua tugas dan anggota akan hilang.')">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                    <button type="submit" name="delete_class" class="px-3 py-1.5 bg-red-600 hover:bg-red-500 text-white text-xs font-bold rounded-lg transition">
                        <i class="fas fa-trash-alt mr-1"></i> Hapus Kelas
                    </button>
                </form>
            </div>
        <?php elseif ($role === 'mahasiswa'): ?>
            <a href="?leave=1&csrf_token=<?= generateCSRFToken(); ?>" 
               onclick="return confirm('Yakin ingin keluar dari kelas ini?')" 
               class="px-3 py-1.5 bg-red-600/50 hover:bg-red-600 text-white text-xs font-bold rounded-lg transition">
                <i class="fas fa-sign-out-alt mr-1"></i> Keluar Kelas
            </a>
        <?php endif; ?>
    </div>

    <div class="bg-blue-600 rounded-3xl p-6 md:p-10 shadow-2xl mb-8 relative overflow-hidden flex flex-col md:flex-row justify-between items-center gap-4">
        <div class="relative z-10 text-center md:text-left mb-6 md:mb-0">
            <h1 class="text-2xl sm:text-3xl md:text-5xl font-extrabold text-white mb-2 break-words"><?= htmlspecialchars($kelas['nama_kelas']); ?></h1>
            <p class="text-blue-200 text-sm sm:text-base md:text-lg break-words"><?= htmlspecialchars($kelas['deskripsi']); ?> • Dosen: <?= htmlspecialchars($kelas['nama_dosen']); ?></p>
        </div>
        <div class="relative z-10 bg-darkbg/50 backdrop-blur-md border border-white/20 p-4 sm:p-5 rounded-2xl text-center w-full md:w-auto min-w-0 md:min-w-[200px]">
            <p class="text-blue-200 text-xs font-bold uppercase tracking-widest mb-1">Kode Bergabung</p>
            <p class="text-2xl sm:text-3xl font-mono font-bold text-white tracking-widest select-all cursor-pointer break-all"><?= htmlspecialchars($kelas['kode_kelas']); ?></p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-6">
            <h3 class="text-xl font-bold text-white border-b border-gray-800 pb-3"><i class="fas fa-tasks mr-2 text-blue-500"></i> Tugas Kelas</h3>
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
                                    <i class="fas fa-edit mr-1"></i> Edit Deadline
                                </button>
                                <a href="lihat_pengumpulan.php?task_id=<?= $tugas['id'] ?>" class="text-xs bg-green-600 hover:bg-green-500 px-2 py-1 rounded text-white whitespace-nowrap">
                                    <i class="fas fa-eye mr-1"></i> Lihat Pengumpulan
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <p class="text-sm text-gray-400 mb-4"><?= nl2br(htmlspecialchars($tugas['deskripsi'])) ?></p>
                    <?php if($role === 'mahasiswa'): ?>
                        <a href="tugas.php" class="inline-block px-4 py-2 bg-darkbg border border-gray-600 hover:border-blue-500 text-gray-300 hover:text-white text-sm font-bold rounded-lg transition">
                            <i class="fas fa-upload mr-2"></i> Kumpulkan Tugas Ini
                        </a>
                    <?php endif; ?>
                </div>
            <?php endwhile; else: ?>
                <div class="bg-surface border border-dashed border-gray-700 rounded-2xl p-8 text-center text-gray-500">Belum ada tugas di kelas ini.</div>
            <?php endif; ?>
        </div>

        <?php if($is_owner): ?>
        <div>
            <div class="bg-surface border border-gray-800 rounded-2xl p-5 sm:p-6 shadow-xl md:sticky md:top-24">
                <h3 class="text-lg font-bold text-white mb-4 border-b border-gray-800 pb-3"><i class="fas fa-plus-circle text-green-500 mr-2"></i> Buat Tugas Baru</h3>
                <form action="" method="POST" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 mb-1.5">Judul Tugas</label>
                        <input type="text" name="judul" class="w-full bg-darkbg border border-gray-700 text-white px-3 py-2.5 rounded-lg focus:border-blue-500 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 mb-1.5">Deskripsi</label>
                        <textarea name="deskripsi" rows="3" class="w-full bg-darkbg border border-gray-700 text-white px-3 py-2.5 rounded-lg focus:border-blue-500 text-sm resize-none" required></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 mb-1.5">Deadline</label>
                        <input type="datetime-local" name="deadline" min="<?= date('Y-m-d\\TH:i') ?>" class="w-full bg-darkbg border border-gray-700 text-white px-3 py-2.5 rounded-lg focus:border-blue-500 text-sm" required>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                    <button type="submit" name="buat_tugas" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-lg transition mt-2">
                        <i class="fas fa-paper-plane mr-2"></i> Publikasikan Tugas
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Modal Edit Deadline -->
<div id="deadlineModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden items-center justify-center transition-opacity opacity-0">
    <div class="bg-surface border border-gray-700 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden transform scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center p-5 border-b border-gray-800 bg-darkbg">
            <h3 class="text-xl font-bold text-white flex items-center"><i class="fas fa-calendar-alt text-yellow-500 mr-3"></i> Ubah Deadline Tugas</h3>
            <button onclick="closeDeadlineModal()" class="text-gray-500 hover:text-red-500 transition"><i class="fas fa-times text-xl"></i></button>
        </div>
        <form action="edit_deadline.php" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="task_id" id="deadline_task_id">
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Deadline Baru</label>
                <input type="datetime-local" name="deadline" id="deadline_date" min="<?= date('Y-m-d\\TH:i') ?>" class="w-full bg-darkbg border border-gray-700 text-white px-4 py-3 rounded-xl focus:border-yellow-500 transition" required>
            </div>
            <div class="flex justify-end gap-3 pt-3">
                <button type="button" onclick="closeDeadlineModal()" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-white">Batal</button>
                <button type="submit" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-500 rounded-lg text-white font-bold">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('deadlineModal');
    const modalBox = modal.querySelector('.bg-surface');
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
        modal.classList.add('opacity-0');
        modalBox.classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 300);
    }
</script>
</body></html>