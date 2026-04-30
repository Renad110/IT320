<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require 'db_connect.php';
date_default_timezone_set('Asia/Riyadh');
$conn->query("SET time_zone = '+03:00'");

if (!isset($_SESSION['user_id'])) {
header("Location: login.php");
exit;
}

$userId = (int)$_SESSION['user_id'];

/* ===== جلب بيانات المستخدم ===== */
$userStmt = $conn->prepare("SELECT full_name, total_points, profile_image_path FROM users WHERE user_id = ?");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

$fullName = $user['full_name'] ?? 'مستخدم';
$initial  = mb_substr($fullName, 0, 1, 'UTF-8');
$userImg  = $user['profile_image_path'] ?? null;
$points   = (int)($user['total_points'] ?? 0);
$userName = $fullName;
$userInit = $initial;

if      ($points >= 100) { $badgeName='💎 ماسي';   $badgeColor='#0891b2'; $badgeMax=150; }
elseif  ($points >= 70)  { $badgeName='🥇 ذهبي';   $badgeColor='#ca8a04'; $badgeMax=100; }
elseif  ($points >= 40)  { $badgeName='🥈 فضي';    $badgeColor='#6b7280'; $badgeMax=70;  }
else                     { $badgeName='🥉 برونزي'; $badgeColor='#b45309'; $badgeMax=40;  }

$progress = min(100, ($points / $badgeMax) * 100);

/* ===== رسائل ===== */
$message = "";
$msgType = "";

