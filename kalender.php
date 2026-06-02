<?php 
include 'includes/db.php'; 
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }

$user = $_SESSION['user']; 
$role = $user['role'];

// Pastikan tabel holidays ada
$conn->query("CREATE TABLE IF NOT EXISTS holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    holiday_date DATE NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Proses penambahan / penghapusan libur (admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'admin') {
    if (isset($_POST['add_holiday'])) {
        $hdate = trim($_POST['holiday_date'] ?? '');
        $htitle = trim($_POST['title'] ?? '');
        $hdesc = trim($_POST['description'] ?? '');
        if ($hdate) {
            $ins = $conn->prepare("INSERT INTO holidays (holiday_date, title, description, created_by) VALUES (?, ?, ?, ?)");
            $ins->bind_param('sssi', $hdate, $htitle, $hdesc, $user['id']);
            $ins->execute();
            $ins->close();
        }
        header('Location: kalender.php'); exit();
    }
    if (isset($_POST['delete_holiday'])) {
        $hid = (int)$_POST['holiday_id'];
        $del = $conn->prepare("DELETE FROM holidays WHERE id = ?");
        $del->bind_param('i', $hid);
        $del->execute();
        $del->close();
        header('Location: kalender.php'); exit();
    }
}

// Ambil holidays untuk ditampilkan
$holRes = $conn->query("SELECT * FROM holidays ORDER BY holiday_date ASC");
$holidays = [];
while($hr = $holRes->fetch_assoc()) {
    $holidays[$hr['holiday_date']] = ['id'=>$hr['id'],'title'=>$hr['title'],'description'=>$hr['description']];
}

include 'includes/header.php'; 
include 'includes/navbar.php'; 
?>

<main class="max-w-3xl mx-auto p-4 md:p-8">
    <!-- Header Halaman -->
    <div class="flex items-center border-b border-gray-800 pb-4 mb-6">
        <div class="w-1.5 h-7 bg-blue-500 rounded-full mr-3 shadow-[0_0_10px_rgba(59,130,246,0.6)]"></div>
        <h2 class="text-2xl font-extrabold text-white tracking-wide">Kalender Akademik</h2>
    </div>

    <!-- Container Kalender (Ukurannya sudah diperkecil) -->
    <div class="bg-surface border border-gray-800 rounded-3xl p-5 md:p-6 shadow-2xl transition-colors">
        <?php if ($role === 'admin'): ?>
        <div class="mb-4 p-4 bg-darkbg border border-gray-700 rounded-lg">
            <h4 class="text-sm font-bold text-white mb-2">Kelola Tanggal Merah (Libur)</h4>
            <form method="POST" class="flex gap-2 flex-col md:flex-row items-start md:items-end">
                <div>
                    <label class="text-xs text-gray-400">Tanggal</label>
                    <input type="date" name="holiday_date" class="bg-darkbg border border-gray-700 px-3 py-2 rounded" required>
                </div>
                <div>
                    <label class="text-xs text-gray-400">Judul</label>
                    <input type="text" name="title" class="bg-darkbg border border-gray-700 px-3 py-2 rounded" placeholder="Mis: Libur Nasional">
                </div>
                <div class="flex-1">
                    <label class="text-xs text-gray-400">Keterangan</label>
                    <input type="text" name="description" class="w-full bg-darkbg border border-gray-700 px-3 py-2 rounded" placeholder="Keterangan singkat">
                </div>
                <div>
                    <button type="submit" name="add_holiday" class="px-4 py-2 bg-redaccent text-white rounded">Simpan</button>
                </div>
            </form>
            <?php if (!empty($holidays)): ?>
                <div class="mt-3 text-xs text-gray-400">
                    <strong>Daftar Tanggal Merah:</strong>
                    <ul>
                        <?php foreach($holidays as $hd => $meta): ?>
                            <li class="mt-1"><?= htmlspecialchars($hd) ?> - <?= htmlspecialchars($meta['title'] ?? '-') ?>
                                <form method="POST" style="display:inline-block;margin-left:8px;"><input type="hidden" name="holiday_id" value="<?= $meta['id'] ?>"><button type="submit" name="delete_holiday" class="text-xs text-red-400">hapus</button></form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Navigasi Bulan & Dropdown Tahun -->
        <div class="flex justify-between items-center mb-5">
            <button onclick="prevMonth()" class="w-9 h-9 rounded-full bg-darkbg border border-gray-700 hover:border-blue-500 text-gray-400 hover:text-white flex items-center justify-center transition shadow-lg">
                <i class="fas fa-chevron-left text-sm"></i>
            </button>
            
            <div class="flex items-center space-x-2 md:space-x-3">
                <h3 id="monthDisplay" class="text-lg md:text-xl font-bold text-white tracking-widest uppercase"></h3>
                
                <!-- DROPDOWN TAHUN -->
                <select id="yearSelect" onchange="changeYear()" class="bg-darkbg border border-gray-700 text-blue-400 font-bold text-lg md:text-xl rounded-lg px-2 py-1 focus:outline-none focus:border-blue-500 cursor-pointer transition-colors shadow-inner appearance-none text-center">
                    <!-- Opsi Tahun akan diisi otomatis oleh Javascript -->
                </select>
            </div>
            
            <button onclick="nextMonth()" class="w-9 h-9 rounded-full bg-darkbg border border-gray-700 hover:border-blue-500 text-gray-400 hover:text-white flex items-center justify-center transition shadow-lg">
                <i class="fas fa-chevron-right text-sm"></i>
            </button>
        </div>

        <!-- Grid Nama Hari -->
        <div class="grid grid-cols-7 gap-1 md:gap-2 mb-3 text-center font-bold text-[10px] md:text-xs uppercase tracking-wider">
            <div class="text-redaccent py-1">Min</div>
            <div class="text-gray-400 py-1">Sen</div>
            <div class="text-gray-400 py-1">Sel</div>
            <div class="text-gray-400 py-1">Rab</div>
            <div class="text-gray-400 py-1">Kam</div>
            <div class="text-gray-400 py-1">Jum</div>
            <div class="text-gray-400 py-1">Sab</div>
        </div>

        <!-- Grid Tanggal -->
        <div id="calendarDays" class="grid grid-cols-7 gap-1.5 md:gap-2">
            <!-- Tanggal akan di-generate di sini -->
        </div>

    </div>
