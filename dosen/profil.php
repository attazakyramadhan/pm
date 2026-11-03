<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Halaman Edit Profil Dosen & Ganti Kata Sandi
// File: dosen/profil.php
// ==============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('dosen');

$pdo = get_db_connection();
$user = current_user();

// Ambil data lengkap dosen beserta prodi
$stmt = $pdo->prepare("SELECT d.*, u.nama, u.email, p.nama_prodi, p.jurusan 
                       FROM dosen d 
                       JOIN users u ON d.user_id = u.id 
                       JOIN program_studi p ON d.id_prodi = p.id 
                       WHERE d.user_id = :uid LIMIT 1");
$stmt->execute([':uid' => $user['id']]);
$dosen = $stmt->fetch();

if (!$dosen) {
    die("Profil dosen tidak ditemukan.");
}

$idDosen = (int)$dosen['id'];
$errors = [];
$successMsg = '';

// Proses Simpan Profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Token keamanan CSRF tidak valid. Silakan muat ulang halaman.';
    } else {
        $action = $_POST['action'] ?? '';

        // 1. UPDATE DATA PROFIL & FOTO
        if ($action === 'update_profile') {
            $nama = trim($_POST['nama'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $bidangKeahlian = trim($_POST['bidang_keahlian'] ?? '');

            if (empty($nama)) {
                $errors[] = 'Nama lengkap dosen wajib diisi.';
            }
            if (empty($email)) {
                $errors[] = 'Alamat email wajib diisi.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Format alamat email tidak valid.';
            } else {
                // Cek email ganda pada pengguna lain
                $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :uid LIMIT 1");
                $stmtCheck->execute([':email' => $email, ':uid' => $user['id']]);
                if ($stmtCheck->fetch()) {
                    $errors[] = 'Alamat email sudah digunakan oleh akun lain.';
                }
            }
            if (empty($bidangKeahlian)) {
                $errors[] = 'Bidang keahlian dosen wajib diisi.';
            }

            // Proses Upload Foto (jika ada)
            $fotoPath = $dosen['foto'];
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['foto'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = 'Terjadi kesalahan saat mengunggah berkas foto.';
                } else {
                    $maxSize = 2 * 1024 * 1024; // 2MB
                    if ($file['size'] > $maxSize) {
                        $errors[] = 'Ukuran berkas foto maksimal 2MB.';
                    } else {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime = finfo_file($finfo, $file['tmp_name']);
                        finfo_close($finfo);

                        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
                        if (!in_array($mime, $allowedMimes, true)) {
                            $errors[] = 'Format berkas foto harus JPG, PNG, atau WEBP.';
                        } else {
                            $ext = match ($mime) {
                                'image/jpeg' => 'jpg',
                                'image/png'  => 'png',
                                'image/webp' => 'webp',
                                default      => 'jpg',
                            };
                            $fileName = 'dosen_' . $idDosen . '_' . time() . '.' . $ext;
                            $targetDir = __DIR__ . '/../assets/img/dosen/';
                            if (!is_dir($targetDir)) {
                                mkdir($targetDir, 0755, true);
                            }
                            $targetPath = $targetDir . $fileName;

                            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                                // Hapus foto lama jika ada
                                if (!empty($dosen['foto']) && file_exists(__DIR__ . '/../' . $dosen['foto'])) {
                                    unlink(__DIR__ . '/../' . $dosen['foto']);
                                }
                                $fotoPath = 'assets/img/dosen/' . $fileName;
                            } else {
                                $errors[] = 'Gagal menyimpan foto ke server.';
                            }
                        }
                    }
                }
            }

            if (empty($errors)) {
                // Update tabel users
                $stmtUser = $pdo->prepare("UPDATE users SET nama = :nama, email = :email WHERE id = :uid");
                $stmtUser->execute([':nama' => $nama, ':email' => $email, ':uid' => $user['id']]);

                // Update tabel dosen
                $stmtDosen = $pdo->prepare("UPDATE dosen SET bidang_keahlian = :keahlian, foto = :foto WHERE id = :id");
                $stmtDosen->execute([
                    ':keahlian' => $bidangKeahlian,
                    ':foto'     => $fotoPath,
                    ':id'       => $idDosen,
                ]);

                // Perbarui sesi pengguna
                $_SESSION['user_nama'] = $nama;
                $_SESSION['user_email'] = $email;

                set_flash('success', 'Profil dosen berhasil diperbarui.');
                redirect(BASE_URL . '/dosen/profil.php');
            }
        }

        // 2. GANTI KATA SANDI
        if ($action === 'change_password') {
            $passLama = $_POST['password_lama'] ?? '';
            $passBaru = $_POST['password_baru'] ?? '';
            $passKonfirmasi = $_POST['password_konfirmasi'] ?? '';

            // Cek password lama
            $stmtUser = $pdo->prepare("SELECT password_hash FROM users WHERE id = :uid");
            $stmtUser->execute([':uid' => $user['id']]);
            $u = $stmtUser->fetch();

            if (!$u || !password_verify($passLama, $u['password_hash'])) {
                $errors[] = 'Kata sandi saat ini (lama) tidak sesuai.';
            }
            if (strlen($passBaru) < 6) {
                $errors[] = 'Kata sandi baru minimal terdiri dari 6 karakter.';
            }
            if ($passBaru !== $passKonfirmasi) {
                $errors[] = 'Konfirmasi kata sandi baru tidak cocok.';
            }

            if (empty($errors)) {
                $newHash = password_hash($passBaru, PASSWORD_BCRYPT);
                $stmtUpdatePass = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :uid");
                $stmtUpdatePass->execute([':hash' => $newHash, ':uid' => $user['id']]);

                set_flash('success', 'Kata sandi berhasil diperbarui.');
                redirect(BASE_URL . '/dosen/profil.php');
            }
        }
    }
}

