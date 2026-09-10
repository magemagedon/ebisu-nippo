<?php
require_once 'db.php';
require_once 'common.php';

$pdo = get_db();

// 次回アクション一覧（次回予定日があるもの）
$where = ['m.f_jikai_yoteibi IS NOT NULL'];
$params = [];

// 一般ユーザーは自分のみ、部門管理者は自部門のみ
[$cond, $ps] = visible_tantosha_where($pdo, 'h.fk_tantosha_id');
if ($cond) { $where[] = $cond; array_push($params, ...$ps); }

$stmt = $pdo->prepare("
    SELECT m.*, h.f_date, h.pk_nippo_id, t.f_tantosha_name,
           DATEDIFF(m.f_jikai_yoteibi, CURDATE()) AS days_left
    FROM t_nippo_meisai m
    JOIN t_nippo_header h ON m.fk_nippo_id = h.pk_nippo_id
    JOIN t_tantosha t ON h.fk_tantosha_id = t.pk_tantosha_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY m.f_jikai_yoteibi ASC
");
$stmt->execute($params);
$actions = $stmt->fetchAll();

// 分類
$overdue  = array_filter($actions, fn($a) => $a['days_left'] < 0);
$today    = array_filter($actions, fn($a) => $a['days_left'] === 0 || $a['days_left'] === '0');
$week     = array_filter($actions, fn($a) => $a['days_left'] > 0 && $a['days_left'] <= 7);
$future   = array_filter($actions, fn($a) => $a['days_left'] > 7);

echo html_header('次回アクション一覧');
echo nav_bar();
?>
<div class="container">
  <div class="page-title">次回アクション一覧</div>

  <!-- サマリー -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px">
    <?php
    $summary = [
      ['期限超過', count($overdue), '#b71c1c', '#fdecea'],
      ['本日',     count($today),   '#e65100', '#fff8e1'],
      ['今週中',   count($week),    '#1565c0', '#e3f2fd'],
      ['それ以降', count($future),  '#2e7d32', '#e6f4ea'],
    ];
    foreach($summary as [$label, $cnt, $color, $bg]):
    ?>
    <div style="background:<?= $bg ?>;border:1px solid <?= $color ?>33;border-radius:8px;padding:12px;text-align:center">
      <div style="font-size:11px;color:<?= $color ?>;font-weight:600;margin-bottom:6px"><?= $label ?></div>
      <div style="font-size:28px;font-weight:700;color:<?= $color ?>"><?= $cnt ?></div>
      <div style="font-size:11px;color:#888">件</div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php
  $sections = [
    ['🔴 期限超過', $overdue,  '#b71c1c', '#fdecea'],
    ['🟠 本日',     $today,    '#e65100', '#fff3e0'],
    ['🔵 今週中',   $week,     '#1565c0', '#e3f2fd'],
    ['🟢 それ以降', $future,   '#2e7d32', '#e6f4ea'],
  ];
  foreach($sections as [$title, $items, $color, $bg]):
    if(empty($items)) continue;
  ?>
  <div class="card">
    <div class="card-header" style="background:<?= $color ?>">
      <?= $title ?>
      <span style="font-size:12px;font-weight:400"><?= count($items) ?> 件</span>
    </div>
    <div class="card-body" style="padding:0">
      <!-- PC -->
      <div class="table-wrap pc-only">
      <table>
        <thead>
          <tr>
            <th>予定日</th>
            <th>残日数</th>
            <th>担当者</th>
            <th>訪問先</th>
            <th>次回アクション</th>
            <th>受注見込み</th>
            <th style="text-align:center">日報</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($items as $a):
          $dl = (int)$a['days_left'];
          $day_label = $dl < 0 ? abs($dl).'日超過' : ($dl === 0 ? '本日' : $dl.'日後');
          $day_color = $dl < 0 ? '#b71c1c' : ($dl === 0 ? '#e65100' : ($dl <= 7 ? '#1565c0' : '#2e7d32'));
        ?>
        <tr>
          <td style="white-space:nowrap;font-weight:600"><?= h(date('Y/m/d', strtotime($a['f_jikai_yoteibi']))) ?></td>
          <td style="text-align:center">
            <span style="color:<?= $day_color ?>;font-weight:700;font-size:13px"><?= $day_label ?></span>
          </td>
          <td><?= h($a['f_tantosha_name']) ?></td>
          <td style="font-weight:600"><?= h($a['f_homonsakimei']) ?></td>
          <td><?= h($a['f_jikai_action']) ?></td>
          <td><?= juchu_badge($a['f_juchu_mikomikubun']) ?></td>
          <td style="text-align:center">
            <a href="detail.php?id=<?= h($a['pk_nippo_id']) ?>" class="btn btn-blue btn-sm">詳細</a>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <!-- スマホ -->
      <div class="sp-only" style="padding:10px">
        <?php foreach($items as $a):
          $dl = (int)$a['days_left'];
          $day_label = $dl < 0 ? abs($dl).'日超過' : ($dl === 0 ? '本日' : $dl.'日後');
          $day_color = $dl < 0 ? '#b71c1c' : ($dl === 0 ? '#e65100' : ($dl <= 7 ? '#1565c0' : '#2e7d32'));
        ?>
        <div style="background:#fff;border:1px solid #e0e8f0;border-radius:6px;padding:12px;margin-bottom:8px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <span style="font-weight:700;font-size:15px;color:#1B3A6B"><?= h($a['f_homonsakimei']) ?></span>
            <span style="color:<?= $day_color ?>;font-weight:700;font-size:13px"><?= $day_label ?></span>
          </div>
          <div style="font-size:12px;color:#666;margin-bottom:4px">
            <?= h(date('Y/m/d', strtotime($a['f_jikai_yoteibi']))) ?> ／ <?= h($a['f_tantosha_name']) ?>
          </div>
          <div style="font-size:13px;margin-bottom:8px"><?= h($a['f_jikai_action']) ?></div>
          <div style="display:flex;justify-content:space-between;align-items:center">
            <?= juchu_badge($a['f_juchu_mikomikubun']) ?>
            <a href="detail.php?id=<?= h($a['pk_nippo_id']) ?>" class="btn btn-blue btn-sm">詳細 →</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <?php if(empty($actions)): ?>
  <div class="card"><div class="card-body" style="text-align:center;color:#999;padding:40px">次回アクションはありません</div></div>
  <?php endif; ?>

</div>
<?= html_footer() ?>
