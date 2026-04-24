<?php
// courts.php — Admin Court Management (CRUD)
require_once 'includes/auth.php';
require_once 'config/database.php';
requireAdmin();

$db     = getDB();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// ── POST: Save / Update ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']          ?? '');
    $type    = trim($_POST['type']          ?? '');
    $price   = (float)($_POST['price_per_hour'] ?? 0);
    $desc    = trim($_POST['description']   ?? '');
    $status  = trim($_POST['status']        ?? 'active');
    $editId  = (int)($_POST['edit_id']      ?? 0);
    $errors  = [];

    if (!$name)  $errors[] = 'Court name is required.';
    if (!in_array($type,['Padel','Badminton','Tennis'])) $errors[] = 'Invalid court type.';
    if ($price <= 0) $errors[] = 'Price must be greater than 0.';

    if (empty($errors)) {
        if ($editId) {
            $db->prepare("UPDATE courts SET name=:n,type=:t,price_per_hour=:p,description=:d,status=:s WHERE id=:id")
               ->execute([':n'=>$name,':t'=>$type,':p'=>$price,':d'=>$desc,':s'=>$status,':id'=>$editId]);
            $_SESSION['flash'] = ['type'=>'success','msg'=>'Court updated successfully!'];
        } else {
            $db->prepare("INSERT INTO courts (name,type,price_per_hour,description,status) VALUES (:n,:t,:p,:d,:s)")
               ->execute([':n'=>$name,':t'=>$type,':p'=>$price,':d'=>$desc,':s'=>$status]);
            $_SESSION['flash'] = ['type'=>'success','msg'=>'Court added successfully!'];
        }
        header('Location: courts.php'); exit;
    }
}

// ── Delete ──
if ($action==='delete' && $id) {
    $cnt = $db->prepare("SELECT COUNT(*) FROM reservations WHERE court_id=:id AND status='confirmed'");
    $cnt->execute([':id'=>$id]);
    if ((int)$cnt->fetchColumn() > 0) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Cannot delete court with active reservations.'];
    } else {
        $db->prepare("DELETE FROM courts WHERE id=:id")->execute([':id'=>$id]);
        $_SESSION['flash'] = ['type'=>'info','msg'=>'Court deleted.'];
    }
    header('Location: courts.php'); exit;
}

// ── Load edit record ──
$editCourt = null;
if ($action==='edit' && $id) {
    $s = $db->prepare("SELECT * FROM courts WHERE id=:id");
    $s->execute([':id'=>$id]);
    $editCourt = $s->fetch();
    if (!$editCourt) { header('Location: courts.php'); exit; }
}