</main>

<script>
    const monthNames = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
    
    // Ambil waktu saat ini
    let date = new Date();
    let currentMonth = date.getMonth();
    let currentYear = date.getFullYear();

    const monthDisplay = document.getElementById("monthDisplay");
    const yearSelect = document.getElementById("yearSelect");
    const calendarDays = document.getElementById("calendarDays");

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // FUNGSI MEMASUKKAN DAFTAR TAHUN KE DROPDOWN (10 Tahun ke belakang s/d 10 Tahun ke depan)
    function populateYears() {
        let actualYear = new Date().getFullYear();
        for (let y = actualYear - 10; y <= actualYear + 10; y++) {
            let opt = document.createElement("option");
            opt.value = y;
            opt.innerHTML = y;
            yearSelect.appendChild(opt);
        }
    }
    populateYears();

    // FUNGSI RENDER KALENDER
    function renderCalendar() {
        calendarDays.innerHTML = "";
        
        // Update teks Bulan dan dropdown Tahun
        monthDisplay.innerHTML = monthNames[currentMonth];
        yearSelect.value = currentYear; 

        let firstDay = new Date(currentYear, currentMonth, 1).getDay();
        let daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
        let today = new Date();

        // Render kotak kosong (sebelum tanggal 1)
        for (let i = 0; i < firstDay; i++) {
            let emptyDiv = document.createElement("div");
            // Kotak diperkecil (h-10 md:h-14)
            emptyDiv.className = "h-10 md:h-14 rounded-lg bg-darkbg/30 border border-transparent";
            calendarDays.appendChild(emptyDiv);
        }

        // Render tanggal
        for (let i = 1; i <= daysInMonth; i++) {
            let dayDiv = document.createElement("div");
            const hol = isHoliday(currentYear, currentMonth, i);
            const isToday = (i === today.getDate() && currentMonth === today.getMonth() && currentYear === today.getFullYear());

            if (hol) {
                dayDiv.className = "h-16 md:h-20 rounded-lg bg-red-600 border border-red-400 flex flex-col items-center justify-center text-white shadow-[0_0_15px_rgba(239,68,68,0.25)] cursor-pointer hover:scale-[1.02] transition-transform overflow-hidden px-1 py-1 text-center";
            } else if (isToday) {
                // Style Hari Ini
                dayDiv.className = "h-10 md:h-14 rounded-lg bg-blue-600 border border-blue-400 flex items-center justify-center text-sm md:text-base font-bold text-white shadow-[0_0_15px_rgba(37,99,235,0.5)] cursor-pointer hover:scale-105 transition-transform";
            } else {
                // Style Hari Biasa
                dayDiv.className = "h-10 md:h-14 rounded-lg bg-darkbg border border-gray-800 flex items-center justify-center text-xs md:text-sm font-semibold text-gray-300 hover:border-gray-500 hover:text-white cursor-pointer transition-colors relative";

                let dayOfWeek = new Date(currentYear, currentMonth, i).getDay();
                if (dayOfWeek === 0) {
                    dayDiv.classList.add("text-red-400"); // Hari Minggu
                }
            }

            if (hol) {
                const holidayTitle = hol.title ? escapeHtml(hol.title) : 'Libur';
                const holidayDesc = hol.description ? escapeHtml(hol.description) : '';
                dayDiv.title = `${hol.title ? hol.title + ' - ' : ''}${hol.description || ''}`;
                dayDiv.innerHTML = `
                    <span class="text-sm md:text-base font-bold leading-none">${i}</span>
                    <span class="mt-1 text-[9px] md:text-[10px] font-semibold leading-tight line-clamp-2 px-1">${holidayTitle}</span>
                    ${holidayDesc ? `<span class="mt-0.5 text-[8px] md:text-[9px] leading-tight opacity-90 line-clamp-2 px-1">${holidayDesc}</span>` : ''}
                `;
            } else {
                dayDiv.innerHTML = i;
            }
            calendarDays.appendChild(dayDiv);
        }
    }

    // FUNGSI GANTI BULAN
    function prevMonth() {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        renderCalendar();
    }

    function nextMonth() {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        renderCalendar();
    }

    // FUNGSI SAAT TAHUN DIPILIH MANUAL DARI DROPDOWN
    function changeYear() {
        currentYear = parseInt(yearSelect.value);
        renderCalendar();
    }

    // Eksekusi awal
    // Export holidays from PHP to JS
    const holidays = <?php echo json_encode($holidays); ?>;

    function isHoliday(y,m,d) {
        const key = y + '-' + String(m+1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
        return holidays[key] || null;
    }

    renderCalendar();
</script>

</body>
</html>