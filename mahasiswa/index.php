<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Beranda Mahasiswa: Direktori, Pencarian, & Filter Status Dosen
// File: mahasiswa/index.php
// ==============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('mahasiswa');

$pdo = get_db_connection();
$user = current_user();

// Parameter Pencarian & Filter Server-Side (GET)
$q = trim($_GET['q'] ?? '');
$filterProdi = (int)($_GET['prodi'] ?? 0);
$filterStatus = trim($_GET['status'] ?? '');

// Ambil daftar Program Studi untuk pilihan filter
$prodiList = $pdo->query("SELECT * FROM program_studi ORDER BY nama_prodi ASC")->fetchAll();

// Bangun query daftar dosen beserta status dan beban sesi
$sql = "SELECT d.*, u.nama, u.email, p.nama_prodi, p.jurusan, 
               COALESCE(sk.status, 'Tersedia') AS status, 
               sk.catatan AS status_catatan, 
               sk.berlaku_sampai, 
               sk.updated_at,
               (SELECT COUNT(*) FROM jadwal_dosen jd WHERE jd.id_dosen = d.id) AS total_jadwal,
               (SELECT COUNT(*) FROM slot_konsultasi WHERE id_dosen = d.id AND aktif = 1) AS total_slot
        FROM dosen d
        JOIN users u ON d.user_id = u.id
        JOIN program_studi p ON d.id_prodi = p.id
        LEFT JOIN status_ketersediaan sk ON d.id = sk.id_dosen
        WHERE 1=1";

$params = [];

if (!empty($q)) {
    $sql .= " AND (u.nama LIKE :q OR d.nidn_nip LIKE :q OR d.bidang_keahlian LIKE :q OR p.nama_prodi LIKE :q)";
    $params[':q'] = "%{$q}%";
}

if ($filterProdi > 0) {
    $sql .= " AND d.id_prodi = :prodi";
    $params[':prodi'] = $filterProdi;
}

if (!empty($filterStatus)) {
    $sql .= " AND COALESCE(sk.status, 'Tersedia') = :status";
    $params[':status'] = $filterStatus;
}

$sql .= " ORDER BY u.nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dosenList = $stmt->fetchAll();

