<?php
session_start();
require 'db_connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $age      = trim($_POST['age'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if ($name === '' || $email === '' || $password === '' || $confirm === '') {
        $error = 'الرجاء تعبئة جميع الحقول المطلوبة';
    } elseif ($password !== $confirm) {
        $error = 'كلمة المرور وتأكيدها غير متطابقتين';
    } elseif (strlen($password) < 6) {
        $error = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    } else {
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'هذا البريد الإلكتروني مسجل مسبقاً';
        } else {
            $ageInt = ($age === '60+' ? 60 : (int)$age);

            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, age, phone) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssis", $name, $email, $password, $ageInt, $phone);

            if ($stmt->execute()) {
                // ✅ التحويل لصفحة تسجيل الدخول مع رسالة نجاح
                $_SESSION['signup_success'] = 'تم إنشاء حسابك بنجاح! سجّل دخولك الآن';
                header("Location: login.php");
                exit;
            } else {
                $error = 'حدث خطأ أثناء إنشاء الحساب: ' . $conn->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>نفل - إنشاء حساب</title>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'IBM Plex Sans Arabic', sans-serif; background: linear-gradient(135deg, #f5f7f0 0%, #e8f0e0 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem 1rem; }
    .card { background: #fff; border-radius: 1.4rem; box-shadow: 0 20px 60px rgba(0,0,0,0.09); padding: 2.5rem; width: 100%; max-width: 420px; animation: fadeUp 0.5s ease; }
    @keyframes fadeUp { from{opacity:0;transform:translateY(24px);}to{opacity:1;transform:translateY(0);} }
    .logo-wrap { text-align: center; margin-bottom: 1.75rem; }
    .logo-wrap img { width: auto; height: 90px; object-fit: contain; filter: drop-shadow(0 4px 12px rgba(45,106,53,0.15)); margin-bottom: .5rem; }
    .logo-wrap h1 { font-size:1.5rem; font-weight:700; color:#1e4d25; margin-bottom:.2rem; }
    .logo-wrap p { color:#6b7280; font-size:.87rem; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: .85rem; margin-bottom: 1rem; }
    .form-group { margin-bottom: 1rem; }
    label { display:block; color:#374151; font-weight:500; margin-bottom:.4rem; font-size:.9rem; }
    input, select { width:100%; padding:.78rem 1rem; border:1.5px solid #d1d5db; border-radius:.65rem; font-family:inherit; font-size:.95rem; color:#111; background:#fafafa; transition:border-color .2s,box-shadow .2s; outline:none; appearance: none; }
    select { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position: left 1rem center; padding-left:2.5rem; }
    input:focus, select:focus { border-color:#2d6a35; box-shadow:0 0 0 3px rgba(45,106,53,0.1); background:#fff; }
    .alert-error { background:#fef2f2; border:1px solid #fecaca; color:#dc2626; padding:.7rem 1rem; border-radius:.6rem; font-size:.88rem; margin-bottom:1rem; text-align:center; }
    .btn-primary { width:100%; padding:.88rem; background:#2d6a35; color:#fff; border:none; border-radius:.7rem; font-size:1.05rem; font-weight:600; font-family:inherit; cursor:pointer; transition:background .2s,transform .1s; margin-top:.4rem; }
    .btn-primary:hover { background:#1e4d25; }
    .btn-primary:active { transform:scale(0.98); }
    .footer-text { text-align:center; margin-top:1.3rem; color:#4b5563; font-size:.9rem; }
    .footer-text a { color:#2d6a35; text-decoration:none; font-weight:600; }
    .footer-text a:hover { text-decoration:underline; }
  </style>
</head>
<body>
  <div class="card">
    <div class="logo-wrap">
      <img src="logo.jpg" alt="نفل" />
      <h1>إنشاء حساب جديد</h1>
      <p>انضم لمجتمع نفل البيئي</p>
    </div>

    <?php if ($error): ?>
      <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="signup.php">
      <div class="form-group">
        <label for="name">الاسم الكامل</label>
        <input type="text" id="name" name="name" placeholder="أدخل اسمك الكامل" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"/>
      </div>
      <div class="form-row">
        <div class="form-group" style="margin-bottom:0">
          <label for="age">العمر</label>
          <select id="age" name="age" required>
            <option value="" disabled <?= !isset($_POST['age']) ? 'selected' : '' ?>>اختر</option>
            <?php
              $ages = array_merge(range(15,35), [40,45,50,55,'60+']);
              $sel = $_POST['age'] ?? '';
              foreach ($ages as $a) {
                $s = ($sel == $a) ? 'selected' : '';
                echo "<option $s>$a</option>";
              }
            ?>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label for="phone">رقم الجوال</label>
          <input type="tel" id="phone" name="phone" placeholder="05xxxxxxxx" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"/>
        </div>
      </div>
      <div class="form-group" style="margin-top:1rem">
        <label for="email">البريد الإلكتروني</label>
        <input type="email" id="email" name="email" placeholder="example@email.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>
      </div>
      <div class="form-group">
        <label for="password">كلمة المرور</label>
        <input type="password" id="password" name="password" placeholder="••••••••" required minlength="6" />
      </div>
      <div class="form-group">
        <label for="confirm">تأكيد كلمة المرور</label>
        <input type="password" id="confirm" name="confirm" placeholder="••••••••" required />
      </div>
      <button type="submit" class="btn-primary">إنشاء الحساب</button>
    </form>
    <div class="footer-text">
      لديك حساب بالفعل؟ <a href="login.php">تسجيل الدخول</a>
    </div>
  </div>
</body>
</html>
