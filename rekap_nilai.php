<?php
include 'includes/db.php';
include 'includes/lang.php';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit(); }

$user = $_SESSION['user'];
$role = $user['role'] ?? '';
if ($role !== 'dosen' && $role !== 'admin') { die(t('access_denied')); }

function letterGrade($score) {
    if ($score === null || $score === '') return '-';
    $score = (float)$score;
    if ($score >= 80) return 'A';
    if ($score >= 75) return 'B+';
    if ($score >= 70) return 'B';
    if ($score >= 65) return 'C+';
    if ($score >= 60) return 'C';
    if ($score >= 55) return 'C-';
    return 'E';
}

$dosen_id = (int)$user['id'];
if ($role === 'admin') {
    $stmt = $conn->prepare("SELECT c.id, c.nama_kelas, c.deskripsi, u.nama AS nama_dosen FROM classes c JOIN users u ON c.dosen_id = u.id ORDER BY c.created_at DESC");
} else {
    $stmt = $conn->prepare("SELECT c.id, c.nama_kelas, c.deskripsi, u.nama AS nama_dosen FROM classes c JOIN users u ON c.dosen_id = u.id WHERE c.dosen_id = ? ORDER BY c.created_at DESC");
    $stmt->bind_param('i', $dosen_id);
}
$stmt->execute();
$kelas_list = $stmt->get_result();
$stmt->close();

$selected_class = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$recap_rows = [];
$class_info = null;
$generated_at = new DateTime('now', new DateTimeZone('Asia/Jakarta'));

if ($selected_class > 0) {
    if ($role === 'admin') {
        $class_stmt = $conn->prepare("SELECT c.nama_kelas, c.deskripsi, u.nama AS nama_dosen FROM classes c JOIN users u ON c.dosen_id = u.id WHERE c.id = ?");
        $class_stmt->bind_param('i', $selected_class);
    } else {
        $class_stmt = $conn->prepare("SELECT c.nama_kelas, c.deskripsi, u.nama AS nama_dosen FROM classes c JOIN users u ON c.dosen_id = u.id WHERE c.id = ? AND c.dosen_id = ?");
        $class_stmt->bind_param('ii', $selected_class, $dosen_id);
    }
    $class_stmt->execute();
    $class_info = $class_stmt->get_result()->fetch_assoc();
    $class_stmt->close();

    if ($class_info) {
        $student_stmt = $conn->prepare("SELECT u.id, u.nama FROM class_members cm JOIN users u ON cm.mahasiswa_id = u.id WHERE cm.class_id = ? ORDER BY u.nama ASC");
        $student_stmt->bind_param('i', $selected_class);
        $student_stmt->execute();
        $students = [];
        foreach ($student_stmt->get_result() as $student) {
            $student['scores'] = [];
            $student['average'] = null;
            $students[(int)$student['id']] = $student;
        }
        $student_stmt->close();

        $score_stmt = $conn->prepare(
            "SELECT ts.mahasiswa_id, t.judul AS nama_tugas, ts.nilai, ts.feedback
             FROM task_submissions ts
             JOIN tasks t ON ts.task_id = t.id
             WHERE t.class_id = ? AND ts.nilai IS NOT NULL
             ORDER BY t.created_at ASC, t.id ASC"
        );
        $score_stmt->bind_param('i', $selected_class);
        $score_stmt->execute();
        foreach ($score_stmt->get_result() as $score) {
            $student_id = (int)$score['mahasiswa_id'];
            if (isset($students[$student_id])) {
                $students[$student_id]['scores'][] = $score;
            }
        }
        $score_stmt->close();

        foreach ($students as $student) {
            if ($student['scores']) {
                $total = array_sum(array_map(function ($score) { return (float)$score['nilai']; }, $student['scores']));
                $average = round($total / count($student['scores']), 2);
                foreach ($student['scores'] as $score) {
                    $recap_rows[] = [
                        'student_name' => $student['nama'],
                        'task_name' => $score['nama_tugas'],
                        'score' => $score['nilai'],
                        'grade' => letterGrade($score['nilai']),
                        'average' => $average,
                        'notes' => trim((string)($score['feedback'] ?? ''))
                    ];
                }
            } else {
                $recap_rows[] = [
                    'student_name' => $student['nama'],
                    'task_name' => '-',
                    'score' => null,
                    'grade' => '-',
                    'average' => null,
                    'notes' => ''
                ];
            }
        }
    }
}

