<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (currentRole() !== 'siswa') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$studentId = currentUser()['id'];
$journalId = (int)($_POST['journal_id'] ?? 0);

if (!$journalId) {
    echo json_encode(['success' => false, 'message' => 'ID Jurnal tidak valid']);
    exit;
}

// Ensure journal belongs to student and is still pending
$stmt = db()->prepare("SELECT * FROM journals WHERE id = ? AND student_id = ?");
$stmt->execute([$journalId, $studentId]);
$journal = $stmt->fetch();

if (!$journal) {
    echo json_encode(['success' => false, 'message' => 'Jurnal tidak ditemukan.']);
    exit;
}
if ($journal['status'] !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'Hanya jurnal status Pending yang bisa dihapus.']);
    exit;
}

// Unlink media files
$mediaStmt = db()->prepare("SELECT file_name FROM journal_media WHERE journal_id = ?");
$mediaStmt->execute([$journalId]);
$medias = $mediaStmt->fetchAll();
foreach ($medias as $m) {
    $path = __DIR__ . '/../uploads/media/' . $m['file_name'];
    if (file_exists($path)) @unlink($path);
}

// Unlink task file
if ($journal['task_file']) {
    $taskPath = __DIR__ . '/../uploads/tasks/' . $journal['task_file'];
    if (file_exists($taskPath)) @unlink($taskPath);
}

// DB deletion (cascade will handle journal_media records if set, otherwise they delete automatically but we do it cleanly via FK IF properly set up)
db()->prepare("DELETE FROM journals WHERE id = ?")->execute([$journalId]);

$_SESSION['flash'] = ['type' => 'success', 'message' => 'Jurnal berhasil dihapus.'];
echo json_encode(['success' => true]);
