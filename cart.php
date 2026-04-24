<?php
// cart.php — Customer Checkout
require_once 'includes/auth.php';
require_once 'config/database.php';
requireLogin();

$page_title = 'Checkout — Sport Center Hub';
$db  = getDB();
$uid = currentUser()['id'] ? (int)currentUser()['id'] : null;

$court_id    = (int)($_GET['court_id'] ?? 0);
$booking_date= trim($_GET['date']      ?? '');
$start_time  = trim($_GET['start']     ?? '');
$end_time    = trim($_GET['end']       ?? '');

$error = null;
$court = null;

if ($court_id && $booking_date && $start_time && $end_time) {
    $s = $db->prepare("SELECT * FROM courts WHERE id=:id AND status='active'");
    $s->execute([':id'=>$court_id]);
    $court = $s->fetch();
    if (!$court) $error = 'Court not found.';
    if ($court) {
        // Block bookings in the past
        $slotDateTime = $booking_date . ' ' . $start_time;
        if (strtotime($slotDateTime) < time()) {
            $error = 'You cannot book a time slot that has already passed.';
        }
        if (!$error) {
            $chk = $db->prepare("SELECT id FROM reservations WHERE court_id=:c AND booking_date=:d AND status='confirmed' AND start_time<:e AND end_time>:s LIMIT 1");
            $chk->execute([':c'=>$court_id,':d'=>$booking_date,':e'=>$end_time,':s'=>$start_time]);
            if ($chk->fetch()) $error = 'This slot was just booked. Please go back and choose another.';
        }
    }
} else {
    $error = 'Invalid booking parameters. Please start again.';
}

$mins  = $court ? (strtotime("1970-01-01 $end_time") - strtotime("1970-01-01 $start_time")) / 60 : 0;
$hrs   = $mins / 60;
$total = $court ? $hrs * $court['price_per_hour'] : 0;

// ── POST: Confirm ──
if ($_SERVER['REQUEST_METHOD']==='POST' && !$error) {
    $name  = trim($_POST['renter_name']  ?? '');
    $phone = trim($_POST['renter_phone'] ?? '');
    $notes = trim($_POST['notes']        ?? '');
    $errs  = [];
    if (!$name)  $errs[] = 'Full name is required.';
    if (!$phone) $errs[] = 'Phone number is required.';

    if (empty($errs)) {
        // Final conflict check
        $chk = $db->prepare("SELECT id FROM reservations WHERE court_id=:c AND booking_date=:d AND status='confirmed' AND start_time<:e AND end_time>:s LIMIT 1");
        $chk->execute([':c'=>$court_id,':d'=>$booking_date,':e'=>$end_time,':s'=>$start_time]);
        if ($chk->fetch()) {
            $error = 'Someone just booked this slot. Please go back and pick another time.';
        } else {
            $ins = $db->prepare("INSERT INTO reservations (court_id,user_id,renter_name,renter_phone,booking_date,start_time,end_time,total_price,notes) VALUES (:c,:u,:n,:p,:d,:s,:e,:t,:no)");
            $ins->execute([':c'=>$court_id,':u'=>$uid,':n'=>$name,':p'=>$phone,':d'=>$booking_date,':s'=>$start_time,':e'=>$end_time,':t'=>$total,':no'=>$notes]);
            $newId = $db->lastInsertId();
            $_SESSION['flash'] = ['type'=>'success','msg'=>'Booking confirmed! See you on the court 🎾'];
            header("Location: confirmation.php?id=$newId"); exit;
        }
    }
}

$typeColor = [
    'Padel'    => ['bg'=>'#e8f8f0','fg'=>'#1a9e5c'],
    'Badminton'=> ['bg'=>'#eff6ff','fg'=>'#2563eb'],
    'Tennis'   => ['bg'=>'#fffbeb','fg'=>'#d97706'],
];
$clr = $typeColor[$court['type'] ?? ''] ?? ['bg'=>'#f5f5f5','fg'=>'#333'];

include 'includes/header.php';
?>
<link rel="stylesheet" href="assets/book.css"/>

<?php if ($error): ?>
<div style="max-width:520px;margin:3rem auto;text-align:center;">
  <div style="font-size:3rem;margin-bottom:1rem;">😕</div>
  <h2 style="font-family:var(--font-head);font-size:1.5rem;margin-bottom:.5rem;">Oops!</h2>
  <p style="color:var(--text3);margin-bottom:1.5rem;"><?= htmlspecialchars($error) ?></p>
  <a href="book.php" class="btn btn-primary btn-lg">← Back to Booking</a>