/* ===== إلغاء التسجيل ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_event_id'])) {
$eventId = (int)$_POST['cancel_event_id'];

$check = $conn->prepare("
SELECT attendance_status
FROM registrations
WHERE user_id = ? AND event_id = ?
");
$check->bind_param("ii", $userId, $eventId);
$check->execute();
$reg = $check->get_result()->fetch_assoc();
$check->close();

if ($reg && $reg['attendance_status'] === 'Attended') {
$message = "لا يمكن إلغاء التسجيل بعد تأكيد الحضور";
$msgType = "err";
} else {
$del = $conn->prepare("
DELETE FROM registrations
WHERE user_id = ? AND event_id = ?
");
$del->bind_param("ii", $userId, $eventId);
$del->execute();
$del->close();

$message = "تم إلغاء التسجيل بنجاح";
$msgType = "ok";
}
}

/* ===== تأكيد الحضور بالكود ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_event_id'])) {
$eventId = (int)$_POST['confirm_event_id'];
$enteredCode = trim($_POST['attendance_code'] ?? '');

$stmt = $conn->prepare("
SELECT
r.registration_id,
r.attendance_status,
e.attendance_code,
e.points,
e.certificate_available
FROM registrations r
JOIN events e ON e.event_id = r.event_id
WHERE r.user_id = ? AND e.event_id = ?
");
$stmt->bind_param("ii", $userId, $eventId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
$message = "أنت غير مسجل في هذه الفعالية";
$msgType = "err";
} elseif ($row['attendance_status'] === 'Attended') {
$message = "تم تأكيد حضورك مسبقًا";
$msgType = "warn";
} elseif (!$row['attendance_code']) {
$message = "لم يتم تفعيل كود الحضور بعد";
$msgType = "err";
} elseif ($enteredCode !== $row['attendance_code']) {
$message = "كود الحضور غير صحيح";
$msgType = "err";
} else {
$registrationId = (int)$row['registration_id'];
$eventPoints = (int)$row['points'];

$updateReg = $conn->prepare("
UPDATE registrations
SET attendance_status = 'Attended',
points_awarded = ?
WHERE registration_id = ?
");
$updateReg->bind_param("ii", $eventPoints, $registrationId);
$updateReg->execute();
$updateReg->close();

$updateUser = $conn->prepare("
UPDATE users
SET total_points = total_points + ?
WHERE user_id = ?
");
$updateUser->bind_param("ii", $eventPoints, $userId);
$updateUser->execute();
$updateUser->close();

if ((int)$row['certificate_available'] === 1) {
$checkCert = $conn->prepare("
SELECT certificate_id
FROM certificates
WHERE registration_id = ?
");
$checkCert->bind_param("i", $registrationId);
$checkCert->execute();
$certExists = $checkCert->get_result()->num_rows > 0;
$checkCert->close();

if (!$certExists) {
$insertCert = $conn->prepare("
INSERT INTO certificates (registration_id, issue_date)
VALUES (?, CURDATE())
");
$insertCert->bind_param("i", $registrationId);
$insertCert->execute();
$insertCert->close();
}
}

$message = "تم تأكيد الحضور بنجاح";
$msgType = "ok";
}
}

/* ===== عرض الشهادة في صفحة مستقلة ===== */
if (isset($_GET['certificate'])) {
$registrationId = (int)$_GET['certificate'];

$certStmt = $conn->prepare("
SELECT
u.full_name,
e.title,
e.event_date,
e.volunteer_hours,
e.points,
c.issue_date
FROM certificates c
JOIN registrations r ON r.registration_id = c.registration_id
JOIN users u ON u.user_id = r.user_id
JOIN events e ON e.event_id = r.event_id
WHERE c.registration_id = ? AND r.user_id = ?
LIMIT 1
");
$certStmt->bind_param("ii", $registrationId, $userId);
$certStmt->execute();
$cert = $certStmt->get_result()->fetch_assoc();
$certStmt->close();

if (!$cert) {
echo "Certificate not found";
exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>شهادة مشاركة</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
body{margin:0;font-family:'IBM Plex Sans Arabic',sans-serif;background:linear-gradient(180deg,#f7faf8,#eef6f0);min-height:100vh;padding:32px;color:#1f2937}
.page{min-height:calc(100vh - 64px);display:flex;align-items:center;justify-content:center}
.cert{width:100%;max-width:1100px;background:#fff;border:4px solid #cfe5d4;border-radius:32px;box-shadow:0 24px 60px rgba(25,58,37,.14);overflow:hidden}
.strip{height:18px;background:linear-gradient(90deg,#1e4d25,#4d8f60,#b99a3a,#4d8f60,#1e4d25)}
.header{text-align:center;padding:42px 42px 15px}
.logo{width:115px;margin-bottom:10px}
.title{font-size:2.8rem;color:#1e4d25;margin:0;font-weight:800}
.subtitle{color:#8a948f}
.body{text-align:center;padding:10px 60px 30px}
.name{font-size:3rem;color:#1f5c32;font-weight:800;display:inline-block;border-bottom:4px solid #d8b44c;padding:0 18px 8px}
.event{font-size:1.8rem}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin:28px 0}
.box{background:#f6faf7;border:1px solid #d9e8dc;border-radius:18px;padding:18px}
.label{display:block;color:#7b8580;margin-bottom:8px}
.value{font-weight:700}
.msg{background:#f2f9f4;border:1px dashed #b8d8bf;border-radius:22px;padding:22px;line-height:2}
.footer{display:flex;justify-content:space-between;align-items:end;padding:0 60px 42px;gap:20px}
.line{width:220px;height:2px;background:#1e4d25;margin-bottom:10px}
.badge{background:#edf7ef;color:#245331;border:1px solid #cfe5d4;border-radius:999px;padding:12px 18px;font-weight:700}
.actions{text-align:center;padding-bottom:30px}
button{border:none;border-radius:14px;padding:14px 24px;font-family:inherit;font-size:1rem;font-weight:700;cursor:pointer}
.print{background:#2d6a35;color:#fff}
.close{background:#f3f4f6;color:#374151}
@media print{body{background:#fff;padding:0}.actions{display:none}.cert{box-shadow:none;max-width:100%;border-radius:0}}
</style>
</head>
<body>
<div class="page">
<div class="cert">
<div class="strip"></div>
<div class="header">
<img src="logo.jpg" class="logo" alt="نفل">
<p>منصة نفل البيئية</p>
<h1 class="title">شهادة مشاركة</h1>
<p class="subtitle">Environmental Participation Certificate</p>
</div>
<div class="body">
<p>تتشرف منصة نفل البيئية بمنح هذه الشهادة إلى</p>
<h2 class="name"><?= htmlspecialchars($cert['full_name']) ?></h2>
<p>تقديرًا لمشاركتها الفاعلة في فعالية</p>
<h3 class="event"><?= htmlspecialchars($cert['title']) ?></h3>

<div class="grid">
<div class="box"><span class="label">تاريخ الفعالية</span><span class="value"><?= htmlspecialchars($cert['event_date']) ?></span></div>
<div class="box"><span class="label">الساعات التطوعية</span><span class="value"><?= (int)$cert['volunteer_hours'] ?> ساعات</span></div>
<div class="box"><span class="label">النقاط المكتسبة</span><span class="value"><?= (int)$cert['points'] ?> نقطة</span></div>
</div>

<div class="msg">نشكرك على مساهمتك في دعم المبادرات البيئية والمشاركة في بناء مجتمع أكثر وعيًا واستدامة.</div>
</div>
<div class="footer">
<div>
<div class="line"></div>
<span>إدارة منصة نفل</span>
</div>
<div class="badge">🌿 مشاركة بيئية معتمدة</div>
</div>
<div class="actions">
<button class="print" onclick="window.print()">طباعة الشهادة</button>
<button class="close" onclick="window.close()">إغلاق</button>
</div>
</div>
</div>
<?php exit; ?>
</body>
</html>
<?php
}

/* ===== التاب الحالي ===== */
$tab = $_GET['tab'] ?? 'upcoming';
if (!in_array($tab, array('upcoming', 'past', 'completed'))) {
$tab = 'upcoming';
}

/* ===== جلب فعالياتي ===== */
$sql = "
SELECT
r.registration_id,
r.registration_date,
r.attendance_status,
r.points_awarded,
e.event_id,
e.title,
e.description,
e.category,
e.event_date,
e.start_time,
e.end_time,
e.location,
e.volunteer_hours,
e.points,
e.image_path,
e.attendance_code,
e.certificate_available,
c.certificate_id,
c.issue_date
FROM registrations r
JOIN events e ON e.event_id = r.event_id
LEFT JOIN certificates c ON c.registration_id = r.registration_id
WHERE r.user_id = ?
";

$nowDateTime = date('Y-m-d H:i:s');

if ($tab === 'completed') {
    $sql .= " AND r.attendance_status = 'Attended'";
} elseif ($tab === 'past') {
    $sql .= " 
        AND r.attendance_status = 'Registered'
        AND CONCAT(e.event_date, ' ', e.end_time) < ?
    ";
} else {
    $sql .= " 
        AND r.attendance_status = 'Registered'
        AND CONCAT(e.event_date, ' ', e.end_time) >= ?
    ";
}

$sql .= " ORDER BY e.event_date ASC, e.start_time ASC";

$stmt = $conn->prepare($sql);

if ($tab === 'completed') {
    $stmt->bind_param("i", $userId);
} else {
    $stmt->bind_param("is", $userId, $nowDateTime);
}
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function categoryArabic($category) {
$categories = array(
'Cleaning' => 'تنظيف',
'Planting' => 'زراعة',
'Recycling' => 'إعادة تدوير',
'Awareness' => 'توعية',
'Exhibition' => 'معرض'
);

if (isset($categories[$category])) {
return $categories[$category];
}

return $category;
}

function statusLabel($event) {
if ($event['attendance_status'] === 'Attended') {
return 'مكتملة';
}

if ($event['event_date'] < date('Y-m-d')) {
return 'سابقة';
}

if ($event['event_date'] === date('Y-m-d')) {
return 'جارية';
}

return 'قادمة';
}

function isActiveNow($event) {
    $now = date('Y-m-d H:i:s');
    $startDateTime = $event['event_date'] . ' ' . $event['start_time'];
    $endDateTime = $event['event_date'] . ' ' . $event['end_time'];

    return ($now >= $startDateTime && $now <= $endDateTime);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>نفل - فعالياتي</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="styles.css"/>
<style>
body{font-family:'IBM Plex Sans Arabic',sans-serif;background:#f5f7f0;color:#1f2937;margin:0}
header{background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.08);position:sticky;top:0;z-index:50}
.header-inner{max-width:1200px;margin:0 auto;padding:.75rem 1.5rem;display:flex;align-items:center;justify-content:space-between;gap:1rem}
.brand img{height:52px;filter:drop-shadow(0 2px 8px rgba(45,106,53,.2))}
.header-right{display:flex;align-items:center;gap:.85rem}
.user-chip{display:flex;align-items:center;gap:.55rem;background:#f0fdf4;border:1.5px solid #bbf7d0;padding:.42rem .95rem;border-radius:99px}
.user-avatar{width:30px;height:30px;border-radius:50%;background:#2d6a35;color:white;display:flex;align-items:center;justify-content:center;font-weight:700}
.user-name{font-weight:600;font-size:.9rem;color:#1e4d25}
.badge-widget{display:flex;align-items:center;gap:.55rem;background:#fafafa;border:1px solid #e5e7eb;padding:.45rem .85rem;border-radius:.75rem}
.badge-info{text-align:right}
.badge-name{font-size:.8rem;font-weight:600}
.badge-pts{font-size:.73rem;color:#6b7280;margin-top:.1rem}
.progress-wrap{width:88px;height:5px;background:#e5e7eb;border-radius:99px;overflow:hidden;margin-top:.3rem}
.progress-bar{height:100%;border-radius:99px}
.nav-links{display:flex;gap:.4rem}
.nav-link{display:flex;align-items:center;gap:.38rem;text-decoration:none;color:#374151;font-size:.86rem;font-weight:500;padding:.42rem .85rem;border-radius:.55rem;border:1.5px solid transparent}
.nav-link:hover,.nav-link.active{background:#f0fdf4;color:#2d6a35;border-color:#bbf7d0}
.btn-logout{color:#dc2626;text-decoration:none;font-size:.86rem;font-weight:600}
.hero{background:linear-gradient(135deg,#2d6a35,#1a4220);color:#fff;padding:2.5rem 1.5rem}
.hero-inner{max-width:1200px;margin:0 auto}
.hero-greeting{font-size:.92rem;color:#86efac;margin-bottom:.35rem}
.hero h2{font-size:2rem;margin:.2rem 0}
.hero p{color:#bbf7d0}
.main{max-width:1200px;margin:0 auto;padding:1.75rem 1.5rem 3rem}
.alert{padding:.85rem 1rem;border-radius:.7rem;margin-bottom:1rem;text-align:center;font-size:.92rem}
.alert-ok{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
.alert-warn{background:#fef9c3;color:#854d0e;border:1px solid #fde68a}
.alert-err{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
.tabs{display:flex;gap:.5rem;margin-bottom:1.4rem;flex-wrap:wrap}
.tab-btn{padding:.65rem 1.2rem;border-radius:999px;background:#fff;color:#374151;text-decoration:none;font-weight:700;border:1.5px solid #e5e7eb}
.tab-btn.active{background:#2d6a35;color:#fff;border-color:#2d6a35}
.events-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(295px,1fr));gap:1.3rem}
.event-card{background:#fff;border-radius:.9rem;box-shadow:0 2px 8px rgba(0,0,0,.07);overflow:hidden}
.event-img{width:100%;height:182px;object-fit:cover}
.event-body{padding:1.1rem}
.event-meta{display:flex;justify-content:space-between;align-items:center;margin-bottom:.65rem}
.status-tag{background:#dcfce7;color:#166534;padding:.2rem .7rem;border-radius:99px;font-size:.78rem;font-weight:600}
.pts-badge{color:#ca8a04;font-size:.8rem;font-weight:700}
.event-title{font-size:1rem;font-weight:700;margin-bottom:.75rem}
.event-details{display:flex;flex-direction:column;gap:.35rem;color:#4b5563;font-size:.83rem}
.code-box{background:#f9fafb;border:1px solid #e5e7eb;border-radius:.75rem;padding:1rem;margin-top:1rem}
.code-box h4{margin:0 0 .6rem;color:#1e4d25}
.code-input{width:100%;padding:.7rem;border:1.5px solid #d1d5db;border-radius:.6rem;font-family:inherit;margin-bottom:.7rem}
.actions{display:flex;gap:.5rem;margin-top:.8rem;flex-wrap:wrap}
.btn{border:none;border-radius:.6rem;padding:.65rem 1rem;font-family:inherit;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block;text-align:center}
.btn-primary{background:#2d6a35;color:white}
.btn-primary:hover{background:#1e4d25}
.btn-danger{background:#fef2f2;color:#dc2626}
.btn-soft{background:#f0fdf4;color:#166534}
.empty-state{text-align:center;background:#fff;border-radius:1rem;padding:2.5rem;color:#6b7280}
@media(max-width:768px){.badge-widget{display:none}.user-name{display:none}.nav-link span{display:none}}
</style>
</head>
<body>

<header>
  <div class="header-inner">
    <a href="home.php" class="brand"><img src="logo.jpg" alt="نفل"/></a>
    <div class="header-right">
      <div class="user-chip">
        <div class="user-avatar">
          <?php if ($userImg && file_exists($userImg)): ?>
            <img src="<?= htmlspecialchars($userImg) ?>" alt="<?= htmlspecialchars($fullName) ?>"/>
          <?php else: ?>
            <?= htmlspecialchars($initial) ?>
          <?php endif; ?>
        </div>
        <span class="user-name"><?= htmlspecialchars($fullName) ?></span>
      </div>
      <div class="badge-widget">
        <div class="badge-info">
          <div class="badge-name" style="color:<?= $badgeColor ?>"><?= $badgeName ?></div>
          <div class="badge-pts"><?= $points ?> / <?= $badgeMax ?> نقطة</div>
          <div class="progress-wrap">
            <div class="progress-bar" style="width:<?= $progress ?>%;background:<?= $badgeColor ?>"></div>
          </div>
        </div>
      </div>
      <div class="nav-links">
        <a href="home.php" class="nav-link">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          <span>الرئيسية</span>
        </a>
        <a href="MyEvents.php" class="nav-link active">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 16l2 2 4-4"/></svg>
          <span>فعالياتي</span>
        </a>
        <a href="organized-events.php" class="nav-link">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          <span>فعالياتي المنظمة</span>
        </a>
      </div>
      <a href="logout.php" class="btn-logout">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        خروج
      </a>
    </div>
  </div>
</header>

<section class="hero">
<div class="hero-inner">
<div class="hero-greeting">مرحباً يا <?= htmlspecialchars(explode(' ', $userName)[0]) ?> 👋</div>
<h2>فعالياتي</h2>
<p>تابع الفعاليات المسجل فيها، أكد حضورك، واستعرض شهاداتك</p>
</div>
</section>

<main class="main">

<?php if ($message): ?>
<div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="tabs">
<a href="MyEvents.php?tab=upcoming" class="tab-btn <?= $tab === 'upcoming' ? 'active' : '' ?>">القادمة</a>
<a href="MyEvents.php?tab=past" class="tab-btn <?= $tab === 'past' ? 'active' : '' ?>">السابقة</a>
<a href="MyEvents.php?tab=completed" class="tab-btn <?= $tab === 'completed' ? 'active' : '' ?>">المكتملة</a>
</div>

<?php if (empty($events)): ?>
<div class="empty-state">
<h3>ما عندك فعاليات هنا</h3>
<p>سجل في فعالية من الصفحة الرئيسية وبتظهر لك هنا</p>
</div>
<?php else: ?>
<div class="events-grid">
<?php foreach ($events as $event): ?>
<div class="event-card">
<img class="event-img" src="<?= htmlspecialchars($event['image_path'] ?: 'https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?w=900&q=80') ?>" alt="<?= htmlspecialchars($event['title']) ?>">

<div class="event-body">
<div class="event-meta">
<span class="status-tag"><?= statusLabel($event) ?></span>
<span class="pts-badge"><?= (int)$event['points'] ?> نقطة</span>
</div>

<div class="event-title"><?= htmlspecialchars($event['title']) ?></div>

<div class="event-details">
<div>📅 <?= htmlspecialchars($event['event_date']) ?></div>
<div>🕒 <?= substr($event['start_time'], 0, 5) ?> - <?= substr($event['end_time'], 0, 5) ?></div>
<div>📍 <?= htmlspecialchars($event['location']) ?></div>
<div>⏱ <?= (int)$event['volunteer_hours'] ?> ساعات تطوعية</div>
<div>🏷 <?= htmlspecialchars(categoryArabic($event['category'])) ?></div>
</div>

<?php if ($event['attendance_status'] === 'Attended'): ?>

<?php if ($event['certificate_id'] && (int)$event['certificate_available'] === 1): ?>
<div class="actions">
<a class="btn btn-soft" target="_blank" href="MyEvents.php?certificate=<?= (int)$event['registration_id'] ?>">
عرض الشهادة
</a>
</div>
<?php else: ?>
<div class="code-box">
<p>تم تأكيد حضورك، لكن الشهادة غير متاحة لهذه الفعالية.</p>
</div>
<?php endif; ?>

<?php elseif (isActiveNow($event)): ?>

<?php if ($event['attendance_code']): ?>
<form method="POST" class="code-box">
<h4>تأكيد الحضور</h4>
<input type="hidden" name="confirm_event_id" value="<?= (int)$event['event_id'] ?>">
<input class="code-input" type="text" name="attendance_code" placeholder="أدخل كود الحضور" required>
<button class="btn btn-primary" type="submit">تأكيد الحضور</button>
</form>
<?php else: ?>
<div class="code-box">
<h4>الحضور</h4>
<p>بانتظار تفعيل كود الحضور من المنظم.</p>
</div>
<?php endif; ?>

<div class="actions">
<button class="btn btn-danger" type="button" onclick="openCancelModal(<?= (int)$event['event_id'] ?>)">إلغاء التسجيل</button>
</div>

<?php elseif ($event['event_date'] > date('Y-m-d')): ?>

<div class="code-box">
<h4>الحضور</h4>
<p>لم تبدأ الفعالية بعد.</p>
</div>

<div class="actions">
<button class="btn btn-danger" type="button" onclick="openCancelModal(<?= (int)$event['event_id'] ?>)">إلغاء التسجيل</button>
</div>

<?php else: ?>

<div class="code-box">
<h4>الحضور</h4>
<p style="color:#dc2626;">انتهت الفعالية ولم يتم تأكيد الحضور.</p>
</div>

<?php endif; ?>

</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

</main>

<!-- Modal تأكيد إلغاء التسجيل -->
<div id="cancelModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:1.1rem;padding:2rem;max-width:360px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.15);">
    <div style="width:60px;height:60px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
    </div>
    <div style="font-size:1.1rem;font-weight:700;color:#1f2937;margin-bottom:.5rem;">إلغاء التسجيل</div>
    <div style="color:#6b7280;font-size:.88rem;line-height:1.6;margin-bottom:1.4rem;">هل أنتِ متأكد من إلغاء تسجيلك في هذه الفعالية؟</div>
    <div style="display:flex;gap:.7rem;">
      <button onclick="closeCancelModal()" style="flex:1;padding:.7rem;background:#f3f4f6;color:#374151;border:none;border-radius:.65rem;font-family:inherit;font-size:.9rem;font-weight:600;cursor:pointer;">تراجع</button>
      <button onclick="submitCancel()" style="flex:1;padding:.7rem;background:#dc2626;color:#fff;border:none;border-radius:.65rem;font-family:inherit;font-size:.9rem;font-weight:700;cursor:pointer;">إلغاء التسجيل</button>
    </div>
  </div>
</div>
<form id="cancelForm" method="POST" style="display:none;">
  <input type="hidden" name="cancel_event_id" id="cancelEventId"/>
</form>

<script>
function openCancelModal(eventId) {
  document.getElementById('cancelEventId').value = eventId;
  const m = document.getElementById('cancelModal');
  m.style.display = 'flex';
}
function closeCancelModal() {
  document.getElementById('cancelModal').style.display = 'none';
}
function submitCancel() {
  document.getElementById('cancelForm').submit();
}
document.getElementById('cancelModal').addEventListener('click', function(e){
  if (e.target === this) closeCancelModal();
});
</script>

</body>
</html>
