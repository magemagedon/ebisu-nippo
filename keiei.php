<?php
require_once 'db.php';
require_once 'common.php';
if ($_SESSION['kengen'] !== '管理者') { header('Location: index.php'); exit; }

$pdo = get_db();
$msg = ''; $msg_type = 'success';
if (isset($_GET['csv_msg'])) { $msg = $_GET['csv_msg']; $msg_type = $_GET['csv_msg_type'] ?? 'success'; }

$tab = $_GET['tab'] ?? 'hyou';

/* ===================== POST処理 ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_batch') {
        $pdo->prepare("DELETE FROM t_keiei_denpyo WHERE f_import_batch=?")->execute([$_POST['batch'] ?? '']);
        header('Location: keiei.php?csv_msg=' . urlencode('取込データを削除しました。') . '&csv_msg_type=success');
        exit;
    }

    if ($action === 'save_keikaku') {
        $rule_id = $_POST['rule_id'] ?? '';
        $kubun   = $_POST['kubun'] ?? '';
        $values  = $_POST['values'] ?? []; // ym => value
        if ($rule_id && in_array($kubun, ['計画', '前年'], true)) {
            $up = $pdo->prepare("INSERT INTO t_keiei_keikaku (pk_keikaku_id,f_yearmonth,fk_rule_id,f_kubun,f_value)
                                  VALUES (?,?,?,?,?)
                                  ON DUPLICATE KEY UPDATE f_value=VALUES(f_value), f_updated_at=NOW()");
            $del = $pdo->prepare("DELETE FROM t_keiei_keikaku WHERE f_yearmonth=? AND fk_rule_id=? AND f_kubun=?");
            foreach ($values as $ym => $v) {
                $v = trim(str_replace(',', '', (string)$v));
                if (!preg_match('/^\d{4}-\d{2}$/', $ym)) continue;
                if ($v === '') { $del->execute([$ym, $rule_id, $kubun]); continue; }
                $up->execute([generate_uuid(), $ym, $rule_id, $kubun, (float)$v]);
            }
            $msg = "{$kubun}を保存しました。"; $tab = 'hyou';
        }
    }

    // ---- 分類ルール ----
    if ($action === 'rule_add') {
        $pdo->prepare("INSERT INTO t_keiei_bunrui_rule
            (pk_rule_id,f_jigyobu,f_koujou,f_shihyo,f_shihyo_type,f_kansan_keisu,f_bumon_keyword,f_shohin_keyword,f_uriage_shiire,f_sort_order,f_active,f_created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,'有効',NOW())")
            ->execute([
                generate_uuid(), trim($_POST['f_jigyobu'] ?? ''), trim($_POST['f_koujou'] ?? ''), trim($_POST['f_shihyo'] ?? ''),
                $_POST['f_shihyo_type'] ?? '金額', (float)($_POST['f_kansan_keisu'] ?: 1),
                trim($_POST['f_bumon_keyword'] ?? '') ?: null, trim($_POST['f_shohin_keyword'] ?? '') ?: null,
                $_POST['f_uriage_shiire'] ?? '売上', (int)($_POST['f_sort_order'] ?? 0),
            ]);
        $msg = '分類ルールを追加しました。'; $tab = 'rule';
    } elseif ($action === 'rule_edit') {
        $pdo->prepare("UPDATE t_keiei_bunrui_rule SET
            f_jigyobu=?,f_koujou=?,f_shihyo=?,f_shihyo_type=?,f_kansan_keisu=?,f_bumon_keyword=?,f_shohin_keyword=?,f_uriage_shiire=?,f_sort_order=?,f_active=?,f_updated_at=NOW()
            WHERE pk_rule_id=?")
            ->execute([
                trim($_POST['f_jigyobu'] ?? ''), trim($_POST['f_koujou'] ?? ''), trim($_POST['f_shihyo'] ?? ''),
                $_POST['f_shihyo_type'] ?? '金額', (float)($_POST['f_kansan_keisu'] ?: 1),
                trim($_POST['f_bumon_keyword'] ?? '') ?: null, trim($_POST['f_shohin_keyword'] ?? '') ?: null,
                $_POST['f_uriage_shiire'] ?? '売上', (int)($_POST['f_sort_order'] ?? 0), $_POST['f_active'] ?? '有効',
                $_POST['rule_id'] ?? '',
            ]);
        $msg = '分類ルールを更新しました。'; $tab = 'rule';
    } elseif ($action === 'rule_delete') {
        $pdo->prepare("DELETE FROM t_keiei_bunrui_rule WHERE pk_rule_id=?")->execute([$_POST['rule_id'] ?? '']);
        $msg = '分類ルールを削除しました。'; $tab = 'rule';
    }

    if ($msg) {
        header('Location: keiei.php?tab=' . urlencode($tab) . '&csv_msg=' . urlencode($msg) . '&csv_msg_type=success');
        exit;
    }
}

/* ===================== 表示期間（12ヶ月分） ===================== */
$min_ym = $pdo->query("SELECT MIN(f_yearmonth) FROM t_keiei_denpyo")->fetchColumn();
$default_start = $min_ym ?: date('Y-m', strtotime('-11 months'));
$start_ym = $_GET['start_ym'] ?? $default_start;
if (!preg_match('/^\d{4}-\d{2}$/', $start_ym)) $start_ym = $default_start;

