<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Halaman Detail Ketersediaan, Jadwal Mengajar, & Slot Dosen
// File: mahasiswa/detail.php
// ==============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('mahasiswa');

$pdo = get_db_connection();
$idDosen = (int)($_GET['id'] ?? 0);

if ($idDosen <= 0) {
    set_flash('danger', 'Dosen yang Anda tuju tidak valid.');
    redirect(BASE_URL . '/mahasiswa/index.php');
}

// 1. Ambil data profil dosen dan status ketersediaan
$stmtDosen = $pdo->prepare("SELECT d.*, u.nama, u.email, p.nama_prodi, p.jurusan,
                                   COALESCE(sk.status, 'Tersedia') AS status,
                                   sk.catatan AS status_catatan,
                                   sk.berlaku_sampai,
                                   sk.updated_at
                            FROM dosen d
                            JOIN users u ON d.user_id = u.id
                            JOIN program_studi p ON d.id_prodi = p.id
                            LEFT JOIN status_ketersediaan sk ON d.id = sk.id_dosen
                            WHERE d.id = :id LIMIT 1");
$stmtDosen->execute([':id' => $idDosen]);
$dosen = $stmtDosen->fetch();

if (!$dosen) {
    set_flash('danger', 'Data dosen tidak ditemukan dalam basis data.');
    redirect(BASE_URL . '/mahasiswa/index.php');
}

$statusVal = $dosen['status'];
$isExpired = false;
if (!empty($dosen['berlaku_sampai']) && strtotime($dosen['berlaku_sampai']) < time()) {
    $isExpired = true;
}

// 2. Ambil Jadwal Mengajar Dosen via tabel jadwal_dosen (skema baru normalized)
$orderHari = "FIELD(jm.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat')";
$stmtJadwal = $pdo->prepare(
    "SELECT jm.id, jm.hari, jm.jam_mulai, jm.jam_selesai, jm.tipe_sesi, jm.sks, jm.mode, jm.ruang,
            mk.kode AS kode_mk, mk.nama AS nama_mk,
            k.nama_kelas, k.semester,
            GROUP_CONCAT(u2.nama ORDER BY u2.nama SEPARATOR ', ') AS semua_dosen
     FROM jadwal_mengajar jm
     JOIN mata_kuliah mk ON mk.id = jm.id_mk
     JOIN kelas k ON k.id = jm.id_kelas
     JOIN jadwal_dosen jd ON jd.id_jadwal = jm.id
     JOIN dosen d2 ON d2.id = jd.id_dosen
     JOIN users u2 ON u2.id = d2.user_id
     WHERE jm.id IN (
         SELECT id_jadwal FROM jadwal_dosen WHERE id_dosen = :id_dosen
     )
     GROUP BY jm.id
     ORDER BY {$orderHari}, jm.jam_mulai ASC"
);
$stmtJadwal->execute([':id_dosen' => $idDosen]);
$jadwalList = $stmtJadwal->fetchAll();

// 3. Ambil Slot Konsultasi Aktif
$orderHariSlot = "FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat')";
$stmtKonsul = $pdo->prepare("SELECT * FROM slot_konsultasi WHERE id_dosen = :id AND jenis = 'konsultasi' AND aktif = 1 ORDER BY {$orderHariSlot}, jam_mulai ASC");
$stmtKonsul->execute([':id' => $idDosen]);
$konsulList = $stmtKonsul->fetchAll();

// 4. Ambil Slot Bimbingan Tugas Akhir Aktif
$stmtBimbingan = $pdo->prepare("SELECT * FROM slot_konsultasi WHERE id_dosen = :id AND jenis = 'bimbingan' AND aktif = 1 ORDER BY {$orderHariSlot}, jam_mulai ASC");
$stmtBimbingan->execute([':id' => $idDosen]);
$bimbinganList = $stmtBimbingan->fetchAll();

$page_title = 'Jadwal & Ketersediaan ' . $dosen['nama'];
$active_page = 'beranda';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Navigasi Breadcrumb & Tombol Kembali -->
<div class="row mb-3">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/mahasiswa/index.php" class="text-success text-decoration-none"><i class="bi bi-grid-fill me-1"></i>Daftar Dosen</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($dosen['nama']) ?></li>
            </ol>
        </nav>
        <a href="<?= BASE_URL ?>/mahasiswa/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar Dosen
        </a>
    </div>
</div>

<!-- Kartu Header Profil Dosen & Status Terkini -->
<div class="card card-custom border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="row align-items-center gy-4">
            <!-- Profil Foto & Biodata -->
            <div class="col-lg-7">
                <div class="d-flex align-items-center gap-3">
                    <?php if (!empty($dosen['foto']) && file_exists(__DIR__ . '/../' . $dosen['foto'])): ?>
                        <img src="<?= BASE_URL . '/' . e($dosen['foto']) ?>" alt="Foto Dosen" class="rounded-circle shadow-sm" style="width: 88px; height: 88px; object-fit: cover;">
                    <?php else: ?>
                        <div class="avatar-initial avatar-initial-lg bg-success bg-opacity-10 text-success fw-bold" style="width: 88px; height: 88px;">
                            <?= strtoupper(substr($dosen['nama'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <span class="badge bg-success-subtle text-success fw-bold px-2 py-1 mb-1">
                            <?= e($dosen['nama_prodi']) ?> &bull; <?= e($dosen['jurusan']) ?>
                        </span>
                        <h3 class="fw-bold text-dark mb-1"><?= e($dosen['nama']) ?></h3>
                        <p class="text-secondary small mb-1">
                            <i class="bi bi-person-vcard me-1"></i>NIDN / NIP: <strong class="text-dark"><?= $dosen['nidn_nip'] ? e($dosen['nidn_nip']) : '<span class="text-muted fst-italic">-</span>' ?></strong> &bull; 
                            <i class="bi bi-envelope me-1 ms-2"></i>Email: <a href="mailto:<?= e($dosen['email']) ?>" class="text-decoration-none text-dark"><?= e($dosen['email']) ?></a>
                        </p>
                        <p class="text-muted small mb-0">
                            <i class="bi bi-award me-1 text-warning"></i>Keahlian: <span class="text-dark"><?= e($dosen['bidang_keahlian']) ?></span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Panel Status Ketersediaan Terkini -->
            <div class="col-lg-5">
                <div class="p-3 bg-light rounded-3 border-start border-4 <?= match($statusVal) {
                    'Tersedia' => 'border-success',
                    'Mengajar' => 'border-primary',
                    'Hanya Konsultasi' => 'border-warning',
                    'Hanya Bimbingan' => 'border-info',
                    default => 'border-secondary'
                } ?> shadow-sm">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-bold text-secondary text-uppercase">Status Ketersediaan:</span>
                        <div><?= status_badge($statusVal) ?></div>
                    </div>

                    <div class="small mb-2">
                        <strong class="text-dark d-block mb-1"><i class="bi bi-chat-left-text me-1 text-success"></i>Catatan Dosen:</strong>
                        <div class="text-secondary bg-white p-2 rounded border small">
                            <?= !empty($dosen['status_catatan']) ? nl2br(e($dosen['status_catatan'])) : '<span class="text-muted fst-italic">Tidak ada catatan khusus dari dosen.</span>' ?>
                        </div>
                    </div>

                    <div class="row g-2 small text-muted" style="font-size: 0.78rem;">
                        <div class="col-6">
                            <span>Berlaku Sampai:</span>
                            <strong class="d-block text-dark"><?= $dosen['berlaku_sampai'] ? format_datetime($dosen['berlaku_sampai']) : 'Tidak dibatasi' ?></strong>
                        </div>
                        <div class="col-6">
                            <span>Terakhir Diperbarui:</span>
                            <strong class="d-block text-dark"><?= $dosen['updated_at'] ? format_datetime($dosen['updated_at']) : '-' ?></strong>
                        </div>
                    </div>

                    <?php if ($isExpired): ?>
                        <div class="alert alert-warning py-1 px-2 small mt-2 mb-0 d-flex align-items-center" style="font-size: 0.78rem;">
                            <i class="bi bi-clock-history me-1"></i>Waktu berlaku status telah berakhir. Silakan hubungi dosen melalui email jika mendesak.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Konten Jadwal & Slot Ketersediaan Dosen -->
<div class="row g-4 mb-4">
    <!-- 1. Tabel Jadwal Mengajar Mingguan -->
    <div class="col-12">
        <div class="card card-custom border-0 shadow-sm">
            <div class="card-header card-header-polinela d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold"><i class="bi bi-book-half me-2 text-primary"></i>Jadwal Mengajar Perkuliahan</span>
                <span class="badge bg-primary"><?= count($jadwalList) ?> Sesi Terdaftar</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($jadwalList)): ?>
                    <div class="text-center py-4 px-3">
                        <i class="bi bi-calendar-check fs-2 text-muted d-block mb-2"></i>
                        <p class="text-muted small mb-0">Dosen belum memiliki jadwal mengajar yang terdaftar di sistem.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4" style="width: 40px;">No</th>
                                    <th style="width: 100px;">Hari</th>
                                    <th style="width: 160px;">Waktu</th>
                                    <th>Mata Kuliah</th>
                                    <th style="width: 100px;">Tipe</th>
                                    <th style="width: 60px;">SKS</th>
                                    <th style="width: 90px;">Mode</th>
                                    <?php if (TAMPILKAN_RUANG): ?>
                                    <th>Ruang</th>
                                    <?php endif; ?>
                                    <th>Dosen Pengampu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($jadwalList as $j): ?>
                                    <tr>
                                        <td class="ps-4 text-muted small"><?= $no++ ?></td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success fw-bold px-2 py-1">
                                                <?= e($j['hari']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <i class="bi bi-clock me-1 text-secondary"></i>
                                            <strong><?= substr($j['jam_mulai'], 0, 5) ?></strong>–<strong><?= substr($j['jam_selesai'], 0, 5) ?></strong> <span class="text-muted small">WIB</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-dark bg-opacity-75 font-monospace small me-1"><?= e($j['kode_mk']) ?></span>
                                            <strong class="text-dark"><?= e($j['nama_mk']) ?></strong>
                                            <div class="text-muted small"><?= e($j['nama_kelas']) ?> &bull; Sem. <?= e($j['semester']) ?></div>
                                        </td>
                                        <td><?= tipe_badge($j['tipe_sesi']) ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border"><?= (int)$j['sks'] ?></span>
                                        </td>
                                        <td><?= mode_badge($j['mode']) ?></td>
                                        <?php if (TAMPILKAN_RUANG): ?>
                                        <td class="small text-secondary">
                                            <?= !empty($j['ruang']) ? e($j['ruang']) : '<span class="text-muted">-</span>' ?>
                                        </td>
                                        <?php endif; ?>
                                        <td class="small"><?= e($j['semua_dosen'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 2. Slot Konsultasi Akademik -->
    <div class="col-lg-6">
        <div class="card card-custom border-0 shadow-sm h-100">
            <div class="card-header card-header-polinela d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold"><i class="bi bi-chat-text-fill me-2 text-warning"></i>Slot Konsultasi Akademik</span>
                <span class="badge bg-warning text-dark"><?= count($konsulList) ?> Slot Aktif</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($konsulList)): ?>
                    <div class="text-center py-4 px-3">
                        <i class="bi bi-chat-left-dots fs-2 text-muted d-block mb-2"></i>
                        <p class="text-muted small mb-0">Saat ini tidak ada slot konsultasi akademik yang sedang aktif dibuka.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($konsulList as $k): ?>
                            <div class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge bg-success-subtle text-success fw-bold px-2 py-1"><?= e($k['hari']) ?></span>
                                    <span class="badge bg-light text-dark border">
                                        <i class="bi bi-clock me-1 text-success"></i><?= substr($k['jam_mulai'], 0, 5) ?> - <?= substr($k['jam_selesai'], 0, 5) ?> WIB
                                    </span>
                                </div>
                                <div class="small text-secondary mt-2">
                                    <strong class="text-dark">Petunjuk / Topik:</strong>
                                    <?= !empty($k['catatan']) ? e($k['catatan']) : '<span class="text-muted fst-italic">Konsultasi umum seputar perkuliahan dan akademik</span>' ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 3. Slot Bimbingan Tugas Akhir -->
    <div class="col-lg-6">
        <div class="card card-custom border-0 shadow-sm h-100">
            <div class="card-header card-header-polinela d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold"><i class="bi bi-mortarboard-fill me-2 text-info"></i>Slot Bimbingan Tugas Akhir / PKL</span>
                <span class="badge bg-info text-dark"><?= count($bimbinganList) ?> Slot Aktif</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($bimbinganList)): ?>
                    <div class="text-center py-4 px-3">
                        <i class="bi bi-journal-text fs-2 text-muted d-block mb-2"></i>
                        <p class="text-muted small mb-0">Saat ini tidak ada slot bimbingan tugas akhir yang dibuka dosen.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($bimbinganList as $b): ?>
                            <div class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge bg-success-subtle text-success fw-bold px-2 py-1"><?= e($b['hari']) ?></span>
                                    <span class="badge bg-light text-dark border">
                                        <i class="bi bi-clock me-1 text-info"></i><?= substr($b['jam_mulai'], 0, 5) ?> - <?= substr($b['jam_selesai'], 0, 5) ?> WIB
                                    </span>
                                </div>
                                <div class="small text-secondary mt-2">
                                    <strong class="text-dark">Persyaratan / Catatan:</strong>
                                    <?= !empty($b['catatan']) ? e($b['catatan']) : '<span class="text-muted fst-italic">Membawa lembar asistensi dan draf laporan tugas akhir</span>' ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Informasi Etika Mahasiswa -->
<div class="row">
    <div class="col-12">
        <div class="card card-custom border-0 shadow-sm bg-light">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="fs-2 text-success">
                    <i class="bi bi-shield-check"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-1">Etika Menemui Dosen Politeknik Negeri Lampung</h6>
                    <p class="small text-muted mb-0">
                        Pastikan Anda datang sesuai dengan slot konsultasi atau bimbingan yang telah ditentukan. Datang tanpa jadwal dapat mengganggu waktu mengajar dan penelitian dosen. S-LECTURA menjaga privasi dosen tanpa pelacakan fisik ruangan secara real-time.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

