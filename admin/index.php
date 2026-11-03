<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Dashboard Administrator Kampus & Ringkasan Metrik
// File: admin/index.php
// ==============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pdo = get_db_connection();
$user = current_user();

// 1. Ambil Metrik Ringkasan Sistem
$countDosen = (int)$pdo->query("SELECT COUNT(*) FROM dosen")->fetchColumn();
$countMhs = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'mahasiswa'")->fetchColumn();
$countSlotAktif = (int)$pdo->query("SELECT COUNT(*) FROM slot_konsultasi WHERE aktif = 1")->fetchColumn();
$countJadwal = (int)$pdo->query("SELECT COUNT(*) FROM jadwal_mengajar")->fetchColumn();
$countProdi = (int)$pdo->query("SELECT COUNT(*) FROM program_studi")->fetchColumn();

// 2. Ambil Status Ketersediaan Seluruh Dosen
$sqlDosenStatus = "SELECT d.id AS id_dosen, d.nidn_nip, u.nama, p.nama_prodi,
                          COALESCE(sk.status, 'Tersedia') AS status,
                          sk.catatan,
                          sk.berlaku_sampai,
                          sk.updated_at
                   FROM dosen d
                   JOIN users u ON d.user_id = u.id
                   JOIN program_studi p ON d.id_prodi = p.id
                   LEFT JOIN status_ketersediaan sk ON d.id = sk.id_dosen
                   ORDER BY u.nama ASC";
$dosenStatusList = $pdo->query($sqlDosenStatus)->fetchAll();

$page_title = 'Dashboard Administrator';
$active_page = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Banner Sambutan Administrator -->
<div class="row mb-4">
    <div class="col-12">
        <div class="hero-banner shadow-sm">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div>
                    <span class="badge bg-success-subtle text-success fw-bold px-3 py-1 mb-2">
                        <i class="bi bi-shield-lock-fill me-1"></i>Panel Administrator S-LECTURA
                    </span>
                    <h2 class="fw-bold text-dark mb-1">Pusat Kendali Sistem Politeknik Negeri Lampung</h2>
                    <p class="text-secondary mb-0">
                        Selamat datang, <strong><?= e($user['nama']) ?></strong>. Pantau metrik ketersediaan dosen, jadwal mengajar, dan data master pengguna.
                    </p>
                </div>
                <div class="text-md-end">
                    <span class="badge bg-light text-dark border p-2 px-3 small">
                        <i class="bi bi-calendar3 me-1 text-success"></i><?= date('l, d F Y') ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Kartu Metrik Ringkasan (Stat Cards) -->
<div class="row g-3 mb-4">
    <!-- Total Dosen -->
    <div class="col-sm-6 col-xl">
        <div class="card stat-card bg-success shadow-sm h-100">
            <div class="card-body">
                <span class="fw-semibold text-white-50 text-uppercase small">Total Dosen</span>
                <h2 class="fw-bold text-white mb-0"><?= $countDosen ?></h2>
                <div class="mt-2">
                    <a href="<?= BASE_URL ?>/admin/dosen.php" class="text-white text-decoration-none small fw-semibold">
                        Kelola Dosen <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            <i class="bi bi-person-video3 stat-icon"></i>
        </div>
    </div>

    <!-- Total Mahasiswa -->
    <div class="col-sm-6 col-xl">
        <div class="card stat-card bg-primary shadow-sm h-100">
            <div class="card-body">
                <span class="fw-semibold text-white-50 text-uppercase small">Mahasiswa Terdaftar</span>
                <h2 class="fw-bold text-white mb-0"><?= $countMhs ?></h2>
                <div class="mt-2">
                    <a href="<?= BASE_URL ?>/admin/users.php" class="text-white text-decoration-none small fw-semibold">
                        Kelola Pengguna <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            <i class="bi bi-people-fill stat-icon"></i>
        </div>
    </div>

    <!-- Total Slot Aktif -->
    <div class="col-sm-6 col-xl">
        <div class="card stat-card bg-info shadow-sm h-100">
            <div class="card-body">
                <span class="fw-semibold text-white-50 text-uppercase small">Slot Bimbingan/Konsul</span>
                <h2 class="fw-bold text-white mb-0"><?= $countSlotAktif ?></h2>
                <div class="mt-2">
                    <a href="<?= BASE_URL ?>/admin/jadwal-slot.php" class="text-white text-decoration-none small fw-semibold">
                        Supervisi Slot <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            <i class="bi bi-clock-history stat-icon"></i>
        </div>
    </div>

    <!-- Total Jadwal Mengajar -->
    <div class="col-sm-6 col-xl">
        <div class="card stat-card bg-warning text-dark shadow-sm h-100">
            <div class="card-body">
                <span class="fw-semibold text-dark text-opacity-75 text-uppercase small">Jadwal Kuliah</span>
                <h2 class="fw-bold text-dark mb-0"><?= $countJadwal ?></h2>
                <div class="mt-2">
                    <a href="<?= BASE_URL ?>/admin/jadwal-slot.php" class="text-dark text-decoration-none small fw-bold">
                        Supervisi Jadwal <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            <i class="bi bi-calendar-week stat-icon text-dark"></i>
        </div>
    </div>

    <!-- Total Program Studi -->
    <div class="col-sm-6 col-xl">
        <div class="card stat-card bg-secondary shadow-sm h-100">
            <div class="card-body">
                <span class="fw-semibold text-white-50 text-uppercase small">Program Studi</span>
                <h2 class="fw-bold text-white mb-0"><?= $countProdi ?></h2>
                <div class="mt-2">
                    <a href="<?= BASE_URL ?>/admin/prodi.php" class="text-white text-decoration-none small fw-semibold">
                        Kelola Prodi <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            <i class="bi bi-building-fill stat-icon"></i>
        </div>
    </div>
