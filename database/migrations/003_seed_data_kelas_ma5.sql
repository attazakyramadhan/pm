-- ==============================================================
-- S-LECTURA: Migrasi 003 - Seed Data Kelas Manajemen Informatika A 5
-- Password placeholder: semua dosen & mahasiswa pakai hash 'changeme'
-- WAJIB jalankan http://localhost/pm/database/update_passwords.php?token=slectura_setup
-- setelah import ini untuk set password yang benar!
-- ==============================================================
USE `s_lectura`;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- BERSIHKAN DATA LAMA (dosen, mahasiswa, jadwal, status, slot)
-- Pertahankan admin (id=1) dan program_studi
-- ============================================================
DELETE FROM `status_ketersediaan`;
DELETE FROM `slot_konsultasi`;
DELETE FROM `jadwal_dosen`;
DELETE FROM `jadwal_mengajar`;
DELETE FROM `dosen`;
DELETE FROM `users` WHERE id > 1;  -- Pertahankan admin
DELETE FROM `mahasiswa`;
DELETE FROM `kelas`;
DELETE FROM `mata_kuliah`;

-- Reset auto-increment
ALTER TABLE `users` AUTO_INCREMENT = 2;
ALTER TABLE `dosen` AUTO_INCREMENT = 1;
ALTER TABLE `kelas` AUTO_INCREMENT = 1;
ALTER TABLE `mata_kuliah` AUTO_INCREMENT = 1;
ALTER TABLE `jadwal_mengajar` AUTO_INCREMENT = 1;
ALTER TABLE `slot_konsultasi` AUTO_INCREMENT = 1;
ALTER TABLE `status_ketersediaan` AUTO_INCREMENT = 1;
ALTER TABLE `mahasiswa` AUTO_INCREMENT = 1;

-- ============================================================
-- 1. PROGRAM STUDI (pastikan id=2 = Manajemen Informatika ada)
-- ============================================================
INSERT IGNORE INTO `program_studi` (`id`, `nama_prodi`, `jurusan`) VALUES
(1, 'D4 Rekayasa Perangkat Lunak', 'Teknologi Informasi'),
(2, 'Manajemen Informatika',       'Teknologi Informasi'),
(3, 'D3 Akuntansi Terapan',        'Ekonomi dan Bisnis');

-- Update nama prodi id=2 agar konsisten dengan spec
UPDATE `program_studi` SET `nama_prodi`='Manajemen Informatika', `jurusan`='Teknologi Informasi' WHERE id=2;

-- ============================================================
-- 2. KELAS
-- ============================================================
INSERT INTO `kelas` (`id`, `nama_kelas`, `id_prodi`, `semester`, `tahun_ajaran`) VALUES
(1, 'Manajemen Informatika A 5', 2, 5, '2026/2027 Ganjil');

-- ============================================================
-- 3. MATA KULIAH
-- ============================================================
INSERT INTO `mata_kuliah` (`id`, `kode`, `nama`) VALUES
(1, 'PMI1516', 'Pemrograman Basis Data'),
(2, 'PKU1511', 'Proyek Mandiri'),
(3, 'PMI1517', 'Pemrograman Berbasis Mobile'),
(4, 'PMI1519', 'E-Bisnis'),
(5, 'PMI1518', 'Etika dan Hukum Teknologi Informasi'),
(6, 'PMI1511', 'Keamanan Sistem Informasi');

