<?php
require_once 'kj_common.php';
$pdo = get_db();
$msg = ''; $msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title = trim($_POST['f_title'] ?? '');
        if ($title && !empty($_POST['fk_factory_id'])) {
            $factory_id = $_POST['fk_factory_id'];
            // 担当者が明示指定されていなければ窓口担当を自動アサイン（要件4.4-3）
            $assign_id = $_POST['fk_tantosha_id'] ?: null;
            $notify = kj_resolve_notify_target($pdo, $factory_id, $_SESSION['tantosha_id']);
            if (!$assign_id) $assign_id = $notify['tantosha_id'];

            $pdo->prepare("INSERT INTO t_fugu (pk_fugu_id,fk_factory_id,fk_setsubi_id,f_title,f_detail,f_taiou_keikaku,fk_tantosha_id,f_kigen_date,f_priority,f_status,f_created_at,f_updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,'未着手',NOW(),NOW())")
                ->execute([
                    generate_uuid(), $factory_id, $_POST['fk_setsubi_id'] ?: null, $title,
                    $_POST['f_detail'] ?? '', $_POST['f_taiou_keikaku'] ?? '',
                    $assign_id ?: $_SESSION['tantosha_id'], $_POST['f_kigen_date'] ?: null,
                    $_POST['f_priority'] ?? '中'
                ]);
            $msg = '不具合・行動計画を登録しました。';

            // 明示的に自分以外を担当に割り当てた場合、またはフォールバック先メールがある場合は通知
            $mail_to = $notify['email'];
            if ($mail_to) {
                $fn = $pdo->prepare("SELECT f_factory_name FROM t_factory WHERE pk_factory_id=?"); $fn->execute([$factory_id]);
                kj_send_mail($mail_to, "【工場管理】不具合・行動計画：{$title}",
                    "{$fn->fetchColumn()}にて不具合・行動計画が登録されました。\n\nタイトル：{$title}\n詳細：" . ($_POST['f_detail'] ?: '（なし）') .
                    "\n期限：" . ($_POST['f_kigen_date'] ?: '未設定') .
                    "\n\n工場メンテナンス管理システムの「不具合・行動計画」から対応状況を更新してください。\nhttp://arsystem.jp/ebisu/kj_issue.php");
                $msg .= '（窓口担当へ通知しました）';
            }
        } else { $msg = '工場とタイトルは必須です。'; $msg_type = 'danger'; }

    } elseif ($action === 'status') {
        $pdo->prepare("UPDATE t_fugu SET f_status=?, f_updated_at=NOW() WHERE pk_fugu_id=?")
            ->execute([$_POST['new_status'] ?? '未着手', $_POST['fugu_id'] ?? '']);
        $msg = 'ステータスを更新しました。';

    } elseif ($action === 'plan') {
        $pdo->prepare("UPDATE t_fugu SET f_taiou_keikaku=?, f_kigen_date=?, fk_tantosha_id=?, f_updated_at=NOW() WHERE pk_fugu_id=?")
            ->execute([$_POST['f_taiou_keikaku'] ?? '', $_POST['f_kigen_date'] ?: null, $_POST['fk_tantosha_id'] ?: $_SESSION['tantosha_id'], $_POST['fugu_id'] ?? '']);
        $msg = '対応計画を更新しました。';

    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM t_fugu WHERE pk_fugu_id=?")->execute([$_POST['fugu_id'] ?? '']);
        $msg = '削除しました。';
    }
}

$f_factory = $_GET['f_factory'] ?? '';
$f_status  = $_GET['f_status']  ?? '';
$where = ['1=1']; $params = [];
if ($f_factory) { $where[] = 'g.fk_factory_id = ?'; $params[] = $f_factory; }
if ($f_status)  { $where[] = 'g.f_status = ?'; $params[] = $f_status; }
else            { $where[] = "g.f_status != '完了'"; }

