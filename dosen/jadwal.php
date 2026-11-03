<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Pengelolaan CRUD Jadwal Mengajar (Skema Normalized v2)
// File: dosen/jadwal.php
// ==============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('dosen');

$pdo  = get_db_connection();
$user = current_user();

// Dapatkan ID Dosen
$idDosen = $_SESSION['dosen_id'] ?? null;
if (!$idDosen) {
    $stmt = $pdo->prepare("SELECT id FROM dosen WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $user['id']]);
    $d = $stmt->fetch();
    $idDosen = $d ? (int)$d['id'] : 0;
    $_SESSION['dosen_id'] = $idDosen;
}

$errors   = [];
$hariList = hari_list();

// Ambil daftar mata kuliah dan kelas untuk form
$mkList    = $pdo->query("SELECT * FROM mata_kuliah ORDER BY kode ASC")->fetchAll();
$kelasList = $pdo->query("SELECT k.*, p.nama_prodi FROM kelas k JOIN program_studi p ON p.id = k.id_prodi ORDER BY k.nama_kelas ASC")->fetchAll();
$dosenAll  = $pdo->query("SELECT d.id, u.nama FROM dosen d JOIN users u ON u.id = d.user_id ORDER BY u.nama ASC")->fetchAll();

// ==============================================================
// PROSES AKSI POST (CREATE / UPDATE / DELETE)
// ==============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Token keamanan CSRF tidak valid. Silakan coba lagi.');
        redirect(BASE_URL . '/dosen/jadwal.php');
    }

    $action = $_POST['action'] ?? '';

    // 1. AKSI HAPUS JADWAL
    if ($action === 'delete') {
        $idJadwal = (int)($_POST['id'] ?? 0);
        // Pastikan dosen ini memang pengampu jadwal tersebut
        $stmtCek = $pdo->prepare("SELECT 1 FROM jadwal_dosen WHERE id_jadwal = :jid AND id_dosen = :did LIMIT 1");
        $stmtCek->execute([':jid' => $idJadwal, ':did' => $idDosen]);
        if ($stmtCek->fetch()) {
            // Hapus jadwal (jadwal_dosen di-cascade)
            $pdo->prepare("DELETE FROM jadwal_mengajar WHERE id = :id")->execute([':id' => $idJadwal]);
            set_flash('success', 'Jadwal mengajar berhasil dihapus.');
        } else {
            set_flash('danger', 'Anda tidak berwenang menghapus jadwal ini.');
        }
        redirect(BASE_URL . '/dosen/jadwal.php');
    }

    // 2. AKSI TAMBAH ATAU EDIT JADWAL
    if ($action === 'create' || $action === 'update') {
        $idJadwal   = (int)($_POST['id'] ?? 0);
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

        // Pastikan dosen sendiri selalu termasuk
        if (!in_array($idDosen, $dosenIds)) {
            $dosenIds[] = $idDosen;
        }

        // Validasi input dasar
        if (!in_array($hari, $hariList, true)) {
            $errors[] = 'Pilihan hari perkuliahan tidak valid.';
        }
        if (empty($jamMulai) || empty($jamSelesai)) {
            $errors[] = 'Jam mulai dan jam selesai wajib diisi.';
        } elseif ($jamSelesai <= $jamMulai) {
            $errors[] = 'Jam selesai perkuliahan harus lebih akhir dari jam mulai.';
        }
        if ($idMk <= 0) {
            $errors[] = 'Mata kuliah wajib dipilih.';
        }
        if ($idKelas <= 0) {
            $errors[] = 'Kelas wajib dipilih.';
        }
        if (!in_array($tipeSesi, ['teori', 'praktik'], true)) {
            $errors[] = 'Tipe sesi tidak valid.';
        }
        if (!in_array($mode, ['daring', 'luring'], true)) {
            $errors[] = 'Mode perkuliahan tidak valid.';
        }
        if ($sks < 1 || $sks > 6) {
            $errors[] = 'SKS harus antara 1 sampai 6.';
        }
        if (empty($dosenIds)) {
            $errors[] = 'Minimal satu dosen pengampu harus dipilih.';
        }

        // Validasi bentrok untuk setiap dosen yang dipilih
        if (empty($errors)) {
            $excludeId = ($action === 'update') ? $idJadwal : null;
            foreach ($dosenIds as $did) {
                $conflictMsg = check_time_collision($pdo, $did, $hari, $jamMulai, $jamSelesai, $excludeId, null);
                if ($conflictMsg) {
                    // Ambil nama dosen yang bentrok
                    $stmtNm = $pdo->prepare("SELECT u.nama FROM dosen d JOIN users u ON u.id=d.user_id WHERE d.id=:id LIMIT 1");
                    $stmtNm->execute([':id' => $did]);
                    $nm = $stmtNm->fetchColumn() ?: "Dosen #$did";
                    $errors[] = "[{$nm}] {$conflictMsg}";
                }
            }
        }

        // Eksekusi ke database jika tidak ada galat
        if (empty($errors)) {
            $ruangFinal = ($mode === 'daring') ? null : (empty($ruang) ? null : $ruang);

            if ($action === 'create') {
                $stmtIns = $pdo->prepare(
                    "INSERT INTO jadwal_mengajar (id_mk, id_kelas, hari, jam_mulai, jam_selesai, tipe_sesi, sks, mode, ruang)
                     VALUES (:id_mk, :id_kelas, :hari, :jam_mulai, :jam_selesai, :tipe_sesi, :sks, :mode, :ruang)"
                );
                $stmtIns->execute([
                    ':id_mk'      => $idMk,
                    ':id_kelas'   => $idKelas,
                    ':hari'       => $hari,
                    ':jam_mulai'  => $jamMulai,
                    ':jam_selesai'=> $jamSelesai,
                    ':tipe_sesi'  => $tipeSesi,
                    ':sks'        => $sks,
                    ':mode'       => $mode,
                    ':ruang'      => $ruangFinal,
                ]);
                $newId = (int)$pdo->lastInsertId();

                // Insert relasi dosen-jadwal
                $stmtRel = $pdo->prepare("INSERT IGNORE INTO jadwal_dosen (id_jadwal, id_dosen) VALUES (:jid, :did)");
                foreach ($dosenIds as $did) {
                    $stmtRel->execute([':jid' => $newId, ':did' => $did]);
                }

                set_flash('success', 'Jadwal mengajar baru berhasil ditambahkan.');
            } else {
                // Update jadwal
                $stmtUpd = $pdo->prepare(
                    "UPDATE jadwal_mengajar
                     SET id_mk=:id_mk, id_kelas=:id_kelas, hari=:hari, jam_mulai=:jam_mulai,
                         jam_selesai=:jam_selesai, tipe_sesi=:tipe_sesi, sks=:sks, mode=:mode, ruang=:ruang
                     WHERE id = :id"
                );
                $stmtUpd->execute([
                    ':id_mk'      => $idMk,
                    ':id_kelas'   => $idKelas,
                    ':hari'       => $hari,
                    ':jam_mulai'  => $jamMulai,
                    ':jam_selesai'=> $jamSelesai,
                    ':tipe_sesi'  => $tipeSesi,
                    ':sks'        => $sks,
                    ':mode'       => $mode,
                    ':ruang'      => $ruangFinal,
                    ':id'         => $idJadwal,
                ]);

                // Perbarui relasi dosen (hapus lama, insert baru)
                $pdo->prepare("DELETE FROM jadwal_dosen WHERE id_jadwal = :jid")->execute([':jid' => $idJadwal]);
                $stmtRel = $pdo->prepare("INSERT IGNORE INTO jadwal_dosen (id_jadwal, id_dosen) VALUES (:jid, :did)");
                foreach ($dosenIds as $did) {
                    $stmtRel->execute([':jid' => $idJadwal, ':did' => $did]);
                }

                set_flash('success', 'Jadwal mengajar berhasil diperbarui.');
            }
            redirect(BASE_URL . '/dosen/jadwal.php');
        }
    }
}

