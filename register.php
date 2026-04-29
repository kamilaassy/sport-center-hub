<?php
// register.php — Customer self-registration
require_once 'config/database.php';
require_once 'includes/auth.php';

if (isLoggedIn()) { header('Location: book.php'); exit; }

$page_title = 'Create Account — Sport Center Hub';
$db = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $phone    = trim($_POST['phone']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');

    if (!$name)                               $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($password) < 6)                $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm)               $errors[] = 'Passwords do not match.';

    // Check duplicate email
    if (empty($errors)) {
        $chk = $db->prepare("SELECT id FROM users WHERE email=:e");
        $chk->execute([':e'=>$email]);
        if ($chk->fetch()) $errors[] = 'An account with this email already exists.';
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ins  = $db->prepare("INSERT INTO users (name,email,password,role,phone) VALUES (:n,:e,:p,'customer',:ph)");
        $ins->execute([':n'=>$name,':e'=>$email,':p'=>$hash,':ph'=>$phone]);
        // Auto-login
        attemptLogin($db, $email, $password);
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Account created! Welcome, '.$name.'!'];
        header('Location: book.php');
        exit;
    }
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
<body>
<nav class="navbar">
  <a href="book.php" class="nav-brand">
    <div class="brand-icon">⬡</div>
    <span>Sport Center <strong>Hub</strong></span>
  </a>
  <ul class="nav-links">
    <li><a href="login.php">Login</a></li>
  </ul>
</nav>

<main class="container">
<div class="auth-wrap">
  <div class="auth-card" style="max-width:680px;">
    <div class="auth-logo">
      <div class="brand-icon">⬡</div>
      Sport Center Hub
    </div>

    <div class="auth-title">Create Account</div>
    <p class="auth-sub">Join Sport Center Hub to book courts online</p>

    <?php if ($errors): ?>
      <div class="flash flash--error" style="margin-bottom:1.25rem;border-radius:var(--radius-sm);flex-direction:column;align-items:flex-start;gap:.3rem;">
        <?php foreach ($errors as $e): ?><span>⚠ <?= htmlspecialchars($e) ?></span><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="register.php">
      <div class="form-grid" style="grid-template-columns:1fr;gap:.9rem;">
        <div class="form-group">
          <label>Full Name *</label>
          <input type="text" name="name" value="<?= htmlspecialchars($_POST['name']??'') ?>"
                 placeholder="e.g. John Doe" required autofocus/>
        </div>
        <div class="form-group">
          <label>Email Address *</label>
          <input type="email" name="email" value="<?= htmlspecialchars($_POST['email']??'') ?>"
                 placeholder="you@example.com" required/>
        </div>
        <div class="form-group">
          <label>Phone Number</label>
          <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone']??'') ?>"
                 placeholder="08xxxxxxxxxx"/>
        </div>
        <div class="form-group">
          <label>Password *</label>
          <input type="password" name="password" placeholder="Min. 6 characters" required/>
        </div>
        <div class="form-group">
          <label>Confirm Password *</label>
          <input type="password" name="confirm" placeholder="Repeat password" required/>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-lg" style="width:100%;margin-top:1.5rem;">
        Create Account →
      </button>
    </form>

    <div class="auth-footer" style="margin-top:1.25rem;">
      Already have an account? <a href="login.php">Log in here →</a>
    </div>
  </div>
</div>
</main>

<footer class="footer">
  <div class="footer-inner">
    <div class="footer-brand">
      <div class="brand-icon" style="width:26px;height:26px;font-size:.8rem;">⬡</div>
      Sport Center Hub
    </div>
    <span>© <?= date('Y') ?> Sport Center Hub</span>
  </div>
</footer>
</body>
</html>
