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
        <h2 class="text-3xl font-extrabold text-white mb-2">Persyaratan Layanan</h2>
        <p class="text-gray-500 text-sm mb-8 border-b border-gray-800 pb-6">Syarat dan ketentuan penggunaan platform MY TASK.</p>

        <div class="space-y-6 text-gray-300 text-sm leading-relaxed">
            <div class="bg-darkbg border border-gray-800 p-5 rounded-xl border-l-4 border-l-green-500">
                <h3 class="font-bold text-white text-base mb-1">Pengantar</h3>
                <p class="text-gray-400">Dengan menggunakan MY TASK, Anda menyetujui seluruh syarat dan ketentuan yang tercantum pada halaman ini.</p>
            </div>

            <div class="bg-darkbg border border-gray-800 p-5 rounded-xl border-l-4 border-l-redaccent">
                <h3 class="font-bold text-white text-base mb-1">A. Kewajiban Pengguna</h3>
                <p class="text-gray-400">Pengguna wajib menggunakan akun yang sah dan data yang benar sesuai identitas akademik. Mahasiswa wajib menggunakan akun mahasiswa, dosen wajib menggunakan akun dosen, dan admin wajib menjaga keamanan akses sistem.</p>
            </div>

            <div class="bg-darkbg border border-gray-800 p-5 rounded-xl border-l-4 border-l-blue-500">
                <h3 class="font-bold text-white text-base mb-1">B. Pengumpulan Tugas</h3>
                <p class="text-gray-400">Mahasiswa bertanggung jawab atas file yang dikumpulkan melalui MY TASK. Sistem mencatat waktu pengiriman, pembatalan pengiriman, dan perubahan deadline sebagai bukti aktivitas. Jika dosen memperpanjang deadline, mahasiswa dapat mengirim ulang selama batas waktu baru masih aktif.</p>
            </div>

            <div class="bg-darkbg border border-gray-800 p-5 rounded-xl border-l-4 border-l-green-500">
                <h3 class="font-bold text-white text-base mb-1">C. Batas Waktu dan Perubahan</h3>
                <p class="text-gray-400">Dosen dapat membuat, mengubah, atau memperpanjang deadline tugas sesuai kebutuhan kelas. Mahasiswa hanya dapat mengirim atau membatalkan kiriman sebelum deadline berakhir, kecuali dosen memberikan perpanjangan waktu baru.</p>
            </div>

            <div class="bg-darkbg border border-gray-800 p-5 rounded-xl border-l-4 border-l-yellow-500">
                <h3 class="font-bold text-white text-base mb-1">D. Larangan Penggunaan</h3>
                <p class="text-gray-400">Dilarang menyalahgunakan sistem untuk mengunggah file berbahaya, meniru identitas pengguna lain, atau mencoba mengakses data yang bukan milik Anda. Pelanggaran dapat mengakibatkan pembatasan akses akun.</p>
            </div>

            <div class="bg-darkbg border border-gray-800 p-5 rounded-xl border-l-4 border-l-purple-500">
                <h3 class="font-bold text-white text-base mb-1">E. Perubahan Layanan</h3>
                <p class="text-gray-400">MY TASK dapat menambahkan fitur, mengubah tampilan, atau memperbarui aturan layanan untuk menyesuaikan kebutuhan perkuliahan. Penggunaan berkelanjutan berarti Anda menerima perubahan tersebut.</p>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
</body>
</html>