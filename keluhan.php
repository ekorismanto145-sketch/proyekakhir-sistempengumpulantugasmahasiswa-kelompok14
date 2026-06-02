<?php 
include 'includes/db.php'; 
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }
$user = $_SESSION['user'];
$role = $user['role'];

// Hanya admin yang boleh mengakses
if ($role !== 'admin') {
    die("Akses ditolak. Halaman ini hanya untuk admin.");
}

// Proses tandai sudah dibaca
if (isset($_GET['tandai_dibaca'])) {
    $id = (int)$_GET['tandai_dibaca'];
    $stmt = $conn->prepare("UPDATE reports SET `STATUS` = 'sudah_dibaca' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: keluhan.php");
    exit();
}

// Proses hapus keluhan
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $stmt = $conn->prepare("DELETE FROM reports WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: keluhan.php");
    exit();
}

// Ambil semua keluhan
$query = $conn->query("SELECT id, user_id, user_nama, user_role, pesan, `STATUS` AS status, created_at FROM reports ORDER BY created_at DESC");
$reports = $query->fetch_all(MYSQLI_ASSOC);
$unread_count = $conn->query("SELECT COUNT(*) FROM reports WHERE `STATUS` = 'belum_dibaca'")->fetch_row()[0];

include 'includes/header.php';
include 'includes/navbar.php';
?>

<main class="max-w-5xl mx-auto p-6 md:p-10">
    <div class="flex justify-between items-center border-b border-gray-800 pb-5 mb-8">
        <div>
            <h2 class="text-3xl font-extrabold text-white tracking-wide">Daftar Keluhan</h2>
            <p class="text-gray-400 text-sm mt-1">Total keluhan belum dibaca: <span class="font-bold text-yellow-400"><?= $unread_count ?></span></p>
        </div>
        <a href="index.php" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-white"><i class="fas fa-arrow-left mr-2"></i> Kembali</a>
    </div>

    <div class="space-y-4">
        <?php if (count($reports) > 0): ?>
            <?php foreach ($reports as $r): ?>
                <div class="bg-surface border <?= $r['status'] == 'belum_dibaca' ? 'border-yellow-500/50 bg-yellow-500/5' : 'border-gray-800 opacity-80' ?> rounded-2xl p-5 shadow-lg">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="flex items-center gap-3 mb-2">
                                <span class="text-sm font-bold text-white"><?= htmlspecialchars($r['user_nama']) ?></span>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-700 text-gray-300"><?= htmlspecialchars($r['user_role']) ?></span>
                                <span class="text-xs text-gray-500"><?= date('d M Y, H:i', strtotime($r['created_at'])) ?></span>
                            </div>
                            <p class="text-gray-300 text-sm leading-relaxed"><?= nl2br(htmlspecialchars($r['pesan'])) ?></p>
                        </div>
                        <div class="flex gap-2">
                            <?php if ($r['status'] == 'belum_dibaca'): ?>
                                <a href="?tandai_dibaca=<?= $r['id'] ?>" class="text-xs bg-blue-600 hover:bg-blue-500 px-3 py-1 rounded text-white">Tandai Dibaca</a>
                            <?php endif; ?>
                            <a href="?hapus=<?= $r['id'] ?>" onclick="return confirm('Hapus keluhan ini?')" class="text-xs bg-red-600 hover:bg-red-500 px-3 py-1 rounded text-white">Hapus</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="bg-surface border border-dashed border-gray-700 rounded-2xl p-12 text-center">
                <i class="fas fa-inbox text-5xl text-gray-600 mb-3"></i>
                <p class="text-gray-400">Belum ada keluhan dari pengguna.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
</body></html>