<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth.php';
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$_u = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= $page_title ?? 'Sport Center Hub' ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="assets/style.css"/>
</head>
<body data-role="<?= isAdmin() ? 'admin' : (isCustomer() ? 'customer' : 'guest') ?>">

<nav class="navbar">
  <a href="<?= isAdmin() ? 'index.php' : 'book.php' ?>" class="nav-brand">
    <div class="brand-icon">&#x2B21;</div>
    <span>Sport Center <strong>Hub</strong></span>
  </a>
  <ul class="nav-links">
    <?php if (isAdmin()): ?>
      <li><a href="index.php"        class="<?= $current_page==='index'        ?'active':'' ?>">Dashboard</a></li>
      <li><a href="schedule.php"     class="<?= $current_page==='schedule'     ?'active':'' ?>">Schedule</a></li>
      <li><a href="reservations.php" class="<?= $current_page==='reservations' ?'active':'' ?>">Reservations</a></li>
      <li><a href="courts.php"       class="<?= $current_page==='courts'       ?'active':'' ?>">Courts</a></li>
    <?php elseif (isCustomer()): ?>
      <li><a href="book.php"        class="<?= $current_page==='book'        ?'active':'' ?>">Book a Court</a></li>
      <li><a href="my_bookings.php" class="<?= $current_page==='my_bookings' ?'active':'' ?>">My Bookings</a></li>
    <?php else: ?>
      <!-- Guest -->
      <li><a href="book.php" class="<?= $current_page==='book'?'active':'' ?>">Book a Court</a></li>
    <?php endif; ?>

    <?php if (isLoggedIn()): ?>
      <li class="nav-user-wrap">
        <span class="nav-user-name">&#128100; <?= htmlspecialchars($_u['name']) ?>
          <?php if (isAdmin()): ?>
            <span style="font-size:.68rem;background:#fef3c7;color:#92400e;padding:1px 6px;border-radius:20px;margin-left:4px;font-weight:700;">ADMIN</span>
          <?php endif; ?>
        </span>
        <a href="logout.php" class="nav-logout">Log Out</a>
      </li>
    <?php else: ?>
      <li><a href="login.php" class="nav-cta <?= $current_page==='login'?'active':'' ?>">Login</a></li>
    <?php endif; ?>
  </ul>
  <button class="nav-toggle" onclick="document.querySelector('.nav-links').classList.toggle('open')">&#9776;</button>
</nav>

<?php if (!empty($_SESSION['flash'])): ?>
  <div class="flash flash--<?= $_SESSION['flash']['type'] ?>">
    <span><?= htmlspecialchars($_SESSION['flash']['msg']) ?></span>
    <button onclick="this.parentElement.remove()">&times;</button>
  </div>
  <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<main class="container">
