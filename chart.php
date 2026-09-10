<?php
require_once 'db.php';
require_once 'common.php';

$pdo = get_db();

// ===================== 外部市況（手動入力・管理者のみ） =====================
$gaibu_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'gaibu_soba') {
    if ($_SESSION['kengen'] !== '管理者') {
        header('Location: chart.php'); exit;
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $date = $_POST['f_date'] ?? '';
        $item = trim($_POST['f_item_name'] ?? '');
        $price = $_POST['f_price'] ?? '';
        if ($date && $item && $price !== '') {
            $pdo->prepare("INSERT INTO t_gaibu_soba (pk_gaibu_soba_id,f_date,f_item_name,f_price,f_unit,f_source,f_biko,fk_tantosha_id,f_created_at) VALUES (?,?,?,?,?,?,?,?,NOW())")
                ->execute([generate_uuid(),$date,$item,$price,$_POST['f_unit']?:'円/kg',$_POST['f_source']??'',$_POST['f_biko']??'',$_SESSION['tantosha_id']]);
        }
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM t_gaibu_soba WHERE pk_gaibu_soba_id=?")->execute([$_POST['gaibu_soba_id']??'']);
    }
    header('Location: chart.php?' . http_build_query(['f_from'=>$_GET['f_from']??'', 'f_to'=>$_GET['f_to']??'', 'f_product'=>$_GET['f_product']??''])); exit;
}

// 期間（デフォルト：過去3ヶ月）
$f_from = $_GET['f_from'] ?? date('Y-m-d', strtotime('-3 months'));
$f_to   = $_GET['f_to']   ?? date('Y-m-d');
$f_product = $_GET['f_product'] ?? '';

$products = $pdo->query("SELECT pk_product_id, f_product_name, f_kubun FROM t_product WHERE f_active='有効' ORDER BY f_sort_order,f_product_name")->fetchAll();

// 古紙相場データ（日付・取引先別。商品を選ぶと主要商品ごとの推移に絞り込み）
$where = ['m.f_furushi_soba IS NOT NULL', 'h.f_date BETWEEN ? AND ?'];
$params = [$f_from, $f_to];

if ($f_product) { $where[] = 'm.fk_product_id = ?'; $params[] = $f_product; }

[$cond, $ps] = visible_tantosha_where($pdo, 'h.fk_tantosha_id');
if ($cond) { $where[] = $cond; array_push($params, ...$ps); }

