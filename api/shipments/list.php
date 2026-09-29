<?php
define('IS_API', true);
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'طريقة الطلب غير مسموحة'], 405);
}

$user = requireUserAuth();
$pdo = getDBConnection();

$stmt = $pdo->prepare(
    "SELECT id, tracking_number, receiver_name, receiver_phone, status, cost, created_at
     FROM shipments WHERE user_id = ? ORDER BY created_at DESC"
);
$stmt->execute([$user['user_id']]);

jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
