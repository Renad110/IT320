<?php
session_start();
require_once 'db_connect.php';

// التحقق من صلاحية المشرف
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: admin-login.php");
    exit();
}

$adminName    = $_SESSION['full_name'];
$adminInitial = mb_substr($adminName, 0, 1, 'UTF-8');

// جلب الفعالية من DB
$eventId = (int)($_GET['id'] ?? 0);
if (!$eventId) {
    header("Location: admin-review.php");
    exit();
}

$stmt = $conn->prepare(
    "SELECT e.*, u.full_name AS organizer_name, u.profile_image_path AS organizer_image
     FROM events e
     JOIN users u ON e.created_by_user_id = u.user_id
     WHERE e.event_id = ?"
);
$stmt->bind_param("i", $eventId);
$stmt->execute();
$ev = $stmt->get_result()->fetch_assoc();

if (!$ev) {
    header("Location: admin-review.php");
    exit();
}

// معالجة الاعتماد / الرفض من هذه الصفحة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $newStatus = match($action) {
        'approve' => 'Approved',
        'reject'  => 'Rejected',
        default   => null,
    };
    if ($newStatus) {
        $upd = $conn->prepare("UPDATE events SET status = ? WHERE event_id = ?");
        $upd->bind_param("si", $newStatus, $eventId);
        $upd->execute();
        header("Location: admin-view-event.php?id=$eventId&done=$action");
        exit();
    }
}

// دوال مساعدة
function categoryLabel($c) {
    return match($c) {
        'Cleaning'   => 'تنظيف',
        'Planting'   => 'زراعة',
        'Recycling'  => 'إعادة تدوير',
        'Awareness'  => 'توعية',
        'Exhibition' => 'معرض',
        default      => $c,
    };
}
function ageLabel($a) {
    return match($a) {
        'Children' => 'أطفال',
        'Teens'    => 'مراهقين',
        'Adults'   => 'بالغين',
        'All'      => 'الكل',
        default    => $a,
    };
}

$cat        = categoryLabel($ev['category']);
$age        = ageLabel($ev['target_age_group']);
$isPending  = $ev['status'] === 'Pending';

