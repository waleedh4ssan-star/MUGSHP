<?php
require_once __DIR__ . '/../config/config.php';
requireWebAdmin();
if (!hasPermission('manage_wallet') && !hasPermission('manage_rates') && !hasPermission('view_financials')) {
    header('Location: /admin/access_denied.php');
    exit;
}

$pdo = getDBConnection();
$error = '';
$success = '';

// ---------- شحن رصيد يدوي لعميل ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'topup') {
    if (!hasPermission('manage_wallet')) { header('Location: /admin/wallets.php?error=no_permission'); exit; }
    $targetId = (int)($_POST['user_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $note = sanitize($_POST['note'] ?? '') ?: 'شحن رصيد يدوي من الإدارة';
    if ($targetId && $amount > 0) {
        topupShippingBalance($pdo, $targetId, $amount, $note, (int)$_SESSION['user_id']);
        logAdminAction($pdo, 'wallet_topup', 'user', $targetId, "شحن $amount ر.س - $note");
        $success = 'تم شحن الرصيد بنجاح';
    } else {
        $error = 'بيانات الشحن غير صحيحة';
    }
}

// ---------- تحديث أسعار الشحن ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_rates') {
    if (!hasPermission('manage_rates')) { header('Location: /admin/wallets.php?error=no_permission'); exit; }
    $base = (float)($_POST['base_rate'] ?? 0);
    $perKg = (float)($_POST['per_kg_rate'] ?? 0);
    $costBase = (float)($_POST['cost_base_rate'] ?? 0);
    $costPerKg = (float)($_POST['cost_per_kg_rate'] ?? 0);
    $pdo->prepare("UPDATE shipping_rates SET base_rate=?, per_kg_rate=?, cost_base_rate=?, cost_per_kg_rate=? WHERE id = 1")
        ->execute([$base, $perKg, $costBase, $costPerKg]);
    logAdminAction($pdo, 'update_rates', 'shipping_rates', 1, "سعر بيع: $base+$perKg/كجم، تكلفة: $costBase+$costPerKg/كجم");
    $success = 'تم تحديث أسعار الشحن';
}

// ---------- الموافقة/رفض طلب سحب ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve_withdrawal') {
    if (!hasPermission('manage_wallet')) { header('Location: /admin/wallets.php?error=no_permission'); exit; }
    approveWithdrawalRequest($pdo, (int)$_POST['request_id'], sanitize($_POST['note'] ?? ''));
    logAdminAction($pdo, 'approve_withdrawal', 'withdrawal_request', (int)$_POST['request_id'], 'تمت الموافقة');
    $success = 'تمت الموافقة على طلب السحب';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject_withdrawal') {
    if (!hasPermission('manage_wallet')) { header('Location: /admin/wallets.php?error=no_permission'); exit; }
    rejectWithdrawalRequest($pdo, (int)$_POST['request_id'], sanitize($_POST['note'] ?? 'مرفوض من الإدارة'));
    logAdminAction($pdo, 'reject_withdrawal', 'withdrawal_request', (int)$_POST['request_id'], 'تم الرفض');
    $success = 'تم رفض الطلب وإرجاع المبلغ لمحفظة العميل';
}

$rates = $pdo->query("SELECT * FROM shipping_rates WHERE id = 1")->fetch();
$pendingWithdrawals = $pdo->query(
    "SELECT w.*, u.name AS user_name, u.email AS user_email FROM withdrawal_requests w
     JOIN users u ON u.id = w.user_id WHERE w.status = 'pending' ORDER BY w.created_at ASC"
)->fetchAll();
$customers = $pdo->query(
    "SELECT id, name, email, shipping_balance, cod_balance FROM users WHERE role = 'user' ORDER BY name ASC"
)->fetchAll();

$financials = ['total_revenue' => 0, 'total_cost' => 0, 'total_profit' => 0, 'shipment_count' => 0];
if (hasPermission('view_financials')) {
    $fin = $pdo->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(cost),0) AS rev, COALESCE(SUM(actual_cost),0) AS cst FROM shipments")->fetch();
    $financials['shipment_count'] = (int)$fin['cnt'];
    $financials['total_revenue']  = (float)$fin['rev'];
    $financials['total_cost']     = (float)$fin['cst'];
    $financials['total_profit']   = $financials['total_revenue'] - $financials['total_cost'];
}

$pageTitle = 'المحافظ المالية';
require __DIR__ . '/../includes/header_admin.php';
?>

