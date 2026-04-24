<?php
// schedule.php — Daily Schedule Grid (Admin only)
require_once 'config/database.php';
require_once 'includes/auth.php';
requireAdmin();

$page_title = 'Daily Schedule — Sport Center Hub';
$db = getDB();

$today   = date('Y-m-d');
$selDate = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])
           ? $_GET['date'] : $today;
$prevDate = date('Y-m-d', strtotime($selDate . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($selDate . ' +1 day'));

// Active courts
$courts = $db->query(
    "SELECT * FROM courts WHERE status='active' ORDER BY type, name"
)->fetchAll(PDO::FETCH_ASSOC);

// Bookings for selected date — cast court_id to INT for reliable indexing
$stmt = $db->prepare("
    SELECT r.id, r.court_id, r.renter_name, r.renter_phone,
           r.start_time, r.end_time, r.total_price, r.status,
           TIMESTAMPDIFF(MINUTE, r.start_time, r.end_time)/60 AS duration_hours,
           c.name AS court_name, c.type AS court_type
    FROM reservations r
    JOIN courts c ON c.id = r.court_id
    WHERE r.booking_date = :d AND r.status = 'confirmed'
    ORDER BY r.start_time ASC
");
$stmt->execute([':d' => $selDate]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build slot map  [court_id (int)][hour (int)] = booking row | null
$hours = range(6, 22); // 06:00 – 22:00
$slots = [];
foreach ($courts as $c) {
    $cid = (int)$c['id'];
    foreach ($hours as $h) $slots[$cid][$h] = null;
}
foreach ($bookings as $b) {
    $cid = (int)$b['court_id'];
    $sh  = (int)substr($b['start_time'], 0, 2);
    $eh  = (int)substr($b['end_time'],   0, 2);
    for ($h = $sh; $h < $eh; $h++) {
        if (array_key_exists($cid, $slots) && array_key_exists($h, $slots[$cid])) {
            $slots[$cid][$h] = $b;
        }
    }
}

// Stats
$bookedSlots = 0;
foreach ($slots as $cs) { foreach ($cs as $s) { if ($s) $bookedSlots++; } }
$totalSlots  = count($courts) * count($hours);

include 'includes/header.php';
?>

<div class="page-header">
  <div>
    <h1>Daily <span>Schedule</span></h1>
    <p>Court availability grid — <?= date('l, d F Y', strtotime($selDate)) ?></p>
  </div>
  <?php if (isAdmin()): ?>
    <a href="reservations.php?action=new&booking_date=<?= $selDate ?>" class="btn btn-primary">
      + Add Reservation
    </a>
  <?php endif; ?>
</div>

<!-- Stats -->
<div class="stats-grid" style="margin-bottom:1.5rem;">
  <div class="stat-card green">
    <div class="stat-card-icon">📋</div>
    <div class="stat-label">Total Reservations</div>
    <div class="stat-value"><?= count($bookings) ?></div>
    <div class="stat-sub"><?= date('d M Y', strtotime($selDate)) ?></div>
  </div>
  <div class="stat-card blue">
    <div class="stat-card-icon">🔴</div>
    <div class="stat-label">Slots Booked</div>
    <div class="stat-value"><?= $bookedSlots ?></div>
    <div class="stat-sub">of <?= $totalSlots ?> total slots</div>
  </div>
  <div class="stat-card orange">
    <div class="stat-card-icon">🟢</div>
    <div class="stat-label">Slots Available</div>
    <div class="stat-value"><?= $totalSlots - $bookedSlots ?></div>
    <div class="stat-sub">Open for booking</div>
  </div>
</div>

<!-- Schedule Grid -->
<div class="schedule-wrap">
  <div class="schedule-toolbar">
    <h2>📅 Court Schedule</h2>
    <div class="date-nav">
      <a href="?date=<?= $prevDate ?>">‹ Prev</a>
      <span><?= date('d M Y', strtotime($selDate)) ?></span>
      <a href="?date=<?= $nextDate ?>">Next ›</a>
      <?php if ($selDate !== $today): ?>
        <a href="?date=<?= $today ?>">Today</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="schedule-scroll">
    <table class="schedule-table">
      <thead>
        <tr>
          <th class="time-col">TIME</th>
          <?php foreach ($courts as $c): ?>
          <th>
            <?= htmlspecialchars($c['name']) ?>
            <br><span class="badge badge-<?= strtolower($c['type']) ?>"><?= $c['type'] ?></span>
          </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($hours as $h): ?>
        <tr>
          <td class="time-cell"><?= sprintf('%02d:00', $h) ?></td>
          <?php foreach ($courts as $c): ?>
            <?php
              $cid  = (int)$c['id'];
              $slot = $slots[$cid][$h] ?? null;
            ?>
            <?php if ($slot): ?>
              <td class="slot-booked <?= strtolower($slot['court_type']) ?>"
                  title="<?= htmlspecialchars($slot['renter_name']) ?> · <?= substr($slot['start_time'],0,5) ?>–<?= substr($slot['end_time'],0,5) ?>">
                <?= htmlspecialchars($slot['renter_name']) ?>
                <br><small><?= substr($slot['start_time'],0,5) ?>–<?= substr($slot['end_time'],0,5) ?></small>
              </td>
            <?php else: ?>
              <td class="slot-free"
                  data-court="<?= $c['id'] ?>"
                  data-hour="<?= sprintf('%02d:00', $h) ?>"
                  data-date="<?= $selDate ?>"
                  title="Click to add booking here"></td>
            <?php endif; ?>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="legend">
    <div class="legend-item"><div class="legend-dot free"></div> Available (click to book)</div>
    <div class="legend-item"><div class="legend-dot padel"></div> Padel</div>
    <div class="legend-item"><div class="legend-dot badminton"></div> Badminton</div>
    <div class="legend-item"><div class="legend-dot tennis"></div> Tennis</div>
  </div>
</div>

<!-- Booking list -->
<?php if (!empty($bookings)): ?>
<div class="section-title">Reservations for <?= date('d M Y', strtotime($selDate)) ?></div>
<div class="table-card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>#</th><th>Renter</th><th>Court</th>
          <th>Start</th><th>End</th><th>Duration</th><th>Total</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bookings as $i => $r): ?>
        <tr>
          <td style="color:var(--text3);font-size:.8rem;"><?= $i+1 ?></td>
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
          <td><?= substr($r['start_time'],0,5) ?></td>
          <td><?= substr($r['end_time'],0,5) ?></td>
          <td><?= number_format($r['duration_hours'],1) ?>h</td>
          <td>Rp <?= number_format($r['total_price'],0,',','.') ?></td>
          <td style="white-space:nowrap;">
            <?php if (isAdmin()): ?>
            <a href="reservations.php?action=edit&id=<?= $r['id'] ?>"
               class="btn btn-secondary btn-sm">Edit</a>
            <a href="reservations.php?action=cancel&id=<?= $r['id'] ?>"
               class="btn btn-danger btn-sm"
               data-confirm="Cancel reservation for <?= htmlspecialchars($r['renter_name']) ?>?">Cancel</a>
            <?php else: ?>
            <span style="font-size:.75rem;color:var(--text4);">View only</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<div class="section-title">Reservations for <?= date('d M Y', strtotime($selDate)) ?></div>
<div class="table-card">
  <div class="empty-state">
    <div class="icon">📭</div>
    <p>No reservations on this date. <?php if(isAdmin()): ?><a href="reservations.php?action=new&booking_date=<?= $selDate ?>">Add one →</a><?php endif; ?></p>
  </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
