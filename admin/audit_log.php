<?php
require_once __DIR__ . '/../config/config.php';
requireSuperAdmin();

$pdo = getDBConnection();

$filterAdmin = $_GET['admin_id'] ?? '';
$filterAction = $_GET['action'] ?? '';

$where = [];
$params = [];
if ($filterAdmin !== '') {
    $where[] = 'admin_id = ?';
    $params[] = $filterAdmin;
}
if ($filterAction !== '') {
    $where[] = 'action = ?';
    $params[] = $filterAction;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT * FROM audit_log $whereSql ORDER BY created_at DESC LIMIT 300");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$admins = $pdo->query("SELECT id, name FROM users WHERE role = 'admin' ORDER BY name")->fetchAll();
$actions = $pdo->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetchAll();

$actionLabels = [
    'update_status' => 'تحديث حالة شحنة', 'edit_shipment' => 'تعديل شحنة', 'delete_shipment' => 'حذف شحنة',
    'wallet_topup' => 'شحن رصيد', 'approve_withdrawal' => 'موافقة على سحب', 'reject_withdrawal' => 'رفض سحب',
    'update_rates' => 'تعديل الأسعار', 'create_admin' => 'إنشاء موظف', 'delete_admin' => 'حذف موظف',
    'update_permissions' => 'تعديل صلاحيات', 'suspend_user' => 'تعليق عميل', 'activate_user' => 'تفعيل عميل',
    'promote_user' => 'ترقية عميل لموظف',
];

$pageTitle = 'سجل العمليات';
require __DIR__ . '/../includes/header_admin.php';
?>

<h4 class="mb-3">سجل العمليات</h4>
<p class="text-muted small">سجل بكل إجراء حسّاس قام به أي موظف في لوحة التحكم — لا يمكن حذف أو تعديل أي سطر هنا.</p>

<form class="d-flex gap-2 mb-3 flex-wrap">
  <select name="admin_id" class="form-select form-select-sm" style="max-width:220px" onchange="this.form.submit()">
    <option value="">كل الموظفين</option>
    <?php foreach ($admins as $a): ?>
      <option value="<?= $a['id'] ?>" <?= $filterAdmin == $a['id'] ? 'selected' : '' ?>><?= sanitize($a['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="action" class="form-select form-select-sm" style="max-width:220px" onchange="this.form.submit()">
    <option value="">كل الإجراءات</option>
    <?php foreach ($actions as $a): ?>
      <option value="<?= sanitize($a['action']) ?>" <?= $filterAction === $a['action'] ? 'selected' : '' ?>>
        <?= $actionLabels[$a['action']] ?? sanitize($a['action']) ?>
      </option>
    <?php endforeach; ?>
  </select>
</form>

<div class="card p-3">
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>الموظف</th><th>الإجراء</th><th>التفاصيل</th><th>التاريخ</th></tr></thead>
      <tbody>
        <?php if (!$logs): ?>
          <tr><td colspan="4" class="text-center text-muted py-3">لا توجد سجلات بعد</td></tr>
        <?php endif; ?>
        <?php foreach ($logs as $log): ?>
        <tr>
          <td class="fw-bold"><?= sanitize($log['admin_name']) ?></td>
          <td><span class="badge bg-secondary"><?= $actionLabels[$log['action']] ?? sanitize($log['action']) ?></span></td>
          <td class="text-muted small"><?= sanitize($log['details']) ?></td>
          <td class="text-muted small"><?= sanitize($log['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="text-muted small mb-0">آخر 300 سطر فقط. استخدم الفلاتر أعلاه لتضييق النتائج.</p>
</div>

<?php require __DIR__ . '/../includes/footer_admin.php'; ?>
