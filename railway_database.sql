CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    PASSWORD VARCHAR(255) NOT NULL,
    ROLE ENUM('admin', 'dosen', 'mahasiswa') NOT NULL,
    foto_profil VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (nama, email, PASSWORD, ROLE) 
VALUES ('Administrator', 'admin@myacademic.app', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

INSERT INTO users (nama, email, PASSWORD, ROLE) 
VALUES ('Dr. Imamah, S.kom., M.kom.', '198507212014042001@dosen.trunojoyo.id', '$2a$12$howIPRb4p1CjlGTLon9O9uwIl/iYCni3vbHH25BCMJnOTAiZ.5U62', 'dosen');

INSERT INTO users (nama, email, PASSWORD, ROLE) 
VALUES ('Iqbal Hakim Hakamullah', '250441100017@student.trunojoyo.id', '$2a$12$/Nb7RP8yI463Yuaep21iKeO40bCvGSox8ui9RCeMW00etcSfFSqoS', 'mahasiswa');

INSERT INTO users (nama, email, PASSWORD, ROLE) 
VALUES ('Eko Rismanto', '250441100136@student.trunojoyo.id', '$2a$12$OJvUMEJapcHNt0sAaZ1TAubiekuE.qcAFZ38HJHd2GbsQ1H6aXTpu', 'mahasiswa');

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
    is_read TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    judul VARCHAR(255) NOT NULL,
    deskripsi TEXT NOT NULL,
    deadline DATETIME NOT NULL,
    material_source_type ENUM('upload_baru','existing') DEFAULT 'upload_baru',
    material_reference_id INT DEFAULT NULL,
    material_file_path VARCHAR(255) DEFAULT NULL,
    material_original_name VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
);

CREATE TABLE login_attempts (
    email VARCHAR(100) PRIMARY KEY,
    fail_count INT DEFAULT 0,
    block_level INT DEFAULT 0,
    blocked_until DATETIME DEFAULT NULL,
    blocked_until_ts INT DEFAULT NULL,
    attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE task_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    mahasiswa_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    nilai INT NULL,
    feedback TEXT NULL,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (mahasiswa_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_nama VARCHAR(100) NOT NULL,
    user_role VARCHAR(20) NOT NULL,
    pesan TEXT NOT NULL,
    STATUS ENUM('belum_dibaca', 'sudah_dibaca') DEFAULT 'belum_dibaca',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE lecturer_break_glass_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lecturer_id INT NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME DEFAULT NULL,
    last_used_at DATETIME DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE sensitive_change_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT NOT NULL,
    actor_role VARCHAR(20) NOT NULL,
    lecturer_id INT NOT NULL,
    class_id INT DEFAULT NULL,
    target_type VARCHAR(50) NOT NULL,
    target_id VARCHAR(100) NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    payload_json TEXT DEFAULT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'executed', 'expired') DEFAULT 'pending',
    approver_user_id INT DEFAULT NULL,
    break_glass_used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    approved_at DATETIME DEFAULT NULL,
    executed_at DATETIME DEFAULT NULL,
    rejected_at DATETIME DEFAULT NULL,
    FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE security_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT NOT NULL,
    actor_role VARCHAR(20) NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    target_type VARCHAR(100) NOT NULL,
    target_id VARCHAR(100) NOT NULL,
    reason TEXT NOT NULL,
    context_json TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE CASCADE
);
