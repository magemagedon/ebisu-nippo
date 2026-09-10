<?php
require_once 'kj_common.php';
$pdo = get_db();
$msg = ''; $msg_type = 'success';

$factories = kj_factories($pdo);
$f_factory = $_GET['f_factory'] ?? ($factories[0]['pk_factory_id'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_koutei') {
    $name = trim($_POST['f_koutei_name'] ?? '');
    if ($name && !empty($_POST['fk_factory_id']) && $_POST['f_start_date'] && $_POST['f_end_date']) {
        $pdo->prepare("INSERT INTO t_koutei (pk_koutei_id,fk_factory_id,f_koutei_name,f_start_date,f_end_date,f_tanto,f_biko,f_created_at) VALUES (?,?,?,?,?,?,?,NOW())")
            ->execute([generate_uuid(), $_POST['fk_factory_id'], $name, $_POST['f_start_date'], $_POST['f_end_date'], $_POST['f_tanto'] ?? '', $_POST['f_biko'] ?? '']);
        $msg = '工程を登録しました。';
        $f_factory = $_POST['fk_factory_id'];
    } else { $msg = '工場・工程名・期間は必須です。'; $msg_type = 'danger'; }
}

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));
$prev = mktime(0,0,0,$month-1,1,$year); $next = mktime(0,0,0,$month+1,1,$year);
$first_day = mktime(0,0,0,$month,1,$year);
$last_day  = mktime(0,0,0,$month+1,0,$year);
$days_in_month = (int)date('t',$first_day);
$start_dow = (int)date('w',$first_day);
$month_start = date('Y-m-d',$first_day);
$month_end   = date('Y-m-d',$last_day);

// 工程表データ
$koutei_list = [];
if ($f_factory) {
    $stmt = $pdo->prepare("SELECT * FROM t_koutei WHERE fk_factory_id=? AND f_start_date<=? AND f_end_date>=? ORDER BY f_start_date");
    $stmt->execute([$f_factory, $month_end, $month_start]);
    $koutei_list = $stmt->fetchAll();
}

// 保守・交換予定日（設備マスタから計算）
$maint_by_date = [];
if ($f_factory) {
    $stmt = $pdo->prepare("SELECT * FROM t_setsubi WHERE fk_factory_id=? AND f_active='有効' AND f_koukan_shuki_days IS NOT NULL");
    $stmt->execute([$f_factory]);
    foreach ($stmt->fetchAll() as $s) {
        $next_date = kj_next_koukan_date($pdo, $s);
        if ($next_date && $next_date >= $month_start && $next_date <= $month_end) {
            $maint_by_date[$next_date][] = $s['f_setsubi_name'];
        }
    }
}

// パトロール予定日
$patrol_by_date = [];
if ($f_factory) {
    $stmt = $pdo->prepare("SELECT f_patrol_date FROM t_patrol WHERE fk_factory_id=? AND f_status='計画' AND f_patrol_date BETWEEN ? AND ?");
    $stmt->execute([$f_factory, $month_start, $month_end]);
    foreach ($stmt->fetchAll() as $r) $patrol_by_date[$r['f_patrol_date']][] = 'パトロール予定';
}

// 日付ごとに工程表バーを展開
$koutei_by_date = [];
foreach ($koutei_list as $k) {
    $d = max($k['f_start_date'], $month_start);
    $end = min($k['f_end_date'], $month_end);
    while ($d <= $end) {
        $koutei_by_date[$d][] = $k['f_koutei_name'];
        $d = date('Y-m-d', strtotime($d . ' +1 day'));
    }
}

