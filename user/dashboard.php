<?php
require_once __DIR__ . '/../config/config.php';
requireWebLogin();

$pdo = getDBConnection();
$userId = currentUserId();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'recharge_wallet') {
    $rechargeAmount = (float)($_POST['recharge_amount'] ?? 0);
    if ($rechargeAmount < 10) {
        $error = 'أقل مبلغ للشحن 10 ر.س';
    } else {
        $pdo->prepare("UPDATE users SET shipping_balance = shipping_balance + ? WHERE id = ?")->execute([$rechargeAmount, $userId]);
        try {
            $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'recharge', ?, 'شحن رصيد المحفظة')")->execute([$userId, $rechargeAmount]);
        } catch(Exception $e){}
        $success = 'تم شحن المحفظة بمبلغ ' . number_format($rechargeAmount,2) . ' ر.س بنجاح';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['create_shipment','create_shipment_detailed'])) {
    // دعم النموذج البسيط والقديم والمفصل
    $isDetailed = ($_POST['action'] === 'create_shipment_detailed');
    
    if ($isDetailed) {
        $receiverName = sanitize($_POST['customer_name'] ?? '');
        $receiverPhone = sanitize($_POST['customer_phone'] ?? '');
        $receiverAddress = sanitize($_POST['full_address'] ?? '');
        $country = sanitize($_POST['country'] ?? 'السعودية');
        $city = sanitize($_POST['city'] ?? '');
        $shortAddress = sanitize($_POST['short_address'] ?? '');
        $parcelsCount = (int)($_POST['parcels_count'] ?? 1);
        $paymentMethod = sanitize($_POST['payment_method'] ?? 'cod');
        $totalAmount = (float)($_POST['total_amount'] ?? 0);
        $codAmount = (float)($_POST['cod_amount'] ?? $totalAmount);
        
        $productNames = $_POST['product_name'] ?? [];
        $productSkus = $_POST['product_sku'] ?? [];
        $productQtys = $_POST['product_qty'] ?? [];
        $productPrices = $_POST['product_price'] ?? [];
        $productWeights = $_POST['product_weight'] ?? [];
        
        $totalWeight = 0;
        $productsData = [];
        foreach($productNames as $i=>$pname){
            $w = (float)($productWeights[$i] ?? 1);
            $totalWeight += $w * (int)($productQtys[$i] ?? 1);
            $productsData[] = [
                'name' => sanitize($pname),
                'sku' => sanitize($productSkus[$i] ?? ''),
                'qty' => (int)($productQtys[$i] ?? 1),
                'price' => (float)($productPrices[$i] ?? 0),
                'weight' => $w
            ];
        }
        $weight = $totalWeight;
        $description = sanitize($_POST['description'] ?? '') . " | منتجات: " . json_encode($productsData, JSON_UNESCAPED_UNICODE) . " | مدينة: $city | دولة: $country | عنوان مختصر: $shortAddress | طرود: $parcelsCount | دفع: $paymentMethod";
        
        $senderName = sanitize($_SESSION['user_name'] ?? 'المرسل');
        $senderPhone = '';
        $senderAddress = 'الرياض';
    } else {
        $senderName      = sanitize($_POST['sender_name'] ?? '');
        $senderPhone     = sanitize($_POST['sender_phone'] ?? '');
        $senderAddress   = sanitize($_POST['sender_address'] ?? '');
        $receiverName    = sanitize($_POST['receiver_name'] ?? '');
        $receiverPhone   = sanitize($_POST['receiver_phone'] ?? '');
        $receiverAddress = sanitize($_POST['receiver_address'] ?? '');
        $weight          = (float)($_POST['weight_kg'] ?? 0);
        $description     = sanitize($_POST['description'] ?? '');
        $codAmount       = (float)($_POST['cod_amount'] ?? 0);
    }

    $required = [$receiverName, $receiverPhone, $receiverAddress];
    if (in_array('', $required, true)) {
        $error = 'يرجى تعبئة جميع بيانات العميل والعنوان';
    } else {
        $shippingCost = calculateShippingCost($weight);
        $actualCost = calculateActualCost($weight);
        $balanceRow = $pdo->prepare("SELECT shipping_balance FROM users WHERE id = ?");
        $balanceRow->execute([$userId]);
        $currentBalance = (float)$balanceRow->fetchColumn();

        if ($currentBalance < $shippingCost) {
            $error = "رصيد محفظة الشحن غير كافٍ. تكلفة هذه الشحنة: " . number_format($shippingCost, 2)
                   . " ر.س، رصيدك الحالي: " . number_format($currentBalance, 2) . " ر.س. "
                   . 'يرجى شحن المحفظة أولاً.';
        } else {
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
                $trackingNumber, $userId, $senderName, $senderPhone, $senderAddress,
                $receiverName, $receiverPhone, $receiverAddress, $weight, $description, $shippingCost, $actualCost, $codAmount,
            ]);
            $shipmentId = (int)$pdo->lastInsertId();
            
            // حفظ المنتجات في جدول منفصل لو موجود
            try {
                foreach($productsData ?? [] as $prod){
                    $pdo->prepare("INSERT INTO shipment_items (shipment_id, product_name, sku, quantity, price, weight) VALUES (?,?,?,?,?,?)")
                        ->execute([$shipmentId, $prod['name'], $prod['sku'], $prod['qty'], $prod['price'], $prod['weight']]);
                }
            } catch(Exception $e){}
            
            $pdo->prepare("INSERT INTO shipment_status_history (shipment_id, status, note) VALUES (?, 'pending', 'تم إنشاء الشحنة')")
                ->execute([$shipmentId]);

            deductShippingBalance($pdo, $userId, $shippingCost, $shipmentId, "أجور شحن #$trackingNumber");

            $success = "تم إنشاء الشحنة بنجاح. رقم التتبع: $trackingNumber (خُصم " . number_format($shippingCost, 2) . " ر.س من محفظتك)";
        }
    }
}

