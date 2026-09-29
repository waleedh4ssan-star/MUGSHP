<?php
/**
 * إعدادات الاتصال بقاعدة البيانات
 * منصة Middleware لإدارة وتتبع الشحنات
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'qobpoyte_shipment_db');
define('DB_USER', 'qobpoyte_shipment_db');
define('DB_PASS', 'Ayan@2018**');
define('DB_CHARSET', 'utf8mb4');

/**
 * إرجاع اتصال PDO واحد (Singleton) مع استخدام Prepared Statements دائماً
 */
function getDBConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // لا نطبع تفاصيل الاتصال أو كلمة المرور أبداً في رسالة الخطأ
            error_log('DB Connection Error: ' . $e->getMessage());
            http_response_code(500);
            if (defined('IS_API') && IS_API) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'فشل الاتصال بقاعدة البيانات'], JSON_UNESCAPED_UNICODE);
            } else {
                echo 'حدث خطأ في الاتصال بقاعدة البيانات. حاول لاحقاً.';
            }
            exit;
        }
    }

    return $pdo;
}
