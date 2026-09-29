<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('view_users');

$pdo = getDBConnection();
$canManage = hasPermission('manage_users');
$canSeeFullPhone = isSuperAdmin() || hasPermission('view_full_phone');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) {
        header('Location: /admin/users.php?error=no_permission');
        exit;
    }
    $id = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    // الترقية/التنزيل بين عميل وأدمن قرار حساس — للأدمن الرئيسي فقط
    if (in_array($action, ['make_admin', 'make_user'], true) && !isSuperAdmin()) {
        header('Location: /admin/users.php?error=no_permission');
        exit;
    }

    if ($id && $id !== (int)($_SESSION['user_id'] ?? 0)) {
        $targetName = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $targetName->execute([$id]);
        $targetName = $targetName->fetchColumn() ?: "#$id";

        if ($action === 'suspend') {
            $pdo->prepare("UPDATE users SET status='suspended' WHERE id=? AND role='user'")->execute([$id]);
            logAdminAction($pdo, 'suspend_user', 'user', $id, "تعليق العميل: $targetName");
        } elseif ($action === 'activate') {
            $pdo->prepare("UPDATE users SET status='active' WHERE id=? AND role='user'")->execute([$id]);
            logAdminAction($pdo, 'activate_user', 'user', $id, "تفعيل العميل: $targetName");
        } elseif ($action === 'make_admin') {
            $pdo->prepare("UPDATE users SET role='admin' WHERE id=? AND role='user'")->execute([$id]);
            logAdminAction($pdo, 'promote_user', 'user', $id, "ترقية العميل لموظف: $targetName");
        } elseif ($action === 'make_user') {
            $pdo->prepare("UPDATE users SET role='user', is_super_admin=0 WHERE id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM admin_permissions WHERE user_id=?")->execute([$id]);
            logAdminAction($pdo, 'demote_admin', 'user', $id, "تنزيل الموظف لعميل: $targetName");
        }
    }
    header('Location: /admin/users.php');
    exit;
}

if (($_GET['error'] ?? '') === 'no_permission') {
    $error = 'ليس لديك صلاحية القيام بهذا الإجراء';
}

// هذه الصفحة تعرض العملاء (role='user') فقط — إدارة الموظفين لها صفحة مستقلة
$users = $pdo->query("SELECT * FROM users WHERE role = 'user' ORDER BY created_at DESC")->fetchAll();

$pageTitle = 'المستخدمون';
require __DIR__ . '/../includes/header_admin.php';
?>

<h4 class="mb-3">إدارة العملاء</h4>
<?php if ($error): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

<div class="card p-3">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>الاسم</th><th>البريد</th><th>الجوال</th><th>الحالة</th><?php if ($canManage): ?><th>إجراءات</th><?php endif; ?></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td><?= sanitize($u['name']) ?></td>
          <td><?= sanitize($u['email']) ?></td>
          <td><?= $canSeeFullPhone ? sanitize($u['phone']) : sanitize(maskPhone($u['phone'])) ?></td>
          <td><span class="badge bg-<?= $u['status'] === 'active' ? 'success' : 'danger' ?>"><?= $u['status'] === 'active' ? 'نشط' : 'موقوف' ?></span></td>
          <?php if ($canManage): ?>
          <td class="d-flex gap-1 flex-wrap">
            <form method="post">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <?php if ($u['status'] === 'active'): ?>
                <button name="action" value="suspend" class="btn btn-sm btn-outline-danger">إيقاف</button>
              <?php else: ?>
                <button name="action" value="activate" class="btn btn-sm btn-outline-success">تفعيل</button>
              <?php endif; ?>
            </form>
            <?php if (isSuperAdmin()): ?>
              <form method="post">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button name="action" value="make_admin" class="btn btn-sm btn-outline-dark">ترقية لموظف</button>
              </form>
            <?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (isSuperAdmin()): ?>
<p class="text-muted small mt-3">لإدارة حسابات الموظفين وصلاحياتهم، اذهب إلى <a href="/admin/manage_admins.php">إدارة الموظفين</a>.</p>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer_admin.php'; ?>
