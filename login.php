<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Halaman Masuk (Login) Multi-Peran
// File: login.php
// ==============================================================

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

// Jika sudah login, alihkan ke dashboard masing-masing
if (is_logged_in()) {
    $user = current_user();
    match ($user['role']) {
        'admin'     => redirect(BASE_URL . '/admin/index.php'),
        'dosen'     => redirect(BASE_URL . '/dosen/index.php'),
        'mahasiswa' => redirect(BASE_URL . '/mahasiswa/index.php'),
        default     => redirect(BASE_URL . '/index.php'),
    };
}

$errors = [];
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Sesi form tidak valid atau kedaluwarsa. Silakan muat ulang halaman.';
    } else {
        $identifier = trim($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($identifier)) {
            $errors[] = 'Email atau NPM wajib diisi.';
        }
        if (empty($password)) {
            $errors[] = 'Kata sandi wajib diisi.';
        }

        if (empty($errors)) {
            $pdo = get_db_connection();
            // Cari pengguna berdasarkan email, atau NPM (lewat tabel mahasiswa untuk mahasiswa,
            // atau kolom email untuk dosen/admin)
            $stmt = $pdo->prepare(
                "SELECT u.* FROM users u WHERE u.email = :email
                 UNION
                 SELECT u.* FROM users u
                 JOIN mahasiswa m ON m.user_id = u.id
                 WHERE m.npm = :npm
                 LIMIT 1"
            );
            $stmt->execute([':email' => $identifier, ':npm' => $identifier]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Ambil npm dari tabel mahasiswa jika role mahasiswa
                if ($user['role'] === 'mahasiswa') {
                    $stmtNpm = $pdo->prepare("SELECT npm FROM mahasiswa WHERE user_id = :uid LIMIT 1");
                    $stmtNpm->execute([':uid' => $user['id']]);
                    $mhsRow = $stmtNpm->fetch();
                    $user['npm'] = $mhsRow ? $mhsRow['npm'] : null;
                }

                login_user($user);

                // Cek apakah perlu ganti password
                if (!empty($user['must_change_password']) && (int)$user['must_change_password'] === 1) {
                    set_flash('warning', 'Selamat datang! Anda wajib mengganti password sebelum melanjutkan.');
                    redirect(BASE_URL . '/ganti-password.php');
                }

                set_flash('success', 'Selamat datang kembali, ' . $user['nama'] . '!');

                // Alihkan ke halaman tujuan berdasarkan peran
                match ($user['role']) {
                    'admin'     => redirect(BASE_URL . '/admin/index.php'),
                    'dosen'     => redirect(BASE_URL . '/dosen/index.php'),
                    'mahasiswa' => redirect(BASE_URL . '/mahasiswa/index.php'),
                    default     => redirect(BASE_URL . '/index.php'),
                };
            } else {
                $errors[] = 'Kredensial tidak valid. Silakan periksa kembali Email/NPM dan kata sandi Anda.';
            }
        }
    }
}

$page_title = 'Masuk ke Sistem';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-6 col-lg-5">
        <div class="card card-custom shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <span class="d-inline-flex p-3 rounded-circle bg-success bg-opacity-10 text-success mb-2">
                        <i class="bi bi-mortarboard-fill fs-2"></i>
                    </span>
                    <h3 class="fw-bold text-dark mb-1">Masuk ke S-LECTURA</h3>
                    <p class="text-muted small">Smart Lecturer Availability System — Politeknik Negeri Lampung</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger shadow-sm py-2">
                        <ul class="mb-0 ps-3 small">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/login.php" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="identifier" class="form-label small fw-semibold text-secondary">
                            <i class="bi bi-person me-1"></i>Email atau NPM
                        </label>
                        <input type="text" class="form-control py-2" id="identifier" name="identifier" 
                               value="<?= e($identifier) ?>" placeholder="contoh: dosen@polinela.ac.id / 21753001" required autofocus>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label small fw-semibold text-secondary">
                            <i class="bi bi-lock me-1"></i>Kata Sandi
                        </label>
                        <input type="password" class="form-control py-2" id="password" name="password" 
                               placeholder="Masukkan kata sandi akun" required>
                    </div>

                    <button type="submit" class="btn btn-polinela w-100 py-2 fs-6 mb-1 shadow-sm">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Masuk Sekarang
                    </button>
                </form>

                <div class="text-center pt-3 border-top mt-3">
                    <p class="small text-muted mb-0">
                        <i class="bi bi-shield-check text-success me-1"></i>
                        Akun Mahasiswa dan Dosen dikelola langsung oleh Akademik POLINELA.
                    </p>
                </div>
            </div>
        </div>

        <!-- Bantuan Akun Demo Pengujian -->
        <div class="card card-custom mt-3 border-0 bg-white">
            <div class="card-body p-3">
                <h6 class="small fw-bold text-secondary mb-2">
                    <i class="bi bi-info-circle-fill text-success me-1"></i>Kredensial Akun Uji Coba (Demo):
                </h6>
                <div class="row g-2 small text-muted">
                    <div class="col-12 col-sm-4">
                        <div class="p-2 border rounded bg-light">
                            <strong class="d-block text-dark">Admin</strong>
                            <code>admin@polinela.ac.id</code><br>
                            Sandi: <code>admin123</code>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="p-2 border rounded bg-light">
                            <strong class="d-block text-dark">Dosen</strong>
                            <code>reza@polinela.ac.id</code><br>
                            Sandi: <code>Polinela#2026</code><br>
                            <span class="text-warning fst-italic" style="font-size:0.7rem;">* Wajib ganti saat login pertama</span>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="p-2 border rounded bg-light">
                            <strong class="d-block text-dark">Mahasiswa</strong>
                            <code>24781001</code><br>
                            Sandi: <code>24781001</code> (= NPM)<br>
                            <span class="text-warning fst-italic" style="font-size:0.7rem;">* Wajib ganti saat login pertama</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
