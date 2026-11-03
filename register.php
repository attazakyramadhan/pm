<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Halaman Registrasi Mandiri Mahasiswa
// File: register.php
// ==============================================================

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

// Jika sudah login, alihkan langsung
if (is_logged_in()) {
    redirect(BASE_URL . '/index.php');
}

$errors = [];
$nama = '';
$npm = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Sesi form tidak valid. Silakan muat ulang halaman.';
    } else {
        $nama = trim($_POST['nama'] ?? '');
        $npm = trim($_POST['npm'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        // Validasi input
        if (empty($nama)) {
            $errors[] = 'Nama lengkap mahasiswa wajib diisi.';
        }
        if (empty($npm)) {
            $errors[] = 'Nomor Pokok Mahasiswa (NPM) wajib diisi.';
        } elseif (!preg_match('/^[0-9]{7,15}$/', $npm)) {
            $errors[] = 'Format NPM harus berupa 7 sampai 15 digit angka.';
        }
        if (empty($email)) {
            $errors[] = 'Alamat email wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format alamat email tidak valid.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'Kata sandi minimal terdiri dari 6 karakter.';
        }
        if ($password !== $password_confirm) {
            $errors[] = 'Konfirmasi kata sandi tidak cocok.';
        }

        if (empty($errors)) {
            $pdo = get_db_connection();

            // Cek apakah email sudah terdaftar
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                $errors[] = 'Alamat email sudah terdaftar. Silakan gunakan email lain atau langsung masuk.';
            }

            // Cek apakah NPM sudah terdaftar
            $stmt = $pdo->prepare("SELECT id FROM users WHERE npm = :npm UNION SELECT user_id FROM mahasiswa WHERE npm = :npm LIMIT 1");
            $stmt->execute([':npm' => $npm]);
            if ($stmt->fetch()) {
                $errors[] = 'NPM tersebut sudah terdaftar. Silakan hubungi admin jika ini bukan akun Anda.';
            }

            // Jika validasi lolos semua, masukkan ke database
            if (empty($errors)) {
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $insertStmt = $pdo->prepare("INSERT INTO users (nama, email, password_hash, role, npm, must_change_password) 
                                             VALUES (:nama, :email, :password_hash, 'mahasiswa', :npm, 0)");
                $insertStmt->execute([
                    ':nama'          => $nama,
                    ':email'         => $email,
                    ':password_hash' => $password_hash,
                    ':npm'           => $npm,
                ]);
                $newUserId = (int)$pdo->lastInsertId();

                // Dapatkan id_prodi default (misal prodi pertama atau id=2)
                $prodiDef = $pdo->query("SELECT id FROM program_studi ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 2;
                $stmtMhs = $pdo->prepare("INSERT INTO mahasiswa (user_id, npm, id_kelas, id_prodi) VALUES (:uid, :npm, NULL, :prodi)");
                $stmtMhs->execute([
                    ':uid'   => $newUserId,
                    ':npm'   => $npm,
                    ':prodi' => $prodiDef,
                ]);

                set_flash('success', 'Pendaftaran akun mahasiswa berhasil! Silakan masuk dengan NPM dan kata sandi Anda.');
                redirect(BASE_URL . '/login.php');
            }
        }
    }
}

$page_title = 'Pendaftaran Akun Mahasiswa';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-7 col-lg-6">
        <div class="card card-custom shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <span class="d-inline-flex p-3 rounded-circle bg-success bg-opacity-10 text-success mb-2">
                        <i class="bi bi-person-plus-fill fs-2"></i>
                    </span>
                    <h3 class="fw-bold text-dark mb-1">Registrasi Mahasiswa</h3>
                    <p class="text-muted small">Buat akun mahasiswa Polinela untuk melihat jadwal dan slot bimbingan dosen</p>
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

                <form method="POST" action="<?= BASE_URL ?>/register.php" novalidate>
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="npm" class="form-label small fw-semibold text-secondary">
                                <i class="bi bi-card-text me-1"></i>NPM (Nomor Pokok Mahasiswa) *
                            </label>
                            <input type="text" class="form-control" id="npm" name="npm" 
                                   value="<?= e($npm) ?>" placeholder="contoh: 21753099" required autofocus>
                        </div>
                        <div class="col-md-6">
                            <label for="nama" class="form-label small fw-semibold text-secondary">
                                <i class="bi bi-person me-1"></i>Nama Lengkap *
                            </label>
                            <input type="text" class="form-control" id="nama" name="nama" 
                                   value="<?= e($nama) ?>" placeholder="Nama lengkap sesuai KTM" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label small fw-semibold text-secondary">
                            <i class="bi bi-envelope me-1"></i>Email Aktif *
                        </label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?= e($email) ?>" placeholder="nama@mhs.polinela.ac.id" required>
                        <div class="form-text small">Disarankan menggunakan email institusi kampus Polinela.</div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="password" class="form-label small fw-semibold text-secondary">
                                <i class="bi bi-lock me-1"></i>Kata Sandi *
                            </label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Minimal 6 karakter" required>
                        </div>
                        <div class="col-md-6">
                            <label for="password_confirm" class="form-label small fw-semibold text-secondary">
                                <i class="bi bi-shield-lock me-1"></i>Konfirmasi Kata Sandi *
                            </label>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" 
                                   placeholder="Ulangi kata sandi" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-polinela w-100 py-2 fs-6 mb-3 shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i>Daftar Sekarang
                    </button>
                </form>

                <div class="text-center pt-3 border-top">
                    <p class="small text-muted mb-0">
                        Sudah punya akun?
                        <a href="<?= BASE_URL ?>/login.php" class="text-success fw-bold text-decoration-none">
                            Masuk ke sini
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

