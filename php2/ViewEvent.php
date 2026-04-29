<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid     = $_SESSION['user_id'];
$eventId = (int)($_GET['id'] ?? 1);

// التسجيل في الفعالية عند الضغط على الزر
$message = '';
$msgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // فحص بيانات الفعالية: التاريخ + المنظم
    $checkEvent = $conn->prepare("SELECT event_date, created_by_user_id FROM events WHERE event_id = ?");
    $checkEvent->bind_param("i", $eventId);
    $checkEvent->execute();
    $evRow = $checkEvent->get_result()->fetch_assoc();
    $checkEvent->close();

    $eventDate = $evRow['event_date'] ?? null;
    $organizerId = (int)($evRow['created_by_user_id'] ?? 0);

    if ($eventDate && $eventDate < date('Y-m-d')) {
        $message = '❌ لا يمكن التسجيل، هذه الفعالية انتهت';
        $msgType = 'err';
    } elseif ($organizerId === (int)$uid) {
        // ✅ منع المنظم من التسجيل في فعاليته
        $message = '❌ لا يمكنك التسجيل في فعاليتك الخاصة';
        $msgType = 'err';
    } else {
        // تأكد إنه ما هو مسجل سابقاً
        $check = $conn->prepare("SELECT registration_id FROM registrations WHERE user_id=? AND event_id=?");
        $check->bind_param("ii", $uid, $eventId);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = 'أنت مسجل في هذه الفعالية بالفعل';
            $msgType = 'warn';
        } else {
            $today = date('Y-m-d');
            $ins = $conn->prepare("INSERT INTO registrations (user_id, event_id, registration_date) VALUES (?, ?, ?)");
            $ins->bind_param("iis", $uid, $eventId, $today);
            if ($ins->execute()) {
                $message = '✅ تم تسجيلك في الفعالية بنجاح!';
                $msgType = 'ok';
            } else {
                $message = 'حدث خطأ أثناء التسجيل';
                $msgType = 'err';
            }
            $ins->close();
        }
        $check->close();
    }
}

// جلب بيانات الفعالية + المنظم + عدد المشاركين
$stmt = $conn->prepare("
    SELECT e.*, u.full_name AS organizer_name, u.profile_image_path AS organizer_image,
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id) AS participants
    FROM events e
    JOIN users u ON e.created_by_user_id = u.user_id
    WHERE e.event_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $eventId);
$stmt->execute();
$ev = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ev) {
    echo "<p style='text-align:center;padding:3rem;font-family:sans-serif'>الفعالية غير موجودة</p>";
    exit;
}

// تحقق إذا المستخدم مسجل
$regCheck = $conn->prepare("SELECT registration_id FROM registrations WHERE user_id=? AND event_id=?");
$regCheck->bind_param("ii", $uid, $eventId);
$regCheck->execute();
$alreadyRegistered = $regCheck->get_result()->num_rows > 0;
$regCheck->close();

// ✅ تحقق إذا الفعالية منتهية
$isExpired = ($ev['event_date'] < date('Y-m-d'));

// ✅ تحقق إذا المستخدم هو منظم الفعالية
$isOrganizer = ((int)$ev['created_by_user_id'] === (int)$uid);

// خريطة التصنيفات والفئة المستهدفة
$catMapAr = ['Cleaning'=>'تنظيف','Planting'=>'زراعة','Recycling'=>'إعادة تدوير','Awareness'=>'توعية','Exhibition'=>'معرض'];
$ageMapAr = ['Children'=>'أطفال','Teens'=>'مراهقين','Adults'=>'بالغين','All'=>'الكل'];

$catAr   = $catMapAr[$ev['category']]         ?? $ev['category'];
$ageAr   = $ageMapAr[$ev['target_age_group']] ?? $ev['target_age_group'];
$orgName = $ev['organizer_name'];
$orgImg  = $ev['organizer_image'];
$initials = mb_substr(trim($orgName), 0, 1, 'UTF-8');