include 'includes/header.php';
include 'includes/navbar.php';
?>
<main class="max-w-6xl mx-auto p-4 sm:p-6 md:p-10 recap-page">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-3 no-print">
        <div>
            <p class="text-xs uppercase tracking-[0.18em] text-blue-400 font-bold mb-1">MY ACADEMIC</p>
            <h2 class="text-xl sm:text-2xl font-bold text-white"><?= t('rekap_nilai') ?></h2>
        </div>
        <?php if ($selected_class && $class_info): ?>
            <button onclick="window.print()" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-lg w-full sm:w-auto transition">
                <i class="fas fa-file-pdf mr-2"></i><?= t('print_pdf') ?>
            </button>
        <?php endif; ?>
    </div>

    <form method="GET" class="mb-6 no-print">
        <label class="text-sm text-gray-400"><?= t('choose_class') ?></label>
        <div class="flex flex-col sm:flex-row gap-3 mt-2">
            <select name="class_id" class="bg-darkbg border border-gray-700 text-white px-3 py-2.5 rounded-lg w-full sm:min-w-[280px]">
                <option value="">-- <?= t('choose_class') ?> --</option>
                <?php while ($class = $kelas_list->fetch_assoc()): ?>
                    <option value="<?= (int)$class['id'] ?>" <?= $selected_class === (int)$class['id'] ? 'selected' : '' ?>><?= htmlspecialchars($class['nama_kelas']) ?></option>
                <?php endwhile; ?>
            </select>
            <button class="px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-lg w-full sm:w-auto transition"><?= t('show') ?></button>
        </div>
    </form>

    <?php if ($selected_class && $class_info): ?>
        <section class="recap-document bg-surface border border-gray-800 rounded-2xl shadow-xl overflow-hidden">
            <header class="recap-header p-5 sm:p-7 border-b border-gray-800">
                <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3 mb-5">
                    <div>
                        <p class="document-kicker text-xs uppercase tracking-[0.2em] text-blue-400 font-bold mb-1">MY ACADEMIC</p>
                        <h1 class="document-title text-2xl font-extrabold text-white"><?= t('rekap_nilai') ?></h1>
                    </div>
                    <p class="document-period text-xs text-gray-500"><?= htmlspecialchars($generated_at->format('d/m/Y H:i')) ?> WIB</p>
                </div>
                <div class="metadata-grid grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="metadata-item rounded-xl border border-gray-700 bg-darkbg px-4 py-3">
                        <p class="metadata-label text-[10px] uppercase tracking-wider text-gray-500 mb-1"><?= t('lecturer_name') ?></p>
                        <p class="metadata-value text-sm font-bold text-white"><?= htmlspecialchars($class_info['nama_dosen']) ?></p>
                    </div>
                    <div class="metadata-item rounded-xl border border-gray-700 bg-darkbg px-4 py-3">
                        <p class="metadata-label text-[10px] uppercase tracking-wider text-gray-500 mb-1"><?= t('course_name') ?></p>
                        <p class="metadata-value text-sm font-bold text-white"><?= htmlspecialchars($class_info['nama_kelas']) ?></p>
                    </div>
                    <div class="metadata-item rounded-xl border border-gray-700 bg-darkbg px-4 py-3">
                        <p class="metadata-label text-[10px] uppercase tracking-wider text-gray-500 mb-1"><?= t('generated_at') ?></p>
                        <p class="metadata-value text-sm font-bold text-white"><?= htmlspecialchars($generated_at->format('d M Y, H:i')) ?> WIB</p>
                    </div>
                </div>
            </header>

            <div class="overflow-x-auto">
                <table class="recap-table w-full text-left border-collapse min-w-[920px]">
                    <thead>
                        <tr class="text-xs uppercase tracking-wider text-gray-400 border-b border-gray-700 bg-darkbg/60">
                            <th class="py-3 px-4"><?= t('student_name') ?></th>
                            <th class="py-3 px-4"><?= t('task_name') ?></th>
                            <th class="py-3 px-4 text-center"><?= t('score') ?></th>
                            <th class="py-3 px-4 text-center"><?= t('letter_grade') ?></th>
                            <th class="py-3 px-4 text-center"><?= t('average_grade') ?></th>
                            <th class="py-3 px-4"><?= t('recap_notes') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$recap_rows): ?>
                            <tr><td colspan="6" class="py-10 text-center text-gray-500"><?= t('no_graded_records') ?></td></tr>
                        <?php else: foreach ($recap_rows as $row): ?>
                            <tr class="border-b border-gray-800 last:border-b-0">
                                <td class="py-3 px-4 text-sm font-semibold text-white"><?= htmlspecialchars($row['student_name']) ?></td>
                                <td class="py-3 px-4 text-sm text-gray-300"><?= htmlspecialchars($row['task_name']) ?></td>
                                <td class="py-3 px-4 text-sm text-center font-bold text-blue-400"><?= $row['score'] !== null ? htmlspecialchars($row['score']) : '-' ?></td>
                                <td class="py-3 px-4 text-center"><span class="grade-chip inline-flex min-w-8 justify-center rounded-lg bg-blue-500/10 border border-blue-500/30 px-2 py-1 text-sm font-extrabold text-blue-300"><?= htmlspecialchars($row['grade']) ?></span></td>
                                <td class="py-3 px-4 text-sm text-center font-bold text-white"><?= $row['average'] !== null ? htmlspecialchars($row['average']) : '-' ?></td>
                                <td class="py-3 px-4 text-sm text-gray-400 max-w-[260px] whitespace-normal"><?= $row['notes'] !== '' ? nl2br(htmlspecialchars($row['notes'])) : '-' ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <p class="text-xs text-gray-500 mt-3 no-print"><?= t('use_print_pdf') ?></p>
    <?php endif; ?>
