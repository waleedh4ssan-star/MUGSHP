<?php
/**
 * سجل العمليات (Audit Log) — يسجّل كل إجراء حسّاس يقوم به أي موظف/أدمن
 * في لوحة التحكم، لضمان إمكانية تتبّع "مين سوى إيه ومتى" لاحقاً.
 */

function logAdminAction(PDO $pdo, string $action, string $entityType, ?int $entityId, string $details): void
{
    $adminId   = $_SESSION['user_id'] ?? null;
    $adminName = $_SESSION['user_name'] ?? 'غير معروف';

    $pdo->prepare(
        "INSERT INTO audit_log (admin_id, admin_name, action, entity_type, entity_id, details)
         VALUES (?, ?, ?, ?, ?, ?)"
    )->execute([$adminId, $adminName, $action, $entityType, $entityId, $details]);
}
