<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Pengelolaan Master Data Dosen - Administrator
// File: admin/dosen.php
// ==============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pdo = get_db_connection();
$errors = [];

// ==============================================================
// PROSES AKSI POST (CREATE / UPDATE / DELETE)
// ==============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Token CSRF tidak valid.');
        redirect(BASE_URL . '/admin/dosen.php');
    }

    $action = $_POST['action'] ?? '';

    // 1. TAMBAH DOSEN BARU (BUAT AKUN + PROFIL DOSEN)
    if ($action === 'create') {
        $nama = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $nidnNip = trim($_POST['nidn_nip'] ?? '');
        $idProdi = (int)($_POST['id_prodi'] ?? 0);
        $bidangKeahlian = trim($_POST['bidang_keahlian'] ?? '');

        if (empty($nama)) $errors[] = 'Nama dosen wajib diisi.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Alamat email tidak valid.';
        if (strlen($password) < 6) $errors[] = 'Kata sandi akun minimal 6 karakter.';
        if ($idProdi <= 0) $errors[] = 'Pilih Program Studi.';
        if (empty($bidangKeahlian)) $errors[] = 'Bidang keahlian wajib diisi.';

        if (empty($errors)) {
            // Cek duplikasi email
            $stmtC = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmtC->execute([':email' => $email]);
            if ($stmtC->fetch()) $errors[] = 'Email tersebut sudah digunakan.';

            // Cek duplikasi NIDN/NIP jika diisi
            if (!empty($nidnNip)) {
                $stmtN = $pdo->prepare("SELECT id FROM dosen WHERE nidn_nip = :nidn LIMIT 1");
                $stmtN->execute([':nidn' => $nidnNip]);
                if ($stmtN->fetch()) $errors[] = 'NIDN/NIP tersebut sudah terdaftar.';
            }
        }

        if (empty($errors)) {
            $pdo->beginTransaction();
            try {
                // Buat user dosen
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $insUser = $pdo->prepare("INSERT INTO users (nama, email, password_hash, role, must_change_password) VALUES (:nama, :email, :hash, 'dosen', 1)");
                $insUser->execute([':nama' => $nama, ':email' => $email, ':hash' => $hash]);
                $userId = (int)$pdo->lastInsertId();

                // Buat data dosen
                $insDosen = $pdo->prepare("INSERT INTO dosen (user_id, nidn_nip, id_prodi, bidang_keahlian) 
                                           VALUES (:uid, :nidn, :prodi, :keahlian)");
                $insDosen->execute([
                    ':uid'      => $userId,
                    ':nidn'     => !empty($nidnNip) ? $nidnNip : null,
                    ':prodi'    => $idProdi,
                    ':keahlian' => $bidangKeahlian,
                ]);
                $dosenId = (int)$pdo->lastInsertId();

                // Inisialisasi status ketersediaan awal
                $insStatus = $pdo->prepare("INSERT INTO status_ketersediaan (id_dosen, status, catatan) VALUES (:id, 'Tersedia', 'Siap melayani bimbingan')");
                $insStatus->execute([':id' => $dosenId]);

                $pdo->commit();
                set_flash('success', "Data dosen '{$nama}' berhasil ditambahkan ke sistem.");
                redirect(BASE_URL . '/admin/dosen.php');
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Gagal menyimpan dosen: Terjadi gangguan pada database.';
            }
        }
    }

    // 2. EDIT DATA DOSEN
    if ($action === 'update') {
        $idDosen = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $nidnNip = trim($_POST['nidn_nip'] ?? '');
        $idProdi = (int)($_POST['id_prodi'] ?? 0);
        $bidangKeahlian = trim($_POST['bidang_keahlian'] ?? '');

        // Ambil data dosen lama
        $stmtOld = $pdo->prepare("SELECT user_id FROM dosen WHERE id = :id");
        $stmtOld->execute([':id' => $idDosen]);
        $oldDosen = $stmtOld->fetch();

        if (!$oldDosen) {
            $errors[] = 'Data dosen tidak ditemukan.';
        } else {
            $userId = (int)$oldDosen['user_id'];

            if (empty($nama)) $errors[] = 'Nama dosen wajib diisi.';
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Alamat email tidak valid.';
            if ($idProdi <= 0) $errors[] = 'Pilih Program Studi.';
            if (empty($bidangKeahlian)) $errors[] = 'Bidang keahlian wajib diisi.';

            if (empty($errors)) {
                // Cek email ganda
                $stmtC = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :uid LIMIT 1");
                $stmtC->execute([':email' => $email, ':uid' => $userId]);
                if ($stmtC->fetch()) $errors[] = 'Email tersebut sudah digunakan akun lain.';

                // Cek NIDN ganda jika diisi
                if (!empty($nidnNip)) {
                    $stmtN = $pdo->prepare("SELECT id FROM dosen WHERE nidn_nip = :nidn AND id != :id LIMIT 1");
                    $stmtN->execute([':nidn' => $nidnNip, ':id' => $idDosen]);
                    if ($stmtN->fetch()) $errors[] = 'NIDN/NIP sudah terdaftar pada dosen lain.';
                }
            }

            if (empty($errors)) {
                $pdo->beginTransaction();
                try {
                    // Update user
                    $pdo->prepare("UPDATE users SET nama = :nama, email = :email WHERE id = :uid")
                        ->execute([':nama' => $nama, ':email' => $email, ':uid' => $userId]);

                    // Update dosen
                    $pdo->prepare("UPDATE dosen SET nidn_nip = :nidn, id_prodi = :prodi, bidang_keahlian = :keahlian WHERE id = :id")
                        ->execute([':nidn' => !empty($nidnNip) ? $nidnNip : null, ':prodi' => $idProdi, ':keahlian' => $bidangKeahlian, ':id' => $idDosen]);

                    $pdo->commit();
                    set_flash('success', "Data dosen '{$nama}' berhasil diperbarui.");
                    redirect(BASE_URL . '/admin/dosen.php');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $errors[] = 'Gagal memperbarui data dosen.';
                }
            }
        }
    }

    // 3. HAPUS DOSEN
    if ($action === 'delete') {
        $idDosen = (int)($_POST['id'] ?? 0);
        $stmtFind = $pdo->prepare("SELECT user_id, foto FROM dosen WHERE id = :id");
        $stmtFind->execute([':id' => $idDosen]);
        $d = $stmtFind->fetch();

        if ($d) {
            // Hapus foto jika ada
            if (!empty($d['foto']) && file_exists(__DIR__ . '/../' . $d['foto'])) {
                unlink(__DIR__ . '/../' . $d['foto']);
            }
            // Hapus user terkait (akan cascade ke dosen, jadwal, slot, status)
            $pdo->prepare("DELETE FROM users WHERE id = :uid")->execute([':uid' => $d['user_id']]);
            set_flash('success', 'Data dosen dan seluruh jadwal terkait berhasil dihapus.');
        }
        redirect(BASE_URL . '/admin/dosen.php');
    }
}

