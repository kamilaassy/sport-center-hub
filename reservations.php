<?php
// reservations.php — Admin Reservation Management (CRUD)
require_once 'includes/auth.php';
require_once 'config/database.php';
requireAdmin();

$db     = getDB();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// ── POST: Save / Update ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['new','edit'])) {
    $court_id = (int)$_POST['court_id'];
    $name     = trim($_POST['renter_name']);
    $phone    = trim($_POST['renter_phone'] ?? '');
    $date     = trim($_POST['booking_date']);
    $start    = trim($_POST['start_time']);
    $end      = trim($_POST['end_time']);
    $notes    = trim($_POST['notes'] ?? '');
    $editId   = (int)($_POST['edit_id'] ?? 0);
    $errors   = [];

    if (!$court_id)           $errors[] = 'Please select a court.';
    if (!$name)               $errors[] = 'Renter name is required.';
    if (!$date)               $errors[] = 'Date is required.';
    if (!$start || !$end)     $errors[] = 'Start and end time are required.';
    if ($start && $end && $start >= $end) $errors[] = 'End time must be after start time.';
    // Block past bookings
    if ($date && $start && strtotime($date . ' ' . $start) < time()) {
        $errors[] = 'Cannot create a booking for a date/time that has already passed.';
    }

    if (empty($errors)) {
        $chk = $db->prepare("
            SELECT id, renter_name, start_time, end_time FROM reservations
            WHERE court_id=:cid AND booking_date=:dt AND status='confirmed'
              AND id!=:eid AND start_time < :et AND end_time > :st LIMIT 1
        ");
        $chk->execute([':cid'=>$court_id,':dt'=>$date,':eid'=>$editId,':et'=>$end,':st'=>$start]);
        $conflict = $chk->fetch();
        if ($conflict) {
            $errors[] = sprintf('Double booking! %s already has this court at %s–%s.',
                htmlspecialchars($conflict['renter_name']),
                substr($conflict['start_time'],0,5), substr($conflict['end_time'],0,5));
        }
    }

    if (empty($errors)) {
        $cStmt = $db->prepare("SELECT price_per_hour FROM courts WHERE id=:id");
        $cStmt->execute([':id'=>$court_id]);
        $court = $cStmt->fetch();
        $mins  = (strtotime("1970-01-01 $end") - strtotime("1970-01-01 $start")) / 60;
        $total = ($mins / 60) * ($court['price_per_hour'] ?? 0);

        if ($editId) {
            $stmt = $db->prepare("UPDATE reservations SET court_id=:c,renter_name=:n,renter_phone=:p,booking_date=:d,start_time=:s,end_time=:e,total_price=:t,notes=:no WHERE id=:id");
            $stmt->execute([':c'=>$court_id,':n'=>$name,':p'=>$phone,':d'=>$date,':s'=>$start,':e'=>$end,':t'=>$total,':no'=>$notes,':id'=>$editId]);
            $_SESSION['flash'] = ['type'=>'success','msg'=>'Reservation updated successfully!'];
        } else {
            $stmt = $db->prepare("INSERT INTO reservations (court_id,renter_name,renter_phone,booking_date,start_time,end_time,total_price,notes) VALUES (:c,:n,:p,:d,:s,:e,:t,:no)");
            $stmt->execute([':c'=>$court_id,':n'=>$name,':p'=>$phone,':d'=>$date,':s'=>$start,':e'=>$end,':t'=>$total,':no'=>$notes]);
            $_SESSION['flash'] = ['type'=>'success','msg'=>'Reservation added successfully!'];
        }
        header('Location: schedule.php?date='.$date);
        exit;
    }
}

// ── Cancel ──
if ($action==='cancel' && $id) {
    $db->prepare("UPDATE reservations SET status='cancelled' WHERE id=:id")->execute([':id'=>$id]);
    $_SESSION['flash'] = ['type'=>'info','msg'=>'Reservation cancelled.'];
    header('Location: '.($_SERVER['HTTP_REFERER'] ?? 'reservations.php'));
    exit;
}

// ── Delete ──
if ($action==='delete' && $id) {
    $db->prepare("DELETE FROM reservations WHERE id=:id")->execute([':id'=>$id]);
    $_SESSION['flash'] = ['type'=>'info','msg'=>'Reservation permanently deleted.'];
    header('Location: reservations.php');
    exit;
}

// ── Load courts + edit record ──
$courts      = $db->query("SELECT * FROM courts WHERE status='active' ORDER BY type,name")->fetchAll();
$courtPrices = array_column($courts, 'price_per_hour', 'id');

$editRes = null;
if ($action==='edit' && $id) {
    $s = $db->prepare("SELECT * FROM reservations WHERE id=:id");
    $s->execute([':id'=>$id]);
    $editRes = $s->fetch();
    if (!$editRes) { header('Location: reservations.php'); exit; }
}

