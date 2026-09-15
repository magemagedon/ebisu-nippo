<?php
// ============================================================
// 事故報告（大メニュー）― 承認フロー
// フロー：申請中 → 上長確認済（部門管理者・管理者が確認／差し戻し可）
//        → 管理確認済（管理者が確認／差し戻し可）
//        各段階は順序を厳密に強制（前段階が済むまで次のボタンは出さない）。
//        差し戻し時は理由を添えて報告者に戻し、報告者が修正して再申請する。
//        管理確認済のあと、対応状況（継続／終了）・経営層報告を管理者が記録する。
// ============================================================
require_once 'db.php';
require_once 'common.php';
$pdo = get_db();
$kengen = $_SESSION['kengen'] ?? '';
$is_admin = ($kengen === '管理者');
$can_joucho = in_array($kengen, ['部門管理者', '管理者'], true); // 上長確認は部門管理者以上
$msg = ''; $msg_type = 'success';

$SYSTEM_URL = 'http://arsystem.jp/ebisu/jiko.php';

function jiko_get(PDO $pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM t_jiko WHERE pk_jiko_id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title = trim($_POST['f_title'] ?? '');
        if ($title && !empty($_POST['f_date'])) {
            $pdo->prepare("INSERT INTO t_jiko (pk_jiko_id,f_date,f_time,fk_factory_id,f_place_text,f_kubun,f_title,f_detail,fk_tantosha_id,f_status,f_created_at,f_updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,'申請中',NOW(),NOW())")
                ->execute([
                    generate_uuid(), $_POST['f_date'], $_POST['f_time'] ?: null,
                    $_POST['fk_factory_id'] ?: null, $_POST['f_place_text'] ?? '',
                    $_POST['f_kubun'] ?? 'その他', $title, $_POST['f_detail'] ?? '',
                    $_SESSION['tantosha_id'],
                ]);
            $msg = '事故報告を登録しました。上長による確認をお待ちください。';

            $to_list = mail_targets_by_kengen($pdo, ['部門管理者', '管理者']);
            foreach ($to_list as $to) {
                system_send_mail($to, "【事故報告】上長確認をお願いします：{$title}",
                    "事故報告が登録されました。内容をご確認のうえ、上長確認をお願いします。\n\n" .
                    "件名：{$title}\n発生日：{$_POST['f_date']}\n報告者：{$_SESSION['tantosha_name']}\n\n" .
                    "システムでご確認ください：\n{$SYSTEM_URL}");
            }
        } else { $msg = '発生日と件名は必須です。'; $msg_type = 'danger'; }

    } elseif ($action === 'joucho_confirm' && $can_joucho) {
        $row = jiko_get($pdo, $_POST['jiko_id'] ?? '');
        if ($row && $row['f_status'] === '申請中') {
            $pdo->prepare("UPDATE t_jiko SET f_status='上長確認済', f_joucho_kakunin_flag='確認済', fk_joucho_kakunin_tantosha_id=?, f_joucho_kakunin_at=NOW(), f_updated_at=NOW() WHERE pk_jiko_id=?")
                ->execute([$_SESSION['tantosha_id'], $row['pk_jiko_id']]);
            $msg = '上長確認を記録しました。管理確認待ちです。';

            $to_list = mail_targets_by_kengen($pdo, ['管理者']);
            foreach ($to_list as $to) {
                system_send_mail($to, "【事故報告】管理確認をお願いします：{$row['f_title']}",
                    "上長確認が完了しました。管理確認をお願いします。\n\n" .
                    "件名：{$row['f_title']}\n発生日：{$row['f_date']}\n\n" .
                    "システムでご確認ください：\n{$SYSTEM_URL}");
            }
        } else { $msg = 'この報告は現在「申請中」ではないため、上長確認できません。'; $msg_type = 'danger'; }

    } elseif ($action === 'kanri_confirm' && $is_admin) {
        $row = jiko_get($pdo, $_POST['jiko_id'] ?? '');
        if ($row && $row['f_status'] === '上長確認済') {
            $pdo->prepare("UPDATE t_jiko SET f_status='管理確認済', f_kanri_kakunin_flag='確認済', fk_kanri_kakunin_tantosha_id=?, f_kanri_kakunin_at=NOW(), f_updated_at=NOW() WHERE pk_jiko_id=?")
                ->execute([$_SESSION['tantosha_id'], $row['pk_jiko_id']]);
            $msg = '管理確認を記録しました。承認フローが完了しました。';

            $reporter = $pdo->prepare("SELECT f_email FROM t_tantosha WHERE pk_tantosha_id=?");
            $reporter->execute([$row['fk_tantosha_id']]);
            $to = $reporter->fetchColumn();
            if ($to) {
                system_send_mail($to, "【事故報告】承認が完了しました：{$row['f_title']}",
                    "あなたが報告した事故報告の承認（上長確認・管理確認）が完了しました。\n\n" .
                    "件名：{$row['f_title']}\n発生日：{$row['f_date']}\n\n{$SYSTEM_URL}");
            }
        } else { $msg = 'この報告は「上長確認済」でないため、管理確認できません。'; $msg_type = 'danger'; }

    } elseif ($action === 'sashimodoshi') {
        $row = jiko_get($pdo, $_POST['jiko_id'] ?? '');
        $riyu = trim($_POST['f_sashimodoshi_riyu'] ?? '');
        $stage = null;
        if ($row && $can_joucho && $row['f_status'] === '申請中') { $stage = '上長'; }
        elseif ($row && $is_admin && $row['f_status'] === '上長確認済') { $stage = '管理'; }

        if ($row && $stage && $riyu !== '') {
            $pdo->prepare("UPDATE t_jiko SET f_status='差し戻し', f_sashimodoshi_stage=?, f_sashimodoshi_riyu=?, fk_sashimodoshi_tantosha_id=?, f_sashimodoshi_at=NOW(), f_updated_at=NOW() WHERE pk_jiko_id=?")
                ->execute([$stage, $riyu, $_SESSION['tantosha_id'], $row['pk_jiko_id']]);
            $msg = '差し戻しました。報告者に通知します。';

            $reporter = $pdo->prepare("SELECT f_email FROM t_tantosha WHERE pk_tantosha_id=?");
            $reporter->execute([$row['fk_tantosha_id']]);
            $to = $reporter->fetchColumn();
            if ($to) {
                system_send_mail($to, "【事故報告】差し戻されました：{$row['f_title']}",
                    "あなたが報告した事故報告が「{$stage}確認」の段階で差し戻されました。内容を修正のうえ、再申請してください。\n\n" .
                    "件名：{$row['f_title']}\n差し戻し理由：{$riyu}\n\n{$SYSTEM_URL}");
            }
        } else { $msg = '差し戻し理由の入力、または操作可能な段階かをご確認ください。'; $msg_type = 'danger'; }

    } elseif ($action === 'resubmit') {
        $row = jiko_get($pdo, $_POST['jiko_id'] ?? '');
        $can_edit = $row && $row['f_status'] === '差し戻し' && ($is_admin || $row['fk_tantosha_id'] === $_SESSION['tantosha_id']);
        $title = trim($_POST['f_title'] ?? '');
        if ($can_edit && $title && !empty($_POST['f_date'])) {
            $pdo->prepare("UPDATE t_jiko SET
                    f_date=?, f_time=?, fk_factory_id=?, f_place_text=?, f_kubun=?, f_title=?, f_detail=?,
                    f_status='申請中',
                    f_joucho_kakunin_flag='未確認', fk_joucho_kakunin_tantosha_id=NULL, f_joucho_kakunin_at=NULL,
                    f_kanri_kakunin_flag='未確認', fk_kanri_kakunin_tantosha_id=NULL, f_kanri_kakunin_at=NULL,
                    f_saishinsei_at=NOW(), f_updated_at=NOW()
                WHERE pk_jiko_id=?")
                ->execute([
                    $_POST['f_date'], $_POST['f_time'] ?: null, $_POST['fk_factory_id'] ?: null,
                    $_POST['f_place_text'] ?? '', $_POST['f_kubun'] ?? 'その他', $title, $_POST['f_detail'] ?? '',
                    $row['pk_jiko_id'],
                ]);
            $msg = '修正のうえ再申請しました。上長による確認をお待ちください。';

            $to_list = mail_targets_by_kengen($pdo, ['部門管理者', '管理者']);
            foreach ($to_list as $to) {
                system_send_mail($to, "【事故報告】再申請：上長確認をお願いします：{$title}",
                    "差し戻された事故報告が修正のうえ再申請されました。上長確認をお願いします。\n\n" .
                    "件名：{$title}\n発生日：{$_POST['f_date']}\n\n{$SYSTEM_URL}");
            }
        } else { $msg = '再申請できませんでした（差し戻し状態か、必須項目をご確認ください）。'; $msg_type = 'danger'; }

    } elseif ($action === 'taiou_status' && $can_joucho) {
        $new = ($_POST['new_status'] ?? '継続') === '終了' ? '終了' : '継続';
        $pdo->prepare("UPDATE t_jiko SET f_taiou_status=?, f_updated_at=NOW() WHERE pk_jiko_id=?")
            ->execute([$new, $_POST['jiko_id'] ?? '']);
        $msg = "対応状況を「{$new}」に更新しました。";

    } elseif ($action === 'keiei_houkoku' && $is_admin) {
        $row = jiko_get($pdo, $_POST['jiko_id'] ?? '');
        if ($row && $row['f_status'] === '管理確認済') {
            $pdo->prepare("UPDATE t_jiko SET f_keiei_houkoku_flag='報告済', f_keiei_houkoku_at=NOW(), f_updated_at=NOW() WHERE pk_jiko_id=?")
                ->execute([$row['pk_jiko_id']]);
            $msg = '経営層への報告済みとして記録しました。';
        } else { $msg = '管理確認済みでないため、経営層報告済みにはできません。'; $msg_type = 'danger'; }

    } elseif ($action === 'delete' && $is_admin) {
        $pdo->prepare("DELETE FROM t_jiko WHERE pk_jiko_id=?")->execute([$_POST['jiko_id'] ?? '']);
        $msg = '削除しました。';
    }
}

