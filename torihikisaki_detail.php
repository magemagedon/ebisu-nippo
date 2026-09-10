<?php
require_once 'db.php';
require_once 'common.php';
if ($_SESSION['kengen'] !== '管理者') { header('Location: index.php'); exit; }

$pdo = get_db();
$id  = $_GET['id'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM t_torihikisaki WHERE pk_torihikisaki_id = ?");
$stmt->execute([$id]);
$tori = $stmt->fetch();
if (!$tori) { header('Location: master.php?tab=torihikisaki'); exit; }

$msg = ''; $msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';
    $action  = $_POST['action'] ?? '';

    // ---- 支店・事業所 ----
    if ($section === 'kyoten') {
        if ($action === 'add') {
            $pdo->prepare("INSERT INTO t_torihikisaki_kyoten (pk_kyoten_id,fk_torihikisaki_id,f_kyoten_name,f_kyoten_kana,f_zip,f_address,f_tel,f_fax,f_map_url,f_biko,f_sort_order,f_active,f_created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())")
                ->execute([generate_uuid(),$id,$_POST['f_kyoten_name'],$_POST['f_kyoten_kana']??'',$_POST['f_zip']??'',$_POST['f_address']??'',$_POST['f_tel']??'',$_POST['f_fax']??'',$_POST['f_map_url']??'',$_POST['f_biko']??'',$_POST['f_sort_order']??0,'有効']);
            $msg = '支店・事業所を追加しました。';
        } elseif ($action === 'edit') {
            $pdo->prepare("UPDATE t_torihikisaki_kyoten SET f_kyoten_name=?,f_kyoten_kana=?,f_zip=?,f_address=?,f_tel=?,f_fax=?,f_map_url=?,f_biko=?,f_sort_order=?,f_active=? WHERE pk_kyoten_id=?")
                ->execute([$_POST['f_kyoten_name'],$_POST['f_kyoten_kana']??'',$_POST['f_zip']??'',$_POST['f_address']??'',$_POST['f_tel']??'',$_POST['f_fax']??'',$_POST['f_map_url']??'',$_POST['f_biko']??'',$_POST['f_sort_order']??0,$_POST['f_active']??'有効',$_POST['kyoten_id']]);
            $msg = '支店・事業所を更新しました。';
        } elseif ($action === 'delete') {
            $pdo->prepare("DELETE FROM t_torihikisaki_kyoten WHERE pk_kyoten_id=?")->execute([$_POST['kyoten_id']]);
            $msg = '削除しました。';
        }
    }

    // ---- 部署 ----
    if ($section === 'busho') {
        if ($action === 'add') {
            $pdo->prepare("INSERT INTO t_torihikisaki_busho (pk_busho_id,fk_torihikisaki_id,fk_kyoten_id,f_busho_name,f_tel,f_biko,f_sort_order,f_active,f_created_at) VALUES (?,?,?,?,?,?,?,?,NOW())")
                ->execute([generate_uuid(),$id,$_POST['fk_kyoten_id']?:null,$_POST['f_busho_name'],$_POST['f_tel']??'',$_POST['f_biko']??'',$_POST['f_sort_order']??0,'有効']);
            $msg = '部署を追加しました。';
        } elseif ($action === 'edit') {
            $pdo->prepare("UPDATE t_torihikisaki_busho SET fk_kyoten_id=?,f_busho_name=?,f_tel=?,f_biko=?,f_sort_order=?,f_active=? WHERE pk_busho_id=?")
                ->execute([$_POST['fk_kyoten_id']?:null,$_POST['f_busho_name'],$_POST['f_tel']??'',$_POST['f_biko']??'',$_POST['f_sort_order']??0,$_POST['f_active']??'有効',$_POST['busho_id']]);
            $msg = '部署を更新しました。';
        } elseif ($action === 'delete') {
            $pdo->prepare("DELETE FROM t_torihikisaki_busho WHERE pk_busho_id=?")->execute([$_POST['busho_id']]);
            $msg = '削除しました。';
        }
    }

    // ---- 先方担当者 ----
    if ($section === 'tantosha') {
        if ($action === 'add') {
            if (($_POST['f_main_flag'] ?? '') === '1') {
                $pdo->prepare("UPDATE t_saki_tantosha SET f_main_flag=0 WHERE fk_torihikisaki_id=?")->execute([$id]);
            }
            $pdo->prepare("INSERT INTO t_saki_tantosha (pk_saki_tantosha_id,fk_torihikisaki_id,fk_kyoten_id,fk_busho_id,f_name,f_kana,f_yakushoku,f_tel,f_mobile,f_email,f_main_flag,f_biko,f_sort_order,f_active,f_created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())")
                ->execute([generate_uuid(),$id,$_POST['fk_kyoten_id']?:null,$_POST['fk_busho_id']?:null,$_POST['f_name'],$_POST['f_kana']??'',$_POST['f_yakushoku']??'',$_POST['f_tel']??'',$_POST['f_mobile']??'',$_POST['f_email']??'',($_POST['f_main_flag']??'')==='1'?1:0,$_POST['f_biko']??'',$_POST['f_sort_order']??0,'有効']);
            $msg = '先方担当者を追加しました。';
        } elseif ($action === 'edit') {
            if (($_POST['f_main_flag'] ?? '') === '1') {
                $pdo->prepare("UPDATE t_saki_tantosha SET f_main_flag=0 WHERE fk_torihikisaki_id=? AND pk_saki_tantosha_id<>?")->execute([$id, $_POST['saki_id']]);
            }
            $pdo->prepare("UPDATE t_saki_tantosha SET fk_kyoten_id=?,fk_busho_id=?,f_name=?,f_kana=?,f_yakushoku=?,f_tel=?,f_mobile=?,f_email=?,f_main_flag=?,f_biko=?,f_sort_order=?,f_active=? WHERE pk_saki_tantosha_id=?")
                ->execute([$_POST['fk_kyoten_id']?:null,$_POST['fk_busho_id']?:null,$_POST['f_name'],$_POST['f_kana']??'',$_POST['f_yakushoku']??'',$_POST['f_tel']??'',$_POST['f_mobile']??'',$_POST['f_email']??'',($_POST['f_main_flag']??'')==='1'?1:0,$_POST['f_biko']??'',$_POST['f_sort_order']??0,$_POST['f_active']??'有効',$_POST['saki_id']]);
            $msg = '先方担当者を更新しました。';
        } elseif ($action === 'delete') {
            $pdo->prepare("DELETE FROM t_saki_tantosha WHERE pk_saki_tantosha_id=?")->execute([$_POST['saki_id']]);
            $msg = '削除しました。';
        }
    }

    // ---- 自社担当割当 ----
    if ($section === 'assign') {
        if ($action === 'add') {
            try {
                $pdo->prepare("INSERT INTO t_tantosha_torihikisaki (pk_assign_id,fk_tantosha_id,fk_torihikisaki_id,fk_kyoten_id,fk_busho_id,f_role,f_biko,f_created_at) VALUES (?,?,?,?,?,?,?,NOW())")
                    ->execute([generate_uuid(),$_POST['fk_tantosha_id'],$id,$_POST['fk_kyoten_id']?:null,$_POST['fk_busho_id']?:null,$_POST['f_role']??'メイン',$_POST['f_biko']??'']);
                $msg = '自社担当を追加しました。';
            } catch (Exception $e) { $msg = 'すでに同じ担当が登録されています。'; $msg_type='danger'; }
        } elseif ($action === 'delete') {
            $pdo->prepare("DELETE FROM t_tantosha_torihikisaki WHERE pk_assign_id=?")->execute([$_POST['assign_id']]);
            $msg = '削除しました。';
        }
    }

    // ---- 取引条件 ----
    if ($section === 'joken' && $action === 'save') {
        $exists = $pdo->prepare("SELECT COUNT(*) FROM t_torihiki_joken WHERE fk_torihikisaki_id=?");
        $exists->execute([$id]);
        if ($exists->fetchColumn()) {
            $pdo->prepare("UPDATE t_torihiki_joken SET f_kaishu_frequency=?,f_payment_site=?,f_payment_method=?,f_contract_date=?,f_biko=?,f_updated_at=NOW() WHERE fk_torihikisaki_id=?")
                ->execute([$_POST['f_kaishu_frequency'],$_POST['f_payment_site'],$_POST['f_payment_method'],$_POST['f_contract_date']?:null,$_POST['f_biko'],$id]);
        } else {
            $pdo->prepare("INSERT INTO t_torihiki_joken (pk_joken_id,fk_torihikisaki_id,f_kaishu_frequency,f_payment_site,f_payment_method,f_contract_date,f_biko) VALUES (?,?,?,?,?,?,?)")
                ->execute([generate_uuid(),$id,$_POST['f_kaishu_frequency'],$_POST['f_payment_site'],$_POST['f_payment_method'],$_POST['f_contract_date']?:null,$_POST['f_biko']]);
        }
        $msg = '取引条件を保存しました。';
    }

    // 再読込（フォーム再送信防止）
    header('Location: torihikisaki_detail.php?id=' . urlencode($id) . '&msg=' . urlencode($msg) . '&msg_type=' . $msg_type);
    exit;
}

