<?php
session_start();
require_once 'db_connect.php';

// التحقق من صلاحية المشرف
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: admin-login.php");
    exit();
}

$adminName = $_SESSION['full_name'];
$adminInitial = mb_substr($adminName, 0, 1, 'UTF-8');

// ======= معالجة الاعتماد / الرفض =======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['event_id'])) {
    $eventId = (int)$_POST['event_id'];
    $action  = $_POST['action'];

    if ($action === 'approve') {
        $newStatus = 'Approved';
    } elseif ($action === 'reject') {
        $newStatus = 'Rejected';
    }

    if (isset($newStatus)) {
        $stmt = $conn->prepare("UPDATE events SET status = ? WHERE event_id = ?");
        $stmt->bind_param("si", $newStatus, $eventId);
        $stmt->execute();
    }

    // redirect لتجنب إعادة الإرسال عند refresh
    header("Location: admin-review.php?filter=" . urlencode($_POST['filter'] ?? 'Pending'));
    exit();
}

// ======= جلب الفعاليات من DB =======
$filter = $_GET['filter'] ?? 'Pending';
$allowedFilters = ['Pending', 'Approved', 'Rejected', 'all'];
if (!in_array($filter, $allowedFilters)) $filter = 'Pending';

if ($filter === 'all') {
    $result = $conn->query("SELECT e.*, u.full_name AS organizer_name 
                            FROM events e 
                            JOIN users u ON e.created_by_user_id = u.user_id 
                            ORDER BY e.event_id DESC");
} else {
    $stmt = $conn->prepare("SELECT e.*, u.full_name AS organizer_name 
                            FROM events e 
                            JOIN users u ON e.created_by_user_id = u.user_id 
                            WHERE e.status = ? 
                            ORDER BY e.event_id DESC");
    $stmt->bind_param("s", $filter);
    $stmt->execute();
    $result = $stmt->get_result();
}
$events = $result->fetch_all(MYSQLI_ASSOC);

// ======= إحصائيات =======
$statsResult = $conn->query("SELECT 
    COUNT(*) AS total,
    SUM(status='Pending')  AS pending,
    SUM(status='Approved') AS approved,
    SUM(status='Rejected') AS rejected
    FROM events");
$stats = $statsResult->fetch_assoc();

// دالة تحويل status لعربي
function statusLabel($s) {
    return match($s) {
        'Pending'  => ['label' => 'قيد المراجعة', 'cls' => 'status-pending'],
        'Approved' => ['label' => 'مُعتمدة',      'cls' => 'status-approved'],
        'Rejected' => ['label' => 'مرفوضة',       'cls' => 'status-rejected'],
        default    => ['label' => $s,             'cls' => ''],
    };
}

// دالة تحويل category لعربي
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
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>نفل - لوحة المشرف</title>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="styles.css"/>
  <style>
    .admin-hero { background: linear-gradient(135deg, #1e3a5f 0%, #0f2035 100%); }

    .stats-row {
      display: grid; grid-template-columns: repeat(4,1fr); gap: 1rem;
      margin-bottom: 1.6rem;
    }
    .stat-card {
      background: #fff; border-radius: .85rem;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
      padding: 1.1rem 1.2rem;
      display: flex; align-items: center; gap: .85rem;
    }
    .stat-icon { width: 42px; height: 42px; border-radius: .6rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .stat-num  { font-size: 1.4rem; font-weight: 700; color: #1f2937; }
    .stat-label{ font-size: .76rem; color: #6b7280; margin-top: .1rem; }

    .review-card {
      background: #fff; border-radius: .9rem;
      box-shadow: 0 2px 8px rgba(0,0,0,0.07);
      margin-bottom: 1.1rem; overflow: hidden;
      transition: box-shadow .25s; animation: slideIn .35s ease;
    }
    @keyframes slideIn { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
    .review-card:hover { box-shadow: 0 6px 24px rgba(0,0,0,0.1); }
    .review-card-inner { display: flex; }
    .review-img { width: 150px; flex-shrink: 0; object-fit: cover; }
    .review-body { padding: 1.2rem 1.4rem; flex: 1; display: flex; flex-direction: column; gap: .5rem; }
    .review-top { display: flex; align-items: flex-start; justify-content: space-between; gap: .6rem; }
    .review-title { font-size: 1rem; font-weight: 700; color: #1f2937; line-height: 1.45; }
    .review-organizer { font-size: .82rem; color: #6b7280; }
    .review-organizer span { color: #2d6a35; font-weight: 600; }
    .review-desc { font-size: .85rem; color: #4b5563; line-height: 1.65; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .review-meta { display: flex; flex-wrap: wrap; gap: .5rem 1.1rem; }
    .review-meta-item { display: flex; align-items: center; gap: .35rem; font-size: .8rem; color: #6b7280; }

    .review-actions {
      display: flex; gap: .6rem; padding: .9rem 1.4rem;
      border-top: 1px solid #f3f4f6; background: #fafafa;
      align-items: center; flex-wrap: wrap;
    }
    .btn-approve {
      padding: .55rem 1.4rem; background: #2d6a35; color: #fff;
      border: none; border-radius: .6rem; font-family: inherit;
      font-size: .88rem; font-weight: 700; cursor: pointer;
      display: flex; align-items: center; gap: .4rem; transition: background .2s;
    }
    .btn-approve:hover { background: #1e4d25; }
    .btn-reject {
      padding: .55rem 1.4rem; background: #fef2f2; color: #dc2626;
      border: 1.5px solid #fecaca; border-radius: .6rem; font-family: inherit;
      font-size: .88rem; font-weight: 700; cursor: pointer;
      display: flex; align-items: center; gap: .4rem; transition: background .2s;
    }
    .btn-reject:hover { background: #fee2e2; }
    .btn-view-detail {
      padding: .52rem 1rem; background: #f0f9ff; color: #0369a1;
      border: 1.5px solid #bae6fd; border-radius: .6rem; font-family: inherit;
      font-size: .84rem; font-weight: 600; cursor: pointer;
      margin-right: auto; text-decoration: none;
      display: inline-flex; align-items: center; gap: .35rem; transition: background .2s;
    }
    .btn-view-detail:hover { background: #e0f2fe; }

    .status-badge { padding: .28rem .75rem; border-radius: 99px; font-size: .77rem; font-weight: 600; white-space: nowrap; flex-shrink: 0; }
    .status-pending  { background: #fef9c3; color: #854d0e; }
    .status-approved { background: #dcfce7; color: #166534; }
    .status-rejected { background: #fee2e2; color: #991b1b; }

    .tabs { margin-bottom: 1.2rem; }
    .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; flex-wrap: wrap; gap: .6rem; }
    .section-header h3 { font-size: 1rem; font-weight: 700; color: #1f2937; }

    /* Modal */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 999; opacity: 0; pointer-events: none; transition: opacity .3s; }
    .modal-overlay.show { opacity: 1; pointer-events: all; }
    .modal-box { background: #fff; border-radius: 1.1rem; padding: 2rem; max-width: 400px; width: 90%; text-align: center; transform: scale(.92); transition: transform .3s; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
    .modal-overlay.show .modal-box { transform: scale(1); }
    .modal-icon { width: 62px; height: 62px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; }
    .modal-title { font-size: 1.1rem; font-weight: 700; color: #1f2937; margin-bottom: .5rem; }
    .modal-text  { color: #6b7280; font-size: .88rem; line-height: 1.65; margin-bottom: 1.3rem; }
    .modal-btns  { display: flex; gap: .7rem; }
    .modal-cancel  { flex:1; padding:.7rem; background:#f3f4f6; color:#374151; border:none; border-radius:.65rem; font-family:inherit; font-size:.9rem; font-weight:600; cursor:pointer; }
    .modal-cancel:hover { background:#e5e7eb; }
    .modal-approve { flex:1; padding:.7rem; background:#2d6a35; color:#fff; border:none; border-radius:.65rem; font-family:inherit; font-size:.9rem; font-weight:700; cursor:pointer; }
    .modal-approve:hover { background:#1e4d25; }
    .modal-reject  { flex:1; padding:.7rem; background:#dc2626; color:#fff; border:none; border-radius:.65rem; font-family:inherit; font-size:.9rem; font-weight:700; cursor:pointer; }
    .modal-reject:hover { background:#b91c1c; }

    /* Toast */
    .toast { position: fixed; bottom: 1.5rem; left: 50%; transform: translateX(-50%) translateY(80px); color: #fff; padding: .75rem 1.4rem; border-radius: .7rem; font-size: .9rem; font-weight: 600; box-shadow: 0 8px 24px rgba(0,0,0,0.18); transition: transform .35s ease; z-index: 9999; white-space: nowrap; display: flex; align-items: center; gap: .5rem; }
    .toast.show { transform: translateX(-50%) translateY(0); }
    .toast.approved { background: #1e4d25; }
    .toast.rejected { background: #991b1b; }

    @media(max-width:768px){ .stats-row{grid-template-columns:repeat(2,1fr);} .review-img{display:none;} }
    @media(max-width:480px){ .stats-row{grid-template-columns:1fr 1fr;} }
  </style>
</head>
<body>

<header class="topbar">
  <div class="topbar-inner">
    <a href="home.php" class="brand"><img src="logo.jpg" alt="نفل"/></a>
    <nav class="navbar-links">
      <a href="admin-review.php" class="active">لوحة المشرف</a>
    </nav>
    <div class="header-right">
      <div class="user-chip" style="background:#eff6ff;border-color:#bfdbfe">
        <div class="user-avatar" style="background:#1e3a5f"><?= htmlspecialchars($adminInitial) ?></div>
        <span class="user-name" style="color:#1e3a5f"><?= htmlspecialchars($adminName) ?></span>
      </div>
      <a href="logout.php" class="btn-logout">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        خروج
      </a>
    </div>
  </div>
</header>

<section class="hero admin-hero">
  <div class="hero-inner">
    <div class="hero-greeting">لوحة تحكم المشرف ⚙️</div>
    <h2>مراجعة الفعاليات</h2>
    <p>راجع الفعاليات المقدمة واعتمدها أو ارفضها</p>
  </div>
</section>

<main class="main">

  <!-- إحصائيات من DB -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fef9c3">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ca8a04" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
      <div><div class="stat-num"><?= (int)$stats['pending'] ?></div><div class="stat-label">قيد المراجعة</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#dcfce7">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2d6a35" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      </div>
      <div><div class="stat-num"><?= (int)$stats['approved'] ?></div><div class="stat-label">مُعتمدة</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#fee2e2">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      </div>
      <div><div class="stat-num"><?= (int)$stats['rejected'] ?></div><div class="stat-label">مرفوضة</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#f5f3ff">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6d28d9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      </div>
      <div><div class="stat-num"><?= (int)$stats['total'] ?></div><div class="stat-label">إجمالي الفعاليات</div></div>
    </div>
  </div>

  <!-- تبويبات الفلترة -->
  <div class="section-header">
    <h3>الفعاليات</h3>
  </div>
  <div class="tabs">
    <a href="?filter=Pending"  class="tab-btn <?= $filter==='Pending'  ? 'active' : '' ?>">⏳ قيد المراجعة</a>
    <a href="?filter=Approved" class="tab-btn <?= $filter==='Approved' ? 'active' : '' ?>">✅ مُعتمدة</a>
    <a href="?filter=Rejected" class="tab-btn <?= $filter==='Rejected' ? 'active' : '' ?>">❌ مرفوضة</a>
    <a href="?filter=all"      class="tab-btn <?= $filter==='all'      ? 'active' : '' ?>">الكل</a>
  </div>

  <!-- قائمة الفعاليات -->
  <?php if (empty($events)): ?>
    <div class="empty-state" style="padding:2.5rem; text-align:center">
      <div style="font-size:2.5rem;margin-bottom:.6rem">📭</div>
      <h3>لا توجد فعاليات</h3>
      <p>لا توجد فعاليات في هذا التصنيف</p>
    </div>
  <?php else: ?>
    <?php foreach ($events as $ev): 
      $s   = statusLabel($ev['status']);
      $cat = categoryLabel($ev['category']);
    ?>
    <div class="review-card">
      <div class="review-card-inner">
        <?php if ($ev['image_path']): ?>
          <img class="review-img" src="<?= htmlspecialchars($ev['image_path']) ?>" alt="<?= htmlspecialchars($ev['title']) ?>"/>
        <?php endif; ?>
        <div class="review-body">
          <div class="review-top">
            <div>
              <div class="review-title"><?= htmlspecialchars($ev['title']) ?></div>
              <div class="review-organizer">المنظم: <span><?= htmlspecialchars($ev['organizer_name']) ?></span></div>
            </div>
            <span class="status-badge <?= $s['cls'] ?>"><?= $s['label'] ?></span>
          </div>
          <div class="review-desc"><?= htmlspecialchars($ev['description']) ?></div>
          <div class="review-meta">
            <div class="review-meta-item">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              <?= htmlspecialchars($ev['event_date']) ?>
            </div>
            <div class="review-meta-item">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              <?= htmlspecialchars($ev['location']) ?>
            </div>
            <div class="review-meta-item" style="color:#2d6a35"><?= $cat ?></div>
            <div class="review-meta-item" style="color:#ca8a04">⭐ <?= (int)$ev['points'] ?> نقطة</div>
          </div>
        </div>
      </div>

      <div class="review-actions">
        <a href="admin-view-event.php?id=<?= (int)$ev['event_id'] ?>" class="btn-view-detail">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          عرض التفاصيل
        </a>
        <?php if ($ev['status'] === 'Pending'): ?>
          <!-- زر الاعتماد -->
          <button class="btn-approve" onclick="openModal('approve', <?= (int)$ev['event_id'] ?>)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            اعتماد
          </button>
          <!-- زر الرفض -->
          <button class="btn-reject" onclick="openModal('reject', <?= (int)$ev['event_id'] ?>)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            رفض
          </button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

</main>

<!-- Modal الاعتماد -->
<div class="modal-overlay" id="approveModal">
  <div class="modal-box">
    <div class="modal-icon" style="background:#dcfce7">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#2d6a35" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    </div>
    <div class="modal-title">اعتماد الفعالية</div>
    <div class="modal-text">هل تريد اعتماد هذه الفعالية؟<br>ستظهر للمستخدمين فور الاعتماد.</div>
    <form method="POST" action="">
      <input type="hidden" name="action"   value="approve"/>
      <input type="hidden" name="event_id" id="approveEventId" value=""/>
      <input type="hidden" name="filter"   value="<?= htmlspecialchars($filter) ?>"/>
      <div class="modal-btns">
        <button type="button" class="modal-cancel" onclick="closeModal('approveModal')">إلغاء</button>
        <button type="submit" class="modal-approve">اعتماد</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal الرفض -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal-box">
    <div class="modal-icon" style="background:#fee2e2">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
    </div>
    <div class="modal-title">رفض الفعالية</div>
    <div class="modal-text">هل تريد رفض هذه الفعالية؟<br>لن تظهر للمستخدمين.</div>
    <form method="POST" action="">
      <input type="hidden" name="action"   value="reject"/>
      <input type="hidden" name="event_id" id="rejectEventId" value=""/>
      <input type="hidden" name="filter"   value="<?= htmlspecialchars($filter) ?>"/>
      <div class="modal-btns">
        <button type="button" class="modal-cancel" onclick="closeModal('rejectModal')">إلغاء</button>
        <button type="submit" class="modal-reject">رفض</button>
      </div>
    </form>
  </div>
</div>

<!-- Toast إشعار النجاح -->
<?php if (isset($_GET['done'])): ?>
<div class="toast <?= $_GET['done'] === 'approve' ? 'approved' : 'rejected' ?> show" id="toast">
  <?= $_GET['done'] === 'approve' ? '✅ تم اعتماد الفعالية بنجاح' : '❌ تم رفض الفعالية' ?>
</div>
<script>setTimeout(() => document.getElementById('toast').classList.remove('show'), 2500);</script>
<?php endif; ?>

<script>
  function openModal(type, eventId) {
    if (type === 'approve') {
      document.getElementById('approveEventId').value = eventId;
      document.getElementById('approveModal').classList.add('show');
    } else {
      document.getElementById('rejectEventId').value = eventId;
      document.getElementById('rejectModal').classList.add('show');
    }
  }
  function closeModal(id) {
    document.getElementById(id).classList.remove('show');
  }
  // إغلاق عند الضغط خارج الـ modal
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
      if (e.target === this) this.classList.remove('show');
    });
  });
</script>

</body>
</html>
