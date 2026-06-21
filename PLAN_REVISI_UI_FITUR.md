# Plan Revisi UI dan Fitur

## Ringkasan
Dokumen ini berisi rencana perbaikan untuk aplikasi MY ACADEMIC agar lebih jelas, lebih nyaman dipakai, dan lebih sesuai dengan peran pengguna. Fokusnya ada pada empat area:
- rekap nilai PDF yang lebih informatif,
- pembatasan hak akses mahasiswa dan dosen,
- fitur materi selain tugas,
- dan penyempurnaan theme light/dark agar lebih nyaman di mata.

## Status Plan
- [x] Plan rekap nilai PDF yang lebih informatif
- [ ] Implementasi header PDF rekap nilai
- [x] Plan pembatasan hak akses mahasiswa dan dosen
- [ ] Implementasi pembatasan hak akses mahasiswa/dosen
- [x] Plan fitur materi terpisah dari tugas
- [ ] Revisi database untuk tabel `materials`
- [x] Plan preview file tanpa download
- [ ] Implementasi preview file
- [x] Plan penyempurnaan light/dark mode
- [ ] Penyempurnaan light/dark mode di semua halaman utama

## 1. Rekap Nilai PDF
### Tujuan
Membuat hasil cetak PDF lebih jelas, supaya dosen langsung tahu rekap itu untuk mata kuliah apa dan siapa pengampunya.

### Perubahan yang direncanakan
- Menambahkan header di halaman rekap nilai dan output print/PDF.
- Header menampilkan:
  - nama mata kuliah / nama kelas,
  - deskripsi singkat atau kode kelas,
  - nama dosen pengampu,
  - tanggal cetak.
- Menjaga tampilan tabel nilai tetap rapi saat dicetak.
- Tetap mempertahankan tombol print browser, atau kalau perlu menambahkan label yang lebih jelas bahwa ini akan disimpan sebagai PDF.

### Hasil yang diharapkan
- PDF tidak lagi terlihat seperti tabel generik.
- Dosen bisa langsung mengenali mata kuliah yang direkap.
- Dokumen lebih cocok untuk laporan/arsip.

### Status
- [x] Tabel nilai per mata kuliah dan rata-rata sudah dirancang
- [ ] Header PDF
- [ ] Nama mata kuliah / kelas di header
- [ ] Nama dosen pengampu di header
- [ ] Tanggal cetak di header

## 2. Hak Akses Per Role
### Tujuan
Memisahkan hak akses antara mahasiswa dan dosen agar alur aplikasi lebih sesuai dengan kebutuhan kampus.

### Perubahan yang direncanakan
- **Mahasiswa**
  - tidak bisa membuat kelas,
  - hanya bisa bergabung ke kelas,
  - tetap bisa melihat tugas dan materi,
  - tetap bisa mengumpulkan tugas.
- **Dosen**
  - bisa membuat kelas,
  - bisa mengirim tugas,
  - juga bisa mengirim materi,
  - bisa melihat dan mengelola pengumpulan.

### Hasil yang diharapkan
- UI dan aksi sesuai peran pengguna.
- Tidak ada fitur yang nyasar ke role yang salah.
- Aplikasi terasa lebih aman dan lebih realistis untuk workflow kampus.

### Status
- [ ] Mahasiswa hanya bisa gabung kelas
- [ ] Mahasiswa tidak bisa membuat kelas
- [ ] Dosen bisa membuat kelas
- [ ] Dosen bisa kirim tugas
- [ ] Dosen bisa kirim materi

## 3. Fitur Materi
### Tujuan
Menambahkan jenis konten baru selain tugas, supaya dosen bisa membagikan materi pembelajaran.

### Perubahan yang direncanakan
- Menambah entitas atau kategori konten untuk **materi**.
- Dosen bisa mengunggah materi dengan judul, deskripsi, dan file.
- Mahasiswa dan dosen bisa melihat daftar materi tanpa harus download file terlebih dahulu.
- File tetap bisa diunduh jika memang diperlukan.

### Hasil yang diharapkan
- Kelas tidak hanya berisi tugas, tetapi juga materi pembelajaran.
- Mahasiswa bisa preview informasi file terlebih dahulu.
- Pengalaman penggunaan lebih natural untuk kebutuhan perkuliahan.

### Status
- [x] Plan materi terpisah dari tugas
- [x] Rancangan SQL awal untuk `materials`
- [ ] Implementasi tabel `materials`
- [ ] Implementasi UI upload materi
- [ ] Implementasi daftar materi pada dashboard/detail kelas

## 4. Preview Tanpa Download
### Tujuan
Membuat pengguna bisa melihat isi atau metadata file tanpa harus langsung mengunduhnya.