$userInitial = mb_substr($_SESSION['full_name'], 0, 1, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>نفل - <?= htmlspecialchars($ev['title']) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'IBM Plex Sans Arabic', sans-serif; background: #f5f7f0; min-height: 100vh; color: #1f2937; }

    header { background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.08); position: sticky; top: 0; z-index: 50; }
    .header-inner { max-width: 1200px; margin: 0 auto; padding: .75rem 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
    .brand { display:flex; align-items:center; gap:.5rem; text-decoration:none; }
    .brand img { height: 52px; width: auto; object-fit: contain; filter: drop-shadow(0 2px 8px rgba(45,106,53,0.2)); }
    .header-left { display:flex; align-items:center; gap:.75rem; }
    .user-chip { display:flex; align-items:center; gap:.5rem; background:#f0fdf4; border:1.5px solid #bbf7d0; padding:.38rem .85rem; border-radius:99px; }
    .user-avatar { width:28px; height:28px; border-radius:50%; background:#2d6a35; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.83rem; }
    .user-name { font-weight:600; font-size:.87rem; color:#1e4d25; }
    .btn-back { display:flex; align-items:center; gap:.3rem; color:#2d6a35; background:none; border:none; font-family:inherit; font-size:.88rem; font-weight:500; cursor:pointer; padding:.4rem .8rem; border-radius:.55rem; text-decoration:none; transition:background .2s; }
    .btn-back:hover { background:#f0fdf4; }

    .content { max-width:900px; margin:0 auto; padding:2rem 1.5rem 3rem; }
    .detail-card { background:#fff; border-radius:1rem; box-shadow:0 4px 20px rgba(0,0,0,0.08); overflow:hidden; animation:fadeUp .45s ease; }
    @keyframes fadeUp { from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);} }
    .hero-img { width:100%; height:300px; object-fit:cover; display:block; }
    .card-body { padding:2rem; }

    .top-meta { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.1rem; flex-wrap:wrap; gap:.6rem; }
    .cat-tag { background:#dcfce7; color:#166534; padding:.25rem .95rem; border-radius:99px; font-size:.83rem; font-weight:500; }
    .pts-display { display:flex; align-items:center; gap:.38rem; color:#ca8a04; font-size:.97rem; font-weight:600; }

    .event-title { font-size:1.6rem; font-weight:700; color:#1f2937; margin-bottom:1.5rem; line-height:1.4; }

    .info-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:.9rem; background:#f5f7f0; border-radius:.75rem; padding:1.1rem; margin-bottom:1.75rem; }
    .info-item { display:flex; align-items:center; gap:.65rem; }
    .info-icon { width:38px; height:38px; background:#dcfce7; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .info-icon svg { color:#2d6a35; }
    .info-label { font-size:.73rem; color:#6b7280; margin-bottom:.1rem; }
    .info-value { font-size:.88rem; color:#1f2937; font-weight:500; }

    .organizer-avatar { width:38px; height:38px; border-radius:50%; object-fit:cover; flex-shrink:0; border:2px solid #dcfce7; }
    .organizer-avatar-fallback { width:38px; height:38px; border-radius:50%; background:#2d6a35; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.85rem; flex-shrink:0; }

    .section-title { font-size:1.2rem; font-weight:700; color:#1f2937; margin-bottom:.8rem; }
    .event-desc { color:#4b5563; line-height:1.85; font-size:.97rem; margin-bottom:1.75rem; }

    .benefits-box { background:#fefce8; border:1.5px solid #fde68a; border-radius:.75rem; padding:1.1rem 1.3rem; margin-bottom:1.75rem; }
    .benefits-box h3 { font-size:.93rem; font-weight:600; color:#854d0e; margin-bottom:.65rem; }
    .benefits-list { list-style:none; display:flex; flex-direction:column; gap:.4rem; }
    .benefits-list li { display:flex; align-items:center; gap:.48rem; color:#4b5563; font-size:.9rem; }
    .dot { width:6px; height:6px; background:#ca8a04; border-radius:50%; flex-shrink:0; }

    .btn-register { display:block; width:100%; padding:.95rem; background:#2d6a35; color:#fff; border:none; border-radius:.75rem; font-family:inherit; font-size:1.05rem; font-weight:700; cursor:pointer; text-align:center; transition:background .2s,transform .1s; }
    .btn-register:hover { background:#1e4d25; }
    .btn-register:active { transform:scale(0.99); }
    .btn-register.disabled { background:#9ca3af; cursor:not-allowed; }

    .alert { padding:.85rem 1.1rem; border-radius:.7rem; margin-bottom:1rem; font-size:.92rem; text-align:center; }
    .alert-ok   { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
    .alert-warn { background:#fef9c3; color:#854d0e; border:1px solid #fde68a; }
    .alert-err  { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }

    /* بانر الفعاليات المنتهية */
    .expired-banner {
      background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
      color:#fff; padding:1rem 1.4rem; border-radius:.8rem;
      margin-bottom:1.2rem; display:flex; align-items:center; gap:.7rem;
      box-shadow:0 4px 12px rgba(0,0,0,0.1);
    }
    .expired-banner .icon { font-size:1.5rem; }
    .expired-banner .text { flex:1; }
    .expired-banner .text strong { display:block; font-size:1rem; margin-bottom:.15rem; }
    .expired-banner .text small { font-size:.85rem; opacity:0.9; }

    .readonly-notice {
      background:#f3f4f6; color:#4b5563; padding:.95rem;
      border-radius:.75rem; text-align:center; font-weight:500;
      border:1.5px dashed #9ca3af;
    }

    .organizer-notice {
      background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
      color:#854d0e; padding:.95rem;
      border-radius:.75rem; text-align:center; font-weight:600;
      border:1.5px solid #f59e0b;
      box-shadow: 0 2px 8px rgba(245,158,11,0.15);
    }

    @media(max-width:640px){ .info-grid{grid-template-columns:1fr;} .hero-img{height:200px;} .card-body{padding:1.2rem;} .event-title{font-size:1.3rem;} .user-name{display:none;} }
  </style>
</head>
<body>

<header>
  <div class="header-inner">
    <a href="home.php" class="brand">
      <img src="logo.jpg" alt="نفل" />
    </a>
    <div class="header-left">
      <div class="user-chip">
        <div class="user-avatar"><?= htmlspecialchars($userInitial) ?></div>
        <span class="user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></span>
      </div>
      <a href="home.php" class="btn-back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        الرئيسية
      </a>
    </div>
  </div>
</header>

<main class="content">
  <?php if ($message): ?>
    <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <?php if ($isExpired): ?>
    <div class="expired-banner">
      <span class="icon">⏰</span>
      <div class="text">
        <strong>هذه الفعالية انتهت</strong>
        <small>تاريخ الفعالية كان <?= htmlspecialchars($ev['event_date']) ?> — التفاصيل معروضة للقراءة فقط</small>
      </div>
    </div>
  <?php endif; ?>

  <div class="detail-card">
    <img class="hero-img" src="<?= htmlspecialchars($ev['image_path'] ?: 'https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?w=900&q=80') ?>" alt="<?= htmlspecialchars($ev['title']) ?>" />
    <div class="card-body">
      <div class="top-meta">
        <span class="cat-tag"><?= htmlspecialchars($catAr) ?></span>
        <span class="pts-display">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          <?= (int)$ev['points'] ?> نقطة
        </span>
      </div>

      <h1 class="event-title"><?= htmlspecialchars($ev['title']) ?></h1>

      <div class="info-grid">
        <div class="info-item">
          <div class="info-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
          <div><div class="info-label">التاريخ</div><div class="info-value"><?= htmlspecialchars($ev['event_date']) ?></div></div>
        </div>
        <div class="info-item">
          <div class="info-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
          <div><div class="info-label">وقت البداية – النهاية</div><div class="info-value"><?= substr($ev['start_time'],0,5) ?> – <?= substr($ev['end_time'],0,5) ?></div></div>
        </div>
        <div class="info-item">
          <div class="info-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
          <div><div class="info-label">الموقع</div><div class="info-value"><?= htmlspecialchars($ev['location']) ?></div></div>
        </div>
        <div class="info-item">
          <div class="info-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
          <div><div class="info-label">المشاركون</div><div class="info-value"><?= (int)$ev['participants'] ?> مشارك</div></div>
        </div>
        <div class="info-item">
          <div class="info-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.4 2 2 0 0 1 3.6 1.22h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 8.8a16 16 0 0 0 6.29 6.29l.95-.95a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg></div>
          <div><div class="info-label">رقم التواصل</div><div class="info-value"><?= htmlspecialchars($ev['contact_number']) ?></div></div>
        </div>
        <div class="info-item">
          <div class="info-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
          <div><div class="info-label">ساعات التطوع</div><div class="info-value"><?= (int)$ev['volunteer_hours'] ?> ساعات</div></div>
        </div>
        <div class="info-item">
          <div class="info-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
          <div><div class="info-label">الفئة المستهدفة</div><div class="info-value"><?= htmlspecialchars($ageAr) ?></div></div>
        </div>
        <div class="info-item">
          <?php if ($orgImg): ?>
            <img class="organizer-avatar" src="<?= htmlspecialchars($orgImg) ?>" alt="<?= htmlspecialchars($orgName) ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';" />
            <div class="organizer-avatar-fallback" style="display:none"><?= htmlspecialchars($initials) ?></div>
          <?php else: ?>
            <div class="organizer-avatar-fallback"><?= htmlspecialchars($initials) ?></div>
          <?php endif; ?>
          <div><div class="info-label">المنظم</div><div class="info-value"><?= htmlspecialchars($orgName) ?></div></div>
        </div>
      </div>

      <div class="section-title">عن الفعالية</div>
      <p class="event-desc"><?= nl2br(htmlspecialchars($ev['description'])) ?></p>

      <?php if (!$isExpired && !$isOrganizer): ?>
        <div class="benefits-box">
          <h3>💡 ماذا تحصل عند التسجيل؟</h3>
          <ul class="benefits-list">
            <li><span class="dot"></span><?= (int)$ev['points'] ?> نقطة تضاف لرصيدك</li>
            <li><span class="dot"></span>شهادة مشاركة رقمية</li>
            <li><span class="dot"></span>فرصة للتواصل مع مجتمع بيئي فعال</li>
            <li><span class="dot"></span>المساهمة في حماية البيئة</li>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($isExpired): ?>
        <div class="readonly-notice">
          🔒 هذه الفعالية انتهت — العرض للقراءة فقط
        </div>
      <?php elseif ($isOrganizer): ?>
        <div class="organizer-notice">
           أنت منظم هذه الفعالية — لا يمكنك التسجيل فيها
        </div>
      <?php elseif ($alreadyRegistered): ?>
        <button class="btn-register disabled" disabled>أنت مسجل في هذه الفعالية</button>
      <?php else: ?>
        <form method="POST" action="ViewEvent.php?id=<?= $eventId ?>">
          <button type="submit" name="register" class="btn-register">سجّل في الفعالية</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</main>

</body>
</html>