-- ============================================================
-- 4. USERS DOSEN (id 2-7)
-- Password placeholder '$2y$10$placeholder' akan diupdate oleh update_passwords.php
-- Hash ini adalah password_hash('changeme', PASSWORD_BCRYPT) yang valid
-- ============================================================
-- Hash 'changeme': $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
INSERT INTO `users` (`id`, `nama`, `email`, `password_hash`, `role`, `npm`, `must_change_password`) VALUES
(2,  'Muhammad Reza Redo Islami, S.Kom., M.T.I.', 'reza@polinela.ac.id',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dosen',     NULL, 1),
(3,  'Dwirgo Sahlinal, S.T., M.Eng.',             'dwirgo@polinela.ac.id',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dosen',     NULL, 1),
(4,  'Dewi Kania Widyawati, S.Kom., M.Kom.',      'dewi@polinela.ac.id',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dosen',     NULL, 1),
(5,  'Akhmad Jayadi, S.Kom., M.Cs.',              'akhmad@polinela.ac.id',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dosen',     NULL, 1),
(6,  'Rima Maulini, S.Kom., M.Kom.',              'rima@polinela.ac.id',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dosen',     NULL, 1),
(7,  'Kurniawan Saputra, S.Kom., M.Kom.',         'kurniawan@polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dosen',     NULL, 1);

-- ============================================================
-- 5. USERS MAHASISWA (id 8-41)
-- Password placeholder sama, akan diupdate
-- ============================================================
INSERT INTO `users` (`id`, `nama`, `email`, `password_hash`, `role`, `npm`, `must_change_password`) VALUES
(8,  'Abdur Rouf Hanafi',             '24781001@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(9,  'Ahmad Rizky Maulana',           '24781002@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(10, 'Alzahra Dwi Febriyan',          '24781003@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(11, 'Atta Zaky Ramadhan',            '24781004@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(12, 'Bunga Putri Salsabilla',        '24781005@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(13, 'Dafa Anggara Yonata',           '24781006@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(14, 'Deni Prawira',                  '24781007@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(15, 'Dona Virza',                    '24781008@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(16, 'Fahmi Ghozali',                 '24781009@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(17, 'Farhan Habibullah',             '24781010@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(18, 'Fitri Amelia Ananti',           '24781011@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(19, 'Heidy Putri Shafira',           '24781012@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(20, 'Jesfitrina Sihombing',          '24781013@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(21, 'Lia Agustina',                  '24781014@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(22, 'M. Rayhan Zulkarnain',          '24781015@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(23, 'Muhammad Alwan Dzaky',          '24781016@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(24, 'Nabila Alfi Nur Khasanah',      '24781017@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(25, 'Nadiya Ghefira El Firsi',       '24781018@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(26, 'Nayla Putri Syafira Arrovi',    '24781019@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(27, 'Nyken Sekar Ayuningtyas',       '24781020@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(28, 'Rafi Diandra Ardi Agusta',      '24781021@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(29, 'Rendy Dwi Prayoga',             '24781022@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(30, 'Rifki Rangga Saputra',          '24781023@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(31, 'Rizki Surohman',                '24781024@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(32, 'Rubby Ibnu Anantara',           '24781025@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(33, 'Septi Cahyaningtias',           '24781026@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(34, 'Sofi Ramadhani',                '24781027@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(35, 'Tasya Rismala',                 '24781028@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(36, 'Ulfa Setyaningsih',             '24781029@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(37, 'Yoga Ricky Pasaribu',           '24781030@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(38, 'Yusuf Al Fikri Jayasena',       '24781031@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(39, 'Ahmad Muhajir',                 '24781093@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(40, 'Alya Alfi Lutfiah',             '24781094@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1),
(41, 'Atha Talitha Nabil',            'atha.talitha@mhs.polinela.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa', NULL, 1);

-- ============================================================
-- 6. DATA PROFIL DOSEN
-- ============================================================
INSERT INTO `dosen` (`id`, `user_id`, `nidn_nip`, `id_prodi`, `foto`, `bidang_keahlian`) VALUES
(1, 2, NULL,                 2, NULL, 'Pemrograman Basis Data, Teknologi Informasi, Rekayasa Perangkat Lunak'),
(2, 3, NULL,                 2, NULL, 'Rekayasa Perangkat Lunak, Pemrograman Mobile, Teknik Elektro'),
(3, 4, NULL,                 2, NULL, 'Sistem Informasi, Pemrograman Web, Manajemen Proyek'),
(4, 5, '199407172024061001', 2, NULL, 'Pemrograman Mobile, Keamanan Sistem Informasi, Basis Data'),
(5, 6, '197802192005012001', 2, NULL, 'E-Bisnis, Etika dan Hukum TI, Sistem Informasi'),
(6, 7, NULL,                 2, NULL, 'Keamanan Sistem Informasi, E-Bisnis, Pemrograman Web');

-- ============================================================
-- 7. TABEL MAHASISWA (relasi user_id → npm + kelas + prodi)
-- ============================================================
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
-- No.34: NPM duplikat - simpan sementara dengan penanda
(34, 41, '24781025-PERIKSA', 1, 2);

-- ============================================================
-- 8. JADWAL MENGAJAR (13 baris - skema baru normalized)
-- ============================================================
-- D1=Reza(1) D2=Dwirgo(2) D3=Dewi(3) D4=Akhmad(4) D5=Rima(5) D6=Kurniawan(6)
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

-- ============================================================
-- 9. JADWAL DOSEN (24 baris - relasi many-to-many)
-- ============================================================
INSERT INTO `jadwal_dosen` (`id_jadwal`, `id_dosen`) VALUES
-- Jadwal 1: PMI1516 Teori Senin → D1 (Reza), D2 (Dwirgo)
(1, 1), (1, 2),
-- Jadwal 2: PKU1511 Praktik Senin → D3 (Dewi), D2 (Dwirgo)
(2, 3), (2, 2),
-- Jadwal 3: PMI1516 Praktik Senin luring → D1 (Reza), D2 (Dwirgo)
(3, 1), (3, 2),
-- Jadwal 4: PKU1511 Praktik Selasa → D3 (Dewi), D2 (Dwirgo)
(4, 3), (4, 2),
-- Jadwal 5: PMI1517 Teori Selasa → D4 (Akhmad)
(5, 4),
-- Jadwal 6: PMI1519 Praktik Rabu luring → D5 (Rima), D6 (Kurniawan)
(6, 5), (6, 6),
-- Jadwal 7: PMI1518 Teori Rabu → D6 (Kurniawan), D5 (Rima)
(7, 6), (7, 5),
-- Jadwal 8: PMI1517 Praktik Kamis luring → D4 (Akhmad)
(8, 4),
-- Jadwal 9: PMI1511 Teori Kamis → D1 (Reza), D4 (Akhmad)
(9, 1), (9, 4),
-- Jadwal 10: PMI1519 Teori Kamis → D5 (Rima), D6 (Kurniawan)
(10, 5), (10, 6),
-- Jadwal 11: PKU1511 Praktik Jumat → D3 (Dewi), D2 (Dwirgo)
(11, 3), (11, 2),
-- Jadwal 12: PMI1518 Praktik Jumat luring → D6 (Kurniawan), D5 (Rima)
(12, 6), (12, 5),
-- Jadwal 13: PMI1511 Praktik Jumat luring → D1 (Reza), D4 (Akhmad)
(13, 1), (13, 4);

-- ============================================================
-- 10. STATUS KETERSEDIAAN (semua dosen mulai dengan "Tersedia")
-- ============================================================
INSERT INTO `status_ketersediaan` (`id`, `id_dosen`, `status`, `catatan`, `berlaku_sampai`) VALUES
(1, 1, 'Tersedia', '[DUMMY] Status awal sistem', NULL),
(2, 2, 'Tersedia', '[DUMMY] Status awal sistem', NULL),
(3, 3, 'Tersedia', '[DUMMY] Status awal sistem', NULL),
(4, 4, 'Tersedia', '[DUMMY] Status awal sistem', NULL),
(5, 5, 'Tersedia', '[DUMMY] Status awal sistem', NULL),
(6, 6, 'Tersedia', '[DUMMY] Status awal sistem', NULL);

-- ============================================================
-- 11. SLOT KONSULTASI (1-2 slot per dosen, tidak bentrok jadwal)
-- Semua diberi catatan [DUMMY]
-- ============================================================
-- D1 (Reza): mengajar Senin 07:30-08:20, 15:00-19:00; Kamis 08:40-10:20, 17:00-21:00; Jumat 17:00-21:00
-- Slot: Senin 09:00-10:30 (konsultasi), Kamis 11:00-12:30 (bimbingan)
INSERT INTO `slot_konsultasi` (`id`, `id_dosen`, `hari`, `jam_mulai`, `jam_selesai`, `jenis`, `catatan`, `aktif`) VALUES
(1,  1, 'Senin',  '09:00:00', '10:30:00', 'konsultasi', '[DUMMY] Konsultasi akademik & KRS semester aktif', 1),
(2,  1, 'Kamis',  '11:00:00', '12:30:00', 'bimbingan',  '[DUMMY] Bimbingan Tugas Akhir & Proyek Mandiri',  1),

-- D2 (Dwirgo): mengajar Senin 07:30-08:20, 13:00-19:00; Selasa 13:00-15:00; Jumat 09:00-11:00
-- Slot: Rabu 09:00-11:00 (konsultasi), Kamis 09:00-10:30 (bimbingan)
(3,  2, 'Rabu',   '09:00:00', '11:00:00', 'konsultasi', '[DUMMY] Konsultasi materi & tugas kuliah',        1),
(4,  2, 'Kamis',  '09:00:00', '10:30:00', 'bimbingan',  '[DUMMY] Bimbingan PKU & Proyek Akhir',            1),

-- D3 (Dewi): mengajar Senin 13:00-15:00; Selasa 13:00-15:00; Jumat 09:00-11:00
-- Slot: Rabu 10:00-12:00 (bimbingan), Kamis 13:00-15:00 (konsultasi)
(5,  3, 'Rabu',   '10:00:00', '12:00:00', 'bimbingan',  '[DUMMY] Bimbingan Proyek Mandiri & laporan PKU',  1),
(6,  3, 'Kamis',  '13:00:00', '15:00:00', 'konsultasi', '[DUMMY] Konsultasi akademik & rencana studi',     1),

-- D4 (Akhmad): mengajar Selasa 07:00-07:50; Kamis 08:40-10:20, 17:00-21:00
-- Slot: Senin 10:00-12:00 (konsultasi), Rabu 13:00-15:00 (bimbingan)
(7,  4, 'Senin',  '10:00:00', '12:00:00', 'konsultasi', '[DUMMY] Konsultasi Pemrograman Mobile & Keamanan', 1),
(8,  4, 'Rabu',   '13:00:00', '15:00:00', 'bimbingan',  '[DUMMY] Bimbingan Proyek Akhir & Tugas Besar',    1),

-- D5 (Rima): mengajar Rabu 07:00-07:50, 15:00-17:00; Kamis 07:00-07:50; Jumat 13:30-15:30
-- Slot: Selasa 10:00-12:00 (konsultasi), Kamis 10:30-12:00 (bimbingan)
(9,  5, 'Selasa', '10:00:00', '12:00:00', 'konsultasi', '[DUMMY] Konsultasi E-Bisnis & Etika TI',          1),
(10, 5, 'Kamis',  '10:30:00', '12:00:00', 'bimbingan',  '[DUMMY] Bimbingan PKU & laporan proyek mandiri',  1),

-- D6 (Kurniawan): mengajar Rabu 07:00-07:50, 15:00-17:00; Kamis 07:00-07:50; Jumat 13:30-15:30
-- Slot: Senin 10:00-12:00 (konsultasi), Selasa 10:00-12:00 (bimbingan)
(11, 6, 'Senin',  '10:00:00', '12:00:00', 'konsultasi', '[DUMMY] Konsultasi Keamanan Sistem & E-Bisnis',   1),
(12, 6, 'Selasa', '10:00:00', '12:00:00', 'bimbingan',  '[DUMMY] Bimbingan Proyek Akhir & Skripsi',        1);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- VERIFIKASI JUMLAH DATA
-- ============================================================
SELECT 'users'           AS tabel, COUNT(*) AS jumlah FROM users
UNION ALL SELECT 'dosen',         COUNT(*) FROM dosen
UNION ALL SELECT 'mahasiswa',     COUNT(*) FROM mahasiswa
UNION ALL SELECT 'kelas',         COUNT(*) FROM kelas
UNION ALL SELECT 'mata_kuliah',   COUNT(*) FROM mata_kuliah
UNION ALL SELECT 'jadwal_mengajar', COUNT(*) FROM jadwal_mengajar
UNION ALL SELECT 'jadwal_dosen',  COUNT(*) FROM jadwal_dosen
UNION ALL SELECT 'slot_konsultasi', COUNT(*) FROM slot_konsultasi
UNION ALL SELECT 'status_ketersediaan', COUNT(*) FROM status_ketersediaan;

