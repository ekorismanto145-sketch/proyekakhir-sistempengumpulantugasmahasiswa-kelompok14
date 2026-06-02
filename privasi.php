<?php 
include 'includes/db.php'; 
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }
$user = $_SESSION['user']; 
$role = $user['role'];
include 'includes/header.php'; 
include 'includes/navbar.php'; 
?>

<main class="max-w-4xl mx-auto p-6 md:p-10">
    <div class="bg-surface border border-gray-800 rounded-3xl p-8 md:p-12 shadow-2xl">
        <h2 class="text-3xl font-extrabold text-white mb-2">Kebijakan Privasi</h2>
        <p class="text-gray-500 text-sm mb-8 border-b border-gray-800 pb-6">Terakhir diperbarui: 19 Mei 2026</p>

        <div class="space-y-8 text-gray-300 text-sm leading-relaxed">
            <section>
                <p>Halaman ini menjelaskan bagaimana MY TASK mengumpulkan, menggunakan, menyimpan, dan melindungi data pengguna selama layanan digunakan.</p>
            </section>

            <section>
                <h3 class="text-xl font-bold text-white mb-3 flex items-center"><i class="fas fa-shield-alt text-redaccent mr-2"></i> 1. Pengumpulan Data</h3>
                <p>MY TASK mengumpulkan data yang diperlukan untuk menjalankan layanan akademik, seperti nama, email institusi, peran pengguna (admin, dosen, mahasiswa), kelas yang diikuti, aktivitas penggunaan, serta file tugas yang diunggah atau diunduh melalui sistem.</p>
            </section>

            <section>
                <h3 class="text-xl font-bold text-white mb-3 flex items-center"><i class="fas fa-database text-blue-500 mr-2"></i> 2. Penggunaan Informasi</h3>
                <p>Informasi tersebut digunakan untuk memverifikasi akun, menghubungkan mahasiswa dengan kelas yang tepat, menampilkan tugas dan pengumpulan kepada dosen, mencatat aktivitas akademik, serta menjaga ketertiban proses pengumpulan tugas. Data tidak digunakan untuk tujuan di luar operasional MY TASK.</p>
            </section>

            <section>
                <h3 class="text-xl font-bold text-white mb-3 flex items-center"><i class="fas fa-lock text-green-500 mr-2"></i> 3. Keamanan Data</h3>
                <p>Kata sandi disimpan menggunakan *hashing* dan akses fitur dibatasi berdasarkan peran pengguna. File tugas hanya dapat diakses oleh pihak yang berwenang, yaitu mahasiswa pemilik file, dosen pemilik kelas, dan admin sistem. Tindakan sensitif juga dilindungi dengan token CSRF.</p>
            </section>

            <section>
                <h3 class="text-xl font-bold text-white mb-3 flex items-center"><i class="fas fa-file-alt text-yellow-500 mr-2"></i> 4. Penyimpanan Tugas</h3>
                <p>File tugas yang diunggah akan disimpan selama masih diperlukan untuk kegiatan pembelajaran dan pemeriksaan tugas. Jika pengumpulan dibatalkan, file terkait akan dihapus dari penyimpanan aktif sistem.</p>
            </section>

            <section>
                <h3 class="text-xl font-bold text-white mb-3 flex items-center"><i class="fas fa-envelope text-purple-400 mr-2"></i> 5. Perubahan Kebijakan</h3>
                <p>Kebijakan ini dapat diperbarui sewaktu-waktu mengikuti kebutuhan pengembangan MY TASK. Jika ada perubahan penting, informasi pada halaman ini akan disesuaikan.</p>
            </section>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
</body>
</html>