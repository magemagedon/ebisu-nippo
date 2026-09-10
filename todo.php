<?php
require_once 'db.php';
require_once 'common.php';

$pdo = get_db();
$msg = '';
$msg_type = 'success';

// 処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title    = trim($_POST['f_title'] ?? '');
        $detail   = trim($_POST['f_detail'] ?? '');
        $due      = $_POST['f_due_date'] ?: null;
        $priority = $_POST['f_priority'] ?? '中';
        if ($title) {
            $pdo->prepare("INSERT INTO t_todo (pk_todo_id,fk_tantosha_id,f_title,f_detail,f_due_date,f_priority,f_status,f_created_at,f_updated_at) VALUES (?,?,?,?,?,?,'未着手',NOW(),NOW())")
                ->execute([generate_uuid(), $_SESSION['tantosha_id'], $title, $detail, $due, $priority]);
            $msg = 'ToDoを追加しました。';
        } else {
            $msg = 'タイトルを入力してください。'; $msg_type = 'danger';
        }

    } elseif ($action === 'status') {
        $tid    = $_POST['todo_id'] ?? '';
        $status = $_POST['new_status'] ?? '';
        if ($tid && $status) {
            $pdo->prepare("UPDATE t_todo SET f_status=?, f_updated_at=NOW() WHERE pk_todo_id=? AND fk_tantosha_id=?")
                ->execute([$status, $tid, $_SESSION['tantosha_id']]);
            $msg = 'ステータスを更新しました。';
        }

    } elseif ($action === 'delete') {
        $tid = $_POST['todo_id'] ?? '';
        if ($tid) {
            $pdo->prepare("DELETE FROM t_todo WHERE pk_todo_id=? AND (fk_tantosha_id=? OR ?='管理者')")
                ->execute([$tid, $_SESSION['tantosha_id'], $_SESSION['kengen']]);
            $msg = '削除しました。';
        }
    }
}

// 絞り込み
$f_status  = $_GET['f_status']  ?? '';
$f_tanto   = $_GET['f_tanto']   ?? '';

$where  = ['1=1'];
$params = [];

// 管理者は全員分、一般は自分のみ
if ($_SESSION['kengen'] !== '管理者') {
    $where[] = 't.fk_tantosha_id = ?';
    $params[] = $_SESSION['tantosha_id'];
} elseif ($f_tanto) {
    $where[] = 't.fk_tantosha_id = ?';
    $params[] = $f_tanto;
}

if ($f_status) {
    $where[] = 't.f_status = ?';
    $params[] = $f_status;
} else {
    $where[] = "t.f_status != '完了'";
}

