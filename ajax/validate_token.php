<?php
ob_start(); // Prevent whitespace/notice corruption
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('siswa');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Metode tidak valid.']); exit;
}

$token     = strtoupper(trim($_POST['token'] ?? ''));
$studentId = currentUser()['id'];

if (empty($token)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Token tidak boleh kosong.']); exit;
}

if (hasAttendedToday($studentId)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Anda sudah melakukan absensi hari ini.']); exit;
}

$result = validateToken($token, $studentId);
ob_clean();
echo json_encode($result);