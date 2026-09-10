<?php
require_once 'db.php';
require_once 'common.php';

$pdo = get_db();
$msg = '';
$msg_type = 'success';

// 管理者：追加・削除処理
if ($_SESSION['kengen'] === '管理者' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title       = trim($_POST['f_title'] ?? '');
        $body        = trim($_POST['f_body'] ?? '');
        $important   = isset($_POST['f_important']) ? 1 : 0;
        $publish     = $_POST['f_publish_date'] ?? date('Y-m-d');
        $expire      = $_POST['f_expire_date'] ?: null;

        if ($title && $body) {
            $pdo->prepare("
                INSERT INTO t_oshirase
                (pk_oshirase_id, f_title, f_body, f_author_id, f_important, f_publish_date, f_expire_date, f_created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([generate_uuid(), $title, $body, $_SESSION['tantosha_id'], $important, $publish, $expire]);
            $msg = 'お知らせを追加しました。';
        } else {
            $msg = 'タイトルと本文を入力してください。';
            $msg_type = 'danger';
        }

    } elseif ($action === 'delete') {
        $oid = $_POST['oshirase_id'] ?? '';
        if ($oid) {
            $pdo->prepare("DELETE FROM t_oshirase WHERE pk_oshirase_id = ?")->execute([$oid]);
            $msg = 'お知らせを削除しました。';
        }
    }
}

// お知らせ一覧取得
$today = date('Y-m-d');
$rows = $pdo->query("
    SELECT o.*, t.f_tantosha_name
    FROM t_oshirase o
    JOIN t_tantosha t ON o.f_author_id = t.pk_tantosha_id
    WHERE o.f_publish_date <= '{$today}'
      AND (o.f_expire_date IS NULL OR o.f_expire_date >= '{$today}')
    ORDER BY o.f_important DESC, o.f_publish_date DESC
")->fetchAll();

echo html_header('お知らせ');
echo nav_bar();
?>
<div class="container">
  <div class="page-title">お知らせ</div>

  <?php if($msg): ?>
  <div class="alert alert-<?= $msg_type ?>"><?= h($msg) ?></div>
  <?php endif; ?>

  <!-- 管理者：お知らせ追加 -->
  <?php if($_SESSION['kengen'] === '管理者'): ?>
  <div class="card">
    <div class="card-header">お知らせを追加（管理者のみ）</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="form-group">
          <label>タイトル <span style="color:#c62828">*</span></label>
          <input type="text" name="f_title" class="form-control" placeholder="お知らせのタイトル" required>
        </div>
        <div class="form-group">
          <label>本文 <span style="color:#c62828">*</span></label>
          <textarea name="f_body" class="form-control" rows="3" placeholder="お知らせの内容" required></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
          <div class="form-group" style="margin:0">
            <label>公開開始日</label>
            <input type="date" name="f_publish_date" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group" style="margin:0">
            <label>公開終了日（任意）</label>
            <input type="date" name="f_expire_date" class="form-control">
          </div>
          <div class="form-group" style="margin:0">
            <label>重要度</label>
            <div style="padding:10px 0">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="f_important" value="1">
                <span style="color:#c62828;font-weight:600">🔴 重要なお知らせにする</span>
              </label>
            </div>
          </div>
        </div>
        <div style="text-align:right;margin-top:12px">
          <button type="submit" class="btn btn-primary">追加する</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <!-- お知らせ一覧 -->
  <?php if(empty($rows)): ?>
  <div class="card">
    <div class="card-body" style="text-align:center;color:#999;padding:40px">現在お知らせはありません</div>
  </div>
  <?php else: ?>
  <?php foreach($rows as $r): ?>
  <div class="card" style="margin-bottom:12px;<?= $r['f_important'] ? 'border:2px solid #c62828' : '' ?>">
    <div class="card-header" style="<?= $r['f_important'] ? 'background:#c62828' : '' ?>">
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <?php if($r['f_important']): ?>
        <span style="background:#fff;color:#c62828;border-radius:4px;padding:2px 8px;font-size:11px;font-weight:700">🔴 重要</span>
        <?php endif; ?>
        <span><?= h($r['f_title']) ?></span>
      </div>
      <div style="display:flex;align-items:center;gap:8px">
        <span style="font-size:11px;font-weight:400;opacity:.8">
          <?= h(date('Y/m/d', strtotime($r['f_publish_date']))) ?> ／ <?= h($r['f_tantosha_name']) ?>
        </span>
        <?php if($_SESSION['kengen'] === '管理者'): ?>
        <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？')">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="oshirase_id" value="<?= h($r['pk_oshirase_id']) ?>">
          <button type="submit" style="background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.4);color:#fff;border-radius:4px;padding:2px 8px;font-size:11px;cursor:pointer">削除</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-body">
      <div style="font-size:14px;line-height:1.8;white-space:pre-wrap"><?= h($r['f_body']) ?></div>
      <?php if($r['f_expire_date']): ?>
      <div style="margin-top:10px;font-size:11px;color:#888">掲載期限：<?= h(date('Y/m/d', strtotime($r['f_expire_date']))) ?></div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

</div>
<?= html_footer() ?>
