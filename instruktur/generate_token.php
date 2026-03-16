<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/TUGASPAKDANIL/ABSENSITALENTA/includes/auth.php';
requireRole('instruktur');
header('Location: /TUGASPAKDANIL/ABSENSITALENTA/admin/generate_token.php'); exit;
