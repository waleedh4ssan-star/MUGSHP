# منصة Middleware لتتبع الشحنات

مبنية بلغة PHP خام (بدون Framework) لتعمل مباشرة على استضافة Hostinger المشتركة.

## المحتويات
- **`/api`** — الـ Middleware نفسه: نقاط API يستقبل منها أي تطبيق أو موقع خارجي طلبات JSON ويتعامل مع قاعدة البيانات.
- **`/user`** — واجهة العميل (تسجيل، دخول، لوحة تحكم، طلب شحنة).
- **`/admin`** — لوحة الأدمن (إدارة الشحنات، المستخدمين، مفاتيح API).
- **`/track.php`** — صفحة تتبع عامة لا تحتاج تسجيل دخول.
- **`/database/schema.sql`** — هيكلة قاعدة البيانات كاملة.

## خطوات التثبيت على Hostinger

1. **رفع الملفات**: من لوحة Hostinger افتح File Manager، وارفع كل محتويات هذا المجلد إلى `public_html` (أو الدومين الفرعي المطلوب). تأكد أن `index.php` يكون في جذر المجلد مباشرة.

2. **قاعدة البيانات**: قاعدة البيانات `qobpoyte_shipment_db` وبيانات الاتصال معبّأة مسبقاً في `config/database.php`. تأكد فقط أنها منشأة فعلياً من لوحة Hostinger (hPanel → Databases) وأن المستخدم `qobpoyte_shipment_db` له صلاحيات كاملة عليها.

3. **تنفيذ الجداول**: افتح **phpMyAdmin** من hPanel، اختر قاعدة البيانات، ثم اذهب لتبويب **SQL** والصق محتوى ملف `database/schema.sql` بالكامل ونفّذه (Go). سينشئ هذا كل الجداول + حساب أدمن افتراضي.

4. **الدخول للوحة الأدمن**: بعد الرفع، افتح `https://yourdomain.com/admin/login.php`
   - البريد: `admin@example.com`
   - كلمة المرور: `Admin@123`
   - **غيّر كلمة المرور فوراً** (من قاعدة البيانات مباشرة أو أضف صفحة تغيير كلمة مرور لاحقاً).

5. **تحديث الإعدادات**: في `config/config.php` غيّر قيمة `APP_URL` إلى رابط موقعك الفعلي.

6. **PHP Version**: من hPanel → Advanced → PHP Configuration، اختر PHP 8.1 أو أحدث.

## استخدام الـ API (الوسيط) من تطبيق/موقع خارجي

جميع الردود بصيغة JSON.

### تسجيل مستخدم
```
POST /api/auth/register.php
Body: { "name": "...", "email": "...", "phone": "...", "password": "..." }
```

### تسجيل دخول (يرجع Token)
```
POST /api/auth/login.php
Body: { "email": "...", "password": "..." }
```

### إنشاء شحنة (يتطلب Header: Authorization: Bearer <token>)
```
POST /api/shipments/create.php
Body: { "sender_name":"", "sender_phone":"", "sender_address":"",
        "receiver_name":"", "receiver_phone":"", "receiver_address":"",
        "weight_kg": 2.5, "description":"", "cost": 35 }
```

### عرض شحنات المستخدم (Bearer Token)
```
GET /api/shipments/list.php
```

### تتبع شحنة (عام، بدون توكن)
```
GET /api/shipments/track.php?tracking_number=SHP-20260101-AB12CD
```

### لوحة الأدمن عبر API (Bearer Token لأدمن، أو Header: X-Api-Key لتطبيق خارجي معتمد)
```
GET  /api/admin/shipments.php               → كل الشحنات
GET  /api/admin/shipments.php?status=pending → فلترة بالحالة
PUT  /api/admin/shipments.php  Body: { "id": 5, "status": "in_transit", "note": "..." }
GET  /api/admin/users.php                    → كل المستخدمين (أدمن فقط)
```

مفاتيح الـ `X-Api-Key` تُنشأ من لوحة الأدمن → مفاتيح API.

## ملاحظات أمنية مهمة قبل الإطلاق الفعلي
- غيّر كلمة مرور الأدمن الافتراضية فوراً.
- فعّل HTTPS (شهادة SSL مجانية متوفرة من Hostinger).
- لا تشارك محتوى `config/database.php` مع أي طرف خارجي.
- ضع نسخة احتياطية دورية لقاعدة البيانات من hPanel.
