<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? sanitize($pageTitle) . ' - ' : '' ?>لوحة التحكم</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
<link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid px-4">
    <a class="navbar-brand fw-bold" href="/admin/dashboard.php">لوحة تحكم الأدمن</a>
    <div class="d-flex gap-2">
      <?php if (hasPermission('view_dashboard')): ?><a href="/admin/dashboard.php" class="btn btn-outline-light btn-sm">الرئيسية</a><?php endif; ?>
      <?php if (hasPermission('view_shipments')): ?><a href="/admin/shipments.php" class="btn btn-outline-light btn-sm">الشحنات</a><?php endif; ?>
      <?php if (hasPermission('view_users')): ?><a href="/admin/users.php" class="btn btn-outline-light btn-sm">العملاء</a><?php endif; ?>
      <?php if (hasPermission('manage_api_keys')): ?><a href="/admin/api_keys.php" class="btn btn-outline-light btn-sm">مفاتيح API</a><?php endif; ?>
      <?php if (hasPermission('manage_wallet') || hasPermission('manage_rates') || hasPermission('view_financials')): ?><a href="/admin/wallets.php" class="btn btn-outline-light btn-sm">المحافظ</a><?php endif; ?>
      <?php if (isSuperAdmin()): ?><a href="/admin/manage_admins.php" class="btn btn-outline-light btn-sm">الموظفون</a><?php endif; ?>
      <?php if (isSuperAdmin()): ?><a href="/admin/audit_log.php" class="btn btn-outline-light btn-sm">سجل العمليات</a><?php endif; ?>
      <a href="/admin/logout.php" class="btn btn-light btn-sm">خروج</a>
    </div>
  </div>
</nav>
<div class="container-fluid px-4 py-4">