</div>
<?php else: ?>

<div class="cart-page">
  <div class="breadcrumb">
    <a href="book.php">← Back to Schedule</a>
    <span class="bc-sep">/</span>
    <span>Checkout</span>
  </div>

  <div class="cart-layout">

    <!-- Customer Form -->
    <div class="cart-form-col">
      <div class="cart-section-card">
        <div class="cart-section-title">
          <div class="cs-icon">👤</div>
          <h2>Your Details</h2>
        </div>

        <?php if (!empty($errs)): ?>
          <div class="flash flash--error" style="margin-bottom:1.25rem;border-radius:var(--radius-sm);flex-direction:column;align-items:flex-start;gap:.3rem;">
            <?php foreach($errs as $e): ?><span>⚠ <?= htmlspecialchars($e) ?></span><?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="cart.php?court_id=<?= $court_id ?>&date=<?= urlencode($booking_date) ?>&start=<?= urlencode($start_time) ?>&end=<?= urlencode($end_time) ?>">
          <div class="form-grid" style="grid-template-columns:1fr;gap:1rem;">
            <div class="form-group">
              <label>Full Name *</label>
              <input type="text" name="renter_name"
                     value="<?= htmlspecialchars($_POST['renter_name'] ?? currentUser()['name']) ?>"
                     placeholder="e.g. John Doe" required/>
            </div>
            <div class="form-group">
              <label>Phone Number *</label>
              <input type="tel" name="renter_phone"
                     value="<?= htmlspecialchars($_POST['renter_phone'] ?? '') ?>"
                     placeholder="08xxxxxxxxxx" required/>
            </div>
            <div class="form-group">
              <label>Notes (optional)</label>
              <textarea name="notes" rows="3" placeholder="Equipment needs, special requests…"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
            </div>
          </div>

          <div class="policy-box" style="margin-top:1.25rem;">
            <div class="policy-item"><span class="policy-icon">✓</span><span>Free cancellation before your session starts</span></div>
            <div class="policy-item"><span class="policy-icon">✓</span><span>Please arrive 10 minutes early to prepare</span></div>
            <div class="policy-item"><span class="policy-icon">✓</span><span>Bring appropriate sports footwear</span></div>
          </div>

          <button type="submit" class="btn-book" style="width:100%;margin-top:1.5rem;font-size:1rem;">
            ✓ Confirm Booking — Rp <?= number_format($total, 0, ',', '.') ?>
          </button>
        </form>
      </div>
    </div>

    <!-- Order Summary -->
    <div class="cart-summary-col">
      <div class="cart-court-card" style="border-top:4px solid <?= $clr['fg'] ?>">
        <div class="ccc-type-badge" style="background:<?= $clr['bg'] ?>;color:<?= $clr['fg'] ?>"><?= htmlspecialchars($court['type']) ?></div>
        <div class="ccc-name"><?= htmlspecialchars($court['name']) ?></div>
        <div class="ccc-desc"><?= htmlspecialchars($court['description'] ?? '') ?></div>
        <div class="ccc-rate">Rp <?= number_format($court['price_per_hour'],0,',','.') ?> <small>/ hour</small></div>
      </div>

      <div class="order-detail-card">
        <div class="od-title">Order Details</div>
        <div class="od-row"><span class="od-label">📅 Date</span><span class="od-value"><?= date('D, d M Y', strtotime($booking_date)) ?></span></div>
        <div class="od-row"><span class="od-label">⏰ Time</span><span class="od-value"><?= substr($start_time,0,5) ?> – <?= substr($end_time,0,5) ?></span></div>
        <div class="od-row"><span class="od-label">⌛ Duration</span><span class="od-value"><?= $hrs ?>h</span></div>
        <div class="od-row"><span class="od-label">🏟 Court</span><span class="od-value"><?= htmlspecialchars($court['name']) ?></span></div>
        <div class="od-divider"></div>
        <div class="od-row"><span class="od-label">Rate/hour</span><span class="od-value">Rp <?= number_format($court['price_per_hour'],0,',','.') ?></span></div>
        <div class="od-row"><span class="od-label">Hours</span><span class="od-value">× <?= $hrs ?></span></div>
        <div class="od-divider"></div>
        <div class="od-row total"><span class="od-label">Total</span><span class="od-value green">Rp <?= number_format($total,0,',','.') ?></span></div>
      </div>

      <div class="secure-note">
        🔒 Pay at the venue — no upfront payment required.
      </div>
    </div>

  </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
