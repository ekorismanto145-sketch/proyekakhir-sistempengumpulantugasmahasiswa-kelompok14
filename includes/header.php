<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'id', ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MY TASK - Sistem Pengumpulan Tugas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --bg-darkbg: #111827;
            --bg-surface: #182233;
            --bg-surface-2: #202c40;
            --border-color: #2a3648;
            --text-main: #f3f4f6;
            --text-heading: #f9fafb;
            --text-body: #d1d5db;
            --text-muted: #9ca3af;
            --input-bg: #162033;
            --input-border: #3c4a60;
            --hover-bg: #243045;
            --shadow-soft: 0 10px 30px rgba(15, 23, 42, 0.34);
        }

        html.light-mode {
            --bg-darkbg: #f4efe4;
            --bg-surface: #fffaf1;
            --bg-surface-2: #f8f3e8;
            --border-color: #ddd4c3;
            --text-main: #1f2937;
            --text-heading: #111827;
            --text-body: #334155;
            --text-muted: #64748b;
            --input-bg: #fffaf1;
            --input-border: #d2c6b3;
            --hover-bg: #ede3d2;
            --shadow-soft: 0 10px 24px rgba(61, 45, 32, 0.08);
        }

        body {
            background-color: var(--bg-darkbg);
            color: var(--text-main);
        }

        .bg-darkbg { background-color: var(--bg-darkbg) !important; }
        .bg-surface { background-color: var(--bg-surface) !important; }
        .bg-surface-2 { background-color: var(--bg-surface-2) !important; }
        .text-main { color: var(--text-main) !important; }
        .text-heading { color: var(--text-heading) !important; }
        .text-body { color: var(--text-body) !important; }
        .text-muted { color: var(--text-muted) !important; }
        .shadow-soft { box-shadow: var(--shadow-soft) !important; }

        html.light-mode .text-white { color: var(--text-heading) !important; }
        html.light-mode .text-gray-100 { color: var(--text-heading) !important; }
        html.light-mode .text-gray-50 { color: var(--text-heading) !important; }
        html.light-mode .text-gray-600 { color: #475569 !important; }
        html.light-mode .text-gray-200,
        html.light-mode .text-gray-300,
        html.light-mode .text-gray-400,
        html.light-mode .text-gray-500 { color: var(--text-muted) !important; }
        html.light-mode .text-gray-700 { color: #334155 !important; }
        html.light-mode .text-gray-800 { color: #1e293b !important; }
        html.light-mode .border-gray-800,
        html.light-mode .border-gray-700 { border-color: var(--border-color) !important; }
        html.light-mode .bg-gray-800,
        html.light-mode .bg-gray-900 { background-color: var(--hover-bg) !important; }
        html.light-mode .bg-gray-700 { background-color: #d6dee8 !important; }
        html.light-mode .bg-gray-600 { background-color: #b7c3d3 !important; }
        html.light-mode .bg-gray-500 { background-color: #94a3b8 !important; }
        html.light-mode .bg-darkbg { background-color: var(--bg-darkbg) !important; }
        html.light-mode .bg-surface { background-color: var(--bg-surface) !important; }
        html.light-mode .bg-surface-2 { background-color: var(--bg-surface-2) !important; }
        html.light-mode .bg-blue-900\/10 { background-color: rgba(37, 99, 235, 0.06) !important; }
        html.light-mode .bg-blue-900\/20 { background-color: rgba(37, 99, 235, 0.10) !important; }
        html.light-mode .bg-purple-900\/10 { background-color: rgba(124, 58, 237, 0.06) !important; }
        html.light-mode .bg-yellow-900\/10 { background-color: rgba(217, 119, 6, 0.07) !important; }
        html.light-mode .bg-green-900\/10 { background-color: rgba(22, 163, 74, 0.06) !important; }
        html.light-mode .bg-surface\/50 { background-color: rgba(255, 255, 255, 0.72) !important; }
        html.light-mode .bg-darkbg\/70 { background-color: rgba(248, 250, 252, 0.82) !important; }
        html.light-mode .bg-darkbg\/50 { background-color: rgba(255, 250, 241, 0.74) !important; }
        html.light-mode .bg-darkbg\/30 { background-color: rgba(255, 250, 241, 0.58) !important; }
        html.light-mode .bg-blue-500\/15 { background-color: rgba(37, 99, 235, 0.10) !important; }
        html.light-mode .bg-yellow-500\/15 { background-color: rgba(234, 179, 8, 0.12) !important; }
        html.light-mode .bg-red-500\/10 { background-color: rgba(239, 68, 68, 0.10) !important; }
        html.light-mode .bg-red-500\/20 { background-color: rgba(220, 38, 38, 0.10) !important; }
        html.light-mode .text-blue-400 { color: #2563eb !important; }
        html.light-mode .text-yellow-400 { color: #ca8a04 !important; }
        html.light-mode .text-red-500 { color: #dc2626 !important; }
        html.light-mode .text-purple-400 { color: #7c3aed !important; }
        html.light-mode .text-green-500 { color: #16a34a !important; }
        html.light-mode .text-blue-500 { color: #2563eb !important; }
        html.light-mode .text-yellow-500 { color: #d97706 !important; }
        html.light-mode .text-red-400 { color: #dc2626 !important; }
        html.light-mode .bg-blue-600 { background-color: #2563eb !important; }
        html.light-mode .bg-yellow-600 { background-color: #d97706 !important; }
        html.light-mode .bg-green-600 { background-color: #16a34a !important; }
        html.light-mode .bg-red-600 { background-color: #dc2626 !important; }
        html.light-mode .bg-purple-600 { background-color: #7c3aed !important; }
        html.light-mode .bg-red-500\/10 { background-color: rgba(220, 38, 38, 0.08) !important; }
        html.light-mode .bg-green-500\/5 { background-color: rgba(34, 197, 94, 0.06) !important; }
        html.light-mode .bg-yellow-500\/5 { background-color: rgba(234, 179, 8, 0.08) !important; }
        html.light-mode .bg-blue-500\/5 { background-color: rgba(59, 130, 246, 0.06) !important; }
        html.light-mode .border-blue-500\/30 { border-color: rgba(37, 99, 235, 0.22) !important; }
        html.light-mode .border-purple-500\/30 { border-color: rgba(124, 58, 237, 0.22) !important; }
        html.light-mode .border-yellow-500\/30 { border-color: rgba(217, 119, 6, 0.22) !important; }
        html.light-mode .border-red-500\/30 { border-color: rgba(220, 38, 38, 0.22) !important; }
        html.light-mode .border-green-500\/30 { border-color: rgba(22, 163, 74, 0.22) !important; }
        html.light-mode .border-blue-500\/50 { border-color: rgba(37, 99, 235, 0.36) !important; }
        html.light-mode .border-gray-600 { border-color: #b7c3d3 !important; }
        html.light-mode .border-gray-500 { border-color: #94a3b8 !important; }
        html.light-mode .bg-blue-500\/15 { background-color: rgba(37, 99, 235, 0.08) !important; }
        html.light-mode .bg-yellow-500\/15 { background-color: rgba(217, 119, 6, 0.10) !important; }
        html.light-mode .bg-red-500\/10 { background-color: rgba(220, 38, 38, 0.08) !important; }
        html.light-mode .bg-red-500\/20 { background-color: rgba(220, 38, 38, 0.10) !important; }
        html.light-mode .bg-green-500\/20 { background-color: rgba(22, 163, 74, 0.08) !important; }
        html.light-mode .bg-green-500\/10 { background-color: rgba(22, 163, 74, 0.06) !important; }
        html.light-mode .bg-red-500\/20 { background-color: rgba(220, 38, 38, 0.10) !important; }
        html.light-mode .bg-blue-600\/50 { background-color: rgba(37, 99, 235, 0.44) !important; }
        html.light-mode .bg-orange-500\/20 { background-color: rgba(249, 115, 22, 0.10) !important; }
        html.light-mode .border-orange-500 { border-color: rgba(249, 115, 22, 0.40) !important; }
        html.light-mode .text-orange-300 { color: #ea580c !important; }
        html.light-mode .text-orange-400 { color: #c2410c !important; }
        html.light-mode .bg-gradient-to-r.from-gray-800.to-gray-900 {
            background-image: linear-gradient(90deg, #eef2f7, #dde4ee) !important;
        }
        html.light-mode .from-gray-800,
        html.light-mode .to-gray-900 {
            --tw-gradient-from: #eef2f7 !important;
            --tw-gradient-to: #dde4ee !important;
            --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to) !important;
        }
        html.light-mode .text-green-400 { color: #16a34a !important; }
        html.light-mode .text-gray-900 { color: #020617 !important; }
        html.light-mode input,
        html.light-mode select,
        html.light-mode textarea {
            background-color: var(--input-bg) !important;
            border-color: var(--input-border) !important;
            color: var(--text-heading) !important;
        }
        html.light-mode input::placeholder,
        html.light-mode textarea::placeholder {
            color: #94a3b8 !important;
        }
        html.light-mode button:hover {
            filter: brightness(0.985);
        }
        html.light-mode a {
            color: inherit;
        }
        html.light-mode .bg-surface\/80 {
            background-color: rgba(255, 255, 255, 0.88) !important;
        }
        html.light-mode .shadow-md,
        html.light-mode .shadow-xl,
        html.light-mode .shadow-2xl,
        html.light-mode .shadow-lg {
            box-shadow: var(--shadow-soft) !important;
        }
        
        .sidebar-transition { transition: transform 0.3s ease-in-out; }
        .dropdown-menu { display: none; }
        .dropdown-menu.active { display: block; }

        * { box-sizing: border-box; }
        body { overflow-x: hidden; }
        img, video, canvas, svg { max-width: 100%; height: auto; }
        input, select, textarea, button { max-width: 100%; }
    </style>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        darkbg: 'var(--bg-darkbg)',
                        surface: 'var(--bg-surface)',
                        redaccent: '#E53E3E',
                    }
                }
            }
        }

        if(localStorage.getItem('theme') === 'light' || localStorage.getItem('login_theme') === 'light'){
            document.documentElement.classList.add('light-mode');
        }

        function toggleTheme() {
            if(document.documentElement.classList.contains('light-mode')) {
                document.documentElement.classList.remove('light-mode');
                localStorage.setItem('theme', 'dark');
                localStorage.setItem('login_theme', 'dark');
            } else {
                document.documentElement.classList.add('light-mode');
                localStorage.setItem('theme', 'light');
                localStorage.setItem('login_theme', 'light');
            }
            window.dispatchEvent(new Event('storage'));
        }
    </script>
</head>
<body class="bg-darkbg text-[var(--text-main)] min-h-screen font-sans transition-colors duration-300 selection:bg-redaccent selection:text-white">
