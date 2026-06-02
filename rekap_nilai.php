<?php
include 'includes/db.php';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit(); }
$user = $_SESSION['user'];
$role = $user['role'] ?? '';
if ($role !== 'dosen' && $role !== 'admin') { die('Akses ditolak.'); }

// Ambil kelas yang diajarkan dosen
$dosen_id = $user['id'];
$stmt = $conn->prepare("SELECT id, nama_kelas, deskripsi FROM classes WHERE dosen_id = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $dosen_id);
$stmt->execute();
$kelas_list = $stmt->get_result();
$stmt->close();

$selected_class = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$students = [];
$class_info = null;
if ($selected_class > 0) {
    // Ambil info kelas
    $s = $conn->prepare("SELECT nama_kelas, deskripsi FROM classes WHERE id = ? AND dosen_id = ?");
    $s->bind_param('ii', $selected_class, $dosen_id);
    $s->execute();
    $class_info = $s->get_result()->fetch_assoc();
    $s->close();

    if ($class_info) {
        // Ambil anggota kelas
        $q = $conn->prepare("SELECT u.id, u.nama, u.email FROM class_members cm JOIN users u ON cm.mahasiswa_id = u.id WHERE cm.class_id = ? ORDER BY u.nama ASC");
        $q->bind_param('i', $selected_class);
        $q->execute();
        $res = $q->get_result();
        while($r = $res->fetch_assoc()) {
            // hitung rata-rata nilai untuk mahasiswa ini di kelas ini
            $avg_stmt = $conn->prepare("SELECT AVG(ts.nilai) as avg_nilai FROM task_submissions ts JOIN tasks t ON ts.task_id = t.id WHERE t.class_id = ? AND ts.mahasiswa_id = ? AND ts.nilai IS NOT NULL");
            $avg_stmt->bind_param('ii', $selected_class, $r['id']);
            $avg_stmt->execute();
            $avg = $avg_stmt->get_result()->fetch_assoc()['avg_nilai'];
            $avg_stmt->close();
            $r['avg_nilai'] = $avg !== null ? round($avg,2) : null;
            $students[] = $r;
        }
        $q->close();
    }
}

include 'includes/header.php';
include 'includes/navbar.php';
?>
<main class="max-w-4xl mx-auto p-4 sm:p-6 md:p-10">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-3">
        <h2 class="text-xl sm:text-2xl font-bold text-white">Rekap Nilai - Cetak PDF</h2>
        <?php if($selected_class && $class_info): ?>
            <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white rounded w-full sm:w-auto">Cetak PDF</button>
        <?php endif; ?>
    </div>

    <form method="GET" class="mb-6">
        <label class="text-sm text-gray-400">Pilih Kelas</label>
        <div class="flex flex-col sm:flex-row gap-3 mt-2">
            <select name="class_id" class="bg-darkbg border border-gray-700 text-white px-3 py-2 rounded w-full sm:w-auto">
                <option value="">-- Pilih Kelas --</option>
                <?php while($k = $kelas_list->fetch_assoc()): ?>
                    <option value="<?= $k['id'] ?>" <?= ($selected_class == $k['id']) ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                <?php endwhile; ?>
            </select>
            <button class="px-4 py-2 bg-blue-600 text-white rounded w-full sm:w-auto">Tampilkan</button>
        </div>
    </form>

    <?php if ($selected_class && $class_info): ?>
        <div class="bg-surface border border-gray-800 rounded-lg p-6">
            <h3 class="text-lg font-bold text-white mb-2"><?= htmlspecialchars($class_info['nama_kelas']) ?></h3>
            <p class="text-sm text-gray-400 mb-4">Mata Kuliah / Deskripsi: <?= nl2br(htmlspecialchars($class_info['deskripsi'])) ?></p>

            <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[620px]">
                <thead>
                    <tr class="text-sm text-gray-400 border-b border-gray-700">
                        <th class="py-2 px-3">No</th>
                        <th class="py-2 px-3">Nama Mahasiswa</th>
                        <th class="py-2 px-3">Email</th>
                        <th class="py-2 px-3">Rata-rata Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no=1; if (count($students) == 0): ?>
                        <tr><td colspan="4" class="py-6 text-center text-gray-500">Belum ada mahasiswa terdaftar.</td></tr>
                    <?php else: foreach($students as $st): ?>
                        <tr class="border-b border-gray-800">
                            <td class="py-2 px-3 text-sm text-gray-300"><?= $no++ ?></td>
                            <td class="py-2 px-3 text-sm text-white"><?= htmlspecialchars($st['nama']) ?></td>
                            <td class="py-2 px-3 text-sm text-gray-400"><?= htmlspecialchars($st['email']) ?></td>
                            <td class="py-2 px-3 text-sm text-white"><?= $st['avg_nilai'] !== null ? $st['avg_nilai'] : '-' ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
        </div>
        <p class="text-xs text-gray-500 mt-3">Gunakan tombol "Cetak PDF" untuk menyimpan sebagai PDF (fitur browser Print to PDF).</p>
    <?php endif; ?>
</main>
</body>
</html>
