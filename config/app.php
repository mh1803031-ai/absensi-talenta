<?php
/**
 * ═══════════════════════════════════════════════
 *  config/app.php  —  Central Application Config
 * ═══════════════════════════════════════════════
 *
 * ✅ CARA PENGGUNAAN:
 *
 * LOCALHOST (sekarang):
 *   Tidak perlu mengubah apa-apa. Script otomatis mendeteksi localhost.
 *
 * PRODUCTION SERVER (domain sendiri):
 *   Ubah nilai-nilai di bagian PRODUCTION CONFIG di bawah ini:
 *   1. APP_BASE_PATH → Jika website ada di root domain (misal: domainanda.com),
 *      kosongkan: define('APP_BASE_PATH', '');
 *      Jika ada di subdirektori (misal: domainanda.com/aplikasi),
 *      isi: define('APP_BASE_PATH', '/aplikasi');
 *   2. Isi DB_USER, DB_PASS, DB_NAME sesuai hosting Anda (lihat cPanel).
 */

// ═══════════════════════════════════════════════
//  ENVIRONMENT DETECTION (Otomatis)
// ═══════════════════════════════════════════════
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocalhost = in_array($host, ['localhost', '127.0.0.1', '::1'])
               || str_ends_with($host, '.local')
               || str_ends_with($host, '.test');

// ═══════════════════════════════════════════════
//  LOCALHOST CONFIG
// ═══════════════════════════════════════════════
if ($isLocalhost) {
    define('APP_ENV',       'local');
    define('APP_BASE_PATH', '/TUGASPAKDANIL/ABSENSITALENTA');
    define('DB_HOST',       'localhost');
    define('DB_USER',       'root');
    define('DB_PASS',       '');
    define('DB_NAME',       'talenta_db');
}

// ═══════════════════════════════════════════════
//  PRODUCTION CONFIG  ← UBAH INI SAAT DEPLOY!
// ═══════════════════════════════════════════════
else {
    define('APP_ENV',       'production');
    define('APP_BASE_PATH', '');            // ← Kosongkan jika di root domain
    define('DB_HOST',       'localhost');   // ← Biasanya tetap localhost di shared hosting
    define('DB_USER',       'YOUR_DB_USER');  // ← GANTI dengan user DB dari cPanel
    define('DB_PASS',       'YOUR_DB_PASS');  // ← GANTI dengan password DB
    define('DB_NAME',       'YOUR_DB_NAME');  // ← GANTI dengan nama database
}
