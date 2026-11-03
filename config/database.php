<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Konfigurasi Koneksi Basis Data PDO & Konstanta URL
// File: config/database.php
// ==============================================================

// Fungsi pemuat berkas .env sederhana (PHP Native)
if (!function_exists('load_env_file')) {
    function load_env_file(string $filePath): void {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return;
        }
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                [$key, $val] = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val);
                // Bersihkan kutip tunggal/ganda
                if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                    (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                    $val = substr($val, 1, -1);
                }
                if (!array_key_exists($key, $_ENV) && !array_key_exists($key, $_SERVER)) {
                    putenv("{$key}={$val}");
                    $_ENV[$key] = $val;
                    $_SERVER[$key] = $val;
                }
            }
        }
    }
}

// Muat berkas .env dari root direktori proyek jika ada
load_env_file(__DIR__ . '/../.env');

// Ambil nilai dari .env atau gunakan default XAMPP
$envHost = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1');
$envName = getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: ($_ENV['DB_NAME'] ?? ($_ENV['DB_DATABASE'] ?? 's_lectura')));
$envUser = getenv('DB_USER') ?: (getenv('DB_USERNAME') ?: ($_ENV['DB_USER'] ?? ($_ENV['DB_USERNAME'] ?? 'root')));
$envPass = getenv('DB_PASS');
if ($envPass === false) {
    $envPass = getenv('DB_PASSWORD');
}
if ($envPass === false) {
    $envPass = $_ENV['DB_PASS'] ?? ($_ENV['DB_PASSWORD'] ?? '');
}
$envPort = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');

define('DB_HOST', $envHost);
define('DB_PORT', $envPort);
define('DB_NAME', $envName);
define('DB_USER', $envUser);
define('DB_PASS', $envPass);
define('DB_CHARSET', 'utf8mb4');

/**
 * Mendapatkan instance koneksi PDO tunggal (Singleton pattern)
 *
 * @return PDO
 */
function get_db_connection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Tampilkan pesan kesalahan yang informatif namun aman
            die("Kesalahan koneksi basis data: Gagal terhubung ke database '" . DB_NAME . "' di " . DB_HOST . ":" . DB_PORT . ". Pastikan server MySQL di XAMPP aktif.");
        }
    }
    return $pdo;
}

// Deteksi otomatis direktori dasar (BASE_URL) agar kompatibel baik di root maupun subfolder htdocs XAMPP
if (!defined('BASE_URL')) {
    $envBaseUrl = getenv('BASE_URL') ?: (getenv('APP_URL') ?: ($_ENV['BASE_URL'] ?? ($_ENV['APP_URL'] ?? '')));
    if (!empty($envBaseUrl)) {
        define('BASE_URL', rtrim($envBaseUrl, '/'));
    } else {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $baseDir = preg_replace('#/(mahasiswa|dosen|admin)(/.*)?$#', '', $scriptDir);
        $baseDir = rtrim($baseDir, '/');
        define('BASE_URL', $baseDir);
    }
}

// Konstanta tampilan ruang kuliah pada halaman jadwal resmi.
// true  = tampilkan nama ruang (contoh: "Lab Terpadu Lab. Komputer 3.6")
// false = hanya tampilkan badge Daring/Luring (lebih privat)
if (!defined('TAMPILKAN_RUANG')) {
    $envRuang = getenv('TAMPILKAN_RUANG') ?: ($_ENV['TAMPILKAN_RUANG'] ?? 'true');
    define('TAMPILKAN_RUANG', filter_var($envRuang, FILTER_VALIDATE_BOOLEAN));
}

