<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('view_shipments');

$pdo = getDBConnection();
$statusLabels = [
    'pending' => 'قيد الانتظار', 'processing' => 'قيد المعالجة', 'picked_up' => 'تم الاستلام',
    'in_transit' => 'في الطريق', 'out_for_delivery' => 'خارج للتوصيل',
    'delivered' => 'تم التسليم', 'cancelled' => 'ملغاة',
];

$canEdit = hasPermission('edit_shipments');
$canDelete = hasPermission('delete_shipments');
$canSeeFullPhone = isSuperAdmin() || hasPermission('view_full_phone');
$error = '';

// ---------- تحديث الحالة ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    if (!$canEdit) { header('Location: /admin/shipments.php?error=no_permission'); exit; }
    $id = (int)($_POST['shipment_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    $note = sanitize($_POST['note'] ?? '');
    $existsCheck = $pdo->prepare("SELECT * FROM shipments WHERE id = ?");
    $existsCheck->execute([$id]);
    $shipmentRow = $existsCheck->fetch();
    if ($id && $shipmentRow && array_key_exists($newStatus, $statusLabels)) {
        $pdo->prepare("UPDATE shipments SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
        $pdo->prepare("INSERT INTO shipment_status_history (shipment_id, status, note) VALUES (?, ?, ?)")
            ->execute([$id, $newStatus, $note ?: 'تحديث من الأدمن']);

        // اعتماد مبلغ COD لمحفظة العميل تلقائياً عند التسليم (مرة واحدة فقط)
        if ($newStatus === 'delivered' && !$shipmentRow['cod_credited'] && (float)$shipmentRow['cod_amount'] > 0) {
            creditCodBalance(
                $pdo, (int)$shipmentRow['user_id'], (float)$shipmentRow['cod_amount'], $id,
                "اعتماد COD لشحنة #{$shipmentRow['tracking_number']}"
            );
        }

        logAdminAction($pdo, 'update_status', 'shipment', $id, "شحنة #{$shipmentRow['tracking_number']} → {$statusLabels[$newStatus]}");
    }
    header('Location: /admin/shipments.php');
    exit;
}

// ---------- تعديل بيانات الشحنة كاملة ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_full') {
    if (!$canEdit) { header('Location: /admin/shipments.php?error=no_permission'); exit; }
    $id = (int)($_POST['shipment_id'] ?? 0);
    $pdo->prepare(
        "UPDATE shipments SET sender_name=?, sender_phone=?, sender_address=?,
         receiver_name=?, receiver_phone=?, receiver_address=?, weight_kg=?, description=?, cost=?, cod_amount=?
         WHERE id=?"
    )->execute([
        sanitize($_POST['sender_name'] ?? ''), sanitize($_POST['sender_phone'] ?? ''), sanitize($_POST['sender_address'] ?? ''),
        sanitize($_POST['receiver_name'] ?? ''), sanitize($_POST['receiver_phone'] ?? ''), sanitize($_POST['receiver_address'] ?? ''),
        (float)($_POST['weight_kg'] ?? 0), sanitize($_POST['description'] ?? ''), (float)($_POST['cost'] ?? 0), (float)($_POST['cod_amount'] ?? 0),
        $id,
    ]);
    logAdminAction($pdo, 'edit_shipment', 'shipment', $id, 'تعديل كامل لبيانات الشحنة');
    header('Location: /admin/shipments.php');
    exit;
}

// ---------- حذف شحنة ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!$canDelete) { header('Location: /admin/shipments.php?error=no_permission'); exit; }
    $id = (int)($_POST['shipment_id'] ?? 0);
    if ($id) {
        $trackingForLog = $pdo->prepare("SELECT tracking_number FROM shipments WHERE id = ?");
        $trackingForLog->execute([$id]);
        $trackingForLog = $trackingForLog->fetchColumn();
        $pdo->prepare("DELETE FROM shipments WHERE id = ?")->execute([$id]);
        logAdminAction($pdo, 'delete_shipment', 'shipment', $id, "حذف شحنة #$trackingForLog");
    }
    header('Location: /admin/shipments.php');
    exit;
}

if (($_GET['error'] ?? '') === 'no_permission') {
    $error = 'ليس لديك صلاحية القيام بهذا الإجراء';
}

$filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$sql = "SELECT s.*, u.name AS customer_name, u.email AS customer_email FROM shipments s JOIN users u ON u.id = s.user_id WHERE 1=1";
$sqlParams = [];
if ($filter && array_key_exists($filter, $statusLabels)) {
    $sql .= " AND s.status = ?";
    $sqlParams[] = $filter;
}
if ($search !== '') {
    $sql .= " AND (s.tracking_number LIKE ? OR s.receiver_phone LIKE ?)";
    $sqlParams[] = "%$search%";
    $sqlParams[] = "%$search%";
}
if ($dateFrom !== '') {
    $sql .= " AND DATE(s.created_at) >= ?";
    $sqlParams[] = $dateFrom;
}
if ($dateTo !== '') {
    $sql .= " AND DATE(s.created_at) <= ?";
    $sqlParams[] = $dateTo;
}
$sql .= " ORDER BY s.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($sqlParams);
$shipments = $stmt->fetchAll();

$pageTitle = 'الشحنات';
require __DIR__ . '/../includes/header_admin.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4>إدارة الشحنات</h4>
  <a href="/admin/export_shipments.php?<?= http_build_query($_GET) ?>" class="btn btn-sm btn-outline-success">تصدير Excel</a>
</div>

