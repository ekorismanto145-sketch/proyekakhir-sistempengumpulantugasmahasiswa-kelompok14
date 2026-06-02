<?php 
include 'includes/db.php'; 

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
    <title>Register - MY TASK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            transition: background 0.3s, color 0.3s;
        }
        body.dark {
            background: radial-gradient(circle at 20% 30%, #0a0a0a, #000000);
            color: #f0f0f0;
        }
        body.light {
            background: radial-gradient(circle at 20% 30%, #e0e0e0, #b0b0b0);
            color: #1f2937;
        }
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
        .toggle-btn {
            cursor: pointer;
            transition: all 0.2s;
        }
        .toggle-btn:hover {
            opacity: 0.8;
        }
        input, select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }
        body.light input, body.light select {
            background: rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.2);
            color: #1f2937;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #3b82f6;
            ring: 2px solid #3b82f6;
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
            <p id="formDesc" class="text-gray-300 mb-6">Bergabung dengan MY TASK</p>

            <!-- Tempat pesan error/success -->
            <div id="messageArea" class="mb-4"></div>

            <form id="registerForm" action="" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                <div>
                    <label id="nameLabel" class="text-xs font-semibold text-gray-300 uppercase ml-1">Nama Lengkap</label>
                    <input type="text" name="nama" placeholder="Nama Lengkap" class="w-full p-3 rounded-lg" required>
                </div>
                <div>
                    <label id="roleLabel" class="text-xs font-semibold text-gray-300 uppercase ml-1">Role</label>
                    <select name="role" class="w-full p-3 rounded-lg" required>
                        <option value="mahasiswa">Mahasiswa (Gunakan NIM)</option>
                        <option value="dosen">Dosen (Gunakan NIP)</option>
                    </select>
                </div>
                <div>
                    <label id="emailLabel" class="text-xs font-semibold text-gray-300 uppercase ml-1">Email</label>
                    <input type="email" name="email" placeholder="Contoh: 25044...@student.trunojoyo.ac.id" class="w-full p-3 rounded-lg" required>
                </div>
                <div>
                    <label id="passLabel" class="text-xs font-semibold text-gray-300 uppercase ml-1">Password</label>
                    <input type="password" name="password" placeholder="Minimal 6 karakter" class="w-full p-3 rounded-lg" required minlength="6">
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
                desc: "Bergabung dengan MY TASK",
                nameLabel: "Nama Lengkap",
                roleLabel: "Role",
                emailLabel: "Email",
                passLabel: "Password",
                btn: "Daftar",
                loginText: "Sudah punya akun? ",
                loginLink: "Login",
                errorDefault: "Terjadi kesalahan. Periksa kembali data Anda.",
                successMsg: "Registrasi Berhasil! Silakan Login."
            },
            en: {
                title: "Register Account",
                desc: "Join MY TASK",
                nameLabel: "Full Name",
                roleLabel: "Role",
                emailLabel: "Email",
                passLabel: "Password",
                btn: "Register",
                loginText: "Already have an account? ",
                loginLink: "Login",
                errorDefault: "An error occurred. Please check your data.",
                successMsg: "Registration Successful! Please login."
            }
        };
        let currentLang = localStorage.getItem('register_lang') || 'id';
        let currentTheme = localStorage.getItem('register_theme') || 'dark';

        function applyLanguage(lang) {
            const t = trans[lang];
            document.getElementById('formTitle').innerText = t.title;
            document.getElementById('formDesc').innerText = t.desc;
            document.getElementById('nameLabel').innerText = t.nameLabel;
            document.getElementById('roleLabel').innerText = t.roleLabel;
            document.getElementById('emailLabel').innerText = t.emailLabel;
            document.getElementById('passLabel').innerText = t.passLabel;
            document.getElementById('registerBtn').innerText = t.btn;
            const loginPara = document.getElementById('loginLink');
            loginPara.innerHTML = `${t.loginText}<a href="login.php" class="text-blue-400 hover:underline">${t.loginLink}</a>`;
            localStorage.setItem('register_lang', lang);
        }

        function applyTheme(theme) {
            if (theme === 'light') {
                document.body.classList.remove('dark');
                document.body.classList.add('light');
                const icon = document.querySelector('#themeToggle i');
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            } else {
                document.body.classList.remove('light');
                document.body.classList.add('dark');
                const icon = document.querySelector('#themeToggle i');
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
            }
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
                $email = trim($_POST['email']);
                $password = $_POST['password'];
                $role = $_POST['role'];
                $errors = [];
                if (empty($nama)) $errors[] = "Nama harus diisi.";
                if (empty($email)) $errors[] = "Email harus diisi.";
                if (strlen($password) < 6) $errors[] = "Password minimal 6 karakter.";
                if (!in_array($role, ['mahasiswa','dosen'])) $errors[] = "Role tidak valid.";
                if ($role == 'dosen' && !preg_match("/^[0-9]+@dosen\.trunojoyo\.ac\.id$/", $email)) $errors[] = "Format email dosen salah.";
                if ($role == 'mahasiswa' && !preg_match("/^[0-9]+@student\.trunojoyo\.ac\.id$/", $email)) $errors[] = "Format email mahasiswa salah.";
                if (empty($errors)) {
                    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
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