echo html_header('工程表連動ビュー');
echo nav_bar();
?>
<div class="container">
  <div class="page-title">工場メンテナンス管理　｜　工程表連動ビュー</div>
  <?= kj_subnav('kj_process.php') ?>

  <?php if($msg): ?><div class="alert alert-<?= $msg_type ?>"><?= h($msg) ?></div><?php endif; ?>

  <div class="card">
    <div class="card-header">工程を登録</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="add_koutei">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px" class="meisai-grid-3">
          <div class="form-group" style="margin:0">
            <label>工場・拠点 <span style="color:#c62828">*</span></label>
            <select name="fk_factory_id" class="form-control" required>
              <?php foreach($factories as $f): ?><option value="<?= h($f['pk_factory_id']) ?>" <?= $f_factory===$f['pk_factory_id']?'selected':'' ?>><?= h($f['f_factory_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0"><label>工程・作業名 <span style="color:#c62828">*</span></label><input type="text" name="f_koutei_name" class="form-control" placeholder="例：RPF製造ライン稼働" required></div>
          <div class="form-group" style="margin:0"><label>開始日 <span style="color:#c62828">*</span></label><input type="date" name="f_start_date" class="form-control" required></div>
          <div class="form-group" style="margin:0"><label>終了日 <span style="color:#c62828">*</span></label><input type="date" name="f_end_date" class="form-control" required></div>
          <div class="form-group" style="margin:0"><label>担当部署</label><input type="text" name="f_tanto" class="form-control" placeholder="例：生産技術課"></div>
          <div class="form-group" style="grid-column:span 3;margin:0"><label>備考</label><input type="text" name="f_biko" class="form-control"></div>
        </div>
        <div style="text-align:right;margin-top:12px"><button type="submit" class="btn btn-primary">登録する</button></div>
      </form>
    </div>
  </div>

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px">
    <form method="get" style="display:flex;gap:8px;align-items:center">
      <label style="font-size:12px;font-weight:600;color:#1B3A6B">工場</label>
      <select name="f_factory" onchange="this.form.submit()">
        <?php foreach($factories as $f): ?><option value="<?= h($f['pk_factory_id']) ?>" <?= $f_factory===$f['pk_factory_id']?'selected':'' ?>><?= h($f['f_factory_name']) ?></option><?php endforeach; ?>
      </select>
      <input type="hidden" name="year" value="<?= $year ?>"><input type="hidden" name="month" value="<?= $month ?>">
    </form>
    <div style="display:flex;align-items:center;gap:10px">
      <a href="?f_factory=<?= h($f_factory) ?>&year=<?= date('Y',$prev) ?>&month=<?= date('n',$prev) ?>" class="btn btn-gray btn-sm">◀ 前月</a>
      <span style="font-size:18px;font-weight:700;color:#1B3A6B"><?= $year ?>年<?= $month ?>月</span>
      <a href="?f_factory=<?= h($f_factory) ?>&year=<?= date('Y',$next) ?>&month=<?= date('n',$next) ?>" class="btn btn-gray btn-sm">次月 ▶</a>
    </div>
  </div>

  <div style="display:flex;gap:14px;margin-bottom:10px;font-size:11px;flex-wrap:wrap">
    <span><span style="display:inline-block;width:10px;height:10px;background:#2E75B6;border-radius:2px;margin-right:4px"></span>工程表</span>
    <span><span style="display:inline-block;width:10px;height:10px;background:#e65100;border-radius:2px;margin-right:4px"></span>保守・交換予定</span>
    <span><span style="display:inline-block;width:10px;height:10px;background:#6a1b9a;border-radius:2px;margin-right:4px"></span>パトロール予定</span>
    <span style="color:#c62828;font-weight:700">⚠ 同日に複数予定＝作業競合の可能性</span>
  </div>

  <div class="card">
    <div class="card-body" style="padding:0">
      <div style="display:grid;grid-template-columns:repeat(7,1fr);border-bottom:2px solid #1B3A6B">
        <?php foreach(['日','月','火','水','木','金','土'] as $dow): ?>
        <div style="text-align:center;padding:8px;font-weight:700;font-size:13px;background:#1B3A6B;color:#fff"><?= $dow ?></div>
        <?php endforeach; ?>
      </div>
      <?php
      $today_str = date('Y-m-d'); $day = 1;
      $rows = ceil(($start_dow + $days_in_month) / 7);
      for ($row = 0; $row < $rows; $row++):
      ?>
      <div style="display:grid;grid-template-columns:repeat(7,1fr);border-bottom:1px solid #e8eef5;min-height:80px">
        <?php for ($col = 0; $col < 7; $col++):
          $cell_idx = $row*7+$col;
          $is_valid = ($cell_idx >= $start_dow && $day <= $days_in_month);
          $date_str = $is_valid ? sprintf('%04d-%02d-%02d',$year,$month,$day) : '';
          $is_today = ($date_str === $today_str);
          $koutei_events = $is_valid ? ($koutei_by_date[$date_str] ?? []) : [];
          $maint_events  = $is_valid ? ($maint_by_date[$date_str] ?? []) : [];
          $patrol_events = $is_valid ? ($patrol_by_date[$date_str] ?? []) : [];
          $has_conflict  = !empty($koutei_events) && (!empty($maint_events) || !empty($patrol_events));
          $bg = $is_today ? '#EEF3FA' : ($row%2===0?'#fff':'#fafbfc');
        ?>
        <div style="border-right:1px solid #e8eef5;padding:4px;background:<?= $bg ?>;<?= $has_conflict?'box-shadow:inset 0 0 0 2px #c62828':'' ?>">
          <?php if($is_valid): ?>
          <div style="font-size:12px;font-weight:<?= $is_today?'700':'500' ?>;margin-bottom:3px;<?= $is_today?'background:#2E75B6;color:#fff;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center':'' ?>">
            <?= $day ?><?= $has_conflict?' ⚠':'' ?>
          </div>
          <?php foreach($koutei_events as $e): ?><div style="background:#2E75B6;color:#fff;border-radius:3px;padding:1px 4px;margin-bottom:1px;font-size:9px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis" title="<?= h($e) ?>"><?= h(mb_substr($e,0,7)) ?></div><?php endforeach; ?>
          <?php foreach($maint_events as $e): ?><div style="background:#e65100;color:#fff;border-radius:3px;padding:1px 4px;margin-bottom:1px;font-size:9px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis" title="<?= h($e) ?>">🔧<?= h(mb_substr($e,0,6)) ?></div><?php endforeach; ?>
          <?php foreach($patrol_events as $e): ?><div style="background:#6a1b9a;color:#fff;border-radius:3px;padding:1px 4px;margin-bottom:1px;font-size:9px">🦺パトロール</div><?php endforeach; ?>
          <?php endif; ?>
        </div>
        <?php if($is_valid) $day++; endfor; ?>
      </div>
      <?php endfor; ?>
    </div>
  </div>
</div>
<?= html_footer() ?>
