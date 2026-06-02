<?php 
include 'includes/db.php'; 
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }

$user = $_SESSION['user']; 
$role = $user['role'];

// Helper: cek apakah kolom ada di sebuah tabel (gunakan query langsung untuk kompatibilitas)
function columnExists($conn, $table, $column) {
    $table = preg_replace('/[^0-9a-zA-Z_]/', '', $table);
    $col = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `" . $table . "` LIKE '" . $col . "'");
    if ($res === false) return false;
    $exists = ($res->num_rows > 0);
    return $exists;
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// =========================================================
// PROSES BATALKAN PENGUMPULAN (MAHASISWA)
// =========================================================
if (isset($_POST['batalkan_pengumpulan']) && $role === 'mahasiswa') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die("CSRF token tidak valid.");
    }
    $task_id = (int)$_POST['task_id'];
    
    // Cek deadline
    $stmt_deadline = $conn->prepare("SELECT deadline FROM tasks WHERE id = ?");
    $stmt_deadline->bind_param("i", $task_id);
    $stmt_deadline->execute();
    $deadline = $stmt_deadline->get_result()->fetch_assoc()['deadline'];
    $stmt_deadline->close();
    if (strtotime($deadline) < time()) {
        die("Tidak dapat membatalkan karena sudah melewati deadline.");
    }
    
    // Cek apakah sudah ada submission dan belum dinilai (tanpa mengasumsikan kolom `nilai` ada)
    if (columnExists($conn, 'task_submissions', 'nilai')) {
        $sql_check = "SELECT nilai, file_path FROM task_submissions WHERE task_id = ? AND mahasiswa_id = ?";
    } else {
        $sql_check = "SELECT file_path FROM task_submissions WHERE task_id = ? AND mahasiswa_id = ?";
    }
    $stmt_sub = $conn->prepare($sql_check);
    $stmt_sub->bind_param("ii", $task_id, $user['id']);
    $stmt_sub->execute();
    $sub = $stmt_sub->get_result()->fetch_assoc();
    $stmt_sub->close();
    if (!$sub) {
        die("Anda belum mengumpulkan tugas ini.");
    }
    if (array_key_exists('nilai', $sub) && $sub['nilai'] !== null) {
        die("Tugas sudah dinilai, tidak dapat dibatalkan.");
    }
    
    // Hapus file fisik
    $file_path = $sub['file_path'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    // Hapus dari database
    $del = $conn->prepare("DELETE FROM task_submissions WHERE task_id = ? AND mahasiswa_id = ?");
    $del->bind_param("ii", $task_id, $user['id']);
    $del->execute();
    $del->close();
    
    // Notifikasi untuk mahasiswa
    $desc_mhs = "Anda telah membatalkan pengumpulan tugas (ID: $task_id). Anda dapat mengumpulkan ulang sebelum deadline.";
    $stmt_act = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'tugas')");
    $stmt_act->bind_param("is", $user['id'], $desc_mhs);
    $stmt_act->execute();
    
    // Notifikasi untuk dosen
    $stmt_dosen = $conn->prepare("SELECT c.dosen_id FROM tasks t JOIN classes c ON t.class_id = c.id WHERE t.id = ?");
    $stmt_dosen->bind_param("i", $task_id);
    $stmt_dosen->execute();
    $dosen_id = $stmt_dosen->get_result()->fetch_assoc()['dosen_id'];
    $stmt_dosen->close();
    $desc_dos = "Mahasiswa " . $user['nama'] . " telah membatalkan pengumpulan tugas (ID: $task_id).";
    $stmt_act2 = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'tugas')");
    $stmt_act2->bind_param("is", $dosen_id, $desc_dos);
    $stmt_act2->execute();
    
    header("Location: tugas.php?pesan=batal_sukses");
    exit();
}

