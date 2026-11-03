<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Berkas Pembantu (Helper Functions) & Utilitas Keamanan
// File: includes/helpers.php
// ==============================================================

/**
 * Sanitasi output untuk mencegah Cross-Site Scripting (XSS)
 *
 * @param mixed $value
 * @return string
 */
function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Memastikan sesi aktif dengan konfigurasi cookie aman
 */
function start_session_if_none(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // Konfigurasi cookie sesi aman
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_samesite', 'Lax');
        session_start();
    }
}

/**
 * Mendapatkan atau membuat token CSRF baru
 *
 * @return string
 */
function csrf_token(): string {
    start_session_if_none();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Menghasilkan input hidden CSRF token untuk form POST
 *
 * @return string
 */
function csrf_field(): string {
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Memverifikasi validitas token CSRF
 *
 * @param string|null $token
 * @return bool
 */
function verify_csrf_token(?string $token): bool {
    start_session_if_none();
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Menyimpan pesan flash (notifikasi sementara) ke sesi
 *
 * @param string $type success|danger|warning|info
 * @param string $message
 */
function set_flash(string $type, string $message): void {
    start_session_if_none();
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Mengambil dan menghapus pesan flash dari sesi
 *
 * @return array|null
 */
function get_flash(): ?array {
    start_session_if_none();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Merender alert Bootstrap untuk pesan flash jika tersedia
 *
 * @return string
 */
function display_flash(): string {
    $flash = get_flash();
    if (!$flash) {
        return '';
    }

    $icon = match ($flash['type']) {
        'success' => 'bi-check-circle-fill',
        'danger'  => 'bi-exclamation-octagon-fill',
        'warning' => 'bi-exclamation-triangle-fill',
        default   => 'bi-info-circle-fill',
    };

    return '<div class="alert alert-' . e($flash['type']) . ' alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">' .
           '  <i class="bi ' . $icon . ' fs-5 me-2"></i>' .
           '  <div>' . e($flash['message']) . '</div>' .
           '  <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Tutup"></button>' .
           '</div>';
}

/**
 * Melakukan pengalihan URL yang aman
 *
 * @param string $url
 */
function redirect(string $url): void {
    header("Location: " . $url);
    exit;
}

/**
 * Daftar hari perkuliahan resmi (Senin s.d. Jumat)
 *
 * @return array
 */
function hari_list(): array {
    return ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
}

/**
 * Format string waktu TIME (HH:MM:SS) ke format Indonesia (HH:MM WIB)
 *
 * @param string|null $time
 * @return string
 */
function format_time(?string $time): string {
    if (!$time) return '-';
    $timestamp = strtotime($time);
    return date('H:i', $timestamp) . ' WIB';
}

/**
 * Format string waktu DATETIME ke format Indonesia
 *
 * @param string|null $datetime
 * @return string
 */
function format_datetime(?string $datetime): string {
    if (!$datetime) return '-';
    $timestamp = strtotime($datetime);
    $bulanIndo = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    ];
    $d = date('d', $timestamp);
    $m = $bulanIndo[(int)date('m', $timestamp)];
    $y = date('Y', $timestamp);
    $t = date('H:i', $timestamp);
    return "{$d} {$m} {$y}, {$t} WIB";
}

/**
 * Merender badge status ketersediaan dengan warna dan teks yang jelas
 *
 * @param string $status
 * @return string
 */
function status_badge(string $status): string {
    return match ($status) {
        'Tersedia' => '<span class="badge bg-success px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i>Tersedia</span>',
        'Mengajar' => '<span class="badge bg-primary px-2 py-1"><i class="bi bi-book-half me-1"></i>Sedang Mengajar</span>',
        'Hanya Konsultasi' => '<span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-chat-text-fill me-1"></i>Hanya Konsultasi</span>',
        'Hanya Bimbingan' => '<span class="badge bg-info text-dark px-2 py-1"><i class="bi bi-mortarboard-fill me-1"></i>Hanya Bimbingan TA</span>',
        'Tidak Tersedia' => '<span class="badge bg-secondary px-2 py-1"><i class="bi bi-x-circle-fill me-1"></i>Tidak Tersedia</span>',
        default => '<span class="badge bg-light text-dark px-2 py-1">' . e($status) . '</span>',
    };
}

/**
 * Validasi bentrok waktu lintas jadwal mengajar dan slot konsultasi
 * untuk dosen yang sama pada hari yang sama.
 * Menggunakan skema baru: jadwal_mengajar + jadwal_dosen (many-to-many).
 * Dua rentang dianggap bentrok jika: mulai_baru < selesai_lama DAN selesai_baru > mulai_lama
 * (jam persinggungan tepat, misal selesai=15:00 dan mulai=15:00, BUKAN bentrok)
 *
 * @param PDO $pdo
 * @param int $id_dosen
 * @param string $hari
 * @param string $jam_mulai   HH:MM atau HH:MM:SS
 * @param string $jam_selesai HH:MM atau HH:MM:SS
 * @param int|null $exclude_jadwal_id ID jadwal_mengajar yang sedang diedit (jika ada)
 * @param int|null $exclude_slot_id   ID slot_konsultasi yang sedang diedit (jika ada)
 * @return string|null Pesan error jika bentrok, atau null jika aman
 */
function check_time_collision(
    PDO $pdo,
    int $id_dosen,
    string $hari,
    string $jam_mulai,
    string $jam_selesai,
    ?int $exclude_jadwal_id = null,
    ?int $exclude_slot_id = null
): ?string {
    // 1. Cek bentrok dengan Jadwal Mengajar (via tabel relasi jadwal_dosen)
    $sqlJadwal = "SELECT jm.id, jm.jam_mulai, jm.jam_selesai, mk.nama AS nama_mk, k.nama_kelas
                  FROM jadwal_mengajar jm
                  JOIN jadwal_dosen jd ON jd.id_jadwal = jm.id
                  JOIN mata_kuliah mk  ON mk.id = jm.id_mk
                  JOIN kelas k         ON k.id  = jm.id_kelas
                  WHERE jd.id_dosen = :id_dosen
                    AND jm.hari     = :hari
                    AND jm.jam_mulai  < :selesai
                    AND jm.jam_selesai > :mulai";
    $paramsJadwal = [
        ':id_dosen' => $id_dosen,
        ':hari'     => $hari,
        ':selesai'  => $jam_selesai,
        ':mulai'    => $jam_mulai,
    ];
    if ($exclude_jadwal_id !== null) {
        $sqlJadwal .= " AND jm.id != :exclude_id";
        $paramsJadwal[':exclude_id'] = $exclude_jadwal_id;
    }
    $stmtJadwal = $pdo->prepare($sqlJadwal);
    $stmtJadwal->execute($paramsJadwal);
    $conflictJadwal = $stmtJadwal->fetch();

    if ($conflictJadwal) {
        $mulai   = substr($conflictJadwal['jam_mulai'],   0, 5);
        $selesai = substr($conflictJadwal['jam_selesai'], 0, 5);
        return "Waktu bentrok dengan Jadwal '{$conflictJadwal['nama_mk']} - {$conflictJadwal['nama_kelas']}' "
             . "hari {$hari} pukul {$mulai}–{$selesai} WIB.";
    }

    // 2. Cek bentrok dengan Slot Konsultasi / Bimbingan yang aktif
    $sqlSlot = "SELECT * FROM slot_konsultasi
                WHERE id_dosen   = :id_dosen
                  AND hari       = :hari
                  AND aktif      = 1
                  AND jam_mulai  < :selesai
                  AND jam_selesai > :mulai";
    $paramsSlot = [
        ':id_dosen' => $id_dosen,
        ':hari'     => $hari,
        ':selesai'  => $jam_selesai,
        ':mulai'    => $jam_mulai,
    ];
    if ($exclude_slot_id !== null) {
        $sqlSlot .= " AND id != :exclude_id";
        $paramsSlot[':exclude_id'] = $exclude_slot_id;
    }
    $stmtSlot = $pdo->prepare($sqlSlot);
    $stmtSlot->execute($paramsSlot);
    $conflictSlot = $stmtSlot->fetch();

    if ($conflictSlot) {
        $jenisLabel = $conflictSlot['jenis'] === 'bimbingan' ? 'Bimbingan TA' : 'Konsultasi';
        $mulai   = substr($conflictSlot['jam_mulai'],   0, 5);
        $selesai = substr($conflictSlot['jam_selesai'], 0, 5);
        return "Waktu bentrok dengan Slot {$jenisLabel} hari {$hari} pukul {$mulai}–{$selesai} WIB.";
    }

    return null;
}

/**
 * Badge mode kuliah (Daring / Luring)
 */
function mode_badge(string $mode): string {
    return match ($mode) {
        'daring' => '<span class="badge bg-info text-dark"><i class="bi bi-wifi me-1"></i>Daring</span>',
        'luring' => '<span class="badge bg-secondary"><i class="bi bi-building me-1"></i>Luring</span>',
        default  => '<span class="badge bg-light text-dark">' . e($mode) . '</span>',
    };
}

/**
 * Badge tipe sesi (Teori / Praktik)
 */
function tipe_badge(string $tipe): string {
    return match ($tipe) {
        'teori'   => '<span class="badge bg-primary"><i class="bi bi-book me-1"></i>Teori</span>',
        'praktik' => '<span class="badge bg-warning text-dark"><i class="bi bi-tools me-1"></i>Praktik</span>',
        default   => '<span class="badge bg-light text-dark">' . e($tipe) . '</span>',
    };
}

