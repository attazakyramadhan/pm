<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Pengelolaan Master Program Studi - Administrator
// File: admin/prodi.php
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
        redirect(BASE_URL . '/admin/prodi.php');
    }

    $action = $_POST['action'] ?? '';

    // 1. TAMBAH PRODI
    if ($action === 'create') {
        $namaProdi = trim($_POST['nama_prodi'] ?? '');
        $jurusan = trim($_POST['jurusan'] ?? '');

        if (empty($namaProdi)) $errors[] = 'Nama program studi wajib diisi.';
        if (empty($jurusan)) $errors[] = 'Nama jurusan wajib diisi.';

        if (empty($errors)) {
            $stmtIns = $pdo->prepare("INSERT INTO program_studi (nama_prodi, jurusan) VALUES (:prodi, :jurusan)");
            $stmtIns->execute([':prodi' => $namaProdi, ':jurusan' => $jurusan]);

            set_flash('success', "Program studi '{$namaProdi}' berhasil ditambahkan.");
            redirect(BASE_URL . '/admin/prodi.php');
        }
    }

    // 2. EDIT PRODI
    if ($action === 'update') {
        $idProdi = (int)($_POST['id'] ?? 0);
        $namaProdi = trim($_POST['nama_prodi'] ?? '');
        $jurusan = trim($_POST['jurusan'] ?? '');

        if (empty($namaProdi)) $errors[] = 'Nama program studi wajib diisi.';
        if (empty($jurusan)) $errors[] = 'Nama jurusan wajib diisi.';

        if (empty($errors)) {
            $stmtUpd = $pdo->prepare("UPDATE program_studi SET nama_prodi = :prodi, jurusan = :jurusan WHERE id = :id");
            $stmtUpd->execute([':prodi' => $namaProdi, ':jurusan' => $jurusan, ':id' => $idProdi]);

            set_flash('success', "Program studi '{$namaProdi}' berhasil diperbarui.");
            redirect(BASE_URL . '/admin/prodi.php');
        }
    }

    // 3. HAPUS PRODI
    if ($action === 'delete') {
        $idProdi = (int)($_POST['id'] ?? 0);

        // Cek keterikatan dosen
        $stmtCek = $pdo->prepare("SELECT COUNT(*) FROM dosen WHERE id_prodi = :id");
        $stmtCek->execute([':id' => $idProdi]);
        $count = (int)$stmtCek->fetchColumn();

        if ($count > 0) {
            set_flash('danger', "Tidak dapat menghapus Program Studi ini karena masih menaungi {$count} dosen aktif. Pindahkan dosen terlebih dahulu.");
        } else {
            $pdo->prepare("DELETE FROM program_studi WHERE id = :id")->execute([':id' => $idProdi]);
            set_flash('success', 'Program studi berhasil dihapus.');
        }
        redirect(BASE_URL . '/admin/prodi.php');
    }
}

// Ambil data prodi beserta jumlah dosen
$sqlProdi = "SELECT p.*, COUNT(d.id) AS total_dosen 
             FROM program_studi p 
             LEFT JOIN dosen d ON p.id = d.id_prodi 
             GROUP BY p.id 
             ORDER BY p.jurusan ASC, p.nama_prodi ASC";
$prodiList = $pdo->query($sqlProdi)->fetchAll();

$page_title = 'Program Studi Polinela';
$active_page = 'prodi';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="fw-bold text-dark mb-1"><i class="bi bi-building-fill me-2 text-warning"></i>Master Program Studi</h3>
        <p class="text-secondary small mb-0">Kelola data program studi dan jurusan di lingkungan Politeknik Negeri Lampung.</p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <button type="button" class="btn btn-polinela shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahProdi">
            <i class="bi bi-plus-circle me-1"></i>Tambah Program Studi
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

<!-- Tabel Program Studi -->
<div class="card card-custom border-0 shadow-sm">
    <div class="card-header card-header-polinela d-flex justify-content-between align-items-center py-3">
        <span class="fw-bold"><i class="bi bi-table me-2"></i>Daftar Program Studi</span>
        <span class="badge bg-warning text-dark"><?= count($prodiList) ?> Program Studi</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="width: 50px;">No</th>
                        <th>Nama Program Studi</th>
                        <th>Jurusan Kampus</th>
                        <th style="width: 150px;">Jumlah Dosen</th>
                        <th class="text-end pe-4" style="width: 160px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($prodiList)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada data program studi.</td></tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($prodiList as $p): ?>
                            <tr>
                                <td class="ps-4 text-muted small"><?= $no++ ?></td>
                                <td>
                                    <strong class="text-dark d-block"><?= e($p['nama_prodi']) ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary"><?= e($p['jurusan']) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-success-subtle text-success fw-bold"><?= $p['total_dosen'] ?> Dosen</span>
                                </td>
                                <td class="text-end pe-4">
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-edit-prodi me-1"
                                            data-id="<?= $p['id'] ?>"
                                            data-nama="<?= e($p['nama_prodi']) ?>"
                                            data-jurusan="<?= e($p['jurusan']) ?>"
                                            title="Edit Prodi">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="<?= BASE_URL ?>/admin/prodi.php" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                data-confirm="Apakah Anda yakin ingin menghapus prodi '<?= e($p['nama_prodi']) ?>'?" title="Hapus Prodi">
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

<!-- Modal Tambah Prodi -->
<div class="modal fade" id="modalTambahProdi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= BASE_URL ?>/admin/prodi.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Tambah Program Studi Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Nama Program Studi *</label>
                        <input type="text" class="form-control" name="nama_prodi" placeholder="Contoh: D4 Rekayasa Perangkat Lunak" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Jurusan Kampus *</label>
                        <input type="text" class="form-control" name="jurusan" placeholder="Contoh: Teknologi Informasi / Ekonomi dan Bisnis" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-polinela btn-sm px-3"><i class="bi bi-save me-1"></i>Simpan Prodi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Prodi -->
<div class="modal fade" id="modalEditProdi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= BASE_URL ?>/admin/prodi.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_prodi_id" value="">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Program Studi</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Nama Program Studi *</label>
                        <input type="text" class="form-control" name="nama_prodi" id="edit_nama_prodi" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Jurusan Kampus *</label>
                        <input type="text" class="form-control" name="jurusan" id="edit_jurusan" required>
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
    const editBtns = document.querySelectorAll('.btn-edit-prodi');
    const editModal = new bootstrap.Modal(document.getElementById('modalEditProdi'));

    editBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('edit_prodi_id').value = this.getAttribute('data-id');
            document.getElementById('edit_nama_prodi').value = this.getAttribute('data-nama');
            document.getElementById('edit_jurusan').value = this.getAttribute('data-jurusan');
            editModal.show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