// ── List: with booking stats ──
$courts = $db->query("
    SELECT c.*,
      (SELECT COUNT(*) FROM reservations r WHERE r.court_id=c.id AND r.status='confirmed') AS total_bookings,
      (SELECT COUNT(*) FROM reservations r WHERE r.court_id=c.id AND r.status='confirmed' AND r.booking_date=CURDATE()) AS today_bookings
    FROM courts c ORDER BY c.type, c.name
")->fetchAll();

$isForm     = in_array($action,['new','edit']);
$page_title = ($isForm ? ($editCourt?'Edit':'Add').' Court' : 'Courts').' — Sport Center Hub';
include 'includes/header.php';
?>

<?php if ($isForm): ?>
<!-- ══ FORM ══ -->
<div class="page-header">
  <div>
    <h1><?= $editCourt ? 'Edit <span>Court</span>' : 'Add <span>Court</span>' ?></h1>
    <p><?= $editCourt ? 'Update court details' : 'Register a new court to the system' ?></p>
  </div>
  <a href="courts.php" class="btn btn-secondary">← Back</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="flash flash--error" style="margin-bottom:1.5rem;border-radius:var(--radius-sm);flex-direction:column;align-items:flex-start;gap:.3rem;">
    <?php foreach($errors as $e): ?><span>⚠ <?= htmlspecialchars($e) ?></span><?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="form-card">
  <form method="POST" action="courts.php?action=<?= $editCourt?'edit':'new' ?>">
    <?php if ($editCourt): ?><input type="hidden" name="edit_id" value="<?= $editCourt['id'] ?>"><?php endif; ?>
    <div class="form-grid">
      <div class="form-group">
        <label>Court Name *</label>
        <input type="text" name="name"
               value="<?= htmlspecialchars($_POST['name'] ?? $editCourt['name'] ?? '') ?>"
               placeholder="e.g. Padel Court A" required/>
      </div>
      <div class="form-group">
        <label>Court Type *</label>
        <select name="type" required>
          <option value="">— Select Type —</option>
          <?php foreach(['Padel','Badminton','Tennis'] as $t): ?>
            <option value="<?= $t ?>" <?= (($_POST['type']??$editCourt['type']??'')===$t)?'selected':'' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Price per Hour (Rp) *</label>
        <input type="number" name="price_per_hour"
               value="<?= htmlspecialchars($_POST['price_per_hour'] ?? $editCourt['price_per_hour'] ?? '') ?>"
               placeholder="e.g. 350000" min="0" step="5000" required/>
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="status">
          <option value="active"   <?= (($_POST['status']??$editCourt['status']??'active')==='active')  ?'selected':'' ?>>Active</option>
          <option value="inactive" <?= (($_POST['status']??$editCourt['status']??'active')==='inactive')?'selected':'' ?>>Inactive</option>
        </select>
      </div>
      <div class="form-group full">
        <label>Description</label>
        <textarea name="description" placeholder="Court features, facilities, notes…"><?= htmlspecialchars($_POST['description']??$editCourt['description']??'') ?></textarea>
      </div>
    </div>
    <div style="display:flex;gap:.75rem;margin-top:1.75rem;">
      <button type="submit" class="btn btn-primary btn-lg">
        <?= $editCourt ? '💾 Save Changes' : '+ Add Court' ?>
      </button>
      <a href="courts.php" class="btn btn-secondary btn-lg">Cancel</a>
    </div>
  </form>
</div>

<?php else: ?>
<!-- ══ LIST ══ -->
<div class="page-header">
  <div>
    <h1>Manage <span>Courts</span></h1>
    <p><?= count($courts) ?> court<?= count($courts)!=1?'s':'' ?> registered</p>
  </div>
  <a href="courts.php?action=new" class="btn btn-primary">+ Add Court</a>
</div>

<?php $types=['Padel'=>0,'Badminton'=>0,'Tennis'=>0]; foreach($courts as $c) $types[$c['type']]++; ?>
<div class="stats-grid" style="margin-bottom:1.5rem;">
  <?php foreach($types as $t=>$n): ?>
  <div class="stat-card <?= $t==='Padel'?'green':($t==='Badminton'?'blue':'orange') ?>">
    <div class="stat-card-icon"><?= $t==='Padel'?'🎾':($t==='Badminton'?'🏸':'🎾') ?></div>
    <div class="stat-label"><?= $t ?></div>
    <div class="stat-value"><?= $n ?></div>
    <div class="stat-sub">court<?= $n!=1?'s':'' ?></div>
  </div>
  <?php endforeach; ?>
</div>

<div class="courts-grid">
  <?php foreach ($courts as $c): ?>
  <div class="court-card" data-type="<?= $c['type'] ?>">
    <div class="court-type-strip <?= strtolower($c['type']) ?>"></div>
    <div class="court-card-top">
      <div>
        <span class="badge badge-<?= strtolower($c['type']) ?>"><?= $c['type'] ?></span>
        <div class="court-name"><?= htmlspecialchars($c['name']) ?></div>
      </div>
      <div class="court-price" style="text-align:right;">
        <div class="amount">Rp <?= number_format($c['price_per_hour'],0,',','.') ?></div>
        <small>/hour</small>
        <br><span class="badge badge-<?= $c['status'] ?>" style="margin-top:.3rem;"><?= ucfirst($c['status']) ?></span>
      </div>
    </div>
    <?php if ($c['description']): ?>
      <p class="court-desc"><?= htmlspecialchars($c['description']) ?></p>
    <?php endif; ?>
    <div class="court-meta">
      <span>📅 Today: <strong><?= $c['today_bookings'] ?></strong></span>
      <span>📊 Total: <strong><?= $c['total_bookings'] ?></strong></span>
    </div>
    <div class="court-actions">
      <a href="courts.php?action=edit&id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
      <a href="schedule.php" class="btn btn-secondary btn-sm">Schedule</a>
      <?php if ($c['total_bookings']==0): ?>
        <a href="courts.php?action=delete&id=<?= $c['id'] ?>" class="btn btn-danger btn-sm"
           data-confirm="Delete court '<?= htmlspecialchars($c['name']) ?>'?">Delete</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php if (empty($courts)): ?>
  <div class="empty-state">
    <div class="icon">🏟</div>
    <p>No courts yet. <a href="courts.php?action=new">Add the first one →</a></p>
  </div>
<?php endif; ?>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