### Perubahan yang direncanakan
- Menampilkan daftar file materi atau tugas dengan:
  - nama file,
  - tipe file,
  - ukuran file jika tersedia,
  - tanggal upload.
- Menampilkan tombol:
  - lihat detail,
  - unduh file,
  - atau buka preview jika format mendukung.
- Untuk PDF, bisa dipertimbangkan preview langsung di browser.
- Untuk Word, cukup tampilkan detail dan opsi unduh.

### Hasil yang diharapkan
- Pengguna tidak perlu download dulu untuk tahu isi file.
- UI lebih efisien dan modern.

### Status
- [x] Strategi preview PDF, Word, dan file lain sudah direncanakan
- [ ] Implementasi metadata file di UI
- [ ] Implementasi preview PDF
- [ ] Implementasi preview atau unduh Word

## 5. Penyempurnaan Light Mode
### Tujuan
Membuat tema terang tidak silau, tidak pucat, dan tetap nyaman dipakai lama.

### Perubahan yang direncanakan
- Menyesuaikan ulang token warna light mode.
- Memperjelas hierarki visual:
  - background utama,
  - surface atau card,
  - heading,
  - body text,
  - muted text,
  - border,
  - input,
  - badge atau label.
- Mengurangi kesan putih penuh yang bikin mata cepat lelah.
- Memastikan transisi dark dan light tetap konsisten di:
  - login,
  - register,
  - dashboard,
  - settings,
  - rekap nilai.

### Arah visual
- Light mode diarahkan ke warna:
  - putih kebiruan lembut,
  - abu terang,
  - teks gelap yang tetap halus.
- Bukan putih tajam penuh yang terlalu kontras.

### Hasil yang diharapkan
- Tidak bikin sakit mata.
- Tetap enak dipakai lama.
- Dark mode dan light mode sama-sama terasa rapi.

### Status
- [x] Token warna light mode sudah diperbaiki di header
- [x] Toggle theme di header sudah ditambahkan
- [x] Login page light mode sudah diselaraskan
- [x] Register page light mode sudah diselaraskan
- [ ] Poles light mode halaman dashboard
- [ ] Poles light mode halaman settings
- [ ] Poles light mode halaman rekap nilai

## 6. Prioritas Implementasi
Urutan yang disarankan:
1. [x] Perbaiki light mode global dulu.
2. [ ] Tambahkan header PDF untuk rekap nilai.
3. [ ] Batasi hak akses mahasiswa dan dosen.
4. [ ] Tambahkan fitur materi.
5. [ ] Tambahkan preview file tanpa download.

## 7. Catatan Teknis
- Perubahan role akses akan butuh pengecekan di file dashboard dan halaman form.
- Fitur materi kemungkinan butuh penyesuaian database dan UI.
- Preview file perlu mempertimbangkan format PDF, DOC, dan DOCX.
- Light mode sebaiknya dipoles dulu di level global sebelum detail halaman satu per satu.

## 8. Pertanyaan untuk Diskusi
Sebelum implementasi, perlu diputuskan:
- Apakah materi akan disimpan di tabel baru atau digabung dengan tasks?
- Apakah preview file cukup metadata saja, atau ingin viewer PDF langsung?
- Apakah mahasiswa masih boleh melihat materi dari kelas yang diikuti saja?
- Apakah rekap nilai PDF cukup print browser, atau perlu generator PDF khusus?

## 9. Rencana Database untuk Fitur Materi
### Tujuan
Menambah struktur data yang rapi untuk menyimpan materi pembelajaran secara terpisah dari tugas, supaya sistem lebih mudah dirawat dan dikembangkan.

### Opsi yang Direkomendasikan
Membuat tabel baru bernama `materials` agar materi tidak tercampur dengan `tasks`.

### Struktur Tabel yang Disarankan
- `id` sebagai primary key
- `class_id` untuk relasi ke kelas
- `judul` untuk nama materi
- `deskripsi` untuk keterangan singkat
- `file_path` untuk lokasi file
- `original_name` untuk nama file asli
- `uploaded_by` untuk menyimpan user dosen atau admin yang mengunggah
- `mime_type` untuk mempermudah validasi dan preview
- `file_size` untuk membantu info file
- `created_at` untuk timestamp upload

### Relasi yang Disarankan
- `class_id` mengarah ke tabel `classes`
- `uploaded_by` mengarah ke tabel `users`

### Kenapa Dipisah dari Tugas
- tugas dan materi punya tujuan berbeda
- query jadi lebih bersih
- hak akses lebih mudah diatur
- tampilan UI lebih jelas

