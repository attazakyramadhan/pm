<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Autentikasi, Kontrol Akses Berbasis Peran (RBAC), & Manajemen Sesi
// File: includes/auth.php
// ==============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

start_session_if_none();

/**
 * Mengecek apakah pengguna saat ini sudah login
 *
 * @return bool
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Mendapatkan data pengguna yang sedang login dari sesi
 *
 * @return array|null
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'       => $_SESSION['user_id'],
        'nama'     => $_SESSION['user_nama'] ?? '',
        'email'    => $_SESSION['user_email'] ?? '',
        'role'     => $_SESSION['user_role'] ?? '',
        'npm'      => $_SESSION['user_npm'] ?? null,
        'dosen_id' => $_SESSION['dosen_id'] ?? null,
    ];
}

/**
 * Memastikan pengguna sudah login; alihkan ke login.php jika belum.
 * Juga memeriksa apakah pengguna perlu mengganti password.
 */
function require_login(): void {
    if (!is_logged_in()) {
        set_flash('warning', 'Silakan masuk terlebih dahulu untuk mengakses halaman tersebut.');
        redirect(BASE_URL . '/login.php');
    }
    require_password_change();
}

/**
 * Memastikan peran pengguna sesuai dengan izin akses halaman (RBAC)
 *
 * @param string|array $allowed_roles
 */
function require_role($allowed_roles): void {
    require_login();

    $roles = is_array($allowed_roles) ? $allowed_roles : [$allowed_roles];
    $user = current_user();

    if (!in_array($user['role'], $roles, true)) {
        set_flash('danger', 'Akses ditolak: Anda tidak memiliki izin untuk membuka halaman tersebut.');

        // Alihkan pengguna ke dashboard yang sesuai dengan peran mereka
        $destination = match ($user['role']) {
            'admin'     => BASE_URL . '/admin/index.php',
            'dosen'     => BASE_URL . '/dosen/index.php',
            'mahasiswa' => BASE_URL . '/mahasiswa/index.php',
            default     => BASE_URL . '/login.php',
        };
        redirect($destination);
    }
}

/**
 * Menyiapkan sesi login yang aman bagi pengguna
 *
 * @param array $user Data baris dari tabel users
 */
function login_user(array $user): void {
    start_session_if_none();
    // Mencegah Session Fixation attack
    session_regenerate_id(true);

    $_SESSION['user_id']              = $user['id'];
    $_SESSION['user_nama']            = $user['nama'];
    $_SESSION['user_email']           = $user['email'];
    $_SESSION['user_role']            = $user['role'];
    $_SESSION['user_npm']             = $user['npm'] ?? null;
    $_SESSION['must_change_password'] = (int)($user['must_change_password'] ?? 0);

    // Jika pengguna adalah dosen, ambil id_dosen untuk mempermudah akses
    if ($user['role'] === 'dosen') {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT id FROM dosen WHERE user_id = :uid LIMIT 1");
        $stmt->execute([':uid' => $user['id']]);
        $dosen = $stmt->fetch();
        $_SESSION['dosen_id'] = $dosen ? (int)$dosen['id'] : null;
    } else {
        unset($_SESSION['dosen_id']);
    }
}

/**
 * Menghancurkan sesi pengguna saat keluar (logout)
 */
function logout_user(): void {
    start_session_if_none();
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Memaksa pengguna mengganti password jika must_change_password = 1.
 * Dipanggil dari require_login(). Mengizinkan akses ke ganti-password.php dan logout.php saja.
 */
function require_password_change(): void {
    start_session_if_none();
    if (empty($_SESSION['must_change_password']) || (int)$_SESSION['must_change_password'] !== 1) {
        return; // Tidak perlu ganti password
    }

    // Izinkan hanya halaman ganti-password.php dan logout.php
    $allowedSuffixes = ['ganti-password.php', 'logout.php'];
    $currentPath = $_SERVER['SCRIPT_NAME'] ?? '';
    foreach ($allowedSuffixes as $suffix) {
        if (str_ends_with($currentPath, $suffix)) {
            return;
        }
    }

    set_flash('warning', 'Anda harus mengganti password terlebih dahulu sebelum dapat menggunakan sistem.');
    redirect(BASE_URL . '/ganti-password.php');
}