$page_title = 'Edit Profil Dosen';
$active_page = 'profil';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col-12">
        <h3 class="fw-bold text-dark mb-1"><i class="bi bi-person-lines-fill me-2 text-success"></i>Profil & Pengaturan Akun</h3>
        <p class="text-secondary small mb-0">Kelola identitas publik, bidang keahlian, foto profil, dan kata sandi akun Anda.</p>
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

<div class="row g-4">
    <!-- Formulir Edit Profil -->
    <div class="col-lg-7">
        <div class="card card-custom border-0 shadow-sm">
            <div class="card-header card-header-polinela py-3">
                <span class="fw-bold"><i class="bi bi-person-circle me-2"></i>Informasi Data Dosen</span>
            </div>
            <div class="card-body p-4">
                <form action="<?= BASE_URL ?>/dosen/profil.php" method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="d-flex align-items-center gap-3 mb-4 p-3 bg-light rounded-3">
                        <?php if (!empty($dosen['foto']) && file_exists(__DIR__ . '/../' . $dosen['foto'])): ?>
                            <img src="<?= BASE_URL . '/' . e($dosen['foto']) ?>" alt="Foto Dosen" class="rounded-circle shadow-sm" style="width: 80px; height: 80px; object-fit: cover;">
                        <?php else: ?>
                            <div class="avatar-initial avatar-initial-lg bg-success bg-opacity-10 text-success">
                                <?= strtoupper(substr($dosen['nama'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <label for="foto" class="form-label fw-semibold small text-secondary mb-1">Ganti Foto Profil</label>
                            <input type="file" class="form-control form-control-sm" id="foto" name="foto" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text small text-muted">Format: JPG, PNG, atau WEBP. Maksimal 2MB.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="nama" class="form-label fw-semibold small text-secondary">Nama Lengkap & Gelar *</label>
                        <input type="text" class="form-control" id="nama" name="nama" value="<?= e($dosen['nama']) ?>" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-secondary">NIDN / NIP (Tetap)</label>
                            <input type="text" class="form-control bg-light" value="<?= e($dosen['nidn_nip']) ?>" readonly disabled>
                            <div class="form-text small text-muted">NIDN/NIP hanya dapat diubah oleh Administrator.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-secondary">Program Studi</label>
                            <input type="text" class="form-control bg-light" value="<?= e($dosen['nama_prodi']) ?>" readonly disabled>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold small text-secondary">Email Resmi Polinela *</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= e($dosen['email']) ?>" required>
                    </div>

                    <div class="mb-4">
                        <label for="bidang_keahlian" class="form-label fw-semibold small text-secondary">Bidang Keahlian & Fokus Riset *</label>
                        <textarea class="form-control" id="bidang_keahlian" name="bidang_keahlian" rows="3" required><?= e($dosen['bidang_keahlian']) ?></textarea>
                        <div class="form-text small text-muted">Pisahkan dengan koma jika lebih dari satu (contoh: Rekayasa Perangkat Lunak, Data Science).</div>
                    </div>

                    <button type="submit" class="btn btn-polinela px-4 py-2 fw-semibold shadow-sm">
                        <i class="bi bi-save me-1"></i>Simpan Perubahan Profil
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Formulir Ganti Kata Sandi -->
    <div class="col-lg-5">
        <div class="card card-custom border-0 shadow-sm">
            <div class="card-header card-header-polinela py-3">
                <span class="fw-bold"><i class="bi bi-shield-lock me-2"></i>Keamanan Akun & Kata Sandi</span>
            </div>
            <div class="card-body p-4">
                <form action="<?= BASE_URL ?>/dosen/profil.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label for="password_lama" class="form-label fw-semibold small text-secondary">Kata Sandi Saat Ini *</label>
                        <input type="password" class="form-control" id="password_lama" name="password_lama" required>
                    </div>

                    <div class="mb-3">
                        <label for="password_baru" class="form-label fw-semibold small text-secondary">Kata Sandi Baru *</label>
                        <input type="password" class="form-control" id="password_baru" name="password_baru" placeholder="Minimal 6 karakter" required>
                    </div>

                    <div class="mb-4">
                        <label for="password_konfirmasi" class="form-label fw-semibold small text-secondary">Konfirmasi Kata Sandi Baru *</label>
                        <input type="password" class="form-control" id="password_konfirmasi" name="password_konfirmasi" required>
                    </div>

                    <button type="submit" class="btn btn-outline-polinela w-100 py-2 fw-semibold">
                        <i class="bi bi-key-fill me-1"></i>Perbarui Kata Sandi
                    </button>
                </form>
            </div>
        </div>

        <div class="card card-custom border-0 shadow-sm mt-3 bg-light">
            <div class="card-body p-3">
                <h6 class="small fw-bold text-dark mb-1"><i class="bi bi-shield-check text-success me-1"></i>Informasi Privasi Kampus:</h6>
                <p class="small text-muted mb-0">
                    Sistem S-LECTURA dirancang memprioritaskan privasi Anda. Lokasi fisik ruangan Anda tidak dilacak. Status ketersediaan dikendalikan sepenuhnya oleh Anda sendiri.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

