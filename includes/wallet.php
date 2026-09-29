<?php
/**
 * منطق المحافظ المالية المركزي.
 * - محفظة الشحن (shipping): رصيد مدفوع مقدماً، يُخصم منه تلقائياً عند إنشاء كل شحنة. لا يُسمح بالسالب أبداً.
 * - محفظة COD (cod): تتجمع تلقائياً من قيمة تحصيل الشحنات المُسلَّمة، ويُسحب منها عند طلب السحب.
 * كل عملية تُسجَّل في wallet_transactions لضمان سجل مالي كامل قابل للتدقيق.
 */

/** حساب تكلفة الشحن حسب الوزن، بناءً على إعدادات shipping_rates */
function calculateShippingCost(float $weightKg): float
{
    $pdo = getDBConnection();
    $rate = $pdo->query("SELECT base_rate, per_kg_rate FROM shipping_rates WHERE id = 1")->fetch();
    if (!$rate) {
        return 15.0; // قيمة احتياطية لو الإعدادات غير موجودة لأي سبب
    }
    $extraWeight = max(0, $weightKg - 1);
    return round((float)$rate['base_rate'] + $extraWeight * (float)$rate['per_kg_rate'], 2);
}

/** تكلفتك الفعلية التقديرية لنفس الشحنة (تُدخل يدوياً لحين ربط شركات شحن حقيقية) */
function calculateActualCost(float $weightKg): float
{
    $pdo = getDBConnection();
    $rate = $pdo->query("SELECT cost_base_rate, cost_per_kg_rate FROM shipping_rates WHERE id = 1")->fetch();
    if (!$rate) {
        return 0;
    }
    $extraWeight = max(0, $weightKg - 1);
    return round((float)$rate['cost_base_rate'] + $extraWeight * (float)$rate['cost_per_kg_rate'], 2);
}

/**
 * خصم تكلفة شحن من رصيد المستخدم عند إنشاء شحنة جديدة.
 * يرجع true لو نجح الخصم، أو false لو الرصيد غير كافٍ (بدون أي تعديل على القاعدة).
 */
