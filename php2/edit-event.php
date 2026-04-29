<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId  = (int)$_SESSION['user_id'];
$eventId = (int)($_GET['id'] ?? 0);

if (!$eventId) {
    header("Location: organized-events.php");
    exit();
}

// جلب الفعالية — يجب أن تكون Pending وتابعة للمستخدم
$stmt = $conn->prepare(
    "SELECT * FROM events WHERE event_id = ? AND created_by_user_id = ? AND status = 'Pending'"
);
if (!$stmt) {
    header("Location: organized-events.php");
    exit();
}
$stmt->bind_param("ii", $eventId, $userId);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    header("Location: organized-events.php");
    exit();
}

// ======= جلب بيانات المستخدم للهيدر =======
$userQ = $conn->prepare("SELECT full_name, total_points, profile_image_path FROM users WHERE user_id = ?");
if (!$userQ) { die('خطأ في قاعدة البيانات'); }
$userQ->bind_param("i", $userId);
$userQ->execute();
$userRow  = $userQ->get_result()->fetch_assoc();
$userQ->close();

$fullName = $userRow['full_name']          ?? 'مستخدم';
$initial  = mb_substr($fullName, 0, 1, 'UTF-8');
$userImg  = $userRow['profile_image_path'] ?? null;
$points   = (int)($userRow['total_points'] ?? 0);

if      ($points >= 100) { $badgeName='💎 ماسي';   $badgeColor='#0891b2'; $badgeMax=150; }
elseif  ($points >= 70)  { $badgeName='🥇 ذهبي';   $badgeColor='#ca8a04'; $badgeMax=100; }
elseif  ($points >= 40)  { $badgeName='🥈 فضي';    $badgeColor='#6b7280'; $badgeMax=70;  }
else                     { $badgeName='🥉 برونزي'; $badgeColor='#b45309'; $badgeMax=40;  }
$progress = min(100, ($points / $badgeMax) * 100);

$errors = [];

// ======= معالجة الحفظ =======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title']       ?? '');
    $category    = $_POST['category']         ?? '';
    $description = trim($_POST['description'] ?? '');
    $eventDate   = $_POST['event_date']       ?? '';
    $startTime   = $_POST['start_time']       ?? '';
    $endTime     = $_POST['end_time']         ?? '';
    $location    = trim($_POST['location']    ?? '');
    $contact     = trim($_POST['contact']     ?? '');
    $targetAge   = $_POST['target_age']       ?? '';
    $pts         = (int)($_POST['points']     ?? 0);
    $certAvail   = isset($_POST['cert'])      ? 1 : 0;

    $volunteerHours = 0;
    if ($startTime && $endTime && $endTime > $startTime) {
        $s = new DateTime("2000-01-01 $startTime");
        $e = new DateTime("2000-01-01 $endTime");
        $volunteerHours = round($e->diff($s)->h + $e->diff($s)->i / 60, 1);
    }

    // validation كامل
    if (!$title)       $errors[] = 'العنوان مطلوب';
    if (!$category)    $errors[] = 'التصنيف مطلوب';
    if (!$description) $errors[] = 'وصف الفعالية مطلوب';
    if (!$eventDate)   $errors[] = 'التاريخ مطلوب';
    if (!$location)    $errors[] = 'الموقع مطلوب';

    if (empty($errors)) {
        $upd = $conn->prepare(
            "UPDATE events SET
                title = ?, category = ?, description = ?,
                event_date = ?, start_time = ?, end_time = ?,
                location = ?, contact_number = ?, target_age_group = ?,
                points = ?, volunteer_hours = ?, certificate_available = ?
             WHERE event_id = ? AND created_by_user_id = ? AND status = 'Pending'"
        );
        if ($upd) {
            $upd->bind_param(
                "sssssssssidiii",
                $title, $category, $description,
                $eventDate, $startTime, $endTime,
                $location, $contact, $targetAge,
                $pts, $volunteerHours, $certAvail,
                $eventId, $userId
            );
            if ($upd->execute()) {
                header("Location: organized-events.php?saved=1");
                exit();
            } else {
                $errors[] = 'حدث خطأ أثناء الحفظ، حاولي مجدداً.';
            }
            $upd->close();
        } else {
            $errors[] = 'خطأ في قاعدة البيانات.';
        }
    }
    // تحديث $event بالقيم الجديدة لعرضها في الفورم
    $event = array_merge($event, [
        'title'       => $title,   'category'    => $category,
        'description' => $description, 'event_date' => $eventDate,
        'start_time'  => $startTime,   'end_time'   => $endTime,
        'location'    => $location,    'contact_number' => $contact,
        'target_age_group' => $targetAge, 'points'  => $pts,
        'certificate_available' => $certAvail,
    ]);
}

