<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$base = '/TUGASPAKDANIL/ABSENSITALENTA';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $base/login.php");
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if (!$username || !$password) {
    header("Location: $base/login.php?error=invalid");
    exit;
}

$stmt = db()->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !verifyPassword($password, $user['password'])) {
    header("Location: $base/login.php?error=invalid");
    exit;
}

if (!$user['is_active']) {
    header("Location: $base/login.php?error=inactive");
    exit;
}

// Create session
$_SESSION['user_id']  = $user['id'];
$_SESSION['role']     = $user['role'];
$_SESSION['user']     = [
    'id'       => $user['id'],
    'name'     => $user['name'],
    'username' => $user['username'],
    'role'     => $user['role'],
    'photo'    => $user['photo'],
    'class_id' => $user['class_id'],
];

redirectByRole();
