<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MY TASK - Sistem Pengumpulan Tugas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* VARIABLE WARNA DEFAULT (DARK MODE) */
        :root {
            --bg-darkbg: #0B1120;
            --bg-surface: #151E32;
            --border-color: #1f2937;
            --text-main: #ffffff;
            --text-muted: #9ca3af;
            --hover-bg: #1f2937;
        }

        /* VARIABLE WARNA JIKA MODE TERANG DIAKTIFKAN */
        html.light-mode {
            --bg-darkbg: #f3f4f6; /* Abu-abu sangat terang */
            --bg-surface: #ffffff; /* Putih bersih */
            --border-color: #e5e7eb;
            --text-main: #111827; /* Hitam gelap */
            --text-muted: #6b7280;
            --hover-bg: #f3f4f6;
        }

        /* OVERRIDE CLASS TAILWIND AGAR OTOMATIS BERUBAH WARNA */
        html.light-mode .text-white { color: var(--text-main) !important; }
        html.light-mode .text-gray-300, html.light-mode .text-gray-400, html.light-mode .text-gray-500 { color: var(--text-muted) !important; }
        html.light-mode .border-gray-800, html.light-mode .border-gray-700 { border-color: var(--border-color) !important; }
        html.light-mode .bg-gray-800 { background-color: var(--hover-bg) !important; }
        
        .sidebar-transition { transition: transform 0.3s ease-in-out; }
        .dropdown-menu { display: none; }
        .dropdown-menu.active { display: block; }

        /* Responsif global */
        * { box-sizing: border-box; }
        body { overflow-x: hidden; }
        img, video, canvas, svg { max-width: 100%; height: auto; }
        input, select, textarea, button { max-width: 100%; }
    </style>

    <script>
        // Set konfigurasi Tailwind untuk mengambil warna dari CSS Variable
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

        // Cek LocalStorage: Apakah user sebelumnya memilih Mode Terang?
        if(localStorage.getItem('theme') === 'light'){
            document.documentElement.classList.add('light-mode');
        }

        // Fungsi Tombol Switch Tema
        function toggleTheme() {
            if(document.documentElement.classList.contains('light-mode')) {
                document.documentElement.classList.remove('light-mode');
                localStorage.setItem('theme', 'dark'); // Simpan pilihan Gelap
            } else {
                document.documentElement.classList.add('light-mode');
                localStorage.setItem('theme', 'light'); // Simpan pilihan Terang
            }
        }
    </script>
</head>
<body class="bg-darkbg text-[var(--text-main)] min-h-screen font-sans transition-colors duration-300 selection:bg-redaccent selection:text-white">