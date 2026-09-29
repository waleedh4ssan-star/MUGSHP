<?php
require_once __DIR__ . '/../config/config.php';

if (isWebLoggedIn()) {
    header('Location: /user/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $result = webLogin($email, $password);
    if ($result['success']) {
        header('Location: /user/dashboard.php');
        exit;
    }
    $error = $result['message'];
}

$pageTitle = 'تسجيل الدخول';
require __DIR__ . '/../includes/header_user.php';
?>
<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card p-4">
      <h4 class="mb-3 text-center">تسجيل الدخول</h4>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= sanitize($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <div class="mb-3">
          <label class="form-label">البريد الإلكتروني</label>
          <input type="email" name="email" class="form-control" required value="<?= sanitize($_POST['email'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">كلمة المرور</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">دخول</button>
      </form>
      <p class="text-center mt-3 mb-0">ليس لديك حساب؟ <a href="/user/register.php">إنشاء حساب</a></p>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer_user.php'; ?>
