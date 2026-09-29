<?php
require_once __DIR__ . '/config/config.php';

$trackingNumber = trim($_GET['tracking_number'] ?? '');
$shipment = null;
$history = [];
$notFound = false;

if ($trackingNumber !== '') {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM shipments WHERE tracking_number = ? LIMIT 1");
    $stmt->execute([$trackingNumber]);
    $shipment = $stmt->fetch();

    if ($shipment) {
        $h = $pdo->prepare("SELECT * FROM shipment_status_history WHERE shipment_id = ? ORDER BY created_at ASC");
        $h->execute([$shipment['id']]);
        $history = $h->fetchAll();
    } else {
        $notFound = true;
    }
}

$statusLabels = [
    'pending' => 'قيد الانتظار', 'processing' => 'قيد المعالجة', 'picked_up' => 'تم الاستلام',
    'in_transit' => 'في الطريق', 'out_for_delivery' => 'خارج للتوصيل',
    'delivered' => 'تم التسليم', 'cancelled' => 'ملغاة',
];

$pageTitle = 'تتبّع شحنة';
require __DIR__ . '/includes/header_user.php';
?>

<div class="tracking-box">
  <div class="card p-4 mb-4">
    <h4 class="text-center mb-3">تتبّع شحنتك</h4>
    <form method="get" class="d-flex gap-2">
      <input type="text" name="tracking_number" class="form-control" placeholder="أدخل رقم التتبع مثال: SHP-20260101-AB12CD"
             value="<?= sanitize($trackingNumber) ?>" required>
      <button class="btn btn-primary">تتبّع</button>
    </form>
  </div>

  <?php if ($notFound): ?>
    <div class="alert alert-warning text-center">لا توجد شحنة بهذا الرقم، تأكد من الرقم وحاول مجدداً.</div>
  <?php endif; ?>

  <?php if ($shipment): ?>
    <div class="card p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><?= sanitize($shipment['tracking_number']) ?></h5>
        <span class="badge-status status-<?= sanitize($shipment['status']) ?>">
          <?= $statusLabels[$shipment['status']] ?? $shipment['status'] ?>
        </span>
      </div>
      <p class="mb-1"><strong>المستلم:</strong> <?= sanitize($shipment['receiver_name']) ?></p>
      <p class="mb-1"><strong>عنوان التسليم:</strong> <?= sanitize($shipment['receiver_address']) ?></p>
      <p class="mb-3 text-muted small">آخر تحديث: <?= sanitize($shipment['updated_at']) ?></p>

      <h6 class="mt-4">سجل الحالة</h6>
      <?php foreach (array_reverse($history) as $h): ?>
        <div class="timeline-item">
          <div class="fw-bold"><?= $statusLabels[$h['status']] ?? sanitize($h['status']) ?></div>
          <?php if ($h['note']): ?><div class="text-muted small"><?= sanitize($h['note']) ?></div><?php endif; ?>
          <div class="text-muted small"><?= sanitize($h['created_at']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer_user.php'; ?>
