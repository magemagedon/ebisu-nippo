<?php
require_once 'db.php';
require_once 'common.php';

$pdo = get_db();

// 期間（デフォルト：過去3ヶ月）
$f_from = $_GET['f_from'] ?? date('Y-m-d', strtotime('-3 months'));
$f_to   = $_GET['f_to']   ?? date('Y-m-d');

// 古紙相場データ（日付・取引先別）
$where = ['m.f_furushi_soba IS NOT NULL', 'h.f_date BETWEEN ? AND ?'];
$params = [$f_from, $f_to];

if ($_SESSION['kengen'] !== '管理者') {
    $where[] = 'h.fk_tantosha_id = ?';
    $params[] = $_SESSION['tantosha_id'];
}

$stmt = $pdo->prepare("
    SELECT h.f_date,
           COALESCE(tr.f_torihikisaki_name, m.f_homonsakimei) AS torihikisaki_name,
           m.f_furushi_soba, m.f_kaishu_ryo, m.f_tanka,
           t.f_tantosha_name
    FROM t_nippo_meisai m
    JOIN t_nippo_header h ON m.fk_nippo_id = h.pk_nippo_id
    JOIN t_tantosha t ON h.fk_tantosha_id = t.pk_tantosha_id
    LEFT JOIN t_torihikisaki tr ON m.fk_torihikisaki_id = tr.pk_torihikisaki_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY h.f_date ASC
");
$stmt->execute($params);
$raw = $stmt->fetchAll();

// 日付ごとの平均相場
$by_date = [];
foreach ($raw as $r) {
    $d = $r['f_date'];
    if (!isset($by_date[$d])) $by_date[$d] = [];
    $by_date[$d][] = (float)$r['f_furushi_soba'];
}
$dates  = array_keys($by_date);
$avg_soba = array_map(fn($v) => round(array_sum($v)/count($v)), $by_date);

// 取引先別相場データ
$by_torihiki = [];
foreach ($raw as $r) {
    $name = $r['torihikisaki_name'];
    if (!isset($by_torihiki[$name])) $by_torihiki[$name] = [];
    $by_torihiki[$name][$r['f_date']] = (float)$r['f_furushi_soba'];
}

// 月別回収量
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(h.f_date,'%Y-%m') AS month,
           SUM(m.f_kaishu_ryo) AS total_ryo,
           SUM(COALESCE(m.f_kaishu_ryo * m.f_tanka, 0)) AS total_kin
    FROM t_nippo_meisai m
    JOIN t_nippo_header h ON m.fk_nippo_id = h.pk_nippo_id
    WHERE m.f_kaishu_ryo IS NOT NULL AND h.f_date BETWEEN ? AND ?
    GROUP BY month ORDER BY month ASC
");
$stmt->execute([$f_from, $f_to]);
$monthly = $stmt->fetchAll();

echo html_header('古紙相場グラフ');
echo nav_bar();
?>
<div class="container">
  <div class="page-title">古紙相場　グラフ</div>

  <!-- 期間選択 -->
  <form method="get" class="search-area" style="margin-bottom:16px">
    <label>期間</label>
    <input type="date" name="f_from" value="<?= h($f_from) ?>">
    <span style="color:#666">〜</span>
    <input type="date" name="f_to" value="<?= h($f_to) ?>">
    <button type="submit" class="btn btn-blue btn-sm">表示</button>
    <a href="?f_from=<?= date('Y-m-d', strtotime('-1 month')) ?>&f_to=<?= date('Y-m-d') ?>" class="btn btn-gray btn-sm">1ヶ月</a>
    <a href="?f_from=<?= date('Y-m-d', strtotime('-3 months')) ?>&f_to=<?= date('Y-m-d') ?>" class="btn btn-gray btn-sm">3ヶ月</a>
    <a href="?f_from=<?= date('Y-m-d', strtotime('-6 months')) ?>&f_to=<?= date('Y-m-d') ?>" class="btn btn-gray btn-sm">6ヶ月</a>
  </form>

  <?php if(empty($raw)): ?>
  <div class="card"><div class="card-body" style="text-align:center;color:#999;padding:40px">期間内に古紙相場データがありません</div></div>
  <?php else: ?>

  <!-- 古紙相場推移グラフ -->
  <div class="card">
    <div class="card-header">古紙相場　推移（円/t）</div>
    <div class="card-body">
      <canvas id="sobaChart" height="80"></canvas>
    </div>
  </div>

  <!-- 月別回収量グラフ -->
  <div class="card">
    <div class="card-header">月別　回収量・金額</div>
    <div class="card-body">
      <canvas id="monthlyChart" height="80"></canvas>
    </div>
  </div>

  <!-- データテーブル -->
  <div class="card">
    <div class="card-header">相場データ一覧</div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>日付</th>
            <th>取引先</th>
            <th>担当者</th>
            <th style="text-align:right">古紙相場（円/t）</th>
            <th style="text-align:right">回収量（t）</th>
            <th style="text-align:right">単価（円/t）</th>
            <th style="text-align:right">金額（円）</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($raw as $r): ?>
        <tr>
          <td style="white-space:nowrap"><?= h(date('Y/m/d', strtotime($r['f_date']))) ?></td>
          <td style="font-weight:600"><?= h($r['torihikisaki_name']) ?></td>
          <td><?= h($r['f_tantosha_name']) ?></td>
          <td style="text-align:right"><?= number_format($r['f_furushi_soba']) ?></td>
          <td style="text-align:right"><?= $r['f_kaishu_ryo'] ? $r['f_kaishu_ryo'] : '―' ?></td>
          <td style="text-align:right"><?= $r['f_tanka'] ? number_format($r['f_tanka']) : '―' ?></td>
          <td style="text-align:right;font-weight:600;color:#1B3A6B">
            <?= ($r['f_kaishu_ryo'] && $r['f_tanka']) ? number_format($r['f_kaishu_ryo'] * $r['f_tanka']) : '―' ?>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if(!empty($raw)): ?>
// 相場推移グラフ
const sobaCtx = document.getElementById('sobaChart').getContext('2d');
new Chart(sobaCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(fn($d) => date('m/d', strtotime($d)), $dates), JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            label: '平均古紙相場（円/t）',
            data: <?= json_encode(array_values($avg_soba)) ?>,
            borderColor: '#1B3A6B',
            backgroundColor: 'rgba(27,58,107,0.08)',
            borderWidth: 2,
            pointBackgroundColor: '#2E75B6',
            pointRadius: 5,
            fill: true,
            tension: 0.3,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'top' },
            tooltip: { callbacks: { label: ctx => ctx.parsed.y.toLocaleString() + '円/t' } }
        },
        scales: {
            y: {
                ticks: { callback: v => v.toLocaleString() + '円' },
                grid: { color: 'rgba(0,0,0,0.05)' }
            }
        }
    }
});

// 月別回収量グラフ
const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
new Chart(monthlyCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($monthly, 'month'), JSON_UNESCAPED_UNICODE) ?>,
        datasets: [
            {
                label: '回収量（t）',
                data: <?= json_encode(array_map(fn($m) => (float)$m['total_ryo'], $monthly)) ?>,
                backgroundColor: 'rgba(46,117,182,0.7)',
                borderColor: '#2E75B6',
                borderWidth: 1,
                yAxisID: 'y',
            },
            {
                label: '金額（万円）',
                data: <?= json_encode(array_map(fn($m) => round($m['total_kin']/10000, 1), $monthly)) ?>,
                backgroundColor: 'rgba(230,81,0,0.7)',
                borderColor: '#E65100',
                borderWidth: 1,
                type: 'line',
                yAxisID: 'y2',
                tension: 0.3,
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: {
            y:  { position: 'left',  ticks: { callback: v => v + 't' }, grid: { color: 'rgba(0,0,0,0.05)' } },
            y2: { position: 'right', ticks: { callback: v => v + '万円' }, grid: { display: false } }
        }
    }
});
<?php endif; ?>
</script>
<?= html_footer() ?>
