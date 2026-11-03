-- ==============================================================
-- S-LECTURA: Migrasi 002 - Normalisasi Skema Jadwal Mengajar
-- Tambah tabel: kelas, mata_kuliah, jadwal_dosen, mahasiswa
-- Modifikasi: jadwal_mengajar (struktur baru), users (must_change_password), dosen (nidn_nip nullable)
-- Jalankan SETELAH database s_lectura sudah ada
-- ==============================================================
USE `s_lectura`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Tambah kolom must_change_password ke users (jika belum ada)
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `must_change_password` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`;

-- 2. Ubah nidn_nip jadi nullable (dosen tanpa NIP resmi)
ALTER TABLE `dosen`
    MODIFY `nidn_nip` VARCHAR(30) NULL;

-- 3. Hapus jadwal_mengajar lama sebelum buat yang baru
DROP TABLE IF EXISTS `jadwal_dosen`;
DROP TABLE IF EXISTS `jadwal_mengajar`;

-- 4. Tabel kelas
CREATE TABLE IF NOT EXISTS `kelas` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `nama_kelas`   VARCHAR(100) NOT NULL,
  `id_prodi`     INT NOT NULL,
  `semester`     TINYINT NOT NULL,
  `tahun_ajaran` VARCHAR(30) NOT NULL,
  CONSTRAINT `fk_kelas_prodi` FOREIGN KEY (`id_prodi`)
    REFERENCES `program_studi` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabel mata_kuliah
CREATE TABLE IF NOT EXISTS `mata_kuliah` (
  `id`   INT AUTO_INCREMENT PRIMARY KEY,
  `kode` VARCHAR(20) NOT NULL UNIQUE,
  `nama` VARCHAR(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabel jadwal_mengajar BARU (normalized, tanpa id_dosen langsung)
CREATE TABLE `jadwal_mengajar` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `id_mk`      INT NOT NULL,
  `id_kelas`   INT NOT NULL,
  `hari`       ENUM('Senin','Selasa','Rabu','Kamis','Jumat') NOT NULL,
  `jam_mulai`  TIME NOT NULL,
  `jam_selesai` TIME NOT NULL,
  `tipe_sesi`  ENUM('teori','praktik') NOT NULL DEFAULT 'teori',
  `sks`        TINYINT NOT NULL DEFAULT 1,
  `mode`       ENUM('daring','luring') NOT NULL DEFAULT 'luring',
  `ruang`      VARCHAR(100) NULL,
  CONSTRAINT `fk_jadwal_mk`    FOREIGN KEY (`id_mk`)    REFERENCES `mata_kuliah` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_jadwal_kelas` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id`)       ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Tabel relasi dosen-jadwal (many-to-many / team teaching)
CREATE TABLE `jadwal_dosen` (
  `id_jadwal` INT NOT NULL,
  `id_dosen`  INT NOT NULL,
  PRIMARY KEY (`id_jadwal`, `id_dosen`),
  CONSTRAINT `fk_jd_jadwal` FOREIGN KEY (`id_jadwal`) REFERENCES `jadwal_mengajar` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_jd_dosen`  FOREIGN KEY (`id_dosen`)  REFERENCES `dosen` (`id`)           ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Tabel mahasiswa (pisah dari users; npm pindah ke sini)
CREATE TABLE IF NOT EXISTS `mahasiswa` (
  `id`       INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`  INT NOT NULL UNIQUE,
  `npm`      VARCHAR(20) NOT NULL UNIQUE,
  `id_kelas` INT NULL,
  `id_prodi` INT NOT NULL,
  CONSTRAINT `fk_mhs_user`  FOREIGN KEY (`user_id`)  REFERENCES `users` (`id`)          ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mhs_kelas` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id`)           ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_mhs_prodi` FOREIGN KEY (`id_prodi`) REFERENCES `program_studi` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