<h4 class="mb-3">إدارة المحافظ المالية</h4>
<?php if ($success): ?><div class="alert alert-success"><?= sanitize($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

<div class="row g-3 mb-4">
  <?php if (hasPermission('manage_rates')): ?>
  <div class="col-md-6">
    <div class="card p-3">
      <h6>أسعار الشحن الحالية</h6>
      <form method="post" class="d-flex gap-2 align-items-end flex-wrap">
        <input type="hidden" name="action" value="update_rates">
        <div>
          <label class="form-label small">سعر البيع الأساسي (أول كجم)</label>
          <input type="number" step="0.01" name="base_rate" class="form-control form-control-sm" value="<?= $rates['base_rate'] ?>">
        </div>
        <div>
          <label class="form-label small">سعر البيع لكل كجم إضافي</label>
          <input type="number" step="0.01" name="per_kg_rate" class="form-control form-control-sm" value="<?= $rates['per_kg_rate'] ?>">
        </div>
        <div>
          <label class="form-label small text-danger">تكلفتك الفعلية (أول كجم)</label>
          <input type="number" step="0.01" name="cost_base_rate" class="form-control form-control-sm" value="<?= $rates['cost_base_rate'] ?>">
        </div>
        <div>
          <label class="form-label small text-danger">تكلفتك الفعلية (لكل كجم إضافي)</label>
          <input type="number" step="0.01" name="cost_per_kg_rate" class="form-control form-control-sm" value="<?= $rates['cost_per_kg_rate'] ?>">
        </div>
        <button class="btn btn-sm btn-dark">حفظ</button>
      </form>
      <p class="text-muted small mb-0 mt-2">
        التكلفة الفعلية تُدخل يدوياً حالياً (تقديرية) لحين ربط شركات شحن حقيقية عبر API — تُستخدم فقط لحساب الربح التقديري في لوحة التحكم.
      </p>
    </div>
  </div>
  <?php endif; ?>

  <?php if (hasPermission('manage_wallet')): ?>
  <div class="col-md-6">
    <div class="card p-3">
      <h6>شحن رصيد يدوي لعميل</h6>
      <form method="post" class="d-flex gap-2 flex-wrap">
        <input type="hidden" name="action" value="topup">
        <select name="user_id" class="form-select form-select-sm" style="max-width:220px" required>
          <option value="">اختر عميل</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?> (<?= sanitize($c['email']) ?>)</option>
          <?php endforeach; ?>
        </select>
        <input type="number" step="0.01" name="amount" placeholder="المبلغ" class="form-control form-control-sm" style="max-width:120px" required>
        <input type="text" name="note" placeholder="ملاحظة (اختياري)" class="form-control form-control-sm">
        <button class="btn btn-sm btn-primary">شحن</button>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php if (hasPermission('view_financials')): ?>
<div class="card p-3 mb-4">
  <h6 class="mb-3">ملخص مالي تقديري (بناءً على الأسعار الحالية)</h6>
  <div class="row text-center g-3">
    <div class="col-md-3">
      <div class="text-muted small">إجمالي الإيرادات (كل الوقت)</div>
      <div class="fs-4 fw-bold"><?= number_format($financials['total_revenue'], 2) ?> ر.س</div>
    </div>
    <div class="col-md-3">
      <div class="text-muted small">إجمالي التكلفة التقديرية</div>
      <div class="fs-4 fw-bold text-danger"><?= number_format($financials['total_cost'], 2) ?> ر.س</div>
    </div>
    <div class="col-md-3">
      <div class="text-muted small">الربح التقديري</div>
      <div class="fs-4 fw-bold text-success"><?= number_format($financials['total_profit'], 2) ?> ر.س</div>
    </div>
    <div class="col-md-3">
      <div class="text-muted small">عدد الشحنات المحتسبة</div>
      <div class="fs-4 fw-bold"><?= $financials['shipment_count'] ?></div>
    </div>
  </div>
  <p class="text-muted small mb-0 mt-2">* الأرقام تقديرية بناءً على التكلفة اليدوية المُدخلة، وليست تكلفة فعلية من شركة شحن حقيقية بعد.</p>
</div>
<?php endif; ?>

<?php if (hasPermission('manage_wallet')): ?>
<div class="card p-3 mb-4">
  <h6 class="mb-3">طلبات سحب COD قيد المراجعة (<?= count($pendingWithdrawals) ?>)</h6>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>العميل</th><th>المبلغ</th><th>الآيبان</th><th>التاريخ</th><th>إجراء</th></tr></thead>
      <tbody>
        <?php if (!$pendingWithdrawals): ?>
          <tr><td colspan="5" class="text-center text-muted py-3">لا توجد طلبات قيد المراجعة</td></tr>
        <?php endif; ?>
        <?php foreach ($pendingWithdrawals as $w): ?>
        <tr>
          <td><?= sanitize($w['user_name']) ?><br><span class="text-muted small"><?= sanitize($w['user_email']) ?></span></td>
          <td class="fw-bold"><?= number_format($w['amount'], 2) ?> ر.س</td>
          <td><?= sanitize($w['bank_iban']) ?></td>
          <td class="text-muted small"><?= sanitize($w['created_at']) ?></td>
          <td class="d-flex gap-1">
            <form method="post">
              <input type="hidden" name="action" value="approve_withdrawal">
              <input type="hidden" name="request_id" value="<?= $w['id'] ?>">
              <button class="btn btn-sm btn-success" onclick="return confirm('تأكيد تحويل المبلغ فعلياً بنكياً ثم الموافقة؟');">تمت الموافقة (تم التحويل)</button>
            </form>
            <form method="post">
              <input type="hidden" name="action" value="reject_withdrawal">
              <input type="hidden" name="request_id" value="<?= $w['id'] ?>">
              <button class="btn btn-sm btn-outline-danger">رفض</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card p-3">
  <h6 class="mb-3">أرصدة العملاء</h6>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>الاسم</th><th>البريد</th><th>رصيد الشحن</th><th>رصيد COD</th></tr></thead>
      <tbody>
        <?php foreach ($customers as $c): ?>
        <tr>
          <td><?= sanitize($c['name']) ?></td>
          <td><?= sanitize($c['email']) ?></td>
          <td><?= number_format($c['shipping_balance'], 2) ?></td>
          <td class="text-success"><?= number_format($c['cod_balance'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>


<?php require __DIR__ . '/../includes/footer_admin.php'; ?>
