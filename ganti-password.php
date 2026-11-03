<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Halaman Ganti Password (Paksa & Sukarela)
// File: ganti-password.php
// ==============================================================

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

// Harus login, tapi TIDAK panggil require_login() karena itu akan loop balik ke sini
if (!is_logged_in()) {
    set_flash('warning', 'Silakan masuk terlebih dahulu.');
    redirect(BASE_URL . '/login.php');
}

$pdo  = get_db_connection();
$user = current_user();
$mustChange = !empty($_SESSION['must_change_password']) && (int)$_SESSION['must_change_password'] === 1;

$errors     = [];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $passBaru        = $_POST['password_baru'] ?? '';
        $passKonfirmasi  = $_POST['password_konfirmasi'] ?? '';

        // Jika bukan ganti paksa, verifikasi password lama
        if (!$mustChange) {
            $passLama = $_POST['password_lama'] ?? '';
            $stmtCek  = $pdo->prepare("SELECT password_hash FROM users WHERE id = :uid");
            $stmtCek->execute([':uid' => $user['id']]);
            $row = $stmtCek->fetch();
            if (!$row || !password_verify($passLama, $row['password_hash'])) {
                $errors[] = 'Kata sandi saat ini (lama) tidak sesuai.';
            }
        }

        if (strlen($passBaru) < 8) {
            $errors[] = 'Kata sandi baru minimal 8 karakter.';
        }
        if ($passBaru !== $passKonfirmasi) {
            $errors[] = 'Konfirmasi kata sandi baru tidak cocok.';
        }

        if (empty($errors)) {
            $newHash = password_hash($passBaru, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare(
                "UPDATE users SET password_hash = :hash, must_change_password = 0 WHERE id = :uid"
            );
            $stmt->execute([':hash' => $newHash, ':uid' => $user['id']]);

            // Perbarui sesi
            $_SESSION['must_change_password'] = 0;

            set_flash('success', 'Kata sandi berhasil diperbarui. Selamat datang di S-LECTURA!');

            // Arahkan ke dashboard sesuai peran
            $destination = match ($user['role']) {
                'admin'     => BASE_URL . '/admin/index.php',
                'dosen'     => BASE_URL . '/dosen/index.php',
                'mahasiswa' => BASE_URL . '/mahasiswa/index.php',
                default     => BASE_URL . '/login.php',
            };
            redirect($destination);
        }
    }
}

$page_title = $mustChange ? 'Wajib Ganti Kata Sandi' : 'Ganti Kata Sandi';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">

        <?php if ($mustChange): ?>
        <div class="alert alert-warning d-flex align-items-start gap-2 shadow-sm mb-4">
            <i class="bi bi-exclamation-triangle-fill fs-5 mt-1 flex-shrink-0"></i>
            <div>
                <strong>Ganti Password Diperlukan</strong><br>
                <span class="small">Ini adalah login pertama Anda. Demi keamanan akun, Anda wajib mengganti
                password sebelum dapat menggunakan sistem.</span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger shadow-sm">
            <strong><i class="bi bi-exclamation-octagon-fill me-1"></i>Terjadi Kesalahan:</strong>
            <ul class="mb-0 ps-3 small mt-1">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="card card-custom border-0 shadow-sm">
            <div class="card-header card-header-polinela py-3">
                <span class="fw-bold">
                    <i class="bi bi-shield-lock me-2"></i>
                    <?= $mustChange ? 'Buat Kata Sandi Baru' : 'Ganti Kata Sandi' ?>
                </span>
            </div>
            <div class="card-body p-4">
                <form action="<?= BASE_URL ?>/ganti-password.php" method="POST">
                    <?= csrf_field() ?>

                    <?php if (!$mustChange): ?>
                    <div class="mb-3">
                        <label for="password_lama" class="form-label fw-semibold small text-secondary">
                            Kata Sandi Saat Ini *
                        </label>
                        <input type="password" class="form-control" id="password_lama"
                               name="password_lama" required autocomplete="current-password">
                    </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="password_baru" class="form-label fw-semibold small text-secondary">
                            Kata Sandi Baru *
                        </label>
                        <input type="password" class="form-control" id="password_baru"
                               name="password_baru" placeholder="Minimal 8 karakter"
                               required autocomplete="new-password">
                        <div class="form-text small text-muted">Minimal 8 karakter, gunakan kombinasi huruf dan angka.</div>
                    </div>

                    <div class="mb-4">
                        <label for="password_konfirmasi" class="form-label fw-semibold small text-secondary">
                            Konfirmasi Kata Sandi Baru *
                        </label>
                        <input type="password" class="form-control" id="password_konfirmasi"
                               name="password_konfirmasi" placeholder="Ulangi kata sandi baru"
                               required autocomplete="new-password">
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-polinela fw-semibold py-2">
                            <i class="bi bi-key-fill me-1"></i>
                            <?= $mustChange ? 'Simpan & Lanjutkan ke Sistem' : 'Perbarui Kata Sandi' ?>
                        </button>
                    </div>

                    <?php if (!$mustChange): ?>
                    <div class="text-center mt-3">
                        <a href="<?= BASE_URL ?>/<?= $user['role'] === 'dosen' ? 'dosen/profil.php' : ($user['role'] === 'admin' ? 'admin/index.php' : 'mahasiswa/index.php') ?>"
                           class="small text-muted">
                            <i class="bi bi-arrow-left me-1"></i>Kembali
                        </a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="card border-0 bg-light mt-3">
            <div class="card-body p-3">
                <p class="small text-muted mb-0">
                    <i class="bi bi-shield-check text-success me-1"></i>
                    <strong>Keamanan:</strong> Kata sandi Anda disimpan dalam bentuk terenkripsi (bcrypt).
                    Tim S-LECTURA tidak dapat membaca kata sandi Anda.
                </p>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

