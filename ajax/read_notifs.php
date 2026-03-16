<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/config/database.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (isLoggedIn()) {
    try {
        $stmt = db()->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([currentUser()['id']]);
    } catch (Exception $e) {}
}

$ref = $_SERVER['HTTP_REFERER'] ?? '/TUGASPAKDANIL/ABSENSITALENTA/index.php';
header("Location: $ref");
exit;
