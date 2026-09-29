<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'الرئيسية - حلول الشحن والتوصيل المتكاملة';
require __DIR__ . '/includes/header_user.php';
?>

<!-- HERO ULTRA 3D -->
<div class="hero-section-ultra text-center mb-5 position-relative">
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  
  <div class="position-relative" style="z-index:2">
    <span class="badge bg-white text-primary fw-bold px-3 py-2 rounded-pill mb-3 shadow-sm">🚚 منصة سعودية 100% لخدمة تجارتك</span>
    <h1 class="hero-title-ultra mb-3"><?= APP_NAME ?></h1>
    <p class="hero-desc-ultra">
      منصتك الذكية لإدارة وتتبع الشحنات داخل الرياض وكافة مدن المملكة — خدمات توصيل، تغليف، وتخزين بربط آلي ولوحة تحكم واحدة تجمعك بأفضل شركاء النجاح اللوجستيين.
    </p>
    <div class="d-flex justify-content-center gap-3 flex-wrap mt-4">
      <a href="/track.php" class="btn-3d-primary fs-5">
        🚀 تتبّع شحنتك الآن
      </a>
      <?php if (isWebLoggedIn()): ?>
        <a href="/user/dashboard.php" class="btn-3d-secondary fs-5">لوحة التحكم</a>
      <?php else: ?>
        <a href="/user/register.php" class="btn-3d-secondary fs-5">أنشئ حساباً مجاناً</a>
      <?php endif; ?>
    </div>

    <!-- بطاقة تتبع عائمة -->
    <div class="mx-auto mt-5 p-3 bg-white rounded-4 shadow-lg d-none d-md-flex align-items-center gap-3" style="max-width:520px; backdrop-filter: blur(10px);">
      <div class="bg-success rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px">📦</div>
      <div class="text-start flex-grow-1">
        <div class="small text-muted">رقم الشحنة #RY-48291</div>
        <div class="fw-bold text-dark">في الطريق - الرياض → جدة <span class="badge bg-success-subtle text-success ms-2">Live</span></div>
        <div class="progress mt-2" style="height:6px"><div class="progress-bar bg-primary" style="width:78%"></div></div>
      </div>
      <div class="fw-bold text-primary">78%</div>
    </div>
  </div>
</div>

<!-- من نحن -->
<div class="row align-items-center mb-5 g-4 reveal">
  <div class="col-lg-6">
    <div class="ps-lg-3">
      <span class="badge bg-primary-subtle text-primary fw-bold px-3 py-2 rounded-pill mb-2">من نحن</span>
      <h2 class="title-ultra fs-1 mb-3">شريكك اللوجستي الموثوق للنمو والتوسع</h2>
      <p class="text-secondary fs-5 lh-lg mb-3">
        نحن شركة توصيل وحلول لوجستية سعودية صاعدة، نجمع بين التوصيل المباشر والسريع داخل الرياض مع الشحن الشامل لكافة مناطق المملكة بالتعاون مع كبرى شركات الشحن.
      </p>
      <p class="text-muted lh-lg mb-0">
        نوفر لأصحاب المتاجر الإلكترونية والأعمال طريقة سهلة ومبتكرة لإدارة الطلبات، التغليف، التجهيز، وتحصيل المبالغ عند الاستلام بسرعة وأمان.
      </p>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="row g-3">
      <div class="col-6"><div class="stat-card-ultra"><div class="stat-number-ultra mb-1">24 ساعة</div><div class="text-muted fw-bold small">التوصيل داخل الرياض</div></div></div>
      <div class="col-6"><div class="stat-card-ultra"><div class="stat-number-ultra mb-1">13 ر.س</div><div class="text-muted fw-bold small">أسعار توصيل تبدأ من</div></div></div>
      <div class="col-6"><div class="stat-card-ultra"><div class="stat-number-ultra mb-1">100%</div><div class="text-muted fw-bold small">تتبّع لحظي ومباشر</div></div></div>
      <div class="col-6"><div class="stat-card-ultra"><div class="stat-number-ultra mb-1">شهر مجاناً</div><div class="text-muted fw-bold small">تخزين للعملاء الجدد</div></div></div>
    </div>
  </div>
</div>

<!-- مميزاتنا -->
<div class="text-center mb-4 reveal">
  <h2 class="title-ultra fs-1">مميزاتنا الاستثنائية</h2>
  <p class="subtitle-ultra">كل ما تحتاجه لإدارة وتوسيع نطاق تجارتك في منصة واحدة</p>