// =========================================================
// PROSES UPLOAD TUGAS (dengan pengecekan submit)
// =========================================================
if (isset($_POST['kirim_tugas']) && $role === 'mahasiswa') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die("CSRF token tidak valid.");
    }
    $task_id = (int)$_POST['task_id'];
    
    // Cek sudah pernah submit?
    $stmt_check = $conn->prepare("SELECT id FROM task_submissions WHERE task_id = ? AND mahasiswa_id = ?");
    $stmt_check->bind_param("ii", $task_id, $user['id']);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        die("Anda sudah mengumpulkan tugas ini. Batalkan terlebih dahulu jika ingin mengganti file.");
    }
    $stmt_check->close();
    
    // Cek akses
    $stmt_cek = $conn->prepare("
        SELECT 1 FROM tasks t 
        JOIN class_members cm ON t.class_id = cm.class_id 
        WHERE t.id = ? AND cm.mahasiswa_id = ?
    ");
    $stmt_cek->bind_param("ii", $task_id, $user['id']);
    $stmt_cek->execute();
    if ($stmt_cek->get_result()->num_rows == 0) {
        die("Anda tidak memiliki akses ke tugas ini.");
    }
    $stmt_cek->close();
    
    // Cek deadline
    $stmt_deadline = $conn->prepare("SELECT deadline, judul, class_id FROM tasks WHERE id = ?");
    $stmt_deadline->bind_param("i", $task_id);
    $stmt_deadline->execute();
    $task_data = $stmt_deadline->get_result()->fetch_assoc();
    if (strtotime($task_data['deadline']) < time()) {
        header("Location: tugas.php?pesan=terlambat");
        exit();
    }
    $judul_tugas = $task_data['judul'];
    $class_id = $task_data['class_id'];
    $stmt_deadline->close();
    
    // Ambil nama kelas dan dosen_id
    $stmt_kelas = $conn->prepare("SELECT nama_kelas, dosen_id FROM classes WHERE id = ?");
    $stmt_kelas->bind_param("i", $class_id);
    $stmt_kelas->execute();
    $kelas_data = $stmt_kelas->get_result()->fetch_assoc();
    $nama_kelas = $kelas_data['nama_kelas'];
    $dosen_id = $kelas_data['dosen_id'];
    $stmt_kelas->close();
    
    // Upload file
    if (isset($_FILES['file_tugas']) && $_FILES['file_tugas']['error'] === 0) {
        $nama_file = $_FILES['file_tugas']['name'];
        $tmp_name = $_FILES['file_tugas']['tmp_name'];
        $max_size = 5 * 1024 * 1024;
        $allowed_mime = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmp_name);
        finfo_close($finfo);
        if (!in_array($mime, $allowed_mime)) {
            header("Location: tugas.php?pesan=format_salah");
            exit();
        }
        if ($_FILES['file_tugas']['size'] > $max_size) {
            header("Location: tugas.php?pesan=terlalu_besar");
            exit();
        }
        $nama_simpan = bin2hex(random_bytes(16)) . '.bin';
        $folder = 'uploads/tugas/';
        if (!is_dir($folder)) mkdir($folder, 0777, true);
        $file_path = $folder . $nama_simpan;
        if (move_uploaded_file($tmp_name, $file_path)) {
            $stmt_sub = $conn->prepare("INSERT INTO task_submissions (task_id, mahasiswa_id, file_path, original_name) VALUES (?, ?, ?, ?)");
            $stmt_sub->bind_param("iiss", $task_id, $user['id'], $file_path, $nama_file);
            $stmt_sub->execute();
            $stmt_sub->close();
            
            $desc_mhs = "Anda telah mengirimkan tugas: " . htmlspecialchars($judul_tugas) . " (" . htmlspecialchars($nama_kelas) . ")";
            $stmt_act = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'tugas')");
            $stmt_act->bind_param("is", $user['id'], $desc_mhs);
            $stmt_act->execute();
            
            $desc_dos = "Mahasiswa " . $user['nama'] . " telah mengumpulkan tugas: " . $judul_tugas;
            $stmt_act2 = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'tugas')");
            $stmt_act2->bind_param("is", $dosen_id, $desc_dos);
            $stmt_act2->execute();
            
            header("Location: tugas.php?pesan=berhasil");
            exit();
        } else {
            header("Location: tugas.php?pesan=gagal_file");
            exit();
        }
    } else {
        header("Location: tugas.php?pesan=gagal_file");
        exit();
    }
}

