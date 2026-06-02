<?php 
include 'includes/db.php'; 

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];
$role = $user['role'] ?? '';
$user_id = $user['id'] ?? 0;

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// PROSES BUAT KELAS
if (isset($_POST['action_buat']) && ($role === 'dosen' || $role === 'admin')) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die("CSRF token tidak valid.");
    }
    $nama_kelas = trim($_POST['nama_kelas']);
    $matpel = trim($_POST['mata_pelajaran']);
    $ruang = trim($_POST['ruang']);
    $dosen_id = $user_id;
    $kode_kelas = strtoupper(substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 7));
    $deskripsi = "$matpel - $ruang";

    $stmt = $conn->prepare("INSERT INTO classes (nama_kelas, deskripsi, kode_kelas, dosen_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $nama_kelas, $deskripsi, $kode_kelas, $dosen_id);
    $stmt->execute();
    $stmt->close();

    $desc = "Telah membuat kelas baru: " . $nama_kelas;
    $stmt_act = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'kelas_baru')");
    $stmt_act->bind_param("is", $dosen_id, $desc);
    $stmt_act->execute();
    $stmt_act->close();

    header("Location: index.php?pesan=kelas_dibuat");
    exit();
}

// PROSES GABUNG KELAS
if (isset($_POST['action_gabung']) && $role === 'mahasiswa') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die("CSRF token tidak valid.");
    }
    $kode_kelas = strtoupper(trim($_POST['kode_kelas']));
    $mhs_id = $user_id;

    $stmt = $conn->prepare("SELECT id, nama_kelas FROM classes WHERE kode_kelas = ?");
    $stmt->bind_param("s", $kode_kelas);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $kelas = $result->fetch_assoc();
        $class_id = $kelas['id'];
        $nama_kelas = $kelas['nama_kelas'];

        $stmt2 = $conn->prepare("SELECT * FROM class_members WHERE class_id = ? AND mahasiswa_id = ?");
        $stmt2->bind_param("ii", $class_id, $mhs_id);
        $stmt2->execute();
        $cek = $stmt2->get_result();
        if ($cek->num_rows == 0) {
            $stmt3 = $conn->prepare("INSERT INTO class_members (class_id, mahasiswa_id) VALUES (?, ?)");
            $stmt3->bind_param("ii", $class_id, $mhs_id);
            $stmt3->execute();
            $stmt3->close();

            $desc = "Telah bergabung ke kelas: " . $nama_kelas;
            $stmt4 = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'gabung')");
            $stmt4->bind_param("is", $mhs_id, $desc);
            $stmt4->execute();
            $stmt4->close();

            header("Location: index.php?pesan=berhasil_gabung");
            exit();
        } else {
            header("Location: index.php?pesan=sudah_gabung");
            exit();
        }
    } else {
        header("Location: index.php?pesan=kode_salah");
        exit();
    }
    $stmt->close();
}

// Ambil data kelas
if ($role === 'dosen' || $role === 'admin') {
    $query = $conn->prepare("SELECT * FROM classes WHERE dosen_id = ? ORDER BY created_at DESC");
    $query->bind_param("i", $user_id);
} else {
    $query = $conn->prepare("SELECT c.* FROM classes c JOIN class_members cm ON c.id = cm.class_id WHERE cm.mahasiswa_id = ? ORDER BY cm.joined_at DESC");
    $query->bind_param("i", $user_id);
}
$query->execute();
$query_kelas = $query->get_result();

// Statistik akun terdaftar
$jumlah_mahasiswa = 0;
$jumlah_dosen = 0;
if ($role === 'admin') {
    $stmt_count = $conn->prepare("SELECT ROLE, COUNT(*) as total FROM users WHERE ROLE IN ('mahasiswa','dosen') GROUP BY ROLE");
    $stmt_count->execute();
    $res_count = $stmt_count->get_result();
    while ($row_count = $res_count->fetch_assoc()) {
        if ($row_count['ROLE'] === 'mahasiswa') {
            $jumlah_mahasiswa = (int)$row_count['total'];
        } elseif ($row_count['ROLE'] === 'dosen') {
            $jumlah_dosen = (int)$row_count['total'];
        }
    }
    $stmt_count->close();
} elseif ($role === 'dosen') {
    $stmt_count = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE ROLE = 'mahasiswa'");
    $stmt_count->execute();
    $jumlah_mahasiswa = (int)$stmt_count->get_result()->fetch_assoc()['total'];
    $stmt_count->close();
}

include 'includes/header.php';
include 'includes/navbar.php';
?>

