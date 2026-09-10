<?php
require_once 'db.php';
require_once 'common.php';

$pdo = get_db();

// 取引先一覧（訪問履歴があるもの）
$where = ['1=1'];
$params = [];

[$cond, $ps] = visible_tantosha_where($pdo, 'h.fk_tantosha_id');
if ($cond) { $where[] = $cond; array_push($params, ...$ps); }

$selected_id = $_GET['torihikisaki_id'] ?? '';
$selected_name = $_GET['torihikisaki_name'] ?? '';

// 訪問先ごとの集計
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(tr.pk_torihikisaki_id, '') AS torihikisaki_id,
        COALESCE(tr.f_torihikisaki_name, m.f_homonsakimei) AS torihikisaki_name,
        COUNT(m.pk_meisai_id) AS homon_count,
        MAX(h.f_date) AS last_visit,
        SUM(CASE WHEN m.f_juchu_mikomikubun='受注' THEN 1 ELSE 0 END) AS juchu_count,
        SUM(COALESCE(m.f_kaishu_ryo * m.f_tanka, 0)) AS total_kin
    FROM t_nippo_meisai m
    JOIN t_nippo_header h ON m.fk_nippo_id = h.pk_nippo_id
    LEFT JOIN t_torihikisaki tr ON m.fk_torihikisaki_id = tr.pk_torihikisaki_id
    WHERE " . implode(' AND ', $where) . "
    GROUP BY torihikisaki_id, torihikisaki_name
    ORDER BY last_visit DESC
");
$stmt->execute($params);
$torihikisaki_list = $stmt->fetchAll();

