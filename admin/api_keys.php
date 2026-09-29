<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('manage_api_keys');

$pdo = getDBConnection();
$newKeyPlain = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $name = sanitize($_POST['name'] ?? '');
        if ($name !== '') {
            $newKeyPlain = generateToken();
            $pdo->prepare("INSERT INTO api_keys (name, api_key, active) VALUES (?, ?, 1)")
                ->execute([$name, $newKeyPlain]);
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE api_keys SET active = 1 - active WHERE id = ?")->execute([$id]);
    }
}

$keys = $pdo->query("SELECT * FROM api_keys ORDER BY created_at DESC")->fetchAll();

$pageTitle = 'مفاتيح API';
require __DIR__ . '/../includes/header_admin.php';
?>

<h4 class="mb-3">مفاتيح API للتطبيقات الخارجية</h4>

<?php if ($newKeyPlain): ?>
  <div class="alert alert-success">
    تم إنشاء المفتاح بنجاح. انسخه الآن — لن يظهر بشكل كامل لاحقاً بنفس الطريقة:<br>
    <code><?= sanitize($newKeyPlain) ?></code>
  </div>
<?php endif; ?>

<div class="card p-3 mb-4">
  <form method="post" class="d-flex gap-2">
    <input type="hidden" name="action" value="create">
    <input type="text" name="name" class="form-control" placeholder="اسم التطبيق/الموقع الذي سيستخدم المفتاح" required>
    <button class="btn btn-dark">إنشاء مفتاح جديد</button>
  </form>
</div>

<div class="card p-3">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>الاسم</th><th>المفتاح</th><th>الحالة</th><th>تاريخ الإنشاء</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($keys as $k): ?>
        <tr>
          <td><?= sanitize($k['name']) ?></td>
          <td><code><?= sanitize(substr($k['api_key'], 0, 10)) ?>...</code></td>
          <td><span class="badge bg-<?= $k['active'] ? 'success' : 'secondary' ?>"><?= $k['active'] ? 'مفعّل' : 'معطّل' ?></span></td>
          <td><?= sanitize($k['created_at']) ?></td>
          <td>
            <form method="post">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= $k['id'] ?>">
              <button class="btn btn-sm btn-outline-secondary"><?= $k['active'] ? 'تعطيل' : 'تفعيل' ?></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer_admin.php'; ?>