<main class="max-w-7xl mx-auto p-4 md:p-8">
    <?php if(isset($_GET['pesan'])): ?>
        <?php 
            $msg = ""; $color = "";
            if($_GET['pesan'] == 'kelas_dibuat') { $msg = "Kelas baru berhasil dibuat!"; $color = "bg-green-500/20 border-green-500 text-green-400"; }
            if($_GET['pesan'] == 'berhasil_gabung') { $msg = "Berhasil bergabung ke kelas!"; $color = "bg-blue-500/20 border-blue-500 text-blue-400"; }
            if($_GET['pesan'] == 'sudah_gabung') { $msg = "Anda sudah terdaftar di kelas ini."; $color = "bg-yellow-500/20 border-yellow-500 text-yellow-400"; }
            if($_GET['pesan'] == 'kode_salah') { $msg = "Kode kelas tidak ditemukan!"; $color = "bg-red-500/20 border-red-500 text-red-400"; }
        ?>
        <div class="mb-6 px-4 py-3 rounded-xl border <?= $color ?> flex items-center justify-between">
            <span><i class="fas fa-info-circle mr-2"></i> <?= htmlspecialchars($msg) ?></span>
            <button onclick="this.parentElement.style.display='none'" class="hover:text-white"><i class="fas fa-times"></i></button>
        </div>
    <?php endif; ?>

    <!-- Welcome Banner dengan teks terjemahan -->
    <div class="relative overflow-hidden bg-surface border border-gray-800 rounded-3xl p-8 mb-10 shadow-2xl">
        <div class="relative z-10 flex flex-col md:flex-row justify-between items-center">
            <div class="text-center md:text-left mb-6 md:mb-0">
                <h2 class="text-3xl md:text-4xl font-extrabold text-white mb-2"><?= t('system_overview') ?></h2>
                <p class="text-gray-400 text-base md:text-lg"><?= t('welcome_back') ?>, <span class="text-gray-100 font-semibold"><?= htmlspecialchars($user['nama'] ?? ''); ?></span>.</p>
            </div>
            <div>
                <button onclick="openClassModal()" class="px-6 py-3.5 bg-gradient-to-r from-gray-800 to-gray-900 border border-gray-600 hover:border-redaccent text-white font-semibold rounded-xl transition-all flex items-center group">
                    <i class="fas fa-plus-circle text-redaccent mr-3 group-hover:rotate-90 transition-transform duration-300"></i> <?= t('kelola_kelas') ?>
                </button>
            </div>
        </div>

        <?php if ($role === 'admin' || $role === 'dosen'): ?>
            <div class="relative z-10 grid grid-cols-1 <?= $role === 'admin' ? 'md:grid-cols-2' : 'md:grid-cols-1' ?> gap-4 mt-6">
                <div class="bg-darkbg/70 border border-blue-500/30 rounded-2xl p-4">
                    <p class="text-xs uppercase tracking-widest text-gray-400 mb-1">Akun Mahasiswa Terdaftar</p>
                    <p class="text-3xl font-extrabold text-blue-400"><?= $jumlah_mahasiswa ?></p>
                </div>
                <?php if ($role === 'admin'): ?>
                    <div class="bg-darkbg/70 border border-yellow-500/30 rounded-2xl p-4">
                        <p class="text-xs uppercase tracking-widest text-gray-400 mb-1">Akun Dosen Terdaftar</p>
                        <p class="text-3xl font-extrabold text-yellow-400"><?= $jumlah_dosen ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Kolom Kelas Aktif -->
        <div class="lg:col-span-2 space-y-6">
            <div class="flex justify-between items-end border-b border-gray-800 pb-3">
                <h3 class="text-xl font-bold text-white flex items-center"><i class="fas fa-layer-group text-gray-500 mr-3"></i> <?= t('kelas_aktif') ?></h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <?php if ($query_kelas->num_rows > 0): ?>
                    <?php while ($kelas = $query_kelas->fetch_assoc()): ?>
                        <a href="detail_kelas.php?id=<?= $kelas['id'] ?>" class="group bg-surface rounded-2xl border border-gray-800 hover:border-blue-500 transition-all overflow-hidden relative cursor-pointer shadow-lg block">
                            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-500 to-purple-500"></div>
                            <div class="p-6">
                                <div class="flex justify-between items-start mb-4">
                                    <span class="bg-gray-800 text-xs px-2 py-1 rounded text-gray-400 border border-gray-700 tracking-widest font-mono"><i class="fas fa-key mr-1"></i> <?= htmlspecialchars($kelas['kode_kelas']); ?></span>
                                    <div class="w-8 h-8 rounded-full bg-darkbg flex items-center justify-center border border-gray-700 group-hover:bg-blue-600 transition"><i class="fas fa-arrow-right text-gray-500 group-hover:text-white -rotate-45"></i></div>
                                </div>
                                <h4 class="text-xl font-bold text-white mb-1"><?= htmlspecialchars($kelas['nama_kelas']); ?></h4>
                                <p class="text-sm text-gray-500 mb-4"><?= htmlspecialchars($kelas['deskripsi']); ?></p>
                                <div class="flex items-center justify-between pt-4 border-t border-gray-800">
                                    <span class="text-xs font-semibold text-blue-400"><i class="fas fa-sign-in-alt mr-1"></i> <?= t('masuk_kelas') ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-2 p-8 border border-dashed border-gray-700 rounded-2xl text-center bg-surface/50">
                        <i class="fas fa-folder-open text-4xl text-gray-600 mb-3"></i>
                        <p class="text-gray-500"><?= t('belum_ada_kelas') ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Aktivitas Terkini -->
        <div class="space-y-6">
            <div class="border-b border-gray-800 pb-3">
                <h3 class="text-xl font-bold text-white flex items-center"><i class="fas fa-bolt text-yellow-500 mr-3"></i> <?= t('aktivitas_terkini') ?></h3>
            </div>
            <div class="bg-surface rounded-2xl border border-gray-800 p-5 shadow-lg text-left">
                <?php
                $stmt_act = $conn->prepare("SELECT * FROM activities WHERE user_id = ? ORDER BY waktu DESC LIMIT 4");
                $stmt_act->bind_param("i", $user['id']);
                $stmt_act->execute();
                $query_act = $stmt_act->get_result();
                if ($query_act->num_rows > 0):
                    while($act = $query_act->fetch_assoc()):
                ?>
                    <div class="flex gap-4 items-start mb-4 border-b border-gray-800/50 pb-4 last:border-0 last:pb-0 last:mb-0">
                        <div class="mt-1 w-2 h-2 <?= ($act['tipe'] == 'kelas_baru') ? 'bg-blue-500' : 'bg-redaccent' ?> rounded-full flex-shrink-0"></div>
                        <div>
                            <p class="text-sm text-gray-200 font-semibold leading-tight"><?= htmlspecialchars($act['deskripsi']) ?></p>
                            <p class="text-xs text-gray-500 mt-1"><?= date('d M Y, H:i', strtotime($act['waktu'])) ?> WIB</p>
                        </div>
                    </div>
                <?php endwhile; else: ?>
                    <p class="text-sm text-gray-500 text-center py-4"><?= t('belum_ada_aktivitas') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Modal Opsi Kelas (terjemahan sudah disertakan) -->
