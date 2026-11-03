/**
 * S-LECTURA - Politeknik Negeri Lampung
 * JavaScript Antarmuka & Utilitas Interaktif
 * File: assets/js/app.js
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Auto-dismiss flash alerts setelah 5 detik
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 5000);
    });

    // 2. Dialog konfirmasi untuk aksi hapus (data-confirm)
    document.querySelectorAll('[data-confirm]').forEach(function (element) {
        element.addEventListener('click', function (e) {
            const message = this.getAttribute('data-confirm') || 'Apakah Anda yakin ingin menghapus data ini?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // 3. Validasi instan jam selesai harus lebih besar dari jam mulai pada formulir
    const formsWithTime = document.querySelectorAll('form');
    formsWithTime.forEach(function (form) {
        const jamMulai = form.querySelector('input[name="jam_mulai"]');
        const jamSelesai = form.querySelector('input[name="jam_selesai"]');

        if (jamMulai && jamSelesai) {
            form.addEventListener('submit', function (e) {
                if (jamMulai.value && jamSelesai.value) {
                    if (jamSelesai.value <= jamMulai.value) {
                        e.preventDefault();
                        alert('Jam selesai harus lebih akhir dari jam mulai!');
                        jamSelesai.focus();
                    }
                }
            });
        }
    });

    // 4. Filter instan kartu dosen di sisi klien (jika elemen tersedia)
    const searchInput = document.getElementById('searchLecturerInput');
    const prodiFilter = document.getElementById('filterProdiSelect');
    const statusFilter = document.getElementById('filterStatusSelect');
    const lecturerCards = document.querySelectorAll('.lecturer-card-item');

    function applyFilters() {
        if (!lecturerCards.length) return;

        const searchVal = (searchInput ? searchInput.value : '').toLowerCase().trim();
        const prodiVal = prodiFilter ? prodiFilter.value : '';
        const statusVal = statusFilter ? statusFilter.value : '';

        let visibleCount = 0;

        lecturerCards.forEach(function (card) {
            const name = (card.getAttribute('data-name') || '').toLowerCase();
            const prodi = card.getAttribute('data-prodi') || '';
            const status = card.getAttribute('data-status') || '';
            const keahlian = (card.getAttribute('data-keahlian') || '').toLowerCase();

            const matchSearch = !searchVal || name.includes(searchVal) || keahlian.includes(searchVal);
            const matchProdi = !prodiVal || prodi === prodiVal;
            const matchStatus = !statusVal || status === statusVal;

            if (matchSearch && matchProdi && matchStatus) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        const emptyMessage = document.getElementById('noResultsMessage');
        if (emptyMessage) {
            emptyMessage.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }

    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (prodiFilter) prodiFilter.addEventListener('change', applyFilters);
    if (statusFilter) statusFilter.addEventListener('change', applyFilters);
});

