<?php
require_once __DIR__ . '/../config/config.php';
requireSuperAdmin();

$pdo = getDBConnection();
$error = '';
$success = '';

// ---------- إضافة موظف جديد ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $name     = sanitize($_POST['name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $phone    = sanitize($_POST['phone'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $selectedPerms = $_POST['permissions'] ?? [];

    if ($name === '' || $email === '' || $password === '') {
        $error = 'الاسم والبريد وكلمة المرور مطلوبة';
    } elseif (!isValidEmail($email)) {
        $error = 'صيغة البريد الإلكتروني غير صحيحة';
    } elseif (strlen($password) < 6) {
        $error = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'هذا البريد الإلكتروني مسجّل مسبقاً';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare(
                "INSERT INTO users (name, email, phone, password_hash, role, is_super_admin, status)
                 VALUES (?, ?, ?, ?, 'admin', 0, 'active')"
            )->execute([$name, $email, $phone, $hash]);
            $newId = (int)$pdo->lastInsertId();

            foreach ($selectedPerms as $perm) {
                if (array_key_exists($perm, ADMIN_PERMISSIONS)) {
                    $pdo->prepare("INSERT IGNORE INTO admin_permissions (user_id, permission_key) VALUES (?, ?)")
                        ->execute([$newId, $perm]);
                }
            }
            $success = "تم إنشاء حساب الموظف \"$name\" بنجاح";
            logAdminAction($pdo, 'create_admin', 'user', $newId, "إنشاء موظف: $name ($email) بصلاحيات: " . implode(', ', $selectedPerms));
        }
    }
}

// ---------- تحديث صلاحيات موظف موجود ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_permissions') {
    $targetId = (int)($_POST['user_id'] ?? 0);
    $selectedPerms = $_POST['permissions'] ?? [];

    $target = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin' AND is_super_admin = 0");
    $target->execute([$targetId]);
    $target = $target->fetch();

    if ($target) {
        $pdo->prepare("DELETE FROM admin_permissions WHERE user_id = ?")->execute([$targetId]);
        foreach ($selectedPerms as $perm) {
            if (array_key_exists($perm, ADMIN_PERMISSIONS)) {
                $pdo->prepare("INSERT IGNORE INTO admin_permissions (user_id, permission_key) VALUES (?, ?)")
                    ->execute([$targetId, $perm]);
            }
        }
        $success = 'تم تحديث صلاحيات الموظف';
        logAdminAction($pdo, 'update_permissions', 'user', $targetId, "صلاحيات جديدة لـ {$target['name']}: " . implode(', ', $selectedPerms));
    }
}

// ---------- حذف موظف ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $targetId = (int)($_POST['user_id'] ?? 0);
    if ($targetId === (int)$_SESSION['user_id']) {
        $error = 'لا يمكنك حذف حسابك الخاص';
    } else {
        $target = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
        $target->execute([$targetId]);
        $target = $target->fetch();
        if ($target && (int)$target['is_super_admin'] === 1) {
            $error = 'لا يمكن حذف حساب أدمن رئيسي آخر من هنا';
        } elseif ($target) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$targetId]);
            $success = 'تم حذف حساب الموظف';
            logAdminAction($pdo, 'delete_admin', 'user', $targetId, "حذف موظف: {$target['name']} ({$target['email']})");
        }
    }
}

$admins = $pdo->query(
    "SELECT * FROM users WHERE role = 'admin' ORDER BY is_super_admin DESC, created_at DESC"
)->fetchAll();

// صلاحيات كل موظف مسبقاً (لعرضها كـ checkboxes محددة)
$permsByUser = [];
$permRows = $pdo->query("SELECT user_id, permission_key FROM admin_permissions")->fetchAll();
foreach ($permRows as $row) {
    $permsByUser[$row['user_id']][] = $row['permission_key'];
}

$pageTitle = 'إدارة الموظفين';
require __DIR__ . '/../includes/header_admin.php';
?>

<h4 class="mb-3">إدارة حسابات الموظفين والصلاحيات</h4>