<div id="classModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden items-center justify-center transition-opacity opacity-0">
    <div class="bg-surface border border-gray-700 rounded-2xl w-full max-w-lg shadow-[0_0_50px_rgba(0,0,0,0.8)] overflow-hidden transform scale-95 transition-transform duration-300" id="classModalBox">
        <div class="flex justify-between items-center p-5 border-b border-gray-800 bg-darkbg">
            <h3 class="text-xl font-bold text-white flex items-center"><i class="fas fa-chalkboard text-redaccent mr-3"></i> <?= t('kelola_kelas') ?></h3>
            <button onclick="closeClassModal()" class="text-gray-500 hover:text-redaccent transition"><i class="fas fa-times text-xl"></i></button>
        </div>
        <div class="flex border-b border-gray-800 bg-darkbg">
            <button onclick="switchTab('gabung')" id="tab-gabung" class="flex-1 py-3 text-sm font-bold text-white border-b-2 border-blue-500 bg-surface transition"><?= t('gabung_kelas') ?></button>
            <button onclick="switchTab('buat')" id="tab-buat" class="flex-1 py-3 text-sm font-bold text-gray-500 hover:text-gray-300 border-b-2 border-transparent transition"><?= t('buat_kelas') ?></button>
        </div>
        <div id="content-gabung" class="p-6">
            <div class="bg-darkbg border border-gray-700 rounded-xl p-4 flex items-center justify-between mb-4">
                <div class="flex items-center space-x-4">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['nama'] ?? '') ?>&background=151E32&color=fff" class="w-12 h-12 rounded-full border border-gray-600">
                    <div><p class="text-sm font-bold text-white"><?= htmlspecialchars($user['email'] ?? '') ?></p><p class="text-xs text-gray-500 uppercase tracking-wider"><?= htmlspecialchars($role) ?></p></div>
                </div>
                <?php if ($role !== 'admin'): ?>
                    <a href="logout.php" class="text-xs font-bold text-blue-500 hover:text-white hover:bg-blue-600 transition border border-blue-500/50 px-3 py-2 rounded-lg"><?= t('ganti_akun') ?></a>
                <?php endif; ?>
            </div>
            <div class="mb-5 bg-blue-900/10 border border-blue-500/30 p-3.5 rounded-xl flex items-start">
                <i class="fas fa-info-circle text-blue-400 mt-0.5 mr-3"></i>
                <p class="text-xs text-gray-400 leading-relaxed"><?= t('info_email') ?></p>
            </div>
            <form action="" method="POST">
                <div class="mb-6">
                    <label class="block text-xs font-bold text-gray-400 mb-2 uppercase tracking-wider"><?= t('kode_kelas_label') ?></label>
                    <input type="text" name="kode_kelas" placeholder="<?= t('kode_kelas_placeholder') ?>" minlength="6" maxlength="8" class="w-full bg-darkbg border border-gray-700 text-white px-5 py-4 rounded-xl focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition uppercase font-mono tracking-widest" required>
                </div>
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                <button type="submit" name="action_gabung" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3.5 rounded-xl transition"><?= t('gabung_sekarang') ?></button>
            </form>
        </div>
        <div id="content-buat" class="p-6 hidden">
            <form action="" method="POST" class="space-y-4">
                <div><label class="block text-xs font-bold text-gray-400 mb-1.5 uppercase tracking-wider"><?= t('nama_kelas_label') ?></label><input type="text" name="nama_kelas" placeholder="<?= t('nama_kelas_placeholder') ?>" class="w-full bg-darkbg border border-gray-700 text-white px-4 py-3 rounded-xl focus:outline-none focus:border-redaccent transition text-sm" required></div>
                <div><label class="block text-xs font-bold text-gray-400 mb-1.5 uppercase tracking-wider"><?= t('mata_kuliah_label') ?></label><input type="text" name="mata_pelajaran" placeholder="<?= t('mata_kuliah_placeholder') ?>" class="w-full bg-darkbg border border-gray-700 text-white px-4 py-3 rounded-xl focus:outline-none focus:border-redaccent transition text-sm" required></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-xs font-bold text-gray-400 mb-1.5 uppercase tracking-wider"><?= t('ruang_label') ?></label><input type="text" name="ruang" placeholder="<?= t('ruang_placeholder') ?>" class="w-full bg-darkbg border border-gray-700 text-white px-4 py-3 rounded-xl focus:outline-none focus:border-redaccent transition text-sm" required></div>
                    <div><label class="block text-xs font-bold text-gray-400 mb-1.5 uppercase tracking-wider"><?= t('nama_dosen_label') ?></label><input type="text" name="nama_dosen" value="<?= ($role === 'dosen') ? htmlspecialchars($user['nama'] ?? '') : '' ?>" placeholder="<?= t('nama_dosen_placeholder') ?>" class="w-full bg-darkbg border border-gray-700 text-white px-4 py-3 rounded-xl focus:outline-none focus:border-redaccent transition text-sm" required></div>
                </div>
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                <button type="submit" name="action_buat" class="w-full mt-2 bg-gradient-to-r from-red-600 to-red-800 hover:from-red-500 hover:to-red-700 text-white font-bold py-3.5 rounded-xl transition"><?= t('buat_kelas_button') ?></button>
            </form>
        </div>
    </div>
