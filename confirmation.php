<?php
// confirmation.php — Booking Confirmed
require_once 'includes/auth.php';
require_once 'config/database.php';
requireLogin();

$page_title = 'Booking Confirmed — Sport Center Hub';
$db  = getDB();
$uid = (int)currentUser()['id'];
$id  = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: book.php'); exit; }

$stmt = $db->prepare("
    SELECT r.*, c.name AS court_name, c.type AS court_type,
           TIMESTAMPDIFF(MINUTE, r.start_time, r.end_time)/60 AS duration_hours
    FROM reservations r JOIN courts c ON c.id=r.court_id
    WHERE r.id=:id AND (r.user_id=:uid OR 1=:is_admin)
");
$stmt->execute([':id'=>$id,':uid'=>$uid,':is_admin'=>(isAdmin()?1:0)]);
$res = $stmt->fetch();
if (!$res) { header('Location: my_bookings.php'); exit; }

include 'includes/header.php';
?>
<link rel="stylesheet" href="assets/book.css"/>

<div class="confirm-wrap">
  <div class="confirm-card">
    <div class="confirm-icon-wrap">
      <div class="confirm-icon">✓</div>
    </div>
    <h1 class="confirm-title">Booking Confirmed!</h1>
    <p class="confirm-sub">See you on the court. Here's your booking summary.</p>
    <div class="confirm-ref">Booking #<?= str_pad($res['id'],5,'0',STR_PAD_LEFT) ?></div>

    <div class="confirm-details">
      <div class="cd-row"><span>Name</span><strong><?= htmlspecialchars($res['renter_name']) ?></strong></div>
      <div class="cd-row"><span>Phone</span><strong><?= htmlspecialchars($res['renter_phone']) ?></strong></div>
      <div class="cd-row"><span>Court</span><strong><?= htmlspecialchars($res['court_name']) ?></strong></div>
      <div class="cd-row"><span>Date</span><strong><?= date('D, d M Y', strtotime($res['booking_date'])) ?></strong></div>
      <div class="cd-row"><span>Time</span><strong><?= substr($res['start_time'],0,5) ?> – <?= substr($res['end_time'],0,5) ?></strong></div>
      <div class="cd-row"><span>Duration</span><strong><?= number_format($res['duration_hours'],1) ?>h</strong></div>
      <div class="cd-row total"><span>Total</span><strong>Rp <?= number_format($res['total_price'],0,',','.') ?></strong></div>
    </div>

    <div class="confirm-notice">
      💳 Payment is due at the venue. Please arrive 10 minutes before your session.
    </div>

    <div class="confirm-actions">
      <a href="book.php"        class="btn btn-primary btn-lg">Book Another Court</a>
      <a href="my_bookings.php" class="btn btn-secondary btn-lg">My Bookings</a>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
