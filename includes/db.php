<?php
function env_value(array $keys, $default = null) {
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }

        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
    }

    return $default;
}

$db_host = env_value(['DB_HOST', 'MYSQLHOST', 'DATABASE_HOST'], 'localhost');
$db_user = env_value(['DB_USER', 'MYSQLUSER', 'DATABASE_USER'], 'root');
$db_pass = env_value(['DB_PASSWORD', 'MYSQLPASSWORD', 'DATABASE_PASSWORD'], '');
$db_name = env_value(['DB_NAME', 'MYSQLDATABASE', 'DATABASE_NAME'], 'my_task');
$db_port = (int) env_value(['DB_PORT', 'MYSQLPORT', 'DATABASE_PORT'], 3306);

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['toggle_lang'])) {
    $new_lang = $_GET['toggle_lang'];
    if (in_array($new_lang, ['id', 'en'], true)) {
        $_SESSION['lang'] = $new_lang;
    }
    $redirect_url = strtok($_SERVER['REQUEST_URI'], '?');
    header('Location: ' . $redirect_url);
    exit();
}
?>
