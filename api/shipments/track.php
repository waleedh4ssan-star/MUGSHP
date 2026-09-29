<?php
define('IS_API', true);
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'طريقة الطلب غير مسموحة'], 405);
}

$trackingNumber = trim($_GET['tracking_number'] ?? '');
if ($trackingNumber === '') {
    jsonResponse(['success' => false, 'message' => 'رقم التتبع مطلوب'], 422);
}

$pdo = getDBConnection();
$stmt = $pdo->prepare(
    "SELECT tracking_number, sender_name, receiver_name, receiver_address, status, weight_kg, created_at, updated_at
     FROM shipments WHERE tracking_number = ? LIMIT 1"
);
$stmt->execute([$trackingNumber]);
$shipment = $stmt->fetch();

if (!$shipment) {
    jsonResponse(['success' => false, 'message' => 'لا توجد شحنة بهذا الرقم'], 404);
}

$historyStmt = $pdo->prepare(
    "SELECT status, note, created_at FROM shipment_status_history
     WHERE shipment_id = (SELECT id FROM shipments WHERE tracking_number = ?)
     ORDER BY created_at ASC"
);
$historyStmt->execute([$trackingNumber]);

jsonResponse([
    'success' => true,
    'data' => [
        'shipment' => $shipment,
        'history'  => $historyStmt->fetchAll(),
    ],
]);
