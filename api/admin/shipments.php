<?php
define('IS_API', true);
require_once __DIR__ . '/../../config/config.php';

/**
 * يقبل هذا الملف نوعين من المصادقة:
 * 1) Bearer Token لأدمن مسجّل دخول عبر النظام
 * 2) X-Api-Key لتطبيق/موقع خارجي معتمد (Middleware حقيقي)
 */
function authorizeAdminRequest(): void
{
    $hasBearer = getBearerToken() !== null;
    $hasApiKey = getApiKeyHeader() !== null;

    if ($hasApiKey) {
        requireApiKey();
        return;
    }
    if ($hasBearer) {
        requireAdminAuth();
        return;
    }
    jsonResponse(['success' => false, 'message' => 'مطلوب Bearer Token (أدمن) أو X-Api-Key'], 401);
}

authorizeAdminRequest();
$pdo = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $status = $_GET['status'] ?? null;
    if ($status) {
        $stmt = $pdo->prepare("SELECT s.*, u.name AS customer_name, u.email AS customer_email
                                FROM shipments s JOIN users u ON u.id = s.user_id
                                WHERE s.status = ? ORDER BY s.created_at DESC");
        $stmt->execute([$status]);
    } else {
        $stmt = $pdo->query("SELECT s.*, u.name AS customer_name, u.email AS customer_email
                              FROM shipments s JOIN users u ON u.id = s.user_id
                              ORDER BY s.created_at DESC");
    }
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

if ($method === 'PUT' || $method === 'PATCH') {
    $input = getJsonInput();
    $id = (int)($input['id'] ?? 0);
    $newStatus = $input['status'] ?? '';
    $note = sanitize($input['note'] ?? '');

    $validStatuses = ['pending','processing','picked_up','in_transit','out_for_delivery','delivered','cancelled'];
    if (!$id || !in_array($newStatus, $validStatuses, true)) {
        jsonResponse(['success' => false, 'message' => 'بيانات غير صحيحة (id أو status)'], 422);
    }

    $check = $pdo->prepare("SELECT * FROM shipments WHERE id = ?");
    $check->execute([$id]);
    $shipmentRow = $check->fetch();
    if (!$shipmentRow) {
        jsonResponse(['success' => false, 'message' => 'الشحنة غير موجودة'], 404);
    }

    $pdo->prepare("UPDATE shipments SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
    $pdo->prepare("INSERT INTO shipment_status_history (shipment_id, status, note) VALUES (?, ?, ?)")
        ->execute([$id, $newStatus, $note ?: 'تم تحديث الحالة']);

    if ($newStatus === 'delivered' && !$shipmentRow['cod_credited'] && (float)$shipmentRow['cod_amount'] > 0) {
        creditCodBalance(
            $pdo, (int)$shipmentRow['user_id'], (float)$shipmentRow['cod_amount'], $id,
            "اعتماد COD لشحنة #{$shipmentRow['tracking_number']}"
        );
    }

    jsonResponse(['success' => true, 'message' => 'تم تحديث حالة الشحنة']);
}

jsonResponse(['success' => false, 'message' => 'طريقة الطلب غير مسموحة'], 405);
