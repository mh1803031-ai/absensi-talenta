<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
        header('Location: /TUGASPAKDANIL/ABSENSITALENTA/login.php');
        exit;
    }
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!in_array(currentRole(), $roles)) {
        header('Location: /TUGASPAKDANIL/ABSENSITALENTA/login.php?error=unauthorized');
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
    switch (currentRole()) {
        case 'admin':       header('Location: /TUGASPAKDANIL/ABSENSITALENTA/admin/dashboard.php'); break;
        case 'guru':        header('Location: /TUGASPAKDANIL/ABSENSITALENTA/guru/dashboard.php'); break;
        case 'instruktur':  header('Location: /TUGASPAKDANIL/ABSENSITALENTA/instruktur/dashboard.php'); break;
        case 'siswa':       header('Location: /TUGASPAKDANIL/ABSENSITALENTA/siswa/dashboard.php'); break;
        default:            header('Location: /TUGASPAKDANIL/ABSENSITALENTA/login.php'); break;
    }
    exit;
}