// الحروف الأولى من اسم المنظم
$nameParts      = explode(' ', trim($ev['organizer_name']));
$organizerInit  = mb_substr($nameParts[0], 0, 1, 'UTF-8') . (isset($nameParts[1]) ? mb_substr($nameParts[1], 0, 1, 'UTF-8') : '');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>نفل - <?= htmlspecialchars($ev['title']) ?></title>
  <link rel="stylesheet" href="styles.css"/>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'IBM Plex Sans Arabic', sans-serif; background: #f5f7f0; min-height: 100vh; color: #1f2937; }

    header { background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.08); position: sticky; top: 0; z-index: 50; }
    .header-inner { max-width: 1200px; margin: 0 auto; padding: .75rem 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
    .brand { display:flex; align-items:center; gap:.5rem; text-decoration:none; }
    .brand img { height: 52px; width: auto; object-fit: contain; filter: drop-shadow(0 2px 8px rgba(45,106,53,0.2)); }
    .header-right { display:flex; align-items:center; gap:.85rem; }
    .user-chip { display:flex; align-items:center; gap:.5rem; background:#eff6ff; border:1.5px solid #bfdbfe; padding:.38rem .85rem; border-radius:99px; }
    .user-avatar { width:28px; height:28px; border-radius:50%; background:#1e3a5f; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.83rem; }
    .user-name { font-weight:600; font-size:.87rem; color:#1e3a5f; }
    .btn-back { display:flex; align-items:center; gap:.3rem; color:#2d6a35; background:none; border:none; font-family:inherit; font-size:.88rem; font-weight:500; cursor:pointer; padding:.4rem .8rem; border-radius:.55rem; text-decoration:none; transition:background .2s; }
    .btn-back:hover { background:#f0fdf4; }
    .btn-logout { display:flex; align-items:center; gap:.3rem; color:#dc2626; background:none; border:none; font-family:inherit; font-size:.86rem; font-weight:500; cursor:pointer; padding:.42rem .75rem; border-radius:.55rem; text-decoration:none; transition:background .2s; white-space:nowrap; }
    .btn-logout:hover { background:#fef2f2; }

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
    .info-label { font-size:.73rem; color:#6b7280; margin-bottom:.1rem; }
    .info-value { font-size:.88rem; color:#1f2937; font-weight:500; }

    .organizer-avatar { width:38px; height:38px; border-radius:50%; object-fit:cover; flex-shrink:0; border:2px solid #dcfce7; }
    .organizer-avatar-fallback { width:38px; height:38px; border-radius:50%; background:#2d6a35; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.85rem; flex-shrink:0; }

    .section-title { font-size:1.2rem; font-weight:700; color:#1f2937; margin-bottom:.8rem; }
    .event-desc { color:#4b5563; line-height:1.85; font-size:.97rem; margin-bottom:1.75rem; }

    .btn-action { display:block; width:100%; padding:.95rem; border:none; border-radius:.75rem; font-family:inherit; font-size:1.05rem; font-weight:700; cursor:pointer; text-align:center; transition:background .2s, transform .1s; color:#fff; }
    .btn-action:active { transform:scale(0.99); }
    .btn-approve-main { background:#2d6a35; }
    .btn-approve-main:hover { background:#1e4d25; }
    .btn-reject-main  { background:#dc2626; }
    .btn-reject-main:hover  { background:#b91c1c; }

    .status-badge { display:inline-block; padding:.3rem 1rem; border-radius:99px; font-size:.82rem; font-weight:600; }
    .status-pending  { background:#fef9c3; color:#854d0e; }
    .status-approved { background:#dcfce7; color:#166534; }
    .status-rejected { background:#fee2e2; color:#991b1b; }

    /* Toast */
    .toast { position: fixed; bottom: 1.5rem; left: 50%; transform: translateX(-50%) translateY(80px); color: #fff; padding: .75rem 1.4rem; border-radius: .7rem; font-size: .9rem; font-weight: 600; box-shadow: 0 8px 24px rgba(0,0,0,0.18); transition: transform .35s ease; z-index: 9999; white-space: nowrap; }
    .toast.show { transform: translateX(-50%) translateY(0); }
    .toast.approved { background: #1e4d25; }
    .toast.rejected { background: #991b1b; }

    @media(max-width:640px){ .info-grid{grid-template-columns:1fr;} .hero-img{height:200px;} .card-body{padding:1.2rem;} .event-title{font-size:1.3rem;} .user-name{display:none;} }
  </style>
</head>
<body>

<header>
  <div class="header-inner">
    <a href="home.php" class="brand"><img src="logo.jpg" alt="نفل"/></a>
    <div class="header-right">
      <a href="admin-review.php" class="btn-back">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        لوحة المشرف
      </a>
      <div class="user-chip">
        <div class="user-avatar"><?= htmlspecialchars($adminInitial) ?></div>
        <span class="user-name"><?= htmlspecialchars($adminName) ?></span>
      </div>
      <a href="logout.php" class="btn-logout">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        خروج
      </a>
    </div>
  </div>
</header>

<main class="content">
  <div class="detail-card">
    <?php if ($ev['image_path']): ?>
      <img class="hero-img" src="<?= htmlspecialchars($ev['image_path']) ?>" alt="<?= htmlspecialchars($ev['title']) ?>"/>
    <?php endif; ?>

    <div class="card-body">
      <div class="top-meta">
        <span class="cat-tag"><?= $cat ?></span>
        <span class="pts-display">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          <?= (int)$ev['points'] ?> نقطة
        </span>
        <span class="status-badge status-<?= strtolower($ev['status']) ?>">
          <?= match($ev['status']) { 'Pending' => 'قيد المراجعة', 'Approved' => 'مُعتمدة', 'Rejected' => 'مرفوضة', default => $ev['status'] } ?>
        </span>
      </div>

      <h1 class="event-title"><?= htmlspecialchars($ev['title']) ?></h1>

      <div class="info-grid">
        <div class="info-item">
          <div class="info-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2d6a35" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
          <div><div class="info-label">التاريخ</div><div class="info-value"><?= htmlspecialchars($ev['event_date']) ?></div></div>
        </div>
        <div class="info-item">
          <div class="info-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2d6a35" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
          <div><div class="info-label">وقت البداية – النهاية</div><div class="info-value"><?= htmlspecialchars($ev['start_time']) ?> – <?= htmlspecialchars($ev['end_time']) ?></div></div>
        </div>
        <div class="info-item">
          <div class="info-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2d6a35" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
          <div><div class="info-label">الموقع</div><div class="info-value"><?= htmlspecialchars($ev['location']) ?></div></div>
        </div>
        <div class="info-item">
          <div class="info-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2d6a35" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.4 2 2 0 0 1 3.6 1.22h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 8.8a16 16 0 0 0 6.29 6.29l.95-.95a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg></div>
          <div><div class="info-label">رقم التواصل</div><div class="info-value"><?= htmlspecialchars($ev['contact_number']) ?></div></div>
        </div>
        <div class="info-item">
          <div class="info-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2d6a35" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
          <div><div class="info-label">ساعات التطوع</div><div class="info-value"><?= (int)$ev['volunteer_hours'] ?> ساعات</div></div>
        </div>
        <div class="info-item">
          <div class="info-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2d6a35" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
          <div><div class="info-label">الفئة المستهدفة</div><div class="info-value"><?= $age ?></div></div>
        </div>
        <div class="info-item">
          <?php if ($ev['organizer_image']): ?>
            <img class="organizer-avatar" src="<?= htmlspecialchars($ev['organizer_image']) ?>" alt="<?= htmlspecialchars($ev['organizer_name']) ?>"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"/>
            <div class="organizer-avatar-fallback" style="display:none"><?= htmlspecialchars($organizerInit) ?></div>
          <?php else: ?>
            <div class="organizer-avatar-fallback"><?= htmlspecialchars($organizerInit) ?></div>
          <?php endif; ?>
          <div><div class="info-label">المنظم</div><div class="info-value"><?= htmlspecialchars($ev['organizer_name']) ?></div></div>
        </div>
      </div>

      <div class="section-title">عن الفعالية</div>
      <p class="event-desc"><?= htmlspecialchars($ev['description']) ?></p>

      <?php if ($isPending): ?>
      <div style="display:flex; gap:10px; margin-top:20px;">
        <!-- زر الاعتماد -->
        <form method="POST" action="" style="flex:1">
          <input type="hidden" name="action" value="approve"/>
          <button type="submit" class="btn-action btn-approve-main">اعتماد ✅</button>
        </form>
        <!-- زر الرفض -->
        <form method="POST" action="" style="flex:1">
          <input type="hidden" name="action" value="reject"/>
          <button type="submit" class="btn-action btn-reject-main">رفض ❌</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<!-- Toast -->
<?php if (isset($_GET['done'])): ?>
<div class="toast <?= $_GET['done'] === 'approve' ? 'approved' : 'rejected' ?> show" id="toast">
  <?= $_GET['done'] === 'approve' ? '✅ تم اعتماد الفعالية بنجاح' : '❌ تم رفض الفعالية' ?>
</div>
<script>setTimeout(() => document.getElementById('toast').classList.remove('show'), 2500);</script>
<?php endif; ?>

</body>
</html>
