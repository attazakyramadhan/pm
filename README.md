# S-LECTURA: Smart Lecturer Availability System
**Politeknik Negeri Lampung (POLINELA)**

S-LECTURA adalah sistem informasi terpusat berbasis web yang menampilkan jadwal mengajar perkuliahan, slot konsultasi, slot bimbingan tugas akhir, dan status ketersediaan dosen secara mandiri (*self-managed*). Sistem ini dirancang untuk menyelesaikan permasalahan mahasiswa yang sering datang ke ruang dosen tanpa kepastian kehadiran.

---

## 🛡️ Prinsip Privasi Utama (Pembeda Proyek)
**TIDAK ADA pelacakan atau penampilan lokasi fisik dosen secara real-time** (tanpa GPS, tanpa RFID, dan tanpa nama ruangan fisik real-time). Sistem ini sepenuhnya berfokus pada transparansi **jadwal mengajar resmi dan status ketersediaan mandiri** yang diperbarui langsung oleh dosen. Hal ini menjamin kenyamanan dan privasi dosen dalam menjalankan tri dharma perguruan tinggi.

> **Konfigurasi Privasi Ruang Kuliah (`TAMPILKAN_RUANG`)**:
> Kolom ruang pada jadwal adalah ruang perkuliahan pada jadwal resmi kampus, **BUKAN** lokasi keberadaan fisik dosen saat ini. Diatur melalui konstanta `TAMPILKAN_RUANG` di `config/database.php` (default: `true`). Jika diubah menjadi `false`, hanya badge Daring/Luring yang ditampilkan. Ruang kuliah **tidak pernah** ditampilkan di kartu profil dosen maupun di badge status ketersediaan.

---

## ⚠️ Data yang Perlu Diperbaiki (Catatan Integritas Data)

Pada data mentah mahasiswa kelas **Manajemen Informatika A 5**, ditemukan **duplikasi nomor pokok mahasiswa (NPM)**:
- **No. 25**: `Rubby Ibnu Anantara` &mdash; NPM: `24781025`
- **No. 34**: `Atha Talitha Nabil` &mdash; NPM: `24781025` *(Duplikat)*

### Penanganan oleh Sistem:
Kolom `npm` pada tabel `mahasiswa` memiliki batasan `UNIQUE`. Demi menjaga integritas data tanpa menebak-nebak NPM asli:
1. Akun mahasiswa **Atha Talitha Nabil** disimpan dengan NPM sementara: **`24781025-PERIKSA`** (email: `atha.talitha@mhs.polinela.ac.id`).
2. Setelah pihak akademik/kemahasiswaan mengonfirmasi NPM yang benar, administrator dapat memperbaikinya secara langsung menggunakan perintah SQL berikut:

```sql
-- Contoh koreksi NPM setelah konfirmasi nomor yang benar dari BAAK:
UPDATE `mahasiswa` 
SET `npm` = '24781032' -- ganti dengan NPM asli yang benar
WHERE `npm` = '24781025-PERIKSA';

-- Sinkronkan juga pada tabel users (jika ada):
UPDATE `users` 
SET `npm` = '24781032', `email` = '24781032@mhs.polinela.ac.id'
WHERE `email` = 'atha.talitha@mhs.polinela.ac.id';
```

---

## 💻 Tech Stack
- **Bahasa Pemrograman**: PHP Native (Versi 8.0+ / 8.2) dengan PDO Prepared Statements
- **Basis Data**: MySQL / MariaDB (Storage Engine: InnoDB, Charset: `utf8mb4`, Foreign Key Constraints)
- **Antarmuka (Frontend)**: HTML5, CSS3, JavaScript murni, Bootstrap 5.3 (Lokal + CDN fallback), Bootstrap Icons
- **Lingkungan**: XAMPP (Apache + MySQL) pada Windows &mdash; *Plug & Play* tanpa perlu npm build atau Node.js

---

## 🚀 Panduan Instalasi di XAMPP

### Langkah 1: Penempatan Folder Proyek
Salin folder proyek ini ke direktori `htdocs` XAMPP:
```text
C:\xampp\htdocs\pm\   (atau C:\xampp\htdocs\s-lectura\)
```
*(Atau gunakan junction: `cmd.exe /c "mklink /J C:\xampp\htdocs\pm d:\pm"`)*.

### Langkah 2: Impor Basis Data Lengkap
Buka terminal / CMD / XAMPP Shell dan jalankan import berkas SQL utama:
```cmd
C:\xampp\mysql\bin\mysql.exe -u root < d:\pm\database\s_lectura.sql
```

