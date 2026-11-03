<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Halaman Jadwal Kelas Mahasiswa (per Hari)
// File: mahasiswa/jadwal-kelas.php
// ==============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('mahasiswa');

$pdo  = get_db_connection();
$user = current_user();

// Ambil data kelas mahasiswa
$stmtMhs = $pdo->prepare(
    "SELECT m.*, k.nama_kelas, k.semester, k.tahun_ajaran, p.nama_prodi
     FROM mahasiswa m
     JOIN kelas k ON k.id = m.id_kelas
     JOIN program_studi p ON p.id = m.id_prodi
     WHERE m.user_id = :uid LIMIT 1"
);
$stmtMhs->execute([':uid' => $user['id']]);
$mhs = $stmtMhs->fetch();

$jadwalPerHari = [];

if ($mhs && $mhs['id_kelas']) {
    // Ambil seluruh jadwal kelas beserta dosen pengampu
    $stmtJadwal = $pdo->prepare(
        "SELECT jm.id, jm.hari, jm.jam_mulai, jm.jam_selesai, jm.tipe_sesi,
                jm.sks, jm.mode, jm.ruang,
                mk.kode AS kode_mk, mk.nama AS nama_mk,
                GROUP_CONCAT(u.nama ORDER BY u.nama SEPARATOR ', ') AS dosen_list
         FROM jadwal_mengajar jm
         JOIN mata_kuliah mk ON mk.id = jm.id_mk
         JOIN kelas k ON k.id = jm.id_kelas
         JOIN jadwal_dosen jd ON jd.id_jadwal = jm.id
         JOIN dosen d ON d.id = jd.id_dosen
         JOIN users u ON u.id = d.user_id
         WHERE jm.id_kelas = :id_kelas
         GROUP BY jm.id
         ORDER BY FIELD(jm.hari,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'), jm.jam_mulai"
    );
    $stmtJadwal->execute([':id_kelas' => $mhs['id_kelas']]);
    $semuaJadwal = $stmtJadwal->fetchAll();

    // Kelompokkan per hari (Senin s.d. Jumat)
    $urutan = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
    foreach ($urutan as $hari) {
        $jadwalPerHari[$hari] = [];
    }
    foreach ($semuaJadwal as $j) {
        if (in_array($j['hari'], $urutan, true)) {
            $jadwalPerHari[$j['hari']][] = $j;
        }
    }
}

// Hitung total SKS per minggu
$totalSks = 0;
foreach ($jadwalPerHari as $list) {
    foreach ($list as $j) {
        $totalSks += (int)$j['sks'];
    }
}

$page_title  = 'Jadwal Kelas Saya';
$active_page = 'jadwal-kelas';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col">
        <h3 class="fw-bold text-dark mb-1">
            <i class="bi bi-calendar-week-fill me-2 text-success"></i>Jadwal Kelas Saya
        </h3>
        <?php if ($mhs): ?>
        <p class="text-secondary small mb-0">
            Kelas: <strong><?= e($mhs['nama_kelas']) ?></strong> &mdash;
            Semester <?= e($mhs['semester']) ?> &mdash; <?= e($mhs['tahun_ajaran']) ?>
            &mdash; Total: <strong><?= $totalSks ?> SKS/minggu</strong>
        </p>
        <?php endif; ?>
    </div>
    <div class="col-auto">
        <a href="<?= BASE_URL ?>/mahasiswa/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

<?php if (!$mhs || !$mhs['id_kelas']): ?>
<div class="alert alert-info d-flex align-items-center gap-2">
    <i class="bi bi-info-circle-fill fs-5"></i>
    <div>Anda belum terdaftar di kelas manapun. Hubungi administrator untuk pengaturan kelas.</div>
</div>
<?php else: ?>

<?php
$adaJadwal = false;
foreach ($jadwalPerHari as $list) { if ($list) { $adaJadwal = true; break; } }
if (!$adaJadwal):
?>
<div class="alert alert-warning d-flex align-items-center gap-2">
    <i class="bi bi-calendar-x-fill fs-5"></i>
    <div>Belum ada jadwal yang terdaftar untuk kelas Anda.</div>
</div>
<?php else: ?>

<?php
$hariAktif = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
$hariIndo  = date('l'); // Hari ini (English)
$mapHari   = [
    'Monday'    => 'Senin',   'Tuesday'  => 'Selasa',
    'Wednesday' => 'Rabu',    'Thursday' => 'Kamis',
    'Friday'    => 'Jumat',
];
$hariIni = $mapHari[$hariIndo] ?? '';
?>

<!-- Tab hari -->
<ul class="nav nav-pills mb-4 flex-wrap gap-1" id="hariTab">
    <?php foreach ($hariAktif as $idx => $hari):
        $jumlah = count($jadwalPerHari[$hari] ?? []);
        $isActive = ($hari === $hariIni) || ($idx === 0 && !isset($jadwalPerHari[$hariIni]));
    ?>
    <li class="nav-item">
        <button class="nav-link <?= $isActive ? 'active' : '' ?> position-relative"
                data-bs-toggle="pill"
                data-bs-target="#tab-<?= strtolower($hari) ?>">
            <?= e($hari) ?>
            <?php if ($jumlah > 0): ?>
            <span class="badge bg-success rounded-pill ms-1" style="font-size:0.65rem;"><?= $jumlah ?></span>
            <?php endif; ?>
        </button>
    </li>
    <?php endforeach; ?>
</ul>

<div class="tab-content">
    <?php foreach ($hariAktif as $idx => $hari):
        $jadwalHari = $jadwalPerHari[$hari] ?? [];
        $isActive   = ($hari === $hariIni) || ($idx === 0 && !isset($jadwalPerHari[$hariIni]));
    ?>
    <div class="tab-pane fade <?= $isActive ? 'show active' : '' ?>" id="tab-<?= strtolower($hari) ?>">

        <?php if (empty($jadwalHari)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-calendar-check fs-1 opacity-25"></i>
            <p class="mt-2">Tidak ada jadwal kuliah hari <?= e($hari) ?></p>
        </div>
        <?php else: ?>

        <div class="row g-3">
            <?php foreach ($jadwalHari as $j): ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm hover-lift">
                    <div class="card-body p-3">
                        <div class="row align-items-start g-2">
                            <!-- Waktu -->
                            <div class="col-auto">
                                <div class="text-center bg-success bg-opacity-10 rounded-3 p-2"
                                     style="min-width:80px;">
                                    <div class="fw-bold text-success small">
                                        <?= substr($j['jam_mulai'], 0, 5) ?>
                                    </div>
                                    <div class="text-muted" style="font-size:.7rem;">s.d.</div>
                                    <div class="fw-bold text-success small">
                                        <?= substr($j['jam_selesai'], 0, 5) ?>
                                    </div>
                                    <div class="text-muted mt-1" style="font-size:.65rem;">WIB</div>
                                </div>
                            </div>
                            <!-- Detail -->
                            <div class="col">
                                <div class="d-flex flex-wrap align-items-center gap-1 mb-1">
                                    <span class="badge bg-dark bg-opacity-75 font-monospace small">
                                        <?= e($j['kode_mk']) ?>
                                    </span>
                                    <?= tipe_badge($j['tipe_sesi']) ?>
                                    <?= mode_badge($j['mode']) ?>
                                    <span class="badge bg-light text-dark border">
                                        <i class="bi bi-lightning-charge me-1"></i><?= (int)$j['sks'] ?> SKS
                                    </span>
                                </div>

                                <h6 class="fw-bold mb-1 text-dark"><?= e($j['nama_mk']) ?></h6>

                                <div class="small text-muted">
                                    <i class="bi bi-person-badge me-1"></i>
                                    <?= e($j['dosen_list']) ?>
                                </div>

                                <?php if (TAMPILKAN_RUANG && !empty($j['ruang'])): ?>
                                <div class="small text-secondary mt-1">
                                    <i class="bi bi-geo-alt me-1 text-warning"></i>
                                    <?= e($j['ruang']) ?>
                                    <span class="text-muted fst-italic ms-1">(Ruang perkuliahan resmi)</span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

