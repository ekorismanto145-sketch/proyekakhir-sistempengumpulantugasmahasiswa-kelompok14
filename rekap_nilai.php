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

            <div class="table-tools no-print flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-3 border-b border-gray-800 bg-darkbg/40">
                <div>
                    <p id="recapResultCount" class="text-xs font-bold text-gray-300"></p>
                    <p class="text-[11px] text-gray-500 mt-0.5"><?= t('print_filtered_hint') ?></p>
                </div>
                <button type="button" id="resetRecapFilters" class="px-3 py-2 rounded-lg border border-gray-700 hover:border-blue-500 text-xs font-bold text-gray-300 hover:text-white transition">
                    <i class="fas fa-rotate-left mr-1.5"></i><?= t('reset_filters') ?>
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="recap-table w-full text-left border-collapse min-w-[920px]">
                    <thead>
                        <tr class="text-xs uppercase tracking-wider text-gray-400 border-b border-gray-700 bg-darkbg/60">
                            <?php
                            $recap_columns = [
                                ['label' => t('student_name'), 'type' => 'text'],
                                ['label' => t('task_name'), 'type' => 'text'],
                                ['label' => t('score'), 'type' => 'number'],
                                ['label' => t('letter_grade'), 'type' => 'grade'],
                                ['label' => t('average_grade'), 'type' => 'number'],
                                ['label' => t('recap_notes'), 'type' => 'text']
                            ];
                            foreach ($recap_columns as $index => $column): ?>
                                <th class="py-3 px-4 <?= in_array($index, [2, 3, 4], true) ? 'text-center' : '' ?>">
                                    <button type="button" class="recap-sort inline-flex items-center gap-1.5 hover:text-white transition" data-column="<?= $index ?>" data-type="<?= $column['type'] ?>" title="<?= t('sort_column') ?>">
                                        <span><?= htmlspecialchars($column['label']) ?></span>
                                        <i class="sort-icon fas fa-sort text-gray-600"></i>
                                    </button>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                        <tr class="table-filter-row no-print border-b border-gray-700 bg-darkbg/80">
                            <th class="p-2"><input type="search" data-filter="student" placeholder="<?= t('filter_student') ?>" class="recap-filter w-full rounded-lg border border-gray-700 bg-surface px-2.5 py-2 text-xs text-white placeholder:text-gray-600"></th>
                            <th class="p-2"><input type="search" data-filter="task" placeholder="<?= t('filter_task') ?>" class="recap-filter w-full rounded-lg border border-gray-700 bg-surface px-2.5 py-2 text-xs text-white placeholder:text-gray-600"></th>
                            <th class="p-2"><div class="flex gap-1"><input type="number" min="0" max="100" data-filter="scoreMin" placeholder="Min" class="recap-filter w-1/2 rounded-lg border border-gray-700 bg-surface px-2 py-2 text-xs text-white"><input type="number" min="0" max="100" data-filter="scoreMax" placeholder="Max" class="recap-filter w-1/2 rounded-lg border border-gray-700 bg-surface px-2 py-2 text-xs text-white"></div></th>
                            <th class="p-2"><select data-filter="grade" class="recap-filter w-full rounded-lg border border-gray-700 bg-surface px-2 py-2 text-xs text-white"><option value=""><?= t('all_grades') ?></option><?php foreach (['A', 'B+', 'B', 'C+', 'C', 'C-', 'E'] as $grade): ?><option value="<?= $grade ?>"><?= $grade ?></option><?php endforeach; ?></select></th>
                            <th class="p-2"><div class="flex gap-1"><input type="number" min="0" max="100" data-filter="averageMin" placeholder="Min" class="recap-filter w-1/2 rounded-lg border border-gray-700 bg-surface px-2 py-2 text-xs text-white"><input type="number" min="0" max="100" data-filter="averageMax" placeholder="Max" class="recap-filter w-1/2 rounded-lg border border-gray-700 bg-surface px-2 py-2 text-xs text-white"></div></th>
                            <th class="p-2"><input type="search" data-filter="notes" placeholder="<?= t('filter_notes') ?>" class="recap-filter w-full rounded-lg border border-gray-700 bg-surface px-2.5 py-2 text-xs text-white placeholder:text-gray-600"></th>
                        </tr>
                    </thead>
                    <tbody id="recapTableBody">
                        <?php if (!$recap_rows): ?>
                            <tr class="empty-recap-row"><td colspan="6" class="py-10 text-center text-gray-500"><?= t('no_graded_records') ?></td></tr>
                        <?php else: foreach ($recap_rows as $row_index => $row): ?>
                            <tr class="recap-row border-b border-gray-800 last:border-b-0" data-index="<?= $row_index ?>" data-student="<?= htmlspecialchars(mb_strtolower($row['student_name']), ENT_QUOTES) ?>" data-task="<?= htmlspecialchars(mb_strtolower($row['task_name']), ENT_QUOTES) ?>" data-score="<?= $row['score'] !== null ? (float)$row['score'] : '' ?>" data-grade="<?= htmlspecialchars($row['grade'], ENT_QUOTES) ?>" data-average="<?= $row['average'] !== null ? (float)$row['average'] : '' ?>" data-notes="<?= htmlspecialchars(mb_strtolower($row['notes']), ENT_QUOTES) ?>">
                                <td class="py-3 px-4 text-sm font-semibold text-white"><?= htmlspecialchars($row['student_name']) ?></td>
                                <td class="py-3 px-4 text-sm text-gray-300"><?= htmlspecialchars($row['task_name']) ?></td>
                                <td class="py-3 px-4 text-sm text-center font-bold text-blue-400"><?= $row['score'] !== null ? htmlspecialchars($row['score']) : '-' ?></td>
                                <td class="py-3 px-4 text-center"><span class="grade-chip inline-flex min-w-8 justify-center rounded-lg bg-blue-500/10 border border-blue-500/30 px-2 py-1 text-sm font-extrabold text-blue-300"><?= htmlspecialchars($row['grade']) ?></span></td>
                                <td class="py-3 px-4 text-sm text-center font-bold text-white"><?= $row['average'] !== null ? htmlspecialchars($row['average']) : '-' ?></td>
                                <td class="py-3 px-4 text-sm text-gray-400 max-w-[260px] whitespace-normal"><?= $row['notes'] !== '' ? nl2br(htmlspecialchars($row['notes'])) : '-' ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        <tr id="filteredEmptyRow" hidden><td colspan="6" class="py-10 text-center text-gray-500"><?= t('no_filter_results') ?></td></tr>
                    </tbody>
                </table>
            </div>
        </section>
        <p class="text-xs text-gray-500 mt-3 no-print"><?= t('use_print_pdf') ?></p>
    <?php endif; ?>
