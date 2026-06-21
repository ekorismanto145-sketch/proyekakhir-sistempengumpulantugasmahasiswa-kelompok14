<?php
// Pastikan file kamus dipanggil jika belum
if (!function_exists('t')) { include_once 'lang.php'; }

// Avatar
if (isset($_SESSION['user']['foto_profil']) && !empty($_SESSION['user']['foto_profil'])) {
    $avatar_url = 'uploads/' . $_SESSION['user']['foto_profil'];
} else {
    $avatar_url = 'https://ui-avatars.com/api/?name=' . urlencode(substr($_SESSION['user']['nama'] ?? 'User', 0, 2)) . '&background=151E32&color=fff';
}

 $is_real_admin = (isset($_SESSION['user']['original_role']) && $_SESSION['user']['original_role'] === 'admin') || (($_SESSION['user']['role'] ?? '') === 'admin');
 $current_role = $_SESSION['user']['role'] ?? 'Guest';

 $admin_keluhan_baru = 0;
 $notif_belum_baca = 0;
 if (isset($_SESSION['user']['id'])) {
     $current_user_id = (int)$_SESSION['user']['id'];
     $stmt_badge = $conn->prepare("SELECT COUNT(*) FROM reports WHERE `STATUS` = 'belum_dibaca'");
     $stmt_badge->execute();
     $admin_keluhan_baru = (int)$stmt_badge->get_result()->fetch_row()[0];
     $stmt_badge->close();

     $stmt_badge = $conn->prepare("SELECT COUNT(*) FROM activities WHERE user_id = ? AND is_read = 0");
     $stmt_badge->bind_param("i", $current_user_id);
     $stmt_badge->execute();
     $notif_belum_baca = (int)$stmt_badge->get_result()->fetch_row()[0];
     $stmt_badge->close();
 }
?>

<div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-40 hidden"></div>