*Jika menerapkan migrasi di atas database yang sudah ada, jalankan berkas migrasi berurutan:*
1. `database/migrations/002_skema_jadwal_kelas.sql` (Normalisasi skema jadwal, kelas, mata kuliah, tim teaching)
2. `database/migrations/003_seed_data_kelas_ma5.sql` (Data kelas Manajemen Informatika A 5, 6 dosen, 13 jadwal, 34 mahasiswa)

### Langkah 3: Update Hash Sandi Awal
Buka peramban atau terminal untuk menginisialisasi kata sandi terenkripsi bcrypt yang sesuai aturan:
- **Via Browser**: Akses `http://localhost/pm/database/update_passwords.php?token=slectura_setup`
- **Via Terminal**: Jalankan `php d:\pm\database\update_passwords.php`

### Langkah 4: Buka Aplikasi di Browser
Akses URL aplikasi:
👉 **`http://localhost/pm/`** *(atau `http://localhost:8000/` jika menggunakan `php -S localhost:8000`)*.

---

## 👥 Kredensial Akun Pengujian (Demo) & Aturan Sandi

Seluruh pengguna baru memiliki penanda `must_change_password = 1` dan **diwajibkan mengganti kata sandi pada saat pertama kali login**.

### 1. Administrator
| Identitas | Kata Sandi | Keterangan |
|---|---|---|
| `admin@polinela.ac.id` | `admin123` | Akses penuh dashboard, pengguna, master dosen, prodi, dan supervisi jadwal. |

### 2. Dosen (6 Orang Pengampu Kelas Manajemen Informatika A 5)
Sandi awal demo untuk seluruh dosen: **`Polinela#2026`** *(wajib diganti saat login pertama)*.
| No | Dosen (dengan gelar) | Email / Username Login | NIP |
|---|---|---|---|
| 1 | Muhammad Reza Redo Islami, S.Kom., M.T.I. | `reza@polinela.ac.id` | *- (NULL)* |
| 2 | Dwirgo Sahlinal, S.T., M.Eng. | `dwirgo@polinela.ac.id` | *- (NULL)* |
| 3 | Dewi Kania Widyawati, S.Kom., M.Kom. | `dewi@polinela.ac.id` | *- (NULL)* |
| 4 | Akhmad Jayadi, S.Kom., M.Cs. | `akhmad@polinela.ac.id` | `199407172024061001` |
| 5 | Rima Maulini, S.Kom., M.Kom. | `rima@polinela.ac.id` | `197802192005012001` |
| 6 | Kurniawan Saputra, S.Kom., M.Kom. | `kurniawan@polinela.ac.id` | *- (NULL)* |

### 3. Mahasiswa (Kelas Manajemen Informatika A 5)
Login menggunakan **NPM** atau email `@mhs.polinela.ac.id`. Sandi awal = **NPM masing-masing** *(wajib diganti saat login pertama)*.
| No | Nama Mahasiswa | NPM (Username & Sandi Awal) |
|---|---|---|
| 1 | Abdur Rouf Hanafi | `24781001` |
| 2 | Ahmad Rizky Maulana | `24781002` |
| 3 | Alzahra Dwi Febriyan | `24781003` |
| 4 | Atta Zaky Ramadhan | `24781004` |
| 5 | Bunga Putri Salsabilla | `24781005` |
| 6 | Dafa Anggara Yonata | `24781006` |
| 7 | Deni Prawira | `24781007` |
| 8 | Dona Virza | `24781008` |
| 9 | Fahmi Ghozali | `24781009` |
| 10 | Farhan Habibullah | `24781010` |
| 11 | Fitri Amelia Ananti | `24781011` |
| 12 | Heidy Putri Shafira | `24781012` |
| 13 | Jesfitrina Sihombing | `24781013` |
| 14 | Lia Agustina | `24781014` |
| 15 | M. Rayhan Zulkarnain | `24781015` |
| 16 | Muhammad Alwan Dzaky | `24781016` |
| 17 | Nabila Alfi Nur Khasanah | `24781017` |
| 18 | Nadiya Ghefira El Firsi | `24781018` |
| 19 | Nayla Putri Syafira Arrovi | `24781019` |
| 20 | Nyken Sekar Ayuningtyas | `24781020` |
| 21 | Rafi Diandra Ardi Agusta | `24781021` |
| 22 | Rendy Dwi Prayoga | `24781022` |
| 23 | Rifki Rangga Saputra | `24781023` |
| 24 | Rizki Surohman | `24781024` |
| 25 | Rubby Ibnu Anantara | `24781025` |
| 26 | Septi Cahyaningtias | `24781026` |
| 27 | Sofi Ramadhani | `24781027` |
| 28 | Tasya Rismala | `24781028` |
| 29 | Ulfa Setyaningsih | `24781029` |
| 30 | Yoga Ricky Pasaribu | `24781030` |
| 31 | Yusuf Al Fikri Jayasena | `24781031` |
| 32 | Ahmad Muhajir | `24781093` |
| 33 | Alya Alfi Lutfiah | `24781094` |
| 34 | Atha Talitha Nabil | `24781025-PERIKSA` |

