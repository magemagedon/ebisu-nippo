<?php
require_once 'db.php';
require_once 'common.php';

$pdo = get_db();

// 今月の期間
$month_start = date('Y-m-01');
$month_end   = date('Y-m-t');

// 担当者別訪問件数（今月）
$stmt = $pdo->prepare("
    SELECT t.f_tantosha_name, COUNT(m.pk_meisai_id) AS homon_count,
           SUM(CASE WHEN m.f_juchu_mikomikubun='受注' THEN 1 ELSE 0 END) AS juchu_count
    FROM t_tantosha t
    LEFT JOIN t_nippo_header h ON t.pk_tantosha_id = h.fk_tantosha_id
        AND h.f_date BETWEEN ? AND ?
    LEFT JOIN t_nippo_meisai m ON h.pk_nippo_id = m.fk_nippo_id
    WHERE t.f_zaiseki_flag = '有効'
    GROUP BY t.pk_tantosha_id, t.f_tantosha_name
    ORDER BY homon_count DESC
");
$stmt->execute([$month_start, $month_end]);
$tanto_stats = $stmt->fetchAll();

// 確認ステータス別件数
$stmt = $pdo->query("
    SELECT f_kakunin_status, COUNT(*) AS cnt
    FROM t_nippo_header
    GROUP BY f_kakunin_status
");
$status_stats = [];
foreach($stmt->fetchAll() as $r) {
    $status_stats[$r['f_kakunin_status']] = $r['cnt'];
}

// 受注見込み区分別件数（今月）
$stmt = $pdo->prepare("
    SELECT m.f_juchu_mikomikubun, COUNT(*) AS cnt
    FROM t_nippo_meisai m
    JOIN t_nippo_header h ON m.fk_nippo_id = h.pk_nippo_id
    WHERE h.f_date BETWEEN ? AND ?
    GROUP BY m.f_juchu_mikomikubun
    ORDER BY cnt DESC
");
$stmt->execute([$month_start, $month_end]);
$juchu_stats = $stmt->fetchAll();

// 最近の日報（未確認）
$mk_where = ["h.f_kakunin_status = '未確認'"];
$mk_params = [];
[$mk_cond, $mk_ps] = visible_tantosha_where($pdo, 'h.fk_tantosha_id');
if ($mk_cond) { $mk_where[] = $mk_cond; array_push($mk_params, ...$mk_ps); }
$stmt = $pdo->prepare("
    SELECT h.*, t.f_tantosha_name,
        (SELECT COUNT(*) FROM t_nippo_meisai m WHERE m.fk_nippo_id = h.pk_nippo_id) AS meisai_count
    FROM t_nippo_header h
    LEFT JOIN t_tantosha t ON h.fk_tantosha_id = t.pk_tantosha_id
    WHERE " . implode(' AND ', $mk_where) . "
    ORDER BY h.f_date DESC
    LIMIT 5
");
$stmt->execute($mk_params);
$mikakunin = $stmt->fetchAll();

// 今月の合計訪問件数
$stmt = $pdo->prepare("
    SELECT COUNT(m.pk_meisai_id) AS total
    FROM t_nippo_meisai m
    JOIN t_nippo_header h ON m.fk_nippo_id = h.pk_nippo_id
    WHERE h.f_date BETWEEN ? AND ?
");
$stmt->execute([$month_start, $month_end]);
$total_homon = $stmt->fetchColumn();

// 今月の日報件数
$stmt = $pdo->prepare("SELECT COUNT(*) FROM t_nippo_header WHERE f_date BETWEEN ? AND ?");
$stmt->execute([$month_start, $month_end]);
$total_nippo = $stmt->fetchColumn();

// 今月の受注件数
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM t_nippo_meisai m
    JOIN t_nippo_header h ON m.fk_nippo_id = h.pk_nippo_id
    WHERE h.f_date BETWEEN ? AND ? AND m.f_juchu_mikomikubun = '受注'
");
$stmt->execute([$month_start, $month_end]);
$total_juchu = $stmt->fetchColumn();

echo html_header('ダッシュボード');
echo nav_bar();
?>
<div class="container">
<?php
$today = date("Y-m-d");
$oshirase_rows = $pdo->query("SELECT o.*, t.f_tantosha_name FROM t_oshirase o JOIN t_tantosha t ON o.f_author_id = t.pk_tantosha_id WHERE o.f_publish_date <= '{$today}' AND (o.f_expire_date IS NULL OR o.f_expire_date >= '{$today}') ORDER BY o.f_important DESC, o.f_publish_date DESC LIMIT 3")->fetchAll();
?>
<?php if(!empty($oshirase_rows)): ?>
<div class="card" style="margin-bottom:16px;border:1px solid #c5d3e8">
  <div class="card-header" style="background:#E65100">お知らせ <span style="font-size:12px;font-weight:400"><?= count($oshirase_rows) ?>件</span></div>
  <div class="card-body" style="padding:0">
  <?php foreach($oshirase_rows as $o): ?>
  <div style="padding:10px 16px;border-bottom:1px solid #e8eef5">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
      <?php if($o["f_important"]): ?><span style="background:#fdecea;color:#c62828;border:1px solid #f5a8a8;border-radius:4px;padding:1px 6px;font-size:10px;font-weight:700">重要</span><?php endif; ?>
      <span style="font-weight:600;font-size:13px"><?= h($o["f_title"]) ?></span>
      <span style="font-size:11px;color:#888;margin-left:auto"><?= h(date("Y/m/d", strtotime($o["f_publish_date"]))) ?></span>
    </div>
    <div style="font-size:12px;color:#555"><?= h(mb_substr($o["f_body"],0,60)) ?><?= mb_strlen($o["f_body"])>60?"…":"" ?> <a href="oshirase.php" style="font-size:11px">詳細</a></div>
  </div>
  <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
  <div class="page-title">簡易ダッシュボード　<span style="font-size:13px;font-weight:400;color:#666"><?= date('Y年m月') ?></span></div>

  <!-- サマリーカード -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px">
    <div class="card" style="margin:0">
      <div class="card-body" style="text-align:center;padding:16px 10px">
        <div style="font-size:11px;color:#888;margin-bottom:6px">今月の日報件数</div>
        <div style="font-size:32px;font-weight:700;color:#1B3A6B"><?= (int)$total_nippo ?></div>
        <div style="font-size:11px;color:#888">件</div>
      </div>
    </div>
    <div class="card" style="margin:0">
      <div class="card-body" style="text-align:center;padding:16px 10px">
        <div style="font-size:11px;color:#888;margin-bottom:6px">今月の訪問件数</div>
        <div style="font-size:32px;font-weight:700;color:#2E75B6"><?= (int)$total_homon ?></div>
        <div style="font-size:11px;color:#888">件</div>
      </div>
    </div>
    <div class="card" style="margin:0">
      <div class="card-body" style="text-align:center;padding:16px 10px">
        <div style="font-size:11px;color:#888;margin-bottom:6px">今月の受注件数</div>
        <div style="font-size:32px;font-weight:700;color:#2e7d32"><?= (int)$total_juchu ?></div>
        <div style="font-size:11px;color:#888">件</div>
      </div>
    </div>
  </div>

  <!-- 確認ステータス -->
  <div class="card">
    <div class="card-header">確認ステータス（全期間）</div>
    <div class="card-body">
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px">
        <?php
        $status_list = [
          '未確認'   => ['badge-warning', '#8a6200'],
          '確認済'   => ['badge-success', '#1a7a3a'],
          '差し戻し' => ['badge-danger',  '#b71c1c'],
        ];
        foreach($status_list as $s => [$cls, $color]):
          $cnt = $status_stats[$s] ?? 0;
        ?>
        <div style="text-align:center;padding:12px 8px;background:#f7f9fc;border-radius:6px;border:1px solid #e0e8f0">
          <?= status_badge($s) ?>
          <div style="font-size:24px;font-weight:700;color:<?= $color ?>;margin-top:8px"><?= (int)$cnt ?></div>
          <div style="font-size:11px;color:#888">件</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- 担当者別今月実績 -->
  <div class="card">
    <div class="card-header">担当者別　今月実績</div>
    <div class="card-body" style="padding:0">
      <table style="min-width:auto">
        <thead>
          <tr>
            <th>担当者</th>
            <th style="text-align:center">訪問件数</th>
            <th style="text-align:center">受注件数</th>
            <th style="text-align:center">受注率</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($tanto_stats as $s): ?>
        <?php $rate = $s['homon_count'] > 0 ? round($s['juchu_count'] / $s['homon_count'] * 100) : 0; ?>
        <tr>
          <td style="font-weight:600"><?= h($s['f_tantosha_name']) ?></td>
          <td style="text-align:center">
            <span style="font-size:18px;font-weight:700;color:#2E75B6"><?= (int)$s['homon_count'] ?></span>
            <span style="font-size:11px;color:#888"> 件</span>
          </td>
          <td style="text-align:center">
            <span style="font-size:18px;font-weight:700;color:#2e7d32"><?= (int)$s['juchu_count'] ?></span>
            <span style="font-size:11px;color:#888"> 件</span>
          </td>
          <td style="text-align:center">
            <div style="display:flex;align-items:center;gap:6px">
              <div style="flex:1;background:#e0e8f0;border-radius:3px;height:6px;overflow:hidden">
                <div style="background:#2e7d32;height:100%;width:<?= $rate ?>%;border-radius:3px"></div>
              </div>
              <span style="font-size:12px;font-weight:600;color:#2e7d32;white-space:nowrap"><?= $rate ?>%</span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 受注見込み区分（今月） -->
  <?php if(!empty($juchu_stats)): ?>
  <div class="card">
    <div class="card-header">受注見込み区分　今月内訳</div>
    <div class="card-body">
      <?php
      $total_m = array_sum(array_column($juchu_stats, 'cnt'));
      $colors = ['受注'=>'#2e7d32','見込み'=>'#1565c0','継続フォロー'=>'#e65100','失注'=>'#b71c1c','情報収集'=>'#555'];
      foreach($juchu_stats as $j):
        $pct = $total_m > 0 ? round($j['cnt'] / $total_m * 100) : 0;
        $col = $colors[$j['f_juchu_mikomikubun']] ?? '#888';
      ?>
      <div style="margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:13px">
          <span style="font-weight:500"><?= h($j['f_juchu_mikomikubun']) ?></span>
          <span style="color:#666"><?= (int)$j['cnt'] ?>件（<?= $pct ?>%）</span>
        </div>
        <div style="background:#e0e8f0;border-radius:4px;height:10px;overflow:hidden">
          <div style="background:<?= $col ?>;height:100%;width:<?= $pct ?>%;border-radius:4px;transition:width .3s"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- 未確認日報 -->
  <?php if(($_SESSION['kengen'] === '管理者' || $_SESSION['kengen'] === '部門管理者') && !empty($mikakunin)): ?>
  <div class="card">
    <div class="card-header" style="background:#e65100">
      未確認日報
      <span style="font-size:12px;font-weight:400"><?= count($mikakunin) ?> 件</span>
    </div>
    <div class="card-body" style="padding:0">
      <table style="min-width:auto">
        <thead>
          <tr>
            <th>日付</th>
            <th>担当者</th>
            <th style="text-align:center">訪問</th>
            <th style="text-align:center">確認</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($mikakunin as $r): ?>
        <tr>
          <td style="font-weight:600;white-space:nowrap"><?= h(date('Y/m/d', strtotime($r['f_date']))) ?></td>
          <td><?= h($r['f_tantosha_name']) ?></td>
          <td style="text-align:center"><?= (int)$r['meisai_count'] ?>件</td>
          <td style="text-align:center">
            <a href="detail.php?id=<?= h($r['pk_nippo_id']) ?>" class="btn btn-blue btn-sm">確認</a>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <div style="margin-bottom:20px;text-align:center">
    <a href="index.php" class="btn btn-gray">← 日報一覧へ</a>
  </div>
</div>
<?= html_footer() ?>
