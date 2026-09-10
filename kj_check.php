<?php
require_once 'kj_common.php';
$pdo = get_db();
$msg = ''; $msg_type = 'success';

$factories = kj_factories($pdo);
$f_factory = $_POST['fk_factory_id'] ?? $_GET['f_factory'] ?? ($factories[0]['pk_factory_id'] ?? '');
$f_date    = $_POST['f_check_date'] ?? date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $factory_id = $_POST['fk_factory_id'] ?? '';
    $check_date = $_POST['f_check_date'] ?? date('Y-m-d');
    $results    = $_POST['result'] ?? [];   // [setsubi_id => '正常'|'異常']
    $comments   = $_POST['comment'] ?? [];  // [setsubi_id => text]

    if ($factory_id && !empty($results)) {
        try {
            $pdo->beginTransaction();
            $check_id = generate_uuid();
            $pdo->prepare("INSERT INTO t_nichiji_check (pk_check_id,fk_factory_id,f_check_date,fk_tantosha_id,f_biko,f_created_at) VALUES (?,?,?,?,?,NOW())")
                ->execute([$check_id, $factory_id, $check_date, $_SESSION['tantosha_id'], $_POST['f_biko'] ?? '']);

            $stmt_m = $pdo->prepare("INSERT INTO t_nichiji_check_meisai (pk_check_meisai_id,fk_check_id,fk_setsubi_id,f_result,f_comment,f_created_at) VALUES (?,?,?,?,?,NOW())");
            $stmt_f = $pdo->prepare("INSERT INTO t_fugu (pk_fugu_id,fk_factory_id,fk_setsubi_id,fk_check_meisai_id,f_title,f_detail,fk_tantosha_id,f_priority,f_status,f_created_at,f_updated_at) VALUES (?,?,?,?,?,?,?,?,'未着手',NOW(),NOW())");
            $stmt_name = $pdo->prepare("SELECT f_setsubi_name FROM t_setsubi WHERE pk_setsubi_id = ?");

            $abnormal_count = 0;
            foreach ($results as $setsubi_id => $result) {
                $comment = trim($comments[$setsubi_id] ?? '');
                $meisai_id = generate_uuid();
                $stmt_m->execute([$meisai_id, $check_id, $setsubi_id, $result, $comment]);
                if ($result === '異常') {
                    $abnormal_count++;
                    $stmt_name->execute([$setsubi_id]);
                    $sname = $stmt_name->fetchColumn() ?: '設備';
                    $stmt_f->execute([
                        generate_uuid(), $factory_id, $setsubi_id, $meisai_id,
                        "【日次チェック】{$sname}に異常あり",
                        $comment ?: '日次チェックリストで異常が報告されました。',
                        $_SESSION['tantosha_id'], '高'
                    ]);
                }
            }
            $pdo->commit();
            $msg = "チェックを記録しました（異常 {$abnormal_count} 件）。" . ($abnormal_count ? '不具合・行動計画に自動登録しました。' : '');
            $msg_type = $abnormal_count ? 'warning' : 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = 'エラーが発生しました: ' . $e->getMessage(); $msg_type = 'danger';
        }
    } else {
        $msg = '工場を選択し、1件以上点検してください。'; $msg_type = 'danger';
    }
    $f_factory = $factory_id ?: $f_factory;
    $f_date    = $check_date;
}

// 対象設備一覧（日次チェックは設備・機械本体が対象。部品の交換時期は保守スケジュール画面で管理）
$setsubis = [];
if ($f_factory) {
    $stmt = $pdo->prepare("SELECT * FROM t_setsubi WHERE fk_factory_id=? AND f_active='有効' AND fk_parent_setsubi_id IS NULL AND f_setsubi_kubun IN ('設備','機械') ORDER BY f_sort_order, f_setsubi_name");
    $stmt->execute([$f_factory]);
    $setsubis = $stmt->fetchAll();
}

// 直近のチェック履歴（当該工場）
$history = [];
if ($f_factory) {
    $stmt = $pdo->prepare("
        SELECT c.*, t.f_tantosha_name,
          (SELECT COUNT(*) FROM t_nichiji_check_meisai m WHERE m.fk_check_id=c.pk_check_id) AS item_count,
          (SELECT COUNT(*) FROM t_nichiji_check_meisai m WHERE m.fk_check_id=c.pk_check_id AND m.f_result='異常') AS abnormal_count
        FROM t_nichiji_check c
        JOIN t_tantosha t ON c.fk_tantosha_id = t.pk_tantosha_id
        WHERE c.fk_factory_id = ?
        ORDER BY c.f_check_date DESC, c.f_created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$f_factory]);
    $history = $stmt->fetchAll();
}

