-- ==============================================================
-- S-LECTURA: Smart Lecturer Availability System
-- Politeknik Negeri Lampung (POLINELA)
-- Skema Basis Data & Data Awal Lengkap (Normalized v2)
-- Database: s_lectura
-- Charset: utf8mb4 / InnoDB
-- ==============================================================

CREATE DATABASE IF NOT EXISTS `s_lectura` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `s_lectura`;

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------------
-- PEMBERSIHAN TABEL
-- --------------------------------------------------------------
DROP TABLE IF EXISTS `status_ketersediaan`;
DROP TABLE IF EXISTS `slot_konsultasi`;
DROP TABLE IF EXISTS `jadwal_dosen`;
DROP TABLE IF EXISTS `jadwal_mengajar`;
DROP TABLE IF EXISTS `mahasiswa`;
DROP TABLE IF EXISTS `dosen`;
DROP TABLE IF EXISTS `kelas`;
DROP TABLE IF EXISTS `mata_kuliah`;
DROP TABLE IF EXISTS `program_studi`;
DROP TABLE IF EXISTS `users`;

-- --------------------------------------------------------------
-- 1. Tabel: users
-- --------------------------------------------------------------
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('mahasiswa', 'dosen', 'admin') NOT NULL,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 1,
  `npm` VARCHAR(20) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 2. Tabel: program_studi
-- --------------------------------------------------------------
CREATE TABLE `program_studi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_prodi` VARCHAR(100) NOT NULL,
  `jurusan` VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 3. Tabel: kelas
-- --------------------------------------------------------------
CREATE TABLE `kelas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_kelas` VARCHAR(100) NOT NULL,
  `id_prodi` INT NOT NULL,
  `semester` TINYINT NOT NULL,
  `tahun_ajaran` VARCHAR(30) NOT NULL,
  CONSTRAINT `fk_kelas_prodi` FOREIGN KEY (`id_prodi`) REFERENCES `program_studi` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 4. Tabel: mata_kuliah
-- --------------------------------------------------------------
CREATE TABLE `mata_kuliah` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kode` VARCHAR(20) NOT NULL UNIQUE,
  `nama` VARCHAR(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 5. Tabel: dosen
-- --------------------------------------------------------------
CREATE TABLE `dosen` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `nidn_nip` VARCHAR(30) NULL UNIQUE,
  `id_prodi` INT NOT NULL,
  `foto` VARCHAR(255) NULL,
  `bidang_keahlian` VARCHAR(150) NOT NULL,
  CONSTRAINT `fk_dosen_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dosen_prodi` FOREIGN KEY (`id_prodi`) REFERENCES `program_studi` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 6. Tabel: mahasiswa
-- --------------------------------------------------------------
CREATE TABLE `mahasiswa` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `npm` VARCHAR(20) NOT NULL UNIQUE,
  `id_kelas` INT NULL,
  `id_prodi` INT NOT NULL,
  CONSTRAINT `fk_mhs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mhs_kelas` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_mhs_prodi` FOREIGN KEY (`id_prodi`) REFERENCES `program_studi` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 7. Tabel: jadwal_mengajar (normalized)
-- --------------------------------------------------------------
CREATE TABLE `jadwal_mengajar` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_mk` INT NOT NULL,
  `id_kelas` INT NOT NULL,
  `hari` ENUM('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat') NOT NULL,
  `jam_mulai` TIME NOT NULL,
  `jam_selesai` TIME NOT NULL,
  `tipe_sesi` ENUM('teori', 'praktik') NOT NULL DEFAULT 'teori',
  `sks` TINYINT NOT NULL DEFAULT 1,
  `mode` ENUM('daring', 'luring') NOT NULL DEFAULT 'luring',
  `ruang` VARCHAR(100) NULL,
  CONSTRAINT `fk_jadwal_mk` FOREIGN KEY (`id_mk`) REFERENCES `mata_kuliah` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_jadwal_kelas` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 8. Tabel: jadwal_dosen (many-to-many / team teaching)
