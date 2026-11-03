<?php
// ==============================================================
// S-LECTURA - Politeknik Negeri Lampung
// Template Footer & Skrip JS
// File: includes/footer.php
// ==============================================================
?>
    </div>
</main>

<footer class="py-4 mt-auto border-top border-secondary border-opacity-25">
    <div class="container">
        <div class="row gy-3 align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <div class="d-flex align-items-center justify-content-center justify-content-md-start gap-2 mb-1">
                    <span class="badge bg-success p-1 px-2"><i class="bi bi-mortarboard-fill"></i></span>
                    <strong class="text-white">S-LECTURA</strong>
                    <span class="text-white-50">— Smart Lecturer Availability System</span>
                </div>
                <p class="small text-muted mb-0">
                    Sistem Informasi Ketersediaan Dosen Terpusat & Mandiri.
                    <span class="d-block text-white-50" style="font-size: 0.8rem;">
                        <i class="bi bi-shield-check me-1 text-success"></i>Prinsip Privasi: Sistem hanya menampilkan jadwal & status mandiri tanpa pelacakan fisik (GPS/RFID).
                    </span>
                </p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <p class="small text-muted mb-1">
                    &copy; <?= date('Y') ?> <strong>Politeknik Negeri Lampung (POLINELA)</strong>.
                </p>
                <p class="small text-muted mb-0" style="font-size: 0.78rem;">
                    Jl. Soekarno Hatta No. 10, Rajabasa, Bandar Lampung, Lampung 35144
                </p>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5.3 JS Bundle (Lokal Offline + CDN Fallback) -->
<script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
<script>
if (typeof bootstrap === 'undefined') {
    document.write('<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"><\/script>');
}
</script>
<!-- Script Kustom S-LECTURA -->
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>

