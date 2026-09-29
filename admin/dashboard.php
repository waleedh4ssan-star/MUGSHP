<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('view_dashboard', '/admin/access_denied.php');

$pdo = getDBConnection();

$totalShipments = $pdo->query("SELECT COUNT(*) c FROM shipments")->fetch()['c'];
$totalUsers     = $pdo->query("SELECT COUNT(*) c FROM users WHERE role='user'")->fetch()['c'];
$pending        = $pdo->query("SELECT COUNT(*) c FROM shipments WHERE status='pending'")->fetch()['c'];
$delivered      = $pdo->query("SELECT COUNT(*) c FROM shipments WHERE status='delivered'")->fetch()['c'];

$todayCount = $pdo->query("SELECT COUNT(*) c FROM shipments WHERE DATE(created_at) = CURDATE()")->fetch()['c'];
$monthCount = $pdo->query("SELECT COUNT(*) c FROM shipments WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())")->fetch()['c'];

// شحنات متأخرة: قيد الانتظار أو المعالجة لأكثر من 24 ساعة
$stuck = $pdo->query(
    "SELECT COUNT(*) c FROM shipments
     WHERE status IN ('pending','processing') AND created_at < (NOW() - INTERVAL 24 HOUR)"
)->fetch()['c'];

$financials = ['total_revenue' => 0, 'total_cost' => 0, 'total_profit' => 0];
if (hasPermission('view_financials')) {
    $fin = $pdo->query("SELECT COALESCE(SUM(cost),0) AS rev, COALESCE(SUM(actual_cost),0) AS cst FROM shipments")->fetch();
    $financials['total_revenue'] = (float)$fin['rev'];
    $financials['total_cost']    = (float)$fin['cst'];
    $financials['total_profit']  = $financials['total_revenue'] - $financials['total_cost'];
}

$pendingWithdrawalsCount = 0;
if (hasPermission('manage_wallet')) {
    $pendingWithdrawalsCount = (int)$pdo->query("SELECT COUNT(*) c FROM withdrawal_requests WHERE status='pending'")->fetch()['c'];
}

// بيانات آخر 7 أيام لرسم بياني
$chartLabels = [];
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('D', strtotime($day));
    $cnt = $pdo->prepare("SELECT COUNT(*) c FROM shipments WHERE DATE(created_at) = ?");
    $cnt->execute([$day]);
    $chartData[] = (int)$cnt->fetch()['c'];
}

$recent = $pdo->query(
    "SELECT s.*, u.name AS customer_name FROM shipments s
     JOIN users u ON u.id = s.user_id ORDER BY s.created_at DESC LIMIT 5"
)->fetchAll();

$statusLabels = [
    'pending' => 'قيد الانتظار', 'processing' => 'قيد المعالجة', 'picked_up' => 'تم الاستلام',
    'in_transit' => 'في الطريق', 'out_for_delivery' => 'خارج للتوصيل',
    'delivered' => 'تم التسليم', 'cancelled' => 'ملغاة',
];

$pageTitle = 'الرئيسية';
require __DIR__ . '/../includes/header_admin.php';
?>

<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="card p-3 text-center"><div class="text-muted small">إجمالي الشحنات</div><div class="fs-3 fw-bold"><?= $totalShipments ?></div></div></div>
  <div class="col-md-3"><div class="card p-3 text-center"><div class="text-muted small">شحنات اليوم</div><div class="fs-3 fw-bold text-primary"><?= $todayCount ?></div></div></div>
  <div class="col-md-3"><div class="card p-3 text-center"><div class="text-muted small">شحنات هذا الشهر</div><div class="fs-3 fw-bold"><?= $monthCount ?></div></div></div>
  <div class="col-md-3"><div class="card p-3 text-center"><div class="text-muted small">شحنات متأخرة (+24 ساعة)</div><div class="fs-3 fw-bold <?= $stuck > 0 ? 'text-danger' : '' ?>"><?= $stuck ?></div></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card p-3 text-center"><div class="text-muted small">إجمالي العملاء</div><div class="fs-3 fw-bold"><?= $totalUsers ?></div></div></div>
  <div class="col-md-3"><div class="card p-3 text-center"><div class="text-muted small">قيد الانتظار</div><div class="fs-3 fw-bold text-warning"><?= $pending ?></div></div></div>
  <div class="col-md-3"><div class="card p-3 text-center"><div class="text-muted small">تم التسليم</div><div class="fs-3 fw-bold text-success"><?= $delivered ?></div></div></div>
  <?php if (hasPermission('manage_wallet')): ?>
  <div class="col-md-3"><div class="card p-3 text-center"><div class="text-muted small">طلبات سحب معلقة</div><div class="fs-3 fw-bold <?= $pendingWithdrawalsCount > 0 ? 'text-danger' : '' ?>"><?= $pendingWithdrawalsCount ?></div></div></div>
  <?php endif; ?>
</div>

<?php if (hasPermission('view_financials')): ?>
<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="card p-3 text-center"><div class="text-muted small">إجمالي الإيرادات</div><div class="fs-4 fw-bold"><?= number_format($financials['total_revenue'], 2) ?> ر.س</div></div></div>
  <div class="col-md-4"><div class="card p-3 text-center"><div class="text-muted small">إجمالي التكلفة التقديرية</div><div class="fs-4 fw-bold text-danger"><?= number_format($financials['total_cost'], 2) ?> ر.س</div></div></div>
  <div class="col-md-4"><div class="card p-3 text-center"><div class="text-muted small">الربح التقديري</div><div class="fs-4 fw-bold text-success"><?= number_format($financials['total_profit'], 2) ?> ر.س</div></div></div>
</div>
<?php endif; ?>

<div class="card p-3 mb-4">
  <h6 class="mb-3">الشحنات آخر 7 أيام</h6>
  <canvas id="shipmentsChart" height="80"></canvas>
</div>

<div class="card p-3">
  <h5 class="mb-3">آخر 5 شحنات</h5>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>رقم التتبع</th><th>العميل</th><th>المستلم</th><th>الحالة</th><th>التاريخ</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $s): ?>
        <tr>
          <td class="fw-bold"><?= sanitize($s['tracking_number']) ?></td>
          <td><?= sanitize($s['customer_name']) ?></td>
          <td><?= sanitize($s['receiver_name']) ?></td>
          <td><span class="badge-status status-<?= sanitize($s['status']) ?>"><?= $statusLabels[$s['status']] ?? $s['status'] ?></span></td>
          <td><?= sanitize($s['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <a href="/admin/shipments.php" class="btn btn-sm btn-outline-dark">عرض كل الشحنات</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('shipmentsChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
    datasets: [{
      label: 'عدد الشحنات',
      data: <?= json_encode($chartData) ?>,
      borderColor: '#0d6efd',
      backgroundColor: 'rgba(13,110,253,0.1)',
      tension: 0.3,
      fill: true,
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
  }
});
</script>

<?php require __DIR__ . '/../includes/footer_admin.php'; ?>
