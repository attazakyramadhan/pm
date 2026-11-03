<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Pengelolaan CRUD Slot Konsultasi & Bimbingan TA Dosen
// File: dosen/slot.php
// ==============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('dosen');

$pdo = get_db_connection();
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

$errors = [];
$hariList = hari_list();

// ==============================================================
// PROSES AKSI POST (CREATE / UPDATE / TOGGLE / DELETE)
// ==============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Token keamanan CSRF tidak valid. Silakan coba lagi.');
        redirect(BASE_URL . '/dosen/slot.php');
    }

    $action = $_POST['action'] ?? '';

    // 1. AKSI TOGGLE AKTIF / NONAKTIF
    if ($action === 'toggle') {
        $idSlot = (int)($_POST['id'] ?? 0);
        $stmtFind = $pdo->prepare("SELECT aktif FROM slot_konsultasi WHERE id = :id AND id_dosen = :id_dosen");
        $stmtFind->execute([':id' => $idSlot, ':id_dosen' => $idDosen]);
        $currSlot = $stmtFind->fetch();

        if ($currSlot) {
            $newAktif = $currSlot['aktif'] ? 0 : 1;
            $stmtToggle = $pdo->prepare("UPDATE slot_konsultasi SET aktif = :aktif WHERE id = :id AND id_dosen = :id_dosen");
            $stmtToggle->execute([':aktif' => $newAktif, ':id' => $idSlot, ':id_dosen' => $idDosen]);
            $statusText = $newAktif ? 'diaktifkan' : 'dinonaktifkan sementara';
            set_flash('success', "Slot berhasil {$statusText}.");
        }
        redirect(BASE_URL . '/dosen/slot.php');
    }

    // 2. AKSI HAPUS SLOT
    if ($action === 'delete') {
        $idSlot = (int)($_POST['id'] ?? 0);
        $stmtDel = $pdo->prepare("DELETE FROM slot_konsultasi WHERE id = :id AND id_dosen = :id_dosen");
        $stmtDel->execute([':id' => $idSlot, ':id_dosen' => $idDosen]);
        
        set_flash('success', 'Slot konsultasi/bimbingan berhasil dihapus.');
        redirect(BASE_URL . '/dosen/slot.php');
    }

    // 3. AKSI TAMBAH ATAU EDIT SLOT
    if ($action === 'create' || $action === 'update') {
        $idSlot = (int)($_POST['id'] ?? 0);
        $hari = trim($_POST['hari'] ?? '');
        $jamMulai = trim($_POST['jam_mulai'] ?? '');
        $jamSelesai = trim($_POST['jam_selesai'] ?? '');
        $jenis = trim($_POST['jenis'] ?? '');
        $catatan = trim($_POST['catatan'] ?? '');
        $aktif = isset($_POST['aktif']) ? 1 : 0;

        // Validasi dasar
        if (!in_array($hari, $hariList, true)) {
            $errors[] = 'Pilihan hari tidak valid.';
        }
        if (empty($jamMulai) || empty($jamSelesai)) {
            $errors[] = 'Jam mulai dan jam selesai wajib diisi.';
        } elseif ($jamSelesai <= $jamMulai) {
            $errors[] = 'Jam selesai slot harus lebih akhir dari jam mulai.';
        }
        if (!in_array($jenis, ['konsultasi', 'bimbingan'], true)) {
            $errors[] = 'Pilihan jenis slot tidak valid (harus Konsultasi atau Bimbingan).';
        }

        // Validasi bentrok waktu (jika slot aktif)
        if (empty($errors) && $aktif === 1) {
            $excludeId = ($action === 'update') ? $idSlot : null;
            $conflictMsg = check_time_collision($pdo, $idDosen, $hari, $jamMulai, $jamSelesai, null, $excludeId);
            if ($conflictMsg) {
                $errors[] = $conflictMsg;
            }
        }

        // Eksekusi penyimpanan
        if (empty($errors)) {
            $catatanVal = !empty($catatan) ? mb_substr($catatan, 0, 255) : null;
            $jenisLabel = $jenis === 'bimbingan' ? 'Bimbingan TA' : 'Konsultasi';

            if ($action === 'create') {
                $stmtInsert = $pdo->prepare("INSERT INTO slot_konsultasi (id_dosen, hari, jam_mulai, jam_selesai, jenis, catatan, aktif) 
                                             VALUES (:id_dosen, :hari, :jam_mulai, :jam_selesai, :jenis, :catatan, :aktif)");
                $stmtInsert->execute([
                    ':id_dosen'    => $idDosen,
                    ':hari'        => $hari,
                    ':jam_mulai'   => $jamMulai,
                    ':jam_selesai' => $jamSelesai,
                    ':jenis'       => $jenis,
                    ':catatan'     => $catatanVal,
                    ':aktif'       => $aktif,
                ]);
                set_flash('success', "Slot {$jenisLabel} hari {$hari} pukul {$jamMulai}-{$jamSelesai} berhasil ditambahkan.");
            } else {
                $stmtUpdate = $pdo->prepare("UPDATE slot_konsultasi 
                                             SET hari = :hari, jam_mulai = :jam_mulai, jam_selesai = :jam_selesai, 
                                                 jenis = :jenis, catatan = :catatan, aktif = :aktif 
                                             WHERE id = :id AND id_dosen = :id_dosen");
                $stmtUpdate->execute([
                    ':hari'        => $hari,
                    ':jam_mulai'   => $jamMulai,
                    ':jam_selesai' => $jamSelesai,
                    ':jenis'       => $jenis,
                    ':catatan'     => $catatanVal,
                    ':aktif'       => $aktif,
                    ':id'          => $idSlot,
                    ':id_dosen'    => $idDosen,
                ]);
                set_flash('success', "Slot {$jenisLabel} berhasil diperbarui.");
            }
            redirect(BASE_URL . '/dosen/slot.php');
        }
    }
}

// Ambil semua slot milik dosen
$orderHari = "FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat')";
$stmtSlots = $pdo->prepare("SELECT * FROM slot_konsultasi WHERE id_dosen = :id_dosen ORDER BY {$orderHari}, jam_mulai ASC");
$stmtSlots->execute([':id_dosen' => $idDosen]);
$slotList = $stmtSlots->fetchAll();

$page_title = 'Kelola Slot Konsultasi & Bimbingan';
$active_page = 'slot';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="fw-bold text-dark mb-1"><i class="bi bi-clock-history me-2 text-success"></i>Slot Konsultasi & Bimbingan</h3>
        <p class="text-secondary small mb-0">Atur ketersediaan waktu untuk konsultasi akademik dan bimbingan Tugas Akhir bagi mahasiswa.</p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <button type="button" class="btn btn-polinela shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahSlot">
            <i class="bi bi-plus-circle me-1"></i>Tambah Slot Baru
        </button>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm">
        <strong class="d-block mb-1"><i class="bi bi-exclamation-octagon-fill me-1"></i>Gagal Menyimpan Slot:</strong>
        <ul class="mb-0 ps-3 small">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Tabel Daftar Slot -->
<div class="card card-custom border-0 shadow-sm">
    <div class="card-header card-header-polinela d-flex justify-content-between align-items-center py-3">
        <span class="fw-bold"><i class="bi bi-calendar2-check me-2"></i>Daftar Slot Konsultasi & Bimbingan Mahasiswa</span>
        <span class="badge bg-success"><?= count($slotList) ?> Slot Dikonfigurasi</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($slotList)): ?>
            <div class="text-center py-5 px-3">
                <i class="bi bi-clock-history fs-1 text-muted d-block mb-2"></i>
                <h5 class="fw-semibold text-secondary">Belum Ada Slot Waktu</h5>
                <p class="text-muted small mb-3">Tentukan waktu buka konsultasi atau bimbingan tugas akhir agar mahasiswa dapat menemui Anda pada waktu yang tepat.</p>
                <button type="button" class="btn btn-sm btn-outline-polinela" data-bs-toggle="modal" data-bs-target="#modalTambahSlot">
                    <i class="bi bi-plus-lg me-1"></i>Tambah Slot Pertama
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 50px;">No</th>
                            <th style="width: 130px;">Hari</th>
                            <th style="width: 170px;">Waktu Slot</th>
                            <th style="width: 150px;">Jenis Slot</th>
                            <th>Catatan / Petunjuk</th>
                            <th style="width: 130px;">Status</th>
                            <th class="text-end pe-4" style="width: 160px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($slotList as $s): ?>
                            <tr class="<?= $s['aktif'] ? '' : 'table-light opacity-75' ?>">
                                <td class="ps-4 text-muted small"><?= $no++ ?></td>
                                <td>
                                    <span class="badge bg-success-subtle text-success fw-bold px-2 py-1">
                                        <?= e($s['hari']) ?>
                                    </span>
                                </td>
                                <td>
                                    <i class="bi bi-clock me-1 text-secondary"></i>
                                    <strong><?= substr($s['jam_mulai'], 0, 5) ?></strong> - <strong><?= substr($s['jam_selesai'], 0, 5) ?></strong> WIB
                                </td>
                                <td>
                                    <?php if ($s['jenis'] === 'bimbingan'): ?>
                                        <span class="badge bg-info text-dark px-2 py-1">
                                            <i class="bi bi-mortarboard-fill me-1"></i>Bimbingan TA
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark px-2 py-1">
                                            <i class="bi bi-chat-text-fill me-1"></i>Konsultasi
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= !empty($s['catatan']) ? e($s['catatan']) : '<span class="text-muted small fst-italic">Tanpa catatan khusus</span>' ?>
                                </td>
                                <td>
                                    <form action="<?= BASE_URL ?>/dosen/slot.php" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $s['aktif'] ? 'btn-success' : 'btn-secondary' ?> py-0 px-2 rounded-pill" 
                                                title="Klik untuk ubah aktif/nonaktif">
                                            <i class="bi <?= $s['aktif'] ? 'bi-check-circle-fill' : 'bi-pause-circle-fill' ?> me-1"></i>
                                            <?= $s['aktif'] ? 'Aktif' : 'Nonaktif' ?>
                                        </button>
                                    </form>
                                </td>
                                <td class="text-end pe-4">
                                    <button type="button" class="btn btn-sm btn-outline-primary me-1 btn-edit-slot" 
                                            data-id="<?= $s['id'] ?>"
                                            data-hari="<?= e($s['hari']) ?>"
                                            data-jam-mulai="<?= substr($s['jam_mulai'], 0, 5) ?>"
                                            data-jam-selesai="<?= substr($s['jam_selesai'], 0, 5) ?>"
                                            data-jenis="<?= e($s['jenis']) ?>"
                                            data-catatan="<?= e($s['catatan']) ?>"
                                            data-aktif="<?= $s['aktif'] ?>"
                                            title="Edit Slot">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="<?= BASE_URL ?>/dosen/slot.php" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                data-confirm="Apakah Anda yakin ingin menghapus slot waktu ini?" title="Hapus Slot">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Tambah Slot -->
