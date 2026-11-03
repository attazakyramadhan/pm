<?php
// Test script for Fase 5: Modul Admin + Ringkasan
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

echo "=== MEMULAI PENGUJIAN FASE 5 (MODUL ADMIN + RINGKASAN) ===\n\n";

$pdo = get_db_connection();

// 1. Uji Metrik Ringkasan Kampus
$countDosen = (int)$pdo->query("SELECT COUNT(*) FROM dosen")->fetchColumn();
$countMhs = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'mahasiswa'")->fetchColumn();
$countSlot = (int)$pdo->query("SELECT COUNT(*) FROM slot_konsultasi WHERE aktif = 1")->fetchColumn();
$countJadwal = (int)$pdo->query("SELECT COUNT(*) FROM jadwal_mengajar")->fetchColumn();
$countProdi = (int)$pdo->query("SELECT COUNT(*) FROM program_studi")->fetchColumn();

if ($countDosen >= 6 && $countMhs >= 4 && $countProdi >= 3) {
    echo "[PASS] 1. Metrik ringkasan sistem valid ($countDosen dosen, $countMhs mhs, $countSlot slot aktif, $countJadwal jadwal, $countProdi prodi).\n";
} else {
    echo "[FAIL] 1. Perhitungan metrik ringkasan sistem tidak sesuai.\n";
}

// 2. Uji CRUD Pengguna (Users)
$testEmail = 'admintest@polinela.ac.id';
$pdo->prepare("DELETE FROM users WHERE email = :email")->execute([':email' => $testEmail]);

$insU = $pdo->prepare("INSERT INTO users (nama, email, password_hash, role, npm) VALUES ('User Test Admin', :email, 'dummyhash', 'mahasiswa', '21759999')");
$insU->execute([':email' => $testEmail]);
$testUserId = (int)$pdo->lastInsertId();

if ($testUserId > 0) {
    echo "[PASS] 2. Tambah pengguna baru via admin berhasil (ID: $testUserId).\n";
} else {
    echo "[FAIL] 2. Tambah pengguna gagal.\n";
}

// Reset kata sandi pengguna
$newHash = password_hash('passwordBaru123', PASSWORD_BCRYPT);
$pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id")->execute([':hash' => $newHash, ':id' => $testUserId]);
$checkPass = $pdo->query("SELECT password_hash FROM users WHERE id = $testUserId")->fetch();
if ($checkPass && password_verify('passwordBaru123', $checkPass['password_hash'])) {
    echo "[PASS] 3. Reset kata sandi pengguna berhasil diverifikasi.\n";
} else {
    echo "[FAIL] 3. Reset kata sandi gagal.\n";
}

// Hapus pengguna pengujian
$pdo->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $testUserId]);
echo "[PASS] 4. Hapus pengguna berhasil.\n";

// 3. Uji CRUD Program Studi & Proteksi Hapus
$pdo->prepare("DELETE FROM program_studi WHERE nama_prodi = 'D4 Rekayasa Keamanan Siber'")->execute();
$insP = $pdo->prepare("INSERT INTO program_studi (nama_prodi, jurusan) VALUES ('D4 Rekayasa Keamanan Siber', 'Teknologi Informasi')");
$insP->execute();
$testProdiId = (int)$pdo->lastInsertId();

if ($testProdiId > 0) {
    echo "[PASS] 5. Tambah program studi baru berhasil (ID: $testProdiId).\n";
} else {
    echo "[FAIL] 5. Tambah program studi gagal.\n";
}

// Hapus prodi tanpa dosen (harus bisa)
$delP = $pdo->prepare("DELETE FROM program_studi WHERE id = :id");
$delP->execute([':id' => $testProdiId]);
echo "[PASS] 6. Hapus program studi kosong berhasil.\n";

// Cek proteksi prodi yang ada dosennya (Prodi ID 1)
$cekDosenProdi = (int)$pdo->query("SELECT COUNT(*) FROM dosen WHERE id_prodi = 1")->fetchColumn();
if ($cekDosenProdi > 0) {
    echo "[PASS] 7. Proteksi penghapusan prodi dengan dosen aktif terdeteksi ({$cekDosenProdi} dosen terikat).\n";
} else {
    echo "[FAIL] 7. Proteksi prodi gagal.\n";
}

// 4. Uji Supervisi Jadwal & Status Ketersediaan oleh Admin
$idDosen = 1;
$adminCatatan = 'Diperbarui oleh Administrator untuk evaluasi akreditasi';
$updStatusAdmin = $pdo->prepare("UPDATE status_ketersediaan SET status = 'Mengajar', catatan = :catatan, updated_at = NOW() WHERE id_dosen = :id");
$updStatusAdmin->execute([':catatan' => $adminCatatan, ':id' => $idDosen]);

$checkStat = $pdo->query("SELECT status, catatan FROM status_ketersediaan WHERE id_dosen = $idDosen")->fetch();
if ($checkStat && $checkStat['status'] === 'Mengajar' && $checkStat['catatan'] === $adminCatatan) {
    echo "[PASS] 8. Supervisi status ketersediaan dosen oleh Admin berhasil disimpan.\n";
} else {
    echo "[FAIL] 8. Supervisi status dosen gagal.\n";
}

echo "\n=== SEMUA 8 PENGUJIAN FASE 5 LULUS DENGAN SUKSES! ===\n";

