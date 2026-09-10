<?php
require_once 'db.php';
require_once 'common.php';

$pdo = get_db();

// 表示月
$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));

// 前月・次月
$prev = mktime(0,0,0,$month-1,1,$year);
$next = mktime(0,0,0,$month+1,1,$year);
$prev_y = date('Y',$prev); $prev_m = date('n',$prev);
$next_y = date('Y',$next); $next_m = date('n',$next);

// 月初・月末
$first_day = mktime(0,0,0,$month,1,$year);
$last_day  = mktime(0,0,0,$month+1,0,$year);
$days_in_month = (int)date('t',$first_day);
$start_dow = (int)date('w',$first_day); // 0=日

// 訪問予定（次回予定日）を取得
$where = ['m.f_jikai_yoteibi BETWEEN ? AND ?'];
$params = [date('Y-m-d',$first_day), date('Y-m-d',$last_day)];
[$cond, $ps] = visible_tantosha_where($pdo, 'h.fk_tantosha_id');
if ($cond) { $where[] = $cond; array_push($params, ...$ps); }

$stmt = $pdo->prepare("
    SELECT m.f_jikai_yoteibi, m.f_homonsakimei, m.f_jikai_action,
           m.f_juchu_mikomikubun, t.f_tantosha_name, h.pk_nippo_id,
           t.pk_tantosha_id
    FROM t_nippo_meisai m
    JOIN t_nippo_header h ON m.fk_nippo_id = h.pk_nippo_id
    JOIN t_tantosha t ON h.fk_tantosha_id = t.pk_tantosha_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY m.f_jikai_yoteibi ASC, t.f_tantosha_name ASC
");
$stmt->execute($params);
$schedules = $stmt->fetchAll();

// 日付ごとにグループ化
$by_date = [];
foreach ($schedules as $s) {
    $by_date[$s['f_jikai_yoteibi']][] = $s;
}

// 担当者カラー
$tanto_colors = [];
$color_list = ['#1B3A6B','#2E75B6','#2e7d32','#e65100','#6a1b9a','#00838f','#c62828','#4e342e'];
$color_idx = 0;
foreach ($schedules as $s) {
    if (!isset($tanto_colors[$s['pk_tantosha_id']])) {
        $tanto_colors[$s['pk_tantosha_id']] = $color_list[$color_idx % count($color_list)];
        $color_idx++;
    }
}

// 担当者一覧（凡例用）
$tantoshas = $pdo->query("SELECT * FROM t_tantosha WHERE f_zaiseki_flag='有効' ORDER BY f_tantosha_name")->fetchAll();

echo html_header('訪問予定カレンダー');
echo nav_bar();
?>
<div class="container">
  <div class="page-title">訪問予定　カレンダー</div>

  <!-- カレンダーヘッダ -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px">
    <div style="display:flex;align-items:center;gap:12px">
      <a href="?year=<?= $prev_y ?>&month=<?= $prev_m ?>" class="btn btn-gray btn-sm">◀ 前月</a>
      <span style="font-size:20px;font-weight:700;color:#1B3A6B"><?= $year ?>年<?= $month ?>月</span>
      <a href="?year=<?= $next_y ?>&month=<?= $next_m ?>" class="btn btn-gray btn-sm">次月 ▶</a>
      <a href="?year=<?= date('Y') ?>&month=<?= date('n') ?>" class="btn btn-blue btn-sm">今月</a>
    </div>
    <!-- 担当者凡例 -->
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php foreach($tantoshas as $t): ?>
      <?php $col = $tanto_colors[$t['pk_tantosha_id']] ?? '#888'; ?>
      <span style="display:flex;align-items:center;gap:4px;font-size:11px">
        <span style="width:10px;height:10px;border-radius:50%;background:<?= $col ?>;display:inline-block"></span>
        <?= h($t['f_tantosha_name']) ?>
      </span>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- カレンダー本体 -->
  <div class="card" style="margin-bottom:16px">
    <div class="card-body" style="padding:0">
      <!-- 曜日ヘッダ -->
      <div style="display:grid;grid-template-columns:repeat(7,1fr);border-bottom:2px solid #1B3A6B">
        <?php
        $dow_labels = ['日','月','火','水','木','金','土'];
        $dow_colors = ['#c62828','#1a1a2e','#1a1a2e','#1a1a2e','#1a1a2e','#1a1a2e','#1565c0'];
        foreach($dow_labels as $i => $dow): ?>
        <div style="text-align:center;padding:8px;font-weight:700;font-size:13px;color:<?= $dow_colors[$i] ?>;background:#1B3A6B;color:#fff">
          <?= $dow ?>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- 日付グリッド -->
      <?php
      $today_str = date('Y-m-d');
      $day = 1;
      $rows = ceil(($start_dow + $days_in_month) / 7);
      for ($row = 0; $row < $rows; $row++):
      ?>
      <div style="display:grid;grid-template-columns:repeat(7,1fr);border-bottom:1px solid #e8eef5;min-height:90px">
        <?php for ($col = 0; $col < 7; $col++):
          $cell_idx = $row * 7 + $col;
          $is_valid = ($cell_idx >= $start_dow && $day <= $days_in_month);
          $date_str = $is_valid ? sprintf('%04d-%02d-%02d', $year, $month, $day) : '';
          $is_today = ($date_str === $today_str);
          $is_sunday = ($col === 0);
          $is_saturday = ($col === 6);
          $events = $is_valid ? ($by_date[$date_str] ?? []) : [];
          $bg = $is_today ? '#EEF3FA' : ($row % 2 === 0 ? '#fff' : '#fafbfc');
        ?>
        <div style="border-right:1px solid #e8eef5;padding:4px;background:<?= $bg ?>;min-height:90px;vertical-align:top">
          <?php if($is_valid): ?>
          <div style="font-size:13px;font-weight:<?= $is_today?'700':'500' ?>;color:<?= $is_today?'#2E75B6':($is_sunday?'#c62828':($is_saturday?'#1565c0':'#1a1a2e')) ?>;margin-bottom:4px;<?= $is_today?'background:#2E75B6;color:#fff;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;':'' ?>">
            <?= $day ?>
          </div>
          <?php foreach($events as $e):
            $col_bg = $tanto_colors[$e['pk_tantosha_id']] ?? '#888';
          ?>
          <div style="background:<?= $col_bg ?>;color:#fff;border-radius:3px;padding:2px 5px;margin-bottom:2px;font-size:10px;cursor:pointer;overflow:hidden;white-space:nowrap;text-overflow:ellipsis"
               title="<?= h($e['f_tantosha_name']) ?>：<?= h($e['f_homonsakimei']) ?> / <?= h($e['f_jikai_action']) ?>"
               onclick="location.href='detail.php?id=<?= h($e['pk_nippo_id']) ?>'">
            <?= h(mb_substr($e['f_homonsakimei'],0,8)) ?>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <?php
          if($is_valid) $day++;
        endfor; ?>
      </div>
      <?php endfor; ?>
    </div>
  </div>

  <!-- 今月の訪問予定リスト -->
  <?php if(!empty($schedules)): ?>
  <div class="card">
    <div class="card-header">
      <?= $year ?>年<?= $month ?>月　訪問予定一覧
      <span style="font-size:12px;font-weight:400"><?= count($schedules) ?>件</span>
    </div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>予定日</th>
            <th>担当者</th>
            <th>訪問先</th>
            <th>次回アクション</th>
            <th>受注見込み</th>
            <th style="text-align:center">詳細</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($schedules as $s):
          $col = $tanto_colors[$s['pk_tantosha_id']] ?? '#888';
        ?>
        <tr>
          <td style="white-space:nowrap;font-weight:600"><?= h(date('Y/m/d', strtotime($s['f_jikai_yoteibi']))) ?></td>
          <td>
            <span style="display:inline-flex;align-items:center;gap:5px">
              <span style="width:8px;height:8px;border-radius:50%;background:<?= $col ?>;display:inline-block"></span>
              <?= h($s['f_tantosha_name']) ?>
            </span>
          </td>
          <td style="font-weight:600"><?= h($s['f_homonsakimei']) ?></td>
          <td><?= h($s['f_jikai_action']) ?></td>
          <td><?= juchu_badge($s['f_juchu_mikomikubun']) ?></td>
          <td style="text-align:center">
            <a href="detail.php?id=<?= h($s['pk_nippo_id']) ?>" class="btn btn-blue btn-sm">詳細</a>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>
<?= html_footer() ?>