</div>

<script>
    const modal = document.getElementById('classModal');
    const modalBox = document.getElementById('classModalBox');
    function openClassModal() { modal.classList.remove('hidden'); modal.classList.add('flex'); setTimeout(() => { modal.classList.remove('opacity-0'); modalBox.classList.remove('scale-95'); }, 10); }
    function closeClassModal() { modal.classList.add('opacity-0'); modalBox.classList.add('scale-95'); setTimeout(() => { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 300); }
    function switchTab(tabName) {
        const tabGabung = document.getElementById('tab-gabung');
        const tabBuat = document.getElementById('tab-buat');
        const contentGabung = document.getElementById('content-gabung');
        const contentBuat = document.getElementById('content-buat');
        if (tabName === 'gabung') {
            tabGabung.classList.replace('text-gray-500', 'text-white');
            tabGabung.classList.replace('border-transparent', 'border-blue-500');
            tabGabung.classList.replace('hover:text-gray-300', 'bg-surface');
            tabBuat.classList.replace('text-white', 'text-gray-500');
            tabBuat.classList.replace('border-redaccent', 'border-transparent');
            tabBuat.classList.add('hover:text-gray-300');
            tabBuat.classList.remove('bg-surface');
            contentGabung.classList.remove('hidden');
            contentBuat.classList.add('hidden');
        } else {
            tabBuat.classList.replace('text-gray-500', 'text-white');
            tabBuat.classList.replace('border-transparent', 'border-redaccent');
            tabBuat.classList.replace('hover:text-gray-300', 'bg-surface');
            tabGabung.classList.replace('text-white', 'text-gray-500');
            tabGabung.classList.replace('border-blue-500', 'border-transparent');
            tabGabung.classList.add('hover:text-gray-300');
            tabGabung.classList.remove('bg-surface');
            contentBuat.classList.remove('hidden');
            contentGabung.classList.add('hidden');
        }
    }
</script>
</body>
</html>