<?php
$conn = mysqli_connect("localhost", "root", "", "my_task");

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