<?php if ($success): ?><div class="alert alert-success"><?= sanitize($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>

<div class="d-flex justify-content-end mb-3">
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newAdminModal">+ إضافة موظف جديد</button>
</div>

<div class="card p-3">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>الاسم</th><th>البريد</th><th>النوع</th><th>الصلاحيات</th><th>إجراءات</th></tr></thead>
      <tbody>
        <?php foreach ($admins as $a): ?>
        <tr>
          <td><?= sanitize($a['name']) ?></td>
          <td><?= sanitize($a['email']) ?></td>
          <td>
            <?php if ($a['is_super_admin']): ?>
              <span class="badge bg-dark">أدمن رئيسي (كل الصلاحيات)</span>
            <?php else: ?>
              <span class="badge bg-secondary">موظف</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($a['is_super_admin']): ?>
              <span class="text-muted small">—</span>
            <?php else: ?>
              <?php $userPerms = $permsByUser[$a['id']] ?? []; ?>
              <?php if ($userPerms): ?>
                <?php foreach ($userPerms as $p): ?>
                  <span class="badge bg-light text-dark border mb-1"><?= sanitize(ADMIN_PERMISSIONS[$p] ?? $p) ?></span>
                <?php endforeach; ?>
              <?php else: ?>
                <span class="text-muted small">بدون صلاحيات</span>
              <?php endif; ?>
            <?php endif; ?>
          </td>
          <td class="d-flex gap-1 flex-wrap">
            <?php if (!$a['is_super_admin']): ?>
              <button class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#editPerms<?= $a['id'] ?>">تعديل الصلاحيات</button>
              <?php if ((int)$a['id'] !== (int)$_SESSION['user_id']): ?>
                <form method="post" onsubmit="return confirm('تأكيد حذف هذا الموظف؟');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="user_id" value="<?= $a['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger">حذف</button>
                </form>
              <?php endif; ?>
            <?php else: ?>
              <span class="text-muted small">—</span>
            <?php endif; ?>
          </td>
        </tr>

        <!-- Modal تعديل صلاحيات هذا الموظف -->
        <?php if (!$a['is_super_admin']): ?>
        <div class="modal fade" id="editPerms<?= $a['id'] ?>" tabindex="-1">
          <div class="modal-dialog">
            <div class="modal-content">
              <form method="post">
                <input type="hidden" name="action" value="update_permissions">
                <input type="hidden" name="user_id" value="<?= $a['id'] ?>">
                <div class="modal-header">
                  <h6 class="modal-title">صلاحيات: <?= sanitize($a['name']) ?></h6>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <?php foreach (ADMIN_PERMISSIONS as $key => $label): ?>
                    <div class="form-check mb-2">
                      <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= $key ?>"
                             id="perm_<?= $a['id'] ?>_<?= $key ?>"
                             <?= in_array($key, $permsByUser[$a['id']] ?? [], true) ? 'checked' : '' ?>>
                      <label class="form-check-label" for="perm_<?= $a['id'] ?>_<?= $key ?>"><?= sanitize($label) ?></label>
                    </div>
                  <?php endforeach; ?>
                </div>
                <div class="modal-footer">
                  <button class="btn btn-primary">حفظ الصلاحيات</button>
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

<!-- Modal إضافة موظف جديد -->
<div class="modal fade" id="newAdminModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="create">
        <div class="modal-header">
          <h5 class="modal-title">إضافة موظف جديد</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2"><input class="form-control" name="name" placeholder="الاسم الكامل" required></div>
          <div class="mb-2"><input type="email" class="form-control" name="email" placeholder="البريد الإلكتروني" required></div>
          <div class="mb-2"><input class="form-control" name="phone" placeholder="رقم الجوال"></div>
          <div class="mb-3"><input type="password" class="form-control" name="password" placeholder="كلمة المرور" minlength="6" required></div>

          <div class="mb-3">
            <label class="form-label fw-bold">قالب دور جاهز (اختياري)</label>
            <select class="form-select form-select-sm" onchange="applyRoleTemplate(this)">
              <option value="">— اختر يدوياً من تحت —</option>
              <?php foreach (ROLE_TEMPLATES as $tplKey => $tpl): ?>
                <option value="<?= $tplKey ?>" data-perms="<?= sanitize(implode(',', $tpl['permissions'])) ?>">
                  <?= sanitize($tpl['label']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <label class="form-label fw-bold">الصلاحيات</label>
          <?php foreach (ADMIN_PERMISSIONS as $key => $label): ?>
            <div class="form-check mb-2">
              <input class="form-check-input role-perm-checkbox" type="checkbox" name="permissions[]" value="<?= $key ?>" id="new_<?= $key ?>">
              <label class="form-check-label" for="new_<?= $key ?>"><?= sanitize($label) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary">إنشاء الحساب</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function applyRoleTemplate(selectEl) {
  const checkboxes = document.querySelectorAll('#newAdminModal .role-perm-checkbox');
  const templateKey = selectEl.value;
  if (!templateKey) {
    checkboxes.forEach(cb => cb.checked = false);
    return;
  }
  const selectedOption = selectEl.querySelector(`option[value="${templateKey}"]`);
  const perms = (selectedOption.dataset.perms || '').split(',').filter(Boolean);
  checkboxes.forEach(cb => { cb.checked = perms.includes(cb.value); });
}
</script>
<?php require __DIR__ . '/../includes/footer_admin.php'; ?>
