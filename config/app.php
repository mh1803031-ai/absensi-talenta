<?php
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocalhost = in_array($host, ['localhost', '127.0.0.1', '::1'])
               || str_ends_with($host, '.local')
               || str_ends_with($host, '.test');

if ($isLocalhost) {
    define('APP_ENV',       'local');
    define('APP_BASE_PATH', '/TUGASPAKDANIL/ABSENSITALENTA');
    define('DB_HOST',       'localhost');
    define('DB_USER',       'root');
    define('DB_PASS',       '');
    define('DB_NAME',       'talenta_db');
} else {
    define('APP_ENV',       'production');
    define('APP_BASE_PATH', '');
    define('DB_HOST',       'localhost');
    define('DB_USER',       'YOUR_DB_USER');
    define('DB_PASS',       'YOUR_DB_PASS');
    define('DB_NAME',       'YOUR_DB_NAME');
}