if (isset($_GET['msg'])) { $msg = $_GET['msg']; $msg_type = $_GET['msg_type'] ?? 'success'; }

$kyotens   = $pdo->prepare("SELECT * FROM t_torihikisaki_kyoten WHERE fk_torihikisaki_id=? ORDER BY f_sort_order, f_kyoten_name");
$kyotens->execute([$id]); $kyotens = $kyotens->fetchAll();

$bushos    = $pdo->prepare("SELECT * FROM t_torihikisaki_busho WHERE fk_torihikisaki_id=? ORDER BY f_sort_order, f_busho_name");
$bushos->execute([$id]); $bushos = $bushos->fetchAll();

$tantoshas = $pdo->prepare("SELECT * FROM t_saki_tantosha WHERE fk_torihikisaki_id=? ORDER BY f_main_flag DESC, f_sort_order, f_name");
$tantoshas->execute([$id]); $tantoshas = $tantoshas->fetchAll();

$assigns = $pdo->prepare("
    SELECT a.*, t.f_tantosha_name, k.f_kyoten_name, b.f_busho_name
    FROM t_tantosha_torihikisaki a
    JOIN t_tantosha t ON a.fk_tantosha_id = t.pk_tantosha_id
    LEFT JOIN t_torihikisaki_kyoten k ON a.fk_kyoten_id = k.pk_kyoten_id
    LEFT JOIN t_torihikisaki_busho b ON a.fk_busho_id = b.pk_busho_id
    WHERE a.fk_torihikisaki_id = ?
    ORDER BY a.f_role, t.f_tantosha_name
");
$assigns->execute([$id]); $assigns = $assigns->fetchAll();

$joken = $pdo->prepare("SELECT * FROM t_torihiki_joken WHERE fk_torihikisaki_id=?");
$joken->execute([$id]); $joken = $joken->fetch();

$employees = $pdo->query("SELECT * FROM t_tantosha WHERE f_zaiseki_flag='有効' ORDER BY f_tantosha_name")->fetchAll();

$kyoten_name = array_column($kyotens, 'f_kyoten_name', 'pk_kyoten_id');
$busho_name  = array_column($bushos, 'f_busho_name', 'pk_busho_id');

echo html_header('取引先詳細：' . $tori['f_torihikisaki_name']);
echo nav_bar();
?>
<style>
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
@media(max-width:700px){.detail-grid{grid-template-columns:1fr}}
.mini-form{background:#f7f9fc;border:1px solid #e0e8f0;border-radius:6px;padding:12px;margin-bottom:12px}
.mini-form .row{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;margin-bottom:8px}
.mini-form label{font-size:11px;font-weight:600;color:#1B3A6B;display:block;margin-bottom:3px}
.mini-form input,.mini-form select{width:100%;padding:6px 8px;border:1px solid #C5D3E8;border-radius:4px;font-size:13px}
.chip-list{display:flex;flex-direction:column;gap:6px}
.chip-row{display:flex;align-items:center;gap:8px;padding:8px 10px;background:#fff;border:1px solid #E8EEF5;border-radius:6px;flex-wrap:wrap}
.chip-row .name{font-weight:600;color:#1B3A6B;min-width:120px}
.chip-row .meta{font-size:12px;color:#666}
.chip-row .spacer{flex:1}
.main-star{color:#e65100;font-size:12px;font-weight:700}
</style>
<div class="container">
  <div class="page-title">
    取引先詳細：<?= h($tori['f_torihikisaki_name']) ?>
    <?php if($tori['f_segment']): ?><span class="badge badge-info"><?= h($tori['f_segment']) ?></span><?php endif; ?>
  </div>
  <div style="margin-bottom:14px"><a href="master.php?tab=torihikisaki" class="btn btn-gray btn-sm">← 取引先一覧に戻る</a></div>

  <?php if($msg): ?><div class="alert alert-<?= h($msg_type) ?>"><?= h($msg) ?></div><?php endif; ?>

  <!-- 基本情報・リンク -->
  <div class="card">
    <div class="card-header">基本情報</div>
    <div class="card-body">
      <div class="detail-grid" style="font-size:13px">
        <div><strong>外部コード：</strong><?= h($tori['f_code'] ?: '―') ?></div>
        <div><strong>カナ：</strong><?= h($tori['f_torihikisaki_kana'] ?: '―') ?></div>
        <div><strong>電話：</strong><?= $tori['f_tel'] ? '<a href="tel:'.h($tori['f_tel']).'">'.h($tori['f_tel']).'</a>' : '―' ?></div>
        <div><strong>FAX：</strong><?= h($tori['f_fax'] ?: '―') ?></div>
        <div style="grid-column:1/-1"><strong>住所：</strong><?= h($tori['f_address'] ?: '―') ?></div>
      </div>
      <div style="margin-top:10px;display:flex;gap:14px;flex-wrap:wrap">
        <?php $mu = map_url($tori); if($mu): ?><a href="<?= h($mu) ?>" target="_blank" rel="noopener">📍 地図を開く</a><?php endif; ?>
        <?php if($tori['f_hp_url']): ?><a href="<?= h($tori['f_hp_url']) ?>" target="_blank" rel="noopener">🌐 ホームページ</a><?php endif; ?>
        <?php if($tori['f_kessan_url']): ?><a href="<?= h($tori['f_kessan_url']) ?>" target="_blank" rel="noopener">📊 決算情報</a><?php endif; ?>
      </div>
      <p style="margin-top:10px;font-size:12px;color:#888">基本情報の編集は一覧画面の「編集」ボタンから行ってください。</p>
    </div>
  </div>

  <!-- 取引条件 -->
  <div class="card">
    <div class="card-header">取引条件<span style="font-size:11px;font-weight:400;margin-left:6px">（担当交代時の引き継ぎ確認用）</span></div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="section" value="joken"><input type="hidden" name="action" value="save">
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="form-group" style="margin:0"><label>回収頻度</label><input type="text" name="f_kaishu_frequency" class="form-control" value="<?= h($joken['f_kaishu_frequency']??'') ?>" placeholder="例：週2回（火・金）"></div>
          <div class="form-group" style="margin:0"><label>支払いサイト</label><input type="text" name="f_payment_site" class="form-control" value="<?= h($joken['f_payment_site']??'') ?>" placeholder="例：月末締め翌月末払い"></div>
          <div class="form-group" style="margin:0"><label>支払い方法</label><input type="text" name="f_payment_method" class="form-control" value="<?= h($joken['f_payment_method']??'') ?>"></div>
          <div class="form-group" style="margin:0"><label>契約日</label><input type="date" name="f_contract_date" class="form-control" value="<?= h($joken['f_contract_date']??'') ?>"></div>
          <div class="form-group" style="grid-column:1/-1;margin:0"><label>備考</label><textarea name="f_biko" class="form-control" rows="2"><?= h($joken['f_biko']??'') ?></textarea></div>
        </div>
        <div style="text-align:right;margin-top:10px"><button type="submit" class="btn btn-primary btn-sm">保存する</button></div>
      </form>
    </div>
  </div>

  <!-- 支店・事業所 -->
  <div class="card">
    <div class="card-header">支店・事業所（<?= count($kyotens) ?>件）</div>
    <div class="card-body">
      <div class="mini-form">
        <form method="post">
          <input type="hidden" name="section" value="kyoten"><input type="hidden" name="action" value="add">
          <div class="row">
            <div><label>名称 *</label><input type="text" name="f_kyoten_name" required placeholder="例：坂出工場"></div>
            <div><label>カナ</label><input type="text" name="f_kyoten_kana"></div>
            <div><label>電話</label><input type="text" name="f_tel"></div>
            <div><label>郵便番号</label><input type="text" name="f_zip"></div>
          </div>
          <div class="row">
            <div style="grid-column:1/3"><label>住所</label><input type="text" name="f_address"></div>
            <div><label>地図URL</label><input type="url" name="f_map_url"></div>
            <div><label>備考</label><input type="text" name="f_biko"></div>
          </div>
          <button type="submit" class="btn btn-blue btn-sm">＋ 支店・事業所を追加</button>
        </form>
      </div>
      <div class="chip-list">
        <?php foreach($kyotens as $k): ?>
        <div class="chip-row" style="<?= $k['f_active']==='無効'?'opacity:.5':'' ?>">
          <span class="name"><?= h($k['f_kyoten_name']) ?></span>
          <span class="meta"><?= h($k['f_address']) ?><?= $k['f_tel']?'　☎'.h($k['f_tel']):'' ?></span>
          <span class="spacer"></span>
          <form method="post" onsubmit="return confirm('削除しますか？（紐づく部署・担当者は所属未設定になります）')">
            <input type="hidden" name="section" value="kyoten"><input type="hidden" name="action" value="delete"><input type="hidden" name="kyoten_id" value="<?= h($k['pk_kyoten_id']) ?>">
            <button type="submit" class="btn btn-danger btn-sm">削除</button>
          </form>
        </div>
        <?php endforeach; ?>
        <?php if(!$kyotens): ?><p style="font-size:12px;color:#999">支店・事業所は未登録です（本社のみの場合は登録不要です）。</p><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- 部署 -->
  <div class="card">
    <div class="card-header">部署（<?= count($bushos) ?>件）</div>
    <div class="card-body">
      <div class="mini-form">
        <form method="post">
          <input type="hidden" name="section" value="busho"><input type="hidden" name="action" value="add">
          <div class="row">
            <div><label>部署名 *</label><input type="text" name="f_busho_name" required placeholder="例：購買部"></div>
            <div><label>所属拠点</label>
              <select name="fk_kyoten_id"><option value="">（本社）</option><?php foreach($kyotens as $k): ?><option value="<?= h($k['pk_kyoten_id']) ?>"><?= h($k['f_kyoten_name']) ?></option><?php endforeach; ?></select>
            </div>
            <div><label>電話</label><input type="text" name="f_tel"></div>
            <div><label>備考</label><input type="text" name="f_biko"></div>
          </div>
          <button type="submit" class="btn btn-blue btn-sm">＋ 部署を追加</button>
        </form>
      </div>
      <div class="chip-list">
        <?php foreach($bushos as $b): ?>
        <div class="chip-row" style="<?= $b['f_active']==='無効'?'opacity:.5':'' ?>">
          <span class="name"><?= h($b['f_busho_name']) ?></span>
          <span class="meta"><?= $b['fk_kyoten_id'] ? h($kyoten_name[$b['fk_kyoten_id']] ?? '') : '本社' ?></span>
          <span class="spacer"></span>
          <form method="post" onsubmit="return confirm('削除しますか？')">
            <input type="hidden" name="section" value="busho"><input type="hidden" name="action" value="delete"><input type="hidden" name="busho_id" value="<?= h($b['pk_busho_id']) ?>">
            <button type="submit" class="btn btn-danger btn-sm">削除</button>
          </form>
        </div>
        <?php endforeach; ?>
        <?php if(!$bushos): ?><p style="font-size:12px;color:#999">部署は未登録です。</p><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- 先方担当者 -->
  <div class="card">
    <div class="card-header">先方担当者（<?= count($tantoshas) ?>名）</div>
    <div class="card-body">
      <div class="mini-form">
        <form method="post">
          <input type="hidden" name="section" value="tantosha"><input type="hidden" name="action" value="add">
          <div class="row">
            <div><label>氏名 *</label><input type="text" name="f_name" required></div>
            <div><label>役職</label><input type="text" name="f_yakushoku" placeholder="例：課長"></div>
            <div><label>所属拠点</label>
              <select name="fk_kyoten_id"><option value="">（本社）</option><?php foreach($kyotens as $k): ?><option value="<?= h($k['pk_kyoten_id']) ?>"><?= h($k['f_kyoten_name']) ?></option><?php endforeach; ?></select>
            </div>
            <div><label>所属部署</label>
              <select name="fk_busho_id"><option value="">（指定なし）</option><?php foreach($bushos as $b): ?><option value="<?= h($b['pk_busho_id']) ?>"><?= h($b['f_busho_name']) ?></option><?php endforeach; ?></select>
            </div>
          </div>
          <div class="row">
            <div><label>電話</label><input type="text" name="f_tel"></div>
            <div><label>携帯</label><input type="text" name="f_mobile"></div>
            <div><label>メール</label><input type="email" name="f_email"></div>
            <div><label><input type="checkbox" name="f_main_flag" value="1" style="width:auto;margin-right:4px">主担当にする</label></div>
          </div>
          <button type="submit" class="btn btn-blue btn-sm">＋ 先方担当者を追加</button>
        </form>
      </div>
      <div class="chip-list">
        <?php foreach($tantoshas as $t): ?>
        <div class="chip-row" style="<?= $t['f_active']==='無効'?'opacity:.5':'' ?>">
          <span class="name"><?= h($t['f_name']) ?><?= $t['f_yakushoku']?'（'.h($t['f_yakushoku']).'）':'' ?></span>
          <?php if($t['f_main_flag']): ?><span class="main-star">★ 主担当</span><?php endif; ?>
          <span class="meta">
            <?= $t['fk_busho_id'] ? h($busho_name[$t['fk_busho_id']] ?? '') : ($t['fk_kyoten_id'] ? h($kyoten_name[$t['fk_kyoten_id']] ?? '') : '') ?>
            <?= $t['f_tel']?'　☎'.h($t['f_tel']):'' ?><?= $t['f_email']?'　✉'.h($t['f_email']):'' ?>
          </span>
          <span class="spacer"></span>
          <form method="post" onsubmit="return confirm('削除しますか？')">
            <input type="hidden" name="section" value="tantosha"><input type="hidden" name="action" value="delete"><input type="hidden" name="saki_id" value="<?= h($t['pk_saki_tantosha_id']) ?>">
            <button type="submit" class="btn btn-danger btn-sm">削除</button>
          </form>
        </div>
        <?php endforeach; ?>
        <?php if(!$tantoshas): ?><p style="font-size:12px;color:#999">先方担当者は未登録です。</p><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- 自社担当割当 -->
  <div class="card">
    <div class="card-header">自社担当（メイン／サブ）</div>
    <div class="card-body">
      <div class="mini-form">
        <form method="post">
          <input type="hidden" name="section" value="assign"><input type="hidden" name="action" value="add">
          <div class="row">
            <div><label>担当者 *</label>
              <select name="fk_tantosha_id" required>
                <option value="">-- 選択 --</option>
                <?php foreach($employees as $e): ?><option value="<?= h($e['pk_tantosha_id']) ?>"><?= h($e['f_tantosha_name']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div><label>区分</label><select name="f_role"><option>メイン</option><option>サブ</option></select></div>
            <div><label>担当拠点（任意）</label>
              <select name="fk_kyoten_id"><option value="">（全体）</option><?php foreach($kyotens as $k): ?><option value="<?= h($k['pk_kyoten_id']) ?>"><?= h($k['f_kyoten_name']) ?></option><?php endforeach; ?></select>
            </div>
            <div><label>担当部署（任意）</label>
              <select name="fk_busho_id"><option value="">（全体）</option><?php foreach($bushos as $b): ?><option value="<?= h($b['pk_busho_id']) ?>"><?= h($b['f_busho_name']) ?></option><?php endforeach; ?></select>
            </div>
          </div>
          <button type="submit" class="btn btn-blue btn-sm">＋ 自社担当を追加</button>
        </form>
      </div>
      <div class="chip-list">
        <?php foreach($assigns as $a): ?>
        <div class="chip-row">
          <span class="name"><?= h($a['f_tantosha_name']) ?></span>
          <span class="badge <?= $a['f_role']==='メイン'?'badge-success':'badge-info' ?>"><?= h($a['f_role']) ?></span>
          <span class="meta"><?= h($a['f_kyoten_name'] ?? '') ?><?= $a['f_busho_name']?'　'.h($a['f_busho_name']):'' ?></span>
          <span class="spacer"></span>
          <form method="post" onsubmit="return confirm('削除しますか？')">
            <input type="hidden" name="section" value="assign"><input type="hidden" name="action" value="delete"><input type="hidden" name="assign_id" value="<?= h($a['pk_assign_id']) ?>">
            <button type="submit" class="btn btn-danger btn-sm">削除</button>
          </form>
        </div>
        <?php endforeach; ?>
        <?php if(!$assigns): ?><p style="font-size:12px;color:#999">自社担当は未登録です。</p><?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?= html_footer() ?>
