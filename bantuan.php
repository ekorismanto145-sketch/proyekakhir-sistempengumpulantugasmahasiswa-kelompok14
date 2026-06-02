<?php 
include 'includes/db.php'; 
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }

$user = $_SESSION['user']; 
$role = $user['role'];

if ($role === 'admin') {
    header("Location: notifikasi.php");
    exit();
}

// Proses kirim keluhan
if (isset($_POST['kirim_keluhan'])) {
    $pesan = trim($_POST['pesan']);
    if (!empty($pesan)) {
        $stmt = $conn->prepare("INSERT INTO reports (user_id, user_nama, user_role, pesan) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user['id'], $user['nama'], $role, $pesan);
        $stmt->execute();
        $stmt->close();

        $pesan_notif = 'Keluhan baru dari ' . $user['nama'] . ' (' . $role . ')';
        $stmt_admin = $conn->prepare("SELECT id FROM users WHERE role = 'admin'");
        $stmt_admin->execute();
        $admins = $stmt_admin->get_result();

        if ($admins && $admins->num_rows > 0) {
            $stmt_notif = $conn->prepare("INSERT INTO activities (user_id, deskripsi, tipe) VALUES (?, ?, 'keluhan')");
            while ($admin = $admins->fetch_assoc()) {
                $admin_id = (int)$admin['id'];
                $stmt_notif->bind_param("is", $admin_id, $pesan_notif);
                $stmt_notif->execute();
            }
            $stmt_notif->close();
        }
        $stmt_admin->close();

        header("Location: bantuan.php?pesan=terkirim");
        exit();
    } else {
        header("Location: bantuan.php?pesan=gagal");
        exit();
    }
}

include 'includes/header.php'; 
include 'includes/navbar.php'; 
?>

<main class="max-w-4xl mx-auto p-6 md:p-10">
    <div class="flex items-center border-b border-gray-800 pb-5 mb-8">
        <div class="w-1.5 h-8 bg-yellow-500 rounded-full mr-4 shadow-[0_0_10px_rgba(234,179,8,0.6)]"></div>
        <h2 class="text-3xl font-extrabold text-white tracking-wide">Pusat Bantuan</h2>
    </div>

    <?php if(isset($_GET['pesan'])): ?>
        <?php if($_GET['pesan'] == 'terkirim'): ?>
            <div class="mb-6 px-4 py-3 bg-green-500/20 border border-green-500 text-green-400 rounded-xl">
                <i class="fas fa-check-circle mr-2"></i> Keluhan berhasil dikirim. Admin akan segera menindaklanjuti.
            </div>
        <?php elseif($_GET['pesan'] == 'gagal'): ?>
            <div class="mb-6 px-4 py-3 bg-red-500/20 border border-red-500 text-red-400 rounded-xl">
                <i class="fas fa-exclamation-circle mr-2"></i> Gagal mengirim. Pesan tidak boleh kosong.
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="space-y-6">
            <div class="bg-surface border border-gray-800 p-6 rounded-2xl shadow-lg">
                <h3 class="text-lg font-bold text-white mb-2 flex items-center"><i class="fas fa-info-circle text-blue-500 mr-2"></i> Tentang MY TASK</h3>
                <p class="text-sm text-gray-400 leading-relaxed">Platform untuk mengumpulkan tugas dan mengelola kelas secara interaktif. Dosen dapat membuat kelas, mahasiswa dapat bergabung dengan menggunakan kode unik 6-8 digit.</p>
            </div>
            <div class="bg-surface border border-gray-800 p-6 rounded-2xl shadow-lg">
                <h3 class="text-lg font-bold text-white mb-2 flex items-center"><i class="fas fa-moon text-purple-500 mr-2"></i> Mode Tampilan</h3>
                <p class="text-sm text-gray-400 leading-relaxed">Anda dapat mengubah tema warna website (Gelap / Terang) melalui menu <b>Setelan</b> yang ada di sidebar (garis tiga) sebelah kiri layar.</p>
            </div>
        </div>

        <!-- Form Laporan -->
        <div class="bg-surface border border-gray-700 rounded-2xl p-6 shadow-xl">
            <h3 class="text-xl font-bold text-white mb-1">Lapor Kendala</h3>
            <p class="text-xs text-gray-500 mb-5">Punya kendala dengan MY TASK? Kirim pesan ke Admin.</p>
            
            <form action="" method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-2 uppercase tracking-wider">Pesan Anda</label>
                    <textarea name="pesan" rows="4" placeholder="Jelaskan masalah Anda terkait sistem ini..." class="w-full bg-darkbg border border-gray-700 text-white px-4 py-3 rounded-xl focus:outline-none focus:border-redaccent transition text-sm resize-none" required></textarea>
                </div>
                <button type="submit" name="kirim_keluhan" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-xl transition shadow-lg">
                    <i class="fas fa-paper-plane mr-2"></i> Kirim Laporan
                </button>
            </form>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
</body></html>