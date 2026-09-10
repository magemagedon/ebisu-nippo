<?php
require_once 'db.php';
require_once 'common.php';
if ($_SESSION['kengen'] !== '管理者') { header('Location: index.php'); exit; }

$pdo = get_db();
$tab = $_GET['tab'] ?? 'company';
$msg = '';
$msg_type = 'success';
if (isset($_GET['csv_msg'])) { $msg = $_GET['csv_msg']; $msg_type = $_GET['csv_msg_type'] ?? 'success'; }

// ===================== 会社基本情報 =====================
if ($tab === 'company' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $exists = $pdo->query("SELECT COUNT(*) FROM t_company")->fetchColumn();
        if ($exists) {
            $pdo->prepare("UPDATE t_company SET f_company_name=?,f_zip=?,f_address=?,f_tel=?,f_fax=?,f_email=?,f_url=?,f_tanto_name=?,f_biko=?,f_updated_at=NOW() WHERE 1=1")
                ->execute([$_POST['f_company_name'],$_POST['f_zip'],$_POST['f_address'],$_POST['f_tel'],$_POST['f_fax'],$_POST['f_email'],$_POST['f_url'],$_POST['f_tanto_name'],$_POST['f_biko']]);
        } else {
            $pdo->prepare("INSERT INTO t_company (pk_company_id,f_company_name,f_zip,f_address,f_tel,f_fax,f_email,f_url,f_tanto_name,f_biko) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([generate_uuid(),$_POST['f_company_name'],$_POST['f_zip'],$_POST['f_address'],$_POST['f_tel'],$_POST['f_fax'],$_POST['f_email'],$_POST['f_url'],$_POST['f_tanto_name'],$_POST['f_biko']]);
        }
        $msg = '会社基本情報を保存しました。';
    }
}

// ===================== 工場・拠点 =====================
if ($tab === 'factory' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pdo->prepare("INSERT INTO t_factory (pk_factory_id,f_factory_name,f_zip,f_address,f_tel,f_tanto_name,fk_madoguchi_tantosha_id,f_daihyo_email,f_biko,f_sort_order,f_active,f_created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())")
            ->execute([generate_uuid(),$_POST['f_factory_name'],$_POST['f_zip'],$_POST['f_address'],$_POST['f_tel'],$_POST['f_tanto_name'],$_POST['fk_madoguchi_tantosha_id']?:null,$_POST['f_daihyo_email']?:null,$_POST['f_biko'],$_POST['f_sort_order']??0,'有効']);
        $msg = '工場・拠点を追加しました。';
    } elseif ($action === 'edit') {
        $pdo->prepare("UPDATE t_factory SET f_factory_name=?,f_zip=?,f_address=?,f_tel=?,f_tanto_name=?,fk_madoguchi_tantosha_id=?,f_daihyo_email=?,f_biko=?,f_sort_order=?,f_active=? WHERE pk_factory_id=?")
            ->execute([$_POST['f_factory_name'],$_POST['f_zip'],$_POST['f_address'],$_POST['f_tel'],$_POST['f_tanto_name'],$_POST['fk_madoguchi_tantosha_id']?:null,$_POST['f_daihyo_email']?:null,$_POST['f_biko'],$_POST['f_sort_order']??0,$_POST['f_active'],$_POST['factory_id']]);
        $msg = '工場・拠点を更新しました。';
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM t_factory WHERE pk_factory_id=?")->execute([$_POST['factory_id']]);
        $msg = '削除しました。';
    }
}

// ===================== 商品 =====================
if ($tab === 'product' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pdo->prepare("INSERT INTO t_product (pk_product_id,f_code,f_product_name,f_kubun,f_category,f_segment,f_subcategory,f_unit,f_standard_price,f_biko,f_sort_order,f_active,f_created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())")
            ->execute([generate_uuid(),$_POST['f_code']??'',$_POST['f_product_name'],$_POST['f_kubun']??'商品',$_POST['f_category'],$_POST['f_segment']?:null,$_POST['f_subcategory']??'',$_POST['f_unit'],$_POST['f_standard_price']?:null,$_POST['f_biko'],$_POST['f_sort_order']??0,'有効']);
        $msg = '商品を追加しました。';
    } elseif ($action === 'edit') {
        $pdo->prepare("UPDATE t_product SET f_code=?,f_product_name=?,f_kubun=?,f_category=?,f_segment=?,f_subcategory=?,f_unit=?,f_standard_price=?,f_biko=?,f_sort_order=?,f_active=? WHERE pk_product_id=?")
            ->execute([$_POST['f_code']??'',$_POST['f_product_name'],$_POST['f_kubun']??'商品',$_POST['f_category'],$_POST['f_segment']?:null,$_POST['f_subcategory']??'',$_POST['f_unit'],$_POST['f_standard_price']?:null,$_POST['f_biko'],$_POST['f_sort_order']??0,$_POST['f_active'],$_POST['product_id']]);
        $msg = '商品を更新しました。';
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM t_product WHERE pk_product_id=?")->execute([$_POST['product_id']]);
        $msg = '削除しました。';
    }
}

// ===================== 単価 =====================
if ($tab === 'price' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pdo->prepare("INSERT INTO t_price (pk_price_id,fk_torihikisaki_id,fk_product_id,f_price,f_start_date,f_end_date,f_biko,f_created_at) VALUES (?,?,?,?,?,?,?,NOW())")
            ->execute([generate_uuid(),$_POST['fk_torihikisaki_id']?:null,$_POST['fk_product_id'],$_POST['f_price'],$_POST['f_start_date'],$_POST['f_end_date']?:null,$_POST['f_biko']]);
        $msg = '単価を追加しました。';
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM t_price WHERE pk_price_id=?")->execute([$_POST['price_id']]);
        $msg = '削除しました。';
    }
}

// ===================== 取引条件 =====================
if ($tab === 'joken' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $tid = $_POST['fk_torihikisaki_id'] ?? '';
        $exists = $pdo->prepare("SELECT COUNT(*) FROM t_torihiki_joken WHERE fk_torihikisaki_id=?");
        $exists->execute([$tid]);
        if ($exists->fetchColumn()) {
            $pdo->prepare("UPDATE t_torihiki_joken SET f_kaishu_frequency=?,f_payment_site=?,f_payment_method=?,f_contract_date=?,f_biko=?,f_updated_at=NOW() WHERE fk_torihikisaki_id=?")
                ->execute([$_POST['f_kaishu_frequency'],$_POST['f_payment_site'],$_POST['f_payment_method'],$_POST['f_contract_date']?:null,$_POST['f_biko'],$tid]);
        } else {
            $pdo->prepare("INSERT INTO t_torihiki_joken (pk_joken_id,fk_torihikisaki_id,f_kaishu_frequency,f_payment_site,f_payment_method,f_contract_date,f_biko) VALUES (?,?,?,?,?,?,?)")
                ->execute([generate_uuid(),$tid,$_POST['f_kaishu_frequency'],$_POST['f_payment_site'],$_POST['f_payment_method'],$_POST['f_contract_date']?:null,$_POST['f_biko']]);
        }
        $msg = '取引条件を保存しました。';
    }
}

