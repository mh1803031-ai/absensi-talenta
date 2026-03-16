<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode([]); exit;
}

$journalId = (int)($_GET['journal_id'] ?? 0);
if (!$journalId) {
    echo json_encode([]); exit;
}

// If student, only allow own journal
if (currentRole() === 'siswa') {
    $check = db()->prepare("SELECT id FROM journals WHERE id = ? AND student_id = ?");
    $check->execute([$journalId, currentUser()['id']]);
    if (!$check->fetch()) {
        echo json_encode([]); exit;
    }
}

$stmt = db()->prepare(
    "SELECT id, file_name, file_type, original_name FROM journal_media WHERE journal_id = ? ORDER BY uploaded_at ASC"
);
$stmt->execute([$journalId]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