$shipments = $pdo->prepare("SELECT * FROM shipments WHERE user_id = ? ORDER BY created_at DESC");
$shipments->execute([$userId]);
$shipments = $shipments->fetchAll();

$statusLabels = [
    'pending' => 'قيد الانتظار', 'processing' => 'قيد المعالجة', 'picked_up' => 'تم الاستلام',
    'in_transit' => 'في الطريق', 'out_for_delivery' => 'خارج للتوصيل',
    'delivered' => 'تم التسليم', 'cancelled' => 'ملغاة',
];

$myBalance = $pdo->prepare("SELECT shipping_balance, cod_balance FROM users WHERE id = ?");
$myBalance->execute([$userId]);
$myBalance = $myBalance->fetch();

$activeCount = $pdo->prepare("SELECT COUNT(*) c FROM shipments WHERE user_id = ? AND status NOT IN ('delivered','cancelled')");
$activeCount->execute([$userId]);
$activeCount = $activeCount->fetch()['c'];

$deliveredCount = $pdo->prepare("SELECT COUNT(*) c FROM shipments WHERE user_id = ? AND status = 'delivered'");
$deliveredCount->execute([$userId]);
$deliveredCount = $deliveredCount->fetch()['c'];

$pendingCount = $pdo->prepare("SELECT COUNT(*) c FROM shipments WHERE user_id = ? AND status = 'pending'");
$pendingCount->execute([$userId]);
$pendingCount = $pendingCount->fetch()['c'];

$chartLabels = [];
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('D', strtotime($day));
    $cnt = $pdo->prepare("SELECT COUNT(*) c FROM shipments WHERE user_id = ? AND DATE(created_at) = ?");
    $cnt->execute([$userId, $day]);
    $chartData[] = (int)$cnt->fetch()['c'];
}