$categories = [
    'Cleaning'   => '🧹 تنظيف',
    'Planting'   => '🌱 زراعة',
    'Recycling'  => '♻️ إعادة تدوير',
    'Awareness'  => '📢 توعية',
    'Exhibition' => '🎪 معرض',
];
$ageGroups = [
    'Children' => 'أطفال',
    'Teens'    => 'مراهقون',
    'Adults'   => 'بالغون',
    'All'      => 'الجميع',
];
$pointsOptions = [5, 8, 10, 15, 20];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>نفل - تعديل الفعالية</title>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="styles.css"/>
  <style>
    .form-card { background:#fff; border-radius:1rem; box-shadow:0 2px 12px rgba(0,0,0,0.07); overflow:hidden; animation:fadeUp .45s ease; }
    @keyframes fadeUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
    .form-body { padding:2rem; }
    .pending-notice { background:#fef9c3; border:1.5px solid #fde68a; border-radius:.75rem; padding:1rem 1.2rem; margin-bottom:1.5rem; display:flex; align-items:flex-start; gap:.75rem; }
    .pending-notice-icon { font-size:1.3rem; flex-shrink:0; }
    .pending-notice-text { font-size:.86rem; color:#4b5563; line-height:1.65; }
    .pending-notice-text strong { color:#854d0e; }
    .error-box { background:#fee2e2; border:1.5px solid #fecaca; border-radius:.75rem; padding:.9rem 1.2rem; margin-bottom:1.2rem; font-size:.87rem; color:#991b1b; }
    .form-group { margin-bottom:1.1rem; }
    label { display:block; color:#374151; font-weight:600; font-size:.88rem; margin-bottom:.4rem; }
    label span.req { color:#dc2626; }
    input[type="text"], input[type="date"], input[type="time"],
    input[type="tel"], input[type="number"], select, textarea {
      width:100%; padding:.78rem 1rem; border:1.5px solid #d1d5db; border-radius:.65rem;
      font-family:inherit; font-size:.93rem; color:#111; background:#fafafa;
      outline:none; transition:border-color .2s,box-shadow .2s; appearance:none;
    }
    select { background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:left 1rem center; padding-left:2.5rem; }
    input:focus, select:focus, textarea:focus { border-color:#2d6a35; box-shadow:0 0 0 3px rgba(45,106,53,0.1); background:#fff; }
    textarea { resize:vertical; min-height:110px; line-height:1.7; }
    .input-hint { font-size:.77rem; color:#9ca3af; margin-top:.3rem; }
    .form-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
    .form-row-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem; }
    .section-divider { font-size:.8rem; font-weight:700; color:#9ca3af; margin:1.5rem 0 1rem; display:flex; align-items:center; gap:.6rem; }
    .section-divider::after { content:''; flex:1; height:1px; background:#f3f4f6; }
    .toggle-row { display:flex; align-items:center; justify-content:space-between; padding:.85rem 1rem; background:#f5f7f0; border-radius:.65rem; }
    .toggle-label { font-size:.9rem; font-weight:500; color:#374151; }
    .toggle-sub   { font-size:.78rem; color:#6b7280; margin-top:.1rem; }
    .switch { position:relative; width:44px; height:24px; flex-shrink:0; }
    .switch input { opacity:0; width:0; height:0; }
    .slider { position:absolute; inset:0; background:#d1d5db; border-radius:99px; cursor:pointer; transition:.3s; }
    .slider::before { content:''; position:absolute; width:18px; height:18px; background:#fff; border-radius:50%; top:3px; right:3px; transition:.3s; box-shadow:0 1px 3px rgba(0,0,0,0.2); }
    .switch input:checked + .slider { background:#2d6a35; }
    .switch input:checked + .slider::before { transform:translateX(-20px); }
    .pts-opt { padding:.5rem 1.2rem; border-radius:99px; border:1.5px solid #d1d5db; background:#fff; font-family:inherit; font-size:.88rem; font-weight:600; cursor:pointer; color:#374151; transition:all .2s; }
    .pts-opt:hover  { border-color:#2d6a35; color:#2d6a35; }
    .pts-opt.active { background:#2d6a35; color:#fff; border-color:#2d6a35; }
    .form-footer { display:flex; gap:.8rem; justify-content:flex-end; align-items:center; padding:1.3rem 2rem; border-top:1px solid #f3f4f6; background:#fafafa; flex-wrap:wrap; }
    .btn-cancel-page { padding:.72rem 1.5rem; background:#f3f4f6; color:#374151; border:none; border-radius:.65rem; font-family:inherit; font-size:.92rem; font-weight:600; cursor:pointer; transition:background .2s; text-decoration:none; display:inline-flex; align-items:center; gap:.4rem; }
    .btn-cancel-page:hover { background:#e5e7eb; }
    .btn-save { padding:.72rem 2rem; background:#2d6a35; color:#fff; border:none; border-radius:.65rem; font-family:inherit; font-size:.92rem; font-weight:700; cursor:pointer; transition:background .2s,transform .1s; display:inline-flex; align-items:center; gap:.4rem; }
    .btn-save:hover  { background:#1e4d25; }
    .btn-save:active { transform:scale(.98); }
    .nav-links { display:flex; align-items:center; gap:.4rem; }
    .nav-link { display:flex; align-items:center; gap:.38rem; text-decoration:none; color:#374151; font-size:.86rem; font-weight:500; padding:.42rem .85rem; border-radius:.55rem; transition:background .2s,color .2s,border-color .2s; white-space:nowrap; border:1.5px solid transparent; }
    .nav-link:hover, .nav-link.active { background:#f0fdf4; color:#2d6a35; border-color:#bbf7d0; }
    .nav-link svg { flex-shrink:0; }
    .badge-widget { display:flex; align-items:center; gap:.55rem; background:#fafafa; border:1px solid #e5e7eb; padding:.45rem .85rem; border-radius:.75rem; }
    .badge-info { text-align:right; }
    .badge-name { font-size:.8rem; font-weight:600; }
    .badge-pts { font-size:.73rem; color:#6b7280; margin-top:.1rem; }
    .progress-wrap { width:88px; height:5px; background:#e5e7eb; border-radius:99px; overflow:hidden; margin-top:.3rem; }
    .progress-bar { height:100%; border-radius:99px; }
    @media(max-width:640px){ .form-row-2,.form-row-3{grid-template-columns:1fr;} .form-body,.form-footer{padding:1.2rem;} }
    @media(max-width:768px){ .nav-link span{display:none;} .nav-link{padding:.42rem .55rem;} .badge-widget{display:none;} .user-name{display:none;} }
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
        <a href="MyEvents.php" class="nav-link">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 16l2 2 4-4"/></svg>
          <span>فعالياتي</span>
        </a>
        <a href="organized-events.php" class="nav-link active">
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
    <div class="hero-greeting">فعالياتي المنظمة</div>
    <h2>تعديل الفعالية</h2>
    <p>عدّلي تفاصيل فعاليتك قبل اعتمادها من المشرف</p>
  </div>
</section>

<main class="main">
  <div class="form-card">
    <form method="POST" action="">
    <div class="form-body">

      <div class="pending-notice">
        <div class="pending-notice-icon">⏳</div>
        <div class="pending-notice-text">
          هذه الفعالية <strong>قيد المراجعة</strong>. يمكنك تعديلها الآن.
          بعد اعتماد الفعالية من المشرف، لن يمكن تعديلها.
        </div>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="error-box">
          <?php foreach ($errors as $e): ?>
            <div>• <?= htmlspecialchars($e) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="section-divider">المعلومات الأساسية</div>

      <div class="form-group">
        <label>عنوان الفعالية <span class="req">*</span></label>
        <input type="text" name="title" required value="<?= htmlspecialchars($event['title']) ?>"/>
      </div>

      <div class="form-group">
        <label>التصنيف <span class="req">*</span></label>
        <select name="category" required>
          <?php foreach ($categories as $val => $label): ?>
            <option value="<?= $val ?>" <?= $event['category'] === $val ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>وصف الفعالية <span class="req">*</span></label>
        <textarea name="description" required><?= htmlspecialchars($event['description']) ?></textarea>
      </div>

      <div class="section-divider">التاريخ والموقع</div>

      <div class="form-row-2">
        <div class="form-group">
          <label>تاريخ الفعالية <span class="req">*</span></label>
          <input type="date" name="event_date" required value="<?= htmlspecialchars($event['event_date']) ?>"/>
        </div>
        <div class="form-group">
          <label>الفئة العمرية</label>
          <select name="target_age">
            <?php foreach ($ageGroups as $val => $label): ?>
              <option value="<?= $val ?>" <?= $event['target_age_group'] === $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row-3">
        <div class="form-group">
          <label>وقت البداية</label>
          <input type="time" name="start_time" id="startTime" value="<?= htmlspecialchars(substr($event['start_time'],0,5)) ?>" onchange="calcHours()"/>
        </div>
        <div class="form-group">
          <label>وقت النهاية</label>
          <input type="time" name="end_time" id="endTime" value="<?= htmlspecialchars(substr($event['end_time'],0,5)) ?>" onchange="calcHours()"/>
        </div>
        <div class="form-group">
          <label>ساعات التطوع</label>
          <input type="number" id="volunteerHoursDisplay" value="<?= (int)$event['volunteer_hours'] ?>" readonly/>
          <div class="input-hint">تُحسب تلقائياً</div>
        </div>
      </div>

      <div class="form-group">
        <label>الموقع <span class="req">*</span></label>
        <input type="text" name="location" required value="<?= htmlspecialchars($event['location']) ?>"/>
      </div>

      <div class="form-group">
        <label>رقم التواصل</label>
        <input type="tel" name="contact" value="<?= htmlspecialchars($event['contact_number']) ?>"/>
      </div>

      <div class="section-divider">الإعدادات</div>

      <div class="toggle-row" style="margin-bottom:.9rem">
        <div>
          <div class="toggle-label">🏅 شهادة حضور</div>
          <div class="toggle-sub">المتطوعون يحصلون على شهادة بعد التحقق</div>
        </div>
        <label class="switch">
          <input type="checkbox" name="cert" id="certAvailable" <?= $event['certificate_available'] ? 'checked' : '' ?>/>
          <span class="slider"></span>
        </label>
      </div>

      <div class="form-group">
        <label>نقاط المشاركة</label>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:.5rem">
          <?php foreach ($pointsOptions as $opt): ?>
            <button type="button" class="pts-opt <?= (int)$event['points'] === $opt ? 'active' : '' ?>"
                    data-val="<?= $opt ?>" onclick="selectPoints(this)">
              <?= $opt ?> نقطة
            </button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" name="points" id="eventPoints" value="<?= (int)$event['points'] ?>"/>
      </div>

    </div>

    <div class="form-footer">
      <a href="organized-events.php" class="btn-cancel-page">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        إلغاء
      </a>
      <button type="submit" class="btn-save">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        حفظ التعديلات
      </button>
    </div>
    </form>
  </div>
</main>

<script>
  function calcHours() {
    const s = document.getElementById('startTime').value;
    const e = document.getElementById('endTime').value;
    if (s && e && e > s) {
      const diff = (new Date('2000-01-01T'+e) - new Date('2000-01-01T'+s)) / 3600000;
      document.getElementById('volunteerHoursDisplay').value = Math.round(diff * 10) / 10;
    }
  }
  function selectPoints(btn) {
    document.querySelectorAll('.pts-opt').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('eventPoints').value = btn.dataset.val;
  }
</script>

</body>
</html>