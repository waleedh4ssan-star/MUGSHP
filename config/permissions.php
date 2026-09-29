<?php
/**
 * تعريف مركزي لكل الصلاحيات المتاحة لموظفي لوحة التحكم.
 * الأدمن الرئيسي (is_super_admin = 1) يملك كل الصلاحيات تلقائياً دائماً،
 * وهو الوحيد القادر يدير حسابات الموظفين (إضافة/حذف/تعديل صلاحياتهم).
 */

const ADMIN_PERMISSIONS = [
    'view_dashboard'  => 'عرض لوحة التحكم والإحصائيات',
    'view_shipments'  => 'عرض الشحنات',
    'edit_shipments'  => 'تعديل بيانات الشحنات وتحديث حالتها',
    'delete_shipments'=> 'حذف الشحنات',
    'view_users'      => 'عرض قائمة العملاء',
    'manage_users'    => 'تعليق/تفعيل حسابات العملاء',
    'manage_api_keys' => 'إدارة مفاتيح API',
    'manage_wallet'   => 'إدارة المحافظ المالية (شحن رصيد، الموافقة على السحوبات)',
    'view_financials' => 'عرض التقارير المالية والأرباح (سعر البيع مقابل التكلفة الفعلية)',
    'manage_rates'    => 'تعديل أسعار الشحن والتكلفة الفعلية',
    'view_full_phone' => 'عرض أرقام جوالات العملاء كاملة (بدونها تظهر مموّهة جزئياً)',
];

/** حزم صلاحيات جاهزة (قوالب أدوار) لتسهيل إنشاء موظف بدور معروف بضغطة واحدة.
 *  تشمل فقط الأدوار اللي لها صفحات فعلية بالنظام حالياً. */
const ROLE_TEMPLATES = [
    'operations' => [
        'label' => 'موظف عمليات',
        'permissions' => ['view_dashboard', 'view_shipments', 'edit_shipments', 'view_users', 'view_full_phone'],
    ],
    'finance' => [
        'label' => 'موظف مالية',
        'permissions' => ['view_dashboard', 'view_shipments', 'manage_wallet', 'view_financials'],
    ],
];

function allPermissionKeys(): array
{
    return array_keys(ADMIN_PERMISSIONS);
}