// Ambil daftar prodi untuk form
$prodiList = $pdo->query("SELECT * FROM program_studi ORDER BY nama_prodi ASC")->fetchAll();

// Ambil daftar seluruh dosen
$sqlDosen = "SELECT d.*, u.nama, u.email, p.nama_prodi, p.jurusan,
                    (SELECT COUNT(DISTINCT id_jadwal) FROM jadwal_dosen WHERE id_dosen = d.id) AS total_jadwal,
                    (SELECT COUNT(*) FROM slot_konsultasi WHERE id_dosen = d.id AND aktif = 1) AS total_slot
             FROM dosen d
             JOIN users u ON d.user_id = u.id
             JOIN program_studi p ON d.id_prodi = p.id
             ORDER BY u.nama ASC";
$dosenList = $pdo->query($sqlDosen)->fetchAll();

$page_title = 'Data Dosen Polinela';
$active_page = 'dosen';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="fw-bold text-dark mb-1"><i class="bi bi-person-badge-fill me-2 text-success"></i>Master Data Dosen</h3>
        <p class="text-secondary small mb-0">Kelola informasi pengajar, NIDN/NIP, penugasan program studi, dan akun sistem.</p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <button type="button" class="btn btn-polinela shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahDosen">
            <i class="bi bi-person-plus-fill me-1"></i>Tambah Dosen Baru
        </button>
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