<div class="modal fade" id="modalTambahSlot" tabindex="-1" aria-labelledby="modalTambahSlotLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= BASE_URL ?>/dosen/slot.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold" id="modalTambahSlotLabel"><i class="bi bi-plus-circle me-2"></i>Tambah Slot Konsultasi/Bimbingan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="hari_slot" class="form-label fw-semibold small text-secondary">Hari *</label>
                        <select class="form-select" id="hari_slot" name="hari" required>
                            <option value="">-- Pilih Hari --</option>
                            <?php foreach ($hariList as $h): ?>
                                <option value="<?= $h ?>"><?= $h ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="jam_mulai_slot" class="form-label fw-semibold small text-secondary">Jam Mulai *</label>
                            <input type="time" class="form-control" id="jam_mulai_slot" name="jam_mulai" required>
                        </div>
                        <div class="col-6">
                            <label for="jam_selesai_slot" class="form-label fw-semibold small text-secondary">Jam Selesai *</label>
                            <input type="time" class="form-control" id="jam_selesai_slot" name="jam_selesai" required>
                        </div>
                        <div class="form-text small">Jam selesai harus setelah jam mulai.</div>
                    </div>

                    <div class="mb-3">
                        <label for="jenis_slot" class="form-label fw-semibold small text-secondary">Jenis Slot *</label>
                        <select class="form-select" id="jenis_slot" name="jenis" required>
                            <option value="konsultasi">Konsultasi Akademik / Perkuliahan</option>
                            <option value="bimbingan">Bimbingan Tugas Akhir / Skripsi / PKL</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="catatan_slot" class="form-label fw-semibold small text-secondary">Catatan / Petunjuk (Opsional)</label>
                        <input type="text" class="form-control" id="catatan_slot" name="catatan" 
                               placeholder="Contoh: Membawa lembar asistensi / Bab 3 proposal" maxlength="255">
                    </div>

                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="aktif_slot" name="aktif" value="1" checked>
                        <label class="form-check-label fw-semibold text-secondary small" for="aktif_slot">Status Slot Langsung Aktif</label>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-polinela btn-sm px-3"><i class="bi bi-save me-1"></i>Simpan Slot</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Slot -->
