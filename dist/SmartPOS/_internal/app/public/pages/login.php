<?php
require_once dirname(__DIR__,2) . '/includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = json_in();
    $pin = $d['pin'] ?? '';
    $stmt = get_db()->query("SELECT * FROM users LIMIT 1");
    $u = $stmt->fetch();
    if ($u && password_verify($pin, $u['pin_hash'])) {
        $_SESSION['uid'] = $u['id'];
        $_SESSION['uname'] = $u['name'];
        json_out(['ok'=>true,'redirect'=>'/dashboard']);
    }
    json_out(['ok'=>false,'msg'=>'Incorrect PIN'], 401);
}

$shop = get_setting('shop_name', 'SmartPOS');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($shop) ?> — Login</title>
<link rel="stylesheet" href="/assets/css/style.css">
<script>(function(){var t=localStorage.getItem('pos_theme');if(t)document.documentElement.setAttribute('data-theme',t);})();</script>
</head>
<body class="login-body">
<div class="login-box">
  <div class="login-logo"><?= htmlspecialchars(strtoupper(substr($shop,0,1))) ?></div>
  <h1 class="login-title"><?= htmlspecialchars($shop) ?></h1>
  <p class="login-sub">Enter your 4-digit PIN</p>
  <div class="pin-dots">
    <span class="pin-dot" data-i="0"></span><span class="pin-dot" data-i="1"></span>
    <span class="pin-dot" data-i="2"></span><span class="pin-dot" data-i="3"></span>
  </div>
  <p id="pin-error" class="pin-error"></p>
  <div class="keypad">
    <?php for($i=1;$i<=9;$i++): ?><button class="key" data-k="<?=$i?>"><?=$i?></button><?php endfor; ?>
    <button class="key key-ghost" disabled></button>
    <button class="key" data-k="0">0</button>
    <button class="key" id="key-back">&#8592;</button>
  </div>
  <p class="login-hint">Default PIN: <b>1234</b> — change it in Settings</p>
</div>
<script src="/assets/js/login.js"></script>
</body>
</html>
