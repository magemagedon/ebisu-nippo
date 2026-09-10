<?php
require_once 'kj_common.php';
$pdo = get_db();
$is_admin = ($_SESSION['kengen'] === '管理者');
$msg = ''; $msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$is_admin) { header('Location: kj_equipment.php'); exit; }
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['f_setsubi_name'] ?? '');
        $parent_id = ($_POST['fk_parent_setsubi_id'] ?? '') ?: null;

        if ($parent_id) {
            // 部品を追加：所属工場は親設備から自動継承
            $stmt = $pdo->prepare("SELECT fk_factory_id FROM t_setsubi WHERE pk_setsubi_id=?");
            $stmt->execute([$parent_id]);
            $factory_id = $stmt->fetchColumn();
            $kubun = '消耗部品';
        } else {
            $factory_id = $_POST['fk_factory_id'] ?? '';
            $kubun = in_array($_POST['f_setsubi_kubun'] ?? '', ['設備', '機械']) ? $_POST['f_setsubi_kubun'] : '設備';
        }

        if ($name && $factory_id) {
            $pdo->prepare("INSERT INTO t_setsubi (pk_setsubi_id,fk_factory_id,fk_parent_setsubi_id,f_setsubi_name,f_setsubi_kubun,f_type_no,f_setti_date,f_koukan_shuki_days,f_biko,f_sort_order,f_active,f_created_at,f_updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())")
                ->execute([
                    generate_uuid(), $factory_id, $parent_id, $name, $kubun,
                    $_POST['f_type_no'] ?? '', $_POST['f_setti_date'] ?: null,
                    $_POST['f_koukan_shuki_days'] ?: null, $_POST['f_biko'] ?? '', $_POST['f_sort_order'] ?? 0, '有効'
                ]);
            $msg = $parent_id ? '部品を追加しました。' : '設備を追加しました。';
        } else { $msg = '工場・名称は必須です。'; $msg_type = 'danger'; }

    } elseif ($action === 'edit') {
        $parent_id = $_POST['fk_parent_setsubi_id'] ?: null;
        if ($parent_id === $_POST['setsubi_id']) $parent_id = null; // 自己参照を防止

        if ($parent_id) {
            $stmt = $pdo->prepare("SELECT fk_factory_id FROM t_setsubi WHERE pk_setsubi_id=?");
            $stmt->execute([$parent_id]);
            $factory_id = $stmt->fetchColumn() ?: $_POST['fk_factory_id'];
            $kubun = '消耗部品';
        } else {
            $factory_id = $_POST['fk_factory_id'];
            $kubun = $_POST['f_setsubi_kubun'];
        }

        $pdo->prepare("UPDATE t_setsubi SET fk_factory_id=?,fk_parent_setsubi_id=?,f_setsubi_name=?,f_setsubi_kubun=?,f_type_no=?,f_setti_date=?,f_koukan_shuki_days=?,f_biko=?,f_sort_order=?,f_active=?,f_updated_at=NOW() WHERE pk_setsubi_id=?")
            ->execute([
                $factory_id, $parent_id, $_POST['f_setsubi_name'], $kubun,
                $_POST['f_type_no'], $_POST['f_setti_date'] ?: null, $_POST['f_koukan_shuki_days'] ?: null,
                $_POST['f_biko'], $_POST['f_sort_order'] ?? 0, $_POST['f_active'], $_POST['setsubi_id']
            ]);
        $msg = '更新しました。';

    } elseif ($action === 'delete') {
        try {
            $pdo->prepare("DELETE FROM t_setsubi WHERE pk_setsubi_id=?")->execute([$_POST['setsubi_id']]);
            $msg = '削除しました（紐付く部品も削除されます）。';
        } catch (PDOException $e) {
            // 不具合・点検履歴等が紐づいている場合は削除できない仕様（データ保全のため）
            $msg = 'この設備・部品は不具合記録や点検履歴に使われているため削除できません。使わなくなった場合は「編集」から状態を「無効」にしてください。';
            $msg_type = 'danger';
        }

    } elseif ($action === 'assign_parent') {
        $parent_id = $_POST['fk_parent_setsubi_id'] ?? '';
        if ($parent_id && $parent_id !== ($_POST['setsubi_id'] ?? '')) {
            $stmt = $pdo->prepare("SELECT fk_factory_id FROM t_setsubi WHERE pk_setsubi_id=?");
            $stmt->execute([$parent_id]);
            $factory_id = $stmt->fetchColumn();
            $pdo->prepare("UPDATE t_setsubi SET fk_parent_setsubi_id=?, fk_factory_id=COALESCE(?, fk_factory_id), f_updated_at=NOW() WHERE pk_setsubi_id=?")
                ->execute([$parent_id, $factory_id, $_POST['setsubi_id']]);
            $msg = '所属設備を設定しました。';
        } else { $msg = '所属設備を選択してください。'; $msg_type = 'danger'; }
    }
}

