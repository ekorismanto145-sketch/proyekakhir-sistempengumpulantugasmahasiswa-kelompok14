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
$stmt = $conn->prepare("SELECT * FROM classes WHERE id = ?");
$stmt->bind_param("i", $class_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) { header("Location: index.php"); exit(); }
$kelas = $result->fetch_assoc();
$stmt->close();

// Otorisasi: hanya dosen pemilik kelas atau admin yang boleh edit
$is_owner = ($role === 'admin') || ($role === 'dosen' && $kelas['dosen_id'] == $user['id']);
if (!$is_owner) { die("Anda tidak memiliki akses untuk mengedit kelas ini."); }

// Proses update
if (isset($_POST['update_kelas'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die("CSRF token tidak valid.");
    }
    $nama_kelas = trim($_POST['nama_kelas']);
    $deskripsi = trim($_POST['deskripsi']);
    
    $upd = $conn->prepare("UPDATE classes SET nama_kelas = ?, deskripsi = ? WHERE id = ?");
    $upd->bind_param("ssi", $nama_kelas, $deskripsi, $class_id);
    $upd->execute();
    $upd->close();
    
    header("Location: detail_kelas.php?id=$class_id&pesan=kelas_diupdate");
    exit();
}

include 'includes/header.php'; 
include 'includes/navbar.php'; 
?>

<main class="max-w-2xl mx-auto p-6 md:p-10">
    <div class="flex items-center border-b border-gray-800 pb-5 mb-8">
        <div class="w-1.5 h-8 bg-yellow-500 rounded-full mr-4"></div>
        <h2 class="text-3xl font-extrabold text-white tracking-wide">Edit Kelas</h2>
    </div>

    <div class="bg-surface border border-gray-800 rounded-2xl p-6 shadow-xl">
        <form action="" method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Nama Kelas</label>
                <input type="text" name="nama_kelas" value="<?= htmlspecialchars($kelas['nama_kelas']) ?>" 
                       class="w-full bg-darkbg border border-gray-700 text-white px-4 py-3 rounded-xl focus:border-yellow-500 transition" required>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Deskripsi</label>
                <textarea name="deskripsi" rows="4" 
                          class="w-full bg-darkbg border border-gray-700 text-white px-4 py-3 rounded-xl focus:border-yellow-500 transition"><?= htmlspecialchars($kelas['deskripsi']) ?></textarea>
            </div>
            <div class="text-sm text-gray-500">
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Kode Kelas</label>
                <input type="text" value="<?= htmlspecialchars($kelas['kode_kelas']) ?>" disabled 
                       class="w-full bg-gray-800 border border-gray-700 text-gray-400 px-4 py-2 rounded-xl cursor-not-allowed">
                <p class="text-[11px] mt-1">Kode kelas tidak dapat diubah.</p>
            </div>
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
            <div class="flex justify-end gap-3">
                <a href="detail_kelas.php?id=<?= $class_id ?>" class="px-5 py-2.5 bg-gray-700 hover:bg-gray-600 text-white font-bold rounded-xl transition">Batal</a>
                <button type="submit" name="update_kelas" class="px-5 py-2.5 bg-yellow-600 hover:bg-yellow-500 text-white font-bold rounded-xl transition">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</main>
</body></html>