// ===================== 部署 =====================
if ($tab === 'busho' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['f_busho_name'] ?? '');
        if ($name) {
            try {
                $pdo->prepare("INSERT INTO t_busho (pk_busho_id,f_busho_name,f_biko,f_sort_order,f_active,f_created_at) VALUES (?,?,?,?,?,NOW())")
                    ->execute([generate_uuid(),$name,$_POST['f_biko']??'',$_POST['f_sort_order']??0,'有効']);
                $msg = '部署を追加しました。';
            } catch(Exception $e) { $msg='エラー：同名の部署が既に存在します。'; $msg_type='danger'; }
        } else { $msg='部署名を入力してください。'; $msg_type='danger'; }
    } elseif ($action === 'edit') {
        $bid=$_POST['busho_id']??''; $name=trim($_POST['f_busho_name']??'');
        if ($name && $bid) {
            $pdo->prepare("UPDATE t_busho SET f_busho_name=?,f_biko=?,f_sort_order=?,f_active=?,f_updated_at=NOW() WHERE pk_busho_id=?")
                ->execute([$name,$_POST['f_biko']??'',$_POST['f_sort_order']??0,$_POST['f_active']??'有効',$bid]);
            $msg='部署を更新しました。';
        }
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM t_busho WHERE pk_busho_id=?")->execute([$_POST['busho_id']]);
        $msg='削除しました。';
    }
}

// ===================== 担当者 =====================
if ($tab === 'tantosha' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name=$_POST['f_tantosha_name']??''; $account=$_POST['f_account_name']??''; $pass=$_POST['f_password']??''; $kengen=$_POST['f_kengen_kubun']??'一般'; $busho=$_POST['fk_busho_id']?:null;
        $email=$_POST['f_email']??''; $tel=$_POST['f_tel']??'';
        if ($name && $account && $pass) {
            try {
                $pdo->prepare("INSERT INTO t_tantosha (pk_tantosha_id,f_tantosha_name,fk_busho_id,f_account_name,f_email,f_tel,f_password_hash,f_kengen_kubun,f_zaiseki_flag,f_created_at) VALUES (?,?,?,?,?,?,SHA2(?,256),?,'有効',NOW())")
                    ->execute([generate_uuid(),$name,$busho,$account,$email?:null,$tel?:null,$pass,$kengen]);
                $msg = '担当者を追加しました。';
            } catch(Exception $e) { $msg='エラー：アカウント名が重複しています。'; $msg_type='danger'; }
        } else { $msg='必須項目を入力してください。'; $msg_type='danger'; }
    } elseif ($action === 'edit') {
        $tid=$_POST['tantosha_id']??''; $name=trim($_POST['f_tantosha_name']??''); $kengen=$_POST['f_kengen_kubun']??'一般'; $busho=$_POST['fk_busho_id']?:null;
        $email=$_POST['f_email']??''; $tel=$_POST['f_tel']??'';
        if ($name && $tid) {
            $pdo->prepare("UPDATE t_tantosha SET f_tantosha_name=?,fk_busho_id=?,f_kengen_kubun=?,f_email=?,f_tel=? WHERE pk_tantosha_id=?")
                ->execute([$name,$busho,$kengen,$email?:null,$tel?:null,$tid]);
            $msg='担当者情報を更新しました。';
        }
    } elseif ($action === 'toggle') {
        $tid=$_POST['tantosha_id']??''; $flg=$_POST['current_flag']??''; $new=$flg==='有効'?'無効':'有効';
        $pdo->prepare("UPDATE t_tantosha SET f_zaiseki_flag=? WHERE pk_tantosha_id=?")->execute([$new,$tid]);
        $msg="在籍フラグを「{$new}」に変更しました。";
    } elseif ($action === 'change_pass') {
        $tid=$_POST['tantosha_id']??''; $pass=$_POST['new_password']??'';
        if ($pass) { $pdo->prepare("UPDATE t_tantosha SET f_password_hash=SHA2(?,256) WHERE pk_tantosha_id=?")->execute([$pass,$tid]); $msg='パスワードを変更しました。'; }
        else { $msg='パスワードを入力してください。'; $msg_type='danger'; }
    }
}