-- --------------------------------------------------------------
CREATE TABLE `jadwal_dosen` (
  `id_jadwal` INT NOT NULL,
  `id_dosen` INT NOT NULL,
  PRIMARY KEY (`id_jadwal`, `id_dosen`),
  CONSTRAINT `fk_jd_jadwal` FOREIGN KEY (`id_jadwal`) REFERENCES `jadwal_mengajar` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_jd_dosen` FOREIGN KEY (`id_dosen`) REFERENCES `dosen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 9. Tabel: slot_konsultasi
-- --------------------------------------------------------------
CREATE TABLE `slot_konsultasi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_dosen` INT NOT NULL,
  `hari` ENUM('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat') NOT NULL,
  `jam_mulai` TIME NOT NULL,
  `jam_selesai` TIME NOT NULL,
  `jenis` ENUM('konsultasi', 'bimbingan') NOT NULL,
  `catatan` VARCHAR(255) NULL,
  `aktif` TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT `fk_slot_dosen` FOREIGN KEY (`id_dosen`) REFERENCES `dosen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 10. Tabel: status_ketersediaan
-- --------------------------------------------------------------
CREATE TABLE `status_ketersediaan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_dosen` INT NOT NULL UNIQUE,
  `status` ENUM('Tersedia', 'Mengajar', 'Hanya Konsultasi', 'Hanya Bimbingan', 'Tidak Tersedia') NOT NULL DEFAULT 'Tersedia',
  `catatan` VARCHAR(255) NULL,
  `berlaku_sampai` DATETIME NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_status_dosen` FOREIGN KEY (`id_dosen`) REFERENCES `dosen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ==============================================================
-- DATA AWAL (SEEDING LENGKAP)
-- ==============================================================

-- 1. Program Studi
INSERT INTO `program_studi` (`id`, `nama_prodi`, `jurusan`) VALUES
(1, 'D4 Rekayasa Perangkat Lunak', 'Teknologi Informasi'),
(2, 'Manajemen Informatika',       'Teknologi Informasi'),
(3, 'D3 Akuntansi Terapan',        'Ekonomi dan Bisnis');

-- 2. Kelas
INSERT INTO `kelas` (`id`, `nama_kelas`, `id_prodi`, `semester`, `tahun_ajaran`) VALUES
(1, 'Manajemen Informatika A 5', 2, 5, '2026/2027 Ganjil');

-- 3. Mata Kuliah
INSERT INTO `mata_kuliah` (`id`, `kode`, `nama`) VALUES
(1, 'PMI1516', 'Pemrograman Basis Data'),
(2, 'PKU1511', 'Proyek Mandiri'),
(3, 'PMI1517', 'Pemrograman Berbasis Mobile'),
(4, 'PMI1519', 'E-Bisnis'),
(5, 'PMI1518', 'Etika dan Hukum Teknologi Informasi'),
(6, 'PMI1511', 'Keamanan Sistem Informasi');

-- 4. Users (1 Admin + 6 Dosen + 34 Mahasiswa = 41 Akun)
-- Hash:
-- admin123: $2y$10$82aIXLlST8d/RmgbrdE/Hu6GnXdCKxIFy/xWsSSCbv6DYobFQnA/i
-- Polinela#2026 / mhs: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi (jalankan update_passwords.php untuk hash bcrypt final)
INSERT INTO `users` (`id`, `nama`, `email`, `password_hash`, `role`, `must_change_password`, `npm`) VALUES
-- 1 Admin
(1,  'Administrator Polinela',                     'admin@polinela.ac.id',           '$2y$10$82aIXLlST8d/RmgbrdE/Hu6GnXdCKxIFy/xWsSSCbv6DYobFQnA/i', 'admin',     0, NULL),
-- 6 Dosen
(2,  'Muhammad Reza Redo Islami, S.Kom., M.T.I.', 'reza@polinela.ac.id',            '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dosen',     1, NULL),
(3,  'Dwirgo Sahlinal, S.T., M.Eng.',             'dwirgo@polinela.ac.id',          '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dosen',     1, NULL),
(4,  'Dewi Kania Widyawati, S.Kom., M.Kom.',      'dewi@polinela.ac.id',            '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dosen',     1, NULL),
(5,  'Akhmad Jayadi, S.Kom., M.Cs.',              'akhmad@polinela.ac.id',          '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dosen',     1, NULL),
(6,  'Rima Maulini, S.Kom., M.Kom.',              'rima@polinela.ac.id',            '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dosen',     1, NULL),
(7,  'Kurniawan Saputra, S.Kom., M.Kom.',         'kurniawan@polinela.ac.id',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dosen',     1, NULL),
-- 34 Mahasiswa
(8,  'Abdur Rouf Hanafi',                         '24781001@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781001'),
(9,  'Ahmad Rizky Maulana',                       '24781002@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781002'),
(10, 'Alzahra Dwi Febriyan',                      '24781003@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781003'),
(11, 'Atta Zaky Ramadhan',                        '24781004@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781004'),
(12, 'Bunga Putri Salsabilla',                    '24781005@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781005'),
(13, 'Dafa Anggara Yonata',                       '24781006@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781006'),
(14, 'Deni Prawira',                              '24781007@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781007'),
(15, 'Dona Virza',                                '24781008@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781008'),
(16, 'Fahmi Ghozali',                             '24781009@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781009'),
(17, 'Farhan Habibullah',                         '24781010@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781010'),
(18, 'Fitri Amelia Ananti',                       '24781011@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781011'),
(19, 'Heidy Putri Shafira',                       '24781012@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781012'),
(20, 'Jesfitrina Sihombing',                      '24781013@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781013'),
(21, 'Lia Agustina',                              '24781014@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781014'),
(22, 'M. Rayhan Zulkarnain',                      '24781015@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781015'),
(23, 'Muhammad Alwan Dzaky',                      '24781016@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781016'),
(24, 'Nabila Alfi Nur Khasanah',                  '24781017@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781017'),
(25, 'Nadiya Ghefira El Firsi',                   '24781018@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781018'),
(26, 'Nayla Putri Syafira Arrovi',                '24781019@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781019'),
(27, 'Nyken Sekar Ayuningtyas',                   '24781020@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781020'),
(28, 'Rafi Diandra Ardi Agusta',                  '24781021@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781021'),
(29, 'Rendy Dwi Prayoga',                         '24781022@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781022'),
(30, 'Rifki Rangga Saputra',                      '24781023@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781023'),
(31, 'Rizki Surohman',                            '24781024@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781024'),
(32, 'Rubby Ibnu Anantara',                       '24781025@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781025'),
(33, 'Septi Cahyaningtias',                       '24781026@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781026'),
(34, 'Sofi Ramadhani',                            '24781027@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781027'),
(35, 'Tasya Rismala',                             '24781028@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781028'),
(36, 'Ulfa Setyaningsih',                         '24781029@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781029'),
(37, 'Yoga Ricky Pasaribu',                       '24781030@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781030'),
(38, 'Yusuf Al Fikri Jayasena',                   '24781031@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781031'),
(39, 'Ahmad Muhajir',                             '24781093@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781093'),
(40, 'Alya Alfi Lutfiah',                         '24781094@mhs.polinela.ac.id',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781094'),
-- Mahasiswa 34: Duplikat NPM dengan nomor 25 - diberi penanda '24781025-PERIKSA'
(41, 'Atha Talitha Nabil',                        'atha.talitha@mhs.polinela.ac.id','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', 1, '24781025-PERIKSA');

-- 5. Profil Dosen
INSERT INTO `dosen` (`id`, `user_id`, `nidn_nip`, `id_prodi`, `foto`, `bidang_keahlian`) VALUES
(1, 2, NULL,                 2, NULL, 'Pemrograman Basis Data, Teknologi Informasi, Rekayasa Perangkat Lunak'),
(2, 3, NULL,                 2, NULL, 'Rekayasa Perangkat Lunak, Pemrograman Mobile, Teknik Elektro'),
(3, 4, NULL,                 2, NULL, 'Sistem Informasi, Pemrograman Web, Manajemen Proyek'),
(4, 5, '199407172024061001', 2, NULL, 'Pemrograman Mobile, Keamanan Sistem Informasi, Basis Data'),
(5, 6, '197802192005012001', 2, NULL, 'E-Bisnis, Etika dan Hukum TI, Sistem Informasi'),
(6, 7, NULL,                 2, NULL, 'Keamanan Sistem Informasi, E-Bisnis, Pemrograman Web');

-- 6. Mahasiswa (Relasi Kelas & Prodi)
INSERT INTO `mahasiswa` (`id`, `user_id`, `npm`, `id_kelas`, `id_prodi`) VALUES
(1,  8,  '24781001',         1, 2),
(2,  9,  '24781002',         1, 2),
(3,  10, '24781003',         1, 2),
(4,  11, '24781004',         1, 2),
(5,  12, '24781005',         1, 2),
(6,  13, '24781006',         1, 2),
(7,  14, '24781007',         1, 2),
(8,  15, '24781008',         1, 2),
(9,  16, '24781009',         1, 2),
(10, 17, '24781010',         1, 2),
(11, 18, '24781011',         1, 2),
(12, 19, '24781012',         1, 2),
(13, 20, '24781013',         1, 2),
(14, 21, '24781014',         1, 2),
(15, 22, '24781015',         1, 2),
(16, 23, '24781016',         1, 2),
(17, 24, '24781017',         1, 2),
(18, 25, '24781018',         1, 2),
(19, 26, '24781019',         1, 2),
(20, 27, '24781020',         1, 2),
(21, 28, '24781021',         1, 2),
(22, 29, '24781022',         1, 2),
(23, 30, '24781023',         1, 2),
(24, 31, '24781024',         1, 2),
(25, 32, '24781025',         1, 2),
(26, 33, '24781026',         1, 2),
(27, 34, '24781027',         1, 2),
(28, 35, '24781028',         1, 2),
(29, 36, '24781029',         1, 2),
(30, 37, '24781030',         1, 2),
(31, 38, '24781031',         1, 2),
(32, 39, '24781093',         1, 2),
(33, 40, '24781094',         1, 2),
(34, 41, '24781025-PERIKSA', 1, 2);

-- 7. Jadwal Mengajar (13 baris kelas Manajemen Informatika A 5)
-- D1=Reza(1), D2=Dwirgo(2), D3=Dewi(3), D4=Akhmad(4), D5=Rima(5), D6=Kurniawan(6)
-- MK: PMI1516=1, PKU1511=2, PMI1517=3, PMI1519=4, PMI1518=5, PMI1511=6
INSERT INTO `jadwal_mengajar` (`id`, `id_mk`, `id_kelas`, `hari`, `jam_mulai`, `jam_selesai`, `tipe_sesi`, `sks`, `mode`, `ruang`) VALUES
(1,  1, 1, 'Senin',  '07:30:00', '08:20:00', 'teori',   1, 'daring', NULL),
(2,  2, 1, 'Senin',  '13:00:00', '15:00:00', 'praktik', 1, 'daring', NULL),
(3,  1, 1, 'Senin',  '15:00:00', '19:00:00', 'praktik', 2, 'luring', 'Lab Terpadu Lab. Komputer 3.6'),
(4,  2, 1, 'Selasa', '13:00:00', '15:00:00', 'praktik', 1, 'daring', NULL),
(5,  3, 1, 'Selasa', '07:00:00', '07:50:00', 'teori',   1, 'daring', NULL),
(6,  4, 1, 'Rabu',   '15:00:00', '17:00:00', 'praktik', 1, 'luring', 'Lab Terpadu Ruang 2.4'),
(7,  5, 1, 'Rabu',   '07:00:00', '07:50:00', 'teori',   1, 'daring', NULL),
(8,  3, 1, 'Kamis',  '17:00:00', '21:00:00', 'praktik', 2, 'luring', 'Lab Terpadu Lab. Komputer 3.1'),
(9,  6, 1, 'Kamis',  '08:40:00', '10:20:00', 'teori',   2, 'daring', NULL),
(10, 4, 1, 'Kamis',  '07:00:00', '07:50:00', 'teori',   1, 'daring', NULL),
(11, 2, 1, 'Jumat',  '09:00:00', '11:00:00', 'praktik', 1, 'daring', NULL),
(12, 5, 1, 'Jumat',  '13:30:00', '15:30:00', 'praktik', 1, 'luring', 'Lab Terpadu Ruang 2.4'),
(13, 6, 1, 'Jumat',  '17:00:00', '21:00:00', 'praktik', 2, 'luring', 'Lab Terpadu Lab. Komputer 3.1');

-- 8. Jadwal Dosen (24 baris relasi many-to-many)
INSERT INTO `jadwal_dosen` (`id_jadwal`, `id_dosen`) VALUES
(1, 1), (1, 2),
(2, 3), (2, 2),
(3, 1), (3, 2),
(4, 3), (4, 2),
(5, 4),
(6, 5), (6, 6),
(7, 6), (7, 5),
(8, 4),
(9, 1), (9, 4),
(10, 5), (10, 6),
(11, 3), (11, 2),
(12, 6), (12, 5),
(13, 1), (13, 4);

-- 9. Status Ketersediaan (Semua diawali "Tersedia")
INSERT INTO `status_ketersediaan` (`id`, `id_dosen`, `status`, `catatan`, `berlaku_sampai`) VALUES
(1, 1, 'Tersedia', '[DUMMY] Status awal sistem', NULL),
(2, 2, 'Tersedia', '[DUMMY] Status awal sistem', NULL),
(3, 3, 'Tersedia', '[DUMMY] Status awal sistem', NULL),
(4, 4, 'Tersedia', '[DUMMY] Status awal sistem', NULL),
(5, 5, 'Tersedia', '[DUMMY] Status awal sistem', NULL),
(6, 6, 'Tersedia', '[DUMMY] Status awal sistem', NULL);

-- 10. Slot Konsultasi & Bimbingan (Tidak bentrok dengan jadwal mengajar)
INSERT INTO `slot_konsultasi` (`id`, `id_dosen`, `hari`, `jam_mulai`, `jam_selesai`, `jenis`, `catatan`, `aktif`) VALUES
-- D1 (Reza)
(1,  1, 'Senin',  '09:00:00', '10:30:00', 'konsultasi', '[DUMMY] Konsultasi akademik & KRS semester aktif', 1),
(2,  1, 'Kamis',  '11:00:00', '12:30:00', 'bimbingan',  '[DUMMY] Bimbingan Tugas Akhir & Proyek Mandiri',  1),
-- D2 (Dwirgo)
(3,  2, 'Rabu',   '09:00:00', '11:00:00', 'konsultasi', '[DUMMY] Konsultasi materi & tugas kuliah',        1),
(4,  2, 'Kamis',  '09:00:00', '10:30:00', 'bimbingan',  '[DUMMY] Bimbingan PKU & Proyek Akhir',            1),
-- D3 (Dewi)
(5,  3, 'Rabu',   '10:00:00', '12:00:00', 'bimbingan',  '[DUMMY] Bimbingan Proyek Mandiri & laporan PKU',  1),
(6,  3, 'Kamis',  '13:00:00', '15:00:00', 'konsultasi', '[DUMMY] Konsultasi akademik & rencana studi',     1),
-- D4 (Akhmad)
(7,  4, 'Senin',  '10:00:00', '12:00:00', 'konsultasi', '[DUMMY] Konsultasi Pemrograman Mobile & Keamanan', 1),
(8,  4, 'Rabu',   '13:00:00', '15:00:00', 'bimbingan',  '[DUMMY] Bimbingan Proyek Akhir & Tugas Besar',    1),
-- D5 (Rima)
(9,  5, 'Selasa', '10:00:00', '12:00:00', 'konsultasi', '[DUMMY] Konsultasi E-Bisnis & Etika TI',          1),
(10, 5, 'Kamis',  '10:30:00', '12:00:00', 'bimbingan',  '[DUMMY] Bimbingan PKU & laporan proyek mandiri',  1),
-- D6 (Kurniawan)
(11, 6, 'Senin',  '10:00:00', '12:00:00', 'konsultasi', '[DUMMY] Konsultasi Keamanan Sistem & E-Bisnis',   1),
(12, 6, 'Selasa', '10:00:00', '12:00:00', 'bimbingan',  '[DUMMY] Bimbingan Proyek Akhir & Skripsi',        1);
