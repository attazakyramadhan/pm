<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Dashboard Dosen & Pengelolaan Status Ketersediaan
// File: dosen/index.php
// ==============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('dosen');

$pdo = get_db_connection();
$user = current_user();

// Pastikan data dosen tersedia
$stmt = $pdo->prepare("SELECT d.*, p.nama_prodi, p.jurusan 
                       FROM dosen d 
                       JOIN program_studi p ON d.id_prodi = p.id 
                       WHERE d.user_id = :uid LIMIT 1");
$stmt->execute([':uid' => $user['id']]);
$dosen = $stmt->fetch();

if (!$dosen) {
    die("Data dosen belum terdaftar secara lengkap. Silakan hubungi Administrator Polinela.");
}
$idDosen = (int)$dosen['id'];
$_SESSION['dosen_id'] = $idDosen;

// Ambil status ketersediaan saat ini
$stmtStatus = $pdo->prepare("SELECT * FROM status_ketersediaan WHERE id_dosen = :id LIMIT 1");
$stmtStatus->execute([':id' => $idDosen]);
$currentStatus = $stmtStatus->fetch();

$statusValue = $currentStatus['status'] ?? 'Tersedia';
$statusCatatan = $currentStatus['catatan'] ?? '';
$berlakuSampai = $currentStatus['berlaku_sampai'] ?? null;
$updatedAt = $currentStatus['updated_at'] ?? null;

// Cek apakah status sudah kedaluwarsa
$isExpired = false;
if ($berlakuSampai && strtotime($berlakuSampai) < time()) {
    $isExpired = true;
}

// Statistik jadwal & slot
$stmtCountJadwal = $pdo->prepare("SELECT COUNT(DISTINCT id_jadwal) FROM jadwal_dosen WHERE id_dosen = :id");
$stmtCountJadwal->execute([':id' => $idDosen]);
$totalJadwal = (int)$stmtCountJadwal->fetchColumn();

$stmtCountKonsul = $pdo->prepare("SELECT COUNT(*) FROM slot_konsultasi WHERE id_dosen = :id AND jenis = 'konsultasi' AND aktif = 1");
$stmtCountKonsul->execute([':id' => $idDosen]);
$totalKonsul = (int)$stmtCountKonsul->fetchColumn();

$stmtCountBimbingan = $pdo->prepare("SELECT COUNT(*) FROM slot_konsultasi WHERE id_dosen = :id AND jenis = 'bimbingan' AND aktif = 1");
$stmtCountBimbingan->execute([':id' => $idDosen]);
$totalBimbingan = (int)$stmtCountBimbingan->fetchColumn();

// Jadwal Hari Ini (via jadwal_dosen)
$hariMapping = [
    1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'
];
$hariIni = $hariMapping[(int)date('N')];

$stmtHariIniJadwal = $pdo->prepare(
    "SELECT jm.*, mk.kode AS kode_mk, mk.nama AS nama_mk, k.nama_kelas
     FROM jadwal_mengajar jm
     JOIN mata_kuliah mk ON mk.id = jm.id_mk
     JOIN kelas k ON k.id = jm.id_kelas
     WHERE jm.id IN (SELECT id_jadwal FROM jadwal_dosen WHERE id_dosen = :id)
       AND jm.hari = :hari
     ORDER BY jm.jam_mulai ASC"
);
$stmtHariIniJadwal->execute([':id' => $idDosen, ':hari' => $hariIni]);
$jadwalHariIni = $stmtHariIniJadwal->fetchAll();

$stmtHariIniSlot = $pdo->prepare("SELECT * FROM slot_konsultasi WHERE id_dosen = :id AND hari = :hari AND aktif = 1 ORDER BY jam_mulai ASC");
$stmtHariIniSlot->execute([':id' => $idDosen, ':hari' => $hariIni]);
$slotHariIni = $stmtHariIniSlot->fetchAll();

$page_title = 'Dashboard Dosen';
$active_page = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Banner Sambutan Dosen -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card card-custom border-0 p-4 bg-white shadow-sm">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <?php if (!empty($dosen['foto']) && file_exists(__DIR__ . '/../' . $dosen['foto'])): ?>
                        <img src="<?= BASE_URL . '/' . e($dosen['foto']) ?>" alt="Foto Dosen" class="rounded-circle shadow-sm" style="width: 72px; height: 72px; object-fit: cover;">
                    <?php else: ?>
                        <div class="avatar-initial avatar-initial-lg bg-success bg-opacity-10 text-success">
                            <?= strtoupper(substr($user['nama'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <span class="badge bg-success-subtle text-success fw-bold px-2 py-1 mb-1">Dosen Polinela</span>
                        <h3 class="fw-bold text-dark mb-0"><?= e($user['nama']) ?></h3>
                        <p class="text-muted small mb-0">
                            NIDN/NIP: <span class="fw-semibold text-dark"><?= e($dosen['nidn_nip']) ?></span> &bull; 
                            Prodi: <span class="fw-semibold text-dark"><?= e($dosen['nama_prodi']) ?></span>
                        </p>
                        <p class="text-secondary small mb-0 mt-1">
                            <i class="bi bi-award me-1 text-warning"></i>Keahlian: <?= e($dosen['bidang_keahlian']) ?>
                        </p>
                    </div>
                </div>
                <div class="text-md-end">
                    <a href="<?= BASE_URL ?>/dosen/profil.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-pencil-square me-1"></i>Perbarui Profil
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bagian Status Ketersediaan & Form Pembaruan 1-Klik -->
<div class="row g-4 mb-4">
    <!-- Status Terkini -->
    <div class="col-lg-5">
        <div class="card card-custom h-100 border-0 shadow-sm">
            <div class="card-header card-header-polinela d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold"><i class="bi bi-broadcast me-2"></i>Status Ketersediaan Saat Ini</span>
                <span class="badge bg-light text-dark small">Terkini</span>
            </div>
            <div class="card-body p-4 text-center d-flex flex-column justify-content-center">
                <div class="mb-3">
                    <span style="font-size: 1.25rem;">
                        <?= status_badge($statusValue) ?>
                    </span>
                </div>

                <?php if ($isExpired): ?>
                    <div class="alert alert-warning py-2 px-3 small d-flex align-items-center mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                        <div class="text-start">
                            <strong>Status Kedaluwarsa!</strong> Waktu berlaku status telah berakhir. Mohon perbarui status Anda.
                        </div>
                    </div>
                <?php endif; ?>

                <div class="p-3 bg-light rounded-3 text-start mb-3">
                    <div class="small text-muted mb-1"><i class="bi bi-chat-left-quote me-1"></i>Catatan untuk Mahasiswa:</div>
                    <div class="fw-medium text-dark">
                        <?= !empty($statusCatatan) ? nl2br(e($statusCatatan)) : '<span class="text-muted fst-italic">Tidak ada catatan tambahan.</span>' ?>
                    </div>
                </div>

                <div class="row text-start small text-muted g-2">
                    <div class="col-6">
                        <span class="d-block text-secondary">Berlaku Sampai:</span>
                        <strong class="text-dark">
                            <?= $berlakuSampai ? format_datetime($berlakuSampai) : 'Tidak dibatasi' ?>
                        </strong>
                    </div>
                    <div class="col-6">
                        <span class="d-block text-secondary">Terakhir Diperbarui:</span>
                        <strong class="text-dark">
                            <?= $updatedAt ? format_datetime($updatedAt) : '-' ?>
                        </strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Ubah Status Cepat -->
    <div class="col-lg-7">
        <div class="card card-custom h-100 border-0 shadow-sm">
            <div class="card-header card-header-polinela py-3">
                <span class="fw-bold"><i class="bi bi-lightning-charge-fill me-2 text-warning"></i>Perbarui Status Ketersediaan Anda</span>
            </div>
            <div class="card-body p-4">
                <form action="<?= BASE_URL ?>/dosen/status-update.php" method="POST">
                    <?= csrf_field() ?>

                    <label class="form-label fw-semibold small text-secondary mb-2">Pilih Status Ketersediaan Baru:</label>
                    <div class="row g-2 mb-3">
                        <?php 
                        $statusOptions = [
                            'Tersedia'        => ['label' => 'Tersedia', 'badge' => 'outline-success', 'icon' => 'bi-check-circle-fill'],
                            'Mengajar'        => ['label' => 'Mengajar', 'badge' => 'outline-primary', 'icon' => 'bi-book-half'],
                            'Hanya Konsultasi'=> ['label' => 'Hanya Konsultasi', 'badge' => 'outline-warning', 'icon' => 'bi-chat-text-fill'],
                            'Hanya Bimbingan' => ['label' => 'Hanya Bimbingan', 'badge' => 'outline-info', 'icon' => 'bi-mortarboard-fill'],
                            'Tidak Tersedia'  => ['label' => 'Tidak Tersedia', 'badge' => 'outline-secondary', 'icon' => 'bi-x-circle-fill'],
                        ];
                        foreach ($statusOptions as $key => $opt): 
                        ?>
                            <div class="col-6 col-sm-4">
                                <input type="radio" class="btn-check" name="status" id="status_<?= md5($key) ?>" value="<?= e($key) ?>" <?= $statusValue === $key ? 'checked' : '' ?> required>
                                <label class="btn btn-<?= $opt['badge'] ?> w-100 py-2 d-flex flex-column align-items-center justify-content-center text-truncate" for="status_<?= md5($key) ?>">
                                    <i class="bi <?= $opt['icon'] ?> fs-5 mb-1"></i>
                                    <span class="small fw-semibold"><?= e($opt['label']) ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-3">
                        <label for="catatan" class="form-label fw-semibold small text-secondary">
                            <i class="bi bi-chat-text me-1"></i>Catatan Singkat (Opsional):
                        </label>
                        <input type="text" class="form-control" id="catatan" name="catatan" 
                               value="<?= e($statusCatatan) ?>" placeholder="Contoh: Sedang evaluasi proposal di lab komputer / istirahat jam 12.00" maxlength="255">
                        <div class="form-text small text-muted">Maksimal 255 karakter. Informasi ini langsung terlihat oleh mahasiswa.</div>
                    </div>

                    <div class="mb-3">
                        <label for="berlaku_sampai" class="form-label fw-semibold small text-secondary">
                            <i class="bi bi-clock me-1"></i>Status Berlaku Sampai (Opsional):
                        </label>
                        <input type="datetime-local" class="form-control" id="berlaku_sampai" name="berlaku_sampai"
                               value="<?= $berlakuSampai ? date('Y-m-d\TH:i', strtotime($berlakuSampai)) : '' ?>">
                        <div class="form-text small text-muted">Kosongkan jika status berlaku hingga Anda memperbaruinya kembali.</div>
                    </div>

                    <button type="submit" class="btn btn-polinela w-100 py-2 fw-semibold shadow-sm">
                        <i class="bi bi-arrow-repeat me-1"></i>Simpan Perubahan Status Sekarang
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Kartu Ringkasan Metrik Slot & Jadwal -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card bg-success shadow-sm">
            <div class="card-body">
                <span class="fw-semibold text-white-50 text-uppercase small">Jadwal Kuliah</span>
                <h2 class="fw-bold text-white mb-0"><?= $totalJadwal ?> <span class="fs-6 fw-normal">Sesi</span></h2>
                <div class="mt-2">
                    <a href="<?= BASE_URL ?>/dosen/jadwal.php" class="text-white text-decoration-none small fw-semibold">
                        Kelola Jadwal <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            <i class="bi bi-calendar-week stat-icon"></i>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card bg-primary shadow-sm">
            <div class="card-body">
                <span class="fw-semibold text-white-50 text-uppercase small">Slot Konsultasi Aktif</span>
                <h2 class="fw-bold text-white mb-0"><?= $totalKonsul ?> <span class="fs-6 fw-normal">Slot</span></h2>
                <div class="mt-2">
                    <a href="<?= BASE_URL ?>/dosen/slot.php" class="text-white text-decoration-none small fw-semibold">
                        Kelola Slot <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            <i class="bi bi-chat-dots-fill stat-icon"></i>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card bg-info shadow-sm">
            <div class="card-body">
                <span class="fw-semibold text-white-50 text-uppercase small">Slot Bimbingan TA Aktif</span>
                <h2 class="fw-bold text-white mb-0"><?= $totalBimbingan ?> <span class="fs-6 fw-normal">Slot</span></h2>
                <div class="mt-2">
                    <a href="<?= BASE_URL ?>/dosen/slot.php" class="text-white text-decoration-none small fw-semibold">
                        Kelola Bimbingan <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            <i class="bi bi-mortarboard-fill stat-icon"></i>
        </div>
    </div>
</div>

<!-- Agenda / Jadwal Dosen Hari Ini -->
<div class="row">
    <div class="col-12">
        <div class="card card-custom border-0 shadow-sm">
            <div class="card-header card-header-polinela d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold"><i class="bi bi-calendar-event me-2"></i>Agenda Hari Ini (<?= e($hariIni) ?>, <?= date('d M Y') ?>)</span>
                <span class="badge bg-success small">Jadwal Otomatis</span>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <!-- Kuliah Hari Ini -->
                    <div class="col-md-6">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                            <i class="bi bi-book me-1 text-primary"></i>Jadwal Mengajar Hari Ini
                        </h6>
                        <?php if (empty($jadwalHariIni)): ?>
                            <div class="p-3 bg-light rounded text-center text-muted small">
                                <i class="bi bi-calendar-check me-1"></i>Tidak ada jadwal mengajar pada hari <?= e($hariIni) ?>.
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($jadwalHariIni as $j): ?>
                                    <div class="list-group-item px-0 d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong class="text-dark d-block"><?= e($j['nama_mk'] ?? $j['kode_mk'] ?? '-') ?></strong>
                                            <span class="badge bg-secondary-subtle text-secondary small"><?= e($j['nama_kelas'] ?? '-') ?></span>
                                            <span class="ms-1"><?= tipe_badge($j['tipe_sesi'] ?? 'teori') ?></span>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-primary text-white">
                                                <?= substr($j['jam_mulai'], 0, 5) ?> - <?= substr($j['jam_selesai'], 0, 5) ?> WIB
                                            </span>
                                            <div class="mt-1"><?= mode_badge($j['mode'] ?? 'luring') ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Slot Konsultasi/Bimbingan Hari Ini -->
                    <div class="col-md-6">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                            <i class="bi bi-clock-history me-1 text-success"></i>Slot Konsultasi & Bimbingan Hari Ini
                        </h6>
                        <?php if (empty($slotHariIni)): ?>
                            <div class="p-3 bg-light rounded text-center text-muted small">
                                <i class="bi bi-calendar-check me-1"></i>Tidak ada slot konsultasi/bimbingan aktif pada hari <?= e($hariIni) ?>.
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($slotHariIni as $s): ?>
                                    <div class="list-group-item px-0 d-flex justify-content-between align-items-start">
                                        <div>
                                            <span class="badge <?= $s['jenis'] === 'bimbingan' ? 'bg-info text-dark' : 'bg-warning text-dark' ?> me-1">
                                                <?= $s['jenis'] === 'bimbingan' ? 'Bimbingan TA' : 'Konsultasi' ?>
                                            </span>
                                            <?php if (!empty($s['catatan'])): ?>
                                                <small class="text-muted d-block mt-1"><?= e($s['catatan']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-success text-white">
                                                <?= substr($s['jam_mulai'], 0, 5) ?> - <?= substr($s['jam_selesai'], 0, 5) ?> WIB
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
