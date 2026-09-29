<?php
require_once __DIR__ . '/../config/config.php';

if (isWebLoggedIn()) {
    header('Location: /user/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = sanitize($_POST['name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $phone    = sanitize($_POST['phone'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($name === '' || $email === '' || $password === '') {
        $error = 'جميع الحقول المطلوبة يجب تعبئتها';
    } elseif (!isValidEmail($email)) {
        $error = 'صيغة البريد الإلكتروني غير صحيحة';
    } elseif (strlen($password) < 6) {
        $error = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    } else {
        $pdo = getDBConnection();
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'هذا البريد الإلكتروني مسجّل مسبقاً';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'user')")
                ->execute([$name, $email, $phone, $hash]);
            webLogin($email, $password);
            header('Location: /user/dashboard.php');
            exit;
        }
    }
}

$pageTitle = 'إنشاء حساب';
require __DIR__ . '/../includes/header_user.php';
?>
<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card p-4">
      <h4 class="mb-3 text-center">إنشاء حساب جديد</h4>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= sanitize($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <div class="mb-3">
          <label class="form-label">الاسم الكامل</label>
          <input type="text" name="name" class="form-control" required value="<?= sanitize($_POST['name'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">البريد الإلكتروني</label>
          <input type="email" name="email" class="form-control" required value="<?= sanitize($_POST['email'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">رقم الجوال</label>
          <input type="text" name="phone" class="form-control" value="<?= sanitize($_POST['phone'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">كلمة المرور</label>
          <input type="password" name="password" class="form-control" required minlength="6">
        </div>
        <button type="submit" class="btn btn-primary w-100">إنشاء الحساب</button>
      </form>
      <p class="text-center mt-3 mb-0">لديك حساب؟ <a href="/user/login.php">تسجيل الدخول</a></p>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer_user.php'; ?>
