<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Supervisi & Pengelolaan Jadwal/Slot Seluruh Dosen - Administrator
// File: admin/jadwal-slot.php
// ==============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pdo = get_db_connection();
$errors = [];
$hariList = hari_list();

// Dapatkan ID Dosen terpilih dari GET atau POST
$selectedDosenId = (int)($_REQUEST['id_dosen'] ?? 0);

// ==============================================================
// PROSES AKSI POST (SUPERVISI STATUS, JADWAL, ATAU SLOT)
// ==============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Token CSRF tidak valid.');
        redirect(BASE_URL . '/admin/jadwal-slot.php?id_dosen=' . $selectedDosenId);
    }

    $action = $_POST['action'] ?? '';
    $idDosen = (int)($_POST['id_dosen'] ?? 0);

    // 1. UPDATE STATUS KETERSEDIAAN DOSEN
    if ($action === 'update_status') {
        $status = trim($_POST['status'] ?? '');
        $catatan = trim($_POST['catatan'] ?? '');
        $berlakuSampaiRaw = trim($_POST['berlaku_sampai'] ?? '');

        $validStatuses = ['Tersedia', 'Mengajar', 'Hanya Konsultasi', 'Hanya Bimbingan', 'Tidak Tersedia'];
        if (!in_array($status, $validStatuses, true)) {
            $errors[] = 'Pilihan status tidak valid.';
        } else {
            $berlakuSampai = null;
            if (!empty($berlakuSampaiRaw)) {
                $ts = strtotime($berlakuSampaiRaw);
                if ($ts) $berlakuSampai = date('Y-m-d H:i:s', $ts);
            }
            $catatanVal = !empty($catatan) ? mb_substr($catatan, 0, 255) : null;

            $sqlStatus = "INSERT INTO status_ketersediaan (id_dosen, status, catatan, berlaku_sampai)
                          VALUES (:id, :status, :catatan, :berlaku)
                          ON DUPLICATE KEY UPDATE 
                              status = :status_u, catatan = :catatan_u, berlaku_sampai = :berlaku_u, updated_at = NOW()";
            $stmtSt = $pdo->prepare($sqlStatus);
            $stmtSt->execute([
                ':id'        => $idDosen,
                ':status'    => $status,
                ':catatan'   => $catatanVal,
                ':berlaku'   => $berlakuSampai,
                ':status_u'  => $status,
                ':catatan_u' => $catatanVal,
                ':berlaku_u' => $berlakuSampai,
            ]);

            set_flash('success', "Status ketersediaan dosen berhasil diperbarui menjadi: <strong>{$status}</strong>");
            redirect(BASE_URL . '/admin/jadwal-slot.php?id_dosen=' . $idDosen);
        }
    }

    // 2. KELOLA JADWAL MENGAJAR (CREATE / DELETE)
    if ($action === 'create_jadwal') {
        $hari       = trim($_POST['hari'] ?? '');
        $jamMulai   = trim($_POST['jam_mulai'] ?? '');
        $jamSelesai = trim($_POST['jam_selesai'] ?? '');
        $idMk       = (int)($_POST['id_mk'] ?? 0);
        $idKelas    = (int)($_POST['id_kelas'] ?? 0);
        $tipeSesi   = trim($_POST['tipe_sesi'] ?? 'teori');
        $sks        = (int)($_POST['sks'] ?? 1);
        $mode       = trim($_POST['mode'] ?? 'luring');
        $ruang      = trim($_POST['ruang'] ?? '');
        $dosenIds   = array_map('intval', (array)($_POST['dosen_ids'] ?? []));

        if ($idDosen > 0 && !in_array($idDosen, $dosenIds)) {
            $dosenIds[] = $idDosen;
        }

        if (!in_array($hari, $hariList, true)) $errors[] = 'Pilihan hari tidak valid.';
        if (empty($jamMulai) || empty($jamSelesai)) $errors[] = 'Jam mulai dan selesai wajib diisi.';
        elseif ($jamSelesai <= $jamMulai) $errors[] = 'Jam selesai harus setelah jam mulai.';
        if ($idMk <= 0) $errors[] = 'Mata kuliah wajib dipilih.';
        if ($idKelas <= 0) $errors[] = 'Kelas wajib dipilih.';
        if (!in_array($tipeSesi, ['teori', 'praktik'], true)) $errors[] = 'Tipe sesi tidak valid.';
        if (!in_array($mode, ['daring', 'luring'], true)) $errors[] = 'Mode perkuliahan tidak valid.';
        if ($sks < 1 || $sks > 6) $errors[] = 'SKS harus antara 1 sampai 6.';
        if (empty($dosenIds)) $errors[] = 'Minimal satu dosen pengampu harus dipilih.';

        if (empty($errors)) {
            foreach ($dosenIds as $did) {
                $conflict = check_time_collision($pdo, $did, $hari, $jamMulai, $jamSelesai, null, null);
                if ($conflict) {
                    $stmtNm = $pdo->prepare("SELECT u.nama FROM dosen d JOIN users u ON u.id=d.user_id WHERE d.id=:id LIMIT 1");
                    $stmtNm->execute([':id' => $did]);
                    $nm = $stmtNm->fetchColumn() ?: "Dosen #$did";
                    $errors[] = "[{$nm}] {$conflict}";
                }
            }
        }

        if (empty($errors)) {
            $ruangFinal = ($mode === 'daring') ? null : (empty($ruang) ? null : $ruang);
            $ins = $pdo->prepare(
                "INSERT INTO jadwal_mengajar (id_mk, id_kelas, hari, jam_mulai, jam_selesai, tipe_sesi, sks, mode, ruang)
                 VALUES (:id_mk, :id_kelas, :hari, :jam_mulai, :jam_selesai, :tipe_sesi, :sks, :mode, :ruang)"
            );
            $ins->execute([
                ':id_mk'       => $idMk,
                ':id_kelas'    => $idKelas,
                ':hari'        => $hari,
                ':jam_mulai'   => $jamMulai,
                ':jam_selesai' => $jamSelesai,
                ':tipe_sesi'   => $tipeSesi,
                ':sks'         => $sks,
                ':mode'        => $mode,
                ':ruang'       => $ruangFinal,
            ]);
            $newJadwalId = (int)$pdo->lastInsertId();

            $stmtRel = $pdo->prepare("INSERT IGNORE INTO jadwal_dosen (id_jadwal, id_dosen) VALUES (:jid, :did)");
            foreach ($dosenIds as $did) {
                $stmtRel->execute([':jid' => $newJadwalId, ':did' => $did]);
            }

            set_flash('success', "Jadwal mengajar berhasil ditambahkan.");
            redirect(BASE_URL . '/admin/jadwal-slot.php?id_dosen=' . $idDosen);
        }
    }

    if ($action === 'delete_jadwal') {
        $idJadwal = (int)($_POST['id_jadwal'] ?? 0);
        $pdo->prepare("DELETE FROM jadwal_mengajar WHERE id = :id")->execute([':id' => $idJadwal]);
        set_flash('success', 'Jadwal mengajar berhasil dihapus.');
        redirect(BASE_URL . '/admin/jadwal-slot.php?id_dosen=' . $idDosen);
    }

    // 3. KELOLA SLOT KONSULTASI / BIMBINGAN (CREATE / UPDATE / TOGGLE / DELETE)
    if ($action === 'create_slot' || $action === 'update_slot') {
        $idSlot = (int)($_POST['id_slot'] ?? 0);
        $hari = trim($_POST['hari'] ?? '');
        $jamMulai = trim($_POST['jam_mulai'] ?? '');
        $jamSelesai = trim($_POST['jam_selesai'] ?? '');
        $jenis = trim($_POST['jenis'] ?? '');
        $catatan = trim($_POST['catatan'] ?? '');
        $aktif = isset($_POST['aktif']) ? 1 : 0;

        if (!in_array($hari, $hariList, true)) $errors[] = 'Pilihan hari tidak valid.';
        if (empty($jamMulai) || empty($jamSelesai)) $errors[] = 'Jam mulai dan selesai wajib diisi.';
        elseif ($jamSelesai <= $jamMulai) $errors[] = 'Jam selesai harus setelah jam mulai.';
        if (!in_array($jenis, ['konsultasi', 'bimbingan'], true)) $errors[] = 'Pilihan jenis slot tidak valid.';

        if (empty($errors) && $aktif === 1) {
            $excludeId = ($action === 'update_slot') ? $idSlot : null;
            $conflict = check_time_collision($pdo, $idDosen, $hari, $jamMulai, $jamSelesai, null, $excludeId);
            if ($conflict) $errors[] = $conflict;
        }

        if (empty($errors)) {
            $catatanVal = !empty($catatan) ? mb_substr($catatan, 0, 255) : null;
            if ($action === 'create_slot') {
                $ins = $pdo->prepare("INSERT INTO slot_konsultasi (id_dosen, hari, jam_mulai, jam_selesai, jenis, catatan, aktif)
                                      VALUES (:id, :hari, :jam_mulai, :jam_selesai, :jenis, :catatan, :aktif)");
                $ins->execute([
                    ':id'          => $idDosen,
                    ':hari'        => $hari,
                    ':jam_mulai'   => $jamMulai,
                    ':jam_selesai' => $jamSelesai,
                    ':jenis'       => $jenis,
                    ':catatan'     => $catatanVal,
                    ':aktif'       => $aktif,
                ]);
                set_flash('success', "Slot {$jenis} berhasil ditambahkan.");
            } else {
                $upd = $pdo->prepare("UPDATE slot_konsultasi 
                                      SET hari = :hari, jam_mulai = :jam_mulai, jam_selesai = :jam_selesai, jenis = :jenis, catatan = :catatan, aktif = :aktif 
                                      WHERE id = :id AND id_dosen = :id_dosen");
                $upd->execute([
                    ':hari'        => $hari,
                    ':jam_mulai'   => $jamMulai,
                    ':jam_selesai' => $jamSelesai,
                    ':jenis'       => $jenis,
                    ':catatan'     => $catatanVal,
                    ':aktif'       => $aktif,
                    ':id'          => $idSlot,
                    ':id_dosen'    => $idDosen,
                ]);
                set_flash('success', "Slot {$jenis} berhasil diperbarui.");
            }
            redirect(BASE_URL . '/admin/jadwal-slot.php?id_dosen=' . $idDosen);
        }
    }

    if ($action === 'toggle_slot') {
        $idSlot = (int)($_POST['id_slot'] ?? 0);
        $s = $pdo->prepare("SELECT aktif FROM slot_konsultasi WHERE id = :id AND id_dosen = :dosen");
        $s->execute([':id' => $idSlot, ':dosen' => $idDosen]);
        $row = $s->fetch();
        if ($row) {
            $newAktif = $row['aktif'] ? 0 : 1;
            $pdo->prepare("UPDATE slot_konsultasi SET aktif = :aktif WHERE id = :id AND id_dosen = :dosen")
                ->execute([':aktif' => $newAktif, ':id' => $idSlot, ':dosen' => $idDosen]);
            set_flash('success', 'Status keaktifan slot berhasil diubah.');
        }
        redirect(BASE_URL . '/admin/jadwal-slot.php?id_dosen=' . $idDosen);
    }

    if ($action === 'delete_slot') {
        $idSlot = (int)($_POST['id_slot'] ?? 0);
        $pdo->prepare("DELETE FROM slot_konsultasi WHERE id = :id AND id_dosen = :dosen")
            ->execute([':id' => $idSlot, ':dosen' => $idDosen]);
        set_flash('success', 'Slot konsultasi/bimbingan berhasil dihapus.');
        redirect(BASE_URL . '/admin/jadwal-slot.php?id_dosen=' . $idDosen);
    }
}

// Ambil semua daftar dosen untuk dropdown selector
$dosenOptions = $pdo->query("SELECT d.id, u.nama, d.nidn_nip, p.nama_prodi 
                             FROM dosen d 
                             JOIN users u ON d.user_id = u.id 
                             JOIN program_studi p ON d.id_prodi = p.id 
                             ORDER BY u.nama ASC")->fetchAll();

$mkList    = $pdo->query("SELECT * FROM mata_kuliah ORDER BY kode ASC")->fetchAll();
$kelasList = $pdo->query("SELECT k.*, p.nama_prodi FROM kelas k JOIN program_studi p ON p.id = k.id_prodi ORDER BY k.nama_kelas ASC")->fetchAll();
$dosenAll  = $pdo->query("SELECT d.id, u.nama FROM dosen d JOIN users u ON u.id = d.user_id ORDER BY u.nama ASC")->fetchAll();

// Jika dosen terpilih valid, ambil data detailnya
$selectedDosen = null;
$jadwalList = [];
$slotList = [];
$currentStatus = null;

if ($selectedDosenId > 0) {
    $stmtSel = $pdo->prepare("SELECT d.*, u.nama, u.email, p.nama_prodi, p.jurusan 
                              FROM dosen d 
                              JOIN users u ON d.user_id = u.id 
                              JOIN program_studi p ON d.id_prodi = p.id 
                              WHERE d.id = :id LIMIT 1");
    $stmtSel->execute([':id' => $selectedDosenId]);
    $selectedDosen = $stmtSel->fetch();

    if ($selectedDosen) {
        // Status ketersediaan
        $stmtSt = $pdo->prepare("SELECT * FROM status_ketersediaan WHERE id_dosen = :id LIMIT 1");
        $stmtSt->execute([':id' => $selectedDosenId]);
        $currentStatus = $stmtSt->fetch();

        // Jadwal mengajar (via jadwal_dosen)
        $orderHari = "FIELD(jm.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat')";
        $stmtJ = $pdo->prepare(
            "SELECT jm.*, mk.kode AS kode_mk, mk.nama AS nama_mk,
                    k.nama_kelas, k.semester,
                    GROUP_CONCAT(u.nama ORDER BY u.nama SEPARATOR ', ') AS dosen_list
             FROM jadwal_mengajar jm
             JOIN mata_kuliah mk ON mk.id = jm.id_mk
             JOIN kelas k ON k.id = jm.id_kelas
             JOIN jadwal_dosen jd ON jd.id_jadwal = jm.id
             JOIN dosen d ON d.id = jd.id_dosen
             JOIN users u ON u.id = d.user_id
             WHERE jm.id IN (SELECT id_jadwal FROM jadwal_dosen WHERE id_dosen = :id)
             GROUP BY jm.id
             ORDER BY {$orderHari}, jm.jam_mulai ASC"
        );
        $stmtJ->execute([':id' => $selectedDosenId]);
        $jadwalList = $stmtJ->fetchAll();

        // Slot konsultasi & bimbingan
        $orderHariSlot = "FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat')";
        $stmtS = $pdo->prepare("SELECT * FROM slot_konsultasi WHERE id_dosen = :id ORDER BY {$orderHariSlot}, jam_mulai ASC");
        $stmtS->execute([':id' => $selectedDosenId]);
        $slotList = $stmtS->fetchAll();
    }
}

$page_title = 'Supervisi Jadwal & Slot Dosen';
$active_page = 'supervisi';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col-12">
        <h3 class="fw-bold text-dark mb-1"><i class="bi bi-calendar2-check-fill me-2 text-success"></i>Supervisi Jadwal & Status Ketersediaan Dosen</h3>
        <p class="text-secondary small mb-0">Administrator dapat memantau serta membantu memperbarui jadwal mengajar, slot bimbingan, dan status dosen.</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm">
        <strong class="d-block mb-1"><i class="bi bi-exclamation-octagon-fill me-1"></i>Terjadi Kesalahan:</strong>
        <ul class="mb-0 ps-3 small">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Pemilih Dosen -->
<div class="card card-custom border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= BASE_URL ?>/admin/jadwal-slot.php" class="row g-2 align-items-center">
            <div class="col-md-9 col-lg-8">
                <label for="selectDosen" class="form-label small fw-semibold text-secondary mb-1">
                    <i class="bi bi-person-video3 me-1"></i>Pilih Dosen yang Ingin Dikelola / Disupervisi:
                </label>
                <select class="form-select" id="selectDosen" name="id_dosen" onchange="this.form.submit()">
                    <option value="0">-- Pilih Dosen Polinela --</option>
                    <?php foreach ($dosenOptions as $opt): ?>
                        <option value="<?= $opt['id'] ?>" <?= $selectedDosenId === (int)$opt['id'] ? 'selected' : '' ?>>
                            <?= e($opt['nama']) ?> (NIDN: <?= e($opt['nidn_nip']) ?> - <?= e($opt['nama_prodi']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-lg-4 d-flex align-items-end">
                <button type="submit" class="btn btn-polinela w-100 py-2">
                    <i class="bi bi-arrow-right-circle me-1"></i>Buka Data Dosen
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!$selectedDosen): ?>
    <div class="card card-custom border-0 shadow-sm p-5 text-center">
        <i class="bi bi-arrow-up-circle fs-1 text-success mb-2"></i>
        <h5 class="fw-bold text-secondary">Silakan Pilih Dosen Terlebih Dahulu</h5>
        <p class="text-muted small mb-0">Pilih salah satu dosen melalui dropdown di atas untuk mengelola status ketersediaan, jadwal mengajar, dan slot bimbingannya.</p>
    </div>
<?php else: 
    $statusVal = $currentStatus['status'] ?? 'Tersedia';
    $catatanVal = $currentStatus['catatan'] ?? '';
    $berlakuVal = $currentStatus['berlaku_sampai'] ?? null;
?>
    <!-- Header Dosen Terpilih -->
    <div class="card card-custom border-0 shadow-sm p-4 mb-4 bg-white">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-initial avatar-initial-lg bg-success bg-opacity-10 text-success fw-bold">
                    <?= strtoupper(substr($selectedDosen['nama'], 0, 1)) ?>
                </div>
                <div>
                    <span class="badge bg-success-subtle text-success small mb-1"><?= e($selectedDosen['nama_prodi']) ?></span>
                    <h4 class="fw-bold text-dark mb-0"><?= e($selectedDosen['nama']) ?></h4>
                    <p class="small text-muted mb-0">NIDN: <?= e($selectedDosen['nidn_nip']) ?> &bull; <?= e($selectedDosen['email']) ?></p>
                </div>
            </div>
            <div>
                <span class="small text-muted d-block text-md-end mb-1">Status Ketersediaan Saat Ini:</span>
                <div class="text-md-end"><?= status_badge($statusVal) ?></div>
            </div>
        </div>
    </div>

    <!-- Bagian 1: Ubah Status Dosen -->
    <div class="card card-custom border-0 shadow-sm mb-4">
        <div class="card-header card-header-polinela py-3">
            <span class="fw-bold"><i class="bi bi-broadcast me-2"></i>Pembaruan Status Ketersediaan (Supervisi Admin)</span>
        </div>
        <div class="card-body p-4">
            <form action="<?= BASE_URL ?>/admin/jadwal-slot.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id_dosen" value="<?= $selectedDosenId ?>">

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Status Ketersediaan *</label>
                        <select class="form-select" name="status" required>
                            <option value="Tersedia" <?= $statusVal === 'Tersedia' ? 'selected' : '' ?>>Tersedia</option>
                            <option value="Mengajar" <?= $statusVal === 'Mengajar' ? 'selected' : '' ?>>Mengajar</option>
                            <option value="Hanya Konsultasi" <?= $statusVal === 'Hanya Konsultasi' ? 'selected' : '' ?>>Hanya Konsultasi</option>
                            <option value="Hanya Bimbingan" <?= $statusVal === 'Hanya Bimbingan' ? 'selected' : '' ?>>Hanya Bimbingan</option>
                            <option value="Tidak Tersedia" <?= $statusVal === 'Tidak Tersedia' ? 'selected' : '' ?>>Tidak Tersedia</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold text-secondary">Catatan untuk Mahasiswa</label>
                        <input type="text" class="form-control" name="catatan" value="<?= e($catatanVal) ?>" placeholder="Catatan singkat (opsional)" maxlength="255">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-secondary">Berlaku Sampai (Opsional)</label>
                        <input type="datetime-local" class="form-control" name="berlaku_sampai" value="<?= $berlakuVal ? date('Y-m-d\TH:i', strtotime($berlakuVal)) : '' ?>">
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-polinela btn-sm px-4">
                            <i class="bi bi-save me-1"></i>Simpan Perubahan Status
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Bagian 2: Jadwal Mengajar Dosen -->
    <div class="card card-custom border-0 shadow-sm mb-4">
        <div class="card-header card-header-polinela d-flex justify-content-between align-items-center py-3">
            <span class="fw-bold"><i class="bi bi-book-half me-2 text-primary"></i>Jadwal Mengajar Kuliah (<?= count($jadwalList) ?> Sesi)</span>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahJadwalAdmin">
                <i class="bi bi-plus-circle me-1"></i>Tambah Jadwal
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 40px;">No</th>
                            <th style="width: 100px;">Hari</th>
                            <th style="width: 150px;">Waktu</th>
                            <th>Mata Kuliah</th>
                            <th style="width: 120px;">Kelas</th>
                            <th style="width: 90px;">Tipe</th>
                            <th style="width: 60px;">SKS</th>
                            <th style="width: 90px;">Mode</th>
                            <?php if (TAMPILKAN_RUANG): ?>
                            <th>Ruang</th>
                            <?php endif; ?>
                            <th>Dosen Pengampu</th>
                            <th class="text-end pe-4" style="width: 80px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($jadwalList)): ?>
                            <tr><td colspan="<?= TAMPILKAN_RUANG ? 11 : 10 ?>" class="text-center py-4 text-muted small">Dosen ini belum memiliki jadwal mengajar.</td></tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($jadwalList as $j): ?>
                                <tr>
                                    <td class="ps-4 text-muted small"><?= $no++ ?></td>
                                    <td><span class="badge bg-success-subtle text-success"><?= e($j['hari']) ?></span></td>
                                    <td class="small"><strong><?= substr($j['jam_mulai'], 0, 5) ?></strong>–<strong><?= substr($j['jam_selesai'], 0, 5) ?></strong> WIB</td>
                                    <td>
                                        <span class="badge bg-dark bg-opacity-75 font-monospace small me-1"><?= e($j['kode_mk']) ?></span>
                                        <strong class="text-dark small"><?= e($j['nama_mk']) ?></strong>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= e($j['nama_kelas']) ?></span></td>
                                    <td><?= tipe_badge($j['tipe_sesi']) ?></td>
                                    <td class="text-center"><span class="badge bg-light text-dark border"><?= (int)$j['sks'] ?></span></td>
                                    <td><?= mode_badge($j['mode']) ?></td>
                                    <?php if (TAMPILKAN_RUANG): ?>
                                    <td class="small text-secondary"><?= $j['ruang'] ? e($j['ruang']) : '<span class="text-muted">-</span>' ?></td>
                                    <?php endif; ?>
                                    <td class="small"><?= e($j['dosen_list']) ?></td>
                                    <td class="text-end pe-4">
                                        <form action="<?= BASE_URL ?>/admin/jadwal-slot.php" method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_jadwal">
                                            <input type="hidden" name="id_dosen" value="<?= $selectedDosenId ?>">
                                            <input type="hidden" name="id_jadwal" value="<?= $j['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Hapus jadwal mata kuliah ini?">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bagian 3: Slot Konsultasi & Bimbingan -->
    <div class="card card-custom border-0 shadow-sm mb-4">
        <div class="card-header card-header-polinela d-flex justify-content-between align-items-center py-3">
            <span class="fw-bold"><i class="bi bi-clock-history me-2 text-warning"></i>Slot Konsultasi & Bimbingan (<?= count($slotList) ?> Slot)</span>
            <button type="button" class="btn btn-sm btn-warning text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#modalTambahSlotAdmin">
                <i class="bi bi-plus-circle me-1"></i>Tambah Slot
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 50px;">No</th>
                            <th style="width: 120px;">Hari</th>
                            <th style="width: 160px;">Waktu Slot</th>
                            <th style="width: 150px;">Jenis</th>
                            <th>Catatan</th>
                            <th style="width: 110px;">Status</th>
                            <th class="text-end pe-4" style="width: 140px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($slotList)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted small">Dosen ini belum memiliki slot bimbingan/konsultasi.</td></tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($slotList as $s): ?>
                                <tr>
                                    <td class="ps-4 text-muted small"><?= $no++ ?></td>
                                    <td><span class="badge bg-success-subtle text-success"><?= e($s['hari']) ?></span></td>
                                    <td class="small"><strong><?= substr($s['jam_mulai'], 0, 5) ?></strong> - <strong><?= substr($s['jam_selesai'], 0, 5) ?></strong> WIB</td>
                                    <td>
                                        <span class="badge <?= $s['jenis'] === 'bimbingan' ? 'bg-info text-dark' : 'bg-warning text-dark' ?>">
                                            <?= $s['jenis'] === 'bimbingan' ? 'Bimbingan TA' : 'Konsultasi' ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted"><?= !empty($s['catatan']) ? e($s['catatan']) : '-' ?></td>
                                    <td>
                                        <form action="<?= BASE_URL ?>/admin/jadwal-slot.php" method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_slot">
                                            <input type="hidden" name="id_dosen" value="<?= $selectedDosenId ?>">
                                            <input type="hidden" name="id_slot" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-sm <?= $s['aktif'] ? 'btn-success' : 'btn-secondary' ?> py-0 px-2 rounded-pill small">
                                                <?= $s['aktif'] ? 'Aktif' : 'Nonaktif' ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="text-end pe-4">
                                        <form action="<?= BASE_URL ?>/admin/jadwal-slot.php" method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_slot">
                                            <input type="hidden" name="id_dosen" value="<?= $selectedDosenId ?>">
                                            <input type="hidden" name="id_slot" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Hapus slot ini?">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Tambah Jadwal Admin -->
    <div class="modal fade" id="modalTambahJadwalAdmin" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form action="<?= BASE_URL ?>/admin/jadwal-slot.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_jadwal">
                    <input type="hidden" name="id_dosen" value="<?= $selectedDosenId ?>">

                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Tambah Jadwal Mengajar</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Mata Kuliah *</label>
                                <select class="form-select" name="id_mk" required>
                                    <option value="">-- Pilih Mata Kuliah --</option>
                                    <?php foreach ($mkList as $mk): ?>
                                    <option value="<?= $mk['id'] ?>">[<?= e($mk['kode']) ?>] <?= e($mk['nama']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Kelas *</label>
                                <select class="form-select" name="id_kelas" required>
                                    <option value="">-- Pilih Kelas --</option>
                                    <?php foreach ($kelasList as $kl): ?>
                                    <option value="<?= $kl['id'] ?>"><?= e($kl['nama_kelas']) ?> (Sem. <?= $kl['semester'] ?>, <?= e($kl['tahun_ajaran']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-secondary">Hari Perkuliahan *</label>
                                <select class="form-select" name="hari" required>
                                    <option value="">-- Pilih Hari --</option>
                                    <?php foreach ($hariList as $h): ?>
                                    <option value="<?= $h ?>"><?= $h ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-secondary">Jam Mulai *</label>
                                <input type="time" class="form-control" name="jam_mulai" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-secondary">Jam Selesai *</label>
                                <input type="time" class="form-control" name="jam_selesai" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-secondary">Tipe Sesi *</label>
                                <select class="form-select" name="tipe_sesi" required>
                                    <option value="teori">Teori</option>
                                    <option value="praktik">Praktik</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold text-secondary">SKS *</label>
                                <input type="number" class="form-control" name="sks" min="1" max="6" value="2" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold text-secondary">Mode *</label>
                                <select class="form-select" name="mode" id="adminMode" required onchange="toggleAdminRuang(this.value)">
                                    <option value="luring">Luring</option>
                                    <option value="daring">Daring</option>
                                </select>
                            </div>
                            <div class="col-md-3" id="adminRuangWrapper">
                                <label class="form-label small fw-semibold text-secondary">Ruang</label>
                                <input type="text" class="form-control" name="ruang" id="adminRuang" placeholder="contoh: Lab 3.1">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold text-secondary">
                                    Dosen Pengampu * <span class="text-muted">(Bisa pilih lebih dari 1 / team teaching)</span>
                                </label>
                                <select class="form-select" name="dosen_ids[]" multiple size="5" required>
                                    <?php foreach ($dosenAll as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= $d['id'] == $selectedDosenId ? 'selected' : '' ?>>
                                        <?= e($d['nama']) ?><?= $d['id'] == $selectedDosenId ? ' (Dosen Terpilih)' : '' ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text small text-muted">Tahan <kbd>Ctrl</kbd> atau <kbd>Cmd</kbd> untuk memilih beberapa dosen.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm px-3">Simpan Jadwal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
    function toggleAdminRuang(mode) {
        const wrapper = document.getElementById('adminRuangWrapper');
        const input = document.getElementById('adminRuang');
        if (mode === 'daring') {
            wrapper.style.opacity = '0.5';
            input.disabled = true;
            input.value = '';
        } else {
            wrapper.style.opacity = '1';
            input.disabled = false;
        }
    }
    </script>

    <!-- Modal Tambah Slot Admin -->
    <div class="modal fade" id="modalTambahSlotAdmin" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form action="<?= BASE_URL ?>/admin/jadwal-slot.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_slot">
                    <input type="hidden" name="id_dosen" value="<?= $selectedDosenId ?>">

                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Tambah Slot Bimbingan/Konsultasi</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Hari *</label>
                            <select class="form-select" name="hari" required>
                                <option value="">-- Pilih Hari --</option>
                                <?php foreach ($hariList as $h): ?>
                                    <option value="<?= $h ?>"><?= $h ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-secondary">Jam Mulai *</label>
                                <input type="time" class="form-control" name="jam_mulai" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-secondary">Jam Selesai *</label>
                                <input type="time" class="form-control" name="jam_selesai" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Jenis Slot *</label>
                            <select class="form-select" name="jenis" required>
                                <option value="konsultasi">Konsultasi Akademik</option>
                                <option value="bimbingan">Bimbingan Tugas Akhir / PKL</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Catatan / Petunjuk (Opsional)</label>
                            <input type="text" class="form-control" name="catatan" maxlength="255">
                        </div>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" role="switch" name="aktif" value="1" checked>
                            <label class="form-check-label fw-semibold text-secondary small">Status Langsung Aktif</label>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning text-dark fw-bold btn-sm px-3">Simpan Slot</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