### Dampak ke Sistem
- perlu menambah migrasi database
- perlu menambah halaman atau section materi
- perlu menyesuaikan navbar, dashboard, dan detail kelas
- upload file materi bisa memakai aturan file yang mirip tugas

### Hasil yang Diharapkan
- dosen bisa unggah materi tanpa mengganggu alur tugas
- mahasiswa bisa melihat materi dengan lebih jelas
- database tetap rapi dan scalable

### Rancangan SQL Awal
```sql
CREATE TABLE materials (
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
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);
```

### Catatan Implementasi
- `mime_type` dipakai untuk membedakan PDF, DOC, DOCX, atau file lain.
- `file_size` membantu menampilkan informasi file sebelum diunduh.
- `uploaded_by` memudahkan audit siapa yang mengunggah materi.
- Jika nanti materi diberi kategori tambahan, bisa ditambah kolom `kategori` atau `tipe`.

### Alur Data Materi
1. Dosen memilih kelas.
2. Dosen mengisi judul, deskripsi, dan file materi.
3. File disimpan ke folder upload server.
4. Metadata file disimpan ke tabel `materials`.
5. Mahasiswa melihat daftar materi dari kelas yang diikuti.
6. Mahasiswa bisa buka detail, preview, atau unduh file.

### Query Dasar yang Dibutuhkan
- Insert materi baru saat dosen upload.
- Select daftar materi berdasarkan `class_id`.
- Select detail satu materi berdasarkan `id`.
- Update materi jika nanti ada fitur edit.
- Delete materi jika dosen atau admin menghapus.

### Dampak ke File SQL Railway
- File `railway_database.sql` perlu ditambah definisi tabel `materials`.
- Jika schema Railway sudah terlanjur live, perlu migration SQL terpisah.
- Data lama tidak terganggu karena tabel baru bersifat tambahan.

### Risiko Teknis
- Ukuran file materi bisa membebani storage jika tidak dibatasi.
- Format file perlu divalidasi supaya tidak sembarang upload.
- Preview PDF lebih mudah daripada DOC atau DOCX.
- Harus ada aturan akses supaya hanya anggota kelas yang bisa lihat materi.

### Aturan Akses yang Disarankan
- Dosen/admin: bisa upload, edit, dan hapus materi.
- Mahasiswa: hanya bisa melihat materi dari kelas yang diikuti.
- Pengunjung non-login: tidak boleh akses.

## 10. Rencana Preview File Tanpa Download
### Tujuan
Membuat pengguna bisa melihat informasi file sebelum mengunduh, supaya pengalaman akses materi dan tugas lebih nyaman.

### Fokus Preview
- Menampilkan metadata file:
  - nama file,
  - ukuran file,
  - tipe file,
  - tanggal upload,
  - pengunggah.
- Menyediakan tombol:
  - lihat detail,
  - preview jika memungkinkan,
  - unduh file.

### Strategi Preview yang Disarankan
1. **PDF**
   - bisa dibuka langsung di browser dengan embedded viewer atau link preview.
2. **DOC / DOCX**
   - tidak dipaksakan preview penuh di server.
   - cukup tampilkan metadata dan tombol unduh.
3. **File lain**
   - tampilkan metadata dasar dan opsi unduh jika diizinkan.

### Komponen UI yang Dibutuhkan
- kartu atau list file pada materi dan tugas,
- badge tipe file,
- panel detail file,
- tombol preview,
- tombol download.

### Perubahan Backend yang Mungkin Dibutuhkan
- membaca `mime_type` dari database,
- menentukan apakah file bisa dipreview,
- membuat endpoint atau route preview jika nanti dibutuhkan,
- memastikan hak akses file tetap aman.

### Keamanan
- Preview hanya boleh untuk user yang memang punya akses ke kelas.
- File tidak boleh dibuka bebas tanpa validasi login.
- Link preview/download sebaiknya pakai token atau validasi session.

### Dampak ke Sistem
- tidak wajib menambah tabel baru jika metadata file sudah ada,
- tetapi lebih baik jika tabel `materials` menyimpan `mime_type` dan `file_size`,
- logic tampilannya akan lebih rapi karena file bisa dibedakan berdasarkan jenis.

### Output yang Diharapkan
- pengguna bisa tahu isi file secara umum tanpa download dulu,
- PDF bisa preview langsung,
- Word tetap aman lewat unduhan,
- tampilan file jadi lebih modern dan praktis.

### Status
- [x] Rencana preview file sudah dibuat
- [ ] Implementasi metadata file
- [ ] Implementasi preview PDF
- [ ] Implementasi preview atau unduh Word
