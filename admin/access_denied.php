<?php
require_once __DIR__ . '/../config/config.php';
requireWebAdmin();

$pageTitle = 'غير مصرح';
require __DIR__ . '/../includes/header_admin.php';
?>

<div class="card p-4 text-center">
  <h4 class="text-danger mb-2">ليس لديك صلاحية الوصول</h4>
  <p class="text-muted mb-0">حسابك لا يملك أي صلاحيات مفعّلة حالياً في لوحة التحكم. تواصل مع الأدمن الرئيسي لتفعيل الصلاحيات المناسبة لك.</p>
</div>

<?php require __DIR__ . '/../includes/footer_admin.php'; ?>
