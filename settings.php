<?php 
include 'includes/db.php'; 
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }

include 'includes/lang.php';

$user = $_SESSION['user']; 

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
// PROSES SIMPAN PROFIL (UPLOAD FOTO & BAHASA)
// =========================================================
if (isset($_POST['simpan_profil'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die("CSRF token tidak valid.");
    }
    if (isset($_POST['bahasa'])) { $_SESSION['lang'] = $_POST['bahasa']; }
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
        $ekstensi = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $nama_file_baru = 'profil_' . $user['id'] . '_' . time() . '.' . $ekstensi;
        $folder_tujuan = 'uploads/';
        if (!is_dir($folder_tujuan)) mkdir($folder_tujuan, 0777, true);
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $folder_tujuan . $nama_file_baru)) {
            $stmt = $conn->prepare("UPDATE users SET foto_profil = ? WHERE id = ?");
            $stmt->bind_param("si", $nama_file_baru, $user['id']);
            $stmt->execute();
            $stmt->close();
            $_SESSION['user']['foto_profil'] = $nama_file_baru;
        }
    }
    header("Location: settings.php?pesan=sukses");
    exit();
}

// =========================================================
// PROSES GANTI PASSWORD (TAMBAHAN)
// =========================================================
$password_error = "";
$password_success = "";
if (isset($_POST['ganti_password'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die("CSRF token tidak valid.");
    }
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $password_error = "Semua field password harus diisi.";
    } elseif (strlen($new_password) < 6) {
        $password_error = "Password baru minimal 6 karakter.";
    } elseif ($new_password !== $confirm_password) {
        $password_error = "Konfirmasi password baru tidak cocok.";
    } else {
        // Ambil hash password lama dari database
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if (password_verify($old_password, $row['password'])) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->bind_param("si", $new_hash, $user['id']);
            if ($update->execute()) {
                $password_success = "Password berhasil diubah. Silakan login kembali dengan password baru.";
                // Opsional: logout user setelah ganti password? Biarkan saja, tapi lebih aman logout.
                // Kita bisa logout otomatis dengan menghapus session.
                session_destroy();
                header("Location: login.php?pesan=password_changed");
                exit();
            } else {
                $password_error = "Gagal mengupdate password. Coba lagi.";
            }
            $update->close();
        } else {
            $password_error = "Password lama salah.";
        }
    }
}

include 'includes/header.php'; 
include 'includes/navbar.php'; 
?>

