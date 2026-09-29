<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('view_shipments');

$pdo = getDBConnection();

$statusLabels = [
    'pending' => 'قيد الانتظار', 'processing' => 'قيد المعالجة', 'picked_up' => 'تم الاستلام',
    'in_transit' => 'في الطريق', 'out_for_delivery' => 'خارج للتوصيل',
    'delivered' => 'تم التسليم', 'cancelled' => 'ملغاة',
];

$filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$sql = "SELECT s.*, u.name AS customer_name FROM shipments s JOIN users u ON u.id = s.user_id WHERE 1=1";
$params = [];
if ($filter && array_key_exists($filter, $statusLabels)) {
    $sql .= " AND s.status = ?";
    $params[] = $filter;
}
if ($search !== '') {
    $sql .= " AND (s.tracking_number LIKE ? OR s.receiver_phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($dateFrom !== '') {
    $sql .= " AND DATE(s.created_at) >= ?";
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $sql .= " AND DATE(s.created_at) <= ?";
    $params[] = $dateTo;
}
$sql .= " ORDER BY s.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$canSeeFullPhone = isSuperAdmin() || hasPermission('view_full_phone');
$canSeeFinancials = hasPermission('view_financials');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="all_shipments_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

$headerRow = ['رقم التتبع', 'العميل', 'اسم المستلم', 'جوال المستلم', 'عنوان المستلم', 'الوزن (كجم)', 'سعر البيع', 'مبلغ COD', 'الحالة', 'تاريخ الإنشاء'];
if ($canSeeFinancials) {
    $headerRow[] = 'التكلفة الفعلية';
    $headerRow[] = 'الربح';
}
fputcsv($out, $headerRow);

foreach ($rows as $r) {
    $phone = $canSeeFullPhone ? $r['receiver_phone'] : maskPhone($r['receiver_phone']);
    $row = [
        $r['tracking_number'], $r['customer_name'], $r['receiver_name'], $phone, $r['receiver_address'],
        $r['weight_kg'], $r['cost'], $r['cod_amount'], $statusLabels[$r['status']] ?? $r['status'], $r['created_at'],
    ];
    if ($canSeeFinancials) {
        $row[] = $r['actual_cost'];
        $row[] = round((float)$r['cost'] - (float)$r['actual_cost'], 2);
    }
    fputcsv($out, $row);
}
fclose($out);
exit;
