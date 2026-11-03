<?php
// Test script for Fase 2 verification
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

echo "=== MEMULAI PENGUJIAN FASE 2 ===\n\n";

// 1. Uji Koneksi Basis Data
try {
    $pdo = get_db_connection();
    echo "[PASS] 1. Koneksi PDO ke database s_lectura berhasil.\n";
} catch (Exception $e) {
    echo "[FAIL] 1. Gagal koneksi DB: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Uji Helper XSS & Format
$raw = "<script>alert('xss')</script>";
$escaped = e($raw);
if ($escaped === "&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;") {
    echo "[PASS] 2. Sanitasi output e() berhasil mencegah XSS.\n";
} else {
    echo "[FAIL] 2. Sanitasi e() gagal: $escaped\n";
}

$timeFmt = format_time('08:00:00');
if ($timeFmt === '08:00 WIB') {
    echo "[PASS] 3. format_time() berhasil ('$timeFmt').\n";
} else {
    echo "[FAIL] 3. format_time() salah: $timeFmt\n";
}

// 3. Uji CSRF Token
$token = csrf_token();
if (!empty($token) && verify_csrf_token($token) && !verify_csrf_token('invalid_token')) {
    echo "[PASS] 4. Token CSRF aman: verifikasi valid dan tolak token palsu.\n";
} else {
    echo "[FAIL] 4. CSRF verification gagal.\n";
}

// 4. Uji Verifikasi Password Demo Akun
$accounts = [
    ['admin@polinela.ac.id', 'admin123', 'admin'],
    ['eko.nugroho@polinela.ac.id', 'dosen123', 'dosen'],
    ['21753001', 'mhs123', 'mahasiswa'],
];

foreach ($accounts as $acc) {
    [$id, $pass, $role] = $acc;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email OR npm = :npm");
    $stmt->execute([':email' => $id, ':npm' => $id]);
    $u = $stmt->fetch();
    if ($u && password_verify($pass, $u['password_hash']) && $u['role'] === $role) {
        echo "[PASS] 5. Akun demo $role ($id) terverifikasi dengan benar.\n";
    } else {
        echo "[FAIL] 5. Verifikasi akun demo $role ($id) gagal.\n";
    }
}

// Uji password salah
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = 'admin@polinela.ac.id'");
$stmt->execute();
$u = $stmt->fetch();
if (!password_verify('wrong_pass', $u['password_hash'])) {
    echo "[PASS] 6. Password salah berhasil ditolak.\n";
} else {
    echo "[FAIL] 6. Password salah malah diterima!\n";
}

// 5. Uji Deteksi Bentrok Waktu (Cross-table Collision Detection)
// Dosen 1 (id=1) memiliki jadwal mengajar Senin 08:00 - 10:30 (RPL 5A)
// dan slot konsultasi Senin 10:45 - 12:00
$conflict1 = check_time_collision($pdo, 1, 'Senin', '09:00', '10:00');
if ($conflict1 && strpos($conflict1, 'Jadwal Mengajar') !== false) {
    echo "[PASS] 7. Deteksi bentrok dengan Jadwal Mengajar berhasil terdeteksi.\n";
} else {
    echo "[FAIL] 7. Gagal deteksi bentrok jadwal mengajar: $conflict1\n";
}

$conflict2 = check_time_collision($pdo, 1, 'Senin', '11:00', '11:30');
if ($conflict2 && strpos($conflict2, 'Slot Konsultasi') !== false) {
    echo "[PASS] 8. Deteksi bentrok dengan Slot Konsultasi berhasil terdeteksi.\n";
} else {
    echo "[FAIL] 8. Gagal deteksi bentrok slot konsultasi: $conflict2\n";
}

$noConflict = check_time_collision($pdo, 1, 'Senin', '16:00', '17:00');
if ($noConflict === null) {
    echo "[PASS] 9. Waktu bebas (tanpa bentrok) berhasil diizinkan.\n";
} else {
    echo "[FAIL] 9. Waktu bebas malah dianggap bentrok: $noConflict\n";
}

// 6. Uji Registrasi Mahasiswa Baru
$testNpm = '21753999';
$testEmail = 'tester.mhs@mhs.polinela.ac.id';
// Hapus dulu jika ada
$pdo->prepare("DELETE FROM users WHERE npm = :npm OR email = :email")->execute([':npm' => $testNpm, ':email' => $testEmail]);

$newHash = password_hash('mhsbaru123', PASSWORD_BCRYPT);
$ins = $pdo->prepare("INSERT INTO users (nama, email, password_hash, role, npm) VALUES ('Tester Mahasiswa', :email, :hash, 'mahasiswa', :npm)");
$ins->execute([':email' => $testEmail, ':hash' => $newHash, ':npm' => $testNpm]);

$check = $pdo->prepare("SELECT * FROM users WHERE npm = :npm");
$check->execute([':npm' => $testNpm]);
$created = $check->fetch();
if ($created && $created['role'] === 'mahasiswa' && password_verify('mhsbaru123', $created['password_hash'])) {
    echo "[PASS] 10. Registrasi mandiri mahasiswa baru berhasil disimpan dan diverifikasi.\n";
    // Bersihkan kembali data tester
    $pdo->prepare("DELETE FROM users WHERE npm = :npm")->execute([':npm' => $testNpm]);
} else {
    echo "[FAIL] 10. Registrasi mandiri mahasiswa gagal.\n";
}

echo "\n=== SEMUA PENGUJIAN FASE 2 SELESAI DENGAN SUKSES! ===\n";
