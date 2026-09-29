<?php
require_once __DIR__ . '/../config/config.php';

if (isWebAdmin()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $result = webLogin($email, $password);
    if ($result['success'] && $result['user']['role'] === 'admin') {
        header('Location: /admin/dashboard.php');
        exit;
    } elseif ($result['success']) {
        webLogout();
        $error = 'هذا الحساب لا يملك صلاحية دخول لوحة التحكم';
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>دخول لوحة التحكم</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
<link href="/assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-dark d-flex align-items-center" style="min-height:100vh;">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-4">
      <div class="card p-4">
        <h4 class="text-center mb-3">دخول لوحة التحكم</h4>
        <?php if ($error): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>
        <form method="post">
          <div class="mb-3">
            <label class="form-label">البريد الإلكتروني</label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">كلمة المرور</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <button class="btn btn-dark w-100">دخول</button>
        </form>
      </div>
    </div>
  </div>
</div>
</body>
</html>