// Ambil jadwal yang melibatkan dosen ini (via jadwal_dosen)
$orderHari = "FIELD(jm.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat')";
$stmtList = $pdo->prepare(
    "SELECT jm.*, mk.kode AS kode_mk, mk.nama AS nama_mk,
            k.nama_kelas, k.semester, k.tahun_ajaran,
            GROUP_CONCAT(u.nama ORDER BY u.nama SEPARATOR ', ') AS dosen_list
     FROM jadwal_mengajar jm
     JOIN mata_kuliah mk ON mk.id = jm.id_mk
     JOIN kelas k ON k.id = jm.id_kelas
     JOIN jadwal_dosen jd ON jd.id_jadwal = jm.id
     JOIN dosen d ON d.id = jd.id_dosen
     JOIN users u ON u.id = d.user_id
     WHERE jm.id IN (SELECT id_jadwal FROM jadwal_dosen WHERE id_dosen = :id_dosen)
     GROUP BY jm.id
     ORDER BY {$orderHari}, jm.jam_mulai ASC"
);
$stmtList->execute([':id_dosen' => $idDosen]);
$jadwalList = $stmtList->fetchAll();

$page_title = 'Kelola Jadwal Mengajar';
$active_page = 'jadwal';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="fw-bold text-dark mb-1"><i class="bi bi-calendar3 me-2 text-success"></i>Jadwal Mengajar Anda</h3>
        <p class="text-secondary small mb-0">Kelola jadwal kuliah mingguan. Sistem otomatis mencegah bentrok waktu dengan slot bimbingan/konsultasi.</p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <button type="button" class="btn btn-polinela shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahJadwal">
            <i class="bi bi-plus-circle me-1"></i>Tambah Jadwal Baru
        </button>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm">
        <strong class="d-block mb-1"><i class="bi bi-exclamation-octagon-fill me-1"></i>Gagal Menyimpan Jadwal:</strong>
        <ul class="mb-0 ps-3 small">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Tabel Daftar Jadwal Mengajar -->