$f_factory = $_GET['f_factory'] ?? '';
$factories = kj_factories($pdo);

// 設備・機械（親／トップレベル）
$where = ["s.fk_parent_setsubi_id IS NULL", "s.f_setsubi_kubun IN ('設備','機械')"];
$params = [];
if ($f_factory) { $where[] = 's.fk_factory_id = ?'; $params[] = $f_factory; }
$stmt = $pdo->prepare("SELECT s.*, f.f_factory_name FROM t_setsubi s JOIN t_factory f ON s.fk_factory_id=f.pk_factory_id WHERE " . implode(' AND ', $where) . " ORDER BY f.f_sort_order, s.f_sort_order, s.f_setsubi_name");
$stmt->execute($params);
$units = $stmt->fetchAll();

// 各設備に紐づく部品を一括取得
$unit_ids = array_column($units, 'pk_setsubi_id');
$parts_by_parent = [];
if ($unit_ids) {
    $in = implode(',', array_fill(0, count($unit_ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM t_setsubi WHERE fk_parent_setsubi_id IN ($in) ORDER BY f_sort_order, f_setsubi_name");
    $stmt->execute($unit_ids);
    foreach ($stmt->fetchAll() as $p) $parts_by_parent[$p['fk_parent_setsubi_id']][] = $p;
}

// 未割当の部品（過去データ等、まだ設備に紐付いていない消耗部品）
$owhere = ["s.f_setsubi_kubun = '消耗部品'", 's.fk_parent_setsubi_id IS NULL'];
$oparams = [];
if ($f_factory) { $owhere[] = 's.fk_factory_id = ?'; $oparams[] = $f_factory; }
$stmt = $pdo->prepare("SELECT s.*, f.f_factory_name FROM t_setsubi s JOIN t_factory f ON s.fk_factory_id=f.pk_factory_id WHERE " . implode(' AND ', $owhere) . " ORDER BY s.f_setsubi_name");
$stmt->execute($oparams);
$orphans = $stmt->fetchAll();

echo html_header('設備マスタ');
echo nav_bar();
?>
<div class="container">
  <div class="page-title">工場メンテナンス管理　｜　設備マスタ</div>
  <?= kj_subnav('kj_equipment.php') ?>

  <?php if($msg): ?><div class="alert alert-<?= $msg_type ?>"><?= h($msg) ?></div><?php endif; ?>

  <?php if($is_admin): ?>
  <div class="card">
    <div class="card-header">設備・機械を追加</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="add">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px" class="meisai-grid-3">
          <div class="form-group" style="margin:0">
            <label>工場・拠点 <span style="color:#c62828">*</span></label>
            <select name="fk_factory_id" class="form-control" required>
              <option value="">-- 選択 --</option>
              <?php foreach($factories as $f): ?><option value="<?= h($f['pk_factory_id']) ?>"><?= h($f['f_factory_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0">
            <label>設備名 <span style="color:#c62828">*</span></label>
            <input type="text" name="f_setsubi_name" class="form-control" placeholder="例：破砕機（1号機）" required>
          </div>
          <div class="form-group" style="margin:0">
            <label>区分</label>
            <select name="f_setsubi_kubun" class="form-control"><option value="設備">設備</option><option value="機械">機械</option></select>
          </div>
          <div class="form-group" style="margin:0"><label>型番</label><input type="text" name="f_type_no" class="form-control"></div>
          <div class="form-group" style="margin:0"><label>設置日</label><input type="date" name="f_setti_date" class="form-control"></div>
          <div class="form-group" style="margin:0"><label>本体の保守周期（日数・任意）</label><input type="number" name="f_koukan_shuki_days" class="form-control" placeholder="オーバーホール等"></div>
          <div class="form-group" style="grid-column:span 2;margin:0"><label>備考</label><input type="text" name="f_biko" class="form-control"></div>
        </div>
        <div style="text-align:right;margin-top:12px"><button type="submit" class="btn btn-primary">追加する</button></div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <form method="get" class="search-area">
    <label>工場・拠点</label>
    <select name="f_factory" onchange="this.form.submit()">
      <option value="">全工場</option>
      <?php foreach($factories as $f): ?>
      <option value="<?= h($f['pk_factory_id']) ?>" <?= $f_factory===$f['pk_factory_id']?'selected':'' ?>><?= h($f['f_factory_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php if(empty($units)): ?>
  <div class="card"><div class="card-body" style="text-align:center;color:#999;padding:30px">設備が登録されていません</div></div>
  <?php endif; ?>

  <?php foreach($units as $u):
    $uid = $u['pk_setsubi_id'];
    $parts = $parts_by_parent[$uid] ?? [];
    $unit_next = kj_next_koukan_date($pdo, $u);
  ?>
  <div class="card">
    <div class="card-header" style="<?= $u['f_active']==='無効'?'opacity:.6':'' ?>">
      <div>
        ⚙️ <?= h($u['f_setsubi_name']) ?>
        <span style="font-size:11px;font-weight:400;opacity:.85">　<?= h($u['f_factory_name']) ?>／<?= h($u['f_setsubi_kubun']) ?><?= $u['f_type_no']?'／'.h($u['f_type_no']):'' ?></span>
      </div>
      <?php if($is_admin): ?>
      <div>
        <button type="button" class="btn btn-gray btn-sm" onclick='showEdit(<?= json_encode($u, JSON_UNESCAPED_UNICODE|JSON_HEX_APOS) ?>)'>編集</button>
        <form method="post" style="display:inline" onsubmit="return confirm('この設備を削除しますか？紐づく部品も削除されます。')">
          <input type="hidden" name="action" value="delete"><input type="hidden" name="setsubi_id" value="<?= h($uid) ?>">
          <button type="submit" class="btn btn-danger btn-sm">削除</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <?php if($u['f_koukan_shuki_days']): [$label,$color] = kj_days_label($unit_next ? floor((strtotime($unit_next)-strtotime(date('Y-m-d')))/86400) : null); ?>
      <div style="font-size:12px;color:#666;margin-bottom:10px">本体保守周期：<?= (int)$u['f_koukan_shuki_days'] ?>日／次回：<?= $unit_next?h(date('Y/m/d',strtotime($unit_next))):'―' ?> <span style="color:<?= $color ?>;font-weight:700"><?= $unit_next?$label:'' ?></span></div>
      <?php endif; ?>

      <div style="font-size:12px;font-weight:700;color:#1B3A6B;margin-bottom:8px">部品リスト（<?= count($parts) ?>件）</div>
      <?php if(!empty($parts)): ?>
      <div class="table-wrap"><table>
        <thead><tr><th>部品名</th><th class="pc-only">型番</th><th>次回交換予定</th><th style="text-align:center">状態</th><?php if($is_admin): ?><th style="text-align:center">操作</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach($parts as $p):
          $pnext = kj_next_koukan_date($pdo, $p);
          $pdays = $pnext ? floor((strtotime($pnext)-strtotime(date('Y-m-d')))/86400) : null;
          [$plabel,$pcolor] = kj_days_label($pdays);
        ?>
        <tr style="<?= $p['f_active']==='無効'?'opacity:.5':'' ?>">
          <td style="font-weight:600"><?= h($p['f_setsubi_name']) ?></td>
          <td class="pc-only"><?= h($p['f_type_no']) ?></td>
          <td>
            <?php if($pnext): ?>
            <span style="font-size:12px"><?= h(date('Y/m/d', strtotime($pnext))) ?></span>
            <span style="color:<?= $pcolor ?>;font-weight:700;font-size:12px;margin-left:4px"><?= $plabel ?></span>
            <?php else: ?><span style="color:#999;font-size:12px">周期未設定</span><?php endif; ?>
          </td>
          <td style="text-align:center"><span class="badge <?= $p['f_active']==='有効'?'badge-success':'badge-danger' ?>"><?= h($p['f_active']) ?></span></td>
          <?php if($is_admin): ?>
          <td style="text-align:center;white-space:nowrap">
            <button type="button" class="btn btn-blue btn-sm" onclick='showEdit(<?= json_encode($p, JSON_UNESCAPED_UNICODE|JSON_HEX_APOS) ?>)'>編集</button>
            <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？')">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="setsubi_id" value="<?= h($p['pk_setsubi_id']) ?>">
              <button type="submit" class="btn btn-danger btn-sm">削除</button>
            </form>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php else: ?>
      <div style="font-size:12px;color:#999;margin-bottom:10px">部品はまだ登録されていません</div>
      <?php endif; ?>

      <?php if($is_admin): ?>
      <form method="post" style="margin-top:10px;padding-top:10px;border-top:1px dashed #e0e8f0">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="fk_parent_setsubi_id" value="<?= h($uid) ?>">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:8px;align-items:end" class="meisai-grid">
          <div class="form-group" style="margin:0"><label>部品名</label><input type="text" name="f_setsubi_name" class="form-control" placeholder="例：破砕刃" required></div>
          <div class="form-group" style="margin:0"><label>型番</label><input type="text" name="f_type_no" class="form-control"></div>
          <div class="form-group" style="margin:0"><label>設置／前回交換日</label><input type="date" name="f_setti_date" class="form-control"></div>
          <div class="form-group" style="margin:0"><label>交換周期（日数）</label><input type="number" name="f_koukan_shuki_days" class="form-control" placeholder="例：90"></div>
          <button type="submit" class="btn btn-blue btn-sm">＋ 部品を追加</button>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <?php if(!empty($orphans)): ?>
  <div class="card" style="border:1px solid #f0d080">
    <div class="card-header" style="background:#e65100">未割当の部品<span style="font-size:12px;font-weight:400"><?= count($orphans) ?>件（所属設備を設定してください）</span></div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap"><table>
        <thead><tr><th>工場</th><th>部品名</th><th>型番</th><th>所属設備を設定</th></tr></thead>
        <tbody>
        <?php foreach($orphans as $o):
          $candidates = array_filter($units, fn($u) => $u['fk_factory_id'] === $o['fk_factory_id']);
        ?>
        <tr>
          <td><?= h($o['f_factory_name']) ?></td>
          <td style="font-weight:600"><?= h($o['f_setsubi_name']) ?></td>
          <td><?= h($o['f_type_no']) ?></td>
          <td>
            <?php if($is_admin): ?>
            <form method="post" style="display:flex;gap:6px">
              <input type="hidden" name="action" value="assign_parent">
              <input type="hidden" name="setsubi_id" value="<?= h($o['pk_setsubi_id']) ?>">
              <select name="fk_parent_setsubi_id" class="form-control" style="max-width:220px" required>
                <option value="">-- 設備を選択 --</option>
                <?php foreach($candidates as $c): ?><option value="<?= h($c['pk_setsubi_id']) ?>"><?= h($c['f_setsubi_name']) ?></option><?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-primary btn-sm">設定</button>
            </form>
            <?php else: ?>―<?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php if($is_admin): ?>
<div id="editModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:8px;padding:24px;width:480px;max-width:95%;max-height:90vh;overflow-y:auto">
    <div style="font-weight:700;font-size:15px;margin-bottom:16px;color:#1B3A6B">編集</div>
    <form method="post">
      <input type="hidden" name="action" value="edit"><input type="hidden" name="setsubi_id" id="e_id">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="form-group" style="grid-column:1/-1;margin:0">
          <label>工場・拠点</label>
          <select name="fk_factory_id" id="e_factory" class="form-control">
            <?php foreach($factories as $f): ?><option value="<?= h($f['pk_factory_id']) ?>"><?= h($f['f_factory_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="grid-column:1/-1;margin:0">
          <label>所属設備（空欄＝この設備自体がトップレベル）</label>
          <select name="fk_parent_setsubi_id" id="e_parent" class="form-control">
            <option value="">-- トップレベル（設備・機械） --</option>
            <?php foreach($units as $u): ?><option value="<?= h($u['pk_setsubi_id']) ?>"><?= h($u['f_setsubi_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="grid-column:1/-1;margin:0"><label>名称</label><input type="text" name="f_setsubi_name" id="e_name" class="form-control"></div>
        <div class="form-group" style="margin:0">
          <label>区分（トップレベルの場合のみ有効）</label>
          <select name="f_setsubi_kubun" id="e_kubun" class="form-control"><option value="設備">設備</option><option value="機械">機械</option><option value="消耗部品">消耗部品</option></select>
        </div>
        <div class="form-group" style="margin:0"><label>型番</label><input type="text" name="f_type_no" id="e_type" class="form-control"></div>
        <div class="form-group" style="margin:0"><label>設置日／前回交換日</label><input type="date" name="f_setti_date" id="e_setti" class="form-control"></div>
        <div class="form-group" style="margin:0"><label>交換・保守周期（日数）</label><input type="number" name="f_koukan_shuki_days" id="e_shuki" class="form-control"></div>
        <div class="form-group" style="margin:0"><label>表示順</label><input type="number" name="f_sort_order" id="e_sort" class="form-control"></div>
        <div class="form-group" style="margin:0"><label>状態</label><select name="f_active" id="e_active" class="form-control"><option>有効</option><option>無効</option></select></div>
        <div class="form-group" style="grid-column:1/-1;margin:0"><label>備考</label><input type="text" name="f_biko" id="e_biko" class="form-control"></div>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
        <button type="button" class="btn btn-gray" onclick="document.getElementById('editModal').style.display='none'">キャンセル</button>
        <button type="submit" class="btn btn-primary">更新する</button>
      </div>
    </form>
  </div>
</div>
<script>
function showEdit(s) {
  document.getElementById('e_id').value = s.pk_setsubi_id;
  document.getElementById('e_factory').value = s.fk_factory_id;
  document.getElementById('e_parent').value = s.fk_parent_setsubi_id || '';
  document.getElementById('e_name').value = s.f_setsubi_name;
  document.getElementById('e_kubun').value = s.f_setsubi_kubun;
  document.getElementById('e_type').value = s.f_type_no || '';
  document.getElementById('e_setti').value = s.f_setti_date || '';
  document.getElementById('e_shuki').value = s.f_koukan_shuki_days || '';
  document.getElementById('e_sort').value = s.f_sort_order || 0;
  document.getElementById('e_active').value = s.f_active;
  document.getElementById('e_biko').value = s.f_biko || '';
  document.getElementById('editModal').style.display = 'flex';
}
document.getElementById('editModal').addEventListener('click', function(e){ if(e.target===this) this.style.display='none'; });
</script>
<?php endif; ?>
<?= html_footer() ?>
