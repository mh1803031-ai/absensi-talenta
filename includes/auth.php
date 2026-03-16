<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load central app config for APP_BASE_PATH
$_appConfigPath = __DIR__ . '/../config/app.php';
if (file_exists($_appConfigPath) && !defined('APP_BASE_PATH')) {
    require_once $_appConfigPath;
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return $_SESSION['user'] ?? null;
}

function currentRole(): ?string {
    return $_SESSION['role'] ?? null;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_BASE_PATH . '/login.php');
        exit;
    }
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!in_array(currentRole(), $roles)) {
        header('Location: ' . APP_BASE_PATH . '/login.php?error=unauthorized');
        exit;
    }
}

function isAdmin(): bool     { return currentRole() === 'admin'; }
function isGuru(): bool      { return currentRole() === 'guru'; }
function isInstruktur(): bool{ return currentRole() === 'instruktur'; }
function isSiswa(): bool     { return currentRole() === 'siswa'; }
function isStaff(): bool     { return in_array(currentRole(), ['admin', 'guru', 'instruktur']); }
function isAdminOrGuru(): bool { return in_array(currentRole(), ['admin', 'guru']); }

function redirectByRole(): void {
    $base = APP_BASE_PATH;
    switch (currentRole()) {
        case 'admin':       header("Location: $base/admin/dashboard.php"); break;
        case 'guru':        header("Location: $base/guru/dashboard.php"); break;
        case 'instruktur':  header("Location: $base/instruktur/dashboard.php"); break;
        case 'siswa':       header("Location: $base/siswa/dashboard.php"); break;
        default:            header("Location: $base/login.php"); break;
    }
    exit;
}