$stmt = $pdo->prepare("
    SELECT h.f_date,
           COALESCE(tr.f_torihikisaki_name, m.f_homonsakimei) AS torihikisaki_name,
           m.f_furushi_soba, m.f_kaishu_ryo, m.f_tanka,
           t.f_tantosha_name, p.f_product_name
    FROM t_nippo_meisai m
    JOIN t_nippo_header h ON m.fk_nippo_id = h.pk_nippo_id
    JOIN t_tantosha t ON h.fk_tantosha_id = t.pk_tantosha_id
    LEFT JOIN t_torihikisaki tr ON m.fk_torihikisaki_id = tr.pk_torihikisaki_id
    LEFT JOIN t_product p ON m.fk_product_id = p.pk_product_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY h.f_date ASC
");
$stmt->execute($params);
$raw = $stmt->fetchAll();

// 外部市況（直近1年、品目別）
$gaibu_from = date('Y-m-d', strtotime('-1 year'));
$stmt = $pdo->prepare("SELECT * FROM t_gaibu_soba WHERE f_date >= ? ORDER BY f_date ASC");
$stmt->execute([$gaibu_from]);
$gaibu_raw = $stmt->fetchAll();
$gaibu_items = [];
$gaibu_dates_set = [];
foreach ($gaibu_raw as $g) {
    $gaibu_items[$g['f_item_name']][$g['f_date']] = ['price' => (float)$g['f_price'], 'unit' => $g['f_unit']];
    $gaibu_dates_set[$g['f_date']] = true;
}
$gaibu_dates = array_keys($gaibu_dates_set);
sort($gaibu_dates);
$gaibu_recent = array_slice(array_reverse($gaibu_raw), 0, 20);

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
    <label>対象商品</label>
    <select name="f_product">
      <option value="">全体（商品指定なし含む）</option>
      <?php foreach($products as $p): ?>
      <option value="<?= h($p['pk_product_id']) ?>" <?= $f_product===$p['pk_product_id']?'selected':'' ?>>[<?= h($p['f_kubun']) ?>] <?= h($p['f_product_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-blue btn-sm">表示</button>
    <a href="?f_from=<?= date('Y-m-d', strtotime('-1 month')) ?>&f_to=<?= date('Y-m-d') ?>&f_product=<?= h($f_product) ?>" class="btn btn-gray btn-sm">1ヶ月</a>
    <a href="?f_from=<?= date('Y-m-d', strtotime('-3 months')) ?>&f_to=<?= date('Y-m-d') ?>&f_product=<?= h($f_product) ?>" class="btn btn-gray btn-sm">3ヶ月</a>
    <a href="?f_from=<?= date('Y-m-d', strtotime('-6 months')) ?>&f_to=<?= date('Y-m-d') ?>&f_product=<?= h($f_product) ?>" class="btn btn-gray btn-sm">6ヶ月</a>
  </form>

  <?php if(empty($raw)): ?>
  <div class="card"><div class="card-body" style="text-align:center;color:#999;padding:40px">期間内に社内相場データがありません</div></div>
  <?php else: ?>

  <!-- 古紙相場推移グラフ -->
  <div class="card">
    <div class="card-header">社内相場　推移（円/t）<?= $f_product ? '　※商品で絞り込み中（社外非公開データです）' : '' ?></div>
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
            <th>商品</th>
            <th>担当者</th>
            <th style="text-align:right">相場（円/t）</th>
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
          <td style="color:#666"><?= h($r['f_product_name']) ?: '―' ?></td>
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

  <!-- 外部市況（手動入力） -->
  <div class="card" style="margin-top:24px">
    <div class="card-header" style="background:#2E75B6">
      外部市況（古紙専門誌・バージン樹脂／ナフサ・石炭 等）
      <span style="font-size:12px;font-weight:400">直近1年・手動入力</span>
    </div>
    <div class="card-body">
      <div style="font-size:12px;color:#666;margin-bottom:12px">社内相場は社外非公開です。ここには公開情報（専門誌・市況調査機関等）を入力し、値上げ交渉タイミングの判断材料として社内相場と見比べてください。</div>
      <?php if(!empty($gaibu_items)): ?>
      <canvas id="gaibuChart" height="70" style="margin-bottom:16px"></canvas>
      <?php endif; ?>

      <?php if($_SESSION['kengen'] === '管理者'): ?>
      <form method="post" style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr;gap:8px;align-items:end;margin-bottom:16px;padding:12px;background:#f7f9fc;border-radius:6px">
        <input type="hidden" name="form" value="gaibu_soba">
        <input type="hidden" name="action" value="add">
        <div class="form-group" style="margin:0"><label>日付</label><input type="date" name="f_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        <div class="form-group" style="margin:0"><label>品目</label><input type="text" name="f_item_name" class="form-control" placeholder="例：古紙（段ボール）" required></div>
        <div class="form-group" style="margin:0"><label>価格</label><input type="number" name="f_price" class="form-control" step="0.01" required></div>
        <div class="form-group" style="margin:0"><label>単位</label><input type="text" name="f_unit" class="form-control" value="円/kg"></div>
        <div class="form-group" style="margin:0"><label>出典</label><input type="text" name="f_source" class="form-control" placeholder="専門誌名等"></div>
        <div style="grid-column:1/-1;text-align:right"><button type="submit" class="btn btn-blue btn-sm">追加する</button></div>
      </form>
      <?php endif; ?>

      <?php if(empty($gaibu_recent)): ?>
      <div style="text-align:center;color:#999;padding:20px">外部市況データがまだ登録されていません<?= $_SESSION['kengen']==='管理者' ? '。上のフォームから登録してください。' : '' ?></div>
      <?php else: ?>
      <div class="table-wrap">
      <table>
        <thead><tr><th>日付</th><th>品目</th><th style="text-align:right">価格</th><th>出典</th><?php if($_SESSION['kengen']==='管理者'): ?><th style="text-align:center">操作</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach($gaibu_recent as $g): ?>
        <tr>
          <td style="white-space:nowrap"><?= h(date('Y/m/d', strtotime($g['f_date']))) ?></td>
          <td style="font-weight:600"><?= h($g['f_item_name']) ?></td>
          <td style="text-align:right"><?= number_format($g['f_price'],2) ?><?= h($g['f_unit']) ?></td>
          <td style="font-size:12px;color:#666"><?= h($g['f_source']) ?></td>
          <?php if($_SESSION['kengen']==='管理者'): ?>
          <td style="text-align:center">
            <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？')">
              <input type="hidden" name="form" value="gaibu_soba"><input type="hidden" name="action" value="delete"><input type="hidden" name="gaibu_soba_id" value="<?= h($g['pk_gaibu_soba_id']) ?>">
              <button type="submit" class="btn btn-danger btn-sm">削除</button>
            </form>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
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

<?php if(!empty($gaibu_items)): ?>
// 外部市況グラフ（品目ごとの折れ線。単位が異なるため参考表示）
const gaibuCtx = document.getElementById('gaibuChart').getContext('2d');
const gaibuColors = ['#1B3A6B','#2E75B6','#e65100','#2e7d32','#c62828','#6a1b9a'];
new Chart(gaibuCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(fn($d) => date('Y/m/d', strtotime($d)), $gaibu_dates), JSON_UNESCAPED_UNICODE) ?>,
        datasets: [
            <?php $ci = 0; foreach($gaibu_items as $item => $points): ?>
            {
                label: <?= json_encode($item . '（' . (reset($points)['unit'] ?? '') . '）', JSON_UNESCAPED_UNICODE) ?>,
                data: <?= json_encode(array_map(fn($d) => $points[$d]['price'] ?? null, $gaibu_dates)) ?>,
                borderColor: gaibuColors[<?= $ci % 6 ?>],
                backgroundColor: 'transparent',
                borderWidth: 2,
                pointRadius: 4,
                spanGaps: true,
                tension: 0.2,
            },
            <?php $ci++; endforeach; ?>
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: {
            y: { grid: { color: 'rgba(0,0,0,0.05)' } }
        }
    }
});
<?php endif; ?>
</script>
<?= html_footer() ?>