function deductShippingBalance(PDO $pdo, int $userId, float $amount, ?int $shipmentId, string $note): bool
{
    $pdo->beginTransaction();
    try {
        // قفل الصف لمنع تضارب عمليات متزامنة على نفس المستخدم
        $stmt = $pdo->prepare("SELECT shipping_balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $current = (float)($stmt->fetchColumn() ?: 0);

        if ($current < $amount) {
            $pdo->rollBack();
            return false;
        }

        $newBalance = round($current - $amount, 2);
        $pdo->prepare("UPDATE users SET shipping_balance = ? WHERE id = ?")->execute([$newBalance, $userId]);
        $pdo->prepare(
            "INSERT INTO wallet_transactions (user_id, wallet_type, type, amount, balance_after, reference_shipment_id, note)
             VALUES (?, 'shipping', 'deduction', ?, ?, ?, ?)"
        )->execute([$userId, $amount, $newBalance, $shipmentId, $note]);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** إضافة رصيد لمحفظة الشحن (شحن يدوي من الأدمن، أو استرجاع) */
function topupShippingBalance(PDO $pdo, int $userId, float $amount, string $note, ?int $createdBy = null): float
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT shipping_balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $current = (float)($stmt->fetchColumn() ?: 0);
        $newBalance = round($current + $amount, 2);

        $pdo->prepare("UPDATE users SET shipping_balance = ? WHERE id = ?")->execute([$newBalance, $userId]);
        $pdo->prepare(
            "INSERT INTO wallet_transactions (user_id, wallet_type, type, amount, balance_after, note, created_by)
             VALUES (?, 'shipping', 'topup', ?, ?, ?, ?)"
        )->execute([$userId, $amount, $newBalance, $note, $createdBy]);

        $pdo->commit();
        return $newBalance;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** اعتماد مبلغ COD لمحفظة المستخدم عند تسليم شحنة (يُستدعى مرة واحدة فقط لكل شحنة) */
function creditCodBalance(PDO $pdo, int $userId, float $amount, int $shipmentId, string $note): float
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT cod_balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $current = (float)($stmt->fetchColumn() ?: 0);
        $newBalance = round($current + $amount, 2);

        $pdo->prepare("UPDATE users SET cod_balance = ? WHERE id = ?")->execute([$newBalance, $userId]);
        $pdo->prepare(
            "INSERT INTO wallet_transactions (user_id, wallet_type, type, amount, balance_after, reference_shipment_id, note)
             VALUES (?, 'cod', 'cod_credit', ?, ?, ?, ?)"
        )->execute([$userId, $amount, $newBalance, $shipmentId, $note]);
        $pdo->prepare("UPDATE shipments SET cod_credited = 1 WHERE id = ?")->execute([$shipmentId]);

        $pdo->commit();
        return $newBalance;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** طلب سحب من محفظة COD — يخصم المبلغ فوراً (Hold) لحين موافقة/رفض الأدمن */
function requestCodWithdrawal(PDO $pdo, int $userId, float $amount, string $iban): array
{
    if ($amount <= 0) {
        return ['success' => false, 'message' => 'المبلغ غير صحيح'];
    }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT cod_balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $current = (float)($stmt->fetchColumn() ?: 0);

        if ($current < $amount) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'رصيد محفظة COD غير كافٍ'];
        }

        $newBalance = round($current - $amount, 2);
        $pdo->prepare("UPDATE users SET cod_balance = ? WHERE id = ?")->execute([$newBalance, $userId]);
        $pdo->prepare(
            "INSERT INTO wallet_transactions (user_id, wallet_type, type, amount, balance_after, note)
             VALUES (?, 'cod', 'withdrawal', ?, ?, 'طلب سحب قيد المراجعة')"
        )->execute([$userId, $amount, $newBalance]);
        $pdo->prepare(
            "INSERT INTO withdrawal_requests (user_id, amount, bank_iban, status) VALUES (?, ?, ?, 'pending')"
        )->execute([$userId, $amount, $iban]);

        $pdo->commit();
        return ['success' => true, 'message' => 'تم إرسال طلب السحب بنجاح، بانتظار مراجعة الإدارة'];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** رفض طلب سحب COD — يرجّع المبلغ للمحفظة */
function rejectWithdrawalRequest(PDO $pdo, int $requestId, string $adminNote): bool
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM withdrawal_requests WHERE id = ? AND status = 'pending' FOR UPDATE");
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();
        if (!$req) {
            $pdo->rollBack();
            return false;
        }

        $userStmt = $pdo->prepare("SELECT cod_balance FROM users WHERE id = ? FOR UPDATE");
        $userStmt->execute([$req['user_id']]);
        $current = (float)($userStmt->fetchColumn() ?: 0);
        $newBalance = round($current + (float)$req['amount'], 2);

        $pdo->prepare("UPDATE users SET cod_balance = ? WHERE id = ?")->execute([$newBalance, $req['user_id']]);
        $pdo->prepare(
            "INSERT INTO wallet_transactions (user_id, wallet_type, type, amount, balance_after, note)
             VALUES (?, 'cod', 'withdrawal_reversal', ?, ?, ?)"
        )->execute([$req['user_id'], $req['amount'], $newBalance, 'رفض طلب السحب: ' . $adminNote]);
        $pdo->prepare(
            "UPDATE withdrawal_requests SET status='rejected', admin_note=?, processed_at=NOW() WHERE id=?"
        )->execute([$adminNote, $requestId]);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** الموافقة على طلب سحب COD — المبلغ خُصم مسبقاً عند الطلب، فقط نغيّر الحالة */
function approveWithdrawalRequest(PDO $pdo, int $requestId, string $adminNote): bool
{
    $stmt = $pdo->prepare("SELECT id FROM withdrawal_requests WHERE id = ? AND status = 'pending'");
    $stmt->execute([$requestId]);
    if (!$stmt->fetch()) {
        return false;
    }
    $pdo->prepare(
        "UPDATE withdrawal_requests SET status='approved', admin_note=?, processed_at=NOW() WHERE id=?"
    )->execute([$adminNote, $requestId]);
    return true;
}
