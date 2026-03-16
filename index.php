<?php
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) { redirectByRole(); }
header('Location: /TUGASPAKDANIL/ABSENSITALENTA/login.php');
exit;