$f_status = $_GET['f_status'] ?? '';
$where = ['1=1']; $params = [];
if ($f_status === '継続')          { $where[] = "j.f_taiou_status = '継続'"; }
elseif ($f_status === '終了')      { $where[] = "j.f_taiou_status = '終了'"; }
elseif ($f_status === '未報告')    { $where[] = "j.f_keiei_houkoku_flag = '未報告'"; }
elseif ($f_status === '承認待ち')  { $where[] = "j.f_status IN ('申請中','上長確認済')"; }
elseif ($f_status === '差し戻し')  { $where[] = "j.f_status = '差し戻し'"; }

$stmt = $pdo->prepare("
    SELECT j.*, f.f_factory_name, t.f_tantosha_name,
           jt.f_tantosha_name AS joucho_name, kt.f_tantosha_name AS kanri_name,
           st.f_tantosha_name AS sashimodoshi_name
    FROM t_jiko j
    LEFT JOIN t_factory f ON j.fk_factory_id = f.pk_factory_id
    JOIN t_tantosha t ON j.fk_tantosha_id = t.pk_tantosha_id
    LEFT JOIN t_tantosha jt ON j.fk_joucho_kakunin_tantosha_id = jt.pk_tantosha_id
    LEFT JOIN t_tantosha kt ON j.fk_kanri_kakunin_tantosha_id = kt.pk_tantosha_id
    LEFT JOIN t_tantosha st ON j.fk_sashimodoshi_tantosha_id = st.pk_tantosha_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY j.f_date DESC, j.f_created_at DESC
");
$stmt->execute($params);
$list = $stmt->fetchAll();

$open_count = $pdo->query("SELECT COUNT(*) FROM t_jiko WHERE f_taiou_status='継続'")->fetchColumn();
$unreported_count = $pdo->query("SELECT COUNT(*) FROM t_jiko WHERE f_keiei_houkoku_flag='未報告'")->fetchColumn();
$matteru_count = $pdo->query("SELECT COUNT(*) FROM t_jiko WHERE f_status IN ('申請中','上長確認済')")->fetchColumn();
$sashimodoshi_count = $pdo->query("SELECT COUNT(*) FROM t_jiko WHERE f_status='差し戻し'")->fetchColumn();
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

  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px" class="meisai-grid-3">
    <div class="card" style="margin:0"><div class="card-body" style="text-align:center;padding:16px 10px">
      <div style="font-size:11px;color:#888;margin-bottom:6px">承認待ち</div>
      <div style="font-size:32px;font-weight:700;color:<?= $matteru_count>0?'#e65100':'#2e7d32' ?>"><?= $matteru_count ?></div>
    </div></div>
    <div class="card" style="margin:0"><div class="card-body" style="text-align:center;padding:16px 10px">
      <div style="font-size:11px;color:#888;margin-bottom:6px">差し戻し中</div>
      <div style="font-size:32px;font-weight:700;color:<?= $sashimodoshi_count>0?'#c62828':'#2e7d32' ?>"><?= $sashimodoshi_count ?></div>
    </div></div>
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
      <option value="承認待ち" <?= $f_status==='承認待ち'?'selected':'' ?>>承認待ち（申請中／上長確認済）</option>
      <option value="差し戻し" <?= $f_status==='差し戻し'?'selected':'' ?>>差し戻し中</option>
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
      <?php else: foreach($list as $j):
        $can_resubmit = $j['f_status'] === '差し戻し' && ($is_admin || $j['fk_tantosha_id'] === $_SESSION['tantosha_id']);
      ?>
      <div style="padding:14px 16px;border-bottom:1px solid #e8eef5;<?= $j['f_taiou_status']==='継続'?'border-left:3px solid #c62828':'' ?>">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px">
          <div style="flex:1;min-width:220px">
            <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:4px">
              <?= jiko_kubun_badge($j['f_kubun']) ?>
              <span style="font-weight:700;font-size:14px"><?= h($j['f_title']) ?></span>
              <?= status_badge($j['f_status']) ?>
            </div>
            <div style="font-size:12px;color:#666">
              📅 <?= h(date('Y/m/d', strtotime($j['f_date']))) ?><?= $j['f_time']?' '.h($j['f_time']):'' ?>
              <?php if($j['f_factory_name']): ?>／🏭 <?= h($j['f_factory_name']) ?><?php endif; ?>
              <?php if($j['f_place_text']): ?>／<?= h($j['f_place_text']) ?><?php endif; ?>
              ／👤 報告者：<?= h($j['f_tantosha_name']) ?>
            </div>
            <?php if($j['f_detail']): ?><div style="font-size:12px;color:#666;margin-top:6px;white-space:pre-wrap"><?= h($j['f_detail']) ?></div><?php endif; ?>

            <?php if($j['f_status'] === '差し戻し'): ?>
            <div style="background:#fdecea;border:1px solid #f5c6cb;border-radius:6px;padding:8px 10px;margin-top:8px;font-size:12px;color:#611a15">
              ⚠️ <?= h($j['f_sashimodoshi_stage']) ?>確認の段階で差し戻されました（<?= h($j['sashimodoshi_name'] ?? '') ?>／<?= h(date('Y/m/d H:i', strtotime($j['f_sashimodoshi_at']))) ?>）<br>
              理由：<?= nl2br(h($j['f_sashimodoshi_riyu'])) ?>
            </div>
            <?php endif; ?>

            <!-- 承認フロー -->
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px">
              <span class="badge <?= $j['f_joucho_kakunin_flag']==='確認済'?'badge-success':'badge-warning' ?>">上長確認：<?= h($j['f_joucho_kakunin_flag']) ?><?= $j['joucho_name']?'（'.h($j['joucho_name']).'）':'' ?></span>
              <span class="badge <?= $j['f_kanri_kakunin_flag']==='確認済'?'badge-success':'badge-warning' ?>">管理確認：<?= h($j['f_kanri_kakunin_flag']) ?><?= $j['kanri_name']?'（'.h($j['kanri_name']).'）':'' ?></span>
              <span class="badge <?= $j['f_taiou_status']==='終了'?'badge-success':'badge-danger' ?>">対応：<?= h($j['f_taiou_status']) ?></span>
              <span class="badge <?= $j['f_keiei_houkoku_flag']==='報告済'?'badge-success':'badge-info' ?>">経営層報告：<?= h($j['f_keiei_houkoku_flag']) ?></span>
            </div>
          </div>

          <!-- 操作 -->
          <div style="display:flex;gap:4px;flex-wrap:wrap;justify-content:flex-end">
            <?php if($can_joucho && $j['f_status']==='申請中'): ?>
            <form method="post" style="display:inline"><input type="hidden" name="action" value="joucho_confirm"><input type="hidden" name="jiko_id" value="<?= h($j['pk_jiko_id']) ?>"><button type="submit" class="btn btn-blue btn-sm">上長確認する</button></form>
            <button type="button" class="btn btn-danger btn-sm" onclick="openSashimodoshi('<?= h($j['pk_jiko_id']) ?>','<?= h(addslashes($j['f_title'])) ?>')">差し戻す</button>
            <?php endif; ?>
            <?php if($is_admin && $j['f_status']==='上長確認済'): ?>
            <form method="post" style="display:inline"><input type="hidden" name="action" value="kanri_confirm"><input type="hidden" name="jiko_id" value="<?= h($j['pk_jiko_id']) ?>"><button type="submit" class="btn btn-blue btn-sm">管理確認する</button></form>
            <button type="button" class="btn btn-danger btn-sm" onclick="openSashimodoshi('<?= h($j['pk_jiko_id']) ?>','<?= h(addslashes($j['f_title'])) ?>')">差し戻す</button>
            <?php endif; ?>
            <?php if($can_resubmit): ?>
            <button type="button" class="btn btn-warning btn-sm" onclick="openResubmit(<?= h(json_encode($j)) ?>)">修正して再申請</button>
            <?php endif; ?>
            <?php if($can_joucho): ?>
              <?php if($j['f_taiou_status']==='継続'): ?>
              <form method="post" style="display:inline"><input type="hidden" name="action" value="taiou_status"><input type="hidden" name="jiko_id" value="<?= h($j['pk_jiko_id']) ?>"><input type="hidden" name="new_status" value="終了"><button type="submit" class="btn btn-success btn-sm">対応終了にする</button></form>
              <?php else: ?>
              <form method="post" style="display:inline"><input type="hidden" name="action" value="taiou_status"><input type="hidden" name="jiko_id" value="<?= h($j['pk_jiko_id']) ?>"><input type="hidden" name="new_status" value="継続"><button type="submit" class="btn btn-gray btn-sm">継続に戻す</button></form>
              <?php endif; ?>
            <?php endif; ?>
            <?php if($is_admin && $j['f_status']==='管理確認済' && $j['f_keiei_houkoku_flag']!=='報告済'): ?>
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

<!-- 差し戻しモーダル -->
<div id="sashimodoshiModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:300;align-items:center;justify-content:center;padding:16px">
  <div style="background:#fff;border-radius:8px;max-width:420px;width:100%;padding:20px">
    <div style="font-weight:700;font-size:15px;margin-bottom:10px">差し戻し：<span id="sm_title"></span></div>
    <form method="post">
      <input type="hidden" name="action" value="sashimodoshi">
      <input type="hidden" name="jiko_id" id="sm_jiko_id">
      <div class="form-group"><label>差し戻し理由 <span style="color:#c62828">*</span></label>
        <textarea name="f_sashimodoshi_riyu" class="form-control" rows="4" required placeholder="修正してほしい内容を具体的に記入してください"></textarea>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
        <button type="button" class="btn btn-gray btn-sm" onclick="closeSashimodoshi()">キャンセル</button>
        <button type="submit" class="btn btn-danger btn-sm">差し戻す</button>
      </div>
    </form>
  </div>
</div>

<!-- 再申請モーダル -->
<div id="resubmitModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:300;align-items:center;justify-content:center;padding:16px;overflow-y:auto">
  <div style="background:#fff;border-radius:8px;max-width:520px;width:100%;padding:20px;margin:20px 0">
    <div style="font-weight:700;font-size:15px;margin-bottom:10px">修正して再申請</div>
    <form method="post">
      <input type="hidden" name="action" value="resubmit">
      <input type="hidden" name="jiko_id" id="rs_jiko_id">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="form-group" style="margin:0"><label>発生日 <span style="color:#c62828">*</span></label><input type="date" name="f_date" id="rs_date" class="form-control" required></div>
        <div class="form-group" style="margin:0"><label>発生時刻</label><input type="time" name="f_time" id="rs_time" class="form-control"></div>
        <div class="form-group" style="margin:0">
          <label>事故種別</label>
          <select name="f_kubun" id="rs_kubun" class="form-control">
            <option value="労災">労災</option><option value="交通事故">交通事故</option>
            <option value="設備事故">設備事故</option><option value="ヒヤリハット">ヒヤリハット</option><option value="その他">その他</option>
          </select>
        </div>
        <div class="form-group" style="margin:0">
          <label>発生場所（工場・拠点）</label>
          <select name="fk_factory_id" id="rs_factory" class="form-control">
            <option value="">-- 該当なし／選択しない --</option>
            <?php foreach($factories as $f): ?><option value="<?= h($f['pk_factory_id']) ?>"><?= h($f['f_factory_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0;grid-column:1/-1"><label>場所の補足</label><input type="text" name="f_place_text" id="rs_place" class="form-control"></div>
        <div class="form-group" style="margin:0;grid-column:1/-1"><label>件名 <span style="color:#c62828">*</span></label><input type="text" name="f_title" id="rs_title" class="form-control" required></div>
        <div class="form-group" style="margin:0;grid-column:1/-1"><label>詳細</label><textarea name="f_detail" id="rs_detail" class="form-control" rows="3"></textarea></div>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
        <button type="button" class="btn btn-gray btn-sm" onclick="closeResubmit()">キャンセル</button>
        <button type="submit" class="btn btn-primary btn-sm">再申請する</button>
      </div>
    </form>
  </div>
</div>

<script>
function openSashimodoshi(id, title) {
    document.getElementById('sm_jiko_id').value = id;
    document.getElementById('sm_title').textContent = title;
    document.getElementById('sashimodoshiModal').style.display = 'flex';
}
function closeSashimodoshi() { document.getElementById('sashimodoshiModal').style.display = 'none'; }

function openResubmit(j) {
    document.getElementById('rs_jiko_id').value = j.pk_jiko_id;
    document.getElementById('rs_date').value = j.f_date;
    document.getElementById('rs_time').value = j.f_time || '';
    document.getElementById('rs_kubun').value = j.f_kubun;
    document.getElementById('rs_factory').value = j.fk_factory_id || '';
    document.getElementById('rs_place').value = j.f_place_text || '';
    document.getElementById('rs_title').value = j.f_title;
    document.getElementById('rs_detail').value = j.f_detail || '';
    document.getElementById('resubmitModal').style.display = 'flex';
}
function closeResubmit() { document.getElementById('resubmitModal').style.display = 'none'; }
</script>
<?= html_footer() ?>
