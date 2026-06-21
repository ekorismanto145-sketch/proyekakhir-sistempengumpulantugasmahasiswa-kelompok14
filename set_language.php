<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$language = $_GET['lang'] ?? '';
if (in_array($language, ['id', 'en'], true)) {
    $_SESSION['lang'] = $language;
}

$return_url = $_GET['return'] ?? '/index.php';
$is_local_path = str_starts_with($return_url, '/')
    && !str_starts_with($return_url, '//')
    && !preg_match('/[\r\n]/', $return_url);

header('Location: ' . ($is_local_path ? $return_url : '/index.php'), true, 303);
exit();
