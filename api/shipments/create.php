<?php
define('IS_API', true);
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'طريقة الطلب غير مسموحة'], 405);
}

$user = requireUserAuth();
$input = getJsonInput() ?: $_POST;

$senderName      = sanitize($input['sender_name'] ?? '');
$senderPhone     = sanitize($input['sender_phone'] ?? '');
$senderAddress   = sanitize($input['sender_address'] ?? '');
$receiverName    = sanitize($input['receiver_name'] ?? '');
$receiverPhone   = sanitize($input['receiver_phone'] ?? '');
$receiverAddress = sanitize($input['receiver_address'] ?? '');
$weight          = (float)($input['weight_kg'] ?? 0);
$description     = sanitize($input['description'] ?? '');
$codAmount       = (float)($input['cod_amount'] ?? 0);

$required = [$senderName, $senderPhone, $senderAddress, $receiverName, $receiverPhone, $receiverAddress];
foreach ($required as $field) {
    if ($field === '') {
        jsonResponse(['success' => false, 'message' => 'جميع بيانات المرسل والمستلم مطلوبة'], 422);
    }
}

$pdo = getDBConnection();

$shippingCost = calculateShippingCost($weight);
$actualCost = calculateActualCost($weight);
$balanceStmt = $pdo->prepare("SELECT shipping_balance FROM users WHERE id = ?");
$balanceStmt->execute([$user['user_id']]);
$currentBalance = (float)$balanceStmt->fetchColumn();

if ($currentBalance < $shippingCost) {
    jsonResponse([
        'success' => false,
        'message' => 'رصيد محفظة الشحن غير كافٍ',
        'data' => ['required' => $shippingCost, 'balance' => $currentBalance],
    ], 402);
}

// ضمان عدم تكرار رقم التتبع
do {
    $trackingNumber = generateTrackingNumber();
    $exists = $pdo->prepare("SELECT id FROM shipments WHERE tracking_number = ?");
    $exists->execute([$trackingNumber]);
} while ($exists->fetch());

$stmt = $pdo->prepare(
    "INSERT INTO shipments
    (tracking_number, user_id, sender_name, sender_phone, sender_address,
     receiver_name, receiver_phone, receiver_address, weight_kg, description, cost, actual_cost, cod_amount, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
);
$stmt->execute([
    $trackingNumber, $user['user_id'], $senderName, $senderPhone, $senderAddress,
    $receiverName, $receiverPhone, $receiverAddress, $weight, $description, $shippingCost, $actualCost, $codAmount,
]);
$shipmentId = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO shipment_status_history (shipment_id, status, note) VALUES (?, 'pending', 'تم إنشاء الشحنة')")
    ->execute([$shipmentId]);

deductShippingBalance($pdo, $user['user_id'], $shippingCost, $shipmentId, "أجور شحن #$trackingNumber");

jsonResponse([
    'success' => true,
    'message' => 'تم إنشاء الشحنة بنجاح',
    'data' => [
        'id'              => $shipmentId,
        'tracking_number' => $trackingNumber,
        'status'          => 'pending',
        'shipping_cost'   => $shippingCost,
        'cod_amount'      => $codAmount,
    ],
], 201);