$stmt = $pdo->prepare("
    SELECT g.*, f.f_factory_name, s.f_setsubi_name, sp.f_setsubi_name AS setsubi_parent_name, t.f_tantosha_name, t.f_tel AS tantosha_tel,
           DATEDIFF(g.f_kigen_date, CURDATE()) AS days_left
    FROM t_fugu g
    JOIN t_factory f ON g.fk_factory_id = f.pk_factory_id
    LEFT JOIN t_setsubi s ON g.fk_setsubi_id = s.pk_setsubi_id
    LEFT JOIN t_setsubi sp ON s.fk_parent_setsubi_id = sp.pk_setsubi_id
    JOIN t_tantosha t ON g.fk_tantosha_id = t.pk_tantosha_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY FIELD(g.f_priority,'高','中','低'), g.f_kigen_date IS NULL, g.f_kigen_date ASC
");
$stmt->execute($params);
$list = $stmt->fetchAll();

$factories = kj_factories($pdo);
$tantoshas = $pdo->query("SELECT * FROM t_tantosha WHERE f_zaiseki_flag='有効' ORDER BY f_tantosha_name")->fetchAll();
$setsubis  = $pdo->query("
    SELECT s.pk_setsubi_id, s.fk_factory_id, s.f_setsubi_name, p.f_setsubi_name AS parent_name
    FROM t_setsubi s LEFT JOIN t_setsubi p ON s.fk_parent_setsubi_id = p.pk_setsubi_id
    ORDER BY p.f_setsubi_name IS NOT NULL, s.f_setsubi_name
")->fetchAll();

echo html_header('不具合・行動計画');
echo nav_bar();
?>
<div class="container">
  <div class="page-title">工場メンテナンス管理　｜　不具合・行動計画</div>
  <?= kj_subnav('kj_issue.php') ?>

  <?php if($msg): ?><div class="alert alert-<?= $msg_type ?>"><?= h($msg) ?></div><?php endif; ?>

  <div class="card">
    <div class="card-header">不具合・行動計画を登録</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="form-group">
          <label>タイトル <span style="color:#c62828">*</span></label>
          <input type="text" name="f_title" class="form-control" placeholder="例：破砕機1号機　異音発生" required>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="form-group" style="margin:0">
            <label>工場・拠点 <span style="color:#c62828">*</span></label>
            <select name="fk_factory_id" class="form-control" id="issueFactory" onchange="filterSetsubi()" required>
              <option value="">-- 選択 --</option>
              <?php foreach($factories as $f): ?><option value="<?= h($f['pk_factory_id']) ?>"><?= h($f['f_factory_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0">
            <label>対象設備（任意）</label>
            <select name="fk_setsubi_id" id="issueSetsubi" class="form-control"><option value="">-- なし --</option></select>
          </div>
        </div>
        <div class="form-group"><label>不具合の詳細</label><textarea name="f_detail" class="form-control" rows="2"></textarea></div>
        <div class="form-group"><label>対応計画</label><textarea name="f_taiou_keikaku" class="form-control" rows="2" placeholder="対応方針・手順など"></textarea></div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
          <div class="form-group" style="margin:0">
            <label>担当者<span style="font-size:10px;color:#888;font-weight:400">（工場選択時に窓口担当を自動セット・変更可）</span></label>
            <select name="fk_tantosha_id" id="issueTantosha" class="form-control">
              <option value="" selected>-- 窓口担当を自動アサイン --</option>
              <?php foreach($tantoshas as $t): ?><option value="<?= h($t['pk_tantosha_id']) ?>"><?= h($t['f_tantosha_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0"><label>期限</label><input type="date" name="f_kigen_date" class="form-control"></div>
          <div class="form-group" style="margin:0">
            <label>優先度</label>
            <select name="f_priority" class="form-control">
              <option value="高">🔴 高</option><option value="中" selected>🟡 中</option><option value="低">🟢 低</option>
            </select>
          </div>
        </div>
        <div style="text-align:right;margin-top:12px"><button type="submit" class="btn btn-primary">登録する</button></div>
      </form>
    </div>
  </div>

  <form method="get" class="search-area">
    <label>工場</label>
    <select name="f_factory"><option value="">全工場</option><?php foreach($factories as $f): ?><option value="<?= h($f['pk_factory_id']) ?>" <?= $f_factory===$f['pk_factory_id']?'selected':'' ?>><?= h($f['f_factory_name']) ?></option><?php endforeach; ?></select>
    <label>ステータス</label>
    <select name="f_status">
      <option value="">未完了のみ</option>
      <option value="未着手" <?= $f_status==='未着手'?'selected':'' ?>>未着手</option>
      <option value="対応中" <?= $f_status==='対応中'?'selected':'' ?>>対応中</option>
      <option value="完了" <?= $f_status==='完了'?'selected':'' ?>>完了</option>
    </select>
    <button type="submit" class="btn btn-blue btn-sm">絞り込み</button>
    <a href="kj_issue.php" class="btn btn-gray btn-sm">リセット</a>
  </form>

  <div class="card">
    <div class="card-header">不具合・行動計画一覧<span style="font-size:12px;font-weight:400"><?= count($list) ?>件</span></div>
    <div class="card-body" style="padding:0">
      <?php if(empty($list)): ?>
      <div style="text-align:center;color:#999;padding:40px">対象の不具合はありません</div>
      <?php else: foreach($list as $i => $g):
        $dl = $g['days_left'];
        [$label, $color] = kj_days_label($dl);
        $is_overdue = ($dl !== null && (int)$dl < 0 && $g['f_status'] !== '完了');
        $row_bg = $g['f_status']==='完了' ? '#f5f5f5' : ($i%2===0?'#fff':'#f7f9fc');
      ?>
      <div style="padding:12px 16px;border-bottom:1px solid #e8eef5;background:<?= $row_bg ?>;<?= $is_overdue?'border-left:3px solid #c62828':'' ?>">
        <div style="display:flex;align-items:flex-start;gap:10px;flex-wrap:wrap">
          <?= kj_priority_badge($g['f_priority']) ?>
          <div style="flex:1;min-width:200px">
            <div style="font-weight:600;font-size:14px;<?= $g['f_status']==='完了'?'text-decoration:line-through;color:#999':'' ?>"><?= h($g['f_title']) ?></div>
            <div style="font-size:12px;color:#666;margin-top:2px">
              🏭 <?= h($g['f_factory_name']) ?><?= $g['f_setsubi_name'] ? '／⚙️ '.($g['setsubi_parent_name'] ? h($g['setsubi_parent_name']).' ＞ ' : '').h($g['f_setsubi_name']) : '' ?>
            </div>
            <?php if($g['f_detail']): ?><div style="font-size:12px;color:#666;margin-top:4px"><?= h($g['f_detail']) ?></div><?php endif; ?>
            <?php if($g['f_taiou_keikaku']): ?><div style="font-size:12px;color:#1B3A6B;background:#EEF3FA;border-radius:4px;padding:4px 8px;margin-top:6px">対応計画：<?= h($g['f_taiou_keikaku']) ?></div><?php endif; ?>
            <div style="display:flex;gap:10px;margin-top:6px;font-size:11px;color:#888;flex-wrap:wrap;align-items:center">
              <span>👤 <?= h($g['f_tantosha_name']) ?></span>
              <?php if($g['f_status'] !== '完了'): ?><?= kj_elapsed_badge($g['f_created_at']) ?><?php endif; ?>
              <?php if($g['f_kigen_date']): ?>
              <span style="color:<?= $color ?>;font-weight:600">📅 <?= h(date('Y/m/d', strtotime($g['f_kigen_date']))) ?>（<?= $label ?>）</span>
              <?php endif; ?>
              <?= kj_tel_link($g['tantosha_tel']) ?>
            </div>
          </div>
          <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
            <?= kj_status_badge($g['f_status']) ?>
            <div style="display:flex;gap:4px">
              <?php
              $next = match($g['f_status']) {
                '未着手' => [['対応中','btn-blue'],['完了','btn-success']],
                '対応中' => [['完了','btn-success'],['未着手','btn-gray']],
                '完了'   => [['未着手','btn-gray']],
                default   => [],
              };
              foreach($next as [$ns, $nc]): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="action" value="status"><input type="hidden" name="fugu_id" value="<?= h($g['pk_fugu_id']) ?>"><input type="hidden" name="new_status" value="<?= h($ns) ?>">
                <button type="submit" class="btn <?= $nc ?> btn-sm"><?= h($ns) ?></button>
              </form>
              <?php endforeach; ?>
              <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？')">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="fugu_id" value="<?= h($g['pk_fugu_id']) ?>">
                <button type="submit" class="btn btn-danger btn-sm">削除</button>
              </form>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>
<script>
const setsubiData = <?= json_encode($setsubis, JSON_UNESCAPED_UNICODE) ?>;
const madoguchiData = <?= json_encode(array_column($factories, 'fk_madoguchi_tantosha_id', 'pk_factory_id'), JSON_UNESCAPED_UNICODE) ?>;
function filterSetsubi() {
  const fid = document.getElementById('issueFactory').value;
  const sel = document.getElementById('issueSetsubi');
  sel.innerHTML = '<option value="">-- なし --</option>';
  setsubiData.filter(s => s.fk_factory_id === fid).forEach(s => {
    const opt = document.createElement('option');
    opt.value = s.pk_setsubi_id; opt.textContent = s.parent_name ? (s.parent_name + ' ＞ ' + s.f_setsubi_name) : s.f_setsubi_name;
    sel.appendChild(opt);
  });
  // 担当者が未選択（自動アサイン）のままなら、この工場に窓口担当が設定されているかをセレクトの見た目で示す
  const tantoshaSel = document.getElementById('issueTantosha');
  if (tantoshaSel.value === '') {
    const mid = madoguchiData[fid];
    tantoshaSel.querySelector('option[value=""]').textContent = mid
      ? '-- 窓口担当を自動アサイン（設定あり） --'
      : '-- 窓口担当を自動アサイン（未設定→自分が担当に） --';
  }
}
</script>
<?= html_footer() ?>
