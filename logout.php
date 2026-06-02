<?php
// Mulai session jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hapus semua data session
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),                 // Nama cookie session (biasanya PHPSESSID)
        '',                             // Nilai kosong
        time() - 42000,                 // Waktu kadaluwarsa di masa lalu (hapus)
        $params["path"],                // Path cookie
        $params["domain"],              // Domain cookie
        $params["secure"],              // Hanya HTTPS? (sesuai setting)
        $params["httponly"]             // Hanya akses HTTP
    );
}

// Hancurkan session
session_destroy();

// Cek apakah user ingin menambah akun atau sekadar logout biasa
if (isset($_GET['action']) && $_GET['action'] == 'add_account') {
    // Arahkan ke login dengan pesan (opsional)
    header("Location: login.php?pesan=silakan_tambah_akun");
} else {
    // Logout biasa
    header("Location: login.php");
}
exit();
?>