<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Template Header & Navigasi Responsif
// File: includes/header.php
// ==============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

$user = current_user();
$activePage = $active_page ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? e($page_title) . ' - ' : '' ?>S-LECTURA Politeknik Negeri Lampung</title>
    
    <!-- Bootstrap 5.3 CSS (Lokal Offline + CDN Fallback) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons (Lokal Offline + CDN Fallback) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Kustom Style Polinela -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<!-- Navigasi Bar Utama -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-polinela sticky-top py-2">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>/index.php">
            <span class="p-1 px-2 rounded bg-white text-success fw-bold fs-5 shadow-sm">
                <i class="bi bi-mortarboard-fill"></i>
            </span>
            <div>
                <span class="fw-bold tracking-wide">S-LECTURA</span>
                <span class="d-none d-sm-block text-white-50" style="font-size: 0.72rem; line-height: 1;">Politeknik Negeri Lampung</span>
            </div>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if ($user && $user['role'] === 'mahasiswa'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'beranda' ? 'active' : '' ?>" href="<?= BASE_URL ?>/mahasiswa/index.php">
                            <i class="bi bi-grid-fill me-1"></i>Daftar Dosen & Ketersediaan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'jadwal-kelas' ? 'active' : '' ?>" href="<?= BASE_URL ?>/mahasiswa/jadwal-kelas.php">
                            <i class="bi bi-calendar-week-fill me-1"></i>Jadwal Kelas Saya
                        </a>
                    </li>
                <?php elseif ($user && $user['role'] === 'dosen'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>" href="<?= BASE_URL ?>/dosen/index.php">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'jadwal' ? 'active' : '' ?>" href="<?= BASE_URL ?>/dosen/jadwal.php">
                            <i class="bi bi-calendar3 me-1"></i>Jadwal Mengajar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'slot' ? 'active' : '' ?>" href="<?= BASE_URL ?>/dosen/slot.php">
                            <i class="bi bi-clock-history me-1"></i>Slot Konsultasi & Bimbingan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'profil' ? 'active' : '' ?>" href="<?= BASE_URL ?>/dosen/profil.php">
                            <i class="bi bi-person-lines-fill me-1"></i>Profil Saya
                        </a>
                    </li>
                <?php elseif ($user && $user['role'] === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/index.php">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'users' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/users.php">
                            <i class="bi bi-people-fill me-1"></i>Pengguna
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'dosen' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/dosen.php">
                            <i class="bi bi-person-badge-fill me-1"></i>Data Dosen
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'prodi' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/prodi.php">
                            <i class="bi bi-building-fill me-1"></i>Program Studi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === 'supervisi' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/jadwal-slot.php">
                            <i class="bi bi-calendar2-check-fill me-1"></i>Supervisi Jadwal
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <!-- Area Profil / Tombol Masuk -->
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <?php if ($user): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2 py-1 px-2 rounded bg-white bg-opacity-10" href="#" id="userMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar-initial avatar-initial-sm bg-warning text-dark fw-bold">
                                <?= strtoupper(substr($user['nama'], 0, 1)) ?>
                            </span>
                            <div class="text-start pe-1">
                                <span class="d-block fw-semibold text-white" style="font-size: 0.85rem; max-width: 160px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= e($user['nama']) ?>
                                </span>
                                <span class="badge bg-light text-dark text-capitalize" style="font-size: 0.65rem;">
                                    <?= e($user['role']) ?><?= $user['npm'] ? ' (' . e($user['npm']) . ')' : '' ?>
                                </span>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" aria-labelledby="userMenu">
                            <li><h6 class="dropdown-header">Akun Terautentikasi</h6></li>
                            <li><span class="dropdown-item-text small text-muted"><?= e($user['email']) ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php if ($user['role'] === 'dosen'): ?>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/dosen/profil.php"><i class="bi bi-person me-2"></i>Edit Profil Dosen</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/ganti-password.php"><i class="bi bi-key me-2"></i>Ganti Kata Sandi</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php" data-confirm="Apakah Anda yakin ingin keluar dari sistem?">
                                    <i class="bi bi-box-arrow-right me-2"></i>Keluar (Logout)
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="btn btn-outline-light btn-sm px-3" href="<?= BASE_URL ?>/login.php">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Masuk
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="py-4">
    <div class="container">
        <!-- Render Flash Message Otomatis -->
        <?= display_flash() ?>