<!-- Tabel Dosen -->
<div class="card card-custom border-0 shadow-sm">
    <div class="card-header card-header-polinela d-flex justify-content-between align-items-center py-3">
        <span class="fw-bold"><i class="bi bi-table me-2"></i>Daftar Dosen Politeknik Negeri Lampung</span>
        <span class="badge bg-success"><?= count($dosenList) ?> Dosen Terdaftar</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="width: 50px;">No</th>
                        <th>Dosen & NIDN</th>
                        <th>Program Studi</th>
                        <th>Bidang Keahlian</th>
                        <th style="width: 140px;">Beban Sesi</th>
                        <th class="text-end pe-4" style="width: 180px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dosenList)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada data dosen.</td></tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($dosenList as $d): ?>
                            <tr>
                                <td class="ps-4 text-muted small"><?= $no++ ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!empty($d['foto']) && file_exists(__DIR__ . '/../' . $d['foto'])): ?>
                                            <img src="<?= BASE_URL . '/' . e($d['foto']) ?>" class="rounded-circle shadow-sm" style="width: 40px; height: 40px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="avatar-initial avatar-initial-sm bg-success bg-opacity-10 text-success fw-bold">
                                                <?= strtoupper(substr($d['nama'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <strong class="text-dark d-block"><?= e($d['nama']) ?></strong>
                                            <small class="text-muted">NIP/NIDN: <?= !empty($d['nidn_nip']) ? e($d['nidn_nip']) : '-' ?> &bull; <?= e($d['email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-secondary border"><?= e($d['nama_prodi']) ?></span>
                                </td>
                                <td class="small text-secondary" style="max-width: 250px;">
                                    <?= e($d['bidang_keahlian']) ?>
                                </td>
                                <td class="small">
                                    <span class="badge bg-primary me-1" title="Jadwal Kuliah"><?= $d['total_jadwal'] ?> Kuliah</span>
                                    <span class="badge bg-info text-dark" title="Slot Konsultasi & Bimbingan"><?= $d['total_slot'] ?> Slot</span>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="<?= BASE_URL ?>/admin/jadwal-slot.php?id_dosen=<?= $d['id'] ?>" class="btn btn-sm btn-outline-success me-1" title="Supervisi Jadwal/Slot">
                                        <i class="bi bi-calendar-check"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-edit-dosen me-1"
                                            data-id="<?= $d['id'] ?>"
                                            data-nama="<?= e($d['nama']) ?>"
                                            data-email="<?= e($d['email']) ?>"
                                            data-nidn="<?= e($d['nidn_nip']) ?>"
                                            data-prodi="<?= $d['id_prodi'] ?>"
                                            data-keahlian="<?= e($d['bidang_keahlian']) ?>"
                                            title="Edit Dosen">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="<?= BASE_URL ?>/admin/dosen.php" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                data-confirm="Apakah Anda yakin ingin menghapus data dosen '<?= e($d['nama']) ?>' beserta seluruh jadwalnya?" title="Hapus Dosen">
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

<!-- Modal Tambah Dosen -->
<div class="modal fade" id="modalTambahDosen" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= BASE_URL ?>/admin/dosen.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i>Tambah Dosen Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Nama Lengkap & Gelar *</label>
                        <input type="text" class="form-control" name="nama" placeholder="Contoh: Dr. Ir. Eko Nugroho, M.Kom." required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-secondary">Email Polinela *</label>
                            <input type="email" class="form-control" name="email" placeholder="nama@polinela.ac.id" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-secondary">Kata Sandi Awal *</label>
                            <input type="password" class="form-control" name="password" placeholder="Minimal 6 karakter" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-secondary">NIDN / NIP *</label>
                            <input type="text" class="form-control" name="nidn_nip" placeholder="Nomor Induk Dosen" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-secondary">Program Studi *</label>
                            <select class="form-select" name="id_prodi" required>
                                <option value="">-- Pilih Prodi --</option>
                                <?php foreach ($prodiList as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= e($p['nama_prodi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Bidang Keahlian *</label>
                        <textarea class="form-control" name="bidang_keahlian" rows="2" placeholder="Contoh: Rekayasa Perangkat Lunak, Cloud Computing" required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-polinela btn-sm px-3"><i class="bi bi-save me-1"></i>Simpan Dosen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Dosen -->
<div class="modal fade" id="modalEditDosen" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= BASE_URL ?>/admin/dosen.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_dosen_id" value="">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Data Dosen</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Nama Lengkap & Gelar *</label>
                        <input type="text" class="form-control" name="nama" id="edit_nama" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Email Resmi *</label>
                        <input type="email" class="form-control" name="email" id="edit_email" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-secondary">NIDN / NIP *</label>
                            <input type="text" class="form-control" name="nidn_nip" id="edit_nidn" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-secondary">Program Studi *</label>
                            <select class="form-select" name="id_prodi" id="edit_prodi" required>
                                <?php foreach ($prodiList as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= e($p['nama_prodi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Bidang Keahlian *</label>
                        <textarea class="form-control" name="bidang_keahlian" id="edit_keahlian" rows="2" required></textarea>
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
    const editBtns = document.querySelectorAll('.btn-edit-dosen');
    const editModal = new bootstrap.Modal(document.getElementById('modalEditDosen'));

    editBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('edit_dosen_id').value = this.getAttribute('data-id');
            document.getElementById('edit_nama').value = this.getAttribute('data-nama');
            document.getElementById('edit_email').value = this.getAttribute('data-email');
            document.getElementById('edit_nidn').value = this.getAttribute('data-nidn');
            document.getElementById('edit_prodi').value = this.getAttribute('data-prodi');
            document.getElementById('edit_keahlian').value = this.getAttribute('data-keahlian');
            editModal.show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

