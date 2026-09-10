<?php
require_once 'db.php';
require_once 'common.php';

$pdo = get_db();

$where = ['1=1'];
$params = [];

$f_from  = $_GET['f_from']  ?? '';
$f_to    = $_GET['f_to']    ?? '';
$f_tanto = $_GET['f_tanto'] ?? '';

if ($f_from)  { $where[] = 'h.f_date >= ?'; $params[] = $f_from; }
if ($f_to)    { $where[] = 'h.f_date <= ?'; $params[] = $f_to; }
if ($f_tanto) { $where[] = 'h.fk_tantosha_id = ?'; $params[] = $f_tanto; }

[$cond, $ps] = visible_tantosha_where($pdo, 'h.fk_tantosha_id');
if ($cond) { $where[] = $cond; array_push($params, ...$ps); }

$sql = "SELECT h.*, t.f_tantosha_name,
        (SELECT COUNT(*) FROM t_nippo_meisai m WHERE m.fk_nippo_id = h.pk_nippo_id) AS meisai_count
        FROM t_nippo_header h
        LEFT JOIN t_tantosha t ON h.fk_tantosha_id = t.pk_tantosha_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY h.f_date DESC, h.f_created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$visible_ids = visible_tantosha_ids($pdo);
if ($visible_ids === null) {
    $tantoshas = $pdo->query("SELECT * FROM t_tantosha WHERE f_zaiseki_flag='有効' ORDER BY f_tantosha_name")->fetchAll();
} elseif (count($visible_ids) > 1) {
    $ph = implode(',', array_fill(0, count($visible_ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM t_tantosha WHERE f_zaiseki_flag='有効' AND pk_tantosha_id IN ($ph) ORDER BY f_tantosha_name");
    $stmt->execute($visible_ids);
    $tantoshas = $stmt->fetchAll();
} else {
    $tantoshas = [];
}

echo html_header('日報一覧');
echo nav_bar();
?>
<div class="container">
  <div class="page-title">業務日報　一覧</div>

  <?php if(isset($_GET['msg'])): ?>
  <div class="alert alert-success"><?= h($_GET['msg']) ?></div>
  <?php endif; ?>

  <!-- 検索エリア -->
  <form method="get">
  <div class="search-area">
    <label>期間</label>
    <input type="date" name="f_from" value="<?= h($f_from) ?>">
    <span style="color:#666" class="pc-only">〜</span>
    <input type="date" name="f_to" value="<?= h($f_to) ?>">
    <?php if ($_SESSION['kengen'] === '管理者' || $_SESSION['kengen'] === '部門管理者'): ?>
    <label>担当者</label>
    <select name="f_tanto">
      <option value="">全員</option>
      <?php foreach($tantoshas as $t): ?>
      <option value="<?= h($t['pk_tantosha_id']) ?>" <?= $f_tanto===$t['pk_tantosha_id']?'selected':'' ?>>
        <?= h($t['f_tantosha_name']) ?>
      </option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <button type="submit" class="btn btn-blue btn-sm">検索</button>
    <a href="index.php" class="btn btn-gray btn-sm">全件</a>
    <a href="csv.php?type=nippo&f_from=<?= h($f_from) ?>&f_to=<?= h($f_to) ?>&f_tanto=<?= h($f_tanto) ?>" class="btn btn-gray btn-sm">📥 CSV</a>
    <a href="csv.php?type=meisai&f_from=<?= h($f_from) ?>&f_to=<?= h($f_to) ?>&f_tanto=<?= h($f_tanto) ?>" class="btn btn-gray btn-sm">📥 明細CSV</a>
    <div style="margin-left:auto">
      <a href="create.php" class="btn btn-primary">＋ 新規日報</a>
    </div>
  </div>
  </form>

  <!-- PC用テーブル -->
  <div class="card pc-only">
    <div class="card-header">
      日報一覧
      <span style="font-size:12px;font-weight:400"><?= count($rows) ?> 件</span>
    </div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>日付</th>
            <th>担当者</th>
            <th>訪問件数</th>
            <th>確認ステータス</th>
            <th>備考</th>
            <th style="text-align:center">操作</th>
          </tr>
        </thead>
        <tbody>
        <?php if(empty($rows)): ?>
          <tr><td colspan="6" style="text-align:center;padding:30px;color:#999">日報がありません</td></tr>
        <?php else: ?>
          <?php foreach($rows as $r): ?>
          <tr>
            <td style="white-space:nowrap;font-weight:600"><?= h(date('Y/m/d', strtotime($r['f_date']))) ?></td>
            <td><?= h($r['f_tantosha_name']) ?></td>
            <td style="text-align:center"><?= (int)$r['meisai_count'] ?> 件</td>
            <td><?= status_badge($r['f_kakunin_status']) ?></td>
            <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#666"><?= h($r['f_biko']) ?></td>
            <td style="text-align:center;white-space:nowrap">
              <a href="detail.php?id=<?= h($r['pk_nippo_id']) ?>" class="btn btn-blue btn-sm">詳細</a>
              <?php if($r['f_kakunin_status'] !== '確認済' && ($r['fk_tantosha_id']===$_SESSION['tantosha_id'] || $_SESSION['kengen']==='管理者')): ?>
              <a href="create.php?id=<?= h($r['pk_nippo_id']) ?>" class="btn btn-gray btn-sm">編集</a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>

  <!-- スマホ用カードリスト -->
  <div class="sp-only">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <span style="font-size:13px;color:#666"><?= count($rows) ?> 件</span>
    </div>
    <?php if(empty($rows)): ?>
    <div class="card"><div class="card-body" style="text-align:center;color:#999;padding:30px">日報がありません</div></div>
    <?php else: ?>
    <?php foreach($rows as $r): ?>
    <div class="nippo-card">
      <div class="nippo-card-header">
        <span class="nippo-card-date"><?= h(date('Y/m/d', strtotime($r['f_date']))) ?></span>
        <?= status_badge($r['f_kakunin_status']) ?>
      </div>
      <div class="nippo-card-body">
        <div style="margin-bottom:4px">
          <span style="color:#888;font-size:12px">担当：</span><?= h($r['f_tantosha_name']) ?>
          <span style="color:#888;font-size:12px;margin-left:10px">訪問：</span><?= (int)$r['meisai_count'] ?>件
        </div>
        <?php if($r['f_biko']): ?>
        <div style="color:#666;font-size:12px;margin-top:4px"><?= h(mb_substr($r['f_biko'],0,40)) ?><?= mb_strlen($r['f_biko'])>40?'…':'' ?></div>
        <?php endif; ?>
      </div>
      <div class="nippo-card-footer">
        <?php if($r['f_kakunin_status'] !== '確認済' && ($r['fk_tantosha_id']===$_SESSION['tantosha_id'] || $_SESSION['kengen']==='管理者')): ?>
        <a href="create.php?id=<?= h($r['pk_nippo_id']) ?>" class="btn btn-gray btn-sm">編集</a>
        <?php endif; ?>
        <a href="detail.php?id=<?= h($r['pk_nippo_id']) ?>" class="btn btn-blue btn-sm">詳細 →</a>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>
<?= html_footer() ?>