$months = [];
$ts = strtotime($start_ym . '-01');
for ($i = 0; $i < 12; $i++) {
    $months[] = date('Y-m', strtotime("+{$i} months", $ts));
}
$end_ym = end($months);
$show_compare = isset($_GET['compare']);

$target_ym = $_GET['target_ym'] ?? '';
if (!in_array($target_ym, $months, true)) {
    $target_ym = null; // 対象月未指定・範囲外なら、実績がある最新月を既定にする
}

/* ===================== 分類ルール ===================== */
$rules = $pdo->query("SELECT * FROM t_keiei_bunrui_rule WHERE f_active='有効' ORDER BY f_sort_order, f_jigyobu, f_koujou")->fetchAll();
$all_rules = $pdo->query("SELECT * FROM t_keiei_bunrui_rule ORDER BY f_sort_order, f_jigyobu, f_koujou")->fetchAll();

/* ===================== 実績集計（ルールごとにLIKEでキーワード集計） ===================== */
function keiei_rule_sum(PDO $pdo, array $rule, string $start_ym, string $end_ym): array {
    $where = ["f_yearmonth BETWEEN ? AND ?"];
    $bind  = [$start_ym, $end_ym];

    if (!empty($rule['f_bumon_keyword'])) {
        $kws = array_filter(array_map('trim', explode('|', $rule['f_bumon_keyword'])));
        if ($kws) {
            $ors = [];
            foreach ($kws as $kw) { $ors[] = "f_bumon LIKE ?"; $bind[] = '%' . $kw . '%'; }
            $where[] = '(' . implode(' OR ', $ors) . ')';
        }
    }
    if (!empty($rule['f_shohin_keyword'])) {
        $kws = array_filter(array_map('trim', explode('|', $rule['f_shohin_keyword'])));
        if ($kws) {
            $ors = [];
            foreach ($kws as $kw) { $ors[] = "CONCAT(COALESCE(f_shohin_shubetsu,''),' ',COALESCE(f_shohin,'')) LIKE ?"; $bind[] = '%' . $kw . '%'; }
            $where[] = '(' . implode(' OR ', $ors) . ')';
        }
    }
    if ($rule['f_uriage_shiire'] !== '両方') {
        $where[] = "f_uriage_shiire_kubun = ?";
        $bind[] = $rule['f_uriage_shiire'];
    }

    $valCol = $rule['f_shihyo_type'] === '数量' ? "SUM(f_suryo) * {$rule['f_kansan_keisu']}" : "SUM(f_kingaku)";
    $sql = "SELECT f_yearmonth ym, {$valCol} v FROM t_keiei_denpyo WHERE " . implode(' AND ', $where) . " GROUP BY f_yearmonth";
    $st = $pdo->prepare($sql);
    $st->execute($bind);
    $result = [];
    foreach ($st->fetchAll() as $r) { $result[$r['ym']] = (float)$r['v']; }
    return $result;
}