</main>

<style>
@page {
    size: A4 landscape;
    margin: 14mm;
}
@media print {
    nav, .no-print, aside {
        display: none !important;
    }
    html, body {
        background: #fff !important;
        color: #172033 !important;
        font-family: "Segoe UI", Arial, sans-serif !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .recap-page {
        max-width: none !important;
        padding: 0 !important;
    }
    .recap-document {
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        background: #fff !important;
    }
    .recap-header {
        padding: 0 0 7mm !important;
        border-bottom: 2px solid #1d4ed8 !important;
    }
    .document-kicker, .metadata-label {
        color: #1d4ed8 !important;
    }
    .document-title, .metadata-value {
        color: #172033 !important;
    }
    .document-period {
        color: #526079 !important;
    }
    .metadata-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    }
    .metadata-item {
        background: #f4f7fb !important;
        border: 1px solid #d4dbe7 !important;
        break-inside: avoid;
    }
    .recap-table {
        min-width: 100% !important;
        table-layout: fixed;
        color: #172033 !important;
    }
    .recap-table thead {
        display: table-header-group;
    }
    .recap-table thead tr {
        background: #eaf0fb !important;
        color: #24334f !important;
        border-color: #aebbd0 !important;
    }
    .recap-table tr {
        break-inside: avoid;
        border-color: #d4dbe7 !important;
    }
    .recap-table th, .recap-table td {
        padding: 7px 9px !important;
        color: #172033 !important;
        font-size: 9pt !important;
        vertical-align: top;
    }
    .recap-table th:nth-child(1) { width: 19%; }
    .recap-table th:nth-child(2) { width: 19%; }
    .recap-table th:nth-child(3) { width: 8%; }
    .recap-table th:nth-child(4) { width: 8%; }
    .recap-table th:nth-child(5) { width: 11%; }
    .recap-table th:nth-child(6) { width: 35%; }
    .grade-chip {
        background: #e6efff !important;
        border-color: #9db8ed !important;
        color: #173e87 !important;
    }
}
</style>
<?php if ($class_info): ?>
<script>
    document.title = <?= json_encode(t('rekap_nilai') . ' - ' . $class_info['nama_kelas'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>
<?php endif; ?>
</body>
</html>