<div class="modal fade" id="modalEditSlot" tabindex="-1" aria-labelledby="modalEditSlotLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= BASE_URL ?>/dosen/slot.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_slot_id" value="">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="modalEditSlotLabel"><i class="bi bi-pencil-square me-2"></i>Edit Slot Konsultasi/Bimbingan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="edit_slot_hari" class="form-label fw-semibold small text-secondary">Hari *</label>
                        <select class="form-select" id="edit_slot_hari" name="hari" required>
                            <?php foreach ($hariList as $h): ?>
                                <option value="<?= $h ?>"><?= $h ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="edit_slot_jam_mulai" class="form-label fw-semibold small text-secondary">Jam Mulai *</label>
                            <input type="time" class="form-control" id="edit_slot_jam_mulai" name="jam_mulai" required>
                        </div>
                        <div class="col-6">
                            <label for="edit_slot_jam_selesai" class="form-label fw-semibold small text-secondary">Jam Selesai *</label>
                            <input type="time" class="form-control" id="edit_slot_jam_selesai" name="jam_selesai" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_slot_jenis" class="form-label fw-semibold small text-secondary">Jenis Slot *</label>
                        <select class="form-select" id="edit_slot_jenis" name="jenis" required>
                            <option value="konsultasi">Konsultasi Akademik / Perkuliahan</option>
                            <option value="bimbingan">Bimbingan Tugas Akhir / Skripsi / PKL</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit_slot_catatan" class="form-label fw-semibold small text-secondary">Catatan / Petunjuk (Opsional)</label>
                        <input type="text" class="form-control" id="edit_slot_catatan" name="catatan" maxlength="255">
                    </div>

                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="edit_slot_aktif" name="aktif" value="1">
                        <label class="form-check-label fw-semibold text-secondary small" for="edit_slot_aktif">Status Slot Aktif</label>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3"><i class="bi bi-check-lg me-1"></i>Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const editSlotBtns = document.querySelectorAll('.btn-edit-slot');
    const editSlotModal = new bootstrap.Modal(document.getElementById('modalEditSlot'));

    editSlotBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('edit_slot_id').value = this.getAttribute('data-id');
            document.getElementById('edit_slot_hari').value = this.getAttribute('data-hari');
            document.getElementById('edit_slot_jam_mulai').value = this.getAttribute('data-jam-mulai');
            document.getElementById('edit_slot_jam_selesai').value = this.getAttribute('data-jam-selesai');
            document.getElementById('edit_slot_jenis').value = this.getAttribute('data-jenis');
            document.getElementById('edit_slot_catatan').value = this.getAttribute('data-catatan') || '';
            document.getElementById('edit_slot_aktif').checked = this.getAttribute('data-aktif') === '1';
            editSlotModal.show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