$jisseki = []; // rule_id => [ym => value]
foreach ($rules as $r) {
    $jisseki[$r['pk_rule_id']] = keiei_rule_sum($pdo, $r, $start_ym, $end_ym);
}

if ($target_ym === null) {
    foreach (array_reverse($months) as $m) {
        foreach ($jisseki as $vals) { if (isset($vals[$m])) { $target_ym = $m; break 2; } }
    }
    if ($target_ym === null) $target_ym = $end_ym;
}

/* ===================== 計画・前年（手入力） ===================== */
$keikaku = ['計画' => [], '前年' => []]; // kubun => rule_id => ym => value
if ($rules) {
    $ids = array_column($rules, 'pk_rule_id');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT * FROM t_keiei_keikaku WHERE fk_rule_id IN ({$ph}) AND f_yearmonth BETWEEN ? AND ?");
    $st->execute([...$ids, $start_ym, $end_ym]);
    foreach ($st->fetchAll() as $k) {
        $keikaku[$k['f_kubun']][$k['fk_rule_id']][$k['f_yearmonth']] = (float)$k['f_value'];
    }
}

/* 事業部→工場 でグループ化 */
$groups = [];
foreach ($rules as $r) {
    $groups[$r['f_jigyobu']][$r['f_koujou']][] = $r;
}

