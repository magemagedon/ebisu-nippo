<?php
require_once 'kj_common.php';
$pdo = get_db();
$msg = ''; $msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $factory_id = $_POST['fk_factory_id'] ?? '';
        $items = $_POST['f_check_item'] ?? [];
        if ($factory_id && !empty(array_filter($items))) {
            try {
                $pdo->beginTransaction();
                $patrol_id = generate_uuid();
                $pdo->prepare("INSERT INTO t_patrol (pk_patrol_id,fk_factory_id,f_patrol_date,fk_tantosha_id,f_status,f_biko,f_created_at,f_updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW())")
                    ->execute([$patrol_id, $factory_id, $_POST['f_patrol_date'] ?: date('Y-m-d'), $_SESSION['tantosha_id'], $_POST['f_status'] ?? '実施済', $_POST['f_biko'] ?? '']);

                $stmt = $pdo->prepare("INSERT INTO t_patrol_meisai (pk_patrol_meisai_id,fk_patrol_id,f_check_item,f_result,f_shiteki_naiyo,f_taisaku,f_taisaku_kigen,f_taisaku_status,f_created_at) VALUES (?,?,?,?,?,?,?,?,NOW())");
                foreach ($items as $i => $item) {
                    if (trim($item) === '') continue;
                    $result = $_POST['f_result'][$i] ?? '良';
                    $stmt->execute([
                        generate_uuid(), $patrol_id, $item, $result,
                        $_POST['f_shiteki_naiyo'][$i] ?? '', $_POST['f_taisaku'][$i] ?? '',
                        $_POST['f_taisaku_kigen'][$i] ?: null,
                        $result === '要改善' ? '未対応' : '完了'
                    ]);
                }
                $pdo->commit();
                $msg = 'パトロール記録を登録しました。';
            } catch (Exception $e) {
                $pdo->rollBack();
                $msg = 'エラーが発生しました: ' . $e->getMessage(); $msg_type = 'danger';
            }
        } else { $msg = '工場と点検項目を1件以上入力してください。'; $msg_type = 'danger'; }

    } elseif ($action === 'taisaku_status') {
        $pdo->prepare("UPDATE t_patrol_meisai SET f_taisaku_status=? WHERE pk_patrol_meisai_id=?")
            ->execute([$_POST['new_status'] ?? '未対応', $_POST['meisai_id'] ?? '']);
        $msg = '対策ステータスを更新しました。';
    }
}

$factories = kj_factories($pdo);
$f_factory = $_GET['f_factory'] ?? '';
$where = ['1=1']; $params = [];
if ($f_factory) { $where[] = 'p.fk_factory_id = ?'; $params[] = $f_factory; }

$stmt = $pdo->prepare("SELECT p.*, f.f_factory_name, t.f_tantosha_name FROM t_patrol p JOIN t_factory f ON p.fk_factory_id=f.pk_factory_id JOIN t_tantosha t ON p.fk_tantosha_id=t.pk_tantosha_id WHERE " . implode(' AND ', $where) . " ORDER BY p.f_patrol_date DESC, p.f_created_at DESC LIMIT 20");
$stmt->execute($params);
$patrols = $stmt->fetchAll();

