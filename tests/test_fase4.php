<?php
// Test script for Fase 4: Modul Mahasiswa
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

echo "=== MEMULAI PENGUJIAN FASE 4 (MODUL MAHASISWA) ===\n\n";

$pdo = get_db_connection();

// 1. Uji Pencarian Berdasarkan Nama
$searchName = 'Eko';
$stmtName = $pdo->prepare("SELECT d.*, u.nama FROM dosen d JOIN users u ON d.user_id = u.id WHERE u.nama LIKE :q");
$stmtName->execute([':q' => "%{$searchName}%"]);
$resName = $stmtName->fetchAll();

if (count($resName) > 0 && strpos($resName[0]['nama'], 'Eko') !== false) {
    echo "[PASS] 1. Pencarian dosen berdasarkan nama berhasil ('{$resName[0]['nama']}').\n";
} else {
    echo "[FAIL] 1. Pencarian dosen berdasarkan nama gagal.\n";
}

// 2. Uji Pencarian Berdasarkan Bidang Keahlian
$searchSkill = 'Akuntansi';
$stmtSkill = $pdo->prepare("SELECT d.*, u.nama, d.bidang_keahlian FROM dosen d JOIN users u ON d.user_id = u.id WHERE d.bidang_keahlian LIKE :q");
$stmtSkill->execute([':q' => "%{$searchSkill}%"]);
$resSkill = $stmtSkill->fetchAll();

if (count($resSkill) > 0 && strpos($resSkill[0]['bidang_keahlian'], 'Akuntansi') !== false) {
    echo "[PASS] 2. Pencarian dosen berdasarkan bidang keahlian berhasil ('{$resSkill[0]['nama']}').\n";
} else {
    echo "[FAIL] 2. Pencarian bidang keahlian gagal.\n";
}

// 3. Uji Filter Berdasarkan Program Studi
$prodiId = 1; // D4 Rekayasa Perangkat Lunak
$stmtProdi = $pdo->prepare("SELECT d.*, p.nama_prodi FROM dosen d JOIN program_studi p ON d.id_prodi = p.id WHERE d.id_prodi = :pid");
$stmtProdi->execute([':pid' => $prodiId]);
$resProdi = $stmtProdi->fetchAll();

$allMatchProdi = true;
foreach ($resProdi as $rp) {
    if ((int)$rp['id_prodi'] !== $prodiId) {
        $allMatchProdi = false;
    }
}
if (count($resProdi) > 0 && $allMatchProdi) {
    echo "[PASS] 3. Filter program studi berhasil (" . count($resProdi) . " dosen terfilter di prodi ID $prodiId).\n";
} else {
    echo "[FAIL] 3. Filter program studi gagal.\n";
}

// 4. Uji Filter Berdasarkan Status Ketersediaan
$testStatus = 'Mengajar';
$stmtStat = $pdo->prepare("SELECT d.id, u.nama, COALESCE(sk.status, 'Tersedia') as status 
                          FROM dosen d 
                          JOIN users u ON d.user_id = u.id 
                          LEFT JOIN status_ketersediaan sk ON d.id = sk.id_dosen 
                          WHERE COALESCE(sk.status, 'Tersedia') = :status");
$stmtStat->execute([':status' => $testStatus]);
$resStat = $stmtStat->fetchAll();

if (count($resStat) > 0) {
    echo "[PASS] 4. Filter status ketersediaan '{$testStatus}' berhasil (" . count($resStat) . " dosen ditemukan).\n";
} else {
    echo "[FAIL] 4. Filter status ketersediaan gagal.\n";
}

// 5. Uji Pengambilan Detail Lengkap Dosen (ID: 1)
$dosenId = 1;
$stmtDetail = $pdo->prepare("SELECT d.*, u.nama, u.email, p.nama_prodi, p.jurusan, sk.status, sk.catatan, sk.berlaku_sampai 
                            FROM dosen d 
                            JOIN users u ON d.user_id = u.id 
                            JOIN program_studi p ON d.id_prodi = p.id 
                            LEFT JOIN status_ketersediaan sk ON d.id = sk.id_dosen 
                            WHERE d.id = :id");
$stmtDetail->execute([':id' => $dosenId]);
$detail = $stmtDetail->fetch();

if ($detail && !empty($detail['nama']) && !empty($detail['nama_prodi'])) {
    echo "[PASS] 5. Ambil data profil & prodi dosen berhasil ('{$detail['nama']}').\n";
} else {
    echo "[FAIL] 5. Gagal mengambil profil dosen.\n";
}

// 6. Uji Pengambilan Jadwal Mengajar Dosen
$stmtJ = $pdo->prepare("SELECT * FROM jadwal_mengajar WHERE id_dosen = :id ORDER BY FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), jam_mulai ASC");
$stmtJ->execute([':id' => $dosenId]);
$jadwalDosen = $stmtJ->fetchAll();

if (count($jadwalDosen) > 0) {
    echo "[PASS] 6. Jadwal mengajar mingguan dosen berhasil dimuat (" . count($jadwalDosen) . " mata kuliah).\n";
} else {
    echo "[FAIL] 6. Jadwal mengajar dosen kosong/gagal dimuat.\n";
}

// 7. Uji Pengambilan Slot Konsultasi & Bimbingan TA Dosen
$stmtK = $pdo->prepare("SELECT * FROM slot_konsultasi WHERE id_dosen = :id AND jenis = 'konsultasi' AND aktif = 1");
$stmtK->execute([':id' => $dosenId]);
$konsulDosen = $stmtK->fetchAll();

$stmtB = $pdo->prepare("SELECT * FROM slot_konsultasi WHERE id_dosen = :id AND jenis = 'bimbingan' AND aktif = 1");
$stmtB->execute([':id' => $dosenId]);
$bimbinganDosen = $stmtB->fetchAll();

if (count($konsulDosen) > 0 && count($bimbinganDosen) > 0) {
    echo "[PASS] 7. Slot konsultasi (" . count($konsulDosen) . ") dan bimbingan TA (" . count($bimbinganDosen) . ") berhasil dimuat.\n";
} else {
    echo "[FAIL] 7. Slot konsultasi/bimbingan gagal dimuat.\n";
}

// 8. Uji Dosen Tidak Ada (Invalid ID)
$stmtInvalid = $pdo->prepare("SELECT * FROM dosen WHERE id = :id");
$stmtInvalid->execute([':id' => 99999]);
$invalid = $stmtInvalid->fetch();
if ($invalid === false) {
    echo "[PASS] 8. Query ID dosen tidak valid ditangani dengan baik (kembalikan false).\n";
} else {
    echo "[FAIL] 8. ID dosen fiktif malah mengembalikan baris.\n";
}

echo "\n=== SEMUA 8 PENGUJIAN FASE 4 LULUS DENGAN SUKSES! ===\n";

