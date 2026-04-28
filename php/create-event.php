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

$errors = [];

// ======= معالجة الإرسال =======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title']       ?? '');
    $category    = $_POST['category']         ?? '';
    $description = trim($_POST['description'] ?? '');
    $eventDate   = $_POST['event_date']       ?? '';
    $startTime   = $_POST['start_time']       ?? '';
    $endTime     = $_POST['end_time']         ?? '';
    $location    = trim($_POST['location']    ?? '');
    $contact     = trim($_POST['contact']     ?? '');
    $targetAge   = $_POST['target_age']       ?? 'All';
    $points      = (int)($_POST['points']     ?? 15);
    $certAvail   = isset($_POST['cert'])      ? 1 : 0;

    // حساب ساعات التطوع
    $volunteerHours = 1;
    if ($startTime && $endTime && $endTime > $startTime) {
        $s = new DateTime("2000-01-01 $startTime");
        $e = new DateTime("2000-01-01 $endTime");
        $volunteerHours = max(1, round($e->diff($s)->h + $e->diff($s)->i / 60, 1));
    }

    // validation
    if (!$title)     $errors[] = 'عنوان الفعالية مطلوب';
    if (!$category)  $errors[] = 'التصنيف مطلوب';
    if (!$description) $errors[] = 'وصف الفعالية مطلوب';
    if (!$eventDate) $errors[] = 'تاريخ الفعالية مطلوب';
    if (!$location)  $errors[] = 'الموقع مطلوب';

    // رفع الصورة
    $imagePath = null;
    if (!empty($_FILES['image']['name'])) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (in_array($_FILES['image']['type'], $allowed)) {
            $ext      = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = 'event_' . time() . '_' . $userId . '.' . $ext;
            $dest     = 'uploads/' . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                $imagePath = $dest;
            }
        } else {
            $errors[] = 'نوع الصورة غير مدعوم (JPG, PNG, WEBP فقط)';
        }
    }

    if (empty($errors)) {
        // كود حضور عشوائي
        $attendanceCode = 'NAFL' . strtoupper(substr(md5(uniqid()), 0, 4));

        $stmt = $conn->prepare(
            "INSERT INTO events
                (title, description, category, event_date, start_time, end_time,
                 location, contact_number, volunteer_hours, target_age_group,
                 points, certificate_available, image_path, status, attendance_code, created_by_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?)"
        );
        $stmt->bind_param(
            "ssssssssisiissi",
            $title, $description, $category,
            $eventDate, $startTime, $endTime,
            $location, $contact, $volunteerHours,
            $targetAge, $points, $certAvail,
            $imagePath, $attendanceCode, $userId
        );
        $stmt->execute();
        header("Location: organized-events.php?created=1");
        exit();
    }
}

// نقاط وشارة المستخدم للهيدر
$userStmt = $conn->prepare("SELECT total_points FROM users WHERE user_id = ?");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$points_user = (int)$userStmt->get_result()->fetch_assoc()['total_points'];

function getBadge($pts) {
    if ($pts >= 100) return ['name' => '🥇 ذهبي',  'color' => '#ca8a04', 'max' => 200];
    if ($pts >= 50)  return ['name' => '🥈 فضي',   'color' => '#6b7280', 'max' => 100];
    return               ['name' => '🥉 برونزي', 'color' => '#b45309', 'max' => 50];
}
$badge    = getBadge($points_user);
$progress = min(100, round(($points_user / $badge['max']) * 100));

$categories = [
    'Cleaning'   => '🧹 تنظيف',
    'Planting'   => '🌱 زراعة',
    'Recycling'  => '♻️ إعادة تدوير',
    'Awareness'  => '📢 توعية',
    'Exhibition' => '🎪 معرض',
];
$ageGroups = [
    'All'      => 'الجميع',
    'Children' => 'أطفال',
    'Teens'    => 'مراهقين',
    'Adults'   => 'بالغون',
];
$pointsOptions = [5, 10, 15, 20];

