<?php
require_once 'db.php';
require_once 'common.php';
if ($_SESSION['kengen'] !== '管理者') { header('Location: index.php'); exit; }

$pdo = get_db();

// ---- テンプレートダウンロード ----
if (($_GET['action'] ?? '') === 'template') {
    $header = ['取引日','取引先名','取引先コード','商品名','部門名','金額','数量','備考'];
    $sample = ['2026-08-01','高松紙業株式会社','C0101','段ボール古紙','営業部','850000','50','7月分'];
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="uriage_template.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, $header);
    fputcsv($out, $sample);
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['csv_file']['tmp_name'])) {
    header('Location: uriage.php');
    exit;
}

$tmp = $_FILES['csv_file']['tmp_name'];
$raw = file_get_contents($tmp);

$enc = mb_detect_encoding($raw, ['UTF-8','SJIS-win','SJIS','EUC-JP'], true);
if ($enc && $enc !== 'UTF-8') {
    $raw = mb_convert_encoding($raw, 'UTF-8', $enc);
}
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

$lines = preg_split("/\r\n|\r|\n/", $raw);
$rows = [];
foreach ($lines as $line) {
    if (trim($line) === '') continue;
    $rows[] = str_getcsv($line);
}

$added = 0; $skipped = 0; $errors = [];
$batch = date('YmdHis') . '-' . substr(generate_uuid(), 0, 8);

$startIdx = 0;
if (!empty($rows[0]) && (in_array('取引先名', $rows[0]) || in_array('取引日', $rows[0]))) {
    $startIdx = 1;
}

$findToriByCode = $pdo->prepare("SELECT pk_torihikisaki_id, f_segment FROM t_torihikisaki WHERE f_code = ? AND f_code <> '' LIMIT 1");
$findToriByName = $pdo->prepare("SELECT pk_torihikisaki_id, f_segment FROM t_torihikisaki WHERE f_torihikisaki_name = ? LIMIT 1");
$findProdByName = $pdo->prepare("SELECT pk_product_id, f_segment FROM t_product WHERE f_product_name = ? LIMIT 1");
$findBushoByName = $pdo->prepare("SELECT pk_busho_id FROM t_busho WHERE f_busho_name = ? LIMIT 1");
$ins = $pdo->prepare("INSERT INTO t_uriage
    (pk_uriage_id,f_date,fk_torihikisaki_id,f_torihikisaki_name,fk_product_id,f_product_name,f_segment,fk_busho_id,f_busho_name,f_kingaku,f_suryo,f_biko,fk_tantosha_id,f_import_batch,f_created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");

for ($i = $startIdx; $i < count($rows); $i++) {
    $r = $rows[$i];
    $r = array_pad($r, 8, '');
    [$date, $toriName, $toriCode, $prodName, $bushoName, $kingaku, $suryo, $biko] = array_map('trim', array_slice($r, 0, 8));

    if ($date === '' || $kingaku === '') { $skipped++; continue; }
    $date = str_replace('/', '-', $date);
    if (!preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $date)) { $errors[] = ($i+1) . '行目：取引日の形式が不正です（' . $date . '）'; continue; }

    try {
        $tori_id = null; $segment = null;
        if ($toriCode !== '') {
            $findToriByCode->execute([$toriCode]);
            if ($row = $findToriByCode->fetch()) { $tori_id = $row['pk_torihikisaki_id']; $segment = $row['f_segment']; }
        }
        if (!$tori_id && $toriName !== '') {
            $findToriByName->execute([$toriName]);
            if ($row = $findToriByName->fetch()) { $tori_id = $row['pk_torihikisaki_id']; $segment = $row['f_segment']; }
        }

        $prod_id = null;
        if ($prodName !== '') {
            $findProdByName->execute([$prodName]);
            if ($row = $findProdByName->fetch()) { $prod_id = $row['pk_product_id']; if (!$segment) $segment = $row['f_segment']; }
        }

        $busho_id = null;
        if ($bushoName !== '') {
            $findBushoByName->execute([$bushoName]);
            if ($row = $findBushoByName->fetch()) { $busho_id = $row['pk_busho_id']; }
        }

        $ins->execute([
            generate_uuid(), $date, $tori_id, $toriName ?: null, $prod_id, $prodName ?: null, $segment,
            $busho_id, $bushoName ?: null, (float)str_replace(',', '', $kingaku), $suryo !== '' ? (float)str_replace(',', '', $suryo) : null,
            $biko, $_SESSION['tantosha_id'], $batch,
        ]);
        $added++;
    } catch (Exception $e) {
        $errors[] = ($i+1) . '行目：' . $e->getMessage();
    }
}

$msg = "取込完了：{$added} 件" . ($skipped ? "／スキップ {$skipped} 件（取引日・金額なし）" : '');
if ($errors) $msg .= '／エラー ' . count($errors) . ' 件';

header('Location: uriage.php?csv_msg=' . urlencode($msg) . ($errors ? '&csv_msg_type=danger' : '&csv_msg_type=success'));
exit;