<form class="row g-2 mb-3">
  <div class="col-md-3">
    <input type="text" name="search" class="form-control form-control-sm" placeholder="رقم التتبع أو جوال المستلم" value="<?= sanitize($search) ?>">
  </div>
  <div class="col-md-2">
    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
      <option value="">كل الحالات</option>
      <?php foreach ($statusLabels as $key => $label): ?>
        <option value="<?= $key ?>" <?= $filter === $key ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= sanitize($dateFrom) ?>" title="من تاريخ">
  </div>
  <div class="col-md-2">
    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= sanitize($dateTo) ?>" title="إلى تاريخ">
  </div>
  <div class="col-md-2">
    <button class="btn btn-sm btn-primary w-100">بحث/فلترة</button>
  </div>
  <div class="col-md-1">
    <a href="/admin/shipments.php" class="btn btn-sm btn-outline-secondary w-100">مسح</a>
  </div>
</form>

<?php if ($error): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

<div class="card p-3">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr><th>رقم التتبع</th><th>العميل</th><th>المستلم</th><th>الهاتف</th><th>الحالة</th><th>COD</th><?php if ($canEdit): ?><th>تعديل الحالة</th><?php endif; ?><th>إجراءات</th></tr>
      </thead>
      <tbody>
        <?php foreach ($shipments as $s): ?>
        <tr>
          <td class="fw-bold"><?= sanitize($s['tracking_number']) ?></td>
          <td><?= sanitize($s['customer_name']) ?><br><span class="text-muted small"><?= sanitize($s['customer_email']) ?></span></td>
          <td><?= sanitize($s['receiver_name']) ?></td>
          <td><?= $canSeeFullPhone ? sanitize($s['receiver_phone']) : sanitize(maskPhone($s['receiver_phone'])) ?></td>
          <td><span class="badge-status status-<?= sanitize($s['status']) ?>"><?= $statusLabels[$s['status']] ?? $s['status'] ?></span></td>
          <td>
            <?= number_format($s['cod_amount'], 2) ?>
            <?php if ($s['cod_amount'] > 0 && $s['cod_credited']): ?>
              <span class="badge bg-success">مُعتمد</span>
            <?php endif; ?>
          </td>
          <?php if ($canEdit): ?>
          <td>
            <form method="post" class="d-flex gap-1">
              <input type="hidden" name="action" value="update_status">
              <input type="hidden" name="shipment_id" value="<?= $s['id'] ?>">
              <select name="status" class="form-select form-select-sm">
                <?php foreach ($statusLabels as $key => $label): ?>
                  <option value="<?= $key ?>" <?= $s['status'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-sm btn-dark">تحديث</button>
            </form>
          </td>
          <?php endif; ?>
          <td class="d-flex gap-1">
            <?php if ($canEdit): ?>
              <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editShip<?= $s['id'] ?>">تعديل</button>
            <?php endif; ?>
            <?php if ($canDelete): ?>
              <form method="post" onsubmit="return confirm('تأكيد حذف هذه الشحنة نهائياً؟');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="shipment_id" value="<?= $s['id'] ?>">
                <button class="btn btn-sm btn-outline-danger">حذف</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>

        <?php if ($canEdit): ?>
        <div class="modal fade" id="editShip<?= $s['id'] ?>" tabindex="-1">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <form method="post">
                <input type="hidden" name="action" value="edit_full">
                <input type="hidden" name="shipment_id" value="<?= $s['id'] ?>">
                <div class="modal-header">
                  <h6 class="modal-title">تعديل الشحنة <?= sanitize($s['tracking_number']) ?></h6>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <h6 class="text-primary">المرسل</h6>
                  <div class="row">
                    <div class="col-md-4 mb-2"><input class="form-control" name="sender_name" value="<?= sanitize($s['sender_name']) ?>" placeholder="الاسم"></div>
                    <div class="col-md-4 mb-2"><input class="form-control" name="sender_phone" value="<?= sanitize($s['sender_phone']) ?>" placeholder="الجوال"></div>
                    <div class="col-md-4 mb-2"><input class="form-control" name="sender_address" value="<?= sanitize($s['sender_address']) ?>" placeholder="العنوان"></div>
                  </div>
                  <h6 class="text-primary mt-3">المستلم</h6>
                  <div class="row">
                    <div class="col-md-4 mb-2"><input class="form-control" name="receiver_name" value="<?= sanitize($s['receiver_name']) ?>" placeholder="الاسم"></div>
                    <div class="col-md-4 mb-2"><input class="form-control" name="receiver_phone" value="<?= sanitize($s['receiver_phone']) ?>" placeholder="الجوال"></div>
                    <div class="col-md-4 mb-2"><input class="form-control" name="receiver_address" value="<?= sanitize($s['receiver_address']) ?>" placeholder="العنوان"></div>
                  </div>
                  <div class="row mt-3">
                    <div class="col-md-3 mb-2"><input type="number" step="0.1" class="form-control" name="weight_kg" value="<?= sanitize($s['weight_kg']) ?>" placeholder="الوزن (كجم)"></div>
                    <div class="col-md-3 mb-2"><input type="number" step="0.01" class="form-control" name="cost" value="<?= sanitize($s['cost']) ?>" placeholder="أجور الشحن"></div>
                    <div class="col-md-3 mb-2"><input type="number" step="0.01" class="form-control" name="cod_amount" value="<?= sanitize($s['cod_amount']) ?>" placeholder="مبلغ COD"></div>
                    <div class="col-md-3 mb-2"><input class="form-control" name="description" value="<?= sanitize($s['description']) ?>" placeholder="الوصف"></div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button class="btn btn-primary">حفظ التعديلات</button>
                </div>
              </form>
            </div>
          </div>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer_admin.php'; ?>
