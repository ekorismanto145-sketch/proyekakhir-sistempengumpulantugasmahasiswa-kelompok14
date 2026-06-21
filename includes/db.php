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
$db_name = env_value(['DB_NAME', 'MYSQLDATABASE', 'DATABASE_NAME'], 'my_academic');
$db_port = (int) env_value(['DB_PORT', 'MYSQLPORT', 'DATABASE_PORT'], 3306);

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$materials_table_sql = "CREATE TABLE IF NOT EXISTS materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    judul VARCHAR(255) NOT NULL,
    deskripsi TEXT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    file_size INT DEFAULT NULL,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (class_id),
    INDEX (uploaded_by)
)";
$conn->query($materials_table_sql);

$task_material_columns = [
    'material_source_type' => "ENUM('upload_baru','existing') DEFAULT 'upload_baru'",
    'material_reference_id' => 'INT DEFAULT NULL',
    'material_file_path' => 'VARCHAR(255) DEFAULT NULL',
    'material_original_name' => 'VARCHAR(255) DEFAULT NULL'
];
foreach ($task_material_columns as $column_name => $column_definition) {
    $column_stmt = $conn->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND COLUMN_NAME = ?
         LIMIT 1"
    );
    $column_stmt->bind_param('s', $column_name);
    $column_stmt->execute();
    $column_exists = $column_stmt->get_result()->num_rows > 0;
    $column_stmt->close();

    if (!$column_exists) {
        $conn->query("ALTER TABLE tasks ADD COLUMN `$column_name` $column_definition");
    }
}

if (isset($_GET['toggle_lang'])) {
    $new_lang = $_GET['toggle_lang'];
    if (in_array($new_lang, ['id', 'en'], true)) {
        $_SESSION['lang'] = $new_lang;
    }

    // Backward compatibility for old language links while preserving page params.
    $request_uri = $_SERVER['REQUEST_URI'] ?? '/index.php';
    $url_parts = parse_url($request_uri);
    $query = [];
    if (!empty($url_parts['query'])) {
        parse_str($url_parts['query'], $query);
    }
    unset($query['toggle_lang']);
    $redirect_url = $url_parts['path'] ?? '/index.php';
    if ($query) {
        $redirect_url .= '?' . http_build_query($query);
    }
    header('Location: ' . $redirect_url);
    exit();
}

if (isset($_GET['switch_role'])) {
    $new_role = $_GET['switch_role'];
    if (!isset($_SESSION['user']['original_role'])) {
        $_SESSION['user']['original_role'] = $_SESSION['user']['role'] ?? '';
    }

    if (
        ($_SESSION['user']['original_role'] ?? '') === 'admin'
        && in_array($new_role, ['admin', 'dosen', 'mahasiswa'], true)
    ) {
        $_SESSION['user']['role'] = $new_role;
    }

    $request_uri = $_SERVER['REQUEST_URI'] ?? '/index.php';
    $url_parts = parse_url($request_uri);
    $query = [];
    if (!empty($url_parts['query'])) {
        parse_str($url_parts['query'], $query);
    }
    unset($query['switch_role']);
    $redirect_url = $url_parts['path'] ?? '/index.php';
    if ($query) {
        $redirect_url .= '?' . http_build_query($query);
    }
    header('Location: ' . $redirect_url);
    exit();
}
?>