</div>
<div class="row g-4 mb-5 reveal">
  <div class="col-md-4"><div class="card-3d-ultra feature-card-ultra"><span class="feature-badge-ultra">🎉 عرض حصري للعملاء الجدد</span><div class="feature-icon-ultra">📦</div><h4 class="title-ultra fs-4 mb-3">مساحة تخزين مجانية</h4><p class="text-muted small mb-0 lh-lg">احصل على شهر كامل تخزين مجاني لمنتجاتك عند إتمام 20 طلب شحن خلال الفترة لتجربة خدماتنا بدون أي التزامات مبدئية.</p></div></div>
  <div class="col-md-4"><div class="card-3d-ultra feature-card-ultra"><div class="feature-icon-ultra">🚚</div><h4 class="title-ultra fs-4 mb-3">توصيل سريع داخل الرياض</h4><p class="text-muted small mb-0 lh-lg">أسعار تنافسية تبدأ من 13 ريال فقط مع تسليم الشحنة للعميل النهائي خلال 24 ساعة بكل احترافية.</p></div></div>
  <div class="col-md-4"><div class="card-3d-ultra feature-card-ultra"><div class="feature-icon-ultra">🎁</div><h4 class="title-ultra fs-4 mb-3">خدمة التغليف الاحترافي</h4><p class="text-muted small mb-0 lh-lg">نجهز ونغلف شحناتك بمواد تغليف عالية الجودة لتحافظ على سلامة منتجاتك وتصل بمظهر فاخر يعزز علامتك التجارية.</p></div></div>
  <div class="col-md-4"><div class="card-3d-ultra feature-card-ultra"><div class="feature-icon-ultra">🧰</div><h4 class="title-ultra fs-4 mb-3">التجهيز والتعبئة</h4><p class="text-muted small mb-0 lh-lg">أرسل لنا طلبات متجرك ويتولى فريقنا جلب المنتجات من المستودع، تعبئتها، وتجهيزها للتوصيل المباشر.</p></div></div>
  <div class="col-md-4"><div class="card-3d-ultra feature-card-ultra"><div class="feature-icon-ultra">💰</div><h4 class="title-ultra fs-4 mb-3">الدفع عند الاستلام COD</h4><p class="text-muted small mb-0 lh-lg">نحصل قيمة طلباتك من العميل عند التسليم نقدياً أو عبر الشبكة، وتتحول المبالغ مباشرة لمحفظتك الإلكترونية.</p></div></div>
  <div class="col-md-4"><div class="card-3d-ultra feature-card-ultra"><div class="feature-icon-ultra">📍</div><h4 class="title-ultra fs-4 mb-3">تتبع لحظي وربط API</h4><p class="text-muted small mb-0 lh-lg">لوحة تحكم تفاعلية تمكنك من متابعة الشحنات ومشاركة روابط التتبع مع عملائك وتوفير ربط برمجي مباشر مع متجرك.</p></div></div>
</div>

<!-- شركاء النجاح -->
<div class="text-center mb-4 reveal">
  <h2 class="title-ultra fs-2">شركاء النجاح والتغطية اللوجستية</h2>
  <p class="subtitle-ultra">نضمن تغطية جميع مدن المملكة وخارجها عبر الربط مع أكبر الشبكات اللوجستية</p>
</div>
<div class="row g-3 mb-5 justify-content-center reveal">
  <div class="col-6 col-sm-4 col-md-3 col-lg-2"><div class="partner-badge-ultra"><div class="fs-2 mb-1">📮</div><div class="fw-bold text-dark small">سبل (SPL)</div></div></div>
  <div class="col-6 col-sm-4 col-md-3 col-lg-2"><div class="partner-badge-ultra"><div class="fs-2 mb-1">📦</div><div class="fw-bold text-dark small">أرامكس (Aramex)</div></div></div>
  <div class="col-6 col-sm-4 col-md-3 col-lg-2"><div class="partner-badge-ultra"><div class="fs-2 mb-1">🚚</div><div class="fw-bold text-dark small">سمسا (SMSA)</div></div></div>
  <div class="col-6 col-sm-4 col-md-3 col-lg-2"><div class="partner-badge-ultra"><div class="fs-2 mb-1">✈</div><div class="fw-bold text-dark small">دي إتش إل (DHL)</div></div></div>
  <div class="col-6 col-sm-4 col-md-3 col-lg-2"><div class="partner-badge-ultra"><div class="fs-2 mb-1">⚡</div><div class="fw-bold text-dark small">جي أند تي (J&T)</div></div></div>
  <div class="col-6 col-sm-4 col-md-3 col-lg-2"><div class="partner-badge-ultra"><div class="fs-2 mb-1">🛵</div><div class="fw-bold text-dark small">تطبيقات التوصيل السريع</div></div></div>
</div>

<!-- المستودعات -->
<div class="text-center mb-4 reveal">
  <h2 class="title-ultra fs-2">مواقعنا ومستودعاتنا</h2>
  <p class="subtitle-ultra">مستودعات مجهزة بالكامل لتخزين وتجهيز طلباتك داخل الرياض</p>
</div>
<div class="row g-4 mb-5 reveal">
  <div class="col-md-6"><div class="card-3d-ultra p-4 d-flex align-items-center gap-3"><div class="feature-icon-ultra mb-0 flex-shrink-0" style="width:65px;height:65px;font-size:30px">🏢</div><div><h5 class="title-ultra fs-5 mb-1">المستودع الرئيسي — الرياض</h5><p class="text-muted small mb-0">[أضف العنوان هنا] — يستقبل جميع الطلبات ويغطي كامل أحياء الرياض.</p></div></div></div>
  <div class="col-md-6"><div class="card-3d-ultra p-4 d-flex align-items-center gap-3"><div class="feature-icon-ultra mb-0 flex-shrink-0" style="width:65px;height:65px;font-size:30px">🏬</div><div><h5 class="title-ultra fs-5 mb-1">[مستودع إضافي]</h5><p class="text-muted small mb-0">[أضف العنوان التفصيلي للمستودع الإضافي هنا]</p></div></div></div>
</div>

<!-- CTA -->
<div class="hero-section-ultra text-center mb-5 reveal" style="background: linear-gradient(135deg, #0a192f 0%, #0d6efd 100%);">
  <h2 class="hero-title-ultra fs-1 mb-3">جاهز لانطلاقة جديدة مع شحناتك؟</h2>
  <p class="hero-desc-ultra mb-4">أنشئ حسابك اليوم واستفد من عرض التخزين المجاني لأول شهر مع باقة مميزات شحن متكاملة.</p>
  <?php if (isWebLoggedIn()): ?>
    <a href="/user/dashboard.php" class="btn-3d-primary fs-5">الانتقال للوحة التحكم</a>
  <?php else: ?>
    <a href="/user/register.php" class="btn-3d-primary fs-5">أنشئ حسابك مجاناً الآن</a>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer_user.php'; ?>
