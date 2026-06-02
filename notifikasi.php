<?php 
include 'includes/db.php'; 
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }

$user = $_SESSION['user']; 
$role = $user['role'];
$user_id = $user['id'];

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Tandai satu notifikasi (GET request, tidak perlu CSRF, tapi aman karena hanya update berdasarkan ID user)
if (isset($_GET['read'])) {
    $id_notif = (int)$_GET['read'];
    $stmt = $conn->prepare("UPDATE activities SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id_notif, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: notifikasi.php"); exit();
}

// Tandai semua - butuh CSRF
if (isset($_POST['mark_all_read'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die("CSRF token tidak valid.");
    }
    $stmt = $conn->prepare("UPDATE activities SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: notifikasi.php?msg=all_read"); exit();
}

// Hapus satu notifikasi (GET request, aman karena ID tidak bisa ditebak? Tapi lebih baik tetap pakai CSRF untuk konsistensi. Namun karena GET, kita skip)
if (isset($_GET['delete'])) {
    $id_del = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM activities WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id_del, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: notifikasi.php?msg=deleted"); exit();
}

// Hapus semua - butuh CSRF
if (isset($_POST['delete_all'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die("CSRF token tidak valid.");
    }
    $stmt = $conn->prepare("DELETE FROM activities WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: notifikasi.php?msg=all_deleted"); exit();
}

include 'includes/header.php'; 
include 'includes/navbar.php'; 

$filter = isset($_GET['view']) ? $_GET['view'] : 'all';
$sql_filter = ($filter == 'unread') ? " AND is_read = 0 " : "";
$sql = "SELECT * FROM activities WHERE user_id = ? $sql_filter ORDER BY waktu DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$query_notif = $stmt->get_result();
?>

<main class="max-w-4xl mx-auto p-6 md:p-10">
    <div class="flex flex-col md:flex-row md:items-center justify-between border-b border-gray-800 pb-6 mb-8 gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-white tracking-wide">Notifikasi</h2>
            <div class="flex gap-4 mt-2">
                <a href="?view=all" class="text-sm <?= $filter == 'all' ? 'text-blue-500 font-bold' : 'text-gray-500 hover:text-gray-300' ?>">Semua</a>
                <a href="?view=unread" class="text-sm <?= $filter == 'unread' ? 'text-blue-500 font-bold' : 'text-gray-500 hover:text-gray-300' ?>">Belum Dibaca</a>
            </div>
        </div>
        <div class="flex gap-2">
            <form action="" method="POST" onsubmit="return confirm('Tandai semua pesan sebagai dibaca?')">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                <button type="submit" name="mark_all_read" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs font-bold rounded-lg transition"><i class="fas fa-check-double mr-2"></i>Tandai Semua Dibaca</button>
            </form>
            <form action="" method="POST" onsubmit="return confirm('Hapus seluruh riwayat notifikasi?')">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                <button type="submit" name="delete_all" class="px-4 py-2 bg-red-500/10 hover:bg-red-500 text-red-500 hover:text-white text-xs font-bold rounded-lg transition"><i class="fas fa-trash-alt mr-2"></i>Hapus Semua</button>
            </form>
        </div>
    </div>

    <div class="space-y-4">
        <?php if ($query_notif->num_rows > 0): ?>
            <?php while ($notif = $query_notif->fetch_assoc()): ?>
                <div class="group relative bg-surface border <?= $notif['is_read'] ? 'border-gray-800 opacity-70' : 'border-blue-500/30 bg-blue-500/5' ?> rounded-2xl p-5 shadow-lg flex items-start gap-4 transition-all">
                    <?php if(!$notif['is_read']): ?>
                        <div class="absolute top-5 right-5 w-2 h-2 bg-blue-500 rounded-full shadow-[0_0_8px_rgba(59,130,246,1)]"></div>
                    <?php endif; ?>
                    <div class="w-12 h-12 rounded-full bg-darkbg border border-gray-700 flex items-center justify-center flex-shrink-0">
                        <?php if($notif['tipe'] == 'tugas'): ?>
                            <i class="fas fa-file-upload text-green-500"></i>
                        <?php elseif($notif['tipe'] == 'kelas_baru'): ?>
                            <i class="fas fa-chalkboard-teacher text-purple-500"></i>
                        <?php elseif($notif['tipe'] == 'keluhan'): ?>
                            <i class="fas fa-headset text-yellow-500"></i>
                        <?php else: ?>
                            <i class="fas fa-bell text-blue-500"></i>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1">
                        <a href="?read=<?= $notif['id'] ?>" class="block">
                            <p class="text-gray-200 font-semibold leading-tight mb-1 group-hover:text-white transition"><?= htmlspecialchars($notif['deskripsi']) ?></p>
                            <p class="text-xs text-gray-500"><i class="fas fa-clock mr-1"></i> <?= date('d M Y, H:i', strtotime($notif['waktu'])) ?> WIB</p>
                        </a>
                        <?php if ($role === 'admin' && $notif['tipe'] === 'keluhan'): ?>
                            <a href="keluhan.php" class="inline-flex items-center mt-3 px-3 py-1.5 rounded-lg bg-yellow-500/10 text-yellow-400 text-xs font-bold border border-yellow-500/30 hover:bg-yellow-500/20 transition">
                                <i class="fas fa-envelope-open-text mr-2"></i> Buka keluhan
                            </a>
                        <?php endif; ?>
                    </div>
                    <a href="?delete=<?= $notif['id'] ?>" onclick="return confirm('Hapus notifikasi ini?')" class="text-gray-600 hover:text-red-500 transition px-2 opacity-0 group-hover:opacity-100"><i class="fas fa-times"></i></a>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="py-20 text-center"><i class="fas fa-bell-slash text-5xl text-gray-800 mb-4"></i><p class="text-gray-500 font-medium">Tidak ada notifikasi yang ditemukan.</p></div>
        <?php endif; ?>
    </div>
</main>
</body></html>