/* 取込履歴 */
$batches = $pdo->query("
    SELECT d.f_import_batch, MIN(d.f_created_at) AS imported_at, COUNT(*) AS cnt, SUM(d.f_kingaku) AS total
    FROM t_keiei_denpyo d
    WHERE d.f_import_batch IS NOT NULL
    GROUP BY d.f_import_batch ORDER BY imported_at DESC LIMIT 10
")->fetchAll();
$total_records = $pdo->query("SELECT COUNT(*) FROM t_keiei_denpyo")->fetchColumn();

echo html_header('経営数値集計');
echo nav_bar();
?>
<style>
.keiei-table{border-collapse:collapse;width:100%;font-size:12px}
.keiei-table th,.keiei-table td{border:1px solid #E0E8F0;padding:5px 7px;white-space:nowrap}
.keiei-table thead th{background:#1B3A6B;color:#fff;text-align:center;position:sticky;top:0}
.keiei-table td.num{text-align:right;font-variant-numeric:tabular-nums}
.keiei-table td.label{background:#F7F9FC;font-weight:600;color:#1B3A6B}
.keiei-table tr.jigyobu-row td{background:#E8F1FB;font-weight:700;color:#1B3A6B}
.keiei-table tr.jisseki-row td.num{color:#1a1a2e}
.keiei-table tr.keikaku-row td,.keiei-table tr.zennen-row td{color:#888;font-size:11px}
.keiei-table input.cell-input{width:64px;font-size:11px;padding:2px 4px;border:1px solid #C5D3E8;border-radius:3px;text-align:right}
.rate-badge{font-size:10px;padding:1px 5px;border-radius:8px;margin-left:4px}
.rate-good{background:#e6f4ea;color:#1a7a3a}
.rate-bad{background:#fdecea;color:#b71c1c}
.tabs{display:flex;gap:4px;margin-bottom:14px;flex-wrap:wrap}
</style>
<div class="container">
  <div class="page-title">経営数値集計（実績一覧表）</div>

  <?php if($msg): ?><div class="alert alert-<?= h($msg_type) ?>"><?= h($msg) ?></div><?php endif; ?>

  <div class="tabs">
    <a href="?tab=hyou" class="btn <?= $tab==='hyou'?'btn-primary':'btn-gray' ?> btn-sm">集計表（年間）</a>
    <a href="?tab=getsuji" class="btn <?= $tab==='getsuji'?'btn-primary':'btn-gray' ?> btn-sm">月次実績（単月・累計）</a>
    <a href="?tab=rule" class="btn <?= $tab==='rule'?'btn-primary':'btn-gray' ?> btn-sm">分類ルール（<?= count($all_rules) ?>）</a>
  </div>

  <?php if ($tab === 'rule'): ?>
  <!-- ===================== 分類ルールタブ ===================== -->
  <div class="card">
    <div class="card-header">分類ルールを追加</div>
    <div class="card-body">
      <p style="font-size:12px;color:#666;margin-bottom:10px">
        取り込んだ伝票データの「部門」「商品種別・商品」列をキーワードで判定し、事業部・工場（グループ）・指標行に振り分けます。<br>
        キーワードは部分一致。複数指定する場合は <code>|</code> で区切ってください（例：<code>ＲＰＦ（愛媛）|ＲＰＦ（愛媛製紙）</code>）。空欄は「条件なし（すべて該当）」です。
      </p>
      <form method="post">
        <input type="hidden" name="action" value="rule_add">
        <div class="sub-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px">
          <div class="form-group" style="margin:0"><label>事業部 *</label><input type="text" name="f_jigyobu" class="form-control" required placeholder="例：再資源化事業部"></div>
          <div class="form-group" style="margin:0"><label>工場・グループ</label><input type="text" name="f_koujou" class="form-control" placeholder="例：RPF工場（任意）"></div>
          <div class="form-group" style="margin:0"><label>指標行名 *</label><input type="text" name="f_shihyo" class="form-control" required placeholder="例：RPF売上"></div>
          <div class="form-group" style="margin:0"><label>集計対象</label>
            <select name="f_shihyo_type" class="form-control"><option value="金額">金額</option><option value="数量">数量</option></select>
          </div>
          <div class="form-group" style="margin:0"><label>数量換算係数<br><small>（kg→tなら0.001）</small></label><input type="number" step="0.0001" name="f_kansan_keisu" class="form-control" value="1"></div>
          <div class="form-group" style="margin:0"><label>部門キーワード</label><input type="text" name="f_bumon_keyword" class="form-control" placeholder="例：四国工場"></div>
          <div class="form-group" style="margin:0"><label>商品種別・商品キーワード</label><input type="text" name="f_shohin_keyword" class="form-control" placeholder="例：ＲＰＦ（四国）"></div>
          <div class="form-group" style="margin:0"><label>売上／仕入</label>
            <select name="f_uriage_shiire" class="form-control"><option value="売上">売上</option><option value="仕入">仕入</option><option value="両方">両方</option></select>
          </div>
          <div class="form-group" style="margin:0"><label>並び順</label><input type="number" name="f_sort_order" class="form-control" value="0"></div>
        </div>
        <div style="text-align:right;margin-top:10px"><button type="submit" class="btn btn-primary btn-sm">＋ 追加</button></div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">分類ルール一覧</div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap"><table>
        <thead><tr><th>事業部</th><th>工場</th><th>指標</th><th>対象</th><th>換算</th><th>部門KW</th><th>商品KW</th><th>売上/仕入</th><th>状態</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($all_rules as $r): ?>
        <tr style="<?= $r['f_active']==='無効'?'opacity:.5':'' ?>">
          <td><?= h($r['f_jigyobu']) ?></td>
          <td><?= h($r['f_koujou']) ?></td>
          <td style="font-weight:600"><?= h($r['f_shihyo']) ?></td>
          <td><?= h($r['f_shihyo_type']) ?></td>
          <td><?= h($r['f_kansan_keisu']) ?></td>
          <td style="font-size:11px;color:#666"><?= h($r['f_bumon_keyword'] ?: '―') ?></td>
          <td style="font-size:11px;color:#666"><?= h($r['f_shohin_keyword'] ?: '―') ?></td>
          <td><?= h($r['f_uriage_shiire']) ?></td>
          <td><?= h($r['f_active']) ?></td>
          <td style="white-space:nowrap">
            <button type="button" class="btn btn-blue btn-sm" onclick='openRuleEdit(<?= json_encode($r, JSON_UNESCAPED_UNICODE) ?>)'>編集</button>
            <form method="post" style="display:inline" onsubmit="return confirm('このルールを削除しますか？（保存済みの計画・前年データも削除されます）')">
              <input type="hidden" name="action" value="rule_delete"><input type="hidden" name="rule_id" value="<?= h($r['pk_rule_id']) ?>">
              <button type="submit" class="btn btn-danger btn-sm">削除</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$all_rules): ?><tr><td colspan="10" style="text-align:center;color:#999;padding:16px">ルールがありません</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>

  <!-- 編集モーダル -->
  <div id="ruleModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div class="card" style="width:520px;max-width:92vw;max-height:90vh;overflow-y:auto">
      <div class="card-header">分類ルールを編集</div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="action" value="rule_edit"><input type="hidden" name="rule_id" id="rm_id">
          <div class="form-group"><label>事業部</label><input type="text" name="f_jigyobu" id="rm_jigyobu" class="form-control" required></div>
          <div class="form-group"><label>工場・グループ</label><input type="text" name="f_koujou" id="rm_koujou" class="form-control"></div>
          <div class="form-group"><label>指標行名</label><input type="text" name="f_shihyo" id="rm_shihyo" class="form-control" required></div>
          <div class="form-group"><label>集計対象</label>
            <select name="f_shihyo_type" id="rm_type" class="form-control"><option value="金額">金額</option><option value="数量">数量</option></select>
          </div>
          <div class="form-group"><label>数量換算係数</label><input type="number" step="0.0001" name="f_kansan_keisu" id="rm_keisu" class="form-control"></div>
          <div class="form-group"><label>部門キーワード</label><input type="text" name="f_bumon_keyword" id="rm_bumon" class="form-control"></div>
          <div class="form-group"><label>商品種別・商品キーワード</label><input type="text" name="f_shohin_keyword" id="rm_shohin" class="form-control"></div>
          <div class="form-group"><label>売上／仕入</label>
            <select name="f_uriage_shiire" id="rm_us" class="form-control"><option value="売上">売上</option><option value="仕入">仕入</option><option value="両方">両方</option></select>
          </div>
          <div class="form-group"><label>並び順</label><input type="number" name="f_sort_order" id="rm_sort" class="form-control"></div>
          <div class="form-group"><label>状態</label>
            <select name="f_active" id="rm_active" class="form-control"><option value="有効">有効</option><option value="無効">無効</option></select>
          </div>
          <div style="display:flex;gap:8px;justify-content:flex-end">
            <button type="button" class="btn btn-gray" onclick="document.getElementById('ruleModal').style.display='none'">キャンセル</button>
            <button type="submit" class="btn btn-primary">保存</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <script>
  function openRuleEdit(r) {
    document.getElementById('rm_id').value = r.pk_rule_id;
    document.getElementById('rm_jigyobu').value = r.f_jigyobu;
    document.getElementById('rm_koujou').value = r.f_koujou;
    document.getElementById('rm_shihyo').value = r.f_shihyo;
    document.getElementById('rm_type').value = r.f_shihyo_type;
    document.getElementById('rm_keisu').value = r.f_kansan_keisu;
    document.getElementById('rm_bumon').value = r.f_bumon_keyword || '';
    document.getElementById('rm_shohin').value = r.f_shohin_keyword || '';
    document.getElementById('rm_us').value = r.f_uriage_shiire;
    document.getElementById('rm_sort').value = r.f_sort_order;
    document.getElementById('rm_active').value = r.f_active;
    document.getElementById('ruleModal').style.display = 'flex';
  }
  </script>

  <?php elseif ($tab === 'getsuji'): ?>
  <!-- ===================== 月次実績タブ（PDF2ページ目形式：単月＋累計の前年比較・計画比較） ===================== -->
  <div class="card">
    <div class="card-header">
      月次実績
      <form method="get" style="display:inline-flex;gap:8px;align-items:center">
        <input type="hidden" name="tab" value="getsuji">
        <label style="font-size:12px;color:#B8D4F0">累計の起点</label>
        <input type="month" name="start_ym" value="<?= h($start_ym) ?>" class="form-control" style="width:150px;font-size:12px;padding:4px 8px">
        <label style="font-size:12px;color:#B8D4F0">対象月</label>
        <select name="target_ym" class="form-control" style="font-size:12px;padding:4px 8px">
          <?php foreach ($months as $m): [$yy,$mm] = explode('-', $m); ?>
          <option value="<?= h($m) ?>" <?= $m===$target_ym?'selected':'' ?>><?= h($yy) ?>年<?= h($mm) ?>月</option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-gray btn-sm">表示</button>
      </form>
    </div>
    <div class="card-body" style="padding:0">
      <?php if (!$rules): ?>
      <div style="text-align:center;color:#999;padding:30px">分類ルールが登録されていません。「分類ルール」タブから追加してください。</div>
      <?php else: ?>
      <div class="table-wrap">
      <table class="keiei-table">
        <thead>
          <tr>
            <th rowspan="2" style="position:sticky;left:0;z-index:2;min-width:200px">事業部／工場／指標</th>
            <th colspan="5">月次（<?= h($target_ym) ?>）</th>
            <th colspan="6">累計（<?= h($start_ym) ?>〜<?= h($target_ym) ?>）</th>
          </tr>
          <tr>
            <th>前年</th><th>計画</th><th>本年実績</th><th>前年比較</th><th>計画比較</th>
            <th>前年</th><th>計画</th><th>本年</th><th>前年比較累計</th><th>計画比較累計</th><th>計画達成率</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($groups as $jigyobu => $byKoujou): ?>
          <tr class="jigyobu-row"><td colspan="12"><?= h($jigyobu) ?></td></tr>
          <?php foreach ($byKoujou as $koujou => $ruleList): ?>
            <?php if ($koujou !== ''): ?>
            <tr><td class="label" style="padding-left:18px"><?= h($koujou) ?></td><?php for($i=0;$i<11;$i++) echo '<td></td>'; ?></tr>
            <?php endif; ?>
            <?php foreach ($ruleList as $r): $rid = $r['pk_rule_id']; $dec = $r['f_shihyo_type']==='数量' ? 1 : 0;
              $tYear = $jisseki[$rid][$target_ym] ?? 0;
              $tZennen = $keikaku['前年'][$rid][$target_ym] ?? 0;
              $tKeikaku = $keikaku['計画'][$rid][$target_ym] ?? 0;
              $tZenhi = $tYear - $tZennen;
              $tKeikakuhi = $tYear - $tKeikaku;

              $ruiku_months = array_slice($months, 0, array_search($target_ym, $months, true) + 1);
              $rYear = 0; $rZennen = 0; $rKeikaku = 0;
              foreach ($ruiku_months as $m) {
                  $rYear    += $jisseki[$rid][$m] ?? 0;
                  $rZennen  += $keikaku['前年'][$rid][$m] ?? 0;
                  $rKeikaku += $keikaku['計画'][$rid][$m] ?? 0;
              }
              $rZenhi = $rYear - $rZennen;
              $rKeikakuhi = $rYear - $rKeikaku;
              $tassei = $rKeikaku > 0 ? round($rYear / $rKeikaku * 100) : null;
            ?>
            <tr class="jisseki-row">
              <td class="label" style="padding-left:<?= $koujou!==''?'32px':'18px' ?>;position:sticky;left:0;background:#F7F9FC"><?= h($r['f_shihyo']) ?></td>
              <td class="num"><?= number_format($tZennen, $dec) ?></td>
              <td class="num"><?= number_format($tKeikaku, $dec) ?></td>
              <td class="num" style="font-weight:700"><?= number_format($tYear, $dec) ?></td>
              <td class="num" style="color:<?= $tZenhi>=0?'#1a7a3a':'#b71c1c' ?>"><?= number_format($tZenhi, $dec) ?></td>
              <td class="num" style="color:<?= $tKeikakuhi>=0?'#1a7a3a':'#b71c1c' ?>"><?= number_format($tKeikakuhi, $dec) ?></td>
              <td class="num"><?= number_format($rZennen, $dec) ?></td>
              <td class="num"><?= number_format($rKeikaku, $dec) ?></td>
              <td class="num" style="font-weight:700"><?= number_format($rYear, $dec) ?></td>
              <td class="num" style="color:<?= $rZenhi>=0?'#1a7a3a':'#b71c1c' ?>"><?= number_format($rZenhi, $dec) ?></td>
              <td class="num" style="color:<?= $rKeikakuhi>=0?'#1a7a3a':'#b71c1c' ?>"><?= number_format($rKeikakuhi, $dec) ?></td>
              <td class="num"><?= $tassei!==null ? '<span class="rate-badge '.($tassei>=100?'rate-good':'rate-bad').'">'.$tassei.'%</span>' : '―' ?></td>
            </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php else: ?>
  <!-- ===================== 集計表タブ ===================== -->

  <?php if($total_records == 0): ?>
  <div class="card"><div class="card-body" style="text-align:center;color:#999;padding:30px">
    伝票データがまだ取り込まれていません。下記からCSVを取り込んでください。
  </div></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">伝票データ（CSV）を取り込む</div>
    <div class="card-body">
      <form method="post" action="csv_import_keiei.php" enctype="multipart/form-data">
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <input type="file" name="csv_file" class="form-control" style="flex:1;min-width:200px" accept=".csv" required>
          <button type="submit" class="btn btn-primary btn-sm">📤 取込む</button>
        </div>
        <div style="font-size:11px;color:#888;margin-top:6px">
          販売管理システムから出力した伝票データCSVをそのまま取り込めます（列名で自動判定。列順が変わっても対応）。<br>
          振り分け（どの事業部・指標に集計するか）は下の「分類ルール」タブで管理します。
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      実績一覧表
      <form method="get" style="display:inline-flex;gap:8px;align-items:center">
        <input type="hidden" name="tab" value="hyou">
        <label style="font-size:12px;color:#B8D4F0">表示開始月</label>
        <input type="month" name="start_ym" value="<?= h($start_ym) ?>" class="form-control" style="width:150px;font-size:12px;padding:4px 8px">
        <label style="font-size:12px;display:flex;align-items:center;gap:4px"><input type="checkbox" name="compare" value="1" <?= $show_compare?'checked':'' ?> style="width:auto">計画・前年も表示</label>
        <button type="submit" class="btn btn-gray btn-sm">表示</button>
      </form>
    </div>
    <div class="card-body" style="padding:0">
      <?php if (!$rules): ?>
      <div style="text-align:center;color:#999;padding:30px">分類ルールが登録されていません。「分類ルール」タブから追加してください。</div>
      <?php else: ?>
      <?php $extra_forms = []; ?>
      <div class="table-wrap">
      <table class="keiei-table">
        <thead>
          <tr>
            <th style="position:sticky;left:0;z-index:2;min-width:200px">事業部／工場／指標</th>
            <?php foreach ($months as $m): [$yy,$mm] = explode('-', $m); ?>
            <th><?= h($yy) ?>/<?= h($mm) ?></th>
            <?php endforeach; ?>
            <th>計</th>
            <?php if ($show_compare): ?><th>達成率</th><th>前年比</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($groups as $jigyobu => $byKoujou): ?>
          <tr class="jigyobu-row"><td colspan="<?= count($months) + 1 + ($show_compare?2:0) ?>"><?= h($jigyobu) ?></td></tr>
          <?php foreach ($byKoujou as $koujou => $ruleList): ?>
            <?php if ($koujou !== ''): ?>
            <tr><td class="label" style="padding-left:18px"><?= h($koujou) ?></td><?php for($i=0;$i<count($months)+($show_compare?2:0);$i++) echo '<td></td>'; ?></tr>
            <?php endif; ?>
            <?php foreach ($ruleList as $r): $rid = $r['pk_rule_id'];
              $row = $jisseki[$rid] ?? [];
              $sum = array_sum($row);
              $kSum = 0; $zSum = 0;
              foreach ($months as $m) { $kSum += $keikaku['計画'][$rid][$m] ?? 0; $zSum += $keikaku['前年'][$rid][$m] ?? 0; }
              $tassei = $kSum > 0 ? round($sum / $kSum * 100) : null;
              $zenhi  = $zSum > 0 ? round($sum / $zSum * 100) : null;
            ?>
            <tr class="jisseki-row">
              <td class="label" style="padding-left:<?= $koujou!==''?'32px':'18px' ?>;position:sticky;left:0;background:#F7F9FC"><?= h($r['f_shihyo']) ?>
                <span style="font-size:10px;color:#999;font-weight:400">（実績）</span>
              </td>
              <?php foreach ($months as $m): ?>
              <td class="num"><?= isset($row[$m]) ? number_format($row[$m], $r['f_shihyo_type']==='数量'?1:0) : '―' ?></td>
              <?php endforeach; ?>
              <td class="num" style="font-weight:700"><?= number_format($sum, $r['f_shihyo_type']==='数量'?1:0) ?></td>
              <?php if ($show_compare): ?>
              <td class="num"><?= $tassei!==null ? '<span class="rate-badge '.($tassei>=100?'rate-good':'rate-bad').'">'.$tassei.'%</span>' : '―' ?></td>
              <td class="num"><?= $zenhi!==null ? '<span class="rate-badge '.($zenhi>=100?'rate-good':'rate-bad').'">'.$zenhi.'%</span>' : '―' ?></td>
              <?php endif; ?>
            </tr>
            <?php if ($show_compare):
              $fid_k = 'fk_' . substr(md5($rid), 0, 10);
              $fid_z = 'fz_' . substr(md5($rid), 0, 10);
              $extra_forms[] = ['id' => $fid_k, 'kubun' => '計画', 'rule_id' => $rid];
              $extra_forms[] = ['id' => $fid_z, 'kubun' => '前年', 'rule_id' => $rid];
            ?>
            <tr class="keikaku-row">
              <td class="label" style="padding-left:32px;position:sticky;left:0;background:#fff">計画</td>
              <?php foreach ($months as $m): ?>
              <td><input type="text" inputmode="decimal" class="cell-input" form="<?= $fid_k ?>" name="values[<?= h($m) ?>]" value="<?= isset($keikaku['計画'][$rid][$m]) ? (int)$keikaku['計画'][$rid][$m] : '' ?>"></td>
              <?php endforeach; ?>
              <td class="num"><?= $kSum ? number_format($kSum) : '―' ?>
                <button type="submit" form="<?= $fid_k ?>" class="btn btn-gray btn-sm" style="padding:2px 6px;font-size:10px">保存</button>
              </td>
              <td></td><td></td>
            </tr>
            <tr class="zennen-row">
              <td class="label" style="padding-left:32px;position:sticky;left:0;background:#fff">前年</td>
              <?php foreach ($months as $m): ?>
              <td><input type="text" inputmode="decimal" class="cell-input" form="<?= $fid_z ?>" name="values[<?= h($m) ?>]" value="<?= isset($keikaku['前年'][$rid][$m]) ? (int)$keikaku['前年'][$rid][$m] : '' ?>"></td>
              <?php endforeach; ?>
              <td class="num"><?= $zSum ? number_format($zSum) : '―' ?>
                <button type="submit" form="<?= $fid_z ?>" class="btn btn-gray btn-sm" style="padding:2px 6px;font-size:10px">保存</button>
              </td>
              <td></td><td></td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
          <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php foreach ($extra_forms as $f): ?>
      <form id="<?= $f['id'] ?>" method="post" style="display:none">
        <input type="hidden" name="action" value="save_keikaku">
        <input type="hidden" name="kubun" value="<?= h($f['kubun']) ?>">
        <input type="hidden" name="rule_id" value="<?= h($f['rule_id']) ?>">
      </form>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header">取込履歴（直近10件）</div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap"><table>
        <thead><tr><th>取込日時</th><th style="text-align:right">件数</th><th style="text-align:right">金額合計</th><th style="text-align:center">操作</th></tr></thead>
        <tbody>
        <?php if(empty($batches)): ?>
        <tr><td colspan="4" style="text-align:center;padding:16px;color:#999">取込履歴がありません</td></tr>
        <?php endif; ?>
        <?php foreach($batches as $b): ?>
        <tr>
          <td style="white-space:nowrap"><?= h(date('Y/m/d H:i', strtotime($b['imported_at']))) ?></td>
          <td style="text-align:right"><?= (int)$b['cnt'] ?>件</td>
          <td style="text-align:right"><?= number_format($b['total']) ?>円</td>
          <td style="text-align:center">
            <form method="post" style="display:inline" onsubmit="return confirm('このバッチの伝票データを全て削除しますか？（取り消せません）')">
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
  <?php endif; ?>
</div>
<?= html_footer() ?>
