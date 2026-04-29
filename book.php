<?php
// book.php — Customer: Book a Court (padelclub.id style)
require_once 'includes/auth.php';
require_once 'config/database.php';
// No requireLogin here — guests can browse courts; login prompt shown at checkout

$page_title = 'Book a Court — Sport Center Hub';
$db = getDB();

$courts = $db->query(
    "SELECT * FROM courts WHERE status='active' ORDER BY type, name"
)->fetchAll(PDO::FETCH_ASSOC);

$futureBookings = $db->query("
    SELECT court_id, booking_date,
           DATE_FORMAT(start_time,'%H:%i') AS start_time,
           DATE_FORMAT(end_time,'%H:%i')   AS end_time
    FROM reservations
    WHERE booking_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)
      AND status = 'confirmed'
")->fetchAll(PDO::FETCH_ASSOC);

$today = date('Y-m-d');
$dates = [];
for ($i = 0; $i < 60; $i++) {
    $ts = strtotime("+$i days");
    $dates[] = [
        'ymd'  => date('Y-m-d', $ts),
        'day'  => date('D', $ts),
        'date' => date('d', $ts),
        'mon'  => date('M', $ts),
    ];
}

include 'includes/header.php';
?>
<link rel="stylesheet" href="assets/book.css"/>

<div class="book-page-header">
  <h1 class="book-title">Book a <span>Court</span></h1>
  <p class="book-subtitle">Select your date, time, duration, and court — then proceed to checkout.</p>
</div>

<?php if (!isLoggedIn()): ?>
<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:var(--radius);padding:.85rem 1.25rem;margin-bottom:1.5rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
  <div style="display:flex;align-items:center;gap:.6rem;font-size:.875rem;color:#92400e;">
    <span style="font-size:1.1rem;">&#128274;</span>
    <span>You need to <strong>log in</strong> to complete a booking. Browse courts first!</span>
  </div>
  <a href="login.php" class="btn btn-primary btn-sm" style="white-space:nowrap;">Login / Register &rarr;</a>
</div>
<?php endif; ?>

<div class="book-layout">

  <!-- LEFT: Steps -->
  <div class="book-steps">

    <!-- Step 1: Date -->
    <div class="step-card unlocked" id="step-date">
      <div class="step-header">
        <div class="step-num">1</div>
        <h2 class="step-title">Select Date</h2>
      </div>
      <p class="step-hint">Scroll to browse — tap a date to select</p>
      <div class="date-strip-wrap">
        <div class="date-strip" id="dateStrip">
          <?php foreach ($dates as $d): ?>
          <button class="date-pill <?= $d['ymd']===$today?'today':'' ?>"
                  data-date="<?= $d['ymd'] ?>">
            <span class="dp-day"><?= $d['day'] ?></span>
            <span class="dp-num"><?= $d['date'] ?></span>
            <span class="dp-mon"><?= $d['mon'] ?></span>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Step 2: Time -->
    <div class="step-card locked" id="step-time">
      <div class="step-header">
        <div class="step-num">2</div>
        <h2 class="step-title">Select Start Time</h2>
      </div>
      <p class="step-hint">Greyed-out slots are fully booked</p>
      <div class="time-slots-grid" id="timeGrid">
        <div class="slots-placeholder">← Select a date first</div>
      </div>
    </div>

    <!-- Step 3: Duration -->
    <div class="step-card locked" id="step-duration">
      <div class="step-header">
        <div class="step-num">3</div>
        <h2 class="step-title">Select Duration</h2>
      </div>
      <p class="step-hint">How long would you like to play?</p>
      <div class="duration-pills" id="durationPills">
        <?php foreach ([60,90,120,150,180] as $m):
          $label = $m >= 60
            ? (floor($m/60).'h'.($m%60 ? ' '.($m%60).'m' : ''))
            : $m.'m';
        ?>
        <button class="dur-pill <?= $m===60?'active':'' ?>" data-mins="<?= $m ?>">
          <span class="dur-num"><?= $label ?></span>
          <span class="dur-sub"><?= $m ?> min</span>
        </button>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Step 4: Court -->
    <div class="step-card locked" id="step-court">
      <div class="step-header">
        <div class="step-num">4</div>
        <h2 class="step-title">Select Court</h2>
      </div>
      <p class="step-hint">Available courts for your chosen slot</p>
      <div class="court-picker" id="courtPicker">
        <div class="slots-placeholder">← Complete steps above</div>
      </div>
    </div>

  </div><!-- /.book-steps -->

  <!-- RIGHT: Summary Sidebar -->
  <div class="book-sidebar">
    <div class="summary-card">
      <div class="summary-header"><h3>Booking Summary</h3></div>
      <div class="summary-body">
        <div class="summary-row"><span class="sr-label">Venue</span><span class="sr-value">Sport Center Hub</span></div>
        <div class="summary-row"><span class="sr-label">Date</span><span class="sr-value" id="sum-date">—</span></div>
        <div class="summary-row"><span class="sr-label">Start</span><span class="sr-value" id="sum-start">—</span></div>
        <div class="summary-row"><span class="sr-label">End</span><span class="sr-value" id="sum-end">—</span></div>
        <div class="summary-row"><span class="sr-label">Duration</span><span class="sr-value" id="sum-dur">—</span></div>
        <div class="summary-row"><span class="sr-label">Court</span><span class="sr-value" id="sum-court">—</span></div>
        <div class="summary-divider"></div>
        <div class="summary-row total">
          <span class="sr-label">Total</span>
          <span class="sr-value green" id="sum-price">Rp 0</span>
        </div>
      </div>
      <div class="summary-footer">
        <button class="btn-book" id="btnContinue" disabled onclick="handleCheckout()">
          Continue to Checkout →
        </button>
        <?php if (!isLoggedIn()): ?>
        <p class="summary-note" id="checkout-note" style="color:#d97706;">&#128274; Login required to confirm booking</p>
        <?php else: ?>
        <p class="summary-note" id="checkout-note">Fill in your details on the next step</p>
        <?php endif; ?>
      </div>
    </div>
    <div class="info-card"><div class="info-icon">⏰</div>
      <div><div class="info-title">Operating Hours</div><div class="info-text">Daily 06:00 – 22:00</div></div>
    </div>
    <div class="info-card"><div class="info-icon">📋</div>
      <div><div class="info-title">Cancellation</div><div class="info-text">Free cancellation before session starts</div></div>
    </div>
    <div class="info-card"><div class="info-icon">💳</div>
      <div><div class="info-title">Payment</div><div class="info-text">Pay at the venue — no upfront charge</div></div>
    </div>
  </div>

</div><!-- /.book-layout -->

<script id="courts-data"   type="application/json"><?= json_encode($courts, JSON_HEX_TAG) ?></script>
<script id="bookings-data" type="application/json"><?= json_encode($futureBookings, JSON_HEX_TAG) ?></script>
<script src="assets/book.js"></script>

<?php include 'includes/footer.php'; ?>