$pre = [
    'court_id'    => (int)($_GET['court_id']    ?? ($editRes['court_id']    ?? 0)),
    'booking_date'=> $_GET['booking_date']       ?? ($editRes['booking_date'] ?? date('Y-m-d')),
    'start_time'  => $_GET['start_time']         ?? ($editRes['start_time']  ?? ''),
    'end_time'    => $_GET['end_time']            ?? ($editRes['end_time']    ?? ''),
    'renter_name' => $_POST['renter_name']        ?? ($editRes['renter_name'] ?? ''),
    'renter_phone'=> $_POST['renter_phone']       ?? ($editRes['renter_phone']?? ''),
    'notes'       => $_POST['notes']              ?? ($editRes['notes']       ?? ''),
];

// ── List query ──
$search     = trim($_GET['search']      ?? '');
$filterDate = trim($_GET['filter_date'] ?? '');
$filterType = trim($_GET['filter_type'] ?? '');
$listPage   = max(1,(int)($_GET['p'] ?? 1));
$perPage    = 15;
$offset     = ($listPage-1)*$perPage;

$where  = "WHERE r.status != 'deleted'";
$params = [];
if ($search)     { $where .= " AND (r.renter_name LIKE :s OR r.renter_phone LIKE :s2 OR c.name LIKE :s3)"; $params[':s']="%$search%"; $params[':s2']="%$search%"; $params[':s3']="%$search%"; }
if ($filterDate) { $where .= " AND r.booking_date=:fd"; $params[':fd']=$filterDate; }
if ($filterType) { $where .= " AND c.type=:ft";         $params[':ft']=$filterType; }

$cntStmt = $db->prepare("SELECT COUNT(*) FROM reservations r JOIN courts c ON c.id=r.court_id $where");
$cntStmt->execute($params);
$totalRows  = (int)$cntStmt->fetchColumn();
$totalPages = (int)ceil($totalRows / $perPage);

$listStmt = $db->prepare("SELECT r.*,c.name AS court_name,c.type AS court_type,TIMESTAMPDIFF(MINUTE,r.start_time,r.end_time)/60 AS duration_hours FROM reservations r JOIN courts c ON c.id=r.court_id $where ORDER BY r.booking_date DESC,r.start_time ASC LIMIT $perPage OFFSET $offset");
$listStmt->execute($params);
$reservations = $listStmt->fetchAll();

$isForm     = in_array($action,['new','edit']);
$page_title = ($isForm ? ($editRes?'Edit':'Add').' Reservation' : 'Reservations').' — Sport Center Hub';
include 'includes/header.php';
?>

<?php if ($isForm): ?>
<!-- ══ FORM ══ -->
<div class="page-header">
  <div>
    <h1><?= $editRes ? 'Edit <span>Reservation</span>' : 'Add <span>Reservation</span>' ?></h1>
    <p><?= $editRes ? 'Update the booking details below' : 'Create a new court booking' ?></p>
  </div>
  <a href="reservations.php" class="btn btn-secondary">← Back</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="flash flash--error" style="margin-bottom:1.5rem;border-radius:var(--radius-sm);flex-direction:column;align-items:flex-start;gap:.3rem;">
    <?php foreach($errors as $e): ?><span>⚠ <?= htmlspecialchars($e) ?></span><?php endforeach; ?>
  </div>
<?php endif; ?>

<script id="court-prices-data" type="application/json"><?= json_encode($courtPrices) ?></script>

<div class="form-card">
  <form id="reservation-form" method="POST"
        action="reservations.php?action=<?= $editRes?'edit':'new' ?>"
        data-edit-id="<?= $editRes['id'] ?? '' ?>">
    <?php if ($editRes): ?><input type="hidden" name="edit_id" value="<?= $editRes['id'] ?>"><?php endif; ?>

    <div class="form-section-title">Renter Information</div>
    <div class="form-grid">
      <div class="form-group">
        <label for="renter_name">Full Name *</label>
        <input type="text" id="renter_name" name="renter_name"
               value="<?= htmlspecialchars($pre['renter_name']) ?>"
               placeholder="e.g. John Doe" required/>
      </div>
      <div class="form-group">
        <label for="renter_phone">Phone Number</label>
        <input type="tel" id="renter_phone" name="renter_phone"
               value="<?= htmlspecialchars($pre['renter_phone']) ?>"
               placeholder="08xxxxxxxxxx"/>
      </div>
    </div>

    <div class="form-section-title" style="margin-top:1.5rem;">Booking Details</div>
    <div class="form-grid">
      <div class="form-group">
        <label for="court_id">Court *</label>
        <select id="court_id" name="court_id" required>
          <option value="">— Select Court —</option>
          <?php foreach($courts as $c): ?>
          <option value="<?= $c['id'] ?>" data-price="<?= $c['price_per_hour'] ?>"
                  <?= $pre['court_id']==$c['id']?'selected':'' ?>>
            <?= htmlspecialchars($c['name']) ?> (<?= $c['type'] ?>) — Rp <?= number_format($c['price_per_hour'],0,',','.') ?>/hr
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="booking_date">Date *</label>
        <input type="date" id="booking_date" name="booking_date"
               value="<?= htmlspecialchars($pre['booking_date']) ?>"
               min="<?= date('Y-m-d') ?>" required/>
      </div>
      <div class="form-group">
        <label for="start_time">Start Time *</label>
        <input type="time" id="start_time" name="start_time"
               value="<?= htmlspecialchars($pre['start_time']) ?>"
               min="06:00" max="22:00" step="1800" required/>
      </div>
      <div class="form-group">
        <label for="end_time">End Time *</label>
        <input type="time" id="end_time" name="end_time"
               value="<?= htmlspecialchars($pre['end_time']) ?>"
               min="06:00" max="23:00" step="1800" required/>
      </div>

      <div class="form-group full">
        <label>Availability Check</label>
        <div id="avail-status" class="idle">Fill in all fields above to check availability</div>
      </div>
      <div class="form-group full">
        <label>Estimated Price</label>
        <div class="price-preview">
          <span class="label">Total booking cost</span>
          <span class="value" id="price-val">Rp 0</span>
        </div>
      </div>
      <div class="form-group full">
        <label for="notes">Notes (optional)</label>
        <textarea id="notes" name="notes" placeholder="Special requests, member info, etc."><?= htmlspecialchars($pre['notes']) ?></textarea>
      </div>
    </div>

    <div style="display:flex;gap:.75rem;margin-top:1.75rem;flex-wrap:wrap;">
      <button type="submit" id="submit-btn" class="btn btn-primary btn-lg">
        <?= $editRes ? '💾 Save Changes' : '✓ Create Reservation' ?>
      </button>
      <a href="reservations.php" class="btn btn-secondary btn-lg">Cancel</a>
    </div>
  </form>
