<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? sanitize($pageTitle) . ' - ' : '' ?><?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
<link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand p-0 d-flex align-items-center" href="/user/dashboard.php">
  <img src="/assets/img/maqtura_logo_white.png" alt="<?= APP_NAME ?>" style="height: 36px; width: auto; object-fit: contain;">
</a>
    <div class="d-flex gap-2">
      <a href="/track.php" class="btn btn-outline-light btn-sm">تتبّع شحنة</a>
      <?php if (isWebLoggedIn()): ?>
        <a href="/user/dashboard.php" class="btn btn-outline-light btn-sm">لوحتي</a>
        <a href="/user/wallet.php" class="btn btn-outline-light btn-sm">محفظتي</a>
        <a href="/user/logout.php" class="btn btn-light btn-sm">خروج</a>
      <?php else: ?>
        <a href="/user/login.php" class="btn btn-outline-light btn-sm">دخول</a>
        <a href="/user/register.php" class="btn btn-light btn-sm">حساب جديد</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
<div class="container py-4">
