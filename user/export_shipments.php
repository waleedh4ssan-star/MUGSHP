<?php
require_once __DIR__ . '/../config/config.php';
requireWebLogin();

$pdo = getDBConnection();
$userId = currentUserId();

$statusLabels = [
    'pending' => 'قيد الانتظار', 'processing' => 'قيد المعالجة', 'picked_up' => 'تم الاستلام',
    'in_transit' => 'في الطريق', 'out_for_delivery' => 'خارج للتوصيل',
    'delivered' => 'تم التسليم', 'cancelled' => 'ملغاة',
];

$filterStatus = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$sql = "SELECT * FROM shipments WHERE user_id = ?";
$params = [$userId];
if ($filterStatus && array_key_exists($filterStatus, $statusLabels)) {
    $sql .= " AND status = ?";
    $params[] = $filterStatus;
}
if ($search !== '') {
    $sql .= " AND (tracking_number LIKE ? OR receiver_phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($dateFrom !== '') {
    $sql .= " AND DATE(created_at) >= ?";
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $sql .= " AND DATE(created_at) <= ?";
    $params[] = $dateTo;
}
$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="shipments_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM حتى يفتح إكسل الملف بترميز UTF-8 صحيح
fputcsv($out, ['رقم التتبع', 'اسم المستلم', 'جوال المستلم', 'عنوان المستلم', 'الوزن (كجم)', 'التكلفة', 'مبلغ COD', 'الحالة', 'تاريخ الإنشاء']);

foreach ($rows as $r) {
    fputcsv($out, [
        $r['tracking_number'], $r['receiver_name'], $r['receiver_phone'], $r['receiver_address'],
        $r['weight_kg'], $r['cost'], $r['cod_amount'], $statusLabels[$r['status']] ?? $r['status'], $r['created_at'],
    ]);
}
fclose($out);
exit;