include 'includes/header.php'; 
include 'includes/navbar.php'; 
?>

<main class="max-w-4xl mx-auto p-6 md:p-10">
    <?php if(isset($_GET['pesan'])): ?>
        <?php 
            $msg = ""; $color = "";
            if($_GET['pesan'] == 'berhasil') { $msg = "Tugas berhasil dikirim!"; $color = "bg-green-500/20 border-green-500 text-green-400"; }
            if($_GET['pesan'] == 'batal_sukses') { $msg = "Pengumpulan berhasil dibatalkan. Anda dapat mengumpulkan ulang."; $color = "bg-yellow-500/20 border-yellow-500 text-yellow-400"; }
            if($_GET['pesan'] == 'format_salah') { $msg = "Format ditolak! Harap unggah file PDF atau Word."; $color = "bg-orange-500/20 border-orange-500 text-orange-400"; }
            if($_GET['pesan'] == 'terlalu_besar') { $msg = "File terlalu besar. Maksimal 5MB."; $color = "bg-red-500/20 border-red-500 text-red-400"; }
            if($_GET['pesan'] == 'gagal_file') { $msg = "Gagal mengirim. Pastikan Anda memilih file."; $color = "bg-red-500/20 border-red-500 text-red-400"; }
            if($_GET['pesan'] == 'terlambat') { $msg = "Tugas sudah melewati deadline. Tidak dapat dikumpulkan."; $color = "bg-red-500/20 border-red-500 text-red-400"; }
        ?>
        <div class="mb-6 px-4 py-3 border <?= $color ?> rounded-xl flex items-center justify-between">
            <span><i class="fas fa-info-circle mr-2"></i> <?= htmlspecialchars($msg) ?></span>
            <button onclick="this.parentElement.style.display='none'"><i class="fas fa-times"></i></button>
        </div>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row md:items-center justify-between border-b border-gray-800 pb-5 mb-8 gap-4">
        <div class="flex items-center">
            <div class="w-1.5 h-8 bg-blue-500 rounded-full mr-4"></div>
            <h2 class="text-3xl font-extrabold text-white tracking-wide">Daftar Tugas</h2>
        </div>
        <?php if($role === 'mahasiswa'): ?>
        <form method="GET" action="" class="flex items-center bg-darkbg border border-gray-700 rounded-xl px-3 py-1">
            <i class="fas fa-filter text-gray-500 mr-2 text-xs"></i>
            <select name="filter_kelas" onchange="this.form.submit()" class="bg-transparent text-gray-300 text-sm focus:outline-none py-2 cursor-pointer">
                <option value="">Semua Kelas</option>
                <?php
                $stmt_filter = $conn->prepare("SELECT c.id, c.nama_kelas FROM classes c JOIN class_members cm ON c.id = cm.class_id WHERE cm.mahasiswa_id = ?");
                $stmt_filter->bind_param("i", $user['id']);
                $stmt_filter->execute();
                $res_filter = $stmt_filter->get_result();
                while($f = $res_filter->fetch_assoc()):
                    $sel = (isset($_GET['filter_kelas']) && $_GET['filter_kelas'] == $f['id']) ? 'selected' : '';
                ?>
                    <option value="<?= $f['id'] ?>" <?= $sel ?>><?= htmlspecialchars($f['nama_kelas']) ?></option>
                <?php endwhile; ?>
                <?php $stmt_filter->close(); ?>
            </select>
        </form>
        <?php endif; ?>
    </div>

    <div class="space-y-6">
        <?php
        // Query tugas
        if ($role === 'admin') {
            $stmt = $conn->prepare("SELECT t.*, c.nama_kelas, c.dosen_id, u.nama as dosen FROM tasks t JOIN classes c ON t.class_id = c.id JOIN users u ON c.dosen_id = u.id ORDER BY t.created_at DESC");
            $stmt->execute();
            $q_tugas = $stmt->get_result();
        } elseif ($role === 'dosen') {
            $stmt = $conn->prepare("SELECT t.*, c.nama_kelas, c.dosen_id, u.nama as dosen FROM tasks t JOIN classes c ON t.class_id = c.id JOIN users u ON c.dosen_id = u.id WHERE c.dosen_id = ? ORDER BY t.created_at DESC");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $q_tugas = $stmt->get_result();
        } else {
            $sql = "SELECT t.*, c.nama_kelas, c.dosen_id, u.nama as dosen 
                    FROM tasks t 
                    JOIN classes c ON t.class_id = c.id 
                    JOIN class_members cm ON c.id = cm.class_id 
                    JOIN users u ON c.dosen_id = u.id 
                    WHERE cm.mahasiswa_id = ?";
            $params = [$user['id']];
            $types = "i";
            if (isset($_GET['filter_kelas']) && !empty($_GET['filter_kelas'])) {
                $f_id = (int)$_GET['filter_kelas'];
                $sql .= " AND c.id = ?";
                $params[] = $f_id;
                $types .= "i";
            }
            $sql .= " ORDER BY t.created_at DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $q_tugas = $stmt->get_result();
        }

        if ($q_tugas->num_rows > 0):
            while($t = $q_tugas->fetch_assoc()):
                $deadline_time = strtotime($t['deadline']);
                $is_expired = (time() > $deadline_time);
                
                $submission = null;
                $has_submitted = false;
                $can_cancel = false;
                if ($role === 'mahasiswa') {
                    // Tentukan kolom yang ada: nilai, feedback
                    $has_nilai = columnExists($conn, 'task_submissions', 'nilai');
                    $has_feedback = columnExists($conn, 'task_submissions', 'feedback');
                    $cols = ['id', 'submitted_at', 'file_path'];
                    if ($has_nilai) $cols[] = 'nilai';
                    if ($has_feedback) $cols[] = 'feedback';
                    $sql_sub = "SELECT " . implode(', ', $cols) . " FROM task_submissions WHERE task_id = ? AND mahasiswa_id = ?";
                    $stmt_sub = $conn->prepare($sql_sub);
                    $stmt_sub->bind_param("ii", $t['id'], $user['id']);
                    $stmt_sub->execute();
                    $submission = $stmt_sub->get_result()->fetch_assoc();
                    $stmt_sub->close();
                    $has_submitted = !empty($submission['id']);
                    if ($has_submitted && !$is_expired) {
                        if (array_key_exists('nilai', $submission)) {
                            if ($submission['nilai'] === null) $can_cancel = true;
                        } else {
                            // jika kolom nilai tidak ada, anggap belum dinilai sehingga bisa batalkan
                            $can_cancel = true;
                        }
                    }
                }
        ?>
            <div class="bg-surface rounded-2xl border border-gray-800 p-6 shadow-lg">
                <div class="flex flex-col md:flex-row justify-between items-start border-b border-gray-800 pb-4 mb-4 gap-4">
                    <div>
                        <h3 class="text-xl font-bold text-white"><?= htmlspecialchars($t['judul']) ?></h3>
                        <p class="text-sm text-gray-400 mt-1"><?= htmlspecialchars($t['nama_kelas']) ?> • Dosen: <?= htmlspecialchars($t['dosen']) ?></p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <?php if ($is_expired): ?>
                            <span class="px-3 py-1 bg-red-500/10 text-red-400 border border-red-500/50 text-xs font-bold rounded-lg">
                                <i class="fas fa-clock mr-1.5"></i> Tenggat: <?= date('d M Y, H:i', $deadline_time) ?>
                            </span>
                        <?php else: ?>
                            <span class="px-3 py-1 bg-blue-500/10 text-blue-400 border border-blue-500/50 text-xs font-bold rounded-lg">
                                <i class="fas fa-clock mr-1.5"></i> Tenggat: <?= date('d M Y, H:i', $deadline_time) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($role === 'dosen' && $t['dosen_id'] == $user['id']): ?>
                            <a href="lihat_pengumpulan.php?task_id=<?= $t['id'] ?>" class="text-xs bg-green-600 hover:bg-green-500 px-2 py-1 rounded text-white whitespace-nowrap">
                                <i class="fas fa-eye mr-1"></i> Lihat Pengumpulan
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="text-sm text-gray-300 mb-6"><?= nl2br(htmlspecialchars($t['deskripsi'])) ?></p>
                
                <?php if ($role === 'mahasiswa'): ?>
                    <?php if ($has_submitted): ?>
                        <div class="bg-green-500/10 border border-green-500/30 rounded-lg p-3 mb-4">
                            <p class="text-green-400 text-sm">
                                <i class="fas fa-check-circle mr-2"></i> 
                                Anda sudah mengumpulkan tugas ini pada <?= date('d M Y, H:i', strtotime($submission['submitted_at'])) ?>.
                            </p>
                        </div>
                        <?php if ((array_key_exists('nilai', $submission) && $submission['nilai'] !== null) || (array_key_exists('feedback', $submission) && !empty($submission['feedback']))): ?>
                            <div class="mt-4 p-3 bg-darkbg border border-gray-700 rounded-lg">
                                <?php if (array_key_exists('nilai', $submission) && $submission['nilai'] !== null): ?>
                                    <p class="text-sm"><span class="font-bold text-yellow-400">Nilai:</span> <span class="text-white"><?= htmlspecialchars($submission['nilai']) ?></span></p>
                                <?php endif; ?>
                                <?php if (array_key_exists('feedback', $submission) && !empty($submission['feedback'])): ?>
                                    <p class="text-sm mt-1"><span class="font-bold text-blue-400">Feedback:</span> <span class="text-gray-300"><?= nl2br(htmlspecialchars($submission['feedback'])) ?></span></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($can_cancel): ?>
                            <form method="POST" class="mt-4">
                                <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                                <button type="submit" name="batalkan_pengumpulan" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white text-sm font-bold rounded-xl transition" onclick="return confirm('Yakin ingin membatalkan pengumpulan? File akan dihapus dan Anda dapat mengumpulkan ulang.')">
                                    <i class="fas fa-trash-alt mr-2"></i> Batalkan Pengumpulan
                                </button>
                            </form>
                        <?php elseif ($submission['nilai'] !== null): ?>
                            <p class="text-gray-400 text-xs mt-2"><i class="fas fa-info-circle"></i> Tugas sudah dinilai, tidak dapat dibatalkan.</p>
                        <?php elseif ($is_expired): ?>
                            <p class="text-gray-400 text-xs mt-2"><i class="fas fa-clock"></i> Deadline sudah lewat, tidak dapat membatalkan.</p>
                        <?php endif; ?>
                        
                    <?php elseif ($is_expired): ?>
                        <p class="text-red-400 text-sm"><i class="fas fa-ban"></i> Tugas sudah melewati deadline. Tidak dapat mengumpulkan.</p>
                    <?php else: ?>
                        <form action="" method="POST" enctype="multipart/form-data" class="mt-4">
                            <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                            <div class="relative group">
                                <input type="file" name="file_tugas" accept=".pdf,.doc,.docx" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                <div class="border-2 border-dashed border-gray-700 bg-darkbg rounded-xl p-6 text-center group-hover:border-blue-500 transition">
                                    <i class="fas fa-cloud-upload-alt text-3xl text-gray-600 mb-2 group-hover:text-blue-500"></i>
                                    <p class="text-xs text-gray-400">Klik untuk memilih file <span class="text-blue-400 font-bold">PDF/Word</span></p>
                                    <p id="file-name-<?= $t['id'] ?>" class="text-[10px] text-gray-500 mt-1 italic">Belum ada file dipilih</p>
                                </div>
                            </div>
                            <div class="mt-4 flex justify-end">
                                <button type="submit" name="kirim_tugas" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-sm font-bold rounded-xl transition">Kirim Tugas</button>
                            </div>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endwhile; else: ?>
            <div class="bg-surface border border-dashed border-gray-700 rounded-2xl p-16 text-center">
                <i class="fas fa-clipboard-list text-4xl text-gray-700 mb-3"></i>
                <p class="text-gray-400">Tidak ada tugas ditemukan.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : "Belum ada file dipilih";
            const label = this.parentElement.querySelector('p[id^="file-name-"]');
            if(label) {
                label.innerText = "File terpilih: " + fileName;
                label.classList.remove('text-gray-500');
                label.classList.add('text-blue-400');
            }
        });
    });
</script>
</body></html>