// 選択中の訪問先の履歴
$histories = [];
if ($selected_name) {
    $hwhere = ['m.f_homonsakimei = ? OR tr.f_torihikisaki_name = ?'];
    $hparams = [$selected_name, $selected_name];
    [$hcond, $hps] = visible_tantosha_where($pdo, 'h.fk_tantosha_id');
    if ($hcond) { $hwhere[] = $hcond; array_push($hparams, ...$hps); }
    $stmt = $pdo->prepare("
        SELECT m.*, h.f_date, h.pk_nippo_id, h.f_kakunin_status,
               t.f_tantosha_name, tr.f_torihikisaki_name
        FROM t_nippo_meisai m
        JOIN t_nippo_header h ON m.fk_nippo_id = h.pk_nippo_id
        JOIN t_tantosha t ON h.fk_tantosha_id = t.pk_tantosha_id
        LEFT JOIN t_torihikisaki tr ON m.fk_torihikisaki_id = tr.pk_torihikisaki_id
        WHERE " . implode(' AND ', $hwhere) . "
        ORDER BY h.f_date DESC
    ");
    $stmt->execute($hparams);
    $histories = $stmt->fetchAll();
}

echo html_header('訪問先別履歴');
echo nav_bar();
?>
<div class="container">
  <div class="page-title">訪問先別　履歴</div>

  <div style="display:grid;grid-template-columns:280px 1fr;gap:16px">

    <!-- 左：訪問先リスト -->
    <div>
      <div class="card" style="margin:0">
        <div class="card-header">訪問先一覧（<?= count($torihikisaki_list) ?>件）</div>
        <div style="overflow-y:auto;max-height:70vh">
          <?php foreach($torihikisaki_list as $tr): ?>
          <?php $active = ($selected_name === $tr['torihikisaki_name']); ?>
          <a href="?torihikisaki_id=<?= urlencode($tr['torihikisaki_id']) ?>&torihikisaki_name=<?= urlencode($tr['torihikisaki_name']) ?>"
             style="display:block;padding:12px 16px;border-bottom:1px solid #e8eef5;text-decoration:none;background:<?= $active?'#EEF3FA':'#fff' ?>;border-left:<?= $active?'4px solid #2E75B6':'4px solid transparent' ?>">
            <div style="font-weight:<?= $active?'700':'500' ?>;color:#1B3A6B;font-size:13px;margin-bottom:4px"><?= h($tr['torihikisaki_name']) ?></div>
            <div style="display:flex;gap:10px;font-size:11px;color:#888">
              <span>訪問<?= (int)$tr['homon_count'] ?>回</span>
              <span>受注<?= (int)$tr['juchu_count'] ?>件</span>
              <span>最終：<?= h(date('m/d', strtotime($tr['last_visit']))) ?></span>
            </div>
          </a>
          <?php endforeach; ?>
          <?php if(empty($torihikisaki_list)): ?>
          <div style="padding:20px;text-align:center;color:#999">履歴がありません</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- 右：履歴詳細 -->
    <div>
      <?php if($selected_name): ?>

      <!-- サマリー -->
      <?php
      $total_homon = count($histories);
      $total_juchu = count(array_filter($histories, fn($h) => $h['f_juchu_mikomikubun'] === '受注'));
      $total_kin   = array_sum(array_map(fn($h) => ($h['f_kaishu_ryo'] ?? 0) * ($h['f_tanka'] ?? 0), $histories));
      $last_action = $histories[0]['f_jikai_action'] ?? '';
      $last_date   = $histories[0]['f_date'] ?? '';
      ?>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px">
        <div class="card" style="margin:0">
          <div class="card-body" style="text-align:center;padding:14px 10px">
            <div style="font-size:11px;color:#888;margin-bottom:4px">総訪問回数</div>
            <div style="font-size:28px;font-weight:700;color:#1B3A6B"><?= $total_homon ?></div>
            <div style="font-size:11px;color:#888">回</div>
          </div>
        </div>
        <div class="card" style="margin:0">
          <div class="card-body" style="text-align:center;padding:14px 10px">
            <div style="font-size:11px;color:#888;margin-bottom:4px">受注件数</div>
            <div style="font-size:28px;font-weight:700;color:#2e7d32"><?= $total_juchu ?></div>
            <div style="font-size:11px;color:#888">件</div>
          </div>
        </div>
        <div class="card" style="margin:0">
          <div class="card-body" style="text-align:center;padding:14px 10px">
            <div style="font-size:11px;color:#888;margin-bottom:4px">累計金額</div>
            <div style="font-size:20px;font-weight:700;color:#2E75B6"><?= number_format($total_kin) ?></div>
            <div style="font-size:11px;color:#888">円</div>
          </div>
        </div>
      </div>

      <?php if($last_action): ?>
      <div style="background:#fff8e1;border:1px solid #f0d080;border-radius:6px;padding:12px 16px;margin-bottom:14px">
        <div style="font-size:11px;color:#8a6200;font-weight:600;margin-bottom:4px">直近の次回アクション（<?= h(date('Y/m/d', strtotime($last_date))) ?>）</div>
        <div style="font-size:14px;font-weight:600;color:#1a1a2e"><?= h($last_action) ?></div>
      </div>
      <?php endif; ?>

      <!-- 履歴一覧 -->
      <div class="card" style="margin:0">
        <div class="card-header">
          <?= h($selected_name) ?>　訪問履歴
          <span style="font-size:12px;font-weight:400"><?= $total_homon ?>回</span>
        </div>
        <div class="card-body" style="padding:0">
          <?php foreach($histories as $i => $h): ?>
          <div style="padding:14px 16px;border-bottom:1px solid #e8eef5;<?= $i%2===0?'background:#fff':'background:#f7f9fc' ?>">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:6px">
              <div>
                <span style="font-size:15px;font-weight:700;color:#1B3A6B"><?= h(date('Y/m/d', strtotime($h['f_date']))) ?></span>
                <span style="font-size:12px;color:#888;margin-left:8px"><?= h($h['f_tantosha_name']) ?></span>
              </div>
              <div style="display:flex;gap:6px;align-items:center">
                <?= juchu_badge($h['f_juchu_mikomikubun']) ?>
                <?= status_badge($h['f_kakunin_status']) ?>
                <a href="detail.php?id=<?= h($h['pk_nippo_id']) ?>" class="btn btn-blue btn-sm">詳細</a>
              </div>
            </div>
            <?php if($h['f_taiou_naiyo']): ?>
            <div style="background:#f0f4fa;border-left:3px solid #2E75B6;padding:6px 10px;font-size:13px;border-radius:0 4px 4px 0;margin-bottom:6px"><?= h($h['f_taiou_naiyo']) ?></div>
            <?php endif; ?>
            <div style="display:flex;gap:16px;font-size:12px;color:#666;flex-wrap:wrap">
              <?php if($h['f_jikai_action']): ?>
              <span>→ <?= h($h['f_jikai_action']) ?></span>
              <?php endif; ?>
              <?php if($h['f_jikai_yoteibi']): ?>
              <span>📅 <?= h(date('Y/m/d', strtotime($h['f_jikai_yoteibi']))) ?></span>
              <?php endif; ?>
              <?php if($h['f_kaishu_ryo'] && $h['f_tanka']): ?>
              <span>💰 <?= number_format($h['f_kaishu_ryo'] * $h['f_tanka']) ?>円</span>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <?php else: ?>
      <div class="card" style="margin:0">
        <div class="card-body" style="text-align:center;color:#999;padding:60px 20px">
          <div style="font-size:40px;margin-bottom:12px">👈</div>
          <div>左の訪問先を選択してください</div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
</script>
<?= html_footer() ?>
