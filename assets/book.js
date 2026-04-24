// assets/book.js — Sport Center Hub Booking Flow
// Handles: date pick → time slot gen → duration → court availability → summary → cart

(function () {
  /* ── State ── */
  const state = {
    date:     null,
    start:    null,   // "HH:MM"
    durMins:  60,
    courtId:  null,
    courtName: null,
    courtPrice: 0,
  };

  /* ── Load data embedded by PHP ── */
  const courts   = JSON.parse(document.getElementById('courts-data')?.textContent   || '[]');
  const bookings = JSON.parse(document.getElementById('bookings-data')?.textContent || '[]');

  const OPEN_H = 6;   // 06:00
  const CLOSE_H = 22; // 22:00
  const SLOT_MIN = 30; // slot resolution in minutes

  /* ── DOM refs ── */
  const stepTime     = document.getElementById('step-time');
  const stepDuration = document.getElementById('step-duration');
  const stepCourt    = document.getElementById('step-court');
  const timeGrid     = document.getElementById('timeGrid');
  const courtPicker  = document.getElementById('courtPicker');
  const durationPills= document.getElementById('durationPills');
  const btnContinue  = document.getElementById('btnContinue');

  /* ── Summary refs ── */
  const sumDate  = document.getElementById('sum-date');
  const sumStart = document.getElementById('sum-start');
  const sumEnd   = document.getElementById('sum-end');
  const sumDur   = document.getElementById('sum-dur');
  const sumCourt = document.getElementById('sum-court');
  const sumPrice = document.getElementById('sum-price');

  /* ─────────────────────────────────────────
     UTILS
  ───────────────────────────────────────── */
  function toMins(hhmm) {
    const [h, m] = hhmm.split(':').map(Number);
    return h * 60 + m;
  }
  function fromMins(m) {
    return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
  }
  function formatDur(mins) {
    const h = Math.floor(mins / 60), m = mins % 60;
    return h + 'h' + (m ? ' ' + m + 'm' : '');
  }
  function fmtDate(ymd) {
    const [y, mo, d] = ymd.split('-');
    const names = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const days  = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    const dt = new Date(y, mo - 1, d);
    return days[dt.getDay()] + ', ' + d + ' ' + names[mo - 1] + ' ' + y;
  }

  /* Get all booked minute-ranges for a court on a date */
  function getBookedRanges(courtId, date) {
    return bookings
      .filter(b => b.court_id == courtId && b.booking_date === date)
      .map(b => ({ s: toMins(b.start_time), e: toMins(b.end_time) }));
  }

  /* Check if a slot [startMins, endMins) overlaps any booking on ANY court */
  function isCourtBusy(courtId, date, startMins, endMins) {
    return getBookedRanges(courtId, date).some(r => startMins < r.e && endMins > r.s);
  }

  /* For a given date + start, what's the max duration (mins) until the next booking on ANY court?
     Used to disable duration pills that would overlap. Returns Infinity if no constraint. */
  function maxDurForSlot(courtId, date, startMins) {
    const ranges = getBookedRanges(courtId, date).filter(r => r.s >= startMins);
    if (!ranges.length) return (CLOSE_H * 60) - startMins;
    return Math.min(...ranges.map(r => r.s)) - startMins;
  }

  /* ─────────────────────────────────────────
     STEP 1 — DATE
  ───────────────────────────────────────── */
  document.querySelectorAll('.date-pill').forEach(btn => {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.date-pill').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      state.date  = this.dataset.date;
      state.start = null;
      state.courtId = null;
      buildTimeGrid();
      unlock(stepTime);
      lock(stepDuration);
      lock(stepCourt);
      clearSummary();
    });
  });

  /* ─────────────────────────────────────────
     STEP 2 — TIME SLOTS
  ───────────────────────────────────────── */
  function buildTimeGrid() {
    timeGrid.innerHTML = '';
    const slots = [];
    for (let m = OPEN_H * 60; m < CLOSE_H * 60; m += SLOT_MIN) {
      slots.push(m);
    }

    /* Check if selected date is today — need to block past slots */
    const now        = new Date();
    const todayYmd   = now.getFullYear() + '-' +
                       String(now.getMonth() + 1).padStart(2, '0') + '-' +
                       String(now.getDate()).padStart(2, '0');
    const isToday    = state.date === todayYmd;
    /* Current time in minutes, add 30-min buffer so you can't book a slot starting "now" */
    const nowMins    = now.getHours() * 60 + now.getMinutes() + 30;

    slots.forEach(startMins => {
      const label    = fromMins(startMins);
      const allBusy  = courts.every(c => isCourtBusy(c.id, state.date, startMins, startMins + SLOT_MIN));
      const isPast   = isToday && startMins < nowMins;

      const btn = document.createElement('button');
      btn.dataset.start = label;
      btn.dataset.time  = label;   // for pre-fill lookup

      if (isPast) {
        btn.className   = 'time-slot taken';
        btn.textContent = label;
        btn.title       = 'This time has already passed';
      } else if (allBusy) {
        btn.className   = 'time-slot taken';
        btn.textContent = label;
        btn.title       = 'All courts booked at this time';
      } else {
        btn.className   = 'time-slot';
        btn.textContent = label;
        btn.addEventListener('click', function () {
          document.querySelectorAll('.time-slot').forEach(b => b.classList.remove('active'));
          this.classList.add('active');
          state.start   = this.dataset.start;
          state.courtId = null;
          refreshDurationPills();
          buildCourtPicker();
          unlock(stepDuration);
          unlock(stepCourt);
          updateSummary();
        });
      }
      timeGrid.appendChild(btn);
    });
    stepTime.classList.add('done');
  }

  /* ─────────────────────────────────────────
     STEP 3 — DURATION
  ───────────────────────────────────────── */
  document.querySelectorAll('.dur-pill').forEach(btn => {
    btn.addEventListener('click', function () {
      if (this.classList.contains('disabled')) return;
      document.querySelectorAll('.dur-pill').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      state.durMins = parseInt(this.dataset.mins);
      state.courtId = null;
      buildCourtPicker();
      updateSummary();
    });
  });

  function refreshDurationPills() {
    const startMins = toMins(state.start);
    document.querySelectorAll('.dur-pill').forEach(btn => {
      const d = parseInt(btn.dataset.mins);
      const endMins = startMins + d;
      // disabled if would exceed operating hours
      const tooLate = endMins > CLOSE_H * 60;
      btn.classList.toggle('disabled', tooLate);
    });
    // Activate 60min by default
    const first = document.querySelector('.dur-pill:not(.disabled)');
    document.querySelectorAll('.dur-pill').forEach(b => b.classList.remove('active'));
    if (first) { first.classList.add('active'); state.durMins = parseInt(first.dataset.mins); }
    stepDuration.classList.add('done');
  }

  /* ─────────────────────────────────────────
     STEP 4 — COURT PICKER
  ───────────────────────────────────────── */
  function buildCourtPicker() {
    courtPicker.innerHTML = '';
    if (!state.start || !state.date) return;

    const startMins = toMins(state.start);
    const endMins   = startMins + state.durMins;

    const typeColor = {
      Padel:     { bg: '#e8f8f0', fg: '#1a9e5c' },
      Badminton: { bg: '#eff6ff', fg: '#2563eb' },
      Tennis:    { bg: '#fffbeb', fg: '#d97706' },
    };

    courts.forEach(c => {
      const busy = isCourtBusy(c.id, state.date, startMins, endMins);
      const clr  = typeColor[c.type] || { bg: '#f5f5f5', fg: '#333' };

      const card = document.createElement('div');
      card.className = 'cp-card' + (busy ? ' unavailable' : '');
      card.dataset.id    = c.id;
      card.dataset.name  = c.name;
      card.dataset.price = c.price_per_hour;

      card.innerHTML = `
        <div class="cp-check">✓</div>
        <div class="cp-top">
          <div>
            <div class="cp-type" style="background:${clr.bg};color:${clr.fg}">${c.type}</div>
            <div class="cp-name">${c.name}</div>
          </div>
          <div style="text-align:right">
            <div class="cp-price">Rp ${parseInt(c.price_per_hour).toLocaleString('id-ID')}<br><small>/hour</small></div>
          </div>
        </div>
        <div class="cp-desc">${c.description || ''}</div>
        ${busy ? '<div style="font-size:.72rem;color:#ef4444;font-weight:600;margin-top:.4rem">⚠ Not available for this slot</div>' : ''}
      `;

      if (!busy) {
        card.addEventListener('click', function () {
          document.querySelectorAll('.cp-card').forEach(b => b.classList.remove('selected'));
          this.classList.add('selected');
          state.courtId   = parseInt(this.dataset.id);
          state.courtName = this.dataset.name;
          state.courtPrice= parseFloat(this.dataset.price);
          updateSummary();
          stepCourt.classList.add('done');
        });
      }
      courtPicker.appendChild(card);
    });
  }

  /* ─────────────────────────────────────────
     SUMMARY + CONTINUE BUTTON
  ───────────────────────────────────────── */
  function updateSummary() {
    if (state.date)  sumDate.textContent  = fmtDate(state.date);
    if (state.start) {
      sumStart.textContent = state.start;
      const endMins = toMins(state.start) + state.durMins;
      sumEnd.textContent = fromMins(endMins);
      sumDur.textContent = formatDur(state.durMins);
    }
    if (state.courtName) {
      sumCourt.textContent = state.courtName;
    }
    if (state.courtPrice && state.durMins) {
      const total = state.courtPrice * (state.durMins / 60);
      sumPrice.textContent = 'Rp ' + total.toLocaleString('id-ID');
    }

    const ready = state.date && state.start && state.durMins && state.courtId;
    btnContinue.disabled = !ready;
  }

  function clearSummary() {
    sumDate.textContent  = '—';
    sumStart.textContent = '—';
    sumEnd.textContent   = '—';
    sumDur.textContent   = '—';
    sumCourt.textContent = '—';
    sumPrice.textContent = 'Rp 0';
    btnContinue.disabled = true;
  }

  /* ─────────────────────────────────────────
     NAVIGATE TO CART
  ───────────────────────────────────────── */
  /* ── Checkout: require login for guest users ── */
  window.handleCheckout = function () {
    const isLoggedIn = document.body.dataset.role !== 'guest';
    if (!isLoggedIn) {
      // Build the cart URL so after login they're sent straight to checkout
      if (!state.date || !state.start || !state.durMins || !state.courtId) return;
      const startMins = toMins(state.start);
      const end = fromMins(startMins + state.durMins);
      const cartUrl = 'cart.php?' + new URLSearchParams({
        court_id: state.courtId,
        date:     state.date,
        start:    state.start,
        end:      end,
      }).toString();
      window.location.href = 'login.php?redirect=' + encodeURIComponent(cartUrl);
      return;
    }
    window.goToCart();
  };

  window.goToCart = function () {
    if (!state.date || !state.start || !state.durMins || !state.courtId) return;
    const startMins = toMins(state.start);
    const end = fromMins(startMins + state.durMins);
    const params = new URLSearchParams({
      court_id: state.courtId,
      date:     state.date,
      start:    state.start,
      end:      end,
    });
    window.location.href = 'cart.php?' + params.toString();
  };

  /* ─────────────────────────────────────────
     LOCK / UNLOCK STEPS
  ───────────────────────────────────────── */
  function lock(el)   { el.classList.add('locked');   el.classList.remove('unlocked'); }
  function unlock(el) { el.classList.remove('locked'); el.classList.add('unlocked'); }

  /* Init — unlock duration & court if pre-filled */
  lock(stepTime);
  lock(stepDuration);
  lock(stepCourt);

  /* ── Pre-fill from URL params (e.g. from schedule.php click) ── */
  const urlParams = new URLSearchParams(window.location.search);
  const preDate  = urlParams.get('date');
  const preStart = urlParams.get('start');
  const preCourt = urlParams.get('court');

  if (preDate) {
    const pill = document.querySelector(`.date-pill[data-date="${preDate}"]`);
    if (pill) {
      pill.click();
      pill.scrollIntoView({ inline: 'center', behavior: 'smooth' });
    } else {
      // Fallback: select today
      const todayPill = document.querySelector('.date-pill.today');
      if (todayPill) todayPill.click();
    }
  } else {
    /* Auto-select today's date */
    const todayPill = document.querySelector('.date-pill.today');
    if (todayPill) todayPill.click();
  }

  // After date is set, pre-select time slot if provided
  if (preDate && preStart) {
    setTimeout(() => {
      const timeBtn = document.querySelector(`.time-slot[data-time="${preStart}"]`);
      if (timeBtn && !timeBtn.disabled) timeBtn.click();
    }, 100);
  }

  // After time set, pre-select court if provided
  if (preDate && preStart && preCourt) {
    setTimeout(() => {
      const courtCard = document.querySelector(`.cp-card[data-id="${preCourt}"]`);
      if (courtCard && !courtCard.classList.contains('busy')) courtCard.click();
    }, 200);
  }

})();
