<?php
// includes/auth.php — Authentication & Authorization Helpers
if (session_status() === PHP_SESSION_NONE) session_start();

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function isAdmin(): bool {
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

function isCustomer(): bool {
    return ($_SESSION['user_role'] ?? '') === 'customer';
}

function currentUser(): array {
    return [
        'id'    => $_SESSION['user_id']   ?? null,
        'name'  => $_SESSION['user_name'] ?? '',
        'role'  => $_SESSION['user_role'] ?? '',
        'email' => $_SESSION['user_email']?? '',
    ];
}

/** Redirect to login if not logged in as admin */
function requireAdmin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    if (!isAdmin()) {
        header('Location: book.php');
        exit;
    }
}

/** Redirect to login if not logged in as customer */
function requireCustomer(): void {
    if (!isLoggedIn()) {
        header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    if (!isCustomer()) {
        header('Location: index.php');
        exit;
    }
}

/** Redirect to login if not logged in (any role) */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/** Login: verify credentials and set session. Returns true on success. */
function attemptLogin(PDO $db, string $email, string $password): bool {
    $stmt = $db->prepare("SELECT * FROM users WHERE email = :e LIMIT 1");
    $stmt->execute([':e' => $email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['user_email'] = $user['email'];
        return true;
    }
    return false;
}
