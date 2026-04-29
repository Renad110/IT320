<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId   = (int)$_SESSION['user_id'];

// جلب event_id من URL
$eventId = (int)($_GET['id'] ?? 0);
if (!$eventId) {
    header("Location: organized-events.php");
    exit();
}

// التحقق إن الفعالية تابعة لهذا المستخدم ومعتمدة
$stmt = $conn->prepare(
    "SELECT * FROM events WHERE event_id = ? AND created_by_user_id = ? AND status = 'Approved'"
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

// جلب المشاركين
$participants = [];
$partStmt = $conn->prepare(
    "SELECT u.full_name, u.profile_image_path, r.registration_date, r.attendance_status, r.points_awarded
     FROM registrations r
     JOIN users u ON r.user_id = u.user_id
     WHERE r.event_id = ?
     ORDER BY r.registration_date ASC"
);
if ($partStmt) {
    $partStmt->bind_param("i", $eventId);
    $partStmt->execute();
    $participants = $partStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $partStmt->close();
}

$total    = count($participants);
$attended = count(array_filter($participants, fn($p) => $p['attendance_status'] === 'Attended'));
$pending  = $total - $attended;

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
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>نفل - المشاركون</title>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="styles.css"/>
  <style>
    .page-card { background:#fff; border-radius:1rem; box-shadow:0 2px 12px rgba(0,0,0,0.07); overflow:hidden; animation:fadeUp .45s ease; }
    @keyframes fadeUp { from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)} }

    .event-banner { background:linear-gradient(135deg,#f0fdf4,#dcfce7); border-bottom:1px solid #bbf7d0; padding:1.2rem 1.5rem; display:flex; align-items:center; gap:1rem; flex-wrap:wrap; }
    .event-banner-img   { width:64px; height:64px; border-radius:.65rem; object-fit:cover; flex-shrink:0; }
    .event-banner-title { font-size:1rem; font-weight:700; color:#1f2937; margin-bottom:.3rem; }
    .event-banner-meta  { font-size:.82rem; color:#6b7280; display:flex; gap:.8rem; flex-wrap:wrap; }

    .mini-stats    { display:flex; gap:.7rem; padding:1rem 1.5rem; border-bottom:1px solid #f3f4f6; flex-wrap:wrap; }
    .mini-stat     { flex:1; min-width:100px; background:#f5f7f0; border-radius:.65rem; padding:.75rem 1rem; text-align:center; }
    .mini-stat-num { font-size:1.3rem; font-weight:700; color:#1f2937; }
    .mini-stat-label { font-size:.75rem; color:#6b7280; margin-top:.1rem; }

    .code-panel  { margin:1.2rem 1.5rem; background:#f0fdf4; border:1.5px solid #bbf7d0; border-radius:.85rem; padding:1.1rem 1.3rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.8rem; }
    .code-label  { font-size:.82rem; color:#166534; font-weight:600; margin-bottom:.3rem; }
    .code-value  { font-size:1.6rem; font-weight:800; color:#2d6a35; letter-spacing:.15em; }

    .filter-bar  { padding:1rem 1.5rem; display:flex; gap:.7rem; align-items:center; border-bottom:1px solid #f3f4f6; flex-wrap:wrap; }
    .search-wrap { position:relative; flex:1; min-width:200px; }
    .search-wrap svg   { position:absolute; right:.9rem; top:50%; transform:translateY(-50%); color:#9ca3af; pointer-events:none; }
    .search-wrap input { width:100%; padding:.62rem 2.6rem .62rem .9rem; border:1.5px solid #d1d5db; border-radius:.6rem; font-family:inherit; font-size:.88rem; outline:none; background:#fafafa; transition:border-color .2s; }
    .search-wrap input:focus { border-color:#2d6a35; background:#fff; }

    .filter-select { padding:.62rem 2.2rem .62rem .9rem; border:1.5px solid #d1d5db; border-radius:.6rem; font-family:inherit; font-size:.85rem; outline:none; background:#fafafa; cursor:pointer; appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:left .7rem center; }

    .table-wrap { overflow-x:auto; }
    table       { width:100%; border-collapse:collapse; font-size:.88rem; }
    thead tr    { background:#f5f7f0; }
    th { text-align:right; padding:.8rem 1.2rem; font-size:.78rem; font-weight:700; color:#6b7280; white-space:nowrap; border-bottom:1px solid #e5e7eb; }
    td { padding:.9rem 1.2rem; border-bottom:1px solid #f3f4f6; color:#374151; vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    tr:hover td      { background:#fafafa; }

    .participant-name      { display:flex; align-items:center; gap:.65rem; }
    .participant-avatar    { width:34px; height:34px; border-radius:50%; background:#2d6a35; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.85rem; flex-shrink:0; }
    .participant-name-text { font-weight:600; color:#1f2937; }

    .att-badge      { display:inline-flex; align-items:center; gap:.3rem; padding:.22rem .7rem; border-radius:99px; font-size:.77rem; font-weight:600; }
    .att-attended   { background:#dcfce7; color:#166534; }
    .att-registered { background:#fef9c3; color:#854d0e; }

    .pts-cell { color:#ca8a04; font-weight:600; }

    .btn-copy        { padding:.55rem 1.2rem; background:#2d6a35; color:#fff; border:none; border-radius:.6rem; font-family:inherit; font-size:.86rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:.4rem; transition:background .2s; }
    .btn-copy:hover  { background:#1e4d25; }
    .export-btn      { padding:.55rem 1.1rem; background:#f5f7f0; color:#374151; border:1.5px solid #d1d5db; border-radius:.6rem; font-family:inherit; font-size:.84rem; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:.4rem; transition:background .2s; }
    .export-btn:hover { background:#e5e7eb; }

    .nav-links       { display:flex; align-items:center; gap:.4rem; }
    .nav-link        { display:flex; align-items:center; gap:.38rem; text-decoration:none; color:#374151; font-size:.86rem; font-weight:500; padding:.42rem .85rem; border-radius:.55rem; transition:background .2s,color .2s; white-space:nowrap; border:1.5px solid transparent; }
    .nav-link:hover  { background:#f0fdf4; color:#2d6a35; border-color:#bbf7d0; }
    .nav-link.active { background:#f0fdf4; color:#2d6a35; border-color:#bbf7d0; }

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
    @media(max-width:640px){ th:nth-child(4),td:nth-child(4){ display:none; } }
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
    <h2>قائمة المشاركين</h2>
    <p>تابعي المشاركين المسجلين وحالة حضورهم</p>
  </div>
</section>

<main class="main">
  <div class="page-card">

    <!-- بانر الفعالية -->
    <div class="event-banner">
      <?php if ($event['image_path']): ?>
        <img class="event-banner-img" src="<?= htmlspecialchars($event['image_path']) ?>" alt=""/>
      <?php endif; ?>
      <div>
        <div class="event-banner-title"><?= htmlspecialchars($event['title']) ?></div>
        <div class="event-banner-meta">
          <span>📅 <?= htmlspecialchars($event['event_date']) ?></span>
          <span>🕗 <?= htmlspecialchars($event['start_time']) ?> — <?= htmlspecialchars($event['end_time']) ?></span>
          <span>📍 <?= htmlspecialchars($event['location']) ?></span>
        </div>
      </div>
    </div>

    <!-- إحصائيات مصغرة -->
    <div class="mini-stats">
      <div class="mini-stat">
        <div class="mini-stat-num"><?= $total ?></div>
        <div class="mini-stat-label">إجمالي المسجلين</div>
      </div>
      <div class="mini-stat" style="background:#f0fdf4">
        <div class="mini-stat-num" style="color:#2d6a35"><?= $attended ?></div>
        <div class="mini-stat-label">حضروا فعلاً</div>
      </div>
      <div class="mini-stat" style="background:#fef9c3">
        <div class="mini-stat-num" style="color:#ca8a04"><?= $pending ?></div>
        <div class="mini-stat-label">لم يحضروا بعد</div>
      </div>
    </div>

    <!-- كود الحضور -->
    <?php if ($event['attendance_code']): ?>
    <div class="code-panel">
      <div>
        <div class="code-label">🔑 كود الحضور — شاركيه مع المشاركين في الفعالية</div>
        <div class="code-value" id="attCode"><?= htmlspecialchars($event['attendance_code']) ?></div>
      </div>
      <button class="btn-copy" onclick="copyCode()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
        نسخ الكود
      </button>
    </div>
    <?php endif; ?>

    <!-- شريط البحث والفلتر -->
    <div class="filter-bar">
      <div class="search-wrap">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" placeholder="ابحث باسم المشارك..." oninput="filterTable(this.value)"/>
      </div>
      <select class="filter-select" onchange="filterByAtt(this.value)">
        <option value="all">الكل</option>
        <option value="Attended">حضروا</option>
        <option value="Registered">لم يحضروا</option>
      </select>
      <button class="export-btn" onclick="exportCSV()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        تصدير CSV
      </button>
    </div>

    <!-- الجدول -->
    <div class="table-wrap">
      <table id="participantsTable">
        <thead>
          <tr>
            <th>#</th>
            <th>المشارك</th>
            <th>تاريخ التسجيل</th>
            <th>الحضور</th>
            <th>النقاط</th>
          </tr>
        </thead>
        <tbody id="tableBody">
          <?php if (empty($participants)): ?>
            <tr><td colspan="5" style="text-align:center;color:#9ca3af;padding:2rem">لا يوجد مشاركون بعد</td></tr>
          <?php else: ?>
            <?php foreach ($participants as $i => $p):
              $init      = mb_substr($p['full_name'], 0, 1, 'UTF-8');
              $isAttended = $p['attendance_status'] === 'Attended';
            ?>
            <tr data-name="<?= htmlspecialchars($p['full_name']) ?>" data-status="<?= htmlspecialchars($p['attendance_status']) ?>">
              <td style="color:#9ca3af;font-size:.8rem"><?= $i + 1 ?></td>
              <td>
                <div class="participant-name">
                  <div class="participant-avatar"><?= htmlspecialchars($init) ?></div>
                  <span class="participant-name-text"><?= htmlspecialchars($p['full_name']) ?></span>
                </div>
              </td>
              <td style="color:#6b7280;font-size:.82rem"><?= htmlspecialchars($p['registration_date']) ?></td>
              <td>
                <span class="att-badge <?= $isAttended ? 'att-attended' : 'att-registered' ?>">
                  <?= $isAttended ? '✅ حضر' : '⏳ مسجل' ?>
                </span>
              </td>
              <td class="pts-cell"><?= $p['points_awarded'] > 0 ? $p['points_awarded'] . ' ⭐' : '—' ?></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</main>

<div class="toast" id="toast">✅ تم نسخ الكود</div>

<script>
  // بيانات المشاركين للـ export و client-side filter
  const participants = <?= json_encode(array_map(fn($p) => [
      'name'    => $p['full_name'],
      'date'    => $p['registration_date'],
      'status'  => $p['attendance_status'],
      'points'  => $p['points_awarded'],
  ], $participants), JSON_UNESCAPED_UNICODE) ?>;

  let searchVal = '';
  let attFilter = 'all';

  function filterTable(val) {
    searchVal = val.trim();
    applyFilter();
  }
  function filterByAtt(val) {
    attFilter = val;
    applyFilter();
  }

  function applyFilter() {
    const rows = document.querySelectorAll('#tableBody tr[data-name]');
    let visible = 0;
    rows.forEach(row => {
      const name   = row.dataset.name;
      const status = row.dataset.status;
      const matchSearch = name.includes(searchVal);
      const matchAtt    = attFilter === 'all' || status === attFilter;
      row.style.display = (matchSearch && matchAtt) ? '' : 'none';
      if (matchSearch && matchAtt) visible++;
    });
    // إذا ما في نتائج
    const empty = document.getElementById('noResults');
    if (empty) empty.style.display = visible === 0 ? '' : 'none';
  }

  function copyCode() {
    const code = document.getElementById('attCode')?.textContent?.trim();
    if (code) navigator.clipboard.writeText(code).catch(() => {});
    const t = document.getElementById('toast');
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2000);
  }

  function exportCSV() {
    const rows = [['الاسم', 'تاريخ التسجيل', 'الحضور', 'النقاط']];
    participants.forEach(p => rows.push([
      p.name, p.date,
      p.status === 'Attended' ? 'حضر' : 'مسجل',
      p.points
    ]));
    const csv  = rows.map(r => r.join(',')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' });
    const a    = document.createElement('a');
    a.href     = URL.createObjectURL(blob);
    a.download = 'participants.csv';
    a.click();
  }
</script>

</body>
</html>