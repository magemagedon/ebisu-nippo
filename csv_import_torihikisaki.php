<?php
require_once 'db.php';
require_once 'common.php';
if ($_SESSION['kengen'] !== '管理者') { header('Location: index.php'); exit; }

$pdo = get_db();

// ---- テンプレートダウンロード ----
if (($_GET['action'] ?? '') === 'template') {
    $header = ['外部コード','取引先名','取引先名カナ','セグメント','先方担当者名','電話番号','FAX','郵便番号','住所','ホームページURL','備考'];
    $sample = ['C0101','高松紙業株式会社','タカマツシギョウ','古紙','大西 一郎','087-800-0001','','760-0000','香川県高松市番町1-1','https://example.com',''];
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="torihikisaki_template.csv"');
    echo "\xEF\xBB\xBF"; // BOM（Excelでの文字化け防止）
    $out = fopen('php://output', 'w');
    fputcsv($out, $header);
    fputcsv($out, $sample);
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['csv_file']['tmp_name'])) {
    header('Location: master.php?tab=torihikisaki');
    exit;
}

$tmp = $_FILES['csv_file']['tmp_name'];
$raw = file_get_contents($tmp);

// 文字コード自動判定（Shift-JIS / UTF-8 両対応）
$enc = mb_detect_encoding($raw, ['UTF-8','SJIS-win','SJIS','EUC-JP'], true);
if ($enc && $enc !== 'UTF-8') {
    $raw = mb_convert_encoding($raw, 'UTF-8', $enc);
}
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw); // BOM除去

$lines = preg_split("/\r\n|\r|\n/", $raw);
$rows = [];
foreach ($lines as $line) {
    if (trim($line) === '') continue;
    $rows[] = str_getcsv($line);
}

$added = 0; $updated = 0; $skipped = 0; $errors = [];

// 1行目はヘッダーとして無視（「取引先名」「外部コード」等が含まれる場合のみ自動判定、含まれなければ先頭行もデータとして扱う）
$startIdx = 0;
if (!empty($rows[0]) && (in_array('取引先名', $rows[0]) || in_array('外部コード', $rows[0]))) {
    $startIdx = 1;
}

$updStmt = $pdo->prepare("UPDATE t_torihikisaki SET f_code=?,f_torihikisaki_kana=?,f_segment=?,f_tantosha_name=?,f_tel=?,f_fax=?,f_zip=?,f_address=?,f_hp_url=?,f_biko=?,f_updated_at=NOW() WHERE pk_torihikisaki_id=?");
$insStmt = $pdo->prepare("INSERT INTO t_torihikisaki (pk_torihikisaki_id,f_code,f_torihikisaki_name,f_torihikisaki_kana,f_segment,f_tantosha_name,f_tel,f_fax,f_zip,f_address,f_hp_url,f_biko,f_active,f_created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
$findByCode = $pdo->prepare("SELECT pk_torihikisaki_id FROM t_torihikisaki WHERE f_code = ? AND f_code <> '' LIMIT 1");
$findByName = $pdo->prepare("SELECT pk_torihikisaki_id FROM t_torihikisaki WHERE f_torihikisaki_name = ? LIMIT 1");

for ($i = $startIdx; $i < count($rows); $i++) {
    $r = $rows[$i];
    // 列不足を空文字で補う
    $r = array_pad($r, 11, '');
    [$code, $name, $kana, $segment, $tanto, $tel, $fax, $zip, $address, $hp, $biko] = array_map('trim', array_slice($r, 0, 11));

    if ($name === '') { $skipped++; continue; }

    try {
        $existId = null;
        if ($code !== '') {
            $findByCode->execute([$code]);
            $existId = $findByCode->fetchColumn() ?: null;
        }
        if (!$existId) {
            $findByName->execute([$name]);
            $existId = $findByName->fetchColumn() ?: null;
        }

        if ($existId) {
            $updStmt->execute([$code,$kana,$segment?:null,$tanto,$tel,$fax,$zip,$address,$hp,$biko,$existId]);
            $updated++;
        } else {
            $insStmt->execute([generate_uuid(),$code,$name,$kana,$segment?:null,$tanto,$tel,$fax,$zip,$address,$hp,$biko,'有効']);
            $added++;
        }
    } catch (Exception $e) {
        $errors[] = ($i+1) . '行目：' . $e->getMessage();
    }
}

$msg = "取込完了：新規 {$added} 件／更新 {$updated} 件" . ($skipped ? "／スキップ {$skipped} 件（取引先名なし）" : '');
if ($errors) $msg .= '／エラー ' . count($errors) . ' 件';

header('Location: master.php?tab=torihikisaki&csv_msg=' . urlencode($msg) . ($errors ? '&csv_msg_type=danger' : '&csv_msg_type=success'));
exit;
