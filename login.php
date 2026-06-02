<?php 
include __DIR__ . '/includes/db.php'; 

// Gunakan timestamp integer dari PHP untuk konsistensi
$current_time = time();

// Hapus data lama (lebih dari 1 jam)
$conn->query("DELETE FROM login_attempts WHERE blocked_until_ts IS NOT NULL AND blocked_until_ts < " . ($current_time - 3600));
$conn->query("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 1 HOUR) AND blocked_until_ts IS NULL");

if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$error_msg = "";

if (isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error_msg = "Email dan password harus diisi!";
    } else {
        // Cek data percobaan
        $stmt = $conn->prepare("SELECT fail_count, block_level, blocked_until_ts FROM login_attempts WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        $now = time();
        $is_blocked = false;
        $remaining_seconds = 0;

        // Periksa blokir
        if ($row && !empty($row['blocked_until_ts']) && $row['blocked_until_ts'] > $now) {
            $is_blocked = true;
            $remaining_seconds = $row['blocked_until_ts'] - $now;
        }

        if ($is_blocked) {
            $minutes = floor($remaining_seconds / 60);
            $seconds = $remaining_seconds % 60;
            if ($minutes > 0) {
                $error_msg = "Terlalu banyak percobaan gagal. Coba lagi setelah {$minutes} menit {$seconds} detik.";
            } else {
                $error_msg = "Terlalu banyak percobaan gagal. Coba lagi setelah {$seconds} detik.";
            }
        } else {
            // Verifikasi login
            $stmt = $conn->prepare("SELECT id, nama, email, `PASSWORD` AS password, `ROLE` AS role, foto_profil FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password'])) {
                // Login sukses, hapus percobaan
                $del = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
                $del->bind_param("s", $email);
                $del->execute();
                $del->close();

                session_regenerate_id(true);
                $_SESSION['user'] = $user;
                header("Location: index.php");
                exit();
            } else {
                // Login gagal
                $fail_count = ($row ? $row['fail_count'] : 0) + 1;
                $block_level = $row ? $row['block_level'] : 0;
                $blocked_until_ts = null;

                if ($fail_count >= 3) {
                    $delays = [30, 60, 180, 300, 600, 1800];
                    $duration = isset($delays[$block_level]) ? $delays[$block_level] : 3600;
                    $blocked_until_ts = $now + $duration;
                    $block_level++;
                    $fail_count = 0;
                    $error_msg = "Terlalu banyak percobaan gagal. Silakan coba lagi nanti.";
                } else {
                    $error_msg = "Email atau password salah!";
                }

                // Simpan data ke database
                if ($row) {
                    $update = $conn->prepare("UPDATE login_attempts SET fail_count = ?, block_level = ?, blocked_until_ts = ? WHERE email = ?");
                    $update->bind_param("iiis", $fail_count, $block_level, $blocked_until_ts, $email);
                    $update->execute();
                    $update->close();
                } else {
                    $insert = $conn->prepare("INSERT INTO login_attempts (email, fail_count, block_level, blocked_until_ts) VALUES (?, ?, ?, ?)");
                    $insert->bind_param("siii", $email, $fail_count, $block_level, $blocked_until_ts);
                    $insert->execute();
                    $insert->close();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MY TASK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { transition: background 0.3s, color 0.3s; }
        body.dark { background: radial-gradient(circle at 20% 30%, #0a0a0a, #000000); color: #f0f0f0; }
        body.light { background: radial-gradient(circle at 20% 30%, #e0e0e0, #b0b0b0); color: #1f2937; }
        .glass-card {
            background: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: background 0.3s, border 0.3s;
        }
        body.light .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
        .toggle-btn { cursor: pointer; transition: all 0.2s; }
        .toggle-btn:hover { opacity: 0.8; }
    </style>
</head>
<body class="dark">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="glass-card rounded-2xl shadow-2xl w-full max-w-md p-8 transition-all">
            <div class="flex justify-end gap-3 mb-4">
                <button id="themeToggle" class="toggle-btn text-gray-200 hover:text-white text-xl"><i class="fas fa-moon"></i></button>
                <button id="langToggle" class="toggle-btn text-gray-200 hover:text-white text-sm font-semibold px-2 py-1 rounded-lg bg-white/10">EN</button>
            </div>
            <h2 id="appTitle" class="text-3xl font-bold text-blue-400 mb-2">MY TASK</h2>
            <p id="subTitle" class="text-gray-300 mb-6">Masuk ke sistem pengumpulan tugas</p>
            <?php if (!empty($error_msg)): ?>
                <p id="errorMsg" class='bg-red-500/20 border border-red-500 text-red-300 p-3 rounded-lg text-sm mb-4 font-semibold'><?= htmlspecialchars($error_msg) ?></p>
            <?php endif; ?>
            <form action="" method="POST" class="space-y-4" autocomplete="off">
                <div><label id="emailLabel" class="text-xs font-semibold text-gray-300 uppercase ml-1">Email</label><input type="email" name="email" placeholder="nama@student.trunojoyo.ac.id" class="w-full p-3 rounded-lg bg-white/10 border border-gray-600 text-white focus:ring-2 focus:ring-blue-500 outline-none transition" required autocomplete="off"></div>
                <div><label id="passLabel" class="text-xs font-semibold text-gray-300 uppercase ml-1">Password</label><input type="password" name="password" placeholder="••••••••" class="w-full p-3 rounded-lg bg-white/10 border border-gray-600 text-white focus:ring-2 focus:ring-blue-500 outline-none" required autocomplete="new-password"></div>
                <button id="loginBtn" type="submit" name="login" class="w-full bg-blue-600 text-white p-3 rounded-lg font-bold hover:bg-blue-700 transition shadow-lg">Masuk</button>
            </form>
            <p id="registerLink" class="mt-6 text-center text-sm text-gray-400">Belum punya akun? <a href="register.php" class="text-blue-400 hover:underline">Daftar di sini</a></p>
        </div>
    </div>
    <script>
        const translations = {
            id: { title: "MY TASK", subtitle: "Masuk ke sistem pengumpulan tugas", emailLabel: "Email", passLabel: "Password", loginBtn: "Masuk", registerText: "Belum punya akun? ", registerLink: "Daftar di sini", errorDefault: "Email atau password salah!" },
            en: { title: "MY TASK", subtitle: "Login to assignment system", emailLabel: "Email", passLabel: "Password", loginBtn: "Login", registerText: "Don't have an account? ", registerLink: "Register here", errorDefault: "Invalid email or password!" }
        };
        let currentLang = localStorage.getItem('login_lang') || 'id';
        let currentTheme = localStorage.getItem('login_theme') || 'dark';
        function applyLanguage(lang) {
            const t = translations[lang];
            document.getElementById('appTitle').innerText = t.title;
            document.getElementById('subTitle').innerText = t.subtitle;
            document.getElementById('emailLabel').innerText = t.emailLabel;
            document.getElementById('passLabel').innerText = t.passLabel;
            document.getElementById('loginBtn').innerText = t.loginBtn;
            document.getElementById('registerLink').innerHTML = `${t.registerText}<a href="register.php" class="text-blue-400 hover:underline">${t.registerLink}</a>`;
            const errorMsgElem = document.querySelector('#errorMsg');
            if (errorMsgElem && errorMsgElem.innerText.includes('Email atau password salah')) errorMsgElem.innerText = t.errorDefault;
            localStorage.setItem('login_lang', lang);
        }
        function applyTheme(theme) {
            if (theme === 'light') {
                document.body.classList.remove('dark'); document.body.classList.add('light');
                document.querySelector('#themeToggle i').classList.remove('fa-moon'); document.querySelector('#themeToggle i').classList.add('fa-sun');
            } else {
                document.body.classList.remove('light'); document.body.classList.add('dark');
                document.querySelector('#themeToggle i').classList.remove('fa-sun'); document.querySelector('#themeToggle i').classList.add('fa-moon');
            }
            localStorage.setItem('login_theme', theme);
        }
        document.getElementById('langToggle').addEventListener('click', () => { currentLang = (currentLang === 'id') ? 'en' : 'id'; applyLanguage(currentLang); });
        document.getElementById('themeToggle').addEventListener('click', () => { currentTheme = (currentTheme === 'dark') ? 'light' : 'dark'; applyTheme(currentTheme); });
        applyLanguage(currentLang);
        applyTheme(currentTheme);
    </script>
</body>
</html>