// 明細を一括取得
$patrol_ids = array_column($patrols, 'pk_patrol_id');
$meisai_by_patrol = [];
if ($patrol_ids) {
    $in = implode(',', array_fill(0, count($patrol_ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM t_patrol_meisai WHERE fk_patrol_id IN ($in) ORDER BY f_created_at");
    $stmt->execute($patrol_ids);
    foreach ($stmt->fetchAll() as $m) $meisai_by_patrol[$m['fk_patrol_id']][] = $m;
}

echo html_header('安全パトロール');
echo nav_bar();
?>
<div class="container">
  <div class="page-title">工場メンテナンス管理　｜　安全パトロール</div>
  <?= kj_subnav('kj_patrol.php') ?>

  <?php if($msg): ?><div class="alert alert-<?= $msg_type ?>"><?= h($msg) ?></div><?php endif; ?>

  <form method="post" id="patrolForm">
    <input type="hidden" name="action" value="save">
    <div class="card">
      <div class="card-header">パトロール　計画・実施記録</div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
          <div class="form-group" style="margin:0">
            <label>工場・拠点 <span style="color:#c62828">*</span></label>
            <select name="fk_factory_id" class="form-control" required>
              <option value="">-- 選択 --</option>
              <?php foreach($factories as $f): ?><option value="<?= h($f['pk_factory_id']) ?>"><?= h($f['f_factory_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0"><label>実施日／予定日</label><input type="date" name="f_patrol_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
          <div class="form-group" style="margin:0">
            <label>ステータス</label>
            <select name="f_status" class="form-control"><option value="実施済">実施済</option><option value="計画">計画（これから実施）</option></select>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">点検項目<button type="button" onclick="addItem()" class="btn btn-blue btn-sm">＋ 項目を追加</button></div>
      <div class="card-body" id="itemList">
        <?php foreach(['通路・避難経路の確保','消火設備の配置・使用可否','保護具の着用状況','安全表示・標識の状態'] as $idx => $default): ?>
        <div class="meisai-row" id="item_<?= $idx ?>">
          <span class="meisai-num"><?= $idx+1 ?></span>
          <?php if($idx>0): ?><button type="button" class="del-btn" onclick="delItem(this)">削除</button><?php endif; ?>
          <div style="padding-left:28px">
            <div class="form-group"><label>点検箇所・項目</label><input type="text" name="f_check_item[]" class="form-control" value="<?= h($default) ?>"></div>
            <div class="form-group">
              <label>結果</label>
              <select name="f_result[]" class="form-control"><option value="良">良</option><option value="要改善">要改善</option></select>
            </div>
            <div class="form-group"><label>発見事項（要改善の場合）</label><input type="text" name="f_shiteki_naiyo[]" class="form-control"></div>
            <div class="meisai-grid">
              <div class="form-group" style="margin:0"><label>対策</label><input type="text" name="f_taisaku[]" class="form-control"></div>
              <div class="form-group" style="margin:0"><label>対策期限</label><input type="date" name="f_taisaku_kigen[]" class="form-control"></div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-group"><label>全体備考</label><input type="text" name="f_biko" class="form-control"></div>
    <button type="submit" class="btn btn-primary btn-block" style="margin-bottom:20px">登録する</button>
  </form>

  <form method="get" class="search-area">
    <label>工場</label>
    <select name="f_factory" onchange="this.form.submit()">
      <option value="">全工場</option>
      <?php foreach($factories as $f): ?><option value="<?= h($f['pk_factory_id']) ?>" <?= $f_factory===$f['pk_factory_id']?'selected':'' ?>><?= h($f['f_factory_name']) ?></option><?php endforeach; ?>
    </select>
  </form>

  <div class="card">
    <div class="card-header">パトロール実施履歴<span style="font-size:12px;font-weight:400"><?= count($patrols) ?>件</span></div>
    <div class="card-body" style="padding:0">
      <?php if(empty($patrols)): ?>
      <div style="text-align:center;color:#999;padding:40px">記録がありません</div>
      <?php else: foreach($patrols as $p): $items = $meisai_by_patrol[$p['pk_patrol_id']] ?? []; ?>
      <div style="padding:12px 16px;border-bottom:1px solid #e8eef5">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;margin-bottom:8px">
          <div>
            <span style="font-weight:700"><?= h(date('Y/m/d', strtotime($p['f_patrol_date']))) ?></span>
            <span style="font-size:12px;color:#888;margin-left:8px">🏭 <?= h($p['f_factory_name']) ?>／👤 <?= h($p['f_tantosha_name']) ?></span>
          </div>
          <?= kj_patrol_status_badge($p['f_status']) ?>
        </div>
        <?php foreach($items as $m): ?>
        <div style="background:#f7f9fc;border:1px solid #e0e8f0;border-radius:6px;padding:8px 12px;margin-bottom:6px;font-size:12px">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap">
            <div><?= kj_result_badge($m['f_result']) ?> <span style="font-weight:600;margin-left:4px"><?= h($m['f_check_item']) ?></span></div>
            <?php if($m['f_result']==='要改善'): ?>
            <div style="display:flex;gap:6px;align-items:center">
              <?= kj_status_badge($m['f_taisaku_status']) ?>
              <div style="display:flex;gap:4px">
                <?php $ns = match($m['f_taisaku_status']){'未対応'=>[['対応中','btn-blue'],['完了','btn-success']],'対応中'=>[['完了','btn-success']],default=>[]}; foreach($ns as [$s,$c]): ?>
                <form method="post" style="display:inline"><input type="hidden" name="action" value="taisaku_status"><input type="hidden" name="meisai_id" value="<?= h($m['pk_patrol_meisai_id']) ?>"><input type="hidden" name="new_status" value="<?= h($s) ?>"><button type="submit" class="btn <?= $c ?> btn-sm"><?= h($s) ?></button></form>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>
          </div>
          <?php if($m['f_shiteki_naiyo']): ?><div style="margin-top:4px;color:#666">発見：<?= h($m['f_shiteki_naiyo']) ?></div><?php endif; ?>
          <?php if($m['f_taisaku']): ?><div style="margin-top:2px;color:#1B3A6B">対策：<?= h($m['f_taisaku']) ?><?= $m['f_taisaku_kigen']?'（期限：'.h(date('Y/m/d',strtotime($m['f_taisaku_kigen']))).'）':'' ?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>
<script>
let itemCount = <?= count(['通路・避難経路の確保','消火設備の配置・使用可否','保護具の着用状況','安全表示・標識の状態']) ?>;
function addItem() {
  const idx = itemCount++;
  const div = document.createElement('div');
  div.className = 'meisai-row'; div.id = 'item_' + idx;
  div.innerHTML = `<span class="meisai-num"></span><button type="button" class="del-btn" onclick="delItem(this)">削除</button>
    <div style="padding-left:28px">
      <div class="form-group"><label>点検箇所・項目</label><input type="text" name="f_check_item[]" class="form-control"></div>
      <div class="form-group"><label>結果</label><select name="f_result[]" class="form-control"><option value="良">良</option><option value="要改善">要改善</option></select></div>
      <div class="form-group"><label>発見事項（要改善の場合）</label><input type="text" name="f_shiteki_naiyo[]" class="form-control"></div>
      <div class="meisai-grid">
        <div class="form-group" style="margin:0"><label>対策</label><input type="text" name="f_taisaku[]" class="form-control"></div>
        <div class="form-group" style="margin:0"><label>対策期限</label><input type="date" name="f_taisaku_kigen[]" class="form-control"></div>
      </div>
    </div>`;
  document.getElementById('itemList').appendChild(div);
  renumber();
}
function delItem(btn) { btn.closest('.meisai-row').remove(); renumber(); }
function renumber() { document.querySelectorAll('#itemList .meisai-num').forEach((el,i)=>el.textContent=i+1); }
</script>
<?= html_footer() ?>
