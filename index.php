<?php
// index.php — Admin Dashboard
require_once 'includes/auth.php';
require_once 'config/database.php';
requireAdmin();

$page_title = 'Dashboard — Sport Center Hub';
$db = getDB();

$totalCourts   = $db->query("SELECT COUNT(*) FROM courts WHERE status='active'")->fetchColumn();
$todayBookings = $db->query("SELECT COUNT(*) FROM reservations WHERE booking_date=CURDATE() AND status='confirmed'")->fetchColumn();
$monthRevenue  = $db->query("SELECT COALESCE(SUM(total_price),0) FROM reservations WHERE MONTH(booking_date)=MONTH(CURDATE()) AND YEAR(booking_date)=YEAR(CURDATE()) AND status='confirmed'")->fetchColumn();
$totalBookings = $db->query("SELECT COUNT(*) FROM reservations WHERE status='confirmed'")->fetchColumn();

$todayRes = $db->query("
    SELECT r.*, c.name AS court_name, c.type AS court_type,
           TIMESTAMPDIFF(MINUTE, r.start_time, r.end_time)/60 AS duration_hours
    FROM reservations r JOIN courts c ON c.id=r.court_id
    WHERE r.booking_date=CURDATE() AND r.status='confirmed'
    ORDER BY r.start_time ASC
")->fetchAll();

$upcoming = $db->query("
    SELECT r.*, c.name AS court_name, c.type AS court_type
    FROM reservations r JOIN courts c ON c.id=r.court_id
    WHERE r.booking_date BETWEEN DATE_ADD(CURDATE(),INTERVAL 1 DAY)
                              AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)
      AND r.status='confirmed'
    ORDER BY r.booking_date ASC, r.start_time ASC LIMIT 10
")->fetchAll();

include 'includes/header.php';
?>

<!-- Hero Banner -->
<div class="hero-banner">
  <div style="position:relative;z-index:1;">
    <h1>Admin <span>Dashboard</span> ⚙</h1>
    <p><?= date('l, d F Y') ?> — Sport Center Hub Management</p>
  </div>
  <div class="hero-banner-actions">
    <a href="reservations.php?action=new" class="btn btn-white btn-lg">+ Add Reservation</a>
    <a href="schedule.php" class="btn btn-ghost btn-lg">📅 View Schedule</a>
  </div>
</div>

<!-- Stat Cards -->
<div class="stats-grid">
  <div class="stat-card green">
    <div class="stat-card-icon">🏟</div>
    <div class="stat-label">Active Courts</div>
    <div class="stat-value"><?= $totalCourts ?></div>
    <div class="stat-sub">Padel · Badminton · Tennis</div>
  </div>
  <div class="stat-card blue">
    <div class="stat-card-icon">📅</div>
    <div class="stat-label">Today's Bookings</div>
    <div class="stat-value"><?= $todayBookings ?></div>
    <div class="stat-sub"><?= date('d M Y') ?></div>
  </div>
  <div class="stat-card orange">
    <div class="stat-card-icon">📊</div>
    <div class="stat-label">All-Time Bookings</div>
    <div class="stat-value"><?= $totalBookings ?></div>
    <div class="stat-sub">Confirmed reservations</div>
  </div>
  <div class="stat-card purple">
    <div class="stat-card-icon">💰</div>
    <div class="stat-label">Revenue This Month</div>
    <div class="stat-value small">Rp <?= number_format($monthRevenue,0,',','.') ?></div>
    <div class="stat-sub"><?= date('F Y') ?></div>
  </div>
</div>

<!-- Quick Actions -->
<div class="quick-actions">
  <a href="schedule.php"     class="btn btn-secondary">📅 Today's Schedule</a>
  <a href="courts.php"       class="btn btn-secondary">🏟 Manage Courts</a>
  <a href="reservations.php" class="btn btn-secondary">📋 All Reservations</a>
  <a href="reservations.php?action=new" class="btn btn-primary">+ New Reservation</a>
</div>

<!-- Today's Reservations Table -->
<div class="table-card">
  <div class="table-card-header">
    <h2>📅 Today's Reservations</h2>
    <a href="schedule.php" class="btn btn-secondary btn-sm">View Schedule →</a>
  </div>
  <div class="table-wrapper">
    <?php if (empty($todayRes)): ?>
      <div class="empty-state">
        <div class="icon">📭</div>
        <p>No reservations today. <a href="reservations.php?action=new">Add one →</a></p>
      </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Renter</th><th>Court</th><th>Time</th>
          <th>Duration</th><th>Total</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($todayRes as $r): ?>
        <tr>
          <td>
            <div class="renter-name"><?= htmlspecialchars($r['renter_name']) ?></div>
            <?php if ($r['renter_phone']): ?>
              <div class="renter-phone"><?= htmlspecialchars($r['renter_phone']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <?= htmlspecialchars($r['court_name']) ?>
            <br><span class="badge badge-<?= strtolower($r['court_type']) ?>"><?= $r['court_type'] ?></span>
          </td>
          <td><?= substr($r['start_time'],0,5) ?> – <?= substr($r['end_time'],0,5) ?></td>
          <td><?= number_format($r['duration_hours'],1) ?>h</td>
          <td>Rp <?= number_format($r['total_price'],0,',','.') ?></td>
          <td style="white-space:nowrap;">
            <a href="reservations.php?action=edit&id=<?= $r['id'] ?>"
               class="btn btn-secondary btn-sm">Edit</a>
            <a href="reservations.php?action=cancel&id=<?= $r['id'] ?>"
               class="btn btn-danger btn-sm"
               data-confirm="Cancel reservation for <?= htmlspecialchars($r['renter_name']) ?>?">Cancel</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- Upcoming -->
<?php if (!empty($upcoming)): ?>
<div class="section-title">Upcoming — Next 7 Days</div>
<div class="table-card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>Date</th><th>Renter</th><th>Court</th><th>Time</th><th>Total</th></tr>
      </thead>
      <tbody>
        <?php foreach ($upcoming as $r): ?>
        <tr>
          <td><?= date('D, d M', strtotime($r['booking_date'])) ?></td>
          <td><div class="renter-name"><?= htmlspecialchars($r['renter_name']) ?></div></td>
          <td>
            <?= htmlspecialchars($r['court_name']) ?>
            <span class="badge badge-<?= strtolower($r['court_type']) ?>"><?= $r['court_type'] ?></span>
          </td>
          <td><?= substr($r['start_time'],0,5) ?> – <?= substr($r['end_time'],0,5) ?></td>
          <td>Rp <?= number_format($r['total_price'],0,',','.') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