<main class="max-w-4xl mx-auto p-6 md:p-10">
    <?php if(isset($_GET['pesan']) && $_GET['pesan'] == 'sukses'): ?>
        <div class="mb-6 px-4 py-3 bg-green-500/20 border border-green-500 text-green-400 rounded-xl flex items-center justify-between">
            <span><i class="fas fa-check-circle mr-2"></i> Perubahan profil berhasil disimpan!</span>
            <button onclick="this.parentElement.style.display='none'" class="hover:text-white"><i class="fas fa-times"></i></button>
        </div>
    <?php endif; ?>
    <?php if ($password_success): ?>
        <div class="mb-6 px-4 py-3 bg-green-500/20 border border-green-500 text-green-400 rounded-xl">
            <?= htmlspecialchars($password_success) ?>
        </div>
    <?php endif; ?>
    <?php if ($password_error): ?>
        <div class="mb-6 px-4 py-3 bg-red-500/20 border border-red-500 text-red-400 rounded-xl">
            <?= htmlspecialchars($password_error) ?>
        </div>
    <?php endif; ?>

    <div class="flex items-center border-b border-gray-800 pb-5 mb-8">
        <div class="w-1.5 h-8 bg-gray-500 rounded-full shadow-[0_0_10px_rgba(107,114,128,0.6)] mr-4"></div>
        <h2 class="text-3xl font-extrabold text-white tracking-wide"><?= t('setelan_aplikasi') ?></h2>
    </div>

    <div class="space-y-6">
        <!-- Form Edit Profil (Foto & Bahasa) -->
        <form action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
            <section class="bg-surface p-6 md:p-8 rounded-2xl shadow-xl border border-gray-800">
                <h3 class="font-bold text-white text-lg mb-6 flex items-center border-b border-gray-800 pb-4"><i class="fas fa-user-circle mr-3 text-blue-500 text-xl"></i> <?= t('setelan_akun') ?></h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2"><?= t('perbarui_foto') ?></label>
                        <input type="file" name="foto" accept="image/*" class="block w-full text-sm text-gray-400 border border-gray-700 bg-darkbg rounded-xl cursor-pointer">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2"><?= t('bahasa') ?></label>
                        <select name="bahasa" class="w-full bg-darkbg border border-gray-700 text-white px-4 py-3 rounded-xl">
                            <option value="id" <?= ($_SESSION['lang'] == 'id') ? 'selected' : '' ?>>Bahasa Indonesia</option>
                            <option value="en" <?= ($_SESSION['lang'] == 'en') ? 'selected' : '' ?>>English (US)</option>
                        </select>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="submit" name="simpan_profil" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-xl transition">
                        <i class="fas fa-save mr-2"></i> <?= t('simpan') ?>
                    </button>
                </div>
            </section>
        </form>

        <!-- Form Ganti Password (Sekarang Fungsional) -->
        <form action="" method="POST" class="mt-6">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
            <section class="bg-surface p-6 md:p-8 rounded-2xl shadow-xl border border-gray-800">
                <h3 class="font-bold text-white text-lg mb-4 flex items-center border-b border-gray-800 pb-4"><i class="fas fa-key mr-3 text-yellow-500 text-xl"></i> Ganti Password</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Password Lama</label>
                        <input type="password" name="old_password" required class="w-full bg-darkbg border border-gray-700 text-white px-4 py-3 rounded-xl">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Password Baru</label>
                        <input type="password" name="new_password" required minlength="6" class="w-full bg-darkbg border border-gray-700 text-white px-4 py-3 rounded-xl">
                        <p class="text-xs text-gray-500 mt-1">Minimal 6 karakter</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Konfirmasi Password Baru</label>
                        <input type="password" name="confirm_password" required class="w-full bg-darkbg border border-gray-700 text-white px-4 py-3 rounded-xl">
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="submit" name="ganti_password" class="px-6 py-2.5 bg-yellow-600 hover:bg-yellow-500 text-white font-bold rounded-xl transition">
                        <i class="fas fa-sync-alt mr-2"></i> Ganti Password
                    </button>
                </div>
            </section>
        </form>

        <!-- Bagian Notifikasi Email, Tema, Sinkronisasi, Logout (tidak diubah) -->
        <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-surface p-6 md:p-8 rounded-2xl shadow-xl border border-gray-800">
                <h3 class="font-bold text-white text-lg mb-4 flex items-center border-b border-gray-800 pb-4"><i class="fas fa-envelope mr-3 text-red-500 text-xl"></i> <?= t('notif_email') ?></h3>
                <input type="email" value="<?= htmlspecialchars($user['email']) ?>" readonly class="w-full bg-darkbg border border-gray-700 text-gray-400 px-4 py-3 rounded-xl cursor-not-allowed">
            </div>
            <div class="bg-surface p-6 md:p-8 rounded-2xl shadow-xl border border-gray-800">
                <h3 class="font-bold text-white text-lg mb-4 flex items-center border-b border-gray-800 pb-4"><i class="fas fa-palette mr-3 text-purple-500 text-xl"></i> <?= t('tema') ?></h3>
                <div class="flex items-center justify-between mt-2">
                    <div><p class="text-white font-bold text-sm"><?= t('ganti_tema') ?></p><p class="text-xs text-gray-400 mt-1"><?= t('ubah_gelap_terang') ?></p></div>
                    <button onclick="toggleTheme()" class="flex items-center px-4 py-3 bg-darkbg border border-gray-700 hover:border-blue-500 rounded-xl transition group">
                        <i class="fas fa-moon text-blue-400 group-hover:hidden block mr-2"></i>
                        <i class="fas fa-sun text-yellow-400 hidden group-hover:block mr-2"></i>
                        <span class="text-gray-300 font-bold text-sm"><?= t('ubah') ?></span>
                    </button>
                </div>
            </div>
        </section>

        <section class="bg-surface p-6 md:p-8 rounded-2xl shadow-xl border border-gray-800 flex justify-between items-center">
            <div><h3 class="font-bold text-white flex items-center mb-1"><i class="fas fa-sync mr-3 text-green-500"></i> <?= t('sinkronisasi') ?></h3><p class="text-sm text-gray-400"><?= t('info_sinkron') ?></p></div>
            <label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" class="sr-only peer"><div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-blue-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all"></div></label>
        </section>

        <section class="pt-4">
            <form action="logout.php" method="POST">
                <button type="submit" class="w-full bg-red-500/10 text-red-500 py-3.5 rounded-xl border border-red-500/50 font-bold hover:bg-red-500 hover:text-white transition flex items-center justify-center">
                    <i class="fas fa-sign-out-alt mr-2"></i> <?= t('keluar') ?>
                </button>
            </form>
        </section>
    </div>
</main>

<script>
    function toggleTheme() {
        if(document.documentElement.classList.contains('light-mode')) {
            document.documentElement.classList.remove('light-mode');
            localStorage.setItem('theme', 'dark');
        } else {
            document.documentElement.classList.add('light-mode');
            localStorage.setItem('theme', 'light');
        }
    }
</script>
</body></html>