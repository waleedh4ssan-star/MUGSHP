<?php
define('IS_API', true);
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'طريقة الطلب غير مسموحة'], 405);
}

$input = getJsonInput() ?: $_POST;

$name     = sanitize($input['name'] ?? '');
$email    = strtolower(trim($input['email'] ?? ''));
$phone    = sanitize($input['phone'] ?? '');
$password = (string)($input['password'] ?? '');

if ($name === '' || $email === '' || $password === '') {
    jsonResponse(['success' => false, 'message' => 'الاسم والبريد الإلكتروني وكلمة المرور مطلوبة'], 422);
}
if (!isValidEmail($email)) {
    jsonResponse(['success' => false, 'message' => 'صيغة البريد الإلكتروني غير صحيحة'], 422);
}
if (strlen($password) < 6) {
    jsonResponse(['success' => false, 'message' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل'], 422);
}

$pdo = getDBConnection();

$check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$check->execute([$email]);
if ($check->fetch()) {
    jsonResponse(['success' => false, 'message' => 'هذا البريد الإلكتروني مسجّل مسبقاً'], 409);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'user')");
$stmt->execute([$name, $email, $phone, $hash]);
$userId = (int)$pdo->lastInsertId();

// إصدار توكن دخول مباشرة بعد التسجيل
$token = generateToken();
$expires = date('Y-m-d H:i:s', strtotime('+' . TOKEN_EXPIRY_DAYS . ' days'));
$pdo->prepare("INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, ?)")
    ->execute([$userId, $token, $expires]);

jsonResponse([
    'success' => true,
    'message' => 'تم إنشاء الحساب بنجاح',
    'data' => [
        'user'  => ['id' => $userId, 'name' => $name, 'email' => $email],
        'token' => $token,
    ],
], 201);
