<?php
// login.php — Unified login: role auto-detected from email
require_once 'config/database.php';
require_once 'includes/auth.php';

// Already logged in
if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? 'index.php' : 'book.php'));
    exit;
}

$page_title = 'Login — Sport Center Hub';
$db       = getDB();
$error    = '';
$redirect = $_GET['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } elseif (attemptLogin($db, $email, $password)) {
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Welcome back, ' . currentUser()['name'] . '!'];
        $dest = $redirect ?: (isAdmin() ? 'index.php' : 'book.php');
        header('Location: ' . $dest);
        exit;
    } else {
        $error = 'Incorrect email or password. Please try again.';
    }
}

// Flash from register
$flash_msg = '';
if (!empty($_SESSION['flash'])) {
    $flash_msg = $_SESSION['flash'];
    unset($_SESSION['flash']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= $page_title ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="assets/style.css"/>
</head>
<body style="background:var(--bg);">

<nav class="navbar">
  <a href="book.php" class="nav-brand">
    <div class="brand-icon">&#x2B21;</div>
    <span>Sport Center <strong>Hub</strong></span>
  </a>
  <ul class="nav-links">
    <li><a href="book.php">Book a Court</a></li>
  </ul>
</nav>

<main class="container">
<div class="auth-wrap">
  <div class="auth-card">

    <div class="auth-logo">
      <div class="brand-icon">&#x2B21;</div>
      Sport Center Hub
    </div>

    <div class="auth-title">Welcome Back!</div>
    <p class="auth-sub">Log in with your email. Admin &amp; customer roles are detected automatically.</p>

    <?php if ($flash_msg): ?>
      <div class="flash flash--<?= htmlspecialchars($flash_msg['type']) ?>" style="margin-bottom:1.25rem;border-radius:var(--radius-sm);">
        <span><?= htmlspecialchars($flash_msg['msg']) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="flash flash--error" style="margin-bottom:1.25rem;border-radius:var(--radius-sm);">
        <span>&#9888; <?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="login.php<?= $redirect ? '?redirect='.urlencode($redirect) : '' ?>">
      <div class="form-group" style="margin-bottom:1rem;">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               placeholder="you@example.com" required autofocus/>
      </div>
      <div class="form-group" style="margin-bottom:1.5rem;">
        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               placeholder="Enter your password" required/>
      </div>
      <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">
        &#8594; Log In
      </button>
    </form>

    <div class="auth-divider">or</div>
    <div class="auth-footer">
      Don't have an account?
      <a href="register.php">Create one for free &#8594;</a>
    </div>

    <div style="margin-top:1.5rem;padding:.85rem 1rem;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);font-size:.78rem;color:var(--text3);">
      <strong style="color:var(--text2);">Demo Credentials</strong><br>
      Admin &nbsp;: <code>admin@sporthub.com</code> / <code>password</code><br>
      Customer: <code>john@example.com</code> &nbsp;&nbsp;/ <code>password</code>
    </div>

  </div>
</div>
</main>

<footer class="footer">
  <div class="footer-inner">
    <div class="footer-brand">
      <div class="brand-icon" style="width:26px;height:26px;font-size:.8rem;">&#x2B21;</div>
      Sport Center Hub
    </div>
    <span>&copy; <?= date('Y') ?> Sport Center Hub &middot; All rights reserved</span>
  </div>
</footer>

</body>
</html>