<div class="card card-custom border-0 shadow-sm mb-4">
    <div class="card-header card-header-polinela d-flex justify-content-between align-items-center py-3">
        <span class="fw-bold"><i class="bi bi-table me-2"></i>Daftar Jadwal Kuliah Mingguan</span>
        <span class="badge bg-success"><?= count($jadwalList) ?> Jadwal Terdaftar</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($jadwalList)): ?>
            <div class="text-center py-5 px-3">
                <i class="bi bi-calendar-plus fs-1 text-muted d-block mb-3 opacity-50"></i>
                <h5 class="text-muted">Belum Ada Jadwal</h5>
                <p class="text-muted small mb-4">Jadwal mengajar Anda belum terdaftar. Tambahkan jadwal kuliah Anda untuk semester ini.</p>
                <button class="btn btn-polinela" data-bs-toggle="modal" data-bs-target="#modalTambahJadwal">
                    <i class="bi bi-plus-circle me-1"></i>Tambah Jadwal Pertama
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">No</th>
                            <th>Hari</th>
                            <th>Waktu</th>
                            <th>Mata Kuliah</th>
                            <th>Kelas</th>
                            <th>Tipe</th>
                            <th>SKS</th>
                            <th>Mode</th>
                            <?php if (TAMPILKAN_RUANG): ?>
                            <th>Ruang</th>
                            <?php endif; ?>
                            <th>Dosen Pengampu</th>
                            <th class="text-center pe-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($jadwalList as $j): ?>
                            <tr>
                                <td class="ps-4 text-muted small"><?= $no++ ?></td>
                                <td>
                                    <span class="badge bg-success-subtle text-success fw-semibold px-2">
                                        <?= e($j['hari']) ?>
                                    </span>
                                </td>
                                <td class="small">
                                    <strong><?= substr($j['jam_mulai'],0,5) ?></strong>–<strong><?= substr($j['jam_selesai'],0,5) ?></strong>
                                    <span class="text-muted">WIB</span>
                                </td>
                                <td>
                                    <span class="badge bg-dark bg-opacity-75 font-monospace small me-1"><?= e($j['kode_mk']) ?></span>
                                    <strong class="text-dark small"><?= e($j['nama_mk']) ?></strong>
                                </td>
                                <td class="small"><?= e($j['nama_kelas']) ?></td>
                                <td><?= tipe_badge($j['tipe_sesi']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border"><?= (int)$j['sks'] ?></span>
                                </td>
                                <td><?= mode_badge($j['mode']) ?></td>
                                <?php if (TAMPILKAN_RUANG): ?>
                                <td class="small text-secondary"><?= $j['ruang'] ? e($j['ruang']) : '<span class="text-muted">-</span>' ?></td>
                                <?php endif; ?>
                                <td class="small"><?= e($j['dosen_list']) ?></td>
                                <td class="text-center pe-4">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <button type="button" class="btn btn-sm btn-outline-warning"
                                                title="Edit jadwal"
                                                onclick="editJadwal(<?= htmlspecialchars(json_encode($j), ENT_QUOTES) ?>)">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>
                                        <form method="POST" action="<?= BASE_URL ?>/dosen/jadwal.php" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $j['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    title="Hapus jadwal"
                                                    data-confirm="Hapus jadwal <?= e($j['nama_mk']) ?> hari <?= e($j['hari']) ?>? Tindakan ini tidak dapat dibatalkan.">
                                                <i class="bi bi-trash3-fill"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ======================== MODAL TAMBAH JADWAL ======================== -->
<div class="modal fade" id="modalTambahJadwal" tabindex="-1" aria-labelledby="modalTambahLabel">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header card-header-polinela py-3">
                <h5 class="modal-title fw-bold" id="modalTambahLabel">
                    <i class="bi bi-calendar-plus me-2"></i>
                    <span id="modalTambahTitle">Tambah Jadwal Mengajar</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/dosen/jadwal.php" id="formJadwal">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formId" value="0">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Mata Kuliah -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-secondary">Mata Kuliah *</label>
                            <select class="form-select" name="id_mk" id="formIdMk" required>
                                <option value="">-- Pilih Mata Kuliah --</option>
                                <?php foreach ($mkList as $mk): ?>
                                <option value="<?= $mk['id'] ?>">
                                    [<?= e($mk['kode']) ?>] <?= e($mk['nama']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Kelas -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-secondary">Kelas *</label>
                            <select class="form-select" name="id_kelas" id="formIdKelas" required>
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach ($kelasList as $kl): ?>
                                <option value="<?= $kl['id'] ?>">
                                    <?= e($kl['nama_kelas']) ?> (Sem. <?= $kl['semester'] ?>, <?= e($kl['tahun_ajaran']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Hari -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-secondary">Hari *</label>
                            <select class="form-select" name="hari" id="formHari" required>
                                <?php foreach ($hariList as $h): ?>
                                <option value="<?= $h ?>"><?= $h ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Jam Mulai -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-secondary">Jam Mulai *</label>
                            <input type="time" class="form-control" name="jam_mulai" id="formJamMulai" required>
                        </div>

                        <!-- Jam Selesai -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-secondary">Jam Selesai *</label>
                            <input type="time" class="form-control" name="jam_selesai" id="formJamSelesai" required>
                        </div>

                        <!-- Tipe Sesi -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-secondary">Tipe Sesi *</label>
                            <select class="form-select" name="tipe_sesi" id="formTipeSesi" required>
                                <option value="teori">Teori</option>
                                <option value="praktik">Praktik</option>
                            </select>
                        </div>

                        <!-- SKS -->
                        <div class="col-md-2">
                            <label class="form-label fw-semibold small text-secondary">SKS *</label>
                            <input type="number" class="form-control" name="sks" id="formSks"
                                   min="1" max="6" value="2" required>
                        </div>

                        <!-- Mode -->
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small text-secondary">Mode *</label>
                            <select class="form-select" name="mode" id="formMode" required onchange="toggleRuang(this.value)">
                                <option value="luring">Luring</option>
                                <option value="daring">Daring</option>
                            </select>
                        </div>

                        <!-- Ruang -->
                        <div class="col-md-3" id="ruangWrapper">
                            <label class="form-label fw-semibold small text-secondary">Ruang</label>
                            <input type="text" class="form-control" name="ruang" id="formRuang"
                                   placeholder="contoh: Lab Komputer 3.1">
                            <div class="form-text small text-muted">Kosongkan jika Daring.</div>
                        </div>

                        <!-- Dosen Pengampu -->
                        <div class="col-12">
                            <label class="form-label fw-semibold small text-secondary">
                                Dosen Pengampu * <span class="text-muted">(Bisa lebih dari satu / team teaching)</span>
                            </label>
                            <select class="form-select" name="dosen_ids[]" id="formDosenIds" multiple size="5" required>
                                <?php foreach ($dosenAll as $d): ?>
                                <option value="<?= $d['id'] ?>"
                                    <?= $d['id'] == $idDosen ? 'selected' : '' ?>>
                                    <?= e($d['nama']) ?><?= $d['id'] == $idDosen ? ' (Anda)' : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small text-muted">
                                <i class="bi bi-info-circle me-1"></i>Tahan <kbd>Ctrl</kbd> (Windows) atau <kbd>Cmd</kbd> (Mac) untuk memilih lebih dari satu dosen.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-polinela fw-semibold">
                        <i class="bi bi-save me-1"></i>Simpan Jadwal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleRuang(mode) {
    const wrapper = document.getElementById('ruangWrapper');
    const ruangInput = document.getElementById('formRuang');
    if (mode === 'daring') {
        wrapper.style.opacity = '0.5';
        ruangInput.disabled = true;
        ruangInput.value = '';
    } else {
        wrapper.style.opacity = '1';
        ruangInput.disabled = false;
    }
}

function editJadwal(data) {
    document.getElementById('formAction').value = 'update';
    document.getElementById('formId').value = data.id;
    document.getElementById('modalTambahTitle').textContent = 'Edit Jadwal Mengajar';
    document.getElementById('formIdMk').value = data.id_mk;
    document.getElementById('formIdKelas').value = data.id_kelas;
    document.getElementById('formHari').value = data.hari;
    document.getElementById('formJamMulai').value = data.jam_mulai ? data.jam_mulai.substring(0,5) : '';
    document.getElementById('formJamSelesai').value = data.jam_selesai ? data.jam_selesai.substring(0,5) : '';
    document.getElementById('formTipeSesi').value = data.tipe_sesi;
    document.getElementById('formSks').value = data.sks;
    document.getElementById('formMode').value = data.mode;
    document.getElementById('formRuang').value = data.ruang || '';
    toggleRuang(data.mode);

    // Reset semua opsi dosen kemudian pilih yang sesuai (perlu AJAX atau reload modal dengan data dosen)
    // Untuk sementara, set ulang ke dosen login
    const sel = document.getElementById('formDosenIds');
    for (let opt of sel.options) {
        opt.selected = false;
    }
    // Minimal pilih dosen yang login
    for (let opt of sel.options) {
        if (parseInt(opt.value) === <?= $idDosen ?>) {
            opt.selected = true;
        }
    }

    const modal = new bootstrap.Modal(document.getElementById('modalTambahJadwal'));
    modal.show();
}

// Reset modal saat ditutup
document.getElementById('modalTambahJadwal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('formAction').value = 'create';
    document.getElementById('formId').value = '0';
    document.getElementById('modalTambahTitle').textContent = 'Tambah Jadwal Mengajar';
    document.getElementById('formJadwal').reset();
    toggleRuang('luring');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