$page_title = 'Daftar Ketersediaan Dosen';
$active_page = 'beranda';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Banner Informasi Mahasiswa -->
<div class="row mb-4">
    <div class="col-12">
        <div class="hero-banner shadow-sm">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge bg-success-subtle text-success fw-bold px-3 py-1 mb-2">
                        <i class="bi bi-mortarboard-fill me-1"></i>Portal Mahasiswa Polinela
                    </span>
                    <h2 class="fw-bold text-dark mb-2">Smart Lecturer Availability System</h2>
                    <p class="text-secondary mb-0">
                        Pantau jadwal mengajar mingguan, slot konsultasi, dan bimbingan Tugas Akhir dosen Politeknik Negeri Lampung secara transparan dan mandiri.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <div class="p-3 bg-white bg-opacity-75 rounded-3 d-inline-block text-start border shadow-sm">
                        <small class="text-muted d-block"><i class="bi bi-person-badge text-success me-1"></i>Login sebagai Mahasiswa:</small>
                        <strong class="text-dark d-block"><?= e($user['nama']) ?></strong>
                        <span class="badge bg-secondary small">NPM: <?= e($user['npm'] ?? '-') ?></span>
                        <a href="<?= BASE_URL ?>/mahasiswa/jadwal-kelas.php"
                           class="btn btn-sm btn-outline-success mt-2 d-block">
                            <i class="bi bi-calendar-week me-1"></i>Jadwal Kelas Saya
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Panel Pencarian dan Filter -->
<div class="card card-custom border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <form method="GET" action="<?= BASE_URL ?>/mahasiswa/index.php" class="row g-3">
            <!-- Input Pencarian Teks -->
            <div class="col-lg-5 col-md-6">
                <label for="searchLecturerInput" class="form-label small fw-semibold text-secondary">
                    <i class="bi bi-search me-1"></i>Cari Nama Dosen / Bidang Keahlian
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0" id="searchLecturerInput" name="q" 
                           value="<?= e($q) ?>" placeholder="Ketik nama, keahlian, atau NIDN...">
                </div>
            </div>

            <!-- Filter Program Studi -->
            <div class="col-lg-3 col-md-6">
                <label for="filterProdiSelect" class="form-label small fw-semibold text-secondary">
                    <i class="bi bi-building me-1"></i>Program Studi
                </label>
                <select class="form-select" id="filterProdiSelect" name="prodi">
                    <option value="0">Semua Program Studi</option>
                    <?php foreach ($prodiList as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $filterProdi === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= e($p['nama_prodi']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter Status Ketersediaan -->
            <div class="col-lg-2 col-md-6">
                <label for="filterStatusSelect" class="form-label small fw-semibold text-secondary">
                    <i class="bi bi-funnel me-1"></i>Status
                </label>
                <select class="form-select" id="filterStatusSelect" name="status">
                    <option value="">Semua Status</option>
                    <option value="Tersedia" <?= $filterStatus === 'Tersedia' ? 'selected' : '' ?>>Tersedia</option>
                    <option value="Mengajar" <?= $filterStatus === 'Mengajar' ? 'selected' : '' ?>>Mengajar</option>
                    <option value="Hanya Konsultasi" <?= $filterStatus === 'Hanya Konsultasi' ? 'selected' : '' ?>>Hanya Konsultasi</option>
                    <option value="Hanya Bimbingan" <?= $filterStatus === 'Hanya Bimbingan' ? 'selected' : '' ?>>Hanya Bimbingan</option>
                    <option value="Tidak Tersedia" <?= $filterStatus === 'Tidak Tersedia' ? 'selected' : '' ?>>Tidak Tersedia</option>
                </select>
            </div>

            <!-- Tombol Filter & Reset -->
            <div class="col-lg-2 col-md-6 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-polinela flex-fill py-2">
                    <i class="bi bi-filter me-1"></i>Filter
                </button>
                <a href="<?= BASE_URL ?>/mahasiswa/index.php" class="btn btn-outline-secondary py-2" title="Reset Pencarian">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Navigasi Mode Tampilan (Kartu Grid vs Tabel Terstruktur) -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-3 gap-2">
    <div class="text-secondary small">
        <i class="bi bi-people-fill text-success me-1"></i>
        Menampilkan <strong><span id="displayedCount"><?= count($dosenList) ?></span></strong> dosen Polinela
        <?php if (!empty($q) || $filterProdi > 0 || !empty($filterStatus)): ?>
            <span class="badge bg-secondary-subtle text-secondary ms-1">Hasil Terfilter</span>
        <?php endif; ?>
    </div>
    
    <div class="btn-group btn-group-sm" role="group" aria-label="Pilihan Tampilan">
        <button type="button" class="btn btn-outline-success active" id="btnViewCards" onclick="switchView('cards')">
            <i class="bi bi-grid-fill me-1"></i>Tampilan Kartu
        </button>
        <button type="button" class="btn btn-outline-success" id="btnViewTable" onclick="switchView('table')">
            <i class="bi bi-table me-1"></i>Tampilan Tabel
        </button>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODE 1: TAMPILAN KARTU (GRID)                                  -->
<!-- ============================================================== -->
<div class="row g-4 mb-4" id="viewCardsContainer">
    <?php if (empty($dosenList)): ?>
        <div class="col-12" id="noResultsMessage">
            <div class="card card-custom border-0 p-5 text-center shadow-sm">
                <i class="bi bi-search fs-1 text-muted d-block mb-3"></i>
                <h5 class="fw-bold text-secondary">Tidak Ada Dosen yang Sesuai</h5>
                <p class="text-muted small mb-3">Tidak ditemukan dosen yang cocok dengan kata kunci atau filter yang Anda pilih.</p>
                <div>
                    <a href="<?= BASE_URL ?>/mahasiswa/index.php" class="btn btn-outline-polinela btn-sm px-3">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Semua Filter
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="col-12" id="noResultsMessage" style="display: none;">
            <div class="card card-custom border-0 p-5 text-center shadow-sm">
                <i class="bi bi-search fs-1 text-muted d-block mb-3"></i>
                <h5 class="fw-bold text-secondary">Tidak Ada Dosen yang Sesuai</h5>
                <p class="text-muted small mb-0">Coba ubah kata kunci atau hapus filter status.</p>
            </div>
        </div>
        <?php foreach ($dosenList as $d): 
            $statusVal = $d['status'];
            $isExpired = false;
            if (!empty($d['berlaku_sampai']) && strtotime($d['berlaku_sampai']) < time()) {
                $isExpired = true;
            }
        ?>
            <div class="col-md-6 col-lg-4 lecturer-card-item" 
                 data-name="<?= e(strtolower($d['nama'])) ?>" 
                 data-prodi="<?= (int)$d['id_prodi'] ?>" 
                 data-status="<?= e($statusVal) ?>"
                 data-keahlian="<?= e(strtolower($d['bidang_keahlian'])) ?>">
                <div class="card card-custom h-100 border-0 shadow-sm d-flex flex-column">
                    <div class="card-body p-4 d-flex flex-column">
                        <!-- Bagian Header Kartu: Foto & Info Prodi -->
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="d-flex align-items-center gap-3">
                                <?php if (!empty($d['foto']) && file_exists(__DIR__ . '/../' . $d['foto'])): ?>
                                    <img src="<?= BASE_URL . '/' . e($d['foto']) ?>" alt="Foto Dosen" class="rounded-circle shadow-sm" style="width: 58px; height: 58px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="avatar-initial avatar-initial-sm bg-success bg-opacity-10 text-success fw-bold" style="width: 58px; height: 58px; font-size: 1.35rem;">
                                        <?= strtoupper(substr($d['nama'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <span class="badge bg-light text-secondary border small mb-1"><?= e($d['nama_prodi']) ?></span>
                                    <h6 class="fw-bold text-dark mb-0" style="line-height: 1.3;">
                                        <?= e($d['nama']) ?>
                                    </h6>
                                </div>
                            </div>
                        </div>

                        <!-- Status Ketersediaan Saat Ini (Wajib Warna + Teks Jelas) -->
                        <div class="p-2 px-3 rounded-3 bg-light mb-3 border-start border-4 <?= match($statusVal) {
                            'Tersedia' => 'border-success',
                            'Mengajar' => 'border-primary',
                            'Hanya Konsultasi' => 'border-warning',
                            'Hanya Bimbingan' => 'border-info',
                            default => 'border-secondary'
                        } ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small text-muted fw-semibold">Status Saat Ini:</span>
                                <div><?= status_badge($statusVal) ?></div>
                            </div>
                            <?php if (!empty($d['status_catatan'])): ?>
                                <p class="small text-secondary mb-0 mt-2 fst-italic" style="font-size: 0.8rem;">
                                    <i class="bi bi-chat-quote me-1"></i>"<?= e($d['status_catatan']) ?>"
                                </p>
                            <?php endif; ?>
                            <?php if ($isExpired): ?>
                                <div class="badge bg-warning-subtle text-warning-emphasis small mt-1 py-1" style="font-size: 0.72rem;">
                                    <i class="bi bi-clock-history me-1"></i>Waktu berlaku status telah berakhir
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Ringkasan Jadwal Kuliah & Slot Bimbingan Dosen -->
                        <div class="d-flex gap-2 mb-3">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 small">
                                <i class="bi bi-book me-1"></i><?= (int)$d['total_jadwal'] ?> Jadwal Kuliah
                            </span>
                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1 small">
                                <i class="bi bi-clock me-1"></i><?= (int)$d['total_slot'] ?> Slot Bimbingan/Konsul
                            </span>
                        </div>

                        <!-- Keterangan Tambahan: NIDN & Keahlian -->
                        <div class="small text-muted mb-3 flex-grow-1">
                            <div class="mb-1">
                                <i class="bi bi-person-vcard me-1 text-secondary"></i>NIDN: <span class="text-dark fw-medium"><?= e($d['nidn_nip']) ?></span>
                            </div>
                            <div>
                                <i class="bi bi-lightbulb me-1 text-secondary"></i>Keahlian: 
                                <span class="text-dark"><?= e($d['bidang_keahlian']) ?></span>
                            </div>
                        </div>

                        <!-- Tombol Menuju Detail Lengkap Dosen -->
                        <div class="pt-2 border-top mt-auto">
                            <a href="<?= BASE_URL ?>/mahasiswa/detail.php?id=<?= $d['id'] ?>" class="btn btn-polinela w-100 py-2 btn-sm fw-semibold shadow-sm">
                                <i class="bi bi-calendar-check me-1"></i>Buka Jadwal & Slot Ketersediaan
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ============================================================== -->
<!-- MODE 2: TAMPILAN TABEL LENGKAP MAHASISWA                      -->
<!-- ============================================================== -->
<div class="card card-custom border-0 shadow-sm mb-4" id="viewTableContainer" style="display: none;">
    <div class="card-header card-header-polinela py-3">
        <span class="fw-bold"><i class="bi bi-table me-2"></i>Tabel Status Ketersediaan & Jadwal Dosen</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="width: 50px;">No</th>
                        <th>Nama Dosen & NIDN</th>
                        <th>Program Studi</th>
                        <th style="width: 170px;">Status Ketersediaan</th>
                        <th>Catatan Terkini</th>
                        <th style="width: 180px;">Beban Sesi</th>
                        <th class="text-end pe-4" style="width: 170px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dosenList)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">Tidak ada dosen yang cocok dengan pencarian.</td></tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($dosenList as $d): ?>
                            <tr>
                                <td class="ps-4 text-muted small"><?= $no++ ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-initial avatar-initial-sm bg-success bg-opacity-10 text-success fw-bold">
                                            <?= strtoupper(substr($d['nama'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <strong class="text-dark d-block"><?= e($d['nama']) ?></strong>
                                            <small class="text-muted">NIDN: <?= e($d['nidn_nip']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-secondary border"><?= e($d['nama_prodi']) ?></span>
                                </td>
                                <td>
                                    <?= status_badge($d['status']) ?>
                                </td>
                                <td class="small">
                                    <?= !empty($d['status_catatan']) ? e($d['status_catatan']) : '<span class="text-muted fst-italic">Tanpa catatan</span>' ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary me-1"><?= (int)$d['total_jadwal'] ?> Kuliah</span>
                                    <span class="badge bg-info text-dark"><?= (int)$d['total_slot'] ?> Slot</span>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="<?= BASE_URL ?>/mahasiswa/detail.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-polinela">
                                        <i class="bi bi-calendar-check me-1"></i>Lihat Jadwal
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function switchView(mode) {
    const cardView = document.getElementById('viewCardsContainer');
    const tableView = document.getElementById('viewTableContainer');
    const btnCards = document.getElementById('btnViewCards');
    const btnTable = document.getElementById('btnViewTable');

    if (mode === 'table') {
        cardView.style.display = 'none';
        tableView.style.display = 'block';
        btnCards.classList.remove('active');
        btnTable.classList.add('active');
    } else {
        cardView.style.display = 'flex';
        tableView.style.display = 'none';
        btnCards.classList.add('active');
        btnTable.classList.remove('active');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
