<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Handler Pembaruan Cepat Status Ketersediaan Dosen
// File: dosen/status-update.php
// ==============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('dosen');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/dosen/index.php');
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    set_flash('danger', 'Token keamanan CSRF tidak valid. Silakan coba lagi.');
    redirect(BASE_URL . '/dosen/index.php');
}

$pdo = get_db_connection();
$idDosen = $_SESSION['dosen_id'] ?? null;

if (!$idDosen) {
    $stmt = $pdo->prepare("SELECT id FROM dosen WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $_SESSION['user_id']]);
    $d = $stmt->fetch();
    if ($d) {
        $idDosen = (int)$d['id'];
        $_SESSION['dosen_id'] = $idDosen;
    } else {
        set_flash('danger', 'Data profil dosen tidak ditemukan. Hubungi administrator kampus.');
        redirect(BASE_URL . '/login.php');
    }
}

$validStatuses = ['Tersedia', 'Mengajar', 'Hanya Konsultasi', 'Hanya Bimbingan', 'Tidak Tersedia'];
$status = trim($_POST['status'] ?? '');
$catatan = trim($_POST['catatan'] ?? '');
$berlakuSampaiRaw = trim($_POST['berlaku_sampai'] ?? '');

if (!in_array($status, $validStatuses, true)) {
    set_flash('danger', 'Pilihan status ketersediaan tidak valid.');
    redirect(BASE_URL . '/dosen/index.php');
}

$berlakuSampai = null;
if (!empty($berlakuSampaiRaw)) {
    $ts = strtotime($berlakuSampaiRaw);
    if ($ts) {
        $berlakuSampai = date('Y-m-d H:i:s', $ts);
    }
}

// Potong catatan jika lebih dari 255 karakter
if (mb_strlen($catatan) > 255) {
    $catatan = mb_substr($catatan, 0, 255);
}

try {
    $sql = "INSERT INTO status_ketersediaan (id_dosen, status, catatan, berlaku_sampai)
            VALUES (:id_dosen, :status, :catatan, :berlaku_sampai)
            ON DUPLICATE KEY UPDATE 
                status = :status_update, 
                catatan = :catatan_update, 
                berlaku_sampai = :berlaku_sampai_update, 
                updated_at = NOW()";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_dosen'               => $idDosen,
        ':status'                 => $status,
        ':catatan'                => !empty($catatan) ? $catatan : null,
        ':berlaku_sampai'         => $berlakuSampai,
        ':status_update'          => $status,
        ':catatan_update'         => !empty($catatan) ? $catatan : null,
        ':berlaku_sampai_update'  => $berlakuSampai,
    ]);

    set_flash('success', "Status ketersediaan berhasil diperbarui menjadi: <strong>{$status}</strong>");
} catch (PDOException $e) {
    set_flash('danger', 'Gagal memperbarui status ketersediaan: Terjadi gangguan pada database.');
}

redirect(BASE_URL . '/dosen/index.php');

