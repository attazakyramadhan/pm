<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Pengelolaan Pengguna (Users) - Administrator
// File: admin/users.php
// ==============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pdo = get_db_connection();
$currentUser = current_user();

$errors = [];

// ==============================================================
// PROSES AKSI POST (CREATE / UPDATE / RESET PASSWORD / DELETE)
// ==============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Token CSRF tidak valid. Silakan coba lagi.');
        redirect(BASE_URL . '/admin/users.php');
    }

    $action = $_POST['action'] ?? '';

    // 1. TAMBAH PENGGUNA BARU
    if ($action === 'create') {
        $nama = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = trim($_POST['role'] ?? '');
        $npm = trim($_POST['npm'] ?? '');

        if (empty($nama)) $errors[] = 'Nama lengkap wajib diisi.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Alamat email tidak valid.';
        if (strlen($password) < 6) $errors[] = 'Kata sandi minimal 6 karakter.';
        if (!in_array($role, ['admin', 'dosen', 'mahasiswa'], true)) $errors[] = 'Peran pengguna tidak valid.';
        if ($role === 'mahasiswa' && empty($npm)) $errors[] = 'NPM wajib diisi untuk peran Mahasiswa.';

        if (empty($errors)) {
            // Cek duplikasi email
            $stmtC = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmtC->execute([':email' => $email]);
            if ($stmtC->fetch()) $errors[] = 'Email sudah digunakan oleh akun lain.';

            // Cek duplikasi NPM jika mahasiswa
            if ($role === 'mahasiswa' && !empty($npm)) {
                $stmtN = $pdo->prepare("SELECT id FROM users WHERE npm = :npm UNION SELECT user_id FROM mahasiswa WHERE npm = :npm LIMIT 1");
                $stmtN->execute([':npm' => $npm]);
                if ($stmtN->fetch()) $errors[] = 'NPM sudah digunakan oleh mahasiswa lain.';
            }
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmtIns = $pdo->prepare("INSERT INTO users (nama, email, password_hash, role, npm, must_change_password) 
                                      VALUES (:nama, :email, :hash, :role, :npm, 1)");
            $stmtIns->execute([
                ':nama'  => $nama,
                ':email' => $email,
                ':hash'  => $hash,
                ':role'  => $role,
                ':npm'   => ($role === 'mahasiswa') ? $npm : null,
            ]);
            $newUid = (int)$pdo->lastInsertId();

            if ($role === 'mahasiswa') {
                $prodiDef = $pdo->query("SELECT id FROM program_studi ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 2;
                $stmtM = $pdo->prepare("INSERT INTO mahasiswa (user_id, npm, id_kelas, id_prodi) VALUES (:uid, :npm, NULL, :prodi)");
                $stmtM->execute([':uid' => $newUid, ':npm' => $npm, ':prodi' => $prodiDef]);
            }

            set_flash('success', "Akun pengguna '{$nama}' ({$role}) berhasil dibuat.");
            redirect(BASE_URL . '/admin/users.php');
        }
    }

    // 2. EDIT DATA PENGGUNA
    if ($action === 'update') {
        $idUser = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $npm = trim($_POST['npm'] ?? '');

        if (empty($nama)) $errors[] = 'Nama lengkap wajib diisi.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Alamat email tidak valid.';
        if (!in_array($role, ['admin', 'dosen', 'mahasiswa'], true)) $errors[] = 'Peran pengguna tidak valid.';
        if ($role === 'mahasiswa' && empty($npm)) $errors[] = 'NPM wajib diisi untuk peran Mahasiswa.';

        if (empty($errors)) {
            // Cek email ganda pada pengguna lain
            $stmtC = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1");
            $stmtC->execute([':email' => $email, ':id' => $idUser]);
            if ($stmtC->fetch()) $errors[] = 'Email sudah digunakan oleh akun lain.';

            // Cek NPM ganda jika mahasiswa
            if ($role === 'mahasiswa' && !empty($npm)) {
                $stmtN = $pdo->prepare("SELECT id FROM users WHERE npm = :npm AND id != :id LIMIT 1");
                $stmtN->execute([':npm' => $npm, ':id' => $idUser]);
                if ($stmtN->fetch()) $errors[] = 'NPM sudah digunakan oleh mahasiswa lain.';
            }
        }

        if (empty($errors)) {
            $stmtUpd = $pdo->prepare("UPDATE users 
                                      SET nama = :nama, email = :email, role = :role, npm = :npm 
                                      WHERE id = :id");
            $stmtUpd->execute([
                ':nama'  => $nama,
                ':email' => $email,
                ':role'  => $role,
                ':npm'   => ($role === 'mahasiswa') ? $npm : null,
                ':id'    => $idUser,
            ]);

            set_flash('success', "Data pengguna '{$nama}' berhasil diperbarui.");
            redirect(BASE_URL . '/admin/users.php');
        }
    }

    // 3. RESET KATA SANDI PENGGUNA
    if ($action === 'reset_password') {
        $idUser = (int)($_POST['id'] ?? 0);
        $newPass = $_POST['new_password'] ?? '';

        if (strlen($newPass) < 6) {
            $errors[] = 'Kata sandi baru minimal harus 6 karakter.';
        } else {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $stmtPass = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
            $stmtPass->execute([':hash' => $newHash, ':id' => $idUser]);

            set_flash('success', 'Kata sandi pengguna berhasil direset.');
            redirect(BASE_URL . '/admin/users.php');
        }
    }

    // 4. HAPUS PENGGUNA
    if ($action === 'delete') {
        $idUser = (int)($_POST['id'] ?? 0);

        // Larang menghapus diri sendiri
        if ($idUser === (int)$currentUser['id']) {
            set_flash('danger', 'Anda tidak dapat menghapus akun Anda sendiri saat sedang masuk.');
        } else {
            $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $stmtDel->execute([':id' => $idUser]);
            set_flash('success', 'Akun pengguna berhasil dihapus dari sistem.');
        }
        redirect(BASE_URL . '/admin/users.php');
    }
}

// Filter & Pencarian Pengguna
$q = trim($_GET['q'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');

$sql = "SELECT u.*, COALESCE(m.npm, u.npm) AS npm_display
        FROM users u
        LEFT JOIN mahasiswa m ON m.user_id = u.id
        WHERE 1=1";
$params = [];
if (!empty($q)) {
    $sql .= " AND (u.nama LIKE :q OR u.email LIKE :q OR u.npm LIKE :q OR m.npm LIKE :q)";
    $params[':q'] = "%{$q}%";
}
if (!empty($roleFilter) && in_array($roleFilter, ['admin', 'dosen', 'mahasiswa'], true)) {
    $sql .= " AND u.role = :role";
    $params[':role'] = $roleFilter;
}
$sql .= " ORDER BY u.id DESC";

$stmtUsers = $pdo->prepare($sql);
$stmtUsers->execute($params);
$usersList = $stmtUsers->fetchAll();

$page_title = 'Kelola Pengguna';
$active_page = 'users';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="fw-bold text-dark mb-1"><i class="bi bi-people-fill me-2 text-primary"></i>Manajemen Pengguna</h3>
        <p class="text-secondary small mb-0">Kelola akun Admin, Dosen, dan Mahasiswa Politeknik Negeri Lampung.</p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <button type="button" class="btn btn-polinela shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahUser">
            <i class="bi bi-person-plus-fill me-1"></i>Tambah Pengguna Baru
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

<!-- Filter & Pencarian Form -->
<div class="card card-custom border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= BASE_URL ?>/admin/users.php" class="row g-2 align-items-center">
            <div class="col-md-6 col-lg-5">
                <input type="text" class="form-control" name="q" value="<?= e($q) ?>" placeholder="Cari nama, email, atau NPM...">
            </div>
            <div class="col-md-4 col-lg-3">
                <select class="form-select" name="role">
                    <option value="">Semua Peran</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Administrator</option>
                    <option value="dosen" <?= $roleFilter === 'dosen' ? 'selected' : '' ?>>Dosen</option>
                    <option value="mahasiswa" <?= $roleFilter === 'mahasiswa' ? 'selected' : '' ?>>Mahasiswa</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-polinela flex-fill"><i class="bi bi-search me-1"></i>Cari</button>
                <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Pengguna -->
<div class="card card-custom border-0 shadow-sm">
    <div class="card-header card-header-polinela d-flex justify-content-between align-items-center py-3">
        <span class="fw-bold"><i class="bi bi-table me-2"></i>Daftar Pengguna Sistem</span>
        <span class="badge bg-primary"><?= count($usersList) ?> Pengguna</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="width: 50px;">No</th>
                        <th>Nama Pengguna</th>
                        <th>Email Resmi</th>
                        <th style="width: 130px;">Peran (Role)</th>
                        <th style="width: 140px;">NPM Mahasiswa</th>
                        <th style="width: 160px;">Terdaftar</th>
                        <th class="text-end pe-4" style="width: 190px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usersList)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">Tidak ada data pengguna yang ditemukan.</td></tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($usersList as $u): ?>
                            <tr>
                                <td class="ps-4 text-muted small"><?= $no++ ?></td>
                                <td>
                                    <strong class="text-dark d-block"><?= e($u['nama']) ?></strong>
                                    <?php if ((int)$u['id'] === (int)$currentUser['id']): ?>
                                        <span class="badge bg-warning text-dark small" style="font-size: 0.65rem;">Akun Anda</span>
                                    <?php endif; ?>
                                </td>
                                <td><code><?= e($u['email']) ?></code></td>
                                <td>
                                    <?php if ($u['role'] === 'admin'): ?>
                                        <span class="badge bg-dark"><i class="bi bi-shield-fill me-1"></i>Admin</span>
                                    <?php elseif ($u['role'] === 'dosen'): ?>
                                        <span class="badge bg-success"><i class="bi bi-person-video3 me-1"></i>Dosen</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary"><i class="bi bi-mortarboard-fill me-1"></i>Mahasiswa</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $u['npm_display'] ? e($u['npm_display']) : '<span class="text-muted">-</span>' ?></td>
                                <td class="small text-muted"><?= format_datetime($u['created_at']) ?></td>
                                <td class="text-end pe-4">
                                    <!-- Edit User -->
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-edit-user me-1"
                                            data-id="<?= $u['id'] ?>"
                                            data-nama="<?= e($u['nama']) ?>"
                                            data-email="<?= e($u['email']) ?>"
                                            data-role="<?= e($u['role']) ?>"
                                            data-npm="<?= e($u['npm_display'] ?? '') ?>"
                                            title="Edit Pengguna">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <!-- Reset Password -->
                                    <button type="button" class="btn btn-sm btn-outline-warning text-dark btn-reset-pass me-1"
                                            data-id="<?= $u['id'] ?>"
                                            data-nama="<?= e($u['nama']) ?>"
                                            title="Reset Kata Sandi">
                                        <i class="bi bi-key-fill"></i>
                                    </button>
                                    <!-- Hapus User -->
                                    <?php if ((int)$u['id'] !== (int)$currentUser['id']): ?>
                                        <form action="<?= BASE_URL ?>/admin/users.php" method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                    data-confirm="Apakah Anda yakin ingin menghapus pengguna '<?= e($u['nama']) ?>'?" title="Hapus Pengguna">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah Pengguna -->
<div class="modal fade" id="modalTambahUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= BASE_URL ?>/admin/users.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i>Tambah Pengguna Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Nama Lengkap *</label>
                        <input type="text" class="form-control" name="nama" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Alamat Email *</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Kata Sandi Awal *</label>
                        <input type="password" class="form-control" name="password" placeholder="Minimal 6 karakter" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Peran Akun *</label>
                        <select class="form-select" name="role" id="roleSelectorCreate" required>
                            <option value="mahasiswa">Mahasiswa</option>
                            <option value="dosen">Dosen</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div class="mb-3" id="npmGroupCreate">
                        <label class="form-label small fw-semibold text-secondary">NPM (Khusus Mahasiswa)</label>
                        <input type="text" class="form-control" name="npm" placeholder="Contoh: 21753001">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-polinela btn-sm px-3"><i class="bi bi-save me-1"></i>Simpan Pengguna</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Pengguna -->
<div class="modal fade" id="modalEditUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= BASE_URL ?>/admin/users.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_user_id" value="">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Data Pengguna</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Nama Lengkap *</label>
                        <input type="text" class="form-control" name="nama" id="edit_nama" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Alamat Email *</label>
                        <input type="email" class="form-control" name="email" id="edit_email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Peran Akun *</label>
                        <select class="form-select" name="role" id="edit_role" required>
                            <option value="mahasiswa">Mahasiswa</option>
                            <option value="dosen">Dosen</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div class="mb-3" id="npmGroupEdit">
                        <label class="form-label small fw-semibold text-secondary">NPM (Khusus Mahasiswa)</label>
                        <input type="text" class="form-control" name="npm" id="edit_npm">
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

<!-- Modal Reset Password Pengguna -->
<div class="modal fade" id="modalResetPassword" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= BASE_URL ?>/admin/users.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="id" id="reset_user_id" value="">

                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold"><i class="bi bi-key-fill me-2"></i>Reset Kata Sandi Pengguna</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">
                        Mengatur ulang kata sandi untuk akun: <strong class="text-dark" id="reset_user_name"></strong>.
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Kata Sandi Baru *</label>
                        <input type="password" class="form-control" name="new_password" placeholder="Minimal 6 karakter" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning text-dark btn-sm px-3 fw-bold"><i class="bi bi-check2 me-1"></i>Reset Sandi Sekarang</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Edit User Modal
    const editBtns = document.querySelectorAll('.btn-edit-user');
    const editModal = new bootstrap.Modal(document.getElementById('modalEditUser'));
    editBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('edit_user_id').value = this.getAttribute('data-id');
            document.getElementById('edit_nama').value = this.getAttribute('data-nama');
            document.getElementById('edit_email').value = this.getAttribute('data-email');
            const role = this.getAttribute('data-role');
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_npm').value = this.getAttribute('data-npm') || '';
            document.getElementById('npmGroupEdit').style.display = role === 'mahasiswa' ? 'block' : 'none';
            editModal.show();
        });
    });

    // Reset Password Modal
    const resetBtns = document.querySelectorAll('.btn-reset-pass');
    const resetModal = new bootstrap.Modal(document.getElementById('modalResetPassword'));
    resetBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('reset_user_id').value = this.getAttribute('data-id');
            document.getElementById('reset_user_name').textContent = this.getAttribute('data-nama');
            resetModal.show();
        });
    });

    // Toggle NPM display on role change (create modal)
    const roleSelectCreate = document.getElementById('roleSelectorCreate');
    const npmGroupCreate = document.getElementById('npmGroupCreate');
    if (roleSelectCreate && npmGroupCreate) {
        roleSelectCreate.addEventListener('change', function () {
            npmGroupCreate.style.display = this.value === 'mahasiswa' ? 'block' : 'none';
        });
    }

    // Toggle NPM display on role change (edit modal)
    const editRole = document.getElementById('edit_role');
    const npmGroupEdit = document.getElementById('npmGroupEdit');
    if (editRole && npmGroupEdit) {
        editRole.addEventListener('change', function () {
            npmGroupEdit.style.display = this.value === 'mahasiswa' ? 'block' : 'none';
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

