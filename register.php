<?php 
include 'includes/db.php'; 

$requested_lang = $_POST['lang'] ?? '';
if (in_array($requested_lang, ['id', 'en'], true)) {
    $_SESSION['lang'] = $requested_lang;
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MY ACADEMIC</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            transition: background 0.3s, color 0.3s;
            color: #f8fafc;
            background: radial-gradient(circle at 20% 30%, #141b2d, #0b1020);
        }
        html.light-mode body {
            background: radial-gradient(circle at 20% 30%, #f4efe4, #e8dcc8);
            color: #0f172a;
        }
        .glass-card {
            background: rgba(17, 24, 39, 0.60);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: background 0.3s, border 0.3s;
        }
        html.light-mode .glass-card {
            background: rgba(255, 250, 241, 0.92);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(221, 212, 195, 0.9);
            box-shadow: 0 12px 30px rgba(61, 45, 32, 0.08);
        }
        .toggle-btn {
            cursor: pointer;
            transition: all 0.2s;
        }
        .toggle-btn:hover {
            opacity: 0.8;
        }
        input, select {
            background: rgba(255, 250, 241, 0.10);
            border: 1px solid rgba(221, 212, 195, 0.18);
            color: #f8fafc;
        }
        select option {
            background: #0f172a;
            color: #f8fafc;
        }
        html.light-mode input, html.light-mode select {
            background: rgba(255, 250, 241, 0.98);
            border: 1px solid rgba(210, 198, 179, 0.9);
            color: #0f172a;
        }
        html.light-mode select option {
            background: #fffaf1;
            color: #0f172a;
        }
        html.light-mode input::placeholder {
            color: #94a3b8;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.18);
        }
        html.light-mode .text-gray-300 { color: #475569 !important; }
        html.light-mode .text-gray-400 { color: #64748b !important; }
        html.light-mode .text-white { color: #0f172a !important; }
        html.light-mode .toggle-btn { color: #334155 !important; }
        html.light-mode .toggle-btn:hover { color: #111827 !important; }
        html.light-mode .bg-white\/10 { background: rgba(255, 255, 255, 0.72) !important; }
        html.light-mode .border-gray-600 { border-color: rgba(148, 163, 184, 0.35) !important; }
        html.light-mode .bg-blue-600 { background: #2563eb !important; }
        html.light-mode .hover\:bg-blue-700:hover { background: #1d4ed8 !important; }
        html.light-mode input::placeholder {
            color: #94a3b8;
        }
    </style>
</head>
<body class="dark">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="glass-card rounded-2xl shadow-2xl w-full max-w-md p-8 transition-all">
            <div class="flex justify-end gap-3 mb-4">
                <button id="themeToggle" class="toggle-btn text-gray-200 hover:text-white text-xl">
                    <i class="fas fa-moon"></i>
                </button>
                <button id="langToggle" class="toggle-btn text-gray-200 hover:text-white text-sm font-semibold px-2 py-1 rounded-lg bg-white/10">
                    EN
                </button>
            </div>

            <h2 id="formTitle" class="text-3xl font-bold text-blue-400 mb-2">Daftar Akun</h2>
            <p id="formDesc" class="text-gray-300 mb-6">Bergabung dengan MY ACADEMIC</p>

            <!-- Tempat pesan error/success -->
            <div id="messageArea" class="mb-4"></div>

            <form id="registerForm" action="" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                <input id="formLang" type="hidden" name="lang" value="id">
                <div>
                    <label id="nameLabel" class="text-xs font-semibold text-gray-300 uppercase ml-1">Nama Lengkap</label>
                    <input id="nameInput" type="text" name="nama" placeholder="Masukkan nama lengkap" class="w-full p-3 rounded-lg" required>
                </div>
                <div>
                    <label id="roleLabel" class="text-xs font-semibold text-gray-300 uppercase ml-1">Role</label>
                    <select id="roleSelect" name="role" class="w-full p-3 rounded-lg" required>
                        <option value="mahasiswa">Mahasiswa (Gunakan NIM)</option>
                        <option value="dosen">Dosen (Gunakan NIP)</option>
                    </select>
                </div>
                <div>
                    <label id="emailLabel" class="text-xs font-semibold text-gray-300 uppercase ml-1">Email</label>
                    <input id="emailInput" type="email" name="email" placeholder="Masukkan email" class="w-full p-3 rounded-lg" required>
                </div>
                <div>
                    <label id="passLabel" class="text-xs font-semibold text-gray-300 uppercase ml-1">Password</label>
                    <input id="passwordInput" type="password" name="password" placeholder="Masukkan kata sandi" class="w-full p-3 rounded-lg" required minlength="6">
                </div>
                <button id="registerBtn" type="submit" name="register" class="w-full bg-blue-600 hover:bg-blue-700 text-white p-3 rounded-lg font-bold transition">Daftar</button>
            </form>
            <p id="loginLink" class="mt-6 text-center text-sm text-gray-400">
                Sudah punya akun? <a href="login.php" class="text-blue-400 hover:underline">Login</a>
            </p>
        </div>
    </div>

    <script>
        // Multi bahasa
        const trans = {
            id: {
                title: "Daftar Akun",
                desc: "Bergabung dengan MY ACADEMIC",
                nameLabel: "Nama Lengkap",
                roleLabel: "Peran",
                emailLabel: "Email",
                passLabel: "Kata sandi",
                namePlaceholder: "Masukkan nama lengkap",
                emailPlaceholder: "Masukkan email",
                passPlaceholder: "Masukkan kata sandi",
                roleStudent: "Mahasiswa (Gunakan NIM)",
                roleLecturer: "Dosen (Gunakan NIP)",
                btn: "Daftar",
                loginText: "Sudah punya akun? ",
                loginLink: "Masuk",
                errorDefault: "Terjadi kesalahan. Periksa kembali data Anda.",
                successMsg: "Registrasi berhasil. Silakan masuk."
            },
            en: {
                title: "Register Account",
                desc: "Join MY ACADEMIC",
                nameLabel: "Full Name",
                roleLabel: "Role",
                emailLabel: "Email",
                passLabel: "Password",
                namePlaceholder: "Enter full name",
                emailPlaceholder: "Enter email",
                passPlaceholder: "Enter password",
                roleStudent: "Student (Use NIM)",
                roleLecturer: "Lecturer (Use NIP)",
                btn: "Register",
                loginText: "Already have an account? ",
                loginLink: "Sign In",
                errorDefault: "An error occurred. Please check your data.",
                successMsg: "Registration successful. Please sign in."
            }
        };
        let currentLang = localStorage.getItem('lang') || localStorage.getItem('register_lang') || 'id';
        let currentTheme = localStorage.getItem('theme') || localStorage.getItem('register_theme') || 'dark';

        function applyLanguage(lang) {
            const t = trans[lang];
            document.documentElement.lang = lang;
            document.getElementById('langToggle').innerText = lang.toUpperCase();
            document.getElementById('formTitle').innerText = t.title;
            document.getElementById('formDesc').innerText = t.desc;
            document.getElementById('nameLabel').innerText = t.nameLabel;
            document.getElementById('roleLabel').innerText = t.roleLabel;
            document.getElementById('emailLabel').innerText = t.emailLabel;
            document.getElementById('passLabel').innerText = t.passLabel;
            document.getElementById('nameInput').placeholder = t.namePlaceholder;
            document.getElementById('emailInput').placeholder = t.emailPlaceholder;
            document.getElementById('passwordInput').placeholder = t.passPlaceholder;
            const roleSelect = document.getElementById('roleSelect');
            roleSelect.options[0].text = t.roleStudent;
            roleSelect.options[1].text = t.roleLecturer;
            document.getElementById('registerBtn').innerText = t.btn;
            const loginPara = document.getElementById('loginLink');
            loginPara.innerHTML = `${t.loginText}<a href="login.php" class="text-blue-400 hover:underline">${t.loginLink}</a>`;
            localStorage.setItem('lang', lang);
            localStorage.setItem('ui_lang', lang);
            localStorage.setItem('register_lang', lang);
            document.getElementById('formLang').value = lang;
        }

        function applyTheme(theme) {
            if (theme === 'light') {
                document.documentElement.classList.add('light-mode');
                const icon = document.querySelector('#themeToggle i');
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            } else {
                document.documentElement.classList.remove('light-mode');
                const icon = document.querySelector('#themeToggle i');
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
            }
            localStorage.setItem('theme', theme);
            localStorage.setItem('register_theme', theme);
        }

        document.getElementById('themeToggle').addEventListener('click', () => {
            currentTheme = currentTheme === 'dark' ? 'light' : 'dark';
            applyTheme(currentTheme);
        });
        document.getElementById('langToggle').addEventListener('click', () => {
            currentLang = currentLang === 'id' ? 'en' : 'id';
            applyLanguage(currentLang);
        });

        applyLanguage(currentLang);
        applyTheme(currentTheme);

        // Menampilkan pesan dari PHP
        <?php if (isset($_POST['register'])): 
            // Proses registrasi disini (sama seperti kode register sebelumnya)
            // Simpan hasil ke variabel $msg dan $success
            $msg = "";
            $isSuccess = false;
            if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
                $msg = "CSRF token tidak valid.";
            } else {
                $nama = trim($_POST['nama']);
                $email = strtolower(trim($_POST['email']));
                $password = $_POST['password'];
                $role = $_POST['role'];
                $errors = [];
                if (empty($nama)) $errors[] = "Nama harus diisi.";
                if (empty($email)) $errors[] = "Email harus diisi.";
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Format email tidak valid.";
                if (strlen($password) < 6) $errors[] = "Password minimal 6 karakter.";
                if (!in_array($role, ['mahasiswa','dosen'])) $errors[] = "Role tidak valid.";
                if ($role == 'dosen' && !preg_match("/^[0-9]+@dosen\.trunojoyo\.ac\.id$/", $email)) $errors[] = "Format email dosen salah.";
                if ($role == 'mahasiswa' && !preg_match("/^[0-9]+@student\.trunojoyo\.ac\.id$/", $email)) $errors[] = "Format email mahasiswa salah.";
                if (empty($errors)) {
                    $stmt = $conn->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $stmt->store_result();
                    if ($stmt->num_rows > 0) {
                        $errors[] = "Email sudah terdaftar.";
                    }
                    $stmt->close();
                }
                if (empty($errors)) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $nama, $email, $hashed, $role);
                    if ($stmt->execute()) {
                        $isSuccess = true;
                        $msg = "Registrasi Berhasil! Silakan Login.";
                    } elseif ((int)$stmt->errno === 1062) {
                        $msg = "Email sudah terdaftar.";
                    } else {
                        $msg = "Error: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $msg = implode("<br>", $errors);
                }
            }
            if ($isSuccess): ?>
                setTimeout(() => {
                    const msgDiv = document.getElementById('messageArea');
                    msgDiv.innerHTML = '<div class="bg-green-500/20 border border-green-500 text-green-300 p-3 rounded-lg text-sm"><?= htmlspecialchars($msg) ?></div>';
                }, 100);
            <?php elseif (!empty($msg)): ?>
                setTimeout(() => {
                    const msgDiv = document.getElementById('messageArea');
                    msgDiv.innerHTML = '<div class="bg-red-500/20 border border-red-500 text-red-300 p-3 rounded-lg text-sm"><?= htmlspecialchars($msg) ?></div>';
                }, 100);
            <?php endif; ?>
        <?php endif; ?>
    </script>
</body>
</html>
