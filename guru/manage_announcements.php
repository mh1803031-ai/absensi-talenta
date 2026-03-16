<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('guru');
// Redirect to shared admin/manage_announcements.php which handles both admin and guru
header('Location: /TUGASPAKDANIL/ABSENSITALENTA/admin/manage_announcements.php');
exit;
