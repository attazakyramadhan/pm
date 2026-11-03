<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Halaman Keluar (Logout)
// File: logout.php
// ==============================================================

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

logout_user();

set_flash('info', 'Anda telah berhasil keluar dari sistem S-LECTURA.');
redirect(BASE_URL . '/login.php');

