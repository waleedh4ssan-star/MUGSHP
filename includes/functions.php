<?php
/**
 * دوال مساعدة عامة (API Response, Tokens, Sanitize, Auth Guards)
 */

function jsonResponse($data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function sanitize($value): string
{
    return htmlspecialchars(trim((string)($value ?? '')), ENT_QUOTES, 'UTF-8');
}

function generateTrackingNumber(): string
{
    return 'SHP-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

function generateToken(): string
{
    return bin2hex(random_bytes(32));
}

/** قراءة Authorization: Bearer <token> بشكل متوافق مع مختلف إعدادات الاستضافة */
function getBearerToken(): ?string
{
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders() ?: [];
    }
    $authHeader = $headers['Authorization']
        ?? $headers['authorization']
        ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? null)
        ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null);

    if ($authHeader && preg_match('/Bearer\s+(\S+)/i', $authHeader, $m)) {
        return $m[1];
    }
    return null;
}

function getApiKeyHeader(): ?string
{
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders() ?: [];
    }
    foreach ($headers as $k => $v) {
        if (strtolower($k) === 'x-api-key') {
            return $v;
        }
    }
    return $_SERVER['HTTP_X_API_KEY'] ?? null;
}

/** التحقق من توكن مستخدم مسجّل دخول عبر الـ API، يعيد بيانات المستخدم أو يوقف الطلب */
function requireUserAuth(): array
{
    $token = getBearerToken();
    if (!$token) {
        jsonResponse(['success' => false, 'message' => 'مطلوب تسجيل الدخول (Token مفقود)'], 401);
    }

    $pdo = getDBConnection();
    $stmt = $pdo->prepare(
        "SELECT t.user_id, u.name, u.email, u.role
         FROM auth_tokens t
         JOIN users u ON u.id = t.user_id
         WHERE t.token = ? AND t.expires_at > NOW() AND u.status = 'active'"
    );
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'Token غير صالح أو منتهي الصلاحية'], 401);
    }

    return $user;
}

function requireAdminAuth(): array
{
    $user = requireUserAuth();
    if ($user['role'] !== 'admin') {
        jsonResponse(['success' => false, 'message' => 'صلاحيات غير كافية'], 403);
    }
    return $user;
}

/** للتطبيقات/المواقع الخارجية التي تستدعي الـ API الوسيط عبر مفتاح ثابت */
function requireApiKey(): array
{
    $apiKey = getApiKeyHeader();
    if (!$apiKey) {
        jsonResponse(['success' => false, 'message' => 'مطلوب X-Api-Key'], 401);
    }

    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM api_keys WHERE api_key = ? AND active = 1");
    $stmt->execute([$apiKey]);
    $key = $stmt->fetch();

    if (!$key) {
        jsonResponse(['success' => false, 'message' => 'API Key غير صالح أو معطّل'], 401);
    }

    return $key;
}

function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** تمويه رقم جوال جزئياً لموظف ما يملك صلاحية view_full_phone (مثال: 05xxxx1234) */
function maskPhone(string $phone): string
{
    $len = strlen($phone);
    if ($len <= 6) {
        return $phone;
    }
    return substr($phone, 0, 2) . str_repeat('x', $len - 6) . substr($phone, -4);
}
