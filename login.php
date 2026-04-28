<?php
session_start();
require 'db_connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'الرجاء تعبئة جميع الحقول';
    } else {
        // مقارنة مباشرة (بدون تشفير) - أسهل للاختبار
        $stmt = $conn->prepare("SELECT user_id, full_name, email, total_points, profile_image_path, is_admin FROM users WHERE email = ? AND password = ? LIMIT 1");
        $stmt->bind_param("ss", $email, $password);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $_SESSION['user_id']      = $user['user_id'];
            $_SESSION['full_name']    = $user['full_name'];
            $_SESSION['email']        = $user['email'];
            $_SESSION['total_points'] = $user['total_points'];
            $_SESSION['profile_image']= $user['profile_image_path'];
            $_SESSION['is_admin']     = $user['is_admin'];
            header("Location: home.php");
            exit;
        } else {
            $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>نفل - تسجيل الدخول</title>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'IBM Plex Sans Arabic', sans-serif;
      background: linear-gradient(135deg, #f5f7f0 0%, #e8f0e0 100%);
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center; padding: 1rem;
    }
    .card {
      background: #fff; border-radius: 1.4rem;
      box-shadow: 0 20px 60px rgba(0,0,0,0.09);
      padding: 2.8rem 2.5rem; width: 100%; max-width: 420px;
      animation: fadeUp 0.5s ease;
    }
    @keyframes fadeUp { from{opacity:0;transform:translateY(24px);}to{opacity:1;transform:translateY(0);} }

    .logo-wrap { text-align: center; margin-bottom: 2rem; }
    .logo-wrap img {
      width: auto; height: 100px;
      object-fit: contain;
      filter: drop-shadow(0 4px 16px rgba(45,106,53,0.18));
      margin-bottom: .5rem;
    }
    .logo-wrap p { color:#6b7280; font-size:.93rem; }

    .form-group { margin-bottom: 1.1rem; }
    label { display:block; color:#374151; font-weight:500; margin-bottom:.45rem; font-size:.93rem; }
    input { width:100%; padding:.82rem 1rem; border:1.5px solid #d1d5db; border-radius:.65rem; font-family:inherit; font-size:.97rem; color:#111; background:#fafafa; transition:border-color .2s,box-shadow .2s; outline:none; }
    input:focus { border-color:#2d6a35; box-shadow:0 0 0 3px rgba(45,106,53,0.1); background:#fff; }

    .btn-primary { width:100%; padding:.88rem; background:#2d6a35; color:#fff; border:none; border-radius:.7rem; font-size:1.05rem; font-weight:600; font-family:inherit; cursor:pointer; transition:background .2s,transform .1s; margin-top:.5rem; }
    .btn-primary:hover { background:#1e4d25; }
    .btn-primary:active { transform:scale(0.98); }

    .footer-text { text-align:center; margin-top:1.5rem; color:#4b5563; font-size:.93rem; }
    .footer-text a { color:#2d6a35; text-decoration:none; font-weight:600; }
    .footer-text a:hover { text-decoration:underline; }

    .alert-error {
      background:#fef2f2; border:1px solid #fecaca; color:#dc2626;
      padding:.7rem 1rem; border-radius:.6rem; font-size:.88rem;
      margin-bottom:1rem; text-align:center;
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="logo-wrap">
      <img src="logo.jpg" alt="نفل" />
      <p>منصة الفعاليات البيئية المجتمعية</p>
    </div>

    <?php if ($error): ?>
      <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <div class="form-group">
        <label for="email">البريد الإلكتروني</label>
        <input type="email" id="email" name="email" placeholder="example@email.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>
      </div>
      <div class="form-group">
        <label for="password">كلمة المرور</label>
        <input type="password" id="password" name="password" placeholder="••••••••" required />
      </div>
      <button type="submit" class="btn-primary">تسجيل الدخول</button>
    </form>

    <div class="footer-text">
      ليس لديك حساب؟ <a href="signup.php">إنشاء حساب جديد</a>
    </div>
  </div>
</body>
</html>
