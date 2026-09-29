<?php
define('IS_API', true);
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'طريقة الطلب غير مسموحة'], 405);
}

$input = getJsonInput() ?: $_POST;
$email    = strtolower(trim($input['email'] ?? ''));
$password = (string)($input['password'] ?? '');

if ($email === '' || $password === '') {
    jsonResponse(['success' => false, 'message' => 'البريد الإلكتروني وكلمة المرور مطلوبان'], 422);
}

$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    jsonResponse(['success' => false, 'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة'], 401);
}
if ($user['status'] !== 'active') {
    jsonResponse(['success' => false, 'message' => 'هذا الحساب موقوف'], 403);
}

$token = generateToken();
$expires = date('Y-m-d H:i:s', strtotime('+' . TOKEN_EXPIRY_DAYS . ' days'));
$pdo->prepare("INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, ?)")
    ->execute([$user['id'], $token, $expires]);

jsonResponse([
    'success' => true,
    'message' => 'تم تسجيل الدخول بنجاح',
    'data' => [
        'user' => [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ],
        'token' => $token,
    ],
]);
