<?php
session_start();
require_once 'db_connect.php';

// إذا مسجل دخول مسبقاً روح للوحة التحكم
if (isset($_SESSION['user_id']) && $_SESSION['is_admin'] == 1) {
    header("Location: admin-review.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND is_admin = 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();

    // كلمة المرور مخزنة كـ plain text في الـ DB (123456)
    if ($user && $user['password'] === $password) {
        $_SESSION['user_id']   = $user['user_id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['is_admin']  = $user['is_admin'];
        header("Location: admin-review.php");
        exit();
    } else {
        $error = "بيانات المسؤول غير صحيحة";
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>تسجيل دخول الأدمن</title>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'IBM Plex Sans Arabic', sans-serif;
      background: linear-gradient(135deg, #f5f7f0 0%, #e8f0e0 100%);
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .card {
      background: #fff;
      border-radius: 1.4rem;
      box-shadow: 0 20px 60px rgba(0,0,0,0.09);
      padding: 2.5rem;
      width: 100%;
      max-width: 400px;
    }
    .logo { text-align: center; margin-bottom: 20px; }
    .logo img { height: 90px; }
    h2 { text-align: center; margin-bottom: 20px; color: #2d6a35; }
    .form-group { margin-bottom: 15px; }
    input {
      width: 100%;
      padding: 12px;
      border-radius: 10px;
      border: 1px solid #ccc;
      font-family: 'IBM Plex Sans Arabic', sans-serif;
      font-size: 0.95rem;
    }
    input:focus { outline: none; border-color: #2d6a35; }
    .error-msg {
      background: #fee2e2;
      color: #991b1b;
      padding: 10px 14px;
      border-radius: 8px;
      font-size: 0.88rem;
      margin-bottom: 14px;
      text-align: center;
    }
    button {
      width: 100%;
      padding: 12px;
      background: #2d6a35;
      color: white;
      border: none;
      border-radius: 10px;
      font-family: 'IBM Plex Sans Arabic', sans-serif;
      font-size: 16px;
      font-weight: 700;
      cursor: pointer;
      transition: background .2s;
    }
    button:hover { background: #1e4d25; }
  </style>
</head>
<body>
  <div class="card">
    <div class="logo">
      <img src="logo.jpg" alt="نفل"/>
    </div>
    <h2>دخول المسؤول</h2>

    <?php if ($error): ?>
      <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <input type="email" name="email" placeholder="البريد الإلكتروني" required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>
      </div>
      <div class="form-group">
        <input type="password" name="password" placeholder="كلمة المرور" required/>
      </div>
      <button type="submit">تسجيل الدخول</button>
    </form>
  </div>
</body>
</html>
