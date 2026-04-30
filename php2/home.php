<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = $_SESSION['user_id'];
$userQ = $conn->prepare("SELECT full_name, total_points, profile_image_path FROM users WHERE user_id = ?");
$userQ->bind_param("i", $uid);
$userQ->execute();
$user = $userQ->get_result()->fetch_assoc();
$userQ->close();

$fullName = $user['full_name'] ?? 'مستخدم';
$points   = (int)($user['total_points'] ?? 0);
$initial  = mb_substr($fullName, 0, 1, 'UTF-8');
$userImg  = $user['profile_image_path'] ?? null;

if ($points >= 100)      { $badgeName='💎 ماسي';   $badgeColor='#0891b2'; $badgeMax=150; }
elseif ($points >= 70)   { $badgeName='🥇 ذهبي';   $badgeColor='#ca8a04'; $badgeMax=100; }
elseif ($points >= 40)   { $badgeName='🥈 فضي';    $badgeColor='#6b7280'; $badgeMax=70;  }
else                     { $badgeName='🥉 برونزي'; $badgeColor='#b45309'; $badgeMax=40;  }
$progress = min(100, ($points / $badgeMax) * 100);

$catMapAr = [
    'Cleaning'   => 'تنظيف',
    'Planting'   => 'زراعة',
    'Recycling'  => 'إعادة تدوير',
    'Awareness'  => 'توعية',
    'Exhibition' => 'معرض'
];

$selectedCat = $_GET['cat']    ?? 'الكل';
$searchTerm  = trim($_GET['q'] ?? '');
$showMode    = $_GET['show']   ?? 'upcoming';  // upcoming = قادمة | expired = منتهية

$sql = "SELECT e.event_id, e.title, e.category, e.event_date, e.end_time, e.location, e.points, e.image_path,
               (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id) AS participants
        FROM events e
        WHERE e.status = 'Approved'";

// فلتر التاريخ والوقت بناءً على وضع العرض
if ($showMode === 'expired') {
    $sql .= " AND TIMESTAMP(e.event_date, e.end_time) < NOW()";
} else {
    $sql .= " AND TIMESTAMP(e.event_date, e.end_time) >= NOW()";
}

$params = [];
$types  = '';

if ($selectedCat !== 'الكل') {
    $catEn = array_search($selectedCat, $catMapAr);
    if ($catEn) {
        $sql .= " AND e.category = ?";
        $params[] = $catEn;
        $types   .= 's';
    }
}
if ($searchTerm !== '') {
    $sql .= " AND e.title LIKE ?";
    $params[] = "%$searchTerm%";
    $types   .= 's';
}

