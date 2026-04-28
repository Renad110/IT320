<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId   = (int)$_SESSION['user_id'];
$userName = $_SESSION['full_name'];
$userInit = mb_substr($userName, 0, 1, 'UTF-8');

// ======= معالجة الحذف =======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['event_id'])) {
    if ($_POST['action'] === 'delete') {
        $delId = (int)$_POST['event_id'];
        $stmt = $conn->prepare(
            "DELETE FROM events WHERE event_id = ? AND created_by_user_id = ? AND status = 'Pending'"
        );
        $stmt->bind_param("ii", $delId, $userId);
        $stmt->execute();
    }
    header("Location: organized-events.php");
    exit();
}

// ======= جلب الفعاليات =======
$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'Pending', 'Approved', 'Rejected'])) $filter = 'all';

if ($filter === 'all') {
    $stmt = $conn->prepare(
        "SELECT e.*,
                (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id) AS participants_count
         FROM events e WHERE e.created_by_user_id = ?
         ORDER BY e.event_id DESC"
    );
    $stmt->bind_param("i", $userId);
} else {
    $stmt = $conn->prepare(
        "SELECT e.*,
                (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id) AS participants_count
         FROM events e WHERE e.created_by_user_id = ? AND e.status = ?
         ORDER BY e.event_id DESC"
    );
    $stmt->bind_param("is", $userId, $filter);
}
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ======= إحصائيات =======
$statsStmt = $conn->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(status='Pending') AS pending,
        (SELECT COALESCE(SUM(1),0) FROM registrations r
         JOIN events e2 ON r.event_id = e2.event_id
         WHERE e2.created_by_user_id = ?) AS total_participants
     FROM events WHERE created_by_user_id = ?"
);
$statsStmt->bind_param("ii", $userId, $userId);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();

// ======= نقاط وشارة =======
$userStmt = $conn->prepare("SELECT total_points FROM users WHERE user_id = ?");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$points = (int)$userStmt->get_result()->fetch_assoc()['total_points'];