// ===================== 取引先 =====================
if ($tab === 'torihikisaki' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name=trim($_POST['f_torihikisaki_name']??'');
        if ($name) {
            $pdo->prepare("INSERT INTO t_torihikisaki
                (pk_torihikisaki_id,f_code,f_torihikisaki_name,f_torihikisaki_kana,f_segment,f_tantosha_name,f_zip,f_tel,f_fax,f_address,f_hp_url,f_map_url,f_kessan_url,f_biko,f_active,f_created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())")
                ->execute([generate_uuid(),$_POST['f_code']??'',$name,$_POST['f_torihikisaki_kana']??'',$_POST['f_segment']?:null,
                    $_POST['f_tantosha_name']??'',$_POST['f_zip']??'',$_POST['f_tel']??'',$_POST['f_fax']??'',$_POST['f_address']??'',
                    $_POST['f_hp_url']??'',$_POST['f_map_url']??'',$_POST['f_kessan_url']??'',$_POST['f_biko']??'','有効']);
            $msg='取引先を追加しました。';
        } else { $msg='取引先名を入力してください。'; $msg_type='danger'; }
    } elseif ($action === 'edit') {
        $tid=$_POST['torihikisaki_id']??''; $name=trim($_POST['f_torihikisaki_name']??'');
        if ($name && $tid) {
            $pdo->prepare("UPDATE t_torihikisaki SET
                f_code=?,f_torihikisaki_name=?,f_torihikisaki_kana=?,f_segment=?,f_tantosha_name=?,f_zip=?,f_tel=?,f_fax=?,f_address=?,
                f_hp_url=?,f_map_url=?,f_kessan_url=?,f_biko=?,f_active=?,f_updated_at=NOW() WHERE pk_torihikisaki_id=?")
                ->execute([$_POST['f_code']??'',$name,$_POST['f_torihikisaki_kana']??'',$_POST['f_segment']?:null,$_POST['f_tantosha_name']??'',
                    $_POST['f_zip']??'',$_POST['f_tel']??'',$_POST['f_fax']??'',$_POST['f_address']??'',
                    $_POST['f_hp_url']??'',$_POST['f_map_url']??'',$_POST['f_kessan_url']??'',$_POST['f_biko']??'',$_POST['f_active']??'有効',$tid]);
            $msg='取引先を更新しました。';
        }
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM t_torihikisaki WHERE pk_torihikisaki_id=?")->execute([$_POST['torihikisaki_id']]);
        $msg='削除しました。';
    }
}

// データ取得
$company      = $pdo->query("SELECT * FROM t_company LIMIT 1")->fetch();
$factories    = $pdo->query("SELECT * FROM t_factory ORDER BY f_sort_order,f_factory_name")->fetchAll();
$products     = $pdo->query("SELECT * FROM t_product ORDER BY f_sort_order,f_product_name")->fetchAll();
$prices       = $pdo->query("SELECT p.*,tr.f_torihikisaki_name,pr.f_product_name FROM t_price p LEFT JOIN t_torihikisaki tr ON p.fk_torihikisaki_id=tr.pk_torihikisaki_id JOIN t_product pr ON p.fk_product_id=pr.pk_product_id ORDER BY pr.f_product_name,tr.f_torihikisaki_name")->fetchAll();
$jokens       = $pdo->query("SELECT j.*,t.f_torihikisaki_name FROM t_torihiki_joken j JOIN t_torihikisaki t ON j.fk_torihikisaki_id=t.pk_torihikisaki_id ORDER BY t.f_torihikisaki_name")->fetchAll();
$bushos       = $pdo->query("SELECT * FROM t_busho ORDER BY f_sort_order,f_busho_name")->fetchAll();
$tantoshas    = $pdo->query("SELECT t.*, b.f_busho_name FROM t_tantosha t LEFT JOIN t_busho b ON t.fk_busho_id=b.pk_busho_id ORDER BY t.f_zaiseki_flag DESC,t.f_tantosha_name")->fetchAll();
$madoguchi_names = array_column($tantoshas, 'f_tantosha_name', 'pk_tantosha_id');
$segments     = $pdo->query("SELECT f_segment_name FROM t_segment WHERE f_active='有効' ORDER BY f_sort_order,f_segment_name")->fetchAll(PDO::FETCH_COLUMN);
$kubun_cls    = ['商品'=>'badge-success','サービス'=>'badge-info','仕入れ'=>'badge-warning'];
$torihikisakis= $pdo->query("
    SELECT t.*,
        (SELECT COUNT(*) FROM t_torihikisaki_kyoten k WHERE k.fk_torihikisaki_id=t.pk_torihikisaki_id AND k.f_active='有効') AS kyoten_cnt,
        (SELECT COUNT(*) FROM t_torihikisaki_busho b WHERE b.fk_torihikisaki_id=t.pk_torihikisaki_id AND b.f_active='有効') AS busho_cnt,
        (SELECT COUNT(*) FROM t_saki_tantosha s WHERE s.fk_torihikisaki_id=t.pk_torihikisaki_id AND s.f_active='有効') AS tantosha_cnt
    FROM t_torihikisaki t
    ORDER BY (t.f_torihikisaki_kana IS NULL), t.f_torihikisaki_kana, t.f_torihikisaki_name
")->fetchAll();

echo html_header('マスタ管理');
echo nav_bar();
?>
<div class="container">
  <div class="page-title">マスタ管理</div>

  <?php if($msg): ?>
  <div class="alert alert-<?= $msg_type ?>"><?= h($msg) ?></div>
  <?php endif; ?>

  <!-- タブ -->
  <div style="display:flex;gap:4px;margin-bottom:16px;flex-wrap:wrap">
    <?php foreach([
      'company'=>'会社情報', 'factory'=>'工場・拠点', 'product'=>'商品',
      'price'=>'単価', 'joken'=>'取引条件', 'busho'=>'部署', 'tantosha'=>'担当者', 'torihikisaki'=>'取引先'
    ] as $key=>$label): ?>
    <a href="?tab=<?= $key ?>" class="btn <?= $tab===$key?'btn-primary':'btn-gray' ?> btn-sm"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

<?php if($tab === 'company'): ?>
  <!-- ========== 会社基本情報 ========== -->
  <div class="card">
    <div class="card-header">会社基本情報</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="save">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group" style="grid-column:1/-1;margin:0">
            <label>会社名 <span style="color:#c62828">*</span></label>
            <input type="text" name="f_company_name" class="form-control" value="<?= h($company['f_company_name']??'') ?>" required>
          </div>
          <div class="form-group" style="margin:0">
            <label>郵便番号</label>
            <input type="text" name="f_zip" class="form-control" value="<?= h($company['f_zip']??'') ?>" placeholder="000-0000">
          </div>
          <div class="form-group" style="margin:0">
            <label>電話番号</label>
            <input type="text" name="f_tel" class="form-control" value="<?= h($company['f_tel']??'') ?>">
          </div>
          <div class="form-group" style="grid-column:1/-1;margin:0">
            <label>住所</label>
            <input type="text" name="f_address" class="form-control" value="<?= h($company['f_address']??'') ?>">
          </div>
          <div class="form-group" style="margin:0">
            <label>FAX</label>
            <input type="text" name="f_fax" class="form-control" value="<?= h($company['f_fax']??'') ?>">
          </div>
          <div class="form-group" style="margin:0">
            <label>メールアドレス</label>
            <input type="email" name="f_email" class="form-control" value="<?= h($company['f_email']??'') ?>">
          </div>
          <div class="form-group" style="margin:0">
            <label>WebサイトURL</label>
            <input type="text" name="f_url" class="form-control" value="<?= h($company['f_url']??'') ?>">
          </div>
          <div class="form-group" style="margin:0">
            <label>担当者名</label>
            <input type="text" name="f_tanto_name" class="form-control" value="<?= h($company['f_tanto_name']??'') ?>">
          </div>
          <div class="form-group" style="grid-column:1/-1;margin:0">
            <label>備考</label>
            <textarea name="f_biko" class="form-control" rows="2"><?= h($company['f_biko']??'') ?></textarea>
          </div>
        </div>
        <div style="text-align:right;margin-top:14px">
          <button type="submit" class="btn btn-primary">保存する</button>
        </div>
      </form>
    </div>
  </div>

<?php elseif($tab === 'factory'): ?>
  <!-- ========== 工場・拠点 ========== -->
  <div class="card">
    <div class="card-header">工場・拠点を追加</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="add">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="form-group" style="grid-column:1/-1;margin:0">
            <label>拠点名 <span style="color:#c62828">*</span></label>
            <input type="text" name="f_factory_name" class="form-control" placeholder="例：観音寺工場" required>
          </div>
          <div class="form-group" style="margin:0"><label>郵便番号</label><input type="text" name="f_zip" class="form-control" placeholder="000-0000"></div>
          <div class="form-group" style="margin:0"><label>電話番号</label><input type="text" name="f_tel" class="form-control"></div>
          <div class="form-group" style="grid-column:1/-1;margin:0"><label>住所</label><input type="text" name="f_address" class="form-control"></div>
          <div class="form-group" style="margin:0"><label>担当者（現場・表示用）</label><input type="text" name="f_tanto_name" class="form-control"></div>
          <div class="form-group" style="margin:0"><label>表示順</label><input type="number" name="f_sort_order" class="form-control" value="0"></div>
          <div class="form-group" style="margin:0">
            <label>窓口担当<span style="font-size:10px;color:#888;font-weight:400">（工場管理：異常・不具合の自動アサイン先）</span></label>
            <select name="fk_madoguchi_tantosha_id" class="form-control">
              <option value="">未設定</option>
              <?php foreach($tantoshas as $t): if($t['f_zaiseki_flag']!=='有効') continue; ?><option value="<?= h($t['pk_tantosha_id']) ?>"><?= h($t['f_tantosha_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0">
            <label>代表メールアドレス<span style="font-size:10px;color:#888;font-weight:400">（工場長使用・窓口担当未設定時の通知先）</span></label>
            <input type="email" name="f_daihyo_email" class="form-control">
          </div>
          <div class="form-group" style="grid-column:1/-1;margin:0"><label>備考</label><input type="text" name="f_biko" class="form-control"></div>
        </div>
        <div style="text-align:right;margin-top:12px"><button type="submit" class="btn btn-primary">追加する</button></div>
      </form>
    </div>
  </div>
  <div class="card">
    <div class="card-header">工場・拠点一覧（<?= count($factories) ?>件）</div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap"><table>
        <thead><tr><th>拠点名</th><th class="pc-only">住所</th><th class="pc-only">電話</th><th class="pc-only">窓口担当</th><th style="text-align:center">状態</th><th style="text-align:center">操作</th></tr></thead>
        <tbody>
        <?php foreach($factories as $f): ?>
        <tr style="<?= $f['f_active']==='無効'?'opacity:0.5':'' ?>">
          <td style="font-weight:600"><?= h($f['f_factory_name']) ?></td>
          <td class="pc-only" style="font-size:12px"><?= h($f['f_address']) ?></td>
          <td class="pc-only"><?= h($f['f_tel']) ?></td>
          <td class="pc-only"><?= h($madoguchi_names[$f['fk_madoguchi_tantosha_id']] ?? '') ?: '<span style="color:#bbb">未設定</span>' ?></td>
          <td style="text-align:center"><span class="badge <?= $f['f_active']==='有効'?'badge-success':'badge-danger' ?>"><?= h($f['f_active']) ?></span></td>
          <td style="text-align:center;white-space:nowrap">
            <button type="button" class="btn btn-blue btn-sm" onclick="showFactoryEdit(<?= htmlspecialchars(json_encode($f),ENT_QUOTES) ?>)">編集</button>
            <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？')">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="factory_id" value="<?= h($f['pk_factory_id']) ?>">
              <button type="submit" class="btn btn-danger btn-sm">削除</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>

<?php elseif($tab === 'product'): ?>
  <!-- ========== 商品マスタ ========== -->
  <div class="card">
    <div class="card-header">商品を追加</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="add">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
          <div class="form-group" style="margin:0"><label>商品名 <span style="color:#c62828">*</span></label><input type="text" name="f_product_name" class="form-control" placeholder="例：段ボール古紙" required></div>
          <div class="form-group" style="margin:0">
            <label>区分</label>
            <select name="f_kubun" class="form-control">
              <option value="商品">商品</option>
              <option value="サービス">サービス（分析のみ・計量のみ 等）</option>
              <option value="仕入れ">仕入れ（お客様からの仕入れ）</option>
            </select>
          </div>
          <div class="form-group" style="margin:0">
            <label>セグメント</label>
            <select name="f_segment" class="form-control">
              <option value="">未設定</option>
              <?php foreach($segments as $s): ?><option value="<?= h($s) ?>"><?= h($s) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0"><label>細分類</label><input type="text" name="f_subcategory" class="form-control" placeholder="例：猫砂・綿・原綿の色 等"></div>
          <div class="form-group" style="margin:0"><label>カテゴリ</label><input type="text" name="f_category" class="form-control" placeholder="例：古紙"></div>
          <div class="form-group" style="margin:0"><label>外部コード</label><input type="text" name="f_code" class="form-control"></div>
          <div class="form-group" style="margin:0"><label>単位</label><input type="text" name="f_unit" class="form-control" placeholder="例：t" value="t"></div>
          <div class="form-group" style="margin:0"><label>標準単価（円）</label><input type="number" name="f_standard_price" class="form-control" step="100"></div>
          <div class="form-group" style="margin:0"><label>表示順</label><input type="number" name="f_sort_order" class="form-control" value="0"></div>
          <div class="form-group" style="grid-column:1/-1;margin:0"><label>備考</label><input type="text" name="f_biko" class="form-control"></div>
        </div>
        <div style="text-align:right;margin-top:12px"><button type="submit" class="btn btn-primary">追加する</button></div>
      </form>
    </div>
  </div>
  <div class="card">
    <div class="card-header">商品一覧（<?= count($products) ?>件）</div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap"><table>
        <thead><tr><th>商品名</th><th style="text-align:center">区分</th><th class="pc-only">セグメント／細分類</th><th class="pc-only">カテゴリ</th><th>単位</th><th style="text-align:right">標準単価</th><th style="text-align:center">状態</th><th style="text-align:center">操作</th></tr></thead>
        <tbody>
        <?php foreach($products as $pr): ?>
        <tr style="<?= $pr['f_active']==='無効'?'opacity:0.5':'' ?>">
          <td style="font-weight:600"><?= h($pr['f_product_name']) ?></td>
          <td style="text-align:center"><span class="badge <?= $kubun_cls[$pr['f_kubun']]??'badge-info' ?>"><?= h($pr['f_kubun']) ?></span></td>
          <td class="pc-only" style="font-size:12px;color:#666"><?= h($pr['f_segment']) ?><?= $pr['f_subcategory']?' / '.h($pr['f_subcategory']):'' ?></td>
          <td class="pc-only"><?= h($pr['f_category']) ?></td>
          <td><?= h($pr['f_unit']) ?></td>
          <td style="text-align:right"><?= $pr['f_standard_price']?number_format($pr['f_standard_price']).'円':'―' ?></td>
          <td style="text-align:center"><span class="badge <?= $pr['f_active']==='有効'?'badge-success':'badge-danger' ?>"><?= h($pr['f_active']) ?></span></td>
          <td style="text-align:center;white-space:nowrap">
            <button type="button" class="btn btn-blue btn-sm" onclick="showProductEdit(<?= htmlspecialchars(json_encode($pr),ENT_QUOTES) ?>)">編集</button>
            <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？')">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="product_id" value="<?= h($pr['pk_product_id']) ?>">
              <button type="submit" class="btn btn-danger btn-sm">削除</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>

<?php elseif($tab === 'price'): ?>
  <!-- ========== 単価マスタ ========== -->
  <div class="card">
    <div class="card-header">単価を追加</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="add">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="form-group" style="margin:0">
            <label>取引先（空白=標準単価）</label>
            <select name="fk_torihikisaki_id" class="form-control">
              <option value="">-- 標準単価（全取引先共通） --</option>
              <?php foreach($torihikisakis as $tr): ?>
              <option value="<?= h($tr['pk_torihikisaki_id']) ?>"><?= h($tr['f_torihikisaki_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0">
            <label>商品 <span style="color:#c62828">*</span></label>
            <select name="fk_product_id" class="form-control" required>
              <option value="">-- 選択 --</option>
              <?php foreach($products as $pr): ?>
              <option value="<?= h($pr['pk_product_id']) ?>">[<?= h($pr['f_kubun']) ?>] <?= h($pr['f_product_name']) ?>（標準：<?= $pr['f_standard_price']?number_format($pr['f_standard_price']).'円':'―' ?>）</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0"><label>単価（円） <span style="color:#c62828">*</span></label><input type="number" name="f_price" class="form-control" step="100" required></div>
          <div class="form-group" style="margin:0"><label>適用開始日 <span style="color:#c62828">*</span></label><input type="date" name="f_start_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
          <div class="form-group" style="margin:0"><label>適用終了日（任意）</label><input type="date" name="f_end_date" class="form-control"></div>
          <div class="form-group" style="margin:0"><label>備考</label><input type="text" name="f_biko" class="form-control"></div>
        </div>
        <div style="text-align:right;margin-top:12px"><button type="submit" class="btn btn-primary">追加する</button></div>
      </form>
    </div>
  </div>
  <div class="card">
    <div class="card-header">単価一覧（<?= count($prices) ?>件）</div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap"><table>
        <thead><tr><th>商品名</th><th style="text-align:center">区分</th><th>取引先</th><th style="text-align:right">単価</th><th>適用期間</th><th>備考</th><th style="text-align:center">操作</th></tr></thead>
        <tbody>
        <?php foreach($prices as $pr): ?>
        <tr>
          <td style="font-weight:600"><?= h($pr['f_product_name']) ?></td>
          <td style="text-align:center"><span class="badge <?= $kubun_cls[$pr['f_kubun']]??'badge-info' ?>"><?= h($pr['f_kubun']) ?></span></td>
          <td><?= $pr['f_torihikisaki_name']?h($pr['f_torihikisaki_name']):'<span class="badge badge-info">標準</span>' ?></td>
          <td style="text-align:right;font-weight:600;color:#1B3A6B"><?= number_format($pr['f_price']) ?>円</td>
          <td style="font-size:12px"><?= h(date('Y/m/d',strtotime($pr['f_start_date']))) ?>〜<?= $pr['f_end_date']?h(date('Y/m/d',strtotime($pr['f_end_date']))):'終了日なし' ?></td>
          <td style="font-size:12px;color:#666"><?= h($pr['f_biko']) ?></td>
          <td style="text-align:center">
            <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？')">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="price_id" value="<?= h($pr['pk_price_id']) ?>">
              <button type="submit" class="btn btn-danger btn-sm">削除</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>

<?php elseif($tab === 'joken'): ?>
  <!-- ========== 取引条件 ========== -->
  <div class="card">
    <div class="card-header">取引条件を登録・編集</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="save">
        <div class="form-group">
          <label>取引先 <span style="color:#c62828">*</span></label>
          <select name="fk_torihikisaki_id" class="form-control" id="jokenSelect" onchange="loadJoken(this.value)" required>
            <option value="">-- 取引先を選択 --</option>
            <?php foreach($torihikisakis as $tr): ?>
            <option value="<?= h($tr['pk_torihikisaki_id']) ?>"><?= h($tr['f_torihikisaki_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="form-group" style="margin:0"><label>回収頻度</label><input type="text" name="f_kaishu_frequency" id="j_kaishu" class="form-control" placeholder="例：週2回（火・金）"></div>
          <div class="form-group" style="margin:0"><label>支払いサイト</label><input type="text" name="f_payment_site" id="j_site" class="form-control" placeholder="例：月末締め翌月末払い"></div>
          <div class="form-group" style="margin:0"><label>支払い方法</label><input type="text" name="f_payment_method" id="j_method" class="form-control" placeholder="例：銀行振込"></div>
          <div class="form-group" style="margin:0"><label>契約日</label><input type="date" name="f_contract_date" id="j_contract" class="form-control"></div>
          <div class="form-group" style="grid-column:1/-1;margin:0"><label>備考</label><textarea name="f_biko" id="j_biko" class="form-control" rows="2"></textarea></div>
        </div>
        <div style="text-align:right;margin-top:12px"><button type="submit" class="btn btn-primary">保存する</button></div>
      </form>
    </div>
  </div>
  <div class="card">
    <div class="card-header">取引条件一覧</div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap"><table>
        <thead><tr><th>取引先</th><th>回収頻度</th><th>支払いサイト</th><th>支払い方法</th><th>契約日</th></tr></thead>
        <tbody>
        <?php foreach($jokens as $j): ?>
        <tr>
          <td style="font-weight:600"><?= h($j['f_torihikisaki_name']) ?></td>
          <td><?= h($j['f_kaishu_frequency']) ?></td>
          <td><?= h($j['f_payment_site']) ?></td>
          <td><?= h($j['f_payment_method']) ?></td>
          <td><?= $j['f_contract_date']?h(date('Y/m/d',strtotime($j['f_contract_date']))):'―' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>

<?php elseif($tab === 'busho'): ?>
  <!-- ========== 部署 ========== -->
  <div class="card">
    <div class="card-header">部署を追加</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="add">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="form-group" style="margin:0"><label>部署名 <span style="color:#c62828">*</span></label><input type="text" name="f_busho_name" class="form-control" placeholder="例：営業部"></div>
          <div class="form-group" style="margin:0"><label>表示順</label><input type="number" name="f_sort_order" class="form-control" value="0"></div>
          <div class="form-group" style="grid-column:1/-1;margin:0"><label>備考</label><input type="text" name="f_biko" class="form-control"></div>
        </div>
        <div style="text-align:right;margin-top:12px"><button type="submit" class="btn btn-primary">追加する</button></div>
      </form>
    </div>
  </div>
  <div class="card">
    <div class="card-header">部署一覧</div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap"><table>
        <thead><tr><th>部署名</th><th class="pc-only">備考</th><th style="text-align:center">状態</th><th style="text-align:center">操作</th></tr></thead>
        <tbody>
        <?php if(empty($bushos)): ?>
        <tr><td colspan="4" style="text-align:center;padding:20px;color:#999">部署が登録されていません</td></tr>
        <?php endif; ?>
        <?php foreach($bushos as $b): ?>
        <tr style="<?= $b['f_active']==='無効'?'opacity:0.5':'' ?>">
          <td style="font-weight:600"><?= h($b['f_busho_name']) ?></td>
          <td class="pc-only" style="color:#666"><?= h($b['f_biko']) ?></td>
          <td style="text-align:center"><span class="badge <?= $b['f_active']==='有効'?'badge-success':'badge-danger' ?>"><?= h($b['f_active']) ?></span></td>
          <td style="text-align:center;white-space:nowrap">
            <button type="button" class="btn btn-blue btn-sm" onclick="showBushoEdit(<?= htmlspecialchars(json_encode($b),ENT_QUOTES) ?>)">編集</button>
            <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？（所属する担当者は部署未設定になります）')">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="busho_id" value="<?= h($b['pk_busho_id']) ?>">
              <button type="submit" class="btn btn-danger btn-sm">削除</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>

<?php elseif($tab === 'tantosha'): ?>
  <!-- ========== 担当者 ========== -->
  <div class="card">
    <div class="card-header">担当者を追加</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="add">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="form-group" style="margin:0"><label>担当者名 <span style="color:#c62828">*</span></label><input type="text" name="f_tantosha_name" class="form-control" placeholder="例：鈴木 三郎"></div>
          <div class="form-group" style="margin:0"><label>アカウント名 <span style="color:#c62828">*</span></label><input type="text" name="f_account_name" class="form-control" placeholder="例：suzuki"></div>
          <div class="form-group" style="margin:0"><label>パスワード <span style="color:#c62828">*</span></label><input type="password" name="f_password" class="form-control"></div>
          <div class="form-group" style="margin:0">
            <label>権限区分</label>
            <select name="f_kengen_kubun" class="form-control">
              <option value="一般">一般</option>
              <option value="部門管理者">部門管理者</option>
              <option value="管理者">管理者</option>
            </select>
          </div>
          <div class="form-group" style="margin:0">
            <label>部署</label>
            <select name="fk_busho_id" class="form-control">
              <option value="">未設定</option>
              <?php foreach($bushos as $b): ?><option value="<?= h($b['pk_busho_id']) ?>"><?= h($b['f_busho_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0">
            <label>メールアドレス<span style="font-size:10px;color:#888;font-weight:400">（工場管理の通知用・任意）</span></label>
            <input type="email" name="f_email" class="form-control">
          </div>
          <div class="form-group" style="margin:0">
            <label>携帯電話番号<span style="font-size:10px;color:#888;font-weight:400">（電話発信ボタン用・任意）</span></label>
            <input type="tel" name="f_tel" class="form-control" placeholder="090-0000-0000">
          </div>
        </div>
        <div style="text-align:right;margin-top:12px"><button type="submit" class="btn btn-primary">追加する</button></div>
      </form>
    </div>
  </div>
  <div class="card">
    <div class="card-header">担当者一覧</div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap"><table>
        <thead><tr><th>担当者名</th><th>アカウント名</th><th>部署</th><th class="pc-only">メール／電話</th><th style="text-align:center">権限</th><th style="text-align:center">在籍</th><th style="text-align:center">操作</th></tr></thead>
        <tbody>
        <?php foreach($tantoshas as $t): ?>
        <tr style="<?= $t['f_zaiseki_flag']==='無効'?'opacity:0.5':'' ?>">
          <td style="font-weight:600"><?= h($t['f_tantosha_name']) ?></td>
          <td><?= h($t['f_account_name']) ?></td>
          <td><?= h($t['f_busho_name'] ?? '') ?: '<span style="color:#bbb">未設定</span>' ?></td>
          <td class="pc-only" style="font-size:11px;color:#666"><?= h($t['f_email']) ?><?= ($t['f_email'] && $t['f_tel'])?'<br>':'' ?><?= h($t['f_tel']) ?><?= (!$t['f_email'] && !$t['f_tel'])?'<span style="color:#bbb">未設定</span>':'' ?></td>
          <td style="text-align:center"><span class="badge <?= $t['f_kengen_kubun']==='管理者'?'badge-info':($t['f_kengen_kubun']==='部門管理者'?'badge-warning':'badge-success') ?>"><?= h($t['f_kengen_kubun']) ?></span></td>
          <td style="text-align:center">
            <form method="post" style="display:inline">
              <input type="hidden" name="action" value="toggle"><input type="hidden" name="tantosha_id" value="<?= h($t['pk_tantosha_id']) ?>"><input type="hidden" name="current_flag" value="<?= h($t['f_zaiseki_flag']) ?>">
              <button type="submit" class="badge <?= $t['f_zaiseki_flag']==='有効'?'badge-success':'badge-danger' ?>" style="cursor:pointer;border:none"><?= h($t['f_zaiseki_flag']) ?></button>
            </form>
          </td>
          <td style="text-align:center;white-space:nowrap">
            <button type="button" class="btn btn-blue btn-sm" onclick="showTantoshaEdit(<?= htmlspecialchars(json_encode($t),ENT_QUOTES) ?>)">編集</button>
            <button type="button" class="btn btn-gray btn-sm" onclick="showPassForm('<?= h($t['pk_tantosha_id']) ?>','<?= h($t['f_tantosha_name']) ?>')">PW変更</button>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>

<?php else: ?>
  <!-- ========== 取引先 ========== -->
  <div class="card">
    <div class="card-header">取引先を追加</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="add">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
          <div class="form-group" style="grid-column:1/-1;margin:0"><label>取引先名 <span style="color:#c62828">*</span></label><input type="text" name="f_torihikisaki_name" class="form-control" required></div>
          <div class="form-group" style="margin:0"><label>取引先名カナ</label><input type="text" name="f_torihikisaki_kana" class="form-control" placeholder="例：タカマツシギョウ"></div>
          <div class="form-group" style="margin:0"><label>外部コード</label><input type="text" name="f_code" class="form-control" placeholder="販売管理システムのコード等"></div>
          <div class="form-group" style="margin:0">
            <label>セグメント</label>
            <select name="f_segment" class="form-control">
              <option value="">未設定</option>
              <?php foreach($segments as $s): ?><option value="<?= h($s) ?>"><?= h($s) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0"><label>先方担当者名</label><input type="text" name="f_tantosha_name" class="form-control" placeholder="任意。詳細画面で複数登録も可"></div>
          <div class="form-group" style="margin:0"><label>電話番号</label><input type="text" name="f_tel" class="form-control"></div>
          <div class="form-group" style="margin:0"><label>FAX</label><input type="text" name="f_fax" class="form-control"></div>
          <div class="form-group" style="margin:0"><label>郵便番号</label><input type="text" name="f_zip" class="form-control" placeholder="000-0000"></div>
          <div class="form-group" style="grid-column:2/4;margin:0"><label>住所</label><input type="text" name="f_address" class="form-control"></div>
          <div class="form-group" style="margin:0"><label>ホームページURL</label><input type="url" name="f_hp_url" class="form-control" placeholder="https://"></div>
          <div class="form-group" style="margin:0"><label>地図URL（空欄可）</label><input type="url" name="f_map_url" class="form-control" placeholder="空欄なら住所から自動検索"></div>
          <div class="form-group" style="margin:0"><label>決算情報URL</label><input type="url" name="f_kessan_url" class="form-control" placeholder="https://"></div>
          <div class="form-group" style="grid-column:1/-1;margin:0"><label>備考</label><input type="text" name="f_biko" class="form-control"></div>
        </div>
        <div style="text-align:right;margin-top:12px"><button type="submit" class="btn btn-primary">追加する</button></div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      CSVで一括取込
      <a href="csv_import_torihikisaki.php?action=template" class="btn btn-gray btn-sm">テンプレートをダウンロード</a>
    </div>
    <div class="card-body">
      <p style="font-size:12px;color:#666;margin-bottom:10px">
        列構成：外部コード, 取引先名, 取引先名カナ, セグメント, 先方担当者名, 電話番号, FAX, 郵便番号, 住所, ホームページURL, 備考。<br>
        「外部コード」が一致する行は更新、無ければ「取引先名」で一致確認のうえ新規追加します。Shift-JIS／UTF-8のどちらでも読み込めます。
      </p>
      <form method="post" action="csv_import_torihikisaki.php" enctype="multipart/form-data">
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <input type="file" name="csv_file" accept=".csv" required>
          <button type="submit" class="btn btn-primary btn-sm">取り込む</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">取引先一覧（<?= count($torihikisakis) ?>件）</div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap"><table>
        <thead><tr><th>取引先名</th><th class="pc-only">カナ</th><th class="pc-only">セグメント</th><th style="text-align:center" class="pc-only">支店/部署/担当</th><th class="pc-only">電話</th><th style="text-align:center">状態</th><th style="text-align:center">操作</th></tr></thead>
        <tbody>
        <?php foreach($torihikisakis as $t): ?>
        <tr style="<?= $t['f_active']==='無効'?'opacity:0.5':'' ?>">
          <td style="font-weight:600"><?= h($t['f_torihikisaki_name']) ?><?php if($t['f_code']): ?><br><span style="font-size:11px;color:#888">[<?= h($t['f_code']) ?>]</span><?php endif; ?></td>
          <td class="pc-only" style="font-size:12px;color:#666"><?= h($t['f_torihikisaki_kana']) ?></td>
          <td class="pc-only"><?php if($t['f_segment']): ?><span class="badge badge-info"><?= h($t['f_segment']) ?></span><?php endif; ?></td>
          <td class="pc-only" style="text-align:center;font-size:12px;color:#555"><?= (int)$t['kyoten_cnt'] ?> / <?= (int)$t['busho_cnt'] ?> / <?= (int)$t['tantosha_cnt'] ?></td>
          <td class="pc-only"><?= h($t['f_tel']) ?></td>
          <td style="text-align:center"><span class="badge <?= $t['f_active']==='有効'?'badge-success':'badge-danger' ?>"><?= h($t['f_active']) ?></span></td>
          <td style="text-align:center;white-space:nowrap">
            <a href="torihikisaki_detail.php?id=<?= h($t['pk_torihikisaki_id']) ?>" class="btn btn-success btn-sm">詳細</a>
            <button type="button" class="btn btn-blue btn-sm" onclick="showTorihikiEdit(<?= htmlspecialchars(json_encode($t),ENT_QUOTES) ?>)">編集</button>
            <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？（支店・部署・担当者も削除されます）')">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="torihikisaki_id" value="<?= h($t['pk_torihikisaki_id']) ?>">
              <button type="submit" class="btn btn-danger btn-sm">削除</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
<?php endif; ?>
</div>

<!-- モーダル：工場編集 -->
<div id="factoryModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:8px;padding:24px;width:480px;max-width:95%;max-height:90vh;overflow-y:auto">
    <div style="font-weight:700;font-size:15px;margin-bottom:16px;color:#1B3A6B">工場・拠点を編集</div>
    <form method="post">
      <input type="hidden" name="action" value="edit"><input type="hidden" name="factory_id" id="fid">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="form-group" style="grid-column:1/-1;margin:0"><label>拠点名</label><input type="text" name="f_factory_name" id="fname" class="form-control"></div>
        <div class="form-group" style="margin:0"><label>郵便番号</label><input type="text" name="f_zip" id="fzip" class="form-control"></div>
        <div class="form-group" style="margin:0"><label>電話番号</label><input type="text" name="f_tel" id="ftel" class="form-control"></div>
        <div class="form-group" style="grid-column:1/-1;margin:0"><label>住所</label><input type="text" name="f_address" id="faddr" class="form-control"></div>
        <div class="form-group" style="margin:0"><label>担当者（現場・表示用）</label><input type="text" name="f_tanto_name" id="ftanto" class="form-control"></div>
        <div class="form-group" style="margin:0"><label>表示順</label><input type="number" name="f_sort_order" id="fsort" class="form-control"></div>
        <div class="form-group" style="margin:0">
          <label>窓口担当</label>
          <select name="fk_madoguchi_tantosha_id" id="fmadoguchi" class="form-control">
            <option value="">未設定</option>
            <?php foreach($tantoshas as $t): if($t['f_zaiseki_flag']!=='有効') continue; ?><option value="<?= h($t['pk_tantosha_id']) ?>"><?= h($t['f_tantosha_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0"><label>代表メールアドレス</label><input type="email" name="f_daihyo_email" id="fdaihyoemail" class="form-control"></div>
        <div class="form-group" style="margin:0"><label>状態</label><select name="f_active" id="factive" class="form-control"><option>有効</option><option>無効</option></select></div>
        <div class="form-group" style="grid-column:1/-1;margin:0"><label>備考</label><input type="text" name="f_biko" id="fbiko" class="form-control"></div>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
        <button type="button" class="btn btn-gray" onclick="document.getElementById('factoryModal').style.display='none'">キャンセル</button>
        <button type="submit" class="btn btn-primary">更新する</button>
      </div>
    </form>
  </div>
</div>

<!-- モーダル：商品編集 -->
<div id="productModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:8px;padding:24px;width:480px;max-width:95%;max-height:90vh;overflow-y:auto">
    <div style="font-weight:700;font-size:15px;margin-bottom:16px;color:#1B3A6B">商品を編集</div>
    <form method="post">
      <input type="hidden" name="action" value="edit"><input type="hidden" name="product_id" id="prid">
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
        <div class="form-group" style="grid-column:1/-1;margin:0"><label>商品名</label><input type="text" name="f_product_name" id="prname" class="form-control"></div>
        <div class="form-group" style="margin:0">
          <label>区分</label>
          <select name="f_kubun" id="prkubun" class="form-control">
            <option value="商品">商品</option>
            <option value="サービス">サービス</option>
            <option value="仕入れ">仕入れ</option>
          </select>
        </div>
        <div class="form-group" style="margin:0">
          <label>セグメント</label>
          <select name="f_segment" id="prsegment" class="form-control">
            <option value="">未設定</option>
            <?php foreach($segments as $s): ?><option value="<?= h($s) ?>"><?= h($s) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0"><label>細分類</label><input type="text" name="f_subcategory" id="prsubcat" class="form-control"></div>
        <div class="form-group" style="margin:0"><label>カテゴリ</label><input type="text" name="f_category" id="prcat" class="form-control"></div>
        <div class="form-group" style="margin:0"><label>外部コード</label><input type="text" name="f_code" id="prcode" class="form-control"></div>
        <div class="form-group" style="margin:0"><label>単位</label><input type="text" name="f_unit" id="prunit" class="form-control"></div>
        <div class="form-group" style="margin:0"><label>標準単価</label><input type="number" name="f_standard_price" id="prprice" class="form-control" step="100"></div>
        <div class="form-group" style="margin:0"><label>表示順</label><input type="number" name="f_sort_order" id="prsort" class="form-control"></div>
        <div class="form-group" style="margin:0"><label>状態</label><select name="f_active" id="practive" class="form-control"><option>有効</option><option>無効</option></select></div>
        <div class="form-group" style="grid-column:1/-1;margin:0"><label>備考</label><input type="text" name="f_biko" id="prbiko" class="form-control"></div>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
        <button type="button" class="btn btn-gray" onclick="document.getElementById('productModal').style.display='none'">キャンセル</button>
        <button type="submit" class="btn btn-primary">更新する</button>
      </div>
    </form>
  </div>
</div>

<!-- モーダル：取引先編集 -->
<div id="torihikiModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:8px;padding:24px;width:560px;max-width:95%;max-height:90vh;overflow-y:auto">
    <div style="font-weight:700;font-size:15px;margin-bottom:16px;color:#1B3A6B">取引先を編集</div>
    <form method="post">
      <input type="hidden" name="action" value="edit"><input type="hidden" name="torihikisaki_id" id="tid">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="form-group" style="grid-column:1/-1;margin:0"><label>取引先名</label><input type="text" name="f_torihikisaki_name" id="tname" class="form-control" required></div>
        <div class="form-group" style="margin:0"><label>取引先名カナ</label><input type="text" name="f_torihikisaki_kana" id="tkana" class="form-control"></div>
        <div class="form-group" style="margin:0"><label>外部コード</label><input type="text" name="f_code" id="tcode" class="form-control"></div>
        <div class="form-group" style="margin:0">
          <label>セグメント</label>
          <select name="f_segment" id="tsegment" class="form-control">
            <option value="">未設定</option>
            <?php foreach($segments as $s): ?><option value="<?= h($s) ?>"><?= h($s) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0"><label>状態</label><select name="f_active" id="tactive" class="form-control"><option>有効</option><option>無効</option></select></div>
        <div class="form-group" style="margin:0"><label>先方担当者名（代表）</label><input type="text" name="f_tantosha_name" id="ttanto" class="form-control"></div>
        <div class="form-group" style="margin:0"><label>電話番号</label><input type="text" name="f_tel" id="ttel" class="form-control"></div>
        <div class="form-group" style="margin:0"><label>FAX</label><input type="text" name="f_fax" id="tfax" class="form-control"></div>
        <div class="form-group" style="margin:0"><label>郵便番号</label><input type="text" name="f_zip" id="tzip" class="form-control"></div>
        <div class="form-group" style="grid-column:1/-1;margin:0"><label>住所</label><input type="text" name="f_address" id="taddr" class="form-control"></div>
        <div class="form-group" style="margin:0"><label>ホームページURL</label><input type="url" name="f_hp_url" id="thp" class="form-control"></div>
        <div class="form-group" style="margin:0"><label>地図URL</label><input type="url" name="f_map_url" id="tmap" class="form-control"></div>
        <div class="form-group" style="grid-column:1/-1;margin:0"><label>決算情報URL</label><input type="url" name="f_kessan_url" id="tkessan" class="form-control"></div>
        <div class="form-group" style="grid-column:1/-1;margin:0"><label>備考</label><input type="text" name="f_biko" id="tbiko" class="form-control"></div>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
        <button type="button" class="btn btn-gray" onclick="document.getElementById('torihikiModal').style.display='none'">キャンセル</button>
        <button type="submit" class="btn btn-primary">更新する</button>
      </div>
    </form>
  </div>
</div>

<!-- モーダル：部署編集 -->
<div id="bushoModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:8px;padding:24px;width:420px;max-width:95%;max-height:90vh;overflow-y:auto">
    <div style="font-weight:700;font-size:15px;margin-bottom:16px;color:#1B3A6B">部署を編集</div>
    <form method="post">
      <input type="hidden" name="action" value="edit"><input type="hidden" name="busho_id" id="bid">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="form-group" style="grid-column:1/-1;margin:0"><label>部署名</label><input type="text" name="f_busho_name" id="bname" class="form-control"></div>
        <div class="form-group" style="margin:0"><label>表示順</label><input type="number" name="f_sort_order" id="bsort" class="form-control"></div>
        <div class="form-group" style="margin:0"><label>状態</label><select name="f_active" id="bactive" class="form-control"><option>有効</option><option>無効</option></select></div>
        <div class="form-group" style="grid-column:1/-1;margin:0"><label>備考</label><input type="text" name="f_biko" id="bbiko" class="form-control"></div>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
        <button type="button" class="btn btn-gray" onclick="document.getElementById('bushoModal').style.display='none'">キャンセル</button>
        <button type="submit" class="btn btn-primary">更新する</button>
      </div>
    </form>
  </div>
</div>

<!-- モーダル：担当者編集 -->
<div id="tantoshaModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:8px;padding:24px;width:420px;max-width:95%;max-height:90vh;overflow-y:auto">
    <div style="font-weight:700;font-size:15px;margin-bottom:16px;color:#1B3A6B">担当者を編集</div>
    <form method="post">
      <input type="hidden" name="action" value="edit"><input type="hidden" name="tantosha_id" id="ttid">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="form-group" style="grid-column:1/-1;margin:0"><label>担当者名</label><input type="text" name="f_tantosha_name" id="ttname" class="form-control"></div>
        <div class="form-group" style="margin:0">
          <label>権限区分</label>
          <select name="f_kengen_kubun" id="ttkengen" class="form-control">
            <option value="一般">一般</option>
            <option value="部門管理者">部門管理者</option>
            <option value="管理者">管理者</option>
          </select>
        </div>
        <div class="form-group" style="margin:0">
          <label>部署</label>
          <select name="fk_busho_id" id="ttbusho" class="form-control">
            <option value="">未設定</option>
            <?php foreach($bushos as $b): ?><option value="<?= h($b['pk_busho_id']) ?>"><?= h($b['f_busho_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0"><label>メールアドレス</label><input type="email" name="f_email" id="ttemail" class="form-control"></div>
        <div class="form-group" style="margin:0"><label>携帯電話番号</label><input type="tel" name="f_tel" id="tttel" class="form-control"></div>
      </div>
      <div style="font-size:11px;color:#888;margin-top:8px">アカウント名・パスワードはこの画面からは変更できません（パスワードは「PW変更」から）。</div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
        <button type="button" class="btn btn-gray" onclick="document.getElementById('tantoshaModal').style.display='none'">キャンセル</button>
        <button type="submit" class="btn btn-primary">更新する</button>
      </div>
    </form>
  </div>
</div>

<!-- モーダル：PW変更 -->
<div id="passModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:8px;padding:24px;width:320px;max-width:90%">
    <div style="font-weight:700;font-size:15px;margin-bottom:16px;color:#1B3A6B">パスワード変更：<span id="passName"></span></div>
    <form method="post">
      <input type="hidden" name="action" value="change_pass"><input type="hidden" name="tantosha_id" id="passId">
      <div class="form-group"><label>新しいパスワード</label><input type="password" name="new_password" class="form-control" required autofocus></div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
        <button type="button" class="btn btn-gray" onclick="document.getElementById('passModal').style.display='none'">キャンセル</button>
        <button type="submit" class="btn btn-primary">変更する</button>
      </div>
    </form>
  </div>
</div>

<script>
const jokenData = <?= json_encode(array_column($jokens, null, 'fk_torihikisaki_id'), JSON_UNESCAPED_UNICODE) ?>;

function showFactoryEdit(f) {
  document.getElementById('fid').value=f.pk_factory_id;
  document.getElementById('fname').value=f.f_factory_name;
  document.getElementById('fzip').value=f.f_zip||'';
  document.getElementById('ftel').value=f.f_tel||'';
  document.getElementById('faddr').value=f.f_address||'';
  document.getElementById('ftanto').value=f.f_tanto_name||'';
  document.getElementById('fsort').value=f.f_sort_order||0;
  document.getElementById('fmadoguchi').value=f.fk_madoguchi_tantosha_id||'';
  document.getElementById('fdaihyoemail').value=f.f_daihyo_email||'';
  document.getElementById('factive').value=f.f_active;
  document.getElementById('fbiko').value=f.f_biko||'';
  document.getElementById('factoryModal').style.display='flex';
}
function showProductEdit(p) {
  document.getElementById('prid').value=p.pk_product_id;
  document.getElementById('prname').value=p.f_product_name;
  document.getElementById('prkubun').value=p.f_kubun||'商品';
  document.getElementById('prsegment').value=p.f_segment||'';
  document.getElementById('prsubcat').value=p.f_subcategory||'';
  document.getElementById('prcat').value=p.f_category||'';
  document.getElementById('prcode').value=p.f_code||'';
  document.getElementById('prunit').value=p.f_unit||'';
  document.getElementById('prprice').value=p.f_standard_price||'';
  document.getElementById('prsort').value=p.f_sort_order||0;
  document.getElementById('practive').value=p.f_active;
  document.getElementById('prbiko').value=p.f_biko||'';
  document.getElementById('productModal').style.display='flex';
}
function showTorihikiEdit(t) {
  document.getElementById('tid').value=t.pk_torihikisaki_id;
  document.getElementById('tname').value=t.f_torihikisaki_name;
  document.getElementById('tkana').value=t.f_torihikisaki_kana||'';
  document.getElementById('tcode').value=t.f_code||'';
  document.getElementById('tsegment').value=t.f_segment||'';
  document.getElementById('tactive').value=t.f_active||'有効';
  document.getElementById('ttanto').value=t.f_tantosha_name||'';
  document.getElementById('ttel').value=t.f_tel||'';
  document.getElementById('tfax').value=t.f_fax||'';
  document.getElementById('tzip').value=t.f_zip||'';
  document.getElementById('taddr').value=t.f_address||'';
  document.getElementById('thp').value=t.f_hp_url||'';
  document.getElementById('tmap').value=t.f_map_url||'';
  document.getElementById('tkessan').value=t.f_kessan_url||'';
  document.getElementById('tbiko').value=t.f_biko||'';
  document.getElementById('torihikiModal').style.display='flex';
}
function showPassForm(id, name) {
  document.getElementById('passId').value=id;
  document.getElementById('passName').textContent=name;
  document.getElementById('passModal').style.display='flex';
}
function showBushoEdit(b) {
  document.getElementById('bid').value=b.pk_busho_id;
  document.getElementById('bname').value=b.f_busho_name;
  document.getElementById('bsort').value=b.f_sort_order||0;
  document.getElementById('bactive').value=b.f_active||'有効';
  document.getElementById('bbiko').value=b.f_biko||'';
  document.getElementById('bushoModal').style.display='flex';
}
function showTantoshaEdit(t) {
  document.getElementById('ttid').value=t.pk_tantosha_id;
  document.getElementById('ttname').value=t.f_tantosha_name;
  document.getElementById('ttkengen').value=t.f_kengen_kubun||'一般';
  document.getElementById('ttbusho').value=t.fk_busho_id||'';
  document.getElementById('ttemail').value=t.f_email||'';
  document.getElementById('tttel').value=t.f_tel||'';
  document.getElementById('tantoshaModal').style.display='flex';
}
function loadJoken(tid) {
  const j = jokenData[tid] || {};
  document.getElementById('j_kaishu').value=j.f_kaishu_frequency||'';
  document.getElementById('j_site').value=j.f_payment_site||'';
  document.getElementById('j_method').value=j.f_payment_method||'';
  document.getElementById('j_contract').value=j.f_contract_date||'';
  document.getElementById('j_biko').value=j.f_biko||'';
}
['factoryModal','productModal','torihikiModal','passModal','bushoModal','tantoshaModal'].forEach(id => {
  document.getElementById(id).addEventListener('click', function(e) {
    if(e.target===this) this.style.display='none';
  });
});
</script>
<?= html_footer() ?>