// استرجاع قيم الفورم بعد خطأ
$old = $_POST;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>نفل - إنشاء فعالية جديدة</title>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="styles.css"/>
  <style>
    .form-card { background:#fff; border-radius:1rem; box-shadow:0 2px 12px rgba(0,0,0,0.07); overflow:hidden; animation:fadeUp .45s ease; max-width:850px; margin:2rem auto; }
    @keyframes fadeUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
    .form-body { padding:2rem; }

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

    .image-upload-wrapper { border:2px dashed #d1d5db; border-radius:.8rem; padding:2rem; text-align:center; background:#fafafa; cursor:pointer; transition:all .2s; margin-top:.5rem; }
    .image-upload-wrapper:hover { border-color:#2d6a35; background:#f0fdf4; }
    .upload-icon { font-size:2rem; color:#9ca3af; margin-bottom:.5rem; }
    .preview-container img { max-width:100%; border-radius:.6rem; margin-top:1rem; display:none; max-height:200px; object-fit:cover; }

    .form-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
    .form-row-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem; }

    .section-divider { font-size:.8rem; font-weight:700; color:#9ca3af; margin:1.5rem 0 1rem; display:flex; align-items:center; gap:.6rem; }
    .section-divider::after { content:''; flex:1; height:1px; background:#f3f4f6; }

    .toggle-row { display:flex; align-items:center; justify-content:space-between; padding:.85rem 1rem; background:#f5f7f0; border-radius:.65rem; }
    .switch { position:relative; width:44px; height:24px; flex-shrink:0; }
    .switch input { opacity:0; width:0; height:0; }
    .slider { position:absolute; inset:0; background:#d1d5db; border-radius:99px; cursor:pointer; transition:.3s; }
    .slider::before { content:''; position:absolute; width:18px; height:18px; background:#fff; border-radius:50%; top:3px; right:3px; transition:.3s; box-shadow:0 1px 3px rgba(0,0,0,0.2); }
    .switch input:checked + .slider { background:#2d6a35; }
    .switch input:checked + .slider::before { transform:translateX(-20px); }

    .pts-opt { padding:.5rem 1.2rem; border-radius:99px; border:1.5px solid #d1d5db; background:#fff; cursor:pointer; transition:all .2s; font-size:.88rem; font-weight:600; font-family:inherit; color:#374151; }
    .pts-opt:hover  { border-color:#2d6a35; color:#2d6a35; }
    .pts-opt.active { background:#2d6a35; color:#fff; border-color:#2d6a35; }

    .form-footer { display:flex; gap:.8rem; justify-content:flex-end; align-items:center; padding:1.3rem 2rem; border-top:1px solid #f3f4f6; background:#fafafa; flex-wrap:wrap; }
    .btn-cancel-page { text-decoration:none; color:#6b7280; font-size:.9rem; padding:.72rem 1.2rem; border-radius:.65rem; transition:background .2s; }
    .btn-cancel-page:hover { background:#f3f4f6; }
    .btn-submit-custom { display:inline-flex; align-items:center; justify-content:center; background:#2d6a35; color:#fff; padding:12px 38px; border-radius:16px; border:none; font-family:inherit; font-size:1rem; font-weight:600; cursor:pointer; transition:all .3s ease; }
    .btn-submit-custom:hover { background:#1e4d25; transform:translateY(-1px); }

    .nav-links       { display:flex; align-items:center; gap:.4rem; }
    .nav-link        { display:flex; align-items:center; gap:.38rem; text-decoration:none; color:#374151; font-size:.86rem; font-weight:500; padding:.42rem .85rem; border-radius:.55rem; transition:background .2s,color .2s; white-space:nowrap; border:1.5px solid transparent; }
    .nav-link:hover  { background:#f0fdf4; color:#2d6a35; border-color:#bbf7d0; }
    .nav-link.active { background:#f0fdf4; color:#2d6a35; border-color:#bbf7d0; }

    @media(max-width:640px){ .form-row-2,.form-row-3{grid-template-columns:1fr;} .form-body,.form-footer{padding:1.2rem;} }
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
          <div class="badge-pts"><?= $points_user ?> / <?= $badge['max'] ?> نقطة</div>
          <div class="progress-wrap">
            <div class="progress-bar" style="width:<?= $progress ?>%;background:<?= $badge['color'] ?>"></div>
          </div>
        </div>
      </div>

      <div class="nav-links">
        <a href="myEvent.php" class="nav-link">
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
    <div class="hero-greeting">إنشاء فعالية</div>
    <h2>إضافة فعالية جديدة</h2>
    <p>املئي البيانات أدناه لإرسال فعاليتك للمراجعة والاعتماد</p>
  </div>
</section>

<main class="main">
  <div class="form-card">
    <form method="POST" action="" enctype="multipart/form-data">
      <div class="form-body">

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
          <input type="text" name="title" placeholder="مثلاً: مبادرة تنظيف منتزه الملك سلمان" required
                 value="<?= htmlspecialchars($old['title'] ?? '') ?>"/>
        </div>

        <div class="form-group">
          <label>التصنيف <span class="req">*</span></label>
          <select name="category" required>
            <option value="" disabled <?= empty($old['category']) ? 'selected' : '' ?>>اختر التصنيف...</option>
            <?php foreach ($categories as $val => $label): ?>
              <option value="<?= $val ?>" <?= ($old['category'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>وصف الفعالية <span class="req">*</span></label>
          <textarea name="description" placeholder="اشرحي تفاصيل الفعالية وشروط المشاركة..." required><?= htmlspecialchars($old['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label>صورة الفعالية</label>
          <div class="image-upload-wrapper" onclick="document.getElementById('imgInput').click()">
            <div class="upload-icon">📸</div>
            <div style="font-size:.85rem;color:#6b7280">اضغطي لرفع صورة تعبيرية للفعالية</div>
            <input type="file" name="image" id="imgInput" hidden accept="image/*" onchange="previewImg(this)"/>
            <div class="preview-container"><img id="viewImg" src="#"/></div>
          </div>
        </div>

        <div class="section-divider">التاريخ والموقع</div>

        <div class="form-row-2">
          <div class="form-group">
            <label>تاريخ الفعالية <span class="req">*</span></label>
            <input type="date" name="event_date" required value="<?= htmlspecialchars($old['event_date'] ?? '') ?>"/>
          </div>
          <div class="form-group">
            <label>الفئة العمرية</label>
            <select name="target_age">
              <?php foreach ($ageGroups as $val => $label): ?>
                <option value="<?= $val ?>" <?= ($old['target_age'] ?? 'All') === $val ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-row-3">
          <div class="form-group">
            <label>وقت البداية</label>
            <input type="time" name="start_time" id="startTime" onchange="calcHours()"
                   value="<?= htmlspecialchars($old['start_time'] ?? '') ?>"/>
          </div>
          <div class="form-group">
            <label>وقت النهاية</label>
            <input type="time" name="end_time" id="endTime" onchange="calcHours()"
                   value="<?= htmlspecialchars($old['end_time'] ?? '') ?>"/>
          </div>
          <div class="form-group">
            <label>ساعات التطوع</label>
            <input type="number" id="volunteerHoursDisplay" value="1" min="1" step="1"
                   style="background:#f3f4f6;" onkeydown="return false;" readonly/>
          </div>
        </div>

        <div class="form-group">
          <label>الموقع بالتفصيل <span class="req">*</span></label>
          <input type="text" name="location" placeholder="المدينة، الحي، الحديقة أو المركز" required
                 value="<?= htmlspecialchars($old['location'] ?? '') ?>"/>
        </div>

        <div class="form-group">
          <label>رقم التواصل</label>
          <input type="tel" name="contact" placeholder="05xxxxxxxx"
                 value="<?= htmlspecialchars($old['contact'] ?? '') ?>"/>
        </div>

        <div class="section-divider">الإعدادات الإضافية</div>

        <div class="toggle-row" style="margin-bottom:.9rem">
          <div>
            <div style="font-size:.9rem;font-weight:600;color:#374151">🏅 منح شهادة حضور</div>
          </div>
          <label class="switch">
            <input type="checkbox" name="cert" id="certAvailable" <?= isset($old['cert']) ? 'checked' : '' ?>/>
            <span class="slider"></span>
          </label>
        </div>

        <div class="form-group">
          <label>نقاط المشاركة</label>
          <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:.5rem">
            <?php foreach ($pointsOptions as $opt): ?>
              <button type="button" class="pts-opt <?= ((int)($old['points'] ?? 15)) === $opt ? 'active' : '' ?>"
                      data-val="<?= $opt ?>" onclick="selectPoints(this)">
                <?= $opt ?> نقطة
              </button>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="points" id="eventPoints" value="<?= (int)($old['points'] ?? 15) ?>"/>
        </div>

      </div>

      <div class="form-footer">
        <a href="organized-events.php" class="btn-cancel-page">إلغاء</a>
        <button type="submit" class="btn-submit-custom">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left:.4rem"><path d="M22 2L11 13"/><path d="M22 2L15 22l-4-9-9-4 20-7z"/></svg>
          إرسال للمراجعة
        </button>
      </div>
    </form>
  </div>
</main>

<script>
  function previewImg(input) {
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = function(e) {
        const img = document.getElementById('viewImg');
        img.src = e.target.result;
        img.style.display = 'block';
      };
      reader.readAsDataURL(input.files[0]);
    }
  }

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
