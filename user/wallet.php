<?php
require_once __DIR__ . '/../config/config.php';
requireWebLogin();

$pdo = getDBConnection();
$userId = currentUserId();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'withdraw') {
    $amount = (float)($_POST['amount'] ?? 0);
    $iban = sanitize($_POST['iban'] ?? '');
    if ($iban === '') {
        $error = 'رقم الآيبان مطلوب';
    } else {
        $result = requestCodWithdrawal($pdo, $userId, $amount, $iban);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

$user = $pdo->prepare("SELECT shipping_balance, cod_balance FROM users WHERE id = ?");
$user->execute([$userId]);
$user = $user->fetch();

$transactions = $pdo->prepare(
    "SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 50"
);
$transactions->execute([$userId]);
$transactions = $transactions->fetchAll();

$withdrawals = $pdo->prepare(
    "SELECT * FROM withdrawal_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 20"
);
$withdrawals->execute([$userId]);
$withdrawals = $withdrawals->fetchAll();

$typeLabels = [
    'topup' => 'شحن رصيد', 'deduction' => 'خصم أجور شحن', 'cod_credit' => 'اعتماد COD',
    'withdrawal' => 'طلب سحب', 'withdrawal_reversal' => 'استرجاع سحب مرفوض', 'adjustment' => 'تعديل يدوي',
];
$wStatusLabels = ['pending' => 'قيد المراجعة', 'approved' => 'تم التحويل', 'rejected' => 'مرفوض'];

$pageTitle = 'محفظتي';
require __DIR__ . '/../includes/header_user.php';
?>

<h4 class="mb-3">محفظتي</h4>

<?php if ($success): ?><div class="alert alert-success"><?= sanitize($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card p-3">
      <span class="text-muted small">رصيد الشحن (مدفوع مقدماً)</span>
      <div class="fs-3 fw-bold"><?= number_format($user['shipping_balance'], 2) ?> ر.س</div>
      <p class="text-muted small mb-0">يُستخدم لدفع أجور الشحن تلقائياً عند إنشاء أي شحنة. لشحن رصيدك، تواصل مع الإدارة حالياً.</p>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card p-3">
      <span class="text-muted small">رصيد COD (مستحق لك)</span>
      <div class="fs-3 fw-bold text-success"><?= number_format($user['cod_balance'], 2) ?> ر.س</div>
      <button class="btn btn-sm btn-success mt-2" data-bs-toggle="modal" data-bs-target="#withdrawModal">طلب سحب</button>
    </div>
  </div>
</div>

<?php if ($withdrawals): ?>
<div class="card p-3 mb-4">
  <h6 class="mb-3">طلبات السحب</h6>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>المبلغ</th><th>الآيبان</th><th>الحالة</th><th>التاريخ</th></tr></thead>
      <tbody>
        <?php foreach ($withdrawals as $w): ?>
        <tr>
          <td><?= number_format($w['amount'], 2) ?> ر.س</td>
          <td><?= sanitize($w['bank_iban']) ?></td>
          <td>
            <span class="badge bg-<?= $w['status'] === 'approved' ? 'success' : ($w['status'] === 'rejected' ? 'danger' : 'warning') ?>">
              <?= $wStatusLabels[$w['status']] ?? $w['status'] ?>
            </span>
          </td>
          <td><?= sanitize($w['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card p-3">
  <h6 class="mb-3">سجل الحركات</h6>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>النوع</th><th>المحفظة</th><th>المبلغ</th><th>الرصيد بعدها</th><th>ملاحظة</th><th>التاريخ</th></tr></thead>
      <tbody>
        <?php if (!$transactions): ?>
          <tr><td colspan="6" class="text-center text-muted py-3">لا توجد حركات بعد</td></tr>
        <?php endif; ?>
        <?php foreach ($transactions as $t): ?>
        <tr>
          <td><?= $typeLabels[$t['type']] ?? $t['type'] ?></td>
          <td><?= $t['wallet_type'] === 'shipping' ? 'الشحن' : 'COD' ?></td>
          <td class="<?= in_array($t['type'], ['topup','cod_credit','withdrawal_reversal'], true) ? 'text-success' : 'text-danger' ?>">
            <?= in_array($t['type'], ['topup','cod_credit','withdrawal_reversal'], true) ? '+' : '-' ?><?= number_format($t['amount'], 2) ?>
          </td>
          <td><?= number_format($t['balance_after'], 2) ?></td>
          <td class="text-muted small"><?= sanitize($t['note']) ?></td>
          <td class="text-muted small"><?= sanitize($t['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="withdrawModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="withdraw">
        <div class="modal-header">
          <h5 class="modal-title">طلب سحب من محفظة COD</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">المبلغ (المتاح: <?= number_format($user['cod_balance'], 2) ?> ر.س)</label>
            <input type="number" step="0.01" max="<?= $user['cod_balance'] ?>" class="form-control" name="amount" required>
          </div>
          <div class="mb-2">
            <label class="form-label">رقم الآيبان</label>
            <input type="text" class="form-control" name="iban" placeholder="SA00 0000 0000 0000 0000 0000" required>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary">إرسال الطلب</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer_user.php'; ?>
