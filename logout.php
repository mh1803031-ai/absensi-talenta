<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$base = defined('APP_BASE_PATH') ? APP_BASE_PATH : '';

if (isLoggedIn() && currentRole() === 'siswa') {
    $sid = currentUser()['id'];
    if (hasAttendedToday($sid) && !hasSubmittedJournalToday($sid)) {
        setFlash('danger', 'Anda harus mengirimkan jurnal/tugas harian sebelum dapat keluar dari aplikasi.');
        header("Location: $base/siswa/dashboard.php");
        exit;
    }
}

session_destroy();
header("Location: $base/login.php?msg=logout");
exit;
