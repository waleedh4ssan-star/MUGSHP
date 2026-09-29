<?php
/**
 * مصادقة واجهات الويب (لوحة المستخدم ولوحة الأدمن) بالاعتماد على الجلسة (Session)
 * هذا منفصل عن توكنات الـ API المستخدمة من التطبيقات الخارجية
 */

function webLogin(string $email, string $password): array
{
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة'];
    }

    if ($user['status'] !== 'active') {
        return ['success' => false, 'message' => 'هذا الحساب موقوف، تواصل مع الإدارة'];
    }

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];

    return ['success' => true, 'user' => $user];
}

function webLogout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function isWebLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function isWebAdmin(): bool
{
    return isWebLoggedIn() && ($_SESSION['user_role'] ?? '') === 'admin';
}

/** الأدمن الرئيسي: يملك كل الصلاحيات دائماً. يُقرأ حيّاً من القاعدة حتى تنعكس
 *  أي ترقية/تنزيل صلاحيات فوراً دون الحاجة لتسجيل خروج/دخول. */
function isSuperAdmin(): bool
{
    if (!isWebAdmin()) {
        return false;
    }
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT is_super_admin FROM users WHERE id = ?");
    $stmt->execute([currentUserId()]);
    return (int)$stmt->fetchColumn() === 1;
}

/** هل الموظف الحالي يملك صلاحية معينة؟ الأدمن الرئيسي يملك كل شيء تلقائياً.
 *  يقرأ من قاعدة البيانات مباشرة (لا يعتمد على الجلسة) حتى تنعكس أي تغييرات
 *  في الصلاحيات فوراً دون الحاجة لتسجيل خروج/دخول. */
function hasPermission(string $key): bool
{
    if (isSuperAdmin()) {
        return true;
    }
    if (!isWebAdmin()) {
        return false;
    }
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT 1 FROM admin_permissions WHERE user_id = ? AND permission_key = ? LIMIT 1");
    $stmt->execute([currentUserId(), $key]);
    return (bool)$stmt->fetchColumn();
}

/** يوقف الطلب ويحوّل المستخدم لو ما يملك الصلاحية المطلوبة */
function requirePermission(string $key, string $redirect = '/admin/dashboard.php'): void
{
    requireWebAdmin();
    if (!hasPermission($key)) {
        header('Location: ' . $redirect . '?error=no_permission');
        exit;
    }
}

/** يوقف الطلب لو المستخدم مو الأدمن الرئيسي (لصفحات إدارة الموظفين) */
function requireSuperAdmin(string $redirect = '/admin/dashboard.php'): void
{
    requireWebAdmin();
    if (!isSuperAdmin()) {
        header('Location: ' . $redirect . '?error=no_permission');
        exit;
    }
}

function requireWebLogin(string $redirect = '/user/login.php'): void
{
    if (!isWebLoggedIn()) {
        header('Location: ' . $redirect);
        exit;
    }
}

function requireWebAdmin(string $redirect = '/admin/login.php'): void
{
    if (!isWebAdmin()) {
        header('Location: ' . $redirect);
        exit;
    }
}

function currentUserId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}
