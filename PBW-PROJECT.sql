CREATE DATABASE my_task;
USE my_task;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'dosen', 'mahasiswa') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

DELETE FROM users WHERE email = 'admin@mytask.id';

INSERT INTO users (nama, email, password, role) 
VALUES ('Administrator', 'admin@mytask.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

USE my_task;
CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kelas VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    kode_kelas VARCHAR(10) UNIQUE NOT NULL,
    dosen_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dosen_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE class_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    mahasiswa_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (mahasiswa_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    deskripsi TEXT NOT NULL,
    tipe VARCHAR(50) NOT NULL, -- Contoh: 'tugas', 'kelas_baru', 'gabung'
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

ALTER TABLE users ADD foto_profil VARCHAR(255) DEFAULT NULL;

CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    judul VARCHAR(255) NOT NULL,
    deskripsi TEXT NOT NULL,
    deadline DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
);

ALTER TABLE activities ADD is_read TINYINT(1) DEFAULT 0;