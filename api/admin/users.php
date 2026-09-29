<?php
define('IS_API', true);
require_once __DIR__ . '/../../config/config.php';

requireAdminAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'طريقة الطلب غير مسموحة'], 405);
}

$pdo = getDBConnection();
$stmt = $pdo->query("SELECT id, name, email, phone, role, status, created_at FROM users ORDER BY created_at DESC");

jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