---

## 📁 Struktur Direktori Proyek

```text
s-lectura/
├── config/
│   └── database.php              # PDO Singleton, BASE_URL, konstanta TAMPILKAN_RUANG
├── includes/
│   ├── auth.php                  # RBAC, login/logout, paksa ganti sandi (require_password_change)
│   ├── helpers.php               # Anti-XSS (e()), CSRF, format tanggal/WIB, check_time_collision() normalized
│   ├── header.php                # Navigasi Bootstrap 5 responsif, menu multi-peran
│   └── footer.php                # Footer privasi kampus, bootstrap.bundle.js
├── assets/
│   ├── css/
│   │   ├── bootstrap.min.css     # Bootstrap 5.3 lokal (offline ready)
│   │   ├── bootstrap-icons.min.css
│   │   └── style.css             # Tema hijau Polinela (#107C41)
│   └── js/
│       ├── bootstrap.bundle.min.js
│       └── app.js                # Auto-dismiss alert, konfirmasi hapus, validasi waktu
├── database/
│   ├── migrations/
│   │   ├── 002_skema_jadwal_kelas.sql     # Skema kelas, mata kuliah, mahasiswa, jadwal_dosen
│   │   └── 003_seed_data_kelas_ma5.sql    # Data kelas Manajemen Informatika A 5 & 13 jadwal
│   ├── s_lectura.sql                      # Skema DDL lengkap + seed langsung siap pakai
│   └── update_passwords.php               # Skrip update bcrypt untuk dosen & mahasiswa
├── docs/
│   └── pengujian.md              # Laporan pengujian fungsional & skenario uji baru
├── ganti-password.php            # Halaman ganti kata sandi wajib (login pertama) & sukarela
├── mahasiswa/
│   ├── index.php                 # Direktori dosen, filter status & prodi
│   ├── detail.php                # Detail dosen: status, jadwal mingguan (team teaching), slot
│   └── jadwal-kelas.php          # Jadwal kuliah mingguan kelas mahasiswa per hari (Senin-Jumat)
├── dosen/
│   ├── index.php                 # Dashboard dosen: status aktif, quick update, agenda hari ini
│   ├── jadwal.php                # Kelola jadwal mengajar (team teaching, anti-bentrok, SKS, mode, ruang)
│   ├── slot.php                  # Kelola slot konsultasi & bimbingan TA
│   └── profil.php                # Edit profil, keahlian, upload foto, ganti sandi
├── admin/
│   ├── index.php                 # Dashboard metrik statistik kampus
│   ├── users.php                 # Manajemen akun (admin, dosen, mahasiswa) & reset sandi
│   ├── dosen.php                 # Master data dosen & penugasan prodi
│   ├── prodi.php                 # Master program studi
│   └── jadwal-slot.php           # Supervisi jadwal & slot bimbingan seluruh dosen
├── login.php                     # Masuk multi-peran (email / NPM)
├── register.php                  # Registrasi mandiri mahasiswa
├── logout.php                    # Logout & destroy session
└── README.md                     # Dokumentasi panduan instalasi & penggunaan
```

---

## 🌟 Logika Validasi Bentrok Jadwal (*Collision Detection*)

Dua rentang waktu dianggap **bentrok** jika dan hanya jika:
$$\text{mulai\_baru} < \text{selesai\_lama} \quad \text{DAN} \quad \text{selesai\_baru} > \text{mulai\_lama}$$

Artinya:
- Sesi yang tepat bersinggungan di batas menit (misalnya selesai pukul `15:00` dan sesi baru mulai pukul `15:00`) **BUKAN merupakan bentrok**.
- Validasi dilakukan lintas relasi `jadwal_mengajar` $\leftrightarrow$ `jadwal_dosen` $\leftrightarrow$ `slot_konsultasi` untuk setiap dosen yang terlibat.

---

*Dikembangkan untuk Politeknik Negeri Lampung (POLINELA).*
