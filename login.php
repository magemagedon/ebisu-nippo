<?php
require_once 'db.php';
require_once 'common.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account = trim($_POST['account'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($account && $password) {
        $pdo = get_db();
        $stmt = $pdo->prepare("SELECT * FROM t_tantosha WHERE f_account_name = ? AND f_zaiseki_flag = '有効'");
        $stmt->execute([$account]);
        $user = $stmt->fetch();
        
        if ($user && hash_equals($user['f_password_hash'], hash('sha256', $password))) {
            $_SESSION['tantosha_id']   = $user['pk_tantosha_id'];
            $_SESSION['tantosha_name'] = $user['f_tantosha_name'];
            $_SESSION['kengen']        = $user['f_kengen_kubun'];
            header('Location: index.php');
            exit;
        }
        $error = 'アカウント名またはパスワードが正しくありません。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ログイン | エビス紙料 業務システム</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Hiragino Kaku Gothic ProN','Meiryo',sans-serif;background:#1B3A6B;min-height:100vh;display:flex;align-items:center;justify-content:center}
.login-wrap{background:#fff;border-radius:10px;padding:40px 36px;width:360px;box-shadow:0 8px 32px rgba(0,0,0,.25)}
.logo{text-align:center;margin-bottom:28px}
.logo h1{font-size:20px;font-weight:700;color:#1B3A6B;margin-bottom:4px}
.logo p{font-size:12px;color:#888}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:12px;font-weight:600;color:#1B3A6B;margin-bottom:5px}
.form-group input{width:100%;padding:10px 12px;border:1px solid #C5D3E8;border-radius:5px;font-size:14px;font-family:inherit}
.form-group input:focus{outline:none;border-color:#2E75B6;box-shadow:0 0 0 2px rgba(46,117,182,.15)}
.btn-login{width:100%;padding:11px;background:#1B3A6B;color:#fff;border:none;border-radius:5px;font-size:15px;font-weight:700;cursor:pointer;margin-top:6px}
.btn-login:hover{background:#2a4f8a}
.error{background:#fdecea;color:#b71c1c;border:1px solid #f5a8a8;padding:8px 12px;border-radius:5px;font-size:13px;margin-bottom:14px}
.hint{margin-top:16px;padding:10px;background:#EEF3FA;border-radius:5px;font-size:11px;color:#555;text-align:center}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="logo">
    <h1>エビス紙料株式会社</h1>
    <p>業務システム</p>
  </div>
  <?php if($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
  <form method="post">
    <div class="form-group">
      <label>アカウント名</label>
      <input type="text" name="account" value="<?= h($_POST['account'] ?? '') ?>" placeholder="例：yamada" required autofocus>
    </div>
    <div class="form-group">
      <label>パスワード</label>
      <input type="password" name="password" placeholder="パスワード" required>
    </div>
    <button class="btn-login" type="submit">ログイン</button>
  </form>
  <div class="hint">テスト用：yamada / password123</div>
</div>
</body>
</html>
