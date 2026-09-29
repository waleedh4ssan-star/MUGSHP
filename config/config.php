<?php
/**
 * الإعدادات العامة للتطبيق
 */

error_reporting(E_ALL);
ini_set('display_errors', '0'); // اجعلها 1 مؤقتاً أثناء تطوير محلي فقط

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_NAME', 'منصة تتبع الشحنات');
// غيّر هذا الرابط إلى رابط موقعك الفعلي بعد الرفع على Hostinger
define('APP_URL', 'https://yourdomain.com');
define('TOKEN_EXPIRY_DAYS', 30);

date_default_timezone_set('Asia/Riyadh');

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_web.php';
require_once __DIR__ . '/../includes/wallet.php';
require_once __DIR__ . '/../includes/audit.php';
