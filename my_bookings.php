<?php
// my_bookings.php — Customer: view own reservations
require_once 'includes/auth.php';
require_once 'config/database.php';
requireLogin();

$page_title = (isAdmin() ? 'All Bookings' : 'My Bookings') . ' — Sport Center Hub';
$db  = getDB();
$uid = (int)currentUser()['id'];

// Cancel own booking
if (isset($_GET['cancel']) && (int)$_GET['cancel'] > 0) {
    $id = (int)$_GET['cancel'];
    // Only cancel if it belongs to this user and is in the future
    $chk = $db->prepare("SELECT id, booking_date, start_time FROM reservations WHERE id=:id AND user_id=:uid AND status='confirmed'");
    $chk->execute([':id'=>$id,':uid'=>$uid]);
    $row = $chk->fetch();
    if ($row) {
        $sessionStart = strtotime($row['booking_date'].' '.$row['start_time']);
        if ($sessionStart > time()) {
            $db->prepare("UPDATE reservations SET status='cancelled' WHERE id=:id")->execute([':id'=>$id]);
            $_SESSION['flash'] = ['type'=>'info','msg'=>'Booking cancelled successfully.'];
        } else {
            $_SESSION['flash'] = ['type'=>'error','msg'=>'Cannot cancel a past or ongoing session.'];
        }
    }
    header('Location: my_bookings.php'); exit;
}

// Load user bookings
$filter  = $_GET['filter'] ?? 'upcoming'; // upcoming | past | all
$page    = max(1,(int)($_GET['p']??1));
$perPage = 10;
$offset  = ($page-1)*$perPage;

$dateFilter = match($filter) {
    'upcoming' => "AND r.booking_date >= CURDATE()",
    'past'     => "AND r.booking_date <  CURDATE()",
    default    => ""
};

$userFilter = isAdmin() ? "" : "AND r.user_id=:uid";
$cntStmt = $db->prepare("SELECT COUNT(*) FROM reservations r JOIN courts c ON c.id=r.court_id WHERE 1=1 $userFilter $dateFilter");
$cntBinds = isAdmin() ? [] : [':uid'=>$uid];
$cntStmt->execute($cntBinds);
$total     = (int)$cntStmt->fetchColumn();
$totalPages= (int)ceil($total/$perPage);

$stmt = $db->prepare("
    SELECT r.*, c.name AS court_name, c.type AS court_type, c.price_per_hour,
           TIMESTAMPDIFF(MINUTE, r.start_time, r.end_time)/60 AS duration_hours
    FROM reservations r JOIN courts c ON c.id=r.court_id
    WHERE 1=1 $userFilter $dateFilter
    ORDER BY r.booking_date DESC, r.start_time DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($cntBinds);
$bookings = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
  <div>
    <h1>My <span>Bookings</span></h1>
    <p>Hello, <?= htmlspecialchars(currentUser()['name']) ?>! Here are your reservations.</p>
  </div>
  <a href="book.php" class="btn btn-primary">+ Book a Court</a>
</div>

<!-- Filter tabs -->
<div style="display:flex;gap:.4rem;margin-bottom:1.5rem;background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:.35rem;width:fit-content;box-shadow:var(--shadow-sm);">
  <?php foreach(['upcoming'=>'Upcoming','past'=>'Past','all'=>'All'] as $k=>$label): ?>
    <a href="?filter=<?= $k ?>"
       class="btn <?= $filter===$k?'btn-primary':'btn-secondary' ?> btn-sm"
       style="border:none;<?= $filter===$k?'':'background:transparent;box-shadow:none;' ?>"
    ><?= $label ?></a>
  <?php endforeach; ?>
</div>

<div class="table-card">
  <div class="table-wrapper">
    <?php if (empty($bookings)): ?>
      <div class="empty-state">
        <div class="icon">📭</div>
        <p>No <?= $filter === 'all' ? '' : $filter ?> bookings found.
          <a href="book.php">Book a court now →</a>
        </p>
      </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Ref</th><th>Court</th><th>Date</th>
          <th>Time</th><th>Duration</th><th>Total</th>
          <th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($bookings as $r): ?>
        <?php
          $isPast   = strtotime($r['booking_date'].' '.$r['start_time']) < time();
          $canCancel= $r['status']==='confirmed' && !$isPast;
        ?>
        <tr>
          <td style="font-size:.78rem;color:var(--text3);font-weight:600;">#<?= str_pad($r['id'],5,'0',STR_PAD_LEFT) ?></td>
          <td>
            <div class="renter-name"><?= htmlspecialchars($r['court_name']) ?></div>
            <span class="badge badge-<?= strtolower($r['court_type']) ?>"><?= $r['court_type'] ?></span>
          </td>
          <td>
            <?= date('d M Y', strtotime($r['booking_date'])) ?>
            <br><small style="color:var(--text3);"><?= date('D', strtotime($r['booking_date'])) ?></small>
          </td>
          <td><?= substr($r['start_time'],0,5) ?> – <?= substr($r['end_time'],0,5) ?></td>
          <td><?= number_format($r['duration_hours'],1) ?>h</td>
          <td>Rp <?= number_format($r['total_price'],0,',','.') ?></td>
          <td>
            <?php if ($r['status']==='confirmed' && $isPast): ?>
              <span class="badge" style="background:#f0fdf4;color:#15803d;">Completed</span>
            <?php else: ?>
              <span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($canCancel): ?>
              <a href="my_bookings.php?cancel=<?= $r['id'] ?>" class="btn btn-danger btn-sm"
                 data-confirm="Cancel booking #<?= str_pad($r['id'],5,'0',STR_PAD_LEFT) ?>?">Cancel</a>
            <?php else: ?>
              <span style="font-size:.75rem;color:var(--text4);">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php for($pg=1;$pg<=$totalPages;$pg++): ?>
    <a href="?filter=<?= $filter ?>&p=<?= $pg ?>"
       class="btn <?= $pg===$page?'btn-primary':'btn-secondary' ?> btn-sm"><?= $pg ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<!-- Info box -->
<div style="margin-top:1.5rem;background:var(--blue-light);border:1px solid #bfdbfe;border-radius:var(--radius);padding:1rem 1.25rem;font-size:.85rem;color:#1d4ed8;">
  <strong>📋 Cancellation Policy:</strong> Bookings can be cancelled any time before the session starts.
  Payment is collected at the venue — no charge for cancellations.
</div>

<?php include 'includes/footer.php'; ?>