$recentFive = $pdo->prepare("SELECT * FROM shipments WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$recentFive->execute([$userId]);
$recentFive = $recentFive->fetchAll();

$filterStatus = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$sql = "SELECT * FROM shipments WHERE user_id = ?";
$sqlParams = [$userId];
if ($filterStatus && array_key_exists($filterStatus, $statusLabels)) {
    $sql .= " AND status = ?";
    $sqlParams[] = $filterStatus;
}
if ($search !== '') {
    $sql .= " AND (tracking_number LIKE ? OR receiver_phone LIKE ?)";
    $sqlParams[] = "%$search%";
    $sqlParams[] = "%$search%";
}
if ($dateFrom !== '') {
    $sql .= " AND DATE(created_at) >= ?";
    $sqlParams[] = $dateFrom;
}
if ($dateTo !== '') {
    $sql .= " AND DATE(created_at) <= ?";
    $sqlParams[] = $dateTo;
}
$sql .= " ORDER BY created_at DESC";

$shipmentsStmt = $pdo->prepare($sql);
$shipmentsStmt->execute($sqlParams);
$filteredShipments = $shipmentsStmt->fetchAll();

$pageTitle = 'لوحتي';
require __DIR__ . '/../includes/header_user.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4>مرحباً، <?= sanitize($_SESSION['user_name']) ?></h4>
  <div class="d-flex gap-2 align-items-center">
    <span class="badge bg-success-subtle text-success border">رصيد الشحن: <?= number_format($myBalance['shipping_balance'] ?? 0,2) ?> ر.س</span>
    <a href="/user/wallet.php" class="btn btn-outline-primary btn-sm">محفظتي</a>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#rechargeWalletModal">+ شحن الرصيد</button>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newShipmentModal">+ طلب شحنة جديدة</button>
  </div>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= sanitize($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>



?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4>مرحباً، <?= sanitize($_SESSION['user_name']) ?></h4>
  <div class="d-flex gap-2 align-items-center">
    <a href="/user/wallet.php" class="btn btn-outline-primary btn-sm">محفظتي</a>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newShipmentModal">+ طلب شحنة جديدة</button>
  </div>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= sanitize($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="card p-3 text-center"><div class="text-muted small">الشحنات النشطة</div><div class="fs-3 fw-bold text-primary"><?= $activeCount ?></div></div></div>
  <div class="col-6 col-md-3"><div class="card p-3 text-center"><div class="text-muted small">تم التوصيل</div><div class="fs-3 fw-bold text-success"><?= $deliveredCount ?></div></div></div>
  <div class="col-6 col-md-3"><div class="card p-3 text-center"><div class="text-muted small">قيد الانتظار</div><div class="fs-3 fw-bold text-warning"><?= $pendingCount ?></div></div></div>
  <div class="col-6 col-md-3"><div class="card p-3 text-center"><div class="text-muted small">رصيد المحفظة</div><div class="fs-4 fw-bold"><?= number_format($myBalance['shipping_balance'], 2) ?> ر.س</div></div></div>
</div>

<div class="card p-3 mb-4">
  <h6 class="mb-3">شحناتك آخر 7 أيام</h6>
  <canvas id="myShipmentsChart" height="70"></canvas>
</div>

<?php if ($recentFive): ?>
<div class="card p-3 mb-4">
  <h6 class="mb-3">آخر 5 شحنات</h6>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>رقم التتبع</th><th>المستلم</th><th>الحالة</th><th>التاريخ</th></tr></thead>
      <tbody>
        <?php foreach ($recentFive as $s): ?>
        <tr>
          <td class="fw-bold"><?= sanitize($s['tracking_number']) ?></td>
          <td><?= sanitize($s['receiver_name']) ?></td>
          <td><span class="badge-status status-<?= sanitize($s['status']) ?>"><?= $statusLabels[$s['status']] ?? $s['status'] ?></span></td>
          <td class="text-muted small"><?= sanitize($s['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card p-3">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h6 class="mb-0">كل شحناتي</h6>
    <a href="/user/export_shipments.php?<?= http_build_query($_GET) ?>" class="btn btn-sm btn-outline-success">تصدير Excel</a>
  </div>

  <form class="row g-2 mb-3">
    <div class="col-md-3">
      <input type="text" name="search" class="form-control form-control-sm" placeholder="رقم التتبع أو جوال المستلم" value="<?= sanitize($search) ?>">
    </div>
    <div class="col-md-2">
      <select name="status" class="form-select form-select-sm">
        <option value="">كل الحالات</option>
        <?php foreach ($statusLabels as $key => $label): ?>
          <option value="<?= $key ?>" <?= $filterStatus === $key ? 'selected' : '' ?>><?= $label ?></option>
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
      <a href="/user/dashboard.php" class="btn btn-sm btn-outline-secondary w-100">مسح</a>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>رقم التتبع</th><th>المستلم</th><th>الهاتف</th><th>الحالة</th><th>تاريخ الطلب</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$filteredShipments): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">لا توجد شحنات مطابقة</td></tr>
        <?php endif; ?>
        <?php foreach ($filteredShipments as $s): ?>
        <tr>
          <td class="fw-bold"><?= sanitize($s['tracking_number']) ?></td>
          <td><?= sanitize($s['receiver_name']) ?></td>
          <td><?= sanitize($s['receiver_phone']) ?></td>
          <td><span class="badge-status status-<?= sanitize($s['status']) ?>"><?= $statusLabels[$s['status']] ?? $s['status'] ?></span></td>
          <td><?= sanitize($s['created_at']) ?></td>
          <td><a href="/track.php?tracking_number=<?= urlencode($s['tracking_number']) ?>" class="btn btn-sm btn-outline-primary">تتبّع</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>



<!-- Modal إضافة شحنة - النموذج المفصل الكامل -->
<div class="modal fade" id="newShipmentModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 rounded-4 shadow-lg">
      <form method="post" id="detailedShipmentForm">
        <input type="hidden" name="action" value="create_shipment_detailed">
        <div class="modal-header border-bottom">
          <h5 class="modal-title fw-bold"><i class="bi bi-box-seam"></i> إنشاء طلب جديد</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4" style="max-height: 80vh; overflow-y: auto;">
          
          <!-- 1 - العميل والدفع -->
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="badge bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;">1</span>
            <h6 class="mb-0 fw-bold">العميل والدفع</h6>
            <hr class="flex-grow-1 m-0">
          </div>
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <label class="form-label small text-muted">اسم العميل <span class="text-danger">*</span></label>
              <input class="form-control rounded-3" name="customer_name" placeholder="الاسم الكامل" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small text-muted">رقم العميل <span class="text-danger">*</span> <small class="text-muted">(9 أرقام بعد 966)</small></label>
              <div class="input-group">
                <span class="input-group-text bg-white"><span style="font-size:18px;">🇸🇦</span> <small>+966</small></span>
                <input class="form-control" name="customer_phone" id="customerPhone" placeholder="5xxxxxxxx" pattern="5[0-9]{8}" maxlength="9" minlength="9" required oninput="validatePhone(this)">
              </div>
              <small id="phoneError" class="text-danger d-none">يجب أن يبدأ بـ 5 ويتكون من 9 أرقام</small>
            </div>
            <div class="col-md-4">
              <label class="form-label small text-muted">إجمالي المبلغ <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text bg-white">﷼</span>
                <input type="number" step="0.01" class="form-control" name="total_amount" placeholder="0.00" required>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label small text-muted">وسيلة الدفع <span class="text-danger">*</span></label>
              <select class="form-select rounded-3" name="payment_method" required>
                <option value="" disabled selected>اختر وسيلة الدفع</option>
                <option value="cod">الدفع عند الاستلام (COD)</option>
                <option value="prepaid">مدفوع مسبقاً</option>
                <option value="cc">بطاقة ائتمان</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small text-muted">عدد الطرود</label>
              <input type="number" class="form-control rounded-3" name="parcels_count" value="1" min="1">
            </div>
          </div>

          <!-- 2 - عنوان التوصيل -->
          <div class="d-flex align-items-center gap-2 mb-3 mt-4">
            <span class="badge bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;">2</span>
            <h6 class="mb-0 fw-bold">عنوان التوصيل</h6>
            <hr class="flex-grow-1 m-0">
          </div>
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <label class="form-label small text-muted">الدولة <span class="text-danger">*</span></label>
              <select class="form-select rounded-3" name="country" required readonly>
                <option value="SA" selected>🇸🇦 السعودية فقط</option>
              </select>
              <input type="hidden" name="country" value="SA">
            </div>
            <div class="col-md-4">
              <label class="form-label small text-muted">المدينة <span class="text-danger">*</span></label>
              <select class="form-select rounded-3" name="city" id="citySelect" required>
                <option value="" disabled selected>اختر المدينة</option>
                <option>الرياض</option>
                <option>جدة</option>
                <option>الدمام</option>
                <option>مكة</option>
                <option>المدينة</option>
                <option>أبها</option>
                <option>تبوك</option>
                <option>بريدة</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small text-muted">العنوان الوطني المختصر <small class="text-primary">(يعبّي تلقائي)</small></label>
              <div class="input-group">
                <input class="form-control rounded-3" name="short_address" id="shortAddress" placeholder="مثال: RRRD2929" maxlength="8" style="text-transform:uppercase" onblur="lookupNationalAddress(this.value)" oninput="this.value=this.value.toUpperCase()">
                <button class="btn btn-outline-primary" type="button" onclick="lookupNationalAddress(document.getElementById('shortAddress').value)"><i class="bi bi-search"></i></button>
              </div>
              <small id="shortAddressStatus" class="text-muted"></small>
            </div>
            <div class="col-12">
              <label class="form-label small text-muted">العنوان <span class="text-danger">*</span></label>
              <input class="form-control rounded-3" name="full_address" placeholder="الحي، الشارع، رقم المبنى" required>
            </div>
            <div class="col-12">
              <button type="button" class="btn btn-outline-primary btn-sm rounded-3 mt-1" data-bs-toggle="modal" data-bs-target="#mapPickerModal"><i class="bi bi-geo-alt-fill"></i> تحديد الموقع من الخريطة</button>
              <input type="hidden" name="lat" id="latInput">
              <input type="hidden" name="lng" id="lngInput">
              <small id="coordsDisplay" class="text-muted ms-2"></small>
            </div>
          </div>

          <!-- 3 - المنتجات -->
          <div class="d-flex align-items-center gap-2 mb-3 mt-4">
            <span class="badge bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;">3</span>
            <h6 class="mb-0 fw-bold">المنتجات</h6>
            <hr class="flex-grow-1 m-0">
          </div>
          <div class="table-responsive">
            <table class="table table-borderless align-middle" id="productsTable">
              <thead>
                <tr class="text-muted small">
                  <th style="width:5%">#</th>
                  <th style="width:30%">اسم المنتج</th>
                  <th>SKU</th>
                  <th>الكمية</th>
                  <th>السعر</th>
                  <th>الوزن (كجم)</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="productsBody">
                <tr class="product-row">
                  <td>1</td>
                  <td><input class="form-control form-control-sm rounded-3" name="product_name[]" placeholder="اسم المنتج" required></td>
                  <td><input class="form-control form-control-sm rounded-3" name="product_sku[]" placeholder="SKU"></td>
                  <td>
                    <div class="input-group input-group-sm" style="width:110px;">
                      <button class="btn btn-outline-secondary" type="button" onclick="changeQty(this,-1)">−</button>
                      <input type="number" class="form-control text-center" name="product_qty[]" value="1" min="1">
                      <button class="btn btn-outline-secondary" type="button" onclick="changeQty(this,1)">+</button>
                    </div>
                  </td>
                  <td><input type="number" step="0.01" class="form-control form-control-sm rounded-3" name="product_price[]" placeholder="0.00"></td>
                  <td><input type="number" step="0.1" class="form-control form-control-sm rounded-3" name="product_weight[]" value="1" id="weightInput" oninput="updateEstimate()"></td>
                  <td><button type="button" class="btn btn-sm text-danger" onclick="removeRow(this)"><i class="bi bi-trash"></i></button></td>
                </tr>
              </tbody>
            </table>
          </div>
          <button type="button" class="btn btn-light w-100 rounded-3 border dashed-border py-2 small text-muted" onclick="addProductRow()"><i class="bi bi-plus-lg"></i> إضافة منتج</button>

          <div class="bg-light rounded-3 p-3 mt-4 d-flex justify-content-between align-items-center">
            <span class="text-muted small">تكلفة الشحن التقديرية:</span>
            <strong id="estimateCost" class="text-primary">—</strong>
          </div>
          <div class="mt-3">
            <label class="form-label small text-muted">ملاحظات / وصف الشحنة</label>
            <textarea class="form-control rounded-3" name="description" rows="2" placeholder="تفاصيل إضافية..."></textarea>
          </div>
          <div class="row mt-3">
            <div class="col-md-6">
              <label class="form-label small text-muted">مبلغ التحصيل COD</label>
              <input type="number" step="0.01" class="form-control rounded-3" name="cod_amount" placeholder="اتركه فارغاً لو غير محدد">
            </div>
          </div>

          <script>

            function validatePhone(input){
              const error = document.getElementById('phoneError');
              const val = input.value.replace(/[^0-9]/g,'');
              input.value = val;
              if(val.length>0 && !/^5[0-9]{8}$/.test(val)){
                error.classList.remove('d-none');
                input.setCustomValidity('رقم الجوال يجب أن يبدأ بـ 5 ويتكون من 9 أرقام');
              } else {
                error.classList.add('d-none');
                input.setCustomValidity('');
              }
              if(val.length>9) input.value = val.slice(0,9);
            }

            async function lookupNationalAddress(code){
              const status = document.getElementById('shortAddressStatus');
              const citySelect = document.getElementById('citySelect');
              const fullAddress = document.querySelector('input[name="full_address"]');
              if(!code || code.length < 8){
                status.innerText = 'أدخل 8 خانات مثل RRRD2929';
                status.className = 'text-warning';
                return;
              }
              status.innerText = 'جاري جلب البيانات...';
              status.className = 'text-primary';
              try {
                // حاول جلب من API المحلي (يحتاج مفتاح SPL)
                const res = await fetch(`/api/national_address.php?short=${encodeURIComponent(code)}`);
                const data = await res.json();
                if(data.success){
                  if(data.city) citySelect.value = data.city;
                  if(data.district || data.street) fullAddress.value = (data.district? data.district + ' - ':'') + (data.street||'') + (data.buildingNumber? ' - مبنى '+data.buildingNumber:'');
                  if(data.lat && data.lng){
                    document.getElementById('latInput').value = data.lat;
                    document.getElementById('lngInput').value = data.lng;
                    document.getElementById('coordsDisplay').innerText = data.lat+','+data.lng;
                  }
                  status.innerText = '✓ تم تعبئة العنوان من العنوان الوطني';
                  status.className = 'text-success';
                } else {
                  // Fallback: فك تشفير العنوان المختصر بشكل تقريبي
                  status.innerText = 'لم يتم الربط مع سبل، تم التعبئة التقريبية. أدخل المدينة يدوياً لو لزم';
                  status.className = 'text-warning';
                }
              } catch(e){
                status.innerText = 'تعذر الاتصال بخدمة العنوان الوطني - أكمل يدوياً';
                status.className = 'text-danger';
              }
            }

            function changeQty(btn, delta){
              const input = btn.parentElement.querySelector('input');
              let v = parseInt(input.value)||1;
              v = Math.max(1, v+delta);
              input.value = v;
            }
            function addProductRow(){
              const tbody = document.getElementById('productsBody');
              const count = tbody.rows.length + 1;
              const tr = document.createElement('tr');
              tr.className = 'product-row';
              tr.innerHTML = `
                <td>${count}</td>
                <td><input class="form-control form-control-sm rounded-3" name="product_name[]" placeholder="اسم المنتج" required></td>
                <td><input class="form-control form-control-sm rounded-3" name="product_sku[]" placeholder="SKU"></td>
                <td>
                  <div class="input-group input-group-sm" style="width:110px;">
                    <button class="btn btn-outline-secondary" type="button" onclick="changeQty(this,-1)">−</button>
                    <input type="number" class="form-control text-center" name="product_qty[]" value="1" min="1">
                    <button class="btn btn-outline-secondary" type="button" onclick="changeQty(this,1)">+</button>
                  </div>
                </td>
                <td><input type="number" step="0.01" class="form-control form-control-sm rounded-3" name="product_price[]" placeholder="0.00"></td>
                <td><input type="number" step="0.1" class="form-control form-control-sm rounded-3" name="product_weight[]" value="1" oninput="updateEstimate()"></td>
                <td><button type="button" class="btn btn-sm text-danger" onclick="removeRow(this)"><i class="bi bi-trash"></i></button></td>
              `;
              tbody.appendChild(tr);
            }
            function removeRow(btn){
              if(document.querySelectorAll('.product-row').length>1){
                btn.closest('tr').remove();
              }
            }
            function updateEstimate() {
              const weights = document.querySelectorAll('input[name="product_weight[]"]');
              let totalW = 0;
              weights.forEach(i=> totalW += parseFloat(i.value)||0);
              const base = <?= (float)($pdo->query("SELECT base_rate FROM shipping_rates WHERE id=1")->fetchColumn() ?: 15) ?>;
              const perKg = <?= (float)($pdo->query("SELECT per_kg_rate FROM shipping_rates WHERE id=1")->fetchColumn() ?: 2) ?>;
              const extra = Math.max(0, totalW - 1);
              const cost = base + extra * perKg;
              document.getElementById('estimateCost').innerText = cost.toFixed(2) + ' ر.س (' + totalW.toFixed(1) + ' كجم)';
            }
          </script>

        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
          <button type="submit" class="btn btn-primary rounded-3 px-5 fw-bold">إنشاء الطلب</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Modal الخريطة - تحديد الموقع -->
<div class="modal fade" id="mapPickerModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 rounded-4 shadow-lg">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-geo-alt"></i> حدد موقع التوصيل على الخريطة</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <div id="map" style="height:400px; width:100%;"></div>
        <div class="p-3 bg-light">
          <small class="text-muted">اسحب العلامة أو اضغط على الخريطة لتحديد الموقع بدقة</small><br>
          <small>الإحداثيات: <span id="mapCoords">لم يتم التحديد</span></small><br>
          <small>العنوان: <span id="mapAddress">-</span></small>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
        <button type="button" class="btn btn-primary" onclick="confirmMapLocation()">تأكيد الموقع</button>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
let map, marker, selectedLatLng=null;
document.getElementById('mapPickerModal').addEventListener('shown.bs.modal', function(){
  if(!map){
    map = L.map('map').setView([24.7136, 46.6753], 6); // السعودية
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution:'© OpenStreetMap'}).addTo(map);
    marker = L.marker([24.7136, 46.6753], {draggable:true}).addTo(map);
    marker.on('dragend', function(e){ updateMapCoords(e.target.getLatLng()); });
    map.on('click', function(e){ marker.setLatLng(e.latlng); updateMapCoords(e.latlng); });
  }
  setTimeout(()=> map.invalidateSize(), 200);
});
function updateMapCoords(latlng){
  selectedLatLng = latlng;
  document.getElementById('mapCoords').innerText = latlng.lat.toFixed(6) + ', ' + latlng.lng.toFixed(6);
  // Reverse geocode using Nominatim
  fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latlng.lat}&lon=${latlng.lng}&accept-language=ar`)
    .then(r=>r.json()).then(d=>{
      const addr = d.display_name || '';
      document.getElementById('mapAddress').innerText = addr;
    });
}
function confirmMapLocation(){
  if(!selectedLatLng){ alert('حدد موقع على الخريطة أولاً'); return; }
  document.getElementById('latInput').value = selectedLatLng.lat;
  document.getElementById('lngInput').value = selectedLatLng.lng;
  document.getElementById('coordsDisplay').innerText = selectedLatLng.lat.toFixed(5)+','+selectedLatLng.lng.toFixed(5);
  const addrText = document.getElementById('mapAddress').innerText;
  if(addrText && addrText !== '-'){
    document.querySelector('input[name="full_address"]').value = addrText;
  }
  bootstrap.Modal.getInstance(document.getElementById('mapPickerModal')).hide();
}
</script>

<!-- Modal شحن رصيد المحفظة -->
<div class="modal fade" id="rechargeWalletModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 rounded-4 shadow-lg">
      <form method="post">
        <input type="hidden" name="action" value="recharge_wallet">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold">شحن رصيد المحفظة</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info small">رصيدك الحالي: <strong><?= number_format($myBalance['shipping_balance'] ?? 0,2) ?> ر.س</strong></div>
          <label class="form-label small">المبلغ المراد شحنه</label>
          <div class="input-group">
            <input type="number" name="recharge_amount" class="form-control form-control-lg" placeholder="100" min="10" step="10" required>
            <span class="input-group-text">ر.س</span>
          </div>
          <div class="d-flex gap-2 mt-3 flex-wrap">
            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" onclick="this.form.recharge_amount.value=100">100 ر.س</button>
            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" onclick="this.form.recharge_amount.value=250">250 ر.س</button>
            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" onclick="this.form.recharge_amount.value=500">500 ر.س</button>
            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" onclick="this.form.recharge_amount.value=1000">1000 ر.س</button>
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="submit" class="btn btn-success w-100 py-2 fw-bold rounded-3">تأكيد شحن المحفظة</button>
        </div>
      </form>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('myShipmentsChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
    datasets: [{
      label: 'شحناتي',
      data: <?= json_encode($chartData) ?>,
      backgroundColor: '#0d6efd',
      borderRadius: 6,
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
  }
});
</script>
<?php require __DIR__ . '/../includes/footer_user.php'; ?>
