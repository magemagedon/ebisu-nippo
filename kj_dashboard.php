<?php
require_once 'kj_common.php';
$pdo = get_db();

$factories = kj_factories($pdo);
$factory_count = count($factories);
$today = date('Y-m-d');
$week_ago = date('Y-m-d', strtotime('-6 days'));
$month_start = date('Y-m-01');
$month_end   = date('Y-m-t');

// ---- 点検実施率（直近7日） ----
$stmt = $pdo->prepare("SELECT fk_factory_id, COUNT(DISTINCT f_check_date) AS days FROM t_nichiji_check WHERE f_check_date BETWEEN ? AND ? GROUP BY fk_factory_id");
$stmt->execute([$week_ago, $today]);
$check_days_by_factory = [];
foreach ($stmt->fetchAll() as $r) $check_days_by_factory[$r['fk_factory_id']] = (int)$r['days'];
$total_check_days = array_sum($check_days_by_factory);
$check_rate = $factory_count > 0 ? round($total_check_days / ($factory_count * 7) * 100) : 0;

// ---- 未対応の不具合 ----
$stmt = $pdo->query("
    SELECT g.*, f.f_factory_name, DATEDIFF(g.f_kigen_date, CURDATE()) AS days_left
    FROM t_fugu g JOIN t_factory f ON g.fk_factory_id=f.pk_factory_id
    WHERE g.f_status != '完了' ORDER BY g.f_kigen_date IS NULL, g.f_kigen_date ASC
");
$open_issues = $stmt->fetchAll();
$overdue_issues = array_filter($open_issues, fn($g) => $g['days_left'] !== null && (int)$g['days_left'] < 0);

// ---- 保守期日超過 ----
$stmt = $pdo->query("
    SELECT s.*, f.f_factory_name, p.f_setsubi_name AS parent_name
    FROM t_setsubi s
    JOIN t_factory f ON s.fk_factory_id=f.pk_factory_id
    LEFT JOIN t_setsubi p ON s.fk_parent_setsubi_id = p.pk_setsubi_id
    WHERE s.f_active='有効' AND s.f_koukan_shuki_days IS NOT NULL
");
$maint_overdue = [];
foreach ($stmt->fetchAll() as $s) {
    $next = kj_next_koukan_date($pdo, $s);
    if ($next && $next < $today) {
        $s['next_date'] = $next;
        $s['days_over'] = (int)floor((strtotime($today) - strtotime($next)) / 86400);
        $maint_overdue[] = $s;
    }
}
usort($maint_overdue, fn($a,$b) => $b['days_over'] <=> $a['days_over']);

// ---- パトロール実施状況（今月） ----
$stmt = $pdo->prepare("
    SELECT f.pk_factory_id, f.f_factory_name,
      SUM(CASE WHEN p.f_status='実施済' THEN 1 ELSE 0 END) AS jisshi,
      SUM(CASE WHEN p.f_status='計画' THEN 1 ELSE 0 END) AS keikaku,
      MAX(p.f_patrol_date) AS last_date
    FROM t_factory f
    LEFT JOIN t_patrol p ON p.fk_factory_id = f.pk_factory_id AND p.f_patrol_date BETWEEN ? AND ?
    WHERE f.f_active='有効'
    GROUP BY f.pk_factory_id, f.f_factory_name
    ORDER BY f.f_sort_order, f.f_factory_name
");
$stmt->execute([$month_start, $month_end]);
$patrol_stats = $stmt->fetchAll();

echo html_header('工場メンテナンス管理ダッシュボード');
echo nav_bar();
?>
<div class="container">
  <div class="page-title">工場メンテナンス管理　｜　経営ダッシュボード</div>
  <?= kj_subnav('kj_dashboard.php') ?>

  <!-- KPIサマリー -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px">
    <div class="card" style="margin:0"><div class="card-body" style="text-align:center;padding:16px 10px">
      <div style="font-size:11px;color:#888;margin-bottom:6px">点検実施率（直近7日）</div>
      <div style="font-size:32px;font-weight:700;color:<?= $check_rate>=80?'#2e7d32':($check_rate>=50?'#e65100':'#c62828') ?>"><?= $check_rate ?>%</div>
      <div style="font-size:11px;color:#888"><?= $total_check_days ?> / <?= $factory_count*7 ?> 工場日</div>
    </div></div>
    <div class="card" style="margin:0"><div class="card-body" style="text-align:center;padding:16px 10px">
      <div style="font-size:11px;color:#888;margin-bottom:6px">未対応の不具合</div>
      <div style="font-size:32px;font-weight:700;color:<?= count($open_issues)>0?'#c62828':'#2e7d32' ?>"><?= count($open_issues) ?></div>
      <div style="font-size:11px;color:#888">うち期限超過 <?= count($overdue_issues) ?> 件</div>
    </div></div>
    <div class="card" style="margin:0"><div class="card-body" style="text-align:center;padding:16px 10px">
      <div style="font-size:11px;color:#888;margin-bottom:6px">保守・交換　期日超過</div>
      <div style="font-size:32px;font-weight:700;color:<?= count($maint_overdue)>0?'#c62828':'#2e7d32' ?>"><?= count($maint_overdue) ?></div>
      <div style="font-size:11px;color:#888">件（設備）</div>
    </div></div>
    <div class="card" style="margin:0"><div class="card-body" style="text-align:center;padding:16px 10px">
      <div style="font-size:11px;color:#888;margin-bottom:6px">今月のパトロール実施</div>
      <div style="font-size:32px;font-weight:700;color:#2E75B6"><?= array_sum(array_column($patrol_stats,'jisshi')) ?></div>
      <div style="font-size:11px;color:#888">計画 <?= array_sum(array_column($patrol_stats,'keikaku')) ?> 件</div>
    </div></div>
  </div>

  <!-- 未対応の不具合 -->
  <div class="card">
    <div class="card-header" style="background:#c62828">未対応の不具合・行動計画<span style="font-size:12px;font-weight:400"><?= count($open_issues) ?>件</span></div>
    <div class="card-body" style="padding:0">
      <?php if(empty($open_issues)): ?>
      <div style="text-align:center;color:#999;padding:24px">未対応の不具合はありません</div>
      <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>工場</th><th>タイトル</th><th>優先度</th><th>ステータス</th><th>期限</th><th style="text-align:center">操作</th></tr></thead>
        <tbody>
        <?php foreach(array_slice($open_issues,0,10) as $g): [$label,$color] = kj_days_label($g['days_left']); ?>
        <tr>
          <td><?= h($g['f_factory_name']) ?></td>
          <td style="font-weight:600"><?= h($g['f_title']) ?></td>
          <td><?= kj_priority_badge($g['f_priority']) ?></td>
          <td><?= kj_status_badge($g['f_status']) ?></td>
          <td><?php if($g['f_kigen_date']): ?><span style="color:<?= $color ?>;font-weight:600;font-size:12px"><?= h(date('Y/m/d',strtotime($g['f_kigen_date']))) ?>（<?= $label ?>）</span><?php else: ?>―<?php endif; ?></td>
          <td style="text-align:center"><a href="kj_issue.php" class="btn btn-blue btn-sm">対応する</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- 保守期日超過 -->
  <div class="card">
    <div class="card-header" style="background:#e65100">保守・消耗品交換　期日超過<span style="font-size:12px;font-weight:400"><?= count($maint_overdue) ?>件</span></div>
    <div class="card-body" style="padding:0">
      <?php if(empty($maint_overdue)): ?>
      <div style="text-align:center;color:#999;padding:24px">期日超過の設備はありません</div>
      <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>工場</th><th>所属設備</th><th>設備</th><th>予定日</th><th>超過日数</th><th style="text-align:center">操作</th></tr></thead>
        <tbody>
        <?php foreach($maint_overdue as $s): ?>
        <tr>
          <td><?= h($s['f_factory_name']) ?></td>
          <td style="color:#888"><?= $s['parent_name'] ? h($s['parent_name']) : '―' ?></td>
          <td style="font-weight:600"><?= h($s['f_setsubi_name']) ?></td>
          <td><?= h(date('Y/m/d',strtotime($s['next_date']))) ?></td>
          <td><span style="color:#c62828;font-weight:700"><?= $s['days_over'] ?>日超過</span></td>
          <td style="text-align:center"><a href="kj_maintenance.php" class="btn btn-blue btn-sm">記録する</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- 工場別点検実施状況・パトロール状況 -->
  <div class="card">
    <div class="card-header">工場別　点検実施・パトロール状況</div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap"><table>
        <thead><tr><th>工場</th><th style="text-align:center">直近7日の点検日数</th><th style="text-align:center">今月パトロール実施</th><th style="text-align:center">今月パトロール計画</th><th>最終パトロール日</th></tr></thead>
        <tbody>
        <?php foreach($patrol_stats as $p): $cd = $check_days_by_factory[$p['pk_factory_id']] ?? 0; ?>
        <tr>
          <td style="font-weight:600"><?= h($p['f_factory_name']) ?></td>
          <td style="text-align:center">
            <span style="font-weight:700;color:<?= $cd>=6?'#2e7d32':($cd>=3?'#e65100':'#c62828') ?>"><?= $cd ?></span> / 7日
          </td>
          <td style="text-align:center"><?= (int)$p['jisshi'] ?>件</td>
          <td style="text-align:center"><?= (int)$p['keikaku'] ?>件</td>
          <td><?= $p['last_date'] ? h(date('Y/m/d', strtotime($p['last_date']))) : '―' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>
<?= html_footer() ?>