<div id="sidebar" class="fixed inset-y-0 left-0 w-[85vw] max-w-xs sm:w-64 bg-surface border-r border-gray-800 shadow-2xl z-50 transform -translate-x-full sidebar-transition flex flex-col">
    <div class="p-6 border-b border-gray-800 flex justify-between items-center">
        <h1 class="text-xl font-bold tracking-wider text-white">MY<span class="text-gray-500">TASK</span></h1>
        <button onclick="toggleSidebar()" class="text-gray-500 hover:text-redaccent transition"><i class="fas fa-times text-lg"></i></button>
    </div>
    <div class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
        <a href="index.php" class="flex items-center p-3 hover:bg-gray-800 rounded-xl text-gray-300 hover:text-white transition"><i class="fas fa-chalkboard-teacher mr-3 w-5 text-center"></i> <?= t('kelas') ?></a>
        <a href="notifikasi.php" class="flex items-center justify-between p-3 hover:bg-gray-800 rounded-xl text-gray-300 hover:text-white transition">
            <span class="flex items-center"><i class="fas fa-bell mr-3 w-5 text-center"></i> <?= t('notifikasi') ?></span>
            <?php if ($notif_belum_baca > 0): ?>
                <span class="ml-3 inline-flex items-center justify-center min-w-6 h-6 px-2 rounded-full bg-blue-500/15 text-blue-400 text-[11px] font-bold border border-blue-500/30"><?= $notif_belum_baca ?></span>
            <?php endif; ?>
        </a>
        <a href="kalender.php" class="flex items-center p-3 hover:bg-gray-800 rounded-xl text-gray-300 hover:text-white transition"><i class="fas fa-calendar-alt mr-3 w-5 text-center"></i> <?= t('kalender') ?></a>
        <div class="pt-4 pb-2">
            <p class="text-[10px] font-bold text-gray-500 px-3 uppercase tracking-widest border-b border-gray-800 pb-2 mb-2"><?= t('terdaftar') ?></p>
            <?php
            $role_cek = $_SESSION['user']['role'] ?? 'mahasiswa';
            $user_id_cek = $_SESSION['user']['id'] ?? 0;
            if ($role_cek === 'dosen' || $role_cek === 'admin') {
                $stmt = $conn->prepare("SELECT * FROM classes WHERE dosen_id = ? ORDER BY created_at DESC LIMIT 5");
                $stmt->bind_param("i", $user_id_cek);
            } else {
                $stmt = $conn->prepare("SELECT c.* FROM classes c JOIN class_members cm ON c.id = cm.class_id WHERE cm.mahasiswa_id = ? ORDER BY cm.joined_at DESC LIMIT 5");
                $stmt->bind_param("i", $user_id_cek);
            }
            $stmt->execute();
            $res_sidebar = $stmt->get_result();
            while($row = $res_sidebar->fetch_assoc()){
            ?>
                <a href="detail_kelas.php?id=<?= $row['id'] ?>" class="flex items-center p-2.5 hover:bg-gray-800 rounded-xl text-gray-400 hover:text-white transition text-sm">
                    <i class="fas fa-book mr-3 text-blue-500"></i> <?= htmlspecialchars($row['nama_kelas']); ?>
                </a>
            <?php } $stmt->close(); ?>
        </div>
        <div class="pt-2 border-t border-gray-800">
            <a href="tugas.php" class="flex items-center p-3 hover:bg-gray-800 rounded-xl text-gray-300 hover:text-white transition"><i class="fas fa-tasks mr-3 w-5 text-center"></i> <?= t('daftar_tugas') ?></a>

            <?php if ($current_role === 'dosen'): ?>
                <a href="rekap_nilai.php" class="flex items-center p-3 hover:bg-gray-800 rounded-xl text-gray-300 hover:text-white transition"><i class="fas fa-file-pdf mr-3 w-5 text-center"></i> <?= t('rekap_nilai') ?></a>
            <?php endif; ?>

            <?php if ($current_role === 'admin'): ?>
                <a href="keluhan.php" class="flex items-center justify-between p-3 hover:bg-gray-800 rounded-xl text-gray-300 hover:text-white transition">
                    <span class="flex items-center"><i class="fas fa-envelope-open-text mr-3 w-5 text-center"></i> <?= t('complaints') ?></span>
                    <?php if ($admin_keluhan_baru > 0): ?>
                        <span class="ml-3 inline-flex items-center justify-center min-w-6 h-6 px-2 rounded-full bg-yellow-500/15 text-yellow-400 text-[11px] font-bold border border-yellow-500/30"><?= $admin_keluhan_baru ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <a href="settings.php" class="flex items-center p-3 hover:bg-gray-800 rounded-xl text-gray-300 hover:text-white transition"><i class="fas fa-cog mr-3 w-5 text-center"></i> <?= t('setelan') ?></a>
            <a href="bantuan.php" class="flex items-center p-3 hover:bg-gray-800 rounded-xl text-gray-300 hover:text-white transition"><i class="fas fa-question-circle mr-3 w-5 text-center"></i> <?= t('bantuan') ?></a>
            <?php if ($current_role === 'admin'): ?>
                <a href="kalender.php" class="flex items-center p-3 hover:bg-gray-800 rounded-xl text-gray-300 hover:text-white transition"><i class="fas fa-calendar-day mr-3 w-5 text-center"></i> <?= t('kelola_kalender') ?></a>
            <?php endif; ?>
        </div>
    </div>
</div>

