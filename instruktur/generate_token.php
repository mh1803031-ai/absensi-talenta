<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('instruktur');
header('Location: /TUGASPAKDANIL/ABSENSITALENTA/admin/generate_token.php'); exit;
