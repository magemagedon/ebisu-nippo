<?php
require_once 'kj_common.php';
$pdo = get_db();
$msg = ''; $msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'record') {
    $setsubi_id = $_POST['fk_setsubi_id'] ?? '';
    $date = $_POST['f_koukan_date'] ?? date('Y-m-d');
    if ($setsubi_id) {
        $pdo->prepare("INSERT INTO t_setsubi_koukan (pk_koukan_id,fk_setsubi_id,f_koukan_date,fk_tantosha_id,f_naiyo,f_biko,f_created_at) VALUES (?,?,?,?,?,?,NOW())")
            ->execute([generate_uuid(), $setsubi_id, $date, $_SESSION['tantosha_id'], $_POST['f_naiyo'] ?? '', $_POST['f_biko'] ?? '']);
        $msg = '保守・交換の実施記録を追加しました。次回予定日を更新しました。';
    } else { $msg = '設備を選択してください。'; $msg_type = 'danger'; }
}

$f_factory = $_GET['f_factory'] ?? '';
$where = ["s.f_active='有効'", 's.f_koukan_shuki_days IS NOT NULL'];
$params = [];
if ($f_factory) { $where[] = 's.fk_factory_id = ?'; $params[] = $f_factory; }

$stmt = $pdo->prepare("
    SELECT s.*, f.f_factory_name, p.f_setsubi_name AS parent_name
    FROM t_setsubi s
    JOIN t_factory f ON s.fk_factory_id=f.pk_factory_id
    LEFT JOIN t_setsubi p ON s.fk_parent_setsubi_id = p.pk_setsubi_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY f.f_sort_order, s.f_setsubi_name
");
$stmt->execute($params);
$setsubis = $stmt->fetchAll();

// 次回予定日を計算し、区分に振り分け
$overdue = []; $today = []; $week = []; $future = [];
$today_str = date('Y-m-d');
foreach ($setsubis as $s) {
    $next = kj_next_koukan_date($pdo, $s);
    $s['next_date'] = $next;
    $s['days_left'] = $next ? (int)floor((strtotime($next) - strtotime($today_str)) / 86400) : null;
    if ($s['days_left'] === null) continue;
    if ($s['days_left'] < 0) $overdue[] = $s;
    elseif ($s['days_left'] === 0) $today[] = $s;
    elseif ($s['days_left'] <= 7) $week[] = $s;
    else $future[] = $s;
}
usort($overdue, fn($a,$b) => $a['days_left'] <=> $b['days_left']);
usort($week, fn($a,$b) => $a['days_left'] <=> $b['days_left']);
usort($future, fn($a,$b) => $a['days_left'] <=> $b['days_left']);

$factories = kj_factories($pdo);
$all_setsubi = $pdo->query("
    SELECT s.pk_setsubi_id, s.f_setsubi_name, s.f_koukan_shuki_days, p.f_setsubi_name AS parent_name
    FROM t_setsubi s LEFT JOIN t_setsubi p ON s.fk_parent_setsubi_id = p.pk_setsubi_id
    WHERE s.f_active='有効' AND s.f_koukan_shuki_days IS NOT NULL ORDER BY s.f_setsubi_name
")->fetchAll();

// 直近の実施履歴
$rireki = $pdo->query("
    SELECT k.*, s.f_setsubi_name, p.f_setsubi_name AS parent_name, t.f_tantosha_name
    FROM t_setsubi_koukan k
    JOIN t_setsubi s ON k.fk_setsubi_id = s.pk_setsubi_id
    LEFT JOIN t_setsubi p ON s.fk_parent_setsubi_id = p.pk_setsubi_id
    JOIN t_tantosha t ON k.fk_tantosha_id = t.pk_tantosha_id
    ORDER BY k.f_koukan_date DESC, k.f_created_at DESC LIMIT 10
")->fetchAll();

echo html_header('保守・消耗品交換スケジュール');
echo nav_bar();
?>
<div class="container">
  <div class="page-title">工場メンテナンス管理　｜　保守・消耗品交換スケジュール</div>
  <?= kj_subnav('kj_maintenance.php') ?>

  <?php if($msg): ?><div class="alert alert-<?= $msg_type ?>"><?= h($msg) ?></div><?php endif; ?>

  <!-- サマリー -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px">
    <?php foreach([['期限超過',count($overdue),'#b71c1c','#fdecea'],['本日',count($today),'#e65100','#fff8e1'],['今週中',count($week),'#1565c0','#e3f2fd'],['それ以降',count($future),'#2e7d32','#e6f4ea']] as [$label,$cnt,$color,$bg]): ?>
    <div style="background:<?= $bg ?>;border:1px solid <?= $color ?>33;border-radius:8px;padding:12px;text-align:center">
      <div style="font-size:11px;color:<?= $color ?>;font-weight:600;margin-bottom:6px"><?= $label ?></div>
      <div style="font-size:28px;font-weight:700;color:<?= $color ?>"><?= $cnt ?></div>
      <div style="font-size:11px;color:#888">件</div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- 実施記録フォーム -->
  <div class="card">
    <div class="card-header">保守・交換の実施を記録</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="record">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
          <div class="form-group" style="margin:0">
            <label>対象設備 <span style="color:#c62828">*</span></label>
            <select name="fk_setsubi_id" class="form-control" required>
              <option value="">-- 選択 --</option>
              <?php foreach($all_setsubi as $s): ?><option value="<?= h($s['pk_setsubi_id']) ?>"><?= $s['parent_name']?h($s['parent_name']).' ＞ ':'' ?><?= h($s['f_setsubi_name']) ?>（周期<?= (int)$s['f_koukan_shuki_days'] ?>日）</option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0"><label>実施日 <span style="color:#c62828">*</span></label><input type="date" name="f_koukan_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
          <div class="form-group" style="margin:0"><label>実施内容</label><input type="text" name="f_naiyo" class="form-control" placeholder="例：刃の交換"></div>
          <div class="form-group" style="grid-column:1/-1;margin:0"><label>備考</label><input type="text" name="f_biko" class="form-control"></div>
        </div>
        <div style="text-align:right;margin-top:12px"><button type="submit" class="btn btn-primary">記録する</button></div>
      </form>
    </div>
  </div>

  <form method="get" class="search-area">
    <label>工場</label>
    <select name="f_factory" onchange="this.form.submit()">
      <option value="">全工場</option>
      <?php foreach($factories as $f): ?><option value="<?= h($f['pk_factory_id']) ?>" <?= $f_factory===$f['pk_factory_id']?'selected':'' ?>><?= h($f['f_factory_name']) ?></option><?php endforeach; ?>
    </select>
  </form>

  <?php
  $sections = [['🔴 期限超過', $overdue, '#b71c1c'], ['🟠 本日', $today, '#e65100'], ['🔵 今週中', $week, '#1565c0'], ['🟢 それ以降', $future, '#2e7d32']];
  foreach ($sections as [$title, $items, $color]):
    if (empty($items)) continue;
  ?>
  <div class="card">
    <div class="card-header" style="background:<?= $color ?>"><?= $title ?><span style="font-size:12px;font-weight:400"><?= count($items) ?>件</span></div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap"><table>
        <thead><tr><th>予定日</th><th>残日数</th><th>工場</th><th>設備</th><th class="pc-only">区分</th><th style="text-align:center">周期</th></tr></thead>
        <tbody>
        <?php foreach($items as $s): [$label, $lcolor] = kj_days_label($s['days_left']); ?>
        <tr>
          <td style="white-space:nowrap;font-weight:600"><?= h(date('Y/m/d', strtotime($s['next_date']))) ?></td>
          <td style="text-align:center"><span style="color:<?= $lcolor ?>;font-weight:700;font-size:13px"><?= $label ?></span></td>
          <td><?= h($s['f_factory_name']) ?></td>
          <td style="font-weight:600"><?= $s['parent_name']?'<span style="font-weight:400;color:#888">'.h($s['parent_name']).' ＞ </span>':'' ?><?= h($s['f_setsubi_name']) ?></td>
          <td class="pc-only"><?= h($s['f_setsubi_kubun']) ?></td>
          <td style="text-align:center"><?= (int)$s['f_koukan_shuki_days'] ?>日</td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
  <?php endforeach; ?>

  <?php if(empty($overdue) && empty($today) && empty($week) && empty($future)): ?>
  <div class="card"><div class="card-body" style="text-align:center;color:#999;padding:40px">周期設定済みの設備がありません（設備マスタで交換周期を設定してください）</div></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">直近の実施履歴</div>
    <div class="card-body" style="padding:0">
      <?php if(empty($rireki)): ?>
      <div style="text-align:center;color:#999;padding:30px">実施履歴はまだありません</div>
      <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>実施日</th><th>設備</th><th>実施者</th><th>実施内容</th></tr></thead>
        <tbody>
        <?php foreach($rireki as $r): ?>
        <tr>
          <td style="white-space:nowrap"><?= h(date('Y/m/d', strtotime($r['f_koukan_date']))) ?></td>
          <td style="font-weight:600"><?= $r['parent_name']?'<span style="font-weight:400;color:#888">'.h($r['parent_name']).' ＞ </span>':'' ?><?= h($r['f_setsubi_name']) ?></td>
          <td><?= h($r['f_tantosha_name']) ?></td>
          <td style="color:#666"><?= h($r['f_naiyo']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?= html_footer() ?>
