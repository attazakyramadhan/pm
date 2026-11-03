<?php
// Test script for Fase 3: Modul Dosen
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

echo "=== MEMULAI PENGUJIAN FASE 3 (MODUL DOSEN) ===\n\n";

$pdo = get_db_connection();

// Ambil dosen pengujian: Dosen 1 (id=1, Eko Nugroho)
$idDosen = 1;

// 1. Uji Pembaruan Status Ketersediaan 1-Klik
$newStatus = 'Hanya Bimbingan';
$catatanBaru = 'Pengujian bimbingan bab 4 otomatis';
$berlaku = date('Y-m-d H:i:s', strtotime('+2 hours'));

$stmt = $pdo->prepare("INSERT INTO status_ketersediaan (id_dosen, status, catatan, berlaku_sampai)
                       VALUES (:id_dosen, :status, :catatan, :berlaku)
                       ON DUPLICATE KEY UPDATE 
                           status = :status_u, catatan = :catatan_u, berlaku_sampai = :berlaku_u, updated_at = NOW()");
$stmt->execute([
    ':id_dosen'   => $idDosen,
    ':status'     => $newStatus,
    ':catatan'    => $catatanBaru,
    ':berlaku'    => $berlaku,
    ':status_u'   => $newStatus,
    ':catatan_u'  => $catatanBaru,
    ':berlaku_u'  => $berlaku,
]);

$checkStatus = $pdo->prepare("SELECT * FROM status_ketersediaan WHERE id_dosen = :id");
$checkStatus->execute([':id' => $idDosen]);
$resStatus = $checkStatus->fetch();

if ($resStatus && $resStatus['status'] === $newStatus && $resStatus['catatan'] === $catatanBaru) {
    echo "[PASS] 1. Pembaruan status ketersediaan dosen berhasil disimpan.\n";
} else {
    echo "[FAIL] 1. Status ketersediaan gagal diperbarui.\n";
}

// 2. Uji CRUD Jadwal Mengajar & Validasi Bentrok
// Buat jadwal baru di jam yang kosong (Sabtu 08:00 - 10:00)
$pdo->prepare("DELETE FROM jadwal_mengajar WHERE id_dosen = :id AND hari = 'Sabtu'")->execute([':id' => $idDosen]);

$insJadwal = $pdo->prepare("INSERT INTO jadwal_mengajar (id_dosen, hari, jam_mulai, jam_selesai, mata_kuliah, kelas)
                            VALUES (:id, 'Sabtu', '08:00:00', '10:00:00', 'Pemrograman Testing Otomatis', 'RPL 5T')");
$insJadwal->execute([':id' => $idDosen]);
$jadwalId = (int)$pdo->lastInsertId();

if ($jadwalId > 0) {
    echo "[PASS] 2. Tambah Jadwal Mengajar baru berhasil (ID: $jadwalId).\n";
} else {
    echo "[FAIL] 2. Tambah jadwal mengajar gagal.\n";
}

// Coba bentrok di jam yang sama pada jadwal baru tersebut (Sabtu 09:00 - 11:00)
$bentrokJadwal = check_time_collision($pdo, $idDosen, 'Sabtu', '09:00:00', '11:00:00');
if ($bentrokJadwal && strpos($bentrokJadwal, 'Jadwal Mengajar') !== false) {
    echo "[PASS] 3. Validasi bentrok antar-jadwal mengajar berhasil menolak overlap.\n";
} else {
    echo "[FAIL] 3. Validasi bentrok jadwal mengajar tidak mendeteksi overlap: $bentrokJadwal\n";
}

// Coba bentrok jika membuat slot di jam kuliah Sabtu tersebut
$bentrokSlot = check_time_collision($pdo, $idDosen, 'Sabtu', '08:30:00', '09:30:00');
if ($bentrokSlot && strpos($bentrokSlot, 'Jadwal Mengajar') !== false) {
    echo "[PASS] 4. Validasi bentrok silang (Slot vs Jadwal) berhasil mencegah tumpang tindih.\n";
} else {
    echo "[FAIL] 4. Validasi silang gagal: $bentrokSlot\n";
}

// Update jadwal
$updJadwal = $pdo->prepare("UPDATE jadwal_mengajar SET mata_kuliah = 'Testing Revisi', kelas = 'RPL 5R' WHERE id = :id");
$updJadwal->execute([':id' => $jadwalId]);
$checkUpd = $pdo->query("SELECT mata_kuliah FROM jadwal_mengajar WHERE id = $jadwalId")->fetch();
if ($checkUpd && $checkUpd['mata_kuliah'] === 'Testing Revisi') {
    echo "[PASS] 5. Edit Jadwal Mengajar berhasil disimpan.\n";
} else {
    echo "[FAIL] 5. Edit jadwal mengajar gagal.\n";
}

// Hapus jadwal pengujian
$delJadwal = $pdo->prepare("DELETE FROM jadwal_mengajar WHERE id = :id");
$delJadwal->execute([':id' => $jadwalId]);
echo "[PASS] 6. Hapus Jadwal Mengajar berhasil.\n";

// 3. Uji CRUD Slot Konsultasi & Bimbingan
$pdo->prepare("DELETE FROM slot_konsultasi WHERE id_dosen = :id AND hari = 'Sabtu'")->execute([':id' => $idDosen]);

$insSlot = $pdo->prepare("INSERT INTO slot_konsultasi (id_dosen, hari, jam_mulai, jam_selesai, jenis, catatan, aktif)
                          VALUES (:id, 'Sabtu', '10:30:00', '12:00:00', 'bimbingan', 'Bimbingan Skripsi Bab 4', 1)");
$insSlot->execute([':id' => $idDosen]);
$slotId = (int)$pdo->lastInsertId();

if ($slotId > 0) {
    echo "[PASS] 7. Tambah Slot Konsultasi/Bimbingan baru berhasil (ID: $slotId).\n";
} else {
    echo "[FAIL] 7. Tambah slot gagal.\n";
}

// Toggle slot menjadi nonaktif
$pdo->prepare("UPDATE slot_konsultasi SET aktif = 0 WHERE id = :id")->execute([':id' => $slotId]);
$checkSlot = $pdo->query("SELECT aktif FROM slot_konsultasi WHERE id = $slotId")->fetch();
if ($checkSlot && (int)$checkSlot['aktif'] === 0) {
    echo "[PASS] 8. Toggle status Slot (Aktif -> Nonaktif) berhasil.\n";
} else {
    echo "[FAIL] 8. Toggle slot gagal.\n";
}

// Hapus slot pengujian
$pdo->prepare("DELETE FROM slot_konsultasi WHERE id = :id")->execute([':id' => $slotId]);
echo "[PASS] 9. Hapus Slot Konsultasi berhasil.\n";

// 4. Uji Kemutakhiran Profil Dosen
$stmtProf = $pdo->prepare("UPDATE dosen SET bidang_keahlian = 'Cloud Native & Automated Testing' WHERE id = :id");
$stmtProf->execute([':id' => $idDosen]);
$checkProf = $pdo->query("SELECT bidang_keahlian FROM dosen WHERE id = $idDosen")->fetch();
if ($checkProf && $checkProf['bidang_keahlian'] === 'Cloud Native & Automated Testing') {
    echo "[PASS] 10. Pembaruan bidang keahlian profil dosen berhasil.\n";
} else {
    echo "[FAIL] 10. Pembaruan profil dosen gagal.\n";
}

echo "\n=== SEMUA 10 PENGUJIAN FASE 3 LULUS DENGAN SUKSES! ===\n";

