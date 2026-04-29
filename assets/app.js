// assets/app.js — Sport Center Hub

/* ── Availability Checker ── */
(function () {
  const form   = document.getElementById('reservation-form');
  if (!form) return;

  const courtSel  = document.getElementById('court_id');
  const dateIn    = document.getElementById('booking_date');
  const startIn   = document.getElementById('start_time');
  const endIn     = document.getElementById('end_time');
  const statusEl  = document.getElementById('avail-status');
  const submitBtn = document.getElementById('submit-btn');
  const priceEl   = document.getElementById('price-val');

  // Court prices passed from PHP
  const courtPrices = JSON.parse(document.getElementById('court-prices-data')?.textContent || '{}');

  async function checkAvailability() {
    const court = courtSel.value;
    const date  = dateIn.value;
    const start = startIn.value;
    const end   = endIn.value;
    const editId = form.dataset.editId || '';

    if (!court || !date || !start || !end) {
      setStatus('idle', 'Isi semua field untuk mengecek ketersediaan');
      updatePrice();
      return;
    }

    if (start >= end) {
      setStatus('busy', '⚠ Jam selesai harus lebih besar dari jam mulai');
      if (submitBtn) submitBtn.disabled = true;
      return;
    }

    setStatus('idle', '⏳ Mengecek ketersediaan...');
    try {
      const params = new URLSearchParams({ court_id: court, booking_date: date, start_time: start, end_time: end, exclude_id: editId });
      const res = await fetch('api/check_availability.php?' + params);
      const data = await res.json();
      if (data.available) {
        setStatus('ok', '✓ Lapangan tersedia pada jam yang dipilih');
        if (submitBtn) submitBtn.disabled = false;
      } else {
        setStatus('busy', '✗ Lapangan sudah dipesan: ' + (data.conflict || 'jam bentrok'));
        if (submitBtn) submitBtn.disabled = true;
      }
    } catch {
      setStatus('idle', 'Gagal mengecek — periksa koneksi');
    }
    updatePrice();
  }

  function setStatus(type, msg) {
    if (!statusEl) return;
    statusEl.className = type;
    statusEl.textContent = msg;
  }

  function updatePrice() {
    if (!priceEl) return;
    const court = courtSel.value;
    const start = startIn.value;
    const end   = endIn.value;
    const price = courtPrices[court] || 0;
    if (start && end && start < end && price) {
      const mins = timeToMins(end) - timeToMins(start);
      const hrs  = mins / 60;
      const total = hrs * price;
      priceEl.textContent = 'Rp ' + total.toLocaleString('id-ID');
    } else {
      priceEl.textContent = 'Rp 0';
    }
  }

  function timeToMins(t) {
    const [h, m] = t.split(':').map(Number);
    return h * 60 + m;
  }

  [courtSel, dateIn, startIn, endIn].forEach(el => {
    if (el) el.addEventListener('change', checkAvailability);
  });
  [startIn, endIn].forEach(el => {
    if (el) el.addEventListener('input', checkAvailability);
  });
})();

/* ── Delete Confirmation ── */
document.addEventListener('click', function (e) {
  const btn = e.target.closest('[data-confirm]');
  if (!btn) return;
  if (!confirm(btn.dataset.confirm)) e.preventDefault();
});

/* ── Auto-dismiss flash ── */
setTimeout(() => {
  document.querySelectorAll('.flash').forEach(el => el.style.opacity = '0');
  setTimeout(() => document.querySelectorAll('.flash').forEach(el => el.remove()), 400);
}, 4000);

/* ── Schedule slot click: admin → reservations.php, customer → book.php ── */
document.querySelectorAll('.slot-free').forEach(cell => {
  cell.addEventListener('click', function () {
    const courtId = this.dataset.court;
    const hour    = this.dataset.hour;
    const date    = this.dataset.date;
    const isAdmin = document.body.dataset.role === 'admin';
    if (isAdmin) {
      window.location.href = `reservations.php?court_id=${courtId}&start_time=${hour}&booking_date=${date}`;
    } else {
      window.location.href = `book.php?date=${date}&start=${hour}&court=${courtId}`;
    }
  });
});
