<?php
// Cek apakah user sudah memilih bahasa, jika belum jadikan 'id' (Indonesia) sebagai default
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'id'; 
}
$lang = $_SESSION['lang'];

// Kamus Bahasa Lengkap
$translations = [
    'id' => [
        // Navbar & umum
        'kelas' => 'Kelas',
        'notifikasi' => 'Notifikasi',
        'kalender' => 'Kalender',
        'terdaftar' => 'Terdaftar',
        'daftar_tugas' => 'Daftar Tugas',
        'setelan' => 'Setelan',
        'bantuan' => 'Bantuan',
        'setelan_aplikasi' => 'Setelan Aplikasi',
        'setelan_akun' => 'Setelan Akun',
        'perbarui_foto' => 'Perbarui Foto',
        'ganti_sandi' => 'Ganti Kata Sandi',
        'bahasa' => 'Bahasa',
        'simpan' => 'Simpan Perubahan',
        'notif_email' => 'Notifikasi Email',
        'tema' => 'Tema Tampilan',
        'ganti_tema' => 'Ganti Tema',
        'ubah_gelap_terang' => 'Ubah ke Terang/Gelap',
        'ubah' => 'Ubah',
        'sinkronisasi' => 'Sinkronisasi Data',
        'info_sinkron' => 'Aktifkan untuk sinkronisasi saat tidak menggunakan Wi-Fi.',
        'keluar' => 'Keluar dari MY TASK',

        // Index / Dashboard
        'system_overview' => 'System Overview',
        'welcome_back' => 'Welcome back',
        'kelas_aktif' => 'Kelas Aktif',
        'masuk_kelas' => 'Masuk Kelas',
        'aktivitas_terkini' => 'Aktivitas Terkini',
        'belum_ada_kelas' => 'Belum ada kelas. Klik Kelola Kelas untuk bergabung atau membuat kelas baru.',
        'kelola_kelas' => 'Kelola Kelas',
        'belum_ada_aktivitas' => 'Belum ada aktivitas baru.',
        'tugas_kelas' => 'Tugas Kelas',
        'buat_tugas_baru' => 'Buat Tugas Baru',
        'judul_tugas' => 'Judul Tugas',
        'deskripsi' => 'Deskripsi / Instruksi',
        'deadline' => 'Batas Waktu (Deadline)',
        'publikasikan_tugas' => 'Publikasikan Tugas',
        'kumpulkan_tugas_ini' => 'Kumpulkan Tugas Ini',
        'tenggat' => 'Tenggat',
        'lihat_pengumpulan' => 'Lihat Pengumpulan',
        'edit_deadline' => 'Edit Deadline',
        'edit_kelas' => 'Edit Kelas',
        'hapus_kelas' => 'Hapus Kelas',
        'keluar_kelas' => 'Keluar Kelas',
        'kode_bergabung' => 'Kode Bergabung',

        // Modal dan Form
        'gabung_kelas' => 'Gabung Kelas',
        'buat_kelas' => 'Buat Kelas',
        'nama_kelas_label' => 'Nama Kelas',
        'nama_kelas_placeholder' => 'Masukkan nama kelas',
        'mata_kuliah_label' => 'Mata Kuliah',
        'mata_kuliah_placeholder' => 'Masukkan mata kuliah',
        'ruang_label' => 'Ruang',
        'ruang_placeholder' => 'Masukkan ruang',
        'nama_dosen_label' => 'Nama Dosen',
        'nama_dosen_placeholder' => 'Nama dosen pengajar',
        'buat_kelas_button' => 'Buat Kelas',
        'gabung_sekarang' => 'Gabung Sekarang',
        'kode_kelas_label' => 'Kode Kelas',
        'kode_kelas_placeholder' => 'Masukkan kode kelas (dari dosen)',
        'ganti_akun' => 'Ganti Akun',
        'info_email' => 'Gunakan akun @student.trunojoyo.ac.id atau @dosen.trunojoyo.ac.id.',

        // Tugas.php
        'filter_kelas' => 'Filter Kelas',
        'semua_kelas' => 'Semua Kelas',
        'kirim_tugas' => 'Kirim Tugas',
        'file_terpilih' => 'File terpilih',
        'belum_ada_file' => 'Belum ada file dipilih',
        'tidak_ada_tugas' => 'Tidak ada tugas ditemukan.',
        'tugas_berhasil_dikirim' => 'Tugas berhasil dikirim!',
        'format_ditolak' => 'Format ditolak! Harap unggah file PDF atau Word.',
        'file_terlalu_besar' => 'File terlalu besar. Maksimal 5MB.',
        'gagal_mengirim' => 'Gagal mengirim. Pastikan Anda memilih file.',
        'tugas_terlambat' => 'Tugas sudah melewati deadline. Tidak dapat dikumpulkan.',
        'klik_pilih_file' => 'Klik untuk memilih file',
        'pdf_word' => 'PDF/Word',

        // Notifikasi
        'tandai_semua_dibaca' => 'Tandai Semua Dibaca',
        'hapus_semua' => 'Hapus Semua',
        'tidak_ada_notifikasi' => 'Tidak ada notifikasi yang ditemukan.',
        'hapus_notifikasi_ini' => 'Hapus notifikasi ini?',

        // Detail Kelas
        'kembali_ke_dashboard' => 'Kembali ke Dashboard',
        'simpan_perubahan' => 'Simpan Perubahan',
        'batal' => 'Batal',
    ],
    'en' => [
        // Navbar
        'kelas' => 'Classes',
        'notifikasi' => 'Notifications',
        'kalender' => 'Calendar',
        'terdaftar' => 'Enrolled',
        'daftar_tugas' => 'Task List',
        'setelan' => 'Settings',
        'bantuan' => 'Help',
        'setelan_aplikasi' => 'Application Settings',
        'setelan_akun' => 'Account Settings',
        'perbarui_foto' => 'Update Photo',
        'ganti_sandi' => 'Change Password',
        'bahasa' => 'Language',
        'simpan' => 'Save Changes',
        'notif_email' => 'Email Notifications',
        'tema' => 'Display Theme',
        'ganti_tema' => 'Change Theme',
        'ubah_gelap_terang' => 'Switch Dark/Light',
        'ubah' => 'Switch',
        'sinkronisasi' => 'Data Synchronization',
        'info_sinkron' => 'Enable to sync data when not using Wi-Fi.',
        'keluar' => 'Logout from MY TASK',

        // Index
        'system_overview' => 'System Overview',
        'welcome_back' => 'Welcome back',
        'kelas_aktif' => 'Active Classes',
        'masuk_kelas' => 'Enter Class',
        'aktivitas_terkini' => 'Recent Activities',
        'belum_ada_kelas' => 'No classes yet. Click Manage Class to join or create a new class.',
        'kelola_kelas' => 'Manage Class',
        'belum_ada_aktivitas' => 'No recent activities.',
        'tugas_kelas' => 'Class Tasks',
        'buat_tugas_baru' => 'Create New Task',
        'judul_tugas' => 'Task Title',
        'deskripsi' => 'Description / Instructions',
        'deadline' => 'Deadline',
        'publikasikan_tugas' => 'Publish Task',
        'kumpulkan_tugas_ini' => 'Submit This Task',
        'tenggat' => 'Due',
        'lihat_pengumpulan' => 'View Submissions',
        'edit_deadline' => 'Edit Deadline',
        'edit_kelas' => 'Edit Class',
        'hapus_kelas' => 'Delete Class',
        'keluar_kelas' => 'Leave Class',
        'kode_bergabung' => 'Join Code',

        // Modal
        'gabung_kelas' => 'Join Class',
        'buat_kelas' => 'Create Class',
        'nama_kelas_label' => 'Class Name',
        'nama_kelas_placeholder' => 'Enter class name',
        'mata_kuliah_label' => 'Course',
        'mata_kuliah_placeholder' => 'Enter course',
        'ruang_label' => 'Room',
        'ruang_placeholder' => 'Enter room',
        'nama_dosen_label' => 'Lecturer Name',
        'nama_dosen_placeholder' => 'Enter lecturer name',
        'buat_kelas_button' => 'Create Class',
        'gabung_sekarang' => 'Join Now',
        'kode_kelas_label' => 'Class Code',
        'kode_kelas_placeholder' => 'Enter class code from teacher',
        'ganti_akun' => 'Switch Account',
        'info_email' => 'Use @student.trunojoyo.ac.id or @dosen.trunojoyo.ac.id account.',

        // Tugas
        'filter_kelas' => 'Filter Class',
        'semua_kelas' => 'All Classes',
        'kirim_tugas' => 'Submit Task',
        'file_terpilih' => 'Selected file',
        'belum_ada_file' => 'No file selected',
        'tidak_ada_tugas' => 'No tasks found.',
        'tugas_berhasil_dikirim' => 'Task submitted successfully!',
        'format_ditolak' => 'Invalid format! Please upload PDF or Word file.',
        'file_terlalu_besar' => 'File too large. Maximum 5MB.',
        'gagal_mengirim' => 'Failed to submit. Please select a file.',
        'tugas_terlambat' => 'Task deadline has passed. Cannot submit.',
        'klik_pilih_file' => 'Click to select file',
        'pdf_word' => 'PDF/Word',

        // Notifikasi
        'tandai_semua_dibaca' => 'Mark All as Read',
        'hapus_semua' => 'Delete All',
        'tidak_ada_notifikasi' => 'No notifications found.',
        'hapus_notifikasi_ini' => 'Delete this notification?',

        // Detail Kelas
        'kembali_ke_dashboard' => 'Back to Dashboard',
        'simpan_perubahan' => 'Save Changes',
        'batal' => 'Cancel',
    ]
];

function t($key) {
    global $translations, $lang;
    return isset($translations[$lang][$key]) ? $translations[$lang][$key] : $key;
}
?>