</main>

<style>
.recap-sort {
    background: transparent;
    border: 0;
    color: inherit;
}
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
    .sort-icon {
        display: none !important;
    }
}
</style>
<?php if ($class_info): ?>
<script>
    document.title = <?= json_encode(t('rekap_nilai') . ' - ' . $class_info['nama_kelas'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

    const recapBody = document.getElementById('recapTableBody');
    const recapRows = Array.from(recapBody.querySelectorAll('.recap-row'));
    const recapFilters = Array.from(document.querySelectorAll('.recap-filter'));
    const sortButtons = Array.from(document.querySelectorAll('.recap-sort'));
    const filteredEmptyRow = document.getElementById('filteredEmptyRow');
    const resultCount = document.getElementById('recapResultCount');
    const gradeRank = { E: 0, 'C-': 1, C: 2, 'C+': 3, B: 4, 'B+': 5, A: 6 };
    let sortState = null;

    function filterValue(name) {
        return document.querySelector(`[data-filter="${name}"]`)?.value.trim() || '';
    }

    function numericMatch(value, min, max) {
        if (value === '') return min === '' && max === '';
        const number = Number(value);
        return (min === '' || number >= Number(min)) && (max === '' || number <= Number(max));
    }

    function updateResultCount(shown) {
        resultCount.textContent = <?= json_encode(t('showing_rows'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
            .replace('{shown}', shown)
            .replace('{total}', recapRows.length);
    }

    function applyRecapFilters() {
        const student = filterValue('student').toLocaleLowerCase();
        const task = filterValue('task').toLocaleLowerCase();
        const notes = filterValue('notes').toLocaleLowerCase();
        const grade = filterValue('grade');
        let shown = 0;

        recapRows.forEach(row => {
            const visible = row.dataset.student.includes(student)
                && row.dataset.task.includes(task)
                && row.dataset.notes.includes(notes)
                && (!grade || row.dataset.grade === grade)
                && numericMatch(row.dataset.score, filterValue('scoreMin'), filterValue('scoreMax'))
                && numericMatch(row.dataset.average, filterValue('averageMin'), filterValue('averageMax'));
            row.hidden = !visible;
            if (visible) shown++;
        });

        if (recapRows.length) filteredEmptyRow.hidden = shown !== 0;
        updateResultCount(shown);
    }

    function sortableValue(row, column, type) {
        const keys = ['student', 'task', 'score', 'grade', 'average', 'notes'];
        const value = row.dataset[keys[column]] ?? '';
        if (type === 'number') return value === '' ? null : Number(value);
        if (type === 'grade') return gradeRank[value] ?? -1;
        return value;
    }

    function sortRecap(column, type, direction) {
        const factor = direction === 'asc' ? 1 : -1;
        const sorted = [...recapRows].sort((a, b) => {
            const first = sortableValue(a, column, type);
            const second = sortableValue(b, column, type);
            if (first === null || first === '') return second === null || second === '' ? Number(a.dataset.index) - Number(b.dataset.index) : 1;
            if (second === null || second === '') return -1;
            if (type === 'text') {
                const compared = first.localeCompare(second, '<?= ($_SESSION['lang'] ?? 'id') === 'en' ? 'en' : 'id' ?>', { sensitivity: 'base', numeric: true });
                return compared !== 0 ? compared * factor : Number(a.dataset.index) - Number(b.dataset.index);
            }
            return first === second ? Number(a.dataset.index) - Number(b.dataset.index) : (first - second) * factor;
        });
        sorted.forEach(row => recapBody.insertBefore(row, filteredEmptyRow));
    }

    recapFilters.forEach(control => {
        control.addEventListener(control.tagName === 'SELECT' ? 'change' : 'input', applyRecapFilters);
    });

    sortButtons.forEach(button => {
        button.addEventListener('click', () => {
            const column = Number(button.dataset.column);
            const direction = sortState?.column === column && sortState.direction === 'asc' ? 'desc' : 'asc';
            sortState = { column, direction };
            sortRecap(column, button.dataset.type, direction);
            sortButtons.forEach(item => {
                const icon = item.querySelector('.sort-icon');
                const active = Number(item.dataset.column) === column;
                icon.className = `sort-icon fas ${active ? (direction === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'} ${active ? 'text-blue-400' : 'text-gray-600'}`;
                item.setAttribute('aria-sort', active ? (direction === 'asc' ? 'ascending' : 'descending') : 'none');
            });
        });
    });

    document.getElementById('resetRecapFilters').addEventListener('click', () => {
        recapFilters.forEach(control => { control.value = ''; });
        sortState = null;
        [...recapRows].sort((a, b) => Number(a.dataset.index) - Number(b.dataset.index)).forEach(row => recapBody.insertBefore(row, filteredEmptyRow));
        sortButtons.forEach(button => {
            button.querySelector('.sort-icon').className = 'sort-icon fas fa-sort text-gray-600';
            button.setAttribute('aria-sort', 'none');
        });
        applyRecapFilters();
    });

    updateResultCount(recapRows.length);
</script>
<?php endif; ?>
</body>
</html>