</div>

<?php else: ?>
<!-- ══ LIST ══ -->
<div class="page-header">
  <div>
    <h1>All <span>Reservations</span></h1>
    <p><?= $totalRows ?> reservation<?= $totalRows!=1?'s':'' ?> found</p>
  </div>
  <a href="reservations.php?action=new" class="btn btn-primary">+ Add Reservation</a>
</div>

<!-- Filters -->
<form method="GET" class="filter-bar">
  <div class="form-group">
    <label>Search</label>
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Name, phone, court…"/>
  </div>
  <div class="form-group">
    <label>Date</label>
    <input type="date" name="filter_date" value="<?= htmlspecialchars($filterDate) ?>"/>
  </div>
  <div class="form-group">
    <label>Type</label>
    <select name="filter_type">
      <option value="">All Types</option>
      <?php foreach(['Padel','Badminton','Tennis'] as $t): ?>
        <option value="<?= $t ?>" <?= $filterType===$t?'selected':'' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-secondary" style="align-self:flex-end;">Filter</button>
  <?php if($search||$filterDate||$filterType): ?>
    <a href="reservations.php" class="btn btn-secondary" style="align-self:flex-end;">Clear</a>
  <?php endif; ?>
</form>

<div class="table-card">
  <div class="table-wrapper">
    <?php if (empty($reservations)): ?>
      <div class="empty-state"><div class="icon">📭</div><p>No reservations found.</p></div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>#</th><th>Renter</th><th>Court</th><th>Date</th>
          <th>Time</th><th>Duration</th><th>Total</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($reservations as $i => $r): ?>
        <tr>
          <td style="color:var(--text3);font-size:.78rem;"><?= $offset+$i+1 ?></td>
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
          <td>
            <?= date('d M Y', strtotime($r['booking_date'])) ?>
            <br><small style="color:var(--text3);"><?= date('D', strtotime($r['booking_date'])) ?></small>
          </td>
          <td><?= substr($r['start_time'],0,5) ?> – <?= substr($r['end_time'],0,5) ?></td>
          <td><?= number_format($r['duration_hours'],1) ?>h</td>
          <td>Rp <?= number_format($r['total_price'],0,',','.') ?></td>
          <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
          <td style="white-space:nowrap;">
            <?php if ($r['status']==='confirmed'): ?>
              <a href="reservations.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
              <a href="reservations.php?action=cancel&id=<?= $r['id'] ?>" class="btn btn-danger btn-sm"
                 data-confirm="Cancel booking for <?= htmlspecialchars($r['renter_name']) ?>?">Cancel</a>
            <?php else: ?>
              <a href="reservations.php?action=delete&id=<?= $r['id'] ?>" class="btn btn-danger btn-sm"
                 data-confirm="Permanently delete this reservation?">Delete</a>
            <?php endif; ?>
            <a href="schedule.php?date=<?= $r['booking_date'] ?>" class="btn btn-secondary btn-sm">Schedule</a>
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
  <?php
    $qBase = http_build_query(array_filter(['search'=>$search,'filter_date'=>$filterDate,'filter_type'=>$filterType]));
    for ($pg=1; $pg<=$totalPages; $pg++):
  ?>
    <a href="?<?= $qBase ?>&p=<?= $pg ?>"
       class="btn <?= $pg===$listPage?'btn-primary':'btn-secondary' ?> btn-sm"><?= $pg ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
