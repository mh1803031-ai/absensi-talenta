<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('guru');
// Guru's manage_students is same as admin manage_users (with role restriction in that file)
header('Location: /TUGASPAKDANIL/ABSENSITALENTA/admin/manage_users.php');
exit;
