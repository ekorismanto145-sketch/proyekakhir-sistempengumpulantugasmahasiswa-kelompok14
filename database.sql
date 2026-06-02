CREATE DATABASE my_task;
USE my_task;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    PASSWORD VARCHAR(255) NOT NULL,
    ROLE ENUM('admin', 'dosen', 'mahasiswa') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

DELETE FROM users WHERE email = 'admin@mytask.id';

INSERT INTO users (nama, email, PASSWORD, ROLE) 
VALUES ('Administrator', 'admin@mytask.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

INSERT INTO users (nama, email, PASSWORD, ROLE) 
VALUES ('Dr. Imamah, S.kom., M.kom.', '198507212014042001@dosen.trunojoyo.id', '$2a$12$howIPRb4p1CjlGTLon9O9uwIl/iYCni3vbHH25BCMJnOTAiZ.5U62', 'dosen');

INSERT INTO users (nama, email, PASSWORD, ROLE) 
VALUES ('Iqbal Hakim Hakamullah', '250441100017@student.trunojoyo.id', '$2a$12$/Nb7RP8yI463Yuaep21iKeO40bCvGSox8ui9RCeMW00etcSfFSqoS', 'mahasiswa');

INSERT INTO users (nama, email, PASSWORD, ROLE) 
VALUES ('Eko Rismanto', '250441100136@student.trunojoyo.id', '$2a$12$OJvUMEJapcHNt0sAaZ1TAubiekuE.qcAFZ38HJHd2GbsQ1H6aXTpu', 'mahasiswa');

DELETE FROM users WHERE email = '198507212014042001@dosen.trunojoyo.id';

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
    tipe VARCHAR(50) NOT NULL, 
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

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100),
    attempt_time DATETIME,
    INDEX(email)
);

CREATE TABLE IF NOT EXISTS task_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    mahasiswa_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (mahasiswa_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX(task_id),
    INDEX(mahasiswa_id)
);

DROP TABLE login_attempts;

ALTER TABLE login_attempts 
ADD COLUMN block_level INT DEFAULT 0,
ADD COLUMN fail_count INT DEFAULT 0,
ADD COLUMN blocked_until DATETIME DEFAULT NULL,
ADD UNIQUE KEY (email);

DROP TABLE IF EXISTS login_attempts;

CREATE TABLE login_attempts (
    email VARCHAR(100) PRIMARY KEY,
    fail_count INT DEFAULT 0,
    block_level INT DEFAULT 0,
    blocked_until DATETIME DEFAULT NULL,
    attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE class_members DROP FOREIGN KEY class_members_ibfk_1;
ALTER TABLE class_members ADD CONSTRAINT class_members_ibfk_1 FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE;

ALTER TABLE tasks DROP FOREIGN KEY tasks_ibfk_1;
ALTER TABLE tasks ADD CONSTRAINT tasks_ibfk_1 FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE;

CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_nama VARCHAR(100) NOT NULL,
    user_role VARCHAR(20) NOT NULL,
    pesan TEXT NOT NULL,
    STATUS ENUM('belum_dibaca', 'sudah_dibaca') DEFAULT 'belum_dibaca',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

ALTER TABLE task_submissions 
ADD COLUMN nilai INT NULL,
ADD COLUMN feedback TEXT NULL;

SELECT * FROM login_attempts;

-- Hapus tabel lama jika ada, lalu buat ulang dengan primary key email
DROP TABLE IF EXISTS login_attempts;
CREATE TABLE login_attempts (
    email VARCHAR(100) PRIMARY KEY,
    fail_count INT DEFAULT 0,
    block_level INT DEFAULT 0,
    blocked_until DATETIME DEFAULT NULL,
    attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE login_attempts ADD COLUMN blocked_until_ts INT DEFAULT NULL;