echo html_header('日次チェックリスト');
echo nav_bar();
?>
<div class="container">
  <div class="page-title">工場メンテナンス管理　｜　日次チェックリスト</div>
  <?= kj_subnav('kj_check.php') ?>

  <?php if($msg): ?><div class="alert alert-<?= $msg_type ?>"><?= h($msg) ?></div><?php endif; ?>

  <form method="get" class="search-area">
    <label>工場</label>
    <select name="f_factory" onchange="this.form.submit()">
      <?php foreach($factories as $f): ?>
      <option value="<?= h($f['pk_factory_id']) ?>" <?= $f_factory===$f['pk_factory_id']?'selected':'' ?>><?= h($f['f_factory_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php if(empty($setsubis)): ?>
  <div class="card"><div class="card-body" style="text-align:center;color:#999;padding:30px">
    この工場には点検対象の設備が登録されていません。先に「設備マスタ」で登録してください。
  </div></div>
  <?php else: ?>
  <form method="post">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="fk_factory_id" value="<?= h($f_factory) ?>">
    <div class="card">
      <div class="card-header">
        日次点検入力
        <input type="date" name="f_check_date" class="form-control" style="max-width:160px" value="<?= h($f_date) ?>">
      </div>
      <div class="card-body" style="padding:0">
        <?php foreach($setsubis as $s): $sid = $s['pk_setsubi_id']; ?>
        <div style="padding:14px 16px;border-bottom:1px solid #e8eef5">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:6px">
            <div>
              <span style="font-weight:700;font-size:14px"><?= h($s['f_setsubi_name']) ?></span>
              <span style="font-size:11px;color:#888;margin-left:6px"><?= h($s['f_setsubi_kubun']) ?><?= $s['f_type_no']?'／'.h($s['f_type_no']):'' ?></span>
            </div>
            <div style="display:flex;gap:14px">
              <label style="display:flex;align-items:center;gap:4px;font-size:13px;cursor:pointer">
                <input type="radio" name="result[<?= h($sid) ?>]" value="正常" checked> ✅ 正常
              </label>
              <label style="display:flex;align-items:center;gap:4px;font-size:13px;cursor:pointer;color:#c62828">
                <input type="radio" name="result[<?= h($sid) ?>]" value="異常" onclick="document.getElementById('cm_<?= h($sid) ?>').style.display='block'"> ⚠️ 異常
              </label>
            </div>
          </div>
          <input type="text" id="cm_<?= h($sid) ?>" name="comment[<?= h($sid) ?>]" class="form-control" placeholder="異常内容を入力してください" style="display:none">
        </div>
        <?php endforeach; ?>
        <div style="padding:14px 16px">
          <div class="form-group" style="margin:0"><label>全体備考</label><input type="text" name="f_biko" class="form-control" placeholder="申し送り事項があれば"></div>
        </div>
      </div>
    </div>
    <button type="submit" class="btn btn-primary btn-block" style="margin-bottom:20px">この内容で記録する</button>
  </form>
  <?php endif; ?>

  <?php if(!empty($history)): ?>
  <div class="card">
    <div class="card-header">直近の点検履歴</div>
    <div class="card-body" style="padding:0">
      <?php foreach($history as $h): ?>
      <div style="padding:10px 16px;border-bottom:1px solid #e8eef5;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px">
        <div>
          <span style="font-weight:600"><?= h(date('Y/m/d', strtotime($h['f_check_date']))) ?></span>
          <span style="font-size:12px;color:#888;margin-left:8px"><?= h($h['f_tantosha_name']) ?></span>
          <span style="font-size:12px;color:#888;margin-left:8px"><?= (int)$h['item_count'] ?>件点検</span>
        </div>
        <div><?= $h['abnormal_count'] > 0 ? "<span class='badge badge-danger'>異常 {$h['abnormal_count']} 件</span>" : "<span class='badge badge-success'>異常なし</span>" ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?= html_footer() ?>