$stmt = $pdo->prepare("
    SELECT t.*, ta.f_tantosha_name,
           DATEDIFF(t.f_due_date, CURDATE()) AS days_left
    FROM t_todo t
    JOIN t_tantosha ta ON t.fk_tantosha_id = ta.pk_tantosha_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY FIELD(t.f_priority,'高','中','低'), t.f_due_date ASC, t.f_created_at ASC
");
$stmt->execute($params);
$todos = $stmt->fetchAll();

// 統計
$stmt2 = $pdo->prepare("
    SELECT f_status, COUNT(*) AS cnt FROM t_todo
    WHERE fk_tantosha_id = ?
    GROUP BY f_status
");
$stmt2->execute([$_SESSION['tantosha_id']]);
$my_stats = [];
foreach($stmt2->fetchAll() as $r) $my_stats[$r['f_status']] = $r['cnt'];

$tantoshas = $pdo->query("SELECT * FROM t_tantosha WHERE f_zaiseki_flag='有効' ORDER BY f_tantosha_name")->fetchAll();

echo html_header('ToDoリスト');
echo nav_bar();
?>
<div class="container">
  <div class="page-title">ToDoリスト</div>

  <?php if($msg): ?>
  <div class="alert alert-<?= $msg_type ?>"><?= h($msg) ?></div>
  <?php endif; ?>

  <!-- 自分の統計 -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px">
    <?php
    $stat_list = [
      ['未着手', $my_stats['未着手'] ?? 0, '#e65100', '#fff8e1'],
      ['進行中', $my_stats['進行中'] ?? 0, '#1565c0', '#e3f2fd'],
      ['完了',   $my_stats['完了']   ?? 0, '#2e7d32', '#e6f4ea'],
    ];
    foreach($stat_list as [$label, $cnt, $color, $bg]):
    ?>
    <div class="card" style="margin:0">
      <div class="card-body" style="text-align:center;padding:12px 10px">
        <div style="font-size:11px;color:<?= $color ?>;font-weight:600;margin-bottom:4px"><?= $label ?></div>
        <div style="font-size:28px;font-weight:700;color:<?= $color ?>"><?= $cnt ?></div>
        <div style="font-size:11px;color:#888">件</div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Todo追加フォーム -->
  <div class="card">
    <div class="card-header">ToDoを追加</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="form-group">
          <label>タイトル <span style="color:#c62828">*</span></label>
          <input type="text" name="f_title" class="form-control" placeholder="例：〇〇社への見積提出" required>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
          <div class="form-group" style="margin:0">
            <label>期限日</label>
            <input type="date" name="f_due_date" class="form-control">
          </div>
          <div class="form-group" style="margin:0">
            <label>優先度</label>
            <select name="f_priority" class="form-control">
              <option value="高">🔴 高</option>
              <option value="中" selected>🟡 中</option>
              <option value="低">🟢 低</option>
            </select>
          </div>
          <div class="form-group" style="margin:0">
            <label>詳細メモ</label>
            <input type="text" name="f_detail" class="form-control" placeholder="補足があれば">
          </div>
        </div>
        <div style="text-align:right;margin-top:12px">
          <button type="submit" class="btn btn-primary">追加する</button>
        </div>
      </form>
    </div>
  </div>

  <!-- 絞り込み -->
  <form method="get" class="search-area">
    <?php if($_SESSION['kengen'] === '管理者'): ?>
    <label>担当者</label>
    <select name="f_tanto">
      <option value="">全員</option>
      <?php foreach($tantoshas as $t): ?>
      <option value="<?= h($t['pk_tantosha_id']) ?>" <?= $f_tanto===$t['pk_tantosha_id']?'selected':'' ?>><?= h($t['f_tantosha_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <label>ステータス</label>
    <select name="f_status">
      <option value="">未完了のみ</option>
      <option value="未着手" <?= $f_status==='未着手'?'selected':'' ?>>未着手</option>
      <option value="進行中" <?= $f_status==='進行中'?'selected':'' ?>>進行中</option>
      <option value="完了"   <?= $f_status==='完了'  ?'selected':'' ?>>完了</option>
    </select>
    <button type="submit" class="btn btn-blue btn-sm">絞り込み</button>
    <a href="todo.php" class="btn btn-gray btn-sm">リセット</a>
  </form>

  <!-- ToDoリスト -->
  <div class="card">
    <div class="card-header">
      ToDoリスト
      <span style="font-size:12px;font-weight:400"><?= count($todos) ?>件</span>
    </div>
    <div class="card-body" style="padding:0">
      <?php if(empty($todos)): ?>
      <div style="text-align:center;color:#999;padding:40px">ToDoがありません</div>
      <?php else: ?>
      <?php foreach($todos as $i => $todo):
        $dl = $todo['days_left'];
        $priority_color = match($todo['f_priority']) { '高'=>'#c62828','中'=>'#e65100','低'=>'#2e7d32', default=>'#888' };
        $status_color   = match($todo['f_status'])   { '未着手'=>'#e65100','進行中'=>'#1565c0','完了'=>'#2e7d32', default=>'#888' };
        $status_bg      = match($todo['f_status'])   { '未着手'=>'#fff8e1','進行中'=>'#e3f2fd','完了'=>'#e6f4ea', default=>'#f5f5f5' };
        $is_overdue     = ($dl !== null && $dl < 0 && $todo['f_status'] !== '完了');
        $row_bg         = $todo['f_status']==='完了' ? '#f5f5f5' : ($i%2===0?'#fff':'#f7f9fc');
      ?>
      <div style="padding:12px 16px;border-bottom:1px solid #e8eef5;background:<?= $row_bg ?>;<?= $is_overdue?'border-left:3px solid #c62828':'' ?>">
        <div style="display:flex;align-items:flex-start;gap:10px;flex-wrap:wrap">

          <!-- 優先度バッジ -->
          <span style="background:<?= $priority_color ?>22;color:<?= $priority_color ?>;border:1px solid <?= $priority_color ?>66;border-radius:4px;padding:2px 8px;font-size:11px;font-weight:700;white-space:nowrap">
            <?= h($todo['f_priority']) ?>
          </span>

          <!-- タイトル・詳細 -->
          <div style="flex:1;min-width:0">
            <div style="font-weight:600;font-size:14px;<?= $todo['f_status']==='完了'?'text-decoration:line-through;color:#999':'' ?>">
              <?= h($todo['f_title']) ?>
            </div>
            <?php if($todo['f_detail']): ?>
            <div style="font-size:12px;color:#666;margin-top:2px"><?= h($todo['f_detail']) ?></div>
            <?php endif; ?>
            <div style="display:flex;gap:10px;margin-top:4px;font-size:11px;color:#888;flex-wrap:wrap">
              <?php if($_SESSION['kengen']==='管理者'): ?>
              <span>👤 <?= h($todo['f_tantosha_name']) ?></span>
              <?php endif; ?>
              <?php if($todo['f_due_date']): ?>
              <span style="color:<?= $is_overdue?'#c62828':($dl<=3?'#e65100':'#888') ?>;font-weight:<?= $is_overdue||$dl<=3?'600':'400' ?>">
                📅 <?= h(date('Y/m/d', strtotime($todo['f_due_date']))) ?>
                <?php if($dl !== null): ?>
                （<?= $dl<0 ? abs($dl).'日超過' : ($dl===0 ? '本日' : $dl.'日後') ?>）
                <?php endif; ?>
              </span>
              <?php endif; ?>
            </div>
          </div>

          <!-- ステータス変更 -->
          <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
            <span style="background:<?= $status_bg ?>;color:<?= $status_color ?>;border:1px solid <?= $status_color ?>66;border-radius:10px;padding:3px 10px;font-size:11px;font-weight:600">
              <?= h($todo['f_status']) ?>
            </span>
            <?php if($todo['fk_tantosha_id'] === $_SESSION['tantosha_id'] || $_SESSION['kengen']==='管理者'): ?>
            <div style="display:flex;gap:4px">
              <?php
              $next_statuses = match($todo['f_status']) {
                '未着手' => [['進行中','btn-blue'],['完了','btn-success']],
                '進行中' => [['完了','btn-success'],['未着手','btn-gray']],
                '完了'   => [['未着手','btn-gray']],
              };
              foreach($next_statuses as [$ns, $nc]):
              ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="todo_id" value="<?= h($todo['pk_todo_id']) ?>">
                <input type="hidden" name="new_status" value="<?= h($ns) ?>">
                <button type="submit" class="btn <?= $nc ?> btn-sm"><?= h($ns) ?></button>
              </form>
              <?php endforeach; ?>
              <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="todo_id" value="<?= h($todo['pk_todo_id']) ?>">
                <button type="submit" class="btn btn-danger btn-sm">削除</button>
              </form>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<?= html_footer() ?>