<nav class="bg-surface/80 backdrop-blur-md border-b border-gray-800 sticky top-0 z-30 px-3 sm:px-4 py-3 flex justify-between items-center gap-2">
    <div class="flex items-center space-x-4">
        <button onclick="toggleSidebar()" class="p-2 text-gray-400 hover:text-[var(--text-main)] hover:bg-gray-800 rounded-lg transition"><i class="fas fa-bars text-xl"></i></button>
        <div class="hidden md:flex items-center space-x-2"><div class="w-1.5 h-6 bg-redaccent rounded-full"></div><span class="text-lg font-bold text-[var(--text-main)] tracking-widest">MY TASK</span></div>
    </div>
    <div class="flex items-center space-x-2 md:space-x-4">
        <!-- Toggle Bahasa Dropdown -->
        <?php
        $currentLang = $_SESSION['lang'] ?? 'id';
        $languageReturnUrl = $_SERVER['REQUEST_URI'] ?? '/index.php';
        $indonesianUrl = 'set_language.php?' . http_build_query(['lang' => 'id', 'return' => $languageReturnUrl]);
        $englishUrl = 'set_language.php?' . http_build_query(['lang' => 'en', 'return' => $languageReturnUrl]);
        ?>
        <button id="themeToggleBtn" type="button" onclick="toggleTheme()" class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-full border border-gray-700 bg-darkbg text-gray-300 hover:bg-gray-800 transition text-sm font-medium">
            <i id="themeToggleIcon" class="fas fa-moon text-blue-400"></i>
            <span id="themeToggleLabel"><?= t('dark') ?></span>
        </button>
        <div class="relative">
            <button id="langMenuBtn" class="flex items-center space-x-1 bg-darkbg border border-gray-700 rounded-full px-3 py-1.5 text-sm font-medium text-gray-300 hover:bg-gray-800 transition">
                <i class="fas fa-globe mr-1"></i>
                <span><?= $currentLang === 'id' ? 'ID' : 'EN' ?></span>
                <i class="fas fa-chevron-down text-xs ml-1"></i>
            </button>
            <div id="langDropdown" class="dropdown-menu absolute right-0 mt-2 w-32 bg-surface border border-gray-700 rounded-xl shadow-lg z-50 overflow-hidden hidden">
                <a href="<?= htmlspecialchars($indonesianUrl, ENT_QUOTES, 'UTF-8') ?>" onclick="localStorage.setItem('lang','id'); localStorage.setItem('ui_lang','id');" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-800 transition">ID - <?= t('language_indonesia') ?></a>
                <a href="<?= htmlspecialchars($englishUrl, ENT_QUOTES, 'UTF-8') ?>" onclick="localStorage.setItem('lang','en'); localStorage.setItem('ui_lang','en');" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-800 transition">EN - <?= t('language_english') ?></a>
            </div>
        </div>

        <?php $role_color = ($current_role == 'admin') ? 'text-red-400 border-red-500/30' : (($current_role == 'dosen') ? 'text-purple-400 border-purple-500/30' : 'text-blue-400 border-blue-500/30'); ?>
        <span class="hidden md:inline-block px-3 py-1 bg-darkbg text-[10px] font-bold uppercase tracking-widest <?= $role_color ?> rounded-full border"><?= htmlspecialchars($current_role); ?></span>
        <div class="relative">
            <button onclick="toggleDropdown('profileMenu')" class="focus:outline-none flex items-center justify-center w-10 h-10 rounded-full border border-gray-600 hover:border-blue-500 transition bg-darkbg overflow-hidden"><img src="<?= $avatar_url ?>" class="w-full h-full object-cover" alt="Profile"></button>
            <div id="profileMenu" class="dropdown-menu absolute right-0 mt-3 w-[calc(100vw-1rem)] max-w-xs sm:w-72 bg-surface rounded-2xl shadow-2xl border border-gray-700 z-50 overflow-hidden">
                <div class="text-center p-5 border-b border-gray-700 bg-darkbg">
                    <p class="text-sm font-medium text-gray-300"><?= htmlspecialchars($_SESSION['user']['email'] ?? 'user@email.com'); ?></p>
                    <div class="w-16 h-16 rounded-full mx-auto my-4 bg-gray-800 border-2 border-gray-600 overflow-hidden"><img src="<?= $avatar_url ?>" class="w-full h-full object-cover" alt="Profile"></div>
                    <p class="text-lg text-[var(--text-main)] font-bold">Halo, <?= htmlspecialchars($_SESSION['user']['nama'] ?? 'User'); ?>!</p>
                    <a href="settings.php" class="inline-block mt-3 px-4 py-2 border border-gray-600 rounded-full text-xs text-gray-300 hover:bg-gray-800 transition w-full text-center"><?= t('manage_account_google') ?></a>
                </div>
                <?php if($is_real_admin): ?>
                <div class="py-2 bg-surface border-b border-gray-700">
                    <p class="text-[10px] font-bold text-gray-500 px-5 uppercase tracking-widest mb-1"><?= t('show_as') ?>:</p>
                    <a href="?switch_role=admin" class="w-full text-left px-5 py-2.5 hover:bg-gray-800 text-gray-300 hover:text-white flex items-center text-sm <?= ($current_role == 'admin') ? 'text-red-400 font-bold bg-darkbg' : '' ?>"><i class="fas fa-user-shield mr-4 w-4 text-center"></i> <?= t('admin_role') ?></a>
                    <a href="?switch_role=dosen" class="w-full text-left px-5 py-2.5 hover:bg-gray-800 text-gray-300 hover:text-white flex items-center text-sm <?= ($current_role == 'dosen') ? 'text-purple-400 font-bold bg-darkbg' : '' ?>"><i class="fas fa-chalkboard-teacher mr-4 w-4 text-center"></i> <?= t('dosen_role') ?></a>
                    <a href="?switch_role=mahasiswa" class="w-full text-left px-5 py-2.5 hover:bg-gray-800 text-gray-300 hover:text-white flex items-center text-sm <?= ($current_role == 'mahasiswa') ? 'text-blue-400 font-bold bg-darkbg' : '' ?>"><i class="fas fa-user-graduate mr-4 w-4 text-center"></i> <?= t('mahasiswa_role') ?></a>
                </div>
                <?php endif; ?>
                <div class="py-2 bg-surface">
                    <a href="logout.php?action=add_account" class="w-full text-left px-5 py-3 hover:bg-gray-800 text-gray-300 hover:text-white flex items-center text-sm"><i class="fas fa-user-plus mr-4 w-4 text-center"></i> <?= t('tambahkan_akun_lainnya') ?></a>
                    <a href="settings.php" class="w-full text-left px-5 py-3 hover:bg-gray-800 text-gray-300 hover:text-white flex items-center text-sm"><i class="fas fa-laptop mr-4 w-4 text-center"></i> <?= t('kelola_akun_perangkat') ?></a>
                </div>
                <div class="py-3 bg-darkbg border-t border-gray-700 flex justify-center space-x-3 text-[11px] text-gray-500">
                    <a href="privasi.php" class="hover:text-gray-300"><?= t('kebijakan_privasi') ?></a> • 
                    <a href="persyaratan.php" class="hover:text-gray-300"><?= t('persyaratan_layanan') ?></a>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
    function toggleSidebar() { document.getElementById('sidebar').classList.toggle('-translate-x-full'); document.getElementById('overlay').classList.toggle('hidden'); }
    function toggleDropdown(id) { document.querySelectorAll('.dropdown-menu').forEach(menu => { if(menu.id !== id) menu.classList.remove('active'); }); document.getElementById(id).classList.toggle('active'); }
    window.onclick = function(event) { if (!event.target.closest('.relative')) { document.querySelectorAll('.dropdown-menu').forEach(menu => menu.classList.remove('active')); } }
    // Dropdown bahasa
    const langBtn = document.getElementById('langMenuBtn');
    const langDropdown = document.getElementById('langDropdown');
    if (langBtn && langDropdown) {
        langBtn.addEventListener('click', (e) => { e.stopPropagation(); langDropdown.classList.toggle('hidden'); langDropdown.classList.toggle('active'); });
        window.addEventListener('click', () => { langDropdown.classList.add('hidden'); langDropdown.classList.remove('active'); });
    }
    localStorage.setItem('lang', '<?php echo $currentLang; ?>');
    localStorage.setItem('ui_lang', '<?php echo $currentLang; ?>');
