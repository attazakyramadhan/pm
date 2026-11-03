<?php
// S-LECTURA - Generate password hashes untuk seed data
// Jalankan: php database/gen_hashes.php > database/hashes.txt

$cost = ['cost' => 10];

// Dosen: password Polinela#2026
echo "DOSEN|" . password_hash('Polinela#2026', PASSWORD_BCRYPT, $cost) . "\n";

// Mahasiswa: password = NPM masing-masing
$npms = [
    '24781001','24781002','24781003','24781004','24781005','24781006','24781007',
    '24781008','24781009','24781010','24781011','24781012','24781013','24781014',
    '24781015','24781016','24781017','24781018','24781019','24781020','24781021',
    '24781022','24781023','24781024','24781025','24781026','24781027','24781028',
    '24781029','24781030','24781031','24781093','24781094','24781025-PERIKSA'
];
foreach ($npms as $npm) {
    echo "{$npm}|" . password_hash($npm, PASSWORD_BCRYPT, $cost) . "\n";
}

