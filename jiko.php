<?php
// ============================================================
// 事故報告（大メニュー）
// フロー：報告 → 上長確認（部門管理者・管理者）→ 管理確認（管理者）
//        → 対応状況（継続／終了）→ 経営層報告（管理者）
// ============================================================
require_once 'db.php';
require_once 'common.php';
$pdo = get_db();
$kengen = $_SESSION['kengen'] ?? '';
$is_admin = ($kengen === '管理者');
$can_joucho = in_array($kengen, ['部門管理者', '管理者'], true); // 上長確認は部門管理者以上
$msg = ''; $msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title = trim($_POST['f_title'] ?? '');
        if ($title && !empty($_POST['f_date'])) {
            $pdo->prepare("INSERT INTO t_jiko (pk_jiko_id,f_date,f_time,fk_factory_id,f_place_text,f_kubun,f_title,f_detail,fk_tantosha_id,f_created_at,f_updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())")
                ->execute([
                    generate_uuid(), $_POST['f_date'], $_POST['f_time'] ?: null,
                    $_POST['fk_factory_id'] ?: null, $_POST['f_place_text'] ?? '',
                    $_POST['f_kubun'] ?? 'その他', $title, $_POST['f_detail'] ?? '',
                    $_SESSION['tantosha_id'],
                ]);
            $msg = '事故報告を登録しました。上長・管理者による確認をお待ちください。';
        } else { $msg = '発生日と件名は必須です。'; $msg_type = 'danger'; }

    } elseif ($action === 'joucho_confirm' && $can_joucho) {
        $pdo->prepare("UPDATE t_jiko SET f_joucho_kakunin_flag='確認済', fk_joucho_kakunin_tantosha_id=?, f_joucho_kakunin_at=NOW() WHERE pk_jiko_id=?")
            ->execute([$_SESSION['tantosha_id'], $_POST['jiko_id'] ?? '']);
        $msg = '上長確認を記録しました。';

    } elseif ($action === 'kanri_confirm' && $is_admin) {
        $pdo->prepare("UPDATE t_jiko SET f_kanri_kakunin_flag='確認済', fk_kanri_kakunin_tantosha_id=?, f_kanri_kakunin_at=NOW() WHERE pk_jiko_id=?")
            ->execute([$_SESSION['tantosha_id'], $_POST['jiko_id'] ?? '']);
        $msg = '管理確認を記録しました。';

    } elseif ($action === 'taiou_status' && $can_joucho) {
        $new = ($_POST['new_status'] ?? '継続') === '終了' ? '終了' : '継続';
        $pdo->prepare("UPDATE t_jiko SET f_taiou_status=? WHERE pk_jiko_id=?")
            ->execute([$new, $_POST['jiko_id'] ?? '']);
        $msg = "対応状況を「{$new}」に更新しました。";

    } elseif ($action === 'keiei_houkoku' && $is_admin) {
        $pdo->prepare("UPDATE t_jiko SET f_keiei_houkoku_flag='報告済', f_keiei_houkoku_at=NOW() WHERE pk_jiko_id=?")
            ->execute([$_POST['jiko_id'] ?? '']);
        $msg = '経営層への報告済みとして記録しました。';

    } elseif ($action === 'delete' && $is_admin) {
        $pdo->prepare("DELETE FROM t_jiko WHERE pk_jiko_id=?")->execute([$_POST['jiko_id'] ?? '']);
        $msg = '削除しました。';
    }
}

$f_status = $_GET['f_status'] ?? '';
$where = ['1=1']; $params = [];
if ($f_status === '継続')       { $where[] = "j.f_taiou_status = '継続'"; }
elseif ($f_status === '終了')   { $where[] = "j.f_taiou_status = '終了'"; }
elseif ($f_status === '未報告') { $where[] = "j.f_keiei_houkoku_flag = '未報告'"; }