// المنتهية تترتب من الأحدث للأقدم، القادمة من الأقرب
$sql .= ($showMode === 'expired') ? " ORDER BY e.event_date DESC" : " ORDER BY e.event_date ASC";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>نفل - اكتشف الفعاليات</title>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'IBM Plex Sans Arabic', sans-serif; background: #f5f7f0; min-height: 100vh; color: #1f2937; }
    header { background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.08); position: sticky; top: 0; z-index: 50; }
    .header-inner { max-width: 1200px; margin: 0 auto; padding: .75rem 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
    .brand { display:flex; align-items:center; gap:.5rem; text-decoration:none; }
    .brand img { height: 52px; width: auto; object-fit: contain; filter: drop-shadow(0 2px 8px rgba(45,106,53,0.2)); }
    .header-right { display:flex; align-items:center; gap:.85rem; }
    .user-chip { display:flex; align-items:center; gap:.55rem; background:#f0fdf4; border:1.5px solid #bbf7d0; padding:.42rem .95rem; border-radius:99px; }
    .user-avatar { width:30px; height:30px; border-radius:50%; background:#2d6a35; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.88rem; flex-shrink:0; overflow:hidden; }
    .user-avatar img { width:100%; height:100%; object-fit:cover; display:block; }
    .user-name { font-weight:600; font-size:.9rem; color:#1e4d25; }
    .badge-widget { display:flex; align-items:center; gap:.55rem; background:#fafafa; border:1px solid #e5e7eb; padding:.45rem .85rem; border-radius:.75rem; }
    .badge-info { text-align:right; }
    .badge-name { font-size:.8rem; font-weight:600; }
    .badge-pts { font-size:.73rem; color:#6b7280; margin-top:.1rem; }
    .progress-wrap { width:88px; height:5px; background:#e5e7eb; border-radius:99px; overflow:hidden; margin-top:.3rem; }
    .progress-bar { height:100%; border-radius:99px; }
    .btn-logout { display:flex; align-items:center; gap:.3rem; color:#dc2626; background:none; border:none; font-family:inherit; font-size:.86rem; font-weight:500; cursor:pointer; padding:.42rem .75rem; border-radius:.55rem; transition:background .2s; white-space:nowrap; text-decoration:none; }
    .btn-logout:hover { background:#fef2f2; }
    .nav-links { display:flex; align-items:center; gap:.4rem; }
    .nav-link { display:flex; align-items:center; gap:.38rem; text-decoration:none; color:#374151; font-size:.86rem; font-weight:500; padding:.42rem .85rem; border-radius:.55rem; transition:background .2s, color .2s, border-color .2s; white-space:nowrap; border:1.5px solid transparent; }
    .nav-link:hover { background:#f0fdf4; color:#2d6a35; border-color:#bbf7d0; }
    .nav-link svg { flex-shrink:0; }
    @media(max-width:768px){ .nav-link span { display:none; } .nav-link { padding:.42rem .55rem; } }
    .hero { background: linear-gradient(135deg, #2d6a35 0%, #1a4220 100%); color:#fff; padding: 2.5rem 1.5rem; }
    .hero-inner { max-width:1200px; margin:0 auto; }
    .hero-greeting { font-size:.92rem; color:#86efac; margin-bottom:.35rem; font-weight:500; }
    .hero h2 { font-size:2rem; font-weight:700; margin-bottom:.45rem; }
    .hero p { font-size:.97rem; color:#bbf7d0; }
    .main { max-width:1200px; margin:0 auto; padding: 1.75rem 1.5rem 3rem; }
    .search-card { background:#fff; border-radius:.9rem; box-shadow:0 2px 10px rgba(0,0,0,0.06); padding:1.3rem; margin-bottom:1.6rem; }
    .search-wrap { position:relative; margin-bottom:1rem; }
    .search-icon { position:absolute; right:1rem; top:50%; transform:translateY(-50%); color:#9ca3af; pointer-events:none; }
    .search-input { width:100%; padding:.78rem 2.8rem .78rem 1rem; border:1.5px solid #d1d5db; border-radius:.65rem; font-family:inherit; font-size:.93rem; outline:none; background:#fafafa; transition:border-color .2s,box-shadow .2s; }
    .search-input:focus { border-color:#2d6a35; box-shadow:0 0 0 3px rgba(45,106,53,0.1); background:#fff; }
    .cats-label { color:#374151; font-weight:500; margin-bottom:.55rem; font-size:.88rem; }
    .categories { display:flex; flex-wrap:wrap; gap:.4rem; align-items:center; }
    .cat-btn { padding:.38rem .95rem; border-radius:99px; border:none; font-family:inherit; font-size:.83rem; cursor:pointer; background:#f3f4f6; color:#374151; transition:background .2s,color .2s; text-decoration:none; display:inline-block; }
    .cat-btn:hover { background:#e5e7eb; }
    .cat-btn.active { background:#2d6a35; color:#fff; }

    /* زر المنتهية بستايل مميز */
    .cat-btn-expired { background:#fef2f2; color:#991b1b; border:1.5px solid #fecaca; padding:.32rem .85rem; }
    .cat-btn-expired:hover { background:#fee2e2; }
    .cat-btn-expired.active { background:#991b1b; color:#fff; border-color:#991b1b; }
    .events-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(295px,1fr)); gap:1.3rem; }
    .event-card { background:#fff; border-radius:.9rem; box-shadow:0 2px 8px rgba(0,0,0,0.07); overflow:hidden; cursor:pointer; transition:box-shadow .25s,transform .2s; text-decoration:none; color:inherit; display:block; position:relative; }
    .event-card:hover { box-shadow:0 8px 30px rgba(0,0,0,0.13); transform:translateY(-3px); }
    .event-card.expired { opacity:0.85; }
    .event-card.expired .event-img { filter:grayscale(40%); }
    .expired-overlay { position:absolute; top:.7rem; right:.7rem; background:rgba(75,85,99,0.95); color:#fff; padding:.3rem .8rem; border-radius:99px; font-size:.78rem; font-weight:600; z-index:2; backdrop-filter:blur(4px); }
    .event-img { width:100%; height:182px; object-fit:cover; display:block; }
    .event-img-placeholder { width:100%; height:182px; background:linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); display:flex; align-items:center; justify-content:center; font-size:3.5rem; }
    .event-body { padding:1.1rem; }
    .event-meta { display:flex; align-items:center; justify-content:space-between; margin-bottom:.65rem; }
    .cat-tag { background:#dcfce7; color:#166534; padding:.2rem .7rem; border-radius:99px; font-size:.78rem; font-weight:500; }
    .pts-badge { display:flex; align-items:center; gap:.25rem; color:#ca8a04; font-size:.8rem; font-weight:500; }
    .event-title { font-size:.98rem; font-weight:600; color:#1f2937; margin-bottom:.75rem; line-height:1.45; }
    .event-details { display:flex; flex-direction:column; gap:.35rem; }
    .detail-row { display:flex; align-items:center; gap:.42rem; color:#4b5563; font-size:.8rem; }
    .detail-row svg { flex-shrink:0; color:#9ca3af; }
    .btn-details { display:block; width:100%; margin-top:.9rem; padding:.6rem; background:#2d6a35; color:#fff; border:none; border-radius:.6rem; font-family:inherit; font-size:.9rem; font-weight:600; cursor:pointer; text-align:center; transition:background .2s; }
    .btn-details:hover { background:#1e4d25; }
    .no-results { text-align:center; padding:3rem 0; color:#6b7280; font-size:1rem; grid-column:1/-1; }
    @media(max-width:640px){ .hero h2{font-size:1.5rem;} .badge-widget{display:none;} .user-name{display:none;} }
  </style>
</head>
<body>

<header>
  <div class="header-inner">
    <a href="home.php" class="brand">
      <img src="logo.jpg" alt="نفل" />
    </a>
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
        <a href="MyEvents.php" class="nav-link">
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
    <div class="hero-greeting">مرحباً يا <?= htmlspecialchars(explode(' ', $fullName)[0]) ?> 👋</div>
    <h2><?= $showMode === 'expired' ? 'الفعاليات المنتهية ⏰' : 'اكتشف الفعاليات البيئية' ?></h2>
    <p><?= $showMode === 'expired' ? 'تصفّح الفعاليات السابقة وتعرّف على إنجازاتنا' : 'شارك في بناء مستقبل أخضر لمدينة الرياض' ?></p>
  </div>
</section>

<main class="main">
  <form method="GET" action="home.php" class="search-card">
    <div class="search-wrap">
      <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input class="search-input" type="text" name="q" placeholder="ابحث عن فعالية..." value="<?= htmlspecialchars($searchTerm) ?>" onchange="this.form.submit()"/>
    </div>
    <div class="cats-label">التصنيفات</div>
    <div class="categories">
      <?php
        $cats = ['الكل','تنظيف','زراعة','إعادة تدوير','توعية','معرض','منتهية'];
        foreach ($cats as $c) {
            $isActive = ($c === 'منتهية' && $showMode === 'expired')
                     || ($c !== 'منتهية' && $showMode !== 'expired' && $selectedCat === $c);
            $active = $isActive ? 'active' : '';

            // الرابط: لو "منتهية" يستخدم show=expired، غير ذلك يستخدم cat
            if ($c === 'منتهية') {
                $url = "home.php?show=expired" . ($searchTerm ? "&q=".urlencode($searchTerm) : "");
                $extraClass = 'cat-btn-expired';
                $label = '⏰ منتهية';
            } else {
                $url = "home.php?cat=" . urlencode($c) . ($searchTerm ? "&q=".urlencode($searchTerm) : "");
                $extraClass = '';
                $label = $c;
            }
            echo "<a class='cat-btn $extraClass $active' href='$url'>$label</a>";
        }
      ?>
    </div>

    <!-- حقول مخفية للحفاظ على الفلتر عند البحث -->
    <?php if ($showMode === 'expired'): ?>
      <input type="hidden" name="show" value="expired"/>
    <?php else: ?>
      <input type="hidden" name="cat" value="<?= htmlspecialchars($selectedCat) ?>"/>
    <?php endif; ?>
  </form>

  <div class="events-grid">
    <?php if (empty($events)): ?>
      <div class="no-results">لا توجد فعاليات تطابق البحث</div>
    <?php else: ?>
      <?php foreach ($events as $e):
        $catAr = $catMapAr[$e['category']] ?? $e['category'];
        $hasImage = !empty($e['image_path']);
      ?>
        <a class="event-card <?= $showMode === 'expired' ? 'expired' : '' ?>" href="ViewEvent.php?id=<?= $e['event_id'] ?>">
          <?php if ($showMode === 'expired'): ?>
            <span class="expired-overlay">⏰ الفعاليات المنتهية</span>
          <?php endif; ?>
          <?php if ($hasImage): ?>
            <img class="event-img" src="<?= htmlspecialchars($e['image_path']) ?>" alt="<?= htmlspecialchars($e['title']) ?>" loading="lazy"/>
          <?php else: ?>
            <div class="event-img-placeholder">🌱</div>
          <?php endif; ?>
          <div class="event-body">
            <div class="event-meta">
              <span class="cat-tag"><?= htmlspecialchars($catAr) ?></span>
              <span class="pts-badge">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                <?= $e['points'] ?> نقطة
              </span>
            </div>
            <div class="event-title"><?= htmlspecialchars($e['title']) ?></div>
            <div class="event-details">
              <div class="detail-row">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <?= htmlspecialchars($e['event_date']) ?>
              </div>
              <div class="detail-row">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <?= htmlspecialchars($e['location']) ?>
              </div>
              <div class="detail-row">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <?= (int)$e['participants'] ?> مشارك
              </div>
            </div>
            <div class="btn-details">عرض التفاصيل</div>
          </div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</main>

</body>
</html>


