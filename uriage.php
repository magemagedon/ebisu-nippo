<?php
require_once 'db.php';
require_once 'common.php';
if ($_SESSION['kengen'] !== '管理者') { header('Location: index.php'); exit; }

$pdo = get_db();
$msg = ''; $msg_type = 'success';
if (isset($_GET['csv_msg'])) { $msg = $_GET['csv_msg']; $msg_type = $_GET['csv_msg_type'] ?? 'success'; }

// バッチ削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_batch') {
    $pdo->prepare("DELETE FROM t_uriage WHERE f_import_batch=?")->execute([$_POST['batch']??'']);
    header('Location: uriage.php?csv_msg=' . urlencode('取込データを削除しました。') . '&csv_msg_type=success');
    exit;
}

$this_month = date('Y-m');
$last_month = date('Y-m', strtotime('-1 month'));
$last_year_month = date('Y-m', strtotime('-1 year'));

function month_sum_by($pdo, $groupExpr, $groupLabel) {
    global $this_month, $last_month, $last_year_month;
    $stmt = $pdo->query("
        SELECT {$groupExpr} AS grp, DATE_FORMAT(f_date,'%Y-%m') AS ym, SUM(f_kingaku) AS total
        FROM t_uriage
        WHERE DATE_FORMAT(f_date,'%Y-%m') IN ('{$this_month}','{$last_month}','{$last_year_month}')
        GROUP BY grp, ym
    ");
    $rows = $stmt->fetchAll();
    $result = [];
    foreach ($rows as $r) {
        $g = $r['grp'] !== null && $r['grp'] !== '' ? $r['grp'] : '未設定';
        if (!isset($result[$g])) $result[$g] = ['this'=>0,'last'=>0,'lastyear'=>0];
        if ($r['ym'] === $this_month) $result[$g]['this'] = (float)$r['total'];
        elseif ($r['ym'] === $last_month) $result[$g]['last'] = (float)$r['total'];
        elseif ($r['ym'] === $last_year_month) $result[$g]['lastyear'] = (float)$r['total'];
    }
    ksort($result);
    return $result;
}

$by_busho   = month_sum_by($pdo, "f_busho_name", '部門');
$by_segment = month_sum_by($pdo, "f_segment", 'セグメント');

// 取引先一覧（売上データがあるもの）
$torihikisaki_opts = $pdo->query("
    SELECT COALESCE(fk_torihikisaki_id,'') AS tid, f_torihikisaki_name AS name
    FROM t_uriage WHERE f_torihikisaki_name IS NOT NULL AND f_torihikisaki_name <> ''
    GROUP BY tid, name ORDER BY name
")->fetchAll();

$selected_tori = $_GET['tori'] ?? '';
$tori_trend = [];
if ($selected_tori !== '') {
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(f_date,'%Y-%m') AS ym, SUM(f_kingaku) AS total
        FROM t_uriage
        WHERE f_torihikisaki_name = ? AND f_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY ym ORDER BY ym ASC
    ");
    $stmt->execute([$selected_tori]);
    $tori_trend = $stmt->fetchAll();
}

// 取込バッチ一覧（直近10件）
$batches = $pdo->query("
    SELECT u.f_import_batch, MIN(u.f_created_at) AS imported_at, COUNT(*) AS cnt, SUM(u.f_kingaku) AS total, t.f_tantosha_name
    FROM t_uriage u LEFT JOIN t_tantosha t ON u.fk_tantosha_id = t.pk_tantosha_id
    WHERE u.f_import_batch IS NOT NULL
    GROUP BY u.f_import_batch ORDER BY imported_at DESC LIMIT 10
")->fetchAll();

$total_records = $pdo->query("SELECT COUNT(*) FROM t_uriage")->fetchColumn();

echo html_header('経営分析');
echo nav_bar();
?>
<div class="container">
  <div class="page-title">経営分析（売上実績）</div>

  <?php if($msg): ?>
  <div class="alert alert-<?= $msg_type ?>"><?= h($msg) ?></div>
  <?php endif; ?>

  <?php if($total_records == 0): ?>
  <div class="card"><div class="card-body" style="text-align:center;color:#999;padding:30px">
    売上データがまだ取り込まれていません。下記からCSVを取り込んでください。
  </div></div>
  <?php endif; ?>

  <!-- CSV取込 -->
  <div class="card">
    <div class="card-header">
      売上データ（CSV）を取り込む
      <a href="csv_import_uriage.php?action=template" class="btn btn-gray btn-sm">📥 テンプレートDL</a>
    </div>
    <div class="card-body">
      <form method="post" action="csv_import_uriage.php" enctype="multipart/form-data">
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <input type="file" name="csv_file" class="form-control" style="flex:1;min-width:200px" accept=".csv" required>
          <button type="submit" class="btn btn-primary btn-sm">📤 取込む</button>
        </div>
        <div style="font-size:11px;color:#888;margin-top:6px">
          列構成：取引日, 取引先名, 取引先コード（任意）, 商品名（任意）, 部門名（任意）, 金額, 数量（任意）, 備考（任意）。<br>
          取引先名・商品名・部門名はマスターと自動照合します（未登録でも件数として取り込まれます）。
        </div>
      </form>
    </div>
  </div>

  <!-- 部門別月次収益 -->
  <div class="card">
    <div class="card-header">部門別　月次収益（<?= h($this_month) ?> ／ 前月 ／ 前年同月）</div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap"><table>
        <thead><tr><th>部門</th><th style="text-align:right"><?= h($this_month) ?></th><th style="text-align:right"><?= h($last_month) ?></th><th style="text-align:right"><?= h($last_year_month) ?>（前年同月）</th><th style="text-align:right">前年同月比</th></tr></thead>
        <tbody>
        <?php if(empty($by_busho)): ?>
        <tr><td colspan="5" style="text-align:center;padding:16px;color:#999">データがありません</td></tr>
        <?php endif; ?>
        <?php foreach($by_busho as $name => $v): $rate = $v['lastyear']>0 ? round($v['this']/$v['lastyear']*100) : null; ?>
        <tr>
          <td style="font-weight:600"><?= h($name) ?></td>
          <td style="text-align:right"><?= number_format($v['this']) ?>円</td>
          <td style="text-align:right;color:#666"><?= number_format($v['last']) ?>円</td>
          <td style="text-align:right;color:#666"><?= number_format($v['lastyear']) ?>円</td>
          <td style="text-align:right"><?= $rate!==null ? ($rate>=100?'<span style="color:#2e7d32;font-weight:600">':'<span style="color:#c62828;font-weight:600">').$rate.'%</span>' : '―' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>

  <!-- セグメント別月次収益 -->
  <div class="card">
    <div class="card-header">セグメント別　月次収益（<?= h($this_month) ?> ／ 前月 ／ 前年同月）</div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap"><table>
        <thead><tr><th>セグメント</th><th style="text-align:right"><?= h($this_month) ?></th><th style="text-align:right"><?= h($last_month) ?></th><th style="text-align:right"><?= h($last_year_month) ?>（前年同月）</th><th style="text-align:right">前年同月比</th></tr></thead>
        <tbody>
        <?php if(empty($by_segment)): ?>
        <tr><td colspan="5" style="text-align:center;padding:16px;color:#999">データがありません</td></tr>
        <?php endif; ?>
        <?php foreach($by_segment as $name => $v): $rate = $v['lastyear']>0 ? round($v['this']/$v['lastyear']*100) : null; ?>
        <tr>
          <td style="font-weight:600"><?= h($name) ?></td>
          <td style="text-align:right"><?= number_format($v['this']) ?>円</td>
          <td style="text-align:right;color:#666"><?= number_format($v['last']) ?>円</td>
          <td style="text-align:right;color:#666"><?= number_format($v['lastyear']) ?>円</td>
          <td style="text-align:right"><?= $rate!==null ? ($rate>=100?'<span style="color:#2e7d32;font-weight:600">':'<span style="color:#c62828;font-weight:600">').$rate.'%</span>' : '―' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>

  <!-- 取引先別取引推移 -->
  <div class="card">
    <div class="card-header">取引先別　取引推移（直近12ヶ月）</div>
    <div class="card-body">
      <form method="get" class="search-area" style="margin-bottom:14px">
        <label>取引先</label>
        <select name="tori" onchange="this.form.submit()">
          <option value="">-- 選択してください --</option>
          <?php foreach($torihikisaki_opts as $t): ?>
          <option value="<?= h($t['name']) ?>" <?= $selected_tori===$t['name']?'selected':'' ?>><?= h($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <?php if($selected_tori && !empty($tori_trend)): ?>
      <canvas id="toriChart" height="80"></canvas>
      <?php elseif($selected_tori): ?>
      <div style="text-align:center;color:#999;padding:20px">直近12ヶ月のデータがありません</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- 取込履歴 -->
  <div class="card">
    <div class="card-header">取込履歴（直近10件）</div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap"><table>
        <thead><tr><th>取込日時</th><th>実行者</th><th style="text-align:right">件数</th><th style="text-align:right">合計金額</th><th style="text-align:center">操作</th></tr></thead>
        <tbody>
        <?php if(empty($batches)): ?>
        <tr><td colspan="5" style="text-align:center;padding:16px;color:#999">取込履歴がありません</td></tr>
        <?php endif; ?>
        <?php foreach($batches as $b): ?>
        <tr>
          <td style="white-space:nowrap"><?= h(date('Y/m/d H:i', strtotime($b['imported_at']))) ?></td>
          <td><?= h($b['f_tantosha_name']) ?></td>
          <td style="text-align:right"><?= (int)$b['cnt'] ?>件</td>
          <td style="text-align:right"><?= number_format($b['total']) ?>円</td>
          <td style="text-align:center">
            <form method="post" style="display:inline" onsubmit="return confirm('このバッチの売上データを全て削除しますか？（取り消せません）')">
              <input type="hidden" name="action" value="delete_batch"><input type="hidden" name="batch" value="<?= h($b['f_import_batch']) ?>">
              <button type="submit" class="btn btn-danger btn-sm">取消（削除）</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if($selected_tori && !empty($tori_trend)): ?>
new Chart(document.getElementById('toriChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($tori_trend,'ym'), JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            label: <?= json_encode($selected_tori . '　月次売上（円）', JSON_UNESCAPED_UNICODE) ?>,
            data: <?= json_encode(array_map(fn($r)=>(float)$r['total'], $tori_trend)) ?>,
            borderColor: '#1B3A6B',
            backgroundColor: 'rgba(27,58,107,0.08)',
            borderWidth: 2,
            pointRadius: 4,
            fill: true,
            tension: 0.3,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' }, tooltip: { callbacks: { label: ctx => ctx.parsed.y.toLocaleString() + '円' } } },
        scales: { y: { ticks: { callback: v => v.toLocaleString() + '円' }, grid: { color: 'rgba(0,0,0,0.05)' } } }
    }
});
<?php endif; ?>
</script>
<?= html_footer() ?>