</div>

<!-- Ringkasan Status Ketersediaan Seluruh Dosen -->
<div class="card card-custom border-0 shadow-sm mb-4">
    <div class="card-header card-header-polinela d-flex justify-content-between align-items-center py-3">
        <span class="fw-bold"><i class="bi bi-broadcast me-2"></i>Status Ketersediaan Seluruh Dosen Polinela</span>
        <a href="<?= BASE_URL ?>/admin/jadwal-slot.php" class="btn btn-sm btn-outline-success">
            <i class="bi bi-gear-fill me-1"></i>Supervisi Jadwal & Slot
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="width: 50px;">No</th>
                        <th>Nama Dosen</th>
                        <th>Program Studi</th>
                        <th style="width: 170px;">Status Terkini</th>
                        <th>Catatan / Keterangan</th>
                        <th style="width: 180px;">Terakhir Diperbarui</th>
                        <th class="text-end pe-4" style="width: 120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($dosenStatusList as $ds): ?>
                        <tr>
                            <td class="ps-4 text-muted small"><?= $no++ ?></td>
                            <td>
                                <strong class="text-dark d-block"><?= e($ds['nama']) ?></strong>
                                <small class="text-muted">NIDN: <?= e($ds['nidn_nip']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-secondary border"><?= e($ds['nama_prodi']) ?></span>
                            </td>
                            <td>
                                <?= status_badge($ds['status']) ?>
                            </td>
                            <td class="small">
                                <?= !empty($ds['catatan']) ? e($ds['catatan']) : '<span class="text-muted fst-italic">Tanpa catatan</span>' ?>
                            </td>
                            <td class="small text-muted">
                                <?= $ds['updated_at'] ? format_datetime($ds['updated_at']) : '-' ?>
                            </td>
                            <td class="text-end pe-4">
                                <a href="<?= BASE_URL ?>/admin/jadwal-slot.php?id_dosen=<?= $ds['id_dosen'] ?>" class="btn btn-sm btn-outline-polinela" title="Kelola Jadwal & Status Dosen Ini">
                                    <i class="bi bi-pencil-square me-1"></i>Kelola
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Navigasi Menu Cepat Admin -->
<div class="row g-3">
    <div class="col-md-4">
        <div class="card card-custom border-0 shadow-sm p-3">
            <div class="d-flex align-items-center gap-3">
                <span class="p-3 rounded-circle bg-primary bg-opacity-10 text-primary fs-3">
                    <i class="bi bi-person-gear"></i>
                </span>
                <div>
                    <h6 class="fw-bold text-dark mb-1">Manajemen Pengguna</h6>
                    <p class="small text-muted mb-2">Tambah admin, dosen, mahasiswa, reset sandi.</p>
                    <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-sm btn-outline-primary">Buka Pengguna</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-custom border-0 shadow-sm p-3">
            <div class="d-flex align-items-center gap-3">
                <span class="p-3 rounded-circle bg-success bg-opacity-10 text-success fs-3">
                    <i class="bi bi-person-badge"></i>
                </span>
                <div>
                    <h6 class="fw-bold text-dark mb-1">Master Data Dosen</h6>
                    <p class="small text-muted mb-2">Penugasan NIDN, prodi, dan keahlian.</p>
                    <a href="<?= BASE_URL ?>/admin/dosen.php" class="btn btn-sm btn-outline-success">Buka Master Dosen</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-custom border-0 shadow-sm p-3">
            <div class="d-flex align-items-center gap-3">
                <span class="p-3 rounded-circle bg-warning bg-opacity-10 text-warning fs-3">
                    <i class="bi bi-building"></i>
                </span>
                <div>
                    <h6 class="fw-bold text-dark mb-1">Master Program Studi</h6>
                    <p class="small text-muted mb-2">Kelola nama prodi dan jurusan kampus.</p>
                    <a href="<?= BASE_URL ?>/admin/prodi.php" class="btn btn-sm btn-outline-warning text-dark">Buka Prodi</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