function getBadge($pts) {
    if ($pts >= 100) return ['name' => '🥇 ذهبي',  'color' => '#ca8a04', 'max' => 200];
    if ($pts >= 50)  return ['name' => '🥈 فضي',   'color' => '#6b7280', 'max' => 100];
    return               ['name' => '🥉 برونزي', 'color' => '#b45309', 'max' => 50];
}
$badge    = getBadge($points);
$progress = min(100, round(($points / $badge['max']) * 100));

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
function statusInfo($s) {
    return match($s) {
        'Pending'  => ['label' => 'قيد المراجعة', 'cls' => 'status-pending'],
        'Approved' => ['label' => 'مُعتمدة',      'cls' => 'status-approved'],
        'Rejected' => ['label' => 'مرفوضة',       'cls' => 'status-rejected'],
        default    => ['label' => $s,             'cls' => ''],
    };
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>نفل - فعالياتي المنظمة</title>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="styles.css"/>
  <style>
    /* كاردات الفعاليات */
    .org-card { background:#fff; border-radius:.9rem; box-shadow:0 2px 8px rgba(0,0,0,0.07); overflow:hidden; margin-bottom:1rem; transition:box-shadow .25s; display:flex; flex-direction:column; }
    .org-card:hover { box-shadow:0 6px 24px rgba(0,0,0,0.11); }
    .org-card-inner { display:flex; }
    .org-card-img   { width:140px; flex-shrink:0; object-fit:cover; }
    .org-card-body  { padding:1.1rem 1.3rem; flex:1; display:flex; flex-direction:column; gap:.4rem; }
    .org-card-top   { display:flex; align-items:flex-start; justify-content:space-between; gap:.6rem; }
    .org-card-title { font-size:1rem; font-weight:700; color:#1f2937; line-height:1.45; }

    .status-badge    { padding:.28rem .75rem; border-radius:99px; font-size:.77rem; font-weight:600; white-space:nowrap; flex-shrink:0; }
    .status-pending  { background:#fef9c3; color:#854d0e; }
    .status-approved { background:#dcfce7; color:#166534; }
    .status-rejected { background:#fee2e2; color:#991b1b; }

    .org-meta      { display:flex; flex-wrap:wrap; gap:.5rem 1.1rem; }
    .org-meta-item { display:flex; align-items:center; gap:.35rem; font-size:.8rem; color:#6b7280; }

    .org-card-actions { display:flex; gap:.5rem; padding:.8rem 1.3rem; border-top:1px solid #f3f4f6; background:#fafafa; flex-wrap:wrap; }

    .action-btn            { padding:.45rem 1rem; border-radius:.55rem; border:none; font-family:inherit; font-size:.83rem; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:.35rem; transition:background .2s,transform .1s; text-decoration:none; }
    .action-btn:active     { transform:scale(.97); }
    .action-edit           { background:#f0fdf4; color:#166534; }
    .action-edit:hover     { background:#dcfce7; }
    .action-delete         { background:#fef2f2; color:#dc2626; }
    .action-delete:hover   { background:#fee2e2; }
    .action-view           { background:#f0f9ff; color:#0369a1; }
    .action-view:hover     { background:#e0f2fe; }
    .action-participants       { background:#f5f3ff; color:#6d28d9; }
    .action-participants:hover { background:#ede9fe; }

    .stats-row  { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.6rem; }
    .stat-card  { background:#fff; border-radius:.85rem; box-shadow:0 2px 8px rgba(0,0,0,0.06); padding:1.2rem; display:flex; align-items:center; gap:.9rem; }
    .stat-icon  { width:44px; height:44px; border-radius:.65rem; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .stat-num   { font-size:1.5rem; font-weight:700; color:#1f2937; }
    .stat-label { font-size:.78rem; color:#6b7280; margin-top:.1rem; }

    .btn-new-event       { display:inline-flex; align-items:center; gap:.4rem; background:#2d6a35; color:#fff; padding:.6rem 1.3rem; border-radius:.65rem; border:none; font-family:inherit; font-size:.9rem; font-weight:700; cursor:pointer; text-decoration:none; transition:background .2s; }
    .btn-new-event:hover { background:#1e4d25; }

    .section-header    { display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; flex-wrap:wrap; gap:.6rem; }
    .section-header h3 { font-size:1rem; font-weight:700; color:#1f2937; }

    .nav-links           { display:flex; align-items:center; gap:.4rem; }
    .nav-link            { display:flex; align-items:center; gap:.38rem; text-decoration:none; color:#374151; font-size:.86rem; font-weight:500; padding:.42rem .85rem; border-radius:.55rem; transition:background .2s,color .2s; white-space:nowrap; border:1.5px solid transparent; }
    .nav-link:hover      { background:#f0fdf4; color:#2d6a35; border-color:#bbf7d0; }
    .nav-link.active     { background:#f0fdf4; color:#2d6a35; border-color:#bbf7d0; }

    /* Modal */
    .modal-overlay            { position:fixed; inset:0; background:rgba(0,0,0,0.45); display:flex; align-items:center; justify-content:center; z-index:999; opacity:0; pointer-events:none; transition:opacity .3s; }
    .modal-overlay.show       { opacity:1; pointer-events:all; }
    .modal-box                { background:#fff; border-radius:1.1rem; padding:2rem; max-width:380px; width:90%; text-align:center; transform:scale(.92); transition:transform .3s; box-shadow:0 20px 60px rgba(0,0,0,0.15); }
    .modal-overlay.show .modal-box { transform:scale(1); }
    .modal-icon-del           { width:62px; height:62px; border-radius:50%; background:#fee2e2; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem; }
    .modal-title              { font-size:1.15rem; font-weight:700; color:#1f2937; margin-bottom:.5rem; }
    .modal-text               { color:#6b7280; font-size:.88rem; line-height:1.6; margin-bottom:1.4rem; }
    .modal-btns               { display:flex; gap:.7rem; }
    .modal-cancel             { flex:1; padding:.7rem; background:#f3f4f6; color:#374151; border:none; border-radius:.65rem; font-family:inherit; font-size:.9rem; font-weight:600; cursor:pointer; }
    .modal-cancel:hover       { background:#e5e7eb; }
    .modal-confirm            { flex:1; padding:.7rem; background:#dc2626; color:#fff; border:none; border-radius:.65rem; font-family:inherit; font-size:.9rem; font-weight:700; cursor:pointer; }
    .modal-confirm:hover      { background:#b91c1c; }

    @media(max-width:640px) { .org-card-img{display:none;} .stats-row{grid-template-columns:1fr;} }
  </style>
</head>
<body>

<header>
  <div class="header-inner">
    <a href="home.php" class="brand"><img src="logo.jpg" alt="نفل"/></a>
    <div class="header-right">

      <div class="user-chip">
        <div class="user-avatar"><?= htmlspecialchars($userInit) ?></div>
        <span class="user-name"><?= htmlspecialchars($userName) ?></span>
      </div>

      <div class="badge-widget">
        <div class="badge-info">
          <div class="badge-name" style="color:<?= $badge['color'] ?>"><?= $badge['name'] ?></div>
          <div class="badge-pts"><?= $points ?> / <?= $badge['max'] ?> نقطة</div>
          <div class="progress-wrap">
            <div class="progress-bar" style="width:<?= $progress ?>%;background:<?= $badge['color'] ?>"></div>
          </div>
        </div>
      </div>

      <div class="nav-links">
        <a href="myEvent.html" class="nav-link">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 16l2 2 4-4"/></svg>
          <span>فعالياتي</span>
        </a>
        <a href="organized-events.php" class="nav-link active">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/><line x1="19" y1="8" x2="23" y2="8"/><line x1="21" y1="6" x2="21" y2="10"/></svg>
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
    <div class="hero-greeting">مرحباً يا <?= htmlspecialchars($userName) ?> 👋</div>
    <h2>فعالياتي المنظمة</h2>
    <p>تابع الفعاليات التي أنشأتها وأدِر مشاركيها</p>
  </div>
</section>

<main class="main">

  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-icon" style="background:#dcfce7">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2d6a35" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      </div>
      <div>
        <div class="stat-num"><?= (int)$stats['total'] ?></div>
        <div class="stat-label">إجمالي الفعاليات</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#fef9c3">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ca8a04" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
      <div>
        <div class="stat-num"><?= (int)$stats['pending'] ?></div>
        <div class="stat-label">قيد المراجعة</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#f5f3ff">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6d28d9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <div>
        <div class="stat-num"><?= (int)$stats['total_participants'] ?></div>
        <div class="stat-label">إجمالي المشاركين</div>
      </div>
    </div>
  </div>

  <div class="section-header">
    <h3>جميع الفعاليات</h3>
    <a href="create-event.php" class="btn-new-event">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      إنشاء فعالية جديدة
    </a>
  </div>

  <div class="tabs">
    <a href="?filter=all"      class="tab-btn <?= $filter==='all'      ? 'active':'' ?>">الكل</a>
    <a href="?filter=Pending"  class="tab-btn <?= $filter==='Pending'  ? 'active':'' ?>">قيد المراجعة</a>
    <a href="?filter=Approved" class="tab-btn <?= $filter==='Approved' ? 'active':'' ?>">مُعتمدة</a>
    <a href="?filter=Rejected" class="tab-btn <?= $filter==='Rejected' ? 'active':'' ?>">مرفوضة</a>
  </div>

  <?php if (empty($events)): ?>
    <div class="empty-state">
      <div style="font-size:2.5rem;margin-bottom:.6rem">📭</div>
      <h3>لا توجد فعاليات</h3>
      <p>لم تنشئي أي فعاليات بعد</p>
    </div>
  <?php else: ?>
    <?php foreach ($events as $ev):
      $s          = statusInfo($ev['status']);
      $cat        = categoryLabel($ev['category']);
      $isPending  = $ev['status'] === 'Pending';
      $isApproved = $ev['status'] === 'Approved';
    ?>
    <div class="org-card">
      <div class="org-card-inner">
        <?php if ($ev['image_path']): ?>
          <img class="org-card-img" src="<?= htmlspecialchars($ev['image_path']) ?>" alt="<?= htmlspecialchars($ev['title']) ?>"/>
        <?php endif; ?>
        <div class="org-card-body">
          <div class="org-card-top">
            <div class="org-card-title"><?= htmlspecialchars($ev['title']) ?></div>
            <span class="status-badge <?= $s['cls'] ?>"><?= $s['label'] ?></span>
          </div>
          <div class="org-meta">
            <div class="org-meta-item">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              <?= htmlspecialchars($ev['event_date']) ?>
            </div>
            <div class="org-meta-item">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              <?= htmlspecialchars($ev['location']) ?>
            </div>
            <div class="org-meta-item">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
              <?= (int)$ev['participants_count'] ?> مشارك
            </div>
            <div class="org-meta-item" style="color:#ca8a04">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
              <?= (int)$ev['points'] ?> نقطة
            </div>
          </div>
        </div>
      </div>

      <div class="org-card-actions">
        <?php if ($isApproved): ?>
          <a href="view-participants.php?id=<?= (int)$ev['event_id'] ?>" class="action-btn action-participants">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            عرض المشاركين
          </a>
        <?php endif; ?>

        <?php if ($isPending): ?>
          <a href="edit-event.php?id=<?= (int)$ev['event_id'] ?>" class="action-btn action-edit">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            تعديل
          </a>
          <button class="action-btn action-delete" onclick="openDeleteModal(<?= (int)$ev['event_id'] ?>)">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
            حذف
          </button>
        <?php endif; ?>

        <a href="ViewEvent.php?id=<?= (int)$ev['event_id'] ?>" class="action-btn action-view">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          عرض الفعالية
        </a>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

</main>

<!-- Modal تأكيد الحذف -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal-box">
    <div class="modal-icon-del">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
    </div>
    <div class="modal-title">حذف الفعالية</div>
    <div class="modal-text">هل أنتِ متأكدة من حذف هذه الفعالية؟<br>لا يمكن التراجع عن هذا الإجراء.</div>
    <form method="POST" action="">
      <input type="hidden" name="action"   value="delete"/>
      <input type="hidden" name="event_id" id="deleteEventId" value=""/>
      <div class="modal-btns">
        <button type="button" class="modal-cancel" onclick="closeDeleteModal()">إلغاء</button>
        <button type="submit" class="modal-confirm">حذف</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openDeleteModal(id) {
    document.getElementById('deleteEventId').value = id;
    document.getElementById('deleteModal').classList.add('show');
  }
  function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
  }
  document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
  });
</script>

</body>
</html>
