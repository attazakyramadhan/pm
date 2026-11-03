<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Berkas Pengarah Utama (Root Router)
// File: index.php
// ==============================================================

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    $user = current_user();
    match ($user['role']) {
        'admin'     => redirect(BASE_URL . '/admin/index.php'),
        'dosen'     => redirect(BASE_URL . '/dosen/index.php'),
        'mahasiswa' => redirect(BASE_URL . '/mahasiswa/index.php'),
        default     => redirect(BASE_URL . '/login.php'),
    };
} else {
    // Sesuai asumsi semua peran login, arahkan pengunjung yang belum login ke login.php
    redirect(BASE_URL . '/login.php');
}