$stmt = $pdo->prepare("
    SELECT j.*, f.f_factory_name, t.f_tantosha_name,
           jt.f_tantosha_name AS joucho_name, kt.f_tantosha_name AS kanri_name
    FROM t_jiko j
    LEFT JOIN t_factory f ON j.fk_factory_id = f.pk_factory_id
    JOIN t_tantosha t ON j.fk_tantosha_id = t.pk_tantosha_id
    LEFT JOIN t_tantosha jt ON j.fk_joucho_kakunin_tantosha_id = jt.pk_tantosha_id
    LEFT JOIN t_tantosha kt ON j.fk_kanri_kakunin_tantosha_id = kt.pk_tantosha_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY j.f_date DESC, j.f_created_at DESC
");
$stmt->execute($params);
$list = $stmt->fetchAll();

$open_count = $pdo->query("SELECT COUNT(*) FROM t_jiko WHERE f_taiou_status='継続'")->fetchColumn();
$unreported_count = $pdo->query("SELECT COUNT(*) FROM t_jiko WHERE f_keiei_houkoku_flag='未報告'")->fetchColumn();
$factories = $pdo->query("SELECT * FROM t_factory WHERE f_active='有効' ORDER BY f_sort_order,f_factory_name")->fetchAll();

function jiko_kubun_badge($k) {
    $map = ['労災' => 'badge-danger', '交通事故' => 'badge-danger', '設備事故' => 'badge-warning', 'ヒヤリハット' => 'badge-info', 'その他' => 'badge-info'];
    $cls = $map[$k] ?? 'badge-info';
    return "<span class='badge {$cls}'>" . h($k) . "</span>";
}

echo html_header('事故報告');
echo nav_bar();
?>
<div class="container">
  <div class="page-title">🚨 事故報告</div>

  <?php if($msg): ?><div class="alert alert-<?= $msg_type ?>"><?= h($msg) ?></div><?php endif; ?>

  <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:16px">
    <div class="card" style="margin:0"><div class="card-body" style="text-align:center;padding:16px 10px">
      <div style="font-size:11px;color:#888;margin-bottom:6px">対応継続中</div>
      <div style="font-size:32px;font-weight:700;color:<?= $open_count>0?'#c62828':'#2e7d32' ?>"><?= $open_count ?></div>
    </div></div>
    <div class="card" style="margin:0"><div class="card-body" style="text-align:center;padding:16px 10px">
      <div style="font-size:11px;color:#888;margin-bottom:6px">経営層 未報告</div>
      <div style="font-size:32px;font-weight:700;color:<?= $unreported_count>0?'#e65100':'#2e7d32' ?>"><?= $unreported_count ?></div>
    </div></div>
  </div>

  <div class="card">
    <div class="card-header">事故報告を登録</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="add">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px" class="meisai-grid-3">
          <div class="form-group" style="margin:0"><label>発生日 <span style="color:#c62828">*</span></label><input type="date" name="f_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
          <div class="form-group" style="margin:0"><label>発生時刻</label><input type="time" name="f_time" class="form-control"></div>
          <div class="form-group" style="margin:0">
            <label>事故種別</label>
            <select name="f_kubun" class="form-control">
              <option value="労災">労災</option>
              <option value="交通事故">交通事故</option>
              <option value="設備事故">設備事故</option>
              <option value="ヒヤリハット">ヒヤリハット</option>
              <option value="その他" selected>その他</option>
            </select>
          </div>
          <div class="form-group" style="margin:0">
            <label>発生場所（工場・拠点）</label>
            <select name="fk_factory_id" class="form-control">
              <option value="">-- 該当なし／選択しない --</option>
              <?php foreach($factories as $f): ?><option value="<?= h($f['pk_factory_id']) ?>"><?= h($f['f_factory_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0;grid-column:span 2"><label>場所の補足（工場以外の場合など）</label><input type="text" name="f_place_text" class="form-control" placeholder="例：高松市内、○○号線"></div>
          <div class="form-group" style="grid-column:1/-1;margin:0"><label>件名 <span style="color:#c62828">*</span></label><input type="text" name="f_title" class="form-control" placeholder="例：構内でフォークリフトと接触" required></div>
          <div class="form-group" style="grid-column:1/-1;margin:0"><label>詳細</label><textarea name="f_detail" class="form-control" rows="3" placeholder="発生状況、けがの有無、初期対応 等"></textarea></div>
        </div>
        <div style="text-align:right;margin-top:12px"><button type="submit" class="btn btn-primary">報告する</button></div>
      </form>
    </div>
  </div>

  <form method="get" class="search-area">
    <label>絞り込み</label>
    <select name="f_status" onchange="this.form.submit()">
      <option value="">すべて</option>
      <option value="継続" <?= $f_status==='継続'?'selected':'' ?>>対応継続中</option>
      <option value="終了" <?= $f_status==='終了'?'selected':'' ?>>対応終了</option>
      <option value="未報告" <?= $f_status==='未報告'?'selected':'' ?>>経営層 未報告</option>
    </select>
  </form>

  <div class="card">
    <div class="card-header">事故報告一覧<span style="font-size:12px;font-weight:400"><?= count($list) ?>件</span></div>
    <div class="card-body" style="padding:0">
      <?php if(empty($list)): ?>
      <div style="text-align:center;color:#999;padding:40px">該当する報告はありません</div>
      <?php else: foreach($list as $j): ?>
      <div style="padding:14px 16px;border-bottom:1px solid #e8eef5;<?= $j['f_taiou_status']==='継続'?'border-left:3px solid #c62828':'' ?>">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px">
          <div style="flex:1;min-width:220px">
            <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:4px">
              <?= jiko_kubun_badge($j['f_kubun']) ?>
              <span style="font-weight:700;font-size:14px"><?= h($j['f_title']) ?></span>
            </div>
            <div style="font-size:12px;color:#666">
              📅 <?= h(date('Y/m/d', strtotime($j['f_date']))) ?><?= $j['f_time']?' '.h($j['f_time']):'' ?>
              <?php if($j['f_factory_name']): ?>／🏭 <?= h($j['f_factory_name']) ?><?php endif; ?>
              <?php if($j['f_place_text']): ?>／<?= h($j['f_place_text']) ?><?php endif; ?>
              ／👤 報告者：<?= h($j['f_tantosha_name']) ?>
            </div>
            <?php if($j['f_detail']): ?><div style="font-size:12px;color:#666;margin-top:6px;white-space:pre-wrap"><?= h($j['f_detail']) ?></div><?php endif; ?>

            <!-- 確認フロー -->
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px">
              <span class="badge <?= $j['f_joucho_kakunin_flag']==='確認済'?'badge-success':'badge-warning' ?>">上長確認：<?= h($j['f_joucho_kakunin_flag']) ?><?= $j['joucho_name']?'（'.h($j['joucho_name']).'）':'' ?></span>
              <span class="badge <?= $j['f_kanri_kakunin_flag']==='確認済'?'badge-success':'badge-warning' ?>">管理確認：<?= h($j['f_kanri_kakunin_flag']) ?><?= $j['kanri_name']?'（'.h($j['kanri_name']).'）':'' ?></span>
              <span class="badge <?= $j['f_taiou_status']==='終了'?'badge-success':'badge-danger' ?>">対応：<?= h($j['f_taiou_status']) ?></span>
              <span class="badge <?= $j['f_keiei_houkoku_flag']==='報告済'?'badge-success':'badge-info' ?>">経営層報告：<?= h($j['f_keiei_houkoku_flag']) ?></span>
            </div>
          </div>

          <!-- 操作 -->
          <div style="display:flex;gap:4px;flex-wrap:wrap;justify-content:flex-end">
            <?php if($can_joucho && $j['f_joucho_kakunin_flag']!=='確認済'): ?>
            <form method="post" style="display:inline"><input type="hidden" name="action" value="joucho_confirm"><input type="hidden" name="jiko_id" value="<?= h($j['pk_jiko_id']) ?>"><button type="submit" class="btn btn-blue btn-sm">上長確認する</button></form>
            <?php endif; ?>
            <?php if($is_admin && $j['f_kanri_kakunin_flag']!=='確認済'): ?>
            <form method="post" style="display:inline"><input type="hidden" name="action" value="kanri_confirm"><input type="hidden" name="jiko_id" value="<?= h($j['pk_jiko_id']) ?>"><button type="submit" class="btn btn-blue btn-sm">管理確認する</button></form>
            <?php endif; ?>
            <?php if($can_joucho): ?>
              <?php if($j['f_taiou_status']==='継続'): ?>
              <form method="post" style="display:inline"><input type="hidden" name="action" value="taiou_status"><input type="hidden" name="jiko_id" value="<?= h($j['pk_jiko_id']) ?>"><input type="hidden" name="new_status" value="終了"><button type="submit" class="btn btn-success btn-sm">対応終了にする</button></form>
              <?php else: ?>
              <form method="post" style="display:inline"><input type="hidden" name="action" value="taiou_status"><input type="hidden" name="jiko_id" value="<?= h($j['pk_jiko_id']) ?>"><input type="hidden" name="new_status" value="継続"><button type="submit" class="btn btn-gray btn-sm">継続に戻す</button></form>
              <?php endif; ?>
            <?php endif; ?>
            <?php if($is_admin && $j['f_keiei_houkoku_flag']!=='報告済'): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('経営層への報告済みとして記録しますか？')"><input type="hidden" name="action" value="keiei_houkoku"><input type="hidden" name="jiko_id" value="<?= h($j['pk_jiko_id']) ?>"><button type="submit" class="btn btn-warning btn-sm">経営層報告済みにする</button></form>
            <?php endif; ?>
            <?php if($is_admin): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？')"><input type="hidden" name="action" value="delete"><input type="hidden" name="jiko_id" value="<?= h($j['pk_jiko_id']) ?>"><button type="submit" class="btn btn-danger btn-sm">削除</button></form>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>
<?= html_footer() ?>