</script>
<script>
    function syncThemeToggleButton() {
        const isLight = document.documentElement.classList.contains('light-mode');
        const icon = document.getElementById('themeToggleIcon');
        const label = document.getElementById('themeToggleLabel');
        const btn = document.getElementById('themeToggleBtn');

        if (!icon || !label || !btn) return;

        if (isLight) {
            icon.classList.remove('fa-moon', 'text-blue-400');
            icon.classList.add('fa-sun', 'text-yellow-400');
            label.textContent = '<?= t('light') ?>';
            btn.classList.add('border-yellow-500/30');
            btn.classList.remove('border-gray-700');
        } else {
            icon.classList.remove('fa-sun', 'text-yellow-400');
            icon.classList.add('fa-moon', 'text-blue-400');
            label.textContent = '<?= t('dark') ?>';
            btn.classList.add('border-gray-700');
            btn.classList.remove('border-yellow-500/30');
        }
    }

    syncThemeToggleButton();

    const originalToggleTheme = window.toggleTheme;
    window.toggleTheme = function () {
        if (typeof originalToggleTheme === 'function') {
            originalToggleTheme();
            syncThemeToggleButton();
        }
    };

    window.addEventListener('storage', (event) => {
        if (event.key === 'theme' || event.key === 'login_theme') {
            syncThemeToggleButton();
        }
    });
</script>
